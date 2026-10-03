import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-121--monitor-sterilizer / usecase-121--monitor-sterilizer
//
// Event-log station (one sterilizer_detail row per sterilization cycle,
// unbounded) — Monitor shows a "Hari Ini" 2-card counter (Jumlah Sterilizer
// / Jumlah Siklus Tercatat), a "Siklus Terakhir" card (latest cycle across
// ALL of the user's records, not limited to today), and the list of the
// user's ongoing/paused drafts (uniformly labeled "Pause").
//
// Records/cycles are seeded directly via the dev-only `window.__mslTestDb`
// bridge, same as monitor-effluent-plant.spec.ts. sterilizer_record.date is
// a plain 'YYYY-MM-DD' (not a datetime like the hourly-grid stations).

type Status = 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'

interface CycleSeed {
  id: string
  sterilizerNo?: string | null
  closeDoorTime?: string | null
  openDoorTime?: string | null
  durationMinutes?: number | null
  createdAt?: string
}

async function seedSterilizerRecord(
  page: Page,
  userId: string,
  overrides: {
    id: string
    status?: Status
    sterilizerId?: string | null
    date?: string | null
    cycles?: CycleSeed[]
  },
): Promise<void> {
  await page.evaluate(
    async ({ userId, o }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO sterilizer_record (id, status, sterilizer_id, date, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)`,
        [o.id, o.status ?? 'draft_ongoing', o.sterilizerId ?? null, o.date ?? null, userId, now, now],
      )

      for (const c of o.cycles ?? []) {
        const createdAt = c.createdAt ?? now
        await db.run(
          `INSERT OR REPLACE INTO sterilizer_detail
             (id, sterilizer_record_id, sterilizer_no, close_door_time, open_door_time, duration_minutes, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
          [c.id, o.id, c.sterilizerNo ?? null, c.closeDoorTime ?? null, c.openDoorTime ?? null, c.durationMinutes ?? null, createdAt, createdAt],
        )
      }
    },
    { userId, o: overrides },
  )
}

function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

async function openFromStationList(page: Page): Promise<void> {
  await page.getByTestId('menu-card-production-process-activity').click()
  await page.waitForURL('**/stations')

  const picker = page.getByTestId('production-line-picker')
  const firstTile = page.locator('[data-testid^="station-tile-"]').first()
  await expect(picker.or(firstTile).first()).toBeVisible({ timeout: 15_000 })
  if (await picker.isVisible()) {
    await page.locator('[data-testid^="production-line-option-"]').first().click()
  }

  await page.getByText('Sterilizer', { exact: true }).click()
  await page.waitForURL('**/stations/sterilizer/monitor')
}

