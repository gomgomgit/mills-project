# Derived Assumptions Log — module-mobile-station-ops.screen-008--monitor-grading.3-tech-spec

## v1 — 2026-08-17

- Keputusan bahwa semua aksi adalah operasi lokal SQLite (INSERT/UPDATE/DELETE), tanpa endpoint API ← disimpulkan agent, konsisten dengan pola screen-007
- DELETE grading-record cascading ke grading-detail ← disimpulkan dari relationship one-to-many di entity-catalog
- screen_dependencies ke screen-006, screen-011, screen-014 ← disimpulkan dari alur navigasi usecase
- Implementation note: metrik progress spesifik Grading masih open question di business spec ← dicatat sebagai catatan implementasi, bukan keputusan final

## v2 — 2026-08-18

- Business logic dan test_scenarios ditulis ulang total mengikuti pola screen-007--monitor-weighbridge v4 (counter 3-card + list draft/pause + New Data/Load Data) ← mirroring struktural dari instruksi user "hal yang sama seperti weighbridge", diterjemahkan 1:1 ke entitas grading-record
- Counter dihitung dari filter date(date) = hari ini (bukan arrival_datetime seperti Weighbridge) ← field timestamp Grading bernama `date`, bukan `arrival_datetime`, sesuai entity-catalog v2
- Fungsi repo baru diasumsikan: getTodaySummary(userId), getDrafts(userId) pada gradingRecordRepo.ts, mengikuti pola nama yang sama persis dengan weighbridgeRecordRepo.ts ← belum ada di kode, akan dibuat saat implementasi
