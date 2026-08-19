# Derived Assumptions Log — module-mobile-station-ops.screen-008--monitor-grading.4-implement

## v1 — 2026-08-17

- getProgressSummary() menghitung semua grading_record milik user lokal terlepas dari status ("jumlah record dinilai pada sesi berjalan") karena grading_record tidak punya field weight/quantity sendiri
- createDraft(userId) hanya insert status=draft_ongoing + created_by, field lain (grading_number, vehicle_number, dst) dibiarkan null untuk diisi Form Grading (screen-011)
- deleteDraft() cascade di level aplikasi (2 DELETE berurutan: grading_detail lalu grading_record), bukan FK cascade level DB — konsisten dengan localSchema.ts
