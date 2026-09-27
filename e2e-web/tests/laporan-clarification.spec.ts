/**
 * Laporan Clarification (Browser/Playwright) — screen-132--laporan-clarification-web /
 * usecase-132--laporan-clarification-web.
 *
 * One test per test_scenarios entry whose `browser_test` is non-empty (all
 * 23; one of them is documented below as not seedable through the product's
 * own screens). Route is /reports/clarification — the repo's report prefix
 * is English (/reports/management, /reports/stations, /reports/cages-track,
 * /reports/sterilizer, /reports/boiler-room); no route in this app uses
 * /laporan.
 *
 * SELF-SUFFICIENT DATA, because this screen cannot create any: the report is
 * READ-ONLY by design, so there is no UI here to seed it with. The fixtures
 * are built once, in beforeAll, through the two screens that DO own that
 * data:
 *   - /master-data/periods       (screen-128, as Admin)      — every period,
 *     including the closed one and the two the picker must decide between;
 *   - /data/clarification/create (screen-119, as Supervisor) — the records
 *     and their time-slot rows, with the exact shape every assertion needs.
 *
 * =========================================================================
 * THE SEEDED NUMBERS ARE LOAD-BEARING. Do not "tidy" them.
 * =========================================================================
 * This screen's defining property is that PRODUCTION IS DERIVED, NOT
 * RECORDED: there is no production column, and production.total_ton is the
 * SUM of pure_oil_production_rate_ton_hour over the rows that carry one.
 * Its most dangerous trap is that AN HOUR WITH NO RATE READING IS NOT AN
 * HOUR THAT PRODUCED ZERO — and BOTH readings give the SAME TOTAL, so only
 * the AVERAGE can catch a zero-filling implementation. Every number below
 * exists to make that one difference visible.
 *
 * PERIOD_MAIN — 3 days, ONE clarification unit, ELEVEN filled slots:
 *
 *   Record A  (unit CLF-<run>-A1, date MAIN.start, 10 rows, slots 07:00..16:00)
 *     Suhu Clarification : 93,0 on rows 0..6          ->  7 readings
 *     Suhu Minyak        : 97,0 on rows 0..4          ->  5 readings
 *     Suhu Sludge        : 87,0 on rows 1..9          ->  9 readings
 *     Level Buffer       : 72,0 on rows 0..2          ->  3 readings
 *     LAJU PRODUKSI      : 10 / 12,5 / 8 / 9,5 on rows 0..3 -> 4 readings
 *     Downtime           : 10 menit on rows 0..5      ->  6 readings
 *   Record B  (unit CLF-<run>-A1, date MAIN.end, 1 row, slot 07:00)
 *     ONLY `Findings` is filled — nothing numeric at all.
 *
 *   => PRODUKSI: 10 + 12,5 + 8 + 9,5 = 40,0 ton from FOUR rate readings,
 *      out of ELEVEN filled slots. The rate average is therefore
 *      40,0 / 4 = 10,00 ton/jam and NEVER 40,0 / 11 = 3,64: the six
 *      rate-less rows contribute neither a 0,0 to the sum nor a 1 to the
 *      denominator. Scenarios 7, 17 and 18 assert that pair.
 *   => EVERY METRIC HAS A DIFFERENT DENOMINATOR — 7 / 5 / 9 / 3 / 4 / 6 —
 *      on purpose: a single shared reading count would deflate every
 *      rarely-filled metric while still producing a plausible number.
 *   => RECORD B'S FINDINGS-ONLY ROW is deliberate too: a slot counts as
 *      FILLED when any one of its SEVEN non-time_slot columns is filled, so
 *      that row raises coverage to 11 while contributing to no metric at
 *      all. It is also what puts a record on MAIN.end, which scenario 19
 *      (inclusive range) needs.
 *   => COVERAGE: 1 unit x 3 hari x 24 slot = 72 expected against 11 filled
 *      = 15,3 %.
 *   => DOWNTIME: 6 x 10 = 60 menit over 6 readings, recorded on 6 hours.
 *
 * PERIOD_NORATE — 3 rows filling ONLY Suhu Sludge: the rate was never
 *   recorded (production unavailable, reading count 0) AND downtime was
 *   never recorded ("tidak tercatat", reading count 0), while the sludge
 *   card keeps its own 87,0 over 3 readings. Scenarios 6 and 9 (step 1).
 * PERIOD_ZERODOWN — 3 rows recording downtime 0: total 0 with reading count
 *   3. Together with PERIOD_NORATE this is the whole point of scenario 9 —
 *   "recorded and it was zero" and "never recorded" must READ DIFFERENTLY.
 * PERIOD_RATEDOWN — ONE row with rate 10,0 AND downtime 20: production
 *   stays 10,0 ton (not 10 x 40/60 = 6,67) and downtime reads 20. Scenario 8.
 *   >> That non-subtracting formula is STILL PENDING the process owner; this
 *   >> spec asserts current behaviour and does not decide the question.
 * PERIOD_MULTI — three units on one date: CLF-B1 20,0 ton, CLF-B2 15,0 ton,
 *   CLF-B3 with a findings-only row and no numeric reading at all. Period
 *   total 35,0 ton. Scenario 11.
 * PERIOD_SPARSE — 1 unit x 10 days x 24 = 240 expected against 2 filled:
 *   the coverage card must be legible ABOVE the figures. Scenario 10.
 * PERIOD_EMPTY  — no record at all: every figure reads as unavailable,
 *   never 0, and no empty chart is drawn. Scenario 5.
 * PERIOD_CLOSED — one record of 2 rows, then closed: a closed period stays
 *   fully readable AND fully exportable. Scenario 15.
 * PERIOD_EXTREME — Suhu Sludge 250,0 and Level Buffer 0,5 beside ordinary
 *   values: rendered as-is, flagged nowhere. Scenario 21.
 * PERIOD_WHOLE_MILL and PERIOD_OTHER_MILL — the two period-picker membership
 *   cases; neither carries a record. Since 2026-09-26 a period covers its
 *   whole mill, so WHOLE_MILL is simply a period of THIS mill (it carries a
 *   Clarification row like every other one) and OTHER_MILL is a period of a
 *   mill with no active station at all, hence with no station row to match.
 * OUTSIDE_DATE — one record one day BEFORE MAIN.start, rate 999. It must
 *   appear nowhere: no recap row, no trend column, not in the CSV.
 *
 * TIME SLOTS MUST BE PICKED IN ASCENDING ORDER. FormClarification's
 * availableTimeSlotOptions() only offers slots whose index in
 * ClarificationRecordService::canonicalTimeSlots() is ABOVE the highest one
 * already picked, and that canonical order starts at 07:00 and wraps
 * (07:00, 08:00, ..., 23:00, 00:00, ..., 06:00). Rows are therefore always
 * filled front to back.
 *
 * WINDOWS ARE UNIQUE PER RUN AND SIT IN THEIR OWN DATE LANE. A period may
 * not overlap another IN THE SAME MILL — since 2026-09-26 the overlap rule
 * ignores the station type entirely — so every window is derived from
 * RUN_OFFSET and laid out end to end by a cursor, and RUN_OFFSET itself is
 * pushed into this spec's own lane by laneOffset().
 *
 * THE LANE IS NOT THE EPOCH ANY MORE. This header used to claim separation by
 * century (year 2700 here against 2600 elsewhere). That separation was
 * illusory: the day offsets themselves reach ~2.4 MILLION days (~6,500
 * years), so all the "centuries" overlapped across years 2600-9400 and the
 * four Business Unit A specs collided on 0.024% of seconds. The epoch is now
 * 2600 everywhere and the real separation is arithmetic — see
 * tests/support/period-lanes.ts.
 *
 * The stride between runs is still wider than one run's whole span so an
 * older run's leftover records cannot fall inside a newer run's window and
 * silently shift every figure above.
 *
 * CLEANUP IS MANDATORY, not tidiness. Without the deletePeriodsByPrefix()
 * call in afterAll this suite poisons itself, and that is measured rather
 * than assumed (see e2e-web/tests/support/periods.ts): the period list is
 * paginated at 20 rows ordered by start_date DESC, this spec adds 12 rows
 * per run, and after a single further run the rows a test has just created
 * are pushed to page 2 — so createPeriod() fails on its own toBeVisible()
 * check BEFORE a single behavioural assertion has run.
 *
 * NO THRESHOLD ASSERTIONS ANYWHERE. Clarification has no operational-target
 * master (there is no ClarificationOperationalTarget), so every scenario
 * that touches an extreme value asserts the ABSENCE of a warning colour,
 * icon or badge — by name. The one deliberate exception is the shared
 * `md-threshold` note box, which this screen uses precisely to STATE that
 * nothing is flagged (data-testid="tank-gap-note"); the assertions below
 * therefore name the flagging testids and colour classes rather than the
 * bare word.
 *
 * EXPORT IS A LIVEWIRE ACTION, not an <a href>: the button carries
 * wire:click="exportCsv('csv')", so the assertion waits for Playwright's
 * download event fired by Livewire's client-side download handler.
 */

