/**
 * Laporan Weighbridge (Browser/Playwright) — screen-143--laporan-weighbridge-web /
 * usecase-146--laporan-weighbridge-web.
 *
 * One test per test_scenarios entry whose `browser_test` is non-empty (all
 * 35). Route is /reports/weighbridge — the repo's report prefix is English
 * (/reports/management, /reports/stations, /reports/cages-track,
 * /reports/sterilizer, /reports/boiler-room, /reports/clarification,
 * /reports/storage-tank); no route in this app uses /laporan.
 *
 * SELF-SUFFICIENT DATA, because this screen cannot create any: the report is
 * READ-ONLY by design. The fixtures are built once, in beforeAll, through the
 * two screens that DO own that data:
 *   - /master-data/periods       (screen-128, as Admin)      — every period,
 *     including the one that is closed afterwards;
 *   - /data/weighbridge/create   (screen-022, as Supervisor) — the trips.
 *
 * =========================================================================
 * THREE STATES THIS SPEC CANNOT CREATE, AND WHY THAT IS NOT A GAP
 * =========================================================================
 * Three scenarios below need a trip shape that NO WEB SCREEN CAN PRODUCE.
 * They are not skipped and they are not faked — each asserts the part of its
 * `assert` that IS browser-reachable, and names where the unreachable half is
 * covered. Inventing a database write from a browser spec would prove nothing
 * about the browser.
 *
 *   1. A TRIP WITH NO WEIGHING TIMESTAMP (scenario 10). record_datetime is
 *      ['required','date'] in WeighbridgeRecordService::validateForm(), so the
 *      form refuses to save one. Such rows exist only from a legacy/mobile
 *      path — which is exactly why undated_trip_count exists. What IS
 *      asserted here: the undated figure is rendered as a FIGURE OF ITS OWN in
 *      the completeness section, with the explanation that it counts towards
 *      nothing else. The non-zero case is asserted in
 *      backend/tests/Unit/Services/WeighbridgeReportServiceTest.php case 18,
 *      and in the Api / Livewire suites.
 *
 *   2. A DISPATCH TRIP WITH NO DESTINATION (scenario 14). `destination` is
 *      REQUIRED for weighbridge_type=dispatch in the same validator. What IS
 *      asserted here is the invariant the scenario actually turns on: the
 *      per-destination trip counts add up EXACTLY to the headline outgoing
 *      trip count, so no trip is missing from the recap. The "Belum diisi"
 *      group itself is asserted in the unit (case 31/46), Api (scenario 14)
 *      and Livewire (scenario 14) suites.
 *
 *   3. A DRAFT TRIP (scenario 16). WeighbridgeRecordService::create() writes
 *      status='saved' unconditionally; draft_ongoing / draft_paused arrive
 *      only from the mobile sync path. What IS asserted here: the draft count
 *      is rendered as its own row, stating in words that drafts are COUNTED
 *      in every figure rather than filtered out of them.
 *
 * =========================================================================
 * THE SEEDED NUMBERS ARE LOAD-BEARING. Do not "tidy" them.
 * =========================================================================
 * WEIGHTS ARE RAW KILOGRAMS. The form labels gross/tare/net "(kg)", the Data
 * Browser does too, and the report does no conversion at all — so every
 * expected string below is a kilogram figure in Indonesian formatting
 * (10.000,00). The screen mock's "ton" is not the repo convention.
 *
 * A NULL NET WEIGHT IS SEEDED BY LEAVING TARE EMPTY. net_weight is computed
 * (gross - tare) and the form has no net field at all; gross_weight is
 * required and NOT NULL in the schema. So "the weighing is not finished" is
 * exactly "gross taken, tare not yet", and that is how every unweighed trip
 * below is built.
 *
 * PERIOD_MAIN — 10 days, BOTH flows, deliberately asymmetric so a wrong
 *   implementation gives a DIFFERENT number rather than the same one:
 *     receive  MAIN+0 06:10  Estate Besar    gross 12000 tare 2000 -> 10.000
 *     receive  MAIN+0 06:40  Estate Kecil    gross  3000 tare 2000 ->  1.000
 *     receive  MAIN+1 13:20  Estate Besar    gross  7000 tare 2000 ->  5.000
 *     receive  MAIN+2 13:50  Estate Kecil    gross  9000 (NO TARE) ->  kosong
 *     dispatch MAIN+3 20:15  Refinery X      gross 22000 tare 2000 -> 20.000
 *     dispatch MAIN+4 20:45  Port Y          gross  6000 tare 2000 ->  4.000
 *   => receive: 4 trips, 3 weighed, total 16.000,00, average 5.333,33,
 *      1 unfinished; busiest hour 06.00 with 2; 22 empty hours.
 *   => dispatch: 2 trips, 2 weighed, total 24.000,00, average 12.000,00;
 *      busiest hour 20.00 with 2; 23 empty hours.
 *   => The two flows are NEVER summed: 6 trips and 40.000,00 kg are numbers
 *      that must appear NOWHERE on the page, and scenario 23 says so.
 *   => 5 dates with a trip out of 10 days in the period.
 *
 * PERIOD_EMPTY    — no trip at all.
 * PERIOD_RECEIVE  — incoming only, so the OUTGOING block must still render in
 *                   full, with its values reading "tidak tersedia".
 * PERIOD_NOWEIGHT — every trip with the tare left empty: totals and averages
 *                   read "tidak tersedia", the filled-trip count reads 0, and
 *                   the trip count still reads the real number.
 * PERIOD_PARTIAL  — 5 incoming trips, only 3 weighed (1.000 + 2.000 + 3.000):
 *                   the average is 2.000,00 = 6.000 / 3, NEVER 1.200,00 =
 *                   6.000 / 5, and the denominator 3 is printed beside it.
 *                   Also carries scenario 24, whose 10/6 figures are
 *                   illustrative of the same rule.
 * PERIOD_HOUR     — every trip inside hour 06: one filled column, 23 empty,
 *                   empty-hour count 23, and the chart still drawn.
 * PERIOD_SKEWED   — one dominant origin plus three one-trip origins: four
 *                   rows, none trimmed, no "lain-lain" bucket.
 * PERIOD_CLOSED   — trips first, THEN the Weighbridge station of that period
 *                   is closed: a closed period stays fully readable AND fully
 *                   exportable.
 * PERIOD_LONG     — 20 days with trips on 5 dates, for the recap toggle.
 * PERIOD_INCLUSIVE— trips exactly on the first day and exactly on the last
 *                   day, plus one the day BEFORE and one the day AFTER
 *                   carrying 99.000 / 88.000 which must appear nowhere.
 * PERIOD_EXTREME  — 999.999 beside 1,00: rendered as-is, flagged nowhere.
 * PERIOD_OTHER_MILL — a period of a mill with no active station at all, hence
 *                   with no Weighbridge `period_stations` row: the only
 *                   browser-reachable shape of "a period this screen must not
 *                   offer".
 *
 * WINDOWS ARE UNIQUE PER RUN AND SIT IN THIS SPEC'S OWN DATE LANE. A period
 * may not overlap another IN THE SAME MILL — since 2026-09-26 the overlap rule
 * ignores the station type entirely — so every window is derived from
 * RUN_OFFSET and laid out end to end by a cursor, and RUN_OFFSET itself is
 * pushed into this spec's lane by laneOffset(..., 'weighbridge'). See
 * tests/support/period-lanes.ts; this spec's lane entry was added with it.
 *
 * ROW-CREATION TIME IS NOT WEIGHING TIME, AND THIS LANE PROVES IT FOR FREE.
 * Every trip below is entered TODAY (created_at ~2026) but carries a
 * record_datetime in year 2600 — centuries outside its own period by
 * row-creation time. Every figure still lands in the period the WEIGHING
 * belongs to, which is scenarios 9 and 27 end to end.
 *
 * CLEANUP IS MANDATORY, not tidiness. Without the deletePeriodsByPrefix() call
 * in afterAll this suite poisons itself: the period list is paginated at 20
 * rows ordered by start_date DESC and this spec adds 12 rows per run, so after
 * a couple of further runs createPeriod() fails on its own toBeVisible() check
 * before a single behavioural assertion has run. The weighbridge_records rows
 * are removed by globalTeardown (php artisan e2e:prune-records).
 *
 * NO THRESHOLD ASSERTIONS ANYWHERE — the opposite. Weighbridge has no
 * operational-target master table, so scenario 33 asserts the ABSENCE of a
 * warning colour, icon or badge BY NAME, and requires the extreme row's markup
 * to be byte-identical to an ordinary row's.
 *
 * EXPORT IS A LIVEWIRE ACTION, not an <a href>: the button carries
 * wire:click="exportCsv('csv')", so the assertion waits for Playwright's
 * download event fired by Livewire's client-side download handler.
 */

