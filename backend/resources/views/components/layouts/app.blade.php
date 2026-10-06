@props(['title', 'heading' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — Mills Smart Log</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @livewireStyles
    <x-searchable-select-assets />
    <x-filter-assets />

    {{-- Token desain + kosakata komponen bersama, dipasang 2026-10-06.
         URUTANNYA MENENTUKAN dan jangan ditukar:
           1. design-tokens  — WAJIB di atas blok <style> di bawah. Lima nama token ada di
              kedua tempat dan satu di antaranya berbeda nilai (--color-border: #E3E3E3 di
              sini vs #d1d5db di :root bawah). Dengan urutan ini :root bawah yang menang,
              sehingga tidak ada nilai yang sekarang bekerja ikut berubah.
           2. components      — memakai 35 token dari (1); tanpa (1) browser membuang setiap
              deklarasinya dan kelas-kelasnya tampil tanpa gaya. --}}
    @include('partials.design-tokens')
    @include('partials.components-styles')

    <style>
        /* Shared page shell (sidebar + header) — single source of truth for
           every screen in resources/views/. Design tokens per uiux-spec v5
           (layout.adaptation, revised 2026-09-09): brand #249360, Inter.
           Inlined here (no frontend build pipeline in this backend scaffold
           — see implementation_notes on the screens that migrated to this
           component) rather than an external stylesheet. Page-specific
           styles stay in each page's own view, passed via the `styles`
           slot below.

           Three-tier responsive shell, mirrors
           .asdlc/generated/1-foundation/uiux-spec/assets/shell.css:
             - Desktop (>=1024px): sidebar 240px, icon + label
             - Tablet  (768–1024px): sidebar collapses to 64px icon-only
             - Phone   (<768px): sidebar hidden, becomes an off-canvas
               drawer (240px, icon + label) opened via a hamburger button
               (44px touch target) in the header, closed via backdrop tap */
        :root {
            --color-brand: #249360;
            --color-brand-hover: #1d7a4e;
            --color-text: #1f2937;
            --color-text-muted: #6b7280;
            --color-border: #d1d5db;
            --radius-input: 6px;
            --sidebar-width: 240px;
            --sidebar-collapsed-width: 64px;

            /* Sidebar PUTIH, bukan #F7F7F7 milik design-tokens, atas umpan
               balik user 2026-10-06: "bisa sedikit dibedakan background pada
               content dan sidebar".
               Sebelumnya sidebar #F7F7F7 di atas body #f3f4f6 — selisihnya
               hanya empat satuan dan praktis tidak terlihat; yang memisahkan
               keduanya cuma border 1px. Putih memberi panel yang benar-benar
               terbaca sebagai bidang tersendiri sambil tetap terang, dan
               kontras teksnya justru NAIK: teks #1f2937 14,68:1 (dari
               13,70:1), nav muted #6b7280 4,83:1 (dari 4,51:1), aktif brand
               #249360 3,88:1 (dari 3,62:1) — ketiganya di atas ambang 3:1.
               Kartu konten juga putih, tetapi ia duduk di atas body kelabu
               dengan border dan shadow sendiri, jadi keduanya tidak menyatu.
               Didefinisikan DI SINI, sesudah partials.design-tokens
               disertakan, supaya nilai ini yang menang tanpa menyunting
               berkas token bersama.
               (Jangan menulis nama direktif Blade ber-@ di dalam komentar
               CSS: Blade tetap mengompilasinya walau ia berada di dalam
               komentar, dan hasilnya PHP yang rusak — persis itu yang
               menjatuhkan seluruh aplikasi saat komentar ini pertama
               ditulis.) */
            --color-sidebar-bg: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            background: #f3f4f6;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--color-text);
        }

        .shell-sidebar {
            width: var(--sidebar-width);
            flex-shrink: 0;
            /* Sidebar TERANG sejak 2026-10-06 (keputusan user), mengikuti
               uiux-spec assets/shell.css: background var(--color-sidebar-bg)
               = --color-surface = #F7F7F7, dipisahkan dari konten oleh
               border-right 1px. BORDER ITULAH PEMISAHNYA, bukan warnanya:
               selisih sidebar-vs-body hanya 1,03:1 (dan 1,07:1 pun di tema
               shell.css aslinya), jadi tanpa border keduanya menyatu.
               Itu juga sebabnya body TIDAK diubah ke putih — tidak ada
               gunanya. Kontras terukur, semuanya di atas ambang 3:1 yang
               ditetapkan uiux-spec.accessibility: teks #1f2937 13,70:1,
               nav muted #6b7280 4,51:1, aktif brand #249360 3,62:1.
               (Sidebar gelap sebelumnya 16,98:1 — turun, tetap lolos.) */
            background: var(--color-sidebar-bg, #f7f7f7);
            color: var(--color-text);
            border-right: 1px solid var(--color-border);
            padding: 24px 16px;
            overflow-y: auto;
            transition: width 0.15s, transform 0.2s ease-in-out;
        }

        .shell-sidebar__brand {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 24px;
            white-space: nowrap;
            overflow: hidden;
        }

        .shell-sidebar__nav {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .shell-sidebar__nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: var(--radius-input);
            color: var(--color-text-muted);
            text-decoration: none;
            font-size: 14px;
            min-height: 44px;
        }

        /* hover dan active DIPISAH sejak sidebar jadi terang — shell.css
           membedakannya (brand 8% vs 12%, dan active berwarna brand serta
           lebih berat). Digabung seperti sebelumnya, butir aktif tidak
           dapat dibedakan dari butir yang sedang disentuh kursor.
           color-mix() milik shell.css diganti rgba() eksplisit: hasilnya
           identik dan tidak bergantung dukungan browser. */
        .shell-sidebar__nav a:hover {
            background: rgba(36, 147, 96, 0.08);
            color: var(--color-text);
        }

        .shell-sidebar__nav a.active {
            background: rgba(36, 147, 96, 0.12);
            color: var(--color-brand);
            font-weight: 500;
        }

        .shell-sidebar__nav svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        /* Label boleh turun baris (bukan dipotong "Production Process A…"):
           nama menu panjang tetap terbaca utuh di sidebar 240px. */
        .shell-sidebar__nav .label {
            white-space: normal;
            overflow-wrap: anywhere;
            line-height: 1.3;
        }

        /* ---------- Collapsible nav group ----------
           Uses native <details>/<summary> — no JS, keyboard-operable for free,
           same element the daily mill report already uses for its table toggle.
           The group is rendered with `open` from Blade whenever one of its
           children is the current route, so landing on one of its child pages shows
           the group already expanded rather than hiding where you are. */
        .shell-nav-group > summary {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: var(--radius-input);
            color: var(--color-text-muted);
            font-size: 14px;
            min-height: 44px;
            cursor: pointer;
            user-select: none;
            list-style: none;
        }

        /* Hide the default disclosure triangle in both engine families —
           the chevron below is the affordance instead. */
        .shell-nav-group > summary::-webkit-details-marker {
            display: none;
        }

        .shell-nav-group > summary::marker {
            content: '';
        }

        .shell-nav-group > summary:hover,
        .shell-nav-group > summary:focus-visible {
            background: rgba(36, 147, 96, 0.08);
            color: var(--color-text);
        }

        .shell-nav-group__chevron {
            margin-left: auto;
            width: 14px !important;
            height: 14px !important;
            opacity: 0.75;
            transition: transform 0.15s ease;
        }

        .shell-nav-group[open] > summary .shell-nav-group__chevron {
            transform: rotate(90deg);
        }

        .shell-nav-group__items {
            list-style: none;
            margin: 4px 0 0 19px;
            padding: 0 0 0 14px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            border-left: 1px solid var(--color-border);
        }

        .shell-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .shell-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 24px;
            background: #fff;
            border-bottom: 1px solid var(--color-border);
        }

        .shell-header__start {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .shell-header__title {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .shell-header__user-area {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .shell-header__user {
            font-size: 14px;
            color: var(--color-text-muted);
        }

        .shell-header__logout {
            padding: 6px 14px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-input);
            background: #fff;
            color: var(--color-text);
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
        }

        .shell-header__logout:hover {
            background: #f3f4f6;
        }

        /* padding-bottom 104px = tombol chatbot melayang (56px + jarak 24px
           dari tepi bawah) + 24px ruang: baris/tombol terakhir halaman
           (mis. "Hapus" di baris tabel paling bawah) selalu bisa digulir ke
           atas tombol itu, tidak pernah tertutup. */
        .shell-body {
            flex: 1;
            padding: 32px 24px 104px;
            overflow-x: hidden;
        }

        .shell-hamburger {
            display: none;
            align-items: center;
            justify-content: center;
            min-width: 44px;
            min-height: 44px;
            background: transparent;
            border: none;
            border-radius: var(--radius-input);
            color: var(--color-text);
            cursor: pointer;
            flex-shrink: 0;
        }

        .shell-hamburger:hover {
            background: #f3f4f6;
        }

        .shell-hamburger svg {
            width: 22px;
            height: 22px;
        }

        .shell-backdrop {
            display: none;
        }

        /* ---------- Tablet (768px–1024px): sidebar collapses to icon-only ---------- */
        @media (max-width: 1024px) {
            .shell-sidebar {
                width: var(--sidebar-collapsed-width);
                padding: 24px 12px;
            }

            .shell-sidebar__brand .label,
            .shell-sidebar__nav .label {
                display: none;
            }

            .shell-sidebar__nav a {
                justify-content: center;
            }

            /* Icon-only rail: the group header centres like every other item,
               the chevron and the indent guide would only add noise with no
               label to attach to. The group still opens on click, revealing
               its four child icons. */
            .shell-nav-group > summary {
                justify-content: center;
            }

            .shell-nav-group__chevron {
                display: none;
            }

            .shell-nav-group__items {
                margin-left: 0;
                padding-left: 0;
                border-left: none;
            }
        }

        /* ---------- Phone (<768px): sidebar becomes an off-canvas drawer ---------- */
        @media (max-width: 767px) {
            .shell-hamburger {
                display: inline-flex;
            }

            .shell-sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                width: var(--sidebar-width);
                padding: 24px 16px;
                transform: translateX(-100%);
                z-index: 40;
                box-shadow: 0 10px 24px rgba(0, 0, 0, 0.16);
            }

            .shell-sidebar.is-open {
                transform: translateX(0);
            }

            /* full icon + label restored inside the drawer (not icon-only like tablet) */
            .shell-sidebar__brand .label,
            .shell-sidebar__nav .label {
                display: inline;
            }

            .shell-sidebar__nav a {
                justify-content: flex-start;
            }

            /* Drawer shows labels again, so the group gets its chevron and
               indent guide back too. */
            .shell-nav-group > summary {
                justify-content: flex-start;
            }

            .shell-nav-group__chevron {
                display: inline;
            }

            .shell-nav-group__items {
                margin-left: 19px;
                padding-left: 14px;
                border-left: 1px solid var(--color-border);
            }

            .shell-backdrop {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.4);
                z-index: 30;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.2s ease-in-out;
            }

            .shell-backdrop.is-visible {
                opacity: 1;
                pointer-events: auto;
            }

            .shell-body {
                padding: 20px 16px 104px;
            }

            .shell-header {
                padding: 12px 16px;
            }
        }
    </style>
    {{ $styles ?? '' }}
</head>
<body>
    {{-- Bar progres + live region + kelas loading bersama (.ld-*). --}}
    <x-loading-assets />

    <div class="shell-backdrop" id="shell-backdrop"></div>

    <aside class="shell-sidebar" id="shell-sidebar">
        <p class="shell-sidebar__brand"><span class="label">Mills Smart Log</span></p>
        <ul class="shell-sidebar__nav">
            @if (\App\Support\RouteAccess::allows('operator.home'))
            <li><a href="{{ route('operator.home') }}"{!! request()->routeIs('operator.home') ? ' class="active"' : '' !!}><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 10 3l7 6.5"/><path d="M5 8v9h10V8"/></svg><span class="label">Beranda</span></a></li>
            @endif
            @if (\App\Support\RouteAccess::allows('dashboard'))
            <li><a href="{{ route('dashboard') }}"{!! request()->routeIs('dashboard') ? ' class="active"' : '' !!}><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="7" height="7" rx="1"/><rect x="11" y="2" width="7" height="7" rx="1"/><rect x="2" y="11" width="7" height="7" rx="1"/><rect x="11" y="11" width="7" height="7" rx="1"/></svg><span class="label">Dashboard</span></a></li>
            @endif
            @if (\App\Support\RouteAccess::allows('production-process-activity'))
            <li><a href="{{ route('production-process-activity') }}"{!! request()->routeIs('production-process-activity') ? ' class="active"' : '' !!}><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="2 11 6 11 8 5 12 16 14 11 18 11"/></svg><span class="label">Production Process Activity</span></a></li>
            @endif
            @if (\App\Support\RouteAccess::allows('reports.management'))
            <li><a href="{{ route('reports.management') }}"{!! request()->routeIs('reports.management') ? ' class="active"' : '' !!}><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="17" x2="4" y2="9"/><line x1="10" y1="17" x2="10" y2="3"/><line x1="16" y1="17" x2="16" y2="12"/></svg><span class="label">Laporan Manajemen</span></a></li>
            @endif
            @if (\App\Support\RouteAccess::allows('reports.stations'))
            <li><a href="{{ route('reports.stations') }}"{!! request()->routeIs('reports.stations', 'reports.sterilizer', 'reports.cages-track', 'reports.boiler-room', 'reports.clarification', 'reports.storage-tank') ? ' class="active"' : '' !!}><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="2.5" width="14" height="15" rx="2"/><path d="M6.5 13.5v-3M10 13.5v-6M13.5 13.5v-4.5"/></svg><span class="label">Laporan Stasiun</span></a></li>
            @endif
            @if (\App\Support\RouteAccess::allows('master-data.tree-view'))
            <li><a href="{{ route('master-data.tree-view') }}"{!! request()->routeIs('master-data.tree-view') ? ' class="active"' : '' !!}><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 5.5A1.5 1.5 0 0 1 3.5 4h3.6l1.4 1.7h7A1.5 1.5 0 0 1 17 7.2v7.3A1.5 1.5 0 0 1 15.5 16h-12A1.5 1.5 0 0 1 2 14.5v-9z"/></svg><span class="label">Struktur Mills</span></a></li>
            @endif
            {{-- Corporate -> Company -> Business Unit -> Production Line are one
                 hierarchy and were four separate sidebar entries; folded into a single
                 collapsible group to shorten the rail. Opens automatically when the
                 current route is one of them, so the active page is never hidden. --}}
            {{-- shell-nav-group-wrap SENGAJA tidak punya aturan CSS, dan itu sudah
                 diperiksa (nol selektor yang cocok di seluruh resources/ dan
                 public/), bukan terlupakan. Ia penanda semantik murni yang
                 membedakan <li> pembungkus grup dari <li> item biasa: seluruh
                 gayanya dibawa .shell-nav-group pada <details> di dalamnya, yang
                 punya 14 aturan sendiri. Dinyatakan di sini supaya pemeriksaan
                 "tiap kelas yang dipakai markup harus punya definisi" tidak
                 menemukannya lagi sebagai cacat — dan supaya siapa pun yang nanti
                 butuh menata <li> ini tahu hook-nya sudah tersedia. --}}
            @if (\App\Support\RouteAccess::allowsAny(['master-data.corporates', 'master-data.companies', 'master-data.business-units', 'master-data.production-lines']))
            <li class="shell-nav-group-wrap">
                <details class="shell-nav-group"{!! request()->routeIs('master-data.corporates', 'master-data.companies', 'master-data.production-lines', 'master-data.business-units') ? ' open' : '' !!}>
                    <summary>
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="7.5" y="2" width="5" height="4" rx="1"/><rect x="2" y="14" width="5" height="4" rx="1"/><rect x="13" y="14" width="5" height="4" rx="1"/><path d="M10 6v4M4.5 14v-2a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v2"/></svg>
                        <span class="label">Struktur Organisasi</span>
                        <svg class="shell-nav-group__chevron" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="7 4 13 10 7 16"/></svg>
                    </summary>
                    <ul class="shell-nav-group__items">
                    @if (\App\Support\RouteAccess::allows('master-data.corporates'))
                    <li><a href="{{ route('master-data.corporates') }}"{!! request()->routeIs('master-data.corporates') ? ' class="active"' : '' !!}><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="10" height="16" rx="1"/><line x1="8" y1="6" x2="8" y2="6"/><line x1="12" y1="6" x2="12" y2="6"/><line x1="8" y1="10" x2="8" y2="10"/><line x1="12" y1="10" x2="12" y2="10"/><line x1="8" y1="14" x2="8" y2="14"/><line x1="12" y1="14" x2="12" y2="14"/></svg><span class="label">Kelola Corporate</span></a></li>
                    @endif
                    @if (\App\Support\RouteAccess::allows('master-data.companies'))
                    <li><a href="{{ route('master-data.companies') }}"{!! request()->routeIs('master-data.companies') ? ' class="active"' : '' !!}><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="6" height="11"/><rect x="10" y="2" width="7" height="16"/></svg><span class="label">Kelola Company</span></a></li>
                    @endif
                    @if (\App\Support\RouteAccess::allows('master-data.business-units'))
                    <li><a href="{{ route('master-data.business-units') }}"{!! request()->routeIs('master-data.business-units') ? ' class="active"' : '' !!}><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="16" height="10" rx="1.5"/><path d="M7 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/><line x1="2" y1="12" x2="18" y2="12"/></svg><span class="label">Kelola Business Unit</span></a></li>
                    @endif
                    @if (\App\Support\RouteAccess::allows('master-data.production-lines'))
                    <li><a href="{{ route('master-data.production-lines') }}"{!! request()->routeIs('master-data.production-lines') ? ' class="active"' : '' !!}><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="2" y1="10" x2="14" y2="10"/><polyline points="10 6 14 10 10 14"/></svg><span class="label">Kelola Production Line</span></a></li>
                    @endif
                    </ul>
                </details>
            </li>
            @endif
            @if (\App\Support\RouteAccess::allows('master-data.stations'))
            <li><a href="{{ route('master-data.stations') }}"{!! request()->routeIs('master-data.stations') ? ' class="active"' : '' !!}><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.5 6-10a6 6 0 1 0-12 0c0 4.5 6 10 6 10z"/><circle cx="10" cy="8" r="2"/></svg><span class="label">Kelola Station</span></a></li>
            @endif
            @if (\App\Support\RouteAccess::allows('master-data.machinery'))
            <li><a href="{{ route('master-data.machinery') }}"{!! request()->routeIs('master-data.machinery', 'master-data.machinery-groups') ? ' class="active"' : '' !!}><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="3"/><path d="M10 2v2M10 16v2M18 10h-2M4 10H2M15.5 4.5l-1.4 1.4M5.9 14.1l-1.4 1.4M15.5 15.5l-1.4-1.4M5.9 5.9 4.5 4.5"/></svg><span class="label">Kelola Mesin</span></a></li>
            @endif
            @if (\App\Support\RouteAccess::allows('master-data.periods'))
            <li><a href="{{ route('master-data.periods') }}"{!! request()->routeIs('master-data.periods') ? ' class="active"' : '' !!}><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4" width="15" height="13" rx="2"/><path d="M2.5 8h15M6.5 2.5v3M13.5 2.5v3"/></svg><span class="label">Kelola Periode Pelaporan</span></a></li>
            @endif
            @if (\App\Support\RouteAccess::allows('mill-settings'))
            <li><a href="{{ route('mill-settings') }}"{!! request()->routeIs('mill-settings') ? ' class="active"' : '' !!}><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 1-5.4 5.4L4 17l-1-1 5.3-5.3a4 4 0 0 1 5.4-5.4l-2.4 2.4 1 1 2.4-2.4z"/></svg><span class="label">Mills Setting</span></a></li>
            @endif
            @if (\App\Support\RouteAccess::allows('users.index'))
            <li><a href="{{ route('users.index') }}"{!! request()->routeIs('users.index') ? ' class="active"' : '' !!}><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="3"/><path d="M2 18c0-3 2-5 5-5s5 2 5 5"/><circle cx="15" cy="8" r="2.3"/><path d="M13 12c2 0 5 1 5 6"/></svg><span class="label">Kelola User & Role</span></a></li>
            @endif
            @if (\App\Support\RouteAccess::allows('settings.password'))
            <li><a href="{{ route('settings.password') }}"{!! request()->routeIs('settings.password') ? ' class="active"' : '' !!}><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="9" width="12" height="9" rx="1.5"/><path d="M7 9V6a3 3 0 0 1 6 0v3"/></svg><span class="label">Ganti Password</span></a></li>
            @endif
        </ul>
    </aside>

    <div class="shell-main">
        <header class="shell-header">
            <div class="shell-header__start">
                <button type="button" class="shell-hamburger" id="shell-hamburger" aria-label="Buka menu navigasi" aria-expanded="false" aria-controls="shell-sidebar">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><line x1="2" y1="5" x2="18" y2="5"/><line x1="2" y1="10" x2="18" y2="10"/><line x1="2" y1="15" x2="18" y2="15"/></svg>
                </button>
                <h1 class="shell-header__title">{{ $heading ?? $title }}</h1>
            </div>
            @auth
                <div class="shell-header__user-area">
                    <span class="shell-header__user">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="shell-header__logout" data-testid="logout-button">Logout</button>
                    </form>
                </div>
            @endauth
        </header>

        <main class="shell-body">
            {{ $slot }}
        </main>
    </div>

    <x-chatbot-widget />

    <script>
        (function () {
            var sidebar = document.getElementById('shell-sidebar');
            var backdrop = document.getElementById('shell-backdrop');
            var hamburger = document.getElementById('shell-hamburger');
            if (!sidebar || !backdrop || !hamburger) return;

            function openDrawer() {
                sidebar.classList.add('is-open');
                backdrop.classList.add('is-visible');
                hamburger.setAttribute('aria-expanded', 'true');
            }

            function closeDrawer() {
                sidebar.classList.remove('is-open');
                backdrop.classList.remove('is-visible');
                hamburger.setAttribute('aria-expanded', 'false');
            }

            hamburger.addEventListener('click', function () {
                sidebar.classList.contains('is-open') ? closeDrawer() : openDrawer();
            });
            backdrop.addEventListener('click', closeDrawer);
            window.addEventListener('resize', function () {
                if (window.innerWidth >= 768) closeDrawer();
            });
        })();
    </script>

    @livewireScripts
</body>
</html>
