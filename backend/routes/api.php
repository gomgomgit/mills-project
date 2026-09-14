<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessUnitController;
use App\Http\Controllers\Api\CagesTrackRecordController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CorporateController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EffluentPlantRecordController;
use App\Http\Controllers\Api\GradingRecordController;
use App\Http\Controllers\Api\CpoDispatchRecordController;
use App\Http\Controllers\Api\KernelDispatchRecordController;
use App\Http\Controllers\Api\MachineryController;
use App\Http\Controllers\Api\MachineryGroupController;
use App\Http\Controllers\Api\ManagementReportController;
use App\Http\Controllers\Api\MillSettingController;
use App\Http\Controllers\Api\PressingRecordController;
use App\Http\Controllers\Api\DepricarpingRecordController;
use App\Http\Controllers\Api\KernelPlantRecordController;
use App\Http\Controllers\Api\ProcessWaterRecordController;
use App\Http\Controllers\Api\ProductionLineController;
use App\Http\Controllers\Api\RecordVerificationController;
use App\Http\Controllers\Api\SolidWasteDisposalRecordController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\StorageTankRecordController;
use App\Http\Controllers\Api\BoilerRoomRecordController;
use App\Http\Controllers\Api\ClarificationRecordController;
use App\Http\Controllers\Api\ProcessQualityControlRecordController;
use App\Http\Controllers\Api\SterilizerRecordController;
use App\Http\Controllers\Api\EngineRoomRecordController;
use App\Http\Controllers\Api\ThreshingRecordController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WeighbridgeRecordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (REST — consumed by the mobile app via Sanctum tokens)
|--------------------------------------------------------------------------
| Stateless token auth for mobile (Sanctum personal access tokens). Screen
| endpoints are registered by impl-2-screen between the markers below, per
| screen tech-spec's `api_contracts[].endpoints`.
*/

Route::get('/health', fn () => response()->json(['status' => 'ok']));

// === ASDLC_ROUTES_START ===
// screen-001--login-web / usecase-001--login-web
// AND screen-002--login-mobile / usecase-002--login-mobile (same route,
// see App\Http\Controllers\Api\AuthController::login() for how the two
// branches are selected).
//
// `web` middleware is applied explicitly here (rather than relying solely on
// Sanctum's EnsureFrontendRequestsAreStateful, which only starts a session
// when the request's Referer/Origin matches a stateful domain) because the
// web branch's business_logic unconditionally requires establishing a
// Laravel session (step 6: "Buat Laravel session untuk user, set session
// cookie").
//
// This route is CSRF-exempt (see bootstrap/app.php's
// validateCsrfTokens(except: ['api/login'])): the web branch is never
// actually called over HTTP by the Livewire login (App\Livewire\Auth\
// LoginForm calls AuthService::login() in-process, not this route), so no
// CSRF-protected browser flow depends on this endpoint's CSRF check; and the
// mobile branch (screen-002) is a token-issuing endpoint called by a native
// client that has no session/CSRF token yet on its very first request, so
// enforcing CSRF here would make mobile login impossible. `web` middleware
// is kept (not swapped for a lighter middleware set) purely so the session
// cookie still gets set for the web branch — the mobile client simply
// ignores that cookie.
Route::post('/login', [AuthController::class, 'login'])->middleware('web');

// Shared by screen-001--login-web and screen-002--login-mobile: populates
// the "Business Area" picker both login forms render. Public — must be
// usable before the user has any session/token, same reasoning as
// POST /api/login above. Was a known gap (documented in screen-002's
// implementation known_issues) until now: neither screen's tech-spec
// defined this endpoint even though both forms depended on it client-side.
Route::get('/business-units', [BusinessUnitController::class, 'index']);

// screen-003--ganti-password-web / usecase-003--ganti-password-web
// AND screen-004--ganti-password-mobile / usecase-004--ganti-password-mobile
// (same route, same controller action — self-service password change,
// requester IS the target user, via AuthController::changePassword() /
// AuthService::changePassword()).
//
// MERGE DECISION (screen-004 impl, not explicitly written in either
// screen-003's or screen-004's tech-spec): rather than adding a second
// route/controller-method for the mobile screen, the EXISTING route is
// extended to serve both entry points:
//   - guard: 'auth:web,sanctum' — Laravel's Authenticate middleware tries
//     each guard left-to-right and authenticates via whichever succeeds
//     (Auth::shouldUse() then pins that guard as the request's default),
//     so the web session guard (screen-003, Livewire browser session) and
//     the Sanctum token guard (screen-004, mobile app) both work through
//     this single route. AuthController::changePassword() below resolves
//     $request->user() with NO explicit guard argument so it picks up
//     whichever guard the middleware selected.
//   - roles: 'admin,supervisor,mill_management,operator' — the UNION of
//     screen-003's actor_permissions (admin, supervisor, mill_management)
//     and screen-004's (operator, supervisor). Business-sensible because
//     this is a *self*-service action (no Checked-By/Acknowledged-By-style
//     cross-user restriction applies to changing one's own password), so
//     any authenticated role is allowed.
// EnsureRole (App\Http\Middleware\EnsureRole) already accepts an arbitrary
// comma-separated role list via variadic ...$roles — no change needed to
// support the 4th role.
Route::middleware(['auth:web,sanctum', 'role:admin,supervisor,mill_management,operator'])
    ->patch('/me/password', [AuthController::class, 'changePassword']);

