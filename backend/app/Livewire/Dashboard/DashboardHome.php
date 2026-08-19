<?php

namespace App\Livewire\Dashboard;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DashboardHome — placeholder for screen-025--dashboard-web ("Dashboard
 * Web"), route name `dashboard`, /dashboard.
 *
 * screen-025 has not gone through its own business/tech spec + ASDLC
 * implementation cycle yet. This is a minimal empty-state stub only, so
 * the sidebar's pre-existing "Dashboard" link (url('/dashboard') in every
 * master-data/data-browser shell view) resolves instead of 404ing —
 * previously a blocker for manually testing/navigating the other already-
 * implemented screens. Replace this component (and its view) wholesale
 * once screen-025's real business+tech spec and implementation land; do
 * not build on top of this stub.
 *
 * Access control: route-level only, mirrors every other screen's pattern
 * — 'auth' + 'role:admin,supervisor,mill_management' per uiux-spec's
 * navigation_per_role (Dashboard menu item roles), guarded in
 * routes/web.php before this component ever mounts.
 */
#[Layout('dashboard.index')]
class DashboardHome extends Component
{
    public function render()
    {
        return view('livewire.dashboard.dashboard-home');
    }
}
