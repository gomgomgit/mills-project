<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\ClarificationDetail;
use App\Models\ClarificationRecord;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\StationType;
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
 * ClarificationReportService — screen-132--laporan-clarification-web /
 * usecase-132--laporan-clarification-web (Laporan Periode Clarification).
 *
 * Shared by the API controller (App\Http\Controllers\Api\
 * ClarificationReportController) and the Livewire component (App\Livewire\
 * Dashboard\LaporanClarification), the same split used by
 * BoilerRoomReportService / BoilerRoomReportController / LaporanBoilerRoom
 * — so the web page and the API can never disagree on a figure.
 *
 * READ-ONLY BY CONSTRUCTION: every public method is a SELECT, and the
 * /api/clarification-reports prefix carries no POST/PUT/PATCH/DELETE. A
 * report must never be able to mutate the data it reports on.
 *
 * ------------------------------------------------------------------
 * WHAT IS DIFFERENT FROM THE BOILER ROOM REPORT — and why
 * ------------------------------------------------------------------
 * Boiler Room records CONDITION only. Clarification records condition AND
 * the one figure the whole mill is judged by — production — except that
 * production IS NOT RECORDED ANYWHERE. That single fact drives five rules,
 * each of which fails SILENTLY (a plausible number, never an error):
 *
 *  1. PRODUCTION IS DERIVED, NOT RECORDED. `clarification_details` has no
 *     production column at all; what it has is
 *     `pure_oil_production_rate_ton_hour`, in ton/HOUR. The hourly-grid
 *     pattern (ClarificationRecordService::canonicalTimeSlots() — 24 slots
 *     of one hour each) makes one reading stand for one hour, so
 *     production.total_ton = SUM(rate) over the rows whose rate is
 *     non-null. BECAUSE IT IS DERIVED, production.reading_count is
 *     published right beside it: production summed from 40 readings and
 *     production summed from 400 readings must never look equally
 *     convincing.
 *
 *  2. AN HOUR WITH NO RATE READING IS NOT AN HOUR THAT PRODUCED ZERO — and
 *     this is the most dangerous trap on this screen BECAUSE BOTH READINGS
 *     PRODUCE THE SAME TOTAL. A rate-less row contributes nothing to the
 *     SUM and, decisively, nothing to the DENOMINATOR of the rate average
 *     either. Treating it as 0.0 leaves the total looking correct while
 *     deflating every average: 10 filled rows of which only 4 carry a rate
 *     give avg 40.0/4 = 10.0, never 40.0/10 = 4.0. A mill with gappy
 *     recording would otherwise be indistinguishable from a mill that had
 *     stopped producing.
 *
 *  3. RATE AND DOWNTIME DO NOT NET EACH OTHER OFF. An hour that records
 *     rate 10.0 ton/hour AND downtime 20 minutes still contributes 10.0
 *     ton, not 10 x 40/60 = 6.67. This follows the formula fixed by the
 *     user during scoping ("sum laju x 1 jam").
 *
 *     >> OPEN QUESTION, STILL PENDING THE PROCESS OWNER — DO NOT DECIDE IT
 *     >> HERE. If the rate an Operator types is an INSTANTANEOUS rate
 *     >> rather than the mean across the hour, period production comes out
 *     >> higher than reality and the gap is visible nowhere. That is why
 *     >> total downtime is REQUIRED to be published next to production.
 *     >> Should the owner later decide downtime must be subtracted, the
 *     >> formula becomes rate x (60 - downtime_mins) / 60 — one change, in
 *     >> productionOf() below. Until then this class deliberately does not
 *     >> multiply the two together.
 *
 *  4. DOWNTIME ZERO IS NOT DOWNTIME UNRECORDED. downtime.total_mins is
 *     null when downtime.reading_count is 0 ("never written down") and 0
 *     when it was written down and genuinely was zero ("never stopped").
 *     The two states MUST stay distinguishable in the response; merging
 *     them reports a reliability figure that was never measured.
 *
 *  5. A SEPARATE DENOMINATOR PER METRIC, and NULL IS NEVER ZERO. All six
 *     numeric columns are nullable and a row counts as FILLED when at
 *     least one of its non-time_slot columns is filled, so one row can
 *     carry a sludge temperature and no rate at all. Every metric is
 *     therefore averaged over ONLY the rows where THAT column is non-null
 *     and carries its own reading_count; a metric never filled returns
 *     min/avg/max null with reading_count 0, independently of every other
 *     metric.
 *
 * RECORDING COMPLETENESS MATTERS MORE HERE THAN ON ANY OTHER STATION
 * REPORT, and for a reason specific to rule 1: because production is
 * DERIVED from the readings that exist, a gap in the recording lowers the
 * production figure itself — it does not merely lower confidence in it.
 * coverage.filled_slots / coverage.expected_slots is part of the report
 * body, not a footnote, and the screen renders it above every number.
 *
 * ALL THREE TANK TEMPERATURES TRAVEL ON ONE daily[] ROW per date, so the
 * screen can draw them on ONE chart with ONE shared axis. What is read is
 * the DIFFERENCE between the tanks — that gap is the sign the separation
 * process is working. Three separate charts would satisfy the phrase
 * "temperature trend across tanks" literally while destroying its point.
 *
 * ------------------------------------------------------------------
 * THERE IS DELIBERATELY NO THRESHOLD FLAGGING ANYWHERE IN THIS REPORT
 * ------------------------------------------------------------------
 * No out-of-range marking, no threshold card, no safe/danger colouring, no
 * severity, no outlier detection, no IQR. Clarification has NO
 * operational-target master table — there is no ClarificationOperationalTarget;
 * only Threshing / Pressing / Depricarping / Kernel Plant have one, and the
 * class docblock of ClarificationRecordService states that absence is
 * deliberate (same scope decision as Boiler Room / Engine Room / Storage
 * Tank / Effluent Plant).
 *
 * Deriving a threshold from the period's own data would produce a number
 * that LOOKS like a process limit while being nothing but a statistic about
 * the very data it is judging — and a tank temperature rendered in red
 * would be read as a process breach. What this report publishes is
 * min / avg / max / trend / reading_count; the judgement belongs to a
 * human. This paragraph exists so that the omission is not "completed"
 * later, and the rule is asserted BY NAME in the tests (no key or class
 * matching threshold / target / limit / outlier / iqr / is_danger /
 * is_warning / severity / status_flag) rather than merely left
 * unimplemented — a "do not flag" rule only survives if something guards
 * it.
 *
 * ------------------------------------------------------------------
 * CROSS-MILL SECURITY IS CLOSED AT TWO DIFFERENT POINTS, on purpose
 * ------------------------------------------------------------------
 *   1. resolveBusinessUnit() IGNORES the client's business_unit_id for
 *      Supervisor / Mill Management — not validated, not compared,
 *      discarded. Probing another mill's id returns 200 with the CALLER'S
 *      OWN data, deliberately not a 403: a 403 would confirm the other mill
 *      exists, and there is no access attempt to refuse because the
 *      parameter is never used for those roles.
 *   2. authorizePeriod() REFUSES a period belonging to another mill with
 *      403. Here there IS a concrete handle on another mill's data, so it
 *      is refused outright rather than silently rewritten.
 * Folding these two into one uniform 403 is the mistake this class exists
 * to avoid.
 *
 * A Supervisor / Mill Management account whose users.business_unit_id is
 * NULL FAILS CLOSED with 422 and the whole-mill list is never even read —
 * see allBusinessUnits(), which is public and trivial precisely so a spy
 * can prove it was never called.
 *
 * OPERATOR IS REFUSED ON EVERY PATH. This is a web screen and there is NO
 * Operator widening here: the mobile Clarification report is screen-138 and
 * has not been built, so there is no caller to widen for. routes/api.php
 * carries 'role:supervisor,mill_management,admin' WITHOUT operator, and
 * guardAccess() below refuses Operator two layers deeper. Widening one
 * without the other is the bug that hit screen-129/135; widening either
 * without also adding Operator to the MILL-BOUND branch of
 * resolveBusinessUnit() would be worse — it would drop Operator into the
 * unbound Admin branch, where a client-supplied business_unit_id IS
 * honoured.
 *
 * ------------------------------------------------------------------
 * RESPONSE FIELD NAMES
 * ------------------------------------------------------------------
 * The endpoint schema is authoritative (implementation_note #13):
 * production.total_ton / production.reading_count / downtime.total_mins /
 * downtime.reading_count, and the flat *_avg / production_ton columns on
 * daily[] and by_unit[]. The screen spec's own derived test cases name a
 * few of the same figures differently (avg_production_per_day_ton,
 * avg_downtime_per_day_mins, avg_clarification_tank_temp_c and friends, and
 * a nested daily[].production / by_unit[].production). Rather than pick one
 * reading and silently break the other, the schema names are emitted AND
 * the alternate names are emitted alongside them as aliases of the SAME
 * computed value — they are never computed twice. Each alias is marked
 * below.
 */
class ClarificationReportService
{
    /**
     * Export row ceiling, counted in EXPORTED LINES (= clarification_details
     * rows), not header records — one daily record carries up to 24
     * time-slot rows, so counting headers would sail straight past the real
     * limit. Same value as ClarificationRecordService::EXPORT_ROW_LIMIT.
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /**
     * Jenis stasiun yang dilaporkan layar ini — kunci baris
     * `period_stations` yang statusnya dipakai di seluruh payload layar ini.
     *
     * Menggantikan ALL_STATION_TYPES_LABEL ('Semua Stasiun'), yang hilang
     * bersama `periods.station_type` pada 2026-09-25: sebuah periode tidak
     * lagi bisa berlaku "untuk semua jenis stasiun" lewat station_type NULL —
     * cakupan itu kini dinyatakan lewat ADANYA satu baris period_stations per
     * jenis stasiun. Karena itu daftar periode layar ini adalah daftar
     * pasangan (periode, clarification), dan tidak ada lagi opsi tanpa jenis stasiun.
     */
    protected const STATION_TYPE = StationTypeEnum::Clarification->value;

    /** Export formats this report understands. Anything else is 422. */
    public const SUPPORTED_FORMATS = ['csv', 'excel'];

    /**
     * The SIX numeric measurement columns, in the order `metrics` publishes
     * them: the derived-production driver first, then the three tank
     * temperatures that share one chart, then buffer level, then downtime.
     *
     * Every one of them is nullable, and every one of them is averaged over
     * its OWN non-null rows with its OWN reading_count — see metricsOf().
     *
     * `findings` is deliberately absent: it is free text, it is never
     * aggregated, and it appears only in the export. It IS however part of
     * ClarificationRecordService::READING_FIELDS, so a row carrying nothing
     * but a finding still counts as a filled slot for coverage while
     * contributing to no metric — which is correct: something WAS recorded
     * in that slot.
     */
    public const NUMERIC_METRICS = [
        'pure_oil_production_rate_ton_hour',
        'clarification_tank_temp_c',
        'oil_tank_temperature_c',
        'sludge_tank_temp_c',
        'buffer_tank_level_percent',
        'downtime_mins',
    ];

    /**
     * THE column production is derived from. There is no production column
     * in `clarification_details`; this is the only source, in ton/hour, and
     * one reading stands for one hour of the canonical grid.
     */
    public const PRODUCTION_RATE_COLUMN = 'pure_oil_production_rate_ton_hour';

    /** Downtime column — summed and counted separately, never netted against the rate. */
    public const DOWNTIME_COLUMN = 'downtime_mins';

    /**
     * Export column headers — context columns first, repeated on every
     * line, then the time slot, then ALL SEVEN non-time_slot columns in
     * ClarificationRecordService::READING_FIELDS order (including `findings`,
     * verbatim).
     *
     * @var array<int, string>
     */
    public const EXPORT_HEADER = [
        'Tanggal',
        'Unit Clarification',
        'Status',
        'Catatan',
        'Slot Waktu',
        'Suhu Tangki Clarification (C)',
        'Suhu Tangki Minyak (C)',
        'Suhu Tangki Sludge (C)',
        'Level Buffer Tank (%)',
        'Laju Produksi Minyak Murni (ton/jam)',
        'Downtime (menit)',
        'Temuan',
    ];

    /**
     * code => name from the `station_types` master table, memoised per
     * service instance (one request / one Livewire render).
     *
     * @var array<string, string>|null
     */
    protected ?array $stationTypeNames = null;

    protected ?ClarificationRecordService $recordService = null;

    // ------------------------------------------------------------------
    // Access
    // ------------------------------------------------------------------

    /**
     * business_logic step 1 — which mill the caller is allowed to look at.
     *
     * Supervisor / Mill Management: ALWAYS their own business_unit_id; the
     * `business_unit_id` argument is ignored outright, so probing another
     * mill's id is a no-op that still returns the caller's own data with
     * HTTP 200.
     *
     * Admin: the value MUST come from the caller. Missing is 422
     * VALIDATION_ERROR with errors.business_unit_id — never a silent null
     * and never an empty result set, which would read as "this mill has no
     * data".
     *
     * Operator: TREATED EXACTLY LIKE SUPERVISOR / MILL MANAGEMENT since
     * 2026-09-25 (screen-138--laporan-clarification-mobile). This is the
     * decisive half of that widening. Admitting Operator in guardAccess()
     * ALONE would let it fall through to the Admin branch below, where the
     * client's business_unit_id IS honoured — an Operator could then read
     * ANY mill's report. That is a cross-mill leak, not a display defect.
     * The fail-closed 422 above applies to Operator too: an account with no
     * mill gets "Hubungi Admin", never the all-mills list.
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
            || $role === UserRole::Operator->value) {
            // Client-supplied business_unit_id is deliberately DISCARDED —
            // not validated, not compared, discarded.
            $businessUnitId = (string) (auth()->user()->business_unit_id ?? '');

            if ($businessUnitId === '') {
                // FAIL CLOSED, and fail EARLY: this return happens before
                // any repository call, so allBusinessUnits() is provably
                // never reached from this path (asserted with a spy).
                // Falling back to "every mill" would turn one broken
                // master-data row into a cross-mill leak.
                throw ValidationException::withMessages([
                    'business_unit_id' => ['Akun Anda belum terhubung ke mill. Hubungi Admin.'],
                ]);
            }

            return $businessUnitId;
        }

        // Admin — the only role not bound to one mill, and the only role
        // that can reach this point: guardAccess() admits exactly four
        // roles and the other three are handled above.
        if ($requestedBusinessUnitId === null || $requestedBusinessUnitId === '') {
            // Incomplete input, not refused access — 422, never 403.
            throw ValidationException::withMessages([
                'business_unit_id' => ['Pilih mill terlebih dahulu untuk menampilkan laporan.'],
            ]);
        }

        return $requestedBusinessUnitId;
    }

    /**
     * Mill picker options — ADMIN ONLY. Supervisor, Mill Management and
     * Operator are bound to a single mill and have no picker at all, so
     * asking for this list is a 403 rather than a filtered list of one.
     *
     * UNCHANGED by the screen-138 widening: Operator is admitted to the
     * report methods above, but not here. It is bound to its own mill and
     * never needs the list of every mill; the mobile view must not call it.
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
     * Public and deliberately trivial so it can be spied on: the
     * "fail closed" rule for a Supervisor / Mill Management account with no
     * business_unit_id is only meaningful if it can be PROVEN that the
     * all-mills list was never built, and a spy that records zero calls to
     * this method is that proof.
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
     * business_logic step 3 — load a period and prove the caller may read
     * it.
     *
     * 404 when the id does not exist. 403 when it belongs to another mill
     * and the caller is mill-bound (Supervisor / Mill Management) — THIS is
     * the real cross-mill leak path, so unlike the ignored business_unit_id
     * query param it is refused outright. Admin passes for any mill.
     *
     * THE ROLE GUARD RUNS BEFORE THE LOOKUP, on purpose and proven by the
     * exception TYPE: an Operator handing in a period id that does not exist
     * receives AuthorizationException (403), NOT ModelNotFoundException
     * (404). A role that is not admitted at all must not be able to learn
     * whether a period id exists by comparing a 403 against a 404.
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
     * business_logic step 2 — the periods selectable for this mill.
     *
     * A period covers Clarification when it HAS a `period_stations` row for
     * station_type 'clarification'. The old second branch — station_type NULL,
     * meaning "this period applies to every station type" — is GONE with the
     * column itself (2026-09-25): all-station scope is now expressed by the
     * PRESENCE of one row per station type, so a period WITHOUT a 'clarification'
     * row is deliberately not listed here at all. Newest first. An empty array
     * is a valid answer — a mill with no period yet gets [] with HTTP 200 and a
     * UI hint pointing at Kelola Periode Pelaporan, never a 404 and never an exception.
     *
     * The mill is resolved here rather than by the caller so that calling
     * listPeriods() as an Admin without a mill is the documented 422, and
     * calling it as a bound role with someone else's id still reads the
     * caller's own mill.
     *
     * @return list<array{id: string, name: string, start_date: string, end_date: string, status: string, station_type: string, station_type_label: string}>
     */
    public function listPeriods(?string $businessUnitId = null): array
    {
        $businessUnitId = $this->resolveBusinessUnit($businessUnitId);

        return Period::query()
            ->where('business_unit_id', $businessUnitId)
            ->whereHas('stations', fn (Builder $query) => $query->where('station_type', self::STATION_TYPE))
            // Dimuat terbatas pada jenis stasiun ini supaya statusValue()
            // tidak menembak satu kueri per periode (N+1).
            ->with(['stations' => fn ($query) => $query->where('station_type', self::STATION_TYPE)])
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get()
            ->map(fn (Period $period) => $this->periodOption($period))
            ->all();
    }

    /**
     * business_logic steps 4-15 — every figure on the screen for one
     * period: the period header, recording coverage, DERIVED production
     * with its own reading count, downtime with its own reading count, the
     * six metrics each with its own denominator, the daily recap/trend that
     * carries all three tank temperatures on one row, the per-unit recap,
     * and the period totals.
     *
     * Membership is decided by clarification_records.date — the date the
     * readings belong to — INCLUSIVE on both bounds, and never by created_at
     * or the mobile sync time. A row entered late still belongs to the
     * period it happened in.
     *
     * @param  Period|string|null  $period  model or id (both accepted so callers
     *                                      that already authorised the period do
     *                                      not have to re-read it)
     *
     * @throws ValidationException 422 VALIDATION_ERROR (no period, or Admin with no mill)
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ModelNotFoundException 404 NOT_FOUND
     */
    public function buildSummary(Period|string|null $period = null, ?string $requestedBusinessUnitId = null): array
    {
        // Ordered deliberately: role first (403), then mill (422), then the
        // period id (422), then the period itself (404 / 403). A caller who
        // may not be here at all never learns which period ids exist.
        $this->guardAccess();
        $this->resolveBusinessUnit($requestedBusinessUnitId);

        $period = $this->requirePeriod($period);

        $records = $this->recordsFor($period);
        $rows = $this->rowsOf($records);
        // "Reading rows" ARE the FILLED rows. A detail row whose seven
        // non-time_slot columns are all null is an untouched slot, already
        // reported as missing by coverage.
        $filledRows = $rows->filter(fn ($row) => $row->filled)->values();

        $dates = $this->datesOf($records);
        $units = $this->unitsOf($records);

        $daysInPeriod = $this->daysInPeriod($period);
        $daysWithRecords = $dates->count();
        $filledSlots = $filledRows->count();
        $unitCount = $units->count();
        // Expected slots per unit per day comes from the canonical time-slot
        // grid the input screens themselves use
        // (ClarificationRecordService::canonicalTimeSlots()), not from a
        // number invented here — one definition, one answer. It is also what
        // makes "one reading = one hour" true, which is what licenses the
        // derived-production sum.
        $slotsPerUnitPerDay = count(ClarificationRecordService::canonicalTimeSlots());
        $expectedSlots = $unitCount * $daysInPeriod * $slotsPerUnitPerDay;

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
            // has_data distinguishes "there is nothing to report" from "the
            // figures happen to be zero". The screen uses it to refuse to
            // draw an empty chart, which would read as a measured flat line.
            'has_data' => $filledSlots > 0,
            // RECORDING COVERAGE IS PART OF THE REPORT, NOT METADATA, and on
            // THIS screen it is load-bearing rather than merely advisory:
            // production is DERIVED from the readings that exist, so a gap
            // in the recording lowers the production figure itself. The
            // screen renders this card ABOVE every other figure.
            'coverage' => [
                'filled_slots' => $filledSlots,
                'expected_slots' => $expectedSlots,
                // Two decimals, not one: 9 of 144 is 6.25%, and rounding it
                // to 6.3 loses the only digit that distinguishes a badly
                // recorded period from a catastrophically recorded one. The
                // screen formats for display; the payload keeps the number.
                'coverage_percent' => $expectedSlots === 0
                    ? 0.0
                    : round(100 * $filledSlots / $expectedSlots, 2),
                'unit_count' => $unitCount,
                'slots_per_unit_per_day' => $slotsPerUnitPerDay,
                'days_in_period' => $daysInPeriod,
            ],
            'production' => $this->productionOf($filledRows, $daysWithRecords),
            'downtime' => $this->downtimeOf($filledRows, $daysWithRecords),
            'metrics' => $this->metricsOf($filledRows),
            'daily' => $this->dailyOf($dates),
            'by_unit' => $this->byUnitOf($units),
            'total' => [
                'days_with_records' => $daysWithRecords,
                'reading_rows' => $filledSlots,
            ],
        ];
    }

    /**
     * Repo-convention alias of buildSummary(), so this service reads the
     * same way as BoilerRoomReportService::summary() and
     * CagesTrackReportService::summary() at the call sites. One
     * implementation, two names — never two implementations.
     */
    public function summary(Period|string|null $period = null, ?string $requestedBusinessUnitId = null): array
    {
        return $this->buildSummary($period, $requestedBusinessUnitId);
    }

    /**
     * business_logic step 17 — ONE EXPORTED LINE PER TIME SLOT
     * (clarification_details row), with the record's context columns (date /
     * clarification unit / status / note) repeated on every line so the file
     * can be pivoted directly in a spreadsheet. Same convention as the 18
     * station exports (commit 8611974), scoped to a period.
     *
     * All SEVEN non-time_slot columns are emitted, INCLUDING `findings` —
     * verbatim, with no rounding.
     *
     * THE GUARD AND THE ROW-LIMIT CHECK RUN EAGERLY, at call time, while
     * the rows themselves are yielded lazily from a chunked query. Making
     * this method itself a generator would defer the 403/422 until the
     * first iteration, so a refused export would look like a successful
     * call that produced nothing.
     *
     * The ceiling counts DETAIL ROWS, not header records.
     *
     * @return Generator<int, array<int, string|float|null>>
     *
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ModelNotFoundException 404 NOT_FOUND
     * @throws ValidationException 422 VALIDATION_ERROR
     * @throws ExportFailedException 422 EXPORT_FAILED
     */
    public function buildExportRows(Period|string|null $period = null, ?string $requestedBusinessUnitId = null): Generator
    {
        $this->guardAccess();
        $this->resolveBusinessUnit($requestedBusinessUnitId);

        $period = $this->requirePeriod($period);

        $recordQuery = $this->recordQueryFor($period);

        $detailRowCount = ClarificationDetail::query()
            ->whereIn('clarification_record_id', (clone $recordQuery)->select('clarification_records.id'))
            ->count();

        // Strictly greater than: exactly EXPORT_ROW_LIMIT rows still export.
        if ($detailRowCount > self::EXPORT_ROW_LIMIT) {
            throw new ExportFailedException;
        }

        return $this->streamExportRows($recordQuery);
    }

    /**
     * business_logic step 17 — the streamed file around buildExportRows().
     *
     * @throws ValidationException 422 VALIDATION_ERROR (unsupported format)
     * @throws ExportFailedException 422 EXPORT_FAILED
     */
    public function export(Period|string|null $period = null, string $format = 'csv', ?string $requestedBusinessUnitId = null): StreamedResponse
    {
        $this->guardAccess();

        if (! in_array($format, self::SUPPORTED_FORMATS, true)) {
            throw ValidationException::withMessages([
                'format' => ['Format ekspor harus csv atau excel.'],
            ]);
        }

        $resolvedPeriod = $this->requirePeriod($period);

        // Runs the guard + the row-limit check NOW, before a single byte of
        // the response is committed — a refused export must never begin
        // streaming.
        $rows = $this->buildExportRows($resolvedPeriod, $requestedBusinessUnitId);

        try {
            [$contentType, $filename] = $this->fileMetaFor($format, $resolvedPeriod);

            return response()->streamDownload(function () use ($rows) {
                $handle = fopen('php://output', 'w');

                // Explicit $separator/$enclosure/$escape — PHP 8.4 deprecates
                // relying on fputcsv()'s default $escape.
                fputcsv($handle, self::EXPORT_HEADER, ',', '"', '\\');

                foreach ($rows as $row) {
                    fputcsv($handle, $row, ',', '"', '\\');
                }

                fclose($handle);
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
    // NULLABLE columns is NOT the same in SQLite (used by the test suite)
    // and PostgreSQL (production), and the separate-denominator and
    // null-is-not-zero rules above are exactly what gets lost in that
    // difference. No avg()/sum()/min()/max()/groupBy() is issued at the SQL
    // layer anywhere below; the queries only ever select raw rows, filter
    // them, and order them. The row set is bounded by one reporting period;
    // the export path — the only unbounded one — streams in chunks instead.
    // ------------------------------------------------------------------

    /**
     * Every Clarification record inside the period, flattened to plain
     * objects with their time-slot detail rows attached, ascending by
     * time_slot.
     *
     * ONE DATE CAN HAVE SEVERAL RECORDS — one per clarification unit,
     * because clarification_id is a STRING column on the HEADER, not a
     * foreign key. The per-date aggregation below merges them, and the
     * per-unit aggregation splits them apart again.
     *
     * @return Collection<int, object>
     */
    protected function recordsFor(Period $period): Collection
    {
        return $this->recordQueryFor($period)
            // business_logic step 15: time_slot is a TIME column and is
            // ordered and grouped AS THE TIME VALUE IT IS. It is never cast
            // to an integer hour and no slot is assumed to fall exactly on
            // the hour — casting would collapse 06:00 and 06:30 into one
            // slot and would reorder 00:30 against 10:00.
            ->with(['clarificationDetails' => fn ($query) => $query->orderBy('time_slot')])
            ->orderBy('clarification_records.date')
            ->orderBy('clarification_records.clarification_id')
            ->get()
            ->map(fn (ClarificationRecord $record) => (object) [
                'date' => $this->dateStringOf($record->date),
                'clarification_id' => (string) ($record->clarification_id ?? ''),
                'status' => $this->recordStatusValue($record),
                'note' => $record->note,
                'rows' => $record->clarificationDetails
                    ->map(fn (ClarificationDetail $detail) => $this->rowOf($detail, $record))
                    ->values(),
            ])
            ->values();
    }

    /**
     * One detail row, reduced to exactly what the aggregation needs.
     *
     * `filled` REUSES ClarificationRecordService::isRowFilled() over its
     * READING_FIELDS — the definition the input screens already enforce.
     * Writing a second "is this row filled?" rule here is how the report
     * and the form start disagreeing about what was recorded. That is also
     * why READING_FIELDS is not copied into this class.
     */
    protected function rowOf(ClarificationDetail $detail, ClarificationRecord $record): object
    {
        $attributes = $detail->only(ClarificationRecordService::READING_FIELDS);

        $values = [];

        foreach (self::NUMERIC_METRICS as $metric) {
            $raw = $detail->{$metric};
            // NULL STAYS NULL. It is never coerced to 0.0 — a missing
            // reading must not be able to drag an average down, and a
            // missing RATE reading must not be able to look like an hour
            // that produced nothing.
            $values[$metric] = $raw === null || $raw === '' ? null : (float) $raw;
        }

        return (object) [
            'date' => $this->dateStringOf($record->date),
            'clarification_id' => (string) ($record->clarification_id ?? ''),
            'time_slot' => (string) $detail->time_slot,
            'filled' => $this->recordService()->isRowFilled($attributes),
            'values' => $values,
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
     * DERIVED PRODUCTION — the figure that defines this screen.
     *
     * There is no production column to read. total_ton is the SUM of
     * pure_oil_production_rate_ton_hour over the rows whose rate is
     * non-null, because the hourly grid makes one reading stand for one
     * hour of its slot.
     *
     * reading_count TRAVELS WITH IT, always. A total derived from 4
     * readings and a total derived from 400 must never look equally
     * trustworthy, and reading_count is the only thing that says so.
     *
     * ROWS WITHOUT A RATE ARE EXCLUDED FROM BOTH THE SUM AND THE
     * DENOMINATOR. They are not zero-production hours. This is the trap
     * that hides itself: both readings give the SAME total, and only the
     * average differs — 10 filled rows of which 4 carry a rate give
     * 40.0/4 = 10.0, never 40.0/10 = 4.0.
     *
     * DOWNTIME IS NOT SUBTRACTED HERE. An hour recording rate 10.0 and
     * downtime 20 mins contributes 10.0 ton, not 6.67 — see rule 3 in the
     * class docblock. That question is STILL OPEN and belongs to the
     * process owner; if it is ever answered the other way, the change is
     * exactly one line in this method (rate x (60 - downtime) / 60) and
     * nowhere else.
     *
     * null, never 0.0, when nothing was ever recorded.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array<string, float|int|null>
     */
    protected function productionOf(Collection $filledRows, int $daysWithRecords): array
    {
        $rates = $this->valuesOf($filledRows, self::PRODUCTION_RATE_COLUMN);
        $readingCount = count($rates);

        $totalTon = $readingCount === 0 ? null : round(array_sum($rates), 2);

        // Per-day average divides by DAYS THAT HAVE RECORDS, not by the
        // period length: a day nobody wrote anything down for is not a day
        // the mill produced nothing, and dividing by the calendar would
        // silently apply exactly the zero-for-missing rule this report
        // exists to refuse.
        $avgPerDay = ($totalTon === null || $daysWithRecords === 0)
            ? null
            : round($totalTon / $daysWithRecords, 2);

        $avgRate = $readingCount === 0
            ? null
            : round(array_sum($rates) / $readingCount, 2);

        return [
            'total_ton' => $totalTon,
            'avg_per_day_ton' => $avgPerDay,
            // Alias of avg_per_day_ton — same value, the name the screen
            // spec's derived tests use. Computed once, published twice.
            'avg_production_per_day_ton' => $avgPerDay,
            'reading_count' => $readingCount,
            // The rate figures live here as well as in `metrics` so the
            // production card can show the denominator its own average was
            // taken over without reaching across the payload.
            'avg_rate_ton_hour' => $avgRate,
            'min_rate_ton_hour' => $readingCount === 0 ? null : round(min($rates), 2),
            'max_rate_ton_hour' => $readingCount === 0 ? null : round(max($rates), 2),
        ];
    }

    /**
     * DOWNTIME — where zero and unrecorded are different answers.
     *
     * total_mins is the SUM over rows whose downtime_mins is non-null;
     * reading_count is how many rows that was; hours_with_downtime counts
     * only the rows above zero.
     *
     * WHEN reading_count IS 0, total_mins IS null — NOT 0. Zero means "it
     * never stopped"; null means "nobody measured". Merging them publishes
     * a reliability figure that was never taken. The pair
     * (total_mins, reading_count) is what makes the two states
     * distinguishable from the response, which is why both always ship.
     *
     * Downtime is published BESIDE production on purpose: as long as rate
     * and downtime are not netted against each other (see productionOf()),
     * the reader needs both numbers to judge the production figure at all.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array<string, float|int|null>
     */
    protected function downtimeOf(Collection $filledRows, int $daysWithRecords): array
    {
        $values = $this->valuesOf($filledRows, self::DOWNTIME_COLUMN);
        $readingCount = count($values);

        $totalMins = $readingCount === 0 ? null : $this->minutesValue(array_sum($values));

        $avgPerDay = ($totalMins === null || $daysWithRecords === 0)
            ? null
            : round($totalMins / $daysWithRecords, 2);

        return [
            'total_mins' => $totalMins,
            'avg_per_day_mins' => $avgPerDay,
            // Alias of avg_per_day_mins — see the note in productionOf().
            'avg_downtime_per_day_mins' => $avgPerDay,
            // Rows whose recorded downtime is strictly positive. A row
            // recording 0 counts as a reading but not as an hour with
            // downtime; a row recording nothing counts as neither.
            'hours_with_downtime' => $filledRows
                ->filter(fn ($row) => ($row->values[self::DOWNTIME_COLUMN] ?? null) !== null
                    && $row->values[self::DOWNTIME_COLUMN] > 0)
                ->count(),
            'reading_count' => $readingCount,
        ];
    }

    /**
     * THE OTHER HEART OF THIS REPORT: one entry per metric, each with ITS
     * OWN denominator.
     *
     * A metric is averaged over ONLY the rows where that column is
     * non-null, and reports how many rows that was. There is deliberately
     * no shared reading_count anywhere in the payload — a single shared
     * denominator would deflate every rarely-filled metric while still
     * producing a number that looks entirely reasonable.
     *
     * A metric never filled in the whole period returns null/null/null with
     * reading_count 0 — NOT 0/0/0 — and does so independently of every
     * other metric: an empty rate column leaves the sludge temperature
     * untouched.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array<string, array{min: float|null, avg: float|null, max: float|null, reading_count: int}>
     */
    protected function metricsOf(Collection $filledRows): array
    {
        $metrics = [];

        foreach (self::NUMERIC_METRICS as $metric) {
            $metrics[$metric] = $this->statsOf($this->valuesOf($filledRows, $metric));
        }

        return $metrics;
    }

    /**
     * min / avg / max / reading_count over one metric's own values.
     *
     * Empty input is null/null/null/0, never 0/0/0/0.
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
            'min' => round(min($values), 2),
            'avg' => round(array_sum($values) / count($values), 2),
            'max' => round(max($values), 2),
            'reading_count' => count($values),
        ];
    }

    /**
     * The non-null values of one column across a bucket of rows. THE ONLY
     * place a metric's denominator is established — nulls are dropped, never
     * replaced by zero.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array<int, float>
     */
    protected function valuesOf(Collection $filledRows, string $metric): array
    {
        return $filledRows
            ->map(fn ($row) => $row->values[$metric] ?? null)
            ->filter(fn ($value) => $value !== null)
            ->values()
            ->all();
    }

    /**
     * One aggregate per DATE that has at least one record, ascending,
     * merging every clarification unit that ran on that date.
     *
     * A date whose rows are all empty for a given metric yields null in that
     * column but STILL COUNTS as a date with records — dropping it would
     * make the period look better recorded than it was. A date with no
     * record at all gets no entry: a padded zero row would read as "we
     * measured nothing" when the mill simply did not run.
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
                    'filled_slots' => $filled->count(),
                    'averages' => $this->bucketAveragesOf($filled),
                    'production' => $this->bucketProductionOf($filled),
                    'downtime_mins' => $this->bucketDowntimeOf($filled),
                ];
            })
            ->values();
    }

    /**
     * One aggregate per CLARIFICATION UNIT (clarification_id) over every
     * record in the period.
     *
     * A unit that has a record but not one filled reading STILL APPEARS,
     * with reading_count 0 and null everywhere else. Dropping it would hide
     * exactly the unit that was never written down — the one worth seeing.
     *
     * @param  Collection<int, object>  $records
     * @return Collection<int, object>
     */
    protected function unitsOf(Collection $records): Collection
    {
        return $records
            ->groupBy('clarification_id')
            ->sortKeys()
            ->map(function (Collection $group, string $clarificationId) {
                $rows = $group->flatMap(fn ($record) => $record->rows)->values();
                $filled = $rows->filter(fn ($row) => $row->filled)->values();

                return (object) [
                    'clarification_id' => $clarificationId,
                    'reading_count' => $filled->count(),
                    'averages' => $this->bucketAveragesOf($filled),
                    'production' => $this->bucketProductionOf($filled),
                    'downtime_mins' => $this->bucketDowntimeOf($filled),
                ];
            })
            ->values();
    }

    /**
     * Each metric averaged over one bucket (a date or a clarification unit)
     * — each, again, with ITS OWN denominator inside that bucket.
     *
     * null when the bucket has no value at all for that metric.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array<string, float|null>
     */
    protected function bucketAveragesOf(Collection $filledRows): array
    {
        $averages = [];

        foreach (self::NUMERIC_METRICS as $metric) {
            $values = $this->valuesOf($filledRows, $metric);

            $averages[$metric] = $values === []
                ? null
                : round(array_sum($values) / count($values), 2);
        }

        return $averages;
    }

    /**
     * Derived production inside one bucket, with the bucket's OWN rate
     * reading count.
     *
     * A date (or unit) with records but no rate reading gets total_ton null
     * and reading_count 0 — NOT 0.0 ton. On the daily trend that is the
     * difference between "we produced nothing that day" and "nobody wrote
     * the rate down that day", and the chart must not draw the second as
     * the first.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array{total_ton: float|null, reading_count: int, rate_avg: float|null}
     */
    protected function bucketProductionOf(Collection $filledRows): array
    {
        $rates = $this->valuesOf($filledRows, self::PRODUCTION_RATE_COLUMN);
        $readingCount = count($rates);

        return [
            'total_ton' => $readingCount === 0 ? null : round(array_sum($rates), 2),
            'reading_count' => $readingCount,
            'rate_avg' => $readingCount === 0
                ? null
                : round(array_sum($rates) / $readingCount, 2),
        ];
    }

    /**
     * Downtime summed inside one bucket. null when the bucket recorded no
     * downtime reading at all — same null-is-not-zero rule as the period
     * figure.
     *
     * @param  Collection<int, object>  $filledRows
     */
    protected function bucketDowntimeOf(Collection $filledRows): float|int|null
    {
        $values = $this->valuesOf($filledRows, self::DOWNTIME_COLUMN);

        return $values === [] ? null : $this->minutesValue(array_sum($values));
    }

    /**
     * The daily recap / daily trend table.
     *
     * ALL THREE TANK TEMPERATURES SHIP ON THE SAME OBJECT per date, which is
     * the whole point: the screen draws them on ONE chart with ONE axis
     * because what is read is the DIFFERENCE between the tanks. Splitting
     * them across three payload shapes would invite three charts, which
     * satisfies "temperature trend across tanks" literally and destroys its
     * meaning.
     *
     * @param  Collection<int, object>  $dates
     * @return list<array>
     */
    protected function dailyOf(Collection $dates): array
    {
        return $dates
            ->map(fn ($row) => [
                'date' => $row->date,
                'filled_slots' => $row->filled_slots,
                'production_ton' => $row->production['total_ton'],
                'rate_avg' => $row->production['rate_avg'],
                'rate_reading_count' => $row->production['reading_count'],
                'clarification_tank_temp_avg' => $row->averages['clarification_tank_temp_c'],
                'oil_tank_temperature_avg' => $row->averages['oil_tank_temperature_c'],
                'sludge_tank_temp_avg' => $row->averages['sludge_tank_temp_c'],
                'buffer_tank_level_avg' => $row->averages['buffer_tank_level_percent'],
                'downtime_mins' => $row->downtime_mins,
                // Aliases of the three temperatures and of the derived
                // production above — same values, the names the screen
                // spec's derived tests use. Never computed twice.
                'avg_clarification_tank_temp_c' => $row->averages['clarification_tank_temp_c'],
                'avg_oil_tank_temperature_c' => $row->averages['oil_tank_temperature_c'],
                'avg_sludge_tank_temp_c' => $row->averages['sludge_tank_temp_c'],
                'production' => [
                    'total_ton' => $row->production['total_ton'],
                    'reading_count' => $row->production['reading_count'],
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * The per-clarification-unit recap table.
     *
     * No buffer_tank_level column here, matching the endpoint schema — the
     * buffer level is a tank-wide figure rather than a per-unit one, and it
     * is published on the period card and in daily[] instead.
     *
     * @param  Collection<int, object>  $units
     * @return list<array>
     */
    protected function byUnitOf(Collection $units): array
    {
        return $units
            ->map(fn ($row) => [
                'clarification_id' => $row->clarification_id,
                'reading_count' => $row->reading_count,
                'production_ton' => $row->production['total_ton'],
                'rate_avg' => $row->production['rate_avg'],
                'rate_reading_count' => $row->production['reading_count'],
                'clarification_tank_temp_avg' => $row->averages['clarification_tank_temp_c'],
                'oil_tank_temperature_avg' => $row->averages['oil_tank_temperature_c'],
                'sludge_tank_temp_avg' => $row->averages['sludge_tank_temp_c'],
                'downtime_mins' => $row->downtime_mins,
                // Alias of production_ton / its own reading count — see
                // dailyOf().
                'production' => [
                    'total_ton' => $row->production['total_ton'],
                    'reading_count' => $row->production['reading_count'],
                ],
            ])
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // Export streaming
    // ------------------------------------------------------------------

    /**
     * The lazy half of buildExportRows(): one array per time-slot row,
     * pulled in chunks so a long period never materialises as one
     * collection.
     *
     * Ordered by date, then by clarification unit, then by time_slot — and
     * time_slot is compared as the TIME value it is, never as an integer
     * hour.
     *
     * @return Generator<int, array<int, string|float|null>>
     */
    protected function streamExportRows(Builder $recordQuery): Generator
    {
        $query = (clone $recordQuery)
            ->with(['clarificationDetails' => fn ($detailQuery) => $detailQuery->orderBy('time_slot')])
            ->orderBy('clarification_records.date')
            ->orderBy('clarification_records.clarification_id')
            ->orderBy('clarification_records.id');

        foreach ($query->lazy(200) as $record) {
            /** @var ClarificationRecord $record */
            $context = [
                optional($record->date)->toDateString(),
                $record->clarification_id,
                $this->recordStatusValue($record),
                $record->note,
            ];

            foreach ($record->clarificationDetails as $detail) {
                /** @var ClarificationDetail $detail */
                $reading = [(string) $detail->time_slot];

                foreach (ClarificationRecordService::READING_FIELDS as $field) {
                    // VERBATIM, including `findings` — no rounding, no
                    // normalisation, exactly as the Operator typed it.
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
     * Base query over the period's Clarification records (header rows).
     *
     * Scoped through `stations` to the PERIOD'S business unit and to the
     * clarification station type, and bounded INCLUSIVELY on
     * clarification_records.date — the event date, not created_at and not
     * the sync time. clarification_records carries neither period_id nor
     * business_unit_id, so the join is the only way to scope it.
     */
    protected function recordQueryFor(Period $period): Builder
    {
        return ClarificationRecord::query()
            ->join('stations', 'stations.id', '=', 'clarification_records.station_id')
            ->where('stations.business_unit_id', $period->business_unit_id)
            ->where('stations.type', StationTypeEnum::Clarification->value)
            ->whereDate('clarification_records.date', '>=', $period->start_date->toDateString())
            ->whereDate('clarification_records.date', '<=', $period->end_date->toDateString())
            ->select('clarification_records.*');
    }

    /**
     * Session + role gate shared by every entry point.
     *
     * This gate sits two layers deeper than the route middleware on
     * purpose: clearing the middleware must never be enough by itself. That
     * is why widening a role here and widening routes/api.php are always one
     * change, never two — the lesson from screen-129/135.
     *
     * OPERATOR IS ADMITTED SINCE 2026-09-25
     * (screen-138--laporan-clarification-mobile). Until that screen this
     * gate refused it, because the WEB report had no Operator caller to
     * widen for; the mobile report is that caller. Admitting Operator HERE
     * without also adding it to the mill-bound branch of
     * resolveBusinessUnit() would let it pass its own business_unit_id and
     * read any mill's report — which is why those two edits are one change,
     * never two, and why ClarificationReportServiceTest case 37 asserts the
     * mill binding rather than merely the acceptance.
     *
     * businessUnitOptions() is NOT part of this widening: it does its own
     * Admin-only check and still answers 403 for Operator.
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
     * The mill check shared by authorizePeriod() and by every entry point
     * that accepts an already-loaded Period. Re-running it on a model that
     * was authorised a moment ago costs nothing and closes the gap where a
     * caller hands in a Period it never checked.
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
     * A missing period_id is 422 VALIDATION_ERROR — incomplete input, not a
     * 404 for the empty string and not a silently empty report.
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

    /** Inclusive day count of the period — both ends belong to it. */
    protected function daysInPeriod(Period $period): int
    {
        return (int) $period->start_date->copy()->startOfDay()
            ->diffInDays($period->end_date->copy()->startOfDay()) + 1;
    }

    /**
     * Minutes as the endpoint schema types them: an integer when the sum is
     * whole, which is every ordinary case and what the tests compare against
     * with ===.
     *
     * The DB column is float nullable, so a genuinely fractional downtime is
     * representable; it is preserved here rather than truncated, because
     * silently rounding a recorded value is worse than a type that is
     * slightly wider than the schema says. See the known issue recorded with
     * this screen.
     */
    protected function minutesValue(float|int $minutes): float|int
    {
        $rounded = round((float) $minutes, 2);

        return $rounded == (int) $rounded ? (int) $rounded : $rounded;
    }

    protected function recordService(): ClarificationRecordService
    {
        return $this->recordService ??= app(ClarificationRecordService::class);
    }

    /**
     * Satu opsi periode UNTUK LAYAR INI. Bentuknya sengaja tetap DATAR,
     * persis seperti sebelum 2026-09-25, karena layar mobile dan blade
     * membacanya apa adanya — pemisahan periods/period_stations tidak
     * merembes ke kontrak API.
     *
     * Bacaannya: yang diminta layar ini bukan periode telanjang melainkan
     * pasangan (periode, clarification). Karena itu `status` adalah status
     * STASIUN INI di periode itu (period_stations.status) — periode sendiri
     * tidak punya status lagi — dan `station_type` selalu terisi: ia tidak
     * pernah null lagi karena hanya periode yang punya baris untuk jenis
     * ini yang sampai ke sini.
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
     * Status yang dilaporkan layar ini adalah status BARIS period_stations
     * untuk jenis stasiun layar ini, bukan status periode: sejak 2026-09-25
     * periode tidak punya status sendiri (Period::$status melempar
     * LogicException), karena stasiun tidak ditutup serentak — Sterilizer
     * bisa tertutup sementara Clarification masih terbuka di periode yang
     * sama.
     *
     * Tanpa baris untuk jenis ini, jenis stasiun ini tidak dikelola periode
     * itu, dan jawabannya 'draft' — arti draft memang "stasiun ini belum
     * dipakai di periode ini". Sengaja bukan string kosong: nilai status
     * harus tetap salah satu dari draft/open/closed karena blade dan layar
     * mobile mencocokkannya. Bentuk ini hanya bisa muncul lewat summary()/
     * export(): listPeriods() tidak pernah memulangkan periode tanpa baris.
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
     * Baris period_stations untuk jenis stasiun layar ini. Memakai relasi
     * yang sudah di-eager-load bila ada — listPeriods() memuatnya terbatas
     * pada jenis ini — dan baru menembak kueri sendiri bila belum.
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

    protected function recordStatusValue(ClarificationRecord $record): string
    {
        return $record->status instanceof \BackedEnum
            ? $record->status->value
            : (string) $record->status;
    }

    /**
     * Label resolved from the `station_types` master table, not from
     * App\Enums\StationType — station types are DATA since 2026-09-22, so a
     * type added by INSERT must render its real name without a code change.
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
     * codebase — no XLSX writer package is installed, so format=excel
     * serves a CSV body under the xlsx mimetype/extension.
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
                "laporan-clarification_{$slug}_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "laporan-clarification_{$slug}_{$timestamp}.csv",
        ];
    }
}
