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
