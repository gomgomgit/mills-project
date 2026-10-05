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

## v7 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git diff HEAD BusinessUnitService.php, KelolaBusinessUnit.php, blade, tests/*BusinessUnit*, tests/Pest.php; berkas baru MasterDataDeleteGuardTest.php, ImageUploadValidationTest.php).
- files_generated (+) = app/Rules/RealImage.php, app/Rules/UniqueCaseInsensitive.php.
- fe_files_generated (+) = app/Livewire/Concerns/ValidatesUploadOnSelect.php.
- test_files_generated (+) = MasterDataDeleteGuardTest.php, ImageUploadValidationTest.php, tests/Pest.php ← menguji KelolaBusinessUnit / fakeRealImage().
- implementation_notes[5] = tes logo memakai fakeRealImage() ← test diff.
- implementation_notes (+) = REVISI audit-fix.

## v8 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit 658cedc, c321f32), code is truth (blade/Livewire layar ini, app/Livewire/Concerns/HasFilterReset.php, resources/views/components/filter/*, busy-label.blade.php).
- implementation_notes[-1] ← entri REVISI round 3: x-filter.bar + HasFilterReset (bila ada filter), ld-region, busy-label + disabled pada Simpan/Ya, Hapus/aksi baris, header flex-wrap; test bersama FilterBarTest.php / LoadingStateTest.php dirujuk di catatan, tidak didaftarkan di test_files_generated.
