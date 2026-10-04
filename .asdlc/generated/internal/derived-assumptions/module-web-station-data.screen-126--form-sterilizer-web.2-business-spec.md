
## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (form-sterilizer.blade.php, FormSterilizer.php, SterilizerRecordService.php).
- information_displayed[2], business_rules[2] = default WIB, Date tidak boleh melewati besok ← assertEventDateNotTooFarAhead.
- information_displayed[4] = Checked by SPV checkbox hanya Supervisor, lainnya teks Ya/Tidak ← blade @if isSupervisor().
- available_actions[4].actor_ids = [actor-supervisor] ← blade + upsertDetails() mengabaikan non-Supervisor (keputusan user 2026-10-04).
- business_rules[9] = Checked by SPV Supervisor-only; nilai peran lain diabaikan (baris baru Tidak, baris lama tetap) ← SterilizerRecordService::upsertDetails().
- edge_cases += tanggal > besok, id bukan UUID, non-Supervisor mengirim SPV tercentang.

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (SterilizerRecordService.php).
- business_rules[9], edge_cases (+1) ← non-Supervisor: baris tanpa id mewarisi centang SPV baris tersimpan dengan Sterilizer No + Close Door Time sama.
