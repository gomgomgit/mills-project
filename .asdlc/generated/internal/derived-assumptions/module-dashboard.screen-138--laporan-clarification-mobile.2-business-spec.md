## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/LaporanClarificationView.vue, mobile/src/services/clarificationReportRepo.ts, ClarificationReportService.php).
- information_displayed[3] = label status Draft/Terbuka/Tertutup ← view closed: 'Tertutup' (sebelumnya 'Ditutup')
- information_displayed[15] = kelengkapan sampai hari ini + keterangan; '-' + 'belum ada slot yang diharapkan' bila expected 0 ← coverageDaysCounted / coveragePercentText
- information_displayed (+1) = pemilih Production Line ← view selectedProductionLineId (drift lama, ditambahkan karena kode acuan)
- available_actions[3].description = CSV dibentuk server, kolom Periode/Mill/Production Line, label Indonesia, slot HH:MM ← ClarificationReportService::EXPORT_HEADER
- business_rules (+1) = penyebut berhenti di hari ini dihitung server; 0 → '-' ← view docblock
- edge_cases (+2) = periode berjalan; expected 0 → '-' ← LaporanClarificationView.spec.ts

## v3 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit ee5294c, d5da9cf), code is truth (mobile/src/views/LaporanClarificationView.vue, mobile/src/utils/latestRequest.ts, tests/laporanStaleResponse.spec.ts, tests/e2e/laporan-stale-response.spec.ts).
- edge_cases ← ganti pilihan cepat: hanya ringkasan pilihan terakhir yang tampil (respons basi diabaikan); nama berkas ekspor milik periode yang diekspor + Ekspor terkunci Mengekspor….
- ⚠ Chip Mill/Production Line (e4f231e) tidak memerlukan perubahan teks spec — information_displayed sudah menyebut keterangan mill/line; testid tetap.
