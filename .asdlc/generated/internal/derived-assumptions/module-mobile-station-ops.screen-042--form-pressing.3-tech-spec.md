# Derived Assumptions Log — module-mobile-station-ops.screen-042--form-pressing.3-tech-spec

## v3 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Tidak menambah unit_test_cases: form tidak punya logika periode untuk diuji (simpan lokal tanpa cek periode).
- TEMUAN yang menyimpang dari brief: form ini memanggil writeThroughSync.syncAfterSave() setelah simpan lokal; bila Mills Setting immediate_sync_enabled aktif, record langsung di-POST ke server dan penolakan 422 PERIOD_CLOSED ditelan diam-diam. Didokumentasikan sebagai jalur tulis server, bukan sebagai 'tidak ada panggilan server'.
- Tidak menambah endpoint ke api_contracts (endpoints tetap kosong, sesuai instruksi) meskipun ada jalur POST write-through.
- Dikonfirmasi dari kode: form hanya membuka draft (draft_ongoing/draft_paused) — Data Preview mengarahkan record saved/synced ke layar preview, bukan form — sehingga tidak ada jalur edit/PATCH record tersinkron.
- Kalimat edge case/aturan/catatan dirumuskan sendiri; 'pemulihan = Admin membuka kembali baris stasiun di screen-142 lalu sinkron ulang' mengikuti brief.

## v4 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- Edge case usecase-141 ditulis ulang: penolakan 4xx write-through kini tampil lewat ConfirmDialog 'Tersimpan, tetapi ditolak server' lalu navigasi ke Monitor Pressing; offline/5xx tetap diam; record tetap 'saved'.
- implementation_notes[0] diganti: sinkron bukan lagi 'terpisah/tanpa API' — disebut jalur sinkron manual Station List (STATION_PUSH_CONFIGS) + write-through; Pause/Clear tidak memanggil server.
- Catatan REVISI kunci periode: frasa 'kegagalannya diam' diganti dengan perilaku dialog penolakan.

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (FormPressingView.vue, pressingRecordRepo.ts, utils/localDate.ts, millSettingRepo.ts, writeThroughSync.ts, syncService.ts, localSchema.ts, PressingRecordService.php).
- api_contracts[0].business_logic[1] = date diisi createDraft() todayLocalDateString() (lokal, bukan UTC) ← diff pressingRecordRepo.createDraft.
- business_logic[2] = label 'Catatan' #field-note ← diff FormPressingView.vue.
- edge_case_handling[10].handling += 422 tanggal > besok; sync_error/SyncFailureHint pada sinkron manual ← assertEventDateNotTooFarAhead('date'), syncService.failure()/markSynced.
- business_rules_applied[0] = tanggal lokal ← createDraft.
- unit_test_cases[2] = createDraft menyimpan tanggal lokal (01:30 WIB → hari ini) ← localDate.sqljs.spec.ts. (⚠ spec uji menguji threshing; untuk stasiun ini disimpulkan dari kode identik.)
- implementation_notes += REVISI 2026-10-04 (tanggal lokal, Catatan, write-through aktif berkat millSettingRepo SELECT immediate_sync_enabled, line per record, sync_error, validasi server) ← millSettingRepo diff, writeThroughSync.spec.ts, syncService.ts.
