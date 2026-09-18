/**
 * FormSolidWasteDisposalTest (Browser/Playwright) —
 * screen-111--form-solid-waste-disposal-web /
 * usecase-066--form-solid-waste-disposal-web.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserSolidWasteDisposalTest.php's file-level docblock.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const FORM_CREATE_PATH = '/data/solid-waste-disposal/create';
const USERNAME = 'swdtest-form01';


test.describe('Form Solid Waste Disposal', () => {
  test('membuat record baru berhasil', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(FORM_CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ index: 1 });
    await page.locator('[data-testid="solid-waste-disposal-id-input"]').fill('SWD-BROWSER-001');
    await page.locator('[data-testid="date-input"]').fill('2026-08-31');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="detail-event-date-0"]').fill('2026-08-31');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL(/\/data\/solid-waste-disposal\/[0-9a-f-]+$/);
  });

  test('menampilkan error inline saat field wajib kosong', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(FORM_CREATE_PATH);

    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="detail-error"]').or(page.locator('.sf-field__error'))).toBeVisible();
  });
});
