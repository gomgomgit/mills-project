# Derived Assumptions Log — module-mobile-station-ops.screen-012--form-cages-track.4-implement

## v1 — 2026-08-18

- Route path pakai /stations/cages-track/form/:id (bukan /stations/cages-track/form tanpa id seperti tech-spec) — mengikuti presedan screen-010/011, param :id diperlukan untuk load draft by route param
- saveDraft() sekuensial (header UPDATE lalu tipped-time upsert per baris), bukan transaksi atomik — sama seperti gradingRecordRepo.ts, localDb.ts belum punya transaction primitive
- CagesTippedTimeGrid.vue mirror pola UX GradingDetailGrid.vue (screen-011)
- Validasi "minimal 1 baris" dan role-stripping checked_by ditegakkan di 2 lapis: repo (CagesTippedTimeRequiredError) dan view (pre-check client-side)

## Catatan proses (bukan derived assumption teknis)
Selama implementasi screen ini, sub-agent code-writer dan test-writer melaporkan tool Read/Edit tidak tersedia di sesi mereka (hanya Bash+Write) — bekerja-sekitar via pola tulis-file-baru+mv atau cat+Write. Setiap file yang dihasilkan lewat pola ini (cagesTrackRecordRepo.ts, router/index.ts, cagesTrackRecordRepo.spec.ts, FormCagesTrackView.spec.ts) diverifikasi langsung oleh command (dibaca penuh) sebelum dianggap aman — semua isinya benar dan konsisten dengan pola screen sebelumnya, tidak ada kode berbahaya atau kehilangan data.

## v5 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Berkas test yang dikutip dipilih sendiri: backend/tests/Unit/Support/EnforcesPeriodLockTest.php, backend/tests/Feature/Api/KelolaPeriodePelaporanTest.php, mobile/tests/syncService.spec.ts. Tidak ada test (mobile) yang memakai respons PERIOD_CLOSED secara spesifik; test mobile yang dikutip hanya mencakup galat generik.
- test_results tidak disentuh; tidak ada test dijalankan (sesuai brief).

## v6 — 2026-10-03

Run penuh Playwright mobile 2026-10-03: 430 lulus, 0 gagal.
- test_results.browser = 17/0 (run_at diperbarui); spec tidak diubah.

## v7 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (cagesTrackRecordRepo.ts, FormCagesTrackView.vue, syncService.ts, millSettingRepo.ts, routes/api.php, tests baru di mobile/tests).
- fe_files_generated += utils/localDate.ts, services/syncService.ts, services/millSettingRepo.ts ← diimpor repo (localDate) / jalur sinkron / getJumlahCages. (⚠ syncService tidak diimpor view langsung.)
- fe_test_files_generated += localDate.sqljs.spec.ts, syncService.sqljs.spec.ts, millSettingRepo.sqljs.spec.ts, noteLabelConsistency.spec.ts, DialogTeleport.spec.ts ← menguji tippler_start_time lokal, payload/line/sync_error, SELECT mill_setting, label Catatan, teleport dialog. (⚠ DialogTeleport/millSettingRepo cakupan tidak langsung.)
- known_issues -= "station_id belum diisi createDraft()" ← createDraft memakai resolveActiveStationId (commit 845009c, sebelum audit).
- known_issues -= "GET /api/mill-settings/current BELUM diimplementasikan" ← routes/api.php:445 Route::get('/mill-settings/current').
- implementation_notes += REVISI 2026-10-04 ← diff terkait.

## v8 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit d5da9cf, e4f231e, ee5294c), code is truth (mobile/src/views/Form*View.vue, useBusyAction/BusyLabel/LoadingState, SearchableSelect.vue, App.vue).
- implementation_notes ← penjaga aksi ganda Simpan/Pause/Clear (actionInProgress), await router.push, BusyLabel, LoadingState variant form, dok mengambang; berkas uji bersama (loadingStates.screens.spec.ts, floating-safe-area.spec.ts) disebut di catatan, tidak didaftarkan.
- ⚠ 2-business-spec form TIDAK diubah: tidak ada teks spec yang menggambarkan label tombol sibuk/SearchableSelect, dan penjaga ketuk ganda dinilai pola UI bersama (didokumentasikan pemanggil di shared-decisions), bukan aturan bisnis per layar.
