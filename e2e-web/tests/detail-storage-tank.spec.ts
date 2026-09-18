/**
 * DetailStorageTankTest (Browser/Playwright) —
 * screen-106--detail-storage-tank-web / usecase-095--detail-storage-tank-web.
 *
 * Playwright spec, mirrors tests/Browser/DetailEffluentPlantTest.php's
 * conventions — MINUS any operational-target table assertion (this station
 * has none).
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserStorageTankTest.php's docblock for the full rationale.
 *
 * Test data assumption: authenticated Supervisor session
 * (sttest-supervisor01 / Passw0rd!), a pre-seeded Storage Tank record
 * reachable from the Data Browser's first row.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'



test.describe('Detail Storage Tank (Web)', () => {
  // Scenario: "berhasil"
  test('klik baris di Data Browser Storage Tank, halaman detail menampilkan seluruh field record', async ({ page }) => {
    await login(page, 'sttest-supervisor01', PASSWORD);
    await page.goto('/data/storage-tank');
    await page.locator('.st-table__row').first().click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/storage-tank/'));
    await expect(page.locator('[data-testid="detail-storage-tank-id"]')).toBeVisible();
    await expect(page.locator('[data-testid="storage-tank-detail-grid"]')).toBeVisible();
  });

  // Scenario: "Record Tidak Ditemukan"
  test('navigasi langsung ke id yang tidak valid, halaman menampilkan error', async ({ page }) => {
    await login(page, 'sttest-supervisor01', PASSWORD);
    await page.goto('/data/storage-tank/00000000-0000-0000-0000-000000000000');

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });

  // Scenario: "Edit"
  test('klik tombol Edit pada halaman detail, browser menampilkan Form Storage Tank mode ubah', async ({ page }) => {
    await login(page, 'sttest-supervisor01', PASSWORD);
    await page.goto('/data/storage-tank');
    await page.locator('.st-table__row').first().click();
    await page.waitForURL((url) => url.pathname.startsWith('/data/storage-tank/'));

    await page.locator('[data-testid="edit-button"]').click();
    await page.waitForURL((url) => url.pathname.endsWith('/edit'));
  });
});
