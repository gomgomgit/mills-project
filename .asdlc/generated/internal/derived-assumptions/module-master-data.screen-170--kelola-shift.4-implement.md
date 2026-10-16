# Derived Assumptions — module-master-data.screen-170--kelola-shift.4-implement

## v1 — 2026-10-16

Layar pertama fitur Shift. Backend + web saja; `mobile/` nol tersentuh.

### BUG SPEC YANG SAYA TULIS SENDIRI — ditemukan implementasi, belum diputuskan

**Spec screen-170 bertentangan dengan dirinya sendiri soal shift 24 jam.** Aturan bisnis saya melarang `start_time == end_time` ("shift berdurasi nol tidak dapat menampung record") sambil menambahkan kalimat yang tidak koheren: *"shift 24 jam penuh harus ditulis sebagai satu shift yang menutup seluruh hari, bukan sebagai jam yang berimpit"* — padahal `07:00 → 07:00` **adalah** jam berimpit dan tidak ada cara lain menuliskan 1440 menit. Edge case "satu line punya tepat satu shift yang menutup 24 jam penuh — sah" dan unit case "shift tunggal menutup 24 jam" (yang fixture-nya `07:00–07:00`) sama-sama mengandaikannya bisa, sementara unit case "start_time sama dengan end_time ditolak" melarangnya.

Resolusi implementasi: `validate()` menolak jam berimpit sesudah normalisasi (`'07:00'` vs `'07:00:00'` ikut tertangkap), sementara `durationMinutes()` membaca modulo 0 sebagai 1440 sehingga baris yang terlanjur ada tetap tergambar sebagai satu blok penuh alih-alih lenyap. **Akibatnya shift 24 jam tidak dapat dibuat lewat API maupun form** — hanya lewat factory, dan itulah cara ketiga suite backend mengasersinya.

Agen benar tidak mengeskalasinya sebagai `spec_mismatch`: implementasi yang koheren untuk kedua bacaan memang ada, yang kurang adalah maksud produk. **Menunggu keputusan user.** Dua jalannya: (A) terima jam berimpit HANYA ketika line belum punya shift lain — tidak ambigu secara struktur, dan pratinjau hidup akan menggambar batang penuh sebelum Simpan ditekan sehingga salah ketik terlihat lebih dulu; atau (B) tetap tolak, dan edge case serta unit case yang menjanjikan 24 jam DICABUT dari artefak karena keduanya menjanjikan sesuatu yang tidak dapat dicapai lewat layar.

### Pilihan implementasi yang tidak dicakup spec

