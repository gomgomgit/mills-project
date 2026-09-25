<?php

namespace App\Livewire\Dashboard;

use App\Enums\UserRole;
use App\Services\CagesTrackReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * LaporanCagesTrack — screen-130--laporan-cages-track-web ("Laporan Cages
 * & Tracks"), route name `reports.cages-track`, /reports/cages-track.
 *
 * Reuses CagesTrackReportService — the exact same service the API
 * controller (App\Http\Controllers\Api\CagesTrackReportController) uses —
 * so the page and the API can never report different figures (mirrors
 * LaporanSterilizer/SterilizerReportService and LaporanStasiun/
 * StationReportService).
 *
 * READ-ONLY: the only actions are picking a mill, picking a period,
 * opening/closing the daily recap, and exporting. Nothing here writes to
 * `cages_track_records` or `cages_tipped_times`.
 *
 * ROLE SHAPES THE FILTER BAR, not just the data:
 *   - Supervisor / Mill Management see NO mill picker at all — their mill
 *     is fixed, and offering a picker they cannot use would be a lie. They
 *     get a mill-current caption instead.
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
 * The period picker auto-selects the newest period, so the page is useful
 * on first paint rather than demanding a choice before showing anything.
 */
#[Layout('dashboard.laporan-cages-track')]
class LaporanCagesTrack extends Component
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

    /** Daily recap table open/closed — collapsed content, never a data filter. */
    public bool $showRecap = false;

    /**
     * Operator is a mobile-only actor with no web access at all, so the
     * component refuses to mount for them — not merely an empty render.
     * The route middleware ('role:supervisor,mill_management,admin') stops
     * them first in a browser; this guard also covers the component being
     * mounted directly, which is exactly how the test scenario exercises
     * it.
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
     * Streams the period's hourly tipping rows as CSV/Excel through the
     * shared service, so a file downloaded from the page is byte-for-byte
     * what the API endpoint returns. Returns null (and does nothing) when
     * no period is selected — there is nothing to export yet, which is not
     * an error.
     *
     * A CLOSED period exports exactly like an open one: the period lock
     * governs writing data, not reading a report.
     */
    public function export(string $format = 'csv')
    {
        if ($this->periodId === '') {
            return null;
        }

        $service = app(CagesTrackReportService::class);

        return $service->export(
            $service->authorizePeriod($this->periodId),
            $format,
            $this->resolvedBusinessUnitId(),
        );
    }

    public function render()
    {
        $service = app(CagesTrackReportService::class);

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
                $summary = $service->summary($service->authorizePeriod($this->periodId));
            }
        }

        return view('livewire.dashboard.laporan-cages-track', [
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
     * correct even before a period is chosen, and must never echo a mill
     * the user merely asked for.
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
     * because the previous mill's period simply is not in the new mill's
     * list and is replaced by its newest one. That is also what stops a
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
