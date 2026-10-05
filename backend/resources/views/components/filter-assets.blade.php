{{--
    Aset bersama keluarga komponen filter (x-filter.bar, x-filter.field,
    x-filter.search, x-filter.date-range, x-filter.icon).

    Dibuat 2026-10-05 ("cek semua filtering yang ada dan perbaiki design-nya"):
    sebelumnya setiap layar daftar menulis filternya sendiri — 18 Data Browser
    dengan prefix CSS masing-masing (br-/ct-/wb-…), master data dengan
    `kc-filter` yang labelnya terjepit di samping kolom ("Filter / Company"
    dua baris), Kelola User menumpuk dua combobox tanpa wadah, Mills Setting
    dan Laporan Manajemen dengan gaya ketiga dan keempat. Sekarang satu bahasa
    visual — sama dengan toolbar Laporan Stasiun
    (components/report-filter-bar.blade.php + dashboard/partials/
    report-styles.blade.php): field ringkas berlabel kecil + ikon, select
    bertanda chevron sendiri, lebar wajar dalam satu baris di desktop,
    bertumpuk penuh di ponsel, badge status kecil, dan satu baris ringkasan
    (jumlah hasil, jumlah filter aktif, tombol Reset filter).

    Dimuat SEKALI di <head> shell (components/layouts/app.blade.php), sama
    seperti x-searchable-select-assets, supaya kelas-kelasnya selalu
    terdefinisi — juga untuk bagian yang baru disisipkan morph Livewire.

    Warna ditulis literal (bukan custom property layar) karena setiap layar
    mendefinisikan token dengan nama berbeda (--kc-*, --br-*, --md-*); nilai
    di sini sama dengan token --md-* laporan stasiun.
--}}
<style>
    .fb-bar { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 14px 20px; margin-bottom: 20px; padding: 16px 20px;
              background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
              color: #0f172a; box-sizing: border-box; }
    .fb-bar *, .fb-bar *::before, .fb-bar *::after { box-sizing: border-box; }
    .fb-icon { display: inline-block; flex-shrink: 0; width: 16px; height: 16px; vertical-align: middle; }
    .fb-bar__fields { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px 14px; flex: 1 1 560px; min-width: 0; }
    .fb-bar__actions { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 8px; margin-left: auto; }

    {{-- Field: label kecil di atas, kotak kontrol setinggi 40px. Lebar
       mengikuti isinya — bukan 50% atau 100% layar. --}}
    .fb-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; flex: 0 1 200px; }
    .fb-field--sm { flex: 0 1 160px; }
    .fb-field--md { flex: 0 1 220px; }
    .fb-field--lg { flex: 0 1 280px; }
    .fb-field--range { flex: 0 1 300px; }
    .fb-field--auto { flex: 0 0 auto; }
    .fb-field--grow { flex: 1 1 260px; max-width: 420px; }
    .fb-field__head { display: flex; align-items: center; gap: 6px; min-height: 20px; }
    .fb-field__label { font-size: 12px; font-weight: 600; color: #64748b; }
    .fb-field__box { position: relative; min-width: 0; }
    .fb-field__icon { position: absolute; z-index: 1; left: 12px; top: 50%; width: 16px; height: 16px; transform: translateY(-50%);
                      color: #249360; pointer-events: none; }

    {{-- Kontrol: native <select>, <input type=date|search>, dan input
       x-searchable-select (kelas diteruskan lewat atribut class). Memakai
       background-color, BUKAN shorthand background, agar chevron bawaan
       combobox (.ss-combobox__input) tidak terhapus. --}}
    .fb-control { display: block; width: 100%; max-width: 100%; height: 40px; margin: 0; padding: 0 12px; font: inherit; font-size: 14px;
                  font-weight: 500; color: #0f172a; background-color: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
                  transition: border-color .15s, box-shadow .15s; }
    .fb-control::placeholder { color: #94a3b8; font-weight: 400; }
    .fb-control:hover { border-color: #cbd5e1; }
    .fb-control:focus { outline: none; border-color: #249360; box-shadow: 0 0 0 3px rgba(36, 147, 96, .18); }
    .fb-field__box--icon .fb-control { padding-left: 36px; }
    .fb-control--select { padding-right: 34px; text-overflow: ellipsis; white-space: nowrap; cursor: pointer; -webkit-appearance: none; appearance: none;
                          background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
                          background-repeat: no-repeat; background-position: right 12px center; background-size: 16px 16px; }
    {{-- Combobox x-searchable-select: chevron miliknya sendiri, ruang kanan. --}}
    .fb-control.ss-combobox__input { padding-right: 32px; text-overflow: ellipsis; }
    .fb-control--date { min-width: 0; padding: 0 10px; font-variant-numeric: tabular-nums; }
    .fb-control--date::-webkit-calendar-picker-indicator { cursor: pointer; opacity: .65; }
    .fb-control--date::-webkit-calendar-picker-indicator:hover { opacity: 1; }

    {{-- Rentang tanggal: dua input date (ketik manual + pemilih bawaan
       browser) dipisah tanda –; tetap berdampingan di ponsel. --}}
    .fb-range { display: flex; align-items: center; gap: 6px; min-width: 0; }
    .fb-range .fb-control--date { flex: 1 1 0; }
    .fb-range__sep { flex-shrink: 0; color: #94a3b8; font-size: 13px; }

    {{-- Pencarian dengan tombol hapus. Tombol silang bawaan WebKit
       disembunyikan agar tidak ada dua tanda silang. --}}
    .fb-search { position: relative; }
    .fb-control--search { padding-right: 36px; }
    .fb-control--search::-webkit-search-cancel-button { -webkit-appearance: none; appearance: none; display: none; }
    .fb-search__clear { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); display: inline-flex; align-items: center; justify-content: center;
                        width: 28px; height: 28px; padding: 0; border: 0; border-radius: 8px; background: transparent; color: #64748b; cursor: pointer; }
    .fb-search__clear:hover { background: #f1f5f9; color: #0f172a; }
    .fb-search__clear:focus-visible { outline: 2px solid #249360; outline-offset: 1px; }
    .fb-search__clear svg { width: 14px; height: 14px; }

    {{-- Konteks tetap (mill akun terikat): keterangan setinggi field —
       BUKAN input disabled/readonly (konvensi web). --}}
    .fb-field__static { display: flex; align-items: center; gap: 8px; height: 40px; margin: 0; padding: 0 12px; min-width: 0;
                        border: 1px solid #e2e8f0; border-radius: 10px; background: #f8faf9; font-size: 14px; color: #0f172a; }
    .fb-field__static strong { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fb-field__static .fb-icon { width: 16px; height: 16px; flex-shrink: 0; color: #249360; }

    {{-- Segmented control (mis. mode tampilan Grup / Rata). --}}
    .fb-segment { display: inline-flex; height: 40px; padding: 3px; gap: 2px; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; }
    .fb-segment__btn { display: inline-flex; align-items: center; gap: 6px; padding: 0 14px; border: 0; border-radius: 7px; background: transparent;
                       font: inherit; font-size: 13px; font-weight: 600; color: #64748b; cursor: pointer; }
    .fb-segment__btn svg { width: 15px; height: 15px; }
    .fb-segment__btn:hover { color: #0f172a; }
    .fb-segment__btn--active { background: #fff; color: #1a6f48; box-shadow: 0 1px 2px rgba(15, 23, 42, .12); }
    .fb-segment__btn:focus-visible { outline: 2px solid #249360; outline-offset: 1px; }

    {{-- Badge kecil di kepala field / ringkasan. --}}
    .fb-badge { display: inline-flex; align-items: center; gap: 4px; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600;
                line-height: 16px; white-space: nowrap; }
    .fb-badge svg { width: 12px; height: 12px; }
    .fb-badge--req { background: #fef3c7; color: #92400e; }
    .fb-badge--active { background: #e8f5ee; color: #1a6f48; }
    .fb-badge--muted { background: #f1f5f9; color: #475569; }

    {{-- Baris ringkasan di dasar bar: jumlah hasil · filter aktif · catatan,
       tombol Reset filter di kanan. --}}
    .fb-bar__foot { flex: 1 1 100%; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 16px;
                    padding-top: 12px; border-top: 1px dashed #e2e8f0; }
    .fb-bar__summary { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px; margin: 0; font-size: 12px; line-height: 1.5; color: #64748b; min-width: 0; }
    .fb-count { display: inline-flex; align-items: center; gap: 6px; }
    .fb-count strong { color: #0f172a; font-weight: 700; font-variant-numeric: tabular-nums; }
    .fb-count .fb-icon { width: 14px; height: 14px; color: #64748b; }
    .fb-bar__note { display: inline-flex; align-items: flex-start; gap: 6px; }
    .fb-bar__note .fb-icon { width: 14px; height: 14px; flex-shrink: 0; margin-top: 2px; }
    .fb-reset { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 10px; border: 1px solid transparent; border-radius: 8px;
                background: transparent; font: inherit; font-size: 12px; font-weight: 600; color: #1a6f48; cursor: pointer; }
    .fb-reset svg { width: 14px; height: 14px; }
    .fb-reset:hover { background: #e8f5ee; }
    .fb-reset:focus-visible { outline: 2px solid #249360; outline-offset: 1px; }

    {{-- Tombol aksi di kanan bar (mis. ekspor) — setinggi field, sama dengan
       .md-btn toolbar laporan periode. --}}
    .fb-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; height: 40px; padding: 0 14px; border-radius: 10px;
              border: 1px solid #e2e8f0; background: #fff; color: #0f172a; font: inherit; font-size: 14px; font-weight: 600; cursor: pointer;
              text-decoration: none; white-space: nowrap; }
    .fb-btn .fb-icon { width: 16px; height: 16px; }
    .fb-btn:hover { border-color: #249360; color: #1a6f48; }
    .fb-btn--primary { background: #249360; border-color: #249360; color: #fff; }
    .fb-btn--primary:hover { background: #1a6f48; border-color: #1a6f48; color: #fff; }

    .fb-sr { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }

    @media (max-width: 767px) {
        .fb-bar { padding: 14px; gap: 12px; }
        .fb-bar__fields { flex-basis: 100%; }
        .fb-bar .fb-field { flex: 1 1 100%; max-width: none; }
        .fb-bar__actions { flex: 1 1 100%; margin-left: 0; }
        .fb-bar__actions .fb-btn { flex: 1 1 0; }
        .fb-segment { width: 100%; }
        .fb-segment__btn { flex: 1 1 0; justify-content: center; }
    }
</style>
