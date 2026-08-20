<?php

namespace App\Livewire\Dashboard;

use App\Exceptions\InvalidDateRangeException;
use App\Services\ManagementReportService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ManagementReport — screen-026--laporan-manajemen ("Laporan Manajemen"),
 * route name `reports.management`, /reports/management.
 *
 * Reuses ManagementReportService — the exact same service the API
 * controller (App\Http\Controllers\Api\ManagementReportController) uses —
 * so filtering/aggregation rules stay identical between the web and API
 * entry points (mirrors DashboardHome/DashboardService).
 *
 * Filter state defaults to start-of-month..today on first mount (business
 * spec rule: "Filter rentang tanggal default: awal bulan berjalan sampai
 * hari ini"). Business unit is always the logged-in user's own — no
 * business-unit picker, per screen_tech_spec (Mill Management scoped to
 * their own mill only).
 */
#[Layout('dashboard.management-report')]
class ManagementReport extends Component
{
    public string $date_from = '';

    public string $date_to = '';

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->date_from = Carbon::today()->startOfMonth()->toDateString();
        $this->date_to = Carbon::today()->toDateString();
    }

    public function exportUrl(string $format): string
    {
        $query = array_filter([
            'date_from' => $this->date_from !== '' ? $this->date_from : null,
            'date_to' => $this->date_to !== '' ? $this->date_to : null,
            'format' => $format,
        ], fn ($value) => $value !== null);

        return url('/api/reports/management-summary/export').'?'.http_build_query($query);
    }

    public function render()
    {
        $service = app(ManagementReportService::class);
        $businessUnitId = (string) auth()->user()->business_unit_id;

        try {
            $breakdown = $service->getBreakdown(
                $businessUnitId,
                $this->date_from !== '' ? $this->date_from : null,
                $this->date_to !== '' ? $this->date_to : null,
            );
            $this->errorMessage = null;
        } catch (InvalidDateRangeException $e) {
            // date_from > date_to → filter not applied, surface the error
            // inline and keep an empty breakdown (mirrors the API
            // contract's 422 INVALID_DATE_RANGE, adapted to Livewire's
            // request/response cycle).
            $this->errorMessage = $e->getMessage();
            $breakdown = [
                'rows' => [],
                'total' => [
                    'weighbridge' => ['count' => 0, 'total_net_weight' => 0.0],
                    'grading' => ['count' => 0, 'total_netto' => 0.0, 'total_quantity' => 0.0],
                    'cages_track' => ['count' => 0, 'total_cages_tipped' => 0],
                ],
            ];
        }

        return view('livewire.dashboard.management-report', [
            'breakdown' => $breakdown,
        ]);
    }
}
