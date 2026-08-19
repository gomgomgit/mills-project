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
*/
return [

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,localhost:5173,127.0.0.1,127.0.0.1:8000,127.0.0.1:5173,::1',
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
