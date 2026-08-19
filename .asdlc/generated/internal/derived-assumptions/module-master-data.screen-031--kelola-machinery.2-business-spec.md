# Derived Assumptions Log — module-master-data.screen-031--kelola-machinery.2-business-spec

## v1 — 2026-08-19

- Insurance/tax_purchase managed as child-grids WITHIN the Machinery form (not separate screens) ← direct consequence of user's "full scope" answer to the earlier AskUserQuestion ("groups sebagai entity/screen tambahan, dan insurance/tax_purchases sebagai child-grid di dalam form Kelola Machinery")
- Machinery has NO delete-guard (deletion cascades to insurance/tax_purchase rows instead of being blocked) ← deliberate divergence from every other master-data screen's delete-guard pattern, reasoned from Machinery being the hierarchy's leaf entity: insurance/tax_purchase rows have no independent identity/reference elsewhere, so blocking deletion the way Corporate/Company/Business Unit/Station/Machinery Group do (checking for *referencing* children) doesn't apply the same way — these are pure ownership children, not independently-referenced entities
- station_id AND business_unit_id both auto-derived from the selected Machinery Group (not independent dropdowns) ← extends the same anti-drift pattern already established for Machinery Group's own business_unit_id (copied from Station)
- test_priority = "medium" ← comparable complexity to sibling master-data screens despite the extra child-grid complexity, since the additional complexity is more about form/UI mechanics than business-rule count