- **`ShiftOverlapException` WAJIB ada, bukan opsional** ← `ApiExceptionHandler::codeFor()` hanya menurunkan VALIDATION_ERROR / UNAUTHENTICATED / FORBIDDEN / NOT_FOUND sendiri; kode `SHIFT_OVERLAP` baru sampai ke amplop galat bila exception-nya mengimplementasi `App\Exceptions\HasErrorCode`. Bentuknya meniru `PeriodOverlapException`.
- **`uncovered_minutes` = 0 untuk `unconfigured`** (bukan 1440), dan line tanpa shift menerbitkan **nol blok**. Batang datar "belum diatur" adalah keputusan TAMPILAN di blade yang dikunci pada `state` — dan justru itu yang secara struktural mencegahnya pernah tergambar dengan arsiran celah. `blocks[].kind` tetap persis `'shift' | 'gap'`.
- **`starts_at_production_day` bernilai `true` untuk line `unconfigured`** ← line tanpa shift tidak diberi peringatan ketidakselarasan.
- **`business_unit_id` tak dikenal / bukan UUID → data dan coverage kosong dengan 200**; hanya absen/kosong yang 422. Tanpa aturan `exists`, dan `Str::isUuid()` diperiksa sebelum kueri apa pun sehingga PostgreSQL tidak pernah melempar SQLSTATE 22P02 — jebakan `ScopesToActorMill` yang sudah terdokumentasi. Sebaliknya `production_line_id` bukan-UUID / milik mill lain → 422, sesuai edge case.
- **`position` adalah indeks 0-basis di dalam line-nya**, diturunkan dari urutan offset, bukan offset-nya sendiri. Pengurutan dilakukan **di PHP, bukan SQL**, karena "jam mulai relatif 07:00" tidak punya `ORDER BY` yang portabel atas kolom TIME.
- **`created_by`/`updated_by` FK nullable** (`nullOnDelete`) mengikuti `production_lines` dan `mill_settings`, meski katalog entitas menandai `created_by` wajib — lapisan aplikasi selalu mengisinya dari sesi, kolomnya tidak menegakkan.
- **POST memulangkan 200, bukan 201** ← `PeriodController::store()` memulangkan 201, tetapi `test_scenarios` butir 1 langkah 3 serta butir 7/10/12/15 semuanya mengharapkan 200. Spec diikuti, preseden tidak.
- **Kontrol filter memakai `<select class="input">` native sesuai mock**, bukan pasangan `x-filter.bar` + `x-searchable-select` yang dipakai screen-128 ← kosakata kelas mock memetakan 1:1 ke `partials/components-styles.blade.php` yang sudah disertakan layout. Form modal tetap memakai `<x-modal>` dan `<x-busy-label>` milik proyek.
- **Baris catatan line "belum diatur" dirender setelah baris shift di tiap halaman** dengan `colspan=6`, sesuai mock yang menampilkannya inline.
- **Blok pratinjau Alpine datang dari server** (`shiftBlocksByLine()`, tanpa paginasi); JS hanya menghitung offset/durasi kandidatnya sendiri dan irisannya, dengan rumus yang diasersi identik dengan konstanta service.
- **Gate admin TIGA lapis** — middleware rute → `abort_unless` di `mount()` → `ShiftService::guardAdmin()` di tiap metode publik service. Konvensi proyek selama ini middleware saja; lapis ketiga ada karena business_logic #1 dan edge case menuntut "nol kueri shift" bagi non-admin. Memakai `abort(403)` alih-alih kelas exception baru, karena `ApiExceptionHandler` sudah menurunkan FORBIDDEN dari `HttpException` 403.
- **Entri sidebar ditambahkan ke `app.blade.php`** ← satu `<li>` berpenjaga `RouteAccess::allows('master-data.shifts')`, bersebelahan dengan Kelola Periode Pelaporan. Spec bisnis menyebut pintu masuknya "Menu sidebar Master Data → Kelola Shift"; tanpa entri itu layar hanya dapat dicapai dengan mengetik URL. `RouteAccess` membaca middleware `role:` milik rutenya sendiri, jadi tidak ada daftar peran yang diduplikasi. **Berkas ini permukaan bersama — blast radius-nya `tests/Feature/WebAccessTest.php`, dan ketiga belas test-nya diperiksa.**
- **`app/Models/Shift.php` dibuat di putaran layar ini, bukan oleh putaran entity-models** ← layar ini satu-satunya pemakainya, dan menjalankan ulang entity-models akan meregenerasi 54 model yang tidak berubah. Penyimpangan sadar; `project.4-implement.entity-models` TIDAK diperbarui.
- **`ShiftFactory` dibuat** karena ketiga suite backend butuh menanam shift 24 jam yang tidak dapat dibuat lewat API (lihat bug spec di atas).

### Yang membuat jebakan utamanya benar-benar terjaga

- **Sumbu menit, bukan string jam** — kasus jebakan (`01:00–03:00` lawan `23:00–07:00` yang sudah ada) diasersi di tiga lapis: unit (`findOverlap` tidak null + jendela persis 1080/1200 + `create()` melempar), API (422 `SHIFT_OVERLAP`), dan Livewire (pesan form menyebut "Malam"). Agen memverifikasi test itu benar-benar memerahkan implementasi string-compare lewat simulasi terpisah: `'01:00' < '07:00' && '23:00' < '03:00'` menyimpulkan "tidak bertindih (SALAH)" sementara sumbu menit menemukan irisan 120 menit. Unit test-nya juga mengasersi perbandingan string yang menyesatkan itu sendiri, sehingga jebakannya mendokumentasikan dirinya.
- **Kelas CSS diperiksa terhadap CSS HASIL COMPILE** (`view:cache`, token `class="…"` diekstrak dari view terkompilasi, selektor dikumpulkan dari `<style>` view yang benar-benar dikirim halaman ini): 72 kelas dipakai, 0 tanpa definisi. Dan pemeriksaan itu kini **ditegakkan permanen oleh suite Livewire** — jadi kelas cacat `kelola-machinery` sekarang jadi test, bukan kebiasaan.
- **Ambang 14px** — 13 deklarasi font-size, semuanya `--font-size-sm` (14px) atau `--font-size-base` (16px); nol `--font-size-xs`, nol literal px, nol literal warna. Pada viewport sempit label sumbu **dijarangkan** (`display:none` pada tick sekunder), bukan diperkecil.
- **Perbedaan arsiran dibaca dari CSS terkompilasi**: `backgroundImage` blok celah wajib memuat `gradient`, blok "belum diatur" wajib persis `none`.
- **Badge dibaca lewat elemen badge-nya**, dan satu test membuktikan "belum diatur" muncul lebih dari sekali di halaman — justru itu sebabnya pemindaian kontainer dilarang.

