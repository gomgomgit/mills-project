# Derived Assumptions Log — module-web-station-data.screen-114--form-cpo-dispatch-web.4-implement

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Isi catatan REVISI (urutan pemanggilan guard, file test yang dikutip) diturunkan dari pembacaan kode service/Livewire/test saat ini, bukan dari laporan run test; test_results sengaja tidak disentuh dan suite tidak dijalankan.
- Ditegaskan bahwa kunci periode memakai tanggal header `date`, BUKAN event_date per baris Log Kejadian (diverifikasi dari pemanggilan assertPeriodOpenForWrite di service).

## v3 — 2026-10-03

Sinkronisasi catatan uji 4-implement dengan uji yang ditambahkan 2026-10-03.
- test_results.integration.passed = 18, dihitung dari jumlah `it(` di backend/tests/Feature/Api/FormCpoDispatchTest.php (failed 0, run_at 2026-10-03T00:00:00Z); unit/component/browser tidak diubah karena tidak dijalankan ulang untuk sinkronisasi ini.
- Tidak ada known_issue yang secara eksplisit menyatakan penolakan kunci periode per-stasiun belum diuji, jadi known_issues dibiarkan; catatan REVISI kunci periode sebelumnya ("test per-layar membuka periode Terbuka sebagai prasyarat") dilengkapi oleh catatan REVISI baru, bukan diubah.
- backend/tests/Feature/Api/FormCpoDispatchTest.php sudah ada di test_files_generated, tidak ditambahkan ulang.

## v4 — 2026-10-03

Mencatat perbaikan kode/uji 2026-10-03.
- e2e-web/tests/form-cpo-dispatch.spec.ts (ikut diubah hari ini: pilih line berdasarkan label) ditambahkan ke fe_test_files_generated.
- test_results.unit tidak dinaikkan walau ada 1 uji regresi baru — tidak diminta.
- Known issue 'browser test tidak dijalankan' (merujuk backend/tests/Browser yang sudah dihapus) DIBIARKAN karena test_results.browser tidak diisi — hapus bila hitungan e2e dicatat.

## v5 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser = 2 lulus, 0 gagal, run_at 2026-10-03T00:00:00Z. Angka lulus/gagal/skip diambil dari e2e-full.log.counts.json (bukan dihitung ulang).
- known_issue 'Browser test dibuat tapi tidak dijalankan — tidak ada browser di sandbox' dihapus; known_issue lain dipertahankan.
- Path spec sudah ada di fe_test_files_generated — tidak ditambahkan lagi.

## v6 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/FormCpoDispatchTest.php` dihapus dari `fe_test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).

## v7 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git diff HEAD CpoDispatchRecordService.php, FormCpoDispatch.php, form-cpo-dispatch.blade.php; untracked AppTime.php, GuardsRecordIdShape.php, tests).
- files_generated += AppTime.php, EnforcesPeriodLock.php, ScopesToActorMill.php, ApiExceptionHandler.php, config/app.php (⚠ berkas bersama, dicantumkan karena perilaku layar berubah lewatnya).
- fe_files_generated += GuardsRecordIdShape.php.
- test_files_generated += AuditFix20261004Test.php; fe_test_files_generated += RecordIdShapeGuardTest.php, WebFormNoDisabledFieldTest.php.
- implementation_notes[3] = Net Weight teks; implementation_notes += REVISI audit-fix.
