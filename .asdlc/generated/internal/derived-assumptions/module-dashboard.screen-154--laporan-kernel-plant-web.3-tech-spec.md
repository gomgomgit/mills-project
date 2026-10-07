# Derived Assumptions — module-dashboard.screen-154--laporan-kernel-plant-web.3-tech-spec

## v1 — 2026-10-07

Autonomy `autopilot`: seluruh isi tech spec ini rumusan agent, diturunkan dari business spec
screen-154 v1 dan dari lima laporan kondisi yang sudah berjalan. User tidak menyatakan satu
keputusan teknis pun di sini.

- `route = /reports/kernel-plant` dan prefix API `/api/kernel-plant-reports/*` dengan EMPAT endpoint ← diturunkan dari pola yang dipakai lima laporan kondisi sebelumnya, bukan dinyatakan user. Nama `kernel-plant` dipilih agar cocok dengan nilai `StationType` enum `kernel_plant`.

- `KernelPlantReportService` dirancang sebagai saudara `DepricarpingReportService` baris demi baris (41 metode, konstanta yang sama bentuknya) ← keputusan agent. Depricarping dipilih sebagai model, bukan Threshing/Pressing, karena ia satu-satunya yang sudah punya `downtime_minutes` numerik, `findings` teks bebas, DAN satu pasangan kolom berbagi standar — ketiganya ada juga di Kernel Plant.

- PRASYARAT REFACTOR pada `KernelPlantRecordService` (tambah `public const READING_FIELDS`, ubah `isRowFilled()` dari `protected` menjadi `public` dan mengulangi konstanta itu) = temuan agent saat membaca kode, BUKAN permintaan user. Ini satu-satunya perubahan pada berkas yang sudah ada dan teruji. Perilakunya identik — sembilan kolom yang sama, semantik OR yang sama, asimetri `''`-dianggap-kosong hanya untuk `findings` yang sama — tetapi ia TETAP perubahan pada layar input, bukan hanya penambahan laporan. Dicatat terpisah supaya terlihat di review.

- Keputusan MEMINJAM definisi slot 'terisi' alih-alih menurunkannya ulang ← penilaian agent. Dua definisi yang berbeda berarti angka cakupan di laporan tidak sama dengan yang diterima layar input, dan tak seorang pun bisa tahu mana yang salah. Preseden: BoilerRoom, lalu Threshing, Pressing, Depricarping.

- Nama unit `kernel_plant_count` / `by_kernel_plant` / `slots_per_kernel_plant_per_day` ← turunan agent dari kolom `kernel_plant_id` pada `kernel_plant_records`. Depricarping memakai `presser_*` karena unitnya presser; tidak ada nama yang dinyatakan user.

- Penyebut cakupan memakai unit yang BENAR-BENAR punya record, bukan yang terdaftar ← diwarisi dari Depricarping dengan alasan yang sama: penyebut dari stasiun terdaftar akan menghukum mill yang sengaja tidak mengoperasikan satu unitnya. Bukan dinyatakan user.

- Urutan galat (peran 403 → mill 422 → id periode 422 → periode 404/403) ← diwarisi dari Depricarping; alasannya agar pemanggil yang tak berhak sama sekali tidak pernah tahu id periode mana yang ada. Turunan, bukan permintaan.

- Perbedaan perlakuan antara `period_id` milik mill lain (403) dan `production_line_id` milik mill lain (DIABAIKAN) ← diwarisi dari saudara-saudaranya. Alasannya: `period_id` selalu eksplisit dari pemanggil sehingga penolakan tidak membocorkan apa pun yang belum ia sebut, sementara line yang diabaikan menjaga agar penyelidikan tidak mendapat jawaban. Asimetri ini turunan agent dan mudah terbaca sebagai ketidakkonsistenan, jadi dicatat.

- Keputusan TIDAK menduplikasi daftar opsi Production Line (memakai `/api/production-lines/options-for-report` yang dibangun untuk screen-135) ← turunan agent.

- 16 `implementation_notes` dan 17 `edge_case_handling` = seluruhnya rumusan agent. Yang paling mudah salah dan karenanya ditulis paling tegas: `targetsWithoutMetric()` harus membandingkan terhadap HIMPUNAN lima parameter, bukan terhadap jumlah tujuh entri peta. Depricarping punya SATU pasangan berbagi standar sehingga kode yang menangani satu pasangan secara khusus LOLOS di sana dan GAGAL di sini — penilaian agent tentang titik rawan, bukan fakta yang dinyatakan di mana pun.

- `has_standard` pada blok downtime ditetapkan selalu `false` ← turunan dari kenyataan bahwa master Kernel Plant tidak punya baris downtime; bukan keputusan user.

- Kolom header ekspor beserta label Indonesianya (`Ripple Mill 1 (Amps)`, `Shell Bin Kernel Loss (%)`, dst.) = rumusan agent; tidak satu pun label dinyatakan user.

