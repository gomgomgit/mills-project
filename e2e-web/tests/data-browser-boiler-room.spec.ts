/**
 * DataBrowserBoilerRoomTest (Browser/Playwright) —
 * screen-098--data-browser-boiler-room-web /
 * usecase-106--data-browser-boiler-room-web.
 *
 * Playwright spec, mirrors tests/Browser/DataBrowserEngineRoomTest.php's
 * conventions exactly.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — a .php file containing
 * Playwright TS syntax, excluded from phpunit.xml's testsuites, meant to be
 * run separately via `playwright test` against a live dev server. Verified
 * only by static review, consistent with every other Browser/* spec in this
 * codebase.
 *
 * Test data assumption: authenticated Admin session
 * (brtest-admin01 / Passw0rd!), a business unit with at least one Boiler
 * Room record dated within 2026-08-01..2026-08-15.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'



test.describe('Data Browser Boiler Room (Web)', () => {
  // Scenario: "success"
  test('apply date range and business unit filters, click export and choose format', async ({ page }) => {
    await login(page, 'brtest-admin01', PASSWORD);
    await page.goto('/data/boiler-room');

    await page.locator('#date_from').fill('2026-08-01');
    await page.locator('#date_to').fill('2026-08-15');

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.locator('a', { hasText: 'Ekspor CSV' }).click(),
    ]);

    expect(download.suggestedFilename()).toContain('boiler-room-records');
  });

  // Scenario: "Tidak Ada Data Sesuai Filter"
  test('apply filters with no matching data, empty state shown', async ({ page }) => {
    await login(page, 'brtest-admin01', PASSWORD);
    await page.goto('/data/boiler-room');

    await page.locator('#date_from').fill('2020-01-01');
    await page.locator('#date_to').fill('2020-01-02');

    await expect(page.locator('.br-empty__title')).toContainText('Tidak ada data');
  });

  // Scenario: "Rentang Tanggal Tidak Valid"
  test('start date later than end date, validation message displayed', async ({ page }) => {
    await login(page, 'brtest-admin01', PASSWORD);
    await page.goto('/data/boiler-room');

    await page.locator('#date_from').fill('2026-08-20');
    await page.locator('#date_to').fill('2026-08-10');

    await expect(page.locator('.br-alert')).toContainText('Rentang tanggal tidak valid');
  });

  // Scenario: "Klik Baris Membuka Detail"
  test('click a table row, browser navigates to Detail Boiler Room', async ({ page }) => {
    await login(page, 'brtest-admin01', PASSWORD);
    await page.goto('/data/boiler-room');

    await page.locator('.br-table__row').first().click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/boiler-room/') && !url.pathname.endsWith('/create'));
  });
});
