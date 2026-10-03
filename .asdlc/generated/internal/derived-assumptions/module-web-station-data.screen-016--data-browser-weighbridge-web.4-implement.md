# Derived Assumptions Log — module-web-station-data.screen-016--data-browser-weighbridge-web.4-implement

## v1 — 2026-08-18

- Export CSV/Excel: tidak ada package XLSX writer (spatie/laravel-excel dsb) ditambahkan sesuai instruksi; format=excel memakai fallback CSV dengan mimetype/ekstensi .xlsx (pragmatis MVP, bukan file OOXML asli)
- EXPORT_ROW_LIMIT = 50000 dipilih sebagai batas pragmatis (tech-spec hanya bilang "misal 50000 baris")
- Filter "stasiun" yang disebut di prosa uiux-spec sengaja tidak diimplementasikan — bukan bagian query params resmi di tech-spec kedua endpoint
- Livewire component memanggil WeighbridgeRecordService langsung (bukan trait WithPagination) agar web dan API entry point berbagi satu code path persis sama

## Keputusan HITL (permanent exception spec_mismatch — dikonfirmasi user)
1. **total_pages saat hasil kosong**: total_pages=1 (standar Laravel, max(1, ceil(0/perPage))) dipilih sebagai kontrak resmi — BUKAN total_pages=0. Helper Pagination.php (dipakai bersama semua screen list) dikembalikan ke perilaku standar lastPage(); test dan Livewire error-fallback disesuaikan mengikuti keputusan ini.
2. **Content-Type export CSV**: 'text/csv; charset=utf-8' diterima sebagai setara 'text/csv' (valid secara HTTP) — assertion test dilonggarkan (toStartWith), implementasi tidak diubah.

## Catatan proses — race condition
Dua invocation screen-impl-agent untuk screen ini sempat berjalan BERSAMAAN secara tidak sengaja (kegagalan komunikasi status sebelumnya membuat command mengira invocation pertama gagal lalu meluncurkan yang kedua, padahal yang pertama masih berjalan di background). Keduanya menulis ke file yang sama (Pagination.php, routes, CompanyFactory.php, dst) — invocation pertama "menang" race dan hasil akhirnya yang dipertahankan di disk. Command memverifikasi seluruh state file secara langsung (bukan hanya percaya laporan agent), lalu menerapkan sendiri kedua keputusan HITL di atas dan menjalankan full test suite untuk konfirmasi tidak ada regresi. Tidak ada file rusak atau hilang akibat race ini — hanya perlu rekonsiliasi manual.

## v4 — 2026-10-03

Sinkronisasi artefak dengan kode isolasi Production Line yang sudah ada (kode tidak diubah).
- test_results.unit/integration/component ← dipetakan dari berkas uji: unit = tests/Unit/Services/*RecordServiceTest.php, integration = tests/Feature/Api/DataBrowser*Test.php, component = tests/Feature/Livewire/DataBrowser*Test.php; run_at memakai tanggal jalan (2026-10-03) tanpa jam yang tepat
- test_results.unit.passed ← mencakup SELURUH uji unit service stasiun ini, termasuk metode yang dipakai layar Form/Detail, bukan hanya jalur Data Browser
- test_results.browser ← dibiarkan apa adanya: uji Playwright tidak dijalankan pada sinkronisasi ini
- files_generated += ScopesToActorMill.php ← trait bersama (dibuat 2026-09-28), dicatat di setiap layar Data Browser karena buildFilteredQuery() bergantung padanya
- known_issues += celah uji browser Production Line ← diangkat dari catatan tech-spec, severity minor dipilih agen

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v4)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v5 — 2026-10-03

Sinkronisasi catatan uji 4-implement dengan uji yang ditambahkan 2026-10-03.
- test_results.browser.passed = 2: dihitung dari jumlah deklarasi test( di spec e2e-web (test.skip tidak dihitung); failed = 0 karena spec lulus penuh.
- Known_issue lama yang hanya menyatakan browser test (backend/tests/Browser/*.php) tidak dapat/tidak dijalankan ikut dihapus: suite browser yang benar-benar dieksekusi untuk layar ini adalah spec e2e-web Playwright, dan kini lulus penuh.
- Spec e2e-web dan helper e2e-web/tests/support/production-line-filter.ts dicatat di fe_test_files_generated (sebelumnya tidak tercatat di daftar file uji mana pun).

## v6 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/DataBrowserWeighbridgeTest.php` dihapus dari `test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).
