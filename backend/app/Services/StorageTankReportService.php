<?php

namespace App\Services;

use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\StationType;
use App\Models\StorageTankDetail;
use App\Models\StorageTankRecord;
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
 * StorageTankReportService — screen-133--laporan-storage-tank-web /
 * usecase-133--laporan-storage-tank-web (Laporan Periode Storage Tank).
 *
 * Shared by the API controller (App\Http\Controllers\Api\
 * StorageTankReportController) and the Livewire component (App\Livewire\
 * Dashboard\LaporanStorageTank), the same split used by
 * ClarificationReportService / BoilerRoomReportService — so the web page and
 * the API can never disagree on a figure.
 *
 * READ-ONLY BY CONSTRUCTION: every public method is a SELECT, and the
 * /api/storage-tank-reports prefix carries no POST/PUT/PATCH/DELETE. A
 * report must never be able to mutate the data it reports on.
 *
 * ==================================================================
 * WHAT MAKES THIS REPORT DIFFERENT FROM THE OTHER FIVE STATION REPORTS
 * ==================================================================
 * Cages Track / Sterilizer / Boiler Room / Clarification all summarise
 * EVENTS across a period: they count, sum and average readings, and every
 * extra reading refines the answer. THIS report answers a question about
 * STATE AND ITS CHANGE — how much was in the tanks at the start, how much
 * at the end, how much moved in between — and that answer is a COMPARISON
 * OF EXACTLY TWO READINGS per tank. More readings do not refine it; what
 * decides it is WHICH two readings are the first and the last.
 *
 * That single difference drives seven rules, each of which fails SILENTLY
 * (a plausible number, never an error):
 *
 *  1. STOCK IS NOT AN AGGREGATE. For each tank, order ALL of its readings
 *     by (storage_tank_records.date, storage_tank_details.time_slot)
 *     ASCENDING, then take the FIRST reading whose calculated_weight_mt is
 *     non-null as the opening and the LAST such reading as the closing.
 *     NOT min() and max() — a tank that was filled mid-period and drawn
 *     down again has its highest reading in the middle, and min/max would
 *     report a movement that never happened. NOT the first/last row in
 *     STORAGE ORDER either: nothing guarantees insert order matches time
 *     order, and the unit-test fixtures deliberately insert the rows out of
 *     order so an id-ordered implementation is rejected.
 *
 *  2. THE TIMESTAMP OF EACH OF THOSE TWO READINGS TRAVELS WITH IT
 *     (opening_at / closing_at, per tank AND on the period card). An
 *     opening stock first recorded on day three of the period means the
 *     first two days were never written down, and the movement figure then
 *     covers a shorter span than the period it is labelled with. Without
 *     the timestamps the reader has no way of knowing that.
 *
 *  3. IF A TANK'S EARLIEST READING DOES NOT RECORD STOCK, the opening is
 *     taken from the NEXT reading that does — and opening_at points at THAT
 *     reading, not at the empty one and not at the period start. The
 *     mirror-image rule applies backwards for the closing.
 *
 *  4. MOVEMENT IS SUMMED PER TANK, never derived from the combined stock.
 *     movement = closing - opening FOR THAT TANK, and stock.movement_mt is
 *     the SUM of those per-tank movements. Computing it as
 *     stock.closing_mt - stock.opening_mt gives a different — and wrong —
 *     answer whenever the set of tanks recorded at the start differs from
 *     the set recorded at the end: it mixes a change in STOCK together with
 *     a change in RECORDING COVERAGE, and the result looks entirely
 *     reasonable.
 *
 *  5. A TANK WITH EXACTLY ONE STOCK READING HAS NO COMPUTABLE MOVEMENT:
 *     movement_mt is null and movement_computable is false — NOT 0. Zero
 *     means "the stock did not change", a strictly stronger claim than
 *     "cannot be computed", and it is a claim nobody measured. Such a tank
 *     also contributes NOTHING to stock.movement_mt.
 *
 *  6. A NEGATIVE MOVEMENT IS A VALID VALUE, not an error: oil was shipped
 *     out. It is never clamped to zero, never passed through abs(), and
 *     never flagged as a warning anywhere in the payload or on the screen.
 *
 *  7. A SEPARATE DENOMINATOR PER METRIC, and NULL IS NEVER ZERO. All 17
 *     non-time_slot columns are nullable and a row counts as FILLED when at
 *     least one of them is filled (StorageTankRecordService::isRowFilled()
 *     over READING_FIELDS — reused, never re-stated here), so one row can
 *     record FFA and leave DOBI empty. Every metric is therefore averaged
 *     over ONLY the rows where THAT column is non-null and carries its own
 *     reading_count; a metric never filled returns min/avg/max null with
 *     reading_count 0, independently of every other metric.
 *
 * AVERAGE TEMPERATURE IS READ FROM THE COLUMN THE OPERATOR RECORDED
 * (average_temperature_c) and is NEVER recomputed from
 * oil_temperature_top_c / _middle_c / _bottom_c. Recomputing would create a
 * SECOND truth that drifts silently away from the one the Operator sees on
 * the input screen, and the drift would never be visible because both
 * numbers look equally plausible. The three positional temperatures are
 * still published as metrics in their own right, for their own purpose.
 * When average_temperature_c is empty the metric is null with
 * reading_count 0 — the report does not derive it from the other three.
 *
 * STOCK USES WEIGHT (MT), decided by the process owner on 2026-09-25.
 * calculated_volume_m3 and the sounding depths remain available as metrics
 * in their own right but are never used for opening / closing / movement:
 * all three can be filled independently and disagree, and without this
 * decision all three look equally authoritative.
 *
 * RECORDING COMPLETENESS IS PART OF THE REPORT BODY, not a footnote, and
 * on this screen for a reason specific to rule 1: coverage is what says how
 * far apart in time the two compared readings actually sit. The screen
 * renders it ABOVE every figure.
 *
 * ------------------------------------------------------------------
 * THERE IS DELIBERATELY NO THRESHOLD FLAGGING ANYWHERE IN THIS REPORT
 * ------------------------------------------------------------------
 * No out-of-range marking, no threshold card, no safe/danger colouring, no
 * severity, no outlier detection, no IQR — and, specifically, no warning
 * styling on a negative movement. Storage Tank has NO operational-target
 * master table: there is no StorageTankOperationalTarget (only Threshing /
 * Pressing / Depricarping / Kernel Plant have one), and the class docblock
 * of StorageTankRecordService states that the absence is deliberate — the
 * same scope decision as Boiler Room / Engine Room / Clarification /
 * Effluent Plant.
 *
 * FFA and moisture DO have widely-known industry limits, which is exactly
 * why deriving a threshold here would be dangerous: a number derived from
 * the period's own data would be READ as an official quality limit while
 * being nothing but a statistic about the very data it judges, and an FFA
 * figure rendered in red would be read as a breach of a standard that this
 * system never recorded. What this report publishes is
 * min / avg / max / trend / reading_count; the judgement belongs to a
 * human. This paragraph exists so the omission is not "completed" later,
 * and the rule is asserted BY NAME in the tests (no key or class matching
 * threshold / outlier / iqr / fence / is_danger / is-danger / text-red /
 * severity / alert) rather than merely left unimplemented — a "do not flag"
 * rule only survives if something guards it.
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
 * see allBusinessUnits(), which is public and deliberately trivial so a spy
 * can prove it was never called.
 *
 * OPERATOR IS ADMITTED SINCE 2026-09-25, AND BOUND TO ITS OWN MILL. The
 * mobile Storage Tank report (screen-139) reuses these very endpoints
 * instead of getting its own, so the figures on the phone and the figures on
 * the web report cannot drift apart. The widening is THREE lines and they
 * land together: routes/api.php now carries
 * 'role:supervisor,mill_management,admin,operator', guardAccess() below
 * admits Operator, and resolveBusinessUnit() puts it in the MILL-BOUND
 * branch. The third is the one that matters — widening only the first two
 * would drop Operator into the unbound Admin branch, where a client-supplied
 * business_unit_id IS honoured, and an Operator could read any mill's
 * report. Same shape as screen-129/135/136/137/138.
 *
 * What did NOT widen: businessUnitOptions() still answers 403 for Operator
 * (the all-mills list is Admin's), authorizePeriod() still refuses another
 * mill's period with 403, and the WEB route /reports/storage-tank in
 * routes/web.php is untouched — Operator has no web UI at all.
 */
class StorageTankReportService
{
    /**
     * Export row ceiling, counted in EXPORTED LINES (= storage_tank_details
     * rows), not header records — one daily record carries up to 24
     * time-slot rows, so counting headers would sail straight past the real
     * limit. Same value as StorageTankRecordService::EXPORT_ROW_LIMIT.
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /** Label shown for a period whose station_type is NULL (covers every type). */
    public const ALL_STATION_TYPES_LABEL = PeriodService::ALL_STATION_TYPES_LABEL;

    /** Export formats this report understands. Anything else is 422. */
    public const SUPPORTED_FORMATS = ['csv', 'excel'];

    /**
     * THE stock column — weight in metric tons, per the process owner's
     * decision of 2026-09-25.
     *
     * calculated_volume_m3 and the sounding depths are NOT stock: they are
     * published as metrics in their own right but never feed opening /
     * closing / movement. All three families can be filled independently and
     * can disagree; picking one and saying so is the whole point.
     */
    public const STOCK_COLUMN = 'calculated_weight_mt';

    /**
     * THE average-temperature column, read VERBATIM from what the Operator
     * recorded. Never derived from the three positional temperatures below
     * it — see the class docblock.
     */
    public const AVERAGE_TEMPERATURE_COLUMN = 'average_temperature_c';

    /**
     * The TEN numeric metrics `metrics` publishes, in order: the four oil
     * quality figures first (they share the quality trend chart), then the
     * Operator-recorded average temperature, then the two calculated stock
     * figures, then the three positional temperatures.
     *
     * Every one of them is nullable, and every one of them is averaged over
     * its OWN non-null rows with its OWN reading_count — see metricsOf().
     *
     * DELIBERATELY ABSENT, and the absence is asserted:
     *   - steam_heating_valve_status (enum) and the three free-text columns
     *     tank_structural_condition / inspector_name / findings are NEVER
     *     aggregated into min/avg/max. They appear only in the export. They
     *     ARE part of StorageTankRecordService::READING_FIELDS, so a row
     *     carrying nothing but a finding still counts as a filled slot for
     *     coverage while contributing to no metric — which is correct:
     *     something WAS recorded in that slot.
     *   - cpo_sounding_depth_mm / water_dip_bottom_depth_mm /
     *     net_oil_depth_mm are raw sounding inputs, not reported figures;
     *     the `metrics` key list is asserted exactly, so adding them would
     *     be a breaking change rather than a bonus.
     *
     * @var array<int, string>
     */
    public const NUMERIC_METRICS = [
        'ffa_percent',
        'moisture_content_percent',
        'impurities_dirt_percent',
        'dobi_index',
        'average_temperature_c',
        'calculated_weight_mt',
        'calculated_volume_m3',
        'oil_temperature_top_c',
        'oil_temperature_middle_c',
        'oil_temperature_bottom_c',
    ];

    /**
     * How many leading EXPORT_HEADER columns are RECORD CONTEXT, repeated
     * verbatim on every one of that record's time-slot lines. Published as a
     * constant so the export shape is derived, never counted by hand.
     */
    public const EXPORT_CONTEXT_COLUMN_COUNT = 4;

    /**
     * Export column headers — context columns first (repeated on every
     * line), then the time slot, then ALL SEVENTEEN non-time_slot columns in
     * StorageTankRecordService::READING_FIELDS order, INCLUDING the steam
     * valve enum and the three text columns, verbatim.
     *
     * 4 context + 1 slot + 17 readings = 22 columns.
     *
     * @var array<int, string>
     */
    public const EXPORT_HEADER = [
        'Tanggal',
        'Tangki',
        'Status',
        'Catatan',
        'Slot Waktu',
        'Kedalaman Sounding CPO (mm)',
        'Kedalaman Water Dip Bottom (mm)',
        'Kedalaman Minyak Bersih (mm)',
        'Suhu Minyak Atas (C)',
        'Suhu Minyak Tengah (C)',
        'Suhu Minyak Bawah (C)',
        'Suhu Rata-rata (C)',
        'Volume Terhitung (m3)',
        'Berat Terhitung (MT)',
        'FFA (%)',
        'Kadar Air (%)',
        'Kotoran (%)',
        'DOBI',
        'Status Katup Pemanas Uap',
        'Kondisi Struktur Tangki',
        'Nama Inspektur',
        'Temuan',
    ];

    /**
     * code => name from the `station_types` master table, memoised per
     * service instance (one request / one Livewire render).
     *
     * @var array<string, string>|null
     */
    protected ?array $stationTypeNames = null;

    protected ?StorageTankRecordService $recordService = null;

    // ------------------------------------------------------------------
    // Access
    // ------------------------------------------------------------------

    /**
     * business_logic step 1 — which mill the caller is allowed to look at.
     *
     * Operator / Supervisor / Mill Management: ALWAYS their own
     * business_unit_id; the `business_unit_id` argument is ignored outright,
     * so probing another mill's id is a no-op that still returns the
     * caller's own data with HTTP 200.
     *
     * Admin: the value MUST come from the caller. Missing is 422
     * VALIDATION_ERROR with errors.business_unit_id — never a silent null
     * and never an empty result set, which would read as "this mill has no
     * data".
     *
     * OPERATOR JOINED THE MILL-BOUND BRANCH ON 2026-09-25, together with the
     * guardAccess() widening for screen-139 (the mobile Storage Tank report,
     * which reuses these very endpoints). THE TWO CHANGES ARE ONE CHANGE,
     * NEVER TWO: admitting Operator in guardAccess() alone would drop it
     * through to the Admin branch below, where the client's
     * business_unit_id is HONOURED, and an Operator could then read any
     * mill's report by naming it. That is a cross-mill leak, not a display
     * defect, and the 422 fail-closed check inside this branch now protects
     * Operator too.
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
     * Mill picker options — ADMIN ONLY. Operator, Supervisor and Mill
     * Management are bound to a single mill and have no picker at all, so
     * asking for this list is a 403 rather than a filtered list of one.
     *
     * NOT touched by the screen-139 Operator widening, on purpose: the
     * report itself opened up, the list of every mill did not. The refusal
     * is raised HERE rather than by the route middleware, so it carries
     * code = 'FORBIDDEN' through ApiExceptionHandler.
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
     * and the caller is mill-bound (Operator / Supervisor / Mill
     * Management) — THIS is the real cross-mill leak path, so unlike the
     * ignored business_unit_id query param it is refused outright. Admin
     * passes for any mill.
     *
     * NO CHANGE WAS NEEDED HERE FOR THE screen-139 OPERATOR WIDENING: only
     * Admin is treated as unbound below, so Operator was closed out of other
     * mills' periods the moment it entered the mill-bound branch of
     * resolveBusinessUnit().
     *
     * THE ROLE GUARD RUNS BEFORE THE LOOKUP, on purpose and proven by the
     * exception TYPE: a caller who is refused outright must not be able to
     * learn whether a period id exists by comparing a 403 against a 404.
     * Since the widening that no longer describes Operator: it IS an
     * admitted caller now, so a 404 over a genuinely unknown id leaks
     * nothing. The CROSS-MILL oracle still matters, and still holds —
     * another mill's period answers 403, never 404.
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
     * A period covers Storage Tank when its station_type is 'storage-tank'
     * OR NULL (NULL = the period applies to every station type in that
     * mill). Newest first. An empty array is a valid answer — a mill with no
     * period yet gets [] with HTTP 200 and a UI hint pointing at Kelola
     * Periode Pelaporan, never a 404 and never an exception.
     *
     * A CLOSED period is listed exactly like an open one: the period lock
     * governs writing data, not reading a report.
     *
     * The mill is resolved here rather than by the caller so that calling
     * listPeriods() as an Admin without a mill is the documented 422, and
     * calling it as a bound role with someone else's id still reads the
     * caller's own mill.
     *
     * @return list<array{id: string, name: string, start_date: string, end_date: string, status: string, station_type: string|null, station_type_label: string}>
     */
    public function listPeriods(?string $businessUnitId = null): array
    {
        $businessUnitId = $this->resolveBusinessUnit($businessUnitId);

        return Period::query()
            ->where('business_unit_id', $businessUnitId)
            ->where(function (Builder $query) {
                $query->where('station_type', StationTypeEnum::StorageTank->value)
                    ->orWhereNull('station_type');
            })
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get()
            ->map(fn (Period $period) => $this->periodOption($period))
            ->all();
    }

    /**
     * business_logic steps 4-19 — every figure on the screen for one
     * period: the period header, recording coverage, the opening / closing /
     * movement stock block WITH the timestamps of the two readings each
     * figure came from, the ten metrics each with its own denominator, the
     * per-tank recap, the daily recap/trend, and the period totals.
     *
     * Membership is decided by storage_tank_records.date — the date the
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
        // "Reading rows" ARE the FILLED rows. A detail row whose seventeen
        // non-time_slot columns are all null is an untouched slot, already
        // reported as missing by coverage.
        $filledRows = $rows->filter(fn ($row) => $row->filled)->values();

        $tanks = $this->tanksOf($records);
        $dates = $this->datesOf($records);

        $daysInPeriod = $this->daysInPeriod($period);
        $daysWithRecords = $dates->count();
        $filledSlots = $filledRows->count();
        $tankCount = $tanks->count();
        // Slots per tank per day comes from the canonical time-slot grid the
        // input screens themselves use
        // (StorageTankRecordService::canonicalTimeSlots()), not from a number
        // invented here — one definition, one answer.
        $slotsPerTankPerDay = count(StorageTankRecordService::canonicalTimeSlots());
        $expectedSlots = $tankCount * $daysInPeriod * $slotsPerTankPerDay;

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
            // RECORDING COVERAGE IS PART OF THE REPORT, NOT METADATA. On
            // this screen it says how far apart in time the two compared
            // readings actually sit, so the screen renders it ABOVE every
            // other figure.
            'coverage' => [
                'filled_slots' => $filledSlots,
                'expected_slots' => $expectedSlots,
                // Two decimals, not one: 3 of 240 is 1.25%, and rounding it
                // to 1.3 loses the only digit that distinguishes a badly
                // recorded period from a catastrophically recorded one.
                'coverage_percent' => $expectedSlots === 0
                    ? 0.0
                    : round(100 * $filledSlots / $expectedSlots, 2),
                'tank_count' => $tankCount,
                'slots_per_tank_per_day' => $slotsPerTankPerDay,
                'days_in_period' => $daysInPeriod,
            ],
            'stock' => $this->stockOf($tanks),
            'metrics' => $this->metricsOf($filledRows),
            'by_tank' => $this->byTankOf($tanks),
            'daily' => $this->dailyOf($dates),
            'total' => [
                'days_with_records' => $daysWithRecords,
                'reading_rows' => $filledSlots,
            ],
        ];
    }

    /**
     * Repo-convention alias of buildSummary(), so this service reads the
     * same way as BoilerRoomReportService::summary() and
     * ClarificationReportService::summary() at the call sites. One
     * implementation, two names — never two implementations.
     */
    public function summary(Period|string|null $period = null, ?string $requestedBusinessUnitId = null): array
    {
        return $this->buildSummary($period, $requestedBusinessUnitId);
    }

    /**
     * business_logic step 20 — ONE EXPORTED LINE PER TIME SLOT
     * (storage_tank_details row), with the record's context columns (date /
     * tank / status / note / checked-by / acknowledged-by) repeated on every
     * line so the file can be pivoted directly in a spreadsheet. Same
     * convention as the 18 station exports (commit 8611974), scoped to a
     * period.
     *
     * All SEVENTEEN non-time_slot columns are emitted, INCLUDING the steam
     * valve enum and the three text columns — verbatim, with no rounding.
     * The export is the ONLY place those four appear: they are never
     * aggregated into min/avg/max anywhere in the summary.
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

        $detailRowCount = StorageTankDetail::query()
            ->whereIn('storage_tank_record_id', (clone $recordQuery)->select('storage_tank_records.id'))
            ->count();

        // Strictly greater than: exactly EXPORT_ROW_LIMIT rows still export.
        if ($detailRowCount > self::EXPORT_ROW_LIMIT) {
            throw new ExportFailedException;
        }

        return $this->streamExportRows($recordQuery);
    }

    /**
     * business_logic step 20 — the streamed file around buildExportRows().
     *
     * SIGNATURE NOTE: the third parameter is the requested business unit,
     * matching CagesTrackReportService / SterilizerReportService /
     * BoilerRoomReportService / ClarificationReportService exactly, and
     * resolveBusinessUnit() IS called on the export path (through
     * buildExportRows()) rather than only on the summary path. All five
     * station report services have been uniform on this since 2026-09-25;
     * an export that skipped the mill resolution would be the one path where
     * an Admin with no mill selected silently got a file.
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

        // Runs the guard + the mill resolution + the row-limit check NOW,
        // before a single byte of the response is committed — a refused
        // export must never begin streaming.
        $rows = $this->buildExportRows($resolvedPeriod, $requestedBusinessUnitId);

        try {
            [$contentType, $filename] = $this->fileMetaFor($format, $resolvedPeriod);

            return response()->streamDownload(function () use ($rows) {
                // A failure WHILE writing is still EXPORT_FAILED (422), not a
                // half-written file reported as a success.
                try {
                    $handle = fopen('php://output', 'w');

                    // Explicit $separator/$enclosure/$escape — PHP 8.4
                    // deprecates relying on fputcsv()'s default $escape.
                    fputcsv($handle, self::EXPORT_HEADER, ',', '"', '\\');

                    foreach ($rows as $row) {
                        fputcsv($handle, $row, ',', '"', '\\');
                    }

                    fclose($handle);
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
    // Deliberate, and doubly so on this screen. First, SQL aggregate
    // behaviour over NULLABLE columns is NOT the same in SQLite (used by the
    // test suite) and PostgreSQL (production), and the separate-denominator
    // and null-is-not-zero rules are exactly what gets lost in that
    // difference. Second — and decisively here — picking each tank's FIRST
    // and LAST stock reading is ORDERED logic that a plain SQL aggregate
    // cannot express at all: MIN()/MAX() would answer a different question
    // (the smallest and largest value) that happens to look like the right
    // one. No avg()/sum()/min()/max()/group by is issued at the SQL layer
    // anywhere below; the queries only ever select raw rows, filter them, and
    // order them. The row set is bounded by one reporting period; the export
    // path — the only unbounded one — streams in chunks instead.
    // ------------------------------------------------------------------

    /**
     * Every Storage Tank record inside the period, flattened to plain
     * objects with their time-slot detail rows attached.
     *
     * ONE DATE CAN HAVE SEVERAL RECORDS — one per tank, because
     * storage_tank_id is a STRING column on the HEADER, not a foreign key.
     * The per-tank aggregation splits them apart; the per-date aggregation
     * merges them.
     *
     * @return Collection<int, object>
     */
    protected function recordsFor(Period $period): Collection
    {
        return $this->recordQueryFor($period)
            // time_slot is a TIME column and is ordered AS THE TIME VALUE IT
            // IS. It is never cast to an integer hour and no slot is assumed
            // to fall exactly on the hour — casting would collapse 06:00 and
            // 06:30 into one slot and would reorder 00:30 against 10:00.
            //
            // This ORDER BY is a convenience for the export and for reading
            // the query log; NOTHING below relies on it. Every ordering that
            // matters is re-established explicitly in PHP by sortKey(), over
            // (date, time_slot) — precisely because database storage order is
            // not guaranteed and the opening/closing rule would fail
            // silently if it were trusted.
            ->with(['storageTankDetails' => fn ($query) => $query->orderBy('time_slot')])
            ->orderBy('storage_tank_records.date')
            ->orderBy('storage_tank_records.storage_tank_id')
            ->get()
            ->map(fn (StorageTankRecord $record) => (object) [
                'date' => $this->dateStringOf($record->date),
                'storage_tank_id' => (string) ($record->storage_tank_id ?? ''),
                'status' => $this->recordStatusValue($record),
                'note' => $record->note,
                'rows' => $record->storageTankDetails
                    ->map(fn (StorageTankDetail $detail) => $this->rowOf($detail, $record))
                    ->values(),
            ])
            ->values();
    }

    /**
     * One detail row, reduced to exactly what the aggregation needs.
     *
     * `filled` REUSES StorageTankRecordService::isRowFilled() over its
     * READING_FIELDS — the definition the input screens already enforce.
     * Writing a second "is this row filled?" rule here is how the report and
     * the form start disagreeing about what was recorded. That is also why
     * the seventeen column names are not copied into this class.
     *
     * `sort_key` is what every ordering in this service is built on:
     * "<date> <HH:MM>", so (date, time_slot) ascending is one string
     * comparison and cannot silently degrade into storage order.
     */
    protected function rowOf(StorageTankDetail $detail, StorageTankRecord $record): object
    {
        $attributes = $detail->only(StorageTankRecordService::READING_FIELDS);

        $values = [];

        foreach (self::NUMERIC_METRICS as $metric) {
            $raw = $detail->{$metric};
            // NULL STAYS NULL. It is never coerced to 0.0 — a missing
            // reading must not be able to drag an average down, and a
            // missing STOCK reading must not be able to look like an empty
            // tank.
            $values[$metric] = $raw === null || $raw === '' ? null : (float) $raw;
        }

        $date = $this->dateStringOf($record->date);
        $timeSlot = $this->timeSlotValue($detail->time_slot);

        return (object) [
            'date' => $date,
            'storage_tank_id' => (string) ($record->storage_tank_id ?? ''),
            'time_slot' => $timeSlot,
            // The instant this reading stands for, as the payload publishes
            // it: "Y-m-d H:i". Also the sort key — chronological order and
            // the published timestamp can never drift apart.
            'at' => $date.' '.$timeSlot,
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
     * THE HEART OF THIS REPORT: one entry per TANK (storage_tank_id), with
     * its opening and closing stock chosen BY TIME, not by value.
     *
     * For each tank:
     *   - every row it has, ordered by (date, time_slot) ASCENDING —
     *     explicitly, in PHP, never trusting database storage order;
     *   - the FIRST of those rows whose calculated_weight_mt is non-null is
     *     the opening, the LAST is the closing. A tank whose earliest
     *     reading left the stock column empty therefore opens at its next
     *     FILLED reading, and opening_at points at that reading — rule 3 of
     *     the class docblock falls out of this ordering for free rather than
     *     needing a special case;
     *   - movement = closing - opening, and it is COMPUTABLE only when the
     *     tank has AT LEAST TWO stock readings. One reading means opening
     *     and closing are the same row: movement is null and
     *     movement_computable is false — NOT 0, which would claim the stock
     *     did not change;
     *   - a tank with a record but no stock reading at all STILL APPEARS,
     *     with its real reading_count and nulls for stock. Dropping it would
     *     hide exactly the tank that was never measured.
     *
     * @param  Collection<int, object>  $records
     * @return Collection<int, object>
     */
    protected function tanksOf(Collection $records): Collection
    {
        return $records
            ->groupBy('storage_tank_id')
            ->sortKeys()
            ->map(function (Collection $group, string $storageTankId) {
                $rows = $group->flatMap(fn ($record) => $record->rows)->values();
                $filled = $rows->filter(fn ($row) => $row->filled)->values();

                // EXPLICIT chronological ordering. This is the one line the
                // whole screen rests on, and it is the reason the unit-test
                // fixtures insert their rows in a deliberately scrambled id
                // order: an implementation that leaned on the query's
                // default ordering would pass every test written against
                // tidy fixtures and be wrong in production.
                $stockRows = $rows
                    ->filter(fn ($row) => $row->values[self::STOCK_COLUMN] !== null)
                    ->sortBy(fn ($row) => $row->at)
                    ->values();

                $opening = $stockRows->first();
                $closing = $stockRows->last();

                // TWO readings minimum. With one, opening and closing are
                // the same row and the difference between them is not a
                // measurement of anything.
                $computable = $stockRows->count() >= 2;

                $movement = $computable
                    ? round(
                        (float) $closing->values[self::STOCK_COLUMN] - (float) $opening->values[self::STOCK_COLUMN],
                        2
                    )
                    : null;

                return (object) [
                    'storage_tank_id' => $storageTankId,
                    'reading_count' => $filled->count(),
                    'opening_mt' => $opening === null ? null : round((float) $opening->values[self::STOCK_COLUMN], 2),
                    'opening_at' => $opening?->at,
                    'closing_mt' => $closing === null ? null : round((float) $closing->values[self::STOCK_COLUMN], 2),
                    'closing_at' => $closing?->at,
                    // A NEGATIVE VALUE SURVIVES UNTOUCHED: no clamping, no
                    // abs(), no flag. Oil leaving the tank is the ordinary
                    // case, not an error.
                    'movement_mt' => $movement,
                    'movement_computable' => $computable,
                    'averages' => $this->bucketAveragesOf($filled),
                ];
            })
            ->values();
    }

    /**
     * The period-level stock block, assembled ENTIRELY from the per-tank
     * figures above.
     *
     * opening_mt / closing_mt are the SUM of each tank's own opening /
     * closing. opening_at / closing_at are the EARLIEST opening and the
     * LATEST closing across the tanks, so the card says which two instants
     * the period figures actually span.
     *
     * movement_mt IS THE SUM OF THE PER-TANK MOVEMENTS — deliberately NOT
     * closing_mt - opening_mt. The two differ exactly when the set of tanks
     * recorded at the start differs from the set recorded at the end, and in
     * that case the subtraction answers a different question: it adds the
     * arrival of a newly-recorded tank to the movement of the others and
     * calls the result "net movement". A tank whose movement is not
     * computable contributes NOTHING here.
     *
     * Every figure is null — never 0.0 — when nothing feeds it.
     *
     * @param  Collection<int, object>  $tanks
     * @return array<string, float|int|string|null>
     */
    protected function stockOf(Collection $tanks): array
    {
        $openings = $tanks->filter(fn ($tank) => $tank->opening_mt !== null);
        $closings = $tanks->filter(fn ($tank) => $tank->closing_mt !== null);
        $movements = $tanks->filter(fn ($tank) => $tank->movement_computable && $tank->movement_mt !== null);

        return [
            'opening_mt' => $openings->isEmpty() ? null : round($openings->sum('opening_mt'), 2),
            'opening_at' => $openings->isEmpty() ? null : $openings->pluck('opening_at')->min(),
            'closing_mt' => $closings->isEmpty() ? null : round($closings->sum('closing_mt'), 2),
            'closing_at' => $closings->isEmpty() ? null : $closings->pluck('closing_at')->max(),
            'movement_mt' => $movements->isEmpty() ? null : round($movements->sum('movement_mt'), 2),
            'tanks_with_movement' => $movements->count(),
            // Every tank in by_tank that is NOT counted above: the
            // single-reading ones AND the ones with no stock reading at all.
            // Both are "no computable movement", and neither is zero.
            'tanks_without_movement' => $tanks->count() - $movements->count(),
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
     * producing a number that looks entirely reasonable. DOBI in particular
     * is not measured every slot.
     *
     * A metric never filled in the whole period returns null/null/null with
     * reading_count 0 — NOT 0/0/0 — and does so independently of every
     * other metric: an empty impurities column leaves FFA untouched.
     *
     * average_temperature_c is one of these ten and nothing more: it is read
     * from its own column and is NEVER derived from the three positional
     * temperatures, which are three further independent metrics.
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
     * Each metric averaged over one bucket (a date or a tank) — each, again,
     * with ITS OWN denominator inside that bucket.
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
     * One aggregate per DATE that has at least one record, ascending,
     * merging every tank that was recorded on that date.
     *
     * A date whose rows are all empty for a given metric yields null in that
     * column but STILL COUNTS as a date with records — dropping it would
     * make the period look better recorded than it was. A date with no
     * record at all gets no entry: a padded zero row would read as "we
     * measured zero" when nothing was measured.
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
                    'stock_total_mt' => $this->dailyStockTotalOf($rows),
                ];
            })
            ->values();
    }

    /**
     * The combined stock of every tank ON ONE DATE — and, like the period
     * figures, a COMPARISON rather than a sum of readings: for each tank the
     * LAST stock reading it has on that date (its state at the end of the
     * day), and those per-tank states summed.
     *
     * Averaging a tank's readings across the day, or summing all of them,
     * would both inflate a tank that happened to be read more often — the
     * same confusion between "how much was there" and "how many times we
     * looked" that rule 1 exists to prevent.
     *
     * null — never 0.0 — on a date where no tank recorded any stock.
     *
     * @param  Collection<int, object>  $rows
     */
    protected function dailyStockTotalOf(Collection $rows): ?float
    {
        $perTank = $rows
            ->filter(fn ($row) => $row->values[self::STOCK_COLUMN] !== null)
            ->sortBy(fn ($row) => $row->at)
            ->groupBy('storage_tank_id')
            ->map(fn (Collection $tankRows) => (float) $tankRows->last()->values[self::STOCK_COLUMN]);

        return $perTank->isEmpty() ? null : round($perTank->sum(), 2);
    }

    /**
     * The per-tank recap table — the table the period movement figure is
     * literally the column sum of, which is why it ships with every figure
     * the card shows plus the two timestamps.
     *
     * @param  Collection<int, object>  $tanks
     * @return list<array>
     */
    protected function byTankOf(Collection $tanks): array
    {
        return $tanks
            ->map(fn ($tank) => [
                'storage_tank_id' => $tank->storage_tank_id,
                'reading_count' => $tank->reading_count,
                'opening_mt' => $tank->opening_mt,
                'opening_at' => $tank->opening_at,
                'closing_mt' => $tank->closing_mt,
                'closing_at' => $tank->closing_at,
                'movement_mt' => $tank->movement_mt,
                'movement_computable' => $tank->movement_computable,
                'ffa_avg' => $tank->averages['ffa_percent'],
                // From the Operator's own column, with its OWN denominator —
                // never recomputed from the three positional temperatures.
                'average_temperature_avg' => $tank->averages[self::AVERAGE_TEMPERATURE_COLUMN],
            ])
            ->values()
            ->all();
    }

    /**
     * The daily recap / daily trend table.
     *
     * FFA, MOISTURE AND DOBI SHIP ON THE SAME ROW per date, which is the
     * whole point: the screen draws them on ONE chart because what says the
     * oil is deteriorating is the three of them moving TOGETHER, not one of
     * them alone.
     *
     * Their scales are NOT comparable — DOBI runs about 2-4 with no unit,
     * FFA about 3-5%, moisture about 0.1-0.3% — so the screen MUST normalise
     * them (or use a second axis) and MUST SAY SO in the legend. Pinning
     * three different scales to one axis without a word makes two of the
     * three render as flat lines at the bottom and look as though nothing
     * changed. The payload stays raw; the presentation decision belongs to
     * the screen, and the raw values remain in this same table underneath
     * the chart so the normalisation hides nothing.
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
                'stock_total_mt' => $row->stock_total_mt,
                'ffa_avg' => $row->averages['ffa_percent'],
                'moisture_avg' => $row->averages['moisture_content_percent'],
                'impurities_avg' => $row->averages['impurities_dirt_percent'],
                'dobi_avg' => $row->averages['dobi_index'],
                'temperature_avg' => $row->averages[self::AVERAGE_TEMPERATURE_COLUMN],
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
     * Ordered by date, then by tank, then by time_slot — and time_slot is
     * compared as the TIME value it is, never as an integer hour.
     *
     * @return Generator<int, array<int, string|float|null>>
     */
    protected function streamExportRows(Builder $recordQuery): Generator
    {
        $query = (clone $recordQuery)
            ->with(['storageTankDetails' => fn ($detailQuery) => $detailQuery->orderBy('time_slot')])
            ->orderBy('storage_tank_records.date')
            ->orderBy('storage_tank_records.storage_tank_id')
            ->orderBy('storage_tank_records.id');

        foreach ($query->lazy(200) as $record) {
            /** @var StorageTankRecord $record */
            // FOUR context columns — tanggal, tangki, status, catatan — and
            // deliberately no more. This is the spec's exact list, and it is
            // the same four the four sibling report services (Sterilizer,
            // Cages & Track, Boiler Room, Clarification) repeat on every line.
            // The checker / acknowledger user columns are intentionally
            // absent: they hold raw user UUIDs, which mean nothing to a
            // spreadsheet reader, and no sibling export carries them.
            $context = [
                optional($record->date)->toDateString(),
                $record->storage_tank_id,
                $this->recordStatusValue($record),
                $record->note,
            ];

            foreach ($record->storageTankDetails as $detail) {
                /** @var StorageTankDetail $detail */
                $reading = [$this->timeSlotValue($detail->time_slot)];

                foreach (StorageTankRecordService::READING_FIELDS as $field) {
                    // VERBATIM — including the steam valve enum and the three
                    // text columns — with no rounding and no normalisation,
                    // exactly as the Operator typed it. This is the only
                    // place those four ever appear.
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
     * Base query over the period's Storage Tank records (header rows).
     *
     * Scoped through `stations` to the PERIOD'S business unit and to the
     * storage-tank station type, and bounded INCLUSIVELY on
     * storage_tank_records.date — the event date, not created_at and not
     * the sync time. storage_tank_records carries neither period_id nor
     * business_unit_id, so the join is the only way to scope it.
     */
    protected function recordQueryFor(Period $period): Builder
    {
        return StorageTankRecord::query()
            ->join('stations', 'stations.id', '=', 'storage_tank_records.station_id')
            ->where('stations.business_unit_id', $period->business_unit_id)
            ->where('stations.type', StationTypeEnum::StorageTank->value)
            ->whereDate('storage_tank_records.date', '>=', $period->start_date->toDateString())
            ->whereDate('storage_tank_records.date', '<=', $period->end_date->toDateString())
            ->select('storage_tank_records.*');
    }

    /**
     * Session + role gate shared by every entry point.
     *
     * This gate sits two layers deeper than the route middleware on
     * purpose: clearing the middleware must never be enough by itself. That
     * is why widening a role here and widening routes/api.php are always one
     * change, never two — the lesson from screen-129/135.
     *
     * OPERATOR IS ADMITTED SINCE 2026-09-25 (screen-139, the mobile Storage
     * Tank report, which reuses these endpoints rather than getting its
     * own). Admitting it HERE is only half the widening: without also adding
     * it to the mill-bound branch of resolveBusinessUnit() it would fall
     * through to the Admin branch, pass its own business_unit_id and read
     * any mill's report. Those two lines land together or not at all.
     *
     * businessUnitOptions() is deliberately NOT part of the widening and
     * still answers 403 for Operator: a role bound to one mill has no
     * picker, and the all-mills list is precisely what it must not see.
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
     * time_slot as hour-and-minute, the way it is stored and the way the
     * export writes it: "06:00".
     *
     * The column is a native TIME, so a driver may hand back "06:00:00" or
     * "06:00"; both normalise to the same five characters here. It is NEVER
     * turned into an integer hour and never widened into a full datetime —
     * casting to an integer would collapse 06:00 and 06:30 into one slot and
     * would sort 00:30 after 10:00.
     */
    protected function timeSlotValue(mixed $timeSlot): string
    {
        $value = trim((string) $timeSlot);

        if ($value === '') {
            return '';
        }

        return substr($value, 0, 5);
    }

    protected function recordService(): StorageTankRecordService
    {
        return $this->recordService ??= app(StorageTankRecordService::class);
    }

    /**
     * @return array{id: string, name: string, start_date: string, end_date: string, status: string, station_type: string|null, station_type_label: string}
     */
    protected function periodOption(Period $period): array
    {
        return [
            'id' => (string) $period->id,
            'name' => (string) $period->name,
            'start_date' => $period->start_date->toDateString(),
            'end_date' => $period->end_date->toDateString(),
            'status' => $this->statusValue($period),
            'station_type' => $period->station_type,
            'station_type_label' => $this->stationTypeLabel($period->station_type),
        ];
    }

    protected function statusValue(Period $period): string
    {
        return is_object($period->status) ? $period->status->value : (string) $period->status;
    }

    protected function recordStatusValue(StorageTankRecord $record): string
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
    protected function stationTypeLabel(?string $code): string
    {
        if ($code === null) {
            return self::ALL_STATION_TYPES_LABEL;
        }

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
                "laporan-storage-tank_{$slug}_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "laporan-storage-tank_{$slug}_{$timestamp}.csv",
        ];
    }
}
