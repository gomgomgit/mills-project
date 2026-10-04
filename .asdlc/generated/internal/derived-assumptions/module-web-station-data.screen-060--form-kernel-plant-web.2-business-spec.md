
## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (KernelPlantRecordService.php, FormKernelPlant.php, EnforcesPeriodLock.php, GuardsRecordIdShape.php).
- information_displayed[0] = Tanggal default hari ini (WIB), tidak boleh melewati besok ← assertEventDateNotTooFarAhead, config app.timezone Asia/Jakarta.
- business_rules[+ (appended)] = tanggal > besok ditolak inline ← ValidationException errors.date ditangkap FormKernelPlant::save() ke errors_.
- edge_cases += tanggal > besok; id edit bukan UUID → 'Record tidak ditemukan.' ← GuardsRecordIdShape.
