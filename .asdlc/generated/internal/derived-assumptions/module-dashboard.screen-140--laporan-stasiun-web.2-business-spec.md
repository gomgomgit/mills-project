# Derived Assumptions — module-dashboard.screen-140--laporan-stasiun-web.2-business-spec

## v1 — 2026-09-23

Yang dinyatakan langsung oleh user hari ini hanyalah urutan alurnya: *"di dalam menu
laporan stasiun sebaiknya memilih mills nya terlebih dahulu lalu memilih stasiun"*.
Seluruh butir di bawah ini adalah turunan agent dari kalimat itu ditambah konteks repo,
bukan pernyataan user.

- `business_rules[1]` = "Pemilih Mill hanya untuk pengguna yang punya lebih dari satu pilihan; Supervisor & Mill Management ditetapkan otomatis dan tidak dapat memilih mill lain" ← user meminta langkah pilih Mill tanpa menyebut siapa yang melihatnya. Hanya Admin yang `users.business_unit_id`-nya NULL; peran lain terikat satu mill, sehingga bagi mereka langkah itu berisi tepat satu pilihan. Pola "lebih dari satu → tampilkan; tepat satu → pilih otomatis" disalin dari pemilih Production Line di mobile `StationListView.vue`.
- `business_rules[5]` = "Mill yang ditetapkan ikut terbawa ke layar laporan" ← agar screen-129 tidak menampilkan pemilih Mill kedua dalam satu alur. User tidak menyebut soal ini; konsekuensinya screen-129 perlu disesuaikan, dicatat sebagai pekerjaan terpisah.
- `business_rules[2]` dan `business_rules[3]` = daftar stasiun bersumber dari master Jenis Stasiun, urut `sort_order`, jenis historis `other` dikecualikan ← dipilih agar menambah jenis stasiun cukup INSERT di master, konsisten dengan keputusan `station_types` 2026-09-22. User tidak menyebut sumber daftarnya.
- `entry_points` = ["Menu sidebar 'Laporan Stasiun'", "Kembali dari salah satu layar laporan periode stasiun"] ← entri sidebar sudah ada (sementara menembak ke Sterilizer); jalur "kembali" diturunkan dari sifat layar sebagai pintu masuk, tidak dinyatakan user.
- `edge_cases` (6 butir) ← seluruhnya turunan agent. Yang paling berkonsekuensi: akun Supervisor/Mill Management yang belum terhubung ke mill diberi pesan jelas dan **tidak** diberi daftar seluruh mill sebagai gantinya — gagal tertutup, bukan gagal terbuka.
- `test_priority` = "high" ← 7 business rule (ambang aturan adalah 5+), ditambah perilaku yang berbeda per peran dan menyangkut pembatasan cakupan mill. Meski layar ini hanya baca dan navigasi, salah menerapkan aturan kedua berarti Supervisor bisa melihat mill lain.
- `usecase_ids` = ["usecase-142--laporan-stasiun-web"] ← sudah terdaftar di usecase-index untuk layar ini sejak penetapan scope; artefaknya belum pernah ditulis, jadi dibuat v1 pada run ini.
