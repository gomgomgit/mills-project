# Derived Assumptions Log — module-web-station-data.screen-022--form-weighbridge-web.4-implement

## v3 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Isi catatan REVISI (urutan pemanggilan guard, file test yang dikutip) diturunkan dari pembacaan kode service/Livewire/test saat ini, bukan dari laporan run test; test_results sengaja tidak disentuh dan suite tidak dijalankan.
- Tanggal kejadian Weighbridge = bagian tanggal dari record_datetime (trait menormalkan ke Y-m-d); contoh batas inklusif memakai jam 08:00/17:00.

## v4 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- Catatan lama 'Station diresolve dari business_unit_id' dibiarkan (riwayat) dan dikoreksi lewat catatan REVISI baru, bukan diganti.
- Dicatat bahwa pesan default NoActiveWeighbridgeStationException dan docblock FORM_FIELDS masih menyebut Business Unit — komentar/pesan kode usang, tidak diubah.

## v5 — 2026-10-03

Sinkronisasi catatan uji 4-implement dengan uji yang ditambahkan 2026-10-03.
- test_results.integration.passed = 16, dihitung dari jumlah `it(` di backend/tests/Feature/Api/FormWeighbridgeTest.php (failed 0, run_at 2026-10-03T00:00:00Z); unit/component/browser tidak diubah karena tidak dijalankan ulang untuk sinkronisasi ini.
- Tidak ada known_issue yang secara eksplisit menyatakan penolakan kunci periode per-stasiun belum diuji, jadi known_issues dibiarkan; catatan REVISI kunci periode sebelumnya ("test per-layar membuka periode Terbuka sebagai prasyarat") dilengkapi oleh catatan REVISI baru, bukan diubah.
- backend/tests/Feature/Api/FormWeighbridgeTest.php sudah ada di test_files_generated, tidak ditambahkan ulang.

## v6 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser diisi 7 lulus/0 gagal dari e2e-full.log.counts.json (spec form-weighbridge); run_at 2026-10-03T00:00:00Z.
- known_issue 'Browser test dibuat tapi tidak dijalankan — tidak ada browser di sandbox' dihapus karena spec kini dijalankan; known_issue lain dipertahankan.
- e2e-web/tests/form-weighbridge.spec.ts ditambahkan ke test_files_generated (berdampingan dengan entri uji browser lama); entri backend/tests/Browser/* yang sudah tidak ada dibiarkan, di luar cakupan.

## v7 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/FormWeighbridgeTest.php` dihapus dari `test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).

## v8 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git diff HEAD backend/app/Livewire/Data/FormWeighbridge.php, WeighbridgeRecordService.php, form-weighbridge.blade.php, untracked AppTime.php / GuardsRecordIdShape.php / tests).
- files_generated += AppTime.php, EnforcesPeriodLock.php, ScopesToActorMill.php, ApiExceptionHandler.php, config/app.php ← dipakai jalur create/update layar ini (⚠ shared support files, dicantumkan karena perilaku layar berubah lewatnya).
- fe_files_generated += GuardsRecordIdShape.php, Display.php ← dipakai FormWeighbridge / blade.
- test_files_generated += AuditFix20261004Test.php, RecordIdShapeGuardTest.php, WebFormNoDisabledFieldTest.php ← menguji FormWeighbridge / WeighbridgeRecordService.
- implementation_notes[4] = Net Weight teks read-only (bukan disabled) ← blade.
- implementation_notes += REVISI audit-fix 2026-10-04.
