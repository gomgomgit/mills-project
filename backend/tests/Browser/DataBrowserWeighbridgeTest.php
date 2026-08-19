/**
 * DataBrowserWeighbridgeTest (Browser/Playwright) —
 * screen-016--data-browser-weighbridge-web / usecase-016--data-browser-
 * weighbridge-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * the convention established by tests/Browser/ChangePasswordWebTest.php
 * and tests/Browser/LoginWebTest.php — same file-extension/import-syntax
 * pattern (a .php path containing a Playwright TS spec body), since
 * test_strategy.browser_test.tool is Playwright (not Laravel Dusk — this
 * codebase has no laravel/dusk dependency, see composer.json, and every
 * prior screen's Browser suite already uses this Playwright-in-.php
 * convention; this file follows the existing precedent rather than
 * introducing a second, inconsistent browser-test framework).
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT: there is no dev server or
 * browser available in this sandbox (test_strategy.browser_test: tool=
 * Playwright, base_url=http://localhost:8000, start_command=
 * "php artisan serve"). This file is written to be complete and correct,
 * to be run later via `playwright test` per
 * test_strategy.browser_test.run_command, from a project root with
 * @playwright/test installed and a playwright.config.* pointing at this
 * file (e.g. testDir including backend/tests/Browser). See this screen's
 * known_issues for the setup this requires.
 *
 * Covers scenario 1 ("Telusuri & Ekspor Data Weighbridge — berhasil") end-
 * to-end through the UI, per this screen's browser_test spec: apply a
 * filter, see the table update, click export, verify the download starts.
 *
 * Test data assumption (mirrors ChangePasswordWebTest.php's approach):
 * this screen requires an authenticated session with role supervisor,
 * mill_management, or admin, so the scenario logs in first via /login,
 * then navigates to /data/weighbridge.
 *   - business unit: "Mill A" (any business_unit whose name is selectable
 *     in the login form's Business Area <select>)
 *   - wbtest-browse01 / Passw0rd! (role: supervisor)
 * Adjust the USERNAME/BUSINESS_UNIT_NAME/PASSWORD constants below to match
 * whatever seeder is used to provision the target environment. At least
 * one WeighbridgeRecord for that business unit, dated within the filter
 * range used below (2026-01-01..2026-12-31), must exist in the seeded
 * environment for the "table update" assertion to have a row to show.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const DATA_BROWSER_PATH = '/data/weighbridge';
const BUSINESS_UNIT_NAME = 'Mill A';
const USERNAME = 'wbtest-browse01';
const PASSWORD = 'Passw0rd!';

async function login(page, username, password) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.locator('#business_unit_id').selectOption({ label: BUSINESS_UNIT_NAME });
  await page.locator('button[type="submit"]').click();
  // Redirected away from /login once the session is established.
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
}

test.describe('Data Browser Weighbridge', () => {
  // Scenario: "Telusuri & Ekspor Data Weighbridge — berhasil"
  test('menerapkan filter, tabel diperbarui, dan ekspor CSV memicu unduhan', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}${DATA_BROWSER_PATH}`);

    // Apply the date-range filter.
    await page.locator('#date_from').fill('2026-01-01');
    await page.locator('#date_to').fill('2026-12-31');

    // The table updates in place (Livewire wire:model.live), no full page
    // navigation — wait for the loading class to clear before asserting.
    await expect(page.locator('.wb-browser')).not.toHaveClass(/wb-browser--busy/);

    // At least one row is now visible (fixture data assumption above), or
    // the empty state message ("Tidak ada data") is decidedly absent for
    // this scenario — this scenario expects data to exist.
    await expect(page.locator('.wb-table__row').first()).toBeVisible();
    await expect(page.locator('.wb-empty')).toHaveCount(0);

    // Click "Ekspor CSV" and verify the browser starts a file download.
    // The export link is a plain <a href target="_blank"> (not an
    // AJAX/blob call — see App\Livewire\Data\DataBrowserWeighbridge's
    // exportUrl() docblock), so Playwright's download event fires on the
    // resulting navigation.
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.locator('.wb-browser__export a', { hasText: 'Ekspor CSV' }).click(),
    ]);

    expect(download.suggestedFilename()).toMatch(/^weighbridge-records_.*\.csv$/);
  });
});
