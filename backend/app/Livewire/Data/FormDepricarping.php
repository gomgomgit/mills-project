<?php

namespace App\Livewire\Data;

use App\Enums\UserRole;
use App\Models\DepricarpingOperationalTarget;
use App\Models\ProductionLine;
use App\Services\DepricarpingRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FormDepricarping — screen-059--form-depricarping-web / "Form
 * Depricarping (Web)" (Livewire web page, routes `data.depricarping.create`
 * /data/depricarping/create and `data.depricarping.edit`
 * /data/depricarping/{id}/edit — same component class handles both modes,
 * mode determined by whether $id was bound). Mirrors FormPressing
 * (screen-058) for header form shape + dual Checked/Acknowledged
 * self-attestation checkboxes.
 *
 * REVISED 2026-08-24 (entity-catalog v12): now also mirrors FormPressing's
 * Pressing Detail grid for the Depricarping Detail grid itself — the user
 * explicitly rejected the original FIXED 24-row design (every canonical
 * time-slot row always present, no add/remove-row UI at all) as wasting
 * screen space. `detailRows` now uses the EXACT same dynamic add-row/
 * remove-row pattern as FormPressing's Pressing Detail grid:
 * `addDetailRow()`/`removeDetailRow()`/`canAddRow()`/
 * `availableTimeSlotOptions()` (the canonical-order analogue of
 * FormCagesTrack's `availableHourOptions()`, floored by canonical-slot
 * INDEX rather than raw hour, since Depricarping's slots wrap starting at
 * 07:00). A brand-new draft starts with ZERO rows (no longer
 * pre-populated); an existing record's rows (with their `id`s) are loaded
 * verbatim in edit mode.
 *
 * STRUCTURAL DIFFERENCE FROM PRESSING/THRESHING: instead of one free-text
 * "Downtime Reason" reading column, Depricarping's own source log sheet has
 * TWO separate columns — `downtime_minutes` (numeric) and `findings`
 * (free text) — kept distinct end-to-end, unaffected by this dynamic-row
 * revision (a row counts as filled when EITHER is set, same as any other
 * reading column).
 *
 * Reuses DepricarpingRecordService::create()/update() — the exact same
 * service methods the API controller (App\Http\Controllers\Api\
 * DepricarpingRecordController::store()/update()) calls.
 *
 * Two deliberate divergences from Form Depricarping mobile (screen-043),
 * per explicit product direction (same divergences as screen-057/058):
 *  - date defaults to now() but stays a genuinely editable input (mobile
 *    locks it).
 *  - Saved records are fully editable afterwards (mode edit) — there is no
 *    draft/pause/Clear concept on web; Simpan always results in
 *    status=saved.
 *
 * Target Operasional (operationalTargets) is queried DIRECTLY here — NOT
 * sent as part of the create/update payload — per this screen's tech spec.
 */
#[Layout('data.depricarping-form')]
class FormDepricarping extends Component
{
    protected const FIELDS = ['production_line_id', 'presser_id', 'date', 'note'];

    public ?string $id = null;

    public bool $isEdit = false;

    public bool $notFound = false;

    /** @var array<string, mixed> */
    public array $form = [
        'production_line_id' => '',
        'presser_id' => '',
        'date' => '',
        'note' => '',
    ];

    /**
     * Dynamic add-row/remove-row grid (REVISED 2026-08-24, entity-catalog
     * v12) — mirrors FormPressing's `$detailRows` exactly. Starts empty for
     * a brand-new draft; `id` is present for rows loaded from an existing
     * record (UPDATE target) and absent for rows added this session
     * (INSERT target).
     *
     * @var array<int, array{id: ?string, time_slot: ?string, fan_static_pressure_mmh2o: mixed, polishing_drum_speed_rpm: mixed, air_velocity_ms: mixed, fibre_moisture_percent: mixed, kernel_recovery_in_fibre_percent: mixed, nut_silo_1_temp_c: mixed, nut_silo_2_temp_c: mixed, downtime_minutes: mixed, findings: mixed}>
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
            $record = app(DepricarpingRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;

            return;
        }

        $this->form['presser_id'] = $record['presser_id'] ?? '';
        $this->form['date'] = $record['date'] ?? '';
        $this->form['note'] = $record['note'] ?? '';

        $this->businessUnitName = $record['station_name'] ?? null;
        $this->checked = filled($record['checked_by_name']);
        $this->acknowledged = filled($record['acknowledged_by_name']);

        $this->detailRows = collect($record['details'])
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'time_slot' => $row['time_slot'],
                'fan_static_pressure_mmh2o' => $row['fan_static_pressure_mmh2o'],
                'polishing_drum_speed_rpm' => $row['polishing_drum_speed_rpm'],
                'air_velocity_ms' => $row['air_velocity_ms'],
                'fibre_moisture_percent' => $row['fibre_moisture_percent'],
                'kernel_recovery_in_fibre_percent' => $row['kernel_recovery_in_fibre_percent'],
                'nut_silo_1_temp_c' => $row['nut_silo_1_temp_c'],
                'nut_silo_2_temp_c' => $row['nut_silo_2_temp_c'],
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
            'fan_static_pressure_mmh2o' => '',
            'polishing_drum_speed_rpm' => '',
            'air_velocity_ms' => '',
            'fibre_moisture_percent' => '',
            'kernel_recovery_in_fibre_percent' => '',
            'nut_silo_1_temp_c' => '',
            'nut_silo_2_temp_c' => '',
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
     * DepricarpingRecordService::canonicalTimeSlots() (bukan string
     * comparison — slot wrap mulai dari 07:00). Mirrors
     * FormPressing::availableTimeSlotOptions() exactly.
     *
     * @return array<int, string>
     */
    public function availableTimeSlotOptions(int $rowIndex): array
    {
        $canonical = DepricarpingRecordService::canonicalTimeSlots();
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
                'fan_static_pressure_mmh2o' => $row['fan_static_pressure_mmh2o'] !== '' ? $row['fan_static_pressure_mmh2o'] : null,
                'polishing_drum_speed_rpm' => $row['polishing_drum_speed_rpm'] !== '' ? $row['polishing_drum_speed_rpm'] : null,
                'air_velocity_ms' => $row['air_velocity_ms'] !== '' ? $row['air_velocity_ms'] : null,
                'fibre_moisture_percent' => $row['fibre_moisture_percent'] !== '' ? $row['fibre_moisture_percent'] : null,
                'kernel_recovery_in_fibre_percent' => $row['kernel_recovery_in_fibre_percent'] !== '' ? $row['kernel_recovery_in_fibre_percent'] : null,
                'nut_silo_1_temp_c' => $row['nut_silo_1_temp_c'] !== '' ? $row['nut_silo_1_temp_c'] : null,
                'nut_silo_2_temp_c' => $row['nut_silo_2_temp_c'] !== '' ? $row['nut_silo_2_temp_c'] : null,
                'downtime_minutes' => $row['downtime_minutes'] !== '' ? $row['downtime_minutes'] : null,
                'findings' => $row['findings'] !== '' ? $row['findings'] : null,
            ])
            ->all();

        $service = app(DepricarpingRecordService::class);

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
            // e.g. NoActiveDepricarpingStationException (422) — a single,
            // non-field-keyed condition, shown as a page-level alert
            // rather than an inline per-field error.
            $this->generalError = $e->getMessage();

            return;
        }

        $this->redirect(route('data.depricarping.detail', ['id' => $record['id']]), navigate: false);
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
        return view('livewire.data.form-depricarping', [
            'operationalTargets' => DepricarpingOperationalTarget::orderBy('sort_order')->get(),
        ]);
    }
}
