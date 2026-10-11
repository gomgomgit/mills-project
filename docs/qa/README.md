# Dokumen QA — Mill Smart Log

## Yang DISERAHKAN ke klien

Hanya tiga berkas Excel. Ketiganya sudah bebas rujukan internal — tanpa nama berkas,
nama kolom basis data, nama komponen, id spesifikasi, tanggal keputusan internal, atau
rujukan ke uji otomatis. Ada pemindai yang memeriksanya; lihat "Memeriksa kebersihan".

| Berkas | Jalan | Dipakai untuk |
|---|---:|---|
| `Mill-Smart-Log-Test-Case-Blackbox-Alur.xlsx` | 265 | **Satu putaran manual.** Tingkat 1+2. |
| `Mill-Smart-Log-Test-Case-Blackbox-Ringkas.xlsx` | 1.449 | Regresi bila waktu terbatas. |
| `Mill-Smart-Log-Test-Case-Blackbox.xlsx` | 1.509 | Putaran rilis. Cakupan penuh. |

Ditambah satu berkas hasil, bila sebuah putaran uji sudah dijalankan:

| Berkas | Isi |
|---|---|
| `Mill-Smart-Log-Hasil-Uji-Alur.xlsx` | Berkas Alur dengan kolom hasil TERISI. 18 dari 265 jalan diuji pada 2026-10-11, semuanya Lulus; 247 sisanya bertanda `Tidak diuji` beserta alasan spesifik per baris. |

Ketiganya bersarang: **Alur ⊂ Ringkas ⊂ Penuh**. Baris yang sama punya id, langkah, dan
hasil diharapkan yang identik di ketiganya, jadi satu id laporan bug berarti satu hal di
mana pun ia dibuka.

Enam sheet per berkas: Panduan, Kasus Uji, Rekap, Lingkungan & Akun, Matriks Stasiun,
Catatan & Batasan.

## Yang TIDAK diserahkan

| Berkas | Mengapa internal |
|---|---|
| `blackbox-test-cases.md` | Sumber kebenaran. Memuat riwayat perbaikan, id spesifikasi (`screen-145`), dan catatan internal yang sengaja berbeda dari Excel. |
| `parse-cases.py`, `build-xlsx.py` | Perkakas pembangun. Memuat nama komponen dan keputusan desain. |
| `README.md` ini | Internal. |
| `isi-hasil.py` | Perkakas pengisi kolom hasil. |
| `periksa-cakupan.py` | Pemeriksa: hasil yang (id, varian)-nya tidak ada di berkas Alur, dan baris Alur yang belum punya hasil. |
| `periksa-kerapian.py` | Pemeriksa jejak internal pada `.xlsx` sebelum diserahkan (nama kelas PHP, `data-testid`, jalur berkas sumber). |
| `../../e2e-web/qa-run/` | Pelaksana uji + `hasil.json`. Memuat nama elemen dan rute. |

## Membangun ulang

Markdown adalah sumber kebenaran. **Jangan menyunting `.xlsx` langsung** — suntingannya
hilang pada pembangunan berikutnya.

```
python3 parse-cases.py cases.json
python3 build-xlsx.py cases.json Mill-Smart-Log-Test-Case-Blackbox.xlsx
python3 build-xlsx.py cases.json Mill-Smart-Log-Test-Case-Blackbox-Ringkas.xlsx ringkas
python3 build-xlsx.py cases.json Mill-Smart-Log-Test-Case-Blackbox-Alur.xlsx    alur
```

Butuh `openpyxl`. Tiga hal ditulis di `build-xlsx.py`, bukan diurai dari markdown, karena
bentuknya tabel dan bukan kasus uji: matriks 18 stasiun, daftar tingkat Kelola, dan tabel
Catatan & Batasan. Kalau markdown berubah di bagian itu, ubah juga di sana.

**Hal keempat yang ditulis di `build-xlsx.py`: cakupan per varian, di `applies()`.** Bagian
`**Berlaku untuk:**` pada sebuah kasus di markdown adalah penjelasan untuk pembaca; yang
menentukan baris mana dicetak sebagai "Tidak berlaku" adalah `applies()`. Keduanya harus
berubah bersama. Kalau hanya markdown yang diubah, jalan Tingkat 1 sebuah kasus bisa mendarat
pada varian yang kasusnya tidak berlaku di sana — dan karena setiap kasus `MD-KEL-*` hanya
punya SATU jalan di berkas Alur, kasus itu lalu tidak pernah benar-benar teruji. Itu persis
yang terjadi pada `MD-KEL-05` (mendarat di Business Unit, padahal aturannya hanya ada di
Company) dan `MD-KEL-10`/`MD-KEL-11` (butuh kolom pencarian, yang hanya ada di Kelola Mesin).

