{{--
    Halaman 403 ramah pengguna — menggantikan halaman bawaan Laravel
    "403 FORBIDDEN" (bahasa Inggris, tanpa navigasi) yang dulu muncul setiap
    kali EnsureRole::forbidden() menolak sebuah rute web. User yang sudah
    login tetap melihat shell aplikasi (sidebar yang sudah difilter per
    peran + tombol Logout di header), jadi selalu ada jalan keluar. User
    tanpa sesi (jarang — 'auth' biasanya menjawab lebih dulu) mendapat kartu
    sederhana dengan tautan ke Login.
--}}
@php
    $homeUrl = auth()->check() ? app(\App\Services\AuthService::class)->redirectFor(auth()->user()->role->value) : null;
@endphp
@auth
    <x-layouts.app title="Akses Ditolak">
        <x-slot:styles>
            <style>
                .forbidden-card {
                    max-width: 560px;
                    margin: 48px auto 0;
                    padding: 32px 28px;
                    background: #fff;
                    border: 1px solid var(--color-border);
                    border-radius: 10px;
                    text-align: center;
                }

                .forbidden-card__code {
                    margin: 0 0 8px;
                    font-size: 13px;
                    font-weight: 700;
                    letter-spacing: 0.08em;
                    color: var(--color-text-muted);
                }

                .forbidden-card__title {
                    margin: 0 0 12px;
                    font-size: 20px;
                    font-weight: 700;
                    color: var(--color-text);
                }

                .forbidden-card__text {
                    margin: 0 0 24px;
                    font-size: 14px;
                    line-height: 1.5;
                    color: var(--color-text-muted);
                }

                .forbidden-card__actions {
                    display: flex;
                    flex-wrap: wrap;
                    justify-content: center;
                    gap: 12px;
                }

                .forbidden-card__primary {
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

                .forbidden-card__primary:hover {
                    background: var(--color-brand-hover);
                }

                .forbidden-card__secondary {
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

                .forbidden-card__secondary:hover {
                    background: #f3f4f6;
                }
            </style>
        </x-slot:styles>

        <div class="forbidden-card" data-testid="forbidden-page">
            <p class="forbidden-card__code">403 · Forbidden</p>
            <h2 class="forbidden-card__title">Akses Ditolak</h2>
            <p class="forbidden-card__text">
                Peran Anda tidak memiliki akses ke halaman ini. Gunakan menu di samping
                untuk membuka halaman yang tersedia untuk Anda, atau keluar dan masuk
                dengan akun lain.
            </p>
            <div class="forbidden-card__actions">
                @if ($homeUrl)
                    <a href="{{ url($homeUrl) }}" class="forbidden-card__primary" data-testid="forbidden-home">Kembali ke Beranda</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="forbidden-card__secondary" data-testid="forbidden-logout">Logout</button>
                </form>
            </div>
        </div>
    </x-layouts.app>
@else
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Akses Ditolak — Mills Smart Log</title>
        <style>
            body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f3f4f6; font-family: Inter, system-ui, sans-serif; color: #1f2937; }
            .forbidden-guest { max-width: 420px; margin: 16px; padding: 32px 28px; background: #fff; border: 1px solid #d1d5db; border-radius: 10px; text-align: center; }
            .forbidden-guest h1 { margin: 0 0 12px; font-size: 20px; }
            .forbidden-guest p { margin: 0 0 24px; font-size: 14px; color: #6b7280; }
            .forbidden-guest a { color: #249360; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="forbidden-guest" data-testid="forbidden-page">
            <p>403 · Forbidden</p>
            <h1>Akses Ditolak</h1>
            <p>Anda tidak memiliki akses ke halaman ini.</p>
            <a href="{{ route('login') }}">Masuk ke Mills Smart Log</a>
        </div>
    </body>
    </html>
@endauth
