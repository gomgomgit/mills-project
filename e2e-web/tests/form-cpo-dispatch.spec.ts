/**
 * FormCpoDispatchTest (Browser/Playwright) —
 * screen-114--form-cpo-dispatch-web /
 * usecase-084--form-cpo-dispatch-web.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserCpoDispatchTest.php's file-level docblock.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const FORM_CREATE_PATH = '/data/cpo-dispatch/create';
const USERNAME = 'cdtest-form01';


test.describe('Form CPO Dispatch', () => {
  test('membuat record baru berhasil', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(FORM_CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ index: 1 });
    await page.locator('[data-testid="cpo-dispatch-id-input"]').fill('CD-BROWSER-001');
    await page.locator('[data-testid="date-input"]').fill('2026-08-31');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="detail-event-date-0"]').fill('2026-08-31');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL(/\/data\/cpo-dispatch\/[0-9a-f-]+$/);
  });

  test('menampilkan error inline saat field wajib kosong', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(FORM_CREATE_PATH);

    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="detail-error"]').or(page.locator('.cf-field__error'))).toBeVisible();
  });
});
