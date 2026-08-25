import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-039--monitor-depricarping / usecase-039--monitor-depricarping
//
// Mirrors monitor-pressing.spec.ts's structure — a scrollable list of the
// current user's ongoing/paused local drafts (every row labeled uniformly
// "Pause"), a "Hari Ini" 2-card counter row, plus 'New Data' / 'Load Data' /
// 'Back' actions and the same header/breadcrumb/hamburger nav-menu as every
// other mobile station screen.
//
// Capacitor mobile screens ARE browser-testable via the Vite dev server —
// this suite runs directly against a real browser page.
//
// Seeds `depricarping_record` (and, for the counter, `depricarping_detail`)
// rows directly via the dev-only `window.__mslTestDb` bridge (same pattern
// as monitor-pressing.spec.ts) — there is no in-app flow that produces more
// than one draft at a time through the real UI.
async function seedDepricarpingDraft(
  page: Page,
  userId: string,
  overrides: {
    id: string
    status?: 'draft_ongoing' | 'draft_paused'
    presserId?: string | null
    updatedAt?: string
  },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, status, presserId, updatedAt }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = updatedAt ?? new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO depricarping_record (id, status, presser_id, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'draft_ongoing', presserId ?? null, userId, now, now],
      )
    },
    { userId, id: overrides.id, status: overrides.status, presserId: overrides.presserId, updatedAt: overrides.updatedAt },
  )
}

/**
 * Seeds a `depricarping_record` header row (status/date) plus a handful of
 * `depricarping_detail` rows for it (REVISED 2026-08-24, entity-catalog v12:
 * rows are no longer pre-created 24-at-a-time — this seeds only
 * `hours.length` rows, exactly as if the user had added that many via
 * "Tambah baris") — for the "Hari Ini" counter
 * (depricarpingRecordRepo.getTodaySummary(), now a plain COUNT of rows, not
 * filtered by "filled").
 */
async function seedDepricarpingRecordForCounter(
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
        `INSERT OR REPLACE INTO depricarping_record (id, status, date, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'saved', date, userId, now, now],
      )

      for (const [i, hour] of hours.entries()) {
        const timeSlot = `${String(hour).padStart(2, '0')}:00`
        const detailId = `${id}-detail-${i}`
        await db.run(
          `INSERT OR REPLACE INTO depricarping_detail (id, depricarping_record_id, time_slot, fan_static_pressure_mmh2o, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?, ?)`,
          [detailId, id, timeSlot, 40 + hour, now, now],
        )
      }
    },
    { userId, id: overrides.id, status: overrides.status, date: overrides.date, hours: overrides.hours },
  )
}

function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

test.describe('Monitor Depricarping (screen-039)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('Monitor Depricarping — success (New Data creates a fresh draft with zero detail rows)', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedDepricarpingDraft(page, userId, { id: 'e2e-depricarping-existing', presserId: 'DP-EXISTING' })

    await page.goto('/stations/depricarping/monitor')
    await expect(page.getByTestId('draft-item-e2e-depricarping-existing')).toBeVisible()

    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/depricarping\/form\/[^/]+$/)
    await expect(page).not.toHaveURL('**/stations/depricarping/form/e2e-depricarping-existing')

    // REVISED 2026-08-24 (entity-catalog v12): rows are no longer
    // pre-created — a fresh draft starts with zero detail rows.
    await expect(page.getByTestId('depricarping-detail-row')).toHaveCount(0)
  })

  test('Monitor Depricarping — Lanjutkan Draft/Pause', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedDepricarpingDraft(page, userId, {
      id: 'e2e-depricarping-lanjutkan',
      status: 'draft_paused',
      presserId: 'DP-LANJUTKAN',
    })

    await page.goto('/stations/depricarping/monitor')

    const item = page.getByTestId('draft-item-e2e-depricarping-lanjutkan')
    await expect(item).toBeVisible()
    await expect(item).toContainText('Pause')

    await item.click()
    await page.waitForURL('**/stations/depricarping/form/e2e-depricarping-lanjutkan')

    await page.goto('/stations/depricarping/monitor')
    await expect(page.getByTestId('draft-item-e2e-depricarping-lanjutkan')).toContainText('Pause')
  })

  test('Monitor Depricarping — Load Data', async ({ page }) => {
    await page.goto('/stations/depricarping/monitor')

    await page.getByTestId('load-data-button').click()
    await page.waitForURL(/\/stations\/depricarping\/preview/)
  })

  test('Monitor Depricarping — Tap Breadcrumb', async ({ page }) => {
    await page.goto('/stations/depricarping/monitor')

    await page.getByTestId('breadcrumb-production-process-activity').click()
    await page.waitForURL('**/stations')
  })

  test('Monitor Depricarping — Buka Menu Hamburger', async ({ page }) => {
    await page.goto('/stations/depricarping/monitor')

    await expect(page.getByTestId('nav-menu')).toBeHidden()

    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu-change-password')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })

  test('Monitor Depricarping — Belum Ada Draft', async ({ page }) => {
    await page.goto('/stations/depricarping/monitor')

    await expect(page.getByTestId('draft-list-empty')).toContainText('Belum ada draft depricarping tersimpan.')
    await expect(page.getByTestId('new-data-button')).toBeEnabled()
  })

  test('Monitor Depricarping — Counter Hari Ini Menampilkan Data', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = new Date()
    const yesterday = new Date(today.getTime() - 24 * 60 * 60 * 1000)

    // Two today-dated records, both counted regardless of status. REVISED
    // 2026-08-24: the counter is now a plain COUNT of rows added (no
    // "filled" filter) — record 1 has 2 rows, record 2 has 1 row, total
    // detailRowCount = 3.
    await seedDepricarpingRecordForCounter(page, userId, {
      id: 'e2e-depricarping-counter-today-1',
      status: 'saved',
      date: toLocalDateString(today),
      hours: [7, 9],
    })
    await seedDepricarpingRecordForCounter(page, userId, {
      id: 'e2e-depricarping-counter-today-2',
      status: 'draft_ongoing',
      date: toLocalDateString(today),
      hours: [10],
    })
    // A non-today row — must NOT be counted at all.
    await seedDepricarpingRecordForCounter(page, userId, {
      id: 'e2e-depricarping-counter-yesterday',
      status: 'saved',
      date: toLocalDateString(yesterday),
      hours: [7, 8, 9, 10, 11],
    })

    await page.goto('/stations/depricarping/monitor')

    await expect(page.getByTestId('counter-count-depricarping-record')).toHaveText('2')
    await expect(page.getByTestId('counter-detail-row-count')).toHaveText('3')
  })

  test('Monitor Depricarping — Belum Ada Data Hari Ini', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000)

    await seedDepricarpingRecordForCounter(page, userId, {
      id: 'e2e-depricarping-counter-none-today',
      status: 'saved',
      date: toLocalDateString(yesterday),
      hours: [7],
    })

    await page.goto('/stations/depricarping/monitor')

    await expect(page.getByTestId('counter-count-depricarping-record')).toHaveText('0')
    await expect(page.getByTestId('counter-detail-row-count')).toHaveText('0')
  })
})
