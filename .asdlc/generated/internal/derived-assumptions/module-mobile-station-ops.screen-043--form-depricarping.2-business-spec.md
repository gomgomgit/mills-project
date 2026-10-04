# Derived Assumptions — module-mobile-station-ops.screen-043--form-depricarping.2-business-spec

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/FormDepricarpingView.vue, mobile/src/services/depricarpingRecordRepo.ts, mobile/src/utils/localDate.ts, mobile/src/services/syncService.ts, mobile/src/services/millSettingRepo.ts, backend/app/Services/DepricarpingRecordService.php).
- information_displayed[0] = Tanggal = tanggal LOKAL perangkat ← depricarpingRecordRepo.createDraft() kini todayLocalDateString() (sebelumnya toISOString() UTC).
- information_displayed[1] = 'Note' → 'Catatan' ← FormField id="field-note" label="Catatan".
- description = 'Note' → 'Catatan' ← label FormField.
- business_rules[0] = 'Note' → 'Catatan' ← label FormField.
- business_rules (aturan Tanggal) = tanggal LOKAL, draft 00:00–06:59 WIB tetap hari itu ← todayLocalDateString(); localDate.sqljs.spec.ts.
- edge_cases += 422 periode tertutup / tanggal > besok via write-through (dialog penolakan) atau sinkron manual ('Gagal sinkron: <alasan>') ← DepricarpingRecordService assertEventDateNotTooFarAhead('date'); FormDepricarpingView writeThroughRejection; millSettingRepo kini SELECT immediate_sync_enabled sehingga write-through benar-benar aktif; syncService sync_error + SyncFailureHint. (⚠ penempatan aturan server di spec layar form adalah inferensi.)
