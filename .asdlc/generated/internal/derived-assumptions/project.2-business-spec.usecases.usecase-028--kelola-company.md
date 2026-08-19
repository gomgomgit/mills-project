# Derived Assumptions Log — project.2-business-spec.usecases.usecase-028--kelola-company

## v1 — 2026-08-19

- Full main_flow/alternative_flows drafted from screen's available_actions + business_rules (no separate user interview, autopilot fast-path), same pattern as usecase-027
- bdd-spec-writer-agent intentionally skipped 3 of 4 business_rules as separate scenarios (uniqueness, delete-guard, FK-required) since they're already covered by their corresponding alternative-flow scenarios — only kept the admin-only access rule as its own scenario since no flow already exercises it
