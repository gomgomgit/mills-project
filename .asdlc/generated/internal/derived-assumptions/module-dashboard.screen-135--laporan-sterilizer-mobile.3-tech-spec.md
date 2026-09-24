# Derived Assumptions — module-dashboard.screen-135--laporan-sterilizer-mobile.3-tech-spec

## v1 — 2026-09-23

- **Nol endpoint baru** ← user menetapkan pakai ulang; yang diturunkan agent adalah konsekuensinya: `api_contracts[0].endpoints` menyalin keempat endpoint screen-129 apa adanya, dan api-index tidak bertambah entri — hanya `actor_ids` keempatnya yang ditambah `actor-station-operator`.
- **Rute WEB tidak ikut dilebarkan** ← user bilang "tambahkan Operator ke endpoint yang ada". Yang dilebarkan hanya 4 rute API; `/reports/sterilizer` versi web tetap menolak Operator karena Operator memang tidak punya UI web. Pembedaan ini turunan agent, dan penting: melebarkan rute web akan memberi Operator halaman yang tidak pernah dirancang untuknya.
- **`resolveBusinessUnit()` tidak boleh diubah** ← ia sudah memperlakukan seluruh peran non-Admin dengan benar (mill dari akun, parameter klien diabaikan). Menambahkan `operator` ke daftar peran rute berarti Operator ikut masuk cabang itu **tanpa satu baris kode baru**. Ditulis eksplisit karena godaan untuk "menambahkan cabang operator" akan besar.
- **Akun tanpa mill: nol pemanggilan endpoint, termasuk tidak memanggil `options`** ← gagal tertutup di sisi klien, sejajar dengan perilaku server di screen-140. Kalau klien tetap memanggil `options`, daftar seluruh mill terbentuk di memori peran yang seharusnya terikat satu mill. Ada unit test yang mengasersi nol pemanggilan.
- **Nol perhitungan ulang di sisi klien** ← seluruh angka dipetakan apa adanya dari respons. Ada unit test khusus untuk ini. Inilah yang secara teknis menjamin aturan bisnis "angka mobile tidak boleh berbeda dari web" — tanpa test itu, aturannya hanya niat.
- **`avg/min/max duration` null ditampilkan "tidak tersedia", bukan 0** ← menampilkan 0 akan terbaca sebagai fakta yang salah: "rata-rata 0 menit" berbeda arti dari "tidak ada durasi tercatat".
- **Ambang tetap ditampilkan meski tidak ada pencilan** ← ambang adalah informasi, bukan sekadar penanda. Pengguna perlu tahu batas yang dipakai untuk menilai bahwa memang tidak ada yang menyimpang.
- **401 diteruskan ke penjagaan sesi, bukan diperlakukan sebagai kegagalan jaringan** ← keduanya sama-sama "permintaan gagal", tetapi jalurnya harus berbeda; mencampurnya akan membuat sesi kedaluwarsa tampil sebagai "periksa koneksi Anda".
- **Periode terpilih dipertahankan saat jaringan gagal** ← pengguna tidak perlu mengulang dari awal. Diuji eksplisit karena mudah hilang saat state di-reset pada penanganan galat.
- **Rekap harian tertutup secara bawaan** ← periode sebulan berarti 30 baris yang menenggelamkan isi lain di layar ponsel.
- **Dua** `test_scenario` ber-`api_test` kosong (akun tanpa mill, jaringan gagal) ← keduanya dibuktikan justru oleh ketiadaan panggilan HTTP. Agent sempat menulis "tiga"; angka yang benar dua, karena "Sesi berakhir" memang punya langkah 401.
- `data-testid` (25 penanda) ← ditetapkan agar component test dan browser test punya pegangan stabil; bukan permintaan user.
