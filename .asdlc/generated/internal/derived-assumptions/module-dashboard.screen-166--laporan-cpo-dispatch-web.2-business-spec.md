# Derived Assumptions — module-dashboard.screen-166--laporan-cpo-dispatch-web.2-business-spec

## v1 — 2026-10-18

- Seluruh isi DITURUNKAN dari skema `cpo_dispatch_details` dan dari pola tujuh belas laporan periode sebelumnya, bukan dari wawancara.
- SEBARAN PER JAM dan WAKTU PUTAR TANKER diangkat sebagai bagian utama layar ← diturunkan dari keberadaan `time_in`/`time_out`, yang membuat stasiun ini SATU-SATUNYA laporan pengiriman yang dapat menjawab pertanyaan waktu. Pengguna tidak meminta keduanya.
- Sebaran jam dihitung menurut JAM MASUK, bukan jam keluar ← keputusan saya; yang ditanyakan adalah kapan tanker datang, karena itulah yang membebani jembatan timbang.

## Keputusan terbesar, dan ia TAFSIR bukan pembacaan

- `time_in` dan `time_out` bertipe `time` TANPA TANGGAL (diverifikasi di migrasi), sehingga jam keluar yang lebih kecil daripada jam masuk menghasilkan selisih NEGATIF. Keputusan saya: dibaca sebagai HARI BERIKUTNYA (+24 jam), bukan sebagai data salah — tanker memang berangkat melewati tengah malam. Bentuk masalahnya SAMA dengan shift melewati tengah malam yang sudah diselesaikan di `ShiftService`.
- DAN jumlah pengiriman yang ditafsirkan begitu DITERBITKAN tersendiri ← keputusan saya, dan ini yang paling penting: pembaca harus dapat melihat berapa banyak angka waktu putar yang lahir dari PENAFSIRAN alih-alih dari pembacaan langsung. Tafsir yang tidak terlihat adalah tafsir yang tidak dapat dibantah.
- Waktu putar yang tampak tidak wajar TIDAK dipotong dan TIDAK dibuang ← keputusan saya; tidak ada master batas kewajaran, sehingga memilih batas berarti mengarangnya. Layar menyatakan bahwa angka seperti itu patut DIPERIKSA PEMBACA, bukan diputuskan laporan.
- Jam masuk = jam keluar → waktu putar NOL, dilaporkan apa adanya ← keputusan saya; bukan dibuang dan bukan dibaca menyeberang tengah malam. Nol adalah angka, bukan ketiadaan.

## Lanjutan

- EMPAT PENYEBUT BERBEDA dinyatakan di layar ← diturunkan dari nullability independen `net_weight_mt`, `time_in`, dan `time_out`. Satu angka 'jumlah pengiriman' yang dipakai sebagai penyebut semuanya akan salah sistematis. Tidak ada preseden; laporan sebelumnya paling banyak punya dua.
- Sebaran jam punya kelompok 'jam tidak tercatat' TERSENDIRI ← diturunkan dari `time_in` nullable; sejajar dengan baris 'tidak tercatat' pada rekap kategoris, dan menjaga penjumlahan sebaran tetap sama dengan jumlah pengiriman.
- DOBI dinyatakan sebagai INDEKS TANPA SATUAN ← DIVERIFIKASI: kolomnya `float`, form merendernya `<input type="number" step="0.01">`, dan tidak ada satuan di mana pun. Memberinya satuan akan salah.
- Ketiadaan rekap berlabel DINYATAKAN di layar ← DIVERIFIKASI: satu-satunya `<select>` di form CPO adalah pemilih production line, jadi stasiun ini NOL kolom berpilihan tertutup — kebalikan dari Kernel Dispatch. Dinyatakan karena pembaca yang datang dari laporan itu akan mencarinya.
- TIGA rekap kategoris (perusahaan pengangkut, tangki sumber, pembeli tujuan) ← saya pilih ketiga kolom teks itu dari skema.
- `storage_tank_source` diperlakukan sebagai TEKS ← DIVERIFIKASI: `string` nullable, form `<input type="text">`, tanpa FK.
- TIGA penghitung kepatuhan dan kedua segel tidak pernah dijumlahkan ← pola yang sama dengan screen-164.
- Ekspor membawa jam masuk dan jam keluar APA ADANYA, TANPA penafsiran menyeberang tengah malam ← keputusan saya: ekspor adalah rincian mentah, dan penafsiran adalah pekerjaan laporan. Memasukkan tafsir ke ekspor membuat dua sumber angka yang dapat menyimpang.
- Nama segel `seal_no_top`/`seal_no_bottom` TANPA awalan `security_` ← DIVERIFIKASI di migrasi; BERBEDA dari kernel-dispatch. Menyalin nama lintas stasiun akan ter-compile dan tidak pernah cocok.
- Operator timbang `weighbridge_operator` TANPA `_id` ← DIVERIFIKASI; berbeda dari kernel-dispatch.
- `test_priority` = high ← 29 aturan, 23 kasus tepi, dan satu keputusan yang bersifat tafsir.
- Kedua `open_questions` dibiarkan TERBUKA, bukan diputuskan sendiri: ketiadaan master batas kewajaran waktu putar dan ketiadaan master ambang mutu. Keduanya BATAS DATA, bukan pilihan laporan — dan pemilik produk mungkin mengharapkan penandaan itu ada.
