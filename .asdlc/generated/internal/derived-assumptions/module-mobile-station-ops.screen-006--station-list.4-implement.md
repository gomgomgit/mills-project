# Derived Assumptions Log — module-mobile-station-ops.screen-006--station-list.4-implement

## v1 — 2026-08-17

- stationRepo.ts mengasumsikan 15 baris station (3 aktif + 12 placeholder) sudah ada di tabel lokal via sync sebelumnya, tidak disintesis di kode
- StationGrid.vue menghindari atribut disabled native (akan menekan tap) — pakai aria-disabled + styling agar tap tetap bisa memunculkan pesan "belum tersedia"
- Router pakai konvensi meta: { public: false } (bukan requiresAuth: true) mengikuti guard yang sudah ada
- Belum ada skema/migration lokal formal untuk tabel station SQLite — screen ini mengasumsikan tabel+baris sudah ada via sync, konsisten dengan asumsi localDb.ts

## v6 — 2026-08-18

- Draft-status detection REUSE 3 repo yang sudah ada (weighbridgeRecordRepo.getSummary, gradingRecordRepo/cagesTrackRecordRepo.getProgressSummary) alih-alih membuat service query baru ← draftRecordsRepo.ts generik sudah dihapus saat revisi Home (dead code, hanya dipakai fitur yang sudah dihapus) — keputusan sadar untuk tidak menghidupkannya lagi, cukup pakai fungsi summary per-stasiun yang sudah ada dan sudah teruji
- Setiap panggilan repo draft-status di-.catch(()=>null) independen (best-effort per repo, bukan all-or-nothing) ← bukan requirement eksplisit di tech-spec, ditambahkan agar satu repo gagal tidak memblokir 2 lainnya
- Header brand+hamburger di StationListView.vue adalah salinan persis pola HomeView.vue (nama variabel/class sama) ← demi konsistensi visual & perilaku lintas layar, bukan diminta secara literal "harus identik" oleh user
- Padding tile dinaikkan dari 10px 6px ke 22px 6px ← angka spesifik dipilih agen, user hanya minta "lebih besar" tanpa angka pasti

## v7 — 2026-08-19

- ICON_OVERRIDES di StationGrid.vue memakai persis 10 nama (gauge/layers/package/truck/scale/warehouse/factory/container/box/boxes) ← tech-spec v4/v5 hanya bilang "nama icon Lucide yang dikenali", tidak mendaftar nama pastinya; agen menemukan vocabulary sebenarnya dengan membaca `MillSettingService::SUPPORTED_ICONS` di backend agar konsisten dengan apa yang benar-benar bisa dipilih Admin di Mills Setting
- SVG path tiap icon override ditulis tangan (bukan dari library Lucide asli, karena package `lucide-vue-next` tidak terpasang di mobile) ← konsisten dengan pola 15 icon existing lain di file yang sama, sudah ada known_issue serupa sebelumnya
- Icon override HANYA berlaku untuk tile aktif, tidak pernah untuk tile disabled/placeholder ← translasi langsung dari business_logic step 3 tech-spec, bukan asumsi baru
- 1 test e2e di luar scope (form-cages-track.spec.ts) ditemukan gagal saat regresi penuh, dicatat sebagai known_issue tapi TIDAK diperbaiki (di luar directive screen-006 only) ← disiplin scope, bukan diabaikan begitu saja

## v13 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Berkas test yang dikutip dipilih sendiri: backend/tests/Unit/Support/EnforcesPeriodLockTest.php, backend/tests/Feature/Api/KelolaPeriodePelaporanTest.php, mobile/tests/syncService.spec.ts, mobile/tests/StationListView.spec.ts. Tidak ada test (mobile) yang memakai respons PERIOD_CLOSED secara spesifik; test mobile yang dikutip hanya mencakup galat generik.
- test_results tidak disentuh; tidak ada test dijalankan (sesuai brief).

## v14 — 2026-10-03

Sinkronisasi catatan uji 4-implement dengan uji yang ditambahkan 2026-10-03.
- Catatan lama 'Belum ada test mobile PERIOD_CLOSED' berada di implementation_notes (bukan known_issues), jadi tidak dihapus; disupersede oleh catatan REVISI v14.
- mobile/src/services/syncService.ts ditambahkan ke fe_files_generated dan mobile/tests/syncService.spec.ts ke fe_test_files_generated karena keduanya belum tercatat.
- test_results tidak diubah — hanya satu uji baru di syncService.spec.ts yang dikonfirmasi; jumlah total uji screen ini tidak dihitung ulang.

## v15 — 2026-10-03

Run penuh Playwright mobile 2026-10-03: 430 lulus, 0 gagal.
- test_results.browser = 6/0.
- Spec diperbaiki hari ini (drift spec, bukan cacat aplikasi; hanya mobile/tests/e2e yang berubah): lewat pemilih Production Line dulu; kasus tile nonaktif men-seed placeholder inaktif; kasus override ikon memblokir re-sync stasiun.

## v16 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git status/diff mobile + backend).
- files_generated += GradingParameterController.php, GradingParameterService.php, routes/api.php ← GET /api/grading-parameters dipakai sinkronisasi layar ini.
- test_files_generated += backend/tests/Feature/Api/MobileReadEndpointsTest.php (grading-parameters).
- fe_files_generated += SyncResultDialog.vue, gradingParameterSync.ts, millSettingRepo.ts, utils/localDate.ts, utils/floatingSafeArea.ts, App.vue ← dipakai/diubah untuk alur Sinkronisasi & tampilan layar ini. ⚠ App.vue/floatingSafeArea.ts bersifat global (semua layar), dicantumkan di sini karena Station List pemilik tombol Sinkronisasi/dialog.
- fe_test_files_generated += syncService.sqljs.spec.ts, SyncFailureHint.spec.ts, DialogTeleport.spec.ts, floatingSafeArea.spec.ts, millSettingRepo.sqljs.spec.ts, writeThroughSync.spec.ts, e2e/sync-and-verification.spec.ts.
- implementation_notes += REVISI 2026-10-04 (line per record, sync_error, grading param resolve, write-through fix, dialog teleport, safe area, 'Stasiun Aktif').

## v17 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (mobile/src/services/syncService.ts, mobile/src/services/apiClient.ts, mobile/tests/syncService.sqljs.spec.ts, mobile/tests/e2e/sync-and-verification.spec.ts).
- fe_files_generated ← + apiClient.ts.
- fe_test_files_generated ← + apiClient.unauthorized.spec.ts.
- implementation_notes ← append catatan #3 dan #4b.

## v18 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit d5da9cf, ee5294c), code is truth (StationListView.vue, utils/floatingSafeArea.ts, App.vue).
- fe_test_files_generated ← mobile/tests/e2e/floating-safe-area.spec.ts (layar ini pemilik floatingSafeArea.ts).
- implementation_notes ← penjaga sync ganda + uji, dok mengambang + uji.
