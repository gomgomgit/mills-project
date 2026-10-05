# Derived Assumptions — module-dashboard.screen-133--laporan-storage-tank-web.3-tech-spec

## v1 — 2026-09-25

- `route` = `/reports/storage-tank`, 4 endpoint `/api/storage-tank-reports/*` ← konvensi repo dan pola empat laporan stasiun sebelumnya.
- **Fixture unit test menyisipkan baris dengan urutan id SENGAJA ACAK** ← tidak saya minta; agen penurun test menambahkannya. Tanpa itu, implementasi yang mengambil "baris pertama menurut urutan penyimpanan" akan lolos, karena urutan penyimpanan kebetulan sering sama dengan urutan waktu. Basis data tidak menjamin apa pun tentang itu.
- **Fixture suhu sengaja membuat kolom tercatat berbeda dari rata-rata aritmetik** (50/60/70 → 60, tetapi kolom = 55, diasersi 55 dengan `not->toBe(60.0)`) ← ini satu-satunya bentuk fixture yang dapat menolak implementasi yang menghitung ulang. Dengan data normal di mana keduanya kebetulan sama, kedua implementasi lolos.
- **Fixture pergerakan memakai tangki kedua yang hanya punya pembacaan di akhir periode** ← membuat cara per-tangki dan cara gabungan menghasilkan angka yang BERBEDA, sehingga asersinya dapat menolak yang salah secara eksplisit. Dengan data lengkap, kedua cara memberi hasil sama dan testnya tidak membuktikan apa pun.
- `stock.movement_mt` dikirim sebagai medan tersendiri, bukan dibiarkan dihitung layar ← kalau layar yang menghitungnya dari `closing_mt - opening_mt`, aturan "dijumlahkan per tangki" hilang di lapisan tampilan tanpa satu pun test server yang menangkapnya.
- `movement_computable` dikirim sebagai boolean terpisah dari `movement_mt` yang null ← membedakan "tidak dapat dihitung" dari "kebetulan null karena sebab lain", dan memberi layar sesuatu yang dapat diasersi selain ketiadaan angka.
- **Normalisasi indeks dipilih di atas sumbu kedua untuk grafik tiga metrik mutu** ← keputusan agen mock yang saya terima: sumbu kedua hanya memisahkan DOBI, sementara FFA dan kadar air sendiri berbeda sekitar 20×, jadi masalahnya tidak selesai. Spec menerima kedua implementasi, tetapi asersi legenda bersifat mengikat — apa pun yang dipilih wajib dinyatakan.
- **Konflik antar-aturan di instruksi mock saya** ← empat aturan angka yang saya tetapkan saling mengunci, sehingga contoh "stok awal diambil hari ketiga" tidak dapat hadir di mock tanpa merusak salah satunya. Agen menyelesaikannya dengan membuat ketiga tangki tercatat di kedua ujung tetapi pada slot jam berbeda, sehingga kolom tanggal tetap membawa muatan nyata. Dicatat karena mock karenanya TIDAK memperagakan kasus itu — yang memperagakannya adalah unit test.
- `slots_per_tank_per_day` dikirim di `coverage` ← agar persentase kelengkapan dapat ditelusuri dan tidak menjadi angka ajaib, sama seperti screen-131 dan screen-132.

## v2 — 2026-09-27

- `implementation_notes[0..]` = catatan pemisahan periods/period_stations disisipkan di AWAL daftar, bukan di akhir ← briefing tidak menentukan posisinya; perubahan model adalah hal pertama yang perlu dibaca sebelum catatan lain, dan catatan lama tetap utuh di bawahnya.
- `test_scenarios[*].scenario_ref` = dibiarkan identik byte-per-byte ← ref ini mengunci ke `bdd_scenarios` pada usecase Fase 2 yang TIDAK basi dan tidak saya sentuh. Menamai ulangnya akan memutus tautan itu tanpa satu pun kegagalan yang terlihat, padahal isi skenarionya memang berubah dan yang wajib berubah hanya action/assert/request_example.
- `endpoints[*].response.success_schema.*.status` = `"string (draft|open|closed) — status BARIS period_stations <Stasiun>"` (bukan tetap `"string"` telanjang) ← briefing hanya menyebut bahwa artinya berubah. Skema yang tetap berbunyi `"string"` masih terbaca sebagai status periode oleh pembaca mana pun, jadi kualifikasi prosa dipasang di dalam nilai skemanya sendiri.
- `data_operations` = ditambah entri `period-station` (SELECT); `shared_entities` SENGAJA TIDAK ditambah ← artefak-artefak ini sudah mendaftarkan `station-type` dan `business-unit` di `data_operations` tanpa memasukkannya ke `shared_entities`. Menambah period-station hanya di data_operations mengikuti konvensi yang sudah ada; menambahnya di kedua tempat akan membuatnya menonjol berbeda dari dua entri lain yang sejenis.
- `business_logic` = disisipkan dua langkah baru, `statusValue()` dan `periodOption()` ← briefing tidak memintanya. Keduanya adalah tempat percabangan sesungguhnya hidup (fallback `'draft'`, dan `station_type` yang tidak lagi nullable), dan `business_logic` adalah yang dipakai menurunkan unit test; tanpa langkah itu keduanya tidak punya sandaran di artefak.
- `unit_test_cases` = ditambah 2 kasus baru per layar (periode tanpa baris period_stations; status stasiun ini vs status stasiun lain di periode yang sama) ← kode menambahkan tepat dua test service dengan maksud itu pada commit 83a4065. Artefak yang hanya menulis ulang kasus lama akan melaporkan cakupan uji lebih kecil daripada yang sebenarnya ada.
- `unit_test_cases` = TIDAK ditambah kasus untuk fallback `statusValue()` = `'draft'` ← tidak ada test-nya di kode, dan menuliskannya sebagai kasus akan membuat artefak menjanjikan uji yang tidak pernah dijalankan. Dicatat sebagai celah cakupan di `implementation_notes` alih-alih.
- `edge_case_handling` = ditambah satu kondisi baru ("periode induk ada tetapi tanpa baris period_stations untuk jenis stasiun ini") ← briefing menyebut perilakunya tetapi tidak ke mana ia ditulis; ini satu-satunya tempat di artefak yang memang menampung bentuk data yang tidak dapat dijangkau lewat UI.
- `test_scenarios[*].browser_test` = ditulis ulang di sekitar fixture e2e yang benar-benar ada (periode milik mill tanpa stasiun aktif = tanpa baris period_stations), dan langkah "buktikan di sumbernya" (membuka baris stasiun di layar Kelola Periode Pelaporan) dipertahankan sebagai bagian asersinya ← e2e-web/tests/laporan-storage-tank.spec.ts memang melakukan keduanya; asersi yang hanya berbunyi "periode X tidak muncul" tidak menjelaskan MENGAPA, dan itulah yang dulu membuat bentuk lamanya tampak masih bisa diproduksi.
- `implementation_notes` = ditambah catatan bahwa satu skenario pemilih periode di artefak ditegakkan oleh TIGA test komponen Livewire ← asimetri 1-ke-3 itu akan terbaca sebagai cakupan yang hilang saat artefak dibandingkan dengan suite-nya.

