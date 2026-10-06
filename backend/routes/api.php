<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoilerRoomRecordController;
use App\Http\Controllers\Api\BoilerRoomReportController;
use App\Http\Controllers\Api\BusinessUnitController;
use App\Http\Controllers\Api\CagesTrackRecordController;
use App\Http\Controllers\Api\CagesTrackReportController;
use App\Http\Controllers\Api\ClarificationRecordController;
use App\Http\Controllers\Api\ClarificationReportController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CorporateController;
use App\Http\Controllers\Api\CpoDispatchRecordController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepricarpingRecordController;
use App\Http\Controllers\Api\EffluentPlantRecordController;
use App\Http\Controllers\Api\EngineRoomRecordController;
use App\Http\Controllers\Api\GradingParameterController;
use App\Http\Controllers\Api\GradingRecordController;
use App\Http\Controllers\Api\GradingReportController;
use App\Http\Controllers\Api\DepricarpingReportController;
use App\Http\Controllers\Api\PressingReportController;
use App\Http\Controllers\Api\ThreshingReportController;
use App\Http\Controllers\Api\KernelDispatchRecordController;
use App\Http\Controllers\Api\KernelPlantRecordController;
use App\Http\Controllers\Api\MachineryController;
use App\Http\Controllers\Api\MachineryGroupController;
use App\Http\Controllers\Api\ManagementReportController;
use App\Http\Controllers\Api\MillSettingController;
use App\Http\Controllers\Api\PeriodController;
use App\Http\Controllers\Api\PressingRecordController;
use App\Http\Controllers\Api\ProcessQualityControlRecordController;
use App\Http\Controllers\Api\ProcessWaterRecordController;
use App\Http\Controllers\Api\ProductionLineController;
use App\Http\Controllers\Api\RecordVerificationController;
use App\Http\Controllers\Api\RecordVerificationStatusController;
use App\Http\Controllers\Api\SolidWasteDisposalRecordController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\StationReportController;
use App\Http\Controllers\Api\SterilizerRecordController;
use App\Http\Controllers\Api\SterilizerReportController;
use App\Http\Controllers\Api\StorageTankRecordController;
use App\Http\Controllers\Api\StorageTankReportController;
use App\Http\Controllers\Api\ThreshingRecordController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WeighbridgeRecordController;
use App\Http\Controllers\Api\WeighbridgeReportController;
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
// EXPORT ROUTES — the 'web' middleware group is stacked on top of 'api'
// (same reason as POST /api/login above): the export links are plain <a
// href> navigations from a Livewire page, and the 'api' group only starts a
// session when Sanctum's EnsureFrontendRequestsAreStateful recognises the
// Referer host in config('sanctum.stateful'). Any host not listed there —
// localhost:8000, a LAN IP, the production domain — got 401 Unauthenticated
// on every export while the page itself rendered fine (reproduced
// 2026-09-22). Stacking 'web' starts the session unconditionally, so the
// export no longer depends on a Referer header or on the deployment host
// being listed. Safe for GET: VerifyCsrfToken only checks mutating methods.
Route::middleware(['web', 'auth:web', 'role:supervisor,mill_management,admin'])
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
Route::middleware(['web', 'auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/grading-records/export', [GradingRecordController::class, 'export']);

// screen-018--data-browser-cages-track-web
// Session-guarded ('auth:web' — this screen is web-only, no mobile
// counterpart exists for it yet) + role-guarded (supervisor,
// mill_management, admin per screen_tech_spec.actor_permissions). Mirrors
// screen-016/screen-017's registration pattern exactly.
Route::middleware(['auth:web', 'role:supervisor,mill_management,admin'])
    ->get('/cages-track-records', [CagesTrackRecordController::class, 'index']);
Route::middleware(['web', 'auth:web', 'role:supervisor,mill_management,admin'])
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
    // /production-lines/options-for-report — added 2026-09-28. SAME ROUTE-
    // ORDERING CONSTRAINT as /current above, and the reason it is declared
    // HERE rather than next to the admin CRUD block below: "options-for-
    // report" is a literal segment that a /production-lines/{id}-shaped
    // route would swallow, so it must be registered before the admin group.
    //
    // It sits in THIS group (not the admin one) because its whole purpose is
    // to be reachable by a mobile Sanctum token: the five mobile report
    // screens require a Production Line before showing any number, and the
    // only list endpoint they had (/current) is SELF-SCOPED, so Admin — who
    // is deliberately not mill-bound — was left with no way to pick a line
    // at all. The pre-existing GET /production-lines/options could not be
    // reused: it is 'auth:web' + 'role:admin' (session guard), unreachable
    // with a mobile token, and admin-only.
    //
    // Widening the audience does NOT widen the data: scope is resolved by
    // ScopesToActorMill::resolveReadMillId() inside the service, which
    // DISCARDS a mill-bound actor's `business_unit_id` query param, 422s an
    // actor whose account has no mill, and only lets Admin choose.
    Route::get('/production-lines/options-for-report', [ProductionLineController::class, 'optionsForReport']);
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
//
// The EXPORT route additionally carries the 'web' group (2026-10-03), like
// the 18 Data Browser export routes: "Ekspor CSV" is a plain link, so the
// browser navigates to it with only the session cookie. Without 'web' the
// session is never started on this api route and auth:web only succeeds
// when the page's origin happens to be in SANCTUM_STATEFUL_DOMAINS — on any
// other port/host the download was a 401 JSON body instead of a file.
Route::middleware(['auth:web,sanctum', 'role:mill_management'])
    ->get('/reports/management-summary', [ManagementReportController::class, 'summary']);
Route::middleware(['web', 'auth:web,sanctum', 'role:mill_management'])
    ->get('/reports/management-summary/export', [ManagementReportController::class, 'export']);

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
Route::middleware(['web', 'auth:web', 'role:supervisor,mill_management,admin'])
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
Route::middleware(['web', 'auth:web', 'role:supervisor,mill_management,admin'])
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
Route::middleware(['web', 'auth:web', 'role:supervisor,mill_management,admin'])
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
Route::middleware(['web', 'auth:web', 'role:supervisor,mill_management,admin'])
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
Route::middleware(['web', 'auth:web', 'role:supervisor,mill_management,admin'])
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
Route::middleware(['web', 'auth:web', 'role:supervisor,mill_management,admin'])
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
Route::middleware(['web', 'auth:web', 'role:supervisor,mill_management,admin'])
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
Route::middleware(['web', 'auth:web', 'role:supervisor,mill_management,admin'])
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
Route::middleware(['web', 'auth:web', 'role:supervisor,mill_management,admin'])
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
Route::middleware(['web', 'auth:web', 'role:supervisor,mill_management,admin'])
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
Route::middleware(['web', 'auth:web', 'role:supervisor,mill_management,admin'])
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
Route::middleware(['web', 'auth:web', 'role:supervisor,mill_management,admin'])
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
Route::middleware(['web', 'auth:web', 'role:supervisor,mill_management,admin'])
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
Route::middleware(['web', 'auth:web', 'role:supervisor,mill_management,admin'])
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
Route::middleware(['web', 'auth:web', 'role:supervisor,mill_management,admin'])
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

// screen-128--kelola-periode-pelaporan (usecase-128 CRUD + usecase-140
// tutup/buka kembali + usecase-144 buka periode draft). Admin-only
// across the board, per
// screen_tech_spec.actor_permissions — supervisor / mill_management /
// operator all have can_access=false, closure actions included (Mill
// Management consumes the period reports but may not close the books).
//
// IMPORTANT — route ordering: GET /periods/business-units/options MUST be
// registered BEFORE the parameterised /periods/{id} routes below, or
// Laravel matches the literal "business-units" segment against {id}.
// Same requirement as /production-lines/business-units/options and
// /mill-settings/current: the literal /periods/business-units/options must
// stay registered BEFORE anything that could match "business-units" as a
// {id} segment.
//
// TWO PREFIXES, ONE CONTROLLER (2026-09-26). /periods/{id} takes a PERIOD
// id; /period-stations/{id}/... takes a `period_stations` id — the
// `stations[].id` of PeriodService::toRow(), one row per station type inside
// a period, which is what PeriodClosureService::close()/reopen()/open()/
// unverifiedCount() have taken since the table was split.
//
// These four used to live at /periods/{id}/close and friends. They were
// moved because the old path LIED: it said "period" while {id} had to be a
// period_station, so every caller and every test URL had to be accompanied
// by a note explaining that. A path that needs a footnote to be used
// correctly is a trap for the next reader, and the closure tests carried
// exactly that footnote. The literal extra segment meant the old routes
// could not collide with PATCH/DELETE /periods/{id}; the new prefix means
// they cannot even be confused with them.
Route::middleware(['auth:web', 'role:admin'])->group(function () {
    Route::get('/periods', [PeriodController::class, 'index']);
    Route::get('/periods/business-units/options', [PeriodController::class, 'businessUnitOptions']);
    // screen-128 panel "Periode Terbuka Hari Ini per Mill" (2026-10-01). A
    // LITERAL segment, so it lives up here with business-units/options and for
    // the identical reason: registered after /periods/{id} it would answer 404,
    // because "open-summary" is a perfectly good-looking {id}. Keeping the two
    // literals adjacent makes the ordering requirement visible at a glance
    // instead of depending on someone reading the comment above.
    Route::get('/periods/open-summary', [PeriodController::class, 'openSummary']);
    // screen-142--detail-periode-pelaporan / usecase-145. Registered AFTER
    // the literal /periods/business-units/options above for the reason
    // spelled out there — as a GET with a {id} segment, this is the route
    // that would otherwise swallow it.
    Route::get('/periods/{id}', [PeriodController::class, 'show']);
    Route::post('/periods', [PeriodController::class, 'store']);
    Route::patch('/periods/{id}', [PeriodController::class, 'update']);
    Route::delete('/periods/{id}', [PeriodController::class, 'destroy']);

    Route::get('/period-stations/{id}/unverified-count', [PeriodController::class, 'unverifiedCount']);
    Route::post('/period-stations/{id}/close', [PeriodController::class, 'close']);
    Route::post('/period-stations/{id}/reopen', [PeriodController::class, 'reopen']);
    Route::post('/period-stations/{id}/open', [PeriodController::class, 'open']);
});

// screen-129--laporan-sterilizer-web (usecase-129 — Laporan Periode
// Sterilizer), now ALSO serving screen-135--laporan-sterilizer-mobile
// (usecase-135 — Laporan Periode Sterilizer (Mobile)). Dual-guarded
// ('auth:web,sanctum', same reasoning as screen-025/026 above) +
// role-guarded per screen_tech_spec.actor_permissions.
//
// 2026-09-23 — `operator` added to the role list for screen-135: the
// mobile report is deliberately open to the actor who enters the data
// ("orang yang menginput data berhak melihat hasilnya", screen-135
// business_rules). This widens the API ONLY. The WEB route
// /reports/sterilizer in routes/web.php is deliberately left at
// supervisor / mill_management / admin — Operator has no web UI at all,
// so a 403 there is still the correct answer.
//
// GET-ONLY, deliberately: a report must not expose any path that mutates
// the Sterilizer data it reports on, so there is no POST/PUT/PATCH/DELETE
// on this prefix.
//
// IMPORTANT — route ordering: none of these are parameterised, so no
// literal-vs-{id} collision is possible here (unlike /periods/... above).
//
// business_unit_id is accepted on /periods but IGNORED for Supervisor /
// Mill Management (SterilizerReportService::resolveBusinessUnit) — probing
// another mill still returns 200 with the caller's own data, on purpose:
// a 403 would confirm the other mill exists. The real cross-mill guard is
// on period_id, in authorizePeriod(), which does answer 403.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])->group(function () {
    Route::get('/sterilizer-reports/business-units/options', [SterilizerReportController::class, 'businessUnitOptions']);
    Route::get('/sterilizer-reports/periods', [SterilizerReportController::class, 'periods']);
    Route::get('/sterilizer-reports/summary', [SterilizerReportController::class, 'summary']);
    Route::get('/sterilizer-reports/export', [SterilizerReportController::class, 'export']);
});

