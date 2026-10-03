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
