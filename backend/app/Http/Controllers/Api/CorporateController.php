<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CorporateService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CorporateController — screen-027--kelola-corporate (Kelola Corporate).
 * auth_requirement: role: admin (see routes/api.php's 'auth:web' +
 * 'role:admin' middleware chain, mirroring screen-016/017/018's
 * registration pattern).
 *
 * Delegates all validation/business logic to CorporateService, shared with
 * the Livewire component (App\Livewire\MasterData\KelolaCorporate) so both
 * entry points enforce the exact same rules.
 *
 * store()/update() now accept multipart/form-data (entity-catalog v4
 * rework — the `logo` upload) in addition to plain form fields. Since raw
 * HTTP PATCH requests with a multipart/form-data body are not reliably
 * parsed into $_FILES/$_POST by PHP outside of Laravel's test HTTP client
 * (a long-standing PHP limitation, not Laravel-specific), a real
 * (non-test) multipart update with a new logo file must be sent as POST
 * with a `_method=PATCH` override field for Laravel's method-spoofing to
 * kick in — see implementation_notes.
 *
 * FIELDS — every corporate field this screen accepts from the request,
 * shared between store()/update() (mirrors CorporateService::TEXT_FIELDS
 * minus corporate_code/name is not applicable — same full list, kept in
 * sync manually since Request::only() needs a plain array).
 */
class CorporateController extends Controller
{
    protected const FIELDS = [
        'corporate_code',
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
    ];

    public function __construct(protected CorporateService $service) {}

    /**
     * index() — GET /api/corporates. business_logic step 1: paginate via
     * the shared Pagination helper, company_count per row.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', Pagination::DEFAULT_PAGE), 1);
        $perPage = Pagination::resolvePerPage($request);

        $result = $this->service->listCorporates($page, $perPage);

        return response()->json($result);
    }

    /**
     * store() — POST /api/corporates. business_logic step 2: validate
     * corporate_code/name required+unique (+ logo file type/size) → 422
     * if invalid → insert. `created_by` is always derived server-side
     * from the authenticated admin inside the service — never read from
     * the request here.
     *
     * Response status: the tech-spec's success_schema does not specify a
     * status code for this endpoint — 201 Created is used as the
     * conventional REST default for a resource-creating POST (see
     * implementation_notes).
     */
    public function store(Request $request): JsonResponse
    {
        $corporate = $this->service->create(
            $request->only(self::FIELDS),
            $request->file('logo')
        );

        return response()->json($corporate, 201);
    }

    /**
     * update() — PATCH /api/corporates/{id}. business_logic step 3:
     * validate id exists → 404 if not → validate corporate_code/name
     * required+unique excluding self (+ logo file type/size) → 422 if
     * invalid → update. `updated_by` is always derived server-side from
     * the authenticated admin inside the service.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $corporate = $this->service->update(
            $id,
            $request->only(self::FIELDS),
            $request->file('logo')
        );

        return response()->json($corporate);
    }

    /**
     * destroy() — DELETE /api/corporates/{id}. business_logic step 4:
     * validate id exists → 404 if not → count related Company rows → 409
     * CORPORATE_HAS_COMPANIES if any exist → else delete.
     */
    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['deleted' => true]);
    }
}
