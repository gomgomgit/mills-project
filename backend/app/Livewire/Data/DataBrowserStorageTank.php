<?php

namespace App\Livewire\Data;

use App\Exceptions\InvalidDateRangeException;
use App\Models\BusinessUnit;
use App\Services\StorageTankRecordService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DataBrowserStorageTank — screen-096--data-browser-storage-tank-web /
 * "Data Browser Storage Tank" (Livewire web page, route name
 * `data.storage-tank`, /data/storage-tank).
 *
 * Reuses StorageTankRecordService — the exact same service the API
 * controller (App\Http\Controllers\Api\StorageTankRecordController) uses —
 * so filtering/pagination rules stay identical between the web and API
 * entry points (mirrors DataBrowserEffluentPlant's pattern).
 *
 * Filter state mirrors the tech-spec exactly: date_from, date_to,
 * business_unit_id.
 *
 * Pagination: calls StorageTankRecordService::listRecords() directly and
 * keeps its own $page/$perPage state, rather than Livewire's
 * WithPagination trait — same approach as DataBrowserEffluentPlant.
 *
 * Export: dispatched as a plain browser-navigable link (GET
 * /api/storage-tank-records/export?...), NOT an AJAX/blob download —
 * session cookie carries auth on a same-origin navigation.
 */
#[Layout('data.storage-tank')]
class DataBrowserStorageTank extends Component
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

    protected function exportUrl(string $format): string
    {
        $query = array_filter($this->activeFilters(), fn ($value) => $value !== null);
        $query['format'] = $format;

        return url('/api/storage-tank-records/export').'?'.http_build_query($query);
    }

    public function render()
    {
        $service = app(StorageTankRecordService::class);

        try {
            $result = $service->listRecords($this->activeFilters(), $this->page, $this->perPage);
            $this->errorMessage = null;
        } catch (InvalidDateRangeException $e) {
            $this->errorMessage = $e->getMessage();
            $result = [
                'data' => [],
                'meta' => ['page' => 1, 'per_page' => $this->perPage, 'total' => 0, 'total_pages' => 1],
            ];
        }

        return view('livewire.data.data-browser-storage-tank', [
            'records' => $result['data'],
            'meta' => $result['meta'],
            'businessUnits' => BusinessUnit::orderBy('name')->get(['id', 'name']),
            'exportCsvUrl' => $this->exportUrl('csv'),
            'exportExcelUrl' => $this->exportUrl('excel'),
        ]);
    }
}
