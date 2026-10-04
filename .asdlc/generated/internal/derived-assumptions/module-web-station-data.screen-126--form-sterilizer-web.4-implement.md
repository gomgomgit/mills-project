# Derived Assumptions Log — module-web-station-data.screen-126--form-sterilizer-web.4-implement

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Isi catatan REVISI (urutan pemanggilan guard, file test yang dikutip) diturunkan dari pembacaan kode service/Livewire/test saat ini, bukan dari laporan run test; test_results sengaja tidak disentuh dan suite tidak dijalankan.
- Stasiun ini satu-satunya yang diuji end-to-end oleh KelolaPeriodePelaporanTest.php (POST/PATCH /api/sterilizer-records), jadi catatan menyebut cakupan langsung tersebut.

## v3 — 2026-10-03

Sinkronisasi catatan uji 4-implement dengan uji yang ditambahkan 2026-10-03.
- test_results.integration.passed = 18, dihitung dari jumlah `it(` di backend/tests/Feature/Api/FormSterilizerTest.php (failed 0, run_at 2026-10-03T00:00:00Z); unit/component/browser tidak diubah karena tidak dijalankan ulang untuk sinkronisasi ini.
- Tidak ada known_issue yang secara eksplisit menyatakan penolakan kunci periode per-stasiun belum diuji, jadi known_issues dibiarkan; catatan REVISI kunci periode sebelumnya ("test per-layar membuka periode Terbuka sebagai prasyarat") dilengkapi oleh catatan REVISI baru, bukan diubah.
- backend/tests/Feature/Api/FormSterilizerTest.php sudah ada di test_files_generated, tidak ditambahkan ulang.

## v4 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser = 2 lulus, 0 gagal, run_at 2026-10-03T00:00:00Z. Angka lulus/gagal/skip diambil dari e2e-full.log.counts.json (bukan dihitung ulang).
- known_issue 'Browser test dibuat tapi tidak dijalankan — tidak ada browser di sandbox' dihapus; known_issue lain dipertahankan.
- Path e2e-web/tests/form-sterilizer.spec.ts ditambahkan ke test_files_generated (belum ada di daftar berkas mana pun).

## v5 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/FormSterilizerTest.php` dihapus dari `fe_test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).

## v6 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git diff HEAD SterilizerRecordService.php, FormSterilizer.php, form-sterilizer.blade.php; untracked GuardsRecordIdShape.php, tests).
- files_generated += AppTime.php, EnforcesPeriodLock.php, ScopesToActorMill.php, ApiExceptionHandler.php, config/app.php (⚠ shared support files; AppTime dipakai lewat EnforcesPeriodLock::latestEventDate, bukan normalisasi langsung di service ini).
- fe_files_generated += GuardsRecordIdShape.php.
- test_files_generated += AuditFix20261004Test.php; fe_test_files_generated += WebFormNoDisabledFieldTest.php, RecordIdShapeGuardTest.php.
- implementation_notes[7] = Checked by SPV per baris kini Supervisor-only; += REVISI audit-fix.

## v7 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (SterilizerRecordService.php).
- test_files_generated (+AuditFix20261005Test), implementation_notes (+1), test_results unit 35 / integration 18+2=20 / component 14+2=16 (dijalankan ulang; +2 masing-masing dari AuditFix20261005 [spv]).
