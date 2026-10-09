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
          'Mill-Smart-Log-Test-Case-Blackbox-Alur.xlsx'):
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
