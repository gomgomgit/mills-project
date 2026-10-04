## v1 — 2026-08-20

- `test_priority = high` ← screen memiliki 6 business rules dan berdampak langsung ke akses/permission seluruh sistem, meski hanya 1 actor (actor-admin)
- User tidak dapat dihapus permanen, hanya dinonaktifkan (`is_active`) ← tidak dinyatakan eksplisit; disimpulkan dari kebutuhan menjaga integritas referensi `created_by`/`checked_by`/`acknowledged_by` pada record stasiun yang sudah ada di seluruh entitas lain
- `business_unit_id` wajib diisi untuk role selain Admin, opsional untuk Admin ← turunan langsung dari `user.business_unit_id` yang `required: false` di entity-catalog, dan dari pola aktor lain (Operator/Supervisor/Mill Management selalu terikat 1 mill)
- Admin tidak dapat menonaktifkan akun miliknya sendiri yang sedang login ← safety constraint standar, tidak dinyatakan eksplisit tapi mencegah admin mengunci diri sendiri keluar dari sistem
- Password awal ditentukan langsung oleh Admin saat Tambah User (bukan alur invite/set-password terpisah) ← tidak ada konvensi invite di codebase ini; user mengganti password sendiri lewat layar Ganti Password (screen-003) yang sudah ada setelah login pertama

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (UserService.php, KelolaUserRole.php, kelola-user-role.blade.php, UserRole.php, PasswordPolicy.php).
- information_displayed[3] = username teks di Edit + kolom opsional Reset Password ← blade kc-form-field__static, field password di mode edit
- information_displayed += label role rapi (UserRole::label); pesan sukses ← $successMessage
- available_actions[2].description = Edit dengan Reset Password opsional, username tak bisa diubah ← UserService::update
- available_actions[3].description = Nonaktifkan dengan konfirmasi; sesi/token diakhiri ← wire:confirm, setStatus tokens()->delete(), EnsureUserIsActive
- business_rules[0] = unik case-insensitive + tanpa spasi ← usernameUniqueRule, regex ^\S+$
- business_rules[4] = password awal ikut aturan login ← PasswordPolicy::rule()
- business_rules += reset password oleh Admin; penonaktifan mengakhiri sesi
- edge_cases[0] = duplikat termasuk beda huruf; [4] = aturan password login; += username berspasi

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (UserService.php, EnsureUserIsActive.php, Api/UserController.php).
- available_actions[2], business_rules[7] (+API), business_rules (+1), edge_cases (+2) ← reset password mencabut token & sesi web user (kecuali sesi saat ini saat reset diri sendiri), pesan REVOKED_MESSAGE.
