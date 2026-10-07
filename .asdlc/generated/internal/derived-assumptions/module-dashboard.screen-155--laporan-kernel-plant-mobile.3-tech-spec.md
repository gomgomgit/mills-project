# Derived Assumptions — module-dashboard.screen-155--laporan-kernel-plant-mobile.3-tech-spec

## v1 — 2026-10-07

Autonomy `autopilot`: seluruh isi tech spec ini rumusan agent, diturunkan dari business spec
screen-155 dan dari implementasi screen-154 yang baru mendarat.

- Rute `report-kernel-plant` di `/reports/kernel-plant`, berkas `kernelPlantReportRepo.ts` + `LaporanKernelPlantView.vue`, satu entri router, satu baris peta pemilih ← seluruhnya pola sepuluh laporan mobile sebelumnya, bukan dinyatakan user.

- NOL perubahan backend ← bukan kebetulan, melainkan konsekuensi urutan pembangunan: keempat rute sudah menerima peran mobile sejak screen-154 karena kedua layar direncanakan satu seri. Pasangan Weighbridge (143 → 144) harus menambal tiga tempat sesudahnya justru karena layar web-nya mendarat sebelum kembaran mobile-nya diketahui.

- TIGA KOREKSI TERHADAP SPEC DAN INSTRUKSI SAYA SENDIRI, semuanya ditemukan saat implementasi dan diselesaikan dengan mengikuti KODE, bukan spec:
  1. Saya menulis "keempat endpoint menerima peran mobile" — hanya TIGA. `/business-units/options` menolak Operator/Supervisor/Mill Management dengan 403 dari dalam service. Konsekuensinya justru lebih baik dan disengaja: layar ini tak punya jalur kode ke sana sama sekali, jadi `business_unit_id` tak dapat terkirim karena KETIADAAN JALUR, bukan karena penjaga.
  2. Skema respons `/periods` di draf pertama saya memuat `business_unit_name` — medan itu TIDAK ADA. `periodOption()` (baris 1798–1805) mengembalikan `station_type` dan `station_type_label`. Nama mill dibaca dari `summary.business_unit.name` saja, satu sumber, supaya keduanya tak dapat menyimpang.
  3. Saya menulis satuan suhu `(°C)`; master menerbitkan `C`. Klien merendernya VERBATIM — memetakan `C`→`°C` di klien tepat jenis normalisasi yang dilarang aturan nol-aritmetika, dan akan membuat ponsel berselisih dengan web tentang medan yang sama. Bila `°C` yang diinginkan, satu baris di `KernelPlantReportService::METRIC_LABELS` — satu tempat, dua layar. Itu suntingan backend dan tidak dilakukan.

- DUA DEFEK LAMA di Depricarping mobile yang sengaja tidak disalin, keduanya diverifikasi dengan grep: `.metric-values` dipakai tiga kali di `LaporanDepricarpingView.vue` (baris 1485, 1498, 1512) tetapi TIDAK TERDEFINISI di satu berkas pun di seluruh `src/`; dan keterangan cakupannya menyebut "minimal satu dari ENAM kolom bacaan" padahal docblock repo-nya sendiri dan backend menyebut SEMBILAN. Keduanya berkas layar lain dan tidak diperbaiki di sini.

- SATU PENYIMPANGAN dari sepuluh laporan mobile saudaranya yang layak dilihat manusia: layar ini tidak punya `scopeParams`/`isAdmin`/`fetchBusinessUnits` sama sekali, sementara kesepuluh repo lain punya ketiganya dan kesepuluh view lain merender pemilih mill Admin. Dasarnya: `actor_permissions` tech spec ini (admin dan mill-management `can_access: false`), `api_contracts` yang tidak memuat `/business-units/options`, dan controller yang menolaknya 403. Catatan untuk arsip: tech spec screen-153 juga mengecualikan Admin, tetapi implementasi Depricarping tetap membawa cabang Admin — jadi penyimpangan ini disengaja dan berbeda dari saudaranya.

- `targets-without-metric-reason` mencetak kalimat TETAP ("Tidak ada kolom pengukurannya") tanpa membaca `target.reason` ← keputusan yang diperiksa lalu dibiarkan: `no_column` satu-satunya nilai yang dapat dicapai hari ini (`UNMAPPED_NO_COLUMN`), jadi mengkodekan nilai yang belum bisa diproduksi siapa pun justru lebih buruk daripada kalimat tetap. KONSEKUENSINYA DICATAT, bukan diuji: bila suatu saat ada alasan kedua di server, layar ini akan salah melabelinya dan suite tidak akan menangkapnya.

- Satu kelas CSS baru didefinisikan, `.detail-table td.wrap` (`white-space: normal; min-width: 180px; overflow-wrap: anywhere`) ← untuk edge case "temuan sangat panjang": Depricarping memasang `white-space: nowrap` pada setiap sel, yang tidak memotong apa pun tetapi mendorong temuan panjang menjadi gulir-dalam-kartu yang sangat lebar. Dua definisi Depricarping yang tak terpakai (`.metric-figure`, `.metric-unit`) dibuang alih-alih dibawa sebagai CSS mati.

- SPEC PEMILIH STASIUN HARUS IKUT BERUBAH, dan itu DISENGAJA oleh perancangnya: `tests/ReportingPilihStasiunView.spec.ts` memakai `kernel-plant` sebagai fixture kanonis "laporan belum dibangun", jadi membangun laporan ini membuat 7 ujinya gagal. Docblock-nya (baris 21–30) menyatakan bahwa contohnya sudah EMPAT KALI dipindahkan karena laporannya memang dibangun, dan "dipindahkan, BUKAN dihapus ... spec ini akan gagal lagi — dan itu benar, karena ia memaksa contohnya diperbarui alih-alih diam-diam menjadi selalu hijau." Pemindahan KELIMA dilakukan ke `process-quality-control`, dipilih karena sort_order-nya 180 — paling akhir dari seluruh jenis stasiun — sehingga pemindahan keenam tertunda paling lama. Alasan itu ditulis ke docblock spec tersebut.
