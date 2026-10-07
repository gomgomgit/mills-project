# Derived Assumptions — module-dashboard.screen-154--laporan-kernel-plant-web.4-implement

## v1 — 2026-10-07

Autonomy `autopilot`; checkpoint pra-implementasi Section 3b DILEWATI atas keputusan user untuk
rangkaian ini ("Lewati — jalan terus sampai semua selesai"). `spec_mismatch` tetap blocking dan
tidak pernah terpicu.

- Implementasi diturunkan sebagai PORT baris-demi-baris dari keluarga Depricarping, bukan ditulis dari nol ← keputusan agent. Depricarping dipilih sebagai model (bukan Threshing/Pressing) karena ia satu-satunya yang sudah punya ketiga hal yang juga ada di Kernel Plant: `downtime_minutes` numerik, `findings` teks bebas, dan kolom yang berbagi satu standar.

- PRASYARAT yang dikerjakan lebih dulu dan bukan bagian dari laporan: `KernelPlantRecordService` mendapat `public const READING_FIELDS` dan `isRowFilled()`-nya dijadikan publik serta mengulangi konstanta itu. Perilakunya identik — sembilan kolom yang sama, semantik OR yang sama, asimetri `''`-kosong-hanya-untuk-`findings` yang sama — dan dibuktikan dengan 130 uji layar input yang tetap lolos. Tetapi ia TETAP perubahan pada berkas yang sudah teruji, bukan hanya penambahan, dan dicatat terpisah justru karena itu.

- SATU DEFEK KEAMANAN DITEMUKAN DAN DITUTUP. `productionLineInfo()` versi pertama mencari line dengan `find($id)` tanpa klausa `business_unit_id`, sehingga `GET /summary?production_line_id=<line mill lain>` menjawab 200 berisi id DAN NAMA MANUSIAWI line yang pemanggil tak berhak melihatnya. Angkanya aman dengan sendirinya (`scopeToProductionLine()` menyaring ke nol baris, query tetap terkurung pada mill periode); bloknya tidak. Ia merusak justru alasan line mill lain DIABAIKAN alih-alih ditolak 403. Dibuktikan lewat permintaan HTTP sungguhan sebelum diperbaiki, ditutup dengan satu klausa `where`, dijaga uji regresi di skenario 14 uji Api yang sudah dibuktikan bergigi dengan membalik perbaikannya. BENTUK TANPA SCOPE ITU ADA IDENTIK DI KESEPULUH REPORT SERVICE LAIN dan di sana MASIH TERBUKA — tidak diperbaiki karena mengubah sepuluh laporan yang sudah berjalan adalah keputusan pemilik produk, bukan efek samping penambahan laporan kesebelas.

- SATU DEFEK TAMPILAN DITEMUKAN DAN DITUTUP. Keterangan berbagi standar mencetak `equipment_parameter` tanpa penjaga null, sehingga ketika nama parameter master disunting ia merender `master hanya memuat satu baris "" untuk keduanya` — tanda kutip kosong yang mengklaim baris master yang justru baru lepas, tepat pada keadaan layar yang aturan "suntingan harus terlihat" dibangun untuk itu. Fallback nilai tidak cukup: kalimatnya SENDIRI menjadi tidak benar, jadi yang bercabang adalah pernyataannya. Dijaga uji regresi di skenario 10 uji Livewire, juga sudah dibuktikan bergigi.

- `kernel_plant_name` = `kernel_plant_id` ← keputusan agent, diverifikasi: `kernel_plant_records.kernel_plant_id` adalah kolom `string` biasa (migrasi 2026_08_23_000011) berisi LABEL unit yang ditulis tangan, dan TIDAK ADA tabel master `kernel_plants` di skema ini. Diisolasi di `kernelPlantNameOf()` supaya memperkenalkan master kelak adalah satu perubahan metode tanpa mengubah bentuk payload.

- TIGA PENYIMPANGAN SADAR dari tata letak Depricarping, masing-masing karena asersi spec menuntutnya: tabel parameter tetap dirender ketika `has_data` false (Depricarping menggantinya dengan empty state); kartu KPI downtime selalu dirender dengan tanda pisah di dalam selnya (Depricarping menukar seluruh baris kartu dengan satu kalimat, dan kalimat tidak punya sel untuk diasersikan); keterangan "periode sedang berjalan" menyebut TANGGAL hari terakhir terhitung, dilewatkan dari `render()` sebagai `countedUntil` supaya dapat diasersikan tanpa DOM dan supaya blade tidak menyentuh jam.

- Tabel parameter TUJUH kolom, bukan delapan ← master Kernel Plant berbentuk tiga kolom (`equipment_parameter`/`target_benchmark`/`corrective_action_plan`), tanpa `critical_limit` dan tanpa `operational_consequence_justification`. Tidak ada medan yang dikarang sebagai kunci selalu-null agar "sebangun" dengan Depricarping — itu akan memberi layar dua sel yang tak seorang pun dapat mengisinya.

- `coverage: -1` pada `test_results.unit` adalah SENTINEL, bukan pengukuran ← template menuntut angka dan menolak null, sementara cakupan baris memang TIDAK DIUKUR pada jalan ini: `php artisan test --coverage` keluar dengan kode 0 dan diam tanpa driver Xdebug/PCOV, dan `composer test:coverage` (gerbang yang menolak keadaan itu) tidak dijalankan. 0 akan mengklaim sudah diukur dan hasilnya nol, yang lebih buruk daripada jelas-jelas tak masuk akal.

- Satu kegagalan uji FLAKY teramati sekali pada satu jalan suite penuh (`DashboardServiceTest`, "returns success result with correct counts") dan TIDAK terulang pada dua jalan berikutnya maupun saat dijalankan sendiri dua kali. `DashboardService` tidak bersinggungan dengan satu pun berkas yang diubah di sini (diperiksa dengan grep). Dicatat alih-alih disembunyikan; bukan diklaim sudah beres.

- Instruksi saya sendiri kepada agent uji bahwa `station_types` perlu diseed TERNYATA SALAH, dan agent memeriksanya alih-alih menurut: migrasi `2026_09_22_000029` menyisipkan master itu sendiri, jadi barisnya ada setelah `RefreshDatabase`. Penyemaian hanya perlu untuk uji yang MENGGANTI master, seperti `LaporanStasiunTest`.
