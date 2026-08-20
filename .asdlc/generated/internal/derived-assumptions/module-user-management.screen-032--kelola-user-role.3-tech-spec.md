## v1 — 2026-08-20

- `route = /users` ← diambil langsung dari `uiux-spec.layout.navigation_per_role` yang sudah menetapkan target `/users` untuk menu item "User & Role Management"
- `PATCH /api/users/:id/status` dipisah dari `PATCH /api/users/:id` ← agar toggle aktif/nonaktif dari daftar tidak perlu membuka form penuh dan tidak bisa tidak sengaja mengubah field lain
- Tidak ada endpoint DELETE ← turunan langsung dari business rule "user tidak dihapus permanen, hanya dinonaktifkan"
- Dropdown Business Unit di FE memakai endpoint publik `GET /api/business-units` yang sudah ada dari screen-029, tidak membuat endpoint baru ← menghindari duplikasi, endpoint tersebut sudah terbukti aman untuk kebutuhan dropdown
- Validasi password minimal 6 karakter ← diturunkan dari deskripsi field `password_hash` di entity-catalog ("minimal 6 karakter, case-sensitive, alfanumerik+simbol sebelum di-hash")
