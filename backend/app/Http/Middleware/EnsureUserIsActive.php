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
 * Sejak 2026-10-05 juga mengeluarkan sesi yang dibuat SEBELUM
 * users.sessions_revoked_at (Reset Password oleh Admin — lihat
 * UserService::update()), dengan pesan REVOKED_MESSAGE.
 *
 * Hanya guard 'web' (sesi) yang diperiksa di sini. Token Sanctum (mobile)
 * ditolak lewat Sanctum::authenticateAccessTokensUsing() di
 * AppServiceProvider dan dicabut saat penonaktifan (UserService::setStatus()).
 */
class EnsureUserIsActive
{
    public const MESSAGE = 'Akun Anda telah dinonaktifkan, hubungi Admin.';

    public const REVOKED_MESSAGE = 'Sesi Anda telah berakhir karena password direset oleh Admin. Silakan login kembali.';

    /**
     * Kunci sesi: kapan sesi ini login (milidetik epoch). Ditulis saat
     * event Login (AppServiceProvider) dan, untuk sesi lama yang belum
     * punya, pada request pertama yang melihatnya. Sesi yang stempelnya
     * LEBIH TUA dari users.sessions_revoked_at dikeluarkan (Reset Password
     * oleh Admin, 2026-10-05).
     */
    public const SESSION_AUTH_AT = 'auth_session_started_at_ms';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $user = Auth::guard('web')->user();

        if ($user && ! $user->is_active) {
            return $this->logOut($request, self::MESSAGE);
        }

        if ($user) {
            $startedAt = $request->session()->get(self::SESSION_AUTH_AT);

            if ($startedAt === null) {
                $startedAt = now()->getTimestampMs();
                $request->session()->put(self::SESSION_AUTH_AT, $startedAt);
            }

            $revokedAt = $user->sessions_revoked_at;

            if ($revokedAt !== null && (int) $startedAt < $revokedAt->getTimestampMs()) {
                return $this->logOut($request, self::REVOKED_MESSAGE);
            }
        }

        return $next($request);
    }

    protected function logOut(Request $request, string $message): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() && ! $request->hasHeader('X-Livewire')) {
            return response()->json(['message' => $message], 401);
        }

        return redirect()->route('login')->with('auth_error', $message);
    }
}
