<?php

/**
 * KelolaCorporateTest (Browser/Playwright) — screen-027--kelola-corporate /
 * usecase-027--kelola-corporate.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * the convention established by tests/Browser/DataBrowserWeighbridgeTest.php
 * and tests/Browser/ChangePasswordWebTest.php — same file-extension/
 * import-syntax pattern (a .php path containing a Playwright TS spec body),
 * since test_strategy.browser_test.tool is Playwright (not Laravel Dusk —
 * this codebase has no laravel/dusk dependency, see composer.json).
 *
 * This is a plain Livewire/Blade web screen served by `php artisan serve`
 * (test_strategy.browser_test.base_url), so it is fully browser-testable
 * via the dev server — not deferred.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT: there is no dev server or
 * browser available in this sandbox. This file is written to be complete
 * and correct, to be run later via `playwright test` per
 * test_strategy.browser_test.run_command, from a project root with
 * @playwright/test installed and a playwright.config.* pointing at this
 * file (e.g. testDir including backend/tests/Browser).
 *
 * Test data assumption (mirrors ChangePasswordWebTest.php's approach): this
 * screen requires an authenticated admin session, so each scenario logs in
 * via /login first, then navigates to /master-data/corporates. Each
 * scenario uses its own dedicated fixture user/data (rather than sharing
 * across scenarios) so scenario order never matters even though this spec
 * does not reset the DB itself (Playwright drives the browser only,
 * against whatever seeder provisioned the target environment).
 *   - business unit: "Mill A" (any business_unit whose name is selectable
 *     in the login form's Business Area <select>)
 *   - corptest-admin01 / Passw0rd! (role: admin) — scenarios 1, 2, 3, 4, 5
 *   - corptest-nonadmin01 / Passw0rd! (role: supervisor) — scenario 6
 * Scenario 3 assumes a corporate named "PT Hapus Bersih" pre-seeded with 0
 * related companies. Scenario 4 assumes a corporate named "PT Ada
 * Company" pre-seeded with at least 1 related Company row. Scenario 5
 * assumes a corporate named "PT Nama Duplikat" already exists. Scenario 2
 * assumes a corporate named "PT Sebelum Edit" already exists. Adjust the
 * USERNAME/BUSINESS_UNIT_NAME/fixture-name constants below to match
 * whatever seeder is used to provision the target environment.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const CORPORATES_PATH = '/master-data/corporates';
const BUSINESS_UNIT_NAME = 'Mill A';
const PASSWORD = 'Passw0rd!';

async function login(page, username, password) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.locator('#business_unit_id').selectOption({ label: BUSINESS_UNIT_NAME });
  await page.locator('button[type="submit"]').click();
  // Redirected away from /login once the session is established.
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
}

async function gotoCorporates(page) {
  await page.goto(`${BASE_URL}${CORPORATES_PATH}`);
}

test.describe('Kelola Corporate', () => {
  // Scenario 1: "Kelola Corporate — success"
  test('menambah corporate baru dan menampilkannya di tabel', async ({ page }) => {
    await login(page, 'corptest-admin01', PASSWORD);
    await gotoCorporates(page);

    await page.locator('button', { hasText: 'Tambah Corporate' }).click();

    const uniqueName = `PT Baru ${Date.now()}`;
    await page.locator('#name').fill(uniqueName);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-table__row', { hasText: uniqueName })).toBeVisible();
  });

  // Scenario 2: "Kelola Corporate — Edit Corporate"
  test('mengedit nama corporate dan menampilkan perubahan di tabel', async ({ page }) => {
    await login(page, 'corptest-admin01', PASSWORD);
    await gotoCorporates(page);

    const row = page.locator('.kc-table__row', { hasText: 'PT Sebelum Edit' });
    await row.locator('button', { hasText: 'Edit' }).click();

    const newName = `PT Sesudah Edit ${Date.now()}`;
    await page.locator('#name').fill(newName);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-table__row', { hasText: newName })).toBeVisible();
    await expect(page.locator('.kc-table__row', { hasText: 'PT Sebelum Edit' })).toHaveCount(0);
  });

  // Scenario 3: "Kelola Corporate — Hapus Corporate — berhasil"
  test('menghapus corporate tanpa company terkait, baris hilang dari tabel', async ({ page }) => {
    await login(page, 'corptest-admin01', PASSWORD);
    await gotoCorporates(page);

    const row = page.locator('.kc-table__row', { hasText: 'PT Hapus Bersih' });
    await row.locator('button', { hasText: 'Hapus' }).click();
    await row.locator('button', { hasText: 'Ya, Hapus' }).click();

    await expect(page.locator('.kc-table__row', { hasText: 'PT Hapus Bersih' })).toHaveCount(0);
  });

  // Scenario 4: "Kelola Corporate — Hapus Corporate — ditolak"
  test('menolak penghapusan corporate yang masih memiliki company, baris tetap ada', async ({ page }) => {
    await login(page, 'corptest-admin01', PASSWORD);
    await gotoCorporates(page);

    const row = page.locator('.kc-table__row', { hasText: 'PT Ada Company' });
    await row.locator('button', { hasText: 'Hapus' }).click();
    await row.locator('button', { hasText: 'Ya, Hapus' }).click();

    await expect(page.locator('.kc-alert')).toContainText('Company');
    await expect(page.locator('.kc-table__row', { hasText: 'PT Ada Company' })).toBeVisible();
  });

  // Scenario 5: "Kelola Corporate — Nama duplikat"
  test('menampilkan error validasi saat menyimpan nama yang sudah dipakai', async ({ page }) => {
    await login(page, 'corptest-admin01', PASSWORD);
    await gotoCorporates(page);

    await page.locator('button', { hasText: 'Tambah Corporate' }).click();
    await page.locator('#name').fill('PT Nama Duplikat');
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-form-field__error')).toContainText(/sudah digunakan/i);
    // The modal stays open — submission was blocked by validation, no
    // second row with the duplicate name was created.
    await expect(page.locator('.kc-modal')).toBeVisible();
    await expect(page.locator('.kc-table__row', { hasText: 'PT Nama Duplikat' })).toHaveCount(1);
  });

  // Scenario 6: "Kelola Corporate — akses ditolak untuk selain Admin"
  test('menampilkan halaman akses ditolak untuk pengguna non-admin', async ({ page }) => {
    await login(page, 'corptest-nonadmin01', PASSWORD);
    await page.goto(`${BASE_URL}${CORPORATES_PATH}`);

    // EnsureRole::forbidden() -> abort(403), Laravel's default HTML error
    // page — no corporate table/controls are rendered.
    await expect(page.locator('body')).toContainText(/403/);
    await expect(page.locator('.kc-table')).toHaveCount(0);
  });
});
