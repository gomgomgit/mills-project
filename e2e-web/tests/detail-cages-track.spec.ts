/**
 * DetailCagesTrackTest (Browser/Playwright) — screen-021--detail-cages-track-web /
 * usecase-021--detail-cages-track-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/DetailGradingTest.php's convention (a .php path
 * containing a Playwright TS spec body).
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserWeighbridgeTest.php's file-level docblock for the same
 * sandbox-limitation note; this file follows the same run instructions.
 *
 * Test data assumption (mirrors DetailGradingTest.php): requires an
 * authenticated session with role supervisor/mill_management/admin, and at
 * least one CagesTrackRecord for that business unit to click through from
 * the Data Browser.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const DATA_BROWSER_PATH = '/data/cages-track';
const BUSINESS_UNIT_NAME = 'Mill A';
const USERNAME = 'cttest-browse01';


test.describe('Detail Cages Track', () => {
  // Scenario: "Lihat Detail Cages Track - berhasil"
  test('klik baris di Data Browser membuka halaman detail dengan seluruh field dan grid Cages Tipped Time', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(DATA_BROWSER_PATH);

    await page.locator('.ct-table__row').first().click();

    await page.waitForURL(/\/data\/cages-track\/[0-9a-f-]+$/);
    await expect(page.getByText('Detail Cages Track')).toBeVisible();
    await expect(page.locator('[data-testid="detail-cages-track-number"]')).toBeVisible();
    await expect(page.locator('[data-testid="cages-tipped-time-grid"]')).toBeVisible();
  });

  // Scenario: "Lihat Detail Cages Track - Record Tidak Ditemukan"
  test('navigasi langsung ke id tidak valid menampilkan pesan error dan tombol Back', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto('/data/cages-track/00000000-0000-0000-0000-000000000000');

    await expect(page.getByText('Record tidak ditemukan')).toBeVisible();

    await page.locator('[data-testid="back-button"]').click();

    expect(page.url()).toContain(DATA_BROWSER_PATH);
  });
});
