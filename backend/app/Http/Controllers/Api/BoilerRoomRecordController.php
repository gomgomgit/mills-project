<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BoilerRoomRecordService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BoilerRoomRecordController — screen-098--data-browser-boiler-room-web /
 * screen-108--detail-boiler-room-web / screen-118--form-boiler-room-web.
 * auth_requirement: authenticated. Actors: actor-supervisor,
 * actor-mill-management, actor-admin (see routes/api.php's 'auth:web' +
 * 'role:supervisor,mill_management,admin' middleware chain, mirroring
 * EngineRoomRecordController's registration pattern).
 *
 * Delegates all logic to BoilerRoomRecordService, shared with the Livewire
 * components so both entry points apply the exact same rules.
 */
class BoilerRoomRecordController extends Controller
{
    public function __construct(protected BoilerRoomRecordService $service) {}

    /**
     * index() — GET /api/boiler-room-records.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'business_unit_id']);

        $page = max((int) $request->query('page', Pagination::DEFAULT_PAGE), 1);
        $perPage = Pagination::resolvePerPage($request);

        $result = $this->service->listRecords($filters, $page, $perPage);

        return response()->json($result);
    }

    /**
     * show() — GET /api/boiler-room-records/{id}.
     */
    public function show(string $id): JsonResponse
    {
        return response()->json($this->service->getDetail($id));
    }

    /**
     * store() — POST /api/boiler-room-records. Creates a new record + its
     * boiler_room_detail rows (however many were added), resolving
     * station_id from the given production_line_id.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->only([
            'production_line_id', 'boiler_room_id', 'date', 'note',
            'checked', 'acknowledged', 'details',
        ]);

        $record = $this->service->create($data, $request->user());

        return response()->json($record, 201);
    }

    /**
     * update() — PATCH /api/boiler-room-records/{id}. Updates an existing
     * record and upserts its boiler_room_detail rows (insert/update/delete,
     * mirrors Engine Room); production_line_id/station_id are never
     * accepted here.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->only([
            'boiler_room_id', 'date', 'note', 'checked', 'acknowledged', 'details',
        ]);

        $record = $this->service->update($id, $data, $request->user());

        return response()->json($record);
    }

    /**
     * export() — GET /api/boiler-room-records/export.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'business_unit_id']);
        $format = (string) $request->query('format', 'csv');

        return $this->service->export($filters, $format);
    }
}