// screen-016--data-browser-weighbridge-web
// Session-guarded ('auth:web' — this screen is web-only, no mobile
// counterpart exists for it yet) + role-guarded (supervisor,
// mill_management, admin per screen_tech_spec.actor_permissions).
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/weighbridge-records', [WeighbridgeRecordController::class, 'index']);
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/weighbridge-records/export', [WeighbridgeRecordController::class, 'export']);

// screen-019--detail-weighbridge-web
// IMPORTANT — registered AFTER /weighbridge-records/export above, so the
// literal "export" segment is matched first; otherwise Laravel would match
// it against {id} here instead.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/weighbridge-records/{id}', [WeighbridgeRecordController::class, 'show']);

// screen-022--form-weighbridge-web
// POST has no {id} segment to collide with; PATCH shares the exact {id}
// path GET/show already uses above but a different HTTP method never
// collides in Laravel routing.
//
// TEMPORARY (2026-08-20, syncService.ts): dual-guarded 'auth:web,sanctum'
// + 'operator' added to the role list — this pair is now also called from
// the mobile app's manual "Sinkronisasi" button (Station List, screen-006)
// to push locally-entered Weighbridge records so they become visible on
// web. See syncService.ts's own doc comment for the full mechanism
// (station_id is never sent by either caller; the server always resolves
// it from business_unit_id, so mobile's synthetic local station ids never
// need to reconcile with real Station rows).
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->post('/weighbridge-records', [WeighbridgeRecordController::class, 'store']);
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->patch('/weighbridge-records/{id}', [WeighbridgeRecordController::class, 'update']);

// screen-017--data-browser-grading-web
// Session-guarded ('auth:web' — this screen is web-only, no mobile
// counterpart exists for it yet) + role-guarded (supervisor,
// mill_management, admin per screen_tech_spec.actor_permissions). Mirrors
// screen-016's registration pattern exactly.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/grading-records', [GradingRecordController::class, 'index']);
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/grading-records/export', [GradingRecordController::class, 'export']);

// screen-018--data-browser-cages-track-web
// Session-guarded ('auth:web' — this screen is web-only, no mobile
// counterpart exists for it yet) + role-guarded (supervisor,
// mill_management, admin per screen_tech_spec.actor_permissions). Mirrors
// screen-016/screen-017's registration pattern exactly.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/cages-track-records', [CagesTrackRecordController::class, 'index']);
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/cages-track-records/export', [CagesTrackRecordController::class, 'export']);

// screen-021--detail-cages-track-web
// IMPORTANT — registered AFTER /cages-track-records/export above, so the
// literal "export" segment is matched first; otherwise Laravel would match
// it against {id} here instead. Mirrors screen-019/020's registration.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/cages-track-records/{id}', [CagesTrackRecordController::class, 'show']);

// screen-024--form-cages-track-web
// POST has no {id} segment to collide with; PATCH shares the exact {id}
// path GET/show already uses above but a different HTTP method never
// collides in Laravel routing. Mirrors screen-022/023's registration
// pattern exactly.
//
// TEMPORARY (2026-08-20, syncService.ts) — same dual-guard + 'operator'
// addition as screen-022's routes above, for the mobile sync button.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->post('/cages-track-records', [CagesTrackRecordController::class, 'store']);
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->patch('/cages-track-records/{id}', [CagesTrackRecordController::class, 'update']);

// screen-020--detail-grading-web
// IMPORTANT — registered AFTER /grading-records/export above, so the
// literal "export" segment is matched first; otherwise Laravel would match
// it against {id} here instead. Mirrors screen-019's registration pattern
// exactly.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/grading-records/{id}', [GradingRecordController::class, 'show']);

// screen-023--form-grading-web
// POST has no {id} segment to collide with; PATCH shares the exact {id}
// path GET/show already uses above but a different HTTP method never
// collides in Laravel routing. Mirrors screen-022's registration pattern
// exactly.
//
// TEMPORARY (2026-08-20, syncService.ts) — same dual-guard + 'operator'
// addition as screen-022's routes above, for the mobile sync button.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->post('/grading-records', [GradingRecordController::class, 'store']);
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->patch('/grading-records/{id}', [GradingRecordController::class, 'update']);

// screen-027--kelola-corporate
// Session-guarded ('auth:web' — this is an admin-only web master-data
// screen, no mobile counterpart) + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/operator
// all have can_access=false for this screen). Grouped (rather than
// repeating the middleware call per route like screen-016/017/018) since
// all four CRUD routes here share the exact same guard — functionally
// identical, just less repetition.
Route::middleware(['auth:web', 'role:admin'])->group(function () {
    Route::get('/corporates', [CorporateController::class, 'index']);
    Route::post('/corporates', [CorporateController::class, 'store']);
    Route::patch('/corporates/{id}', [CorporateController::class, 'update']);
    Route::delete('/corporates/{id}', [CorporateController::class, 'destroy']);
});

