import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-015--data-preview-cages-track / usecase-015--data-preview-cages-track
//
// FULL REWRITE (2026-08-19, scope expansion — the last of a 3-screen Cages
// Track overhaul; Monitor Cages Track and Form Cages Track are already
// implemented/tested/verified independently and are not touched here) to
// match DataPreviewCagesTrackView.vue's rewrite adding a route-driven LIST
// mode (default, `/stations/cages-track/preview` — no `:id`) alongside the
// pre-existing DETAIL mode (`/stations/cages-track/preview/:id`) — mirrors
// data-preview-grading.spec.ts's (screen-014) / data-preview-weighbridge
// .spec.ts's (screen-013) own dual-mode rewrites as closely as possible,
// adapted for this screen's `cages_tipped_time` child-row detail grid
// (parsed via hourLabel()/checkedCagesDisplay() rather than resolved
// Quality Parameter names) and its DETAIL mode's Checked By / Acknowledged
// By fields (rendered here, unlike Data Preview Grading, which renders
// neither). The previous version of this file (a single "success" +
// "Record Tidak Ditemukan" test targeting the old single-record-only
// read-only view shape, driven entirely through the real Form Cages Track
// UI) is known-broken against the new schema/UI — this is fully replaced
// below, not preserved.
//
// Per this suite's convention (see monitor-cages-track.spec.ts /
// form-cages-track.spec.ts / data-preview-grading.spec.ts), multi-record
// list scenarios seed `cages_track_record` (and, for detail mode,
// `cages_tipped_time`) rows directly via the dev-only `window.
// __mslTestDb` bridge — there is no in-app flow that produces more than a
// couple of records at a time through the real UI.
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

