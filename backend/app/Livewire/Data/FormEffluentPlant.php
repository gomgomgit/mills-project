<?php

namespace App\Livewire\Data;

use App\Enums\UserRole;
use App\Models\ProductionLine;
use App\Services\EffluentPlantRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FormEffluentPlant — screen-115--form-effluent-plant-web / "Form Effluent
 * Plant (Web)" (Livewire web page, routes `data.effluent-plant.create`
 * /data/effluent-plant/create and `data.effluent-plant.edit`
 * /data/effluent-plant/{id}/edit — same component class handles both modes,
 * mode determined by whether $id was bound). Mirrors FormThreshing
 * (screen-057) for header form shape, dual Checked/Acknowledged
 * self-attestation checkboxes, AND the dynamic add-row/remove-row Effluent
 * Plant Detail grid: `addDetailRow()`/`removeDetailRow()`/`canAddRow()`/
 * `availableTimeSlotOptions()` (the canonical-order analogue of
 * FormCagesTrack's `availableHourOptions()`, floored by canonical-slot
 * INDEX rather than raw hour, since Effluent Plant's slots wrap starting at
 * 07:00). A brand-new draft starts with ZERO rows; an existing record's
 * rows (with their `id`s) are loaded verbatim in edit mode.
 *
 * Reuses EffluentPlantRecordService::create()/update() — the exact same
 * service methods the API controller
 * (App\Http\Controllers\Api\EffluentPlantRecordController::store()/
 * update()) calls.
 *
 * Two deliberate divergences from Form Effluent Plant mobile (screen-075),
 * per explicit product direction (same divergences as Form Threshing Web):
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
#[Layout('data.effluent-plant-form')]
class FormEffluentPlant extends Component
{
    protected const FIELDS = ['production_line_id', 'effluent_plant_id', 'date', 'note'];

    public ?string $id = null;

    public bool $isEdit = false;

    public bool $notFound = false;

    /** @var array<string, mixed> */
    public array $form = [
        'production_line_id' => '',
        'effluent_plant_id' => '',
        'date' => '',
        'note' => '',
    ];

    /**
     * Dynamic add-row/remove-row grid — mirrors FormThreshing's
     * `$detailRows` exactly. Starts empty for a brand-new draft; `id` is
     * present for rows loaded from an existing record (UPDATE target) and
     * absent for rows added this session (INSERT target).
     *
     * @var array<int, array{id: ?string, time_slot: ?string, anaerobic_pond_1_ph: mixed, anaerobic_pond_1_temp_c: mixed, anaerobic_pond_2_ph: mixed, anaerobic_pond_2_temp_c: mixed, cooling_pond_ph: mixed, cooling_pond_temp_c: mixed, biogas_flare_status: mixed, biogas_flow_rate_m3h: mixed, raw_pome_feed_rate_m3h: mixed, effluent_discharge_flow_rate_m3h: mixed, final_discharge_ph: mixed, final_discharge_bod_mgl_lab: mixed, final_discharge_cod_mgl_lab: mixed, final_discharge_tss_mgl_lab: mixed, dosing_pump_1_status: mixed, chemical_consumed_kgl: mixed, sludge_dewatering_status: mixed, remarks_maintenance_actions: mixed, findings: mixed}>
     */
    public array $detailRows = [];

    /** The 3 detail-row columns backed by a SQLite enum CHECK constraint — an empty-string selection must be coerced to null before persisting (see save()). */
    protected const ENUM_FIELDS = ['biogas_flare_status', 'dosing_pump_1_status', 'sludge_dewatering_status'];

    /** All 19 non-time_slot detail-row columns, in display order. */
    protected const DETAIL_FIELDS = [
        'anaerobic_pond_1_ph', 'anaerobic_pond_1_temp_c', 'anaerobic_pond_2_ph', 'anaerobic_pond_2_temp_c',
        'cooling_pond_ph', 'cooling_pond_temp_c', 'biogas_flare_status', 'biogas_flow_rate_m3h',
        'raw_pome_feed_rate_m3h', 'effluent_discharge_flow_rate_m3h', 'final_discharge_ph',
        'final_discharge_bod_mgl_lab', 'final_discharge_cod_mgl_lab', 'final_discharge_tss_mgl_lab',
        'dosing_pump_1_status', 'chemical_consumed_kgl', 'sludge_dewatering_status',
        'remarks_maintenance_actions', 'findings',
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
            $record = app(EffluentPlantRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;

            return;
        }

        $this->form['effluent_plant_id'] = $record['effluent_plant_id'] ?? '';
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
     * FormThreshing::availableTimeSlotOptions(): all 24 canonical slots
     * DIKURANGI (slot yang sudah dipakai baris manapun) DAN (slot yang
     * index-nya <= index slot TERTINGGI di antara baris lain yang sudah
     * terisi), dibandingkan lewat posisi index-nya di
     * EffluentPlantRecordService::canonicalTimeSlots().
     *
     * @return array<int, string>
     */
    public function availableTimeSlotOptions(int $rowIndex): array
    {
        $canonical = EffluentPlantRecordService::canonicalTimeSlots();
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
        // fields (numeric + status); EffluentPlantRecordService also
        // coerces the 3 ENUM_FIELDS specifically before persistence, since
        // their SQLite CHECK constraint rejects '' outright — see that
        // service's normalizeDetails() for the authoritative fix.
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

        $service = app(EffluentPlantRecordService::class);

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
            // e.g. NoActiveEffluentPlantStationException (422) — a single,
            // non-field-keyed condition, shown as a page-level alert
            // rather than an inline per-field error.
            $this->generalError = $e->getMessage();

            return;
        }

        $this->redirect(route('data.effluent-plant.detail', ['id' => $record['id']]), navigate: false);
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
        return view('livewire.data.form-effluent-plant');
    }
}
