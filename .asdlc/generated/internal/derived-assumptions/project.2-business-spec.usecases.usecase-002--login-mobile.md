# Derived Assumptions — project.2-business-spec.usecases.usecase-002--login-mobile

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (mobile/src/services/apiClient.ts, mobile/src/main.ts, mobile/tests/e2e/sync-and-verification.spec.ts).
- alternative_flows ← append 'Sesi Ditolak Server Saat Masih Login'.
- bdd_scenarios ← append 'Login Mobile — Sesi Ditolak Server' (diturunkan dari e2e '#3 akun dinonaktifkan' + '#3 password salah').
