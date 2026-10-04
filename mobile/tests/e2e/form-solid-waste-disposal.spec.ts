import { test, expect, type Page } from '@playwright/test'
import { login, USERS, getAuthUserId } from './helpers'

// screen-071--form-solid-waste-disposal / usecase-062--form-solid-waste-disposal
//
// EVENT-LOG form: header (Solid Waste Disp. ID required, Date auto) +
// "Log Kejadian" table whose rows are added manually via "+ Tambah Baris"
// (unbounded, one row per disposal event; Tanggal Kejadian is the per-row
// required field, Net Weight = Gross - Tare computed and read-only).
// Checked By / Acknowledged By are rendered DISABLED (not hidden) for roles
// that cannot write them — that is this screen's spec (tech-spec business
// logic 8/9: "disabled untuk role lain").
//
// Persisted state is asserted by reading the local SQLite through the
// dev-only `window.__mslTestDb` bridge.
//
// Write-through saving: if the mill's immediate_sync_enabled is on, Simpan
// also POSTs to /api/solid-waste-disposal-records. These tests do not
// depend on that setting — the POST is aborted (= offline, which the form
// treats silently), so Simpan always lands on the Monitor.
//
// Not reproducible in a browser: nothing in this screen's browser_test list
// needs a real device; the server-side PERIOD_CLOSED rejection path
// (usecase-141) is a sync concern, not one of this screen's browser_test
// scenarios.

const STATION = 'solid-waste-disposal'
const MONITOR_PATH = `/stations/${STATION}/monitor`
const ID_FIELD = '#solid_waste_disposal_id'
const RECORD_TABLE = 'solid_waste_disposal_record'
const DETAIL_TABLE = 'solid_waste_disposal_detail'
const FK = 'solid_waste_disposal_record_id'

type DbBridge = {
  run: (sql: string, params?: unknown[]) => Promise<unknown>
  query: (sql: string, params?: unknown[]) => Promise<Record<string, unknown>[]>
}

async function dbQuery(page: Page, sql: string, params: unknown[] = []): Promise<Record<string, unknown>[]> {
  return page.evaluate(
    async ({ sql, params }) => (window as unknown as { __mslTestDb: DbBridge }).__mslTestDb.query(sql, params),
    { sql, params },
  )
}

async function dbRun(page: Page, sql: string, params: unknown[] = []): Promise<void> {
  await page.evaluate(
    async ({ sql, params }) => {
      await (window as unknown as { __mslTestDb: DbBridge }).__mslTestDb.run(sql, params)
    },
    { sql, params },
  )
}

