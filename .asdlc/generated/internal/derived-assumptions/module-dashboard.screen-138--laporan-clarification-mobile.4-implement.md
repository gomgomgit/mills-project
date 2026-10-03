# Derived Assumptions Log — module-dashboard.screen-138--laporan-clarification-mobile.4-implement

## v2 — 2026-10-03

Sinkronisasi artefak 4-implement dengan kode (kode tidak diubah).
- Pemetaan tipe test mengikuti konvensi artefak yang sudah ada, bukan konvensi brief backend: unit = spec repo mobile + productionLineRepo.spec; integration = Feature/Api Laporan*Test + Unit/Services *ReportServiceTest + KelolaProductionLineTest --filter options-for-report; component = spec view mobile.
- productionLineRepo.ts, ProductionLineController.php, ProductionLineService.php, dan KelolaProductionLineTest.php ditambahkan ke daftar berkas karena layar ini kini bergantung pada GET /api/production-lines/options-for-report (98c812b), walau berkas-berkas itu dipakai bersama kelima layar laporan mobile.
- Hitungan browser lama sengaja tidak diubah (Playwright tidak dijalankan); keusangannya dicatat sebagai known_issue severity low.
- Entri known_issues lama yang kini usang (mis. bentuk galat response?.status di screen-136) tidak dihapus; keusangannya dijelaskan di catatan revisi.

## v3 — 2026-10-03

Ukur ulang uji browser Playwright mobile 2026-10-03.
- Angka browser diambil dari run tujuh spec laporan mobile (Vite dev server + backend :8000, DemoAccountSeeder): 185 lolos, 0 gagal; failed = 0 dan run_at disetel 2026-10-03T00:00:00Z (jam tidak tercatat, dipakai tengah malam UTC).
- laporan-clarification.spec.ts: 35 lolos (naik dari 29 karena skenario bertambah).
- Perbaikan asersi flaky expectSingleColumn() (menunggu kartu dirender sebelum cards.count()) dicatat sebagai perubahan berkas test saja, bukan kode produksi; verifikasi 60/60 dalam 5 ulangan dianggap cukup untuk menyatakan stabil.
- Known_issue "test_results.browser tidak diukur ulang" dihapus karena pengukuran ulang kini menutupnya; known_issue lain dibiarkan apa adanya.
