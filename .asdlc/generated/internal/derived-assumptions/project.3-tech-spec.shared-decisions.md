# Derived Assumptions Log — project.3-tech-spec.shared-decisions

## v1 — 2026-08-17

- auth.mechanism / auth.session_strategy / auth.notes ← diturunkan dari arch_spec.tech_stack ("Laravel Sanctum + session auth") dan nfr.security, diterima tanpa koreksi
- error_format (structure, example, notes) ← diturunkan dari konvensi standar Laravel, tidak dinyatakan eksplisit di arch-spec
- pagination.strategy / pagination.defaults / pagination.notes ← diturunkan dari konvensi pagination bawaan Laravel, tidak dinyatakan eksplisit di arch-spec
- naming_conventions (api_endpoints, db_tables, db_columns) ← diturunkan dari tech stack Laravel/MySQL, tidak dinyatakan eksplisit di arch-spec
- other_decisions (timestamps UTC ISO 8601, file storage Laravel Filesystem local disk, UUID untuk entitas offline mobile) ← diturunkan dari constraints/goals di PRD, bukan pernyataan eksplisit di shared-decisions
