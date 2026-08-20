<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GradingRecordService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GradingRecordController — screen-017--data-browser-grading-web
 * (Data Browser Grading, web). auth_requirement: authenticated. Actors:
 * actor-supervisor, actor-mill-management, actor-admin (see routes/api.php's
 * 'auth:web' + 'role:supervisor,mill_management,admin' middleware chain,
 * mirroring screen-016's registration pattern).
 *
 * Delegates all filtering/pagination/export logic to
 * GradingRecordService, shared with the Livewire component
 * (App\Livewire\Data\DataBrowserGrading) so both entry points apply the
 * exact same rules.
 */
class GradingRecordController extends Controller
{
    public function __construct(protected GradingRecordService $service) {}

    /**
     * index() — GET /api/grading-records. business_logic steps 1-4:
     * validate date range, filter, paginate via the shared Pagination
     * helper, return {data, meta}.
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
     * show() — GET /api/grading-records/{id}. screen-020--detail-grading-web
     * business_logic steps 1-4: findOrFail (404 auto-handled by
     * ApiExceptionHandler when not found) → resolve display names/grid via
     * GradingRecordService::getDetail().
     */
    public function show(string $id): JsonResponse
    {
        return response()->json($this->service->getDetail($id));
    }

    /**
     * store() — POST /api/grading-records. screen-023--form-grading-web
     * business_logic steps 1-5: create a new record + details, resolving
     * station_id from the given business_unit_id.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->only([
            'business_unit_id', 'grading_number', 'date', 'weighbridge_record_id',
            'license_plate_no', 'vehicle_code', 'estate_supplier', 'division',
            'netto', 'quantity', 'note', 'acknowledged', 'details',
        ]);

        $record = $this->service->create($data, $request->user());

        return response()->json($record, 201);
    }

    /**
     * update() — PATCH /api/grading-records/{id}. screen-023--form-grading-web
     * business_logic steps 6-8: update an existing record and upsert its
     * details; business_unit_id/station_id are never accepted here.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->only([
            'grading_number', 'date', 'weighbridge_record_id',
            'license_plate_no', 'vehicle_code', 'estate_supplier', 'division',
            'netto', 'quantity', 'note', 'acknowledged', 'details',
        ]);

        $record = $this->service->update($id, $data, $request->user());

        return response()->json($record);
    }

    /**
     * export() — GET /api/grading-records/export. business_logic
     * steps 1-2 (same filters, no pagination) + 5-6: generate a file
     * stream (CSV, or CSV-as-xlsx fallback for format=excel — see
     * GradingRecordService::fileMetaFor()) and return it for download.
     * NOT a JSON response.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'business_unit_id']);
        $format = (string) $request->query('format', 'csv');

        return $this->service->export($filters, $format);
    }
}
