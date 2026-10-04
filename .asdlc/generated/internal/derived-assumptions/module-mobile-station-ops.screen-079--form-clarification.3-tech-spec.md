# Derived Assumptions Log — module-mobile-station-ops.screen-079--form-clarification.3-tech-spec

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Tidak menambah unit_test_cases: form tidak punya logika periode untuk diuji (simpan lokal tanpa cek periode).
- TEMUAN yang menyimpang dari brief: form ini memanggil writeThroughSync.syncAfterSave() setelah simpan lokal; bila Mills Setting immediate_sync_enabled aktif, record langsung di-POST ke server dan penolakan 422 PERIOD_CLOSED ditelan diam-diam. Didokumentasikan sebagai jalur tulis server, bukan sebagai 'tidak ada panggilan server'.
- Tidak menambah endpoint ke api_contracts (endpoints tetap kosong, sesuai instruksi) meskipun ada jalur POST write-through.
- Dikonfirmasi dari kode: form hanya membuka draft (draft_ongoing/draft_paused) — Data Preview mengarahkan record saved/synced ke layar preview, bukan form — sehingga tidak ada jalur edit/PATCH record tersinkron.
- Kalimat edge case/aturan/catatan dirumuskan sendiri; 'pemulihan = Admin membuka kembali baris stasiun di screen-142 lalu sinkron ulang' mengikuti brief.

## v3 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- Edge case usecase-141 ditulis ulang: penolakan 4xx write-through kini tampil lewat ConfirmDialog 'Tersimpan, tetapi ditolak server' lalu ke Monitor; offline/5xx tetap diam.
- implementation_notes[0] ('tidak ada panggilan API') diganti: SQLite lokal + sinkron manual Station List (STATION_PUSH_CONFIGS) + write-through bila immediate_sync_enabled aktif; Pause/Clear/Back tidak memanggil server.
- Catatan REVISI kunci periode: frasa 'kegagalannya diam' dipersempit menjadi offline/5xx saja; frasa 'kode tidak diubah' dihapus karena kode form berubah 2026-10-03.

## v4 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/services/clarificationRecordRepo.ts, mobile/src/views/FormClarificationView.vue, mobile/src/services/syncService.ts, mobile/src/services/millSettingRepo.ts, mobile/src/services/localSchema.ts, mobile/src/utils/localDate.ts).
- api_contracts[0].business_logic[1] = createDraft() mengisi date lokal 'YYYY-MM-DD' (todayLocalDateString); view fallback nowLocalDateTimeString() bila kosong; header berisi "Catatan" (FormField id field-note) ← diff createDraft + template view
- api_contracts[0].edge_case_handling (+2) = draft dini hari/data lama UTC dinormalisasi ke tanggal lokal; penolakan 4xx menulis sync_error lokal (dikosongkan saat berhasil, offline tidak dicatat) → Data Preview "Gagal sinkron: <alasan>"; write-through aktif sungguhan; line dari station_id record ← localSchema.normalizeLegacyUtcDates, syncService failure/markSynced, components/SyncFailureHint.vue, millSettingRepo SELECT
- implementation_notes (append) = REVISI 2026-10-04 audit-fix (date lokal, Catatan, write-through aktif + sync_error + line per record, ConfirmDialog teleport/floatingSafeArea) ← diff kode terkait
