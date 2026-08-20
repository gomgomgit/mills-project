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
// screen-025--dashboard-web
Route::middleware(['auth', 'role:admin,supervisor,mill_management'])
    ->get('/dashboard', \App\Livewire\Dashboard\DashboardHome::class)
    ->name('dashboard');

// screen-026--laporan-manajemen — Mill Management only, per
// screen_tech_spec.actor_permissions (narrower than Dashboard Web above).
Route::middleware(['auth', 'role:mill_management'])
    ->get('/reports/management', \App\Livewire\Dashboard\ManagementReport::class)
    ->name('reports.management');

// screen-001--login-web
Route::get('/login', \App\Livewire\Auth\LoginForm::class)->name('login');

// screen-003--ganti-password-web
Route::middleware(['auth', 'role:admin,supervisor,mill_management'])
    ->get('/settings/password', \App\Livewire\Settings\ChangePasswordForm::class)
    ->name('settings.password');

// screen-035--production-process-activity-web
// Pure static Blade view (no Livewire component, no controller/service, per
// tech-spec v1) — the 15-tile station picker that replaces the 6 individual
// sidebar shortcuts (Data Browser + Input Weighbridge/Grading/Cages Track).
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/production-process-activity', fn () => view('data.production-process-activity'))
    ->name('production-process-activity');

// screen-016--data-browser-weighbridge-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/weighbridge', \App\Livewire\Data\DataBrowserWeighbridge::class)
    ->name('data.weighbridge');

// screen-022--form-weighbridge-web
// IMPORTANT — '/data/weighbridge/create' MUST be registered BEFORE
// '/data/weighbridge/{id}' (screen-019, right below) or Laravel would
// match the literal "create" segment against {id} instead.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/weighbridge/create', \App\Livewire\Data\FormWeighbridge::class)
    ->name('data.weighbridge.create');

// screen-019--detail-weighbridge-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/weighbridge/{id}', \App\Livewire\Data\DetailWeighbridge::class)
    ->name('data.weighbridge.detail');

// screen-022--form-weighbridge-web (edit mode) — extra '/edit' segment
// never collides with '/data/weighbridge/{id}' above regardless of
// registration order.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/weighbridge/{id}/edit', \App\Livewire\Data\FormWeighbridge::class)
    ->name('data.weighbridge.edit');

// screen-017--data-browser-grading-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/grading', \App\Livewire\Data\DataBrowserGrading::class)
    ->name('data.grading');

// screen-018--data-browser-cages-track-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/cages-track', \App\Livewire\Data\DataBrowserCagesTrack::class)
    ->name('data.cages-track');

// screen-023--form-grading-web
// IMPORTANT — '/data/grading/create' MUST be registered BEFORE
// '/data/grading/{id}' (screen-020, right below) or Laravel would match
// the literal "create" segment against {id} instead. Mirrors
// screen-022's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/grading/create', \App\Livewire\Data\FormGrading::class)
    ->name('data.grading.create');

// screen-020--detail-grading-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/grading/{id}', \App\Livewire\Data\DetailGrading::class)
    ->name('data.grading.detail');

// screen-023--form-grading-web (edit mode) — extra '/edit' segment never
// collides with '/data/grading/{id}' above regardless of registration
// order.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/grading/{id}/edit', \App\Livewire\Data\FormGrading::class)
    ->name('data.grading.edit');

// screen-024--form-cages-track-web
// IMPORTANT — '/data/cages-track/create' MUST be registered BEFORE
// '/data/cages-track/{id}' (screen-021, right below) or Laravel would match
// the literal "create" segment against {id} instead. Mirrors
// screen-022/023's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/cages-track/create', \App\Livewire\Data\FormCagesTrack::class)
    ->name('data.cages-track.create');

// screen-021--detail-cages-track-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/cages-track/{id}', \App\Livewire\Data\DetailCagesTrack::class)
    ->name('data.cages-track.detail');

// screen-024--form-cages-track-web (edit mode) — extra '/edit' segment never
// collides with '/data/cages-track/{id}' above regardless of registration
// order.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/cages-track/{id}/edit', \App\Livewire\Data\FormCagesTrack::class)
    ->name('data.cages-track.edit');

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

// screen-036--kelola-production-line
// Session-guarded ('auth') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/operator
// all have can_access=false for this screen). Mirrors screen-027/028/029's
// registration pattern exactly — EnsureRole::forbidden() aborts(403) with
// Laravel's default HTML error page for any non-admin session before
// App\Livewire\MasterData\KelolaProductionLine ever mounts. Inserted here
// (between Business Unit and Station) to mirror the hierarchy: Business
// Unit → Production Line → Station.
Route::middleware(['auth', 'role:admin'])
    ->get('/master-data/production-lines', \App\Livewire\MasterData\KelolaProductionLine::class)
    ->name('master-data.production-lines');

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

// screen-032--kelola-user-role
// Session-guarded ('auth') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/
// operator all have can_access=false for this screen). Mirrors
// screen-027/028/029/030/031/033's registration pattern exactly —
// EnsureRole::forbidden() aborts(403) with Laravel's default HTML error
// page for any non-admin session before App\Livewire\UserManagement\
// KelolaUserRole ever mounts.
Route::middleware(['auth', 'role:admin'])
    ->get('/users', \App\Livewire\UserManagement\KelolaUserRole::class)
    ->name('users.index');
// === ASDLC_ROUTES_END ===