// screen-140--laporan-stasiun-web (usecase-142 — Pilih Stasiun untuk
// Laporan). Dual-guarded ('auth:web,sanctum', same reasoning as
// screen-025/026/129 above) + role-guarded to supervisor /
// mill_management / admin per screen_tech_spec.actor_permissions —
// Operator is a mobile-only actor with no web report access at all.
//
// GET-ONLY, deliberately: this screen only chooses a destination and must
// not expose any path that mutates data.
//
// Note which answer each situation gets, because three are easy to get
// wrong (StationReportService):
//   - Supervisor / Mill Management sending ANOTHER mill's business_unit_id
//     -> 200 with their OWN mill. The parameter is DISCARDED, never
//     validated, so there is nothing to refuse and no 403 to give.
//   - Admin with no business_unit_id -> 422 VALIDATION_ERROR, not 403.
//   - A bound account whose users.business_unit_id is NULL -> 422, and the
//     list of all mills is never read (fail closed).
//
// The options endpoint stays behind the same three-role middleware but is
// Admin-only in the service: Supervisor / Mill Management get 403 there,
// since a role bound to one mill has no picker at all.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin'])->group(function () {
    Route::get('/station-reports/business-units/options', [StationReportController::class, 'businessUnitOptions']);
    Route::get('/station-reports/stations', [StationReportController::class, 'stations']);
});

