# Derived Assumptions — module-mobile-station-ops.screen-041--form-threshing.2-business-spec

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/FormThreshingView.vue, mobile/src/services/threshingRecordRepo.ts, mobile/src/utils/localDate.ts, mobile/src/services/syncService.ts, mobile/src/services/millSettingRepo.ts, backend/app/Services/ThreshingRecordService.php).
- information_displayed[0] = Tanggal = tanggal LOKAL perangkat ← threshingRecordRepo.createDraft() kini todayLocalDateString() (sebelumnya toISOString() UTC).
- information_displayed[1] = 'Note' → 'Catatan' ← FormField id="field-note" label="Catatan".
- business_rules (aturan Tanggal) = tanggal LOKAL, draft 00:00–06:59 WIB tetap hari itu ← todayLocalDateString(); localDate.sqljs.spec.ts.
- edge_cases += 422 periode tertutup / tanggal > besok via write-through (dialog penolakan) atau sinkron manual ('Gagal sinkron: <alasan>') ← ThreshingRecordService assertEventDateNotTooFarAhead('date'); FormThreshingView writeThroughRejection; millSettingRepo kini SELECT immediate_sync_enabled sehingga write-through benar-benar aktif; syncService sync_error + SyncFailureHint. (⚠ penempatan aturan server di spec layar form adalah inferensi.)
