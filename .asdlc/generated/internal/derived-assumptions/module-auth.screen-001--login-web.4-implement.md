# Derived Assumptions Log — module-auth.screen-001--login-web.4-implement

## v1 — 2026-08-17

- redirect_to per role (ROLE_REDIRECTS map) ← tidak didokumentasikan eksplisit di tech spec, agent memilih path konkret per role
- Business-area access dicek via exact match User.business_unit_id ← tidak ada tabel M2M akses di entity-catalog, agent memilih interpretasi paling sederhana
- 3 exception class baru (InvalidCredentialsException, AccountInactiveException, BusinessAreaMismatchException) ← ErrorCodes shared-module hanya punya kategori generik, agent menambah exception spesifik
- ApiExceptionHandler tidak mengeluarkan field error_code machine-readable ← gap antara kontrak tech-spec (INVALID_CREDENTIALS dll) dan implementasi shared error-handler; test hanya menguji status+teks pesan, bukan error_code — perlu diperbaiki di iterasi shared-modules berikutnya
- Design token di-inline sebagai CSS biasa (bukan lewat pipeline Vite/Tailwind) ← belum ada asset pipeline di scaffold backend-only
- POST /api/login diberi middleware('web') eksplisit (bukan hanya EnsureFrontendRequestsAreStateful) agar session selalu dibuat sesuai business_logic step 6 ← keputusan fix implementasi, konsekuensi arsitektur untuk usecase-002 (mobile) perlu ditinjau ulang nanti
- AuthServiceTest pakai RefreshDatabase+sqlite in-memory+factories, bukan true mock ← deviasi pragmatis dari test_strategy.unit_test.mock_policy karena AuthService pakai Eloquent langsung
