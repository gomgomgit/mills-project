# Derived Assumptions Log — module-master-data.screen-031--kelola-machinery.2-business-spec

## v1 — 2026-08-19

- Insurance/tax_purchase managed as child-grids WITHIN the Machinery form (not separate screens) ← direct consequence of user's "full scope" answer to the earlier AskUserQuestion ("groups sebagai entity/screen tambahan, dan insurance/tax_purchases sebagai child-grid di dalam form Kelola Machinery")
- Machinery has NO delete-guard (deletion cascades to insurance/tax_purchase rows instead of being blocked) ← deliberate divergence from every other master-data screen's delete-guard pattern, reasoned from Machinery being the hierarchy's leaf entity: insurance/tax_purchase rows have no independent identity/reference elsewhere, so blocking deletion the way Corporate/Company/Business Unit/Station/Machinery Group do (checking for *referencing* children) doesn't apply the same way — these are pure ownership children, not independently-referenced entities
- station_id AND business_unit_id both auto-derived from the selected Machinery Group (not independent dropdowns) ← extends the same anti-drift pattern already established for Machinery Group's own business_unit_id (copied from Station)
- test_priority = "medium" ← comparable complexity to sibling master-data screens despite the extra child-grid complexity, since the additional complexity is more about form/UI mechanics than business-rule count

## v3 — 2026-09-30

Penggabungan screen-031 + screen-033 jadi satu layar. User memilih bentuk HIERARKIS setelah
diberi tiga pilihan beserta ongkos dan empat masalahnya; detail di bawah ini diturunkan agen,
bukan dinyatakan user.

- `available_actions[Buka/tutup sebuah grup]` = baris grup dapat dibuka/ditutup ← bentuk hierarkis mengharuskannya, tapi user tidak menyebut interaksinya
- `business_rules[paginasi per grup 20/halaman]` = mesin tidak ikut dihitung ← 223 grup × rata-rata 3 mesin; bila mesin ikut dihitung satu halaman melar jadi ~80 baris
- `business_rules[pencarian mencocokkan grup DAN mesin]` = grup yang memuat mesin cocok terbuka otomatis ← tanpa ini mencari nama mesin tidak menghasilkan apa pun yang terlihat; ini cacat, bukan preferensi
- `business_rules[wadah 'Tanpa grup']` = mesin tanpa induk wajib tetap terlihat ← machinery_group_id nullable; di dev sekarang NOL baris seperti itu, jadi cacatnya tidak akan terlihat saat dikerjakan, tapi skemanya mengizinkan
- `information_displayed[panel ringkas saat grup dibuka]` = Deskripsi/Unit/Workshop Factor/Cost per Equipment/Dibuat Pada pindah ke sini ← baris grup dan baris mesin hanya berbagi 3 dari 10/7 kolom; satu header bersama akan mengosongkan tujuh kolom pada tiap baris mesin
- `available_actions[Tambah Machinery dari baris grup]` = machinery_group_id terisi otomatis ← menghilangkan pemilihan grup yang sudah jelas dari konteks barisnya
- `edge_cases[keadaan terbuka tidak ikut pindah halaman]` = halaman baru mulai tertutup kecuali sedang mencari ← agen memutuskan; tidak dibahas dengan user
- `test_priority` = high (sebelumnya medium) ← aturan bisnis jadi 14 setelah penggabungan; ambang "high" adalah 5+

Bukan asumsi — dinyatakan/dipilih user pada 2026-09-30:
- bentuk hierarkis (bukan grup sidebar, bukan dua tab)
- mode Rata sebagai tampilan kedua, setelah agen melaporkan bahwa daftar rata seluruh
  Machinery akan HILANG. User menjawab "b saja" atas pilihan A (terima saja) / B (tambahkan
  tombol alih tampilan rata). Seluruh aturan dan aksi ber-mode-Rata berasal dari keputusan itu.

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (backend/app/Livewire/MasterData/KelolaMachinery.php, MachineryService.php, MachineryGroupService.php, kelola-machinery.blade.php, KelolaMachineryAuditTest.php).
- information_displayed[1] = + kolom Business Unit di baris grup ← blade <th>Business Unit</th>, toRow business_unit_name.
- information_displayed[4] = wadah 'Tanpa grup' saat cari hanya bila cocok, jumlah = yang cocok ← render() `$ungroupedCount = count($ungroupedRows)`.
- information_displayed[7..8] + (3 baru) = label pemilih 'Mill — Line — Station' / 'MG-xxx — deskripsi (Station · Line)', Station/Line sebagai teks, prefill grup dari '+ Mesin', pesan sukses, error baris child di bawah kolom ← stationOptions(), machineryGroupOptions(), blade, openCreateForm($groupId), formErrorKey().
- business_rules[0], [4] = kode unik case-insensitive ← UniqueCaseInsensitive.
- business_rules (+3) = gambar sungguhan; pesan sukses & feedback dibersihkan; nilai turunan sebagai teks ← RealImage, clearFeedback(), .kc-form-field__static.
- edge_cases[1], [2], [6] + (3 baru) = beda huruf; gambar palsu; error baris child; wadah tanpa grup saat cari; nama station kembar dibedakan label ← kode/tes.

## v6 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit 658cedc), code is truth (backend/resources/views/livewire/master-data/kelola-machinery.blade.php, app/Livewire/MasterData/KelolaMachinery.php, tests/Feature/Livewire/FilterBarTest.php).
- information_displayed[0] ← mode/Cari/filter kini satu bar filter bersama (segmen 'Tampilan').
- information_displayed[5] ← penghitung mesin tanpa grup kini catatan di ringkasan bar, mode Grup saja dan bila > 0.
- information_displayed[15] ← ringkasan bar: 'N grup'/'N mesin', badge filter aktif (hanya filter yang tampil di mode itu), Reset filter.
- edge_cases[16] ← Reset filter mengosongkan pencarian+filter+grup terbuka, ke halaman 1, mode tampilan dipertahankan (afterFilterReset).
