<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PeriodClosureService;
use App\Services\PeriodService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PeriodController — screen-128--kelola-periode-pelaporan's API surface:
 * index() / businessUnitOptions() / store() / update() / destroy()
 * (usecase-128) plus unverifiedCount() / close() / reopen()
 * (usecase-140).
 *
 * All validation and business logic is delegated to PeriodService and
 * PeriodClosureService — the exact same services the Livewire component
 * (App\Livewire\MasterData\KelolaPeriodePelaporan) calls, so web and API
 * enforce one rule set. Mirrors ProductionLineController's shape exactly.
 *
 * Every action is uniformly admin-gated at the route layer (routes/api.php,
 * 'auth:web' + 'role:admin') per screen_tech_spec.actor_permissions —
 * supervisor / mill_management / operator all have can_access=false,
 * including for the closure actions, which Mill Management explicitly may
 * not perform even though the reports they drive are Mill Management's.
 *
 * FIELDS — every Period field this screen's create/update forms accept
 * from the request. `status`, `closed_by`, `closed_at` and `created_by`
 * are never taken from the request: status starts at 'draft' and only ever
 * changes through close()/reopen(), and the actor is always resolved from
 * the session.
 */
class PeriodController extends Controller
{
    protected const FIELDS = [
        'business_unit_id',
        'station_type',
        'name',
        'start_date',
        'end_date',
    ];

    public function __construct(
        protected PeriodService $service,
        protected PeriodClosureService $closureService,
    ) {}

    /**
     * index() — GET /api/periods. business_logic step "list": paginate,
     * optional business_unit_id and status filters, eager-load
     * businessUnit + closedBy, per_page capped at 100.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', Pagination::DEFAULT_PAGE), 1);
        $perPage = Pagination::resolvePerPage($request);

        $businessUnitId = $request->query('business_unit_id');
        $status = $request->query('status');

        $result = $this->service->listPeriods(
            $page,
            $perPage,
            $businessUnitId !== null ? (string) $businessUnitId : null,
            $status !== null ? (string) $status : null
        );

        return response()->json($result);
    }

    /**
     * businessUnitOptions() — GET /api/periods/business-units/options.
     * Unpaginated { id, name } dropdown feed for this screen's form,
     * mirroring ProductionLineController::businessUnitOptions().
     *
     * ROUTE ORDER MATTERS: this must be registered BEFORE
     * /api/periods/{id}, or Laravel matches the literal "business-units"
     * segment against {id} — see routes/api.php's comment.
     */
    public function businessUnitOptions(): JsonResponse
    {
        return response()->json(['data' => $this->service->businessUnitOptions()]);
    }

    /**
     * store() — POST /api/periods. Validation → PERIOD_OVERLAP check →
     * INSERT with status='draft'. 201 Created, mirroring
     * ProductionLineController::store().
     */
    public function store(Request $request): JsonResponse
    {
        $period = $this->service->create($request->only(self::FIELDS));

        return response()->json($period, 201);
    }

    /**
     * update() — PATCH /api/periods/{id}. 404 if unknown, 409
     * PERIOD_CLOSED_IMMUTABLE if already closed, then the same validation
     * and overlap check as store() with this row excluded.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $period = $this->service->update($id, $request->only(self::FIELDS));

        return response()->json($period);
    }

    /**
     * destroy() — DELETE /api/periods/{id}. 404 if unknown, 409
     * PERIOD_CLOSED_IMMUTABLE if closed, else delete.
     */
    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['deleted' => true]);
    }

    /**
     * unverifiedCount() — GET /api/periods/{id}/unverified-count. Feeds
     * the close-confirmation dialog's warning figure; never blocks the
     * closure itself.
     */
    public function unverifiedCount(string $id): JsonResponse
    {
        return response()->json($this->closureService->unverifiedCount($id));
    }

    /**
     * close() — POST /api/periods/{id}/close. Conditional UPDATE; 409
     * PERIOD_ALREADY_CLOSED when another Admin got there first.
     */
    public function close(string $id): JsonResponse
    {
        return response()->json($this->closureService->close($id));
    }

    /**
     * reopen() — POST /api/periods/{id}/reopen. 409 PERIOD_NOT_CLOSED
     * unless the period is actually closed.
     */
    public function reopen(string $id): JsonResponse
    {
        return response()->json($this->closureService->reopen($id));
    }
}
