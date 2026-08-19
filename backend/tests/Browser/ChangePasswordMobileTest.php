/**
 * ChangePasswordMobileTest (Browser/Playwright) —
 * screen-004--ganti-password-mobile / usecase-004--ganti-password-mobile.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * the convention established by tests/Browser/ChangePasswordWebTest.php
 * (screen-003) and tests/Browser/LoginMobileTest.php (screen-002) — same
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
 * file (e.g. testDir including backend/tests/Browser). See this screen's
 * known_issues (test-writer-agent, initial pass): flagged as a minor known
 * issue that this file could not be executed as part of this pass.
 *
 * Test data assumption (mirrors ChangePasswordWebTest.php's / Login
 * MobileTest.php's approach): this screen requires an authenticated mobile
 * session (Sanctum token, obtained via the real /login flow with
 * device_name), so each scenario logs in via /login first, then navigates
 * to /settings/password. Each scenario uses its own dedicated fixture user
 * (rather than sharing one across scenarios) so scenario order never
 * matters, even though this spec does not reset the DB itself — a shared
 * user's password would otherwise actually change after the "berhasil"
 * scenario runs, breaking later scenarios' "old_password" values.
 *   - business unit: "Mill A" (any business_unit whose name is selectable
 *     in the login form's Business Area <select>)
 *   - passtest-mobile-success01 / OldPass123! (role: operator) — scenario 1
 *   - passtest-mobile-offline01 / OldPass123! (role: operator) — scenario 2
 *   - passtest-mobile-wrongold01 / OldPass123! (role: operator) — scenario 3
 *   - passtest-mobile-badformat01 / OldPass123! (role: operator) — scenario 4
 *   - passtest-mobile-mismatch01 / OldPass123! (role: operator) — scenario 5
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
  // Redirected away from /login once the session/token is established.
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
}

async function gotoChangePassword(page) {
  await page.goto(`${BASE_URL}${CHANGE_PASSWORD_PATH}`);
}

test.describe('Ganti Password Mobile', () => {
  // Scenario: "Ganti Password Mobile — berhasil"
  test('berhasil mengganti password dan menampilkan pesan sukses, form direset', async ({ page }) => {
    await login(page, 'passtest-mobile-success01', OLD_PASSWORD);
    await gotoChangePassword(page);

    await page.locator('#old_password').fill(OLD_PASSWORD);
    await page.locator('#new_password').fill('NewPass456!');
    await page.locator('#new_password_confirmation').fill('NewPass456!');
    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.banner-success')).toContainText('Password berhasil diubah.');

    // Form fields reset.
    await expect(page.locator('#old_password')).toHaveValue('');
    await expect(page.locator('#new_password')).toHaveValue('');
    await expect(page.locator('#new_password_confirmation')).toHaveValue('');
  });

  // Scenario: "Tidak ada koneksi internet"
  test('memblokir submit sebelum ada request saat tidak ada koneksi internet', async ({ page, context }) => {
    await login(page, 'passtest-mobile-offline01', OLD_PASSWORD);
    await gotoChangePassword(page);

    await page.locator('#old_password').fill(OLD_PASSWORD);
    await page.locator('#new_password').fill('NewPass456!');
    await page.locator('#new_password_confirmation').fill('NewPass456!');

    await context.setOffline(true);

    const requests: string[] = [];
    page.on('request', (req) => {
      if (req.url().includes('/api/me/password')) {
        requests.push(req.url());
      }
    });

    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.banner-error')).toContainText(
      'Tidak ada koneksi internet. Ganti password memerlukan koneksi internet.'
    );
    expect(requests).toHaveLength(0);

    await context.setOffline(false);
  });

  // Scenario: "Ganti Password Mobile — Password Lama Salah"
  test('menampilkan error password lama salah', async ({ page }) => {
    await login(page, 'passtest-mobile-wrongold01', OLD_PASSWORD);
    await gotoChangePassword(page);

    await page.locator('#old_password').fill('WrongOldPass1!');
    await page.locator('#new_password').fill('NewPass456!');
    await page.locator('#new_password_confirmation').fill('NewPass456!');
    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.banner-error')).toContainText('Password lama salah.');
  });

  // Scenario: "Ganti Password Mobile — Password Baru Tidak Memenuhi Format"
  test('menampilkan error validasi format password baru dan memblokir submit', async ({ page }) => {
    await login(page, 'passtest-mobile-badformat01', OLD_PASSWORD);
    await gotoChangePassword(page);

    await page.locator('#old_password').fill(OLD_PASSWORD);
    await page.locator('#new_password').fill('abc');
    await page.locator('#new_password_confirmation').fill('abc');
    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.field-error')).toContainText(/[Pp]assword/);
    // No success banner should appear — submission was blocked client-side.
    await expect(page.locator('.banner-success')).toHaveCount(0);
  });

  // Scenario: "Ganti Password Mobile — Konfirmasi Tidak Cocok"
  test('menampilkan error konfirmasi password tidak cocok', async ({ page }) => {
    await login(page, 'passtest-mobile-mismatch01', OLD_PASSWORD);
    await gotoChangePassword(page);

    await page.locator('#old_password').fill(OLD_PASSWORD);
    await page.locator('#new_password').fill('NewPass456!');
    await page.locator('#new_password_confirmation').fill('Different789!');
    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.field-error')).toContainText(/[Kk]onfirmasi/);
    await expect(page.locator('.banner-success')).toHaveCount(0);
  });
});
