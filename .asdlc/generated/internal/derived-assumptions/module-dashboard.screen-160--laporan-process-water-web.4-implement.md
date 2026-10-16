# Derived Assumptions — module-dashboard.screen-160--laporan-process-water-web.4-implement

## v1 — 2026-10-16

Nilai yang diturunkan agen (bukan dinyatakan spec maupun pemilik produk), beserta alasannya.

### Yang menyentuh kode produksi

- **`DETAIL_FIELDS` ikut dibuka ke `public`** (brief hanya menyebut `READING_FIELDS` dan `isRowFilled()`) ← ekspor wajib menerbitkan 13 kolom DALAM URUTAN `DETAIL_FIELDS`, dan itu tak terbaca selagi `protected`. Alternatifnya mendaftar ulang 13 nama kolom di report service — duplikasi yang justru dilarang spec untuk `READING_FIELDS`.
- **`.md-explain`, bukan `.md-note` yang digambar mock** ← di partial produksi `.md-note` adalah inline-flex 12px satu baris; paragraf di dalamnya runtuh. `.md-explain` punya peran visual yang digambar mock dan sudah dipakai screen-133/156/158. Pembungkus `.md-trendchart__track` milik mock dibuang karena tak berdefinisi di CSS terkompilasi.
- **`''` → `null` untuk kesembilan kolom numerik** ← string kosong di-cast float menjadi `0.0` dan menyeret rata-rata. Lihat [[project-sqlite-vs-postgres-trap]].
- **`inspector_id` di-trim tepi; whitespace-only → `not_recorded`; nol case-folding** ← dua baris untuk satu kode meniadakan guna rekapnya.
- **Baris rekap inspector diurutkan menurut KODE (`SORT_NATURAL`), bukan jumlah slot** ← mengurutkan menurut volume menjadikannya peringkat orang, yang dilarang spec. Kunci urutnya dinyatakan di catatan layar.
- **`metrics` tepat 9 kunci, `text_entries` tepat 2** ← `_note` semu pada `success_schema` tidak diterbitkan karena `unit_test_cases` menuntut tepat 9; catatannya pindah ke `_note_absences` / `coverage._note_filled_rule` di tingkat atas.
- **Prosa payload sengaja menghindari token yang ketiadaannya diasersi** (`status_distribution`, `downtime`, `shift`, `threshold`) ← nama kolom sungguhan hidup di LAYAR, tempat spec memang memintanya. Karena asersi ketiadaan memakai NAMA KUNCI, ini tidak menciptakan risiko hijau-palsu.
- **Tanpa kartu "Kelengkapan Record"** ← mock tidak punya dan `information_displayed` tidak menyebutnya (engine-room punya). `total.days_with_records` dan `total.reading_rows` tetap tampil di footer gabungan rekap per-unit.

### Yang menyentuh bentuk test

- **Asersi `::option()` membuang komentar lebih dulu lewat `token_get_all()`** ← frasa "Tidak ada ::option() di layar ini" muncul verbatim di empat docblock yang justru membuktikan klaimnya; grep mentah akan memerah pada prosa yang benar. Lihat [[feedback-vacuous-assertions]].
- **`case 38` tidak dapat melarang `groupBy(`** ← agregasinya memang `Collection::groupBy()` di PHP, yang merupakan seluruh maksud aturannya. Yang dilarang kini hanya ejaan SQL-raw; nol agregat SQL diasersi terpisah; dan kedua panggilan Collection itu diasersi ADA.
- **Invarian gulir mendatar dicabangkan** ← rekap harian stasiun ini 11 kolom (engine-room 25), jadi `scrollWidth > clientWidth` tidak dijamin di viewport lebar; memakunya akan memerahkan tabel yang baik-baik saja. Yang diasersi tanpa syarat adalah `overflowX === 'auto'` dari CSS terkompilasi plus halaman tidak pernah menggulir mendatar.
- **`case 17b` ditambahkan untuk membuktikan fixture-nya membedakan** ← menjalankan aturan 11-kolom dan 13-kolom berdampingan pada baris yang sama (1 lawan 2), sehingga case 17 benar-benar akan memerahkan implementasi 13-kolom alih-alih kebetulan hijau.

### Keputusan yang agen TOLAK ambil, dan ia benar

Agen menemukan riak kedua (lima asersi yang memakai `process-water` sebagai contoh "laporan belum dibangun"), memperbaiki dua berkas yang komentarnya sendiri sudah mengantisipasinya, lalu **berhenti** pada tiga sisanya — karena memperbaikinya berarti menafsir ulang larangan eksplisit saya atas `LaporanStasiunTest.php`, satu di antaranya di berkas yang brief saya tidak pernah sebut. **Berhenti di situ adalah keputusan yang benar**, dan ketiganya saya perbaiki sendiri sesudahnya: larangan itu milik saya, bukan milik pemilik produk.

Yang saya tambahkan di luar pemindahan contohnya: **asersi ALASAN pindah** (`REPORT_ROUTES` punya kunci lama, tidak punya kunci baru) dan **pasangan positifnya** (tile serta payload Process Water kini aktif). Tanpa yang kedua, test hanya membuktikan ada satu tile nonaktif di suatu tempat — bukan bahwa tile berubah keadaan ketika laporannya mendarat. Lihat [[project-station-report-addition-ripples]].
