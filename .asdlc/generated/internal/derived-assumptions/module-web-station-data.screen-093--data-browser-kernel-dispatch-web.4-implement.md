# Derived Assumptions Log — module-web-station-data.screen-093--data-browser-kernel-dispatch-web.4-implement

## v2 — 2026-10-03

Sinkronisasi artefak dengan kode isolasi Production Line yang sudah ada (kode tidak diubah).
- test_results.unit/integration/component ← dipetakan dari berkas uji: unit = tests/Unit/Services/*RecordServiceTest.php, integration = tests/Feature/Api/DataBrowser*Test.php, component = tests/Feature/Livewire/DataBrowser*Test.php; run_at memakai tanggal jalan (2026-10-03) tanpa jam yang tepat
- test_results.unit.passed ← mencakup SELURUH uji unit service stasiun ini, termasuk metode yang dipakai layar Form/Detail, bukan hanya jalur Data Browser
- test_results.browser ← dibiarkan apa adanya: uji Playwright tidak dijalankan pada sinkronisasi ini
- files_generated += ScopesToActorMill.php ← trait bersama (dibuat 2026-09-28), dicatat di setiap layar Data Browser karena buildFilteredQuery() bergantung padanya
- known_issues += celah uji browser Production Line ← diangkat dari catatan tech-spec, severity minor dipilih agen

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v2)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v3 — 2026-10-03

Sinkronisasi catatan uji 4-implement dengan uji yang ditambahkan 2026-10-03.
- test_results.browser.passed = 4: dihitung dari jumlah deklarasi test( di spec e2e-web (test.skip tidak dihitung); failed = 0 karena spec lulus penuh.
- Known_issue lama yang hanya menyatakan browser test (backend/tests/Browser/*.php) tidak dapat/tidak dijalankan ikut dihapus: suite browser yang benar-benar dieksekusi untuk layar ini adalah spec e2e-web Playwright, dan kini lulus penuh.
- Spec e2e-web dan helper e2e-web/tests/support/production-line-filter.ts dicatat di fe_test_files_generated (sebelumnya tidak tercatat di daftar file uji mana pun).
- Perbaikan strict mode empty-state (locator kelas judul, bukan getByText) dicatat sebagai perbaikan spec, bukan cacat aplikasi.

## v4 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/DataBrowserKernelDispatchTest.php` dihapus dari `fe_test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (KernelDispatchRecordService.php, data-browser-kernel-dispatch.blade.php, app/Support/SheetWriter.php, ExportValue.php, Display.php, tests/Unit/Services/ExportDetailRowsTest.php, tests/Pest.php).
- files_generated += Support/SheetWriter.php, Support/ExportValue.php, Support/Display.php ← dipakai export() dan blade
- test_files_generated += tests/Unit/Services/ExportDetailRowsTest.php, tests/Pest.php ← ExportDetailRowsTest mencakup baris ekspor ke-18 stasiun; Pest.php memuat helper xlsxRows/exportBodyAsCsv (⚠ memasukkan berkas helper = penilaian agen)
- implementation_notes += REVISI (2026-10-04, audit-fix …); test_results tidak diubah

## v6 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (backend/app/Support/Concerns/ScopesToActorMill.php, backend/tests/Feature/AuditFix20261005Test.php, e2e-web/tests/audit-fix-20261005.spec.ts).
- test_files_generated / fe_test_files_generated ← + AuditFix20261005Test.php, audit-fix-20261005.spec.ts (keduanya mencakup layar ini lewat dataset 18 Data Browser).
- implementation_notes ← +1 catatan audit-fix 2026-10-05.