// screen-028--kelola-company
// Session-guarded ('auth:web') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/operator
// all have can_access=false for this screen). Mirrors screen-027's
// registration pattern exactly (grouped middleware, one shared guard for
// all routes). GET /corporates/options is declared in THIS group (not
// CorporateController's) even though it queries the Corporate model —
// it's a screen-028-specific dropdown-population endpoint (feeds the
// Company form's Corporate-select), not a general Corporate CRUD
// endpoint, so it lives on CompanyController alongside the rest of this
// screen's endpoints per its tech-spec's api_contracts. No route-order
// conflict with CorporateController's routes above: none of those declare
// a GET /corporates/{id}-shaped route that "options" could collide with.
Route::middleware(['auth:web', 'role:admin'])->group(function () {
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/corporates/options', [CompanyController::class, 'corporateOptions']);
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::patch('/companies/{id}', [CompanyController::class, 'update']);
    Route::delete('/companies/{id}', [CompanyController::class, 'destroy']);
});

// screen-029--kelola-business-unit
// GET /business-units stays PUBLIC/merged — see
// BusinessUnitController::index()'s docblock (pre-existing route,
// registered above near /login, unchanged): it now serves both the
// legacy screen-001/002 login picker AND this screen's paginated/
// filtered list, selected by presence of page/per_page/company_id query
// params. Session-guarded ('auth:web') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/
// operator all have can_access=false for this screen) for the remaining
// 4 routes below — these are the admin-gated create/update/delete/
// companyOptions actions. GET /companies/options is declared in THIS
// group (not CompanyController's) even though it queries the Company
// model — it's a screen-029-specific dropdown-population endpoint (feeds
// the Business Unit form's Company-select), not a general Company CRUD
// endpoint, so it lives on BusinessUnitController alongside the rest of
// this screen's endpoints per its tech-spec's api_contracts, mirroring
// screen-028's GET /corporates/options precedent exactly. No route-order
// conflict with CompanyController's routes above: none of those declare
// a GET /companies/{id}-shaped route that "options" could collide with.
Route::middleware(['auth:web', 'role:admin'])->group(function () {
    Route::get('/companies/options', [BusinessUnitController::class, 'companyOptions']);
    Route::post('/business-units', [BusinessUnitController::class, 'store']);
    Route::patch('/business-units/{id}', [BusinessUnitController::class, 'update']);
    Route::delete('/business-units/{id}', [BusinessUnitController::class, 'destroy']);
});

// screen-036--kelola-production-line (entity-catalog v9, 2026-08-20 —
// inserted between Business Unit and Station in the hierarchy).
// IMPORTANT — route ordering: GET /api/production-lines/current and
// /api/production-lines/current/stations (self-scoped, mobile-facing —
// Station List's new Production Line picker step) MUST be registered
// BEFORE the admin-only /production-lines routes below, mirroring
// screen-034's GET /mill-settings/current ordering requirement exactly —
// otherwise Laravel would match the literal "current" segment against a
// {id}-shaped route instead of reaching these dedicated routes (there is
// no such route on ProductionLineController today, but this ordering is
// kept as a standing guard against ever introducing one above these).
Route::middleware(['auth:web,sanctum', 'role:operator,supervisor,mill_management,admin'])->group(function () {
    Route::get('/production-lines/current', [ProductionLineController::class, 'current']);
    Route::get('/production-lines/current/stations', [ProductionLineController::class, 'currentStations']);
});

// Admin-only CRUD — mirrors MachineryGroupController's registration
// pattern exactly (every action uniformly admin-gated, no public-endpoint
// collision to accommodate). GET /business-units/options-shaped dropdown
// feed is declared as businessUnitOptions() here for this screen's own
// create/edit form.
Route::middleware(['auth:web', 'role:admin'])->group(function () {
    Route::get('/production-lines', [ProductionLineController::class, 'index']);
    Route::get('/production-lines/business-units/options', [ProductionLineController::class, 'businessUnitOptions']);
    Route::post('/production-lines', [ProductionLineController::class, 'store']);
    Route::patch('/production-lines/{id}', [ProductionLineController::class, 'update']);
    Route::delete('/production-lines/{id}', [ProductionLineController::class, 'destroy']);
});

// screen-030--kelola-station
// Unlike BusinessUnitController::index()'s merged public/admin
// GET /business-units above, GET /stations has no pre-existing public
// endpoint to accommodate — every action for this screen is uniformly
// admin-gated ('auth:web' + 'role:admin', per screen_tech_spec.
// actor_permissions — supervisor/mill_management/operator all have
// can_access=false for this screen), so no legacy-branch merge decision
// was needed on StationController::index() (see that method's
// docblock). GET /business-units/options is declared on
// StationController (not BusinessUnitController) even though it queries
// the BusinessUnit model — it's a screen-030-specific dropdown-
// population endpoint (feeds the Station form's Business Unit-select),
// not a general Business Unit CRUD endpoint, so it lives alongside the
// rest of this screen's endpoints per its tech-spec's api_contracts,
// mirroring screen-029's GET /companies/options precedent exactly (grep
// confirmed no route named `business-units/options` existed anywhere in
// this codebase before this screen). No route-order conflict with
// BusinessUnitController's routes above: none of those declare a
// GET /business-units/{id}-shaped route that "options" could collide
// with.
Route::middleware(['auth:web', 'role:admin'])->group(function () {
    Route::get('/stations', [StationController::class, 'index']);
    Route::get('/business-units/options', [StationController::class, 'businessUnitOptions']);
    // /production-lines/options — added 2026-08-20 (entity-catalog v9,
    // production_line_id is now a required FK on stations). Feeds the
    // Station form's new Production Line-select, cascaded from the
    // chosen Business Unit via the required ?business_unit_id= query
    // param — mirrors GET /business-units/options exactly, one level
    // down.
    Route::get('/production-lines/options', [StationController::class, 'productionLineOptions']);
    Route::post('/stations', [StationController::class, 'store']);
    Route::patch('/stations/{id}', [StationController::class, 'update']);
    Route::delete('/stations/{id}', [StationController::class, 'destroy']);
});

