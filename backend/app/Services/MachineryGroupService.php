<?php

namespace App\Services;

use App\Exceptions\MachineryGroupHasMachineryException;
use App\Models\Machinery;
use App\Models\MachineryGroup;
use App\Models\Station;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * MachineryGroupService — screen-033--kelola-machinery-group /
 * usecase-033--kelola-machinery-group (Kelola Machinery Group —
 * admin-only master-data CRUD, one level below Station).
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * MachineryGroupController) and the Livewire component (App\Livewire\
 * MasterData\KelolaMachineryGroup) — same code path, no internal HTTP
 * round-trip, so validation/business rules stay identical between the two
 * entry points. Mirrors App\Services\StationService's structure exactly,
 * with one CRITICAL structural divergence this screen exists to enforce:
 *
 *  - `production_line_id` is NEVER read from the create()/update() $data
 *    payload, even if present — validate() below deliberately excludes it
 *    from both $payload and the returned validated array. create()/
 *    update() instead look up the (already-validated-to-exist) Station by
 *    its `station_id` and copy that Station's own `production_line_id`
 *    onto the MachineryGroup being written. This guarantees
 *    machinery_groups.production_line_id can never drift from its parent
 *    Station's production_line_id, regardless of what a client sends —
 *    the structural hierarchy-consistency guarantee this screen exists
 *    to enforce (see App\Models\MachineryGroup's docblock).
 *  - `group_code` is REQUIRED (unlike Station's optional `code`) and
 *    globally unique — mirrors Corporate/Company/BusinessUnit's required
 *    `*_code` fields rather than Station's nullable one.
 *  - No cross-field is_active/type rule (MachineryGroup has no
 *    is_active/type fields at all).
 */
class MachineryGroupService
{
    /**
     * listMachineryGroups() — business_logic step "list": paginate,
     * optional station_id filter, eager-load station + productionLine (for
     * station_name/production_line_name) + withCount('machinery') (for
     * machinery_count) — a single query regardless of page size, same
     * approach as StationService::listStations()'s
     * with('businessUnit')/withCount('machineryGroups').
     */
    public function listMachineryGroups(int $page, int $perPage, ?string $stationId = null): array
    {
        $query = MachineryGroup::query()
            ->with(['station', 'productionLine'])
            ->withCount('machinery')
            ->orderBy('group_code');

        if ($stationId !== null && $stationId !== '') {
            $query->where('station_id', $stationId);
        }

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (MachineryGroup $machineryGroup) => $this->toRow($machineryGroup))
            ->all();

        return $formatted;
    }

    /**
     * stationOptions() — business_logic step "stationOptions": SELECT
     * id,name,production_line_id from all Station, ordered by name,
     * unpaginated — feeds the Station-select dropdown on the Machinery
     * Group create/edit form. `production_line_id` is included (unlike
     * StationService::businessUnitOptions()'s plain id/name shape) so the
     * FE can copy/display it client-side before submit — the server
     * independently re-derives production_line_id from station_id again
     * anyway on create()/update(), never trusting client input for that
     * field (see this class's own docblock).
     */
    public function stationOptions(): array
    {
        return Station::query()
            ->orderBy('name')
            ->get(['id', 'name', 'production_line_id'])
            ->map(fn (Station $station) => [
                'id' => $station->id,
                'name' => $station->name,
                'production_line_id' => $station->production_line_id,
            ])
            ->all();
    }

    /**
     * create() — business_logic step "create": validate station_id
     * exists → validate group_code required+unique globally → validate
     * description/unit/workshop_factor/cost_per_equipment nullable → 422
     * if any invalid → copy production_line_id from the found Station →
     * insert.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     * @throws ModelNotFoundException
     */
    public function create(array $data): array
    {
        $attributes = $this->validate($data, null);

        // station_id was already validated to exist via Rule::exists
        // above — findOrFail() here is a defensive re-fetch (the actual
        // Station row is needed regardless, to copy its production_line_id)
        // rather than a second validation pass; a race where the Station
        // is deleted between validate() and this line is an acceptable,
        // extremely narrow edge case shared by every sibling
        // Service::create() in this codebase.
        $station = Station::findOrFail($attributes['station_id']);
        $attributes['production_line_id'] = $station->production_line_id;

        $machineryGroup = MachineryGroup::create($attributes);
        $machineryGroup->load(['station', 'productionLine']);
        $machineryGroup->loadCount('machinery');

        return $this->toRow($machineryGroup);
    }

    /**
     * update() — business_logic step "update": validate id exists → 404
     * if not → same field validation as create() (group_code unique
     * excluding self) → 422 if any invalid → re-copy production_line_id
     * from the (possibly changed) station_id → update.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function update(string $id, array $data): array
    {
        $machineryGroup = MachineryGroup::findOrFail($id);

        $attributes = $this->validate($data, $machineryGroup->id);

        $station = Station::findOrFail($attributes['station_id']);
        $attributes['production_line_id'] = $station->production_line_id;

        $machineryGroup->update($attributes);
        $machineryGroup->load(['station', 'productionLine']);
        $machineryGroup->loadCount('machinery');

        return $this->toRow($machineryGroup);
    }

    /**
     * delete() — business_logic step "delete": validate id exists → 404
     * if not → count Machinery WHERE machinery_group_id=id → 409
     * MACHINERY_GROUP_HAS_MACHINERY if non-zero → else delete.
     *
     * @throws ModelNotFoundException
     * @throws MachineryGroupHasMachineryException
     */
    public function delete(string $id): void
    {
        $machineryGroup = MachineryGroup::findOrFail($id);

        $machineryCount = Machinery::where('machinery_group_id', $id)->count();

        if ($machineryCount > 0) {
            throw new MachineryGroupHasMachineryException();
        }

        $machineryGroup->delete();
    }

    /**
     * Validates station_id, group_code, description, unit,
     * workshop_factor, and cost_per_equipment in a single Validator pass
     * — matches shared_decisions.error_format's
     * `{ message, errors: { field: [...] } }` shape. Mirrors
     * StationService::validate()'s structure.
     *
     * DELIBERATELY never validates or returns `production_line_id` — it is
     * not part of this screen's writable field set at all (see this
     * class's own docblock); create()/update() derive it themselves from
     * the Station found via `station_id`.
     *
     * @param  array<string, mixed>  $data  raw create()/update() payload
     * @param  string|null  $excludeId  the machinery group's own id on
     *                                  update() (excluded from the
     *                                  group_code-uniqueness check), null
     *                                  on create()
     * @return array<string, mixed> validated attributes (station_id,
     *                              group_code, description, unit,
     *                              workshop_factor, cost_per_equipment),
     *                              ready for MachineryGroup::create()/
     *                              ->update() once production_line_id is
     *                              added by the caller
     *
     * @throws ValidationException
     */
    protected function validate(array $data, ?string $excludeId): array
    {
        $groupCodeUniqueRule = Rule::unique('machinery_groups', 'group_code');

        if ($excludeId !== null) {
            $groupCodeUniqueRule = $groupCodeUniqueRule->ignore($excludeId);
        }

        $payload = [
            'station_id' => $data['station_id'] ?? null,
            'group_code' => $this->emptyToNull($data['group_code'] ?? null),
            'description' => $this->emptyToNull($data['description'] ?? null),
            'unit' => $this->emptyToNull($data['unit'] ?? null),
            'workshop_factor' => $this->emptyToNull($data['workshop_factor'] ?? null),
            'cost_per_equipment' => $this->emptyToNull($data['cost_per_equipment'] ?? null),
        ];

        $rules = [
            'station_id' => ['required', 'string', Rule::exists('stations', 'id')],
            'group_code' => ['required', 'string', 'max:255', $groupCodeUniqueRule],
            'description' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'workshop_factor' => ['nullable', 'numeric'],
            'cost_per_equipment' => ['nullable', 'numeric'],
        ];

        $messages = [
            'station_id.required' => 'Station wajib dipilih.',
            'station_id.exists' => 'Station yang dipilih tidak ditemukan.',
            'group_code.required' => 'Kode Machinery Group wajib diisi.',
            'group_code.max' => 'Kode Machinery Group maksimal 255 karakter.',
            'group_code.unique' => 'Kode Machinery Group sudah digunakan.',
            'description.max' => 'Deskripsi maksimal 255 karakter.',
            'unit.max' => 'Unit maksimal 255 karakter.',
            'workshop_factor.numeric' => 'Workshop Factor harus berupa angka.',
            'cost_per_equipment.numeric' => 'Cost per Equipment harus berupa angka.',
        ];

        $validated = Validator::make($payload, $rules, $messages)->validate();

        // Cast the two numeric fields to real PHP floats before returning
        // — the `numeric` rule accepts numeric strings (e.g. from an HTML
        // text/number input) but does not itself coerce the type, and
        // MachineryGroup::$casts's 'float' cast (App\Models\MachineryGroup)
        // only normalizes on READ, not on WRITE.
        if ($validated['workshop_factor'] !== null) {
            $validated['workshop_factor'] = (float) $validated['workshop_factor'];
        }
        if ($validated['cost_per_equipment'] !== null) {
            $validated['cost_per_equipment'] = (float) $validated['cost_per_equipment'];
        }

        return $validated;
    }

    /**
     * Normalizes an empty-string input to null, so a "cleared" optional
     * (or numeric-but-blank) field saves as NULL rather than "" —
     * mirrors StationService::emptyToNull()'s identical helper.
     */
    protected function emptyToNull(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }

    /**
     * Maps a MachineryGroup (with station+productionLine eager-loaded +
     * machinery count already loaded via withCount()/loadCount()) to the
     * endpoints' shared row shape.
     */
    protected function toRow(MachineryGroup $machineryGroup): array
    {
        return [
            'id' => $machineryGroup->id,
            'production_line_id' => $machineryGroup->production_line_id,
            'production_line_name' => optional($machineryGroup->productionLine)->name,
            'station_id' => $machineryGroup->station_id,
            'station_name' => optional($machineryGroup->station)->name,
            'group_code' => $machineryGroup->group_code,
            'description' => $machineryGroup->description,
            'unit' => $machineryGroup->unit,
            'workshop_factor' => $machineryGroup->workshop_factor !== null ? (float) $machineryGroup->workshop_factor : null,
            'cost_per_equipment' => $machineryGroup->cost_per_equipment !== null ? (float) $machineryGroup->cost_per_equipment : null,
            'machinery_count' => (int) ($machineryGroup->machinery_count ?? 0),
            'created_at' => optional($machineryGroup->created_at)->toIso8601String(),
        ];
    }
}
