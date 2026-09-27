/**
 * Laporan Storage Tank (Browser/Playwright) — screen-133--laporan-storage-tank-web /
 * usecase-133--laporan-storage-tank-web.
 *
 * One test per test_scenarios entry whose `browser_test` is non-empty (all
 * 27). Route is /reports/storage-tank — the repo's report prefix is English
 * (/reports/management, /reports/stations, /reports/cages-track,
 * /reports/sterilizer, /reports/boiler-room, /reports/clarification); no
 * route in this app uses /laporan.
 *
 * SELF-SUFFICIENT DATA, because this screen cannot create any: the report is
 * READ-ONLY by design, so there is no UI here to seed it with. The fixtures
 * are built once, in beforeAll, through the two screens that DO own that
 * data:
 *   - /master-data/periods        (screen-128, as Admin)      — every period,
 *     including the closed one and the two the picker must decide between;
 *   - /data/storage-tank/create   (screen-116, as Supervisor) — the records
 *     and their time-slot rows, with the exact shape every assertion needs.
 *
 * =========================================================================
 * THE SEEDED NUMBERS ARE LOAD-BEARING. Do not "tidy" them.
 * =========================================================================
 * This screen's defining property is that STOCK IS A COMPARISON OF TWO
 * READINGS, not an aggregate: per tank, the FIRST and the LAST reading whose
 * Berat Terhitung (MT) is filled, ordered by (date, time slot). Every number
 * below exists so that a WRONG implementation produces a DIFFERENT number
 * rather than the same one by luck.
 *
 * PERIOD_MAIN — 10 days, TWO tanks.
 *   TANK_SCRAMBLED, three records, ENTERED IN A DELIBERATELY WRONG ORDER:
 *     entered 1st: MAIN.start+4, slot 12:00, berat 250,0
 *     entered 2nd: MAIN.start+9, slot 18:00, berat  80,0
 *     entered 3rd: MAIN.start+0, slot 06:00, berat 100,0
 *     => opening by VALUE would be 80,0; by ENTRY ORDER 250,0;
 *        by TIME (the only correct answer) 100,0 @ MAIN.start 06:00,
 *        closing 80,0 @ MAIN.start+9 18:00, movement -20,0.
 *   TANK_QUALITY, three records carrying the three quality metrics:
 *     MAIN.start+1  07:00  berat 400,0  FFA 3,2  air 0,12  DOBI 3,8
 *     MAIN.start+1  08:00  berat 450,0  FFA 4,1  air 0,21  DOBI 3,1
 *     MAIN.start+5  07:00  berat 430,0  FFA 4,9  air 0,29  DOBI 2,4
 *     => opening 400,0 @ MAIN.start+1 07:00, closing 430,0, movement +30,0.
 *   => PERIOD STOCK: opening 100,0 + 400,0 = 500,0 ; closing 80,0 + 430,0 =
 *      510,0 ; MOVEMENT -20,0 + 30,0 = +10,0 — summed PER TANK.
 *   => SIX filled slots against 2 tanks x 10 days x 24 = 480 expected.
 *   => THREE quality ranges that cannot share one linear axis (FFA 3-5 %,
 *      air 0,12-0,29 %, DOBI 2,4-3,8), which is why the chart normalises
 *      and MUST SAY SO in its legend.
 *
 * PERIOD_SINGLE — TANK_PAIR reads 100,0 -> 130,0 (+30,0) while TANK_ONE has
 *   exactly ONE stock reading of 500,0. Movement for TANK_ONE is "tidak
 *   dapat dihitung", NEVER 0,0, and the 500,0 contributes nothing: the
 *   period movement stays +30,0 and never becomes +530,0.
 * PERIOD_LATEOPEN — TANK_LATE's earliest reading fills only FFA (no stock),
 *   so the opening comes from the NEXT filled reading: 150,0 at
 *   LATEOPEN.start+2 12:00, not at LATEOPEN.start.
 * PERIOD_NOSTOCK — TANK_NOSTOCK has two readings with FFA only: it STAYS in
 *   the per-tank recap with stock and movement "tidak tersedia".
 * PERIOD_MOVEMENT — TANK_SPAN reads 100,0 -> 90,0 (-10,0) while TANK_LATEEND
 *   is recorded ONLY at the end, 400,0 -> 420,0 (+20,0). Per tank = +10,0;
 *   combined stock arithmetic would say +410,0. The two can never be
 *   confused, which is the entire point.
 * PERIOD_NEGATIVE — 500,0 -> 320,0: -180,0, rendered with its ASCII minus
 *   in ordinary text colour.
 * PERIOD_NOIMP — FFA 3,0 / 4,0 / 5,0 with Kotoran never filled: that card
 *   reads "tidak tersedia" with 0 pembacaan while FFA reads 4,0 from 3.
 * PERIOD_NOAVGTEMP — atas/tengah/bawah 50/60/70 with Suhu Rata-rata EMPTY:
 *   the card says "tidak tersedia" and the screen derives NOTHING (60,0
 *   would be the arithmetic mean it must not compute).
 * PERIOD_AVGTEMP  — the same 50/60/70 WITH Suhu Rata-rata 55,0: the card
 *   shows 55,0, never the 60,0 a recomputation would produce.
 * PERIOD_DENOM — row A (FFA 3,0 + air 0,2), row B (FFA 5,0), row C (DOBI
 *   2,4): FFA 2 pembacaan avg 4,0; air 1 avg 0,2 (never 0,07); DOBI 1.
 * PERIOD_SPARSE — 2 tanks x 5 days x 24 = 240 expected against 3 filled:
 *   1,25 %, and the coverage card sits ABOVE every figure.
 * PERIOD_EMPTY — no record at all: every figure reads "tidak tersedia",
 *   never 0, and no empty chart is drawn.
 * PERIOD_CLOSED — one record of two rows, then closed: a closed period stays
 *   fully readable AND fully exportable.
 * PERIOD_LONG — 20 dates, one row each: the daily recap is OPEN by default
 *   and can be closed, with the figures and both charts staying put.
 * PERIOD_INCLUSIVE — readings exactly on start_date (00:00, 111,0) and on
 *   end_date (23:00, 222,0), plus one the day BEFORE and one the day AFTER
 *   carrying 999,0 / 888,0 which must appear nowhere.
 * PERIOD_EXTREME — FFA 42,0 and Suhu Rata-rata 150,0 beside ordinary
 *   values: rendered as-is, flagged nowhere, with byte-identical card
 *   classes.
 * PERIOD_WHOLE_MILL and PERIOD_OTHER_MILL — the two period-picker membership
 *   cases; neither carries a record. Since 2026-09-26 a period covers its
 *   whole mill, so WHOLE_MILL is simply a period of THIS mill (it carries a
 *   Storage Tank row like every other one) and OTHER_MILL is a period of a
 *   mill with no active station at all, hence with no station row to match.
 *
 * TIME SLOTS MUST BE PICKED IN ASCENDING ORDER WITHIN ONE RECORD.
 * FormStorageTank::availableTimeSlotOptions() only offers slots whose index
 * in StorageTankRecordService::canonicalTimeSlots() is ABOVE the highest one
 * already picked, and that canonical order starts at 07:00 and wraps (07:00,
 * 08:00, ..., 23:00, 00:00, ..., 06:00). Every multi-row record below is
 * therefore filled front to back; the single-row records are free to use any
 * slot, which is how 06:00 (the LAST canonical slot) is reachable at all.
 *
 * WINDOWS ARE UNIQUE PER RUN AND SIT IN THEIR OWN DATE LANE. A period may
 * not overlap another IN THE SAME MILL — since 2026-09-26 the overlap rule
 * ignores the station type entirely — so every window is derived from
 * RUN_OFFSET and laid out end to end by a cursor, and RUN_OFFSET itself is
 * pushed into this spec's own lane by laneOffset().
 *
 * THE LANE IS NOT THE EPOCH ANY MORE. This header used to claim separation by
 * century (year 2800 here against 2600 elsewhere). That separation was
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
 * paginated at 20 rows ordered by start_date DESC, this spec adds 17 rows
 * per run, and after a single further run the rows a test has just created
 * are pushed to page 2 — so createPeriod() fails on its own toBeVisible()
 * check BEFORE a single behavioural assertion has run.
 *
 * NO THRESHOLD ASSERTIONS ANYWHERE — the opposite. Storage Tank has no
 * operational-target master table, so every scenario that touches an extreme
 * value asserts the ABSENCE of a warning colour, icon or badge BY NAME, and
 * scenario 25 goes further: it compares the class ATTRIBUTE of a card
 * holding an extreme value against one holding an ordinary value and
 * requires them to be identical. The explanatory note box on this screen
 * deliberately uses the class `.md-explain` rather than the shared
 * `.md-threshold`, precisely so the bare-substring form of that rule holds
 * on the rendered page.
 *
 * EXPORT IS A LIVEWIRE ACTION, not an <a href>: the button carries
 * wire:click="exportCsv('csv')", so the assertion waits for Playwright's
 * download event fired by Livewire's client-side download handler.
 *
 * KNOWN FLAKE, NOT REDESIGNED HERE: support/auth.ts:36 can time out on a
 * long serial run. It is documented, and this spec deliberately does not
 * work around it by inventing a second login path.
 */

