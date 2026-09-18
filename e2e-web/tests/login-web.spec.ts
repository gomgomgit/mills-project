/**
 * LoginWebTest (Browser/Playwright) — screen-001--login-web / usecase-001--login-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Runs
 * against a real browser + `php artisan serve` dev server
 * (test_strategy.browser_test: tool=Playwright, base_url=http://localhost:8000,
 * start_command="php artisan serve").
 *
 * REWRITTEN: the login form's Business Area <select> was removed (see
 * resources/views/livewire/auth/login-form.blade.php — only
 * #username/#password remain; App\Livewire\Auth\LoginForm::login() now
 * calls AuthService::login($this->username, $this->password) with no
 * business_unit_id argument at all). The old "Business Area Tidak Sesuai"
 * scenario is dropped along with it — BusinessAreaMismatchException can no
 * longer be thrown from this screen since business_unit_id is never sent
 * (the catch block in LoginForm::login() is now unreachable defensive
 * code, not a live path). This file previously drove a
 * `#business_unit_id` <select> that no longer exists on this page at all,
 * so every scenario below was failing at the first interaction.
 *
 * NOT executed in this run (no dev server / browser available here) — this
 * file is written to be complete and correct, to be run later via
 * `playwright test` per test_strategy.browser_test.run_command, from a
 * project root with @playwright/test installed and a playwright.config.*
 * pointing at this file (e.g. testDir including backend/tests/Browser).
 *
 * Test data assumption: these scenarios assume seeded fixture users are
 * present in the environment under test (this spec does not seed the DB
 * itself — Playwright drives the browser only). Suggested fixtures,
 * matching DemoAccountSeeder.php:
 *   - active user: username=supervisor01, password=Passw0rd!, role=supervisor
 *   - inactive user: username=inactive-a, password=Passw0rd!
 * Adjust the USERNAME constants below to match whatever seeder is used to
 * provision the target environment.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD, LOGIN_PATH } from './support/auth'


const VALID_USERNAME = 'supervisor01';
const VALID_PASSWORD = 'Passw0rd!';

const INACTIVE_USERNAME = 'inactive-a';

async function gotoLogin(page) {
  await page.goto('/login');
}

test.describe('Login Web', () => {
  // Scenario: "Login Web — berhasil"
  test('berhasil login dan redirect ke dashboard sesuai role, session cookie ter-set', async ({ page, context }) => {
    await gotoLogin(page);

    await page.locator('#username').fill(VALID_USERNAME);
    await page.locator('#password').fill(VALID_PASSWORD);

    await page.locator('button.login-button').click();

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

    await page.locator('button.login-button').click();

    await expect(page.locator('.login-alert')).toContainText('Username atau password salah.');
    expect(page.url()).toContain(LOGIN_PATH);

    // Password field cleared, form stays editable.
    await expect(page.locator('#password')).toHaveValue('');
    await expect(page.locator('button.login-button')).toBeEnabled();
  });

  // Scenario: "Login Web — Akun Dinonaktifkan"
  test('menampilkan error akun tidak aktif', async ({ page }) => {
    await gotoLogin(page);

    await page.locator('#username').fill(INACTIVE_USERNAME);
    await page.locator('#password').fill(VALID_PASSWORD);

    await page.locator('button.login-button').click();

    await expect(page.locator('.login-alert')).toContainText('Akun tidak aktif, hubungi Admin.');
    expect(page.url()).toContain(LOGIN_PATH);
  });

  // Scenario: "Login Web — Format Password Tidak Valid"
  test('menampilkan error validasi format password dan memblokir submit', async ({ page }) => {
    await gotoLogin(page);

    await page.locator('#username').fill(VALID_USERNAME);
    await page.locator('#password').fill('abc');

    await page.locator('button.login-button').click();

    await expect(page.locator('.form-field__error')).toContainText(/[Pp]assword/);
    expect(page.url()).toContain(LOGIN_PATH);
  });
});
