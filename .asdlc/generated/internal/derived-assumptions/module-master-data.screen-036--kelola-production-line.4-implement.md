# Derived Assumptions Log — module-master-data.screen-036--kelola-production-line.4-implement

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v5)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v6 — 2026-10-03

Sinkronisasi catatan uji 4-implement dengan uji yang ditambahkan 2026-10-03.
- deferred_items 'Browser (Playwright) test suite' dihapus karena spec e2e-web kini ada dan lulus 8/8.
- known_issue tentang tests/Browser/KelolaProductionLineTest.php tidak dijalankan php artisan test dipertahankan — masih benar untuk berkas itu.
- e2e-web/tests/kelola-production-line.spec.ts ditambahkan ke fe_test_files_generated.

## v7 — 2026-10-03

Mencatat perbaikan kode/uji 2026-10-03.
- 4-implement layar ini tidak menyebut searchable-select, jadi perbaikan komponen tidak dicatat di sini.
- Known issue tentang tests/Browser dihapus (direktori terhapus di 8879d8d, diverifikasi ls); entri berkas lama di test_files_generated dibiarkan sebagai jejak historis.

## v8 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/KelolaProductionLineTest.php` dihapus dari `test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).

## v9 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git diff HEAD ProductionLineService.php, KelolaProductionLine.php, blade, tests/*ProductionLine*, e2e-web/tests/kelola-production-line.spec.ts).
- files_generated (+) = app/Rules/UniqueCaseInsensitive.php, app/Services/StationService.php ← dipakai ProductionLineService (RECORD_TABLES).
- test_files_generated (+) = MasterDataDeleteGuardTest.php, MasterDataValidationAuditTest.php ← menguji KelolaProductionLine.
- implementation_notes (+) = REVISI audit-fix.

## v10 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit 658cedc, c321f32), code is truth (blade/Livewire layar ini, app/Livewire/Concerns/HasFilterReset.php, resources/views/components/filter/*, busy-label.blade.php).
- implementation_notes[-1] ← entri REVISI round 3: x-filter.bar + HasFilterReset (bila ada filter), ld-region, busy-label + disabled pada Simpan/Ya, Hapus/aksi baris, header flex-wrap; test bersama FilterBarTest.php / LoadingStateTest.php dirujuk di catatan, tidak didaftarkan di test_files_generated.
