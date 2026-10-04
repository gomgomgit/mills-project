# Derived Assumptions — module-dashboard.screen-136--laporan-cages-track-mobile.4-implement

## v1 — 2026-09-24

- **`/business-units/options` tetap 403 untuk Operator** ← briefing saya menulis "Operator DITERIMA (200) di keempat endpoint". Agen menyimpang dan benar: endpoint itu Admin-only di dalam service, dan Operator terikat mill akunnya sehingga tidak pernah butuh daftar pilihan mill. Akses lebih SEMPIT dari yang disetujui, jadi tidak menuntut keputusan ulang.
- **Badan `resolveBusinessUnit()` Sterilizer tidak disalin** ← briefing saya menyuruh "tiru pola di SterilizerReportService baris 120". Versi Sterilizer tidak punya pemeriksaan gagal-tertutup 422; menyalinnya utuh akan menurunkan Cages & Tracks. Hanya satu disjungsi yang ditambahkan. Diverifikasi ulang di kode setelah implementasi.
- **`Sanctum::actingAs()` untuk skenario Operator**, bukan `actingAs($user,'web')` ← idiom test Sterilizer memakai sesi web. Memakainya di sini akan membuat guard `sanctum` yang justru menjadi alasan perubahan rute tidak pernah tersentuh test.
- **Viewport e2e disetel eksplisit 390x844** ← project Playwright repo ini Desktop Chrome 1280px. Tanpa penyetelan ini, asersi "satu kolom / tanpa gulir mendatar / sasaran sentuh 44px" lolos tanpa menguji apa pun. Tidak diminta spec.
- **Asersi collapsible ditulis sebagai visibilitas, bukan ketiadaan DOM** ← `CollapsibleSection.vue` memakai `v-show`. Ditambah temuan jsdom: `getComputedStyle` di-cache per elemen, sehingga setelah badan terbaca `display:none` seluruh keturunannya terbaca invisible seterusnya pada pohon yang sama. Asersi "terlihat setelah dibuka" karena itu dibuktikan pada wrapper yang di-mount ulang. Berlaku untuk test collapsible mana pun di repo ini.
- **Perbaikan auto-fix menyentuh test, bukan kode produksi** ← `expect(api.hits.summary).toBe(1)` membaca counter secara sinkron saat permintaan masih in-flight. Didiagnosis sebagai cacat test lewat tiga bukti terpisah (trace, DOM saat gagal, reproduksi di bawah beban CPU), bukan disimpulkan dari tebakan. Asersinya tetap `toBe(1)` persis; tidak ada retry maupun timeout yang ditambahkan.
- **Helper `pickPeriod()` bersama sengaja tidak diubah** ← test "jaringan gagal" memakai `route.abort('failed')` yang tidak pernah memicu event `response`; menambahkan `waitForResponse` ke helper akan menggantung test itu selamanya. Perbaikan dipasang di pemanggil, bukan di helper.
- **`normalizeKpi` tidak dibuat** ← Cages & Tracks tidak punya penamaan ganda seperti Sterilizer (`avg_duration_minutes` vs `avg_duration`), jadi tidak ada yang perlu dinormalisasi. Menyalin fungsi itu berarti membuat kode yang tidak pernah dipakai.
- **Tombol Back mengarah ke Dashboard & Reporting, bukan pemilih stasiun** ← mengikuti screen-135 agar kedua layar laporan mobile seragam. Dicatat sebagai `deferred_item` karena ini pilihan, bukan keharusan.

## v2 — 2026-10-03

Sinkronisasi artefak 4-implement dengan kode (kode tidak diubah).
- Pemetaan tipe test mengikuti konvensi artefak yang sudah ada, bukan konvensi brief backend: unit = spec repo mobile + productionLineRepo.spec; integration = Feature/Api Laporan*Test + Unit/Services *ReportServiceTest + KelolaProductionLineTest --filter options-for-report; component = spec view mobile.
- productionLineRepo.ts, ProductionLineController.php, ProductionLineService.php, dan KelolaProductionLineTest.php ditambahkan ke daftar berkas karena layar ini kini bergantung pada GET /api/production-lines/options-for-report (98c812b), walau berkas-berkas itu dipakai bersama kelima layar laporan mobile.
- Hitungan browser lama sengaja tidak diubah (Playwright tidak dijalankan); keusangannya dicatat sebagai known_issue severity low.
- Entri known_issues lama yang kini usang (mis. bentuk galat response?.status di screen-136) tidak dihapus; keusangannya dijelaskan di catatan revisi.

## v3 — 2026-10-03

Ukur ulang uji browser Playwright mobile 2026-10-03.
- Angka browser diambil dari run tujuh spec laporan mobile (Vite dev server + backend :8000, DemoAccountSeeder): 185 lolos, 0 gagal; failed = 0 dan run_at disetel 2026-10-03T00:00:00Z (jam tidak tercatat, dipakai tengah malam UTC).
- laporan-cages-track.spec.ts: 31 lolos (naik dari 25 karena skenario bertambah).
- Perbaikan asersi flaky expectSingleColumn() (menunggu kartu dirender sebelum cards.count()) dicatat sebagai perubahan berkas test saja, bukan kode produksi; verifikasi 60/60 dalam 5 ulangan dianggap cukup untuk menyatakan stabil.
- Known_issue "test_results.browser tidak diukur ulang" dihapus karena pengukuran ulang kini menutupnya; known_issue lain dibiarkan apa adanya. Known_issue lama '19 test e2e mobile gagal' tidak disentuh karena menyangkut suite penuh, bukan run ini.

## v4 — 2026-10-03

Run penuh Playwright mobile 2026-10-03: 430 lulus, 0 gagal.
- known_issue '19 test e2e mobile gagal, pra-ada' dihapus.
- test_results.browser sudah 31/0 per 2026-10-03, tidak diubah.

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (LaporanCagesTrackView.vue, mobile/tests/LaporanCagesTrackView.spec.ts, mobile/tests/e2e/laporan-cages-track.spec.ts, CagesTrackReportService.php).
- files_generated[+] = backend/app/Support/SheetWriter.php, backend/app/Support/ExportValue.php ← endpoint ekspor bersama
- test_files_generated[+] = backend/tests/Feature/ReportAuditFix20261004Test.php, backend/tests/Feature/ExportXlsxTest.php ← menguji ekspor Cages Track bersama
- implementation_notes[+] = REVISI: 'Tertutup', 0 hari → NOT_AVAILABLE + kpi-avg-per-day-empty, CSV berubah ← diff view/spec
- known_issues tidak diubah (SyncResultDialog.vue hanya berubah indentasi/teleport, tidak terbukti memperbaiki unhandled rejection)
