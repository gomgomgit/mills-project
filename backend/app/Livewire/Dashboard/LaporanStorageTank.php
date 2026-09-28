<?php

namespace App\Livewire\Dashboard;

use App\Enums\UserRole;
use App\Services\StorageTankReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * LaporanStorageTank — screen-133--laporan-storage-tank-web ("Laporan
 * Storage Tank"), route name `reports.storage-tank`,
 * /reports/storage-tank.
 *
 * Reuses StorageTankReportService — the exact same service the API
 * controller (App\Http\Controllers\Api\StorageTankReportController) uses —
 * so the page and the API can never report different figures (mirrors
 * LaporanClarification/ClarificationReportService).
 *
 * READ-ONLY: the only actions are picking a mill, picking a period,
 * opening/closing the daily recap, and exporting. Nothing here writes to
 * `storage_tank_records` or `storage_tank_details`, and the component
 * deliberately exposes no action that reads like one.
 *
 * WHAT THE SCREEN MUST NEVER LET THE READER MISREAD — the reason several
 * things on this page look redundant:
 *   - Opening and closing stock are a COMPARISON OF TWO READINGS, not an
 *     aggregate, so the TIMESTAMP of each is rendered next to its number
 *     everywhere the number appears. An opening stock first recorded on day
 *     three means two days went unrecorded and the movement covers a shorter
 *     span than its label claims.
 *   - Net movement is the SUM OF THE PER-TANK MOVEMENTS, and the per-tank
 *     table it is the column sum of sits right below it, so the reader can
 *     check the two against each other.
 *   - A tank with one stock reading renders "tidak dapat dihitung", NEVER 0.
 *     Zero claims the stock did not change; that claim was never measured.
 *   - A negative movement renders with its minus sign in ordinary text
 *     colour. Oil leaving the tank is the normal case, not an alarm.
 *   - Average temperature is whatever the Operator recorded in that column.
 *     The page never derives it from the top/middle/bottom temperatures.
 *   - A null figure renders as an unavailable marker, NEVER as 0.
 *   - Recording coverage is rendered ABOVE every number, because it is what
 *     says how far apart in time the two compared readings sit.
 *   - NOTHING on this page is flagged as out of range: no threshold card, no
 *     safe/danger colouring, no outlier, no IQR. Storage Tank has no
 *     operational-target master table.
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
 * nothing at all — the page still shows the caller's own mill, with HTTP
 * 200, deliberately not a 403.
 *
 * A PERIOD id belonging to another mill IS refused, and visibly: the page
 * renders an access-denied notice and NOT one figure of the other mill. The
 * two cases are different on purpose — see the service docblock.
 *
 * Operator has no web access to this screen AND no API access either. The
 * mobile Storage Tank report is screen-139, a separate screen with its own
 * endpoints, so there is no Operator widening anywhere in this screen.
 *
 * The period picker auto-selects the newest period, so the page is useful on
 * first paint rather than demanding a choice before showing anything.
 */
#[Layout('dashboard.laporan-storage-tank')]
class LaporanStorageTank extends Component
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

    /**
     * Daily recap table shown/hidden — collapsed content, never a data
     * filter.
     *
     * OPEN BY DEFAULT, and that is a decision rather than a default left
     * alone: the inclusive-range scenario reads the first and last dates
     * straight off data-testid=daily-table without toggling anything, and the
     * long-recap scenario starts by CLOSING the table (first toggle -> not
     * rendered, second -> rendered again). Both only make sense from an open
     * start. A plain button rather than <details>, so the visible state and
     * the rendered DOM can never disagree: closed means genuinely absent from
     * the DOM, not merely collapsed.
     */
    public bool $dailyRecapOpen = true;

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

    /**
     * Switching mill drops the period selection rather than carrying it
     * across: a period belongs to exactly one mill, so keeping it would
     * either leak or (worse) render an access-denied notice for something
     * the user did not do. Only a HAND-FORCED period id from another mill
     * reaches the refusal path in render().
     */
    public function updatedBusinessUnitId(): void
    {
        $this->periodId = '';
    }

    /** Opens/closes the daily recap table (no re-query — the data is already loaded). */
    public function toggleDailyRecap(): void
    {
        $this->dailyRecapOpen = ! $this->dailyRecapOpen;
    }

    /**
     * Streams the period's time-slot rows as CSV/Excel through the shared
     * service, so a file downloaded from the page is byte-for-byte what the
     * API endpoint returns. Returns null (and does nothing) when no period is
     * selected — there is nothing to export yet, which is not an error.
     *
     * The permission guard, the mill resolution and the 50.000-row ceiling
     * are checked EAGERLY inside the service, before any byte is streamed.
     *
     * The selected mill is threaded through EXACTLY as render() threads it
     * into buildSummary() — resolvedBusinessUnitId(), not the raw
     * $businessUnitId property. Without it an Admin export would reach
     * resolveBusinessUnit(null) and be refused with 422 even though a mill
     * IS selected on screen, so the button would look inert. The bound roles
     * are unaffected: resolveBusinessUnit() discards the argument for them,
     * so handing it their own id changes nothing (still 200, still their own
     * mill), and an Admin who has genuinely picked no mill still passes null
     * and still gets the documented 422 — it is never defaulted to a mill.
     *
     * A CLOSED period exports exactly like an open one: the period lock
     * governs writing data, not reading a report.
     */
    public function exportCsv(string $format = 'csv')
    {
        $productionLineId = $this->resolvedProductionLineId();

        // Tanpa periode ATAU tanpa production line tidak ada yang bisa
        // diekspor. Line dijaga di sini dan bukan hanya di blade, supaya
        // sebuah panggilan langsung ke method ini tidak bisa mengambil
        // berkas yang mencampur seluruh line mill.
        if ($this->periodId === '' || $productionLineId === null) {
            return null;
        }

        $service = app(StorageTankReportService::class);

        return $service->export(
            $service->authorizePeriod($this->periodId),
            $format,
            $this->resolvedBusinessUnitId(),
            $productionLineId,
        );
    }

    public function render()
    {
        $service = app(StorageTankReportService::class);

        $isAdmin = $this->isAdmin();
        $businessUnitId = $this->resolvedBusinessUnitId();

        // FAIL CLOSED: a bound account with no mill never reaches
        // businessUnitOptions(), so the all-mills list is never built for a
        // role that is supposed to be tied to exactly one.
        $hasNoMillForAccount = ! $isAdmin && $businessUnitId === null;

        $businessUnitOptions = $isAdmin ? $service->businessUnitOptions() : [];
        $productionLineOptions = [];
        $productionLineId = null;
        $periods = [];
        $summary = null;
        $forbidden = false;

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

            $forbidden = ! $this->keepSelectionValid($periods);

            // PERIODE TETAP PER MILL — daftar periode di atas tidak
            // bertambah dimensi line sama sekali. Yang tersaring adalah
            // datanya, dan hanya ketika sebuah line sudah dipilih.
            if (! $forbidden && $productionLineId !== null && $this->periodId !== '') {
                $summary = $service->buildSummary(
                    $service->authorizePeriod($this->periodId),
                    $businessUnitId,
                    $productionLineId,
                );
            }
        }

        return view('livewire.dashboard.laporan-storage-tank', [
            'isAdmin' => $isAdmin,
            'businessUnitOptions' => $businessUnitOptions,
            'businessUnitName' => $this->boundBusinessUnitName(),
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
            'hasNoMillForAccount' => $hasNoMillForAccount,
            // A period id that is not among this mill's periods — refused
            // visibly, with none of the other mill's figures rendered.
            'forbidden' => $forbidden,
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

        return app(StorageTankReportService::class)->resolveProductionLine($businessUnitId, $this->productionLineId);
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
     * Auto-selects the newest period when nothing is chosen yet, and reports
     * whether the current choice is legitimate.
     *
     * Returns FALSE when a period id was supplied that is not among this
     * mill's periods — which, because updatedBusinessUnitId() already clears
     * the selection on every mill switch, only happens when someone hands in
     * another mill's period id. That is refused visibly rather than silently
     * swapped: a silent swap would answer a cross-mill probe with a
     * different mill's numbers under the id that was asked for.
     *
     * Deliberate: the public surface of this component stays limited to a
     * toggle and an export (plus the three bound properties). A read-only
     * report must not expose anything that reads like a write action.
     *
     * @param  list<array{id: string}>  $periods
     */
    protected function keepSelectionValid(array $periods): bool
    {
        if ($periods === []) {
            $this->periodId = '';

            return true;
        }

        if ($this->periodId === '') {
            $this->periodId = (string) $periods[0]['id'];

            return true;
        }

        return in_array($this->periodId, array_column($periods, 'id'), true);
    }
}
