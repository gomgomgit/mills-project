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

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const REPORT_PATH = '/reports/management';

// SEJAK 2026-10-03 setiap skenario MEMBUKA REPORT_PATH sesudah login. Spec ini
// menganggap login mendarat di laporan, padahal login mendarat di /dashboard —
// sehingga kelima skenario menunggu elemen laporan di halaman yang salah.
//
// Skenario "filter" memakai 2026-08-01..2026-08-10 (bukan Februari 2026): rentang
// itu memuat record Weighbridge fixture WB-BROWSER-EDIT (2026-08-05) dari
// BrowserTestFixtureSeeder, sedangkan Februari 2026 tidak memuat data apa pun
// sehingga baris Total memang tidak dirender.

// Scenario: "Lihat Laporan Manajemen — berhasil"
test('berhasil: navigating to /reports/management shows the daily breakdown table and Total row', async ({ page }) => {
  await login(page, 'stest-millmgmt01');
  await page.goto(REPORT_PATH);

  await expect(page.locator('[data-testid="report-row-total"]')).toBeVisible();
});

// Scenario: "Lihat Laporan Manajemen — Filter Diterapkan"
test('filter: filling date range updates the report', async ({ page }) => {
  await login(page, 'stest-millmgmt01');
  await page.goto(REPORT_PATH);

  await page.locator('#date_from').fill('2026-08-01');
  await page.locator('#date_to').fill('2026-08-10');
  await page.waitForTimeout(300);

  await expect(page.locator('[data-testid="report-row-total"]')).toBeVisible();
});

// Scenario: "Lihat Laporan Manajemen — Tidak Ada Data Sesuai Filter"
test('empty: a filter combination with zero results shows the empty-data message', async ({ page }) => {
  await login(page, 'stest-millmgmt01');
  await page.goto(REPORT_PATH);

  // 2000, bukan 2020-01-01: tiga skenario form-* "Tanggal Dapat Diedit Manual"
  // menyimpan record bertanggal 2020-01-01, jadi rentang itu TIDAK kosong.
  await page.locator('#date_from').fill('2000-01-01');
  await page.locator('#date_to').fill('2000-01-02');
  await page.waitForTimeout(300);

  await expect(page.locator('[data-testid="report-empty"]')).toBeVisible();
});

// Scenario: "Lihat Laporan Manajemen — Rentang Tanggal Tidak Valid"
test('invalid date range: entering date_from later than date_to shows a validation error', async ({ page }) => {
  await login(page, 'stest-millmgmt01');
  await page.goto(REPORT_PATH);

  await page.locator('#date_from').fill('2026-02-10');
  await page.locator('#date_to').fill('2026-02-01');
  await page.waitForTimeout(300);

  await expect(page.locator('.report-alert')).toBeVisible();
});

// Scenario: "Lihat Laporan Manajemen — Ekspor Laporan"
test('ekspor: clicking Ekspor CSV downloads a file', async ({ page }) => {
  await login(page, 'stest-millmgmt01');
  await page.goto(REPORT_PATH);

  const downloadPromise = page.waitForEvent('download');
  await page.locator('[data-testid="report-export-csv"]').click();
  const download = await downloadPromise;

  expect(download.suggestedFilename()).toContain('laporan-manajemen');
});
