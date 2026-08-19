<?php

namespace App\Livewire\Data;

use App\Exceptions\InvalidDateRangeException;
use App\Models\BusinessUnit;
use App\Services\WeighbridgeRecordService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DataBrowserWeighbridge — screen-016--data-browser-weighbridge-web /
 * "Data Browser Weighbridge" (Livewire web page, route name
 * `data.weighbridge`, /data/weighbridge).
 *
 * Reuses WeighbridgeRecordService — the exact same service the API
 * controller (App\Http\Controllers\Api\WeighbridgeRecordController) uses —
 * so filtering/pagination rules stay identical between the web and API
 * entry points (mirrors the App\Livewire\Settings\ChangePasswordForm /
 * AuthService pattern).
 *
 * Filter state mirrors the tech-spec exactly: date_from, date_to,
 * business_unit_id (a `station` filter is mentioned in the FE description's
 * prose but is NOT part of either endpoint's documented query params —
 * omitted here; see implementation_notes).
 *
 * Pagination: rather than Livewire's WithPagination trait (which pagination
 * a raw Eloquent builder), this component calls
 * WeighbridgeRecordService::listRecords() directly (same call the API
 * controller makes) and keeps its own $page/$perPage state — there is no
 * prior list-screen precedent in this codebase to follow, and this keeps
 * the web and API entry points sharing one code path end-to-end.
 *
 * Export: dispatched as a plain browser-navigable link (GET
 * /api/weighbridge-records/export?...), NOT an AJAX/blob download — the
 * session-authenticated 'auth:web' guard on that route accepts the
 * browser's existing session cookie on a normal same-origin navigation, so
 * a plain <a href> download link (rendered in the Blade view via
 * $exportCsvUrl / $exportExcelUrl below) triggers the browser's native
 * file-download flow with no extra JS needed.
 */
#[Layout('data.weighbridge')]
class DataBrowserWeighbridge extends Component
{
    public string $date_from = '';

    public string $date_to = '';

    public string $business_unit_id = '';

    public int $page = 1;

    public int $perPage = 20;

    public ?string $errorMessage = null;

    public function updatedDateFrom(): void
    {
        $this->resetToFirstPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetToFirstPage();
    }

    public function updatedBusinessUnitId(): void
    {
        $this->resetToFirstPage();
    }

    protected function resetToFirstPage(): void
    {
        $this->page = 1;
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    public function goToPage(int $page): void
    {
        $this->page = max($page, 1);
    }

    /**
     * @return array{date_from: ?string, date_to: ?string, business_unit_id: ?string}
     */
    protected function activeFilters(): array
    {
        return [
            'date_from' => $this->date_from !== '' ? $this->date_from : null,
            'date_to' => $this->date_to !== '' ? $this->date_to : null,
            'business_unit_id' => $this->business_unit_id !== '' ? $this->business_unit_id : null,
        ];
    }

    /**
     * Builds the download URL for the export button (business_logic
     * steps 5-6) — a plain query string against the API export route, so
     * the Blade view can render it as a normal <a href> link.
     */
    protected function exportUrl(string $format): string
    {
        $query = array_filter($this->activeFilters(), fn ($value) => $value !== null);
        $query['format'] = $format;

        return url('/api/weighbridge-records/export').'?'.http_build_query($query);
    }

    public function render()
    {
        $service = app(WeighbridgeRecordService::class);

        try {
            $result = $service->listRecords($this->activeFilters(), $this->page, $this->perPage);
            $this->errorMessage = null;
        } catch (InvalidDateRangeException $e) {
            // Step 1: date_from > date_to → filter not applied, surface the
            // error inline and show an empty result set (mirrors the API
            // contract's 422 INVALID_DATE_RANGE, adapted to Livewire's
            // request/response cycle where a thrown exception would
            // otherwise produce a full-page error).
            $this->errorMessage = $e->getMessage();
            $result = [
                'data' => [],
                'meta' => ['page' => 1, 'per_page' => $this->perPage, 'total' => 0, 'total_pages' => 1],
            ];
        }

        return view('livewire.data.data-browser-weighbridge', [
            'records' => $result['data'],
            'meta' => $result['meta'],
            'businessUnits' => BusinessUnit::orderBy('name')->get(['id', 'name']),
            'exportCsvUrl' => $this->exportUrl('csv'),
            'exportExcelUrl' => $this->exportUrl('excel'),
        ]);
    }
}
