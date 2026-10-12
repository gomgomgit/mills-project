/**
 * Potongan per bagian dari Laporan Weighbridge — bahan SLIDE.
 *
 * Halaman penuhnya 11.792px: bagus sebagai rujukan, tidak terpakai di slide.
 * Di sini tiap bagian dipotret sebagai elemennya sendiri, jadi rasio gambarnya
 * wajar untuk ditempel di satu slide tanpa diperkecil sampai tak terbaca.
 */
import { test, expect, type Page } from '@playwright/test'
import { mkdirSync } from 'node:fs'

const OUT = '../docs/sales/screenshots'
const PW = 'Passw0rd!'

async function masuk(page: Page, u: string) {
  await page.goto('/login', { waitUntil: 'domcontentloaded' })
  await page.locator('#username').fill(u)
  await page.locator('#password').fill(PW)
  await page.locator('button.login-button').click()
  await page.waitForURL((x) => !x.pathname.startsWith('/login'), { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)
}

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

test('potongan laporan weighbridge', async ({ page }) => {
  mkdirSync(OUT, { recursive: true })
  await masuk(page, 'millmanagement-a')
  await page.goto('/reports/weighbridge', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1500)
  await page.locator('#production-line-select').selectOption({ label: 'Line 1' })
  await page.waitForTimeout(1800)
  const sept = (await page.locator('#period-select option').allTextContents()).find((o) => /September 2026/.test(o))
  expect(sept, 'periode September 2026 tidak ada').toBeTruthy()
  await page.locator('#period-select').selectOption({ label: sept! })
  await page.waitForTimeout(3000)
  await sembunyikanChatbot(page)

  // Daftar bagian halaman, apa adanya — dipotret satu per satu.
  const bagian = page.locator('main section, main > div > section')
  const n = await bagian.count()
  console.log('bagian ditemukan:', n)
  let i = 0
  for (const el of await bagian.all()) {
    const kotak = await el.boundingBox()
    if (!kotak || kotak.height < 120 || kotak.width < 300) continue   // lewati yang terlalu kecil
    i += 1
    const judul = (await el.innerText()).replace(/\s+/g, ' ').trim().slice(0, 48)
    await el.screenshot({ path: `${OUT}/web-04x-laporan-bagian-${String(i).padStart(2,'0')}.png` })
    console.log(`  ✓ bagian-${String(i).padStart(2,'0')} (${Math.round(kotak.width)}×${Math.round(kotak.height)}) — ${judul}`)
    if (i >= 10) break
  }
  expect(i, 'tidak ada satu bagian pun yang cukup besar untuk dipotret').toBeGreaterThan(2)
})
