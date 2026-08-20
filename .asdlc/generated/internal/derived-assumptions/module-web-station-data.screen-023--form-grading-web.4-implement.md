# Derived Assumptions Log — module-web-station-data.screen-023--form-grading-web.4-implement

## v1 — 2026-08-20

- **`create()` writes the record as `status=Synced` first, upserts details, then flips to `status=saved`** — not in the tech spec's business_logic literally; discovered as a technical necessity mid-implementation (`GradingRecord::booted()`'s `saving` guard rejects a brand-new `status=saved` record with zero details). Final observable behavior (API/UI response, DB end-state) is unaffected — the record ends at `status=saved` either way.
- **`grading_records.vehicle_code` migration + `doctrine/dbal` composer dependency** — not anticipated by the tech spec at all; a real, confirmed schema gap found while writing tests (a prior session's "make vehicle_code optional" change never touched the DB). Fixed rather than worked around, since the alternative (always sending a placeholder value) would misrepresent optionality to the user.