`parse-cases.py` sekarang **berteriak ke stderr** untuk label `- **Xxx:**` yang tidak
dikenalnya. Sebelum itu label tak dikenal dibuang tanpa jejak, dan seluruh isi `Berlaku untuk`
pada sembilan kasus hilang dari ketiga Excel tanpa ada yang tahu.

## Menjalankan satu putaran dan mengisi hasilnya

Ada **dua pelaksana**, satu per aplikasi, keduanya menjalankan langkah kasusnya **apa adanya**
dan mencatat apa yang terlihat:

| Pelaksana | Berkas | Keluaran |
|---|---|---|
| Web | `e2e-web/qa-run/*.spec.ts` | `e2e-web/qa-run/hasil.jsonl` |
| Mobile | `mobile/tests/qa-run/*.spec.ts` | `mobile/tests/qa-run/hasil-mobile.jsonl` |

Keduanya bukan suite regresi: masing-masing berada di luar `testDir` suite regresinya dan
punya config sendiri, supaya tidak pernah ikut berjalan di sana.

**Urutannya mengikat: web lebih dulu.** Dua kasus diserahterimakan lewat berkas — `DS-12`
membandingkan urutan tile memakai `qa-run/urutan-web.json`, dan `AD-10` memeriksa di mobile
nilai yang diubah pelaksana web lewat `qa-run/ad10-web.json`.

```
# 1. Basis data uji + keadaan yang dituntut prasyarat kasusnya.
cd e2e-web && npm run db:prepare          # HANYA *_e2e; menolak basis data lain
cd ../backend
php artisan tinker --env=e2e --execute="require base_path('../e2e-web/qa-run/siapkan-lingkungan.php');"
php artisan tinker --env=e2e --execute="require base_path('../e2e-web/qa-run/siapkan-data.php');"
php artisan tinker --env=e2e --execute="require base_path('../e2e-web/qa-run/siapkan-laporan.php');"
nohup php artisan serve --env=e2e --port=8001 &

# 2. Pelaksana web.
cd ../e2e-web && rm -f qa-run/hasil.jsonl
npx playwright test -c qa-run/playwright.config.ts

# 3. Pelaksana mobile. VITE_API_BASE_URL WAJIB diarahkan ke backend e2e —
#    .env repo menunjuk :8000, yaitu basis data DEV, dan pelaksana ini MENULIS data.
cd ../mobile && rm -f tests/qa-run/hasil-mobile.jsonl
VITE_API_BASE_URL=http://localhost:8001 nohup npm run dev &
npx playwright test -c tests/qa-run/playwright.config.ts

# 4. Isi kolom hasilnya (tanggal putaran sebagai argumen ketiga).
cd ../docs/qa
python3 isi-hasil.py Mill-Smart-Log-Test-Case-Blackbox-Alur.xlsx \
        Mill-Smart-Log-Hasil-Uji-Alur.xlsx 2026-10-12 \
        ../../e2e-web/qa-run/hasil.jsonl \
        ../../mobile/tests/qa-run/hasil-mobile.jsonl
```

### Tiga keadaan yang harus disiapkan, dan mengapa

Tanpa ketiga skrip penyiapan itu, **puluhan kasus tidak dapat dijalankan sama sekali** — bukan
karena sistemnya, karena keadaannya belum ada:

| Skrip | Yang dibentuk | Kasus yang bergantung padanya |
|---|---|---|
| `siapkan-lingkungan.php` | Akun `SPV-B` dan `NOMILL` (§1.2 dokumen; tidak dibuat seeder mana pun), record Mill B pada tanggal yang sama dengan Mill A, periode untuk Mill B, satu Production Line **tanpa stasiun** di Mill A, dan satu mill **tanpa Production Line** beserta akun Operator di dalamnya | `LS-03`, `LS-04`, `LS-05`, `LS-08`, `LS-09`, `WB-FRM-07`, `MB-FORM-15`, `MB-HOME-04` |
| `siapkan-data.php` | Record untuk 11 jenis stasiun Mill A yang belum punya satu pun | Seluruh `WB-DTL-*`, `WB-FRM-*`, dan `LP-W-01` di stasiun itu |
| `siapkan-laporan.php` | Periode masa depan, periode tanpa record, periode berisi record tanpa kolom ukur terisi, periode berdata tanpa downtime, dan satu periode "racikan" (slot per hari tidak sama, downtime null vs 0 vs 45, dua ejaan temuan) | `LP-W-03` sampai `LP-W-14` |

Satu prasyarat lagi dibentuk **lewat layar aplikasi**, bukan skrip: pelaksana WB membuka
seluruh stasiun pada periode yang mencakup hari ini sebagai Admin. Periode berstatus Draft
MENOLAK penyimpanan data web, jadi tanpa langkah itu seluruh `WB-FRM-*` mustahil lulus.

