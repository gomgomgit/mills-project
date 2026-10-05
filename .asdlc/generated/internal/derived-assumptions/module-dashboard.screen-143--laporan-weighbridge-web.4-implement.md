# Derived Assumptions Log — module-dashboard.screen-143--laporan-weighbridge-web.4-implement

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v2)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v3 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser = 36 lulus/0 gagal dari e2e-full.log.counts.json (spec laporan-weighbridge).
- Known_issue tentang BrowserTestFixtureSeeder dan angka regresi enam spec lama dipertahankan — bukan pernyataan 'browser test tidak dijalankan' untuk layar ini.

## v4 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git diff service/blade/tests).
- files_generated[+] = Support/ReportPeriodDays, SheetWriter, ExportValue, Display, ChartAxis, AppTime ← dipakai service/blade (Display & AppTime tak langsung — ⚠)
- fe_files_generated[+] = report-styles.blade.php ← aturan .md-scrollhint--lc / .md-trendchart--days dipakai blade ini
- test_files_generated[+] = ReportAuditFix20261004Test, ExportXlsxTest, e2e-web/tests/support/backend.ts, base-url.ts ← diimpor/mencakup layar ini
- implementation_notes[+1] = REVISI audit-fix ← diff

## v5 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit db73fbd, c321f32), code is truth (laporan-*.blade.php, report-filter-bar.blade.php, tests/Feature/Livewire/Laporan*Test.php, e2e-web/tests/laporan-*.spec.ts).
- implementation_notes[+] ← report-filter-bar, loading ekspor & .ld-region, uji yang disesuaikan