- `METRIC_LABELS` untuk ketujuh kolom (termasuk `Shell Bin Kernel Loss` untuk `shell_loss_percent`) = rumusan agent. Label terakhir itu mengikuti MASTER, bukan nama kolom — dan ketidakcocokan keduanya adalah open question yang sengaja dibiarkan terbuka, bukan diputuskan sendiri.

## v1 (lanjutan) — test spec, 2026-10-07

- `unit_test_cases` = 64 case dan `test_scenarios` = 26 entri (1:1 terhadap bdd_scenarios, urutan identik) ← diturunkan `test-spec-writer-agent`, lalu diperiksa dan dikoreksi command. Agent tidak punya akses Read/Bash/Glob dan `ToolSearch` nonaktif di sesinya, jadi ia TIDAK dapat membuka `laporan-depricarping.spec.ts` maupun test Depricarping mana pun; pola rumahnya ia rekonstruksi dari payload dan dari `implementation_notes`. Ia menyatakannya sendiri dan meminta diverifikasi — dan verifikasi itu memang menemukan kesalahan.

- EMPAT koreksi command atas keluaran agent, semuanya terbukti dari kode/berkas, bukan selera:
  1. Kunci stasiun `'kernel_plant'` → `'kernel-plant'` (27 tempat). `seedOpenPeriodForForms()` mencocokkannya ke `station.station_type` dari API, yaitu nilai enum `StationType::KernelPlant = 'kernel-plant'`. Nilai bergaris-bawah akan gagal di `period-fixture.ts` baris 184 dengan pesan "periode prasyarat tidak punya baris stasiun".
  2. Bentuk `api_test[].endpoint` dan `.request_example`: agent menulis string, template menuntut objek (`{method, path}` dan dict parameter) — ditolak validator, lalu ditransformasi secara terprogram, bukan ditulis ulang tangan.
  3. Skenario "mill belum punya satu pun periode Kernel Plant": `browser_test`-nya saling bertentangan — `beforeAll` menyemai periode itu, lalu asersinya menuntut periode itu tidak ada. Di DB e2e bersama (87 spec, `workers: 1`, fixture idempoten yang MEMAKAI ULANG periode) keadaan itu tak dapat diatur tanpa menjatuhkan spec lain. Diganti menjadi alasan eksplisit; cakupannya ditutup uji Livewire + unit case.
  4. Skenario "penyebut tiap parameter tidak boleh disamakan": agent menyebut 6/2/5/4/3/7/1 sebagai nilai **M**, padahal itu **N**. Dibetulkan, dan ditambahi peringatan terbalik ("M berbeda per baris adalah BUG, bukan yang diuji").

- ATURAN PENYEBUT `N dari M` DIPAKU DI SINI, dan ini keputusan tech spec yang sebelumnya menggantung: N = `metrics[].filled_slot_count`, M = `coverage.filled_slots`, SERAGAM untuk ketujuh baris. Diverifikasi langsung dari kode yang sudah berjalan — `DepricarpingReportService::metricsOf()` menerbitkan HANYA `filled_slot_count`, dan `laporan-depricarping.blade.php` baris 436 merender `{{ filled_slot_count }} dari {{ coverage['filled_slots'] }} slot`. Tidak ada M per kolom di API mana pun, dan tidak boleh ditambahkan: ketujuh kolom ukur hadir pada SETIAP baris detail, jadi "unit mana mengukur parameter mana" tidak dapat diketahui skema ini.

- `screen-mock-agent` mengarang M per kolom (672/336) berdasarkan andaian itu, menandainya sendiri sebagai keputusan tech spec, dan mock-nya SUDAH DIPERBAIKI (ketujuh sel menjadi 503, catatan auditnya ditulis ulang memuat sebab kesalahannya). Ia juga meniru screen-131 (Boiler Room) alih-alih screen-152 karena mock Depricarping/Threshing/Pressing memang tidak pernah dibuat — diverifikasi, dan itu pilihan yang benar.

- `information_displayed[3]` pada business spec diperjelas (ver 1 → 2) justru karena kalimat lamanya ("penyebutnya memang berbeda-beda") adalah yang membuat kedua agent salah baca dengan cara yang sama.

- `shares_standard_with` bersarang DI BAWAH `target`, bukan di tingkat metrik ← diverifikasi dari `DepricarpingReportService::targetFor()`; draf pertama tech spec ini menaruhnya di tingkat metrik dan sudah dikoreksi. Master Kernel Plant hanya punya DUA kolom target (`target_benchmark`, `corrective_action_plan`) lawan empat milik Depricarping, jadi `target` di sini lebih ramping — tidak ada medan yang dikarang agar "sebangun".

- Catatan agent bahwa `'no_column'` versus `UNMAPPED_NO_COLUMN` adalah konflik: BUKAN konflik. `UNMAPPED_NO_COLUMN` adalah nama konstanta PHP yang nilainya `'no_column'`, persis seperti Depricarping. Resolusi agent (assert nilainya) benar.

