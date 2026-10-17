# Derived Assumptions — module-dashboard.screen-163--laporan-solid-waste-disposal-mobile.3-tech-spec

## v1 — 2026-10-18

- NOL endpoint baru — keempat endpoint screen-162 dipakai ulang apa adanya ← diturunkan dari pola screen-158/159 dan screen-160/161, dan dari fakta terverifikasi bahwa Operator sudah diterima di ketiga rute data sejak commit pertama screen-162.
- `route` = `/reports/solid-waste-disposal`, nama rute `report-solid-waste-disposal` ← pola router mobile untuk keenam laporan sebelumnya.
- Tidak ada penjagaan peran di router mobile ← fakta terverifikasi di screen-161: router itu hanya punya satu penjaga global `meta.public`; pembatasan peran ada di server.
- Klien TIDAK menghitung satu agregat pun; ia membaca `/summary` dan memilih field ← keputusan saya, dan inilah yang MENJAMIN angka mobile = angka web. Pengguna tidak membahasnya.
- Urutan baris milik SERVER; klien tidak mengurutkan ulang ← keputusan saya, dengan alasan baris yang sama akan tampil berbeda di dua platform bila klien ikut mengurutkan.
- Prosa `_note_*` dan `compliance._note` dirender UTUH tanpa parafrase ← keputusan saya; memparafrasekannya berarti membuat dua penjelasan untuk satu keadaan.
- null dicetak '—' DIPERIKSA PER FIELD, bukan lewat default `?? 0` tunggal ← keputusan saya; default tunggal akan mengubah 'tidak diketahui' menjadi 'tidak ada beban' di seluruh layar sekaligus.
- `undated_load_count` dirender hanya ketika > 0 ← diwarisi dari koreksi screen-162 (kolom NOT NULL).
- Penjagaan "jangan panggil /summary tanpa line" ditempatkan di SATU fungsi pemuat, bukan di pemanggilnya ← keputusan saya; terbukti perlu di screen-161, tempat jalur 'ganti periode' dan 'coba lagi' masing-masing dapat melewatinya.
- Ekspor mobile hanya `format=csv` ← pola laporan mobile sebelumnya.
- Riak `ReportingPilihStasiunView.spec.ts` diselesaikan dengan KODE FIKTIF, bukan pemindahan ← KOREKSI atas catatan screen-161 yang menyatakan kolam kandidat habis. Catatan itu dibuat tanpa memeriksa bagaimana spec itu memperoleh daftar stasiunnya: ia MENYUNTIKKAN daftarnya sendiri lewat `makeStation(...)` + `getActiveAndPlaceholderStationsMock.mockResolvedValue(...)`, sehingga jenis stasiun fiktif memang tersedia di sana.
- Ikon 'solid-waste-disposal' sudah ada di STATION_ICONS ← diverifikasi di berkas, bukan diasumsikan.
