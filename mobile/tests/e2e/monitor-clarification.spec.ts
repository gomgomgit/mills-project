import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-069--monitor-clarification / usecase-109--monitor-clarification
//
// Mirrors monitor-effluent-plant.spec.ts's structure (itself mirroring
// monitor-threshing.spec.ts) — a scrollable list of the
// current user's ongoing/paused local drafts (every row labeled uniformly
// "Pause"), a "Hari Ini" 2-card counter row, plus 'New Data' / 'Load Data'
// / 'Back' actions and the same header/breadcrumb/hamburger nav-menu as
// every other mobile station screen.
//
// Capacitor mobile screens ARE browser-testable via the Vite dev server —
// this suite runs directly against a real browser page.
//
// Seeds `clarification_record` (and, for the counter, `clarification_detail`)
// rows directly via the dev-only `window.__mslTestDb` bridge (same pattern
// as monitor-effluent-plant.spec.ts) — there is no in-app flow that produces
// more than one draft at a time through the real UI.
async function seedClarificationDraft(
  page: Page,
  userId: string,
  overrides: {
    id: string
    status?: 'draft_ongoing' | 'draft_paused'
    clarificationId?: string | null
    updatedAt?: string
  },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, status, clarificationId, updatedAt }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = updatedAt ?? new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO clarification_record (id, status, clarification_id, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'draft_ongoing', clarificationId ?? null, userId, now, now],
      )
    },
    { userId, id: overrides.id, status: overrides.status, clarificationId: overrides.clarificationId, updatedAt: overrides.updatedAt },
  )
}

/**
 * Seeds a `clarification_record` header row (status/date) plus a handful
 * of `clarification_detail` rows for it — for the "Hari Ini" counter
 * (clarificationRecordRepo.getTodaySummary(), a plain COUNT of rows, not
 * filtered by "filled").
 */
async function seedClarificationRecordForCounter(
  page: Page,
  userId: string,
  overrides: {
    id: string
    status?: 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'
    date: string
    hours: number[]
  },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, status, date, hours }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO clarification_record (id, status, date, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'saved', date, userId, now, now],
      )

      for (const [i, hour] of hours.entries()) {
        const timeSlot = `${String(hour).padStart(2, '0')}:00`
        const detailId = `${id}-detail-${i}`
        await db.run(
          `INSERT OR REPLACE INTO clarification_detail (id, clarification_record_id, time_slot, clarification_tank_temp_c, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?, ?)`,
          [detailId, id, timeSlot, 10 + hour, now, now],
        )
      }
    },
    { userId, id: overrides.id, status: overrides.status, date: overrides.date, hours: overrides.hours },
  )
}

/**
 * Reaches Monitor Clarification the way a user does — Home > Production Process
 * Activity > (Production Line picker, the demo mill has 3 lines) > tile.
 * Same picker handling as station-list.spec.ts's beforeEach.
 */
async function openFromStationList(page: Page): Promise<void> {
  await page.getByTestId('menu-card-production-process-activity').click()
  await page.waitForURL('**/stations')

  const picker = page.getByTestId('production-line-picker')
  const firstTile = page.locator('[data-testid^="station-tile-"]').first()
  await expect(picker.or(firstTile).first()).toBeVisible({ timeout: 15_000 })
  if (await picker.isVisible()) {
    await page.locator('[data-testid^="production-line-option-"]').first().click()
  }

  await page.getByText('Clarification', { exact: true }).click()
  await page.waitForURL('**/stations/clarification/monitor')
}

function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

