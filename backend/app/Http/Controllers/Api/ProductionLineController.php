<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductionLineService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ProductionLineController — screen-036--kelola-production-line (Kelola
 * Production Line)'s index()/businessUnitOptions()/store()/update()/
 * destroy() actions.
 *
 * Delegates all validation/business logic to ProductionLineService, shared
 * with the Livewire component (App\Livewire\MasterData\
 * KelolaProductionLine) so both entry points enforce the exact same rules
 * — mirrors MachineryGroupController's pattern exactly: every action here
 * is uniformly admin-gated at the route layer (routes/api.php's
 * screen-036--kelola-production-line block).
 *
 * FIELDS — every ProductionLine field this screen's create/update forms
 * accept from the request.
 */
class ProductionLineController extends Controller
{
    protected const FIELDS = [
        'business_unit_id',
        'name',
        'code',
        'description',
    ];

    public function __construct(protected ProductionLineService $service) {}

    /**
     * current() — GET /api/production-lines/current. Self-scoped list of
     * the authenticated user's own business unit's Production Lines —
     * mobile-facing (Station List's new Production Line picker step).
     */
    public function current(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->listCurrentForUser($request->user())]);
    }

    /**
     * currentStations() — GET /api/production-lines/current/stations.
     * Self-scoped station list for one Production Line, feeding the
     * mobile Station List grid after the Production Line picker step.
     * `production_line_id` is a required query param, validated to belong
     * to the authenticated user's own business unit.
     */
    public function currentStations(Request $request): JsonResponse
    {
        $productionLineId = (string) $request->query('production_line_id', '');

        return response()->json(['data' => $this->service->currentStations($request->user(), $productionLineId)]);
    }

    /**
     * index() — GET /api/production-lines. business_logic step "list":
     * paginate, optional business_unit_id filter, eager-load businessUnit +
     * withCount('stations').
     */
    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', Pagination::DEFAULT_PAGE), 1);
        $perPage = Pagination::resolvePerPage($request);
        $businessUnitId = $request->query('business_unit_id');

        $result = $this->service->listProductionLines(
            $page,
            $perPage,
            $businessUnitId !== null ? (string) $businessUnitId : null
        );

        return response()->json($result);
    }

    /**
     * businessUnitOptions() — GET /api/business-units/options-shaped
     * dropdown feed, but declared here as a screen-036-specific action
     * (mirrors MachineryGroupController::stationOptions()'s precedent):
     * unpaginated { id, name } list, ordered by name.
     */
    public function businessUnitOptions(): JsonResponse
    {
        return response()->json(['data' => $this->service->businessUnitOptions()]);
    }

    /**
     * store() — POST /api/production-lines. business_logic step "create":
     * validate business_unit_id exists → validate name required → validate
     * code unique-if-filled → 422 if any invalid → insert, then
     * auto-provision the 15 canonical DEFAULT_STATIONS rows.
     *
     * Response status: 201 Created, mirroring
     * MachineryGroupController::store()/StationController::store().
     */
    public function store(Request $request): JsonResponse
    {
        $productionLine = $this->service->create($request->only(self::FIELDS));

        return response()->json($productionLine, 201);
    }

    /**
     * update() — PATCH /api/production-lines/{id}. business_logic step
     * "update": validate id exists → 404 if not → same field validation as
     * store() (code unique excluding self) → 422 if any invalid → update.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $productionLine = $this->service->update($id, $request->only(self::FIELDS));

        return response()->json($productionLine);
    }

    /**
     * destroy() — DELETE /api/production-lines/{id}. business_logic step
     * "delete": validate id exists → 404 if not → count related Station
     * rows → 409 PRODUCTION_LINE_HAS_STATIONS if non-zero → else delete.
     */
    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['deleted' => true]);
    }
}
