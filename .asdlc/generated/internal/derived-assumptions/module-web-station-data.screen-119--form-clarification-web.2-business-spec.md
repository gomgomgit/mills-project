
## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (ClarificationRecordService.php, FormClarification.php, EnforcesPeriodLock.php, AppTime.php, GuardsRecordIdShape.php).
- business_rules[1] = tanggal default hari ini (WIB), tidak boleh melewati besok, pesan inline ← assertEventDateNotTooFarAhead di create/update; config app.timezone Asia/Jakarta.
- edge_cases += tanggal > besok ditolak inline; id bukan UUID → 'Record tidak ditemukan.' ← GuardsRecordIdShape di FormClarification::mount.
