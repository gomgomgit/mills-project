
## v1 — 2026-10-05
- business_rules[filter tanggal default] = 7 hari terakhir (H-6 s.d. hari ini, WIB), mengacu ke tanggal record (Weighbridge: bagian tanggal record_datetime) ← user menyerahkan pilihan "today/last 7 days" ke agen; 7 hari dipilih agar record dari HP yang baru disinkron beberapa hari kemudian tetap terlihat tanpa mengubah filter
- information_displayed[kolom tabel] = Production Line (pertama), Stasiun, ID Record, Tanggal, Status, Checked By, Acknowledged By, Diterima Server ← diturunkan agen dari pola Data Browser (Production Line kolom pertama) + permintaan "status sinkron/verifikasi per record"; kolom 'Diterima Server' (created_at) ditambahkan agen agar Operator bisa memastikan sync-nya sudah sampai
- information_displayed[label status verifikasi] = 'Belum diperiksa' / 'Belum dikonfirmasi' / Grading 'Tidak berlaku' ← diturunkan agen dari pending-label mobile ("Belum diperiksa Supervisor", "Belum dikonfirmasi Mill Management") dan aturan Grading tanpa Checked By (RecordVerificationService::NO_CHECKED_BY_MODELS)
- information_displayed[catatan sinkron] = catatan tetap "data mobile baru tampil setelah disinkronkan; draft di HP tidak tampil" ← agen; record Operator hanya sampai server lewat sync manual
- available_actions = Saring, Reset filter, Navigasi halaman, Buka detail, Kembali ke daftar ← diturunkan agen; Reset Filter mengikuti pola empty-state uiux list
- entry_points[tombol Beranda] = tombol 'Lihat Data Saya' di Beranda Operator ← user menyebut "dicapai dari Beranda Operator"; bentuk tombol dan teksnya keputusan agen
- entry_points[kembali dari detail] = kembali ke daftar dengan filter & halaman yang sama ← agen
- business_rules[peran lain] = Supervisor/Mill Management/Admin mendapat 403 di layar ini dan tidak melihat menunya ← keputusan agen (mereka sudah punya Data Browser); user hanya menyatakan layar khusus Operator
- business_rules[urutan stasiun] = urutan tetap kanonis (7 MVP dulu, lalu sisanya), bukan alfabetis ← agen, mengikuti uiux screen_type_patterns[list]
- business_rules[urutan daftar] = tanggal record terbaru, lalu waktu diterima server terbaru; 20 per halaman; ≤2 detik ← agen dari shared pagination + NFR PRD
- business_rules[detail tanpa Edit/verifikasi] = detail memakai label & pengelompokan layar Detail stasiun tetapi tanpa tombol Edit maupun tombol verifikasi ← user: "Detail labels consistent with existing Detail screens"; penghilangan tombol diturunkan agen dari "No input/edit/verify"
- edge_cases[akun tanpa mill] = pesan 'Akun Anda belum terhubung ke mill. Hubungi Admin.' + daftar kosong ← agen, mengikuti perilaku gagal-tertutup ScopesToActorMill
- edge_cases[tautan record bukan milik] = 'Record tidak ditemukan' tanpa mengonfirmasi keberadaan ← agen, sejalan dengan pola Detail (id bukan UUID = tidak ditemukan) dan RecordVerificationStatusService
- edge_cases[line/stasiun asing lewat tautan] = diabaikan diam-diam (Semua Line / Semua Stasiun) ← agen, mengikuti aturan clampProductionLineIdToMill Data Browser
- edge_cases[rentang tanggal terbalik] = pesan 'Tanggal Dari tidak boleh setelah Tanggal Sampai', tidak disaring ← agen, mengikuti INVALID_DATE_RANGE Data Browser
- test_priority = high ← menyangkut cakupan data pribadi (hanya data sendiri, hanya mill sendiri), 12 business rule, dan pemisahan akses antar-peran
- open_questions = [] ← agen menilai tidak ada pertanyaan bisnis yang menghalangi Phase 3; keputusan yang masih bisa diperdebatkan dicatat sebagai asumsi di atas

## v2 — 2026-10-05
- actors = Operator + Supervisor ← USER (Checkpoint 3b): "Akses DIUBAH menjadi Operator + Supervisor"; Mill Management & Admin tetap 403 tanpa menu ← USER
- business_rules[cakupan Supervisor] = Supervisor hanya melihat record buatannya sendiri di mill sendiri, read-only seperti Operator; tambahan murni — layar Supervisor lain tetap ← USER
- business_rules[filter tanggal default] = 7 hari terakhir ← USER mengonfirmasi asumsi v1 (bukan lagi turunan agen)
- entry_points[Supervisor] = hanya menu sidebar 'Data Saya' (Supervisor tidak punya Beranda, mendarat di /dashboard) ← turunan agen dari keputusan user
- edge_cases[Supervisor vs record Operator] = record buatan Operator tidak tampil di Data Saya Supervisor (tetap lewat Data Browser) ← USER ("hanya record yang mereka buat sendiri"); rumusan edge case oleh agen
