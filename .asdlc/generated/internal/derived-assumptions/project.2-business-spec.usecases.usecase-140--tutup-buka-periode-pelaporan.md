# Derived Assumptions Log — project.2-business-spec.usecases.usecase-140--tutup-buka-periode-pelaporan

## v2 — 2026-09-27

- `related_screen_ids` = screen-142 ← repoint usecase-index v16 (keputusan user)
- Seluruh isi diubah dari "menutup PERIODE" menjadi "menutup SATU JENIS STASIUN di dalam periode" ← diverifikasi ke `PeriodClosureService`: `close()`/`reopen()`/`open()`/`unverifiedCount()` semuanya menerima satu `$periodStationId`, dan `unverifiedCount` di-scope ke jenis stasiun yang ditutup. Artefak v1 masih menggambarkan status pada periode
- 11 bdd_scenario v1 dipertahankan seluruhnya (judulnya dijaga tetap agar `scenario_ref` di tech spec tidak terputus), ditambah 3 baru: record jenis stasiun lain tidak ikut terkunci, jalan keluar manual lubang backfill, dan baris stasiun sudah tidak ada ← turunan agen
- 4 skenario yang menggambarkan penegakan kunci (data mobile menyusul, upaya verifikasi, mengubah data stasiun, data di luar rentang) DIPERTAHANKAN tetapi diberi catatan eksplisit "dilacak usecase-141 dan belum diimplementasikan" ← keputusan agen: membuangnya akan menghapus kontrak yang sudah diikat 4 test ber-skip, sementara membiarkannya tanpa catatan akan berbohong soal apa yang berjalan
- alternative_flow "Membuka kembali stasiun agar periodenya dapat disimpan ulang" ← turunan agen dari docblock `update()`; ini satu-satunya jalan keluar lubang backfill yang tersedia hari ini
