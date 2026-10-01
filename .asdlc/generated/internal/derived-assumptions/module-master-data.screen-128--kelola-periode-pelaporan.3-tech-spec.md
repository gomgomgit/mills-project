# Derived Assumptions Log — module-master-data.screen-128--kelola-periode-pelaporan.3-tech-spec

## v1–v2 — 2026-09-22

- route = "/master-data/periods", api = "/api/periods", tabel = "periods" ← derived from the entity id `period` + shared-decisions naming conventions, following the production-line precedent exactly. User never named the route or table
- 8 endpoint (bukan 5 CRUD biasa) ← 3 endpoint tambahan di luar pola master data biasa: /unverified-count, /close, /reopen. User menyatakan aksinya, pemecahannya menjadi endpoint terpisah adalah turunan agen
- close() memakai UPDATE BERSYARAT `WHERE id=? AND status <> 'closed'` lalu memeriksa affected_rows ← agent's answer to the "dua Admin menutup bersamaan" edge case; user never specified a concurrency mechanism. Alternatif (SELECT FOR UPDATE / optimistic version column) tidak dipakai karena satu UPDATE sudah cukup
- error code baru: PERIOD_OVERLAP (422), PERIOD_CLOSED_IMMUTABLE (409), PERIOD_ALREADY_CLOSED (409), PERIOD_NOT_CLOSED (409) ← seluruhnya dinamai agen. Hanya PERIOD_CLOSED yang berasal dari keputusan user (shared-decisions v5)
- definisi "belum terverifikasi" = `checked_by IS NULL OR acknowledged_by IS NULL` ← agent-derived. User berkata "jumlah record yang belum terverifikasi" tanpa mendefinisikan mana dari dua tahap verifikasi yang dihitung. Konsekuensinya: record yang sudah di-check Supervisor tapi belum di-acknowledge Mill Management TETAP dihitung sebagai belum terverifikasi
- unverified-count dihitung on-demand saat dialog dibuka, bukan disimpan sebagai snapshot ← agent's performance call; menyentuh hingga 18 tabel per panggilan
- batas rentang periode bersifat INKLUSIF di kedua ujung, dan dua periode yang bersentuhan tepat di batas dianggap beririsan ← agent-derived; tidak pernah dibahas
- station_type_label "Semua Stasiun" untuk station_type NULL ← mengikuti frasa yang sudah dipakai di business spec information_displayed
- per_page dibatasi maksimum 100 ← mengikuti shared-decisions.pagination.defaults
- test_scenarios: 20 entri, unit_test_cases: 46 (29 + 17) ← diturunkan oleh test-spec-writer-agent dari 20 bdd_scenarios Phase 2, tidak dikonfirmasi satu per satu (autopilot)
- CATATAN PROSES: v1 sempat ditulis dengan derivasi test yang dibuat langsung oleh command ini karena test-spec-writer-agent tampak mandek (transcript berhenti tumbuh 2,5 menit tanpa hand-back). Agent ternyata selesai tepat setelah v1 ditulis, dan hasilnya lebih lengkap — v2 menggantikan seluruh unit_test_cases dan test_scenarios dengan keluaran agent. Tidak ada isi v1 yang bertahan di bagian itu
- 4 test_scenario (data mobile menyusul, upaya verifikasi, mengubah data stasiun, data di luar rentang) memakai endpoint record stasiun yang TIDAK ada di api_contracts screen-128 ← keputusan agen untuk mempertahankannya sebagai kontrak lintas-layar alih-alih membuangnya. Konsekuensi nyata di Phase 4: keempatnya tidak akan lolos saat screen-128 diimplementasikan sendirian

## v3 — 2026-09-23

Kontrak baru `POST /api/periods/{id}/open` (usecase-144). Sengaja dibuat cermin `reopen`.

