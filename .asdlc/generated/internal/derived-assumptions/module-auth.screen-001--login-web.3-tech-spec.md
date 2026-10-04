# Derived Assumptions Log — module-auth.screen-001--login-web.3-tech-spec

## v1 — 2026-08-17

- Skema request/response `POST /api/login` (body_schema, success_schema) ← diturunkan agent, tidak dinyatakan eksplisit di business spec/usecase
- Daftar error code: 401 INVALID_CREDENTIALS, 403 ACCOUNT_INACTIVE, 403 BUSINESS_AREA_MISMATCH, 422 VALIDATION_ERROR ← diturunkan agent dari edge_cases business spec
- Urutan 7-langkah business_logic dengan percabangan ← translasi teknis dari main_flow usecase
- Implementation note: rate limiting/lockout belum diputuskan ← open question di business spec, dicatat sebagai catatan implementasi bukan keputusan final
- Implementation note: sesi login ganda tidak dibatasi di MVP ← open question di business spec, dicatat sebagai catatan implementasi bukan keputusan final

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (AuthService.php, AuthController.php, LoginForm.php, routes/web.php, EnsureUserIsActive.php, AppServiceProvider.php, bootstrap/app.php, PasswordPolicy.php, RouteAccess.php).
- actor_permissions += actor-station-operator can_access=true (terbatas: /beranda + /settings/password) ← routes/web.php role:operator
- endpoints[0].request.body_schema.business_unit_id = opsional, harus cocok bila dikirim ← AuthController filled('business_unit_id') (drift pra-audit)
- business_logic[0] = business_unit_id opsional; [1] = PasswordPolicy; [4] = resolusi BU dari akun; [6] = redirect_to operator /beranda ← AuthService
- business_logic += 8 LoginForm::mount redirect + flash; 9 rute '/'; 10 EnsureUserIsActive + Sanctum inactive ← kode terkait
- edge_case_handling += sesi akun nonaktif; /login saat login; Operator → 403 kustom
- business_rules_applied[1],[2] = BU diturunkan dari akun; redirect Operator /beranda
- unit_test_cases += operator → /beranda; sesi akun nonaktif dikeluarkan ← WebAccessTest
- implementation_notes += /beranda tanpa screen sendiri; sidebar RouteAccess; errors/403.blade.php
- test_scenarios += Operator mendarat di Beranda; sesi akun dinonaktifkan diakhiri ← audit-web-admin.spec.ts #2/#4
