/**
 * Laporan Sterilizer (Browser/Playwright) — screen-129--laporan-sterilizer-web /
 * usecase-129--laporan-sterilizer-web.
 *
 * One test per test_scenarios entry whose `browser_test` is non-empty (all
 * 15). Route is /reports/sterilizer — the repo's report prefix is English
 * (/reports/management, screen-026); no route in this app uses /laporan.
 *
 * SELF-SUFFICIENT DATA, because this screen cannot create any: the report
 * is read-only by design, so there is no UI here to seed it with. The
 * fixtures are therefore built once, in beforeAll, through the two screens
 * that DO own that data:
 *   - /master-data/periods (screen-128, as Admin) — the reporting periods,
 *     including the closed one and the ones this screen must refuse to
 *     offer;
 *   - /data/sterilizer/create (screen-126, as Supervisor) — the cycles
 *     themselves, with the exact duration spread the outlier assertions
 *     depend on.
 * Nothing is assumed about the seeded content of the target database
 * beyond BrowserTestFixtureSeeder's accounts and the "BU Browser Test"
 * mill.
 *
 * WINDOWS ARE UNIQUE PER RUN (same device as kelola-periode-pelaporan.spec.ts):
 * a period may not overlap another on the same (mill, station type), so
 * every window is derived from RUN_OFFSET and sits far in the future. Like
 * that spec, this one does not delete what it creates — re-running it is
 * safe, but the rows accumulate.
 *
 * DURATION SPREAD IS LOAD-BEARING: the "Lengkap" period carries cycles of
 * 88, 90, 91, 92, 93, 94, 95, 96, 97 and 200 minutes plus two cycles with
 * no open-door time. That yields a Tukey fence of 84,5 - 102,5 minutes and
 * exactly one flagged cycle (200), which is what the outlier and threshold
 * scenarios assert. Do not "tidy" those numbers.
 *
 * EXPORT IS A LIVEWIRE ACTION, not an <a href>: the button carries
 * wire:click="export('csv')", so the assertion waits for Playwright's
 * download event fired by Livewire's client-side download handler.
 */

