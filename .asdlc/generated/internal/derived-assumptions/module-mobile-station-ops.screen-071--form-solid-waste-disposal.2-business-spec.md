
## v2 — 2026-10-04

Penyeragaman checkbox verifikasi (keputusan user 2026-10-04).
- available_actions[5] "Isi Acknowledged By": actor_ids ["actor-supervisor"] → ["actor-mill-management"], selaras business_rules (Acknowledged By hanya Mill Management), actor-index, dan kode (v-if="isMillManagement").
- information_displayed[1] (Verifikasi): diperjelas bahwa checkbox Checked By hanya ditampilkan untuk Supervisor dan Acknowledged By hanya untuk Mill Management; peran lain tidak melihatnya (disembunyikan, bukan dinonaktifkan).
- actors: ditambah "actor-mill-management" (sinkronisasi dokumentasi, bukan perubahan akses — route form hanya mensyaratkan login tanpa pembatasan role, dan view merender Acknowledged By untuk mill_management; akses mobile Mill Management sesuai keputusan produk 2026-09-14).
