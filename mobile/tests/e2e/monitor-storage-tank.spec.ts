import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-066--monitor-storage-tank / usecase-091--monitor-storage-tank
//
// Mirrors monitor-effluent-plant.spec.ts — MonitorStorageTankView.vue is a
// structural copy of MonitorEffluentPlantView.vue: a "Hari Ini" 2-card
// counter row, a scrollable list of the current user's ongoing/paused local
// drafts (every row labeled uniformly "Pause"), 'New Data' / 'Load Data'
// actions, breadcrumb and hamburger nav-menu.
//
// Scenario source: the screen's tech-spec test_scenarios[*].browser_test
// (New Data, Lanjutkan Draft/Pause, Load Data, Belum Ada Draft, Belum Ada
// Data Hari Ini) plus the business-logic/edge cases the reference specs
// also cover (counter with data, many drafts scroll, breadcrumb, hamburger).
//
// Rows are seeded directly via the dev-only `window.__mslTestDb` bridge —
// there is no in-app flow that produces several drafts at once.

const STATION = 'storage-tank'
const RECORD_TABLE = 'storage_tank_record'
const DETAIL_TABLE = 'storage_tank_detail'
const ID_COLUMN = 'storage_tank_id'
const READING_COLUMN = 'cpo_sounding_depth_mm'
const ID_FIELD = '#field-storage-tank-id'
const LABEL_LOWER = 'storage tank'

async function seedDraft(
  page: Page,
  userId: string,
  overrides: { id: string; status?: 'draft_ongoing' | 'draft_paused'; stationRecordId?: string | null; updatedAt?: string },
): Promise<void> {
  await page.evaluate(
    async ({ table, idColumn, userId, id, status, stationRecordId, updatedAt }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = updatedAt ?? new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO ${table} (id, status, ${idColumn}, date, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'draft_ongoing', stationRecordId ?? null, now, userId, now, now],
      )
    },
    {
      table: RECORD_TABLE,
      idColumn: ID_COLUMN,
      userId,
      id: overrides.id,
      status: overrides.status,
      stationRecordId: overrides.stationRecordId,
      updatedAt: overrides.updatedAt,
    },
  )
}

/**
 * Seeds a header row (status/date) plus `hours.length` detail rows for the
 * "Hari Ini" counter (getTodaySummary(): plain COUNT of records dated today,
 * any status, and plain COUNT of their detail rows).
 */
async function seedRecordForCounter(
  page: Page,
  userId: string,
  overrides: { id: string; status?: 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'; date: string; hours: number[] },
): Promise<void> {
  await page.evaluate(
    async ({ recordTable, detailTable, fkColumn, readingColumn, userId, id, status, date, hours }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO ${recordTable} (id, status, date, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'saved', date, userId, now, now],
      )

      for (const [i, hour] of hours.entries()) {
        await db.run(
          `INSERT OR REPLACE INTO ${detailTable} (id, ${fkColumn}, time_slot, ${readingColumn}, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?, ?)`,
          [`${id}-detail-${i}`, id, `${String(hour).padStart(2, '0')}:00`, 10 + hour, now, now],
        )
      }
    },
    {
      recordTable: RECORD_TABLE,
      detailTable: DETAIL_TABLE,
      fkColumn: `${RECORD_TABLE}_id`,
      readingColumn: READING_COLUMN,
      userId,
      id: overrides.id,
      status: overrides.status,
      date: overrides.date,
      hours: overrides.hours,
    },
  )
}

function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const MONITOR_URL = `/stations/${STATION}/monitor`
const FORM_URL_RE = new RegExp(`/stations/${STATION}/form/[^/]+$`)

