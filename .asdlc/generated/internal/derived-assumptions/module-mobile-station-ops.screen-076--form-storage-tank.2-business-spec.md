# Derived Assumptions — module-mobile-station-ops.screen-076--form-storage-tank.2-business-spec

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/FormStorageTankView.vue, mobile/src/services/storageTankRecordRepo.ts, mobile/src/utils/localDate.ts). Patch dilakukan agen sebelumnya (terputus sebelum mencatat log); entri ini ditulis menyusul.
- information_displayed[0] = Tanggal otomatis dengan tanggal LOKAL perangkat ← storageTankRecordRepo.createDraft() memakai todayLocalDateString()
- information_displayed[1] = label catatan "Catatan" (bukan "Note") ← FormStorageTankView.vue: FormField id="field-note" label="Catatan"
- business_rules[0] = Tanggal otomatis memakai tanggal LOKAL perangkat (bukan UTC; draft 00:00–06:59 WIB tetap bertanggal hari ini) ← createDraft() todayLocalDateString()
