# Derived Assumptions Log — module-mobile-station-ops.screen-006--station-list.4-implement

## v1 — 2026-08-17

- stationRepo.ts mengasumsikan 15 baris station (3 aktif + 12 placeholder) sudah ada di tabel lokal via sync sebelumnya, tidak disintesis di kode
- StationGrid.vue menghindari atribut disabled native (akan menekan tap) — pakai aria-disabled + styling agar tap tetap bisa memunculkan pesan "belum tersedia"
- Router pakai konvensi meta: { public: false } (bukan requiresAuth: true) mengikuti guard yang sudah ada
- Belum ada skema/migration lokal formal untuk tabel station SQLite — screen ini mengasumsikan tabel+baris sudah ada via sync, konsisten dengan asumsi localDb.ts

## v6 — 2026-08-18

- Draft-status detection REUSE 3 repo yang sudah ada (weighbridgeRecordRepo.getSummary, gradingRecordRepo/cagesTrackRecordRepo.getProgressSummary) alih-alih membuat service query baru ← draftRecordsRepo.ts generik sudah dihapus saat revisi Home (dead code, hanya dipakai fitur yang sudah dihapus) — keputusan sadar untuk tidak menghidupkannya lagi, cukup pakai fungsi summary per-stasiun yang sudah ada dan sudah teruji
- Setiap panggilan repo draft-status di-.catch(()=>null) independen (best-effort per repo, bukan all-or-nothing) ← bukan requirement eksplisit di tech-spec, ditambahkan agar satu repo gagal tidak memblokir 2 lainnya
- Header brand+hamburger di StationListView.vue adalah salinan persis pola HomeView.vue (nama variabel/class sama) ← demi konsistensi visual & perilaku lintas layar, bukan diminta secara literal "harus identik" oleh user
- Padding tile dinaikkan dari 10px 6px ke 22px 6px ← angka spesifik dipilih agen, user hanya minta "lebih besar" tanpa angka pasti

## v7 — 2026-08-19

- ICON_OVERRIDES di StationGrid.vue memakai persis 10 nama (gauge/layers/package/truck/scale/warehouse/factory/container/box/boxes) ← tech-spec v4/v5 hanya bilang "nama icon Lucide yang dikenali", tidak mendaftar nama pastinya; agen menemukan vocabulary sebenarnya dengan membaca `MillSettingService::SUPPORTED_ICONS` di backend agar konsisten dengan apa yang benar-benar bisa dipilih Admin di Mills Setting
- SVG path tiap icon override ditulis tangan (bukan dari library Lucide asli, karena package `lucide-vue-next` tidak terpasang di mobile) ← konsisten dengan pola 15 icon existing lain di file yang sama, sudah ada known_issue serupa sebelumnya
- Icon override HANYA berlaku untuk tile aktif, tidak pernah untuk tile disabled/placeholder ← translasi langsung dari business_logic step 3 tech-spec, bukan asumsi baru
- 1 test e2e di luar scope (form-cages-track.spec.ts) ditemukan gagal saat regresi penuh, dicatat sebagai known_issue tapi TIDAK diperbaiki (di luar directive screen-006 only) ← disiplin scope, bukan diabaikan begitu saja