// screen-130--laporan-cages-track-web (usecase-130 — Laporan Periode
// Cages & Tracks) AND screen-136--laporan-cages-track-mobile (usecase-136
// — Lihat Laporan Periode Cages & Tracks (Mobile)). Dual-guarded +
// role-guarded to supervisor / mill_management / admin / operator, per the
// actor_permissions of BOTH screens.
//
// 2026-09-24 — WIDENED FOR screen-136. The mobile report reuses these four
// routes AS-IS; there is no mobile-only endpoint, exactly as screen-135
// reused /api/sterilizer-reports/* above. THREE changes were required
// together, and none of them is sufficient alone:
//   1. the `sanctum` guard here — mobile authenticates with a Sanctum
//      token, not a session cookie, so 'auth:web' alone would 401 every
//      device;
//   2. `operator` in this role list AND UserRole::Operator in
//      CagesTrackReportService::guardAccess(), which refuses two layers
//      deeper — widening the middleware alone would leave Operator
//      clearing the route only to be refused by the service, which is
//      precisely what happened to screen-135;
//   3. Operator added to the MILL-BOUND branch of
//      CagesTrackReportService::resolveBusinessUnit(). Admitting Operator
//      in guardAccess() only would have dropped it into the unbound Admin
//      branch, letting an Operator pass any business_unit_id and read
//      another mill's report — a cross-mill leak, not a display defect.
//
// ONLY these 4 API routes were widened. The WEB route /reports/cages-track
// in routes/web.php deliberately stays at supervisor / mill_management /
// admin — Operator has no web UI at all, so a 403 there is still the
// correct answer (asserted by Feature/Livewire/LaporanCagesTrackTest and
// e2e-web/tests/laporan-cages-track.spec.ts, both of which must stay green
// unchanged).
//
// GET-ONLY, deliberately: a report must not expose any path that mutates
// the Cages & Tracks data it reports on, so there is no
// POST/PUT/PATCH/DELETE on this prefix.
//
// IMPORTANT — route ordering: none of these are parameterised, so no
// literal-vs-{id} collision is possible here.
//
// business_unit_id is accepted on /periods, /summary and /export but is
// IGNORED for Supervisor / Mill Management / Operator
// (CagesTrackReportService::resolveBusinessUnit) — probing another mill
// still returns 200 with the caller's own data, on purpose: a 403 would
// confirm the other mill exists. The real cross-mill guard is on
// period_id, in authorizePeriod(), which does answer 403 — including for
// Operator, which is bound like the other two mill-bound roles.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])->group(function () {
    Route::get('/cages-track-reports/business-units/options', [CagesTrackReportController::class, 'businessUnitOptions']);
    Route::get('/cages-track-reports/periods', [CagesTrackReportController::class, 'periods']);
    Route::get('/cages-track-reports/summary', [CagesTrackReportController::class, 'summary']);
    Route::get('/cages-track-reports/export', [CagesTrackReportController::class, 'export']);
});

