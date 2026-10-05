# Derived Assumptions — module-dashboard.screen-135--laporan-sterilizer-mobile.2-business-spec

## v1 — 2026-09-23

User menetapkan dua hal secara eksplisit: laporan mobile memakai ulang 4 endpoint
`/api/sterilizer-reports/*` yang sudah ada (bukan endpoint terpisah), dan Operator
ditambahkan ke daftar peran endpoint itu. Sisanya turunan agent.

- `business_rules[5]` = "angka berasal dari sumber yang sama dengan laporan versi web; tidak boleh ada perhitungan kedua yang berdiri sendiri" ← ini alasan produk di balik keputusan teknis user, ditulis sebagai aturan agar tidak hilang. Dua perhitungan terpisah pasti menyimpang tanpa ketahuan — dan sesi ini sudah dua kali menemukan kelas bug yang persis begitu.
- `business_rules[2]` = Operator/Supervisor/Mill Management terikat mill akunnya dan **tidak dapat memaksanya lewat permintaan** ← disalin dari perilaku `resolveBusinessUnit()` yang sudah berjalan. Penting ditulis sebagai aturan bisnis karena melebarkan endpoint ke Operator berarti menambah satu peran lagi yang mungkin mencoba.
- `business_rules[4]` = status periode tidak membatasi apa pun ← konsisten dengan screen-129. Kunci periode mengatur penulisan, bukan pembacaan.
- `business_rules[7,8]` = rata-rata hanya dari siklus berdurasi (jumlah yang dikeluarkan ditampilkan), dan pencilan pakai metode kuartil dengan ambang ditampilkan ← disalin dari keputusan screen-129 agar kedua layar bercerita sama. Bila salah satu diubah kelak, keduanya harus diubah bersama.
- `business_rules[9]` = kegagalan jaringan harus dinyatakan jelas beserta cara mencoba lagi ← **inilah perbedaan terpenting dari screen-134**. screen-134 nol pemanggilan data sehingga offline tidak berpengaruh; layar ini justru bergantung jaringan, dan penggunanya Operator yang kerap tanpa sinyal. Diam di layar ini jauh lebih merusak daripada di layar mana pun.
- `edge_cases` (10 butir) ← seluruhnya turunan. Empat di antaranya menyalin cabang yang sudah terbukti di screen-129 (periode kosong, seluruh durasi kosong, sampel kurang dari ambang, durasi seragam); empat lain khas mobile (jaringan gagal, sesi berakhir, rekap panjang di layar sempit, akun tanpa mill).
- `entry_points` = lewat menu Reporting setelah memilih stasiun, dan sementara ini lewat rute langsung ← screen-141 belum ada. Ini yang membuat layar ini sementara tidak punya jalur navigasi dari dalam aplikasi.
- `test_priority` = `high` ← 10 aturan (ambang 5+), empat peran dengan perilaku cakupan mill berbeda, dan layar ini menjadi satu-satunya tempat Operator melihat hasil kerjanya. Salah menerapkan aturan cakupan mill berarti Operator melihat mill lain.
- `usecase_ids` ← `usecase-135--laporan-sterilizer-mobile` sudah terdaftar di usecase-index sejak penetapan scope; artefaknya baru ditulis pada run ini.

## v2 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit ee5294c, d5da9cf), code is truth (mobile/src/views/LaporanSterilizerView.vue, mobile/src/utils/latestRequest.ts, tests/laporanStaleResponse.spec.ts, tests/e2e/laporan-stale-response.spec.ts).
- edge_cases ← ganti pilihan cepat: hanya ringkasan pilihan terakhir yang tampil (respons basi diabaikan); nama berkas ekspor milik periode yang diekspor + Ekspor terkunci Mengekspor….
- ⚠ Chip Mill/Production Line (e4f231e) tidak memerlukan perubahan teks spec — information_displayed sudah menyebut keterangan mill/line; testid tetap.
