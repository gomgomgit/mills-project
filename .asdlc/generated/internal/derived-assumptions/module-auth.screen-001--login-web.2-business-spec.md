# Derived Assumptions Log — module-auth.screen-001--login-web.2-business-spec

## v1 — 2026-08-14

- entry_points = ["Mengakses URL aplikasi web saat belum login", "Redirect otomatis setelah sesi login berakhir/expired", "Redirect setelah logout"] ← proposed by agent in draft, accepted without correction
- business_rules = ["Password minimal 6 karakter, case-sensitive, kombinasi alfanumerik+simbol", "User harus memilih Business Area/Company yang sesuai dengan aksesnya", "Redirect setelah login mengikuti role masing-masing"] ← proposed by agent in draft, accepted without correction
- edge_cases = ["Kredensial salah", "Akun tidak aktif/dinonaktifkan Admin", "Business Area tidak sesuai dengan akses user", "Sesi login ganda"] ← proposed by agent in draft, accepted without correction

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (AuthService.php, LoginForm.php, routes/web.php, EnsureUserIsActive.php, operator/home.blade.php, errors/403.blade.php, RouteAccess.php).
- description = login tanpa pemilih Business Area; Operator → /beranda; '/' & '/login' redirect ← ROLE_REDIRECTS operator='/beranda', LoginForm::mount(), rute '/'
- actors += actor-station-operator ← web login Operator diizinkan (keputusan produk 2026-10-04, WebAccessTest #2)
- entry_points[0] = '/' dialihkan ke /login; += dikeluarkan karena akun dinonaktifkan ← rute '/' + EnsureUserIsActive flash auth_error
- information_displayed[2] = pesan akun dinonaktifkan (tanpa pemilih Business Area) ← login-form.blade.php tak punya pemilih BU (drift sejak 2026-08-20, ikut dikoreksi)
- available_actions[0] actor_ids += operator, description redirect per peran ← AuthService::redirectFor()
- business_rules[1] = Business Area diturunkan dari akun ← AuthService::login step 5 (drift pra-audit, ikut dikoreksi)
- business_rules[2] = redirect Admin/SPV/MM → /dashboard, Operator → /beranda ← ROLE_REDIRECTS
- business_rules += akses web Operator terbatas + sidebar RouteAccess + 403 Indonesia; redirect user login di '/' & '/login'; sesi akun nonaktif diakhiri ← routes/web.php, layouts/app.blade.php, errors/403.blade.php, EnsureUserIsActive
- edge_cases += akun dinonaktifkan saat sesi berjalan; buka /login saat login; Operator buka rute terlarang → 403 ← WebAccessTest #1/#4/#14