- **Tidak mendaftarkan `401 UNAUTHENTICATED`** ← test-spec-writer sempat mengusulkannya, dan itu keliru. Diverifikasi ke tech spec v2: `close` dan `reopen` hanya mendaftarkan 404/409/403; satu-satunya endpoint periode yang mendaftarkan 401 adalah `GET /api/periods`. Penanganan sesi ada di lapisan middleware, bukan di kontrak ini. Menyamakan lebih penting daripada melengkapi.
- `error_code` = `PERIOD_NOT_DRAFT` dengan http 409 ← meniru `PERIOD_NOT_CLOSED` milik `reopen`. Exception barunya `PeriodNotDraftException`, salinan struktur `PeriodNotClosedException`.
- **UPDATE berkondisi `WHERE id=? AND status='draft'`, bukan `save()` pada model** ← kondisi pada WHERE itu sendiri yang menjadi penjaga dua Admin bersamaan; tidak ada penguncian baris maupun transaksi tambahan. Pola ini disalin dari `close()`, yang komentarnya di `PeriodClosureService` baris 120-131 menjelaskan alasannya.
- **Satu unit test khusus mengasersi nol pemanggilan repository record stasiun** ← `close()` memang menghitung data belum terverifikasi, sehingga `open()` mudah dianggap simetris dan ikut dibebani validasi data. Test itu yang menahannya.
- **Satu unit test khusus mengasersi periode `open` tetap bisa di-update dan di-delete** ← `PeriodService::update()`/`delete()` hanya menolak `closed`. Sangat mudah seseorang "merapikan" ini menjadi ikut mengunci `open`; test itu yang menangkapnya.
- Skenario "status Terbuka tidak dapat dikembalikan ke Draft" berakhir **200**, bukan 422 ← diverifikasi ke `PeriodService::validate()`: field `status` tidak pernah divalidasi maupun ditulis, jadi kiriman itu **diabaikan**, bukan ditolak. Perbedaan yang halus tapi penting bagi penulis test.
- `DELETE` berakhir **200** (`{"deleted": true}`), bukan 204 ← diverifikasi ke `PeriodController::destroy()`.
- Dialog konfirmasi (`askOpen`/`cancelOpen`/`confirmOpen`) ← meniru pasangan close/reopen yang sudah ada. Jalur batal tidak punya bdd_scenario tersendiri, jadi ditulis sebagai catatan implementasi agar tetap diuji.

## v4 — 2026-09-27

Kontrak `usecase-140` dan `usecase-144` DIPINDAHKAN ke screen-142 (bukan dibuang), dan kontrak
`usecase-128` yang tersisa disusulkan ke model induk–anak yang sudah berjalan di kode.

- Seluruh `success_schema` periode diganti dengan bentuk `PeriodService::toRow()` (induk + `stations[]` + `station_count` + `closed_station_count` + `is_immutable` + `status_summary`) ← dibaca dari docblock `toRow()`; v3 masih memakai bentuk pipih ber-`status`/`station_type` yang kolomnya sudah dihapus dari database
- Induk sengaja TIDAK punya `status` ← alasan disalin dari `toRow()`: kunci bernama `status` akan mengundang `$row['status'] === 'closed'` hidup terus. Dicatat sebagai business_logic langkah 0 supaya tidak "dirapikan" kembali
- `PERIOD_CLOSED_IMMUTABLE` pada PATCH/DELETE dijawab dengan aturan "ANY" (satu baris closed sudah cukup) ← dari `guardAgainstClosedStation()`
- Urutan `PATCH` = guard 409 → validate → overlap → UPDATE → backfill ← dari `update()`; ditulis eksplisit karena posisi backfill SETELAH guard itulah sumber lubang backfill
- Lubang backfill dicatat sebagai `edge_case_handling` DAN `implementation_notes`, dengan jalan keluar manual (buka kembali → simpan → tutup lagi) ← turunan agen dari docblock `update()`; user tidak diminta memutuskan perbaikan tuntasnya
- 48 `unit_test_cases` (dari 29 milik usecase-128 di v3) ← ditulis ulang oleh agen terhadap perilaku kode saat ini; 12 kasus lama yang menguji `station_type`/`Semua Stasiun`/status periode tunggal digantikan kasus yang menguji baris stasiun, backfill add-only, aturan immutable "ANY", dan makna filter EXISTS. Tidak ada kasus yang dihapus tanpa penggantinya
- 13 `test_scenarios` (dari 9 milik usecase-128 di v3) ← 9 lama dipertahankan dengan payload tanpa `station_type`, ditambah 4 baru: mill tanpa stasiun aktif, backfill lewat simpan ulang, makna filter Status Stasiun, dan navigasi ke layar detail. 19 `test_scenarios` milik usecase-140/144 PINDAH ke screen-142
- Daftar `data-testid` yang hilang vs yang tetap ditulis eksplisit di `implementation_notes` ← turunan agen; dibuat agar langkah implementasi (dan helper e2e-web) punya satu daftar yang bisa dipakai memeriksa dirinya sendiri
- Endpoint BARU `GET /api/periods/{id}` TIDAK didaftarkan di artefak ini melainkan di screen-142 ← ia hanya dipakai layar detail; catatan urutan rute (literal sebelum berparameter) tetap ditulis di sini karena rute /periods-nya milik layar ini