// screen-033--kelola-machinery-group
// Session-guarded ('auth:web') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/
// operator all have can_access=false for this screen). Mirrors
// screen-030's registration pattern exactly — every action here is
// uniformly admin-gated, no public-endpoint collision to accommodate.
// GET /stations/options is declared on MachineryGroupController (not
// StationController) even though it queries the Station model — it's a
// screen-033-specific dropdown-population endpoint (feeds the Machinery
// Group form's Station-select, returning {id, name, business_unit_id}
// per row so the FE can copy business_unit_id client-side for display
// before submit — the server independently re-derives it from station_id
// again on write, never trusting client input for that field), not a
// general Station CRUD endpoint — mirrors screen-030's
// GET /business-units/options precedent exactly (grep confirmed no route
// named `stations/options` existed anywhere in this codebase before this
// screen). No route-order conflict with StationController's routes
// above: none of those declare a GET /stations/{id}-shaped route that
// "options" could collide with.
Route::middleware(['auth:web', 'role:admin'])->group(function () {
    Route::get('/machinery-groups', [MachineryGroupController::class, 'index']);
    Route::get('/stations/options', [MachineryGroupController::class, 'stationOptions']);
    Route::post('/machinery-groups', [MachineryGroupController::class, 'store']);
    Route::patch('/machinery-groups/{id}', [MachineryGroupController::class, 'update']);
    Route::delete('/machinery-groups/{id}', [MachineryGroupController::class, 'destroy']);
});

// screen-031--kelola-machinery
// Session-guarded ('auth:web') + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/
// operator all have can_access=false for this screen). Mirrors
// screen-033's registration pattern exactly — every action here is
// uniformly admin-gated, no public-endpoint collision to accommodate.
// GET /machinery-groups/options is declared on MachineryController (not
// MachineryGroupController) even though it queries the MachineryGroup
// model — it's a screen-031-specific dropdown-population endpoint (feeds
// the Machinery form's Machinery Group-select, returning
// {id, group_code, station_id, business_unit_id} per row so the FE can
// copy station_id/business_unit_id client-side for display before submit
// — the server independently re-derives both from machinery_group_id
// again on write, never trusting client input for either), not a general
// MachineryGroup CRUD endpoint — mirrors screen-033's
// GET /stations/options precedent exactly, one level down (grep
// confirmed no route named `machinery-groups/options` existed anywhere
// in this codebase before this screen). No route-order conflict with
// MachineryGroupController's routes above: GET /machinery-groups/options
// is registered before any GET /machinery-groups/{id}-shaped route could
// be declared (none exists on MachineryGroupController), and this
// screen's own GET /machinery/{id} below only matches the `/machinery`
// prefix, not `/machinery-groups`.
Route::middleware(['auth:web', 'role:admin'])->group(function () {
    Route::get('/machinery', [MachineryController::class, 'index']);
    Route::get('/machinery-groups/options', [MachineryController::class, 'groupOptions']);
    Route::get('/machinery/{id}', [MachineryController::class, 'show']);
    Route::post('/machinery', [MachineryController::class, 'store']);
    Route::patch('/machinery/{id}', [MachineryController::class, 'update']);
    Route::delete('/machinery/{id}', [MachineryController::class, 'destroy']);
});

// screen-034--mills-setting
// Admin + Mill Management (per screen_tech_spec.actor_permissions) —
// per-resource ownership scoping (Mill Management restricted to their own
// business_unit_id) is enforced INSIDE MillSettingService::checkAccess(),
// not at the route/middleware layer, since 'role:...' can only gate by
// role, not by which :business_unit_id was requested.
//
// IMPORTANT — route ordering: GET /api/mill-settings/current and
// /api/mill-settings/current/stations (self-scoped, mobile-facing —
// screen-005--home, screen-012--form-cages-track, screen-006's mobile
// consumers) MUST be registered BEFORE the :businessUnitId routes below,
// or Laravel would match the literal "current" segment against the
// {businessUnitId} parameter instead of reaching these dedicated routes.
Route::middleware(['auth:web,sanctum', 'role:operator,supervisor,mill_management,admin'])->group(function () {
    Route::get('/mill-settings/current', [MillSettingController::class, 'current']);
    Route::get('/mill-settings/current/stations', [MillSettingController::class, 'currentStations']);
});

