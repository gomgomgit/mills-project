# Derived Assumptions Log — module-dashboard.screen-025--dashboard-web.4-implement

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v2 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser = 3 lulus/0 gagal dari e2e-full.log.counts.json (spec dashboard-home).
- Known issue 'Browser (Playwright) test ditulis lengkap tapi tidak dijalankan' dihapus.
- Path e2e-web/tests/dashboard-home.spec.ts ditambahkan ke test_files_generated (konvensi seragam lintas layar).

## v3 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/DashboardHomeTest.php` dihapus dari `fe_test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).
