# Derived Assumptions Log — module-web-station-data.screen-017--data-browser-grading-web.4-implement

## v1 — 2026-08-18

- GradingRecordService memfilter pada kolom date (plain date via whereDate()), bukan datetime seperti arrival_datetime di weighbridge — konsekuensi perbedaan tipe field di entity-catalog
- Menggunakan versi Pagination::format() yang sudah dikoreksi (total_pages=1 untuk hasil kosong) dari resolusi spec_mismatch screen-016 — tidak mereintroduksi special-case lama
- Export grading meng-include checked_by/acknowledged_by yang di-resolve ke nama user via eager load, berbeda dari weighbridge yang weight-centric
- InvalidDateRangeException/ExportFailedException reuse langsung (generik, tidak spesifik weighbridge) — tidak diduplikasi

## v3 — 2026-10-03

Sinkronisasi artefak dengan kode isolasi Production Line yang sudah ada (kode tidak diubah).
- test_results.unit/integration/component ← dipetakan dari berkas uji: unit = tests/Unit/Services/*RecordServiceTest.php, integration = tests/Feature/Api/DataBrowser*Test.php, component = tests/Feature/Livewire/DataBrowser*Test.php; run_at memakai tanggal jalan (2026-10-03) tanpa jam yang tepat
- test_results.unit.passed ← mencakup SELURUH uji unit service stasiun ini, termasuk metode yang dipakai layar Form/Detail, bukan hanya jalur Data Browser
- test_results.browser ← dibiarkan apa adanya: uji Playwright tidak dijalankan pada sinkronisasi ini
- files_generated += ScopesToActorMill.php ← trait bersama (dibuat 2026-09-28), dicatat di setiap layar Data Browser karena buildFilteredQuery() bergantung padanya
- known_issues += celah uji browser Production Line ← diangkat dari catatan tech-spec, severity minor dipilih agen

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v3)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar
