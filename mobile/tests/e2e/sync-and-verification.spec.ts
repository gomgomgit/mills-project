import { test, expect, type Page, type ConsoleMessage, type Response } from '@playwright/test'
import { execSync } from 'node:child_process'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

// Audit 2026-10-04 — end-to-end proof, against the REAL backend (:8000), of
// the mobile sync/verification fixes:
//
//   #1  Verification from Data Preview reaches the server (was: wrong URL,
//       CORS-blocked, UI claimed "butuh koneksi" while online).
//   #2  Write-through ("Kirim data langsung ke server saat disimpan")
//       actually pushes on Simpan, and a server rejection shows the
//       "Tersimpan, tetapi ditolak server" dialog.
//   #3  Grading sync sends REAL grading_parameter ids (was: fake local ids →
//       PostgreSQL 22P02 → 500).
//   #5  Manual sync sends each record to the Production Line of the station
//       it was created on, not the currently selected line.
//   #9  A rejected record shows "Gagal sinkron: <alasan>" in Data Preview.
//
// Data: an ISOLATED test mill built by support/mobile-sync-fixture.php
// (immediate sync ON, two lines, period open for every station except
// Pressing, which stays DRAFT so the server rejects it on purpose).
//
// Every test asserts no `pageerror`, no `console.error` and no HTTP >= 400
// except the specific responses that test provokes on purpose.

const here = path.dirname(fileURLToPath(import.meta.url))
const BACKEND_DIR = path.resolve(here, '../../../backend')
const FIXTURE = path.resolve(here, 'support/mobile-sync-fixture.php')

interface Fixture {
  business_unit_id: string
  lines: Array<{ id: string; name: string }>
  users: Record<string, string>
  grading_parameters: Array<{ id: string; name: string; uom: string; sort_order: number }>
}

let fixture: Fixture

const OPERATOR = { username: 'mse2e-operator01', password: 'Passw0rd!' }
const SUPERVISOR = { username: 'mse2e-supervisor01', password: 'Passw0rd!' }

test.beforeAll(() => {
  const out = execSync(`php artisan tinker --execute="require '${FIXTURE}';"`, {
    cwd: BACKEND_DIR,
    encoding: 'utf8',
    timeout: 120_000,
  })
  const line = out.split('\n').find((l) => l.startsWith('FIXTURE_JSON '))
  if (!line) throw new Error(`fixture failed:\n${out}`)
  fixture = JSON.parse(line.slice('FIXTURE_JSON '.length))
})

const API_BASE = 'http://localhost:8000'

/** Fails the test on any page error / console error / HTTP >= 400 not explicitly allowed. */
function guard(
  page: Page,
  allowed: Array<{ status: number; url: RegExp }> = [],
  options: { allowAbortedRequests?: boolean } = {},
) {
  const problems: string[] = []
  const allowedHits: number[] = []

  page.on('pageerror', (error) => problems.push(`pageerror: ${error.message}`))
  page.on('response', (response: Response) => {
    if (response.status() < 400) return
    const ok = allowed.find((a) => a.status === response.status() && a.url.test(response.url()))
    if (ok) {
      allowedHits.push(response.status())
      return
    }
    problems.push(`HTTP ${response.status()} ${response.request().method()} ${response.url()}`)
  })
  page.on('console', (message: ConsoleMessage) => {
    if (message.type() !== 'error') return
    // Chrome logs every 4xx as "Failed to load resource" — tolerated only
    // for a status this test deliberately provokes.
    const status = /status of (\d{3})/.exec(message.text())?.[1]
    if (status && allowed.some((a) => a.status === Number(status))) return
    if (options.allowAbortedRequests && /net::ERR_FAILED/.test(message.text())) return
    problems.push(`console.error: ${message.text()}`)
  })

  return {
    assertClean: () => expect(problems, problems.join('\n')).toEqual([]),
  }
}

async function login(page: Page, user: { username: string; password: string }) {
  await page.goto('/login')
  await page.locator('#username').fill(user.username)
  await page.locator('#password').fill(user.password)
  await page.getByRole('button', { name: 'Login' }).click()
  await page.waitForURL('**/home')
}

