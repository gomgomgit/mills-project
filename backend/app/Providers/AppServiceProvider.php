<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum; // Ensure this is imported

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Token Sanctum (mobile) milik akun nonaktif ditolak (401) walau
        // belum dicabut — penonaktifan juga mencabut token
        // (UserService::setStatus()), ini lapis kedua untuk token yang
        // terlewat. Sesi web ditangani EnsureUserIsActive.
        Sanctum::authenticateAccessTokensUsing(function ($accessToken, bool $isValid): bool {
            if (! $isValid) {
                return false;
            }

            $tokenable = $accessToken->tokenable;

            return ! ($tokenable instanceof User) || (bool) $tokenable->is_active;
        });

        // Stempel waktu login sesi web — pembanding users.sessions_revoked_at
        // di EnsureUserIsActive (Reset Password oleh Admin mencabut sesi yang
        // lebih tua dari reset, keputusan 2026-10-05).
        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard === 'web' && app()->bound('request') && request()->hasSession()) {
                request()->session()->put(EnsureUserIsActive::SESSION_AUTH_AT, now()->getTimestampMs());
            }
        });

        // Enforce HTTPS exclusively in production environments
        if (app()->environment('production')) {
            URL::forceScheme('https');

            // Note: In newer Laravel versions, you can also use:
            // URL::forceHttps();
        }
    }
}
