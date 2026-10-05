import { test, expect, type BrowserContext, type Page } from '@playwright/test'
import { login } from './helpers'

/**
 * loading-states.spec.ts — audit loading state 2026-10-05, dibuktikan di
 * browser nyata pada 390x844:
 *   - baca/tulis SQLite lokal yang lambat menampilkan LoadingState / tombol
 *     sibuk, lalu hilang;
 *   - ketukan ganda New Data membuat tepat SATU draft (dulu dua), Pause
 *     menulis sekali dan footer Form tidak bergeser;
 *   - Sinkronisasi dan Ekspor CSV menampilkan status sibuk selama API lambat
 *     dan Ekspor ganda hanya mengirim satu permintaan.
 *
 * Jeda buatan HANYA ada di uji ini: query/run elemen <jeep-sqlite> dibungkus
 * lewat addInitScript (aktif bila window.__slowDbMs > 0), dan API diperlambat
 * dengan page.route. Tidak ada kode penunda yang ikut dikirim di aplikasi.
 */

test.use({ viewport: { width: 390, height: 844 } })

async function installSlowLocalDb(context: BrowserContext): Promise<void> {
  await context.addInitScript(() => {
    const timer = setInterval(() => {
      const el = document.querySelector('jeep-sqlite') as (HTMLElement & Record<string, unknown>) | null
      if (!el || typeof el.query !== 'function' || el.__slowPatched) return
      for (const method of ['query', 'run']) {
        const original = (el[method] as (...args: unknown[]) => Promise<unknown>).bind(el)
        Object.defineProperty(el, method, {
          configurable: true,
          value: async (...args: unknown[]) => {
            const ms = (window as unknown as { __slowDbMs?: number }).__slowDbMs ?? 0
            if (ms > 0) await new Promise((resolve) => setTimeout(resolve, ms))
            return original(...args)
          },
        })
      }
      el.__slowPatched = true
      clearInterval(timer)
    }, 5)
  })
}

const slowDb = (page: Page, ms: number) => page.evaluate((value) => ((window as unknown as { __slowDbMs: number }).__slowDbMs = value), ms)

function watchErrors(page: Page): string[] {
  const problems: string[] = []
  page.on('pageerror', (error) => problems.push(`pageerror: ${error.message}`))
  page.on('console', (message) => {
    if (message.type() === 'error') problems.push(`console.error: ${message.text()}`)
  })
  page.on('response', (response) => {
    if (response.status() >= 400) problems.push(`HTTP ${response.status()} ${response.url()}`)
  })
  return problems
}

async function noHorizontalOverflow(page: Page): Promise<void> {
  expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0)
}

async function countBoilerRoomRecords(page: Page): Promise<number> {
  return page.evaluate(async () => {
    const db = (window as unknown as { __mslTestDb: { query: (sql: string) => Promise<Array<{ c: number }>> } }).__mslTestDb
    const rows = await db.query('SELECT COUNT(*) AS c FROM boiler_room_record')
    return Number(rows[0].c)
  })
}

