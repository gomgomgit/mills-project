<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ThreshingReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ThreshingReportController — screen-148--laporan-threshing-web and
 * screen-149--laporan-threshing-mobile. auth_requirement: authenticated,
 * Operator / Supervisor / Mill Management / Admin (see routes/api.php's
 * 'role:supervisor,mill_management,admin,operator' middleware).
 *
 * GET-ONLY BY DESIGN. There is no POST/PUT/PATCH/DELETE action here and none
 * on the /api/threshing-reports prefix — this screen is a read-only report
 * and must not offer any path that could alter Threshing data.
 *
 * All resolution / aggregation / export logic lives in
 * ThreshingReportService, shared with the Livewire component
 * (App\Livewire\Dashboard\LaporanThreshing), so the web page, the API and
 * the phone can never disagree on a figure.
 *
 * THIS REPORT SUMMARISES CONDITION, NOT OUTPUT. `summary` returns min / avg
 * / max for the five measurement columns on threshing_details, and EACH ONE
 * CARRIES ITS OWN `filled_slot_count`: every column is nullable and a slot
 * counts as filled when any one of the six reading columns is filled, so one
 * shared denominator would be wrong for at least four of the five.
 *
 * THE FIRST STATION REPORT WITH AN OPERATIONAL-TARGET MASTER — AND IT STILL
 * FLAGS NOTHING. threshing_operational_targets supplies
 * standard_operational_target and action_plan_on_deviation per parameter,
 * and both are published next to the measured figure. But the standard is
 * PROSE in a VARCHAR ('21 - 23 RPM (optimal for separation)', 'Within motor
 * rated full-load current (FLC)'), so there is no severity key, no
 * is_out_of_range key, and no colouring anywhere in the payload: parsing
 * that into comparators means inventing a limit nobody set. Full reasoning
 * in the ThreshingReportService class docblock.
 *
 * THE MASTER LISTS SIX PARAMETERS AND THE FORM MEASURES FIVE, so
 * `targets_without_metric` publishes the gap rather than hiding it — today
 * that is 'Bearing Temperature', a standard with no measurement anywhere in
 * the system. A standard that is never measured reads as satisfied when it
 * is merely absent.
 *
 * TWO REQUIRED QUERY PARAMETERS ON /summary AND /export: `period_id` AND
 * `production_line_id`. A missing one is 422 VALIDATION_ERROR and ZERO
 * threshing_records queries run. There is deliberately NO all-lines
 * fallback — a total that mixes a dozen production lines is not a number
 * anyone can act on.
 *
 * NOTE WHICH GUARD CLOSES WHICH HOLE — three different answers on purpose:
 *   - `business_unit_id` from the client is IGNORED for every mill-bound
 *     role (200 with their own mill's data, deliberately NOT 403 — a 403
 *     would confirm the other mill exists);
 *   - a `production_line_id` belonging to another mill IS refused with 403
 *     in ThreshingReportService::resolveProductionLine();
 *   - a `period_id` belonging to another mill IS refused with 403 in
 *     ThreshingReportService::authorizePeriod().
 *
 * OPERATOR IS ADMITTED ON THREE OF THE FOUR ROUTES FROM DAY ONE, because the
 * mobile twin (screen-149) is built in the same series. THE FOURTH,
 * /business-units/options, STILL REFUSES OPERATOR with 403, raised by the
 * service rather than by the middleware: a role bound to one mill has no
 * picker, and handing it the list of every mill is exactly the leak this
 * must not open. The WEB route /reports/threshing is not widened either.
 *
 * The production-line OPTION LIST is NOT here: GET
 * /api/production-lines/options-for-report (built for screen-135) is reused
 * verbatim. It is not a new endpoint and is not duplicated under this prefix.
 */
class ThreshingReportController extends Controller
{
    public function __construct(protected ThreshingReportService $service) {}

    /**
     * businessUnitOptions() — GET /api/threshing-reports/business-units/options.
     *
     * Admin-only mill picker; the service throws 403 FORBIDDEN for anyone
     * else, since Operator, Supervisor and Mill Management are already
     * bound to one mill and have no picker at all. This is the one route on
     * the prefix that Operator does not reach. Enforced in the SERVICE
     * rather than by this route's middleware on purpose: the middleware
     * admits all four roles for the other endpoints.
     */
    public function businessUnitOptions(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->businessUnitOptions(),
        ]);
    }

    /**
     * periods() — GET /api/threshing-reports/periods?business_unit_id=...
     *
     * business_logic step 5. Returns { data: [] } with HTTP 200 when the
     * mill has no period covering Threshing yet — an empty picker plus a UI
     * hint pointing at Kelola Periode Pelaporan, never a 404. An Admin who
     * did not pick a mill gets 422 VALIDATION_ERROR; so does a bound
     * account whose users.business_unit_id is NULL, and in that case the
     * all-mills list is never read at all.
     *
     * Period STATUS never filters this list: closed periods are listed, and
     * remain fully readable and exportable. The period lock governs writing
     * data, not reading a report.
     *
     * `production_line_id` is deliberately NOT a parameter here: periods
     * are per MILL, not per line. What a line filters is the DATA, not the
     * period list.
     */
    public function periods(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->listPeriods(
                $this->queryString($request, 'business_unit_id'),
            ),
        ]);
    }

    /**
     * summary() — GET /api/threshing-reports/summary?period_id=...&production_line_id=...
     *
     * business_logic steps 1-24: resolve the mill (422), resolve the
     * production line (422 when absent / 403 when it belongs to another
     * mill), authorise the period (404 / 403), then every figure for that
     * period on that line — recording coverage FIRST, then the five metrics
     * each with its own denominator and its own operational standard, the
     * targets that have no measurement at all, the per-thresher recap, the
     * daily recap, the literal downtime-reason recap, and the period
     * totals.
     *
     * NOT ONE KEY COMPARES A FIGURE TO ITS STANDARD, and that absence is
     * asserted by a unit test.
     */
    public function summary(Request $request): JsonResponse
    {
        $businessUnitId = $this->queryString($request, 'business_unit_id');

        // Ordered on purpose: an Admin with no mill is 422, and a missing or
        // foreign production line is 422/403, BEFORE the period is looked up
        // — so an incomplete request never turns into a 404/403 about a
        // period it was never entitled to ask about.
        $effectiveBusinessUnitId = $this->service->resolveBusinessUnit($businessUnitId);

        $productionLineId = $this->requireProductionLineId($request);

        $this->service->resolveProductionLine($effectiveBusinessUnitId, $productionLineId);

        $periodId = $this->requirePeriodId($request);

        return response()->json(
            $this->service->buildSummary($periodId, $businessUnitId, $productionLineId),
        );
    }

    /**
     * export() — GET /api/threshing-reports/export?period_id=...&production_line_id=...&format=csv
     *
     * business_logic step 25: ONE ROW PER TIME SLOT, with the period / mill
     * / production line / date / thresher / status / note context columns
     * repeated verbatim on every row so the file can be pivoted straight in
     * a spreadsheet. A slot whose measurement columns are all empty stays a
     * row with EMPTY cells — never dropped, never written as 0.
     *
     * NOT a JSON response. 422 EXPORT_FAILED above 50.000 rows — counted
     * over SLOT ROWS, not records, because one daily record carries up to
     * 24 of them. 422 VALIDATION_ERROR for a format outside csv|excel.
     *
     * The permission guard, the mill resolution, the line resolution and the
     * row-limit check run EAGERLY inside the service, before the response
     * starts streaming — otherwise a 403/422 would reach the client as a
     * successful but empty download.
     *
     * A CLOSED period exports exactly like an open one.
     */
    public function export(Request $request): StreamedResponse
    {
        $businessUnitId = $this->queryString($request, 'business_unit_id');

        $effectiveBusinessUnitId = $this->service->resolveBusinessUnit($businessUnitId);

        $productionLineId = $this->requireProductionLineId($request);

        $this->service->resolveProductionLine($effectiveBusinessUnitId, $productionLineId);

        $periodId = $this->requirePeriodId($request);

        $format = (string) ($this->queryString($request, 'format') ?? 'csv');

        return $this->service->export($periodId, $format, $businessUnitId, $productionLineId);
    }

    /**
     * `production_line_id` is REQUIRED on summary and export — a missing one
     * is 422 VALIDATION_ERROR with errors.production_line_id.
     *
     * IT IS NEVER DEFAULTED TO "ALL LINES OF THE MILL". A total that mixes a
     * dozen production lines is not a number anyone can act on, and silently
     * widening the scope of a report because a parameter was forgotten is
     * the worst of both worlds: it answers 200 with a figure nobody asked
     * for. The refusal is raised here AND again inside the service, so a
     * direct service call cannot skip it either.
     *
     * The line is filtered on `threshing_records.production_line_id` — the
     * record's own snapshot column — never through a join to `stations`, so
     * a station later moved to another line does not rewrite the readings it
     * already produced.
     *
     * @throws ValidationException
     */
    protected function requireProductionLineId(Request $request): string
    {
        $productionLineId = $this->queryString($request, 'production_line_id');

        if ($productionLineId === null) {
            throw ValidationException::withMessages([
                'production_line_id' => ['Production Line wajib dipilih untuk menampilkan laporan.'],
            ]);
        }

        return $productionLineId;
    }

    /**
     * `period_id` is required on both summary and export — a missing one is
     * 422 VALIDATION_ERROR with errors.period_id, not a 404 for the empty
     * string and not a silent empty report.
     *
     * @throws ValidationException
     */
    protected function requirePeriodId(Request $request): string
    {
        $periodId = $this->queryString($request, 'period_id');

        if ($periodId === null) {
            throw ValidationException::withMessages([
                'period_id' => ['Periode Pelaporan wajib dipilih.'],
            ]);
        }

        return $periodId;
    }

    /** Trimmed query value, or null when absent/blank. */
    protected function queryString(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
