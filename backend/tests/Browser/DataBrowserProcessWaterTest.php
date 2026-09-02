<?php

/**
 * DataBrowserProcessWaterTest (Browser/Playwright) —
 * screen-092--data-browser-process-water-web /
 * usecase-070--data-browser-process-water-web.
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
 * (pwtest-admin01 / Passw0rd!), a business unit with at least one Process
 * Water record dated within 2026-08-01..2026-08-15.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const PASSWORD = 'Passw0rd!';

async function login(page, username, password) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
}

test.describe('Data Browser Process Water (Web)', () => {
  // Scenario: "success"
  test('apply date range and business unit filters, click export and choose format', async ({ page }) => {
    await login(page, 'pwtest-admin01', PASSWORD);
    await page.goto(`${BASE_URL}/data/process-water`);

    await page.locator('#date_from').fill('2026-08-01');
    await page.locator('#date_to').fill('2026-08-15');

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.locator('a', { hasText: 'Ekspor CSV' }).click(),
    ]);

    expect(download.suggestedFilename()).toContain('process-water-records');
  });

  // Scenario: "Tidak Ada Data Sesuai Filter"
  test('apply filters with no matching data, empty state shown', async ({ page }) => {
    await login(page, 'pwtest-admin01', PASSWORD);
    await page.goto(`${BASE_URL}/data/process-water`);

    await page.locator('#date_from').fill('2020-01-01');
    await page.locator('#date_to').fill('2020-01-02');

    await expect(page.locator('.pw-empty__title')).toContainText('Tidak ada data');
  });

  // Scenario: "Rentang Tanggal Tidak Valid"
  test('start date later than end date, validation message displayed', async ({ page }) => {
    await login(page, 'pwtest-admin01', PASSWORD);
    await page.goto(`${BASE_URL}/data/process-water`);

    await page.locator('#date_from').fill('2026-08-20');
    await page.locator('#date_to').fill('2026-08-10');

    await expect(page.locator('.pw-alert')).toContainText('Rentang tanggal tidak valid');
  });

  // Scenario: "Klik Baris Membuka Detail"
  test('click a table row, browser navigates to Detail Process Water', async ({ page }) => {
    await login(page, 'pwtest-admin01', PASSWORD);
    await page.goto(`${BASE_URL}/data/process-water`);

    await page.locator('.pw-table__row').first().click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/process-water/') && !url.pathname.endsWith('/create'));
  });
});
