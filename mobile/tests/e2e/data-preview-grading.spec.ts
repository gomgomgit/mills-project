import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-014--data-preview-grading / usecase-014--data-preview-grading
//
// FULL REWRITE (2026-08-19, scope expansion) to match
// DataPreviewGradingView.vue's rewrite adding a route-driven LIST mode
// (default, `/stations/grading/preview` — no `:id`) alongside the
// pre-existing DETAIL mode (`/stations/grading/preview/:id`) — mirrors
// data-preview-weighbridge.spec.ts's (screen-013) own 2026-08-18 rewrite as
// closely as possible given this screen's different detail-mode data
// source (getDraftWithDetails() + getGradingParameterOptions(), resolving
// Quality Parameter names client-side, rather than a single flat row read).
// The previous version of this file targeted the old single-record-only
// shape and never matched the current schema/UI — it is fully replaced
// below, not preserved.
//
// Per this suite's convention (see monitor-grading.spec.ts /
// form-grading.spec.ts / data-preview-weighbridge.spec.ts), multi-record
// list scenarios seed `grading_record` (and, for detail mode,
// `grading_detail` + `grading_parameter`) rows directly via the dev-only
// `window.__mslTestDb` bridge — there is no in-app flow that produces more
// than a couple of records at a time through the real UI.
//
// `grading_parameter` seeding note (same known_issue as
// form-grading.spec.ts): `seedGradingParametersIfNeeded()` (localSchema.ts)
// is exported but never actually invoked anywhere in the app (not from
// main.ts's bootstrap(), not from stores/auth.ts's login flow), so in a
// real run of this app today `grading_parameter` is EMPTY. This suite
// seeds its own rows directly (same two ids/names as
// form-grading.spec.ts's seedGradingParameters(), for consistency) so the
// detail-mode scenario below can meaningfully assert resolved Quality
// Parameter names; it does not fix the underlying wiring gap (out of this
// test-writing task's scope — no implementation file may be touched).
//
// This is a Capacitor mobile screen but IS browser-testable via the Vite
// dev server (per this suite's established convention) — not deferred as
// "mobile-only".
function todayLocalDateString(): string {
  const today = new Date()
  const yyyy = today.getFullYear()
  const mm = String(today.getMonth() + 1).padStart(2, '0')
  const dd = String(today.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

async function seedGradingRecord(
  page: Page,
  userId: string,
  overrides: {
    id: string
    status?: 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'
    gradingNumber?: string | null
    licensePlateNo?: string | null
    date?: string | null
    vehicleCode?: string | null
    estateSupplier?: string | null
    division?: string | null
    netto?: number | null
    quantity?: number | null
    note?: string | null
    updatedAt?: string
  },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, status, gradingNumber, licensePlateNo, date, vehicleCode, estateSupplier, division, netto, quantity, note, updatedAt }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = updatedAt ?? new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO grading_record
           (id, status, grading_number, date, license_plate_no, vehicle_code, estate_supplier, division,
            netto, quantity, note, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          id,
          status ?? 'saved',
          gradingNumber ?? null,
          date ?? null,
          licensePlateNo ?? null,
          vehicleCode ?? null,
          estateSupplier ?? null,
          division ?? null,
          netto ?? null,
          quantity ?? null,
          note ?? null,
          userId,
          now,
          now,
        ],
      )
    },
    {
      userId,
      id: overrides.id,
      status: overrides.status,
      gradingNumber: overrides.gradingNumber,
      licensePlateNo: overrides.licensePlateNo,
      date: overrides.date,
      vehicleCode: overrides.vehicleCode,
      estateSupplier: overrides.estateSupplier,
      division: overrides.division,
      netto: overrides.netto,
      quantity: overrides.quantity,
      note: overrides.note,
      updatedAt: overrides.updatedAt,
    },
  )
}

