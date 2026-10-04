# Derived Assumptions — module-dashboard.screen-131--laporan-boiler-room-web.2-business-spec

## v1 — 2026-09-24

Cakupan yang user tetapkan untuk Boiler Room menyebut: *"tren tekanan & suhu uap, TDS/pH air, jumlah blowdown & sootblowing per hari, suhu gas buang"*. Yang di bawah ini turunan agent di sekitar pernyataan itu, seluruhnya berdasar pembacaan skema dan kode nyata.

- **Setiap metrik punya penyebutnya sendiri** ← diturunkan dari skema: ke-15 kolom pengukuran di `boiler_room_details` semuanya nullable, dan `BoilerRoomRecordService` mendefinisikan baris "terisi" sebagai minimal satu kolom non-null. Jadi satu baris dapat mengisi tekanan dan mengosongkan pH. Memakai satu penyebut bersama akan mengempiskan rata-rata metrik yang jarang diisi, dan hasilnya tetap terlihat seperti angka yang sah. Tidak dinyatakan user.
- **Jumlah pembacaan ditampilkan berdampingan dengan tiap angka** ← konsekuensi butir di atas. Tanpa itu, rata-rata dari 3 pembacaan dan dari 300 pembacaan terlihat sama meyakinkan. Pilihan agent.
- **Blowdown dan sootblowing punya TIGA keadaan** ← kolomnya enum `y`/`n` yang NULLABLE. "Tidak tercatat" bukan "tidak dilakukan"; memperlakukan NULL sebagai `n` akan melaporkan kelalaian perawatan yang tidak pernah terjadi. Diturunkan dari skema, bukan dari user.
- **Kelengkapan pencatatan diangkat menjadi isi laporan, bukan catatan kaki** ← tidak diminta user. Alasannya: dengan seluruh kolom nullable dan jumlah slot bebas (1..24 per record), sebuah periode bisa terisi 20% dan tetap menghasilkan rata-rata yang terlihat rapi. Kelengkapan adalah temuan, bukan metadata.
- **TIDAK ADA penandaan nilai di luar batas** ← diverifikasi: tidak ada `BoilerRoomOperationalTarget` (hanya Threshing, Pressing, Depricarping, Kernel Plant yang punya), dan docblock `BoilerRoomRecordService` menyatakan ketiadaan itu memang disengaja, sama seperti Engine Room/Storage Tank/Effluent Plant. Cakupan user untuk stasiun ini juga tidak menyebut ambang — berbeda dari Sterilizer yang eksplisit meminta "siklus di luar durasi normal". Menurunkan ambang dari data periode sendiri untuk tekanan boiler berisiko dibaca sebagai batas keselamatan padahal hanya turunan statistik. **Diangkat sebagai `open_questions`.**
- **`fuel_feed_rate`, `id_fan_load`, `sa_fan_load` tidak pernah dirata-rata maupun digrafikkan** ← ketiganya kolom teks bebas, dinyatakan eksplisit di docblock `BoilerRoomRecordService` baris 49 karena satuan pada formulir kertasnya bercampur (Hz/%/ton, A/%). Bukan pilihan gaya; merata-ratakannya mustahil.
- **Rekap per unit boiler** ← `boiler_room_records.boiler_room_id` adalah kolom string, sehingga satu mill dapat punya beberapa unit, sejajar `sterilizer_no` dan `cages_track_number`. User tidak menyebut pemecahan per unit.
- `time_slot` bertipe TIME, bukan integer jam ← berbeda dari `tipped_hour` milik Cages & Tracks. Dicatat karena pengelompokan per jam menuntut ekstraksi dan slotnya belum tentu jatuh tepat di awal jam. Konsekuensinya baru mengikat di Phase 3.
- `test_priority` = `high` ← sama dengan screen-129/130: menyangkut cakupan mill dan hak akses lintas peran.
- `edge_cases` (10 butir) ← seluruhnya turunan agent; empat di antaranya (metrik tak pernah diisi, perawatan tak tercatat, pencatatan tak lengkap, beberapa unit boiler) tidak punya padanan di dua laporan stasiun sebelumnya karena memang sifat stasiun ini.

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (BoilerRoomReportService.php, ReportPeriodDays.php, SheetWriter.php, ExportValue.php, laporan-boiler-room.blade.php, LaporanBoilerRoom.php).
- information_displayed[2] = hero menyebut nama Production Line ← blade `@if ($selectedProductionLine) {{ name }}` (audit #10)
- information_displayed[11] = kelengkapan sampai hari ini untuk periode berjalan + keterangan + '—'/'belum ada slot yang diharapkan' bila expected 0 ← ReportPeriodDays::counted(), blade period-running-note / coverage-no-expected
- information_displayed[17] = tombol Ekspor CSV + Ekspor Excel (.xlsx sungguhan), hanya setelah line dipilih ← blade + SheetWriter
- information_displayed (+2) = pemilih Production Line wajib; petunjuk gulir tren harian > 10 tanggal ← blade production-line-select (sudah ada sebelum audit — drift lama, ditambahkan karena kode acuan) + scroll-hint
- available_actions[3].description = ekspor CSV/xlsx, kolom Periode/Mill/Production Line, status Indonesia, slot HH:MM, Ya/Tidak ← BoilerRoomReportService::EXPORT_HEADER / streamExportRows
- available_actions (+1) = Pilih Production Line ← LaporanBoilerRoom::$productionLineId (drift lama)
- business_rules (+2) = penyebut kelengkapan berhenti di hari ini (WIB); Excel = xlsx sungguhan ← ReportPeriodDays, SheetWriter
- edge_cases (+2) = periode berjalan; expected_slots 0 → '—' ← blade, ReportAuditFix20261004Test #3/#9