import { readFile } from 'node:fs/promises'
import { test, expect, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { pruneLaneData } from './support/backend'
import { deletePeriodsByPrefix } from './support/periods'
import { closeStation, createOpenPeriodViaUi } from './support/period-screen'
import { laneIsoDate, laneOffset } from './support/period-lanes'
import { STATEFUL_REFERER } from './support/base-url'

const REPORT_PATH = '/reports/weighbridge'
const PERIODS_PATH = '/master-data/periods'
const WEIGHBRIDGE_FORM_PATH = '/data/weighbridge/create'

/** The mill these fixtures live in — the same one the other report specs use. */
const BUSINESS_UNIT = 'Business Unit A'
const STATION_TYPE = 'Weighbridge'

/**
 * The mill of the period this screen must NOT offer.
 *
 * listPeriods() offers a period when it belongs to the caller's mill AND has a
 * `period_stations` row for this screen's station type. Within one provisioned
 * mill that second clause can no longer be made false from the UI: creating a
 * period registers a row for EVERY station type the mill has. "Mill Kode
 * Duplikat" has no active station at all, so its period gets NO station row
 * whatsoever — it fails both clauses at once, and it is the only
 * browser-reachable shape of "a period that does not cover this station type".
 * The station-row clause on its own is asserted where it can be produced
 * directly, in backend/tests/Feature/Api/LaporanWeighbridgeTest.php.
 */
const OTHER_MILL = 'Mill Kode Duplikat'

const SUPERVISOR = 'supervisor01'
const MILL_MANAGEMENT = 'millmanagement-a'
const ADMIN = 'admin'
/** Operator is a mobile-only actor, and the mobile Weighbridge report
 *  (screen-144) is not built yet — so there is no Operator widening anywhere
 *  on this screen, web or API. */
const OPERATOR = 'operator01'

/**
 * A per-run day offset, so two runs never collide.
 *
 * THE STRIDE IS LOAD-BEARING. Periods are deleted in afterAll, but if one
 * run's windows could fall inside the next run's windows, the older run's
 * trips would be silently counted by the newer run's report and every figure
 * below would drift. One run's whole fixture spans ~75 days, so the seconds
 * counter is multiplied by 120 for a 120-day stride; laneOffset() then moves
 * the result into this spec's own lane, which is what keeps it clear of the
 * five other period-seeding specs that share "Business Unit A".
 */
const RUN_OFFSET = laneOffset((Math.floor(Date.now() / 1000) % 20000) * 120, 'weighbridge')

/** Name prefix every period of this spec carries — also the cleanup key. */
const PERIOD_PREFIX = 'Weighbridge '

/**
 * laneIsoDate(): ONE epoch (1970, in the past since 2026-10-04 — the server
 * now rejects event dates later than tomorrow) for every period-seeding
 * spec. Separation from
 * the other specs comes from laneOffset(), not from the epoch — a century is
 * not wide enough to separate day offsets that reach millions.
 */
function isoDate(dayOffset: number): string {
  return laneIsoDate(dayOffset)
}

/** 'YYYY-MM-DDTHH:mm' for the form's datetime-local input. */
function isoDateTime(dayOffset: number, time: string): string {
  return `${isoDate(dayOffset)}T${time}`
}

/** The month abbreviations the screen renders — must match the blade's map. */
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']

/** "01 Sep 1985" — the date label the hero and the daily recap print. */
function dateLabel(dayOffset: number): string {
  const [year, month, day] = isoDate(dayOffset).split('-')

  return `${day} ${MONTHS[Number(month) - 1]} ${year}`
}

/**
 * Windows are laid out END TO END by a cursor with a three-day gap, rather
 * than at fixed slots: they range from one day to twenty, and a fixed spacing
 * would have the long ones overlap their neighbours — and every window of this
 * spec must keep clear of every other one, because the overlap rule is per
 * mill and no longer looks at the station type at all.
 *
 * The cursor starts two days AFTER RUN_OFFSET so the inclusive-range
 * scenario's "day before" trip still belongs to this run's lane.
 */
let cursor = RUN_OFFSET + 2

function nextWindow(days = 1): { startDay: number; endDay: number; start: string; end: string } {
  const startDay = cursor
  cursor += days + 3

  return { startDay, endDay: startDay + days - 1, start: isoDate(startDay), end: isoDate(startDay + days - 1) }
}

const MAIN = nextWindow(10)
const EMPTY = nextWindow()
const RECEIVE_ONLY = nextWindow()
const NOWEIGHT = nextWindow()
const PARTIAL = nextWindow()
const HOUR = nextWindow()
const SKEWED = nextWindow()
const CLOSED = nextWindow()
const LONG = nextWindow(20)
const INCLUSIVE = nextWindow(5)
const EXTREME = nextWindow()
const OTHER_MILL_WINDOW = nextWindow()

const PERIOD_MAIN = `${PERIOD_PREFIX}Lengkap ${RUN_OFFSET}`
const PERIOD_EMPTY = `${PERIOD_PREFIX}Kosong ${RUN_OFFSET}`
const PERIOD_RECEIVE = `${PERIOD_PREFIX}Hanya Arus Masuk ${RUN_OFFSET}`
const PERIOD_NOWEIGHT = `${PERIOD_PREFIX}Berat Kosong ${RUN_OFFSET}`
const PERIOD_PARTIAL = `${PERIOD_PREFIX}Sebagian Tertimbang ${RUN_OFFSET}`
const PERIOD_HOUR = `${PERIOD_PREFIX}Satu Jam ${RUN_OFFSET}`
const PERIOD_SKEWED = `${PERIOD_PREFIX}Asal Dominan ${RUN_OFFSET}`
const PERIOD_CLOSED = `${PERIOD_PREFIX}Tertutup ${RUN_OFFSET}`
const PERIOD_LONG = `${PERIOD_PREFIX}Rekap Panjang ${RUN_OFFSET}`
const PERIOD_INCLUSIVE = `${PERIOD_PREFIX}Rentang Inklusif ${RUN_OFFSET}`
const PERIOD_EXTREME = `${PERIOD_PREFIX}Nilai Ekstrem ${RUN_OFFSET}`
const PERIOD_OTHER_MILL = `${PERIOD_PREFIX}Mill Lain ${RUN_OFFSET}`

/**
 * DUA PERIODE SATU HARI DI KEDUA SISI PERIOD_INCLUSIVE — ADA HANYA SUPAYA
 * TRIP DI LUAR JENDELA BISA DITANAM, sejak 2026-10-02.
 *
 * Skenario "rentang inklusif" menanam empat trip: tepat di kedua ujung
 * PERIOD_INCLUSIVE, dan satu hari di LUAR masing-masing ujung yang harus
 * tidak muncul di mana pun. Kunci periode (usecase-141) adalah WHITELIST:
 * tanggal yang tidak dimuat satu pun periode terbuka ditolak 422
 * PERIOD_CLOSED, jadi tanpa kedua periode ini dua trip itu tidak pernah
 * tersimpan — dan asersi toHaveCount(0) lolos karena datanya tidak ada,
 * bukan karena laporannya menyaring. Asersi yang selalu hijau adalah jenis
 * cacat termahal dalam suite ini.
 *
 * Keduanya satu hari, duduk di CELAH yang sudah ada antar-jendela (cursor
 * memberi jarak 3 hari), jadi tidak satu pun jendela lain tergeser dan tidak
 * ada yang tumpang tindih. Keduanya tidak pernah dipilih di pemilih periode,
 * jadi tidak mengubah satu pun angka yang diasersi.
 */
const PERIOD_BEFORE_INCLUSIVE = `${PERIOD_PREFIX}Sehari Sebelum Inklusif ${RUN_OFFSET}`
const PERIOD_AFTER_INCLUSIVE = `${PERIOD_PREFIX}Sehari Sesudah Inklusif ${RUN_OFFSET}`

/** WB card numbers of this run, unique so old records cannot blend in. */
const CARD = (suffix: string) => `WB-${RUN_OFFSET}-${suffix}`

/** Origin / destination labels of this run, unique for the same reason. */
const ESTATE_BESAR = `Estate Besar ${RUN_OFFSET}`
const ESTATE_KECIL = `Estate Kecil ${RUN_OFFSET}`
const ESTATE_DOMINAN = `Estate Dominan ${RUN_OFFSET}`
const REFINERY_X = `Refinery X ${RUN_OFFSET}`
const PORT_Y = `Port Y ${RUN_OFFSET}`
const ESTATE_LUAR = `Estate Di Luar ${RUN_OFFSET}`

/**
 * The production line the fixtures are entered on, resolved BY ID in
 * beforeAll — never by label.
 *
 * Selecting by label would be a silent correctness bug: this database has
 * several mills each carrying a line called "Line 1", so picking the first
 * label match would file the trips under whichever mill the database happened
 * to order first, while the period belongs to BUSINESS_UNIT — and the report
 * would then be correctly empty, which reads exactly like a broken report.
 */
let PRODUCTION_LINE_ID = ''
let PRODUCTION_LINE_NAME = ''

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
    Referer: STATEFUL_REFERER,
    Accept: 'application/json',
    ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf.value) } : {}),
  }
}

/**
 * Resolves, as Admin, a production line of BUSINESS_UNIT whose WEIGHBRIDGE
 * station is active — the only lines FormWeighbridge will accept, since
 * WeighbridgeRecordService::create() looks the station up by
 * (production_line_id, type='weighbridge', is_active=true) and refuses
 * anything else.
 */
async function resolveProductionLine(page: Page): Promise<void> {
  const headers = await statefulHeaders(page)

  const mills = await page.request.get('/api/weighbridge-reports/business-units/options', { headers })
  expect(mills.ok(), 'admin could not read the mill list').toBe(true)

  const mill = ((await mills.json()).data as Array<{ id: string; name: string }>)
    .find((row) => row.name === BUSINESS_UNIT)

  expect(mill, `this environment has no mill named "${BUSINESS_UNIT}"`).toBeTruthy()

  const stations = await page.request.get(
    `/api/stations?per_page=100&business_unit_id=${mill!.id}`,
    { headers },
  )
  expect(stations.ok(), 'admin could not read the station list').toBe(true)

  const weighbridge = ((await stations.json()).data as Array<{
    type: string
    is_active: boolean
    production_line_id: string
  }>).find((row) => row.type === 'weighbridge' && row.is_active && row.production_line_id)

  expect(
    weighbridge,
    `no active weighbridge station on any production line of "${BUSINESS_UNIT}", so no trip can be entered `
      + 'and this report would have nothing to report on',
  ).toBeTruthy()

  PRODUCTION_LINE_ID = weighbridge!.production_line_id

  const lines = await page.request.get(
    `/api/production-lines/options-for-report?business_unit_id=${mill!.id}`,
    { headers },
  )
  expect(lines.ok(), 'admin could not read the production line list').toBe(true)

  PRODUCTION_LINE_NAME = ((await lines.json()).data as Array<{ id: string; name: string }>)
    .find((row) => row.id === PRODUCTION_LINE_ID)?.name ?? ''

  expect(PRODUCTION_LINE_NAME, 'the resolved production line has no name in the report option list').not.toBe('')
}

// ---------------------------------------------------------------------
// Fixture builders (other screens' UI)
// ---------------------------------------------------------------------

/**
 * MEMBUAT PERIODE DAN LANGSUNG MEMBUKA BARIS STASIUNNYA, sejak 2026-10-02.
 *
 * Kunci periode (usecase-141) menjadikan periode TERBUKA prasyarat menulis
 * record stasiun: setiap *RecordService menolak 422 PERIOD_CLOSED bila tidak
 * ada periode `open` milik mill itu yang memuat tanggal record. Periode yang
 * baru dibuat selalu DRAFT, jadi sebelum perubahan ini seluruh fixture spec
 * ini ditolak oleh form-nya dan beforeAll menggantung sampai timeout —
 * BUKAN gagal dengan pesan yang menyebut periode.
 *
 * Mill tanpa baris stasiun berjenis ini (PERIOD_OTHER_MILL) dilewati tanpa
 * galat; tidak ada record yang ditanam di sana.
 */
async function createPeriod(
  page: Page,
  options: { name: string; start: string; end: string; businessUnit?: string },
): Promise<void> {
  await createOpenPeriodViaUi(page, {
    businessUnit: options.businessUnit ?? BUSINESS_UNIT,
    name: options.name,
    start: options.start,
    end: options.end,
    stationLabel: STATION_TYPE,
  })
}

interface TripOptions {
  type: 'receive' | 'dispatch'
  /** Day offset from the lane epoch (laneIsoDate, 1970). */
  day: number
  /** 'HH:mm' — the ONE timestamp a trip carries. There is no second one. */
  time: string
  card: string
  estateSupplier: string
  /** REQUIRED by the form for dispatch, and ignored for receive. */
  destination?: string
  gross: number
  /** OMIT IT to leave the weighing unfinished: net_weight then stays NULL.
   *  There is no net field on the form — net is gross minus tare. */
  tare?: number
}

/**
 * One weighbridge_records row, entered through screen-022's create form.
 *
 * ORDER MATTERS. The flow tab is clicked BEFORE any field is filled: the tab
 * is a `wire:click="$set(...)"` round trip, and the Tujuan Muatan field only
 * exists in the dispatch branch. Business Unit is `wire:model.live` and
 * clearing it resets production_line_id, so the line is chosen after the mill
 * and the option is waited for rather than assumed.
 */
async function createTrip(page: Page, options: TripOptions): Promise<void> {
  await page.goto(WEIGHBRIDGE_FORM_PATH)

  await page.locator('[data-testid="business-unit-select"]').selectOption({ label: BUSINESS_UNIT })
  // One Livewire round trip repopulates the line list; wait for the option
  // rather than racing it.
  await expect(
    page.locator(`[data-testid="production-line-select"] option[value="${PRODUCTION_LINE_ID}"]`),
  ).toHaveCount(1)
  await page.locator('[data-testid="production-line-select"]').selectOption(PRODUCTION_LINE_ID)

  if (options.type === 'dispatch') {
    await page.locator('[data-testid="type-tab-dispatch"]').click()
    await expect(page.locator('[data-testid="destination-input"]')).toBeVisible()
  }

  await page.locator('[data-testid="wb-card-number-input"]').fill(options.card)
  await page.locator('[data-testid="record-datetime-input"]').fill(isoDateTime(options.day, options.time))
  await page.locator('[data-testid="vehicle-number-input"]').fill('B 1234 WB')
  await page.locator('[data-testid="driver-name-input"]').fill('Sopir E2E')
  await page.locator('[data-testid="estate-supplier-input"]').fill(options.estateSupplier)

  if (options.type === 'dispatch') {
    await page.locator('[data-testid="destination-input"]').fill(options.destination as string)
  }

  // gross and tare are both `wire:model.live`, and the read-only Net Weight
  // preview is computed from them — so waiting on the preview is waiting on
  // the round trip rather than on a fixed timeout.
  await page.locator('[data-testid="gross-weight-input"]').fill(String(options.gross))

  if (options.tare === undefined) {
    // THE UNFINISHED WEIGHING: tare left empty, so the preview stays empty and
    // net_weight is stored NULL. Never written as 0.
    // Sejak 2026-10-04 pratinjau ini teks (<span>), bukan input nonaktif:
    // kosong dirender "-" (App\Support\Display::number).
    await expect(page.locator('[data-testid="net-weight-preview"]')).toHaveText('-')
  } else {
    await page.locator('[data-testid="tare-weight-input"]').fill(String(options.tare))
    // Teks berformat Indonesia, 2 desimal — Display::number($net, 2).
    await expect(page.locator('[data-testid="net-weight-preview"]'))
      .toHaveText((options.gross - options.tare).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))
  }

  await page.locator('[data-testid="save-button"]').click()
  await page.waitForURL(
    (url) => url.pathname.startsWith('/data/weighbridge/') && !url.pathname.endsWith('/create'),
  )
}

// ---------------------------------------------------------------------
// Report helpers
// ---------------------------------------------------------------------

async function openReport(page: Page, username: string): Promise<void> {
  await login(page, username, PASSWORD)
  await page.goto(REPORT_PATH)
  await expect(page.locator('[data-testid="laporan-weighbridge"]')).toBeVisible()
}

