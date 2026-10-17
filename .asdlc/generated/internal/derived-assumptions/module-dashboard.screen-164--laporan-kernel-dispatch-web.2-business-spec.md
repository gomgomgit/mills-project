# Derived Assumptions — module-dashboard.screen-164--laporan-kernel-dispatch-web.2-business-spec

## v1 — 2026-10-18

- Seluruh isi layar ini DITURUNKAN dari skema `kernel_dispatch_details` dan dari pola enam belas laporan periode sebelumnya, bukan dari wawancara. Pengguna hanya menyatakan "selesaikan laporan stasiun sampai semua stasiun selesai".
- Bentuk laporannya HIBRIDA: hitungan transaksi + ukuran mutu per pengiriman ← diturunkan dari skema (ada `event_date` tanpa kolom waktu DAN empat kolom persen). Belum ada preseden untuk gabungan ini; Weighbridge/Grading/Solid Waste hanya menghitung, laporan bermutu sebelumnya bergrid per jam.
- EMPAT ukuran mutu dipilih sebagai metrik laporan: kadar air inti, kadar kotoran, ALB, inti pecah ← saya pilih keempat kolom persen itu dari skema; pengguna tidak menyebut ukuran mutu mana pun.
- Setiap ukuran mutu menerbitkan PENYEBUTNYA SENDIRI, dan kenyataan bahwa keempatnya boleh berbeda DINYATAKAN di layar ← keputusan saya; diturunkan dari fakta bahwa keempat kolom nullable secara independen.
- TIGA rekap kategoris (pengangkut, silo sumber, pembeli tujuan) ← saya pilih ketiga kolom teks itu; pengguna tidak menyebut rekap apa pun.
- `silo_source_id` diperlakukan sebagai TEKS, bukan rujukan ← DIVERIFIKASI: kolomnya `string` nullable, TIDAK ADA tabel silo di migrasi mana pun, dan form web merendernya sebagai `<input type="text">`. Nama berakhiran `_id` itu menyesatkan, dan andaian wajar bahwa ia foreign key akan menghasilkan join yang tidak mungkin.
- `remarks_gate_status` diperlakukan sebagai ENUM SUNGGUHAN, satu-satunya di stasiun ini ← DIVERIFIKASI: form web merendernya sebagai `<select>` dengan tepat dua pilihan (`released` / `not_released`) plus kosong. Berbeda dari `action_taken_status` di Process Water, yang `<input type=text>` dan karena itu TIDAK disebar.
- TETAPI nilai di luar kedua pilihan ditampilkan APA ADANYA ← DIVERIFIKASI: `KernelDispatchRecordService` hanya menyimpan `filled(...) ? $value : null`, tanpa daftar tertutup, sehingga jalur selain form web dapat menyimpan apa pun. `Display::option()` memang jatuh ke nilai mentah untuk yang tak dikenal, dan itu perilaku yang diinginkan.
- 'Tidak tercatat' TIDAK dihitung sebagai 'tidak dilepas' ← keputusan saya; menggabungkannya akan menuduh pengiriman yang sekadar belum diisi statusnya.
- Pengiriman 'tidak dilepas' DITONJOLKAN sebagai angka tersendiri di luar rekapnya ← keputusan saya; ia kejadian yang perlu dilihat, bukan baris yang perlu dihitung.
- TIGA penghitung kepatuhan terpisah (surat jalan, segel atas, segel bawah), dan segel atas/bawah TIDAK PERNAH dijumlahkan ← keputusan saya; satu kendaraan kehilangan satu segel adalah keadaan berbeda dari dua kendaraan masing-masing kehilangan satu.
- TANPA sebaran per jam DAN tanpa waktu putar kendaraan, keduanya dinyatakan di layar beserta sebabnya ← diturunkan dari ketiadaan kolom waktu. Disebutkan pula bahwa di CPO Dispatch keduanya justru ADA — supaya ketiadaan di sini tidak dibaca sebagai kelalaian dan supaya tidak ada yang menambahkannya dengan menebak jam dari waktu input.
- TANPA penandaan nilai mutu di luar batas ← diturunkan dari ketiadaan master ambang mutu untuk stasiun ini.
- HANYA satu kolom teks kejadian (temuan), tanpa pasangan 'catatan' ← diturunkan dari skema; berbeda dari Solid Waste Disposal yang punya dua.
- Ekspor CSV dan Excel keduanya ada ← ini layar WEB; pola enam belas laporan web sebelumnya.
- `test_priority` = high ← 24 aturan, 20 kasus tepi, dan gabungan dua bentuk laporan yang belum pernah digabung.

## Keputusan yang MELEBARKAN cakupan, dan sebabnya — perlu diketahui pemilik produk

- `Display::OPTION_LABELS` DITAMBAH dua entri (`released` → 'Released', `not_released` → 'Not Released'), dan `detail-kernel-dispatch.blade.php` diubah untuk MENDELEGASIKAN ke `Display::option()` alih-alih mencetak kode mentah.
  - **Sebabnya:** laporan ini butuh label yang sama dengan form, dan aturan rumah proyek ini melarang menuliskan ulang pemetaan di tempat kedua (keputusan 2026-10-14: kolom enum pengukuran memakai `option()`, berlaku di mana pun termasuk layar laporan; blade mendelegasikan, jangan menyalin). Label dulu hidup HANYA di satu `<select>`, karena belum ada pembaca ketiga. Laporan ini pembaca ketiga itu.
  - **Terverifikasi aman:** kedua nilai itu NOL kemunculan di luar form kernel-dispatch; NOL test mengasersikan nilai mentahnya; `Display::option()` sudah mengembalikan '-' untuk kosong sehingga perilaku null tidak berubah. Suite yang menyentuhnya: 111 lolos.
  - **Efek sampingnya DISENGAJA dan menguntungkan:** layar Detail Kernel Dispatch berhenti mencetak `not_released` kepada manusia.
  - Label disalin VERBATIM dari form, tidak diterjemahkan — dua label untuk satu nilai adalah dua kosakata.
