import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId, USERS } from './helpers'

// screen-086--data-preview-storage-tank / usecase-093--data-preview-storage-tank
//
// Mirrors data-preview-effluent-plant.spec.ts — one route/component for list
// mode (no :id) and read-only detail mode (:id). Records are seeded via the
// dev-only `window.__mslTestDb` bridge with 3 detail rows each.
//
// Scenario source: the tech-spec test_scenarios[*].browser_test (success,
// Record Tidak Ditemukan, Tap Item Draft/Pause, Filter Tanggal Default Hari
// Ini, Filter Tidak Menghasilkan Apapun, List Kosong, Back dari Mode Detail)
// plus business_logic step 9 (Back in list mode -> Monitor).
//
// Detail mode shows BOTH verification status blocks (Checked By and
// Acknowledged By) — this station supports both levels. The verify action
// itself (PATCH /api/records/storage-tank/{server_id}/verification) is not
// in this screen's browser_test list; only the role gating of the actions
// and the "belum tersinkron" note for a local-only record are asserted.

const STATION = 'storage-tank'
const RECORD_TABLE = 'storage_tank_record'
const DETAIL_TABLE = 'storage_tank_detail'
const ID_COLUMN = 'storage_tank_id'
const ID_FIELD = '#field-storage-tank-id'
const ID_PREFIX = 'ST'
const LABEL_LOWER = 'storage tank'

const PREVIEW_URL = `/stations/${STATION}/preview`
const PREVIEW_LIST_RE = new RegExp(`/stations/${STATION}/preview$`)

type Status = 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'

async function seedRecord(
  page: Page,
  userId: string,
  overrides: { id: string; status?: Status; stationRecordId?: string | null; date?: string; hours?: number[] },
): Promise<void> {
  await page.evaluate(
    async ({ recordTable, detailTable, fk, idColumn, userId, id, status, stationRecordId, date, hours }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO ${recordTable} (id, status, ${idColumn}, date, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'saved', stationRecordId ?? null, date ?? now, userId, now, now],
      )

      for (const [i, hour] of hours.entries()) {
        await db.run(
          `INSERT OR REPLACE INTO ${detailTable} (id, ${fk}, time_slot, created_at, updated_at) VALUES (?, ?, ?, ?, ?)`,
          [`${id}-detail-${i}`, id, `${String(hour).padStart(2, '0')}:00`, now, now],
        )
      }
    },
    {
      recordTable: RECORD_TABLE,
      detailTable: DETAIL_TABLE,
      fk: `${RECORD_TABLE}_id`,
      idColumn: ID_COLUMN,
      userId,
      id: overrides.id,
      status: overrides.status,
      stationRecordId: overrides.stationRecordId,
      date: overrides.date,
      hours: overrides.hours ?? [7, 9, 14],
    },
  )
}

function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const today = (): string => toLocalDateString(new Date())
const yesterday = (): string => toLocalDateString(new Date(Date.now() - 24 * 60 * 60 * 1000))

