import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-009--monitor-cages-track / usecase-009--monitor-cages-track
//
// Rewritten (2026-08-19, entity-catalog v3) to match MonitorCagesTrackView.vue's
// list-view rewrite — the old single-draft summary + Mulai Input Baru/
// Lanjutkan/Pause/Clear flow is gone (dropped entirely, along with its e2e
// coverage below), mirroring monitor-grading.spec.ts's (screen-008) own
// equivalent rewrite. The screen now renders a scrollable list of the
// current user's ongoing/paused local drafts (every row labeled uniformly
// "Pause"), plus 'New Data' / 'Load Data' / 'Back' actions and the same
// header/breadcrumb/hamburger nav-menu as MonitorGradingView.vue /
// MonitorWeighbridgeView.vue / HomeView.vue / StationListView.vue.
//
// Capacitor mobile screens ARE browser-testable via the Vite dev server —
// this suite runs directly against a real browser page, same as every
// other mobile screen's e2e suite in this project.
//
// Seeds `cages_track_record` (and, for the counter, `cages_tipped_time`)
// rows directly via the dev-only `window.__mslTestDb` bridge (same pattern
// as helpers.ts's seedPausedDrafts() / monitor-grading.spec.ts's
// seedGradingDraft()) — there is no in-app flow that produces more than one
// draft at a time through the real UI, so direct seeding is the only
// practical way to exercise "render with an existing list" scenarios.
async function seedCagesTrackDraft(
  page: Page,
  userId: string,
  overrides: {
    id: string
    status?: 'draft_ongoing' | 'draft_paused'
    cagesTrackNumber?: string | null
    updatedAt?: string
  },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, status, cagesTrackNumber, updatedAt }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = updatedAt ?? new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO cages_track_record (id, status, cages_track_number, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'draft_ongoing', cagesTrackNumber ?? null, userId, now, now],
      )
    },
    {
      userId,
      id: overrides.id,
      status: overrides.status,
      cagesTrackNumber: overrides.cagesTrackNumber,
      updatedAt: overrides.updatedAt,
    },
  )
}

/**
 * "today's counter" addition — seeds a `cages_track_record` header row
 * (status/date) plus zero or more `cages_tipped_time` child rows (each
 * carrying a `total_cages` value), for the new "Hari Ini" section
 * (cagesTrackRecordRepo.getTodaySummary()). Separate from
 * seedCagesTrackDraft() above (which only sets the columns the draft/pause
 * LIST needs) — the counter needs `date` on the header plus the CHILD
 * table's `total_cages` sum, mirroring monitor-grading.spec.ts's
 * seedGradingRecordForCounter() helper, adapted for cages-track's
 * two-table "sum lives on the child" shape (see cagesTrackRecordRepo.ts's
 * getTodaySummary() doc comment).
 */
async function seedCagesTrackRecordForCounter(
  page: Page,
  userId: string,
  overrides: {
    id: string
    status?: 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'
    date: string
    tippedTimeTotalCages: number[]
  },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, status, date, tippedTimeTotalCages }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO cages_track_record (id, status, date, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'saved', date, userId, now, now],
      )

      for (let i = 0; i < tippedTimeTotalCages.length; i += 1) {
        const tippedId = `${id}-tipped-${i}`
        await db.run(
          `INSERT OR REPLACE INTO cages_tipped_time (id, cages_track_record_id, tipped_hour, total_cages, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?, ?)`,
          [tippedId, id, i, tippedTimeTotalCages[i], now, now],
        )
      }
    },
    { userId, id: overrides.id, status: overrides.status, date: overrides.date, tippedTimeTotalCages: overrides.tippedTimeTotalCages },
  )
}

/**
 * Formats a local (device-timezone) date string ("YYYY-MM-DD") — required
 * here (rather than `Date#toISOString()`, which is UTC) because
 * `getTodaySummary()`'s SQL filters via `date('now', 'localtime')`, i.e.
 * the LOCAL calendar day. Mirrors monitor-grading.spec.ts's
 * toLocalDateString(), since `cages_track_record`'s `date` column (like
 * grading_record's `date`) carries no time component.
 */
function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

