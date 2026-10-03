import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId, USERS } from './helpers'

// screen-077--form-engine-room / usecase-098--form-engine-room
//
// Mirrors form-effluent-plant.spec.ts — FormEngineRoomView.vue follows the
// same hourly-grid, dynamic add-row/remove-row pattern: a fresh draft starts
// with ZERO rows, "Tambah baris" adds one row at a time, the Time-Slot
// SearchableSelect only offers canonical slots (07:00..06:00) that are not
// used by another row and come after the highest slot already picked.
// This station has NO operational-target reference table.
//
// Scenario source: the tech-spec test_scenarios[*].browser_test.
//
// Write-through: when Mills Setting immediate_sync_enabled is on, Simpan
// also POSTs the record to the server (writeThroughSync.syncAfterSave). The
// suite must not depend on that setting nor on the dev server's reporting
// period state, so every POST to this station's records endpoint is aborted
// (a network failure is the documented silent path — the record stays
// 'saved' locally and waits for the next manual sync).
//
// Not reproducible in a browser: the 422 PERIOD_CLOSED rejection dialog is
// a server-side period-lock path, covered by its own write-through specs,
// not by this station's browser_test list.

const STATION = 'engine-room'
const RECORD_TABLE = 'engine_room_record'
const DETAIL_TABLE = 'engine_room_detail'
const ID_COLUMN = 'engine_room_id'
const ID_FIELD = '#field-engine-room-id'
const ID_LABEL = 'Engine Room ID'
const ID_PREFIX = 'ER'
const API_RECORDS_RE = /\/api\/engine-room-records/
// One numeric reading column on the grid (testid prefix + DB column).
const READING_TESTID = 'steam-inlet-pressure'
const READING_COLUMN = 'steam_turbine_inlet_pressure_bar'
// One enum/status column on the grid (testid prefix + DB column + option value).
const ENUM_TESTID = 'diesel-gen-2-status'
const ENUM_COLUMN = 'diesel_gen_2_status'
const ENUM_VALUE = 'standby'

const MONITOR_URL = `/stations/${STATION}/monitor`
const MONITOR_URL_RE = new RegExp(`/stations/${STATION}/monitor$`)
const FORM_URL_RE = new RegExp(`/stations/${STATION}/form/[^/]+$`)

// Time-Slot is a SearchableSelect.vue (typeable combobox) — same helper as
// form-effluent-plant.spec.ts.
async function selectSearchableOption(page: Page, testId: string, optionLabel: string): Promise<void> {
  const root = page.getByTestId(testId)
  await root.locator('input').click()

  const option = root.getByRole('option', { name: optionLabel, exact: true })
  await option.waitFor({ state: 'visible', timeout: 15_000 })
  await option.click()
}

async function closeSearchableSelect(page: Page, testId: string): Promise<void> {
  await page.getByTestId(testId).locator('input').press('Escape')
}

async function blockWriteThrough(page: Page): Promise<void> {
  await page.route(API_RECORDS_RE, (route) =>
    route.request().method() === 'POST' ? route.abort('internetdisconnected') : route.continue(),
  )
}

async function openNewDraft(page: Page): Promise<string> {
  await page.goto(MONITOR_URL)
  await page.getByTestId('new-data-button').click()
  await page.waitForURL(FORM_URL_RE)
  await expect(page.locator(ID_FIELD)).toBeVisible()
  return page.url().split('/').pop() as string
}

async function getRecord(page: Page, id: string): Promise<Record<string, unknown> | null> {
  return page.evaluate(
    async ({ table, id }) => {
      const db = (window as unknown as { __mslTestDb: { query: (sql: string, params?: unknown[]) => Promise<unknown[]> } })
        .__mslTestDb
      const rows = await db.query(`SELECT * FROM ${table} WHERE id = ?`, [id])
      return (rows[0] as Record<string, unknown>) ?? null
    },
    { table: RECORD_TABLE, id },
  )
}

async function getDetailRows(page: Page, recordId: string): Promise<Array<Record<string, unknown>>> {
  return page.evaluate(
    async ({ table, fk, recordId }) => {
      const db = (window as unknown as { __mslTestDb: { query: (sql: string, params?: unknown[]) => Promise<unknown[]> } })
        .__mslTestDb
      return (await db.query(`SELECT * FROM ${table} WHERE ${fk} = ? ORDER BY time_slot ASC`, [recordId])) as Array<
        Record<string, unknown>
      >
    },
    { table: DETAIL_TABLE, fk: `${RECORD_TABLE}_id`, recordId },
  )
}

