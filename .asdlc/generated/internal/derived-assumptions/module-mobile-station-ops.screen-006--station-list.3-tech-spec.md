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

## v3 — 2026-08-19

- business_logic step 3: warna indikator draft dirender sebagai border/overlay DI ATAS foto (bukan diganti/dihilangkan) ← turunan teknis dari business_rules baru di business spec v3, tidak dinyatakan detail render-nya oleh user
- 3 unit_test_cases baru (render image, fallback null, fallback onerror) ditambahkan manual mengikuti pola test existing di file ini (test-spec-writer-agent adalah subagent, tidak dapat dipanggil dari fork) ← keterbatasan eksekusi, bukan keputusan desain
- 1 test_scenario baru "Foto Stasiun Ditampilkan" ditambahkan manual dengan pola sama ← idem
- implementation_notes: catatan eksplisit soal kebutuhan migration function untuk kolom `image` di tabel lokal `station` (bukan hanya CREATE TABLE IF NOT EXISTS) ← pelajaran langsung dari bug produksi migrateWeighbridgeTableToV5 yang ditemukan sebelumnya di sesi ini, diterapkan preventif di sini
- screen_dependencies ditambah screen-034--mills-setting (sumber pengelolaan station.image) ← konsekuensi logis dari fitur baru, tidak dinyatakan eksplisit user

## v4 — 2026-08-19 (koreksi user di checkpoint v3)

- business_logic step 1/3 dan implementation_notes diubah total: station.icon (override nama icon Lucide) menggantikan station.image (foto background) — styling tile (warna, shadow, radius, layout) tidak berubah sama sekali dari sebelum fitur ada ← koreksi eksplisit user
- 3 unit_test_cases diganti (bukan ditambah) dari versi image (render/fallback-null/fallback-onerror) menjadi versi icon (icon valid/fallback-null/fallback-invalid) ← turunan langsung dari koreksi
- test_scenario "Foto Stasiun Ditampilkan" diganti nama & isi menjadi "Ikon Stasiun Override" ← idem
- data_operations: kolom yang di-SELECT dari station berubah dari image menjadi icon ← idem

## v5 — 2026-08-19 (info dari coordinator, shared infra)

- implementation_notes: ditambahkan known-limitation eksplisit soal fetchAndCacheStationIconOverrides() mencocokkan berdasarkan (business_unit_id, type) bukan station id asli — aman untuk MVP (maks 1 station aktif per tipe per mill), berisiko silent-misapply jika ada >1 station aktif tipe sama di masa depan ← dinyatakan eksplisit oleh coordinator/user, bukan inferensi agent, dicatat verbatim sesuai instruksi
