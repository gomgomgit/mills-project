# Derived Assumptions — module-dashboard.screen-132--laporan-clarification-web.3-tech-spec

## v1 — 2026-09-25

- `route` = `/reports/clarification`, 4 endpoint ber-prefix `/api/clarification-reports/*` ← konvensi repo dan pola tiga laporan stasiun sebelumnya. User tidak menyebut rute.
- **`production.total_ton` = `SUM(pure_oil_production_rate_ton_hour)`** ← diverifikasi di skema: tidak ada kolom produksi sama sekali. Pola "hourly grid" milik `ClarificationRecordService` yang membuat satu pembacaan berlaku untuk satu jam, sehingga penjumlahan laju setara dengan produksi. Rumusnya milik user; yang saya turunkan adalah konsekuensinya — `reading_count` wajib ikut dikirim, karena angka turunan dari 40 pembacaan tidak boleh terlihat sama meyakinkan dengan yang dari 400.
- **Jam tanpa catatan laju dikeluarkan dari SUM DAN dari penyebut rata-rata** ← jebakan paling berbahaya di layar ini, karena **kedua tafsir menghasilkan TOTAL yang sama**. Yang berbeda hanya rata-ratanya: menganggap jam kosong sebagai 0,0 membengkakkan penyebut dan mengempiskan rata-rata laju sementara totalnya tetap terlihat benar. Kesalahan seperti ini tidak akan pernah terlihat dari angka utamanya.
- **`downtime.total_mins` null bila tidak pernah tercatat, 0 bila tercatat dan memang nol** ← `downtime_mins` nullable. Menyatukan keduanya akan melaporkan keandalan yang tidak pernah diukur. Kontraknya mengirim `reading_count` terpisah supaya perbedaan itu dapat dibaca dari respons, bukan hanya dari tampilan.
- **`daily[]` mengirim ketiga suhu pada SATU baris per tanggal** ← user menulis "tren suhu antar tangki"; kata "antar" itu yang menentukan. Bentuk respons ini yang memungkinkan layar menggambarnya pada satu sumbu. Tiga seri terpisah akan memenuhi kalimatnya secara harfiah sambil menghilangkan maksudnya.
- **Laju dan downtime tidak saling mengurangi** ← mengikuti rumus user apa adanya. **Pertanyaannya masih TERBUKA** dan ditulis ke dalam `implementation_notes` agar tidak hilang: bila laju yang diinput adalah laju sesaat, produksi periode lebih tinggi daripada kenyataan. Tidak diputuskan di kode; kalau kelak dibalik, rumusnya `laju × (60 − downtime_mins)/60`.
- **Ketiadaan penandaan ambang wajib DIASERSI menurut nama** ← disalin dari pelajaran screen-131, di mana agen melakukannya tanpa diminta dan itu terbukti benar. Aturan "jangan menandai" hanya bertahan kalau ada test yang menjaganya; tanpa itu seseorang akan melengkapinya kelak dengan niat baik.
- **Guard peran sebelum `findOrFail`, dibuktikan lewat jenis exception** ← disalin dari screen-131. `AuthorizationException` dan bukan `ModelNotFoundException` adalah satu-satunya cara membuktikan urutannya, karena keduanya sama-sama menghasilkan penolakan.
- **Entri `REPORT_ROUTES` harus sesuai `sort_order`** ← `LaporanStasiunTest` melakukan `toBe()` terurut ketat. Pelajaran dari screen-131, dicatat di sini supaya tidak ditemukan ulang lewat test merah.
- **Nama medan diselaraskan** (`production.total_ton`, `downtime.total_mins`, dst.) ← turunan test memakai nama datar `total_production_ton`/`total_downtime_mins`; 45 kemunculan diganti agar artefak tidak memuat dua nama untuk satu hal. Pola kesalahan yang sama terjadi di screen-131 dan screen-136 — agen penurun test memang cenderung mengarang nama medan ketika kontraknya tidak diberikan inline.

## v2 — 2026-09-25

- **PERTANYAAN TERBUKA DITUTUP: `downtime_mins` TIDAK mengurangi produksi** ← keputusan pemilik proses 2026-09-25. Dasarnya menjawab persis ketidakpastian yang saya angkat: laju yang diinput Operator adalah **laju rata-rata sepanjang jam itu**, bukan laju sesaat saat mesin berjalan. Karena downtime sudah tercermin di dalam lajunya, mengalikan dengan `(60 − downtime)/60` akan menghitung penurunan yang sama dua kali. Rumus `Σ laju × 1 jam` final, dan total downtime tetap ditampilkan berdampingan sebagai konteks — bukan sebagai pengurang.
- Implementasi TIDAK berubah ← ia memang sudah mengikuti rumus itu sejak awal. Yang berubah hanya status pertanyaannya: dari terbuka menjadi diputuskan, sehingga tidak diangkat ulang di layar mobile (screen-138) maupun saat seseorang membaca `productionOf()` kelak dan mengira rumusnya belum final.