import { test, expect, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { deletePeriodsByPrefix } from './support/periods'

const REPORT_PATH = '/reports/sterilizer'
const PERIODS_PATH = '/master-data/periods'
const STERILIZER_FORM_PATH = '/data/sterilizer/create'

const SUPERVISOR = 'stertest-browse01'
const FORM_SUPERVISOR = 'stertest-form01'
const ADMIN = 'stest-admin01'
/** Operator is a mobile-only actor — seeded by DemoAccountSeeder. */
const OPERATOR = 'operator01'

const BUSINESS_UNIT = 'BU Browser Test'
const STATION_TYPE = 'Sterilizer'
const OTHER_STATION_TYPE = 'Threshing'

/** A per-run day offset, so two runs never create overlapping windows. */
const RUN_OFFSET = Math.floor(Date.now() / 1000) % 150000

function isoDate(dayOffset: number): string {
  return new Date(Date.UTC(2600, 0, 1) + dayOffset * 86400000).toISOString().slice(0, 10)
}

/** Five-day window, slot-spaced so the run's own periods cannot overlap. */
function windowFor(slot: number): { startDay: number; start: string; end: string } {
  const startDay = RUN_OFFSET + slot * 10

  return { startDay, start: isoDate(startDay), end: isoDate(startDay + 4) }
}

const MAIN = windowFor(1)
const UNIFORM = windowFor(2)
const EMPTY = windowFor(3)
const CLOSED = windowFor(4)
const ALL_TYPES = windowFor(5)
const OTHER_TYPE = windowFor(6)

const PERIOD_MAIN = `Sterilizer Lengkap ${RUN_OFFSET}`
const PERIOD_UNIFORM = `Sterilizer Seragam ${RUN_OFFSET}`
const PERIOD_EMPTY = `Sterilizer Kosong ${RUN_OFFSET}`
const PERIOD_CLOSED = `Sterilizer Tertutup ${RUN_OFFSET}`
const PERIOD_ALL_TYPES = `Sterilizer Semua Stasiun ${RUN_OFFSET}`
const PERIOD_OTHER_TYPE = `Sterilizer Jenis Lain ${RUN_OFFSET}`

/** The date one day BEFORE the main window — must never be reported. */
const OUTSIDE_DATE = isoDate(MAIN.startDay - 1)

// ---------------------------------------------------------------------
// Fixture builders (other screens' UI)
// ---------------------------------------------------------------------

/** #business_unit_id / #station_type are x-searchable-select comboboxes. */
async function selectSearchable(page: Page, id: string, label: string): Promise<void> {
  await page.locator(`#${id}`).click()
  await page.locator(`#${id}`).fill(label)
  await page.locator(`#${id}-listbox`).getByRole('option', { name: label, exact: true }).click()
}

async function createPeriod(
  page: Page,
  options: { name: string; start: string; end: string; stationType: string | null },
): Promise<void> {
  await page.locator('[data-testid="add-period-button"]').click()
  await selectSearchable(page, 'business_unit_id', BUSINESS_UNIT)

  // Leaving the station type untouched is the "every station type" scope.
  if (options.stationType) {
    await selectSearchable(page, 'station_type', options.stationType)
  }

  await page.locator('#name').fill(options.name)
  await page.locator('#start_date').fill(options.start)
  await page.locator('#end_date').fill(options.end)
  await page.locator('[data-testid="save-button"]').click()

  await expect(page.locator('.kc-table__row', { hasText: options.name })).toBeVisible()
}

async function closePeriod(page: Page, name: string): Promise<void> {
  const row = page.locator('.kc-table__row', { hasText: name })

  await row.locator('button', { hasText: 'Tutup Periode' }).click()
  await page.locator('[data-testid="confirm-close-button"]').click()

  await expect(row.locator('.kc-badge')).toHaveText('Tertutup')
}

/** 07:00 plus `minutes`, as HH:MM — the form derives the duration from it. */
function openDoorTime(minutes: number): string {
  const total = 7 * 60 + minutes

  return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`
}

/**
 * One sterilizer_records row on `date` with one cycle per entry of
 * `durations`. A null duration leaves the open-door time blank — the
 * "cycle without a duration" case the KPI card must report separately.
 * `withPeakTimes` fills all six triple-peak times on the first cycle only,
 * which is what keeps triple-peak compliance strictly between 0 and 100.
 */
async function createSterilizerRecord(
  page: Page,
  options: { sterilizerId: string; date: string; durations: Array<number | null>; withPeakTimes?: boolean },
): Promise<void> {
  await page.goto(STERILIZER_FORM_PATH)

  await page.locator('[data-testid="production-line-select"]').selectOption({ index: 1 })
  await page.locator('[data-testid="sterilizer-id-input"]').fill(options.sterilizerId)
  await page.locator('[data-testid="date-input"]').fill(options.date)

  for (let index = 0; index < options.durations.length; index++) {
    const duration = options.durations[index]

    await page.locator('[data-testid="add-row-button"]').click()

    const row = page.locator(`[data-testid="detail-row-${index}"]`)
    await expect(row).toBeVisible()

    await page.locator(`[data-testid="detail-sterilizer-no-${index}"]`).fill(String((index % 3) + 1))
    await page.locator(`[data-testid="detail-close-door-time-${index}"]`).fill('07:00')

    if (options.withPeakTimes && index === 0) {
      // The six triple-peak times have no testid of their own; they are
      // the time inputs between close-door (0) and open-door (7).
      const times = row.locator('input[type="time"]')

      for (let peak = 1; peak <= 6; peak++) {
        await times.nth(peak).fill(openDoorTime(peak * 10))
      }
    }

    if (duration === null) {
      continue
    }

    await page.locator(`[data-testid="detail-open-door-time-${index}"]`).fill(openDoorTime(duration))
    // Waiting on the computed duration keeps the Livewire round trips of
    // one row from racing the next row's inputs.
    await expect(page.locator(`[data-testid="detail-duration-minutes-${index}"]`)).toHaveText(String(duration))
  }

  await page.locator('[data-testid="save-button"]').click()
  await page.waitForURL(/\/data\/sterilizer\/[0-9a-f-]+$/)
}

// ---------------------------------------------------------------------
// Report helpers
// ---------------------------------------------------------------------

async function openReport(page: Page, username: string): Promise<void> {
  await login(page, username, PASSWORD)
  await page.goto(REPORT_PATH)
  await expect(page.locator('[data-testid="laporan-sterilizer"]')).toBeVisible()
}

/** Picks the period whose option label contains `name`, then waits for it. */
async function selectPeriod(page: Page, name: string): Promise<void> {
  const option = page.locator('[data-testid="period-select"] option', { hasText: name })
  await expect(option).toHaveCount(1)

  const value = await option.getAttribute('value')
  await page.locator('[data-testid="period-select"]').selectOption(value as string)

  await expect(page.locator('[data-testid="report-hero"]')).toContainText(name)
}

test.describe('Laporan Sterilizer', () => {
  // Pembersihan — lihat tests/support/periods.ts untuk alasan lengkapnya.
  // Tanpa ini, periode yang dibuat spec ini menumpuk di database dev
  // (~15 baris per run) sampai memenuhi halaman 1 daftar yang dipaginasi
  // 20 baris, lalu baris yang baru dibuat test terdorong ke halaman 2 dan
  // seluruh suite gagal di createPeriod() sebelum satu pun asersi jalan.
  // Terbukti terjadi pada 2026-09-23: 117 periode, 116 di antaranya residu.
  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage()

    try {
      await login(page, ADMIN, PASSWORD)
      const deleted = await deletePeriodsByPrefix(page, ['Sterilizer '])
      console.log(`[cleanup] laporan-sterilizer: %d periode dihapus`, deleted)
    } catch (error) {
      // Kegagalan membersihkan bukan kegagalan produk — jangan pernah
      // memerahkan suite karenanya.
      console.warn('[cleanup] laporan-sterilizer: pembersihan gagal:', error)
    } finally {
      await page.close()
    }
  })

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(600_000)

    const page = await browser.newPage()

    try {
      // --- Periods (Admin, screen-128) ---------------------------------
      await login(page, ADMIN, PASSWORD)
      await page.goto(PERIODS_PATH)

      await createPeriod(page, { name: PERIOD_MAIN, start: MAIN.start, end: MAIN.end, stationType: STATION_TYPE })
      await createPeriod(page, { name: PERIOD_UNIFORM, start: UNIFORM.start, end: UNIFORM.end, stationType: STATION_TYPE })
      await createPeriod(page, { name: PERIOD_EMPTY, start: EMPTY.start, end: EMPTY.end, stationType: STATION_TYPE })
      await createPeriod(page, { name: PERIOD_CLOSED, start: CLOSED.start, end: CLOSED.end, stationType: STATION_TYPE })
      // station_type null — "covers every station type", so it MUST be
      // offered by this screen's period picker.
      await createPeriod(page, { name: PERIOD_ALL_TYPES, start: ALL_TYPES.start, end: ALL_TYPES.end, stationType: null })
      // Another station type entirely — it must NOT be offered.
      await createPeriod(page, { name: PERIOD_OTHER_TYPE, start: OTHER_TYPE.start, end: OTHER_TYPE.end, stationType: OTHER_STATION_TYPE })

      await closePeriod(page, PERIOD_CLOSED)

      // --- Cycles (Supervisor, screen-126) -----------------------------
      await page.context().clearCookies()
      await login(page, FORM_SUPERVISOR, PASSWORD)

      // Exactly on the first day of the main window.
      await createSterilizerRecord(page, {
        sterilizerId: `STR-RPT-${RUN_OFFSET}-A`,
        date: MAIN.start,
        durations: [88, 90, 91, 92, 93],
        withPeakTimes: true,
      })

      // Exactly on the last day: the 200-minute outlier plus the two
      // cycles whose open-door time is still blank.
      await createSterilizerRecord(page, {
        sterilizerId: `STR-RPT-${RUN_OFFSET}-B`,
        date: MAIN.end,
        durations: [94, 95, 96, 97, 200, null, null],
      })

      // One day before the window — must never appear in the report.
      await createSterilizerRecord(page, {
        sterilizerId: `STR-RPT-${RUN_OFFSET}-C`,
        date: OUTSIDE_DATE,
        durations: [90],
      })

      // Uniform spread: IQR = 0, so the fence collapses onto 95.
      await createSterilizerRecord(page, {
        sterilizerId: `STR-RPT-${RUN_OFFSET}-D`,
        date: UNIFORM.start,
        durations: [95, 95, 95, 95, 95, 95, 95, 95, 95, 95],
      })
    } finally {
      await page.close()
    }
  })

  // Scenario: "success"
  test('berhasil: kartu angka utama, grafik, ambang pencilan, rekap harian, dan unduhan CSV', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MAIN)

    // Four KPI cards with real numbers.
    await expect(page.locator('[data-testid="kpi-total-cycles"]')).toContainText('12')
    await expect(page.locator('[data-testid="kpi-total-cages"]')).toBeVisible()
    await expect(page.locator('[data-testid="kpi-avg-duration"]')).toBeVisible()
    await expect(page.locator('[data-testid="kpi-triple-peak"]')).toBeVisible()

    // Charts are drawn, and the outlier card states its threshold.
    await expect(page.locator('[data-testid="daily-trend"]')).toBeVisible()
    await expect(page.locator('[data-testid="duration-distribution"]')).toBeVisible()
    await expect(page.locator('[data-testid="by-unit"]')).toBeVisible()
    await expect(page.locator('[data-testid="outlier-threshold"]')).toContainText('Ambang batas yang dipakai')

    // Daily recap opens with a Total row.
    await page.locator('[data-testid="recap-toggle"]').click()
    await expect(page.locator('[data-testid="recap-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="recap-row-total"]')).toContainText('TOTAL PERIODE')

    const downloadPromise = page.waitForEvent('download')
    await page.locator('[data-testid="export-csv"]').click()
    const download = await downloadPromise

    expect(download.suggestedFilename()).toContain('laporan-sterilizer')
  })

  // Scenario: "Admin belum memilih mill"
  test('admin tanpa mill: pemilih Mill kosong dan pesan memilih mill, tanpa angka apa pun', async ({ page }) => {
    await openReport(page, ADMIN)

    await expect(page.locator('[data-testid="mill-select"]')).toHaveValue('')
    await expect(page.locator('[data-testid="empty-select-mill"]')).toBeVisible()
    await expect(page.locator('[data-testid="report-kpis"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="daily-trend"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="period-select"]')).toHaveCount(0)
  })

  // Scenario: "Admin memilih mill lalu melihat laporannya"
  test('admin memilih mill: daftar periode terisi ulang dan angka mill itu muncul', async ({ page }) => {
    await openReport(page, ADMIN)

    await page.locator('[data-testid="mill-select"]').selectOption({ label: BUSINESS_UNIT })
    await expect(page.locator('[data-testid="empty-select-mill"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="period-select"]')).toBeVisible()

    await selectPeriod(page, PERIOD_MAIN)

    await expect(page.locator('[data-testid="report-kpis"]')).toBeVisible()
    await expect(page.locator('[data-testid="kpi-total-cycles"]')).toContainText('12')
    await expect(page.locator('[data-testid="daily-trend"]')).toBeVisible()
  })

  // Scenario: "Belum ada Periode Pelaporan"
  test('belum ada periode: pemilih periode kosong dengan arahan membuat periode, tanpa error', async ({ page }) => {
    await openReport(page, ADMIN)

    // Which mills have no Sterilizer period is environment data, so the
    // spec looks for one instead of hardcoding a name. The values are read
    // up front: every selection re-renders the picker, which would stale
    // the element handles.
    const millValues: string[] = []

    for (const option of await page.locator('[data-testid="mill-select"] option').all()) {
      const value = await option.getAttribute('value')

      if (value) {
        millValues.push(value)
      }
    }

    let found = false

    for (const value of millValues) {
      await page.locator('[data-testid="mill-select"]').selectOption(value)
      // One Livewire round trip: either the period picker fills, or the
      // "no period yet" hint appears.
      await page.waitForTimeout(500)

      if (await page.locator('[data-testid="empty-no-periods"]').isVisible()) {
        found = true
        break
      }
    }

    expect(found, 'no mill without a Sterilizer reporting period exists in this environment').toBe(true)

    await expect(page.locator('[data-testid="empty-no-periods"]')).toContainText('Belum ada Periode Pelaporan')
    await expect(page.locator('[data-testid="report-kpis"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="daily-trend"]')).toHaveCount(0)
    // An empty mill is not an error page.
    await expect(page.locator('[data-testid="laporan-sterilizer"]')).toBeVisible()
  })

  // Scenario: "Periode tanpa data Sterilizer"
  test('periode tanpa data: angka utama nol, pesan belum ada data, dan tidak ada batang kosong', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_EMPTY)

    await expect(page.locator('[data-testid="kpi-total-cycles"]')).toContainText('0')
    await expect(page.locator('[data-testid="empty-no-data"]')).toContainText('Belum ada data pada periode ini')
    await expect(page.locator('[data-testid="daily-trend"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="by-unit"]')).toHaveCount(0)
  })

  // Scenario: "Sebagian siklus belum punya durasi"
  test('sebagian siklus tanpa durasi: total siklus utuh, rata-rata tidak terdilusi, jumlah yang dikeluarkan tertulis', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MAIN)

    // All 12 cycles counted...
    await expect(page.locator('[data-testid="kpi-total-cycles"]')).toContainText('12')
    // ...while the average covers the 10 that have a duration, and the
    // page says so right next to it.
    await expect(page.locator('[data-testid="cycles-without-duration"]')).toContainText('Dihitung dari 10 siklus')
    await expect(page.locator('[data-testid="cycles-without-duration"]')).toContainText('2 siklus tanpa durasi dikeluarkan')
  })

  // Scenario: "Seluruh durasi seragam"
  test('durasi seragam: kartu menyatakan tidak ada siklus di luar kebiasaan dan tetap menulis ambangnya', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_UNIFORM)

    await expect(page.locator('[data-testid="outliers-none"]')).toContainText('Tidak ada siklus di luar kebiasaan')
    await expect(page.locator('[data-testid="outlier-threshold"]')).toBeVisible()
    await expect(page.locator('[data-testid="outlier-item"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="outliers-insufficient"]')).toHaveCount(0)
  })

  // Scenario: "Supervisor dan Mill Management hanya melihat millnya sendiri"
  test('supervisor: tidak ada pemilih Mill dan periode hanya milik mill sendiri', async ({ page }) => {
    await openReport(page, SUPERVISOR)

    // Offering a picker they cannot use would be a lie, so there is none.
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)

    await selectPeriod(page, PERIOD_MAIN)

    await expect(page.locator('[data-testid="report-hero"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="report-kpis"]')).toBeVisible()
  })

  // Scenario: "Operator mencoba mengakses"
  //
  // STILL CORRECT AFTER 2026-09-23, and deliberately so. The API role list
  // (/api/sterilizer-reports/*) was widened to include `operator` for
  // screen-135--laporan-sterilizer-mobile, but the WEB route asserted here
  // — REPORT_PATH = '/reports/sterilizer', a Livewire page guarded in
  // routes/web.php — was NOT. Operator has no web UI for this report, so
  // 403 remains the right answer on the web. This test navigates the
  // browser to the web page; it never calls the API, and must not be
  // rewritten to do so: the mobile/API side is covered by
  // backend/tests/Feature/Api/LaporanSterilizerTest.php.
  test('operator: akses laporan WEB ditolak dan tidak ada data yang terlihat', async ({ page }) => {
    await login(page, OPERATOR, PASSWORD)
    // Web route, not the API — that separation is the assertion.
    await page.goto(REPORT_PATH)

    // EnsureRole -> abort(403): the error page, never the report.
    await expect(page.locator('body')).toContainText(/403|Forbidden/i)
    await expect(page.locator('[data-testid="laporan-sterilizer"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="report-kpis"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="recap-table"]')).toHaveCount(0)
  })

  // Scenario: "Periode berstatus Tertutup"
  test('periode tertutup: laporan tetap tampil dengan label Tertutup dan ekspor tetap terpicu', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_CLOSED)

    await expect(page.locator('[data-testid="hero-status"]')).toHaveText(/Tertutup/)
    await expect(page.locator('[data-testid="report-kpis"]')).toBeVisible()
    // Status is a caption, not a gate — the button is never disabled.
    await expect(page.locator('[data-testid="export-csv"]')).toBeEnabled()

    const downloadPromise = page.waitForEvent('download')
    await page.locator('[data-testid="export-csv"]').click()
    const download = await downloadPromise

    expect(download.suggestedFilename()).toContain('laporan-sterilizer')
  })

  // Scenario: "periode yang tidak mencakup jenis stasiun Sterilizer tidak
  // dapat dipilih"
  test('pemilih periode: memuat periode Sterilizer dan semua-stasiun, bukan periode jenis lain', async ({ page }) => {
    await openReport(page, SUPERVISOR)

    const options = page.locator('[data-testid="period-select"] option')

    await expect(options.filter({ hasText: PERIOD_MAIN })).toHaveCount(1)
    await expect(options.filter({ hasText: PERIOD_ALL_TYPES })).toHaveCount(1)
    await expect(options.filter({ hasText: PERIOD_OTHER_TYPE })).toHaveCount(0)

    // Newest first: this run's latest window is the "jenis lain" one, so
    // among the offered ones the all-station-type period leads.
    const labels = await options.allTextContents()
    const allTypesIndex = labels.findIndex((label) => label.includes(PERIOD_ALL_TYPES))
    const mainIndex = labels.findIndex((label) => label.includes(PERIOD_MAIN))

    expect(allTypesIndex).toBeLessThan(mainIndex)
  })

  // Scenario: "rentang inklusif memakai tanggal kejadian"
  test('rentang inklusif: baris tanggal awal dan akhir muncul, siklus di luar rentang tidak', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MAIN)

    await page.locator('[data-testid="recap-toggle"]').click()
    await expect(page.locator('[data-testid="recap-table"]')).toBeVisible()

    await expect(page.locator(`[data-testid="recap-row-${MAIN.start}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="recap-row-${MAIN.end}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="recap-row-${OUTSIDE_DATE}"]`)).toHaveCount(0)

    // The Total row is consistent with the two visible rows (5 + 7).
    await expect(page.locator('[data-testid="recap-row-total"]')).toContainText('12')
  })

  // Scenario: "kepatuhan triple-peak turun saat waktu puncak atau
  // pembuangan tidak lengkap"
  test('triple-peak: kepatuhan di bawah 100 persen sebanding dengan siklus yang tidak lengkap', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MAIN)

    const card = page.locator('[data-testid="kpi-triple-peak"]')

    // Only one of the 12 cycles carries all six times.
    await expect(card).toContainText('1 dari 12 siklus lengkap')
    await expect(card).toContainText('11 siklus belum lengkap keenam waktunya')
    await expect(card.locator('.md-kpi__value')).not.toContainText('100')
  })

  // Scenario: "ambang siklus menyimpang wajib ditampilkan di layar"
  test('ambang pencilan: nilai ambang tertulis dan hanya siklus di luar ambang yang terdaftar', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MAIN)

    const threshold = page.locator('[data-testid="outlier-threshold"]')

    // The fence derived from this period's own spread: 84,5 - 102,5.
    await expect(threshold).toContainText('84,5')
    await expect(threshold).toContainText('102,5')

    // Exactly the 200-minute cycle sits outside it.
    await expect(page.locator('[data-testid="outlier-item"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="outlier-item"]')).toContainText('200')
    await expect(page.locator('[data-testid="outlier-item"]')).toContainText('di atas ambang atas')
  })

  // Scenario: "laporan bersifat baca saja"
  test('baca saja: tidak ada aksi tambah, ubah, hapus, maupun sel yang dapat diedit', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MAIN)
    await page.locator('[data-testid="recap-toggle"]').click()
    await expect(page.locator('[data-testid="recap-table"]')).toBeVisible()

    const report = page.locator('[data-testid="laporan-sterilizer"]')

    await expect(report.getByRole('button', { name: /Tambah|Ubah|Edit|Hapus|Simpan/i })).toHaveCount(0)
    // The only controls are the two pickers and the two export buttons.
    await expect(report.locator('input')).toHaveCount(0)
    await expect(report.locator('textarea')).toHaveCount(0)
    await expect(report.locator('form')).toHaveCount(0)
    await expect(report.locator('[data-testid="recap-table"] input')).toHaveCount(0)
    await expect(report.locator('[contenteditable="true"]')).toHaveCount(0)
  })
})
