
## v2 — 2026-10-04

Penyeragaman checkbox verifikasi (keputusan user 2026-10-04).
- available_actions[6] "Isi Acknowledged By": actor_ids ["actor-supervisor"] → ["actor-mill-management"], selaras business_rules (Acknowledged By hanya Mill Management), actor-index, dan kode (v-if="isMillManagement").
- information_displayed[1] (Verifikasi): diperjelas bahwa checkbox Checked By hanya ditampilkan untuk Supervisor dan Acknowledged By hanya untuk Mill Management; peran lain tidak melihatnya (disembunyikan, bukan dinonaktifkan).
- actors: ditambah "actor-mill-management" (sinkronisasi dokumentasi, bukan perubahan akses — route form hanya mensyaratkan login tanpa pembatasan role, dan view merender Acknowledged By untuk mill_management; akses mobile Mill Management sesuai keputusan produk 2026-09-14).

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/FormSterilizerView.vue, mobile/src/services/sterilizerRecordRepo.ts, backend/app/Services/SterilizerRecordService.php, mobile/tests/FormSterilizerView.spec.ts, mobile/tests/e2e/form-sterilizer.spec.ts).
- information_displayed[0] = Date otomatis dengan tanggal LOKAL perangkat ← createDraft() memakai todayLocalDateString() (src/utils/localDate.ts)
- information_displayed[1] = label catatan "Catatan" (bukan "Note") ← FormField id="field-note" label="Catatan"
- information_displayed[2] = Checked by SPV per baris: checkbox hanya Supervisor; peran lain teks 'Ya'/'—' ← template v-if="isSupervisor" / span.spv-check-text
- available_actions[4].actor_ids = [actor-supervisor] (sebelumnya operator+supervisor) ← checkbox per baris hanya dirender untuk isSupervisor
- business_rules[0] = Date memakai tanggal LOKAL perangkat ← createDraft()
- business_rules (+1) = Checked by SPV hanya Supervisor; server mengabaikan (tidak menolak) nilai dari non-Supervisor, baris lama dipertahankan, baris baru false ← SterilizerRecordService::upsertDetails() $actorIsSupervisor
- edge_cases (+1) = non-Supervisor membuka draft dengan baris sudah dicentang → teks 'Ya', tak dapat diubah; nilai dari non-Supervisor diabaikan server ← view + service

## v4 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (mobile/src/services/syncService.ts, backend/app/Services/SterilizerRecordService.php).
- business_rules[8] ← tambah: sinkron hanya kirim Checked by SPV bila Supervisor; baris tanpa id mewarisi lewat Sterilizer No + Close Door Time.
- edge_cases ← append: penggantian semua baris oleh non-Supervisor tidak menghapus centang SPV.