// screen-131--laporan-boiler-room-web (usecase-131 — Laporan Periode Boiler
// Room). Mirrors the /api/cages-track-reports group above, role list
// included.
//
// 2026-09-25 — WIDENED FOR screen-137 (the mobile Boiler Room report). The
// mobile report reuses these four routes AS-IS; there is no mobile-only
// endpoint, exactly as screen-135 reused /api/sterilizer-reports/* and
// screen-136 reused /api/cages-track-reports/*. The `sanctum` guard was
// ALREADY on this group, so only the role list moved here — but the
// widening is still THREE changes that ship together or not at all:
//   1. `sanctum` in the guard — mobile authenticates with a Sanctum token,
//      not a session cookie, so 'auth:web' alone would 401 every device
//      (already present on this group before screen-137);
//   2. `operator` in this role list AND UserRole::Operator in
//      BoilerRoomReportService::guardAccess(), which refuses two layers
//      deeper — widening the middleware alone would leave Operator
//      clearing the route only to be refused by the service, which is
//      precisely what happened to screen-129/135;
//   3. Operator added to the MILL-BOUND branch of
//      BoilerRoomReportService::resolveBusinessUnit(). Admitting Operator
//      in guardAccess() only would have dropped it into the unbound Admin
//      branch, letting an Operator pass any business_unit_id and read
//      another mill's report — a cross-mill leak, not a display defect.
//
// ONLY these 4 API routes were widened. The WEB route /reports/boiler-room
// in routes/web.php deliberately stays at supervisor / mill_management /
// admin — Operator has no web UI at all, so a 403 there is still the
// correct answer (asserted by Feature/Livewire/LaporanBoilerRoomTest and
// e2e-web/tests/laporan-boiler-room.spec.ts, both of which must stay green
// unchanged, because LaporanBoilerRoom::canAccess() keeps its own role list
// and never calls this service).
//
// GET-ONLY, deliberately: a report must not expose any path that mutates
// the Boiler Room data it reports on, so there is no
// POST/PUT/PATCH/DELETE on this prefix.
//
// IMPORTANT — route ordering: none of these are parameterised, so no
// literal-vs-{id} collision is possible here.
//
// business_unit_id is accepted on /periods, /summary and /export but is
// IGNORED for Supervisor / Mill Management / Operator
// (BoilerRoomReportService::resolveBusinessUnit) — probing another mill
// still returns 200 with the caller's own data, on purpose: a 403 would
// confirm the other mill exists. The real cross-mill guard is on period_id,
// in authorizePeriod(), which does answer 403 — including for Operator,
// which is bound like the other two mill-bound roles.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])->group(function () {
    Route::get('/boiler-room-reports/business-units/options', [BoilerRoomReportController::class, 'businessUnitOptions']);
    Route::get('/boiler-room-reports/periods', [BoilerRoomReportController::class, 'periods']);
    Route::get('/boiler-room-reports/summary', [BoilerRoomReportController::class, 'summary']);
    Route::get('/boiler-room-reports/export', [BoilerRoomReportController::class, 'export']);
});

