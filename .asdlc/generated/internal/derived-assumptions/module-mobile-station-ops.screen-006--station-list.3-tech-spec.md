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

## v10 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Perilaku per-record (bukan all-or-nothing) dikonfirmasi dari kode syncService.ts (try/catch per baris di pushUniformRow dan sync{Weighbridge,Grading,CagesTrack}Records), bukan dari brief.
- Menambahkan sendiri catatan kaskade Grading: Weighbridge yang tertolak membuat Grading gagal dengan alasan 'Weighbridge terkait belum tersinkron', bukan pesan periode.
- Menyebut write-through (immediate_sync_enabled) sebagai jalur kedua yang penolakannya diam sehingga alasan hanya terlihat di layar ini — temuan kode, tidak ada di brief.
- Menyatakan 'grid stasiun tidak menampilkan indikator record saved yang tertahan' sebagai konsekuensi terbuka (diverifikasi: StationListView/StationGrid tidak menghitung record 'saved').
- Tidak menambah unit_test_cases: tidak ada logika baru di layar ini; penanganan galat generik sudah teruji.

## v11 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/services/syncService.ts, gradingParameterSync.ts, localSchema.ts, millSettingRepo.ts, components/SyncResultDialog.vue, StationGrid.vue, App.vue, utils/floatingSafeArea.ts, utils/localDate.ts; backend GradingParameterController/Service, routes/api.php).
- api_contracts[0].endpoints += GET /api/grading-parameters (auth:web,sanctum, 4 peran; data [{id,name,uom,sort_order}]) ← routes/api.php + GradingParameterService::listForMobile(); dipanggil syncAllRecords bila ada Grading saved.
- api_contracts[0].business_logic += step 9 Sinkronisasi (line per record, fallback line terpilih, tanpa throw; grading param resolve; date lokal/offset; sync_error tulis/kosongkan; SyncResultDialog teleport + stationName) ← syncAllRecords()/syncTable()/resolveRecordContext()/failure()/markSynced().
- api_contracts[0].data_operations += station SELECT production_line_id,name; record UPDATE status/server_id/sync_error; grading-parameter UPSERT. ⚠ entity_id 'grading-parameter' dan operation UPSERT diasumsikan (cache lokal diisi fetchAndCacheGradingParameters, isi persis tidak dibaca).
- api_contracts[0].edge_case_handling[4].handling = nama fungsi baru (syncTable/push*Row) + sync_error → hint Data Preview ← syncService.ts.
- api_contracts[0].edge_case_handling += line beda dari terpilih; tanpa line (NO_LINE_REASON); grading id palsu tak terpetakan; offline tidak menulis sync_error ← syncService.ts, gradingParameterSync.resolveServerGradingParameterIds().
- api_contracts[0].business_rules_applied += sinkron ke line stasiun tempat record dibuat ← resolveRecordContext().
- api_contracts[0].unit_test_cases += 4 ← syncService.sqljs.spec.ts (#3/#5/#9), syncService.spec.ts.
- implementation_notes += REVISI 2026-10-04 (koreksi catatan 2026-10-03: write-through kini benar aktif via millSettingRepo SELECT fix; penolakan ditulis sync_error; dialog teleport; floating safe area; label 'Stasiun Aktif').
- test_scenarios += 'Pilih Stasiun — Sinkronisasi per Record'. ⚠ diturunkan dari e2e sync-and-verification.spec.ts, bukan dari bdd_scenarios Phase 2.

## v12 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (mobile/src/services/apiClient.ts, mobile/src/services/syncService.ts).
- implementation_notes ← append: 401 saat sinkron ditangani interceptor apiClient; supervisorOnlyDetailColumns Sterilizer.
- test_scenarios ← append 'Pilih Stasiun — Sinkronisasi Ditolak karena Sesi Tidak Berlaku'.
- ⚠ 401 susulan dalam batch yang sama (request tanpa token setelah expireSession) diasumsikan tetap ditangkap per record oleh syncService dan tidak memicu handler lagi — disimpulkan dari kode, tidak diuji khusus di layar ini.
