# Derived Assumptions Log — project.2-business-spec.actor-index

## v1 — 2026-08-14

- actors.permissions = permission summaries synthesized for all 4 actors from PRD initial_actors descriptions ← user confirmed actor list only in general terms ("sesuai"), specific permission summary text is agent's synthesis

## v3 — 2026-09-14

- actor-mill-management.permissions = input data stasiun diperluas dari "(web)" jadi "(web & mobile)" ← user menyatakan eksplisit "gw mau mill management bisa login juga ke mobile"; yang diturunkan agen hanyalah penemuan bahwa hal ini SUDAH berfungsi di kode (AuthService tidak pernah membatasi login mobile per role, dan repo mobile justru menyimpan acknowledged_by khusus untuk role ini), sehingga perubahan ini mendokumentasikan + mengunci perilaku yang ada, bukan menambah kapabilitas baru
- actor-admin.description = ditambahkan catatan bahwa Admin secara teknis juga bisa login mobile ← user tidak menanyakan Admin; agen menambahkannya karena penyebabnya sama persis (tidak ada gating role di AuthService) dan membiarkannya tidak terdokumentasi akan mengulang kebingungan yang sama di kemudian hari. Ditulis sebagai "tidak diblokir", BUKAN sebagai alur kerja yang didukung
- actor-admin.permissions = ditambahkan "isi Checked By & Acknowledged By" ← menyusulkan keputusan 2026-09-14 (Admin boleh keduanya) yang saat itu diimplementasikan di kode dan uiux-spec tapi belum tercermin di actor-index
