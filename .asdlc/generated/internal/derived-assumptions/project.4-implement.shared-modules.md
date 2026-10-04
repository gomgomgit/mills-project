# Derived Assumptions Log — project.4-implement.shared-modules

## v1 — 2026-08-17

- shared-modules.bootstrap_configs_bundled: config/session.php dan config/filesystems.php dibuat sekaligus meski tidak diminta sebagai modul terpisah — diperlukan agar Laravel bisa boot sama sekali dan mendukung SESSION_DRIVER/FILESYSTEM_DISK dari shared-decisions ← keputusan cakupan agent
- shared-modules.token_storage_placeholder: mobile/src/services/tokenStorage.ts saat ini pakai localStorage sebagai placeholder, BUKAN @capacitor/preferences (secure native storage) — perlu diganti sebelum build native shipping ← catatan implementasi penting, bukan keputusan final
- shared-modules.router_history_mode: mobile/src/router/index.ts pakai createWebHistory; mungkin perlu createWebHashHistory jika ada masalah deep-link di build native Capacitor (file://) ← keputusan default agent, belum divalidasi di device nyata
- shared-modules.no_explicit_cors_config: tidak ada config/cors.php eksplisit, mengandalkan default Laravel HandleCors — mungkin perlu custom origin untuk capacitor://localhost ← keputusan default agent
- shared-modules.fe_error_handler_no_ui: errorHandler.ts hanya console.log + return string, belum terhubung ke toast/snackbar UI karena belum ada UI library terpasang — wiring ke UI ditunda ke impl-2-screen ← cakupan sengaja dibatasi
- shared-modules.role_middleware_design: EnsureRole middleware dirancang sebagai alias 'role' dengan parameter role list (mis. role:admin,supervisor), diterapkan per-screen nanti di impl-2-screen — bukan mekanisme yang eksplisit diminta di shared-decisions, disimpulkan dari pola actor_permissions di semua screen tech-spec
- shared-modules.pagination_wraps_eloquent: Pagination helper membungkus Eloquent paginate() bawaan, bukan implementasi manual — keputusan implementasi agent

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (bootstrap/app.php, AppServiceProvider, ApiExceptionHandler, config/app.php, backend/.env.example, app/Support/*, app/Rules/*, mobile utils, mobile/vite.config.ts).
- env_vars_required += APP_TIMEZONE (default Asia/Jakarta), EVENT_DATE_MAX_DAYS_AHEAD (default 1) ← backend/.env.example + config/app.php
- files_generated += EnsureUserIsActive, AppServiceProvider, config/app.php, Support/{AppTime,RouteAccess,PasswordPolicy,Display,ExportValue,SheetWriter}, Rules/{RealImage,UniqueCaseInsensitive}, Livewire/Concerns/ValidatesUploadOnSelect, errors/403.blade.php, .env.e2e.example ← berkas baru lintas-layar; memasukkannya ke shared-modules (bukan per layar) adalah keputusan agen
- fe_files_generated += mobile/src/utils/localDate.ts, utils/floatingSafeArea.ts, tests/setup/teleportStub.ts ← berkas baru lintas-layar (keputusan agen)
- setup_notes += REVISI 2026-10-04 (auth-middleware EnsureUserIsActive + Sanctum nonaktif, error-handler QueryException, timezone, utilitas baru, env e2e) ← kode
- setup_notes += catatan bahwa deskripsi DB_* MySQL usang, DB aktual PostgreSQL ← arch-spec + .env.example (DB_CONNECTION=pgsql); deskripsi env var lama sengaja tidak ditulis ulang

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (EnsureUserIsActive.php, AppServiceProvider.php, Display.php, ExportValue.php, ScopesToActorMill.php, mobile apiClient.ts/auth.ts/errorHandler.ts/main.ts/utils/optionLabel.ts).
- fe_files_generated += mobile/src/utils/optionLabel.ts.
- setup_notes += REVISI 2026-10-05 (revoke sesi, label opsi, filter bukan-UUID, 401 terpusat mobile).
- ⚠ ScopesToActorMill.php tidak ditambahkan ke files_generated (tidak pernah tercantum sebelumnya; di luar modul shared).
