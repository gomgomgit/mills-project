# Derived Assumptions — module-dashboard.screen-160--laporan-process-water-web.3-tech-spec

## v1 — 2026-10-16

Diturunkan dari spec bisnis screen-160 + pola screen-158 (Engine Room web, laporan bergrid paling mutakhir).

### Fakta yang dibaca langsung, bukan diandaikan

- **Posisi di `REPORT_ROUTES`**: `process-water` (sort_order 130) disisipkan **antara EngineRoom (120) dan StorageTank (140)**, dibaca dari urutan peta yang berjalan sekarang (13 entri).
- **Riak asersi tetangga: TEPAT SATU** — `EngineRoomReportServiceTest.php:2805`, penerus engine-room yang harus berubah dari `StorageTank` ke `ProcessWater`. Diverifikasi lewat `grep` atas `at + 1]` dan `at - 1]` di seluruh `backend/tests`: asersi pendahulu engine-room (2804) dan kedua asersi Depricarping **tidak** terpengaruh, dan Storage Tank tidak punya asersi tetangga sama sekali. Lebih kecil daripada Engine Room yang menyentuh dua.
- **`READING_FIELDS` dan `isRowFilled()` masih `protected`** di `ProcessWaterRecordService`; `canonicalTimeSlots()` sudah `public static`.
- **Delapan entitas yang dirujuk semuanya ada di katalog** (`process-water-record`, `process-water-detail`, dan enam lainnya).

### Turunan agent

- **Empat endpoint di bawah `/api/process-water-reports`** mengikuti bentuk tiga belas laporan terdahulu; tidak dinyatakan user.
- **Operator diterima pada ketiga rute data sejak commit pertama** ← presedens Grading/Kernel Plant/Engine Room, karena screen-161 menyusul langsung.
- **Empat `group` metrik: `water_flow` | `softener` | `chemical_dosing` | `boiler_feed`** ← nama grup dan urutannya mengikuti RANTAI PENGOLAHAN AIR, bukan urutan kolom di basis data. Blade membangun blok dari respons.
- **`text_entries` sebagai kunci yang memuat dua daftar bernama**, bukan dua kunci sejajar di akar payload ← pilihan agent; keduanya sejenis dan mengelompokkannya membuat ketiadaan sebaran kategoris lebih kentara.
- **`inspector_recap` memakai bentuk `{entries, not_recorded, recorded_slots}`** yang sengaja MENIRU bentuk `status_distribution` di laporan lain — meski isinya bukan kategoris. Alasannya: `not_recorded` terpisah dan penyebut `recorded_slots` adalah dua aturan yang sama-sama berlaku di sini, dan memakai bentuk yang sudah dikenal membuat blade serta test-nya seragam dengan laporan lain.
- **Tren harian memakai `raw_water_flow_m3h`** sebagai satu-satunya seri ← metrik paling hulu dan paling mewakili beban stasiun.
- **Keputusan user 2026-10-16 yang dikunci di spec ini**: `action_taken_status` teks bebas (tiga bukti: form web `<input type="text">`, label mobile `"Action Taken/Status"` dengan garis miring, nol entri `Display::OPTION_LABELS`), dan `inspector_id` teks bebas yang **tetap ditampilkan** meski menyimpangi preseden Storage Tank.
- **`ExportValue::option()` tidak dipakai sama sekali**, dan ketiadaannya diasersi satu unit case ← stasiun ini tanpa kolom enum pengukuran, sehingga jebakan tukar-pemeta `status()`/`option()` yang membayangi laporan lain **tidak berlaku di sini**. Dicatat supaya tidak ada yang "melengkapinya".
- **Kolom `shift` tidak di payload maupun layar, TAPI TETAP di ekspor** ← ekspor adalah rincian mentah; pembedaan ini ditanam sebagai skenario tersendiri karena mudah disederhanakan menjadi "buang di mana-mana".

### Jebakan utama yang ditanam sebagai kasus uji

**Aturan sebelas-bukan-tiga-belas** adalah yang paling mudah salah diimplementasikan, karena menghitung ke-13 kolom adalah yang tampak wajar dari skema. Satu unit case menanam **dua record berlawanan sekaligus** — satu hanya mengisi `inspector_id`, satu hanya mengisi `findings` — dan menuntut `filled_slots == 1`. Implementasi yang menghitung 13 kolom memulangkan 2 dan memerah di situ. Skenario padanannya ada di `test_scenarios` butir 10.

Instruksi implementasinya tegas: **pinjam `isRowFilled()` dari service**, jangan menulis ulang pemeriksaannya.

### Jumlah

4 endpoint · 32 business_logic · 8 data_operations · 31 edge_case_handling · 30 business_rules_applied · **52 unit_test_cases** · **33 test_scenarios** (cocok persis dan berurutan dengan bdd, nol duplikat, nol endpoint asing, status {200,403,422}) · 8 screen_dependencies · 15 implementation_notes.

## v2 — 2026-10-16 (koreksi sesudah implementasi)

**Satu klaim di v1 terbukti salah, dan saya yang menulisnya.** `implementation_notes[2]` dan `screen_dependencies[0]` menyatakan "TEPAT SATU asersi tetangga akan gugur". Yang sebenarnya gugur: **enam asersi di empat berkas**, dalam dua kategori.

**Kenapa gagal diantisipasi:** saya mencari riak dengan `grep -rn 'at + 1\]\|at - 1\]'` — pola asersi *tetangga* pada `REPORT_ROUTES`. Grep itu benar dan menemukan satu-satunya anggota kategorinya. Yang ia tidak bisa temukan adalah kategori kedua: test yang memakai `process-water` sebagai **contoh "laporan belum dibangun"**. Bentuknya tidak menyebut urutan, tidak menyebut `REPORT_ROUTES`, dan satu-satunya yang menghubungkannya adalah **kodenya sendiri**. Membangun laporan sebuah stasiun MEMBALIK jawaban setiap test semacam itu.

**Grep yang benar, dan ia sepele:** `grep -rln '<kode-stasiun>' backend/tests`, lalu buang berkas milik stasiun itu sendiri. Sisanya adalah blast radius-nya. Dicatat di tech-spec supaya screen-161 dan empat jenis stasiun terakhir mewarisi angka yang benar, bukan angka yang menenangkan.

**Perbaikannya selalu sama:** pindahkan contohnya ke jenis stasiun yang masih belum berlaporan (kini `solid-waste-disposal`), jangan dihapus — **dan asersikan ALASAN pindahnya** (`REPORT_ROUTES` punya kunci lama, tidak punya kunci baru). Pada dua berkas saya juga menambahkan pasangan positifnya: Process Water kini AKTIF. Tanpa pasangan itu, test hanya membuktikan ada satu tile nonaktif di suatu tempat — bukan bahwa tile berubah keadaan ketika laporannya mendarat.

**Tenggat yang sekarang terlihat:** tersisa empat pembawa contoh (solid-waste-disposal 150, kernel-dispatch 160, cpo-dispatch 170, process-quality-control 180). Sesudah keempatnya berlaporan, contohnya HABIS dan test-test itu harus ditulis ulang dengan kode fiktif yang disuntikkan — kalau tidak, ia akan menghijau tanpa pembawa. Paling murah dikerjakan sekarang. Lihat [[project-station-report-addition-ripples]] dan [[feedback-vacuous-assertions]].
