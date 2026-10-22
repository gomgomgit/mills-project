
## v1 — 2026-10-22

- rentang apply dibatasi 1830 hari ← spec tidak menyebut batas; mencegah permintaan raksasa
- validasi dates-vs-range pada apply → 422 ← tabel endpoint dan business logic spec sempat berbeda soal kode; dipilih 422 (VALIDATION_ERROR)
- deleted_at dibaca sebagai nilai Eloquent zona waktu aplikasi, bukan UTC ← spec meminta zona aplikasi tetapi tidak menyebut cara membaca kolomnya
- CSS ditulis inline di blade (kelas jd-*), mengikuti Kelola Shift, bukan menyalin CSS mock ← spec tidak menyebut tempat gaya
- penolakan peran lewat middleware EnsureRole tetap tanpa field code ← perilaku lama, tidak diubah
- tes sengaja tidak ditulis ← test_generation_level = none (config), status partial

## v2 — 2026-10-22

- sel tabel = satu <button> penuh, kotak kalender = div role=button ← spec hanya minta 'bisa diklik'; pilihan elemen oleh agen
- tabel menampilkan semua nama (tanpa '+N lagi') agar tidak ada tombol bersarang; kalender memakai '+N lagi' ← keputusan agen
- jd-discard-yes menampilkan spinner tanpa teks sibuk ← satu asersi tes mengunci teks tombol persis; tradeoff dicatat
- kalender menyembunyikan baris shift tertutup tanpa petugas; tabel tetap menulis 'belum ada petugas' ← keputusan agen
- layar sempit: satu baris per tanggal tanpa judul per minggu ← penyederhanaan agen
- status dibiarkan 'partial' meski 192 tes layar lulus ← Playwright tidak pernah dijalankan; geometri tak terbukti

## v3 — 2026-10-22

- status 'partial' -> 'complete' ← keputusan agen: semua lapisan tes lulus (unit 78, integration 40, component 75, browser 43), tanpa deferred item. Syarat 'terverifikasi' dipenuhi oleh Playwright 43/43 di DB e2e bersih. Catatan: PostgreSQL pada tingkat layar tetap tidak terbukti (known_issue major) — keputusan status ini dapat dibalik bila kamu menganggap itu syarat 'complete'.
- Playwright tidak idempoten ← ditemukan saat menjalankan ulang tanpa reset; dicatat sebagai known_issue, tidak diperbaiki
- angka tes browser 43 = jumlah tes terdaftar yang lulus pada run bersih ← dihitung dari log run 2026-10-22
