import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-007--monitor-weighbridge / usecase-007--monitor-weighbridge
//
// Rewritten (2026-08-18) to match MonitorWeighbridgeView.vue's list-view
// rewrite — the old single-draft summary + Pause/Clear/Lanjutkan-badge
// flow is gone (dropped entirely, along with its e2e coverage below). The
// screen now renders a scrollable list of the current user's
// ongoing/paused local drafts (every row labeled uniformly "Pause"), plus
// 'New Data' / 'Load Data' / 'Back' actions and the same header/breadcrumb/
// hamburger nav-menu as HomeView.vue / StationListView.vue.
//
// Seeds `weighbridge_record` rows directly via the dev-only
// `window.__mslTestDb` bridge (same pattern as helpers.ts's
// seedPausedDrafts() / station-list.spec.ts's inline "Color indicator"
// test) — there is no in-app flow that produces more than one draft at a
// time through the real UI, so direct seeding is the only practical way to
// exercise "render with an existing list" scenarios.
async function seedWeighbridgeDraft(
  page: Page,
  userId: string,
  overrides: { id: string; status?: 'draft_ongoing' | 'draft_paused'; wbCardNumber?: string | null; updatedAt?: string },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, status, wbCardNumber, updatedAt }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = updatedAt ?? new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO weighbridge_record (id, status, wb_card_number, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'draft_ongoing', wbCardNumber ?? null, userId, now, now],
      )
    },
    {
      userId,
      id: overrides.id,
      status: overrides.status,
      wbCardNumber: overrides.wbCardNumber,
      updatedAt: overrides.updatedAt,
    },
  )
}


/**
 * "today's counter" addition — seeds a full `weighbridge_record` row
 * (status/record_datetime/net_weight/quantity), for the new "Hari Ini"
 * section (weighbridgeRecordRepo.getTodaySummary()). Separate from
 * seedWeighbridgeDraft() above (which only sets the columns the
 * draft/pause LIST needs) — the counter needs record_datetime + the two
 * numeric sum columns as well, mirroring
 * data-preview-weighbridge.spec.ts's seedWeighbridgeRecord() helper.
 * record_datetime replaces the old arrival_datetime/dispatch_datetime pair
 * as of entity-catalog v5 — a single column for both Receive and Dispatch.
 */
async function seedWeighbridgeRecordForCounter(
  page: Page,
  userId: string,
  overrides: {
    id: string
    status?: 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'
    recordDatetime: string
    netWeight: number
    quantity: number
  },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, status, recordDatetime, netWeight, quantity }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO weighbridge_record
           (id, status, record_datetime, net_weight, quantity, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'saved', recordDatetime, netWeight, quantity, userId, now, now],
      )
    },
    { userId, id: overrides.id, status: overrides.status, recordDatetime: overrides.recordDatetime, netWeight: overrides.netWeight, quantity: overrides.quantity },
  )
}

/**
 * Formats a local (device-timezone) date-time string in the same
 * naive/no-offset "YYYY-MM-DDTHH:mm" shape this app's own record_datetime
 * values use (see FormWeighbridgeView.vue's <input type="datetime-local">
 * binding) — required here (rather than `Date#toISOString()`, which is
 * UTC) because `getTodaySummary()`'s SQL filters via
 * `date('now', 'localtime')`, i.e. the LOCAL calendar day.
 */
