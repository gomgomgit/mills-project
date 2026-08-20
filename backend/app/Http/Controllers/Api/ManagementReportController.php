<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ManagementReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ManagementReportController — screen-026--laporan-manajemen (Laporan
 * Manajemen). auth_requirement: authenticated, Mill Management only (see
 * routes/api.php's 'role:mill_management' middleware).
 *
 * Delegates all aggregation/export logic to ManagementReportService,
 * shared with the Livewire component (App\Livewire\Dashboard\
 * ManagementReport) so both entry points return identical figures.
 * Business unit is always resolved from the acting user
 * ($request->user()->business_unit_id) — never accepted as a request
 * param, since Mill Management is scoped to their own mill only.
 */
class ManagementReportController extends Controller
{
    public function __construct(protected ManagementReportService $service) {}

    /**
     * summary() — GET /api/reports/management-summary. business_logic
     * steps 1-6: validate/default date range → per-day aggregate for the
     * acting user's own business unit → return {rows, total}.
     */
    public function summary(Request $request): JsonResponse
    {
        $businessUnitId = (string) $request->user()->business_unit_id;

        $breakdown = $this->service->getBreakdown(
            $businessUnitId,
            $request->query('date_from'),
            $request->query('date_to'),
        );

        return response()->json($breakdown);
    }

    /**
     * export() — GET /api/reports/management-summary/export. Same filter
     * as summary(), generates a CSV/xlsx-fallback file stream with one
     * row per date plus a Total row. NOT a JSON response.
     */
    public function export(Request $request): StreamedResponse
    {
        $businessUnitId = (string) $request->user()->business_unit_id;
        $format = (string) $request->query('format', 'csv');

        return $this->service->export(
            $businessUnitId,
            $request->query('date_from'),
            $request->query('date_to'),
            $format,
        );
    }
}
