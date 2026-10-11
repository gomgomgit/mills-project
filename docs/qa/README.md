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

## Menjalankan satu putaran dan mengisi hasilnya

`e2e-web/qa-run/jalankan.spec.ts` menjalankan langkah kasusnya **apa adanya** dan mencatat
apa yang terlihat ke `qa-run/hasil.json`. Ia bukan suite regresi: ia berada di luar
`testDir` suite itu dan punya config sendiri, supaya tidak pernah ikut berjalan di sana.

```
cd e2e-web
npm run db:prepare                 # basis data uji, HANYA *_e2e
npx playwright test -c qa-run/playwright.config.ts

cd ../docs/qa
python3 isi-hasil.py ../../e2e-web/qa-run/hasil.json \
        Mill-Smart-Log-Test-Case-Blackbox-Alur.xlsx \
        Mill-Smart-Log-Hasil-Uji-Alur.xlsx 2026-10-11
```

Dua aturan yang dipegang `isi-hasil.py`, keduanya ada sebabnya:

1. **Baris yang tidak dijalankan ditandai `Tidak diuji`, tidak pernah `Lulus`.** Mengisinya
   Lulus berarti mengarang bukti uji di berkas yang diserahkan ke klien.
2. **Pencocokan ketat pada (id, varian).** Tidak ada pelapisan "berlaku untuk semua varian":
   satu kasus dijalankan pada satu stasiun atau satu tingkat, dan hasil di Depricarping bukan
   bukti untuk Sterilizer. Pelapisan seperti itu pernah hampir menandai baris Business Unit
   memakai bukti dari Company.

Yang **tidak boleh** masuk ke pelaksana: kasus yang menuntut penilaian mata (warna, keadaan
redup), hover, lebar 390px, mematikan koneksi, mengubah jam perangkat, atau membuka berkas
unduhan. Itu pekerjaan penguji manusia, dan memaksakannya ke mesin menghasilkan "Lulus" yang
tidak memeriksa apa pun.

### Yang ditemukan putaran pertama

Ketiga kegagalan putaran pertama semuanya cacat **dokumen atau pelaksana**, bukan aplikasi —
dan itu justru gunanya menjalankan kasusnya:

| Temuan | Cacatnya di mana |
|---|---|
| `DS-06` menyatakan Admin boleh membuka Laporan Manajemen | Teks kasus. Rutenya `role:mill_management` saja — sengaja lebih sempit dari Dashboard. Matriks peran juga ikut salah. |
| `DS-04` menemukan 0 tile | Teks kasus. Grid stasiun baru dirender setelah Production Line dipilih; langkah itu tidak ada di kasusnya. `DS-05` kena hal yang sama. |
| `AU-02`/`AU-03` membaca dua pesan berbeda | Pelaksana. Halaman dibaca saat tombol masih "Memproses…", jadi yang berbeda adalah waktunya, bukan pesannya. Sekarang menunggu elemen pesannya muncul. |

## Memeriksa kebersihan sebelum menyerahkan

Jalankan ini setiap kali Excel dibangun ulang. Keluarannya harus `BERSIH` untuk ketiganya.

```python
import openpyxl, re, collections
PAT = (r'(?i)(hard-?cod\w*|localhost|\bnpm\b|artisan|seeder|unit test|vitest|pest\b'
       r'|playwright|READING_FIELDS|ListFilterBar|RecordVerificationStatus|screen-\d+'
       r'|20\d\d-\d\d-\d\d|pernah ada bug|atas permintaan user|\bdefek\b|\bcommit\b'
       r'|[a-z_]+\.(?:php|ts|vue|sh)\b)')
for f in ('Mill-Smart-Log-Test-Case-Blackbox.xlsx',
          'Mill-Smart-Log-Test-Case-Blackbox-Ringkas.xlsx',
          'Mill-Smart-Log-Test-Case-Blackbox-Alur.xlsx',
          'Mill-Smart-Log-Hasil-Uji-Alur.xlsx'):
    wb = openpyxl.load_workbook(f)
    hits = collections.Counter()
    for sh in wb.sheetnames:
        for row in wb[sh].iter_rows():
            for c in row:
                if isinstance(c.value, str):
                    for m in re.finditer(PAT, c.value):
                        hits[m.group(0)] += 1
    print(f, dict(hits) if hits else 'BERSIH')
```

Pola itu bukan hiasan: setiap entri di dalamnya pernah benar-benar bocor ke keluaran.
Tambahkan juga `RouteAccess|::|data-testid|is-done|is-pending|middleware|DOM` saat memindai
berkas hasil — catatan pengamatan pernah membocorkan keenam istilah itu.

Satu pengecualian untuk berkas hasil: pola `20\d\d-\d\d-\d\d` akan kena pada kolom
**Tanggal**, dan di sana tanggal memang seharusnya ada.
