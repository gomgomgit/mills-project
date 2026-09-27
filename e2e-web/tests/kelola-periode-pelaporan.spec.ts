/**
 * Kelola Periode Pelaporan (Browser/Playwright) —
 * screen-128--kelola-periode-pelaporan / usecase-128 (CRUD) +
 * usecase-140 (tutup & buka kembali) + usecase-144 (buka stasiun draft).
 *
 * One test per test_scenarios entry whose `browser_test` is non-empty
 * (scenarios 1–13, 16–18 and 21–28, plus the cancelOpen path), followed by
 * the scenarios the per-station model made possible for the first time.
 * Scenarios 14, 15, 19 and 20 carry an empty browser_test — they are
 * mobile-sync / record-locking scenarios and live, skipped, in
 * backend/tests/Feature/Api/KelolaPeriodePelaporanTest.php.
 *
 * ── APA YANG BERUBAH PADA 2026-09-26, DAN MENGAPA SPEC INI BERBEDA JAUH ──
 *
 * Entitas periode dipecah menjadi induk (`periods`) + anak
 * (`period_stations`). Konsekuensinya menyentuh hampir setiap asersi di sini:
 *
 *  1. PERIODE TIDAK PUNYA STATUS. Yang punya status adalah tiap baris
 *     stasiun. Baris induk hanya membawa RINGKASAN: `status_summary`
 *     (draft | open | closed | mixed | empty, dilabeli Draft / Terbuka /
 *     Tertutup / Campuran / Tanpa Stasiun) dan "N stasiun · M tertutup".
 *     Tidak ada lagi kolom Jenis Stasiun, Ditutup Oleh maupun Waktu Ditutup
 *     di baris induk — ketiganya pindah ke baris stasiun.
 *  2. FORM TIDAK PUNYA PEMILIH JENIS STASIUN. Satu periode mencakup SELURUH
 *     mill: menyimpan mendaftarkan satu baris per jenis stasiun aktif di
 *     mill itu. Form menggantinya dengan pratinjau daftar stasiun yang akan
 *     didaftarkan; di mode Edit, jenis yang belum punya baris diberi badge
 *     "Baru".
 *  3. TABEL BERTINGKAT DAN TERTUTUP SECARA DEFAULT. Baris stasiun hidup di
 *     <tr> kedua dan hanya dirender selama id periodenya ada di
 *     $expandedPeriodIds — yang kosong saat mount. SETIAP tombol per-stasiun
 *     karena itu tidak ada di DOM sebelum barisnya dibuka; helper
 *     expandPeriod()/openStationRow() di tests/support/period-screen.ts yang
 *     mengurus itu, dan setiap testid per-stasiun membawa id
 *     `period_stations`, bukan id periode.
 *  4. SIKLUSNYA draft -> open -> closed, TANPA JALAN PINTAS. Baris Draft
 *     hanya menawarkan "Buka Stasiun"; "Tutup Stasiun" baru muncul pada
 *     baris Terbuka; "Buka Kembali" hanya pada baris Tertutup. Dulu sebuah
 *     periode Draft bisa langsung ditutup dalam satu klik — tidak lagi.
 *  5. TUMPANG TINDIH TANGGAL KINI PER MILL, tanpa melihat jenis stasiun.
 *     Satu tanggal hanya boleh dimiliki satu periode pada satu mill.
 *
 * FIXTURES — this spec deliberately reuses accounts BrowserTestFixtureSeeder
 * already creates, so no seeder change is needed to run it:
 *   - butest-admin01     (Admin,      name "Butest admin01")
 *   - pltest-admin01     (Admin,      name "Pltest admin01") — the second
 *                        Admin of the concurrent-closure scenario
 *   - butest-nonadmin01  (Supervisor) — the "akses ditolak" scenarios
 *   - Business Unit "BU Browser Test" — the mill every scenario uses; it has
 *     18 active station types, so every period created here gets 18 station
 *     rows. The COUNT is never hardcoded: it is read from the form's own
 *     station preview, so adding or retiring a station in the fixture mill
 *     cannot silently invalidate an assertion.
 * Password: PASSWORD from ./support/auth.
 *
 * SELF-SUFFICIENT DATA: every scenario creates the periods it needs through
 * the UI. Because a period's range may not overlap another period OF THE SAME
 * MILL, each test gets its OWN five-day window far in the future, offset by a
 * per-run seed (see uniqueRange) — so re-running the suite never collides
 * with the rows a previous run left behind. Two scenarios are exceptions and
 * both clean up after themselves: the unverified-count one must cover a date
 * that already carries an unverified station record (2026-08-05, seeded by
 * BrowserTestFixtureSeeder), and the "Tanpa Stasiun" one must use a mill
 * without stations, which is a different mill entirely.
 *
 * SLOTS GROW IN DECLARATION ORDER, on purpose. The list is paginated at 20
 * rows ordered by start_date DESC and this spec accumulates a period per
 * test until afterAll, so a test's own row must sort ABOVE the ones the
 * earlier tests left. That holds only while slot numbers increase down the
 * file; the two fixed-window scenarios therefore run FIRST, while the list
 * is still short enough for their much older dates to be on page 1.
 *
 * #business_unit_id / #filterBusinessUnitId / #filterStatus are
 * x-searchable-select comboboxes (resources/views/components/
 * searchable-select.blade.php), not native <select> — driven through
 * selectSearchable() from ./support/period-screen.
 */

