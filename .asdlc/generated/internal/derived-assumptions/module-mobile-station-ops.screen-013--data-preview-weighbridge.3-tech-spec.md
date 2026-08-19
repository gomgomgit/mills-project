# Derived Assumptions Log — module-mobile-station-ops.screen-013--data-preview-weighbridge.3-tech-spec

## v1 — 2026-08-17

- Keputusan bahwa screen ini tidak memiliki endpoint API (baca lokal SQLite by id) ← disimpulkan agent, konsisten dengan pola screen sebelumnya

## v2 — 2026-08-18

- Mode list & detail berbagi 1 komponen/route (dibedakan via ada/tidaknya :id param), bukan 2 screen terpisah ← desain agent, hemat duplikasi, konsisten dengan konvensi route :id? yang sudah ada sejak v1
- Filter tanggal & search dilakukan client-side terhadap hasil findAll() (bukan query ulang per keystroke) ← keputusan performa, volume data per user kecil
- Back di mode detail → list (bukan Monitor); Back di mode list → Monitor ← 2 perilaku Back berbeda tergantung mode, instruksi eksplisit user
- test scenarios: 18 unit (level repo/computed), 0 API, 9 component, 9 browser ← delegated test-spec-writer-agent dari Phase 2 bdd_scenarios (9 skenario)

## v3 — 2026-08-18

- dateFilter default = tanggal lokal hari ini, diisi SEKALI saat mount di mode list ← Back dari detail TIDAK reset filter yang sudah diubah user kembali ke hari ini (hanya reset saat komponen benar-benar di-mount ulang, bukan setiap kali balik ke mode list dalam instance yang sama)
- 3 unit test baru (init default, filter dgn default, ubah/kosongkan default) ditambahkan ke 18 yang sudah ada = 21 total
- 1 test_scenario baru "Filter Tanggal Default Hari Ini" ditambahkan ke 9 yang sudah ada = 10 total ← awalnya salah GANTI skenario "Filter Diterapkan" yang sudah ada, dikoreksi sebelum lanjut ke checkpoint
