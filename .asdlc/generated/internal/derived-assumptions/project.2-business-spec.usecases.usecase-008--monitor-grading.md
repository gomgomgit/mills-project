# Derived Assumptions Log — project.2-business-spec.usecases.usecase-008--monitor-grading

## v2 — 2026-08-18

- Full revision mirroring usecase-007--monitor-weighbridge v3's same restructure (Pause/Clear moved off Monitor onto Form) ← structural mirroring of confirmed Weighbridge pattern per user instruction "hal yang sama seperti weighbridge"
- bdd-spec-writer-agent flagged 4 existing scenarios ("Pause Progress", "Clear Draft", "Pause Ditekan Tanpa Draft Ongoing", "Clear dibatalkan tanpa konfirmasi") as now factually contradictory to the updated main_flow/alternative_flows (which no longer contain any Pause/Clear behavior on this screen) but could not remove them itself (agent has no delete authority under "never remove" merge policy) ← command-level override: these 4 were DELETED from bdd_scenarios rather than kept, since keeping factually-wrong scenarios would be worse than removing them; their content will be properly re-derived on usecase-011--form-grading instead, where Pause/Clear now actually live. Same override judgment call already made 3 times earlier this session for the analogous Weighbridge screens.
- 5 new scenarios added (Load Data, Tap Breadcrumb, Buka Menu Hamburger, Belum Ada Draft, Belum Ada Data Hari Ini) ← derived fresh from updated main_flow/alternative_flows, none existed before
