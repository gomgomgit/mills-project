# Derived Assumptions — module-dashboard.screen-130--laporan-cages-track-web.2-business-spec

## v1 — 2026-09-24

Yang Anda tetapkan saat penetapan scope hanyalah garis besar isinya: *"Cages & Tracks (per jam):
lori di-tip per hari & per jam, puncak & jeda tipping, antrean tersisa, jam tanpa tipping"*.
Seluruh butir di bawah ini turunan agent dari membaca struktur data nyatanya.

- **Penumpahan dihitung dari baris rincian per jam, bukan dari `cages_tipped` pada record harian** ← keduanya ada di basis data dan bisa berbeda bila Operator mengoreksi salah satunya. Rincian per jam yang benar-benar tercatat per kejadian, jadi itu yang dipakai. Tanpa aturan ini, dua implementasi yang sama-sama "benar" bisa menghasilkan total berbeda.
- **Jam tanpa penumpahan hanya dihitung di dalam jam operasi tippler** ← menghitung seluruh 24 jam membuat mill satu shift selalu terlihat punya 16 jam menganggur. Angkanya benar, tapi menyesatkan, dan laporan yang menyesatkan akan berhenti dipercaya.
- **Jeda terpanjang dihitung dalam satu hari, tidak lintas hari** ← jeda semalam bukan jeda operasional. Tanpa batas ini, setiap mill punya "jeda 14 jam" setiap hari dan metriknya kehilangan arti.
- **`cages_remain` adalah potret per jam, bukan antrean menumpuk** ← diverifikasi ke `CagesTrackRecordService`: nilainya `machineryCountForStation($station->id) - $totalCages`, yaitu armada lori stasiun dikurangi yang ditumpahkan pada jam itu. Nama kolomnya mudah disalahartikan sebagai antrean kumulatif; karena itu laporan menampilkan nilai **terendah** dan rata-rata, bukan jumlah.
- **Hari tanpa waktu berhenti tippler dikeluarkan dari rata-rata durasi, jumlahnya ditampilkan** ← `tippler_stop_time` nullable. Pola yang sama dengan `cycles_without_duration` di screen-129, disengaja agar kedua laporan bercerita dengan cara yang sama.
- `edge_cases` **operasi melewati tengah malam** ← `tippler_start_time` dan `tippler_stop_time` adalah timestamp, jadi shift malam menghasilkan jam mulai lebih besar dari jam berhenti. Tanpa penanganan, durasinya negatif dan jam operasinya kacau.
- `edge_cases` **hari dengan record tetapi nol baris rincian** ← mungkin terjadi karena `CagesTrackRecord` dapat dibuat lalu rinciannya menyusul. Hari itu harus terhitung sebagai hari operasi tanpa penumpahan, bukan hilang dari penyebut.
- `actors` tanpa Operator ← konsisten dengan screen-129: Operator tidak punya UI web. Jalur mobilenya screen-136, terpisah.
- `test_priority` = `high` ← 11 aturan (ambang 5+), tiga peran dengan perilaku cakupan mill berbeda, dan empat aturan perhitungan yang masing-masing bisa salah tanpa terlihat dari angka totalnya.
- `usecase_ids` ← `usecase-130--...` sudah terdaftar di usecase-index sejak penetapan scope; artefaknya baru ditulis pada run ini.

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (CagesTrackReportService.php, laporan-cages-track.blade.php, LaporanCagesTrack.php, ReportAuditFix20261004Test.php, WebAccessTest.php).
- information_displayed[15] = Tombol Ekspor CSV dan Ekspor Excel (.xlsx sungguhan) ← blade export('csv')/export('excel') + SheetWriter
- information_displayed[+] = pemilih Production Line wajib + nama line di hero ← hero `$selectedProductionLine` (#10)
- available_actions[3].description = CSV/xlsx, kolom Periode/Mill/Production Line, jam HH:MM (06:00), status Indonesia ← export() diff
- available_actions[+] = Pilih Production Line ← LaporanCagesTrack production_line_id
- business_rules[2] = Operator login web terbatas, laporan 403, menu tak tampil; laporan Operator di mobile ← WebAccessTest + RouteAccess
- business_rules[+] = Production Line wajib, tanpa opsi semua line ← tech-spec + Livewire (sebelumnya tak tercatat di business-spec)
- edge_cases[0] = 0 hari ber-record → rata-rata '–' + 'Belum ada hari ber-record…'; lori keluar 'Belum ada record pada periode dan line ini' bukan 'Sama banyak' ← blade #9 + test '#9 Cages & Tracks tanpa data'
- edge_cases[8] = + kolom batang selebar label, gulir di kartu, petunjuk bila > 10 tanggal ← md-trendchart--days + scroll-hint

## v3 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit db73fbd, c321f32), code is truth (components/report-filter-bar.blade.php, livewire/dashboard/laporan-*.blade.php, app/Livewire/Dashboard/LaporanCagesTrack.php).
- information_displayed[0] ← format opsi periode ringkas 'Nama · rentang · Status' + badge status periode terpilih (components/report-filter-bar.blade.php, db73fbd); jenis stasiun tidak lagi ditulis di opsi
- information_displayed[1] ← mill akun terikat tampil sebagai keterangan statis field 'Mill' di report-filter-bar
- information_displayed[+] ← susunan & label bar filter bersama (report-filter-bar): urutan field, posisi tombol ekspor, catatan, opsi awal 'Pilih Mill'/'Pilih Line', badge 'Aktif'
- available_actions[3].description ← tombol ekspor wire:loading.attr=disabled (target export) + x-busy-label 'Mengekspor…' (c321f32)
