<?php

namespace App\Livewire\MasterData;

use App\Enums\PeriodStatus;
use App\Exceptions\PeriodAlreadyClosedException;
use App\Exceptions\PeriodClosedImmutableException;
use App\Exceptions\PeriodNotClosedException;
use App\Exceptions\PeriodOverlapException;
use App\Models\Period;
use App\Services\PeriodClosureService;
use App\Services\PeriodService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * KelolaPeriodePelaporan — screen-128--kelola-periode-pelaporan /
 * usecase-128 (CRUD) + usecase-140 (tutup & buka kembali). Livewire web
 * screen at /master-data/periods, route name `master-data.periods`.
 *
 * Reuses PeriodService and PeriodClosureService — the exact same services
 * App\Http\Controllers\Api\PeriodController uses — so the web form and the
 * API never disagree on a rule. Structure mirrors
 * App\Livewire\MasterData\KelolaProductionLine closely (a filter bar, an
 * inline delete confirmation, a modal form), with two additions this
 * screen needs and the other master-data screens do not:
 *
 *   1. A SECOND filter (status), alongside the Business Unit one.
 *   2. A CLOSE-CONFIRMATION MODAL. Closing a period is the one
 *      wide-blast-radius action on this screen — it locks every station
 *      record dated inside the range from input, edit AND verification —
 *      so the dialog first shows how many records in the range are still
 *      unverified (fetched on demand via PeriodClosureService::
 *      unverifiedCount(), never on the list, since it touches up to 18
 *      tables). The figure is a WARNING, not a blocker: the confirm button
 *      stays enabled whatever it says.
 *
 * Reopen uses the same inline-confirmation idea as delete (confirmingReopenId)
 * rather than a modal — there is nothing to warn about, it only unlocks.
 *
 * STATION TYPE DROPDOWN is filled from the `station_types` master table
 * via PeriodService::stationTypeOptions(), never from a hardcoded list or
 * from App\Enums\StationType, with an empty first option labelled
 * "Semua Stasiun" standing for the NULL (all types) scope.
 *
 * Access control: route-level only. routes/web.php guards
 * /master-data/periods with 'auth' + 'role:admin' — EnsureRole::forbidden()
 * aborts(403) before this component ever mounts for a non-admin session,
 * same as every other master-data screen.
 */
#[Layout('master-data.periods')]
class KelolaPeriodePelaporan extends Component
{
    public int $page = 1;

    public int $perPage = 20;

    /** Filter Business Unit — URL-bound so the filtered view is linkable. */
    #[Url]
    public string $filterBusinessUnitId = '';

    /** Filter status — draft | open | closed, '' = semua status. */
    #[Url]
    public string $filterStatus = '';

    public bool $showForm = false;

    public ?string $editingId = null;

    public string $business_unit_id = '';

    /**
     * Kept out of $form (same reasoning as $business_unit_id): it is bound
     * directly by x-searchable-select, and '' means the NULL all-types
     * scope rather than a missing value.
     */
    public string $station_type = '';

    /** @var array<string, string> */
    public array $form = [];

    public ?string $formErrorMessage = null;

    public ?string $successMessage = null;

    public ?string $confirmingDeleteId = null;

    public ?string $deleteErrorMessage = null;

    public ?string $confirmingReopenId = null;

    /** Period currently shown in the close-confirmation dialog. */
    public ?string $closingId = null;

    /** @var array<string, mixed>|null snapshot of the closing period's row */
    public ?array $closingPeriod = null;

    public int $closingUnverifiedCount = 0;

    /** @var list<array{station_type: string, count: int}> */
    public array $closingBreakdown = [];

    public ?string $closeErrorMessage = null;

    public function mount(): void
    {
        $this->form = $this->emptyForm();
    }

    public function updatedFilterBusinessUnitId(): void
    {
        $this->page = 1;
    }

    public function updatedFilterStatus(): void
    {
        $this->page = 1;
    }

    /**
     * @return array<string, string>
     */
    protected function emptyForm(): array
    {
        return [
            'name' => '',
            'start_date' => '',
            'end_date' => '',
        ];
    }

