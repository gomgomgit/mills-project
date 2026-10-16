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
