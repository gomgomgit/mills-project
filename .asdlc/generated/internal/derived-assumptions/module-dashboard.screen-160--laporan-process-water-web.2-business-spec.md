# Derived Assumptions — module-dashboard.screen-160--laporan-process-water-web.2-business-spec

## v1 — 2026-10-16

Tidak ada wawancara. User berkata "lanjutkan laporan yang belum"; Process Water dipilih karena ia stasiun berikutnya menurut sort_order (130) di antara lima yang tersisa. Seluruh isi spec turunan agent dari pembacaan langsung migrasi + `ProcessWaterRecordService` + pola screen-158 (Engine Room web, laporan bergrid paling mutakhir).

### Fakta yang dibaca langsung, bukan diandaikan

- **`READING_FIELDS` = 11** (9 numerik + `action_taken_status` + `findings`), dan **`shift` serta `inspector_id` DIKECUALIKAN dari `isRowFilled()`** ← dinyatakan EKSPLISIT di docblock service baris 48–57, yang menyebut keduanya "identifying/context columns, not reading columns" dan merujuk pengecualian serupa di Threshing. Ini **berbeda dari Engine Room** yang menghitung seluruh 27 kolomnya termasuk kedua kolom teks.
- **`action_taken_status` bertipe `string` polos, BUKAN enum** ← dibaca dari migrasi. Tidak ada himpunan nilai tertutup, tidak ada entri di `Display::OPTION_LABELS`.
- **`production_line_id` NOT NULL** sejak migrasi `2026_09_28_000043` (tiga migrasi: tambah, backfill, not-null).
- **`date` bertipe DATE**, 24 slot kanonis `(7+i)%24` dari `canonicalTimeSlots()` yang sudah `public static`.
- **`READING_FIELDS` dan `isRowFilled()` masih `protected`** → perlu dibuka ke `public` di Phase 4, sama seperti Engine Room.
- Tanpa kolom downtime, tanpa tabel master target.

> Catatan metode: grep pertama saya memakai pola `[a-z_]+` sehingga **diam-diam menjatuhkan kolom yang mengandung angka** (`raw_water_flow_m3h`, `clarified_water_flow_m3h`). Ketahuan karena jumlah kolom tidak cocok dengan `READING_FIELDS`. Pola diperbaiki ke `[a-z0-9_]+` dan dibaca ulang — kalau tidak, spec ini akan menyebut tujuh kolom ukur, bukan sembilan.

### Turunan agent

- **`action_taken_status` diterbitkan sebagai REKAP TEKS, bukan sebaran kategoris** ← keputusan terpenting di layar ini, dan ia melawan namanya sendiri. Dua kolom bernama serupa di stasiun lain (`diesel_gen_1_status`, `biogas_flare_status`) keduanya enum; yang ini tidak. Sebaran atas teks bebas menghasilkan satu "nilai" per kalimat yang berbeda — daftar kalimat berjumlah satu-satu bukan sebaran. **→ `open_questions` #1**: bila kolom ini memang dimaksudkan kategoris, ia layak dijadikan enum lewat migrasi dan laporan ini lalu menerbitkannya sebagai sebaran.
- **Empat blok berjudul mengikuti RANTAI PENGOLAHAN AIR** (Aliran Air → Softener → Dosis Kimia → Air Umpan Boiler), bukan urutan kolom di basis data ← pilihan agent; pembaca yang menelusuri penyimpangan dapat bergerak mundur ke tahap sebelumnya. Sembilan kartu sebenarnya masih terbaca tanpa pengelompokan, jadi ini pilihan keterbacaan dan bukan keharusan seperti 23 kartu Engine Room.
- **Rekap per `inspector_id`** ← turunan agent; kolom ini tidak dimiliki Engine Room. Dibenarkan karena kelengkapan pencatatan adalah pokok layar ini dan slot kosong selalu punya pemilik yang dapat ditanyai. **Maksudnya dinyatakan di layar** ("untuk menelusuri slot yang belum terisi, bukan untuk menilai orang") supaya tidak terbaca sebagai alat penilaian kinerja. **→ `open_questions` #2**: kolomnya string polos bukan FK ke User, jadi laporan menampilkan kodenya mentah tanpa dapat memulangkan nama.
- **Kolom `shift` SENGAJA TIDAK DITERBITKAN**, dan alasannya dinyatakan di layar ← turunan agent. Kolomnya kosong di seluruh baris dan sudah dijadwalkan diganti relasi ke master Shift (screen-170) pada putaran penandaan record. Menerbitkan kolom kosong yang akan berubah arti lebih buruk daripada tidak menerbitkannya; menyatakan alasannya mencegah ketiadaannya terbaca sebagai kelalaian. **→ `open_questions` #3**: apakah layar ini perlu ditinjau ulang sesudah penandaan shift mendarat.
- **Dua kelas "slot kosong" yang berbeda dan harus dibedakan** ← turunan agent, dan ini konsekuensi paling halus dari pengecualian di atas: slot yang hanya mengisi kolom TEKS BEBAS **terhitung terisi** (keduanya termasuk 11 kolom pembacaan), sementara slot yang hanya mengisi **`shift`/`inspector_id` TIDAK terhitung**. Keduanya ditanam sebagai edge case terpisah justru karena mudah tertukar.
- **Tren harian memakai `raw_water_flow_m3h`** sebagai satu-satunya seri ← pilihan agent; ia metrik paling hulu dan paling mewakili beban stasiun.
- **TIDAK ADA penandaan di luar batas, keterangannya dirender utuh** ← diverifikasi tidak ada master target. Yang khas: pH dan TDS air umpan boiler punya batas yang dikenal SANGAT luas di luar aplikasi, jadi godaan menandai paling besar justru di sini — sama persis dengan situasi 50 Hz di Engine Room.
- **Operator diterima pada ketiga rute API data sejak commit pertama** ← presedens Grading/Kernel Plant/Engine Room, karena screen-161 menyusul langsung. Rute web tetap tertutup.
- `test_priority` `high` · 25 info · 23 aturan · 20 edge case · 3 pertanyaan terbuka · 26 alternative flow · 33 bdd scenario ← seluruhnya turunan agent.

