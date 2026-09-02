<?php

namespace App\Livewire\Data;

use App\Enums\UserRole;
use App\Models\ProductionLine;
use App\Services\ProcessQualityControlRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FormProcessQualityControl — screen-120--form-process-quality-control-web / "Form
 * Process Quality Control (Web)" (Livewire web page, routes `data.process-quality-control.create`
 * /data/process-quality-control/create and `data.process-quality-control.edit`
 * /data/process-quality-control/{id}/edit — same component class handles both modes,
 * mode determined by whether $id was bound). Mirrors FormClarification (screen-119)
 * for header form shape, dual Checked/Acknowledged self-attestation checkboxes, AND
 * the dynamic add-row/remove-row Process Quality Control Detail grid:
 * `addDetailRow()`/`removeDetailRow()`/`canAddRow()`/`availableTimeSlotOptions()`.
 * A brand-new draft starts with ZERO rows; an existing record's rows (with their
 * `id`s) are loaded verbatim in edit mode.
 *
 * Reuses ProcessQualityControlRecordService::create()/update() — the exact same
 * service methods the API controller
 * (App\Http\Controllers\Api\ProcessQualityControlRecordController::store()/update())
 * calls.
 *
 * Two deliberate divergences from Form Process Quality Control mobile (screen-080),
 * per explicit product direction (same divergences as Form Clarification Web):
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
 * THE STATION WITH THE MOST NON-TIME_SLOT COLUMNS PER ROW IN THE PROJECT — 16
 * columns: Shift (text), 12 numeric readings (`type="number"` inputs), QC
 * Inspector ID (text), QC Engineering Corrective Actions (text), Findings
 * (text). NO enum dropdowns, NO empty-string-to-null coercion needed anywhere
 * in save() beyond the plain `'' -> null` pattern shared by every field.
 */
#[Layout('data.process-quality-control-form')]
class FormProcessQualityControl extends Component
{
    protected const FIELDS = ['production_line_id', 'process_qc_id', 'date', 'note'];

    public ?string $id = null;

    public bool $isEdit = false;

    public bool $notFound = false;

    /** @var array<string, mixed> */
    public array $form = [
        'production_line_id' => '',
        'process_qc_id' => '',
        'date' => '',
        'note' => '',
    ];

    /**
     * Dynamic add-row/remove-row grid — mirrors FormClarification's
     * `$detailRows` exactly. Starts empty for a brand-new draft; `id` is
     * present for rows loaded from an existing record (UPDATE target) and
     * absent for rows added this session (INSERT target).
     *
     * @var array<int, array{id: ?string, time_slot: ?string, shift: mixed, fruit_press_oil_loss_in_sludge_percent: mixed, fruit_press_oil_loss_in_fibre_percent: mixed, purifier_clarification_balance_inlet_temp_c: mixed, purifier_clarification_balance_backpressure_bar: mixed, vacuum_drying_station_drier_temp_c: mixed, vacuum_drying_station_vacuum_pressure_bar: mixed, decanter_centrifuge_feed_rate_mth: mixed, decanter_centrifuge_oil_loss_in_cake_percent: mixed, final_storage_ffa_percent: mixed, final_storage_moisture_content_percent: mixed, final_storage_impurities_dirt_percent: mixed, final_storage_dobi_index: mixed, qc_inspector_id: mixed, qc_engineering_corrective_actions: mixed, findings: mixed}>
     */
    public array $detailRows = [];

    /** All 16 non-time_slot detail-row columns, in display order (migration column order). */
    protected const DETAIL_FIELDS = [
        'shift',
        'fruit_press_oil_loss_in_sludge_percent', 'fruit_press_oil_loss_in_fibre_percent',
        'purifier_clarification_balance_inlet_temp_c', 'purifier_clarification_balance_backpressure_bar',
        'vacuum_drying_station_drier_temp_c', 'vacuum_drying_station_vacuum_pressure_bar',
        'decanter_centrifuge_feed_rate_mth', 'decanter_centrifuge_oil_loss_in_cake_percent',
        'final_storage_ffa_percent', 'final_storage_moisture_content_percent',
        'final_storage_impurities_dirt_percent', 'final_storage_dobi_index',
        'qc_inspector_id', 'qc_engineering_corrective_actions', 'findings',
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
            $record = app(ProcessQualityControlRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;

            return;
        }

        $this->form['process_qc_id'] = $record['process_qc_id'] ?? '';
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
     * FormClarification::availableTimeSlotOptions(): all 24 canonical slots
     * DIKURANGI (slot yang sudah dipakai baris manapun) DAN (slot yang
     * index-nya <= index slot TERTINGGI di antara baris lain yang sudah
     * terisi), dibandingkan lewat posisi index-nya di
     * ProcessQualityControlRecordService::canonicalTimeSlots().
     *
     * @return array<int, string>
     */
    public function availableTimeSlotOptions(int $rowIndex): array
    {
        $canonical = ProcessQualityControlRecordService::canonicalTimeSlots();
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
            ->map(function (array $row) {
                $mapped = ['id' => $row['id'], 'time_slot' => $row['time_slot']];

                foreach (self::DETAIL_FIELDS as $field) {
                    $value = $row[$field];
                    $mapped[$field] = $value !== '' ? $value : null;
                }

                return $mapped;
            })
            ->all();

        $service = app(ProcessQualityControlRecordService::class);

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
            // e.g. NoActiveProcessQualityControlStationException (422) — a
            // single, non-field-keyed condition, shown as a page-level
            // alert rather than an inline per-field error.
            $this->generalError = $e->getMessage();

            return;
        }

        $this->redirect(route('data.process-quality-control.detail', ['id' => $record['id']]), navigate: false);
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
        return view('livewire.data.form-process-quality-control');
    }
}
