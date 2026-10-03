import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-061--monitor-solid-waste-disposal / usecase-061--monitor-solid-waste-disposal
//
// Solid Waste Disposal is an EVENT-LOG station (header record + one
// `solid_waste_disposal_detail` row per disposal event, `event_date` per
// row) — the Monitor shows a "Hari Ini" 2-card counter (records dated
// today, any status / detail rows of those records), a "Kejadian Terakhir"
// card (most recently created detail row across ALL of the user's records,
// any date), and the user's draft/pause list.
//
// Capacitor mobile screen, browser-testable via the Vite dev server. Rows
// are seeded straight into the local SQLite via the dev-only
// `window.__mslTestDb` bridge (same convention as monitor-cages-track /
// monitor-effluent-plant) — there is no UI flow that produces several
// records at once.

type DbBridge = { run: (sql: string, params?: unknown[]) => Promise<unknown> }

async function seedRecord(
  page: Page,
  userId: string,
  record: {
    id: string
    status?: 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'
    swdId?: string | null
    date?: string | null
    updatedAt?: string
  },
): Promise<void> {
  await page.evaluate(
    async ({ userId, record }) => {
      const db = (window as unknown as { __mslTestDb: DbBridge }).__mslTestDb
      const now = record.updatedAt ?? new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO solid_waste_disposal_record
           (id, status, solid_waste_disposal_id, date, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)`,
        [record.id, record.status ?? 'saved', record.swdId ?? null, record.date ?? null, userId, now, now],
      )
    },
    { userId, record },
  )
}

async function seedDetail(
  page: Page,
  detail: {
    id: string
    recordId: string
    eventDate?: string | null
    solidWasteType?: string | null
    vehicleNo?: string | null
    createdAt?: string
  },
): Promise<void> {
  await page.evaluate(async (detail) => {
    const db = (window as unknown as { __mslTestDb: DbBridge }).__mslTestDb
    const createdAt = detail.createdAt ?? new Date().toISOString()
    await db.run(
      `INSERT OR REPLACE INTO solid_waste_disposal_detail
         (id, solid_waste_disposal_record_id, event_date, solid_waste_type, vehicle_no, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [detail.id, detail.recordId, detail.eventDate ?? null, detail.solidWasteType ?? null, detail.vehicleNo ?? null, createdAt, createdAt],
    )
  }, detail)
}

function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const STATION_LABEL = 'Solid Waste Disposal'
const MONITOR_PATH = '/stations/solid-waste-disposal/monitor'

/** Home -> Production Process Activity -> (line picker) -> station tile. */
async function openFromStationList(page: Page): Promise<void> {
  await page.getByTestId('menu-card-production-process-activity').click()
  await page.waitForURL('**/stations')

  const picker = page.getByTestId('production-line-picker')
  const firstTile = page.locator('[data-testid^="station-tile-"]').first()
  await expect(picker.or(firstTile).first()).toBeVisible({ timeout: 15_000 })
  if (await picker.isVisible()) {
    await page.locator('[data-testid^="production-line-option-"]').first().click()
  }

  const tile = page.getByText(STATION_LABEL, { exact: true })
  await expect(tile).toBeVisible({ timeout: 15_000 })
  await tile.click()
  await page.waitForURL(`**${MONITOR_PATH}`)
}

