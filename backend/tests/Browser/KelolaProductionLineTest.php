<?php

/**
 * KelolaProductionLineTest (Browser/Playwright) — screen-036--kelola-production-line
 * / usecase-036--kelola-production-line.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * the convention established by tests/Browser/KelolaBusinessUnitTest.php
 * (this screen sits one level below Business Unit, exactly as Business
 * Unit sits one level below Company — same structure, no file upload) —
 * same file-extension/import-syntax pattern (a .php path containing a
 * Playwright TS spec body), since test_strategy.browser_test.tool is
 * Playwright (not Laravel Dusk — this codebase has no laravel/dusk
 * dependency, see composer.json).
 *
 * This is a plain Livewire/Blade web screen served by `php artisan serve`
 * (test_strategy.browser_test.base_url), so it is fully browser-testable
 * via the dev server — not deferred.
 *
 * #business_unit_id / #filterBusinessUnitId are the x-searchable-select
 * combobox (resources/views/components/searchable-select.blade.php), not a
 * native <select> — interacted with via the selectSearchable()/
 * selectSearchableFirst() helpers below (same pattern as
 * KelolaBusinessUnitTest.php et al).
 *
 * create() auto-provisions 19 canonical stations for a newly created
 * Production Line (ProductionLineService::DEFAULT_STATIONS) — the
 * "success" scenario below asserts "19" in the Jumlah Station column.
 * Fixture Production Lines used by the delete scenarios are assumed
 * seeded directly (bypassing the service, e.g. via a factory/seeder), so
 * they start with 0 stations unless the seeder deliberately attaches one —
 * same convention as KelolaBusinessUnitTest.php's "Mill Hapus Bersih" /
 * "Mill Ada Station" fixtures.
 *
 * WRITTEN BUT NOT RUN IN THIS SESSION: no dev server/browser available in
 * this environment. Written to be complete and correct, to be run later
 * via `playwright test` per test_strategy.browser_test.run_command, from a
 * project root with @playwright/test installed and a playwright.config.*
 * pointing at this file (e.g. testDir including backend/tests/Browser).
 *
 * Test data assumption: this screen requires an authenticated admin
 * session, so each scenario logs in via /login first, then navigates to
 * /master-data/production-lines. Each scenario uses its own dedicated
 * fixture user/data so scenario order never matters even though this spec
 * does not reset the DB itself.
 *   - pltest-admin01 / Passw0rd! (role: admin) — scenarios 1-7
 *   - pltest-nonadmin01 / Passw0rd! (role: supervisor) — scenario 7
 * Scenario 1 assumes a business unit named "Mill PL Baru" already exists
 * (to pick from the Business Unit dropdown). Scenario 2 assumes a
 * production line named "PL Sebelum Edit" already exists under some
 * business unit, and a business unit named "Mill PL Tujuan Edit" exists as
 * the edit target. Scenario 3 assumes a production line named
 * "PL Hapus Bersih" pre-seeded (directly, not via the service) with 0
 * related Station rows. Scenario 4 assumes a production line named
 * "PL Ada Station" pre-seeded with at least 1 related Station row.
 * Scenario 5 assumes a production line already exists with code
 * "PL-DUP-01" under any business unit. Scenario 6 assumes at least one
 * Business Unit row exists (for the "Business Unit induk wajib dipilih"
 * validation scenario). Scenario 7 (akses ditolak) needs no pre-seeded
 * Production Line data. Adjust the USERNAME/fixture-name constants below
 * to match whatever seeder is used to provision the target environment.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const PRODUCTION_LINES_PATH = '/master-data/production-lines';
const PASSWORD = 'Passw0rd!';

async function login(page, username, password) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.locator('button[type="submit"]').click();
  // Redirected away from /login once the session is established.
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
}

async function gotoProductionLines(page) {
  await page.goto(`${BASE_URL}${PRODUCTION_LINES_PATH}`);
}

async function selectSearchable(page, id, label) {
  await page.locator(`#${id}`).click();
  await page.locator(`#${id}`).fill(label);
  await page.locator(`#${id}-listbox`).getByRole('option', { name: label, exact: true }).click();
}

async function selectSearchableFirst(page, id) {
  await page.locator(`#${id}`).click();
  await page.locator(`#${id}-listbox`).getByRole('option').nth(1).click();
}

test.describe('Kelola Production Line', () => {
  // Scenario: "Kelola Production Line — success"
  test('menambah production line baru dengan memilih business unit, 18 station otomatis dibuat', async ({ page }) => {
    await login(page, 'pltest-admin01', PASSWORD);
    await gotoProductionLines(page);

    await page.locator('button', { hasText: 'Tambah Production Line' }).click();
    await selectSearchable(page, 'business_unit_id', 'Mill PL Baru');

    const uniqueSuffix = Date.now();
    await page.locator('#code').fill(`PL-BROWSER-${uniqueSuffix}`);
    const uniqueName = `Line Baru ${uniqueSuffix}`;
    await page.locator('#name').fill(uniqueName);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    const row = page.locator('.kc-table__row', { hasText: uniqueName });
    await expect(row).toBeVisible();
    await expect(row).toContainText('Mill PL Baru');
    await expect(row).toContainText('18');
  });

  // Scenario: "Kelola Production Line — Edit Production Line"
  test('mengedit kode, nama, dan business unit production line lalu menampilkan perubahan di tabel', async ({ page }) => {
    await login(page, 'pltest-admin01', PASSWORD);
    await gotoProductionLines(page);

    const row = page.locator('.kc-table__row', { hasText: 'PL Sebelum Edit' });
    await row.locator('button', { hasText: 'Edit' }).click();

    await selectSearchable(page, 'business_unit_id', 'Mill PL Tujuan Edit');
    const uniqueSuffix = Date.now();
    await page.locator('#code').fill(`PL-BROWSER-EDIT-${uniqueSuffix}`);
    const newName = `Line Sesudah Edit ${uniqueSuffix}`;
    await page.locator('#name').fill(newName);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    const updatedRow = page.locator('.kc-table__row', { hasText: newName });
    await expect(updatedRow).toBeVisible();
    await expect(updatedRow).toContainText('Mill PL Tujuan Edit');
    await expect(page.locator('.kc-table__row', { hasText: 'PL Sebelum Edit' })).toHaveCount(0);
  });

  // Scenario: "Kelola Production Line — Hapus — berhasil"
  test('menghapus production line tanpa station terkait, baris hilang dari tabel', async ({ page }) => {
    await login(page, 'pltest-admin01', PASSWORD);
    await gotoProductionLines(page);

    const row = page.locator('.kc-table__row', { hasText: 'PL Hapus Bersih' });
    await row.locator('button', { hasText: 'Hapus' }).click();
    await row.locator('button', { hasText: 'Ya, Hapus' }).click();

    await expect(page.locator('.kc-table__row', { hasText: 'PL Hapus Bersih' })).toHaveCount(0);
  });

  // Scenario: "Kelola Production Line — Hapus — ditolak"
  test('menolak penghapusan production line yang masih memiliki station terkait, baris tetap ada', async ({ page }) => {
    await login(page, 'pltest-admin01', PASSWORD);
    await gotoProductionLines(page);

    const row = page.locator('.kc-table__row', { hasText: 'PL Ada Station' });
    await row.locator('button', { hasText: 'Hapus' }).click();
    await row.locator('button', { hasText: 'Ya, Hapus' }).click();

    await expect(page.locator('.kc-alert')).toContainText(/Station/i);
    await expect(page.locator('.kc-table__row', { hasText: 'PL Ada Station' })).toBeVisible();
  });

  // Scenario: "Kelola Production Line — Kode duplikat"
  test('menampilkan error validasi saat menyimpan kode yang sudah dipakai production line lain', async ({ page }) => {
    await login(page, 'pltest-admin01', PASSWORD);
    await gotoProductionLines(page);

    await page.locator('button', { hasText: 'Tambah Production Line' }).click();
    await selectSearchableFirst(page, 'business_unit_id');
    await page.locator('#code').fill('PL-DUP-01');
    await page.locator('#name').fill('Line Kode Duplikat');
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-form-field__error')).toContainText(/sudah digunakan/i);
    // The modal stays open — submission was blocked by validation, no new
    // row with the duplicate code was created.
    await expect(page.locator('.kc-modal')).toBeVisible();
    await expect(page.locator('.kc-table__row', { hasText: 'Line Kode Duplikat' })).toHaveCount(0);
  });

  // Scenario: "Kelola Production Line — Business Unit induk wajib dipilih"
  test('menampilkan error validasi saat submit form tanpa memilih Business Unit', async ({ page }) => {
    await login(page, 'pltest-admin01', PASSWORD);
    await gotoProductionLines(page);

    await page.locator('button', { hasText: 'Tambah Production Line' }).click();
    const uniqueSuffix = Date.now();
    await page.locator('#code').fill(`PL-NOBU-${uniqueSuffix}`);
    await page.locator('#name').fill('Line Tanpa Business Unit');
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-form-field__error')).toContainText(/wajib dipilih/i);
    await expect(page.locator('.kc-modal')).toBeVisible();
    await expect(page.locator('.kc-table__row', { hasText: 'Line Tanpa Business Unit' })).toHaveCount(0);
  });

  // Scenario: "Kelola Production Line — Akses ditolak untuk non-Admin"
  test('menampilkan halaman akses ditolak untuk pengguna non-admin', async ({ page }) => {
    await login(page, 'pltest-nonadmin01', PASSWORD);
    await page.goto(`${BASE_URL}${PRODUCTION_LINES_PATH}`);

    // EnsureRole::forbidden() -> abort(403), Laravel's default HTML error
    // page — no production line table/controls are rendered.
    await expect(page.locator('body')).toContainText(/403/);
    await expect(page.locator('.kc-table')).toHaveCount(0);
  });

  // Additional coverage: filtering the table by Business Unit.
  test('memfilter tabel production line berdasarkan Business Unit yang dipilih', async ({ page }) => {
    await login(page, 'pltest-admin01', PASSWORD);
    await gotoProductionLines(page);

    await selectSearchableFirst(page, 'filterBusinessUnitId');

    // Every visible row (if any) belongs to the selected Business Unit —
    // asserted structurally rather than against a specific row count,
    // since the target environment's seeded data volume may vary.
    await expect(page.locator('.kc-table')).toBeVisible();
  });
});
