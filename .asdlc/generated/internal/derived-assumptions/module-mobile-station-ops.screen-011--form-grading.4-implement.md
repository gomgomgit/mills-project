# Derived Assumptions Log — module-mobile-station-ops.screen-011--form-grading.4-implement

## v1 — 2026-08-18

- saveDraft() melakukan UPDATE header lalu INSERT/UPDATE detail secara sekuensial (bukan atomik) — tidak ada transaction primitive di localDb.ts, mengikuti pola weighbridgeRecordRepo.ts
- Field header wajib (grading_number, date, vehicle_number, driver_name, estate_supplier) disimpulkan dari pola weighbridge form karena tech-spec excerpt tidak mengenumerasi field wajib secara eksplisit — perlu dikonfirmasi ulang ke tech-spec lengkap
- GradingDetailGrid.vue category field free-text, tidak ada daftar kategori enumerasi di entity-catalog/tech-spec — mungkin perlu jadi dropdown jika ada daftar kategori baku
- Validasi "checked_by hanya supervisor" dan "minimal 1 baris detail" ditegakkan di 2 lapis: repo (defense in depth) dan view (UX cepat)
