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

## v4 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git diff ReportingPilihStasiunView.vue + spec unit/e2e).
- implementation_notes[0], [3] = productionLineRepo/seed dipakai hanya saat cache stasiun kosong (bukan lagi "nol jaringan mutlak") ← bootstrapStationsWhenEmpty()
- known_issues[0] = ditandai DITUTUP (layar kosong bagi pengguna baru sudah diperbaiki bootstrap) — tidak dihapus karena patch tak bisa menghapus elemen list tanpa menulis ulang semua known_issues ⚠ (keputusan agen)
- implementation_notes += REVISI (2026-10-04, audit-fix)
- Daftar berkas tidak berubah: berkas uji yang menutup perubahan (ReportingPilihStasiunView.spec.ts, e2e/reporting-pilih-stasiun.spec.ts) sudah tercatat
- known_issues[1] (label panjang/bubble AI menutupi tile) dibiarkan — floatingSafeArea mungkin mengurangi tumpang tindih bubble tetapi tidak diverifikasi ⚠

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (mobile/tests/e2e/reporting-pilih-stasiun.spec.ts).
- known_issues[1] ← bagian 'tertutup bubble AI' DITUTUP.
- ⚠ known_issues[1] sisa 'label panjang terpotong' dibiarkan terbuka — tidak diverifikasi ulang terhadap kode saat ini.
- fe_test_files_generated ← + mobile/tests/e2e/reporting-pilih-stasiun.spec.ts (sebelumnya tidak tercatat).
- implementation_notes ← append REVISI 2026-10-05.

## v6 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit ee5294c, d5da9cf), code is truth (App.vue, utils/floatingSafeArea.ts, ReportingPilihStasiunView.vue).
- fe_test_files_generated ← e2e/floating-safe-area.spec.ts, floatingSafeArea.spec.ts.
- known_issues[1] ← bagian tile tertutup kini ditutup penuh oleh dok (semua posisi gulir).
- implementation_notes ← LoadingState grid + dok mengambang + uji.
