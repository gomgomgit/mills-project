<?php

namespace App\Services;

use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BoilerRoomDetail;
use App\Models\BoilerRoomRecord;
use App\Models\BusinessUnit;
use App\Models\Period;
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
 * BoilerRoomReportService — screen-131--laporan-boiler-room-web /
 * usecase-131--laporan-boiler-room-web (Laporan Periode Boiler Room).
 *
 * Shared by the API controller (App\Http\Controllers\Api\
 * BoilerRoomReportController) and the Livewire component (App\Livewire\
 * Dashboard\LaporanBoilerRoom), the same split used by
 * CagesTrackReportService / CagesTrackReportController /
 * LaporanCagesTrack — so the web page and the API can never disagree on a
 * figure.
 *
 * READ-ONLY BY CONSTRUCTION: every public method is a SELECT, and the
 * /api/boiler-room-reports prefix carries no POST/PUT/PATCH/DELETE. A
 * report must never be able to mutate the data it reports on.
 *
 * ------------------------------------------------------------------
 * WHAT IS DIFFERENT FROM THE CAGES & TRACKS REPORT — and why
 * ------------------------------------------------------------------
 * Cages & Tracks counts OUTPUT ("how much was tipped, and when"). Boiler
 * Room records CONDITION ("how stable was it") — steam pressure and
 * temperature, feed-water quality, flue-gas temperature, and which
 * maintenance was actually carried out. That flips five rules, each of
 * which fails SILENTLY (a plausible-looking number, not an error) and each
 * of which has its own unit test:
 *
 *  1. A SEPARATE DENOMINATOR PER METRIC. All nine numeric columns are
 *     nullable and a detail row counts as FILLED when at least one of its
 *     15 non-time_slot columns is filled, so one row can carry a pressure
 *     reading and no pH reading at all. Every metric is therefore averaged
 *     over ONLY the rows where THAT column is non-null, and carries its own
 *     reading_count. 10 rows with pressure and 4 with pH (6.0/7.0/7.0/8.0)
 *     must give avg pH 7.0 (28/4), never 2.8 (28/10) — and 2.8 is exactly
 *     the kind of number nobody questions.
 *  2. THE EXTREMES COME FROM RAW READINGS, not from daily averages. A day
 *     whose two readings are 12.0 and 28.0 has a daily average of 20.0; the
 *     card still reports min 12.0 / max 28.0 while the daily recap column
 *     still reports 20.0. THE TWO DIFFER ON PURPOSE — the pressure that
 *     dropped to 12 bar for one slot is the event worth seeing, and it is
 *     invisible in the day's average. Because the numbers cannot be
 *     reconciled against each other, the screen is REQUIRED to say so; see
 *     the note rendered next to the metric cards in
 *     livewire/dashboard/laporan-boiler-room.blade.php. Without that label
 *     a reader concludes the report is broken.
 *  3. MAINTENANCE HAS THREE STATES, not two. blowdown_executed and
 *     sootblowing_executed are NULLABLE enum('y','n'), so
 *     executed = COUNT('y'), not_executed = COUNT('n'),
 *     not_recorded = COUNT(NULL), and the three ALWAYS sum to the number of
 *     reading rows. Treating NULL as 'n' reports a maintenance lapse that
 *     never happened.
 *  4. NULL IS NOT ZERO. A metric that was never filled returns
 *     min/avg/max = null with reading_count 0 — not 0/0/0, which reads as
 *     "we measured it and it was zero". Independent per metric: one null
 *     metric never affects another.
 *  5. THE THREE FREE-TEXT COLUMNS (fuel_feed_rate, id_fan_load,
 *     sa_fan_load) are NEVER aggregated — no min/avg/max, no trend, no
 *     per-unit column. The units on the paper form are mixed (Hz/%/tons,
 *     A/%), which is why they are STRING columns in the schema; averaging
 *     them is impossible, not merely undesirable. They appear verbatim in
 *     the export and nowhere else.
 *
 * ------------------------------------------------------------------
 * THERE IS DELIBERATELY NO THRESHOLD FLAGGING ANYWHERE IN THIS REPORT
 * ------------------------------------------------------------------
 * No out-of-range marking, no severity, no safe/danger colouring, no
 * outlier detection. Boiler Room has NO operational-target master table —
 * there is no BoilerRoomOperationalTarget; only Threshing / Pressing /
 * Depricarping / Kernel Plant have one, and the class docblock of
 * BoilerRoomRecordService states that absence is deliberate (same scope
 * decision as Engine Room / Storage Tank / Effluent Plant).
 *
 * Deriving a threshold from the period's own data would produce a number
 * that LOOKS like a safety limit for a pressure vessel while being nothing
 * but a statistic about the very data it is judging. A boiler pressure
 * rendered in red would be read as a safety breach. What this report
 * publishes is min / avg / max / trend; the judgement belongs to a human.
 * This paragraph exists so that the omission is not "completed" later.
 *
 * ------------------------------------------------------------------
 * CROSS-MILL SECURITY IS CLOSED AT TWO DIFFERENT POINTS, on purpose
 * ------------------------------------------------------------------
 *   1. resolveBusinessUnit() IGNORES the client's business_unit_id for
 *      Supervisor / Mill Management / Operator — not validated, not
 *      compared, discarded. Probing another mill's id returns 200 with the
 *      CALLER'S OWN data, deliberately not a 403: a 403 would confirm the
 *      other mill exists, and there is no access attempt to refuse because
 *      the parameter is never used for those roles.
 *   2. authorizePeriod() REFUSES a period belonging to another mill with
 *      403. Here there IS a concrete handle on another mill's data, so it
 *      is refused outright rather than silently rewritten.
 * Folding these two into one uniform 403 is the mistake this class exists
 * to avoid.
 *
 * A mill-bound account whose users.business_unit_id is NULL FAILS CLOSED
 * with 422 and the whole-mill list is never even read — see
 * allBusinessUnits(), which is public and trivial precisely so a spy can
 * prove it was never called. That applies to Operator too.
 *
 * OPERATOR IS ACCEPTED SINCE 2026-09-25 (screen-137 — the mobile Boiler
 * Room report), mirroring the widening CagesTrackReportService received on
 * 2026-09-24 for screen-136 and SterilizerReportService on 2026-09-23 for
 * screen-135. Operator is treated exactly like Supervisor / Mill
 * Management: MILL-BOUND. Three sites changed in the same breath, and
 * skipping any one of them opens a hole:
 *   a. routes/api.php — the four /api/boiler-room-reports routes gained
 *      `operator` in the role list (the `sanctum` guard, which mobile needs
 *      because it carries a token rather than a session cookie, was already
 *      there);
 *   b. guardAccess() below — Operator added to the accepted-role list;
 *   c. resolveBusinessUnit() below — Operator added to the MILL-BOUND
 *      branch. Doing (b) WITHOUT (c) would have dropped Operator into the
 *      unbound Admin branch, where a client-supplied business_unit_id is
 *      honoured — i.e. an Operator could have read any mill's report. That
 *      is the single most important line of this widening.
 * authorizePeriod() needed NO change: only Admin is treated as unbound
 * there, so Operator was closed out of other mills' periods the moment it
 * stopped being Admin-like.
 * businessUnitOptions() needed NO change either: it stays ADMIN ONLY, so
 * Operator still gets 403 there.
 *
 * THE WIDENING STOPS AT THE API. The WEB route /reports/boiler-room and
 * App\Livewire\Dashboard\LaporanBoilerRoom::canAccess() deliberately stay
 * without Operator, which has no web UI at all — canAccess() keeps its own
 * role list precisely so that this service can widen without dragging the
 * web page along.
 */
