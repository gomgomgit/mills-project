# Derived Assumptions Log — module-master-data.screen-127--master-data-tree-view.3-tech-spec

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v2 — 2026-10-06

- `route` = `/master-data/tree-view` TIDAK diubah ← keputusan sadar: rutenya kunci bookmark dan id layarnya kunci dep-graph. Namanya yang menyesatkan dibiarkan sebagai open question Phase 2, bukan diputuskan di sini.
- Pemuatan data dipindah dari `mount()` ke `render()` ← turunan teknis, tidak diminta siapa pun. Alasan tertulis versi lama ("read-only, tidak paginasi, refetch sia-sia") mati bersama revamp; dan memuat di render() yang membuat "angka dihitung ulang sesudah aksi" tercapai tanpa penyesuaian manual, termasuk kasus pindah-induk yang mengubah jumlah pada dua induk.
- `MasterDataTreeService` DIPERLUAS dengan `board()` / `counts()` / `corporateRows()` / `companyRows()`, bukan diganti ← pilihan agen. Nama-nama metode itu karangan agen; yang tetap adalah satu query nested eager-load supaya bebas N+1.
- Angka ringkasan dihitung lewat COUNT terpisah per tabel, BUKAN dari koleksi tersaring ← turunan agen dari business rule "baris ringkasan membuktikan tidak ada yang disembunyikan". Konsekuensinya: saat penyaring aktif, total tidak mengecil dan jumlah-yang-cocok ditampilkan sebagai keterangan terpisah. Keputusan itu tidak dinyatakan user.
- Penyaringan dilakukan di PHP atas hasil satu query, bukan WHERE di basis data ← pilihan agen, beralasan pada 20 baris dan konvensi proyek (agregasi di PHP). Ikut ditinjau bila jumlah mill melewati ambang di business_rules.
- Mill tanpa line ditentukan dari `production_lines_count === 0`, BUKAN dari koleksi line kosong sesudah penyaringan ← dibedakan agen; keduanya mudah tertukar dan salah satunya menyesatkan.
- SATU modal untuk empat tingkat (`$modalLevel` menentukan field dan service) alih-alih empat modal ← pilihan agen.
- Validasi dua lapis (rules() komponen sebagai cermin + service sebagai otoritas, ValidationException dipetakan ke `form.<field>`) ← BUKAN pilihan agen, ini pola yang sudah ada dan terbaca di `KelolaCorporate::save()`. Dicatat di sini karena Phase 2 semula menyatakan "tidak boleh punya aturan sendiri", yang terlalu absolut dan sudah dikoreksi di business spec v3.
- `$logo` null pada mode edit = "jangan ubah logo", bukan "hapus logo" ← diturunkan dari kenyataan bahwa keempat service tidak punya jalur hapus-logo. Layar ini karena itu tidak menjanjikannya.
- Konfirmasi hapus WAJIB menyebut bahwa menghapus Production Line juga menghapus stasiunnya ← diangkat agen setelah membaca `ProductionLineService::delete()`. Tidak disebut user, dan tidak disebut di business spec; satu-satunya tempat cakupan penghapusan itu terlihat adalah kode service.
- Nama entitas disimpan di `$confirmingDelete` saat `askDelete()`, bukan dicari ulang saat merender ← pilihan agen, supaya konfirmasi tetap menyebut nama yang benar bila data berubah di antara dua render.
- api-index TIDAK ditulis ← penyimpangan sadar dari Step 9 perintah `tech-2-screen`. Layar ini tidak punya endpoint HTTP dan sudah berisi 0 entri sebelum maupun sesudah revamp, jadi "penggantian idempoten" itu no-op dan menulisnya hanya menaikkan nomor versi tanpa perubahan isi. Dicatat supaya tidak terbaca sebagai kelalaian.
- `x-master-data-tree-node` dinyatakan boleh dihapus ← SUDAH DIVERIFIKASI, bukan dugaan: grep di `backend/resources` dan `backend/app` menunjukkan pemakainya hanya berkasnya sendiri (rekursi) dan blade tree-view yang akan diganti.
- Penggantian dua skenario uji lama (`toggleNode`, tautan ber-filter induk) dan kewajiban mempertahankan dua lainnya (bebas N+1, 403) ← keputusan agen atas uji yang sudah ada; tidak dibahas user.
- Kewajiban browser spec baru + syarat dapat jalan sendirian sesudah `prepare-db.sh` ← diturunkan dari test-strategy dan dari pelajaran commit 9db7a25 di sesi ini.
- `test_scenarios` diturunkan `test-spec-writer-agent` dari 46 skenario BDD Phase 2, tanpa konfirmasi satu per satu (autopilot).
