# Derived Assumptions — module-master-data.screen-170--kelola-shift.3-tech-spec

## v1 — 2026-10-15

Diturunkan dari spec bisnis screen-170 + pola `screen-128--kelola-periode-pelaporan` (master Admin-only terdekat) + konvensi rute master yang dibaca langsung dari `routes/web.php` dan `routes/api.php`.

### Pola yang diikuti dari kode, bukan dikarang

- **Rute web `/master-data/shifts`, Livewire `App\Livewire\MasterData\KelolaShift`, middleware `['auth','role:admin']`** ← dibaca dari `routes/web.php`: sembilan layar master lain memakai bentuk `/master-data/{resource}` yang sama persis.
- **Urutan pendaftaran rute API adalah bagian dari kontrak** ← dibaca dari komentar di `routes/api.php` yang mendokumentasikan jebakan `/periods/business-units/options` tertelan `/periods/{id}`. Diulang di sini sebagai aturan eksplisit, bukan ditemukan ulang nanti.

### Turunan agent

- **Enam endpoint, dua di antaranya literal** (`business-units/options`, `production-lines/options`) ← bentuk yang sama dengan master lain; tidak dinyatakan user.
- **`production_line_id` OPSIONAL pada daftar**, berbeda dari layar laporan tempat line WAJIB ← alasannya berbeda dan dinyatakan: laporan menghasilkan angka gabungan yang tidak bermakna bila mencampur line, sementara layar ini menampilkan daftar yang tiap barisnya menyebut line-nya sendiri. Lihat [[project-production-line-isolation]] — ini pengecualian sadar atas pola itu, bukan kelalaian.
- **CAKUPAN DIHITUNG DI SERVER dan diterbitkan sebagai `coverage[].blocks`** berisi `start_offset_minutes` dan `duration_minutes` ← pilihan agent, dan alasannya bukan kerapian: perhitungan yang sama akan dibutuhkan lagi oleh gerbang sinkronisasi dan oleh penandaan `shift_id` pada record, dan tiga penghitung yang berdiri sendiri akan berselisih.
- **Offset diukur dari 07:00, bukan dari tengah malam** ← konsekuensi langsung dari hari produksi 07:00–06:00 yang terverifikasi di kisi `(7+i)%24`. `start_offset_minutes` 0 berarti 07:00.
- **Shift melewati tengah malam dipecah dua HANYA di dalam perhitungan tumpang tindih**, tidak pernah di payload maupun tampilan ← pilihan agent; memecahnya di payload akan membuat blade menggambar dua potongan di kedua ujung batang, persis yang dihindari dengan memulai timeline di 07:00.
- **PERBANDINGAN RENTANG DI SUMBU MENIT 0..1440, BUKAN PADA STRING JAM** ← dan ini butir terpenting di berkas ini. Membandingkan `'23:00'` dengan `'07:00'` sebagai string menyimpulkan rentangnya kosong, sehingga shift malam tidak pernah terdeteksi bertindih — dan hasil salahnya terlihat masuk akal. Kelas kesalahan yang sama dengan cacat pengurutan slot di laporan Engine Room yang baru diperbaiki. Satu unit case ditanam KHUSUS untuk memerahkan implementasi string-compare itu.
- **Tumpang tindih = irisan LEBIH BESAR dari nol menit** ← yang membuat batas inklusifnya jatuh pada tempat yang benar: shift berakhir tepat di jam mulai shift lain beririsan nol, jadi diterima sebagai pergantian normal.
- **`state` punya TIGA nilai: `covered` | `gap` | `unconfigured`** ← pilihan agent, dan `unconfigured` sengaja BUKAN `gap` dengan `uncovered_minutes` 1440. Membedakannya DI PAYLOAD-lah yang memungkinkan blade membedakannya di layar; kalau payload menyamakan keduanya, tampilan tidak punya dasar untuk memisahkannya.
- **`position` diturunkan server dan dipakai blade untuk mengurutkan TABEL maupun TIMELINE** ← satu sumber urutan untuk dua tampilan; dua pengurutan yang berdiri sendiri adalah cara tercepat membuat keduanya berselisih.
- **`starts_at_production_day` adalah peringatan, bukan penolakan** ← mengikuti keputusan di spec bisnis.
- **`production_line_id` milik mill lain ditolak 422, bukan 403** ← pilihan agent: bagi Admin yang tidak terikat mill, itu parameter yang keliru dan bukan penolakan akses.
- **`start_time == end_time` ditolak sebagai VALIDATION_ERROR, bukan SHIFT_OVERLAP** ← dua kesalahan berbeda dengan pesan berbeda.
- **Kode galat `SHIFT_OVERLAP` dipakai sebagai kode tersendiri** ← pilihan agent; ia menuntut pesan yang menyebut shift pembanding dan rentang tindihannya, dan kode generik akan kehilangan tuntutan itu.
- **Pratinjau klien adalah SATU-SATUNYA perhitungan cakupan di sisi klien, dan BUKAN sumber kebenaran** ← dinyatakan eksplisit supaya tidak terbaca melanggar prinsip "server yang menghitung"; penolakan tetap datang dari server saat Simpan, dan pratinjau wajib memakai rumus offset yang sama persis.
- **`coverage` tidak ikut berpaginasi** ← ia merangkum cakupan line, bukan mendaftar baris. Pola yang sama dengan panel card screen-128 yang juga tidak ikut berpindah halaman.
- **Tidak ada parameter tanggal pada satu pun endpoint**, dan ketiadaan itu diasersi oleh sebuah unit case ← konsekuensi keputusan user "jadwal sama setiap hari"; diasersi supaya ketiadaannya tidak terbaca sebagai kelalaian lalu "dilengkapi".
- **Unique index `(production_line_id, name)` di migrasi; tumpang tindih TIDAK ditegakkan index** ← tumpang tindih rentang tidak dapat dinyatakan sebagai unique constraint; ia ditegakkan di lapisan aplikasi, sama seperti aturan rentang tanggal pada Periode Pelaporan.
- **Asersi badge status menyasar ELEMEN badge, bukan memindai kontainer** ← 'belum diatur' muncul juga di kalimat penjelas di bawah batang, jadi pemindaian kontainer akan cocok di tempat yang salah. Lihat [[feedback-vacuous-assertions]].
- **Spec browser mengasersi invarian GEOMETRIS yang diukur dari DOM** (urutan blok = urutan baris, blok melewati tengah malam adalah SATU elemen yang menempel ujung kanan, lebar sebanding durasi, nol gulir mendatar halaman) dan tidak memaku lebar piksel ← lebar bergantung viewport.
- 45 `unit_test_cases` · 28 `test_scenarios` (satu per bdd, berurutan, diverifikasi cocok persis) · 25 business_logic · 22 edge_case_handling · 17 implementation_notes ← seluruhnya turunan agent.
