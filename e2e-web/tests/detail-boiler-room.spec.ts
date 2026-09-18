/**
 * DetailBoilerRoomTest (Browser/Playwright) —
 * screen-108--detail-boiler-room-web / usecase-107--detail-boiler-room-web.
 *
 * Playwright spec, mirrors tests/Browser/DetailEngineRoomTest.php's
 * conventions — MINUS any operational-target table assertion (this station
 * has none).
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserBoilerRoomTest.php's docblock for the full rationale.
 *
 * Test data assumption: authenticated Supervisor session
 * (brtest-supervisor01 / Passw0rd!), a pre-seeded Boiler Room record
 * reachable from the Data Browser's first row.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'



test.describe('Detail Boiler Room (Web)', () => {
  // Scenario: "berhasil"
  test('klik baris di Data Browser Boiler Room, halaman detail menampilkan seluruh field record', async ({ page }) => {
    await login(page, 'brtest-supervisor01', PASSWORD);
    await page.goto('/data/boiler-room');
    await page.locator('.br-table__row').first().click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/boiler-room/'));
    await expect(page.locator('[data-testid="detail-boiler-room-id"]')).toBeVisible();
    await expect(page.locator('[data-testid="boiler-room-detail-grid"]')).toBeVisible();
  });

  // Scenario: "Record Tidak Ditemukan"
  test('navigasi langsung ke id yang tidak valid, halaman menampilkan error', async ({ page }) => {
    await login(page, 'brtest-supervisor01', PASSWORD);
    await page.goto('/data/boiler-room/00000000-0000-0000-0000-000000000000');

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });

  // Scenario: "Edit"
  test('klik tombol Edit pada halaman detail, browser menampilkan Form Boiler Room mode ubah', async ({ page }) => {
    await login(page, 'brtest-supervisor01', PASSWORD);
    await page.goto('/data/boiler-room');
    await page.locator('.br-table__row').first().click();
    await page.waitForURL((url) => url.pathname.startsWith('/data/boiler-room/'));

    await page.locator('[data-testid="edit-button"]').click();
    await page.waitForURL((url) => url.pathname.endsWith('/edit'));
  });
});