async function seedPausedDraftWithRows(page: Page, userId: string, id: string, stationRecordId: string): Promise<void> {
  await page.evaluate(
    async ({ recordTable, detailTable, fk, idColumn, readingColumn, userId, id, stationRecordId }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO ${recordTable} (id, status, ${idColumn}, date, note, created_by, created_at, updated_at)
         VALUES (?, 'draft_paused', ?, ?, ?, ?, ?, ?)`,
        [id, stationRecordId, now, 'Catatan draft', userId, now, now],
      )
      // Inserted out of canonical order on purpose (09:00 before 07:00).
      const rows: Array<[string, number]> = [
        ['09:00', 1290],
        ['07:00', 1250],
      ]
      for (const [i, [slot, value]] of rows.entries()) {
        await db.run(
          `INSERT OR REPLACE INTO ${detailTable} (id, ${fk}, time_slot, ${readingColumn}, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)`,
          [`${id}-detail-${i}`, id, slot, value, now, now],
        )
      }
    },
    {
      recordTable: RECORD_TABLE,
      detailTable: DETAIL_TABLE,
      fk: `${RECORD_TABLE}_id`,
      idColumn: ID_COLUMN,
      readingColumn: READING_COLUMN,
      userId,
      id,
      stationRecordId,
    },
  )
}

test.describe('Form Engine Room (screen-077) — Station Operator', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
    await blockWriteThrough(page)
  })

  test('Input Data Engine Room — success as Station Operator', async ({ page }) => {
    const recordId = await openNewDraft(page)

    await page.locator(ID_FIELD).fill(`${ID_PREFIX}-E2E-001`)
    await page.getByTestId('add-detail-row-button').click()
    await selectSearchableOption(page, 'time-slot-select-0', '07:00')
    await page.locator(`#${READING_TESTID}-0`).fill('1250')
    await page.getByTestId('save-button').click()

    await page.waitForURL(MONITOR_URL_RE)

    const record = await getRecord(page, recordId)
    expect(record?.status).toBe('saved')
    expect(record?.[ID_COLUMN]).toBe(`${ID_PREFIX}-E2E-001`)
    expect(record?.checked_by ?? null).toBeNull()
    expect(record?.acknowledged_by ?? null).toBeNull()
    const rows = await getDetailRows(page, recordId)
    expect(rows).toHaveLength(1)
    expect(rows[0].time_slot).toBe('07:00')
    expect(rows[0][READING_COLUMN]).toBe(1250)

    // Saved record is no longer a draft, and shows "Tersimpan" in Data Preview.
    await expect(page.getByTestId(`draft-item-${recordId}`)).toHaveCount(0)
    await page.goto(`/stations/${STATION}/preview`)
    await expect(page.getByTestId(`record-item-${recordId}`)).toContainText('Tersimpan')
  })

  test('Input Data Engine Room — ID Belum Lengkap', async ({ page }) => {
    const recordId = await openNewDraft(page)

    await page.getByTestId('add-detail-row-button').click()
    await selectSearchableOption(page, 'time-slot-select-0', '07:00')
    await page.locator(`#${READING_TESTID}-0`).fill('1250')
    await page.getByTestId('save-button').click()

    await expect(page.getByText(`${ID_LABEL} wajib diisi.`)).toBeVisible()
    await expect(page).toHaveURL(FORM_URL_RE)
    expect((await getRecord(page, recordId))?.status).toBe('draft_ongoing')
  })

  test('Input Data Engine Room — Belum Ada Baris Valid', async ({ page }) => {
    const recordId = await openNewDraft(page)

    await page.locator(ID_FIELD).fill(`${ID_PREFIX}-E2E-002`)
    await page.getByTestId('save-button').click()

    await expect(page.getByTestId('detail-rows-error')).toBeVisible()
    await expect(page).toHaveURL(FORM_URL_RE)

    // A row with a Time-Slot but no reading is still not valid.
    await page.getByTestId('add-detail-row-button').click()
    await selectSearchableOption(page, 'time-slot-select-0', '07:00')
    await page.getByTestId('save-button').click()

    await expect(page.getByTestId('detail-rows-error')).toBeVisible()
    await expect(page).toHaveURL(FORM_URL_RE)
    expect((await getRecord(page, recordId))?.status).toBe('draft_ongoing')
  })

  test('Input Data Engine Room — Tambah/Hapus Baris dan Urutan Time-Slot', async ({ page }) => {
    await openNewDraft(page)

    await expect(page.getByTestId(`${STATION}-detail-row`)).toHaveCount(0)
    await page.getByTestId('add-detail-row-button').click()
    await expect(page.getByTestId(`${STATION}-detail-row`)).toHaveCount(1)
    await selectSearchableOption(page, 'time-slot-select-0', '09:00')
    await page.getByTestId('add-detail-row-button').click()
    await expect(page.getByTestId(`${STATION}-detail-row`)).toHaveCount(2)

    // While row 1 holds 09:00, row 2 only offers slots after it.
    const row1 = page.getByTestId('time-slot-select-1')
    await row1.locator('input').click()
    await expect(row1.getByRole('option', { name: '10:00', exact: true })).toBeVisible()
    for (const slot of ['07:00', '08:00', '09:00']) {
      await expect(row1.getByRole('option', { name: slot, exact: true })).toHaveCount(0)
    }
    await closeSearchableSelect(page, 'time-slot-select-1')

    await page.getByTestId('remove-detail-row-button').first().click()
    await expect(page.getByTestId(`${STATION}-detail-row`)).toHaveCount(1)

    // The remaining row (now index 0) gets 09:00 — and 07:00 — back.
    const remaining = page.getByTestId('time-slot-select-0')
    await remaining.locator('input').click()
    await expect(remaining.getByRole('option', { name: '09:00', exact: true })).toBeVisible()
    await expect(remaining.getByRole('option', { name: '07:00', exact: true })).toBeVisible()
  })

  test('Input Data Engine Room — Baris Valid Hanya Dari Status Enum', async ({ page }) => {
    const recordId = await openNewDraft(page)

    await page.locator(ID_FIELD).fill(`${ID_PREFIX}-E2E-ENUM`)
    await page.getByTestId('add-detail-row-button').click()
    await selectSearchableOption(page, 'time-slot-select-0', '08:00')
    await page.getByTestId(`${ENUM_TESTID}-0`).selectOption(ENUM_VALUE)
    await page.getByTestId('save-button').click()

    await page.waitForURL(MONITOR_URL_RE)
    expect((await getRecord(page, recordId))?.status).toBe('saved')
    const rows = await getDetailRows(page, recordId)
    expect(rows).toHaveLength(1)
    expect(rows[0][ENUM_COLUMN]).toBe(ENUM_VALUE)
    expect(rows[0][READING_COLUMN]).toBeNull()
  })

  test('Input Data Engine Room — Checked By Khusus Supervisor (Operator tidak dapat mengisi)', async ({ page }) => {
    await openNewDraft(page)

    // Presence first: the Verifikasi section is rendered for the operator…
    await expect(page.getByTestId('inputted-by-display')).toBeVisible()
    // …but both toggles are hidden from roles that cannot write them.
    await expect(page.getByTestId('checked-by-toggle')).toHaveCount(0)
    await expect(page.getByTestId('acknowledged-by-toggle')).toHaveCount(0)
  })

  test('Input Data Engine Room — Lanjutkan Draft Paused', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const id = `e2e-${STATION}-resume`
    await seedPausedDraftWithRows(page, userId, id, `${ID_PREFIX}-RESUME`)

    await page.goto(MONITOR_URL)
    await page.getByTestId(`draft-item-${id}`).click()
    await page.waitForURL(`**/stations/${STATION}/form/${id}`)

    await expect(page.locator(ID_FIELD)).toHaveValue(`${ID_PREFIX}-RESUME`)
    await expect(page.locator('#field-note')).toHaveValue('Catatan draft')
    await expect(page.getByTestId(`${STATION}-detail-row`)).toHaveCount(2)
    // Canonical order, not insertion order.
    await expect(page.getByTestId('time-slot-select-0')).toHaveAttribute('data-value', '07:00')
    await expect(page.getByTestId('time-slot-select-1')).toHaveAttribute('data-value', '09:00')
    await expect(page.locator(`#${READING_TESTID}-0`)).toHaveValue('1250')
    await expect(page.locator(`#${READING_TESTID}-1`)).toHaveValue('1290')
  })

  test('Input Data Engine Room — Back Dengan Perubahan Belum Tersimpan', async ({ page }) => {
    await openNewDraft(page)

    await page.getByTestId('add-detail-row-button').click()
    await selectSearchableOption(page, 'time-slot-select-0', '07:00')
    await page.locator(`#${READING_TESTID}-0`).fill('1300')
    await page.getByTestId('back-button').click()

    await expect(page.getByText('Ada perubahan yang belum disimpan.')).toBeVisible()
    await expect(page).toHaveURL(FORM_URL_RE)

    await page.getByRole('button', { name: 'Ya, Keluar' }).click()
    await page.waitForURL(MONITOR_URL_RE)
  })

  test('Input Data Engine Room — Back Tanpa Perubahan langsung ke Monitor', async ({ page }) => {
    await openNewDraft(page)

    await page.getByTestId('back-button').click()

    await page.waitForURL(MONITOR_URL_RE)
    await expect(page.getByText('Ada perubahan yang belum disimpan.')).toHaveCount(0)
  })

  test('Input Data Engine Room — Pause Progress', async ({ page }) => {
    const recordId = await openNewDraft(page)

    await page.getByTestId('pause-button').click()

    await page.waitForURL(MONITOR_URL_RE)
    await expect(page.getByTestId(`draft-item-${recordId}`)).toContainText('Pause')
    expect((await getRecord(page, recordId))?.status).toBe('draft_paused')
  })

  test('Input Data Engine Room — Clear Draft (batal lalu konfirmasi)', async ({ page }) => {
    const recordId = await openNewDraft(page)
    await page.locator(ID_FIELD).fill(`${ID_PREFIX}-E2E-CLEAR`)

    // Cancel — nothing changes.
    await page.getByTestId('clear-button').click()
    await expect(page.getByRole('alertdialog', { name: 'Hapus Draft' })).toBeVisible()
    await page.getByRole('button', { name: 'Batal' }).click()
    await expect(page.getByRole('alertdialog', { name: 'Hapus Draft' })).toHaveCount(0)
    await expect(page).toHaveURL(FORM_URL_RE)
    await expect(page.locator(ID_FIELD)).toHaveValue(`${ID_PREFIX}-E2E-CLEAR`)
    expect(await getRecord(page, recordId)).not.toBeNull()

    // Confirm — permanently removed.
    await page.getByTestId('clear-button').click()
    await page.getByRole('button', { name: 'Ya, Hapus' }).click()
    await page.waitForURL(MONITOR_URL_RE)

    expect(await getRecord(page, recordId)).toBeNull()
    expect(await getDetailRows(page, recordId)).toHaveLength(0)
    await expect(page.getByTestId('draft-list-empty')).toBeVisible()
    await page.goto(`/stations/${STATION}/preview`)
    await expect(page.getByTestId('record-list-empty')).toBeVisible()
  })
})

