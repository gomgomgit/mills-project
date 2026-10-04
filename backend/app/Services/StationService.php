<?php

namespace App\Services;

use App\Enums\StationType;
use App\Exceptions\StationHasMachineryException;
use App\Models\BusinessUnit;
use App\Models\Machinery;
use App\Models\MachineryGroup;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\StationType as StationTypeModel;
use App\Rules\UniqueCaseInsensitive;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * StationService — screen-030--kelola-station /
 * usecase-030--kelola-station (Kelola Station — admin-only master-data
 * CRUD, one level below Business Unit).
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * StationController) and the Livewire component (App\Livewire\
 * MasterData\KelolaStation) — same code path, no internal HTTP
 * round-trip, so validation/business rules stay identical between the two
 * entry points. Mirrors App\Services\BusinessUnitService's structure
 * exactly, with two deliberate divergences:
 *
 *  - No `logo` upload at all — Station has no such field. This class has
 *    no LOGO_DISK/LOGO_DIRECTORY constant, no UploadedFile parameter, no
 *    storeLogo() method.
 *  - `code` is OPTIONAL (nullable) rather than required — a Station may
 *    legitimately have no code — but still globally unique when present
 *    (`nullable` + `Rule::unique(...)` together is the correct idiomatic
 *    Laravel approach: `unique` only rejects a genuine duplicate
 *    non-null value, multiple NULL rows are always allowed to coexist).
 *  - `is_active` carries an extra cross-field rule on top of the plain
 *    `boolean` rule: it may only be true when `type` is one of
 *    weighbridge/grading/cages-track — never true when `type` is
 *    "other". This is enforced via a `Validator::make(...)->after(...)`
 *    closure alongside the rest of this class's single-pass validation
 *    (see validate() below), the same technique used throughout this
 *    codebase whenever a rule can't be expressed as a plain Laravel
 *    validation rule string.
 */
class StationService
{
    /**
     * listStations() — business_logic step "list": paginate, optional
     * business_unit_id filter, eager-load businessUnit (for
     * business_unit_name) + withCount('machineryGroups') (for
     * machinery_group_count) — a single query regardless of page size,
     * same approach as BusinessUnitService::listBusinessUnits()'s
     * with('company')/withCount('stations').
     */
    public function listStations(int $page, int $perPage, ?string $businessUnitId = null, ?string $productionLineId = null): array
    {
        // Diurutkan per Production Line lalu nama: tiap line punya set
        // station yang sama (18 tipe), jadi tanpa kolom/urutan line daftar
        // tampak berisi station yang sama 2–3 kali (temuan audit #10).
        $query = Station::query()
            ->with(['businessUnit', 'productionLine'])
            ->withCount('machineryGroups')
            ->orderBy(
                ProductionLine::query()->select('name')->whereColumn('production_lines.id', 'stations.production_line_id')
            )
            ->orderBy('name');

        if ($businessUnitId !== null && $businessUnitId !== '') {
            $query->where('business_unit_id', $businessUnitId);
        }

        if ($productionLineId !== null && $productionLineId !== '') {
            $query->where('production_line_id', $productionLineId);
        }

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (Station $station) => $this->toRow($station))
            ->all();

