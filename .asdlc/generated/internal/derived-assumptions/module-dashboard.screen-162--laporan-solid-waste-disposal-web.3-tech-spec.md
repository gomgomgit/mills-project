# Derived Assumptions — module-dashboard.screen-162--laporan-solid-waste-disposal-web.3-tech-spec

## v1 — 2026-10-18

- `route` = `/reports/solid-waste-disposal` ← pola keenam laporan stasiun yang sudah ada; pengguna tidak menyebut jalur.
- `api_contracts[0].endpoints` = EMPAT endpoint baru `/api/solid-waste-disposal-reports/{business-units/options,periods,summary,export}` ← pengguna tidak menyebut jumlah maupun pembagian endpoint; diturunkan dari pola yang identik di keenam laporan sebelumnya. Berbeda dari screen-161 yang NOL endpoint baru karena ia memakai ulang milik screen-160; di sini belum ada backend-nya sama sekali.
- Operator diterima di ketiga rute data SEJAK COMMIT PERTAMA ← bukan permintaan pengguna; diturunkan supaya screen-163 (mobile) nanti tidak perlu melebarkan izin apa pun. Pola terbukti pada screen-158/159 dan 160/161.
- `production_line_id` WAJIB, tanpa mode "semua line" ← diturunkan dari isolasi Production Line; pengguna tidak menyatakannya untuk layar ini.
- Satuan hitung = BARIS DETAIL (satu muatan), bukan record induk ← diturunkan dari bentuk tabel `solid_waste_disposal_details` (berkunci `event_date`, tanpa READING_FIELDS); pengguna tidak membahas granularitas.
- `net_weight_*` dihitung hanya atas baris non-null, dengan `net_weight_load_count` DITERBITKAN sebagai penyebut ← aturan yang saya bawa dari laporan sebelumnya, bukan permintaan pengguna.
- Nol pengukuran → `null`, bukan `0` ← keputusan saya; pengguna tidak menyebut perbedaan "tidak diketahui" vs "tidak ada beban".
- `missing_net_weight_load_count` ditonjolkan tersendiri, dan dibacakan sebagai "penimbangan belum selesai" bukan "data tidak lengkap" ← diturunkan dari fakta bahwa `net_weight_mt` adalah kolom TURUNAN SERVICE (`gross − tare`); pengguna tidak membahasnya.
- EMPAT rekap kategoris (`waste_type`, `source_station`, `disposal_site`, `purpose_end_use`) ← saya pilih keempat kolom ini dari skema tabel; pengguna tidak menyebut rekap mana pun.
- NOL join / nol pemetaan master pada keempat rekap, nilai apa adanya ← diturunkan dari fakta keempatnya teks bebas; pengguna tidak melarang maupun meminta penerjemahan.
- Baris `value = null` ("tidak tercatat") sebagai baris TERSENDIRI dan SELALU terakhir; urut nilai alfabetis bukan urut jumlah ← keputusan saya, dengan alasan penjumlahan rekap harus tetap sama dengan totalnya dan posisi nilai tidak berpindah antar periode.
- Beda huruf besar/kecil TETAP dua entri terpisah (hanya trim di ujung) ← keputusan saya; menyatukannya berarti memutuskan dua ejaan adalah hal yang sama, dan teks bebas tidak menjaminnya.
- `compliance.missing_gate_pass_count` / `missing_seal_count` sebagai JEJAK KEPATUHAN terpisah dari blok `loads` ← saya pilih dua kolom ini sebagai penanda dokumen angkut; pengguna tidak menyebut kepatuhan.
- TANPA blok `hourly`/`busiest_hour`/`empty_hour_count`, dan sebabnya DICETAK di layar ← diturunkan dari ketiadaan kolom waktu pada tabel detail (hanya `event_date`). Pengguna tidak meminta sebaran jam maupun melarangnya. Prosanya ada untuk mencegah seseorang menambahkannya kemudian dengan menebak jam dari `created_at`.
- `daily[]` = satu entri per hari BERMUATAN, bukan per hari kalender ← keputusan saya; kebalikan dari laporan bergrid yang justru wajib memunculkan slot kosong.
- `daily_total` dijumlahkan dari `daily[]`, bukan disalin dari `loads` ← keputusan saya, supaya selisih akibat baris undated tidak tersembunyi.
- `undated_load_count` diterbitkan dan dicetak berdampingan dengan total ← keputusan saya; pengguna tidak membahas baris tanpa `event_date`.
- Baris dengan `event_date` DI LUAR rentang periode tetap terhitung (keanggotaan dari `date` INDUK) ← keputusan saya; pengguna tidak membahas perbedaan kedua kolom tanggal itu.
- `completeness` memakai `ReportPeriodDays::counted` ← konsisten dengan keenam laporan sebelumnya; pengguna tidak menyebut rumus kelengkapan.
- Record draft IKUT terhitung dan `draft_load_count` menyatakan berapa ← pola laporan sebelumnya, bukan permintaan pengguna.
- Status periode TIDAK disaring (draft/open/closed sama-sama terbaca) ← perilaku yang berlaku sekarang di laporan lain; dipertahankan tanpa ditanyakan.
- Kolom `shift` tidak diterbitkan di payload maupun layar, hanya di ekspor ← keputusan saya, karena ia teks bebas pada tabel detail dan BUKAN FK ke `shifts`. Terpisah dari `shift_id` baru di tabel induk.
- TANPA blok downtime dan TANPA penandaan nilai di luar batas, keduanya DINYATAKAN di layar ← diturunkan dari ketiadaan master target dan ketiadaan kolom downtime pada stasiun ini.
- `text_entries` memisahkan `findings` dan `remarks` menjadi dua daftar kejadian, tidak disebar jadi rekap ← keputusan saya, karena keduanya teks bebas.
- Batas ekspor 50.000 baris, format `csv|xlsx`, gate EAGER sebelum aliran ← konvensi ekspor proyek; pengguna tidak menyebut batas.
- Entri `REPORT_ROUTES` di-APPEND di akhir (setelah storage-tank/140) ← diturunkan dari `station_types.sort_order` = 150, dibaca dari tabel master bukan dari urutan deklarasi enum.
- Tiap baris rekap menerbitkan penyebutnya sendiri (`net_weight_load_count` + `net_weight_avg` per baris) ← DIPERBAIKI setelah membandingkan spec dengan mock HTML-nya; versi pertama skema saya hanya menerbitkan penyebut di tingkat global, dan mock-nya yang benar.
- Contoh "stasiun belum berlaporan" di `StationReportServiceTest` akan diganti KODE FIKTIF, bukan dipindahkan ke kernel-dispatch ← keputusan saya; memindahkannya hanya menunda kegagalan yang sama sampai screen-164.
- `UNAVAILABLE_STATION` di spec browser dipindahkan ke `process-quality-control` (180) ← keputusan saya; bukan ke kernel-dispatch (160), karena keempat stasiun tanpa laporan sedang dibangun berurutan sehingga contoh mana pun selain yang terakhir akan basi lagi dalam hitungan hari.
