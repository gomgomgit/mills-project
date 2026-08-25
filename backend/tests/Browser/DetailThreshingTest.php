<?php

/**
 * DetailThreshingTest (Browser/Playwright) — screen-053--detail-threshing-web
 * / usecase-053--detail-threshing-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/DetailCagesTrackTest.php's conventions.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * tests/Browser/DataBrowserThreshingTest.php's docblock for the full
 * rationale.
 *
 * Test data assumption: authenticated session (threshtest-browse01 /
 * Passw0rd!), and a seeded ThreshingRecord with Thresher ID
 * "TH-BROWSER-DETAIL" reachable from the Data Browser Threshing list.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const USERNAME = 'threshtest-browse01';
const PASSWORD = 'Passw0rd!';

async function login(page, username, password) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
}

test.describe('Detail Threshing (Web)', () => {
  // Scenario: "Lihat Detail Threshing - berhasil"
  test('klik baris di Data Browser Threshing, halaman detail menampilkan seluruh field, grid 24 baris, dan tabel Target Operasional', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}/data/threshing`);
    await page.locator('.th-table__row', { hasText: 'TH-BROWSER-DETAIL' }).click();

    await expect(page.locator('[data-testid="detail-thresher-id"]')).toContainText('TH-BROWSER-DETAIL');
    await expect(page.locator('[data-testid="threshing-detail-grid"] tbody tr')).toHaveCount(24);
    await expect(page.locator('[data-testid="operational-target-table"] tbody tr')).toHaveCount(6);
    await expect(page.locator('[data-testid="operational-target-table"]')).toContainText('Thresher Drum Speed');
  });

  // Scenario: "Lihat Detail Threshing - Record Tidak Ditemukan"
  test('navigasi langsung ke id yang tidak valid, halaman menampilkan pesan error dan tombol Back', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}/data/threshing/00000000-0000-0000-0000-000000000000`);

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
    await expect(page.locator('[data-testid="back-button"]')).toBeVisible();
  });

  // Edit navigation (Checked By/Acknowledged By blank -> '-' is covered at
  // the Feature/Livewire level; this asserts the Edit button navigates to
  // Form Threshing's edit mode).
  test('klik tombol Edit membuka Form Threshing mode ubah', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}/data/threshing`);
    await page.locator('.th-table__row', { hasText: 'TH-BROWSER-DETAIL' }).click();
    await page.locator('[data-testid="edit-button"]').click();

    await page.waitForURL((url) => url.pathname.endsWith('/edit'));
  });
});
