# Derived Assumptions Log — module-dashboard.screen-139--laporan-storage-tank-mobile.2-business-spec

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/LaporanStorageTankView.vue, storageTankReportRepo.ts, StorageTankReportService.php).
- information_displayed[3] = label status periode tertutup 'Tertutup' ← PERIOD_STATUS_LABEL closed: 'Ditutup' → 'Tertutup'
- information_displayed[16] = kelengkapan 1 desimal, rumus days_counted '(sampai hari ini)', period-running-note, '-' bila expected 0 ← coveragePercentText / coverageDaysCounted / coverage-no-expected
- business_rules[+1] = penyebut sampai hari ini, 0 → '-', 1 desimal ← ReportPeriodDays + view
- edge_cases[+2] = periode berjalan; penyebut 0 ← LaporanStorageTankView.spec.ts blok 'kelengkapan periode berjalan'

## v3 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit ee5294c, d5da9cf), code is truth (mobile/src/views/LaporanStorageTankView.vue, mobile/src/utils/latestRequest.ts, tests/laporanStaleResponse.spec.ts, tests/e2e/laporan-stale-response.spec.ts).
- edge_cases ← ganti pilihan cepat: hanya ringkasan pilihan terakhir yang tampil (respons basi diabaikan); nama berkas ekspor milik periode yang diekspor + Ekspor terkunci Mengekspor….
- ⚠ Chip Mill/Production Line (e4f231e) tidak memerlukan perubahan teks spec — information_displayed sudah menyebut keterangan mill/line; testid tetap.