async function chooseLine(page: Page, lineIndex: number) {
  await page.goto('/stations')
  const picker = page.getByTestId('production-line-picker')
  await expect(picker.or(page.getByTestId('production-line-switcher')).first()).toBeVisible({ timeout: 15_000 })
  if (!(await picker.isVisible())) {
    await page.getByTestId('production-line-switcher').click()
  }
  await page.getByTestId(`production-line-option-${fixture.lines[lineIndex].id}`).click()
  await expect(page.locator('[data-testid^="station-tile-"]').first()).toBeVisible({ timeout: 15_000 })
}

async function selectSearchableOption(page: Page, testId: string, optionLabel: string) {
  const root = page.getByTestId(testId)
  await root.locator('input').click()
  const option = root.getByRole('option', { name: optionLabel, exact: true })
  await option.waitFor({ state: 'visible', timeout: 15_000 })
  await option.click()
}

async function localRow(page: Page, table: string, id: string) {
  return page.evaluate(
    async ({ table, id }) => {
      const db = (window as unknown as { __mslTestDb: { query: (sql: string, p?: unknown[]) => Promise<Record<string, unknown>[]> } }).__mslTestDb
      return (await db.query(`SELECT * FROM ${table} WHERE id = ?`, [id]))[0] ?? null
    },
    { table, id },
  )
}

async function newThreshingRecord(page: Page, thresherId: string): Promise<string> {
  await page.goto('/stations/threshing/monitor')
  await page.getByTestId('new-data-button').click()
  await page.waitForURL(/\/stations\/threshing\/form\/[^/]+$/)
  const id = page.url().split('/').pop() as string
  await page.locator('#field-thresher-id').fill(thresherId)
  await page.getByTestId('add-detail-row-button').click()
  await selectSearchableOption(page, 'time-slot-select-0', '07:00')
  await page.locator('#ffb-throughput-0').fill('45.5')
  return id
}

