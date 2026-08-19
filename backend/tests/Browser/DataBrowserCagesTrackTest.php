<?php

/**
 * DataBrowserCagesTrackTest (Browser/Playwright) —
 * screen-018--data-browser-cages-track-web / usecase-018--data-browser-
 * cages-track-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/DataBrowserGradingTest.php's convention exactly (same
 * file-extension/import-syntax pattern — a .php path containing a
 * Playwright TS spec body), since test_strategy.browser_test.tool is
 * Playwright (not Laravel Dusk — this codebase has no laravel/dusk
 * dependency, see composer.json).
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT: there is no dev server or
 * browser available in this sandbox (test_strategy.browser_test: tool=
 * Playwright, base_url=http://localhost:8000, start_command=
 * "php artisan serve"). This file is written to be complete and correct,
 * to be run later via `playwright test` per
 * test_strategy.browser_test.run_command, from a project root with
 * @playwright/test installed and a playwright.config.* pointing at this
 * file (e.g. testDir including backend/tests/Browser). See this screen's
 * known_issues for the setup this requires — this is informational, not an
 * error: test-runner-agent is expected to skip this file (mirrors
 * screen-016/017's precedent).
 *
 * Covers scenario 1 ("Telusuri & Ekspor Data Cages Track — success") end-
 * to-end through the UI, per this screen's browser_test spec: apply a
 * filter, see the table update, click export, verify the download starts.
 *
 * Test data assumption (mirrors DataBrowserGradingTest.php's approach):
 * this screen requires an authenticated session with role supervisor,
 * mill_management, or admin, so the scenario logs in first via /login,
 * then navigates to /data/cages-track.
 *   - business unit: "Mill A" (any business_unit whose name is selectable
 *     in the login form's Business Area <select>)
 *   - cttest-browse01 / Passw0rd! (role: supervisor)
 * Adjust the USERNAME/BUSINESS_UNIT_NAME/PASSWORD constants below to match
 * whatever seeder is used to provision the target environment. At least
 * one CagesTrackRecord for that business unit, dated within the filter
 * range used below (2026-01-01..2026-12-31), must exist in the seeded
 * environment for the "table update" assertion to have a row to show.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const DATA_BROWSER_PATH = '/data/cages-track';
const BUSINESS_UNIT_NAME = 'Mill A';
const USERNAME = 'cttest-browse01';
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

test.describe('Data Browser Cages Track', () => {
  // Scenario: "Telusuri & Ekspor Data Cages Track — success"
  test('menerapkan filter, tabel diperbarui, dan ekspor CSV memicu unduhan', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}${DATA_BROWSER_PATH}`);

    // Apply the date-range filter.
    await page.locator('#date_from').fill('2026-01-01');
    await page.locator('#date_to').fill('2026-12-31');

    // The table updates in place (Livewire wire:model.live), no full page
    // navigation — wait for the loading class to clear before asserting.
    await expect(page.locator('.ct-browser')).not.toHaveClass(/ct-browser--busy/);

    // At least one row is now visible (fixture data assumption above), or
    // the empty state message ("Tidak ada data") is decidedly absent for
    // this scenario — this scenario expects data to exist.
    await expect(page.locator('.ct-table__row').first()).toBeVisible();
    await expect(page.locator('.ct-empty')).toHaveCount(0);

    // Click "Ekspor CSV" and verify the browser starts a file download.
    // The export link is a plain <a href target="_blank"> (not an
    // AJAX/blob call — see App\Livewire\Data\DataBrowserCagesTrack's
    // exportUrl() docblock), so Playwright's download event fires on the
    // resulting navigation.
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.locator('.ct-browser__export a', { hasText: 'Ekspor CSV' }).click(),
    ]);

    expect(download.suggestedFilename()).toMatch(/^cages-track-records_.*\.csv$/);
  });

  // Scenario: "Telusuri & Ekspor Data Cages Track — Tidak Ada Data Sesuai
  // Filter"
  test('filter tanpa data cocok menampilkan pesan tidak ada data', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}${DATA_BROWSER_PATH}`);

    // A range far enough in the past that no seeded fixture data matches.
    await page.locator('#date_from').fill('2000-01-01');
    await page.locator('#date_to').fill('2000-01-02');

    await expect(page.locator('.ct-browser')).not.toHaveClass(/ct-browser--busy/);
    await expect(page.locator('.ct-empty__title')).toHaveText('Tidak ada data');
    // Known gap (see tests/Feature/Livewire/DataBrowserCagesTrackTest.php's
    // file-level docblock): the tech-spec assert also mentions a visible
    // reset-filter button, which the current Blade view does not render —
    // not asserted here.
  });

  // Scenario: "Telusuri & Ekspor Data Cages Track — Rentang Tanggal Tidak
  // Valid"
  test('rentang tanggal tidak valid menampilkan pesan validasi', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}${DATA_BROWSER_PATH}`);

    // date_from later than date_to.
    await page.locator('#date_from').fill('2026-08-20');
    await page.locator('#date_to').fill('2026-08-10');

    await expect(page.locator('.ct-browser')).not.toHaveClass(/ct-browser--busy/);
    await expect(page.locator('.ct-alert')).toContainText('Rentang tanggal tidak valid');
  });

  // Scenario: "Telusuri & Ekspor Data Cages Track — Klik Baris Membuka
  // Detail" — screen-021 (Detail Cages Track, Web) does not exist yet, so
  // this scenario cannot be exercised end-to-end (no route to navigate
  // to). Skipped until screen-021 is implemented, mirroring screen-017's
  // equivalent gap (see tests/Feature/Livewire/DataBrowserCagesTrackTest.
  // php's docblock for the same reasoning at the component-test level).
  test.skip('klik baris membuka detail cages track — menunggu screen-021 (Detail Cages Track, Web)', async ({ page }) => {
    // Intentionally left unimplemented: no `data.cages-track.detail` route
    // exists yet for Playwright to navigate to and assert against.
  });

  // Scenario: "Telusuri & Ekspor Data Cages Track — Ekspor Gagal"
  test.skip('ekspor gagal menampilkan pesan error — memerlukan dataset > EXPORT_ROW_LIMIT', async ({ page }) => {
    // Intentionally left unimplemented in this environment: reliably
    // reproducing a dataset that exceeds CagesTrackRecordService::
    // EXPORT_ROW_LIMIT (50,000 rows) requires a dedicated seeded
    // environment/fixture, out of scope for a UI-level smoke spec. Covered
    // end-to-end at the HTTP layer instead by tests/Feature/Api/
    // DataBrowserCagesTrackTest.php's "Ekspor Gagal" test, which bulk-
    // inserts EXPORT_ROW_LIMIT + 1 rows and asserts the 422 EXPORT_FAILED
    // response directly.
  });
});