import { test, expect, type Locator, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { deletePeriodsByPrefix } from './support/periods'
import {
  createPeriodViaUi,
  expandPeriod,
  openStationRow,
  openStation,
  periodIdFor,
  periodRow,
  selectSearchable,
  stationIdFor,
} from './support/period-screen'

const PERIODS_PATH = '/master-data/periods'

const ADMIN = 'butest-admin01'
const ADMIN_NAME = 'Butest admin01'
const SECOND_ADMIN = 'pltest-admin01'
const SECOND_ADMIN_NAME = 'Pltest admin01'
const NON_ADMIN = 'butest-nonadmin01'

const BUSINESS_UNIT = 'BU Browser Test'

/** The station row nearly every scenario acts on. */
const STATION_TYPE = 'Sterilizer'

/** A second station row, to prove the two never move together. */
const OTHER_STATION_TYPE = 'Clarification'

/**
 * The only station type with an unverified record inside the August 2026
 * window BrowserTestFixtureSeeder seeds (one Effluent Plant record dated
 * 2026-08-05, with neither checked_by nor acknowledged_by). Every other type
 * has none in that window — which is exactly what makes the per-station
 * unverified count provable in a browser.
 */
const UNVERIFIED_STATION_TYPE = 'Effluent Plant'

/**
 * A mill with no active station at all, so a period created for it gets NO
 * `period_stations` row — the `empty` / "Tanpa Stasiun" summary, which cannot
 * be produced in a provisioned mill.
 */
const EMPTY_MILL = 'Mill Kode Duplikat'

/**
 * A per-run day offset, so two runs of this suite never try to create
 * overlapping ranges in the same mill.
 *
 * MONOTONIC ON PURPOSE (one day of period-date space per minute of wall
 * clock since 2026-01-01), where it used to be `Date.now() % 150000`: the
 * list is paginated at 20 rows ordered by `start_date DESC`, and the
 * fixture database keeps every period any previous run created —
 * including laporan-sterilizer.spec.ts's, which seeds its own windows in
 * the year-2600 era. A modulo offset lands a fresh run above or below
 * those older rows at random, and once more than 20 of them sort higher,
 * the row a test has just created is no longer on page 1 and every
 * assertion on it fails. An offset that only ever grows keeps the current
 * run's rows at the top of the first page whatever the database has
 * accumulated. Two runs started within the same minute still collide —
 * in practice that window is no worse than the old scheme's.
 *
 * IT ALSO KEEPS THIS SPEC CLEAR OF laporan-sterilizer.spec.ts, which is the
 * other spec seeding periods in this mill. Now that overlap is per mill
 * rather than per (mill, station type), sharing a date with it would be
 * refused outright. Its windows live at most ~151,200 days past 2600-01-01
 * (see tests/support/period-lanes.ts); this spec's epoch of 2100 plus a
 * minutes-since-2026 offset puts it ~203,000 days past that same date and
 * only ever further, so the two can never meet.
 */
const RUN_OFFSET = Math.floor((Date.now() - Date.UTC(2026, 0, 1)) / 60000)

function isoDate(dayOffset: number): string {
  return new Date(Date.UTC(2100, 0, 1) + dayOffset * 86400000).toISOString().slice(0, 10)
}

/** A five-day window unique to this run AND to this scenario's slot. */
function uniqueRange(slot: number): { start: string; end: string } {
  const startDay = RUN_OFFSET + slot * 10

  return { start: isoDate(startDay), end: isoDate(startDay + 4) }
}

/**
 * Windows for the saves that MUST BE REJECTED (duplicate name, reversed
 * dates). They are laid out far past every accepted slot so that, if a
 * rejection ever silently succeeds, the row it creates cannot be mistaken
 * for another scenario's and cannot block one either.
 */
function rejectedRange(slot: number): { start: string; end: string } {
  return uniqueRange(100 + slot)
}

/**
 * The window of the "Tanpa Stasiun" period, which lives in ANOTHER mill.
 *
 * Year 9600, past every date lane the laporan specs use (their windows reach
 * year ~9445 at the very most — see tests/support/period-lanes.ts). Those
 * specs each seed one period in this same mill, and the overlap rule no
 * longer looks at the station type, so a shared date would be refused. The
 * period is deleted inside its own test anyway; the lane is belt and braces.
 */
function emptyMillRange(): { start: string; end: string } {
  const startDay = RUN_OFFSET % 40000

  const iso = (offset: number) =>
    new Date(Date.UTC(9600, 0, 1) + offset * 86400000).toISOString().slice(0, 10)

  return { start: iso(startDay), end: iso(startDay + 4) }
}

function uniqueName(label: string): string {
  return `Periode ${label} ${RUN_OFFSET}`
}

async function gotoPeriods(page: Page): Promise<void> {
  await page.goto(PERIODS_PATH)
}

/** Creates a period through the UI and waits for its parent row to appear. */
async function createPeriod(
  page: Page,
  options: { name: string; start: string; end: string; businessUnit?: string },
): Promise<void> {
  await createPeriodViaUi(page, {
    businessUnit: options.businessUnit ?? BUSINESS_UNIT,
    name: options.name,
    start: options.start,
    end: options.end,
  })
}

/** One station row of an expanded period. */
function stationRow(page: Page, stationId: string): Locator {
  return page.locator(`[data-testid="period-station-row-${stationId}"]`)
}

/**
 * Opens the "Tutup Stasiun" dialog for one station row, taking the row from
 * Draft to Terbuka first if needed — the button exists on Terbuka rows only.
 */
async function askCloseStation(page: Page, periodId: string, stationId: string): Promise<void> {
  await openStation(page, periodId, stationId)
  await page.locator(`[data-testid="station-close-button-${stationId}"]`).click()
  await expect(page.locator('[data-testid="unverified-warning"]')).toBeVisible()
}

/** Deletes one period through the UI, from its parent row's inline confirm. */
async function deletePeriod(page: Page, name: string): Promise<void> {
  const periodId = await periodIdFor(page, name)

  await page.locator(`[data-testid="delete-button-${periodId}"]`).click()
  await page.locator('[data-testid="confirm-delete-button"]').click()

  await expect(periodRow(page, name)).toHaveCount(0)
}

test.describe('Kelola Periode Pelaporan', () => {
  // Pembersihan — lihat tests/support/periods.ts untuk alasan lengkapnya.
  // Tanpa ini, periode yang dibuat spec ini menumpuk di database dev
  // (~25 baris per run) sampai memenuhi halaman 1 daftar yang dipaginasi
  // 20 baris, lalu baris yang baru dibuat test terdorong ke halaman 2 dan
  // seluruh suite gagal di createPeriod() sebelum satu pun asersi jalan.
  // Terbukti terjadi pada 2026-09-23: 117 periode, 116 di antaranya residu.
  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage()

    try {
      await login(page, ADMIN, PASSWORD)
      const deleted = await deletePeriodsByPrefix(page, ['Periode '])
      console.log(`[cleanup] kelola-periode-pelaporan: %d periode dihapus`, deleted)
    } catch (error) {
      // Kegagalan membersihkan bukan kegagalan produk — jangan pernah
      // memerahkan suite karenanya.
      console.warn('[cleanup] kelola-periode-pelaporan: pembersihan gagal:', error)
    } finally {
      await page.close()
    }
  })

  // ── Scenario 13, and the scenario the split made possible ─────────────
  //
  // RUNS FIRST because its window is fixed in August 2026: it must cover
  // 2026-08-05, the date BrowserTestFixtureSeeder stamps on its unverified
  // Effluent Plant record. Those dates sort BELOW every other row this spec
  // creates, so the row is only reliably on page 1 while the list is still
  // short.
  test('dialog tutup: angka belum terverifikasi milik stasiun yang ditutup, bukan se-periode', async ({ page }) => {
    const name = uniqueName('Belum Terverifikasi')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)

    // One period, the whole mill — so BOTH station types below belong to the
    // same period and the same date range. Before the split there was one
    // figure per period and this comparison could not be made at all.
    await createPeriod(page, { name, start: '2026-08-01', end: '2026-08-15' })
    const periodId = await periodIdFor(page, name)
    await expandPeriod(page, periodId)

    const unverifiedId = await stationIdFor(page, periodId, UNVERIFIED_STATION_TYPE)
    const quietId = await stationIdFor(page, periodId, STATION_TYPE)

    // The station that HAS unverified records: a real, non-zero figure named
    // after that station type, its breakdown, and the explanation that
    // verification locks too.
    await askCloseStation(page, periodId, unverifiedId)
    await expect(page.locator('[data-testid="unverified-count"]')).toContainText(
      new RegExp(`[1-9]\\d* data ${UNVERIFIED_STATION_TYPE} belum terverifikasi`),
    )
    await expect(page.locator('[data-testid="unverified-breakdown"]')).toContainText(UNVERIFIED_STATION_TYPE)
    await expect(page.locator('[data-testid="unverified-warning"]')).toContainText('verifikasi ikut terkunci')
    await page.locator('[data-testid="cancel-close-button"]').click()
    await expect(page.locator('[data-testid="unverified-warning"]')).toHaveCount(0)

    // THE POINT OF THE WHOLE REVISION: the same period, the same range, a
    // different station — and a different figure. A period-wide count would
    // have reported the Effluent Plant record here too.
    await expandPeriod(page, periodId)
    await askCloseStation(page, periodId, quietId)
    // toContainText, and the leading (^|\s) instead of a bare ^: a RegExp
    // matcher is applied to the raw text node, which the blade indents over
    // two lines — toHaveText's whitespace normalisation only applies to the
    // string form. The guard that matters is the word boundary, so that a
    // "10" can never satisfy an assertion written for "0".
    await expect(page.locator('[data-testid="unverified-count"]')).toContainText(
      new RegExp(`(^|\\s)0 data ${STATION_TYPE} belum terverifikasi`),
    )
    await page.locator('[data-testid="cancel-close-button"]').click()

    // The figure never blocks the closure — closing proceeds.
    await expandPeriod(page, periodId)
    await askCloseStation(page, periodId, unverifiedId)
    await page.locator('[data-testid="confirm-close-button"]').click()
    await expect(page.locator(`[data-testid="station-status-badge-${unverifiedId}"]`)).toHaveText('Tertutup')

    // Clean up: this is one of the two scenarios that cannot use a unique
    // far-future window, so it must not leave a row behind that would block
    // a re-run. A closed station has to be reopened before the period can be
    // deleted at all.
    await expandPeriod(page, periodId)
    await page.locator(`[data-testid="station-reopen-button-${unverifiedId}"]`).click()
    await page.locator('[data-testid="confirm-reopen-button"]').click()
    await expect(page.locator(`[data-testid="station-status-badge-${unverifiedId}"]`)).toHaveText('Terbuka')
    await deletePeriod(page, name)
  })

  // Scenario 1: "Kelola Periode Pelaporan — success", plus the new
  // "membuat periode tanpa memilih stasiun" case: there IS no station
  // choice, and the row must come out with one station per station type the
  // mill actually has.
  test('menambah periode baru: baris muncul dengan badge Draft dan satu baris stasiun per inventaris mill', async ({ page }) => {
    const { start, end } = uniqueRange(1)
    const name = uniqueName('Sukses')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)

    await page.locator('[data-testid="add-period-button"]').click()
    await selectSearchable(page, 'business_unit_id', BUSINESS_UNIT)

    // The form tells the Admin what the period will cover, now that they
    // cannot choose it. THE EXPECTED COUNT IS READ FROM HERE, never
    // hardcoded: it is PeriodService::activeStationTypesForMill()'s own
    // answer for this mill, so the assertion below follows the fixture
    // instead of going stale behind it.
    const previewItems = page.locator('[data-testid="station-preview"] .kc-preview__list li')
    await expect(previewItems.first()).toBeVisible()
    const previewLabels = (await previewItems.allTextContents()).map((label) => label.trim())
    expect(previewLabels.length).toBeGreaterThan(1)
    expect(previewLabels).toContain(STATION_TYPE)
    expect(previewLabels).toContain(OTHER_STATION_TYPE)

    // There is no Jenis Stasiun control of any kind left to pick from.
    await expect(page.locator('#station_type')).toHaveCount(0)

    await page.locator('#name').fill(name)
    await page.locator('#start_date').fill(start)
    await page.locator('#end_date').fill(end)
    await page.locator('[data-testid="save-button"]').click()

    const row = periodRow(page, name)
    await expect(row).toBeVisible()
    await expect(row).toContainText(BUSINESS_UNIT)

    const periodId = await periodIdFor(page, name)
    // The parent row summarises; it never claims a station type of its own.
    await expect(page.locator(`[data-testid="station-summary-${periodId}"]`))
      .toHaveText(`${previewLabels.length} stasiun`)
    await expect(page.locator(`[data-testid="status-summary-${periodId}"]`)).toHaveText('Draft')
    await expect(row).not.toContainText(STATION_TYPE)

    // One station row per previewed station type, every one of them Draft
    // with both closure columns empty.
    await expandPeriod(page, periodId)
    const stationRows = page.locator(`[data-testid="period-stations-${periodId}"] tbody tr`)
    await expect(stationRows).toHaveCount(previewLabels.length)

    const stationId = await stationIdFor(page, periodId, STATION_TYPE)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Draft')
    // "Ditutup Oleh" / "Waktu Ditutup" render an em dash while the station
    // is not closed.
    await expect(stationRow(page, stationId).locator('td').nth(2)).toHaveText('—')
    await expect(stationRow(page, stationId).locator('td').nth(3)).toHaveText('—')

    // The modal closed and a success flash is shown.
    await expect(page.locator('.kcm-modal')).toHaveCount(0)
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()
  })

  // Scenario 2: "Rentang tanggal tumpang tindih"
  //
  // The rule is now PER MILL and takes no station type into account, which
  // is why no station choice appears in either save below.
  test('rentang tumpang tindih: modal tetap terbuka, pesan menyebut periode lawan, tabel tidak bertambah', async ({ page }) => {
    const { start, end } = uniqueRange(2)
    const existing = uniqueName('Bentrok Awal')
    const attempted = uniqueName('Bentrok Tambahan')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)

    await createPeriod(page, { name: existing, start, end })

    const rowsBefore = await page.locator('.kc-table__row').count()

    await page.locator('[data-testid="add-period-button"]').click()
    await selectSearchable(page, 'business_unit_id', BUSINESS_UNIT)
    await expect(page.locator('[data-testid="station-preview"] .kc-preview__list li').first()).toBeVisible()
    await page.locator('#name').fill(attempted)
    // Starts inside the existing window -> overlaps.
    await page.locator('#start_date').fill(isoDate(RUN_OFFSET + 2 * 10 + 2))
    await page.locator('#end_date').fill(isoDate(RUN_OFFSET + 2 * 10 + 8))
    await page.locator('[data-testid="save-button"]').click()

    // The modal stays open with the input intact, and the message names the
    // conflicting period.
    await expect(page.locator('.kcm-modal')).toBeVisible()
    await expect(page.locator('[data-testid="form-error"]')).toContainText(existing)
    await expect(page.locator('#name')).toHaveValue(attempted)
    await expect(periodRow(page, attempted)).toHaveCount(0)
    expect(await page.locator('.kc-table__row').count()).toBe(rowsBefore)
  })

  // Scenario 3: "Tanggal selesai lebih awal dari tanggal mulai"
  test('tanggal selesai mendahului tanggal mulai: error inline di bawah Tanggal Selesai', async ({ page }) => {
    const { start, end } = rejectedRange(3)
    const name = uniqueName('Terbalik')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)

    await page.locator('[data-testid="add-period-button"]').click()
    await selectSearchable(page, 'business_unit_id', BUSINESS_UNIT)
    await expect(page.locator('[data-testid="station-preview"] .kc-preview__list li').first()).toBeVisible()
    await page.locator('#name').fill(name)
    // Deliberately reversed.
    await page.locator('#start_date').fill(end)
    await page.locator('#end_date').fill(start)
    await page.locator('[data-testid="save-button"]').click()

    await expect(page.locator('.kc-form-field__error')).toContainText(/tidak boleh lebih awal/i)
    await expect(page.locator('.kcm-modal')).toBeVisible()
    await expect(periodRow(page, name)).toHaveCount(0)
  })

  // Scenario 4: "Nama periode sudah dipakai"
  test('nama periode duplikat: error di bawah field Nama dan tabel tetap satu baris bernama sama', async ({ page }) => {
    const { start, end } = uniqueRange(4)
    const name = uniqueName('Duplikat')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)

    await createPeriod(page, { name, start, end })

    await page.locator('[data-testid="add-period-button"]').click()
    await selectSearchable(page, 'business_unit_id', BUSINESS_UNIT)
    await expect(page.locator('[data-testid="station-preview"] .kc-preview__list li').first()).toBeVisible()
    await page.locator('#name').fill(name)
    // A different, non-overlapping window — the only rejected thing is the name.
    const other = rejectedRange(4)
    await page.locator('#start_date').fill(other.start)
    await page.locator('#end_date').fill(other.end)
    await page.locator('[data-testid="save-button"]').click()

    await expect(page.locator('.kc-form-field__error')).toContainText(/sudah digunakan/i)
    await expect(page.locator('.kcm-modal')).toBeVisible()
    await expect(periodRow(page, name)).toHaveCount(1)
  })

  // Scenario 5: "Mengubah atau menghapus periode yang sudah tertutup" —
  // rewritten for the per-station model, and extended with the other
  // direction the split made testable: once the closed station is reopened
  // the two actions come back.
  test('satu stasiun tertutup: Edit dan Hapus mati dengan penjelasan, hidup lagi setelah stasiun dibuka kembali', async ({ page }) => {
    const { start, end } = uniqueRange(5)
    const name = uniqueName('Terkunci')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const periodId = await periodIdFor(page, name)
    const stationId = await (async () => {
      const opened = await openStationRow(page, name, STATION_TYPE)

      return opened.stationId
    })()

    await askCloseStation(page, periodId, stationId)
    await page.locator('[data-testid="confirm-close-button"]').click()
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Tertutup')

    // DISABLED, not hidden: `is_immutable` is exactly the condition
    // PeriodService::update()/delete() refuse on (409
    // PERIOD_CLOSED_IMMUTABLE), and a period with 1 closed station out of 18
    // must show that editing is blocked rather than make the Admin hunt for
    // a vanished button. The 409 itself is asserted at API level in
    // backend/tests/Feature/Api/KelolaPeriodePelaporanTest.php.
    const editButton = page.locator(`[data-testid="edit-button-${periodId}"]`)
    const deleteButton = page.locator(`[data-testid="delete-button-${periodId}"]`)
    await expect(editButton).toBeDisabled()
    await expect(deleteButton).toBeDisabled()
    await expect(editButton).toHaveAttribute('title', /[Bb]uka kembali stasiun/)

    // The summary counts it, and the closed station offers only the reopen.
    await expect(page.locator(`[data-testid="station-summary-${periodId}"]`)).toContainText('1 tertutup')
    await expect(page.locator(`[data-testid="station-reopen-button-${stationId}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="station-close-button-${stationId}"]`)).toHaveCount(0)
    await expect(page.locator(`[data-testid="station-open-button-${stationId}"]`)).toHaveCount(0)

    // Reopen it and both period actions are available again — nothing about
    // the period itself was ever locked.
    await page.locator(`[data-testid="station-reopen-button-${stationId}"]`).click()
    await page.locator('[data-testid="confirm-reopen-button"]').click()
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
    await expect(editButton).toBeEnabled()
    await expect(deleteButton).toBeEnabled()

    // Name and range are untouched.
    await expect(periodRow(page, name)).toContainText(name)
  })

  // Scenario 6: "Belum ada Business Unit"
  //
  // SKIPPED, not deleted: the fixture environment ALWAYS has
  // "BU Browser Test" seeded (every other spec in this suite needs it), so
  // the empty-dropdown state cannot be produced in a browser without wiping
  // the database out from under the whole suite. The same scenario IS
  // asserted, for real, at component level
  // (backend/tests/Feature/Livewire/KelolaPeriodePelaporanTest.php) and at
  // API level (backend/tests/Feature/Api/KelolaPeriodePelaporanTest.php),
  // both of which can empty the table safely.
  test.skip('belum ada Business Unit: dropdown kosong dan pesan arahan membuat Business Unit dulu', async ({ page }) => {
    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)

    await page.locator('[data-testid="add-period-button"]').click()
    await expect(page.locator('#business_unit_id-listbox').getByRole('option')).toHaveCount(0)
    await expect(page.locator('.ss-combobox__empty-hint')).toContainText('Belum ada Business Unit')
  })

  // Scenario 7: "Periode sudah dihapus pengguna lain"
  test('periode dihapus pengguna lain saat form terbuka: pesan ramah, tabel ter-refresh, tanpa error 500', async ({ page, browser }) => {
    const { start, end } = uniqueRange(7)
    const name = uniqueName('Balapan Hapus')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const periodId = await periodIdFor(page, name)

    // Admin 1 opens the edit form...
    await page.locator(`[data-testid="edit-button-${periodId}"]`).click()
    await expect(page.locator('.kcm-modal')).toBeVisible()

    // ...while Admin 2, in a separate browser context, deletes the row.
    const otherContext = await browser.newContext()
    const otherPage = await otherContext.newPage()
    await login(otherPage, SECOND_ADMIN, PASSWORD)
    await gotoPeriods(otherPage)
    await deletePeriod(otherPage, name)
    await otherContext.close()

    // Admin 1 saves the now-orphaned form.
    await page.locator('#name').fill(`${name} Revisi`)
    await page.locator('[data-testid="save-button"]').click()

    await expect(page.locator('[data-testid="form-error"]')).toContainText(/tidak ditemukan/i)
    await expect(page.locator('body')).not.toContainText('500')
    await expect(periodRow(page, name)).toHaveCount(0)
  })

  // Scenario 8: "pengguna non-Admin mencoba mengakses"
  test('akses ditolak: pengguna non-Admin tidak melihat data periode sama sekali', async ({ page }) => {
    await login(page, NON_ADMIN, PASSWORD)
    await page.goto(PERIODS_PATH)

    // EnsureRole::forbidden() -> abort(403), Laravel's default HTML error page.
    await expect(page.locator('body')).toContainText(/403/)
    await expect(page.locator('[data-testid="period-table"]')).toHaveCount(0)
  })

  // Scenario 9: "Business Unit tidak diisi"
  test('submit tanpa memilih Business Unit: error inline dan tabel tidak bertambah', async ({ page }) => {
    const { start, end } = rejectedRange(9)
    const name = uniqueName('Tanpa BU')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)

    await page.locator('[data-testid="add-period-button"]').click()
    // Deliberately no mill: the station preview cannot even be computed
    // without one, so the form says so instead of listing stations.
    await expect(page.locator('[data-testid="station-preview"]'))
      .toContainText('Pilih Business Unit')
    await page.locator('#name').fill(name)
    await page.locator('#start_date').fill(start)
    await page.locator('#end_date').fill(end)
    await page.locator('[data-testid="save-button"]').click()

    await expect(page.locator('.kc-form-field__error')).toContainText(/wajib dipilih/i)
    await expect(page.locator('.kcm-modal')).toBeVisible()
    // The rest of the form survived the rejection.
    await expect(page.locator('#name')).toHaveValue(name)
    await expect(periodRow(page, name)).toHaveCount(0)
  })

  // Scenario 10: "Tutup & Buka Kembali Periode Pelaporan — success"
  test('tutup stasiun: dialog menyebut stasiunnya, setelah konfirmasi badge stasiun jadi Tertutup', async ({ page }) => {
    const { start, end } = uniqueRange(10)
    const name = uniqueName('Tutup Sukses')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const { periodId, stationId } = await openStationRow(page, name, STATION_TYPE)
    await askCloseStation(page, periodId, stationId)

    await expect(page.locator('.kcm-modal')).toBeVisible()
    await expect(page.locator('[data-testid="unverified-count"]')).toContainText(
      `data ${STATION_TYPE} belum terverifikasi`,
    )

    await page.locator('[data-testid="confirm-close-button"]').click()

    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Tertutup')
    // Ditutup Oleh names the Admin who confirmed it; the parent row does not
    // carry a closer at all any more.
    await expect(stationRow(page, stationId).locator('td').nth(2)).toHaveText(ADMIN_NAME)
    await expect(page.locator('.kcm-modal')).toHaveCount(0)
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()
  })

  // Scenario 11: "buka kembali periode yang sudah tertutup"
  test('buka kembali stasiun: badge jadi Terbuka, kolom penutupan kosong, tombol Tutup Stasiun kembali', async ({ page }) => {
    const { start, end } = uniqueRange(11)
    const name = uniqueName('Buka Kembali')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const { periodId, stationId } = await openStationRow(page, name, STATION_TYPE)
    await askCloseStation(page, periodId, stationId)
    await page.locator('[data-testid="confirm-close-button"]').click()
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Tertutup')

    await page.locator(`[data-testid="station-reopen-button-${stationId}"]`).click()
    await page.locator('[data-testid="confirm-reopen-button"]').click()

    // reopen() lands on Terbuka, never back on Draft, and drops the record of
    // who closed it.
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
    await expect(stationRow(page, stationId).locator('td').nth(2)).toHaveText('—')
    await expect(stationRow(page, stationId).locator('td').nth(3)).toHaveText('—')
    await expect(page.locator(`[data-testid="station-close-button-${stationId}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="edit-button-${periodId}"]`)).toBeEnabled()
    await expect(page.locator(`[data-testid="delete-button-${periodId}"]`)).toBeEnabled()
  })

  // Scenario 12: "Admin membatalkan penutupan"
  test('membatalkan dialog Tutup Stasiun: badge stasiun tetap dan kolom Ditutup Oleh tetap kosong', async ({ page }) => {
    const { start, end } = uniqueRange(12)
    const name = uniqueName('Batal Tutup')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const { periodId, stationId } = await openStationRow(page, name, STATION_TYPE)
    await askCloseStation(page, periodId, stationId)

    await page.locator('[data-testid="cancel-close-button"]').click()

    await expect(page.locator('[data-testid="unverified-warning"]')).toHaveCount(0)
    await expandPeriod(page, periodId)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
    await expect(stationRow(page, stationId).locator('td').nth(2)).toHaveText('—')
    await expect(page.locator(`[data-testid="status-summary-${periodId}"]`)).toHaveText('Campuran')
  })

  // Scenario 16: "menutup periode yang sudah tertutup"
  test('baris stasiun Tertutup: tombol Tutup Stasiun tidak terlihat, Buka Kembali dapat diklik', async ({ page }) => {
    const { start, end } = uniqueRange(13)
    const name = uniqueName('Sudah Tertutup')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const { periodId, stationId } = await openStationRow(page, name, STATION_TYPE)
    await askCloseStation(page, periodId, stationId)
    await page.locator('[data-testid="confirm-close-button"]').click()

    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Tertutup')
    await expect(page.locator(`[data-testid="station-close-button-${stationId}"]`)).toHaveCount(0)
    await expect(page.locator(`[data-testid="station-reopen-button-${stationId}"]`)).toBeEnabled()
  })

  // Scenario 17: "dua Admin menutup periode bersamaan" — now two Admins
  // closing the same STATION.
  test('dua Admin menutup stasiun yang sama: Admin kedua diberi tahu, penutup pertama tetap tercatat', async ({ page, browser }) => {
    const { start, end } = uniqueRange(14)
    const name = uniqueName('Balapan Tutup')

    // Admin B prepares the row, takes the station to Terbuka and keeps the
    // list open (not reloaded) with the station rows expanded.
    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })
    const { periodId, stationId } = await openStationRow(page, name, STATION_TYPE)
    await openStation(page, periodId, stationId)

    // Admin A closes the same station in another browser context.
    const otherContext = await browser.newContext()
    const otherPage = await otherContext.newPage()
    await login(otherPage, SECOND_ADMIN, PASSWORD)
    await gotoPeriods(otherPage)
    await expandPeriod(otherPage, periodId)
    await otherPage.locator(`[data-testid="station-close-button-${stationId}"]`).click()
    await otherPage.locator('[data-testid="confirm-close-button"]').click()
    await expect(otherPage.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Tertutup')
    await expect(stationRow(otherPage, stationId)).toContainText(SECOND_ADMIN_NAME)

    // Admin B, without reloading, confirms the closure of the same station.
    await page.locator(`[data-testid="station-close-button-${stationId}"]`).click()
    await page.locator('[data-testid="confirm-close-button"]').click()

    // They are told it is already closed, by whom and when — and the list
    // now shows Admin A as the closer in BOTH browsers.
    await expect(page.locator('[data-testid="close-error"]')).toContainText(SECOND_ADMIN_NAME)
    await expandPeriod(page, periodId)
    await expect(stationRow(page, stationId)).toContainText(SECOND_ADMIN_NAME)

    await otherPage.reload()
    await expandPeriod(otherPage, periodId)
    await expect(stationRow(otherPage, stationId)).toContainText(SECOND_ADMIN_NAME)
    await otherContext.close()
  })

  // Scenario 18: "pengguna selain Admin menutup periode"
  test('akses ditolak: non-Admin tidak mendapat tombol Tutup/Buka Kembali dan status stasiun tidak berubah', async ({ page, browser }) => {
    const { start, end } = uniqueRange(15)
    const name = uniqueName('Non Admin')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })
    const { periodId, stationId } = await openStationRow(page, name, STATION_TYPE)

    // The non-Admin never reaches the screen, so no station action is offered.
    const otherContext = await browser.newContext()
    const otherPage = await otherContext.newPage()
    await login(otherPage, NON_ADMIN, PASSWORD)
    await otherPage.goto(PERIODS_PATH)
    await expect(otherPage.locator('body')).toContainText(/403/)
    await expect(otherPage.locator('[data-testid^="station-close-button-"]')).toHaveCount(0)
    await expect(otherPage.locator('[data-testid^="station-reopen-button-"]')).toHaveCount(0)
    await expect(otherPage.locator('[data-testid^="station-open-button-"]')).toHaveCount(0)
    await otherContext.close()

    // Re-checked as Admin: the station is exactly as it was.
    await page.reload()
    await expandPeriod(page, periodId)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Draft')
    await expect(stationRow(page, stationId).locator('td').nth(2)).toHaveText('—')
  })

  // ── usecase-144 (Buka Stasiun) — scenarios 21–28 ──────────────────────
  //
  // SELECTOR NOTE: the bare `open-period-button` testid these scenarios used
  // to share is gone. Every action now carries its own
  // `period_stations` id (`station-open-button-{id}`), so the old warning
  // about scoping a bare testid to a row no longer applies — but the button
  // is only in the DOM while its period's station rows are expanded.

  // Scenario 21: "Buka Periode Pelaporan — sukses"
  test('buka stasiun draft: dialog konfirmasi lalu badge stasiun jadi Terbuka dan tombol Buka Stasiun hilang', async ({ page }) => {
    const { start, end } = uniqueRange(16)
    const name = uniqueName('Buka Sukses')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const { periodId, stationId } = await openStationRow(page, name, STATION_TYPE)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Draft')

    await page.locator(`[data-testid="station-open-button-${stationId}"]`).click()

    const dialog = page.locator('[data-testid="open-period-dialog"]')
    await expect(dialog).toBeVisible()
    await expect(dialog).toContainText('tidak dapat dikembalikan ke Draft')
    // The dialog is about ONE station, and says so.
    await expect(dialog).toContainText(STATION_TYPE)

    await page.locator('[data-testid="confirm-open-period"]').click()

    // The dialog closes and the station is now Terbuka.
    await expect(page.locator('[data-testid="open-period-dialog"]')).toHaveCount(0)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
    await expect(page.locator(`[data-testid="station-open-button-${stationId}"]`)).toHaveCount(0)
    // Opening is not closing: the closure columns stay empty and the period's
    // edit / delete actions remain available.
    await expect(stationRow(page, stationId).locator('td').nth(2)).toHaveText('—')
    await expect(stationRow(page, stationId).locator('td').nth(3)).toHaveText('—')
    await expect(page.locator(`[data-testid="edit-button-${periodId}"]`)).toBeEnabled()
    await expect(page.locator(`[data-testid="delete-button-${periodId}"]`)).toBeEnabled()
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()
  })

  // Alternative flow "Admin membatalkan pembukaan" — no bdd_scenario of its
  // own, but the cancel path must not go untested.
  test('membatalkan dialog Buka Stasiun: badge tetap Draft dan tombol Buka Stasiun masih ada', async ({ page }) => {
    const { start, end } = uniqueRange(17)
    const name = uniqueName('Batal Buka')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const { periodId, stationId } = await openStationRow(page, name, STATION_TYPE)
    await page.locator(`[data-testid="station-open-button-${stationId}"]`).click()
    await expect(page.locator('[data-testid="open-period-dialog"]')).toBeVisible()

    await page.locator('[data-testid="cancel-open-period"]').click()

    await expect(page.locator('[data-testid="open-period-dialog"]')).toHaveCount(0)
    await expandPeriod(page, periodId)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Draft')
    await expect(page.locator(`[data-testid="station-open-button-${stationId}"]`)).toBeVisible()
  })

  // Scenario 22: "Periode sudah terbuka"
  test('baris stasiun Terbuka: tombol Buka Stasiun tidak dirender sama sekali', async ({ page }) => {
    const { start, end } = uniqueRange(18)
    const name = uniqueName('Sudah Terbuka')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const { periodId, stationId } = await openStationRow(page, name, STATION_TYPE)
    await openStation(page, periodId, stationId)

    // Still absent after a full reload — the button is driven by the station
    // row's status, not by client-side state. (The forced-action message a
    // stale list produces is asserted in the concurrent-opening scenario
    // below.)
    await page.reload()
    await expandPeriod(page, periodId)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
    await expect(page.locator(`[data-testid="station-open-button-${stationId}"]`)).toHaveCount(0)
    await expect(page.locator(`[data-testid="station-close-button-${stationId}"]`)).toBeVisible()
  })

  // Scenario 23: "Periode sudah tertutup"
  test('baris stasiun Tertutup: hanya Buka Kembali yang ditawarkan, bukan Buka Stasiun', async ({ page }) => {
    const { start, end } = uniqueRange(19)
    const name = uniqueName('Buka Tertutup')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const { periodId, stationId } = await openStationRow(page, name, STATION_TYPE)
    await askCloseStation(page, periodId, stationId)
    await page.locator('[data-testid="confirm-close-button"]').click()

    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Tertutup')
    // "Buka Stasiun" and "Buka Kembali" are two different actions; a closed
    // station must offer only the latter.
    await expect(page.locator(`[data-testid="station-open-button-${stationId}"]`)).toHaveCount(0)
    await expect(page.locator(`[data-testid="station-reopen-button-${stationId}"]`)).toBeEnabled()
    // (The 409 message that names "Buka Kembali Periode" for a forced call
    // is asserted at API and component level — it cannot be produced from a
    // browser, since the button is never rendered on a closed station.)
  })

  // Scenario 24: "Periode tidak ditemukan"
  test('periode dihapus pengguna lain: konfirmasi Buka Stasiun memberi pesan tidak ditemukan tanpa error 500', async ({ page, browser }) => {
    const { start, end } = uniqueRange(20)
    const name = uniqueName('Buka Sudah Dihapus')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const { stationId } = await openStationRow(page, name, STATION_TYPE)

    // Admin 1 opens the confirmation dialog FIRST, so the station row still
    // exists when the snapshot is taken...
    await page.locator(`[data-testid="station-open-button-${stationId}"]`).click()
    await expect(page.locator('[data-testid="open-period-dialog"]')).toBeVisible()

    // ...then Admin 2, in another context, deletes the whole period — which
    // takes its station rows with it.
    const otherContext = await browser.newContext()
    const otherPage = await otherContext.newPage()
    await login(otherPage, SECOND_ADMIN, PASSWORD)
    await gotoPeriods(otherPage)
    await deletePeriod(otherPage, name)
    await otherContext.close()

    await page.locator('[data-testid="confirm-open-period"]').click()

    await expect(page.locator('[data-testid="close-error"]')).toContainText(/tidak ditemukan/i)
    await expect(page.locator('body')).not.toContainText('500')
    await expect(periodRow(page, name)).toHaveCount(0)

    await page.reload()
    await expect(periodRow(page, name)).toHaveCount(0)
  })

  // Scenario 25: "Dua Admin membuka bersamaan"
  test('dua Admin membuka stasiun yang sama: hanya yang pertama mengubah status, yang kedua diberi tahu sudah terbuka', async ({ page, browser }) => {
    const { start, end } = uniqueRange(21)
    const name = uniqueName('Balapan Buka')

    // Admin B prepares the row and keeps the (now stale) list open.
    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })
    const { periodId, stationId } = await openStationRow(page, name, STATION_TYPE)

    // Admin A opens the same station in another browser context, first.
    const otherContext = await browser.newContext()
    const otherPage = await otherContext.newPage()
    await login(otherPage, SECOND_ADMIN, PASSWORD)
    await gotoPeriods(otherPage)
    await expandPeriod(otherPage, periodId)
    await otherPage.locator(`[data-testid="station-open-button-${stationId}"]`).click()
    await otherPage.locator('[data-testid="confirm-open-period"]').click()
    await expect(otherPage.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')

    // Admin B, without reloading, confirms the same opening.
    await page.locator(`[data-testid="station-open-button-${stationId}"]`).click()
    await page.locator('[data-testid="confirm-open-period"]').click()

    // Told it is already open — no second change, no silent overwrite.
    await expect(page.locator('[data-testid="close-error"]')).toContainText(/sudah terbuka/i)
    await expect(page.locator('[data-testid="success-message"]')).toHaveCount(0)
    await expandPeriod(page, periodId)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')

    await page.reload()
    await expandPeriod(page, periodId)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
    await expect(page.locator(`[data-testid="station-open-button-${stationId}"]`)).toHaveCount(0)

    await otherContext.close()
  })

  // Scenario 26: "Bukan Admin mencoba membuka periode"
  test('akses ditolak: non-Admin tidak pernah melihat tombol Buka Stasiun dan status tetap Draft', async ({ page, browser }) => {
    const { start, end } = uniqueRange(22)
    const name = uniqueName('Buka Non Admin')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })
    const { periodId, stationId } = await openStationRow(page, name, STATION_TYPE)

    const otherContext = await browser.newContext()
    const otherPage = await otherContext.newPage()
    await login(otherPage, NON_ADMIN, PASSWORD)
    await otherPage.goto(PERIODS_PATH)
    await expect(otherPage.locator('body')).toContainText(/403/)
    await expect(otherPage.locator('[data-testid^="station-open-button-"]')).toHaveCount(0)
    await otherContext.close()

    // Re-checked as Admin: the station is exactly as it was.
    await page.reload()
    await expandPeriod(page, periodId)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Draft')
    await expect(page.locator(`[data-testid="station-open-button-${stationId}"]`)).toBeVisible()
  })

  // Scenario 27: "status Terbuka tidak dapat dikembalikan ke Draft"
  test('form edit periode dengan stasiun Terbuka: tidak ada kontrol status maupun jenis stasiun', async ({ page }) => {
    const { start, end } = uniqueRange(23)
    const name = uniqueName('Tanpa Mundur')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const { periodId, stationId } = await openStationRow(page, name, STATION_TYPE)
    await openStation(page, periodId, stationId)

    await page.locator(`[data-testid="edit-button-${periodId}"]`).click()

    const modal = page.locator('.kcm-modal')
    await expect(modal).toBeVisible()
    // The form has no status control of any kind — there is simply no way
    // back to Draft from the UI — and no station-type control either, since
    // a period's coverage is derived from the mill rather than chosen.
    await expect(modal.locator('#status')).toHaveCount(0)
    await expect(modal.locator('#station_type')).toHaveCount(0)
    await expect(modal).not.toContainText('Draft')
    // In Edit mode the preview says what saving WILL and will NOT do; a mill
    // whose every station type already has a row shows no "Baru" badge.
    await expect(page.locator('[data-testid="station-preview"]')).toContainText('tidak diubah maupun dihapus')
    await expect(page.locator('[data-testid^="station-preview-new-"]')).toHaveCount(0)

    await page.locator('[data-testid="save-button"]').click()
    await expect(page.locator('.kcm-modal')).toHaveCount(0)

    await expandPeriod(page, periodId)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
    await page.reload()
    await expandPeriod(page, periodId)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
  })

  // Scenario 28: "periode Terbuka tetap dapat diubah dan dihapus"
  test('periode dengan stasiun Terbuka tetap dapat diubah dan dihapus, tanpa pesan terkunci', async ({ page }) => {
    const { start, end } = uniqueRange(24)
    const name = uniqueName('Terbuka Bebas')
    const renamed = `${name} Revisi`

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const { periodId, stationId } = await openStationRow(page, name, STATION_TYPE)
    await openStation(page, periodId, stationId)

    // Edit is still available while a station is merely open.
    await page.locator(`[data-testid="edit-button-${periodId}"]`).click()
    await page.locator('#name').fill(renamed)
    await page.locator('[data-testid="save-button"]').click()

    await expect(periodRow(page, renamed)).toBeVisible()
    await expandPeriod(page, periodId)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
    await expect(page.locator('[data-testid="delete-error"]')).toHaveCount(0)

    // And so is delete.
    await deletePeriod(page, renamed)
    await expect(page.locator('[data-testid="delete-error"]')).toHaveCount(0)
  })

  // ── Skenario yang baru mungkin ada setelah pemisahan ──────────────────
  //
  // Inti seluruh revisi ini: stasiun tidak selesai serentak. Sebelum
  // 2026-09-26 tidak satu pun dari tiga skenario berikut dapat dituliskan,
  // karena satu periode hanya punya satu status.

  // Sterilizer tertutup sementara Clarification masih terbuka.
  test('stasiun tidak serentak: satu tertutup satu terbuka, baris induk berbunyi Campuran', async ({ page }) => {
    const { start, end } = uniqueRange(25)
    const name = uniqueName('Campuran')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const periodId = await periodIdFor(page, name)
    await expandPeriod(page, periodId)
    const closedId = await stationIdFor(page, periodId, STATION_TYPE)
    const openId = await stationIdFor(page, periodId, OTHER_STATION_TYPE)

    // Everything starts identical: same period, same range, same status.
    await expect(page.locator(`[data-testid="station-status-badge-${closedId}"]`)).toHaveText('Draft')
    await expect(page.locator(`[data-testid="station-status-badge-${openId}"]`)).toHaveText('Draft')

    // Take the second station to Terbuka and leave it there.
    await openStation(page, periodId, openId)

    // Close the first one only.
    await askCloseStation(page, periodId, closedId)
    await page.locator('[data-testid="confirm-close-button"]').click()
    await expect(page.locator(`[data-testid="station-status-badge-${closedId}"]`)).toHaveText('Tertutup')

    // CLOSING ONE STATION CHANGES NOTHING ABOUT THE OTHER: same row, same
    // period, still Terbuka, still with empty closure columns and still
    // offering its own Tutup Stasiun.
    await expect(page.locator(`[data-testid="station-status-badge-${openId}"]`)).toHaveText('Terbuka')
    await expect(stationRow(page, openId).locator('td').nth(2)).toHaveText('—')
    await expect(stationRow(page, openId).locator('td').nth(3)).toHaveText('—')
    await expect(page.locator(`[data-testid="station-close-button-${openId}"]`)).toBeVisible()
    // ...and the closed one names its closer.
    await expect(stationRow(page, closedId).locator('td').nth(2)).toHaveText(ADMIN_NAME)

    // The parent row summarises the disagreement rather than picking a side.
    await expect(page.locator(`[data-testid="status-summary-${periodId}"]`)).toHaveText('Campuran')
    await expect(page.locator(`[data-testid="station-summary-${periodId}"]`)).toContainText('1 tertutup')

    // Collapsing and reopening the row shows the same two statuses — the
    // difference lives in the data, not in a rendering accident.
    await page.locator(`[data-testid="expand-button-${periodId}"]`).click()
    await expect(page.locator(`[data-testid="period-stations-${periodId}"]`)).toHaveCount(0)
    await expandPeriod(page, periodId)
    await expect(page.locator(`[data-testid="station-status-badge-${closedId}"]`)).toHaveText('Tertutup')
    await expect(page.locator(`[data-testid="station-status-badge-${openId}"]`)).toHaveText('Terbuka')
  })

  // Filter "Status Stasiun" — "punya MINIMAL SATU stasiun berstatus ini",
  // so one period can legitimately appear under two different values.
  test('filter Status Stasiun: periode dengan stasiun tertutup DAN draft muncul di kedua filter', async ({ page }) => {
    const { start, end } = uniqueRange(26)
    const name = uniqueName('Filter Campuran')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const periodId = await periodIdFor(page, name)
    const { stationId } = await openStationRow(page, name, STATION_TYPE)
    await askCloseStation(page, periodId, stationId)
    await page.locator('[data-testid="confirm-close-button"]').click()
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Tertutup')

    // 1 closed, 1 open (the one that had to be opened before closing) and 16
    // still draft — so all three filter values must match this one period.
    for (const label of ['Tertutup', 'Terbuka', 'Draft']) {
      await selectSearchable(page, 'filterStatus', label)
      await expect(
        page.locator(`[data-testid="period-row-${periodId}"]`),
        `filter "${label}" seharusnya tetap memuat periode dengan minimal satu stasiun berstatus itu`,
      ).toHaveCount(1)
    }
  })

  // status_summary 'empty' — a mill with no active station at all.
  test('mill tanpa stasiun aktif: periode tersimpan dengan badge Tanpa Stasiun dan tetap dapat diubah', async ({ page }) => {
    const { start, end } = emptyMillRange()
    const name = uniqueName('Tanpa Stasiun')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)

    await page.locator('[data-testid="add-period-button"]').click()
    await selectSearchable(page, 'business_unit_id', EMPTY_MILL)
    // The form warns instead of listing stations, and says the period is
    // still saved.
    await expect(page.locator('[data-testid="station-preview-empty"]'))
      .toContainText('belum punya stasiun aktif')
    await page.locator('#name').fill(name)
    await page.locator('#start_date').fill(start)
    await page.locator('#end_date').fill(end)
    await page.locator('[data-testid="save-button"]').click()

    const periodId = await periodIdFor(page, name)
    await expect(page.locator(`[data-testid="status-summary-${periodId}"]`)).toHaveText('Tanpa Stasiun')
    await expect(page.locator(`[data-testid="station-summary-${periodId}"]`)).toHaveText('Belum ada stasiun')

    // Nothing can be closed, so nothing is immutable: both period actions
    // stay live.
    await expect(page.locator(`[data-testid="edit-button-${periodId}"]`)).toBeEnabled()
    await expect(page.locator(`[data-testid="delete-button-${periodId}"]`)).toBeEnabled()

    // Expanding it explains the state rather than showing an empty table.
    await expandPeriod(page, periodId)
    await expect(page.locator(`[data-testid="period-stations-empty-${periodId}"]`))
      .toContainText('belum memiliki stasiun aktif')
    await expect(page.locator(`[data-testid="period-stations-${periodId}"]`)).toHaveCount(0)

    // Clean up in-test: this period lives in ANOTHER mill, and leaving it
    // there would sit in the date space the laporan specs use for their own
    // period in that same mill.
    await deletePeriod(page, name)
  })
})
