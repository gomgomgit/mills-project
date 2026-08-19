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
