<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WeighbridgeReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * WeighbridgeReportController — screen-143--laporan-weighbridge-web
 * (Laporan Periode Weighbridge) and, since 2026-10-05,
 * screen-144--laporan-weighbridge-mobile. auth_requirement: authenticated,
 * Operator / Supervisor / Mill Management / Admin (see routes/api.php's
 * 'role:supervisor,mill_management,admin,operator' middleware).
 *
 * GET-ONLY BY DESIGN. There is no POST/PUT/PATCH/DELETE action here and none
 * on the /api/weighbridge-reports prefix — this screen is a read-only report
 * and must not offer any path that could alter Weighbridge data.
 *
 * All resolution / aggregation / export logic lives in
 * WeighbridgeReportService, shared with the Livewire component
 * (App\Livewire\Dashboard\LaporanWeighbridge), so the web page and the API can
 * never disagree on a figure — the same split as
 * StorageTankReportController / LaporanStorageTank.
 *
 * THIS REPORT SUMMARISES TRANSACTIONS, NOT READINGS, AND THE TWO FLOWS ARE
 * NEVER SUMMED. `summary` returns two independent metric sets — `receive`
 * (FFB arriving, record_datetime = arrival) and `dispatch` (shipments
 * leaving, record_datetime = departure) — and NOT ONE KEY totals the two.
 * There is also NO vehicle-in-plant duration anywhere, in the payload or in
 * the CSV: migration 2026_08_19_000010 merged the two old timestamp columns
 * into a single `record_datetime` and dropped both, so a trip carries exactly
 * one timestamp and a duration cannot be computed at all. The per-hour trip
 * distribution is its approved replacement. Full reasoning in the
 * WeighbridgeReportService class docblock.
 *
 * TWO REQUIRED QUERY PARAMETERS ON /summary AND /export: `period_id` AND
 * `production_line_id`. A missing one is 422 VALIDATION_ERROR and ZERO
 * weighbridge_records queries run. There is deliberately NO all-lines
 * fallback — unlike the five earlier station reports, where
 * production_line_id stayed optional at the API layer so their already-shipped
 * mobile twins would not break. Weighbridge had no shipped mobile twin when
 * this prefix was written, so the parameter was required from day one — and
 * when screen-144 arrived it was built TO that stricter contract rather than
 * the contract being relaxed for it.
 *
 * NOTE WHICH GUARD CLOSES WHICH HOLE — three different answers on purpose:
 *   - `business_unit_id` from the client is IGNORED for the mill-bound roles
 *     (200 with their own mill's data, deliberately NOT 403 — a 403 would
 *     confirm the other mill exists);
 *   - a `production_line_id` belonging to another mill IS refused with 403 in
 *     WeighbridgeReportService::resolveProductionLine();
 *   - a `period_id` belonging to another mill IS refused with 403 in
 *     WeighbridgeReportService::authorizePeriod().
 *
 * OPERATOR IS ADMITTED ON THREE OF THE FOUR ROUTES SINCE 2026-10-05, when
 * screen-144 (the mobile Weighbridge report) was built on them. The widening
 * was three changes together — routes/api.php,
 * WeighbridgeReportService::guardAccess(), and the MILL-BOUND branch of
 * resolveBusinessUnit() — and the third is the load-bearing one: without it
 * Operator falls into the unbound Admin branch where the client's
 * business_unit_id IS honoured.
 *
 * THE FOURTH ROUTE, /business-units/options, STILL REFUSES OPERATOR with 403,
 * raised by the service rather than by the middleware. A role bound to one
 * mill has no picker, and handing it the list of every mill is exactly the
 * leak the widening had to avoid.
 *
 * The production-line OPTION LIST is NOT here: GET
 * /api/production-lines/options-for-report (built for screen-135) is reused
 * verbatim. It is not a new endpoint and is not duplicated under this prefix.
 */
class WeighbridgeReportController extends Controller
{
    public function __construct(protected WeighbridgeReportService $service) {}

    /**
     * businessUnitOptions() — GET /api/weighbridge-reports/business-units/options.
     *
     * Admin-only mill picker; the service throws 403 FORBIDDEN for anyone
     * else, since Operator, Supervisor and Mill Management are already bound to
     * one mill and have no picker at all. This is the one route on the prefix
     * that the screen-144 widening deliberately did NOT open. Enforced in the SERVICE rather than by this
     * route's middleware on purpose: the middleware admits all three roles for
     * the other endpoints, and handing a mill-bound role the list of every
     * mill is precisely the leak this screen must not open.
     */
    public function businessUnitOptions(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->businessUnitOptions(),
        ]);
    }

    /**
     * periods() — GET /api/weighbridge-reports/periods?business_unit_id=...
     *
     * business_logic steps 1-4. Returns { data: [] } with HTTP 200 when the
     * mill has no period covering Weighbridge yet — an empty picker plus a UI
     * hint pointing at Kelola Periode Pelaporan, never a 404. An Admin who did
     * not pick a mill gets 422 VALIDATION_ERROR; so does a bound account whose
     * users.business_unit_id is NULL, and in that case the all-mills list is
     * never read at all.
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
     * summary() — GET /api/weighbridge-reports/summary?period_id=...&production_line_id=...
     *
     * business_logic steps 5-18: resolve the mill (422), resolve the
     * production line (422 when absent / 403 when it belongs to another mill),
     * authorise the period (404 / 403), then every figure for that period on
     * that line — SPLIT INTO THE `receive` AND `dispatch` GROUPS, each with
     * its own trip_count, net-weight total / average / filled-trip
     * denominator, missing-weight count, 24 hourly buckets, busiest hour and
     * empty-hour count, plus the per-origin (receive) or per-destination
     * (dispatch) breakdown — then draft_trip_count, undated_trip_count, the
     * daily recap with the two flows in separate columns, and completeness.
     *
     * NOT ONE RETURNED KEY TOTALS THE TWO FLOWS, and no figure anywhere is
     * derived from a vehicle-in-plant duration.
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
     * export() — GET /api/weighbridge-reports/export?period_id=...&production_line_id=...&format=csv
     *
     * business_logic step 19: ONE ROW PER TRIP, with the period / mill /
     * production line / flow-type context columns repeated verbatim on every
     * row so the file can be pivoted straight in a spreadsheet. A trip whose
     * net weight is NULL stays a row with an EMPTY cell — never dropped, never
     * written as 0.
     *
     * THE HEADER CARRIES EXACTLY ONE TIMESTAMP COLUMN AND NO DURATION COLUMN.
     * NOT a JSON response. 422 EXPORT_FAILED above 50.000 rows, 422
     * VALIDATION_ERROR for a format outside csv|excel.
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
     * The line is filtered on `weighbridge_records.production_line_id` — the
     * record's own snapshot column — never through a join to `stations`, so a
     * station later moved to another line does not rewrite the trips it
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
