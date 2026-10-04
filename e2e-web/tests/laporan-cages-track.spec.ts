/**
 * Laporan Cages & Tracks (Browser/Playwright) — screen-130--laporan-cages-track-web /
 * usecase-130--laporan-cages-track-web.
 *
 * One test per test_scenarios entry whose `browser_test` is non-empty (all
 * 21; two of them are documented below as not seedable through the
 * product's own screens). Route is /reports/cages-track — the repo's report
 * prefix is English (/reports/management, /reports/stations,
 * /reports/sterilizer); no route in this app uses /laporan.
 *
 * SELF-SUFFICIENT DATA, because this screen cannot create any: the report
 * is read-only by design, so there is no UI here to seed it with. The
 * fixtures are therefore built once, in beforeAll, through the two screens
 * that DO own that data:
 *   - /master-data/periods       (screen-128, as Admin)      — the periods,
 *     including the closed one and the ones this screen must refuse to
 *     offer;
 *   - /data/cages-track/create   (screen-024, as Supervisor) — the records
 *     and their hourly tipping rows, with the exact shape every assertion
 *     below depends on.
 *
 * WHY "Business Unit A" AND NOT "BU Browser Test": the Cages & Tracks grid
 * renders one checkbox column per MACHINERY row on the production line's
 * cages-track station (CagesTrackRecordService::machineryCountForStation).
 * BrowserTestFixtureSeeder seeds stations but no machinery at all, so on
 * "BU Browser Test" the grid has zero columns, `+ Tambah Baris` is disabled
 * and NO hourly row can be entered — the whole report would have nothing to
 * report on. "Business Unit A" (DemoMachineryDataSeeder) does carry
 * machinery, so that is the mill these fixtures live in. Both the
 * production line AND its column count are RESOLVED AT SEED TIME rather
 * than hardcoded, because both are environment data — see
 * PRODUCTION_LINE_ID for why selecting the line by label would be a silent
 * correctness bug.
 *
 * WINDOWS ARE UNIQUE PER RUN (same device as laporan-sterilizer.spec.ts and
 * kelola-periode-pelaporan.spec.ts): a period may not overlap another IN THE
 * SAME MILL, so every window is derived from RUN_OFFSET, slot-spaced, and
 * sits far in the future.
 *
 * PER MILL, NOT PER (MILL, STATION TYPE). Since 2026-09-26 the overlap rule
 * ignores the station type entirely: one date belongs to exactly one period
 * of a mill, full stop. FOUR SPECS SEED PERIODS IN "Business Unit A"
 * (laporan-boiler-room, laporan-cages-track, laporan-clarification,
 * laporan-storage-tank) and until that change their windows were free to
 * coincide because their station types differed. They are not any more, so
 * their RUN_OFFSET bands share one date line — see the note in
 * tests/support/periods.ts.
 * See RUN_OFFSET for why the stride between runs is
 * wider than one run's whole span: periods are cleaned up afterwards, the
 * RECORDS are not, and an older run's records falling inside a newer run's
 * window silently shifts every figure below.
 *
 * CLEANUP IS MANDATORY, not tidiness. Without the deletePeriodsByPrefix()
 * call in afterAll this suite poisons itself, and that is measured rather
 * than assumed (see e2e-web/tests/support/periods.ts): the period list is
 * paginated at 20 rows ordered by start_date DESC, this spec adds 9 rows per
 * run, and after roughly two runs the rows a test has just created are
 * pushed to page 2 — so createPeriod() fails on its own toBeVisible() check
 * BEFORE a single behavioural assertion has run.
 *
 * ------------------------------------------------------------------------
 * THE SEEDED NUMBERS ARE LOAD-BEARING. Do not "tidy" them.
 * ------------------------------------------------------------------------
 * PERIOD_MAIN — two records, tippler 06:00-18:00 on both days:
 *   day MAIN.start : cages_out 12, hours 06(3 lori), 07(2), 08(1)  -> 6 lori
 *   day MAIN.end   : cages_out  8, hours 06(2), 08(2), 14(2)       -> 6 lori
 *   => total tipped 12, total out 20, 2 hari ber-record, rata-rata 6 lori/hari
 *   => jam puncak 06.00 dengan 5 lori (3+2), NOT hour 08
 *   => jam menganggur 9 + 9 = 18 (window {6..17}, 3 jam terisi tiap hari)
 *   => jeda terpanjang 6 jam (14-8) pada MAIN.end — NOT 8 (06->14), because
 *      hour 08 sits between them
 *   => durasi tippler rata-rata 12 jam, 0 hari tanpa jendela sah
 *   => antrean: sisa = CAGE_COLUMNS - lori jam itu, jadi terendah
 *      CAGE_COLUMNS-3 dan rata-rata CAGE_COLUMNS-2 — NEVER their sum
 *   Header `Cages Tipped` is set to 999 on both records ON PURPOSE: it is
 *   the summary field the report must never read, so 999 appearing anywhere
 *   on the page is a regression, not a coincidence.
 * PERIOD_GRAIN — one record, cages_out 50 with EIGHT hourly rows (00..07,
 *   1 lori each). Total Lori Keluar must read 50, never 400.
 * PERIOD_ONE_HOUR — one record with exactly ONE tipping hour, so there is no
 *   gap to measure at all: the card must say so, not print "0 jam".
 * PERIOD_NIGHT — tippler NIGHT.start 22:00 -> NIGHT.start+1 04:00, tipping
 *   at 01 and 22. Duration 6 jam (never -18), operating hour set
 *   {22,23,0,1,2,3}, idle {23,0,2,3} = 4 jam.
 * PERIOD_NO_WINDOW — tippler start 22:00 and stop 04:00 ON THE SAME DAY
 *   (stop <= start). See the note on that period's builder: the form makes
 *   tippler_stop_time REQUIRED, so a genuinely NULL stop cannot be entered
 *   through the UI; stop <= start is the other input that reaches the
 *   SAME "window cannot be computed" branch, and it is the one this
 *   environment can actually produce.
 * PERIOD_CLOSED / PERIOD_EMPTY / PERIOD_WHOLE_MILL / PERIOD_OTHER_MILL —
 *   status caption, empty state, and the two period-picker membership cases.
 *
 * EXPORT IS A LIVEWIRE ACTION, not an <a href>: the button carries
 * wire:click="export('csv')", so the assertion waits for Playwright's
 * download event fired by Livewire's client-side download handler.
 */

