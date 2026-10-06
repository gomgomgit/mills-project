<?php

namespace App\Livewire\Dashboard;

use App\Enums\UserRole;
use App\Services\DepricarpingReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * LaporanDepricarping — screen-152--laporan-depricarping-web ("Laporan Depricarping"),
 * route name `reports.depricarping`, /reports/depricarping.
 *
 * Reuses DepricarpingReportService — the exact same service the API controller
 * (App\Http\Controllers\Api\DepricarpingReportController) and the mobile screen
 * (screen-153) use — so the page, the API and the phone can never report
 * different figures.
 *
 * READ-ONLY: the only actions are picking a mill, picking a production line,
 * picking a period, opening/closing the daily recap, and exporting. Nothing
 * here writes to `depricarping_records` or `depricarping_details`.
 *
 * WHAT THE SCREEN MUST NEVER LET THE READER MISREAD — the reason several
 * things on this page look redundant:
 *   - RECORDING COVERAGE IS RENDERED ABOVE EVERY OTHER FIGURE, together with
 *     the three numbers that build its denominator (pressers that actually
 *     ran, days counted, 24 canonical slots per day). A period filled to a
 *     fifth still produces tidy-looking averages, and the reader has to see
 *     that before trusting them.
 *   - EVERY AVERAGE RENDERS ITS OWN DENOMINATOR BESIDE IT. All SEVEN
 *     measurement columns are nullable and are filled independently, so each
 *     min/avg/max prints the number of slots it was computed from. A slot
 *     that did not record a column has not measured it at zero — it has not
 *     measured it at all.
 *   - ALL THREE TARGET COLUMNS SIT ON THE SAME ROW AS THE FIGURE, and they
 *     answer three different questions: `target_range` is where it SHOULD
 *     be, `critical_limit` is when it is TOO FAR, and
 *     `operational_consequence_justification` is WHAT IS AT STAKE. The third
 *     exists on neither the Threshing nor the Pressing master, and it is the
 *     column most easily dropped for space and most costly to drop: without
 *     it, three parameters all past their limits look equally urgent, and
 *     they are not. So the parameter table here is EIGHT columns wide, one
 *     more than Threshing's and Pressing's seven.
 *   - THE TWO NUT SILO ROWS CARRY A SHARED NOTE saying their standard is ONE
 *     master row. The master holds a single 'Nut Silo Temperature' while the
 *     detail table has nut_silo_1_temp_c and nut_silo_2_temp_c. They stay two
 *     rows with their own denominators — two physical silos, and averaging
 *     them would hide a drifting silo behind a normal one — but without the
 *     note the identical standard printed twice in a row reads like
 *     duplicated data and someone will "clean it up".
 *   - AND NOTHING IS FLAGGED AS OUT OF RANGE. No threshold chip, no
 *     safe/danger colouring, no severity — and here the reason has to be
 *     sharper than on either earlier report, because this master's
 *     `critical_limit` is the TIDIEST of the three ('< 35 or > 55 mmH2O',
 *     '> 1.00%', '< 55°C or > 75°C'): five of six carry an explicit numeric
 *     comparator. Three things hold it back. The limit columns are VARCHAR
 *     that Admin / Mill Management may edit at any time, so a parser that
 *     fails on the next shape would STOP WARNING without raising anything —
 *     and a warning that disappears reads as "everything is fine". The
 *     two-sided form needs a different parser from the one-sided one, and the
 *     unit is inside the text too. And one parameter's own direction is
 *     unsettled (next point), so parsing its limit would produce an INVERTED
 *     warning — worse than none. The page SAYS all of this, because an
 *     unexplained absence of flagging reads as an unfinished feature.
 *   - ONE STANDARD IS SHOWN WITH NO FIGURE, AND ONE FIGURE WITH NO STANDARD,
 *     ON PURPOSE — the finding on this page most in need of a human reader.
 *     The master's standard is 'Kernel Loss in Fibre', target '< 0.50%' (a
 *     LOSS, smaller is better). The column is kernel_recovery_in_fibre_percent
 *     and all four Depricarping input/detail screens label it 'Kernel Recovery
 *     in Fibre' (a RECOVERY, larger is better). Opposite framings of one
 *     quantity, and nothing settles it: the factory writes null, no seeder
 *     fills it, the database holds zero values. So the figure prints under the
 *     column's own label with a note that no standard is paired with it, and
 *     the standard prints in the standards-without-measurement section with
 *     the reason stated. Pairing them would make this page judge in the
 *     reverse direction with nobody noticing.
 *   - THE STANDARDS-WITHOUT-MEASUREMENT SECTION IS DRAWN EVEN WHEN EMPTY,
 *     with a note that every standard has a measurement. A section that
 *     disappears cannot be told apart from a section nobody built — and on
 *     this page that section carries the point above.
 *   - A PARAMETER ROW WITH NO READINGS IS STILL RENDERED IN FULL, with its
 *     values shown as unavailable and its denominator as 0 slots. An absent
 *     row reads as "there is no such parameter"; a present row of unavailables
 *     reads as "nobody measured it", and only the second is true.
 *   - A null figure renders as an unavailable marker, NEVER as 0. And a
 *     coverage percentage with no denominator yet renders as a dash, never as
 *     0% — 0% claims something was measured and came out at zero.
 *   - DOWNTIME IS A NUMBER HERE, ON ITS OWN BLOCK — the first station report
 *     where that is possible, because depricarping_details.downtime_minutes is
 *     an INTEGER where Threshing and Pressing had only free text. Total
 *     minutes, how many slots recorded it, and the average per RECORDING
 *     slot. The page states that slots with no downtime entry are NOT counted
 *     as zero minutes, and that this parameter has no master standard at all
 *     — otherwise the empty target cells read as a master nobody filled in.
 *     When nothing was recorded the block says so rather than printing
 *     0 minutes, which would read as "the station never stopped".
 *   - FINDINGS ARE GROUPED LITERALLY, on a SEPARATE block from downtime, and
 *     the page says so. Two spellings of one thing appear as two rows; without
 *     the note that reads as a defect in the report rather than as the shape
 *     of the data. Kept separate from downtime because one answers "how long"
 *     and the other "what was seen".
 *   - DRAFT RECORDS ARE COUNTED in every figure, and the draft count is
 *     printed so the reader knows how much of the report rests on unfinished
 *     data.
 *   - VERIFICATION STATUS IS COMPLETENESS, NOT A FILTER: the unchecked and
 *     unacknowledged counts are printed, and no figure on the page is filtered
 *     by them.
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
 * renders NOT ONE NUMBER — never the whole mill's totals as a stand-in. The
 * single option of a one-line mill is NOT auto-selected either: choosing is
 * the reader's decision, and guessing it produces figures they did not ask
 * for. Switching mill RESETS the line (keepProductionLineValid drops a line
 * that is not in the new mill's option list), so no figure of the previous
 * mill can survive on screen.
 *
 * THE MILL IS NEVER NEGOTIABLE FROM THE UI for a bound role:
 * resolvedBusinessUnitId() ignores $businessUnitId entirely for them, so
 * forcing the property (or the query string) to another mill changes nothing
 * at all — the page still shows the caller's own mill, with HTTP 200,
 * deliberately not a 403. A foreign PRODUCTION LINE and a foreign PERIOD are
 * different: both are concrete handles on another mill's data, so the line is
 * dropped back to "not chosen" and the period renders a visible refusal.
 *
 * OPERATOR HAS NO WEB ACCESS TO THIS SCREEN, and canAccess() below is its own
 * role list ON PURPOSE rather than a call to
 * DepricarpingReportService::guardAccess(): the service DOES admit Operator,
 * because the mobile report (screen-153) reuses the same four endpoints. If
 * canAccess() ever delegates to the service, this screen silently opens to
 * Operator — and the test for this scenario is what would catch it.
 *
 * The period picker auto-selects the newest period, so the page is useful on
 * first paint rather than demanding a choice before showing anything. The
 * production line is deliberately NOT auto-selected — see above.
 */
#[Layout('dashboard.laporan-depricarping')]
class LaporanDepricarping extends Component
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
     * Selama belum dipilih, layar ini tidak menampilkan satu angka pun.
     *
     * TIDAK MEMBUKA KEBOCORAN LINTAS MILL: keepProductionLineValid() hanya
     * menerima line yang ada di dalam mill yang berlaku.
     */
    #[Url(as: 'production_line_id')]
    public string $productionLineId = '';

    /** Selected reporting period; auto-filled with the newest one. */
    public string $periodId = '';

    /**
     * Daily recap table shown/hidden — collapsed content, never a data filter.
     *
     * OPEN BY DEFAULT, and a plain button rather than <details>, so the
     * visible state and the rendered DOM can never disagree: closed means
     * genuinely absent from the DOM, not merely hidden. Toggling changes NO
     * figure on the page and fires NO query.
     */
    public bool $dailyRecapOpen = true;

    /**
     * Operator is a mobile-only actor on this screen, so the component refuses
     * to mount for them — not merely an empty render. The route middleware
     * ('role:supervisor,mill_management,admin') stops them first in a browser;
     * this guard also covers the component being mounted directly, which is
     * exactly how the test scenario exercises it.
     */
    public function mount(): void
    {
        abort_unless($this->canAccess(), 403);
    }

    /**
     * Switching mill drops the period selection rather than carrying it
     * across: a period belongs to exactly one mill, so keeping it would either
     * leak or (worse) render an access-denied notice for something the user
     * did not do.
     *
     * The PRODUCTION LINE is dropped by keepProductionLineValid() in render()
     * instead of here, so that a line arriving by query string is filtered by
     * the same single rule as a line left over from a mill switch — one
     * mechanism, not two.
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
     * API endpoint returns — one row per slot, context repeated, and an empty
     * slot still emitting a row with empty cells.
     *
     * Returns null (and does nothing) when no period OR no production line is
     * selected: there is nothing to export yet, which is not an error. The
     * LINE is guarded here and not only in the blade, so a direct call to this
     * method can never fetch a file that mixes every line of the mill.
     *
     * The permission guard, the mill resolution, the line resolution and the
     * 50.000-ROW ceiling are checked EAGERLY inside the service, before any
     * byte is streamed — and that ceiling counts SLOT ROWS, not records,
     * because one daily record can produce 24.
     *
     * The selected mill is threaded through EXACTLY as render() threads it
     * into buildSummary() — resolvedBusinessUnitId(), not the raw
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

        $service = app(DepricarpingReportService::class);

        return $service->export(
            $service->authorizePeriod($this->periodId),
            $format,
            $this->resolvedBusinessUnitId(),
            $productionLineId,
        );
    }

    public function render()
    {
        $service = app(DepricarpingReportService::class);

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
            // Opsi line SELALU dibatasi mill yang berlaku, jadi line mill lain
            // tidak pernah menjadi opsi — dan keepProductionLineValid()
            // membuang sisa pilihan yang tidak ada di daftar itu, yang juga
            // cara "Admin berganti mill -> pilihan line direset" bekerja tanpa
            // hook updated* apa pun.
            $productionLineOptions = $service->productionLineOptions($businessUnitId);

            $this->keepProductionLineValid($productionLineOptions);

            $productionLineId = $this->productionLineId !== '' ? $this->productionLineId : null;

            $periods = $service->listPeriods($businessUnitId);

            $forbidden = ! $this->keepSelectionValid($periods);

            // PERIODE TETAP PER MILL — daftar periode di atas tidak bertambah
            // dimensi line sama sekali. Yang tersaring adalah DATANYA, dan
            // hanya ketika sebuah line sudah dipilih.
            if (! $forbidden && $productionLineId !== null && $this->periodId !== '') {
                $summary = $service->buildSummary(
                    $service->authorizePeriod($this->periodId),
                    $businessUnitId,
                    $productionLineId,
                );
            }
        }

        return view('livewire.dashboard.laporan-depricarping', [
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
     * belum ada pilihan yang sah.
     *
     * DepricarpingReportService::resolveProductionLine() sudah MEMULANGKAN null
     * (bukan melempar) untuk line yang bukan milik mill berlaku, sama seperti
     * BoilerRoomReportService — jadi tidak ada varian *OrNull yang perlu
     * dipanggil di sini. Di layar, "belum memilih line" adalah keadaan awal
     * yang normal dan harus merender ajakan memilih, bukan halaman galat;
     * jalur API yang menolak 422/403 ada di controller.
     */
    protected function resolvedProductionLineId(): ?string
    {
        $businessUnitId = $this->resolvedBusinessUnitId();

        if ($businessUnitId === null || $this->productionLineId === '') {
            return null;
        }

        return app(DepricarpingReportService::class)
            ->resolveProductionLine($businessUnitId, $this->productionLineId);
    }

    /**
     * Membuang line yang tidak ada di dalam mill yang berlaku — line mill lain
     * lewat query string, atau sisa pilihan setelah Admin berganti mill. Jatuh
     * ke "belum memilih" (yang merender arahan memilih), bukan ke line
     * pertama: memilih line adalah keputusan pembaca laporan, dan menebaknya
     * akan menghasilkan angka yang tidak ia minta — termasuk pada mill yang
     * hanya punya SATU line, yang sengaja tetap tidak dipilih otomatis.
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
     * ITS OWN LIST, NOT DepricarpingReportService::guardAccess(). The service
     * admits Operator because the mobile report (screen-153) reuses its
     * endpoints; borrowing it here would silently open this web screen to
     * Operator. Keep the duplication: it is the thing that makes the two
     * decisions independent.
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
            $this->periodId = (string) $periods[0]['id'];

            return true;
        }

        return in_array($this->periodId, array_column($periods, 'id'), true);
    }
}
