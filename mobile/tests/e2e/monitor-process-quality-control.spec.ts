import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-070--monitor-process-quality-control / usecase-115--monitor-process-quality-control
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
// Seeds `process_quality_control_record` (and, for the counter, `process_quality_control_detail`)
// rows directly via the dev-only `window.__mslTestDb` bridge (same pattern
// as monitor-effluent-plant.spec.ts) — there is no in-app flow that produces
// more than one draft at a time through the real UI.
async function seedProcessQualityControlDraft(
  page: Page,
  userId: string,
  overrides: {
    id: string
    status?: 'draft_ongoing' | 'draft_paused'
    processQcId?: string | null
    updatedAt?: string
  },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, status, processQcId, updatedAt }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = updatedAt ?? new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO process_quality_control_record (id, status, process_qc_id, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'draft_ongoing', processQcId ?? null, userId, now, now],
      )
    },
    { userId, id: overrides.id, status: overrides.status, processQcId: overrides.processQcId, updatedAt: overrides.updatedAt },
  )
}

/**
 * Seeds a `process_quality_control_record` header row (status/date) plus a handful
 * of `process_quality_control_detail` rows for it — for the "Hari Ini" counter
 * (processQualityControlRecordRepo.getTodaySummary(), a plain COUNT of rows, not
 * filtered by "filled").
 */
async function seedProcessQualityControlRecordForCounter(
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
        `INSERT OR REPLACE INTO process_quality_control_record (id, status, date, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'saved', date, userId, now, now],
      )

      for (const [i, hour] of hours.entries()) {
        const timeSlot = `${String(hour).padStart(2, '0')}:00`
        const detailId = `${id}-detail-${i}`
        await db.run(
          `INSERT OR REPLACE INTO process_quality_control_detail (id, process_quality_control_record_id, time_slot, fruit_press_oil_loss_in_sludge_percent, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?, ?)`,
          [detailId, id, timeSlot, 10 + hour, now, now],
        )
      }
    },
    { userId, id: overrides.id, status: overrides.status, date: overrides.date, hours: overrides.hours },
  )
}

/**
 * Reaches Monitor Process Quality Control the way a user does — Home > Production Process
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

  await page.getByText('Process Quality Control', { exact: true }).click()
  await page.waitForURL('**/stations/process-quality-control/monitor')
}

function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

test.describe('Monitor Process Quality Control (screen-070)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('Monitor Process Quality Control — success (New Data creates a fresh draft with zero detail rows)', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedProcessQualityControlDraft(page, userId, { id: 'e2e-process-quality-control-existing', processQcId: 'PQC-EXISTING' })

    await openFromStationList(page)
    await expect(page.getByTestId('draft-item-e2e-process-quality-control-existing')).toBeVisible()

    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/process-quality-control\/form\/[^/]+$/)
    await expect(page).not.toHaveURL(/\/stations\/process-quality-control\/form\/e2e-process-quality-control-existing$/)

    // Rows are not pre-created — a fresh draft starts with zero detail rows.
    await expect(page.getByTestId('process-quality-control-detail-row')).toHaveCount(0)
  })

  test('Monitor Process Quality Control — Lanjutkan Draft/Pause', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedProcessQualityControlDraft(page, userId, {
      id: 'e2e-process-quality-control-lanjutkan',
      status: 'draft_paused',
      processQcId: 'PQC-LANJUTKAN',
    })

    await page.goto('/stations/process-quality-control/monitor')

    const item = page.getByTestId('draft-item-e2e-process-quality-control-lanjutkan')
    await expect(item).toBeVisible()
    await expect(item).toContainText('Pause')

    await item.click()
    await page.waitForURL('**/stations/process-quality-control/form/e2e-process-quality-control-lanjutkan')
    // Pre-filled with the selected draft's data.
    await expect(page.locator('#field-process-qc-id')).toHaveValue('PQC-LANJUTKAN')

    await page.goto('/stations/process-quality-control/monitor')
    await expect(page.getByTestId('draft-item-e2e-process-quality-control-lanjutkan')).toContainText('Pause')
  })

  test('Monitor Process Quality Control — Load Data', async ({ page }) => {
    await page.goto('/stations/process-quality-control/monitor')

    await page.getByTestId('load-data-button').click()
    await page.waitForURL(/\/stations\/process-quality-control\/preview$/)

    // List mode, with the date + search filters visible.
    await expect(page.getByTestId('date-filter-input')).toBeVisible()
    await expect(page.getByTestId('search-filter-input')).toBeVisible()
  })

  test('Monitor Process Quality Control — Tap Breadcrumb', async ({ page }) => {
    await page.goto('/stations/process-quality-control/monitor')

    await page.getByTestId('breadcrumb-production-process-activity').click()
    await page.waitForURL('**/stations')
  })

  test('Monitor Process Quality Control — Buka Menu Hamburger', async ({ page }) => {
    await page.goto('/stations/process-quality-control/monitor')

    await expect(page.getByTestId('nav-menu')).toBeHidden()

    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu-change-password')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })

  test('Monitor Process Quality Control — Belum Ada Draft', async ({ page }) => {
    await page.goto('/stations/process-quality-control/monitor')

    await expect(page.getByTestId('draft-list-empty')).toContainText('Belum ada draft process quality control tersimpan.')
    await expect(page.getByTestId('new-data-button')).toBeEnabled()
  })

  test('Monitor Process Quality Control — Counter Hari Ini Menampilkan Data', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = new Date()
    const yesterday = new Date(today.getTime() - 24 * 60 * 60 * 1000)

    // Two today-dated records, both counted regardless of status. The
    // counter is a plain COUNT of rows added (no "filled" filter) —
    // record 1 has 2 rows, record 2 has 1 row, total detailRowCount = 3.
    await seedProcessQualityControlRecordForCounter(page, userId, {
      id: 'e2e-process-quality-control-counter-today-1',
      status: 'saved',
      date: toLocalDateString(today),
      hours: [7, 9],
    })
    await seedProcessQualityControlRecordForCounter(page, userId, {
      id: 'e2e-process-quality-control-counter-today-2',
      status: 'draft_ongoing',
      date: toLocalDateString(today),
      hours: [10],
    })
    // A non-today row — must NOT be counted at all.
    await seedProcessQualityControlRecordForCounter(page, userId, {
      id: 'e2e-process-quality-control-counter-yesterday',
      status: 'saved',
      date: toLocalDateString(yesterday),
      hours: [7, 8, 9, 10, 11],
    })

    await page.goto('/stations/process-quality-control/monitor')

    await expect(page.getByTestId('counter-count-process-quality-control-record')).toHaveText('2')
    await expect(page.getByTestId('counter-detail-row-count')).toHaveText('3')
  })

  test('Monitor Process Quality Control — Belum Ada Data Hari Ini', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000)

    await seedProcessQualityControlRecordForCounter(page, userId, {
      id: 'e2e-process-quality-control-counter-none-today',
      status: 'saved',
      date: toLocalDateString(yesterday),
      hours: [7],
    })

    await page.goto('/stations/process-quality-control/monitor')

    await expect(page.getByTestId('counter-count-process-quality-control-record')).toHaveText('0')
    await expect(page.getByTestId('counter-detail-row-count')).toHaveText('0')
  })
})
