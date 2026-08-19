# Derived Assumptions Log — module-mobile-station-ops.screen-005--home.3-tech-spec

## v1 — 2026-08-17

- Keputusan bahwa screen ini tidak memiliki endpoint API sama sekali (murni local SQLite read, offline-first) ← disimpulkan agent dari sifat data draft, tidak dinyatakan eksplisit sebagai "tanpa API" di business spec
- Logika grouping/badge/sorting/pagination lokal (5 langkah business_logic) ← translasi teknis dari usecase main_flow
- Query filter `created_by = current user` untuk drafts ← disimpulkan agent, bukan pernyataan eksplisit di business spec
- screen_dependencies ke screen-006 dan 3 form screen (010/011/012) ← disimpulkan dari alur navigasi usecase

## v2 — 2026-08-18

- api_contracts.endpoints = [] dan data_operations = [] (tidak ada API maupun SQLite lokal sama sekali) ← Home sekarang murni statis + navigasi, tidak ada state yang dibaca dari mana pun selain nama user (sudah ada di Pinia auth store dari alur login)
- edge_case_handling: teks toast/info "Segera Hadir" untuk tap menu placeholder ← detail konten pesan, tidak dinyatakan eksplisit oleh user
- screen_dependencies: dependensi ke screen-010/011/012 (lanjutkan draft paused) DIHAPUS ← fitur lanjutkan draft paused sudah tidak ada di Home; hanya screen-006 (Station List) yang tersisa sebagai dependency
- implementation_notes: hero image adalah aset statis dibundle di aplikasi, bukan diserve dari API ← konsekuensi teknis dari keputusan bisnis "boleh cari di Unsplash" (aset gambar, bukan endpoint)
- test scenarios derived: 0 unit-service (tidak ada backend), 7 unit test (level komponen/frontend), 0 API, 4 component, 4 browser ← delegated ke test-spec-writer-agent dari Phase 2 bdd_scenarios (yang sudah diganti total di v2 usecase-005), tidak ditanyakan ke user (autopilot)
