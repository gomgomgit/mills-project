# Derived Assumptions Log — module-web-station-data.screen-112--form-process-water-web.4-implement

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Isi catatan REVISI (urutan pemanggilan guard, file test yang dikutip) diturunkan dari pembacaan kode service/Livewire/test saat ini, bukan dari laporan run test; test_results sengaja tidak disentuh dan suite tidak dijalankan.

## v3 — 2026-10-03

Sinkronisasi catatan uji 4-implement dengan uji yang ditambahkan 2026-10-03.
- test_results.integration.passed = 19, dihitung dari jumlah `it(` di backend/tests/Feature/Api/FormProcessWaterTest.php (failed 0, run_at 2026-10-03T00:00:00Z); unit/component/browser tidak diubah karena tidak dijalankan ulang untuk sinkronisasi ini.
- Tidak ada known_issue yang secara eksplisit menyatakan penolakan kunci periode per-stasiun belum diuji, jadi known_issues dibiarkan; catatan REVISI kunci periode sebelumnya ("test per-layar membuka periode Terbuka sebagai prasyarat") dilengkapi oleh catatan REVISI baru, bukan diubah.
- backend/tests/Feature/Api/FormProcessWaterTest.php sudah ada di test_files_generated, tidak ditambahkan ulang.

## v4 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser = 7 lulus, 0 gagal, run_at 2026-10-03T00:00:00Z. Angka lulus/gagal/skip diambil dari e2e-full.log.counts.json (bukan dihitung ulang).
- known_issue 'Browser (Playwright) spec ... could not be executed in this sandboxed environment' dihapus (satu-satunya known_issue).
- e2e-web/tests/form-process-water.spec.ts ditambahkan ke test_files_generated (belum ada di daftar berkas mana pun).

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git diff HEAD FormProcessWater.php, ProcessWaterRecordService.php; untracked GuardsRecordIdShape.php, AppTime.php, tests).
- files_generated += AppTime.php, EnforcesPeriodLock.php, ScopesToActorMill.php, ApiExceptionHandler.php ⚠ shared support files, dicantumkan karena perilaku create/update layar ini berubah lewatnya.
- fe_files_generated += GuardsRecordIdShape.php ← use GuardsRecordIdShape di FormProcessWater.php.
- fe_test_files_generated += RecordIdShapeGuardTest.php (FormProcessWater edit ada di dataset 18 stasiun), WebFormNoDisabledFieldTest.php (cek statis semua view form-*).
- implementation_notes += REVISI audit-fix 2026-10-04 (view tidak berubah).
