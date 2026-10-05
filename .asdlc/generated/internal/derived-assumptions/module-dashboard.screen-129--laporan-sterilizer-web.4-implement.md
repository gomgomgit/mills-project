# Derived Assumptions Log — module-dashboard.screen-129--laporan-sterilizer-web.4-implement

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v2)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v3 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser = 15 lulus/0 gagal (run_at 2026-10-02 → 2026-10-03) dari e2e-full.log.counts.json (spec laporan-sterilizer).
- Tidak ada known_issue 'browser belum dijalankan'; path spec sudah tercatat.

## v4 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (SterilizerReportService.php, laporan-sterilizer.blade.php, report-styles.blade.php, layouts/app.blade.php, e2e-web/tests/laporan-sterilizer.spec.ts, git status untracked).
- files_generated[+] = backend/app/Support/SheetWriter.php, backend/app/Support/ExportValue.php ← dipakai export()
- test_files_generated[+] = backend/tests/Feature/ReportAuditFix20261004Test.php, backend/tests/Feature/ExportXlsxTest.php ← keduanya menguji laporan Sterilizer (CSV #8, hero #10, xlsx)
- known_issues[5].description = DIPERBAIKI — sidebar disaring RouteAccess, halaman errors/403 ← layouts/app.blade.php RouteAccess::allows
- known_issues[7].description = DIPERBAIKI — pruneLaneData di awal & afterAll spec browser ← diff laporan-sterilizer.spec.ts
- implementation_notes[+] = REVISI (2026-10-04, audit-fix …) ← ringkasan diff
- test_results tidak diubah (tidak ada run baru yang tercatat)

## v5 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit db73fbd, c321f32), code is truth (laporan-*.blade.php, report-filter-bar.blade.php, tests/Feature/Livewire/Laporan*Test.php, e2e-web/tests/laporan-*.spec.ts).
- implementation_notes[+] ← report-filter-bar, loading ekspor & .ld-region, uji yang disesuaikan
