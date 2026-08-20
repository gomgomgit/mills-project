<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MillSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MillSettingController — screen-034--mills-setting (Mills Setting)'s
 * show()/update()/stations()/setStationIcon() actions.
 *
 * Delegates all validation/ownership-scoping/business logic to
 * MillSettingService, shared with the Livewire component
 * (App\Livewire\Settings\MillsSetting) so both entry points enforce the
 * exact same rules. Every action requires the acting user
 * ($request->user()) — MillSettingService::checkAccess() rejects Mill
 * Management users acting on a business_unit_id other than their own;
 * Admin is never restricted.
 *
 * Also hosts current()/currentStations() — GET /api/mill-settings/current
 * and /api/mill-settings/current/stations (screen-005--home,
 * screen-012--form-cages-track, screen-006--station-list's mobile
 * consumers) — self-scoped read-only endpoints with no :business_unit_id
 * param, open to any authenticated mobile role (operator/supervisor
 * included, not just Admin/Mill Management). Kept in this same
 * controller/service rather than a separate class since both entry
 * points share MillSettingService::findOrCreateRow()/toRow().
 */
class MillSettingController extends Controller
{
    public function __construct(protected MillSettingService $service) {}

    /**
     * current() — GET /api/mill-settings/current. Self-scoped to the
     * acting user's own business unit; any authenticated mobile role.
     */
    public function current(Request $request): JsonResponse
    {
        return response()->json($this->service->getCurrent($request->user()));
    }

    /**
     * currentStations() — GET /api/mill-settings/current/stations.
     * Self-scoped counterpart to stations() below.
     */
    public function currentStations(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->listCurrentStations($request->user())]);
    }

    /**
     * show() — GET /api/mill-settings/{businessUnitId}. business_logic
     * step 3: get-or-create the default row for this business unit.
     */
    public function show(Request $request, string $businessUnitId): JsonResponse
    {
        $millSetting = $this->service->getOrCreate($request->user(), $businessUnitId);

        return response()->json($millSetting);
    }

    /**
     * update() — PATCH /api/mill-settings/{businessUnitId}. business_logic
     * step 4: validate → get-or-create → store uploaded files → update.
     * Multipart request (app_name as a plain field, logo/home_page_image
     * as files).
     */
    public function update(Request $request, string $businessUnitId): JsonResponse
    {
        $millSetting = $this->service->update(
            $request->user(),
            $businessUnitId,
            $request->only(['app_name']),
            $request->file('logo'),
            $request->file('home_page_image'),
        );

        return response()->json($millSetting);
    }

    /**
     * stations() — GET /api/mill-settings/{businessUnitId}/stations.
     * business_logic step 5: list this business unit's stations with
     * their current icon override.
     */
    public function stations(Request $request, string $businessUnitId): JsonResponse
    {
        return response()->json(['data' => $this->service->listStations($request->user(), $businessUnitId)]);
    }

    /**
     * setStationIcon() — PATCH /api/mill-settings/{businessUnitId}/stations/{stationId}.
     * business_logic step 6: validate ownership + icon allow-list →
     * update station.icon (null resets to the type-default icon).
     */
    public function setStationIcon(Request $request, string $businessUnitId, string $stationId): JsonResponse
    {
        $station = $this->service->setStationIcon(
            $request->user(),
            $businessUnitId,
            $stationId,
            $request->input('icon'),
        );

        return response()->json($station);
    }
}
