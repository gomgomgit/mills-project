/**
 * DetailKernelDispatchTest (Browser/Playwright) —
 * screen-103--detail-kernel-dispatch-web /
 * usecase-077--detail-kernel-dispatch-web.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserKernelDispatchTest.php's file-level docblock.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const DATA_BROWSER_PATH = '/data/kernel-dispatch';
const USERNAME = 'kdtest-browse01';


test.describe('Detail Kernel Dispatch', () => {
  test('klik baris di Data Browser membuka halaman detail dengan seluruh field dan log kejadian', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(DATA_BROWSER_PATH);

    await page.locator('.kd-table__row').first().click();

    await page.waitForURL(/\/data\/kernel-dispatch\/[0-9a-f-]+$/);
    await expect(page.getByText('Detail Kernel Dispatch')).toBeVisible();
    await expect(page.locator('[data-testid="detail-kernel-dispatch-id"]')).toBeVisible();
    await expect(page.locator('[data-testid="kernel-dispatch-detail-log"]')).toBeVisible();
  });

  test('navigasi langsung ke id tidak valid menampilkan pesan error dan tombol Back', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto('/data/kernel-dispatch/00000000-0000-0000-0000-000000000000');

    await expect(page.getByText('Record tidak ditemukan')).toBeVisible();

    await page.locator('[data-testid="back-button"]').click();
    await page.waitForURL(DATA_BROWSER_PATH);
  });
});