// screen-132--laporan-clarification-web (Laporan Periode Clarification) —
// four GET endpoints behind 'role:supervisor,mill_management,admin'.
//
// OPERATOR WAS ADDED 2026-09-25 for screen-138--laporan-clarification-mobile,
// the mobile Clarification report. Until that screen there was no caller to
// widen for and the role was deliberately absent; the caller now exists, and
// the people who key the readings in are entitled to read them back. Same
// widening SterilizerReportService got for screen-135, CagesTrackReportService
// for screen-136 and BoilerRoomReportService for screen-137.
//
// THE WIDENING IS THREE LINES OF CODE AND THEY ARE ONE CHANGE, NEVER THREE:
// this middleware list, ClarificationReportService::guardAccess(), and
// ClarificationReportService::resolveBusinessUnit() — the last of which puts
// Operator in the MILL-BOUND branch. Widening the first two alone would drop
// Operator into the Admin branch, where the client's business_unit_id IS
// honoured, and that is a cross-mill leak rather than a display defect. The
// guard 'auth:web,sanctum' needed no change: it was already here, because the
// mobile app authenticates with a Sanctum token rather than a session.
//
// The WEB route /reports/clarification in routes/web.php is UNCHANGED and
// still carries no `operator`: this widening stops at the API. Operator has
// no web UI at all.
//
// GET-ONLY, deliberately: a report must not expose any path that mutates
// the Clarification data it reports on, so there is no
// POST/PUT/PATCH/DELETE on this prefix.
//
// IMPORTANT — route ordering: none of these are parameterised, so no
// literal-vs-{id} collision is possible here.
//
// business_unit_id is accepted on /periods, /summary and /export but is
// IGNORED for Supervisor / Mill Management / Operator
// (ClarificationReportService::resolveBusinessUnit) — probing another mill
// still returns 200 with the caller's own data, on purpose: a 403 would
// confirm the other mill exists. The real cross-mill guard is on period_id,
// in authorizePeriod(), which does answer 403.
//
// /business-units/options stays ADMIN ONLY and answers 403 for Operator —
// enforced by the service, not by this middleware. A role bound to one mill
// has no picker, and handing it the list of every mill would be the very leak
// the widening avoided.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])->group(function () {
    Route::get('/clarification-reports/business-units/options', [ClarificationReportController::class, 'businessUnitOptions']);
    Route::get('/clarification-reports/periods', [ClarificationReportController::class, 'periods']);
    Route::get('/clarification-reports/summary', [ClarificationReportController::class, 'summary']);
    Route::get('/clarification-reports/export', [ClarificationReportController::class, 'export']);
});

// screen-133--laporan-storage-tank-web (Laporan Periode Storage Tank) —
// four GET endpoints, shared verbatim with
// screen-139--laporan-storage-tank-mobile.
//
// OPERATOR WAS ADDED 2026-09-25 for screen-139, the mobile Storage Tank
// report, which reuses THESE four endpoints rather than getting its own —
// one source of figures for the web report and the phone. The same widening
// sterilizer-reports got for screen-135, cages-track-reports for
// screen-136, boiler-room-reports for screen-137 and clarification-reports
// for screen-138. The people who key the readings in are entitled to read
// them back.
//
// The widening is THREE lines of code, never one: this middleware,
// StorageTankReportService::guardAccess(), and — the one that is easy to
// miss — StorageTankReportService::resolveBusinessUnit(), which must place
// Operator in the MILL-BOUND branch. Widening the first two alone would drop
// Operator into the Admin branch, where the client's business_unit_id is
// HONOURED: a cross-mill leak, not a display defect.
//
// The WEB route /reports/storage-tank (routes/web.php) is deliberately
// UNCHANGED and still refuses Operator — there is no Operator web UI, and
// the widening stops at the API.
//
// GET-ONLY, deliberately: a report must not expose any path that mutates
// the Storage Tank data it reports on, so there is no
// POST/PUT/PATCH/DELETE on this prefix.
//
// IMPORTANT — route ordering: none of these are parameterised, so no
// literal-vs-{id} collision is possible here.
//
// business_unit_id is accepted on /periods, /summary and /export but is
// IGNORED for every mill-bound role — Operator, Supervisor and Mill
// Management alike (StorageTankReportService::resolveBusinessUnit) —
// probing another mill still returns 200 with the caller's own data, on
// purpose: a 403 would confirm the other mill exists. The real cross-mill
// guard is on period_id, in authorizePeriod(), which does answer 403.
//
// /business-units/options stays ADMIN ONLY and answers 403 for Operator —
// enforced by the service, not by this middleware. A role bound to one mill
// has no picker, and handing it the list of every mill would be the very leak
// the widening avoided.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])->group(function () {
    Route::get('/storage-tank-reports/business-units/options', [StorageTankReportController::class, 'businessUnitOptions']);
    Route::get('/storage-tank-reports/periods', [StorageTankReportController::class, 'periods']);
    Route::get('/storage-tank-reports/summary', [StorageTankReportController::class, 'summary']);
    Route::get('/storage-tank-reports/export', [StorageTankReportController::class, 'export']);
});

