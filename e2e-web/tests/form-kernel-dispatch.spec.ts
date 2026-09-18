/**
 * FormKernelDispatchTest (Browser/Playwright) —
 * screen-113--form-kernel-dispatch-web /
 * usecase-078--form-kernel-dispatch-web.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserKernelDispatchTest.php's file-level docblock.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const FORM_CREATE_PATH = '/data/kernel-dispatch/create';
const USERNAME = 'kdtest-form01';


test.describe('Form Kernel Dispatch', () => {
  test('membuat record baru berhasil', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(FORM_CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ index: 1 });
    await page.locator('[data-testid="kernel-dispatch-id-input"]').fill('KD-BROWSER-001');
    await page.locator('[data-testid="date-input"]').fill('2026-08-31');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="detail-event-date-0"]').fill('2026-08-31');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL(/\/data\/kernel-dispatch\/[0-9a-f-]+$/);
  });

  test('menampilkan error inline saat field wajib kosong', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(FORM_CREATE_PATH);

    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="detail-error"]').or(page.locator('.kf-field__error'))).toBeVisible();
  });
});
