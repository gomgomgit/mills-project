# Derived Assumptions — module-dashboard.screen-164--laporan-kernel-dispatch-web.4-implement

## v1 — 2026-10-18

- `status` = `complete` ← diturunkan dari hasil uji: unit 67, integrasi 32, komponen 39, browser 22, nol gagal; suite backend 6052 → **6190 lolos / 0 gagal**. Verifikasi urutan e2e (`--workers=1`, form + laporan bersama) 22/22. Coverage tidak terukur dan TIDAK dipakai menurunkan status.
- Ekspor 27 kolom dengan konteks keenam `Rentang Periode` ← keputusan agen di luar preseden (laporan lain 5 konteks); alasannya nama periode teks bebas tanpa jaminan memuat tanggalnya. Diterima.
- Relasi eager load bernama `kernelDispatchDetails`, bukan `details` seperti bunyi spec ← fakta kode; spec yang kurang tepat.
- Tidak ada grafik tren harian ← ketiadaan yang disengaja: baik business spec maupun tech spec tidak memintanya, berbeda dari screen-162 yang punya.

## Tiga keputusan saya atas laporan agen

- **Ekspor diubah ke `ExportValue::option()`**. Agen memakai `Display::option()` agar sel tak-tercatat tercetak `-` "sama seperti layar Detail". Alasan itu salah tempat: di layar `-` penanda untuk manusia, di berkas ekspor ia nilai teks yang diurutkan dan dijumlahkan pembacanya — sel `-` menjadi kategori palsu di pivot. Aturan rumah (keputusan 2026-10-14) dan kedua preseden (Effluent Plant, Engine Room) menang. Dua test case dikoreksi, plus satu asersi negatif BARU bahwa `Display::option($value)` tidak boleh muncul di jalur ekspor.
- **Flake laten `ShiftStampingTest` diperbaiki**, dan sebabnya pekerjaan shift saya sendiri 2026-10-16: test memposting record tanpa `shift_id` sehingga shift diturunkan dari jam dinding, dan fixture hanya punya shift Pagi 07:00–15:00. Hijau hanya di luar jam itu; setiap baseline "hijau" sebelumnya terambil di luar jendela Pagi. Dipaku dengan `travelTo(20:00)` — pola yang sudah dipakai test "celah jadwal" di berkas yang sama, yang satu ini terlewat — plus asersi DB supaya layar yang kebetulan mencetak "Tanpa shift" karena sebab lain tidak meloloskannya.
- **Perubahan agen di luar daftar berkas DITERIMA**: `SolidWasteDisposalReportServiceTest` case 47 & 48 di-re-point, bukan dilemahkan — case 47 justru lebih kuat (tetangga di kedua sisi + ketiga sort_order). Koplingnya dibiarkan disengaja agar memerah lagi di screen-166 dan 168.

## Koreksi atas spec yang saya sendiri tulis

- Premis "nilai enum tak dikenal mungkin ada" LEBIH SEMPIT dari yang saya tulis ← kolomnya `enum([...])->nullable()` dengan CHECK constraint di PostgreSQL DAN SQLite; insert nilai lain melempar `QueryException`. Saya menyimpulkan "mungkin ada" dari fakta bahwa SERVICE tidak memvalidasinya, tanpa memeriksa bahwa KOLOMNYA yang memvalidasi — dua lapis penjaga, hanya satu yang saya lihat. Cabang defensifnya tetap benar, tetapi ia menjaga terhadap perubahan skema di masa depan, bukan terhadap data hari ini.
- api_test skenario 4 salah menebak mekanismenya ← yang menolak adalah penjaga LINE bukan penjaga PERIODE; `authorizePeriodModel()` meloloskan Admin untuk mill mana pun, jadi periode basi + line valid = 200. "Admin berganti mill" bukan satu operasi melainkan dua parameter yang masing-masing punya penjaganya sendiri.
- Perkiraan saya bahwa riaknya ada di tiga berkas kandidat SALAH untuk kali kedua ← yang memerah adalah berkas spec screen-162 sendiri, dan grep atas kandidat tidak menemukannya. Yang menemukannya: menjalankan suite penuh. Pelajaran screen-162 terulang verbatim.
