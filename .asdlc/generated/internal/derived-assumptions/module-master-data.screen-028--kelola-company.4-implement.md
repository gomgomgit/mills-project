# Derived Assumptions Log — module-master-data.screen-028--kelola-company.4-implement

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v3)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v4 — 2026-10-03

Mencatat perbaikan kode/uji 2026-10-03.
- Spec + paged-table.ts ditambahkan ke fe_test_files_generated.
- test_results tidak diubah (tak ada hitungan per-layar yang diberikan).
- known_issues layar ini memang kosong — tidak ada yang dihapus.

## v5 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser = 6 lulus/0 gagal dari e2e-full.log.counts.json (spec kelola-company); 1 skip dicatat di catatan.
- Path spec sudah ada di fe_test_files_generated; tidak ada known_issue untuk dihapus.

## v6 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/KelolaCompanyTest.php` dihapus dari `test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).

## v7 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git diff HEAD KelolaCompany.php, CompanyService.php, kelola-company.blade.php, tests/*Company*, tests/Pest.php).
- files_generated (+) = app/Rules/RealImage.php, app/Rules/UniqueCaseInsensitive.php ← dipakai CompanyService.
- fe_files_generated (+) = app/Livewire/Concerns/ValidatesUploadOnSelect.php ← trait di KelolaCompany.
- test_files_generated (+) = MasterDataValidationAuditTest.php, ImageUploadValidationTest.php, tests/Pest.php ← menguji KelolaCompany / helper fakeRealImage().
- implementation_notes[4] = tes logo kini fakeRealImage() ← test diff.
- implementation_notes (+) = REVISI audit-fix.

## v8 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit 658cedc, c321f32), code is truth (blade/Livewire layar ini, app/Livewire/Concerns/HasFilterReset.php, resources/views/components/filter/*, busy-label.blade.php).
- implementation_notes[-1] ← entri REVISI round 3: x-filter.bar + HasFilterReset (bila ada filter), ld-region, busy-label + disabled pada Simpan/Ya, Hapus/aksi baris, header flex-wrap; test bersama FilterBarTest.php / LoadingStateTest.php dirujuk di catatan, tidak didaftarkan di test_files_generated.
