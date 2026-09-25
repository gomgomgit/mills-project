# Derived Assumptions Log — project.1-foundation.test-strategy

## v1 — 2026-08-14

- unit_test.run_command = "php artisan test --testsuite=Unit" ← user asked for "simple", agent chose the exact command
- integration_test.seed_command = "php artisan migrate:fresh --seed --env=testing" ← user confirmed the suggested example
- integration_test.run_command = "php artisan test --testsuite=Feature" ← user confirmed the suggested example
- component_test.run_command = "vitest run" ← user gave a partial answer ("vitest aja"), agent completed the flag
- browser_test.start_command = "php artisan serve" ← user gave a partial answer ("php artisan aja"), agent completed the subcommand
- browser_test.base_url = "http://localhost:8000" ← user confirmed the suggested example
- browser_test.run_command = "playwright test" ← user confirmed the suggested example

## v2 — 2026-09-25

- **`integration_test.seed_command` dihapus, bukan diperbaiki** ← nilainya `php artisan migrate:fresh --seed --env=testing`, dan diverifikasi: `backend/.env.testing` TIDAK ADA, sehingga `--env=testing` jatuh kembali ke `.env` yang menunjuk PostgreSQL `mill_smart_log` — database dev yang dipakai server :8000. Menjalankan langkah yang terdokumentasi sebagai bagian alur normal akan **menghapus database pengembangan**.
- **Dan perintah itu tidak pernah diperlukan** ← `backend/phpunit.xml` baris 25-26 sudah memaksa `DB_CONNECTION=sqlite` dengan `DB_DATABASE=:memory:`, dan test memakai `RefreshDatabase`. Tiap berkas test menyiapkan skemanya sendiri.
- **Ditemukan dua kali secara independen** ← agen implementasi screen-132 dan screen-137, keduanya menolak menjalankannya dan melaporkannya. Dua penemuan terpisah dari sudut berbeda itulah yang membuat saya memperlakukannya sebagai nyata, bukan salah baca satu agen.
- Nilainya diganti dengan penjelasan, bukan dikosongkan ← medan kosong akan mengundang seseorang mengisinya kembali dengan perintah yang sama. Teks penggantinya menyatakan syarat yang harus dipenuhi lebih dulu bila perintah seed memang kelak diperlukan: buat `.env.testing` dan verifikasi `DB_DATABASE`-nya bukan database dev.
