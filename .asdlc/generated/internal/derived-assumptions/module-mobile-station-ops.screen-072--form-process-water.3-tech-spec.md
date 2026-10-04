# Derived Assumptions Log — module-mobile-station-ops.screen-072--form-process-water.3-tech-spec

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Tidak menambah unit_test_cases: form tidak punya logika periode untuk diuji (simpan lokal tanpa cek periode).
- TEMUAN yang menyimpang dari brief: form ini memanggil writeThroughSync.syncAfterSave() setelah simpan lokal; bila Mills Setting immediate_sync_enabled aktif, record langsung di-POST ke server dan penolakan 422 PERIOD_CLOSED ditelan diam-diam. Didokumentasikan sebagai jalur tulis server, bukan sebagai 'tidak ada panggilan server'.
- Tidak menambah endpoint ke api_contracts (endpoints tetap kosong, sesuai instruksi) meskipun ada jalur POST write-through.
- Dikonfirmasi dari kode: form hanya membuka draft (draft_ongoing/draft_paused) — Data Preview mengarahkan record saved/synced ke layar preview, bukan form — sehingga tidak ada jalur edit/PATCH record tersinkron.
- Kalimat edge case/aturan/catatan dirumuskan sendiri; 'pemulihan = Admin membuka kembali baris stasiun di screen-142 lalu sinkron ulang' mengikuti brief.

## v3 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- edge_case usecase-141: penolakan write-through 4xx kini ditampilkan lewat ConfirmDialog 'Tersimpan, tetapi ditolak server' lalu navigasi ke Monitor; offline/5xx tetap diam (redaksi dipilih agen, diverifikasi terhadap writeThroughSync.ts + Form*View.vue).
- implementation_notes[0]: 'sync manual terpisah' diganti — record 'saved' dikirim lewat sinkron manual Station List (STATION_PUSH_CONFIGS) dan write-through syncAfterSave() bila immediate_sync_enabled aktif.
- Catatan REVISI kunci periode: frasa 'kegagalannya diam' diganti dengan pembedaan offline/5xx (diam) vs 4xx (dialog).
- Klaim 'syncService.ts TIDAK diperluas … (deferred)' dinyatakan usang di implementation_notes[0].

## v4 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/services/processWaterRecordRepo.ts, mobile/src/views/FormProcessWaterView.vue, mobile/src/services/syncService.ts, mobile/src/services/millSettingRepo.ts, mobile/src/services/localSchema.ts, mobile/src/utils/localDate.ts).
- api_contracts[0].business_logic[1] = createDraft() mengisi date lokal 'YYYY-MM-DD' (todayLocalDateString); view fallback nowLocalDateTimeString() bila kosong ← diff createDraft + form.date fallback di view
- api_contracts[0].business_logic[2] = Verifikasi berisi "Catatan" (FormField id field-note) ← diff template view
- api_contracts[0].edge_case_handling (append) = penolakan 4xx menulis sync_error lokal, dikosongkan saat berhasil, offline tidak dicatat; tampil di Data Preview "Gagal sinkron: <alasan>" ← syncService.failure()/rememberSyncError()/markSynced(), localSchema.migrateRecordTablesForSyncError, components/SyncFailureHint.vue
- implementation_notes (append) = write-through aktif sungguhan (millSettingRepo SELECT immediate_sync_enabled), payload date lokal, Production Line per record dari station_id ← diff millSettingRepo.ts, syncService.pushUniformRow/resolveRecordContext/syncTable
