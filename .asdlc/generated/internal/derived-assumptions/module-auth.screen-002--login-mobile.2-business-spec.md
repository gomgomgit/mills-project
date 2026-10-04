# Derived Assumptions Log — module-auth.screen-002--login-mobile.2-business-spec

## v1 — 2026-08-14

- entry_points = ["Membuka aplikasi mobile untuk pertama kali", "Redirect setelah logout", "Redirect setelah sesi/token lokal kadaluarsa"] ← proposed by agent in draft, accepted without correction
- business_rules = ["Password minimal 6 karakter, case-sensitive, alfanumerik+simbol", "Login pertama kali memerlukan koneksi internet aktif; sesi berikutnya dapat berjalan offline", "User harus memilih Business Area/Company sesuai penugasannya"] ← proposed by agent in draft, accepted without correction
- edge_cases = ["Tidak ada koneksi saat login pertama kali", "Kredensial salah", "Akun dinonaktifkan", "Token sesi lokal kadaluarsa"] ← proposed by agent in draft, accepted without correction

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (AuthService.php, mobile/src/stores/auth.ts, AppServiceProvider.php, UserService.php).
- information_displayed[2] = tanpa pemilih Business Area ← mobile LoginForm.vue (drift pra-audit, ikut dikoreksi)
- business_rules[2] = Business Area diturunkan dari akun ← AuthService::login step 5 (drift pra-audit)
- business_rules += mill-setting & master Grading diambil best-effort saat login ← auth.ts login() fetchAndCacheGradingParameters
- business_rules += token akun nonaktif dicabut & ditolak 401 ← UserService::setStatus, Sanctum::authenticateAccessTokensUsing
- edge_cases += akun dinonaktifkan saat masih login di mobile → 401

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (mobile/src/services/apiClient.ts, mobile/src/stores/auth.ts, mobile/src/components/LoginForm.vue).
- business_rules[4] ← diperluas: 401 ditangani terpusat, sesi dibersihkan, data lokal tetap, pesan 'Sesi berakhir atau akun dinonaktifkan. Silakan login kembali.'.
- edge_cases[4] ← diperbarui: kembali ke Login dengan pesan; pengecualian /api/login, offline, request basi.
