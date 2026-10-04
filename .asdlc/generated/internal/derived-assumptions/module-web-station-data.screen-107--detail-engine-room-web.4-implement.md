# Derived Assumptions Log — module-web-station-data.screen-107--detail-engine-room-web.4-implement

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Catatan menyebut bahwa aksi verifikasi itu sendiri sebelumnya tidak terdokumentasi di artefak ini.
- Test yang dikutip: tests/Feature/Livewire/DetailRecordVerificationTest.php (diwakili DetailCagesTrack — tidak ada test per stasiun untuk layar ini), tests/Feature/Api/RecordVerificationTest.php, tests/Unit/Support/EnforcesPeriodLockTest.php. test_results tidak disentuh.

## v3 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- Hitungan diambil dari e2e-full.log.counts.json (per-spec [lulus, gagal, skip]). test_results.browser = 3 lulus, 0 gagal, run_at 2026-10-03T00:00:00Z.
- known_issue 'browser test tidak dapat dijalankan / tidak ada browser di sandbox' dihapus karena spec e2e-web kini sudah dijalankan; known_issue lain dipertahankan.
- Path e2e-web/tests/detail-engine-room.spec.ts ditambahkan ke test_files_generated (daftar tempat spec browser lama backend/tests/Browser/* tercatat / default; berkas backend/tests/Browser/* itu tidak ada di repo tetapi entri lamanya dibiarkan).

## v4 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/DetailEngineRoomTest.php` dihapus dari `test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (backend/app/Livewire/Data/DetailEngineRoom.php, backend/resources/views/livewire/data/detail-engine-room.blade.php, backend/app/Livewire/Data/Concerns/GuardsRecordIdShape.php, backend/app/Support/Display.php, backend/tests/Feature/Livewire/RecordIdShapeGuardTest.php).
- fe_files_generated += backend/app/Livewire/Data/Concerns/GuardsRecordIdShape.php, backend/app/Support/Display.php ← dipakai DetailEngineRoom.php (use GuardsRecordIdShape) dan blade (\App\Support\Display::...)
- fe_test_files_generated += backend/tests/Feature/Livewire/RecordIdShapeGuardTest.php ← file uji baru mencakup DetailEngineRoom (dataset 18 stasiun)
- implementation_notes += REVISI (2026-10-04, audit-fix) ← git diff DetailEngineRoom.php / detail-engine-room.blade.php; DetailEngineRoomTest asersi angka kini id-ID
- known_issues: tidak diubah ← tidak ada issue yang diperbaiki/ditambahkan oleh audit untuk layar ini

## v6 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (backend/app/Support/Concerns/ScopesToActorMill.php, backend/tests/Feature/AuditFix20261005Test.php, e2e-web/tests/audit-fix-20261005.spec.ts, backend/app/Support/{Display,ExportValue}.php, backend/app/Services/*RecordService.php, resources/views/livewire/data/detail-*.blade.php).
- test_files_generated / fe_test_files_generated ← + AuditFix20261005Test.php, audit-fix-20261005.spec.ts.
- implementation_notes ← +1 catatan Display::option().
- ⚠ Himpunan opsi Diesel Gen (run/standby/off) diambil dari komentar Display::OPTION_LABELS + form-engine-room.blade.php.
