# Derived Assumptions Log — project.2-business-spec.usecases.usecase-029--kelola-business-unit

## v1 — 2026-08-19

- Full main_flow/alternative_flows drafted from screen's available_actions + business_rules (no separate user interview, autopilot fast-path), same pattern as usecase-027/028
- bdd-spec-writer-agent kept 2 business-rule scenarios separate this round (unlike usecase-027/028 which each kept only 1) — "Akses ditolak untuk non-Admin" AND "Company induk wajib dipilih" both got their own scenario since neither was already exercised by an alternative_flow (the "Belum ada Company" alt-flow covers the case where no Company exists at all, which is distinct from "a Company exists but the Admin didn't select one")

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (BusinessUnitService::delete(), KelolaBusinessUnit.php, MasterDataDeleteGuardTest.php).
- alternative_flows[1..2] (trigger+steps), postconditions[1], business_rules[1], bdd_scenarios[2..3] = hapus hanya bila tak ada User/Production Line/Station/Periode; penolakan merinci jumlah; sukses tampil pesan ← delete() + successMessage. Sebelumnya hanya "Station" (kontradiksi dengan kode).