test.describe('Monitor Cages Track (screen-009)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  // Scenario 1: "success"
  test('Monitor Cages Track — success', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedCagesTrackDraft(page, userId, { id: 'e2e-cages-track-existing', cagesTrackNumber: 'CT-EXISTING' })

    await page.goto('/stations/cages-track/monitor')
    await expect(page.getByTestId('draft-item-e2e-cages-track-existing')).toBeVisible()

    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/[^/]+$/)
    await expect(page).not.toHaveURL('**/stations/cages-track/form/e2e-cages-track-existing')
  })

  // Scenario 2: "Lanjutkan Draft/Pause"
  test('Monitor Cages Track — Lanjutkan Draft/Pause', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedCagesTrackDraft(page, userId, {
      id: 'e2e-cages-track-lanjutkan',
      status: 'draft_paused',
      cagesTrackNumber: 'CT-LANJUTKAN',
    })

    await page.goto('/stations/cages-track/monitor')

    const item = page.getByTestId('draft-item-e2e-cages-track-lanjutkan')
    await expect(item).toBeVisible()
    await expect(item).toContainText('Pause')

    await item.click()
    await page.waitForURL('**/stations/cages-track/form/e2e-cages-track-lanjutkan')

    // Navigating back to Monitor confirms the tap was pure navigation — no
    // status-update call happened as a side effect of the tap itself: the
    // record is still present (and still shown as "Pause", i.e. its
    // draft_paused status was not silently flipped to draft_ongoing by
    // some other means).
    await page.goto('/stations/cages-track/monitor')
    await expect(page.getByTestId('draft-item-e2e-cages-track-lanjutkan')).toContainText('Pause')
  })

  // Scenario 3: "Load Data"
  test('Monitor Cages Track — Load Data', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')

    await page.getByTestId('load-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/preview/)
  })

  // Scenario 4: "Tap Breadcrumb"
  test('Monitor Cages Track — Tap Breadcrumb', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')

    await page.getByTestId('breadcrumb-production-process-activity').click()
    await page.waitForURL('**/stations')
  })

  // Scenario 5: "Buka Menu Hamburger"
  test('Monitor Cages Track — Buka Menu Hamburger', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')

    await expect(page.getByTestId('nav-menu')).toBeHidden()

    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu-change-password')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })

  // Scenario 6: "Belum Ada Draft"
  test('Monitor Cages Track — Belum Ada Draft', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')

    await expect(page.getByTestId('draft-list-empty')).toContainText('Belum ada draft cages track tersimpan.')
    await expect(page.getByTestId('new-data-button')).toBeEnabled()
  })

  // Scenario 7: "Belum Ada Data Hari Ini" / "Counter Menampilkan Data"
  test('Monitor Cages Track — Counter Hari Ini Menampilkan Data', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = new Date()
    const yesterday = new Date(today.getTime() - 24 * 60 * 60 * 1000)

    // Two today-dated header rows, different statuses, both counted (no
    // status filter) — countCagesTrack=2. Each has its own
    // cages_tipped_time child rows, summed across both records:
    // sumTotalCages = (3+2) + (5) = 10.
    await seedCagesTrackRecordForCounter(page, userId, {
      id: 'e2e-cages-track-counter-today-1',
      status: 'saved',
      date: toLocalDateString(today),
      tippedTimeTotalCages: [3, 2],
    })
    await seedCagesTrackRecordForCounter(page, userId, {
      id: 'e2e-cages-track-counter-today-2',
      status: 'draft_ongoing',
      date: toLocalDateString(today),
      tippedTimeTotalCages: [5],
    })
    // A non-today row — must NOT be counted, including its own
    // cages_tipped_time rows.
    await seedCagesTrackRecordForCounter(page, userId, {
      id: 'e2e-cages-track-counter-yesterday',
      status: 'saved',
      date: toLocalDateString(yesterday),
      tippedTimeTotalCages: [99],
    })

    await page.goto('/stations/cages-track/monitor')

    await expect(page.getByTestId('counter-count-cages-track')).toHaveText('2')
    await expect(page.getByTestId('counter-total-cages')).toHaveText('10')
  })

  test('Monitor Cages Track — Belum Ada Data Hari Ini', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000)

    // Only a non-today row exists — the counter must still show all zero,
    // and must not sum that row's cages_tipped_time rows either.
    await seedCagesTrackRecordForCounter(page, userId, {
      id: 'e2e-cages-track-counter-none-today',
      status: 'saved',
      date: toLocalDateString(yesterday),
      tippedTimeTotalCages: [7],
    })

    await page.goto('/stations/cages-track/monitor')

    await expect(page.getByTestId('counter-count-cages-track')).toHaveText('0')
    await expect(page.getByTestId('counter-total-cages')).toHaveText('0')
  })
})