class BoilerRoomReportService
{
    /**
     * Export row ceiling, counted in EXPORTED LINES (= boiler_room_details
     * rows), not header records — one daily record carries up to 24
     * time-slot rows, so counting headers would sail straight past the real
     * limit. Same trap that was fixed for the 18 station exports in commit
     * 8611974.
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /** Label shown for a period whose station_type is NULL (covers every type). */
    public const ALL_STATION_TYPES_LABEL = PeriodService::ALL_STATION_TYPES_LABEL;

    /** Export formats this report understands. Anything else is 422. */
    public const SUPPORTED_FORMATS = ['csv', 'excel'];

    /**
     * The NINE numeric measurement columns, in the order the screen reads
     * them: the five headline condition metrics first, then the four
     * supporting ones.
     *
     * Every one of them is nullable, and every one of them is averaged over
     * its OWN non-null rows — see metricsOf().
     *
     * The three free-text columns are deliberately absent from this list;
     * see FREE_TEXT_COLUMNS.
     */
    public const NUMERIC_METRICS = [
        'steam_pressure_bar',
        'steam_temp_c',
        'water_tds_ppm',
        'water_ph',
        'exhaust_gas_temp_c',
        'feed_water_temp_c',
        'feed_water_tank_level_percent',
        'boiler_water_level_percent',
        'dust_collector_differential_pressure_mmh2o',
    ];

