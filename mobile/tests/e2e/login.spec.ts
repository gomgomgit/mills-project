import { test, expect } from '@playwright/test'
import { USERS } from './helpers'

/**
 * login.spec.ts — screen-002--login-mobile / usecase-002--login-mobile
 * "Login Mobile".
 *
 * Replaces backend/tests/Browser/LoginMobileTest.php, which — despite its
 * name — pointed at the Laravel WEB app (BASE_URL http://localhost:8000,
 * '/login', '/home') and drove a Business Area <select> that no longer
 * exists on this screen (business_unit_id is now auto-derived server-side,
 * see LoginForm.vue's header comment and ./helpers.ts's login()) — it was
 * really re-testing screen-001's web login form a second time, not this
 * mobile screen. That file has been deleted; this is the real mobile e2e
 * coverage, run against the Vite dev server per playwright.config.ts
 * (baseURL http://localhost:5173).
 *
 * Selectors mirror LoginForm.vue's actual markup (#username/#password IDs,
 * .banner-error/.field-error classes, "Login" button text) — same
 * convention ./helpers.ts's login() helper already uses.
 */
test.describe('Login Mobile (screen-002)', () => {
  test('berhasil login, redirect ke Home, token tersimpan lokal', async ({ page }) => {
    await page.goto('/login')
    await page.locator('#username').fill(USERS.operator.username)
    await page.locator('#password').fill(USERS.operator.password)

    await page.getByRole('button', { name: 'Login' }).click()
    await page.waitForURL('**/home')

    const token = await page.evaluate(() => localStorage.getItem('msl_auth_token'))
    expect(token).toBeTruthy()
  })

  test('menampilkan error kredensial salah, password dikosongkan, retry diizinkan', async ({ page }) => {
    await page.goto('/login')
    await page.locator('#username').fill(USERS.operator.username)
    await page.locator('#password').fill('SalahSekali1!')

    await page.getByRole('button', { name: 'Login' }).click()

    await expect(page.locator('.banner-error')).toContainText('Username atau password salah.')
    await expect(page.locator('#password')).toHaveValue('')
    await expect(page).toHaveURL(/\/login$/)

    // Retry with the correct password succeeds.
    await page.locator('#password').fill(USERS.operator.password)
    await page.getByRole('button', { name: 'Login' }).click()
    await page.waitForURL('**/home')
  })

  test('menampilkan error akun tidak aktif, tidak berpindah halaman', async ({ page }) => {
    await page.goto('/login')
    await page.locator('#username').fill('inactive-a')
    await page.locator('#password').fill('Passw0rd!')

    await page.getByRole('button', { name: 'Login' }).click()

    await expect(page.locator('.banner-error')).toContainText('Akun tidak aktif, hubungi Admin.')
    await expect(page).toHaveURL(/\/login$/)
  })

  test('menampilkan error validasi format password dan memblokir submit', async ({ page }) => {
    await page.goto('/login')
    await page.locator('#username').fill(USERS.operator.username)
    await page.locator('#password').fill('abc') // too short, no symbol
    await page.locator('#password').blur()

    await expect(page.locator('.field-error')).toContainText('Password minimal 6 karakter')

    await page.getByRole('button', { name: 'Login' }).click()
    // Blocked client-side — no network round-trip, still on /login.
    await expect(page).toHaveURL(/\/login$/)
  })

  test('memblokir submit sebelum ada request saat tidak ada koneksi di login pertama', async ({ page, context }) => {
    // Fresh context (default per test) — no token stored yet, matches
    // useConnectivityGuard's blocksFirstLogin = offline AND no stored token.
    // Navigate first (loads the SPA shell/JS bundles while still online),
    // THEN go offline — an SPA served by Vite has nothing to render from if
    // the very first navigation itself has no network to fetch from.
    await page.goto('/login')
    await context.setOffline(true)

    await page.locator('#username').fill(USERS.operator.username)
    await page.locator('#password').fill(USERS.operator.password)

    await page.getByRole('button', { name: 'Login' }).click()

    await expect(page.locator('.banner-error')).toContainText(
      'Koneksi internet diperlukan untuk login pertama kali',
    )
    await expect(page).toHaveURL(/\/login$/)

    await context.setOffline(false)
  })
})
