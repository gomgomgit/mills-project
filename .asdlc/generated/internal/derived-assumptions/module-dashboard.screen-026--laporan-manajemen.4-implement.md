# Derived Assumptions Log — module-dashboard.screen-026--laporan-manajemen.4-implement

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v2 — 2026-10-03

Mencatat perbaikan kode/uji 2026-10-03.
- Known issue tunggal 'browser test tidak dijalankan' dihapus karena e2e lulus 5/5; test_results.browser diisi 5/0 run_at 2026-10-03.
- e2e-web/tests/management-report.spec.ts ditambahkan ke fe_test_files_generated.

## v3 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/ManagementReportTest.php` dihapus dari `fe_test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).

## v4 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git status/diff: ManagementReportService.php, ManagementReport.php, management-report.blade.php, ManagementReportController.php, Support/SheetWriter.php, Support/Display.php).
- files_generated += backend/app/Support/SheetWriter.php ← export() memakai SheetWriter::open()
- fe_files_generated += backend/app/Support/Display.php ← blade memakai Display::number/date
- test_files_generated += backend/tests/Feature/ExportXlsxTest.php ← tes 'ekspor Laporan Manajemen format=excel adalah xlsx sungguhan'
- implementation_notes[3] = ekspor via SheetWriter (csv / xlsx sungguhan) ← fileMetaFor docblock + SheetWriter
- implementation_notes += REVISI (2026-10-04, audit-fix): line wajib, split WB, rentang invalid, ekspor, tampilan Indonesia ← diff kode
- test_results tidak diubah (tidak menjalankan tes per brief)

## v5 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit 658cedc, c321f32), code is truth (management-report.blade.php, LoadingStateTest.php).
- implementation_notes[+] ← filter bar bersama + loading ekspor; uji bersama disebut di catatan
