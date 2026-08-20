<?php

/**
 * ProductionProcessActivityTest (Browser/Playwright) —
 * screen-035--production-process-activity-web /
 * usecase-035--production-process-activity-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/DashboardHomeTest.php's convention (.php path containing a
 * Playwright TS spec body).
 *
 * WRITTEN BUT NOT RUN IN THIS SESSION — same environment constraint as
 * every sibling Browser spec in this codebase (no dev server/browser
 * available here). Run later via `playwright test` from a project root
 * with @playwright/test installed.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const PPA_PATH = '/production-process-activity';
const LOGIN_BUSINESS_UNIT_NAME = 'Mill A';
const PASSWORD = 'Passw0rd!';

async function login(page, username, businessUnitName = LOGIN_BUSINESS_UNIT_NAME) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(PASSWORD);
  await page.locator('#business_area').selectOption({ label: businessUnitName });
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(`${BASE_URL}/dashboard`);
}

test.describe('Production Process Activity (screen-035)', () => {
  test('Pilih Stasiun (Web) — success: clicking the Weighbridge tile navigates to its Data Browser', async ({ page }) => {
    await login(page, 'supervisor01');
    await page.goto(`${BASE_URL}${PPA_PATH}`);

    await page.getByRole('link', { name: /Weighbridge/i }).click();

    await page.waitForURL(`${BASE_URL}/data/weighbridge`);
    await expect(page).toHaveURL(`${BASE_URL}/data/weighbridge`);
  });

  test('Pilih Stasiun (Web) — Klik Stasiun Disabled: clicking a placeholder tile does not navigate', async ({ page }) => {
    await login(page, 'supervisor01');
    await page.goto(`${BASE_URL}${PPA_PATH}`);

    const placeholderTile = page.getByText('Sterilizer').locator('..');
    await placeholderTile.click({ force: true });

    // No navigation should have occurred — still on the same page.
    await expect(page).toHaveURL(`${BASE_URL}${PPA_PATH}`);
  });
});
