# Derived Assumptions Log — project.2-business-spec.usecases.usecase-018--data-browser-cages-track-web

## v1 — 2026-08-18

- main_flow/alternative_flows/postconditions/business_rules = seluruh isi diturunkan meniru usecase-016 (Telusuri & Ekspor Data Weighbridge) persis, substitusi terminologi Cages Track ← autopilot: drafted from screen spec, not individually confirmed; pola identik sudah dikonfirmasi 2x sebelumnya (usecase-016, usecase-017)
- bdd_scenarios (5 scenario) = diturunkan bdd-spec-writer-agent dari main_flow + 4 alternative_flows; tidak ada scenario per-role terpisah karena main_flow tidak membedakan hasil per actor (Supervisor/Mill Management/Admin identik) ← agent derived, tidak ditanyakan ke user
