# Derived Assumptions Log — module-master-data.screen-028--kelola-company.3-tech-spec

## v1 — 2026-08-19
(see earlier entries)

## v2 — 2026-08-19 (ERD rework)

- company_code globally unique, name still per-corporate unique ← two independent uniqueness rules coexist, per entity-catalog v4's decision (code mirrors ERD's PK-semantics: globally unique; name keeps its pre-existing screen-028 design point of per-parent scoping)
- logo/logo_url handling identical to screen-027's pattern (jpg/png, max 2MB, stored path exposed as logo_url) — kept consistent across both entities now carrying a logo field
- last_update (date, optional) passed through in request/response per entity-catalog's addition, no additional validation beyond being a valid date
