# Derived Assumptions Log — module-web-station-data.screen-023--form-grading-web.4-implement

## v1 — 2026-08-20

- **`create()` writes the record as `status=Synced` first, upserts details, then flips to `status=saved`** — not in the tech spec's business_logic literally; discovered as a technical necessity mid-implementation (`GradingRecord::booted()`'s `saving` guard rejects a brand-new `status=saved` record with zero details). Final observable behavior (API/UI response, DB end-state) is unaffected — the record ends at `status=saved` either way.
- **`grading_records.vehicle_code` migration + `doctrine/dbal` composer dependency** — not anticipated by the tech spec at all; a real, confirmed schema gap found while writing tests (a prior session's "make vehicle_code optional" change never touched the DB). Fixed rather than worked around, since the alternative (always sending a placeholder value) would misrepresent optionality to the user.

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Isi catatan REVISI (urutan pemanggilan guard, file test yang dikutip) diturunkan dari pembacaan kode service/Livewire/test saat ini, bukan dari laporan run test; test_results sengaja tidak disentuh dan suite tidak dijalankan.

## v3 — 2026-10-03

Sinkronisasi catatan uji 4-implement dengan uji yang ditambahkan 2026-10-03.
- test_results.integration.passed = 15, dihitung dari jumlah `it(` di backend/tests/Feature/Api/FormGradingTest.php (failed 0, run_at 2026-10-03T00:00:00Z); unit/component/browser tidak diubah karena tidak dijalankan ulang untuk sinkronisasi ini.
- Tidak ada known_issue yang secara eksplisit menyatakan penolakan kunci periode per-stasiun belum diuji, jadi known_issues dibiarkan; catatan REVISI kunci periode sebelumnya ("test per-layar membuka periode Terbuka sebagai prasyarat") dilengkapi oleh catatan REVISI baru, bukan diubah.
- backend/tests/Feature/Api/FormGradingTest.php sudah ada di test_files_generated, tidak ditambahkan ulang.
- Perbaikan spec e2e-web/tests/form-grading.spec.ts (race selectLive) dicatat hanya di catatan implementasi; test_results.browser tidak diubah karena jumlah uji spec itu tidak termasuk cakupan sinkronisasi ini.

## v4 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser diisi 8 lulus/0 gagal dari e2e-full.log.counts.json (spec form-grading); run_at 2026-10-03T00:00:00Z.
- known_issue 'Browser test dibuat tapi tidak dijalankan — tidak ada browser di sandbox' dihapus karena spec kini dijalankan; known_issue lain dipertahankan.
- e2e-web/tests/form-grading.spec.ts ditambahkan ke test_files_generated (berdampingan dengan entri uji browser lama); entri backend/tests/Browser/* yang sudah tidak ada dibiarkan, di luar cakupan.

## v5 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/FormGradingTest.php` dihapus dari `fe_test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).

## v6 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git diff HEAD GradingRecordService.php, FormGrading.php, form-grading.blade.php; untracked AppTime.php, GuardsRecordIdShape.php, tests).
- files_generated += AppTime.php, EnforcesPeriodLock.php, ScopesToActorMill.php, ApiExceptionHandler.php, config/app.php (⚠ shared support files, dicantumkan karena perilaku layar berubah lewatnya).
- fe_files_generated += GuardsRecordIdShape.php.
- test_files_generated += tests/Feature/AuditFix20261004Test.php; fe_test_files_generated += WebFormNoDisabledFieldTest.php, RecordIdShapeGuardTest.php.
- implementation_notes[5] = UOM/Percentage teks; += REVISI audit-fix.
