<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Enums\RecordStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\Uom;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\GradingRecord;
use App\Models\Period;
use App\Models\PeriodStation;
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
 * GradingReportService — screen-146--laporan-grading-web and
 * screen-147--laporan-grading-mobile, which share this service and the four
 * /api/grading-reports/* endpoints so the web page, the API and the phone can
 * never disagree on a figure.
 *
 * ------------------------------------------------------------------
 * WHAT THIS REPORT IS ABOUT, AND WHY ITS SHAPE DIFFERS
 * ------------------------------------------------------------------
 * The six station reports before this one summarise THROUGHPUT (how much went
 * through) or READINGS (what the instrument said). This one summarises
 * COMPOSITION: each grading_records row is one truckload that was sorted, and
 * what the reader is after is the ripeness MIX of the FFB arriving at the
 * mill.
 *
 * That changes the arithmetic in two ways that are easy to get wrong, and both
 * are the reason this file is longer than it looks like it should be.
 *
 * 1. TWO NUMBERS PER PARAMETER, AND BOTH ARE PUBLISHED.
 *
 *    `share_percent` is the parameter's quantity over the period's total for
 *    its own unit — a mix WEIGHTED BY LOAD SIZE. `avg_percentage` is the mean
 *    of the per-load percentages — every load counts the same, however big it
 *    was. They answer different questions and they DISAGREE whenever load
 *    sizes differ: one enormous bad load dominates the share without moving
 *    the average, and a dozen small bad loads move the average without moving
 *    the share.
 *
 *    Publishing only one of them is the decision that looks simplest and
 *    hides half the truth. Both are returned, and the screens are required to
 *    render them side by side WITH the explanation — two numbers that
 *    disagree and no stated reason is worse than either number alone.
 *
 * 2. BUNCHES AND KILOGRAMS ARE NEVER SUMMED, and that is STRUCTURAL here
 *    rather than a convention.
 *
 *    Thirteen grading parameters are counted in bunches; the three brondolan
 *    (loose-fruit) parameters are weighed in kilograms. "Total quantity" over
 *    both is not a number at all. So the payload carries TWO independent
 *    blocks — `bunch` and `kg` — each with its own quantity_total, which is
 *    also its own share_percent denominator. There is no key anywhere that
 *    adds them, and a screen cannot accidentally produce one by iterating a
 *    single list.
 *
 *    The unit comes from the DETAIL ROW (grading_details.uom), not from the
 *    master parameter: the detail's uom is a frozen copy taken at selection
 *    time (see GradingDetail::gradingParameter()), and that copy is what says
 *    which unit the quantity was actually recorded in. Reading it from the
 *    master would silently regroup historical rows every time a master unit
 *    is edited.
 *
 * ------------------------------------------------------------------
 * EVERY AVERAGE CARRIES ITS OWN DENOMINATOR, AND HERE IT MATTERS MOST
 * ------------------------------------------------------------------
 * `avg_percentage` divides by `load_count` OF THAT PARAMETER — the number of
 * loads that actually recorded it — never by the period's load_count. A load
 * that does not list a parameter has NOT assessed it at zero percent; it has
 * not assessed it at all. Dividing by the period's load count would deflate
 * every rarely-recorded parameter while still producing a plausible-looking
 * number, which is the most expensive kind of wrong. The denominator is
 * published next to the average precisely so the difference is visible.
 *
 * ------------------------------------------------------------------
 * THE PERCENTAGE IS READ, NEVER RECOMPUTED
 * ------------------------------------------------------------------
 * grading_details.percentage is set by GradingRecordService at save time —
 * netto-based for uom=kg, header-quantity-based for uom=bunch — and is passed
 * through untouched here. Recomputing it would create a second truth that
 * drifts from what the operator saw on the input screen, and it would drift
 * exactly where the data is odd (netto 0, bunch count 0), which is where a
 * reader is most likely to be looking.
 *
 * ------------------------------------------------------------------
 * A LOAD WITH NO PARAMETER ROWS IS A FINDING, NOT A ROW TO SKIP
 * ------------------------------------------------------------------
 * It still counts in load_count, netto, bunch count, the per-origin recap and
 * the daily recap — it was weighed and recorded. It contributes NOTHING to
 * either parameter block, because nothing was assessed. The difference is
 * published as `loads_without_detail`: without it, the reader cannot explain
 * why the load count does not match the number of loads behind the mix.
 *
 * THERE IS NO `undated` COUNTER HERE, and the absence is deliberate:
 * grading_records.date is NOT NULL, so every load can be placed in a period.
 * WeighbridgeReportService has undated_trip_count only because its
 * record_datetime is nullable. Stated so the next reader does not take the
 * gap for an oversight.
 *
 * ------------------------------------------------------------------
 * CROSS-MILL SECURITY IS CLOSED AT THREE DIFFERENT POINTS, on purpose
 * ------------------------------------------------------------------
 *   1. resolveBusinessUnit() IGNORES the client's business_unit_id for every
 *      mill-bound role — Operator, Supervisor and Mill Management alike. Not
 *      validated, not compared, discarded. Probing another mill's id returns
 *      200 with the CALLER'S OWN data, deliberately not a 403: a 403 would
 *      confirm the other mill exists.
 *   2. resolveProductionLine() REFUSES a line belonging to another mill with
 *      403, and zero grading_records queries have run by then. Here there IS a
 *      concrete handle on another mill's data.
 *   3. authorizePeriod() REFUSES a period belonging to another mill with 403.
 *
 * A mill-bound account whose users.business_unit_id is NULL FAILS CLOSED and
 * the whole-mill list is never even read — see allBusinessUnits(), which is
 * public and deliberately trivial so a spy can prove it was never called.
 *
 * OPERATOR IS ADMITTED ON THE THREE DATA ROUTES FROM DAY ONE. That differs
 * from Weighbridge, where the mobile twin came later and the widening had to
 * be its own reviewable step: here screen-147 is built in the SAME change, so
 * sending a role list that would have to be widened an hour later would be
 * theatre. What does NOT change is the part that makes it safe — Operator sits
 * in the MILL-BOUND branch of resolveBusinessUnit() from the first line, and
 * businessUnitOptions() still refuses it, because a role tied to one mill has
 * no picker and handing it the list of every mill is the very leak this
 * avoids.
 *
 * THE WEB ROUTE /reports/grading IS NOT WIDENED. App\Livewire\Dashboard\
 * LaporanGrading keeps its OWN role list (canAccess()) rather than borrowing
 * guardAccess(), so widening the API cannot widen the web screen by accident.
 *
 * ------------------------------------------------------------------
 * ALL AGGREGATION HAPPENS IN PHP, NONE OF IT IN SQL
 * ------------------------------------------------------------------
 * Same decision as the six reports before it, for the same reasons. SQL
 * aggregate behaviour over NULLABLE columns is NOT identical in SQLite (the
 * test suite) and PostgreSQL (production), and the separate-denominator and
 * null-is-not-zero rules are exactly what gets lost in that difference.
 * Date-part extraction has NO portable SQL spelling either. One query loads
 * every load of the period+line with its details and their parameters eagerly;
 * everything after that is PHP.
 *
 * AND ONE PORTABILITY TRAP THAT ALREADY BIT THIS REPO: `date` is a TIMESTAMP
 * column, so the range filter uses whereDate(), not a bare where(). A plain
 * where('date', '<=', '2026-09-30') drops every load recorded after midnight
 * on the last day of the period.
 */
