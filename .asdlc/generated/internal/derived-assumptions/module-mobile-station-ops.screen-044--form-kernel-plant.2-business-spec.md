
## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/FormKernelPlantView.vue, mobile/src/services/kernelPlantRecordRepo.ts, mobile/src/utils/localDate.ts).
- information_displayed[1] = label catatan "Catatan" (bukan "Note") ← FormKernelPlantView.vue: FormField id="field-note" label="Catatan"
- business_rules[1] = Tanggal otomatis memakai tanggal LOKAL perangkat (bukan UTC) ← kernelPlantRecordRepo.createDraft() kini memakai todayLocalDateString()
- business_rules[0] = 'Note' → 'Catatan' dalam daftar field tidak wajib ← label view
