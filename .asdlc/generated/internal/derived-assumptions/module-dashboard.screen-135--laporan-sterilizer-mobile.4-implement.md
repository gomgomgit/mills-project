# Derived Assumptions — module-dashboard.screen-135--laporan-sterilizer-mobile.4-implement

## v1 — 2026-09-24 (ditulis susulan)

Artefak ini ditulis sehari setelah implementasinya selesai. Yang dicatat di sini adalah pilihan yang tidak ditentukan spec, ditambah dua hal yang hanya terlihat justru karena penulisannya tertunda.

- **`status` = `partial`, bukan `complete`** ← seluruh test yang ada hijau (39 component, 18 browser, 58 backend), tetapi lapisan repo `sterilizerReportRepo.ts` tidak punya unit test sama sekali. Kembarannya [[module-dashboard.screen-136--laporan-cages-track-mobile.4-implement]] punya 21 kasus yang mengunci hal-hal yang tidak dijaga component test. Menyebutnya `complete` akan menyembunyikan bahwa aturan "tidak ada perhitungan kedua di sisi ponsel" tidak terjaga test apa pun di layar ini.
- **Angka test diukur ulang pada 2026-09-24, bukan direkonstruksi** ← artefak susulan paling mudah diisi dengan angka dari ingatan atau dari laporan agen lama. Ketiga suite dijalankan ulang untuk mendapatkan angkanya.
- **Kekeliruan briefing saya dicatat di `implementation_notes`, bukan dihilangkan** ← saya memberi tahu agen bahwa `resolveBusinessUnit()` sudah menangani seluruh peran non-Admin; itu keliru dan memakan satu putaran agen penuh. Pelajaran itu yang membuat saya membaca fungsi yang sama sebelum membriefing screen-136, dan di sanalah ditemukan bahwa melebarkan `guardAccess()` saja menjatuhkan Operator ke cabang Admin. Menghapus catatan itu akan menghapus sebab dari akibatnya.
- **`files_generated` menyertakan berkas milik screen-129** (`routes/api.php`, `SterilizerReportService.php`) ← perluasan akses Operator memang MENGUBAH layar yang sudah jadi, bukan hanya menambah. Mencatatnya di sini supaya jejaknya tidak hilang saat orang menelusuri kenapa screen-129 berubah.
- **Tidak ada endpoint baru dan tidak ada tambahan api-index** ← keputusan user 2026-09-23. Dicatat karena artefak Phase 4 tanpa `files_generated` di sisi API mudah disalahbaca sebagai pekerjaan yang belum selesai.
- **Dep-graph tidak dapat dipakai sendirian untuk menjawab "apa yang sudah jadi"** ← selama sehari ia menyatakan `not_started` sementara kodenya sudah berjalan. Dicatat sebagai `known_issue` tingkat rendah karena itu sifat prosesnya, bukan cacat satu layar.

## v3 — 2026-10-03

Sinkronisasi artefak 4-implement dengan kode (kode tidak diubah).
- Pemetaan tipe test mengikuti konvensi artefak yang sudah ada, bukan konvensi brief backend: unit = spec repo mobile + productionLineRepo.spec; integration = Feature/Api Laporan*Test + Unit/Services *ReportServiceTest + KelolaProductionLineTest --filter options-for-report; component = spec view mobile.
- productionLineRepo.ts, ProductionLineController.php, ProductionLineService.php, dan KelolaProductionLineTest.php ditambahkan ke daftar berkas karena layar ini kini bergantung pada GET /api/production-lines/options-for-report (98c812b), walau berkas-berkas itu dipakai bersama kelima layar laporan mobile.
- Hitungan browser lama sengaja tidak diubah (Playwright tidak dijalankan); keusangannya dicatat sebagai known_issue severity low.
- Entri known_issues lama yang kini usang (mis. bentuk galat response?.status di screen-136) tidak dihapus; keusangannya dijelaskan di catatan revisi.

## v4 — 2026-10-03

Ukur ulang uji browser Playwright mobile 2026-10-03.
- Angka browser diambil dari run tujuh spec laporan mobile (Vite dev server + backend :8000, DemoAccountSeeder): 185 lolos, 0 gagal; failed = 0 dan run_at disetel 2026-10-03T00:00:00Z (jam tidak tercatat, dipakai tengah malam UTC).
- laporan-sterilizer.spec.ts: 24 lolos (naik dari 18 karena skenario bertambah).
- Known_issue "test_results.browser tidak diukur ulang" dihapus karena pengukuran ulang kini menutupnya; known_issue lain dibiarkan apa adanya.