    /**
     * Client-side mirror of PeriodService::validate() (defense in depth —
     * same rules, same DB-backed exists/unique lookups, so this never
     * disagrees with the service). The overlap check is deliberately NOT
     * mirrored: it is one query the service already owns, and duplicating
     * it here would mean two places to keep the NULL-wildcard semantics
     * right. A PeriodOverlapException from the service is caught in save()
     * and surfaced as a form-level message instead.
     */
    protected function buildValidator(): \Illuminate\Validation\Validator
    {
        $businessUnitId = $this->business_unit_id !== '' ? $this->business_unit_id : null;
        $stationType = $this->station_type !== '' ? $this->station_type : null;

        $nameUniqueRule = Rule::unique('periods', 'name')
            ->where(function ($query) use ($businessUnitId, $stationType) {
                if ($businessUnitId === null) {
                    $query->whereNull('business_unit_id');
                } else {
                    $query->where('business_unit_id', $businessUnitId);
                }

                return $stationType === null
                    ? $query->whereNull('station_type')
                    : $query->where('station_type', $stationType);
            });

        if ($this->editingId !== null) {
            $nameUniqueRule = $nameUniqueRule->ignore($this->editingId);
        }

        $payload = [
            'business_unit_id' => $businessUnitId,
            'station_type' => $stationType,
            'form' => [
                'name' => $this->form['name'] !== '' ? $this->form['name'] : null,
                'start_date' => $this->form['start_date'] !== '' ? $this->form['start_date'] : null,
                'end_date' => $this->form['end_date'] !== '' ? $this->form['end_date'] : null,
            ],
        ];

        $rules = [
            'business_unit_id' => ['required', 'string', Rule::exists('business_units', 'id')],
            'station_type' => ['nullable', 'string', Rule::exists('station_types', 'code')],
            'form.name' => ['required', 'string', 'max:255', $nameUniqueRule],
            'form.start_date' => ['required', 'date'],
            'form.end_date' => ['required', 'date', 'after_or_equal:form.start_date'],
        ];

        $messages = [
            'business_unit_id.required' => 'Business Unit wajib dipilih.',
            'business_unit_id.exists' => 'Business Unit yang dipilih tidak ditemukan.',
            'station_type.exists' => 'Jenis stasiun yang dipilih tidak ditemukan.',
            'form.name.required' => 'Nama Periode wajib diisi.',
            'form.name.max' => 'Nama Periode maksimal 255 karakter.',
            'form.name.unique' => 'Nama Periode sudah digunakan pada Business Unit dan jenis stasiun ini.',
            'form.start_date.required' => 'Tanggal Mulai wajib diisi.',
            'form.start_date.date' => 'Tanggal Mulai tidak valid.',
            'form.end_date.required' => 'Tanggal Selesai wajib diisi.',
            'form.end_date.date' => 'Tanggal Selesai tidak valid.',
            'form.end_date.after_or_equal' => 'Tanggal Selesai tidak boleh lebih awal dari Tanggal Mulai.',
        ];

        return Validator::make($payload, $rules, $messages);
    }

