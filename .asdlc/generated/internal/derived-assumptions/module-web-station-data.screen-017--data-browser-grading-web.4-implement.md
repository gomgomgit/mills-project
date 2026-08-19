# Derived Assumptions Log — module-web-station-data.screen-017--data-browser-grading-web.4-implement

## v1 — 2026-08-18

- GradingRecordService memfilter pada kolom date (plain date via whereDate()), bukan datetime seperti arrival_datetime di weighbridge — konsekuensi perbedaan tipe field di entity-catalog
- Menggunakan versi Pagination::format() yang sudah dikoreksi (total_pages=1 untuk hasil kosong) dari resolusi spec_mismatch screen-016 — tidak mereintroduksi special-case lama
- Export grading meng-include checked_by/acknowledged_by yang di-resolve ke nama user via eager load, berbeda dari weighbridge yang weight-centric
- InvalidDateRangeException/ExportFailedException reuse langsung (generik, tidak spesifik weighbridge) — tidak diduplikasi
