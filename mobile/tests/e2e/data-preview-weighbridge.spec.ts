import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-013--data-preview-weighbridge / usecase-013--data-preview-weighbridge
//
// Rewritten (2026-08-18, scope expansion) to match
// DataPreviewWeighbridgeView.vue's rewrite adding a route-driven LIST mode
// (default, `/stations/weighbridge/preview` — no `:id`) alongside the
// pre-existing DETAIL mode (`/stations/weighbridge/preview/:id`). The
// previous version of this file only exercised the old single-record-only
// flow (navigate through Monitor -> Form -> Save -> Preview by id); that
// coverage is preserved below as scenario "berhasil" (now via the new LIST
// mode + a filter + a row tap, rather than a raw `page.goto` by id) plus
// "Record Tidak Ditemukan" (detail mode, invalid id — unchanged).
//
// Per this suite's convention (see monitor-weighbridge.spec.ts /
// station-list.spec.ts), multi-record list scenarios seed
// `weighbridge_record` rows directly via the dev-only `window.__mslTestDb`
// bridge — there is no in-app flow that produces more than a couple of
// records at a time through the real UI.
//
// This is a Capacitor mobile screen but IS browser-testable via the Vite
// dev server (per this suite's established convention) — not deferred as
// "mobile-only".
//
// Updated again (2026-08-18, additive change) for `loadList()` now
// defaulting the date filter to TODAY's local date on every list-mode
// mount (previously always `''`) — see DataPreviewWeighbridgeView.vue's
// own `todayLocalDateString()` (local getFullYear/getMonth+1/getDate,
// zero-padded, NOT UTC/toISOString). `todayLocalDateString()` below
// mirrors that exact algorithm in this file's own (Node-side) process, so
// scenarios can independently compute the same "today" value the app
// computes client-side (same machine, no explicit Playwright
// `timezoneId` override configured, so both sides observe the same local
// clock). Consequences applied throughout this file:
//   - A new scenario ("Filter Tanggal Default ke Hari Ini") directly
//     asserts the date filter input's default value.
//   - Every pre-existing scenario that seeded a record NOT dated today and
//     asserted it visible in the list with no date-filter interaction
//     ("berhasil", "Tap Item Draft/Pause", "Filter Tidak Menghasilkan
//     Apapun", "Back dari Mode Detail") now seeds that record dated TODAY
//     instead, so it still appears under the list's default filter — the
//     scenario's actual intent (search filtering / row-tap navigation /
//     reset behavior / post-Back visibility) is otherwise unchanged.
//   - "Filter Diterapkan" specifically exercises exact-date filtering
//     across two different fixed dates, so instead of reseeding to today
//     it now explicitly clears the date filter first to establish the
//     "both visible" baseline, then applies the exact-date filter as
//     before.
function todayLocalDateString(): string {
  const today = new Date()
  const yyyy = today.getFullYear()
  const mm = String(today.getMonth() + 1).padStart(2, '0')
  const dd = String(today.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

async function seedWeighbridgeRecord(
  page: Page,
  userId: string,
  overrides: {
    id: string
    status?: 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'
    wbCardNumber?: string | null
    driverName?: string | null
    arrivalDatetime?: string | null
    updatedAt?: string
  },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, status, wbCardNumber, driverName, arrivalDatetime, updatedAt }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = updatedAt ?? new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO weighbridge_record
           (id, status, wb_card_number, driver_name, arrival_datetime, dispatch_datetime, vehicle_number,
            estate_supplier, division, block, gross_weight, tare_weight, net_weight, quantity,
            checked_by, acknowledged_by, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          id,
          status ?? 'saved',
          wbCardNumber ?? null,
          driverName ?? null,
          arrivalDatetime ?? null,
          '2026-08-17T10:00',
          'B 1234 CD',
          'Estate A',
          'Divisi 1',
          'Blok 3',
          15000,
          5000,
          10000,
          1,
          'Supervisor Satu',
          'Mill Manager',
          userId,
          now,
          now,
        ],
      )
    },
    { userId, id: overrides.id, status: overrides.status, wbCardNumber: overrides.wbCardNumber, driverName: overrides.driverName, arrivalDatetime: overrides.arrivalDatetime, updatedAt: overrides.updatedAt },
  )
}

