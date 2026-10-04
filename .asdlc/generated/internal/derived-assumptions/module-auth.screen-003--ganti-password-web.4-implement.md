# Derived Assumptions Log — module-auth.screen-003--ganti-password-web.4-implement

## v1 — 2026-08-17

- password.blade.php dibangun sekaligus sebagai shell sidebar+header (belum ada shared app layout, karena screen-001/002 adalah pre-auth) ← ditandai untuk diekstrak ke layouts.app bersama nanti
- Middleware role:admin,supervisor,mill_management ditambahkan di kedua route untuk menegakkan actor_permissions ← eksplisit dari tech-spec, translasi teknis langsung
- Sesi lama tidak di-invalidate setelah ganti password ← sesuai asumsi open-question di tech-spec
- Business logic dibagi identik antara API controller dan Livewire component via AuthService::changePassword() ← keputusan desain agent, konsisten dengan pola screen-001

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v2 — 2026-10-03

Mencatat perbaikan kode/uji 2026-10-03.
- change-password-web.spec.ts ditambahkan ke fe_test_files_generated.
- test_results tidak diubah (tak ada hitungan per-layar yang diberikan).
- Known issue 'browser test tidak dijalankan' (merujuk backend/tests/Browser yang sudah dihapus) DIBIARKAN karena test_results.browser tidak diisi — hapus bila hitungan e2e dicatat.

## v3 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser = 4 lulus/0 gagal dari e2e-full.log.counts.json (spec change-password-web).
- Known issue 'Browser test ... dibuat tapi tidak dijalankan' dihapus; path spec sudah ada di fe_test_files_generated sehingga tidak ditambah.

## v4 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/ChangePasswordWebTest.php` dihapus dari `test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git diff HEAD routes/web.php, AuthService.php; berkas baru PasswordPolicy.php, WebAccessTest.php, audit-web-admin.spec.ts).
- files_generated += backend/app/Support/PasswordPolicy.php
- test_files_generated += backend/tests/Feature/WebAccessTest.php
- fe_test_files_generated += e2e-web/tests/audit-web-admin.spec.ts
- implementation_notes += REVISI 2026-10-04 (rute web menerima operator, PasswordPolicy)