import { readFile } from 'node:fs/promises'
import { test, expect, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { deletePeriodsByPrefix } from './support/periods'
import { closeStation, createPeriodViaUi, openStationRow } from './support/period-screen'
import { laneOffset } from './support/period-lanes'

const REPORT_PATH = '/reports/clarification'
const PERIODS_PATH = '/master-data/periods'
const CLARIFICATION_FORM_PATH = '/data/clarification/create'

/** The mill these fixtures live in — the same one laporan-boiler-room uses. */
const BUSINESS_UNIT = 'Business Unit A'
const STATION_TYPE = 'Clarification'
/**
 * The mill of the period this screen must NOT offer.
 *
 * WHY A MILL AND NOT A STATION TYPE ANY MORE. listPeriods() offers a period
 * when it belongs to the caller's mill AND has a `period_stations` row for
 * this screen's station type. Within one provisioned mill that second clause
 * can no longer be made false from the UI: creating a period registers a row
 * for EVERY station type the mill has, so there is no such thing as a period
 * of this mill that skips Clarification. "Mill Kode Duplikat" has no active
 * station at all, so its period gets NO station row whatsoever — it fails
 * both clauses at once, and it is the only browser-reachable shape of "a
 * period that does not cover this station type". The station-row clause on
 * its own is asserted where it can be produced directly, in
 * backend/tests/Feature/Api/LaporanClarificationTest.php.
 */
const OTHER_MILL = 'Mill Kode Duplikat'

const SUPERVISOR = 'supervisor01'
const MILL_MANAGEMENT = 'millmanagement-a'
const ADMIN = 'admin'
/** Operator is a mobile-only actor — it has no web UI for this report, and
 *  its API was never widened either (screen-138, the mobile Clarification
 *  report, does not exist). */
const OPERATOR = 'operator01'

/**
 * A per-run day offset, so two runs never collide.
 *
 * THE STRIDE IS LOAD-BEARING. Periods are deleted in afterAll, but the
 * clarification_records entered through screen-119 are NOT (that screen
 * exposes no delete). They stay in the dev database forever. If one run's
 * windows can fall inside the next run's windows, the older run's records
 * are silently counted by the newer run's report and every figure above
 * drifts.
 *
 * One run's whole fixture spans ~45 days (see the cursor below), so the
 * seconds counter is multiplied by 60 for a 60-day stride between runs. The
 * modulus keeps the resulting year inside four digits, which
 * Date#toISOString() requires: 40000 x 60 days is ~6570 years past 2600.
 */
/**
 * LAJUR TANGGAL SPEC INI. Offset mentah di bawah tetap seperti semula —
 * stride-nya dipilih demi keperluan spec ini sendiri — lalu laneOffset()
 * menggesernya ke lajur yang tidak dipakai spec lain. Sejak aturan tumpang
 * tindih periode menjadi PER MILL (2026-09-26), spec-spec yang berbagi satu
 * mill tidak boleh lagi memakai rentang tanggal yang sama; alasan lengkap dan
 * aritmetikanya ada di tests/support/period-lanes.ts.
 */
const RUN_OFFSET = laneOffset((Math.floor(Date.now() / 1000) % 40000) * 60, 'clarification')

/** Name prefix every period of this spec carries — also the cleanup key. */
const PERIOD_PREFIX = 'Clarification '

/**
 * Year 2600, the SAME epoch every period-seeding spec uses. Separation from
 * the other specs comes from laneOffset() (tests/support/period-lanes.ts),
 * not from the epoch — see the file header for why a century was never wide
 * enough to separate anything here.
 */
function isoDate(dayOffset: number): string {
  return new Date(Date.UTC(2600, 0, 1) + dayOffset * 86400000).toISOString().slice(0, 10)
}

/**
 * Windows are laid out END TO END by a cursor with a two-day gap, rather
 * than at fixed slots: PERIOD_SPARSE is ten days wide and a fixed spacing
 * would have it overlap its neighbours. Every window of this spec must keep
 * clear of every other one: the overlap rule is per mill and no longer looks
 * at the station type at all.
 *
 * The cursor starts one day AFTER RUN_OFFSET so OUTSIDE_DATE
 * (MAIN.start - 1) still belongs to this run's lane.
 */
let cursor = RUN_OFFSET + 1

function nextWindow(days = 1): { startDay: number; start: string; end: string } {
  const startDay = cursor
  cursor += days + 2

  return { startDay, start: isoDate(startDay), end: isoDate(startDay + days - 1) }
}

const MAIN = nextWindow(3)
const SPARSE = nextWindow(10)
const EMPTY = nextWindow()
const CLOSED = nextWindow()
const NORATE = nextWindow()
const ZERODOWN = nextWindow()
const RATEDOWN = nextWindow()
const MULTI = nextWindow()
const EXTREME = nextWindow()
const TEMPS = nextWindow(3)
const WHOLE_MILL = nextWindow()
const OTHER_MILL_WINDOW = nextWindow()

const PERIOD_MAIN = `${PERIOD_PREFIX}Lengkap ${RUN_OFFSET}`
const PERIOD_SPARSE = `${PERIOD_PREFIX}Tipis ${RUN_OFFSET}`
const PERIOD_EMPTY = `${PERIOD_PREFIX}Kosong ${RUN_OFFSET}`
const PERIOD_CLOSED = `${PERIOD_PREFIX}Tertutup ${RUN_OFFSET}`
const PERIOD_NORATE = `${PERIOD_PREFIX}Tanpa Laju ${RUN_OFFSET}`
const PERIOD_ZERODOWN = `${PERIOD_PREFIX}Downtime Nol ${RUN_OFFSET}`
const PERIOD_RATEDOWN = `${PERIOD_PREFIX}Laju Dan Downtime ${RUN_OFFSET}`
const PERIOD_MULTI = `${PERIOD_PREFIX}Banyak Unit ${RUN_OFFSET}`
const PERIOD_EXTREME = `${PERIOD_PREFIX}Nilai Ekstrem ${RUN_OFFSET}`
const PERIOD_TEMPS = `${PERIOD_PREFIX}Tren Suhu ${RUN_OFFSET}`
const PERIOD_WHOLE_MILL = `${PERIOD_PREFIX}Seluruh Mill ${RUN_OFFSET}`
const PERIOD_OTHER_MILL = `${PERIOD_PREFIX}Mill Lain ${RUN_OFFSET}`

/** The date one day BEFORE the main window — must never be reported. */
const OUTSIDE_DATE = isoDate(MAIN.startDay - 1)

/** The clarification units of this run, unique per run. */
const UNIT_ONE = `CLF-${RUN_OFFSET}-A1`
const UNIT_MULTI_A = `CLF-${RUN_OFFSET}-B1`
const UNIT_MULTI_B = `CLF-${RUN_OFFSET}-B2`
const UNIT_MULTI_C = `CLF-${RUN_OFFSET}-B3`

/**
 * The production line the fixtures are entered on, resolved BY ID in
 * beforeAll — never by label.
 *
 * Selecting by label would be a silent correctness bug: the create form's
 * dropdown lists EVERY production line in the instance, unfiltered by mill,
 * and this database has several mills each carrying a line called "Line 1".
 * Picking the first label match would file the record under whichever mill
 * the database happened to order first, while the period belongs to
 * BUSINESS_UNIT — and the report would then be correctly empty, which reads
 * exactly like a broken report.
 */
let PRODUCTION_LINE_ID = ''

/**
 * Referer + XSRF header pair that makes an APIRequestContext call count as a
 * stateful session request. Same device, and the same reasons, as
 * tests/support/periods.ts — Playwright sends no Referer of its own, so
 * without it Sanctum falls through to the token path and answers 401.
 */
async function statefulHeaders(page: Page): Promise<Record<string, string>> {
  const cookies = await page.context().cookies()
  const xsrf = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')

  return {
    Referer: 'http://localhost:8000/',
    Accept: 'application/json',
    ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf.value) } : {}),
  }
}

/**
 * Resolves, as Admin, a production line of BUSINESS_UNIT whose CLARIFICATION
 * station is active — the only lines FormClarification will accept, since
 * ClarificationRecordService::create() looks the station up by
 * (production_line_id, type='clarification', is_active=true) and refuses
 * anything else.
 */
