/**
 * DataBrowserEffluentPlantTest (Browser/Playwright) —
 * screen-095--data-browser-effluent-plant-web /
 * usecase-088--data-browser-effluent-plant-web.
 *
 * Playwright spec, mirrors tests/Browser/DataBrowserThreshingTest.php's
 * conventions exactly.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — a .php file containing
 * Playwright TS syntax, excluded from phpunit.xml's testsuites, meant to be
 * run separately via `playwright test` against a live dev server. Verified
 * only by static review, consistent with every other Browser/* spec in this
 * codebase.
 *
 * Test data assumption: authenticated Admin session
 * (eptest-admin01 / Passw0rd!), a business unit with at least one Process
 * Water record dated within 2026-08-01..2026-08-15.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { assertProductionLineFilter } from './support/production-line-filter'



test.describe('Data Browser Effluent Plant (Web)', () => {
  // Scenario: "success"
  test('apply date range and business unit filters, click export and choose format', async ({ page }) => {
    await login(page, 'eptest-admin01', PASSWORD);
    await page.goto('/data/effluent-plant');

    await page.locator('#date_from').fill('2026-08-01');
    await page.locator('#date_to').fill('2026-08-15');

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.locator('a', { hasText: 'Ekspor CSV' }).click(),
    ]);

    expect(download.suggestedFilename()).toContain('effluent-plant-records');
  });

  // Scenario: "Tidak Ada Data Sesuai Filter"
  test('apply filters with no matching data, empty state shown', async ({ page }) => {
    await login(page, 'eptest-admin01', PASSWORD);
    await page.goto('/data/effluent-plant');

    await page.locator('#date_from').fill('2020-01-01');
    await page.locator('#date_to').fill('2020-01-02');

    await expect(page.locator('.ep-empty__title')).toContainText('Tidak ada data');
  });

  // Scenario: "Rentang Tanggal Tidak Valid"
  test('start date later than end date, validation message displayed', async ({ page }) => {
    await login(page, 'eptest-admin01', PASSWORD);
    await page.goto('/data/effluent-plant');

    await page.locator('#date_from').fill('2026-08-20');
    await page.locator('#date_to').fill('2026-08-10');

    await expect(page.locator('.ep-alert')).toContainText('Rentang tanggal tidak valid');
  });

  // Scenario: "Klik Baris Membuka Detail"
  test('click a table row, browser navigates to Detail Effluent Plant', async ({ page }) => {
    await login(page, 'eptest-admin01', PASSWORD);
    await page.goto('/data/effluent-plant');

    await page.locator('.ep-table__row').first().click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/effluent-plant/') && !url.pathname.endsWith('/create'));
  });

  // Scenario: filter Production Line — record milik dua line di mill yang
  // sama (fixture EP-BROWSER-PL-A / -PL-B, BrowserTestFixtureSeeder::
  // productionLineFilterFixtures). "Semua Line" memuat keduanya dengan
  // Production Line sebagai kolom pertama; memilih satu line menyempitkan
  // tabel ke record line itu saja; tautan ekspor (dan CSV-nya) membawa
  // production_line_id. Langkahnya di tests/support/production-line-filter.ts.
  test('filter production line menyempitkan tabel dan terbawa ke ekspor', async ({ page }) => {
    await assertProductionLineFilter(page, {
      username: 'eptest-admin01',
      path: '/data/effluent-plant',
      cls: 'ep',
      idPrefix: 'EP',
    });
  });
});
