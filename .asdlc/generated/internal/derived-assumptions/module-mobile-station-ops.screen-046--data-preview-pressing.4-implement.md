# Derived Assumptions Log — module-mobile-station-ops.screen-046--data-preview-pressing.4-implement

## v3 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Test yang dikutip: backend/tests/Feature/Api/RecordVerificationTest.php (blok kunci periode) dan backend/tests/Unit/Support/EnforcesPeriodLockTest.php; dicatat bahwa mobile/tests/recordVerification.spec.ts belum punya kasus PERIOD_CLOSED khusus (hanya offline). test_results tidak disentuh.

## v4 — 2026-10-03

Sinkronisasi catatan uji 4-implement dengan uji yang ditambahkan 2026-10-03.
- Tidak ada known_issue yang menyatakan uji mobile PERIOD_CLOSED belum ada — celah itu hanya tercatat di catatan REVISI kunci periode (implementation_notes), jadi tidak ada yang dihapus; catatan REVISI v4 menyatakannya tertutup.
- Uji baru berada di mobile/tests/recordVerification.spec.ts (komponen bersama RecordVerificationActions), bukan di spec layar ini; berkas itu tidak ditambahkan ke fe_test_files_generated dan test_results tidak diubah karena jumlah uji per layar tidak berubah.

## v5 — 2026-10-03

Run penuh Playwright mobile 2026-10-03: 430 lulus, 0 gagal.
- test_results.browser = 4/0 (sebelumnya kosong).
- known_issue 'spec tidak dapat dijalankan di sandbox' dihapus — kini dijalankan nyata.
- Spec diperbaiki hari ini (drift spec, bukan cacat aplikasi; hanya mobile/tests/e2e yang berubah): 'Back dari Mode Detail' — cek negatif not.toHaveURL dengan glob '**/…' (digabung ke baseURL, tak pernah cocok) diganti RegExp.

## v6 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git status/diff mobile + backend).
- files_generated += RecordVerificationStatusController.php, RecordVerificationStatusService.php, routes/api.php ← endpoint GET verifikasi dipakai layar ini.
- test_files_generated += backend/tests/Feature/Api/MobileReadEndpointsTest.php.
- fe_files_generated += SyncFailureHint.vue, recordVerificationApi.ts, apiClient.ts, utils/localDate.ts ← diimpor/dipakai DataPreviewPressingView.vue.
- fe_test_files_generated += SyncFailureHint.spec.ts, syncService.sqljs.spec.ts, recordVerification.spec.ts, e2e/sync-and-verification.spec.ts.
- implementation_notes += REVISI 2026-10-04.
