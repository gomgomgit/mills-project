<?php

namespace App\Services;

use App\Exceptions\ProductionLineHasStationsException;
use App\Models\BusinessUnit;
use App\Models\Machinery;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * ProductionLineService — screen-036--kelola-production-line /
 * usecase-036--kelola-production-line (Kelola Production Line —
 * admin-only master-data CRUD, inserted 2026-08-20 between Business Unit
 * and Station in the hierarchy: Business Unit → Production Line → Station
 * → Machinery Group → Machinery).
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * ProductionLineController) and the Livewire component (App\Livewire\
 * MasterData\KelolaProductionLine) — same code path, mirrors every other
 * master-data service in this codebase (BusinessUnitService/
 * MachineryGroupService/StationService).
 *
 * OWNS the 15-canonical-station auto-provisioning behavior that used to
 * live on BusinessUnitService::create() (moved here 2026-08-20, since
 * each Production Line now gets its own full set of stations, not one
 * shared set per Business Unit — see DEFAULT_STATIONS below).
 * BusinessUnitService::create() no longer auto-creates ANY stations or
 * production lines — a new Business Unit has zero production lines until
 * an Admin explicitly creates one via this screen.
 */
class ProductionLineService
{
    /**
     * The 15 canonical stations every Production Line is auto-provisioned
     * with on create() — 3 MVP-functional (weighbridge/grading/
     * cages-track, `is_active` true) + 12 `other`-typed placeholders for
     * future station schemas (`is_active` false). Identical list to the
     * one formerly on BusinessUnitService::DEFAULT_STATIONS (moved here
     * verbatim, not duplicated — BusinessUnitService no longer has its own
     * copy) and to mobile's `DEFAULT_STATIONS`
     * (mobile/src/services/localSchema.ts). `code` is intentionally left
     * null for every row — nullable+unique at the DB layer, multiple null
     * `code`s across many production lines never collide.
     */
    protected const DEFAULT_STATIONS = [
        ['name' => 'Weighbridge', 'type' => 'weighbridge', 'is_active' => true],
        ['name' => 'Grading', 'type' => 'grading', 'is_active' => true],
        ['name' => 'Cages Track', 'type' => 'cages-track', 'is_active' => true],
        ['name' => 'Sterilizer', 'type' => 'other', 'is_active' => false],
        ['name' => 'Thresher', 'type' => 'other', 'is_active' => false],
        ['name' => 'Press', 'type' => 'other', 'is_active' => false],
        ['name' => 'Clarification', 'type' => 'other', 'is_active' => false],
        ['name' => 'Kernel Plant', 'type' => 'other', 'is_active' => false],
        ['name' => 'Boiler', 'type' => 'other', 'is_active' => false],
        ['name' => 'Effluent Treatment', 'type' => 'other', 'is_active' => false],
        ['name' => 'Loading Ramp', 'type' => 'other', 'is_active' => false],
        ['name' => 'Digester', 'type' => 'other', 'is_active' => false],
        ['name' => 'Engine Room', 'type' => 'other', 'is_active' => false],
        ['name' => 'Water Treatment', 'type' => 'other', 'is_active' => false],
        ['name' => 'Bulking Storage', 'type' => 'other', 'is_active' => false],
    ];

    /**
     * listProductionLines() — business_logic step "list": paginate,
     * optional business_unit_id filter, eager-load businessUnit (for
     * business_unit_name) + withCount('stations') (for station_count).
     */
    public function listProductionLines(int $page, int $perPage, ?string $businessUnitId = null): array
    {
        $query = ProductionLine::query()
            ->with('businessUnit')
            ->withCount('stations')
            ->orderBy('name');

        if ($businessUnitId !== null && $businessUnitId !== '') {
            $query->where('business_unit_id', $businessUnitId);
        }

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (ProductionLine $productionLine) => $this->toRow($productionLine))
            ->all();

