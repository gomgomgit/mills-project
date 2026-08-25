<?php

namespace App\Livewire\Data;

use App\Enums\UserRole;
use App\Models\KernelPlantOperationalTarget;
use App\Models\ProductionLine;
use App\Services\KernelPlantRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FormKernelPlant — screen-060--form-kernel-plant-web / "Form
 * Kernel Plant (Web)" (Livewire web page, routes `data.kernel-plant.create`
 * /data/kernel-plant/create and `data.kernel-plant.edit`
 * /data/kernel-plant/{id}/edit — same component class handles both modes,
 * mode determined by whether $id was bound). Mirrors FormDepricarping
 * (screen-059) for header form shape + dual Checked/Acknowledged
 * self-attestation checkboxes.
 *
 * REVISED 2026-08-24 (entity-catalog v12): now also mirrors FormDepricarping's
 * Depricarping Detail grid for the Kernel Plant Detail grid itself — the
 * user explicitly rejected the original FIXED 24-row design (every
 * canonical time-slot row always present, no add/remove-row UI at all) as
 * wasting screen space. `detailRows` now uses the EXACT same dynamic
 * add-row/remove-row pattern as FormDepricarping's Depricarping Detail
 * grid: `addDetailRow()`/`removeDetailRow()`/`canAddRow()`/
 * `availableTimeSlotOptions()` (the canonical-order analogue of
 * FormCagesTrack's `availableHourOptions()`, floored by canonical-slot
 * INDEX rather than raw hour, since Kernel Plant's slots wrap starting at
 * 07:00). A brand-new draft starts with ZERO rows (no longer
 * pre-populated); an existing record's rows (with their `id`s) are loaded
 * verbatim in edit mode.
 *
 * STRUCTURAL SHAPE MATCHES DEPRICARPING: instead of one free-text
 * "Downtime Reason" reading column, Kernel Plant's own source log sheet has
 * TWO separate columns — `downtime_minutes` (numeric) and `findings`
 * (free text) — kept distinct end-to-end, unaffected by this dynamic-row
 * revision (a row counts as filled when EITHER is set, same as any other
 * reading column).
 *
 * Reuses KernelPlantRecordService::create()/update() — the exact same
 * service methods the API controller (App\Http\Controllers\Api\
 * KernelPlantRecordController::store()/update()) calls.
 *
 * Two deliberate divergences from Form Kernel Plant mobile (screen-044),
 * per explicit product direction (same divergences as screen-057/058/059):
 *  - date defaults to now() but stays a genuinely editable input (mobile
 *    locks it).
 *  - Saved records are fully editable afterwards (mode edit) — there is no
 *    draft/pause/Clear concept on web; Simpan always results in
 *    status=saved.
 *
 * Target Operasional (operationalTargets) is queried DIRECTLY here — NOT
 * sent as part of the create/update payload — per this screen's tech spec.
 */
#[Layout('data.kernel-plant-form')]
class FormKernelPlant extends Component
{
    protected const FIELDS = ['production_line_id', 'kernel_plant_id', 'date', 'note'];

    public ?string $id = null;

    public bool $isEdit = false;

    public bool $notFound = false;

    /** @var array<string, mixed> */
    public array $form = [
        'production_line_id' => '',
        'kernel_plant_id' => '',
        'date' => '',
        'note' => '',
    ];

