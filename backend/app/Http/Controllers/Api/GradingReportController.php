<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GradingReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GradingReportController — screen-146--laporan-grading-web and
 * screen-147--laporan-grading-mobile. auth_requirement: authenticated,
 * Operator / Supervisor / Mill Management / Admin (see routes/api.php's
 * 'role:supervisor,mill_management,admin,operator' middleware).
 *
 * GET-ONLY BY DESIGN. There is no POST/PUT/PATCH/DELETE action here and none
 * on the /api/grading-reports prefix — this screen is a read-only report and
 * must not offer any path that could alter Grading data.
 *
 * All resolution / aggregation / export logic lives in GradingReportService,
 * shared with the Livewire component (App\Livewire\Dashboard\LaporanGrading),
 * so the web page, the API and the phone can never disagree on a figure.
 *
 * THIS REPORT SUMMARISES COMPOSITION, NOT THROUGHPUT, AND BUNCHES ARE NEVER
 * ADDED TO KILOGRAMS. `summary` returns two independent parameter blocks —
 * `bunch` (thirteen parameters counted in bunches) and `kg` (the three
 * brondolan parameters, weighed) — each carrying the quantity_total that is
 * also its own share denominator. NOT ONE KEY totals the two.
 *
 * Each parameter carries TWO figures that may disagree, and both are
 * published: `share_percent` (weighted by load size) and `avg_percentage`
 * (every load counts the same), the latter always beside its own
 * `load_count`, which is the number of loads that actually recorded that
 * parameter. Full reasoning in the GradingReportService class docblock.
 *
 * TWO REQUIRED QUERY PARAMETERS ON /summary AND /export: `period_id` AND
 * `production_line_id`. A missing one is 422 VALIDATION_ERROR and ZERO
 * grading_records queries run. There is deliberately NO all-lines fallback.
 *
 * NOTE WHICH GUARD CLOSES WHICH HOLE — three different answers on purpose:
 *   - `business_unit_id` from the client is IGNORED for every mill-bound role
 *     (200 with their own mill's data, deliberately NOT 403 — a 403 would
 *     confirm the other mill exists);
 *   - a `production_line_id` belonging to another mill IS refused with 403 in
 *     GradingReportService::resolveProductionLine();
 *   - a `period_id` belonging to another mill IS refused with 403 in
 *     GradingReportService::authorizePeriod().
 *
 * OPERATOR IS ADMITTED ON THREE OF THE FOUR ROUTES FROM DAY ONE, because the
 * mobile twin (screen-147) is built in the same change. THE FOURTH,
 * /business-units/options, STILL REFUSES OPERATOR with 403, raised by the
 * service rather than by the middleware: a role bound to one mill has no
 * picker, and handing it the list of every mill is exactly the leak the
 * widening must avoid. The WEB route /reports/grading is not widened either.
 *
 * The production-line OPTION LIST is NOT here: GET
 * /api/production-lines/options-for-report (built for screen-135) is reused
 * verbatim. It is not a new endpoint and is not duplicated under this prefix.
 */
class GradingReportController extends Controller
{
    public function __construct(protected GradingReportService $service) {}

    /**
     * businessUnitOptions() — GET /api/grading-reports/business-units/options.
     *
     * Admin-only mill picker; the service throws 403 FORBIDDEN for anyone
     * else, since Operator, Supervisor and Mill Management are already bound
     * to one mill and have no picker at all. Enforced in the SERVICE rather
     * than by this route's middleware on purpose: the middleware admits all
     * four roles for the other endpoints, and handing a mill-bound role the
     * list of every mill is precisely the leak this screen must not open.
     */
    public function businessUnitOptions(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->businessUnitOptions(),
        ]);
    }

    /**
     * periods() — GET /api/grading-reports/periods?business_unit_id=...
     *
     * Returns { data: [] } with HTTP 200 when the mill has no period covering
     * Grading yet — an empty picker plus a UI hint pointing at Kelola Periode
     * Pelaporan, never a 404. An Admin who did not pick a mill gets 422
     * VALIDATION_ERROR; so does a bound account whose users.business_unit_id is
     * NULL, and in that case the all-mills list is never read at all.
     *
     * Period STATUS never filters this list: closed periods are listed, and
     * remain fully readable and exportable. The period lock governs writing
     * data, not reading a report.
     *
     * `production_line_id` is deliberately NOT a parameter here: periods are
     * per MILL, not per line. What a line filters is the DATA, not the period
     * list.
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
     * summary() — GET /api/grading-reports/summary?period_id=...&production_line_id=...
     *
     * Resolve the mill (422), resolve the production line (422 when absent /
     * 403 when it belongs to another mill), authorise the period (404 / 403),
     * then every figure for that period on that line: load count, netto and
     * bunch-count totals with their averages, the TWO parameter blocks with
     * each parameter's share, average percentage and its own denominator, the
     * per-origin recap, the completeness counters, the daily recap and its
     * total row.
     *
     * NOT ONE RETURNED KEY ADDS BUNCHES TO KILOGRAMS, and no percentage
     * anywhere is recomputed from netto or from the header bunch count.
     */
    public function summary(Request $request): JsonResponse
    {
        $businessUnitId = $this->queryString($request, 'business_unit_id');

        // Ordered on purpose: an Admin with no mill is 422, and a missing or
        // foreign production line is 422/403, BEFORE the period is looked up —
        // so an incomplete request never turns into a 404/403 about a period
        // it was never entitled to ask about.
        $effectiveBusinessUnitId = $this->service->resolveBusinessUnit($businessUnitId);

        $productionLineId = $this->requireProductionLineId($request);

        $this->service->resolveProductionLine($effectiveBusinessUnitId, $productionLineId);

        $periodId = $this->requirePeriodId($request);

        return response()->json(
            $this->service->buildSummary($periodId, $businessUnitId, $productionLineId),
        );
    }

    /**
     * export() — GET /api/grading-reports/export?period_id=...&production_line_id=...&format=csv
     *
     * ONE ROW PER PARAMETER PER LOAD, with the period / mill / production line
     * context repeated verbatim on every row so the file can be pivoted
     * straight in a spreadsheet, and `Satuan` on every row so bunches and
     * kilograms stay distinguishable without ever being summed. A load with no
     * parameter rows STAYS A ROW, with the parameter columns empty — never
     * dropped.
     *
     * NOT a JSON response. 422 EXPORT_FAILED above 50.000 EMITTED ROWS — rows,
     * not records: one load can produce sixteen — and 422 VALIDATION_ERROR for
     * a format outside csv|excel.
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
     * `production_line_id` is REQUIRED on summary and export — a missing one is
     * 422 VALIDATION_ERROR with errors.production_line_id.
     *
     * IT IS NEVER DEFAULTED TO "ALL LINES OF THE MILL". A total that mixes a
     * dozen production lines is not a number anyone can act on, and silently
     * widening the scope of a report because a parameter was forgotten is the
     * worst of both worlds: it answers 200 with a figure nobody asked for.
     * The refusal is raised here AND again inside the service, so a direct
     * service call cannot skip it either.
     *
     * The line is filtered on `grading_records.production_line_id` — the
     * record's own snapshot column — never through a join to `stations`, so a
     * station later moved to another line does not rewrite the loads it
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