test.describe('Mobile sync & verification (audit 2026-10-04)', () => {
  test('#2 write-through: Simpan pushes to the server and the record becomes synced', async ({ page }) => {
    const g = guard(page)
    await login(page, OPERATOR)
    await chooseLine(page, 0)

    const id = await newThreshingRecord(page, `TH-WT-${Date.now()}`)
    const pushed = page.waitForResponse((r) => r.url().endsWith('/api/threshing-records') && r.request().method() === 'POST')
    await page.getByTestId('save-button').click()
    const response = await pushed
    expect(response.status()).toBe(201)
    expect(response.request().postDataJSON().production_line_id).toBe(fixture.lines[0].id)
    await page.waitForURL('**/stations/threshing/monitor')

    await expect.poll(async () => (await localRow(page, 'threshing_record', id))?.status).toBe('synced')
    expect((await localRow(page, 'threshing_record', id))?.server_id).toBeTruthy()

    await page.goto('/stations/threshing/preview')
    await expect(page.getByTestId(`record-item-${id}`)).toContainText('Tersinkron')
    // #4: the detail's Tanggal input is filled (was empty for ISO-UTC values).
    await page.getByTestId(`record-item-${id}`).click()
    await expect(page.locator('#field-tanggal')).not.toHaveValue('')
    g.assertClean()
  })

  test('#2/#9 write-through rejection: dialog on Simpan, then "Gagal sinkron" hint in Data Preview', async ({ page }) => {
    const g = guard(page, [{ status: 422, url: /\/api\/pressing-records$/ }])
    await login(page, OPERATOR)
    await chooseLine(page, 0)

    await page.goto('/stations/pressing/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/pressing\/form\/[^/]+$/)
    const id = page.url().split('/').pop() as string
    await page.locator('#field-presser-id').fill(`PR-REJ-${Date.now()}`)
    await page.getByTestId('add-detail-row-button').click()
    await selectSearchableOption(page, 'time-slot-select-0', '07:00')
    await page.locator('#digester-temp-0').fill('92')

    const pushed = page.waitForResponse((r) => r.url().endsWith('/api/pressing-records') && r.request().method() === 'POST')
    await page.getByTestId('save-button').click()
    expect((await pushed).status()).toBe(422)

    await expect(page.getByText('Tersimpan, tetapi ditolak server')).toBeVisible()
    await page.getByRole('button', { name: 'Mengerti' }).click()
    await page.waitForURL('**/stations/pressing/monitor')

    const row = await localRow(page, 'pressing_record', id)
    expect(row?.status).toBe('saved')
    expect(String(row?.sync_error)).toMatch(/\S/)

    await page.goto('/stations/pressing/preview')
    const item = page.getByTestId(`record-item-${id}`)
    await expect(item).toContainText('Tersimpan')
    await expect(item.getByTestId('sync-failure-hint')).toContainText('Gagal sinkron:')
    g.assertClean()
  })

  test('#1 supervisor verifies a synced record from Data Preview', async ({ page }) => {
    const g = guard(page)
    await login(page, SUPERVISOR)
    await chooseLine(page, 0)

    const id = await newThreshingRecord(page, `TH-VER-${Date.now()}`)
    await page.getByTestId('save-button').click()
    await page.waitForURL('**/stations/threshing/monitor')
    await expect.poll(async () => (await localRow(page, 'threshing_record', id))?.status).toBe('synced')

    await page.goto(`/stations/threshing/preview/${id}`)
    const patched = page.waitForResponse((r) => /\/api\/records\/threshing\/[^/]+\/verification$/.test(r.url()))
    await page.getByTestId('toggle-checked-button').click()
    expect((await patched).status()).toBe(200)

    await expect(page.getByTestId('verification-message')).toHaveText('Verifikasi tersimpan.')
    await expect(page.getByTestId('verification-message')).not.toContainText('koneksi')
    await expect(page.getByTestId('toggle-checked-button')).toContainText('Batalkan')
    expect((await localRow(page, 'threshing_record', id))?.checked_by).toBe(fixture.users['mse2e-supervisor01'])
    g.assertClean()
  })

  test('#5 manual sync uses the line the record was created on, not the currently selected line', async ({ page }) => {
    // The write-through push is aborted on purpose (simulated offline).
    const g = guard(page, [], { allowAbortedRequests: true })
    await login(page, OPERATOR)
    await chooseLine(page, 0)

    // Keep the record local: make the write-through push fail like offline.
    await page.route('**/api/threshing-records', (route) =>
      route.request().method() === 'POST' ? route.abort() : route.continue(),
    )
    const id = await newThreshingRecord(page, `TH-LINE-${Date.now()}`)
    await page.getByTestId('save-button').click()
    await page.waitForURL('**/stations/threshing/monitor')
    expect((await localRow(page, 'threshing_record', id))?.status).toBe('saved')
    await page.unroute('**/api/threshing-records')

    // Operator moves to Line 2, then syncs.
    await chooseLine(page, 1)
    const pushed = page.waitForResponse((r) => r.url().endsWith('/api/threshing-records') && r.request().method() === 'POST')
    await page.getByTestId('sync-button').click()
    const response = await pushed
    expect(response.status()).toBe(201)
    expect(response.request().postDataJSON().production_line_id).toBe(fixture.lines[0].id)

    await expect(page.getByTestId('sync-dialog-message')).toContainText('berhasil disinkronkan')
    expect((await localRow(page, 'threshing_record', id))?.status).toBe('synced')
    g.assertClean()
  })

  test('#3 grading sync: fake local parameter ids are mapped to real server ids (no 500)', async ({ page }) => {
    // GET /api/grading-parameters is served by the REAL backend (no stub):
    // the app maps its fake local ids to the server's ids by name.
    const g = guard(page)
    await login(page, OPERATOR)
    await chooseLine(page, 0)

    // A real Weighbridge record first (Grading links to it).
    const card = `WB-GR-${Date.now()}`
    await page.goto('/stations/weighbridge/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/weighbridge\/form\/[^/]+$/)
    await page.getByLabel('WB Card Number/ID').fill(card)
    await page.getByLabel('No. Kendaraan').fill('B 1 GR')
    await page.getByLabel('Nama Sopir').fill('Budi')
    await page.getByLabel('Estate/Supplier Asal').fill('Estate A')
    await page.getByLabel('Berat Masuk (Gross)').fill('15000')
    await page.getByTestId('save-button').click()
    await page.waitForURL('**/stations/weighbridge/monitor')

    // #4 display: the record's datetime-local input is filled (it rendered
    // empty "mm/dd/yyyy --:--" when bound to an ISO-UTC string).
    await page.goto('/stations/weighbridge/preview')
    await page.locator('[data-testid^="record-item-"]').filter({ hasText: card }).click()
    await expect(page.getByTestId('detail-record-datetime').locator('input')).toHaveValue(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/)

    await page.goto('/stations/grading/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/grading\/form\/[^/]+$/)
    const gradingId = page.url().split('/').pop() as string
    await page.getByLabel('No. Grading').fill(`GR-${Date.now()}`)
    await selectSearchableOption(page, 'wb-card-no-select', card)
    await page.getByLabel('Netto (kg)').fill('1000')
    await page.getByLabel('Quantity (bunch)').fill('50')
    await page.getByTestId('add-detail-row-button').click()
    await selectSearchableOption(page, 'detail-parameter-select-0', 'Mentah')
    await page.locator('#detail-qty-0').fill('10')
    await page.getByTestId('save-button').click()
    await page.waitForURL('**/stations/grading/monitor')

    await page.goto('/stations')
    // The master is fetched right before the Grading sync (syncService).
    const masterFetched = page.waitForResponse((r) => r.url().endsWith('/api/grading-parameters'))
    const gradingPost = page.waitForResponse((r) => r.url().endsWith('/api/grading-records') && r.request().method() === 'POST')
    await page.getByTestId('sync-button').click()
    const master = await masterFetched
    expect(master.status()).toBe(200)
    expect((await master.json()).data.map((p: { id: string }) => p.id)).toEqual(fixture.grading_parameters.map((p) => p.id))
    const response = await gradingPost
    expect(response.status()).toBe(201)
    const sent = response.request().postDataJSON().details[0].grading_parameter_id
    expect(sent).toBe(fixture.grading_parameters.find((p) => p.name === 'Mentah')?.id)

    await expect(page.getByTestId('sync-dialog-message')).toContainText('berhasil disinkronkan')
    expect((await localRow(page, 'grading_record', gradingId))?.status).toBe('synced')
    g.assertClean()
  })

  test('#3 grading sync without a parameter master: clear on-device failure, nothing sent, station named', async ({ page }) => {
    // The master endpoint exists now; this test simulates it being
    // UNAVAILABLE (e.g. an older server) so the on-device refusal path is
    // still proven. The 503 is the only deliberately provoked error.
    const g = guard(page, [{ status: 503, url: /\/api\/grading-parameters$/ }])
    await page.route('**/api/grading-parameters', (route) =>
      route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ message: 'unavailable' }) }),
    )
    await login(page, OPERATOR)
    await chooseLine(page, 0)
    const userId = fixture.users['mse2e-operator01']

    // Linked weighbridge already synced; grading detail uses a fake local id.
    await page.evaluate(async (userId) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, p?: unknown[]) => Promise<unknown> } }).__mslTestDb
      const now = new Date().toISOString()
      await db.run(`INSERT INTO weighbridge_record (id, status, wb_card_number, server_id, created_by, created_at, updated_at) VALUES ('e2e-wb-x', 'synced', 'WB-X', 'not-used', ?, ?, ?)`, [userId, now, now])
      await db.run(`INSERT INTO grading_record (id, status, grading_number, date, weighbridge_record_id, netto, quantity, created_by, created_at, updated_at) VALUES ('e2e-gr-x', 'saved', 'GR-X', '2026-10-04', 'e2e-wb-x', 1000, 50, ?, ?, ?)`, [userId, now, now])
      await db.run(`INSERT INTO grading_detail (id, grading_record_id, grading_parameter_id, quantity, created_at, updated_at) VALUES ('e2e-gd-x', 'e2e-gr-x', 'default-grading-parameter-1', 10, ?, ?)`, [now, now])
    }, userId)

    let gradingPosts = 0
    page.on('request', (r) => {
      if (r.url().endsWith('/api/grading-records') && r.method() === 'POST') gradingPosts += 1
    })
    await page.getByTestId('sync-button').click()

    const failed = page.getByTestId('sync-dialog-failed-item')
    await expect(failed).toHaveCount(1)
    await expect(failed).toContainText('Grading')
    await expect(failed).toContainText('GR-X')
    await expect(failed).toContainText('Master Quality Parameter')
    expect(gradingPosts).toBe(0)
    g.assertClean()
  })
})