    /**
     * Dynamic add-row/remove-row grid (REVISED 2026-08-24, entity-catalog
     * v12) — mirrors FormDepricarping's `$detailRows` exactly. Starts empty
     * for a brand-new draft; `id` is present for rows loaded from an
     * existing record (UPDATE target) and absent for rows added this
     * session (INSERT target).
     *
     * @var array<int, array{id: ?string, time_slot: ?string, ripple_mill_1_amps: mixed, ripple_mill_2_amps: mixed, claybath_hydro_sg: mixed, kernel_silo_1_temp_c: mixed, kernel_silo_2_temp_c: mixed, kernel_moisture_percent: mixed, shell_loss_percent: mixed, downtime_minutes: mixed, findings: mixed}>
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
            $record = app(KernelPlantRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;

            return;
        }

        $this->form['kernel_plant_id'] = $record['kernel_plant_id'] ?? '';
        $this->form['date'] = $record['date'] ?? '';
        $this->form['note'] = $record['note'] ?? '';

        $this->businessUnitName = $record['station_name'] ?? null;
        $this->checked = filled($record['checked_by_name']);
        $this->acknowledged = filled($record['acknowledged_by_name']);

        $this->detailRows = collect($record['details'])
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'time_slot' => $row['time_slot'],
                'ripple_mill_1_amps' => $row['ripple_mill_1_amps'],
                'ripple_mill_2_amps' => $row['ripple_mill_2_amps'],
                'claybath_hydro_sg' => $row['claybath_hydro_sg'],
                'kernel_silo_1_temp_c' => $row['kernel_silo_1_temp_c'],
                'kernel_silo_2_temp_c' => $row['kernel_silo_2_temp_c'],
                'kernel_moisture_percent' => $row['kernel_moisture_percent'],
                'shell_loss_percent' => $row['shell_loss_percent'],
                'downtime_minutes' => $row['downtime_minutes'],
                'findings' => $row['findings'],
            ])
            ->toArray();
    }

    public function addDetailRow(): void
    {
        $this->detailRows[] = [
            'id' => null,
            'time_slot' => '',
            'ripple_mill_1_amps' => '',
            'ripple_mill_2_amps' => '',
            'claybath_hydro_sg' => '',
            'kernel_silo_1_temp_c' => '',
            'kernel_silo_2_temp_c' => '',
            'kernel_moisture_percent' => '',
            'shell_loss_percent' => '',
            'downtime_minutes' => '',
            'findings' => '',
        ];
    }

    public function removeDetailRow(int $index): void
    {
        unset($this->detailRows[$index]);
        $this->detailRows = array_values($this->detailRows);
    }

    /** "Tambah baris" is disabled once all 24 canonical slots are already used. */
    public function canAddRow(): bool
    {
        return count($this->detailRows) < 24;
    }

    /**
     * availableTimeSlotOptions() — the canonical-order analogue of
     * FormCagesTrack::availableHourOptions(): all 24 canonical slots
     * DIKURANGI (slot yang sudah dipakai baris manapun) DAN (slot yang
     * index-nya <= index slot TERTINGGI di antara baris lain yang sudah
     * terisi), dibandingkan lewat posisi index-nya di
     * KernelPlantRecordService::canonicalTimeSlots() (bukan string
     * comparison — slot wrap mulai dari 07:00). Mirrors
     * FormDepricarping::availableTimeSlotOptions() exactly.
     *
     * @return array<int, string>
     */
    public function availableTimeSlotOptions(int $rowIndex): array
    {
        $canonical = KernelPlantRecordService::canonicalTimeSlots();
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
                'ripple_mill_1_amps' => $row['ripple_mill_1_amps'] !== '' ? $row['ripple_mill_1_amps'] : null,
                'ripple_mill_2_amps' => $row['ripple_mill_2_amps'] !== '' ? $row['ripple_mill_2_amps'] : null,
                'claybath_hydro_sg' => $row['claybath_hydro_sg'] !== '' ? $row['claybath_hydro_sg'] : null,
                'kernel_silo_1_temp_c' => $row['kernel_silo_1_temp_c'] !== '' ? $row['kernel_silo_1_temp_c'] : null,
                'kernel_silo_2_temp_c' => $row['kernel_silo_2_temp_c'] !== '' ? $row['kernel_silo_2_temp_c'] : null,
                'kernel_moisture_percent' => $row['kernel_moisture_percent'] !== '' ? $row['kernel_moisture_percent'] : null,
                'shell_loss_percent' => $row['shell_loss_percent'] !== '' ? $row['shell_loss_percent'] : null,
                'downtime_minutes' => $row['downtime_minutes'] !== '' ? $row['downtime_minutes'] : null,
                'findings' => $row['findings'] !== '' ? $row['findings'] : null,
            ])
            ->all();

        $service = app(KernelPlantRecordService::class);

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
            // e.g. NoActiveKernelPlantStationException (422) — a single,
            // non-field-keyed condition, shown as a page-level alert
            // rather than an inline per-field error.
            $this->generalError = $e->getMessage();

            return;
        }

        $this->redirect(route('data.kernel-plant.detail', ['id' => $record['id']]), navigate: false);
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
        return view('livewire.data.form-kernel-plant', [
            'operationalTargets' => KernelPlantOperationalTarget::orderBy('sort_order')->get(),
        ]);
    }
}
