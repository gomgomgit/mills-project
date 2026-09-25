<?php

namespace App\Livewire\Dashboard;

use App\Enums\UserRole;
use App\Services\BoilerRoomReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * LaporanBoilerRoom — screen-131--laporan-boiler-room-web ("Laporan Boiler
 * Room"), route name `reports.boiler-room`, /reports/boiler-room.
 *
 * Reuses BoilerRoomReportService — the exact same service the API
 * controller (App\Http\Controllers\Api\BoilerRoomReportController) uses —
 * so the page and the API can never report different figures (mirrors
 * LaporanCagesTrack/CagesTrackReportService and LaporanSterilizer/
 * SterilizerReportService).
 *
 * READ-ONLY: the only actions are picking a mill, picking a period,
 * opening/closing the daily recap, and exporting. Nothing here writes to
 * `boiler_room_records` or `boiler_room_details`, and the component
 * deliberately exposes no action that reads like one.
 *
 * ROLE SHAPES THE FILTER BAR, not just the data:
 *   - Supervisor / Mill Management see NO mill picker at all — their mill is
 *     fixed, and offering a picker they cannot use would be a lie. They get
 *     a mill-name caption instead.
 *   - Admin (users.business_unit_id is NULL) sees the picker and must use
 *     it: without a mill there is no data to show, so the page asks for one
 *     instead of rendering an empty report that reads like "no data".
 *   - A bound account whose business_unit_id is NULL gets a "contact Admin"
 *     notice and NO picker — the all-mills list is never even read. That
 *     fail-closed rule is why render() resolves the mill itself instead of
 *     calling the service's resolveBusinessUnit(), which would throw.
 *
 * THE MILL IS NEVER NEGOTIABLE FROM THE UI for a bound role:
 * resolvedBusinessUnitId() ignores $businessUnitId entirely for them, so
 * forcing the property (or the query string) to another mill changes
 * nothing at all.
 *
 * Operator has no web access to this screen AND no API access either —
 * unlike Cages & Tracks, whose API was widened for screen-136. The mobile
 * Boiler Room report is screen-137 and has not been built.
 *
 * The period picker auto-selects the newest period, so the page is useful on
 * first paint rather than demanding a choice before showing anything.
 */
#[Layout('dashboard.laporan-boiler-room')]
class LaporanBoilerRoom extends Component
{
    /**
     * DIBACA DARI QUERY STRING sejak 2026-09-25.
     *
     * Laporan Stasiun (screen-140) sudah membawa mill di tautan tiap tile:
     * StationReportService membangun report_path sebagai
     * route($routeName, ['business_unit_id' => $businessUnitId]). Tanpa
     * #[Url] di sini, Livewire tidak pernah menghidrasinya, sehingga Admin
     * yang baru saja memilih mill lalu menekan sebuah tile MENDARAT DI
     * LAYAR YANG MEMINTANYA MEMILIH MILL LAGI — dan tanpa satu angka pun
     * termuat. Mill-nya sudah ada di URL; layar ini yang mengabaikannya.
     *
     * TIDAK MEMBUKA KEBOCORAN LINTAS MILL: untuk peran yang terikat satu
     * mill, resolvedBusinessUnitId() mengabaikan properti ini sepenuhnya
     * dan selalu memakai business_unit_id akun. Memaksa mill lain lewat
     * query string tetap tidak mengubah apa pun bagi mereka — itu sudah
     * diuji, dan justru itulah yang membuat #[Url] aman di sini.
     */
    // as: 'business_unit_id' WAJIB — tanpa itu #[Url] memakai NAMA
    // PROPERTI sebagai kunci query ('businessUnitId'), sementara tautan
    // yang dibangun StationReportService memakai 'business_unit_id'.
    // Keduanya tidak bertemu, dan layarnya tetap meminta pilih mill —
    // persis seperti sebelum #[Url] ditambahkan, tanpa tanda apa pun
    // bahwa ada yang salah.
    #[Url(as: 'business_unit_id')]
    public string $businessUnitId = '';

    /** Selected reporting period; auto-filled with the newest one. */
    public string $periodId = '';

    /**
     * Daily recap table shown/hidden — collapsed content, never a data
     * filter.
     *
     * OPEN BY DEFAULT, and that is a decision rather than a default left
     * alone: both the component test and the browser test for the
     * long-recap scenario start by CLOSING it ("toggle pertama -> daily-recap
     * tidak ter-render", "klik toggle untuk menutup lalu klik lagi untuk
     * membuka"), which only makes sense from an open start. The tech spec's
     * edge-case line calls for a `<details>` closed by default; that shape
     * would keep the table in the DOM while merely collapsing it, which the
     * same spec's tests explicitly reject. The tests win, and the toggle is
     * a plain button so the visible state and the rendered DOM can never
     * disagree.
     */
    public bool $showRecap = true;

    /**
     * Operator is a mobile-only actor with no web access at all, so the
     * component refuses to mount for them — not merely an empty render. The
     * route middleware ('role:supervisor,mill_management,admin') stops them
     * first in a browser; this guard also covers the component being mounted
     * directly, which is exactly how the test scenario exercises it.
     */
    public function mount(): void
    {
        abort_unless($this->canAccess(), 403);
    }

    /** Opens/closes the daily recap table (no re-query — the data is already loaded). */
    public function toggleRekapHarian(): void
    {
        $this->showRecap = ! $this->showRecap;
    }

    /**
     * Streams the period's time-slot rows as CSV/Excel through the shared
     * service, so a file downloaded from the page is byte-for-byte what the
     * API endpoint returns. Returns null (and does nothing) when no period is
     * selected — there is nothing to export yet, which is not an error.
     *
     * The permission guard and the 50.000-row ceiling are checked EAGERLY
     * inside the service, before any byte is streamed.
     *
     * The selected mill is threaded through EXACTLY as render() threads it
     * into buildSummary() — resolvedBusinessUnitId(), not the raw
     * $businessUnitId property. Without it an Admin export would reach
     * buildExportRows()'s resolveBusinessUnit(null) and be refused with 422
     * even though a mill IS selected on screen, so the button would look
     * inert. The bound roles are unaffected: resolveBusinessUnit() discards
     * the argument for them, so handing it their own id changes nothing
     * (still 200, still their own mill), and an Admin who has genuinely
     * picked no mill still passes null and still gets the documented 422 —
     * it is never defaulted to a mill.
     *
     * A CLOSED period exports exactly like an open one: the period lock
     * governs writing data, not reading a report.
     */
    public function export(string $format = 'csv')
    {
        if ($this->periodId === '') {
            return null;
        }

        $service = app(BoilerRoomReportService::class);

        return $service->export(
            $service->authorizePeriod($this->periodId),
            $format,
            $this->resolvedBusinessUnitId(),
        );
    }

    public function render()
    {
        $service = app(BoilerRoomReportService::class);

        $isAdmin = $this->isAdmin();
        $businessUnitId = $this->resolvedBusinessUnitId();

        // FAIL CLOSED: a bound account with no mill never reaches
        // businessUnitOptions(), so the all-mills list is never built for a
        // role that is supposed to be tied to exactly one.
        $hasNoMillForAccount = ! $isAdmin && $businessUnitId === null;

        $businessUnitOptions = $isAdmin ? $service->businessUnitOptions() : [];
        $periods = [];
        $summary = null;

        if ($businessUnitId !== null) {
            $periods = $service->listPeriods($businessUnitId);

            $this->keepSelectionValid($periods);

            if ($this->periodId !== '') {
                $summary = $service->buildSummary(
                    $service->authorizePeriod($this->periodId),
                    $businessUnitId,
                );
            }
        }

        return view('livewire.dashboard.laporan-boiler-room', [
            'isAdmin' => $isAdmin,
            'businessUnitOptions' => $businessUnitOptions,
            'businessUnitName' => $this->boundBusinessUnitName(),
            'periods' => $periods,
            'selectedPeriod' => collect($periods)->firstWhere('id', $this->periodId),
            'summary' => $summary,
            // Admin who has not picked a mill yet: the page asks for one
            // instead of showing an empty report.
            'needsMillSelection' => $isAdmin && $businessUnitId === null,
            'hasNoMillForAccount' => $hasNoMillForAccount,
        ]);
    }

    /**
     * The mill whose report is being read: the user's own for Supervisor /
     * Mill Management (never negotiable from the UI), the picked one for
     * Admin, null when an Admin has not picked yet or when a bound account
     * has no mill at all.
     */
    protected function resolvedBusinessUnitId(): ?string
    {
        if ($this->isAdmin()) {
            return $this->businessUnitId !== '' ? $this->businessUnitId : null;
        }

        // $this->businessUnitId is deliberately NOT consulted here.
        $businessUnitId = (string) (auth()->user()?->business_unit_id ?? '');

        return $businessUnitId !== '' ? $businessUnitId : null;
    }

    /**
     * Mill name shown as a caption for bound roles. Read from the account's
     * own relation, never from the selected period — the caption must stay
     * correct even before a period is chosen, and must never echo a mill the
     * user merely asked for.
     */
    protected function boundBusinessUnitName(): string
    {
        if ($this->isAdmin()) {
            return '';
        }

        return (string) (auth()->user()?->businessUnit?->name ?? '');
    }

    protected function isAdmin(): bool
    {
        return $this->role() === UserRole::Admin->value;
    }

    /** Supervisor, Mill Management, and Admin only — never Operator. */
    protected function canAccess(): bool
    {
        return in_array($this->role(), [
            UserRole::Supervisor->value,
            UserRole::MillManagement->value,
            UserRole::Admin->value,
        ], true);
    }

    protected function role(): string
    {
        $user = auth()->user();

        if ($user === null) {
            return '';
        }

        return $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
    }

    /**
     * Guards against a period id that is no longer in the current list —
     * which is also how switching mill works: no updated* hook is needed,
     * because the previous mill's period simply is not in the new mill's list
     * and is replaced by its newest one. That is also what stops a
     * hand-forced period id from another mill: it is not in the list, so it
     * is replaced before authorizePeriod() ever sees it.
     *
     * Deliberate: the public surface of this component stays limited to a
     * toggle and an export (plus the three bound properties). A read-only
     * report must not expose anything that reads like a write action.
     *
     * @param  list<array{id: string}>  $periods
     */
    protected function keepSelectionValid(array $periods): void
    {
        if ($periods === []) {
            $this->periodId = '';

            return;
        }

        $ids = array_column($periods, 'id');

        if (! in_array($this->periodId, $ids, true)) {
            $this->periodId = (string) $periods[0]['id'];
        }
    }
}