test.describe('Monitor Solid Waste Disposal (screen-061)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('Monitor Solid Waste Disposal — menampilkan ringkasan dan list draft (dari Station List)', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    const yesterday = toLocalDateString(new Date(Date.now() - 24 * 60 * 60 * 1000))

    // Two today-dated records (different statuses — both counted) with 2 + 1
    // detail rows => countRecords=2, countEvents=3. A yesterday record with
    // 4 rows must not be counted.
    await seedRecord(page, userId, { id: 'e2e-swd-today-1', status: 'saved', swdId: 'SWD-TODAY-1', date: today })
    await seedRecord(page, userId, { id: 'e2e-swd-today-2', status: 'draft_paused', swdId: 'SWD-TODAY-2', date: today })
    await seedRecord(page, userId, { id: 'e2e-swd-yesterday', status: 'saved', swdId: 'SWD-YESTERDAY', date: yesterday })
    await seedDetail(page, { id: 'e2e-swdd-1', recordId: 'e2e-swd-today-1', eventDate: today, createdAt: '2026-01-01T01:00:00.000Z' })
    await seedDetail(page, { id: 'e2e-swdd-2', recordId: 'e2e-swd-today-1', eventDate: today, createdAt: '2026-01-01T02:00:00.000Z' })
    await seedDetail(page, {
      id: 'e2e-swdd-3',
      recordId: 'e2e-swd-today-2',
      eventDate: today,
      solidWasteType: 'Fiber Terbaru',
      vehicleNo: 'BK 9999 ZZ',
      // Newest created_at of all — this one is the "Kejadian Terakhir".
      createdAt: '2030-01-01T00:00:00.000Z',
    })
    for (let i = 0; i < 4; i += 1) {
      await seedDetail(page, { id: `e2e-swdd-y-${i}`, recordId: 'e2e-swd-yesterday', eventDate: yesterday, createdAt: '2026-01-01T00:00:00.000Z' })
    }

    await openFromStationList(page)

    await expect(page.getByTestId('counter-count-records')).toHaveText('2')
    await expect(page.getByTestId('counter-count-events')).toHaveText('3')

    const lastEvent = page.getByTestId('last-event-card')
    await expect(lastEvent).toContainText('Fiber Terbaru')
    await expect(lastEvent).toContainText('BK 9999 ZZ')

    // Only the draft/pause record is in the list, labeled uniformly "Pause".
    const draftItem = page.getByTestId('draft-item-e2e-swd-today-2')
    await expect(draftItem).toBeVisible()
    await expect(draftItem).toContainText('SWD-TODAY-2')
    await expect(draftItem).toContainText('Pause')
    await expect(page.getByTestId('draft-item-e2e-swd-today-1')).toHaveCount(0)
  })

  test('Monitor Solid Waste Disposal — Kejadian Terakhir tidak dibatasi hari ini', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const lastWeek = toLocalDateString(new Date(Date.now() - 7 * 24 * 60 * 60 * 1000))
    await seedRecord(page, userId, { id: 'e2e-swd-old', status: 'synced', swdId: 'SWD-OLD', date: lastWeek })
    await seedDetail(page, { id: 'e2e-swdd-old', recordId: 'e2e-swd-old', eventDate: lastWeek, solidWasteType: 'Janjang Kosong', vehicleNo: 'BK 1111 AA' })

    await page.goto(MONITOR_PATH)

    await expect(page.getByTestId('last-event-card')).toContainText('Janjang Kosong')
    await expect(page.getByTestId('last-event-card')).toContainText(lastWeek)
    // Old record is not today — counters stay 0.
    await expect(page.getByTestId('counter-count-records')).toHaveText('0')
    await expect(page.getByTestId('counter-count-events')).toHaveText('0')
  })

  test('Monitor Solid Waste Disposal — New Data', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedRecord(page, userId, { id: 'e2e-swd-existing', status: 'draft_ongoing', swdId: 'SWD-EXISTING' })

    await page.goto(MONITOR_PATH)
    await expect(page.getByTestId('draft-item-e2e-swd-existing')).toBeVisible()

    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/solid-waste-disposal\/form\/[^/]+$/)
    await expect(page).not.toHaveURL(/\/stations\/solid-waste-disposal\/form\/e2e-swd-existing$/)

    // The new draft really exists and opens empty.
    await expect(page.locator('#solid_waste_disposal_id')).toBeVisible()
    await expect(page.locator('#solid_waste_disposal_id')).toHaveValue('')
    await expect(page.locator('[data-testid^="detail-row-"]')).toHaveCount(0)
  })

  test('Monitor Solid Waste Disposal — Lanjutkan Draft', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedRecord(page, userId, { id: 'e2e-swd-resume', status: 'draft_paused', swdId: 'SWD-RESUME', date: '2026-09-30' })
    await seedDetail(page, { id: 'e2e-swdd-resume', recordId: 'e2e-swd-resume', eventDate: '2026-09-30', vehicleNo: 'BK 5555 RS' })

    await page.goto(MONITOR_PATH)
    const item = page.getByTestId('draft-item-e2e-swd-resume')
    await expect(item).toContainText('Pause')
    await item.click()

    await page.waitForURL('**/stations/solid-waste-disposal/form/e2e-swd-resume')
    await expect(page.locator('#solid_waste_disposal_id')).toHaveValue('SWD-RESUME')
    await expect(page.getByTestId('detail-event-date-0')).toHaveValue('2026-09-30')

    // Tapping was pure navigation — status is still paused.
    await page.goto(MONITOR_PATH)
    await expect(page.getByTestId('draft-item-e2e-swd-resume')).toContainText('Pause')
  })

  // REGRESI — DIPERBAIKI 2026-10-03 (todayDateString() kini tanggal lokal; dulu UTC): createDraft() stamps `date` with the UTC calendar day
  // (solidWasteDisposalRecordRepo.ts:180 todayDateString(): `new Date().
  // toISOString().slice(0, 10)`), while the "Hari Ini" counter
  // (getTodaySummary: date('now','localtime')), the Form's date fallback and
  // the Data Preview default filter all use the LOCAL day. On a UTC+7 (WIB)
  // device, a New Data tapped between 00:00 and 06:59 local is dated
  // YESTERDAY: it is not counted under "Hari Ini" and is hidden by Data
  // Preview's default today filter. Spec: business_logic 6 "date=hari ini".
  test('Monitor Solid Waste Disposal — New Data dini hari tetap bertanggal hari ini (lokal)', async ({ page }) => {
    const now = new Date()
    const earlyMorning = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 2, 30, 0)
    await page.clock.setFixedTime(earlyMorning)
    await page.goto(MONITOR_PATH)

    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/solid-waste-disposal\/form\/[^/]+$/)
    await expect(page.locator('#solid_waste_disposal_id')).toBeVisible()
    const id = page.url().split('/').pop() as string

    const rows = await page.evaluate(
      async (id) =>
        (window as unknown as { __mslTestDb: { query: (sql: string, p: unknown[]) => Promise<{ date: string }[]> } }).__mslTestDb.query(
          'SELECT date FROM solid_waste_disposal_record WHERE id = ?',
          [id],
        ),
      id,
    )
    expect(rows[0]?.date).toBe(toLocalDateString(earlyMorning))

    await page.goto(MONITOR_PATH)
    await expect(page.getByTestId('counter-count-records')).toHaveText('1')
  })

  test('Monitor Solid Waste Disposal — Load Data', async ({ page }) => {
    await page.goto(MONITOR_PATH)

    await page.getByTestId('load-data-button').click()
    await page.waitForURL(/\/stations\/solid-waste-disposal\/preview$/)

    await expect(page.getByTestId('date-filter')).toBeVisible()
    await expect(page.getByTestId('search-filter')).toBeVisible()
  })

  test('Monitor Solid Waste Disposal — Belum Ada Draft', async ({ page }) => {
    await page.goto(MONITOR_PATH)

    await expect(page.getByTestId('draft-list-empty')).toContainText('Belum ada draft solid waste disposal tersimpan.')
    await expect(page.getByTestId('new-data-button')).toBeVisible()
    await expect(page.getByTestId('new-data-button')).toBeEnabled()
  })

  test('Monitor Solid Waste Disposal — Belum Ada Kejadian Tercatat', async ({ page }) => {
    const userId = await getAuthUserId(page)
    // A record with zero detail rows — still no "event".
    await seedRecord(page, userId, { id: 'e2e-swd-no-events', status: 'saved', swdId: 'SWD-NO-EVENTS', date: toLocalDateString(new Date()) })

    await page.goto(MONITOR_PATH)

    await expect(page.getByTestId('counter-count-records')).toHaveText('1')
    await expect(page.getByTestId('last-event-card')).toContainText('Belum ada kejadian tercatat')
  })

  test('Monitor Solid Waste Disposal — Belum Ada Data Hari Ini', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const yesterday = toLocalDateString(new Date(Date.now() - 24 * 60 * 60 * 1000))
    await seedRecord(page, userId, { id: 'e2e-swd-only-yesterday', status: 'saved', date: yesterday })
    await seedDetail(page, { id: 'e2e-swdd-only-yesterday', recordId: 'e2e-swd-only-yesterday', eventDate: yesterday, solidWasteType: 'Abu Boiler' })

    await page.goto(MONITOR_PATH)

    // Presence first: the yesterday event IS loaded (last-event card), yet
    // the counters show 0.
    await expect(page.getByTestId('last-event-card')).toContainText('Abu Boiler')
    await expect(page.getByTestId('counter-count-records')).toHaveText('0')
    await expect(page.getByTestId('counter-count-events')).toHaveText('0')
  })

  test('Monitor Solid Waste Disposal — Tap Breadcrumb', async ({ page }) => {
    await page.goto(MONITOR_PATH)

    await page.getByTestId('breadcrumb-production-process-activity').click()
    await page.waitForURL('**/stations')
  })

  test('Monitor Solid Waste Disposal — Back ke Station List', async ({ page }) => {
    await page.goto(MONITOR_PATH)

    await page.getByTestId('back-button').click()
    await page.waitForURL('**/stations')
  })

  test('Monitor Solid Waste Disposal — Buka Menu Hamburger', async ({ page }) => {
    await page.goto(MONITOR_PATH)

    await expect(page.getByTestId('nav-menu')).toBeHidden()
    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu-change-password')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })
})
