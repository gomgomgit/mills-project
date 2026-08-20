<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * UserController — screen-032--kelola-user-role (Kelola User & Role)'s
 * index()/store()/update()/setStatus() actions.
 *
 * Delegates all validation/business logic to UserService, shared with the
 * Livewire component (App\Livewire\UserManagement\KelolaUserRole) so both
 * entry points enforce the exact same rules — mirrors
 * BusinessUnitController's pattern.
 */
class UserController extends Controller
{
    public function __construct(protected UserService $service) {}

    /**
     * index() — GET /api/users. business_logic step "list": paginate,
     * optional role/business_unit_id filters, eager-load businessUnit.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', Pagination::DEFAULT_PAGE), 1);
        $perPage = Pagination::resolvePerPage($request);

        $result = $this->service->listUsers(
            $page,
            $perPage,
            $request->query('role') !== null ? (string) $request->query('role') : null,
            $request->query('business_unit_id') !== null ? (string) $request->query('business_unit_id') : null,
        );

        return response()->json($result);
    }

    /**
     * store() — POST /api/users. business_logic step "create": validate
     * username/name/role/business_unit_id/password → 422 if any invalid
     * → hash password → insert.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->service->create(
            $request->only(['username', 'name', 'role', 'business_unit_id', 'password'])
        );

        return response()->json($user, 201);
    }

    /**
     * update() — PATCH /api/users/{id}. business_logic step "update":
     * validate id exists → 404 if not → validate name/role/business_unit_id
     * → 422 if invalid → update (password_hash untouched).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $this->service->update(
            $id,
            $request->only(['name', 'role', 'business_unit_id'])
        );

        return response()->json($user);
    }

    /**
     * setStatus() — PATCH /api/users/{id}/status. business_logic step
     * "status": validate id exists → 404 if not → 409
     * CANNOT_DEACTIVATE_SELF if deactivating the acting admin's own
     * account → else update is_active.
     */
    public function setStatus(Request $request, string $id): JsonResponse
    {
        $user = $this->service->setStatus(
            $id,
            (bool) $request->boolean('is_active'),
            (string) $request->user()->id,
        );

        return response()->json($user);
    }
}
