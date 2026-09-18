/**
 * DetailSterilizerTest (Browser/Playwright) —
 * screen-125--detail-sterilizer-web /
 * usecase-125--detail-sterilizer-web.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserSterilizerTest.php's file-level docblock.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const DATA_BROWSER_PATH = '/data/sterilizer';
const USERNAME = 'stertest-browse01';


test.describe('Detail Sterilizer', () => {
  test('klik baris di Data Browser membuka halaman detail dengan seluruh field dan log siklus', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(DATA_BROWSER_PATH);

    await page.locator('.sf-table__row').first().click();

    await page.waitForURL(/\/data\/sterilizer\/[0-9a-f-]+$/);
    await expect(page.getByText('Detail Sterilizer')).toBeVisible();
    await expect(page.locator('[data-testid="detail-sterilizer-id"]')).toBeVisible();
    await expect(page.locator('[data-testid="sterilizer-detail-log"]')).toBeVisible();
  });

  test('navigasi langsung ke id tidak valid menampilkan pesan error dan tombol Back', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto('/data/sterilizer/00000000-0000-0000-0000-000000000000');

    await expect(page.getByText('Record tidak ditemukan')).toBeVisible();

    await page.locator('[data-testid="back-button"]').click();
    await page.waitForURL(DATA_BROWSER_PATH);
  });
});