test.describe('Mobile Data Preview / Grading / Reporting UX (audit 2026-10-04)', () => {
  test('#6 Data Preview pulls verification made on the web (real GET /api/records/{type}/verification)', async ({ page, request }) => {
    const g = guard(page)
    await login(page, OPERATOR)
    await chooseLine(page, 0)

    const id = await newThreshingRecord(page, `TH-PULL-${Date.now()}`)
    await page.getByTestId('save-button').click()
    await page.waitForURL('**/stations/threshing/monitor')
    await expect.poll(async () => (await localRow(page, 'threshing_record', id))?.status).toBe('synced')
    const serverId = (await localRow(page, 'threshing_record', id))?.server_id as string

    // The Supervisor verifies on the SERVER (outside this device), exactly
    // as a web approval would — no stub of the pull endpoint.
    const supLogin = await request.post(`${API_BASE}/api/login`, {
      data: { ...SUPERVISOR, device_name: 'e2e-sync-and-verification' },
      headers: { Accept: 'application/json' },
    })
    expect(supLogin.status()).toBe(200)
    const supToken = (await supLogin.json()).token as string
    const verified = await request.patch(`${API_BASE}/api/records/threshing/${serverId}/verification`, {
      data: { level: 'checked', value: true },
      headers: { Accept: 'application/json', Authorization: `Bearer ${supToken}` },
    })
    expect(verified.status()).toBe(200)
    const supervisorName = (await verified.json()).checked_by_name as string
    expect(supervisorName).toBeTruthy()
    expect((await localRow(page, 'threshing_record', id))?.checked_by ?? null).toBeNull()

    const pulled = page.waitForResponse((r) => /\/api\/records\/threshing\/verification\?/.test(r.url()))
    await page.goto(`/stations/threshing/preview/${id}`)
    const pullResponse = await pulled
    expect(pullResponse.status()).toBe(200)
    expect((await pullResponse.json()).data).toContainEqual(expect.objectContaining({ id: serverId, checked_by: fixture.users['mse2e-supervisor01'] }))
    await expect(page.getByTestId('verify-status-checked-by')).toContainText(supervisorName)
    await expect(page.getByTestId('verify-status-checked-by')).not.toContainText('Belum diperiksa')
    g.assertClean()
  })

  test('#7 an abandoned empty Weighbridge draft is not offered in Grading\'s WB Card dropdown', async ({ page }) => {
    const g = guard(page)
    await login(page, OPERATOR)
    await chooseLine(page, 0)

    await page.goto('/stations/weighbridge/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/weighbridge\/form\/[^/]+$/)
    const abandonedId = page.url().split('/').pop() as string
    await page.getByTestId('back-button').click()
    await page.waitForURL('**/stations/weighbridge/monitor')

    await page.goto('/stations/grading/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/grading\/form\/[^/]+$/)
    await page.getByTestId('wb-card-no-select').locator('input').click()
    const options = page.getByTestId('wb-card-no-select').getByRole('option')
    await expect(options.filter({ hasText: 'Tanpa WB Card No' })).toHaveCount(0)
    await expect(options.filter({ hasText: abandonedId })).toHaveCount(0)
    g.assertClean()
  })

  test('#8 Reporting works right after login without opening Station List first', async ({ page }) => {
    const g = guard(page)
    await login(page, OPERATOR)
    await page.goto('/reports')
    await expect(page.locator('[data-testid^="station-tile-"]')).toHaveCount(18, { timeout: 15_000 })
    await expect(page.getByTestId('no-stations')).toHaveCount(0)

    await page.getByTestId('station-tile-weighbridge').click()
    await expect(page.getByTestId('info-message')).toHaveText('Laporan Weighbridge belum tersedia di aplikasi mobile.')
    g.assertClean()
  })

  test('#10 Load Data Weighbridge has no horizontal overflow at 390px; Station List copy', async ({ page }) => {
    const g = guard(page)
    await page.setViewportSize({ width: 390, height: 844 })
    await login(page, OPERATOR)
    await chooseLine(page, 0)
    await expect(page.getByText('Stasiun MVP Aktif')).toHaveCount(0)
    await expect(page.getByText('Stasiun Aktif', { exact: true })).toBeVisible()

    await page.goto('/stations/weighbridge/preview')
    await expect(page.getByTestId('search-filter-input')).toBeVisible()
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
    expect(overflow).toBeLessThanOrEqual(0)
    const box = await page.getByTestId('search-filter-input').boundingBox()
    expect((box?.x ?? 0) + (box?.width ?? 0)).toBeLessThanOrEqual(390)
    g.assertClean()
  })
})
