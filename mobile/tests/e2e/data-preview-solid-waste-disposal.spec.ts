import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-081--data-preview-solid-waste-disposal / usecase-063--data-preview-solid-waste-disposal
//
// Dual route-driven mode (mirrors data-preview-cages-track.spec.ts):
//  - LIST (`/stations/solid-waste-disposal/preview`) — every local record of
//    the user, any status; date filter defaults to TODAY (local), plus a
//    search on solid_waste_disposal_id; draft rows open the Form, saved/
//    synced rows open DETAIL.
//  - DETAIL (`/preview/:id`) — read-only header + Log Kejadian table +
//    RecordVerificationStatus blocks (this station shows BOTH Checked By and
//    Acknowledged By).
//
// Records/detail rows are seeded via the dev-only `window.__mslTestDb`
// bridge. Verification ACTIONS (PATCH .../verification, server PERIOD_CLOSED)
// are not among this screen's browser_test scenarios and need a synced
// record with a real server_id — not exercised here.

type DbBridge = { run: (sql: string, params?: unknown[]) => Promise<unknown> }

const STATION = 'solid-waste-disposal'
const LIST_PATH = `/stations/${STATION}/preview`
const MONITOR_PATH = `/stations/${STATION}/monitor`

async function seedRecord(
  page: Page,
  userId: string,
  record: {
    id: string
    status?: 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'
    swdId?: string | null
    date?: string | null
    note?: string | null
    checkedBy?: string | null
    checkedByName?: string | null
    acknowledgedBy?: string | null
    acknowledgedByName?: string | null
  },
): Promise<void> {
  await page.evaluate(
    async ({ userId, record }) => {
      const db = (window as unknown as { __mslTestDb: DbBridge }).__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO solid_waste_disposal_record
           (id, status, solid_waste_disposal_id, date, note, checked_by, checked_by_name,
            acknowledged_by, acknowledged_by_name, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          record.id,
          record.status ?? 'saved',
          record.swdId ?? null,
          record.date ?? null,
          record.note ?? null,
          record.checkedBy ?? null,
          record.checkedByName ?? null,
          record.acknowledgedBy ?? null,
          record.acknowledgedByName ?? null,
          userId,
          now,
          now,
        ],
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
    eventDate: string
    vehicleNo: string
    solidWasteType: string
    gross: number
    tare: number
    site: string
    createdAt: string
  },
): Promise<void> {
  await page.evaluate(async (d) => {
    const db = (window as unknown as { __mslTestDb: DbBridge }).__mslTestDb
    await db.run(
      `INSERT OR REPLACE INTO solid_waste_disposal_detail
         (id, solid_waste_disposal_record_id, event_date, vehicle_no, solid_waste_type, gross_weight_mt,
          tare_weight_mt, net_weight_mt, disposal_utilization_site, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [d.id, d.recordId, d.eventDate, d.vehicleNo, d.solidWasteType, d.gross, d.tare, d.gross - d.tare, d.site, d.createdAt, d.createdAt],
    )
  }, detail)
}

function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const today = (): string => toLocalDateString(new Date())
const yesterday = (): string => toLocalDateString(new Date(Date.now() - 24 * 60 * 60 * 1000))

test.describe('Data Preview Solid Waste Disposal (screen-081)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('Lihat Data Solid Waste Disposal Tersimpan — Record Tidak Ditemukan', async ({ page }) => {
    await page.goto(`${LIST_PATH}/does-not-exist`)

    await expect(page.getByTestId('record-not-found')).toContainText('Record tidak ditemukan')
    await expect(page.getByTestId('back-button')).toBeVisible()

    // Back from detail goes to the LIST, not the Monitor.
    await page.getByTestId('back-button').click()
    await expect(page).toHaveURL(new RegExp(`/stations/${STATION}/preview$`))
  })

  test('Lihat Data Solid Waste Disposal Tersimpan — success (Monitor -> Load Data -> detail read-only)', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedRecord(page, userId, {
      id: 'e2e-swd-saved',
      status: 'saved',
      swdId: 'SWD-PREVIEW-01',
      date: today(),
      note: 'Catatan e2e SWD',
      checkedBy: 'e2e-supervisor-id',
      checkedByName: 'Supervisor Satu',
    })
    await seedRecord(page, userId, { id: 'e2e-swd-synced', status: 'synced', swdId: 'SWD-PREVIEW-02', date: today() })
    await seedDetail(page, {
      id: 'e2e-swdd-a',
      recordId: 'e2e-swd-saved',
      eventDate: today(),
      vehicleNo: 'BK 1234 SW',
      solidWasteType: 'Fiber',
      gross: 12.5,
      tare: 4.5,
      site: 'Kebun Blok A',
      createdAt: '2026-01-01T00:00:00.000Z',
    })
    await seedDetail(page, {
      id: 'e2e-swdd-b',
      recordId: 'e2e-swd-saved',
      eventDate: today(),
      vehicleNo: 'BK 5678 SW',
      solidWasteType: 'Abu Boiler',
      gross: 9,
      tare: 3,
      site: 'Landfill',
      createdAt: '2026-01-01T00:01:00.000Z',
    })

    await page.goto(MONITOR_PATH)
    await page.getByTestId('load-data-button').click()
    await page.waitForURL(new RegExp(`/stations/${STATION}/preview$`))

    await expect(page.getByTestId('record-item-e2e-swd-saved')).toContainText('Tersimpan')
    await expect(page.getByTestId('record-item-e2e-swd-synced')).toContainText('Tersinkron')

    await page.getByTestId('record-item-e2e-swd-saved').click()
    await page.waitForURL(`**${LIST_PATH}/e2e-swd-saved`)

    await expect(page.getByTestId('detail-solid-waste-disposal-id')).toContainText('SWD-PREVIEW-01')
    await expect(page.getByText('Catatan e2e SWD')).toBeVisible()
    await expect(page.getByTestId('verify-status-checked-by')).toContainText('Oleh Supervisor Satu')
    await expect(page.getByTestId('verify-status-acknowledged-by')).toContainText('Belum dikonfirmasi Mill Management')

    const log = page.getByTestId('solid-waste-disposal-detail-log')
    await expect(log.locator('tbody tr')).toHaveCount(2)
    await expect(log.locator('tbody tr').nth(0)).toContainText('BK 1234 SW')
    await expect(log.locator('tbody tr').nth(0)).toContainText('Fiber')
    await expect(log.locator('tbody tr').nth(0)).toContainText('Kebun Blok A')
    await expect(log.locator('tbody tr').nth(0)).toContainText('8')
    await expect(log.locator('tbody tr').nth(1)).toContainText('Abu Boiler')

    // Read-only: the detail body renders no editable controls at all.
    await expect(page.locator('.detail-body')).toBeVisible()
    await expect(page.locator('.detail-body').locator('input, select, textarea')).toHaveCount(0)
  })

  test('Lihat Data Solid Waste Disposal Tersimpan — Tap Item Draft/Pause', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedRecord(page, userId, { id: 'e2e-swd-draft', status: 'draft_paused', swdId: 'SWD-DRAFT', date: today() })

    await page.goto(LIST_PATH)
    const item = page.getByTestId('record-item-e2e-swd-draft')
    await expect(item).toContainText('Pause')
    await item.click()

    await page.waitForURL(`**/stations/${STATION}/form/e2e-swd-draft`)
    await expect(page.locator('#solid_waste_disposal_id')).toHaveValue('SWD-DRAFT')
  })

  test('Lihat Data Solid Waste Disposal Tersimpan — Filter Tanggal Default Hari Ini', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedRecord(page, userId, { id: 'e2e-swd-today', status: 'saved', swdId: 'SWD-TODAY', date: today() })
    await seedRecord(page, userId, { id: 'e2e-swd-yesterday', status: 'saved', swdId: 'SWD-YESTERDAY', date: yesterday() })

    await page.goto(LIST_PATH)

    await expect(page.getByTestId('date-filter')).toHaveValue(today())
    await expect(page.getByTestId('record-item-e2e-swd-today')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-swd-yesterday')).toHaveCount(0)

    // Clearing the date filter shows everything; picking yesterday narrows to it.
    await page.getByTestId('date-filter').fill('')
    await expect(page.getByTestId('record-item-e2e-swd-yesterday')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-swd-today')).toBeVisible()
    await page.getByTestId('date-filter').fill(yesterday())
    await expect(page.getByTestId('record-item-e2e-swd-yesterday')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-swd-today')).toHaveCount(0)
  })

  test('Lihat Data Solid Waste Disposal Tersimpan — Pencarian ID (case-insensitive)', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedRecord(page, userId, { id: 'e2e-swd-s1', status: 'saved', swdId: 'SWD-ALPHA-01', date: today() })
    await seedRecord(page, userId, { id: 'e2e-swd-s2', status: 'saved', swdId: 'SWD-BETA-02', date: today() })

    await page.goto(LIST_PATH)
    await expect(page.getByTestId('record-item-e2e-swd-s1')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-swd-s2')).toBeVisible()

    await page.getByTestId('search-filter').fill('beta')
    await expect(page.getByTestId('record-item-e2e-swd-s2')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-swd-s1')).toHaveCount(0)
  })

  test('Lihat Data Solid Waste Disposal Tersimpan — Filter Tidak Menghasilkan Apapun', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedRecord(page, userId, { id: 'e2e-swd-solo', status: 'saved', swdId: 'SWD-SOLO', date: today() })

    await page.goto(LIST_PATH)
    await expect(page.getByTestId('record-item-e2e-swd-solo')).toBeVisible()

    await page.getByTestId('search-filter').fill('tidak-ada-yang-cocok')

    await expect(page.getByTestId('list-not-found')).toContainText('Tidak ditemukan')
    await expect(page.getByTestId('record-item-e2e-swd-solo')).toHaveCount(0)
    await expect(page.getByTestId('reset-filter-button')).toBeVisible()

    await page.getByTestId('reset-filter-button').click()

    await expect(page.getByTestId('date-filter')).toHaveValue('')
    await expect(page.getByTestId('search-filter')).toHaveValue('')
    await expect(page.getByTestId('record-item-e2e-swd-solo')).toBeVisible()
    await expect(page.getByTestId('list-not-found')).toHaveCount(0)
  })

  test('Lihat Data Solid Waste Disposal Tersimpan — List Kosong', async ({ page }) => {
    await page.goto(LIST_PATH)

    await expect(page.getByTestId('list-empty')).toContainText('Belum ada data solid waste disposal tersimpan.')
    await expect(page.getByTestId('record-list')).toHaveCount(0)
  })

  test('Lihat Data Solid Waste Disposal Tersimpan — Back dari List ke Monitor', async ({ page }) => {
    await page.goto(LIST_PATH)
    await expect(page.getByTestId('date-filter')).toBeVisible()

    await page.getByTestId('back-button').click()
    await page.waitForURL(`**${MONITOR_PATH}`)
  })

  test('Lihat Data Solid Waste Disposal Tersimpan — Back dari Mode Detail ke List', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedRecord(page, userId, { id: 'e2e-swd-back', status: 'saved', swdId: 'SWD-BACK', date: today() })

    await page.goto(`${LIST_PATH}/e2e-swd-back`)
    await expect(page.getByTestId('detail-solid-waste-disposal-id')).toContainText('SWD-BACK')

    await page.getByTestId('back-button').click()

    await expect(page).toHaveURL(new RegExp(`/stations/${STATION}/preview$`))
    await expect(page.getByTestId('record-item-e2e-swd-back')).toBeVisible()
  })

  test('Lihat Data Solid Waste Disposal Tersimpan — Tap Breadcrumb', async ({ page }) => {
    await page.goto(LIST_PATH)

    await page.getByRole('navigation', { name: 'Breadcrumb' }).getByRole('button', { name: 'Solid Waste Disposal' }).click()
    await page.waitForURL(`**${MONITOR_PATH}`)
  })

  test('Lihat Data Solid Waste Disposal Tersimpan — Buka Menu Hamburger', async ({ page }) => {
    await page.goto(LIST_PATH)

    await expect(page.getByTestId('nav-menu')).toBeHidden()
    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })
})
