# Derived Assumptions Log — module-dashboard.screen-139--laporan-storage-tank-mobile.2-business-spec

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/LaporanStorageTankView.vue, storageTankReportRepo.ts, StorageTankReportService.php).
- information_displayed[3] = label status periode tertutup 'Tertutup' ← PERIOD_STATUS_LABEL closed: 'Ditutup' → 'Tertutup'
- information_displayed[16] = kelengkapan 1 desimal, rumus days_counted '(sampai hari ini)', period-running-note, '-' bila expected 0 ← coveragePercentText / coverageDaysCounted / coverage-no-expected
- business_rules[+1] = penyebut sampai hari ini, 0 → '-', 1 desimal ← ReportPeriodDays + view
- edge_cases[+2] = periode berjalan; penyebut 0 ← LaporanStorageTankView.spec.ts blok 'kelengkapan periode berjalan'
