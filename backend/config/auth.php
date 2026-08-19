<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Authentication — auth-middleware (shared-modules)
|--------------------------------------------------------------------------
| shared_decisions.auth.mechanism: Laravel Sanctum (token-based) for
| mobile/API + Laravel session cookie for web (Livewire).
|
| Guards:
|   - "web"     — session-cookie guard, used by Livewire (Admin/Supervisor/
|                 Mill Management web app).
|   - "sanctum" — token guard (Laravel\Sanctum\Guard), used by API routes
|                 consumed by the mobile app. Also transparently handles
|                 first-party stateful (cookie) requests from the web app
|                 when routes are protected with auth:sanctum instead of
|                 auth:web — see EnsureFrontendRequestsAreStateful in
|                 bootstrap/app.php.
|
| Provider "users" resolves to App\Models\User, whose auth password column
| is `password_hash` (see User::getAuthPasswordName()).
*/
return [

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