import { readFile } from 'node:fs/promises'
import { test, expect, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { deletePeriodsByPrefix } from './support/periods'
import { closeStation, createPeriodViaUi, openStationRow } from './support/period-screen'
import { laneOffset } from './support/period-lanes'

const REPORT_PATH = '/reports/storage-tank'
const PERIODS_PATH = '/master-data/periods'
const STORAGE_TANK_FORM_PATH = '/data/storage-tank/create'

/** The mill these fixtures live in — the same one the other report specs use. */
const BUSINESS_UNIT = 'Business Unit A'
const STATION_TYPE = 'Storage Tank'
/**
 * The mill of the period this screen must NOT offer.
 *
 * WHY A MILL AND NOT A STATION TYPE ANY MORE. listPeriods() offers a period
 * when it belongs to the caller's mill AND has a `period_stations` row for
 * this screen's station type. Within one provisioned mill that second clause
 * can no longer be made false from the UI: creating a period registers a row
 * for EVERY station type the mill has, so there is no such thing as a period
 * of this mill that skips Storage Tank. "Mill Kode Duplikat" has no active
 * station at all, so its period gets NO station row whatsoever — it fails
 * both clauses at once, and it is the only browser-reachable shape of "a
 * period that does not cover this station type". The station-row clause on
 * its own is asserted where it can be produced directly, in
 * backend/tests/Feature/Api/LaporanStorageTankTest.php.
 */
const OTHER_MILL = 'Mill Kode Duplikat'

const SUPERVISOR = 'supervisor01'
const MILL_MANAGEMENT = 'millmanagement-a'
const ADMIN = 'admin'
/** Operator is a mobile-only actor. The mobile Storage Tank report is
 *  screen-139, a SEPARATE screen with its own endpoints — so there is no
 *  Operator widening anywhere on this web screen. */
const OPERATOR = 'operator01'

/**
 * A per-run day offset, so two runs never collide.
 *
 * THE STRIDE IS LOAD-BEARING. Periods are deleted in afterAll, but the
 * storage_tank_records entered through screen-116 are NOT (that screen
 * exposes no delete). They stay in the dev database forever. If one run's
 * windows can fall inside the next run's windows, the older run's records
 * are silently counted by the newer run's report and every figure above
 * drifts.
 *
 * One run's whole fixture spans ~90 days (see the cursor below), so the
 * seconds counter is multiplied by 120 for a 120-day stride between runs.
 * The modulus keeps the resulting year inside four digits, which
 * Date#toISOString() requires.
 */
/**
 * LAJUR TANGGAL SPEC INI. Offset mentah di bawah tetap seperti semula —
 * stride-nya dipilih demi keperluan spec ini sendiri — lalu laneOffset()
 * menggesernya ke lajur yang tidak dipakai spec lain. Sejak aturan tumpang
 * tindih periode menjadi PER MILL (2026-09-26), spec-spec yang berbagi satu
 * mill tidak boleh lagi memakai rentang tanggal yang sama; alasan lengkap dan
 * aritmetikanya ada di tests/support/period-lanes.ts.
 */
const RUN_OFFSET = laneOffset((Math.floor(Date.now() / 1000) % 20000) * 120, 'storage-tank')

/** Name prefix every period of this spec carries — also the cleanup key. */
const PERIOD_PREFIX = 'Storage Tank '

/**
 * Year 2600, the SAME epoch every period-seeding spec uses. Separation from
 * the other specs comes from laneOffset() (tests/support/period-lanes.ts),
 * not from the epoch — see the file header for why a century was never wide
 * enough to separate anything here.
 */
function isoDate(dayOffset: number): string {
  return new Date(Date.UTC(2600, 0, 1) + dayOffset * 86400000).toISOString().slice(0, 10)
}

/** The month abbreviations the screen renders — must match the blade's map. */
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']

/**
 * The instant label the report prints beside every stock figure, e.g.
 * "01 Sep 06:00" — day, abbreviated month, and the READING'S OWN HOUR. The
 * hour is not decoration: it is what says how far apart the two compared
 * readings actually sit.
 */
function instantLabel(iso: string, slot: string): string {
  const [, month, day] = iso.split('-')

  return `${day} ${MONTHS[Number(month) - 1]} ${slot}`
}

/**
 * Windows are laid out END TO END by a cursor with a two-day gap, rather
 * than at fixed slots: the windows here range from one day to twenty, and a
 * fixed spacing would have the long ones overlap their neighbours — and
 * every window of this spec must keep clear of every other one: the overlap
 * rule is per mill and no longer looks at the station type at all.
 *
 * The cursor starts one day AFTER RUN_OFFSET so the inclusive-range
 * scenario's "day before" record still belongs to this run's lane.
 */
let cursor = RUN_OFFSET + 2

function nextWindow(days = 1): { startDay: number; start: string; end: string } {
  const startDay = cursor
  cursor += days + 3

  return { startDay, start: isoDate(startDay), end: isoDate(startDay + days - 1) }
}

const MAIN = nextWindow(10)
const SINGLE = nextWindow(10)
const LATEOPEN = nextWindow(10)
const NOSTOCK = nextWindow()
const MOVEMENT = nextWindow(10)
const NEGATIVE = nextWindow(10)
const NOIMP = nextWindow()
const NOAVGTEMP = nextWindow()
const AVGTEMP = nextWindow()
const DENOM = nextWindow()
const SPARSE = nextWindow(5)
const EMPTY = nextWindow()
const CLOSED = nextWindow()
const LONG = nextWindow(20)
const INCLUSIVE = nextWindow(10)
const EXTREME = nextWindow()
const WHOLE_MILL = nextWindow()
const OTHER_MILL_WINDOW = nextWindow()

const PERIOD_MAIN = `${PERIOD_PREFIX}Lengkap ${RUN_OFFSET}`
const PERIOD_SINGLE = `${PERIOD_PREFIX}Pembacaan Tunggal ${RUN_OFFSET}`
const PERIOD_LATEOPEN = `${PERIOD_PREFIX}Awal Kosong ${RUN_OFFSET}`
const PERIOD_NOSTOCK = `${PERIOD_PREFIX}Tanpa Stok ${RUN_OFFSET}`
const PERIOD_MOVEMENT = `${PERIOD_PREFIX}Pergerakan Per Tangki ${RUN_OFFSET}`
const PERIOD_NEGATIVE = `${PERIOD_PREFIX}Pergerakan Negatif ${RUN_OFFSET}`
const PERIOD_NOIMP = `${PERIOD_PREFIX}Tanpa Kotoran ${RUN_OFFSET}`
const PERIOD_NOAVGTEMP = `${PERIOD_PREFIX}Suhu Rata Kosong ${RUN_OFFSET}`
const PERIOD_AVGTEMP = `${PERIOD_PREFIX}Suhu Rata Terisi ${RUN_OFFSET}`
const PERIOD_DENOM = `${PERIOD_PREFIX}Penyebut Berbeda ${RUN_OFFSET}`
const PERIOD_SPARSE = `${PERIOD_PREFIX}Tipis ${RUN_OFFSET}`
const PERIOD_EMPTY = `${PERIOD_PREFIX}Kosong ${RUN_OFFSET}`
const PERIOD_CLOSED = `${PERIOD_PREFIX}Tertutup ${RUN_OFFSET}`
const PERIOD_LONG = `${PERIOD_PREFIX}Rekap Panjang ${RUN_OFFSET}`
const PERIOD_INCLUSIVE = `${PERIOD_PREFIX}Rentang Inklusif ${RUN_OFFSET}`
const PERIOD_EXTREME = `${PERIOD_PREFIX}Nilai Ekstrem ${RUN_OFFSET}`
const PERIOD_WHOLE_MILL = `${PERIOD_PREFIX}Seluruh Mill ${RUN_OFFSET}`
const PERIOD_OTHER_MILL = `${PERIOD_PREFIX}Mill Lain ${RUN_OFFSET}`

/** The tanks of this run, unique per run so old records cannot blend in. */
const TANK_SCRAMBLED = `ST-${RUN_OFFSET}-SCR`
const TANK_QUALITY = `ST-${RUN_OFFSET}-QUA`
const TANK_PAIR = `ST-${RUN_OFFSET}-PAIR`
const TANK_ONE = `ST-${RUN_OFFSET}-ONE`
const TANK_LATE = `ST-${RUN_OFFSET}-LATE`
const TANK_NOSTOCK = `ST-${RUN_OFFSET}-NOSTK`
const TANK_SPAN = `ST-${RUN_OFFSET}-SPAN`
const TANK_LATEEND = `ST-${RUN_OFFSET}-LEND`
const TANK_NEG = `ST-${RUN_OFFSET}-NEG`
const TANK_SPARSE_A = `ST-${RUN_OFFSET}-SPA`
const TANK_SPARSE_B = `ST-${RUN_OFFSET}-SPB`
const TANK_MISC = `ST-${RUN_OFFSET}-MISC`

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
 * Resolves, as Admin, a production line of BUSINESS_UNIT whose STORAGE TANK
 * station is active — the only lines FormStorageTank will accept, since
 * StorageTankRecordService::create() looks the station up by
 * (production_line_id, type='storage-tank', is_active=true) and refuses
 * anything else.
 */
async function resolveProductionLine(page: Page): Promise<void> {
  const headers = await statefulHeaders(page)

  const mills = await page.request.get('/api/storage-tank-reports/business-units/options', { headers })
  expect(mills.ok(), 'admin could not read the mill list').toBe(true)

  const mill = ((await mills.json()).data as Array<{ id: string; name: string }>)
    .find((row) => row.name === BUSINESS_UNIT)

  expect(mill, `this environment has no mill named "${BUSINESS_UNIT}"`).toBeTruthy()

  const stations = await page.request.get(
    `/api/stations?per_page=100&business_unit_id=${mill!.id}`,
    { headers },
  )
  expect(stations.ok(), 'admin could not read the station list').toBe(true)

  const storageTank = ((await stations.json()).data as Array<{
    type: string
    is_active: boolean
    production_line_id: string
  }>).find((row) => row.type === 'storage-tank' && row.is_active && row.production_line_id)

  expect(
    storageTank,
    `no active storage-tank station on any production line of "${BUSINESS_UNIT}", so no record can be entered `
      + 'and this report would have nothing to report on',
  ).toBeTruthy()

  PRODUCTION_LINE_ID = storageTank!.production_line_id
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
  /** One of the 24 canonical labels. Rows of the SAME record MUST be listed
   *  in ASCENDING canonical order (07:00 first, 06:00 last) — the form's
   *  Time-Slot dropdown only offers slots above the highest already picked. */
  slot: string
  /** THE stock column, in metric tons. Leaving it out is NOT entering 0 —
   *  that difference is the whole point of this screen. */
  weight?: number
  volume?: number
  ffa?: number
  moisture?: number
  impurities?: number
  dobi?: number
  tempTop?: number
  tempMiddle?: number
  tempBottom?: number
  /** Read VERBATIM by the report. Never derived from the three above. */
  avgTemp?: number
  inspector?: string
  findings?: string
}

/**
 * One storage_tank_records row plus one storage_tank_details row per entry
 * of `rows`, entered through screen-116's create form.
 */
async function createStorageTankRecord(
  page: Page,
  options: { storageTankId: string; date: string; note?: string; rows: SlotRow[] },
): Promise<void> {
  await page.goto(STORAGE_TANK_FORM_PATH)

  await page.locator('[data-testid="production-line-select"]').selectOption(PRODUCTION_LINE_ID)
  await page.locator('[data-testid="storage-tank-id-input"]').fill(options.storageTankId)
  await page.locator('[data-testid="date-input"]').fill(options.date)

  if (options.note) {
    await page.locator('[data-testid="note-input"]').fill(options.note)
  }

  for (let index = 0; index < options.rows.length; index++) {
    const row = options.rows[index]

    await page.locator('[data-testid="add-row-button"]').click()
    await expect(page.locator(`[data-testid="storage-tank-detail-row-${index}"]`)).toBeVisible()

    // wire:model.live — the slot choice is a Livewire round trip, and the
    // NEXT row's option list is computed from it, so wait for it to land.
    const slotSelect = page.locator(`[data-testid="time-slot-select-${index}"]`)
    await slotSelect.selectOption(row.slot)
    await expect(slotSelect).toHaveValue(row.slot)

    const fills: Array<[string, number | string | undefined]> = [
      ['calculated-weight', row.weight],
      ['calculated-volume', row.volume],
      ['ffa', row.ffa],
      ['moisture-content', row.moisture],
      ['impurities-dirt', row.impurities],
      ['dobi-index', row.dobi],
      ['oil-temp-top', row.tempTop],
      ['oil-temp-middle', row.tempMiddle],
      ['oil-temp-bottom', row.tempBottom],
      ['average-temp', row.avgTemp],
      ['inspector-name', row.inspector],
      ['findings', row.findings],
    ]

    for (const [testid, value] of fills) {
      if (value !== undefined) {
        await page.locator(`[data-testid="${testid}-${index}"]`).fill(String(value))
      }
    }
  }

  await page.locator('[data-testid="save-button"]').click()
  await page.waitForURL(
    (url) => url.pathname.startsWith('/data/storage-tank/') && !url.pathname.endsWith('/create'),
  )
}

// ---------------------------------------------------------------------
// Report helpers
// ---------------------------------------------------------------------

async function openReport(page: Page, username: string): Promise<void> {
  await login(page, username, PASSWORD)
  await page.goto(REPORT_PATH)
  await expect(page.locator('[data-testid="laporan-storage-tank"]')).toBeVisible()
}

/** Picks the period whose option label contains `name`, then waits for it. */
async function selectPeriod(page: Page, name: string): Promise<void> {
  const option = page.locator('[data-testid="period-select"] option', { hasText: name })
  await expect(option).toHaveCount(1)

  const value = await option.getAttribute('value')
  await page.locator('[data-testid="period-select"]').selectOption(value as string)

  await expect(page.locator('[data-testid="report-hero"]')).toContainText(name)
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

  expect(download.suggestedFilename()).toContain('laporan-storage-tank')
  expect(download.suggestedFilename()).toMatch(/\.csv$/)

  const path = await download.path()
  expect(path, 'the CSV download produced no local file').toBeTruthy()

  return readFile(path as string, 'utf8')
}

/** The `class` attribute of the element carrying `testid`, verbatim. */
async function classOf(page: Page, testid: string): Promise<string | null> {
  return page.locator(`[data-testid="${testid}"]`).getAttribute('class')
}

test.describe('Laporan Storage Tank', () => {
  // Pembersihan — lihat tests/support/periods.ts untuk alasan lengkapnya.
  // Tanpa ini, periode menumpuk sampai memenuhi halaman 1 daftar yang
  // dipaginasi 20 baris, lalu baris yang baru dibuat test terdorong ke
  // halaman 2 dan suite gagal di createPeriod() sebelum satu pun asersi
  // perilaku jalan. Spec ini membuat 18 periode per run, jadi satu run
  // berikutnya saja sudah lebih dari cukup untuk memenuhi halaman itu.
  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage()

    try {
      await login(page, ADMIN, PASSWORD)
      const deleted = await deletePeriodsByPrefix(page, [PERIOD_PREFIX])
      console.log('[cleanup] laporan-storage-tank: %d periode dihapus', deleted)
    } catch (error) {
      // Kegagalan membersihkan bukan kegagalan produk.
      console.warn('[cleanup] laporan-storage-tank: pembersihan gagal:', error)
    } finally {
      await page.close()
    }
  })

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(2_400_000)

    const page = await browser.newPage()

    try {
      // --- Periods (Admin, screen-128) ---------------------------------
      await login(page, ADMIN, PASSWORD)
      await page.goto(PERIODS_PATH)

      for (const [name, window] of [
        [PERIOD_MAIN, MAIN],
        [PERIOD_SINGLE, SINGLE],
        [PERIOD_LATEOPEN, LATEOPEN],
        [PERIOD_NOSTOCK, NOSTOCK],
        [PERIOD_MOVEMENT, MOVEMENT],
        [PERIOD_NEGATIVE, NEGATIVE],
        [PERIOD_NOIMP, NOIMP],
        [PERIOD_NOAVGTEMP, NOAVGTEMP],
        [PERIOD_AVGTEMP, AVGTEMP],
        [PERIOD_DENOM, DENOM],
        [PERIOD_SPARSE, SPARSE],
        [PERIOD_EMPTY, EMPTY],
        [PERIOD_CLOSED, CLOSED],
        [PERIOD_LONG, LONG],
        [PERIOD_INCLUSIVE, INCLUSIVE],
        [PERIOD_EXTREME, EXTREME],
      ] as Array<[string, typeof MAIN]>) {
        await createPeriod(page, { name, start: window.start, end: window.end })
      }

      // Covers the whole mill, Storage Tank included — so it MUST be offered by this
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
      console.log('[fixture] laporan-storage-tank: production line %s', PRODUCTION_LINE_ID)

      // --- Records (Supervisor, screen-116) ----------------------------
      await page.context().clearCookies()
      await login(page, SUPERVISOR, PASSWORD)

      // PERIOD_MAIN / TANK_SCRAMBLED — ENTERED OUT OF CHRONOLOGICAL ORDER
      // ON PURPOSE. 250,0 is written first and 100,0 last, so an
      // implementation that trusts storage order opens at 250,0 and one that
      // takes the minimum opens at 80,0. Only ordering by (date, time slot)
      // gives 100,0.
      await createStorageTankRecord(page, {
        storageTankId: TANK_SCRAMBLED,
        date: isoDate(MAIN.startDay + 4),
        rows: [{ slot: '12:00', weight: 250 }],
      })
      await createStorageTankRecord(page, {
        storageTankId: TANK_SCRAMBLED,
        date: isoDate(MAIN.startDay + 9),
        rows: [{ slot: '18:00', weight: 80 }],
      })
      await createStorageTankRecord(page, {
        storageTankId: TANK_SCRAMBLED,
        date: MAIN.start,
        note: 'Pembacaan pertama periode',
        rows: [{ slot: '06:00', weight: 100 }],
      })

      // PERIOD_MAIN / TANK_QUALITY — three dates carrying FFA, kadar air and
      // DOBI so the single quality chart has a line per series, and three
      // scales that are NOT comparable on one linear axis.
      await createStorageTankRecord(page, {
        storageTankId: TANK_QUALITY,
        date: isoDate(MAIN.startDay + 1),
        rows: [
          { slot: '07:00', weight: 400, ffa: 3.2, moisture: 0.12, dobi: 3.8 },
          { slot: '08:00', weight: 450, ffa: 4.1, moisture: 0.21, dobi: 3.1 },
        ],
      })
      await createStorageTankRecord(page, {
        storageTankId: TANK_QUALITY,
        date: isoDate(MAIN.startDay + 5),
        rows: [{ slot: '07:00', weight: 430, ffa: 4.9, moisture: 0.29, dobi: 2.4 }],
      })

      // PERIOD_SINGLE — one tank with two stock readings, one tank with
      // exactly ONE. The single one is large and unmistakable (500,0) if it
      // ever leaks into the period movement.
      await createStorageTankRecord(page, {
        storageTankId: TANK_PAIR,
        date: SINGLE.start,
        rows: [{ slot: '06:00', weight: 100 }],
      })
      await createStorageTankRecord(page, {
        storageTankId: TANK_PAIR,
        date: isoDate(SINGLE.startDay + 9),
        rows: [{ slot: '18:00', weight: 130 }],
      })
      await createStorageTankRecord(page, {
        storageTankId: TANK_ONE,
        date: isoDate(SINGLE.startDay + 9),
        rows: [{ slot: '12:00', weight: 500 }],
      })

      // PERIOD_LATEOPEN — the earliest reading records FFA but NO stock, so
      // the opening must come from the next FILLED reading, two days later.
      await createStorageTankRecord(page, {
        storageTankId: TANK_LATE,
        date: LATEOPEN.start,
        rows: [{ slot: '06:00', ffa: 3.2 }],
      })
      await createStorageTankRecord(page, {
        storageTankId: TANK_LATE,
        date: isoDate(LATEOPEN.startDay + 2),
        rows: [{ slot: '12:00', weight: 150 }],
      })
      await createStorageTankRecord(page, {
        storageTankId: TANK_LATE,
        date: isoDate(LATEOPEN.startDay + 8),
        rows: [{ slot: '18:00', weight: 140 }],
      })

      // PERIOD_NOSTOCK — a tank with records but NOT ONE stock reading. It
      // must stay in the recap: dropping it would hide exactly the tank
      // nobody measured.
      await createStorageTankRecord(page, {
        storageTankId: TANK_NOSTOCK,
        date: NOSTOCK.start,
        rows: [
          { slot: '07:00', ffa: 4.0 },
          { slot: '08:00', ffa: 4.4 },
        ],
      })

      // PERIOD_MOVEMENT — the per-tank vs combined-stock trap: +10,0 one way,
      // +410,0 the other.
      await createStorageTankRecord(page, {
        storageTankId: TANK_SPAN,
        date: MOVEMENT.start,
        rows: [{ slot: '06:00', weight: 100 }],
      })
      await createStorageTankRecord(page, {
        storageTankId: TANK_SPAN,
        date: isoDate(MOVEMENT.startDay + 9),
        rows: [{ slot: '18:00', weight: 90 }],
      })
      await createStorageTankRecord(page, {
        storageTankId: TANK_LATEEND,
        date: isoDate(MOVEMENT.startDay + 8),
        rows: [{ slot: '06:00', weight: 400 }],
      })
      await createStorageTankRecord(page, {
        storageTankId: TANK_LATEEND,
        date: isoDate(MOVEMENT.startDay + 9),
        rows: [{ slot: '06:00', weight: 420 }],
      })

      // PERIOD_NEGATIVE — stock falling is the ordinary case, not an alarm.
      await createStorageTankRecord(page, {
        storageTankId: TANK_NEG,
        date: isoDate(NEGATIVE.startDay + 1),
        rows: [{ slot: '06:00', weight: 500 }],
      })
      await createStorageTankRecord(page, {
        storageTankId: TANK_NEG,
        date: isoDate(NEGATIVE.startDay + 7),
        rows: [{ slot: '18:00', weight: 320 }],
      })

      // PERIOD_NOIMP — Kotoran never filled while FFA is, three times.
      await createStorageTankRecord(page, {
        storageTankId: TANK_MISC,
        date: NOIMP.start,
        rows: [
          { slot: '07:00', ffa: 3.0, weight: 100 },
          { slot: '08:00', ffa: 4.0 },
          { slot: '09:00', ffa: 5.0 },
        ],
      })

      // PERIOD_NOAVGTEMP — the three positional temperatures WITHOUT the
      // average column: the screen must derive nothing (60,0 is the
      // arithmetic mean it must never compute).
      await createStorageTankRecord(page, {
        storageTankId: TANK_MISC,
        date: NOAVGTEMP.start,
        rows: [{ slot: '07:00', tempTop: 50, tempMiddle: 60, tempBottom: 70, weight: 100 }],
      })

      // PERIOD_AVGTEMP — the same three temperatures WITH an average of
      // 55,0, which disagrees with their mean of 60,0 on purpose.
      await createStorageTankRecord(page, {
        storageTankId: TANK_MISC,
        date: AVGTEMP.start,
        rows: [{ slot: '07:00', tempTop: 50, tempMiddle: 60, tempBottom: 70, avgTemp: 55, weight: 100 }],
      })

      // PERIOD_DENOM — every metric filled a different number of times.
      await createStorageTankRecord(page, {
        storageTankId: TANK_MISC,
        date: DENOM.start,
        rows: [
          { slot: '07:00', ffa: 3.0, moisture: 0.2 },
          { slot: '08:00', ffa: 5.0 },
          { slot: '09:00', dobi: 2.4 },
        ],
      })

      // PERIOD_SPARSE — 3 filled slots against 2 x 5 x 24 = 240 expected.
      await createStorageTankRecord(page, {
        storageTankId: TANK_SPARSE_A,
        date: SPARSE.start,
        rows: [
          { slot: '07:00', weight: 100, ffa: 4.0 },
          { slot: '08:00', weight: 90 },
        ],
      })
      await createStorageTankRecord(page, {
        storageTankId: TANK_SPARSE_B,
        date: isoDate(SPARSE.startDay + 2),
        rows: [{ slot: '07:00', weight: 300 }],
      })

      // PERIOD_LONG — 20 dates, one reading each.
      for (let day = 0; day < 20; day++) {
        await createStorageTankRecord(page, {
          storageTankId: TANK_MISC,
          date: isoDate(LONG.startDay + day),
          rows: [{ slot: '07:00', weight: 100 + day, ffa: 4.0, moisture: 0.2, dobi: 3.0 }],
        })
      }

      // PERIOD_INCLUSIVE — exactly on both bounds, plus one day outside each
      // end that must appear nowhere.
      await createStorageTankRecord(page, {
        storageTankId: TANK_MISC,
        date: isoDate(INCLUSIVE.startDay - 1),
        rows: [{ slot: '23:00', weight: 999 }],
      })
      await createStorageTankRecord(page, {
        storageTankId: TANK_MISC,
        date: INCLUSIVE.start,
        rows: [{ slot: '00:00', weight: 111 }],
      })
      await createStorageTankRecord(page, {
        storageTankId: TANK_MISC,
        date: isoDate(INCLUSIVE.startDay + 9),
        rows: [{ slot: '23:00', weight: 222 }],
      })
      await createStorageTankRecord(page, {
        storageTankId: TANK_MISC,
        date: isoDate(INCLUSIVE.startDay + 10),
        rows: [{ slot: '00:00', weight: 888 }],
      })

      // PERIOD_EXTREME — an extreme beside an ordinary value.
      await createStorageTankRecord(page, {
        storageTankId: TANK_MISC,
        date: EXTREME.start,
        rows: [
          { slot: '07:00', ffa: 4.0, avgTemp: 50, dobi: 3.0, moisture: 0.2, weight: 100 },
          { slot: '08:00', ffa: 42.0, avgTemp: 150, dobi: 3.1, moisture: 0.21, weight: 90 },
          { slot: '09:00', ffa: 4.2, avgTemp: 51, dobi: 2.9, moisture: 0.19 },
        ],
      })

      // PERIOD_CLOSED — data first, then the period is closed, so the
      // "closed periods stay readable and exportable" scenario has figures.
      await createStorageTankRecord(page, {
        storageTankId: TANK_MISC,
        date: CLOSED.start,
        rows: [
          { slot: '07:00', weight: 100, ffa: 4.0, moisture: 0.2, dobi: 3.0, avgTemp: 55 },
          { slot: '08:00', weight: 90, ffa: 4.4, moisture: 0.22, dobi: 2.8 },
        ],
      })

      // --- Close the closed period (Admin again) -----------------------
      await page.context().clearCookies()
      await login(page, ADMIN, PASSWORD)
      await page.goto(PERIODS_PATH)
      // Closing is PER STATION now: only the Storage Tank row of this period
      // is closed, and the report must stay fully readable and exportable.
      await closeStation(page, PERIOD_CLOSED, STATION_TYPE)
    } finally {
      await page.close()
    }
  })

  // =====================================================================
  // Scenario 1: "berhasil sebagai Supervisor atau Mill Management"
  // =====================================================================
  test('berhasil: kartu stok beserta waktu pembacaannya, tabel per tangki, lima kartu metrik, kedua grafik, dan unduhan CSV', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MAIN)

    // A bound role gets a caption, never a picker.
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)

    // Recording coverage is part of the report body, above every number.
    await expect(page.locator('[data-testid="coverage-card"]')).toBeVisible()
    await expect(page.locator('[data-testid="coverage-filled-slots"]')).toHaveText('6')
    await expect(page.locator('[data-testid="coverage-expected-slots"]')).toHaveText('480')

    // STOCK, WITH THE INSTANT OF THE READING EACH FIGURE CAME FROM.
    await expect(page.locator('[data-testid="stock-opening-mt"]')).toHaveText('500,0')
    await expect(page.locator('[data-testid="opening-at"]')).toHaveText(instantLabel(MAIN.start, '06:00'))
    await expect(page.locator('[data-testid="stock-closing-mt"]')).toHaveText('510,0')
    await expect(page.locator('[data-testid="closing-at"]')).toHaveText(instantLabel(isoDate(MAIN.startDay + 9), '18:00'))
    await expect(page.locator('[data-testid="stock-movement-mt"]')).toHaveText('+10,0')
    await expect(page.locator('[data-testid="tanks-with-movement"]')).toHaveText('2')
    await expect(page.locator('[data-testid="tanks-without-movement"]')).toHaveText('0')

    // FIVE metric cards, each carrying its OWN reading count.
    for (const slug of ['ffa', 'moisture', 'impurities', 'dobi', 'temperature']) {
      await expect(page.locator(`[data-testid="metric-card-${slug}"]`)).toBeVisible()
      await expect(page.locator(`[data-testid="metric-${slug}-reading-count"]`)).toBeVisible()
    }

    await expect(page.locator('[data-testid="metric-ffa-reading-count"]')).toHaveText('3')

    // Both charts, the per-tank recap and the daily recap.
    await expect(page.locator('[data-testid="quality-trend-chart"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="stock-trend-chart"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="by-tank-table"]')).toBeVisible()
    await expect(page.locator(`[data-testid="by-tank-row-${TANK_SCRAMBLED}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="by-tank-row-${TANK_QUALITY}"]`)).toBeVisible()
    await expect(page.locator('[data-testid="daily-table"]')).toBeVisible()

    // Export — a Livewire download, not a navigation.
    const csv = await downloadCsv(page)
    expect(csv).toContain('Slot Waktu')
    expect(csv).toContain('Berat Terhitung (MT)')
    expect(csv).toContain(TANK_SCRAMBLED)

    // No write-flavoured control anywhere.
    for (const control of ['save-button', 'edit-button', 'delete-button', 'add-row-button']) {
      await expect(page.locator(`[data-testid="${control}"]`)).toHaveCount(0)
    }
  })

  test('berhasil sebagai Mill Management: laporan yang sama, tetap tanpa pemilih Mill', async ({ page }) => {
    await openReport(page, MILL_MANAGEMENT)
    await selectPeriod(page, PERIOD_MAIN)

    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="stock-opening-mt"]')).toHaveText('500,0')
    await expect(page.locator('[data-testid="stock-movement-mt"]')).toHaveText('+10,0')
    await expect(page.locator('[data-testid="by-tank-table"]')).toBeVisible()
  })

  // =====================================================================
  // Scenario 2: "berhasil sebagai Admin"
  // =====================================================================
  test('admin memilih mill: pemilih Mill terlihat, lalu seluruh blok angka muncul dan CSV terunduh', async ({ page }) => {
    await openReport(page, ADMIN)

    await expect(page.locator('[data-testid="mill-select"]')).toBeVisible()
    await page.locator('[data-testid="mill-select"]').selectOption({ label: BUSINESS_UNIT })
    await expect(page.locator('[data-testid="mill-select-hint"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="period-select"]')).toBeVisible()

    await selectPeriod(page, PERIOD_MAIN)

    // Identical blocks to every other role.
    await expect(page.locator('[data-testid="coverage-card"]')).toBeVisible()
    await expect(page.locator('[data-testid="stock-opening-mt"]')).toHaveText('500,0')
    await expect(page.locator('[data-testid="stock-movement-mt"]')).toHaveText('+10,0')
    await expect(page.locator('[data-testid="quality-trend-chart"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="stock-trend-chart"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="by-tank-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="export-button"]')).toBeEnabled()

    const csv = await downloadCsv(page)
    expect(csv).toContain(TANK_QUALITY)

    for (const control of ['save-button', 'edit-button', 'delete-button']) {
      await expect(page.locator(`[data-testid="${control}"]`)).toHaveCount(0)
    }
  })

  // =====================================================================
  // Scenario 3: "Admin memilih mill lebih dulu"
  // =====================================================================
  test('admin tanpa mill: arahan memilih mill terlihat dan tidak ada satu pun kartu angka maupun grafik', async ({ page }) => {
    await openReport(page, ADMIN)

    await expect(page.locator('[data-testid="mill-select"]')).toHaveValue('')
    await expect(page.locator('[data-testid="mill-select-hint"]')).toBeVisible()
    // The page ASKS for a mill instead of drawing an empty report that would
    // read as "this mill has no data".
    for (const testid of [
      'coverage-card', 'stock-opening-card', 'stock-closing-card', 'stock-movement-card',
      'metric-card-ffa', 'metric-card-temperature', 'quality-trend-chart', 'stock-trend-chart',
      'by-tank-table', 'daily-table', 'period-select', 'export-button',
    ]) {
      await expect(page.locator(`[data-testid="${testid}"]`)).toHaveCount(0)
    }
  })

  // =====================================================================
  // Scenario 4: "mill belum punya periode"
  // =====================================================================
  test('belum ada periode: pemilih periode kosong dengan arahan menghubungi Admin, tanpa angka', async ({ page }) => {
    await openReport(page, ADMIN)

    // Which mills have no Storage Tank period is environment data, so the
    // spec looks for one instead of hardcoding a name. The values are read
    // up front: every selection re-renders the picker, which would stale the
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
      // One Livewire round trip: either the period picker fills, or the
      // "no period yet" hint appears.
      await page.waitForTimeout(500)

      if (await page.locator('[data-testid="no-period-hint"]').isVisible()) {
        found = true
        break
      }
    }

    expect(found, 'no mill without a Storage Tank reporting period exists in this environment').toBe(true)

    await expect(page.locator('[data-testid="no-period-hint"]')).toContainText('Belum ada Periode Pelaporan')
    await expect(page.locator('[data-testid="period-select"] option')).toHaveCount(0)
    await expect(page.locator('[data-testid="coverage-card"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="stock-opening-card"]')).toHaveCount(0)
    // An empty mill is not an error page.
    await expect(page.locator('[data-testid="laporan-storage-tank"]')).toBeVisible()
  })

  // =====================================================================
  // Scenario 5: "periode tanpa data"
  // =====================================================================
  test('periode tanpa data: keterangan belum ada data, kartu berbunyi tidak tersedia bukan nol, tanpa grafik kosong', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_EMPTY)

    await expect(page.locator('[data-testid="empty-state"]')).toContainText('Belum ada data pada periode ini')

    // NOT '0': zero would read as "measured, and it was zero". The three
    // stock figures and the four quality cards all say it in words.
    for (const testid of ['stock-opening-mt', 'stock-closing-mt', 'stock-movement-mt', 'opening-at', 'closing-at']) {
      await expect(page.locator(`[data-testid="${testid}"]`)).toHaveText('tidak tersedia')
    }

    for (const slug of ['ffa', 'moisture', 'impurities', 'dobi']) {
      await expect(page.locator(`[data-testid="metric-${slug}-avg"]`)).toHaveText('tidak tersedia')
      await expect(page.locator(`[data-testid="metric-${slug}-reading-count"]`)).toHaveText('0')
    }

    // No empty chart, no empty recap — a flat line would read as a
    // measurement, and an empty table as a tank that was checked and found
    // to hold nothing.
    for (const testid of [
      'quality-trend-chart', 'stock-trend-chart', 'by-tank-table', 'daily-table', 'metric-card-temperature',
    ]) {
      await expect(page.locator(`[data-testid="${testid}"]`)).toHaveCount(0)
    }
  })

  // =====================================================================
  // Scenario 6: "tangki hanya punya satu pembacaan stok"
  // =====================================================================
  test('pembacaan tunggal: sel pergerakan berbunyi tidak dapat dihitung dan bukan nol, stok awal dan akhirnya satu waktu', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_SINGLE)

    const row = page.locator(`[data-testid="by-tank-row-${TANK_ONE}"]`)

    await expect(row).toBeVisible()
    // Opening and closing are the SAME reading at the SAME instant — which
    // is exactly why their difference measures nothing.
    await expect(row.locator('td').nth(1)).toHaveText('500,0')
    await expect(row.locator('td').nth(3)).toHaveText('500,0')
    await expect(row.locator('td').nth(2)).toHaveText(instantLabel(isoDate(SINGLE.startDay + 9), '12:00'))
    await expect(row.locator('td').nth(4)).toHaveText(instantLabel(isoDate(SINGLE.startDay + 9), '12:00'))

    // THE CELL THAT CARRIES THE WHOLE POINT. "0,0" would claim the stock did
    // not change — a stronger claim that was never measured. "tidak
    // tersedia" would claim the tank was never read at all, which is the
    // NEXT scenario and a different fact.
    const movementCell = row.locator('td').nth(5)

    await expect(movementCell).toHaveText('tidak dapat dihitung')
    await expect(movementCell).not.toHaveText('0,0')
    await expect(movementCell).not.toHaveText('tidak tersedia')

    // The single reading contributes nothing to the period figure: +30,0
    // from the other tank, never +530,0.
    await expect(page.locator('[data-testid="stock-movement-mt"]')).toHaveText('+30,0')
    await expect(page.locator('[data-testid="tanks-without-movement"]')).toHaveText('1')
    await expect(page.locator('[data-testid="tanks-with-movement"]')).toHaveText('1')
  })

  // =====================================================================
  // Scenario 7: "pembacaan paling awal tidak mencatat stok"
  // =====================================================================
  test('awal kosong: stok awal 150,0 disertai tanggal pembacaan yang sebenarnya, bukan tanggal awal periode', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_LATEOPEN)

    const realOpening = instantLabel(isoDate(LATEOPEN.startDay + 2), '12:00')

    // The card's opening timestamp points at the reading that was actually
    // used, so the two unrecorded days become visible instead of implied.
    await expect(page.locator('[data-testid="stock-opening-mt"]')).toHaveText('150,0')
    await expect(page.locator('[data-testid="opening-at"]')).toHaveText(realOpening)
    await expect(page.locator('[data-testid="opening-at"]')).not.toHaveText(instantLabel(LATEOPEN.start, '06:00'))

    const row = page.locator(`[data-testid="by-tank-row-${TANK_LATE}"]`)

    await expect(row).toContainText('150,0')
    await expect(row).toContainText(realOpening)
    // ...and the row says in words that the tank was only recorded from the
    // third day of the period onwards.
    await expect(row).toContainText('baru tercatat sejak')
  })

  // =====================================================================
  // Scenario 8: "tangki tanpa satu pun pembacaan stok"
  // =====================================================================
  test('tanpa pembacaan stok: baris tangki tetap ada di rekap dengan stok dan pergerakan tidak tersedia', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_NOSTOCK)

    const row = page.locator(`[data-testid="by-tank-row-${TANK_NOSTOCK}"]`)

    // NOT dropped — dropping it would hide exactly the tank nobody measured.
    await expect(row).toBeVisible()

    // Stok awal, waktu awal, stok akhir, waktu akhir and pergerakan: five
    // cells, all "tidak tersedia".
    for (const cell of [1, 2, 3, 4, 5]) {
      await expect(row.locator('td').nth(cell)).toHaveText('tidak tersedia')
    }

    // "tidak dapat dihitung" is the SINGLE-READING case, a different claim,
    // and 0 would be a third one again.
    await expect(row.locator('td').nth(5)).not.toHaveText('tidak dapat dihitung')
    await expect(row.locator('td').nth(5)).not.toHaveText('0,0')

    // Its reading count is real and non-zero: the tank WAS visited, and its
    // FFA average is published from its own denominator.
    await expect(row.locator('td').nth(8)).toHaveText('2')
    await expect(row.locator('td').nth(6)).toHaveText('4,2')
  })

  // =====================================================================
  // Scenario 9: "jumlah tangki berbeda antara awal dan akhir periode"
  // =====================================================================
  test('jumlah tangki berbeda di kedua ujung: kartu pergerakan sama dengan jumlah kolom per tangki, bukan selisih stok gabungan', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MOVEMENT)

    await expect(page.locator('[data-testid="stock-movement-mt"]')).toHaveText('+10,0')

    // The per-tank movements the card is the column sum of.
    await expect(page.locator(`[data-testid="by-tank-row-${TANK_SPAN}"]`)).toContainText('-10,0')
    await expect(page.locator(`[data-testid="by-tank-row-${TANK_LATEEND}"]`)).toContainText('+20,0')
    // The table foot repeats the card's figure, so the reader can check one
    // against the other.
    await expect(page.locator('[data-testid="by-tank-row-total"]')).toContainText('+10,0')

    // The combined-stock answer appears NOWHERE on the page.
    await expect(page.locator('[data-testid="laporan-storage-tank"]')).not.toContainText('410,0')

    // And the late tank says, on its own row, that it was only recorded
    // near the end of the period.
    await expect(page.locator(`[data-testid="by-tank-row-${TANK_LATEEND}"]`)).toContainText('baru tercatat sejak')
  })

  // =====================================================================
  // Scenario 10: "pergerakan bernilai negatif"
  // =====================================================================
  test('pergerakan negatif: -180,0 bertanda minus ASCII tanpa ikon maupun warna peringatan, dan tidak dibulatkan ke nol', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_NEGATIVE)

    const movement = page.locator('[data-testid="stock-movement-mt"]')

    await expect(movement).toHaveText('-180,0')
    await expect(movement).not.toHaveText('180,0')
    await expect(movement).not.toHaveText('0,0')
    // An ASCII hyphen-minus, not U+2212 — the sign has to survive a copy
    // into a spreadsheet.
    expect(await movement.innerText()).not.toContain('−')

    // Oil leaving the tank is the ordinary case: the movement card carries
    // the SAME class attribute as the opening card.
    expect(await classOf(page, 'stock-movement-card')).toBe(await classOf(page, 'stock-opening-card'))

    for (const flag of ['is-danger', 'is-warning', 'text-red', 'bg-red', 'threshold', 'alert', 'severity']) {
      await expect(page.locator(`[data-testid="laporan-storage-tank"] [class*="${flag}"]`)).toHaveCount(0)
    }
  })

  // =====================================================================
  // Scenario 11: "sebuah metrik mutu tidak pernah diisi"
  // =====================================================================
  test('metrik tidak pernah diisi: kartu Kotoran berbunyi tidak tersedia dengan 0 pembacaan, kartu FFA tetap normal', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_NOIMP)

    await expect(page.locator('[data-testid="metric-impurities-avg"]')).toHaveText('tidak tersedia')
    await expect(page.locator('[data-testid="metric-impurities-min"]')).toHaveText('tidak tersedia')
    await expect(page.locator('[data-testid="metric-impurities-max"]')).toHaveText('tidak tersedia')
    await expect(page.locator('[data-testid="metric-impurities-reading-count"]')).toHaveText('0')

    // The neighbour is untouched, with its OWN denominator.
    await expect(page.locator('[data-testid="metric-ffa-avg"]')).toHaveText('4,0')
    await expect(page.locator('[data-testid="metric-ffa-min"]')).toHaveText('3,0')
    await expect(page.locator('[data-testid="metric-ffa-max"]')).toHaveText('5,0')
    await expect(page.locator('[data-testid="metric-ffa-reading-count"]')).toHaveText('3')
  })

  // =====================================================================
  // Scenario 12: "kolom suhu rata-rata kosong"
  // =====================================================================
  test('suhu rata-rata kosong: kartu berbunyi tidak tersedia dan tidak menampilkan nilai hasil hitung ulang', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_NOAVGTEMP)

    await expect(page.locator('[data-testid="metric-temperature-avg"]')).toHaveText('tidak tersedia')
    await expect(page.locator('[data-testid="metric-temperature-min"]')).toHaveText('tidak tersedia')
    await expect(page.locator('[data-testid="metric-temperature-max"]')).toHaveText('tidak tersedia')
    await expect(page.locator('[data-testid="metric-temperature-reading-count"]')).toHaveText('0')

    // 60,0 is the arithmetic mean of 50/60/70 — the answer a recomputing
    // implementation would give. It must not be the card's figure...
    await expect(page.locator('[data-testid="metric-temperature-avg"]')).not.toHaveText('60,0')

    // ...nor appear ANYWHERE on the card, explanation box included. The
    // fixture (PERIOD_NOAVGTEMP: 50/60/70 with the average column empty)
    // supplies no 60 of its own, so a 60 on this card could only have been
    // derived from the three positional temperatures.
    await expect(page.locator('[data-testid="metric-card-temperature"]')).not.toContainText('60,0')
    await expect(page.locator('[data-testid="metric-card-temperature"]')).not.toContainText('60')

    // The card DOES carry an explanation of where the number comes from —
    // an explanation box, never a threshold box.
    await expect(page.locator('[data-testid="temperature-source-note"]')).toBeVisible()
    await expect(page.locator('[data-testid="laporan-storage-tank"] [class*="md-threshold"]')).toHaveCount(0)
  })

  // =====================================================================
  // Scenario 13: "pencatatan sangat tidak lengkap"
  // =====================================================================
  test('kelengkapan rendah: kartu kelengkapan terlihat tanpa menggulir dan angka utama tetap tampil', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_SPARSE)

    await expect(page.locator('[data-testid="coverage-card"]')).toBeVisible()
    await expect(page.locator('[data-testid="coverage-filled-slots"]')).toHaveText('3')
    await expect(page.locator('[data-testid="coverage-expected-slots"]')).toHaveText('240')
    await expect(page.locator('[data-testid="coverage-percent"]')).toHaveText('1,25%')
    await expect(page.locator('[data-testid="low-coverage-emphasis"]')).toBeVisible()

    // PART OF THE REPORT BODY, NOT A FOOTNOTE: on this screen coverage says
    // how far apart the two compared readings sit, so it is in the viewport
    // before the figures are.
    await expect(page.locator('[data-testid="coverage-card"]')).toBeInViewport()

    // ...and the figures are still there.
    await expect(page.locator('[data-testid="stock-opening-mt"]')).toHaveText('400,0')
    await expect(page.locator('[data-testid="stock-movement-mt"]')).toHaveText('-10,0')
    await expect(page.locator('[data-testid="by-tank-table"]')).toBeVisible()
  })

  // =====================================================================
  // Scenario 14: "akun belum terhubung ke mill"
  //
  // THE NULL-MILL ACCOUNT ITSELF IS NOT SEEDABLE THROUGH THE PRODUCT'S OWN
  // SCREENS. UserService::validate() makes business_unit_id REQUIRED for
  // every role except Admin ("Business Unit wajib dipilih untuk role selain
  // Admin."), so a Supervisor / Mill Management account with a NULL mill
  // cannot be created from Kelola User & Role nor through /api/users; such a
  // row can only come from a broken import or a direct database edit.
  //
  // What the browser CAN prove, and what this test does prove, is the
  // fail-closed half of the rule: for a mill-bound role this screen never
  // offers an all-mills picker under ANY condition — not on first paint, not
  // after a period is chosen, and not when the query string asks for one.
  // The NULL-mill branch itself is covered where the row can be built
  // directly: the SPY in StorageTankReportServiceTest case 36 proves the
  // all-mills list is never even read, and the Api / Livewire suites assert
  // the 422 and the contact-Admin notice with no picker at all.
  // =====================================================================
  test('akun terikat mill: daftar seluruh mill tidak pernah ditawarkan, bahkan saat query string memintanya', async ({ page }) => {
    await openReport(page, SUPERVISOR)

    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)

    await selectPeriod(page, PERIOD_MAIN)
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)

    // Even asked for explicitly, the picker is not rendered: a role that is
    // supposed to be tied to exactly one mill is never shown the list of all
    // of them.
    await page.goto(`${REPORT_PATH}?business_unit_id=`)
    await expect(page.locator('[data-testid="laporan-storage-tank"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)
  })

  // =====================================================================
  // Scenario 15: "mencoba melihat mill lain"
  // =====================================================================
  test('mill lain: query string business_unit_id diabaikan, mill akun tetap yang tampil, dan periode mill lain tidak terbuka', async ({ page }) => {
    // Read another mill's id from the Admin picker — no mill id is
    // hardcoded, since that is environment data.
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

    await page.context().clearCookies()
    await login(page, SUPERVISOR, PASSWORD)

    // First request — another mill named in the query string. Answered with
    // the caller's OWN data rather than a refusal, which would confirm the
    // other mill exists. (The component does not bind `businessUnitId` to
    // the URL at all for a bound role, so the parameter cannot even reach
    // the property — a stronger form of the same guard.)
    await page.goto(`${REPORT_PATH}?business_unit_id=${otherMillId}`)

    await expect(page.locator('[data-testid="laporan-storage-tank"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="laporan-storage-tank"]')).not.toContainText(otherMillName)

    await selectPeriod(page, PERIOD_MAIN)
    await expect(page.locator('[data-testid="stock-opening-mt"]')).toHaveText('500,0')

    // Second request — a period id that is not among this mill's periods.
    // No other mill's figure is ever rendered.
    await page.goto(`${REPORT_PATH}?period_id=00000000-0000-0000-0000-000000000000`)

    await expect(page.locator('[data-testid="laporan-storage-tank"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="laporan-storage-tank"]')).not.toContainText(otherMillName)
  })

  // =====================================================================
  // Scenario 16: "Operator mencoba membuka layar web ini"
  // =====================================================================
  test('operator: akses laporan WEB ditolak dan tidak ada angka Storage Tank yang terlihat', async ({ page }) => {
    await login(page, OPERATOR, PASSWORD)
    await page.goto(REPORT_PATH)

    // EnsureRole -> abort(403): the error page, never the report. There is
    // NO Operator widening here — the MOBILE Storage Tank report is
    // screen-139, a separate screen with its own endpoints.
    await expect(page.locator('body')).toContainText(/403|Forbidden/i)

    for (const testid of [
      'laporan-storage-tank', 'coverage-card', 'stock-opening-card', 'stock-movement-card',
      'by-tank-table', 'daily-table', 'metric-card-ffa', 'export-button',
    ]) {
      await expect(page.locator(`[data-testid="${testid}"]`)).toHaveCount(0)
    }
  })

  // =====================================================================
  // Scenario 17: "periode tertutup"
  // =====================================================================
  test('periode tertutup: penanda Tertutup, laporan penuh, dan unduhan CSV tetap berhasil', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_CLOSED)

    await expect(page.locator('[data-testid="period-status-badge"]')).toHaveText('Tertutup')
    await expect(page.locator('[data-testid="coverage-card"]')).toBeVisible()
    await expect(page.locator('[data-testid="stock-opening-mt"]')).toHaveText('100,0')
    await expect(page.locator('[data-testid="stock-movement-mt"]')).toHaveText('-10,0')
    await expect(page.locator('[data-testid="by-tank-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-table"]')).toBeVisible()
    // The period lock governs writing data, not reading a report.
    await expect(page.locator('[data-testid="export-button"]')).toBeEnabled()

    const csv = await downloadCsv(page)
    expect(csv).toContain(CLOSED.start)
  })

  // =====================================================================
  // Scenario 18: "rekap harian panjang"
  // =====================================================================
  test('rekap harian: terbuka secara bawaan, tombol menutupnya lalu membukanya kembali, angka utama dan grafik tetap terlihat', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_LONG)

    // OPEN BY DEFAULT — a decision, not a default left alone.
    await expect(page.locator('[data-testid="daily-toggle"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-table"] tbody tr')).toHaveCount(20)

    await toggleRecap(page)

    // Closed means genuinely ABSENT from the DOM, not merely hidden: a
    // button rather than <details>, so the visible state and the rendered
    // DOM can never disagree.
    await expect(page.locator('[data-testid="daily-table"]')).toHaveCount(0)
    // ...while the main figures and both charts stay readable on the first
    // screen, without a long scroll.
    await expect(page.locator('[data-testid="stock-opening-card"]')).toBeVisible()
    await expect(page.locator('[data-testid="stock-movement-card"]')).toBeVisible()
    await expect(page.locator('[data-testid="quality-trend-chart"]')).toBeVisible()
    await expect(page.locator('[data-testid="stock-trend-chart"]')).toBeVisible()

    await toggleRecap(page)

    await expect(page.locator('[data-testid="daily-table"]')).toBeVisible()
    await expect(page.locator(`[data-testid="daily-row-${LONG.start}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="daily-row-${isoDate(LONG.startDay + 19)}"]`)).toBeVisible()
  })

  // =====================================================================
  // Scenario 19: "stok awal adalah pembacaan pertama dan stok akhir terakhir"
  // =====================================================================
  test('stok awal/akhir menurut waktu: 100,0 dan 80,0 dengan waktunya, sementara 250,0 tidak pernah menjadi salah satunya', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MAIN)

    const row = page.locator(`[data-testid="by-tank-row-${TANK_SCRAMBLED}"]`)

    await expect(row).toContainText('100,0')
    await expect(row).toContainText(instantLabel(MAIN.start, '06:00'))
    await expect(row).toContainText('80,0')
    await expect(row).toContainText(instantLabel(isoDate(MAIN.startDay + 9), '18:00'))
    await expect(row).toContainText('-20,0')
    // Neither the highest value (250,0) nor the first row entered.
    await expect(row).not.toContainText('250,0')

    // The period card sums the tanks' own openings and closings, and its
    // timestamps span the earliest opening to the latest closing.
    await expect(page.locator('[data-testid="stock-opening-mt"]')).toHaveText('500,0')
    await expect(page.locator('[data-testid="opening-at"]')).toHaveText(instantLabel(MAIN.start, '06:00'))
    await expect(page.locator('[data-testid="stock-closing-mt"]')).toHaveText('510,0')
    await expect(page.locator('[data-testid="closing-at"]')).toHaveText(instantLabel(isoDate(MAIN.startDay + 9), '18:00'))
  })

  // =====================================================================
  // Scenario 20: "pergerakan dihitung per tangki lalu dijumlahkan"
  // =====================================================================
  test('pergerakan bersih identik dengan jumlah kolom pergerakan pada tabel per tangki, dan bukan angka cara stok gabungan', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MOVEMENT)

    const card = (await page.locator('[data-testid="stock-movement-mt"]').innerText()).trim()

    expect(card).toBe('+10,0')

    // Sum the per-tank column straight off the table and compare it with the
    // card — the table IS what the card is derived from, and the two are
    // meant to be checkable against each other by the reader.
    const movementCells = await page
      .locator('[data-testid="by-tank-table"] tbody tr td:nth-child(6)')
      .allInnerTexts()

    const summed = movementCells
      .map((text) => text.trim())
      .filter((text) => /^[+-]?\d/.test(text))
      .reduce((total, text) => total + Number(text.replace(/\./g, '').replace(',', '.')), 0)

    expect(summed).toBeCloseTo(10.0, 5)
    // The combined-stock answer appears nowhere on the page.
    await expect(page.locator('[data-testid="laporan-storage-tank"]')).not.toContainText('410,0')
  })

  // =====================================================================
  // Scenario 21: "suhu rata-rata diambil dari kolom yang dicatat Operator"
  // =====================================================================
  test('suhu rata-rata: kartu menampilkan 55,0 dari kolom Operator, dan bukan 60,0 hasil hitung ulang', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_AVGTEMP)

    await expect(page.locator('[data-testid="metric-temperature-avg"]')).toHaveText('55,0')
    await expect(page.locator('[data-testid="metric-temperature-avg"]')).not.toHaveText('60,0')
    await expect(page.locator('[data-testid="metric-temperature-min"]')).toHaveText('55,0')
    await expect(page.locator('[data-testid="metric-temperature-max"]')).toHaveText('55,0')
    await expect(page.locator('[data-testid="metric-temperature-reading-count"]')).toHaveText('1')

    // The recomputed mean is nowhere on the card at all — figures, meta,
    // foot and explanation box alike. PERIOD_AVGTEMP records 50/60/70 with
    // an Operator average of 55,0, so any 60 here would be derived.
    await expect(page.locator('[data-testid="metric-card-temperature"]')).toContainText('55,0')
    await expect(page.locator('[data-testid="metric-card-temperature"]')).not.toContainText('60,0')
    await expect(page.locator('[data-testid="metric-card-temperature"]')).not.toContainText('60')

    // The per-tank column reads the same Operator-recorded column.
    await expect(page.locator(`[data-testid="by-tank-row-${TANK_MISC}"]`)).toContainText('55,0')
  })

  // =====================================================================
  // Scenario 22: "setiap metrik punya penyebutnya sendiri"
  // =====================================================================
  test('penyebut terpisah: jumlah pembacaan berbeda-beda antar kartu sesuai pengisiannya masing-masing', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_DENOM)

    await expect(page.locator('[data-testid="metric-ffa-reading-count"]')).toHaveText('2')
    await expect(page.locator('[data-testid="metric-ffa-avg"]')).toHaveText('4,0')

    await expect(page.locator('[data-testid="metric-moisture-reading-count"]')).toHaveText('1')
    // 0,2 — never 0,07, which is what one shared denominator of 3 would give.
    await expect(page.locator('[data-testid="metric-moisture-avg"]')).toHaveText('0,2')
    await expect(page.locator('[data-testid="metric-moisture-avg"]')).not.toHaveText('0,07')

    await expect(page.locator('[data-testid="metric-dobi-reading-count"]')).toHaveText('1')
    await expect(page.locator('[data-testid="metric-dobi-avg"]')).toHaveText('2,4')

    await expect(page.locator('[data-testid="metric-impurities-reading-count"]')).toHaveText('0')

    // NOT ONE card carries the shared filled-row count of 3.
    await expect(page.locator('[data-testid="coverage-filled-slots"]')).toHaveText('3')

    for (const slug of ['ffa', 'moisture', 'impurities', 'dobi']) {
      await expect(page.locator(`[data-testid="metric-${slug}-reading-count"]`)).not.toHaveText('3')
    }
  })

  // =====================================================================
  // Scenario 23: "rentang periode inklusif di kedua ujung"
  // =====================================================================
  test('rentang inklusif: rekap harian dan CSV memuat kedua tanggal ujung, dan tanggal di luar rentang tidak muncul', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_INCLUSIVE)

    const before = isoDate(INCLUSIVE.startDay - 1)
    const after = isoDate(INCLUSIVE.startDay + 10)
    const last = isoDate(INCLUSIVE.startDay + 9)

    // The daily recap is OPEN by default, so both end dates read straight
    // off the table without toggling anything.
    await expect(page.locator(`[data-testid="daily-row-${INCLUSIVE.start}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="daily-row-${last}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="daily-row-${before}"]`)).toHaveCount(0)
    await expect(page.locator(`[data-testid="daily-row-${after}"]`)).toHaveCount(0)
    await expect(page.locator('[data-testid="daily-table"] tbody tr')).toHaveCount(2)

    await expect(page.locator('[data-testid="stock-opening-mt"]')).toHaveText('111,0')
    await expect(page.locator('[data-testid="opening-at"]')).toHaveText(instantLabel(INCLUSIVE.start, '00:00'))
    await expect(page.locator('[data-testid="stock-closing-mt"]')).toHaveText('222,0')
    await expect(page.locator('[data-testid="closing-at"]')).toHaveText(instantLabel(last, '23:00'))
    // The out-of-range readings never become an endpoint, and never appear.
    await expect(page.locator('[data-testid="laporan-storage-tank"]')).not.toContainText('999,0')
    await expect(page.locator('[data-testid="laporan-storage-tank"]')).not.toContainText('888,0')

    const csv = await downloadCsv(page)

    expect(csv).toContain(INCLUSIVE.start)
    expect(csv).toContain(last)
    expect(csv).not.toContain(before)
    expect(csv).not.toContain(after)
  })

  // =====================================================================
  // Scenario 24: "FFA, kadar air, dan DOBI pada satu grafik"
  // =====================================================================
  test('satu grafik: FFA, kadar air, dan DOBI bersama pada satu bidang gambar, dengan legenda yang menyatakan normalisasinya', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MAIN)

    // ONE drawing area for the three series — not three separate charts.
    await expect(page.locator('[data-testid="quality-trend-chart"]')).toHaveCount(1)

    const legend = page.locator('[data-testid="quality-trend-legend"]')

    await expect(legend).toBeVisible()
    await expect(legend).toContainText('FFA')
    await expect(legend).toContainText('Kadar Air')
    await expect(legend).toContainText('DOBI')

    // THE BINDING ASSERTION: the scale treatment is STATED. FFA runs 3-5 %,
    // kadar air 0,12-0,29 % and DOBI 2,4-3,8 without a unit; pinned to one
    // value axis without a word, two of the three would draw as flat lines
    // at the bottom and read as "nothing changed".
    await expect(legend).toContainText('dinormalkan')
    await expect(legend).toContainText('indeks 100')
    await expect(page.locator('[data-testid="quality-trend-note"]')).toContainText('dinormalkan')

    // The stock chart states its own scale choice too: an axis that does not
    // start at zero.
    await expect(page.locator('[data-testid="stock-trend-legend"]')).toContainText('tidak dimulai dari nol')

    // And the RAW values remain, in full, on the recap below the chart —
    // which is what makes the normalisation hide nothing.
    await expect(page.locator(`[data-testid="daily-row-${isoDate(MAIN.startDay + 5)}"]`)).toContainText('4,9')
    await expect(page.locator(`[data-testid="daily-row-${isoDate(MAIN.startDay + 5)}"]`)).toContainText('0,29')
    await expect(page.locator(`[data-testid="daily-row-${isoDate(MAIN.startDay + 5)}"]`)).toContainText('2,4')
  })

  // =====================================================================
  // Scenario 25: "tidak ada penandaan nilai di luar batas"
  // =====================================================================
  test('nilai ekstrem: kartu ekstrem dan kartu biasa membawa atribut class yang sama, tanpa satu pun elemen penandaan', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_EXTREME)

    // The extremes are rendered as-is: judging them is the reader's job, and
    // Storage Tank has no operational-target master table.
    await expect(page.locator('[data-testid="metric-ffa-max"]')).toHaveText('42,0')
    await expect(page.locator('[data-testid="metric-temperature-max"]')).toHaveText('150,0')

    // THE STRONGER FORM OF THE RULE: the card holding the extreme FFA and
    // the card holding an ordinary DOBI carry the SAME class attribute,
    // byte for byte. There is no conditional branch on class on this page.
    const extremeClass = await classOf(page, 'metric-card-ffa')
    const ordinaryClass = await classOf(page, 'metric-card-dobi')

    expect(extremeClass).not.toBeNull()
    expect(extremeClass).toBe(ordinaryClass)

    // The negative-movement card, too.
    expect(await classOf(page, 'stock-movement-card')).toBe(await classOf(page, 'stock-opening-card'))

    for (const flag of [
      'threshold', 'outlier', 'iqr', 'fence', 'is-out-of-range',
      'severity', 'alert', 'text-red', 'bg-red', 'is-danger', 'is-warning',
    ]) {
      await expect(page.locator(`[data-testid="laporan-storage-tank"] [class*="${flag}"]`)).toHaveCount(0)
      await expect(page.locator(`[data-testid="laporan-storage-tank"] [data-testid*="${flag}"]`)).toHaveCount(0)
    }
  })

  // =====================================================================
  // Scenario 26: "layar hanya membaca"
  // =====================================================================
  test('baca saja: tidak ada tombol tambah, ubah, hapus atau simpan, dan angka tidak berubah setelah seluruh interaksi', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectPeriod(page, PERIOD_MAIN)

    const openingBefore = (await page.locator('[data-testid="stock-opening-mt"]').innerText()).trim()
    const movementBefore = (await page.locator('[data-testid="stock-movement-mt"]').innerText()).trim()

    for (const control of [
      'save-button', 'create-button', 'edit-button', 'delete-button',
      'add-row-button', 'remove-row-button', 'add-data-button',
    ]) {
      await expect(page.locator(`[data-testid="${control}"]`)).toHaveCount(0)
    }

    // Walk the whole page: change period, close and reopen the recap, run
    // the export.
    await selectPeriod(page, PERIOD_SPARSE)
    await selectPeriod(page, PERIOD_MAIN)
    await toggleRecap(page)
    await toggleRecap(page)
    await downloadCsv(page)

    // Reading it twice gives the same answer — this is a read, and nothing
    // it does may alter the data it reports on.
    await page.reload()
    await selectPeriod(page, PERIOD_MAIN)

    await expect(page.locator('[data-testid="stock-opening-mt"]')).toHaveText(openingBefore)
    await expect(page.locator('[data-testid="stock-movement-mt"]')).toHaveText(movementBefore)
    await expect(page.locator('[data-testid="coverage-filled-slots"]')).toHaveText('6')
  })

  // =====================================================================
  // Scenario 27: "daftar periode hanya yang mencakup Storage Tank"
  // =====================================================================
  test('pemilih periode: hanya periode yang punya baris Storage Tank, bukan periode tanpa baris itu', async ({ page, browser }) => {
    await openReport(page, SUPERVISOR)

    const options = page.locator('[data-testid="period-select"] option')

    await expect(options.filter({ hasText: PERIOD_MAIN })).toHaveCount(1)
    await expect(options.filter({ hasText: PERIOD_WHOLE_MILL })).toHaveCount(1)
    // A closed period is offered exactly like an open one: the period lock
    // governs writing data, not reading a report.
    await expect(options.filter({ hasText: PERIOD_CLOSED })).toHaveCount(1)
    // A period of another mill — one with no active station at all, so with
    // no `period_stations` row either — is not offered.
    await expect(options.filter({ hasText: PERIOD_OTHER_MILL })).toHaveCount(0)

    const labels = await options.allTextContents()
    const wholeMillIndex = labels.findIndex((label) => label.includes(PERIOD_WHOLE_MILL))
    const mainIndex = labels.findIndex((label) => label.includes(PERIOD_MAIN))

    // Newest first: this run's latest offered window is the whole-mill one,
    // so it leads the main period.
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
