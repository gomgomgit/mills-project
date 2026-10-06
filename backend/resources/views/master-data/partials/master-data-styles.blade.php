{{--
    CSS khusus screen-127 "Struktur Mills". Kosakata kelas `sm-*` per
    uiux-spec.

    DIMUAT LEWAT SLOT `styles` MILIK LAYOUT (master-data/tree-view.blade.php),
    BUKAN dari dalam komponen Livewire. Livewire 3 memasang wire:id pada
    elemen ter-render pertama; <style> di dalam/di atas root komponen membuat
    atribut itu menempel pada <style> dan SELURUH wire:model berhenti bekerja
    — termasuk kotak penyaring dan setiap field modal. Harganya sudah dibayar
    di screen-140.

    Partial ini milik layar ini SENDIRI. Jangan menambahkan kelas layar ini ke
    dashboard/partials/report-styles.blade.php — partial itu dipakai sembilan
    laporan sekaligus.

    Setiap kelas yang dipakai markup komponen punya definisinya di bawah; tidak
    ada kelas yatim dan tidak ada definisi tanpa pemakai. Kelas .kcm-modal-*
    (kerangka modal) datang dari components/modal.blade.php, .ld-* (spinner
    tombol) dari components/loading-assets.blade.php, dan .shell-* dari layout
    — ketiganya sengaja tidak diduplikasi di sini.
--}}
<style>
    .sm-page {
        /* Token uiux-spec v5 */
        --sm-brand: #249360;
        --sm-brand-hover: #1D7A4E;
        --sm-destructive: #DC2626;
        --sm-destructive-hover: #B91C1C;
        --sm-surface: #F7F7F7;
        --sm-border: #E3E3E3;
        --sm-text: #1F2937;
        --sm-text-muted: #6B7280;
        --sm-radius-card: 12px;
        --sm-radius-chip: 999px;
        --sm-radius-input: 6px;
        --sm-radius-button: 8px;
        --sm-shadow-card: 0 1px 2px rgba(0, 0, 0, 0.06);

        color: var(--sm-text);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        /* Tidak ada gulir horizontal pada halaman, termasuk di lebar
           telepon: isi apa pun yang lebih lebar dipotong di dalam
           pembungkusnya sendiri, bukan dengan melebarkan halaman. */
        max-width: 100%;
    }

    /* Peredam halus selama aksi tulis berjalan — bukan penonaktifan field
       (konvensi web proyek ini: tidak ada input disabled/readonly). */
    .sm-page--busy {
        opacity: 0.6;
        transition: opacity 0.15s ease-in-out;
    }

    .sm-page__header {
        margin-bottom: 16px;
    }

    .sm-page__title {
        margin: 0 0 4px;
        font-size: 22px;
        font-weight: 700;
        line-height: 1.25;
    }

    .sm-page__subtitle {
        margin: 0;
        font-size: 14px;
        color: var(--sm-text-muted);
        max-width: 72ch;
    }

    /* ----------------------------- Baris ringkasan ----------------------------- */

    .sm-counts {
        background: #fff;
        border: 1px solid var(--sm-border);
        border-radius: var(--sm-radius-card);
        box-shadow: var(--sm-shadow-card);
        padding: 14px 16px;
        margin-bottom: 16px;
    }

    .sm-counts__row {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .sm-counts__item {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }

    .sm-counts__num {
        font-size: 24px;
        font-weight: 700;
        line-height: 1.1;
        color: var(--sm-brand);
    }

    .sm-counts__label {
        font-size: 12px;
        font-weight: 500;
        color: var(--sm-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    /* Keterangan jumlah yang cocok — terpisah dari angka total, supaya
       angka total tidak pernah ikut mengecil saat penyaring aktif. */
    .sm-counts__match {
        margin: 12px 0 0;
        padding-top: 10px;
        border-top: 1px dashed var(--sm-border);
        font-size: 13px;
        color: var(--sm-text-muted);
    }

    /* -------------------------------- Penyaring -------------------------------- */

    .sm-toolbar {
        display: flex;
        align-items: flex-end;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .sm-search {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex: 1 1 320px;
        min-width: 0;
    }

    .sm-search__label {
        font-size: 13px;
        font-weight: 500;
        color: var(--sm-text-muted);
    }

    .sm-search__input {
        width: 100%;
        padding: 9px 12px;
        font: inherit;
        font-size: 14px;
        color: var(--sm-text);
        background: #fff;
        border: 1px solid var(--sm-border);
        border-radius: var(--sm-radius-input);
    }

    .sm-search__input:focus {
        outline: 2px solid var(--sm-brand);
        outline-offset: 1px;
        border-color: var(--sm-brand);
    }

    /* --------------------------------- Bagian ---------------------------------- */

    .sm-section {
        margin-bottom: 28px;
    }

    .sm-section__head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 12px;
    }

    .sm-section__heading {
        min-width: 0;
    }

    .sm-section__title {
        margin: 0 0 2px;
        font-size: 16px;
        font-weight: 700;
    }

    .sm-section__hint {
        margin: 0;
        font-size: 13px;
        color: var(--sm-text-muted);
        max-width: 68ch;
    }

    .sm-section__actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* ------------------------------- Papan kartu ------------------------------- */

    /* minmax(0, 1fr) wajib: tanpa batas bawah 0, kolom grid melar mengikuti
       isi terpanjang dan nama 120 karakter akan membuat kartu (dan halaman)
       melebar alih-alih dipotong. align-items: start supaya kartu yang
       punya banyak line tumbuh lebih tinggi tanpa memaksa tetangganya ikut
       tinggi — tinggi kartu yang tidak seragam adalah keadaan normal. */
    .sm-board {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(340px, 100%), 1fr));
        align-items: start;
        gap: 16px;
    }

    .sm-mill {
        background: #fff;
        border: 1px solid var(--sm-border);
        border-radius: var(--sm-radius-card);
        box-shadow: var(--sm-shadow-card);
        padding: 16px;
        min-width: 0;
    }

    .sm-mill__head {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .sm-mill__logo {
        width: 44px;
        height: 44px;
        flex: 0 0 44px;
        border-radius: 10px;
        border: 1px solid var(--sm-border);
        background: var(--sm-surface);
        object-fit: contain;
    }

    /* Penanda pengganti untuk mill tanpa logo: inisial di atas latar netral,
       bukan <img> bersrc kosong (ikon gambar rusak) dan bukan ruang kosong. */
    .sm-mill__logo--initials {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        font-weight: 700;
        letter-spacing: 0.02em;
        color: var(--sm-text-muted);
    }

    .sm-mill__ident {
        flex: 1 1 auto;
        min-width: 0;
    }

    .sm-mill__name {
        margin: 0 0 2px;
        font-size: 15px;
        font-weight: 700;
        line-height: 1.3;
    }

    .sm-mill__code {
        margin: 0;
        font-size: 12px;
        font-weight: 600;
        color: var(--sm-brand);
    }

    .sm-mill__crumb {
        margin: 2px 0 0;
        font-size: 12px;
        color: var(--sm-text-muted);
    }

    .sm-mill__actions {
        display: flex;
        align-items: center;
        gap: 4px;
        flex: 0 0 auto;
    }

    .sm-mill__lines-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-top: 14px;
        padding-top: 12px;
        border-top: 1px solid var(--sm-border);
    }

    .sm-mill__no-lines {
        margin: 10px 0 0;
        padding: 12px;
        font-size: 13px;
        color: var(--sm-text-muted);
        background: var(--sm-surface);
        border: 1px dashed var(--sm-border);
        border-radius: var(--sm-radius-input);
    }

    /* ------------------------- Baris Production Line -------------------------- */

    .sm-lines {
        list-style: none;
        margin: 10px 0 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .sm-line {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        background: var(--sm-surface);
        border: 1px solid var(--sm-border);
        border-radius: var(--sm-radius-input);
        min-width: 0;
    }

    /* Line yang cocok dengan penyaring ditandai — kalau tidak, penyaring
       justru menyembunyikan hal yang dicari. */
    .sm-line--match {
        background: rgba(36, 147, 96, 0.08);
        border-color: var(--sm-brand);
    }

    .sm-line__main {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1 1 auto;
        min-width: 0;
        flex-wrap: wrap;
    }

    .sm-line__name {
        font-size: 13px;
        font-weight: 600;
        max-width: 100%;
    }

    .sm-line__code {
        font-size: 11px;
        font-weight: 600;
        color: var(--sm-text-muted);
        background: #fff;
        border: 1px solid var(--sm-border);
        border-radius: var(--sm-radius-chip);
        padding: 1px 8px;
    }

    .sm-line__match {
        font-size: 11px;
        font-weight: 600;
        color: #fff;
        background: var(--sm-brand);
        border-radius: var(--sm-radius-chip);
        padding: 1px 8px;
    }

    .sm-line__desc {
        font-size: 12px;
        color: var(--sm-text-muted);
        flex: 1 1 100%;
    }

    .sm-line__actions {
        display: flex;
        align-items: center;
        gap: 4px;
        flex: 0 0 auto;
    }

    /* --------------------------------- Chip ----------------------------------- */

    .sm-chip {
        display: inline-flex;
        align-items: center;
        font-size: 12px;
        font-weight: 600;
        color: var(--sm-brand);
        background: rgba(36, 147, 96, 0.1);
        border-radius: var(--sm-radius-chip);
        padding: 2px 10px;
    }

    /* ------------------------------ Keadaan kosong ----------------------------- */

    /* Satu blok kosong PER BAGIAN, bukan satu untuk seluruh halaman:
       "belum ada mill" dan "belum ada Corporate" menuntut tindakan berbeda. */
    .sm-empty {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        padding: 24px;
        background: #fff;
        border: 1px dashed var(--sm-border);
        border-radius: var(--sm-radius-card);
    }

    .sm-empty__title {
        margin: 0;
        font-size: 14px;
        font-weight: 700;
    }

    .sm-empty__text {
        margin: 0;
        font-size: 13px;
        color: var(--sm-text-muted);
        max-width: 68ch;
    }

    /* ------------------------------ Tabel ringkas ------------------------------ */

    /* Pembungkus overflow-x: auto sesuai component_patterns[data-table] —
       tabel yang terlalu lebar menggulir DI DALAM pembungkusnya, bukan
       membuat seluruh halaman menggulir ke samping. */
    .sm-table-wrap {
        background: #fff;
        border: 1px solid var(--sm-border);
        border-radius: var(--sm-radius-card);
        box-shadow: var(--sm-shadow-card);
        overflow-x: auto;
    }

    .sm-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        table-layout: fixed;
    }

    .sm-table__head th {
        text-align: left;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--sm-text-muted);
        background: var(--sm-surface);
        padding: 10px 12px;
        border-bottom: 1px solid var(--sm-border);
        white-space: nowrap;
    }

    .sm-table__row td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--sm-border);
        vertical-align: middle;
        min-width: 0;
    }

    .sm-table__row:last-child td {
        border-bottom: none;
    }

    .sm-table__num {
        width: 8.5rem;
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .sm-table__actions-head {
        width: 6.5rem;
        text-align: right;
    }

    .sm-table__actions {
        text-align: right;
        white-space: nowrap;
    }

    /* ------------------------------- Pemotongan ------------------------------- */

    /* Pemotongan nama/kode panjang dilakukan DI SINI, bukan di PHP: nilai
       utuhnya tetap ada di DOM dan di atribut title, jadi tetap dapat
       dibaca serta disalin, dan kartu tidak melar maupun terpotong di
       tengah kata tanpa penanda. */
    .sm-truncate {
        display: block;
        min-width: 0;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sm-truncate--cell {
        max-width: 22rem;
    }

    /* --------------------------------- Tombol --------------------------------- */

    .sm-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 14px;
        font: inherit;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.2;
        text-decoration: none;
        border: 1px solid transparent;
        border-radius: var(--sm-radius-button);
        background: #fff;
        color: var(--sm-text);
        cursor: pointer;
    }

    .sm-btn:focus-visible {
        outline: 2px solid var(--sm-brand);
        outline-offset: 1px;
    }

    .sm-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .sm-btn--primary {
        background: var(--sm-brand);
        border-color: var(--sm-brand);
        color: #fff;
    }

    .sm-btn--primary:hover:not(:disabled) {
        background: var(--sm-brand-hover);
        border-color: var(--sm-brand-hover);
    }

    .sm-btn--ghost {
        background: #fff;
        border-color: var(--sm-border);
        color: var(--sm-text);
    }

    .sm-btn--ghost:hover:not(:disabled) {
        background: var(--sm-surface);
    }

    .sm-btn--danger {
        background: var(--sm-destructive);
        border-color: var(--sm-destructive);
        color: #fff;
    }

    .sm-btn--danger:hover:not(:disabled) {
        background: var(--sm-destructive-hover);
        border-color: var(--sm-destructive-hover);
    }

    .sm-btn--sm {
        padding: 6px 10px;
        font-size: 12px;
    }

    /* Tombol khusus ikon. Target sentuh tetap 32px dan SETIAP tombol ikon di
       markup membawa aria-label + title yang menyebut barisnya — ikon pensil
       dan tempat sampah berulang di tiap baris, jadi tanpa label tidak ada
       cara mengetahui milik baris yang mana (business rule 14). */
    .sm-btn--icon {
        width: 32px;
        height: 32px;
        padding: 0;
        border-color: var(--sm-border);
        color: var(--sm-text-muted);
    }

    .sm-btn--icon svg {
        width: 15px;
        height: 15px;
    }

    .sm-btn--icon:hover:not(:disabled) {
        background: var(--sm-surface);
        color: var(--sm-text);
    }

    .sm-btn--icon-danger {
        color: var(--sm-destructive);
    }

    .sm-btn--icon-danger:hover:not(:disabled) {
        background: rgba(220, 38, 38, 0.08);
        color: var(--sm-destructive-hover);
    }

    /* -------------------------------- Paginasi --------------------------------- */

    /* Blok ini HANYA dirender komponen ketika isinya melebihi $perPage.
       Keterangan posisinya menyebut jumlah TOTAL ("menampilkan 1-20 dari 34
       mill") supaya angka yang terlihat tidak pernah tertukar dengan angka
       yang ada. */
    .sm-pager {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 12px;
        padding: 10px 12px;
        background: #fff;
        border: 1px solid var(--sm-border);
        border-radius: var(--sm-radius-card);
        font-size: 13px;
    }

    .sm-pager__summary {
        color: var(--sm-text-muted);
    }

    .sm-pager__controls {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    /* ---------------------------- Batas hierarki ------------------------------ */

    .sm-note {
        background: var(--sm-surface);
        border: 1px solid var(--sm-border);
        border-radius: var(--sm-radius-card);
        padding: 16px;
    }

    .sm-note__title {
        margin: 0 0 4px;
        font-size: 14px;
        font-weight: 700;
    }

    .sm-note__text {
        margin: 0 0 12px;
        font-size: 13px;
        color: var(--sm-text-muted);
        max-width: 80ch;
    }

    .sm-note__links {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* ---------------------------------- Toast --------------------------------- */

    .sm-toast {
        position: fixed;
        top: 16px;
        right: 16px;
        z-index: 60;
        max-width: min(420px, calc(100vw - 32px));
        padding: 12px 16px;
        border-radius: var(--sm-radius-button);
        font-size: 13px;
        font-weight: 500;
        color: #fff;
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.16);
    }

    .sm-toast--success {
        background: var(--sm-brand);
    }

    .sm-toast--error {
        background: var(--sm-destructive);
    }

    /* --------------------------------- Alert ---------------------------------- */

    .sm-alert {
        margin: 0 0 12px;
        padding: 10px 12px;
        border-radius: var(--sm-radius-input);
        font-size: 13px;
        background: var(--sm-surface);
        border: 1px solid var(--sm-border);
        color: var(--sm-text);
    }

    .sm-alert--error {
        background: rgba(220, 38, 38, 0.08);
        border-color: var(--sm-destructive);
        color: #7F1D1D;
    }

    /* ------------------------------ Form di modal ------------------------------ */

    .sm-form-section {
        margin-bottom: 18px;
    }

    .sm-form-section__title {
        margin: 0 0 10px;
        padding-bottom: 6px;
        border-bottom: 1px solid var(--sm-border);
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--sm-text-muted);
    }

    .sm-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .sm-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }

    .sm-field--span2 {
        grid-column: 1 / -1;
    }

    .sm-field__label {
        font-size: 12px;
        font-weight: 600;
        color: var(--sm-text);
    }

    .sm-field__required {
        color: var(--sm-destructive);
    }

    .sm-field__input {
        width: 100%;
        padding: 8px 10px;
        font: inherit;
        font-size: 13px;
        color: var(--sm-text);
        background: #fff;
        border: 1px solid var(--sm-border);
        border-radius: var(--sm-radius-input);
    }

    .sm-field__input:focus {
        outline: 2px solid var(--sm-brand);
        outline-offset: 1px;
        border-color: var(--sm-brand);
    }

    /* Select induk — sengaja tidak pernah disabled/readonly, termasuk saat
       terisi otomatis dari kartu induknya (uiux-spec
       component_patterns[web-form-input] + business rule 8). */
    .sm-field__input--select {
        appearance: auto;
        background: #fff;
    }

    .sm-field__input--file {
        padding: 6px 8px;
    }

    .sm-field__input--error {
        border-color: var(--sm-destructive);
    }

    .sm-field__error {
        margin: 0;
        font-size: 12px;
        color: var(--sm-destructive);
    }

    .sm-field__hint {
        margin: 0;
        font-size: 12px;
        color: var(--sm-text-muted);
    }

    /* --------------------------- Konfirmasi hapus ----------------------------- */

    .sm-confirm__text {
        margin: 0 0 12px;
        font-size: 14px;
        line-height: 1.5;
    }

    /* ----------------------- Lebar telepon (<768px) --------------------------- */

    /* Papan dan kedua tabel ringkas turun menjadi satu kolom, tanpa gulir
       horizontal pada halaman — tabel tetap menggulir di dalam
       .sm-table-wrap miliknya sendiri. */
    @media (max-width: 767px) {
        .sm-page__title {
            font-size: 19px;
        }

        .sm-counts__row {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .sm-board {
            grid-template-columns: minmax(0, 1fr);
        }

        .sm-form-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .sm-section__head {
            flex-direction: column;
            align-items: stretch;
        }

        .sm-truncate--cell {
            max-width: 12rem;
        }

        .sm-toast {
            left: 16px;
            right: 16px;
            max-width: none;
        }
    }
</style>