class GradingReportService
{
    /**
     * Ceiling on EMITTED ROWS, not on records — and that distinction is the
     * whole point here. One load can produce up to 16 parameter rows, so this
     * limit is reached at roughly 3.100 loads, far earlier than on any other
     * station report. Counting records instead would let an export run until
     * it exhausted memory mid-stream, which reaches the user as a corrupt
     * download rather than a clear refusal.
     */
    public const EXPORT_ROW_LIMIT = 50000;

    protected const STATION_TYPE = StationTypeEnum::Grading->value;

    public const SUPPORTED_FORMATS = ['csv', 'excel'];

    /** The two unit groups. They are NEVER summed — see the class docblock. */
    public const UOM_BUNCH = Uom::Bunch->value;

    public const UOM_KG = Uom::Kg->value;

    public const UOMS = [self::UOM_BUNCH, self::UOM_KG];

    public const DRAFT_STATUSES = [
        RecordStatus::DraftOngoing->value,
        RecordStatus::DraftPaused->value,
    ];

    /** Periode / Mill / Production Line, repeated verbatim on every row. */
    public const EXPORT_CONTEXT_COLUMN_COUNT = 3;

    /**
     * ONE ROW PER PARAMETER PER LOAD. The load's own columns repeat down its
     * parameter rows so the file can be pivoted straight in a spreadsheet, and
     * `Satuan` rides along on every row so bunches and kilograms stay
     * distinguishable without ever being added together.
     *
     * A load with no parameter rows still emits ONE row, with the four
     * parameter columns empty — dropping it would make the file disagree with
     * the load count on screen.
     */
    public const EXPORT_HEADER = [
        'Periode',
        'Mill',
        'Production Line',
        'Tanggal',
        'No. Grading',
        'No. Polisi',
        'Kode Kendaraan',
        'Asal (Estate/Supplier)',
        'Divisi',
        'Netto (kg)',
        'Jumlah Janjang',
        'Parameter Mutu',
        'Satuan',
        'Kuantitas',
        'Persentase (%)',
        'Catatan',
        'Diperiksa Oleh',
        'Disahkan Oleh',
        'Status',
    ];

