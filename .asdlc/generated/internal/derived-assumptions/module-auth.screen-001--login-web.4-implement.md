# Derived Assumptions Log — module-auth.screen-001--login-web.4-implement

## v1 — 2026-08-17

- redirect_to per role (ROLE_REDIRECTS map) ← tidak didokumentasikan eksplisit di tech spec, agent memilih path konkret per role
- Business-area access dicek via exact match User.business_unit_id ← tidak ada tabel M2M akses di entity-catalog, agent memilih interpretasi paling sederhana
- 3 exception class baru (InvalidCredentialsException, AccountInactiveException, BusinessAreaMismatchException) ← ErrorCodes shared-module hanya punya kategori generik, agent menambah exception spesifik
- ApiExceptionHandler tidak mengeluarkan field error_code machine-readable ← gap antara kontrak tech-spec (INVALID_CREDENTIALS dll) dan implementasi shared error-handler; test hanya menguji status+teks pesan, bukan error_code — perlu diperbaiki di iterasi shared-modules berikutnya
- Design token di-inline sebagai CSS biasa (bukan lewat pipeline Vite/Tailwind) ← belum ada asset pipeline di scaffold backend-only
- POST /api/login diberi middleware('web') eksplisit (bukan hanya EnsureFrontendRequestsAreStateful) agar session selalu dibuat sesuai business_logic step 6 ← keputusan fix implementasi, konsekuensi arsitektur untuk usecase-002 (mobile) perlu ditinjau ulang nanti
- AuthServiceTest pakai RefreshDatabase+sqlite in-memory+factories, bukan true mock ← deviasi pragmatis dari test_strategy.unit_test.mock_policy karena AuthService pakai Eloquent langsung

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v2 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser = 4 lulus/0 gagal dari e2e-full.log.counts.json (spec login-web).
- Known issue 'Browser test (tests/Browser/LoginWebTest.php) dibuat tapi TIDAK dijalankan' dihapus — cakupan browser nyata adalah e2e-web (backend/tests/Browser tidak ada di repo); entri path lama dibiarkan.
- Path e2e-web/tests/login-web.spec.ts ditambahkan ke test_files_generated (tempat file browser lama tercatat).

## v3 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/LoginWebTest.php` dihapus dari `test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).

## v4 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git diff HEAD + berkas baru).
- files_generated += EnsureUserIsActive.php, AppServiceProvider.php, bootstrap/app.php, Support/PasswordPolicy.php, Support/RouteAccess.php, Enums/UserRole.php ← dipakai alur login/redirect/sesi
- test_files_generated += backend/tests/Feature/WebAccessTest.php
- fe_files_generated += operator/home.blade.php, errors/403.blade.php, components/layouts/app.blade.php
- fe_test_files_generated += e2e-web/tests/audit-web-admin.spec.ts
- implementation_notes += REVISI 2026-10-04 (redirect Operator /beranda, '/' route, LoginForm::mount, EnsureUserIsActive, Sanctum inactive, PasswordPolicy, RouteAccess, 403)
- test_results tidak diubah (tidak ada hitungan baru)
