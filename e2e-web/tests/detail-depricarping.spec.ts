/**
 * DetailDepricarpingTest (Browser/Playwright) —
 * screen-055--detail-depricarping-web /
 * usecase-055--detail-depricarping-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/DetailPressingTest.php's conventions.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * tests/Browser/DataBrowserDepricarpingTest.php's docblock for the full
 * rationale.
 *
 * Test data assumption: authenticated session (depricarpingtest-browse01 /
 * Passw0rd!), and a seeded DepricarpingRecord with Presser ID
 * "DP-BROWSER-DETAIL" reachable from the Data Browser Depricarping list.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const USERNAME = 'depricarpingtest-browse01';


test.describe('Detail Depricarping (Web)', () => {
  // Scenario: "Lihat Detail Depricarping - berhasil"
  test('klik baris di Data Browser Depricarping, halaman detail menampilkan seluruh field, grid 24 baris, dan tabel Target Operasional 4 kolom', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto('/data/depricarping');
    await page.locator('.dp-table__row', { hasText: 'DP-BROWSER-DETAIL' }).click();

    await expect(page.locator('[data-testid="detail-presser-id"]')).toContainText('DP-BROWSER-DETAIL');
    await expect(page.locator('[data-testid="depricarping-detail-grid"] tbody tr')).toHaveCount(24);
    await expect(page.locator('[data-testid="operational-target-table"] tbody tr')).toHaveCount(6);
    await expect(page.locator('[data-testid="operational-target-table"] thead th')).toHaveCount(4);
    await expect(page.locator('[data-testid="operational-target-table"]')).toContainText('Fan Static Pressure');
  });

  // Scenario: "Lihat Detail Depricarping - Record Tidak Ditemukan"
  test('navigasi langsung ke id yang tidak valid, halaman menampilkan pesan error dan tombol Back', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto('/data/depricarping/00000000-0000-0000-0000-000000000000');

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
    await expect(page.locator('[data-testid="back-button"]')).toBeVisible();
  });

  // Edit navigation (Checked By/Acknowledged By blank -> '-' is covered at
  // the Feature/Livewire level; this asserts the Edit button navigates to
  // Form Depricarping's edit mode).
  test('klik tombol Edit membuka Form Depricarping mode ubah', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto('/data/depricarping');
    await page.locator('.dp-table__row', { hasText: 'DP-BROWSER-DETAIL' }).click();
    await page.locator('[data-testid="edit-button"]').click();

    await page.waitForURL((url) => url.pathname.endsWith('/edit'));
  });
});
