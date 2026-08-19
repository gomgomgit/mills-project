# Derived Assumptions Log — module-auth.screen-003--ganti-password-web.3-tech-spec

## v1 — 2026-08-17

- Route `/settings/password` dan endpoint `PATCH /api/me/password` beserta skema request/response ← desain teknis agent, tidak dinyatakan eksplisit di business spec
- Daftar error code: 422 VALIDATION_ERROR, 422 PASSWORD_CONFIRMATION_MISMATCH, 422 OLD_PASSWORD_INCORRECT ← diturunkan dari alternative_flows usecase
- Urutan 6-langkah business_logic dengan percabangan ← translasi teknis dari main_flow usecase
- Implementation note: kebijakan invalidate sesi setelah ganti password belum diputuskan ← open question di business spec, diasumsikan sesi tetap berlaku