        return $formatted;
    }

    /**
     * businessUnitOptions() — feeds the Business Unit-select dropdown on
     * the Production Line create/edit form. Mirrors
     * MachineryGroupService::stationOptions() / CompanyService::
     * corporateOptions() exactly.
     */
    public function businessUnitOptions(): array
    {
        return BusinessUnit::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (BusinessUnit $businessUnit) => [
                'id' => $businessUnit->id,
                'name' => $businessUnit->name,
            ])
            ->all();
    }

    /**
     * listCurrentForUser() — GET /api/production-lines/current. Self-scoped
     * counterpart to listProductionLines(), mirrors MillSettingService::
     * getCurrent()'s reasoning exactly: mobile/web clients never know their
     * own business_unit_id server-side ahead of time, so this resolves it
     * from the authenticated user instead of trusting a client-supplied
     * business_unit_id. Unlike getCurrent() (one mill-setting row per
     * business unit), a business unit can now have SEVERAL production
     * lines, so this returns a plain list, not a single row.
     *
     * @throws ModelNotFoundException if the user has no business_unit_id
     */
    public function listCurrentForUser(User $user): array
    {
        if ($user->business_unit_id === null) {
            throw new ModelNotFoundException();
        }

        return ProductionLine::query()
            ->where('business_unit_id', $user->business_unit_id)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (ProductionLine $productionLine) => [
                'id' => $productionLine->id,
                'name' => $productionLine->name,
                'code' => $productionLine->code,
            ])
            ->all();
    }

    /**
     * currentStations() — GET /api/production-lines/current/stations.
     * Self-scoped station list for ONE production line, scoped to the
     * authenticated user's own business unit (a production_line_id
     * belonging to a different business unit is rejected as not-found,
     * never trusted implicitly) — feeds the mobile Station List grid after
     * the Production Line picker step. Each row includes machinery_count
     * (COUNT(machinery WHERE station_id=...)) for cages-track-type
     * stations only (the same figure FormCagesTrack's grid-column-count
     * preview uses, via CagesTrackRecordService::machineryCountForStation()
     * — computed independently here since this endpoint has no dependency
     * on CagesTrackRecordService, but the query is identical); other
     * station types get null, since machinery_count is meaningless for
     * them on this screen.
     *
     * @throws ModelNotFoundException if the user has no business_unit_id,
     *                                or productionLineId doesn't belong to
     *                                the user's own business unit
     */
    public function currentStations(User $user, string $productionLineId): array
    {
        if ($user->business_unit_id === null) {
            throw new ModelNotFoundException();
        }

        $productionLine = ProductionLine::query()
            ->where('id', $productionLineId)
            ->where('business_unit_id', $user->business_unit_id)
            ->firstOrFail();

        return Station::query()
            ->where('production_line_id', $productionLine->id)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'icon', 'is_active'])
            ->map(fn (Station $station) => [
                'id' => $station->id,
                'name' => $station->name,
                'type' => $station->type instanceof \App\Enums\StationType ? $station->type->value : $station->type,
                'icon' => $station->icon,
                'is_active' => $station->is_active,
                'machinery_count' => $station->type instanceof \App\Enums\StationType && $station->type->value === 'cages-track'
                    ? Machinery::where('station_id', $station->id)->count()
                    : null,
            ])
            ->all();
    }

    /**
     * create() — business_logic step "create": validate business_unit_id
     * exists → validate name required → validate code unique-if-filled →
     * 422 if any invalid → insert, then auto-provision the 15 canonical
     * DEFAULT_STATIONS rows for the new Production Line (both
     * `production_line_id` AND the denormalized `business_unit_id` are set
     * on every station row). `created_by` is always set from the
     * authenticated admin — never accepted from $data.
     *
     * The Production Line insert and the 15 Station inserts run inside one
     * DB::transaction() — either all 16 rows are written, or none are,
     * mirrors the exact transactional guarantee BusinessUnitService::
     * create() used to provide before this behavior moved here.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(array $data): array
    {
        $attributes = $this->validate($data, null);
        $attributes['created_by'] = auth()->id();

        $productionLine = DB::transaction(function () use ($attributes) {
            $productionLine = ProductionLine::create($attributes);

            foreach (self::DEFAULT_STATIONS as $station) {
                Station::create([
                    'business_unit_id' => $productionLine->business_unit_id,
                    'production_line_id' => $productionLine->id,
                    'name' => $station['name'],
                    'type' => $station['type'],
                    'is_active' => $station['is_active'],
                ]);
            }

            return $productionLine;
        });

        $productionLine->load('businessUnit');
        $productionLine->loadCount('stations');

        return $this->toRow($productionLine);
    }

    /**
     * update() — business_logic step "update": validate id exists → 404
     * if not → same field validation as create() (code unique excluding
     * self) → 422 if any invalid → update. Does NOT touch this production
     * line's existing stations — station provisioning only ever happens
     * once, at create() time.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function update(string $id, array $data): array
    {
        $productionLine = ProductionLine::findOrFail($id);

        $attributes = $this->validate($data, $productionLine->id);
        $attributes['updated_by'] = auth()->id();

        $productionLine->update($attributes);
        $productionLine->load('businessUnit');
        $productionLine->loadCount('stations');

        return $this->toRow($productionLine);
    }

    /**
     * delete() — business_logic step "delete": validate id exists → 404
     * if not → count Station WHERE production_line_id=id → 409
     * PRODUCTION_LINE_HAS_STATIONS if any exist → else delete.
     *
     * @throws ModelNotFoundException
     * @throws ProductionLineHasStationsException
     */
    public function delete(string $id): void
    {
        $productionLine = ProductionLine::findOrFail($id);

        if ($productionLine->stations()->count() > 0) {
            throw new ProductionLineHasStationsException();
        }

        $productionLine->delete();
    }

    /**
     * Validates business_unit_id, name, code, and description in a single
     * Validator pass — matches shared_decisions.error_format's
     * `{ message, errors: { field: [...] } }` shape.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> validated attributes (business_unit_id,
     *                              name, code, description)
     *
     * @throws ValidationException
     */
    protected function validate(array $data, ?string $excludeId): array
    {
        $codeUniqueRule = Rule::unique('production_lines', 'code');

        if ($excludeId !== null) {
            $codeUniqueRule = $codeUniqueRule->ignore($excludeId);
        }

        $payload = [
            'business_unit_id' => $data['business_unit_id'] ?? null,
            'name' => $this->emptyToNull($data['name'] ?? null),
            'code' => $this->emptyToNull($data['code'] ?? null),
            'description' => $this->emptyToNull($data['description'] ?? null),
        ];

        $rules = [
            'business_unit_id' => ['required', 'string', Rule::exists('business_units', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', $codeUniqueRule],
            'description' => ['nullable', 'string', 'max:255'],
        ];

        $messages = [
            'business_unit_id.required' => 'Business Unit wajib dipilih.',
            'business_unit_id.exists' => 'Business Unit yang dipilih tidak ditemukan.',
            'name.required' => 'Nama Production Line wajib diisi.',
            'name.max' => 'Nama Production Line maksimal 255 karakter.',
            'code.max' => 'Kode Production Line maksimal 255 karakter.',
            'code.unique' => 'Kode Production Line sudah digunakan.',
            'description.max' => 'Deskripsi maksimal 255 karakter.',
        ];

        return Validator::make($payload, $rules, $messages)->validate();
    }

    /**
     * Normalizes an empty-string input to null — mirrors
     * MachineryGroupService::emptyToNull()'s identical helper.
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
     * Maps a ProductionLine (with businessUnit eager-loaded + stations
     * count already loaded via withCount()/loadCount()) to the endpoints'
     * shared row shape.
     */
    protected function toRow(ProductionLine $productionLine): array
    {
        return [
            'id' => $productionLine->id,
            'business_unit_id' => $productionLine->business_unit_id,
            'business_unit_name' => optional($productionLine->businessUnit)->name,
            'name' => $productionLine->name,
            'code' => $productionLine->code,
            'description' => $productionLine->description,
            'station_count' => (int) ($productionLine->stations_count ?? 0),
            'created_by' => $productionLine->created_by,
            'updated_by' => $productionLine->updated_by,
            'created_at' => optional($productionLine->created_at)->toIso8601String(),
        ];
    }
}
