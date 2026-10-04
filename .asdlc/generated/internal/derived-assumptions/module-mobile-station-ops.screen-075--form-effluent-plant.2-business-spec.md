
## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/FormEffluentPlantView.vue, mobile/src/services/effluentPlantRecordRepo.ts, mobile/src/utils/localDate.ts).
- information_displayed[1] = label catatan "Catatan" (bukan "Note") ← FormEffluentPlantView.vue: FormField id="field-note" label="Catatan"
- business_rules[0] = Tanggal otomatis memakai tanggal LOKAL perangkat (bukan UTC) ← effluentPlantRecordRepo.createDraft() kini memakai todayLocalDateString()
