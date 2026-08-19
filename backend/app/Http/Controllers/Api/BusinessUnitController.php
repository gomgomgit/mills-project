<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Services\BusinessUnitService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BusinessUnitController — GET /api/business-units (merged, see index()
 * docblock) + screen-029--kelola-business-unit (Kelola Business Unit)'s
 * companyOptions()/store()/update()/destroy() actions.
 *
 * Delegates all validation/business logic to BusinessUnitService, shared
 * with the Livewire component (App\Livewire\MasterData\
 * KelolaBusinessUnit) so both entry points enforce the exact same rules —
 * mirrors CorporateController/CompanyController's pattern exactly.
 *
 * store()/update() accept multipart/form-data (the `logo` upload),
 * mirroring CorporateController/CompanyController exactly, including
 * their note on real (non-test) multipart PATCH requests needing a
 * `_method=PATCH` override field.
 *
 * FIELDS — every business unit field this screen accepts from the
 * request besides company_id (kept out of FIELDS since it's read via a
 * plain top-level ->only() alongside FIELDS, mirroring
 * CompanyController's corporate_id handling) and logo (handled separately
 * via $request->file()).
 */
class BusinessUnitController extends Controller
{
    protected const FIELDS = [
        'code',
        'name',
        'business_unit_type_code',
        'short_name',
        'leader_name',
        'lawyer_name',
        'address',
        'telephone_no',
        'fax_no',
        'contact_no',
        'extension_no',
        'email',
        'website',
        'map',
        'tax_register_no',
        'insurance_no',
        'epf_employer',
        'socso_employer',
        'labor_union',
    ];

    public function __construct(protected BusinessUnitService $service) {}

    /**
     * index() — GET /api/business-units.
     *
     * MERGE DECISION (deliberate, already decided — not to be
     * second-guessed): this route pre-dates screen-029 and is relied on,
     * byte-for-byte identically, by screen-001/screen-002's pre-login
     * "Business Area" picker (called with NO query params, expects a bare
     * `{ data: [{id, name}, ...] }`). screen-029--kelola-business-unit's
     * tech-spec also wants GET /api/business-units to support a
     * `company_id` filter + `page`/`per_page` pagination + richer rows
     * (`company_name`, `station_count`). Laravel cannot register two
     * routes on the same method+URI, so both behaviours are merged into
     * this ONE action:
     *
     *  - no `page`/`per_page`/`company_id` query param present → legacy
     *    branch, completely unchanged from the pre-screen-029 behaviour.
     *  - any of those params present → this screen's richer, paginated
     *    list branch.
     *
     * This action is kept PUBLIC (no new auth middleware added here) —
     * a deliberate, narrow exception to "role:admin on every endpoint"
     * for this ONE read action only. store()/update()/destroy()/
     * companyOptions() below remain admin-gated via route middleware as
     * normal (see routes/api.php's screen-029 block). Justification:
     *  1. The pre-existing public contract (screen-001/002's login
     *     picker) must not break — it has no session/token to send.
     *  2. The data exposed here (business unit id/name/company_name/
     *     station_count) is master-data labels/counts, not sensitive —
     *     the same non-sensitive list already exposed publicly today,
     *     just richer in shape when pagination params are supplied.
     */
    public function index(Request $request): JsonResponse
    {
        $isListQuery = $request->hasAny(['page', 'per_page', 'company_id']);

        if (! $isListQuery) {
            // Unchanged legacy behaviour — screen-001/002's pre-login
            // "Business Area" picker. Deliberately left untouched.
            $businessUnits = BusinessUnit::query()->orderBy('name')->get(['id', 'name']);

            return response()->json(['data' => $businessUnits]);
        }

        $page = max((int) $request->query('page', Pagination::DEFAULT_PAGE), 1);
        $perPage = Pagination::resolvePerPage($request);
        $companyId = $request->query('company_id');

        $result = $this->service->listBusinessUnits($page, $perPage, $companyId !== null ? (string) $companyId : null);

        return response()->json($result);
    }

    /**
     * companyOptions() — GET /api/companies/options. business_logic step
     * "companyOptions": unpaginated { id, name } list, ordered by name —
     * feeds the Company-select dropdown on the Business Unit create/edit
     * form.
     */
    public function companyOptions(): JsonResponse
    {
        return response()->json(['data' => $this->service->companyOptions()]);
    }

    /**
     * store() — POST /api/business-units. business_logic step "create":
     * validate company_id exists → validate code required+unique
     * globally → validate name required (no uniqueness rule) (+ logo
     * file type/size) → 422 if any invalid → insert. `created_by` is
     * always derived server-side from the authenticated admin inside the
     * service — never read from the request here.
     *
     * Response status: the tech-spec's success_schema does not specify a
     * status code for this endpoint — 201 Created is used as the
     * conventional REST default for a resource-creating POST, mirroring
     * CorporateController::store() / CompanyController::store().
     */
    public function store(Request $request): JsonResponse
    {
        $businessUnit = $this->service->create(
            $request->only(array_merge(['company_id'], self::FIELDS)),
            $request->file('logo')
        );

        return response()->json($businessUnit, 201);
    }

    /**
     * update() — PATCH /api/business-units/{id}. business_logic step
     * "update": validate id exists → 404 if not → validate company_id
     * exists → validate code required+unique globally excluding self →
     * validate name required (no uniqueness rule) (+ logo file type/size)
     * → 422 if any invalid → update. `updated_by` is always derived
     * server-side from the authenticated admin inside the service.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $businessUnit = $this->service->update(
            $id,
            $request->only(array_merge(['company_id'], self::FIELDS)),
            $request->file('logo')
        );

        return response()->json($businessUnit);
    }

    /**
     * destroy() — DELETE /api/business-units/{id}. business_logic step
     * "delete": validate id exists → 404 if not → count related Station
     * rows → 409 BUSINESS_UNIT_HAS_STATIONS if any exist → else delete.
     */
    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['deleted' => true]);
    }
}
