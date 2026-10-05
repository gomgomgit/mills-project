{{--
    report-styles — kosakata CSS `md-*` bersama untuk seluruh halaman
    laporan bergaya dashboard.

    Blok ini semula inline di dashboard/partials/daily-mill-report.blade.php
    (baris 542-716). Diekstrak 2026-09-23 bersama
    screen-129--laporan-sterilizer-web agar laporan periode per stasiun
    memakai bahasa visual yang sama persis dengan Daily Mill Report, tanpa
    menduplikasi 174 baris CSS.

    Isi CSS asli dipindahkan APA ADANYA — tampilan Daily Mill Report tidak
    boleh berubah sedikit pun. Kelas yang ditambahkan SETELAH ekstraksi
    (dipakai laporan periode per stasiun) berada di blok kedua di bawah,
    diberi penanda jelas, dan tidak menyentuh satu pun selector lama.

    Dipakai oleh:
      - dashboard/partials/daily-mill-report.blade.php (screen-025)
      - livewire/dashboard/laporan-sterilizer.blade.php (screen-129)
      - livewire/dashboard/laporan-stasiun.blade.php (screen-140)
      - livewire/dashboard/laporan-cages-track.blade.php (screen-130)
      - livewire/dashboard/laporan-boiler-room.blade.php (screen-131)
      - livewire/dashboard/laporan-clarification.blade.php (screen-132)
--}}
    <style>
        .md { --md-brand: #249360; --md-brand-dark: #1a6f48; --md-brand-soft: #e8f5ee; --md-ink: #0f172a; --md-muted: #64748b; --md-line: #e2e8f0; --md-card: #ffffff;
              display: flex; flex-direction: column; gap: 20px; margin-bottom: 32px; color: var(--md-ink); }
        .md small { font-weight: 500; color: var(--md-muted); }
        /* Pembungkus area hasil laporan (target loading .ld-region): meneruskan
           kolom + jarak .md supaya membungkusnya tidak mengubah tata letak. */
        .md-body { display: flex; flex-direction: column; gap: inherit; min-width: 0; }

        .md-hero { position: relative; overflow: hidden; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 16px;
                   padding: 24px 28px; border-radius: 16px; color: #fff;
                   background: radial-gradient(120% 140% at 100% 0%, #3fb57c 0%, rgba(63,181,124,0) 55%), linear-gradient(135deg, #1a6f48 0%, #249360 60%, #2ea56d 100%); }
        .md-hero::after { content: ''; position: absolute; right: -60px; bottom: -80px; width: 240px; height: 240px; border-radius: 50%; border: 36px solid rgba(255,255,255,.07); }
        .md-hero__eyebrow { margin: 0 0 6px; font-size: 12px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; opacity: .85; }
        .md-hero__title { margin: 0; font-size: 26px; font-weight: 700; letter-spacing: -.01em; }
        .md-hero__subtitle { margin: 6px 0 0; font-size: 14px; opacity: .85; }
        .md-hero__meta { position: relative; z-index: 1; display: flex; flex-wrap: wrap; gap: 8px; }
        /* Pembungkus kolom kiri hero (eyebrow + title + subtitle). Ia dipakai
           empat blade laporan (weighbridge, clarification, boiler-room,
           storage-tank) namun sampai 2026-10-01 TIDAK punya satu pun aturan —
           kelas yang dipakai markup tanpa definisi. Bekerja hanya karena
           .md-hero sudah flex + space-between, tetapi sebagai flex item tanpa
           min-width:0 ia menolak menyusut di bawah lebar isinya, sehingga nama
           mill atau periode yang panjang mendorong .md-hero__meta keluar alih-alih
           membungkus. Satu aturan ini memperbaiki keempat layar sekaligus dan
           menutup kelasnya. */
        .md-hero__main { position: relative; z-index: 1; min-width: 0; }
        .md-chip { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 999px; font-size: 13px; font-weight: 600; }
        .md-chip svg { width: 15px; height: 15px; }
        .md-chip--date { background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.28); }
        .md-chip--dummy { background: #fef3c7; color: #92400e; text-transform: uppercase; letter-spacing: .05em; font-size: 11px; }

        .md-kpis { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 14px; }
        .md-kpi { background: var(--md-card); border: 1px solid var(--md-line); border-radius: 14px; padding: 16px; box-shadow: 0 1px 2px rgba(15,23,42,.04); transition: box-shadow .15s, transform .15s; min-width: 0; }
        .md-kpi:hover { box-shadow: 0 8px 20px rgba(15,23,42,.08); transform: translateY(-1px); }
        .md-kpi__top { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .md-kpi__label { flex: 1; font-size: 13px; font-weight: 600; color: var(--md-muted); }
        /* Penanda periode: setiap angka kartu menyebut sendiri ia Today / MTD / YTD. */
        .md-kpi__period { flex-shrink: 0; padding: 2px 6px; border-radius: 999px; background: #f1f5f9;
            font-size: 10px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--md-muted); }
        .md-kpi__icon { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 10px; background: var(--md-brand-soft); color: var(--md-brand); flex-shrink: 0; }
        .md-kpi__icon svg { width: 18px; height: 18px; }
        .md-kpi__value { margin: 10px 0 2px; font-size: 24px; font-weight: 700; letter-spacing: -.02em; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .md-kpi__value span { font-size: 13px; font-weight: 600; color: var(--md-muted); }
        .md-kpi__meta { margin: 0 0 10px; font-size: 12px; color: var(--md-muted); }
        .md-kpi__foot { margin: 6px 0 0; font-size: 12px; color: var(--md-muted); }
        /* Baris deviasi menempel di bawah baris progres — satu blok barometer, bukan dua. */
        .md-kpi__foot--trend { margin-top: 4px; }

        .md-trend { display: inline-flex; align-items: center; gap: 2px; padding: 1px 7px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .md-trend--up { background: #dcfce7; color: #15803d; }
        .md-trend--up::before { content: '▲'; font-size: 8px; }
        .md-trend--down { background: #fee2e2; color: #b91c1c; }
        .md-trend--down::before { content: '▼'; font-size: 8px; }
        .md-trend--flat { background: #e0f2fe; color: #0369a1; }

        .md-bar { position: relative; height: 8px; border-radius: 999px; background: #edf2f0; }
        .md-bar > span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #2ea56d, #249360); }
        .md-bar > em { position: absolute; top: -4px; bottom: -4px; width: 2px; background: #0f172a; opacity: .45; border-radius: 2px; }
        .md-bar--lg { height: 12px; }

        .md-row { display: grid; gap: 20px; align-items: stretch; }
        .md-row--2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .md-row--2-1 { grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); }
        .md-card { background: var(--md-card); border: 1px solid var(--md-line); border-radius: 16px; padding: 20px; box-shadow: 0 1px 2px rgba(15,23,42,.04); min-width: 0; }
        .md-card__head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 4px 12px; margin-bottom: 16px; }
        .md-card__head h3 { margin: 0; font-size: 16px; font-weight: 700; }
        .md-card__hint { font-size: 12px; color: var(--md-muted); }

        .md-budget { display: flex; flex-direction: column; gap: 16px; }
        .md-budget__row { display: grid; grid-template-columns: 1fr auto; grid-template-areas: 'label pct' 'bar bar'; gap: 6px 12px; }
        .md-budget__label { grid-area: label; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 4px 12px; font-size: 14px; font-weight: 600; }
        .md-budget__nums { font-size: 13px; font-weight: 500; color: var(--md-muted); font-variant-numeric: tabular-nums; }
        .md-budget__nums strong { color: var(--md-ink); }
        .md-budget__row .md-bar { grid-area: bar; }
        .md-budget__pct { grid-area: pct; font-size: 14px; font-weight: 700; color: var(--md-brand-dark); font-variant-numeric: tabular-nums; }
        .md-budget__row--total { padding-top: 14px; border-top: 1px dashed var(--md-line); }
        .md-budget__row--total .md-bar > span { background: linear-gradient(90deg, #1a6f48, #0f4f33); }
        .md-note { margin: 0; font-size: 12px; color: var(--md-muted); display: flex; align-items: center; gap: 6px; }
        .md-note__tick { display: inline-block; width: 2px; height: 12px; background: #0f172a; opacity: .45; }

        .md-donut-wrap { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; justify-content: center; }
        .md-donut { width: 160px; height: 160px; border-radius: 50%; display: grid; place-items: center; flex-shrink: 0; }
        .md-donut__hole { width: 104px; height: 104px; border-radius: 50%; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .md-donut__hole strong { font-size: 22px; font-variant-numeric: tabular-nums; }
        .md-donut__hole span { font-size: 11px; color: var(--md-muted); }
        .md-legend { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 170px; }
        .md-legend li { display: flex; align-items: center; gap: 8px; font-size: 13px; }
        .md-legend i { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
        .md-legend span { flex: 1; color: #334155; }
        .md-legend b { font-variant-numeric: tabular-nums; white-space: nowrap; }
        /* Satuan menempel pada angkanya tapi tidak ikut tebal — angka tetap yang dibaca duluan. */
        .md-legend b small { font-size: 11px; font-weight: 600; color: var(--md-muted); }
        .md-legend--inline { flex-direction: row; flex-wrap: wrap; gap: 8px 16px; margin-top: 4px; }
        .md-legend--inline span { flex: none; font-size: 12px; color: var(--md-muted); }

        .md-lines { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .md-line { border: 1px solid var(--md-line); border-radius: 12px; padding: 14px; background: linear-gradient(180deg, #fbfdfc, #fff); }
        .md-line__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 8px; }
        .md-pill { display: inline-block; padding: 3px 10px; border-radius: 999px; background: var(--md-brand-soft); color: var(--md-brand-dark); font-size: 12px; font-weight: 700; white-space: nowrap; }
        .md-line__stats { margin: 0 0 12px; display: grid; gap: 8px; }
        .md-line__stats div { display: flex; justify-content: space-between; gap: 8px; }
        .md-line__stats dt { font-size: 13px; color: var(--md-muted); }
        .md-line__stats dd { margin: 0; font-size: 14px; font-weight: 700; font-variant-numeric: tabular-nums; }

        .md-hours { display: flex; flex-direction: column; gap: 18px; }
        .md-hours__label { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 14px; }
        .md-hours__label span { font-size: 12px; color: var(--md-muted); font-weight: 600; }
        .md-stack { display: flex; height: 30px; border-radius: 8px; overflow: hidden; background: #edf2f0; }
        .md-stack span { display: grid; place-items: center; color: #fff; font-size: 12px; font-weight: 700; font-variant-numeric: tabular-nums; overflow: hidden; }

        .md-tanks { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .md-tank { display: flex; gap: 14px; padding: 14px; border: 1px solid var(--md-line); border-radius: 12px; min-width: 0; }
        .md-tank--warn { border-color: #fcd34d; background: #fffdf5; }
        .md-tank__gauge { position: relative; width: 34px; height: 76px; border-radius: 8px; background: #edf2f0; overflow: hidden; flex-shrink: 0; }
        .md-tank__gauge span { position: absolute; left: 0; right: 0; bottom: 0; background: linear-gradient(180deg, #3fb57c, #1a6f48); }
        .md-tank--warn .md-tank__gauge span { background: linear-gradient(180deg, #fbbf24, #d97706); }
        .md-tank__gauge b { position: absolute; inset: 0; display: grid; place-items: center; font-size: 10px; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,.35); }
        .md-tank__body { flex: 1; min-width: 0; }
        .md-tank__head { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .md-tank__head strong { font-size: 14px; }
        .md-status { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: #dcfce7; color: #15803d; }
        .md-status--warn { background: #fef3c7; color: #b45309; }
        .md-tank__stock { margin: 4px 0 8px; font-size: 18px; font-weight: 700; font-variant-numeric: tabular-nums; }
        .md-tank__params { display: flex; flex-wrap: wrap; gap: 6px; }
        .md-param { font-size: 12px; padding: 3px 8px; border-radius: 8px; background: #f1f5f9; color: #334155; white-space: nowrap; }
        .md-param b { font-variant-numeric: tabular-nums; }
        .md-param--warn { background: #fef3c7; color: #92400e; }

        .md-utils { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .md-util { display: flex; gap: 12px; align-items: flex-start; padding: 14px; border-radius: 12px; background: #f8faf9; }
        .md-util__icon { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 10px; background: #fff; color: var(--md-brand); box-shadow: 0 1px 2px rgba(15,23,42,.06); flex-shrink: 0; }
        .md-util__icon svg { width: 20px; height: 20px; }
        .md-util__label { margin: 0; font-size: 12px; color: var(--md-muted); font-weight: 600; }
        .md-util__value { margin: 2px 0; font-size: 20px; font-weight: 700; font-variant-numeric: tabular-nums; }
        .md-util__ratio { margin: 0; font-size: 12px; color: var(--md-muted); }

        .md-oer__big { margin: 0; font-size: 44px; font-weight: 700; letter-spacing: -.03em; color: var(--md-brand-dark); line-height: 1; font-variant-numeric: tabular-nums; }
        .md-oer__big span { font-size: 22px; }
        .md-oer__delta { margin: 8px 0 18px; font-size: 13px; color: var(--md-muted); }
        .md-oer__compare { display: flex; flex-direction: column; gap: 12px; }
        .md-oer__item { display: grid; grid-template-columns: 40px 1fr 56px; align-items: center; gap: 10px; font-size: 13px; }
        .md-oer__item > span { color: var(--md-muted); font-weight: 600; }
        .md-oer__item b { text-align: right; font-variant-numeric: tabular-nums; }

        .md-notes { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .md-notes__item { padding: 14px; border-left: 3px solid var(--md-brand); background: #f8faf9; border-radius: 0 10px 10px 0; }
        .md-notes__item p { margin: 8px 0 0; font-size: 14px; line-height: 1.55; color: #334155; }

        .md-details { background: var(--md-card); border: 1px solid var(--md-line); border-radius: 16px; overflow: hidden; }
        .md-details > summary { cursor: pointer; list-style: none; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 6px 12px; padding: 16px 20px; font-size: 16px; font-weight: 700; }
        .md-details > summary::-webkit-details-marker { display: none; }
        .md-details > summary::after { content: ''; width: 9px; height: 9px; border-right: 2px solid var(--md-muted); border-bottom: 2px solid var(--md-muted); transform: rotate(45deg); margin-left: auto; transition: transform .15s; }
        .md-details[open] > summary::after { transform: rotate(-135deg); }
        .md-details > summary small { font-size: 12px; }
        .md-details[open] > summary { border-bottom: 1px solid var(--md-line); }
        .md-details .dmr__grid { padding: 20px; background: #f8faf9; }

        .dmr__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); grid-auto-flow: row dense; gap: 16px; align-items: start; }
        .dmr-panel { background: #fff; border: 1px solid var(--md-line); border-radius: 12px; overflow: hidden; min-width: 0; }
        .dmr-panel--wide { grid-column: 1 / -1; }
        .dmr-panel__title { margin: 0; padding: 10px 14px; font-size: 14px; font-weight: 700; color: var(--md-brand-dark); background: var(--md-brand-soft); border-bottom: 1px solid #cfe8da; }
        .dmr-panel__scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .dmr-table { width: 100%; border-collapse: collapse; font-size: 13px; font-variant-numeric: tabular-nums; }
        .dmr-table th, .dmr-table td { padding: 7px 10px; border-bottom: 1px solid #f0f1f3; white-space: nowrap; }
        .dmr-table th { background: #fafbfb; color: var(--md-muted); font-weight: 600; font-size: 12px; text-align: center; }
        .dmr-table td { text-align: right; }
        .dmr-table td:first-child { text-align: left; color: #334155; }
        .dmr-table tbody tr:hover td { background: #f8faf9; }
        .dmr-table__group td { background: #f1f5f3 !important; font-weight: 700; text-align: left !important; color: var(--md-ink) !important; }
        .dmr-table__total td { font-weight: 700; border-top: 1px solid #cbd5e1; }
        .dmr-table__neg { color: #b91c1c !important; }


        @media (max-width: 1280px) {
            .md-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .md-tanks { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 1024px) {
            .md-row--2, .md-row--2-1 { grid-template-columns: minmax(0, 1fr); }
            .dmr__grid { grid-template-columns: minmax(0, 1fr); }
        }
        @media (max-width: 767px) {
            .md { gap: 14px; }
            .md-hero { padding: 18px; border-radius: 12px; }
            .md-hero__title { font-size: 20px; }
            .md-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .md-kpi { padding: 12px; }
            .md-kpi__value { font-size: 19px; }
            .md-kpi__icon { width: 28px; height: 28px; }
            .md-card { padding: 16px; border-radius: 12px; }
            .md-lines, .md-tanks, .md-utils, .md-notes { grid-template-columns: minmax(0, 1fr); }
            .md-details .dmr__grid { padding: 12px; }
            .dmr-table { font-size: 12px; }
        }
    </style>

    {{--
        TAMBAHAN 2026-09-23 — screen-129--laporan-sterilizer-web.

        Blok terpisah dari CSS warisan Daily Mill Report di atas: TIDAK ADA
        satu pun selector lama yang disentuh, sehingga tampilan Daily Mill
        Report dijamin tidak berubah. Seluruh kelas baru tetap memakai
        prefix `md-` agar kosakata visualnya satu bahasa.

        Dipakai bersama oleh laporan periode per stasiun berikutnya
        (screen-130 s/d screen-139) — jangan menuliskannya inline di blade
        laporan mana pun.
    --}}
    <style>
        /* Filter bar: toolbar bersama components/report-filter-bar.blade.php
           (Mill, Production Line, Periode + aksi ekspor). Ditata ulang
           2026-10-05: field ringkas berlabel + ikon dengan lebar wajar dalam
           satu baris di desktop — bukan dua <select> raksasa 50% — dan
           bertumpuk penuh di ponsel. */
        .md-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 14px 20px;
                      background: var(--md-card); border: 1px solid var(--md-line); border-radius: 16px;
                      padding: 16px 20px; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
        .md-filters__fields { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px 14px; flex: 1 1 560px; min-width: 0; }
        .md-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; flex: 1 1 240px; }
        .md-filters .md-field--mill { flex: 0 1 180px; }
        .md-filters .md-field--line { flex: 0 1 160px; }
        .md-filters .md-field--period { flex: 1 1 220px; max-width: 380px; }
        .md-field__head { display: flex; align-items: center; justify-content: space-between; gap: 8px; min-height: 20px; }
        .md-field__label { font-size: 12px; font-weight: 600; color: var(--md-muted); }
        .md-field__box { position: relative; }
        .md-field__icon { position: absolute; left: 12px; top: 50%; width: 16px; height: 16px; transform: translateY(-50%); color: var(--md-brand); pointer-events: none; }
        .md-field__control { width: 100%; max-width: 100%; box-sizing: border-box; padding: 9px 12px; font: inherit; font-size: 14px;
                             color: var(--md-ink); background: #fff; border: 1px solid var(--md-line); border-radius: 10px; }
        .md-field__control--select { height: 40px; padding: 0 34px 0 36px; font-weight: 500; text-overflow: ellipsis; white-space: nowrap; cursor: pointer;
                                     -webkit-appearance: none; appearance: none;
                                     background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 12px center / 16px 16px;
                                     transition: border-color .15s, box-shadow .15s; }
        .md-field__control--select:hover { border-color: #cbd5e1; }
        .md-field__control:focus { outline: 2px solid var(--md-brand); outline-offset: 1px; border-color: var(--md-brand); }
        .md-field__control--select:focus { outline: none; border-color: var(--md-brand); box-shadow: 0 0 0 3px rgba(36,147,96,.18); }
        /* Mill akun terikat: keterangan statis setinggi field — bukan input disabled. */
        .md-field__static { display: flex; align-items: center; gap: 8px; height: 40px; box-sizing: border-box; margin: 0; padding: 0 12px;
                            border: 1px solid var(--md-line); border-radius: 10px; background: #f8faf9; font-size: 14px; color: var(--md-ink); min-width: 0; }
        .md-field__static strong { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .md-field__static-icon { width: 16px; height: 16px; flex-shrink: 0; color: var(--md-brand); }
        /* Badge kecil di kepala field / status. */
        .md-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; line-height: 16px; white-space: nowrap; }
        .md-badge svg { width: 12px; height: 12px; }
        .md-badge--ok, .md-badge--open { background: var(--md-brand-soft); color: var(--md-brand-dark); }
        .md-badge--draft { background: #fef3c7; color: #92400e; }
        .md-badge--closed { background: #e2e8f0; color: #334155; }
        /* Teks khusus pembaca layar (ikut textContent, tidak tampil). */
        .md-sr { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }
        .md-filters__actions { display: flex; flex-wrap: wrap; gap: 8px; margin-left: auto; }
        .md-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 9px 14px; border-radius: 10px; border: 1px solid var(--md-line);
                  background: #fff; color: var(--md-ink); font-family: inherit; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .md-filters .md-btn { height: 40px; box-sizing: border-box; }
        .md-btn svg { width: 16px; height: 16px; }
        .md-btn:hover { border-color: var(--md-brand); color: var(--md-brand-dark); }
        .md-btn--primary { background: var(--md-brand); border-color: var(--md-brand); color: #fff; }
        .md-btn--primary:hover { background: var(--md-brand-dark); border-color: var(--md-brand-dark); color: #fff; }
        /* Catatan satu baris di dasar toolbar. */
        .md-filters__note { flex: 1 1 100%; display: flex; align-items: flex-start; gap: 6px; margin: 0; padding-top: 12px; border-top: 1px dashed var(--md-line);
                            font-size: 12px; line-height: 1.5; color: var(--md-muted); }
        .md-filters__note svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: 2px; color: var(--md-muted); }
        @media (max-width: 767px) {
            .md-filters { padding: 14px; gap: 12px; }
            .md-filters__fields { flex-basis: 100%; }
            .md-filters .md-field--mill, .md-filters .md-field--line, .md-filters .md-field--period { flex: 1 1 100%; max-width: none; }
            .md-filters__actions { flex: 1 1 100%; margin-left: 0; }
            .md-filters__actions .md-btn { flex: 1 1 0; }
        }
        .md-filters__hint { flex: 1 1 100%; margin: 0; font-size: 12px; color: var(--md-muted); }

        /* KPI: laporan periode memakai 4 kartu, bukan 6 seperti Daily Mill Report. */
        .md-kpis--4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        /* screen-130: baris KPI KEDUA berisi 3 kartu (jam operasi tanpa
           penumpahan, jeda terpanjang, durasi tippler rata-rata). Sejajar
           dengan --4 di atas, bukan ditulis inline di blade laporan. */
        .md-kpis--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }

        .md-chip--status { background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.28); text-transform: uppercase; letter-spacing: .05em; font-size: 11px; }
        .md-chip--closed { background: rgba(15,23,42,.28); border-color: rgba(255,255,255,.2); }
        .md-chip--draft { background: #fef3c7; color: #92400e; border-color: transparent; }

        /* Tren harian: batang vertikal per tanggal, menggulir di dalam kartu
           (bukan melebarkan halaman) saat periodenya panjang. */
        .md-trendchart { display: flex; align-items: flex-end; gap: 6px; height: 176px; padding-bottom: 4px; overflow-x: auto; }
        /* Tinggi batang ditulis inline sebagai persen (lihat laporan-sterilizer.blade.php).
           Persen hanya ter-resolve bila blok penampungnya bertinggi pasti, karena itu:
           .md-trendchart memakai height (bukan min-height), kolomnya height:100%, dan
           kolom dijadikan grid `auto 1fr auto` agar persen dihitung terhadap baris 1fr
           saja — di luar label nilai dan label tanggal. Versi sebelumnya memakai flex
           tanpa tinggi pasti sehingga SELURUH batang jatuh ke min-height 4px dan
           grafiknya rata. Tidak tertangkap test mana pun: asersi hanya memeriksa
           keberadaan elemen, bukan tingginya. */
        .md-trendchart__col { display: grid; grid-template-rows: auto 1fr auto; align-items: end; justify-items: center; gap: 5px; flex: 1 1 0; min-width: 30px; height: 100%; }
        .md-trendchart__val { font-size: 11px; font-weight: 700; color: var(--md-muted); font-variant-numeric: tabular-nums; }
        .md-trendchart__bar { width: 100%; max-width: 34px; min-height: 4px; border-radius: 6px 6px 3px 3px;
                              background: linear-gradient(180deg, #2ea56d, #249360); }
        .md-trendchart__col--low .md-trendchart__bar { background: linear-gradient(180deg, #f6ab4a, #e08b23); }
        /* screen-130: jam DI LUAR jendela operasi tippler pada grafik 24
           jam. Kolomnya diredupkan, bukan disembunyikan — grafik harus
           tetap 24 kolom agar bentuknya tidak berubah antar periode,
           sementara pembacanya tetap bisa membedakan "sepi" dari "memang
           tidak beroperasi". Hanya mewarnai; tidak menyentuh tata letak
           grid `auto 1fr auto` milik .md-trendchart__col. */
        .md-trendchart__col--off .md-trendchart__bar { background: #e9eef2; }
        .md-trendchart__col--off .md-trendchart__val,
        .md-trendchart__col--off .md-trendchart__lbl { opacity: .45; }
        .md-trendchart__lbl { font-size: 11px; color: var(--md-muted); white-space: nowrap; }

        /* Distribusi durasi per hari: min - rata-rata - maks pada satu rel. */
        .md-dist { display: flex; flex-direction: column; gap: 9px; }
        .md-dist__row { display: grid; grid-template-columns: 74px minmax(0, 1fr) auto; align-items: center; gap: 10px; }
        .md-dist__day { font-size: 12px; color: var(--md-muted); white-space: nowrap; }
        .md-dist__track { position: relative; height: 10px; border-radius: 999px; background: #edf2f0; }
        .md-dist__range { position: absolute; top: 0; bottom: 0; border-radius: 999px; background: linear-gradient(90deg, #7fd0a7, #249360); }
        .md-dist__avg { position: absolute; top: -3px; bottom: -3px; width: 2px; background: #0f172a; opacity: .5; border-radius: 2px; }
        .md-dist__nums { font-size: 12px; color: #334155; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .md-dist__nums b { color: var(--md-ink); }

        /* Perbandingan antar unit Sterilizer. */
        .md-units { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .md-unit { display: flex; flex-direction: column; gap: 7px; padding: 14px; border-radius: 12px; background: #f8faf9; min-width: 0; }
        .md-unit__top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .md-unit__label { font-size: 14px; font-weight: 700; }
        .md-unit__stats { display: flex; flex-wrap: wrap; gap: 4px 14px; font-size: 12px; color: var(--md-muted); }
        .md-unit__stats b { color: var(--md-ink); font-variant-numeric: tabular-nums; }

        /* Siklus menyimpang + kotak ambang. */
        .md-outliers { display: flex; flex-direction: column; gap: 10px; }
        .md-outlier { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 12px; background: #fffaf2; border: 1px solid #fde8c8; }
        .md-outlier__icon { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 10px; background: #fef3c7; color: #b45309; flex-shrink: 0; }
        .md-outlier__icon svg { width: 18px; height: 18px; }
        .md-outlier__body { flex: 1; min-width: 0; }
        .md-outlier__title { margin: 0; font-size: 13px; font-weight: 700; }
        .md-outlier__meta { margin: 2px 0 0; font-size: 12px; color: var(--md-muted); }
        .md-outlier__value { margin: 0; font-size: 18px; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .md-outlier__value span { font-size: 12px; font-weight: 600; color: var(--md-muted); }
        .md-threshold { display: flex; gap: 10px; margin-top: 14px; padding: 12px 14px; border-radius: 12px;
                        background: #f1f5f9; color: #334155; font-size: 12px; line-height: 1.6; }
        .md-threshold b { color: var(--md-ink); }

        /* Tabel rekap harian. */
        .md-recap { padding: 20px; background: #f8faf9; overflow-x: auto; }
        .md-table { width: 100%; border-collapse: collapse; font-size: 13px; font-variant-numeric: tabular-nums; }
        .md-table th, .md-table td { padding: 8px 10px; text-align: right; border-bottom: 1px solid var(--md-line); white-space: nowrap; }
        .md-table th:first-child, .md-table td:first-child { text-align: left; }
        .md-table thead th { font-size: 12px; font-weight: 700; color: var(--md-muted); background: #eef3f0; }
        .md-table tfoot td { font-weight: 700; background: #eef3f0; border-bottom: none; }
        .md-table .is-muted { color: #94a3b8; }

        /* Empty state & catatan. */
        .md-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; text-align: center;
                    padding: 40px 24px; background: var(--md-card); border: 1px dashed var(--md-line); border-radius: 16px; }
        .md-empty__icon { display: grid; place-items: center; width: 44px; height: 44px; border-radius: 14px; background: var(--md-brand-soft); color: var(--md-brand); }
        .md-empty__icon svg { width: 22px; height: 22px; }
        .md-empty__title { margin: 0; font-size: 15px; font-weight: 700; }
        .md-empty__text { margin: 0; font-size: 13px; color: var(--md-muted); max-width: 46ch; }


        /* ================================================================
           TAMBAHAN 2026-09-23 — screen-140--laporan-stasiun-web.

           Blok lanjutan di dalam blok kedua yang sama: TIDAK ADA satu pun
           selector di atas yang disentuh, sehingga Daily Mill Report dan
           Laporan Sterilizer dijamin tidak berubah tampilannya.

           `.station-grid` / `.station-tile` sengaja memakai nama yang sama
           dengan data/production-process-activity.blade.php agar dua grid
           stasiun berbicara satu bahasa visual. Halaman itu mendefinisikan
           kelasnya sendiri secara inline dan tidak meng-include partial
           ini, jadi keduanya tidak saling menimpa. Bedanya disengaja: tile
           aktif di sini memakai hijau merek (keluarga LAPORAN), bukan merah
           station-red (keluarga input data).
           ================================================================ */

        /* Penanda langkah: 1 Mill -> 2 Stasiun. */
        .md-step { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .md-step__num { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; flex-shrink: 0;
                        border-radius: 999px; background: var(--md-brand); color: #fff; font-size: 12px; font-weight: 700; }
        .md-step__num--done { background: var(--md-brand-dark); }
        .md-step__title { font-size: 16px; font-weight: 700; color: var(--md-ink); }
        .md-step__hint { margin: 0 0 14px; font-size: 13px; color: var(--md-muted); }

        /* Mill yang sedang berlaku — keterangan, bukan pemilih. */
        .md-millcurrent { display: inline-flex; align-items: center; gap: 8px; padding-bottom: 9px; font-size: 13px; color: var(--md-muted); }
        .md-millcurrent svg { width: 18px; height: 18px; flex-shrink: 0; color: var(--md-brand); }
        .md-millcurrent strong { color: var(--md-ink); font-weight: 600; }

        /* Grid stasiun: kolom auto-fill responsif (temuan audit 2026-10-04
           #10 — sebelumnya 3 kolom TETAP dengan max-width 640px sehingga
           sisa lebar halaman kosong). Tile minimal 150px. */
        .station-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 16px; }
        .station-tile { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;
                        min-height: 44px; padding: 20px 12px; border: 1px solid transparent; border-radius: 12px;
                        box-shadow: 0 1px 2px rgba(15,23,42,.06); font-size: 13px; font-weight: 600; line-height: 1.25;
                        text-align: center; text-decoration: none; }
        .station-tile svg { width: 22px; height: 22px; }
        .station-tile.active { background: var(--md-brand); color: #fff; }
        .station-tile.active:hover { background: var(--md-brand-dark); color: #fff; }
        .station-tile.disabled { background: #f8faf9; border-color: var(--md-line); color: var(--md-muted);
                                 box-shadow: none; font-weight: 500; cursor: default; }
        .station-tile .placeholder-label { font-size: 10px; font-weight: 400; color: var(--md-muted); }

        /* Legenda aktif / belum tersedia. */
        .md-legend { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; margin-top: 14px; font-size: 12px; color: var(--md-muted); }
        .md-legend__item { display: inline-flex; align-items: center; gap: 6px; }
        .md-legend__swatch { display: inline-block; width: 12px; height: 12px; border-radius: 4px; }
        .md-legend__swatch--active { background: var(--md-brand); }
        .md-legend__swatch--disabled { background: #f8faf9; border: 1px solid var(--md-line); }
        /* screen-130: legenda grafik 24 jam — satu contoh warna per
           keadaan batang, supaya kolom redup tidak terbaca sebagai galat. */
        .md-legend__swatch--low { background: #e08b23; }
        .md-legend__swatch--off { background: #e9eef2; border: 1px solid var(--md-line); }

        /* screen-131: panel md-recap yang BERSARANG di dalam md-card ditarik
           sampai tepi kartu, supaya tabel rekap per unit boiler menggulir
           mendatar di dalam kartunya sendiri dan halaman tidak pernah punya
           gulir horizontal. Aturan TATA LETAK murni — tidak ada nilai warna,
           radius, atau shadow baru, dan .md-recap yang berdiri sendiri (di
           dalam <details> pada laporan Sterilizer/Cages & Tracks) tidak
           tersentuh karena selector-nya anak langsung .md-card. */
        .md-card > .md-recap { margin: 0 -20px -20px; border-radius: 0 0 16px 16px; }

        /* ================================================================
           TAMBAHAN 2026-09-25 — screen-132--laporan-clarification-web.

           GRAFIK GARIS TIGA SERI (`md-lc*`). Kosakata lama hanya punya
           grafik BATANG SERI TUNGGAL (.md-trendchart), sedangkan layar ini
           harus menggambar suhu tangki clarification, tangki minyak, dan
           tangki sludge pada SATU bidang gambar dengan SATU sumbu — karena
           yang dibaca adalah SELISIH antar tangki, bukan nilai
           masing-masing. Tiga grafik terpisah memenuhi kalimat "tren suhu
           antar tangki" secara harfiah sambil menghilangkan maksudnya.

           PEMBEDA ANTAR SERI DUA LAPIS — WARNA *DAN* POLA GARIS
           (utuh / putus / titik) — supaya grafiknya tetap terbaca tanpa
           mengandalkan warna sama sekali.

           KETIGA WARNANYA NETRAL SECARA MAKNA (warna merek + dua tingkat
           warna teks): tidak satu pun tangki boleh terbaca sebagai "aman"
           atau "bahaya". TIDAK ADA penandaan nilai di luar batas di layar
           ini — tidak ada kartu ambang, pewarnaan aman/bahaya, outlier,
           maupun IQR — karena Clarification tidak punya master target
           operasional (tidak ada ClarificationOperationalTarget). Alasan
           lengkapnya ada di docblock App\Services\ClarificationReportService.

           Dipakai oleh livewire/dashboard/laporan-clarification.blade.php.
           Jangan menuliskannya inline di blade mana pun.
           ================================================================ */

        /* Token warna seri. Aturan BARU pada .md — tidak menyentuh satu pun
           deklarasi lama di blok pertama. */
        .md { --md-s1: var(--md-brand); --md-s2: var(--md-ink); --md-s3: var(--md-muted); }

        .md-lc { overflow-x: auto; padding-bottom: 6px; }
        .md-lc__svg { display: block; width: 100%; min-width: 620px; height: auto; }
        .md-lc__grid { stroke: var(--md-line); stroke-width: 1; }
        .md-lc__axis { stroke: var(--md-line); stroke-width: 1; }
        .md-lc__ytick, .md-lc__xtick { font-family: inherit; font-size: 11px; fill: var(--md-muted); }
        .md-lc__line { fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        /* Lapis 1: warna. Lapis 2: pola garis. Keduanya, selalu. */
        .md-lc__line--s1 { stroke: var(--md-s1); }
        .md-lc__line--s2 { stroke: var(--md-s2); stroke-dasharray: 7 4; }
        .md-lc__line--s3 { stroke: var(--md-s3); stroke-dasharray: 2 4; }
        .md-lc__dot { stroke: #fff; stroke-width: 1.5; }
        .md-lc__dot--s1 { fill: var(--md-s1); }
        .md-lc__dot--s2 { fill: var(--md-s2); }
        .md-lc__dot--s3 { fill: var(--md-s3); }

        /* Contoh seri pada legenda. Sengaja MENIRU POLA GARISNYA, bukan
           sekadar kotak warna: legenda yang hanya berwarna membuat lapis
           kedua tidak ada gunanya. */
        .md-legend__swatch--s1 { background: var(--md-s1); }
        .md-legend__swatch--s2 { background: repeating-linear-gradient(90deg, var(--md-s2) 0 7px, transparent 7px 11px); border-radius: 0; height: 3px; width: 18px; }
        .md-legend__swatch--s3 { background: repeating-linear-gradient(90deg, var(--md-s3) 0 2px, transparent 2px 6px); border-radius: 0; height: 3px; width: 18px; }

        /* ================================================================
           TAMBAHAN 2026-09-25 — screen-133--laporan-storage-tank-web.

           `.md-explain` — KOTAK KETERANGAN. Tampilannya sama persis dengan
           `.md-threshold` yang sudah ada, dan itu memang disengaja: yang
           berbeda hanya NAMANYA.

           Layar Laporan Storage Tank sengaja TIDAK menandai satu pun nilai
           di luar batas (Storage Tank tidak punya master target operasional
           — tidak ada StorageTankOperationalTarget), dan ketiadaan itu
           DIASERSI MENURUT NAMA: HTML ter-render layar itu diperiksa tidak
           mengandung kata 'threshold', 'outlier', 'iqr', 'fence',
           'is-danger', 'text-red', 'severity', maupun 'alert'. Memakai
           `.md-threshold` di sana akan memerahkan asersi itu justru karena
           NAMA kelasnya, padahal kotaknya dipakai untuk menjelaskan — antara
           lain untuk menjelaskan bahwa tidak ada ambang apa pun di layar itu.

           Murni aditif: tidak satu pun deklarasi lama diubah, dan
           `.md-threshold` tetap dipakai apa adanya oleh laporan-laporan
           sebelumnya.
           ================================================================ */
        .md-explain { display: flex; gap: 10px; margin-top: 14px; padding: 12px 14px; border-radius: 12px;
                      background: #f1f5f9; color: #334155; font-size: 12px; line-height: 1.6; }
        .md-explain b { color: var(--md-ink); }
        .md-explain svg { width: 18px; height: 18px; flex-shrink: 0; color: var(--md-brand); }


        /* ================================================================
           TAMBAHAN 2026-10-04 — perbaikan temuan audit laporan.

           Murni aditif kecuali satu aturan .station-grid (#10) yang
           DIGANTI. Ditulis SEBELUM blok @media di bawah supaya aturan
           responsif yang sudah ada tetap menang di layar sempit.
           ================================================================ */

        /* #4 — ikon info di kotak .md-threshold tidak punya aturan ukuran,
           sehingga SVG tanpa atribut width/height melebar ~290-450px. */
        .md-threshold svg { width: 18px; height: 18px; flex-shrink: 0; color: var(--md-brand); }

        /* #5 — angka utama KPI yang dibungkus <span data-testid> ikut
           terkena `.md-kpi__value span` (gaya SATUAN, 13px). Angkanya
           dikembalikan ke ukuran penuh; span satuan (tanpa data-testid)
           tetap kecil. */
        .md-kpi__value > span[data-testid] { font-size: inherit; font-weight: inherit; color: inherit; }

        /* #3 — keterangan "dihitung sampai hari ini" di bawah bilah
           kelengkapan untuk periode yang masih berjalan. */
        .md-budget__note { margin: -6px 0 0; font-size: 12px; color: var(--md-muted); }

        /* #7 — petunjuk gulir untuk grafik/tabel yang lebih lebar dari
           kartunya (sama dengan petunjuk "Geser mendatar…" di mobile). */
        .md-scrollhint { display: flex; align-items: center; gap: 6px; margin: 8px 0 0; font-size: 12px; color: var(--md-muted); }
        .md-scrollhint svg { width: 14px; height: 14px; flex-shrink: 0; }
        /* Grafik batang harian: tiap kolom selebar labelnya ("01 Sep",
           "2.050"), sehingga label tanggal/nilai tidak pernah bertumpuk;
           yang tidak muat menggulir di dalam kartu, dengan petunjuk di
           bawahnya. */
        .md-trendchart--days { gap: 4px; }
        .md-trendchart--days .md-trendchart__col { flex: 1 0 44px; min-width: 44px; }
        .md-trendchart--days .md-trendchart__val { font-size: 10px; }
        /* Grafik garis (.md-lc, min-width 620px): petunjuk gulir hanya
           ketika kartunya memang lebih sempit dari grafik. */
        .md-card:has(> .md-lc) { container-type: inline-size; }
        .md-scrollhint--lc { display: none; }
        @container (max-width: 660px) { .md-scrollhint--lc { display: flex; } }
        /* Tabel rekap: judul kolom BOLEH membungkus (isi sel tetap satu
           baris), sehingga tabel per unit/rekap muat di kartunya dan kolom
           terakhir tidak terpotong di tepi kanan. Bila tetap lebih lebar
           (layar sempit) tabel menggulir di dalam kartunya. */
        .md-table thead th { white-space: normal; vertical-align: bottom; line-height: 1.3; min-width: 56px; }

        @media (max-width: 1100px) {
            .md-kpis--4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .md-kpis--3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 767px) {
            /* screen-131: label KPI Boiler Room lebih panjang ("31 kali
               dilakukan", "265,6 °C rata-rata"), dan md-kpi__value mewarisi
               white-space: nowrap dari blok pertama. Di layar sempit itu
               melebarkan kartunya dan membuat SELURUH halaman menggulir
               mendatar, jadi nilainya boleh membungkus di sini. Aturan tata
               letak murni; memakai breakpoint 767px yang sudah ada, bukan
               breakpoint baru. */
            .md-kpi__value { white-space: normal; }
            /* Pasangan dari .md-card > .md-recap di atas: padding kartu
               mengecil jadi 16px di bawah 767px, jadi tarikan negatifnya ikut
               mengecil — kalau tidak, panelnya menjorok 4px keluar kartu dan
               justru menciptakan gulir mendatar yang hendak dicegah. */
            .md-card > .md-recap { margin: 0 -16px -16px; border-radius: 0 0 12px 12px; }
            .md-filters { padding: 14px; border-radius: 12px; }
            .md-field { flex: 1 1 100%; }
            .md-filters__actions { margin-left: 0; width: 100%; }
            .md-btn { flex: 1 1 auto; justify-content: center; }
            .md-kpis--4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .md-kpis--3 { grid-template-columns: minmax(0, 1fr); }
            .md-units { grid-template-columns: minmax(0, 1fr); }
            .md-dist__row { grid-template-columns: 58px minmax(0, 1fr); grid-template-areas: 'day track' 'nums nums'; row-gap: 4px; }
            .md-dist__day { grid-area: day; }
            .md-dist__track { grid-area: track; }
            .md-dist__nums { grid-area: nums; text-align: right; }
            .md-outlier { flex-wrap: wrap; }
            .md-recap { padding: 12px; }
            .md-table { font-size: 12px; }
            /* screen-140: grid stasiun tetap 3 kolom di bawah 767px —
               melebar penuh, jarak dan padding mengecil, sehingga halaman
               tidak pernah punya gulir horizontal. */
            .station-grid { grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 12px; }
            .station-tile { padding: 16px 8px; font-size: 12px; }
        }
    </style>
