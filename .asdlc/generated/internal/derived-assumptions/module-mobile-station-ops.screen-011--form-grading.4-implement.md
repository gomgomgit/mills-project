# Derived Assumptions Log — module-mobile-station-ops.screen-011--form-grading.4-implement

## v1 — 2026-08-18

- saveDraft() melakukan UPDATE header lalu INSERT/UPDATE detail secara sekuensial (bukan atomik) — tidak ada transaction primitive di localDb.ts, mengikuti pola weighbridgeRecordRepo.ts
- Field header wajib (grading_number, date, vehicle_number, driver_name, estate_supplier) disimpulkan dari pola weighbridge form karena tech-spec excerpt tidak mengenumerasi field wajib secara eksplisit — perlu dikonfirmasi ulang ke tech-spec lengkap
- GradingDetailGrid.vue category field free-text, tidak ada daftar kategori enumerasi di entity-catalog/tech-spec — mungkin perlu jadi dropdown jika ada daftar kategori baku
- Validasi "checked_by hanya supervisor" dan "minimal 1 baris detail" ditegakkan di 2 lapis: repo (defense in depth) dan view (UX cepat)

## v5 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Berkas test yang dikutip dipilih sendiri: backend/tests/Unit/Support/EnforcesPeriodLockTest.php, backend/tests/Feature/Api/KelolaPeriodePelaporanTest.php, mobile/tests/syncService.spec.ts. Tidak ada test (mobile) yang memakai respons PERIOD_CLOSED secara spesifik; test mobile yang dikutip hanya mencakup galat generik.
- test_results tidak disentuh; tidak ada test dijalankan (sesuai brief).

## v6 — 2026-10-03

Run penuh Playwright mobile 2026-10-03: 430 lulus, 0 gagal.
- test_results.browser = 10/0 (run_at diperbarui); spec tidak diubah.

## v7 — 2026-10-03

Pembersihan rujukan komponen grid yang dihapus.
- known_issue dead-code GradingDetailGrid.vue dihapus: komponen itu sudah dihapus di commit 004aacd (tak diimpor, tipe pre-v2 menggagalkan vue-tsc --noEmit / npm run build).
- Catatan historis v3 tentang GradingDetailGrid.vue dibiarkan; ditambah satu catatan REVISI merujuk 004aacd.

## v8 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (FormGradingView.vue, gradingRecordRepo.ts, gradingParameterSync.ts, syncService.ts, localSchema.ts, stores/auth.ts, GradingParameter{Controller,Service}.php, tests baru).
- files_generated += GradingParameterController.php, GradingParameterService.php ← endpoint baru yang dipakai layar ini (via gradingParameterSync).
- test_files_generated += backend/tests/Feature/Api/MobileReadEndpointsTest.php ← blok "GET /api/grading-parameters".
- fe_files_generated += gradingParameterSync.ts, syncService.ts, localSchema.ts ← pemetaan id parameter, pushGradingRow, seed guard. (⚠ syncService/localSchema dimasukkan karena perilaku layar yang direvisi berada di sana, bukan diimpor view.)
- fe_test_files_generated += syncService.sqljs.spec.ts, syncService.spec.ts, auth.store.spec.ts, noteLabelConsistency.spec.ts, DialogTeleport.spec.ts ← menguji id palsu/dropdown WB, fetch master saat login, label Catatan, ConfirmDialog teleport. (⚠ DialogTeleport cakupan tidak langsung.)
- implementation_notes[0] = asumsi lama "SEMUA record weighbridge apa pun status" diganti filter saved/synced ← getWeighbridgeRecordOptions.
- implementation_notes += REVISI 2026-10-04 ← diff terkait.

## v9 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit d5da9cf, e4f231e, ee5294c), code is truth (mobile/src/views/Form*View.vue, useBusyAction/BusyLabel/LoadingState, SearchableSelect.vue, App.vue).
- implementation_notes ← penjaga aksi ganda Simpan/Pause/Clear (actionInProgress), await router.push, BusyLabel, LoadingState variant form, dok mengambang; berkas uji bersama (loadingStates.screens.spec.ts, floating-safe-area.spec.ts) disebut di catatan, tidak didaftarkan.
- ⚠ 2-business-spec form TIDAK diubah: tidak ada teks spec yang menggambarkan label tombol sibuk/SearchableSelect, dan penjaga ketuk ganda dinilai pola UI bersama (didokumentasikan pemanggil di shared-decisions), bukan aturan bisnis per layar.
