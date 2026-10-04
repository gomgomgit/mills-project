# Derived Assumptions Log — module-auth.screen-002--login-mobile.4-implement

## v1 — 2026-08-17

- Web-session dan mobile-token path digabung dalam satu AuthService::login() via parameter opsional $deviceName ← keputusan desain agent untuk menghindari duplikasi, sesuai seam yang ditinggalkan di screen-001
- CSRF dikecualikan khusus untuk path api/login ← diperlukan karena request pertama mobile tidak punya CSRF token; dikonfirmasi tidak memengaruhi alur Livewire web screen-001
- Migration personal_access_tokens (Sanctum) ditambahkan ← infra yang diperlukan tapi tidak eksplisit di entity-catalog/tech-spec, dependency dari fitur createToken()
- Device name detection pakai navigator.userAgent + random suffix cache (bukan @capacitor/device) ← placeholder karena package belum terpasang
- Offline session-expiry pakai heuristik grace period 7 hari ← tech-spec eksplisit menyebut durasi validitas token offline sebagai open question, agent memilih default konkret
- **Gap ditemukan (major)**: endpoint GET /api/business-units tidak ada di api-index/tech-spec manapun — dropdown Business Area di LoginForm mobile akan kosong di runtime nyata sampai endpoint ini ditambahkan. Perlu ditambahkan sebagai endpoint shared (dipakai screen-001 web login juga) di iterasi berikutnya.

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v2)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v3 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/LoginMobileTest.php` dihapus dari `test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).
- known_issue dihapus (semata soal berkas tests/Browser yang tidak dijalankan): "tests/Browser/LoginMobileTest.php (Playwright lawas) dibuat tapi tidak pernah dijalankan u..."
- Tidak ada spec e2e-web yang jelas cocok (layar mobile) — tidak ditambahkan.

## v4 — 2026-10-03

Run penuh Playwright mobile 2026-10-03: 430 lulus, 0 gagal.
- test_results.browser = 5/0 (sebelumnya kosong).
- mobile/tests/e2e/login.spec.ts ditambahkan ke fe_test_files_generated.
- Spec diperbaiki hari ini (drift spec, bukan cacat aplikasi; hanya mobile/tests/e2e yang berubah): kasus offline menunggu #username sebelum setOffline.

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git diff HEAD mobile/src/stores/auth.ts, AppServiceProvider.php; berkas baru gradingParameterSync.ts, GradingParameterController/Service).
- files_generated += AppServiceProvider.php, GradingParameterController.php, GradingParameterService.php
- test_files_generated += backend/tests/Feature/WebAccessTest.php (#2 token Operator, #4 cabut token)
- fe_files_generated += mobile/src/services/gradingParameterSync.ts
- implementation_notes += REVISI 2026-10-04 (grading params saat login, token akun nonaktif)
- known_issues += mobile tanpa penanganan 401 global setelah token dicabut ⚠ DISIMPULKAN dari grep apiClient.ts/auth.ts (tidak ada redirect 401 global), belum diverifikasi di browser
