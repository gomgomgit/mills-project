# Derived Assumptions Log — module-master-data.screen-029--kelola-business-unit.3-tech-spec

## v1 — 2026-08-19
(see earlier entries)

## v2 — 2026-08-19 (ERD rework)

- code (existing since v1) kept its field name, not renamed to business_unit_code — consistent with entity-catalog v4's decision to only introduce entity-prefixed `*_code` naming for fields that didn't already exist (corporate_code, company_code)
- Logo storage convention: screen-027's implementation established `FILESYSTEM_DISK=local` with Laravel's `local` disk `'serve' => true` auto-registering `/storage/{path}` (no `public` disk / `storage:link` needed) — reused here as `LOGO_DIRECTORY=business-unit-logos`, kept consistent across all 3 master-data screens with a logo field rather than letting each screen invent its own convention
- business_unit_type_code treated as a free-text optional string (not an enum/FK to a lookup table) — the ERD gives no further detail on what values it takes or whether it's governed by a master list; kept as a plain string until such a list is specified
