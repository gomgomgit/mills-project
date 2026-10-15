<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EngineRoomReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * EngineRoomReportController — screen-158--laporan-engine-room-web +
 * screen-159--laporan-engine-room-mobile.
 * auth_requirement: authenticated, Supervisor / Mill Management / Admin /
 * Operator (see routes/api.php's
 * 'role:supervisor,mill_management,admin,operator' middleware).
 *
 * GET-ONLY BY DESIGN. There is no POST/PUT/PATCH/DELETE action here and none
 * on the /api/engine-room-reports prefix — this screen is a read-only report
 * and must not offer any path that could alter Engine Room data.
 *
 * All resolution / aggregation / export logic lives in
 * EngineRoomReportService, shared with the Livewire component
 * (App\Livewire\Dashboard\LaporanEngineRoom), so the web page and the API can
 * never disagree on a figure.
 *
 * OPERATOR IS ADMITTED ON THE THREE DATA ROUTES FROM THE FIRST COMMIT, which
 * is the Grading / Kernel Plant precedent rather than the Weighbridge /
 * Effluent Plant one. There the web screen landed first, Operator was refused
 * until the mobile twin existed, and the widening then had to be done as a
 * follow-up. Here screen-159--laporan-engine-room-mobile is built in the SAME
 * series, so admitting the role now avoids an expensive ripple: three test
 * files whose assertions would have to be inverted and four artifact fields
 * that would have to be updated.
 *
 * THE ADMISSION IS ONE CHANGE IN THREE PLACES, NOT THREE CHANGES: the role
 * list in routes/api.php, EngineRoomReportService::guardAccess(), AND the
 * mill-bound branch of EngineRoomReportService::resolveBusinessUnit().
 * Widening only the first two drops Operator into the Admin branch, where the
 * client's business_unit_id IS honoured, i.e. a cross-mill leak. WHAT STAYS
 * CLOSED: businessUnitOptions() is Admin only (403 raised INSIDE the service,
 * not by the middleware — precisely because this prefix's middleware admits
 * four roles for the sake of the three data routes), and the WEB route
 * /reports/engine-room is NOT widened.
 *
 * TWENTY-THREE MEASUREMENT COLUMNS, TWENTY-TWO OF THEM AVERAGED, EACH WITH
 * ITS OWN DENOMINATOR. `summary` returns min / avg / max for twenty-two of
 * the numeric columns on engine_room_details, and EVERY ONE CARRIES ITS OWN
 * `reading_count` plus the `group` its titled block is built from: every
 * column is nullable, and a slot counts as filled when ANY ONE of the
 * TWENTY-SEVEN reading columns is filled
 * (EngineRoomRecordService::isRowFilled(), borrowed rather than re-derived),
 * so one shared denominator would be wrong for at least twenty-one of them. A
 * findings-only slot counts in coverage.filled_slots while adding to no
 * metric's denominator — which is why that figure can EXCEED every per-metric
 * count, and is not a bug.
 *
 * THE TWENTY-THIRD COLUMN IS NOT IN `metrics` AT ALL.
 * daily_energy_export_kwh has NO period average anywhere in the payload: its
 * name says 'daily' while it is stored PER TIME SLOT, and that mismatch is
 * unexplained in every source. Under the reading "cumulative daily meter",
 * averaging it across 24 slots publishes a figure that looks entirely valid
 * and is entirely wrong. It is published on its own `daily_energy_export` key
 * as a per-date series of the LAST value of that day — taken from the slot's
 * POSITION on the canonical 07:00..06:00 grid, never from a lexical hour
 * comparison — with the source time_slot alongside it so the reader can check
 * it.
 *
 * THE EIGHT GENSET COLUMNS STAY EIGHT SEPARATE KEYS. Genset 1 and Genset 2
 * carry four identical columns each, and the payload contains NO combined
 * average, NO total, NO delta, NO imbalance and NO ratio for any of the four
 * pairs: a combined average hides the load imbalance that is the whole reason
 * those parameters are measured. Unlike Kernel Plant's ripple mill / kernel
 * silo pair there is no `shares_standard_with` either, because Engine Room has
 * no operational-target master row for the two to share.
 *
 * TWO CATEGORICAL STATUS COLUMNS WITH THREE VALUES EACH ARE A DISTRIBUTION,
 * NEVER AN AVERAGE. diesel_gen_1_status and diesel_gen_2_status
 * (run|standby|off) are published as slot counts per value, with known values
 * that never occurred filled in as 0, and NULL slots on a SEPARATE
 * `not_recorded` key that is never folded into run, standby or off. 'STANDBY'
 * IS NEVER FOLDED INTO 'OFF' — a standby genset is ready to take load, a dead
 * one is not. Shares use recorded_slots, never expected_slots. Neither column
 * ever appears in `metrics` or in `daily_trend`.
 *
 * TWO FREE-TEXT COLUMNS, TWO SEPARATE LISTS. `maintenance_remarks` answers
 * what was done, `findings` answers what was seen; a slot filling both appears
 * in both, and merging them would count it twice inside one list.
 *
 * THERE IS NO DOWNTIME BLOCK AND NO OUT-OF-RANGE FLAGGING.
 * engine_room_details has no downtime column at all, and Engine Room has no
 * operational-target master table, so the payload carries no threshold /
 * severity / out-of-range key of any kind — and that holds EVEN THOUGH system
 * frequency, power factor and busbar voltage have limits that are very widely
 * known outside this application. A threshold derived from the period's own
 * data is a statistic that would be read as an electrical safety limit. Full
 * reasoning in the EngineRoomReportService class docblock.
 *
 * TWO REQUIRED QUERY PARAMETERS ON /summary AND /export: `period_id` AND
 * `production_line_id`. A missing one is 422 VALIDATION_ERROR and ZERO
 * engine_room_records queries run. There is deliberately NO all-lines
 * fallback — a total that mixes a dozen production lines is not a number
 * anyone can act on.
 *
 * NOTE WHICH GUARD CLOSES WHICH HOLE — three different answers on purpose:
 *   - `business_unit_id` from the client is IGNORED for a mill-bound role,
 *     Operator included (200 with their own mill's data, deliberately NOT 403
 *     — a 403 would confirm the other mill exists);
 *   - a `production_line_id` belonging to another mill is REFUSED with 403 in
 *     EngineRoomReportService::resolveProductionLine(), before a single
 *     record query runs;
 *   - a `period_id` belonging to another mill is REFUSED with 403 in
 *     EngineRoomReportService::authorizePeriod(), AFTER the line guard, so an
 *     unauthorised caller never learns which period ids exist.
 *
 * The production-line OPTION LIST is NOT here: GET
 * /api/production-lines/options-for-report (built for screen-135) is reused
 * verbatim. It is not a new endpoint and is not duplicated under this prefix.
 */
