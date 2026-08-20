## v1 — 2026-08-20

- Response resolves checked_by/acknowledged_by to display names (checked_by_name/acknowledged_by_name) via join with User, rather than returning raw UUIDs ← not explicitly requested; inferred as necessary for a human-readable read-only detail screen (list screen-016 doesn't need this, but a detail view showing raw UUIDs would be unusable)
- station_name resolved via join with Station, same reasoning ← inferred
- Route `/data/weighbridge/{id}` ← taken directly from screen-016's own test_scenarios ("browser navigates to /data/weighbridge/{id}"), not independently chosen
- 404 RECORD_NOT_FOUND error code naming ← follows the existing error_format/naming convention already used elsewhere in shared-decisions, not explicitly specified for this screen