Route::middleware(['auth:web', 'role:admin,mill_management'])->group(function () {
    Route::get('/mill-settings/{businessUnitId}', [MillSettingController::class, 'show']);
    Route::patch('/mill-settings/{businessUnitId}', [MillSettingController::class, 'update']);
    Route::get('/mill-settings/{businessUnitId}/stations', [MillSettingController::class, 'stations']);
    Route::patch('/mill-settings/{businessUnitId}/stations/{stationId}', [MillSettingController::class, 'setStationIcon']);
});
// screen-025--dashboard-web
// Dual-guarded ('auth:web,sanctum' — this screen is web-only per PRD
// ("Dashboard & Reporting mobile ditunda ke fase berikutnya"), but the
// dual guard is kept for consistency with every other endpoint in this
// file rather than narrowing to 'auth:web' alone) + role-guarded
// (supervisor, mill_management, admin per screen_tech_spec.actor_permissions).
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin'])
    ->get('/dashboard/summary', [DashboardController::class, 'summary']);

// screen-026--laporan-manajemen
// Dual-guarded ('auth:web,sanctum', same reasoning as screen-025 above) +
// role-guarded to Mill Management ONLY (per screen_tech_spec.actor_permissions
// — narrower than Dashboard Web, which is also open to Supervisor/Admin).
// business_unit_id is never a request param here — always resolved from
// the acting user (ManagementReportController), since Mill Management is
// scoped to their own mill only.
Route::middleware(['auth:web,sanctum', 'role:mill_management'])->group(function () {
    Route::get('/reports/management-summary', [ManagementReportController::class, 'summary']);
    Route::get('/reports/management-summary/export', [ManagementReportController::class, 'export']);
});

// screen-032--kelola-user-role
// Session-guarded ('auth:web' — this screen is web-only, no mobile
// counterpart) + role-guarded (admin only, per
// screen_tech_spec.actor_permissions — supervisor/mill_management/
// operator all have can_access=false for this screen).
Route::middleware(['auth:web', 'role:admin'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::patch('/users/{id}', [UserController::class, 'update']);
    Route::patch('/users/{id}/status', [UserController::class, 'setStatus']);
});

// screen-049--data-browser-threshing-web
// Session-guarded ('auth:web' — this screen is web-only, no mobile
// counterpart exists for it yet) + role-guarded (supervisor,
// mill_management, admin per screen_tech_spec.actor_permissions). Mirrors
// screen-018--data-browser-cages-track-web's registration pattern exactly.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/threshing-records', [ThreshingRecordController::class, 'index']);
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/threshing-records/export', [ThreshingRecordController::class, 'export']);

// screen-053--detail-threshing-web
// IMPORTANT — registered AFTER /threshing-records/export above, so the
// literal "export" segment is matched first; otherwise Laravel would match
// it against {id} here instead. Mirrors screen-021's registration.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/threshing-records/{id}', [ThreshingRecordController::class, 'show']);

// screen-057--form-threshing-web
// Dual-guarded ('auth:web,sanctum' + 'operator' role) mirrors
// screen-024--form-cages-track-web's routes. UPDATE (2026-08-24): the
// mobile "Sinkronisasi" button (Station List, screen-006) now calls this
// endpoint — see mobile/src/services/syncService.ts's syncThreshingRecords().
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->post('/threshing-records', [ThreshingRecordController::class, 'store']);
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->patch('/threshing-records/{id}', [ThreshingRecordController::class, 'update']);

// screen-050--data-browser-pressing-web
// Session-guarded ('auth:web' — this screen is web-only, no mobile
// counterpart exists for it yet) + role-guarded (supervisor,
// mill_management, admin per screen_tech_spec.actor_permissions). Mirrors
// screen-049--data-browser-threshing-web's registration pattern exactly.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/pressing-records', [PressingRecordController::class, 'index']);
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/pressing-records/export', [PressingRecordController::class, 'export']);

// screen-054--detail-pressing-web
// IMPORTANT — registered AFTER /pressing-records/export above, so the
// literal "export" segment is matched first; otherwise Laravel would match
// it against {id} here instead. Mirrors screen-053's registration.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/pressing-records/{id}', [PressingRecordController::class, 'show']);

// screen-058--form-pressing-web
// Dual-guarded ('auth:web,sanctum' + 'operator' role) mirrors
// screen-057--form-threshing-web's routes. UPDATE (2026-08-24): the mobile
// "Sinkronisasi" button (Station List, screen-006) now calls this
// endpoint — see mobile/src/services/syncService.ts's syncPressingRecords().
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->post('/pressing-records', [PressingRecordController::class, 'store']);
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->patch('/pressing-records/{id}', [PressingRecordController::class, 'update']);

// screen-051--data-browser-depricarping-web
// Session-guarded ('auth:web' — this screen is web-only, no mobile
// counterpart exists for it yet) + role-guarded (supervisor,
// mill_management, admin per screen_tech_spec.actor_permissions). Mirrors
// screen-050--data-browser-pressing-web's registration pattern exactly.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/depricarping-records', [DepricarpingRecordController::class, 'index']);
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/depricarping-records/export', [DepricarpingRecordController::class, 'export']);

// screen-055--detail-depricarping-web
// IMPORTANT — registered AFTER /depricarping-records/export above, so the
// literal "export" segment is matched first; otherwise Laravel would match
// it against {id} here instead. Mirrors screen-054's registration.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/depricarping-records/{id}', [DepricarpingRecordController::class, 'show']);

