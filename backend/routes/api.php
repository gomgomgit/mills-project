<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessUnitController;
use App\Http\Controllers\Api\CagesTrackRecordController;
use App\Http\Controllers\Api\GradingRecordController;
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
// === ASDLC_ROUTES_END ===
