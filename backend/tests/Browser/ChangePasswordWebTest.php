/**
 * ChangePasswordWebTest (Browser/Playwright) — screen-003--ganti-password-web
 * / usecase-003--ganti-password-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * the convention established by tests/Browser/LoginWebTest.php and
 * tests/Browser/LoginMobileTest.php (screen-001 / screen-002) — same
 * file-extension/import-syntax pattern (a .php path containing a
 * Playwright TS spec body), since test_strategy.browser_test.tool is
 * Playwright (not Laravel Dusk — this codebase has no laravel/dusk
 * dependency, see composer.json).
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT: there is no dev server or
 * browser available in this sandbox (test_strategy.browser_test: tool=
 * Playwright, base_url=http://localhost:8000, start_command=
 * "php artisan serve"). This file is written to be complete and correct,
 * to be run later via `playwright test` per
 * test_strategy.browser_test.run_command, from a project root with
 * @playwright/test installed and a playwright.config.* pointing at this
 * file (e.g. testDir including backend/tests/Browser).
 *
 * Test data assumption (mirrors LoginWebTest.php's approach): this screen
 * requires an authenticated session, so each scenario logs in via /login
 * first, then navigates to /settings/password. Each scenario uses its own
 * dedicated fixture user (rather than sharing one across scenarios) so
 * scenario order never matters even though this spec does not reset the DB
 * itself (Playwright drives the browser only, against whatever seeder
 * provisioned the target environment) — a shared user's password would
 * otherwise actually change after the "success" scenario runs, breaking
 * later scenarios' "old_password" values.
 *   - business unit: "Mill A" (any business_unit whose name is selectable
 *     in the login form's Business Area <select>)
 *   - passtest-success01 / OldPass123! (role: supervisor) — scenario 1
 *   - passtest-wrongold01 / OldPass123! (role: supervisor) — scenario 2
 *   - passtest-badformat01 / OldPass123! (role: supervisor) — scenario 3
 *   - passtest-mismatch01 / OldPass123! (role: supervisor) — scenario 4
 * Adjust the USERNAME/BUSINESS_UNIT_NAME constants below to match whatever
 * seeder is used to provision the target environment.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const CHANGE_PASSWORD_PATH = '/settings/password';
const BUSINESS_UNIT_NAME = 'Mill A';
const OLD_PASSWORD = 'OldPass123!';

async function login(page, username, password) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.locator('#business_unit_id').selectOption({ label: BUSINESS_UNIT_NAME });
  await page.locator('button[type="submit"]').click();
  // Redirected away from /login once the session is established.
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
}

async function gotoChangePassword(page) {
  await page.goto(`${BASE_URL}${CHANGE_PASSWORD_PATH}`);
}

test.describe('Ganti Password Web', () => {
  // Scenario: "Ganti Password Web — success"
  test('berhasil mengganti password dan menampilkan pesan sukses, form direset', async ({ page }) => {
    await login(page, 'passtest-success01', OLD_PASSWORD);
    await gotoChangePassword(page);

    await page.locator('#old_password').fill(OLD_PASSWORD);
    await page.locator('#new_password').fill('NewPass456!');
    await page.locator('#new_password_confirmation').fill('NewPass456!');
    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.settings-toast--success')).toContainText('Password berhasil diubah.');

    // Form fields reset.
    await expect(page.locator('#old_password')).toHaveValue('');
    await expect(page.locator('#new_password')).toHaveValue('');
    await expect(page.locator('#new_password_confirmation')).toHaveValue('');
  });

  // Scenario: "Ganti Password Web — Password Lama Salah"
  test('menampilkan error password lama salah', async ({ page }) => {
    await login(page, 'passtest-wrongold01', OLD_PASSWORD);
    await gotoChangePassword(page);

    await page.locator('#old_password').fill('WrongOldPass1!');
    await page.locator('#new_password').fill('NewPass456!');
    await page.locator('#new_password_confirmation').fill('NewPass456!');
    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.settings-toast--error')).toContainText('Password lama salah.');
  });

  // Scenario: "Ganti Password Web — Password Baru Tidak Memenuhi Format"
  test('menampilkan error validasi format password baru dan memblokir submit', async ({ page }) => {
    await login(page, 'passtest-badformat01', OLD_PASSWORD);
    await gotoChangePassword(page);

    await page.locator('#old_password').fill(OLD_PASSWORD);
    await page.locator('#new_password').fill('abc');
    await page.locator('#new_password_confirmation').fill('abc');
    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.form-field__error')).toContainText(/[Pp]assword/);
    // No success/error toast should appear — submission was blocked by
    // validation.
    await expect(page.locator('.settings-toast--success')).toHaveCount(0);
  });

  // Scenario: "Ganti Password Web — Konfirmasi Tidak Cocok"
  test('menampilkan error konfirmasi password tidak cocok', async ({ page }) => {
    await login(page, 'passtest-mismatch01', OLD_PASSWORD);
    await gotoChangePassword(page);

    await page.locator('#old_password').fill(OLD_PASSWORD);
    await page.locator('#new_password').fill('NewPass456!');
    await page.locator('#new_password_confirmation').fill('Different789!');
    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.form-field__error')).toContainText(/[Kk]onfirmasi/);
    await expect(page.locator('.settings-toast--success')).toHaveCount(0);
  });
});
