# Derived Assumptions Log — module-mobile-station-ops.screen-011--form-grading.2-business-spec

## v1 — 2026-08-15

- information_displayed = header + Grading Detail grid + Checked By field ← proposed by agent in draft (mirrored from Form Weighbridge pattern + uiux-spec grid detail pattern), accepted without correction
- business_rules = ["Checked By hanya Supervisor", "Data kendaraan/supir/estate teks bebas", "Minimal satu baris Grading Detail sebelum Simpan", "Record tersimpan langsung tanpa approval"] ← proposed by agent, accepted without correction
- edge_cases = ["Field wajib belum lengkap", "Belum ada baris Grading Detail", "Operator akses Checked By", "Lanjutkan draft paused", "Back dengan perubahan belum tersimpan"] ← proposed by agent, accepted without correction

## v2 — 2026-08-18

- Full rewrite mengikuti mock referensi gambar yang diberikan user: field header persis "Grading Header" (Grading No, Date, WB Card No, License Plate No, Vehicle Code, Estate, Division, Netto (kg), Quantity (bunch), Note) dan "Grading Detail" grid (Quality Parameter/Qty/UoM/Percentage) ← user eksplisit memilih "Ikuti mock persis" via pertanyaan pilihan, vehicle_number lama dipecah jadi license_plate_no+vehicle_code, driver_name dan block DIHAPUS total (bukan disembunyikan di UI, dihapus dari entity-catalog v2)
- Checked By TIDAK ditampilkan di form ini ← mock tidak mencantumkan field ini pada Grading Header; TIDAK ada instruksi eksplisit user untuk menghapusnya (berbeda dari Weighbridge yang eksplisit "tidak perlu ada checked by") — kolom checked_by tetap dipertahankan di skema (entity-catalog v2 tidak menghapusnya), hanya tidak diekspos di form ini karena "ikuti mock persis" tidak menyertakannya. Ini judgment call agent, bukan pernyataan eksplisit user tentang Checked By spesifik untuk Grading — DIFLAG di checkpoint pra-implementasi untuk konfirmasi user.
- WB Card No dropdown ← field baru, bukan revisi field lama; sumbernya weighbridge-record (FK weighbridge_record_id) sesuai entity-catalog v2, auto-fill License Plate No/Estate/Divisi saat dipilih tapi tetap dapat diedit manual setelahnya ← instruksi eksplisit user "untuk data yang bisa diambil dari weighbridge seperti license_place_no bisa terisi otomatis setelah memilih wb card no"
- open_questions: cakupan dropdown WB Card No (semua record vs filter tanggal/status) ← BELUM dijawab eksplisit oleh user; agent mengasumsikan SEMUA record Weighbridge lokal apa pun statusnya, terbaru dulu — akan diverifikasi di checkpoint pra-implementasi, bukan diputuskan sepihak sebagai final

## v3 — 2026-08-19

- Business rule baru: setiap Quality Parameter hanya bisa dipakai di satu baris Grading Detail, tidak muncul lagi di dropdown baris lain setelah dipilih, kembali tersedia jika baris dihapus/parameter diganti ← instruksi eksplisit user ("quality parameter yang sudah ditambah di grading itu tidak akan muncul lagi di baris lain")
- Interpretasi "1 baris untuk qty uom dan percentage nya" ← ditafsirkan sebagai penegasan bahwa struktur satu-baris-satu-parameter (yang memang sudah menjadi desain sejak v2) kini ditegakkan sebagai aturan eksplisit (parameter tidak boleh dipakai ulang di baris lain), bukan perubahan struktur baris itu sendiri
- Parameter kembali tersedia otomatis jika baris yang memakainya dihapus atau parameternya diganti ← logical consequence, bukan instruksi terpisah dari user

## v4 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/FormGradingView.vue, mobile/src/services/gradingRecordRepo.ts, mobile/src/services/gradingParameterSync.ts, mobile/src/services/syncService.ts, mobile/src/services/localSchema.ts).
- information_displayed[0] = label 'No. Grading', 'No. Polisi', 'Kode Kendaraan', 'Catatan'; Tanggal lokal; dropdown WB hanya saved/synced ber-No. WB Card + pilihan draft ← FormField label/id (field-grading-no, field-license-plate-no, field-vehicle-code, field-note); getWeighbridgeRecordOptions(includeId) WHERE status IN ('saved','synced') AND wb_card_number <> ''.
- business_rules[0] = dropdown WB difilter saved/synced + No. WB Card, kecuali pilihan draft ← gradingRecordRepo.getWeighbridgeRecordOptions + onMounted loadDraft().then(loadWbOptions).
- business_rules[1], [2] = label No. Polisi / Kode Kendaraan / Catatan ← FormGradingView.vue.
- business_rules[7] = Tanggal pakai tanggal/jam LOKAL, dikirim 'YYYY-MM-DD' lokal ← FormGradingView.nowLocalDateTimeString (sudah ada sebelumnya), syncService.pushGradingRow toLocalDateString(row.date).
- business_rules += master Quality Parameter diselaraskan dari server (login + sebelum sinkron Grading), peta via nama, id tak terpetakan → ditolak di perangkat ← gradingParameterSync.fetchAndCacheGradingParameters/resolveServerGradingParameterIds, auth.ts, syncService.syncAllRecords/pushGradingRow.
- business_rules += Grading ditolak di perangkat bila Weighbridge tertaut belum tersinkron ← syncService.pushGradingRow localFailure. (⚠ perilaku ini sudah ada sebelum audit; kini juga ditulis ke sync_error lewat localFailure — didokumentasikan karena sebelumnya tidak ada di spec.)
- edge_cases[2] = dropdown kosong bila tak ada WB saved/synced ber-No. WB Card ← filter repo.
- edge_cases += pilihan WB lama tetap tampil; master parameter belum tersinkron → gagal sinkron di perangkat; 422 periode tertutup / tanggal > besok → 'Gagal sinkron: <alasan>' ← includeId, gradingParameterSync, GradingRecordService assertEventDateNotTooFarAhead('date'), SyncFailureHint.vue.
- open_questions[0] = TERJAWAB: dropdown dibatasi saved/synced ber-No. WB Card ← getWeighbridgeRecordOptions.
