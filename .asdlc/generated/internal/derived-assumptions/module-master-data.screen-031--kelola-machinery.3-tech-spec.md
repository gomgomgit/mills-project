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

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v4)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (MachineryService.php, MachineryGroupService.php, KelolaMachinery.php, KelolaMachineryAuditTest.php).
- api_contracts[0].endpoints[1].response.success_schema = + label ← machineryGroupOptions().
- api_contracts[0].endpoints[3..4].request.body_schema.{equipment_code,picture} = case-insensitive / RealImage ← MachineryService::validate().
- api_contracts[0].business_logic[2], [4], [5] = opsi berlabel; validasi case-insensitive + RealImage ← kode.
- api_contracts[0].edge_case_handling[0], [4] + (1) = beda huruf; gambar palsu; pemetaan error baris child ← formErrorKey().
- api_contracts[0].unit_test_cases (+3) ⚠ diturunkan dari kode/KelolaMachineryAuditTest, contoh label 'MG-001 — Conveyor (Weighbridge · Line 1)' ilustratif.
- api_contracts[1].endpoints[0].response data[0] = + business_unit_name; endpoints[1] = + label; endpoints[2..3].group_code = case-insensitive; business_logic[3..5]; edge_case_handling[0] ← MachineryGroupService. ⚠ diasumsikan GET /api/stations/options memakai stationOptions() yang sama (tidak dibuka controller-nya).
- implementation_notes (+) = REVISI audit-fix.

## v6 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit 658cedc, c321f32), code is truth (app/Livewire/MasterData/KelolaMachinery.php, app/Livewire/Concerns/HasFilterReset.php, backend/resources/views/livewire/master-data/kelola-machinery.blade.php).
- implementation_notes[24] ← HasFilterReset: filterDefaults, afterFilterReset() kosongkan expandedGroupIds, viewMode tak di-reset, activeFilterCount except per mode, note ungrouped di bar, ld-region & busy-label.