test.describe('Data Preview Storage Tank (screen-086)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('Lihat Data Storage Tank Tersimpan — success (Load Data -> Tersimpan item -> detail read-only)', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const id = `e2e-${STATION}-preview-1`
    await seedRecord(page, userId, { id, status: 'saved', stationRecordId: `${ID_PREFIX}-PREVIEW-1`, date: `${today()}T07:00:00` })

    await page.goto(`/stations/${STATION}/monitor`)
    await page.getByTestId('load-data-button').click()
    await page.waitForURL(PREVIEW_LIST_RE)
    await expect(page.getByTestId('date-filter-input')).toHaveValue(today())

    const item = page.getByTestId(`record-item-${id}`)
    await expect(item).toContainText('Tersimpan')
    await item.click()
    await page.waitForURL(`**/stations/${STATION}/preview/${id}`)

    await expect(page.locator(ID_FIELD)).toHaveValue(`${ID_PREFIX}-PREVIEW-1`)
    await expect(page.locator(ID_FIELD)).toBeDisabled()
    await expect(page.getByTestId(`${STATION}-detail-rows-list`)).toBeVisible()
    await expect(page.getByTestId(new RegExp(`^${STATION}-detail-row-`))).toHaveCount(3)
    await expect(page.getByTestId(`${STATION}-detail-row-${id}-detail-0`)).toContainText('07:00')
    // Read-only: no editing controls from the Form in detail mode.
    await expect(page.getByTestId('save-button')).toHaveCount(0)
    await expect(page.getByTestId('add-detail-row-button')).toHaveCount(0)
    // Both verification levels shown, as-is, for any role.
    await expect(page.getByTestId('verify-status-checked-by')).toContainText('Belum diperiksa Supervisor')
    await expect(page.getByTestId('verify-status-acknowledged-by')).toContainText('Belum dikonfirmasi Mill Management')
    // An operator gets no verification actions.
    await expect(page.getByTestId('verification-actions')).toHaveCount(0)
  })

  test('Lihat Data Storage Tank Tersimpan — Tersinkron item also opens detail mode', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const id = `e2e-${STATION}-preview-synced`
    await seedRecord(page, userId, { id, status: 'synced', stationRecordId: `${ID_PREFIX}-SYNCED`, date: `${today()}T08:00:00` })

    await page.goto(PREVIEW_URL)
    const item = page.getByTestId(`record-item-${id}`)
    await expect(item).toContainText('Tersinkron')
    await item.click()

    await page.waitForURL(`**/stations/${STATION}/preview/${id}`)
    await expect(page.getByTestId(`${STATION}-detail-rows-list`)).toBeVisible()
  })

  test('Lihat Data Storage Tank Tersimpan — Tap Item Draft/Pause', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const id = `e2e-${STATION}-preview-draft`
    await seedRecord(page, userId, { id, status: 'draft_paused', stationRecordId: `${ID_PREFIX}-DRAFT`, date: `${today()}T09:00:00` })

    await page.goto(PREVIEW_URL)
    const item = page.getByTestId(`record-item-${id}`)
    await expect(item).toContainText('Pause')
    await item.click()

    await page.waitForURL(`**/stations/${STATION}/form/${id}`)
    await expect(page.locator(ID_FIELD)).toHaveValue(`${ID_PREFIX}-DRAFT`)
  })

  test('Lihat Data Storage Tank Tersimpan — Filter Tanggal Default Hari Ini', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedRecord(page, userId, { id: `e2e-${STATION}-today`, stationRecordId: `${ID_PREFIX}-TODAY`, date: `${today()}T07:00:00` })
    await seedRecord(page, userId, { id: `e2e-${STATION}-yesterday`, stationRecordId: `${ID_PREFIX}-YESTERDAY`, date: `${yesterday()}T07:00:00` })

    await page.goto(PREVIEW_URL)

    await expect(page.getByTestId('date-filter-input')).toHaveValue(today())
    await expect(page.getByTestId(`record-item-e2e-${STATION}-today`)).toBeVisible()
    await expect(page.getByTestId(`record-item-e2e-${STATION}-yesterday`)).toHaveCount(0)

    // Changing the filter to yesterday swaps the list.
    await page.getByTestId('date-filter-input').fill(yesterday())
    await expect(page.getByTestId(`record-item-e2e-${STATION}-yesterday`)).toBeVisible()
    await expect(page.getByTestId(`record-item-e2e-${STATION}-today`)).toHaveCount(0)
  })

  test('Lihat Data Storage Tank Tersimpan — Pencarian ID (case-insensitive)', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedRecord(page, userId, { id: `e2e-${STATION}-search-a`, stationRecordId: `${ID_PREFIX}-ALPHA`, date: `${today()}T07:00:00` })
    await seedRecord(page, userId, { id: `e2e-${STATION}-search-b`, stationRecordId: `${ID_PREFIX}-BRAVO`, date: `${today()}T08:00:00` })

    await page.goto(PREVIEW_URL)
    await expect(page.getByTestId(`record-item-e2e-${STATION}-search-a`)).toBeVisible()
    await expect(page.getByTestId(`record-item-e2e-${STATION}-search-b`)).toBeVisible()

    await page.getByTestId('search-filter-input').fill(`${ID_PREFIX.toLowerCase()}-bravo`)
    await expect(page.getByTestId(`record-item-e2e-${STATION}-search-b`)).toBeVisible()
    await expect(page.getByTestId(`record-item-e2e-${STATION}-search-a`)).toHaveCount(0)
  })

  test('Lihat Data Storage Tank Tersimpan — Filter Tidak Menghasilkan Apapun', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const id = `e2e-${STATION}-nomatch`
    await seedRecord(page, userId, { id, stationRecordId: `${ID_PREFIX}-NOMATCH`, date: `${yesterday()}T07:00:00` })

    await page.goto(PREVIEW_URL)
    await page.getByTestId('search-filter-input').fill('ZZZ-TIDAK-ADA')

    await expect(page.getByTestId('record-list-empty')).toContainText('Tidak ada data yang cocok dengan filter.')
    await expect(page.getByTestId('reset-filter-button')).toBeVisible()

    // Reset clears both filters — the (yesterday-dated) record reappears.
    await page.getByTestId('reset-filter-button').click()
    await expect(page.getByTestId(`record-item-${id}`)).toBeVisible()
  })

  test('Lihat Data Storage Tank Tersimpan — Record Tidak Ditemukan', async ({ page }) => {
    await page.goto(`${PREVIEW_URL}/non-existent-id`)

    await expect(page.getByTestId('record-not-found')).toContainText(`Data ${LABEL_LOWER} tidak ditemukan.`)
    await expect(page.getByTestId('back-button')).toBeVisible()

    // Back from not-found returns to the list, not Monitor.
    await page.getByTestId('back-button').click()
    await expect(page).toHaveURL(PREVIEW_LIST_RE)
  })

  test('Lihat Data Storage Tank Tersimpan — List Kosong', async ({ page }) => {
    await page.goto(PREVIEW_URL)
    // Clear the default date filter so the empty state is the "no data at all" one.
    await page.getByTestId('date-filter-input').fill('')

    await expect(page.getByTestId('record-list-empty')).toContainText(`Belum ada data ${LABEL_LOWER}.`)
    await expect(page.getByTestId('record-list')).toHaveCount(0)
  })

  test('Lihat Data Storage Tank Tersimpan — Detail dengan nol baris', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const id = `e2e-${STATION}-zero-rows`
    await seedRecord(page, userId, { id, stationRecordId: `${ID_PREFIX}-ZERO`, date: `${today()}T07:00:00`, hours: [] })

    await page.goto(`${PREVIEW_URL}/${id}`)

    await expect(page.locator(ID_FIELD)).toHaveValue(`${ID_PREFIX}-ZERO`)
    await expect(page.getByTestId(`${STATION}-detail-rows-empty`)).toBeVisible()
    await expect(page.getByTestId('record-not-found')).toHaveCount(0)
  })

  test('Lihat Data Storage Tank Tersimpan — Back dari Mode Detail', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const id = `e2e-${STATION}-preview-back`
    await seedRecord(page, userId, { id, stationRecordId: `${ID_PREFIX}-BACK`, date: `${today()}T07:00:00` })

    await page.goto(`${PREVIEW_URL}/${id}`)
    await expect(page.locator(ID_FIELD)).toHaveValue(`${ID_PREFIX}-BACK`)
    await page.getByTestId('back-button').click()

    await expect(page).toHaveURL(PREVIEW_LIST_RE)
    await expect(page.getByTestId(`record-item-${id}`)).toBeVisible()
  })

  test('Lihat Data Storage Tank Tersimpan — Back dari Mode List ke Monitor', async ({ page }) => {
    await page.goto(PREVIEW_URL)
    await expect(page.getByTestId('date-filter-input')).toBeVisible()

    await page.getByTestId('back-button').click()

    await page.waitForURL(`**/stations/${STATION}/monitor`)
  })
})

test.describe('Data Preview Storage Tank (screen-086) — Supervisor', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, USERS.supervisor)
  })

  test('Detail record lokal (belum tersinkron) — verifikasi menunggu sinkron', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const id = `e2e-${STATION}-spv-unsynced`
    await seedRecord(page, userId, { id, status: 'saved', stationRecordId: `${ID_PREFIX}-SPV`, date: `${today()}T07:00:00` })

    await page.goto(`${PREVIEW_URL}/${id}`)

    await expect(page.getByTestId('verification-actions')).toBeVisible()
    await expect(page.getByTestId('verification-not-synced')).toContainText('Record belum tersinkron ke server')
    await expect(page.getByTestId('toggle-checked-button')).toBeDisabled()
  })
})
