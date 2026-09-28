<?php

namespace App\Livewire\Dashboard;

use App\Enums\UserRole;
use App\Services\SterilizerReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * LaporanSterilizer — screen-129--laporan-sterilizer-web ("Laporan
 * Sterilizer"), route name `reports.sterilizer`, /reports/sterilizer.
 *
 * Reuses SterilizerReportService — the exact same service the API
 * controller (App\Http\Controllers\Api\SterilizerReportController) uses —
 * so the page and the API can never report different figures (mirrors
 * ManagementReport/ManagementReportService and DashboardHome/
 * DashboardService).
 *
 * READ-ONLY: the only actions are picking a mill, picking a period,
 * opening/closing the daily recap, and exporting. Nothing here writes to
 * `sterilizer_records` or `sterilizer_details`.
 *
 * ROLE SHAPES THE FILTER BAR, not just the data:
 *   - Supervisor / Mill Management see NO mill picker at all — their mill
 *     is fixed, and offering a picker they cannot use would be a lie.
 *   - Admin (users.business_unit_id is NULL) sees the picker and must use
 *     it: without a mill there is no data to show, so the page asks for one
 *     instead of rendering an empty report that reads like "no data".
 *
 * The period picker auto-selects the newest period, so the page is useful
 * on first paint rather than demanding a choice before showing anything.
 */
#[Layout('dashboard.laporan-sterilizer')]
class LaporanSterilizer extends Component
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

    /**
     * PRODUCTION LINE ADALAH KONTEKS YANG DIPILIH, BUKAN IKATAN AKUN.
     *
     * Tidak ada `users.production_line_id` dan tidak boleh ada: satu orang
     * bekerja di line mana pun di millnya. Karena itu line tinggal di state
     * komponen, persis seperti mill milik Admin.
     *
     * MEMILIHNYA WAJIB, dan itu berbeda dari Data Browser yang punya opsi
     * "Semua Line". Alasannya menentukan: laporan menghasilkan ANGKA
     * GABUNGAN, dan "Total 1.200" yang mencampur belasan line bukan angka
     * yang bisa ditindaklanjuti siapa pun. Selama belum dipilih, layar ini
     * tidak menampilkan satu angka pun — mekanismenya sama persis dengan
     * needsMillSelection, bukan mekanisme kedua.
     *
     * `as: 'production_line_id'` WAJIB, dengan alasan yang sama seperti
     * business_unit_id di atas: tautan tile yang dibangun
     * StationReportService memakai kunci `production_line_id`, sedangkan
     * tanpa `as:` Livewire memakai NAMA PROPERTI (`productionLineId`).
     * Keduanya tidak akan pernah bertemu dan layar tetap meminta memilih
     * line — persis bug yang 13 test penjaga pada commit 8658f6e ada untuk
     * mencegah, hanya dengan nama lain.
     *
     * TIDAK MEMBUKA KEBOCORAN LINTAS MILL: keepProductionLineValid() hanya
     * menerima line yang ada di dalam mill yang berlaku, jadi line mill lain
     * lewat properti atau query string dibuang sebelum menyentuh data.
     */
    #[Url(as: 'production_line_id')]
    public string $productionLineId = '';

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
     * Streams the period's cycles as CSV/Excel through the shared service,
     * so a file downloaded from the page is byte-for-byte what the API
     * endpoint returns. Returns null (and does nothing) when no period is
     * selected — there is nothing to export yet, which is not an error.
     */
    public function export(string $format = 'csv')
    {
        $productionLineId = $this->resolvedProductionLineId();

        // Tanpa periode ATAU tanpa production line tidak ada yang bisa
        // diekspor. Line dijaga di sini dan bukan hanya di blade, supaya
        // sebuah panggilan langsung ke method ini tidak bisa mengambil
        // berkas yang mencampur seluruh line mill.
        if ($this->periodId === '' || $productionLineId === null) {
            return null;
        }

        $service = app(SterilizerReportService::class);

        return $service->export(
            $service->authorizePeriod($this->periodId),
            $format,
            $this->resolvedBusinessUnitId(),
            $productionLineId,
        );
    }

    public function render()
    {
        $service = app(SterilizerReportService::class);

        $isAdmin = $this->isAdmin();
        $businessUnitId = $this->resolvedBusinessUnitId();

        $businessUnitOptions = $isAdmin ? $service->businessUnitOptions() : [];
        $productionLineOptions = [];
        $productionLineId = null;
        $periods = [];
        $summary = null;

        if ($businessUnitId !== null) {
            // Opsi line SELALU dibatasi mill yang berlaku, jadi line mill
            // lain tidak pernah menjadi opsi — dan keepProductionLineValid()
            // membuang sisa pilihan yang tidak ada di daftar itu, yang juga
            // cara "Admin berganti mill -> pilihan line direset" bekerja
            // tanpa hook updated* apa pun.
            $productionLineOptions = $service->productionLineOptions($businessUnitId);

            $this->keepProductionLineValid($productionLineOptions);

            $productionLineId = $this->productionLineId !== '' ? $this->productionLineId : null;

            $periods = $service->listPeriods($businessUnitId);

            $this->keepSelectionValid($periods);

            // PERIODE TETAP PER MILL — daftar periode di atas tidak
            // bertambah dimensi line sama sekali. Yang tersaring adalah
            // datanya, dan hanya ketika sebuah line sudah dipilih.
            if ($productionLineId !== null && $this->periodId !== '') {
                $summary = $service->summary(
                    $service->authorizePeriod($this->periodId),
                    $productionLineId,
                );
            }
        }

        return view('livewire.dashboard.laporan-sterilizer', [
            'isAdmin' => $isAdmin,
            'businessUnitOptions' => $businessUnitOptions,
            'periods' => $periods,
            'selectedPeriod' => collect($periods)->firstWhere('id', $this->periodId),
            'summary' => $summary,
            // Admin who has not picked a mill yet: the page asks for one
            // instead of showing an empty report.
            'productionLineOptions' => $productionLineOptions,
            // Line yang sedang dibaca, dinamai. Angka laporan tidak ada
            // artinya tanpa keterangan line mana yang menghasilkannya —
            // itu justru alasan memilih line dijadikan wajib.
            'selectedProductionLine' => collect($productionLineOptions)->firstWhere('id', $productionLineId),
            'needsMillSelection' => $isAdmin && $businessUnitId === null,
            // Mill sudah pasti, line belum: layar meminta memilih line dan
            // TIDAK menampilkan satu angka pun — mekanisme yang sama persis
            // dengan needsMillSelection di atasnya, bukan mekanisme kedua.
            'needsProductionLineSelection' => $businessUnitId !== null && $productionLineId === null,
        ]);
    }

    /**
     * The mill whose report is being read: the user's own for Supervisor /
     * Mill Management (never negotiable from the UI), the picked one for
     * Admin, null when an Admin has not picked yet.
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
     * Line yang benar-benar dipakai untuk menyaring angka, atau null bila
     * belum ada pilihan yang sah. Divalidasi ulang lewat service supaya
     * jalur ekspor memakai jaminan yang sama dengan jalur render — sebuah
     * line mill lain tidak pernah sampai ke berkas yang diunduh.
     */
    protected function resolvedProductionLineId(): ?string
    {
        $businessUnitId = $this->resolvedBusinessUnitId();

        if ($businessUnitId === null || $this->productionLineId === '') {
            return null;
        }

        return app(SterilizerReportService::class)->resolveProductionLine($businessUnitId, $this->productionLineId);
    }

    /**
     * Membuang line yang tidak ada di dalam mill yang berlaku — line mill
     * lain lewat query string, atau sisa pilihan setelah Admin berganti
     * mill. Jatuh ke "belum memilih" (yang merender arahan memilih), bukan
     * ke line pertama: memilih line adalah keputusan pembaca laporan, dan
     * menebaknya akan menghasilkan angka yang tidak ia minta.
     *
     * Kembarannya keepSelectionValid() untuk periode; keduanya protected,
     * sehingga permukaan publik komponen ini tetap sebatas pemilih, toggle
     * dan ekspor.
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
     * Guards against a period id that is no longer in the current list —
     * which is also how switching mill works: no updated* hook is needed,
     * because the previous mill's period simply is not in the new mill's
     * list and is replaced by its newest one.
     *
     * Deliberate: the public surface of this component stays limited to a
     * toggle and an export (plus the two bound properties). A read-only
     * report must not expose anything that reads like a write action.
     *
     * Also covers a hand-edited value. Falls back
     * to the newest period of the list rather than letting
     * authorizePeriod() answer 403/404 for a selection the user never made.
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
