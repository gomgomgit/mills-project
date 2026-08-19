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
