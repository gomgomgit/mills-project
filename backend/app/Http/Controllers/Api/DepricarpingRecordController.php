<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DepricarpingRecordService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DepricarpingRecordController — screen-051--data-browser-depricarping-web
 * / screen-055--detail-depricarping-web / screen-059--form-depricarping-web.
 * auth_requirement: authenticated. Actors: actor-supervisor,
 * actor-mill-management, actor-admin (see routes/api.php's 'auth:web' +
 * 'role:supervisor,mill_management,admin' middleware chain, mirroring
 * screen-049/050/057/058's registration pattern).
 *
 * Delegates all logic to DepricarpingRecordService, shared with the
 * Livewire components so both entry points apply the exact same rules.
 */
class DepricarpingRecordController extends Controller
{
    public function __construct(protected DepricarpingRecordService $service) {}

    /**
     * index() — GET /api/depricarping-records.
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
     * show() — GET /api/depricarping-records/{id}.
     */
    public function show(string $id): JsonResponse
    {
        return response()->json($this->service->getDetail($id));
    }

    /**
     * store() — POST /api/depricarping-records.
     * screen-059--form-depricarping-web business_logic steps 1-3: create a
     * new record + 24 depricarping_detail rows, resolving station_id from
     * the given production_line_id.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->only([
            'production_line_id', 'presser_id', 'date', 'note',
            'checked', 'acknowledged', 'details',
        ]);

        $record = $this->service->create($data, $request->user());

        return response()->json($record, 201);
    }

    /**
     * update() — PATCH /api/depricarping-records/{id}.
     * screen-059--form-depricarping-web business_logic steps 5-7: update an
     * existing record and its 24 depricarping_detail rows by id;
     * production_line_id/station_id are never accepted here.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->only([
            'presser_id', 'date', 'note', 'checked', 'acknowledged', 'details',
        ]);

        $record = $this->service->update($id, $data, $request->user());

        return response()->json($record);
    }

    /**
     * export() — GET /api/depricarping-records/export.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'business_unit_id']);
        $format = (string) $request->query('format', 'csv');

        return $this->service->export($filters, $format);
    }
}
