# Derived Assumptions Log — module-auth.screen-002--login-mobile.3-tech-spec

## v1 — 2026-08-17

- Endpoint `POST /api/login` digunakan ulang dari screen-001, dengan body (`device_name`) & response (`token`) berbeda khusus untuk mobile ← desain arsitektur agent (Sanctum token vs session cookie), bukan pernyataan eksplisit di business spec
- Daftar error code: 401 INVALID_CREDENTIALS, 403 ACCOUNT_INACTIVE, 403 BUSINESS_AREA_MISMATCH, 422 VALIDATION_ERROR ← diturunkan dari edge_cases business spec
- Urutan 7-langkah business_logic dengan percabangan ← translasi teknis dari main_flow usecase
- Keputusan bahwa verifikasi token offline sepenuhnya client-side tanpa panggilan API ← disimpulkan dari deskripsi usecase, bukan spesifikasi teknis eksplisit
- Keputusan bahwa deteksi "tidak ada koneksi saat login pertama" dilakukan client-side sebelum submit ← disimpulkan, tidak eksplisit di business spec