Tiga aturan yang dipegang `isi-hasil.py`, semuanya ada sebabnya:

1. **Baris yang tidak dijalankan ditandai `Tidak diuji`, tidak pernah `Lulus`.** Mengisinya
   Lulus berarti mengarang bukti uji di berkas yang diserahkan ke klien.
2. **Pencocokan ketat pada (id, varian).** Tidak ada pelapisan "berlaku untuk semua varian":
   satu kasus dijalankan pada satu stasiun atau satu tingkat, dan hasil di Depricarping bukan
   bukti untuk Sterilizer. Pelapisan seperti itu pernah hampir menandai baris Business Unit
   memakai bukti dari Company.
3. **`Tidak berlaku` bukan `Tidak diuji`.** Baris yang kolom Langkah-nya sudah berbunyi
   "Tidak berlaku" (dari `applies()`) diberi nilai itu dan **dikeluarkan dari penyebut**.
   Menyatukannya dengan `Tidak diuji` membuat laporan terbaca seperti pekerjaan yang
   tertinggal, padahal tidak ada apa pun untuk diuji di varian itu.

Yang **tidak boleh** masuk ke pelaksana: kasus yang menuntut penilaian mata (warna, keadaan
redup), hover, lebar 390px, mematikan koneksi, mengubah jam perangkat, atau membuka berkas
unduhan. Itu pekerjaan penguji manusia, dan memaksakannya ke mesin menghasilkan "Lulus" yang
tidak memeriksa apa pun.

### Jebakan pelaksana yang berulang

Sebelas hal ini berkali-kali membuat kasus terlihat gagal padahal aplikasinya benar. Kalau
sebuah kasus gagal dengan bukti yang aneh, periksa kesebelasnya lebih dulu:

1. **Locator yang tidak ada tetap DITUNGGU.** `isEnabled()`, `inputValue()`, dan `innerText()`
   pada elemen yang tidak ada menunggu sampai batas waktu test; `.catch()` menangkapnya terlambat
   dan kegagalannya terbaca "halaman tertutup". Pakai pembungkus di `support/qa.ts` yang
   memeriksa keberadaannya lebih dulu.
2. **Penanda `data-testid` TIDAK seragam antar layar.** `period-select` vs `period-selector`,
   `export-csv` vs `export-button`, `add-detail-row-button` vs `add-row-button` vs
   `add-tipped-time-row-button`. Memaku satu nama melaporkan "tombol tidak ada" untuk tombol yang
   ada.
3. **Opsi penampung punya VALUE yang tidak kosong.** "Pilih Production Line" bukan `value=""` di
   layar mobile, jadi pemeriksaan berbasis value selalu menyimpulkan "sudah terpilih".
   Bandingkan TEKS opsi terpilihnya.
4. **Pemilih di layar ini sering combobox pencarian, bukan `<select>`.** Slot waktu mobile, induk
   pada layar Kelola, dan pemilih mill pada Mills Setting semuanya combobox: diklik lalu opsinya
   dipilih, dan opsinya harus disaring menurut id comboboxnya sendiri agar tidak mengenai listbox
   lain yang sedang tersembunyi.
5. **Viewport project menimpa viewport tingkat-atas.** `devices['Desktop Chrome']` membawa 1280px;
   lebar 390px harus ditaruh di dalam `use` project, SESUDAH sebarannya — kalau tidak, seluruh
   kasus tata letak mobile diuji pada lebar desktop tanpa ada yang terlihat salah.
6. **Satu kasus dapat MENGHABISKAN prasyarat kasus lain — dan itu hanya terlihat saat menjalankan sebagian.** `DS-08` dan `DS-10` menuntut keadaan "beberapa stasiun punya record hari ini, beberapa belum". Seluruh kasus Form (`WB-FRM-*`, `MB-FORM-*`) membuat record **bertanggal hari ini**, jadi sesudah keduanya berjalan ke-18 stasiun sudah punya input dan keadaan itu tidak ada lagi. Pada putaran penuh yang berurutan tidak ada masalah — `03-ds.spec.ts` berjalan lebih dulu. Tetapi menjalankan ulang `03-ds` SENDIRIAN sesudah putaran penuh akan membuat DS-08/DS-10 gagal karena prasyaratnya habis, bukan karena aplikasinya. **Hasil yang diserahkan harus berasal dari satu putaran penuh yang berurutan dari basis data yang baru dibangun.**
7. **Tombol dialog harus dicari DI DALAM dialognya.** `ConfirmDialog` mobile di-Teleport ke
   `body` dengan overlay ber-z-index 1200. Pola nama saja (`/^(Ya|Hapus)/`) mengenai tombol
   lain pada layar di belakangnya, dan klik ke tombol belakang itu DITAHAN overlay sampai batas
   waktu test — `MB-FORM-09` habis 3 menit karenanya, dan kegagalannya terbaca seperti tombol
   yang tidak merespons.
