import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-008--monitor-grading / usecase-008--monitor-grading
//
// Rewritten (2026-08-18, entity-catalog v2) to match MonitorGradingView.vue's
// list-view rewrite — the old single-draft summary + Mulai Input Baru/
// Lanjutkan/Pause/Clear flow is gone (dropped entirely, along with its e2e
// coverage below), mirroring monitor-weighbridge.spec.ts's (screen-007) own
// equivalent rewrite. The screen now renders a scrollable list of the
// current user's ongoing/paused local drafts (every row labeled uniformly
// "Pause"), plus 'New Data' / 'Load Data' / 'Back' actions and the same
// header/breadcrumb/hamburger nav-menu as MonitorWeighbridgeView.vue /
// HomeView.vue / StationListView.vue.
//
// Seeds `grading_record` rows directly via the dev-only `window.__mslTestDb`
// bridge (same pattern as helpers.ts's seedPausedDrafts() /
// monitor-weighbridge.spec.ts's seedWeighbridgeDraft()) — there is no in-app
// flow that produces more than one draft at a time through the real UI, so
// direct seeding is the only practical way to exercise "render with an
// existing list" scenarios.
async function seedGradingDraft(
  page: Page,
  userId: string,
  overrides: { id: string; status?: 'draft_ongoing' | 'draft_paused'; gradingNumber?: string | null; updatedAt?: string },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, status, gradingNumber, updatedAt }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = updatedAt ?? new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO grading_record (id, status, grading_number, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'draft_ongoing', gradingNumber ?? null, userId, now, now],
      )
    },
    {
      userId,
      id: overrides.id,
      status: overrides.status,
      gradingNumber: overrides.gradingNumber,
      updatedAt: overrides.updatedAt,
    },
  )
}

/**
 * "today's counter" addition — seeds a full `grading_record` row
 * (status/date/netto/quantity), for the new "Hari Ini" section
 * (gradingRecordRepo.getTodaySummary()). Separate from seedGradingDraft()
 * above (which only sets the columns the draft/pause LIST needs) — the
 * counter needs `date` + the two numeric sum columns as well, mirroring
 * monitor-weighbridge.spec.ts's seedWeighbridgeRecordForCounter() helper.
 */
async function seedGradingRecordForCounter(
  page: Page,
  userId: string,
  overrides: {
    id: string
    status?: 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'
    date: string
    netto: number
    quantity: number
  },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, status, date, netto, quantity }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO grading_record
           (id, status, date, netto, quantity, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'saved', date, netto, quantity, userId, now, now],
      )
    },
    { userId, id: overrides.id, status: overrides.status, date: overrides.date, netto: overrides.netto, quantity: overrides.quantity },
  )
}

/**
 * Formats a local (device-timezone) date string ("YYYY-MM-DD") — required
 * here (rather than `Date#toISOString()`, which is UTC) because
 * `getTodaySummary()`'s SQL filters via `date('now', 'localtime')`, i.e.
 * the LOCAL calendar day. Mirrors monitor-weighbridge.spec.ts's
 * toLocalDateTimeString(), simplified to a date-only string since
 * grading_record's `date` column (unlike weighbridge's
 * `arrival_datetime`) carries no time component.
 */
function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