    public function openCreateForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->business_unit_id = '';
        $this->station_type = '';
        $this->form = $this->emptyForm();
        $this->formErrorMessage = null;
        $this->successMessage = null;
        $this->showForm = true;
    }

    /**
     * "Edit" row action. A closed period may not be edited at all — the
     * table does not render the button for one, and this second guard
     * keeps a forged/stale call from even opening the form (the service
     * would refuse the save anyway with PERIOD_CLOSED_IMMUTABLE).
     */
    public function openEditForm(string $id): void
    {
        try {
            $period = Period::findOrFail($id);
        } catch (ModelNotFoundException) {
            $this->deleteErrorMessage = 'Periode tidak ditemukan, mungkin sudah dihapus.';

            return;
        }

        if ($this->statusValue($period) === PeriodStatus::Closed->value) {
            $this->deleteErrorMessage = (new PeriodClosedImmutableException)->getMessage();

            return;
        }

        $this->resetValidation();
        $this->formErrorMessage = null;
        $this->successMessage = null;
        $this->editingId = $period->id;
        $this->business_unit_id = (string) $period->business_unit_id;
        $this->station_type = (string) ($period->station_type ?? '');

        $this->form = [
            'name' => (string) ($period->name ?? ''),
            'start_date' => optional($period->start_date)->toDateString() ?? '',
            'end_date' => optional($period->end_date)->toDateString() ?? '',
        ];

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->business_unit_id = '';
        $this->station_type = '';
        $this->form = $this->emptyForm();
        $this->formErrorMessage = null;
        $this->resetValidation();
    }

    /**
     * "Simpan" — create or update, per whether $editingId is set.
     *
     * Every failure path deliberately LEAVES THE MODAL OPEN with the
     * Admin's input intact (the screen's test_scenarios assert exactly
     * that for the overlap and the validation cases): a rejected save must
     * never cost them the form they just filled in.
     */
    public function save(): void
    {
        $this->formErrorMessage = null;
        $this->successMessage = null;

        $this->buildValidator()->validate();

        $service = app(PeriodService::class);

        $payload = [
            'business_unit_id' => $this->business_unit_id,
            'station_type' => $this->station_type,
            'name' => $this->form['name'],
            'start_date' => $this->form['start_date'],
            'end_date' => $this->form['end_date'],
        ];

        try {
            if ($this->editingId !== null) {
                $service->update($this->editingId, $payload);
            } else {
                $service->create($payload);
            }
        } catch (ModelNotFoundException) {
            // Deleted by another Admin between opening the form and
            // submitting it — friendly message, list refreshes on the next
            // render(), no 500.
            $this->formErrorMessage = 'Periode tidak ditemukan, mungkin sudah dihapus oleh pengguna lain.';

            return;
        } catch (PeriodClosedImmutableException $e) {
            // Closed by another Admin while this form was open.
            $this->formErrorMessage = $e->getMessage();

            return;
        } catch (PeriodOverlapException $e) {
            // 422 PERIOD_OVERLAP — a form-level message (not field-keyed):
            // it is a conflict between two date fields and an existing
            // row, and the message names the conflicting period.
            $this->formErrorMessage = $e->getMessage();

            return;
        } catch (ValidationException $e) {
            // Server-side re-validation caught something the client-side
            // mirror missed (e.g. a race with another Admin's create).
            // Remapped from the service's plain field keys onto this
            // form's binding keys.
            foreach ($e->errors() as $field => $messages) {
                $key = in_array($field, ['business_unit_id', 'station_type'], true) ? $field : "form.$field";
                $this->addError($key, $messages[0] ?? 'Validasi gagal.');
            }

            return;
        }

        $this->successMessage = $this->editingId !== null
            ? 'Periode berhasil diperbarui.'
            : 'Periode berhasil dibuat.';

        $this->showForm = false;
        $this->editingId = null;
        $this->business_unit_id = '';
        $this->station_type = '';
        $this->form = $this->emptyForm();
        $this->resetValidation();
    }

    public function askDelete(string $id): void
    {
        $this->confirmingDeleteId = $id;
        $this->deleteErrorMessage = null;
        $this->successMessage = null;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    /**
     * Confirming the delete — 404 (already gone) and 409
     * PERIOD_CLOSED_IMMUTABLE (closed) both surface inline and leave the
     * row in place; only a clean delete removes it.
     */
    public function confirmDelete(): void
    {
        if ($this->confirmingDeleteId === null) {
            return;
        }

        try {
            app(PeriodService::class)->delete($this->confirmingDeleteId);
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = null;
            $this->successMessage = 'Periode berhasil dihapus.';
        } catch (PeriodClosedImmutableException $e) {
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = 'Periode tidak ditemukan, mungkin sudah dihapus.';
        }
    }

    /**
     * "Tutup Periode" — opens the confirmation dialog and fetches the
     * unverified-record figure for the range. The figure is fetched HERE
     * (not on the list) because it touches up to 18 record tables; the
     * cost is only paid when an Admin actually intends to close.
     */
    public function askClose(string $id): void
    {
        $this->closeErrorMessage = null;
        $this->successMessage = null;
        $this->confirmingDeleteId = null;
        $this->confirmingReopenId = null;

        $service = app(PeriodService::class);

        try {
            $period = Period::with(['businessUnit', 'closedBy'])->findOrFail($id);
            $result = app(PeriodClosureService::class)->unverifiedCount($id);
        } catch (ModelNotFoundException) {
            $this->closingId = null;
            $this->closingPeriod = null;
            $this->deleteErrorMessage = 'Periode tidak ditemukan, mungkin sudah dihapus.';

            return;
        }

        $this->closingId = $id;
        $this->closingPeriod = [
            'id' => $period->id,
            'name' => $period->name,
            'business_unit_name' => optional($period->businessUnit)->name,
            'station_type_label' => $service->stationTypeLabel($period->station_type),
            'start_date' => optional($period->start_date)->toDateString(),
            'end_date' => optional($period->end_date)->toDateString(),
        ];
        $this->closingUnverifiedCount = (int) $result['unverified_count'];
        $this->closingBreakdown = $result['breakdown'];
    }

    public function cancelClose(): void
    {
        $this->closingId = null;
        $this->closingPeriod = null;
        $this->closingUnverifiedCount = 0;
        $this->closingBreakdown = [];
        $this->closeErrorMessage = null;
    }

    /**
     * "Ya, Tutup Periode". The unverified count NEVER blocks this — it is
     * shown so the Admin decides knowingly, exactly as the business spec's
     * edge case requires.
     *
     * A 409 PERIOD_ALREADY_CLOSED here means another Admin closed the same
     * period first: the dialog closes, their name and time are surfaced,
     * and the next render() shows the list with THEIR closure recorded —
     * this Admin's action had no effect and must not appear to have had
     * one.
     */
    public function confirmClose(): void
    {
        if ($this->closingId === null) {
            return;
        }

        try {
            app(PeriodClosureService::class)->close($this->closingId);
            $this->successMessage = 'Periode berhasil ditutup.';
            $this->closeErrorMessage = null;
        } catch (PeriodAlreadyClosedException $e) {
            $this->closeErrorMessage = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->closeErrorMessage = 'Periode tidak ditemukan, mungkin sudah dihapus.';
        }

        $this->closingId = null;
        $this->closingPeriod = null;
        $this->closingUnverifiedCount = 0;
        $this->closingBreakdown = [];
    }

    public function askReopen(string $id): void
    {
        $this->confirmingReopenId = $id;
        $this->closeErrorMessage = null;
        $this->successMessage = null;
    }

    public function cancelReopen(): void
    {
        $this->confirmingReopenId = null;
    }

    /**
     * Confirming the reopen — clears closed_by/closed_at and unlocks the
     * range again. 409 PERIOD_NOT_CLOSED (someone reopened it first)
     * surfaces inline rather than throwing.
     */
    public function confirmReopen(): void
    {
        if ($this->confirmingReopenId === null) {
            return;
        }

        try {
            app(PeriodClosureService::class)->reopen($this->confirmingReopenId);
            $this->successMessage = 'Periode berhasil dibuka kembali.';
            $this->closeErrorMessage = null;
        } catch (PeriodNotClosedException $e) {
            $this->closeErrorMessage = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->closeErrorMessage = 'Periode tidak ditemukan, mungkin sudah dihapus.';
        }

        $this->confirmingReopenId = null;
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    protected function statusValue(Period $period): ?string
    {
        return $period->status instanceof PeriodStatus
            ? $period->status->value
            : $period->status;
    }

    public function render()
    {
        $service = app(PeriodService::class);

        $result = $service->listPeriods(
            $this->page,
            $this->perPage,
            $this->filterBusinessUnitId !== '' ? $this->filterBusinessUnitId : null,
            $this->filterStatus !== '' ? $this->filterStatus : null
        );

        $stationTypeOptions = $service->stationTypeOptions();

        return view('livewire.master-data.kelola-periode-pelaporan', [
            'periods' => $result['data'],
            'meta' => $result['meta'],
            'businessUnitOptions' => $service->businessUnitOptions(),
            'stationTypeOptions' => $stationTypeOptions,
            // code => name, so the close dialog's breakdown can render a
            // human label per station type without a second query.
            'stationTypeLabels' => collect($stationTypeOptions)
                ->mapWithKeys(fn (array $option) => [$option['code'] => $option['name']])
                ->all(),
        ]);
    }
}
