# Derived Assumptions Log — module-mobile-station-ops.screen-012--form-cages-track.3-tech-spec

## v2 — 2026-08-19

- Full rewrite mirroring screen-010/011 Form v4's business_logic/edge_case_handling/test structure, translated to Cages Track's checklist-grid field set ← structural mirroring per user instruction, field content per entity-catalog v3
- Time dropdown options computed as (0-23 minus hours used by ANY row) intersected with (hours > most-recently-added row's hour) ← direct technical translation of the confirmed ordering rule
- Cages Tipped header locked/disabled once ≥1 detail row exists ← still an OPEN QUESTION per business spec, not yet confirmed by user; implemented as the agent's proposed default, explicitly flagged again at the pre-implementation checkpoint
- Detail row deletion modeled as "mark for deletion, apply at next Simpan/Pause" (queue-for-deletion) ← same OPEN QUESTION status as above, mirrors Grading Detail's established pattern but not independently confirmed for Cages Track
- tippler_stop_time frozen ONLY on Simpan, explicitly NOT on Pause ← direct translation of user's "stop ketika menyimpan" instruction
- Checked By/Acknowledged By role-gating logic is a straightforward carry-over of the pre-v3 spec's existing Checked-By-Supervisor-only rule, extended to Acknowledged-By-Mill-Management-only (mirrors the same role field already present elsewhere in the entity-catalog, e.g. weighbridge-record.acknowledged_by) ← not a new design decision, just applying an existing project-wide convention
