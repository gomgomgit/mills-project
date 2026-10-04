# Derived Assumptions Log — module-mobile-station-ops.screen-123--data-preview-sterilizer.4-implement

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Test yang dikutip: backend/tests/Feature/Api/RecordVerificationTest.php (blok kunci periode) dan backend/tests/Unit/Support/EnforcesPeriodLockTest.php; dicatat bahwa mobile/tests/recordVerification.spec.ts belum punya kasus PERIOD_CLOSED khusus (hanya offline). test_results tidak disentuh.

## v3 — 2026-10-03

Sinkronisasi catatan uji 4-implement dengan uji yang ditambahkan 2026-10-03.
- Tidak ada known_issue yang menyatakan uji mobile PERIOD_CLOSED belum ada — celah itu hanya tercatat di catatan REVISI kunci periode (implementation_notes), jadi tidak ada yang dihapus; catatan REVISI v3 menyatakannya tertutup.
- Uji baru berada di mobile/tests/recordVerification.spec.ts (komponen bersama RecordVerificationActions), bukan di spec layar ini; berkas itu tidak ditambahkan ke fe_test_files_generated dan test_results tidak diubah karena jumlah uji per layar tidak berubah.

## v4 — 2026-10-03

Spec e2e mobile baru + run penuh 727 lulus / 0 gagal.
- mobile/tests/e2e/data-preview-sterilizer.spec.ts ditambahkan ke fe_test_files_generated; test_results.browser = 6 lulus / 0 gagal (run_at 2026-10-03T00:00:00Z, jumlah dari run penuh suite).
- known_issue 'spec browser/E2E belum ada' dihapus; known_issue lain dibiarkan.

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git status/diff mobile + backend).
- files_generated += RecordVerificationStatusController.php, RecordVerificationStatusService.php, routes/api.php ← endpoint GET verifikasi baru dipakai layar ini.
- test_files_generated += backend/tests/Feature/Api/MobileReadEndpointsTest.php ← menguji endpoint GET verifikasi.
- fe_files_generated += SyncFailureHint.vue, recordVerificationApi.ts, apiClient.ts, utils/localDate.ts ← diimpor/dipakai DataPreviewSterilizerView.vue. ⚠ recordVerificationApi.ts/apiClient.ts sebelumnya sudah dipakai tapi tidak terdaftar — ditambahkan karena berubah di audit ini.
- fe_test_files_generated += SyncFailureHint.spec.ts, syncService.sqljs.spec.ts, recordVerification.spec.ts, noteLabelConsistency.spec.ts (iterasi semua view DataPreview*), e2e/sync-and-verification.spec.ts. ⚠ e2e itu tidak menguji stasiun ini secara langsung (pola sama, stasiun lain).
- implementation_notes += REVISI 2026-10-04 (pull verifikasi, /api prefix, network flag, SyncFailureHint, localDate, label Catatan).
