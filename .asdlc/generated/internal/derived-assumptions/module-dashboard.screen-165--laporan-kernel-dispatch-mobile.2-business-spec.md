# Derived Assumptions — module-dashboard.screen-165--laporan-kernel-dispatch-mobile.2-business-spec

## v1 — 2026-10-18

- Seluruh isi DITURUNKAN dari screen-164 (pasangan web) dan dari pola enam laporan mobile sebelumnya, bukan dari wawancara.
- Operator dimasukkan sebagai PENGGUNA, bukan sekadar diizinkan ← pola screen-159/161/163.
- Kartu tonase dan kartu mutu BERURUTAN dan TIDAK dapat dilipat, sementara rekap boleh ← keputusan tata letak saya. Di web keduanya berdampingan; pada lebar ponsel itu tidak mungkin, jadi yang dipertahankan MAKSUDNYA: tonase tinggi dengan kadar air tinggi adalah kabar buruk yang menyamar sebagai kabar baik, sehingga keduanya harus terbaca dalam satu tarikan tanpa tindakan pengguna.
- Luring MENUTUP angkanya ← penyimpangan sadar dari preseden Engine Room, diambil di screen-161 dan diteruskan.
- Ekspor hanya CSV ← pola laporan mobile sebelumnya; pengguna tidak menyebut format.
- Keempat ukuran mutu BERTUMPUK dalam satu kartu dengan tiga angka + penyebut tetap terbaca ← keputusan saya untuk layar sempit; tidak ada preseden mobile untuk kartu mutu empat-ukuran.
- `test_priority` = high ← 26 aturan dan gabungan dua bentuk angka.
- `open_questions` KOSONG ← kedua pertanyaan terbuka screen-164 sudah terjawab: label gate status sudah dinaikkan ke `Display::OPTION_LABELS` (commit 88d5904), dan ketiadaan master ambang mutu sudah dinyatakan di layar sebagai batas data.
