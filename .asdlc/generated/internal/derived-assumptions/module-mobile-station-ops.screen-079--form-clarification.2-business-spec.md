# Derived Assumptions — module-mobile-station-ops.screen-079--form-clarification.2-business-spec

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/FormClarificationView.vue, mobile/src/services/clarificationRecordRepo.ts, mobile/src/utils/localDate.ts).
- information_displayed[0] = Tanggal otomatis dengan tanggal LOKAL perangkat; label catatan "Catatan" (bukan "Note") ← FormClarificationView.vue: FormField id="field-note" label="Catatan"; clarificationRecordRepo.createDraft() memakai todayLocalDateString()
- business_rules[0] = Tanggal otomatis memakai tanggal LOKAL perangkat (bukan UTC; draft 00:00–06:59 WIB tetap bertanggal hari ini) ← clarificationRecordRepo.createDraft() kini memakai todayLocalDateString()
