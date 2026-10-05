{{--
    Halaman 404 ramah pengguna — menggantikan halaman bawaan Laravel
    "404 NOT FOUND" (bahasa Inggris, tanpa navigasi). Dua varian:

    - Di dalam shell aplikasi (sidebar per peran + Logout) bila sesi tersedia
      dan user sudah login — mis. abort(404) / route model binding di rute web.
    - Kartu mandiri bila tidak ada sesi. URL yang sama sekali tidak cocok
      dengan rute mana pun dirender SEBELUM middleware grup 'web' berjalan,
      jadi sesi belum dimulai dan auth()->check() selalu false di sini; tombol
      "Ke Beranda" menuju '/' yang mengalihkan user yang sudah login ke
      beranda perannya dan tamu ke Login, sehingga tetap benar untuk keduanya.

    Permintaan JSON / api/* tidak pernah sampai ke view ini —
    ApiExceptionHandler menjawabnya dengan envelope JSON (code NOT_FOUND).
--}}
@php
    $homeUrl = auth()->check() ? app(\App\Services\AuthService::class)->redirectFor(auth()->user()->role->value) : null;
    $requestedPath = '/'.ltrim(rawurldecode(request()->path()), '/');
@endphp
@if ($homeUrl)
    <x-layouts.app title="Halaman Tidak Ditemukan">
        <x-slot:styles>
            <style>
                .notfound-card {
                    max-width: 560px;
                    margin: 48px auto 0;
                    padding: 36px 28px 32px;
                    background: #fff;
                    border: 1px solid var(--color-border);
                    border-radius: 10px;
                    text-align: center;
                }

                .notfound-card__icon {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 56px;
                    height: 56px;
                    margin: 0 0 16px;
                    border-radius: 50%;
                    background: #e8f5ee;
                    color: var(--color-brand);
                }

                .notfound-card__code {
                    margin: 0 0 8px;
                    font-size: 13px;
                    font-weight: 700;
                    letter-spacing: 0.08em;
                    color: var(--color-text-muted);
                }

                .notfound-card__title {
                    margin: 0 0 12px;
                    font-size: 20px;
                    font-weight: 700;
                    color: var(--color-text);
                }

                .notfound-card__text {
                    margin: 0 0 12px;
                    font-size: 14px;
                    line-height: 1.5;
                    color: var(--color-text-muted);
                }

                .notfound-card__path {
                    display: inline-block;
                    max-width: 100%;
                    margin: 0 0 24px;
                    padding: 4px 10px;
                    border-radius: 6px;
                    background: #f3f4f6;
                    color: var(--color-text);
                    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                    font-size: 13px;
                    overflow-wrap: anywhere;
                }

                .notfound-card__actions {
                    display: flex;
                    flex-wrap: wrap;
                    justify-content: center;
                    gap: 12px;
                }

                .notfound-card__primary {
                    display: inline-flex;
                    align-items: center;
                    min-height: 40px;
                    padding: 8px 18px;
                    border-radius: var(--radius-input);
                    background: var(--color-brand);
                    color: #fff;
                    font-size: 14px;
                    font-weight: 600;
                    text-decoration: none;
                }

                .notfound-card__primary:hover {
                    background: var(--color-brand-hover);
                }

                .notfound-card__secondary {
                    min-height: 40px;
                    padding: 8px 18px;
                    border: 1px solid var(--color-border);
                    border-radius: var(--radius-input);
                    background: #fff;
                    color: var(--color-text);
                    font-size: 14px;
                    font-weight: 600;
                    font-family: inherit;
                    cursor: pointer;
                }

                .notfound-card__secondary:hover {
                    background: #f3f4f6;
                }
            </style>
        </x-slot:styles>

        <div class="notfound-card" data-testid="not-found-page">
            <div class="notfound-card__icon" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/><path d="M8.5 11h5"/></svg>
            </div>
            <p class="notfound-card__code">404 · Not Found</p>
            <h2 class="notfound-card__title">Halaman Tidak Ditemukan</h2>
            <p class="notfound-card__text">
                Halaman yang Anda buka tidak ada, sudah dipindahkan, atau alamatnya salah ketik.
                Periksa kembali alamatnya, atau gunakan menu di samping.
            </p>
            <code class="notfound-card__path" data-testid="not-found-path">{{ $requestedPath }}</code>
            <div class="notfound-card__actions">
                <a href="{{ url($homeUrl) }}" class="notfound-card__primary" data-testid="not-found-home">Kembali ke Beranda</a>
                <button type="button" class="notfound-card__secondary" data-testid="not-found-back" onclick="history.length > 1 ? history.back() : window.location.assign('{{ url($homeUrl) }}')">Halaman Sebelumnya</button>
            </div>
        </div>
    </x-layouts.app>
@else
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Halaman Tidak Ditemukan — Mills Smart Log</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            *, *::before, *::after { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 16px; background: #f3f4f6; font-family: Inter, system-ui, sans-serif; color: #1f2937; }
            .notfound-guest { width: 100%; max-width: 440px; padding: 36px 28px 32px; background: #fff; border: 1px solid #d1d5db; border-radius: 10px; text-align: center; }
            .notfound-guest__brand { margin: 0 0 24px; font-size: 15px; font-weight: 700; color: #249360; }
            .notfound-guest__big { margin: 0; font-size: 56px; font-weight: 700; line-height: 1; color: #249360; letter-spacing: -0.02em; }
            .notfound-guest__title { margin: 12px 0; font-size: 20px; font-weight: 700; }
            .notfound-guest__text { margin: 0 0 12px; font-size: 14px; line-height: 1.5; color: #6b7280; }
            .notfound-guest__path { display: inline-block; max-width: 100%; margin: 0 0 24px; padding: 4px 10px; border-radius: 6px; background: #f3f4f6; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; overflow-wrap: anywhere; }
            .notfound-guest__actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; }
            .notfound-guest__primary { display: inline-flex; align-items: center; min-height: 40px; padding: 8px 18px; border-radius: 6px; background: #249360; color: #fff; font-size: 14px; font-weight: 600; text-decoration: none; }
            .notfound-guest__primary:hover { background: #1d7a4e; }
            .notfound-guest__secondary { min-height: 40px; padding: 8px 18px; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; color: #1f2937; font-size: 14px; font-weight: 600; font-family: inherit; cursor: pointer; }
            .notfound-guest__secondary:hover { background: #f3f4f6; }
        </style>
    </head>
    <body>
        <main class="notfound-guest" data-testid="not-found-page">
            <p class="notfound-guest__brand">Mills Smart Log</p>
            <p class="notfound-guest__big" aria-hidden="true">404</p>
            <h1 class="notfound-guest__title">Halaman Tidak Ditemukan</h1>
            <p class="notfound-guest__text">
                Halaman yang Anda buka tidak ada, sudah dipindahkan, atau alamatnya salah ketik.
            </p>
            <code class="notfound-guest__path" data-testid="not-found-path">{{ $requestedPath }}</code>
            <div class="notfound-guest__actions">
                <a href="{{ route('home') }}" class="notfound-guest__primary" data-testid="not-found-home">Ke Beranda</a>
                <button type="button" class="notfound-guest__secondary" data-testid="not-found-back" onclick="history.length > 1 ? history.back() : window.location.assign('{{ route('home') }}')">Halaman Sebelumnya</button>
            </div>
        </main>
    </body>
    </html>
@endif
