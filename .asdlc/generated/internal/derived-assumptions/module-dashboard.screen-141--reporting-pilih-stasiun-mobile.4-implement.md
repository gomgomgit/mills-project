# Derived Assumptions Log — module-dashboard.screen-141--reporting-pilih-stasiun-mobile.4-implement

## v2 — 2026-10-03

Sinkronisasi artefak 4-implement dengan kode (kode tidak diubah).
- Hitungan unit dan component sama-sama diisi dari mobile/tests/ReportingPilihStasiunView.spec.ts (36), mengikuti konvensi v1 yang mencatat berkas yang sama di kedua tipe.
- DashboardReportingView.spec.ts dijalankan (16 lolos) tetapi tidak dihitung, karena miliknya screen-134.
- deferred_items v1 ('17 stasiun lain') dan catatan '1 tile aktif' tidak dihapus; keusangannya dijelaskan di catatan revisi.
- Tidak ada berkas baru yang ditambahkan ke daftar files: perubahan 98c812b hanya menyentuh ReportingPilihStasiunView.vue dan spec-nya, yang sudah tercantum.

## v3 — 2026-10-03

Ukur ulang uji browser Playwright mobile 2026-10-03.
- Angka browser diambil dari run tujuh spec laporan mobile (Vite dev server + backend :8000, DemoAccountSeeder): 185 lolos, 0 gagal; failed = 0 dan run_at disetel 2026-10-03T00:00:00Z (jam tidak tercatat, dipakai tengah malam UTC).
- reporting-pilih-stasiun.spec.ts: 15 lolos (naik dari 13 karena skenario bertambah).
- Known_issue "test_results.browser tidak diukur ulang" dihapus karena pengukuran ulang kini menutupnya; known_issue lain dibiarkan apa adanya.
