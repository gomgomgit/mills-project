import { defineConfig, devices } from '@playwright/test'
import { APP_ORIGIN } from './tests/support/base-url'

/**
 * Browser tests for the Laravel/Livewire WEB app (screens 001, 003,
 * 016-035, 049-060, 091-127).
 *
 * Why this lives at the repo root rather than inside backend/ or mobile/:
 *   - backend/ has no JS toolchain at all (no package.json), so Playwright
 *     has nowhere to live there.
 *   - mobile/ already runs Playwright, but against the Vite dev server for
 *     the mobile app. Its baseURL, webServer and fixtures are a different
 *     target entirely; sharing one config would mean one suite silently
 *     pointing at the wrong app.
 *
 * These specs were converted from backend/tests/Browser/*.php on
 * 2026-09-16. Those files held Playwright JavaScript behind a `<?php` tag
 * and a PHP docblock — 63 of the 68 were not even parseable as PHP — so
 * they were never registered in phpunit.xml and had never run once, while
 * test-strategy's done_definition still required "All single-screen browser
 * tests pass" for every screen.
 *
 * BASE_URL is an env var, not a constant, because the dev server port has
 * already moved once (8000 -> 8001 when another service took 8000); the
 * original .php files hardcoded 8000 and would now all fail at the first
 * navigation.
 *
 * No `webServer` block: the Laravel app needs ITS OWN database, reset and
 * seeded with this suite's fixtures (e2e-web/scripts/prepare-db.sh →
 * mill_smart_log_e2e, via backend/.env.e2e). Start it yourself with
 * `php artisan serve --env=e2e --port=8001` after preparing the DB — see
 * README.md "Running them". Since 2026-10-04 the suite never touches the
 * dev database mill_smart_log.
 */
export default defineConfig({
  testDir: './tests',
  // Menghapus record stasiun yang ditanam suite ini. Tanpa ini residunya
  // tidak pernah menyusut: aplikasi sengaja tidak punya jalur hapus untuk
  // record stasiun, jadi setiap run menumpuk selamanya (841 baris saat
  // teardown ini ditulis). Alasan lengkap di berkas yang ditunjuk.
  // Penjaga "server ini server e2e" + memulihkan fixture setiap run — lihat
  // berkasnya.
  globalSetup: './tests/support/global-setup.ts',
  globalTeardown: './tests/support/global-teardown.ts',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  use: {
    // 8001 = server e2e (`php artisan serve --env=e2e --port=8001`), yang
    // memakai database-nya sendiri (mill_smart_log_e2e). JANGAN arahkan ke
    // server dev :8000 — suite ini menulis ke database yang dilayaninya.
    baseURL: APP_ORIGIN,
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
})