## v2 — 2026-10-16 (revisi soft delete + shift 24 jam)

Spec v2 diimplementasikan. Suite 5741 → **5767** (nol test lama dihapus, 5 direvisi di tempat, 26 ditambahkan), diverifikasi sendiri.

### Dua dugaan saya yang ternyata keliru — dan ini yang paling berharga dari putaran ini

- **Jalur berbahayanya bukan empat yang saya peringatkan.** Saya menulis bahwa daftar, cakupan, pemilih, dan himpunan pembanding tumpang tindih semuanya wajib mengecualikan baris tertutup, dengan `findOverlap()` sebagai yang paling mudah terlewat. Ternyata **ketiganya sudah tertangani global scope SoftDeletes** — dibuktikan dengan membuang SQL asli lewat `toSql()`, bukan dengan menalar. Yang **tidak** tertangani justru yang tidak saya sebut: `Rule::unique('shifts','name')` membangun query lewat **Query Builder**, bukan Eloquent, sehingga global scope tidak pernah berlaku padanya. Tanpa `whereNull('deleted_at')` eksplisit, lapisan aplikasi jadi LEBIH KETAT daripada partial index-nya, dan gejalanya terbaca sebagai bug index padahal berasal dari validator.
- **Tidak perlu cabang per-driver.** Saya mengantisipasi partial unique index butuh sintaks berbeda SQLite vs PostgreSQL dan menulis itu sebagai jebakan di brief. Ternyata DDL-nya sah **verbatim di keduanya**, diverifikasi lewat probe PostgreSQL sekali pakai. Jebakan SQLite-vs-PostgreSQL nyata di proyek ini, tapi tidak mengenai kasus ini.

### Turunan agent yang saya terima

- **`updated_by` dicap sebelum baris ditutup** ← spec dan katalog sama-sama diam, dan tidak ada kolom `deleted_by`. Karena baris tertutup kini hidup selamanya, tanpa ini tidak ada jejak SIAPA yang menutupnya. Harganya satu `UPDATE` tambahan di jalur hapus, diikat satu test khusus.
- **Kata-kata konfirmasi hapus tidak diubah** ← aturan bisnis hanya menuntut ia menyebut nama shift dan line-nya, dan itu sudah dipenuhi. Bahwa barisnya *ditutup, bukan dibuang* sengaja TIDAK ditampilkan ke Admin karena tidak ada teks spec yang memintanya. Bila penutupan ingin terlihat, itu perubahan tersendiri.
- **`lineHasOtherLiveShift()` `protected`, bukan public** ← hanya terlatih lewat `create()`/`update()`; kopling lebih baik, tapi test yang ingin mengujinya terpisah perlu tahu.
- **Satu test lebih ketat daripada spec** ← spec hanya meminta 422 untuk nama ganda yang hidup; test juga mengasersi `QueryException` di level DB, sehingga index yang tanpa sengaja dilonggarkan menjadi non-unique tidak bisa lolos selamanya di balik pemeriksaan aplikasi. Saya terima: itu memperkuat, bukan mengada-ada.
- **Dua skenario e2e digabung ke dua skenario lain** yang keadaan halamannya sama, dengan judul diubah dan asersi yang kurang ditambahkan, alih-alih menduplikasi setup. Keterlacakannya dijaga lewat judul dan komentar.
- **`deleted_at` tidak dimasukkan `$fillable`** ← `update(['deleted_at' => ...])` yang nyasar jadi no-op alih-alih penutupan tak terlihat.

