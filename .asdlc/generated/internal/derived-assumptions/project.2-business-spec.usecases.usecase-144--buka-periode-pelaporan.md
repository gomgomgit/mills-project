# Derived Assumptions Log — project.2-business-spec.usecases.usecase-144--buka-periode-pelaporan

## v2 — 2026-09-27

- `related_screen_ids` = screen-142 ← repoint usecase-index v16 (keputusan user)
- Aksi diubah dari "membuka PERIODE" menjadi "membuka SATU JENIS STASIUN" dengan label tombol "Buka Stasiun" ← kutipan dari blade yang sudah berjalan; nama usecase-nya sendiri dibiarkan "Buka Periode Pelaporan" agar cocok dengan usecase-index v16 yang tidak boleh diubah di langkah ini
- 8 bdd_scenario v1 dipertahankan seluruhnya dengan judul yang sama, ditambah 1 baru: "membuka satu stasiun tidak menyentuh stasiun lain" ← turunan agen
- Penekanan bahwa pesan penolakan untuk baris `open` dan baris `closed` TIDAK BOLEH sama ← dari `notDraftMessage()`; alasannya (dua aksi mudah tertukar) disalin dari docblock-nya