/**
 * Picks this run's production line. CHOOSING ONE IS MANDATORY on this screen —
 * until it happens the page renders not one number, and deliberately not the
 * whole mill's totals as a stand-in.
 */
async function selectLine(page: Page): Promise<void> {
  await page.locator('[data-testid="production-line-select"]').selectOption(PRODUCTION_LINE_ID)
  await expect(page.locator('[data-testid="production-line-current"]')).toContainText(PRODUCTION_LINE_NAME)
}

/** Picks the period whose option label contains `name`, then waits for it. */
async function selectPeriod(page: Page, name: string): Promise<void> {
  const option = page.locator('[data-testid="period-select"] option', { hasText: name })
  await expect(option).toHaveCount(1)

  const value = await option.getAttribute('value')
  await page.locator('[data-testid="period-select"]').selectOption(value as string)

  await expect(page.locator('[data-testid="report-hero"]')).toContainText(name)
}

/** Opens the report as `username`, picks the line, then the named period. */
async function openPeriod(page: Page, username: string, periodName: string): Promise<void> {
  await openReport(page, username)
  await selectLine(page)
  await selectPeriod(page, periodName)
}

/** Opens/closes the daily recap — the same button does both. */
async function toggleRecap(page: Page): Promise<void> {
  await page.locator('[data-testid="daily-toggle"]').click()
}

/**
 * Clicks Ekspor CSV, waits for the Livewire-fired download, and returns the
 * file's contents. The button carries wire:click="exportCsv('csv')" rather
 * than an href, so the download event is the only thing to wait on.
 */
async function downloadCsv(page: Page): Promise<string> {
  const downloadPromise = page.waitForEvent('download')
  await page.locator('[data-testid="export-button"]').click()
  const download = await downloadPromise

  expect(download.suggestedFilename()).toContain('laporan-weighbridge')
  expect(download.suggestedFilename()).toMatch(/\.csv$/)

  const path = await download.path()
  expect(path, 'the CSV download produced no local file').toBeTruthy()

  return readFile(path as string, 'utf8')
}

/** A CSV body split into non-empty lines. */
function csvLines(body: string): string[] {
  return body.split('\n').map((line) => line.trim()).filter((line) => line !== '')
}

/** The header row, split on commas (this file quotes nothing it must not). */
function csvHeader(body: string): string[] {
  return csvLines(body)[0].split(',').map((cell) => cell.replace(/^"|"$/g, ''))
}

/** The text of the element carrying `testid`. */
async function textOf(page: Page, testid: string): Promise<string> {
  return (await page.locator(`[data-testid="${testid}"]`).innerText()).trim()
}

/** The `class` attribute of the element carrying `testid`, verbatim. */
async function classOf(page: Page, testid: string): Promise<string | null> {
  return page.locator(`[data-testid="${testid}"]`).getAttribute('class')
}

/** Every numeric block the report renders — used by the "no figures" cases. */
const FIGURE_TESTIDS = [
  'no-sum-note', 'flow-receive', 'flow-dispatch',
  'kpi-receive-trip-count', 'kpi-receive-net-weight-total', 'kpi-receive-net-weight-avg',
  'kpi-dispatch-trip-count', 'kpi-dispatch-net-weight-total', 'kpi-dispatch-net-weight-avg',
  'completeness-card', 'incomplete-card', 'incomplete-table',
  'hourly-kpis', 'hourly-distribution', 'by-origin-table', 'by-destination-table',
  'daily-trend', 'daily-recap-card', 'daily-table', 'export-button',
]

/** Controls a read-only report must never expose. */
const WRITE_TESTIDS = [
  'save-button', 'create-button', 'edit-button', 'delete-button',
  'add-row-button', 'remove-row-button', 'submit-button', 'add-data-button',
]

