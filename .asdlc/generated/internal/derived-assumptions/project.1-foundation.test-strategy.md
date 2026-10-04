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

## v3 — 2026-10-04

Sumber: audit-fix 2026-10-04, code is truth (e2e-web/README.md, playwright.config.ts, package.json, scripts/prepare-db.sh, backend/.env.e2e.example, tests/support/global-setup.ts).
- browser_test.environment = server e2e terpisah, DB `mill_smart_log_e2e` via backend/.env.e2e; db:prepare menolak DB bukan *_e2e; global-setup re-seed BrowserTestFixtureSeeder; e2e:prune-records menghapus lajur 1970–2019; EVENT_DATE_MAX_DAYS_AHEAD=1 tetap aktif ← dibaca dari kode/README, bukan dinyatakan user
- browser_test.start_command = `cd e2e-web && npm run db:prepare && npm run serve` (php artisan serve --env=e2e --port=8001) ← dari package.json scripts
- browser_test.base_url = http://localhost:8001 ← default APP_ORIGIN di tests/support/base-url.ts / playwright.config.ts
- browser_test.run_command = `cd e2e-web && npm test` ← package.json; baris suite mobile (Vite :5174 vs backend :8000) ditambahkan dari memori proyek, karena berkas ini tak punya field terpisah untuk suite mobile
