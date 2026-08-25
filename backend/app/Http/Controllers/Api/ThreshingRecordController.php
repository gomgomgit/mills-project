<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ThreshingRecordService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ThreshingRecordController — screen-049--data-browser-threshing-web /
 * screen-053--detail-threshing-web / screen-057--form-threshing-web.
 * auth_requirement: authenticated. Actors: actor-supervisor,
 * actor-mill-management, actor-admin (see routes/api.php's 'auth:web' +
 * 'role:supervisor,mill_management,admin' middleware chain, mirroring
 * screen-018/021/024's registration pattern).
 *
 * Delegates all logic to ThreshingRecordService, shared with the Livewire
 * components so both entry points apply the exact same rules.
 */
class ThreshingRecordController extends Controller
{
    public function __construct(protected ThreshingRecordService $service) {}

    /**
     * index() — GET /api/threshing-records.
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
     * show() — GET /api/threshing-records/{id}.
     */
    public function show(string $id): JsonResponse
    {
        return response()->json($this->service->getDetail($id));
    }

    /**
     * store() — POST /api/threshing-records. screen-057--form-threshing-web
     * business_logic steps 1-3: create a new record + its threshing_detail
     * rows (however many were added), resolving station_id from the given
     * production_line_id.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->only([
            'production_line_id', 'thresher_id', 'date', 'note',
            'checked', 'acknowledged', 'details',
        ]);

        $record = $this->service->create($data, $request->user());

        return response()->json($record, 201);
    }

    /**
     * update() — PATCH /api/threshing-records/{id}.
     * screen-057--form-threshing-web business_logic steps 5-7: update an
     * existing record and upsert its threshing_detail rows (insert/update/
     * delete, mirrors Cages Track); production_line_id/station_id are
     * never accepted here.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->only([
            'thresher_id', 'date', 'note', 'checked', 'acknowledged', 'details',
        ]);

        $record = $this->service->update($id, $data, $request->user());

        return response()->json($record);
    }

    /**
     * export() — GET /api/threshing-records/export.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'business_unit_id']);
        $format = (string) $request->query('format', 'csv');

        return $this->service->export($filters, $format);
    }
}