    /**
     * NEVER AGGREGATED. Free-text string columns whose units are mixed on
     * the paper form (Hz/%/tons, A/%), which is why the schema stores them
     * as strings. They appear verbatim in the export and appear nowhere in
     * `metrics`, `daily` or `by_unit`. Listed here so the exclusion is
     * explicit rather than an omission someone "fixes".
     */
    public const FREE_TEXT_COLUMNS = [
        'fuel_feed_rate',
        'id_fan_load',
        'sa_fan_load',
    ];

    /** The two NULLABLE enum('y','n') maintenance columns — three states each. */
    public const MAINTENANCE_COLUMNS = [
        'blowdown_executed',
        'sootblowing_executed',
    ];

    /**
     * Export column headers — context columns first, repeated on every
     * line, then the time slot, then ALL FIFTEEN measurement columns in
     * BoilerRoomRecordService::READING_FIELDS order (including the three
     * free-text ones, verbatim).
     *
     * @var array<int, string>
     */
    public const EXPORT_HEADER = [
        'Tanggal',
        'Unit Boiler',
        'Status',
        'Catatan',
        'Slot Waktu',
        'Tekanan Uap (bar)',
        'Suhu Uap (C)',
        'Suhu Air Umpan (C)',
        'Level Tangki Air Umpan (%)',
        'Level Air Boiler (%)',
        'TDS Air (ppm)',
        'pH Air',
        'Laju Bahan Bakar',
        'Beban ID Fan',
        'Beban SA Fan',
        'Suhu Gas Buang (C)',
        'Beda Tekanan Dust Collector (mmH2O)',
        'Blowdown',
        'Sootblowing',
        'Temuan',
    ];

    /**
     * code => name from the `station_types` master table, memoised per
     * service instance (one request / one Livewire render).
     *
     * @var array<string, string>|null
     */
    protected ?array $stationTypeNames = null;

    protected ?BoilerRoomRecordService $recordService = null;

    // ------------------------------------------------------------------
    // Access
    // ------------------------------------------------------------------

