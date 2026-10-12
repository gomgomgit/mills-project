/**
 * Tangkapan layar bahan sales deck — sisi WEB.
 *
 * BUKAN uji fitur: tidak ada asersi benar/salah tentang perilaku. Tetapi tiap
 * bidikan MEMERIKSA layarnya benar-benar berisi sebelum difoto — tangkapan
 * laporan kosong tidak berguna untuk bahan jualan, dan kegagalan yang diam
 * akan menghasilkan belasan gambar rapi yang tidak menunjukkan apa pun.
 *
 * Data: mill_smart_log_e2e, migrate:fresh --seed (demo stack SAJA, tanpa
 * fixture uji) — PT Sawit Nusantara / Business Unit A, periode "September
 * 2026" (tertutup, sebulan penuh) dan "Oktober 2026" (draft).
 *
 * Akun dipilih per layar menurut peran yang BOLEH membukanya:
 *   admin            → struktur mills, periode pelaporan, user & role
 *   millmanagement-a → laporan (terarah otomatis ke mill sendiri), mills setting
 *   supervisor01     → data browser + detail (peran operasional sehari-hari)
 */
import { test, expect, type Page } from '@playwright/test'
import { mkdirSync } from 'node:fs'

const OUT = '../docs/sales/screenshots'
const PW = 'Passw0rd!'
const LINE = 'Line 1'
const PERIODE = /September 2026/

// Tiap bidikan berdiri sendiri: satu yang gagal tidak boleh membatalkan sisanya.

