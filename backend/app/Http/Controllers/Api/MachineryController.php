<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MachineryService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MachineryController — screen-031--kelola-machinery (Kelola Machinery)'s
 * index()/groupOptions()/show()/store()/update()/destroy() actions.
 *
 * Delegates all validation/business logic to MachineryService, shared
 * with the Livewire component (App\Livewire\MasterData\KelolaMachinery)
 * so both entry points enforce the exact same rules — mirrors
 * MachineryGroupController's pattern exactly: every action here is
 * uniformly admin-gated at the route layer (routes/api.php's
 * screen-031--kelola-machinery block), no public/legacy-merge complexity
 * to accommodate.
 *
 * store()/update() accept multipart/form-data (the `picture` upload) in
 * addition to plain form fields, same reasoning as
 * CorporateController's own docblock re: PATCH + multipart +
 * `_method=PATCH` override for real (non-test) clients.
 *
 * FIELDS — every Machinery field this screen's create/update forms accept
 * from the request, PLUS the two child-row array keys (`insurances`,
 * `tax_purchases`). `station_id`/`business_unit_id` are DELIBERATELY
 * absent from this whitelist: even if a client includes either in the
 * request body, $request->only(self::FIELDS) below silently drops them
 * before they ever reach the service — MachineryService::create()/
 * ::update() independently re-derive both server-side from the found
 * MachineryGroup instead, never trusting client input for them (see
 * App\Services\MachineryService's docblock).
 *
 * `$request->only()` only includes a key when it is actually PRESENT in
 * the request — this is exactly the "present vs absent" distinction
 * MachineryService::update() relies on to decide whether to replace the
 * `insurances`/`tax_purchases` child rows at all (see that method's
 * docblock), so no extra bookkeeping is needed here to preserve that
 * semantic.
 */
class MachineryController extends Controller
{
    protected const FIELDS = [
        'machinery_group_id',
        'equipment_code',
        'name',
        'description',
        'registration_no',
        'make',
        'model',
        'equipment_type',
        'part_no',
        'serial_no',
        'gearbox',
        'motor',
        'mounting',
        'rpm',
        'chain',
        'capacity',
        'brand',
        'year_made',
        'fixed_asset',
        'control_activity',
        'owner_ite',
        'insurances',
        'tax_purchases',
    ];

    public function __construct(protected MachineryService $service) {}

    /**
     * index() — GET /api/machinery. business_logic step "list": paginate,
     * optional machinery_group_id filter, eager-load machineryGroup (for
     * machinery_group_code), NO child arrays.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', Pagination::DEFAULT_PAGE), 1);
        $perPage = Pagination::resolvePerPage($request);
        $machineryGroupId = $request->query('machinery_group_id');

        $result = $this->service->listMachinery(
            $page,
            $perPage,
            $machineryGroupId !== null ? (string) $machineryGroupId : null
        );

        return response()->json($result);
    }

    /**
     * groupOptions() — GET /api/machinery-groups/options. business_logic
     * step "machineryGroupOptions": unpaginated
     * { id, group_code, station_id, business_unit_id } list, ordered by
     * group_code — feeds the Machinery Group-select dropdown on this
     * screen's create/edit form. Declared here (not on
     * MachineryGroupController) even though it queries the MachineryGroup
     * model — it's a screen-031-specific dropdown-population endpoint
     * (feeds THIS screen's form), not a general MachineryGroup CRUD
     * endpoint, mirroring MachineryGroupController::stationOptions()'s own
     * "declared on the CHILD entity's controller" precedent exactly (one
     * level down: Machinery is the child of MachineryGroup here).
     */
    public function groupOptions(): JsonResponse
    {
        return response()->json(['data' => $this->service->machineryGroupOptions()]);
    }

    /**
     * show() — GET /api/machinery/{id}. business_logic step "detail": the
     * only endpoint in this master-data round with a dedicated detail
     * fetch — includes `insurances`/`tax_purchases` arrays, populates the
     * Edit form.
     */
    public function show(string $id): JsonResponse
    {
        return response()->json($this->service->detail($id));
    }

    /**
     * store() — POST /api/machinery. business_logic step "create":
     * validate machinery_group_id exists → validate equipment_code
     * required+unique globally → validate name required, other fields
     * nullable → 422 if any invalid → derive station_id/business_unit_id
     * from the found MachineryGroup → insert Machinery + insurances +
     * tax_purchases in one DB transaction.
     *
     * Response status: 201 Created, mirroring
     * MachineryGroupController::store()/CorporateController::store().
     */
    public function store(Request $request): JsonResponse
    {
        $machinery = $this->service->create(
            $request->only(self::FIELDS),
            $request->file('picture')
        );

        return response()->json($machinery, 201);
    }

    /**
     * update() — PATCH /api/machinery/{id}. business_logic step "update":
     * validate id exists → 404 if not → same field validation as store()
     * (equipment_code unique excluding self) → 422 if any invalid →
     * re-derive station_id/business_unit_id from the (possibly changed)
     * machinery_group_id → update, replacing child rows only when the
     * `insurances`/`tax_purchases` keys are present in the request body.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $machinery = $this->service->update(
            $id,
            $request->only(self::FIELDS),
            $request->file('picture')
        );

        return response()->json($machinery);
    }

    /**
     * destroy() — DELETE /api/machinery/{id}. business_logic step
     * "delete": validate id exists → 404 if not → delete child rows →
     * delete Machinery. NO 409/guard of any kind — this is the one
     * master-data screen in this round with no delete-guard (see
     * App\Services\MachineryService's docblock).
     */
    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['deleted' => true]);
    }
}
