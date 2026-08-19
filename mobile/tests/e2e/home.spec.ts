import { test, expect } from '@playwright/test'
import { login, USERS } from './helpers'

/**
 * home.spec.ts — screen-005--home / usecase-005--home "Navigasi Home".
 *
 * REWRITTEN (2026-08-18): screen-005's purpose changed completely — it is
 * no longer a per-station draft-status dashboard (old scenario "Lihat
 * Status Draft & Navigasi"), it is now a pure welcome + navigation
 * launcher (header + hero image + personal greeting + 3 menu cards, no
 * API/SQLite call of any kind — api_contracts[].endpoints = [] for this
 * screen). All previous draft-status / empty-state / pagination e2e cases
 * (station-row badges, "Belum ada data draft.", seedPausedDrafts /
 * paused-drafts-list--scrollable) have been dropped along with them.
 *
 * Selectors mirror HomeView.vue's actual markup, same convention as
 * ../HomeView.spec.ts (class names for structural elements, data-testid
 * for interactive/dynamic ones — e.g. `menu-card-*`, `info-message`,
 * `hamburger-button`, `nav-menu`), matching station-list.spec.ts's use of
 * `station-info-message`.
 *
 * Follows the same real-login-against-live-backend pattern as
 * station-list.spec.ts (login() helper, real `npm run dev` + `php artisan
 * serve`).
 */
test.describe('Home (screen-005)', () => {
  test('Navigasi Home — success', async ({ page }) => {
    await login(page)

    await expect(page.locator('.home-header')).toBeVisible()
    await expect(page.locator('.hero-image')).toBeVisible()
    await expect(page.locator('.welcome-text')).toBeVisible()
    await expect(page.getByTestId('menu-card-production-process-activity')).toContainText(
      'Production Process Activity',
    )
    await expect(page.getByTestId('menu-card-estimates-baselines')).toContainText('Estimates & Baselines')
    await expect(page.getByTestId('menu-card-dashboard-reporting')).toContainText('Dashboard & Reporting')

    await page.getByTestId('menu-card-production-process-activity').click()
    await page.waitForURL('**/stations')
  })

  test('Navigasi Home — Menu Placeholder Dipilih', async ({ page }) => {
    await login(page)

    await page.getByTestId('menu-card-estimates-baselines').click()
    await expect(page.getByTestId('info-message')).toContainText('Segera Hadir')
    await expect(page).toHaveURL(/\/home$/)

    await page.getByTestId('menu-card-dashboard-reporting').click()
    await expect(page.getByTestId('info-message')).toContainText('Segera Hadir')
    await expect(page).toHaveURL(/\/home$/)
  })

  test('Navigasi Home — Nama User Tidak Tersedia', async ({ page }) => {
    await login(page)

    // No seeded demo account has an empty name (see helpers.ts's USERS),
    // so the "no name" condition is simulated by patching the locally
    // persisted auth user (tokenStorage's `msl_auth_user` key, same
    // storage this app already reads on every restoreSession() call) and
    // then reloading /home — same "write directly to the app's own local
    // persistence, then exercise the real UI" approach as
    // helpers.ts's seedStations/seedPausedDrafts.
    await page.evaluate(() => {
      const raw = localStorage.getItem('msl_auth_user')
      const user = raw ? JSON.parse(raw) : {}
      user.name = ''
      localStorage.setItem('msl_auth_user', JSON.stringify(user))
    })

    await page.goto('/home')

    const welcomeText = await page.locator('.welcome-text').textContent()
    expect(welcomeText ?? '').toMatch(/Selamat datang/i)
    expect(welcomeText ?? '').not.toContain(USERS.operator.username)
    expect(welcomeText ?? '').not.toMatch(/,/)
  })

  test('Navigasi Home — Tidak Menampilkan Status Draft', async ({ page }) => {
    await login(page)

    await expect(page.locator('.welcome-text')).toBeVisible()
    await expect(page.getByTestId('menu-card-production-process-activity')).toBeVisible()
    await expect(page.getByTestId('menu-card-estimates-baselines')).toBeVisible()
    await expect(page.getByTestId('menu-card-dashboard-reporting')).toBeVisible()

    await expect(page.getByText('Belum ada data draft.')).toHaveCount(0)
    await expect(page.getByText('Sedang berlangsung')).toHaveCount(0)
    await expect(page.getByText('Dijeda')).toHaveCount(0)
    await expect(page.locator('.station-row')).toHaveCount(0)
    await expect(page.locator('.station-summary')).toHaveCount(0)
    await expect(page.locator('.paused-drafts-list')).toHaveCount(0)
  })

  // Scenario: "Navigasi Home — Hero Image Fallback Statis" (tech spec ver 4).
  // No mill-setting has been seeded for the test business unit, so this
  // exercises the real fallback-to-bundled-static-asset path end-to-end.
  test('Navigasi Home — Hero Image Fallback Statis', async ({ page }) => {
    await login(page)

    const heroImage = page.locator('.hero-image')
    await expect(heroImage).toBeVisible()

    const naturalWidth = await heroImage.evaluate((img: HTMLImageElement) => img.naturalWidth)
    expect(naturalWidth).toBeGreaterThan(0)
  })
})
