/**
 * ProductionProcessActivityTest (Browser/Playwright) —
 * screen-035--production-process-activity-web /
 * usecase-035--production-process-activity-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/DashboardHomeTest.php's convention (.php path containing a
 * Playwright TS spec body).
 *
 * WRITTEN BUT NOT RUN IN THIS SESSION — same environment constraint as
 * every sibling Browser spec in this codebase (no dev server/browser
 * available here). Run later via `playwright test` from a project root
 * with @playwright/test installed.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const PPA_PATH = '/production-process-activity';

test.describe('Production Process Activity (screen-035)', () => {
  test('Pilih Stasiun (Web) — success: clicking the Weighbridge tile navigates to its Data Browser', async ({ page }) => {
    await login(page, 'supervisor01');
    await page.goto(PPA_PATH);

    await page.getByRole('link', { name: /Weighbridge/i }).click();

    await page.waitForURL('/data/weighbridge');
    await expect(page).toHaveURL('/data/weighbridge');
  });

  // Repurposed 2026-09-01: Sterilizer (the last placeholder) was promoted
  // to a fully active tile — 0 placeholders remain anywhere on the grid,
  // so there is nothing left to click-disabled. This now asserts the
  // negative: clicking the (now active) Sterilizer tile navigates normally,
  // and no "Belum tersedia" placeholder text renders anywhere.
  test('Pilih Stasiun (Web) — Sterilizer is now active: clicking it navigates to its Data Browser', async ({ page }) => {
    await login(page, 'supervisor01');
    await page.goto(PPA_PATH);

    await expect(page.getByText('Belum tersedia')).toHaveCount(0);

    await page.getByRole('link', { name: /Sterilizer/i }).click();

    await page.waitForURL('/data/sterilizer');
    await expect(page).toHaveURL('/data/sterilizer');
  });
});
