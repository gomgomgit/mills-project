<?php

namespace App\Livewire\Data;

use App\Enums\UserRole;
use App\Models\ProductionLine;
use App\Services\ClarificationRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FormClarification — screen-119--form-clarification-web / "Form
 * Clarification (Web)" (Livewire web page, routes `data.clarification.create`
 * /data/clarification/create and `data.clarification.edit`
 * /data/clarification/{id}/edit — same component class handles both modes,
 * mode determined by whether $id was bound). Mirrors FormBoilerRoom
 * (screen-118) for header form shape, dual Checked/Acknowledged
 * self-attestation checkboxes, AND the dynamic add-row/remove-row
 * Clarification Detail grid: `addDetailRow()`/`removeDetailRow()`/
 * `canAddRow()`/`availableTimeSlotOptions()`. A brand-new draft starts with
 * ZERO rows; an existing record's rows (with their `id`s) are loaded
 * verbatim in edit mode.
 *
 * Reuses ClarificationRecordService::create()/update() — the exact same
 * service methods the API controller
 * (App\Http\Controllers\Api\ClarificationRecordController::store()/update())
 * calls.
 *
 * Two deliberate divergences from Form Clarification mobile (screen-079),
 * per explicit product direction (same divergences as Form Boiler Room
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
 * THE SIMPLEST FORM IN THE PROJECT — 7 non-time_slot columns per row, all
 * 6 readings genuinely numeric (`type="number"` inputs) plus 1 free-text
 * Findings field; NO enum dropdowns, NO empty-string-to-null coercion
 * needed anywhere in save().
 */
#[Layout('data.clarification-form')]
class FormClarification extends Component
{
    protected const FIELDS = ['production_line_id', 'clarification_id', 'date', 'note'];

    public ?string $id = null;

    public bool $isEdit = false;

    public bool $notFound = false;

    /** @var array<string, mixed> */
    public array $form = [
        'production_line_id' => '',
        'clarification_id' => '',
        'date' => '',
        'note' => '',
    ];

    /**
     * Dynamic add-row/remove-row grid — mirrors FormBoilerRoom's
     * `$detailRows` exactly. Starts empty for a brand-new draft; `id` is
     * present for rows loaded from an existing record (UPDATE target) and
     * absent for rows added this session (INSERT target).
     *
     * @var array<int, array{id: ?string, time_slot: ?string, clarification_tank_temp_c: mixed, oil_tank_temperature_c: mixed, sludge_tank_temp_c: mixed, buffer_tank_level_percent: mixed, pure_oil_production_rate_ton_hour: mixed, downtime_mins: mixed, findings: mixed}>
     */
    public array $detailRows = [];

    /** All 7 non-time_slot detail-row columns, in display order. */
    protected const DETAIL_FIELDS = [
        'clarification_tank_temp_c', 'oil_tank_temperature_c', 'sludge_tank_temp_c',
        'buffer_tank_level_percent', 'pure_oil_production_rate_ton_hour', 'downtime_mins',
        'findings',
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
            $record = app(ClarificationRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;

            return;
        }

        $this->form['clarification_id'] = $record['clarification_id'] ?? '';
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
     * FormBoilerRoom::availableTimeSlotOptions(): all 24 canonical slots
     * DIKURANGI (slot yang sudah dipakai baris manapun) DAN (slot yang
     * index-nya <= index slot TERTINGGI di antara baris lain yang sudah
     * terisi), dibandingkan lewat posisi index-nya di
     * ClarificationRecordService::canonicalTimeSlots().
     *
     * @return array<int, string>
     */
    public function availableTimeSlotOptions(int $rowIndex): array
    {
        $canonical = ClarificationRecordService::canonicalTimeSlots();
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

        $service = app(ClarificationRecordService::class);

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
            // e.g. NoActiveClarificationStationException (422) — a single,
            // non-field-keyed condition, shown as a page-level alert
            // rather than an inline per-field error.
            $this->generalError = $e->getMessage();

            return;
        }

        $this->redirect(route('data.clarification.detail', ['id' => $record['id']]), navigate: false);
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
        return view('livewire.data.form-clarification');
    }
}
