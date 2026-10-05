# Derived Assumptions — module-dashboard.screen-131--laporan-boiler-room-web.4-implement

## v1 — 2026-09-24

- **`READING_FIELDS` dan `isRowFilled()` dilebarkan `protected` → `public` di `BoilerRoomRecordService`** ← berkas ke-11, di luar daftar 10 yang saya rencanakan. Agen mengangkatnya sebagai deviasi dan saya menyetujuinya setelah membaca diff-nya: murni aditif, nol perubahan perilaku, 8 berkas test konsumen tetap hijau. Alternatifnya menyalin daftar 15 kolom ke service laporan — dan dua salinan definisi "baris terisi" adalah persis bagaimana laporan dan formulir mulai berbeda pendapat tentang apa yang tercatat.
- **`slots_per_unit_per_day` = 24 dari `canonicalTimeSlots()`, bukan 12 dari mock** ← mock memakai 12 untuk menyusun dataset yang rapi dan menandainya sendiri sebagai kemudahan mock. Angka sebenarnya diambil dari kode dan dipublikasikan di `coverage`, bukan disembunyikan sebagai konstanta — kalau tidak, persentase kelengkapan menjadi angka ajaib yang tidak dapat ditelusuri.
- **Entri `REPORT_ROUTES` ditaruh terakhir** ← `LaporanStasiunTest` melakukan `toBe()` terurut ketat terhadap `array_keys(REPORT_ROUTES)`, dan `sort_order` boiler-room 90 > sterilizer 40. Bukan pilihan gaya; urutan yang salah memerahkan penjaga itu.
- **`buildExportRows()` menjaga izin secara eager lalu menghasilkan baris secara lazy** ← metode bertipe `Generator` akan menunda penolakan sampai iterasi pertama, sehingga 403/422 tampak seperti respons kosong yang berhasil. Tidak diminta spec.
- **Ketiadaan penandaan ambang DIASERSI menurut nama**, bukan sekadar tidak diimplementasikan ← termasuk asersi bahwa kartu berisi nilai ekstrem membawa atribut `class` yang sama dengan kartu biasa. Aturan "jangan menandai" hanya bertahan kalau ada test yang menjaganya; tanpa itu, seseorang akan "melengkapinya" kelak dengan niat baik.
- **Bukti urutan penjaga peran lewat jenis exception** ← Operator dengan `period_id` tidak ada menerima `AuthorizationException` dan eksplisit BUKAN `ModelNotFoundException`. Itulah yang membuktikan guard berjalan sebelum `findOrFail`, sehingga keberadaan `period_id` tidak bocor lewat selisih 403 vs 404. Cara pembuktian ini tidak disebut spec.
- **Test pembagi `avg_per_day` diperkuat, bukan ditukar angkanya** ← fixture dipegang pada `days_in_period = 2 × days_with_records` agar 1,0 dan 0,5 tidak pernah diam-diam berimpit, plus asersi pendamping atas kedua pembilang/pembagi dan `not->toBe(0.5)`. Diverifikasi di sumber bahwa `maintenanceOf()` memakai variabel yang sama dengan yang dipublikasikan sebagai `total.days_with_records`, jadi asersinya mengunci pembagi sungguhan.
- **Rekap harian memakai tombol + markup ber-`@if`, bukan `<details>`** ← konsekuensi kontradiksi spec: `<details>` tertutup tetap menyimpan anaknya di DOM, sehingga "hilang dari DOM setelah ditutup" mustahil dipenuhi. Berbeda dari screen-130 yang memakai `<details>`; perbedaan itu disengaja dan berasal dari skenario testnya sendiri.
- **Selector CSS baru diverifikasi tidak dapat meregresi layar lain** ← `.md-card > .md-recap` hanya berlaku bila `.md-recap` adalah anak LANGSUNG `.md-card`; sterilizer dan cages-track menaruhnya di dalam `<details>`, jadi tidak tersentuh. Pemeriksaan ini tidak diminta, tetapi menambah selector ke partial bersama tanpa memeriksanya adalah cara khas meregresi layar yang tidak sedang dikerjakan.

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v2 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser = 21 lulus/0 gagal dari e2e-full.log.counts.json (spec laporan-boiler-room); 1 skip dicatat di catatan.
- Known_issue flake support/auth.ts dipertahankan — bukan 'tidak dijalankan'.

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (git status/diff BoilerRoomReportService.php, laporan-boiler-room.blade.php, report-styles.blade.php, app/Support/*).
- files_generated (+4) = Support/ReportPeriodDays.php, SheetWriter.php, ExportValue.php, Display.php ← dipakai service (Display via ExportValue::status)
- test_files_generated (+2) = tests/Feature/ReportAuditFix20261004Test.php, tests/Feature/ExportXlsxTest.php ← keduanya memuat kasus boiler-room
- known_issues = hapus 'format=excel menyajikan badan CSV' ← SheetWriter menulis xlsx sungguhan
- implementation_notes (+1) = REVISI audit-fix

## v4 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit db73fbd, c321f32), code is truth (laporan-*.blade.php, report-filter-bar.blade.php, tests/Feature/Livewire/Laporan*Test.php, e2e-web/tests/laporan-*.spec.ts).
- implementation_notes[+] ← report-filter-bar, loading ekspor & .ld-region, uji yang disesuaikan
