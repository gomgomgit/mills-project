<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Livewire — Admin / Supervisor / Mill Management)
|--------------------------------------------------------------------------
| Session-based auth. Screen routes are registered by impl-2-screen between
| the markers below, one per screen tech-spec's `route` field.
*/

Route::get('/health', fn () => response()->json(['status' => 'ok']));

// === ASDLC_ROUTES_START ===
// screen-025--dashboard-web (PLACEHOLDER — see App\Livewire\Dashboard\
// DashboardHome's docblock. screen-025 has no business/tech spec or real
// implementation yet; this stub exists only so the sidebar's pre-existing
// "Dashboard" link resolves instead of 404ing, unblocking manual testing
// of the other already-implemented screens. Replace wholesale, not
// extend, once screen-025's real spec+implementation land.)
Route::middleware(['auth', 'role:admin,supervisor,mill_management'])
    ->get('/dashboard', \App\Livewire\Dashboard\DashboardHome::class)
    ->name('dashboard');

// screen-001--login-web
Route::get('/login', \App\Livewire\Auth\LoginForm::class)->name('login');

// screen-003--ganti-password-web
Route::middleware(['auth', 'role:admin,supervisor,mill_management'])
    ->get('/settings/password', \App\Livewire\Settings\ChangePasswordForm::class)
    ->name('settings.password');

// screen-016--data-browser-weighbridge-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/weighbridge', \App\Livewire\Data\DataBrowserWeighbridge::class)
    ->name('data.weighbridge');

// screen-017--data-browser-grading-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/grading', \App\Livewire\Data\DataBrowserGrading::class)
    ->name('data.grading');

// screen-018--data-browser-cages-track-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/cages-track', \App\Livewire\Data\DataBrowserCagesTrack::class)
    ->name('data.cages-track');

// screen-027--kelola-corporate
// Session-guarded ('auth') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/operator
// all have can_access=false for this screen). EnsureRole::forbidden()
// aborts(403) with Laravel's default HTML error page for any non-admin
// session before App\Livewire\MasterData\KelolaCorporate ever mounts —
// satisfies the "non-admin sees an access-denied state, no list/controls
// rendered" requirement at the routing layer.
Route::middleware(['auth', 'role:admin'])
    ->get('/master-data/corporates', \App\Livewire\MasterData\KelolaCorporate::class)
    ->name('master-data.corporates');

// screen-028--kelola-company
// Session-guarded ('auth') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/operator
// all have can_access=false for this screen). Mirrors screen-027's
// registration pattern exactly — EnsureRole::forbidden() aborts(403) with
// Laravel's default HTML error page for any non-admin session before
// App\Livewire\MasterData\KelolaCompany ever mounts.
Route::middleware(['auth', 'role:admin'])
    ->get('/master-data/companies', \App\Livewire\MasterData\KelolaCompany::class)
    ->name('master-data.companies');

// screen-029--kelola-business-unit
// Session-guarded ('auth') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/operator
// all have can_access=false for this screen). Mirrors screen-027/028's
// registration pattern exactly — EnsureRole::forbidden() aborts(403) with
// Laravel's default HTML error page for any non-admin session before
// App\Livewire\MasterData\KelolaBusinessUnit ever mounts.
Route::middleware(['auth', 'role:admin'])
    ->get('/master-data/business-units', \App\Livewire\MasterData\KelolaBusinessUnit::class)
    ->name('master-data.business-units');

// screen-030--kelola-station
// Session-guarded ('auth') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/operator
// all have can_access=false for this screen). Mirrors screen-027/028/029's
// registration pattern exactly — EnsureRole::forbidden() aborts(403) with
// Laravel's default HTML error page for any non-admin session before
// App\Livewire\MasterData\KelolaStation ever mounts.
Route::middleware(['auth', 'role:admin'])
    ->get('/master-data/stations', \App\Livewire\MasterData\KelolaStation::class)
    ->name('master-data.stations');

// screen-033--kelola-machinery-group
// Session-guarded ('auth') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/
// operator all have can_access=false for this screen). Mirrors
// screen-027/028/029/030's registration pattern exactly —
// EnsureRole::forbidden() aborts(403) with Laravel's default HTML error
// page for any non-admin session before
// App\Livewire\MasterData\KelolaMachineryGroup ever mounts.
Route::middleware(['auth', 'role:admin'])
    ->get('/master-data/machinery-groups', \App\Livewire\MasterData\KelolaMachineryGroup::class)
    ->name('master-data.machinery-groups');

// screen-031--kelola-machinery
// Session-guarded ('auth') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/
// operator all have can_access=false for this screen). Mirrors
// screen-027/028/029/030/033's registration pattern exactly —
// EnsureRole::forbidden() aborts(403) with Laravel's default HTML error
// page for any non-admin session before App\Livewire\MasterData\
// KelolaMachinery ever mounts. This is the LAST screen of this
// master-data round.
Route::middleware(['auth', 'role:admin'])
    ->get('/master-data/machinery', \App\Livewire\MasterData\KelolaMachinery::class)
    ->name('master-data.machinery');

// screen-034--mills-setting
// Session-guarded ('auth') + role-guarded (admin AND mill_management, per
// screen_tech_spec.actor_permissions — supervisor/operator have
// can_access=false). Per-resource ownership scoping (Mill Management
// restricted to their own business_unit_id) is enforced INSIDE
// App\Livewire\Settings\MillsSetting / MillSettingService::checkAccess(),
// not at this route-level middleware, since 'role:...' can only gate by
// role, not by which mill is being configured.
Route::middleware(['auth', 'role:admin,mill_management'])
    ->get('/mill-settings', \App\Livewire\Settings\MillsSetting::class)
    ->name('mill-settings');
// === ASDLC_ROUTES_END ===
