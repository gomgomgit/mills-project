/**
 * Tangkapan layar bahan sales deck — sisi MOBILE (aplikasi Operator).
 *
 * Tiap bidikan memeriksa layarnya berisi sebelum difoto; foto layar kosong
 * tidak berguna untuk bahan jualan. Data dari mill_smart_log_e2e (demo stack).
 */
import { test, expect, type Page } from '@playwright/test'
import { mkdirSync } from 'node:fs'

const OUT = '../docs/sales/screenshots'
const AKUN = { username: 'operator01', password: 'Passw0rd!' }

async function masuk(page: Page) {
  await page.goto('/login', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)
  await page.locator('input[type="text"], #username').first().fill(AKUN.username)
  await page.locator('input[type="password"], #password').first().fill(AKUN.password)
  await page.locator('button[type="submit"]').first().click()
  await page.waitForTimeout(3000)
}

async function bidik(page: Page, nama: string, wajib: (string | RegExp)[], penuh = false) {
  mkdirSync(OUT, { recursive: true })
  for (const t of wajib) {
    await expect(
      typeof t === 'string' ? page.getByText(t, { exact: false }).first() : page.getByText(t).first(),
      `bidikan "${nama}" DIBATALKAN: layar tidak memuat ${t}`,
    ).toBeVisible({ timeout: 20000 })
  }
  await page.waitForTimeout(900)
  await page.screenshot({ path: `${OUT}/${nama}.png`, fullPage: penuh })
  console.log(`  ✓ ${nama}.png`)
}

/**
 * Station List menampilkan grid stasiun hanya setelah Production Line dipilih.
 *
 * Pemilih line di mobile BUKAN <select>: ia tombol production-line-switcher
 * yang membuka daftar production-line-option-<id>. Saat belum ada line
 * terpilih, pemilihnya langsung terbuka menggantikan grid, jadi tombol
 * pembukanya belum tentu ada.
 */
async function pilihLine(page: Page) {
  await page.goto('/stations', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2500)
  const pembuka = page.getByTestId('production-line-switcher')
  if (await pembuka.count()) { await pembuka.click(); await page.waitForTimeout(800) }
  const opsi = page.locator('[data-testid^="production-line-option-"]')
  if (await opsi.count()) { await opsi.first().click(); await page.waitForTimeout(2200) }
}

test('01 login mobile', async ({ page }) => {
  await page.goto('/login', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1500)
  await bidik(page, 'mob-01-login', [], false)
})

test('02 home', async ({ page }) => {
  await masuk(page)
  await page.goto('/home', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2500)
  await bidik(page, 'mob-02-home', ['Production Process Activity'])
})

test('03 station list', async ({ page }) => {
  await masuk(page)
  await pilihLine(page)
  await bidik(page, 'mob-03-station-list', ['Weighbridge'])
})

test('04 monitor stasiun', async ({ page }) => {
  await masuk(page)
  await pilihLine(page)
  await page.goto('/stations/depricarping/monitor', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2500)
  await bidik(page, 'mob-04-monitor-depricarping', ['Depricarping'])
})

test('05 form stasiun', async ({ page }) => {
  await masuk(page)
  await pilihLine(page)
  await page.goto('/stations/depricarping/monitor', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2200)
  const tambah = page.getByRole('button', { name: /tambah|buat|baru|\+/i }).first()
  if (await tambah.count()) { await tambah.click(); await page.waitForTimeout(3000) }
  await bidik(page, 'mob-05-form-depricarping', [])
})

test('06 data preview', async ({ page }) => {
  await masuk(page)
  await pilihLine(page)
  await page.goto('/stations/depricarping/preview', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2800)
  await bidik(page, 'mob-06-data-preview-depricarping', [])
})

test('07 dashboard reporting', async ({ page }) => {
  await masuk(page)
  await page.goto('/dashboard-reporting', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2500)
  await bidik(page, 'mob-07-dashboard-reporting', [])
})
