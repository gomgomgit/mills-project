/**
 * LoginWebTest (Browser/Playwright) — screen-001--login-web / usecase-001--login-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Runs
 * against a real browser + `php artisan serve` dev server
 * (test_strategy.browser_test: tool=Playwright, base_url=http://localhost:8000,
 * start_command="php artisan serve").
 *
 * NOT executed in this run (no dev server / browser available here) — this
 * file is written to be complete and correct, to be run later via
 * `playwright test` per test_strategy.browser_test.run_command, from a
 * project root with @playwright/test installed and a playwright.config.*
 * pointing at this file (e.g. testDir including backend/tests/Browser).
 *
 * Test data assumption: these scenarios assume a seeded fixture business
 * unit + users are present in the environment under test (this spec does
 * not seed the DB itself — Playwright drives the browser only). Suggested
 * fixtures, matching the example credentials given for this screen:
 *   - business unit: "Mill A" (any business_unit whose name is selectable
 *     in the login form's Business Area <select>)
 *   - active user in "Mill A": username=supervisor01, password=Passw0rd!,
 *     role=supervisor
 *   - inactive user in "Mill A": username=inactive01, password=Passw0rd!
 *   - active user NOT in "Mill A" (different business unit), used with
 *     "Mill A" selected to trigger the business-area-mismatch case:
 *     username=otherarea01, password=Passw0rd!
 * Adjust the USERNAME/BUSINESS_UNIT_NAME constants below to match whatever
 * seeder is used to provision the target environment.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';

const VALID_USERNAME = 'supervisor01';
const VALID_PASSWORD = 'Passw0rd!';
const BUSINESS_UNIT_NAME = 'Mill A';

const INACTIVE_USERNAME = 'inactive01';
const OTHER_AREA_USERNAME = 'otherarea01';

async function gotoLogin(page) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
}

async function selectBusinessUnit(page, name) {
  await page.locator('#business_unit_id').selectOption({ label: name });
}

test.describe('Login Web', () => {
  // Scenario: "Login Web — berhasil"
  test('berhasil login dan redirect ke dashboard sesuai role, session cookie ter-set', async ({ page, context }) => {
    await gotoLogin(page);

    await page.locator('#username').fill(VALID_USERNAME);
    await page.locator('#password').fill(VALID_PASSWORD);
    await selectBusinessUnit(page, BUSINESS_UNIT_NAME);

    await page.locator('button[type="submit"]').click();

    // Redirected away from /login to the role's dashboard.
    await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
    expect(page.url()).not.toContain(LOGIN_PATH);

    // Session cookie set.
    const cookies = await context.cookies();
    const sessionCookie = cookies.find((c) => c.name.includes('session'));
    expect(sessionCookie).toBeTruthy();
  });

  // Scenario: "Login Web — Kredensial Salah"
  test('menampilkan error kredensial salah dan tetap di form login', async ({ page }) => {
    await gotoLogin(page);

    await page.locator('#username').fill(VALID_USERNAME);
    await page.locator('#password').fill('WrongPass1!');
    await selectBusinessUnit(page, BUSINESS_UNIT_NAME);

    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.login-alert')).toContainText('Username atau password salah.');
    expect(page.url()).toContain(LOGIN_PATH);

    // Password field cleared, form stays editable.
    await expect(page.locator('#password')).toHaveValue('');
    await expect(page.locator('button[type="submit"]')).toBeEnabled();
  });

  // Scenario: "Login Web — Akun Dinonaktifkan"
  test('menampilkan error akun tidak aktif', async ({ page }) => {
    await gotoLogin(page);

    await page.locator('#username').fill(INACTIVE_USERNAME);
    await page.locator('#password').fill(VALID_PASSWORD);
    await selectBusinessUnit(page, BUSINESS_UNIT_NAME);

    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.login-alert')).toContainText('Akun tidak aktif, hubungi Admin.');
    expect(page.url()).toContain(LOGIN_PATH);
  });

  // Scenario: "Login Web — Business Area Tidak Sesuai"
  test('menampilkan error business area tidak sesuai', async ({ page }) => {
    await gotoLogin(page);

    await page.locator('#username').fill(OTHER_AREA_USERNAME);
    await page.locator('#password').fill(VALID_PASSWORD);
    await selectBusinessUnit(page, BUSINESS_UNIT_NAME);

    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.login-alert')).toContainText(
      'Business area yang dipilih tidak sesuai dengan akses Anda.'
    );
    expect(page.url()).toContain(LOGIN_PATH);
  });

  // Scenario: "Login Web — Format Password Tidak Valid"
  test('menampilkan error validasi format password dan memblokir submit', async ({ page }) => {
    await gotoLogin(page);

    await page.locator('#username').fill(VALID_USERNAME);
    await page.locator('#password').fill('abc');
    await selectBusinessUnit(page, BUSINESS_UNIT_NAME);

    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.form-field__error')).toContainText(/[Pp]assword/);
    expect(page.url()).toContain(LOGIN_PATH);
  });
});
