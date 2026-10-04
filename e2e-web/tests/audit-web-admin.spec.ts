/**
 * Bukti browser untuk perbaikan audit 2026-10-04 di area WEB ADMIN & AUTH
 * (#1 sidebar per peran + halaman 403, #2 Operator → /beranda, #3 password
 * Kelola User, #4 sesi akun nonaktif, #5 hapus BU, #6 upload bukan gambar,
 * #14 redirect '/' dan '/login', #15 label sidebar & tombol chatbot).
 *
 * Setiap test memasang watchProblems(): pageerror, console.error dan
 * respons >= 400 dari aplikasi membuat test gagal (403 hanya diizinkan di
 * test yang memang menguji halaman akses ditolak).
 *
 * Akun fixture: stest-admin01 (Admin), stest-nonadmin01 (Supervisor),
 * webtest-operator01 (Operator), webtest-deact01 (Supervisor yang
 * dinonaktifkan lalu diaktifkan kembali oleh test #4 — seed berikutnya juga
 * memulihkannya).
 */
import { test, expect, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { findRow } from './support/paged-table'
import { watchProblems } from './support/page-health'

async function sidebarLinks(page: Page): Promise<string[]> {
  return page.locator('#shell-sidebar a').evaluateAll((links) => links.map((a) => new URL((a as HTMLAnchorElement).href).pathname))
}

test.describe('Audit web admin & auth', () => {
  test('#1 Supervisor: sidebar tanpa menu admin; URL admin → halaman 403 Indonesia dengan Logout', async ({ page }) => {
    const problems = watchProblems(page, { allowStatus: [403] })
    await login(page, 'stest-nonadmin01', PASSWORD)
    await page.goto('/dashboard')

    const links = await sidebarLinks(page)
    expect(links).toContain('/dashboard')
    expect(links).not.toContain('/master-data/corporates')
    expect(links).not.toContain('/users')
    expect(links).not.toContain('/mill-settings')
    expect(links).not.toContain('/reports/management')

    const response = await page.goto('/users')
    expect(response?.status()).toBe(403)
    await expect(page.locator('[data-testid="forbidden-page"]')).toContainText('Akses Ditolak')
    await expect(page.locator('#shell-sidebar')).toBeVisible()
    await page.locator('[data-testid="forbidden-logout"]').click()
    await page.waitForURL(/\/login$/)

    expect(problems).toEqual([])
  })

  test('#1 Admin: tidak ada menu Laporan Manajemen; label sidebar tidak terpotong', async ({ page }) => {
    const problems = watchProblems(page)
    await login(page, 'stest-admin01', PASSWORD)
    await page.goto('/dashboard')

    const links = await sidebarLinks(page)
    expect(links).toContain('/users')
    expect(links).not.toContain('/reports/management')

    const label = page.locator('#shell-sidebar a', { hasText: 'Production Process Activity' }).locator('.label')
    const { clipped } = await label.evaluate((el) => ({ clipped: el.scrollWidth > el.clientWidth + 1 }))
    expect(clipped).toBe(false)

    expect(problems).toEqual([])
  })

  test('#2 Operator login web → /beranda dengan shell terbatas (Beranda + Ganti Password + Logout)', async ({ page }) => {
    const problems = watchProblems(page)
    await login(page, 'webtest-operator01', PASSWORD)

    await expect(page).toHaveURL(/\/beranda$/)
    await expect(page.locator('[data-testid="operator-home"]')).toContainText('aplikasi mobile')
    expect((await sidebarLinks(page)).sort()).toEqual(['/beranda', '/settings/password'])
    await page.locator('#shell-sidebar a', { hasText: 'Ganti Password' }).click()
    await expect(page).toHaveURL(/\/settings\/password$/)
    await expect(page.locator('[data-testid="logout-button"]')).toBeVisible()

    expect(problems).toEqual([])
  })

  test('#14 "/" dan "/login" mengalihkan sesuai status login', async ({ page }) => {
    const problems = watchProblems(page)
    await page.goto('/')
    await expect(page).toHaveURL(/\/login$/)

    await login(page, 'stest-admin01', PASSWORD)
    await page.goto('/')
    await expect(page).toHaveURL(/\/dashboard$/)
    await page.goto('/login')
    await expect(page).toHaveURL(/\/dashboard$/)

    expect(problems).toEqual([])
  })

  test('#4 sesi akun yang dinonaktifkan Admin dikeluarkan pada request berikutnya', async ({ browser }) => {
    const userContext = await browser.newContext()
    const adminContext = await browser.newContext()
    const userPage = await userContext.newPage()
    const adminPage = await adminContext.newPage()
    const problems = [...watchProblems(adminPage)]

    try {
      await login(userPage, 'webtest-deact01', PASSWORD)
      await userPage.goto('/dashboard')
      await expect(userPage).toHaveURL(/\/dashboard$/)

      await login(adminPage, 'stest-admin01', PASSWORD)
      await adminPage.goto('/users')
      const row = await findRow(adminPage, 'webtest-deact01')
      let confirmText = ''
      adminPage.once('dialog', (dialog) => {
        confirmText = dialog.message()
        dialog.accept()
      })
      await row.locator('button', { hasText: 'Nonaktifkan' }).click()
      await expect(adminPage.locator('[data-testid="success-message"]')).toContainText('User berhasil dinonaktifkan.')
      expect(confirmText).toContain('Nonaktifkan user webtest-deact01')

      await userPage.goto('/dashboard')
      await expect(userPage).toHaveURL(/\/login$/)
      await expect(userPage.locator('.login-alert')).toContainText('Akun Anda telah dinonaktifkan')
    } finally {
      // Pulihkan akun fixture (seed berikutnya juga memulihkannya).
      const row = await findRow(adminPage, 'webtest-deact01')
      if ((await row.locator('button', { hasText: 'Aktifkan' }).count()) > 0) {
        await row.locator('button', { hasText: 'Aktifkan' }).click()
        await expect(adminPage.locator('[data-testid="success-message"]')).toContainText('User berhasil diaktifkan.')
      }
      await userContext.close()
      await adminContext.close()
    }

    expect(problems).toEqual([])
  })

  test('#3 Kelola User: password tanpa simbol ditolak dengan aturan yang sama dengan login', async ({ page }) => {
    const problems = watchProblems(page)
    await login(page, 'stest-admin01', PASSWORD)
    await page.goto('/users')

    await page.locator('button', { hasText: 'Tambah User' }).click()
    await page.locator('#username').fill(`audit-lemah-${Date.now()}`)
    await page.locator('#name').fill('Audit Lemah')
    await page.locator('#role').click()
    await page.locator('#role-listbox').getByRole('option', { name: 'Admin', exact: true }).click()
    await page.locator('#password').fill('abcdef')
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click()

    await expect(page.getByText('Password minimal 6 karakter dan harus mengandung kombinasi huruf/angka serta simbol.')).toBeVisible()

    expect(problems).toEqual([])
  })

  test('#6 Kelola Corporate: file teks bernama .png ditolak saat dipilih', async ({ page }) => {
    const problems = watchProblems(page)
    await login(page, 'stest-admin01', PASSWORD)
    await page.goto('/master-data/corporates')

    await page.locator('button', { hasText: 'Tambah Corporate' }).click()
    await page.locator('input[type="file"]').setInputFiles({
      name: 'logo.png',
      mimeType: 'image/png',
      buffer: Buffer.from('ini bukan gambar'),
    })
    await expect(page.getByText('Logo harus berupa file gambar JPG atau PNG yang valid.')).toBeVisible()
    await expect(page.locator('img[alt="Pratinjau logo baru"]')).toHaveCount(0)

    expect(problems).toEqual([])
  })

  test('#5 Kelola Business Unit: hapus BU yang masih punya Production Line/Station ditolak dengan rincian', async ({ page }) => {
    const problems = watchProblems(page)
    await login(page, 'stest-admin01', PASSWORD)
    await page.goto('/master-data/business-units')

    const row = await findRow(page, 'Mill Station Baru')
    await row.locator('button', { hasText: 'Hapus' }).click()
    await row.locator('button', { hasText: 'Ya, Hapus' }).click()
    await expect(page.locator('.kc-alert')).toContainText('tidak dapat dihapus')
    await expect(page.locator('.kc-alert')).toContainText('Production Line')
    await expect(await findRow(page, 'Mill Station Baru')).toBeVisible()

    expect(problems).toEqual([])
  })

  test('#15 tombol chatbot tidak menutupi tombol Hapus baris terakhir', async ({ page }) => {
    const problems = watchProblems(page)
    await page.setViewportSize({ width: 1280, height: 720 })
    await login(page, 'stest-admin01', PASSWORD)
    await page.goto('/master-data/stations')

    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
    const lastDelete = page.locator('.kc-table tbody tr').last().locator('button', { hasText: 'Hapus' })
    const fab = page.locator('.chatbot-widget')
    const a = await lastDelete.boundingBox()
    const b = await fab.boundingBox()
    expect(a && b).toBeTruthy()
    const overlap = a!.x < b!.x + b!.width && a!.x + a!.width > b!.x && a!.y < b!.y + b!.height && a!.y + a!.height > b!.y
    expect(overlap).toBe(false)

    expect(problems).toEqual([])
  })
})
