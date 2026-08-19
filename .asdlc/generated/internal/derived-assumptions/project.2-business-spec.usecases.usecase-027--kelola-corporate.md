# Derived Assumptions Log — project.2-business-spec.usecases.usecase-027--kelola-corporate

## v1 — 2026-08-19

- Full main_flow/alternative_flows drafted from the screen's available_actions + business_rules (no separate user interview per usecase, per autopilot fast-path) ← usecase-index already listed this usecase as a stub (id/name/screen_ids only); this write fills in its actual content for the first time
- bdd_scenarios derived by bdd-spec-writer-agent: 1 happy-path (single actor) + 4 alternative-flow scenarios + 1 business-rule scenario (admin-only access); 2 business rules (name uniqueness, delete-guard) were intentionally not given their own separate scenario since they're already covered by the "Nama duplikat" and "Hapus Corporate — ditolak" alternative-flow scenarios