test.describe('Monitor Clarification (screen-069)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('Monitor Clarification — success (New Data creates a fresh draft with zero detail rows)', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedClarificationDraft(page, userId, { id: 'e2e-clarification-existing', clarificationId: 'CLR-EXISTING' })

    await openFromStationList(page)
    await expect(page.getByTestId('draft-item-e2e-clarification-existing')).toBeVisible()

    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/clarification\/form\/[^/]+$/)
    await expect(page).not.toHaveURL(/\/stations\/clarification\/form\/e2e-clarification-existing$/)

    // Rows are not pre-created — a fresh draft starts with zero detail rows.
    await expect(page.getByTestId('clarification-detail-row')).toHaveCount(0)
  })

  test('Monitor Clarification — Lanjutkan Draft/Pause', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedClarificationDraft(page, userId, {
      id: 'e2e-clarification-lanjutkan',
      status: 'draft_paused',
      clarificationId: 'CLR-LANJUTKAN',
    })

    await page.goto('/stations/clarification/monitor')

    const item = page.getByTestId('draft-item-e2e-clarification-lanjutkan')
    await expect(item).toBeVisible()
    await expect(item).toContainText('Pause')

    await item.click()
    await page.waitForURL('**/stations/clarification/form/e2e-clarification-lanjutkan')
    // Pre-filled with the selected draft's data.
    await expect(page.locator('#field-clarification-id')).toHaveValue('CLR-LANJUTKAN')

    await page.goto('/stations/clarification/monitor')
    await expect(page.getByTestId('draft-item-e2e-clarification-lanjutkan')).toContainText('Pause')
  })

  test('Monitor Clarification — Load Data', async ({ page }) => {
    await page.goto('/stations/clarification/monitor')

    await page.getByTestId('load-data-button').click()
    await page.waitForURL(/\/stations\/clarification\/preview$/)

    // List mode, with the date + search filters visible.
    await expect(page.getByTestId('date-filter-input')).toBeVisible()
    await expect(page.getByTestId('search-filter-input')).toBeVisible()
  })

  test('Monitor Clarification — Tap Breadcrumb', async ({ page }) => {
    await page.goto('/stations/clarification/monitor')

    await page.getByTestId('breadcrumb-production-process-activity').click()
    await page.waitForURL('**/stations')
  })

  test('Monitor Clarification — Buka Menu Hamburger', async ({ page }) => {
    await page.goto('/stations/clarification/monitor')

    await expect(page.getByTestId('nav-menu')).toBeHidden()

    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu-change-password')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })

  test('Monitor Clarification — Belum Ada Draft', async ({ page }) => {
    await page.goto('/stations/clarification/monitor')

    await expect(page.getByTestId('draft-list-empty')).toContainText('Belum ada draft clarification tersimpan.')
    await expect(page.getByTestId('new-data-button')).toBeEnabled()
  })

  test('Monitor Clarification — Counter Hari Ini Menampilkan Data', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = new Date()
    const yesterday = new Date(today.getTime() - 24 * 60 * 60 * 1000)

    // Two today-dated records, both counted regardless of status. The
    // counter is a plain COUNT of rows added (no "filled" filter) —
    // record 1 has 2 rows, record 2 has 1 row, total detailRowCount = 3.
    await seedClarificationRecordForCounter(page, userId, {
      id: 'e2e-clarification-counter-today-1',
      status: 'saved',
      date: toLocalDateString(today),
      hours: [7, 9],
    })
    await seedClarificationRecordForCounter(page, userId, {
      id: 'e2e-clarification-counter-today-2',
      status: 'draft_ongoing',
      date: toLocalDateString(today),
      hours: [10],
    })
    // A non-today row — must NOT be counted at all.
    await seedClarificationRecordForCounter(page, userId, {
      id: 'e2e-clarification-counter-yesterday',
      status: 'saved',
      date: toLocalDateString(yesterday),
      hours: [7, 8, 9, 10, 11],
    })

    await page.goto('/stations/clarification/monitor')

    await expect(page.getByTestId('counter-count-clarification-record')).toHaveText('2')
    await expect(page.getByTestId('counter-detail-row-count')).toHaveText('3')
  })

  test('Monitor Clarification — Belum Ada Data Hari Ini', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000)

    await seedClarificationRecordForCounter(page, userId, {
      id: 'e2e-clarification-counter-none-today',
      status: 'saved',
      date: toLocalDateString(yesterday),
      hours: [7],
    })

    await page.goto('/stations/clarification/monitor')

    await expect(page.getByTestId('counter-count-clarification-record')).toHaveText('0')
    await expect(page.getByTestId('counter-detail-row-count')).toHaveText('0')
  })
})
