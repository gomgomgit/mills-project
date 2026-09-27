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
 * (usecase-140) and open() (usecase-144).
 *
 * TWO RESOURCES, TWO URL PREFIXES (2026-09-26). The first five actions are
 * about a PERIOD and live under /api/periods. The last four are about ONE
 * STATION INSIDE a period and live under /api/period-stations/{id}/... — they
 * take a `period_stations` id, the `stations[].id` of
 * PeriodService::toRow(), and they would 404 on a period id. They used to sit
 * under /api/periods/{id}/... , which made the path claim the opposite of what
 * the handler does: every reader had to be told that {id} there was not a
 * period. A URL that has to be explained is a URL that will be misused.
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
 * from the request. `created_by`/`updated_by` are never taken from the
 * request (the actor is always resolved from the session), and
 * `status`/`closed_by`/`closed_at` are not Period fields at all any more —
 * they live on `period_stations`, one set per station type, and only ever
 * change through close()/reopen()/open().
 */
class PeriodController extends Controller
{
    /**
     * `station_type` IS NOT ONE OF THEM ANY MORE (2026-09-25). A period takes
     * no station choice: PeriodService::create() derives its station rows from
     * the mill's own inventory and update() backfills the ones added since. A
     * `station_type` key sent by a stale client is simply not forwarded, so it
     * neither takes effect nor 422s.
     */
    protected const FIELDS = [
        'business_unit_id',
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
     * show() — GET /api/periods/{id} (screen-142--detail-periode-pelaporan /
     * usecase-145). ONE period with its station rows, 404 NOT_FOUND when it
     * is gone.
     *
     * The body is PeriodService::getDetail(), i.e. toRow() verbatim — byte
     * for byte one entry of index()'s `data[]`, not a detail-only shape.
     * The detail screen and the list screen therefore read the same keys
     * (`stations[]`, `station_count`, `closed_station_count`,
     * `is_immutable`, `status_summary`), and a period never has two
     * representations to keep in step. Mirrors
     * StorageTankRecordController::show()'s single-resource pattern.
     *
     * ROUTE ORDER MATTERS here too: /periods/business-units/options must
     * stay registered BEFORE this one — see routes/api.php.
     */
    public function show(string $id): JsonResponse
    {
        return response()->json($this->service->getDetail($id));
    }

    /**
     * store() — POST /api/periods. Validation → PERIOD_OVERLAP check →
     * INSERT the period AND one `period_stations` row per station type active
     * in that mill, all status='draft'. 201 Created, mirroring
     * ProductionLineController::store().
     */
    public function store(Request $request): JsonResponse
    {
        $period = $this->service->create($request->only(self::FIELDS));

        return response()->json($period, 201);
    }

    /**
     * update() — PATCH /api/periods/{id}. 404 if unknown, 409
     * PERIOD_CLOSED_IMMUTABLE if at least one of its stations is closed, then
     * the same validation and overlap check as store() with this row
     * excluded, and finally the station-row backfill (see
     * PeriodService::update()).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $period = $this->service->update($id, $request->only(self::FIELDS));

        return response()->json($period);
    }

    /**
     * destroy() — DELETE /api/periods/{id}. 404 if unknown, 409
     * PERIOD_CLOSED_IMMUTABLE if at least one of its stations is closed, else
     * delete (the station rows go with it — cascadeOnDelete).
     */
    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['deleted' => true]);
    }

    /**
     * unverifiedCount() — GET /api/period-stations/{id}/unverified-count.
     * Feeds the close-confirmation dialog's warning figure for ONE station
     * type; never blocks the closure itself.
     *
     * $periodStationId is a `period_stations` id, not a period id — for all
     * four actions below. The parameter is named after what it holds so the
     * next reader does not have to check the route file to find out.
     */
    public function unverifiedCount(string $periodStationId): JsonResponse
    {
        return response()->json($this->closureService->unverifiedCount($periodStationId));
    }

    /**
     * close() — POST /api/period-stations/{id}/close. Conditional UPDATE on
     * the one station row; 409 PERIOD_ALREADY_CLOSED when another Admin got
     * there first. Every other station of the period is untouched.
     */
    public function close(string $periodStationId): JsonResponse
    {
        return response()->json($this->closureService->close($periodStationId));
    }

    /**
     * reopen() — POST /api/period-stations/{id}/reopen. 409
     * PERIOD_NOT_CLOSED unless THAT station is actually closed.
     */
    public function reopen(string $periodStationId): JsonResponse
    {
        return response()->json($this->closureService->reopen($periodStationId));
    }

    /**
     * open() — POST /api/period-stations/{id}/open (usecase-144). Draft →
     * open for one station via the same conditional-UPDATE contract as
     * close(); 409 PERIOD_NOT_DRAFT when it is already open, already closed,
     * or when another Admin opened it first. No station data is touched.
     *
     * Like close() and reopen(), this contract lists 404 / 409 / 403 only
     * — session handling lives in the middleware layer, not here.
     */
    public function open(string $periodStationId): JsonResponse
    {
        return response()->json($this->closureService->open($periodStationId));
    }
}
