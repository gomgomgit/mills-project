# Derived Assumptions — module-dashboard.screen-162--laporan-solid-waste-disposal-web.2-business-spec

## v1 — 2026-10-17

### Satu fakta struktural yang saya kejar sebelum menulis sebaris pun

**`net_weight_mt` TIDAK ADA di `DETAIL_FIELDS`, dan itu BENAR.** Kolomnya ada di migrasi tetapi absen dari daftar isian — awalnya tampak seperti kelalaian yang akan membuat laporan melaporkan nol ton. Ternyata ia **diturunkan service**: `upsertDetails()` menghitung `net = gross − tare`, dan `DETAIL_FIELDS` memang daftar kolom yang diisi manusia. Diverifikasi di `SolidWasteDisposalRecordService.php`, bukan disimpulkan dari nama.

Konsekuensinya mengikat seluruh laporan: **netto null berarti penimbangan BELUM SELESAI**, bukan nol. Itu padanan persis "trip yang beratnya belum terisi" di laporan Weighbridge, dan harus ditonjolkan dengan alasan yang sama — muatan yang belum selesai tidak menurunkan rata-rata, ia **hilang** darinya.

### Yang saya turunkan

- **Pola laporan = Weighbridge (screen-143), bukan Process Water.** Diverifikasi dari skema: baris detail berkunci `event_date`, bukan `time_slot`. Menyalin pola laporan yang baru saja saya kerjakan akan menghasilkan "min/avg/max per 24 slot" untuk data yang tidak punya 24 slot.
- **SEBARAN PER JAM dinyatakan MUSTAHIL, bukan sekadar tidak ada** ← baris detail hanya punya `event_date`, nol kolom waktu. Weighbridge bisa menjawab "jam tersibuk"; layar ini tidak. Dinyatakan di layar supaya ketiadaannya tidak terbaca sebagai kelalaian lalu "dilengkapi" oleh orang berikutnya.
- **Keempat kolom pengelompokan kategoris SUNGGUHAN**, berbeda dari `action_taken_status` ← karena itu sebaran memang sah untuk keempatnya, dan spec menyatakan pembedaan itu eksplisit supaya pelajaran Process Water ("jangan sebar teks bebas") tidak disalahterapkan ke sini.
- **Nomor gate pass dan segel: yang diterbitkan KEHADIRANNYA, bukan nilainya** ← sebuah nomor segel tidak punya besaran. Penghitung "berapa muatan tanpa gate pass".
- **Rekap tidak pernah melipat nilai berfrekuensi rendah menjadi "lainnya"** ← justru daftar panjang berjumlah satu-satu adalah GEJALA bahwa kolomnya diketik bebas dan perlu master. Melipatnya menyembunyikan gejala itu.
- **Nilai yang berbeda hanya pada besar-kecil huruf atau spasi tepi ditampilkan apa adanya** ← normalisasi diam-diam menyembunyikan data master yang perlu dirapikan.

### Satu kontradiksi yang saya tanam sendiri lalu perbaiki

Saya sempat menulis bahwa laporan merender **dua ember** "dibuang vs dimanfaatkan" dari `purpose_end_use`, sementara pertanyaan terbuka di berkas yang sama menyatakan pemetaannya **tidak boleh ditebak**. Dua hal itu tidak bisa benar bersamaan.

Diperbaiki: yang diterbitkan adalah **rekap PER NILAI apa adanya**, dirender berdampingan dengan angka total mana pun. Maksud aslinya tetap tercapai — tidak ada satu angka "limbah keluar" yang terbaca tanpa rinciannya — tanpa menanam pemetaan yang belum punya dasar. Nilai nyata kolom itu **belum pernah terlihat**: `solid_waste_disposal_details` nol baris di DB dev, diverifikasi langsung.
