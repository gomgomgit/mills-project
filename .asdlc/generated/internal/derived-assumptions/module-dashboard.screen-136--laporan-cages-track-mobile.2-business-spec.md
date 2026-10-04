# Derived Assumptions — module-dashboard.screen-136--laporan-cages-track-mobile.2-business-spec

## v1 — 2026-09-24

Layar ini adalah pasangan mobile dari [[module-dashboard.screen-130--laporan-cages-track-web.2-business-spec]], mengikuti pola 129 → 135. Sebagian besar isinya turunan dari dua sumber itu, bukan pernyataan baru user.

- **Operator termasuk aktor** ← keputusan user 2026-09-23 untuk screen-135 ("orang yang menginput data berhak melihat hasilnya"), diterapkan ke stasiun berikutnya tanpa ditanyakan ulang. Perbedaan tajam dari versi web screen-130, yang menolak Operator.
- **Memakai ulang 4 endpoint `/api/cages-track-reports/*` apa adanya** ← keputusan user yang sama: satu sumber angka untuk web dan mobile sehingga mustahil berbeda. Konsekuensi teknisnya (melebarkan rute + `guardAccess()` + `resolveBusinessUnit()` + guard `sanctum`) bukan pilihan, melainkan akibat yang harus dikerjakan.
- `entry_points[0]` = tile Cages & Tracks pada screen-141 ← berbeda dari screen-135 yang sempat ditulis "sementara lewat rute langsung" karena screen-141 belum ada saat itu. Sekarang screen-141 sudah ada, jadi pintu masuknya nyata — tetapi hanya setelah `REPORT_ROUTES` di `ReportingPilihStasiunView.vue` ditambah satu baris.
- **Aturan jeda menyebut "diukur menurut waktu berlalu sejak jendela operasi dibuka"** ← dinaikkan dari koreksi spec screen-130 v2 hari ini, supaya versi mobile tidak mewarisi rumusan v1 yang salah untuk shift malam. User tidak menyebutnya; ini konsekuensi langsung keputusan user 2026-09-24 atas cacat itu.
- `business_rules` tentang jaringan dan `edge_cases` sesi berakhir ← disalin dari screen-135; keduanya khas mobile dan tidak ada padanannya di versi web.
- **Ekspor CSV dipertahankan di mobile** ← screen-135 punya; tidak ditanyakan apakah masuk akal mengunduh CSV di ponsel. Layak dipertanyakan kelak, tetapi menghilangkannya akan membuat dua layar laporan mobile berbeda kemampuan tanpa alasan.
- `test_priority` = `high` ← sama dengan screen-130 dan screen-135: layar ini menyangkut cakupan mill dan hak akses lintas peran.
- `edge_cases` "hari ber-record tanpa rincian per jam" dan "operasi melewati tengah malam" ← dibawa dari screen-130 karena keduanya sifat data stasiun ini, bukan sifat platformnya; angkanya dihitung server yang sama.

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/LaporanCagesTrackView.vue, mobile/tests/LaporanCagesTrackView.spec.ts).
- edge_cases[1] = + 0 hari ber-record → rata-rata 'tidak tersedia' + 'Belum ada hari ber-record untuk dijadikan pembagi.', days-with-records disembunyikan ← view template v-if="!kpi?.days_with_records" + spec diff
