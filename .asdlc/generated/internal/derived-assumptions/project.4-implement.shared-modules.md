# Derived Assumptions Log — project.4-implement.shared-modules

## v1 — 2026-08-17

- shared-modules.bootstrap_configs_bundled: config/session.php dan config/filesystems.php dibuat sekaligus meski tidak diminta sebagai modul terpisah — diperlukan agar Laravel bisa boot sama sekali dan mendukung SESSION_DRIVER/FILESYSTEM_DISK dari shared-decisions ← keputusan cakupan agent
- shared-modules.token_storage_placeholder: mobile/src/services/tokenStorage.ts saat ini pakai localStorage sebagai placeholder, BUKAN @capacitor/preferences (secure native storage) — perlu diganti sebelum build native shipping ← catatan implementasi penting, bukan keputusan final
- shared-modules.router_history_mode: mobile/src/router/index.ts pakai createWebHistory; mungkin perlu createWebHashHistory jika ada masalah deep-link di build native Capacitor (file://) ← keputusan default agent, belum divalidasi di device nyata
- shared-modules.no_explicit_cors_config: tidak ada config/cors.php eksplisit, mengandalkan default Laravel HandleCors — mungkin perlu custom origin untuk capacitor://localhost ← keputusan default agent
- shared-modules.fe_error_handler_no_ui: errorHandler.ts hanya console.log + return string, belum terhubung ke toast/snackbar UI karena belum ada UI library terpasang — wiring ke UI ditunda ke impl-2-screen ← cakupan sengaja dibatasi
- shared-modules.role_middleware_design: EnsureRole middleware dirancang sebagai alias 'role' dengan parameter role list (mis. role:admin,supervisor), diterapkan per-screen nanti di impl-2-screen — bukan mekanisme yang eksplisit diminta di shared-decisions, disimpulkan dari pola actor_permissions di semua screen tech-spec
- shared-modules.pagination_wraps_eloquent: Pagination helper membungkus Eloquent paginate() bawaan, bukan implementasi manual — keputusan implementasi agent
