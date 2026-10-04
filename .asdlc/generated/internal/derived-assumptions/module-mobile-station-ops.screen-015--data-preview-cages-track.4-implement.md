# Derived Assumptions Log — module-mobile-station-ops.screen-015--data-preview-cages-track.4-implement

## v1 — 2026-08-18

- Reuse getDraftWithTippedTimes() yang sudah ada (screen-012) daripada duplikat method baca
- cages_tipped_time rows dirender sebagai list read-only sederhana, mirror pola DataPreviewGradingView.vue (screen-014)
- Route path /stations/cages-track/preview/:id? mirror pola screen-013/014

## Temuan gap lintas-screen (konsisten dengan screen-013/014)
MonitorCagesTrackView.vue (screen-009) onOpenDataPreview() navigasi ke data-preview-cages-track TANPA parameter id — dikonfirmasi via inspeksi kode langsung, situasi identik dengan screen-007/013 dan screen-008/014. Route sendiri benar terdaftar, hanya belum dipakai dengan id oleh pemanggilnya.

## v4 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Test yang dikutip: backend/tests/Feature/Api/RecordVerificationTest.php (blok kunci periode) dan backend/tests/Unit/Support/EnforcesPeriodLockTest.php; dicatat bahwa mobile/tests/recordVerification.spec.ts belum punya kasus PERIOD_CLOSED khusus (hanya offline). test_results tidak disentuh.

## v5 — 2026-10-03

Sinkronisasi catatan uji 4-implement dengan uji yang ditambahkan 2026-10-03.
- Tidak ada known_issue yang menyatakan uji mobile PERIOD_CLOSED belum ada — celah itu hanya tercatat di catatan REVISI kunci periode (implementation_notes), jadi tidak ada yang dihapus; catatan REVISI v5 menyatakannya tertutup.
- Uji baru berada di mobile/tests/recordVerification.spec.ts (komponen bersama RecordVerificationActions), bukan di spec layar ini; berkas itu tidak ditambahkan ke fe_test_files_generated dan test_results tidak diubah karena jumlah uji per layar tidak berubah.

## v6 — 2026-10-03

Run penuh Playwright mobile 2026-10-03: 430 lulus, 0 gagal.
- test_results.browser 101 (hitungan seluruh suite, keliru) → 11/0 (hitungan spec layar ini).
- Spec diperbaiki hari ini (drift spec, bukan cacat aplikasi; hanya mobile/tests/e2e yang berubah): assert blok status verifikasi RecordVerificationStatus; cek negatif not.toHaveURL dengan glob '**/…' (digabung ke baseURL, tak pernah cocok) diganti RegExp.

## v7 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git status/diff mobile + backend).
- files_generated += RecordVerificationStatusController.php, RecordVerificationStatusService.php, routes/api.php.
- test_files_generated += backend/tests/Feature/Api/MobileReadEndpointsTest.php (stationType cages-track).
- fe_files_generated += SyncFailureHint.vue, recordVerificationApi.ts, apiClient.ts, utils/localDate.ts.
- fe_test_files_generated += SyncFailureHint.spec.ts, syncService.sqljs.spec.ts, recordVerification.spec.ts, e2e/sync-and-verification.spec.ts. ⚠ e2e ini tidak menyentuh layar Cages Track secara langsung — dicantumkan karena menguji perilaku bersama (hint/pull).
- implementation_notes += REVISI 2026-10-04 (termasuk koreksi sumber `date`: cagesTrackRecordRepo.createDraft() → nowLocalDateTimeString()).
