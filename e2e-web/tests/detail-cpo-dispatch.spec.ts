/**
 * DetailCpoDispatchTest (Browser/Playwright) —
 * screen-104--detail-cpo-dispatch-web /
 * usecase-083--detail-cpo-dispatch-web.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserCpoDispatchTest.php's file-level docblock.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const DATA_BROWSER_PATH = '/data/cpo-dispatch';
const USERNAME = 'cdtest-browse01';


test.describe('Detail CPO Dispatch', () => {
  test('klik baris di Data Browser membuka halaman detail dengan seluruh field dan log kejadian', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(DATA_BROWSER_PATH);

    await page.locator('.cd-table__row').first().click();

    await page.waitForURL(/\/data\/cpo-dispatch\/[0-9a-f-]+$/);
    await expect(page.getByText('Detail CPO Dispatch')).toBeVisible();
    await expect(page.locator('[data-testid="detail-cpo-dispatch-id"]')).toBeVisible();
    await expect(page.locator('[data-testid="cpo-dispatch-detail-log"]')).toBeVisible();
  });

  test('navigasi langsung ke id tidak valid menampilkan pesan error dan tombol Back', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto('/data/cpo-dispatch/00000000-0000-0000-0000-000000000000');

    await expect(page.getByText('Record tidak ditemukan')).toBeVisible();

    await page.locator('[data-testid="back-button"]').click();
    await page.waitForURL(DATA_BROWSER_PATH);
  });
});
