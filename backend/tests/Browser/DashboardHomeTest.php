<?php

/**
 * DashboardHomeTest (Browser/Playwright) — screen-025--dashboard-web /
 * usecase-025--dashboard-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/MillsSettingTest.php's convention (.php path containing a
 * Playwright TS spec body, per test_strategy.browser_test.tool).
 *
 * WRITTEN BUT NOT RUN IN THIS SESSION — same environment constraint as
 * every sibling Browser spec in this codebase (no dev server/browser
 * available here). Run later via `playwright test` from a project root
 * with @playwright/test installed.
 *
 * Fixture assumptions:
 *   - login business area picker: "Mill A" — stest-supervisor01 / Passw0rd!
 *     (role: supervisor)
 *   - at least one Weighbridge record exists for today's date, for the
 *     "berhasil"/"Klik Card Stasiun" scenarios
 * Adjust the USERNAME/BUSINESS_UNIT_NAME constants below to match
 * whatever seeder provisions the target environment.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const DASHBOARD_PATH = '/dashboard';
const LOGIN_BUSINESS_UNIT_NAME = 'Mill A';
const PASSWORD = 'Passw0rd!';

async function login(page, username, businessUnitName = LOGIN_BUSINESS_UNIT_NAME) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(PASSWORD);
  await page.locator('#business_area').selectOption({ label: businessUnitName });
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(`${BASE_URL}${DASHBOARD_PATH}`);
}

// Scenario: "Lihat Dashboard Web — berhasil"
test('berhasil: navigating to /dashboard shows KPI cards and summary table for today', async ({ page }) => {
  await login(page, 'stest-supervisor01');

  await expect(page.locator('[data-testid="dash-card-weighbridge"]')).toBeVisible();
  await expect(page.locator('[data-testid="dash-card-grading"]')).toBeVisible();
  await expect(page.locator('[data-testid="dash-card-cages-track"]')).toBeVisible();
});

// Scenario: "Lihat Dashboard Web — Filter Diterapkan"
test('filter: filling date range and business unit updates the dashboard', async ({ page }) => {
  await login(page, 'stest-supervisor01');

  await page.locator('#date_from').fill('2026-02-01');
  await page.locator('#date_to').fill('2026-02-10');
  await page.waitForTimeout(300);

  await expect(page.locator('[data-testid="dash-card-weighbridge"]')).toBeVisible();
});

// Scenario: "Lihat Dashboard Web — Tidak Ada Data Sesuai Filter"
test('empty: a filter combination with zero results shows 0 on all cards, not an error', async ({ page }) => {
  await login(page, 'stest-supervisor01');

  await page.locator('#date_from').fill('2020-01-01');
  await page.locator('#date_to').fill('2020-01-02');
  await page.waitForTimeout(300);

  await expect(page.locator('[data-testid="dash-card-weighbridge"]')).toContainText('0');
});

// Scenario: "Lihat Dashboard Web — Filter Tanggal Tidak Valid"
test('invalid date range: entering date_from later than date_to shows a validation error', async ({ page }) => {
  await login(page, 'stest-supervisor01');

  await page.locator('#date_from').fill('2026-02-10');
  await page.locator('#date_to').fill('2026-02-01');
  await page.waitForTimeout(300);

  await expect(page.locator('.dash-alert')).toBeVisible();
});

// Scenario: "Lihat Dashboard Web — Klik Card Stasiun"
test('klik card: clicking the Weighbridge card navigates to Data Browser Weighbridge', async ({ page }) => {
  await login(page, 'stest-supervisor01');

  await page.locator('[data-testid="dash-card-weighbridge"]').click();
  await expect(page).toHaveURL(/\/data\/weighbridge/);
});
