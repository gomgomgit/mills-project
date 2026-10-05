<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * SignalDownloadReady — sinyal "respons unduhan sudah tiba" untuk status
 * loading tautan ekspor di web (2026-10-05).
 *
 * Tombol "Ekspor CSV/Excel" Data Browser & Laporan Manajemen adalah tautan
 * <a href> biasa ke route ekspor (bukan request Livewire), jadi halaman tidak
 * bisa tahu kapan unduhannya selesai. JS di components/loading-assets
 * menambahkan token acak `?_dl=<token>` ke href saat diklik; middleware ini
 * (grup 'web', yang juga ditumpuk pada route ekspor di routes/api.php)
 * memasang cookie `ms_download=<token>` pada respons APA PUN untuk request
 * itu — file maupun error — dan JS mengakhiri status loading begitu cookie
 * bernilai token-nya terlihat.
 *
 * Tidak mengubah isi respons; request tanpa `_dl` (atau dengan token yang
 * tidak valid) dilewatkan apa adanya. Cookie tidak HttpOnly (memang harus
 * dibaca JS), tidak dienkripsi (dikecualikan di bootstrap/app.php), dan
 * hanya hidup 60 detik. Nilainya hanya token acak dari klien sendiri —
 * tidak memuat data apa pun.
 */
class SignalDownloadReady
{
    public const COOKIE = 'ms_download';

    public const QUERY = '_dl';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $token = $request->query(self::QUERY);

        if (is_string($token) && preg_match('/^[A-Za-z0-9]{6,40}$/', $token) === 1) {
            $response->headers->setCookie(new Cookie(
                self::COOKIE,
                $token,
                time() + 60,
                '/',
                null,
                $request->isSecure(),
                false,
                false,
                Cookie::SAMESITE_LAX,
            ));
        }

        return $response;
    }
}