function todayLocal(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

async function blockWriteThrough(page: Page): Promise<void> {
  await page.route(`**/api/${STATION}-records**`, async (route) => {
    if (route.request().method() === 'POST') {
      await route.abort()
      return
    }
    await route.continue()
  })
}

/** Monitor -> New Data -> wait until the new draft's form is rendered. Returns the draft id. */
async function openNewDraft(page: Page): Promise<string> {
  await page.goto(MONITOR_PATH)
  await page.getByTestId('new-data-button').click()
  await page.waitForURL(new RegExp(`/stations/${STATION}/form/[^/]+$`))
  await expect(page.locator(ID_FIELD)).toBeVisible()
  return page.url().split('/').pop() as string
}

async function fillValidRow(page: Page, index: number, eventDate: string): Promise<void> {
  await page.getByTestId(`detail-event-date-${index}`).fill(eventDate)
  await page.getByTestId(`detail-gross-weight-${index}`).fill('12.5')
  await page.getByTestId(`detail-tare-weight-${index}`).fill('4.5')
}

async function recordRow(page: Page, id: string): Promise<Record<string, unknown> | undefined> {
  return (await dbQuery(page, `SELECT * FROM ${RECORD_TABLE} WHERE id = ?`, [id]))[0]
}

test.describe('Form Solid Waste Disposal (screen-071)', () => {
  test.beforeEach(async ({ page }) => {
    await blockWriteThrough(page)
    await login(page)
  })

  test('Input Data Solid Waste Disposal — success sebagai Station Operator', async ({ page }) => {
    const id = await openNewDraft(page)

    await page.locator(ID_FIELD).fill('SWD-E2E-001')
    await page.getByTestId('add-row-button').click()
    await fillValidRow(page, 0, todayLocal())

    // Operator: kedua checkbox verifikasi tidak dirender (hanya peran yang berhak).
    await expect(page.getByTestId('checked-by-checkbox')).toHaveCount(0)
    await expect(page.getByTestId('acknowledged-by-checkbox')).toHaveCount(0)

    await page.getByTestId('save-button').click()
    await page.waitForURL(`**${MONITOR_PATH}`)

    const record = await recordRow(page, id)
    expect(record?.status).toBe('saved')
    expect(record?.solid_waste_disposal_id).toBe('SWD-E2E-001')
    expect(record?.checked_by).toBeNull()
    expect(record?.acknowledged_by).toBeNull()

    const details = await dbQuery(page, `SELECT * FROM ${DETAIL_TABLE} WHERE ${FK} = ?`, [id])
    expect(details).toHaveLength(1)
    expect(details[0].event_date).toBe(todayLocal())
    expect(details[0].net_weight_mt).toBe(8)
  })

  test('Input Data Solid Waste Disposal — success sebagai Supervisor dengan Checked By', async ({ page }) => {
    await login(page, USERS.supervisor)
    const supervisorId = await getAuthUserId(page)
    const id = await openNewDraft(page)

    await page.locator(ID_FIELD).fill('SWD-E2E-SPV')
    await expect(page.getByTestId('checked-by-checkbox')).toBeEnabled()
    await page.getByTestId('checked-by-checkbox').check()
    await expect(page.getByTestId('acknowledged-by-checkbox')).toHaveCount(0)
    await page.getByTestId('add-row-button').click()
    await fillValidRow(page, 0, todayLocal())

    await page.getByTestId('save-button').click()
    await page.waitForURL(`**${MONITOR_PATH}`)

    const record = await recordRow(page, id)
    expect(record?.status).toBe('saved')
    expect(record?.checked_by).toBe(supervisorId)
  })

  test('Input Data Solid Waste Disposal — Field Wajib Belum Lengkap', async ({ page }) => {
    const id = await openNewDraft(page)
    await page.getByTestId('add-row-button').click()
    await fillValidRow(page, 0, todayLocal())

    await page.getByTestId('save-button').click()

    await expect(page.getByText('Solid Waste Disp. ID wajib diisi.')).toBeVisible()
    await expect(page).toHaveURL(new RegExp(`/stations/${STATION}/form/`))
    expect((await recordRow(page, id))?.status).toBe('draft_ongoing')
  })

  test('Input Data Solid Waste Disposal — Belum Ada Baris Log Kejadian Valid', async ({ page }) => {
    const id = await openNewDraft(page)
    await page.locator(ID_FIELD).fill('SWD-E2E-NOROW')

    await page.getByTestId('save-button').click()

    await expect(page.getByTestId('detail-rows-error')).toContainText('Minimal satu baris')
    await expect(page).toHaveURL(new RegExp(`/stations/${STATION}/form/`))

    // A row WITHOUT Tanggal Kejadian is still not a valid row.
    await page.getByTestId('add-row-button').click()
    await page.getByTestId('detail-gross-weight-0').fill('5')
    await page.getByTestId('save-button').click()
    await expect(page.getByTestId('detail-rows-error')).toBeVisible()
    expect((await recordRow(page, id))?.status).toBe('draft_ongoing')
  })

  test('Input Data Solid Waste Disposal — Net Weight Dihitung Otomatis', async ({ page }) => {
    await openNewDraft(page)
    await page.getByTestId('add-row-button').click()

    await page.getByTestId('detail-gross-weight-0').fill('10')
    await page.getByTestId('detail-tare-weight-0').fill('2')

    const net = page.getByTestId('detail-net-weight-0')
    await expect(net).toHaveValue('8')
    await expect(net).toBeDisabled()

    await page.getByTestId('detail-tare-weight-0').fill('3.5')
    await expect(net).toHaveValue('6.5')
  })

  test('Input Data Solid Waste Disposal — Checked By Khusus Supervisor', async ({ page }) => {
    await openNewDraft(page)

    const checkedBy = page.getByTestId('checked-by-checkbox')
    await expect(checkedBy).toHaveCount(0)
    await expect(page.getByTestId('acknowledged-by-checkbox')).toHaveCount(0)
  })

  test('Input Data Solid Waste Disposal — Tambah Baris Tidak Dibatasi', async ({ page }) => {
    await openNewDraft(page)

    for (let i = 0; i < 30; i += 1) {
      await page.getByTestId('add-row-button').click()
    }

    await expect(page.locator('[data-testid^="detail-row-"]')).toHaveCount(30)
    await expect(page.getByTestId('add-row-button')).toBeEnabled()
  })

  test('Input Data Solid Waste Disposal — Hapus Baris Log Kejadian', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const now = new Date().toISOString()
    await dbRun(
      page,
      `INSERT INTO ${RECORD_TABLE} (id, status, solid_waste_disposal_id, date, created_by, created_at, updated_at)
       VALUES ('e2e-swd-del', 'draft_paused', 'SWD-DEL', ?, ?, ?, ?)`,
      [todayLocal(), userId, now, now],
    )
    await dbRun(
      page,
      `INSERT INTO ${DETAIL_TABLE} (id, ${FK}, event_date, vehicle_no, created_at, updated_at)
       VALUES ('e2e-swdd-del-a', 'e2e-swd-del', ?, 'BK 1 AAA', '2026-01-01T00:00:00.000Z', '2026-01-01T00:00:00.000Z'),
              ('e2e-swdd-del-b', 'e2e-swd-del', ?, 'BK 2 BBB', '2026-01-01T00:01:00.000Z', '2026-01-01T00:01:00.000Z')`,
      [todayLocal(), todayLocal()],
    )

    await page.goto(`/stations/${STATION}/form/e2e-swd-del`)
    await expect(page.locator('[data-testid^="detail-row-"]')).toHaveCount(2)

    await page.getByTestId('remove-row-button-0').click()
    await expect(page.locator('[data-testid^="detail-row-"]')).toHaveCount(1)

    // Removal is only queued — still in the DB until the next Simpan.
    expect(await dbQuery(page, `SELECT id FROM ${DETAIL_TABLE} WHERE ${FK} = 'e2e-swd-del'`)).toHaveLength(2)

    await page.getByTestId('save-button').click()
    await page.waitForURL(`**${MONITOR_PATH}`)

    const remaining = await dbQuery(page, `SELECT id FROM ${DETAIL_TABLE} WHERE ${FK} = 'e2e-swd-del'`)
    expect(remaining.map((r) => r.id)).toEqual(['e2e-swdd-del-b'])
  })

  test('Input Data Solid Waste Disposal — Lanjutkan Draft Paused', async ({ page }) => {
    const id = await openNewDraft(page)
    await page.locator(ID_FIELD).fill('SWD-E2E-PAUSE')
    await page.getByTestId('add-row-button').click()
    await fillValidRow(page, 0, '2026-09-28')
    await page.getByTestId('pause-button').click()
    await page.waitForURL(`**${MONITOR_PATH}`)

    await page.getByTestId(`draft-item-${id}`).click()
    await page.waitForURL(new RegExp(`/stations/${STATION}/form/${id}$`))

    await expect(page.locator(ID_FIELD)).toHaveValue('SWD-E2E-PAUSE')
    await expect(page.locator('[data-testid^="detail-row-"]')).toHaveCount(1)
    await expect(page.getByTestId('detail-event-date-0')).toHaveValue('2026-09-28')
    await expect(page.getByTestId('detail-gross-weight-0')).toHaveValue('12.5')
    await expect(page.getByTestId('detail-net-weight-0')).toHaveValue('8')
  })

  test('Input Data Solid Waste Disposal — Back Dengan Perubahan Belum Tersimpan', async ({ page }) => {
    await openNewDraft(page)
    await page.locator(ID_FIELD).fill('SWD-E2E-DIRTY')

    await page.getByTestId('back-button').click()

    await expect(page.getByRole('alertdialog')).toContainText('Ada perubahan yang belum disimpan.')
    await expect(page).toHaveURL(new RegExp(`/stations/${STATION}/form/`))

    await page.locator('.confirm-dialog-button--confirm').click()
    await page.waitForURL(`**${MONITOR_PATH}`)
  })

  test('Input Data Solid Waste Disposal — Back tanpa perubahan langsung ke Monitor', async ({ page }) => {
    await openNewDraft(page)

    await page.getByTestId('back-button').click()

    await page.waitForURL(`**${MONITOR_PATH}`)
    await expect(page.getByRole('alertdialog')).toHaveCount(0)
  })

  test('Input Data Solid Waste Disposal — Pause Progress', async ({ page }) => {
    const id = await openNewDraft(page)
    // Required ID left empty on purpose — Pause does not validate.
    await page.getByTestId('add-row-button').click()
    await page.getByTestId('detail-gross-weight-0').fill('7')

    await page.getByTestId('pause-button').click()
    await page.waitForURL(`**${MONITOR_PATH}`)

    expect((await recordRow(page, id))?.status).toBe('draft_paused')
    await expect(page.getByTestId(`draft-item-${id}`)).toContainText('Pause')
  })

  test('Input Data Solid Waste Disposal — Clear Draft (konfirmasi lalu batal)', async ({ page }) => {
    const id = await openNewDraft(page)

    // Cancel first: nothing changes.
    await page.locator(ID_FIELD).fill('SWD-E2E-CLEAR')
    await page.getByTestId('clear-button').click()
    await expect(page.getByRole('alertdialog')).toContainText('Draft ini akan dihapus permanen')
    await page.locator('.confirm-dialog-button--cancel').click()
    await expect(page.getByRole('alertdialog')).toHaveCount(0)
    await expect(page).toHaveURL(new RegExp(`/stations/${STATION}/form/${id}$`))
    await expect(page.locator(ID_FIELD)).toHaveValue('SWD-E2E-CLEAR')
    expect(await recordRow(page, id)).toBeDefined()

    // Confirm: record deleted permanently, back on Monitor.
    await page.getByTestId('clear-button').click()
    await page.getByText('Ya, Hapus').click()
    await page.waitForURL(`**${MONITOR_PATH}`)
    expect(await recordRow(page, id)).toBeUndefined()
    await expect(page.getByTestId(`draft-item-${id}`)).toHaveCount(0)
  })

  test('Input Data Solid Waste Disposal — Tap Breadcrumb', async ({ page }) => {
    await openNewDraft(page)

    await page.getByRole('navigation', { name: 'Breadcrumb' }).getByRole('button', { name: 'Solid Waste Disposal' }).click()
    await page.waitForURL(`**${MONITOR_PATH}`)
  })

  test('Input Data Solid Waste Disposal — Buka Menu Hamburger', async ({ page }) => {
    await openNewDraft(page)

    await expect(page.getByTestId('nav-menu')).toBeHidden()
    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })
})
