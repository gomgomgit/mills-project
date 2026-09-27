# Derived Assumptions Log — module-master-data.screen-142--detail-periode-pelaporan.2-business-spec

## v1 — 2026-09-27

Layar baru; seluruh isinya diturunkan dari (a) keputusan user "daftar stasiun pindah ke halaman
detail, aksi per stasiun hanya di sana", (b) perilaku kode yang sudah berjalan
(`PeriodService`, `PeriodClosureService`, blade layar daftar), dan (c) pola `detail-*` yang
sudah mapan di `module-web-station-data`.

- `entry_points` memuat "URL langsung /master-data/periods/{id}" selain tautan dari daftar ← turunan agen; user hanya menyebut masuk dari screen-128. Ditambahkan karena rute ber-{id} selalu bisa di-bookmark, dan itu memaksa edge case "periode tidak ditemukan" ditangani
- Kolom tabel stasiun = Jenis Stasiun, Status, Ditutup Oleh, Waktu Ditutup, Aksi ← kutipan apa adanya dari sub-tabel accordion yang lama, jadi tata letaknya pindah tanpa kehilangan kolom
- Aksi per baris mengikuti status (Draft→"Buka Stasiun", Terbuka→"Tutup Stasiun", Tertutup→"Buka Kembali") ← dari blade + docblock `PeriodClosureService::open()`; nama tombolnya dipertahankan persis
- `business_rules` "status_summary tidak boleh dipakai memutuskan aksi per stasiun" ← turunan agen dari docblock `statusSummary()`; dicatat sebagai aturan karena itu jenis bug yang mudah masuk saat implementasi
- edge case "jenis stasiun yang sudah dipensiunkan tetap punya baris dan diurutkan paling belakang" ← turunan agen dari `stationTypeSortOrder()` + aturan never-remove pada backfill; tidak pernah dibahas user
- edge case "lubang backfill" beserta jalan keluar manual ← diminta user secara eksplisit; rumusan detailnya (mengapa update() menolak lebih dulu) dari docblock `update()`
- `open_questions` berisi satu entri tentang aksi "sinkronkan stasiun" add-only ← turunan agen; itu perbaikan tuntas yang disebut docblock update() sebagai usecase baru, dan belum diputuskan user
- `test_priority` = high ← sejajar screen-128, karena layar ini memegang aksi yang mengunci angka laporan
