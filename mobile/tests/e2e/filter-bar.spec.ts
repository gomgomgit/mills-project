import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId, USERS } from './helpers'

// Audit desain filter 2026-10-05 — keluarga komponen filter bersama
// (src/components/filters/). Spec ini memeriksa perilaku yang BARU: pintasan
// tanggal "Hari ini"/"Semua", tombol × pada kolom cari, ringkasan jumlah,
// satu tombol Reset Filter di bar, chip cakupan Laporan — di viewport ponsel
// 390x844 dan 360x740, tanpa pageerror / console.error / respons >= 400, dan
// tanpa scroll horizontal. Semantik filter tiap layar tetap diuji di spec
// data-preview-* / laporan-* masing-masing.

function toLocalDateString(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

function watchProblems(page: Page): string[] {
  const problems: string[] = []
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`))
  page.on('console', (m) => {
    if (m.type() === 'error') problems.push(`console.error: ${m.text()}`)
  })
  page.on('response', (r) => {
    if (r.status() >= 400) problems.push(`HTTP ${r.status()} ${r.url()}`)
  })
  return problems
}

async function horizontalOverflow(page: Page): Promise<number> {
  return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
}

async function seedRecord(
  page: Page,
  table: string,
  idColumn: string,
  userId: string,
  row: { id: string; code: string; date: string },
): Promise<void> {
  await page.evaluate(
    async ({ table, idColumn, userId, row }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO ${table} (id, ${idColumn}, date, status, created_by, created_at, updated_at) VALUES (?, ?, ?, 'saved', ?, ?, ?)`,
        [row.id, row.code, row.date, userId, now, now],
      )
    },
    { table, idColumn, userId, row },
  )
}

for (const viewport of [
  { width: 390, height: 844 },
  { width: 360, height: 740 },
]) {
  test.describe(`Filter bersama @ ${viewport.width}x${viewport.height}`, () => {
    test.use({ viewport })

    // Satu layar dari tiap gaya lama: sterilizer (dulu `filter-bar`, testid
    // date-filter) dan boiler-room (dulu `filter-row`, testid date-filter-input).
    for (const screen of [
      { path: 'sterilizer', table: 'sterilizer_record', idColumn: 'sterilizer_id', dateId: 'date-filter', searchId: 'search-filter' },
      { path: 'boiler-room', table: 'boiler_room_record', idColumn: 'boiler_room_id', dateId: 'date-filter-input', searchId: 'search-filter-input' },
    ]) {
      test(`Data Preview ${screen.path}: pintasan tanggal, hapus kata kunci, ringkasan, reset`, async ({ page }) => {
        const problems = watchProblems(page)
        await login(page)
        const userId = await getAuthUserId(page)
        const today = toLocalDateString(new Date())
        const prefix = `e2e-fb-${screen.path}-${viewport.width}`
        await seedRecord(page, screen.table, screen.idColumn, userId, { id: `${prefix}-today`, code: 'FB-TODAY', date: `${today}T07:00:00` })
        await seedRecord(page, screen.table, screen.idColumn, userId, { id: `${prefix}-old`, code: 'FB-OLD', date: '2020-01-01T07:00:00' })

        await page.goto(`/stations/${screen.path}/preview`)

        const dateInput = page.getByTestId(screen.dateId)
        const today_ = page.getByTestId(`record-item-${prefix}-today`)
        const old = page.getByTestId(`record-item-${prefix}-old`)

        // Default tidak berubah: tanggal hari ini.
        await expect(dateInput).toHaveValue(today)
        await expect(today_).toBeVisible()
        await expect(old).toHaveCount(0)
        await expect(page.getByTestId('date-quick-today')).toHaveAttribute('aria-pressed', 'true')
        await expect(page.getByTestId('filter-result-count')).toContainText(/dari \d+ data/)

        // "Semua" = filter tanggal kosong.
        await page.getByTestId('date-quick-all').click()
        await expect(dateInput).toHaveValue('')
        await expect(old).toBeVisible()
        await expect(today_).toBeVisible()

        // Kata kunci + tombol ×.
        await page.getByTestId(screen.searchId).fill('fb-old')
        await expect(today_).toHaveCount(0)
        await expect(old).toBeVisible()
        await page.getByTestId(`${screen.searchId}-clear`).click()
        await expect(page.getByTestId(screen.searchId)).toHaveValue('')
        await expect(today_).toBeVisible()

        // "Hari ini" mengembalikan default.
        await page.getByTestId('date-quick-today').click()
        await expect(dateInput).toHaveValue(today)
        await expect(old).toHaveCount(0)

        // Reset Filter: tepat satu tombol, mengosongkan keduanya.
        await page.getByTestId(screen.searchId).fill('tidak-ada-yang-cocok')
        await expect(page.getByTestId('reset-filter-button')).toHaveCount(1)
        await page.getByTestId('reset-filter-button').click()
        await expect(dateInput).toHaveValue('')
        await expect(page.getByTestId(screen.searchId)).toHaveValue('')
        await expect(page.getByTestId('reset-filter-button')).toHaveCount(0)

        // Tanggal tetap terbaca utuh (tidak terpotong oleh pintasan).
        const dateBox = await dateInput.boundingBox()
        expect(dateBox?.width ?? 0).toBeGreaterThanOrEqual(140)

        expect(await horizontalOverflow(page)).toBe(0)
        expect(problems).toEqual([])
      })
    }

    test('Laporan Sterilizer: pemilih dalam panel filter + chip cakupan Mill', async ({ page }) => {
      const problems = watchProblems(page)
      await login(page, USERS.supervisor)
      await page.goto('/reports/sterilizer')

      const periodSelect = page.getByTestId('period-select')
      await expect(periodSelect).toBeVisible()
      // Pemilih Periode berada di dalam panel filter, berlabel.
      await expect(page.getByRole('search', { name: 'Filter laporan' }).getByTestId('period-select')).toBeVisible()
      await expect(page.getByTestId('mill-current')).toContainText('Mill:')

      const box = await periodSelect.boundingBox()
      expect(box?.height ?? 0).toBeGreaterThanOrEqual(44)
      expect(await horizontalOverflow(page)).toBe(0)
      expect(problems).toEqual([])
    })
  })
}