async function resolveProductionLine(page: Page): Promise<void> {
  const headers = await statefulHeaders(page)

  const mills = await page.request.get('/api/clarification-reports/business-units/options', { headers })
  expect(mills.ok(), 'admin could not read the mill list').toBe(true)

  const mill = ((await mills.json()).data as Array<{ id: string; name: string }>)
    .find((row) => row.name === BUSINESS_UNIT)

  expect(mill, `this environment has no mill named "${BUSINESS_UNIT}"`).toBeTruthy()

  const stations = await page.request.get(
    `/api/stations?per_page=100&business_unit_id=${mill!.id}`,
    { headers },
  )
  expect(stations.ok(), 'admin could not read the station list').toBe(true)

  const clarification = ((await stations.json()).data as Array<{
    type: string
    is_active: boolean
    production_line_id: string
  }>).find((row) => row.type === 'clarification' && row.is_active && row.production_line_id)

  expect(
    clarification,
    `no active clarification station on any production line of "${BUSINESS_UNIT}", so no record can be entered `
      + 'and this report would have nothing to report on',
  ).toBeTruthy()

  PRODUCTION_LINE_ID = clarification!.production_line_id
}

// ---------------------------------------------------------------------
// Fixture builders (other screens' UI)
// ---------------------------------------------------------------------

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

interface SlotRow {
  /** One of the 24 canonical labels. Rows MUST be listed in ASCENDING
   *  canonical order (07:00 first, 06:00 last) — the form's Time-Slot
   *  dropdown only offers slots above the highest already picked. */
  slot: string
  clarificationTemp?: number
  oilTemp?: number
  sludgeTemp?: number
  bufferLevel?: number
  /** THE column production is derived from, in ton/HOUR. Leaving it out is
   *  NOT the same as entering 0 — that is the whole point of this screen. */
  rate?: number
  downtime?: number
  findings?: string
}

/**
 * One clarification_records row plus one clarification_details row per entry
 * of `rows`, entered through screen-119's create form.
 */
async function createClarificationRecord(
  page: Page,
  options: { clarificationId: string; date: string; note?: string; rows: SlotRow[] },
): Promise<void> {
  await page.goto(CLARIFICATION_FORM_PATH)

  await page.locator('[data-testid="production-line-select"]').selectOption(PRODUCTION_LINE_ID)
  await page.locator('[data-testid="clarification-id-input"]').fill(options.clarificationId)
  await page.locator('[data-testid="date-input"]').fill(options.date)

  if (options.note) {
    await page.locator('[data-testid="note-input"]').fill(options.note)
  }

  for (let index = 0; index < options.rows.length; index++) {
    const row = options.rows[index]

    await page.locator('[data-testid="add-row-button"]').click()
    await expect(page.locator(`[data-testid="clarification-detail-row-${index}"]`)).toBeVisible()

    // wire:model.live — the slot choice is a Livewire round trip, and the
    // NEXT row's option list is computed from it, so wait for it to land.
    const slotSelect = page.locator(`[data-testid="time-slot-select-${index}"]`)
    await slotSelect.selectOption(row.slot)
    await expect(slotSelect).toHaveValue(row.slot)

    if (row.clarificationTemp !== undefined) {
      await page.locator(`[data-testid="clarification-tank-temp-${index}"]`).fill(String(row.clarificationTemp))
    }

    if (row.oilTemp !== undefined) {
      await page.locator(`[data-testid="oil-tank-temperature-${index}"]`).fill(String(row.oilTemp))
    }

    if (row.sludgeTemp !== undefined) {
      await page.locator(`[data-testid="sludge-tank-temp-${index}"]`).fill(String(row.sludgeTemp))
    }

    if (row.bufferLevel !== undefined) {
      await page.locator(`[data-testid="buffer-tank-level-${index}"]`).fill(String(row.bufferLevel))
    }

    if (row.rate !== undefined) {
      await page.locator(`[data-testid="pure-oil-production-rate-${index}"]`).fill(String(row.rate))
    }

    if (row.downtime !== undefined) {
      await page.locator(`[data-testid="downtime-mins-${index}"]`).fill(String(row.downtime))
    }

    if (row.findings !== undefined) {
      await page.locator(`[data-testid="findings-${index}"]`).fill(row.findings)
    }
  }

  await page.locator('[data-testid="save-button"]').click()
  await page.waitForURL(
    (url) => url.pathname.startsWith('/data/clarification/') && !url.pathname.endsWith('/create'),
  )
}

/** The ten rows of PERIOD_MAIN's Record A — see the file header. */
const MAIN_RECORD_ROWS: SlotRow[] = [
  { slot: '07:00', clarificationTemp: 93, oilTemp: 97, bufferLevel: 72, rate: 10, downtime: 10 },
  { slot: '08:00', clarificationTemp: 93, oilTemp: 97, sludgeTemp: 87, bufferLevel: 72, rate: 12.5, downtime: 10 },
  { slot: '09:00', clarificationTemp: 93, oilTemp: 97, sludgeTemp: 87, bufferLevel: 72, rate: 8, downtime: 10 },
  { slot: '10:00', clarificationTemp: 93, oilTemp: 97, sludgeTemp: 87, rate: 9.5, downtime: 10 },
  // From here on NO RATE AT ALL — and these rows are NOT zero-production
  // hours: they contribute nothing to the sum and nothing to the average's
  // denominator.
  { slot: '11:00', clarificationTemp: 93, oilTemp: 97, sludgeTemp: 87, downtime: 10 },
  { slot: '12:00', clarificationTemp: 93, sludgeTemp: 87, downtime: 10 },
  { slot: '13:00', clarificationTemp: 93, sludgeTemp: 87 },
  { slot: '14:00', sludgeTemp: 87 },
  { slot: '15:00', sludgeTemp: 87 },
  { slot: '16:00', sludgeTemp: 87 },
]

// ---------------------------------------------------------------------
// Report helpers
// ---------------------------------------------------------------------

async function openReport(page: Page, username: string): Promise<void> {
  await login(page, username, PASSWORD)
  await page.goto(REPORT_PATH)
  await expect(page.locator('[data-testid="laporan-clarification"]')).toBeVisible()
}

/** Picks the period whose option label contains `name`, then waits for it. */
async function selectPeriod(page: Page, name: string): Promise<void> {
  const option = page.locator('[data-testid="period-selector"] option', { hasText: name })
  await expect(option).toHaveCount(1)

  const value = await option.getAttribute('value')
  await page.locator('[data-testid="period-selector"]').selectOption(value as string)

  await expect(page.locator('[data-testid="report-hero"]')).toContainText(name)
}

/** Opens/closes the daily recap — the same button does both. */
async function toggleRecap(page: Page): Promise<void> {
  await page.locator('[data-testid="daily-recap-toggle"]').click()
}

/**
 * Clicks Ekspor CSV, waits for the Livewire-fired download, and returns the
 * file's contents. The button carries wire:click="exportCsv('csv')" rather
 * than an href, so the download event is the only thing to wait on.
 */
async function downloadCsv(page: Page): Promise<string> {
  const downloadPromise = page.waitForEvent('download')
  await page.locator('[data-testid="export-csv-button"]').click()
  const download = await downloadPromise

  expect(download.suggestedFilename()).toContain('laporan-clarification')
  expect(download.suggestedFilename()).toMatch(/\.csv$/)

  const path = await download.path()
  expect(path, 'the CSV download produced no local file').toBeTruthy()

  return readFile(path as string, 'utf8')
}

