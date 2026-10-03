# Derived Assumptions Log — module-mobile-station-ops.screen-071--form-solid-waste-disposal.3-tech-spec

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Tidak menambah unit_test_cases: form tidak punya logika periode untuk diuji (simpan lokal tanpa cek periode).
- TEMUAN yang menyimpang dari brief: form ini memanggil writeThroughSync.syncAfterSave() setelah simpan lokal; bila Mills Setting immediate_sync_enabled aktif, record langsung di-POST ke server dan penolakan 422 PERIOD_CLOSED ditelan diam-diam. Didokumentasikan sebagai jalur tulis server, bukan sebagai 'tidak ada panggilan server'.
- Tidak menambah endpoint ke api_contracts (endpoints tetap kosong, sesuai instruksi) meskipun ada jalur POST write-through.
- Dikonfirmasi dari kode: form hanya membuka draft (draft_ongoing/draft_paused) — Data Preview mengarahkan record saved/synced ke layar preview, bukan form — sehingga tidak ada jalur edit/PATCH record tersinkron.
- Kalimat edge case/aturan/catatan dirumuskan sendiri; 'pemulihan = Admin membuka kembali baris stasiun di screen-142 lalu sinkron ulang' mengikuti brief.

## v3 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- Edge case usecase-141 ditulis ulang: penolakan 4xx write-through kini tampil lewat ConfirmDialog 'Tersimpan, tetapi ditolak server' lalu navigasi ke Monitor Solid Waste Disposal; offline/5xx tetap diam; record tetap 'saved'.
- implementation_notes[0] diganti: sinkron bukan lagi 'terpisah/tanpa API' — disebut jalur sinkron manual Station List (STATION_PUSH_CONFIGS) + write-through; Pause/Clear tidak memanggil server.
- Catatan REVISI kunci periode: frasa 'kegagalannya diam' diganti dengan perilaku dialog penolakan.
