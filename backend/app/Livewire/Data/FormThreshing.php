<?php

namespace App\Livewire\Data;

use App\Enums\UserRole;
use App\Models\ProductionLine;
use App\Models\ThreshingOperationalTarget;
use App\Services\ThreshingRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FormThreshing — screen-057--form-threshing-web / "Form Threshing (Web)"
 * (Livewire web page, routes `data.threshing.create`
 * /data/threshing/create and `data.threshing.edit`
 * /data/threshing/{id}/edit — same component class handles both modes,
 * mode determined by whether $id was bound). Mirrors FormCagesTrack
 * (screen-024) for header form shape + dual Checked/Acknowledged
 * self-attestation checkboxes AND (REVISED 2026-08-24, entity-catalog v12)
 * now also for the Threshing Detail grid itself — the user explicitly
 * rejected the original FIXED 24-row design (every canonical time-slot row
 * always present, no add/remove-row UI at all) as wasting screen space.
 * `detailRows` now uses the EXACT same dynamic add-row/remove-row pattern
 * as FormCagesTrack's Cages Tipped Time grid: `addDetailRow()`/
 * `removeDetailRow()`/`canAddRow()`/`availableTimeSlotOptions()` (the
 * canonical-order analogue of `availableHourOptions()`, floored by
 * canonical-slot INDEX rather than raw hour, since Threshing's slots wrap
 * starting at 07:00). A brand-new draft starts with ZERO rows (no longer
 * pre-populated); an existing record's rows (with their `id`s) are loaded
 * verbatim in edit mode.
 *
 * Reuses ThreshingRecordService::create()/update() — the exact same
 * service methods the API controller
 * (App\Http\Controllers\Api\ThreshingRecordController::store()/update())
 * calls.
 *
 * Two deliberate divergences from Form Threshing mobile (screen-041), per
 * explicit product direction (same divergences as screen-022/023/024):
 *  - date defaults to now() but stays a genuinely editable input (mobile
 *    locks it).
 *  - Saved records are fully editable afterwards (mode edit) — there is no
 *    draft/pause/Clear concept on web; Simpan always results in
 *    status=saved.
 *
 * Target Operasional (operationalTargets) is queried DIRECTLY here — NOT
 * sent as part of the create/update payload — per this screen's tech spec.
 */
#[Layout('data.threshing-form')]
class FormThreshing extends Component
{
    protected const FIELDS = ['production_line_id', 'thresher_id', 'date', 'note'];

    public ?string $id = null;

    public bool $isEdit = false;

    public bool $notFound = false;

    /** @var array<string, mixed> */
    public array $form = [
        'production_line_id' => '',
        'thresher_id' => '',
        'date' => '',
        'note' => '',
    ];

    /**
     * Dynamic add-row/remove-row grid (REVISED 2026-08-24, entity-catalog
     * v12) — mirrors FormCagesTrack's `$detailRows` exactly. Starts empty
     * for a brand-new draft; `id` is present for rows loaded from an
     * existing record (UPDATE target) and absent for rows added this
     * session (INSERT target).
     *
     * @var array<int, array{id: ?string, time_slot: ?string, ffb_throughput_mt_hour: mixed, thresher_drum_speed_rpm: mixed, motor_current_amps: mixed, unstripped_bunch_count_percent: mixed, empty_bunch_oil_loss_percent: mixed, downtime_reason: mixed}>
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
            $record = app(ThreshingRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;

            return;
        }

        $this->form['thresher_id'] = $record['thresher_id'] ?? '';
        $this->form['date'] = $record['date'] ?? '';
        $this->form['note'] = $record['note'] ?? '';

        $this->businessUnitName = $record['station_name'] ?? null;
        $this->checked = filled($record['checked_by_name']);
        $this->acknowledged = filled($record['acknowledged_by_name']);

        $this->detailRows = collect($record['details'])
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'time_slot' => $row['time_slot'],
                'ffb_throughput_mt_hour' => $row['ffb_throughput_mt_hour'],
                'thresher_drum_speed_rpm' => $row['thresher_drum_speed_rpm'],
                'motor_current_amps' => $row['motor_current_amps'],
                'unstripped_bunch_count_percent' => $row['unstripped_bunch_count_percent'],
                'empty_bunch_oil_loss_percent' => $row['empty_bunch_oil_loss_percent'],
                'downtime_reason' => $row['downtime_reason'],
            ])
            ->toArray();
    }

    public function addDetailRow(): void
    {
        $this->detailRows[] = [
            'id' => null,
            'time_slot' => '',
            'ffb_throughput_mt_hour' => '',
            'thresher_drum_speed_rpm' => '',
            'motor_current_amps' => '',
            'unstripped_bunch_count_percent' => '',
            'empty_bunch_oil_loss_percent' => '',
            'downtime_reason' => '',
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
     * ThreshingRecordService::canonicalTimeSlots() (bukan string
     * comparison — slot wrap mulai dari 07:00).
     *
     * @return array<int, string>
     */
    public function availableTimeSlotOptions(int $rowIndex): array
    {
        $canonical = ThreshingRecordService::canonicalTimeSlots();
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
                'ffb_throughput_mt_hour' => $row['ffb_throughput_mt_hour'] !== '' ? $row['ffb_throughput_mt_hour'] : null,
                'thresher_drum_speed_rpm' => $row['thresher_drum_speed_rpm'] !== '' ? $row['thresher_drum_speed_rpm'] : null,
                'motor_current_amps' => $row['motor_current_amps'] !== '' ? $row['motor_current_amps'] : null,
                'unstripped_bunch_count_percent' => $row['unstripped_bunch_count_percent'] !== '' ? $row['unstripped_bunch_count_percent'] : null,
                'empty_bunch_oil_loss_percent' => $row['empty_bunch_oil_loss_percent'] !== '' ? $row['empty_bunch_oil_loss_percent'] : null,
                'downtime_reason' => $row['downtime_reason'] !== '' ? $row['downtime_reason'] : null,
            ])
            ->all();

        $service = app(ThreshingRecordService::class);

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
            // e.g. NoActiveThreshingStationException (422) — a single,
            // non-field-keyed condition, shown as a page-level alert
            // rather than an inline per-field error.
            $this->generalError = $e->getMessage();

            return;
        }

        $this->redirect(route('data.threshing.detail', ['id' => $record['id']]), navigate: false);
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
        return view('livewire.data.form-threshing', [
            'operationalTargets' => ThreshingOperationalTarget::orderBy('sort_order')->get(),
        ]);
    }
}
