# Derived Assumptions Log — module-mobile-station-ops.screen-007--monitor-weighbridge.3-tech-spec

## v1 — 2026-08-17

- Keputusan bahwa semua aksi (Mulai Input Baru/Pause/Clear/Lanjutkan) adalah operasi lokal SQLite (INSERT/UPDATE/DELETE), tanpa endpoint API ← disimpulkan agent, konsisten dengan pola offline-first
- Ringkasan Arrival/Dispatched dihitung dari agregasi lokal (Sum WB Card, Sum Net Weight, Sum Quantity) ← translasi teknis dari business spec information_displayed
- screen_dependencies ke screen-006, screen-010, screen-013 ← disimpulkan dari alur navigasi usecase

## v2 — 2026-08-18

- Query list draft butuh fungsi repo BARU (getDrafts/findByUserAndStatus, tanpa LIMIT 1) ← getSummary().currentDraft lama cuma ambil 1 draft terbaru, tidak cukup untuk list multi-draft
- Tap list item TIDAK memicu UPDATE status (tidak lagi resumeDraft()) ← konsekuensi langsung dari business_rule "ongoing dan paused digabung", status internal tetap ada di DB tapi tidak perlu ditransisi lagi saat tap
- data_operations UPDATE (Pause) dan DELETE (Clear) DIHAPUS dari screen ini ← kedua aksi pindah total ke usecase-010 (Form Weighbridge)
- Load Data navigasi ke Data Preview mode list (tanpa id) ← scope screen-013 diperluas dari single-record read-only jadi list+filter, akan didetailkan saat merevisi screen-013
- test scenarios derived: 11 unit (level repo/computed), 0 API, 6 component, 6 browser ← delegated test-spec-writer-agent dari Phase 2 bdd_scenarios (6 skenario baru)

## v3 — 2026-08-18 (inline correction saat checkpoint)

- business_logic step 1 (SELECT ringkasan agregat) dan data_operations terkait DIHAPUS, unit_test_cases turun dari 11 ke 10 (2 test summary lama dilebur/dihapus, tetap 10 karena 1 test summary sebenarnya sudah gabung ke test lain) ← mengikuti penghapusan information_displayed di business spec v3, instruksi eksplisit user saat checkpoint

## v4 — 2026-08-18

- Query counter butuh fungsi repo BARU getTodaySummary(userId) ← tidak ada fungsi existing yang agregat khusus "hari ini"; ringkasan lama (dihapus di v3) agregat semua waktu, scope berbeda
- Counter filter pakai date(arrival_datetime) = date('now','localtime') di SQLite ← "hari ini" berarti tanggal lokal device, bukan UTC
- Ditulis langsung tanpa delegasi test-spec-writer-agent (perubahan aditif kecil) — 3 unit test baru + 2 test_scenario baru ditambahkan manual mengikuti pola yang sudah ada
