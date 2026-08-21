<?php

/**
 * KelolaMachineryTest (Browser/Playwright) — screen-031--kelola-machinery /
 * usecase-031--kelola-machinery.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * the convention established by tests/Browser/KelolaMachineryGroupTest.php
 * and tests/Browser/KelolaCorporateTest.php (same file-extension/
 * import-syntax pattern, Playwright not Laravel Dusk).
 *
 * This is a plain Livewire/Blade web screen served by `php artisan serve`
 * (test_strategy.browser_test.base_url), so it is fully browser-testable
 * via the dev server — not deferred.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT: there is no dev server
 * or browser available in this sandbox. This file is written to be
 * complete and correct, to be run later via `playwright test` per
 * test_strategy.browser_test.run_command, from a project root with
 * @playwright/test installed and a playwright.config.* pointing at this
 * file (e.g. testDir including backend/tests/Browser).
 *
 * Test data assumption (mirrors KelolaMachineryGroupTest.php's approach):
 * this screen requires an authenticated admin session, so each scenario
 * logs in via /login first, then navigates to /master-data/machinery.
 * Each scenario uses its own dedicated fixture user/data so scenario
 * order never matters even though this spec does not reset the DB itself.
 *   - business unit: "Mill A" (any business_unit whose name is selectable
 *     in the login form's Business Area <select>)
 *   - mtest-admin01 / Passw0rd! (role: admin) — scenarios 1-6, 8-9
 *   - mtest-nonadmin01 / Passw0rd! (role: supervisor) — scenario 7
 * Scenario assumes a Machinery Group named "MG-BROWSER-BASE" is
 * pre-seeded and selectable in the #machinery_group_id dropdown. Scenario
 * 2 assumes a machinery named "EQ-BROWSER-SEBELUM-EDIT" already exists.
 * Scenario 3 assumes a machinery named "EQ-BROWSER-HAPUS" with NO related
 * child rows already exists (this screen has no delete-guard — deletion
 * always succeeds, even when child rows exist, so no "ditolak" scenario
 * is needed here, unlike Machinery Group's). Scenario 5 assumes a
 * machinery named "EQ-DUP-01" already exists. Adjust the
 * USERNAME/BUSINESS_UNIT_NAME/fixture-name constants below to match
 * whatever seeder is used to provision the target environment.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const MACHINERY_PATH = '/master-data/machinery';
const BUSINESS_UNIT_NAME = 'Mill A';
const PASSWORD = 'Passw0rd!';

async function login(page, username, password) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.locator('#business_unit_id').selectOption({ label: BUSINESS_UNIT_NAME });
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
}

async function gotoMachinery(page) {
  await page.goto(`${BASE_URL}${MACHINERY_PATH}`);
}

test.describe('Kelola Machinery', () => {
  // Scenario: "Kelola Machinery — success"
  test('menambah machinery baru dengan memilih machinery group, station/production line terisi otomatis, dan menampilkannya di tabel', async ({ page }) => {
    await login(page, 'mtest-admin01', PASSWORD);
    await gotoMachinery(page);

    await page.locator('button', { hasText: 'Tambah Machinery' }).click();
    await page.locator('#machinery_group_id').selectOption({ label: 'MG-BROWSER-BASE' });

    // Station/Production Line fields are read-only and auto-populated
    // from the selected Machinery Group — never independently typed.
    await expect(page.locator('#station_display')).toBeDisabled();
    await expect(page.locator('#production_line_display')).toBeDisabled();

    const uniqueSuffix = Date.now();
    const uniqueCode = `EQ-BROWSER-${uniqueSuffix}`;
    await page.locator('#equipment_code').fill(uniqueCode);
    await page.locator('#name').fill('Boiler Utama Browser');
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    const row = page.locator('.kc-table__row', { hasText: uniqueCode });
    await expect(row).toBeVisible();
    await expect(row).toContainText('Boiler Utama Browser');
    await expect(row).toContainText('MG-BROWSER-BASE');
  });

  // Scenario: "Kelola Machinery — child-row grids (insurance & tax/purchase)"
  test('menambah baris asuransi dan pajak/pembelian lalu menyimpan bersama data machinery', async ({ page }) => {
    await login(page, 'mtest-admin01', PASSWORD);
    await gotoMachinery(page);

    await page.locator('button', { hasText: 'Tambah Machinery' }).click();
    await page.locator('#machinery_group_id').selectOption({ label: 'MG-BROWSER-BASE' });

    const uniqueSuffix = Date.now();
    const uniqueCode = `EQ-BROWSER-CHILD-${uniqueSuffix}`;
    await page.locator('#equipment_code').fill(uniqueCode);
    await page.locator('#name').fill('Mesin Dengan Anak Baris');

    await page.locator('button', { hasText: 'Tambah Baris Asuransi' }).click();
    await page.locator('input[wire\\:model="insurances.0.ownership"]').fill('Perusahaan');
    await page.locator('input[wire\\:model="insurances.0.insurance_policy_no"]').fill('POL-BROWSER-1');

    await page.locator('button', { hasText: 'Tambah Baris Pajak/Pembelian' }).click();
    await page.locator('input[wire\\:model="taxPurchases.0.policy_type"]').fill('Cash');

    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    const row = page.locator('.kc-table__row', { hasText: uniqueCode });
    await expect(row).toBeVisible();

    // Re-open the row in edit mode and confirm both child rows persisted.
    await row.locator('button', { hasText: 'Edit' }).click();
    await expect(page.locator('input[wire\\:model="insurances.0.insurance_policy_no"]')).toHaveValue('POL-BROWSER-1');
    await expect(page.locator('input[wire\\:model="taxPurchases.0.policy_type"]')).toHaveValue('Cash');
  });

  // Scenario: "Kelola Machinery — Edit Machinery"
  test('mengedit nama dan machinery group machinery lalu menampilkan perubahan di tabel', async ({ page }) => {
    await login(page, 'mtest-admin01', PASSWORD);
    await gotoMachinery(page);

    const row = page.locator('.kc-table__row', { hasText: 'EQ-BROWSER-SEBELUM-EDIT' });
    await row.locator('button', { hasText: 'Edit' }).click();

    await page.locator('#name').fill('Nama Sesudah Edit');
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    const updatedRow = page.locator('.kc-table__row', { hasText: 'EQ-BROWSER-SEBELUM-EDIT' });
    await expect(updatedRow).toContainText('Nama Sesudah Edit');
  });

  // Scenario: "Kelola Machinery — Hapus — berhasil, NO delete-guard even
  // with child rows"
  test('menghapus machinery (termasuk yang memiliki data asuransi/pajak) tanpa ditolak, baris hilang dari tabel', async ({ page }) => {
    await login(page, 'mtest-admin01', PASSWORD);
    await gotoMachinery(page);

    const row = page.locator('.kc-table__row', { hasText: 'EQ-BROWSER-HAPUS' });
    await row.locator('button', { hasText: 'Hapus' }).click();
    await row.locator('button', { hasText: 'Ya, Hapus' }).click();

    await expect(page.locator('.kc-table__row', { hasText: 'EQ-BROWSER-HAPUS' })).toHaveCount(0);
    // No 409/guard alert of any kind is ever shown for this screen.
    await expect(page.locator('.kc-alert')).toHaveCount(0);
  });

  // Scenario: "Kelola Machinery — Kode duplikat"
  test('menampilkan error validasi saat menyimpan kode equipment yang sudah dipakai machinery lain', async ({ page }) => {
    await login(page, 'mtest-admin01', PASSWORD);
    await gotoMachinery(page);

    await page.locator('button', { hasText: 'Tambah Machinery' }).click();
    await page.locator('#machinery_group_id').selectOption({ index: 1 });
    await page.locator('#equipment_code').fill('EQ-DUP-01');
    await page.locator('#name').fill('Mesin Duplikat');
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-form-field__error')).toContainText(/sudah digunakan/i);
    await expect(page.locator('.kc-modal')).toBeVisible();
  });

  // Scenario: "Kelola Machinery — Machinery Group wajib dipilih"
  test('menampilkan error validasi saat submit form tanpa memilih Machinery Group', async ({ page }) => {
    await login(page, 'mtest-admin01', PASSWORD);
    await gotoMachinery(page);

    await page.locator('button', { hasText: 'Tambah Machinery' }).click();
    const uniqueSuffix = Date.now();
    await page.locator('#equipment_code').fill(`EQ-NOGROUP-${uniqueSuffix}`);
    await page.locator('#name').fill('Mesin Tanpa Group');
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-form-field__error')).toContainText(/wajib dipilih/i);
    await expect(page.locator('.kc-modal')).toBeVisible();
  });

  // Scenario: "Kelola Machinery — Akses ditolak untuk non-Admin"
  test('menampilkan halaman akses ditolak untuk pengguna non-admin', async ({ page }) => {
    await login(page, 'mtest-nonadmin01', PASSWORD);
    await page.goto(`${BASE_URL}${MACHINERY_PATH}`);

    await expect(page.locator('body')).toContainText(/403/);
    await expect(page.locator('.kc-table')).toHaveCount(0);
  });

  // Additional coverage: filtering the table by Machinery Group.
  test('memfilter tabel machinery berdasarkan Machinery Group yang dipilih', async ({ page }) => {
    await login(page, 'mtest-admin01', PASSWORD);
    await gotoMachinery(page);

    await page.locator('#filterMachineryGroupId').selectOption({ index: 1 });

    await expect(page.locator('.kc-table')).toBeVisible();
  });
});
