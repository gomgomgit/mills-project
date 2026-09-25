<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StorageTankReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * StorageTankReportController — screen-133--laporan-storage-tank-web
 * (Laporan Periode Storage Tank). auth_requirement: authenticated,
 * Supervisor / Mill Management / Admin (see routes/api.php's
 * 'role:supervisor,mill_management,admin' middleware).
 *
 * GET-ONLY BY DESIGN. There is no POST/PUT/PATCH/DELETE action here and
 * none on the /api/storage-tank-reports prefix — this screen is a read-only
 * report and must not offer any path that could alter Storage Tank data.
 *
 * All resolution/aggregation/export logic lives in
 * StorageTankReportService, shared with the Livewire component
 * (App\Livewire\Dashboard\LaporanStorageTank), so the web page and the API
 * can never disagree on a figure — the same split as
 * ClarificationReportController / LaporanClarification.
 *
 * STOCK IS A COMPARISON OF TWO READINGS, NOT AN AGGREGATE: `summary`
 * returns, per tank, the FIRST and the LAST reading whose
 * calculated_weight_mt is non-null — ordered by (record date, detail time
 * slot) — together with the TIMESTAMP of each, and the net movement is the
 * SUM OF THE PER-TANK MOVEMENTS rather than the difference of the combined
 * stocks. A tank with only one stock reading reports movement_mt null and
 * movement_computable false, never 0. The full reasoning is in the
 * StorageTankReportService class docblock.
 *
 * Note which guard closes which hole: business_unit_id from the client is
 * IGNORED for the mill-bound roles — Supervisor / Mill Management (200 with
 * their own mill's data, deliberately NOT 403 — a 403 would confirm the
 * other mill exists), while a period_id belonging to another mill IS
 * refused with 403 in StorageTankReportService::authorizePeriod().
 *
 * OPERATOR IS REFUSED ON ALL FOUR ROUTES. This is the WEB report and there
 * is NO Operator widening here: the mobile Storage Tank report is
 * screen-139 and is implemented separately with its own endpoints. The role
 * list here carries no `operator`, and
 * StorageTankReportService::guardAccess() refuses it two layers deeper as
 * well. Widening must always be both, and must always also add Operator to
 * the MILL-BOUND branch of resolveBusinessUnit() — otherwise it falls into
 * the unbound Admin branch where the client's business_unit_id IS honoured.
 */
class StorageTankReportController extends Controller
{
    public function __construct(protected StorageTankReportService $service) {}

    /**
     * businessUnitOptions() — GET /api/storage-tank-reports/business-units/options.
     * Admin-only mill picker; the service throws 403 FORBIDDEN for anyone
     * else, since Supervisor and Mill Management are already bound to one
     * mill and have no picker at all.
     */
    public function businessUnitOptions(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->businessUnitOptions(),
        ]);
    }

    /**
     * periods() — GET /api/storage-tank-reports/periods?business_unit_id=...
     * business_logic steps 1-2. Returns { data: [] } with HTTP 200 when the
     * mill has no period covering Storage Tank yet — an empty picker plus a
     * UI hint, never a 404. An Admin who did not pick a mill gets 422
     * VALIDATION_ERROR; so does a bound account whose users.business_unit_id
     * is NULL, and in that case the all-mills list is never read at all.
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
     * summary() — GET /api/storage-tank-reports/summary?period_id=...
     * business_logic steps 3-19: resolve the mill (422), authorise the
     * period (404 / 403), then every figure on the screen for that period —
     * recording coverage, the opening/closing/movement stock block with the
     * timestamps of the two readings it came from, the ten metrics each with
     * its own denominator (average temperature read from the Operator's own
     * column, never recomputed from the three positional temperatures), the
     * per-tank recap, and the daily recap carrying FFA, moisture and DOBI on
     * one row.
     */
    public function summary(Request $request): JsonResponse
    {
        $businessUnitId = $this->queryString($request, 'business_unit_id');

        // Ordered on purpose: an Admin with no mill is 422 BEFORE the
        // period is looked up, so an incomplete request never turns into a
        // 404/403 about a period it was never entitled to ask about.
        $this->service->resolveBusinessUnit($businessUnitId);

        $periodId = $this->requirePeriodId($request);

        return response()->json(
            $this->service->buildSummary($periodId, $businessUnitId),
        );
    }

    /**
     * export() — GET /api/storage-tank-reports/export?period_id=...&format=csv
     * business_logic step 20: one line per TIME SLOT with the record's
     * context columns repeated, followed by all seventeen non-time_slot
     * columns — including the steam-valve enum and the three text columns —
     * verbatim. That is the ONLY place those four appear; they are never
     * aggregated in `summary`. NOT a JSON response. 422 EXPORT_FAILED above
     * 50.000 detail lines, 422 VALIDATION_ERROR for a format outside
     * csv|excel.
     *
     * The permission guard, the mill resolution and the row-limit check run
     * EAGERLY inside the service, before the response starts streaming —
     * otherwise a 403/422 would reach the client as a successful but empty
     * download.
     *
     * A CLOSED period exports exactly like an open one: the period lock
     * governs writing data, not reading a report.
     */
    public function export(Request $request): StreamedResponse
    {
        $businessUnitId = $this->queryString($request, 'business_unit_id');

        $this->service->resolveBusinessUnit($businessUnitId);

        $periodId = $this->requirePeriodId($request);

        $format = (string) ($this->queryString($request, 'format') ?? 'csv');

        return $this->service->export($periodId, $format, $businessUnitId);
    }

    /**
     * period_id is required on both summary and export — a missing one is
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