// screen-059--form-depricarping-web
// Dual-guarded ('auth:web,sanctum' + 'operator' role) mirrors
// screen-058--form-pressing-web's routes. UPDATE (2026-08-24): the mobile
// "Sinkronisasi" button (Station List, screen-006) now calls this
// endpoint — see mobile/src/services/syncService.ts's syncDepricarpingRecords().
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->post('/depricarping-records', [DepricarpingRecordController::class, 'store']);
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->patch('/depricarping-records/{id}', [DepricarpingRecordController::class, 'update']);

// screen-052--data-browser-kernel-plant-web
// Session-guarded ('auth:web' — this screen is web-only, no mobile
// counterpart exists for it yet) + role-guarded (supervisor,
// mill_management, admin per screen_tech_spec.actor_permissions). Mirrors
// screen-051--data-browser-depricarping-web's registration pattern exactly.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/kernel-plant-records', [KernelPlantRecordController::class, 'index']);
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/kernel-plant-records/export', [KernelPlantRecordController::class, 'export']);

// screen-056--detail-kernel-plant-web
// IMPORTANT — registered AFTER /kernel-plant-records/export above, so the
// literal "export" segment is matched first; otherwise Laravel would match
// it against {id} here instead. Mirrors screen-055's registration.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/kernel-plant-records/{id}', [KernelPlantRecordController::class, 'show']);

// screen-060--form-kernel-plant-web
// Dual-guarded ('auth:web,sanctum' + 'operator' role) mirrors
// screen-059--form-depricarping-web's routes. UPDATE (2026-08-24): the
// mobile "Sinkronisasi" button (Station List, screen-006) now calls this
// endpoint — see mobile/src/services/syncService.ts's syncKernelPlantRecords().
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->post('/kernel-plant-records', [KernelPlantRecordController::class, 'store']);
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->patch('/kernel-plant-records/{id}', [KernelPlantRecordController::class, 'update']);

// screen-091--data-browser-solid-waste-disposal-web
// Mirrors screen-018--data-browser-cages-track-web's registration pattern
// exactly — event-log station, no fixed grid.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/solid-waste-disposal-records', [SolidWasteDisposalRecordController::class, 'index']);
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/solid-waste-disposal-records/export', [SolidWasteDisposalRecordController::class, 'export']);

// screen-101--detail-solid-waste-disposal-web
// IMPORTANT — registered AFTER /solid-waste-disposal-records/export above,
// so the literal "export" segment is matched first.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/solid-waste-disposal-records/{id}', [SolidWasteDisposalRecordController::class, 'show']);

// screen-111--form-solid-waste-disposal-web
// Dual-guarded ('auth:web,sanctum' + 'operator' role) for the mobile sync
// button, mirrors screen-024/057/058/059/060's routes.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->post('/solid-waste-disposal-records', [SolidWasteDisposalRecordController::class, 'store']);
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->patch('/solid-waste-disposal-records/{id}', [SolidWasteDisposalRecordController::class, 'update']);

// screen-092--data-browser-process-water-web
// Session-guarded ('auth:web' — this screen is web-only, no mobile
// counterpart exists for it yet) + role-guarded (supervisor,
// mill_management, admin per screen_tech_spec.actor_permissions). Mirrors
// screen-049--data-browser-threshing-web's registration pattern exactly —
// Process Water follows the same hourly-grid pattern as Threshing (no
// operational-target reference table, unlike Threshing/Pressing/
// Depricarping/Kernel Plant).
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/process-water-records', [ProcessWaterRecordController::class, 'index']);
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/process-water-records/export', [ProcessWaterRecordController::class, 'export']);

// screen-102--detail-process-water-web
// IMPORTANT — registered AFTER /process-water-records/export above, so the
// literal "export" segment is matched first; otherwise Laravel would match
// it against {id} here instead. Mirrors screen-053's registration.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/process-water-records/{id}', [ProcessWaterRecordController::class, 'show']);

// screen-112--form-process-water-web
// Dual-guarded ('auth:web,sanctum' + 'operator' role) mirrors
// screen-057--form-threshing-web's routes, for a future mobile sync button.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->post('/process-water-records', [ProcessWaterRecordController::class, 'store']);
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->patch('/process-water-records/{id}', [ProcessWaterRecordController::class, 'update']);

// screen-093--data-browser-kernel-dispatch-web
// Session-guarded ('auth:web') + role-guarded (supervisor, mill_management,
// admin per screen_tech_spec.actor_permissions). Mirrors
// screen-091--data-browser-solid-waste-disposal-web's registration pattern
// exactly — Kernel Dispatch follows the same event-log pattern as Solid
// Waste Disposal (unbounded detail rows, no fixed grid).
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/kernel-dispatch-records', [KernelDispatchRecordController::class, 'index']);
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/kernel-dispatch-records/export', [KernelDispatchRecordController::class, 'export']);

// screen-103--detail-kernel-dispatch-web
// IMPORTANT — registered AFTER /kernel-dispatch-records/export above, so
// the literal "export" segment is matched first; otherwise Laravel would
// match it against {id} here instead. Mirrors screen-101's registration.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/kernel-dispatch-records/{id}', [KernelDispatchRecordController::class, 'show']);

// screen-113--form-kernel-dispatch-web
// Dual-guarded ('auth:web,sanctum' + 'operator' role) for a future mobile
// sync button, mirrors screen-111's routes.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->post('/kernel-dispatch-records', [KernelDispatchRecordController::class, 'store']);
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->patch('/kernel-dispatch-records/{id}', [KernelDispatchRecordController::class, 'update']);