// screen-143--laporan-weighbridge-web (Laporan Periode Weighbridge) — four
// GET endpoints.
//
// OPERATOR IS ADMITTED SINCE 2026-10-05, when screen-144 (the mobile
// Weighbridge report) was built on these four endpoints rather than getting
// its own — the same reason the five sibling report prefixes (sterilizer /
// cages-track / boiler-room / clarification / storage-tank) already carried
// `operator`.
//
// The widening was THREE changes landing together: this middleware,
// WeighbridgeReportService::guardAccess(), and — the one that is easy to miss
// — the MILL-BOUND branch of
// WeighbridgeReportService::resolveBusinessUnit(). THE THIRD IS WHAT MAKES
// THIS LINE SAFE: without it Operator falls into the Admin branch, where the
// client's business_unit_id IS HONOURED, and an Operator could then read any
// mill's report by naming it. That is a cross-mill leak, not a display
// defect, and it is why the test asserts the OUTCOME — an Operator sending
// another mill's id still receives its own data — rather than merely that the
// role is accepted.
//
// `auth:web,sanctum` was already here and did NOT change: the phone
// authenticates with a Sanctum token and the web page with a session, and
// both guards were admitted from the start.
//
// NOT WIDENED, on purpose: /business-units/options still answers 403 for
// Operator (enforced inside the service, not by this middleware, so this line
// did not loosen it), and the WEB route /reports/weighbridge still carries
// only the three web roles — Operator has no web report UI.
//
// GET-ONLY, deliberately: a report must not expose any path that mutates the
// Weighbridge data it reports on, so there is no POST/PUT/PATCH/DELETE on
// this prefix.
//
// IMPORTANT — route ordering: none of these are parameterised, so no
// literal-vs-{id} collision is possible here.
//
// BOTH period_id AND production_line_id ARE REQUIRED on /summary and
// /export — 422 VALIDATION_ERROR when either is missing, and ZERO
// weighbridge_records queries run. This is the one way this prefix differs
// from the five siblings, where production_line_id stayed optional so their
// already-shipped mobile screens would not break. Weighbridge had no shipped
// mobile screen when this prefix was written, so there was no old reader to
// protect — and screen-144 was then built to the stricter contract rather
// than the contract being relaxed for it. A report that silently widened to
// every line of the mill would answer 200 with a figure nobody asked for.
//
// business_unit_id is accepted on /periods, /summary and /export but is
// IGNORED for every mill-bound role — Operator, Supervisor and Mill
// Management alike (WeighbridgeReportService::resolveBusinessUnit) — probing
// another mill still
// returns 200 with the caller's own data, on purpose: a 403 would confirm the
// other mill exists. The real cross-mill guards are on production_line_id
// (resolveProductionLine) and period_id (authorizePeriod), and both DO answer
// 403.
//
// The production-line OPTION LIST is NOT duplicated here: GET
// /api/production-lines/options-for-report (built for screen-135) is reused
// verbatim.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])->group(function () {
    Route::get('/weighbridge-reports/business-units/options', [WeighbridgeReportController::class, 'businessUnitOptions']);
    Route::get('/weighbridge-reports/periods', [WeighbridgeReportController::class, 'periods']);
    Route::get('/weighbridge-reports/summary', [WeighbridgeReportController::class, 'summary']);
    Route::get('/weighbridge-reports/export', [WeighbridgeReportController::class, 'export']);
});

