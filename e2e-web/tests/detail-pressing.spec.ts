/**
 * DetailPressingTest (Browser/Playwright) — screen-054--detail-pressing-web
 * / usecase-054--detail-pressing-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/DetailThreshingTest.php's conventions.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * tests/Browser/DataBrowserPressingTest.php's docblock for the full
 * rationale.
 *
 * Test data assumption: authenticated session (presstest-browse01 /
 * Passw0rd!), and a seeded PressingRecord with Presser ID
 * "PR-BROWSER-DETAIL" reachable from the Data Browser Pressing list.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const USERNAME = 'presstest-browse01';


test.describe('Detail Pressing (Web)', () => {
  // Scenario: "Lihat Detail Pressing - berhasil"
  test('klik baris di Data Browser Pressing, halaman detail menampilkan seluruh field, grid 24 baris, dan tabel Target Operasional', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto('/data/pressing');
    await page.locator('.pr-table__row', { hasText: 'PR-BROWSER-DETAIL' }).click();

    await expect(page.locator('[data-testid="detail-presser-id"]')).toContainText('PR-BROWSER-DETAIL');
    await expect(page.locator('[data-testid="pressing-detail-grid"] tbody tr')).toHaveCount(24);
    await expect(page.locator('[data-testid="operational-target-table"] tbody tr')).toHaveCount(7);
    await expect(page.locator('[data-testid="operational-target-table"]')).toContainText('Cone Hydraulic Pressure');
  });

  // Scenario: "Lihat Detail Pressing - Record Tidak Ditemukan"
  test('navigasi langsung ke id yang tidak valid, halaman menampilkan pesan error dan tombol Back', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto('/data/pressing/00000000-0000-0000-0000-000000000000');

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
    await expect(page.locator('[data-testid="back-button"]')).toBeVisible();
  });

  // Edit navigation (Checked By/Acknowledged By blank -> '-' is covered at
  // the Feature/Livewire level; this asserts the Edit button navigates to
  // Form Pressing's edit mode).
  test('klik tombol Edit membuka Form Pressing mode ubah', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto('/data/pressing');
    await page.locator('.pr-table__row', { hasText: 'PR-BROWSER-DETAIL' }).click();
    await page.locator('[data-testid="edit-button"]').click();

    await page.waitForURL((url) => url.pathname.endsWith('/edit'));
  });
});
