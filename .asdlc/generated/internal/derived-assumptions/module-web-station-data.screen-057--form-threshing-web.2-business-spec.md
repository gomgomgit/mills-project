
## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (ThreshingRecordService.php, FormThreshing.php, EnforcesPeriodLock.php, GuardsRecordIdShape.php).
- information_displayed[2] = Tanggal default hari ini (WIB), tidak boleh melewati besok ← assertEventDateNotTooFarAhead, config app.timezone Asia/Jakarta.
- business_rules[2] = tanggal > besok ditolak inline ← ValidationException errors.date ditangkap FormThreshing::save() ke errors_.
- edge_cases += tanggal > besok; id edit bukan UUID → 'Record tidak ditemukan.' ← GuardsRecordIdShape.