test.describe('Loading state & penjaga aksi ganda (mobile)', () => {
  test('Monitor -> Form Boiler Room: LoadingState saat lambat, New Data & Pause ganda hanya menulis sekali, footer stabil', async ({
    page,
    context,
  }) => {
    const problems = watchErrors(page)
    await installSlowLocalDb(context)
    await login(page)
    await page.goto('/stations/boiler-room/monitor')
    await expect(page.getByTestId('new-data-button')).toBeEnabled()

    // Data Preview lalu kembali: daftar dimuat ulang dengan SQLite lambat.
    await slowDb(page, 700)
    await page.getByTestId('load-data-button').click()
    await expect(page.getByTestId('list-loading')).toBeVisible()
    await expect(page.getByTestId('list-loading')).toBeHidden({ timeout: 15_000 })
    await page.getByTestId('back-button').first().click()
    await expect(page.getByTestId('draft-list-loading')).toBeVisible()
    await expect(page.getByTestId('draft-list-loading')).toBeHidden({ timeout: 15_000 })

    const before = await (async () => {
      await slowDb(page, 0)
      return countBoilerRoomRecords(page)
    })()

    await slowDb(page, 600)
    const newData = page.getByTestId('new-data-button')
    await newData.dblclick()
    await expect(newData).toHaveAttribute('aria-busy', 'true')
    await expect(newData).toHaveText('Membuat…')
    await page.waitForURL('**/boiler-room/form/**', { timeout: 15_000 })
    await expect(page.getByTestId('form-loading')).toBeVisible()
    await expect(page.getByTestId('pause-button')).toBeVisible({ timeout: 20_000 })
    await noHorizontalOverflow(page)

    await slowDb(page, 0)
    expect(await countBoilerRoomRecords(page)).toBe(before + 1)

    const footerBoxes = async () =>
      Promise.all(['pause-button', 'clear-button', 'save-button'].map((id) => page.getByTestId(id).boundingBox()))
    const idle = await footerBoxes()

    await slowDb(page, 900)
    const pause = page.getByTestId('pause-button')
    await pause.dblclick()
    await expect(pause).toHaveAttribute('aria-busy', 'true')
    await expect(pause).toHaveText('Menyimpan…')
    await expect(page.getByTestId('save-button')).toBeDisabled()
    expect(await footerBoxes()).toEqual(idle)
    await noHorizontalOverflow(page)

    await page.waitForURL('**/boiler-room/monitor', { timeout: 30_000 })
    await slowDb(page, 0)
    expect(await countBoilerRoomRecords(page)).toBe(before + 1)

    expect(problems).toEqual([])
  })

  test('Daftar Stasiun: Sinkronisasi ganda sibuk sekali; Laporan: tanpa "belum ada periode" saat memuat, Ekspor ganda satu permintaan', async ({
    page,
    context,
  }) => {
    const problems = watchErrors(page)
    await installSlowLocalDb(context)
    await login(page)

    await page.goto('/stations')
    await expect(page.getByTestId('sync-button')).toBeEnabled()
    await slowDb(page, 400)
    const sync = page.getByTestId('sync-button')
    await sync.dblclick()
    await expect(sync).toHaveAttribute('aria-busy', 'true')
    await expect(sync).toHaveText('Menyinkronkan…')
    await expect(page.getByTestId('sync-dialog-message')).toBeVisible({ timeout: 30_000 })
    await expect(sync).toHaveText('Sinkronisasi')
    await slowDb(page, 0)

    await page.route('**/api/**', async (route) => {
      await new Promise((resolve) => setTimeout(resolve, 1200))
      await route.continue()
    })
    await page.goto('/reports/boiler-room')
    await expect(page.getByTestId('production-lines-loading')).toBeVisible()
    await expect(page.getByTestId('no-periods')).toHaveCount(0)
    await expect(page.getByTestId('production-lines-loading')).toBeHidden({ timeout: 15_000 })

    const lineSelect = page.getByTestId('production-line-select')
    if (await lineSelect.count()) {
      const value = await lineSelect.locator('option').nth(1).getAttribute('value')
      await lineSelect.selectOption(value!)
    }
    const periodSelect = page.getByTestId('period-select')
    await expect(periodSelect.locator('option').nth(1)).toBeAttached({ timeout: 15_000 })
    const periodValue = await periodSelect.locator('option').nth(1).getAttribute('value')
    await periodSelect.selectOption(periodValue!)
    await expect(page.getByTestId('summary-loading')).toBeVisible()
    await expect(page.getByTestId('summary-loading')).toBeHidden({ timeout: 15_000 })

    const exportRequests: string[] = []
    page.on('request', (request) => {
      if (request.url().includes('/export')) exportRequests.push(request.url())
    })
    const exportButton = page.getByTestId('export-button')
    await exportButton.dblclick()
    await expect(exportButton).toHaveAttribute('aria-busy', 'true')
    await expect(exportButton).toHaveText('Mengekspor…')
    await expect(exportButton).toHaveText('Ekspor CSV', { timeout: 15_000 })
    expect(exportRequests).toHaveLength(1)
    await noHorizontalOverflow(page)

    expect(problems).toEqual([])
  })
})
