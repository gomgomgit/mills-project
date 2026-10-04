# Derived Assumptions Log — module-web-station-data.screen-059--form-depricarping-web.4-implement

## v3 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Isi catatan REVISI (urutan pemanggilan guard, file test yang dikutip) diturunkan dari pembacaan kode service/Livewire/test saat ini, bukan dari laporan run test; test_results sengaja tidak disentuh dan suite tidak dijalankan.

## v4 — 2026-10-03

Sinkronisasi catatan uji 4-implement dengan uji yang ditambahkan 2026-10-03.
- test_results.integration.passed = 21, dihitung dari jumlah `it(` di backend/tests/Feature/Api/FormDepricarpingTest.php (failed 0, run_at 2026-10-03T00:00:00Z); unit/component/browser tidak diubah karena tidak dijalankan ulang untuk sinkronisasi ini.
- Tidak ada known_issue yang secara eksplisit menyatakan penolakan kunci periode per-stasiun belum diuji, jadi known_issues dibiarkan; catatan REVISI kunci periode sebelumnya ("test per-layar membuka periode Terbuka sebagai prasyarat") dilengkapi oleh catatan REVISI baru, bukan diubah.
- backend/tests/Feature/Api/FormDepricarpingTest.php sudah ada di test_files_generated, tidak ditambahkan ulang.

## v5 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser diisi 9 lulus/0 gagal dari e2e-full.log.counts.json (spec form-depricarping); run_at 2026-10-03T00:00:00Z.
- known_issue tunggal tentang spec Playwright backend/tests/Browser/* yang tidak dapat dijalankan dihapus (berkas itu sudah tidak ada; cakupan browser kini e2e-web) — known_issues jadi kosong.
- e2e-web/tests/form-depricarping.spec.ts ditambahkan ke test_files_generated (sebelumnya tidak tercantum di daftar mana pun).

## v6 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git diff HEAD FormDepricarping.php, DepricarpingRecordService.php; untracked GuardsRecordIdShape.php, AppTime.php, tests).
- files_generated += AppTime.php, EnforcesPeriodLock.php, ScopesToActorMill.php, ApiExceptionHandler.php ⚠ shared support files, dicantumkan karena perilaku create/update layar ini berubah lewatnya.
- fe_files_generated += GuardsRecordIdShape.php ← use GuardsRecordIdShape di FormDepricarping.php.
- fe_test_files_generated += RecordIdShapeGuardTest.php (FormDepricarping edit ada di dataset 18 stasiun), WebFormNoDisabledFieldTest.php (cek statis semua view form-*).
- implementation_notes += REVISI audit-fix 2026-10-04 (view tidak berubah).
- implementation_notes[2] = idem (pembanding Cages Track kini teks) ← form-cages-track.blade.php diff.
