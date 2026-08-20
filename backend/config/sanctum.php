<?php

use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Sanctum — auth-middleware (shared-modules)
|--------------------------------------------------------------------------
| shared_decisions.auth: web (Livewire) authenticates via first-party
| stateful (session-cookie) requests from the domains listed below; the
| mobile app authenticates via long-lived personal access tokens (stored
| locally, re-validated on the next online action per shared_decisions.auth
| .notes — tokens do not auto-expire while the device is offline, hence
| `expiration` is left null / not enforced server-side).
|
| Deliberately excludes the mobile app's Vite dev port (5173) — listing it
| here made EnsureFrontendRequestsAreStateful treat every mobile request as
| stateful/CSRF-protected regardless of the Authorization: Bearer header,
| causing "CSRF token mismatch" (419) on every mutating mobile API call
| (found via the sync feature, 2026-08-20). The mobile app must always
| resolve to the token guard, never the stateful/session path.
*/
return [

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort()
    ))),

    'guard' => ['web'],

    'expiration' => null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
