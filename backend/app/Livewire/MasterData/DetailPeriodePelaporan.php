<?php

namespace App\Livewire\MasterData;

use App\Exceptions\PeriodAlreadyClosedException;
use App\Exceptions\PeriodClosedImmutableException;
use App\Exceptions\PeriodNotClosedException;
use App\Exceptions\PeriodNotDraftException;
use App\Exceptions\PeriodOverlapException;
use App\Models\PeriodStation;
use App\Services\PeriodClosureService;
use App\Services\PeriodService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DetailPeriodePelaporan — screen-142--detail-periode-pelaporan /
 * usecase-145 (lihat detail) + usecase-140 (tutup & buka kembali) +
 * usecase-144 (buka stasiun draft). Livewire web screen at
 * /master-data/periods/{id}, route name `master-data.periods.detail`.
 *
 * WHY THIS SCREEN EXISTS (2026-09-27). A period's station rows used to be an
 * expandable second <tr> inside the list (screen-128), and every per-station
 * action lived in it. A mill can have 19 station types, so the accordion was
 * carrying a whole screen's worth of state and actions inside a table row.
 * All of it moved here; screen-128 is now the list, and the period name on it
 * is a link to this page. Nothing about the actions themselves changed — the
 * same PeriodClosureService methods, the same ids, the same messages.
 *
 * FIRST detail-* SCREEN IN MASTER DATA. It follows the detail-* pattern of
 * the Data Stasiun (Web) module (App\Livewire\Data\DetailStorageTank,
 * /data/storage-tank/{id}): mount(id) → load → `notFound` instead of an
 * exception page → a "kembali ke daftar" way out on every state.
 *
 * ── THE THREE THINGS THIS COMPONENT MUST NOT GET WRONG ──────────────────
 *
 *  1. TWO KINDS OF ID, AND ONLY ONE OF THEM IS THE PAGE'S. `$this->id` is a
 *     PERIOD id — the route parameter, the thing Edit and Hapus act on.
 *     Every per-station action takes a `period_stations` id, which only ever
 *     comes from `stations[].id` of PeriodService::toRow(). The properties
 *     holding one are named `...StationId` for exactly that reason, and
 *     `$this->id` is never passed to PeriodClosureService.
 *
 *  2. THE CLOSE DIALOG'S FIGURE BELONGS TO THE STATION BEING CLOSED.
 *     askClose() reads unverifiedCount($periodStationId) and stores that same
 *     id; confirmClose() hands the identical `$this->closingStationId` to
 *     close(). There is no second id in that path, so the warning cannot be
 *     about a different station than the one that gets closed.
 *
 *  3. EDIT / HAPUS ARE DRIVEN BY `is_immutable`, NEVER RE-DERIVED. That flag
 *     is exactly the condition PeriodService::update()/delete() refuse on
 *     (409 PERIOD_CLOSED_IMMUTABLE — at least one station row closed). The
 *     buttons render DISABLED with a `title`, not hidden: an Admin looking at
 *     a period with one closed station out of nineteen has to see that
 *     editing exists and is blocked, not hunt for a vanished control.
 *
 * PER-ROW ACTIONS FOLLOW THE ROW'S OWN STATUS, NOT `status_summary`. Draft
 * offers "Buka Stasiun", open offers "Tutup Stasiun", closed offers "Buka
 * Kembali" — the cycle draft → open → closed with no shortcut and no way back
 * to draft. `status_summary` is a summary for the header badge and nothing
 * else.
 *
 * Access control: route-level only. routes/web.php guards
 * /master-data/periods/{id} with 'auth' + 'role:admin' — EnsureRole::
 * forbidden() aborts(403) before this component ever mounts for a non-admin
 * session, same as the list and every other master-data screen.
 */
#[Layout('master-data.period-detail')]
class DetailPeriodePelaporan extends Component
{
    /** PERIOD id from the route — never a `period_stations` id. */
    public string $id;

    /** The period is gone (bad link, or deleted by another Admin). */
    public bool $notFound = false;

    public bool $showForm = false;

    /**
     * Kept out of $form because it is bound directly by x-searchable-select,
     * exactly as on the list screen's form.
     */
    public string $business_unit_id = '';

    /** @var array<string, string> */
    public array $form = [];

    public ?string $formErrorMessage = null;

    public ?string $successMessage = null;

    public bool $confirmingDelete = false;

    public ?string $deleteErrorMessage = null;

    /** `period_stations` id of the row whose inline reopen confirmation is up. */
    public ?string $confirmingReopenStationId = null;