    /**
     * code => name from the `station_types` master table, memoised per service
     * instance (one request / one Livewire render).
     *
     * @var array<string, string>|null
     */
    protected ?array $stationTypeNames = null;

    // ------------------------------------------------------------------
    // Access
    // ------------------------------------------------------------------

    /**
     * Which mill the caller is allowed to look at.
     *
     * Operator / Supervisor / Mill Management: ALWAYS their own
     * business_unit_id; the argument is ignored outright, so probing another
     * mill's id is a no-op that still returns the caller's own data with
     * HTTP 200.
     *
     * Admin: the value MUST come from the caller. Missing is 422
     * VALIDATION_ERROR — never a silent null and never an empty result set,
     * which would read as "this mill has no data".
     *
     * A bound account with no mill FAILS CLOSED, and fails EARLY: the throw
     * happens before any repository call, so allBusinessUnits() is provably
     * never reached from this path.
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
            // business_unit_id. This line is what makes admitting Operator in
            // guardAccess() safe.
            || $role === UserRole::Operator->value) {
            // Client-supplied business_unit_id is deliberately DISCARDED —
            // not validated, not compared, discarded.
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
     * Mill picker options, ADMIN ONLY — and the one entry point the screen-147
     * widening deliberately did NOT open.
     *
     * Operator, Supervisor and Mill Management are each tied to a single mill
     * and have no picker at all, so asking for this list is a 403 rather than
     * a filtered list of one. The refusal is raised HERE rather than by the
     * route middleware — the middleware already admitted the request — so it
     * carries code = 'FORBIDDEN' through ApiExceptionHandler, and so that
     * clearing the middleware is never enough by itself.
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
     * Deliberately trivial and PUBLIC so a test spy can prove it was never
     * called on the fail-closed paths.
     */
    public function allBusinessUnits(): Collection
    {
        return BusinessUnit::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Production lines of one mill. Always scoped to the EFFECTIVE mill, so a
     * line of another mill is never even offered as an option.
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
     * The line whose figures are being asked for. REQUIRED.
     *
     * Missing is 422 and NEVER a silent widening to "every line of the mill":
     *  a total that mixes a dozen lines is not a number anyone can act on, and
     * answering 200 with a figure nobody asked for is the worst of both
     * worlds. A line belonging to another mill is 403 — unlike
     * business_unit_id, which is merely ignored, a line id is a concrete
     * handle on another mill's data.
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
     * 404 when the id does not exist, 403 when it belongs to another mill —
     * and the ROLE GUARD runs first, so a caller who may not be here at all
     * never learns which period ids exist.
     *
     * @throws ModelNotFoundException 404 NOT_FOUND
     * @throws AuthorizationException 403 FORBIDDEN
     */
    public function authorizePeriod(string $periodId): Period
    {
        $this->guardAccess();

        /** @var Period $period */
        $period = Period::query()->with('businessUnit')->findOrFail($periodId);

        return $this->authorizePeriodModel($period);
    }

    /**
     * Periods of the effective mill that COVER Grading — that is, those with a
     * period_stations row of type 'grading'.
     *
     * Period STATUS never filters this list: closed periods are listed and
     * remain fully readable and exportable. The period lock governs writing
     * data, not reading a report.
     *
     * `production_line_id` is deliberately NOT a parameter: periods are per
     * MILL, not per line. What a line filters is the DATA, not the period list.
     *
     * @return list<array<string, mixed>>
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

    // ------------------------------------------------------------------
    // Summary
    // ------------------------------------------------------------------

    /**
     * Every figure on the screen for one period on one production line.
     *
     * Order of the guards is deliberate: role first (403), then mill (422),
     * then production line (422 / 403), then the period id (422), then the
     * period itself (404 / 403).
     *
     * NOT ONE KEY IN THE RETURNED ARRAY ADDS BUNCHES TO KILOGRAMS. `bunch` and
     * `kg` are two independent blocks, each carrying the quantity_total that
     * is also its own share_percent denominator.
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
        // cases zero grading_records queries have run by this point.
        $productionLineId = $this->resolveProductionLine($businessUnitId, $productionLineId);

        $period = $this->requirePeriod($period);

        // ONE query for every load of this period+line, details and their
        // parameters loaded eagerly. Everything below is PHP: no SQL
        // aggregate, no raw expression, no driver-specific date function.
        $loads = $this->loadsFor($period, $productionLineId);

        $daily = $this->dailyOf($loads);

        return [
            'business_unit' => $this->businessUnitInfo($businessUnitId),
            'production_line' => $this->productionLineInfo($productionLineId),
            'period' => [
                'id' => (string) $period->id,
                'name' => (string) $period->name,
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'status' => $this->statusValue($period),
            ],
            'load_count' => $loads->count(),
            // null, NOT 0, when there is no load at all: zero would claim a
            // measured total of nothing. netto and quantity are NOT NULL
            // columns, so the denominator of both averages is simply the load
            // count — there is no separate filled-count, and saying so here
            // keeps its absence from reading as an oversight.
            'netto_total' => $this->sumOrNull($loads, 'netto'),
            'netto_avg' => $this->avgOrNull($loads, 'netto'),
            'bunch_total' => $this->sumOrNull($loads, 'bunch'),
            'bunch_avg' => $this->avgOrNull($loads, 'bunch'),
            // TWO INDEPENDENT BLOCKS. parameterBlockOf() is called twice and
            // the two results are NEVER folded into a third.
            self::UOM_BUNCH => $this->parameterBlockOf($loads, self::UOM_BUNCH),
            self::UOM_KG => $this->parameterBlockOf($loads, self::UOM_KG),
            'by_estate_supplier' => $this->byEstateSupplierOf($loads),
            // A load that was weighed and recorded but never assessed. It is
            // INSIDE every header figure and OUTSIDE both parameter blocks,
            // and this counter is the only thing that lets a reader explain
            // the gap.
            'loads_without_detail' => $loads->filter(fn ($load) => $load->detail_count === 0)->count(),
            // Draft loads are INCLUDED in every figure above; this is the
            // disclosure, not a filter.
            'draft_load_count' => $loads
                ->filter(fn ($load) => in_array($load->status, self::DRAFT_STATUSES, true))
                ->count(),
            'loads_without_division' => $loads->filter(fn ($load) => $load->division === '')->count(),
            // Verification status is COMPLETENESS, not a filter: these loads
            // still count in full everywhere above.
            'loads_not_checked' => $loads->filter(fn ($load) => ! $load->checked)->count(),
            'loads_not_acknowledged' => $loads->filter(fn ($load) => ! $load->acknowledged)->count(),
            'daily' => $daily,
            'daily_total' => $this->dailyTotalOf($daily),
            'completeness' => [
                'days_in_period' => $this->daysInPeriod($period),
                // COUNT DISTINCT dates having at least one load. `daily` has
                // exactly one row per such date, so counting it IS the
                // distinct count — one definition, one answer.
                'days_with_load' => count($daily),
                // Penyebut persentase hari ber-muatan: berhenti di HARI INI
                // untuk periode yang masih berjalan (temuan audit 2026-10-04).
                'days_counted' => ReportPeriodDays::counted($period),
                'period_running' => ReportPeriodDays::isRunning($period),
            ],
        ];
    }

    /**
     * Repo-convention alias of buildSummary(), so this service reads the same
     * way as its six siblings at the call sites. One implementation, two
     * names — never two implementations.
     */
    public function summary(
        Period|string|null $period = null,
        ?string $requestedBusinessUnitId = null,
        ?string $productionLineId = null,
    ): array {
        return $this->buildSummary($period, $requestedBusinessUnitId, $productionLineId);
    }

    // ------------------------------------------------------------------
    // Export
    // ------------------------------------------------------------------

    /**
     * ONE EXPORTED ROW PER PARAMETER PER LOAD, with the period / mill /
     * production line context repeated verbatim on every row so the file can
     * be pivoted directly in a spreadsheet.
     *
     * The query is THE SAME as summary()'s: same period range on `date`, same
     * line column, same absence of any status filter.
     *
     * A load with NO parameter rows still emits ONE row, with the parameter
     * columns empty — never dropped. Dropping it would make the file disagree
     * with the load count on screen.
     *
     * THE GUARD AND THE ROW-LIMIT CHECK RUN EAGERLY, at call time, while the
     * rows themselves are yielded lazily. Making this method itself a
     * generator would defer the 403/422 until the first iteration, so a
     * refused export would look like a successful call that produced nothing.
     *
     * THE LIMIT IS ON EMITTED ROWS, NOT ON RECORDS — see EXPORT_ROW_LIMIT.
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

        // Counted over ROWS THAT WILL BE EMITTED: one per detail, and one for
        // a load that has none. Counting records would understate it up to
        // sixteenfold and let the export die mid-stream.
        $rowCount = (clone $recordQuery)->withCount('gradingDetails')->get()
            ->sum(fn (GradingRecord $record) => max(1, (int) $record->grading_details_count));

        // Strictly greater than: exactly EXPORT_ROW_LIMIT rows still export.
        // `static::`, not `self::` — the ceiling is a tunable, and the unit
        // test lowers it in a subclass to prove the check counts EMITTED ROWS
        // rather than records. With `self::` the parent's value would be baked
        // in at compile time and that test could only be written by seeding
        // fifty thousand rows.
        if ($rowCount > static::EXPORT_ROW_LIMIT) {
            throw new ExportFailedException;
        }

        $context = [
            (string) $period->name,
            (string) ($period->businessUnit?->name ?? ''),
            (string) ($this->productionLineInfo($productionLineId)['name'] ?? ''),
        ];

        return $this->streamExportRows($recordQuery, $context);
    }

    /**
     * The streamed file around buildExportRows().
     *
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
    // Aggregation — all of it in PHP
    // ------------------------------------------------------------------

    /**
     * One parameter block for ONE unit group.
     *
     * quantity_total is the group's own denominator for share_percent, and
     * that is why the two groups can never contaminate each other: the
     * function only ever sees rows of one unit.
     *
     * @return array{quantity_total: float|null, parameter_count: int, rows: list<array<string, mixed>>}
     */
    protected function parameterBlockOf(Collection $loads, string $uom): array
    {
        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];
        $quantityTotal = 0.0;
        $hasRow = false;

        foreach ($loads as $load) {
            foreach ($load->details as $detail) {
                if ($detail->uom !== $uom) {
                    continue;
                }

                $hasRow = true;
                $quantityTotal += $detail->quantity;

                $key = $detail->parameter_id;

                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'grading_parameter_id' => $detail->parameter_id,
                        'name' => $detail->parameter_name,
                        'quantity_total' => 0.0,
                        // THE DENOMINATOR of avg_percentage, and the only
                        // place it is established: loads that actually
                        // recorded this parameter. A load that does not list
                        // it has not assessed it at 0% — it has not assessed
                        // it at all.
                        'load_count' => 0,
                        '_percentage_sum' => 0.0,
                    ];
                }

                $groups[$key]['quantity_total'] += $detail->quantity;
                $groups[$key]['load_count']++;
                $groups[$key]['_percentage_sum'] += $detail->percentage;
            }
        }

        $rows = [];

        foreach ($groups as $group) {
            $rows[] = [
                'grading_parameter_id' => $group['grading_parameter_id'],
                'name' => $group['name'],
                'quantity_total' => round((float) $group['quantity_total'], 2),
                // Guarded division: never a DivisionByZeroError, and null
                // rather than 0 when the group total is zero — "no share of
                // nothing" is not "a share of zero".
                'share_percent' => $quantityTotal > 0.0
                    ? round(((float) $group['quantity_total'] / $quantityTotal) * 100, 2)
                    : null,
                // Divided by THIS PARAMETER'S load_count, never by the
                // period's. See the class docblock.
                'avg_percentage' => $group['load_count'] > 0
                    ? round((float) $group['_percentage_sum'] / $group['load_count'], 2)
                    : null,
                'load_count' => $group['load_count'],
            ];
        }

        usort($rows, function (array $a, array $b) {
            // Null share sorts as 0.0 for ORDERING ONLY — it stays null in the
            // payload.
            $byShare = ((float) ($b['share_percent'] ?? 0.0)) <=> ((float) ($a['share_percent'] ?? 0.0));

            if ($byShare !== 0) {
                return $byShare;
            }

            $byQuantity = $b['quantity_total'] <=> $a['quantity_total'];

            if ($byQuantity !== 0) {
                return $byQuantity;
            }

            // Deterministic last resort — the same answer on every call.
            return strcmp((string) $a['name'], (string) $b['name']);
        });

        return [
            // null, NOT 0.0, when this unit never appeared at all: the group
            // is still PUBLISHED so the screen can render it as unavailable
            // instead of hiding it, and a hidden group reads as "this does not
            // exist" rather than "there were none".
            'quantity_total' => $hasRow ? round($quantityTotal, 2) : null,
            'parameter_count' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * Per-origin recap. The origin-less loads are ONE group (''), not dropped
     * rows, so the load counts still add up to load_count.
     *
     * `estate_supplier` is a NOT NULL column, so in practice the empty group
     * is made of empty or whitespace-only strings; loadsFor() trims and
     * null-coalesces anyway, which costs nothing and keeps this function
     * correct if the column is ever relaxed.
     *
     * @return list<array<string, mixed>>
     */
    protected function byEstateSupplierOf(Collection $loads): array
    {
        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];

        foreach ($loads as $load) {
            $key = $load->estate_supplier;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'estate_supplier' => $key,
                    'load_count' => 0,
                    'netto_total' => 0.0,
                    'bunch_total' => 0.0,
                ];
            }

            $groups[$key]['load_count']++;
            $groups[$key]['netto_total'] += $load->netto;
            $groups[$key]['bunch_total'] += $load->bunch;
        }

        $rows = array_values($groups);

        usort($rows, function (array $a, array $b) {
            $byNetto = $b['netto_total'] <=> $a['netto_total'];

            if ($byNetto !== 0) {
                return $byNetto;
            }

            $byLoads = $b['load_count'] <=> $a['load_count'];

            if ($byLoads !== 0) {
                return $byLoads;
            }

            return strcmp((string) $a['estate_supplier'], (string) $b['estate_supplier']);
        });

        return array_map(function (array $row) {
            $row['netto_total'] = round((float) $row['netto_total'], 2);
            $row['bunch_total'] = round((float) $row['bunch_total'], 2);

            return $row;
        }, $rows);
    }

    /**
     * One row per DATE that has at least one load, taken from the date part of
     * grading_records.date. Rows are never dropped for being small — a date
     * with one load is a date with a load.
     *
     * @return list<array<string, mixed>>
     */
    protected function dailyOf(Collection $loads): array
    {
        /** @var array<string, array<string, mixed>> $byDate */
        $byDate = [];

        foreach ($loads as $load) {
            if (! isset($byDate[$load->date])) {
                $byDate[$load->date] = [
                    'date' => $load->date,
                    'load_count' => 0,
                    'netto_total' => 0.0,
                    'bunch_total' => 0.0,
                ];
            }

            $byDate[$load->date]['load_count']++;
            $byDate[$load->date]['netto_total'] += $load->netto;
            $byDate[$load->date]['bunch_total'] += $load->bunch;
        }

        ksort($byDate);

        return array_map(function (array $row) {
            $row['netto_total'] = round((float) $row['netto_total'], 2);
            $row['bunch_total'] = round((float) $row['bunch_total'], 2);

            return $row;
        }, array_values($byDate));
    }

    /**
     * The period total row of the daily recap. Sums the daily rows rather than
     * re-querying, so the row can never disagree with the table above it.
     *
     * @param  list<array<string, mixed>>  $daily
     */
    protected function dailyTotalOf(array $daily): array
    {
        $total = [
            'load_count' => 0,
            'netto_total' => null,
            'bunch_total' => null,
        ];

        foreach ($daily as $row) {
            $total['load_count'] += $row['load_count'];
            $total['netto_total'] = (float) ($total['netto_total'] ?? 0.0) + (float) $row['netto_total'];
            $total['bunch_total'] = (float) ($total['bunch_total'] ?? 0.0) + (float) $row['bunch_total'];
        }

        if ($total['netto_total'] !== null) {
            $total['netto_total'] = round((float) $total['netto_total'], 2);
        }

        if ($total['bunch_total'] !== null) {
            $total['bunch_total'] = round((float) $total['bunch_total'], 2);
        }

        return $total;
    }

    /** Sum of one header column, or null when there is no load at all. */
    protected function sumOrNull(Collection $loads, string $attribute): ?float
    {
        if ($loads->isEmpty()) {
            return null;
        }

        return round((float) $loads->sum(fn ($load) => $load->{$attribute}), 2);
    }

    /** Mean of one header column over load_count, or null when there is none. */
    protected function avgOrNull(Collection $loads, string $attribute): ?float
    {
        if ($loads->isEmpty()) {
            return null;
        }

        return round((float) $loads->sum(fn ($load) => $load->{$attribute}) / $loads->count(), 2);
    }

    // ------------------------------------------------------------------
    // Reading the records
    // ------------------------------------------------------------------

    /**
     * Every load of this period+line, flattened into plain objects so the
     * aggregation above never touches an Eloquent accessor twice.
     *
     * The detail rows carry the FROZEN uom of the row itself, not the master
     * parameter's current one — see the class docblock.
     */
    protected function loadsFor(Period $period, string $productionLineId): Collection
    {
        return $this->recordQueryFor($period, $productionLineId)
            ->with(['gradingDetails.gradingParameter:id,name'])
            ->orderBy('grading_records.date')
            ->orderBy('grading_records.id')
            ->get()
            ->map(function (GradingRecord $record) {
                $details = $record->gradingDetails
                    ->map(fn ($detail) => (object) [
                        'parameter_id' => (string) $detail->grading_parameter_id,
                        'parameter_name' => (string) ($detail->gradingParameter?->name ?? ''),
                        // The row's own unit, frozen at selection time.
                        'uom' => $detail->uom instanceof Uom
                            ? $detail->uom->value
                            : (string) $detail->uom,
                        'quantity' => (float) $detail->quantity,
                        // READ, never recomputed from netto or bunch count.
                        'percentage' => (float) $detail->percentage,
                    ])
                    ->values();

                return (object) [
                    // "Y-m-d" of the SORTING, not of the row's creation.
                    'date' => $this->dateStringOf($record->date),
                    'netto' => (float) $record->netto,
                    'bunch' => (float) $record->quantity,
                    // NULL and '' are one group — see byEstateSupplierOf().
                    'estate_supplier' => trim((string) ($record->estate_supplier ?? '')),
                    'division' => trim((string) ($record->division ?? '')),
                    'checked' => $record->checked_by !== null,
                    'acknowledged' => $record->acknowledged_by !== null,
                    'status' => $this->recordStatusValue($record),
                    'details' => $details,
                    'detail_count' => $details->count(),
                ];
            })
            ->values();
    }

    /**
     * The period+line filter, in one place so summary() and the export can
     * never drift apart.
     *
     * whereDate, NOT a bare where: `date` is a TIMESTAMP column, so
     * where('date', '<=', '2026-09-30') drops every load recorded after
     * midnight on the last day of the period. This trap has already bitten
     * this repo on PostgreSQL.
     *
     * The line filter is the RECORD'S OWN column, never a join to `stations` —
     * a station moved to another line must not rewrite the loads it already
     * produced.
     */
    protected function recordQueryFor(Period $period, string $productionLineId): Builder
    {
        return GradingRecord::query()
            ->where('grading_records.production_line_id', $productionLineId)
            ->whereDate('grading_records.date', '>=', $period->start_date->toDateString())
            ->whereDate('grading_records.date', '<=', $period->end_date->toDateString())
            ->select('grading_records.*');
    }

    /**
     * ONE ROW PER PARAMETER PER LOAD, plus one row for a load that has no
     * parameter at all.
     *
     * @return Generator<int, array<int, string|float|null>>
     */
    protected function streamExportRows(Builder $recordQuery, array $context): Generator
    {
        $query = (clone $recordQuery)
            ->with(['gradingDetails.gradingParameter:id,name', 'checkedBy:id,name', 'acknowledgedBy:id,name'])
            ->orderBy('grading_records.date')
            ->orderBy('grading_records.id');

        foreach ($query->lazy(200) as $record) {
            /** @var GradingRecord $record */
            $loadColumns = [
                optional($record->date)->format('Y-m-d'),
                $record->grading_number,
                $record->license_plate_no,
                $record->vehicle_code,
                $record->estate_supplier,
                $record->division,
                $record->netto,
                $record->quantity,
            ];

            $tailColumns = [
                $record->note,
                $record->checkedBy?->name,
                $record->acknowledgedBy?->name,
                // Label Indonesia, bukan enum mentah.
                ExportValue::status($this->recordStatusValue($record)),
            ];

            if ($record->gradingDetails->isEmpty()) {
                // A load that was recorded but never assessed STAYS A ROW,
                // with the four parameter columns empty. Dropping it would
                // make the file disagree with the load count on screen.
                yield array_merge($context, $loadColumns, [null, null, null, null], $tailColumns);

                continue;
            }

            foreach ($record->gradingDetails as $detail) {
                yield array_merge($context, $loadColumns, [
                    $detail->gradingParameter?->name,
                    // Satuan rides along on EVERY row, so bunches and
                    // kilograms stay distinguishable in the file without ever
                    // being added together.
                    $detail->uom instanceof Uom ? $detail->uom->value : (string) $detail->uom,
                    $detail->quantity,
                    $detail->percentage,
                ], $tailColumns);
            }
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Session + role gate shared by every entry point.
     *
     * This gate sits two layers deeper than the route middleware on purpose:
     * clearing the middleware must never be enough by itself.
     *
     * OPERATOR IS ADMITTED — screen-147, the mobile Grading report, calls
     * these endpoints and is built in the same change. Admitting the role HERE
     * is only half of it: resolveBusinessUnit() must also place Operator in
     * the MILL-BOUND branch, or this line opens a cross-mill read. The one
     * entry point that still refuses Operator is businessUnitOptions(), which
     * checks the role itself precisely because this gate lets it through.
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

    /** The mill check shared by authorizePeriod() and requirePeriod(). */
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
     * A missing period is 422 — not a 404 for the empty string, and not a
     * silent empty report.
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

    /** @return array{id: string, name: string} */
    protected function businessUnitInfo(string $businessUnitId): array
    {
        /** @var BusinessUnit|null $businessUnit */
        $businessUnit = BusinessUnit::query()->find($businessUnitId, ['id', 'name']);

        return [
            'id' => $businessUnitId,
            'name' => (string) ($businessUnit?->name ?? ''),
        ];
    }

    /** @return array{id: string, name: string} */
    protected function productionLineInfo(string $productionLineId): array
    {
        /** @var ProductionLine|null $line */
        $line = ProductionLine::query()->find($productionLineId, ['id', 'name']);

        return [
            'id' => $productionLineId,
            'name' => (string) ($line?->name ?? ''),
        ];
    }

    protected function daysInPeriod(Period $period): int
    {
        return (int) $period->start_date->copy()->startOfDay()
            ->diffInDays($period->end_date->copy()->startOfDay()) + 1;
    }

    /** @return array<string, mixed> */
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
     * The status of THIS SCREEN'S STATION inside the period (the
     * period_stations row), not a status of the period: since 2026-09-25 a
     * period has no status of its own, because stations are not closed
     * together.
     */
    protected function statusValue(Period $period): string
    {
        $status = $this->stationRowOf($period)?->status;

        if ($status === null) {
            return PeriodStatus::Draft->value;
        }

        return is_object($status) ? $status->value : (string) $status;
    }

    protected function stationRowOf(Period $period): ?PeriodStation
    {
        if ($period->relationLoaded('stations')) {
            return $period->stations->firstWhere('station_type', self::STATION_TYPE);
        }

        return $period->stations()
            ->where('station_type', self::STATION_TYPE)
            ->first();
    }

    protected function recordStatusValue(GradingRecord $record): string
    {
        return $record->status instanceof \BackedEnum
            ? $record->status->value
            : (string) $record->status;
    }

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

    /** @return array{0: string, 1: string} */
    protected function fileMetaFor(string $format, Period $period): array
    {
        $slug = str($period->name !== '' ? $period->name : 'periode')->slug()->value();
        $timestamp = now()->format('Ymd_His');

        if ($format === 'excel') {
            return [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                "laporan-grading_{$slug}_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "laporan-grading_{$slug}_{$timestamp}.csv",
        ];
    }
}
