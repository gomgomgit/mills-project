# Derived Assumptions Log — module-mobile-station-ops.screen-011--form-grading.3-tech-spec

## v2 — 2026-08-18

- Full rewrite mirroring screen-010--form-weighbridge v4's business_logic/edge_case_handling/test structure, translated to Grading's mock-driven field set ← structural mirroring per user instruction, field content per entity-catalog v2
- WB Card No dropdown query: SELECT all local weighbridge_record rows (any status), ORDER BY arrival_datetime DESC ← no explicit scope instruction from user; documented explicitly as an assumption, not a confirmed decision, pending checkpoint review
- Grading Detail row deletion modeled as "mark for deletion, apply at next Simpan/Pause" rather than immediate DB delete on click ← consistent with the form's general "nothing persists until Simpan/Pause" pattern used elsewhere (e.g. Weighbridge's dirty-state Back confirmation), not stated explicitly by user but a natural extension of that pattern
- Percentage/UOM explicitly documented as read-only/computed fields, never directly user-editable ← direct translation of business_rules, not a new assumption but called out for implementer clarity

## v3 — 2026-08-19

- Quality Parameter dropdown exclusion implemented as a per-row reactive computed over ALL detailRows (not just the row's own state) ← technical translation of the new business rule; the row itself always keeps its own current selection visible in its own dropdown (so it doesn't visually vanish), only OTHER rows exclude it
- No change to grading_detail's persisted shape — this is purely a UI-selection constraint (which options are offered), not a data-model or validation-on-save change; two rows with the same grading_parameter_id are prevented at selection time, not re-validated at Simpan
