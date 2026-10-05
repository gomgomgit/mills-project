# Derived Assumptions Log — module-mobile-station-ops.screen-044--form-kernel-plant.4-implement

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
- test_results.browser = 10/0 (sebelumnya kosong).
- known_issue 'spec tidak dapat dijalankan di sandbox' dihapus — kini dijalankan nyata.
- Spec diperbaiki hari ini (drift spec, bukan cacat aplikasi; hanya mobile/tests/e2e yang berubah): tabel Target Operasional di CollapsibleSection tertutup default (disengaja 2026-08-25) — spec kini membukanya.

## v6 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git status/diff mobile/, tests baru di mobile/tests/).
- fe_files_generated (+) = mobile/src/utils/localDate.ts, mobile/src/services/millSettingRepo.ts, mobile/src/services/syncService.ts ← dipakai jalur draft-date / write-through layar ini (⚠ inferensi: atribusi berkas bersama ke layar ini, bukan berkas milik layar)
- fe_test_files_generated (+) = noteLabelConsistency, DialogTeleport, localDate.sqljs, writeThroughSync, millSettingRepo.sqljs, syncService.sqljs (*.spec.ts) ← tes baru/berubah yang mencakup label Catatan, ConfirmDialog, createDraft lokal & normalisasi UTC, write-through, sync_error/line per record (⚠ inferensi: cakupan generik lintas stasiun, bukan tes khusus layar)
- implementation_notes (append) = REVISI 2026-10-04 audit-fix (label Catatan, date lokal, write-through aktif, sync_error, line per record, ConfirmDialog teleport) ← diff kode terkait

## v7 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit d5da9cf, e4f231e, ee5294c), code is truth (mobile/src/views/Form*View.vue, useBusyAction/BusyLabel/LoadingState, SearchableSelect.vue, App.vue).
- implementation_notes ← penjaga aksi ganda Simpan/Pause/Clear (actionInProgress), await router.push, BusyLabel, LoadingState variant form, dok mengambang; berkas uji bersama (loadingStates.screens.spec.ts, floating-safe-area.spec.ts) disebut di catatan, tidak didaftarkan.
- ⚠ 2-business-spec form TIDAK diubah: tidak ada teks spec yang menggambarkan label tombol sibuk/SearchableSelect, dan penjaga ketuk ganda dinilai pola UI bersama (didokumentasikan pemanggil di shared-decisions), bukan aturan bisnis per layar.