    /**
     * business_logic step 1 — which mill the caller is allowed to look at.
     *
     * Supervisor / Mill Management / Operator: ALWAYS their own
     * business_unit_id; the `business_unit_id` argument is ignored
     * outright, so probing another mill's id is a no-op that still returns
     * the caller's own data with HTTP 200.
     *
     * Admin: the value MUST come from the caller. Missing is 422
     * VALIDATION_ERROR with errors.business_unit_id — never a silent null
     * and never an empty result set, which would read as "this mill has no
     * data".
     *
     * OPERATOR BELONGS IN THE MILL-BOUND BRANCH, and that placement is the
     * single most important line of the screen-137 widening (2026-09-25).
     * Admitting Operator in guardAccess() ALONE would let it fall through
     * to the Admin branch below, where the client's business_unit_id IS
     * honoured: an Operator could then read any mill's report — a
     * cross-mill leak, not a display defect. The 422 fail-closed inside the
     * bound branch applies to Operator too: an account with no mill gets
     * "Hubungi Admin", never the whole-mill list.
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
     * UNCHANGED by the screen-137 widening: Operator is admitted to the
     * prefix but still refused here, and the mobile view must therefore not
     * call this endpoint for a non-Admin.
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
     * THE ROLE GUARD RUNS BEFORE THE LOOKUP, on purpose: a role that is not
     * admitted at all (Operator) must not be able to learn whether a period
     * id exists by comparing a 403 against a 404.
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
     * A period covers Boiler Room when its station_type is 'boiler-room' OR
     * NULL (NULL = the period applies to every station type in that mill).
     * Newest first. An empty array is a valid answer — a mill with no
     * period yet gets [] with HTTP 200 and a UI hint pointing at Kelola
     * Periode Pelaporan, never a 404 and never an exception.
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
                $query->where('station_type', StationTypeEnum::BoilerRoom->value)
                    ->orWhereNull('station_type');
            })
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get()
            ->map(fn (Period $period) => $this->periodOption($period))
            ->all();
    }

    /**
     * business_logic steps 4-15 — every figure on the screen for one
     * period: the period header, recording coverage, the nine metrics with
     * their OWN reading counts, the two maintenance triples, the daily
     * recap/trend, the per-boiler-unit recap, and the period totals.
     *
     * Membership is decided by boiler_room_records.date — the date the
     * readings belong to — INCLUSIVE on both bounds, and never by
     * created_at or the mobile sync time. A row entered late still belongs
     * to the period it happened in.
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
        // "Reading rows" ARE the FILLED rows. A detail row whose 15
        // non-time_slot columns are all null is an untouched slot, already
        // reported as missing by coverage; counting it again as
        // "maintenance not recorded" would report the same emptiness twice.
        $filledRows = $rows->filter(fn ($row) => $row->filled)->values();

        $dates = $this->datesOf($records);
        $units = $this->unitsOf($records);

        $daysInPeriod = $this->daysInPeriod($period);
        $daysWithRecords = $dates->count();
        $filledSlots = $filledRows->count();
        $boilerUnitCount = $units->count();
        // Expected slots per unit per day comes from the canonical time-slot
        // grid the input screens themselves use
        // (BoilerRoomRecordService::canonicalTimeSlots()), not from a number
        // invented here — one definition, one answer.
        $slotsPerUnitPerDay = count(BoilerRoomRecordService::canonicalTimeSlots());
        $expectedSlots = $boilerUnitCount * $daysInPeriod * $slotsPerUnitPerDay;

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
            // RECORDING COVERAGE IS PART OF THE REPORT, NOT METADATA. A
            // period filled to 20% still produces tidy-looking averages, and
            // the reader must see that before trusting them — which is why
            // the screen renders this card ABOVE every other figure.
            'coverage' => [
                'filled_slots' => $filledSlots,
                'expected_slots' => $expectedSlots,
                'coverage_percent' => $expectedSlots === 0
                    ? 0.0
                    : round(100 * $filledSlots / $expectedSlots, 1),
                'boiler_unit_count' => $boilerUnitCount,
                'slots_per_unit_per_day' => $slotsPerUnitPerDay,
                'days_in_period' => $daysInPeriod,
            ],
            'metrics' => $this->metricsOf($filledRows),
            'maintenance' => $this->maintenanceOf($filledRows, $daysWithRecords),
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
     * same way as SterilizerReportService::summary() and
     * CagesTrackReportService::summary() at the call sites. One
     * implementation, two names — never two implementations.
     */
    public function summary(Period|string|null $period = null, ?string $requestedBusinessUnitId = null): array
    {
        return $this->buildSummary($period, $requestedBusinessUnitId);
    }

