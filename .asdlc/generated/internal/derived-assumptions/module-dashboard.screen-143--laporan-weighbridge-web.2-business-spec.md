# Derived Assumptions — module-dashboard.screen-143--laporan-weighbridge-web.2-business-spec

## v1 — 2026-10-01

Autonomy `autopilot`: draft disintesis lalu diterima tanpa tinjauan per-butir. Seluruh
`information_displayed`, `business_rules`, dan `edge_cases` di bawah adalah rumusan agent
kecuali yang memang dinyatakan user (daftar metrik, pemisahan per jenis arus, dan kewajiban
memilih Production Line).

- `test_priority = "high"` ← tidak ditanyakan. Dasarnya: 17 business rule, dan tiga peran dengan tingkat akses berbeda (Admin memilih mill; Supervisor dan Mill Management terkunci pada mill akunnya; Operator ditolak sama sekali).

- `edge_cases`: "Mill hanya punya satu Production Line — line tetap harus dipilih secara sadar" ← turunan agent, TERVERIFIKASI konsisten dengan kelima laporan web yang sudah ada: `resolveProductionLine()` mengembalikan `null` bila klien tidak mengirim line, jadi nol laporan web memilihkan line secara diam-diam walau hanya ada satu. Yang BERBEDA adalah layar input mobile — `screen-006--station-list` menyatakan "auto-skip jika hanya satu". Perbedaan itu disengaja: pada layar input, auto-skip menghemat ketukan dan konteks line tetap terlihat sepanjang pengisian; pada laporan, line adalah bagian dari arti angkanya, dan angka yang muncul tanpa pembaca pernah menyatakan line-nya mudah disalahbaca sebagai angka seluruh mill.

- `business_rules`: "TRIP BERSTATUS DRAFT IKUT TERHITUNG" ← user menyuruh memutuskan dan menyatakan, tidak menyuruh salah satunya. Saya pilih ikut terhitung setelah memeriksa kode: `grep` pada kelima `*ReportService` menunjukkan NOL service menyaring `status` record. Menyimpang khusus Weighbridge akan membuat enam laporan berhitung dengan aturan berbeda. Jumlah draft ditampilkan supaya pembaca tahu, dan pertanyaan lintas-laporannya diangkat ke `open_questions`.

- `edge_cases`: "Sebuah trip waktu keluarnya lebih awal daripada waktu masuknya" ← turunan agent. User hanya menyebut durasi butuh kedua waktu terisi; durasi negatif adalah kasus yang saya tambahkan karena kedua kolom nullable dan tidak ada constraint urutan di tabelnya.

- `edge_cases`: "Satu estate/supplier menyumbang hampir seluruh arus masuk — rekap tidak dipangkas" ← turunan agent, tidak disebut user.

- `edge_cases`: "Periode hanya memuat arus masuk dan nol arus keluar — kelompok arus keluar tetap ditampilkan" ← turunan agent. Konsekuensi langsung dari aturan pemisahan arus, tetapi keputusan menampilkan kelompok kosong alih-alih menyembunyikannya adalah pilihan saya.

- `information_displayed`: "Kelengkapan: berapa hari dalam periode yang memuat minimal satu trip" ← turunan agent. Kelima laporan lain mengukur kelengkapan sebagai slot waktu terisi dari yang diharapkan, tetapi Weighbridge adalah transaksi, bukan pembacaan berkala — tidak ada jumlah trip "yang diharapkan" per hari. Ukuran per-hari ini saya pilih sebagai padanan terdekat yang tidak mengarang target.

- `open_questions`: kolom `quantity` tidak dilaporkan ← pilihan agent. Kolom itu ada dan nullable, tetapi satuannya tidak terbaca dari skema maupun dari daftar metrik user. Melaporkannya tanpa tahu satuannya berisiko menampilkan angka yang salah dibaca.

- `actors` dan `entry_points` ← disalin dari pola screen-133; tidak dinyatakan user untuk layar ini.

## v2 — 2026-10-01

