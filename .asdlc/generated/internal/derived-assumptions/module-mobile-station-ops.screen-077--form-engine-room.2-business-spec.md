# Derived Assumptions — module-mobile-station-ops.screen-077--form-engine-room.2-business-spec

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/FormEngineRoomView.vue, mobile/src/services/engineRoomRecordRepo.ts, mobile/src/utils/localDate.ts).
- information_displayed[0] = Tanggal otomatis dengan tanggal LOKAL perangkat; label catatan "Catatan" (bukan "Note") ← FormEngineRoomView.vue: FormField id="field-note" label="Catatan"; engineRoomRecordRepo.createDraft() memakai todayLocalDateString()
- business_rules[0] = Tanggal otomatis memakai tanggal LOKAL perangkat (bukan UTC; draft 00:00–06:59 WIB tetap bertanggal hari ini) ← engineRoomRecordRepo.createDraft() kini memakai todayLocalDateString()
