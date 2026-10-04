# Derived Assumptions Log — module-mobile-station-ops.screen-122--form-sterilizer.3-tech-spec

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Tidak menambah unit_test_cases: form tidak punya logika periode untuk diuji (simpan lokal tanpa cek periode).
- TEMUAN yang menyimpang dari brief: form ini memanggil writeThroughSync.syncAfterSave() setelah simpan lokal; bila Mills Setting immediate_sync_enabled aktif, record langsung di-POST ke server dan penolakan 422 PERIOD_CLOSED ditelan diam-diam. Didokumentasikan sebagai jalur tulis server, bukan sebagai 'tidak ada panggilan server'.
- Tidak menambah endpoint ke api_contracts (endpoints tetap kosong, sesuai instruksi) meskipun ada jalur POST write-through.
- Dikonfirmasi dari kode: form hanya membuka draft (draft_ongoing/draft_paused) — Data Preview mengarahkan record saved/synced ke layar preview, bukan form — sehingga tidak ada jalur edit/PATCH record tersinkron.
- Kalimat edge case/aturan/catatan dirumuskan sendiri; 'pemulihan = Admin membuka kembali baris stasiun di screen-142 lalu sinkron ulang' mengikuti brief.

## v3 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- Edge case usecase-141 ditulis ulang: penolakan 4xx write-through kini tampil lewat ConfirmDialog 'Tersimpan, tetapi ditolak server' lalu ke Monitor; offline/5xx tetap diam.
- implementation_notes[0] ('tidak ada panggilan API') diganti: SQLite lokal + sinkron manual Station List (STATION_PUSH_CONFIGS) + write-through bila immediate_sync_enabled aktif; Pause/Clear/Back tidak memanggil server.
- Catatan REVISI kunci periode: frasa 'kegagalannya diam' dipersempit menjadi offline/5xx saja; frasa 'kode tidak diubah' dihapus karena kode form berubah 2026-10-03.

## v4 — 2026-10-04

Penyeragaman checkbox verifikasi (keputusan user 2026-10-04).
- business_logic #8/#9: Checked By hanya dirender untuk supervisor (v-if="isSupervisor"), Acknowledged By hanya untuk mill_management (v-if="isMillManagement"); peran lain tidak melihat checkbox sama sekali (sebelumnya: tampil tapi disabled).
- edge_case_handling (Checked By / Acknowledged By oleh peran lain): "disabled/read-only" → "tidak dirender (disembunyikan, bukan disabled)".
- unit_test_cases: kasus "disables Checked By/Acknowledged By" diganti "not rendered"; kasus Acknowledged By kini memeriksa render untuk mill_management (Checked By tidak dirender).
- test_scenarios: assert operator "remain empty/disabled" → checkbox tidak dirender; skenario "Checked By Khusus Supervisor" → checkbox tidak ditampilkan (count 0).
- Selaras konvensi 2026-09-14 form stasiun lain; kode dan test sudah diubah lebih dulu.
- actor_permissions: ditambah entri actor-mill-management (can_access true; akses form seperti Operator/Supervisor + checkbox Acknowledged By, tanpa Checked By) — sinkronisasi dokumentasi, bukan perubahan akses: route hanya mensyaratkan login (router.beforeEach), tanpa pembatasan role.

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/FormSterilizerView.vue, mobile/src/services/sterilizerRecordRepo.ts, backend/app/Services/SterilizerRecordService.php, mobile/src/services/syncService.ts, mobile/src/services/millSettingRepo.ts, mobile/src/services/localSchema.ts, mobile/tests/FormSterilizerView.spec.ts, mobile/tests/e2e/form-sterilizer.spec.ts).
- actor_permissions[0..2].conditions = operator & mill_management: Checked by SPV per baris tampil teks 'Ya'/'—'; supervisor: satu-satunya yang mendapat checkbox per baris + Checked By header ← v-if="isSupervisor" pada <td> Checked by SPV
- api_contracts[0].business_logic[1] = Date diisi createDraft() via todayLocalDateString() bersama (helper lokal todayDateString() dihapus); view fallback todayLocalDateString() ← diff sterilizerRecordRepo.ts
- api_contracts[0].business_logic[2] = Verifikasi berisi "Catatan" (id field-note) ← diff template
- api_contracts[0].business_logic[5] = Checked by SPV ditandai hanya Supervisor (rujuk langkah 16) ← diff template
- api_contracts[0].business_logic (+16) = aturan render checkbox/teks per peran + server mengabaikan nilai non-Supervisor; repo lokal tidak men-strip per peran ← view, SterilizerRecordService::upsertDetails(), sterilizerRecordRepo.ts (tanpa perubahan strip)
- api_contracts[0].edge_case_handling (+3) = Checked by SPV non-Supervisor; date lokal/normalisasi UTC; sync_error + write-through aktif + line per record ← view/service, localSchema, syncService, millSettingRepo
- api_contracts[0].business_rules_applied (+1) = Checked by SPV hanya Supervisor (UI + server) ← view + service
- api_contracts[0].unit_test_cases (+2) = non-Supervisor (operator/mill_management/admin) → teks Ya/—; Supervisor → checkbox dapat diubah ← mobile/tests/FormSterilizerView.spec.ts (2 test baru)
- test_scenarios (+1) = "Checked by SPV per Baris Khusus Supervisor" ← FormSterilizerView.spec.ts + e2e 'Checked by SPV — hanya Supervisor yang dapat mengubah, Operator melihat teks'
- implementation_notes (append) = REVISI 2026-10-04 audit-fix (SPV hanya Supervisor, date lokal bersama, Catatan, write-through/sync_error/line, teleport, kosmetik header/Pause warning) ← diff kode

## v6 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (mobile/src/services/syncService.ts, backend/app/Services/SterilizerRecordService.php, backend/tests/Feature/AuditFix20261005Test.php).
- api_contracts[0].business_logic[15] ← payload hanya Supervisor; server spvMatchKey untuk baris tanpa id.
- api_contracts[0].edge_case_handling[10].handling ← diperbarui sama.
- implementation_notes ← append REVISI 2026-10-05.
- test_scenarios ← append skenario Checked by SPV non-Supervisor (POST supervisor 201 lalu PATCH operator 200 → tersimpan [true,false]).
- ⚠ Bila non-Supervisor mengubah close_door_time baris tercentang dan baris dikirim tanpa id, kunci tidak cocok → centang hilang (false) — perilaku kode, belum diputuskan produk.
