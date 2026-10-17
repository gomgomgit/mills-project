# Derived Assumptions — module-dashboard.screen-164--laporan-kernel-dispatch-web.3-tech-spec

## v1 — 2026-10-18

- EMPAT endpoint baru `/api/kernel-dispatch-reports/{business-units/options,periods,summary,export}` ← pola enam belas laporan sebelumnya; pengguna tidak menyebut endpoint.
- Operator diterima di ketiga rute data SEJAK COMMIT PERTAMA ← supaya screen-165 nanti tidak perlu melebarkan izin. Pola terbukti tiga kali (158/159, 160/161, 162/163).
- Bentuk payload HIBRIDA: blok `dispatches` (transaksi) + blok `quality` (empat ukuran min/avg/max) ← keputusan saya; belum ada preseden untuk gabungan ini, jadi separuhnya disalin dari screen-162 dan separuhnya dari screen-160.
- Keempat `measured_count` INDEPENDEN dan diterbitkan masing-masing ← diturunkan dari fakta bahwa keempat kolom mutu nullable secara independen. Pengguna tidak membahas penyebut.
- Penyebut nol pada satu ukuran TIDAK mengosongkan ukuran lain, diperiksa PER UKURAN ← keputusan saya; satu penjaga tunggal 'ada data mutu atau tidak' akan tampak rapi dan salah.
- `quality._note` diterbitkan dan dirender utuh ← keputusan saya; tanpa kalimat itu penyebut yang berbeda akan dibaca sebagai data hilang.
- TIGA rekap kategoris teks bebas + SATU rekap enum ← saya pilih keempat kolom itu dari skema.
- `silo_source_id` sebagai TEKS ← DIVERIFIKASI tiga cara: kolomnya `string` nullable di migrasi, tidak ada tabel silo di satu migrasi pun, dan form web merendernya `<input type="text">`. Nama berakhiran `_id` menyesatkan.
- `by_gate_status` menerbitkan KODE MENTAH; label diturunkan DI BLADE lewat `Display::option()` ← keputusan saya, supaya kosakata tidak pindah ke lapisan yang salah dan supaya pasangan mobile menerima kode yang dapat ia perlakukan sendiri.
- Nilai enum tak dikenal ditampilkan APA ADANYA, tidak dibuang dan tidak dipaksa masuk pilihan ← DIVERIFIKASI: `KernelDispatchRecordService` menyimpan `filled(...) ? $value : null` tanpa daftar tertutup, dan `Display::option()` memang jatuh ke nilai mentah.
- `not_released_count` menghitung HANYA `'not_released'` persis, bukan termasuk null ← keputusan saya; menggabungkannya akan menuduh pengiriman yang sekadar belum diisi statusnya.
- TIGA penghitung kepatuhan terpisah, dan kedua segel TIDAK PERNAH dijumlahkan ← keputusan saya.
- Urutan rekap `net_weight_total` DESC → `dispatch_count` DESC → `value` ASC ← disalin dari `WeighbridgeReportService::groupedBreakdownOf()`, bukan diputuskan ulang.
- `text_entries` HANYA `findings` ← diturunkan dari skema; stasiun ini tidak punya kolom `remarks`.
- TANPA `hourly` dan TANPA waktu putar kendaraan, keduanya dinyatakan di layar, dengan penyebutan eksplisit bahwa CPO Dispatch justru punya ← diturunkan dari ketiadaan `time_in`/`time_out` di tabel ini dan keberadaannya di `cpo_dispatch_details`.
- TANPA penandaan nilai mutu di luar batas ← diturunkan dari ketiadaan master ambang mutu.
- `undated_dispatch_count` dipertahankan sebagai penjaga invarian tetapi dicetak hanya ketika > 0 ← `event_date` NOT NULL, pelajaran yang sudah dibayar di screen-162.
- Entri REPORT_ROUTES di-APPEND di akhir ← `sort_order` kernel-dispatch = 160, di belakang solid-waste-disposal (150). Dibaca dari tabel master, bukan dari urutan deklarasi enum.
- min/avg/max dihitung DI PHP, bukan lewat agregat SQL ← konsisten dengan enam belas laporan sebelumnya; menghindari perbedaan pembulatan antar-mesin basis data (suite di SQLite, produksi PostgreSQL).
- Profil fixture browser DIVERIFIKASI LANGSUNG di `mill_smart_log_e2e`, bukan diasumsikan: 2 baris, `waybill_number` TERISI sementara ketiga kolom kategoris, keempat kolom mutu, kedua segel dan status gerbang NULL. Profil itu memberi tiga pembuktian gratis (mutu kosong sementara berat ada; penghitung kepatuhan yang benar-benar NOL; 'tidak tercatat' dengan `not_released_count` = 0) dan membuat asersi urutan rekap VAKUM di browser — dinyatakan supaya tidak ditulis seolah teruji.