**KOREKSI ATAS KESALAHAN AGENT DI v1.** v1 menetapkan metrik "Lama kendaraan berada di pabrik"
yang dihitung dari `dispatch_datetime - arrival_datetime`. **Kedua kolom itu TIDAK ADA.**
Migrasi `2026_08_19_000010` menggabungkan keduanya menjadi satu `record_datetime` lalu
`dropColumn(['arrival_datetime','dispatch_datetime'])` di `up()`. Tiap trip kini menyimpan
TEPAT SATU penanda waktu — waktu datang untuk arus masuk, waktu keluar untuk arus keluar.
Durasi menuntut dua penanda waktu pada trip yang sama; datanya tidak ada, jadi metrik itu
mustahil, bukan sekadar sulit.

Bagaimana kesalahan ini terjadi, supaya tidak terulang: saya meng-grep file migrasi itu dengan
pola `$table->timestamp\('[a-z_]+'` tanpa memisahkan `up()` dari `down()`. Kedua kolom itu
muncul di `down()` — tempat mereka DIPULIHKAN saat rollback — dan saya membacanya sebagai bukti
mereka ada. Ini bentuk kekeliruan yang sama persis dengan `business_unit_id` pada screen-031/033:
membaca satu migrasi, bukan menghitung keadaan efektif setelah seluruh migrasi. Verifikasi yang
benar, dan yang akhirnya saya pakai: pindai SEMUA migrasi yang menyentuh tabel itu, jumlahkan
tambah dan buang hanya dari `up()`, lalu cocokkan dengan `$fillable` model. `$fillable`
WeighbridgeRecord memuat tepat satu kolom waktu: `record_datetime`.

Fakta ini juga sudah saya teruskan ke user dalam daftar kolom "terverifikasi" — jadi argumen yang
mereka setujui ikut memuat kesalahan itu. Koreksinya dilaporkan terbuka, bukan diperbaiki diam-diam.

- `information_displayed`: metrik lama kendaraan di pabrik DIHAPUS (3 butir), diganti sebaran trip per jam + jam tersibuk + jumlah jam kosong (3 butir) ← penggantinya PILIHAN AGENT, bukan permintaan user. Dasarnya: sebaran per jam dapat dihitung dari satu penanda waktu, menjawab pertanyaan operasional yang berdekatan (kapan timbangan menumpuk), dan sudah jadi pola mapan di laporan Cages & Tracks ("sebaran per jam"). Tetap: user tidak pernah memintanya.

- `business_rules`: 3 aturan baru ← (a) keanggotaan periode ditentukan penanda waktu KEJADIAN, bukan `created_at` atau waktu sinkronisasi — diambil dari aturan yang sudah tertulis eksplisit di `CagesTrackReportService::summary()`; (b) lama kendaraan TIDAK dilaporkan, dinyatakan terang-terangan beserta alasannya supaya pembaca berikutnya tidak menyangka itu kelalaian; (c) trip tanpa penanda waktu dikecualikan dan jumlahnya ditampilkan.

- `information_displayed` + `edge_cases`: "jumlah trip yang penanda waktunya kosong" ← turunan agent. `record_datetime` nullable di DB secara sengaja (migrasi menyebut: `->change()` butuh doctrine/dbal yang tidak dipasang, jadi wajib-nya ditegakkan di lapisan aplikasi lewat `['required','date']`). Artinya baris ber-NULL mungkin ada dan akan dikecualikan diam-diam oleh filter periode. Mengikuti preseden wadah "Tanpa grup" di screen-031 — skema mengizinkannya, jadi ditangani walau di dev mungkin nol baris.

- `open_questions` butir ke-4 ← ditambahkan: bila lama kendaraan memang dibutuhkan, yang perlu diubah adalah DATANYA (migrasi + form input mobile dan web + keputusan soal trip lama), bukan laporannya. Dinyatakan supaya kebutuhan itu tidak kembali sebagai permintaan ke laporan.

- `description` ditulis ulang untuk menyatakan ketidakmampuan itu di muka ← pilihan agent; spec yang diam soal apa yang tidak bisa dilakukannya mengundang pertanyaan yang sama berulang kali.
