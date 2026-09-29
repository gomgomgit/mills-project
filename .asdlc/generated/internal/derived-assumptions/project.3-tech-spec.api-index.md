
## v58 — 2026-09-28

- Hanya SATU endpoint baru didaftarkan (`/production-lines/options-for-report`), bukan kesembilan rute `/production-lines` ← ditemukan bahwa api-index memuat NOL endpoint `/production-lines`, padahal 9 ada di kode. Celah itu pra-ada dan milik screen-036; mendaftarkan kedelapan sisanya adalah pekerjaan tersendiri, bukan diselundupkan ke sinkronisasi ini.
- Parameter `production_line_id` TIDAK dicatat sebagai daftar parameter ← skema `api-index` tidak punya field untuk parameter permintaan (hanya method, path, description, screen_id, usecase_id, auth_required, actor_ids), dan deskripsi yang ada berkonvensi menjelaskan apa yang DIKEMBALIKAN, bukan mendaftar parameter. Menambahkan nama parameter ke 29 deskripsi akan melanggar konvensi itu; yang diperbarui hanya 11 endpoint laporan, tempat perilakunya benar-benar berubah.
- `screen_id` endpoint baru diisi screen-135 ← ia melayani kelima layar laporan mobile, tetapi skema hanya menyediakan satu `screen_id`. Dipilih yang pertama; deskripsinya menyebut perannya agar tidak terbaca sebagai milik satu layar saja.
