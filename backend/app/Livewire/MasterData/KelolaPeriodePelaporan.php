<?php

namespace App\Livewire\MasterData;

use App\Enums\PeriodStatus;
use App\Exceptions\PeriodAlreadyClosedException;
use App\Exceptions\PeriodClosedImmutableException;
use App\Exceptions\PeriodNotClosedException;
use App\Exceptions\PeriodNotDraftException;
use App\Exceptions\PeriodOverlapException;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Services\PeriodClosureService;
use App\Services\PeriodService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * KelolaPeriodePelaporan — screen-128--kelola-periode-pelaporan /
 * usecase-128 (CRUD) + usecase-140 (tutup & buka kembali) + usecase-144
 * (buka periode draft). Livewire web screen at /master-data/periods, route
 * name `master-data.periods`.
 *
 * Reuses PeriodService and PeriodClosureService — the exact same services
 * App\Http\Controllers\Api\PeriodController uses — so the web form and the
 * API never disagree on a rule.
 *
 * ── WHAT 2026-09-25/26 CHANGED ON THIS SCREEN ───────────────────────────
 *
 * A period no longer HAS a station type, a status, a closer or a closing
 * time. It has a LIST of station rows, each with its own status, and the
 * period only summarises them (PeriodService::toRow(): `status_summary`,
 * `station_count`, `closed_station_count`, `is_immutable`). Three consequences
 * shape everything below:
 *
 *  1. THE FORM TAKES NO STATION CHOICE. The "Jenis Stasiun" select and the
 *     `$station_type` property are gone: create() registers every station
 *     type active in the mill by itself, and update() backfills the ones the
 *     mill has gained since. The Admin lost a control they used to have, so
 *     the form SHOWS them what the period will cover instead — the preview
 *     list built in render() from PeriodService::activeStationTypesForMill(),
 *     never from a rule re-derived here.
 *
 *  2. THE LIST IS ONE PARENT ROW PER PERIOD, EXPANDABLE. A mill can have 19
 *     station types, so rendering every station row of every period would
 *     make the list unreadable. The parent row carries the summary, and
 *     `$expandedPeriodIds` decides whose station rows are rendered under it.
 *     Nothing is expanded by default; askClose()/askOpen()/askReopen() expand
 *     the affected period so the outcome is visible where the action was.
 *
 *  3. CLOSE / OPEN / REOPEN ARE PER STATION, AND CARRY A period_stations id.
 *     The three properties holding the current action's target are named
 *     `...StationId`, and the dialogs read their labels from a snapshot of
 *     THAT station row — so the warning can never talk about a station other
 *     than the one being closed. askClose() reads unverifiedCount($id) and
 *     confirmClose() passes the identical `$this->closingStationId` to
 *     close(); there is no second id anywhere in that path to get wrong.
 *
 * EDIT / HAPUS ARE DRIVEN BY `is_immutable`, NOT RE-DERIVED. That flag is
 * exactly the condition PeriodService::update()/delete() refuse on (409
 * PERIOD_CLOSED_IMMUTABLE — at least one station closed). The buttons are
 * rendered DISABLED rather than hidden: an Admin looking at a period with one
 * closed station out of nineteen needs to see that editing exists and is
 * blocked, not wonder where it went.
 *
 * VALIDATION HAS ONE SOURCE. This component used to mirror
 * PeriodService::validate() in a local buildValidator(). The copy had drifted
 * (it scoped the unique-name rule to `station_type` and added a
 * whereNull('business_unit_id') branch the service never had), which means
 * the form and the API could accept or refuse different input. The mirror is
 * GONE: save() calls the service and maps its ValidationException onto this
 * form's binding keys. One rule set, no second place to keep in step.
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

    /**
     * Filter status — draft | open | closed, '' = semua status. Matches a
     * period that has AT LEAST ONE station in that status (see
     * PeriodService::listPeriods()), which is why a period can appear under
     * two different filter values.
     */
    #[Url]
    public string $filterStatus = '';

    /**
     * Periods whose station rows are currently rendered. Collapsed is the
     * default on purpose — see the class docblock, point 2.
     *
     * @var list<string>
     */
    public array $expandedPeriodIds = [];

    public bool $showForm = false;

    public ?string $editingId = null;

    /**
     * Kept out of $form because it is bound directly by x-searchable-select.
     * Bound `.live` so the station preview under it follows the selected mill
     * without waiting for a save.
     */
    public string $business_unit_id = '';

    /** @var array<string, string> */
    public array $form = [];

    public ?string $formErrorMessage = null;

    public ?string $successMessage = null;

    public ?string $confirmingDeleteId = null;

    public ?string $deleteErrorMessage = null;

    /** `period_stations` id of the row whose inline reopen confirmation is up. */
    public ?string $confirmingReopenStationId = null;

    /**
     * `period_stations` id shown in the open-confirmation dialog
     * (usecase-144). Draft → open is irreversible (there is no way back to
     * Draft), so it gets a real dialog rather than the inline confirmation
     * reopen uses.
     */
    public ?string $openingStationId = null;

    /** @var array<string, mixed>|null snapshot of the opening station row */
    public ?array $openingStation = null;

    /** `period_stations` id shown in the close-confirmation dialog. */
    public ?string $closingStationId = null;

    /** @var array<string, mixed>|null snapshot of the closing station row */
    public ?array $closingStation = null;

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
     * Show / hide one period's station rows. Purely presentational — it
     * touches no data and needs no confirmation.
     */
    public function toggleExpanded(string $periodId): void
    {
        if (in_array($periodId, $this->expandedPeriodIds, true)) {
            $this->expandedPeriodIds = array_values(
                array_filter($this->expandedPeriodIds, fn (string $id) => $id !== $periodId)
            );

            return;
        }

        $this->expandedPeriodIds[] = $periodId;
    }

    protected function expand(?string $periodId): void
    {
        if ($periodId !== null && ! in_array($periodId, $this->expandedPeriodIds, true)) {
            $this->expandedPeriodIds[] = $periodId;
        }
    }

    public function openCreateForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->business_unit_id = '';
        $this->form = $this->emptyForm();
        $this->formErrorMessage = null;
        $this->successMessage = null;
        $this->showForm = true;
    }

    /**
     * "Edit" row action. A period with at least one CLOSED station may not be
     * edited — the table renders the button disabled for one, and this second
     * guard keeps a forged/stale call from even opening the form (the service
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

        if ($this->hasClosedStation($period)) {
            $this->deleteErrorMessage = (new PeriodClosedImmutableException)->getMessage();

            return;
        }

        $this->resetValidation();
        $this->formErrorMessage = null;
        $this->successMessage = null;
        $this->editingId = $period->id;
        $this->business_unit_id = (string) $period->business_unit_id;

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
        $this->form = $this->emptyForm();
        $this->formErrorMessage = null;
        $this->resetValidation();
    }

    /**
     * "Simpan" — create or update, per whether $editingId is set.
     *
     * NO CLIENT-SIDE MIRROR OF THE RULES (see the class docblock): the
     * service validates, and its ValidationException is remapped onto this
     * form's binding keys below, so the field-level errors under each input
     * are the service's own verdict rather than a second opinion.
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
        $this->resetValidation();

        $service = app(PeriodService::class);

        $payload = [
            'business_unit_id' => $this->business_unit_id,
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
            // A station of this period was closed by another Admin while
            // this form was open.
            $this->formErrorMessage = $e->getMessage();

            return;
        } catch (PeriodOverlapException $e) {
            // 422 PERIOD_OVERLAP — a form-level message (not field-keyed):
            // it is a conflict between two date fields and an existing
            // row, and the message names the conflicting period.
            $this->formErrorMessage = $e->getMessage();

            return;
        } catch (ValidationException $e) {
            // The service's plain field keys remapped onto this form's
            // binding keys: `business_unit_id` is a top-level property,
            // everything else lives under `form.`.
            foreach ($e->errors() as $field => $messages) {
                $key = $field === 'business_unit_id' ? $field : "form.$field";
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
     * PERIOD_CLOSED_IMMUTABLE (a station is closed) both surface inline and
     * leave the row in place; only a clean delete removes it.
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
     * "Tutup Stasiun" — opens the confirmation dialog for ONE station row and
     * fetches the unverified-record figure FOR THAT STATION TYPE only. The
     * figure is fetched HERE (not on the list) because it touches a station
     * record table; the cost is only paid when an Admin actually intends to
     * close.
     *
     * $periodStationId is a `period_stations` id — the `stations[].id` of
     * PeriodService::toRow(). It is stored verbatim and handed to
     * PeriodClosureService::close() unchanged by confirmClose(), so the
     * number in the warning and the row that gets closed are the same row by
     * construction.
     */
    public function askClose(string $periodStationId): void
    {
        $this->closeErrorMessage = null;
        $this->successMessage = null;
        $this->confirmingDeleteId = null;
        $this->confirmingReopenStationId = null;

        $station = $this->findStationForDialog($periodStationId);

        if ($station === null) {
            $this->closingStationId = null;
            $this->closingStation = null;
            $this->deleteErrorMessage = 'Stasiun periode tidak ditemukan, mungkin sudah dihapus.';

            return;
        }

        $result = app(PeriodClosureService::class)->unverifiedCount($periodStationId);

        $this->closingStationId = $periodStationId;
        $this->closingStation = $this->stationSnapshot($station);
        $this->closingUnverifiedCount = (int) $result['unverified_count'];
        $this->closingBreakdown = $result['breakdown'];
        $this->expand($station->period_id);
    }

    public function cancelClose(): void
    {
        $this->closingStationId = null;
        $this->closingStation = null;
        $this->closingUnverifiedCount = 0;
        $this->closingBreakdown = [];
        $this->closeErrorMessage = null;
    }

    /**
     * "Ya, Tutup Stasiun". The unverified count NEVER blocks this — it is
     * shown so the Admin decides knowingly, exactly as the business spec's
     * edge case requires.
     *
     * A 409 PERIOD_ALREADY_CLOSED here means another Admin closed the same
     * STATION first: the dialog closes, their name and time are surfaced,
     * and the next render() shows the list with THEIR closure recorded —
     * this Admin's action had no effect and must not appear to have had
     * one. Every other station row of that period is untouched either way.
     */
    public function confirmClose(): void
    {
        if ($this->closingStationId === null) {
            return;
        }

        try {
            app(PeriodClosureService::class)->close($this->closingStationId);
            $this->successMessage = 'Stasiun periode berhasil ditutup.';
            $this->closeErrorMessage = null;
        } catch (PeriodAlreadyClosedException $e) {
            $this->closeErrorMessage = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->closeErrorMessage = 'Stasiun periode tidak ditemukan, mungkin sudah dihapus.';
        }

        $this->closingStationId = null;
        $this->closingStation = null;
        $this->closingUnverifiedCount = 0;
        $this->closingBreakdown = [];
    }

    public function askReopen(string $periodStationId): void
    {
        $this->confirmingReopenStationId = $periodStationId;
        $this->closeErrorMessage = null;
        $this->successMessage = null;

        $station = PeriodStation::query()->find($periodStationId);
        $this->expand($station?->period_id);
    }

    public function cancelReopen(): void
    {
        $this->confirmingReopenStationId = null;
    }

    /**
     * Confirming the reopen of ONE station — clears its closed_by/closed_at
     * and unlocks that station type's records again. 409 PERIOD_NOT_CLOSED
     * (someone reopened it first) surfaces inline rather than throwing.
     */
    public function confirmReopen(): void
    {
        if ($this->confirmingReopenStationId === null) {
            return;
        }

        try {
            app(PeriodClosureService::class)->reopen($this->confirmingReopenStationId);
            $this->successMessage = 'Stasiun periode berhasil dibuka kembali.';
            $this->closeErrorMessage = null;
        } catch (PeriodNotClosedException $e) {
            $this->closeErrorMessage = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->closeErrorMessage = 'Stasiun periode tidak ditemukan, mungkin sudah dihapus.';
        }

        $this->confirmingReopenStationId = null;
    }

    /**
     * "Buka Stasiun" — usecase-144, per station row. Only offered on a DRAFT
     * station (the table renders the button for no other status), and the
     * dialog it opens spells out that the step cannot be undone: the status
     * cycle runs draft -> open -> closed, with no way back to draft.
     *
     * Deliberately does NOT re-check the status here. The authoritative
     * refusal lives in PeriodClosureService::open()'s conditional UPDATE,
     * and duplicating it at this layer would only give a stale row two
     * chances to produce two different messages. A row deleted in the
     * meantime simply opens the dialog with no snapshot and fails on
     * confirm with "tidak ditemukan".
     */
    public function askOpen(string $periodStationId): void
    {
        $this->closeErrorMessage = null;
        $this->successMessage = null;
        $this->confirmingDeleteId = null;
        $this->confirmingReopenStationId = null;

        $this->openingStationId = $periodStationId;
        $this->openingStation = null;

        $station = $this->findStationForDialog($periodStationId);

        if ($station !== null) {
            $this->openingStation = $this->stationSnapshot($station);
            $this->expand($station->period_id);
        }
    }

    public function cancelOpen(): void
    {
        $this->openingStationId = null;
        $this->openingStation = null;
        $this->closeErrorMessage = null;
    }

    /**
     * "Ya, Buka Stasiun". Touches that station row's status and nothing else
     * — no station record is read, validated or changed here, unlike
     * confirmClose() which first surfaces an unverified-record count.
     *
     * A 409 PERIOD_NOT_DRAFT means the station was not draft after all
     * (already open, already closed, or opened by another Admin a moment
     * earlier). The service's message distinguishes those cases — in
     * particular a closed station is pointed at "Buka Kembali" — so it is
     * surfaced verbatim, inline, exactly as reopen does.
     */
    public function confirmOpen(): void
    {
        if ($this->openingStationId === null) {
            return;
        }

        try {
            app(PeriodClosureService::class)->open($this->openingStationId);
            $this->successMessage = 'Stasiun periode berhasil dibuka.';
            $this->closeErrorMessage = null;
        } catch (PeriodNotDraftException $e) {
            $this->closeErrorMessage = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->closeErrorMessage = 'Stasiun periode tidak ditemukan, mungkin sudah dihapus.';
        }

        $this->openingStationId = null;
        $this->openingStation = null;
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

    /**
     * The exact condition PeriodService::update()/delete() refuse on. Read
     * from `period_stations` with the table-qualified column, the only
     * spelling PeriodQueryBuilder allows for a name that used to live on
     * `periods`.
     */
    protected function hasClosedStation(Period $period): bool
    {
        return $period->stations()
            ->where('period_stations.status', PeriodStatus::Closed->value)
            ->exists();
    }

    protected function findStationForDialog(string $periodStationId): ?PeriodStation
    {
        return PeriodStation::query()
            ->with(['period.businessUnit', 'stationTypeRef'])
            ->find($periodStationId);
    }

    /**
     * Everything a close/open dialog needs to name WHAT it is about to
     * change: the station type first, then the period it sits in. The
     * station's own id is carried along so a reader of the snapshot can see
     * it is a `period_stations` id and not a period one.
     *
     * @return array<string, mixed>
     */
    protected function stationSnapshot(PeriodStation $station): array
    {
        return [
            'period_station_id' => $station->id,
            'period_id' => $station->period_id,
            'station_type' => $station->station_type,
            'station_type_label' => optional($station->stationTypeRef)->name ?? $station->station_type,
            'period_name' => optional($station->period)->name,
            'business_unit_name' => optional(optional($station->period)->businessUnit)->name,
            'start_date' => optional(optional($station->period)->start_date)->toDateString(),
            'end_date' => optional(optional($station->period)->end_date)->toDateString(),
        ];
    }

    /**
     * The station types the period being edited/created will cover, for the
     * form's preview. Derived from PeriodService::activeStationTypesForMill()
     * — the same call create() and update()'s backfill use — never from a
     * rule spelled out again here.
     *
     * `is_new` marks a type that has no row yet: on a create that is all of
     * them, on an edit it is exactly what update()'s backfill will add.
     *
     * @param  array<string, string>  $labels  code => name
     * @return list<array{code: string, label: string, is_new: bool}>
     */
    protected function stationPreview(PeriodService $service, array $labels): array
    {
        if (! $this->showForm || $this->business_unit_id === '') {
            return [];
        }

        $existing = $this->editingId !== null
            ? PeriodStation::query()
                ->where('period_id', $this->editingId)
                ->pluck('station_type')
                ->all()
            : [];

        return array_map(
            fn (string $code) => [
                'code' => $code,
                'label' => $labels[$code] ?? $code,
                'is_new' => ! in_array($code, $existing, true),
            ],
            $service->activeStationTypesForMill($this->business_unit_id)
        );
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

        // code => name, so the close dialog's breakdown and the form's
        // station preview can render a human label without a second query.
        $stationTypeLabels = collect($service->stationTypeOptions())
            ->mapWithKeys(fn (array $option) => [$option['code'] => $option['name']])
            ->all();

        return view('livewire.master-data.kelola-periode-pelaporan', [
            'periods' => $result['data'],
            'meta' => $result['meta'],
            'businessUnitOptions' => $service->businessUnitOptions(),
            'stationTypeLabels' => $stationTypeLabels,
            'stationPreview' => $this->stationPreview($service, $stationTypeLabels),
        ]);
    }
}