// screen-094--data-browser-cpo-dispatch-web
// Session-guarded ('auth:web') + role-guarded (supervisor, mill_management,
// admin per screen_tech_spec.actor_permissions). Mirrors
// screen-093--data-browser-kernel-dispatch-web's registration pattern
// exactly — CPO Dispatch follows the same event-log pattern as Kernel
// Dispatch (unbounded detail rows, no fixed grid).
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/cpo-dispatch-records', [CpoDispatchRecordController::class, 'index']);
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/cpo-dispatch-records/export', [CpoDispatchRecordController::class, 'export']);

// screen-104--detail-cpo-dispatch-web
// IMPORTANT — registered AFTER /cpo-dispatch-records/export above, so
// the literal "export" segment is matched first; otherwise Laravel would
// match it against {id} here instead. Mirrors screen-103's registration.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/cpo-dispatch-records/{id}', [CpoDispatchRecordController::class, 'show']);

// screen-114--form-cpo-dispatch-web
// Dual-guarded ('auth:web,sanctum' + 'operator' role) for a future mobile
// sync button, mirrors screen-113's routes.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->post('/cpo-dispatch-records', [CpoDispatchRecordController::class, 'store']);
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->patch('/cpo-dispatch-records/{id}', [CpoDispatchRecordController::class, 'update']);

// screen-095--data-browser-effluent-plant-web
// Session-guarded ('auth:web') + role-guarded (supervisor, mill_management,
// admin per screen_tech_spec.actor_permissions). Mirrors
// screen-092--data-browser-process-water-web's registration pattern exactly
// — Effluent Plant follows the same hourly-grid pattern as Process Water (no
// operational-target reference table).
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/effluent-plant-records', [EffluentPlantRecordController::class, 'index']);
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/effluent-plant-records/export', [EffluentPlantRecordController::class, 'export']);

// screen-105--detail-effluent-plant-web
// IMPORTANT — registered AFTER /effluent-plant-records/export above, so the
// literal "export" segment is matched first; otherwise Laravel would match
// it against {id} here instead. Mirrors screen-102's registration.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/effluent-plant-records/{id}', [EffluentPlantRecordController::class, 'show']);

// screen-115--form-effluent-plant-web
// Dual-guarded ('auth:web,sanctum' + 'operator' role) mirrors
// screen-112--form-process-water-web's routes, for a future mobile sync button.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->post('/effluent-plant-records', [EffluentPlantRecordController::class, 'store']);
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->patch('/effluent-plant-records/{id}', [EffluentPlantRecordController::class, 'update']);

// screen-096--data-browser-storage-tank-web
// Session-guarded ('auth:web') + role-guarded (supervisor, mill_management,
// admin per screen_tech_spec.actor_permissions). Mirrors
// screen-095--data-browser-effluent-plant-web's registration pattern exactly
// — Storage Tank follows the same hourly-grid pattern as Effluent Plant (no
// operational-target reference table).
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/storage-tank-records', [StorageTankRecordController::class, 'index']);
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/storage-tank-records/export', [StorageTankRecordController::class, 'export']);

// screen-106--detail-storage-tank-web
// IMPORTANT — registered AFTER /storage-tank-records/export above, so the
// literal "export" segment is matched first; otherwise Laravel would match
// it against {id} here instead. Mirrors screen-105's registration.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/storage-tank-records/{id}', [StorageTankRecordController::class, 'show']);

// screen-116--form-storage-tank-web
// Dual-guarded ('auth:web,sanctum' + 'operator' role) mirrors
// screen-115--form-effluent-plant-web's routes, for a future mobile sync button.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->post('/storage-tank-records', [StorageTankRecordController::class, 'store']);
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->patch('/storage-tank-records/{id}', [StorageTankRecordController::class, 'update']);

// screen-097--data-browser-engine-room-web
// Session-guarded ('auth:web') + role-guarded (supervisor, mill_management,
// admin per screen_tech_spec.actor_permissions). Mirrors
// screen-096--data-browser-storage-tank-web's registration pattern exactly
// — Engine Room follows the same hourly-grid pattern as Storage Tank (no
// operational-target reference table).
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/engine-room-records', [EngineRoomRecordController::class, 'index']);
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/engine-room-records/export', [EngineRoomRecordController::class, 'export']);

// screen-107--detail-engine-room-web
// IMPORTANT — registered AFTER /engine-room-records/export above, so the
// literal "export" segment is matched first; otherwise Laravel would match
// it against {id} here instead. Mirrors screen-106's registration.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/engine-room-records/{id}', [EngineRoomRecordController::class, 'show']);

// screen-117--form-engine-room-web
// Dual-guarded ('auth:web,sanctum' + 'operator' role) mirrors
// screen-116--form-storage-tank-web's routes, for a future mobile sync button.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->post('/engine-room-records', [EngineRoomRecordController::class, 'store']);
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->patch('/engine-room-records/{id}', [EngineRoomRecordController::class, 'update']);

// screen-098--data-browser-boiler-room-web
// Session-guarded ('auth:web') + role-guarded (supervisor, mill_management,
// admin per screen_tech_spec.actor_permissions). Mirrors
// screen-097--data-browser-engine-room-web's registration pattern exactly
// — Boiler Room follows the same hourly-grid pattern as Engine Room (no
// operational-target reference table).
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/boiler-room-records', [BoilerRoomRecordController::class, 'index']);
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/boiler-room-records/export', [BoilerRoomRecordController::class, 'export']);

