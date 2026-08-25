import { test, expect } from '@playwright/test'
import { login, USERS } from './helpers'

/**
 * change-password.spec.ts — screen-004--ganti-password-mobile /
 * usecase-004--ganti-password-mobile "Ganti Password Mobile".
 *
 * Replaces backend/tests/Browser/ChangePasswordMobileTest.php, which
 * pointed at the Laravel WEB app (BASE_URL http://localhost:8000,
 * '/settings/password') and drove screen-003's web ChangePasswordForm —
 * not this mobile screen at all. That file has been deleted; this is the
 * real mobile e2e coverage, run against the Vite dev server per
 * playwright.config.ts (baseURL http://localhost:5173).
 *
 * Selectors mirror ChangePasswordForm.vue's actual markup
 * (#old_password/#new_password/#new_password_confirmation IDs,
 * .banner-error/.banner-success/.field-error classes, "Simpan" button
 * text).
 *
 * Each scenario logs in fresh via ./helpers.ts's login() (real POST
 * /api/login against the live backend) then navigates to
 * /settings/password, so scenario order never matters. Uses the
 * `supervisor` fixture (not `operator`) so the "berhasil" scenario's real
 * password change doesn't invalidate `operator`'s Passw0rd! credential for
 * any other spec file relying on it.
 */
test.describe('Ganti Password Mobile (screen-004)', () => {
  test('berhasil mengganti password dan menampilkan pesan sukses, form direset', async ({ page }) => {
    await login(page, USERS.supervisor)
    await page.goto('/settings/password')

    await page.locator('#old_password').fill(USERS.supervisor.password)
    await page.locator('#new_password').fill('Passw0rd!2')
    await page.locator('#new_password_confirmation').fill('Passw0rd!2')

    await page.getByRole('button', { name: 'Simpan' }).click()

    await expect(page.locator('.banner-success')).toContainText('berhasil')
    await expect(page.locator('#old_password')).toHaveValue('')
    await expect(page.locator('#new_password')).toHaveValue('')
    await expect(page.locator('#new_password_confirmation')).toHaveValue('')

    // Revert so the fixture credential stays stable for any other spec run.
    await page.locator('#old_password').fill('Passw0rd!2')
    await page.locator('#new_password').fill(USERS.supervisor.password)
    await page.locator('#new_password_confirmation').fill(USERS.supervisor.password)
    await page.getByRole('button', { name: 'Simpan' }).click()
    await expect(page.locator('.banner-success')).toContainText('berhasil')
  })

  test('menampilkan error password lama salah', async ({ page }) => {
    await login(page, USERS.supervisor)
    await page.goto('/settings/password')

    await page.locator('#old_password').fill('SalahBanget1!')
    await page.locator('#new_password').fill('Passw0rd!2')
    await page.locator('#new_password_confirmation').fill('Passw0rd!2')

    await page.getByRole('button', { name: 'Simpan' }).click()

    await expect(page.locator('.banner-error')).toContainText('Password lama salah.')
    await expect(page.locator('#old_password')).toHaveValue('')
  })

  test('menampilkan error validasi format password baru dan memblokir submit', async ({ page }) => {
    await login(page, USERS.supervisor)
    await page.goto('/settings/password')

    await page.locator('#old_password').fill(USERS.supervisor.password)
    await page.locator('#new_password').fill('abc') // too short, no symbol
    await page.locator('#new_password').blur()

    await expect(page.locator('.field-error')).toContainText('Password baru minimal 6 karakter')

    await page.getByRole('button', { name: 'Simpan' }).click()
    // Blocked client-side — no success/error banner from a network round-trip.
    await expect(page.locator('.banner-success')).toHaveCount(0)
  })

  test('menampilkan error konfirmasi password tidak cocok', async ({ page }) => {
    await login(page, USERS.supervisor)
    await page.goto('/settings/password')

    await page.locator('#old_password').fill(USERS.supervisor.password)
    await page.locator('#new_password').fill('Passw0rd!2')
    await page.locator('#new_password_confirmation').fill('Passw0rd!3')
    await page.locator('#new_password_confirmation').blur()

    await expect(page.locator('.field-error')).toContainText('Konfirmasi password tidak cocok')

    await page.getByRole('button', { name: 'Simpan' }).click()
    await expect(page.locator('.banner-success')).toHaveCount(0)
  })

  test('memblokir submit sebelum ada request saat tidak ada koneksi internet', async ({ page, context }) => {
    await login(page, USERS.supervisor)
    await page.goto('/settings/password')

    // Go offline only after the page/form has loaded — an SPA served by
    // Vite has nothing to render from if navigation itself has no network.
    await context.setOffline(true)

    await page.locator('#old_password').fill(USERS.supervisor.password)
    await page.locator('#new_password').fill('Passw0rd!2')
    await page.locator('#new_password_confirmation').fill('Passw0rd!2')

    await page.getByRole('button', { name: 'Simpan' }).click()

    await expect(page.locator('.banner-error')).toContainText(
      'Tidak ada koneksi internet. Ganti password memerlukan koneksi internet.',
    )

    await context.setOffline(false)
  })
})
