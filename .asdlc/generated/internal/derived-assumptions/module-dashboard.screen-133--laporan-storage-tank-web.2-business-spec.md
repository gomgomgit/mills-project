# Derived Assumptions — module-dashboard.screen-133--laporan-storage-tank-web.2-business-spec

## v1 — 2026-09-25

- **Stok BUKAN agregasi melainkan perbandingan pembacaan pertama vs terakhir per tangki** ← konsep yang tidak ada di empat laporan stasiun sebelumnya. Diturunkan dari cakupan user ("stok awal-akhir & pergerakan per tangki") plus skema: tidak ada kolom stok periode, yang ada `calculated_weight_mt` per slot waktu. Konsekuensinya yang menentukan bukan berapa banyak data yang ada, melainkan apakah dua pembacaan ujungnya ada.
- **Tanggal kedua pembacaan ujung wajib ditampilkan** ← tidak diminta user. Stok awal yang diambil pada hari ketiga periode berarti angka pergerakannya menutupi kurun yang lebih pendek daripada periodenya, dan tanpa tanggalnya pembaca tidak punya cara mengetahui itu.
- **Pergerakan dihitung per tangki lalu dijumlahkan** ← bila jumlah tangki yang tercatat berbeda antara awal dan akhir periode, menghitung dari stok gabungan mencampurkan perubahan stok dengan perubahan cakupan pencatatan. Keduanya sama-sama terlihat seperti angka stok yang sah.
- **Tangki dengan satu pembacaan → `null`, bukan 0** ← nol berarti "stok tidak berubah", klaim yang lebih kuat daripada "tidak dapat dihitung". Tangki itu juga tidak menyumbang ke total pergerakan.
- **Suhu rata-rata dari kolom yang dicatat Operator, tidak dihitung ulang** ← alasan yang sama dengan `cages_tipped` di [[module-dashboard.screen-130--laporan-cages-track-web.3-tech-spec]]: menghitung ulang menciptakan kebenaran kedua yang menyimpang diam-diam, dan di sini selisihnya tidak akan pernah terlihat karena keduanya sama-sama masuk akal.
- **DIPUTUSKAN 2026-09-25: stok dari BERAT (MT), bukan volume (m³)** ← keputusan pemilik proses. Saya mengangkatnya sebagai pertanyaan terbuka karena skema menyimpan berat, volume, dan kedalaman sounding secara terpisah dan ketiganya dapat terisi sendiri-sendiri; angkanya akan berbeda dan ketiganya sama-sama terlihat sah. Volume dan kedalaman tetap dilaporkan sebagai metrik tersendiri.
- **Skala DOBI tidak sebanding dengan FFA dan kadar air** ← DOBI ~2–4, FFA ~3–5%, kadar air ~0,1–0,3%. Menempelkan ketiganya pada satu sumbu linear membuat dua di antaranya terlihat datar dan seolah tidak berubah. Layar wajib menormalkan atau memakai sumbu kedua, dan menyatakannya. Turunan agent; user hanya menulis "tren FFA/moisture/DOBI".
- **Tidak ada penandaan nilai di luar batas** ← diverifikasi tidak ada `StorageTankOperationalTarget`, dan docblock `StorageTankRecordService` menyatakan ketiadaan itu memang disengaja, sama seperti Engine Room/Effluent Plant. FFA dan kadar air punya batas mutu yang lazim dikenal di industri; itu diangkat sebagai `open_questions` yang MASIH TERBUKA.
- `test_priority` = `high` ← menyangkut cakupan mill dan hak akses lintas peran, sama dengan empat laporan stasiun sebelumnya.

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (StorageTankReportService.php, laporan-storage-tank.blade.php, ReportPeriodDays.php, ExportValue.php, SheetWriter.php).
- information_displayed[2] = nama Production Line ikut di hero ← blade `@if ($selectedProductionLine) {{ name }} &middot;`
- information_displayed[14] = kelengkapan 1 desimal, penyebut sampai hari ini + catatan period-running, '—' bila expected 0 ← blade coverage-percent/period-running-note/coverage-no-expected
- information_displayed[16] = Ekspor CSV + Ekspor Excel (.xlsx sungguhan) ← blade export-excel-button + SheetWriter
- information_displayed[+] = Pemilih Production Line wajib tanpa opsi semua ← blade production-line-select (perubahan 2026-09-28 yang belum tercatat di business-spec; ⚠ ditambahkan karena artefak tidak menyebutnya sama sekali)
- available_actions[3].description = ekspor CSV/xlsx dengan kolom Periode/Mill/Production Line, status Indonesia, HH:MM, label katup ← StorageTankReportService::EXPORT_HEADER/streamExportRows
- business_rules[+3] = penyebut sampai hari ini; persen 1 desimal; label ekspor terbaca ← ReportPeriodDays::counted, blade $nilai(...,1), ExportValue
- edge_cases[+2] = periode berjalan; penyebut 0 → '—' ← blade + ReportAuditFix20261004Test
