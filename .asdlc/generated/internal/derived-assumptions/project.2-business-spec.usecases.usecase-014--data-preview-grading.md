# Derived Assumptions Log — project.2-business-spec.usecases.usecase-014--data-preview-grading

## v2 — 2026-08-19

- Full restructure mirroring usecase-013--data-preview-weighbridge v3's dual-mode (list+filter+detail) shape ← direct user instruction ("hal yang sama seperti weighbridge untuk halaman monitor dan load data")
- bdd-spec-writer-agent flagged the old "success" scenario as superseded — its Back-navigation outcome (→ Monitor Grading) directly contradicts the new "Back dari Mode Detail" behavior (→ List), and its flow (open detail directly, no list) no longer matches reality ← command-level override: old "success" DELETED, replaced by the new list→detail "success" scenario. Same override judgment call made repeatedly this session.
- Old "Record Tidak Ditemukan" scenario KEPT UNCHANGED — its meaning still holds under the new dual-mode structure (agent's own assessment, not contradictory), so no duplicate was added
- 8 further new scenarios added (Tap Item Draft/Pause, Filter Tanggal Default Hari Ini, Filter Diterapkan, Filter Tidak Menghasilkan Apapun, List Kosong, Tap Breadcrumb, Buka Menu Hamburger, Back dari Mode Detail) ← derived fresh from the updated main_flow/alternative_flows
