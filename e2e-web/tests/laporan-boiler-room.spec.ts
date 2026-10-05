/**
 * Laporan Boiler Room (Browser/Playwright) — screen-131--laporan-boiler-room-web /
 * usecase-131--laporan-boiler-room-web.
 *
 * One test per test_scenarios entry whose `browser_test` is non-empty (all
 * 21; one of them is documented below as not seedable through the product's
 * own screens). Route is /reports/boiler-room — the repo's report prefix is
 * English (/reports/management, /reports/stations, /reports/cages-track,
 * /reports/sterilizer); no route in this app uses /laporan.
 *
 * SELF-SUFFICIENT DATA, because this screen cannot create any: the report is
 * READ-ONLY by design, so there is no UI here to seed it with. The fixtures
 * are built once, in beforeAll, through the two screens that DO own that
 * data:
 *   - /master-data/periods      (screen-128, as Admin)      — the periods,
 *     including the closed one and the two the picker must decide between;
 *   - /data/boiler-room/create  (screen-118, as Supervisor) — the records and
 *     their time-slot rows, with the exact shape every assertion depends on.
 *
 * ------------------------------------------------------------------------
 * THE SEEDED NUMBERS ARE LOAD-BEARING. Do not "tidy" them.
 * ------------------------------------------------------------------------
 * PERIOD_MAIN — 3 days, TWO boiler units, 12 filled slots in total:
 *
 *   Record A  (unit BLR-<run>-A1, date MAIN.start, 10 rows, slots 07:00..16:00)
 *     Tekanan Uap  : 12, 28, 20, 20, 20, 20, 20, 20, 20, 20   (sum 200)
 *     Suhu Uap     : 260 on all ten rows
 *     pH Air       : 6, 7, 7, 8 on the FIRST FOUR rows only — the other six
 *                    are left EMPTY on purpose
 *     Blowdown     : Y, Y, Y, N, N, then five left unset
 *     Sootblowing  : Y, N, N, N, N, then five left unset
 *     Free text    : row 0 only — Laju Bahan Bakar "12 ton/jam",
 *                    Beban ID Fan "80%", Beban SA Fan "sedang"
 *   Record B  (unit BLR-<run>-A2, date MAIN.end, 2 rows, slots 07:00, 08:00)
 *     Tekanan Uap  : 19, 21      Suhu Uap: 265, 267
 *     no pH, no maintenance, no free text
 *
 *   => TEKANAN: 12 readings, avg (200 + 40) / 12 = 20,0 bar
 *      min 12,0 and max 28,0 come from the RAW SLOT readings — while BOTH
 *      daily recap rows read 20,0 (200/10 and 40/2). Those two figures
 *      CANNOT be reconciled, and that is the contract: the screen carries
 *      data-testid="raw-extremes-note" saying so. Scenario 18 asserts both
 *      at once.
 *   => pH: 4 readings, (6+7+7+8)/4 = 7,0. A shared denominator would give
 *      28/12 = 2,3 — a perfectly plausible-looking pH, which is the danger.
 *      The pH card's reading count must read 4 while the pressure card's
 *      reads 12.
 *   => TDS Air and Suhu Gas Buang are NEVER filled: those two cards must
 *      read "–" with a reading count of 0, NOT 0 — and the pressure card
 *      next to them must be unaffected. That is scenario 6.
 *   => BLOWDOWN: 3 dilakukan + 2 tidak dilakukan + 7 tidak tercatat = 12,
 *      the number of reading rows. The seven unset ones are Record A's five
 *      plus Record B's two. NULL never joins "tidak dilakukan": a 9 anywhere
 *      near that figure is the regression this seed exists to catch.
 *      SOOTBLOWING: 1 + 4 + 7 = 12.
 *   => COVERAGE: 2 unit x 3 hari x 24 slot = 144 expected, 12 filled.
 *   => PER UNIT: BLR-<run>-A1 with 10 readings, BLR-<run>-A2 with 2.
 *
 * PERIOD_SPARSE — a 10-day window carrying ONE record of 2 filled rows:
 *   1 unit x 10 hari x 24 slot = 240 expected against 2 filled. The coverage
 *   card must be legible ABOVE the figures, not a footnote.
 * PERIOD_EMPTY  — no record at all: every metric reads "–", never 0, and no
 *   empty chart is drawn.
 * PERIOD_CLOSED — one record of 2 rows, then the period is closed: a closed
 *   period stays fully readable AND fully exportable.
 * PERIOD_WHOLE_MILL and PERIOD_OTHER_MILL — the two period-picker membership
 *   cases; neither carries a record. Since 2026-09-26 a period covers its
 *   whole mill, so WHOLE_MILL is simply a period of THIS mill (it carries a
 *   Boiler Room row like every other one) and OTHER_MILL is a period of a
 *   mill with no active station at all, hence with no station row to match.
 * OUTSIDE_DATE — one record one day BEFORE MAIN.start, Tekanan Uap 99. It
 *   must appear nowhere: no recap row, no trend column, not in the CSV.
 *
 * TIME SLOTS MUST BE PICKED IN ASCENDING ORDER. FormBoilerRoom's
 * availableTimeSlotOptions() only offers slots whose index in
 * BoilerRoomRecordService::canonicalTimeSlots() is ABOVE the highest one
 * already picked, and that canonical order starts at 07:00 and wraps
 * (07:00, 08:00, ..., 23:00, 00:00, ..., 06:00). Rows are therefore always
 * filled front to back.
 *
 * WINDOWS ARE UNIQUE PER RUN (same device as laporan-cages-track.spec.ts): a
 * period may not overlap another IN THE SAME MILL, so every window is derived
 * from RUN_OFFSET, laid out end to end by a cursor, and sits far in the
 * future.
 *
 * PER MILL, NOT PER (MILL, STATION TYPE). Since 2026-09-26 the overlap rule
 * ignores the station type entirely: one date belongs to exactly one period
 * of a mill, full stop. FOUR SPECS SEED PERIODS IN "Business Unit A"
 * (laporan-boiler-room, laporan-cages-track, laporan-clarification,
 * laporan-storage-tank) and until that change their windows were free to
 * coincide because their station types differed. They are not any more, so
 * their RUN_OFFSET bands share one date line — see the note in
 * tests/support/periods.ts.
 * The stride between runs is wider than one run's whole span
 * because periods are cleaned up afterwards and the RECORDS are not: an
 * older run's records falling inside a newer run's window would silently
 * shift every figure above.
 *
 * CLEANUP IS MANDATORY, not tidiness. Without the deletePeriodsByPrefix()
 * call in afterAll this suite poisons itself, and that is measured rather
 * than assumed (see e2e-web/tests/support/periods.ts): the period list is
 * paginated at 20 rows ordered by start_date DESC, this spec adds 6 rows per
 * run, and after a few runs the rows a test has just created are pushed to
 * page 2 — so createPeriod() fails on its own toBeVisible() check BEFORE a
 * single behavioural assertion has run.
 *
 * NO THRESHOLD ASSERTIONS ANYWHERE. Boiler Room has no operational-target
 * master, so every scenario that touches an extreme value asserts the
 * ABSENCE of a warning colour, icon or badge.
 *
 * EXPORT IS A LIVEWIRE ACTION, not an <a href>: the button carries
 * wire:click="export('csv')", so the assertion waits for Playwright's
 * download event fired by Livewire's client-side download handler.
 */

