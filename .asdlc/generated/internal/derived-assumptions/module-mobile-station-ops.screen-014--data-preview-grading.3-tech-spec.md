# Derived Assumptions Log — module-mobile-station-ops.screen-014--data-preview-grading.3-tech-spec

## v2 — 2026-08-19

- Full rewrite mirroring screen-013--data-preview-weighbridge v3's business_logic/edge_case_handling/test structure ← structural mirroring per user instruction, field content per entity-catalog v2
- Search fields: grading_number / license_plate_no (not wb_card_number/driver_name) ← driver_name no longer exists on grading-record; license_plate_no is the closest vehicle-identifying field
- Detail mode resolves each grading_detail row's Quality Parameter display name via grading_parameter_id join at render time (read-only lookup, not stored) ← natural consequence of grading-detail only storing the FK + snapshot uom, not the parameter name itself
- GradingDetailGrid.vue (old component, built around the free-text `category` field) intentionally NOT reused for the read-only detail grid here, same reasoning as screen-011's inline grid ← consistent with that screen's precedent, not independently re-confirmed with user
- gradingRecordRepo.ts needs a new getAllRecords(userId) function mirroring weighbridgeRecordRepo.ts's — not yet written, to be added during implementation
