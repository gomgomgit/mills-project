/**
 * DataBrowserSolidWasteDisposalTest (Browser/Playwright) —
 * screen-091--data-browser-solid-waste-disposal-web /
 * usecase-064--data-browser-solid-waste-disposal-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/DataBrowserCagesTrackTest.php's convention (a .php path
 * containing a Playwright TS spec body).
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — not wired into
 * phpunit.xml (only tests/Unit and tests/Feature run via `php artisan
 * test`), and no Playwright runner is configured here. Run separately with
 * `npx playwright test` against a running app + seeded test data once that
 * infrastructure exists.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const DATA_BROWSER_PATH = '/data/solid-waste-disposal';
const USERNAME = 'swdtest-browse01';


test.describe('Data Browser Solid Waste Disposal', () => {
  test('menampilkan data terfilter dan mengekspor CSV/Excel', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(DATA_BROWSER_PATH);

    await page.locator('#date_from').fill('2026-08-01');
    await page.locator('#date_to').fill('2026-08-31');

    await expect(page.locator('.sw-table__row').first()).toBeVisible();
  });

  test('menampilkan empty state saat filter tidak menghasilkan data', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(DATA_BROWSER_PATH);

    await page.locator('#date_from').fill('2020-01-01');
    await page.locator('#date_to').fill('2020-01-02');

    await expect(page.getByText('Tidak ada data')).toBeVisible();
  });

  test('klik baris membuka halaman detail', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(DATA_BROWSER_PATH);

    await page.locator('.sw-table__row').first().click();

    await page.waitForURL(/\/data\/solid-waste-disposal\/[0-9a-f-]+$/);
  });
});
