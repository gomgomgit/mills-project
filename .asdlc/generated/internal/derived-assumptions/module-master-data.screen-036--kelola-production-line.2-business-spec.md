# Derived Assumptions Log — module-master-data.screen-036--kelola-production-line.2-business-spec

## v2 — 2026-08-31

- business_rules' canonical-station breakdown corrected from "3 aktif + 12 placeholder" (stale since the 2026-08-23 Threshing/Pressing/Depricarping/Kernel Plant promotion, never updated on this screen) directly to "17 aktif + 2 placeholder" (19 total) ← combines the missed 2026-08-23 fix with this session's new promotion of 6 more placeholders (Clarification, Effluent Plant, Storage Tank, Engine Room, Boiler Room, Process Water) and addition of 4 wholly-new stations (Solid Waste Disposal, Kernel Dispatch, CPO Dispatch, Process Quality Control), confirmed directly by the user this session.

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (backend/app/Services/ProductionLineService.php delete(), KelolaProductionLine.php, kelola-production-line.blade.php, MasterDataDeleteGuardTest.php).
- available_actions[3].description, business_rules[5], edge_cases[1] = hapus line + station kosong dalam satu transaksi; ditolak bila record stasiun/Machinery Group/Machinery, pesan merinci ← delete() DB::transaction + $blockers.
- business_rules[2], edge_cases[0] = kode unik tidak peka huruf ← UniqueCaseInsensitive::on('production_lines','code').
- business_rules (+1), information_displayed (+1) = pesan sukses ← successMessage.
- ⚠ angka dalam contoh pesan (5 record stasiun, 1 Machinery Group) ilustratif.
