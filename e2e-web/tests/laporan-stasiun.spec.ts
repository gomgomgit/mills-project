/**
 * Laporan Stasiun (Browser/Playwright) — screen-140--laporan-stasiun-web /
 * usecase-142--laporan-stasiun-web (Pilih Stasiun untuk Laporan).
 *
 * One test per test_scenarios entry whose `browser_test` is non-empty (all
 * 13). Route is /reports — the repo's report prefix is English
 * (/reports/management, /reports/sterilizer); no route in this app uses
 * /laporan.
 *
 * READ-ONLY SPEC, CREATES NOTHING. The screen only picks a destination, so
 * unlike laporan-sterilizer.spec.ts there are no fixtures to build through
 * other screens: everything asserted here is derived from master data that
 * already exists (business_units and station_types). Nothing is written,
 * so re-running leaves the database exactly as it was.
 *
 * THREE SCENARIOS DESCRIBE STATES THE LIVE DATABASE CANNOT BE PUT INTO
 * FROM A BROWSER, and they are handled by asserting the same branch from
 * whichever side the environment actually presents, rather than by being
 * skipped:
 *   - "belum ada mill di sistem" — there is no UI that empties the
 *     Business Unit master (and emptying it would break every other spec).
 *     The test asserts the branch condition itself: an empty picker MUST
 *     show [data-testid="no-business-units"], a non-empty one MUST NOT.
 *   - "akun terikat mill tetapi mill-nya kosong" — no screen can unbind a
 *     Supervisor from their mill, so the test asserts the complement (a
 *     bound account never sees that message and always sees mill-current).
 *     The fail-closed branch itself is covered by
 *     backend/tests/Feature/Api/LaporanStasiunTest.php and
 *     backend/tests/Feature/Livewire/LaporanStasiunTest.php.
 *   - "master Jenis Stasiun kosong" — there is no Jenis Stasiun master
 *     screen at all (the table is seeded by migration), so the test
 *     asserts the same pairing: tiles present => no-station-types absent,
 *     and vice versa.
 * Each is marked below with a WHY-NOT-SEEDED note. Do not "fix" them by
 * seeding the shared database.
 *
 * ORDERING IS ASSERTED AS PROCESS ORDER, NOT ALPHABETICAL: the grid comes
 * from station_types.sort_order, so weighbridge precedes sterilizer and
 * sterilizer precedes threshing — an order that alphabetical sorting could
 * not produce. Proving that ADDING a station type changes the grid needs a
 * master-data write the browser has no screen for; that half is covered by
 * the API and component tests.
 *
 * THE MILL TRAVELS IN THE LINK: an available tile's href carries
 * ?business_unit_id=<id>. screen-129 (Laporan Sterilizer) does not read
 * that parameter yet — that is separate, deferred work — so the navigation
 * assertions check that the URL CARRIES the mill, never that the
 * destination screen consumed it.
 */