// See this file's header comment — grading_parameter is empty in a real
// run, so this suite seeds its own rows directly (same ids/names as
// form-grading.spec.ts's seedGradingParameters(), for consistency).
async function seedGradingParameters(page: Page): Promise<void> {
  await page.evaluate(async () => {
    const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
      .__mslTestDb
    const now = new Date().toISOString()
    const rows: Array<[string, string, string, number]> = [
      ['e2e-grading-param-kg', 'Brondolan Segar', 'kg', 1],
      ['e2e-grading-param-bunch', 'Masak', 'bunch', 2],
    ]

    for (const [id, name, uom, sortOrder] of rows) {
      await db.run(
        `INSERT OR REPLACE INTO grading_parameter (id, name, uom, sort_order, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [id, name, uom, sortOrder, now, now],
      )
    }
  })
}

async function seedGradingDetail(
  page: Page,
  overrides: { id: string; gradingRecordId: string; gradingParameterId: string; quantity: number; uom: string; percentage?: number | null },
): Promise<void> {
  await page.evaluate(
    async ({ id, gradingRecordId, gradingParameterId, quantity, uom, percentage }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO grading_detail
           (id, grading_record_id, grading_parameter_id, quantity, uom, percentage, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        [id, gradingRecordId, gradingParameterId, quantity, uom, percentage ?? null, now, now],
      )
    },
    { id: overrides.id, gradingRecordId: overrides.gradingRecordId, gradingParameterId: overrides.gradingParameterId, quantity: overrides.quantity, uom: overrides.uom, percentage: overrides.percentage },
  )
}

test.describe('Data Preview Grading (screen-014)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  // Scenario: "Record Tidak Ditemukan" — detail mode, invalid id.
  test('Data Preview Grading — Record Tidak Ditemukan', async ({ page }) => {
    await page.goto('/stations/grading/preview/does-not-exist')

    await expect(page.getByTestId('record-not-found')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Back' })).toBeVisible()
  })

  // Scenario: the date filter defaults to today's local date on list-view
  // open, with no seeded records and no user interaction needed.
  test('Data Preview Grading — Filter Tanggal Default ke Hari Ini', async ({ page }) => {
    await page.goto('/stations/grading/preview')

    await expect(page.getByTestId('date-filter-input')).toHaveValue(todayLocalDateString())
  })

  // Scenario: "berhasil" — list mode, apply search filter, tap a
  // saved/synced item -> detail mode, with resolved Quality Parameter
  // names in the detail grid, all header fields read-only.
  test('Data Preview Grading — berhasil (list -> detail, Quality Parameter names resolved)', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = todayLocalDateString()
    await seedGradingParameters(page)
    await seedGradingRecord(page, userId, {
      id: 'e2e-grading-target',
      status: 'saved',
      gradingNumber: 'GR-PREVIEW-01',
      licensePlateNo: 'B 1234 CD',
      vehicleCode: 'VC-001',
      estateSupplier: 'Estate A',
      division: 'Divisi 1',
      netto: 15000,
      quantity: 12,
      note: 'Catatan e2e',
      // Dated TODAY so it's visible under the list's default date filter
      // with no filter interaction yet.
      date: today,
    })
    await seedGradingRecord(page, userId, {
      id: 'e2e-grading-other',
      status: 'synced',
      gradingNumber: 'GR-OTHER-02',
      licensePlateNo: 'B 9999 ZZ',
      date: today,
    })
    await seedGradingDetail(page, {
      id: 'e2e-grading-detail-1',
      gradingRecordId: 'e2e-grading-target',
      gradingParameterId: 'e2e-grading-param-kg',
      quantity: 12,
      uom: 'kg',
      percentage: 80,
    })
    await seedGradingDetail(page, {
      id: 'e2e-grading-detail-2',
      gradingRecordId: 'e2e-grading-target',
      gradingParameterId: 'e2e-grading-param-bunch',
      quantity: 3,
      uom: 'bunch',
      percentage: 25,
    })

    await page.goto('/stations/grading/preview')
    await expect(page.getByTestId('record-item-e2e-grading-target')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-grading-other')).toBeVisible()

    await page.getByTestId('search-filter-input').fill('GR-PREVIEW')
    await expect(page.getByTestId('record-item-e2e-grading-target')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-grading-other')).toBeHidden()

    await page.getByTestId('record-item-e2e-grading-target').click()
    await page.waitForURL('**/stations/grading/preview/e2e-grading-target')

    await expect(page.getByLabel('No. Grading')).toHaveValue('GR-PREVIEW-01')
    await expect(page.getByLabel('No. Grading')).toBeDisabled()
    await expect(page.getByLabel('No. Polisi')).toHaveValue('B 1234 CD')
    await expect(page.getByLabel('No. Polisi')).toBeDisabled()

    const detailRows = page.getByTestId('detail-rows-list')
    await expect(detailRows).toContainText('Brondolan Segar')
    await expect(detailRows).toContainText('Masak')

    // 'Checked By' / 'Acknowledged By' are never rendered on this screen.
    await expect(page.getByText('Checked By', { exact: false })).toHaveCount(0)
    await expect(page.getByText('Acknowledged By', { exact: false })).toHaveCount(0)
  })

  // Scenario: "Tap Item Draft/Pause" — tap a draft/pause item -> navigates
  // to Form Grading, no detail switch.
  test('Data Preview Grading — Tap Item Draft/Pause', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedGradingRecord(page, userId, {
      id: 'e2e-grading-draft',
      status: 'draft_ongoing',
      gradingNumber: 'GR-DRAFT-01',
      // Dated TODAY so the row is present under the default date filter to
      // be tapped.
      date: todayLocalDateString(),
    })

    await page.goto('/stations/grading/preview')
    await page.getByTestId('record-item-e2e-grading-draft').click()

    await page.waitForURL('**/stations/grading/form/e2e-grading-draft')
    await expect(page).not.toHaveURL(/\/stations\/grading\/preview\/e2e-grading-draft/)
  })

  // Scenario: "Filter Diterapkan" — apply date filter -> list updates to
  // matching records only.
  test('Data Preview Grading — Filter Diterapkan', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedGradingRecord(page, userId, {
      id: 'e2e-grading-aug10',
      status: 'saved',
      gradingNumber: 'GR-AUG10',
      date: '2026-08-10',
    })
    await seedGradingRecord(page, userId, {
      id: 'e2e-grading-aug11',
      status: 'saved',
      gradingNumber: 'GR-AUG11',
      date: '2026-08-11',
    })

    await page.goto('/stations/grading/preview')

    // Both fixture records are dated 2026-08-10/11, not today — clear the
    // list's default today date filter first to establish the "both
    // visible, unfiltered" baseline before exercising exact-date filtering.
    await page.getByTestId('date-filter-input').fill('')
    await expect(page.getByTestId('record-item-e2e-grading-aug10')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-grading-aug11')).toBeVisible()

    await page.getByTestId('date-filter-input').fill('2026-08-11')

    await expect(page.getByTestId('record-item-e2e-grading-aug11')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-grading-aug10')).toBeHidden()
  })

  // Scenario: "Filter Tidak Menghasilkan Apapun" — filter matches nothing
  // -> not-found/empty message + Reset Filter button; clicking it restores
  // the full list.
  test('Data Preview Grading — Filter Tidak Menghasilkan Apapun', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = todayLocalDateString()
    await seedGradingRecord(page, userId, {
      id: 'e2e-grading-solo',
      status: 'saved',
      gradingNumber: 'GR-SOLO',
      licensePlateNo: 'B 5555 SS',
      // Dated TODAY so it stays visible against the list's default
      // (untouched) date filter throughout this scenario, which only
      // exercises the search filter.
      date: today,
    })

    await page.goto('/stations/grading/preview')
    await expect(page.getByTestId('record-item-e2e-grading-solo')).toBeVisible()

    await page.getByTestId('search-filter-input').fill('no-such-record-keyword')

    await expect(page.getByTestId('record-list-empty')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-grading-solo')).toBeHidden()
    await expect(page.getByTestId('reset-filter-button')).toBeVisible()

    await page.getByTestId('reset-filter-button').click()

    await expect(page.getByTestId('date-filter-input')).toHaveValue('')
    await expect(page.getByTestId('search-filter-input')).toHaveValue('')
    await expect(page.getByTestId('record-item-e2e-grading-solo')).toBeVisible()
    await expect(page.getByTestId('record-list-empty')).toBeHidden()
  })

  // Scenario: "List Kosong" — no records at all -> empty state.
  test('Data Preview Grading — List Kosong', async ({ page }) => {
    await page.goto('/stations/grading/preview')

    await expect(page.getByTestId('record-list-empty')).toBeVisible()
    await expect(page.getByTestId('record-list')).toBeHidden()
  })

  // Scenario: "Tap Breadcrumb" — tap the 'Grading' breadcrumb segment ->
  // navigates to Monitor Grading.
  test('Data Preview Grading — Tap Breadcrumb', async ({ page }) => {
    await page.goto('/stations/grading/preview')

    await page.getByTestId('breadcrumb-grading').click()
    await page.waitForURL('**/stations/grading/monitor')
  })

  // Scenario: "Buka Menu Hamburger" — tap hamburger -> nav menu visible.
  test('Data Preview Grading — Buka Menu Hamburger', async ({ page }) => {
    await page.goto('/stations/grading/preview')

    await expect(page.getByTestId('nav-menu')).toBeHidden()

    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu-change-password')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })

  // Scenario: "Back dari List Mode" — in list mode, tap Back -> returns to
  // Monitor Grading.
  test('Data Preview Grading — Back dari List Mode', async ({ page }) => {
    await page.goto('/stations/grading/preview')

    await page.getByRole('button', { name: 'Back' }).click()

    await page.waitForURL('**/stations/grading/monitor')
  })

  // Scenario: "Back dari Mode Detail" — in detail mode, tap Back -> returns
  // to list mode (URL loses id, no full page reload / state preserved —
  // asserted by re-checking the same component instance's list renders
  // correctly straight after, with no intermediate navigation event other
  // than this SPA route change), NOT Monitor Grading.
  test('Data Preview Grading — Back dari Mode Detail (list state preserved, no full reload)', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = todayLocalDateString()
    await seedGradingRecord(page, userId, {
      id: 'e2e-grading-back',
      status: 'saved',
      gradingNumber: 'GR-BACK-01',
      date: today,
    })

    await page.goto('/stations/grading/preview/e2e-grading-back')
    await expect(page.getByLabel('No. Grading')).toHaveValue('GR-BACK-01')

    // Marks the current document so a full page reload (which would tear
    // down and recreate the document, clearing this marker) can be
    // detected — the SPA-internal Back navigation must NOT trigger one.
    await page.evaluate(() => {
      ;(window as unknown as { __e2eNoReloadMarker: boolean }).__e2eNoReloadMarker = true
    })

    await page.getByRole('button', { name: 'Back' }).click()

    await page.waitForURL('**/stations/grading/preview')
    await expect(page).not.toHaveURL('**/stations/grading/monitor')
    await expect(page.getByTestId('record-item-e2e-grading-back')).toBeVisible()

    const markerSurvived = await page.evaluate(
      () => (window as unknown as { __e2eNoReloadMarker?: boolean }).__e2eNoReloadMarker === true,
    )
    expect(markerSurvived).toBe(true)
  })
})
