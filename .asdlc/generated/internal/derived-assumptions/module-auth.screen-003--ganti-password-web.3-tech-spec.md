# Derived Assumptions Log — module-auth.screen-003--ganti-password-web.3-tech-spec

## v1 — 2026-08-17

- Route `/settings/password` dan endpoint `PATCH /api/me/password` beserta skema request/response ← desain teknis agent, tidak dinyatakan eksplisit di business spec
- Daftar error code: 422 VALIDATION_ERROR, 422 PASSWORD_CONFIRMATION_MISMATCH, 422 OLD_PASSWORD_INCORRECT ← diturunkan dari alternative_flows usecase
- Urutan 6-langkah business_logic dengan percabangan ← translasi teknis dari main_flow usecase
- Implementation note: kebijakan invalidate sesi setelah ganti password belum diputuskan ← open question di business spec, diasumsikan sesi tetap berlaku

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (routes/web.php, AuthService.php, PasswordPolicy.php).
- actor_permissions += actor-station-operator can_access=true ← web route role:admin,supervisor,mill_management,operator
- business_logic[1] = validasi via PasswordPolicy ← AuthService::validatePasswordFormat
