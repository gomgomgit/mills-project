# Derived Assumptions Log — module-web-station-data.screen-058--form-pressing-web.4-implement

## v3 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Isi catatan REVISI (urutan pemanggilan guard, file test yang dikutip) diturunkan dari pembacaan kode service/Livewire/test saat ini, bukan dari laporan run test; test_results sengaja tidak disentuh dan suite tidak dijalankan.

## v4 — 2026-10-03

Sinkronisasi catatan uji 4-implement dengan uji yang ditambahkan 2026-10-03.
- test_results.integration.passed = 19, dihitung dari jumlah `it(` di backend/tests/Feature/Api/FormPressingTest.php (failed 0, run_at 2026-10-03T00:00:00Z); unit/component/browser tidak diubah karena tidak dijalankan ulang untuk sinkronisasi ini.
- Tidak ada known_issue yang secara eksplisit menyatakan penolakan kunci periode per-stasiun belum diuji, jadi known_issues dibiarkan; catatan REVISI kunci periode sebelumnya ("test per-layar membuka periode Terbuka sebagai prasyarat") dilengkapi oleh catatan REVISI baru, bukan diubah.
- backend/tests/Feature/Api/FormPressingTest.php sudah ada di test_files_generated, tidak ditambahkan ulang.

## v5 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser diisi 7 lulus/0 gagal dari e2e-full.log.counts.json (spec form-pressing); run_at 2026-10-03T00:00:00Z.
- known_issue tunggal tentang spec Playwright backend/tests/Browser/* yang tidak dapat dijalankan dihapus (berkas itu sudah tidak ada; cakupan browser kini e2e-web) — known_issues jadi kosong.
- e2e-web/tests/form-pressing.spec.ts ditambahkan ke test_files_generated (sebelumnya tidak tercantum di daftar mana pun).
