<?php

/**
 * DataBrowserPressingTest (Browser/Playwright) —
 * screen-050--data-browser-pressing-web /
 * usecase-050--data-browser-pressing-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/DataBrowserThreshingTest.php's conventions exactly.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — same constraint as every
 * other Browser/* spec in this codebase (no dev server/browser available in
 * this sandbox). See that file's own docblock for the full rationale.
 *
 * Test data assumption: authenticated session with role supervisor,
 * mill_management, or admin (username presstest-browse01 / Passw0rd!),
 * and at least one PressingRecord for that user's business unit, dated
 * within 2026-01-01..2026-12-31.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const DATA_BROWSER_PATH = '/data/pressing';
const USERNAME = 'presstest-browse01';
const PASSWORD = 'Passw0rd!';

async function login(page, username, password) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
}

test.describe('Data Browser Pressing', () => {
  // Scenario: "Telusuri & Ekspor Data Pressing — success"
  test('menerapkan filter, tabel diperbarui, dan ekspor CSV memicu unduhan', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}${DATA_BROWSER_PATH}`);

    await page.locator('#date_from').fill('2026-01-01');
    await page.locator('#date_to').fill('2026-12-31');

    await expect(page.locator('.pr-browser')).not.toHaveClass(/pr-browser--busy/);
    await expect(page.locator('.pr-table__row').first()).toBeVisible();
    await expect(page.locator('.pr-empty')).toHaveCount(0);

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.locator('.pr-browser__export a', { hasText: 'Ekspor CSV' }).click(),
    ]);

    expect(download.suggestedFilename()).toMatch(/^pressing-records_.*\.csv$/);
  });

  // Scenario: "Telusuri & Ekspor Data Pressing — Tidak Ada Data Sesuai Filter"
  test('filter tanpa data cocok menampilkan pesan tidak ada data', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}${DATA_BROWSER_PATH}`);

    await page.locator('#date_from').fill('2000-01-01');
    await page.locator('#date_to').fill('2000-01-02');

    await expect(page.locator('.pr-browser')).not.toHaveClass(/pr-browser--busy/);
    await expect(page.locator('.pr-empty__title')).toHaveText('Tidak ada data');
  });

  // Scenario: "Telusuri & Ekspor Data Pressing — Rentang Tanggal Tidak Valid"
  test('rentang tanggal tidak valid menampilkan pesan validasi', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}${DATA_BROWSER_PATH}`);

    await page.locator('#date_from').fill('2026-08-20');
    await page.locator('#date_to').fill('2026-08-10');

    await expect(page.locator('.pr-alert')).toContainText('Rentang tanggal tidak valid');
  });

  // Scenario: "Telusuri & Ekspor Data Pressing — Klik Baris Membuka Detail"
  test('klik baris membuka halaman Detail Pressing', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}${DATA_BROWSER_PATH}`);

    await page.locator('.pr-table__row').first().click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/pressing/') && url.pathname !== '/data/pressing/create');
  });

  // Scenario: "Telusuri & Ekspor Data Pressing — Ekspor Gagal"
  // (dataset size guard is server-side only, see
  // tests/Feature/Api/DataBrowserPressingTest.php's "Ekspor Gagal" scenario
  // for full end-to-end coverage of the 422 EXPORT_FAILED response —
  // reproducing a 50,001-row dataset through the real browser is
  // impractical here.)
  test('tombol ekspor selalu mengikuti filter aktif (unduhan gagal diverifikasi lewat Feature/Api)', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}${DATA_BROWSER_PATH}`);

    await page.locator('#date_from').fill('2026-01-01');
    await page.locator('#date_to').fill('2026-12-31');

    const exportHref = await page.locator('.pr-browser__export a', { hasText: 'Ekspor CSV' }).getAttribute('href');
    expect(exportHref).toContain('date_from=2026-01-01');
    expect(exportHref).toContain('date_to=2026-12-31');
  });
});
