<?php

namespace App\Livewire\Dashboard;

use App\Enums\UserRole;
use App\Services\StationReportService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * LaporanStasiun — screen-140--laporan-stasiun-web ("Laporan Stasiun"),
 * route name `reports.stations`, /reports.
 *
 * The single web entry point to every per-station period report, and the
 * only sidebar entry for that family. Two steps, in this order: settle
 * which mill is being looked at, then pick the station — the grid is not
 * rendered at all until the mill is settled.
 *
 * Reuses StationReportService — the exact same service the API controller
 * (App\Http\Controllers\Api\StationReportController) uses — so the page
 * and the API can never show a different station list (mirrors
 * LaporanSterilizer/SterilizerReportService).
 *
 * READ-ONLY: the only actions are picking a mill and following a link.
 * Nothing here writes.
 *
 * ROLE SHAPES THE FIRST STEP, not just the data:
 *   - Supervisor / Mill Management have NO mill picker at all — their mill
 *     is fixed, and offering a picker they cannot use would be a lie. The
 *     mill is shown as a caption instead.
 *   - Admin (users.business_unit_id is NULL) sees the picker and must use
 *     it: without a mill there is no station grid, so the page asks for
 *     one instead of rendering an empty grid that reads like "no data".
 *   - An account bound to a mill that has no mill (broken master data)
 *     is told to contact Admin. It deliberately does NOT fall back to the
 *     full mill list — the list is never even queried for that case.
 */
#[Layout('dashboard.laporan-stasiun')]
class LaporanStasiun extends Component
{
    /** Admin-only mill selection; never rendered, never used, for any other role. */
    public string $businessUnitId = '';

    /**
     * Operator is a mobile-only actor with no web access at all, so the
     * component refuses to mount for them — not merely an empty render.
     * The route middleware ('role:supervisor,mill_management,admin') stops
     * them first in a browser; this guard also covers the component being
     * mounted directly, which is how the test scenario exercises it.
     */
    public function mount(): void
    {
        abort_unless($this->canAccess(), 403);
    }

    public function render()
    {
        $service = app(StationReportService::class);

        $isAdmin = $this->isAdmin();

        // Admin-only, and the ONLY place the whole-mill list is read. A
        // Supervisor / Mill Management without a mill must never reach
        // this line — see the class docblock.
        $businessUnitOptions = $isAdmin ? $service->businessUnitOptions() : [];

        if ($isAdmin) {
            $this->keepSelectionValid($businessUnitOptions);
        }

        $businessUnitId = $this->resolvedBusinessUnitId();

        $businessUnit = null;
        $stations = [];

        if ($businessUnitId !== null) {
            $result = $service->stations($businessUnitId);

            $businessUnit = $result['business_unit'];
            $stations = $result['stations'];
        }

        return view('livewire.dashboard.laporan-stasiun', [
            'isAdmin' => $isAdmin,
            'businessUnitOptions' => $businessUnitOptions,
            'businessUnit' => $businessUnit,
            'stations' => $stations,
            // Admin who has not picked a mill yet: the page asks for one
            // instead of showing an empty grid.
            'needsMillSelection' => $isAdmin && $businessUnitId === null,
            // Supervisor / Mill Management whose account is not linked to
            // any mill — a master-data fault, shown as such rather than as
            // an empty screen, and WITHOUT offering the full mill list.
            'millMissingForAccount' => ! $isAdmin && $businessUnitId === null,
        ]);
    }

    /**
     * The mill whose stations are being listed: the user's own for
     * Supervisor / Mill Management (never negotiable from the UI, so a
     * hand-set $businessUnitId is simply never read for them), the picked
     * one for Admin, null when an Admin has not picked yet or when a bound
     * account has no mill.
     */
    protected function resolvedBusinessUnitId(): ?string
    {
        if ($this->isAdmin()) {
            return $this->businessUnitId !== '' ? $this->businessUnitId : null;
        }

        $businessUnitId = (string) (auth()->user()?->business_unit_id ?? '');

        return $businessUnitId !== '' ? $businessUnitId : null;
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
     * Drops a mill id that is not in the current option list — a
     * hand-edited or stale value. Falls back to "nothing picked" (which
     * renders the hint) rather than letting the service answer 404 for a
     * selection the user never made.
     *
     * @param  list<array{id: string, name: string}>  $options
     */
    protected function keepSelectionValid(array $options): void
    {
        if ($this->businessUnitId === '') {
            return;
        }

        if (! in_array($this->businessUnitId, array_column($options, 'id'), true)) {
            $this->businessUnitId = '';
        }
    }
}
