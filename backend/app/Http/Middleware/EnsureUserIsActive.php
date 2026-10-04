<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureUserIsActive — menolak sesi web milik akun yang SUDAH dinonaktifkan.
 *
 * AuthService::login() hanya memeriksa is_active saat login; sesi yang
 * sudah berjalan sebelumnya tetap hidup (temuan audit: /mill-settings
 * masih 200 setelah akun dinonaktifkan). Middleware ini ditambahkan ke grup
 * 'web' DAN 'api' (bootstrap/app.php) — termasuk /livewire/update — dan
 * pada request berikutnya mengeluarkan user tsb: sesi diinvalidasi, lalu
 *   - request JSON  → 401 { message }
 *   - selain itu     → redirect ke Login dengan pesan (Livewire mengikuti
 *                      redirect ini sendiri lewat response.redirected).
 *
 * Hanya guard 'web' (sesi) yang diperiksa di sini. Token Sanctum (mobile)
 * ditolak lewat Sanctum::authenticateAccessTokensUsing() di
 * AppServiceProvider dan dicabut saat penonaktifan (UserService::setStatus()).
 */
class EnsureUserIsActive
{
    public const MESSAGE = 'Akun Anda telah dinonaktifkan, hubungi Admin.';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $user = Auth::guard('web')->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() && ! $request->hasHeader('X-Livewire')) {
                return response()->json(['message' => self::MESSAGE], 401);
            }

            return redirect()->route('login')->with('auth_error', self::MESSAGE);
        }

        return $next($request);
    }
}