        return $formatted;
    }

    /**
     * businessUnitOptions() — business_logic step "businessUnitOptions":
     * SELECT id,name from all BusinessUnit, ordered by name, unpaginated
     * — feeds the Business Unit-select dropdown on the Station
     * create/edit form. Mirrors
     * BusinessUnitService::companyOptions() exactly.
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
     * productionLineOptions() — feeds the Production Line-select on the
     * Station create/edit form, cascaded from the chosen Business Unit
     * (added 2026-08-20, entity-catalog v9: production_line_id is now a
     * required FK on stations). Returns an empty list when
     * $businessUnitId is null/blank — mirrors
     * FormGrading::loadWeighbridgeOptions()'s "nothing selected yet"
     * behaviour.
     */
    /**
     * Pilihan Type station — dari master station_types (aktif, urutan
     * proses), BUKAN daftar 4 tipe yang ditulis tangan seperti dulu: edit
     * station "Boiler Room" menampilkan Type kosong karena tipenya tidak ada
     * di daftar itu (temuan audit #7). $includeCode menjaga tipe station
     * yang sedang diedit tetap muncul walau tipenya sudah dinonaktifkan.
     *
     * @return list<array{value: string, label: string}>
     */
    public function typeOptions(?string $includeCode = null): array
    {
        return StationTypeModel::query()
            ->where(function ($query) use ($includeCode) {
                $query->where('is_active', true);

                if ($includeCode !== null && $includeCode !== '') {
                    $query->orWhere('code', $includeCode);
                }
            })
            ->orderBy('sort_order')
            ->get(['code', 'name'])
            ->map(fn (StationTypeModel $type) => ['value' => $type->code, 'label' => $type->name])
            ->all();
    }

    /**
     * code → nama tampilan ("cpo-dispatch" → "CPO Dispatch"), menggantikan
     * ucfirst(slug) ("Cpo dispatch") di view.
     *
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return StationTypeModel::query()->pluck('name', 'code')->all();
    }

    /**
     * Satu Production Line hanya boleh punya SATU station per tipe, kecuali
     * "Other" (temuan audit #7: dua Weighbridge di line yang sama diterima).
     * Dicek di aplikasi, bukan indeks unik DB — data lama (fixture e2e)
     * sudah berisi duplikat, dan station lama itu harus tetap bisa diedit:
     * karena itu pada update pemeriksaan hanya berlaku bila line ATAU tipe
     * station tersebut berubah.
     *
     * @return string|null pesan error, atau null bila tidak bentrok
     */
    public function duplicateTypeMessage(?string $productionLineId, ?string $type, ?string $excludeId = null): ?string
    {
        if (! $productionLineId || ! $type || $type === StationType::Other->value) {
            return null;
        }

        if ($excludeId !== null) {
            $current = Station::find($excludeId);
            $currentType = $current?->type instanceof StationType ? $current->type->value : $current?->type;

            if ($current !== null && $current->production_line_id === $productionLineId && $currentType === $type) {
                return null;
            }
        }

        $exists = Station::query()
            ->where('production_line_id', $productionLineId)
            ->where('type', $type)
            ->when($excludeId !== null, fn ($query) => $query->where('id', '!=', $excludeId))
            ->exists();

        if (! $exists) {
            return null;
        }

        $label = self::typeLabels()[$type] ?? $type;

        return "Production Line ini sudah memiliki station bertipe {$label}. Satu Production Line hanya boleh memiliki satu station per tipe (kecuali Other).";
    }

    /**
     * Tabel record stasiun (18) — semuanya ber-FK station_id
     * (restrictOnDelete) dan production_line_id. Dipakai penjaga hapus
     * Production Line (ProductionLineService::delete()): station yang sudah
     * punya record TIDAK boleh ikut terhapus. Sama dengan daftar migrasi
     * 2026_09_28_000041_add_production_line_id_to_18_record_tables.
     */
    public const RECORD_TABLES = [
        'boiler_room_records', 'cages_track_records', 'clarification_records',
        'cpo_dispatch_records', 'depricarping_records', 'effluent_plant_records',
        'engine_room_records', 'grading_records', 'kernel_dispatch_records',
        'kernel_plant_records', 'pressing_records', 'process_quality_control_records',
        'process_water_records', 'solid_waste_disposal_records', 'sterilizer_records',
        'storage_tank_records', 'threshing_records', 'weighbridge_records',
    ];

    public function productionLineOptions(?string $businessUnitId): array
    {
        if ($businessUnitId === null || $businessUnitId === '') {
            return [];
        }

        return ProductionLine::query()
            ->where('business_unit_id', $businessUnitId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ProductionLine $productionLine) => [
                'id' => $productionLine->id,
                'name' => $productionLine->name,
            ])
            ->all();
    }

    /**
     * create() — business_logic step "create": validate business_unit_id
     * exists → validate production_line_id exists AND belongs to
     * business_unit_id → validate name required → validate type required+in-enum →
     * validate is_active boolean + cross-field "not true when
     * type=other" → validate code nullable+unique globally → validate
     * description nullable → 422 if any invalid → insert. Station has no
     * created_by/updated_by columns (confirmed against
     * 2025_01_15_000004_create_stations_table.php) — never set here.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(array $data): array
    {
        $attributes = $this->validate($data, null);

        $station = Station::create($attributes);
        $station->load(['businessUnit', 'productionLine']);
        $station->loadCount('machineryGroups');

        return $this->toRow($station);
    }

    /**
     * update() — business_logic step "update": validate id exists → 404
     * if not → same field validation as create() (code unique excluding
     * self) → 422 if any invalid → update.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function update(string $id, array $data): array
    {
        $station = Station::findOrFail($id);

        $attributes = $this->validate($data, $station->id);

        $station->update($attributes);
        $station->load(['businessUnit', 'productionLine']);
        $station->loadCount('machineryGroups');

        return $this->toRow($station);
    }

    /**
     * delete() — business_logic step "delete": validate id exists → 404
     * if not → count MachineryGroup WHERE station_id=id + count Machinery
     * WHERE station_id=id → 409 STATION_HAS_MACHINERY if EITHER count is
     * non-zero → else delete.
     *
     * @throws ModelNotFoundException
     * @throws StationHasMachineryException
     */
    public function delete(string $id): void
    {
        $station = Station::findOrFail($id);

        $machineryGroupCount = MachineryGroup::where('station_id', $id)->count();
        $machineryCount = Machinery::where('station_id', $id)->count();

        if ($machineryGroupCount > 0 || $machineryCount > 0) {
            throw new StationHasMachineryException;
        }

        $station->delete();
    }

    /**
     * Validates business_unit_id, name, type, is_active, code, and
     * description in a single Validator pass (rather than several
     * sequential validate() calls) so a 422 response can carry every
     * invalid field's errors at once — matches
     * shared_decisions.error_format's `{ message, errors: { field: [...] } }`
     * shape. Mirrors BusinessUnitService::validate()'s structure.
     *
     * Returns the validated attribute array ready to pass straight into
     * Station::create()/->update() — this is deliberately a single
     * combined validate()+normalize() step (unlike
     * BusinessUnitService::validate(), which is called after a separate
     * normalizeTextFields() pass) since Station has far fewer fields and
     * no distinction between "text fields normalized separately" vs
     * "fields validated inline" is worth the extra indirection here.
     *
     * @param  array<string, mixed>  $data  raw create()/update() payload
     * @param  string|null  $excludeId  the station's own id on update()
     *                                  (excluded from the code-uniqueness
     *                                  check), null on create()
     * @return array<string, mixed> validated attributes, ready for
     *                              Station::create()/->update()
     *
     * @throws ValidationException
     */
    protected function validate(array $data, ?string $excludeId): array
    {
        $codeUniqueRule = UniqueCaseInsensitive::on('stations', 'code');

        if ($excludeId !== null) {
            $codeUniqueRule = $codeUniqueRule->ignore($excludeId);
        }

        $payload = [
            'business_unit_id' => $data['business_unit_id'] ?? null,
            'production_line_id' => $data['production_line_id'] ?? null,
            'name' => $data['name'] ?? null,
            'type' => $data['type'] ?? null,
            'code' => $this->emptyToNull($data['code'] ?? null),
            'description' => $this->emptyToNull($data['description'] ?? null),
        ];

        // `is_active` is validated separately from $payload/$rules above
        // (rather than folded into the same array) because its rule needs
        // to run even when the key is entirely absent from $data — a
        // plain array_key_exists()-based "only validate if present"
        // approach (like emptyToNull()'s callers use for code/
        // description) would let a create() call that forgets to send
        // is_active silently fall through to the stations table's DB
        // default(true) without ever being checked against the
        // type==="other" cross-field rule below. Presence is normalized
        // here: an absent key becomes `null`, which the `boolean` rule
        // (no `nullable`) correctly rejects, forcing every caller to send
        // an explicit true/false.
        $payload['is_active'] = array_key_exists('is_active', $data) ? $data['is_active'] : null;

        $rules = [
            'business_unit_id' => ['required', 'string', Rule::exists('business_units', 'id')],
            'production_line_id' => ['required', 'string', Rule::exists('production_lines', 'id')],
            'name' => ['required', 'string', 'max:255'],
            // Validated against the station_types master table rather than
            // App\Enums\StationType (2026-09-22): adding a station type is now
            // an INSERT, and hardcoding the enum here would have kept this
            // rule stale the moment a type was added without a code change.
            // The enum is still used below for the one rule that names a
            // specific type (`other`), where a typo must be a fatal error.
            'type' => ['required', Rule::exists('station_types', 'code')],
            'is_active' => ['required', 'boolean'],
            'code' => ['nullable', 'string', 'max:255', $codeUniqueRule],
            // Free-text field max length matches this codebase's universal
            // convention for optional single-line/short-text fields (see
            // BusinessUnitService::OPTIONAL_TEXT_FIELDS's `address`/`map`
            // rules, both max:255) — kept consistent rather than
            // introducing a longer max just for this one field.
            'description' => ['nullable', 'string', 'max:255'],
        ];

        $messages = [
            'business_unit_id.required' => 'Business Unit wajib dipilih.',
            'business_unit_id.exists' => 'Business Unit yang dipilih tidak ditemukan.',
            'production_line_id.required' => 'Production Line wajib dipilih.',
            'production_line_id.exists' => 'Production Line yang dipilih tidak ditemukan.',
            'name.required' => 'Nama station wajib diisi.',
            'name.max' => 'Nama station maksimal 255 karakter.',
            'type.required' => 'Tipe station wajib dipilih.',
            'type.in' => 'Tipe station tidak valid.',
            'is_active.required' => 'Status aktif wajib diisi.',
            'is_active.boolean' => 'Status aktif tidak valid.',
            'code.max' => 'Kode station maksimal 255 karakter.',
            'code.unique' => 'Kode station sudah digunakan.',
            'description.max' => 'Deskripsi maksimal 255 karakter.',
        ];

        $validator = Validator::make($payload, $rules, $messages);

        // Cross-field rule: is_active may only be true when type is one
        // of weighbridge/grading/cages-track — never true when type is
        // "other". Expressed via ->after() (rather than a plain rule
        // string) since it depends on two fields at once — mirrors this
        // codebase's convention of using ->after() closures for
        // conditions a single field-level rule can't express (see e.g.
        // App\Exceptions's various delete-guard exceptions for the
        // equivalent pattern at the exception layer rather than the
        // validator layer).
        $validator->after(function ($validator) use ($payload, $excludeId) {
            $isActive = filter_var($payload['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $type = $payload['type'];

            if ($isActive === true && $type === StationType::Other->value) {
                $validator->errors()->add(
                    'is_active',
                    'Station dengan tipe Other tidak boleh berstatus aktif — set Status menjadi nonaktif.'
                );
            }

            // entity-catalog constraint: "production_line_id harus milik
            // business_unit_id yang sama" — only checked once both FKs
            // individually passed their own exists() rule above (a
            // not-found production_line_id already carries its own error;
            // this avoids a redundant second error on the same field).
            if (! $validator->errors()->has('production_line_id') && ! $validator->errors()->has('business_unit_id')) {
                $productionLine = ProductionLine::find($payload['production_line_id']);

                if ($productionLine !== null && $productionLine->business_unit_id !== $payload['business_unit_id']) {
                    $validator->errors()->add(
                        'production_line_id',
                        'Production Line yang dipilih bukan milik Business Unit ini.'
                    );
                }
            }

            if (! $validator->errors()->has('production_line_id') && ! $validator->errors()->has('type')) {
                $duplicate = $this->duplicateTypeMessage($payload['production_line_id'], $payload['type'], $excludeId);

                if ($duplicate !== null) {
                    $validator->errors()->add('type', $duplicate);
                }
            }
        });

        $validated = $validator->validate();

        // Explicitly cast is_active to a real PHP bool before returning —
        // Eloquent's `boolean` attribute cast (App\Models\Station::$casts)
        // only normalizes on READ (HasAttributes::getAttribute), not on
        // WRITE, so a raw "1"/"true" string surviving from $validated
        // would otherwise be persisted as-is instead of a proper boolean
        // value.
        $validated['is_active'] = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);

        return $validated;
    }

    /**
     * Normalizes an empty-string input to null, so a "cleared" optional
     * field (code/description submitted as "") saves as NULL rather than
     * "" — mirrors BusinessUnitService::normalizeTextFields()'s
     * empty-string-to-null behaviour for optional fields.
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
     * Maps a Station (with businessUnit eager-loaded + machinery_groups
     * count already loaded via withCount()/loadCount()) to the
     * endpoints' shared row shape.
     *
     * `type` is cast to the StationType enum by the model (see
     * App\Models\Station::$casts) — unwrapped to its raw string value
     * here (defensively guarded in case a caller ever passes a Station
     * instance whose `type` wasn't loaded through the cast, e.g. a raw
     * array-hydrated model in a future refactor).
     */
    protected function toRow(Station $station): array
    {
        return [
            'id' => $station->id,
            'business_unit_id' => $station->business_unit_id,
            'business_unit_name' => optional($station->businessUnit)->name,
            'production_line_id' => $station->production_line_id,
            'production_line_name' => optional($station->productionLine)->name,
            'name' => $station->name,
            'type' => $station->type instanceof StationType ? $station->type->value : $station->type,
            'is_active' => $station->is_active,
            'code' => $station->code,
            'description' => $station->description,
            'machinery_group_count' => (int) ($station->machinery_groups_count ?? 0),
            'created_at' => optional($station->created_at)->toIso8601String(),
        ];
    }
}
