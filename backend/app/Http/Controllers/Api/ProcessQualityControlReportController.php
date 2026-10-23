<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProcessQualityControlReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ProcessQualityControlReportController — screen-160--laporan-process-quality-control-web +
 * screen-161--laporan-process-quality-control-mobile.
 * auth_requirement: authenticated, Supervisor / Mill Management / Admin /
 * Operator (see routes/api.php's
 * 'role:supervisor,mill_management,admin,operator' middleware).
 *
 * GET-ONLY BY DESIGN. There is no POST/PUT/PATCH/DELETE action here and none
 * on the /api/process-quality-control-reports prefix — this screen is a read-only report
 * and must not offer any path that could alter Process Quality Control data.
 *
 * All resolution / aggregation / export logic lives in
 * ProcessQualityControlReportService, shared with the Livewire component
 * (App\Livewire\Dashboard\LaporanProcessQualityControl), so the web page and the API
 * can never disagree on a figure.
 *
 * OPERATOR IS ADMITTED ON THE THREE DATA ROUTES FROM THE FIRST COMMIT, which
 * is the Grading / Kernel Plant / Engine Room precedent rather than the
 * Weighbridge / Effluent Plant one. There the web screen landed first,
 * Operator was refused until the mobile twin existed, and the widening then
 * had to be done as a follow-up. Here
 * screen-161--laporan-process-quality-control-mobile is built in the SAME series, so
 * admitting the role now avoids an expensive ripple.
 *
 * THE ADMISSION IS ONE CHANGE IN THREE PLACES, NOT THREE CHANGES: the role
 * list in routes/api.php, ProcessQualityControlReportService::guardAccess(), AND the
 * mill-bound branch of ProcessQualityControlReportService::resolveBusinessUnit().
 * Widening only the first two drops Operator into the Admin branch, where the
 * client's business_unit_id IS honoured, i.e. a cross-mill leak. WHAT STAYS
 * CLOSED: businessUnitOptions() is Admin only (403 raised INSIDE the service,
 * not by the middleware — precisely because this prefix's middleware admits
 * four roles for the sake of the three data routes), and the WEB route
 * /reports/process-quality-control is NOT widened.
 *
 * TWELVE MEASUREMENT COLUMNS, EACH WITH ITS OWN DENOMINATOR, GROUPED ALONG THE
 * PHYSICAL PATH OF THE OIL. `summary` returns min / avg / max for the twelve numeric
 * columns on process_quality_control_details, and EVERY ONE CARRIES ITS OWN
 * `reading_count` plus the `group` its titled block is built from —
 * water_flow | softener | chemical_dosing | boiler_feed, in the order water
 * actually flows through the station rather than the order the columns sit in
 * the database, so a reader tracing an unexpected boiler-feed figure can walk
 * backwards one stage at a time.
 *
 * A FILLED SLOT IS FOURTEEN COLUMNS, NOT SIXTEEN, AND THIS IS THE ONE RULE
 * MOST EASILY GOT WRONG HERE. A detail row carries sixteen columns, but
 * `shift` and `qc_inspector_id` are IDENTITY columns — ProcessQualityControlRecordService'
 * s docblock says so in those words — and both are EXCLUDED from
 * ProcessQualityControlRecordService::isRowFilled(), which this report BORROWS rather
 * than re-derives. Two opposite consequences, and they must not be confused:
 * a slot that filled only a free-text column DOES count (both text columns
 * are among the fourteen) while adding to no metric's denominator, which is why
 * coverage.filled_slots can EXCEED every per-metric count and is stated on
 * screen; a slot that filled only the two identity columns does NOT count.
 *
 * THERE IS NOT ONE CATEGORICAL DISTRIBUTION KEY IN THIS PAYLOAD, and the
 * absence is asserted. This station has NO enum column at all.
 * `qc_engineering_corrective_actions` is a plain free-text string DESPITE ITS NAME —
 * verified three ways: the web input form renders it <input type="text">
 * beside `findings`, the mobile form's label reads 'Action Taken/Status' WITH
 * A SLASH so 'status' is the second half of a phrase, and there are zero
 * Display::OPTION_LABELS entries. It is published as a TEXT LIST on
 * `text_entries`, one entry per slot, and repeated identical contents are NOT
 * grouped: grouping free text makes every distinct sentence its own "value".
 *
 * FOR THE SAME REASON THE OPTION-LABEL MAPPER IS NOT CALLED ANYWHERE ON THIS
 * SCREEN. Only ExportValue::status() (the RECORD's status) and
 * ExportValue::time() are used, so the status()/option() swap that shadows
 * Engine Room and Effluent Plant cannot happen here.
 *
 * THE PER-INSPECTOR RECAP IS PUBLISHED, AND IT IS RENDERED ON SCREEN — a
 * DELIBERATE DIVERGENCE from Storage Tank, whose service docblock keeps such
 * columns to the export, requested by the product owner on 2026-10-16.
 * `qc_inspector_id` has NO relation to `users`, so the code comes back EXACTLY
 * AS STORED and no name is ever resolved. `not_recorded` is its own key and
 * shares use `recorded_slots`, never expected_slots. The recap exists to
 * chase unfilled slots, not to grade people.
 *
 * THE `shift` COLUMN IS ABSENT FROM /summary BUT PRESENT IN /export. It is
 * empty on every row and is scheduled to be replaced by a relation to the
 * Shift master, so publishing it now would publish an empty column about to
 * change meaning; the screen states that reason. The export carries it anyway,
 * because the export is the RAW detail.
 *
 * THERE IS NO DOWNTIME BLOCK AND NO OUT-OF-RANGE FLAGGING.
 * process_quality_control_details has no downtime column, and Process Quality Control has no
 * operational-target master table, so the payload carries no threshold /
 * severity / out-of-range key of any kind — and that holds EVEN THOUGH boiler
 * feed water pH and TDS have limits very widely known outside this
 * application. A threshold derived from the period's own data is a statistic
 * that would be read as a boiler safety limit. Full reasoning in the
 * ProcessQualityControlReportService class docblock.
 *
 * TWO REQUIRED QUERY PARAMETERS ON /summary AND /export: `period_id` AND
 * `production_line_id`. A missing one is 422 VALIDATION_ERROR and ZERO
 * process_quality_control_records queries run. There is deliberately NO all-lines
 * fallback — a total that mixes a dozen production lines is not a number
 * anyone can act on.
 *
 * NOTE WHICH GUARD CLOSES WHICH HOLE — three different answers on purpose:
 *   - `business_unit_id` from the client is IGNORED for a mill-bound role,
 *     Operator included (200 with their own mill's data, deliberately NOT 403
 *     — a 403 would confirm the other mill exists);
 *   - a `production_line_id` belonging to another mill is REFUSED with 403 in
 *     ProcessQualityControlReportService::resolveProductionLine(), before a single
 *     record query runs;
 *   - a `period_id` belonging to another mill is REFUSED with 403 in
 *     ProcessQualityControlReportService::authorizePeriod(), AFTER the line guard, so
 *     an unauthorised caller never learns which period ids exist.
 *
 * The production-line OPTION LIST is NOT here: GET
 * /api/production-lines/options-for-report (built for screen-135) is reused
 * verbatim. It is not a new endpoint and is not duplicated under this prefix.
 */
class ProcessQualityControlReportController extends Controller
{
    public function __construct(protected ProcessQualityControlReportService $service) {}

    /**
     * businessUnitOptions() — GET /api/process-quality-control-reports/business-units/options.
     *
     * Admin-only mill picker; the service throws 403 FORBIDDEN for anyone
     * else, since Operator, Supervisor and Mill Management are each already
     * bound to one mill and have no picker at all. Enforced in the SERVICE
     * rather than by this route's middleware on purpose — THIS PREFIX'S
     * MIDDLEWARE ADMITS FOUR ROLES for the sake of the three data routes, so a
     * guard in the middleware would not close this endpoint at all.
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
     * periods() — GET /api/process-quality-control-reports/periods?business_unit_id=...
     *
     * business_logic step 6. Returns { data: [] } with HTTP 200 when the mill
     * has no period covering Process Quality Control yet — an empty picker plus a UI
     * hint pointing at Kelola Periode Pelaporan, never a 404. An Admin who did
     * not pick a mill gets 422 VALIDATION_ERROR; so does a bound account whose
     * users.business_unit_id is NULL, and in that case the all-mills list is
     * never read at all.
     *
     * A period "covers Process Quality Control" when it has a period_stations row for
     * station_type 'process-quality-control' — WITH A HYPHEN. The service reads that
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
     * summary() — GET /api/process-quality-control-reports/summary?period_id=...&production_line_id=...
     *
     * business_logic steps 1-28: resolve the mill (422), resolve the production
     * line (422 when absent, 403 for another mill's line), authorise the period
     * (404 / 403), then every figure for that period on that line — recording
     * coverage FIRST, then the twelve metrics each with its own denominator, its own `group` and its own `unit`, and
     * its own `group`, the TWO separate free-text lists, the per-inspector
     * recap, the one daily trend (an AVERAGE per date), the per-unit recap, the
     * daily recap, and the period totals.
     *
     * coverage publishes days_counted BESIDE period_running, and the screen
     * must read days_counted === 0 FIRST: a period whose first day has not
     * arrived yet is "belum dimulai", not "running with nothing recorded".
     * expected_slots === 0 yields coverage_percent 0.0 and the screen renders
     * '—', never '0,0%'.
     *
     * NOT ONE KEY IN THE RESPONSE CARRIES A CATEGORICAL DISTRIBUTION, a
     * downtime figure, a threshold, or a per-shift dimension. Those four
     * absences are deliberate, each for its own reason, and all four are
     * stated on the screen so none of them reads as a section that failed to
     * load.
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
     * export() — GET /api/process-quality-control-reports/export?period_id=...&production_line_id=...&format=csv
     *
     * business_logic step 29: ONE ROW PER TIME SLOT, with the period / mill /
     * production line / date / unit Process Quality Control / status / note context
     * columns repeated verbatim on every row so the file can be pivoted
     * straight in a spreadsheet. A slot whose measurement columns are all
     * empty stays a row with EMPTY cells — never dropped, never written as 0.
     *
     * ALL SIXTEEN detail columns are in the file, `shift` and `qc_inspector_id`
     * INCLUDED: excluding the two identity columns from the filled-slot check
     * is not a reason to drop them from the export, because the export is the
     * RAW detail and a file that cannot stand in for the input screen is not
     * worth exporting. The sixteen labels are the Detail screen's own labels
     * (screen-102), verbatim.
     *
     * THE ONLY TWO VALUE MAPPERS USED ARE ExportValue::status() for the
     * context 'Status' column (the RECORD's status — Tersimpan / Tersinkron)
     * and ExportValue::time() for the HH:MM slot label. The option-label
     * mapper is NOT used, because this station has no measurement-enum column
     * at all.
     *
     * NOT a JSON response. 422 EXPORT_FAILED above 50.000 rows — counted over
     * SLOT ROWS, not records, because one daily record carries up to 24 of
     * them. 422 VALIDATION_ERROR for a format outside csv|excel.
     *
     * The permission guard, the mill resolution, the line resolution, the
     * format check and the row-limit check all run EAGERLY inside the service,
     * before the response starts streaming — otherwise a 403/422 would reach
     * the client as a successful but empty download.
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
     * worst of both worlds: it answers 200 with a figure nobody asked for. The
     * refusal is raised here AND again inside the service, so a direct service
     * call cannot skip it either.
     *
     * The line is filtered on `process_quality_control_records.production_line_id` — the
     * record's own snapshot column — never through a join to `stations`, so a
     * station later moved to another line does not rewrite the readings it
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
     * `period_id` is required on both summary and export — a missing one is 422
     * VALIDATION_ERROR with errors.period_id, not a 404 for the empty string
     * and not a silent empty report.
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
