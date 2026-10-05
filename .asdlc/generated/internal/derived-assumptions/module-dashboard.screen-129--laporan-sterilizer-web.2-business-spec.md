# Derived Assumptions Log — module-dashboard.screen-129--laporan-sterilizer-web.2-business-spec

## v1 — 2026-09-23

- test_priority = "medium" ← 11 business rules memenuhi ambang "high" secara jumlah, TAPI layar ini read-only, tidak mengubah satu baris data pun, dan salah hitung tidak merusak apa pun (hanya menyesatkan pembaca sampai diperbaiki). Agen menurunkannya ke medium atas dasar dampak, bukan jumlah aturan — berbeda dari screen-128 yang high karena aksinya mengunci data
- business_rules += "Status periode ditampilkan sebagai keterangan tetapi TIDAK membatasi apa pun di layar ini: laporan periode tertutup tetap dapat dilihat dan diekspor" ← agent-derived. User tidak pernah membahas apakah periode Draft/Tertutup boleh dilaporkan. Agen memutuskan boleh, karena mengunci laporan periode tertutup akan membuat fitur ini tidak berguna justru saat angkanya paling final
- business_rules += "Periode yang dapat dipilih hanya yang mencakup jenis stasiun Sterilizer, TERMASUK periode bercakupan semua jenis stasiun" ← turunan langsung dari desain station_type nullable, tapi konsekuensinya bagi pemilih periode tidak pernah dinyatakan user
- business_rules += "Siklus tanpa durasi tetap dihitung pada Total Siklus namun DIKELUARKAN dari perhitungan durasi, dan jumlah yang dikeluarkan disebutkan" ← agent-derived. Tanpa aturan ini, siklus yang belum selesai akan diam-diam menurunkan durasi rata-rata. Keputusan menyebutkan jumlahnya di layar adalah pilihan agen
- business_rules += "Ambang siklus menyimpang diturunkan dari sebaran durasi periode itu sendiri, dan ambang yang dipakai WAJIB ditampilkan di layar" ← user meminta "siklus di luar durasi normal" tanpa mendefinisikan normal. Agen memilih ambang relatif terhadap data periode (bukan angka tetap) karena tidak ada master target operasional untuk Sterilizer, dan mewajibkan ambangnya tampil agar angka bisa dipertanggungjawabkan. Nilai ambang persisnya ditentukan di Phase 3
- business_rules += "Laporan bersifat baca saja" ← tidak dinyatakan user, tapi menutup pertanyaan apakah verifikasi/koreksi bisa dilakukan dari sini
- edge_cases = 8 kasus ← seluruhnya agent-derived. Dua yang paling berkonsekuensi: "seluruh durasi seragam → kartu menyatakan tidak ada yang menyimpang, bukan tampil kosong" dan "data belum terverifikasi tetap ditampilkan — verifikasi bukan syarat tampil"
- entry_points memuat entri sidebar SEMENTARA yang mengarah langsung ke laporan ini ← konsekuensi screen-140 (grid pemilih stasiun) belum dibangun; user menetapkan konsep navigasinya, agen memutuskan jembatan sementaranya
- Mill Management dibatasi ke mill sendiri ← tidak dinyatakan user untuk layar ini, tapi konsisten dengan Laporan Manajemen (screen-026) dan Mills Setting yang sudah berlaku begitu

## v2 — 2026-09-23 (koreksi lubang yang ditemukan sebelum Phase 3)

- business_rules += "Admin tidak terikat pada satu mill sehingga Admin — dan hanya Admin — mendapat pemilih Mill" ← KOREKSI atas v1. Pemeriksaan data (`users.business_unit_id`) menunjukkan seluruh Admin ber-business_unit_id NULL, sementara Supervisor dan Mill Management punya. Aturan v1 ("dibatasi ke mill sendiri") akan membuat Admin SELALU melihat empty state karena tidak ada mill yang cocok. Preseden Laporan Manajemen (screen-026) tidak menolong: layar itu hanya untuk Mill Management sehingga tidak pernah menemui kasus ini. Agen memilih pemilih Mill khusus Admin ketimbang "Admin melihat semua mill sekaligus", karena angka gabungan lintas mill tidak punya arti operasional
- available_actions += "Pilih Mill" (actor_ids: hanya actor-admin) ← konsekuensi aturan di atas
- information_displayed += "Pemilih Mill — hanya tampil untuk Admin" ← konsekuensi aturan di atas
- edge_cases += "Admin membuka laporan tanpa memilih mill → pesan yang meminta pemilihan mill terlebih dahulu" ← agen memilih pesan pemandu, bukan auto-pilih mill pertama, karena auto-pilih akan membuat Admin mengira sedang melihat data seluruh mill
- bdd_scenarios: 13 skenario diturunkan bdd-spec-writer-agent dari usecase v1, lalu agen MENAMBAH 2 skenario Admin (belum memilih mill, dan setelah memilih mill) yang tidak ada di keluaran agent karena aturan Admin baru ditemukan sesudahnya. Total 15

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (SterilizerReportService.php, laporan-sterilizer.blade.php, LaporanSterilizer.php, layouts/app.blade.php + RouteAccess, WebAccessTest).
- business_rules[0] = Operator login web terbatas (Beranda + Ganti Password); laporan → 403, menu tak tampil ← WebAccessTest + RouteAccess di sidebar
- business_rules[+] = Production Line wajib, tanpa opsi semua line; sebelum dipilih tak ada angka/Ekspor; nama line di hero ← LaporanSterilizer::$productionLineId/needsProductionLineSelection + hero `$selectedProductionLine` (sudah ada di tech-spec v3, belum di business-spec)
- available_actions[3].description = ekspor CSV / .xlsx sungguhan, konteks Periode/Mill/Production Line, judul Indonesia, status Indonesia, HH:MM, Ya/Tidak ← SheetWriter + EXPORT_HEADER + ExportValue
- available_actions[+] = "Pilih Production Line" ← #[Url(as: 'production_line_id')]
- information_displayed[+] = pemilih Production Line + nama line di hero ← blade hero (#10)
- edge_cases[5] = kolom batang selebar label, gulir di kartu, petunjuk "Geser mendatar…" bila > 10 tanggal ← md-trendchart--days + `count($daily) > 10`

## v4 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit db73fbd, c321f32), code is truth (components/report-filter-bar.blade.php, livewire/dashboard/laporan-*.blade.php, app/Livewire/Dashboard/LaporanSterilizer.php).
- information_displayed[1] ← format opsi periode ringkas 'Nama · rentang · Status' + badge status periode terpilih (components/report-filter-bar.blade.php, db73fbd); jenis stasiun tidak lagi ditulis di opsi
- information_displayed[0] ← mill akun terikat tampil sebagai keterangan statis field 'Mill' di report-filter-bar (LaporanSterilizer kini mengirim businessUnitName untuk peran terikat — sebelumnya layar ini tidak menulis nama mill di area filter)
- information_displayed[+] ← susunan & label bar filter bersama (report-filter-bar): urutan field, posisi tombol ekspor, catatan, opsi awal 'Pilih Mill'/'Pilih Line', badge 'Aktif'
- available_actions[3].description ← tombol ekspor wire:loading.attr=disabled (target export) + x-busy-label 'Mengekspor…' (c321f32)
