<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProcessWaterDetail;
use App\Models\ProcessWaterRecord;
use App\Models\ProductionLine;
use App\Models\StationType;
use App\Support\ExportValue;
use App\Support\ReportPeriodDays;
use App\Support\SheetWriter;
use DateTimeInterface;
use Generator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * ProcessWaterReportService — screen-160--laporan-process-water-web /
 * usecase-166--laporan-process-water-web (Laporan Periode Process Water).
 *
 * Shared by the API controller (App\Http\Controllers\Api\
 * ProcessWaterReportController) and the Livewire component (App\Livewire\
 * Dashboard\LaporanProcessWater) — the same split the thirteen station
 * reports before it use, so the web page and the API can never disagree on a
 * figure.
 *
 * READ-ONLY BY CONSTRUCTION: every public method is a SELECT, and the
 * /api/process-water-reports prefix carries no POST/PUT/PATCH/DELETE.
 *
 * WHAT THIS STATION REPORTS: whether the water entering the boiler is fit to
 * use. NINE numeric measurement columns, grouped into FOUR titled blocks that
 * follow the TREATMENT CHAIN rather than the database column order — Aliran
 * Air (raw + clarified flow), Softener (inlet pH + outlet hardness), Dosis
 * Kimia (alum + polymer), Air Umpan Boiler (tank temperature, pH, TDS). The
 * chain order is what lets a reader who finds an unexpected boiler-feed figure
 * walk BACKWARDS to the stage before it.
 *
 * ------------------------------------------------------------------
 * WHAT MAKES THIS REPORT DIFFERENT FROM ITS THIRTEEN SIBLINGS
 * ------------------------------------------------------------------
 *  1. A FILLED ROW IS DECIDED OVER ELEVEN COLUMNS, NOT THIRTEEN, AND THIS IS
 *     THE SINGLE MOST EASILY MIS-IMPLEMENTED RULE ON THIS SCREEN. A
 *     process_water_details row carries thirteen columns, but `shift` and
 *     `inspector_id` are IDENTIFYING/CONTEXT columns, not reading columns —
 *     ProcessWaterRecordService's own docblock says so in those words — and
 *     both are EXCLUDED from the filled check. `filled` therefore reuses
 *     ProcessWaterRecordService::isRowFilled() over its ELEVEN
 *     READING_FIELDS; it is not re-derived here, because re-deriving it is
 *     how the report and the input form start disagreeing about what was
 *     recorded, and counting all thirteen is exactly what the schema makes
 *     look natural.
 *
 *     TWO OPPOSITE CONSEQUENCES THAT MUST STAY DISTINGUISHABLE:
 *       - a slot that filled ONLY a free-text column (action_taken_status /
 *         findings) DOES count as recorded, because both text columns ARE
 *         among the eleven; it adds to NO metric's denominator;
 *       - a slot that filled ONLY the two identity columns does NOT count,
 *         because neither is a reading.
 *     The first is why coverage.filled_slots can EXCEED every per-metric
 *     reading_count — stated on the screen rather than "corrected".
 *     THIS DIFFERS FROM ENGINE ROOM, which counts all twenty-seven of its
 *     columns because it has no identity columns to exclude.
 *
 *  2. THERE IS NOT ONE CATEGORICAL DISTRIBUTION BLOCK ANYWHERE IN THIS
 *     PAYLOAD, and that absence is the most important difference from the two
 *     reports built immediately before it. THIS STATION HAS NO ENUM COLUMN AT
 *     ALL. `action_taken_status` is a PLAIN FREE-TEXT STRING DESPITE ITS NAME,
 *     verified three independent ways: the web input form renders it as
 *     <input type="text"> beside `findings` (never a <select>, not one
 *     <option>), the mobile form's label reads 'Action Taken/Status' WITH A
 *     SLASH so 'status' is the second half of a compound phrase, and there are
 *     ZERO Display::OPTION_LABELS entries for it. It is published as a TEXT
 *     LIST. Repeated identical values are NOT grouped: grouping free text
 *     turns every distinct sentence into its own "value", and a list of
 *     sentences each counted once is not a distribution — it is the same list
 *     rearranged until it looks like categorical data.
 *
 *  3. THE OPTION-LABEL MAPPER IS NOT CALLED ANYWHERE IN THIS REPORT. There is no
 *     measurement-enum column here, so the status()/option() swap trap that
 *     shadows Engine Room and Effluent Plant does not apply at all. Only
 *     ExportValue::status() (the RECORD's status — Tersimpan/Tersinkron) and
 *     ExportValue::time() (HH:MM slot labels) are used. "Completing" the pair
 *     here would invent a mapping for a column that has none.
 *
 *  4. THE `shift` COLUMN IS ABSENT FROM THE PAYLOAD AND FROM THE SCREEN, BUT
 *     PRESENT IN THE EXPORT. The column exists on process_water_details, is
 *     EMPTY on every row, and is already scheduled to be replaced by a
 *     relation to the Shift master (screen-170) in the record-tagging round.
 *     Publishing an empty column that is about to change meaning is worse than
 *     not publishing it, so a per-shift report arrives WITH that tagging. The
 *     screen states the reason so the absence does not read as an oversight.
 *     The export still carries it, because the export is the RAW detail:
 *     excluding both identity columns from the filled check does NOT mean
 *     dropping them from the file.
 *
 *  5. THE PER-INSPECTOR RECAP IS PUBLISHED AND RENDERED ON SCREEN, and that is
 *     a DELIBERATE DIVERGENCE from the Storage Tank precedent, whose
 *     StorageTankReportService docblock states that its free-text columns
 *     (inspector_name included) "appear only in the export". The divergence
 *     was requested by the product owner on 2026-10-16; it is not an agent
 *     inference, and that is what separates it from a spec drifting out of
 *     step with its siblings. Storage Tank stays export-only unless asked to
 *     change.
 *     `inspector_id` is a PLAIN STRING WITH NO RELATION TO `users`, so the
 *     code is published EXACTLY AS STORED and this service never joins to
 *     users and never attempts to return a person's name — there is no mapping
 *     to guess. `not_recorded` is its OWN key (filled slots whose inspector
 *     column is empty) and is never folded into any entry, and shares use
 *     `recorded_slots` as the denominator, never expected_slots.
 *     THE RECAP EXISTS TO CHASE UNFILLED SLOTS, NOT TO GRADE PEOPLE, and the
 *     screen says so in those words: recording completeness is the subject of
 *     this screen, and an empty slot always has an owner who can be asked.
 *
 *  6. THERE IS NO DOWNTIME BLOCK: process_water_details has no such column at
 *     all. The absence is deliberate and stated on the screen, because a
 *     missing section with no explanation cannot be told apart from a section
 *     that failed to load.
 *
 *  7. THERE IS NO OUT-OF-RANGE FLAGGING OF ANY KIND, and the statement is
 *     rendered IN FULL rather than summarised. This station has NO
 *     operational-target master table (ProcessWaterRecordService's docblock
 *     records that as a deliberate scope difference), while boiler feed water
 *     pH and TDS have limits that are VERY widely known OUTSIDE this
 *     application — every boiler-water guideline carries them, and most
 *     readers arrive with those numbers already in their heads. So the
 *     temptation to flag a deviation is greatest exactly here, and a threshold
 *     derived from the period's own data would be a STATISTIC read as a BOILER
 *     SAFETY LIMIT — one that would move every time recording completeness
 *     changed. Not one key in this payload says out of range, threshold,
 *     target, violation or severity.
 *
 * ------------------------------------------------------------------
 * ALL AGGREGATION HAPPENS IN PHP — none of it in SQL
 * ------------------------------------------------------------------
 * One query plus an eager load, then everything in PHP: no AVG/MIN/MAX/SUM, no
 * selectRaw, no DB::raw, no driver-specific date function. SQL aggregate
 * behaviour over NULLABLE columns is NOT identical between SQLite (the test
 * suite) and PostgreSQL (production), and the separate-denominator and
 * null-is-not-zero rules above are exactly what gets lost in that difference.
 *
 * ------------------------------------------------------------------
 * ACCESS: OPERATOR IS ADMITTED FROM THE FIRST COMMIT
 * ------------------------------------------------------------------
 * The three DATA routes (/periods, /summary, /export) admit Operator from the
 * first commit, because screen-161--laporan-process-water-mobile is built in
 * the SAME series — the Grading / Kernel Plant / Engine Room precedent, not
 * the Weighbridge / Effluent Plant one where the web screen landed first and
 * the widening had to be done as a follow-up.
 *
 * THAT ADMISSION TOUCHES THREE PLACES AT ONCE: the role list in
 * routes/api.php, the role list in guardAccess(), AND the MILL-BOUND branch of
 * resolveBusinessUnit(). Widening the first two alone would drop Operator into
 * the Admin branch, where a client-supplied business_unit_id IS honoured — an
 * Operator could then read any mill's report. That is a cross-mill leak, not a
 * display defect.
 *
 * WHAT STAYS CLOSED: businessUnitOptions() still refuses Operator, Supervisor
 * and Mill Management with 403 (Admin only, raised HERE inside the service
 * rather than by the route middleware, precisely because this prefix's
 * middleware admits four roles for the sake of the three data routes), and the
 * WEB route /reports/process-water is NOT widened.
 *
 * ------------------------------------------------------------------
 * CROSS-MILL SECURITY IS CLOSED AT THREE DIFFERENT POINTS, on purpose
 * ------------------------------------------------------------------
 *   1. resolveBusinessUnit() IGNORES the client's business_unit_id for
 *      Operator / Supervisor / Mill Management — not validated, not compared,
 *      discarded. Probing another mill's id returns 200 with the CALLER'S OWN
 *      data, deliberately not a 403: a 403 would confirm the other mill
 *      exists.
 *   2. resolveProductionLine() REFUSES a line belonging to another mill with
 *      403, and a MISSING line with 422 — both BEFORE a single
 *      process_water_records query runs. The WEB screen calls
 *      resolveProductionLineOrNull() instead, because "no line chosen yet" is
 *      the normal opening state there and must render a prompt.
 *   3. authorizePeriod() REFUSES a period belonging to another mill with 403,
 *      AFTER the line guard, so a caller who may not be here never learns
 *      which period ids exist.
 *
 * A mill-bound account whose users.business_unit_id is NULL FAILS CLOSED with
 * 422 and the whole-mill list is never even read — see allBusinessUnits(),
 * which is public and trivial precisely so a spy can prove it was never
 * called.
 */
class ProcessWaterReportService
{
    /**
     * Export row ceiling, counted in EXPORTED LINES (= process_water_details
     * rows), not header records: one daily record carries up to 24 time-slot
     * rows, so counting headers would sail 24x past the real limit.
     *
     * Read through `static::` below so a unit test can lower it in a subclass
     * instead of seeding fifty thousand rows.
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /**
     * Jenis stasiun yang dilaporkan layar ini — kunci baris `period_stations`
     * yang statusnya dipakai di seluruh payload layar ini.
     *
     * BERTANDA HUBUNG ('process-water'), karena itulah yang disimpan
     * StationTypeEnum::ProcessWater->value dan tabel master `station_types`.
     * Kunci snake_case akan ter-compile, tampak benar, dan tidak pernah cocok
     * dengan satu baris master pun — daftar periodenya lalu kosong untuk
     * setiap mill tanpa satu galat.
     */
    protected const STATION_TYPE = StationTypeEnum::ProcessWater->value;

    /** Export formats this report understands. Anything else is 422. */
    public const SUPPORTED_FORMATS = ['csv', 'excel'];

    /**
     * THE NINE NUMERIC METRICS, in the order the four layout blocks on the
     * screen read them in — which is the ORDER WATER FLOWS THROUGH THE
     * STATION, not the order the columns sit in the database.
     *
     * NINE, NOT ELEVEN: `action_taken_status` and `findings` are TEXT columns
     * and are deliberately absent here — they are published as two separate
     * lists (see FREE_TEXT_COLUMNS). They ARE part of
     * ProcessWaterRecordService::READING_FIELDS, which is why a text-only slot
     * still counts as recorded while adding to no metric's denominator.
     *
     * `shift` and `inspector_id` are absent too, and for a different reason:
     * they are not readings at all (see the class docblock #1 and #4).
     *
     * Every one of the nine is nullable, and every one is averaged over ITS
     * OWN non-null rows — see metricsOf().
     */
    public const NUMERIC_METRICS = [
        // 1 · Aliran Air (2) — hulu rantai.
        'raw_water_flow_m3h',
        'clarified_water_flow_m3h',
        // 2 · Softener (2) — pH di MASUKAN, kesadahan di KELUARAN. Dua hal
        // berbeda di dua titik berbeda; penyebutnya tidak dipinjamkan.
        'softener_inlet_ph',
        'softener_outlet_hardness_ppm',
        // 3 · Dosis Kimia (2) — DUA SATUAN BERBEDA, dan keduanya dibiarkan
        // apa adanya: alum kg/jam, polimer g/jam. Tidak disamakan, tidak
        // dijumlahkan — 'total dosis kimia' bukan besaran yang punya arti.
        'alum_dosing_kgh',
        'polymer_dosing_gh',
        // 4 · Air Umpan Boiler (3) — hilir rantai, pertanyaan pokok stasiun
        // ini, dan tempat godaan menandai paling besar (lihat docblock #7).
        'boiler_feed_tank_temp_c',
        'boiler_feed_water_ph',
        'boiler_feed_tds_ppm',
    ];

    /**
     * Which titled block each metric belongs to. PUBLISHED ON EVERY METRIC
     * ENTRY so the blade composes its four blocks from the RESPONSE rather
     * than from a hardcoded column list — a column added by a later migration
     * then lands in the right block without editing the blade, and nine
     * hand-written cards are nine chances to lose a denominator or inherit
     * another card's.
     *
     * PURELY LAYOUT: not one figure is computed per block and there is no
     * per-block total — units differ even inside a block (see Dosis Kimia).
     * The BLOCK ORDER follows the treatment chain, so a reader tracing a
     * deviation can move backwards one stage at a time.
     *
     * @var array<string, string>
     */
    public const METRIC_GROUPS = [
        'raw_water_flow_m3h' => 'water_flow',
        'clarified_water_flow_m3h' => 'water_flow',
        'softener_inlet_ph' => 'softener',
        'softener_outlet_hardness_ppm' => 'softener',
        'alum_dosing_kgh' => 'chemical_dosing',
        'polymer_dosing_gh' => 'chemical_dosing',
        'boiler_feed_tank_temp_c' => 'boiler_feed',
        'boiler_feed_water_ph' => 'boiler_feed',
        'boiler_feed_tds_ppm' => 'boiler_feed',
    ];

    /**
     * NEVER AGGREGATED, NEVER GROUPED, NEVER MERGED — and in this station
     * NEVER TURNED INTO A DISTRIBUTION EITHER.
     *
     * The two free-text columns are published as two SEPARATE lists of
     * {date, time_slot, text}: `action_taken_status` answers "what was done",
     * `findings` answers "what was seen". A slot filling both appears in BOTH,
     * which is correct — merging them would count that slot twice inside one
     * list.
     *
     * `action_taken_status` IS IN THIS LIST DESPITE ITS NAME. It is a plain
     * string with no closed value set (three independent verifications in the
     * class docblock #2), so repeated identical contents stay repeated
     * entries: twenty slots reading 'Sudah ditangani' render as TWENTY rows,
     * never as one row counted twenty times.
     *
     * @var array<int, string>
     */
    public const FREE_TEXT_COLUMNS = [
        'action_taken_status',
        'findings',
    ];

    /**
     * THE ONLY DAILY TREND — an AVERAGE per date of raw water flow.
     *
     * Legitimate as an average because a flow is an INSTANTANEOUS quantity per
     * slot, not a cumulative meter. Raw water flow is the one charted because
     * it is the MOST UPSTREAM metric and carries the largest denominator, so
     * the shape of the chart is the least distorted by unfilled slots.
     */
    public const TREND_METRICS = [
        'raw_water_flow_m3h',
    ];

    /**
     * Export column headers — context columns first, repeated verbatim on
     * every line, then the time slot, then ALL THIRTEEN detail columns in
     * ProcessWaterRecordService::DETAIL_FIELDS order, i.e. `shift`,
     * `inspector_id`, and the eleven READING_FIELDS.
     *
     * THE TWO IDENTITY COLUMNS ARE IN THE FILE. Excluding them from the
     * filled-slot check does not mean dropping them from the export: the
     * export is the RAW detail, and a file that cannot stand in for the input
     * screen is not worth exporting.
     *
     * The thirteen labels are the LABELS OF THE DETAIL SCREEN
     * (screen-102--detail-process-water-web) and of the Data Browser export,
     * verbatim — one column, one name, across every file this product writes.
     *
     * @var array<int, string>
     */
    public const EXPORT_HEADER = [
        // Kolom konteks diulang di setiap baris, sama dengan ekspor tiga belas
        // laporan periode sebelumnya (temuan audit 2026-10-04 #8b).
        'Periode',
        'Mill',
        'Production Line',
        'Tanggal',
        'Unit Process Water',
        'Status',
        'Catatan',
        'Slot Waktu',
        // KE-13 KOLOM DETAIL, urutan DETAIL_FIELDS.
        'Shift',
        'Inspector ID',
        'Raw Water Flow (m³/h)',
        'Clarified Water Flow (m³/h)',
        'Softener Inlet pH',
        'Softener Outlet Hardness (ppm)',
        'Alum Dosing (kg/h)',
        'Polymer Dosing (g/h)',
        'Boiler Feed Tank Temp (°C)',
        'Boiler Feed Water pH',
        'Boiler Feed TDS (ppm)',
        'Action Taken/Status',
        'Findings',
    ];

    /**
     * code => name from the `station_types` master table, memoised per service
     * instance (one request / one Livewire render).
     *
     * @var array<string, string>|null
     */
    protected ?array $stationTypeNames = null;

    protected ?ProcessWaterRecordService $recordService = null;

    /**
     * slot label => its position on the canonical grid, memoised.
     *
     * @var array<string, int>|null
     */
    protected ?array $slotOrder = null;

    // ------------------------------------------------------------------
    // Access
    // ------------------------------------------------------------------

    /**
     * business_logic step 3 — which mill the caller is allowed to look at.
     *
     * Operator / Supervisor / Mill Management: ALWAYS their own
     * business_unit_id; the `business_unit_id` argument is ignored outright, so
     * probing another mill's id is a no-op that still returns the caller's own
     * data with HTTP 200 — not validated, not compared, discarded.
     *
     * Admin: the value MUST come from the caller. Missing is 422
     * VALIDATION_ERROR with errors.business_unit_id — never a silent null and
     * never an empty result set, which would read as "this mill has no data".
     *
     * A bound account whose users.business_unit_id is NULL FAILS CLOSED with
     * 422, BEFORE any repository call, so allBusinessUnits() is provably never
     * reached from this path.
     *
     * OPERATOR BELONGS IN THE MILL-BOUND BRANCH BELOW AND NOWHERE ELSE, and
     * that single line is what makes admitting Operator in guardAccess() /
     * routes/api.php safe. Widening those role lists WITHOUT this line drops
     * Operator into the Admin branch, where the client's business_unit_id IS
     * honoured: an Operator could then read ANY mill's report. That is a
     * cross-mill leak, not a display defect.
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ValidationException 422 VALIDATION_ERROR
     */
    public function resolveBusinessUnit(?string $requestedBusinessUnitId): string
    {
        $role = $this->guardAccess();

        if ($role === UserRole::Supervisor->value
            || $role === UserRole::MillManagement->value
            // Operator belongs HERE and nowhere else: the branch below treats
            // whatever reaches it as Admin and HONOURS the client's
            // business_unit_id.
            || $role === UserRole::Operator->value) {
            // Client-supplied business_unit_id is deliberately DISCARDED.
            $businessUnitId = (string) (auth()->user()->business_unit_id ?? '');

            if ($businessUnitId === '') {
                throw ValidationException::withMessages([
                    'business_unit_id' => ['Akun Anda belum terhubung ke mill. Hubungi Admin.'],
                ]);
            }

            return $businessUnitId;
        }

        // Admin — the only role not bound to one mill, and the only role that
        // can reach this point: guardAccess() admits exactly four roles and
        // the other three are handled above.
        if ($requestedBusinessUnitId === null || $requestedBusinessUnitId === '') {
            // Incomplete input, not refused access — 422, never 403.
            throw ValidationException::withMessages([
                'business_unit_id' => ['Pilih mill terlebih dahulu untuk menampilkan laporan.'],
            ]);
        }

        return $requestedBusinessUnitId;
    }

    /**
     * business_logic step 4 — mill picker options, ADMIN ONLY.
     *
     * Operator, Supervisor and Mill Management are each bound to a single mill
     * and have no picker at all, so asking for this list is a 403 rather than a
     * filtered list of one. ENFORCED HERE, INSIDE THE SERVICE, not by the
     * route middleware — PRECISELY BECAUSE this prefix's middleware admits
     * four roles for the sake of the three data routes, so a guard placed in
     * the middleware would not close this route at all.
     *
     * An empty master is a valid answer: [] with HTTP 200, never a 404.
     *
     * @return list<array{id: string, name: string}>
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     */
    public function businessUnitOptions(): array
    {
        $user = auth()->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        if ($this->roleOf($user) !== UserRole::Admin->value) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return $this->allBusinessUnits()
            ->map(fn (BusinessUnit $businessUnit) => [
                'id' => (string) $businessUnit->id,
                'name' => (string) $businessUnit->name,
            ])
            ->all();
    }

    /**
     * THE ONLY PLACE THIS SERVICE READS THE WHOLE-MILL LIST.
     *
     * Public and deliberately trivial so it can be spied on: the "fail closed"
     * rule for a bound account with no business_unit_id is only meaningful if
     * it can be PROVEN that the all-mills list was never built, and a spy that
     * records zero calls to this method is that proof.
     *
     * @return Collection<int, BusinessUnit>
     */
    public function allBusinessUnits(): Collection
    {
        return BusinessUnit::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Pemilih Production Line — opsi line DI DALAM mill yang berlaku.
     *
     * Berlaku untuk SEMUA peran, tidak seperti businessUnitOptions() yang
     * khusus Admin: production line BUKAN ikatan akun (tidak ada
     * `users.production_line_id`, dan tidak boleh ada) melainkan KONTEKS YANG
     * DIPILIH. Supervisor pun memilih line, karena satu mill di lapangan punya
     * belasan production line dengan jenis stasiun yang sama berulang di tiap
     * line.
     *
     * Daftar ini SELALU dibatasi mill yang berlaku, sehingga line mill lain
     * tidak pernah menjadi opsi — separuh pertama dari jaminan "line mill lain
     * ditolak"; separuhnya lagi ada di resolveProductionLine(), yang menutup
     * jalur properti/query string.
     *
     * @return list<array{id: string, name: string}>
     */
    public function productionLineOptions(string $businessUnitId): array
    {
        return ProductionLine::query()
            ->where('business_unit_id', $businessUnitId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ProductionLine $line) => [
                'id' => (string) $line->id,
                'name' => (string) $line->name,
            ])
            ->all();
    }

    /**
     * business_logic step 5 — the production line this report is actually read
     * on. 422 when absent, 403 when it belongs to another mill, and in BOTH
     * cases ZERO process_water_records queries have run.
     *
     * MEMILIH LINE WAJIB di layar laporan — berbeda dari Data Browser, yang
     * punya opsi "Semua Line". Alasannya menentukan: laporan menghasilkan
     * ANGKA GABUNGAN, dan sebuah total yang mencampur belasan line bukan angka
     * yang bisa ditindaklanjuti siapa pun. TIDAK ADA jalur mundur ke "seluruh
     * line mill": melebarkan cakupan sebuah laporan diam-diam karena satu
     * parameter terlupa adalah yang terburuk dari dua dunia — ia menjawab 200
     * dengan angka yang tidak diminta siapa pun.
     *
     * @throws ValidationException 422 VALIDATION_ERROR
     * @throws AuthorizationException 403 FORBIDDEN
     */
    public function resolveProductionLine(string $businessUnitId, ?string $requestedProductionLineId): string
    {
        if ($requestedProductionLineId === null || $requestedProductionLineId === '') {
            throw ValidationException::withMessages([
                'production_line_id' => ['Production Line wajib dipilih untuk menampilkan laporan.'],
            ]);
        }

        $belongsToMill = ProductionLine::query()
            ->whereKey($requestedProductionLineId)
            ->where('business_unit_id', $businessUnitId)
            ->exists();

        if (! $belongsToMill) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return $requestedProductionLineId;
    }

    /**
     * Null-returning variant for the WEB screen, where "no line chosen yet" is
     * the normal opening state and must render a prompt rather than an error
     * page. The API path keeps resolveProductionLine(), which throws, so a
     * request naming another mill's line is still refused there.
     */
    public function resolveProductionLineOrNull(string $businessUnitId, ?string $requestedProductionLineId): ?string
    {
        if ($requestedProductionLineId === null || $requestedProductionLineId === '') {
            return null;
        }

        $belongsToMill = ProductionLine::query()
            ->whereKey($requestedProductionLineId)
            ->where('business_unit_id', $businessUnitId)
            ->exists();

        return $belongsToMill ? $requestedProductionLineId : null;
    }

    /**
     * business_logic step 7 — load a period and prove the caller may read it.
     *
     * 404 when the id does not exist. 403 when it belongs to another mill and
     * the caller is mill-bound. Admin passes for any mill.
     *
     * THE ROLE GUARD RUNS BEFORE THE LOOKUP, on purpose: a role that is not
     * admitted at all must not be able to learn whether a period id exists by
     * comparing a 403 against a 404.
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ModelNotFoundException 404 NOT_FOUND
     */
    public function authorizePeriod(string $periodId): Period
    {
        $this->guardAccess();

        /** @var Period $period */
        $period = Period::query()->with('businessUnit')->findOrFail($periodId);

        return $this->authorizePeriodModel($period);
    }

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * business_logic step 6 — the periods selectable for this mill.
     *
     * A period covers Process Water when it HAS a `period_stations` row for
     * station_type 'process-water'. Newest first. An empty array is a valid
     * answer — a mill with no such period yet gets [] with HTTP 200 and a UI
     * hint pointing at Kelola Periode Pelaporan, never a 404.
     *
     * STATUS NEVER FILTERS THIS LIST: closed periods are listed and remain
     * fully readable and exportable. The period lock governs WRITING data, not
     * reading a report.
     *
     * `production_line_id` is deliberately NOT a parameter: periods belong to a
     * MILL, not to a line. What a line filters is the DATA, not the period
     * list.
     *
     * @return list<array{id: string, name: string, start_date: string, end_date: string, status: string, station_type: string, station_type_label: string}>
     */
    public function listPeriods(?string $businessUnitId = null): array
    {
        $businessUnitId = $this->resolveBusinessUnit($businessUnitId);

        return Period::query()
            ->where('business_unit_id', $businessUnitId)
            ->whereHas('stations', fn (Builder $query) => $query->where('station_type', self::STATION_TYPE))
            // Dimuat terbatas pada jenis stasiun ini supaya statusValue() tidak
            // menembak satu kueri per periode (N+1).
            ->with(['stations' => fn ($query) => $query->where('station_type', self::STATION_TYPE)])
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get()
            ->map(fn (Period $period) => $this->periodOption($period))
            ->all();
    }

    /**
     * business_logic steps 1-28 — every figure on the screen for one period on
     * one production line: the period header, recording coverage FIRST, the
     * nine metrics each with its own denominator and its own `group`, the TWO
     * separate free-text lists, the per-inspector recap, the one daily trend
     * (an AVERAGE per date), the per-unit recap, the daily recap, and the
     * period totals.
     *
     * WHAT IS NOT IN HERE, and the absences are asserted: no categorical
     * distribution of any kind (this station has no enum column, and the
     * misleadingly named `action_taken_status` is free text — see the class
     * docblock #2); no downtime; no threshold/target of any kind; and no
     * per-shift dimension (see the class docblock #4).
     *
     * Guard order is deliberate: role (403) -> mill (422) -> production line
     * (422 / 403) -> period id (422) -> the period itself (404 / 403). A caller
     * who may not be here at all never learns which period ids exist, and an
     * incomplete request never turns into a 404/403 about a period it was never
     * entitled to ask about.
     *
     * Membership is decided by process_water_records.date — the date the
     * readings belong to — INCLUSIVE on both bounds, through whereDate even
     * though the column is a DATE rather than a timestamp, so that no station
     * report spells period membership differently from another.
     *
     * @param  Period|string|null  $period  model or id (both accepted so callers
     *                                      that already authorised the period do
     *                                      not have to re-read it)
     *
     * @throws ValidationException 422 VALIDATION_ERROR
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ModelNotFoundException 404 NOT_FOUND
     */
    public function buildSummary(
        Period|string|null $period = null,
        ?string $requestedBusinessUnitId = null,
        ?string $productionLineId = null,
    ): array {
        $this->guardAccess();
        $businessUnitId = $this->resolveBusinessUnit($requestedBusinessUnitId);
        // 422 when absent, 403 when it belongs to another mill — and in BOTH
        // cases zero process_water_records queries have run by this point. The
        // line guard runs BEFORE the period guard on purpose.
        $productionLineId = $this->resolveProductionLine($businessUnitId, $productionLineId);

        $period = $this->requirePeriod($period);

        // ONE query for the whole period+line, details eager-loaded. Everything
        // below is PHP.
        $records = $this->recordsFor($period, $productionLineId);
        $rows = $this->rowsOf($records);
        // "Filled rows" are the only ones that count, and FILLED IS DECIDED
        // OVER ELEVEN COLUMNS, NOT THIRTEEN — see rowOf().
        $filledRows = $rows->filter(fn ($row) => $row->filled)->values();

        $dates = $this->datesOf($records);
        $units = $this->unitsOf($records);

        $daysInPeriod = ReportPeriodDays::inPeriod($period);
        // Hari ber-record, BUKAN hari ber-slot-terisi: sebuah hari yang punya
        // record tetapi seluruh barisnya kosong tetap sebuah hari yang dicatat
        // orang.
        $daysWithRecords = $dates->count();
        $filledSlots = $filledRows->count();
        $unitCount = $units->count();
        // 24 datang dari kisi slot kanonik yang dipakai layar input itu sendiri
        // (ProcessWaterRecordService::canonicalTimeSlots()), bukan angka yang
        // dikarang di sini — satu definisi, satu jawaban.
        $slotsPerUnitPerDay = count(ProcessWaterRecordService::canonicalTimeSlots());
        // PENYEBUT BERHENTI DI HARI INI untuk periode yang masih berjalan, dan
        // 0 bila periode belum mulai — hari yang belum terjadi tidak mungkin
        // tercatat. Lihat App\Support\ReportPeriodDays.
        $daysCounted = ReportPeriodDays::counted($period);
        $expectedSlots = $unitCount * $daysCounted * $slotsPerUnitPerDay;

        return [
            'period' => [
                'id' => (string) $period->id,
                'name' => (string) $period->name,
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'status' => $this->statusValue($period),
                'business_unit_name' => (string) ($period->businessUnit?->name ?? ''),
            ],
            'business_unit' => [
                'id' => (string) $period->business_unit_id,
                'name' => (string) ($period->businessUnit?->name ?? ''),
            ],
            'production_line' => $this->productionLineInfo($productionLineId, (string) $period->business_unit_id),
            // has_data distinguishes "there is nothing to report" from "the
            // figures happen to be zero". The screen uses it to REFUSE to draw
            // an empty chart, which would read as a measured flat line.
            'has_data' => $filledSlots > 0,
            // RECORDING COVERAGE IS PART OF THE REPORT, NOT METADATA. A period
            // filled to a fifth still produces tidy-looking averages, and the
            // reader must see that before trusting them — which is why the
            // screen renders this block ABOVE every other figure.
            'coverage' => [
                'filled_slots' => $filledSlots,
                'expected_slots' => $expectedSlots,
                // 0.0 ketika expected_slots 0 — tanpa DivisionByZeroError, dan
                // layar menampilkan '—' dengan 'belum ada slot yang
                // diharapkan' pada keadaan itu, bukan '0,0%'.
                'coverage_percent' => $expectedSlots === 0
                    ? 0.0
                    : round(100 * $filledSlots / $expectedSlots, 1),
                'process_water_unit_count' => $unitCount,
                'slots_per_unit_per_day' => $slotsPerUnitPerDay,
                'days_in_period' => $daysInPeriod,
                'days_counted' => $daysCounted,
                'period_running' => ReportPeriodDays::isRunning($period),
                // ATURAN SEBELAS-BUKAN-TIGA-BELAS, diterbitkan bersama
                // angkanya supaya pembaca payload tidak perlu menebak mengapa
                // filled_slots bisa melebihi setiap reading_count.
                //
                // Teks ini sengaja TIDAK MENYEBUT NAMA KOLOM mana pun: layar
                // ini diasersi atas KETIADAAN sejumlah kunci dan sejumlah nama
                // kolom di dalam payload, dan menuliskan nama kolom di dalam
                // sebuah keterangan adalah cara termudah memerahkan asersi
                // yang sebenarnya benar. Nama kolomnya ada di LAYAR, tempat
                // keterangan itu memang harus terbaca manusia, dan di docblock
                // kelas ini.
                '_note_filled_rule' => 'Slot terisi dihitung atas SEBELAS kolom '
                    .'ProcessWaterRecordService::READING_FIELDS, BUKAN ketiga belas kolom baris detail: '
                    .'kedua kolom identitas (giliran kerja dan kode inspector) DIKECUALIKAN dari '
                    .'isRowFilled(), dan pengecualian itu dinyatakan eksplisit di docblock '
                    .'ProcessWaterRecordService, bukan ditebak di sini. Karena kesebelas kolom itu '
                    .'mencakup kedua kolom teks bebas, filled_slots dapat MELEBIHI reading_count '
                    .'setiap metrik ukur — dan selisih itu benar, bukan ketidaksesuaian yang perlu '
                    .'dikoreksi.',
            ],
            // THE HEART OF THIS REPORT: nine metrics, nine denominators, each
            // tagged with the block it belongs to. EXACTLY NINE KEYS — the two
            // text columns are not metrics and are not in here.
            'metrics' => $this->metricsOf($filledRows),
            // DUA DAFTAR TERPISAH, dan NOL sebaran kategoris. Satu slot yang
            // mengisi keduanya muncul di kedua daftar, dan itu benar.
            'text_entries' => [
                'action_taken_status' => $this->textEntriesOf($filledRows, 'action_taken_status'),
                'findings' => $this->textEntriesOf($filledRows, 'findings'),
            ],
            // Kolom yang tidak dimiliki stasiun bergrid lain, dan diterbitkan
            // DI LAYAR atas permintaan pemilik produk 2026-10-16 —
            // penyimpangan sadar dari preseden Storage Tank.
            'inspector_recap' => $this->inspectorRecapOf($filledRows),
            'daily_trend' => $this->dailyTrendOf($dates),
            'by_unit' => $this->byUnitOf($units),
            'daily' => $this->dailyOf($dates),
            'daily_total' => [
                // Satu definisi, satu jawaban: jumlah slot per hari dan jumlah
                // slot per unit keduanya menjumlah ke angka ini, dan ke
                // coverage.filled_slots.
                'reading_rows' => $filledSlots,
            ],
            'total' => [
                'days_with_records' => $daysWithRecords,
                'reading_rows' => $filledSlots,
            ],
            // KETIADAAN YANG DISENGAJA, dinyatakan supaya tidak terbaca sebagai
            // bagian yang gagal dimuat.
            //
            // Sama seperti _note_filled_rule di atas, teks ini sengaja tidak
            // mengeja nama kunci maupun nama kolom yang justru harus TIDAK ADA
            // di payload ini. Penjelasan lengkapnya ada di layar dan di
            // docblock kelas ini.
            '_note_absences' => 'TIDAK ADA satu pun blok sebaran kategoris di payload ini, dan '
                .'ketiadaan itu disengaja: stasiun ini tidak punya satu pun kolom berenum, dan kolom '
                .'catatan tindakan yang namanya menyesatkan sebenarnya teks bebas — diverifikasi dari '
                .'formulir web (dirender sebagai input teks bersebelahan dengan kolom temuan), dari '
                .'label formulir mobile yang memakai garis miring sehingga kata kedua adalah paruh '
                .'sebuah frasa, dan dari nol entri di peta label pilihan. Ia karena itu diterbitkan '
                .'sebagai DAFTAR TEKS pada text_entries, dan isi yang berulang sama persis TIDAK '
                .'dikelompokkan. TIDAK ADA blok lama berhenti — baris detail stasiun ini tidak punya '
                .'satu pun kolomnya. TIDAK ADA acuan batas maupun penandaan nilai di luar batas dalam '
                .'bentuk apa pun — stasiun ini tidak punya tabel master acuan operasional, dan itu '
                .'berlaku MESKI pH dan padatan terlarut air umpan boiler punya batas yang dikenal '
                .'sangat luas di luar aplikasi ini: ambang yang diturunkan dari data periode itu '
                .'sendiri hanya turunan statistik, sementara pembaca akan membacanya sebagai batas '
                .'keselamatan boiler. DAN TIDAK ADA dimensi giliran kerja — kolomnya kosong di seluruh '
                .'baris dan sudah dijadwalkan diganti relasi ke master Shift pada putaran penandaan '
                .'record, sehingga laporan per giliran kerja datang bersama penandaan itu. Kolom itu '
                .'TETAP ADA di ekspor, karena ekspor adalah rincian mentah.',
        ];
    }

    /**
     * Repo-convention alias of buildSummary(), so this service reads the same
     * way as its thirteen siblings at the call sites. One implementation, two
     * names — never two implementations.
     */
    public function summary(
        Period|string|null $period = null,
        ?string $requestedBusinessUnitId = null,
        ?string $productionLineId = null,
    ): array {
        return $this->buildSummary($period, $requestedBusinessUnitId, $productionLineId);
    }

    /**
     * business_logic step 29 — ONE EXPORTED LINE PER TIME SLOT
     * (process_water_details row), with the period / mill / production line /
     * date / unit / status / note context columns repeated verbatim on every
     * line so the file can be pivoted straight in a spreadsheet.
     *
     * ALL THIRTEEN detail columns are emitted, the two IDENTITY columns
     * included: excluding them from the filled-slot check does not mean
     * dropping them from the file, because the export is the raw detail. An
     * empty cell stays EMPTY: never 0, never '-'.
     *
     * THE OPTION-LABEL MAPPER IS NOT CALLED HERE AND IS NOT CALLED ANYWHERE IN
     * THIS SERVICE. There is no measurement-enum column on this station, so the
     * only two mappers in play are ExportValue::status() for the RECORD's
     * status context column and ExportValue::time() for the slot label.
     *
     * THE GUARD, THE MILL/LINE RESOLUTION AND THE ROW-LIMIT CHECK RUN EAGERLY,
     * at call time, while the rows themselves are yielded lazily from a chunked
     * query. Making this method itself a generator would defer the 403/422
     * until the first iteration, so a refused export would look like a
     * successful call that produced nothing.
     *
     * THE CEILING COUNTS SLOT ROWS, NOT RECORDS: one record carries up to 24 of
     * them, so the limit is reached at a record count 24x smaller.
     *
     * @return Generator<int, array<int, string|float|null>>
     *
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ModelNotFoundException 404 NOT_FOUND
     * @throws ValidationException 422 VALIDATION_ERROR
     * @throws ExportFailedException 422 EXPORT_FAILED
     */
    public function buildExportRows(
        Period|string|null $period = null,
        ?string $requestedBusinessUnitId = null,
        ?string $productionLineId = null,
    ): Generator {
        $this->guardAccess();
        $businessUnitId = $this->resolveBusinessUnit($requestedBusinessUnitId);
        $productionLineId = $this->resolveProductionLine($businessUnitId, $productionLineId);

        $period = $this->requirePeriod($period);

        $recordQuery = $this->recordQueryFor($period, $productionLineId);

        // Counted over ROWS THAT WILL BE EMITTED — detail rows, not header
        // records. A COUNT(*) over a non-nullable key is not an aggregate over
        // a nullable column, so it is not the SQLite/PostgreSQL trap the
        // aggregation rule above guards against.
        $detailRowCount = ProcessWaterDetail::query()
            ->whereIn('process_water_record_id', (clone $recordQuery)->select('process_water_records.id'))
            ->count();

        // Strictly greater than: exactly EXPORT_ROW_LIMIT rows still export.
        // `static::`, not `self::` — the ceiling is a tunable, and a unit test
        // lowers it in a subclass to prove the check counts EMITTED ROWS rather
        // than records.
        if ($detailRowCount > static::EXPORT_ROW_LIMIT) {
            throw new ExportFailedException;
        }

        $exportContext = [
            (string) $period->name,
            (string) ($period->businessUnit?->name ?? ''),
            (string) ($this->productionLineInfo($productionLineId, (string) $period->business_unit_id)['name'] ?? ''),
        ];

        return $this->streamExportRows($recordQuery, $exportContext);
    }

    /**
     * business_logic step 29 — the streamed file around buildExportRows().
     *
     * @throws ValidationException 422 VALIDATION_ERROR (unsupported format)
     * @throws ExportFailedException 422 EXPORT_FAILED
     */
    public function export(
        Period|string|null $period = null,
        string $format = 'csv',
        ?string $requestedBusinessUnitId = null,
        ?string $productionLineId = null,
    ): StreamedResponse {
        $this->guardAccess();

        if (! in_array($format, self::SUPPORTED_FORMATS, true)) {
            throw ValidationException::withMessages([
                'format' => ['Format ekspor harus csv atau excel.'],
            ]);
        }

        $resolvedPeriod = $this->requirePeriod($period);

        // Runs the guard + the mill resolution + the line resolution + the
        // row-limit check NOW, before a single byte of the response is
        // committed — a refused export must never begin streaming.
        $rows = $this->buildExportRows($resolvedPeriod, $requestedBusinessUnitId, $productionLineId);

        try {
            [$contentType, $filename] = $this->fileMetaFor($format, $resolvedPeriod);

            return response()->streamDownload(function () use ($rows, $format) {
                // A failure WHILE writing is still EXPORT_FAILED (422), not a
                // half-written file reported as a success.
                try {
                    $handle = SheetWriter::open($format);
                    $handle->row(self::EXPORT_HEADER);

                    foreach ($rows as $row) {
                        $handle->row($row);
                    }

                    $handle->close();
                } catch (ExportFailedException $e) {
                    throw $e;
                } catch (Throwable $e) {
                    throw new ExportFailedException;
                }
            }, $filename, [
                'Content-Type' => $contentType,
            ]);
        } catch (ExportFailedException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ExportFailedException;
        }
    }

    // ------------------------------------------------------------------
    // Aggregation — ALL OF IT IN PHP, none of it in SQL
    //
    // Deliberate, and the reason is decisive: SQL aggregate behaviour over
    // NULLABLE columns is NOT the same in SQLite (the test suite) and
    // PostgreSQL (production), and the separate-denominator and
    // null-is-not-zero rules are exactly what gets lost in that difference. No
    // avg()/sum()/groupBy()/selectRaw is issued at the SQL layer anywhere
    // below; the queries only ever select raw rows, filter them, and order
    // them. The row set is bounded by one reporting period; the export path —
    // the only unbounded one — streams in chunks instead.
    // ------------------------------------------------------------------

    /**
     * Every Process Water record inside the period+line, flattened to plain
     * objects with their time-slot detail rows attached, ascending by
     * time_slot.
     *
     * ONE DATE CAN HAVE SEVERAL RECORDS — one per Process Water unit, because
     * process_water_id is a STRING column on the HEADER, not a foreign key (the
     * same shape as engine_room_id and effluent_plant_id). The per-date
     * aggregation merges them; the per-unit aggregation splits them apart
     * again.
     *
     * @return Collection<int, object>
     */
    protected function recordsFor(Period $period, ?string $productionLineId = null): Collection
    {
        return $this->recordQueryFor($period, $productionLineId)
            // time_slot is a TIME column: ordering uses the TIME value as it
            // stands, never cast to an integer hour.
            ->with(['processWaterDetails' => fn ($query) => $query->orderBy('time_slot')])
            ->orderBy('process_water_records.date')
            ->orderBy('process_water_records.process_water_id')
            ->get()
            ->map(fn (ProcessWaterRecord $record) => (object) [
                'date' => $this->dateStringOf($record->date),
                'process_water_id' => (string) ($record->process_water_id ?? ''),
                'status' => $this->recordStatusValue($record),
                'note' => $record->note,
                'rows' => $record->processWaterDetails
                    ->map(fn (ProcessWaterDetail $detail) => $this->rowOf($detail, $record))
                    ->values(),
            ])
            ->values();
    }

    /**
     * One detail row, reduced to exactly what the aggregation needs.
     *
     * `filled` REUSES ProcessWaterRecordService::isRowFilled() over its ELEVEN
     * READING_FIELDS — the definition the input screens already enforce, and
     * the reason both were opened to `public`. IT IS ELEVEN, NOT THIRTEEN:
     * `shift` and `inspector_id` are identity columns and the record service's
     * own docblock excludes them in those words. Writing a second "is this row
     * filled?" rule here, or re-listing the columns in this file, is how the
     * report and the form start disagreeing about what was recorded — and a
     * re-derivation would almost certainly count all thirteen, because that is
     * what the schema makes look natural.
     *
     * Note which way each of the two consequences falls: a slot that only wrote
     * `findings` or `action_taken_status` IS filled (both text columns are
     * among the eleven) and adds to no metric's denominator; a slot that only
     * wrote `shift` and/or `inspector_id` is NOT.
     *
     * `slot_order` is the slot's POSITION ON THE CANONICAL GRID — kept for
     * deterministic ordering inside a bucket. Not a lexical hour comparison:
     * the grid starts at 07:00 and ends at 06:00 the NEXT day, so sorting the
     * labels as strings makes 06:00 the EARLIEST slot of the day when it is in
     * fact the LAST.
     */
    protected function rowOf(ProcessWaterDetail $detail, ProcessWaterRecord $record): object
    {
        $attributes = $detail->only(ProcessWaterRecordService::READING_FIELDS);

        $values = [];

        foreach (self::NUMERIC_METRICS as $metric) {
            $raw = $detail->{$metric};
            // NULL STAYS NULL. Never coerced to 0.0 — a missing reading must
            // not be able to drag an average down. '' is normalised to null
            // too: an empty string reaching a numeric column would cast to 0.0.
            $values[$metric] = $raw === null || $raw === '' ? null : (float) $raw;
        }

        $texts = [];

        foreach (self::FREE_TEXT_COLUMNS as $column) {
            $text = $detail->{$column} === null ? null : trim((string) $detail->{$column});
            // Edge whitespace only. A note of '   ' becomes null rather than a
            // blank entry in the list.
            $texts[$column] = $text === '' ? null : $text;
        }

        // KODE INSPECTOR APA ADANYA. Tidak ada relasi ke tabel users, jadi
        // tidak ada nama yang dapat dipulangkan dan tidak ada pemetaan untuk
        // ditebak (keputusan pemilik produk 2026-10-16). Yang dilakukan di sini
        // hanya memangkas spasi tepi, supaya ' INS-01 ' dan 'INS-01' tidak
        // menjadi dua baris rekap.
        $inspectorId = $detail->inspector_id === null ? null : trim((string) $detail->inspector_id);

        $timeSlot = (string) $detail->time_slot;

        return (object) [
            'date' => $this->dateStringOf($record->date),
            'process_water_id' => (string) ($record->process_water_id ?? ''),
            'time_slot' => $timeSlot,
            'time_slot_label' => ExportValue::time($timeSlot) ?? $timeSlot,
            'slot_order' => $this->slotOrderOf($timeSlot),
            'filled' => $this->recordService()->isRowFilled($attributes),
            'values' => $values,
            'texts' => $texts,
            'inspector_id' => $inspectorId === '' ? null : $inspectorId,
        ];
    }

    /**
     * Every detail row in the period, flattened across records.
     *
     * @param  Collection<int, object>  $records
     * @return Collection<int, object>
     */
    protected function rowsOf(Collection $records): Collection
    {
        return $records->flatMap(fn ($record) => $record->rows)->values();
    }

    /**
     * NINE ENTRIES, NINE DENOMINATORS, each tagged with the titled block it
     * belongs to.
     *
     * A metric is averaged over ONLY the rows where that column is non-null,
     * and reports how many rows that was. There is deliberately NO shared
     * reading_count anywhere in the payload — a single shared denominator would
     * deflate every rarely-filled metric while still producing a number that
     * looks entirely reasonable, and it would be wrong for at least eight of
     * the nine.
     *
     * A metric never filled in the whole period returns null/null/null with
     * reading_count 0 — NOT 0/0/0, which reads as "we measured it and it was
     * zero".
     *
     * EXACTLY NINE KEYS. `action_taken_status` and `findings` are not here;
     * they are text and are published as lists.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array<string, array{min: float|null, avg: float|null, max: float|null, reading_count: int, group: string}>
     */
    protected function metricsOf(Collection $filledRows): array
    {
        $metrics = [];

        foreach (self::NUMERIC_METRICS as $metric) {
            $metrics[$metric] = $this->statsOf($this->valuesOf($filledRows, $metric))
                + ['group' => self::METRIC_GROUPS[$metric]];
        }

        return $metrics;
    }

    /**
     * One metric's own non-null values inside one bucket.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array<int, float>
     */
    protected function valuesOf(Collection $filledRows, string $metric): array
    {
        return $filledRows
            ->map(fn ($row) => $row->values[$metric])
            ->filter(fn ($value) => $value !== null)
            ->values()
            ->all();
    }

    /**
     * min / avg / max / reading_count over one metric's own values.
     *
     * Empty input is null/null/null/0, never 0/0/0/0. ROUNDING HAPPENS HERE AND
     * NOWHERE ELSE, so the blade, the API and the export can never round
     * differently.
     *
     * @param  array<int, float>  $values
     * @return array{min: float|null, avg: float|null, max: float|null, reading_count: int}
     */
    protected function statsOf(array $values): array
    {
        if ($values === []) {
            return ['min' => null, 'avg' => null, 'max' => null, 'reading_count' => 0];
        }

        return [
            'min' => round(min($values), 3),
            'avg' => round(array_sum($values) / count($values), 3),
            'max' => round(max($values), 3),
            'reading_count' => count($values),
        ];
    }

    /**
     * Per-metric averages inside one bucket (a date or a unit), each with ITS
     * OWN reading_count inside that bucket.
     *
     * avg is null — never 0 — when the bucket holds no value at all for that
     * metric. NINE entries, which is what makes the daily recap nine average
     * columns wide.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array<string, array{avg: float|null, reading_count: int}>
     */
    protected function averagesOf(Collection $filledRows): array
    {
        $averages = [];

        foreach (self::NUMERIC_METRICS as $metric) {
            $values = $this->valuesOf($filledRows, $metric);

            $averages[$metric] = [
                'avg' => $values === []
                    ? null
                    : round(array_sum($values) / count($values), 3),
                'reading_count' => count($values),
            ];
        }

        return $averages;
    }

    /**
     * One free-text column published as a list of {date, time_slot, text}, only
     * for the slots where that column is not empty.
     *
     * CALLED TWICE, AND THE TWO RESULTS ARE NEVER FOLDED INTO A THIRD. A slot
     * that filled both columns appears in BOTH lists, which is correct: merging
     * them would count that slot twice inside one list, and the two answer
     * different questions — what was done, versus what was seen. Neither is
     * ever averaged or charted.
     *
     * AND NEITHER IS EVER GROUPED. One entry PER SLOT, in slot order, even when
     * twenty slots carry the identical sentence: grouping free text makes every
     * distinct sentence its own "value", and a list of sentences each counted
     * once is not a distribution. This is the whole reason
     * `action_taken_status` — whose NAME says 'status' — comes through this
     * method rather than through a distribution builder, and there is no
     * distribution builder in this class at all.
     *
     * @param  Collection<int, object>  $filledRows
     * @return list<array{date: string, time_slot: string, text: string}>
     */
    protected function textEntriesOf(Collection $filledRows, string $column): array
    {
        return $filledRows
            ->filter(fn ($row) => ($row->texts[$column] ?? null) !== null)
            ->map(fn ($row) => [
                'date' => $row->date,
                'time_slot' => $row->time_slot_label,
                'text' => (string) $row->texts[$column],
            ])
            ->values()
            ->all();
    }

    /**
     * THE PER-INSPECTOR RECAP — how many FILLED SLOTS each `inspector_id`
     * wrote across the period, with the invariant that proves it:
     *
     *   sum(entries.filled_slots) + not_recorded === recorded_slots === filled rows
     *
     * IT EXISTS TO CHASE UNFILLED SLOTS, NOT TO GRADE PEOPLE. Recording
     * completeness is the subject of this screen, and an empty slot always has
     * an owner who can be asked; that is the whole use. The figure is NOT a
     * performance number — it knows nothing about assignment length, how many
     * units someone covered, days off, or slots that were never theirs — so
     * this method does not rank, does not sort by volume, and does not flag
     * anybody. Entries come back in CODE ORDER, deliberately, so the table
     * cannot be read as a league table.
     *
     * `not_recorded` IS ITS OWN KEY and is never folded into any entry: filled
     * slots whose inspector column is empty are a different fact from a slot
     * attributed to someone. Those slots STILL COUNT in coverage, because
     * `inspector_id` is an identity column and takes no part in deciding
     * whether a slot is filled.
     *
     * `recorded_slots` is the denominator the screen uses for each share —
     * never expected_slots, which would mix untouched slots into the share of a
     * slot that WAS written.
     *
     * THE CODE IS PUBLISHED EXACTLY AS STORED. `inspector_id` is a plain string
     * with NO relation to `users`, so there is no name to return and no mapping
     * to guess (product owner decision 2026-10-16); this method issues no join
     * and no lookup of any kind. Note the formats are not even uniform in the
     * field ('1180734' beside 'OP-07') — one more reason not to invent a
     * mapping.
     *
     * PUBLISHING THIS ON SCREEN IS A DELIBERATE DIVERGENCE from Storage Tank,
     * which keeps such columns to the export; see the class docblock #5.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array{entries: list<array{inspector_id: string, filled_slots: int}>, not_recorded: int, recorded_slots: int}
     */
    protected function inspectorRecapOf(Collection $filledRows): array
    {
        $counts = [];
        $notRecorded = 0;

        foreach ($filledRows as $row) {
            $inspectorId = $row->inspector_id;

            if ($inspectorId === null) {
                $notRecorded++;

                continue;
            }

            $counts[$inspectorId] = ($counts[$inspectorId] ?? 0) + 1;
        }

        ksort($counts, SORT_NATURAL);

        $entries = [];

        foreach ($counts as $inspectorId => $count) {
            $entries[] = [
                'inspector_id' => (string) $inspectorId,
                'filled_slots' => $count,
            ];
        }

        return [
            // Kosong adalah jawaban yang sah dan TETAP DITERBITKAN: seluruh
            // slot terisi tanpa kode inspector menghasilkan entries kosong
            // dengan not_recorded memuat semuanya, sehingga layar dapat
            // MENYATAKAN keadaan itu alih-alih merender rekap kosong tanpa
            // penjelasan.
            'entries' => $entries,
            'not_recorded' => $notRecorded,
            'recorded_slots' => $filledRows->count(),
        ];
    }

    /**
     * One aggregate per DATE that has at least one record, ascending, merging
     * every Process Water unit that ran on that date.
     *
     * A date whose rows are all empty for a given metric yields null in that
     * column but STILL COUNTS as a date with records — dropping it would make
     * the period look better recorded than it was. A date with no record at all
     * gets no entry: a padded zero row would read as "we measured nothing" when
     * the mill simply did not run.
     *
     * @param  Collection<int, object>  $records
     * @return Collection<int, object>
     */
    protected function datesOf(Collection $records): Collection
    {
        return $records
            ->groupBy('date')
            ->sortKeys()
            ->map(function (Collection $group, string $date) {
                $rows = $group->flatMap(fn ($record) => $record->rows)->values();
                $filled = $rows->filter(fn ($row) => $row->filled)->values();

                return (object) [
                    'date' => $date,
                    'reading_rows' => $filled->count(),
                    'averages' => $this->averagesOf($filled),
                ];
            })
            ->values();
    }

    /**
     * One aggregate per PROCESS WATER UNIT (process_water_id) over every record
     * in the period.
     *
     * process_water_id is a STRING column on the header, so one line can carry
     * several units. A unit that has a record but not one filled reading STILL
     * APPEARS, with reading_rows 0 and null averages, and still counts towards
     * process_water_unit_count so expected_slots stays correct. Dropping it
     * would hide exactly the unit that was never written down.
     *
     * @param  Collection<int, object>  $records
     * @return Collection<int, object>
     */
    protected function unitsOf(Collection $records): Collection
    {
        return $records
            ->groupBy('process_water_id')
            ->sortKeys()
            ->map(function (Collection $group, string $processWaterId) {
                $rows = $group->flatMap(fn ($record) => $record->rows)->values();
                $filled = $rows->filter(fn ($row) => $row->filled)->values();
                $dates = $filled->pluck('date')->unique()->values();

                return (object) [
                    'process_water_id' => $processWaterId,
                    'reading_rows' => $filled->count(),
                    'days_with_rows' => $dates->count(),
                    'metrics' => $this->metricsOf($filled),
                ];
            })
            ->values();
    }

    /**
     * THE ONE DAILY TREND — average RAW WATER FLOW per date, each point
     * carrying its own reading_count.
     *
     * An AVERAGE, and legitimately so: a flow is an instantaneous quantity per
     * slot, not a cumulative meter. Raw water flow is the charted one because
     * it is the most upstream metric and carries the largest denominator, so the
     * chart's shape is the least distorted by unfilled slots.
     *
     * A date whose rows never filled the charted column yields avg null, NOT 0:
     * a zero point would claim a measurement that was never made, and the date
     * still counts as a date with records.
     *
     * @param  Collection<int, object>  $dates
     * @return array<string, list<array{date: string, avg: float|null, reading_count: int}>>
     */
    protected function dailyTrendOf(Collection $dates): array
    {
        $trend = [];

        foreach (self::TREND_METRICS as $metric) {
            $trend[$metric] = $dates
                ->filter(fn ($row) => $row->reading_rows > 0)
                ->map(fn ($row) => [
                    'date' => $row->date,
                    'avg' => $row->averages[$metric]['avg'],
                    'reading_count' => $row->averages[$metric]['reading_count'],
                ])
                ->values()
                ->all();
        }

        return $trend;
    }

    /**
     * The daily recap table — ONE ROW PER DATE THAT HAS AT LEAST ONE FILLED ROW
     * (business_logic step 28). A date with records but no filled row at all
     * still counts in total.days_with_records; it simply has no figures to
     * print.
     *
     * NINE average columns per row, each with its own reading_count — narrower
     * than Engine Room's twenty-three, but still wide enough that the table
     * scrolls horizontally INSIDE ITS OWN CARD while the page never does.
     *
     * @param  Collection<int, object>  $dates
     * @return list<array{date: string, reading_rows: int, metrics_avg: array<string, array{avg: float|null, reading_count: int}>}>
     */
    protected function dailyOf(Collection $dates): array
    {
        return $dates
            ->filter(fn ($row) => $row->reading_rows > 0)
            ->map(fn ($row) => [
                'date' => $row->date,
                'reading_rows' => $row->reading_rows,
                'metrics_avg' => $row->averages,
            ])
            ->values()
            ->all();
    }

    /**
     * The per-unit recap table. `metrics` carries the SAME SHAPE as the
     * period-level `metrics`, scoped to this unit — nine entries, each with its
     * own denominator and its own `group`.
     *
     * `reading_count` is published BESIDE `reading_rows` with the same value:
     * the two names are used interchangeably across the thirteen sibling
     * reports, and publishing both keeps every caller reading the key it
     * already expects.
     *
     * @param  Collection<int, object>  $units
     * @return list<array<string, mixed>>
     */
    protected function byUnitOf(Collection $units): array
    {
        return $units
            ->map(fn ($row) => [
                'process_water_id' => $row->process_water_id,
                'reading_rows' => $row->reading_rows,
                'reading_count' => $row->reading_rows,
                'days_with_rows' => $row->days_with_rows,
                'metrics' => $row->metrics,
            ])
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // Export streaming
    // ------------------------------------------------------------------

    /**
     * The lazy half of buildExportRows(): one array per time-slot row, pulled
     * in chunks so a long period never materialises as one collection.
     *
     * Ordered by date, then by unit, then by time_slot — and time_slot is
     * compared as the TIME value it is, never as an integer hour.
     *
     * THE 'Status' CONTEXT COLUMN IS THE RECORD'S STATUS and uses
     * ExportValue::status() (Tersimpan / Tersinkron). THERE IS NO SECOND
     * MAPPER: this station has no measurement-enum column, so
     * the option-label mapper is not called here and is not called anywhere in
     * this service. The status()/option() swap that shadows Engine Room and
     * Effluent Plant cannot happen on this screen, and "completing" the pair
     * would invent labels for a free-text column.
     *
     * The thirteen detail columns go out in DETAIL_FIELDS order, VERBATIM: the
     * two identity columns included, the two free-text columns unmodified, and
     * empty cells left empty.
     *
     * @return Generator<int, array<int, string|float|null>>
     */
    protected function streamExportRows(Builder $recordQuery, array $exportContext = ['', '', '']): Generator
    {
        $query = (clone $recordQuery)
            ->with(['processWaterDetails' => fn ($detailQuery) => $detailQuery->orderBy('time_slot')])
            ->orderBy('process_water_records.date')
            ->orderBy('process_water_records.process_water_id')
            ->orderBy('process_water_records.id');

        foreach ($query->lazy(200) as $record) {
            /** @var ProcessWaterRecord $record */
            $context = array_merge($exportContext, [
                optional($record->date)->toDateString(),
                $record->process_water_id,
                // Status RECORD, label Indonesia (temuan audit 2026-10-04 #8c)
                // — ExportValue::status(). Tidak ada ::option() di layar ini.
                ExportValue::status($this->recordStatusValue($record)),
                $record->note,
            ]);

            foreach ($record->processWaterDetails as $detail) {
                /** @var ProcessWaterDetail $detail */
                // Slot selalu HH:MM (temuan audit 2026-10-04 #8d).
                $reading = [ExportValue::time($detail->time_slot)];

                // KE-13 KOLOM, urutan DETAIL_FIELDS — `shift` dan
                // `inspector_id` lebih dulu, lalu kesebelas READING_FIELDS.
                // Definisinya DIPINJAM dari record service, tidak ditulis ulang
                // di sini.
                foreach (ProcessWaterRecordService::DETAIL_FIELDS as $field) {
                    $reading[] = $detail->{$field};
                }

                yield array_merge($context, $reading);
            }
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Base query over the period's Process Water records (header rows).
     *
     * Scoped through `stations` to the PERIOD'S business unit and to the
     * process-water station type — process_water_records carries neither
     * period_id nor business_unit_id, so the join is the only way to scope the
     * MILL — and bounded INCLUSIVELY on process_water_records.date, the event
     * date, never created_at and never the mobile sync time. A row entered late
     * still belongs to the period it happened in.
     *
     * whereDate, not a raw where: the column is a DATE here, but the suite runs
     * on SQLite while production is PostgreSQL, and this is the spelling the
     * other thirteen reports use.
     */
    protected function recordQueryFor(Period $period, ?string $productionLineId = null): Builder
    {
        $query = ProcessWaterRecord::query()
            ->join('stations', 'stations.id', '=', 'process_water_records.station_id')
            ->where('stations.business_unit_id', $period->business_unit_id)
            ->where('stations.type', StationTypeEnum::ProcessWater->value)
            ->whereDate('process_water_records.date', '>=', $period->start_date->toDateString())
            ->whereDate('process_water_records.date', '<=', $period->end_date->toDateString())
            ->select('process_water_records.*');

        $this->scopeToProductionLine($query, $productionLineId);

        return $query;
    }

    /**
     * Penyaringan per production line, DI KOLOM TABEL RECORD — bukan lewat join
     * ke `stations`.
     *
     * `process_water_records.production_line_id` adalah kolom nyata NOT NULL
     * (migrasi 2026_09_28_000043) yang di-SNAPSHOT dari stasiun saat record
     * dibuat dan tidak pernah berubah sesudahnya. Membaca dari kolom record
     * itulah yang benar secara semantik: untuk record lama yang stasiunnya sudah
     * DIPINDAH ke line lain, kolom record menunjuk line tempat data itu
     * benar-benar dihasilkan, sedangkan `stations.production_line_id` menunjuk
     * line stasiun itu SEKARANG. Menyaring lewat join ke `stations` akan menulis
     * ulang sejarah setiap kali sebuah stasiun dipindahkan.
     */
    protected function scopeToProductionLine(mixed $query, ?string $productionLineId): void
    {
        if ($productionLineId === null || $productionLineId === '') {
            return;
        }

        $query->where('process_water_records.production_line_id', $productionLineId);
    }

    /**
     * business_logic steps 1-2 — session + role gate shared by every entry
     * point.
     *
     * This gate sits two layers deeper than the route middleware on purpose:
     * clearing the middleware must never be enough by itself. That is why
     * widening a role here and widening routes/api.php are always one change,
     * never two.
     *
     * OPERATOR IS ADMITTED FROM THE FIRST COMMIT, because
     * screen-161--laporan-process-water-mobile is built in the same series and
     * is its legitimate caller (the Grading / Kernel Plant / Engine Room
     * precedent). Admitting the role HERE is only one third of it:
     * resolveBusinessUnit() must ALSO place Operator in the MILL-BOUND branch
     * and routes/api.php must admit it too, or this line opens a cross-mill
     * read. The one entry point that still refuses Operator is
     * businessUnitOptions(), which checks the role itself precisely because
     * this gate lets it through.
     *
     * @return string the caller's role
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     */
    protected function guardAccess(): string
    {
        $user = auth()->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        $role = $this->roleOf($user);

        if (! in_array($role, [
            UserRole::Supervisor->value,
            UserRole::MillManagement->value,
            UserRole::Admin->value,
            UserRole::Operator->value,
        ], true)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return $role;
    }

    /**
     * The mill check shared by authorizePeriod() and by every entry point that
     * accepts an already-loaded Period. Re-running it on a model that was
     * authorised a moment ago costs nothing and closes the gap where a caller
     * hands in a Period it never checked.
     *
     * @throws AuthorizationException 403 FORBIDDEN
     */
    protected function authorizePeriodModel(Period $period): Period
    {
        $role = $this->guardAccess();

        if ($role === UserRole::Admin->value) {
            return $period;
        }

        if ((string) $period->business_unit_id !== (string) (auth()->user()->business_unit_id ?? '')) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return $period;
    }

    /**
     * Blok `production_line` pada respons ringkasan. null ketika tidak ada line
     * berlaku.
     *
     * DIBATASI KE MILL-NYA, dan itu bukan hiasan: tanpa klausa
     * business_unit_id, memanggil /summary dengan production_line_id milik mill
     * LAIN akan menerbitkan keberadaan serta NAMA MANUSIAWI line itu. Di layar
     * ini jalur itu sudah ditolak 403 oleh resolveProductionLine(), dan scope di
     * sini adalah lapis keduanya — ditemukan 2026-10-07 dan diperbaiki identik
     * di seluruh report service sekaligus.
     *
     * @return array{id: string, name: string}|null
     */
    protected function productionLineInfo(?string $productionLineId, string $businessUnitId): ?array
    {
        if ($productionLineId === null || $productionLineId === '') {
            return null;
        }

        /** @var ProductionLine|null $line */
        $line = ProductionLine::query()
            ->where('business_unit_id', $businessUnitId)
            ->find($productionLineId, ['id', 'name']);

        if ($line === null) {
            return null;
        }

        return [
            'id' => (string) $line->id,
            'name' => (string) $line->name,
        ];
    }

    /**
     * A missing period_id is 422 VALIDATION_ERROR — incomplete input, not a 404
     * for the empty string and not a silently empty report.
     *
     * @throws ValidationException 422 VALIDATION_ERROR
     * @throws ModelNotFoundException 404 NOT_FOUND
     * @throws AuthorizationException 403 FORBIDDEN
     */
    protected function requirePeriod(Period|string|null $period): Period
    {
        if ($period === null || $period === '') {
            throw ValidationException::withMessages([
                'period_id' => ['Periode Pelaporan wajib dipilih.'],
            ]);
        }

        if ($period instanceof Period) {
            $period = $period->relationLoaded('businessUnit') ? $period : $period->load('businessUnit');

            return $this->authorizePeriodModel($period);
        }

        return $this->authorizePeriod($period);
    }

    protected function recordService(): ProcessWaterRecordService
    {
        return $this->recordService ??= app(ProcessWaterRecordService::class);
    }

    /**
     * A slot label's POSITION on the canonical 24-slot grid (07:00 .. 06:00
     * next day), memoised per service instance.
     *
     * The grid comes from ProcessWaterRecordService::canonicalTimeSlots() — the
     * very grid the input screen renders — so "which slot is last" has exactly
     * one definition in this product. A slot outside the grid sorts AFTER every
     * canonical one rather than silently landing first.
     */
    protected function slotOrderOf(mixed $timeSlot): int
    {
        if ($this->slotOrder === null) {
            $this->slotOrder = array_flip(ProcessWaterRecordService::canonicalTimeSlots());
        }

        $label = ExportValue::time($timeSlot);

        return $this->slotOrder[$label] ?? count($this->slotOrder);
    }

    /**
     * Satu opsi periode UNTUK LAYAR INI. Bentuknya sengaja DATAR, persis seperti
     * ketiga belas laporan sebelumnya, karena blade membacanya apa adanya —
     * pemisahan periods/period_stations tidak merembes ke kontrak API.
     *
     * Yang diminta layar ini bukan periode telanjang melainkan pasangan
     * (periode, process-water). Karena itu `status` adalah status STASIUN INI di
     * periode itu (period_stations.status) — periode sendiri tidak punya status
     * lagi — dan `station_type` selalu terisi.
     *
     * @return array{id: string, name: string, start_date: string, end_date: string, status: string, station_type: string, station_type_label: string}
     */
    protected function periodOption(Period $period): array
    {
        return [
            'id' => (string) $period->id,
            'name' => (string) $period->name,
            'start_date' => $period->start_date->toDateString(),
            'end_date' => $period->end_date->toDateString(),
            'status' => $this->statusValue($period),
            'station_type' => self::STATION_TYPE,
            'station_type_label' => $this->stationTypeLabel(self::STATION_TYPE),
        ];
    }

    /**
     * Status yang dilaporkan layar ini adalah status BARIS period_stations untuk
     * jenis stasiun layar ini, bukan status periode: sejak 2026-09-25 periode
     * tidak punya status sendiri, karena stasiun tidak ditutup serentak.
     *
     * Tanpa baris untuk jenis ini, jawabannya 'draft' — arti draft memang
     * "stasiun ini belum dipakai di periode ini". Sengaja bukan string kosong:
     * nilainya harus tetap salah satu dari draft/open/closed karena blade
     * mencocokkannya.
     */
    protected function statusValue(Period $period): string
    {
        $status = $this->stationRowOf($period)?->status;

        if ($status === null) {
            return PeriodStatus::Draft->value;
        }

        return is_object($status) ? $status->value : (string) $status;
    }

    /**
     * Baris period_stations untuk jenis stasiun layar ini. Memakai relasi yang
     * sudah di-eager-load bila ada — listPeriods() memuatnya terbatas pada jenis
     * ini — dan baru menembak kueri sendiri bila belum.
     */
    protected function stationRowOf(Period $period): ?PeriodStation
    {
        if ($period->relationLoaded('stations')) {
            return $period->stations->firstWhere('station_type', self::STATION_TYPE);
        }

        return $period->stations()
            ->where('station_type', self::STATION_TYPE)
            ->first();
    }

    protected function recordStatusValue(ProcessWaterRecord $record): string
    {
        return $record->status instanceof \BackedEnum
            ? $record->status->value
            : (string) $record->status;
    }

    /**
     * Label resolved from the `station_types` master table, not from
     * App\Enums\StationType — station types are DATA since 2026-09-22, so a type
     * added by INSERT must render its real name without a code change.
     */
    protected function stationTypeLabel(string $code): string
    {
        if ($this->stationTypeNames === null) {
            $this->stationTypeNames = StationType::query()
                ->get(['code', 'name'])
                ->pluck('name', 'code')
                ->all();
        }

        return $this->stationTypeNames[$code] ?? $code;
    }

    protected function dateStringOf(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
    }

    protected function roleOf(object $user): string
    {
        return $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
    }

    /**
     * Same Content-Type/filename convention as every other export in this
     * codebase. format=excel is a real .xlsx written by App\Support\SheetWriter
     * (temuan audit 2026-10-04 #1 — previously a CSV body under an xlsx name).
     *
     * @return array{0: string, 1: string}
     */
    protected function fileMetaFor(string $format, Period $period): array
    {
        $slug = str($period->name !== '' ? $period->name : 'periode')->slug()->value();
        $timestamp = now()->format('Ymd_His');

        if ($format === 'excel') {
            return [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                "laporan-process-water_{$slug}_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "laporan-process-water_{$slug}_{$timestamp}.csv",
        ];
    }
}
