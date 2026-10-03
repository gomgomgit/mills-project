# Derived Assumptions Log — module-auth.screen-004--ganti-password-mobile.3-tech-spec

## v1 — 2026-08-17

- Reuse endpoint `PATCH /api/me/password` dari screen-003 (logika backend identik, hanya beda mekanisme auth: Sanctum token vs session) ← desain arsitektur agent
- Daftar error code: 422 VALIDATION_ERROR, 422 PASSWORD_CONFIRMATION_MISMATCH, 422 OLD_PASSWORD_INCORRECT ← diturunkan dari alternative_flows usecase
- Urutan 6-langkah business_logic dengan percabangan ← translasi teknis dari main_flow usecase
- Keputusan bahwa deteksi "tidak ada koneksi internet" dilakukan client-side sebelum submit ← disimpulkan, tidak eksplisit di business spec

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar
