# Derived Assumptions Log — module-mobile-station-ops.screen-008--monitor-grading.2-business-spec

## v1 — 2026-08-14

- business_rules = ["Pause tersedia untuk 3 stasiun MVP", "Multiple draft bisa berjalan bersamaan", "Clear menghapus tanpa undo, perlu konfirmasi"] ← proposed by agent in draft (mirrored from Monitor Weighbridge pattern), accepted without correction
- edge_cases = ["Clear tanpa dialog konfirmasi", "Pause ditekan tanpa draft ongoing"] ← proposed by agent in draft, accepted without correction

## v2 — 2026-08-18

- Full revision to mirror Monitor Weighbridge v4 pattern (counter row + draft/pause list + New Data/Load Data), per user instruction "hal yang sama seperti weighbridge untuk halaman monitor" ← direct instruction, structural mirroring is agent's own translation to Grading terminology
- Counter unit label "Jumlah Quantity (bunch)" (not "(kg)" like Weighbridge's quantity counter) ← Grading's `quantity` field is inherently bunch-denominated per entity-catalog v2 (distinct from `netto` which is kg), not a literal 1:1 mirror of Weighbridge's card labels — domain-appropriate unit choice, not user-stated
- entry_points reduced to single entry ("Tap Grading di Station List"), old "Tap draft Grading yang di-pause dari Home" entry point REMOVED ← Home (screen-005) no longer surfaces any draft/status info at all (removed entirely in an earlier revision this session), so that entry point is now factually impossible
- Pause/Clear moved off Monitor onto Form Grading (mirrors Weighbridge's same restructure) ← consistent structural mirroring of the confirmed Weighbridge pattern, not independently re-confirmed with user for Grading specifically
