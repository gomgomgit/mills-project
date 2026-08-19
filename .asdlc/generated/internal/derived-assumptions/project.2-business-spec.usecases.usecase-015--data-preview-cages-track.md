# Derived Assumptions Log — project.2-business-spec.usecases.usecase-015--data-preview-cages-track

## v2 — 2026-08-19

- Full restructure mirroring usecase-013/014's dual-mode (list+filter+detail) shape ← direct user instruction, structural mirroring
- bdd-spec-writer-agent flagged the old "success" scenario as superseded — its Back-navigation outcome (→ Monitor Cages Track) directly contradicts the new "Back dari Mode Detail" behavior (→ List) ← command-level override: old "success" DELETED, replaced by the new list→detail "success" scenario. Same override judgment call made repeatedly this session.
- Old "Record Tidak Ditemukan" scenario KEPT UNCHANGED — still valid under the new dual-mode structure
- 8 further new scenarios added (Tap Item Draft/Pause, Filter Tanggal Default Hari Ini, Filter Diterapkan, Filter Tidak Menghasilkan Apapun, List Kosong, Tap Breadcrumb, Buka Menu Hamburger, Back dari Mode Detail)