test.describe('Laporan Clarification', () => {
  // Pembersihan — lihat tests/support/periods.ts untuk alasan lengkapnya.
  // Tanpa ini, periode menumpuk sampai memenuhi halaman 1 daftar yang
  // dipaginasi 20 baris, lalu baris yang baru dibuat test terdorong ke
  // halaman 2 dan suite gagal di createPeriod() sebelum satu pun asersi
  // perilaku jalan. Spec ini membuat 12 periode per run, jadi satu run
  // berikutnya saja sudah cukup untuk memenuhi halaman itu.
  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage()

    try {
      await login(page, ADMIN, PASSWORD)
      const deleted = await deletePeriodsByPrefix(page, [PERIOD_PREFIX])
      console.log('[cleanup] laporan-clarification: %d periode dihapus', deleted)
    } catch (error) {
      // Kegagalan membersihkan bukan kegagalan produk.
      console.warn('[cleanup] laporan-clarification: pembersihan gagal:', error)
    } finally {
      await page.close()
    }
  })

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(1_500_000)

    const page = await browser.newPage()

    try {
      // --- Periods (Admin, screen-128) ---------------------------------
      await login(page, ADMIN, PASSWORD)
      await page.goto(PERIODS_PATH)

      await createPeriod(page, { name: PERIOD_MAIN, start: MAIN.start, end: MAIN.end })
      await createPeriod(page, { name: PERIOD_SPARSE, start: SPARSE.start, end: SPARSE.end })
      await createPeriod(page, { name: PERIOD_EMPTY, start: EMPTY.start, end: EMPTY.end })
      await createPeriod(page, { name: PERIOD_CLOSED, start: CLOSED.start, end: CLOSED.end })
      await createPeriod(page, { name: PERIOD_NORATE, start: NORATE.start, end: NORATE.end })
      await createPeriod(page, { name: PERIOD_ZERODOWN, start: ZERODOWN.start, end: ZERODOWN.end })
      await createPeriod(page, { name: PERIOD_RATEDOWN, start: RATEDOWN.start, end: RATEDOWN.end })
      await createPeriod(page, { name: PERIOD_MULTI, start: MULTI.start, end: MULTI.end })
      await createPeriod(page, { name: PERIOD_EXTREME, start: EXTREME.start, end: EXTREME.end })
      await createPeriod(page, { name: PERIOD_TEMPS, start: TEMPS.start, end: TEMPS.end })
      // Covers the whole mill, Clarification included — so it MUST be offered by this
      // screen's period picker. Before 2026-09-26 this was a period with
      // station_type NULL; the all-stations scope is now expressed by HAVING
      // one row per station type instead.
      await createPeriod(page, { name: PERIOD_WHOLE_MILL, start: WHOLE_MILL.start, end: WHOLE_MILL.end })
      // Another mill, and one without a single active station — it must NOT
      // be offered. See OTHER_MILL.
      await createPeriod(page, {
        name: PERIOD_OTHER_MILL,
        start: OTHER_MILL_WINDOW.start,
        end: OTHER_MILL_WINDOW.end,
        businessUnit: OTHER_MILL,
      })

      // Resolve the production line BY ID while still Admin.
      await resolveProductionLine(page)
      console.log('[fixture] laporan-clarification: production line %s', PRODUCTION_LINE_ID)

      // --- Records (Supervisor, screen-119) ----------------------------
      await page.context().clearCookies()
      await login(page, SUPERVISOR, PASSWORD)

      // PERIOD_MAIN, Record A — ten slots on the FIRST day of the window.
      // See the file header: every number here is load-bearing.
      await createClarificationRecord(page, {
        clarificationId: UNIT_ONE,
        date: MAIN.start,
        note: 'Rekam utama',
        rows: MAIN_RECORD_ROWS,
      })

      // PERIOD_MAIN, Record B — exactly on end_date, and its single row
      // fills ONLY `Findings`: a slot counts as filled when ANY of its seven
      // non-time_slot columns is filled, so this raises coverage to 11 while
      // contributing to no metric at all.
      await createClarificationRecord(page, {
        clarificationId: UNIT_ONE,
        date: MAIN.end,
        rows: [{ slot: '07:00', findings: 'catatan lapangan tanpa angka' }],
      })

      // One day BEFORE the main window — must never be reported.
      await createClarificationRecord(page, {
        clarificationId: `CLF-${RUN_OFFSET}-OUT`,
        date: OUTSIDE_DATE,
        rows: [{ slot: '07:00', rate: 999, sludgeTemp: 999 }],
      })

      // PERIOD_SPARSE — two filled slots inside a ten-day window.
      await createClarificationRecord(page, {
        clarificationId: `CLF-${RUN_OFFSET}-SPARSE`,
        date: SPARSE.start,
        rows: [
          { slot: '07:00', sludgeTemp: 87, rate: 10 },
          { slot: '08:00', sludgeTemp: 87, rate: 10 },
        ],
      })

      // PERIOD_NORATE — the rate was NEVER recorded, and neither was
      // downtime; the sludge temperature was, three times.
      await createClarificationRecord(page, {
        clarificationId: `CLF-${RUN_OFFSET}-NORATE`,
        date: NORATE.start,
        rows: [
          { slot: '07:00', sludgeTemp: 87 },
          { slot: '08:00', sludgeTemp: 87 },
          { slot: '09:00', sludgeTemp: 87 },
        ],
      })

      // PERIOD_ZERODOWN — downtime RECORDED and genuinely zero. Together
      // with PERIOD_NORATE this is the whole point of scenario 9.
      await createClarificationRecord(page, {
        clarificationId: `CLF-${RUN_OFFSET}-ZERO`,
        date: ZERODOWN.start,
        rows: [
          { slot: '07:00', downtime: 0 },
          { slot: '08:00', downtime: 0 },
          { slot: '09:00', downtime: 0 },
        ],
      })

      // PERIOD_RATEDOWN — one hour recording BOTH a rate and downtime.
      await createClarificationRecord(page, {
        clarificationId: `CLF-${RUN_OFFSET}-RD`,
        date: RATEDOWN.start,
        rows: [{ slot: '07:00', rate: 10, downtime: 20 }],
      })

      // PERIOD_MULTI — three units on one date, the third recording nothing
      // numeric at all.
      await createClarificationRecord(page, {
        clarificationId: UNIT_MULTI_A,
        date: MULTI.start,
        rows: [{ slot: '07:00', rate: 20 }],
      })
      await createClarificationRecord(page, {
        clarificationId: UNIT_MULTI_B,
        date: MULTI.start,
        rows: [{ slot: '07:00', rate: 15 }],
      })
      await createClarificationRecord(page, {
        clarificationId: UNIT_MULTI_C,
        date: MULTI.start,
        rows: [{ slot: '07:00', findings: 'unit ini tidak dicatat angkanya' }],
      })

      // PERIOD_EXTREME — an extreme beside an ordinary value.
      await createClarificationRecord(page, {
        clarificationId: `CLF-${RUN_OFFSET}-EXT`,
        date: EXTREME.start,
        rows: [
          { slot: '07:00', clarificationTemp: 93, oilTemp: 97, sludgeTemp: 250, bufferLevel: 0.5 },
          { slot: '08:00', clarificationTemp: 93, oilTemp: 97, sludgeTemp: 87, bufferLevel: 72 },
        ],
      })

      // PERIOD_TEMPS — THREE dates each carrying all three tank
      // temperatures, so the single line chart has a line (rather than a
      // lone dot) per series and the two-layer colour+dash distinction can
      // actually be measured. The gap between the tanks is preserved on
      // every date: minyak above clarification above sludge.
      for (const [offset, temps] of [
        [0, { clarificationTemp: 93, oilTemp: 97, sludgeTemp: 87 }],
        [1, { clarificationTemp: 94, oilTemp: 98, sludgeTemp: 88 }],
        [2, { clarificationTemp: 92, oilTemp: 96, sludgeTemp: 86 }],
      ] as Array<[number, Partial<SlotRow>]>) {
        await createClarificationRecord(page, {
          clarificationId: `CLF-${RUN_OFFSET}-T`,
          date: isoDate(TEMPS.startDay + offset),
          rows: [{ slot: '07:00', ...temps }],
        })
      }

      // PERIOD_CLOSED — data first, then the period is closed, so the
      // "closed periods stay readable and exportable" scenario has figures.
      await createClarificationRecord(page, {
        clarificationId: `CLF-${RUN_OFFSET}-CLOSED`,
        date: CLOSED.start,
        rows: [
          { slot: '07:00', clarificationTemp: 93, oilTemp: 97, sludgeTemp: 87, bufferLevel: 72, rate: 10, downtime: 5 },
          { slot: '08:00', clarificationTemp: 95, oilTemp: 99, sludgeTemp: 89, bufferLevel: 74, rate: 12, downtime: 5 },
        ],
      })

      // --- Close the closed period (Admin again) -----------------------
      await page.context().clearCookies()
      await login(page, ADMIN, PASSWORD)
      await page.goto(PERIODS_PATH)
      // Closing is PER STATION now: only the Clarification row of this period
      // is closed, and the report must stay fully readable and exportable.
      await closeStation(page, PERIOD_CLOSED, STATION_TYPE)
    } finally {
      await page.close()
    }
  })

  // =====================================================================
  // Scenario 1: "berhasil sebagai Supervisor atau Mill Management"
  // =====================================================================
  test('berhasil: tanpa pemilih mill, produksi berdampingan dengan jumlah pembacaannya, dan unduhan CSV diterima', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MAIN)

    // A bound role gets a caption, never a picker.
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="mill-selector"]')).toHaveCount(0)

    // Recording coverage is part of the report body, above every number.
    await expect(page.locator('[data-testid="recording-coverage"]')).toBeVisible()
    await expect(page.locator('[data-testid="coverage-filled-slots"]')).toHaveText('11')
    await expect(page.locator('[data-testid="coverage-expected-slots"]')).toHaveText('72')

    // DERIVED PRODUCTION, beside the reading count that qualifies it.
    await expect(page.locator('[data-testid="summary-production-total"]')).toHaveText('40,0')
    await expect(page.locator('[data-testid="summary-production-reading-count"]')).toHaveText('4')
    await expect(page.locator('[data-testid="summary-downtime-total"]')).toHaveText('60')

    // ONE chart for the three tank temperatures.
    await expect(page.locator('[data-testid="tank-temperature-chart"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="buffer-level-summary"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-trend"]')).toBeVisible()
    await expect(page.locator('[data-testid="by-unit-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-recap-table"]')).toBeVisible()

    // Export — a Livewire download, not a navigation.
    const csv = await downloadCsv(page)
    expect(csv).toContain('Slot Waktu')
    expect(csv).toContain('Laju Produksi Minyak Murni (ton/jam)')

    // No write-flavoured control anywhere.
    for (const control of ['save-button', 'edit-button', 'delete-button']) {
      await expect(page.locator(`[data-testid="${control}"]`)).toHaveCount(0)
    }

    // The figures do not move after a reload: this is a read, and reading it
    // twice must give the same answer.
    await page.reload()
    await selectPeriod(page, PERIOD_MAIN)
    await expect(page.locator('[data-testid="summary-production-total"]')).toHaveText('40,0')
    await expect(page.locator('[data-testid="summary-production-reading-count"]')).toHaveText('4')
  })

  test('berhasil sebagai Mill Management: laporan yang sama, tetap tanpa pemilih Mill', async ({ page }) => {
    await openReport(page, MILL_MANAGEMENT)
    await selectPeriod(page, PERIOD_MAIN)

    await expect(page.locator('[data-testid="mill-selector"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="summary-production-total"]')).toHaveText('40,0')
    await expect(page.locator('[data-testid="summary-production-reading-count"]')).toHaveText('4')
    await expect(page.locator('[data-testid="by-unit-table"]')).toBeVisible()
  })

  // =====================================================================
  // Scenario 2: "berhasil sebagai Admin"
  // =====================================================================
  test('admin memilih mill: pemilih Mill terlihat, lalu seluruh bagian laporan muncul dan Ekspor aktif', async ({ page }) => {
    await openReport(page, ADMIN)

    await expect(page.locator('[data-testid="mill-selector"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-selector"] option')).not.toHaveCount(1)

    await page.locator('[data-testid="mill-selector"]').selectOption({ label: BUSINESS_UNIT })
    await expect(page.locator('[data-testid="mill-required-hint"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="period-selector"]')).toBeVisible()

    await selectPeriod(page, PERIOD_MAIN)

    // Identical blocks to every other role.
    await expect(page.locator('[data-testid="recording-coverage"]')).toBeVisible()
    await expect(page.locator('[data-testid="summary-production-total"]')).toHaveText('40,0')
    await expect(page.locator('[data-testid="tank-temperature-chart"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="daily-trend"]')).toBeVisible()
    await expect(page.locator('[data-testid="by-unit-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-recap-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="export-csv-button"]')).toBeEnabled()

    const csv = await downloadCsv(page)
    expect(csv).toContain(MAIN.start)
  })

  // =====================================================================
  // Scenario 3: "Admin memilih mill lebih dulu"
  // =====================================================================
  test('admin tanpa mill: arahan memilih mill terlihat dan tidak ada satu pun angka laporan', async ({ page }) => {
    await openReport(page, ADMIN)

    await expect(page.locator('[data-testid="mill-selector"]')).toHaveValue('')
    await expect(page.locator('[data-testid="mill-required-hint"]')).toBeVisible()
    // The page asks for a mill instead of drawing an empty report that would
    // read as "this mill has no data".
    await expect(page.locator('[data-testid="summary-production-total"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="recording-coverage"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="tank-temperature-chart"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="daily-recap-table"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="period-selector"]')).toHaveCount(0)
  })

  // =====================================================================
  // Scenario 4: "mill belum punya periode"
  // =====================================================================
  test('belum ada periode: pemilih periode kosong dengan arahan menghubungi Admin, tanpa angka', async ({ page }) => {
    await openReport(page, ADMIN)

    // Which mills have no Clarification period is environment data, so the
    // spec looks for one instead of hardcoding a name. The values are read
    // up front: every selection re-renders the picker, which would stale the
    // element handles.
    const millValues: string[] = []

    for (const option of await page.locator('[data-testid="mill-selector"] option').all()) {
      const value = await option.getAttribute('value')

      if (value) {
        millValues.push(value)
      }
    }

    let found = false

    for (const value of millValues) {
      await page.locator('[data-testid="mill-selector"]').selectOption(value)
      // One Livewire round trip: either the period picker fills, or the
      // "no period yet" hint appears.
      await page.waitForTimeout(500)

      if (await page.locator('[data-testid="no-period-hint"]').isVisible()) {
        found = true
        break
      }
    }

    expect(found, 'no mill without a Clarification reporting period exists in this environment').toBe(true)

    await expect(page.locator('[data-testid="no-period-hint"]')).toContainText('Belum ada Periode Pelaporan')
    await expect(page.locator('[data-testid="period-selector"] option')).toHaveCount(0)
    await expect(page.locator('[data-testid="recording-coverage"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="summary-production-total"]')).toHaveCount(0)
    // An empty mill is not an error page.
    await expect(page.locator('[data-testid="laporan-clarification"]')).toBeVisible()
  })

  // =====================================================================
  // Scenario 5: "periode tanpa data"
  // =====================================================================
  test('periode tanpa data: keterangan belum ada data, angka bertanda pisah bukan nol, tanpa grafik kosong', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_EMPTY)

    await expect(page.locator('[data-testid="no-data-notice"]')).toContainText('Belum ada data pada periode ini')

    // NOT '0': zero would read as "measured, and it was zero". The marker
    // glyph itself is not asserted — the semantics are.
    for (const testid of ['summary-production-total', 'production-rate-avg']) {
      const text = (await page.locator(`[data-testid="${testid}"]`).innerText()).trim()

      expect(text).not.toBe('0')
      expect(text).not.toBe('0,0')
      expect(text).not.toBe('0,00')
      expect(text).not.toMatch(/^\d/)
    }

    for (const card of ['clarification-temp', 'oil-temp', 'sludge-temp', 'buffer-level']) {
      const text = (await page.locator(`[data-testid="${card}-avg"]`).innerText()).trim()

      expect(text).not.toMatch(/^\d/)
      await expect(page.locator(`[data-testid="${card}-reading-count"]`)).toHaveText('0')
    }

    // Downtime says it in words rather than printing a 0.
    await expect(page.locator('[data-testid="summary-downtime-total"]')).toHaveText('tidak tercatat')
    await expect(page.locator('[data-testid="summary-downtime-reading-count"]')).toHaveText('0')

    // No empty chart is drawn — a flat line would read as a measurement.
    await expect(page.locator('[data-testid="tank-temperature-chart"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="daily-trend"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="by-unit-table"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="daily-recap-table"]')).toHaveCount(0)
  })

  // =====================================================================
  // Scenario 6: "laju produksi tidak pernah tercatat"
  // =====================================================================
  test('laju tidak tercatat: produksi tidak tersedia dengan 0 pembacaan, sementara suhu sludge tetap berangka', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_NORATE)

    const production = (await page.locator('[data-testid="summary-production-total"]').innerText()).trim()

    // null, never 0,0 ton: nothing was measured, which is not the same as a
    // mill that produced nothing.
    expect(production).not.toBe('0')
    expect(production).not.toBe('0,0')
    expect(production).not.toMatch(/^\d/)
    await expect(page.locator('[data-testid="summary-production-reading-count"]')).toHaveText('0')

    // Independent per metric — the sludge card is entirely unaffected.
    await expect(page.locator('[data-testid="sludge-temp-avg"]')).toHaveText('87,0')
    await expect(page.locator('[data-testid="sludge-temp-reading-count"]')).toHaveText('3')
  })

  // =====================================================================
  // Scenario 7: "jam tanpa catatan laju" — THE TRAP THAT HIDES ITSELF
  // =====================================================================
  test('jam tanpa laju: 4 pembacaan menghasilkan 40,0 ton dan rata-rata 10,00 — bukan 3,64 — di samping 11 slot terisi', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MAIN)

    await expect(page.locator('[data-testid="summary-production-total"]')).toHaveText('40,0')
    await expect(page.locator('[data-testid="summary-production-reading-count"]')).toHaveText('4')

    // 40,0 / 4 = 10,00. An implementation that treated the seven rate-less
    // rows as 0,0 would print 40,0 / 11 = 3,64 — a perfectly plausible rate
    // beside a total that still looks correct, which is exactly the danger.
    await expect(page.locator('[data-testid="production-rate-avg"]')).toHaveText('10,00')
    await expect(page.locator('[data-testid="production-rate-avg"]')).not.toHaveText('3,64')

    // The coverage card shows the eleven filled slots those four readings
    // came out of: the two numbers disagree ON PURPOSE, and both are shown.
    await expect(page.locator('[data-testid="recording-coverage"]')).toBeVisible()
    await expect(page.locator('[data-testid="coverage-filled-slots"]')).toHaveText('11')
  })

  // =====================================================================
  // Scenario 8: "downtime tercatat bersamaan dengan laju"
  // =====================================================================
  test('laju dan downtime: satu jam berlaju 10,0 dengan downtime 20 tetap menyumbang 10,0 ton', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_RATEDOWN)

    // >> OPEN QUESTION, STILL PENDING THE PROCESS OWNER — not decided here.
    // >> 10 x 40/60 = 6,67 is the netted figure; this report does not
    // >> publish it. If the owner ever decides downtime must be subtracted,
    // >> this assertion changes with the formula.
    await expect(page.locator('[data-testid="summary-production-total"]')).toHaveText('10,0')
    await expect(page.locator('[data-testid="summary-production-total"]')).not.toHaveText('6,7')
    await expect(page.locator('[data-testid="summary-downtime-total"]')).toHaveText('20')

    // Which is precisely why the two must be read side by side — and the
    // page says so.
    await expect(page.locator('[data-testid="downtime-production-note"]')).toBeVisible()
    await expect(page.locator('[data-testid="downtime-production-note"]')).toContainText('tidak saling mengurangi')
  })

  // =====================================================================
  // Scenario 9: "downtime tidak pernah tercatat"
  // =====================================================================
  test('downtime nol vs tidak tercatat: kedua keadaan terbaca berbeda, dan jumlah pembacaannya membedakannya', async ({ page }) => {
    await openReport(page, SUPERVISOR)

    // Never recorded — words, deliberately not a 0.
    await selectPeriod(page, PERIOD_NORATE)
    await expect(page.locator('[data-testid="summary-downtime-total"]')).toHaveText('tidak tercatat')
    await expect(page.locator('[data-testid="summary-downtime-reading-count"]')).toHaveText('0')

    // Recorded, and genuinely zero.
    await selectPeriod(page, PERIOD_ZERODOWN)
    await expect(page.locator('[data-testid="summary-downtime-total"]')).toHaveText('0')
    await expect(page.locator('[data-testid="summary-downtime-reading-count"]')).toHaveText('3')

    // 0 means "it never stopped"; "tidak tercatat" means "nobody measured".
    // Merging them would publish a reliability figure never taken.
    await expect(page.locator('[data-testid="summary-downtime-total"]')).not.toHaveText('tidak tercatat')
  })

  // =====================================================================
  // Scenario 10: "pencatatan sangat tidak lengkap"
  // =====================================================================
  test('kelengkapan rendah: kartu kelengkapan menonjol di atas seluruh angka, yang tetap tampil dengan pembacaan kecil', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_SPARSE)

    await expect(page.locator('[data-testid="recording-coverage"]')).toBeVisible()
    await expect(page.locator('[data-testid="coverage-filled-slots"]')).toHaveText('2')
    // 1 unit x 10 hari x 24 slot.
    await expect(page.locator('[data-testid="coverage-expected-slots"]')).toHaveText('240')
    await expect(page.locator('[data-testid="low-coverage-emphasis"]')).toBeVisible()
    await expect(page.locator('[data-testid="low-coverage-emphasis"]')).toContainText('bukan berarti berproduksi nol')

    // PROMINENT, NOT A FOOTNOTE: the coverage card sits above the production
    // card in the document AND is visible without scrolling, because on this
    // screen a gap in the recording lowers the derived production figure
    // itself.
    const coverageBox = await page.locator('[data-testid="recording-coverage"]').boundingBox()
    const productionBox = await page.locator('[data-testid="production-card"]').boundingBox()

    expect(coverageBox, 'the coverage card is not rendered').not.toBeNull()
    expect(productionBox, 'the production card is not rendered').not.toBeNull()
    expect(coverageBox!.y).toBeLessThan(productionBox!.y)

    // The production figure is still shown, beside its own reading count.
    await expect(page.locator('[data-testid="summary-production-total"]')).toHaveText('20,0')
    await expect(page.locator('[data-testid="summary-production-reading-count"]')).toHaveText('2')
  })

  // =====================================================================
  // Scenario 11: "mill punya beberapa unit Clarification"
  //
  // PARTIALLY SEEDABLE. The spec's third unit has a record with NOT ONE
  // filled reading, and that cannot be entered here: FormClarification
  // refuses a record whose rows are all empty ("Minimal satu baris
  // Clarification Detail harus diisi"), so such a record can only arrive
  // from a legacy import. UNIT_MULTI_C therefore carries a FINDINGS-ONLY
  // row — the closest shape the product's own screens can produce: a unit
  // that recorded something but not one number, whose every numeric column
  // reads as unavailable.
  //
  // The reading_count-0 unit IS covered where the row can be built directly:
  //   - ClarificationReportServiceTest case 22;
  //   - the "beberapa unit" scenarios in the Api and Livewire tests, which
  //     both assert the CLF-03 row with reading_count 0 and null everywhere.
  // =====================================================================
  test('beberapa unit: rekap per unit memuat setiap unit sementara angka periode menggabungkan semuanya', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MULTI)

    await expect(page.locator('[data-testid="by-unit-table"]')).toBeVisible()
    await expect(page.locator(`[data-testid="by-unit-row-${UNIT_MULTI_A}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="by-unit-row-${UNIT_MULTI_B}"]`)).toBeVisible()
    // The unit that recorded no number at all is NOT dropped — it is the one
    // worth seeing.
    await expect(page.locator(`[data-testid="by-unit-row-${UNIT_MULTI_C}"]`)).toBeVisible()

    await expect(page.locator(`[data-testid="by-unit-row-${UNIT_MULTI_A}"]`)).toContainText('20,0')
    await expect(page.locator(`[data-testid="by-unit-row-${UNIT_MULTI_B}"]`)).toContainText('15,0')

    // Its production column reads as unavailable, never as 0,0 ton.
    const unitC = await page.locator(`[data-testid="by-unit-row-${UNIT_MULTI_C}"]`).innerText()
    expect(unitC).not.toContain('0,0')

    // The top figure is the combination of every unit: 20,0 + 15,0.
    await expect(page.locator('[data-testid="summary-production-total"]')).toHaveText('35,0')
    await expect(page.locator('[data-testid="summary-production-reading-count"]')).toHaveText('2')
    await expect(page.locator('[data-testid="by-unit-row-total"]')).toContainText('35,0')
  })

  // =====================================================================
  // Scenario 12: "akun belum terhubung ke mill"
  //
  // NOT SEEDABLE THROUGH THE PRODUCT'S OWN SCREENS. UserService::validate()
  // makes business_unit_id REQUIRED for every role except Admin ("Business
  // Unit wajib dipilih untuk role selain Admin."), so a Supervisor / Mill
  // Management account with a NULL mill cannot be created from Kelola User &
  // Role, nor through /api/users. Such a row can only come from a broken
  // import or a direct database edit.
  //
  // The behaviour IS covered where the row can be built directly:
  //   - the SPY case (33) in ClarificationReportServiceTest proves the
  //     all-mills list is never even read;
  //   - the "akun tanpa mill" scenarios in the Api and Livewire tests assert
  //     the 422 / the contact-Admin notice with no mill picker at all, and
  //     the Api one carries the same spy.
  // =====================================================================
  test.skip('akun tanpa mill: pesan menghubungi Admin, tanpa pemilih mill dan tanpa angka', async () => {
    // Intentionally unimplemented — see the comment block above.
  })

  // =====================================================================
  // Scenario 13: "mencoba melihat mill lain"
  // =====================================================================
  test('mill lain: query string business_unit_id diabaikan dan mill akun tetap yang tampil', async ({ page }) => {
    // Read another mill's id from the Admin picker — no mill id is
    // hardcoded, since that is environment data.
    await openReport(page, ADMIN)

    let otherMillId: string | null = null
    let otherMillName = ''

    for (const option of await page.locator('[data-testid="mill-selector"] option').all()) {
      const value = await option.getAttribute('value')
      const label = (await option.textContent())?.trim() ?? ''

      if (value && label !== BUSINESS_UNIT) {
        otherMillId = value
        otherMillName = label
        break
      }
    }

    expect(otherMillId, 'this environment has only one mill, so cross-mill probing cannot be exercised').not.toBeNull()

    await page.context().clearCookies()
    await login(page, SUPERVISOR, PASSWORD)

    // First request — another mill named in the query string. Answered with
    // the caller's OWN data rather than a 403, which would confirm the other
    // mill exists. (The component does not bind `businessUnitId` to the URL
    // at all, so for a bound role the parameter cannot even reach the
    // property — a stronger form of the same guard.)
    await page.goto(`${REPORT_PATH}?business_unit_id=${otherMillId}`)

    await expect(page.locator('[data-testid="laporan-clarification"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="mill-selector"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="laporan-clarification"]')).not.toContainText(otherMillName)

    await selectPeriod(page, PERIOD_MAIN)
    await expect(page.locator('[data-testid="summary-production-total"]')).toHaveText('40,0')

    // Second request — another mill's PERIOD named in the query string. It
    // is not among this mill's periods, so no other mill's figure is ever
    // rendered.
    await page.goto(`${REPORT_PATH}?period_id=00000000-0000-0000-0000-000000000000`)

    await expect(page.locator('[data-testid="laporan-clarification"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="laporan-clarification"]')).not.toContainText(otherMillName)
  })

  // =====================================================================
  // Scenario 14: "Operator mencoba membuka layar web ini"
  // =====================================================================
  test('operator: akses laporan WEB ditolak dan tidak ada angka Clarification yang terlihat', async ({ page }) => {
    await login(page, OPERATOR, PASSWORD)
    await page.goto(REPORT_PATH)

    // EnsureRole -> abort(403): the error page, never the report. There is
    // NO Operator widening here — screen-138, the mobile Clarification
    // report, does not exist.
    await expect(page.locator('body')).toContainText(/403|Forbidden/i)
    await expect(page.locator('[data-testid="laporan-clarification"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="recording-coverage"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="summary-production-total"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="by-unit-table"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="daily-recap-table"]')).toHaveCount(0)
  })

  // =====================================================================
  // Scenario 15: "periode tertutup"
  // =====================================================================
  test('periode tertutup: status Tertutup, laporan penuh, dan unduhan CSV tetap berhasil', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_CLOSED)

    await expect(page.locator('[data-testid="period-status"]')).toHaveText(/Tertutup/)
    await expect(page.locator('[data-testid="recording-coverage"]')).toBeVisible()
    await expect(page.locator('[data-testid="summary-production-total"]')).toHaveText('22,0')
    await expect(page.locator('[data-testid="summary-production-reading-count"]')).toHaveText('2')
    await expect(page.locator('[data-testid="by-unit-table"]')).toBeVisible()
    // The period lock governs writing data, not reading a report.
    await expect(page.locator('[data-testid="export-csv-button"]')).toBeEnabled()

    const csv = await downloadCsv(page)
    expect(csv).toContain(CLOSED.start)
  })

  // =====================================================================
  // Scenario 16: "rekap harian panjang"
  // =====================================================================
  test('rekap harian: tombol menutup tabelnya lalu membukanya kembali, angka utama dan tren tetap terlihat', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MAIN)

    // OPEN on first paint — the first click CLOSES it.
    await expect(page.locator('[data-testid="daily-recap-table"]')).toBeVisible()

    await toggleRecap(page)
    // Genuinely absent from the DOM, not merely collapsed.
    await expect(page.locator('[data-testid="daily-recap-table"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="daily-recap-toggle"]')).toBeVisible()
    // The headline figures and the trend survive both states, readable
    // without long scrolling.
    await expect(page.locator('[data-testid="summary-production-total"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-trend"]')).toBeVisible()

    await toggleRecap(page)
    await expect(page.locator('[data-testid="daily-recap-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="summary-production-total"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-trend"]')).toBeVisible()

    // Both recap tables scroll INSIDE their own card — the page itself never
    // scrolls horizontally, however many columns they carry.
    const overflows = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    )
    expect(overflows).toBe(false)
  })

  // =====================================================================
  // Scenario 17: "produksi diturunkan dari laju dan jumlah pembacaannya ditampilkan"
  // =====================================================================
  test('produksi turunan: total dan jumlah pembacaannya berada di dalam kartu yang sama, berdampingan', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MAIN)

    await expect(page.locator('[data-testid="summary-production-total"]')).toHaveText('40,0')
    await expect(page.locator('[data-testid="summary-production-reading-count"]')).toHaveText('4')

    // SAME CARD, ADJACENT BOUNDING BOX — not a page footnote. A total from 4
    // readings and a total from 400 must not look equally convincing, and a
    // denominator the reader has to hunt for does not do that job.
    const card = page.locator('[data-testid="production-card"]')
    await expect(card).toContainText('40,0')
    await expect(card).toContainText('4')

    const totalBox = await page.locator('[data-testid="summary-production-total"]').boundingBox()
    const countBox = await page.locator('[data-testid="summary-production-reading-count"]').boundingBox()

    expect(totalBox).not.toBeNull()
    expect(countBox).not.toBeNull()
    // Same line of the same card: a small vertical distance, not a different
    // region of the page.
    expect(Math.abs(totalBox!.y - countBox!.y)).toBeLessThan(40)

    await expect(page.locator('[data-testid="production-derived-note"]')).toBeVisible()
    await expect(page.locator('[data-testid="production-derived-note"]')).toContainText('Diturunkan, bukan dicatat')
  })

  // =====================================================================
  // Scenario 18: "setiap metrik punya penyebutnya sendiri"
  // =====================================================================
  test('penyebut terpisah: tiap kartu menampilkan jumlah pembacaannya sendiri — 4, 9, 6 dan 3', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MAIN)

    await expect(page.locator('[data-testid="summary-production-reading-count"]')).toHaveText('4')
    await expect(page.locator('[data-testid="sludge-temp-reading-count"]')).toHaveText('9')
    await expect(page.locator('[data-testid="summary-downtime-reading-count"]')).toHaveText('6')
    await expect(page.locator('[data-testid="buffer-level-reading-count"]')).toHaveText('3')
    await expect(page.locator('[data-testid="clarification-temp-reading-count"]')).toHaveText('7')
    await expect(page.locator('[data-testid="oil-temp-reading-count"]')).toHaveText('5')

    // Each average consistent with ITS OWN denominator: 40,0/4 = 10,00 and
    // (9 x 87,0)/9 = 87,0. A shared denominator of 11 would give 3,64 and
    // 71,2 — both entirely plausible, which is the danger.
    await expect(page.locator('[data-testid="production-rate-avg"]')).toHaveText('10,00')
    await expect(page.locator('[data-testid="sludge-temp-avg"]')).toHaveText('87,0')
    await expect(page.locator('[data-testid="sludge-temp-avg"]')).not.toHaveText('71,2')

    // Every count is visible without a click or a hover, beside its number.
    for (const card of ['clarification-temp', 'oil-temp', 'sludge-temp', 'buffer-level']) {
      await expect(page.locator(`[data-testid="${card}-avg"]`)).toBeVisible()
      await expect(page.locator(`[data-testid="${card}-reading-count"]`)).toBeVisible()
    }
  })

  // =====================================================================
  // Scenario 19: "rentang periode inklusif di kedua ujung"
  // =====================================================================
  test('rentang inklusif: baris tanggal awal dan akhir ada pada rekap dan CSV, tanggal di luar rentang tidak', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MAIN)

    await expect(page.locator(`[data-testid="daily-recap-row-${MAIN.start}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="daily-recap-row-${MAIN.end}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="daily-recap-row-${OUTSIDE_DATE}"]`)).toHaveCount(0)

    // The production trend carries a column for each dated record and none
    // for the one outside the window.
    await expect(page.locator(`[data-testid="daily-production-col-${MAIN.start}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="daily-production-col-${MAIN.end}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="daily-production-col-${OUTSIDE_DATE}"]`)).toHaveCount(0)

    // The day before the window carried 999 — none of it is here.
    await expect(page.locator('[data-testid="laporan-clarification"]')).not.toContainText('999,0')

    // And the CSV agrees with the page.
    const csv = await downloadCsv(page)

    expect(csv).toContain(MAIN.start)
    expect(csv).toContain(MAIN.end)
    expect(csv).not.toContain(OUTSIDE_DATE)
  })

  // =====================================================================
  // Scenario 20: "ketiga suhu tangki pada satu grafik"
  // =====================================================================
  test('satu grafik: tepat satu elemen grafik suhu memuat ketiga seri, tanpa grafik per tangki', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    // PERIOD_TEMPS, not PERIOD_MAIN: three dated readings per tank, so each
    // series is drawn as a LINE whose colour and dash pattern can be
    // measured — a single-point series renders only a dot.
    await selectPeriod(page, PERIOD_TEMPS)

    // EXACTLY ONE. Three separate charts would satisfy "tren suhu antar
    // tangki" literally while destroying its point: what is read is the GAP
    // between the tanks, and a gap needs one shared axis.
    await expect(page.locator('[data-testid="tank-temperature-chart"]')).toHaveCount(1)

    for (const forbidden of ['clarification-temp-chart', 'oil-temp-chart', 'sludge-temp-chart']) {
      await expect(page.locator(`[data-testid="${forbidden}"]`)).toHaveCount(0)
    }

    // The legend names all three tanks.
    const card = page.locator('[data-testid="tank-temperature-card"]')
    await expect(card).toContainText('Tangki Clarification')
    await expect(card).toContainText('Tangki Minyak')
    await expect(card).toContainText('Tangki Sludge')

    // TWO-LAYER SERIES DISTINCTION — colour AND dash pattern, so the chart
    // is readable without relying on colour at all.
    const chart = page.locator('[data-testid="tank-temperature-chart"]')
    await expect(chart.locator('.md-lc__line--s1')).toHaveCount(1)
    await expect(chart.locator('.md-lc__line--s2')).toHaveCount(1)
    await expect(chart.locator('.md-lc__line--s3')).toHaveCount(1)

    const strokes = await chart.locator('.md-lc__line').evaluateAll((nodes) => nodes.map((node) => {
      const style = window.getComputedStyle(node)

      return { stroke: style.stroke, dash: style.strokeDasharray }
    }))

    expect(strokes).toHaveLength(3)
    // Three distinct colours...
    expect(new Set(strokes.map((entry) => entry.stroke)).size).toBe(3)
    // ...AND three distinct dash patterns, which is what survives greyscale
    // and colour blindness.
    expect(new Set(strokes.map((entry) => entry.dash)).size).toBe(3)

    // The card states the axis is not zero-based and that nothing is
    // flagged — it is an explanation, not a threshold.
    await expect(page.locator('[data-testid="tank-gap-note"]')).toContainText('SELISIH antar tangki')
  })

  // =====================================================================
  // Scenario 21: "tidak ada penandaan nilai di luar batas"
  // =====================================================================
  test('tanpa ambang: nilai ekstrem tampil netral tanpa warna peringatan, ikon, maupun label pelanggaran', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_EXTREME)

    // The 250,0 / 0,5 pair IS rendered — it is simply never judged.
    // Clarification has no operational-target master, so any threshold here
    // would be a statistic dressed up as a SAFETY limit.
    await expect(page.locator('[data-testid="sludge-temp-max"]')).toHaveText('250,0')
    await expect(page.locator('[data-testid="buffer-level-min"]')).toHaveText('0,5')

    for (const badge of [
      'threshold-card', 'threshold-badge', 'outlier-badge', 'iqr-summary',
      'danger-indicator', 'alert-label', 'out-of-range-icon',
    ]) {
      await expect(page.locator(`[data-testid="${badge}"]`)).toHaveCount(0)
    }

    for (const selector of ['.is-danger', '.is-warning', '.text-red-600', '.md-chip--danger', '.md-chip--warning']) {
      await expect(page.locator(selector)).toHaveCount(0)
    }

    const html = await page.locator('[data-testid="laporan-clarification"]').innerHTML()

    for (const flavour of [
      'text-red', 'bg-red', 'text-amber', 'bg-amber', 'is-danger', 'is-warning',
      'md-trendchart__col--low', 'md-trendchart__col--high',
      'is_out_of_range', 'out_of_range', 'outlier', 'iqr', 'severity',
    ]) {
      expect(html, `the report must carry no "${flavour}" styling or flag`).not.toContain(flavour)
    }

    // THE CARD HOLDING THE EXTREME VALUE CARRIES THE SAME class ATTRIBUTE AS
    // AN ORDINARY ONE. The absence of styling is what makes the "do not
    // flag" rule visible, and only a comparison can assert it.
    const extremeClass = await page.locator('[data-testid="sludge-temp-summary"]').getAttribute('class')
    const ordinaryClass = await page.locator('[data-testid="clarification-temp-summary"]').getAttribute('class')

    expect(extremeClass).not.toBeNull()
    expect(extremeClass).toBe(ordinaryClass)

    // And the production trend bars carry no colour modifier either.
    const firstBar = page.locator(`[data-testid="daily-production-col-${EXTREME.start}"]`)
    await expect(firstBar).toHaveClass('md-trendchart__col')
  })

  // =====================================================================
  // Scenario 22: "layar hanya membaca"
  // =====================================================================
  test('baca saja: tidak ada tombol simpan, ubah, atau hapus, dan angka tidak berubah setelah dibaca ulang', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MAIN)

    // Walk the whole page, including both recap tables.
    await expect(page.locator('[data-testid="by-unit-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-recap-table"]')).toBeVisible()

    // Change the period twice, then export — the three interactions the
    // screen actually offers.
    await selectPeriod(page, PERIOD_NORATE)
    await selectPeriod(page, PERIOD_MAIN)
    await downloadCsv(page)

    for (const control of ['save-button', 'edit-button', 'delete-button', 'add-row-button', 'remove-row-button-0']) {
      await expect(page.locator(`[data-testid="${control}"]`)).toHaveCount(0)
    }

    // No form element that could post anything either.
    await expect(page.locator('[data-testid="laporan-clarification"] form')).toHaveCount(0)
    await expect(page.locator('[data-testid="laporan-clarification"] button[type="submit"]')).toHaveCount(0)

    // And the figures are identical after a full reload — nothing about
    // opening the page changed the data it reports on.
    await page.reload()
    await selectPeriod(page, PERIOD_MAIN)
    await expect(page.locator('[data-testid="summary-production-total"]')).toHaveText('40,0')
    await expect(page.locator('[data-testid="summary-production-reading-count"]')).toHaveText('4')
    await expect(page.locator('[data-testid="coverage-filled-slots"]')).toHaveText('11')
  })

  // =====================================================================
  // Scenario 23: "daftar periode hanya yang mencakup Clarification"
  // =====================================================================
  test('pemilih periode: hanya periode yang punya baris Clarification, bukan periode tanpa baris itu', async ({ page, browser }) => {
    await openReport(page, SUPERVISOR)

    const options = page.locator('[data-testid="period-selector"] option')

    await expect(options.filter({ hasText: PERIOD_MAIN })).toHaveCount(1)
    await expect(options.filter({ hasText: PERIOD_WHOLE_MILL })).toHaveCount(1)
    // A period of another mill — one with no active station at all, so with
    // no `period_stations` row either — is not offered.
    await expect(options.filter({ hasText: PERIOD_OTHER_MILL })).toHaveCount(0)

    // Newest first: this run's latest offered window is the whole-mill one,
    // so it leads the main period.
    const labels = await options.allTextContents()
    const wholeMillIndex = labels.findIndex((label) => label.includes(PERIOD_WHOLE_MILL))
    const mainIndex = labels.findIndex((label) => label.includes(PERIOD_MAIN))

    expect(wholeMillIndex).toBeGreaterThanOrEqual(0)
    expect(mainIndex).toBeGreaterThanOrEqual(0)
    expect(wholeMillIndex).toBeLessThan(mainIndex)
    // The option is labelled with THIS screen's station type, never blank and
    // never the "Semua Stasiun" wording the NULL scope used to produce —
    // periodOption().station_type is not nullable any more.
    expect(labels[wholeMillIndex]).toContain(STATION_TYPE)

    // WHY THE PERIOD IS OFFERED, checked at the source rather than inferred:
    // it HAS a `period_stations` row for this screen's station type. That row
    // is what the picker's whereHas() matches, and it is visible on
    // screen-128 once the period's station rows are expanded.
    const adminContext = await browser.newContext()
    const adminPage = await adminContext.newPage()

    try {
      await login(adminPage, ADMIN, PASSWORD)
      await adminPage.goto(PERIODS_PATH)
      const { stationId } = await openStationRow(adminPage, PERIOD_WHOLE_MILL, STATION_TYPE)
      await expect(adminPage.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Draft')
    } finally {
      await adminContext.close()
    }
  })
})
