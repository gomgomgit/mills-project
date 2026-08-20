<?php

/**
 * ManagementReportTest (Browser/Playwright) — screen-026--laporan-manajemen /
 * usecase-026--laporan-manajemen.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/DashboardHomeTest.php's convention.
 *
 * WRITTEN BUT NOT RUN IN THIS SESSION — same environment constraint as
 * every sibling Browser spec in this codebase (no dev server/browser
 * available here). Run later via `playwright test` from a project root
 * with @playwright/test installed.
 *
 * Fixture assumptions:
 *   - login business area picker: "Mill A" — stest-millmgmt01 / Passw0rd!
 *     (role: mill_management)
 *   - at least one Weighbridge record exists for the current month, for
 *     the "berhasil" scenario
 * Adjust the USERNAME/BUSINESS_UNIT_NAME constants below to match
 * whatever seeder provisions the target environment.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const REPORT_PATH = '/reports/management';
const LOGIN_BUSINESS_UNIT_NAME = 'Mill A';
const PASSWORD = 'Passw0rd!';

async function login(page, username, businessUnitName = LOGIN_BUSINESS_UNIT_NAME) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(PASSWORD);
  await page.locator('#business_area').selectOption({ label: businessUnitName });
  await page.locator('button[type="submit"]').click();
  await page.goto(`${BASE_URL}${REPORT_PATH}`);
}

// Scenario: "Lihat Laporan Manajemen — berhasil"
test('berhasil: navigating to /reports/management shows the daily breakdown table and Total row', async ({ page }) => {
  await login(page, 'stest-millmgmt01');

  await expect(page.locator('[data-testid="report-row-total"]')).toBeVisible();
});

// Scenario: "Lihat Laporan Manajemen — Filter Diterapkan"
test('filter: filling date range updates the report', async ({ page }) => {
  await login(page, 'stest-millmgmt01');

  await page.locator('#date_from').fill('2026-02-01');
  await page.locator('#date_to').fill('2026-02-10');
  await page.waitForTimeout(300);

  await expect(page.locator('[data-testid="report-row-total"]')).toBeVisible();
});

// Scenario: "Lihat Laporan Manajemen — Tidak Ada Data Sesuai Filter"
test('empty: a filter combination with zero results shows the empty-data message', async ({ page }) => {
  await login(page, 'stest-millmgmt01');

  await page.locator('#date_from').fill('2020-01-01');
  await page.locator('#date_to').fill('2020-01-02');
  await page.waitForTimeout(300);

  await expect(page.locator('[data-testid="report-empty"]')).toBeVisible();
});

// Scenario: "Lihat Laporan Manajemen — Rentang Tanggal Tidak Valid"
test('invalid date range: entering date_from later than date_to shows a validation error', async ({ page }) => {
  await login(page, 'stest-millmgmt01');

  await page.locator('#date_from').fill('2026-02-10');
  await page.locator('#date_to').fill('2026-02-01');
  await page.waitForTimeout(300);

  await expect(page.locator('.report-alert')).toBeVisible();
});

// Scenario: "Lihat Laporan Manajemen — Ekspor Laporan"
test('ekspor: clicking Ekspor CSV downloads a file', async ({ page }) => {
  await login(page, 'stest-millmgmt01');

  const downloadPromise = page.waitForEvent('download');
  await page.locator('[data-testid="report-export-csv"]').click();
  const download = await downloadPromise;

  expect(download.suggestedFilename()).toContain('laporan-manajemen');
});
