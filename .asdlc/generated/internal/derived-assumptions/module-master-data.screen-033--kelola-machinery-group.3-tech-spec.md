# Derived Assumptions Log — module-master-data.screen-033--kelola-machinery-group.3-tech-spec

## v1 — 2026-08-19

- business_unit_id on machinery_groups is ALWAYS copied server-side from the selected Station, never accepted as independent user input ← enforces the hierarchy-consistency business rule structurally rather than via a separate cross-field validation check, avoiding a class of bugs where the two could drift out of sync
- GET /api/stations/options returns business_unit_id per row (not just id/name) ← needed so the FE can read/copy the selected Station's business_unit_id without a second round-trip
- Expects screen-030 (Kelola Station) to have already created minimal placeholder migrations/models for machinery_groups/machinery (for its own delete-guard) — this screen's implementation EXTENDS those, does not recreate them from scratch
