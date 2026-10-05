# Derived Assumptions — module-master-data.screen-030--kelola-station.2-business-spec

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (backend/app/Livewire/MasterData/KelolaStation.php, backend/app/Services/StationService.php, kelola-station.blade.php, KelolaStationAuditTest.php).
- description, available_actions[0..2] = Station di bawah BU + Production Line, filter BU & Production Line ← listStations(..., $productionLineId), filterProductionLineId. (Spec ver 1 masih "3 aktif + 12 placeholder", tanpa Production Line.)
- information_displayed (ditulis ulang) = kolom Production Line, label tipe master, urutan per line, filter line bergantung BU ('Mill — Line'), dropdown Type dari station_types, empty state terfilter, pesan sukses ← render(), filterProductionLineOptions(), typeOptions(), typeLabels(), blade.
- business_rules[0] = kode unik case-insensitive ← UniqueCaseInsensitive.
- business_rules[1] = type harus di station_types; Other tidak boleh aktif ← Rule::exists('station_types','code') + after().
- business_rules[4] = wajib BU + Production Line milik BU yang sama ← StationService::validate() after().
- business_rules (+2) = satu station per tipe per line kecuali Other (cek hanya bila line/tipe berubah); pesan sukses ← duplicateTypeMessage(), successMessage.
- edge_cases[1] + (3 baru) = beda huruf; tipe kembar; edit tipe non-MVP (Boiler Room) tampil; empty state terfilter + reset filter line ← kode + KelolaStationAuditTest.

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (StationService.php, Livewire/KelolaStationTest.php).
- description, available_actions[3], business_rules[2] ← delete-guard kini juga menolak Station yang punya record stasiun di 18 tabel record; pesan menyebut jumlah record.
- edge_cases (+1) ← hapus station dengan record stasiun: pesan ramah, dialog tertutup, baris tetap, tanpa 500.

## v4 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit 658cedc), code is truth (backend/resources/views/livewire/master-data/kelola-station.blade.php, app/Livewire/MasterData/KelolaStation.php).
- information_displayed[5] ← bar filter bersama (Business Unit + Production Line), jumlah hasil, badge filter aktif, Reset filter mengosongkan keduanya.
