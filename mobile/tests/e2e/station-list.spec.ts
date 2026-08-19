import { test, expect } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-006--station-list / usecase-006--station-list "Pilih Stasiun"
//
// No manual station seeding needed here anymore — stores/auth.ts's
// login() now seeds the 15 default MVP stations itself (screen-006 empty-
// grid fix, 2026-08-18; see localSchema.ts's seedDefaultStationsIfNeeded()).
// Tiles are located by their visible label text rather than by exact
// data-testid, since seeded ids are derived from business_unit_id and not
// asserted here — this keeps the test decoupled from that id scheme.
// (role="listitem" on the tile <button> is NOT reliably exposed by the
// accessibility tree — confirmed via a real run — so getByRole('listitem')
// is avoided here; getByText's click bubbles to the parent button via
// normal DOM event propagation.)
//
// Home was rewritten (2026-08-18, screen-005) into a pure navigation
// launcher — the old "Daftar Stasiun" button no longer exists; the
// functional menu card is now labelled "Production Process Activity"
// (data-testid="menu-card-production-process-activity", see HomeView.vue).
test.describe('Station List (screen-006)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)

    await page.getByTestId('menu-card-production-process-activity').click()
    await page.waitForURL('**/stations')
  })

  test('Pilih Stasiun — success', async ({ page }) => {
    await page.getByText('Weighbridge', { exact: true }).click()
    await page.waitForURL('**/stations/weighbridge/monitor')
  })

  test('Pilih Stasiun — Tap Stasiun Disabled', async ({ page }) => {
    await page.getByText('Sterilizer', { exact: true }).click()

    await expect(page.getByTestId('station-info-message')).toContainText('belum tersedia')
    await expect(page).toHaveURL(/\/stations$/)
  })

  // 2026-08-18 update — tappable 'Home' breadcrumb segment (business_
  // logic step 8). data-testid mirrors StationListView.vue's actual
  // markup (`breadcrumb-home`).
  test('Breadcrumb — tapping "Home" navigates to /home', async ({ page }) => {
    await page.getByTestId('breadcrumb-home').click()
    await page.waitForURL('**/home')
  })

  // 2026-08-18 update — header hamburger nav menu, copied verbatim from
  // HomeView.vue's pattern (see home.spec.ts's own hamburger coverage for
  // the sibling screen). Only presence/visibility is asserted here — the
  // nav menu's own navigation behavior (Ganti Password / Logout) is
  // already covered end-to-end by home.spec.ts and does not need
  // duplicating per screen.
  test('Hamburger menu — tapping the hamburger icon opens the nav menu', async ({ page }) => {
    await expect(page.getByTestId('nav-menu')).toBeHidden()

    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu-change-password')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })

  // 2026-08-18 update — draft-status color indicator (business_logic step
  // 2/3): active tiles render red when the current user has an in-
  // progress (draft_ongoing or draft_paused) record for that station
  // type, dark-neutral otherwise. Seeds directly into the local
  // `weighbridge_record` table via the dev-only `window.__mslTestDb`
  // bridge, same shape as helpers.ts's `seedPausedDrafts()` (id/status/
  // created_by/created_at/updated_at only — the columns
  // weighbridgeRecordRepo.getSummary()'s `currentDraft` lookup actually
  // reads), but inserting a single 'draft_ongoing' row directly here
  // instead of reusing `seedPausedDrafts()` (which is hardcoded to
  // 'draft_paused' and built for Home's "many paused drafts" threshold
  // scenario, not this one).
  test('Color indicator — active tile is red with a draft, dark-neutral with none', async ({ page }) => {
    const weighbridgeTile = page.locator('button.station-tile--active').filter({ hasText: 'Weighbridge' })

    // No draft seeded yet — dark-neutral (#1F2937 -> rgb(31, 41, 55)).
    await expect(weighbridgeTile).toHaveCSS('background-color', 'rgb(31, 41, 55)')

    const userId = await getAuthUserId(page)
    await page.evaluate(async (uid) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT INTO weighbridge_record (id, status, created_by, created_at, updated_at)
         VALUES (?, 'draft_ongoing', ?, ?, ?)`,
        [`e2e-draft-ongoing-${Date.now()}`, uid, now, now],
      )
    }, userId)

    await page.reload()
    await page.waitForURL('**/stations')

    const weighbridgeTileAfterSeed = page.locator('button.station-tile--active').filter({ hasText: 'Weighbridge' })
    await expect(weighbridgeTileAfterSeed).toHaveCSS('background-color', 'rgb(210, 0, 0)')

    // Other active tiles (no draft seeded for them) stay dark-neutral.
    const gradingTile = page.locator('button.station-tile--active').filter({ hasText: 'Grading' })
    await expect(gradingTile).toHaveCSS('background-color', 'rgb(31, 41, 55)')
  })
})
