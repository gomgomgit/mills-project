# Derived Assumptions — project.2-business-spec.usecases.usecase-030--kelola-station

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (StationService::validate()/duplicateTypeMessage(), KelolaStation.php).
- main_flow[2..3] = pilih Production Line + tipe dari master; validasi satu-tipe-per-line; pesan sukses ← kode.
- alternative_flows[4].steps[1], business_rules[1], bdd_scenarios[5].then = hanya Other yang dilarang aktif (kontradiksi: dulu "hanya WB/Grading/CT boleh aktif") ← pesan after() di StationService.
- alternative_flows (+1), business_rules (+1), bdd_scenarios (+1) = tipe kembar dalam satu line ditolak ← duplicateTypeMessage() + KelolaStationAuditTest.
