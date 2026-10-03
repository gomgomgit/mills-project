# Derived Assumptions Log — module-master-data.screen-029--kelola-business-unit.4-implement

## v2 — 2026-08-31

- implementation_scope = "no code/test regeneration — verification only" ← not explicitly instructed by the user; chosen because `backend/app/Services/BusinessUnitService.php` and its existing test suite were confirmed (by direct inspection and by re-running `php artisan test` for the three relevant files, all passing) to already match tech-spec v6 exactly, having been correctly updated during the unrelated 2026-08-20 Production Line rework. Regenerating via code-writer-agent/test-writer-agent would have been pure churn — reproducing equivalent code/tests while risking loss of the existing hand-authored historical docblocks (e.g. BusinessUnitService's "2026-08-20 (entity-catalog v9): NO LONGER auto-provisions any stations..." comment).

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v3)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v4 — 2026-10-03

Mencatat perbaikan kode/uji 2026-10-03.
- Spec + paged-table.ts ditambahkan ke fe_test_files_generated.
- test_results tidak diubah (tak ada hitungan per-layar yang diberikan).
- Known issue 'browser test tidak dijalankan' (merujuk backend/tests/Browser yang sudah dihapus) DIBIARKAN karena test_results.browser tidak diisi — hapus bila hitungan e2e dicatat.

## v5 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser = 7 lulus/0 gagal dari e2e-full.log.counts.json (spec kelola-business-unit); 1 skip dicatat di catatan.
- Known_issue 'Browser test dibuat tapi tidak dijalankan' dihapus; path spec sudah ada di fe_test_files_generated.

## v6 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/KelolaBusinessUnitTest.php` dihapus dari `test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).