import { readFile } from 'node:fs/promises'
import { test, expect, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { pruneLaneData } from './support/backend'
import { deletePeriodsByPrefix } from './support/periods'
import { closeStation, createOpenPeriodViaUi, openStationRow } from './support/period-screen'
import { laneIsoDate, laneOffset } from './support/period-lanes'
import { STATEFUL_REFERER } from './support/base-url'

const REPORT_PATH = '/reports/boiler-room'
const PERIODS_PATH = '/master-data/periods'
const BOILER_ROOM_FORM_PATH = '/data/boiler-room/create'

/** The mill these fixtures live in — the same one laporan-cages-track uses. */
const BUSINESS_UNIT = 'Business Unit A'
const STATION_TYPE = 'Boiler Room'
/**
 * The mill of the period this screen must NOT offer.
 *
 * WHY A MILL AND NOT A STATION TYPE ANY MORE. listPeriods() offers a period
 * when it belongs to the caller's mill AND has a `period_stations` row for
 * this screen's station type. Within one provisioned mill that second clause
 * can no longer be made false from the UI: creating a period registers a row
 * for EVERY station type the mill has, so there is no such thing as a period
 * of this mill that skips Boiler Room. "Mill Kode Duplikat" has no active
 * station at all, so its period gets NO station row whatsoever — it fails
 * both clauses at once, and it is the only browser-reachable shape of "a
 * period that does not cover this station type". The station-row clause on
 * its own is asserted where it can be produced directly, in
 * backend/tests/Feature/Api/LaporanBoilerRoomTest.php.
 */
const OTHER_MILL = 'Mill Kode Duplikat'

const SUPERVISOR = 'supervisor01'
const MILL_MANAGEMENT = 'millmanagement-a'
const ADMIN = 'admin'
/** Operator is a mobile-only actor — it has no web UI for this report, and
 *  unlike Cages & Tracks its API was never widened either (screen-137, the
 *  mobile Boiler Room report, does not exist). */
const OPERATOR = 'operator01'

/**
 * A per-run day offset, so two runs never collide.
 *
 * THE STRIDE IS LOAD-BEARING. Periods are deleted in afterAll, but the
 * boiler_room_records entered through screen-118 are NOT (that screen
 * exposes no delete). They stay in the dev database forever. If one run's
 * windows can fall inside the next run's windows, the older run's records
 * are silently counted by the newer run's report and every figure above
 * drifts.
 *
 * One run's whole fixture spans ~31 days (see the cursor below), so the
 * seconds counter is multiplied by 40 for a 40-day stride between runs. The
 * modulus keeps the resulting year inside four digits, which
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
const RUN_OFFSET = laneOffset((Math.floor(Date.now() / 1000) % 60000) * 40, 'boiler-room')

/** Name prefix every period of this spec carries — also the cleanup key. */
const PERIOD_PREFIX = 'BoilerRoom '

function isoDate(dayOffset: number): string {
  return laneIsoDate(dayOffset)
}

/**
 * Windows are laid out END TO END by a cursor with a two-day gap, rather
 * than at fixed slots: PERIOD_SPARSE is ten days wide and a fixed slot
 * spacing would have it overlap its neighbours. Every window of this spec
 * must keep clear of every other one, PERIOD_WHOLE_MILL included: the overlap
 * rule is per mill and no longer looks at the station type at all.
 */
let cursor = RUN_OFFSET

function nextWindow(days = 1): { startDay: number; start: string; end: string } {
  const startDay = cursor
  cursor += days + 2

  return { startDay, start: isoDate(startDay), end: isoDate(startDay + days - 1) }
}

const MAIN = nextWindow(3)
const SPARSE = nextWindow(10)
const EMPTY = nextWindow()
const CLOSED = nextWindow()
const WHOLE_MILL = nextWindow()
const OTHER_MILL_WINDOW = nextWindow()

const PERIOD_MAIN = `${PERIOD_PREFIX}Lengkap ${RUN_OFFSET}`
const PERIOD_SPARSE = `${PERIOD_PREFIX}Tipis ${RUN_OFFSET}`
const PERIOD_EMPTY = `${PERIOD_PREFIX}Kosong ${RUN_OFFSET}`
const PERIOD_CLOSED = `${PERIOD_PREFIX}Tertutup ${RUN_OFFSET}`
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

/** The two boiler units of PERIOD_MAIN, unique per run. */
const UNIT_ONE = `BLR-${RUN_OFFSET}-A1`
const UNIT_TWO = `BLR-${RUN_OFFSET}-A2`

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
 * without it Sanctum falls through to the token path and answers 401. Kept
 * local rather than exported from periods.ts so that shared helper stays
 * exactly as the specs already depending on it left it.
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
 * Resolves, as Admin, a production line of BUSINESS_UNIT whose BOILER ROOM
 * station is active — the only lines FormBoilerRoom will accept, since
 * BoilerRoomRecordService::create() looks the station up by
 * (production_line_id, type='boiler-room', is_active=true) and refuses
 * anything else.
 */
async function resolveProductionLine(page: Page): Promise<void> {
  const headers = await statefulHeaders(page)

  const mills = await page.request.get('/api/boiler-room-reports/business-units/options', { headers })
  expect(mills.ok(), 'admin could not read the mill list').toBe(true)

  const mill = ((await mills.json()).data as Array<{ id: string; name: string }>)
    .find((row) => row.name === BUSINESS_UNIT)

  expect(mill, `this environment has no mill named "${BUSINESS_UNIT}"`).toBeTruthy()

  const stations = await page.request.get(
    `/api/stations?per_page=100&business_unit_id=${mill!.id}`,
    { headers },
  )
  expect(stations.ok(), 'admin could not read the station list').toBe(true)

  const boilerRoom = ((await stations.json()).data as Array<{
    type: string
    is_active: boolean
    production_line_id: string
  }>).find((row) => row.type === 'boiler-room' && row.is_active && row.production_line_id)

  expect(
    boilerRoom,
    `no active boiler-room station on any production line of "${BUSINESS_UNIT}", so no record can be entered `
      + 'and this report would have nothing to report on',
  ).toBeTruthy()

  PRODUCTION_LINE_ID = boilerRoom!.production_line_id
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

interface SlotRow {
  /** One of the 24 canonical labels. Rows MUST be listed in ASCENDING
   *  canonical order (07:00 first, 06:00 last) — the form's Time-Slot
   *  dropdown only offers slots above the highest already picked. */
  slot: string
  pressure?: number
  temp?: number
  tds?: number
  ph?: number
  exhaust?: number
  /** '' | 'y' | 'n' — '' leaves the slot UNRECORDED, which is a third state
   *  and not the same thing as 'n'. */
  blowdown?: '' | 'y' | 'n'
  sootblowing?: '' | 'y' | 'n'
  fuelFeedRate?: string
  idFanLoad?: string
  saFanLoad?: string
}

/**
 * One boiler_room_records row plus one boiler_room_details row per entry of
 * `rows`, entered through screen-118's create form.
 */
async function createBoilerRoomRecord(
  page: Page,
  options: { boilerRoomId: string; date: string; note?: string; rows: SlotRow[] },
): Promise<void> {
  await page.goto(BOILER_ROOM_FORM_PATH)

  await page.locator('[data-testid="production-line-select"]').selectOption(PRODUCTION_LINE_ID)
  await page.locator('[data-testid="boiler-room-id-input"]').fill(options.boilerRoomId)
  await page.locator('[data-testid="date-input"]').fill(options.date)

  if (options.note) {
    await page.locator('[data-testid="note-input"]').fill(options.note)
  }

  for (let index = 0; index < options.rows.length; index++) {
    const row = options.rows[index]

    await page.locator('[data-testid="add-row-button"]').click()
    await expect(page.locator(`[data-testid="boiler-room-detail-row-${index}"]`)).toBeVisible()

    // wire:model.live — the slot choice is a Livewire round trip, and the
    // NEXT row's option list is computed from it, so wait for it to land.
    const slotSelect = page.locator(`[data-testid="time-slot-select-${index}"]`)
    await slotSelect.selectOption(row.slot)
    await expect(slotSelect).toHaveValue(row.slot)

    if (row.pressure !== undefined) {
      await page.locator(`[data-testid="steam-pressure-${index}"]`).fill(String(row.pressure))
    }

    if (row.temp !== undefined) {
      await page.locator(`[data-testid="steam-temp-${index}"]`).fill(String(row.temp))
    }

    if (row.tds !== undefined) {
      await page.locator(`[data-testid="water-tds-${index}"]`).fill(String(row.tds))
    }

    if (row.ph !== undefined) {
      await page.locator(`[data-testid="water-ph-${index}"]`).fill(String(row.ph))
    }

    if (row.exhaust !== undefined) {
      await page.locator(`[data-testid="exhaust-gas-temp-${index}"]`).fill(String(row.exhaust))
    }

    if (row.blowdown !== undefined) {
      await page.locator(`[data-testid="blowdown-executed-${index}"]`).selectOption(row.blowdown)
    }

    if (row.sootblowing !== undefined) {
      await page.locator(`[data-testid="sootblowing-executed-${index}"]`).selectOption(row.sootblowing)
    }

    if (row.fuelFeedRate !== undefined) {
      await page.locator(`[data-testid="fuel-feed-rate-${index}"]`).fill(row.fuelFeedRate)
    }

    if (row.idFanLoad !== undefined) {
      await page.locator(`[data-testid="id-fan-load-${index}"]`).fill(row.idFanLoad)
    }

    if (row.saFanLoad !== undefined) {
      await page.locator(`[data-testid="sa-fan-load-${index}"]`).fill(row.saFanLoad)
    }
  }

  await page.locator('[data-testid="save-button"]').click()
  await page.waitForURL(
    (url) => url.pathname.startsWith('/data/boiler-room/') && !url.pathname.endsWith('/create'),
  )
}

// ---------------------------------------------------------------------
// Report helpers
// ---------------------------------------------------------------------

async function openReport(page: Page, username: string): Promise<void> {
  await login(page, username, PASSWORD)
  await page.goto(REPORT_PATH)
  await expect(page.locator('[data-testid="laporan-boiler-room"]')).toBeVisible()
}

/** Picks the period whose option label contains `name`, then waits for it. */
async function selectPeriod(page: Page, name: string): Promise<void> {
  const option = page.locator('[data-testid="period-selector"] option', { hasText: name })
  await expect(option).toHaveCount(1)

  const value = await option.getAttribute('value')
  await page.locator('[data-testid="period-selector"]').selectOption(value as string)

  await expect(page.locator('[data-testid="report-hero"]')).toContainText(name)
}

/**
 * Memilih Production Line pada layar laporan.
 *
 * WAJIB SEJAK 2026-09-28. Commit a5ccfba membuat laporan stasiun menolak
 * menampilkan angka apa pun sebelum satu Production Line dipilih secara sadar
 * — laporan menghasilkan angka gabungan per line, dan mencampur beberapa line
 * membuat angkanya menyesatkan. Spec ini terakhir disentuh 2026-09-27, sehari
 * SEBELUM aturan itu mendarat, sehingga seluruh test laporannya berhenti di
 * empty state `select-production-line-hint` dan tidak pernah sampai ke badan
 * laporan. Itu sebabnya 18 dari 22 test di berkas ini merah selama tiga hari
 * dengan sebab yang sama.
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

/** Closes the daily recap if it is open, opening it again is the same click. */
async function toggleRecap(page: Page): Promise<void> {
  await page.locator('[data-testid="daily-recap-toggle"]').click()
}

/**
 * Clicks Ekspor CSV, waits for the Livewire-fired download, and returns the
 * file's contents. The button carries wire:click="export('csv')" rather than
 * an href, so the download event is the only thing to wait on.
 */
async function downloadCsv(page: Page): Promise<string> {
  const downloadPromise = page.waitForEvent('download')
  await page.locator('[data-testid="export-button"]').click()
  const download = await downloadPromise

  expect(download.suggestedFilename()).toContain('laporan-boiler-room')
  expect(download.suggestedFilename()).toMatch(/\.csv$/)

  const path = await download.path()
  expect(path, 'the CSV download produced no local file').toBeTruthy()

  return readFile(path as string, 'utf8')
}

test.describe('Laporan Boiler Room', () => {
  // Pembersihan — lihat tests/support/periods.ts untuk alasan lengkapnya.
  // Tanpa ini, periode menumpuk sampai memenuhi halaman 1 daftar yang
  // dipaginasi 20 baris, lalu baris yang baru dibuat test terdorong ke
  // halaman 2 dan suite gagal di createPeriod() sebelum satu pun asersi
  // perilaku jalan.
  test.afterAll(async ({ browser }) => {
    // Record & periode lajur disapu LEBIH DULU: periode yang berisi record
    // ditolak 409 PERIOD_HAS_RECORDS oleh deletePeriodsByPrefix() di bawah.
    await pruneLaneData('laporan-boiler-room')

    const page = await browser.newPage()

    try {
      await login(page, ADMIN, PASSWORD)
      const deleted = await deletePeriodsByPrefix(page, [PERIOD_PREFIX])
      console.log('[cleanup] laporan-boiler-room: %d periode dihapus', deleted)
    } catch (error) {
      // Kegagalan membersihkan bukan kegagalan produk.
      console.warn('[cleanup] laporan-boiler-room: pembersihan gagal:', error)
    } finally {
      await page.close()
    }
  })

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(900_000)

    // Sisa run sebelumnya yang terhenti sebelum afterAll-nya (record dan
    // periode di rentang lajur). Lihat tests/support/backend.ts.
    await pruneLaneData('laporan-boiler-room (awal)')

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
      await createPeriod(page, { name: PERIOD_SPARSE, start: SPARSE.start, end: SPARSE.end })
      await createPeriod(page, { name: PERIOD_EMPTY, start: EMPTY.start, end: EMPTY.end })
      await createPeriod(page, { name: PERIOD_CLOSED, start: CLOSED.start, end: CLOSED.end })
      // Covers the whole mill, Boiler Room included — so it MUST be offered by this
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
      console.log('[fixture] laporan-boiler-room: production line %s', PRODUCTION_LINE_ID)

      // --- Records (Supervisor, screen-118) ----------------------------
      await page.context().clearCookies()
      await login(page, SUPERVISOR, PASSWORD)

      // PERIOD_MAIN, Record A — ten slots on the FIRST day of the window.
      // See the file header: every number here is load-bearing.
      await createBoilerRoomRecord(page, {
        boilerRoomId: UNIT_ONE,
        date: MAIN.start,
        note: 'Rekam utama',
        rows: [
          { slot: '07:00', pressure: 12, temp: 260, ph: 6, blowdown: 'y', sootblowing: 'y', fuelFeedRate: '12 ton/jam', idFanLoad: '80%', saFanLoad: 'sedang' },
          { slot: '08:00', pressure: 28, temp: 260, ph: 7, blowdown: 'y', sootblowing: 'n' },
          { slot: '09:00', pressure: 20, temp: 260, ph: 7, blowdown: 'y', sootblowing: 'n' },
          { slot: '10:00', pressure: 20, temp: 260, ph: 8, blowdown: 'n', sootblowing: 'n' },
          { slot: '11:00', pressure: 20, temp: 260, blowdown: 'n', sootblowing: 'n' },
          // The five rows below leave BOTH maintenance columns unset: that
          // is "tidak tercatat", a third state, never "tidak dilakukan".
          { slot: '12:00', pressure: 20, temp: 260 },
          { slot: '13:00', pressure: 20, temp: 260 },
          { slot: '14:00', pressure: 20, temp: 260 },
          { slot: '15:00', pressure: 20, temp: 260 },
          { slot: '16:00', pressure: 20, temp: 260 },
        ],
      })

      // PERIOD_MAIN, Record B — a SECOND boiler unit, exactly on end_date.
      await createBoilerRoomRecord(page, {
        boilerRoomId: UNIT_TWO,
        date: MAIN.end,
        rows: [
          { slot: '07:00', pressure: 19, temp: 265 },
          { slot: '08:00', pressure: 21, temp: 267 },
        ],
      })

      // One day BEFORE the main window — must never be reported.
      await createBoilerRoomRecord(page, {
        boilerRoomId: `BLR-${RUN_OFFSET}-OUT`,
        date: OUTSIDE_DATE,
        rows: [{ slot: '07:00', pressure: 99, temp: 299 }],
      })

      // PERIOD_SPARSE — two filled slots inside a ten-day window.
      await createBoilerRoomRecord(page, {
        boilerRoomId: `BLR-${RUN_OFFSET}-SPARSE`,
        date: SPARSE.start,
        rows: [
          { slot: '07:00', pressure: 21, temp: 250 },
          { slot: '08:00', pressure: 21, temp: 250 },
        ],
      })

      // PERIOD_CLOSED — data first, then the period is closed, so the
      // "closed periods stay readable and exportable" scenario has figures.
      await createBoilerRoomRecord(page, {
        boilerRoomId: `BLR-${RUN_OFFSET}-CLOSED`,
        date: CLOSED.start,
        rows: [
          { slot: '07:00', pressure: 20, temp: 260, ph: 7, exhaust: 200, tds: 2000 },
          { slot: '08:00', pressure: 20, temp: 260, ph: 7, exhaust: 200, tds: 2000 },
        ],
      })

      // --- Close the closed period (Admin again) -----------------------
      await page.context().clearCookies()
      await login(page, ADMIN, PASSWORD)
      await page.goto(PERIODS_PATH)
      // Closing is PER STATION now: only the Boiler Room row of this period
      // is closed, and the report must stay fully readable and exportable.
      await closeStation(page, PERIOD_CLOSED, STATION_TYPE)
    } finally {
      await page.close()
    }
  })

  // =====================================================================
  // Scenario 1: "berhasil sebagai Supervisor atau Mill Management"
  // =====================================================================
  test('berhasil: tanpa pemilih mill, seluruh bagian laporan terlihat, dan unduhan CSV diterima', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    // A bound role gets a caption, never a picker.
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="mill-selector"]')).toHaveCount(0)

    await expect(page.locator('[data-testid="recording-coverage"]')).toBeVisible()
    await expect(page.locator('[data-testid="coverage-filled-slots"]')).toHaveText('12')
    await expect(page.locator('[data-testid="coverage-expected-slots"]')).toHaveText('144')

    await expect(page.locator('[data-testid="metric-steam-pressure-avg"]')).toHaveText('20,0')
    await expect(page.locator('[data-testid="metric-steam-pressure-reading-count"]')).toHaveText('12')
    await expect(page.locator('[data-testid="metric-water-ph-avg"]')).toHaveText('7,0')
    await expect(page.locator('[data-testid="metric-water-ph-reading-count"]')).toHaveText('4')

    await expect(page.locator('[data-testid="maintenance-blowdown-executed"]')).toHaveText('3')
    await expect(page.locator('[data-testid="maintenance-sootblowing-executed"]')).toHaveText('1')

    await expect(page.locator('[data-testid="daily-trend-steam-pressure"]')).toBeVisible()
    await expect(page.locator('[data-testid="per-unit-recap"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-recap"]')).toBeVisible()
    await expect(page.locator('[data-testid="raw-extremes-note"]')).toBeVisible()

    // Export — a Livewire download, not a navigation.
    const csv = await downloadCsv(page)
    expect(csv).toContain('Slot Waktu')

    // The figures do not move after a reload: this is a read, and reading it
    // twice must give the same answer.
    await page.reload()
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)
    await expect(page.locator('[data-testid="metric-steam-pressure-avg"]')).toHaveText('20,0')
    await expect(page.locator('[data-testid="metric-steam-pressure-reading-count"]')).toHaveText('12')
  })

  test('berhasil sebagai Mill Management: laporan yang sama, tetap tanpa pemilih Mill', async ({ page }) => {
    await openReport(page, MILL_MANAGEMENT)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    await expect(page.locator('[data-testid="mill-selector"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="metric-steam-pressure-avg"]')).toHaveText('20,0')
    await expect(page.locator('[data-testid="per-unit-recap"]')).toBeVisible()
  })

  // =====================================================================
  // Scenario 2: "berhasil sebagai Admin"
  // =====================================================================
  test('admin memilih mill: pemilih Mill terlihat, lalu seluruh bagian laporan muncul dan Ekspor aktif', async ({ page }) => {
    await openReport(page, ADMIN)

    await expect(page.locator('[data-testid="mill-selector"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-selector"] option')).not.toHaveCount(1)

    await page.locator('[data-testid="mill-selector"]').selectOption({ label: BUSINESS_UNIT })
    await expect(page.locator('[data-testid="select-mill-first-hint"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="period-selector"]')).toBeVisible()

    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    await expect(page.locator('[data-testid="recording-coverage"]')).toBeVisible()
    await expect(page.locator('[data-testid="metric-steam-pressure-avg"]')).toHaveText('20,0')
    await expect(page.locator('[data-testid="daily-trend-steam-pressure"]')).toBeVisible()
    await expect(page.locator('[data-testid="per-unit-recap"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-recap"]')).toBeVisible()
    await expect(page.locator('[data-testid="export-button"]')).toBeEnabled()
  })

  // =====================================================================
  // Scenario 3: "Admin memilih mill lebih dulu"
  // =====================================================================
  test('admin tanpa mill: arahan memilih mill terlihat dan tidak ada satu pun angka laporan', async ({ page }) => {
    await openReport(page, ADMIN)

    await expect(page.locator('[data-testid="mill-selector"]')).toHaveValue('')
    await expect(page.locator('[data-testid="select-mill-first-hint"]')).toBeVisible()
    await expect(page.locator('[data-testid="recording-coverage"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="metric-steam-pressure-avg"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="daily-recap"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="period-selector"]')).toHaveCount(0)
  })

  // =====================================================================
  // Scenario 4: "mill belum punya periode"
  // =====================================================================
  test('belum ada periode: pemilih periode kosong dengan arahan menghubungi Admin, tanpa angka', async ({ page }) => {
    await openReport(page, ADMIN)

    // Which mills have no Boiler Room period is environment data, so the
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
      // One Livewire round trip: the production-line picker reloads for the
      // mill just chosen.
      await page.waitForTimeout(500)

      // SATU LINE HARUS DIPILIH SEBELUM no-period-hint BISA MUNCUL. Empty state
      // layar ini adalah rantai if/elseif dan cabang `needsProductionLineSelection`
      // berada DI DEPAN cabang `periods === []`, jadi mill yang dipilih tanpa line
      // selalu menampilkan `select-production-line-hint` dan perburuan di bawah
      // akan gagal pada SETIAP mill tanpa memandang periodenya. Mill tanpa line
      // sama sekali dilewati: ia juga tidak bisa mencapai cabang periode.
      const lineOptions = await page
        .locator('[data-testid="production-line-select"] option')
        .evaluateAll((nodes) =>
          nodes.map((node) => (node as HTMLOptionElement).value).filter((v) => v !== ''),
        )

      if (lineOptions.length === 0) {
        continue
      }

      await selectProductionLine(page, lineOptions[0])

      if (await page.locator('[data-testid="no-period-hint"]').isVisible()) {
        found = true
        break
      }
    }

    expect(found, 'no mill without a Boiler Room reporting period exists in this environment').toBe(true)

    await expect(page.locator('[data-testid="no-period-hint"]')).toContainText('Belum ada Periode Pelaporan')
    await expect(page.locator('[data-testid="period-selector"] option')).toHaveCount(0)
    await expect(page.locator('[data-testid="recording-coverage"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="metric-steam-pressure-avg"]')).toHaveCount(0)
    // An empty mill is not an error page.
    await expect(page.locator('[data-testid="laporan-boiler-room"]')).toBeVisible()
  })

  // =====================================================================
  // Scenario 5: "periode tanpa data"
  // =====================================================================
  test('periode tanpa data: keterangan belum ada data, metrik bertanda pisah bukan nol, tanpa grafik kosong', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_EMPTY)

    await expect(page.locator('[data-testid="empty-period-notice"]')).toContainText('Belum ada data pada periode ini')

    // '–', never '0': zero would read as "measured, and it was zero".
    for (const card of ['steam-pressure', 'steam-temp', 'water-tds', 'water-ph', 'exhaust-gas-temp']) {
      await expect(page.locator(`[data-testid="metric-${card}-avg"]`)).toHaveText('–')
      await expect(page.locator(`[data-testid="metric-${card}-reading-count"]`)).toHaveText('0')
    }

    // No empty chart is drawn — a flat line would read as a measurement.
    await expect(page.locator('[data-testid="daily-trend-steam-pressure"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="daily-trend-steam-temp"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="per-unit-recap"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="daily-recap"]')).toHaveCount(0)
  })

  // =====================================================================
  // Scenario 6: "sebuah metrik tidak pernah diisi"
  // =====================================================================
  test('metrik kosong: kartu TDS terbaca tidak tersedia dengan 0 pembacaan, kartu tekanan tetap berangka', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    // TDS Air and Suhu Gas Buang are never filled in PERIOD_MAIN.
    await expect(page.locator('[data-testid="metric-water-tds-avg"]')).toHaveText('–')
    await expect(page.locator('[data-testid="metric-water-tds-reading-count"]')).toHaveText('0')
    await expect(page.locator('[data-testid="metric-exhaust-gas-temp-avg"]')).toHaveText('–')
    await expect(page.locator('[data-testid="metric-exhaust-gas-temp-reading-count"]')).toHaveText('0')

    // Independent per metric — the empty ones do not touch the filled ones.
    await expect(page.locator('[data-testid="metric-steam-pressure-avg"]')).toHaveText('20,0')
    await expect(page.locator('[data-testid="metric-steam-pressure-reading-count"]')).toHaveText('12')
    await expect(page.locator('[data-testid="metric-water-ph-reading-count"]')).toHaveText('4')
  })

  // =====================================================================
  // Scenario 7: "perawatan tidak tercatat"
  // =====================================================================
  test('perawatan: tiga keadaan terlihat sebagai angka terpisah dan tidak tercatat jelas beda dari tidak dilakukan', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    // 3 + 2 + 7 = 12 reading rows. NULL never joins "tidak dilakukan" — a 9
    // here would be exactly that regression.
    await expect(page.locator('[data-testid="maintenance-blowdown-executed"]')).toHaveText('3')
    await expect(page.locator('[data-testid="maintenance-blowdown-not-executed"]')).toHaveText('2')
    await expect(page.locator('[data-testid="maintenance-blowdown-not-recorded"]')).toHaveText('7')

    await expect(page.locator('[data-testid="maintenance-sootblowing-executed"]')).toHaveText('1')
    await expect(page.locator('[data-testid="maintenance-sootblowing-not-executed"]')).toHaveText('4')
    await expect(page.locator('[data-testid="maintenance-sootblowing-not-recorded"]')).toHaveText('7')

    // Each of the three carries its own label, so the reader cannot mistake
    // one for another.
    await expect(page.locator('[data-testid="maintenance-blowdown"]')).toContainText('tidak tercatat')
    await expect(page.locator('[data-testid="maintenance-blowdown"]')).toContainText('tidak dilakukan')
  })

  // =====================================================================
  // Scenario 8: "pencatatan sangat tidak lengkap"
  // =====================================================================
  test('kelengkapan rendah: kartu kelengkapan menonjol di atas seluruh angka, yang tetap tampil dengan jumlah pembacaan kecil', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_SPARSE)

    const coverage = page.locator('[data-testid="recording-coverage"]')

    await expect(coverage).toBeVisible()
    await expect(page.locator('[data-testid="coverage-filled-slots"]')).toHaveText('2')
    // 1 unit x 10 hari x 24 slot.
    await expect(page.locator('[data-testid="coverage-expected-slots"]')).toHaveText('240')

    // ABOVE every other figure, not a footnote — asserted by geometry, so a
    // move into a footer would fail here rather than pass silently.
    const coverageBox = await coverage.boundingBox()
    const metricsBox = await page.locator('[data-testid="report-metrics"]').boundingBox()
    const recapBox = await page.locator('[data-testid="per-unit-recap"]').boundingBox()

    expect(coverageBox!.y).toBeLessThan(metricsBox!.y)
    expect(coverageBox!.y).toBeLessThan(recapBox!.y)

    // The figures are still shown, with their small counts beside them.
    await expect(page.locator('[data-testid="metric-steam-pressure-avg"]')).toHaveText('21,0')
    await expect(page.locator('[data-testid="metric-steam-pressure-reading-count"]')).toHaveText('2')
  })

  // =====================================================================
  // Scenario 9: "mill punya beberapa unit boiler"
  //
  // The two-unit half is seeded and asserted in full. The third unit of the
  // spec — one with a record but ZERO filled readings — is NOT SEEDABLE
  // THROUGH THE PRODUCT'S OWN SCREENS: BoilerRoomRecordService::
  // validateDetails() refuses a save with no valid row ("Minimal satu baris
  // Boiler Room Detail (Time-Slot terpilih + minimal 1 kolom bacaan terisi)
  // harus diisi."), so such a record can only arrive from a legacy import.
  // That behaviour IS covered where the row can be built directly:
  //   - backend/tests/Unit/Services/BoilerRoomReportServiceTest.php, case 23;
  //   - the "beberapa unit" scenarios in the Api and Livewire tests, which
  //     both assert the BLR-3 row with reading_count 0 and null averages.
  // Left documented rather than quietly dropped.
  // =====================================================================
  test('beberapa unit: rekap per unit memuat setiap unit sementara angka periode menggabungkan keduanya', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    await expect(page.locator('[data-testid="per-unit-recap"]')).toBeVisible()

    const unitOne = page.locator(`[data-testid="per-unit-row-${UNIT_ONE}"]`)
    const unitTwo = page.locator(`[data-testid="per-unit-row-${UNIT_TWO}"]`)

    await expect(unitOne).toBeVisible()
    await expect(unitTwo).toBeVisible()
    // Ten readings on the first unit, two on the second.
    await expect(unitOne).toContainText('10')
    await expect(unitTwo).toContainText('2')

    // The period figures at the top are the two units COMBINED: 240 / 12.
    await expect(page.locator('[data-testid="metric-steam-pressure-avg"]')).toHaveText('20,0')
    await expect(page.locator('[data-testid="metric-steam-pressure-reading-count"]')).toHaveText('12')
    await expect(page.locator('[data-testid="per-unit-row-total"]')).toContainText('12')
  })

  // =====================================================================
  // Scenario 10: "akun belum terhubung ke mill"
  //
  // NOT SEEDABLE THROUGH THE PRODUCT'S OWN SCREENS. UserService::validate()
  // makes business_unit_id REQUIRED for every role except Admin
  // ("Business Unit wajib dipilih untuk role selain Admin."), so a
  // Supervisor / Mill Management account with a NULL mill cannot be created
  // from Kelola User & Role, nor through /api/users. Such a row can only
  // come from a broken import or a direct database edit.
  //
  // The behaviour IS covered where the row can be built directly:
  //   - the SPY case (34) in BoilerRoomReportServiceTest proves the
  //     all-mills list is never even read;
  //   - the "akun tanpa mill" scenarios in the Api and Livewire tests assert
  //     the 422 / the contact-Admin notice with no mill picker at all.
  // =====================================================================
  test.skip('akun tanpa mill: pesan menghubungi Admin, tanpa dropdown daftar mill dan tanpa angka', async () => {
    // Intentionally unimplemented — see the comment block above.
  })

  // =====================================================================
  // Scenario 11: "mencoba melihat mill lain"
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

    // First request — another mill named in the query string. Deliberately
    // answered with the caller's OWN data rather than a 403, which would
    // confirm the other mill exists.
    await page.goto(`${REPORT_PATH}?business_unit_id=${otherMillId}`)

    await expect(page.locator('[data-testid="laporan-boiler-room"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="mill-selector"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="laporan-boiler-room"]')).not.toContainText(otherMillName)

    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)
    await expect(page.locator('[data-testid="metric-steam-pressure-avg"]')).toHaveText('20,0')

    // Second request — another mill's PERIOD named in the query string. The
    // period is not in this mill's list, so it is replaced before it can be
    // read, and no other mill's figure is ever rendered.
    await page.goto(`${REPORT_PATH}?period_id=00000000-0000-0000-0000-000000000000`)

    await expect(page.locator('[data-testid="laporan-boiler-room"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="laporan-boiler-room"]')).not.toContainText(otherMillName)
  })

  // =====================================================================
  // Scenario 12: "Operator mencoba membuka layar web ini"
  // =====================================================================
  test('operator: akses laporan WEB ditolak dan tidak ada angka Boiler Room yang terlihat', async ({ page }) => {
    await login(page, OPERATOR, PASSWORD)
    await page.goto(REPORT_PATH)

    // EnsureRole -> abort(403): the error page, never the report. Unlike
    // Cages & Tracks there is no mobile counterpart to widen for —
    // screen-137 does not exist.
    await expect(page.locator('body')).toContainText(/403|Forbidden/i)
    await expect(page.locator('[data-testid="laporan-boiler-room"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="recording-coverage"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="per-unit-recap"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="daily-recap"]')).toHaveCount(0)
  })

  // =====================================================================
  // Scenario 13: "periode tertutup"
  // =====================================================================
  test('periode tertutup: status Tertutup, laporan penuh, dan unduhan CSV tetap berhasil', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_CLOSED)

    await expect(page.locator('[data-testid="period-status"]')).toHaveText(/Tertutup/)
    await expect(page.locator('[data-testid="recording-coverage"]')).toBeVisible()
    await expect(page.locator('[data-testid="metric-steam-pressure-reading-count"]')).toHaveText('2')
    await expect(page.locator('[data-testid="per-unit-recap"]')).toBeVisible()
    // The period lock governs writing data, not reading a report.
    await expect(page.locator('[data-testid="export-button"]')).toBeEnabled()

    const csv = await downloadCsv(page)
    expect(csv).toContain(CLOSED.start)
  })

  // =====================================================================
  // Scenario 14: "rekap harian panjang"
  // =====================================================================
  test('rekap harian: tombol menutup tabelnya lalu membukanya kembali, angka utama dan tren tetap terlihat', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    // OPEN on first paint — the first click CLOSES it.
    await expect(page.locator('[data-testid="daily-recap"]')).toBeVisible()

    await toggleRecap(page)
    await expect(page.locator('[data-testid="daily-recap"]')).toHaveCount(0)
    // The headline figures and the trend survive both states.
    await expect(page.locator('[data-testid="report-metrics"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-trend-steam-pressure"]')).toBeVisible()

    await toggleRecap(page)
    await expect(page.locator('[data-testid="daily-recap"]')).toBeVisible()
    await expect(page.locator('[data-testid="report-metrics"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-trend-steam-pressure"]')).toBeVisible()

    // The recap table scrolls INSIDE its own card — the page itself never
    // scrolls horizontally, however many columns the recap has.
    const overflows = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    )
    expect(overflows).toBe(false)
  })

  // =====================================================================
  // Scenario 15: "layar hanya membaca"
  // =====================================================================
  test('baca saja: tidak ada tombol simpan, ubah, atau hapus di mana pun pada layar', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    // Walk the whole page, including both recap tables.
    await expect(page.locator('[data-testid="per-unit-recap"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-recap"]')).toBeVisible()

    for (const control of ['save-button', 'edit-button', 'delete-button', 'add-row-button', 'remove-row-button-0']) {
      await expect(page.locator(`[data-testid="${control}"]`)).toHaveCount(0)
    }

    // No form element that could post anything either.
    await expect(page.locator('[data-testid="laporan-boiler-room"] form')).toHaveCount(0)
    await expect(page.locator('[data-testid="laporan-boiler-room"] button[type="submit"]')).toHaveCount(0)

    // And the figures are identical after a full reload — nothing about
    // opening the page changed the data it reports on.
    await page.reload()
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)
    await expect(page.locator('[data-testid="metric-steam-pressure-reading-count"]')).toHaveText('12')
    await expect(page.locator('[data-testid="coverage-filled-slots"]')).toHaveText('12')
  })

  // =====================================================================
  // Scenario 16: "daftar periode hanya yang mencakup Boiler Room"
  // =====================================================================
  test('pemilih periode: hanya periode yang punya baris Boiler Room, bukan periode tanpa baris itu', async ({ page, browser }) => {
    await openReport(page, SUPERVISOR)

    const options = page.locator('[data-testid="period-selector"] option')

    await expect(options.filter({ hasText: PERIOD_MAIN })).toHaveCount(1)
    await expect(options.filter({ hasText: PERIOD_WHOLE_MILL })).toHaveCount(1)
    await expect(options.filter({ hasText: PERIOD_OTHER_MILL })).toHaveCount(0)

    // Newest first: this run's latest offered window is the whole-mill one,
    // so it leads the main period.
    const labels = await options.allTextContents()
    const wholeMillIndex = labels.findIndex((label) => label.includes(PERIOD_WHOLE_MILL))
    const mainIndex = labels.findIndex((label) => label.includes(PERIOD_MAIN))

    expect(wholeMillIndex).toBeLessThan(mainIndex)
    // Sejak toolbar filter bersama (2026-10-05) opsi periode berbentuk ringkas
    // "Nama · rentang · status" — jenis stasiun tidak lagi ditulis di tiap opsi
    // karena daftar ini memang hanya memuat periode yang mencakup stasiun layar
    // ini. Yang tetap dijaga: opsi tidak pernah membawa kata "Semua Stasiun"
    // milik cakupan NULL lama, dan tetap menyebut statusnya.
    expect(labels[wholeMillIndex]).not.toContain('Semua Stasiun')
    expect(labels[wholeMillIndex]).toMatch(/· (Draft|Terbuka|Tertutup)\s*$/)

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
  // Scenario 17: "rentang periode inklusif di kedua ujung"
  // =====================================================================
  test('rentang inklusif: baris tanggal awal dan akhir ada pada rekap dan CSV, tanggal di luar rentang tidak', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    await expect(page.locator(`[data-testid="daily-recap-row-${MAIN.start}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="daily-recap-row-${MAIN.end}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="daily-recap-row-${OUTSIDE_DATE}"]`)).toHaveCount(0)

    // The trend carries a column for each bound date, and none for the one
    // outside the window.
    await expect(page.locator(`[data-testid="daily-trend-steam-pressure-col-${MAIN.start}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="daily-trend-steam-pressure-col-${MAIN.end}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="daily-trend-steam-pressure-col-${OUTSIDE_DATE}"]`)).toHaveCount(0)

    // The day before the window carried 99 bar — none of it is here.
    await expect(page.locator('[data-testid="laporan-boiler-room"]')).not.toContainText('99,0')

    // And the CSV agrees with the page.
    const csv = await downloadCsv(page)

    expect(csv).toContain(MAIN.start)
    expect(csv).toContain(MAIN.end)
    expect(csv).not.toContain(OUTSIDE_DATE)
  })

  // =====================================================================
  // Scenario 18: "setiap metrik punya penyebutnya sendiri"
  // =====================================================================
  test('penyebut terpisah: pH wajar dengan 4 pembacaan, dan terendah periode adalah pembacaan mentah sementara rekap tetap rata-rata harian', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    // (6+7+7+8)/4 = 7,0, beside its OWN count of 4. A shared denominator
    // would have deflated it to 28/12 = 2,3.
    await expect(page.locator('[data-testid="metric-water-ph-avg"]')).toHaveText('7,0')
    await expect(page.locator('[data-testid="metric-water-ph-reading-count"]')).toHaveText('4')
    await expect(page.locator('[data-testid="metric-water-ph-avg"]')).not.toHaveText('2,3')
    // While the pressure card counts all twelve.
    await expect(page.locator('[data-testid="metric-steam-pressure-reading-count"]')).toHaveText('12')

    // The card's low is the RAW slot reading...
    await expect(page.locator('[data-testid="metric-steam-pressure-min"]')).toHaveText('12,0')
    await expect(page.locator('[data-testid="metric-steam-pressure-max"]')).toHaveText('28,0')

    // ...while the recap row for that very date shows the DAILY average of
    // 20,0. The two cannot be reconciled, which is why the screen must carry
    // the label saying so — without it the reader concludes the report is
    // broken.
    await expect(page.locator(`[data-testid="daily-recap-row-${MAIN.start}"]`)).toContainText('20,0')
    await expect(page.locator('[data-testid="raw-extremes-note"]')).toBeVisible()
    await expect(page.locator('[data-testid="raw-extremes-note"]')).toContainText('tidak dapat dicocokkan')
  })

  // =====================================================================
  // Scenario 19: "jumlah pembacaan ditampilkan berdampingan dengan angkanya"
  // =====================================================================
  test('jumlah pembacaan: terlihat berdampingan dengan tiap angka pada seluruh kartu, tanpa klik maupun hover', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    const expected: Record<string, string> = {
      'steam-pressure': '12',
      'steam-temp': '12',
      'water-tds': '0',
      'water-ph': '4',
      'exhaust-gas-temp': '0',
    }

    for (const [card, count] of Object.entries(expected)) {
      const avg = page.locator(`[data-testid="metric-${card}-avg"]`)
      const readingCount = page.locator(`[data-testid="metric-${card}-reading-count"]`)

      // Both visible without any interaction at all — no click, no hover,
      // no details to open. An average over 4 readings and one over 300 must
      // never look equally convincing.
      await expect(avg).toBeVisible()
      await expect(readingCount).toBeVisible()
      await expect(readingCount).toHaveText(count)

      // Side by side, inside the same card.
      await expect(page.locator(`[data-testid="metric-${card}"]`)).toContainText(count)
    }
  })

  // =====================================================================
  // Scenario 20: "laju bahan bakar dan beban fan tidak pernah dirata-rata"
  // =====================================================================
  test('teks bebas: tidak muncul sebagai rata-rata, min, max maupun tren di halaman, tetapi hadir apa adanya pada CSV', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    // Their units are mixed on the paper form (Hz / % / tons), so averaging
    // them is not merely wrong, it is meaningless.
    for (const card of ['fuel-feed-rate', 'id-fan-load', 'sa-fan-load']) {
      for (const part of ['avg', 'min', 'max', 'reading-count']) {
        await expect(page.locator(`[data-testid="metric-${card}-${part}"]`)).toHaveCount(0)
      }

      await expect(page.locator(`[data-testid="daily-trend-${card}"]`)).toHaveCount(0)
    }

    await expect(page.locator('[data-testid="laporan-boiler-room"]')).not.toContainText('12 ton/jam')
    await expect(page.locator('[data-testid="laporan-boiler-room"]')).not.toContainText('Laju Bahan Bakar')

    // The export is the ONE place they appear — verbatim, with no unit
    // normalisation and no rounding.
    const csv = await downloadCsv(page)

    expect(csv).toContain('Laju Bahan Bakar')
    expect(csv).toContain('Beban ID Fan')
    expect(csv).toContain('Beban SA Fan')
    expect(csv).toContain('12 ton/jam')
    expect(csv).toContain('80%')
    expect(csv).toContain('sedang')
  })

  // =====================================================================
  // Scenario 21: "tidak ada penandaan nilai di luar batas"
  // =====================================================================
  test('tanpa ambang: nilai ekstrem tampil netral tanpa warna peringatan, ikon, maupun label pelanggaran', async ({ page }) => {
    await openReport(page, SUPERVISOR)
    await selectProductionLine(page)
    await selectPeriod(page, PERIOD_MAIN)

    // The 12 / 28 spread IS rendered — it is simply never judged. Boiler
    // Room has no operational-target master, so any threshold here would be
    // a statistic dressed up as a SAFETY limit.
    await expect(page.locator('[data-testid="metric-steam-pressure-min"]')).toHaveText('12,0')
    await expect(page.locator('[data-testid="metric-steam-pressure-max"]')).toHaveText('28,0')

    for (const badge of ['threshold-badge', 'out-of-range-icon', 'alert-label', 'threshold-card', 'outlier-badge']) {
      await expect(page.locator(`[data-testid="${badge}"]`)).toHaveCount(0)
    }

    const html = await page.locator('[data-testid="laporan-boiler-room"]').innerHTML()

    for (const flavour of [
      'text-red', 'bg-red', 'md-chip--danger', 'md-chip--warning',
      'md-trendchart__col--low', 'md-trendchart__col--high', 'is-danger', 'is-warning',
    ]) {
      expect(html, `the report must carry no "${flavour}" styling`).not.toContain(flavour)
    }

    // Every metric value is styled identically — the extreme card uses the
    // same class as the unremarkable one next to it.
    const extremeClass = await page.locator('[data-testid="metric-steam-pressure"]').getAttribute('class')
    const ordinaryClass = await page.locator('[data-testid="metric-water-ph"]').getAttribute('class')

    expect(extremeClass).toBe(ordinaryClass)

    // And the trend bars carry no colour modifier either.
    const firstBar = page.locator(`[data-testid="daily-trend-steam-pressure-col-${MAIN.start}"]`)
    await expect(firstBar).toHaveClass('md-trendchart__col')
  })
})