## v5 — 2026-10-01

Phase 3 untuk panel 'Periode Terbuka per Mill'. Business spec v4 sudah menetapkan APA yang tampil;
yang di bawah ini keputusan teknis yang tidak diturunkan dari sana maupun dari user.

- **Endpoint API baru `GET /api/periods/open-summary`** ← PILIHAN AGENT. User meminta card di halaman web; sebuah endpoint tidak diminta. Dasarnya gaya rumah yang sudah mapan: kelima kemampuan layar ini punya kembaran API, dan screen-142 bahkan mendapat `POST /api/period-stations/{id}/open` untuk aksi yang hanya ada di Livewire. Panel tanpa kembaran API akan menjadi satu-satunya kemampuan periode yang tidak tercatat di `api-index`. Bila user menilai ini kelebihan cakupan, yang dibuang hanya controller + route + satu kelompok test; `PeriodService::openPeriodsByBusinessUnit()` tetap dibutuhkan komponen Livewire-nya.

- **Nama method `openPeriodsByBusinessUnit(?string $businessUnitId = null)`** ← turunan agent, mengikuti pola nama service yang sudah ada (`listPeriods`, `businessUnitOptions`, `activeStationTypesForMill`).

- **Arah iterasi dimulai dari daftar Business Unit, bukan dari daftar periode** ← keputusan agent, dan inilah satu-satunya hal yang membuat aturan "mill tanpa periode terbuka tetap punya card" tidak bisa bocor. Mengelompokkan hasil kueri periode per mill akan menghasilkan panel yang BENAR pada data yang ada periode terbukanya dan SALAH (mill hilang) justru pada keadaan yang paling perlu terlihat. Aturannya ditegakkan oleh bentuk kodenya, bukan oleh kehati-hatian pembacanya.

- **`is_running` + `is_past_range` sebagai DUA boolean, bukan satu enum tiga nilai** ← keputusan agent. Keadaan "terbuka tetapi belum dimulai" harus bisa diwakili tanpa dipaksa masuk ke salah satu penanda. Dengan satu enum, keadaan ketiga ini akan cepat dipetakan salah ke `past` oleh penulis kode berikutnya. Dua boolean yang keduanya `false` adalah representasi yang jujur dan tidak bisa disalahpahami.

- **`Carbon::today()` diambil SEKALI per pemanggilan dan dikembalikan sebagai `meta.today`** ← turunan agent, dua alasan yang keduanya nyata: (1) pemanggilan yang melewati tengah malam tidak boleh menghasilkan satu respons dengan dua tanggal acuan; (2) `Carbon::setTestNow()` membuat `is_running`/`is_past_range` dapat diuji — tanpa itu, test tanggal hanya bisa ditulis dengan tanggal relatif dan suite akan berperilaku berbeda tergantung hari dijalankannya. `meta.today` tidak diminta siapa pun; ia ada supaya test dan FE tidak perlu menebak acuannya.

- **Dua `withCount` dalam satu kueri** ← turunan agent. `toRow()` memberi `station_count` dan `closed_station_count`, tidak pernah `open_station_count`, jadi angka ini memang penambahan. Menghitungnya per periode dengan kueri terpisah akan menjadi N+1 pada panel yang menampilkan seluruh mill.

