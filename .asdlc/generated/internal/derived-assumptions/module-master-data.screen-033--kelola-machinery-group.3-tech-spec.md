# Derived Assumptions Log — module-master-data.screen-033--kelola-machinery-group.3-tech-spec

## v1 — 2026-08-19

- business_unit_id on machinery_groups is ALWAYS copied server-side from the selected Station, never accepted as independent user input ← enforces the hierarchy-consistency business rule structurally rather than via a separate cross-field validation check, avoiding a class of bugs where the two could drift out of sync
- GET /api/stations/options returns business_unit_id per row (not just id/name) ← needed so the FE can read/copy the selected Station's business_unit_id without a second round-trip
- Expects screen-030 (Kelola Station) to have already created minimal placeholder migrations/models for machinery_groups/machinery (for its own delete-guard) — this screen's implementation EXTENDS those, does not recreate them from scratch

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v3)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v4 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (MachineryGroupService.php, MachineryGroupController::stationOptions()). Disalin sama dengan perubahan api_contracts[1] screen-031 (kontrak ganda).
- api_contracts[0].endpoints[0].response data[0] = + business_unit_name ← toRow().
- api_contracts[0].endpoints[1].response = + label ← stationOptions().
- api_contracts[0].endpoints[2..3].group_code, business_logic[3..5], edge_case_handling[0] = case-insensitive + label/urutan ← kode.
- api_contracts[0].unit_test_cases[9] = urut label + label ← stationOptions() sortBy('label') ⚠ dulu "urut nama".
- implementation_notes (+) = REVISI audit-fix.
