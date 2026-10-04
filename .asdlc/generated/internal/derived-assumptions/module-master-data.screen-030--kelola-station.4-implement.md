# Derived Assumptions Log — module-master-data.screen-030--kelola-station.4-implement

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v2 — 2026-10-03

Mencatat perbaikan kode/uji 2026-10-03.
- searchable-select.blade.php (komponen bersama) dicatat di fe_files_generated layar ini; artefak project-level shared-modules sengaja tidak disentuh.
- Seeder fixture + paged-table.ts + spec dimasukkan ke fe_test_files_generated.
- Known issue 'browser test tidak dijalankan' dihapus; test_results.browser 9/0.

## v3 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/KelolaStationTest.php` dihapus dari `test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).

## v4 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git diff HEAD KelolaStation.php, StationService.php, kelola-station.blade.php, components/searchable-select.blade.php; berkas baru searchable-select-assets.blade.php, KelolaStationAuditTest.php, e2e-web/tests/searchable-select-dependent.spec.ts).
- files_generated (+) = app/Rules/UniqueCaseInsensitive.php.
- fe_files_generated (+) = components/searchable-select-assets.blade.php, components/layouts/app.blade.php ← aset combobox dimuat sekali per halaman oleh layout.
- test_files_generated (+) = KelolaStationAuditTest.php; fe_test_files_generated (+) = e2e-web/tests/searchable-select-dependent.spec.ts (skenario Kelola Station ganti BU).
- implementation_notes[1] = is_active hanya dilarang untuk Other ← validate() after().
- implementation_notes (+) = REVISI audit-fix.
- known_issues (+) = delete() tidak memeriksa record stasiun (FK restrictOnDelete → error DB tak tertangkap di confirmDelete) dan filter production_line_id tidak ada di API ⚠ INFERENSI dari kode (delete() + migrasi restrictOnDelete + catch di confirmDelete), tidak dibuktikan lewat run.

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (StationService.php, Api/StationController.php, tes terkait).
- known_issues ← dikosongkan: kedua isu (guard record → 500, filter line API) sudah diperbaiki.
- test_files_generated / fe_test_files_generated ← + AuditFix20261005Test.php, audit-fix-20261005.spec.ts (#1, #7).
- test_results unit/integration/component ← dijalankan ulang 2026-10-05: StationServiceTest 53, Api/KelolaStationTest 36, Livewire KelolaStationTest+AuditTest 35, semua lulus.
- ⚠ test_results.browser 13 (kelola-station.spec.ts 11 + audit #1/#7) ← berdasarkan klaim commit 'e2e-web 554 lulus', tidak dijalankan ulang oleh agen.
