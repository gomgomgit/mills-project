# Derived Assumptions Log — module-user-management.screen-032--kelola-user-role.4-implement

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v2 — 2026-10-03

Mencatat perbaikan kode/uji 2026-10-03.
- Spec + paged-table.ts ditambahkan ke fe_test_files_generated.
- test_results tidak diubah (tak ada hitungan per-layar yang diberikan).
- Known issue 'browser test tidak dijalankan' (merujuk backend/tests/Browser yang sudah dihapus) DIBIARKAN karena test_results.browser tidak diisi — hapus bila hitungan e2e dicatat.

## v3 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser = 7 lulus/0 gagal dari e2e-full.log.counts.json (spec kelola-user-role).
- Known_issue 'Browser test written but not run' dihapus; path spec sudah ada di fe_test_files_generated.

## v4 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/KelolaUserRoleTest.php` dihapus dari `test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git diff HEAD UserService.php, KelolaUserRole.php, kelola-user-role.blade.php, UserRole.php; berkas baru PasswordPolicy.php, KelolaUserRoleAuditTest.php).
- files_generated += Support/PasswordPolicy.php, Enums/UserRole.php
- test_files_generated += KelolaUserRoleAuditTest.php, WebAccessTest.php
- fe_test_files_generated += e2e-web/tests/audit-web-admin.spec.ts
- implementation_notes += REVISI 2026-10-04 (rules() Livewire dihapus, PasswordPolicy, Reset Password, username case-insensitive/tanpa spasi, wire:confirm, cabut token, pesan sukses, label role)

## v6 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (lihat 3-tech-spec).
- files_generated (+migrasi, User.php, EnsureUserIsActive.php, AppServiceProvider.php), test files (+AuditFix20261005Test, audit-fix-20261005.spec.ts), implementation_notes (+1).
- test_results ← dijalankan ulang: UserServiceTest 13, Api/KelolaUserRoleTest 11 + AuditFix20261005 #8b 7 = 18 integration, Livewire KelolaUserRole+Audit 13.
- ⚠ test_results.browser 8 (7 sebelumnya + 1 tes #8b) ← dari klaim commit, tidak dijalankan ulang.

## v7 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit 658cedc, c321f32), code is truth (blade/Livewire layar ini, app/Livewire/Concerns/HasFilterReset.php, resources/views/components/filter/*, busy-label.blade.php).
- implementation_notes[-1] ← entri REVISI round 3: x-filter.bar + HasFilterReset (bila ada filter), ld-region, busy-label + disabled pada Simpan/Ya, Hapus/aksi baris, header flex-wrap; test bersama FilterBarTest.php / LoadingStateTest.php dirujuk di catatan, tidak didaftarkan di test_files_generated.