test.describe('Monitor Storage Tank (screen-066)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('Monitor Storage Tank — New Data (fresh draft, grid starts empty)', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedDraft(page, userId, { id: `e2e-${STATION}-existing`, stationRecordId: 'ST-EXISTING' })

    await page.goto(MONITOR_URL)
    await expect(page.getByTestId(`draft-item-e2e-${STATION}-existing`)).toBeVisible()

    await page.getByTestId('new-data-button').click()
    await page.waitForURL(FORM_URL_RE)
    await expect(page).not.toHaveURL(new RegExp(`/stations/${STATION}/form/e2e-${STATION}-existing$`))

    // Rows are not pre-created — the grid starts empty, only "Tambah baris".
    await expect(page.getByTestId('add-detail-row-button')).toBeVisible()
    await expect(page.getByTestId(`${STATION}-detail-row`)).toHaveCount(0)
  })

  test('Monitor Storage Tank — Lanjutkan Draft/Pause (opens Form pre-filled)', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedDraft(page, userId, { id: `e2e-${STATION}-lanjutkan`, status: 'draft_paused', stationRecordId: 'ST-LANJUTKAN' })

    await page.goto(MONITOR_URL)

    const item = page.getByTestId(`draft-item-e2e-${STATION}-lanjutkan`)
    await expect(item).toBeVisible()
    await expect(item).toContainText('Pause')
    await expect(item).toContainText('ST-LANJUTKAN')

    await item.click()
    await page.waitForURL(`**/stations/${STATION}/form/e2e-${STATION}-lanjutkan`)
    await expect(page.locator(ID_FIELD)).toHaveValue('ST-LANJUTKAN')

    // Tapping an item does not transition its status.
    await page.goto(MONITOR_URL)
    await expect(page.getByTestId(`draft-item-e2e-${STATION}-lanjutkan`)).toContainText('Pause')
  })

  test('Monitor Storage Tank — Load Data (list mode with date and search filters)', async ({ page }) => {
    await page.goto(MONITOR_URL)

    await page.getByTestId('load-data-button').click()
    await page.waitForURL(new RegExp(`/stations/${STATION}/preview$`))
    await expect(page.getByTestId('date-filter-input')).toBeVisible()
    await expect(page.getByTestId('search-filter-input')).toBeVisible()
  })

  test('Monitor Storage Tank — Tap Breadcrumb', async ({ page }) => {
    await page.goto(MONITOR_URL)

    await page.getByTestId('breadcrumb-production-process-activity').click()
    await page.waitForURL('**/stations')
  })

  test('Monitor Storage Tank — Buka Menu Hamburger', async ({ page }) => {
    await page.goto(MONITOR_URL)
    await expect(page.getByTestId('hamburger-button')).toBeVisible()
    await expect(page.getByTestId('nav-menu')).toBeHidden()

    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu-change-password')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })

  test('Monitor Storage Tank — Belum Ada Draft', async ({ page }) => {
    await page.goto(MONITOR_URL)

    await expect(page.getByTestId('draft-list-empty')).toContainText(`Belum ada draft ${LABEL_LOWER} tersimpan.`)
    await expect(page.getByTestId('new-data-button')).toBeVisible()
    await expect(page.getByTestId('new-data-button')).toBeEnabled()
  })

  test('Monitor Storage Tank — Banyak Draft Menumpuk (list scrollable, all reachable)', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const count = 12
    for (let i = 0; i < count; i += 1) {
      await seedDraft(page, userId, {
        id: `e2e-${STATION}-many-${i}`,
        status: i % 2 === 0 ? 'draft_ongoing' : 'draft_paused',
        stationRecordId: `ST-MANY-${i}`,
        updatedAt: new Date(Date.now() - i * 60_000).toISOString(),
      })
    }

    await page.goto(MONITOR_URL)

    await expect(page.locator('[data-testid^="draft-item-"]')).toHaveCount(count)
    // ORDER BY updated_at DESC — the oldest one is last; it must still be reachable.
    const last = page.getByTestId(`draft-item-e2e-${STATION}-many-${count - 1}`)
    await last.scrollIntoViewIfNeeded()
    await expect(last).toBeInViewport()
    await expect(last).toContainText('Pause')
  })

  test('Monitor Storage Tank — Counter Hari Ini Menampilkan Data', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = new Date()
    const yesterday = new Date(today.getTime() - 24 * 60 * 60 * 1000)

    // 2 today-dated records (any status) — 2 + 1 detail rows = 3.
    await seedRecordForCounter(page, userId, { id: `e2e-${STATION}-counter-today-1`, status: 'saved', date: toLocalDateString(today), hours: [7, 9] })
    await seedRecordForCounter(page, userId, { id: `e2e-${STATION}-counter-today-2`, status: 'draft_ongoing', date: toLocalDateString(today), hours: [10] })
    // Not today — must NOT be counted.
    await seedRecordForCounter(page, userId, { id: `e2e-${STATION}-counter-yesterday`, status: 'saved', date: toLocalDateString(yesterday), hours: [7, 8, 9, 10, 11] })

    await page.goto(MONITOR_URL)

    await expect(page.getByTestId(`counter-count-${STATION}-record`)).toHaveText('2')
    await expect(page.getByTestId('counter-detail-row-count')).toHaveText('3')
  })

  test('Monitor Storage Tank — Belum Ada Data Hari Ini', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000)

    await seedRecordForCounter(page, userId, { id: `e2e-${STATION}-counter-none-today`, status: 'saved', date: toLocalDateString(yesterday), hours: [7] })

    await page.goto(MONITOR_URL)

    await expect(page.getByTestId(`counter-count-${STATION}-record`)).toHaveText('0')
    await expect(page.getByTestId('counter-detail-row-count')).toHaveText('0')
  })
})