// screen-146--laporan-grading-web + screen-147--laporan-grading-mobile
// (Laporan Periode Grading) — four GET endpoints shared by the web page, the
// API and the phone, so none of the three can report a different figure.
//
// OPERATOR IS ADMITTED FROM DAY ONE, unlike the Weighbridge prefix above where
// the mobile twin arrived later and the widening had to be its own reviewable
// step. Here screen-147 ships in the SAME change, so a role list that would
// have to be widened an hour later would be theatre. What makes it safe is
// unchanged and is the part that is easy to miss: Operator sits in the
// MILL-BOUND branch of GradingReportService::resolveBusinessUnit() from the
// first line. Without that, Operator falls into the Admin branch where the
// client's business_unit_id IS HONOURED, and an Operator could read any mill's
// report by naming it — a cross-mill leak, not a display defect. The test
// asserts the OUTCOME (another mill's id still returns the caller's own data),
// not merely that the role is accepted.
//
// NOT WIDENED, on purpose: /business-units/options answers 403 for Operator,
// enforced inside GradingReportService::businessUnitOptions() rather than by
// this middleware, so admitting the role here did not loosen it. The WEB route
// /reports/grading carries only the three web roles — Operator has no web
// report UI.
//
// GET-ONLY, deliberately: a report must not expose any path that mutates the
// Grading data it reports on, so there is no POST/PUT/PATCH/DELETE here.
//
// BOTH period_id AND production_line_id ARE REQUIRED on /summary and /export —
// 422 VALIDATION_ERROR when either is missing, and ZERO grading_records
// queries run. Same strict contract as the Weighbridge prefix: there is no
// all-lines fallback, because a total mixing a dozen lines is not a number
// anyone can act on.
//
// business_unit_id is accepted on /periods, /summary and /export but is
// IGNORED for every mill-bound role — Operator, Supervisor and Mill Management
// alike (GradingReportService::resolveBusinessUnit) — probing another mill
// still returns 200 with the caller's own data, on purpose: a 403 would
// confirm the other mill exists. The real cross-mill guards are on
// production_line_id (resolveProductionLine) and period_id (authorizePeriod),
// and both DO answer 403.
//
// The production-line OPTION LIST is NOT duplicated here: GET
// /api/production-lines/options-for-report (built for screen-135) is reused
// verbatim.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])->group(function () {
    Route::get('/grading-reports/business-units/options', [GradingReportController::class, 'businessUnitOptions']);
    Route::get('/grading-reports/periods', [GradingReportController::class, 'periods']);
    Route::get('/grading-reports/summary', [GradingReportController::class, 'summary']);
    Route::get('/grading-reports/export', [GradingReportController::class, 'export']);
});

// === LAPORAN PERIODE THRESHING (screen-148 web + screen-149 mobile) ===
//
// Read-only: four GET routes and nothing else on this prefix. A report must
// never offer a path that can alter the data it reports on.
//
// OPERATOR IS IN THE ROLE LIST FROM DAY ONE, because screen-149 (the mobile
// twin) is built in the same series. That is the lesson of the Weighbridge
// pair, where screen-143 shipped web-only and screen-144 then had to
// retro-fit THREE changes at once (this role list,
// WeighbridgeReportService::guardAccess(), and the mill-bound branch of
// resolveBusinessUnit()). Note which of the three carries the weight: role
// list plus guardAccess() WITHOUT the mill-bound branch drops Operator into
// the unbound Admin branch, where the client's business_unit_id IS honoured
// — a cross-mill leak, not a display defect.
//
// /business-units/options is the ONE route here Operator cannot use. The
// refusal is raised inside ThreshingReportService::businessUnitOptions(),
// not by this middleware, precisely because this middleware admits Operator
// for the other three: a role bound to one mill has no picker, and handing
// it the list of every mill is the leak this must not open.
//
// business_unit_id is accepted on /periods, /summary and /export but is
// IGNORED for every mill-bound role — Operator, Supervisor and Mill
// Management alike (ThreshingReportService::resolveBusinessUnit) — and
// probing another mill still returns 200 with the caller's own data, on
// purpose: a 403 would confirm the other mill exists. The real cross-mill
// guards are on production_line_id (resolveProductionLine) and period_id
// (authorizePeriod), and both DO answer 403.
//
// The production-line OPTION LIST is NOT duplicated here: GET
// /api/production-lines/options-for-report (built for screen-135) is reused
// verbatim.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])->group(function () {
    Route::get('/threshing-reports/business-units/options', [ThreshingReportController::class, 'businessUnitOptions']);
    Route::get('/threshing-reports/periods', [ThreshingReportController::class, 'periods']);
    Route::get('/threshing-reports/summary', [ThreshingReportController::class, 'summary']);
    Route::get('/threshing-reports/export', [ThreshingReportController::class, 'export']);
});

