# Derived Assumptions Log — project.4-implement.scaffold

## v1 — 2026-08-17

- scaffold.dual_project_structure: monorepo dengan `backend/` (Laravel API+Livewire) dan `mobile/` (Vue 3 + Capacitor) sebagai dua codebase terpisah — arch-spec menyebut dua stack (backend Laravel, mobile Vue3+Capacitor) tanpa menentukan tata letak repo secara eksplisit
- scaffold.setup_script_format: Makefile dipilih sebagai task runner idiomatik (bukan komponen tunggal dari Laravel/Vue, keputusan agent untuk konsistensi lintas backend+mobile)
- scaffold.laravel_version: Laravel 11.x (application skeleton style `Application::configure()`) dipilih sebagai versi konkret — arch-spec hanya menyebut "Laravel (PHP)" tanpa versi
- scaffold.test_libraries: Pest dipilih sebagai test runner utama backend (di atas PHPUnit) — arch-spec test_stack menyebut "PHPUnit / Pest", agent memilih Pest sebagai default

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (config/app.php, .gitignore, routes/web.php).
- setup_notes += REVISI 2026-10-04: timezone env APP_TIMEZONE default Asia/Jakarta + event_date_max_days_ahead; .gitignore storage/app/private|public + izinkan .env.e2e.example; rute infrastruktur '/' dan '/beranda' ← diff berkas scaffold
