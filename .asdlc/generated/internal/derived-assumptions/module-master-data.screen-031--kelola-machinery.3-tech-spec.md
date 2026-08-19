# Derived Assumptions Log — module-master-data.screen-031--kelola-machinery.3-tech-spec

## v1 — 2026-08-19

- New GET /api/machinery/:id detail endpoint (not present on any sibling master-data screen) ← needed because the Edit form must load 2 child-grids at once; the paginated list row alone isn't enough, unlike every simpler CRUD sibling where the list row itself has everything the edit form needs
- Update uses "replace-all" semantics for insurances/tax_purchases (delete old rows, insert new ones sent in the request) rather than diffing by id ← simplest correct approach given these child rows have no independent identity the FE needs to track across edits (mirrors how Grading Detail rows are handled in Form Grading, not a novel pattern for this codebase)
- picture (Machinery's own image) follows the same Laravel Filesystem local-disk convention already established for Corporate/Company/Business Unit logos
- 403 FORBIDDEN inferred from actor_permissions on every endpoint, consistent with siblings — no pre-existing public-endpoint collision here (unlike Business Unit's login-picker situation)
