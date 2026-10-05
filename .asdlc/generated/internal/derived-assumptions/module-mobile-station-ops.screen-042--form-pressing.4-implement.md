# Derived Assumptions Log — module-mobile-station-ops.screen-042--form-pressing.4-implement

## v3 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Berkas test yang dikutip dipilih sendiri: backend/tests/Unit/Support/EnforcesPeriodLockTest.php, backend/tests/Feature/Api/KelolaPeriodePelaporanTest.php, mobile/tests/syncService.spec.ts, mobile/tests/writeThroughSync.spec.ts. Tidak ada test (mobile) yang memakai respons PERIOD_CLOSED secara spesifik; test mobile yang dikutip hanya mencakup galat generik.
- test_results tidak disentuh; tidak ada test dijalankan (sesuai brief).

## v4 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- Catatan REVISI tech-spec (kunci periode) tidak lagi menyebut penolakan write-through DIAM; merujuk ke catatan REVISI 2026-10-03.
- fe_files_generated ditambah mobile/src/services/writeThroughSync.ts dan mobile/src/components/ConfirmDialog.vue (berkas bersama yang diubah untuk perilaku ini).
- Catatan baru REVISI (2026-10-03): untuk layar selain Threshing dicatat tidak ada test per-form; cakupan generik writeThroughSync.spec.ts + pola identik FormThreshingView.spec.ts.

## v5 — 2026-10-03

Run penuh Playwright mobile 2026-10-03: 430 lulus, 0 gagal.
- test_results.browser = 9/0 (sebelumnya kosong).
- known_issue 'spec tidak dapat dijalankan di sandbox' dihapus — kini dijalankan nyata.
- Spec diperbaiki hari ini (drift spec, bukan cacat aplikasi; hanya mobile/tests/e2e yang berubah): tabel Target Operasional di CollapsibleSection tertutup default (disengaja 2026-08-25) — spec kini membukanya.

## v6 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (FormPressingView.vue, pressingRecordRepo.ts, utils/localDate.ts, millSettingRepo.ts, syncService.ts, tests baru di mobile/tests).
- fe_files_generated += pressingRecordRepo.ts, utils/localDate.ts, services/millSettingRepo.ts, services/syncService.ts ← createDraft memakai todayLocalDateString; write-through bergantung pada millSettingRepo; payload/line/sync_error di syncService. (⚠ pressingRecordRepo.ts sebelumnya tidak tercantum walau dipakai layar; millSettingRepo/syncService tidak diimpor view langsung.)
- fe_test_files_generated += noteLabelConsistency.spec.ts, DialogTeleport.spec.ts, writeThroughSync.spec.ts, millSettingRepo.sqljs.spec.ts, syncService.sqljs.spec.ts ← label Catatan, teleport dialog penolakan, write-through aktif, SELECT immediate_sync_enabled, date lokal/line/sync_error. (⚠ DialogTeleport/syncService.sqljs cakupan tidak langsung.)
- implementation_notes += REVISI 2026-10-04 ← diff terkait.

## v7 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit d5da9cf, e4f231e, ee5294c), code is truth (mobile/src/views/Form*View.vue, useBusyAction/BusyLabel/LoadingState, SearchableSelect.vue, App.vue).
- implementation_notes ← penjaga aksi ganda Simpan/Pause/Clear (actionInProgress), await router.push, BusyLabel, LoadingState variant form, dok mengambang; berkas uji bersama (loadingStates.screens.spec.ts, floating-safe-area.spec.ts) disebut di catatan, tidak didaftarkan.
- ⚠ 2-business-spec form TIDAK diubah: tidak ada teks spec yang menggambarkan label tombol sibuk/SearchableSelect, dan penjaga ketuk ganda dinilai pola UI bersama (didokumentasikan pemanggil di shared-decisions), bukan aturan bisnis per layar.