## v3 — 2026-09-27

- `implementation_notes[0..]` = catatan pemisahan periods/period_stations disisipkan di AWAL daftar, bukan di akhir ← briefing tidak menentukan posisinya; perubahan model adalah hal pertama yang perlu dibaca sebelum catatan lain, dan catatan lama tetap utuh di bawahnya.
- `test_scenarios[*].scenario_ref` = dibiarkan identik byte-per-byte ← ref ini mengunci ke `bdd_scenarios` pada usecase Fase 2 yang TIDAK basi dan tidak saya sentuh. Menamai ulangnya akan memutus tautan itu tanpa satu pun kegagalan yang terlihat, padahal isi skenarionya memang berubah dan yang wajib berubah hanya action/assert/request_example.
- `endpoints[*].response.success_schema.*.status` = `"string (draft|open|closed) — status BARIS period_stations <Stasiun>"` (bukan tetap `"string"` telanjang) ← briefing hanya menyebut bahwa artinya berubah. Skema yang tetap berbunyi `"string"` masih terbaca sebagai status periode oleh pembaca mana pun, jadi kualifikasi prosa dipasang di dalam nilai skemanya sendiri.
- `data_operations` = ditambah entri `period-station` (SELECT); `shared_entities` SENGAJA TIDAK ditambah ← artefak-artefak ini sudah mendaftarkan `station-type` dan `business-unit` di `data_operations` tanpa memasukkannya ke `shared_entities`. Menambah period-station hanya di data_operations mengikuti konvensi yang sudah ada; menambahnya di kedua tempat akan membuatnya menonjol berbeda dari dua entri lain yang sejenis.
- `business_logic` = disisipkan dua langkah baru, `statusValue()` dan `periodOption()` ← briefing tidak memintanya. Keduanya adalah tempat percabangan sesungguhnya hidup (fallback `'draft'`, dan `station_type` yang tidak lagi nullable), dan `business_logic` adalah yang dipakai menurunkan unit test; tanpa langkah itu keduanya tidak punya sandaran di artefak.
- `unit_test_cases` = ditambah 2 kasus baru per layar (periode tanpa baris period_stations; status stasiun ini vs status stasiun lain di periode yang sama) ← kode menambahkan tepat dua test service dengan maksud itu pada commit 83a4065. Artefak yang hanya menulis ulang kasus lama akan melaporkan cakupan uji lebih kecil daripada yang sebenarnya ada.
- `unit_test_cases` = TIDAK ditambah kasus untuk fallback `statusValue()` = `'draft'` ← tidak ada test-nya di kode, dan menuliskannya sebagai kasus akan membuat artefak menjanjikan uji yang tidak pernah dijalankan. Dicatat sebagai celah cakupan di `implementation_notes` alih-alih.
- `edge_case_handling` = ditambah satu kondisi baru ("periode induk ada tetapi tanpa baris period_stations untuk jenis stasiun ini") ← briefing menyebut perilakunya tetapi tidak ke mana ia ditulis; ini satu-satunya tempat di artefak yang memang menampung bentuk data yang tidak dapat dijangkau lewat UI.
- `test_scenarios[*].browser_test` = ditulis ulang di sekitar fixture e2e yang benar-benar ada (periode milik mill tanpa stasiun aktif = tanpa baris period_stations), dan langkah "buktikan di sumbernya" (membuka baris stasiun di layar Kelola Periode Pelaporan) dipertahankan sebagai bagian asersinya ← e2e-web/tests/laporan-clarification.spec.ts memang melakukan keduanya; asersi yang hanya berbunyi "periode X tidak muncul" tidak menjelaskan MENGAPA, dan itulah yang dulu membuat bentuk lamanya tampak masih bisa diproduksi.
- `implementation_notes` = ditambah catatan bahwa satu skenario pemilih periode di artefak ditegakkan oleh TIGA test komponen Livewire ← asimetri 1-ke-3 itu akan terbaca sebagai cakupan yang hilang saat artefak dibandingkan dengan suite-nya.

## v4 — 2026-09-29