import { test, expect, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { pruneLaneData } from './support/backend'
import { deletePeriodsByPrefix } from './support/periods'
import { closeStation, createOpenPeriodViaUi, openStationRow } from './support/period-screen'
import { laneIsoDate, laneOffset } from './support/period-lanes'
import { STATEFUL_REFERER } from './support/base-url'

const REPORT_PATH = '/reports/cages-track'
const PERIODS_PATH = '/master-data/periods'
const CAGES_TRACK_FORM_PATH = '/data/cages-track/create'

/** Business Unit A — the mill whose cages-track station has machinery. */
const BUSINESS_UNIT = 'Business Unit A'
const STATION_TYPE = 'Cages Track'
/**
 * The mill of the period this screen must NOT offer.
 *
 * WHY A MILL AND NOT A STATION TYPE ANY MORE. listPeriods() offers a period
 * when it belongs to the caller's mill AND has a `period_stations` row for
 * this screen's station type. Within one provisioned mill that second clause
 * can no longer be made false from the UI: creating a period registers a row
 * for EVERY station type the mill has, so there is no such thing as a period
 * of this mill that skips Cages Track. "Mill Kode Duplikat" has no active
 * station at all, so its period gets NO station row whatsoever — it fails
 * both clauses at once, and it is the only browser-reachable shape of "a
 * period that does not cover this station type". The station-row clause on
 * its own is asserted where it can be produced directly, in
 * backend/tests/Feature/Api/LaporanCagesTrackTest.php.
 */
const OTHER_MILL = 'Mill Kode Duplikat'

const SUPERVISOR = 'supervisor01'
const MILL_MANAGEMENT = 'millmanagement-a'
const ADMIN = 'admin'
/** Operator is a mobile-only actor — it has no web UI for this report. */
const OPERATOR = 'operator01'

/**
 * A per-run day offset, so two runs never collide.
 *
 * THE STRIDE IS LOAD-BEARING, not decoration. Periods are deleted in
 * afterAll, but the cages_track_records entered through screen-024 are NOT
 * (that screen exposes no delete, and neither does /api/cages-track-records).
 * They stay in the dev database forever. If one run's windows can fall
 * inside the next run's windows, the older run's records are silently
 * counted by the newer run's report and every figure below drifts — which is
 * exactly what happened on the first pass here: a 10-day slot spacing with a
 * 1-second offset granularity put run N's night-shift record on run N+1's
 * main day, turning 12 tipped cages into 17.
 *
 * So: the seconds counter is multiplied by 25, giving consecutive runs a
 * 25-day stride, while one run's whole fixture spans 20 days (+1 .. +20).
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
const RUN_OFFSET = laneOffset((Math.floor(Date.now() / 1000) % 100000) * 25, 'cages-track')

/** Name prefix every period of this spec carries — also the cleanup key. */
const PERIOD_PREFIX = 'CagesTrack '

function isoDate(dayOffset: number): string {
  return laneIsoDate(dayOffset)
}

/**
 * One period window, two days apart from its neighbours so this run's own
 * periods can never overlap. Every window must keep clear of every other one,
 * PERIOD_WHOLE_MILL included: the overlap rule is per mill and no longer looks
 * at the station type at all.
 *
 * Windows are one day wide except the main one, which needs a start date and
 * an end date far enough apart to prove the range is inclusive on both.
 */
function windowFor(slot: number, days = 1): { startDay: number; start: string; end: string } {
  const startDay = RUN_OFFSET + slot * 2

  return { startDay, start: isoDate(startDay), end: isoDate(startDay + days - 1) }
}

const MAIN = windowFor(1, 3)
const GRAIN = windowFor(3)
const ONE_HOUR = windowFor(4)
const NIGHT = windowFor(5)
const NO_WINDOW = windowFor(6)
const CLOSED = windowFor(7)
const EMPTY = windowFor(8)
const WHOLE_MILL = windowFor(9)
const OTHER_MILL_WINDOW = windowFor(10)

const PERIOD_MAIN = `${PERIOD_PREFIX}Lengkap ${RUN_OFFSET}`
const PERIOD_GRAIN = `${PERIOD_PREFIX}Grain ${RUN_OFFSET}`
const PERIOD_ONE_HOUR = `${PERIOD_PREFIX}Satu Jam ${RUN_OFFSET}`
const PERIOD_NIGHT = `${PERIOD_PREFIX}Lintas Malam ${RUN_OFFSET}`
const PERIOD_NO_WINDOW = `${PERIOD_PREFIX}Tanpa Jendela ${RUN_OFFSET}`
const PERIOD_CLOSED = `${PERIOD_PREFIX}Tertutup ${RUN_OFFSET}`
const PERIOD_EMPTY = `${PERIOD_PREFIX}Kosong ${RUN_OFFSET}`
const PERIOD_WHOLE_MILL = `${PERIOD_PREFIX}Seluruh Mill ${RUN_OFFSET}`
const PERIOD_OTHER_MILL = `${PERIOD_PREFIX}Mill Lain ${RUN_OFFSET}`

/** The date one day BEFORE the main window — must never be reported. */
const OUTSIDE_DATE = isoDate(MAIN.startDay - 1)

/**
 * Periode satu hari yang memuat OUTSIDE_DATE — ADA HANYA SUPAYA RECORD DI
 * LUAR JENDELA BISA DITANAM, sejak 2026-10-02.
 *
 * Kunci periode (usecase-141) adalah WHITELIST: tanggal yang tidak dimuat
 * satu pun periode terbuka ditolak 422 PERIOD_CLOSED. Record OUTSIDE_DATE
 * justru harus benar-benar ADA di database agar skenario "data di luar
 * jendela tidak boleh dilaporkan" menguji sesuatu — tanpa periode ini
 * record itu tidak pernah tersimpan, dan asersi toHaveCount(0) lolos karena
 * datanya tidak ada, bukan karena laporannya menyaring. Itu asersi yang
 * selalu hijau, jenis cacat termahal dalam suite ini.
 *
 * Satu hari, tepat di OUTSIDE_DATE, dan ia TIDAK pernah dipilih di pemilih
 * periode mana pun — jadi ia tidak mengubah satu pun angka yang diasersi.
 * Ia tetap berada di dalam lajur spec ini: LANE_MARGIN (25 hari) di
 * tests/support/period-lanes.ts ada justru untuk hari sebelum RUN_OFFSET.
 */
const PERIOD_OUTSIDE = `${PERIOD_PREFIX}Luar Jendela ${RUN_OFFSET}`

/**
 * Checkbox columns on the cages grid = machinery count on the line's
 * cages-track station. Read once in beforeAll from the form's own
 * `jumlah-cages-hint`; every queue assertion is derived from it rather than
 * from a hardcoded number.
 */
let CAGE_COLUMNS = 0

/**
 * The production line the fixtures are entered on, resolved BY ID in
 * beforeAll — never by label.
 *
 * Selecting by label would be a silent correctness bug here: the create
 * form's dropdown lists EVERY production line in the instance
 * (ProductionLine::orderBy('name'), unfiltered by mill), and this database
 * has four different mills each carrying a line called "Line 1". Picking
 * the first label match would file the record under whichever mill Postgres
 * happened to order first, while the period belongs to BUSINESS_UNIT — and
 * the report would then be correctly empty, which reads exactly like a
 * broken report.
 */
let PRODUCTION_LINE_ID = ''
let PRODUCTION_LINE_NAME = ''

/**
 * Referer + XSRF header pair that makes an APIRequestContext call count as
 * a stateful session request. Same device, and the same reasons, as
 * tests/support/periods.ts — Playwright sends no Referer of its own, so
 * without it Sanctum falls through to the token path and answers 401.
 * Kept local rather than exported from periods.ts so that shared helper
 * stays exactly as the specs that already depend on it left it.
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
 * Every production line of BUSINESS_UNIT, read as Admin through the API so
 * the ids are unambiguous. Returns them in the order the master data does.
 */
async function productionLinesOfBusinessUnit(page: Page): Promise<Array<{ id: string; name: string }>> {
  const headers = await statefulHeaders(page)

  const mills = await page.request.get('/api/cages-track-reports/business-units/options', { headers })
  expect(mills.ok(), 'admin could not read the mill list').toBe(true)

  const mill = ((await mills.json()).data as Array<{ id: string; name: string }>)
    .find((row) => row.name === BUSINESS_UNIT)

  expect(mill, `this environment has no mill named "${BUSINESS_UNIT}"`).toBeTruthy()

  const lines = await page.request.get(
    `/api/production-lines?per_page=100&business_unit_id=${mill!.id}`,
    { headers },
  )
  expect(lines.ok(), 'admin could not read the production line list').toBe(true)

  return (await lines.json()).data as Array<{ id: string; name: string }>
}

/**
 * Picks the first production line of BUSINESS_UNIT whose cages-track station
 * actually has machinery, and records how many checkbox columns that gives.
 * The count comes from the form's own `jumlah-cages-hint`, which prints N
 * directly, so no row has to be added just to measure it.
 */
async function resolveProductionLine(page: Page, lines: Array<{ id: string; name: string }>): Promise<void> {
  for (const line of lines) {
    await page.goto(CAGES_TRACK_FORM_PATH)
    await page.locator('[data-testid="production-line-select"]').selectOption(line.id)

    const hint = page.locator('[data-testid="jumlah-cages-hint"]')

    if (await hint.isVisible().catch(() => false)) {
      // fall through
    } else {
      // One Livewire round trip: either the hint appears, or the
      // "no machinery on this line" notice does.
      await page.waitForTimeout(700)
    }

    if (!(await hint.isVisible().catch(() => false))) {
      continue
    }

    const text = (await hint.textContent()) ?? ''
    const columns = Number(text.match(/N\s*=\s*(\d+)/)?.[1] ?? 0)

    if (columns >= 4) {
      PRODUCTION_LINE_ID = line.id
      PRODUCTION_LINE_NAME = line.name
      CAGE_COLUMNS = columns

      return
    }
  }

  throw new Error(
    `No production line of "${BUSINESS_UNIT}" has at least 4 machinery rows on its cages-track station, `
      + 'so no hourly tipping row can be entered and this report has nothing to report on.',
  )
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

interface TippingHour {
  /** 0..23. Rows MUST be listed in ascending hour order — the form's Time
   *  dropdown only offers hours above the highest already picked. */
  hour: number
  /** How many cage checkboxes to tick; also drives cages_remain. */
  cages: number
}

/**
 * One cages_track_records row plus one cages_tipped_times row per entry of
 * `hours`, entered through screen-024's create form.
 *
 * `tipplerStart` / `tipplerStop` are datetime-local values (YYYY-MM-DDTHH:mm).
 * The stop is REQUIRED by CagesTrackRecordService::validate(), which is why
 * the "no computable window" fixture passes a stop EARLIER than the start
 * rather than an empty one — there is no `after:` rule, so that input is
 * accepted and lands on exactly the same "window cannot be computed" branch
 * a legacy NULL row would.
 */
async function createCagesTrackRecord(
  page: Page,
  options: {
    number: string
    date: string
    tipplerStart: string
    tipplerStop: string
    cagesOut: number
    cagesTipped: number
    hours: TippingHour[]
  },
): Promise<void> {
  await page.goto(CAGES_TRACK_FORM_PATH)

  await page.locator('[data-testid="production-line-select"]').selectOption(PRODUCTION_LINE_ID)
  // The column count only resolves after the production line round trip,
  // and `+ Tambah Baris` stays disabled until it does.
  await expect(page.locator('[data-testid="jumlah-cages-hint"]')).toBeVisible()
  await expect(page.locator('[data-testid="add-row-button"]')).toBeEnabled()

  await page.locator('[data-testid="cages-track-number-input"]').fill(options.number)
  await page.locator('[data-testid="date-input"]').fill(options.date)
  await page.locator('[data-testid="tippler-start-time-input"]').fill(options.tipplerStart)
  await page.locator('[data-testid="tippler-stop-time-input"]').fill(options.tipplerStop)
  await page.locator('[data-testid="cages-out-input"]').fill(String(options.cagesOut))
  await page.locator('[data-testid="cages-tipped-input"]').fill(String(options.cagesTipped))

  for (let index = 0; index < options.hours.length; index++) {
    const row = options.hours[index]

    await page.locator('[data-testid="add-row-button"]').click()
    await expect(page.locator(`[data-testid="cages-tipped-time-row-${index}"]`)).toBeVisible()

    await page.locator(`[data-testid="detail-hour-select-${index}"]`).selectOption(String(row.hour))

    for (let cage = 1; cage <= row.cages; cage++) {
      await page.locator(`[data-testid="detail-cage-${index}-${cage}"]`).check()
      // Every checkbox is its own Livewire round trip (wire:click=
      // "toggleCage"); waiting on the computed total keeps one row's
      // requests from racing the next click.
      // <span> sejak 2026-10-04 (bukan lagi input nonaktif) — testid sama.
      await expect(page.locator(`[data-testid="detail-total-cages-${index}"]`)).toHaveText(String(cage))
    }
  }

  await page.locator('[data-testid="save-button"]').click()
  await page.waitForURL((url) => url.pathname.startsWith('/data/cages-track/') && !url.pathname.endsWith('/create'))
}

// ---------------------------------------------------------------------
// Report helpers
// ---------------------------------------------------------------------

async function openReport(page: Page, username: string): Promise<void> {
  await login(page, username, PASSWORD)
  await page.goto(REPORT_PATH)
  await expect(page.locator('[data-testid="laporan-cages-track"]')).toBeVisible()
}

/** Picks the period whose option label contains `name`, then waits for it. */
async function selectPeriod(page: Page, name: string): Promise<void> {
  const option = page.locator('[data-testid="period-select"] option', { hasText: name })
  await expect(option).toHaveCount(1)

  const value = await option.getAttribute('value')
  await page.locator('[data-testid="period-select"]').selectOption(value as string)

  await expect(page.locator('[data-testid="report-hero"]')).toContainText(name)
}

/** Opens the collapsible daily recap and waits for its table. */
async function openRecap(page: Page): Promise<void> {
  await page.locator('[data-testid="recap-toggle"]').click()
  await expect(page.locator('[data-testid="recap-table"]')).toBeVisible()
}

/**
 * Memilih Production Line pada layar laporan.
 *
 * WAJIB SEJAK 2026-09-28. Commit a5ccfba membuat laporan stasiun menolak
 * menampilkan angka apa pun sebelum satu Production Line dipilih secara sadar
 * — laporan menghasilkan angka gabungan per line, dan mencampur beberapa line
 * membuat angkanya menyesatkan. Spec ini terakhir disentuh sebelum tanggal itu,
 * sehingga seluruh test laporannya berhenti di empty state
 * `select-production-line-hint` dan tidak pernah sampai ke badan laporan.
 *
 * URUTANNYA MILL -> LINE -> PERIODE, dan itu bukan selera: komponen memanggil
 * keepProductionLineValid(), yang mengosongkan productionLineId begitu ia tidak
 * ada di daftar line mill yang sedang dipilih. Memilih line lebih dulu lalu
 * berganti mill akan membuang pilihan line itu tanpa suara.
 *
 * Pemilih periode TIDAK digated oleh line (keduanya duduk di gate mill yang
 * sama), jadi urutan line-lalu-periode di sini aman dan sekaligus mencerminkan
 * urutan yang dilihat pengguna.
 */
async function selectProductionLine(page: Page, lineId: string = PRODUCTION_LINE_ID): Promise<void> {
  await page.locator('[data-testid="production-line-select"]').selectOption(lineId)

  // Titik sinkronisasi positif: `production-line-current` hanya dirender saat
  // komponen benar-benar sudah me-resolve line-nya ($selectedProductionLine !==
  // null), jadi menunggunya membuktikan round trip Livewire-nya mendarat —
  // bukan sekadar bahwa <select> sudah berubah nilainya di DOM.
  await expect(page.locator('[data-testid="production-line-current"]')).toBeVisible()
  await expect(page.locator('[data-testid="select-production-line-hint"]')).toHaveCount(0)
}

test.describe('Laporan Cages & Tracks', () => {
  // Pembersihan — lihat tests/support/periods.ts untuk alasan lengkapnya.
  // Tanpa ini, 9 periode per run menumpuk sampai memenuhi halaman 1 daftar
  // yang dipaginasi 20 baris, lalu baris yang baru dibuat test terdorong ke
  // halaman 2 dan suite gagal di createPeriod() sebelum satu pun asersi
  // perilaku jalan.
  test.afterAll(async ({ browser }) => {
    // Record & periode lajur disapu LEBIH DULU: periode yang berisi record
    // ditolak 409 PERIOD_HAS_RECORDS oleh deletePeriodsByPrefix() di bawah.
    await pruneLaneData('laporan-cages-track')

    const page = await browser.newPage()

    try {
      await login(page, ADMIN, PASSWORD)
      const deleted = await deletePeriodsByPrefix(page, [PERIOD_PREFIX])
      console.log(`[cleanup] laporan-cages-track: %d periode dihapus`, deleted)
    } catch (error) {
      // Kegagalan membersihkan bukan kegagalan produk.
      console.warn('[cleanup] laporan-cages-track: pembersihan gagal:', error)
    } finally {
      await page.close()
    }
  })

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(900_000)

    // Sisa run sebelumnya yang terhenti sebelum afterAll-nya (record dan
    // periode di rentang lajur). Lihat tests/support/backend.ts.
    await pruneLaneData('laporan-cages-track (awal)')

    const page = await browser.newPage()

    try {
      // --- Periods (Admin, screen-128) ---------------------------------
      await login(page, ADMIN, PASSWORD)
      await page.goto(PERIODS_PATH)

      await createPeriod(page, { name: PERIOD_MAIN, start: MAIN.start, end: MAIN.end })
      // Periode satu hari untuk OUTSIDE_DATE — lihat PERIOD_OUTSIDE: tanpa
      // periode terbuka yang memuatnya, record di luar jendela ditolak oleh
      // kunci periode dan asersinya menjadi selalu-hijau.
      await createPeriod(page, { name: PERIOD_OUTSIDE, start: OUTSIDE_DATE, end: OUTSIDE_DATE })
      await createPeriod(page, { name: PERIOD_GRAIN, start: GRAIN.start, end: GRAIN.end })
      await createPeriod(page, { name: PERIOD_ONE_HOUR, start: ONE_HOUR.start, end: ONE_HOUR.end })
      await createPeriod(page, { name: PERIOD_NIGHT, start: NIGHT.start, end: NIGHT.end })
      await createPeriod(page, { name: PERIOD_NO_WINDOW, start: NO_WINDOW.start, end: NO_WINDOW.end })
      await createPeriod(page, { name: PERIOD_CLOSED, start: CLOSED.start, end: CLOSED.end })
      await createPeriod(page, { name: PERIOD_EMPTY, start: EMPTY.start, end: EMPTY.end })
      // Covers the whole mill, Cages Track included — so it MUST be offered by this
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

      // Resolve the production line BY ID while still Admin — four mills in
      // this database each have a line called "Line 1", and the create
      // form's dropdown lists all of them unfiltered.
      const lines = await productionLinesOfBusinessUnit(page)

      // --- Records (Supervisor, screen-024) ----------------------------
      await page.context().clearCookies()
      await login(page, SUPERVISOR, PASSWORD)

      // Read the grid's column count once — it is machinery data, not a
      // constant, and every queue assertion derives from it.
      await resolveProductionLine(page, lines)
      console.log(
        `[fixture] laporan-cages-track: lini "%s" (%s), %d kolom cage`,
        PRODUCTION_LINE_NAME,
        PRODUCTION_LINE_ID,
        CAGE_COLUMNS,
      )

      // PERIOD_MAIN, day 1 — 06:00-18:00, tipping 06/07/08.
      await createCagesTrackRecord(page, {
        number: `CT-RPT-${RUN_OFFSET}-A`,
        date: MAIN.start,
        tipplerStart: `${MAIN.start}T06:00`,
        tipplerStop: `${MAIN.start}T18:00`,
        cagesOut: 12,
        // DELIBERATELY WRONG header summary — never a source of any figure.
        cagesTipped: 999,
        hours: [
          { hour: 6, cages: 3 },
          { hour: 7, cages: 2 },
          { hour: 8, cages: 1 },
        ],
      })

      // PERIOD_MAIN, day 2 — exactly on end_date, with the 6-hour gap.
      await createCagesTrackRecord(page, {
        number: `CT-RPT-${RUN_OFFSET}-B`,
        date: MAIN.end,
        tipplerStart: `${MAIN.end}T06:00`,
        tipplerStop: `${MAIN.end}T18:00`,
        cagesOut: 8,
        cagesTipped: 999,
        hours: [
          { hour: 6, cages: 2 },
          { hour: 8, cages: 2 },
          { hour: 14, cages: 2 },
        ],
      })

      // One day BEFORE the main window — must never be reported.
      await createCagesTrackRecord(page, {
        number: `CT-RPT-${RUN_OFFSET}-OUT`,
        date: OUTSIDE_DATE,
        tipplerStart: `${OUTSIDE_DATE}T06:00`,
        tipplerStop: `${OUTSIDE_DATE}T18:00`,
        cagesOut: 99,
        cagesTipped: 99,
        hours: [{ hour: 6, cages: 1 }],
      })

      // PERIOD_GRAIN — cages_out 50 over EIGHT hourly rows.
      await createCagesTrackRecord(page, {
        number: `CT-RPT-${RUN_OFFSET}-GRAIN`,
        date: GRAIN.start,
        tipplerStart: `${GRAIN.start}T00:00`,
        tipplerStop: `${GRAIN.start}T08:00`,
        cagesOut: 50,
        cagesTipped: 8,
        hours: [0, 1, 2, 3, 4, 5, 6, 7].map((hour) => ({ hour, cages: 1 })),
      })

      // PERIOD_ONE_HOUR — a single tipping hour, so no gap exists at all.
      await createCagesTrackRecord(page, {
        number: `CT-RPT-${RUN_OFFSET}-ONEHOUR`,
        date: ONE_HOUR.start,
        tipplerStart: `${ONE_HOUR.start}T06:00`,
        tipplerStop: `${ONE_HOUR.start}T07:00`,
        cagesOut: 3,
        cagesTipped: 2,
        hours: [{ hour: 6, cages: 2 }],
      })

      // PERIOD_NIGHT — 22:00 -> 04:00 the NEXT day.
      await createCagesTrackRecord(page, {
        number: `CT-RPT-${RUN_OFFSET}-NIGHT`,
        date: NIGHT.start,
        tipplerStart: `${NIGHT.start}T22:00`,
        tipplerStop: `${isoDate(NIGHT.startDay + 1)}T04:00`,
        cagesOut: 5,
        cagesTipped: 5,
        // Ascending by insertion order, which is what the Time dropdown
        // enforces — hour 01 first, then hour 22.
        hours: [
          { hour: 1, cages: 2 },
          { hour: 22, cages: 3 },
        ],
      })

      // PERIOD_NO_WINDOW — stop EARLIER than start on the same day. The
      // form requires a stop, so this is the only input this environment
      // can produce that reaches the "window cannot be computed" branch.
      await createCagesTrackRecord(page, {
        number: `CT-RPT-${RUN_OFFSET}-NOWINDOW`,
        date: NO_WINDOW.start,
        tipplerStart: `${NO_WINDOW.start}T22:00`,
        tipplerStop: `${NO_WINDOW.start}T04:00`,
        cagesOut: 4,
        cagesTipped: 2,
        hours: [
          { hour: 22, cages: 1 },
          { hour: 23, cages: 1 },
        ],
      })

      // PERIOD_CLOSED — data first, then the period is closed, so the
      // "closed periods stay readable and exportable" scenario has figures.
      await createCagesTrackRecord(page, {
        number: `CT-RPT-${RUN_OFFSET}-CLOSED`,
        date: CLOSED.start,
        tipplerStart: `${CLOSED.start}T06:00`,
        tipplerStop: `${CLOSED.start}T18:00`,
        cagesOut: 4,
        cagesTipped: 4,
        hours: [
          { hour: 6, cages: 2 },
          { hour: 7, cages: 2 },
        ],
      })

      // --- Close the closed period (Admin again) -----------------------
      await page.context().clearCookies()
      await login(page, ADMIN, PASSWORD)
      await page.goto(PERIODS_PATH)
      // Closing is PER STATION now: only the Cages Track row of this period
      // is closed, and the report must stay fully readable and exportable.
      await closeStation(page, PERIOD_CLOSED, STATION_TYPE)
    } finally {
      await page.close()
    }
  })

  // =====================================================================
  // Scenario 1: "berhasil sebagai Supervisor atau Mill Management"
  // =====================================================================
  test('berhasil: keterangan mill, seluruh kartu angka, grafik, antrean, dan rekap harian yang dapat ditutup', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    // A bound role gets a caption, never a picker.
    await expect(page.locator('[data-testid="mill-current"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)

    await expect(page.locator('[data-testid="report-kpis"]')).toBeVisible()
    await expect(page.locator('[data-testid="kpi-total-tipped"]')).toContainText('12')
    await expect(page.locator('[data-testid="kpi-total-out"]')).toContainText('20')
    await expect(page.locator('[data-testid="kpi-avg-per-day"]')).toContainText('6')
    await expect(page.locator('[data-testid="kpi-peak-hour"]')).toContainText('06.00')
    await expect(page.locator('[data-testid="kpi-idle-hours"]')).toContainText('18')
    await expect(page.locator('[data-testid="kpi-longest-gap"]')).toContainText('6')
    await expect(page.locator('[data-testid="kpi-tippler-duration"]')).toContainText('12')
    await expect(page.locator('[data-testid="days-without-valid-window"]')).toContainText('0 hari')

    await expect(page.locator('[data-testid="hourly-distribution"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-trend"]')).toBeVisible()
    await expect(page.locator('[data-testid="queue-card"]')).toBeVisible()

    // The recap opens and closes again — a long period must stay readable.
    await openRecap(page)
    await expect(page.locator('[data-testid="recap-row-total"]')).toContainText('TOTAL PERIODE')
    await page.locator('[data-testid="recap-toggle"]').click()
    await expect(page.locator('[data-testid="recap-table"]')).toBeHidden()
    await page.locator('[data-testid="recap-toggle"]').click()
    await expect(page.locator('[data-testid="recap-table"]')).toBeVisible()
  })

  test('berhasil sebagai Mill Management: laporan yang sama, tetap tanpa pemilih Mill', async ({ page }) => {
    await openReport(page, MILL_MANAGEMENT)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-current"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="report-kpis"]')).toBeVisible()
    await expect(page.locator('[data-testid="kpi-total-tipped"]')).toContainText('12')
  })

  // =====================================================================
  // Scenario 2: "berhasil sebagai Admin"
  // =====================================================================
  test('admin memilih mill: pemilih Mill terlihat, lalu seluruh kartu dan grafik muncul', async ({ page }) => {
    await openReport(page, ADMIN)

    await expect(page.locator('[data-testid="mill-select"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-select"] option')).not.toHaveCount(1)

    await page.locator('[data-testid="mill-select"]').selectOption({ label: BUSINESS_UNIT })
    await expect(page.locator('[data-testid="mill-required-hint"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="period-select"]')).toBeVisible()

    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    await expect(page.locator('[data-testid="report-kpis"]')).toBeVisible()
    await expect(page.locator('[data-testid="kpi-total-tipped"]')).toContainText('12')
    await expect(page.locator('[data-testid="hourly-distribution"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-trend"]')).toBeVisible()
    await expect(page.locator('[data-testid="queue-card"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-recap"]')).toBeVisible()
  })

  // =====================================================================
  // Scenario 3: "ekspor rincian per jam ke CSV"
  // =====================================================================
  test('ekspor CSV: unduhan terpicu, berkas berekstensi .csv, dan rekap tetap tampil', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    const downloadPromise = page.waitForEvent('download')
    await page.locator('[data-testid="export-csv"]').click()
    const download = await downloadPromise

    expect(download.suggestedFilename()).toContain('laporan-cages-track')
    expect(download.suggestedFilename()).toMatch(/\.csv$/)

    // The page is unchanged by the export — it is a read, not a navigation.
    await expect(page.locator('[data-testid="report-kpis"]')).toBeVisible()
    await expect(page.locator('[data-testid="kpi-total-tipped"]')).toContainText('12')
  })

  // =====================================================================
  // Scenario 4: "Periode tanpa data"
  // =====================================================================
  test('periode tanpa data: angka utama nol, keterangan belum ada data, dan tidak ada grafik kosong', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_EMPTY)

    await expect(page.locator('[data-testid="empty-period"]')).toContainText('Belum ada data pada periode ini')
    await expect(page.locator('[data-testid="kpi-total-tipped"]')).toContainText('0')
    await expect(page.locator('[data-testid="kpi-total-out"]')).toContainText('0')
    // No empty bars are forced.
    await expect(page.locator('[data-testid="hourly-distribution"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="daily-trend"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="queue-card"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="recap-table"]')).toHaveCount(0)
  })

  // =====================================================================
  // Scenario 5: "Hari dengan record tetapi tanpa rincian per jam"
  //
  // NOT SEEDABLE THROUGH THE PRODUCT'S OWN SCREENS, and deliberately left
  // failing-loud rather than quietly rewritten into something weaker.
  // CagesTrackRecordService::validateDetails() refuses a save with zero
  // valid hourly rows ("Minimal satu baris Cages Tipped Time..."), a row
  // with no cage ticked is filtered out before that check, and
  // CagesTrackRecord::booted() blocks status=saved with no child row. So a
  // record WITHOUT any hourly row cannot be created from any screen this
  // app has — it can only arrive from a legacy import.
  //
  // The behaviour itself IS covered, at the two layers that can build that
  // row directly:
  //   - backend/tests/Unit/Services/CagesTrackReportServiceTest.php, cases
  //     42-43 (daily row of 0, still in the avg denominator);
  //   - backend/tests/Feature/Api/LaporanCagesTrackTest.php and
  //     backend/tests/Feature/Livewire/LaporanCagesTrackTest.php, the
  //     "hari tanpa rincian" scenarios.
  // =====================================================================
  test.skip('hari tanpa rincian: baris rekap bernilai 0 dan tetap menjadi pembagi rata-rata', async () => {
    // Intentionally unimplemented — see the comment block above. Making
    // this pass would require seeding a record with no hourly row, which no
    // screen in this application can produce.
  })

  // =====================================================================
  // Scenario 6: "Seluruh hari tanpa waktu berhenti tippler"
  // =====================================================================
  test('tanpa jendela operasi: durasi tippler berteks tidak tersedia dan jumlah hari yang dikecualikan terlihat', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_NO_WINDOW)

    const durationCard = page.locator('[data-testid="kpi-tippler-duration"]')

    await expect(durationCard).toContainText('Durasi operasi tidak tersedia')
    // "–", never "0 jam": zero would read as "the tippler never ran".
    await expect(durationCard).not.toContainText('0 jam/hari')
    await expect(page.locator('[data-testid="days-without-valid-window"]')).toContainText('1 hari')

    // And the recap writes "tidak tercatat" rather than a fabricated zero.
    await openRecap(page)
    await expect(page.locator(`[data-testid="recap-row-${NO_WINDOW.start}"]`)).toContainText('tidak tercatat')
  })

  // =====================================================================
  // Scenario 7: "Penumpahan hanya pada satu jam"
  // =====================================================================
  test('satu jam saja: kartu jeda menyatakan tidak dapat dihitung dan tidak menulis 0 jam', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_ONE_HOUR)

    const gapCard = page.locator('[data-testid="kpi-longest-gap"]')

    await expect(page.locator('[data-testid="insufficient-gap"]')).toContainText('Tidak ada jeda yang dapat dihitung')
    await expect(gapCard).not.toContainText('0 jam')
  })

  // =====================================================================
  // Scenario 8: "Operasi melewati tengah malam"
  // =====================================================================
  test('lintas tengah malam: jam operasi 6 jam tanpa tanda minus, dan jam menganggur mengikuti himpunan melingkar', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_NIGHT)

    // 6 jam, never -18 (which is what subtracting hour components gives).
    // The assertion targets the VALUE element, not the whole card: the
    // card's own label reads "Durasi Operasi Tippler Rata-rata", so a bare
    // "contains no hyphen" check over the card would never be meaningful.
    const durationValue = page.locator('[data-testid="kpi-tippler-duration"] .md-kpi__value')

    await expect(durationValue).toContainText('6')
    await expect(durationValue).not.toContainText('-')
    await expect(durationValue).not.toContainText('18')
    // Window {22,23,0,1,2,3}, tipped {1,22} -> idle {23,0,2,3} = 4.
    await expect(page.locator('[data-testid="kpi-idle-hours"]')).toContainText('4')

    for (const hour of [22, 23, 0, 1, 2, 3]) {
      await expect(page.locator(`[data-testid="hourly-col-${hour}"]`)).toHaveAttribute('data-within-window', '1')
    }

    for (const hour of [4, 5, 12, 21]) {
      await expect(page.locator(`[data-testid="hourly-col-${hour}"]`)).toHaveAttribute('data-within-window', '0')
    }

    await openRecap(page)
    const nightRow = page.locator(`[data-testid="recap-row-${NIGHT.start}"]`)
    await expect(nightRow).toContainText('6 jam')
    await expect(nightRow).not.toContainText('-18')
  })

  // =====================================================================
  // Scenario 9: "Admin belum memilih mill"
  // =====================================================================
  test('admin tanpa mill: arahan memilih mill terlihat dan tidak ada angka rekap', async ({ page }) => {
    await openReport(page, ADMIN)

    await expect(page.locator('[data-testid="mill-select"]')).toHaveValue('')
    await expect(page.locator('[data-testid="mill-required-hint"]')).toBeVisible()
    await expect(page.locator('[data-testid="report-kpis"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="period-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="hourly-distribution"]')).toHaveCount(0)
  })

  // =====================================================================
  // Scenario 10: "Akun terikat mill tetapi mill-nya kosong"
  //
  // NOT SEEDABLE THROUGH THE PRODUCT'S OWN SCREENS either.
  // UserService::validate() makes business_unit_id REQUIRED for every role
  // except Admin ("Business Unit wajib dipilih untuk role selain Admin."),
  // so a Supervisor / Mill Management account with a NULL mill cannot be
  // created from Kelola User & Role, nor through /api/users. Such a row can
  // only come from a broken import or a direct database edit.
  //
  // The behaviour IS covered where the row can be built directly:
  //   - the SPY case (7) in CagesTrackReportServiceTest proves the
  //     all-mills list is never even read;
  //   - the "akun tanpa mill" scenarios in the Api and Livewire tests
  //     assert the 422 / the contact-Admin notice with no mill picker.
  // =====================================================================
  test.skip('akun tanpa mill: pesan menghubungi Admin, tanpa pemilih Mill dan tanpa angka', async () => {
    // Intentionally unimplemented — see the comment block above.
  })

  // =====================================================================
  // Scenario 11: "Mill belum punya periode"
  // =====================================================================
  test('belum ada periode: pemilih periode kosong dengan arahan menghubungi Admin, tanpa angka', async ({ page }) => {
    await openReport(page, ADMIN)

    // Which mills have no Cages & Tracks period is environment data, so the
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
      // One Livewire round trip: the production-line picker reloads for the
      // mill just chosen.
      await page.waitForTimeout(500)

      // SATU LINE HARUS DIPILIH SEBELUM no-periods BISA MUNCUL. Empty state layar
      // ini adalah rantai if/elseif dan cabang `needsProductionLineSelection`
      // berada DI DEPAN cabang periode kosong, jadi mill yang dipilih tanpa line
      // selalu menampilkan `select-production-line-hint` dan perburuan ini akan
      // gagal pada SETIAP mill tanpa memandang periodenya. Mill tanpa line sama
      // sekali dilewati: ia juga tidak bisa mencapai cabang periode.
      const lineOptions = await page
        .locator('[data-testid="production-line-select"] option')
        .evaluateAll((nodes) =>
          nodes.map((node) => (node as HTMLOptionElement).value).filter((v) => v !== ''),
        )

      if (lineOptions.length === 0) {
        continue
      }

      await selectProductionLine(page, lineOptions[0])

      if (await page.locator('[data-testid="no-periods"]').isVisible()) {
        found = true
        break
      }
    }

    expect(found, 'no mill without a Cages & Tracks reporting period exists in this environment').toBe(true)

    await expect(page.locator('[data-testid="no-periods"]')).toContainText('Belum ada Periode Pelaporan')
    await expect(page.locator('[data-testid="report-kpis"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="hourly-distribution"]')).toHaveCount(0)
    // An empty mill is not an error page.
    await expect(page.locator('[data-testid="laporan-cages-track"]')).toBeVisible()
  })

  // =====================================================================
  // Scenario 12: "Mencoba melihat mill lain"
  // =====================================================================
  test('mill lain: query string business_unit_id diabaikan dan mill akun tetap yang tampil', async ({ page }) => {
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
    await page.goto(`${REPORT_PATH}?business_unit_id=${otherMillId}`)

    await expect(page.locator('[data-testid="laporan-cages-track"]')).toBeVisible()
    // The mill is never negotiable from the UI for a bound role — deliberately
    // answered with the caller's OWN data rather than a 403, which would
    // confirm the other mill exists.
    await expect(page.locator('[data-testid="mill-current"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="laporan-cages-track"]')).not.toContainText(otherMillName)

    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)
    await expect(page.locator('[data-testid="kpi-total-tipped"]')).toContainText('12')
  })

  // =====================================================================
  // Scenario 13: "Operator mencoba membuka layar web ini"
  // =====================================================================
  test('operator: akses laporan WEB ditolak dan tidak ada data yang terlihat', async ({ page }) => {
    await login(page, OPERATOR, PASSWORD)
    await page.goto(REPORT_PATH)

    // EnsureRole -> abort(403): the error page, never the report. Operator
    // has no web UI at all; its reporting path is screen-136 (mobile).
    await expect(page.locator('body')).toContainText(/403|Forbidden/i)
    await expect(page.locator('[data-testid="laporan-cages-track"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="report-kpis"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="recap-table"]')).toHaveCount(0)
  })

  // =====================================================================
  // Scenario 14: "Periode tertutup"
  // =====================================================================
  test('periode tertutup: status Tertutup, kartu tetap penuh, tombol ekspor tidak dinonaktifkan', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_CLOSED)

    await expect(page.locator('[data-testid="hero-status"]')).toHaveText(/Tertutup/)
    await expect(page.locator('[data-testid="report-kpis"]')).toBeVisible()
    await expect(page.locator('[data-testid="kpi-total-tipped"]')).toContainText('4')
    // The period lock governs writing data, not reading a report.
    await expect(page.locator('[data-testid="export-csv"]')).toBeEnabled()

    const downloadPromise = page.waitForEvent('download')
    await page.locator('[data-testid="export-csv"]').click()
    const download = await downloadPromise

    expect(download.suggestedFilename()).toContain('laporan-cages-track')
  })

  // =====================================================================
  // Scenario 15: "periode yang tidak mencakup Cages & Tracks tidak boleh muncul"
  // =====================================================================
  test('pemilih periode: hanya periode yang punya baris Cages Track, bukan periode tanpa baris itu', async ({ page, browser }) => {
    await openReport(page, SUPERVISOR)

    const options = page.locator('[data-testid="period-select"] option')

    await expect(options.filter({ hasText: PERIOD_MAIN })).toHaveCount(1)
    await expect(options.filter({ hasText: PERIOD_WHOLE_MILL })).toHaveCount(1)
    await expect(options.filter({ hasText: PERIOD_OTHER_MILL })).toHaveCount(0)

    // Newest first: this run's latest offered window is the whole-mill one,
    // so it leads the main period.
    const labels = await options.allTextContents()
    const wholeMillIndex = labels.findIndex((label) => label.includes(PERIOD_WHOLE_MILL))
    const mainIndex = labels.findIndex((label) => label.includes(PERIOD_MAIN))

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
      // "Terbuka", bukan "Draft", SEJAK 2026-10-02: createPeriod() di spec ini
      // membuka baris stasiunnya segera setelah membuat periodenya, karena
      // kunci periode (usecase-141) menolak setiap record yang tidak dimuat
      // periode TERBUKA. Yang diuji di sini tetap sama — bahwa periode itu
      // PUNYA baris `period_stations` untuk jenis stasiun layar ini, yaitu
      // yang dicocokkan whereHas() pemilih periode; openStationRow() di atas
      // sudah melempar bila barisnya tidak ada. Statusnya diasersi apa adanya
      // agar perubahan diam-diam pada helper itu tetap terbaca di sini.
      await expect(adminPage.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
    } finally {
      await adminContext.close()
    }
  })

  // =====================================================================
  // Scenario 16: "rentang periode harus inklusif"
  // =====================================================================
  test('rentang inklusif: baris tanggal awal dan akhir terlihat, record di luar rentang tidak', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)
    await openRecap(page)

    await expect(page.locator(`[data-testid="recap-row-${MAIN.start}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="recap-row-${MAIN.end}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="recap-row-${OUTSIDE_DATE}"]`)).toHaveCount(0)

    // The Total row is consistent with the two visible rows (6 + 6).
    await expect(page.locator('[data-testid="recap-row-total"]')).toContainText('12')
    // The day before the window carried 99 cages — none of it is here.
    await expect(page.locator('[data-testid="recap-table"]')).not.toContainText('99')
  })

  // =====================================================================
  // Scenario 17: "angka ringkasan record harian tidak boleh dipakai"
  // =====================================================================
  test('angka ringkasan: total ditumpahkan berasal dari rincian per jam, bukan dari ringkasan record', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)
    await openRecap(page)

    // 12 from the hourly rows. Both records carry a header summary of 999,
    // which must appear NOWHERE on the page.
    await expect(page.locator('[data-testid="kpi-total-tipped"]')).toContainText('12')
    await expect(page.locator('[data-testid="kpi-total-tipped"]')).toContainText(
      'Sumbernya baris rincian per jam, bukan angka ringkasan record harian',
    )
    await expect(page.locator('[data-testid="laporan-cages-track"]')).not.toContainText('999')
    await expect(page.locator(`[data-testid="recap-row-${MAIN.start}"]`)).toContainText('6')
  })

  // =====================================================================
  // Scenario 18: "lori keluar tidak boleh terkalikan jumlah baris rincian"
  // =====================================================================
  test('lori keluar: record ber-cages_out 50 dengan delapan baris rincian menampilkan 50, bukan 400', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_GRAIN)

    const outCard = page.locator('[data-testid="kpi-total-out"]')

    await expect(outCard).toContainText('50')
    // 400 is 50 multiplied by the eight hourly rows — the exact failure a
    // JOIN-then-SUM would produce.
    await expect(outCard).not.toContainText('400')
    await expect(page.locator('[data-testid="kpi-total-tipped"]')).toContainText('8')

    await openRecap(page)
    await expect(page.locator(`[data-testid="recap-row-${GRAIN.start}"]`)).toContainText('50')
    await expect(page.locator('[data-testid="recap-table"]')).not.toContainText('400')
  })

  // =====================================================================
  // Scenario 19: "jam tanpa penumpahan tidak boleh dihitung di luar jam operasi"
  // =====================================================================
  test('jam menganggur: jauh lebih kecil dari 24 dan grafik membedakan jam di dalam dan di luar jendela', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    // 9 idle hours on each of the two days, inside a 12-hour window.
    // Counting all 24 hours would give 42 instead.
    await expect(page.locator('[data-testid="kpi-idle-hours"]')).toContainText('18')
    await expect(page.locator('[data-testid="kpi-idle-hours"]')).toContainText(
      'Hanya dihitung di dalam jam operasi, bukan sepanjang 24 jam',
    )

    await expect(page.locator('[data-testid="hourly-distribution"]')).toBeVisible()

    // The 24 columns are always drawn, and each one says whether it was ever
    // a candidate for being idle.
    await expect(page.locator('[data-testid="hourly-distribution"] .md-trendchart__col')).toHaveCount(24)

    for (const hour of [6, 10, 17]) {
      await expect(page.locator(`[data-testid="hourly-col-${hour}"]`)).toHaveAttribute('data-within-window', '1')
    }

    for (const hour of [0, 5, 18, 23]) {
      await expect(page.locator(`[data-testid="hourly-col-${hour}"]`)).toHaveAttribute('data-within-window', '0')
    }
  })

  // =====================================================================
  // Scenario 20: "jeda terpanjang tidak boleh dihitung lintas hari"
  // =====================================================================
  test('jeda terpanjang: jeda di dalam satu tanggal beserta tanggalnya, bukan selisih lintas hari', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    const gapCard = page.locator('[data-testid="kpi-longest-gap"]')

    // 6 jam (14 - 08) inside MAIN.end. A cross-date measurement would report
    // the overnight pause instead, which is not an operational gap.
    await expect(gapCard).toContainText('6')
    await expect(gapCard).toContainText('Terjadi pada')
    await expect(gapCard).toContainText(
      'Diukur antar jam penumpahan dalam satu tanggal, tidak melintasi malam',
    )
    await expect(page.locator('[data-testid="insufficient-gap"]')).toHaveCount(0)

    await openRecap(page)
    // Each day carries its own intra-day gap, never a shared cross-day one.
    await expect(page.locator(`[data-testid="recap-row-${MAIN.end}"]`)).toContainText('6 jam')
  })

  // =====================================================================
  // Scenario 21: "antrean tersisa tidak boleh diakumulasi"
  // =====================================================================
  test('antrean tersisa: terendah dan rata-rata dalam rentang per jam yang wajar, bukan total akumulatif', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    // cages_remain = CAGE_COLUMNS - lori tipped in that hour, so across the
    // six hourly rows (3,2,1,2,2,2 lori) the lowest snapshot is
    // CAGE_COLUMNS-3 and the average CAGE_COLUMNS-2. Their SUM would be
    // 6*CAGE_COLUMNS-12, which must appear nowhere.
    const expectedMin = CAGE_COLUMNS - 3
    const expectedAvg = CAGE_COLUMNS - 2
    const forbiddenSum = 6 * CAGE_COLUMNS - 12

    await expect(page.locator('[data-testid="queue-card"]')).toBeVisible()
    await expect(page.locator('[data-testid="queue-min"]')).toContainText(String(expectedMin))
    await expect(page.locator('[data-testid="queue-avg"]')).toContainText(String(expectedAvg))
    await expect(page.locator('[data-testid="queue-card"]')).toContainText('Potret per jam')
    await expect(page.locator('[data-testid="queue-min"]')).not.toContainText(String(forbiddenSum))
    await expect(page.locator('[data-testid="queue-avg"]')).not.toContainText(String(forbiddenSum))
  })
})