function toLocalDateTimeString(date: Date, hour = '08:00'): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}T${hour}`
}

test.describe('Monitor Weighbridge (screen-007)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  // Scenario 1: "success"
  test('Monitor Weighbridge — success', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedWeighbridgeDraft(page, userId, { id: 'e2e-wb-existing', wbCardNumber: 'WB-EXISTING' })

    await page.goto('/stations/weighbridge/monitor')
    await expect(page.getByTestId('draft-item-e2e-wb-existing')).toBeVisible()

    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/weighbridge\/form\/[^/]+$/)
    await expect(page).not.toHaveURL('**/stations/weighbridge/form/e2e-wb-existing')
  })

  // Scenario 2: "Lanjutkan Draft/Pause"
  test('Monitor Weighbridge — Lanjutkan Draft/Pause', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedWeighbridgeDraft(page, userId, {
      id: 'e2e-wb-lanjutkan',
      status: 'draft_paused',
      wbCardNumber: 'WB-LANJUTKAN',
    })

    await page.goto('/stations/weighbridge/monitor')

    const item = page.getByTestId('draft-item-e2e-wb-lanjutkan')
    await expect(item).toBeVisible()
    await expect(item).toContainText('Pause')

    await item.click()
    await page.waitForURL('**/stations/weighbridge/form/e2e-wb-lanjutkan')

    // Navigating back to Monitor confirms the tap was pure navigation — no
    // status-update call happened as a side effect of the tap itself: the
    // record is still present (and still shown as "Pause", i.e. its
    // draft_paused status was not silently flipped to draft_ongoing by
    // some other means).
    await page.goto('/stations/weighbridge/monitor')
    await expect(page.getByTestId('draft-item-e2e-wb-lanjutkan')).toContainText('Pause')
  })

  // Scenario 3: "Load Data"
  test('Monitor Weighbridge — Load Data', async ({ page }) => {
    await page.goto('/stations/weighbridge/monitor')

    await page.getByTestId('load-data-button').click()
    await page.waitForURL(/\/stations\/weighbridge\/preview$/)
  })

  // Scenario 4: "Tap Breadcrumb"
  test('Monitor Weighbridge — Tap Breadcrumb', async ({ page }) => {
    await page.goto('/stations/weighbridge/monitor')

    await page.getByTestId('breadcrumb-production-process-activity').click()
    await page.waitForURL('**/stations')
  })

  // Scenario 5: "Buka Menu Hamburger"
  test('Monitor Weighbridge — Buka Menu Hamburger', async ({ page }) => {
    await page.goto('/stations/weighbridge/monitor')

    await expect(page.getByTestId('nav-menu')).toBeHidden()

    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu-change-password')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })

  // Scenario 6: "Belum Ada Draft"
  test('Monitor Weighbridge — Belum Ada Draft', async ({ page }) => {
    await page.goto('/stations/weighbridge/monitor')

    await expect(page.getByTestId('draft-list-empty')).toContainText('Belum ada draft timbangan tersimpan.')
    await expect(page.getByTestId('new-data-button')).toBeEnabled()
  })
  // "today's counter" addition — "Counter Hari Ini Menampilkan Data"
  test('Monitor Weighbridge — Counter Hari Ini Menampilkan Data', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = new Date()
    const yesterday = new Date(today.getTime() - 24 * 60 * 60 * 1000)

    // Two today-dated rows, different statuses, both counted (no status
    // filter) — countWb=2, sumNetWeight=10000+8000=18000, sumQuantity=1+2=3.
    await seedWeighbridgeRecordForCounter(page, userId, {
      id: 'e2e-wb-counter-today-1',
      status: 'saved',
      recordDatetime: toLocalDateTimeString(today, '08:00'),
      netWeight: 10000,
      quantity: 1,
    })
    await seedWeighbridgeRecordForCounter(page, userId, {
      id: 'e2e-wb-counter-today-2',
      status: 'draft_ongoing',
      recordDatetime: toLocalDateTimeString(today, '11:30'),
      netWeight: 8000,
      quantity: 2,
    })
    // A non-today row — must NOT be counted.
    await seedWeighbridgeRecordForCounter(page, userId, {
      id: 'e2e-wb-counter-yesterday',
      status: 'saved',
      recordDatetime: toLocalDateTimeString(yesterday, '09:00'),
      netWeight: 99999,
      quantity: 99,
    })

    await page.goto('/stations/weighbridge/monitor')

    await expect(page.getByTestId('counter-count-wb')).toHaveText('2')
    await expect(page.getByTestId('counter-net-weight')).toHaveText('18000')
    await expect(page.getByTestId('counter-quantity')).toHaveText('3')
  })

  // "today's counter" addition — "Belum Ada Data Hari Ini"
  test('Monitor Weighbridge — Belum Ada Data Hari Ini', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000)

    // Only a non-today row exists — the counter must still show all zero.
    await seedWeighbridgeRecordForCounter(page, userId, {
      id: 'e2e-wb-counter-none-today',
      status: 'saved',
      recordDatetime: toLocalDateTimeString(yesterday, '09:00'),
      netWeight: 5000,
      quantity: 1,
    })

    await page.goto('/stations/weighbridge/monitor')

    await expect(page.getByTestId('counter-count-wb')).toHaveText('0')
    await expect(page.getByTestId('counter-net-weight')).toHaveText('0')
    await expect(page.getByTestId('counter-quantity')).toHaveText('0')
  })
})
