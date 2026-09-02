<?php

/**
 * FormSterilizerTest (Browser/Playwright) —
 * screen-126--form-sterilizer-web /
 * usecase-126--form-sterilizer-web.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserSterilizerTest.php's file-level docblock. This is the FINAL
 * station of this project.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const FORM_CREATE_PATH = '/data/sterilizer/create';
const USERNAME = 'stertest-form01';
const PASSWORD = 'Passw0rd!';

async function login(page, username, password) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
}

test.describe('Form Sterilizer', () => {
  test('membuat record baru berhasil', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}${FORM_CREATE_PATH}`);

    await page.locator('[data-testid="production-line-select"]').selectOption({ index: 1 });
    await page.locator('[data-testid="sterilizer-id-input"]').fill('STR-BROWSER-001');
    await page.locator('[data-testid="date-input"]').fill('2026-08-31');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="detail-close-door-time-0"]').fill('07:00');
    await page.locator('[data-testid="detail-open-door-time-0"]').fill('08:10');
    await expect(page.locator('[data-testid="detail-duration-minutes-0"]')).toHaveText('70');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL(/\/data\/sterilizer\/[0-9a-f-]+$/);
  });

  test('menampilkan error inline saat field wajib kosong', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}${FORM_CREATE_PATH}`);

    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="detail-error"]').or(page.locator('.sf-field__error'))).toBeVisible();
  });
});