- **`open-summary` tidak terpaginasi** ← turunan agent. Batas jumlah barisnya adalah jumlah Business Unit (6 di dev), bukan jumlah periode, sehingga pagination hanya akan menambah cara panel bisa menyatakan hal yang berbeda tentang mill yang sama.

- **`business_unit_id` yang tidak cocok menjawab 200 + data kosong, bukan 404** ← turunan agent. Endpoint ini meringkas, bukan mengambil satu sumber daya; filter yang tidak cocok apa pun adalah hasil kosong yang sah. Satu-satunya keadaan lain yang menghasilkan data kosong adalah belum ada Business Unit sama sekali, dan FE membedakan keduanya lewat jumlah card yang ia minta render.

- **Tidak memakai kelas `md-card`** ← turunan agent, TERVERIFIKASI: `.md-card` hanya didefinisikan di `resources/views/dashboard/partials/report-styles.blade.php`, dipakai 39 kali, dan partial itu hanya di-include oleh layar laporan dashboard. Layout master-data tidak memuatnya, jadi markup ber-`md-card` di layar ini akan dirender tanpa gaya apa pun. Panel memakai prefix `kc-` dan 13 kelas barunya didefinisikan di blok `<style>` blade ini sendiri — nama-namanya dicantumkan di `implementation_notes` supaya pemeriksaan "tiap kelas punya definisi" bisa dijalankan sebagai daftar periksa, bukan dari ingatan.

- **Daftar `data-testid` panel** ← turunan agent, mengikuti pola penamaan `data-testid` yang sudah dipakai layar ini.

- **`GET /api/periods/open-summary` harus didaftarkan sebelum `/api/periods/{id}`** ← bukan asumsi, ini konsekuensi urutan route Laravel yang sudah tercatat sebagai perangkap pada `business-units/options` di `implementation_notes` butir ke-3. Dicatat ulang khusus untuk endpoint baru ini karena perangkapnya persis sama dan akibatnya 404 yang membingungkan.

### Yang DITAHAN dan belum dikerjakan

- **`api-index` belum menambahkan `GET /api/periods/open-summary`** (masih v60, 168 endpoint). Bukan kelalaian: `artifact__patch` hanya bisa MENGGANTI nilai pada path yang sudah ada dan tidak bisa menambah elemen ke sebuah list, sehingga menambah satu endpoint menuntut `artifact__write` yang mengemisi ulang seluruh 168 entri (~62 KB) dengan tangan. Itu pekerjaan salin-tempel masif yang satu slip-nya merusak katalog endpoint seluruh proyek secara diam-diam. Diangkat ke user beserta usulan perbaikan akarnya (mode append pada `artifact__patch`, yang menyentuh `.asdlc/mcp/` sehingga butuh izin eksplisit).

### Penutup utang yang ditahan di v5 — 2026-10-01

`api-index` **sudah** mencatat `GET /api/periods/open-summary` (v60 → v61, 168 → 169 endpoint).
Yang menahannya bukan artefaknya melainkan tool-nya, dan akarnya sudah diperbaiki atas izin user:
`artifact__patch` kini menerima `op` — `set` (bawaan), `append`, `extend` — sehingga menambah satu
endpoint cukup satu edit, bukan mengemisi ulang 169 entri dengan tangan. Diverifikasi sesudahnya:
164 entri yang ada di commit terakhir masih byte-identik dan urutannya tidak bergeser, nol duplikat
`method`+`path`, entri baru berada di ujung mengikuti pola pertumbuhan berkas ini.

Catatan operasional yang mahal dipelajari: menjalankan `/mcp` saja TIDAK memuat ulang kode server —
`/mcp` menyambung ulang ke proses yang sudah hidup, dan Python tidak mengimpor ulang modulnya.
Terbukti dari umur proses: kedua `server.py` lahir sebelum berkasnya diubah. Yang memuat kode baru
adalah aksi reconnect/restart pada server bersangkutan di dalam `/mcp`, bukan sekadar membuka
daftarnya. Cara memastikannya tanpa risiko: kirim satu edit ber-`op` tak dikenal pada path yang
tidak ada — kode lama menjawab dari pemeriksaan path, kode baru menjawab dari pemeriksaan `op`,
dan keduanya menolak tanpa menulis apa pun.