class EngineRoomReportController extends Controller
{
    public function __construct(protected EngineRoomReportService $service) {}

    /**
     * businessUnitOptions() — GET /api/engine-room-reports/business-units/options.
     *
     * Admin-only mill picker; the service throws 403 FORBIDDEN for anyone
     * else, since Operator, Supervisor and Mill Management are each already
     * bound to one mill and have no picker at all. Enforced in the SERVICE
     * rather than by this route's middleware on purpose — THIS PREFIX'S
     * MIDDLEWARE ADMITS FOUR ROLES for the sake of the three data routes, so
     * a guard in the middleware would not close this endpoint at all.
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
     * periods() — GET /api/engine-room-reports/periods?business_unit_id=...
     *
     * business_logic step 6. Returns { data: [] } with HTTP 200 when the mill
     * has no period covering Engine Room yet — an empty picker plus a UI hint
     * pointing at Kelola Periode Pelaporan, never a 404. An Admin who did not
     * pick a mill gets 422 VALIDATION_ERROR; so does a bound account whose
     * users.business_unit_id is NULL, and in that case the all-mills list is
     * never read at all.
     *
     * A period "covers Engine Room" when it has a period_stations row for
     * station_type 'engine-room' — WITH A HYPHEN. The service reads that
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
     * summary() — GET /api/engine-room-reports/summary?period_id=...&production_line_id=...
     *
     * business_logic steps 1-28: resolve the mill (422), resolve the
     * production line (422 when absent, 403 for another mill's line),
     * authorise the period (404 / 403), then every figure for that period on
     * that line — recording coverage FIRST, then the twenty-two averaged
     * metrics each with its own denominator and its own `group`, the
     * energy-export series (LAST value per date, no period average), the two
     * three-valued status distributions, the two separate free-text lists,
     * the one daily trend (an AVERAGE per date), the per-unit recap, the
     * daily recap, and the period totals.
     *
     * coverage publishes days_counted BESIDE period_running, and the screen
     * must read days_counted === 0 FIRST: a period whose first day has not
     * arrived yet is "belum dimulai", not "running with nothing recorded".
     * expected_slots === 0 yields coverage_percent 0.0 and the screen renders
     * '—', never '0,0%'.
     *
     * NOT ONE KEY FLAGS A VALUE AS OUT OF RANGE, and not one key combines,
     * differences or compares the two gensets.
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
     * export() — GET /api/engine-room-reports/export?period_id=...&production_line_id=...&format=csv
     *
     * business_logic step 29: ONE ROW PER TIME SLOT, with the period / mill /
     * production line / date / unit engine room / status / note context
     * columns repeated verbatim on every row so the file can be pivoted
     * straight in a spreadsheet. A slot whose measurement columns are all
     * empty stays a row with EMPTY cells — never dropped, never written as 0.
     *
     * ALL TWENTY-SEVEN reading columns are in the file, the two status
     * columns and the two free-text ones included: dropping any would make
     * the export unable to stand in for the report, which is the whole point
     * of exporting it. The two genset status columns are written with the
     * Detail screen's own labels (Run / Standby / Off via
     * Display::OPTION_LABELS) while the context 'Status' column is the
     * RECORD's status (Tersimpan / Tersinkron) — two similarly named mappers
     * that must not be swapped.
     *
     * daily_energy_export_kwh IS EXPORTED AS IT STANDS, per slot: the export
     * is the raw detail, so the 'last value per date' treatment belongs to
     * /summary and nowhere else.
     *
     * NOT a JSON response. 422 EXPORT_FAILED above 50.000 rows — counted over
     * SLOT ROWS, not records, because one daily record carries up to 24 of
     * them. 422 VALIDATION_ERROR for a format outside csv|excel.
     *
     * The permission guard, the mill resolution, the line resolution, the
     * format check and the row-limit check all run EAGERLY inside the
     * service, before the response starts streaming — otherwise a 403/422
     * would reach the client as a successful but empty download.
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
     * The line is filtered on `engine_room_records.production_line_id` — the
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
