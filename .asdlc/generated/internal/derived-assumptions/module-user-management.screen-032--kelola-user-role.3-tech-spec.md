## v1 — 2026-08-20

- `route = /users` ← diambil langsung dari `uiux-spec.layout.navigation_per_role` yang sudah menetapkan target `/users` untuk menu item "User & Role Management"
- `PATCH /api/users/:id/status` dipisah dari `PATCH /api/users/:id` ← agar toggle aktif/nonaktif dari daftar tidak perlu membuka form penuh dan tidak bisa tidak sengaja mengubah field lain
- Tidak ada endpoint DELETE ← turunan langsung dari business rule "user tidak dihapus permanen, hanya dinonaktifkan"
- Dropdown Business Unit di FE memakai endpoint publik `GET /api/business-units` yang sudah ada dari screen-029, tidak membuat endpoint baru ← menghindari duplikasi, endpoint tersebut sudah terbukti aman untuk kebutuhan dropdown
- Validasi password minimal 6 karakter ← diturunkan dari deskripsi field `password_hash` di entity-catalog ("minimal 6 karakter, case-sensitive, alfanumerik+simbol sebelum di-hash")

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (UserService.php, UserController.php, KelolaUserRole.php).
- endpoints[1] body_schema.username = tanpa spasi + unik case-insensitive; password = PasswordPolicy; error_codes[0].condition diperbarui
- endpoints[2].description = API tidak meneruskan password; Reset Password hanya via Livewire ← UserController::update only(name,role,business_unit_id)
- endpoints[3].description = penonaktifan mencabut token Sanctum ← UserService::setStatus
- business_logic[1..3] = create/update/status sesuai kode (usernameUniqueRule, PasswordPolicy, password_hash hanya bila diisi, tokens()->delete())
- edge_case_handling[0],[3] diperbarui; += username berspasi
- business_rules_applied[0] = unik case-insensitive, tanpa spasi
- unit_test_cases[6] = PasswordPolicy; [9] = password_hash hanya berubah bila Reset Password diisi; += username beda huruf/berspasi; += cabut token ← KelolaUserRoleAuditTest, WebAccessTest
- implementation_notes += validasi hanya di UserService; label UserRole::label()

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (UserService.php, Api/UserController.php, EnsureUserIsActive.php, AppServiceProvider.php, migrasi 2026_10_05_000001).
- PATCH /api/users/:id description + body_schema.password + error_codes[0] ← API kini menerima password (sebelumnya hanya Livewire).
- business_logic[2] + (+5 pencabutan sesi), data_operations, edge_case_handling (+3), business_rules_applied (+1), unit_test_cases (+5), test_scenarios (+2), implementation_notes (+1) ← sessions_revoked_at, tokens()->delete(), stempel sesi auth_session_started_at_ms.
