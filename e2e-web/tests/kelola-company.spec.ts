/**
 * KelolaCompanyTest (Browser/Playwright) — screen-028--kelola-company /
 * usecase-028--kelola-company.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * the convention established by tests/Browser/KelolaCorporateTest.php —
 * same file-extension/import-syntax pattern (a .php path containing a
 * Playwright TS spec body), since test_strategy.browser_test.tool is
 * Playwright (not Laravel Dusk — this codebase has no laravel/dusk
 * dependency, see composer.json).
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
 * Test data assumption (mirrors KelolaCorporateTest.php's approach): this
 * screen requires an authenticated admin session, so each scenario logs in
 * via /login first, then navigates to /master-data/companies. Each
 * scenario uses its own dedicated fixture user/data (rather than sharing
 * across scenarios) so scenario order never matters even though this spec
 * does not reset the DB itself (Playwright drives the browser only,
 * against whatever seeder provisioned the target environment).
 *   - business unit: "Mill A" (any business_unit whose name is selectable
 *     in the login form's Business Area <select>)
 *   - comptest-admin01 / Passw0rd! (role: admin) — scenarios 1, 2, 3, 4, 5, 6
 *   - comptest-nonadmin01 / Passw0rd! (role: supervisor) — scenario 7
 * Scenario 1 assumes a corporate named "PT Induk Baru" already exists (to
 * pick from the Corporate dropdown). Scenario 2 assumes a company named
 * "PT Sebelum Edit" already exists under some corporate, and a corporate
 * named "PT Tujuan Edit" exists as the edit target. Scenario 3 assumes a
 * company named "PT Hapus Bersih" pre-seeded with 0 related business
 * units. Scenario 4 assumes a company named "PT Ada Business Unit"
 * pre-seeded with at least 1 related BusinessUnit row. Scenario 5 assumes
 * a corporate named "PT Sama Corporate" with an existing company named
 * "PT Nama Duplikat" already under it. Scenario 6 assumes a target
 * environment where NO Corporate rows exist at all (a separate, isolated
 * seed state from the other scenarios, since they all depend on at least
 * one Corporate existing). Adjust the USERNAME/BUSINESS_UNIT_NAME/
 * fixture-name constants below to match whatever seeder is used to
 * provision the target environment.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const COMPANIES_PATH = '/master-data/companies';
const BUSINESS_UNIT_NAME = 'Mill A';


async function gotoCompanies(page) {
  await page.goto(COMPANIES_PATH);
}

// Interacts with the x-searchable-select combobox (see
// resources/views/components/searchable-select.blade.php) that replaced
// this screen's #corporate_id <select>.
async function selectSearchable(page, id, label) {
  await page.locator(`#${id}`).click();
  await page.locator(`#${id}`).fill(label);
  await page.locator(`#${id}-listbox`).getByRole('option', { name: label, exact: true }).click();
}

test.describe('Kelola Company', () => {
  // Scenario 1: "Kelola Company — success"
  test('menambah company baru dengan memilih corporate dan menampilkannya di tabel', async ({ page }) => {
    await login(page, 'comptest-admin01', PASSWORD);
    await gotoCompanies(page);

    await page.locator('button', { hasText: 'Tambah Company' }).click();
    await selectSearchable(page, 'corporate_id', 'PT Induk Baru');

    const uniqueName = `PT Anak Baru ${Date.now()}`;
    await page.locator('#name').fill(uniqueName);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    const row = page.locator('.kc-table__row', { hasText: uniqueName });
    await expect(row).toBeVisible();
    await expect(row).toContainText('PT Induk Baru');
    await expect(row).toContainText('0');
  });

  // Scenario 2: "Kelola Company — Edit Company"
  test('mengedit nama dan corporate company lalu menampilkan perubahan di tabel', async ({ page }) => {
    await login(page, 'comptest-admin01', PASSWORD);
    await gotoCompanies(page);

    const row = page.locator('.kc-table__row', { hasText: 'PT Sebelum Edit' });
    await row.locator('button', { hasText: 'Edit' }).click();

    await selectSearchable(page, 'corporate_id', 'PT Tujuan Edit');
    const newName = `PT Sesudah Edit ${Date.now()}`;
    await page.locator('#name').fill(newName);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    const updatedRow = page.locator('.kc-table__row', { hasText: newName });
    await expect(updatedRow).toBeVisible();
    await expect(updatedRow).toContainText('PT Tujuan Edit');
    await expect(page.locator('.kc-table__row', { hasText: 'PT Sebelum Edit' })).toHaveCount(0);
  });

  // Scenario 3: "Kelola Company — Hapus Company — berhasil"
  test('menghapus company tanpa business unit terkait, baris hilang dari tabel', async ({ page }) => {
    await login(page, 'comptest-admin01', PASSWORD);
    await gotoCompanies(page);

    const row = page.locator('.kc-table__row', { hasText: 'PT Hapus Bersih' });
    await row.locator('button', { hasText: 'Hapus' }).click();
    await row.locator('button', { hasText: 'Ya, Hapus' }).click();

    await expect(page.locator('.kc-table__row', { hasText: 'PT Hapus Bersih' })).toHaveCount(0);
  });

  // Scenario 4: "Kelola Company — Hapus Company — ditolak"
  test('menolak penghapusan company yang masih memiliki business unit, baris tetap ada', async ({ page }) => {
    await login(page, 'comptest-admin01', PASSWORD);
    await gotoCompanies(page);

    const row = page.locator('.kc-table__row', { hasText: 'PT Ada Business Unit' });
    await row.locator('button', { hasText: 'Hapus' }).click();
    await row.locator('button', { hasText: 'Ya, Hapus' }).click();

    await expect(page.locator('.kc-alert')).toContainText('Business Unit');
    await expect(page.locator('.kc-table__row', { hasText: 'PT Ada Business Unit' })).toBeVisible();
  });

  // Scenario 5: "Kelola Company — Nama duplikat dalam Corporate yang sama"
  test('menampilkan error validasi saat menyimpan nama yang sudah dipakai di corporate yang sama', async ({ page }) => {
    await login(page, 'comptest-admin01', PASSWORD);
    await gotoCompanies(page);

    await page.locator('button', { hasText: 'Tambah Company' }).click();
    await selectSearchable(page, 'corporate_id', 'PT Sama Corporate');
    await page.locator('#name').fill('PT Nama Duplikat');
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-form-field__error')).toContainText(/sudah digunakan/i);
    // The modal stays open — submission was blocked by validation, no
    // second row with the duplicate name was created under this corporate.
    await expect(page.locator('.kc-modal')).toBeVisible();
    await expect(page.locator('.kc-table__row', { hasText: 'PT Nama Duplikat' })).toHaveCount(1);
  });

  // Scenario 6: "Kelola Company — Belum ada Corporate"
  //
  // Implementation note: the current markup (resources/views/components/
  // searchable-select.blade.php, used for #corporate_id) renders an
  // empty-hint paragraph below the input (empty-message prop) and, once
  // opened, a "Tidak ada hasil." listbox row when corporateOptions is
  // empty — there is no `disabled` attribute or dedicated modal-level
  // guidance copy. This test asserts the actual observable behavior (see
  // the equivalent note in tests/Feature/Livewire/KelolaCompanyTest.php's
  // file-level docblock, and this agent's known_issues in its final
  // report).
  test('menampilkan dropdown corporate kosong saat belum ada Corporate sama sekali', async ({ page }) => {
    await login(page, 'comptest-admin01', PASSWORD);
    await gotoCompanies(page);

    await page.locator('button', { hasText: 'Tambah Company' }).click();

    // No real Corporate to pick — the empty-message hint is shown, and
    // opening the listbox shows only the "no results" row.
    await expect(page.locator('.ss-combobox__empty-hint')).toContainText('Belum ada Corporate');
    await page.locator('#corporate_id').click();
    await expect(page.locator('#corporate_id-listbox')).toContainText('Tidak ada hasil.');
  });

  // Scenario 7: "Kelola Company — akses ditolak untuk non-Admin"
  test('menampilkan halaman akses ditolak untuk pengguna non-admin', async ({ page }) => {
    await login(page, 'comptest-nonadmin01', PASSWORD);
    await page.goto(COMPANIES_PATH);

    // EnsureRole::forbidden() -> abort(403), Laravel's default HTML error
    // page — no company table/controls are rendered.
    await expect(page.locator('body')).toContainText(/403/);
    await expect(page.locator('.kc-table')).toHaveCount(0);
  });
});
