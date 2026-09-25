<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SterilizerReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SterilizerReportController — screen-129--laporan-sterilizer-web
 * (Laporan Periode Sterilizer). auth_requirement: authenticated,
 * Supervisor / Mill Management / Admin (see routes/api.php's
 * 'role:supervisor,mill_management,admin' middleware).
 *
 * GET-ONLY BY DESIGN. There is no POST/PUT/PATCH/DELETE action here and
 * none on the /api/sterilizer-reports prefix — this screen is a read-only
 * report and must not offer any path that could alter Sterilizer data.
 *
 * All resolution/aggregation/export logic lives in
 * SterilizerReportService, shared with the Livewire component
 * (App\Livewire\Dashboard\LaporanSterilizer), so the web page and the API
 * can never disagree on a figure — same split as ManagementReport-
 * Controller / ManagementReport.
 *
 * Note which guard closes which hole: business_unit_id from the client is
 * IGNORED for Supervisor / Mill Management (200 with their own mill's
 * data, deliberately not 403 — a 403 would confirm the other mill exists),
 * while a period_id belonging to another mill IS refused with 403 in
 * SterilizerReportService::authorizePeriod().
 */
class SterilizerReportController extends Controller
{
    public function __construct(protected SterilizerReportService $service) {}

    /**
     * businessUnitOptions() — GET /api/sterilizer-reports/business-units/options.
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
     * periods() — GET /api/sterilizer-reports/periods?business_unit_id=...
     * business_logic steps 1-2. Returns { data: [] } with HTTP 200 when the
     * mill has no period covering Sterilizer yet — an empty picker plus a
     * UI hint, never a 404. An Admin who did not pick a mill gets 422
     * VALIDATION_ERROR from resolveBusinessUnit().
     */
    public function periods(Request $request): JsonResponse
    {
        $businessUnitId = $this->service->resolveBusinessUnit(
            $this->queryString($request, 'business_unit_id'),
        );

        return response()->json([
            'data' => $this->service->listPeriods($businessUnitId),
        ]);
    }

    /**
     * summary() — GET /api/sterilizer-reports/summary?period_id=...
     * business_logic steps 3-10: authorise the period (404 / 403), then
     * every figure on the screen for that period.
     */
    public function summary(Request $request): JsonResponse
    {
        $periodId = $this->requirePeriodId($request);

        $period = $this->service->authorizePeriod($periodId);

        return response()->json($this->service->summary($period));
    }

    /**
     * export() — GET /api/sterilizer-reports/export?period_id=...&format=csv
     * business_logic step 11: one line per CYCLE with the record's context
     * columns repeated. NOT a JSON response. 422 EXPORT_FAILED above
     * 50.000 cycle lines.
     */
    public function export(Request $request): StreamedResponse
    {
        $businessUnitId = $this->queryString($request, 'business_unit_id');

        $this->service->resolveBusinessUnit($businessUnitId);

        $periodId = $this->requirePeriodId($request);

        $period = $this->service->authorizePeriod($periodId);

        $format = (string) ($this->queryString($request, 'format') ?? 'csv');

        return $this->service->export($period, $format, $businessUnitId);
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
