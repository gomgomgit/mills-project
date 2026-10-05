## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/LaporanBoilerRoomView.vue, mobile/src/services/boilerRoomReportRepo.ts, BoilerRoomReportService.php).
- information_displayed[3] = label status Draft/Terbuka/Tertutup (sama dengan web) ← view closed: 'Tertutup' (sebelumnya 'Ditutup')
- information_displayed[12] = kelengkapan sampai hari ini + keterangan periode berjalan; '-' + 'belum ada slot yang diharapkan' bila expected 0 ← coverageDaysCounted / coveragePercentText / coverage-no-expected
- information_displayed (+1) = pemilih Production Line, angka/ekspor hanya setelah line berlaku ← view selectedProductionLineId/resolveProductionLine (drift lama, ditambahkan karena kode acuan)
- available_actions[3].description = CSV dibentuk server, kolom Periode/Mill/Production Line, label Indonesia, slot HH:MM, Ya/Tidak ← exportCsv + BoilerRoomReportService::EXPORT_HEADER
- business_rules (+1) = penyebut berhenti di hari ini dihitung server; ponsel tidak menghitung ulang; 0 → '-' ← view docblock
- edge_cases (+2) = periode berjalan; expected 0 → '-' ← LaporanBoilerRoomView.spec.ts

## v3 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit ee5294c, d5da9cf), code is truth (mobile/src/views/LaporanBoilerRoomView.vue, mobile/src/utils/latestRequest.ts, tests/laporanStaleResponse.spec.ts, tests/e2e/laporan-stale-response.spec.ts).
- edge_cases ← ganti pilihan cepat: hanya ringkasan pilihan terakhir yang tampil (respons basi diabaikan); nama berkas ekspor milik periode yang diekspor + Ekspor terkunci Mengekspor….
- ⚠ Chip Mill/Production Line (e4f231e) tidak memerlukan perubahan teks spec — information_displayed sudah menyebut keterangan mill/line; testid tetap.
