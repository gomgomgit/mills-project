<?php

use App\Exceptions\ApiExceptionHandler;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/health',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Sanctum stateful API middleware (session-based auth for Livewire web,
        // token-based for mobile) — implemented in shared-modules' auth-middleware.
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Role-based route guard (auth-middleware, shared-modules) — every
        // screen tech-spec's actor_permissions are role-scoped. Usage:
        // ->middleware('role:admin,supervisor'). Registered per-screen in
        // impl-2-screen, after an auth guard (auth / auth:sanctum).
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        // POST /api/login (screen-001--login-web + screen-002--login-mobile,
        // App\Http\Controllers\Api\AuthController::login) is CSRF-exempt.
        // That route stacks the 'web' middleware group (for the session
        // cookie the web/session branch needs) on top of the 'api' group,
        // which would otherwise also pull in VerifyCsrfToken -- blocking the
        // screen-002 mobile branch, whose native client has no session/CSRF
        // token yet on its very first request (token issuance IS the
        // login). Safe to exempt: the web branch is never actually invoked
        // over HTTP by the Livewire login (it calls AuthService::login()
        // in-process), so no CSRF-protected browser flow relies on this
        // route's CSRF check.
        $middleware->validateCsrfTokens(except: [
            'api/login',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Error response shape (shared_decisions.error_format) — registered by
        // shared-modules' error-handler module. Only renders JSON for requests
        // expecting JSON (API routes); web/Livewire requests are unaffected.
        // See app/Exceptions/ApiExceptionHandler.
        $exceptions->render(function (\Throwable $e, Request $request) {
            return ApiExceptionHandler::render($request, $e);
        });
    })->create();
