<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KernelDispatchRecordService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * KernelDispatchRecordController — screen-093--data-browser-kernel-dispatch-web,
 * screen-103--detail-kernel-dispatch-web, screen-113--form-kernel-dispatch-web.
 * Mirrors SolidWasteDisposalRecordController's structure exactly, delegating
 * all business logic to KernelDispatchRecordService (shared with the
 * Livewire components) so both web and API entry points apply the same
 * rules.
 */
class KernelDispatchRecordController extends Controller
{
    public function __construct(protected KernelDispatchRecordService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'business_unit_id']);

        $page = max((int) $request->query('page', Pagination::DEFAULT_PAGE), 1);
        $perPage = Pagination::resolvePerPage($request);

        $result = $this->service->listRecords($filters, $page, $perPage);

        return response()->json($result);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->service->getDetail($id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->only([
            'production_line_id', 'kernel_dispatch_id', 'date', 'note',
            'checked', 'acknowledged', 'details',
        ]);

        $record = $this->service->create($data, $request->user());

        return response()->json($record, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->only([
            'kernel_dispatch_id', 'date', 'note', 'checked', 'acknowledged', 'details',
        ]);

        $record = $this->service->update($id, $data, $request->user());

        return response()->json($record);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'business_unit_id']);
        $format = (string) $request->query('format', 'csv');

        return $this->service->export($filters, $format);
    }
}
