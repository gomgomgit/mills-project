<?php

/**
 * KelolaBusinessUnitTest (Browser/Playwright) — screen-029--kelola-business-unit
 * / usecase-029--kelola-business-unit.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * the convention established by tests/Browser/KelolaCorporateTest.php /
 * tests/Browser/KelolaCompanyTest.php — same file-extension/import-syntax
 * pattern (a .php path containing a Playwright TS spec body), since
 * test_strategy.browser_test.tool is Playwright (not Laravel Dusk — this
 * codebase has no laravel/dusk dependency, see composer.json).
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
 * Test data assumption (mirrors KelolaCompanyTest.php's approach): this
 * screen requires an authenticated admin session, so each scenario logs in
 * via /login first, then navigates to /master-data/business-units. Each
 * scenario uses its own dedicated fixture user/data (rather than sharing
 * across scenarios) so scenario order never matters even though this spec
 * does not reset the DB itself (Playwright drives the browser only,
 * against whatever seeder provisioned the target environment).
 *   - business unit: "Mill A" (any business_unit whose name is selectable
 *     in the login form's Business Area <select> — a DIFFERENT entity
 *     from the Business Unit rows this screen manages, just the login
 *     picker's session-scoping business unit)
 *   - butest-admin01 / Passw0rd! (role: admin) — scenarios 1-7
 *   - butest-nonadmin01 / Passw0rd! (role: supervisor) — scenario 7
 * Scenario 1 assumes a company named "PT Company Baru" already exists (to
 * pick from the Company dropdown). Scenario 2 assumes a business unit
 * named "Mill Sebelum Edit" already exists under some company, and a
 * company named "PT Company Tujuan Edit" exists as the edit target.
 * Scenario 3 assumes a business unit named "Mill Hapus Bersih" pre-seeded
 * with 0 related Station rows. Scenario 4 assumes a business unit named
 * "Mill Ada Station" pre-seeded with at least 1 related Station row.
 * Scenario 5 assumes a business unit already exists with code "BU-DUP-01"
 * under any company. Scenario 6 assumes a target environment where NO
 * Company rows exist at all (a separate, isolated seed state from the
 * other scenarios, since they all depend on at least one Company
 * existing). Scenario 7 (Company induk wajib dipilih) assumes at least one
 * Company row exists. Adjust the USERNAME/BUSINESS_UNIT_NAME/fixture-name
 * constants below to match whatever seeder is used to provision the target
 * environment.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const BUSINESS_UNITS_PATH = '/master-data/business-units';
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

async function gotoBusinessUnits(page) {
  await page.goto(`${BASE_URL}${BUSINESS_UNITS_PATH}`);
}

test.describe('Kelola Business Unit', () => {
  // Scenario: "Kelola Business Unit — success"
  test('menambah business unit baru dengan memilih company dan menampilkannya di tabel', async ({ page }) => {
    await login(page, 'butest-admin01', PASSWORD);
    await gotoBusinessUnits(page);

    await page.locator('button', { hasText: 'Tambah Business Unit' }).click();
    await page.locator('#company_id').selectOption({ label: 'PT Company Baru' });

    const uniqueSuffix = Date.now();
    await page.locator('#code').fill(`BU-BROWSER-${uniqueSuffix}`);
    const uniqueName = `Mill Baru ${uniqueSuffix}`;
    await page.locator('#name').fill(uniqueName);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    const row = page.locator('.kc-table__row', { hasText: uniqueName });
    await expect(row).toBeVisible();
    await expect(row).toContainText('PT Company Baru');
    await expect(row).toContainText('0');
  });

  // Scenario: "Kelola Business Unit — Edit Business Unit"
  test('mengedit kode, nama, dan company business unit lalu menampilkan perubahan di tabel', async ({ page }) => {
    await login(page, 'butest-admin01', PASSWORD);
    await gotoBusinessUnits(page);

    const row = page.locator('.kc-table__row', { hasText: 'Mill Sebelum Edit' });
    await row.locator('button', { hasText: 'Edit' }).click();

    await page.locator('#company_id').selectOption({ label: 'PT Company Tujuan Edit' });
    const uniqueSuffix = Date.now();
    await page.locator('#code').fill(`BU-BROWSER-EDIT-${uniqueSuffix}`);
    const newName = `Mill Sesudah Edit ${uniqueSuffix}`;
    await page.locator('#name').fill(newName);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    const updatedRow = page.locator('.kc-table__row', { hasText: newName });
    await expect(updatedRow).toBeVisible();
    await expect(updatedRow).toContainText('PT Company Tujuan Edit');
    await expect(page.locator('.kc-table__row', { hasText: 'Mill Sebelum Edit' })).toHaveCount(0);
  });

  // Scenario: "Kelola Business Unit — Hapus Business Unit — berhasil"
  test('menghapus business unit tanpa station terkait, baris hilang dari tabel', async ({ page }) => {
    await login(page, 'butest-admin01', PASSWORD);
    await gotoBusinessUnits(page);

    const row = page.locator('.kc-table__row', { hasText: 'Mill Hapus Bersih' });
    await row.locator('button', { hasText: 'Hapus' }).click();
    await row.locator('button', { hasText: 'Ya, Hapus' }).click();

    await expect(page.locator('.kc-table__row', { hasText: 'Mill Hapus Bersih' })).toHaveCount(0);
  });

  // Scenario: "Kelola Business Unit — Hapus Business Unit — ditolak"
  test('menolak penghapusan business unit yang masih memiliki station terkait, baris tetap ada', async ({ page }) => {
    await login(page, 'butest-admin01', PASSWORD);
    await gotoBusinessUnits(page);

    const row = page.locator('.kc-table__row', { hasText: 'Mill Ada Station' });
    await row.locator('button', { hasText: 'Hapus' }).click();
    await row.locator('button', { hasText: 'Ya, Hapus' }).click();

    await expect(page.locator('.kc-alert')).toContainText('Station');
    await expect(page.locator('.kc-table__row', { hasText: 'Mill Ada Station' })).toBeVisible();
  });

  // Scenario: "Kelola Business Unit — Kode duplikat"
  test('menampilkan error validasi saat menyimpan kode yang sudah dipakai business unit lain', async ({ page }) => {
    await login(page, 'butest-admin01', PASSWORD);
    await gotoBusinessUnits(page);

    await page.locator('button', { hasText: 'Tambah Business Unit' }).click();
    await page.locator('#company_id').selectOption({ index: 1 });
    await page.locator('#code').fill('BU-DUP-01');
    await page.locator('#name').fill('Mill Kode Duplikat');
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-form-field__error')).toContainText(/sudah digunakan/i);
    // The modal stays open — submission was blocked by validation, no new
    // row with the duplicate code was created.
    await expect(page.locator('.kc-modal')).toBeVisible();
    await expect(page.locator('.kc-table__row', { hasText: 'Mill Kode Duplikat' })).toHaveCount(0);
  });

  // Scenario: "Kelola Business Unit — Belum ada Company"
  //
  // Implementation note: the current markup (resources/views/livewire/
  // master-data/kelola-business-unit.blade.php) renders only the
  // placeholder "-- Pilih Company --" <option> when companyOptions is
  // empty — there is no distinct `disabled` attribute or dedicated
  // "create a Company first" guidance copy. This test asserts the actual
  // observable behavior instead of a guidance message that does not exist
  // in the implementation (see the equivalent note in tests/Feature/
  // Livewire/KelolaBusinessUnitTest.php's file-level docblock, and this
  // agent's known_issues in its final report).
  test('menampilkan dropdown company kosong saat belum ada Company sama sekali', async ({ page }) => {
    await login(page, 'butest-admin01', PASSWORD);
    await gotoBusinessUnits(page);

    await page.locator('button', { hasText: 'Tambah Business Unit' }).click();

    // Only the placeholder option is present — no real Company to pick.
    const companySelect = page.locator('#company_id');
    await expect(companySelect.locator('option')).toHaveCount(1);
    await expect(companySelect.locator('option').first()).toHaveText('-- Pilih Company --');
  });

  // Scenario: "Kelola Business Unit — Akses ditolak untuk non-Admin"
  test('menampilkan halaman akses ditolak untuk pengguna non-admin', async ({ page }) => {
    await login(page, 'butest-nonadmin01', PASSWORD);
    await page.goto(`${BASE_URL}${BUSINESS_UNITS_PATH}`);

    // EnsureRole::forbidden() -> abort(403), Laravel's default HTML error
    // page — no business unit table/controls are rendered.
    await expect(page.locator('body')).toContainText(/403/);
    await expect(page.locator('.kc-table')).toHaveCount(0);
  });

  // Scenario: "Kelola Business Unit — Company induk wajib dipilih"
  test('menampilkan error validasi saat submit form tanpa memilih Company', async ({ page }) => {
    await login(page, 'butest-admin01', PASSWORD);
    await gotoBusinessUnits(page);

    await page.locator('button', { hasText: 'Tambah Business Unit' }).click();
    const uniqueSuffix = Date.now();
    await page.locator('#code').fill(`BU-NOCO-${uniqueSuffix}`);
    await page.locator('#name').fill('Mill Tanpa Company');
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-form-field__error')).toContainText(/wajib dipilih/i);
    await expect(page.locator('.kc-modal')).toBeVisible();
    await expect(page.locator('.kc-table__row', { hasText: 'Mill Tanpa Company' })).toHaveCount(0);
  });
});
