<?php

/**
 * KelolaUserRoleTest (Browser/Playwright) — screen-032--kelola-user-role /
 * usecase-032--kelola-user-role.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * the convention established by tests/Browser/KelolaBusinessUnitTest.php.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT: there is no dev server or
 * browser available in this sandbox. This file is written to be complete
 * and correct, to be run later via `playwright test` per
 * test_strategy.browser_test.run_command.
 *
 * Test data assumption (mirrors KelolaBusinessUnitTest.php's approach):
 * each scenario logs in via /login first, then navigates to /users.
 *   - business unit: "Mill A" (login form's Business Area picker)
 *   - urtest-admin01 / Passw0rd! (role: admin) — scenarios 1-7
 *   - urtest-nonadmin01 / Passw0rd! (role: supervisor) — scenario 7
 * Scenario 2 assumes a user "urtest-existing01" already exists to edit.
 * Scenario 3 assumes a user with username "urtest-duplicate01" already
 * exists. Scenario 6 assumes a second user "urtest-other01" (not the
 * logged-in admin) exists to deactivate. Adjust the USERNAME/fixture-name
 * constants below to match whatever seeder provisions the target
 * environment.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const USERS_PATH = '/users';
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

async function gotoUsers(page) {
  await page.goto(`${BASE_URL}${USERS_PATH}`);
}

test.describe('Kelola User & Role', () => {
  // Scenario: "Kelola User & Role — Tambah User berhasil"
  test('menambah user baru dengan role dan Business Unit lalu menampilkannya di tabel', async ({ page }) => {
    await login(page, 'urtest-admin01', PASSWORD);
    await gotoUsers(page);

    await page.locator('button', { hasText: 'Tambah User' }).click();

    const uniqueSuffix = Date.now();
    const username = `urtest-new-${uniqueSuffix}`;
    await page.locator('#username').fill(username);
    await page.locator('#name').fill('Andi Wijaya');
    await page.locator('#role').selectOption({ label: 'Supervisor' });
    await page.locator('#business_unit_id').selectOption({ label: BUSINESS_UNIT_NAME });
    await page.locator('#password').fill(PASSWORD);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    const row = page.locator('.kc-table__row', { hasText: username });
    await expect(row).toBeVisible();
    await expect(row).toContainText('Supervisor');
  });

  // Scenario: "Kelola User & Role — Edit User berhasil"
  test('mengedit nama dan role user yang sudah ada', async ({ page }) => {
    await login(page, 'urtest-admin01', PASSWORD);
    await gotoUsers(page);

    const row = page.locator('.kc-table__row', { hasText: 'urtest-existing01' });
    await row.locator('button', { hasText: 'Edit' }).click();

    const newName = `Nama Sesudah Edit ${Date.now()}`;
    await page.locator('#name').fill(newName);
    await page.locator('#role').selectOption({ label: 'Mill management' });
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    const updatedRow = page.locator('.kc-table__row', { hasText: 'urtest-existing01' });
    await expect(updatedRow).toContainText(newName);
  });

  // Scenario: "Kelola User & Role — Username duplikat"
  test('menampilkan pesan validasi saat username sudah dipakai', async ({ page }) => {
    await login(page, 'urtest-admin01', PASSWORD);
    await gotoUsers(page);

    await page.locator('button', { hasText: 'Tambah User' }).click();
    await page.locator('#username').fill('urtest-duplicate01');
    await page.locator('#name').fill('User Lain');
    await page.locator('#role').selectOption({ label: 'Supervisor' });
    await page.locator('#business_unit_id').selectOption({ label: BUSINESS_UNIT_NAME });
    await page.locator('#password').fill(PASSWORD);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-form-field__error')).toContainText('sudah digunakan');
  });

  // Scenario: "Kelola User & Role — Business Unit wajib untuk role non-Admin"
  test('menampilkan pesan validasi saat Business Unit tidak dipilih untuk role selain Admin', async ({ page }) => {
    await login(page, 'urtest-admin01', PASSWORD);
    await gotoUsers(page);

    await page.locator('button', { hasText: 'Tambah User' }).click();
    await page.locator('#username').fill(`urtest-nobu-${Date.now()}`);
    await page.locator('#name').fill('Operator Baru');
    await page.locator('#role').selectOption({ label: 'Operator' });
    await page.locator('#password').fill(PASSWORD);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-form-field__error')).toContainText('Business Unit wajib dipilih');
  });

  // Scenario: "Kelola User & Role — Admin menonaktifkan akun sendiri ditolak"
  test('menolak Admin menonaktifkan akun miliknya sendiri', async ({ page }) => {
    await login(page, 'urtest-admin01', PASSWORD);
    await gotoUsers(page);

    const ownRow = page.locator('.kc-table__row', { hasText: 'urtest-admin01' });
    await expect(ownRow.locator('button', { hasText: 'Nonaktifkan' })).toBeDisabled();
  });

  // Scenario: "Kelola User & Role — Nonaktifkan user lain berhasil"
  test('menonaktifkan user lain dan status berubah di tabel', async ({ page }) => {
    await login(page, 'urtest-admin01', PASSWORD);
    await gotoUsers(page);

    const row = page.locator('.kc-table__row', { hasText: 'urtest-other01' });
    await row.locator('button', { hasText: 'Nonaktifkan' }).click();

    await expect(row.locator('.kc-badge')).toContainText('Nonaktif');
  });

  // Scenario: "Kelola User & Role — Akses ditolak untuk non-Admin"
  test('menolak akses layar Kelola User & Role untuk non-Admin', async ({ page }) => {
    await login(page, 'urtest-nonadmin01', PASSWORD);
    await gotoUsers(page);

    await expect(page.locator('body')).toContainText(/403|tidak memiliki akses|forbidden/i);
  });
});
