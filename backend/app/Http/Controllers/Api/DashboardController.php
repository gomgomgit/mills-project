<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DashboardController — screen-025--dashboard-web (Dashboard Web).
 *
 * Delegates all aggregation logic to DashboardService, shared with the
 * Livewire component (App\Livewire\Dashboard\DashboardHome) so both entry
 * points return identical KPI figures.
 */
class DashboardController extends Controller
{
    public function __construct(protected DashboardService $service) {}

    /**
     * summary() — GET /api/dashboard/summary. business_logic steps 1-6:
     * validate/default date range → aggregate Weighbridge/Grading/Cages
     * Track for the given filter → return the combined KPI object.
     */
    public function summary(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'business_unit_id']);

        return response()->json($this->service->getSummary($filters));
    }
}
