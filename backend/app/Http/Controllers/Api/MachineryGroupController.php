<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MachineryGroupService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MachineryGroupController — screen-033--kelola-machinery-group (Kelola
 * Machinery Group)'s index()/stationOptions()/store()/update()/destroy()
 * actions.
 *
 * Delegates all validation/business logic to MachineryGroupService, shared
 * with the Livewire component (App\Livewire\MasterData\
 * KelolaMachineryGroup) so both entry points enforce the exact same rules
 * — mirrors StationController's pattern exactly: every action here is
 * uniformly admin-gated at the route layer (routes/api.php's
 * screen-033--kelola-machinery-group block), no public/legacy-merge
 * complexity to accommodate.
 *
 * store()/update() accept plain JSON — no file-upload field on this
 * entity.
 *
 * FIELDS — every MachineryGroup field this screen's create/update forms
 * accept from the request. `business_unit_id` is DELIBERATELY absent from
 * this whitelist: even if a client includes it in the request body,
 * $request->only(self::FIELDS) below silently drops it before it ever
 * reaches the service — MachineryGroupService::create()/::update()
 * independently re-derive business_unit_id server-side from the found
 * Station record instead, never trusting client input for this field
 * (the structural hierarchy-consistency guarantee this screen exists to
 * enforce — see App\Models\MachineryGroup's docblock).
 */
class MachineryGroupController extends Controller
{
    protected const FIELDS = [
        'station_id',
        'group_code',
        'description',
        'unit',
        'workshop_factor',
        'cost_per_equipment',
    ];

    public function __construct(protected MachineryGroupService $service) {}

    /**
     * index() — GET /api/machinery-groups. business_logic step "list":
     * paginate, optional station_id filter, eager-load station +
     * businessUnit + withCount('machinery').
     */
    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', Pagination::DEFAULT_PAGE), 1);
        $perPage = Pagination::resolvePerPage($request);
        $stationId = $request->query('station_id');

        $result = $this->service->listMachineryGroups(
            $page,
            $perPage,
            $stationId !== null ? (string) $stationId : null
        );

        return response()->json($result);
    }

    /**
     * stationOptions() — GET /api/stations/options. business_logic step
     * "stationOptions": unpaginated { id, name, business_unit_id } list,
     * ordered by name — feeds the Station-select dropdown on the
     * Machinery Group create/edit form. This is a NEW endpoint — confirmed
     * via grep that no route named `stations/options` existed anywhere in
     * this codebase before this screen; distinct from the pre-existing
     * admin-only `GET /api/stations` (no `/options` suffix, different
     * shape/purpose — see StationController::index()) and mirrors the
     * `GET /api/business-units/options` precedent set by screen-030:
     * declared on the CHILD entity's controller (here, MachineryGroup, one
     * level below Station) rather than on StationController itself, since
     * this is a screen-033-specific dropdown-population endpoint, not a
     * general Station CRUD endpoint. `business_unit_id` is included per
     * row (unlike businessUnitOptions()'s plain id/name shape) so the FE
     * can copy/display it client-side before submit.
     */
    public function stationOptions(): JsonResponse
    {
        return response()->json(['data' => $this->service->stationOptions()]);
    }

    /**
     * store() — POST /api/machinery-groups. business_logic step "create":
     * validate station_id exists → validate group_code required+unique
     * globally → validate description/unit/workshop_factor/
     * cost_per_equipment nullable → 422 if any invalid → copy
     * business_unit_id from the found Station → insert.
     *
     * Response status: 201 Created, mirroring
     * StationController::store()/BusinessUnitController::store().
     */
    public function store(Request $request): JsonResponse
    {
        $machineryGroup = $this->service->create($request->only(self::FIELDS));

        return response()->json($machineryGroup, 201);
    }

    /**
     * update() — PATCH /api/machinery-groups/{id}. business_logic step
     * "update": validate id exists → 404 if not → same field validation as
     * store() (group_code unique excluding self) → 422 if any invalid →
     * re-copy business_unit_id from the (possibly changed) station_id →
     * update.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $machineryGroup = $this->service->update($id, $request->only(self::FIELDS));

        return response()->json($machineryGroup);
    }

    /**
     * destroy() — DELETE /api/machinery-groups/{id}. business_logic step
     * "delete": validate id exists → 404 if not → count related Machinery
     * rows → 409 MACHINERY_GROUP_HAS_MACHINERY if non-zero → else delete.
     */
    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['deleted' => true]);
    }
}
