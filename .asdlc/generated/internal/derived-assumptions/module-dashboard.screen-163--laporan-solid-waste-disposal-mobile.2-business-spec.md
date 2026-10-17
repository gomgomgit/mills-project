# Derived Assumptions — module-dashboard.screen-163--laporan-solid-waste-disposal-mobile.2-business-spec

## v1 — 2026-10-18

- Seluruh isi layar ini DITURUNKAN dari screen-162 (pasangan web) yang baru selesai, bukan dari wawancara baru. Pengguna hanya menyatakan "selesaikan laporan stasiun sampai semua stasiun selesai"; pembagian web/mobile per stasiun sudah ada di screen-index sejak lama.
- `actors` memasukkan Operator sebagai PENGGUNA, bukan sekadar diizinkan ← diturunkan dari pola screen-159 dan screen-161; pengguna tidak membahas siapa pembaca laporan mobile.
- Pintu masuk tunggal lewat tile di Reporting (screen-141) ← pola keenam laporan mobile sebelumnya.
- Luring MENUTUP angkanya (bukan sekadar spanduk) ← penyimpangan sadar dari preseden Engine Room, diambil di screen-161 dan diteruskan ke sini. Pengguna tidak membahas perilaku luring.
- Bagian rekap dapat dilipat ← keputusan tata letak untuk layar sempit; tidak diminta.
- Ekspor di mobile hanya CSV (tanpa Excel) ← pola keenam laporan mobile sebelumnya; pengguna tidak menyebut format.
- Urutan rekap dari total netto TERBESAR, sehingga kelompok 'tidak tercatat' bertonase besar muncul di ATAS ← diwarisi dari screen-162 sesudah koreksi di sana; dasarnya preseden kode `WeighbridgeReportService::groupedBreakdownOf()`.
- Line yang diingat perangkat dipakai tanpa diminta memilih lagi ← pola screen-161.
- `test_priority` = high ← diturunkan dari jumlah aturan (20) dan dari fakta bahwa angkanya wajib identik dengan web; pengguna tidak menilai prioritas.
- `open_questions` KOSONG ← seluruh pertanyaan terbuka sudah terjawab saat mengerjakan screen-162; tidak ada yang tersisa khusus untuk platform mobile.
- TIDAK ada entri tentang "muatan tanpa tanggal" sebagai keadaan operasional ← sengaja dihilangkan dari spec ini: kolom tanggal kejadian NOT NULL di skema, keadaannya mustahil, dan spec screen-162 versi pertama keliru memasukkannya.
