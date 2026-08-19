<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StationService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * StationController — screen-030--kelola-station (Kelola Station)'s
 * index()/businessUnitOptions()/store()/update()/destroy() actions.
 *
 * Delegates all validation/business logic to StationService, shared with
 * the Livewire component (App\Livewire\MasterData\KelolaStation) so both
 * entry points enforce the exact same rules — mirrors
 * BusinessUnitController's pattern, MINUS its public/legacy-merge
 * complexity: Station has no pre-existing public endpoint to accommodate
 * (unlike BusinessUnitController::index()'s merge with screen-001/002's
 * login picker — see that class's docblock), every action here is
 * uniformly admin-gated at the route layer (routes/api.php's
 * screen-030--kelola-station block), so index() is a plain paginated
 * list with no legacy-branch decision to make.
 *
 * store()/update() accept plain JSON (no multipart/form-data, no `_method`
 * override concern) — Station has no file-upload field, unlike
 * BusinessUnitController's `logo`.
 *
 * FIELDS — every Station field this screen's create/update forms accept
 * from the request.
 */
class StationController extends Controller
{
    protected const FIELDS = [
        'business_unit_id',
        'name',
        'type',
        'is_active',
        'code',
        'description',
    ];

    public function __construct(protected StationService $service) {}

    /**
     * index() — GET /api/stations. business_logic step "list": paginate,
     * optional business_unit_id filter, eager-load businessUnit +
     * withCount('machineryGroups').
     */
    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', Pagination::DEFAULT_PAGE), 1);
        $perPage = Pagination::resolvePerPage($request);
        $businessUnitId = $request->query('business_unit_id');

        $result = $this->service->listStations(
            $page,
            $perPage,
            $businessUnitId !== null ? (string) $businessUnitId : null
        );

        return response()->json($result);
    }

    /**
     * businessUnitOptions() — GET /api/business-units/options.
     * business_logic step "businessUnitOptions": unpaginated { id, name }
     * list, ordered by name — feeds the Business Unit-select dropdown on
     * the Station create/edit form. This is a NEW endpoint — confirmed
     * via `grep -rn "business-units/options" routes/ app/` that no such
     * route existed anywhere in this codebase before this screen; it is
     * distinct from the pre-existing public `GET /api/business-units`
     * (no `/options` suffix, different shape/purpose — see
     * BusinessUnitController::index()'s docblock) and mirrors the
     * `GET /api/companies/options` / `GET /api/corporates/options`
     * precedent set by screen-029/screen-028: declared on the CHILD
     * entity's controller (here, Station, one level below Business Unit)
     * rather than on BusinessUnitController itself, since this is a
     * screen-030-specific dropdown-population endpoint, not a general
     * Business Unit CRUD endpoint.
     */
    public function businessUnitOptions(): JsonResponse
    {
        return response()->json(['data' => $this->service->businessUnitOptions()]);
    }

    /**
     * store() — POST /api/stations. business_logic step "create":
     * validate business_unit_id exists → validate name required →
     * validate type required+in-enum → validate is_active required
     * boolean (+ cross-field "not true when type=other") → validate code
     * nullable+unique globally → validate description nullable → 422 if
     * any invalid → insert.
     *
     * Response status: the tech-spec's success_schema does not specify a
     * status code for this endpoint — 201 Created is used as the
     * conventional REST default for a resource-creating POST, mirroring
     * BusinessUnitController::store() / CompanyController::store() /
     * CorporateController::store().
     */
    public function store(Request $request): JsonResponse
    {
        $station = $this->service->create($request->only(self::FIELDS));

        return response()->json($station, 201);
    }

    /**
     * update() — PATCH /api/stations/{id}. business_logic step "update":
     * validate id exists → 404 if not → same field validation as
     * store() (code unique excluding self) → 422 if any invalid →
     * update.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $station = $this->service->update($id, $request->only(self::FIELDS));

        return response()->json($station);
    }

    /**
     * destroy() — DELETE /api/stations/{id}. business_logic step
     * "delete": validate id exists → 404 if not → count related
     * MachineryGroup + Machinery rows → 409 STATION_HAS_MACHINERY if
     * either count is non-zero → else delete.
     */
    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['deleted' => true]);
    }
}
