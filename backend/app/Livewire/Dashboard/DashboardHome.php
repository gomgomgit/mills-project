<?php

namespace App\Livewire\Dashboard;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DashboardHome — screen-025--dashboard-web ("Dashboard Web"), route name
 * `dashboard`, /dashboard.
 *
 * Since 2026-09-17 the page shows only the daily mill report
 * (resources/views/dashboard/partials/daily-mill-report.blade.php), still
 * on DUMMY figures. The former "Ringkasan Input Stasiun" block — date/business
 * unit filters plus Weighbridge/Grading/Cages Track KPI cards — was removed
 * at the user's request. DashboardService is kept: the API controller
 * (App\Http\Controllers\Api\DashboardController) still uses it.
 */
#[Layout('dashboard.index')]
class DashboardHome extends Component
{
    public function render()
    {
        return view('livewire.dashboard.dashboard-home');
    }
}
