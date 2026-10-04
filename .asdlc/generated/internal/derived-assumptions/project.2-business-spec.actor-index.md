# Derived Assumptions Log — project.2-business-spec.actor-index

## v1 — 2026-08-14

- actors.permissions = permission summaries synthesized for all 4 actors from PRD initial_actors descriptions ← user confirmed actor list only in general terms ("sesuai"), specific permission summary text is agent's synthesis

## v3 — 2026-09-14

- actor-mill-management.permissions = input data stasiun diperluas dari "(web)" jadi "(web & mobile)" ← user menyatakan eksplisit "gw mau mill management bisa login juga ke mobile"; yang diturunkan agen hanyalah penemuan bahwa hal ini SUDAH berfungsi di kode (AuthService tidak pernah membatasi login mobile per role, dan repo mobile justru menyimpan acknowledged_by khusus untuk role ini), sehingga perubahan ini mendokumentasikan + mengunci perilaku yang ada, bukan menambah kapabilitas baru
- actor-admin.description = ditambahkan catatan bahwa Admin secara teknis juga bisa login mobile ← user tidak menanyakan Admin; agen menambahkannya karena penyebabnya sama persis (tidak ada gating role di AuthService) dan membiarkannya tidak terdokumentasi akan mengulang kebingungan yang sama di kemudian hari. Ditulis sebagai "tidak diblokir", BUKAN sebagai alur kerja yang didukung
- actor-admin.permissions = ditambahkan "isi Checked By & Acknowledged By" ← menyusulkan keputusan 2026-09-14 (Admin boleh keduanya) yang saat itu diimplementasikan di kode dan uiux-spec tapi belum tercermin di actor-index

## v4 — 2026-09-22

- actors.actor-mill-management.permissions += "tidak dapat menutup/membuka Periode Pelaporan" ← user stated "Admin saja" for closing/reopening; the explicit denial on Mill Management is the agent spelling that out on the actor most likely to expect the right
- actors.*.description = laporan periode disebutkan di deskripsi keempat actor ← agent reworded descriptions to match the new permission; user only stated the access rule, not the wording

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (routes/web.php '/beranda' + settings/password role operator, AuthService::ROLE_REDIRECTS, RouteAccess, errors/403.blade.php, routes/api.php GET /records/{stationType}/verification).
- actors[actor-station-operator].description = mobile offline-first + sync manual + laporan mobile; sejak 2026-10-04 BOLEH login web terbatas (Beranda Operator /beranda + Ganti Password; menu lain tersembunyi via RouteAccess, rute lain 403); layar 'lihat data sendiri' belum dispesifikasikan ← komentar routes/web.php "keputusan produk 2026-10-04"; daftar stasiun lama "(Weighbridge, Grading, Cages & Track)" dibuang karena sudah 18 stasiun (inferensi agen)
- actors[actor-station-operator].permissions = + tidak dapat mengisi 'Checked by SPV' Sterilizer; baca status verifikasi record sendiri via GET /api/records/{stationType}/verification; web: login, Beranda, Ganti Password saja ← routes + FormSterilizerView (Supervisor only)