- `success_schema.production_line` ditulis sebagai STRING deskriptif, bukan objek bersarang `{id, name}` ← bloknya bernilai **null seluruhnya** ketika `production_line_id` tidak dikirim, dan sebuah objek bersarang tidak dapat menyatakan itu. Sifat "aditif, nol kunci lama berubah" juga hanya dapat ditulis sebagai prosa, dan justru itulah jaminan yang mengikat pembaca lama.
- `production_line` disisipkan TEPAT SETELAH `period` ← urutannya sama dengan `summary()` di service, sehingga blok konteks (periode, lalu line) berkumpul di awal sebelum angka.
- `production_line_id` TIDAK ditambahkan ke endpoint `/periods` ← bukan kelalaian: periode milik MILL (`periods.business_unit_id`, tanpa kolom line). Menuliskannya sebagai parameter opsional di sana akan mengundang implementasi berikutnya menyaring periode per line.
- `business_logic` = enam langkah baru DITAMBAHKAN DI AKHIR tanpa penomoran ← daftar pada artefak laporan memang tidak bernomor (berbeda dari Data Browser), dan urutan bacanya sudah berjalan dari resolusi → ringkasan → ekspor, jadi penambahan di akhir tetap terbaca runtut.
- `unit_test_cases` = 7 kasus ditulis dalam BAHASA INDONESIA ← mengikuti gaya kasus yang sudah ada pada artefak ini.
- `test_scenarios` = 5 skenario baru; hanya skenario pertama yang `browser_test`-nya diisi ← `e2e-web/tests/laporan-*.spec.ts` memang MEMILIH sebuah Production Line sebelum membaca angka, jadi "angka tampil setelah line dipilih" benar-benar dijalankan. Empat sisanya (angka satu line, line mill lain diabaikan, ekspor tersaring, stasiun dipindah) tidak punya asersi browser sama sekali, jadi ketiganya dikosongkan dan celahnya ditulis di `implementation_notes`.
- Kasus "daftar periode tidak pernah tersaring per line" TIDAK dimasukkan ke `unit_test_cases` layar web ← uji dengan maksud itu ada di sisi mobile (repo + komponen + browser), tidak di suite web. Menuliskannya di sini akan melaporkan cakupan yang tidak ada.

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v4)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (ClarificationReportService.php, ReportPeriodDays.php, SheetWriter.php, ExportValue.php, ChartAxis.php, laporan-clarification.blade.php, routes/api.php, tests/Feature/Api/LaporanClarificationTest.php).
- actor_permissions[3].conditions = web tertutup (403 page; Operator boleh login web sejak 2026-10-04); API periods/summary/export menerima Operator sejak 2026-09-25 ← routes/api.php + LaporanClarificationTest 'operator: 200 on periods/summary/export…' (drift lama, dikoreksi)
- api_contracts[0].endpoints[2].response.success_schema.coverage = + days_counted, period_running ← summary() coverage
- api_contracts[0].endpoints[3].description / _note = CSV atau xlsx sungguhan; 15 kolom Periode/Mill/Production Line + konteks record + slot HH:MM ← EXPORT_HEADER
- api_contracts[0].business_logic[12] = expected_slots = unit × days_counted × slot ← ReportPeriodDays::counted
- api_contracts[0].business_logic[18] = ekspor via SheetWriter + exportContext + ExportValue ← export()/streamExportRows
- api_contracts[0].edge_case_handling (+2) = periode berjalan; expected 0 → '—' ← blade
- api_contracts[0].unit_test_cases[12] = expected_slots pakai days_counted (periode selesai) ← ReportPeriodDays (⚠ given diubah ke 'periode yang sudah selesai' agar angka 144 tetap benar; disimpulkan)
- api_contracts[0].unit_test_cases[41].expect = 15 kolom, status 'Tersinkron', slot HH:MM ← ClarificationReportServiceTest diff
- api_contracts[0].unit_test_cases (+2) = ekspor berlabel; xlsx sungguhan ← ReportAuditFix20261004Test #8, ExportXlsxTest
- test_scenarios[13].api_test[1..3] = 200; api_test[4] = 404 NOT_FOUND; browser_test.assert = halaman 403 ← routes + LaporanClarificationTest (⚠ step 4 404 disimpulkan dari authorizePeriod findOrFail setelah guard peran meloloskan Operator; tidak diasersi tes)
- implementation_notes (+1) = REVISI audit-fix

## v6 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit db73fbd, c321f32), code is truth (components/report-filter-bar.blade.php, laporan-*.blade.php, e2e-web/tests/laporan-*.spec.ts).
- implementation_notes[+] ← report-filter-bar (props/testid diteruskan), format opsi periode, badge status, ekspor busy, .ld-region
