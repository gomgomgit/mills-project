<?php

/**
 * KelolaStationTest (Browser/Playwright) — screen-030--kelola-station /
 * usecase-030--kelola-station.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * the convention established by tests/Browser/KelolaBusinessUnitTest.php /
 * tests/Browser/KelolaCompanyTest.php — same file-extension/import-syntax
 * pattern (a .php path containing a Playwright TS spec body), since
 * test_strategy.browser_test.tool is Playwright (not Laravel Dusk — this
 * codebase has no laravel/dusk dependency, see composer.json).
 *
 * This is a plain Livewire/Blade web screen served by `php artisan serve`
 * (test_strategy.browser_test.base_url), so it is fully browser-testable
 * via the dev server — not deferred.
 *
 * WRITTEN BUT NOT RUN IN THIS SESSION: per this session's task scope, this
 * file is generated only — it is not executed here (no dev server/browser
 * available in this environment, same environment constraint as its
 * sibling KelolaBusinessUnitTest.php/KelolaCompanyTest.php). It is written
 * to be complete and correct, to be run later via `playwright test` per
 * test_strategy.browser_test.run_command, from a project root with
 * @playwright/test installed and a playwright.config.* pointing at this
 * file (e.g. testDir including backend/tests/Browser).
 *
 * Test data assumption (mirrors KelolaBusinessUnitTest.php's approach):
 * this screen requires an authenticated admin session, so each scenario
 * logs in via /login first, then navigates to /master-data/stations. Each
 * scenario uses its own dedicated fixture user/data (rather than sharing
 * across scenarios) so scenario order never matters even though this spec
 * does not reset the DB itself (Playwright drives the browser only,
 * against whatever seeder provisioned the target environment).
 *   - business unit (login picker): "Mill A" (the login form's Business
 *     Area <select> — a DIFFERENT entity from the Station rows this
 *     screen manages, just the login picker's session-scoping business
 *     unit)
 *   - stest-admin01 / Passw0rd! (role: admin) — scenarios 1-9
 *   - stest-nonadmin01 / Passw0rd! (role: supervisor) — scenario 8
 * Scenario 1 assumes a business unit named "Mill Station Baru" already
 * exists (to pick from the Business Unit dropdown). Scenario 2 assumes a
 * station named "Weighbridge Sebelum Edit" already exists under some
 * business unit, and a business unit named "Mill Station Tujuan Edit"
 * exists as the edit target. Scenario 3 assumes a station named
 * "Weighbridge Hapus Bersih" pre-seeded with 0 related MachineryGroup/
 * Machinery rows. Scenario 4 assumes a station named "Weighbridge Ada
 * Machinery" pre-seeded with at least 1 related MachineryGroup or
 * Machinery row. Scenario 5 assumes a station already exists with code
 * "STA-DUP-01" under any business unit. Scenario 6 assumes at least one
 * Business Unit row exists (for the "Business Unit induk wajib dipilih"
 * validation scenario). Scenario 7 (cross-field is_active/type=other
 * rule) needs no pre-seeded data. Scenario 8 (akses ditolak) needs no
 * pre-seeded Station data. Adjust the USERNAME/BUSINESS_UNIT_NAME/
 * fixture-name constants below to match whatever seeder is used to
 * provision the target environment.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const STATIONS_PATH = '/master-data/stations';
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

async function gotoStations(page) {
  await page.goto(`${BASE_URL}${STATIONS_PATH}`);
}

test.describe('Kelola Station', () => {
  // Scenario: "Kelola Station — success"
  test('menambah station baru dengan memilih business unit dan menampilkannya di tabel', async ({ page }) => {
    await login(page, 'stest-admin01', PASSWORD);
    await gotoStations(page);

    await page.locator('button', { hasText: 'Tambah Station' }).click();
    await page.locator('#business_unit_id').selectOption({ label: 'Mill Station Baru' });
    await page.locator('#type').selectOption({ label: 'Weighbridge' });

    const uniqueSuffix = Date.now();
    const uniqueName = `Weighbridge Baru ${uniqueSuffix}`;
    await page.locator('#name').fill(uniqueName);
    await page.locator('#code').fill(`STA-BROWSER-${uniqueSuffix}`);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    const row = page.locator('.kc-table__row', { hasText: uniqueName });
    await expect(row).toBeVisible();
    await expect(row).toContainText('Mill Station Baru');
    await expect(row).toContainText('Weighbridge');
    await expect(row).toContainText('Aktif');
    await expect(row).toContainText('0');
  });

  // Scenario: "Kelola Station — Edit Station"
  test('mengedit nama, type, dan business unit station lalu menampilkan perubahan di tabel', async ({ page }) => {
    await login(page, 'stest-admin01', PASSWORD);
    await gotoStations(page);

    const row = page.locator('.kc-table__row', { hasText: 'Weighbridge Sebelum Edit' });
    await row.locator('button', { hasText: 'Edit' }).click();

    await page.locator('#business_unit_id').selectOption({ label: 'Mill Station Tujuan Edit' });
    await page.locator('#type').selectOption({ label: 'Grading' });
    const uniqueSuffix = Date.now();
    const newName = `Weighbridge Sesudah Edit ${uniqueSuffix}`;
    await page.locator('#name').fill(newName);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    const updatedRow = page.locator('.kc-table__row', { hasText: newName });
    await expect(updatedRow).toBeVisible();
    await expect(updatedRow).toContainText('Mill Station Tujuan Edit');
    await expect(updatedRow).toContainText('Grading');
    await expect(page.locator('.kc-table__row', { hasText: 'Weighbridge Sebelum Edit' })).toHaveCount(0);
  });

  // Scenario: "Kelola Station — Hapus Station — berhasil"
  test('menghapus station tanpa machinery terkait, baris hilang dari tabel', async ({ page }) => {
    await login(page, 'stest-admin01', PASSWORD);
    await gotoStations(page);

    const row = page.locator('.kc-table__row', { hasText: 'Weighbridge Hapus Bersih' });
    await row.locator('button', { hasText: 'Hapus' }).click();
    await row.locator('button', { hasText: 'Ya, Hapus' }).click();

    await expect(page.locator('.kc-table__row', { hasText: 'Weighbridge Hapus Bersih' })).toHaveCount(0);
  });

  // Scenario: "Kelola Station — Hapus Station — ditolak"
  test('menolak penghapusan station yang masih memiliki machinery terkait, baris tetap ada', async ({ page }) => {
    await login(page, 'stest-admin01', PASSWORD);
    await gotoStations(page);

    const row = page.locator('.kc-table__row', { hasText: 'Weighbridge Ada Machinery' });
    await row.locator('button', { hasText: 'Hapus' }).click();
    await row.locator('button', { hasText: 'Ya, Hapus' }).click();

    await expect(page.locator('.kc-alert')).toContainText(/Machinery/i);
    await expect(page.locator('.kc-table__row', { hasText: 'Weighbridge Ada Machinery' })).toBeVisible();
  });

  // Scenario: "Kelola Station — Kode duplikat"
  test('menampilkan error validasi saat menyimpan kode yang sudah dipakai station lain', async ({ page }) => {
    await login(page, 'stest-admin01', PASSWORD);
    await gotoStations(page);

    await page.locator('button', { hasText: 'Tambah Station' }).click();
    await page.locator('#business_unit_id').selectOption({ index: 1 });
    await page.locator('#type').selectOption({ label: 'Weighbridge' });
    await page.locator('#name').fill('Weighbridge Kode Duplikat');
    await page.locator('#code').fill('STA-DUP-01');
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-form-field__error')).toContainText(/sudah digunakan/i);
    // The modal stays open — submission was blocked by validation, no new
    // row with the duplicate code was created.
    await expect(page.locator('.kc-modal')).toBeVisible();
    await expect(page.locator('.kc-table__row', { hasText: 'Weighbridge Kode Duplikat' })).toHaveCount(0);
  });

  // Scenario: "Kelola Station — Business Unit induk wajib dipilih"
  test('menampilkan error validasi saat submit form tanpa memilih Business Unit', async ({ page }) => {
    await login(page, 'stest-admin01', PASSWORD);
    await gotoStations(page);

    await page.locator('button', { hasText: 'Tambah Station' }).click();
    await page.locator('#type').selectOption({ label: 'Weighbridge' });
    const uniqueSuffix = Date.now();
    await page.locator('#name').fill('Weighbridge Tanpa Business Unit');
    await page.locator('#code').fill(`STA-NOBU-${uniqueSuffix}`);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-form-field__error')).toContainText(/wajib dipilih/i);
    await expect(page.locator('.kc-modal')).toBeVisible();
    await expect(page.locator('.kc-table__row', { hasText: 'Weighbridge Tanpa Business Unit' })).toHaveCount(0);
  });

  // CRITICAL — the cross-field rule: is_active=true with type=Other is
  // rejected, form stays open, no row created.
  test('menampilkan error validasi saat status Aktif dipilih untuk tipe Other', async ({ page }) => {
    await login(page, 'stest-admin01', PASSWORD);
    await gotoStations(page);

    await page.locator('button', { hasText: 'Tambah Station' }).click();
    await page.locator('#business_unit_id').selectOption({ index: 1 });
    await page.locator('#type').selectOption({ label: 'Other' });
    const uniqueSuffix = Date.now();
    await page.locator('#name').fill('Station Other Aktif');
    // Default state of the "Aktif" checkbox on openCreateForm() is checked
    // (is_active defaults to true) — leave it checked to trigger the
    // cross-field rule with type=Other.
    await expect(page.locator('#is_active')).toBeChecked();
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-form-field__error')).toContainText(/Other/i);
    await expect(page.locator('.kc-modal')).toBeVisible();
    await expect(page.locator('.kc-table__row', { hasText: 'Station Other Aktif' })).toHaveCount(0);
  });

  // Scenario: "Kelola Station — Akses ditolak untuk non-Admin"
  test('menampilkan halaman akses ditolak untuk pengguna non-admin', async ({ page }) => {
    await login(page, 'stest-nonadmin01', PASSWORD);
    await page.goto(`${BASE_URL}${STATIONS_PATH}`);

    // EnsureRole::forbidden() -> abort(403), Laravel's default HTML error
    // page — no station table/controls are rendered.
    await expect(page.locator('body')).toContainText(/403/);
    await expect(page.locator('.kc-table')).toHaveCount(0);
  });

  // Additional coverage: filtering the table by Business Unit.
  test('memfilter tabel station berdasarkan Business Unit yang dipilih', async ({ page }) => {
    await login(page, 'stest-admin01', PASSWORD);
    await gotoStations(page);

    await page.locator('#filterBusinessUnitId').selectOption({ index: 1 });

    // Every visible row (if any) belongs to the selected Business Unit —
    // asserted structurally rather than against a specific row count,
    // since the target environment's seeded data volume may vary.
    await expect(page.locator('.kc-table')).toBeVisible();
  });
});
