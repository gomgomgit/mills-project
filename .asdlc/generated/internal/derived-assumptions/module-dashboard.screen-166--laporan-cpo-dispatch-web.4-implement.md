## v1 — 2026-10-21

- `turnaround.avg_minutes` dibulatkan 2 desimal (konstanta `TURNAROUND_DECIMALS`) ← spec tidak menyebut pembulatan.
- Jam tersibuk pada seri = jam PALING AWAL ← spec menetapkan `busiest_hour` NULL bila kosong, tetapi diam soal seri.
- `hourly.buckets[]` hanya membawa `hour`, `dispatch_count`, `net_weight_total` (null bila tak ada pengiriman bertimbang pada jam itu) ← spec tidak merinci isi tiap kelompok.
- Label ukuran mutu "Kadar Asam Lemak Bebas (FFA)", "Kadar Air (Moisture)", "Kadar Kotoran (Impurities)", "DOBI" ← spec menyebut ukurannya, bukan teks labelnya; urutan mengikuti layar Detail stasiun.
- Kalimat keadaan-kosong tersendiri ditampilkan DI SAMPING baris tabel kosong ← spec meminta keadaan kosong yang menyebut periode dan line, tidak menentukan apakah tabelnya ikut disembunyikan.
- Waktu putar memakai `ShiftService::minuteOfDayOf()`, BUKAN `ShiftService::durationMinutes()` ← `durationMinutes()` membaca jam yang sama sebagai 1440 menit, sedangkan spec menetapkan NOL menit.
- Jam yang tidak dapat di-parse dihitung sebagai TIDAK TERCATAT ← spec hanya membedakan terisi/kosong, tidak membahas nilai rusak.
- Relasi detail bernama `cpoDispatchDetails`, bukan `details` ← spec menyebut `details`; nama sebenarnya diverifikasi di model.
- Lima test lama (screen-162/164) di-re-point, bukan hanya layar ini ditulis ← spec layar ini tidak membahas riak `REPORT_ROUTES`; memerah di suite penuh dan diperbaiki tanpa melemahkan asersi.
