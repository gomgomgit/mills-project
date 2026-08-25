<?php

/**
 * DetailKernelPlantTest (Browser/Playwright) —
 * screen-056--detail-kernel-plant-web /
 * usecase-056--detail-kernel-plant-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/DetailDepricarpingTest.php's conventions.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * tests/Browser/DataBrowserKernelPlantTest.php's docblock for the full
 * rationale.
 *
 * Test data assumption: authenticated session (kernelplanttest-browse01 /
 * Passw0rd!), and a seeded KernelPlantRecord with Kernel Plant ID
 * "KP-BROWSER-DETAIL" reachable from the Data Browser Kernel Plant list.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const USERNAME = 'kernelplanttest-browse01';
const PASSWORD = 'Passw0rd!';

async function login(page, username, password) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
}

test.describe('Detail Kernel Plant (Web)', () => {
  // Scenario: "Lihat Detail Kernel Plant - berhasil"
  test('klik baris di Data Browser Kernel Plant, halaman detail menampilkan seluruh field, grid 24 baris, dan tabel Target Operasional 3 kolom', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}/data/kernel-plant`);
    await page.locator('.kp-table__row', { hasText: 'KP-BROWSER-DETAIL' }).click();

    await expect(page.locator('[data-testid="detail-kernel-plant-id"]')).toContainText('KP-BROWSER-DETAIL');
    await expect(page.locator('[data-testid="kernel-plant-detail-grid"] tbody tr')).toHaveCount(24);
    await expect(page.locator('[data-testid="operational-target-table"] tbody tr')).toHaveCount(6);
    await expect(page.locator('[data-testid="operational-target-table"] thead th')).toHaveCount(3);
    await expect(page.locator('[data-testid="operational-target-table"]')).toContainText('Ripple Mill (Cracker)');
  });

  // Scenario: "Lihat Detail Kernel Plant - Record Tidak Ditemukan"
  test('navigasi langsung ke id yang tidak valid, halaman menampilkan pesan error dan tombol Back', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}/data/kernel-plant/00000000-0000-0000-0000-000000000000`);

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
    await expect(page.locator('[data-testid="back-button"]')).toBeVisible();
  });

  // Edit navigation (Checked By/Acknowledged By blank -> '-' is covered at
  // the Feature/Livewire level; this asserts the Edit button navigates to
  // Form Kernel Plant's edit mode).
  test('klik tombol Edit membuka Form Kernel Plant mode ubah', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(`${BASE_URL}/data/kernel-plant`);
    await page.locator('.kp-table__row', { hasText: 'KP-BROWSER-DETAIL' }).click();
    await page.locator('[data-testid="edit-button"]').click();

    await page.waitForURL((url) => url.pathname.endsWith('/edit'));
  });
});
