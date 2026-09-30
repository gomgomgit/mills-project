# Derived Assumptions Log — module-master-data.screen-031--kelola-machinery.3-tech-spec

## v1 — 2026-08-19

- New GET /api/machinery/:id detail endpoint (not present on any sibling master-data screen) ← needed because the Edit form must load 2 child-grids at once; the paginated list row alone isn't enough, unlike every simpler CRUD sibling where the list row itself has everything the edit form needs
- Update uses "replace-all" semantics for insurances/tax_purchases (delete old rows, insert new ones sent in the request) rather than diffing by id ← simplest correct approach given these child rows have no independent identity the FE needs to track across edits (mirrors how Grading Detail rows are handled in Form Grading, not a novel pattern for this codebase)
- picture (Machinery's own image) follows the same Laravel Filesystem local-disk convention already established for Corporate/Company/Business Unit logos
- 403 FORBIDDEN inferred from actor_permissions on every endpoint, consistent with siblings — no pre-existing public-endpoint collision here (unlike Business Unit's login-picker situation)

## v2 — 2026-09-30

Tech spec penggabungan screen-031 + screen-033. Bentuk hierarkis dan mode Rata adalah
keputusan user; seluruh penerjemahan teknis di bawah ini diturunkan agen.

- `GET /api/machinery?ungrouped=true` = parameter baru, bukan endpoint baru ← wadah "Tanpa grup" butuh sumber data; menambah endpoint tersendiri berarti satu rute lagi untuk hal yang cuma beda WHERE
- `422 bila machinery_group_id dan ungrouped dikirim bersamaan` = ditolak tegas ← agen memutuskan; alternatifnya mengabaikan salah satu diam-diam, yang menghasilkan daftar tampak benar padahal salah
- `has_search_match_in_machinery` = field respons baru pada /api/machinery-groups ← tanpa penanda ini FE tidak tahu grup mana harus dibuka otomatis, dan pencarian lintas grup jadi tidak berguna; user hanya menyebut "grup terbuka otomatis", bukan mekanismenya
- `meta.ungrouped_machinery_count` = ditempel pada meta endpoint yang sudah ada ← alternatifnya endpoint penghitung tersendiri; dipilih meta agar tidak menambah rute untuk satu angka
- `mode tampilan tidak dikenal backend` = tidak ada parameter 'mode' di endpoint mana pun ← agen memutuskan bahwa Grup/Rata murni keadaan FE; konsekuensinya nol cabang server per mode, dan paginasi dua satuan jatuh sendirinya karena dua endpoint berbeda
- `route tetap /master-data/machinery` = rute screen-031 yang bertahan ← user tidak menyebut rute; dipilih karena mesin yang dominan dan URL-nya lebih umum dibaca
- `redirect /master-data/machinery-groups` = bukan 404 ← agen memutuskan; bookmark dan tautan lama akan patah tanpa itu
- `screen_dependencies` = screen-033 dihapus, screen-030 (Kelola Station) masuk ← ketergantungan pada Station pindah ke sini bersama penyerapan; ketergantungan pada screen-033 lenyap karena layarnya tidak ada lagi

Empat unit test case ditambahkan setelah pemeriksaan makna terhadap spec lama menemukan
celah yang pencocokan mekanis tidak bisa lihat (test lama berbahasa Inggris, yang baru
Indonesia, sehingga pencocokan literal melaporkan 29 "hilang" padahal 25 di antaranya
hanya beda bahasa):
- POST create mengizinkan Machinery tanpa satu pun baris riwayat
- PATCH menolak equipment_code milik Machinery LAIN (cabang exclude-self)
- GET /api/stations/options membawa business_unit_id per baris
- PATCH menolak group_code milik Machinery Group LAIN (cabang exclude-self)

BELUM DIKERJAKAN: atribusi 5 endpoint machinery-group di api-index masih menunjuk
screen-033. Tidak memengaruhi Phase 4 (generasi kode membaca tech spec layar, bukan
api-index), tetapi tabel rujukannya salah sampai diperbaiki.