test.describe('Monitor Sterilizer (screen-121)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('Monitor Sterilizer — menampilkan ringkasan dan list draft', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    const lastWeek = toLocalDateString(new Date(Date.now() - 7 * 24 * 60 * 60 * 1000))

    // Today: 2 records (any status) with 2 + 1 cycles = 3.
    await seedSterilizerRecord(page, userId, {
      id: 'e2e-ster-today-saved',
      status: 'saved',
      sterilizerId: 'STR-SAVED',
      date: today,
      cycles: [
        { id: 'e2e-ster-c1', sterilizerNo: '1', closeDoorTime: '07:00', openDoorTime: '08:10', durationMinutes: 70, createdAt: '2026-01-01T07:00:00.000Z' },
        { id: 'e2e-ster-c2', sterilizerNo: '2', closeDoorTime: '08:30', openDoorTime: '09:40', durationMinutes: 70, createdAt: '2026-01-01T08:00:00.000Z' },
      ],
    })
    await seedSterilizerRecord(page, userId, {
      id: 'e2e-ster-today-draft',
      status: 'draft_paused',
      sterilizerId: 'STR-DRAFT',
      date: today,
      cycles: [{ id: 'e2e-ster-c3', sterilizerNo: '3', closeDoorTime: '10:00', createdAt: '2026-01-01T09:00:00.000Z' }],
    })
    // Older record — not counted, but holds the most recently LOGGED cycle,
    // so it drives "Siklus Terakhir" (not limited to today).
    await seedSterilizerRecord(page, userId, {
      id: 'e2e-ster-old',
      status: 'saved',
      sterilizerId: 'STR-OLD',
      date: lastWeek,
      cycles: [{ id: 'e2e-ster-c-old', sterilizerNo: '4', closeDoorTime: '13:15', openDoorTime: '14:30', durationMinutes: 75, createdAt: '2026-01-02T00:00:00.000Z' }],
    })

    await openFromStationList(page)

    await expect(page.getByTestId('counter-count-records')).toHaveText('2')
    await expect(page.getByTestId('counter-count-cycles')).toHaveText('3')

    const lastCycle = page.getByTestId('last-cycle-card')
    await expect(lastCycle).toContainText('Sterilizer No 4')
    await expect(lastCycle).toContainText('13:15')
    await expect(lastCycle).toContainText('75 menit')

    // Only the draft is listed, labeled Pause.
    await expect(page.getByTestId('draft-item-e2e-ster-today-draft')).toContainText('STR-DRAFT')
    await expect(page.getByTestId('draft-item-e2e-ster-today-draft')).toContainText('Pause')
    await expect(page.getByTestId('draft-item-e2e-ster-today-saved')).toHaveCount(0)
  })

  test('Monitor Sterilizer — New Data', async ({ page }) => {
    await page.goto('/stations/sterilizer/monitor')
    await expect(page.getByTestId('draft-list-empty')).toBeVisible()

    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/sterilizer\/form\/[^/]+$/)

    // A fresh draft starts with an empty Log Siklus.
    await expect(page.locator('#sterilizer_id')).toHaveValue('')
    await expect(page.locator('[data-testid^="detail-row-"]')).toHaveCount(0)
  })

  test('Monitor Sterilizer — Lanjutkan Draft', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedSterilizerRecord(page, userId, {
      id: 'e2e-ster-lanjutkan',
      status: 'draft_paused',
      sterilizerId: 'STR-LANJUTKAN',
      date: toLocalDateString(new Date()),
      cycles: [{ id: 'e2e-ster-lanjutkan-c1', sterilizerNo: '1', closeDoorTime: '07:00' }],
    })

    await page.goto('/stations/sterilizer/monitor')
    await page.getByTestId('draft-item-e2e-ster-lanjutkan').click()

    await page.waitForURL('**/stations/sterilizer/form/e2e-ster-lanjutkan')
    await expect(page.locator('#sterilizer_id')).toHaveValue('STR-LANJUTKAN')
    await expect(page.getByTestId('detail-close-door-time-0')).toHaveValue('07:00')
  })

  test('Monitor Sterilizer — Load Data', async ({ page }) => {
    await page.goto('/stations/sterilizer/monitor')

    await page.getByTestId('load-data-button').click()
    await page.waitForURL(/\/stations\/sterilizer\/preview$/)

    await expect(page.getByTestId('date-filter')).toBeVisible()
    await expect(page.getByTestId('search-filter')).toBeVisible()
  })

  test('Monitor Sterilizer — Belum Ada Draft', async ({ page }) => {
    await page.goto('/stations/sterilizer/monitor')

    await expect(page.getByTestId('draft-list-empty')).toContainText('Belum ada draft sterilizer tersimpan.')
    await expect(page.getByTestId('new-data-button')).toBeVisible()
    await expect(page.getByTestId('new-data-button')).toBeEnabled()
  })

  test('Monitor Sterilizer — Belum Ada Siklus Tercatat', async ({ page }) => {
    await page.goto('/stations/sterilizer/monitor')

    await expect(page.getByTestId('last-cycle-card')).toContainText('Belum ada siklus tercatat.')
    await expect(page.getByTestId('counter-count-records')).toHaveText('0')
    await expect(page.getByTestId('counter-count-cycles')).toHaveText('0')
  })

  test('Monitor Sterilizer — Tap Breadcrumb', async ({ page }) => {
    await page.goto('/stations/sterilizer/monitor')

    await page.getByTestId('breadcrumb-production-process-activity').click()
    await page.waitForURL('**/stations')
  })

  test('Monitor Sterilizer — Buka Menu Hamburger', async ({ page }) => {
    await page.goto('/stations/sterilizer/monitor')

    await expect(page.getByTestId('nav-menu')).toBeHidden()
    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu-change-password')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })
})

// business_logic step 6: New Data sets date = TODAY (local). Pinned to
// 01:30 WIB so the device's local date (2026-10-03) and the UTC date
// (2026-10-02) differ — the window 00:00–06:59 WIB every day.
//
// REGRESI — DIPERBAIKI 2026-10-03 (todayDateString() kini tanggal lokal; dulu UTC): src/services/sterilizerRecordRepo.ts:184-186
// todayDateString() returns `new Date().toISOString().slice(0, 10)` — the
// UTC date. Between 00:00 and 06:59 WIB a new draft is stamped with
// YESTERDAY's date, so it is missing from Monitor's "Hari Ini" counter
// (date('now','localtime')) and hidden by Data Preview's default
// today-filter, and is sent to the server with the wrong event date.
// Verified failing: expected "2026-10-03", received "2026-10-02". The same
// helper exists in cpoDispatchRecordRepo.ts:191,
// solidWasteDisposalRecordRepo.ts:180, kernelDispatchRecordRepo.ts:187.
test.describe('Monitor Sterilizer (screen-121) — tanggal lokal', () => {
  test.use({ timezoneId: 'Asia/Jakarta' })

  test('Monitor Sterilizer — New Data dini hari memakai tanggal lokal, bukan UTC', async ({ page }) => {
    await page.clock.setFixedTime(new Date('2026-10-03T01:30:00+07:00'))
    await login(page)

    await page.goto('/stations/sterilizer/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/sterilizer\/form\/[^/]+$/)

    await expect(page.locator('#date')).toHaveValue('2026-10-03')
  })
})
