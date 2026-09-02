<?php

namespace App\Livewire\Data;

use App\Enums\UserRole;
use App\Models\ProductionLine;
use App\Services\EngineRoomRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FormEngineRoom — screen-117--form-engine-room-web / "Form Engine Room
 * (Web)" (Livewire web page, routes `data.engine-room.create`
 * /data/engine-room/create and `data.engine-room.edit`
 * /data/engine-room/{id}/edit — same component class handles both modes,
 * mode determined by whether $id was bound). Mirrors FormStorageTank
 * (screen-116) for header form shape, dual Checked/Acknowledged
 * self-attestation checkboxes, AND the dynamic add-row/remove-row Engine
 * Room Detail grid: `addDetailRow()`/`removeDetailRow()`/`canAddRow()`/
 * `availableTimeSlotOptions()` (the canonical-order analogue of
 * FormCagesTrack's `availableHourOptions()`, floored by canonical-slot
 * INDEX rather than raw hour, since Engine Room's slots wrap starting at
 * 07:00). A brand-new draft starts with ZERO rows; an existing record's
 * rows (with their `id`s) are loaded verbatim in edit mode.
 *
 * Reuses EngineRoomRecordService::create()/update() — the exact same
 * service methods the API controller
 * (App\Http\Controllers\Api\EngineRoomRecordController::store()/update())
 * calls.
 *
 * Two deliberate divergences from Form Engine Room mobile (screen-077),
 * per explicit product direction (same divergences as Form Storage Tank
 * Web):
 *  - date defaults to now() but stays a genuinely editable input (mobile
 *    locks it).
 *  - Saved records are fully editable afterwards (mode edit) — there is no
 *    draft/pause/Clear concept on web; Simpan always results in
 *    status=saved.
 *
 * UNLIKE FormThreshing: this station has NO operational-target reference
 * table — no `operationalTargets` prop passed to the view, no Target
 * Operasional section rendered.
 *
 * 27 non-time_slot columns per row — the LARGEST field count of any
 * station in this project (task brief originally said 26; the migration —
 * ground truth — has 27; corrected as a derived assumption, see
 * EngineRoomRecordService's own doc comment).
 */
#[Layout('data.engine-room-form')]
class FormEngineRoom extends Component
{
    protected const FIELDS = ['production_line_id', 'engine_room_id', 'date', 'note'];

    public ?string $id = null;

    public bool $isEdit = false;

    public bool $notFound = false;

    /** @var array<string, mixed> */
    public array $form = [
        'production_line_id' => '',
        'engine_room_id' => '',
        'date' => '',
        'note' => '',
    ];

    /**
     * Dynamic add-row/remove-row grid — mirrors FormStorageTank's
     * `$detailRows` exactly. Starts empty for a brand-new draft; `id` is
     * present for rows loaded from an existing record (UPDATE target) and
     * absent for rows added this session (INSERT target).
     *
     * @var array<int, array{id: ?string, time_slot: ?string, steam_turbine_inlet_pressure_bar: mixed, steam_turbine_inlet_temp_c: mixed, steam_turbine_exhaust_pressure_bar: mixed, steam_turbine_rpm: mixed, steam_turbine_alternator_bearing_temp_1_c: mixed, steam_turbine_alternator_bearing_temp_2_c: mixed, diesel_gen_1_status: mixed, diesel_gen_1_load_kw: mixed, diesel_gen_1_amperage_a: mixed, diesel_gen_1_jacket_water_temp_c: mixed, diesel_gen_1_lube_oil_pressure_bar: mixed, diesel_gen_2_status: mixed, diesel_gen_2_load_kw: mixed, diesel_gen_2_amperage_a: mixed, diesel_gen_2_jacket_water_temp_c: mixed, diesel_gen_2_lube_oil_pressure_bar: mixed, electrical_sync_total_factory_load_kw: mixed, electrical_sync_system_frequency_hz: mixed, electrical_sync_power_factor: mixed, electrical_sync_busbar_voltage_v: mixed, air_compressor_1_pressure_bar: mixed, compressor_2_pressure_bar: mixed, battery_charger_ups_voltage_v: mixed, fuel_tank_level: mixed, daily_energy_export_kwh: mixed, action_taken_maintenance_remark: mixed, findings: mixed}>
     */
    public array $detailRows = [];

    /** The 2 detail-row columns backed by a SQLite enum CHECK constraint — an empty-string selection must be coerced to null before persisting (see save()). */
    protected const ENUM_FIELDS = ['diesel_gen_1_status', 'diesel_gen_2_status'];

    /** All 27 non-time_slot detail-row columns, in display order. */
    protected const DETAIL_FIELDS = [
        'steam_turbine_inlet_pressure_bar', 'steam_turbine_inlet_temp_c', 'steam_turbine_exhaust_pressure_bar',
        'steam_turbine_rpm', 'steam_turbine_alternator_bearing_temp_1_c', 'steam_turbine_alternator_bearing_temp_2_c',
        'diesel_gen_1_status', 'diesel_gen_1_load_kw', 'diesel_gen_1_amperage_a',
        'diesel_gen_1_jacket_water_temp_c', 'diesel_gen_1_lube_oil_pressure_bar', 'diesel_gen_2_status',
        'diesel_gen_2_load_kw', 'diesel_gen_2_amperage_a', 'diesel_gen_2_jacket_water_temp_c',
        'diesel_gen_2_lube_oil_pressure_bar', 'electrical_sync_total_factory_load_kw', 'electrical_sync_system_frequency_hz',
        'electrical_sync_power_factor', 'electrical_sync_busbar_voltage_v', 'air_compressor_1_pressure_bar',
        'compressor_2_pressure_bar', 'battery_charger_ups_voltage_v', 'fuel_tank_level',
        'daily_energy_export_kwh', 'action_taken_maintenance_remark', 'findings',
    ];

    public bool $checked = false;

    public bool $acknowledged = false;

    /** Read-only display for edit mode (production_line_id is immutable after create). */
    public ?string $businessUnitName = null;

    /** @var array<int, array{id: string, name: string}> */
    public array $productionLineOptions = [];

    /** @var array<string, string> */
    public array $errors_ = [];

    public ?string $detailError = null;

    public ?string $generalError = null;

    public function mount(?string $id = null): void
    {
        $this->productionLineOptions = ProductionLine::query()->orderBy('name')->get(['id', 'name'])->toArray();

        if ($id === null) {
            $this->isEdit = false;
            $this->form['date'] = now()->format('Y-m-d');

            return;
        }

        $this->id = $id;
        $this->isEdit = true;

        try {
            $record = app(EngineRoomRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;

            return;
        }

        $this->form['engine_room_id'] = $record['engine_room_id'] ?? '';
        $this->form['date'] = $record['date'] ?? '';
        $this->form['note'] = $record['note'] ?? '';

        $this->businessUnitName = $record['station_name'] ?? null;
        $this->checked = filled($record['checked_by_name']);
        $this->acknowledged = filled($record['acknowledged_by_name']);

        $this->detailRows = collect($record['details'])
            ->map(function (array $row) {
                $mapped = ['id' => $row['id'], 'time_slot' => $row['time_slot']];

                foreach (self::DETAIL_FIELDS as $field) {
                    $mapped[$field] = $row[$field] ?? null;
                }

                return $mapped;
            })
            ->toArray();
    }

    public function addDetailRow(): void
    {
        $row = ['id' => null, 'time_slot' => ''];

        foreach (self::DETAIL_FIELDS as $field) {
            $row[$field] = '';
        }

        $this->detailRows[] = $row;
    }

    public function removeDetailRow(int $index): void
    {
        unset($this->detailRows[$index]);
        $this->detailRows = array_values($this->detailRows);
    }

    /** "Tambah Baris" is disabled once all 24 canonical slots are already used. */
    public function canAddRow(): bool
    {
        return count($this->detailRows) < 24;
    }

    /**
     * availableTimeSlotOptions() — the canonical-order analogue of
     * FormStorageTank::availableTimeSlotOptions(): all 24 canonical slots
     * DIKURANGI (slot yang sudah dipakai baris manapun) DAN (slot yang
     * index-nya <= index slot TERTINGGI di antara baris lain yang sudah
     * terisi), dibandingkan lewat posisi index-nya di
     * EngineRoomRecordService::canonicalTimeSlots().
     *
     * @return array<int, string>
     */
    public function availableTimeSlotOptions(int $rowIndex): array
    {
        $canonical = EngineRoomRecordService::canonicalTimeSlots();
        $canonicalOrder = array_flip($canonical);

        $usedSlots = collect($this->detailRows)
            ->reject(fn ($row, $i) => $i === $rowIndex)
            ->pluck('time_slot')
            ->filter(fn ($slot) => $slot !== null && $slot !== '');

        $usedIndexes = $usedSlots->map(fn ($slot) => $canonicalOrder[$slot] ?? null)->filter(fn ($i) => $i !== null);
        $maxOtherIndex = $usedIndexes->max();

        return collect($canonical)
            ->reject(fn ($slot) => $usedSlots->contains($slot))
            ->reject(fn ($slot) => $maxOtherIndex !== null && $canonicalOrder[$slot] <= $maxOtherIndex)
            ->values()
            ->all();
    }

    public function save(): void
    {
        $this->errors_ = [];
        $this->detailError = null;
        $this->generalError = null;

        $data = $this->form;
        $data['checked'] = $this->checked;
        $data['acknowledged'] = $this->acknowledged;
        // Empty-string-to-null coercion here is defense-in-depth for ALL
        // fields (numeric + status); EngineRoomRecordService also coerces
        // the ENUM_FIELDS specifically before persistence, since their
        // SQLite CHECK constraint rejects '' outright — see that service's
        // normalizeDetails() for the authoritative fix.
        $data['details'] = collect($this->detailRows)
            ->map(function (array $row) {
                $mapped = ['id' => $row['id'], 'time_slot' => $row['time_slot']];

                foreach (self::DETAIL_FIELDS as $field) {
                    $value = $row[$field];
                    $mapped[$field] = $value !== '' ? $value : null;
                }

                return $mapped;
            })
            ->all();

        $service = app(EngineRoomRecordService::class);

        try {
            if ($this->isEdit) {
                $record = $service->update($this->id, $data, auth()->user());
            } else {
                $record = $service->create($data, auth()->user());
            }
        } catch (ValidationException $e) {
            $errors = $e->errors();

            if (isset($errors['details'])) {
                $this->detailError = $errors['details'][0];
                unset($errors['details']);
            }

            $this->errors_ = collect($errors)->map(fn ($messages) => $messages[0])->all();

            return;
        } catch (HttpException $e) {
            // e.g. NoActiveEngineRoomStationException (422) — a single,
            // non-field-keyed condition, shown as a page-level alert
            // rather than an inline per-field error.
            $this->generalError = $e->getMessage();

            return;
        }

        $this->redirect(route('data.engine-room.detail', ['id' => $record['id']]), navigate: false);
    }

    public function isSupervisor(): bool
    {
        return auth()->user()?->role === UserRole::Supervisor;
    }

    public function isMillManagement(): bool
    {
        return auth()->user()?->role === UserRole::MillManagement;
    }

    public function render()
    {
        return view('livewire.data.form-engine-room');
    }
}
