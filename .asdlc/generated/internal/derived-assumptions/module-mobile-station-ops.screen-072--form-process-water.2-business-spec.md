
## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/FormProcessWaterView.vue, mobile/src/services/processWaterRecordRepo.ts, mobile/src/utils/localDate.ts).
- information_displayed[1] = label catatan "Catatan" (bukan "Note") ← FormProcessWaterView.vue: FormField id="field-note" label="Catatan"
- business_rules[0] = Tanggal otomatis memakai tanggal LOKAL perangkat (bukan UTC) ← processWaterRecordRepo.createDraft() kini memakai todayLocalDateString()
