# Derived Assumptions Log — module-auth.screen-003--ganti-password-web.2-business-spec

## v1 — 2026-08-14

- entry_points = ["Menu Pengaturan Akun di sidebar", "Klik profil/avatar user di header, pilih Ganti Password"] ← proposed by agent in draft, accepted without correction
- business_rules = ["Password baru minimal 6 karakter, case-sensitive, alfanumerik+simbol", "Password lama harus benar sebelum perubahan diterima", "Konfirmasi password baru harus cocok dengan password baru"] ← proposed by agent in draft, accepted without correction
- edge_cases = ["Password lama salah", "Password baru tidak memenuhi format", "Konfirmasi password tidak cocok"] ← proposed by agent in draft, accepted without correction

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (routes/web.php, layouts/app.blade.php, AuthService.php, PasswordPolicy.php).
- description = termasuk Operator (akses web terbatas) ← settings.password role:...,operator
- actors += actor-station-operator; available_actions[0].actor_ids += operator ← routes/web.php
- entry_points[0] = menu 'Ganti Password' di sidebar per RouteAccess ← layouts/app.blade.php
- business_rules[0] = aturan password tunggal (sama dengan Login & Kelola User) ← PasswordPolicy
