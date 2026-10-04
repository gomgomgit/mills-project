/**
 * SATU-SATUNYA tempat alamat aplikasi yang diuji suite ini (2026-10-04).
 *
 * Default 8001: server e2e `php artisan serve --env=e2e --port=8001`, yang
 * memakai database-nya sendiri (mill_smart_log_e2e, backend/.env.e2e).
 * Server dev :8000 memakai database dev — suite ini MENULIS ke database yang
 * dilayani servernya, jadi jangan arahkan ke sana.
 *
 * Dipakai playwright.config.ts (baseURL) DAN header Referer permintaan API
 * stateful. Sebelumnya Referer itu tertulis 'http://localhost:8000/' di enam
 * tempat: begitu server pindah port, Sanctum tidak lagi mengenali permintaan
 * sebagai stateful (pola SANCTUM_STATEFUL_DOMAINS dicocokkan dengan
 * host:port Referer) dan setiap panggilan /api/periods dijawab 401.
 */
export const APP_ORIGIN = new URL(process.env.E2E_WEB_BASE_URL ?? 'http://localhost:8001').origin

/** Referer untuk permintaan API stateful (Sanctum) — origin aplikasi + '/'. */
export const STATEFUL_REFERER = `${APP_ORIGIN}/`
