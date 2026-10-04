## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/LaporanClarificationView.vue, mobile/src/services/clarificationReportRepo.ts, ClarificationReportService.php).
- information_displayed[3] = label status Draft/Terbuka/Tertutup ← view closed: 'Tertutup' (sebelumnya 'Ditutup')
- information_displayed[15] = kelengkapan sampai hari ini + keterangan; '-' + 'belum ada slot yang diharapkan' bila expected 0 ← coverageDaysCounted / coveragePercentText
- information_displayed (+1) = pemilih Production Line ← view selectedProductionLineId (drift lama, ditambahkan karena kode acuan)
- available_actions[3].description = CSV dibentuk server, kolom Periode/Mill/Production Line, label Indonesia, slot HH:MM ← ClarificationReportService::EXPORT_HEADER
- business_rules (+1) = penyebut berhenti di hari ini dihitung server; 0 → '-' ← view docblock
- edge_cases (+2) = periode berjalan; expected 0 → '-' ← LaporanClarificationView.spec.ts
