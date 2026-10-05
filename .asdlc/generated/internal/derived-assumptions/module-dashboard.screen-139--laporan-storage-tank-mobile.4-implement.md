# Derived Assumptions Log — module-dashboard.screen-139--laporan-storage-tank-mobile.4-implement

## v2 — 2026-10-03

Sinkronisasi artefak 4-implement dengan kode (kode tidak diubah).
- Pemetaan tipe test mengikuti konvensi artefak yang sudah ada, bukan konvensi brief backend: unit = spec repo mobile + productionLineRepo.spec; integration = Feature/Api Laporan*Test + Unit/Services *ReportServiceTest + KelolaProductionLineTest --filter options-for-report; component = spec view mobile.
- productionLineRepo.ts, ProductionLineController.php, ProductionLineService.php, dan KelolaProductionLineTest.php ditambahkan ke daftar berkas karena layar ini kini bergantung pada GET /api/production-lines/options-for-report (98c812b), walau berkas-berkas itu dipakai bersama kelima layar laporan mobile.
- Hitungan browser lama sengaja tidak diubah (Playwright tidak dijalankan); keusangannya dicatat sebagai known_issue severity low.
- Entri known_issues lama yang kini usang (mis. bentuk galat response?.status di screen-136) tidak dihapus; keusangannya dijelaskan di catatan revisi.

## v3 — 2026-10-03

Ukur ulang uji browser Playwright mobile 2026-10-03.
- Angka browser diambil dari run tujuh spec laporan mobile (Vite dev server + backend :8000, DemoAccountSeeder): 185 lolos, 0 gagal; failed = 0 dan run_at disetel 2026-10-03T00:00:00Z (jam tidak tercatat, dipakai tengah malam UTC).
- laporan-storage-tank.spec.ts: 39 lolos (naik dari 33 karena skenario bertambah).
- Perbaikan asersi flaky expectSingleColumn() (menunggu kartu dirender sebelum cards.count()) dicatat sebagai perubahan berkas test saja, bukan kode produksi; verifikasi 60/60 dalam 5 ulangan dianggap cukup untuk menyatakan stabil.
- Known_issue "test_results.browser tidak diukur ulang" dihapus karena pengukuran ulang kini menutupnya; known_issue lain dibiarkan apa adanya.

## v4 — 2026-10-03

Pembersihan rujukan komponen grid yang dihapus.
- known_issue 'galat vue-tsc PRA-ADA di CagesTippedTimeGrid.vue dan GradingDetailGrid.vue' dihapus: kedua komponen dihapus di 004aacd; vue-tsc kini 0 galat dan vite build sukses.
- Ditambah satu catatan REVISI merujuk 004aacd; kode layar tidak berubah.

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git diff mobile view/repo/tests + StorageTankReportService.php).
- files_generated[+] = Support/ReportPeriodDays, SheetWriter, ExportValue ← dipakai StorageTankReportService yang dilayani endpoint layar ini
- test_files_generated[+] = ReportAuditFix20261004Test.php, ExportXlsxTest.php ← mencakup ekspor/kelengkapan Storage Tank sisi server
- implementation_notes[+1] = REVISI audit-fix ← diff
- known_issues[+1] = stub CSV e2e masih 4 kolom konteks ← mobile/tests/e2e/laporan-storage-tank.spec.ts:393 vs EXPORT_HEADER (⚠ inferensi: dinilai tidak memerahkan test karena di-stub; tidak dijalankan)

## v6 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (mobile/tests/e2e/laporan-storage-tank.spec.ts, backend/app/Services/StorageTankReportService.php).
- implementation_notes[10] ← koreksi 'TEPAT 4 kolom konteks' → 7 (25 kolom).
- known_issues[5] ← DITUTUP (stub CSV 25 kolom).
- implementation_notes ← append REVISI 2026-10-05.

## v7 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit e4f231e, d5da9cf, ee5294c), code is truth (mobile/src/views/LaporanStorageTankView.vue, mobile/src/utils/latestRequest.ts, tests/laporanStaleResponse.spec.ts, tests/e2e/laporan-stale-response.spec.ts).
- fe_files_generated ← mobile/src/utils/latestRequest.ts.
- fe_test_files_generated ← mobile/tests/laporanStaleResponse.spec.ts.
- test_files_generated ← mobile/tests/e2e/laporan-stale-response.spec.ts.
- implementation_notes ← 4 bug diperbaiki + uji.
- ⚠ test_results tidak diukur ulang per layar (angka dari pesan commit).
