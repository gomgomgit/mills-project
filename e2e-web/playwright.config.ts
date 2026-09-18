import { defineConfig, devices } from '@playwright/test'

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
 * No `webServer` block: the Laravel app needs a database seeded with this
 * suite's fixtures (php artisan db:seed --class=BrowserTestFixtureSeeder),
 * so starting it blindly from here would run tests against whatever state
 * the developer's database happened to be in. Start the app yourself, seed,
 * then run.
 */
export default defineConfig({
  testDir: './tests',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: process.env.E2E_WEB_BASE_URL ?? 'http://localhost:8000',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
})
