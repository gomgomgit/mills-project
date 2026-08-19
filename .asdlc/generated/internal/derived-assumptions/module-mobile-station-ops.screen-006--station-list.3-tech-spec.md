# Derived Assumptions Log — module-mobile-station-ops.screen-006--station-list.3-tech-spec

## v1 — 2026-08-17

- Keputusan bahwa screen ini tidak memiliki endpoint API (baca dari cache lokal station master data yang disinkron sebelumnya) ← disimpulkan agent, konsisten dengan pola screen-005
- Logika render grid berdasarkan `station.is_active` ← translasi teknis dari usecase, field is_active berasal dari entity-catalog
- screen_dependencies ke screen-005 dan 3 Monitor screen ← disimpulkan dari alur navigasi usecase

## v2 — 2026-08-18

- data_operations ditambah 3 query lokal (weighbridge/grading/cages-track-record, filtered by created_by+status) ← memindahkan logika query draft yang dulu ada di Home v1 sebelum dihapus, sekarang jadi milik screen ini untuk mendukung indikator warna
- edge_case_handling: multi-draft (ongoing+paused) tetap merah, tanpa draft = hitam/netral ← turunan langsung dari business_rules baru
- unit_test_cases mencakup logika hasDraft/color computation per stasiun ← delegated ke test-spec-writer-agent
- test scenarios derived: 12 unit (level repo/computed-property, tidak ada backend), 0 API, 6 component, 6 browser ← delegated ke test-spec-writer-agent dari Phase 2 bdd_scenarios (6 skenario), tidak ditanyakan ke user (autopilot)