test.describe('Data Preview Weighbridge (screen-013)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  // Scenario: "Record Tidak Ditemukan" — detail mode, invalid id.
  test('Data Preview Weighbridge — Record Tidak Ditemukan', async ({ page }) => {
    await page.goto('/stations/weighbridge/preview/does-not-exist')

    await expect(page.getByTestId('record-not-found')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Back' })).toBeVisible()
  })

  // Additive scenario (2026-08-18): the date filter defaults to today's
  // local date on list-view open, with no seeded records and no user
  // interaction needed.
  test('Data Preview Weighbridge — Filter Tanggal Default ke Hari Ini', async ({ page }) => {
    await page.goto('/stations/weighbridge/preview')

    await expect(page.getByTestId('date-filter-input')).toHaveValue(todayLocalDateString())
  })

  // Scenario: "berhasil" — list mode, apply filter, tap a saved/synced
  // item -> detail mode, all fields read-only.
  test('Data Preview Weighbridge — berhasil', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = todayLocalDateString()
    await seedWeighbridgeRecord(page, userId, {
      id: 'e2e-wb-target',
      status: 'saved',
      wbCardNumber: 'WB-PREVIEW-01',
      driverName: 'Budi Santoso',
      // Dated TODAY so it's visible under the list's default date filter
      // with no filter interaction yet — see this file's header comment.
      arrivalDatetime: `${today}T08:00`,
    })
    await seedWeighbridgeRecord(page, userId, {
      id: 'e2e-wb-other',
      status: 'synced',
      wbCardNumber: 'WB-OTHER-02',
      driverName: 'Citra',
      arrivalDatetime: `${today}T09:00`,
    })

    await page.goto('/stations/weighbridge/preview')
    await expect(page.getByTestId('record-item-e2e-wb-target')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-wb-other')).toBeVisible()

    await page.getByTestId('search-filter-input').fill('WB-PREVIEW')
    await expect(page.getByTestId('record-item-e2e-wb-target')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-wb-other')).toBeHidden()

    await page.getByTestId('record-item-e2e-wb-target').click()
    await page.waitForURL('**/stations/weighbridge/preview/e2e-wb-target')

    await expect(page.getByLabel('No. WB Card')).toHaveValue('WB-PREVIEW-01')
    await expect(page.getByLabel('No. WB Card')).toBeDisabled()
    await expect(page.getByLabel('Nama Sopir')).toHaveValue('Budi Santoso')
    await expect(page.getByLabel('Nama Sopir')).toBeDisabled()
  })

  // Scenario: "Tap Item Draft/Pause" — tap a draft/pause item -> navigates
  // to Form Weighbridge, no detail switch.
  test('Data Preview Weighbridge — Tap Item Draft/Pause', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedWeighbridgeRecord(page, userId, {
      id: 'e2e-wb-draft',
      status: 'draft_ongoing',
      wbCardNumber: 'WB-DRAFT-01',
      // Dated TODAY so the row is present under the default date filter
      // to be tapped — the previous version left this null/unset, which
      // is no longer visible by default (recordDate '' !== today).
      arrivalDatetime: `${todayLocalDateString()}T08:00`,
    })

    await page.goto('/stations/weighbridge/preview')
    await page.getByTestId('record-item-e2e-wb-draft').click()

    await page.waitForURL('**/stations/weighbridge/form/e2e-wb-draft')
    await expect(page).not.toHaveURL(/\/stations\/weighbridge\/preview\/e2e-wb-draft/)
  })

  // Scenario: "Filter Diterapkan" — apply date/search filter -> list
  // updates to matching records only.
  test('Data Preview Weighbridge — Filter Diterapkan', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedWeighbridgeRecord(page, userId, {
      id: 'e2e-wb-aug10',
      status: 'saved',
      wbCardNumber: 'WB-AUG10',
      arrivalDatetime: '2026-08-10T08:00',
    })
    await seedWeighbridgeRecord(page, userId, {
      id: 'e2e-wb-aug11',
      status: 'saved',
      wbCardNumber: 'WB-AUG11',
      arrivalDatetime: '2026-08-11T08:00',
    })

    await page.goto('/stations/weighbridge/preview')

    // Both fixture records are dated 2026-08-10/11, not today — clear the
    // list's default today date filter first to establish the "both
    // visible, unfiltered" baseline before exercising exact-date
    // filtering below.
    await page.getByTestId('date-filter-input').fill('')
    await expect(page.getByTestId('record-item-e2e-wb-aug10')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-wb-aug11')).toBeVisible()

    await page.getByTestId('date-filter-input').fill('2026-08-11')

    await expect(page.getByTestId('record-item-e2e-wb-aug11')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-wb-aug10')).toBeHidden()
  })

  // Scenario: "Filter Tidak Menghasilkan Apapun" — filter matches nothing
  // -> not-found/empty message + reset option (clearing the filter inputs
  // restores the full list — this screen has no dedicated "reset" button,
  // see DataPreviewWeighbridgeView.vue's filter-row markup).
  test('Data Preview Weighbridge — Filter Tidak Menghasilkan Apapun', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedWeighbridgeRecord(page, userId, {
      id: 'e2e-wb-solo',
      status: 'saved',
      wbCardNumber: 'WB-SOLO',
      driverName: 'Solo Driver',
      // Dated TODAY so it stays visible against the list's default
      // (untouched) date filter throughout this scenario, which only
      // exercises the search filter.
      arrivalDatetime: `${todayLocalDateString()}T08:00`,
    })

    await page.goto('/stations/weighbridge/preview')
    await expect(page.getByTestId('record-item-e2e-wb-solo')).toBeVisible()

    await page.getByTestId('search-filter-input').fill('no-such-record-keyword')

    await expect(page.getByTestId('record-list-empty')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-wb-solo')).toBeHidden()

    // Reset: clearing the search filter restores the previously matching
    // record.
    await page.getByTestId('search-filter-input').fill('')
    await expect(page.getByTestId('record-item-e2e-wb-solo')).toBeVisible()
    await expect(page.getByTestId('record-list-empty')).toBeHidden()
  })

  // Scenario: "List Kosong" — no records at all -> empty state.
  test('Data Preview Weighbridge — List Kosong', async ({ page }) => {
    await page.goto('/stations/weighbridge/preview')

    await expect(page.getByTestId('record-list-empty')).toBeVisible()
    await expect(page.getByTestId('record-list')).toBeHidden()
  })

  // Scenario: "Tap Breadcrumb" — tap a breadcrumb segment -> navigates
  // accordingly.
  test('Data Preview Weighbridge — Tap Breadcrumb', async ({ page }) => {
    await page.goto('/stations/weighbridge/preview')

    await page.getByTestId('breadcrumb-weighbridge').click()
    await page.waitForURL('**/stations/weighbridge/monitor')
  })

  // Scenario: "Buka Menu Hamburger" — tap hamburger -> nav menu visible.
  test('Data Preview Weighbridge — Buka Menu Hamburger', async ({ page }) => {
    await page.goto('/stations/weighbridge/preview')

    await expect(page.getByTestId('nav-menu')).toBeHidden()

    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu-change-password')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })

  // Scenario: "Back dari Mode Detail" — in detail mode, tap Back ->
  // returns to list mode (URL loses id), NOT Monitor Weighbridge.
  test('Data Preview Weighbridge — Back dari Mode Detail', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedWeighbridgeRecord(page, userId, {
      id: 'e2e-wb-back',
      status: 'saved',
      wbCardNumber: 'WB-BACK-01',
      // Dated TODAY so the record is still visible in list mode's default
      // (today) filter once Back navigates away from detail mode below —
      // detail mode itself (the initial page.goto) is unaffected by the
      // date filter either way.
      arrivalDatetime: `${todayLocalDateString()}T08:00`,
    })

    await page.goto('/stations/weighbridge/preview/e2e-wb-back')
    await expect(page.getByLabel('No. WB Card')).toHaveValue('WB-BACK-01')

    await page.getByRole('button', { name: 'Back' }).click()

    await page.waitForURL('**/stations/weighbridge/preview')
    await expect(page).not.toHaveURL('**/stations/weighbridge/monitor')
    await expect(page.getByTestId('record-item-e2e-wb-back')).toBeVisible()
  })
})
