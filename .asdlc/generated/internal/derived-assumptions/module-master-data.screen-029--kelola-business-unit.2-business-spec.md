# Derived Assumptions Log — module-master-data.screen-029--kelola-business-unit.2-business-spec

## v1 — 2026-08-19

- actors = ["actor-admin"] only ← same reasoning as screen-027/028
- business_rules includes "Business Unit tidak dapat dihapus jika masih memiliki satu atau lebih Station terkait" ← inferred referential-integrity guard, same pattern as prior two master-data screens
- business_rules includes "Kode Business Unit harus unik di seluruh sistem (bukan hanya dalam satu Company)" ← derived from entity-catalog's constraint "code harus unik" (no scoping clause, unlike Company's explicit "dalam satu corporate") — this is a DELIBERATE contrast to screen-028: uniqueness here is on `code`, is GLOBAL not per-parent, whereas screen-028's Company uniqueness is on `name` and IS per-parent. Flagged explicitly since the two screens' uniqueness rules differ in both which field and what scope.
- edge_cases includes "Belum ada Company sama sekali" ← same FK-dependency pattern as screen-028's "Belum ada Corporate"
- test_priority = "medium" ← 4 business rules, same bucket reasoning as screen-027/028