import { test, expect, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const REPORT_PATH = '/reports'
const STERILIZER_REPORT_PATH = '/reports/sterilizer'

/** Supervisor bound to "BU Browser Test" (BrowserTestFixtureSeeder). */
const SUPERVISOR = 'stertest-browse01'
/** Mill Management bound to the same mill — same shape as Supervisor. */
const MILL_MANAGEMENT = 'stest-millmgmt01'
/** Admin — the only role that gets a mill picker. */
const ADMIN = 'stest-admin01'
/** Operator is a mobile-only actor — seeded by DemoAccountSeeder. */
const OPERATOR = 'operator01'

const BUSINESS_UNIT = 'BU Browser Test'

/** Today the only station whose period report exists (REPORT_ROUTES). */
const AVAILABLE_STATION = 'sterilizer'
/** A station whose report is not built yet — the disabled-tile fixture. */
const UNAVAILABLE_STATION = 'threshing'

async function openReports(page: Page, username: string): Promise<void> {
  await login(page, username, PASSWORD)
  await page.goto(REPORT_PATH)
  await expect(page.locator('[data-testid="laporan-stasiun"]')).toBeVisible()
}

/** The station codes of every tile in the grid, in the order rendered. */
async function tileCodes(page: Page): Promise<string[]> {
  const codes: string[] = []

  for (const tile of await page.locator('[data-testid^="station-tile-"]').all()) {
    const testid = await tile.getAttribute('data-testid')

    if (testid) {
      codes.push(testid.replace('station-tile-', ''))
    }
  }

  return codes
}

/** Every option of the mill picker as [value, label] pairs. */
async function millOptions(page: Page): Promise<Array<{ value: string; label: string }>> {
  const options: Array<{ value: string; label: string }> = []

  for (const option of await page.locator('[data-testid="mill-select"] option').all()) {
    const value = await option.getAttribute('value')
    const label = (await option.textContent())?.trim() ?? ''

    if (value) {
      options.push({ value, label })
    }
  }

  return options
}

test.describe('Laporan Stasiun (web)', () => {
  // Scenario: "success as Supervisor / Mill Management"
  test('supervisor: mill sendiri ditetapkan tanpa memilih, dan tile sterilizer membuka laporannya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)

    // Reached the way a user reaches it: the sidebar entry, which must now
    // point at this screen rather than straight at Laporan Sterilizer.
    await page.locator('.shell-sidebar__nav a', { hasText: 'Laporan Stasiun' }).click()
    await expect(page).toHaveURL(new RegExp(`${REPORT_PATH}$`))

    // Offering a picker they cannot use would be a lie, so there is none.
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-current"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="station-grid"]')).toBeVisible()

    const tile = page.locator(`[data-testid="station-tile-${AVAILABLE_STATION}"]`)

    await expect(tile).toBeVisible()
    await expect(tile).not.toHaveClass(/disabled/)

    await tile.click()

    await expect(page).toHaveURL(new RegExp(STERILIZER_REPORT_PATH))
    await expect(page.locator('[data-testid="laporan-sterilizer"]')).toBeVisible()
  })

  // Scenario: "success as Supervisor / Mill Management" (Mill Management half)
  test('mill management: sama seperti Supervisor — tanpa pemilih mill, grid langsung tampil', async ({ page }) => {
    await openReports(page, MILL_MANAGEMENT)

    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-current"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="station-grid"]')).toBeVisible()
  })

  // Scenario: "success as Admin"
  test('admin: grid baru muncul setelah mill dipilih, lalu tile sterilizer membuka laporannya', async ({ page }) => {
    await openReports(page, ADMIN)

    // Mill first, station second: the grid is withheld entirely until the
    // mill is settled.
    await expect(page.locator('[data-testid="station-grid"]')).toHaveCount(0)

    await page.locator('[data-testid="mill-select"]').selectOption({ label: BUSINESS_UNIT })

    await expect(page.locator('[data-testid="station-grid"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-current"]')).toContainText(BUSINESS_UNIT)

    await page.locator(`[data-testid="station-tile-${AVAILABLE_STATION}"]`).click()

    await expect(page).toHaveURL(new RegExp(STERILIZER_REPORT_PATH))
  })

  // Scenario: "Admin belum memilih mill"
  test('admin tanpa mill: arahan memilih mill terlihat dan grid tidak ada, tanpa area kosong tanpa penjelasan', async ({ page }) => {
    await openReports(page, ADMIN)

    await expect(page.locator('[data-testid="mill-select"]')).toHaveValue('')
    await expect(page.locator('[data-testid="mill-required-hint"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-required-hint"]')).toContainText(
      'Pilih mill terlebih dahulu untuk menampilkan stasiun.',
    )
    await expect(page.locator('[data-testid="station-grid"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="station-tile-sterilizer"]')).toHaveCount(0)
  })

  // Scenario: "belum ada mill di sistem"
  //
  // WHY NOT SEEDED: no screen empties the Business Unit master, and doing
  // so would break every other spec in this suite. The assertion is the
  // branch condition itself — the message appears exactly when the picker
  // has nothing to offer, and never while it has.
  test('belum ada mill: keterangan master kosong muncul persis ketika pemilih mill tidak punya opsi', async ({ page }) => {
    await openReports(page, ADMIN)

    const options = await millOptions(page)
    const emptyMasterHint = page.locator('[data-testid="no-business-units"]')

    if (options.length === 0) {
      await expect(emptyMasterHint).toBeVisible()
      await expect(emptyMasterHint).toContainText('Master Business Unit masih kosong')
      await expect(page.locator('[data-testid="station-grid"]')).toHaveCount(0)

      return
    }

    // The complement: with mills available the hint must stay away, and
    // the picker must really list them.
    await expect(emptyMasterHint).toHaveCount(0)
    expect(options.map((option) => option.label).join(' ')).not.toBe('')
    // Still no grid — a mill has to be chosen first, which is a different
    // reason from "there are no mills".
    await expect(page.locator('[data-testid="station-grid"]')).toHaveCount(0)
  })

  // Scenario: "akun terikat mill tetapi mill-nya kosong"
  //
  // WHY NOT SEEDED: no screen can unbind a Supervisor from their mill, so
  // the fail-closed 422 branch cannot be produced from a browser. It is
  // covered by the API and component tests. What IS asserted here is the
  // complement that keeps the branch honest: a bound account never sees
  // that message, always sees its own mill, and never gets a mill picker
  // as a fallback.
  test('akun terikat mill: pesan hubungi Admin tidak muncul, dan daftar seluruh mill tidak pernah ditawarkan', async ({ page }) => {
    await openReports(page, SUPERVISOR)

    await expect(page.locator('[data-testid="no-mill-for-account"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-current"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="station-grid"]')).toBeVisible()
  })

  // Scenario: "menekan stasiun yang belum tersedia"
  test('tile belum tersedia: menekannya tidak memindahkan halaman dan tidak memunculkan error', async ({ page }) => {
    await openReports(page, SUPERVISOR)

    const tile = page.locator(`[data-testid="station-tile-${UNAVAILABLE_STATION}"]`)

    await expect(tile).toBeVisible()
    await expect(tile).toHaveClass(/disabled/)
    await expect(tile).toHaveAttribute('aria-disabled', 'true')
    await expect(tile).toContainText('Belum tersedia')

    await tile.click()

    // It is not a link and carries no wire:click, so there is nothing to
    // follow and nothing to fail.
    await expect(page).toHaveURL(new RegExp(`${REPORT_PATH}$`))
    await expect(tile).toContainText('Belum tersedia')
    await expect(page.locator('body')).not.toContainText(/terjadi kesalahan|500|error/i)
  })

  // Scenario: "master Jenis Stasiun kosong"
  //
  // WHY NOT SEEDED: there is no Jenis Stasiun master screen — the table is
  // populated by migration — so the browser cannot empty it. The assertion
  // is the pairing that defines the branch: tiles and the empty-master
  // caption are mutually exclusive, and neither state is an error page.
  test('master jenis stasiun: grid dan keterangan kosong saling meniadakan, tanpa error', async ({ page }) => {
    await openReports(page, SUPERVISOR)

    await expect(page.locator('[data-testid="station-grid"]')).toBeVisible()

    const codes = await tileCodes(page)
    const emptyMasterHint = page.locator('[data-testid="no-station-types"]')

    if (codes.length === 0) {
      await expect(emptyMasterHint).toBeVisible()
      await expect(emptyMasterHint).toContainText('Master Jenis Stasiun masih kosong')

      return
    }

    await expect(emptyMasterHint).toHaveCount(0)
    await expect(page.locator('body')).not.toContainText(/terjadi kesalahan|500/i)
  })

  // Scenario: "Operator mencoba membuka layar ini"
  test('operator: akses ditolak dan tidak ada nama mill maupun tile stasiun yang terlihat', async ({ page }) => {
    await login(page, OPERATOR, PASSWORD)
    await page.goto(REPORT_PATH)

    // EnsureRole -> abort(403): the error page, never the screen.
    await expect(page.locator('body')).toContainText(/403|Forbidden/i)
    await expect(page.locator('[data-testid="laporan-stasiun"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="station-grid"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-current"]')).toHaveCount(0)
  })

  // Scenario: "pengguna terikat mill tidak dapat mengganti mill"
  test('supervisor memaksa ganti mill lewat query string: mill tetap milik akun, halaman tetap terbuka', async ({ page }) => {
    // Which other mills exist is environment data, so it is read from the
    // Admin's picker rather than hardcoded.
    await openReports(page, ADMIN)

    const otherMill = (await millOptions(page)).find((option) => option.label !== BUSINESS_UNIT)

    await page.context().clearCookies()
    await openReports(page, SUPERVISOR)

    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-current"]')).toContainText(BUSINESS_UNIT)

    // An id that is real but belongs to someone else — or, when this
    // instance has only one mill, an id that belongs to nobody at all.
    // Both must be discarded in the same way: the answer is the caller's
    // own mill, NOT a 403 and NOT a 404, because the parameter is never
    // validated for this role.
    const forcedId = otherMill?.value ?? '00000000-0000-4000-8000-000000000000'

    await page.goto(`${REPORT_PATH}?business_unit_id=${forcedId}`)

    await expect(page.locator('[data-testid="laporan-stasiun"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-current"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="station-grid"]')).toBeVisible()

    if (otherMill) {
      // Not one character of the other mill leaks into the page.
      await expect(page.locator('[data-testid="laporan-stasiun"]')).not.toContainText(otherMill.label)
    }

    // The tile still points at the caller's own mill, not the forced one.
    const href = await page.locator(`[data-testid="station-tile-${AVAILABLE_STATION}"]`).getAttribute('href')

    expect(href).toContain('business_unit_id=')
    expect(href).not.toContain(forcedId)
  })

  // Scenario: "daftar stasiun mengikuti master Jenis Stasiun dan urutan proses produksi"
  //
  // WHY NOT SEEDED: adding a station type needs a master-data write and
  // there is no screen for it, so "a new type appears without a code
  // change" is proven by the API and component tests. What the browser can
  // prove is the ordering rule itself — process order, which alphabetical
  // sorting could never produce.
  test('urutan stasiun: mengikuti urutan proses produksi, bukan alfabet', async ({ page }) => {
    await openReports(page, SUPERVISOR)

    const codes = await tileCodes(page)

    expect(codes.length).toBeGreaterThan(0)

    const alphabetical = [...codes].sort()

    // weighbridge (first in the process) would be near LAST alphabetically,
    // so these two orders cannot coincide.
    expect(codes).not.toEqual(alphabetical)

    const position = (code: string) => codes.indexOf(code)

    if (position('weighbridge') >= 0 && position(AVAILABLE_STATION) >= 0) {
      expect(position('weighbridge')).toBeLessThan(position(AVAILABLE_STATION))
    }

    if (position(AVAILABLE_STATION) >= 0 && position(UNAVAILABLE_STATION) >= 0) {
      expect(position(AVAILABLE_STATION)).toBeLessThan(position(UNAVAILABLE_STATION))
    }
  })

  // Scenario: "jenis stasiun historis 'other' dikecualikan"
  test("jenis 'other' dikecualikan: tidak ada tile Other, tile lain tetap tampil", async ({ page }) => {
    await openReports(page, SUPERVISOR)

    await expect(page.locator('[data-testid="station-tile-other"]')).toHaveCount(0)

    const codes = await tileCodes(page)

    expect(codes).not.toContain('other')
    expect(codes).toContain(AVAILABLE_STATION)
    expect(codes).toContain(UNAVAILABLE_STATION)

    // No tile is captioned with the historical bucket's display name.
    await expect(page.locator('[data-testid="station-grid"]')).not.toContainText(/^Other$/)
  })

  // Scenario: "stasiun yang belum dibangun tampil nonaktif, bukan disembunyikan"
  //
  // ASSERTS THE INVARIANT, NOT A COUNT. The first version of this test hard
  // coded "exactly 1 active tile", written when REPORT_ROUTES held a single
  // entry. Adding screen-130 (Cages & Tracks) made it fail — not because
  // anything regressed, but because the number it froze was never the point.
  // Four more station reports are planned in this phase alone, so a count
  // would go red four more times and teach nothing each time.
  //
  // What actually has to hold is the RELATIONSHIP: a tile is active exactly
  // when it links somewhere, disabled exactly when it does not, every tile
  // is one or the other, and at least one report exists. That stays true
  // however many entries REPORT_ROUTES grows to.
  test('stasiun tanpa laporan tampil nonaktif; yang punya laporan dapat diklik', async ({ page }) => {
    await openReports(page, SUPERVISOR)

    const codes = await tileCodes(page)

    // Unbuilt reports are greyed out, never hidden — the grid shows the
    // whole station catalogue so the feature's coverage reads as it is.
    expect(codes.length).toBeGreaterThan(1)

    const activeTiles = page.locator('[data-testid^="station-tile-"].active')
    const disabledTiles = page.locator('[data-testid^="station-tile-"].disabled')

    const activeCount = await activeTiles.count()
    const disabledCount = await disabledTiles.count()

    // Every tile is exactly one of the two — no tile is both, none neither.
    expect(activeCount + disabledCount).toBe(codes.length)
    // The grid is not entirely dead: at least one report is reachable.
    expect(activeCount).toBeGreaterThan(0)
    // And not everything is active, or "disabled" would be untested here.
    expect(disabledCount).toBeGreaterThan(0)

    // Active <=> links to a report under /reports/.
    for (const tile of await activeTiles.all()) {
      await expect(tile).toHaveAttribute('href', /\/reports\//)
      await expect(tile).not.toContainText('Belum tersedia')
    }

    // Disabled <=> no href at all, and says so in words.
    for (const tile of await disabledTiles.all()) {
      await expect(tile).not.toHaveAttribute('href', /./)
      await expect(tile).toContainText('Belum tersedia')
    }

    // The two fixtures this file pins by name, so the invariant above can
    // never be satisfied vacuously by an empty or mislabelled grid.
    const available = page.locator(`[data-testid="station-tile-${AVAILABLE_STATION}"]`)
    const unavailable = page.locator(`[data-testid="station-tile-${UNAVAILABLE_STATION}"]`)

    await expect(available).toHaveAttribute('href', /reports\/sterilizer/)
    await expect(unavailable).toHaveClass(/disabled/)
    await expect(unavailable).toContainText('Belum tersedia')
    await expect(unavailable).not.toHaveAttribute('href', /./)
  })

  // Scenario: "mill yang ditetapkan terbawa ke layar laporan"
  //
  // NOTE: screen-129 does not read business_unit_id from the query string
  // yet (separate, deferred work), so this asserts that the destination
  // URL CARRIES the last selected mill — not that the report screen
  // consumed it.
  test('mill terbawa: tautan tile membawa mill terakhir yang ditetapkan Admin', async ({ page }) => {
    await openReports(page, ADMIN)

    const options = await millOptions(page)

    expect(options.length).toBeGreaterThan(0)

    const first = options[0]
    const last = options.length > 1 ? options[options.length - 1] : options[0]

    await page.locator('[data-testid="mill-select"]').selectOption(first.value)
    await expect(page.locator('[data-testid="station-grid"]')).toBeVisible()
    await expect(page.locator(`[data-testid="station-tile-${AVAILABLE_STATION}"]`))
      .toHaveAttribute('href', new RegExp(`business_unit_id=${first.value}`))

    // Switched: the link must follow the new mill rather than keep
    // pointing at the one chosen a moment ago.
    await page.locator('[data-testid="mill-select"]').selectOption(last.value)
    await expect(page.locator('[data-testid="mill-current"]')).toContainText(last.label)

    const tile = page.locator(`[data-testid="station-tile-${AVAILABLE_STATION}"]`)

    await expect(tile).toHaveAttribute('href', new RegExp(`business_unit_id=${last.value}`))

    await tile.click()

    await expect(page).toHaveURL(new RegExp(STERILIZER_REPORT_PATH))
    expect(page.url()).toContain(`business_unit_id=${last.value}`)
  })
})
