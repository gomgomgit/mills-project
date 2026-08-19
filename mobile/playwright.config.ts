import { defineConfig, devices } from '@playwright/test'

/**
 * Playwright config — mobile app browser tests (test_strategy.browser_test:
 * tool=Playwright, headless=true, environment="local dev server with test
 * database"). Runs the app via the Vite dev server (same way Capacitor
 * serves it before a native build) — see the `webServer` block below.
 *
 * Screens 005-015 (module-mobile-station-ops) originally deferred browser
 * tests as "mobile-only, no server-renderable route" — that reasoning was
 * corrected: a Capacitor app is a regular SPA before native build, fully
 * reachable and testable via a browser against the dev server, exactly
 * like a web screen. See tests/e2e/*.spec.ts for the retrofitted tests.
 */
export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  // Forced to 1: the backend runs a single `php artisan serve` process
  // against a single SQLite file (see backend/.env's DB_CONNECTION), which
  // serializes writes. Multiple worker processes logging in concurrently
  // (each a real POST /api/login) caused SQLite lock contention severe
  // enough to blow past the 30s login navigation timeout across every
  // spec file at once.
  workers: 1,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: 'http://localhost:5173',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    command: 'npm run dev',
    url: 'http://localhost:5173',
    reuseExistingServer: true,
    timeout: 30_000,
  },
})
