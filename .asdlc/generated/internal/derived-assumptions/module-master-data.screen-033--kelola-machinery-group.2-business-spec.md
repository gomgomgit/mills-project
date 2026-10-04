# Derived Assumptions Log — module-master-data.screen-033--kelola-machinery-group.2-business-spec

## v1-v2 — 2026-08-19

- This screen didn't exist in the original 32-screen scope — added this round per user's explicit "full scope" choice (machinery_groups as its own entity/screen, plus insurance/tax as child-grids in Kelola Machinery)
- business_rules includes a hierarchy-consistency rule ("Station yang dipilih harus berada di bawah Business Unit yang sama") ← inferred from the ERD's denormalized business_unit_id FK on machinery_groups; without an explicit rule, a Machinery Group could claim a Business Unit inconsistent with its Station's actual parent
- test_priority = "medium" ← corrected from an initial "low" draft — 5 business rules puts it in the same complexity bucket as sibling master-data screens (027/028/029), not meaningfully simpler

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (KelolaMachinery.php, MachineryGroupService.php, kelola-machinery.blade.php).
- information_displayed[1] = + Business Unit di daftar grup ← toRow business_unit_name + blade kolom.
- information_displayed[2] = Station berlabel 'Mill — Line — Station', Production Line sebagai teks ← stationOptions() label, blade .kc-form-field__static.
- business_rules[0], edge_cases[1] = group_code unik case-insensitive ← UniqueCaseInsensitive.
- edge_cases (+1) = pesan sukses grup + feedback lama dibersihkan ← saveGroup()/deleteGroup(), clearFeedback().
