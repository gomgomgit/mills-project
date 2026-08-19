# Derived Assumptions Log — module-master-data.screen-028--kelola-company.2-business-spec

## v1 — 2026-08-19

- actors = ["actor-admin"] only ← same reasoning as screen-027 (Kelola Corporate)
- business_rules includes "Company tidak dapat dihapus jika masih memiliki satu atau lebih Business Unit terkait" ← inferred referential-integrity guard from Company→Business Unit hierarchy, not stated directly
- business_rules includes "Nama Company harus unik dalam satu Corporate" (not globally unique) ← derived from entity-catalog's constraint "name unik dalam satu corporate" — a deliberate contrast with Corporate's global-uniqueness rule (screen-027), flagged since it's easy to conflate the two
- edge_cases includes "Belum ada Corporate sama sekali" ← derived from the FK dependency (Company always requires a parent Corporate); this screen's create flow is blocked/guided rather than allowing free-text corporate entry
- test_priority = "medium" ← 4 business rules, same bucket reasoning as screen-027
