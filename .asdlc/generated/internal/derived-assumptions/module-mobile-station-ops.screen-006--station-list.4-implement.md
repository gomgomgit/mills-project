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
