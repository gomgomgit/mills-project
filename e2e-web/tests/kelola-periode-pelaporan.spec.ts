/**
 * Kelola Periode Pelaporan (Browser/Playwright) —
 * screen-128--kelola-periode-pelaporan / usecase-128 (CRUD).
 *
 * DAFTAR PERIODE, DAN HANYA DAFTARNYA. Sejak 2026-09-27 daftar stasiun
 * sebuah periode dan SETIAP aksi per stasiun (usecase-140 tutup & buka
 * kembali, usecase-144 buka stasiun draft) hidup di layar tersendiri,
 * screen-142--detail-periode-pelaporan — dan pengujiannya PINDAH ke
 * tests/detail-periode-pelaporan.spec.ts apa adanya, bukan dihapus.
 * Scenarios 10-13, 16-18 dan 21-26 karena itu tidak lagi ada di berkas ini.
 *
 * Yang tersisa di sini: CRUD periode (scenarios 1-9), filter, ringkasan baris
 * induk, dan dua skenario yang memakai stasiun hanya sebagai LATAR (Edit /
 * Hapus mati saat `is_immutable`, filter Status Stasiun) — keadaan itu
 * disiapkan lewat layar detail, tempat aksinya kini berada, bukan dengan
 * melemahkan asersinya.
 *
 * Scenarios 14, 15, 19 dan 20 membawa browser_test kosong — mereka skenario
 * sinkronisasi mobile / penguncian record dan hidup, ter-skip, di
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
 *  3. BARIS STASIUN TIDAK ADA DI LAYAR INI (sejak 2026-09-27). Accordion
 *     yang dulu membawanya dicabut; nama periode kini sebuah <a href>
 *     (`period-link-{periodId}`, wire:navigate) menuju
 *     `/master-data/periods/{id}`. Mencari tombol per-stasiun di daftar
 *     menghasilkan timeout yang menyesatkan — helper openStationRow() /
 *     closeStation() di tests/support/period-screen.ts yang mengurus
 *     navigasinya, dan setiap testid per-stasiun membawa id
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
 *                        Admin of the concurrent-delete scenario
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

import { test, expect, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { deletePeriodsByPrefix } from './support/periods'
import {
  closeStation,
  createPeriodViaUi,
  gotoPeriodDetail,
  openStation,
  openStationRow,
  periodDetailPath,
  periodIdFor,
  periodRow,
  selectSearchable,
} from './support/period-screen'

const PERIODS_PATH = '/master-data/periods'

const ADMIN = 'butest-admin01'
const SECOND_ADMIN = 'pltest-admin01'
const NON_ADMIN = 'butest-nonadmin01'

const BUSINESS_UNIT = 'BU Browser Test'

/** The station row nearly every scenario acts on. */
const STATION_TYPE = 'Sterilizer'

/** A second station row, to prove the two never move together. */
const OTHER_STATION_TYPE = 'Clarification'

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

/**
 * PENYIAPAN KEADAAN STASIUN LEWAT LAYAR DETAIL, LALU KEMBALI KE DAFTAR.
 *
 * Aksi stasiun tidak ada lagi di layar ini. Beberapa skenario DAFTAR tetap
 * membutuhkan keadaan yang hanya bisa dihasilkan oleh aksi itu — `is_immutable`
 * (Edit/Hapus mati), ringkasan "Campuran", dan filter Status Stasiun. Jalan
 * yang benar adalah menyiapkannya di layar tempat aksinya kini berada, bukan
 * melonggarkan asersinya di sini.
 *
 * Kedua helper di bawah mengembalikan KEDUA id: id PERIODE untuk aksi periode
 * di daftar, dan id `period_stations` untuk aksi stasiun di layar detail.
 * Keduanya tidak pernah saling menggantikan.
 */
