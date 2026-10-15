<?php

namespace App\Livewire\Dashboard;

use App\Enums\UserRole;
use App\Services\EngineRoomReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * LaporanEngineRoom — screen-158--laporan-engine-room-web ("Laporan Periode
 * Engine Room"), route name `reports.engine-room`, /reports/engine-room.
 *
 * Reuses EngineRoomReportService — the exact same service the API controller
 * uses — so the page and the API can never report different figures.
 *
 * READ-ONLY: the only actions are picking a mill, picking a production line,
 * picking a period, and exporting. Nothing here writes to
 * `engine_room_records` or `engine_room_details`.
 *
 * WHAT THE SCREEN MUST NEVER LET THE READER MISREAD — the reason several
 * things on this page look redundant:
 *   - RECORDING COVERAGE IS RENDERED ABOVE EVERY OTHER FIGURE, together with
 *     the three numbers that build its denominator (Engine Room units that
 *     actually ran, days counted, 24 canonical slots per day). A period
 *     filled to a fifth still produces tidy-looking averages, and the reader
 *     has to see that before trusting them. When expected_slots is 0 the
 *     percentage renders as a dash with "belum ada slot yang diharapkan" —
 *     never "0,0%", which reads as a recording failure where nothing was
 *     expected yet.
 *   - EVERY ONE OF THE TWENTY-THREE METRICS PRINTS ITS OWN DENOMINATOR, and
 *     at twenty-three cards that is the defect the tests look for: the SAME
 *     denominator on all of them. All measurement columns are nullable and
 *     filled independently, so each min/avg/max prints the number of
 *     readings it was computed from. The card markup is written ONCE and
 *     looped over the response's `group` field, never copied per block.
 *   - THE EIGHT GENSET FIGURES ARE NEVER MERGED. Genset 1 and Genset 2 carry
 *     four identical columns each and the page shows them side by side,
 *     unaveraged, with no delta: a combined average hides the load imbalance
 *     that is the whole reason those parameters are measured. The note on
 *     both blocks states the PHYSICAL parallelism of the two machines and
 *     NOT a shared standard — unlike Kernel Plant's pair, these two have no
 *     operational-target master row to share.
 *   - THE TWO STATUS COLUMNS ARE DISTRIBUTIONS WITH THREE VALUES, NOT
 *     AVERAGES, and their share uses recorded_slots as the denominator —
 *     never expected_slots, which would mix untouched slots into the share
 *     of a recorded value. A known value that never occurred still renders
 *     as a 0 row, "Tidak tercatat" is its own row, and 'Standby' is NEVER
 *     read as 'Off': a standby genset is ready to take load, a dead one is
 *     not. The labels come from Display::option(), never from a map copied
 *     into the blade.
 *   - THE ENERGY-EXPORT FIGURE IS THE LAST VALUE OF THE DAY, NOT AN AVERAGE,
 *     and the page says why: the column's name says 'daily' while it is
 *     stored per time slot, its semantics are unverified, and averaging a
 *     cumulative meter across 24 slots publishes a number that looks valid
 *     and is wrong. Its series sits NEXT TO the total-load trend precisely so
 *     the page can state that the two aggregations differ.
 *   - THERE IS NO DOWNTIME SECTION, and the page states that absence instead
 *     of leaving a hole: engine_room_details has no downtime column at all,
 *     so an unexplained gap would read as a section that failed to load.
 *   - AND NOTHING IS FLAGGED AS OUT OF RANGE, with the statement rendered IN
 *     FULL rather than summarised, attached to the Kelistrikan Tersinkron
 *     block. Engine Room has no operational-target master table, while
 *     system frequency, power factor and busbar voltage have limits that are
 *     very widely known outside this application — so the temptation to flag
 *     is greatest exactly here, and a threshold derived from the period's own
 *     data would be read as an electrical safety limit.
 *
 * ROLE SHAPES THE FILTER BAR, not just the data:
 *   - Supervisor / Mill Management see NO mill picker at all — their mill is
 *     fixed, and offering a picker they cannot use would be a lie. They get a
 *     mill-name caption instead.
 *   - Admin (users.business_unit_id is NULL) sees the picker and must use it.
 *   - A bound account whose business_unit_id is NULL gets a "contact Admin"
 *     notice and NO picker — the all-mills list is never even read. That
 *     fail-closed rule is why render() resolves the mill itself instead of
 *     calling the service's resolveBusinessUnit(), which would throw.
 *
 * CHOOSING A PRODUCTION LINE IS MANDATORY, and until one is chosen the page
 * renders NOT ONE NUMBER and the export buttons are not rendered at all —
 * never the whole mill's totals as a stand-in. The single option of a
 * one-line mill is NOT auto-selected either: choosing is the reader's
 * decision, and picking it for them would make them believe they are looking
 * at the whole mill.
 *
 * SWITCHING MILL CLEARS BOTH THE PERIOD AND THE PRODUCTION LINE (QA LP-W-09):
 * a period AND a line each belong to exactly one mill, so carrying either
 * across would render an access-denied notice for something the user did not
 * do.
 *
 * OPERATOR HAS NO ACCESS TO THIS SCREEN AT ALL, and canAccess() below is its
 * own role list ON PURPOSE rather than a call to
 * EngineRoomReportService::guardAccess(): THE SERVICE ADMITS OPERATOR FROM
 * THE FIRST COMMIT (the mobile twin screen-159 is built in the same series
 * and is its legitimate caller), so if canAccess() ever delegates to the
 * service, this web screen opens to Operator silently in the same breath.
 * Keep the duplication — it is what makes the two decisions independent.
 *
 * The period picker auto-selects the newest period, so the page is useful on
 * first paint rather than demanding a choice before showing anything — except
 * right after a mill switch, where it deliberately stays empty.
 */
#[Layout('dashboard.laporan-engine-room')]
class LaporanEngineRoom extends Component
{
    /**
     * DIBACA DARI QUERY STRING.
     *
     * Laporan Stasiun (screen-140) membawa mill di tautan tiap tile:
     * StationReportService membangun report_path sebagai
     * route($routeName, ['business_unit_id' => ..., 'production_line_id' => ...]).
     * Tanpa #[Url] di sini, Livewire tidak pernah menghidrasinya, sehingga
     * Admin yang baru saja memilih mill lalu menekan tile MENDARAT DI LAYAR
     * YANG MEMINTANYA MEMILIH MILL LAGI — tanpa satu angka pun termuat.
     *
     * `as: 'business_unit_id'` WAJIB — tanpa itu #[Url] memakai NAMA PROPERTI
     * sebagai kunci query ('businessUnitId'), sementara tautan yang dibangun
     * StationReportService memakai 'business_unit_id'. Keduanya tidak pernah
     * bertemu, dan layarnya tetap meminta pilih mill — tanpa tanda apa pun
     * bahwa ada yang salah.
     *
     * TIDAK MEMBUKA KEBOCORAN LINTAS MILL: untuk peran terikat mill,
     * resolvedBusinessUnitId() mengabaikan properti ini sepenuhnya.
     */
    #[Url(as: 'business_unit_id')]
    public string $businessUnitId = '';

    /**
     * PRODUCTION LINE ADALAH KONTEKS YANG DIPILIH, BUKAN IKATAN AKUN.
     *
     * Memilihnya WAJIB, dan itu berbeda dari Data Browser yang punya opsi
     * "Semua Line": laporan menghasilkan ANGKA GABUNGAN, dan total yang
     * mencampur belasan line bukan angka yang bisa ditindaklanjuti siapa pun.
     * Selama belum dipilih, layar ini tidak menampilkan satu angka pun dan
     * tombol ekspor belum dirender.
     *
     * `as: 'production_line_id'` WAJIB dengan alasan yang sama seperti
     * business_unit_id di atas.
     *
     * TIDAK MEMBUKA KEBOCORAN LINTAS MILL: keepProductionLineValid() hanya
     * menerima line yang ada di dalam daftar opsi mill yang berlaku, dan
     * resolvedProductionLineId() memakai
     * EngineRoomReportService::resolveProductionLineOrNull() yang memulangkan
     * null untuk line mill lain — di layar, "belum memilih line" adalah
     * keadaan awal yang normal dan harus merender ajakan memilih, bukan
     * halaman galat. Jalur API yang menolak 422/403 ada di
     * resolveProductionLine().
     */
    #[Url(as: 'production_line_id')]
    public string $productionLineId = '';

    /** Selected reporting period; auto-filled with the newest one. */
    public string $periodId = '';

    /**
     * Mill DIGANTI — bukan dipilih pertama kali — dan sesudahnya belum ada
     * periode yang dipilih manusia.
     *
     * Dipakai keepSelectionValid() untuk TIDAK mengisi periode secara
     * otomatis sesudah mill berganti. Tanpa penanda ini, pengosongan di
     * updatedBusinessUnitId() tidak pernah terlihat: render yang sama
     * langsung memilih periode terbaru mill baru, sehingga pemilih periode
     * tampak berisi sementara layar belum menampilkan satu angka pun —
     * pembaca lalu menyangka ia sedang melihat periode yang ia pilih
     * (temuan QA LP-W-09).
     *
     * DITETAPKAN DI HOOK `updating`, BUKAN `updated`: nilai LAMA masih ada di
     * sana, dan hanya perpindahan dari satu mill ke mill LAIN yang berarti
     * "berganti". Admin yang baru datang lalu memilih mill pertamanya tidak
     * sedang berganti apa pun.
     */
    public bool $millJustSwitched = false;

    /**
     * Operator has no web UI on this screen at all, so the component refuses
     * to mount for them — not merely an empty render. The route middleware
     * ('role:supervisor,mill_management,admin') stops them first in a
     * browser; this guard also covers the component being mounted directly,
     * which is exactly how the test scenario exercises it.
     *
     * abort_unless() over canAccess() — THE COMPONENT'S OWN ROLE LIST, never
     * the service's guardAccess(). See the class docblock: the service
     * ADMITS OPERATOR from the first commit, and borrowing it here would open
     * this page in the same breath.
     */
    public function mount(): void
    {
        abort_unless($this->canAccess(), 403);
    }

    public function updatingBusinessUnitId(mixed $value): void
    {
        $this->millJustSwitched = $this->businessUnitId !== ''
            && (string) $value !== $this->businessUnitId;
    }

    /**
     * Switching mill drops BOTH the period AND the production line: each
     * belongs to exactly one mill, so keeping either would either leak or
     * (worse) render an access-denied notice for something the user did not
     * do (QA LP-W-09).
     *
     * The line is cleared HERE as well as being filtered by
     * keepProductionLineValid() in render(): the hook makes the reset
     * provable from a component test that only sets the mill, while the
     * render-time filter also catches a line arriving by query string.
     */
    public function updatedBusinessUnitId(): void
    {
        $this->periodId = '';
        $this->productionLineId = '';
    }

    /**
     * Periode dipilih manusia => penanda mill-berganti dicabut, sehingga
     * perilaku normal (termasuk pemeriksaan keabsahan) berjalan seperti
     * biasa.
     */
    public function updatedPeriodId(): void
    {
        $this->millJustSwitched = false;
    }

    /**
     * Streams the period's time-slot rows as CSV/Excel through the shared
     * service, so a file downloaded from the page is byte-for-byte what the
     * API endpoint returns — one row per slot, context repeated, all
     * twenty-seven reading columns, and an empty slot still emitting a row
     * with empty cells.
     *
     * Returns null (and does nothing) when no period OR no production line is
     * selected: there is nothing to export yet, which is not an error. The
     * LINE is guarded here and not only in the blade, so a direct call to
     * this method can never fetch a file that mixes every line of the mill.
     *
     * The permission guard, the mill resolution, the line resolution, the
     * format check and the 50.000-ROW ceiling are checked EAGERLY inside the
     * service, before any byte is streamed — and that ceiling counts SLOT
     * ROWS, not records, because one daily record can produce 24.
     *
     * The selected mill is threaded through EXACTLY as render() threads it
     * into buildSummary(): resolvedBusinessUnitId(), not the raw
     * $businessUnitId property. Without it an Admin export would reach
     * resolveBusinessUnit(null) and be refused with 422 even though a mill IS
     * selected on screen, so the button would look inert.
     *
     * A CLOSED period exports exactly like an open one.
     */
    public function exportCsv(string $format = 'csv')
    {
        $productionLineId = $this->resolvedProductionLineId();

        if ($this->periodId === '' || $productionLineId === null) {
            return null;
        }

        $service = app(EngineRoomReportService::class);

        return $service->export(
            $service->authorizePeriod($this->periodId),
            $format,
            $this->resolvedBusinessUnitId(),
            $productionLineId,
        );
    }

    /**
     * DATA DIMUAT DI SINI, BUKAN DI mount(). Mill, line dan periode semuanya
     * dapat berubah setelah paint pertama (dan dua di antaranya datang dari
     * query string), jadi memuat di mount() berarti angkanya tertinggal satu
     * langkah di belakang pilihan yang terlihat di pemilih.
     */
    public function render()
    {
        $service = app(EngineRoomReportService::class);

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
            // lain tidak pernah menjadi opsi.
            $productionLineOptions = $service->productionLineOptions($businessUnitId);

            $this->keepProductionLineValid($productionLineOptions);

            $productionLineId = $this->resolvedProductionLineId();

            $periods = $service->listPeriods($businessUnitId);

            $forbidden = ! $this->keepSelectionValid($periods);

            // PERIODE TETAP PER MILL — daftar periode di atas tidak bertambah
            // dimensi line sama sekali. Yang tersaring adalah DATANYA, dan
            // hanya ketika sebuah line sudah dipilih. Penjaganya ada DI SINI,
            // di fungsi pemuatan ringkasan, bukan hanya di template.
            if (! $forbidden && $productionLineId !== null && $this->periodId !== '') {
                $summary = $service->buildSummary(
                    $service->authorizePeriod($this->periodId),
                    $businessUnitId,
                    $productionLineId,
                );
            }
        }

        return view('livewire.dashboard.laporan-engine-room', [
            'isAdmin' => $isAdmin,
            'businessUnitOptions' => $businessUnitOptions,
            'businessUnitName' => $this->boundBusinessUnitName(),
            'periods' => $periods,
            'selectedPeriod' => collect($periods)->firstWhere('id', $this->periodId),
            'summary' => $summary,
            'productionLineOptions' => $productionLineOptions,
            // Line yang sedang dibaca, dinamai. Angka laporan tidak ada
            // artinya tanpa keterangan line mana yang menghasilkannya — itu
            // justru alasan memilih line dijadikan wajib.
            'selectedProductionLine' => collect($productionLineOptions)->firstWhere('id', $productionLineId),
            // Admin who has not picked a mill yet: the page asks for one
            // instead of showing an empty report.
            'needsMillSelection' => $isAdmin && $businessUnitId === null,
            // Mill sudah pasti, line belum: layar meminta memilih line dan
            // TIDAK menampilkan satu angka pun.
            'needsProductionLineSelection' => $businessUnitId !== null && $productionLineId === null,
            'hasNoMillForAccount' => $hasNoMillForAccount,
            // A period id that is not among this mill's periods — refused
            // visibly, with none of the other mill's figures rendered.
            'forbidden' => $forbidden,
            // HARI TERAKHIR YANG MUNGKIN TERHITUNG untuk periode yang masih
            // berjalan. Diserahkan dari sini, bukan dibaca dari jam di dalam
            // blade: satu-satunya tempat halaman ini menyentuh waktu
            // sekarang, sehingga keterangan "dihitung sampai ..." dapat diuji
            // tanpa menyentuh DOM. Keterangan itu WAJIB menyebut tanggal ini
            // dan BUKAN end_date periode — end_date yang belum tiba akan
            // terbaca sebagai hari yang sudah ikut dihitung.
            'countedUntil' => now()->toDateString(),
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
     *
     * Costs NO query for an account whose business_unit_id is null (a
     * belongsTo with a null key resolves to null without touching the
     * database), which is what keeps the fail-closed state at zero queries.
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
     * belum ada pilihan yang sah.
     *
     * Memakai resolveProductionLineOrNull(), BUKAN resolveProductionLine():
     * di layar, "belum memilih line" adalah keadaan awal yang normal dan
     * harus merender ajakan memilih, bukan halaman galat 422 — sementara
     * sebuah line milik mill LAIN jatuh ke keadaan yang sama persis, yang
     * juga berarti nama line mill lain tidak pernah terbit di sini. Jalur API
     * yang menolak 422/403 ada di controller dan di service.
     */
    protected function resolvedProductionLineId(): ?string
    {
        $businessUnitId = $this->resolvedBusinessUnitId();

        if ($businessUnitId === null || $this->productionLineId === '') {
            return null;
        }

        return app(EngineRoomReportService::class)
            ->resolveProductionLineOrNull($businessUnitId, $this->productionLineId);
    }

    /**
     * Membuang line yang tidak ada di dalam mill yang berlaku — line mill
     * lain lewat query string, atau sisa pilihan setelah Admin berganti mill.
     * Jatuh ke "belum memilih" (yang merender arahan memilih), bukan ke line
     * pertama: memilih line adalah keputusan pembaca laporan, dan menebaknya
     * akan menghasilkan angka yang tidak ia minta — termasuk pada mill yang
     * hanya punya SATU line, yang sengaja tetap tidak dipilih otomatis,
     * karena memilihkannya akan membuat pembaca menyangka ia sedang melihat
     * seluruh mill.
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

    /**
     * Supervisor, Mill Management, and Admin only — never Operator.
     *
     * ITS OWN LIST, NOT EngineRoomReportService::guardAccess(). The service
     * ADMITS OPERATOR FROM THE FIRST COMMIT — screen-159 (the mobile Engine
     * Room report) is built in the same series and is its legitimate caller —
     * so borrowing it here would SILENTLY OPEN THIS WEB SCREEN TO OPERATOR,
     * with no error and nothing on screen to hint at it. Keep the
     * duplication: it is the thing that makes the two decisions independent.
     */
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
     * swapped: a silent swap would answer a cross-mill probe with a different
     * mill's numbers under the id that was asked for.
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
            // Sesudah mill diganti: DIBIARKAN KOSONG. Pemilih menampilkan
            // "Pilih Periode" dan layar menunggu pilihan — bukan menyuguhkan
            // periode yang tidak pernah dipilih siapa pun (QA LP-W-09).
            if ($this->millJustSwitched) {
                return true;
            }

            $this->periodId = (string) $periods[0]['id'];

            return true;
        }

        return in_array($this->periodId, array_column($periods, 'id'), true);
    }
}
