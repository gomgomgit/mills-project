/**
 * DataBrowserEngineRoomTest (Browser/Playwright) —
 * screen-097--data-browser-engine-room-web /
 * usecase-100--data-browser-engine-room-web.
 *
 * Playwright spec, mirrors tests/Browser/DataBrowserStorageTankTest.php's
 * conventions exactly.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — a .php file containing
 * Playwright TS syntax, excluded from phpunit.xml's testsuites, meant to be
 * run separately via `playwright test` against a live dev server. Verified
 * only by static review, consistent with every other Browser/* spec in this
 * codebase.
 *
 * Test data assumption: authenticated Admin session
 * (ertest-admin01 / Passw0rd!), a business unit with at least one Engine
 * Room record dated within 2026-08-01..2026-08-15.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'



test.describe('Data Browser Engine Room (Web)', () => {
  // Scenario: "success"
  test('apply date range and business unit filters, click export and choose format', async ({ page }) => {
    await login(page, 'ertest-admin01', PASSWORD);
    await page.goto('/data/engine-room');

    await page.locator('#date_from').fill('2026-08-01');
    await page.locator('#date_to').fill('2026-08-15');

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.locator('a', { hasText: 'Ekspor CSV' }).click(),
    ]);

    expect(download.suggestedFilename()).toContain('engine-room-records');
  });

  // Scenario: "Tidak Ada Data Sesuai Filter"
  test('apply filters with no matching data, empty state shown', async ({ page }) => {
    await login(page, 'ertest-admin01', PASSWORD);
    await page.goto('/data/engine-room');

    await page.locator('#date_from').fill('2020-01-01');
    await page.locator('#date_to').fill('2020-01-02');

    await expect(page.locator('.er-empty__title')).toContainText('Tidak ada data');
  });

  // Scenario: "Rentang Tanggal Tidak Valid"
  test('start date later than end date, validation message displayed', async ({ page }) => {
    await login(page, 'ertest-admin01', PASSWORD);
    await page.goto('/data/engine-room');

    await page.locator('#date_from').fill('2026-08-20');
    await page.locator('#date_to').fill('2026-08-10');

    await expect(page.locator('.er-alert')).toContainText('Rentang tanggal tidak valid');
  });

  // Scenario: "Klik Baris Membuka Detail"
  test('click a table row, browser navigates to Detail Engine Room', async ({ page }) => {
    await login(page, 'ertest-admin01', PASSWORD);
    await page.goto('/data/engine-room');

    await page.locator('.er-table__row').first().click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/engine-room/') && !url.pathname.endsWith('/create'));
  });
});
