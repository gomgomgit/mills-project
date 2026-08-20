<?php

/**
 * DetailGradingTest (Browser/Playwright) — screen-020--detail-grading-web /
 * usecase-020--detail-grading-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/DetailWeighbridgeTest.php's convention (a .php path
 * containing a Playwright TS spec body).
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserWeighbridgeTest.php's file-level docblock for the same
 * sandbox-limitation note; this file follows the same run instructions.
 *
 * Test data assumption (mirrors DetailWeighbridgeTest.php): requires an
 * authenticated session with role supervisor/mill_management/admin, and at
 * least one GradingRecord for that business unit to click through from the
 * Data Browser.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const DATA_BROWSER_PATH = '/data/grading';
const BUSINESS_UNIT_NAME = 'Mill A';
const USERNAME = 'grtest-browse01';
const PASSWORD = 'Passw0rd!';

async function login(page, username, password) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.locator('#business_unit_id').selectOption({ label: BUSINESS_UNIT_NAME });
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
}

test.describe('Detail Grading', () => {
  // Scenario: "Lihat Detail Grading — berhasil"
  test('klik baris di Data Browser membuka halaman detail dengan seluruh field dan grid detail', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}${DATA_BROWSER_PATH}`);

    await page.locator('.gr-table__row').first().click();

    await page.waitForURL(/\/data\/grading\/[0-9a-f-]+$/);
    await expect(page.getByText('Detail Grading')).toBeVisible();
    await expect(page.locator('[data-testid="detail-grading-number"]')).toBeVisible();
    await expect(page.locator('[data-testid="grading-detail-grid"]')).toBeVisible();
  });

  // Scenario: "Lihat Detail Grading — Record Tidak Ditemukan"
  test('navigasi langsung ke id tidak valid menampilkan pesan error dan tombol Back', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}/data/grading/00000000-0000-0000-0000-000000000000`);

    await expect(page.getByText('Record tidak ditemukan')).toBeVisible();

    await page.locator('[data-testid="back-button"]').click();

    expect(page.url()).toContain(DATA_BROWSER_PATH);
  });
});
