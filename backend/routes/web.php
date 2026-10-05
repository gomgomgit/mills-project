<?php

use App\Livewire\Auth\LoginForm;
use App\Livewire\Dashboard\DashboardHome;
use App\Livewire\Dashboard\LaporanBoilerRoom;
use App\Livewire\Dashboard\LaporanCagesTrack;
use App\Livewire\Dashboard\LaporanClarification;
use App\Livewire\Dashboard\LaporanGrading;
use App\Livewire\Dashboard\LaporanStasiun;
use App\Livewire\Dashboard\LaporanSterilizer;
use App\Livewire\Dashboard\LaporanStorageTank;
use App\Livewire\Dashboard\LaporanWeighbridge;
use App\Livewire\Dashboard\ManagementReport;
use App\Livewire\Data\DataBrowserBoilerRoom;
use App\Livewire\Data\DataBrowserCagesTrack;
use App\Livewire\Data\DataBrowserClarification;
use App\Livewire\Data\DataBrowserCpoDispatch;
use App\Livewire\Data\DataBrowserDepricarping;
use App\Livewire\Data\DataBrowserEffluentPlant;
use App\Livewire\Data\DataBrowserEngineRoom;
use App\Livewire\Data\DataBrowserGrading;
use App\Livewire\Data\DataBrowserKernelDispatch;
use App\Livewire\Data\DataBrowserKernelPlant;
use App\Livewire\Data\DataBrowserPressing;
use App\Livewire\Data\DataBrowserProcessQualityControl;
use App\Livewire\Data\DataBrowserProcessWater;
use App\Livewire\Data\DataBrowserSolidWasteDisposal;
use App\Livewire\Data\DataBrowserSterilizer;
use App\Livewire\Data\DataBrowserStorageTank;
use App\Livewire\Data\DataBrowserThreshing;
use App\Livewire\Data\DataBrowserWeighbridge;
use App\Livewire\Data\DetailBoilerRoom;
use App\Livewire\Data\DetailCagesTrack;
use App\Livewire\Data\DetailClarification;
use App\Livewire\Data\DetailCpoDispatch;
use App\Livewire\Data\DetailDepricarping;
use App\Livewire\Data\DetailEffluentPlant;
use App\Livewire\Data\DetailEngineRoom;
use App\Livewire\Data\DetailGrading;
use App\Livewire\Data\DetailKernelDispatch;
use App\Livewire\Data\DetailKernelPlant;
use App\Livewire\Data\DetailPressing;
use App\Livewire\Data\DetailProcessQualityControl;
use App\Livewire\Data\DetailProcessWater;
use App\Livewire\Data\DetailSolidWasteDisposal;
use App\Livewire\Data\DetailSterilizer;
use App\Livewire\Data\DetailStorageTank;
use App\Livewire\Data\DetailThreshing;
use App\Livewire\Data\DetailWeighbridge;
use App\Livewire\Data\FormBoilerRoom;
use App\Livewire\Data\FormCagesTrack;
use App\Livewire\Data\FormClarification;
use App\Livewire\Data\FormCpoDispatch;
use App\Livewire\Data\FormDepricarping;
use App\Livewire\Data\FormEffluentPlant;
use App\Livewire\Data\FormEngineRoom;
use App\Livewire\Data\FormGrading;
use App\Livewire\Data\FormKernelDispatch;
use App\Livewire\Data\FormKernelPlant;
use App\Livewire\Data\FormPressing;
use App\Livewire\Data\FormProcessQualityControl;
use App\Livewire\Data\FormProcessWater;
use App\Livewire\Data\FormSolidWasteDisposal;
use App\Livewire\Data\FormSterilizer;
use App\Livewire\Data\FormStorageTank;
use App\Livewire\Data\FormThreshing;
use App\Livewire\Data\FormWeighbridge;
use App\Livewire\MasterData\DetailPeriodePelaporan;
use App\Livewire\MasterData\KelolaBusinessUnit;
use App\Livewire\MasterData\KelolaCompany;
use App\Livewire\MasterData\KelolaCorporate;
use App\Livewire\MasterData\KelolaMachinery;
use App\Livewire\MasterData\KelolaPeriodePelaporan;
use App\Livewire\MasterData\KelolaProductionLine;
use App\Livewire\MasterData\KelolaStation;
use App\Livewire\MasterData\MasterDataTreeView;
use App\Livewire\Settings\ChangePasswordForm;
use App\Livewire\Settings\MillsSetting;
use App\Livewire\UserManagement\KelolaUserRole;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Livewire — Admin / Supervisor / Mill Management)
|--------------------------------------------------------------------------
| Session-based auth. Screen routes are registered by impl-2-screen between
| the markers below, one per screen tech-spec's `route` field.
*/

