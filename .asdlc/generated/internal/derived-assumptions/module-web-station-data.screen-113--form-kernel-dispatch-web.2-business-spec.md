
## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (KernelDispatchRecordService.php, EnforcesPeriodLock.php, form-kernel-dispatch.blade.php, GuardsRecordIdShape.php).
- information_displayed[4] = Net Weight per baris tampil sebagai teks ← blade span kf-computed-value.
- business_rules[2] = Date & Tanggal Kejadian <= besok, Tanggal Kejadian harus sah ← assertEventDateNotTooFarAhead, validateDetails().
- business_rules[4] = Net Weight teks, bukan input nonaktif ← blade.
- business_rules += kunci periode berlaku untuk tanggal header dan setiap baris (lama+baru) ← assertDetailEventDatesWritable. ⚠ Artefak ini (v1) belum memuat aturan kunci periode header; dimasukkan dalam aturan yang sama.
- edge_cases += baris di luar periode Terbuka; tanggal > besok; id bukan UUID.
