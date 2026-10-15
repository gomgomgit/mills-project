# Derived Assumptions — module-dashboard.screen-159--laporan-engine-room-mobile.3-tech-spec

## v1 — 2026-10-15

Spec teknis diturunkan dari screen-158 (web, baru selesai) + pola screen-157 (Effluent Plant mobile). Yang tercatat hanya pilihan yang tidak ditetapkan sumber mana pun.

- **NOL endpoint baru dan NOL entri api-index baru** ← diverifikasi: ketiga rute `/api/engine-room-reports/*` sudah menerima Operator sejak commit pertama screen-158, dan `/api/production-lines/options-for-report` dipakai ulang verbatim dari screen-135. Ini buah dari keputusan di screen-158 untuk menerima Operator lebih awal: screen-157 harus mengerjakan pelebaran aksesnya sendiri di putaran mobile, screen-159 tidak.
- **Peta `REPORT_ROUTES` mobile cukup DI-APPEND** ← dibaca langsung dari `ReportingPilihStasiunView.vue`: peta itu tidak berurut `sort_order` dan tidak punya asersi urutan, berbeda dari `StationReportService::REPORT_ROUTES` di web yang urutannya load-bearing. Ikon `'engine-room'` sudah ada di berkas itu.
- **Resolusi line berurutan: query rute → ingatan perangkat → minta memilih, TANPA langkah "pilih line pertama"** ← pilihan agent. Memilih line secara diam-diam menerbitkan angka yang pengguna tidak pernah minta.
- **Klien TIDAK memvalidasi kepemilikan line; ia bersandar pada 403 server** ← pilihan agent. Memvalidasinya di klien menduplikasi aturan otorisasi di dua tempat yang dapat berselisih.
- **403 pada `/summary` diperlakukan sebagai "pilih line lagi", bukan galat jaringan** ← turunan agent; line basi dari ingatan perangkat adalah penyebab paling umum 403 di layar ini, dan menampilkannya sebagai galat jaringan menyesatkan.
- **Keterangan genset dirender DUA KALI, sekali di dalam tiap blok** ← dan asersinya MENGHITUNG kemunculannya, bukan sekadar memastikan ia ada. Mengasersi "ada" akan lolos pada implementasi yang menaruhnya sekali di atas halaman — yaitu persis cacat yang dihindari, karena di ponsel keterangan di atas halaman sudah tergulir jauh ketika blok yang diterangkannya terlihat. Ini keputusan test yang paling mudah salah di layar ini.
- **Keenam blok dirender terbuka tanpa kontrol lipat** ← pilihan agent; melipatnya menyembunyikan penyebut tiap kartu.
- **Pemetaan `expected_slots` 0 → '—' dinyatakan sebagai PRESENTASI, bukan aritmetika** ← perlu dinyatakan eksplisit supaya aturan nol-hitungan tidak terbaca melarangnya.
- **`statsOf`/pembulatan TIDAK disinggung di spec ini** ← sengaja: pembulatan terjadi di server (3 desimal, keputusan screen-158), dan ponsel merender apa adanya. Menyebut pembulatan di sisi klien akan mengundang orang membulatkan ulang.
- **Kode galat yang dipakai hanya 200/403/422 pada `test_scenarios`** ← 404 dan 401 tercakup di `edge_case_handling` dan `unit_test_cases`, bukan sebagai rantai `api_test`, karena keduanya keadaan sesi/periode yang tidak punya rantai permintaan yang bermakna di layar ini.
- **`test_scenarios` = 39 entri, satu per `bdd_scenarios` usecase-165, berurutan** ← diverifikasi cocok persis dan berurutan; nol endpoint asing; komponen `LaporanEngineRoomView` kecuali skenario penolakan peran yang memakai `ReportingPilihStasiunView`; rute browser selalu `/reports/engine-room`.
- **73 `unit_test_cases`** ← mencakup repo (parsing, parameter, nol-aritmetika, peta label + jalur mundur), resolusi line (4 kasus termasuk try/catch dan "tidak memilih pertama"), penjagaan fungsi-pemuatan (3 jalur: awal, ganti periode, coba lagi), pemetaan galat (401/403/404/422 yang tidak boleh tertukar), dan view (kartu, blok, sebaran, kartu energi, kelengkapan, rekap, ekspor, router).

### Riak Phase 4 yang sudah dipetakan (hasil pembacaan kode, bukan asumsi)

- `mobile/tests/ReportingPilihStasiunView.spec.ts` sekitar baris 830 memakai Engine Room sebagai contoh "stasiun belum berlaporan", dan komentarnya memprediksi kegagalan ini. **Pemindahan KETUJUH.** Konvensi tertulis di berkas itu: `sort_order` tertinggi di antara yang belum terpakai → **Kernel Dispatch (160)**. Docblock kepala berkas yang mendaftar riwayat pemindahan juga perlu diperbarui.
- **Catatan struktural, di luar cakupan screen-159:** sesudah sepuluh layar sisa (screen-160..169), tidak akan ada jenis stasiun nyata yang dapat menjadi contoh "belum berlaporan" — test itu menjadi mustahil dipenuhi. Perbaikan yang benar adalah melepaskannya dari jenis stasiun nyata, dan itu paling murah dikerjakan sebelum tiga pemindahan berikutnya.
