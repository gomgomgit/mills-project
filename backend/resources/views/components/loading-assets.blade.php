{{--
    x-loading-assets — indikator loading bersama untuk SELURUH layar web
    (2026-10-05, "loading state ketika melakukan action yang butuh waktu
    untuk read/write data di semua tempat").

    Dirender SEKALI tepat setelah <body> di components/layouts/app.blade.php
    dan auth/login.blade.php. Isinya tiga hal:

    1. Bar progres tipis di tepi atas layar (.ld-progress). Muncul bila ADA
       request Livewire (/livewire/update) yang belum selesai lebih dari
       150 ms — request cepat tidak berkedip. Dipasang lewat hook "request"
       Livewire, jadi tiap layar otomatis tercakup tanpa markup tambahan.
       (Navigasi wire:navigate punya bar bawaan Livewire sendiri — warnanya
       diseragamkan ke brand lewat config/livewire.php.)

    2. Kelas bersama yang dipakai markup per aksi:
         .ld-spinner           — lingkaran berputar 1em, selalu aria-hidden
         .ld-label             — pembungkus teks tombol (ikon + teks sebaris)
         .ld-label--idle       — teks normal ("Simpan")
         .ld-label--busy       — teks sibuk ("Menyimpan…") + spinner;
                                 lihat components/busy-label.blade.php
         .ld-label--js         — varian yang di-toggle JS (tautan ekspor)
         .ld-region            — area data yang bisa diredupkan
         .ld-region--busy      — area sedang memuat: isi diredupkan + spinner
                                 di tengah atas; tata letak TIDAK bergeser
                                 (hanya opacity + pseudo-element absolut)
         .ld-sr                — teks khusus pembaca layar

    3. Tautan ekspor file (a[data-export-link]) — tautan <a href> biasa ke
       route ekspor, bukan request Livewire, jadi wire:loading tidak bisa
       melihatnya. Saat diklik, JS menambahkan token acak (?_dl=…) ke href,
       menandai tautan aria-busy="true" (teks "Mengekspor…" + spinner, klik
       kedua diabaikan), lalu menunggu cookie "ms_download" bernilai token
       itu. Cookie dipasang middleware SignalDownloadReady pada respons
       ekspor APA PUN (file maupun error) — jadi status loading berakhir
       tepat saat respons unduhan tiba, dan perilaku browser (unduh / tab
       baru) tidak berubah. Batas aman 60 detik bila cookie tak pernah tiba.

    Aksesibilitas: wilayah aria-live="polite" (#ld-live) mengumumkan
    "Memuat…" saat bar tampil; area data memakai aria-busy; spinner selalu
    aria-hidden. prefers-reduced-motion: animasi dimatikan (bar tampil
    statis penuh, spinner berupa cincin diam).
--}}
<div class="ld-progress" id="ld-progress" aria-hidden="true"><span class="ld-progress__bar"></span></div>
<div class="ld-sr" id="ld-live" role="status" aria-live="polite"></div>

<style>
    .ld-progress {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        z-index: 2000;
        overflow: hidden;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .ld-progress--active {
        opacity: 1;
    }

    .ld-progress__bar {
        position: absolute;
        top: 0;
        bottom: 0;
        left: 0;
        width: 40%;
        background: #249360;
        border-radius: 0 2px 2px 0;
        box-shadow: 0 0 6px rgba(36, 147, 96, 0.5);
        animation: ld-progress-slide 1.1s ease-in-out infinite;
    }

    @keyframes ld-progress-slide {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(260%); }
    }

    .ld-spinner {
        display: inline-block;
        flex-shrink: 0;
        width: 1em;
        height: 1em;
        border: 2px solid currentColor;
        border-right-color: transparent;
        border-radius: 50%;
        vertical-align: -0.15em;
        animation: ld-spin 0.7s linear infinite;
    }

    @keyframes ld-spin {
        to { transform: rotate(360deg); }
    }

    .ld-label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    /* Teks sibuk tidak pernah dipotong jadi dua baris di tombol sempit. */
    .ld-label--busy {
        white-space: nowrap;
    }

    /* Varian JS (tautan ekspor): tersembunyi sampai pemiliknya aria-busy. */
    .ld-label--js {
        display: none;
    }

    [aria-busy="true"] > .ld-label--js {
        display: inline-flex;
    }

    [aria-busy="true"] > .ld-label--idle {
        display: none;
    }

    /* Tombol/tautan yang sedang memproses: kursor progres, sedikit redup. */
    a[data-export-link][aria-busy="true"] {
        pointer-events: none;
        cursor: progress;
        opacity: 0.8;
    }

    button:disabled:has(> .ld-label--busy[style*="inline-flex"]) {
        cursor: progress;
        opacity: 0.8;
    }

    .ld-region {
        position: relative;
    }

    .ld-region > * {
        transition: opacity 0.15s ease;
    }

    .ld-region--busy {
        cursor: progress;
    }

    .ld-region--busy > * {
        opacity: 0.45;
    }

    .ld-region--busy::after {
        content: '';
        position: absolute;
        z-index: 5;
        left: 50%;
        top: min(50%, 120px);
        width: 28px;
        height: 28px;
        margin: -14px 0 0 -14px;
        border: 3px solid rgba(36, 147, 96, 0.22);
        border-top-color: #249360;
        border-radius: 50%;
        /* Cakram putih pekat + bayangan halus: spinner tetap terbaca di atas
           teks/ikon apa pun di bawahnya (mis. ilustrasi empty state). */
        background: #fff;
        box-shadow: 0 0 0 8px #fff, 0 2px 12px 8px rgba(15, 23, 42, 0.08);
        animation: ld-spin 0.7s linear infinite;
    }

    .ld-sr {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    @media (prefers-reduced-motion: reduce) {
        .ld-progress__bar {
            width: 100%;
            animation: none;
        }

        .ld-spinner,
        .ld-region--busy::after {
            animation: none;
        }

        .ld-progress,
        .ld-region > * {
            transition: none;
        }
    }
</style>

<script>
    (function () {
        if (window.__msLoadingReady) return;
        window.__msLoadingReady = true;

        var SHOW_AFTER_MS = 150;
        var bar = document.getElementById('ld-progress');
        var live = document.getElementById('ld-live');
        var pending = 0;
        var showTimer = null;

        function start() {
            pending++;
            if (pending !== 1) return;
            clearTimeout(showTimer);
            showTimer = setTimeout(function () {
                if (bar) bar.classList.add('ld-progress--active');
                if (live) live.textContent = 'Memuat…';
            }, SHOW_AFTER_MS);
        }

        function done() {
            pending = Math.max(0, pending - 1);
            if (pending !== 0) return;
            clearTimeout(showTimer);
            if (bar) bar.classList.remove('ld-progress--active');
            if (live) live.textContent = '';
        }

        // Satu "start" per request Livewire; selesai saat respons tiba
        // (respond) atau saat gagal jaringan (fail) — mana yang lebih dulu.
        document.addEventListener('livewire:init', function () {
            window.Livewire.hook('request', function (request) {
                var finished = false;
                function finish() {
                    if (finished) return;
                    finished = true;
                    done();
                }
                start();
                request.respond(finish);
                request.fail(finish);
            });
        });

        // ---------- Tautan ekspor (unduhan file) ----------
        var COOKIE = 'ms_download';
        var MAX_WAIT_MS = 60000;

        function readCookie(name) {
            var parts = document.cookie ? document.cookie.split('; ') : [];
            for (var i = 0; i < parts.length; i++) {
                var eq = parts[i].indexOf('=');
                if (parts[i].slice(0, eq) === name) return decodeURIComponent(parts[i].slice(eq + 1));
            }
            return null;
        }

        function finishExport(link, timer, poll) {
            clearInterval(poll);
            clearTimeout(timer);
            link.removeAttribute('aria-busy');
            done();
        }

        document.addEventListener('click', function (event) {
            var link = event.target && event.target.closest ? event.target.closest('a[data-export-link]') : null;
            if (!link) return;
            if (link.getAttribute('aria-busy') === 'true') {
                event.preventDefault();
                return;
            }

            var token = Math.random().toString(36).slice(2, 12) + Date.now().toString(36);
            var url = new URL(link.href, window.location.href);
            url.searchParams.set('_dl', token);
            link.href = url.toString();

            link.setAttribute('aria-busy', 'true');
            start();

            var poll = setInterval(function () {
                if (readCookie(COOKIE) === token) finishExport(link, timer, poll);
            }, 200);
            var timer = setTimeout(function () { finishExport(link, timer, poll); }, MAX_WAIT_MS);
        });
    })();
</script>
