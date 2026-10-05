import { test, expect, type BrowserContext, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { watchProblems } from './support/page-health'
import { expectRowCountOnAllPages } from './support/paged-table'

/**
 * Loading state web (2026-10-05) — "loading state ketika melakukan action
 * yang butuh waktu untuk read/write data di semua tempat".
 *
 * Request Livewire / ekspor diperlambat DI SISI TEST (route Playwright yang
 * menahan request 1,5 detik), bukan lewat kode aplikasi, supaya keadaan
 * "sedang memuat" bisa diamati:
 *   - bar progres global (#ld-progress) muncul,
 *   - tombol Simpan menampilkan "Menyimpan…" dan TIDAK bisa diklik ganda —
 *     tepat satu request save dan tepat satu record tercipta,
 *   - area tabel Data Browser meredup (aria-busy) saat filter berubah lalu
 *     pulih,
 *   - tautan Ekspor menampilkan "Mengekspor…" sampai respons unduhan tiba,
 *     klik kedua diabaikan (satu request ekspor).
 * Setiap test juga gagal pada pageerror / console.error / HTTP >= 400.
 */

const DELAY_MS = 1500

async function slowLivewire(context: BrowserContext): Promise<void> {
  await context.route('**/livewire/update', async (route) => {
    await new Promise((resolve) => setTimeout(resolve, DELAY_MS))
    await route.continue()
  })
}

function countLivewireCalls(page: Page, method: string): { count: number } {
  const box = { count: 0 }
  page.on('request', (request) => {
    if (request.url().includes('/livewire/update') && (request.postData() ?? '').includes(`"method":"${method}"`)) box.count++
  })
  return box
}

test.describe('Loading state web', () => {
  test('Simpan: bar progres + "Menyimpan…", klik ganda tetap SATU request & SATU record', async ({ page, context }) => {
    const problems = watchProblems(page)
    await login(page, 'corptest-admin01', PASSWORD)
    await page.goto('/master-data/corporates')

    await page.locator('button', { hasText: 'Tambah Corporate' }).click()
    const name = `PT Loading ${Date.now()}`
    await page.locator('#name').fill(name)
    await page.locator('#corporate_code').fill(`CORP-LOAD-${Date.now()}`)

    await slowLivewire(context)
    const saves = countLivewireCalls(page, 'save')
    const save = page.locator('button[type="submit"]', { hasText: 'Simpan' })
    await save.click()
    await save.click({ force: true, timeout: 800 }).catch(() => {})
    await save.dblclick({ force: true, timeout: 800 }).catch(() => {})

    await expect(save).toBeDisabled()
    await expect(save.getByText('Menyimpan…')).toBeVisible()
    await expect(page.locator('#ld-progress')).toHaveClass(/ld-progress--active/)
    await expect(page.locator('#ld-live')).toHaveText('Memuat…')

    await expect(page.locator('#ld-progress')).not.toHaveClass(/ld-progress--active/, { timeout: 10_000 })
    expect(saves.count).toBe(1)
    await expectRowCountOnAllPages(page, name, 1)
    expect(problems).toEqual([])
  })

  test('Data Browser: area tabel meredup saat filter berubah, Ekspor CSV sibuk sampai unduhan tiba', async ({ page, context }) => {
    const problems = watchProblems(page)
    await login(page, 'kernelplanttest-browse01', PASSWORD)
    await page.goto('/data/kernel-plant')

    const region = page.locator('.kp-table-wrap')
    await expect(region).not.toHaveClass(/ld-region--busy/)

    await slowLivewire(context)
    await page.locator('#date_from').fill('2026-01-01')
    await expect(region).toHaveClass(/ld-region--busy/)
    await expect(region).toHaveAttribute('aria-busy', 'true')
    await expect(region).not.toHaveClass(/ld-region--busy/, { timeout: 10_000 })
    await expect(region).not.toHaveAttribute('aria-busy', /.*/)

    // Ekspor: tautan <a target=_blank> → unduhan di tab baru. Ditahan 1,5 s.
    let exportRequests = 0
    context.on('request', (request) => {
      if (request.url().includes('/kernel-plant-records/export')) exportRequests++
    })
    await context.route(/kernel-plant-records\/export/, async (route) => {
      await new Promise((resolve) => setTimeout(resolve, DELAY_MS))
      await route.continue()
    })

    const link = page.locator('.kp-browser__export a', { hasText: 'Ekspor CSV' })
    // Didaftarkan SEBELUM klik (sama dengan spec data-browser-*): unduhan
    // dari tab baru tetap dilaporkan ke halaman pembukanya.
    const downloadPromise = page.waitForEvent('download')
    await link.click()
    await link.click({ force: true, timeout: 800 }).catch(() => {})
    await expect(link).toHaveAttribute('aria-busy', 'true')
    await expect(link.getByText('Mengekspor…')).toBeVisible()

    const download = await downloadPromise
    expect(download.suggestedFilename()).toMatch(/^kernel-plant-records_.*\.csv$/)
    await expect(link).not.toHaveAttribute('aria-busy', /.*/, { timeout: 5_000 })
    await expect(link.getByText('Ekspor CSV')).toBeVisible()
    expect(exportRequests).toBe(1)
    expect(problems).toEqual([])
  })
})
