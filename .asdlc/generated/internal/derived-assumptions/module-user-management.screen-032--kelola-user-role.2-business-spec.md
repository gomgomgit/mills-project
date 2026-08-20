## v1 — 2026-08-20

- `test_priority = high` ← screen memiliki 6 business rules dan berdampak langsung ke akses/permission seluruh sistem, meski hanya 1 actor (actor-admin)
- User tidak dapat dihapus permanen, hanya dinonaktifkan (`is_active`) ← tidak dinyatakan eksplisit; disimpulkan dari kebutuhan menjaga integritas referensi `created_by`/`checked_by`/`acknowledged_by` pada record stasiun yang sudah ada di seluruh entitas lain
- `business_unit_id` wajib diisi untuk role selain Admin, opsional untuk Admin ← turunan langsung dari `user.business_unit_id` yang `required: false` di entity-catalog, dan dari pola aktor lain (Operator/Supervisor/Mill Management selalu terikat 1 mill)
- Admin tidak dapat menonaktifkan akun miliknya sendiri yang sedang login ← safety constraint standar, tidak dinyatakan eksplisit tapi mencegah admin mengunci diri sendiri keluar dari sistem
- Password awal ditentukan langsung oleh Admin saat Tambah User (bukan alur invite/set-password terpisah) ← tidak ada konvensi invite di codebase ini; user mengganti password sendiri lewat layar Ganti Password (screen-003) yang sudah ada setelah login pertama
