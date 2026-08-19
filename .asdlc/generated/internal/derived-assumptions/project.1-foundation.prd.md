# Derived Assumptions Log — project.1-foundation.prd

## v1 — 2026-08-14

- success_metrics = "Operator/Supervisor bisa login dan menyimpan data Weighbridge/Grading/Cages Track secara offline tanpa kehilangan data saat app ditutup/koneksi putus/device restart" ← no explicit statement from user
- success_metrics = "Data tersimpan lokal tampil di web dashboard & data preview setelah sync manual berhasil" ← no explicit statement from user
- success_metrics = "Station list & saved records termuat ≤ 2 detik untuk dataset normal" ← no explicit statement from user
- success_metrics = "Supervisor/Mill Management bisa memfilter (tanggal, business unit/mill, stasiun) dan mengekspor data ke CSV/Excel" ← no explicit statement from user
- success_metrics = "Tampilan mobile sesuai arahan visual Figma untuk layar MVP yang diimplementasikan" ← no explicit statement from user
- success_metrics = "Field Checked By/Acknowledged By tidak bisa diisi oleh role yang tidak berwenang (tervalidasi di level aplikasi)" ← no explicit statement from user
- constraints = "Database MySQL, mengikuti struktur ERD yang sudah didesain" ← agent recommendation, not explicitly requested by user
- constraints = "File/gambar (machinery picture, logo) disimpan sebagai object storage/Laravel filesystem disk, bukan binary di database" ← agent recommendation, not explicitly requested by user
- assumptions = "Pengguna web (Admin/Supervisor/Mill Management) memiliki koneksi internet stabil saat mengakses web app, tidak perlu offline-first di web" (status: tbd) ← no explicit statement from user
