# Derived Assumptions Log — module-master-data.screen-027--kelola-corporate.2-business-spec

## v1 — 2026-08-19

- actors = ["actor-admin"] only ← derived from PRD's confirmed assumption "Master data (Station, Machinery, dst) dikelola sepenuhnya oleh Admin via web, mobile hanya read-only" + actor-index's Admin permissions listing Corporate/Company/Business Unit/Station/Machinery explicitly
- business_rules includes "Corporate tidak dapat dihapus jika masih memiliki satu atau lebih Company terkait" ← inferred referential-integrity guard from the Corporate→Company hierarchy (entity-catalog relationship), not stated directly by the user
- test_priority = "medium" ← derived: 3 business rules (uniqueness, delete-guard, admin-only access) falls in the "meaningful business rules (2-4)" bucket, not "high" (no payment/auth/personal-data handling) nor "low" (has create/edit/delete, not read-only)
- edge_cases (all 3) proposed by agent from standard CRUD failure modes (duplicate name, delete-with-children, empty list), not user-specified
