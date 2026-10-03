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