async function masuk(page: Page, username: string) {
  await page.goto('/login', { waitUntil: 'domcontentloaded' })
  await page.locator('#username').fill(username)
  await page.locator('#password').fill(PW)
  await page.locator('button.login-button').click()
  await page.waitForURL((u) => !u.pathname.startsWith('/login'), { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)
}

/** Memotret HANYA setelah tiap penanda `wajib` terlihat. */
/** Menyembunyikan widget chatbot yang mengambang.
 *
 * Bukan karena ia cacat — ia fitur produk, dan dipotret tersendiri pada
 * bidikan "asisten-ai". Tetapi gelembungnya mengambang di sudut kanan bawah
 * dan menutupi isi pada hampir setiap potongan, sehingga gambar yang dipakai
 * di slide jadi kotor tanpa menambah informasi.
 */
async function sembunyikanChatbot(page: Page) {
  await page.addStyleTag({
    content: `[data-testid="chatbot-widget-bubble"], [data-testid^="chatbot-"] { display: none !important; }`,
  }).catch(() => undefined)
}

async function bidik(page: Page, nama: string, wajib: (string | RegExp)[], penuh = true) {
  mkdirSync(OUT, { recursive: true })
  for (const t of wajib) {
    await expect(
      typeof t === 'string' ? page.getByText(t, { exact: false }).first() : page.getByText(t).first(),
      `bidikan "${nama}" DIBATALKAN: layar tidak memuat ${t} — fotonya akan kosong`,
    ).toBeVisible({ timeout: 20000 })
  }
  await sembunyikanChatbot(page)
  await page.waitForTimeout(1000)
  await page.screenshot({ path: `${OUT}/${nama}.png`, fullPage: penuh })
  console.log(`  ✓ ${nama}.png`)
}

/** Laporan periode: wajib pilih Production Line dulu, lalu periode. */
async function siapkanLaporan(page: Page, rute: string) {
  await page.goto(rute, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1500)
  await page.locator('#production-line-select').selectOption({ label: LINE })
  await page.waitForTimeout(1800)
  const opsi = await page.locator('#period-select option').allTextContents()
  const sept = opsi.find((o) => PERIODE.test(o))
  expect(sept, `periode September tidak ada di ${rute}; opsi: ${opsi.join(' | ')}`).toBeTruthy()
  await page.locator('#period-select').selectOption({ label: sept! })
  await page.waitForTimeout(2500)
}

test('01 login', async ({ page }) => {
  await page.goto('/login', { waitUntil: 'domcontentloaded' })
  await bidik(page, 'web-01-login', ['Username'], false)
})

test('02 dashboard', async ({ page }) => {
  await masuk(page, 'millmanagement-a')
  await page.goto('/dashboard', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2200)
  await bidik(page, 'web-02-dashboard', ['Dashboard'])
})

test('03 pemilih laporan stasiun', async ({ page }) => {
  await masuk(page, 'millmanagement-a')
  await page.goto('/reports', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1800)
  // Grid stasiun baru muncul SESUDAH Production Line dipilih — sebelum itu
  // layar hanya menampilkan arahan, dan fotonya tidak menunjukkan apa pun.
  await page.locator('#production-line-select').selectOption({ label: LINE })
  await page.waitForTimeout(2000)
  await bidik(page, 'web-03-pemilih-laporan-stasiun', ['Weighbridge', 'Sterilizer'])
})

test('04 laporan weighbridge', async ({ page }) => {
  await masuk(page, 'millmanagement-a')
  await siapkanLaporan(page, '/reports/weighbridge')
  await bidik(page, 'web-04-laporan-weighbridge', ['September 2026', /Total|TOTAL/])
})

test('05 laporan boiler room', async ({ page }) => {
  await masuk(page, 'millmanagement-a')
  await siapkanLaporan(page, '/reports/boiler-room')
  await bidik(page, 'web-05-laporan-boiler-room', ['September 2026'])
})

test('06 laporan manajemen', async ({ page }) => {
  await masuk(page, 'millmanagement-a')
  await page.goto('/reports/management', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2500)
  await bidik(page, 'web-06-laporan-manajemen', ['Laporan Manajemen'])
})

test('07 data browser weighbridge', async ({ page }) => {
  await masuk(page, 'supervisor01')
  await page.goto('/data/weighbridge', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2000)
  await bidik(page, 'web-07-data-browser-weighbridge', ['Data Browser Weighbridge', 'Ekspor CSV'])
})

test('08 detail record', async ({ page }) => {
  await masuk(page, 'supervisor01')
  await page.goto('/data/boiler-room', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2000)
  const baris = page.locator('tbody tr').filter({ hasNot: page.locator('[colspan]') }).first()
  await expect(baris, 'tidak ada baris record Boiler Room untuk dibuka').toBeVisible({ timeout: 15000 })
  await baris.click()
  await page.waitForLoadState('domcontentloaded')
  await page.waitForTimeout(2000)
  await bidik(page, 'web-08-detail-record-boiler-room', ['Detail'])
})

test('09 struktur mills', async ({ page }) => {
  await masuk(page, 'admin')
  await page.goto('/master-data/tree-view', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2200)
  await bidik(page, 'web-09-struktur-mills', ['Struktur Mills', 'PT Sawit Nusantara'])
})

test('10 kelola periode pelaporan', async ({ page }) => {
  await masuk(page, 'admin')
  await page.goto('/master-data/periods', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2000)
  await bidik(page, 'web-10-kelola-periode-pelaporan', ['Kelola Periode Pelaporan'])
})

test('11 mills setting', async ({ page }) => {
  await masuk(page, 'millmanagement-a')
  await page.goto('/mill-settings', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2200)
  await bidik(page, 'web-11-mills-setting', ['Mills Setting'])
})

test('12 kelola user & role', async ({ page }) => {
  await masuk(page, 'admin')
  await page.goto('/users', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2000)
  await bidik(page, 'web-12-kelola-user-role', ['Kelola User & Role'])
})

test('13 production process activity', async ({ page }) => {
  await masuk(page, 'supervisor01')
  await page.goto('/production-process-activity', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2000)
  await bidik(page, 'web-13-production-process-activity', ['Weighbridge'])
})


test('14 asisten AI', async ({ page }) => {
  await masuk(page, 'millmanagement-a')
  await page.goto('/dashboard', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2000)
  const gelembung = page.getByTestId('chatbot-widget-bubble')
  await expect(gelembung, 'gelembung asisten tidak ada di layar').toBeVisible({ timeout: 15000 })
  await gelembung.click()
  await page.waitForTimeout(2500)
  mkdirSync(OUT, { recursive: true })
  await page.screenshot({ path: `${OUT}/web-14-asisten-ai.png`, fullPage: false })
  console.log('  ✓ web-14-asisten-ai.png')
})