async function seedCagesTrackRecord(
  page: Page,
  userId: string,
  overrides: {
    id: string
    status?: 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'
    cagesTrackNumber?: string | null
    date?: string | null
    tipplerStartTime?: string | null
    tipplerStopTime?: string | null
    cagesOut?: number | null
    cagesTipped?: number | null
    note?: string | null
    checkedBy?: string | null
    acknowledgedBy?: string | null
    updatedAt?: string
  },
): Promise<void> {
  await page.evaluate(
    async ({
      userId,
      id,
      status,
      cagesTrackNumber,
      date,
      tipplerStartTime,
      tipplerStopTime,
      cagesOut,
      cagesTipped,
      note,
      checkedBy,
      acknowledgedBy,
      updatedAt,
    }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = updatedAt ?? new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO cages_track_record
           (id, status, cages_track_number, date, tippler_start_time, tippler_stop_time,
            cages_out, cages_tipped, note, checked_by, acknowledged_by, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          id,
          status ?? 'saved',
          cagesTrackNumber ?? null,
          date ?? null,
          tipplerStartTime ?? null,
          tipplerStopTime ?? null,
          cagesOut ?? null,
          cagesTipped ?? null,
          note ?? null,
          checkedBy ?? null,
          acknowledgedBy ?? null,
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
      cagesTrackNumber: overrides.cagesTrackNumber,
      date: overrides.date,
      tipplerStartTime: overrides.tipplerStartTime,
      tipplerStopTime: overrides.tipplerStopTime,
      cagesOut: overrides.cagesOut,
      cagesTipped: overrides.cagesTipped,
      note: overrides.note,
      checkedBy: overrides.checkedBy,
      acknowledgedBy: overrides.acknowledgedBy,
      updatedAt: overrides.updatedAt,
    },
  )
}

async function seedCagesTippedTime(
  page: Page,
  overrides: {
    id: string
    cagesTrackRecordId: string
    tippedHour: number
    checkedCageNumbers: string
    totalCages: number
    cagesRemain: number
  },
): Promise<void> {
  await page.evaluate(
    async ({ id, cagesTrackRecordId, tippedHour, checkedCageNumbers, totalCages, cagesRemain }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO cages_tipped_time
           (id, cages_track_record_id, tipped_hour, checked_cage_numbers, total_cages, cages_remain, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        [id, cagesTrackRecordId, tippedHour, checkedCageNumbers, totalCages, cagesRemain, now, now],
      )
    },
    {
      id: overrides.id,
      cagesTrackRecordId: overrides.cagesTrackRecordId,
      tippedHour: overrides.tippedHour,
      checkedCageNumbers: overrides.checkedCageNumbers,
      totalCages: overrides.totalCages,
      cagesRemain: overrides.cagesRemain,
    },
  )
}

test.describe('Data Preview Cages Track (screen-015)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  // Scenario: "Record Tidak Ditemukan" — detail mode, invalid id.
  test('Data Preview Cages Track — Record Tidak Ditemukan', async ({ page }) => {
    await page.goto('/stations/cages-track/preview/does-not-exist')

    await expect(page.getByTestId('record-not-found')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Back' })).toBeVisible()
  })

  // Scenario: the date filter defaults to today's local date on list-view
  // open, with no seeded records and no user interaction needed.
  test('Data Preview Cages Track — Filter Tanggal Default ke Hari Ini', async ({ page }) => {
    await page.goto('/stations/cages-track/preview')

    await expect(page.getByTestId('date-filter-input')).toHaveValue(todayLocalDateString())
  })

  // End-to-end flow: login, navigate to the screen, verify list renders
  // with the default today filter, change/clear the date filter, search,
  // tap a saved/synced record into detail mode, verify detail fields
  // render (including Checked By / Acknowledged By and tipped-time rows),
  // back to list, back to Monitor.
  test('Data Preview Cages Track — berhasil (list -> detail, full header + tipped-time rows, back to list, back to Monitor)', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = todayLocalDateString()

    await seedCagesTrackRecord(page, userId, {
      id: 'e2e-cages-track-target',
      status: 'saved',
      cagesTrackNumber: 'CT-PREVIEW-01',
      date: `${today}T07:00:00`,
      tipplerStartTime: `${today}T07:00:00`,
      tipplerStopTime: `${today}T11:00:00`,
      cagesOut: 20,
      cagesTipped: 18,
      note: 'Catatan e2e',
      checkedBy: 'Supervisor Satu',
      acknowledgedBy: 'Mill Management Satu',
    })
    await seedCagesTrackRecord(page, userId, {
      id: 'e2e-cages-track-other',
      status: 'synced',
      cagesTrackNumber: 'CT-OTHER-02',
      date: `${today}T08:00:00`,
    })
    await seedCagesTippedTime(page, {
      id: 'e2e-cages-tipped-1',
      cagesTrackRecordId: 'e2e-cages-track-target',
      tippedHour: 8,
      checkedCageNumbers: '1,3,5',
      totalCages: 3,
      cagesRemain: 17,
    })
    await seedCagesTippedTime(page, {
      id: 'e2e-cages-tipped-2',
      cagesTrackRecordId: 'e2e-cages-track-target',
      tippedHour: 9,
      checkedCageNumbers: '2,4',
      totalCages: 2,
      cagesRemain: 15,
    })

    await page.goto('/stations/cages-track/preview')

    // List renders with the default today filter, no interaction needed.
    await expect(page.getByTestId('date-filter-input')).toHaveValue(today)
    await expect(page.getByTestId('record-item-e2e-cages-track-target')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-cages-track-other')).toBeVisible()

    // Change the date filter to a non-matching date -> both records hide.
    await page.getByTestId('date-filter-input').fill('2020-01-01')
    await expect(page.getByTestId('record-item-e2e-cages-track-target')).toBeHidden()
    await expect(page.getByTestId('record-list-empty')).toBeVisible()

    // Clear the date filter -> both reappear.
    await page.getByTestId('date-filter-input').fill('')
    await expect(page.getByTestId('record-item-e2e-cages-track-target')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-cages-track-other')).toBeVisible()

    // Search filters down to the target record only.
    await page.getByTestId('search-filter-input').fill('PREVIEW')
    await expect(page.getByTestId('record-item-e2e-cages-track-target')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-cages-track-other')).toBeHidden()

    // Tap the saved record -> detail mode.
    await page.getByTestId('record-item-e2e-cages-track-target').click()
    await page.waitForURL('**/stations/cages-track/preview/e2e-cages-track-target')

    await expect(page.getByLabel('No. Cages Track')).toHaveValue('CT-PREVIEW-01')
    await expect(page.getByLabel('No. Cages Track')).toBeDisabled()
    await expect(page.getByLabel('Cages Out')).toHaveValue('20')
    await expect(page.getByLabel('Cages Tipped')).toHaveValue('18')
    await expect(page.getByLabel('Checked By')).toHaveValue('Supervisor Satu')
    await expect(page.getByLabel('Acknowledged By')).toHaveValue('Mill Management Satu')
    await expect(page.getByLabel('Catatan')).toHaveValue('Catatan e2e')

    const tippedRows = page.getByTestId('tipped-time-rows-list')
    await expect(tippedRows).toBeVisible()
    const row1 = page.getByTestId('tipped-time-row-e2e-cages-tipped-1')
    await expect(row1).toContainText('08:00')
    await expect(row1).toContainText('Cage 1, Cage 3, Cage 5')
    await expect(row1).toContainText('Total Cages: 3')
    await expect(row1).toContainText('Cages Remain: 17')
    const row2 = page.getByTestId('tipped-time-row-e2e-cages-tipped-2')
    await expect(row2).toContainText('09:00')
    await expect(row2).toContainText('Cage 2, Cage 4')
    await expect(row2).toContainText('Total Cages: 2')
    await expect(row2).toContainText('Cages Remain: 15')

    // Back -> list mode (id removed from URL, NOT Monitor), same list
    // state preserved.
    await page.getByRole('button', { name: 'Back' }).click()
    await page.waitForURL('**/stations/cages-track/preview')
    await expect(page).not.toHaveURL('**/stations/cages-track/monitor')
    await expect(page.getByTestId('record-item-e2e-cages-track-target')).toBeVisible()

    // Back again -> Monitor Cages Track.
    await page.getByRole('button', { name: 'Back' }).click()
    await page.waitForURL('**/stations/cages-track/monitor')
  })

  // Scenario: "Tap Item Draft/Pause" — tap a draft/pause item -> navigates
  // to Form Cages Track, no detail switch.
  test('Data Preview Cages Track — Tap Item Draft/Pause', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedCagesTrackRecord(page, userId, {
      id: 'e2e-cages-track-draft',
      status: 'draft_ongoing',
      cagesTrackNumber: 'CT-DRAFT-01',
      // Dated TODAY so the row is present under the default date filter
      // to be tapped.
      date: `${todayLocalDateString()}T07:00:00`,
    })

    await page.goto('/stations/cages-track/preview')
    await page.getByTestId('record-item-e2e-cages-track-draft').click()

    await page.waitForURL('**/stations/cages-track/form/e2e-cages-track-draft')
    await expect(page).not.toHaveURL(/\/stations\/cages-track\/preview\/e2e-cages-track-draft/)
  })

  // Scenario: "Filter Diterapkan" — apply date filter -> list updates to
  // matching records only (compared against the date column's first 10
  // characters — a full local datetime, not a plain date).
  test('Data Preview Cages Track — Filter Diterapkan', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedCagesTrackRecord(page, userId, {
      id: 'e2e-cages-track-aug10',
      status: 'saved',
      cagesTrackNumber: 'CT-AUG10',
      date: '2026-08-10T14:30:00',
    })
    await seedCagesTrackRecord(page, userId, {
      id: 'e2e-cages-track-aug11',
      status: 'saved',
      cagesTrackNumber: 'CT-AUG11',
      date: '2026-08-11T08:00:00',
    })

    await page.goto('/stations/cages-track/preview')

    // Both fixture records are dated 2026-08-10/11, not today — clear the
    // list's default today date filter first to establish the "both
    // visible, unfiltered" baseline before exercising date filtering.
    await page.getByTestId('date-filter-input').fill('')
    await expect(page.getByTestId('record-item-e2e-cages-track-aug10')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-cages-track-aug11')).toBeVisible()

    await page.getByTestId('date-filter-input').fill('2026-08-11')

    await expect(page.getByTestId('record-item-e2e-cages-track-aug11')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-cages-track-aug10')).toBeHidden()
  })

  // Scenario: "Filter Tidak Menghasilkan Apapun" — filter matches nothing
  // -> not-found/empty message + Reset Filter button; clicking it restores
  // the full list.
  test('Data Preview Cages Track — Filter Tidak Menghasilkan Apapun', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = todayLocalDateString()
    await seedCagesTrackRecord(page, userId, {
      id: 'e2e-cages-track-solo',
      status: 'saved',
      cagesTrackNumber: 'CT-SOLO',
      // Dated TODAY so it stays visible against the list's default
      // (untouched) date filter throughout this scenario, which only
      // exercises the search filter.
      date: `${today}T07:00:00`,
    })

    await page.goto('/stations/cages-track/preview')
    await expect(page.getByTestId('record-item-e2e-cages-track-solo')).toBeVisible()

    await page.getByTestId('search-filter-input').fill('no-such-record-keyword')

    await expect(page.getByTestId('record-list-empty')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-cages-track-solo')).toBeHidden()
    await expect(page.getByTestId('reset-filter-button')).toBeVisible()

    await page.getByTestId('reset-filter-button').click()

    await expect(page.getByTestId('date-filter-input')).toHaveValue('')
    await expect(page.getByTestId('search-filter-input')).toHaveValue('')
    await expect(page.getByTestId('record-item-e2e-cages-track-solo')).toBeVisible()
    await expect(page.getByTestId('record-list-empty')).toBeHidden()
  })

  // Scenario: "List Kosong" — no records at all -> empty state.
  test('Data Preview Cages Track — List Kosong', async ({ page }) => {
    await page.goto('/stations/cages-track/preview')

    await expect(page.getByTestId('record-list-empty')).toBeVisible()
    await expect(page.getByTestId('record-list')).toBeHidden()
  })

  // Scenario: "Tap Breadcrumb" — tap the 'Cages Track' breadcrumb segment
  // -> navigates to Monitor Cages Track.
  test('Data Preview Cages Track — Tap Breadcrumb', async ({ page }) => {
    await page.goto('/stations/cages-track/preview')

    await page.getByTestId('breadcrumb-cages-track').click()
    await page.waitForURL('**/stations/cages-track/monitor')
  })

  // Scenario: "Buka Menu Hamburger" — tap hamburger -> nav menu visible.
  test('Data Preview Cages Track — Buka Menu Hamburger', async ({ page }) => {
    await page.goto('/stations/cages-track/preview')

    await expect(page.getByTestId('nav-menu')).toBeHidden()

    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu-change-password')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })

  // Scenario: "Back dari List Mode" — in list mode, tap Back -> returns to
  // Monitor Cages Track.
  test('Data Preview Cages Track — Back dari List Mode', async ({ page }) => {
    await page.goto('/stations/cages-track/preview')

    await page.getByRole('button', { name: 'Back' }).click()

    await page.waitForURL('**/stations/cages-track/monitor')
  })

  // Scenario: "Back dari Mode Detail" — in detail mode, tap Back -> returns
  // to list mode (URL loses id, no full page reload / state preserved —
  // asserted via a marker that would be cleared by a real reload, plus the
  // same component instance's list re-rendering correctly straight after),
  // NOT Monitor Cages Track.
  test('Data Preview Cages Track — Back dari Mode Detail (list state preserved, no full reload)', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = todayLocalDateString()
    await seedCagesTrackRecord(page, userId, {
      id: 'e2e-cages-track-back',
      status: 'saved',
      cagesTrackNumber: 'CT-BACK-01',
      date: `${today}T07:00:00`,
    })

    await page.goto('/stations/cages-track/preview/e2e-cages-track-back')
    await expect(page.getByLabel('No. Cages Track')).toHaveValue('CT-BACK-01')

    // Marks the current document so a full page reload (which would tear
    // down and recreate the document, clearing this marker) can be
    // detected — the SPA-internal Back navigation must NOT trigger one.
    await page.evaluate(() => {
      ;(window as unknown as { __e2eNoReloadMarker: boolean }).__e2eNoReloadMarker = true
    })

    await page.getByRole('button', { name: 'Back' }).click()

    await page.waitForURL('**/stations/cages-track/preview')
    await expect(page).not.toHaveURL('**/stations/cages-track/monitor')
    await expect(page.getByTestId('record-item-e2e-cages-track-back')).toBeVisible()

    const markerSurvived = await page.evaluate(
      () => (window as unknown as { __e2eNoReloadMarker?: boolean }).__e2eNoReloadMarker === true,
    )
    expect(markerSurvived).toBe(true)
  })
})
