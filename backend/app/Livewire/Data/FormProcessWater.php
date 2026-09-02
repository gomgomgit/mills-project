<?php

namespace App\Livewire\Data;

use App\Enums\UserRole;
use App\Models\ProductionLine;
use App\Services\ProcessWaterRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FormProcessWater — screen-112--form-process-water-web / "Form Process
 * Water (Web)" (Livewire web page, routes `data.process-water.create`
 * /data/process-water/create and `data.process-water.edit`
 * /data/process-water/{id}/edit — same component class handles both modes,
 * mode determined by whether $id was bound). Mirrors FormThreshing
 * (screen-057) for header form shape, dual Checked/Acknowledged
 * self-attestation checkboxes, AND the dynamic add-row/remove-row Process
 * Water Detail grid: `addDetailRow()`/`removeDetailRow()`/`canAddRow()`/
 * `availableTimeSlotOptions()` (the canonical-order analogue of
 * FormCagesTrack's `availableHourOptions()`, floored by canonical-slot
 * INDEX rather than raw hour, since Process Water's slots wrap starting at
 * 07:00). A brand-new draft starts with ZERO rows; an existing record's
 * rows (with their `id`s) are loaded verbatim in edit mode.
 *
 * Reuses ProcessWaterRecordService::create()/update() — the exact same
 * service methods the API controller
 * (App\Http\Controllers\Api\ProcessWaterRecordController::store()/
 * update()) calls.
 *
 * Two deliberate divergences from Form Process Water mobile (screen-072),
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
#[Layout('data.process-water-form')]
class FormProcessWater extends Component
{
    protected const FIELDS = ['production_line_id', 'process_water_id', 'date', 'note'];

    public ?string $id = null;

    public bool $isEdit = false;

    public bool $notFound = false;

    /** @var array<string, mixed> */
    public array $form = [
        'production_line_id' => '',
        'process_water_id' => '',
        'date' => '',
        'note' => '',
    ];

    /**
     * Dynamic add-row/remove-row grid — mirrors FormThreshing's
     * `$detailRows` exactly. Starts empty for a brand-new draft; `id` is
     * present for rows loaded from an existing record (UPDATE target) and
     * absent for rows added this session (INSERT target).
     *
     * @var array<int, array{id: ?string, time_slot: ?string, shift: mixed, inspector_id: mixed, raw_water_flow_m3h: mixed, clarified_water_flow_m3h: mixed, softener_inlet_ph: mixed, softener_outlet_hardness_ppm: mixed, alum_dosing_kgh: mixed, polymer_dosing_gh: mixed, boiler_feed_tank_temp_c: mixed, boiler_feed_water_ph: mixed, boiler_feed_tds_ppm: mixed, action_taken_status: mixed, findings: mixed}>
     */
    public array $detailRows = [];

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
            $record = app(ProcessWaterRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;

            return;
        }

        $this->form['process_water_id'] = $record['process_water_id'] ?? '';
        $this->form['date'] = $record['date'] ?? '';
        $this->form['note'] = $record['note'] ?? '';

        $this->businessUnitName = $record['station_name'] ?? null;
        $this->checked = filled($record['checked_by_name']);
        $this->acknowledged = filled($record['acknowledged_by_name']);

        $this->detailRows = collect($record['details'])
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'time_slot' => $row['time_slot'],
                'shift' => $row['shift'],
                'inspector_id' => $row['inspector_id'],
                'raw_water_flow_m3h' => $row['raw_water_flow_m3h'],
                'clarified_water_flow_m3h' => $row['clarified_water_flow_m3h'],
                'softener_inlet_ph' => $row['softener_inlet_ph'],
                'softener_outlet_hardness_ppm' => $row['softener_outlet_hardness_ppm'],
                'alum_dosing_kgh' => $row['alum_dosing_kgh'],
                'polymer_dosing_gh' => $row['polymer_dosing_gh'],
                'boiler_feed_tank_temp_c' => $row['boiler_feed_tank_temp_c'],
                'boiler_feed_water_ph' => $row['boiler_feed_water_ph'],
                'boiler_feed_tds_ppm' => $row['boiler_feed_tds_ppm'],
                'action_taken_status' => $row['action_taken_status'],
                'findings' => $row['findings'],
            ])
            ->toArray();
    }

    public function addDetailRow(): void
    {
        $this->detailRows[] = [
            'id' => null,
            'time_slot' => '',
            'shift' => '',
            'inspector_id' => '',
            'raw_water_flow_m3h' => '',
            'clarified_water_flow_m3h' => '',
            'softener_inlet_ph' => '',
            'softener_outlet_hardness_ppm' => '',
            'alum_dosing_kgh' => '',
            'polymer_dosing_gh' => '',
            'boiler_feed_tank_temp_c' => '',
            'boiler_feed_water_ph' => '',
            'boiler_feed_tds_ppm' => '',
            'action_taken_status' => '',
            'findings' => '',
        ];
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
     * ProcessWaterRecordService::canonicalTimeSlots().
     *
     * @return array<int, string>
     */
    public function availableTimeSlotOptions(int $rowIndex): array
    {
        $canonical = ProcessWaterRecordService::canonicalTimeSlots();
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
        $data['details'] = collect($this->detailRows)
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'time_slot' => $row['time_slot'],
                'shift' => $row['shift'] !== '' ? $row['shift'] : null,
                'inspector_id' => $row['inspector_id'] !== '' ? $row['inspector_id'] : null,
                'raw_water_flow_m3h' => $row['raw_water_flow_m3h'] !== '' ? $row['raw_water_flow_m3h'] : null,
                'clarified_water_flow_m3h' => $row['clarified_water_flow_m3h'] !== '' ? $row['clarified_water_flow_m3h'] : null,
                'softener_inlet_ph' => $row['softener_inlet_ph'] !== '' ? $row['softener_inlet_ph'] : null,
                'softener_outlet_hardness_ppm' => $row['softener_outlet_hardness_ppm'] !== '' ? $row['softener_outlet_hardness_ppm'] : null,
                'alum_dosing_kgh' => $row['alum_dosing_kgh'] !== '' ? $row['alum_dosing_kgh'] : null,
                'polymer_dosing_gh' => $row['polymer_dosing_gh'] !== '' ? $row['polymer_dosing_gh'] : null,
                'boiler_feed_tank_temp_c' => $row['boiler_feed_tank_temp_c'] !== '' ? $row['boiler_feed_tank_temp_c'] : null,
                'boiler_feed_water_ph' => $row['boiler_feed_water_ph'] !== '' ? $row['boiler_feed_water_ph'] : null,
                'boiler_feed_tds_ppm' => $row['boiler_feed_tds_ppm'] !== '' ? $row['boiler_feed_tds_ppm'] : null,
                'action_taken_status' => $row['action_taken_status'] !== '' ? $row['action_taken_status'] : null,
                'findings' => $row['findings'] !== '' ? $row['findings'] : null,
            ])
            ->all();

        $service = app(ProcessWaterRecordService::class);

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
            // e.g. NoActiveProcessWaterStationException (422) — a single,
            // non-field-keyed condition, shown as a page-level alert
            // rather than an inline per-field error.
            $this->generalError = $e->getMessage();

            return;
        }

        $this->redirect(route('data.process-water.detail', ['id' => $record['id']]), navigate: false);
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
        return view('livewire.data.form-process-water');
    }
}
