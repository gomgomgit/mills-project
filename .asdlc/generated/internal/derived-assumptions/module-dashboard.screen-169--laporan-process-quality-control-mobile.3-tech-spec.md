## v1 — 2026-10-23

- route = `report-process-quality-control` pada path `/reports/process-quality-control`, meta.public false ← business spec hanya menyebut "dicapai dari Reporting"; bentuk rute diturunkan dari konvensi 14 laporan mobile sebelumnya
- NOL endpoint baru dan NOL entri api-index ← business spec menyatakan "keempat endpoint dipakai ulang"; konsekuensinya layar ini tidak boleh menyentuh `backend/` sama sekali, dan itu dinyatakan sebagai implementation note supaya tidak ada yang "melebarkan akses" yang sudah lebar
- Mill Management dan Admin ditandai can_access true meski tidak ada entri menu mobile ← server memang menerimanya; penolakan praktisnya datang dari ketiadaan jalur, bukan dari penjaga peran. Menambah penjaga peran di router adalah perubahan se-router yang menyentuh 15 layar laporan — dinyatakan di luar cakupan
- Daftar kelima blok diturunkan dengan menelusuri kunci `metrics` dan mengambil `group` yang belum terlihat ← business spec menyatakan "urutan blok MILIK SERVER"; satu-satunya cara memenuhinya tanpa larik nama blok di klien
- Penanda data pada judul kartu = `some(reading_count > 0)`, bukan `every` ← business spec hanya menyebut "penanda apakah blok itu punya data". `every` akan menandai Final Storage kosong hanya karena DOBI tak pernah diisi, yang justru kebalikan dari maksudnya
- Tata letak per metrik BERTUMPUK (label di atas, tiga angka + penyebut + satuan di bawah) ← business spec menuntut 36 angka terbaca pada lebar ponsel tanpa halaman bergeser; tiga kolom sejajar tidak dapat memenuhinya
- Tabel rekap menggulir MENDATAR (overflow-x), berbeda dari web yang menggulir vertikal (max-height + overflow-y) ← business spec mobile menyebut "menggulir mendatar DI DALAM kartunya"
- Keadaan lipat TIDAK disimpan ke penyimpanan perangkat ← business spec menyatakan keadaan lipat "tidak memengaruhi angka apa pun"; menyimpannya menambah state yang harus dimigrasi tanpa manfaat yang diminta
- Luring sesudah muat MENGHAPUS kartu blok dari DOM, bukan melipatnya ← business spec: "kelima blok ditutup seluruhnya, bukan dibiarkan terlipat dengan isi basi". Dirender sebagai penghapusan karena kartu terlipat berisi angka lama tidak dapat dibedakan dari kartu terlipat berisi angka baru
- Tombol ekspor dikunci selama permintaan berjalan ← business spec menyebut "satu unduhan, satu permintaan" untuk dua tekan cepat; penguncian adalah mekanismenya
- Ekspor mengirim 'csv' secara EKSPLISIT alih-alih mengandalkan default server ← business spec "ekspor hanya CSV"; nilai eksplisit membuat asersi 'nol string excel/xlsx di berkas klien' dapat ditegakkan
- test scenarios diturunkan: 37 skenario (satu per bdd_scenario Phase 2), 45 unit test case
- CATATAN PELAKSANAAN: test_generation_level = 'none', jadi test_scenarios di atas TIDAK dieksekusi menjadi berkas uji pada Phase 4
