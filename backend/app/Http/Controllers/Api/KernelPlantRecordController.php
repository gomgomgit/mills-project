<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KernelPlantRecordService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * KernelPlantRecordController — screen-052--data-browser-kernel-plant-web
 * / screen-056--detail-kernel-plant-web / screen-060--form-kernel-plant-web.
 * auth_requirement: authenticated. Actors: actor-supervisor,
 * actor-mill-management, actor-admin (see routes/api.php's 'auth:web' +
 * 'role:supervisor,mill_management,admin' middleware chain, mirroring
 * screen-051/055/059's registration pattern).
 *
 * Delegates all logic to KernelPlantRecordService, shared with the
 * Livewire components so both entry points apply the exact same rules.
 */
class KernelPlantRecordController extends Controller
{
    public function __construct(protected KernelPlantRecordService $service) {}

    /**
     * index() — GET /api/kernel-plant-records.
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
     * show() — GET /api/kernel-plant-records/{id}.
     */
    public function show(string $id): JsonResponse
    {
        return response()->json($this->service->getDetail($id));
    }

    /**
     * store() — POST /api/kernel-plant-records.
     * screen-060--form-kernel-plant-web business_logic steps 1-3: create a
     * new record + its kernel_plant_detail rows (however many were given),
     * resolving station_id from the given production_line_id.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->only([
            'production_line_id', 'kernel_plant_id', 'date', 'note',
            'checked', 'acknowledged', 'details',
        ]);

        $record = $this->service->create($data, $request->user());

        return response()->json($record, 201);
    }

    /**
     * update() — PATCH /api/kernel-plant-records/{id}.
     * screen-060--form-kernel-plant-web business_logic steps 5-7: update an
     * existing record and upsert its kernel_plant_detail rows (insert/
     * update/delete as needed); production_line_id/station_id are never
     * accepted here.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->only([
            'kernel_plant_id', 'date', 'note', 'checked', 'acknowledged', 'details',
        ]);

        $record = $this->service->update($id, $data, $request->user());

        return response()->json($record);
    }

    /**
     * export() — GET /api/kernel-plant-records/export.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'business_unit_id']);
        $format = (string) $request->query('format', 'csv');

        return $this->service->export($filters, $format);
    }
}
