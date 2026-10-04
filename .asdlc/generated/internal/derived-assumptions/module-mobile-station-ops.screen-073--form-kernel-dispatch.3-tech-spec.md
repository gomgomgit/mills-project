# Derived Assumptions Log — module-mobile-station-ops.screen-073--form-kernel-dispatch.3-tech-spec

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Tidak menambah unit_test_cases: form tidak punya logika periode untuk diuji (simpan lokal tanpa cek periode).
- TEMUAN yang menyimpang dari brief: form ini memanggil writeThroughSync.syncAfterSave() setelah simpan lokal; bila Mills Setting immediate_sync_enabled aktif, record langsung di-POST ke server dan penolakan 422 PERIOD_CLOSED ditelan diam-diam. Didokumentasikan sebagai jalur tulis server, bukan sebagai 'tidak ada panggilan server'.
- Tidak menambah endpoint ke api_contracts (endpoints tetap kosong, sesuai instruksi) meskipun ada jalur POST write-through.
- Dikonfirmasi dari kode: form hanya membuka draft (draft_ongoing/draft_paused) — Data Preview mengarahkan record saved/synced ke layar preview, bukan form — sehingga tidak ada jalur edit/PATCH record tersinkron.
- Kalimat edge case/aturan/catatan dirumuskan sendiri; 'pemulihan = Admin membuka kembali baris stasiun di screen-142 lalu sinkron ulang' mengikuti brief.
- Menegaskan bahwa yang dinilai adalah `date` header, bukan event_date per baris detail (dikonfirmasi di KernelDispatchRecordService dan payload syncService).

## v3 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- edge_case usecase-141: penolakan write-through 4xx kini ditampilkan lewat ConfirmDialog 'Tersimpan, tetapi ditolak server' lalu navigasi ke Monitor; offline/5xx tetap diam (redaksi dipilih agen, diverifikasi terhadap writeThroughSync.ts + Form*View.vue).
- implementation_notes[0]: 'sync manual terpisah' diganti — record 'saved' dikirim lewat sinkron manual Station List (STATION_PUSH_CONFIGS) dan write-through syncAfterSave() bila immediate_sync_enabled aktif.
- Catatan REVISI kunci periode: frasa 'kegagalannya diam' diganti dengan pembedaan offline/5xx (diam) vs 4xx (dialog).

## v4 — 2026-10-04

Penyeragaman checkbox verifikasi (keputusan user 2026-10-04).
- business_logic #8/#9: Checked By hanya dirender untuk supervisor (v-if="isSupervisor"), Acknowledged By hanya untuk mill_management (v-if="isMillManagement"); peran lain tidak melihat checkbox sama sekali (sebelumnya: tampil tapi disabled).
- edge_case_handling (Checked By / Acknowledged By oleh peran lain): "disabled/read-only" → "tidak dirender (disembunyikan, bukan disabled)".
- unit_test_cases: kasus "disables Checked By/Acknowledged By" diganti "not rendered"; kasus Acknowledged By kini memeriksa render untuk mill_management (Checked By tidak dirender).
- test_scenarios: assert operator "remain empty/disabled" → checkbox tidak dirender; skenario "Checked By Khusus Supervisor" → checkbox tidak ditampilkan (count 0).
- Selaras konvensi 2026-09-14 form stasiun lain; kode dan test sudah diubah lebih dulu.
- actor_permissions: ditambah entri actor-mill-management (can_access true; akses form seperti Operator/Supervisor + checkbox Acknowledged By, tanpa Checked By) — sinkronisasi dokumentasi, bukan perubahan akses: route hanya mensyaratkan login (router.beforeEach), tanpa pembatasan role.

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/services/kernelDispatchRecordRepo.ts, mobile/src/views/FormKernelDispatchView.vue, mobile/src/services/syncService.ts, mobile/src/services/millSettingRepo.ts, backend/app/Services/KernelDispatchRecordService.php, backend/app/Support/Concerns/EnforcesPeriodLock.php).
- api_contracts[0].business_logic[1] = createDraft() memakai util bersama todayLocalDateString(); view fallback todayLocalDateString() ← diff repo (helper todayDateString dihapus) + view
- api_contracts[0].business_logic[2] = Verifikasi berisi "Catatan" (id field-note) ← diff template view
- api_contracts[0].edge_case_handling (+2) = (a) event_date baris di periode tertutup → 422 PERIOD_CLOSED 'Baris log bertanggal kejadian … ditolak.', date/event_date > hari ini+EVENT_DATE_MAX_DAYS_AHEAD (default 1) → 422 VALIDATION_ERROR (field date / details); (b) penolakan 4xx menulis sync_error lokal, ditampilkan Data Preview ← KernelDispatchRecordService::create()/update(), EnforcesPeriodLock, syncService.failure()/rememberSyncError()
- api_contracts[0].business_rules_applied[5] = kunci periode dinilai terhadap date header DAN setiap event_date baris; tanggal tidak boleh > besok ← assertDetailEventDatesWritable('kernel-dispatch', …) di KernelDispatchRecordService
- implementation_notes (append) = write-through aktif sungguhan, payload date lokal, line per record, guard detail server ← diff millSettingRepo.ts, syncService.ts, KernelDispatchRecordService.php
