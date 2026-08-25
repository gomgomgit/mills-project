import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-040--monitor-kernel-plant / usecase-040--monitor-kernel-plant
//
// Mirrors monitor-depricarping.spec.ts's structure — a scrollable list of
// the current user's ongoing/paused local drafts (every row labeled
// uniformly "Pause"), a "Hari Ini" 2-card counter row, plus 'New Data' /
// 'Load Data' / 'Back' actions and the same header/breadcrumb/hamburger
// nav-menu as every other mobile station screen.
//
// Capacitor mobile screens ARE browser-testable via the Vite dev server —
// this suite runs directly against a real browser page.
//
// Seeds `kernel_plant_record` (and, for the counter, `kernel_plant_detail`)
// rows directly via the dev-only `window.__mslTestDb` bridge (same pattern
// as monitor-depricarping.spec.ts) — there is no in-app flow that produces
// more than one draft at a time through the real UI.
async function seedKernelPlantDraft(
  page: Page,
  userId: string,
  overrides: {
    id: string
    status?: 'draft_ongoing' | 'draft_paused'
    kernelPlantId?: string | null
    updatedAt?: string
  },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, status, kernelPlantId, updatedAt }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = updatedAt ?? new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO kernel_plant_record (id, status, kernel_plant_id, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'draft_ongoing', kernelPlantId ?? null, userId, now, now],
      )
    },
    { userId, id: overrides.id, status: overrides.status, kernelPlantId: overrides.kernelPlantId, updatedAt: overrides.updatedAt },
  )
}

/**
 * Seeds a `kernel_plant_record` header row (status/date) plus a handful of
 * `kernel_plant_detail` rows for it (REVISED 2026-08-24, entity-catalog v12:
 * rows are no longer pre-created 24-at-a-time — this seeds only
 * `hours.length` rows, exactly as if the user had added that many via
 * "Tambah baris") — for the "Hari Ini" counter
 * (kernelPlantRecordRepo.getTodaySummary(), now a plain COUNT of rows, not
 * filtered by "filled").
 */
async function seedKernelPlantRecordForCounter(
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
        `INSERT OR REPLACE INTO kernel_plant_record (id, status, date, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'saved', date, userId, now, now],
      )

      for (const [i, hour] of hours.entries()) {
        const timeSlot = `${String(hour).padStart(2, '0')}:00`
        const detailId = `${id}-detail-${i}`
        await db.run(
          `INSERT OR REPLACE INTO kernel_plant_detail (id, kernel_plant_record_id, time_slot, ripple_mill_1_amps, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?, ?)`,
          [detailId, id, timeSlot, 20 + hour, now, now],
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

test.describe('Monitor Kernel Plant (screen-040)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('Monitor Kernel Plant — success (New Data creates a fresh draft with zero detail rows)', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedKernelPlantDraft(page, userId, { id: 'e2e-kernel-plant-existing', kernelPlantId: 'KP-EXISTING' })

    await page.goto('/stations/kernel-plant/monitor')
    await expect(page.getByTestId('draft-item-e2e-kernel-plant-existing')).toBeVisible()

    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/kernel-plant\/form\/[^/]+$/)
    await expect(page).not.toHaveURL('**/stations/kernel-plant/form/e2e-kernel-plant-existing')

    // REVISED 2026-08-24 (entity-catalog v12): rows are no longer
    // pre-created — a fresh draft starts with zero detail rows.
    await expect(page.getByTestId('kernel-plant-detail-row')).toHaveCount(0)
  })

  test('Monitor Kernel Plant — Lanjutkan Draft/Pause', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedKernelPlantDraft(page, userId, {
      id: 'e2e-kernel-plant-lanjutkan',
      status: 'draft_paused',
      kernelPlantId: 'KP-LANJUTKAN',
    })

    await page.goto('/stations/kernel-plant/monitor')

    const item = page.getByTestId('draft-item-e2e-kernel-plant-lanjutkan')
    await expect(item).toBeVisible()
    await expect(item).toContainText('Pause')

    await item.click()
    await page.waitForURL('**/stations/kernel-plant/form/e2e-kernel-plant-lanjutkan')

    await page.goto('/stations/kernel-plant/monitor')
    await expect(page.getByTestId('draft-item-e2e-kernel-plant-lanjutkan')).toContainText('Pause')
  })

  test('Monitor Kernel Plant — Load Data', async ({ page }) => {
    await page.goto('/stations/kernel-plant/monitor')

    await page.getByTestId('load-data-button').click()
    await page.waitForURL(/\/stations\/kernel-plant\/preview/)
  })

  test('Monitor Kernel Plant — Tap Breadcrumb', async ({ page }) => {
    await page.goto('/stations/kernel-plant/monitor')

    await page.getByTestId('breadcrumb-production-process-activity').click()
    await page.waitForURL('**/stations')
  })

  test('Monitor Kernel Plant — Buka Menu Hamburger', async ({ page }) => {
    await page.goto('/stations/kernel-plant/monitor')

    await expect(page.getByTestId('nav-menu')).toBeHidden()

    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu-change-password')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })

  test('Monitor Kernel Plant — Belum Ada Draft', async ({ page }) => {
    await page.goto('/stations/kernel-plant/monitor')

    await expect(page.getByTestId('draft-list-empty')).toContainText('Belum ada draft kernel plant tersimpan.')
    await expect(page.getByTestId('new-data-button')).toBeEnabled()
  })

  test('Monitor Kernel Plant — Counter Hari Ini Menampilkan Data', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = new Date()
    const yesterday = new Date(today.getTime() - 24 * 60 * 60 * 1000)

    // Two today-dated records, both counted regardless of status. REVISED
    // 2026-08-24: the counter is now a plain COUNT of rows added (no
    // "filled" filter) — record 1 has 2 rows, record 2 has 1 row, total
    // detailRowCount = 3.
    await seedKernelPlantRecordForCounter(page, userId, {
      id: 'e2e-kernel-plant-counter-today-1',
      status: 'saved',
      date: toLocalDateString(today),
      hours: [7, 9],
    })
    await seedKernelPlantRecordForCounter(page, userId, {
      id: 'e2e-kernel-plant-counter-today-2',
      status: 'draft_ongoing',
      date: toLocalDateString(today),
      hours: [10],
    })
    // A non-today row — must NOT be counted at all.
    await seedKernelPlantRecordForCounter(page, userId, {
      id: 'e2e-kernel-plant-counter-yesterday',
      status: 'saved',
      date: toLocalDateString(yesterday),
      hours: [7, 8, 9, 10, 11],
    })

    await page.goto('/stations/kernel-plant/monitor')

    await expect(page.getByTestId('counter-count-kernel-plant-record')).toHaveText('2')
    await expect(page.getByTestId('counter-detail-row-count')).toHaveText('3')
  })

  test('Monitor Kernel Plant — Belum Ada Data Hari Ini', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000)

    await seedKernelPlantRecordForCounter(page, userId, {
      id: 'e2e-kernel-plant-counter-none-today',
      status: 'saved',
      date: toLocalDateString(yesterday),
      hours: [7],
    })

    await page.goto('/stations/kernel-plant/monitor')

    await expect(page.getByTestId('counter-count-kernel-plant-record')).toHaveText('0')
    await expect(page.getByTestId('counter-detail-row-count')).toHaveText('0')
  })
})
