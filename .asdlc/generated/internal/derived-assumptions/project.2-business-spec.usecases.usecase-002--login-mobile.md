# Derived Assumptions — project.2-business-spec.usecases.usecase-002--login-mobile

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (mobile/src/services/apiClient.ts, mobile/src/main.ts, mobile/tests/e2e/sync-and-verification.spec.ts).
- alternative_flows ← append 'Sesi Ditolak Server Saat Masih Login'.
- bdd_scenarios ← append 'Login Mobile — Sesi Ditolak Server' (diturunkan dari e2e '#3 akun dinonaktifkan' + '#3 password salah').

## v3 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit ee5294c), code is truth (backend AuthController::logout, mobile/src/stores/auth.ts, tests/e2e/logout-revokes-token.spec.ts).
- alternative_flows ← alur Logout (online cabut token perangkat ini; offline hanya sesi lokal; Keluar…; tanpa logout kedua).
- bdd_scenarios ← 2 skenario: Logout mencabut token perangkat; Logout saat offline.
- ⚠ Artefak usecase (project-level item) ikut di-patch atas permintaan brief (alternative flow/bdd logout); dep-graph tidak melacak usecase item — staleness via usecase-index ada di tangan pemanggil.
