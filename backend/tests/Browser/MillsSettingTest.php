<?php

/**
 * MillsSettingTest (Browser/Playwright) — screen-034--mills-setting /
 * usecase-034--mills-setting.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/KelolaStationTest.php's convention (.php path containing
 * a Playwright TS spec body, per test_strategy.browser_test.tool).
 *
 * WRITTEN BUT NOT RUN IN THIS SESSION — same environment constraint as
 * every sibling Browser spec in this codebase (no dev server/browser
 * available here). Run later via `playwright test` from a project root
 * with @playwright/test installed.
 *
 * Fixture assumptions:
 *   - login business area picker: "Mill A" — stest-admin01 / Passw0rd!
 *     (role: admin), stest-mm01 / Passw0rd! (role: mill_management,
 *     scoped to "Mill A")
 *   - a business unit named "Mill Station Icon" with at least one
 *     station (e.g. "Weighbridge Icon Test") exists, for the icon-picker
 *     scenario
 *   - a business unit named "Mill Kosong" with NO stations exists, for
 *     the empty-state scenario
 * Adjust the USERNAME/BUSINESS_UNIT_NAME constants below to match
 * whatever seeder provisions the target environment.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const MILL_SETTINGS_PATH = '/mill-settings';
const LOGIN_BUSINESS_UNIT_NAME = 'Mill A';
const PASSWORD = 'Passw0rd!';

async function login(page, username, businessUnitName = LOGIN_BUSINESS_UNIT_NAME) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(PASSWORD);
  await page.locator('#business_unit_id').selectOption({ label: businessUnitName });
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
}

async function gotoMillSettings(page) {
  await page.goto(`${BASE_URL}${MILL_SETTINGS_PATH}`);
}

test.describe('Mills Setting', () => {
  test('Admin: pilih mill, ubah nama aplikasi dan jumlah cages, klik Simpan', async ({ page }) => {
    await login(page, 'stest-admin01');
    await gotoMillSettings(page);

    await page.locator('#selectedBusinessUnitId').selectOption({ label: 'Mill A' });
    await page.locator('#app_name').fill('Mill Baru');
    await page.locator('#jumlah_cages').fill('8');
    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.ms-alert--success')).toBeVisible();
    await page.reload();
    await page.locator('#selectedBusinessUnitId').selectOption({ label: 'Mill A' });
    await expect(page.locator('#app_name')).toHaveValue('Mill Baru');
    await expect(page.locator('#jumlah_cages')).toHaveValue('8');
  });

  test('Mill Management: navigasi ke Mills Setting langsung menampilkan mill sendiri, tanpa pemilih', async ({ page }) => {
    await login(page, 'stest-mm01');
    await gotoMillSettings(page);

    await expect(page.locator('#selectedBusinessUnitId')).toHaveCount(0);
    await expect(page.locator('#app_name')).toBeVisible();
  });

  test('Mill yang belum pernah diatur: form menampilkan nilai default tanpa error', async ({ page }) => {
    await login(page, 'stest-admin01');
    await gotoMillSettings(page);

    await page.locator('#selectedBusinessUnitId').selectOption({ label: 'Mill Kosong' });

    await expect(page.locator('.ms-alert--error')).toHaveCount(0);
    await expect(page.locator('#jumlah_cages')).toHaveValue('1');
  });

  test('Validasi jumlah cages: isi 0, klik Simpan, error ditampilkan', async ({ page }) => {
    await login(page, 'stest-admin01');
    await gotoMillSettings(page);

    await page.locator('#selectedBusinessUnitId').selectOption({ label: 'Mill A' });
    await page.locator('#jumlah_cages').fill('0');
    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.ms-form-field__error')).toContainText('lebih dari 0');
  });

  test('Mill Management: navigasi paksa ke business_unit_id lain menampilkan status akses ditolak', async ({ page }) => {
    await login(page, 'stest-mm01');
    await gotoMillSettings(page);

    // No picker is rendered for Mill Management — this scenario is
    // exercised at the API/Livewire-component level instead (see
    // tests/Feature/Api/MillSettingTest.php and
    // tests/Feature/Livewire/MillsSettingTest.php), since there is no
    // in-page control a Mill Management user could use to select another
    // mill in the first place.
    await expect(page.locator('#selectedBusinessUnitId')).toHaveCount(0);
  });

  test('Icon station: pilih icon baru untuk salah satu station, preview diperbarui', async ({ page }) => {
    await login(page, 'stest-admin01');
    await gotoMillSettings(page);

    await page.locator('#selectedBusinessUnitId').selectOption({ label: 'Mill Station Icon' });
    const row = page.locator('.ms-table__row', { hasText: 'Weighbridge Icon Test' });
    await row.locator('select').selectOption('truck');

    await expect(page.locator('.ms-alert--success')).toBeVisible();
    await expect(row.locator('select')).toHaveValue('truck');
  });

  test('Belum ada station: bagian icon station menampilkan pesan kosong, bukan error', async ({ page }) => {
    await login(page, 'stest-admin01');
    await gotoMillSettings(page);

    await page.locator('#selectedBusinessUnitId').selectOption({ label: 'Mill Kosong' });

    await expect(page.locator('.ms-empty__title')).toContainText('Belum ada station terdaftar');
  });
});
