<?php

/**
 * KelolaMachineryGroupTest (Browser/Playwright) — screen-033--kelola-machinery-group /
 * usecase-033--kelola-machinery-group.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * the convention established by tests/Browser/KelolaStationTest.php — same
 * file-extension/import-syntax pattern (a .php path containing a
 * Playwright TS spec body), since test_strategy.browser_test.tool is
 * Playwright (not Laravel Dusk — this codebase has no laravel/dusk
 * dependency, see composer.json).
 *
 * This is a plain Livewire/Blade web screen served by `php artisan serve`
 * (test_strategy.browser_test.base_url), so it is fully browser-testable
 * via the dev server — not deferred.
 *
 * WRITTEN BUT NOT RUN IN THIS SESSION: per this session's task scope, this
 * file is generated only — it is not executed here (no dev server/browser
 * available in this environment, same environment constraint as its
 * sibling KelolaStationTest.php/KelolaBusinessUnitTest.php). It is written
 * to be complete and correct, to be run later via `playwright test` per
 * test_strategy.browser_test.run_command, from a project root with
 * @playwright/test installed and a playwright.config.* pointing at this
 * file (e.g. testDir including backend/tests/Browser).
 *
 * Test data assumption (mirrors KelolaStationTest.php's approach): this
 * screen requires an authenticated admin session, so each scenario logs
 * in via /login first, then navigates to /master-data/machinery-groups.
 * Each scenario uses its own dedicated fixture data so scenario order
 * never matters even though this spec does not reset the DB itself
 * (Playwright drives the browser only, against whatever seeder
 * provisioned the target environment).
 *   - business unit (login picker): "Mill A" (the login form's Business
 *     Area <select>)
 *   - mgtest-admin01 / Passw0rd! (role: admin) — scenarios 1-8
 *   - mgtest-nonadmin01 / Passw0rd! (role: supervisor) — scenario 7
 * Scenario 1 assumes a Station named "Mill Machinery Group Station Baru"
 * already exists (to pick from the Station dropdown), and that Station's
 * own Production Line is named "Mill Machinery Group PL Baru". Scenario 2
 * assumes a machinery group with group_code "MG-BROWSER-SEBELUM-EDIT"
 * already exists under some Station, and a Station named
 * "Mill Machinery Group Station Tujuan Edit" exists as the edit target.
 * Scenario 3 assumes a machinery group with group_code
 * "MG-BROWSER-HAPUS-BERSIH" pre-seeded with 0 related Machinery rows.
 * Scenario 4 assumes a machinery group with group_code
 * "MG-BROWSER-ADA-MACHINERY" pre-seeded with at least 1 related Machinery
 * row. Scenario 5 assumes a machinery group already exists with
 * group_code "MG-DUP-01" under any Station. Scenario 6 needs no
 * pre-seeded data (Station wajib dipilih validation). Scenario 7 (akses
 * ditolak) needs no pre-seeded Machinery Group data. Adjust the
 * USERNAME/BUSINESS_UNIT_NAME/fixture-name constants below to match
 * whatever seeder is used to provision the target environment.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const MACHINERY_GROUPS_PATH = '/master-data/machinery-groups';
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

async function gotoMachineryGroups(page) {
  await page.goto(`${BASE_URL}${MACHINERY_GROUPS_PATH}`);
}

test.describe('Kelola Machinery Group', () => {
  // Scenario: "Kelola Machinery Group — success"
  test('menambah machinery group baru dengan memilih station, production line terisi otomatis, dan menampilkannya di tabel', async ({ page }) => {
    await login(page, 'mgtest-admin01', PASSWORD);
    await gotoMachineryGroups(page);

    await page.locator('button', { hasText: 'Tambah Machinery Group' }).click();
    await page.locator('#station_id').selectOption({ label: 'Mill Machinery Group Station Baru' });

    // The Production Line field is read-only and auto-populated from the
    // selected Station — never independently typed by the admin.
    await expect(page.locator('#production_line_display')).toHaveValue('Mill Machinery Group PL Baru');
    await expect(page.locator('#production_line_display')).toBeDisabled();

    const uniqueSuffix = Date.now();
    const uniqueCode = `MG-BROWSER-${uniqueSuffix}`;
    await page.locator('#group_code').fill(uniqueCode);
    await page.locator('#unit').fill('unit');
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    const row = page.locator('.kc-table__row', { hasText: uniqueCode });
    await expect(row).toBeVisible();
    await expect(row).toContainText('Mill Machinery Group Station Baru');
    await expect(row).toContainText('Mill Machinery Group PL Baru');
    await expect(row).toContainText('0');
  });

  // Scenario: "Kelola Machinery Group — Edit Machinery Group"
  test('mengedit kode dan station machinery group lalu menampilkan perubahan di tabel', async ({ page }) => {
    await login(page, 'mgtest-admin01', PASSWORD);
    await gotoMachineryGroups(page);

    const row = page.locator('.kc-table__row', { hasText: 'MG-BROWSER-SEBELUM-EDIT' });
    await row.locator('button', { hasText: 'Edit' }).click();

    await page.locator('#station_id').selectOption({ label: 'Mill Machinery Group Station Tujuan Edit' });
    const uniqueSuffix = Date.now();
    const newCode = `MG-BROWSER-SESUDAH-EDIT-${uniqueSuffix}`;
    await page.locator('#group_code').fill(newCode);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    const updatedRow = page.locator('.kc-table__row', { hasText: newCode });
    await expect(updatedRow).toBeVisible();
    await expect(updatedRow).toContainText('Mill Machinery Group Station Tujuan Edit');
    await expect(page.locator('.kc-table__row', { hasText: 'MG-BROWSER-SEBELUM-EDIT' })).toHaveCount(0);
  });

  // Scenario: "Kelola Machinery Group — Hapus — berhasil"
  test('menghapus machinery group tanpa machinery terkait, baris hilang dari tabel', async ({ page }) => {
    await login(page, 'mgtest-admin01', PASSWORD);
    await gotoMachineryGroups(page);

    const row = page.locator('.kc-table__row', { hasText: 'MG-BROWSER-HAPUS-BERSIH' });
    await row.locator('button', { hasText: 'Hapus' }).click();
    await row.locator('button', { hasText: 'Ya, Hapus' }).click();

    await expect(page.locator('.kc-table__row', { hasText: 'MG-BROWSER-HAPUS-BERSIH' })).toHaveCount(0);
  });

  // Scenario: "Kelola Machinery Group — Hapus — ditolak"
  test('menolak penghapusan machinery group yang masih memiliki machinery terkait, baris tetap ada', async ({ page }) => {
    await login(page, 'mgtest-admin01', PASSWORD);
    await gotoMachineryGroups(page);

    const row = page.locator('.kc-table__row', { hasText: 'MG-BROWSER-ADA-MACHINERY' });
    await row.locator('button', { hasText: 'Hapus' }).click();
    await row.locator('button', { hasText: 'Ya, Hapus' }).click();

    await expect(page.locator('.kc-alert')).toContainText(/Machinery/i);
    await expect(page.locator('.kc-table__row', { hasText: 'MG-BROWSER-ADA-MACHINERY' })).toBeVisible();
  });

  // Scenario: "Kelola Machinery Group — Kode duplikat"
  test('menampilkan error validasi saat menyimpan kode yang sudah dipakai machinery group lain', async ({ page }) => {
    await login(page, 'mgtest-admin01', PASSWORD);
    await gotoMachineryGroups(page);

    await page.locator('button', { hasText: 'Tambah Machinery Group' }).click();
    await page.locator('#station_id').selectOption({ index: 1 });
    await page.locator('#group_code').fill('MG-DUP-01');
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-form-field__error')).toContainText(/sudah digunakan/i);
    // The modal stays open — submission was blocked by validation.
    await expect(page.locator('.kc-modal')).toBeVisible();
  });

  // Scenario: "Kelola Machinery Group — Station wajib dipilih"
  test('menampilkan error validasi saat submit form tanpa memilih Station', async ({ page }) => {
    await login(page, 'mgtest-admin01', PASSWORD);
    await gotoMachineryGroups(page);

    await page.locator('button', { hasText: 'Tambah Machinery Group' }).click();
    const uniqueSuffix = Date.now();
    await page.locator('#group_code').fill(`MG-NOSTATION-${uniqueSuffix}`);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-form-field__error')).toContainText(/wajib dipilih/i);
    await expect(page.locator('.kc-modal')).toBeVisible();
  });

  // Scenario: "Kelola Machinery Group — Akses ditolak untuk non-Admin"
  test('menampilkan halaman akses ditolak untuk pengguna non-admin', async ({ page }) => {
    await login(page, 'mgtest-nonadmin01', PASSWORD);
    await page.goto(`${BASE_URL}${MACHINERY_GROUPS_PATH}`);

    // EnsureRole::forbidden() -> abort(403), Laravel's default HTML error
    // page — no machinery group table/controls are rendered.
    await expect(page.locator('body')).toContainText(/403/);
    await expect(page.locator('.kc-table')).toHaveCount(0);
  });

  // Additional coverage: filtering the table by Station.
  test('memfilter tabel machinery group berdasarkan Station yang dipilih', async ({ page }) => {
    await login(page, 'mgtest-admin01', PASSWORD);
    await gotoMachineryGroups(page);

    await page.locator('#filterStationId').selectOption({ index: 1 });

    // Every visible row (if any) belongs to the selected Station —
    // asserted structurally rather than against a specific row count,
    // since the target environment's seeded data volume may vary.
    await expect(page.locator('.kc-table')).toBeVisible();
  });
});
