<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EffluentPlantReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * EffluentPlantReportController — screen-156--laporan-effluent-plant-web.
 * auth_requirement: authenticated, Supervisor / Mill Management / Admin (see
 * routes/api.php's 'role:supervisor,mill_management,admin' middleware).
 *
 * GET-ONLY BY DESIGN. There is no POST/PUT/PATCH/DELETE action here and none
 * on the /api/effluent-plant-reports prefix — this screen is a read-only
 * report and must not offer any path that could alter Effluent Plant data.
 *
 * All resolution / aggregation / export logic lives in
 * EffluentPlantReportService, shared with the Livewire component
 * (App\Livewire\Dashboard\LaporanEffluentPlant), so the web page and the API
 * can never disagree on a figure.
 *
 * OPERATOR IS REFUSED 403 ON ALL FOUR ROUTES, and that is the one thing that
 * differs from the Grading / Kernel Plant prefixes, which admit Operator on
 * three of their four from the first commit. The reason is not an oversight:
 * the mobile twin of this screen (screen-157) has NOT been built, so there is
 * no legitimate Operator caller today, and admitting a role before its caller
 * exists is access nobody can review. This follows the Weighbridge precedent
 * (screen-143 shipped first; Operator was added when screen-144 was built).
 * WHEN screen-157 IS BUILT, the widening must touch BOTH the role list here
 * and in EffluentPlantReportService::guardAccess() AND the mill-bound branch
 * of EffluentPlantReportService::resolveBusinessUnit() — widening only the
 * first would drop Operator into the Admin branch, where the client's
 * business_unit_id IS honoured, i.e. a cross-mill leak.
 *
 * FOURTEEN METRICS, FOURTEEN DENOMINATORS. `summary` returns min / avg / max
 * for the fourteen numeric columns on effluent_plant_details, and EACH ONE
 * CARRIES ITS OWN `reading_count`: every column is nullable, and a slot
 * counts as filled when ANY ONE of the NINETEEN reading columns is filled
 * (EffluentPlantRecordService::isRowFilled(), borrowed rather than
 * re-derived), so one shared denominator would be wrong for at least thirteen
 * of the fourteen. Three of them (final_discharge_bod/cod/tss_mgl_lab) carry
 * `is_lab: true`: they are LAB results sampled every few days, so their much
 * smaller denominator is the NORMAL state, not missing data. A
 * findings-only slot counts in coverage.filled_slots while adding to no
 * metric's denominator — which is why that figure can exceed every per-metric
 * count, and is not a bug.
 *
 * THREE CATEGORICAL STATUS COLUMNS ARE A DISTRIBUTION, NEVER AN AVERAGE.
 * biogas_flare_status (on|off|fault), dosing_pump_1_status (run|stop) and
 * sludge_dewatering_status (run|stop) are published as slot counts per value,
 * with known values that never occurred filled in as 0, and NULL slots on a
 * SEPARATE `not_recorded` key that is never folded into 'off' or 'stop'.
 * None of the three ever appears in `metrics` or in `daily_trend`.
 *
 * TWO FREE-TEXT COLUMNS, TWO SEPARATE LISTS. `maintenance_remarks` answers
 * what was done, `findings` answers what was seen; a slot filling both
 * appears in both, and merging them would count it twice inside one list.
 *
 * THERE IS NO DOWNTIME BLOCK AND NO COMPLIANCE VERDICT.
 * effluent_plant_details has no downtime column at all, and Effluent Plant
 * has no operational-target master table, so the payload carries no
 * threshold / severity / out-of-range key of any kind. The four final
 * discharge parameters are ENVIRONMENTAL figures whose regulatory limits live
 * outside this application: the payload publishes the numbers and says
 * nothing about compliance. Full reasoning in the
 * EffluentPlantReportService class docblock.
 *
 * TWO REQUIRED QUERY PARAMETERS ON /summary AND /export: `period_id` AND
 * `production_line_id`. A missing one is 422 VALIDATION_ERROR and ZERO
 * effluent_plant_records queries run. There is deliberately NO all-lines
 * fallback — a total that mixes a dozen production lines is not a number
 * anyone can act on.
 *
 * NOTE WHICH GUARD CLOSES WHICH HOLE — three different answers on purpose:
 *   - `business_unit_id` from the client is IGNORED for a mill-bound role
 *     (200 with their own mill's data, deliberately NOT 403 — a 403 would
 *     confirm the other mill exists);
 *   - a `production_line_id` belonging to another mill is REFUSED with 403 in
 *     EffluentPlantReportService::resolveProductionLine(), before a single
 *     record query runs;
 *   - a `period_id` belonging to another mill is REFUSED with 403 in
 *     EffluentPlantReportService::authorizePeriod(), AFTER the line guard, so
 *     an unauthorised caller never learns which period ids exist.
 *
 * The production-line OPTION LIST is NOT here: GET
 * /api/production-lines/options-for-report (built for screen-135) is reused
 * verbatim. It is not a new endpoint and is not duplicated under this prefix.
 */
class EffluentPlantReportController extends Controller
{
    public function __construct(protected EffluentPlantReportService $service) {}

    /**
     * businessUnitOptions() — GET /api/effluent-plant-reports/business-units/options.
     *
     * Admin-only mill picker; the service throws 403 FORBIDDEN for anyone
     * else, since Supervisor and Mill Management are already bound to one
     * mill and have no picker at all. Enforced in the SERVICE rather than by
     * this route's middleware on purpose: when screen-157 widens the
     * middleware, this endpoint must stay closed.
     *
     * An empty master is a valid answer: { data: [] } with HTTP 200, never a
     * 404.
     */
    public function businessUnitOptions(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->businessUnitOptions(),
        ]);
    }

    /**
     * periods() — GET /api/effluent-plant-reports/periods?business_unit_id=...
     *
     * business_logic step 6. Returns { data: [] } with HTTP 200 when the mill
     * has no period covering Effluent Plant yet — an empty picker plus a UI
     * hint pointing at Kelola Periode Pelaporan, never a 404. An Admin who
     * did not pick a mill gets 422 VALIDATION_ERROR; so does a bound account
     * whose users.business_unit_id is NULL, and in that case the all-mills
     * list is never read at all.
     *
     * A period "covers Effluent Plant" when it has a period_stations row for
     * station_type 'effluent-plant' — WITH A HYPHEN. The service reads that
     * value from App\Enums\StationType rather than writing it out, because an
     * underscore here would match no row and answer [] for every mill with no
     * error at all.
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
     * summary() — GET /api/effluent-plant-reports/summary?period_id=...&production_line_id=...
     *
     * business_logic steps 1-26: resolve the mill (422), resolve the
     * production line (422 when absent, 403 for another mill's line),
     * authorise the period (404 / 403), then every figure for that period on
     * that line — recording coverage FIRST, then the fourteen metrics each
     * with its own denominator and its own is_lab marker, the three status
     * distributions, the two separate free-text lists, the two daily trends,
     * the per-unit recap, the daily recap, and the period totals.
     *
     * coverage publishes days_counted BESIDE period_running, and the screen
     * must read days_counted === 0 FIRST: a period whose first day has not
     * arrived yet is "belum dimulai", not "running with nothing recorded".
     * expected_slots === 0 yields coverage_percent 0.0 and the screen renders
     * '—', never '0,0%'.
     *
     * NOT ONE KEY STATES ENVIRONMENTAL COMPLIANCE, and not one key flags a
     * value as out of range.
     */
    public function summary(Request $request): JsonResponse
    {
        $businessUnitId = $this->queryString($request, 'business_unit_id');

        // Ordered on purpose: an Admin with no mill is 422, and a missing
        // production line is 422, BEFORE the period is looked up — so an
        // incomplete request never turns into a 404/403 about a period it was
        // never entitled to ask about.
        $effectiveBusinessUnitId = $this->service->resolveBusinessUnit($businessUnitId);

        $productionLineId = $this->requireProductionLineId($request);

        $this->service->resolveProductionLine($effectiveBusinessUnitId, $productionLineId);

        $periodId = $this->requirePeriodId($request);

        return response()->json(
            $this->service->buildSummary($periodId, $businessUnitId, $productionLineId),
        );
    }

    /**
     * export() — GET /api/effluent-plant-reports/export?period_id=...&production_line_id=...&format=csv
     *
     * business_logic step 27: ONE ROW PER TIME SLOT, with the period / mill /
     * production line / date / unit effluent plant / status / note context
     * columns repeated verbatim on every row so the file can be pivoted
     * straight in a spreadsheet. A slot whose measurement columns are all
     * empty stays a row with EMPTY cells — never dropped, never written as 0.
     *
     * ALL NINETEEN reading columns are in the file, the three status columns
     * and the two free-text ones included: dropping any would make the export
     * unable to stand in for the report, which is the whole point of
     * exporting it. There is no 'patuh'/'tidak patuh' column, because this
     * report states no compliance verdict anywhere.
     *
     * NOT a JSON response. 422 EXPORT_FAILED above 50.000 rows — counted over
     * SLOT ROWS, not records, because one daily record carries up to 24 of
     * them. 422 VALIDATION_ERROR for a format outside csv|excel.
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
     * widening the scope of a report because a parameter was forgotten is the
     * worst of both worlds: it answers 200 with a figure nobody asked for.
     * The refusal is raised here AND again inside the service, so a direct
     * service call cannot skip it either.
     *
     * The line is filtered on `effluent_plant_records.production_line_id` —
     * the record's own snapshot column — never through a join to `stations`,
     * so a station later moved to another line does not rewrite the readings
     * it already produced.
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