// screen-108--detail-boiler-room-web
// IMPORTANT — registered AFTER /boiler-room-records/export above, so the
// literal "export" segment is matched first; otherwise Laravel would match
// it against {id} here instead. Mirrors screen-107's registration.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/boiler-room-records/{id}', [BoilerRoomRecordController::class, 'show']);

// screen-118--form-boiler-room-web
// Dual-guarded ('auth:web,sanctum' + 'operator' role) mirrors
// screen-117--form-engine-room-web's routes, for a future mobile sync button.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->post('/boiler-room-records', [BoilerRoomRecordController::class, 'store']);
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->patch('/boiler-room-records/{id}', [BoilerRoomRecordController::class, 'update']);

// screen-099--data-browser-clarification-web
// Session-guarded ('auth:web') + role-guarded (supervisor, mill_management,
// admin per screen_tech_spec.actor_permissions). Mirrors
// screen-098--data-browser-boiler-room-web's registration pattern exactly
// — Clarification follows the same hourly-grid pattern as Boiler Room (no
// operational-target reference table).
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/clarification-records', [ClarificationRecordController::class, 'index']);
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/clarification-records/export', [ClarificationRecordController::class, 'export']);

// screen-109--detail-clarification-web
// IMPORTANT — registered AFTER /clarification-records/export above, so the
// literal "export" segment is matched first; otherwise Laravel would match
// it against {id} here instead. Mirrors screen-108's registration.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/clarification-records/{id}', [ClarificationRecordController::class, 'show']);

// screen-119--form-clarification-web
// Dual-guarded ('auth:web,sanctum' + 'operator' role) mirrors
// screen-118--form-boiler-room-web's routes, for a future mobile sync button.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->post('/clarification-records', [ClarificationRecordController::class, 'store']);
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->patch('/clarification-records/{id}', [ClarificationRecordController::class, 'update']);

// screen-100--data-browser-process-quality-control-web
// Session-guarded ('auth:web') + role-guarded (supervisor, mill_management,
// admin per screen_tech_spec.actor_permissions). Mirrors
// screen-099--data-browser-clarification-web's registration pattern exactly
// — Process Quality Control follows the same hourly-grid pattern as
// Clarification (no operational-target reference table).
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/process-quality-control-records', [ProcessQualityControlRecordController::class, 'index']);
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/process-quality-control-records/export', [ProcessQualityControlRecordController::class, 'export']);

// screen-110--detail-process-quality-control-web
// IMPORTANT — registered AFTER /process-quality-control-records/export
// above, so the literal "export" segment is matched first; otherwise
// Laravel would match it against {id} here instead. Mirrors screen-109's
// registration.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/process-quality-control-records/{id}', [ProcessQualityControlRecordController::class, 'show']);

// screen-120--form-process-quality-control-web
// Dual-guarded ('auth:web,sanctum' + 'operator' role) mirrors
// screen-119--form-clarification-web's routes, for a future mobile sync button.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->post('/process-quality-control-records', [ProcessQualityControlRecordController::class, 'store']);
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->patch('/process-quality-control-records/{id}', [ProcessQualityControlRecordController::class, 'update']);

// screen-124--data-browser-sterilizer-web
// Session-guarded ('auth:web') + role-guarded (supervisor, mill_management,
// admin per screen_tech_spec.actor_permissions). Mirrors
// screen-094--data-browser-cpo-dispatch-web's registration pattern exactly
// — Sterilizer follows the same event-log pattern as CPO Dispatch
// (unbounded detail rows, no fixed grid). This is the FINAL station of
// this project — after this, all 18 canonical stations have a full
// backend + mobile implementation.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/sterilizer-records', [SterilizerRecordController::class, 'index']);
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/sterilizer-records/export', [SterilizerRecordController::class, 'export']);

// screen-125--detail-sterilizer-web
// IMPORTANT — registered AFTER /sterilizer-records/export above, so the
// literal "export" segment is matched first; otherwise Laravel would match
// it against {id} here instead. Mirrors screen-104's registration.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/sterilizer-records/{id}', [SterilizerRecordController::class, 'show']);

// screen-126--form-sterilizer-web
// Dual-guarded ('auth:web,sanctum' + 'operator' role) mirrors
// screen-114--form-cpo-dispatch-web's routes, for a future mobile sync button.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->post('/sterilizer-records', [SterilizerRecordController::class, 'store']);
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])
    ->patch('/sterilizer-records/{id}', [SterilizerRecordController::class, 'update']);

// Direct approve/un-approve action shared by every station's Detail screen
// (web) and Data Preview screen (mobile) — 2026-09-14 product decision.
// ONE generic route for all 18 stations; {stationType} is resolved through
// RecordVerificationService's explicit whitelist, never concatenated into a
// class name. 'operator' is deliberately absent from the role list: an
// Operator never verifies anything, so the guard rejects them before the
// service's own role rule is even reached.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin'])
    ->patch('/records/{stationType}/{id}/verification', [RecordVerificationController::class, 'update']);

// === ASDLC_ROUTES_END ===
