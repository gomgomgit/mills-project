# Derived Assumptions Log — module-master-data.screen-027--kelola-corporate.4-implement

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v2)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v3 — 2026-10-03

Mencatat perbaikan kode/uji 2026-10-03.
- Spec + paged-table.ts ditambahkan ke fe_test_files_generated.
- test_results tidak diubah (tak ada hitungan per-layar yang diberikan).
- Known issue 'browser test tidak dijalankan' (merujuk backend/tests/Browser yang sudah dihapus) DIBIARKAN karena test_results.browser tidak diisi — hapus bila hitungan e2e dicatat.

## v4 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser = 6 lulus/0 gagal dari e2e-full.log.counts.json (spec kelola-corporate).
- Known_issue 'Browser test dibuat tapi tidak dijalankan' dihapus; path spec sudah ada di fe_test_files_generated.

## v5 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/KelolaCorporateTest.php` dihapus dari `test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).

## v6 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git diff HEAD KelolaCorporate.php, CorporateService.php, kelola-corporate.blade.php, tests/Pest.php; berkas baru app/Rules/*, app/Livewire/Concerns/ValidatesUploadOnSelect.php).
- files_generated (+) = backend/app/Rules/RealImage.php, backend/app/Rules/UniqueCaseInsensitive.php ← dipakai CorporateService.
- fe_files_generated (+) = backend/app/Livewire/Concerns/ValidatesUploadOnSelect.php ← trait dipakai KelolaCorporate.
- test_files_generated (+) = MasterDataValidationAuditTest.php, ImageUploadValidationTest.php, tests/Pest.php ← menguji KelolaCorporate / fakeRealImage() helper.
- implementation_notes (+) = REVISI audit-fix.
- known_issues[2].description = workaround GD kini via fakeRealImage() ← test diff mengganti fake()->create(...) ke fakeRealImage().

## v7 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit c321f32), code is truth (blade/Livewire layar ini, app/Livewire/Concerns/HasFilterReset.php, resources/views/components/filter/*, busy-label.blade.php).
- implementation_notes[-1] ← entri REVISI round 3: x-filter.bar + HasFilterReset (bila ada filter), ld-region, busy-label + disabled pada Simpan/Ya, Hapus/aksi baris, header flex-wrap; test bersama FilterBarTest.php / LoadingStateTest.php dirujuk di catatan, tidak didaftarkan di test_files_generated.
