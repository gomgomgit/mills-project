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

## v7 — 2026-10-05

- initial_actors[0] = "Operator web terbatas: hanya Beranda, Ganti Password, dan melihat data miliknya sendiri; input/edit data stasiun, verifikasi, ekspor, laporan, master data di web tidak diizinkan (403)" ← user hanya menyatakan "operator boleh login web terbatas" (sebelumnya memilih "Beri akses web terbatas" = boleh login web dan melihat data sendiri); rincian halaman yang diizinkan/ditolak diturunkan agen dari RouteAccess yang sudah diimplementasikan. Layar "Data Saya" belum dibangun — Operator saat ini hanya mendarat di /beranda.
- goals[1] = "akses web terbatas (read-only atas data sendiri) bagi Operator" ← diturunkan dari keputusan yang sama, bukan kalimat user
