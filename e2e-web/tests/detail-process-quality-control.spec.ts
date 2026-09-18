/**
 * DetailProcessQualityControlTest (Browser/Playwright) —
 * screen-110--detail-process-quality-control-web / usecase-119--detail-process-quality-control-web.
 *
 * Playwright spec, mirrors tests/Browser/DetailClarificationTest.php's
 * conventions — MINUS any operational-target table assertion (this station
 * has none).
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserProcessQualityControlTest.php's docblock for the full
 * rationale.
 *
 * Test data assumption: authenticated Supervisor session
 * (pqctest-supervisor01 / Passw0rd!), a pre-seeded Process Quality Control
 * record reachable from the Data Browser's first row.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'



test.describe('Detail Process Quality Control (Web)', () => {
  // Scenario: "berhasil"
  test('klik baris di Data Browser Process Quality Control, halaman detail menampilkan seluruh field record', async ({ page }) => {
    await login(page, 'pqctest-supervisor01', PASSWORD);
    await page.goto('/data/process-quality-control');
    await page.locator('.pqc-table__row').first().click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/process-quality-control/'));
    await expect(page.locator('[data-testid="detail-process-qc-id"]')).toBeVisible();
    await expect(page.locator('[data-testid="process-quality-control-detail-grid"]')).toBeVisible();
  });

  // Scenario: "Record Tidak Ditemukan"
  test('navigasi langsung ke id yang tidak valid, halaman menampilkan error', async ({ page }) => {
    await login(page, 'pqctest-supervisor01', PASSWORD);
    await page.goto('/data/process-quality-control/00000000-0000-0000-0000-000000000000');

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });

  // Scenario: "Edit"
  test('klik tombol Edit pada halaman detail, browser menampilkan Form Process Quality Control mode ubah', async ({ page }) => {
    await login(page, 'pqctest-supervisor01', PASSWORD);
    await page.goto('/data/process-quality-control');
    await page.locator('.pqc-table__row').first().click();
    await page.waitForURL((url) => url.pathname.startsWith('/data/process-quality-control/'));

    await page.locator('[data-testid="edit-button"]').click();
    await page.waitForURL((url) => url.pathname.endsWith('/edit'));
  });
});