- Enam unit case ekspor (#58–#63) ada meski TIDAK ada `bdd_scenario` tentang ekspor, padahal `main_flow` langkah 14 memuatnya. Agent menitipkan langkah ekspor ke `api_test` skenario 1 dan 2 alih-alih menambah entri ke-27, karena aturannya satu entri per `bdd_scenario`. Keputusan agent yang dibiarkan berlaku; kalau ekspor perlu skenario sendiri, Phase 2 yang harus menambahnya.

## v1 (lanjutan) — koreksi kelima: bentuk uji browser, 2026-10-07

- KOREKSI TERBESAR atas keluaran agent, dan yang paling mudah terlewat. Agent menulis ke-26 `browser_test` sebagai spec yang MENYEMAI datanya sendiri dan MEMAKU angkanya (mis. "assert nilainya 6, 2, 5, 4, 3, 7, 1", "sel min menampilkan 20 bukan 0", "nilai mendekati 28.46"). Itu bertentangan dengan pola rumah, dan agent tidak dapat mengetahuinya karena ia tidak punya tool untuk membaca `e2e-web/tests/laporan-depricarping.spec.ts`.

- Pola rumah, dibaca langsung dari header spec Depricarping: spec `laporan-*` TIDAK menanam datanya sendiri dan TIDAK mengasersi satu angka tetap pun. Dua alasannya tertulis di sana dan keduanya menentukan: (1) angkanya sudah dibuktikan TIGA KALI di backend — atas service-nya, atas kontrak HTTP-nya, dan atas markup ter-render komponennya — sehingga mengulangnya lewat browser hanya memperlambat suite; (2) basis data e2e BERBAGI data stasiun dengan `BrowserTestFixtureSeeder` dan spec `form-*`, serta periode prasyarat berumur panjang, jadi angka yang dipaku akan berubah setiap kali salah satunya berubah — dan test yang gagal bukan karena produknya salah adalah test yang akan diabaikan orang, lalu dihapus.

- Ke-26 entri ditulis ulang mengikuti pola itu: 19 uji browser berbasis INVARIAN yang dihitung DARI DOM (sehingga benar untuk data apa pun), dan 7 skenario dinyatakan TANPA uji browser beserta alasan yang dapat diperiksa. Jumlah 19 itu sejajar dengan 18 milik Depricarping, plus satu tambahan untuk pasangan berbagi standar KEDUA yang tidak dimiliki Depricarping.

- Tujuh yang tanpa uji browser, dan sebabnya masing-masing adalah keadaan yang TIDAK DAPAT diatur di basis data bersama tanpa menjatuhkan spec lain: mill berline tunggal (mill fixture punya lebih dari satu line); mill tanpa periode Kernel Plant (fixture justru menyemainya); periode tanpa slot terisi (seeder selalu menanam data); periode belum mulai (menambahnya mengubah periode mana yang terpilih sendiri pada 18 spec form-* dan 4 spec laporan-*); master disunting tangan (dibaca juga spec form- dan detail-kernel-plant); akun tanpa business_unit_id (akun uji dipakai 87 spec, dan workers: 1 dengan urutan alfabetis membuat kerusakannya pasti); mill/line lain lewat properti (pemeriksaan tingkat properti, yang Livewire::test() dapat atur persis sementara browser hanya menirunya lewat jalan berliku yang membuktikan lebih sedikit). Ketujuhnya ditutup di lapis Livewire dan unit.

- Satu asersi SENGAJA dilemahkan dibanding usul agent: pada skenario penyebut per metrik, JANGAN mengasersikan bahwa ketujuh N berbeda-beda. Pada data bersama ketujuhnya kebetulan bisa sama, dan test yang gagal karena datanya kebetulan seragam adalah test yang akan dihapus. Bahwa N benar-benar dihitung per kolom dibuktikan unit case dengan sebaran yang dikendalikan penuh; yang diasersikan di browser adalah invariannya (tiap N <= slot terisi periode, dan M identik pada ketujuh baris).

- Dua catatan yang ikut terbukti dari pembacaan itu: urutan `StationReportService::REPORT_ROUTES` memang di-assert strict oleh `LaporanStasiunTest` baris 385 (`expect($available)->toBe(array_keys(REPORT_ROUTES))`, dengan `$available` terurut sort_order master), dan entri peta itu TIDAK DAPAT mendarat tanpa rutenya — `stationList()` memanggil `route($routeName)`, jadi satu tanpa yang lain melempar RouteNotFoundException pada 4 test. Keduanya dipasang bersama.

- Dan satu andaian saya sendiri yang salah, terbukti saat dijalankan: `Route::get($path, Komponen::class)` pada rute Livewire ME-RESOLVE kelasnya SAAT REGISTRASI, bukan saat permintaan masuk. Mendaftarkan rute sebelum komponennya ada membuat SELURUH suite merah dengan galat yang tidak menyebut sebabnya. Rute dan entri peta karenanya diparkir sampai komponennya ada. (Rute controller biasa `[Controller::class, 'method']` tidak begitu — ia tetap lazy, dan rute API Kernel Plant memang aman didaftarkan lebih dulu; suite 5114 test tetap hijau dengannya.)