    /**
     * business_logic step 16 — ONE EXPORTED LINE PER TIME SLOT
     * (boiler_room_details row), with the record's context columns (date /
     * boiler unit / status / note) repeated on every line so the file can
     * be pivoted directly in a spreadsheet. Same convention as the 18
     * station exports (commit 8611974), scoped to a period.
     *
     * All FIFTEEN measurement columns are emitted, INCLUDING the three
     * free-text ones — verbatim, with no unit normalisation and no
     * rounding. The export is the only place they appear at all.
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

        $detailRowCount = BoilerRoomDetail::query()
            ->whereIn('boiler_room_record_id', (clone $recordQuery)->select('boiler_room_records.id'))
            ->count();

        // Strictly greater than: exactly EXPORT_ROW_LIMIT rows still export.
        if ($detailRowCount > self::EXPORT_ROW_LIMIT) {
            throw new ExportFailedException;
        }

        return $this->streamExportRows($recordQuery);
    }

    /**
     * business_logic step 16 — the streamed file around buildExportRows().
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
    // difference. No avg()/sum()/groupBy() is issued at the SQL layer
    // anywhere below; the queries only ever select raw rows, filter them,
    // and order them. The row set is bounded by one reporting period; the
    // export path — the only unbounded one — streams in chunks instead.
    // ------------------------------------------------------------------

    /**
     * Every Boiler Room record inside the period, flattened to plain
     * objects with their time-slot detail rows attached, ascending by
     * time_slot.
     *
     * ONE DATE CAN HAVE SEVERAL RECORDS — one per boiler unit, because
     * boiler_room_id is a STRING column on the HEADER, not a foreign key.
     * The per-date aggregation below merges them, and the per-unit
     * aggregation splits them apart again.
     *
     * @return Collection<int, object>
     */
    protected function recordsFor(Period $period): Collection
    {
        return $this->recordQueryFor($period)
            // time_slot is a TIME column: ordering uses the TIME value as
            // it stands. It is NOT an integer hour like Cages & Tracks'
            // tipped_hour, and casting it to one would collapse 06:00 and
            // 06:30 into the same slot.
            ->with(['boilerRoomDetails' => fn ($query) => $query->orderBy('time_slot')])
            ->orderBy('boiler_room_records.date')
            ->orderBy('boiler_room_records.boiler_room_id')
            ->get()
            ->map(fn (BoilerRoomRecord $record) => (object) [
                'date' => $this->dateStringOf($record->date),
                'boiler_room_id' => (string) ($record->boiler_room_id ?? ''),
                'status' => $this->recordStatusValue($record),
                'note' => $record->note,
                'rows' => $record->boilerRoomDetails
                    ->map(fn (BoilerRoomDetail $detail) => $this->rowOf($detail, $record))
                    ->values(),
            ])
            ->values();
    }