test.describe('Monitor Grading (screen-008)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  // Scenario 1: "success"
  test('Monitor Grading — success', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedGradingDraft(page, userId, { id: 'e2e-grading-existing', gradingNumber: 'GR-EXISTING' })

    await page.goto('/stations/grading/monitor')
    await expect(page.getByTestId('draft-item-e2e-grading-existing')).toBeVisible()

    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/grading\/form\/[^/]+$/)
    await expect(page).not.toHaveURL('**/stations/grading/form/e2e-grading-existing')
  })

  // Scenario 2: "Lanjutkan Draft/Pause"
  test('Monitor Grading — Lanjutkan Draft/Pause', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedGradingDraft(page, userId, {
      id: 'e2e-grading-lanjutkan',
      status: 'draft_paused',
      gradingNumber: 'GR-LANJUTKAN',
    })

    await page.goto('/stations/grading/monitor')

    const item = page.getByTestId('draft-item-e2e-grading-lanjutkan')
    await expect(item).toBeVisible()
    await expect(item).toContainText('Pause')

    await item.click()
    await page.waitForURL('**/stations/grading/form/e2e-grading-lanjutkan')

    // Navigating back to Monitor confirms the tap was pure navigation — no
    // status-update call happened as a side effect of the tap itself: the
    // record is still present (and still shown as "Pause", i.e. its
    // draft_paused status was not silently flipped to draft_ongoing by
    // some other means).
    await page.goto('/stations/grading/monitor')
    await expect(page.getByTestId('draft-item-e2e-grading-lanjutkan')).toContainText('Pause')
  })

  // Scenario 3: "Load Data"
  test('Monitor Grading — Load Data', async ({ page }) => {
    await page.goto('/stations/grading/monitor')

    await page.getByTestId('load-data-button').click()
    await page.waitForURL(/\/stations\/grading\/preview/)
  })

  // Scenario 4: "Tap Breadcrumb"
  test('Monitor Grading — Tap Breadcrumb', async ({ page }) => {
    await page.goto('/stations/grading/monitor')

    await page.getByTestId('breadcrumb-production-process-activity').click()
    await page.waitForURL('**/stations')
  })

  // Scenario 5: "Buka Menu Hamburger"
  test('Monitor Grading — Buka Menu Hamburger', async ({ page }) => {
    await page.goto('/stations/grading/monitor')

    await expect(page.getByTestId('nav-menu')).toBeHidden()

    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu-change-password')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })

  // Scenario 6: "Belum Ada Draft"
  test('Monitor Grading — Belum Ada Draft', async ({ page }) => {
    await page.goto('/stations/grading/monitor')

    await expect(page.getByTestId('draft-list-empty')).toContainText('Belum ada draft grading tersimpan.')
    await expect(page.getByTestId('new-data-button')).toBeEnabled()
  })

  // Scenario 7: "Belum Ada Data Hari Ini" / "Counter Menampilkan Data"
  test('Monitor Grading — Counter Hari Ini Menampilkan Data', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = new Date()
    const yesterday = new Date(today.getTime() - 24 * 60 * 60 * 1000)

    // Two today-dated rows, different statuses, both counted (no status
    // filter) — countGrading=2, sumNetto=10000+8000=18000,
    // sumQuantity=1+2=3.
    await seedGradingRecordForCounter(page, userId, {
      id: 'e2e-grading-counter-today-1',
      status: 'saved',
      date: toLocalDateString(today),
      netto: 10000,
      quantity: 1,
    })
    await seedGradingRecordForCounter(page, userId, {
      id: 'e2e-grading-counter-today-2',
      status: 'draft_ongoing',
      date: toLocalDateString(today),
      netto: 8000,
      quantity: 2,
    })
    // A non-today row — must NOT be counted.
    await seedGradingRecordForCounter(page, userId, {
      id: 'e2e-grading-counter-yesterday',
      status: 'saved',
      date: toLocalDateString(yesterday),
      netto: 99999,
      quantity: 99,
    })

    await page.goto('/stations/grading/monitor')

    await expect(page.getByTestId('counter-count-grading')).toHaveText('2')
    await expect(page.getByTestId('counter-netto')).toHaveText('18000')
    await expect(page.getByTestId('counter-quantity')).toHaveText('3')
  })

  test('Monitor Grading — Belum Ada Data Hari Ini', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000)

    // Only a non-today row exists — the counter must still show all zero.
    await seedGradingRecordForCounter(page, userId, {
      id: 'e2e-grading-counter-none-today',
      status: 'saved',
      date: toLocalDateString(yesterday),
      netto: 5000,
      quantity: 1,
    })

    await page.goto('/stations/grading/monitor')

    await expect(page.getByTestId('counter-count-grading')).toHaveText('0')
    await expect(page.getByTestId('counter-netto')).toHaveText('0')
    await expect(page.getByTestId('counter-quantity')).toHaveText('0')
  })
})
