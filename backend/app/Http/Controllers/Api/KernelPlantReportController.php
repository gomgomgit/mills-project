<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KernelPlantReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * KernelPlantReportController — screen-154--laporan-kernel-plant-web and
 * screen-155--laporan-kernel-plant-mobile. auth_requirement: authenticated,
 * Operator / Supervisor / Mill Management / Admin (see routes/api.php's
 * 'role:supervisor,mill_management,admin,operator' middleware).
 *
 * GET-ONLY BY DESIGN. There is no POST/PUT/PATCH/DELETE action here and none
 * on the /api/kernel-plant-reports prefix — this screen is a read-only report
 * and must not offer any path that could alter Kernel Plant data.
 *
 * All resolution / aggregation / export logic lives in
 * KernelPlantReportService, shared with the Livewire component
 * (App\Livewire\Dashboard\LaporanKernelPlant), so the web page, the API and
 * the phone can never disagree on a figure.
 *
 * THIS REPORT SUMMARISES CONDITION, NOT OUTPUT. `summary` returns min / avg /
 * max for the SEVEN numeric measurement columns on kernel_plant_details, and
 * EACH ONE CARRIES ITS OWN `filled_slot_count`: every column is nullable and a
 * slot counts as filled when ANY ONE of the nine reading columns is filled
 * (KernelPlantRecordService::isRowFilled(), borrowed rather than
 * re-derived), so one shared denominator would be wrong for at least six of
 * the seven. A findings-only slot therefore counts in coverage.filled_slots
 * while adding to no metric's denominator — which is why that figure can
 * exceed every per-metric count, and is not a bug.
 *
 * THE MASTER CARRIES THREE COLUMNS, AND ALL THREE ARE PUBLISHED.
 * kernel_plant_operational_targets supplies `equipment_parameter` (what is
 * judged — 'Kernel Silo 1 & 2'), `target_benchmark` (where it SHOULD be —
 * '70°C - 80°C (Top/Middle zones)', rendered whole, parenthetical and all)
 * and `corrective_action_plan` (what to DO about it — 'Check heater
 * elements/steam valves if temperature drops below 65°C.'). There is NO
 * critical-limit column on this master, so the payload has no
 * `critical_limit` key and no `operational_consequence_justification` key —
 * unlike Depricarping's four-key target block. Note the column names: NOT ONE
 * of the three is shared with threshing_, pressing_ or
 * depricarping_operational_targets, and copying a neighbour's names yields a
 * target block that is entirely null with no error raised at all.
 *
 * SEVEN MEASUREMENT COLUMNS, FIVE DISTINCT STANDARDS, SIX MASTER ROWS. TWO
 * standards each govern TWO columns here — 'Ripple Mill (Cracker)' over both
 * ripple mill amps, 'Kernel Silo 1 & 2' over both silo temperatures. Both
 * halves of each pair keep their own figures and their own denominators (two
 * physical units; averaging a pair would hide a drifting one behind a normal
 * one), and the shared standard is published as
 * `metrics[].target.shares_standard_with` so the repeated standard reads as
 * deliberate rather than as duplicated data someone should clean up.
 * Depricarping had exactly ONE such pair, which is why code that special-cases
 * a single pair passes there and is wrong here.
 *
 * ONE STANDARD HAS NO MEASUREMENT, AND THAT IS THE STEADY STATE.
 * `targets_without_metric` normally holds exactly one row: 'Final Kernel
 * Dirt' (≤ 6.0%), reason 'no_column' — there is no dirt column ANYWHERE in
 * this schema, not merely none in kernel_plant_details, so nothing in the log
 * sheet records it. Published rather than hidden, because a standard that is
 * never measured reads as satisfied when it is merely absent — and this one
 * names the quality premium the mill is paid on. `all_targets_measured` lets
 * the screen DRAW that section even when it is empty.
 *
 * AND IT STILL FLAGS NOTHING. There is no severity key, no is_out_of_range
 * key and no colouring anywhere in the payload. The reason is easier to argue
 * here than on Depricarping: this master has no critical-limit column at all,
 * so the only numbers available sit inside `target_benchmark`, and every one
 * of the six rows carries something else in the same string — a parenthetical
 * about a DIFFERENT quantity ('20 - 25 Amps (Nut Breakage >95%)'), a unit
 * written as a leading phrase ('Specific Gravity 1.18 - 1.24'), a zone
 * qualifier, or a reason ('≤ 7.0% (Prevents mold growth)'). Nothing in the
 * schema constrains their shape either, so a parser's input set is not fixed
 * at build time and a parser that then fails STOPS WARNING without raising
 * anything — a warning that disappears reads as "everything is fine". Full
 * reasoning in the KernelPlantReportService class docblock.
 *
 * DOWNTIME IS A NUMBER ON THIS STATION, like Depricarping and unlike
 * Threshing/Pressing: `downtime` publishes total minutes, how many slots
 * recorded it, and the average per recording slot — null, never 0, when
 * nothing recorded it, and a recorded 0 COUNTS as recorded. `findings` is
 * published beside it as a literal grouping of the free text. The two are
 * never merged: one answers "how long", the other "what was seen".
 *
 * TWO REQUIRED QUERY PARAMETERS ON /summary AND /export: `period_id` AND
 * `production_line_id`. A missing one is 422 VALIDATION_ERROR and ZERO
 * kernel_plant_records queries run. There is deliberately NO all-lines
 * fallback — a total that mixes a dozen production lines is not a number
 * anyone can act on.
 *
 * NOTE WHICH GUARD CLOSES WHICH HOLE — three different answers on purpose:
 *   - `business_unit_id` from the client is IGNORED for every mill-bound
 *     role (200 with their own mill's data, deliberately NOT 403 — a 403
 *     would confirm the other mill exists);
 *   - a `production_line_id` belonging to another mill is resolved to null in
 *     KernelPlantReportService::resolveProductionLine(), so the screen asks
 *     for a line instead of being told the line exists;
 *   - a `period_id` belonging to another mill IS refused with 403 in
 *     KernelPlantReportService::authorizePeriod().
 *
 * OPERATOR IS ADMITTED ON THREE OF THE FOUR ROUTES FROM DAY ONE, because the
 * mobile twin (screen-155) is built in the same series. THE FOURTH,
 * /business-units/options, STILL REFUSES OPERATOR with 403, raised by the
 * service rather than by the middleware: a role bound to one mill has no
 * picker, and handing it the list of every mill is exactly the leak this
 * must not open. The WEB route /reports/kernel-plant is not widened either.
 *
 * The production-line OPTION LIST is NOT here: GET
 * /api/production-lines/options-for-report (built for screen-135) is reused
 * verbatim. It is not a new endpoint and is not duplicated under this prefix.
 */
class KernelPlantReportController extends Controller
{
    public function __construct(protected KernelPlantReportService $service) {}

    /**
     * businessUnitOptions() — GET /api/kernel-plant-reports/business-units/options.
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
     * periods() — GET /api/kernel-plant-reports/periods?business_unit_id=...
     *
     * business_logic step 5. Returns { data: [] } with HTTP 200 when the
     * mill has no period covering Kernel Plant yet — an empty picker plus a
     * UI hint pointing at Kelola Periode Pelaporan, never a 404. An Admin
     * who did not pick a mill gets 422 VALIDATION_ERROR; so does a bound
     * account whose users.business_unit_id is NULL, and in that case the
     * all-mills list is never read at all.
     *
     * A period "covers Kernel Plant" when it has a period_stations row for
     * station_type 'kernel-plant' — WITH A HYPHEN. The service reads that
     * value from App\Enums\StationType rather than writing it out, because
     * an underscore here would match no row and answer [] for every mill
     * with no error at all.
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
     * summary() — GET /api/kernel-plant-reports/summary?period_id=...&production_line_id=...
     *
     * business_logic steps 1-24: resolve the mill (422), resolve the
     * production line (422 when absent), authorise the period (404 / 403),
     * then every figure for that period on that line — recording coverage
     * FIRST, then the seven metrics each with its own denominator and its
     * own operational standard, the standard that has no measurement at all,
     * the per-kernel-plant recap, the daily recap, the downtime block, the
     * literal findings recap, and the period totals.
     *
     * coverage publishes days_counted BESIDE period_running, and the screen
     * must read days_counted === 0 FIRST: a period whose first day has not
     * arrived yet is "belum dimulai", not "running with nothing recorded".
     *
     * NOT ONE KEY COMPARES A FIGURE TO THE TARGET BENCHMARK, and that
     * absence is asserted by a unit test that sweeps the whole payload.
     */
    public function summary(Request $request): JsonResponse
    {
        $businessUnitId = $this->queryString($request, 'business_unit_id');

        // Ordered on purpose: an Admin with no mill is 422, and a missing
        // production line is 422, BEFORE the period is looked up — so an
        // incomplete request never turns into a 404/403 about a period it
        // was never entitled to ask about.
        $effectiveBusinessUnitId = $this->service->resolveBusinessUnit($businessUnitId);

        $productionLineId = $this->requireProductionLineId($request);

        $this->service->resolveProductionLine($effectiveBusinessUnitId, $productionLineId);

        $periodId = $this->requirePeriodId($request);

        return response()->json(
            $this->service->buildSummary($periodId, $businessUnitId, $productionLineId),
        );
    }

    /**
     * export() — GET /api/kernel-plant-reports/export?period_id=...&production_line_id=...&format=csv
     *
     * business_logic step 25: ONE ROW PER TIME SLOT, with the period / mill
     * / production line / date / unit kernel plant / status / note context
     * columns repeated verbatim on every row so the file can be pivoted
     * straight in a spreadsheet. A slot whose measurement columns are all
     * empty stays a row with EMPTY cells — never dropped, never written as 0.
     *
     * ALL NINE reading columns are in the file, downtime and findings
     * included: dropping either would make the export unable to stand in for
     * the report, which is the whole point of exporting it.
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
     * The line is filtered on `kernel_plant_records.production_line_id` —
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
