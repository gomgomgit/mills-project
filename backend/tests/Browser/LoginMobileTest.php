/**
 * LoginMobileTest (Browser/Playwright) — screen-002--login-mobile /
 * usecase-002--login-mobile.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * the convention established by tests/Browser/LoginWebTest.php
 * (screen-001) — same file-extension/import-syntax pattern (a .php path
 * containing a Playwright TS spec body) — since no other Browser test
 * exists in this codebase besides that one.
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
 * Test data assumption (mirrors LoginWebTest.php's approach): these
 * scenarios assume a seeded fixture business unit + users are present in
 * the environment under test (this spec does not seed the DB itself —
 * Playwright drives the browser only). Suggested fixtures, matching the
 * example credentials given for this screen:
 *   - business unit: "Mill A" (any business_unit whose name is selectable
 *     in the login form's Business Area <select>)
 *   - active user in "Mill A": username=operator01, password=Passw0rd!,
 *     role=operator
 *   - inactive user in "Mill A": username=inactive01, password=Passw0rd!
 *   - active user NOT in "Mill A" (different business unit), used with
 *     "Mill A" selected to trigger the business-area-mismatch case:
 *     username=otherarea01, password=Passw0rd!
 * Adjust the USERNAME/BUSINESS_UNIT_NAME constants below to match whatever
 * seeder is used to provision the target environment.
 *
 * "Token Sesi Lokal Kadaluarsa" note: this scenario needs a pre-existing
 * locally-stored session older than the offline grace period
 * (stores/auth.ts's OFFLINE_GRACE_PERIOD_MS) plus a simulated offline
 * network condition at app boot — done here via
 * `context.addInitScript` (seeding localStorage before any app script
 * runs) + Playwright's `context.setOffline(true)`.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const HOME_PATH = '/home';

const VALID_USERNAME = 'operator01';
const VALID_PASSWORD = 'Passw0rd!';
const BUSINESS_UNIT_NAME = 'Mill A';

const INACTIVE_USERNAME = 'inactive01';
const OTHER_AREA_USERNAME = 'otherarea01';

// Mirrors stores/auth.ts's local storage keys (services/tokenStorage.ts).
const TOKEN_KEY = 'msl_auth_token';
const USER_KEY = 'msl_auth_user';
const BUSINESS_UNIT_KEY = 'msl_auth_business_unit';
const TOKEN_ISSUED_AT_KEY = 'msl_auth_token_issued_at';
const OFFLINE_GRACE_PERIOD_MS = 7 * 24 * 60 * 60 * 1000;

async function gotoLogin(page) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
}

async function selectBusinessUnit(page, name) {
  await page.locator('#business_unit_id').selectOption({ label: name });
}

test.describe('Login Mobile', () => {
  // Scenario: "Login Mobile — berhasil"
  test('berhasil login, redirect ke Home, sesi tersimpan secara lokal', async ({ page }) => {
    await gotoLogin(page);

    await page.locator('#username').fill(VALID_USERNAME);
    await page.locator('#password').fill(VALID_PASSWORD);
    await selectBusinessUnit(page, BUSINESS_UNIT_NAME);

    await page.locator('button[type="submit"]').click();

    await page.waitForURL((url) => url.pathname.startsWith(HOME_PATH));
    expect(page.url()).toContain(HOME_PATH);

    // Token + session persisted locally (services/tokenStorage.ts), not as
    // a session cookie — this screen's mobile branch issues a Sanctum
    // token, no web session (see screen-001's equivalent cookie assertion
    // for contrast).
    const token = await page.evaluate((key) => localStorage.getItem(key), TOKEN_KEY);
    expect(token).toBeTruthy();
  });

  // Scenario: "Tidak Ada Koneksi Saat Login Pertama"
  test('memblokir submit sebelum ada request saat tidak ada koneksi di login pertama', async ({ page, context }) => {
    // No stored token yet (first-ever activation) + offline.
    await context.addInitScript((key) => {
      window.localStorage.removeItem(key);
    }, TOKEN_KEY);
    await context.setOffline(true);

    await gotoLogin(page);

    await page.locator('#username').fill(VALID_USERNAME);
    await page.locator('#password').fill(VALID_PASSWORD);
    await selectBusinessUnit(page, BUSINESS_UNIT_NAME);

    const requests: string[] = [];
    page.on('request', (req) => {
      if (req.url().includes('/api/login')) {
        requests.push(req.url());
      }
    });

    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.banner-error')).toContainText(
      'Koneksi internet diperlukan untuk login pertama kali'
    );
    expect(page.url()).toContain(LOGIN_PATH);
    expect(requests).toHaveLength(0);

    await context.setOffline(false);
  });

  // Scenario: "Login Mobile — Kredensial Salah"
  test('menampilkan error kredensial salah, password dikosongkan, retry diizinkan', async ({ page }) => {
    await gotoLogin(page);

    await page.locator('#username').fill(VALID_USERNAME);
    await page.locator('#password').fill('WrongPass1!');
    await selectBusinessUnit(page, BUSINESS_UNIT_NAME);

    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.banner-error')).toContainText('Username atau password salah.');
    expect(page.url()).toContain(LOGIN_PATH);

    await expect(page.locator('#password')).toHaveValue('');
    await expect(page.locator('button[type="submit"]')).toBeEnabled();
  });

  // Scenario: "Login Mobile — Akun Dinonaktifkan"
  test('menampilkan error akun tidak aktif, tidak berpindah halaman', async ({ page }) => {
    await gotoLogin(page);

    await page.locator('#username').fill(INACTIVE_USERNAME);
    await page.locator('#password').fill(VALID_PASSWORD);
    await selectBusinessUnit(page, BUSINESS_UNIT_NAME);

    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.banner-error')).toContainText('Akun tidak aktif, hubungi Admin.');
    expect(page.url()).toContain(LOGIN_PATH);
  });

  // Scenario: "Token Sesi Lokal Kadaluarsa"
  test('sesi lokal kedaluwarsa saat offline meminta login ulang secara online', async ({ page, context }) => {
    const staleIssuedAt = Date.now() - (OFFLINE_GRACE_PERIOD_MS + 60_000);

    await context.addInitScript(
      ({ tokenKey, userKey, buKey, issuedAtKey, issuedAt }) => {
        window.localStorage.setItem(tokenKey, 'stale-e2e-token');
        window.localStorage.setItem(userKey, JSON.stringify({ id: 'u1', username: 'operator01', name: 'Operator Satu', role: 'operator' }));
        window.localStorage.setItem(buKey, JSON.stringify({ id: 'bu-001', name: 'Mill A' }));
        window.localStorage.setItem(issuedAtKey, String(issuedAt));
      },
      {
        tokenKey: TOKEN_KEY,
        userKey: USER_KEY,
        buKey: BUSINESS_UNIT_KEY,
        issuedAtKey: TOKEN_ISSUED_AT_KEY,
        issuedAt: staleIssuedAt,
      }
    );
    await context.setOffline(true);

    // Navigate to a protected route (not /login) to exercise the router's
    // auth guard + restoreSession() staleness check on app boot.
    await page.goto(`${BASE_URL}${HOME_PATH}`);

    await page.waitForURL((url) => url.pathname.startsWith(LOGIN_PATH));
    await expect(page.locator('.banner-info')).toContainText(
      'Sesi lokal Anda telah kedaluwarsa. Silakan login kembali secara online.'
    );

    await context.setOffline(false);
  });

  // Scenario: "Login Mobile — Password Tidak Memenuhi Format Minimum"
  test('menampilkan error validasi format password dan memblokir submit', async ({ page }) => {
    await gotoLogin(page);

    await page.locator('#username').fill(VALID_USERNAME);
    await page.locator('#password').fill('abc');
    await selectBusinessUnit(page, BUSINESS_UNIT_NAME);

    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.field-error')).toContainText(/[Pp]assword/);
    expect(page.url()).toContain(LOGIN_PATH);
  });

  // Scenario: "Login Mobile — Business Area Tidak Sesuai Penugasan"
  test('menampilkan error business area tidak sesuai, tetap di form login', async ({ page }) => {
    await gotoLogin(page);

    await page.locator('#username').fill(OTHER_AREA_USERNAME);
    await page.locator('#password').fill(VALID_PASSWORD);
    await selectBusinessUnit(page, BUSINESS_UNIT_NAME);

    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.banner-error')).toContainText(
      'Business area yang dipilih tidak sesuai dengan akses Anda.'
    );
    expect(page.url()).toContain(LOGIN_PATH);
  });
});