8. **Nilai kolom yang diisi pengisi otomatis diturunkan dari LABELNYA, dan urutan aturannya
   menentukan.** "Cages Track Number" pernah terisi `6` karena aturan jumlah (`/cages/`) menang
   atas aturan identitas — nomor recordnya menjadi angka dan record itu tidak dapat dicari lagi
   di Data Preview, sehingga `MB-FORM-01 [Cages Track]` melaporkan "record tidak muncul" untuk
   record yang justru tersimpan.
9. **Tanggal dibandingkan per BAGIAN, bukan sebagai teks.** Layar menulis "12 Oktober 2026",
   tanggal perangkat dibentuk "12/10/2026": perbandingan teks selalu berbunyi "tidak cocok"
   untuk tanggal yang sama, dan catatan hasilnya menuduh aplikasi yang benar.
10. **Periode pertama belum tentu periode BERDATA.** `siapkan-laporan.php` sengaja membuat
   "QA Masa Depan" dan "QA Tanpa Record". Pada periode tanpa data, laporan menggambar empty
   state dan SELURUH isinya tidak dirender — termasuk kotak keterangan yang diperiksa `LS-16`.
   Pakai `pilihPeriodeBerdata()`, yang menelusuri sampai menemukan periode tanpa `empty-state`.
11. **Satu kasus dapat MERUSAK prasyarat kasus lain.** `06-md-prd.spec.ts` menutup satu stasiun
   pada periode Oktober 2026 untuk menyiapkan keadaan "sebagian terbuka" yang dituntut
   `MD-PRD-02`/`MD-PRD-04` — dan stasiun pertama pada daftar itu Weighbridge. Dua berkas
   kemudian, `WB-FRM-01 [Weighbridge]` gagal dengan "periode sudah ditutup": aplikasinya benar,
   prasyaratnya yang hilang. Langkah prasyarat di `08-wb.spec.ts` kini melalui **dua** jalan
   menuju Terbuka — "Buka" untuk Draft dan "Buka Kembali" untuk yang tertutup. Kalau sebuah
   kasus gagal pada stasiun atau mill yang kasus lain sempat mengubahnya, curigai ini lebih
   dulu daripada aplikasinya.

### Yang ditemukan putaran pertama

Ketiga kegagalan putaran pertama semuanya cacat **dokumen atau pelaksana**, bukan aplikasi —
dan itu justru gunanya menjalankan kasusnya:

| Temuan | Cacatnya di mana |
|---|---|
| `DS-06` menyatakan Admin boleh membuka Laporan Manajemen | Teks kasus. Rutenya `role:mill_management` saja — sengaja lebih sempit dari Dashboard. Matriks peran juga ikut salah. |
| `DS-04` menemukan 0 tile | Teks kasus. Grid stasiun baru dirender setelah Production Line dipilih; langkah itu tidak ada di kasusnya. `DS-05` kena hal yang sama. |
| `AU-02`/`AU-03` membaca dua pesan berbeda | Pelaksana. Halaman dibaca saat tombol masih "Memproses…", jadi yang berbeda adalah waktunya, bukan pesannya. Sekarang menunggu elemen pesannya muncul. |

## Memeriksa kebersihan sebelum menyerahkan

Jalankan ini setiap kali Excel dibangun ulang. Keluarannya harus `BERSIH` untuk keempatnya,
dan statusnya 1 bila ada temuan — jadi bisa dipakai sebagai penjaga.

```
python3 -P periksa-kerapian.py \
    Mill-Smart-Log-Test-Case-Blackbox.xlsx \
    Mill-Smart-Log-Test-Case-Blackbox-Ringkas.xlsx \
    Mill-Smart-Log-Test-Case-Blackbox-Alur.xlsx \
    Mill-Smart-Log-Hasil-Uji-Alur.xlsx
```

Dulu bagian ini berupa potongan Python yang ditempel ke `python3 -`. Dijadikan skrip supaya
polanya satu tempat, temuannya menyebut sel persisnya, dan satu-satunya pengecualian yang sah
— tanggal putaran di kolom **Tanggal** berkas hasil — tertulis di kode, bukan diingat sendiri
setiap kali. Pemeriksaan ini pernah menangkap `2026-08-20` di catatan `AD-10`: tanggal
keputusan internal yang tidak ada gunanya bagi klien.

Setiap pola di dalam skrip itu pernah benar-benar bocor ke keluaran — termasuk `RouteAccess`,
`Kelas::metode`, `data-testid`, nama kelas CSS keadaan, "middleware", dan "DOM", yang keenamnya
masuk lewat kolom catatan pengamatan pelaksana.
