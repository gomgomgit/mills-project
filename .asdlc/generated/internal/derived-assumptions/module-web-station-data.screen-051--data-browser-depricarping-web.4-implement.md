# Derived Assumptions Log — module-web-station-data.screen-051--data-browser-depricarping-web.4-implement

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

## v4 — 2026-10-03

Sinkronisasi catatan uji 4-implement dengan uji yang ditambahkan 2026-10-03.
- test_results.browser.passed = 6: dihitung dari jumlah deklarasi test( di spec e2e-web (test.skip tidak dihitung); failed = 0 karena spec lulus penuh.
- Known_issue lama yang hanya menyatakan browser test (backend/tests/Browser/*.php) tidak dapat/tidak dijalankan ikut dihapus: suite browser yang benar-benar dieksekusi untuk layar ini adalah spec e2e-web Playwright, dan kini lulus penuh.
- Spec e2e-web dan helper e2e-web/tests/support/production-line-filter.ts dicatat di fe_test_files_generated (sebelumnya tidak tercatat di daftar file uji mana pun).
- Perbaikan race href ekspor (toHaveAttribute menunggu putaran Livewire) dicatat sebagai perbaikan spec, bukan cacat aplikasi. Known_issue screen-035 dibiarkan apa adanya (di luar cakupan).

## v5 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/DataBrowserDepricarpingTest.php` dihapus dari `test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).
