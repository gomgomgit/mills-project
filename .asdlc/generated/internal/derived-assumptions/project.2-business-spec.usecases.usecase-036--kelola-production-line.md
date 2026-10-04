# Derived Assumptions — project.2-business-spec.usecases.usecase-036--kelola-production-line

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (ProductionLineService::create()/delete(), DEFAULT_STATIONS, KelolaProductionLine.php).
- alternative_flows[2].steps, postconditions[0], business_rules[2], bdd_scenarios[2..3] = hapus line + station kosong; ditolak bila record/Machinery Group/Machinery ← delete() (kontradiksi: dulu "ditolak jika punya Station").
- description, main_flow[4..5], business_rules[0..1], bdd_scenarios[0..1] = 18 station (bukan 15) + kode case-insensitive + pesan sukses ← DEFAULT_STATIONS 18 entri (sudah benar di kode sejak 2026-09-01; usecase tertinggal).
