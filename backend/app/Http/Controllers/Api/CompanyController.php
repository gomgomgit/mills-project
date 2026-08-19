<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CompanyService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CompanyController — screen-028--kelola-company (Kelola Company).
 * auth_requirement: role: admin (see routes/api.php's 'auth:web' +
 * 'role:admin' middleware chain, mirroring screen-027's registration
 * pattern).
 *
 * Delegates all validation/business logic to CompanyService, shared with
 * the Livewire component (App\Livewire\MasterData\KelolaCompany) so both
 * entry points enforce the exact same rules.
 *
 * store()/update() now accept multipart/form-data (entity-catalog v4
 * rework — the `logo` upload), mirroring CorporateController exactly,
 * including its note on real (non-test) multipart PATCH requests needing
 * a `_method=PATCH` override field.
 *
 * FIELDS — every company field this screen accepts from the request
 * besides corporate_id (kept out of FIELDS since it's read via a plain
 * top-level ->only() alongside FIELDS, mirroring the pre-existing
 * corporate_id handling) and logo (handled separately via
 * $request->file()).
 */
class CompanyController extends Controller
{
    protected const FIELDS = [
        'company_code',
        'name',
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
        'last_update',
    ];

    public function __construct(protected CompanyService $service) {}

    /**
     * index() — GET /api/companies. business_logic step 1: paginate via
     * the shared Pagination helper, optional corporate_id filter,
     * business_unit_count per row.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', Pagination::DEFAULT_PAGE), 1);
        $perPage = Pagination::resolvePerPage($request);
        $corporateId = $request->query('corporate_id');

        $result = $this->service->listCompanies($page, $perPage, $corporateId !== null ? (string) $corporateId : null);

        return response()->json($result);
    }

    /**
     * corporateOptions() — GET /api/corporates/options. business_logic
     * step 2: unpaginated { id, name } list, ordered by name — feeds the
     * Corporate-select dropdown on the Company create/edit form.
     */
    public function corporateOptions(): JsonResponse
    {
        return response()->json(['data' => $this->service->corporateOptions()]);
    }

    /**
     * store() — POST /api/companies. business_logic step 3: validate
     * corporate_id exists → validate company_code required+unique
     * globally → validate name required+unique within that corporate_id
     * (+ logo file type/size) → 422 if any invalid → insert.
     * `created_by` is always derived server-side from the authenticated
     * admin inside the service — never read from the request here.
     *
     * Response status: the tech-spec's success_schema does not specify a
     * status code for this endpoint — 201 Created is used as the
     * conventional REST default for a resource-creating POST, mirroring
     * CorporateController::store().
     */
    public function store(Request $request): JsonResponse
    {
        $company = $this->service->create(
            $request->only(array_merge(['corporate_id'], self::FIELDS)),
            $request->file('logo')
        );

        return response()->json($company, 201);
    }

    /**
     * update() — PATCH /api/companies/{id}. business_logic step 4:
     * validate id exists → 404 if not → validate corporate_id exists →
     * validate company_code required+unique globally excluding self →
     * validate name required+unique within corporate_id excluding self
     * (+ logo file type/size) → 422 if any invalid → update.
     * `updated_by` is always derived server-side from the authenticated
     * admin inside the service.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $company = $this->service->update(
            $id,
            $request->only(array_merge(['corporate_id'], self::FIELDS)),
            $request->file('logo')
        );

        return response()->json($company);
    }

    /**
     * destroy() — DELETE /api/companies/{id}. business_logic step 5:
     * validate id exists → 404 if not → count related BusinessUnit rows →
     * 409 COMPANY_HAS_BUSINESS_UNITS if any exist → else delete.
     */
    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['deleted' => true]);
    }
}
