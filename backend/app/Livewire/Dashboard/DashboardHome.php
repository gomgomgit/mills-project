<?php

namespace App\Livewire\Dashboard;

use App\Exceptions\InvalidDateRangeException;
use App\Models\BusinessUnit;
use App\Services\DashboardService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DashboardHome — screen-025--dashboard-web ("Dashboard Web"), route name
 * `dashboard`, /dashboard.
 *
 * Reuses DashboardService — the exact same service the API controller
 * (App\Http\Controllers\Api\DashboardController) uses — so filtering/
 * aggregation rules stay identical between the web and API entry points
 * (mirrors DataBrowserWeighbridge / WeighbridgeRecordService).
 *
 * Replaces the placeholder stub this component used to be (see git history
 * — the stub existed only so the sidebar's "Dashboard" link resolved
 * before this screen's own ASDLC cycle landed).
 *
 * Filter state defaults to today's date on first mount (business spec
 * rule: "Filter tanggal default hari ini saat dashboard pertama dibuka").
 */
#[Layout('dashboard.index')]
class DashboardHome extends Component
{
    public string $date_from = '';

    public string $date_to = '';

    public string $business_unit_id = '';

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $today = Carbon::today()->toDateString();
        $this->date_from = $today;
        $this->date_to = $today;
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
     * Builds the target Data Browser route + query string for a KPI card
     * click, carrying the currently active date range & business unit
     * filter forward (business rule: "Klik card/baris stasiun meneruskan
     * filter tanggal & business unit yang sedang aktif").
     */
    public function dataBrowserUrl(string $routeName): string
    {
        $query = array_filter($this->activeFilters(), fn ($value) => $value !== null);

        return route($routeName).(count($query) > 0 ? '?'.http_build_query($query) : '');
    }

    public function render()
    {
        $service = app(DashboardService::class);

        try {
            $summary = $service->getSummary($this->activeFilters());
            $this->errorMessage = null;
        } catch (InvalidDateRangeException $e) {
            // date_from > date_to → filter not applied, surface the error
            // inline and keep the previous/empty summary (mirrors the API
            // contract's 422 INVALID_DATE_RANGE, adapted to Livewire's
            // request/response cycle).
            $this->errorMessage = $e->getMessage();
            $summary = [
                'weighbridge' => ['count' => 0, 'total_net_weight' => 0.0],
                'grading' => ['count' => 0, 'total_netto' => 0.0, 'total_quantity' => 0.0],
                'cages_track' => ['count' => 0, 'total_cages_tipped' => 0],
            ];
        }

        return view('livewire.dashboard.dashboard-home', [
            'summary' => $summary,
            'businessUnits' => BusinessUnit::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