    /**
     * `period_stations` id shown in the open-confirmation dialog
     * (usecase-144). Draft → open is irreversible, so it gets a real dialog
     * rather than the inline confirmation reopen uses.
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

    public function mount(string $id): void
    {
        $this->id = $id;
        $this->form = $this->emptyForm();

        // A bad or stale link must land on a friendly page with a way back,
        // not on a 404/500 — business spec edge case 1.
        $this->notFound = $this->loadPeriod() === null;
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
     * THE ONE READ PATH FOR THIS PAGE — PeriodService::getDetail(), i.e.
     * toRow() verbatim, the same object GET /api/periods and the list screen
     * use. Called at the top of every render() so the summary, the badges,
     * the per-row actions and the Edit/Hapus disabled state are all recomputed
     * from one source after every action.
     *
     * @return array<string, mixed>|null null when the period no longer exists
     */
    protected function loadPeriod(): ?array
    {
        try {
            return app(PeriodService::class)->getDetail($this->id);
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * Back to the list. Plain navigation, no data touched — the business
     * spec's "Kembali ke Daftar Periode".
     */
    public function backToList()
    {
        return $this->redirectRoute('master-data.periods', navigate: true);
    }

    // ── Periode induk: Edit / Hapus ─────────────────────────────────────

    /**
     * "Edit Periode". The button is rendered disabled for an immutable
     * period; this second guard keeps a forged or stale call from even
     * opening the form. It reads `is_immutable` from the loaded row — the
     * service's own verdict — rather than re-deriving the rule here.
     */
    public function openEditForm(): void
    {
        $period = $this->loadPeriod();

        if ($period === null) {
            $this->notFound = true;
            $this->deleteErrorMessage = 'Periode tidak ditemukan, mungkin sudah dihapus.';

            return;
        }

        if ($period['is_immutable']) {
            $this->deleteErrorMessage = (new PeriodClosedImmutableException)->getMessage();

            return;
        }

        $this->resetValidation();
        $this->formErrorMessage = null;
        $this->successMessage = null;
        $this->deleteErrorMessage = null;
        $this->business_unit_id = (string) $period['business_unit_id'];

        $this->form = [
            'name' => (string) $period['name'],
            'start_date' => (string) $period['start_date'],
            'end_date' => (string) $period['end_date'],
        ];

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->business_unit_id = '';
        $this->form = $this->emptyForm();
        $this->formErrorMessage = null;
        $this->resetValidation();
    }

    /**
     * "Simpan" on the edit form — PeriodService::update(), the identical
     * call PATCH /api/periods/{id} makes, with its ValidationException
     * remapped onto this form's binding keys. No local mirror of the rules
     * exists here for the same reason it was removed from the list screen:
     * a second copy drifts and then the form and the API disagree.
     *
     * Every failure path leaves the modal open with the Admin's input intact.
     */
    public function save(): void
    {
        $this->formErrorMessage = null;
        $this->successMessage = null;
        $this->resetValidation();

        $payload = [
            'business_unit_id' => $this->business_unit_id,
            'name' => $this->form['name'],
            'start_date' => $this->form['start_date'],
            'end_date' => $this->form['end_date'],
        ];

        try {
            app(PeriodService::class)->update($this->id, $payload);
        } catch (ModelNotFoundException) {
            $this->formErrorMessage = 'Periode tidak ditemukan, mungkin sudah dihapus oleh pengguna lain.';

            return;
        } catch (PeriodClosedImmutableException $e) {
            // A station of this period was closed by another Admin while this
            // form was open.
            $this->formErrorMessage = $e->getMessage();

            return;
        } catch (PeriodOverlapException $e) {
            $this->formErrorMessage = $e->getMessage();

            return;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $key = $field === 'business_unit_id' ? $field : "form.$field";
                $this->addError($key, $messages[0] ?? 'Validasi gagal.');
            }

            return;
        }

        $this->successMessage = 'Periode berhasil diperbarui.';
        $this->showForm = false;
        $this->business_unit_id = '';
        $this->form = $this->emptyForm();
        $this->resetValidation();
    }

    public function askDelete(): void
    {
        $this->confirmingDelete = true;
        $this->deleteErrorMessage = null;
        $this->successMessage = null;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    /**
     * Confirming the delete. On success the period this page is ABOUT no
     * longer exists, so the only honest destination is the list — staying
     * here would render a not-found page for something the Admin just
     * removed on purpose. 409 (a station is closed) and 404 (already gone)
     * surface inline and leave the page in place.
     */
    public function confirmDelete()
    {
        try {
            app(PeriodService::class)->delete($this->id);
        } catch (PeriodClosedImmutableException $e) {
            $this->confirmingDelete = false;
            $this->deleteErrorMessage = $e->getMessage();

            return null;
        } catch (ModelNotFoundException) {
            $this->confirmingDelete = false;
            $this->notFound = true;
            $this->deleteErrorMessage = 'Periode tidak ditemukan, mungkin sudah dihapus.';

            return null;
        }

        $this->confirmingDelete = false;

        return $this->redirectRoute('master-data.periods', navigate: true);
    }

    // ── Aksi per stasiun (usecase-140 / usecase-144) ────────────────────

    /**
     * "Tutup Stasiun" — opens the confirmation dialog for ONE station row and
     * fetches the unverified-record figure FOR THAT STATION TYPE only. The
     * figure is fetched here, not on page load, because it touches a station
     * record table; the cost is only paid when an Admin actually intends to
     * close.
     *
     * $periodStationId is a `period_stations` id — `stations[].id` of
     * toRow(). It is stored verbatim and handed to
     * PeriodClosureService::close() unchanged by confirmClose(), so the
     * number in the warning and the row that gets closed are the same row by
     * construction.
     */
    public function askClose(string $periodStationId): void
    {
        $this->closeErrorMessage = null;
        $this->successMessage = null;
        $this->confirmingDelete = false;
        $this->confirmingReopenStationId = null;

        $station = $this->findStationForDialog($periodStationId);

        if ($station === null) {
            $this->closingStationId = null;
            $this->closingStation = null;
            $this->closeErrorMessage = 'Stasiun periode tidak ditemukan, mungkin sudah dihapus.';

            return;
        }

        $result = app(PeriodClosureService::class)->unverifiedCount($periodStationId);

        $this->closingStationId = $periodStationId;
        $this->closingStation = $this->stationSnapshot($station);
        $this->closingUnverifiedCount = (int) $result['unverified_count'];
        $this->closingBreakdown = $result['breakdown'];
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
     * A 409 PERIOD_ALREADY_CLOSED means another Admin closed the same STATION
     * first: the dialog closes, their name and time are surfaced, and the
     * next render() shows THEIR closure recorded — this Admin's action had no
     * effect and must not appear to have had one. Every other station row of
     * the period is untouched either way.
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
    }

    public function cancelReopen(): void
    {
        $this->confirmingReopenStationId = null;
    }

    /**
     * Confirming the reopen of ONE station — clears its closed_by/closed_at.
     * Once no closed station row is left, the period's Edit/Hapus become
     * live again, which is the documented manual way out of the backfill gap.
     * 409 PERIOD_NOT_CLOSED (someone reopened it first) surfaces inline
     * rather than throwing.
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
     * dialog it opens spells out that the step cannot be undone: the cycle
     * runs draft → open → closed, with no way back to draft.
     *
     * Deliberately does NOT re-check the status here. The authoritative
     * refusal lives in PeriodClosureService::open()'s conditional UPDATE, and
     * duplicating it at this layer would only give a stale row two chances to
     * produce two different messages.
     */
    public function askOpen(string $periodStationId): void
    {
        $this->closeErrorMessage = null;
        $this->successMessage = null;
        $this->confirmingDelete = false;
        $this->confirmingReopenStationId = null;

        $this->openingStationId = $periodStationId;
        $this->openingStation = null;

        $station = $this->findStationForDialog($periodStationId);

        if ($station !== null) {
            $this->openingStation = $this->stationSnapshot($station);
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
     * A 409 PERIOD_NOT_DRAFT means the station was not draft after all. The
     * service's message distinguishes "already open" from "already closed"
     * (the latter points at "Buka Kembali Periode"), so it is surfaced
     * verbatim.
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

    public function render()
    {
        $service = app(PeriodService::class);

        $period = $this->loadPeriod();
        $this->notFound = $period === null;

        // code => name for the close dialog's breakdown, same source the list
        // screen uses.
        $stationTypeLabels = collect($service->stationTypeOptions())
            ->mapWithKeys(fn (array $option) => [$option['code'] => $option['name']])
            ->all();

        return view('livewire.master-data.detail-periode-pelaporan', [
            'period' => $period,
            'businessUnitOptions' => $service->businessUnitOptions(),
            'stationTypeLabels' => $stationTypeLabels,
        ]);
    }
}
