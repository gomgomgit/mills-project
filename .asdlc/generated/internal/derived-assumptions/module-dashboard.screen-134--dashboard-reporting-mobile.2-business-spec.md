# Derived Assumptions — module-dashboard.screen-134--dashboard-reporting-mobile.2-business-spec

## v1 — 2026-09-23

User menyatakan bentuk layarnya cukup rinci: *"di dalamnya ada 2 pilihan menu yaitu
1. dashboard 2. reporting"*. Yang di bawah ini adalah turunan agent di sekitar pernyataan itu.

- `business_rules[2]` = menekan pilihan yang belum tersedia harus memberi **pesan singkat**, bukan diam ← disalin dari preseden mobile yang sudah berlaku: `StationGrid.vue` sengaja **tidak** memakai atribut `disabled` native, justru `aria-disabled="true"`, karena ketukan pada tile nonaktif tetap harus memunculkan pesan "belum tersedia". Ini berbeda dari perilaku web screen-140, di mana tile nonaktif memang inert. Perbedaan itu disengaja: di layar sentuh, diam tidak dapat dibedakan dari aplikasi yang macet.
- `business_rules[1]` = pilihan yang belum dibangun **tetap ditampilkan nonaktif**, tidak disembunyikan ← konsisten dengan screen-035, screen-140, dan `StationGrid.vue`. User tidak menyebut apa yang terjadi selama layar tujuan belum ada.
- `business_rules[0]` = terbuka untuk **semua** peran yang dapat masuk mobile ← layar ini tidak menampilkan data, jadi tidak ada yang perlu dibatasi; pembatasan sesungguhnya ada di layar tujuan. User tidak menyebut peran sama sekali untuk layar ini.
- `business_rules[3]` = layar tetap terbuka penuh saat **offline** ← konsekuensi dari layar yang tidak memuat data. Disebutkan eksplisit karena Operator bekerja di area tanpa sinyal dan ini yang membedakannya dari layar laporan di baliknya.
- `entry_points[1]` = kembali dari layar Reporting — Pilih Stasiun ← diturunkan dari rantai navigasi, layarnya sendiri belum ada.
- `information_displayed` jejak navigasi "Home / Dashboard & Reporting" ← mengikuti pola `StationListView.vue`; `HomeView.vue` tidak punya breadcrumb karena ia layar tingkat pertama, sedangkan layar ini tingkat kedua.
- `edge_cases` (3 butir) ← seluruhnya turunan agent.
- `test_priority` = `low` ← layar navigasi murni, nol data, nol aturan yang menyangkut hak akses atau cakupan mill. Ini satu-satunya layar dari rangkaian Full Cycle yang memang sesederhana itu.
- `usecase_ids` ← `usecase-134--dashboard-reporting-mobile` sudah terdaftar di usecase-index sejak penetapan scope; artefaknya baru ditulis pada run ini.