async function closeStationThenBackToList(
  page: Page,
  name: string,
  stationLabel: string = STATION_TYPE,
): Promise<{ periodId: string; stationId: string }> {
  // periodIdFor dulu, selagi masih di daftar — closeStation() berpindah
  // halaman ke layar detail dan tidak kembali sendiri.
  const periodId = await periodIdFor(page, name)
  const stationId = await closeStation(page, name, stationLabel)
  await gotoPeriods(page)

  return { periodId, stationId }
}

async function openStationThenBackToList(
  page: Page,
  name: string,
  stationLabel: string = STATION_TYPE,
): Promise<{ periodId: string; stationId: string }> {
  const { periodId, stationId } = await openStationRow(page, name, stationLabel)
  await openStation(page, periodId, stationId)
  await gotoPeriods(page)

  return { periodId, stationId }
}

/** Membuka kembali satu stasiun tertutup lewat layar detail, lalu kembali. */
async function reopenStationThenBackToList(page: Page, periodId: string, stationId: string): Promise<void> {
  await gotoPeriodDetail(page, periodId)
  await page.locator(`[data-testid="station-reopen-button-${stationId}"]`).click()
  await page.locator('[data-testid="confirm-reopen-button"]').click()
  await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
  await gotoPeriods(page)
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

  // Scenario 1: "Kelola Periode Pelaporan — success", plus the new
  // "membuat periode tanpa memilih stasiun" case: there IS no station
  // choice, and the row must come out with one station per station type the
  // mill actually has.
  test('menambah periode baru: baris muncul dengan badge Draft dan ringkasan satu stasiun per inventaris mill', async ({ page }) => {
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

    // BARIS STASIUNNYA SENDIRI TIDAK ADA DI SINI. Satu baris per jenis
    // stasiun yang dipratinjau — beserta urutannya, badge Draft-nya dan kolom
    // penutupannya yang kosong — diasersi di layar tempatnya kini hidup,
    // tests/detail-periode-pelaporan.spec.ts. Daftar hanya boleh meringkas,
    // dan ringkasan itu ("N stasiun") sudah dicocokkan ke pratinjau di atas.
    await expect(page.locator(`[data-testid="period-stations-${periodId}"]`)).toHaveCount(0)
    await expect(page.locator('[data-testid^="period-station-row-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="station-status-badge-"]')).toHaveCount(0)

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
  // versi DAFTAR. Yang diuji di sini dua tombol pada baris induk; stasiun
  // yang menguncinya hanya latar, dan disiapkan lewat layar detail tempat
  // aksinya kini berada. Pasangan asersinya pada layar detail ada di
  // tests/detail-periode-pelaporan.spec.ts.
  test('satu stasiun tertutup: Edit dan Hapus di daftar mati dengan penjelasan, hidup lagi setelah stasiun dibuka kembali', async ({ page }) => {
    const { start, end } = uniqueRange(5)
    const name = uniqueName('Terkunci')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const editButton = page.locator(`[data-testid="edit-button-${await periodIdFor(page, name)}"]`)

    // Berangkat dari keadaan hidup, supaya "mati" sesudahnya benar-benar
    // sebuah perubahan dan bukan keadaan awal.
    await expect(editButton).toBeEnabled()

    const { periodId, stationId } = await closeStationThenBackToList(page, name)
    const deleteButton = page.locator(`[data-testid="delete-button-${periodId}"]`)

    // DISABLED, not hidden: `is_immutable` is exactly the condition
    // PeriodService::update()/delete() refuse on (409
    // PERIOD_CLOSED_IMMUTABLE), and a period with 1 closed station out of 18
    // must show that editing is blocked rather than make the Admin hunt for
    // a vanished button. The 409 itself is asserted at API level in
    // backend/tests/Feature/Api/KelolaPeriodePelaporanTest.php.
    await expect(editButton).toBeDisabled()
    await expect(deleteButton).toBeDisabled()
    await expect(editButton).toHaveAttribute('title', /[Bb]uka kembali stasiun/)
    await expect(deleteButton).toHaveAttribute('title', /[Bb]uka kembali stasiun/)

    // Baris induk menghitungnya, dan meringkas perselisihannya.
    await expect(page.locator(`[data-testid="station-summary-${periodId}"]`)).toContainText('1 tertutup')
    await expect(page.locator(`[data-testid="status-summary-${periodId}"]`)).toHaveText('Campuran')

    // Reopen it and both period actions are available again — nothing about
    // the period itself was ever locked.
    await reopenStationThenBackToList(page, periodId, stationId)
    await expect(editButton).toBeEnabled()
    await expect(deleteButton).toBeEnabled()
    await expect(editButton).not.toHaveAttribute('title', /[Bb]uka kembali stasiun/)

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

  // Scenario 27: "status Terbuka tidak dapat dikembalikan ke Draft"
  test('form edit periode dengan stasiun Terbuka: tidak ada kontrol status maupun jenis stasiun', async ({ page }) => {
    const { start, end } = uniqueRange(23)
    const name = uniqueName('Tanpa Mundur')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const { periodId, stationId } = await openStationThenBackToList(page, name)

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

    // Menyimpan dari daftar tidak memindahkan satu pun status stasiun —
    // diperiksa di layar tempat status itu hidup, dan bertahan setelah reload.
    await gotoPeriodDetail(page, periodId)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
    await page.reload()
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

    const { periodId, stationId } = await openStationThenBackToList(page, name)

    // Edit is still available while a station is merely open.
    await page.locator(`[data-testid="edit-button-${periodId}"]`).click()
    await page.locator('#name').fill(renamed)
    await page.locator('[data-testid="save-button"]').click()

    await expect(periodRow(page, renamed)).toBeVisible()
    await expect(page.locator('[data-testid="delete-error"]')).toHaveCount(0)

    await gotoPeriodDetail(page, periodId)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
    await gotoPeriods(page)

    // And so is delete.
    await deletePeriod(page, renamed)
    await expect(page.locator('[data-testid="delete-error"]')).toHaveCount(0)
  })

  // ── Skenario yang baru mungkin ada setelah pemisahan ──────────────────
  //
  // Inti seluruh revisi ini: stasiun tidak selesai serentak. Sebelum
  // 2026-09-26 tidak satu pun dari tiga skenario berikut dapat dituliskan,
  // karena satu periode hanya punya satu status.

  // Sterilizer tertutup sementara Clarification masih terbuka. Bagaimana
  // KEDUA barisnya ter-render, dan bahwa menutup satu tidak menyentuh yang
  // lain, diasersi di tests/detail-periode-pelaporan.spec.ts — di sini yang
  // diuji apa yang DAFTAR lakukan dengan perselisihan itu.
  test('stasiun tidak serentak: baris induk daftar berbunyi Campuran dan menghitung yang tertutup', async ({ page }) => {
    const { start, end } = uniqueRange(25)
    const name = uniqueName('Campuran')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    // Semuanya berangkat Draft, jadi ringkasannya pun Draft dan belum
    // menghitung apa pun sebagai tertutup.
    const periodId = await periodIdFor(page, name)
    await expect(page.locator(`[data-testid="status-summary-${periodId}"]`)).toHaveText('Draft')
    await expect(page.locator(`[data-testid="station-summary-${periodId}"]`)).not.toContainText('tertutup')

    // Satu stasiun dibawa ke Terbuka, satu lagi ditutup — lewat layar detail,
    // tempat kedua aksi itu kini berada.
    const { stationId: openId } = await openStationThenBackToList(page, name, OTHER_STATION_TYPE)
    await closeStationThenBackToList(page, name, STATION_TYPE)

    // The parent row summarises the disagreement rather than picking a side.
    await expect(page.locator(`[data-testid="status-summary-${periodId}"]`)).toHaveText('Campuran')
    await expect(page.locator(`[data-testid="station-summary-${periodId}"]`)).toContainText('1 tertutup')
    // Dan baris induk tetap tidak pernah mengaku punya jenis stasiun sendiri,
    // maupun menyisakan satu pun aksi stasiun.
    await expect(periodRow(page, name)).not.toContainText(STATION_TYPE)
    await expect(page.locator(`[data-testid="station-close-button-${openId}"]`)).toHaveCount(0)
    await expect(page.locator('[data-testid^="station-reopen-button-"]')).toHaveCount(0)

    // Ringkasannya bertahan setelah reload — perbedaannya hidup di data,
    // bukan di kecelakaan rendering.
    await page.reload()
    await expect(page.locator(`[data-testid="status-summary-${periodId}"]`)).toHaveText('Campuran')
  })

  // Filter "Status Stasiun" — "punya MINIMAL SATU stasiun berstatus ini",
  // so one period can legitimately appear under two different values.
  test('filter Status Stasiun: periode dengan stasiun tertutup DAN draft muncul di kedua filter', async ({ page }) => {
    const { start, end } = uniqueRange(26)
    const name = uniqueName('Filter Campuran')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const { periodId } = await closeStationThenBackToList(page, name)

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

  // Accordion-nya tidak sekadar disembunyikan — ia dicabut dan digantikan
  // satu tautan. Test ini menjaga penggantian itu tetap utuh dari kedua
  // sisinya: yang hilang harus benar-benar hilang, dan yang menggantikannya
  // harus benar-benar sebuah tautan yang bisa dibuka di tab baru.
  test('baris periode: namanya tautan ke layar detail, tanpa tombol expand dan tanpa satu pun aksi stasiun', async ({ page }) => {
    const { start, end } = uniqueRange(27)
    const name = uniqueName('Tautan')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const periodId = await periodIdFor(page, name)
    const row = periodRow(page, name)
    const link = row.locator(`[data-testid="period-link-${periodId}"]`)

    await expect(link).toHaveText(name)
    await expect(link).toHaveAttribute('href', new RegExp(`${periodDetailPath(periodId)}$`))

    // Yang dicabut, dicabut seluruhnya — di seluruh halaman, bukan hanya di
    // baris ini.
    for (const gone of [
      `[data-testid="expand-button-${periodId}"]`,
      `[data-testid="period-stations-${periodId}"]`,
      '[data-testid^="period-station-row-"]',
      '[data-testid^="station-status-badge-"]',
      '[data-testid^="station-close-button-"]',
      '[data-testid^="station-open-button-"]',
      '[data-testid^="station-reopen-button-"]',
    ]) {
      await expect(page.locator(gone), `${gone} seharusnya sudah tidak ada di daftar`).toHaveCount(0)
    }

    // Yang tersisa di baris induk hanya dua aksi periode.
    await expect(row.locator(`[data-testid="edit-button-${periodId}"]`)).toBeEnabled()
    await expect(row.locator(`[data-testid="delete-button-${periodId}"]`)).toBeEnabled()

    // Dan tautannya benar-benar membawa ke layar itu.
    await link.click()
    await expect(page).toHaveURL(new RegExp(`${periodDetailPath(periodId)}$`))
    await expect(page.locator('[data-testid="period-name"]')).toHaveText(name)
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

    // Daftar hanya meringkas: tidak ada tabel stasiun dan tidak ada
    // penjelasan yang bisa dibuka di sini sejak accordion-nya dicabut.
    // Penjelasan keadaan ini — yang harus berupa kalimat, bukan tabel kosong —
    // diasersi di tests/detail-periode-pelaporan.spec.ts.
    await expect(page.locator(`[data-testid="period-stations-${periodId}"]`)).toHaveCount(0)
    await expect(page.locator(`[data-testid="period-stations-empty-${periodId}"]`)).toHaveCount(0)

    // Clean up in-test: this period lives in ANOTHER mill, and leaving it
    // there would sit in the date space the laporan specs use for their own
    // period in that same mill.
    await deletePeriod(page, name)
  })
})
