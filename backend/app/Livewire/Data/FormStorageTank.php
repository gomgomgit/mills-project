<?php

namespace App\Livewire\Data;

use App\Enums\UserRole;
use App\Models\ProductionLine;
use App\Services\StorageTankRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FormStorageTank — screen-116--form-storage-tank-web / "Form Storage Tank
 * (Web)" (Livewire web page, routes `data.storage-tank.create`
 * /data/storage-tank/create and `data.storage-tank.edit`
 * /data/storage-tank/{id}/edit — same component class handles both modes,
 * mode determined by whether $id was bound). Mirrors FormEffluentPlant
 * (screen-115) for header form shape, dual Checked/Acknowledged
 * self-attestation checkboxes, AND the dynamic add-row/remove-row Storage
 * Tank Detail grid: `addDetailRow()`/`removeDetailRow()`/`canAddRow()`/
 * `availableTimeSlotOptions()` (the canonical-order analogue of
 * FormCagesTrack's `availableHourOptions()`, floored by canonical-slot
 * INDEX rather than raw hour, since Storage Tank's slots wrap starting at
 * 07:00). A brand-new draft starts with ZERO rows; an existing record's
 * rows (with their `id`s) are loaded verbatim in edit mode.
 *
 * Reuses StorageTankRecordService::create()/update() — the exact same
 * service methods the API controller
 * (App\Http\Controllers\Api\StorageTankRecordController::store()/update())
 * calls.
 *
 * Two deliberate divergences from Form Storage Tank mobile (screen-076),
 * per explicit product direction (same divergences as Form Effluent Plant
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
 */
#[Layout('data.storage-tank-form')]
class FormStorageTank extends Component
{
    protected const FIELDS = ['production_line_id', 'storage_tank_id', 'date', 'note'];

    public ?string $id = null;

    public bool $isEdit = false;

    public bool $notFound = false;

    /** @var array<string, mixed> */
    public array $form = [
        'production_line_id' => '',
        'storage_tank_id' => '',
        'date' => '',
        'note' => '',
    ];

    /**
     * Dynamic add-row/remove-row grid — mirrors FormEffluentPlant's
     * `$detailRows` exactly. Starts empty for a brand-new draft; `id` is
     * present for rows loaded from an existing record (UPDATE target) and
     * absent for rows added this session (INSERT target).
     *
     * @var array<int, array{id: ?string, time_slot: ?string, cpo_sounding_depth_mm: mixed, water_dip_bottom_depth_mm: mixed, net_oil_depth_mm: mixed, oil_temperature_top_c: mixed, oil_temperature_middle_c: mixed, oil_temperature_bottom_c: mixed, average_temperature_c: mixed, calculated_volume_m3: mixed, calculated_weight_mt: mixed, ffa_percent: mixed, moisture_content_percent: mixed, impurities_dirt_percent: mixed, dobi_index: mixed, steam_heating_valve_status: mixed, tank_structural_condition: mixed, inspector_name: mixed, findings: mixed}>
     */
    public array $detailRows = [];

    /** The 1 detail-row column backed by a SQLite enum CHECK constraint — an empty-string selection must be coerced to null before persisting (see save()). */
    protected const ENUM_FIELDS = ['steam_heating_valve_status'];

    /** All 17 non-time_slot detail-row columns, in display order. */
    protected const DETAIL_FIELDS = [
        'cpo_sounding_depth_mm', 'water_dip_bottom_depth_mm', 'net_oil_depth_mm',
        'oil_temperature_top_c', 'oil_temperature_middle_c', 'oil_temperature_bottom_c',
        'average_temperature_c', 'calculated_volume_m3', 'calculated_weight_mt',
        'ffa_percent', 'moisture_content_percent', 'impurities_dirt_percent',
        'dobi_index', 'steam_heating_valve_status', 'tank_structural_condition',
        'inspector_name', 'findings',
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
            $record = app(StorageTankRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;

            return;
        }

        $this->form['storage_tank_id'] = $record['storage_tank_id'] ?? '';
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
     * FormEffluentPlant::availableTimeSlotOptions(): all 24 canonical slots
     * DIKURANGI (slot yang sudah dipakai baris manapun) DAN (slot yang
     * index-nya <= index slot TERTINGGI di antara baris lain yang sudah
     * terisi), dibandingkan lewat posisi index-nya di
     * StorageTankRecordService::canonicalTimeSlots().
     *
     * @return array<int, string>
     */
    public function availableTimeSlotOptions(int $rowIndex): array
    {
        $canonical = StorageTankRecordService::canonicalTimeSlots();
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
        // fields (numeric + status); StorageTankRecordService also coerces
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

        $service = app(StorageTankRecordService::class);

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
            // e.g. NoActiveStorageTankStationException (422) — a single,
            // non-field-keyed condition, shown as a page-level alert
            // rather than an inline per-field error.
            $this->generalError = $e->getMessage();

            return;
        }

        $this->redirect(route('data.storage-tank.detail', ['id' => $record['id']]), navigate: false);
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
        return view('livewire.data.form-storage-tank');
    }
}
