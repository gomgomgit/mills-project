<?php

namespace App\Livewire\Data;

use App\Enums\UserRole;
use App\Models\ProductionLine;
use App\Services\BoilerRoomRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FormBoilerRoom — screen-118--form-boiler-room-web / "Form Boiler Room
 * (Web)" (Livewire web page, routes `data.boiler-room.create`
 * /data/boiler-room/create and `data.boiler-room.edit`
 * /data/boiler-room/{id}/edit — same component class handles both modes,
 * mode determined by whether $id was bound). Mirrors FormEngineRoom
 * (screen-117) for header form shape, dual Checked/Acknowledged
 * self-attestation checkboxes, AND the dynamic add-row/remove-row Boiler
 * Room Detail grid: `addDetailRow()`/`removeDetailRow()`/`canAddRow()`/
 * `availableTimeSlotOptions()`. A brand-new draft starts with ZERO rows;
 * an existing record's rows (with their `id`s) are loaded verbatim in
 * edit mode.
 *
 * Reuses BoilerRoomRecordService::create()/update() — the exact same
 * service methods the API controller
 * (App\Http\Controllers\Api\BoilerRoomRecordController::store()/update())
 * calls.
 *
 * Two deliberate divergences from Form Boiler Room mobile (screen-078),
 * per explicit product direction (same divergences as Form Engine Room
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
 * 15 non-time_slot columns per row — 2 of them (Blowdown Executed,
 * Sootblowing Executed) are Y/N enum dropdowns; 3 (Fuel Feed Rate, ID Fan
 * Load, SA Fan Load) are free-text inputs (not numeric) because their
 * paper-form units are mixed/ambiguous.
 */
#[Layout('data.boiler-room-form')]
class FormBoilerRoom extends Component
{
    protected const FIELDS = ['production_line_id', 'boiler_room_id', 'date', 'note'];

    public ?string $id = null;

    public bool $isEdit = false;

    public bool $notFound = false;

    /** @var array<string, mixed> */
    public array $form = [
        'production_line_id' => '',
        'boiler_room_id' => '',
        'date' => '',
        'note' => '',
    ];

    /**
     * Dynamic add-row/remove-row grid — mirrors FormEngineRoom's
     * `$detailRows` exactly. Starts empty for a brand-new draft; `id` is
     * present for rows loaded from an existing record (UPDATE target) and
     * absent for rows added this session (INSERT target).
     *
     * @var array<int, array{id: ?string, time_slot: ?string, steam_pressure_bar: mixed, steam_temp_c: mixed, feed_water_temp_c: mixed, feed_water_tank_level_percent: mixed, boiler_water_level_percent: mixed, water_tds_ppm: mixed, water_ph: mixed, fuel_feed_rate: mixed, id_fan_load: mixed, sa_fan_load: mixed, exhaust_gas_temp_c: mixed, dust_collector_differential_pressure_mmh2o: mixed, blowdown_executed: mixed, sootblowing_executed: mixed, findings: mixed}>
     */
    public array $detailRows = [];

    /** The 2 detail-row columns backed by a SQLite enum CHECK constraint — an empty-string selection must be coerced to null before persisting (see save()). */
    protected const ENUM_FIELDS = ['blowdown_executed', 'sootblowing_executed'];

    /** All 15 non-time_slot detail-row columns, in display order. */
    protected const DETAIL_FIELDS = [
        'steam_pressure_bar', 'steam_temp_c', 'feed_water_temp_c',
        'feed_water_tank_level_percent', 'boiler_water_level_percent', 'water_tds_ppm',
        'water_ph', 'fuel_feed_rate', 'id_fan_load',
        'sa_fan_load', 'exhaust_gas_temp_c', 'dust_collector_differential_pressure_mmh2o',
        'blowdown_executed', 'sootblowing_executed', 'findings',
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
            $record = app(BoilerRoomRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;

            return;
        }

        $this->form['boiler_room_id'] = $record['boiler_room_id'] ?? '';
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
     * FormEngineRoom::availableTimeSlotOptions(): all 24 canonical slots
     * DIKURANGI (slot yang sudah dipakai baris manapun) DAN (slot yang
     * index-nya <= index slot TERTINGGI di antara baris lain yang sudah
     * terisi), dibandingkan lewat posisi index-nya di
     * BoilerRoomRecordService::canonicalTimeSlots().
     *
     * @return array<int, string>
     */
    public function availableTimeSlotOptions(int $rowIndex): array
    {
        $canonical = BoilerRoomRecordService::canonicalTimeSlots();
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
        // fields (numeric + status); BoilerRoomRecordService also coerces
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

        $service = app(BoilerRoomRecordService::class);

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
            // e.g. NoActiveBoilerRoomStationException (422) — a single,
            // non-field-keyed condition, shown as a page-level alert
            // rather than an inline per-field error.
            $this->generalError = $e->getMessage();

            return;
        }

        $this->redirect(route('data.boiler-room.detail', ['id' => $record['id']]), navigate: false);
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
        return view('livewire.data.form-boiler-room');
    }
}