## v3 — 2026-09-29

- `success_schema.production_line` ditulis sebagai STRING deskriptif, bukan objek bersarang `{id, name}` ← bloknya bernilai **null seluruhnya** ketika `production_line_id` tidak dikirim, dan sebuah objek bersarang tidak dapat menyatakan itu. Sifat "aditif, nol kunci lama berubah" juga hanya dapat ditulis sebagai prosa, dan justru itulah jaminan yang mengikat pembaca lama.
- `production_line` disisipkan TEPAT SETELAH `period` ← urutannya sama dengan `summary()` di service, sehingga blok konteks (periode, lalu line) berkumpul di awal sebelum angka.
- `production_line_id` TIDAK ditambahkan ke endpoint `/periods` ← bukan kelalaian: periode milik MILL (`periods.business_unit_id`, tanpa kolom line). Menuliskannya sebagai parameter opsional di sana akan mengundang implementasi berikutnya menyaring periode per line.
- `business_logic` = enam langkah baru DITAMBAHKAN DI AKHIR tanpa penomoran ← daftar pada artefak laporan memang tidak bernomor (berbeda dari Data Browser), dan urutan bacanya sudah berjalan dari resolusi → ringkasan → ekspor, jadi penambahan di akhir tetap terbaca runtut.
- `unit_test_cases` = 7 kasus ditulis dalam BAHASA INDONESIA ← mengikuti gaya kasus yang sudah ada pada artefak ini.
- `test_scenarios` = 5 skenario baru; hanya skenario pertama yang `browser_test`-nya diisi ← `e2e-web/tests/laporan-*.spec.ts` memang MEMILIH sebuah Production Line sebelum membaca angka, jadi "angka tampil setelah line dipilih" benar-benar dijalankan. Empat sisanya (angka satu line, line mill lain diabaikan, ekspor tersaring, stasiun dipindah) tidak punya asersi browser sama sekali, jadi ketiganya dikosongkan dan celahnya ditulis di `implementation_notes`.
- Kasus "daftar periode tidak pernah tersaring per line" TIDAK dimasukkan ke `unit_test_cases` layar web ← uji dengan maksud itu ada di sisi mobile (repo + komponen + browser), tidak di suite web. Menuliskannya di sini akan melaporkan cakupan yang tidak ada.

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v3)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v4 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (StorageTankReportService.php, laporan-storage-tank.blade.php, ReportPeriodDays.php, ChartAxis.php, SheetWriter.php, ExportValue.php).
- api_contracts[0].endpoints[2].response.success_schema.coverage = + days_counted, period_running; expected_slots pakai days_counted ← summary() 'days_counted' => $daysCounted, 'period_running' => ReportPeriodDays::isRunning
- api_contracts[0].business_logic[16] = expected_slots = tank_count x days_counted x slots ← $expectedSlots = $tankCount * $daysCounted * ...
- api_contracts[0].business_logic[21] = SheetWriter csv/xlsx, 7 kolom konteks, ExportValue status/time/valve ← export()/streamExportRows()
- api_contracts[0].endpoints[3].description + response._note = CSV atau xlsx sungguhan, kolom konteks baru ← fileMetaFor + SheetWriter
- api_contracts[0].unit_test_cases[26].expect = header berlabel Indonesia dgn Periode/Mill/Production Line, 'Tersinkron', 'Open 1/2' ← StorageTankReportServiceTest diff
- api_contracts[0].edge_case_handling[+2] = periode berjalan; penyebut 0 ← ReportPeriodDays + blade
- api_contracts[0].unit_test_cases[+1] = days_counted/period_running hadir di coverage ← StorageTankReportServiceTest asersi kunci 'days_counted','period_running'
- implementation_notes[+1] = ReportPeriodDays, 1 desimal, ChartAxis, SheetWriter/ExportValue ← diff blade/service

## v5 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit db73fbd, c321f32), code is truth (components/report-filter-bar.blade.php, laporan-*.blade.php, e2e-web/tests/laporan-*.spec.ts).
- implementation_notes[+] ← report-filter-bar (props/testid diteruskan), format opsi periode, badge status, ekspor busy, .ld-region
