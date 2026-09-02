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

// Logout — not a screen route (no tech-spec entry), infrastructure like
// /health above, so it lives outside the ASDLC-managed block. POST +
// 'auth' middleware (session-guarded, same as every other web route here)
// per Laravel's standard logout pattern: invalidate the session and
// regenerate both the session id and CSRF token so a stale session cookie
// can't be replayed, then redirect to Login.
Route::middleware('auth')->post('/logout', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

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

// screen-049--data-browser-threshing-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/threshing', \App\Livewire\Data\DataBrowserThreshing::class)
    ->name('data.threshing');

// screen-057--form-threshing-web
// IMPORTANT — '/data/threshing/create' MUST be registered BEFORE
// '/data/threshing/{id}' (screen-053, right below) or Laravel would match
// the literal 'create' segment as {id} instead. Mirrors
// screen-024--form-cages-track-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/threshing/create', \App\Livewire\Data\FormThreshing::class)
    ->name('data.threshing.create');

// screen-053--detail-threshing-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/threshing/{id}', \App\Livewire\Data\DetailThreshing::class)
    ->name('data.threshing.detail');

// screen-057--form-threshing-web (edit mode) — extra '/edit' segment never
// collides with '/data/threshing/{id}' above regardless of registration
// order (different path shape), but kept after 'create' for readability.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/threshing/{id}/edit', \App\Livewire\Data\FormThreshing::class)
    ->name('data.threshing.edit');

// screen-050--data-browser-pressing-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/pressing', \App\Livewire\Data\DataBrowserPressing::class)
    ->name('data.pressing');

// screen-058--form-pressing-web
// IMPORTANT — '/data/pressing/create' MUST be registered BEFORE
// '/data/pressing/{id}' (screen-054, right below) or Laravel would match
// the literal 'create' segment as {id} instead. Mirrors
// screen-057--form-threshing-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/pressing/create', \App\Livewire\Data\FormPressing::class)
    ->name('data.pressing.create');

// screen-054--detail-pressing-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/pressing/{id}', \App\Livewire\Data\DetailPressing::class)
    ->name('data.pressing.detail');

// screen-058--form-pressing-web (edit mode) — extra '/edit' segment never
// collides with '/data/pressing/{id}' above regardless of registration
// order (different path shape), but kept after 'create' for readability.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/pressing/{id}/edit', \App\Livewire\Data\FormPressing::class)
    ->name('data.pressing.edit');

// screen-051--data-browser-depricarping-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/depricarping', \App\Livewire\Data\DataBrowserDepricarping::class)
    ->name('data.depricarping');

// screen-059--form-depricarping-web
// IMPORTANT — '/data/depricarping/create' MUST be registered BEFORE
// '/data/depricarping/{id}' (screen-055, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-058--form-pressing-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/depricarping/create', \App\Livewire\Data\FormDepricarping::class)
    ->name('data.depricarping.create');

// screen-055--detail-depricarping-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/depricarping/{id}', \App\Livewire\Data\DetailDepricarping::class)
    ->name('data.depricarping.detail');

// screen-059--form-depricarping-web (edit mode) — extra '/edit' segment
// never collides with '/data/depricarping/{id}' above regardless of
// registration order (different path shape), but kept after 'create' for
// readability.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/depricarping/{id}/edit', \App\Livewire\Data\FormDepricarping::class)
    ->name('data.depricarping.edit');

// screen-052--data-browser-kernel-plant-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/kernel-plant', \App\Livewire\Data\DataBrowserKernelPlant::class)
    ->name('data.kernel-plant');

// screen-060--form-kernel-plant-web
// IMPORTANT — '/data/kernel-plant/create' MUST be registered BEFORE
// '/data/kernel-plant/{id}' (screen-056, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-059--form-depricarping-web's registration pattern.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/kernel-plant/create', \App\Livewire\Data\FormKernelPlant::class)
    ->name('data.kernel-plant.create');

// screen-056--detail-kernel-plant-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/kernel-plant/{id}', \App\Livewire\Data\DetailKernelPlant::class)
    ->name('data.kernel-plant.detail');

// screen-060--form-kernel-plant-web (edit mode) — extra '/edit' segment
// never collides with '/data/kernel-plant/{id}' above regardless of
// registration order (different path shape), but kept after 'create' for
// readability.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/kernel-plant/{id}/edit', \App\Livewire\Data\FormKernelPlant::class)
    ->name('data.kernel-plant.edit');

// screen-091--data-browser-solid-waste-disposal-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/solid-waste-disposal', \App\Livewire\Data\DataBrowserSolidWasteDisposal::class)
    ->name('data.solid-waste-disposal');

// screen-111--form-solid-waste-disposal-web
// IMPORTANT — '/data/solid-waste-disposal/create' MUST be registered
// BEFORE '/data/solid-waste-disposal/{id}' (screen-101, right below) or
// Laravel would match the literal 'create' segment as {id} instead.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/solid-waste-disposal/create', \App\Livewire\Data\FormSolidWasteDisposal::class)
    ->name('data.solid-waste-disposal.create');

// screen-101--detail-solid-waste-disposal-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/solid-waste-disposal/{id}', \App\Livewire\Data\DetailSolidWasteDisposal::class)
    ->name('data.solid-waste-disposal.detail');

// screen-111--form-solid-waste-disposal-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/solid-waste-disposal/{id}/edit', \App\Livewire\Data\FormSolidWasteDisposal::class)
    ->name('data.solid-waste-disposal.edit');

// screen-092--data-browser-process-water-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/process-water', \App\Livewire\Data\DataBrowserProcessWater::class)
    ->name('data.process-water');

// screen-112--form-process-water-web
// IMPORTANT — '/data/process-water/create' MUST be registered BEFORE
// '/data/process-water/{id}' (screen-102, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-057--form-threshing-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/process-water/create', \App\Livewire\Data\FormProcessWater::class)
    ->name('data.process-water.create');

// screen-102--detail-process-water-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/process-water/{id}', \App\Livewire\Data\DetailProcessWater::class)
    ->name('data.process-water.detail');

// screen-112--form-process-water-web (edit mode) — extra '/edit' segment
// never collides with '/data/process-water/{id}' above regardless of
// registration order (different path shape), but kept after 'create' for
// readability.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/process-water/{id}/edit', \App\Livewire\Data\FormProcessWater::class)
    ->name('data.process-water.edit');

// screen-093--data-browser-kernel-dispatch-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/kernel-dispatch', \App\Livewire\Data\DataBrowserKernelDispatch::class)
    ->name('data.kernel-dispatch');

// screen-113--form-kernel-dispatch-web
// IMPORTANT — '/data/kernel-dispatch/create' MUST be registered BEFORE
// '/data/kernel-dispatch/{id}' (screen-103, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-111--form-solid-waste-disposal-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/kernel-dispatch/create', \App\Livewire\Data\FormKernelDispatch::class)
    ->name('data.kernel-dispatch.create');

// screen-103--detail-kernel-dispatch-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/kernel-dispatch/{id}', \App\Livewire\Data\DetailKernelDispatch::class)
    ->name('data.kernel-dispatch.detail');

// screen-113--form-kernel-dispatch-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/kernel-dispatch/{id}/edit', \App\Livewire\Data\FormKernelDispatch::class)
    ->name('data.kernel-dispatch.edit');

// screen-094--data-browser-cpo-dispatch-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/cpo-dispatch', \App\Livewire\Data\DataBrowserCpoDispatch::class)
    ->name('data.cpo-dispatch');

// screen-114--form-cpo-dispatch-web
// IMPORTANT — '/data/cpo-dispatch/create' MUST be registered BEFORE
// '/data/cpo-dispatch/{id}' (screen-104, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-113--form-kernel-dispatch-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/cpo-dispatch/create', \App\Livewire\Data\FormCpoDispatch::class)
    ->name('data.cpo-dispatch.create');

// screen-104--detail-cpo-dispatch-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/cpo-dispatch/{id}', \App\Livewire\Data\DetailCpoDispatch::class)
    ->name('data.cpo-dispatch.detail');

// screen-114--form-cpo-dispatch-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/cpo-dispatch/{id}/edit', \App\Livewire\Data\FormCpoDispatch::class)
    ->name('data.cpo-dispatch.edit');

// screen-095--data-browser-effluent-plant-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/effluent-plant', \App\Livewire\Data\DataBrowserEffluentPlant::class)
    ->name('data.effluent-plant');

// screen-115--form-effluent-plant-web
// IMPORTANT — '/data/effluent-plant/create' MUST be registered BEFORE
// '/data/effluent-plant/{id}' (screen-105, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-112--form-process-water-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/effluent-plant/create', \App\Livewire\Data\FormEffluentPlant::class)
    ->name('data.effluent-plant.create');

// screen-105--detail-effluent-plant-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/effluent-plant/{id}', \App\Livewire\Data\DetailEffluentPlant::class)
    ->name('data.effluent-plant.detail');

// screen-115--form-effluent-plant-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/effluent-plant/{id}/edit', \App\Livewire\Data\FormEffluentPlant::class)
    ->name('data.effluent-plant.edit');

// screen-096--data-browser-storage-tank-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/storage-tank', \App\Livewire\Data\DataBrowserStorageTank::class)
    ->name('data.storage-tank');

// screen-116--form-storage-tank-web
// IMPORTANT — '/data/storage-tank/create' MUST be registered BEFORE
// '/data/storage-tank/{id}' (screen-106, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-115--form-effluent-plant-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/storage-tank/create', \App\Livewire\Data\FormStorageTank::class)
    ->name('data.storage-tank.create');

// screen-106--detail-storage-tank-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/storage-tank/{id}', \App\Livewire\Data\DetailStorageTank::class)
    ->name('data.storage-tank.detail');

// screen-116--form-storage-tank-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/storage-tank/{id}/edit', \App\Livewire\Data\FormStorageTank::class)
    ->name('data.storage-tank.edit');

// screen-097--data-browser-engine-room-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/engine-room', \App\Livewire\Data\DataBrowserEngineRoom::class)
    ->name('data.engine-room');

// screen-117--form-engine-room-web
// IMPORTANT — '/data/engine-room/create' MUST be registered BEFORE
// '/data/engine-room/{id}' (screen-107, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-116--form-storage-tank-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/engine-room/create', \App\Livewire\Data\FormEngineRoom::class)
    ->name('data.engine-room.create');

// screen-107--detail-engine-room-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/engine-room/{id}', \App\Livewire\Data\DetailEngineRoom::class)
    ->name('data.engine-room.detail');

// screen-117--form-engine-room-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/engine-room/{id}/edit', \App\Livewire\Data\FormEngineRoom::class)
    ->name('data.engine-room.edit');

// screen-098--data-browser-boiler-room-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/boiler-room', \App\Livewire\Data\DataBrowserBoilerRoom::class)
    ->name('data.boiler-room');

// screen-118--form-boiler-room-web
// IMPORTANT — '/data/boiler-room/create' MUST be registered BEFORE
// '/data/boiler-room/{id}' (screen-108, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-117--form-engine-room-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/boiler-room/create', \App\Livewire\Data\FormBoilerRoom::class)
    ->name('data.boiler-room.create');

// screen-108--detail-boiler-room-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/boiler-room/{id}', \App\Livewire\Data\DetailBoilerRoom::class)
    ->name('data.boiler-room.detail');

// screen-118--form-boiler-room-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/boiler-room/{id}/edit', \App\Livewire\Data\FormBoilerRoom::class)
    ->name('data.boiler-room.edit');

// screen-099--data-browser-clarification-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/clarification', \App\Livewire\Data\DataBrowserClarification::class)
    ->name('data.clarification');

// screen-119--form-clarification-web
// IMPORTANT — '/data/clarification/create' MUST be registered BEFORE
// '/data/clarification/{id}' (screen-109, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-118--form-boiler-room-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/clarification/create', \App\Livewire\Data\FormClarification::class)
    ->name('data.clarification.create');

// screen-109--detail-clarification-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/clarification/{id}', \App\Livewire\Data\DetailClarification::class)
    ->name('data.clarification.detail');

// screen-119--form-clarification-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/clarification/{id}/edit', \App\Livewire\Data\FormClarification::class)
    ->name('data.clarification.edit');

// screen-100--data-browser-process-quality-control-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/process-quality-control', \App\Livewire\Data\DataBrowserProcessQualityControl::class)
    ->name('data.process-quality-control');

// screen-120--form-process-quality-control-web
// IMPORTANT — '/data/process-quality-control/create' MUST be registered
// BEFORE '/data/process-quality-control/{id}' (screen-110, right below) or
// Laravel would match the literal 'create' segment as {id} instead. Mirrors
// screen-119--form-clarification-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/process-quality-control/create', \App\Livewire\Data\FormProcessQualityControl::class)
    ->name('data.process-quality-control.create');

// screen-110--detail-process-quality-control-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/process-quality-control/{id}', \App\Livewire\Data\DetailProcessQualityControl::class)
    ->name('data.process-quality-control.detail');

// screen-120--form-process-quality-control-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/process-quality-control/{id}/edit', \App\Livewire\Data\FormProcessQualityControl::class)
    ->name('data.process-quality-control.edit');

// screen-124--data-browser-sterilizer-web
// This is the FINAL station of this project — after this, all 18
// canonical stations have a full web Data Browser/Detail/Form.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/sterilizer', \App\Livewire\Data\DataBrowserSterilizer::class)
    ->name('data.sterilizer');

// screen-126--form-sterilizer-web
// IMPORTANT — '/data/sterilizer/create' MUST be registered BEFORE
// '/data/sterilizer/{id}' (screen-125, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-120--form-process-quality-control-web's registration pattern
// exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/sterilizer/create', \App\Livewire\Data\FormSterilizer::class)
    ->name('data.sterilizer.create');

// screen-125--detail-sterilizer-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/sterilizer/{id}', \App\Livewire\Data\DetailSterilizer::class)
    ->name('data.sterilizer.detail');

// screen-126--form-sterilizer-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/sterilizer/{id}/edit', \App\Livewire\Data\FormSterilizer::class)
    ->name('data.sterilizer.edit');
// === ASDLC_ROUTES_END ===