test.describe('Laporan Weighbridge', () => {
  // Pembersihan — lihat tests/support/periods.ts untuk alasan lengkapnya.
  // Tanpa ini, periode menumpuk sampai memenuhi halaman 1 daftar yang
  // dipaginasi 20 baris, lalu baris yang baru dibuat test terdorong ke
  // halaman 2 dan suite gagal di createPeriod() sebelum satu pun asersi
  // perilaku jalan. Spec ini membuat 12 periode per run.
  test.afterAll(async ({ browser }) => {
    // Record & periode lajur disapu LEBIH DULU: periode yang berisi record
    // ditolak 409 PERIOD_HAS_RECORDS oleh deletePeriodsByPrefix() di bawah.
    await pruneLaneData('laporan-weighbridge')

    const page = await browser.newPage()

    try {
      await login(page, ADMIN, PASSWORD)
      const deleted = await deletePeriodsByPrefix(page, [PERIOD_PREFIX])
      console.log('[cleanup] laporan-weighbridge: %d periode dihapus', deleted)
    } catch (error) {
      // Kegagalan membersihkan bukan kegagalan produk.
      console.warn('[cleanup] laporan-weighbridge: pembersihan gagal:', error)
    } finally {
      await page.close()
    }
  })

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(2_400_000)

    // Sisa run sebelumnya yang terhenti sebelum afterAll-nya (record dan
    // periode di rentang lajur). Lihat tests/support/backend.ts.
    await pruneLaneData('laporan-weighbridge (awal)')

    const page = await browser.newPage()

    try {
      // --- Periods (Admin, screen-128) ---------------------------------
      await login(page, ADMIN, PASSWORD)
      await page.goto(PERIODS_PATH)

      for (const [name, window] of [
        [PERIOD_MAIN, MAIN],
        [PERIOD_EMPTY, EMPTY],
        [PERIOD_RECEIVE, RECEIVE_ONLY],
        [PERIOD_NOWEIGHT, NOWEIGHT],
        [PERIOD_PARTIAL, PARTIAL],
        [PERIOD_HOUR, HOUR],
        [PERIOD_SKEWED, SKEWED],
        [PERIOD_CLOSED, CLOSED],
        [PERIOD_LONG, LONG],
        [PERIOD_INCLUSIVE, INCLUSIVE],
        [PERIOD_EXTREME, EXTREME],
      ] as Array<[string, typeof MAIN]>) {
        await createPeriod(page, { name, start: window.start, end: window.end })
      }

      // Periode satu hari di kedua sisi PERIOD_INCLUSIVE — lihat
      // PERIOD_BEFORE_INCLUSIVE: tanpa keduanya, kedua trip "di luar rentang"
      // ditolak kunci periode dan asersinya menjadi selalu-hijau.
      await createPeriod(page, {
        name: PERIOD_BEFORE_INCLUSIVE,
        start: isoDate(INCLUSIVE.startDay - 1),
        end: isoDate(INCLUSIVE.startDay - 1),
      })
      await createPeriod(page, {
        name: PERIOD_AFTER_INCLUSIVE,
        start: isoDate(INCLUSIVE.endDay + 1),
        end: isoDate(INCLUSIVE.endDay + 1),
      })

      // Another mill, and one without a single active station — so its period
      // gets NO Weighbridge station row and must NOT be offered here.
      await createPeriod(page, {
        name: PERIOD_OTHER_MILL,
        start: OTHER_MILL_WINDOW.start,
        end: OTHER_MILL_WINDOW.end,
        businessUnit: OTHER_MILL,
      })

      // Resolve the production line BY ID while still Admin.
      await resolveProductionLine(page)
      console.log('[fixture] laporan-weighbridge: production line %s (%s)', PRODUCTION_LINE_ID, PRODUCTION_LINE_NAME)

      // --- Trips (Supervisor, screen-022) ------------------------------
      await page.context().clearCookies()
      await login(page, SUPERVISOR, PASSWORD)

      // PERIOD_MAIN — both flows, asymmetric on purpose. See the header for
      // the arithmetic every assertion below depends on.
      await createTrip(page, { type: 'receive', day: MAIN.startDay, time: '06:10', card: CARD('M1'), estateSupplier: ESTATE_BESAR, gross: 12000, tare: 2000 })
      await createTrip(page, { type: 'receive', day: MAIN.startDay, time: '06:40', card: CARD('M2'), estateSupplier: ESTATE_KECIL, gross: 3000, tare: 2000 })
      await createTrip(page, { type: 'receive', day: MAIN.startDay + 1, time: '13:20', card: CARD('M3'), estateSupplier: ESTATE_BESAR, gross: 7000, tare: 2000 })
      // THE UNFINISHED WEIGHING: tare omitted, so net stays empty.
      await createTrip(page, { type: 'receive', day: MAIN.startDay + 2, time: '13:50', card: CARD('M4'), estateSupplier: ESTATE_KECIL, gross: 9000 })
      await createTrip(page, { type: 'dispatch', day: MAIN.startDay + 3, time: '20:15', card: CARD('M5'), estateSupplier: ESTATE_BESAR, destination: REFINERY_X, gross: 22000, tare: 2000 })
      await createTrip(page, { type: 'dispatch', day: MAIN.startDay + 4, time: '20:45', card: CARD('M6'), estateSupplier: ESTATE_BESAR, destination: PORT_Y, gross: 6000, tare: 2000 })

      // PERIOD_RECEIVE — incoming only, so the OUTGOING block must still be
      // rendered in full with its values unavailable.
      await createTrip(page, { type: 'receive', day: RECEIVE_ONLY.startDay, time: '08:00', card: CARD('R1'), estateSupplier: ESTATE_BESAR, gross: 12000, tare: 2000 })
      await createTrip(page, { type: 'receive', day: RECEIVE_ONLY.startDay, time: '09:00', card: CARD('R2'), estateSupplier: ESTATE_BESAR, gross: 7000, tare: 2000 })

      // PERIOD_NOWEIGHT — every trip with the tare left empty.
      await createTrip(page, { type: 'receive', day: NOWEIGHT.startDay, time: '08:00', card: CARD('N1'), estateSupplier: ESTATE_BESAR, gross: 11000 })
      await createTrip(page, { type: 'receive', day: NOWEIGHT.startDay, time: '09:00', card: CARD('N2'), estateSupplier: ESTATE_BESAR, gross: 12000 })

      // PERIOD_PARTIAL — 5 trips, 3 weighed: 1.000 + 2.000 + 3.000 = 6.000,
      // average 2.000,00 over a denominator of 3, never 1.200,00 over 5.
      await createTrip(page, { type: 'receive', day: PARTIAL.startDay, time: '07:00', card: CARD('P1'), estateSupplier: ESTATE_BESAR, gross: 3000, tare: 2000 })
      await createTrip(page, { type: 'receive', day: PARTIAL.startDay, time: '07:10', card: CARD('P2'), estateSupplier: ESTATE_BESAR, gross: 4000, tare: 2000 })
      await createTrip(page, { type: 'receive', day: PARTIAL.startDay, time: '07:20', card: CARD('P3'), estateSupplier: ESTATE_BESAR, gross: 5000, tare: 2000 })
      await createTrip(page, { type: 'receive', day: PARTIAL.startDay, time: '07:30', card: CARD('P4'), estateSupplier: ESTATE_BESAR, gross: 6000 })
      await createTrip(page, { type: 'receive', day: PARTIAL.startDay, time: '07:40', card: CARD('P5'), estateSupplier: ESTATE_BESAR, gross: 6000 })

      // PERIOD_HOUR — every trip inside hour 06.
      await createTrip(page, { type: 'receive', day: HOUR.startDay, time: '06:05', card: CARD('H1'), estateSupplier: ESTATE_BESAR, gross: 3000, tare: 2000 })
      await createTrip(page, { type: 'receive', day: HOUR.startDay, time: '06:25', card: CARD('H2'), estateSupplier: ESTATE_BESAR, gross: 3000, tare: 2000 })
      await createTrip(page, { type: 'receive', day: HOUR.startDay, time: '06:55', card: CARD('H3'), estateSupplier: ESTATE_BESAR, gross: 3000, tare: 2000 })

      // PERIOD_SKEWED — one dominant origin plus three one-trip origins.
      await createTrip(page, { type: 'receive', day: SKEWED.startDay, time: '08:00', card: CARD('S0'), estateSupplier: ESTATE_DOMINAN, gross: 52000, tare: 2000 })

      for (const index of [1, 2, 3]) {
        await createTrip(page, {
          type: 'receive',
          day: SKEWED.startDay,
          time: `09:${String(index * 5).padStart(2, '0')}`,
          card: CARD(`S${index}`),
          estateSupplier: `Supplier Kecil ${index} ${RUN_OFFSET}`,
          gross: 2100,
          tare: 2000,
        })
      }

      // PERIOD_CLOSED — trips first, the station is closed afterwards.
      await createTrip(page, { type: 'receive', day: CLOSED.startDay, time: '08:00', card: CARD('C1'), estateSupplier: ESTATE_BESAR, gross: 3000, tare: 2000 })
      await createTrip(page, { type: 'dispatch', day: CLOSED.startDay, time: '14:00', card: CARD('C2'), estateSupplier: ESTATE_BESAR, destination: REFINERY_X, gross: 4000, tare: 2000 })

      // PERIOD_LONG — trips on 5 dates spread over 20 days.
      for (const [index, offset] of [0, 4, 8, 12, 19].entries()) {
        await createTrip(page, {
          type: 'receive',
          day: LONG.startDay + offset,
          time: '08:00',
          card: CARD(`L${index}`),
          estateSupplier: ESTATE_BESAR,
          gross: 3000,
          tare: 2000,
        })
      }

      // PERIOD_INCLUSIVE — the two boundary days IN, the two neighbouring days
      // OUT. 99.000 and 88.000 must appear nowhere on the page or in the file.
      await createTrip(page, { type: 'receive', day: INCLUSIVE.startDay, time: '00:05', card: CARD('I1'), estateSupplier: ESTATE_BESAR, gross: 3000, tare: 2000 })
      await createTrip(page, { type: 'receive', day: INCLUSIVE.endDay, time: '23:50', card: CARD('I2'), estateSupplier: ESTATE_BESAR, gross: 4000, tare: 2000 })
      await createTrip(page, { type: 'receive', day: INCLUSIVE.startDay - 1, time: '23:50', card: CARD('I3'), estateSupplier: ESTATE_LUAR, gross: 101000, tare: 2000 })
      await createTrip(page, { type: 'receive', day: INCLUSIVE.endDay + 1, time: '00:10', card: CARD('I4'), estateSupplier: ESTATE_LUAR, gross: 90000, tare: 2000 })

      // PERIOD_EXTREME — 999.999 beside 1,00, rendered as-is and flagged
      // nowhere; both still contribute to the total and the average.
      await createTrip(page, { type: 'receive', day: EXTREME.startDay, time: '08:00', card: CARD('X1'), estateSupplier: `Estate Sangat Besar ${RUN_OFFSET}`, gross: 1001999, tare: 2000 })
      await createTrip(page, { type: 'receive', day: EXTREME.startDay, time: '09:00', card: CARD('X2'), estateSupplier: `Estate Sangat Kecil ${RUN_OFFSET}`, gross: 2001, tare: 2000 })

      // --- Close the closed period (Admin again) -----------------------
      await page.context().clearCookies()
      await login(page, ADMIN, PASSWORD)
      await page.goto(PERIODS_PATH)
      // Closing is PER STATION: only the Weighbridge row of this period is
      // closed, and the report must stay fully readable and exportable.
      await closeStation(page, PERIOD_CLOSED, STATION_TYPE)
    } finally {
      await page.close()
    }
  })

  // =====================================================================
  // Scenario 1: "sukses sebagai Supervisor atau Mill Management"
  // =====================================================================
  test('berhasil sebagai Supervisor: nama mill tanpa pemilih, kedua kelompok arus, kedua rekap, sebaran per jam, rekap harian, lalu unduhan CSV', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_MAIN)

    // A bound role gets a caption, never a picker.
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="production-line-current"]')).toContainText(PRODUCTION_LINE_NAME)

    // TWO FLOW BLOCKS, each with its three cards.
    await expect(page.locator('[data-testid="flow-receive"]')).toBeVisible()
    await expect(page.locator('[data-testid="flow-dispatch"]')).toBeVisible()
    await expect(page.locator('[data-testid="kpi-receive-trip-count"]')).toContainText('4')
    await expect(page.locator('[data-testid="kpi-receive-net-weight-total"]')).toContainText('16.000,00')
    await expect(page.locator('[data-testid="receive-net-weight-trip-count"]')).toHaveText('3')
    await expect(page.locator('[data-testid="kpi-receive-net-weight-avg"]')).toContainText('5.333,33')
    await expect(page.locator('[data-testid="kpi-dispatch-trip-count"]')).toContainText('2')
    await expect(page.locator('[data-testid="kpi-dispatch-net-weight-total"]')).toContainText('24.000,00')
    await expect(page.locator('[data-testid="kpi-dispatch-net-weight-avg"]')).toContainText('12.000,00')

    // Both breakdown tables.
    await expect(page.locator('[data-testid="by-origin-table"]')).toContainText(ESTATE_BESAR)
    await expect(page.locator('[data-testid="by-destination-table"]')).toContainText(REFINERY_X)

    // 24-hour distribution per flow, plus busiest hour and empty-hour count.
    await expect(page.locator('[data-testid="hourly-chart-receive"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="hourly-chart-dispatch"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="kpi-busiest-hour-receive"]')).toContainText('06.00')
    await expect(page.locator('[data-testid="kpi-busiest-hour-dispatch"]')).toContainText('20.00')
    await expect(page.locator('[data-testid="kpi-empty-hours-receive"]')).toContainText('22')
    await expect(page.locator('[data-testid="kpi-empty-hours-dispatch"]')).toContainText('23')

    // Completeness: missing weight PER FLOW, draft count, undated count.
    await expect(page.locator('[data-testid="missing-net-weight-receive"]')).toHaveText('1 trip')
    await expect(page.locator('[data-testid="missing-net-weight-dispatch"]')).toHaveText('0 trip')
    await expect(page.locator('[data-testid="draft-trip-count"]')).toBeVisible()
    await expect(page.locator('[data-testid="undated-trip-count"]')).toBeVisible()
    await expect(page.locator('[data-testid="days-with-trip"]')).toHaveText('5')
    await expect(page.locator('[data-testid="days-in-period"]')).toHaveText('10')

    // Daily recap and trend.
    await expect(page.locator('[data-testid="daily-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-trend"]')).toBeVisible()

    // The file downloads — a Livewire download, not a navigation.
    const csv = await downloadCsv(page)
    expect(csv).toContain(CARD('M1'))
    expect(csv).toContain('Arus Masuk')
    expect(csv).toContain('Arus Keluar')
    // Header + 6 trips.
    expect(csvLines(csv)).toHaveLength(7)

    // No write-flavoured control anywhere.
    for (const control of WRITE_TESTIDS) {
      await expect(page.locator(`[data-testid="${control}"]`)).toHaveCount(0)
    }
  })

  test('berhasil sebagai Mill Management: laporan yang sama, tetap tanpa pemilih Mill', async ({ page }) => {
    await openPeriod(page, MILL_MANAGEMENT, PERIOD_MAIN)

    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="kpi-receive-net-weight-total"]')).toContainText('16.000,00')
    await expect(page.locator('[data-testid="kpi-dispatch-net-weight-total"]')).toContainText('24.000,00')
    await expect(page.locator('[data-testid="by-origin-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="by-destination-table"]')).toBeVisible()
  })

  // =====================================================================
  // Scenario 2: "sukses sebagai Admin"
  // =====================================================================
  test('admin: memilih mill lalu Production Line lalu periode, nama keduanya tampil di kepala laporan, dan CSV terunduh', async ({ page }) => {
    await openReport(page, ADMIN)

    await expect(page.locator('[data-testid="mill-select"]')).toBeVisible()
    await page.locator('[data-testid="mill-select"]').selectOption({ label: BUSINESS_UNIT })
    await expect(page.locator('[data-testid="mill-select-hint"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="production-line-select"]')).toBeVisible()

    await selectLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    // Both names appear in the report header.
    await expect(page.locator('[data-testid="report-hero"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="hero-production-line"]')).toContainText(PRODUCTION_LINE_NAME)

    // Identical blocks to every other role, and every figure belongs to that
    // mill and that line only.
    await expect(page.locator('[data-testid="kpi-receive-net-weight-total"]')).toContainText('16.000,00')
    await expect(page.locator('[data-testid="kpi-dispatch-net-weight-total"]')).toContainText('24.000,00')
    await expect(page.locator('[data-testid="export-button"]')).toBeEnabled()

    const csv = await downloadCsv(page)
    expect(csv).toContain(BUSINESS_UNIT)
    expect(csv).toContain(PRODUCTION_LINE_NAME)

    for (const control of WRITE_TESTIDS) {
      await expect(page.locator(`[data-testid="${control}"]`)).toHaveCount(0)
    }
  })

  // =====================================================================
  // Scenario 3: "Production Line belum dipilih"
  // =====================================================================
  test('tanpa Production Line: ajakan memilih tampil, nol angka, dan tidak ada total seluruh mill di mana pun', async ({ page }) => {
    await openReport(page, SUPERVISOR)

    await expect(page.locator('[data-testid="production-line-select"]')).toHaveValue('')
    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()
    await expect(page.locator('[data-testid="select-production-line-hint"]'))
      .toContainText('Pilih production line terlebih dahulu')

    for (const testid of FIGURE_TESTIDS) {
      await expect(page.locator(`[data-testid="${testid}"]`)).toHaveCount(0)
    }

    // No mill-wide total stands in for the missing choice.
    const body = await page.locator('[data-testid="laporan-weighbridge"]').innerText()
    expect(body).not.toContain('16.000,00')
    expect(body).not.toContain('24.000,00')
    expect(body).not.toContain('Semua Line')
  })

  // =====================================================================
  // Scenario 4: "Admin mengganti mill setelah memilih line"
  // =====================================================================
  test('admin berganti mill: pemilih line dikosongkan dan dimuat ulang, dan tak satu angka mill lama tersisa', async ({ page }) => {
    await openReport(page, ADMIN)

    await page.locator('[data-testid="mill-select"]').selectOption({ label: BUSINESS_UNIT })
    await selectLine(page)
    await selectPeriod(page, PERIOD_MAIN)
    await expect(page.locator('[data-testid="kpi-receive-net-weight-total"]')).toContainText('16.000,00')

    // Another mill — read from the picker rather than hardcoded, since which
    // mills exist is environment data.
    let otherMillValue: string | null = null

    for (const option of await page.locator('[data-testid="mill-select"] option').all()) {
      const value = await option.getAttribute('value')
      const label = (await option.textContent())?.trim() ?? ''

      if (value && label !== BUSINESS_UNIT) {
        otherMillValue = value
        break
      }
    }

    expect(otherMillValue, 'this environment has only one mill, so switching cannot be exercised').not.toBeNull()

    await page.locator('[data-testid="mill-select"]').selectOption(otherMillValue as string)

    // The line selection is dropped back to "not chosen" and the prompt
    // returns, until a new line is DELIBERATELY selected.
    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()
    await expect(page.locator('[data-testid="production-line-select"]')).toHaveValue('')

    const body = await page.locator('[data-testid="laporan-weighbridge"]').innerText()
    expect(body).not.toContain('16.000,00')
    expect(body).not.toContain(ESTATE_BESAR)

    for (const testid of FIGURE_TESTIDS) {
      await expect(page.locator(`[data-testid="${testid}"]`)).toHaveCount(0)
    }
  })

  // =====================================================================
  // Scenario 5: "mill hanya punya satu Production Line"
  //
  // How many production lines "Business Unit A" has is environment data this
  // spec must not depend on, so what is asserted here is the RULE that makes
  // the one-line case correct: NOTHING is pre-selected on arrival, no matter
  // how many options there are, and the report only appears once the reader
  // picks one — with that line NAMED, so they always know which line produced
  // the numbers. The literal "exactly one option" case is asserted where the
  // option count can be controlled, in the Livewire suite's scenario 5.
  // =====================================================================
  test('opsi line tidak pernah dipilih otomatis: nol angka sebelum memilih, lalu nama line tampil setelah dipilih', async ({ page }) => {
    await openReport(page, SUPERVISOR)

    await expect(page.locator('[data-testid="production-line-select"]')).toHaveValue('')
    await expect(page.locator('[data-testid="production-line-current"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()

    for (const testid of FIGURE_TESTIDS) {
      await expect(page.locator(`[data-testid="${testid}"]`)).toHaveCount(0)
    }

    await selectLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    await expect(page.locator('[data-testid="production-line-current"]')).toContainText(PRODUCTION_LINE_NAME)
    await expect(page.locator('[data-testid="hero-production-line"]')).toContainText(PRODUCTION_LINE_NAME)
    await expect(page.locator('[data-testid="flow-receive"]')).toBeVisible()
  })

  // =====================================================================
  // Scenario 6: "Admin memilih mill lebih dulu"
  // =====================================================================
  test('admin tanpa mill: pemilih mill dan ajakan memilih terlihat, nol angka, dan kedua pemilih lain belum ada', async ({ page }) => {
    await openReport(page, ADMIN)

    await expect(page.locator('[data-testid="mill-select"]')).toHaveValue('')
    await expect(page.locator('[data-testid="mill-select-hint"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-select-hint"]')).toContainText('Pilih mill terlebih dahulu')

    // The line and period pickers do not exist until a mill is chosen.
    await expect(page.locator('[data-testid="production-line-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="period-select"]')).toHaveCount(0)

    for (const testid of FIGURE_TESTIDS) {
      await expect(page.locator(`[data-testid="${testid}"]`)).toHaveCount(0)
    }
  })

  // =====================================================================
  // Scenario 7: "mill belum punya periode pelaporan"
  // =====================================================================
  test('mill tanpa periode Weighbridge: pemilih periode kosong, arahan menghubungi Admin, tanpa angka', async ({ page }) => {
    await openReport(page, ADMIN)

    // Which mills have no Weighbridge period is environment data, so the spec
    // looks for one instead of hardcoding a name. The values are read up
    // front: every selection re-renders the picker, which would stale the
    // element handles.
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
      // One Livewire round trip: the production-line picker reloads for the
      // mill just chosen.
      await page.waitForTimeout(500)

      // A PRODUCTION LINE MUST BE CHOSEN BEFORE no-period-hint CAN EVER APPEAR.
      // The screen's empty states are an if/elseif chain and the
      // "pilih production line" branch comes FIRST (see
      // livewire/dashboard/laporan-weighbridge.blade.php), so a mill selected
      // without a line shows that branch and never the period hint — the hunt
      // below would then fail on EVERY mill regardless of its periods, which is
      // exactly how this test failed on 2026-10-01. Mills with no line at all
      // are skipped: they cannot reach the period branch either.
      const lineOptions = await page
        .locator('[data-testid="production-line-select"] option')
        .evaluateAll((nodes) =>
          nodes.map((node) => (node as HTMLOptionElement).value).filter((v) => v !== ''),
        )

      if (lineOptions.length === 0) {
        continue
      }

      await page.locator('[data-testid="production-line-select"]').selectOption(lineOptions[0])
      await page.waitForTimeout(500)

      if (await page.locator('[data-testid="no-period-hint"]').isVisible()) {
        found = true
        break
      }
    }

    expect(
      found,
      'no mill with a Weighbridge production line but no Weighbridge reporting period exists in this environment',
    ).toBe(true)

    await expect(page.locator('[data-testid="no-period-hint"]')).toContainText('Belum ada Periode Pelaporan')
    await expect(page.locator('[data-testid="no-period-hint"]')).toContainText('Kelola Periode Pelaporan')
    await expect(page.locator('[data-testid="period-select"] option')).toHaveCount(0)

    for (const testid of FIGURE_TESTIDS) {
      await expect(page.locator(`[data-testid="${testid}"]`)).toHaveCount(0)
    }

    // An empty mill is not an error page.
    await expect(page.locator('[data-testid="laporan-weighbridge"]')).toBeVisible()
  })

  // =====================================================================
  // Scenario 8: "periode tanpa data"
  // =====================================================================
  test('periode tanpa data: pernyataan belum ada data, total dan rata-rata berbunyi tidak tersedia bukan nol, dan tidak ada grafik kosong', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_EMPTY)

    await expect(page.locator('[data-testid="empty-state"]'))
      .toContainText('Belum ada data pada periode dan line ini')

    // "tidak tersedia", NOT 0 — zero would claim a measured total of nothing.
    await expect(page.locator('[data-testid="kpi-receive-net-weight-total"]')).toContainText('tidak tersedia')
    await expect(page.locator('[data-testid="kpi-receive-net-weight-avg"]')).toContainText('tidak tersedia')
    await expect(page.locator('[data-testid="kpi-dispatch-net-weight-total"]')).toContainText('tidak tersedia')
    await expect(page.locator('[data-testid="kpi-dispatch-net-weight-avg"]')).toContainText('tidak tersedia')

    // No misleading empty graph is drawn.
    await expect(page.locator('[data-testid="hourly-distribution"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="daily-trend-chart"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="by-origin-table"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="daily-table"]')).toHaveCount(0)
  })

  // =====================================================================
  // Scenario 9: "trip tersinkron terlambat dari mobile"
  //
  // EVERY trip in this spec demonstrates the rule for free: it is entered
  // TODAY (created_at ~2026) and carries a record_datetime in the lane years (1970-2019) —
  // centuries away from its own period by row-creation time. The figures
  // follow the WEIGHING time regardless.
  // =====================================================================
  test('waktu baris dibuat tidak memengaruhi apa pun: trip terhitung di periode waktu penimbangannya, dan periode lain tidak terpengaruh', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_MAIN)

    await expect(page.locator('[data-testid="kpi-receive-trip-count"]')).toContainText('4')
    await expect(page.locator(`[data-testid="daily-row-${isoDate(MAIN.startDay)}"]`)).toBeVisible()

    // Switch to another period of the same line: the trips above are counted
    // in none of its figures, and its own are counted in none of theirs.
    await selectPeriod(page, PERIOD_RECEIVE)

    await expect(page.locator('[data-testid="kpi-receive-trip-count"]')).toContainText('2')
    await expect(page.locator(`[data-testid="daily-row-${isoDate(MAIN.startDay)}"]`)).toHaveCount(0)
    await expect(page.locator(`[data-testid="daily-row-${isoDate(RECEIVE_ONLY.startDay)}"]`)).toBeVisible()
  })

  // =====================================================================
  // Scenario 10: "trip tanpa penanda waktu penimbangan"
  //
  // A trip with NO weighing timestamp cannot be created from any web screen:
  // record_datetime is ['required','date'] in
  // WeighbridgeRecordService::validateForm(). Such rows arrive only from the
  // legacy/mobile path — which is precisely why undated_trip_count exists at
  // all. What IS browser-verifiable, and what this test asserts, is that the
  // figure is RENDERED AS ITS OWN FIGURE in the completeness section, stating
  // that it counts towards nothing else, so a reader can reconcile this screen
  // against the Data Browser. The non-zero case is asserted in the unit suite
  // (case 18), the Api suite (scenario 10) and the Livewire suite (scenario 10).
  // =====================================================================
  test('jumlah trip tanpa penanda waktu tampil sebagai angkanya sendiri di bagian kelengkapan, dengan keterangan bahwa ia tidak ikut angka mana pun', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_MAIN)

    await expect(page.locator('[data-testid="incomplete-row-undated"]')).toBeVisible()
    await expect(page.locator('[data-testid="undated-trip-count"]')).toContainText('trip')
    await expect(page.locator('[data-testid="incomplete-row-undated"]'))
      .toContainText('TIDAK ikut terhitung di angka mana pun')
    await expect(page.locator('[data-testid="incomplete-row-undated"]')).toContainText('Penanda waktu kosong')

    // It is reported in exactly ONE place and nowhere else.
    await expect(page.locator('[data-testid="undated-trip-count"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="incomplete-note"]')).toContainText('Data Browser')
  })

  // =====================================================================
  // Scenario 11: "periode hanya memuat satu jenis arus"
  // =====================================================================
  test('periode satu arus: bagian arus keluar TETAP terlihat dengan nilai tidak tersedia, bukan hilang dari halaman', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_RECEIVE)

    // Present, in full, rather than absent — so the reader concludes there
    // were no outgoing shipments instead of assuming the section does not
    // exist.
    await expect(page.locator('[data-testid="flow-dispatch"]')).toBeVisible()
    await expect(page.locator('[data-testid="kpi-dispatch-trip-count"]')).toContainText('0')
    await expect(page.locator('[data-testid="kpi-dispatch-net-weight-total"]')).toContainText('tidak tersedia')
    await expect(page.locator('[data-testid="kpi-dispatch-net-weight-avg"]')).toContainText('tidak tersedia')
    await expect(page.locator('[data-testid="kpi-empty-hours-dispatch"]')).toContainText('24')
    await expect(page.locator('[data-testid="by-destination-empty"]')).toBeVisible()

    // The incoming block shows its own figures separately.
    await expect(page.locator('[data-testid="kpi-receive-trip-count"]')).toContainText('2')
    await expect(page.locator('[data-testid="kpi-receive-net-weight-total"]')).toContainText('15.000,00')
  })

  // =====================================================================
  // Scenario 12: "seluruh trip beratnya kosong"
  // =====================================================================
  test('seluruh trip beratnya kosong: total dan rata-rata tidak tersedia, trip berat terisi 0, jumlah trip tetap angka sebenarnya', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_NOWEIGHT)

    await expect(page.locator('[data-testid="kpi-receive-net-weight-total"]')).toContainText('tidak tersedia')
    await expect(page.locator('[data-testid="kpi-receive-net-weight-avg"]')).toContainText('tidak tersedia')
    await expect(page.locator('[data-testid="receive-net-weight-trip-count"]')).toHaveText('0')
    await expect(page.locator('[data-testid="receive-avg-denominator"]')).toHaveText('0')
    await expect(page.locator('[data-testid="kpi-receive-trip-count"]')).toContainText('2')

    // The per-flow unfinished-weighing count equals that flow's trip count.
    await expect(page.locator('[data-testid="missing-net-weight-receive"]')).toHaveText('2 trip')

    // Not 0,00 anywhere in the totals block — the two are different claims.
    expect(await textOf(page, 'kpi-receive-net-weight-total')).not.toContain('0,00')
  })

  // =====================================================================
  // Scenario 13: "penimbangan belum selesai pada sebagian trip"
  // =====================================================================
  test('sebagian trip belum tertimbang: rata-rata sama dengan total dibagi jumlah trip berat terisi yang tertulis di sampingnya', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_PARTIAL)

    // 6.000 / 3 = 2.000,00, and the denominator 3 is printed beside it.
    await expect(page.locator('[data-testid="kpi-receive-net-weight-total"]')).toContainText('6.000,00')
    await expect(page.locator('[data-testid="receive-net-weight-trip-count"]')).toHaveText('3')
    await expect(page.locator('[data-testid="receive-avg-denominator"]')).toHaveText('3')
    await expect(page.locator('[data-testid="kpi-receive-net-weight-avg"]')).toContainText('2.000,00')
    await expect(page.locator('[data-testid="kpi-receive-trip-count"]')).toContainText('5')

    // The unfinished trips are EXCLUDED from the average rather than lowering
    // it: 6.000 / 5 = 1.200,00 appears nowhere on the page.
    const body = await page.locator('[data-testid="laporan-weighbridge"]').innerText()
    expect(body).not.toContain('1.200,00')

    // Each flow shows its own unfinished-weighing count.
    await expect(page.locator('[data-testid="missing-net-weight-receive"]')).toHaveText('2 trip')
    await expect(page.locator('[data-testid="missing-net-weight-dispatch"]')).toHaveText('0 trip')
  })

  // =====================================================================
  // Scenario 14: "tujuan belum diisi pada sebagian trip arus keluar"
  //
  // A dispatch trip with NO destination cannot be created from any web screen:
  // `destination` is REQUIRED for weighbridge_type=dispatch in
  // WeighbridgeRecordService::validateForm(). What this scenario actually
  // turns on is the invariant it protects — NO TRIP IS MISSING FROM THE RECAP,
  // so the per-destination trip counts add up EXACTLY to the headline outgoing
  // trip count — and that IS browser-verifiable, here, on real data. The
  // "Belum diisi" group itself is asserted in the unit suite (cases 31 and
  // 46), the Api suite (scenario 14) and the Livewire suite (scenario 14).
  // =====================================================================
  test('rekap per tujuan tidak kehilangan satu trip pun: kolom Trip menjumlah tepat ke jumlah trip keluar di kaki tabel', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_MAIN)

    await expect(page.locator('[data-testid="by-destination-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="by-destination-table"]')).toContainText(REFINERY_X)
    await expect(page.locator('[data-testid="by-destination-table"]')).toContainText(PORT_Y)

    // Every destination row's Trip cell, summed.
    const rows = page.locator('[data-testid="by-destination-row"], [data-testid="by-destination-row-null"]')
    const rowCount = await rows.count()

    let total = 0

    for (let index = 0; index < rowCount; index++) {
      const cells = rows.nth(index).locator('td')
      total += Number((await cells.nth(1).innerText()).replace(/\./g, '').trim())
    }

    expect(total).toBe(2)
    await expect(page.locator('[data-testid="by-destination-row-total"]')).toContainText('2')
    await expect(page.locator('[data-testid="kpi-dispatch-trip-count"]')).toContainText('2')

    // And the screen says, in words, that a destination-less trip would be
    // kept as its own group rather than dropped.
    await expect(page.locator('[data-testid="by-destination-note"]')).toContainText('tidak dibuang')
    await expect(page.locator('[data-testid="incomplete-row-no-destination"]')).toBeVisible()
  })

  // =====================================================================
  // Scenario 15: "seluruh trip terjadi pada jam yang sama"
  // =====================================================================
  test('seluruh trip pada satu jam: satu kolom terisi dan 23 kosong, jam tersibuk menunjuk jam itu, dan grafiknya tetap digambar', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_HOUR)

    // 24 columns are always drawn — a missing hour would read as an hour that
    // does not exist.
    await expect(page.locator('[data-testid="hourly-chart-receive"] .md-trendchart__col')).toHaveCount(24)
    await expect(page.locator('[data-testid="hourly-col-receive-6"]')).toContainText('3')
    await expect(page.locator('[data-testid="kpi-busiest-hour-receive"]')).toContainText('06.00')
    await expect(page.locator('[data-testid="kpi-busiest-hour-receive"]')).toContainText('3')
    await expect(page.locator('[data-testid="kpi-empty-hours-receive"]')).toContainText('23')

    // The other 23 columns read 0 and are dimmed rather than removed.
    await expect(page.locator('[data-testid="hourly-col-receive-7"]')).toContainText('0')
    await expect(page.locator('[data-testid="hourly-chart-receive"] .md-trendchart__col--off')).toHaveCount(23)
  })

  // =====================================================================
  // Scenario 16: "seluruh trip berstatus draft"
  //
  // A DRAFT trip cannot be created from any web screen:
  // WeighbridgeRecordService::create() writes status='saved' unconditionally,
  // and draft_ongoing / draft_paused arrive only from the mobile sync path.
  // What IS browser-verifiable, and what this test asserts, is the rule the
  // scenario exists to protect: the draft count is rendered as its own figure
  // and the screen states that draft trips are COUNTED in every figure above
  // rather than filtered out of them. The all-draft case is asserted in the
  // unit suite (case 47), the Api suite (scenario 16) and the Livewire suite
  // (scenario 16).
  // =====================================================================
  test('jumlah trip draft tampil sebagai angkanya sendiri, dengan pernyataan bahwa trip draft IKUT terhitung di seluruh angka di atas', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_MAIN)

    await expect(page.locator('[data-testid="incomplete-row-draft"]')).toBeVisible()
    await expect(page.locator('[data-testid="draft-trip-count"]')).toContainText('trip')
    await expect(page.locator('[data-testid="incomplete-row-draft"]')).toContainText('ikut terhitung')
    await expect(page.locator('[data-testid="incomplete-note"]')).toContainText('draft ikut terhitung')

    // And nothing is hidden because of status: every block is on the page.
    await expect(page.locator('[data-testid="flow-receive"]')).toBeVisible()
    await expect(page.locator('[data-testid="flow-dispatch"]')).toBeVisible()
    await expect(page.locator('[data-testid="by-origin-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-table"]')).toBeVisible()
  })

  // =====================================================================
  // Scenario 17: "satu asal menyumbang hampir seluruh arus masuk"
  // =====================================================================
  test('satu asal dominan: seluruh asal tetap terdaftar termasuk yang terkecil, tanpa baris sisa, dan jumlahnya tepat ke angka utama', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_SKEWED)

    // Four origins, four rows — the list is not truncated.
    await expect(page.locator('[data-testid="by-origin-row"]')).toHaveCount(4)
    await expect(page.locator('[data-testid="by-origin-table"]')).toContainText(ESTATE_DOMINAN)

    for (const index of [1, 2, 3]) {
      await expect(page.locator('[data-testid="by-origin-table"]'))
        .toContainText(`Supplier Kecil ${index} ${RUN_OFFSET}`)
    }

    // No "others" bucket exists at all.
    const table = await page.locator('[data-testid="by-origin-table"]').innerText()
    expect(table.toLowerCase()).not.toContain('lain-lain')
    expect(table.toLowerCase()).not.toContain('others')

    // The Trip column adds up to the headline incoming trip count.
    const rows = page.locator('[data-testid="by-origin-row"]')
    let total = 0

    for (let index = 0; index < await rows.count(); index++) {
      total += Number((await rows.nth(index).locator('td').nth(1).innerText()).replace(/\./g, '').trim())
    }

    expect(total).toBe(4)
    await expect(page.locator('[data-testid="kpi-receive-trip-count"]')).toContainText('4')
    await expect(page.locator('[data-testid="by-origin-row-total"]')).toContainText('4')
  })

  // =====================================================================
  // Scenario 18: "akun belum terhubung ke mill"
  //
  // RE-EXPRESSED, because the browser fixture provisions no account whose
  // users.business_unit_id is NULL — and inventing one from here would test
  // the seeder, not the screen. What IS browser-reachable is the half of the
  // fail-closed rule that matters most: a mill-bound role is NEVER offered the
  // all-mills dropdown, not after a period is chosen and not when the query
  // string asks for one. The NULL-mill branch itself is covered where the row
  // can be built directly: the SPY in WeighbridgeReportServiceTest case 3
  // proves the all-mills list is never even READ, and the Api / Livewire
  // suites assert the 422 and the contact-Admin notice with no picker at all.
  // =====================================================================
  test('akun terikat mill: daftar seluruh mill tidak pernah ditawarkan, bahkan ketika query string memintanya', async ({ page }) => {
    await openReport(page, SUPERVISOR)

    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)

    await selectLine(page)
    await selectPeriod(page, PERIOD_MAIN)
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)

    // Even asked for explicitly, the picker is not rendered: a role that is
    // supposed to be tied to exactly one mill is never shown the list of all
    // of them.
    await page.goto(`${REPORT_PATH}?business_unit_id=`)
    await expect(page.locator('[data-testid="laporan-weighbridge"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)
  })

  // =====================================================================
  // Scenario 19: "mencoba melihat mill atau Production Line milik mill lain"
  // =====================================================================
  test('mill lain: query string business_unit_id diabaikan, line mill lain dibuang ke belum-memilih, dan angka yang tampil tetap milik mill sendiri', async ({ page }) => {
    // Read another mill's id and one of its production lines from the Admin
    // side — no id is hardcoded, since that is environment data.
    await openReport(page, ADMIN)

    let otherMillId: string | null = null
    let otherMillName = ''

    for (const option of await page.locator('[data-testid="mill-select"] option').all()) {
      const value = await option.getAttribute('value')
      const label = (await option.textContent())?.trim() ?? ''

      if (value && label !== BUSINESS_UNIT) {
        otherMillId = value
        otherMillName = label
        break
      }
    }

    expect(otherMillId, 'this environment has only one mill, so cross-mill probing cannot be exercised').not.toBeNull()

    const headers = await statefulHeaders(page)
    const otherLines = await page.request.get(
      `/api/production-lines/options-for-report?business_unit_id=${otherMillId}`,
      { headers },
    )
    expect(otherLines.ok()).toBe(true)
    const otherLineId = ((await otherLines.json()).data as Array<{ id: string }>)[0]?.id ?? null

    await page.context().clearCookies()
    await login(page, SUPERVISOR, PASSWORD)

    // (a) Another mill named in the query string: answered with the caller's
    // OWN mill rather than a refusal, which would confirm the other mill
    // exists.
    await page.goto(`${REPORT_PATH}?business_unit_id=${otherMillId}`)
    await expect(page.locator('[data-testid="laporan-weighbridge"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="laporan-weighbridge"]')).not.toContainText(otherMillName)

    await selectLine(page)
    await selectPeriod(page, PERIOD_MAIN)
    await expect(page.locator('[data-testid="kpi-receive-net-weight-total"]')).toContainText('16.000,00')

    // (b) Another mill's PRODUCTION LINE in the query string: refused, and no
    // report body is rendered for it — the choose-a-line prompt comes back
    // rather than the other mill's line name.
    if (otherLineId) {
      await page.goto(`${REPORT_PATH}?production_line_id=${otherLineId}`)
      await expect(page.locator('[data-testid="laporan-weighbridge"]')).toBeVisible()
      await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()
      await expect(page.locator('[data-testid="production-line-select"]')).toHaveValue('')

      for (const testid of FIGURE_TESTIDS) {
        await expect(page.locator(`[data-testid="${testid}"]`)).toHaveCount(0)
      }
    }

    // (c) A period id that is not among this mill's periods: no figure of any
    // other mill is ever rendered.
    await page.goto(`${REPORT_PATH}?period_id=00000000-0000-0000-0000-000000000000`)
    await expect(page.locator('[data-testid="laporan-weighbridge"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="laporan-weighbridge"]')).not.toContainText(otherMillName)
  })

  // =====================================================================
  // Scenario 20: "Operator mencoba membuka layar web ini"
  // =====================================================================
  test('operator: akses laporan WEB ditolak dan tidak ada angka Weighbridge yang terlihat', async ({ page }) => {
    await login(page, OPERATOR, PASSWORD)
    await page.goto(REPORT_PATH)

    // EnsureRole -> abort(403): the error page, never the report. There is NO
    // Operator widening on this screen at all — the mobile Weighbridge report
    // is screen-144 and is not built yet, and widening would have to touch
    // routes/api.php, guardAccess() AND the mill-bound branch of
    // resolveBusinessUnit() together.
    await expect(page.locator('[data-testid="laporan-weighbridge"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="period-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="production-line-select"]')).toHaveCount(0)

    const body = await page.locator('body').innerText()
    expect(body).not.toContain(ESTATE_BESAR)
    expect(body).not.toContain(CARD('M1'))
    expect(body).not.toContain('16.000,00')
  })

  // =====================================================================
  // Scenario 21: "periode berstatus tertutup"
  // =====================================================================
  test('periode tertutup: tetap dapat dipilih, laporan lengkap, status Tertutup tampil sebagai catatan, dan CSV tetap terunduh', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_CLOSED)

    await expect(page.locator('[data-testid="period-status-badge"]')).toHaveText('Tertutup')

    // Rendered in full, exactly as for an open period.
    await expect(page.locator('[data-testid="flow-receive"]')).toBeVisible()
    await expect(page.locator('[data-testid="flow-dispatch"]')).toBeVisible()
    await expect(page.locator('[data-testid="by-origin-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="by-destination-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="hourly-distribution"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="completeness-card"]')).toBeVisible()
    await expect(page.locator('[data-testid="export-button"]')).toBeEnabled()

    // The period lock governs WRITING data, not READING a report.
    const csv = await downloadCsv(page)
    expect(csv).toContain(CARD('C1'))
    expect(csv).toContain(CARD('C2'))
    expect(csvLines(csv)).toHaveLength(3)
  })

  // =====================================================================
  // Scenario 22: "rekap harian sangat panjang"
  // =====================================================================
  test('rekap harian panjang: dapat ditutup lalu dibuka lagi, dan angka utama serta tren tetap terbaca tanpa satu angka pun berubah', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_LONG)

    // OPEN BY DEFAULT.
    await expect(page.locator('[data-testid="daily-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-table"] tbody tr')).toHaveCount(5)

    const before = {
      trips: await textOf(page, 'kpi-receive-trip-count'),
      total: await textOf(page, 'kpi-receive-net-weight-total'),
      days: await textOf(page, 'days-with-trip'),
    }

    await toggleRecap(page)

    // CLOSED means genuinely absent from the DOM, not merely hidden.
    await expect(page.locator('[data-testid="daily-table"]')).toHaveCount(0)
    // The headline figures and the trend stay rendered in both states.
    await expect(page.locator('[data-testid="flow-receive"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-trend"]')).toBeVisible()

    expect(await textOf(page, 'kpi-receive-trip-count')).toBe(before.trips)
    expect(await textOf(page, 'kpi-receive-net-weight-total')).toBe(before.total)
    expect(await textOf(page, 'days-with-trip')).toBe(before.days)

    await toggleRecap(page)

    await expect(page.locator('[data-testid="daily-table"]')).toBeVisible()
    expect(await textOf(page, 'kpi-receive-trip-count')).toBe(before.trips)
    expect(await textOf(page, 'kpi-receive-net-weight-total')).toBe(before.total)
    expect(await textOf(page, 'days-with-trip')).toBe(before.days)
  })

  // =====================================================================
  // Scenario 23: "arus masuk dan arus keluar tidak pernah dijumlahkan"
  // =====================================================================
  test('setiap angka terbelah masuk dan keluar: tidak ada satu pun angka di halaman yang menjumlahkan kedua arus', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_MAIN)

    await expect(page.locator('[data-testid="no-sum-note"]'))
      .toContainText('dua kelompok yang tidak pernah dijumlahkan')

    // Every metric exists twice, once per flow — including TWO separate hourly
    // charts, because a stacked bar would show a combined height, which is
    // exactly the number this report must not have.
    for (const testid of [
      'kpi-receive-trip-count', 'kpi-dispatch-trip-count',
      'kpi-receive-net-weight-total', 'kpi-dispatch-net-weight-total',
      'kpi-receive-net-weight-avg', 'kpi-dispatch-net-weight-avg',
      'hourly-chart-receive', 'hourly-chart-dispatch',
    ]) {
      await expect(page.locator(`[data-testid="${testid}"]`)).toHaveCount(1)
    }

    // The daily recap keeps the two flows in separate columns, including on
    // the footer row: 5 cells, never a 6th combined one.
    await expect(page.locator('[data-testid="daily-row-total"] td')).toHaveCount(5)

    // And the cross-flow sums are nowhere: 6 trips and 40.000,00 kg.
    const body = await page.locator('[data-testid="laporan-weighbridge"]').innerText()
    expect(body).not.toContain('40.000,00')
    expect(body.toLowerCase()).not.toContain('total gabungan')
    expect(body.toLowerCase()).not.toContain('total keseluruhan')

    // The CSV keeps them apart through the flow column and adds no summed one.
    const csv = await downloadCsv(page)
    const header = csvHeader(csv)
    expect(header).toContain('Jenis Arus')

    for (const column of header) {
      expect(column.toLowerCase()).not.toContain('gabungan')
      expect(column.toLowerCase()).not.toContain('combined')
    }

    // One row per trip — never a summary row.
    expect(csvLines(csv)).toHaveLength(7)
  })

  // =====================================================================
  // Scenario 24: "rata-rata berat hanya memakai trip yang beratnya terisi"
  //
  // The scenario's "ten trips of which six are weighed" is illustrative of the
  // rule; PERIOD_PARTIAL carries five of which three are weighed, which
  // exercises exactly the same arithmetic with four fewer form submissions.
  // =====================================================================
  test('rata-rata dibagi jumlah trip berat terisi: angka penyebutnya tampil di sampingnya, jumlah trip tetap angka penuh, sisanya terhitung terpisah', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_PARTIAL)

    await expect(page.locator('[data-testid="kpi-receive-net-weight-avg"]')).toContainText('2.000,00')
    await expect(page.locator('[data-testid="receive-avg-denominator"]')).toHaveText('3')
    await expect(page.locator('[data-testid="kpi-receive-trip-count"]')).toContainText('5')
    await expect(page.locator('[data-testid="receive-missing-net-weight-inline"]')).toHaveText('2')
    await expect(page.locator('[data-testid="missing-net-weight-receive"]')).toHaveText('2 trip')

    // The denominator is the FILLED count, never the trip count.
    expect(await textOf(page, 'kpi-receive-net-weight-avg')).not.toContain('1.200,00')
  })

  // =====================================================================
  // Scenario 25: "jumlah penimbangan belum selesai ditampilkan per arus"
  // =====================================================================
  test('penimbangan belum selesai tampil sebagai satu angka untuk arus masuk dan satu lagi untuk arus keluar, tidak pernah digabung', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_MAIN)

    // Two separate rows, two separate figures.
    await expect(page.locator('[data-testid="incomplete-row-missing-weight-receive"]')).toBeVisible()
    await expect(page.locator('[data-testid="incomplete-row-missing-weight-dispatch"]')).toBeVisible()
    await expect(page.locator('[data-testid="missing-net-weight-receive"]')).toHaveText('1 trip')
    await expect(page.locator('[data-testid="missing-net-weight-dispatch"]')).toHaveText('0 trip')

    // Each is also printed next to its OWN flow's trip count.
    await expect(page.locator('[data-testid="receive-missing-net-weight-inline"]')).toHaveText('1')
    await expect(page.locator('[data-testid="dispatch-missing-net-weight-inline"]')).toHaveText('0')
    await expect(page.locator('[data-testid="kpi-receive-trip-count"]')).toContainText('4')
    await expect(page.locator('[data-testid="kpi-dispatch-trip-count"]')).toContainText('2')

    // Never merged into one figure.
    await expect(page.locator('[data-testid="missing-net-weight-total"]')).toHaveCount(0)
  })

  // =====================================================================
  // Scenario 26: "penyaringan line memakai line yang melekat pada trip itu"
  //
  // Reassigning a station to another production line is an admin master-data
  // operation whose side effects reach every screen in the app, so this spec
  // does NOT perform one — a browser suite that rewires the shared fixture
  // mill breaks every other spec that shares it. What IS asserted here is the
  // observable consequence on this screen: the figures belong to the line
  // SELECTED, every other line of the same mill reports none of them, and the
  // screen says in words that the filter follows the line attached to the trip
  // rather than the station's current one. The reassignment itself is asserted
  // where it can be done in isolation: WeighbridgeReportServiceTest case 15,
  // Api scenario 26 and Livewire scenario 26 all move the station mid-test.
  // =====================================================================
  test('angka milik line yang dipilih saja: line lain pada mill yang sama tidak memuat satu pun trip-nya', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_MAIN)

    await expect(page.locator('[data-testid="kpi-receive-trip-count"]')).toContainText('4')
    await expect(page.locator('[data-testid="by-origin-table"]')).toContainText(ESTATE_BESAR)
    await expect(page.locator('[data-testid="report-filters"]'))
      .toContainText('Production Line yang melekat pada trip itu sendiri')

    // Any OTHER line of the same mill carries none of these trips.
    const otherLineValues: string[] = []

    for (const option of await page.locator('[data-testid="production-line-select"] option').all()) {
      const value = await option.getAttribute('value')

      if (value && value !== PRODUCTION_LINE_ID) {
        otherLineValues.push(value)
      }
    }

    // Deliberately a BRANCH and not test.skip(): a mill with a single
    // production line is a legitimate environment, and turning it into a
    // skipped test would hide a green assertion behind an amber one. The
    // first half above has already run either way.
    if (otherLineValues.length === 0) {
      console.log(
        '[laporan-weighbridge] "%s" has one production line in this environment, '
          + 'so the second-line half of this scenario was not exercised here; '
          + 'it is asserted in the Api and Livewire suites, which control the line count.',
        BUSINESS_UNIT,
      )

      return
    }

    await page.locator('[data-testid="production-line-select"]').selectOption(otherLineValues[0])
    await expect(page.locator('[data-testid="laporan-weighbridge"]')).not.toContainText(ESTATE_BESAR)
    await expect(page.locator('[data-testid="laporan-weighbridge"]')).not.toContainText(CARD('M1'))
  })

  // =====================================================================
  // Scenario 27: "keanggotaan periode ditentukan waktu kejadian penimbangan"
  // =====================================================================
  test('angka mengikuti waktu penimbangan: trip yang barisnya dibuat jauh di luar periode tetap terhitung di periode waktu penimbangannya', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_MAIN)

    // Every trip here was ENTERED today and WEIGHED in the lane years (1970-2019) — so
    // row-creation time is centuries outside the period it is counted in.
    await expect(page.locator('[data-testid="kpi-receive-trip-count"]')).toContainText('4')
    await expect(page.locator(`[data-testid="daily-row-${isoDate(MAIN.startDay)}"]`)).toBeVisible()
    await expect(page.locator('[data-testid="hero-range"]')).toContainText(dateLabel(MAIN.startDay))

    // The screen states the rule as well as obeying it.
    await expect(page.locator('[data-testid="completeness-note"]'))
      .toContainText('bukan waktu baris')

    // A trip weighed outside this period is counted in none of its figures.
    await selectPeriod(page, PERIOD_INCLUSIVE)
    await expect(page.locator('[data-testid="laporan-weighbridge"]')).not.toContainText(CARD('M1'))
  })

  // =====================================================================
  // Scenario 28: "rentang periode inklusif di kedua ujung"
  // =====================================================================
  test('rentang inklusif: trip hari pertama dan hari terakhir ikut penuh, trip H-1 dan H+1 tidak ikut sama sekali', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_INCLUSIVE)

    // Both boundary days are counted, in every figure.
    await expect(page.locator('[data-testid="kpi-receive-trip-count"]')).toContainText('2')
    await expect(page.locator('[data-testid="kpi-receive-net-weight-total"]')).toContainText('3.000,00')
    await expect(page.locator('[data-testid="days-with-trip"]')).toHaveText('2')

    // The daily recap lists the first AND the last day of the period.
    await expect(page.locator(`[data-testid="daily-row-${isoDate(INCLUSIVE.startDay)}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="daily-row-${isoDate(INCLUSIVE.endDay)}"]`)).toBeVisible()

    // The day-before and day-after trips are counted NOWHERE — 99.000,00 and
    // 88.000,00 appear neither on the page nor in the file.
    const body = await page.locator('[data-testid="laporan-weighbridge"]').innerText()
    expect(body).not.toContain(ESTATE_LUAR)
    expect(body).not.toContain('99.000,00')
    expect(body).not.toContain('88.000,00')
    await expect(page.locator(`[data-testid="daily-row-${isoDate(INCLUSIVE.startDay - 1)}"]`)).toHaveCount(0)
    await expect(page.locator(`[data-testid="daily-row-${isoDate(INCLUSIVE.endDay + 1)}"]`)).toHaveCount(0)

    const csv = await downloadCsv(page)
    expect(csv).toContain(CARD('I1'))
    expect(csv).toContain(CARD('I2'))
    expect(csv).not.toContain(CARD('I3'))
    expect(csv).not.toContain(CARD('I4'))
    expect(csvLines(csv)).toHaveLength(3)
  })

  // =====================================================================
  // Scenario 29: "tidak ada angka lama kendaraan berada di pabrik di mana pun"
  //
  // THIS TEST MUST NEVER SEARCH THE PAGE FOR THE WORD "durasi". The screen
  // deliberately SAYS it, under data-testid="no-duration-note": "Lama kendaraan
  // di pabrik TIDAK dilaporkan di layar ini, dan ketiadaannya disengaja." That
  // prose is the feature — the reader is told WHY the metric is missing instead
  // of being left to wonder — and a naive text grep would fail on the
  // explanation itself, pushing someone to delete the explanation to go green.
  //
  // So the absence is asserted STRUCTURALLY: no duration KPI card, no
  // duration-named table column, and EXACTLY ONE timestamp column in the
  // downloaded file.
  // =====================================================================
  test('nol angka lama kendaraan di pabrik: tanpa kartu durasi, tanpa kolom durasi, dan berkas unduhan hanya punya SATU kolom waktu', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_MAIN)

    // (a) No duration KPI card, as a single value, an average or an extreme.
    for (const slug of ['duration', 'durasi', 'lama', 'turnaround', 'dwell']) {
      await expect(page.locator(`[data-testid*="kpi-${slug}"]`)).toHaveCount(0)
    }

    // (b) No duration-named column in any table on the page.
    const headings = await page.locator('[data-testid="laporan-weighbridge"] th').allInnerTexts()

    for (const heading of headings) {
      for (const forbidden of ['durasi', 'duration', 'lama', 'turnaround']) {
        expect(heading.toLowerCase()).not.toContain(forbidden)
      }
    }

    // (c) The ONLY time-based rendering is the hourly distribution with its
    // busiest hour and empty-hour count — all derivable from ONE timestamp.
    await expect(page.locator('[data-testid="hourly-distribution"]')).toBeVisible()
    await expect(page.locator('[data-testid="kpi-busiest-hour-receive"]')).toBeVisible()
    await expect(page.locator('[data-testid="kpi-empty-hours-receive"]')).toBeVisible()

    // (d) The explanation IS rendered — that is the feature, not a leak.
    await expect(page.locator('[data-testid="no-duration-note"]')).toBeVisible()
    await expect(page.locator('[data-testid="no-duration-note"]')).toContainText('ketiadaannya disengaja')

    // (e) The downloaded file carries EXACTLY ONE timestamp column and no
    // duration column.
    const header = csvHeader(await downloadCsv(page))

    const timeColumns = header.filter((column) => /waktu|time|jam|tanggal|date/i.test(column))
    expect(timeColumns).toEqual(['Waktu Penimbangan'])

    for (const column of header) {
      for (const forbidden of ['durasi', 'duration', 'lama', 'turnaround']) {
        expect(column.toLowerCase()).not.toContain(forbidden)
      }
    }
  })

  // =====================================================================
  // Scenario 30: "sebaran trip per jam dihitung dari penanda waktu tunggal"
  // =====================================================================
  test('sebaran per jam: 24 jam tergambar per arus, jam tersibuk dan jam tanpa trip tampil sendiri, dan jumlah kolomnya tepat ke jumlah trip arus itu', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_MAIN)

    for (const flow of ['receive', 'dispatch']) {
      const columns = page.locator(`[data-testid="hourly-chart-${flow}"] .md-trendchart__col`)
      await expect(columns).toHaveCount(24)

      // The 24 counts add up EXACTLY to that flow's dated trip count.
      let total = 0

      for (let hour = 0; hour < 24; hour++) {
        total += Number(
          (await page.locator(`[data-testid="hourly-col-${flow}-${hour}"] .md-trendchart__val`).innerText())
            .replace(/\./g, '')
            .trim(),
        )
      }

      expect(total).toBe(flow === 'receive' ? 4 : 2)
    }

    // The busiest hour is shown WITH its trip count, as a wall clock rather
    // than a bare integer so it can never be read as a quantity.
    await expect(page.locator('[data-testid="kpi-busiest-hour-receive"]')).toContainText('06.00')
    await expect(page.locator('[data-testid="kpi-busiest-hour-receive"]')).toContainText('2')
    await expect(page.locator('[data-testid="kpi-busiest-hour-dispatch"]')).toContainText('20.00')

    // The empty-hour count is its own figure.
    await expect(page.locator('[data-testid="kpi-empty-hours-receive"]')).toContainText('22')
    await expect(page.locator('[data-testid="kpi-empty-hours-dispatch"]')).toContainText('23')
  })

  // =====================================================================
  // Scenario 31: "layar hanya membaca dan tidak mengubah data stasiun"
  // =====================================================================
  test('baca saja: tidak ada tombol ubah, hapus, simpan atau ubah status, dan angka tetap sama setelah seluruh interaksi', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_MAIN)

    const before = {
      trips: await textOf(page, 'kpi-receive-trip-count'),
      total: await textOf(page, 'kpi-receive-net-weight-total'),
      dispatchTotal: await textOf(page, 'kpi-dispatch-net-weight-total'),
      days: await textOf(page, 'days-with-trip'),
    }

    // Change the selections repeatedly, toggle the recap, then export.
    await selectPeriod(page, PERIOD_PARTIAL)
    await selectPeriod(page, PERIOD_RECEIVE)
    await selectPeriod(page, PERIOD_MAIN)
    await toggleRecap(page)
    await toggleRecap(page)
    await downloadCsv(page)

    // No write control exists on the screen.
    for (const control of WRITE_TESTIDS) {
      await expect(page.locator(`[data-testid="${control}"]`)).toHaveCount(0)
    }

    // And after all of it, re-reading the same period and line returns the
    // same numbers.
    await page.reload()
    await selectLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    expect(await textOf(page, 'kpi-receive-trip-count')).toBe(before.trips)
    expect(await textOf(page, 'kpi-receive-net-weight-total')).toBe(before.total)
    expect(await textOf(page, 'kpi-dispatch-net-weight-total')).toBe(before.dispatchTotal)
    expect(await textOf(page, 'days-with-trip')).toBe(before.days)
  })

  // =====================================================================
  // Scenario 32: "daftar periode dibatasi pada periode yang mencakup Weighbridge"
  // =====================================================================
  test('pemilih periode: hanya periode yang mencakup Weighbridge, dengan status stasiun itu, dan tertutup tetap dapat dipilih', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectLine(page)

    const options = page.locator('[data-testid="period-select"] option')

    // This run's periods are all offered ...
    for (const name of [PERIOD_MAIN, PERIOD_EMPTY, PERIOD_CLOSED, PERIOD_LONG]) {
      await expect(options.filter({ hasText: name })).toHaveCount(1)
    }

    // ... and the one belonging to a mill with no active station — hence with
    // no Weighbridge `period_stations` row at all — is not.
    await expect(options.filter({ hasText: PERIOD_OTHER_MILL })).toHaveCount(0)

    // The status shown per option is the WEIGHBRIDGE station status WITHIN
    // THAT PERIOD, never a status of the period itself (there has been no such
    // thing since migration 2026_09_26_000040) and never another station
    // type's status in the same period.
    //
    // PERIOD_MAIN reads "Terbuka" and PERIOD_CLOSED reads "Tertutup" — TWO
    // DIFFERENT STATUSES SIDE BY SIDE IN ONE LIST, which is the point: the
    // status is read per `period_stations` row, not per period.
    //
    // PERIOD_MAIN was "Draft" until 2026-10-02. It is "Terbuka" now because
    // createPeriod() in this spec opens the station row right after creating
    // the period: the period lock (usecase-141) refuses every record whose
    // date no OPEN period admits, so a Draft period means no fixture at all.
    // PERIOD_CLOSED is still taken the whole way draft -> open -> closed by
    // closeStation() in beforeAll.
    await expect(options.filter({ hasText: PERIOD_CLOSED })).toContainText('Tertutup')
    await expect(options.filter({ hasText: PERIOD_MAIN })).toContainText('Terbuka')
    await expect(options.filter({ hasText: PERIOD_MAIN })).toContainText(STATION_TYPE)

    // Both remain selectable, and both render a full report: STATUS NEVER
    // FILTERS THIS LIST and never limits reading or exporting — the period
    // lock governs writing data.
    await selectPeriod(page, PERIOD_CLOSED)
    await expect(page.locator('[data-testid="period-status-badge"]')).toHaveText('Tertutup')
    await expect(page.locator('[data-testid="flow-receive"]')).toBeVisible()

    await selectPeriod(page, PERIOD_MAIN)
    await expect(page.locator('[data-testid="period-status-badge"]')).toHaveText('Terbuka')
    await expect(page.locator('[data-testid="flow-receive"]')).toBeVisible()
  })

  // =====================================================================
  // Scenario 33: "tidak ada penandaan nilai di luar batas"
  // =====================================================================
  test('nilai ekstrem: ditampilkan apa adanya tanpa penandaan, tanpa warna peringatan, tanpa ikon kelayakan, dan tetap ikut total serta rata-rata', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_EXTREME)

    // Rendered as-is.
    await expect(page.locator('[data-testid="by-origin-table"]')).toContainText('999.999,00')
    await expect(page.locator('[data-testid="by-origin-table"]')).toContainText('1,00')

    // The explanatory box uses `.md-explain`, never the shared
    // `.md-threshold`, precisely so this bare-substring rule holds.
    const body = await page.locator('[data-testid="laporan-weighbridge"]').innerHTML()

    for (const forbidden of ['md-threshold', 'is-danger', 'is-warning', 'md-chip--danger', 'outlier', 'iqr']) {
      expect(body).not.toContain(forbidden)
    }

    // The extreme value still contributes to the total and the average like
    // any other trip: 999.999 + 1 = 1.000.000,00 over 2 trips.
    await expect(page.locator('[data-testid="kpi-receive-net-weight-total"]')).toContainText('1.000.000,00')
    await expect(page.locator('[data-testid="kpi-receive-net-weight-avg"]')).toContainText('500.000,00')

    // The two breakdown rows carry byte-identical markup — there is no
    // conditional branch on class anywhere on this page.
    const rows = page.locator('[data-testid="by-origin-row"]')
    await expect(rows).toHaveCount(2)
    expect(await rows.nth(0).getAttribute('class')).toBe(await rows.nth(1).getAttribute('class'))
    expect(await classOf(page, 'kpi-receive-net-weight-total'))
      .toBe(await classOf(page, 'kpi-receive-trip-count'))
  })

  // =====================================================================
  // Scenario 34: "kelengkapan pencatatan tampil sebagai bagian laporan"
  // =====================================================================
  test('kelengkapan pencatatan berdiri setara dengan angka utama: keempat keadaan terkumpul di satu tempat beserta jumlah hari periode', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_MAIN)

    // A card of its own standing, not a footnote.
    await expect(page.locator('[data-testid="completeness-card"]')).toBeVisible()
    await expect(page.locator('[data-testid="incomplete-card"]')).toBeVisible()

    // All five states gathered in ONE table, so the reader can judge how far
    // the figures can be relied on.
    await expect(page.locator('[data-testid="incomplete-row-missing-weight-receive"]')).toBeVisible()
    await expect(page.locator('[data-testid="incomplete-row-missing-weight-dispatch"]')).toBeVisible()
    await expect(page.locator('[data-testid="incomplete-row-no-destination"]')).toBeVisible()
    await expect(page.locator('[data-testid="incomplete-row-draft"]')).toBeVisible()
    await expect(page.locator('[data-testid="incomplete-row-undated"]')).toBeVisible()

    await expect(page.locator('[data-testid="missing-net-weight-receive"]')).toHaveText('1 trip')
    await expect(page.locator('[data-testid="missing-net-weight-dispatch"]')).toHaveText('0 trip')
    await expect(page.locator('[data-testid="no-destination-dispatch-count"]')).toHaveText('0 trip')
    await expect(page.locator('[data-testid="days-in-period"]')).toHaveText('10')
    await expect(page.locator('[data-testid="days-with-trip"]')).toHaveText('5')
    await expect(page.locator('[data-testid="days-with-trip-percent"]')).toContainText('50,0%')

    // Each row states HOW it bears on the figures above — the three states
    // apply differently and the reader is told so.
    await expect(page.locator('[data-testid="incomplete-row-draft"]')).toContainText('ikut terhitung')
    await expect(page.locator('[data-testid="incomplete-row-undated"]')).toContainText('TIDAK ikut terhitung')
    await expect(page.locator('[data-testid="incomplete-row-no-destination"]')).toContainText('tidak dibuang')
  })

  // =====================================================================
  // Scenario 35: "unduh rincian seluruh trip sebagai CSV"
  // =====================================================================
  test('unduh CSV: satu baris per trip, konteks diulang tiap baris, label kolom sama dengan layar, kedua arus terbedakan, trip tanpa berat tetap jadi baris', async ({ page }) => {
    await openPeriod(page, SUPERVISOR, PERIOD_MAIN)

    const csv = await downloadCsv(page)
    const lines = csvLines(csv)
    const header = csvHeader(csv)

    // One row per trip for the selected period and line.
    expect(lines).toHaveLength(7)

    // The context columns repeat on every row.
    expect(header.slice(0, 5)).toEqual([
      'Periode', 'Mill', 'Production Line', 'Jenis Arus', 'Waktu Penimbangan',
    ])

    for (const line of lines.slice(1)) {
      expect(line).toContain(PERIOD_MAIN)
      expect(line).toContain(BUSINESS_UNIT)
      expect(line).toContain(PRODUCTION_LINE_NAME)
    }

    // The column labels match the terms rendered on screen.
    expect(header).toContain('Berat Bersih (kg)')
    await expect(page.locator('[data-testid="by-origin-table"]')).toContainText('Berat bersih (kg)')

    // Incoming and outgoing stay distinguishable through the flow column and
    // are never summed.
    expect(lines.filter((line) => line.includes('Arus Masuk'))).toHaveLength(4)
    expect(lines.filter((line) => line.includes('Arus Keluar'))).toHaveLength(2)

    // The unweighed trip is present as a ROW WITH AN EMPTY WEIGHT, never
    // dropped and never written as 0.
    const netIndex = header.indexOf('Berat Bersih (kg)')
    const unweighed = lines.find((line) => line.includes(CARD('M4')))

    expect(unweighed, 'the unweighed trip is missing from the exported file').toBeTruthy()
    expect((unweighed as string).split(',')[netIndex].replace(/"/g, '').trim()).toBe('')

    // And no duration or in-plant-time column anywhere.
    for (const column of header) {
      for (const forbidden of ['durasi', 'duration', 'lama', 'turnaround']) {
        expect(column.toLowerCase()).not.toContain(forbidden)
      }
    }
  })
})
