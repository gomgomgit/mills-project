/**
 * DetailProcessWaterTest (Browser/Playwright) —
 * screen-102--detail-process-water-web / usecase-071--detail-process-water-web.
 *
 * Playwright spec, mirrors tests/Browser/DetailThreshingTest.php's
 * conventions — MINUS any operational-target table assertion (this station
 * has none).
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserProcessWaterTest.php's docblock for the full rationale.
 *
 * Test data assumption: authenticated Supervisor session
 * (pwtest-supervisor01 / Passw0rd!), a pre-seeded Process Water record
 * reachable from the Data Browser's first row.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'



test.describe('Detail Process Water (Web)', () => {
  // Scenario: "berhasil"
  test('klik baris di Data Browser Process Water, halaman detail menampilkan seluruh field record', async ({ page }) => {
    await login(page, 'pwtest-supervisor01', PASSWORD);
    await page.goto('/data/process-water');
    await page.locator('.pw-table__row').first().click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/process-water/'));
    await expect(page.locator('[data-testid="detail-process-water-id"]')).toBeVisible();
    await expect(page.locator('[data-testid="process-water-detail-grid"]')).toBeVisible();
  });

  // Scenario: "Record Tidak Ditemukan"
  test('navigasi langsung ke id yang tidak valid, halaman menampilkan error', async ({ page }) => {
    await login(page, 'pwtest-supervisor01', PASSWORD);
    await page.goto('/data/process-water/00000000-0000-0000-0000-000000000000');

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });

  // Scenario: "Edit"
  test('klik tombol Edit pada halaman detail, browser menampilkan Form Process Water mode ubah', async ({ page }) => {
    await login(page, 'pwtest-supervisor01', PASSWORD);
    await page.goto('/data/process-water');
    await page.locator('.pw-table__row').first().click();
    await page.waitForURL((url) => url.pathname.startsWith('/data/process-water/'));

    await page.locator('[data-testid="edit-button"]').click();
    await page.waitForURL((url) => url.pathname.endsWith('/edit'));
  });
});