test.describe('Form Engine Room (screen-077) — Supervisor', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, USERS.supervisor)
    await blockWriteThrough(page)
  })

  test('Input Data Engine Room — success as Supervisor (Checked By terisi)', async ({ page }) => {
    const supervisorId = await getAuthUserId(page)
    const recordId = await openNewDraft(page)

    await page.locator(ID_FIELD).fill(`${ID_PREFIX}-E2E-SPV`)
    await page.getByTestId('checked-by-toggle').check()
    await page.getByTestId('add-detail-row-button').click()
    await selectSearchableOption(page, 'time-slot-select-0', '07:00')
    await page.locator(`#${READING_TESTID}-0`).fill('1250')
    await page.getByTestId('save-button').click()

    await page.waitForURL(MONITOR_URL_RE)
    const record = await getRecord(page, recordId)
    expect(record?.status).toBe('saved')
    expect(record?.checked_by).toBe(supervisorId)
  })

  test('Input Data Engine Room — Acknowledged By Khusus Mill Management', async ({ page }) => {
    await openNewDraft(page)

    // Presence first: the supervisor's own Checked By toggle is there…
    await expect(page.getByTestId('checked-by-toggle')).toBeVisible()
    await expect(page.getByTestId('checked-by-toggle')).toBeEnabled()
    // …but Acknowledged By is not offered to a supervisor.
    await expect(page.getByTestId('acknowledged-by-toggle')).toHaveCount(0)
  })
})