Route::get('/health', fn () => response()->json(['status' => 'ok']));

// Akar situs — dulu 404. Infrastruktur (bukan layar ASDLC), jadi di luar
// blok ASDLC seperti /health: user yang sudah login dialihkan ke beranda
// perannya (Operator → /beranda), selain itu ke Login.
Route::get('/', function () {
    $user = Auth::guard('web')->user();

    if ($user) {
        return redirect(app(AuthService::class)->redirectFor($user->role->value));
    }

    return redirect()->route('login');
})->name('home');

// Beranda Operator — halaman pendaratan sementara untuk login web Operator
// (keputusan produk 2026-10-04: Operator BOLEH login web dengan akses
// terbatas; layar "lihat data sendiri" akan dispesifikasikan terpisah lewat
// alur ASDLC). Sebelumnya Operator mendarat di /dashboard yang menjawab 403
// tanpa jalan keluar. Di luar blok ASDLC karena belum punya tech-spec.
Route::middleware(['auth', 'role:operator'])
    ->get('/beranda', fn () => view('operator.home'))
    ->name('operator.home');

// Logout — not a screen route (no tech-spec entry), infrastructure like
// /health above, so it lives outside the ASDLC-managed block. POST +
// 'auth' middleware (session-guarded, same as every other web route here)
// per Laravel's standard logout pattern: invalidate the session and
// regenerate both the session id and CSRF token so a stale session cookie
// can't be replayed, then redirect to Login.
Route::middleware('auth')->post('/logout', function (Request $request) {
    Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

// === ASDLC_ROUTES_START ===
// screen-025--dashboard-web
Route::middleware(['auth', 'role:admin,supervisor,mill_management'])
    ->get('/dashboard', DashboardHome::class)
    ->name('dashboard');

// screen-026--laporan-manajemen — Mill Management only, per
// screen_tech_spec.actor_permissions (narrower than Dashboard Web above).
Route::middleware(['auth', 'role:mill_management'])
    ->get('/reports/management', ManagementReport::class)
    ->name('reports.management');

// screen-001--login-web
Route::get('/login', LoginForm::class)->name('login');

// screen-003--ganti-password-web
// 'operator' ditambahkan 2026-10-04: Operator kini boleh login web
// (akses terbatas), dan Ganti Password adalah salah satu menu yang
// diizinkan untuknya — API ganti password-nya (screen-004) sudah menerima
// operator sejak awal, logikanya sama (AuthService::changePassword()).
Route::middleware(['auth', 'role:admin,supervisor,mill_management,operator'])
    ->get('/settings/password', ChangePasswordForm::class)
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
    ->get('/data/weighbridge', DataBrowserWeighbridge::class)
    ->name('data.weighbridge');

// screen-022--form-weighbridge-web
// IMPORTANT — '/data/weighbridge/create' MUST be registered BEFORE
// '/data/weighbridge/{id}' (screen-019, right below) or Laravel would
// match the literal "create" segment against {id} instead.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/weighbridge/create', FormWeighbridge::class)
    ->name('data.weighbridge.create');

// screen-019--detail-weighbridge-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/weighbridge/{id}', DetailWeighbridge::class)
    ->name('data.weighbridge.detail');

// screen-022--form-weighbridge-web (edit mode) — extra '/edit' segment
// never collides with '/data/weighbridge/{id}' above regardless of
// registration order.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/weighbridge/{id}/edit', FormWeighbridge::class)
    ->name('data.weighbridge.edit');

// screen-017--data-browser-grading-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/grading', DataBrowserGrading::class)
    ->name('data.grading');

// screen-018--data-browser-cages-track-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/cages-track', DataBrowserCagesTrack::class)
    ->name('data.cages-track');

// screen-023--form-grading-web
// IMPORTANT — '/data/grading/create' MUST be registered BEFORE
// '/data/grading/{id}' (screen-020, right below) or Laravel would match
// the literal "create" segment against {id} instead. Mirrors
// screen-022's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/grading/create', FormGrading::class)
    ->name('data.grading.create');

// screen-020--detail-grading-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/grading/{id}', DetailGrading::class)
    ->name('data.grading.detail');

// screen-023--form-grading-web (edit mode) — extra '/edit' segment never
// collides with '/data/grading/{id}' above regardless of registration
// order.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/grading/{id}/edit', FormGrading::class)
    ->name('data.grading.edit');

// screen-024--form-cages-track-web
// IMPORTANT — '/data/cages-track/create' MUST be registered BEFORE
// '/data/cages-track/{id}' (screen-021, right below) or Laravel would match
// the literal "create" segment against {id} instead. Mirrors
// screen-022/023's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/cages-track/create', FormCagesTrack::class)
    ->name('data.cages-track.create');

// screen-021--detail-cages-track-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/cages-track/{id}', DetailCagesTrack::class)
    ->name('data.cages-track.detail');

// screen-024--form-cages-track-web (edit mode) — extra '/edit' segment never
// collides with '/data/cages-track/{id}' above regardless of registration
// order.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/cages-track/{id}/edit', FormCagesTrack::class)
    ->name('data.cages-track.edit');

// screen-127--master-data-tree-view
// Session-guarded ('auth') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions). Read-only navigation aid over the
// Corporate/Company/Business Unit/Production Line hierarchy — clicking a
// node navigates to the corresponding Kelola screen below, pre-filtered.
// Placed before screen-027 since it's the overview entry point for the
// whole master-data section.
Route::middleware(['auth', 'role:admin'])
    ->get('/master-data/tree-view', MasterDataTreeView::class)
    ->name('master-data.tree-view');

// screen-027--kelola-corporate
// Session-guarded ('auth') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/operator
// all have can_access=false for this screen). EnsureRole::forbidden()
// aborts(403) with the app's 403 page (resources/views/errors/403.blade.php) for any non-admin
// session before App\Livewire\MasterData\KelolaCorporate ever mounts —
// satisfies the "non-admin sees an access-denied state, no list/controls
// rendered" requirement at the routing layer.
Route::middleware(['auth', 'role:admin'])
    ->get('/master-data/corporates', KelolaCorporate::class)
    ->name('master-data.corporates');

// screen-028--kelola-company
// Session-guarded ('auth') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/operator
// all have can_access=false for this screen). Mirrors screen-027's
// registration pattern exactly — EnsureRole::forbidden() aborts(403) with
// the app's 403 page (resources/views/errors/403.blade.php) for any non-admin session before
// App\Livewire\MasterData\KelolaCompany ever mounts.
Route::middleware(['auth', 'role:admin'])
    ->get('/master-data/companies', KelolaCompany::class)
    ->name('master-data.companies');

// screen-029--kelola-business-unit
// Session-guarded ('auth') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/operator
// all have can_access=false for this screen). Mirrors screen-027/028's
// registration pattern exactly — EnsureRole::forbidden() aborts(403) with
// the app's 403 page (resources/views/errors/403.blade.php) for any non-admin session before
// App\Livewire\MasterData\KelolaBusinessUnit ever mounts.
Route::middleware(['auth', 'role:admin'])
    ->get('/master-data/business-units', KelolaBusinessUnit::class)
    ->name('master-data.business-units');

// screen-036--kelola-production-line
// Session-guarded ('auth') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/operator
// all have can_access=false for this screen). Mirrors screen-027/028/029's
// registration pattern exactly — EnsureRole::forbidden() aborts(403) with
// the app's 403 page (resources/views/errors/403.blade.php) for any non-admin session before
// App\Livewire\MasterData\KelolaProductionLine ever mounts. Inserted here
// (between Business Unit and Station) to mirror the hierarchy: Business
// Unit → Production Line → Station.
Route::middleware(['auth', 'role:admin'])
    ->get('/master-data/production-lines', KelolaProductionLine::class)
    ->name('master-data.production-lines');

// screen-030--kelola-station
// Session-guarded ('auth') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/operator
// all have can_access=false for this screen). Mirrors screen-027/028/029's
// registration pattern exactly — EnsureRole::forbidden() aborts(403) with
// the app's 403 page (resources/views/errors/403.blade.php) for any non-admin session before
// App\Livewire\MasterData\KelolaStation ever mounts.
Route::middleware(['auth', 'role:admin'])
    ->get('/master-data/stations', KelolaStation::class)
    ->name('master-data.stations');

// screen-033--kelola-machinery-group — DISERAP ke screen-031 (2026-09-30).
// Rute ini TIDAK dihapus, hanya dialihkan: bookmark dan tautan lama akan
// patah kalau dibiarkan 404, dan nama rute 'master-data.machinery-groups'
// masih dipakai route() di beberapa tempat. Pengalihan permanen (301)
// supaya peramban dan crawler ikut memperbarui.
//
// Guard 'auth'+'role:admin' dipertahankan: mengalihkan lebih dulu akan
// membocorkan keberadaan halaman admin ke sesi non-admin, yang sebelumnya
// dijawab 403.
Route::middleware(['auth', 'role:admin'])->group(function () {
    // Route::redirect() tidak tersedia di RouteRegistrar, jadi harus di
    // dalam group() — bukan dirantai setelah middleware().
    Route::redirect('/master-data/machinery-groups', '/master-data/machinery', 301)
        ->name('master-data.machinery-groups');
});

// screen-031--kelola-machinery — Kelola Mesin (hasil penggabungan dengan
// screen-033 pada 2026-09-30: satu layar untuk Machinery Group dan Machinery).
// Session-guarded ('auth') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/
// operator all have can_access=false for this screen). Mirrors
// screen-027/028/029/030/033's registration pattern exactly —
// EnsureRole::forbidden() aborts(403) with the app's 403 page
// (resources/views/errors/403.blade.php) for any non-admin session before
// App\Livewire\MasterData\KelolaMachinery ever mounts. This is the LAST screen of this
// master-data round.
Route::middleware(['auth', 'role:admin'])
    ->get('/master-data/machinery', KelolaMachinery::class)
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
    ->get('/mill-settings', MillsSetting::class)
    ->name('mill-settings');

// screen-032--kelola-user-role
// Session-guarded ('auth') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/
// operator all have can_access=false for this screen). Mirrors
// screen-027/028/029/030/031/033's registration pattern exactly —
// EnsureRole::forbidden() aborts(403) with the app's 403 page
// (resources/views/errors/403.blade.php) for any non-admin session before
// App\Livewire\UserManagement\KelolaUserRole ever mounts.
Route::middleware(['auth', 'role:admin'])
    ->get('/users', KelolaUserRole::class)
    ->name('users.index');

// screen-049--data-browser-threshing-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/threshing', DataBrowserThreshing::class)
    ->name('data.threshing');

// screen-057--form-threshing-web
// IMPORTANT — '/data/threshing/create' MUST be registered BEFORE
// '/data/threshing/{id}' (screen-053, right below) or Laravel would match
// the literal 'create' segment as {id} instead. Mirrors
// screen-024--form-cages-track-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/threshing/create', FormThreshing::class)
    ->name('data.threshing.create');

// screen-053--detail-threshing-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/threshing/{id}', DetailThreshing::class)
    ->name('data.threshing.detail');

// screen-057--form-threshing-web (edit mode) — extra '/edit' segment never
// collides with '/data/threshing/{id}' above regardless of registration
// order (different path shape), but kept after 'create' for readability.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/threshing/{id}/edit', FormThreshing::class)
    ->name('data.threshing.edit');

// screen-050--data-browser-pressing-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/pressing', DataBrowserPressing::class)
    ->name('data.pressing');

// screen-058--form-pressing-web
// IMPORTANT — '/data/pressing/create' MUST be registered BEFORE
// '/data/pressing/{id}' (screen-054, right below) or Laravel would match
// the literal 'create' segment as {id} instead. Mirrors
// screen-057--form-threshing-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/pressing/create', FormPressing::class)
    ->name('data.pressing.create');

// screen-054--detail-pressing-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/pressing/{id}', DetailPressing::class)
    ->name('data.pressing.detail');

// screen-058--form-pressing-web (edit mode) — extra '/edit' segment never
// collides with '/data/pressing/{id}' above regardless of registration
// order (different path shape), but kept after 'create' for readability.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/pressing/{id}/edit', FormPressing::class)
    ->name('data.pressing.edit');

// screen-051--data-browser-depricarping-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/depricarping', DataBrowserDepricarping::class)
    ->name('data.depricarping');

// screen-059--form-depricarping-web
// IMPORTANT — '/data/depricarping/create' MUST be registered BEFORE
// '/data/depricarping/{id}' (screen-055, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-058--form-pressing-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/depricarping/create', FormDepricarping::class)
    ->name('data.depricarping.create');

// screen-055--detail-depricarping-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/depricarping/{id}', DetailDepricarping::class)
    ->name('data.depricarping.detail');

// screen-059--form-depricarping-web (edit mode) — extra '/edit' segment
// never collides with '/data/depricarping/{id}' above regardless of
// registration order (different path shape), but kept after 'create' for
// readability.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/depricarping/{id}/edit', FormDepricarping::class)
    ->name('data.depricarping.edit');

// screen-052--data-browser-kernel-plant-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/kernel-plant', DataBrowserKernelPlant::class)
    ->name('data.kernel-plant');

// screen-060--form-kernel-plant-web
// IMPORTANT — '/data/kernel-plant/create' MUST be registered BEFORE
// '/data/kernel-plant/{id}' (screen-056, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-059--form-depricarping-web's registration pattern.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/kernel-plant/create', FormKernelPlant::class)
    ->name('data.kernel-plant.create');

// screen-056--detail-kernel-plant-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/kernel-plant/{id}', DetailKernelPlant::class)
    ->name('data.kernel-plant.detail');

// screen-060--form-kernel-plant-web (edit mode) — extra '/edit' segment
// never collides with '/data/kernel-plant/{id}' above regardless of
// registration order (different path shape), but kept after 'create' for
// readability.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/kernel-plant/{id}/edit', FormKernelPlant::class)
    ->name('data.kernel-plant.edit');

// screen-091--data-browser-solid-waste-disposal-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/solid-waste-disposal', DataBrowserSolidWasteDisposal::class)
    ->name('data.solid-waste-disposal');

// screen-111--form-solid-waste-disposal-web
// IMPORTANT — '/data/solid-waste-disposal/create' MUST be registered
// BEFORE '/data/solid-waste-disposal/{id}' (screen-101, right below) or
// Laravel would match the literal 'create' segment as {id} instead.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/solid-waste-disposal/create', FormSolidWasteDisposal::class)
    ->name('data.solid-waste-disposal.create');

// screen-101--detail-solid-waste-disposal-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/solid-waste-disposal/{id}', DetailSolidWasteDisposal::class)
    ->name('data.solid-waste-disposal.detail');

// screen-111--form-solid-waste-disposal-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/solid-waste-disposal/{id}/edit', FormSolidWasteDisposal::class)
    ->name('data.solid-waste-disposal.edit');

// screen-092--data-browser-process-water-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/process-water', DataBrowserProcessWater::class)
    ->name('data.process-water');

// screen-112--form-process-water-web
// IMPORTANT — '/data/process-water/create' MUST be registered BEFORE
// '/data/process-water/{id}' (screen-102, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-057--form-threshing-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/process-water/create', FormProcessWater::class)
    ->name('data.process-water.create');

// screen-102--detail-process-water-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/process-water/{id}', DetailProcessWater::class)
    ->name('data.process-water.detail');

// screen-112--form-process-water-web (edit mode) — extra '/edit' segment
// never collides with '/data/process-water/{id}' above regardless of
// registration order (different path shape), but kept after 'create' for
// readability.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/process-water/{id}/edit', FormProcessWater::class)
    ->name('data.process-water.edit');

// screen-093--data-browser-kernel-dispatch-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/kernel-dispatch', DataBrowserKernelDispatch::class)
    ->name('data.kernel-dispatch');

// screen-113--form-kernel-dispatch-web
// IMPORTANT — '/data/kernel-dispatch/create' MUST be registered BEFORE
// '/data/kernel-dispatch/{id}' (screen-103, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-111--form-solid-waste-disposal-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/kernel-dispatch/create', FormKernelDispatch::class)
    ->name('data.kernel-dispatch.create');

// screen-103--detail-kernel-dispatch-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/kernel-dispatch/{id}', DetailKernelDispatch::class)
    ->name('data.kernel-dispatch.detail');

// screen-113--form-kernel-dispatch-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/kernel-dispatch/{id}/edit', FormKernelDispatch::class)
    ->name('data.kernel-dispatch.edit');

// screen-094--data-browser-cpo-dispatch-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/cpo-dispatch', DataBrowserCpoDispatch::class)
    ->name('data.cpo-dispatch');

// screen-114--form-cpo-dispatch-web
// IMPORTANT — '/data/cpo-dispatch/create' MUST be registered BEFORE
// '/data/cpo-dispatch/{id}' (screen-104, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-113--form-kernel-dispatch-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/cpo-dispatch/create', FormCpoDispatch::class)
    ->name('data.cpo-dispatch.create');

// screen-104--detail-cpo-dispatch-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/cpo-dispatch/{id}', DetailCpoDispatch::class)
    ->name('data.cpo-dispatch.detail');

// screen-114--form-cpo-dispatch-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/cpo-dispatch/{id}/edit', FormCpoDispatch::class)
    ->name('data.cpo-dispatch.edit');

// screen-095--data-browser-effluent-plant-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/effluent-plant', DataBrowserEffluentPlant::class)
    ->name('data.effluent-plant');

// screen-115--form-effluent-plant-web
// IMPORTANT — '/data/effluent-plant/create' MUST be registered BEFORE
// '/data/effluent-plant/{id}' (screen-105, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-112--form-process-water-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/effluent-plant/create', FormEffluentPlant::class)
    ->name('data.effluent-plant.create');

// screen-105--detail-effluent-plant-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/effluent-plant/{id}', DetailEffluentPlant::class)
    ->name('data.effluent-plant.detail');

// screen-115--form-effluent-plant-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/effluent-plant/{id}/edit', FormEffluentPlant::class)
    ->name('data.effluent-plant.edit');

// screen-096--data-browser-storage-tank-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/storage-tank', DataBrowserStorageTank::class)
    ->name('data.storage-tank');

// screen-116--form-storage-tank-web
// IMPORTANT — '/data/storage-tank/create' MUST be registered BEFORE
// '/data/storage-tank/{id}' (screen-106, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-115--form-effluent-plant-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/storage-tank/create', FormStorageTank::class)
    ->name('data.storage-tank.create');

// screen-106--detail-storage-tank-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/storage-tank/{id}', DetailStorageTank::class)
    ->name('data.storage-tank.detail');

// screen-116--form-storage-tank-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/storage-tank/{id}/edit', FormStorageTank::class)
    ->name('data.storage-tank.edit');

// screen-097--data-browser-engine-room-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/engine-room', DataBrowserEngineRoom::class)
    ->name('data.engine-room');

// screen-117--form-engine-room-web
// IMPORTANT — '/data/engine-room/create' MUST be registered BEFORE
// '/data/engine-room/{id}' (screen-107, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-116--form-storage-tank-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/engine-room/create', FormEngineRoom::class)
    ->name('data.engine-room.create');

// screen-107--detail-engine-room-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/engine-room/{id}', DetailEngineRoom::class)
    ->name('data.engine-room.detail');

// screen-117--form-engine-room-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/engine-room/{id}/edit', FormEngineRoom::class)
    ->name('data.engine-room.edit');

// screen-098--data-browser-boiler-room-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/boiler-room', DataBrowserBoilerRoom::class)
    ->name('data.boiler-room');

// screen-118--form-boiler-room-web
// IMPORTANT — '/data/boiler-room/create' MUST be registered BEFORE
// '/data/boiler-room/{id}' (screen-108, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-117--form-engine-room-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/boiler-room/create', FormBoilerRoom::class)
    ->name('data.boiler-room.create');

// screen-108--detail-boiler-room-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/boiler-room/{id}', DetailBoilerRoom::class)
    ->name('data.boiler-room.detail');

// screen-118--form-boiler-room-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/boiler-room/{id}/edit', FormBoilerRoom::class)
    ->name('data.boiler-room.edit');

// screen-099--data-browser-clarification-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/clarification', DataBrowserClarification::class)
    ->name('data.clarification');

// screen-119--form-clarification-web
// IMPORTANT — '/data/clarification/create' MUST be registered BEFORE
// '/data/clarification/{id}' (screen-109, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-118--form-boiler-room-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/clarification/create', FormClarification::class)
    ->name('data.clarification.create');

// screen-109--detail-clarification-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/clarification/{id}', DetailClarification::class)
    ->name('data.clarification.detail');

// screen-119--form-clarification-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/clarification/{id}/edit', FormClarification::class)
    ->name('data.clarification.edit');

// screen-100--data-browser-process-quality-control-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/process-quality-control', DataBrowserProcessQualityControl::class)
    ->name('data.process-quality-control');

// screen-120--form-process-quality-control-web
// IMPORTANT — '/data/process-quality-control/create' MUST be registered
// BEFORE '/data/process-quality-control/{id}' (screen-110, right below) or
// Laravel would match the literal 'create' segment as {id} instead. Mirrors
// screen-119--form-clarification-web's registration pattern exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/process-quality-control/create', FormProcessQualityControl::class)
    ->name('data.process-quality-control.create');

// screen-110--detail-process-quality-control-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/process-quality-control/{id}', DetailProcessQualityControl::class)
    ->name('data.process-quality-control.detail');

// screen-120--form-process-quality-control-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/process-quality-control/{id}/edit', FormProcessQualityControl::class)
    ->name('data.process-quality-control.edit');

// screen-124--data-browser-sterilizer-web
// This is the FINAL station of this project — after this, all 18
// canonical stations have a full web Data Browser/Detail/Form.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/sterilizer', DataBrowserSterilizer::class)
    ->name('data.sterilizer');

// screen-126--form-sterilizer-web
// IMPORTANT — '/data/sterilizer/create' MUST be registered BEFORE
// '/data/sterilizer/{id}' (screen-125, right below) or Laravel would
// match the literal 'create' segment as {id} instead. Mirrors
// screen-120--form-process-quality-control-web's registration pattern
// exactly.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/sterilizer/create', FormSterilizer::class)
    ->name('data.sterilizer.create');

// screen-125--detail-sterilizer-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/sterilizer/{id}', DetailSterilizer::class)
    ->name('data.sterilizer.detail');

// screen-126--form-sterilizer-web (edit mode)
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/sterilizer/{id}/edit', FormSterilizer::class)
    ->name('data.sterilizer.edit');

// screen-128--kelola-periode-pelaporan
// Session-guarded ('auth') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor / mill_management /
// operator all have can_access=false for this screen, closure actions
// included). Mirrors screen-027/028/029/030/031/033's registration
// pattern exactly — EnsureRole::forbidden() aborts(403) with the app's
// 403 page (resources/views/errors/403.blade.php) for any non-admin session
// before App\Livewire\MasterData\KelolaPeriodePelaporan ever mounts.
Route::middleware(['auth', 'role:admin'])
    ->get('/master-data/periods', KelolaPeriodePelaporan::class)
    ->name('master-data.periods');

// screen-142--detail-periode-pelaporan (usecase-145 lihat detail +
// usecase-140 tutup/buka kembali + usecase-144 buka stasiun). The station
// list of a period and EVERY per-station action live here since 2026-09-27
// — screen-128 above is the list only.
//
// Same guards as the list ('auth' + 'role:admin'), because it is the same
// screen family: EnsureRole::forbidden() aborts(403) before the component
// mounts for a non-admin session. Registered AFTER '/master-data/periods'
// so the literal path can never be matched as a {id}. Mirrors
// screen-106--detail-storage-tank-web's '/data/storage-tank/{id}'
// registration — the detail-* pattern this is the first Master Data
// instance of.
Route::middleware(['auth', 'role:admin'])
    ->get('/master-data/periods/{id}', DetailPeriodePelaporan::class)
    ->name('master-data.periods.detail');

// screen-129--laporan-sterilizer-web (usecase-129 — Laporan Periode
// Sterilizer). Session-guarded + role-guarded to supervisor /
// mill_management / admin per screen_tech_spec.actor_permissions.
//
// Route is /reports/sterilizer, NOT /laporan/sterilizer — the repo's
// convention for report screens is the English /reports/* prefix
// (/reports/management, screen-026), and no route in this file uses
// /laporan.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/reports/sterilizer', LaporanSterilizer::class)
    ->name('reports.sterilizer');

// screen-140--laporan-stasiun-web (usecase-142 — Pilih Stasiun untuk
// Laporan). Pintu masuk tunggal ke seluruh laporan periode per stasiun,
// dan satu-satunya entri sidebar untuk keluarga laporan itu.
//
// Session-guarded + role-guarded to supervisor / mill_management / admin
// per screen_tech_spec.actor_permissions — sama persis dengan screen-129.
// Operator adalah aktor mobile-only tanpa akses web sama sekali.
//
// Route is /reports, NOT /laporan — the repo's convention for report
// screens is the English /reports/* prefix (/reports/management,
// /reports/sterilizer).
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/reports', LaporanStasiun::class)
    ->name('reports.stations');

// screen-130--laporan-cages-track-web (usecase-130 — Laporan Periode
// Cages & Tracks). Session-guarded + role-guarded to supervisor /
// mill_management / admin per screen_tech_spec.actor_permissions —
// Operator is a mobile-only actor with no web access at all; its reporting
// path is screen-136 (mobile).
//
// Route is /reports/cages-track, NOT /laporan/cages-track — the repo's
// convention for report screens is the English /reports/* prefix
// (/reports/management, /reports/stations, /reports/sterilizer), and no
// route in this file uses /laporan.
//
// Reachable from the UI ONLY through the Cages & Tracks tile on
// screen-140 (/reports), which lights up because
// StationReportService::REPORT_ROUTES now maps the 'cages-track' code to
// this route name.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/reports/cages-track', LaporanCagesTrack::class)
    ->name('reports.cages-track');

// screen-131--laporan-boiler-room-web (usecase-131 — Laporan Periode Boiler
// Room). Session-guarded + role-guarded to supervisor / mill_management /
// admin per screen_tech_spec.actor_permissions — Operator has no web access
// at all. Unlike Cages & Tracks, Operator is refused on the API side too:
// its mobile reporting path (screen-137) has not been built.
//
// Route is /reports/boiler-room, NOT /laporan/boiler-room — the repo's
// convention for report screens is the English /reports/* prefix
// (/reports/management, /reports/stations, /reports/sterilizer,
// /reports/cages-track), and no route in this file uses /laporan.
//
// Reachable from the UI ONLY through the Boiler Room tile on screen-140
// (/reports), which lights up because StationReportService::REPORT_ROUTES
// now maps the 'boiler-room' code to this route name. Without that one line
// the report exists, every test here passes, and the screen stays
// unreachable.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/reports/boiler-room', LaporanBoilerRoom::class)
    ->name('reports.boiler-room');

// screen-132--laporan-clarification-web (Laporan Periode Clarification).
//
// Supervisor / Mill Management / Admin only. Operator is NOT admitted here
// and is not admitted on /api/clarification-reports either — this is the
// web report, and its mobile reporting path (screen-138) has not been
// built.
//
// Route is /reports/clarification, NOT /laporan/clarification — the repo's
// convention for report screens is the English /reports/* prefix
// (/reports/management, /reports/stations, /reports/sterilizer,
// /reports/cages-track, /reports/boiler-room), and no route in this file
// uses /laporan.
//
// Reachable from the UI ONLY through the Clarification tile on screen-140
// (/reports), which lights up because StationReportService::REPORT_ROUTES
// now maps the 'clarification' code to this route name. Without that one
// line the report exists, every test here passes, and the screen stays
// unreachable.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/reports/clarification', LaporanClarification::class)
    ->name('reports.clarification');

// screen-133--laporan-storage-tank-web (Laporan Periode Storage Tank).
//
// Supervisor / Mill Management / Admin only. Operator is NOT admitted here
// and this middleware list is DELIBERATELY unchanged by screen-139: on
// 2026-09-25 the four /api/storage-tank-reports routes were widened to
// Operator for the mobile report, which reuses them, but Operator has no web
// UI and the widening stops at the API. Refusing here does not depend on the
// service either — LaporanStorageTank::canAccess() keeps its own role list.
//
// Route is /reports/storage-tank, NOT /laporan/storage-tank — the repo's
// convention for report screens is the English /reports/* prefix
// (/reports/management, /reports/stations, /reports/sterilizer,
// /reports/cages-track, /reports/boiler-room, /reports/clarification), and
// no route in this file uses /laporan. It is also distinct from the DATA
// screens for the same station, which live under /data/storage-tank
// (browser), /data/storage-tank/create (input form, screen-116),
// /data/storage-tank/{id} and /data/storage-tank/{id}/edit — this report
// reads what those screens write and never writes anything itself.
//
// Reachable from the UI ONLY through the Storage Tank tile on screen-140
// (/reports), which lights up because StationReportService::REPORT_ROUTES
// now maps the 'storage-tank' code to this route name. Without that one
// line the report exists, every test here passes, and the screen stays
// unreachable.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/reports/storage-tank', LaporanStorageTank::class)
    ->name('reports.storage-tank');

// screen-143--laporan-weighbridge-web (Laporan Periode Weighbridge).
//
// Supervisor / Mill Management / Admin only. Operator is NOT admitted here and
// has no web UI at all; the refusal does not depend on the service either —
// LaporanWeighbridge::canAccess() keeps its own role list and the component
// refuses to mount. The /api/weighbridge-reports prefix is likewise
// three-roles-only for now, because the mobile Weighbridge report (screen-144)
// is not built yet.
//
// Route is /reports/weighbridge, NOT /laporan/weighbridge — the repo's
// convention for report screens is the English /reports/* prefix
// (/reports/management, /reports/stations, /reports/sterilizer,
// /reports/cages-track, /reports/boiler-room, /reports/clarification,
// /reports/storage-tank), and no route in this file uses /laporan. It is also
// distinct from the DATA screens for the same station, which live under
// /data/weighbridge (browser), /data/weighbridge/create (input form,
// screen-022), /data/weighbridge/{id} and /data/weighbridge/{id}/edit — this
// report reads what those screens write and never writes anything itself.
//
// Reachable from the UI ONLY through the Weighbridge tile on screen-140
// (/reports), which lights up because StationReportService::REPORT_ROUTES now
// maps the 'weighbridge' code to this route name. Without that one line the
// report exists, every test here passes, and the screen stays unreachable.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/reports/weighbridge', LaporanWeighbridge::class)
    ->name('reports.weighbridge');

// screen-146--laporan-grading-web (Laporan Periode Grading).
//
// Supervisor / Mill Management / Admin only. Operator is NOT admitted here and
// has no web report UI at all — and on this screen that refusal is worth
// stating twice, because the API prefix DOES admit Operator: the mobile
// Grading report (screen-147) ships in the same change and reuses the same
// four endpoints. LaporanGrading::canAccess() therefore keeps its OWN role
// list rather than borrowing GradingReportService::guardAccess(); if it ever
// delegates, this web screen silently opens to Operator.
//
// Route is /reports/grading, NOT /laporan/grading — the repo's convention for
// report screens is the English /reports/* prefix. It is also distinct from the
// DATA screens for the same station, which live under /data/grading (browser),
// /data/grading/create (input form, screen-023), /data/grading/{id} and
// /data/grading/{id}/edit — this report reads what those screens write and
// never writes anything itself.
//
// Reachable from the UI ONLY through the Grading tile on screen-140
// (/reports), which lights up because StationReportService::REPORT_ROUTES now
// maps the 'grading' code to this route name. Without that one line the report
// exists, every test here passes, and the screen stays unreachable.
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/reports/grading', LaporanGrading::class)
    ->name('reports.grading');

// === ASDLC_ROUTES_END ===
