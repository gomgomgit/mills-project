
## v1 — 2026-10-22

- test_priority = high ← lebih dari 5 aturan bisnis dan banyak actor dengan tingkat izin berbeda (Supervisor/Mill Management dibatasi mill sendiri)
- layar terpisah dari Kelola Shift, diakses lewat tab/tombol ← keputusan user setelah usulan agen (2026-10-22)
- Production Line WAJIB dipilih, tanpa opsi 'semua line' ← diturunkan agen dari konvensi "line adalah konteks yang DIPILIH" dan keterbacaan grid bulanan; user tidak menyebut
- shift lintas tengah malam dikaitkan ke TANGGAL MULAI ← tidak disebut user; aturan tunggal agar satu shift malam tak muncul di dua baris
- tanggal lampau boleh diubah tanpa penguncian ← tidak disebut user; asumsi karena roster sering dikoreksi (jadi open question)
- penugasan murni informasional, tak memengaruhi hak input record ← tidak disebut user
- pintasan 'Terapkan ke beberapa tanggal' dan 'Salin dari bulan sebelumnya' ← ditambahkan agen agar sebulan penuh tidak perlu diisi sel demi sel; user tidak memintanya
- 'Salin' mempertahankan sel yang sudah berisi (tidak menimpa) ← pilihan agen yang paling aman
- calon peserta ditentukan oleh role yang DIPILIH saat user dibuat (assignedRole), bukan role efektif ← konsekuensi keputusan role setara Admin 2026-10-22; user hanya berkata "Operator dan Supervisor"
- kolom shift yang sudah dihapus tampil read-only sebelum tanggal penutupan ← penerjemahan agen atas "tidak boleh kehilangan histori"
- user nonaktif tetap tampil di penugasan lama dengan keterangan 'nonaktif' ← tidak disebut user
- konflik dua pengatur: last-write-wins dengan pesan penyegaran ← tidak disebut user
- hak akses: Admin + 7 role setara Admin + Supervisor + Mill Management; Supervisor/Mill Management hanya mill sendiri ← dari jawaban user; pembatasan mill sendiri mengikuti aturan layar lain
- Operator tidak punya layar ini ← tidak disebut user; mengikuti batas akses web Operator

## v2 — 2026-10-22

- dua tampilan 'Kalender | Tabel' dengan Kalender sebagai bawaan ← pilihan A user; 'bawaan' = Kalender adalah penurunan agen dari "kalender jadi tampilan utama"
- klik sel tabel / kotak kalender membuka kartu tanggal di samping, tanpa tombol 'Atur' ← permintaan user
- kartu tanggal memuat SEMUA shift pada tanggal itu; pada klik sel tabel, shift yang diklik terbuka lebih dulu ← penafsiran agen atas "card tanggal tersebut muncul di samping"
- sel/kotak shift tertutup membuka kartu read-only, bukan tidak berbuat apa-apa ← keputusan agen agar histori tetap terbaca
- layar sempit: kalender jatuh ke daftar per minggu ← keputusan agen; user tidak menyebut
- pindah bulan menutup kartu; klik tanggal lain dengan perubahan belum disimpan meminta konfirmasi inline ← keputusan agen
- tampilan terakhir yang dipilih diingat per pengguna di peramban ← keputusan agen (kenyamanan, tanpa efek data)
- skenario BDD baru (10) di usecase-177 ditulis langsung oleh agen utama, bukan lewat bdd-spec-writer-agent ← pengecualian prosedur demi kecepatan; hanya menambah, tidak menghapus