// === LAPORAN PERIODE PRESSING (screen-150 web + screen-151 mobile) ===
//
// Read-only: four GET routes and nothing else on this prefix.
//
// OPERATOR IS IN THE ROLE LIST FROM DAY ONE, because screen-151 (the mobile
// twin) is built in the same series — the pattern proved by screen-146/147 and
// screen-148/149, and the one the Weighbridge pair had to retro-fit with three
// changes at once. Note which of the three carries the weight: role list plus
// guardAccess() WITHOUT the mill-bound branch of resolveBusinessUnit() drops
// Operator into the unbound Admin branch, where the client's business_unit_id
// IS honoured — a cross-mill leak, not a display defect.
//
// /business-units/options is the ONE route here Operator cannot use. The
// refusal is raised inside PressingReportService::businessUnitOptions(), not
// by this middleware, precisely because this middleware admits Operator for
// the other three.
//
// business_unit_id is accepted on /periods, /summary and /export but is
// IGNORED for every mill-bound role, and probing another mill still returns
// 200 with the caller's own data: a 403 would confirm the other mill exists.
// The real cross-mill guards are on production_line_id and period_id.
//
// The production-line OPTION LIST is NOT duplicated here: GET
// /api/production-lines/options-for-report (built for screen-135) is reused
// verbatim.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])->group(function () {
    Route::get('/pressing-reports/business-units/options', [PressingReportController::class, 'businessUnitOptions']);
    Route::get('/pressing-reports/periods', [PressingReportController::class, 'periods']);
    Route::get('/pressing-reports/summary', [PressingReportController::class, 'summary']);
    Route::get('/pressing-reports/export', [PressingReportController::class, 'export']);
});

// === LAPORAN PERIODE DEPRICARPING (screen-152 web + screen-153 mobile) ===
// Same four-endpoint shape and the same role list as the Pressing prefix
// above, and Operator is admitted here from the FIRST commit because the
// mobile twin is built in the same series — the Weighbridge pair (143 -> 144)
// had to patch three places afterwards precisely because its web screen
// shipped before its mobile twin was known.
//
// /business-units/options still REFUSES Operator with 403, raised inside
// DepricarpingReportService rather than by this middleware, which admits all
// four roles for the other three.
//
// The production-line OPTION LIST is NOT duplicated here: GET
// /api/production-lines/options-for-report (built for screen-135) is reused
// verbatim.
Route::middleware(['auth:web,sanctum', 'role:supervisor,mill_management,admin,operator'])->group(function () {
    Route::get('/depricarping-reports/business-units/options', [DepricarpingReportController::class, 'businessUnitOptions']);
    Route::get('/depricarping-reports/periods', [DepricarpingReportController::class, 'periods']);
    Route::get('/depricarping-reports/summary', [DepricarpingReportController::class, 'summary']);
    Route::get('/depricarping-reports/export', [DepricarpingReportController::class, 'export']);
});

// === ENDPOINT BACA MOBILE (audit 2026-10-04) ===
// Dua endpoint baca yang dibutuhkan aplikasi mobile; mobile sudah memanggil
// keduanya dan menurun dengan aman bila belum tersedia.
//
// GET /grading-parameters — master Quality Parameter (global, tidak terikat
// mill) untuk memetakan id buatan lokal ke id server
// (mobile/src/services/gradingParameterSync.ts).
Route::middleware(['auth:web,sanctum', 'role:admin,supervisor,mill_management,operator'])
    ->get('/grading-parameters', [GradingParameterController::class, 'index']);

// GET /records/{stationType}/verification?ids[]=… — pasangan baca dari PATCH
// /records/{stationType}/{id}/verification di atas (whitelist jenis stasiun
// yang sama). BEDA dengan PATCH-nya, 'operator' IKUT: membaca status
// verifikasi record sendiri bukan memverifikasi. Cakupan mill ditegakkan di
// RecordVerificationStatusService (id mill lain tidak muncul di hasil).
// Jumlah segmen berbeda dari PATCH (3 vs 4), jadi tidak bertabrakan.
Route::middleware(['auth:web,sanctum', 'role:admin,supervisor,mill_management,operator'])
    ->get('/records/{stationType}/verification', [RecordVerificationStatusController::class, 'index']);
// === AKHIR ENDPOINT BACA MOBILE ===

// === ASDLC_ROUTES_END ===

// Logout mobile (audit keamanan 2026-10-05) — infrastruktur, sejajar dengan
// POST /logout di routes/web.php; belum punya entri tech-spec sehingga di
// luar blok ASDLC. Sebelumnya route ini TIDAK ADA: authStore.logout() di
// aplikasi mobile menerima 404 (ditelan best-effort) dan token Sanctum
// perangkat tidak pernah dicabut. Mencabut HANYA token yang dipakai request
// ini — token perangkat lain milik user yang sama tetap berlaku. Request
// bersesi (web/SPA stateful) mengeluarkan sesinya. Lihat
// AuthController::logout().
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
