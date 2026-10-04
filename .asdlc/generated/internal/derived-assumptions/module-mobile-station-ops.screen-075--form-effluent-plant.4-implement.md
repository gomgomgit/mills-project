# Derived Assumptions Log — module-mobile-station-ops.screen-075--form-effluent-plant.4-implement

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Berkas test yang dikutip dipilih sendiri: backend/tests/Unit/Support/EnforcesPeriodLockTest.php, backend/tests/Feature/Api/KelolaPeriodePelaporanTest.php, mobile/tests/syncService.spec.ts, mobile/tests/writeThroughSync.spec.ts. Tidak ada test (mobile) yang memakai respons PERIOD_CLOSED secara spesifik; test mobile yang dikutip hanya mencakup galat generik.
- test_results tidak disentuh; tidak ada test dijalankan (sesuai brief).

## v3 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- fe_files_generated ditambah mobile/src/services/writeThroughSync.ts dan mobile/src/components/ConfirmDialog.vue (berkas yang diubah untuk perilaku penolakan).
- Catatan REVISI kunci periode: 'penolakannya DIAM' ditandai sebagai perilaku lama, merujuk catatan REVISI (2026-10-03) baru.
- Catatan REVISI (2026-10-03) baru: cakupan test generik di writeThroughSync.spec.ts; tidak ada component test khusus layar ini, FormThreshingView.spec.ts sebagai representatif.

## v4 — 2026-10-03

Run penuh Playwright mobile 2026-10-03: 430 lulus, 0 gagal.
- test_results.browser = 8/0 (sebelumnya kosong).
- known_issue 'spec tidak dapat dijalankan di sandbox' dihapus — kini dijalankan nyata.
- Spec tidak diubah — tidak ada catatan REVISI.

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git status/diff mobile/, tests baru di mobile/tests/).
- fe_files_generated (+) = mobile/src/utils/localDate.ts, mobile/src/services/millSettingRepo.ts, mobile/src/services/syncService.ts ← dipakai jalur draft-date / write-through layar ini (⚠ inferensi: atribusi berkas bersama ke layar ini, bukan berkas milik layar)
- fe_test_files_generated (+) = noteLabelConsistency, DialogTeleport, localDate.sqljs, writeThroughSync, millSettingRepo.sqljs, syncService.sqljs (*.spec.ts) ← tes baru/berubah yang mencakup label Catatan, ConfirmDialog, createDraft lokal & normalisasi UTC, write-through, sync_error/line per record (⚠ inferensi: cakupan generik lintas stasiun, bukan tes khusus layar)
- implementation_notes (append) = REVISI 2026-10-04 audit-fix (label Catatan, date lokal, write-through aktif, sync_error, line per record, ConfirmDialog teleport) ← diff kode terkait
