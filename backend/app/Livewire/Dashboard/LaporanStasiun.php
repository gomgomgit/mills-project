<?php

namespace App\Livewire\Dashboard;

use App\Enums\UserRole;
use App\Services\StationReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
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
    /**
     * Admin-only mill selection; never rendered, never used, for any other role.
     *
     * DIBACA DARI QUERY STRING sejak 2026-09-25 — arah sebaliknya dari
     * perbaikan yang sama pada kelima layar laporan. Tanpa #[Url], Admin
     * yang memilih mill di sini lalu menekan sebuah tile dan KEMBALI akan
     * mendapati pilihannya hilang, sehingga ia harus memilih mill lagi
     * untuk membuka laporan stasiun kedua. Mill-nya ada di URL; layar ini
     * yang mengabaikannya.
     *
     * `as:` WAJIB. Tanpa itu #[Url] memakai NAMA PROPERTI sebagai kunci
     * query (businessUnitId), sementara seluruh tautan di aplikasi ini
     * membawa business_unit_id. Keduanya tidak akan pernah bertemu dan
     * layar tetap meminta pilih mill — persis seperti sebelum diperbaiki,
     * tanpa satu pun tanda bahwa ada yang salah.
     *
     * TIDAK membuka kebocoran lintas mill: resolvedBusinessUnitId()
     * mengabaikan properti ini sepenuhnya untuk peran terikat mill, jadi
     * memaksakan mill lain lewat query string tidak mengubah apa pun.
     */
    #[Url(as: 'business_unit_id')]
    public string $businessUnitId = '';

    /**
     * PRODUCTION LINE ADALAH KONTEKS YANG DIPILIH, BUKAN IKATAN AKUN —
     * tidak ada `users.production_line_id` dan tidak boleh ada.
     *
     * LAYAR INI PUN MEMERLUKANNYA, dan itu bukan tambahan hiasan.
     * StationReportService::stationList() membangun satu tile per JENIS
     * stasiun hanya dari `business_unit_id`, yang mengandaikan satu stasiun
     * per jenis per mill. Andaian itu sudah salah hari ini: satu mill di dev
     * punya 13 production line dengan jenis stasiun yang sama berulang, jadi
     * layar ini sudah ambigu sebelum perubahan apa pun. Tile kini membawa
     * line terpilih, sama seperti ia sudah membawa mill, sehingga laporan
     * tujuan langsung terisi dan tidak meminta pengguna memilih untuk kedua
     * kalinya.
     *
     * `as: 'production_line_id'` WAJIB — alasannya sama persis dengan
     * business_unit_id di atas: tanpa itu Livewire memakai NAMA PROPERTI
     * sebagai kunci query dan tautan yang dibangun StationReportService
     * tidak akan pernah bertemu dengan layar tujuan. Itu bug yang
     * diperbaiki commit 8658f6e; kembarannya untuk line tidak dibuat.
     */
    #[Url(as: 'production_line_id')]
    public string $productionLineId = '';

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
        $productionLine = null;
        $productionLineOptions = [];
        $productionLineId = null;
        $stations = [];

        if ($businessUnitId !== null) {
            // Opsi line SELALU dibatasi mill yang berlaku, dan
            // keepProductionLineValid() membuang sisa pilihan yang tidak ada
            // di daftar itu — yang juga cara "Admin berganti mill -> pilihan
            // line direset" bekerja, tanpa hook updated* apa pun.
            $productionLineOptions = $service->productionLineOptions($businessUnitId);

            $this->keepProductionLineValid($productionLineOptions);

            $productionLineId = $this->productionLineId !== '' ? $this->productionLineId : null;

            $result = $service->stations($businessUnitId, $productionLineId);

            $businessUnit = $result['business_unit'];
            $productionLine = $result['production_line'];

            // Tanpa line, TIDAK ADA satu tile pun yang diserahkan ke blade —
            // bukan sekadar disembunyikan lewat CSS. Sebuah tile tanpa line
            // akan mendaratkan pengguna di laporan yang meminta memilih line
            // lagi, dan itu persis kesalahan yang commit 8658f6e perbaiki
            // untuk mill.
            $stations = $productionLineId === null ? [] : $result['stations'];
        }

        return view('livewire.dashboard.laporan-stasiun', [
            'isAdmin' => $isAdmin,
            'businessUnitOptions' => $businessUnitOptions,
            'businessUnit' => $businessUnit,
            'productionLine' => $productionLine,
            'productionLineOptions' => $productionLineOptions,
            'stations' => $stations,
            // Admin who has not picked a mill yet: the page asks for one
            // instead of showing an empty grid.
            'needsMillSelection' => $isAdmin && $businessUnitId === null,
            // Supervisor / Mill Management whose account is not linked to
            // any mill — a master-data fault, shown as such rather than as
            // an empty screen, and WITHOUT offering the full mill list.
            'millMissingForAccount' => ! $isAdmin && $businessUnitId === null,
            // Mill sudah pasti, line belum: layar meminta memilih line dan
            // tidak merender grid stasiun — mekanisme yang sama persis
            // dengan needsMillSelection di atasnya, bukan mekanisme kedua.
            'needsProductionLineSelection' => $businessUnitId !== null && $productionLineId === null,
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

    /**
     * Membuang line yang tidak ada di dalam mill yang berlaku — line mill
     * lain lewat query string, atau sisa pilihan setelah Admin berganti
     * mill. Jatuh ke "belum memilih" (yang merender arahan memilih), bukan
     * ke line pertama: memilih line adalah keputusan pengguna, dan
     * menebaknya akan mengirimkan tile ke line yang tidak ia minta.
     *
     * Kembarannya keepSelectionValid() untuk mill.
     *
     * @param  list<array{id: string, name: string}>  $options
     */
    protected function keepProductionLineValid(array $options): void
    {
        if ($this->productionLineId === '') {
            return;
        }

        if (! in_array($this->productionLineId, array_column($options, 'id'), true)) {
            $this->productionLineId = '';
        }
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
