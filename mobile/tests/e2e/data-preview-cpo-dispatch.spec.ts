import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-084--data-preview-cpo-dispatch / usecase-081--data-preview-cpo-dispatch
//
// Dual route-driven mode (mirrors data-preview-cages-track.spec.ts):
//  - LIST (`/stations/cpo-dispatch/preview`) — every local record of
//    the user, any status; date filter defaults to TODAY (local), plus a
//    search on cpo_dispatch_id; draft rows open the Form, saved/
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

const STATION = 'cpo-dispatch'
const LIST_PATH = `/stations/${STATION}/preview`
const MONITOR_PATH = `/stations/${STATION}/monitor`

async function seedRecord(
  page: Page,
  userId: string,
  record: {
    id: string
    status?: 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'
    headerId?: string | null
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
        `INSERT OR REPLACE INTO cpo_dispatch_record
           (id, status, cpo_dispatch_id, date, note, checked_by, checked_by_name,
            acknowledged_by, acknowledged_by_name, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          record.id,
          record.status ?? 'saved',
          record.headerId ?? null,
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
    plateNo: string
    destinationBuyer: string
    gross: number
    tare: number
    site: string
    createdAt: string
  },
): Promise<void> {
  await page.evaluate(async (d) => {
    const db = (window as unknown as { __mslTestDb: DbBridge }).__mslTestDb
    await db.run(
      `INSERT OR REPLACE INTO cpo_dispatch_detail
         (id, cpo_dispatch_record_id, event_date, tanker_plate_no, destination_buyer, gross_weight_mt,
          tare_weight_mt, net_weight_mt, dobi, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [d.id, d.recordId, d.eventDate, d.plateNo, d.destinationBuyer, d.gross, d.tare, d.gross - d.tare, d.site, d.createdAt, d.createdAt],
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

test.describe('Data Preview CPO Dispatch (screen-084)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('Lihat Data CPO Dispatch Tersimpan — Record Tidak Ditemukan', async ({ page }) => {
    await page.goto(`${LIST_PATH}/does-not-exist`)

    await expect(page.getByTestId('record-not-found')).toContainText('Record tidak ditemukan')
    await expect(page.getByTestId('back-button')).toBeVisible()

    // Back from detail goes to the LIST, not the Monitor.
    await page.getByTestId('back-button').click()
    await expect(page).toHaveURL(new RegExp(`/stations/${STATION}/preview$`))
  })

  test('Lihat Data CPO Dispatch Tersimpan — success (Monitor -> Load Data -> detail read-only)', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedRecord(page, userId, {
      id: 'e2e-cpo-saved',
      status: 'saved',
      headerId: 'CPO-PREVIEW-01',
      date: today(),
      note: 'Catatan e2e CPO',
      checkedBy: 'e2e-supervisor-id',
      checkedByName: 'Supervisor Satu',
    })
    await seedRecord(page, userId, { id: 'e2e-cpo-synced', status: 'synced', headerId: 'CPO-PREVIEW-02', date: today() })
    await seedDetail(page, {
      id: 'e2e-cpod-a',
      recordId: 'e2e-cpo-saved',
      eventDate: today(),
      plateNo: 'BK 1234 SW',
      destinationBuyer: 'PT Buyer Satu',
      gross: 12.5,
      tare: 4.5,
      site: '2.85',
      createdAt: '2026-01-01T00:00:00.000Z',
    })
    await seedDetail(page, {
      id: 'e2e-cpod-b',
      recordId: 'e2e-cpo-saved',
      eventDate: today(),
      plateNo: 'BK 5678 SW',
      destinationBuyer: 'PT Pembeli Kedua',
      gross: 9,
      tare: 3,
      site: '3.1',
      createdAt: '2026-01-01T00:01:00.000Z',
    })

    await page.goto(MONITOR_PATH)
    await page.getByTestId('load-data-button').click()
    await page.waitForURL(new RegExp(`/stations/${STATION}/preview$`))

    await expect(page.getByTestId('record-item-e2e-cpo-saved')).toContainText('Tersimpan')
    await expect(page.getByTestId('record-item-e2e-cpo-synced')).toContainText('Tersinkron')

    await page.getByTestId('record-item-e2e-cpo-saved').click()
    await page.waitForURL(`**${LIST_PATH}/e2e-cpo-saved`)

    await expect(page.getByTestId('detail-cpo-dispatch-id')).toContainText('CPO-PREVIEW-01')
    await expect(page.getByText('Catatan e2e CPO')).toBeVisible()
    await expect(page.getByTestId('verify-status-checked-by')).toContainText('Oleh Supervisor Satu')
    await expect(page.getByTestId('verify-status-acknowledged-by')).toContainText('Belum dikonfirmasi Mill Management')

    const log = page.getByTestId('cpo-dispatch-detail-log')
    await expect(log.locator('tbody tr')).toHaveCount(2)
    await expect(log.locator('tbody tr').nth(0)).toContainText('BK 1234 SW')
    await expect(log.locator('tbody tr').nth(0)).toContainText('PT Buyer Satu')
    await expect(log.locator('tbody tr').nth(0)).toContainText('2.85')
    await expect(log.locator('tbody tr').nth(0)).toContainText('8')
    await expect(log.locator('tbody tr').nth(1)).toContainText('PT Pembeli Kedua')

    // Read-only: the detail body renders no editable controls at all.
    await expect(page.locator('.detail-body')).toBeVisible()
    await expect(page.locator('.detail-body').locator('input, select, textarea')).toHaveCount(0)
  })

  test('Lihat Data CPO Dispatch Tersimpan — Tap Item Draft/Pause', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedRecord(page, userId, { id: 'e2e-cpo-draft', status: 'draft_paused', headerId: 'CPO-DRAFT', date: today() })

    await page.goto(LIST_PATH)
    const item = page.getByTestId('record-item-e2e-cpo-draft')
    await expect(item).toContainText('Pause')
    await item.click()

    await page.waitForURL(`**/stations/${STATION}/form/e2e-cpo-draft`)
    await expect(page.locator('#cpo_dispatch_id')).toHaveValue('CPO-DRAFT')
  })

  test('Lihat Data CPO Dispatch Tersimpan — Filter Tanggal Default Hari Ini', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedRecord(page, userId, { id: 'e2e-cpo-today', status: 'saved', headerId: 'CPO-TODAY', date: today() })
    await seedRecord(page, userId, { id: 'e2e-cpo-yesterday', status: 'saved', headerId: 'CPO-YESTERDAY', date: yesterday() })

    await page.goto(LIST_PATH)

    await expect(page.getByTestId('date-filter')).toHaveValue(today())
    await expect(page.getByTestId('record-item-e2e-cpo-today')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-cpo-yesterday')).toHaveCount(0)

    // Clearing the date filter shows everything; picking yesterday narrows to it.
    await page.getByTestId('date-filter').fill('')
    await expect(page.getByTestId('record-item-e2e-cpo-yesterday')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-cpo-today')).toBeVisible()
    await page.getByTestId('date-filter').fill(yesterday())
    await expect(page.getByTestId('record-item-e2e-cpo-yesterday')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-cpo-today')).toHaveCount(0)
  })

  test('Lihat Data CPO Dispatch Tersimpan — Pencarian ID (case-insensitive)', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedRecord(page, userId, { id: 'e2e-cpo-s1', status: 'saved', headerId: 'CPO-ALPHA-01', date: today() })
    await seedRecord(page, userId, { id: 'e2e-cpo-s2', status: 'saved', headerId: 'CPO-BETA-02', date: today() })

    await page.goto(LIST_PATH)
    await expect(page.getByTestId('record-item-e2e-cpo-s1')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-cpo-s2')).toBeVisible()

    await page.getByTestId('search-filter').fill('beta')
    await expect(page.getByTestId('record-item-e2e-cpo-s2')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-cpo-s1')).toHaveCount(0)
  })

  test('Lihat Data CPO Dispatch Tersimpan — Filter Tidak Menghasilkan Apapun', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedRecord(page, userId, { id: 'e2e-cpo-solo', status: 'saved', headerId: 'CPO-SOLO', date: today() })

    await page.goto(LIST_PATH)
    await expect(page.getByTestId('record-item-e2e-cpo-solo')).toBeVisible()

    await page.getByTestId('search-filter').fill('tidak-ada-yang-cocok')

    await expect(page.getByTestId('list-not-found')).toContainText('Tidak ditemukan')
    await expect(page.getByTestId('record-item-e2e-cpo-solo')).toHaveCount(0)
    await expect(page.getByTestId('reset-filter-button')).toBeVisible()

    await page.getByTestId('reset-filter-button').click()

    await expect(page.getByTestId('date-filter')).toHaveValue('')
    await expect(page.getByTestId('search-filter')).toHaveValue('')
    await expect(page.getByTestId('record-item-e2e-cpo-solo')).toBeVisible()
    await expect(page.getByTestId('list-not-found')).toHaveCount(0)
  })

  test('Lihat Data CPO Dispatch Tersimpan — List Kosong', async ({ page }) => {
    await page.goto(LIST_PATH)

    await expect(page.getByTestId('list-empty')).toContainText('Belum ada data cpo dispatch tersimpan.')
    await expect(page.getByTestId('record-list')).toHaveCount(0)
  })

  test('Lihat Data CPO Dispatch Tersimpan — Back dari List ke Monitor', async ({ page }) => {
    await page.goto(LIST_PATH)
    await expect(page.getByTestId('date-filter')).toBeVisible()

    await page.getByTestId('back-button').click()
    await page.waitForURL(`**${MONITOR_PATH}`)
  })

  test('Lihat Data CPO Dispatch Tersimpan — Back dari Mode Detail ke List', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedRecord(page, userId, { id: 'e2e-cpo-back', status: 'saved', headerId: 'CPO-BACK', date: today() })

    await page.goto(`${LIST_PATH}/e2e-cpo-back`)
    await expect(page.getByTestId('detail-cpo-dispatch-id')).toContainText('CPO-BACK')

    await page.getByTestId('back-button').click()

    await expect(page).toHaveURL(new RegExp(`/stations/${STATION}/preview$`))
    await expect(page.getByTestId('record-item-e2e-cpo-back')).toBeVisible()
  })

  test('Lihat Data CPO Dispatch Tersimpan — Tap Breadcrumb', async ({ page }) => {
    await page.goto(LIST_PATH)

    await page.getByRole('navigation', { name: 'Breadcrumb' }).getByRole('button', { name: 'CPO Dispatch' }).click()
    await page.waitForURL(`**${MONITOR_PATH}`)
  })

  test('Lihat Data CPO Dispatch Tersimpan — Buka Menu Hamburger', async ({ page }) => {
    await page.goto(LIST_PATH)

    await expect(page.getByTestId('nav-menu')).toBeHidden()
    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })
})