### Koreksi atas pembacaan agen

Agen melaporkan `business_logic` 29–30 "menggambarkan mekanisme yang dibantah test case spec-nya sendiri". Itu salah baca: butir **30 adalah penyelaras butir 29** — ia menyatakan penggantian-saat-ubah belum aktif di putaran ini. Jadi 29 aturannya, 30 penundaannya, dan unit case yang menegaskan pengubahan memutasi baris menggambarkan perilaku yang berlaku sekarang. Spec-nya konsisten. Hasil kerjanya tetap benar — ia tidak membangun penggantian itu.

### Catatan DB dev

Migrasi soft delete diterapkan ke `mill_smart_log` oleh SAYA, berlingkup `--path=`, atas instruksi user ("jalankan migrasi shifts saja, app_name tunda dulu" — ini juga migrasi shifts). Diverifikasi sebelum dan sesudah: 91 → 92 migrasi (bukan 93), `deleted_at` ada, `app_name` MASIH ADA sehingga migrasi destruktifnya tetap tertunda, dan `pg_indexes` mengonfirmasi tepat satu index ber-predikat `WHERE (deleted_at IS NULL)`. Tanpa penerapan ini halaman Kelola Shift di dev akan error pada setiap kueri shift, karena global scope memancarkan `deleted_at is null` terhadap kolom yang tidak ada.

## v3 — 2026-10-16

Perbaikan cacat tampilan + dua perubahan rancangan yang menyertainya. Yang saya turunkan sendiri di putaran ini, dan alasannya:

- **`segment_index` / `segment_count` / `is_label_segment` pada tiap blok** ← blade butuh dasar EKSPLISIT untuk menautkan pasangan potongan dan memilih tempat label. Menebaknya dari offset adalah logika tampilan yang menyamar sebagai data, dan ia akan ditebak ulang (berbeda) oleh pemakai berikutnya.
- **Tanpa glyph panah pada potongan kedua.** Saya sempat menulis `↳` lalu mencabutnya: pada sumbu 00:00 potongan yang AWAL justru berada di tepi KANAN, jadi penanda berarah menunjuk ke arah yang salah. Yang menautkan keduanya adalah **nama dan rentang jam yang sama persis**; yang membedakan hanya bobot huruf.
- **Shift 24 jam TIDAK mendapat kasus khusus** ← 07:00–07:00 pada sumbu 00:00 keluar sebagai dua segmen bertaut yang menutup batang penuh. Kasus khusus hanya menambah cabang yang dapat salah sementara hasil gambarnya sama.
- **Urutan tabel dibandingkan pada `segment_index` 0, bukan pada seluruh blok** ← urutan seluruh blok tidak dapat sama dengan urutan tabel pada sumbu linier MANA PUN begitu sebuah shift punya potongan di kedua tepi. Aturannya tidak dilanggar; yang berubah adalah apa yang diukur untuk membuktikannya.
- **Pratinjau dua jalur, dan tracknya dua kali lebih tinggi** ← ditemukan dari POTRET PERAMBAN sesudah segmentasi benar, bukan dari test. Tinggi jalur dinaikkan sampai dua baris teks 14px muat UTUH di jalur bawah meski isinya digeser naik oleh pita tindihan; teks yang terpotong setengah huruf sama saja dengan teks yang tidak ada.
- **Penanda tindihan jadi PITA tepi bawah, bukan blok setinggi penuh** ← ketika kandidat bertindih seluruhnya, blok setinggi penuh mengubur label kandidatnya sendiri.

### Yang paling layak diingat dari putaran ini

**Test hijau tidak membuktikan tampilan benar, dan di sini 181 di antaranya hijau sementara sepertiga shift malam terpotong.** Setiap asersi geometri v2 menanyakan "apakah bloknya ada, terlihat, menempel tepi kanan" — ketiganya tetap benar untuk blok yang dipotong `overflow: hidden`. Satu pertanyaan yang tak satu pun ajukan membedakan keduanya: **adakah blok yang digambar di luar track-nya.** Asersi itu kini ada di tiga lapis dan ia menutup KELASNYA. Lihat [[feedback-vacuous-assertions]], [[feedback-design-quality-bar]], [[feedback-mocked-query-tests]].
