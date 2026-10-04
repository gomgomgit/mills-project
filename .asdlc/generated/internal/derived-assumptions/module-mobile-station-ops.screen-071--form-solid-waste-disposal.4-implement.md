# Derived Assumptions Log — module-mobile-station-ops.screen-071--form-solid-waste-disposal.4-implement

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Berkas test yang dikutip dipilih sendiri: backend/tests/Unit/Support/EnforcesPeriodLockTest.php, backend/tests/Feature/Api/KelolaPeriodePelaporanTest.php, mobile/tests/syncService.spec.ts, mobile/tests/writeThroughSync.spec.ts. Tidak ada test (mobile) yang memakai respons PERIOD_CLOSED secara spesifik; test mobile yang dikutip hanya mencakup galat generik.
- test_results tidak disentuh; tidak ada test dijalankan (sesuai brief).

## v3 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- Catatan REVISI tech-spec (kunci periode) tidak lagi menyebut penolakan write-through DIAM; merujuk ke catatan REVISI 2026-10-03.
- fe_files_generated ditambah mobile/src/services/writeThroughSync.ts dan mobile/src/components/ConfirmDialog.vue (berkas bersama yang diubah untuk perilaku ini).
- Catatan baru REVISI (2026-10-03): untuk layar selain Threshing dicatat tidak ada test per-form; cakupan generik writeThroughSync.spec.ts + pola identik FormThreshingView.spec.ts.

## v4 — 2026-10-03

Spec e2e mobile baru + run penuh 727 lulus / 0 gagal.
- mobile/tests/e2e/form-solid-waste-disposal.spec.ts ditambahkan ke fe_test_files_generated; test_results.browser = 15 lulus / 0 gagal (run_at 2026-10-03T00:00:00Z, jumlah dari run penuh suite).
- known_issue 'spec browser/E2E belum ada' dihapus; known_issue lain dibiarkan.
- Cacat tanggal UTC di mobile/src/services/solidWasteDisposalRecordRepo.ts (todayDateString pakai toISOString → draft 00:00–06:59 WIB bertanggal kemarin) diperbaiki ke tanggal lokal; regresi: vitest 'mengisi tanggal draft dengan tanggal lokal, bukan UTC (dini hari WIB)' di mobile/tests/solidWasteDisposalRecordRepo.spec.ts + e2e 'New Data dini hari ...' di monitor-solid-waste-disposal.spec.ts (dari fixme ke lulus). File repo sudah tercantum di fe_files_generated.

## v5 — 2026-10-04

Penyeragaman checkbox verifikasi (keputusan user 2026-10-04).
- implementation_notes: ditambah satu catatan REVISI 2026-10-04 — checkbox header Checked By/Acknowledged By kini hanya dirender untuk peran yang berhak (v-if="isSupervisor" / v-if="isMillManagement"), bukan tampil-disabled untuk semua peran.
- Test vitest dan e2e form ini sudah disesuaikan (toBeDisabled → toHaveCount(0); tes baru untuk mill_management); run gabungan 4 form 60/60 e2e, 57/57 vitest lulus.
- test_results sengaja tidak diubah (jumlah spec e2e tetap).
