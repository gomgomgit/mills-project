# Derived Assumptions — module-master-data.screen-127--master-data-tree-view.2-business-spec

## v2 — 2026-10-06

- `test_priority` = `high` ← sebelumnya `low` karena layar ini read-only. Sesudah revamp ia punya 14 business rule (ambang "high" adalah 5+) DAN menulis master data yang menjadi fondasi seluruh layar lain; salah tulis di sini berakibat ke mana-mana.
- `information_displayed[0]` = baris ringkasan jumlah per tingkat ← tidak diminta user. Ditambahkan karena tanpa angka itu tidak ada cara membedakan "mill memang hanya lima" dari "sisanya tidak terender".
- `information_displayed[7]` = keterangan tetap bahwa hierarki berhenti di Production Line + tautan ke Kelola Station/Machinery ← tidak diminta user. Ketiadaan yang tidak dijelaskan terbaca sebagai data hilang.
- `information_displayed[6]` = penyaring tunggal menyaring papan DAN kedua bagian ringkas sekaligus, mill tetap tampil bila yang cocok adalah line di dalamnya ← user hanya menyetujui mockup yang memuat kotak `[cari…]`; perilaku cocok-di-anak diturunkan agen.
- `business_rules[9]` = ambang "sekitar 50 mill" sebagai titik wajib meninjau ulang keputusan tanpa paginasi ← ANGKA DIPILIH AGEN. User tidak menyebut ambang apa pun; dicantumkan supaya keputusan "tanpa paginasi" tercatat sebagai pilihan sadar yang punya batas, bukan selamanya.
- `business_rules[7]` = induk yang terisi otomatis tetap terlihat dan tetap dapat diubah ← diturunkan dari `uiux-spec.component_patterns[web-form-input]` (input web tidak pernah disabled/readonly), bukan dinyatakan user untuk layar ini.
- `business_rules[13]` = setiap aksi ikon wajib punya label terbaca pembaca layar + tooltip ← diturunkan dari `uiux-spec.accessibility` level Basic (butir `icon_only_elements`), bukan dinyatakan user.
- `business_rules[12]` = angka ringkasan dan jumlah anak dihitung ulang sesudah tiap aksi ← tidak dinyatakan user.
- `business_rules[10]` = Corporate dan Company tidak boleh diturunkan menjadi tautan saja ← user menyetujui mockup yang memuat baris "Corporate (3) · Company (4) [kelola ▾]"; penegasan bahwa itu harus kelola PENUH, bukan tautan, diturunkan agen dari konsekuensi tata letaknya.
- `edge_cases[0..10]` = seluruh 11 butir ← diturunkan agen; user tidak membahas satu pun kasus tepi.
- `entry_points[1]` = tautan balik dari keempat layar Kelola ← tidak diminta user.
- `open_questions[0..2]` = ketiga butir (penamaan layar, apakah kelak menggantikan layar Kelola, tidak ada pola tipe layar yang cocok utuh) ← diangkat agen.
- Pemecahan menjadi EMPAT usecase (lihat / tambah / ubah / hapus) ← pilihan agen. User hanya meminta "kalau bisa dibuat bisa manage data juga"; pemecahan per operasi dipilih supaya tiap usecase punya satu alur yang koheren alih-alih satu usecase berisi 12 jalur.
- Field modal dikelompokkan menjadi Identitas / Kontak / Alamat ← pilihan agen; Corporate dan Company masing-masing punya ~13 field teks, dan satu daftar panjang tanpa kelompok bertentangan dengan permintaan "design yang bagus".
- Penanda pengganti logo = inisial nama mill ← pilihan agen.

## v3 — 2026-10-06

- `business_rules[2]` DIKOREKSI. Versi v2 berbunyi "halaman ini tidak boleh memiliki aturan validasinya sendiri" — terlalu absolut, dan implementasinya akan melanggarnya sendiri. Dibaca langsung di kode: keempat layar Kelola memang mencerminkan aturannya di lapisan komponen (`KelolaCorporate`/`KelolaCompany`/`KelolaBusinessUnit` lewat `rules()`, `KelolaProductionLine` lewat `buildValidator()`) lalu service memvalidasi ulang dan `ValidationException`-nya dipetakan ke kunci `form.<field>`. Pola dua lapis itu disengaja (*defense in depth*, dikomentari di `KelolaCorporate::save()`). Aturannya diperjelas: service adalah OTORITAS, lapisan antarmuka hanya cermin untuk galat per-field, dan selisih di antara keduanya adalah cacat yang harus diperbaiki — bukan dua aturan yang sama-sama berlaku.
- Koreksi ini ditemukan saat Phase 3 membaca kode, bukan diminta user.

## v4 — 2026-10-06

- `business_rules[9]` DIGANTI atas KEPUTUSAN USER, bukan turunan agen: "paginasi dipakai bila perlu". Versi v2 menyatakan "tanpa paginasi" sebagai keputusan sadar dengan ambang tinjau ulang ~50 mill — dan ambang itu memang angka karangan agen (tercatat di v2). Sekarang ambangnya bukan lagi titik untuk meninjau ulang, melainkan titik di mana paginasi mulai bekerja sendiri.
- Yang TIDAK diubah user dan tetap berlaku: lipat/buka tetap tidak dipakai. Alasannya dibedakan secara eksplisit di spec — lipat/buka menyembunyikan data yang SUDAH termuat, paginasi membatasi apa yang DIMUAT.
- Turunan agen dari keputusan itu, bukan dinyatakan user: (a) `perPage` = 20 mengikuti keempat layar Kelola, dipilih supaya tidak melahirkan angka kelima; (b) Production Line TIDAK ikut dipaginasi, karena memotong daftar line di dalam satu kartu membuat kartu itu berbohong tentang isi mill-nya; (c) ketiga edge case baru (hasil saring melintasi halaman, mengubah penyaring dari halaman selain pertama, menghapus entri terakhir di halaman terakhir); (d) keterangan posisi halaman wajib menyebut jumlah total.
- Konsekuensi teknis yang ikut berubah di tech spec v3, juga turunan agen: penyaringan PINDAH dari PHP ke basis data. Alasannya bukan performa melainkan kebenaran — menyaring sesudah paginasi membuat kecocokan di halaman lain terbaca sebagai "tidak ditemukan".