### Koreksi di tempat, 2026-10-16 (ber-`ver` sama — node 3-tech-spec belum ada di hilir)

**Pertanyaan terbuka #2 (`inspector_id`) TERJAWAB pemilik produk:** isinya **teks bebas**, tidak merujuk master mana pun, **dan tidak apa untuk ditampilkan**.

Dua hal yang dikunci:

1. Laporan menampilkan **kode mentah**, tidak pernah mencoba memulangkan nama orang — tidak ada pemetaan untuk ditebak, jadi tidak ada yang perlu diputuskan lagi di Phase 3.
2. **Rekap per inspector tetap tampil di layar** — dan ini **penyimpangan sadar dari preseden Storage Tank**.

**Preseden yang saya temukan setelah user bertanya "dimana ada inspector id":** `StorageTankReportService` sudah memutuskan hal ini untuk `inspector_name` dan keputusannya tegas di docblock-nya — *"DELIBERATELY ABSENT, and the absence is asserted: … `inspector_name` / `findings` are NEVER aggregated into min/avg/max. They appear only in the export."* Jadi rancangan awal saya (rekap di layar) **berlawanan dengan laporan yang sudah terbangun**, dan saya tidak menyadarinya saat menulis v1.

Saya angkat penyimpangan itu ke user alih-alih diam-diam melanjutkannya, dan condong ke mengikuti preseden — bukan karena rekapnya tidak berguna, tetapi karena menyimpang dari preseden **tanpa alasan yang dinyatakan** adalah cara spec mulai tidak konsisten antar laporan. User memilih menampilkannya. Sekarang penyimpangan itu punya alasan yang dinyatakan pemilik produk, dan itulah yang membedakannya dari ketidakkonsistenan.

**Pemetaan kolom sejenis di seluruh sistem, hasil pembacaan langsung** (hanya 3 dari 18 tabel detail punya kolom semacam ini, dan ketiganya menamainya berbeda):

| Tabel | Kolom | Perlakuan laporan |
|---|---|---|
| `process_water_details` | `inspector_id` | rekap di layar (screen-160, keputusan 2026-10-16) |
| `process_quality_control_details` | `qc_inspector_id` | belum ada laporan — keputusan yang sama berlaku saat screen-168 dibangun |
| `storage_tank_details` | `inspector_name` | ekspor saja (screen-133, sudah terbangun) |

> Catatan metode: `grep` awal atas `inspector_id` juga mengenai Engine Room, Boiler Room, Clarification, dan Effluent Plant — seluruhnya **palsu**, berasal dari docblock yang justru menyatakan *"NO identifying/context columns (no shift/inspector_id equivalent)"*. Pola kecocokan-palsu yang sama dengan `grep` kolom berangka sebelumnya di berkas ini.

**Masih terbuka:** pertanyaan #1 (`action_taken_status` kategoris atau teks bebas). Perlakuan saat ini — rekap teks — aman di bawah kedua bacaan, jadi ia tidak memblokir Phase 3.

**Pertanyaan terbuka #1 (`action_taken_status`) TERJAWAB 2026-10-16 — teks bebas, biarkan untuk sekarang.** Tiga bukti terpisah menyepakatinya, dan ketiganya dibaca langsung setelah user bertanya "action_taken_status ada dimana?":

1. **Form web merendernya `<input type="text">`**, bersebelahan dan identik perlakuannya dengan `findings`. Bukan `<select>`, nol `<option>`.
2. **Label form mobile berbunyi `"Action Taken/Status"` — dengan garis miring.** Itu label majemuk "Tindakan yang Diambil / Status", sehingga kata "status" adalah paruh kedua sebuah frasa, bukan penanda himpunan nilai. Inilah bukti yang paling menentukan, dan ia tidak akan ketemu tanpa membuka formnya.
3. **Kolomnya hanya ada di satu tabel** di seluruh sistem, dan kerabat terdekatnya — `action_taken_maintenance_remark` di `engine_room_details` — dinamai **"remark"**. Nol entri `Display::OPTION_LABELS` untuk keduanya.

Jadi perlakuan rekap-teks di v1 benar, dan sekarang ia bersandar pada bukti alih-alih pada "aman di bawah kedua bacaan". Yang tetap berlaku: isi berulang sama persis TIDAK dikelompokkan menjadi sebaran.

User menambahkan "untuk sekarang", jadi ini bukan penutupan permanen: bila kolomnya kelak dijadikan enum, laporan ini dan setiap laporan lain yang dibangun di atas perlakuan teks perlu ditinjau ulang — dan ongkosnya naik seiring bertambahnya layar.

**Seluruh pertanyaan yang memblokir Phase 3 kini terjawab.** Yang tersisa (#3, apakah layar ini perlu ditinjau ulang sesudah penandaan shift mendarat) tidak memblokir apa pun.
