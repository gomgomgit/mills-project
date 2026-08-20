<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WeighbridgeRecordService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * WeighbridgeRecordController — screen-016--data-browser-weighbridge-web
 * (Data Browser Weighbridge, web). auth_requirement: authenticated. Actors:
 * actor-supervisor, actor-mill-management, actor-admin (see routes/api.php's
 * 'auth:web' + 'role:supervisor,mill_management,admin' middleware chain,
 * mirroring screen-003's registration pattern).
 *
 * Delegates all filtering/pagination/export logic to
 * WeighbridgeRecordService, shared with the Livewire component
 * (App\Livewire\Data\DataBrowserWeighbridge) so both entry points apply the
 * exact same rules.
 */
class WeighbridgeRecordController extends Controller
{
    public function __construct(protected WeighbridgeRecordService $service) {}

    /**
     * index() — GET /api/weighbridge-records. business_logic steps 1-4:
     * validate date range, filter, paginate via the shared Pagination
     * helper, return {data, meta}.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'weighbridge_type', 'business_unit_id']);

        $page = max((int) $request->query('page', Pagination::DEFAULT_PAGE), 1);
        $perPage = Pagination::resolvePerPage($request);

        $result = $this->service->listRecords($filters, $page, $perPage);

        return response()->json($result);
    }

    /**
     * show() — GET /api/weighbridge-records/{id}. screen-019--detail-weighbridge-web
     * business_logic steps 1-3: findOrFail (404 auto-handled by
     * ApiExceptionHandler when not found) → resolve display names via
     * WeighbridgeRecordService::getDetail().
     */
    public function show(string $id): JsonResponse
    {
        return response()->json($this->service->getDetail($id));
    }

    /**
     * store() — POST /api/weighbridge-records. screen-022--form-weighbridge-web
     * business_logic steps 1-4: create a new record, resolving station_id
     * from the given production_line_id (2026-08-20: was business_unit_id).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->only([
            'production_line_id', 'wb_card_number', 'weighbridge_type', 'record_datetime',
            'vehicle_number', 'driver_name', 'estate_supplier', 'destination',
            'division', 'block', 'gross_weight', 'tare_weight', 'quantity',
            'checked', 'acknowledged',
        ]);

        $record = $this->service->create($data, $request->user());

        return response()->json($record, 201);
    }

    /**
     * update() — PATCH /api/weighbridge-records/{id}. screen-022--form-weighbridge-web
     * business_logic steps 5-7: update an existing record; production_line_id/
     * station_id are never accepted here.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->only([
            'wb_card_number', 'weighbridge_type', 'record_datetime',
            'vehicle_number', 'driver_name', 'estate_supplier', 'destination',
            'division', 'block', 'gross_weight', 'tare_weight', 'quantity',
            'checked', 'acknowledged',
        ]);

        $record = $this->service->update($id, $data, $request->user());

        return response()->json($record);
    }

    /**
     * export() — GET /api/weighbridge-records/export. business_logic
     * steps 1-2 (same filters, no pagination) + 5-6: generate a file
     * stream (CSV, or CSV-as-xlsx fallback for format=excel — see
     * WeighbridgeRecordService::fileMetaFor()) and return it for download.
     * NOT a JSON response.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'weighbridge_type', 'business_unit_id']);
        $format = (string) $request->query('format', 'csv');

        return $this->service->export($filters, $format);
    }
}
