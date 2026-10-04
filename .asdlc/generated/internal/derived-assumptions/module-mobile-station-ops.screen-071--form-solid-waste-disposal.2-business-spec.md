
## v2 — 2026-10-04

Penyeragaman checkbox verifikasi (keputusan user 2026-10-04).
- available_actions[5] "Isi Acknowledged By": actor_ids ["actor-supervisor"] → ["actor-mill-management"], selaras business_rules (Acknowledged By hanya Mill Management), actor-index, dan kode (v-if="isMillManagement").
- information_displayed[1] (Verifikasi): diperjelas bahwa checkbox Checked By hanya ditampilkan untuk Supervisor dan Acknowledged By hanya untuk Mill Management; peran lain tidak melihatnya (disembunyikan, bukan dinonaktifkan).
- actors: ditambah "actor-mill-management" (sinkronisasi dokumentasi, bukan perubahan akses — route form hanya mensyaratkan login tanpa pembatasan role, dan view merender Acknowledged By untuk mill_management; akses mobile Mill Management sesuai keputusan produk 2026-09-14).

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/FormSolidWasteDisposalView.vue, mobile/src/services/solidWasteDisposalRecordRepo.ts, backend/app/Services/SolidWasteDisposalRecordService.php, backend/app/Support/Concerns/EnforcesPeriodLock.php).
- information_displayed[1] = label catatan "Catatan" (bukan "Note") ← FormSolidWasteDisposalView.vue: FormField id="field-note" label="Catatan"
- business_rules[0] = Date otomatis memakai tanggal LOKAL perangkat (bukan UTC) ← solidWasteDisposalRecordRepo.createDraft() memakai todayLocalDateString() (util bersama, menggantikan helper todayDateString())
- edge_cases (append) = Tanggal Kejadian baris di periode tertutup atau Date/Tanggal Kejadian > besok → ditolak server saat dikirim, alasan ditampilkan ← SolidWasteDisposalRecordService: assertDetailEventDatesWritable('solid-waste-disposal', …) + assertEventDateNotTooFarAhead (header & per baris)
- description = 'data log Cages Track' → 'data log Solid Waste Disposal' ← salah tulis lama di spec (bukan perubahan kode); layar ini jelas Form Solid Waste Disposal
