# Derived Assumptions Log — module-auth.screen-002--login-mobile.3-tech-spec

## v1 — 2026-08-17

- Endpoint `POST /api/login` digunakan ulang dari screen-001, dengan body (`device_name`) & response (`token`) berbeda khusus untuk mobile ← desain arsitektur agent (Sanctum token vs session cookie), bukan pernyataan eksplisit di business spec
- Daftar error code: 401 INVALID_CREDENTIALS, 403 ACCOUNT_INACTIVE, 403 BUSINESS_AREA_MISMATCH, 422 VALIDATION_ERROR ← diturunkan dari edge_cases business spec
- Urutan 7-langkah business_logic dengan percabangan ← translasi teknis dari main_flow usecase
- Keputusan bahwa verifikasi token offline sepenuhnya client-side tanpa panggilan API ← disimpulkan dari deskripsi usecase, bukan spesifikasi teknis eksplisit
- Keputusan bahwa deteksi "tidak ada koneksi saat login pertama" dilakukan client-side sebelum submit ← disimpulkan, tidak eksplisit di business spec

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (AuthController.php, AuthService.php, mobile/src/stores/auth.ts, gradingParameterSync.ts, AppServiceProvider.php, UserService.php).
- endpoints[0].request.body_schema.business_unit_id = opsional, tidak dikirim mobile ← AuthController (drift pra-audit)
- business_logic[4] = resolusi BU dari akun ← AuthService::login
- business_logic += 8 fetch mill-setting + grading parameters best-effort; 9 token akun nonaktif ditolak/dicabut
- edge_case_handling += akun dinonaktifkan saat login mobile → token dicabut, 401
- implementation_notes += alasan master Grading diambil saat login (id parameter buatan lokal ditolak server)

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (mobile/src/services/apiClient.ts, mobile/src/stores/auth.ts, mobile/src/main.ts, mobile/src/services/errorHandler.ts, mobile/src/components/LoginForm.vue).
- implementation_notes ← append catatan penanganan 401 terpusat (expireSession, setUnauthorizedHandler, pengecualian).
- test_scenarios ← append 'Login Mobile — Sesi Ditolak Server'.
- ⚠ test_scenarios[api_test].expected_error_code 'UNAUTHENTICATED' diambil dari ApiExceptionHandler (401 → UNAUTHENTICATED); e2e hanya mengasersi status 401.

## v4 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit ee5294c, d5da9cf), code is truth (AuthController.php, routes/api.php, stores/auth.ts, App.vue, LoginForm.vue, LogoutMobileTest.php, auth.store.spec.ts, e2e/logout-revokes-token.spec.ts).
- api_contracts ← kontrak baru POST /api/logout (usecase-002, auth:sanctum, 200 {message:'Logout berhasil.'}, 401) dengan business_logic server+klien, edge cases, 7 unit_test_cases dari LogoutMobileTest.php + auth.store.spec.ts.
- implementation_notes ← catatan route baru, loggingOut/pendingLogout, LoadingOverlay Keluar…, penjaga submit ganda LoginForm.
- test_scenarios ← 2 skenario (Logout mencabut token perangkat; Logout saat offline).
- ⚠ error_code 401 ditulis UNAUTHENTICATED mengikuti skenario 401 yang sudah ada di artefak ini; respons nyata Laravel hanya {message:'Unauthenticated.'}.
- ⚠ data_operations memakai entity_id 'user' karena personal_access_tokens tidak punya entri di entity-catalog.
- ⚠ Entri api-index untuk POST /api/logout ditambahkan oleh pemanggil (bukan agen ini).
