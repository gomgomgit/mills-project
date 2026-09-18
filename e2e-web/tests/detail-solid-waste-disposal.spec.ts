/**
 * DetailSolidWasteDisposalTest (Browser/Playwright) —
 * screen-101--detail-solid-waste-disposal-web /
 * usecase-065--detail-solid-waste-disposal-web.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserSolidWasteDisposalTest.php's file-level docblock.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const DATA_BROWSER_PATH = '/data/solid-waste-disposal';
const USERNAME = 'swdtest-browse01';


test.describe('Detail Solid Waste Disposal', () => {
  test('klik baris di Data Browser membuka halaman detail dengan seluruh field dan log kejadian', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(DATA_BROWSER_PATH);

    await page.locator('.sw-table__row').first().click();

    await page.waitForURL(/\/data\/solid-waste-disposal\/[0-9a-f-]+$/);
    await expect(page.getByText('Detail Solid Waste Disposal')).toBeVisible();
    await expect(page.locator('[data-testid="detail-solid-waste-disposal-id"]')).toBeVisible();
    await expect(page.locator('[data-testid="solid-waste-disposal-detail-log"]')).toBeVisible();
  });

  test('navigasi langsung ke id tidak valid menampilkan pesan error dan tombol Back', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto('/data/solid-waste-disposal/00000000-0000-0000-0000-000000000000');

    await expect(page.getByText('Record tidak ditemukan')).toBeVisible();

    await page.locator('[data-testid="back-button"]').click();
    await page.waitForURL(DATA_BROWSER_PATH);
  });
});