    /**
     * One detail row, reduced to exactly what the aggregation needs.
     *
     * `filled` REUSES BoilerRoomRecordService::isRowFilled() over its
     * READING_FIELDS — the definition the input screens already enforce.
     * Writing a second "is this row filled?" rule here is how the report
     * and the form start disagreeing about what was recorded.
     */
    protected function rowOf(BoilerRoomDetail $detail, BoilerRoomRecord $record): object
    {
        $attributes = $detail->only(BoilerRoomRecordService::READING_FIELDS);

        $values = [];

        foreach (self::NUMERIC_METRICS as $metric) {
            $raw = $detail->{$metric};
            // NULL STAYS NULL. It is never coerced to 0.0 — a missing
            // reading must not be able to drag an average down.
            $values[$metric] = $raw === null || $raw === '' ? null : (float) $raw;
        }

        return (object) [
            'date' => $this->dateStringOf($record->date),
            'boiler_room_id' => (string) ($record->boiler_room_id ?? ''),
            'time_slot' => (string) $detail->time_slot,
            'filled' => $this->recordService()->isRowFilled($attributes),
            'values' => $values,
            'blowdown_executed' => $this->enumValueOf($detail->blowdown_executed),
            'sootblowing_executed' => $this->enumValueOf($detail->sootblowing_executed),
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
     * THE HEART OF THIS REPORT: one entry per metric, each with ITS OWN
     * denominator.
     *
     * A metric is averaged over ONLY the rows where that column is
     * non-null, and reports how many rows that was. There is deliberately
     * no shared reading_count anywhere in the payload — a single shared
     * denominator would deflate every rarely-filled metric while still
     * producing a number that looks entirely reasonable.
     *
     * min and max come from these RAW READINGS, never from the daily
     * averages in `daily`. The two genuinely disagree, and the screen says
     * so; see the class docblock.
     *
     * A metric never filled in the whole period returns null/null/null with
     * reading_count 0 — NOT 0/0/0.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array<string, array{min: float|null, avg: float|null, max: float|null, reading_count: int}>
     */
    protected function metricsOf(Collection $filledRows): array
    {
        $metrics = [];

        foreach (self::NUMERIC_METRICS as $metric) {
            $values = $filledRows
                ->map(fn ($row) => $row->values[$metric])
                ->filter(fn ($value) => $value !== null)
                ->values()
                ->all();

            $metrics[$metric] = $this->statsOf($values);
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
     * THREE STATES PER MAINTENANCE COLUMN, and the invariant that proves it:
     *
     *   executed + not_executed + not_recorded === number of reading rows
     *
     * executed counts 'y'. not_executed counts 'n'. not_recorded counts
     * NULL. NULL NEVER increments not_executed — doing so would report a
     * maintenance lapse that never happened, on a station where "we did not
     * write it down" and "we did not do it" have completely different
     * consequences.
     *
     * all_unrecorded lets the screen distinguish a zero that means "never
     * done" from a zero that means "never written down"; without it both
     * render as the same 0.
     *
     * avg_per_day divides by the number of DAYS WITH RECORDS (business_logic
     * step 8), guarded against an empty period.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array<string, array{executed: int, not_executed: int, not_recorded: int, avg_per_day: float, all_unrecorded: bool}>
     */
    protected function maintenanceOf(Collection $filledRows, int $daysWithRecords): array
    {
        $maintenance = [];

        foreach (self::MAINTENANCE_COLUMNS as $column) {
            $executed = 0;
            $notExecuted = 0;
            $notRecorded = 0;

            foreach ($filledRows as $row) {
                $value = $row->{$column};

                if ($value === 'y') {
                    $executed++;
                } elseif ($value === 'n') {
                    $notExecuted++;
                } else {
                    // NULL (and only NULL) lands here.
                    $notRecorded++;
                }
            }

            // 'blowdown_executed' => 'blowdown'
            $key = str_replace('_executed', '', $column);

            $maintenance[$key] = [
                'executed' => $executed,
                'not_executed' => $notExecuted,
                'not_recorded' => $notRecorded,
                'avg_per_day' => $daysWithRecords === 0
                    ? 0.0
                    : round($executed / $daysWithRecords, 2),
                'all_unrecorded' => $filledRows->isNotEmpty() && $notRecorded === $filledRows->count(),
            ];
        }

        return $maintenance;
    }

    /**
     * One aggregate per DATE that has at least one record, ascending,
     * merging every boiler unit that ran on that date.
     *
     * A date whose rows are all empty for a given metric yields null in that
     * column but STILL COUNTS as a date with records — dropping it would
     * make the period look better recorded than it was. A date with no
     * record at all gets no entry: a padded zero row would read as "we
     * measured nothing" when the mill simply did not run.
     *
     * The *_avg columns here are DAILY averages, each with its own daily
     * denominator. They are NOT the source of metrics[].min/max; see the
     * class docblock for why the two deliberately differ.
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
                    'averages' => $this->dailyAveragesOf($filled),
                    'blowdown_executed' => $this->countExecuted($filled, 'blowdown_executed'),
                    'sootblowing_executed' => $this->countExecuted($filled, 'sootblowing_executed'),
                ];
            })
            ->values();
    }

    /**
     * One aggregate per BOILER UNIT (boiler_room_id) over every record in
     * the period.
     *
     * A unit that has a record but not one filled reading STILL APPEARS,
     * with reading_count 0 and null averages. Dropping it would hide
     * exactly the unit that was never written down — the one worth seeing.
     *
     * @param  Collection<int, object>  $records
     * @return Collection<int, object>
     */
    protected function unitsOf(Collection $records): Collection
    {
        return $records
            ->groupBy('boiler_room_id')
            ->sortKeys()
            ->map(function (Collection $group, string $boilerRoomId) {
                $rows = $group->flatMap(fn ($record) => $record->rows)->values();
                $filled = $rows->filter(fn ($row) => $row->filled)->values();

                return (object) [
                    'boiler_room_id' => $boilerRoomId,
                    'reading_count' => $filled->count(),
                    'averages' => $this->dailyAveragesOf($filled),
                    'blowdown_executed' => $this->countExecuted($filled, 'blowdown_executed'),
                    'sootblowing_executed' => $this->countExecuted($filled, 'sootblowing_executed'),
                ];
            })
            ->values();
    }

    /**
     * The five headline metrics averaged over one bucket (a date or a boiler
     * unit) — each, again, with ITS OWN denominator inside that bucket.
     *
     * null when the bucket has no value at all for that metric.
     *
     * The three free-text columns are absent here by construction: they are
     * not in NUMERIC_METRICS.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array<string, float|null>
     */
    protected function dailyAveragesOf(Collection $filledRows): array
    {
        $averages = [];

        foreach (self::NUMERIC_METRICS as $metric) {
            $values = $filledRows
                ->map(fn ($row) => $row->values[$metric])
                ->filter(fn ($value) => $value !== null)
                ->values()
                ->all();

            $averages[$metric] = $values === []
                ? null
                : round(array_sum($values) / count($values), 2);
        }

        return $averages;
    }

    /**
     * Count of rows whose maintenance column is 'y'. 'n' and NULL are both
     * excluded here — the three-state split lives in maintenanceOf().
     *
     * @param  Collection<int, object>  $filledRows
     */
    protected function countExecuted(Collection $filledRows, string $column): int
    {
        return $filledRows->filter(fn ($row) => $row->{$column} === 'y')->count();
    }

    /**
     * The daily recap / daily trend table.
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
                'steam_pressure_avg' => $row->averages['steam_pressure_bar'],
                'steam_temp_avg' => $row->averages['steam_temp_c'],
                'water_tds_avg' => $row->averages['water_tds_ppm'],
                'water_ph_avg' => $row->averages['water_ph'],
                'exhaust_gas_temp_avg' => $row->averages['exhaust_gas_temp_c'],
                'blowdown_executed' => $row->blowdown_executed,
                'sootblowing_executed' => $row->sootblowing_executed,
            ])
            ->values()
            ->all();
    }

    /**
     * The per-boiler-unit recap table.
     *
     * @param  Collection<int, object>  $units
     * @return list<array>
     */
    protected function byUnitOf(Collection $units): array
    {
        return $units
            ->map(fn ($row) => [
                'boiler_room_id' => $row->boiler_room_id,
                'reading_count' => $row->reading_count,
                'steam_pressure_avg' => $row->averages['steam_pressure_bar'],
                'steam_temp_avg' => $row->averages['steam_temp_c'],
                'water_tds_avg' => $row->averages['water_tds_ppm'],
                'water_ph_avg' => $row->averages['water_ph'],
                'blowdown_executed' => $row->blowdown_executed,
                'sootblowing_executed' => $row->sootblowing_executed,
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
     * Ordered by date, then by boiler unit, then by time_slot — and
     * time_slot is compared as the TIME value it is, never as an integer
     * hour.
     *
     * @return Generator<int, array<int, string|float|null>>
     */
    protected function streamExportRows(Builder $recordQuery): Generator
    {
        $query = (clone $recordQuery)
            ->with(['boilerRoomDetails' => fn ($detailQuery) => $detailQuery->orderBy('time_slot')])
            ->orderBy('boiler_room_records.date')
            ->orderBy('boiler_room_records.boiler_room_id')
            ->orderBy('boiler_room_records.id');

        foreach ($query->lazy(200) as $record) {
            /** @var BoilerRoomRecord $record */
            $context = [
                optional($record->date)->toDateString(),
                $record->boiler_room_id,
                $this->recordStatusValue($record),
                $record->note,
            ];

            foreach ($record->boilerRoomDetails as $detail) {
                /** @var BoilerRoomDetail $detail */
                $reading = [(string) $detail->time_slot];

                foreach (BoilerRoomRecordService::READING_FIELDS as $field) {
                    // VERBATIM: the three free-text columns pass through
                    // with no unit normalisation and no rounding, exactly as
                    // the Operator typed them.
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
     * Base query over the period's Boiler Room records (header rows).
     *
     * Scoped through `stations` to the PERIOD'S business unit and to the
     * boiler-room station type, and bounded INCLUSIVELY on
     * boiler_room_records.date — the event date, not created_at and not the
     * sync time. boiler_room_records carries neither period_id nor
     * business_unit_id, so the join is the only way to scope it.
     */
    protected function recordQueryFor(Period $period): Builder
    {
        return BoilerRoomRecord::query()
            ->join('stations', 'stations.id', '=', 'boiler_room_records.station_id')
            ->where('stations.business_unit_id', $period->business_unit_id)
            ->where('stations.type', StationTypeEnum::BoilerRoom->value)
            ->whereDate('boiler_room_records.date', '>=', $period->start_date->toDateString())
            ->whereDate('boiler_room_records.date', '<=', $period->end_date->toDateString())
            ->select('boiler_room_records.*');
    }

    /**
     * Session + role gate shared by every entry point.
     *
     * This gate sits two layers deeper than the route middleware on
     * purpose: clearing the middleware must never be enough by itself. That
     * is why widening a role here and widening routes/api.php are always one
     * change, never two — the lesson from screen-129/135.
     *
     * OPERATOR IS ADMITTED SINCE 2026-09-25 (screen-137 — the mobile Boiler
     * Room report), mirroring the widenings CagesTrackReportService and
     * SterilizerReportService received for screen-136 and screen-135.
     * Operator is treated exactly like Supervisor / Mill Management:
     * MILL-BOUND. Admitting it HERE without also adding it to the
     * mill-bound branch of resolveBusinessUnit() would drop it into the
     * unbound Admin branch, where a client-supplied business_unit_id IS
     * honoured — an Operator could then read any mill's report. The two
     * lines are one change, never two.
     *
     * THE WIDENING STOPS AT THE API. The WEB route /reports/boiler-room and
     * App\Livewire\Dashboard\LaporanBoilerRoom::canAccess() deliberately
     * stay without Operator, which has no web UI at all — canAccess() keeps
     * its own role list precisely so that this service can widen without
     * dragging the web page along.
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

    protected function recordService(): BoilerRoomRecordService
    {
        return $this->recordService ??= app(BoilerRoomRecordService::class);
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

    protected function recordStatusValue(BoilerRoomRecord $record): string
    {
        return $record->status instanceof \BackedEnum
            ? $record->status->value
            : (string) $record->status;
    }

    /** Backed enums and plain strings both reduce to the stored 'y'/'n'/null. */
    protected function enumValueOf(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
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
                "laporan-boiler-room_{$slug}_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "laporan-boiler-room_{$slug}_{$timestamp}.csv",
        ];
    }
}
