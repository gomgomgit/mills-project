import { test, expect, type Page } from '@playwright/test'
import { login, USERS } from './helpers'

/**
 * laporan-boiler-room.spec.ts — screen-137--laporan-boiler-room-mobile /
 * usecase-137--laporan-boiler-room-mobile "Lihat Laporan Periode Boiler
 * Room (Mobile)".
 *
 * Satu test per test_scenarios yang punya browser_test — seluruh 26.
 * Berjalan di Vite dev server (playwright.config.ts, baseURL
 * http://localhost:5174): aplikasi Capacitor adalah SPA biasa sebelum
 * dibungkus native, jadi browser test layar mobile TIDAK ditangguhkan
 * sebagai "mobile-only".
 *
 * ────────────────────────────────────────────────────────────────────────
 * VIEWPORT PONSEL, BUKAN DESKTOP — BUKAN OPSIONAL
 * ────────────────────────────────────────────────────────────────────────
 * Berkas ini menimpa viewport bawaan proyek chromium (Desktop Chrome,
 * 1280px) dengan 390x844 lewat test.use() pada SELURUH describe. Separuh
 * klaim layar ini adalah klaim tata letak ("satu kolom", "tanpa gulir
 * mendatar pada halaman", "sasaran sentuh minimal 44px"), dan seluruhnya
 * menjadi HAMPA bila diuji pada kanvas selebar 1280px: apa pun muat di
 * sana, dan asersinya lulus tanpa menguji apa pun.
 *
 * MASUK LEWAT RUTE LANGSUNG. Pintu masuk UI-nya (screen-141, Reporting:
 * pilih stasiun) punya test-nya sendiri di
 * tests/e2e/reporting-pilih-stasiun.spec.ts; berkas ini menavigasi
 * langsung ke '/reports/boiler-room' supaya kegagalan di sini selalu
 * berarti layar laporannya, bukan layar pemilih stasiun.
 *
 * RESPONS API DI-STUB DI TINGKAT JARINGAN (page.route), BUKAN DISEMAI KE
 * DATABASE — keputusan yang sama dengan laporan-cages-track.spec.ts dan
 * laporan-sterilizer.spec.ts, dengan alasan yang sama:
 *   - Layar ini read-only total dan tidak punya satu pun UI untuk membuat
 *     data yang dibacanya; menyemai fixture berarti menjalankan dua layar
 *     WEB lain (periode + input Boiler Room) dari dalam suite mobile.
 *   - Kebenaran ANGKA-nya bukan tanggung jawab layar ini: seluruhnya
 *     berasal dari BoilerRoomReportService, yang sudah diuji penuh di
 *     backend (Feature/Api + Feature/Livewire) dan di e2e-web terhadap data
 *     sungguhan. Yang khas mobile — dan hanya dapat dibuktikan di sini —
 *     adalah PEMETAAN respons itu ke layar satu kolom 390px.
 *   - Beberapa skenario mustahil dibuat dari data sungguhan tanpa merusak
 *     lingkungan (sesi kedaluwarsa, jaringan putus, akun tanpa mill).
 *
 * ANGKA FIXTURE SENGAJA TIDAK KONSISTEN SECARA ARITMETIKA — sama persis
 * dengan tests/boilerRoomReportRepo.spec.ts dan
 * tests/LaporanBoilerRoomView.spec.ts: coverage_percent (6,3) ≠ 100 × 9 /
 * 144; metrics.steam_pressure_bar.max (26,9) LEBIH KECIL daripada
 * daily[0].steam_pressure_avg (42,1) karena ekstrem berasal dari PEMBACAAN
 * MENTAH per slot waktu; maintenance.blowdown.executed (5) ≠ jumlah kolom
 * daily (3); total.days_with_records (11) ≠ panjang daily (3). Layar yang
 * diam-diam menghitung ulang HARUS gagal di sini. Jangan "merapikan".
 *
 * REKAP HARIAN MEMAKAI v-if, BUKAN v-show — berbeda dari screen-135/136.
 * Saat tertutup barisnya benar-benar HILANG dari DOM, jadi keadaan tertutup
 * diasersi dengan toHaveCount(0), bukan dengan not.toBeVisible().
 */

const ROUTE = '/reports/boiler-room'
const API_GLOB = '**/api/boiler-room-reports/**'

/**
 * Halaman disajikan dari :5174 sementara API berada di :8000, jadi setiap
 * respons stub harus membawa header CORS-nya sendiri (termasuk jawaban
 * preflight) — tanpa ini axios menerimanya sebagai kegagalan jaringan dan
 * setiap test di bawah akan "lulus" ke cabang yang salah.
 */
const CORS_HEADERS = {
  'access-control-allow-origin': '*',
  'access-control-allow-headers': '*',
  'access-control-allow-methods': 'GET,POST,OPTIONS',
}

/* ------------------------------------------------------------------ */
/* Fixture — BENTUK RESPONS NYATA                                      */
/* ------------------------------------------------------------------ */

/**
 * /periods dan /business-units/options dibungkus { data: [...] },
 * sedangkan /summary dikirim TANPA pembungkus. Itu bentuk yang
 * benar-benar dikirim App\Http\Controllers\Api\BoilerRoomReportController.
 */
const PERIOD_BR = {
  id: 'per-1',
  name: 'Periode Maret 2026',
  start_date: '2026-03-01',
  end_date: '2026-03-06',
  status: 'open',
  station_type: 'boiler-room',
  station_type_label: 'Boiler Room',
}

const PERIOD_ALL_TYPES = {
  id: 'per-2',
  name: 'Periode Semua Stasiun Maret 2026',
  start_date: '2026-03-01',
  end_date: '2026-03-31',
  status: 'open',
  station_type: null,
  station_type_label: 'Semua Jenis Stasiun',
}

const PERIOD_CLOSED = {
  id: 'per-3',
  name: 'Periode Februari 2026',
  start_date: '2026-02-01',
  end_date: '2026-02-28',
  status: 'closed',
  station_type: 'boiler-room',
  station_type_label: 'Boiler Room',
}

const BUSINESS_UNITS = [
  { id: 'bu-1', name: 'Mill Utara' },
  { id: 'bu-2', name: 'Mill Selatan' },
]

const EMPTY_METRIC = { min: null, avg: null, max: null, reading_count: 0 }

function makeMetrics(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    steam_pressure_bar: { min: 18.2, avg: 21.7, max: 26.9, reading_count: 300 },
    steam_temp_c: { min: 240.0, avg: 268.4, max: 301.5, reading_count: 287 },
    water_tds_ppm: { min: 1400, avg: 1980, max: 2600, reading_count: 265 },
    water_ph: { min: 9.8, avg: 10.4, max: 11.2, reading_count: 3 },
    exhaust_gas_temp_c: { min: 180, avg: 214, max: 260, reading_count: 240 },
    feed_water_temp_c: { min: 78.5, avg: 84.2, max: 91.0, reading_count: 198 },
    feed_water_tank_level_percent: { min: 42.0, avg: 61.3, max: 88.0, reading_count: 176 },
    boiler_water_level_percent: { min: 35.5, avg: 52.8, max: 70.1, reading_count: 154 },
    dust_collector_differential_pressure_mmh2o: { min: 12.0, avg: 18.9, max: 27.4, reading_count: 121 },
    ...overrides,
  }
}

const EMPTY_METRICS = {
  steam_pressure_bar: { ...EMPTY_METRIC },
  steam_temp_c: { ...EMPTY_METRIC },
  water_tds_ppm: { ...EMPTY_METRIC },
  water_ph: { ...EMPTY_METRIC },
  exhaust_gas_temp_c: { ...EMPTY_METRIC },
  feed_water_temp_c: { ...EMPTY_METRIC },
  feed_water_tank_level_percent: { ...EMPTY_METRIC },
  boiler_water_level_percent: { ...EMPTY_METRIC },
  dust_collector_differential_pressure_mmh2o: { ...EMPTY_METRIC },
}

const DAILY_ROWS = [
  {
    date: '2026-03-01',
    filled_slots: 4,
    steam_pressure_avg: 42.1,
    steam_temp_avg: 268.0,
    water_tds_avg: 1900,
    water_ph_avg: null,
    exhaust_gas_temp_avg: null,
    blowdown_executed: 1,
    sootblowing_executed: 0,
  },
  {
    date: '2026-03-02',
    filled_slots: 3,
    steam_pressure_avg: null,
    steam_temp_avg: null,
    water_tds_avg: null,
    water_ph_avg: null,
    exhaust_gas_temp_avg: null,
    blowdown_executed: 0,
    sootblowing_executed: 0,
  },
  {
    date: '2026-03-06',
    filled_slots: 5,
    steam_pressure_avg: 19.4,
    steam_temp_avg: 251.0,
    water_tds_avg: 1750,
    water_ph_avg: 10.4,
    exhaust_gas_temp_avg: 205.0,
    blowdown_executed: 2,
    sootblowing_executed: 1,
  },
]

const BY_UNIT_ROWS = [
  {
    boiler_room_id: 'BLR-1',
    reading_count: 301,
    steam_pressure_avg: 22.0,
    steam_temp_avg: 270.0,
    water_tds_avg: 2000,
    water_ph_avg: 10.5,
    blowdown_executed: 4,
    sootblowing_executed: 1,
  },
  {
    boiler_room_id: 'BLR-2',
    reading_count: 0,
    steam_pressure_avg: null,
    steam_temp_avg: null,
    water_tds_avg: null,
    water_ph_avg: null,
    blowdown_executed: 0,
    sootblowing_executed: 0,
  },
]

function makeSummary(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    period: {
      id: 'per-1',
      name: 'Periode Maret 2026',
      start_date: '2026-03-01',
      end_date: '2026-03-06',
      status: 'open',
      business_unit_name: 'Mill Utara',
    },
    business_unit: { id: 'bu-1', name: 'Mill Utara' },
    has_data: true,
    coverage: {
      filled_slots: 9,
      expected_slots: 144,
      coverage_percent: 6.3,
      boiler_unit_count: 2,
      slots_per_unit_per_day: 24,
      days_in_period: 6,
    },
    metrics: makeMetrics(),
    maintenance: {
      blowdown: { executed: 5, not_executed: 2, not_recorded: 41, avg_per_day: 0.83, all_unrecorded: false },
      sootblowing: { executed: 0, not_executed: 0, not_recorded: 48, avg_per_day: 0, all_unrecorded: true },
    },
    daily: DAILY_ROWS,
    by_unit: BY_UNIT_ROWS,
    total: { days_with_records: 11, reading_rows: 412 },
    ...overrides,
  }
}

const EMPTY_SUMMARY = makeSummary({
  has_data: false,
  coverage: {
    filled_slots: 0,
    expected_slots: 720,
    coverage_percent: 0,
    boiler_unit_count: 1,
    slots_per_unit_per_day: 24,
    days_in_period: 30,
  },
  metrics: { ...EMPTY_METRICS },
  maintenance: {
    blowdown: { executed: 0, not_executed: 0, not_recorded: 0, avg_per_day: 0, all_unrecorded: false },
    sootblowing: { executed: 0, not_executed: 0, not_recorded: 0, avg_per_day: 0, all_unrecorded: false },
  },
  daily: [],
  by_unit: [],
  total: { days_with_records: 0, reading_rows: 0 },
})

/**
 * Rekap sebulan penuh — 30 baris. Ini juga fixture tata letak: 30 kolom
 * grafik (34px masing-masing) jauh lebih lebar daripada viewport 390px,
 * yang membuat klaim "menggulir DI DALAM kartunya" dapat diuji.
 */
const THIRTY_DAYS = Array.from({ length: 30 }, (_, index) => ({
  date: `2026-03-${String(index + 1).padStart(2, '0')}`,
  filled_slots: 4,
  steam_pressure_avg: 20 + index * 0.1,
  steam_temp_avg: 250 + index,
  water_tds_avg: 1800 + index,
  water_ph_avg: 10.2,
  exhaust_gas_temp_avg: 200 + index,
  blowdown_executed: 1,
  sootblowing_executed: 0,
}))

/** Kesembilan kartu metrik, dalam urutan NUMERIC_METRICS server. */
const METRIC_TESTIDS = [
  'steam-pressure',
  'steam-temp',
  'water-tds',
  'water-ph',
  'exhaust-gas-temp',
  'feed-water-temp',
  'feed-water-tank-level',
  'boiler-water-level',
  'dust-collector-dp',
]

/* ------------------------------------------------------------------ */
/* Stub jaringan                                                       */
/* ------------------------------------------------------------------ */

interface ApiState {
  units: unknown[]
  periods: unknown[]
  summary: Record<string, unknown>
  /** 200 kecuali test menyetel lain (401 / 422 / 403). */
  summaryStatus: number
  /** true = permintaan ringkasan diputus di tingkat jaringan. */
  summaryAbort: boolean
  hits: { units: number; periods: number; summary: number; export: number; total: number }
  /** Seluruh URL yang benar-benar dikirim halaman ke endpoint laporan. */
  urls: string[]
  /**
   * Rekaman page.on('request') — independen dari handler route(), supaya
   * klaim "nol permintaan jaringan baru" tidak bersandar pada satu
   * mekanisme saja.
   */
  requested: string[]
}

async function stubApi(page: Page, overrides: Partial<ApiState> = {}): Promise<ApiState> {
  const state: ApiState = {
    units: BUSINESS_UNITS,
    periods: [PERIOD_BR],
    summary: makeSummary(),
    summaryStatus: 200,
    summaryAbort: false,
    hits: { units: 0, periods: 0, summary: 0, export: 0, total: 0 },
    urls: [],
    requested: [],
    ...overrides,
  }

  page.on('request', (request) => {
    if (request.url().includes('/api/boiler-room-reports/')) {
      state.requested.push(request.url())
    }
  })

  await page.route(API_GLOB, async (route) => {
    const request = route.request()
    const url = request.url()

    if (request.method() === 'OPTIONS') {
      await route.fulfill({ status: 204, headers: CORS_HEADERS, body: '' })

      return
    }

    state.urls.push(url)
    state.hits.total += 1

    if (url.includes('/business-units/options')) {
      state.hits.units += 1
      await route.fulfill({ status: 200, headers: CORS_HEADERS, json: { data: state.units } })

      return
    }

    if (url.includes('/periods')) {
      state.hits.periods += 1
      await route.fulfill({ status: 200, headers: CORS_HEADERS, json: { data: state.periods } })

      return
    }

    if (url.includes('/summary')) {
      state.hits.summary += 1

      if (state.summaryAbort) {
        await route.abort('failed')

        return
      }

      if (state.summaryStatus !== 200) {
        await route.fulfill({
          status: state.summaryStatus,
          headers: CORS_HEADERS,
          json: { message: state.summaryStatus === 401 ? 'Unauthenticated.' : 'Permintaan tidak valid.' },
        })

        return
      }

      await route.fulfill({ status: 200, headers: CORS_HEADERS, json: state.summary })

      return
    }

    if (url.includes('/export')) {
      state.hits.export += 1
      await route.fulfill({
        status: 200,
        headers: { ...CORS_HEADERS, 'content-type': 'text/csv' },
        body: 'Tanggal,Slot Waktu,Unit Boiler,Tekanan Uap (bar),Laju Bahan Bakar,Beban ID Fan,Beban SA Fan\n2026-03-01,08:00,BLR-1,20.5,45 Hz,80 %,12 A\n',
      })

      return
    }

    await route.continue()
  })

  return state
}

/* ------------------------------------------------------------------ */
/* Bantuan sesi                                                        */
/* ------------------------------------------------------------------ */

/** Sesi tersimpan dijadikan sesi Admin (peran admin, tanpa mill). */
async function becomeAdminSession(page: Page): Promise<void> {
  await page.evaluate(() => {
    const raw = localStorage.getItem('msl_auth_user')
    const user = raw ? JSON.parse(raw) : {}
    user.role = 'admin'
    user.business_unit_id = null
    localStorage.setItem('msl_auth_user', JSON.stringify(user))
    localStorage.removeItem('msl_auth_business_unit')
  })
}

/** Akun terikat mill yang business_unit_id-nya kosong (masalah data, bukan Admin). */
async function becomeMilllessSession(page: Page): Promise<void> {
  await page.evaluate(() => {
    const raw = localStorage.getItem('msl_auth_user')
    const user = raw ? JSON.parse(raw) : {}
    user.role = 'operator'
    user.business_unit_id = null
    localStorage.setItem('msl_auth_user', JSON.stringify(user))
    localStorage.removeItem('msl_auth_business_unit')
  })
}

async function openReport(page: Page, path: string = ROUTE): Promise<void> {
  await page.goto(path)
  await expect(page.getByTestId('laporan-boiler-room-mobile')).toBeVisible()
}

async function pickPeriod(page: Page, periodId: string): Promise<void> {
  await page.getByTestId('period-select').selectOption(periodId)
}

/** Rekap Harian — TERTUTUP secara bawaan, memakai v-if. */
function recapToggle(page: Page) {
  return page.getByTestId('daily-recap-toggle')
}

function recapRows(page: Page) {
  return page.getByTestId('daily-recap-row')
}

/* ------------------------------------------------------------------ */
/* Bantuan asersi khas ponsel                                          */
/* ------------------------------------------------------------------ */

/**
 * HALAMAN tidak pernah menggulir mendatar. Yang lebar adalah isi kartu
 * (dua grafik tren, tabel per unit, tabel rekap) — dan masing-masing
 * menggulir di dalam kartunya sendiri, itulah yang dibuktikan
 * expectCardScrollsInsideItself() di bawah.
 */
async function expectNoHorizontalPageScroll(page: Page): Promise<void> {
  const overflow = await page.evaluate(() => {
    const doc = document.documentElement

    return {
      scrollWidth: doc.scrollWidth,
      clientWidth: doc.clientWidth,
      bodyScrollWidth: document.body.scrollWidth,
    }
  })

  // Toleransi 1px untuk pembulatan subpiksel.
  expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1)
  expect(overflow.bodyScrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1)
}

/** Satu kolom: tidak ada dua kartu metrik yang berdampingan mendatar. */
async function expectSingleColumn(page: Page): Promise<void> {
  const cards = page.locator('main.laporan-br-view > .metric-stack > .metric-card')
  expect(await cards.count()).toBe(9)

  const boxes = await cards.evaluateAll((elements) =>
    elements.map((element) => {
      const rect = element.getBoundingClientRect()

      return { top: rect.top, bottom: rect.bottom }
    }),
  )

  // Setiap kartu berikutnya berada DI BAWAH kartu sebelumnya, bukan di
  // sampingnya — itulah arti "satu kolom" pada layar 390px.
  for (let index = 1; index < boxes.length; index += 1) {
    expect(boxes[index].top).toBeGreaterThanOrEqual(boxes[index - 1].bottom - 1)
  }
}

/** Sasaran sentuh minimal 44px — ukuran jari, bukan ukuran kursor. */
async function expectTouchTargets(page: Page, testIds: string[]): Promise<void> {
  for (const testId of testIds) {
    const box = await page.getByTestId(testId).boundingBox()
    expect(box, `sasaran sentuh ${testId} tidak terender`).not.toBeNull()
    expect(box!.height, `sasaran sentuh ${testId} terlalu pendek`).toBeGreaterThanOrEqual(44)
    expect(box!.width, `sasaran sentuh ${testId} terlalu sempit`).toBeGreaterThanOrEqual(44)
  }
}

/**
 * Kartu yang isinya lebih lebar daripada layar menggulir DI DALAM dirinya
 * sendiri: scrollWidth > clientWidth DAN overflow-x-nya auto/scroll.
 */
async function expectCardScrollsInsideItself(page: Page, selector: string): Promise<void> {
  const result = await page.locator(selector).first().evaluate((element) => ({
    scrollWidth: element.scrollWidth,
    clientWidth: element.clientWidth,
    overflowX: getComputedStyle(element).overflowX,
  }))

  expect(['auto', 'scroll']).toContain(result.overflowX)
  expect(result.scrollWidth).toBeGreaterThan(result.clientWidth)
}

/* ================================================================== */

test.describe('Laporan Boiler Room Mobile (screen-137)', () => {
  // Layar ponsel sungguhan — lihat catatan VIEWPORT pada docblock berkas.
  // Tanpa baris ini seluruh tuntutan satu-kolom di bawah menjadi hampa.
  test.use({ viewport: { width: 390, height: 844 } })

  // Scenario 1: "berhasil dilihat Operator, Supervisor, dan Mill Management"
  test('berhasil — satu kolom tanpa gulir mendatar halaman, tanpa pemilih Mill, tanpa kontrol tulis', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page)
    await openReport(page)

    // Pengguna terikat mill: pemilih Mill tidak ditawarkan sama sekali.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.getByTestId('mill-current')).toBeVisible()

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('mill-current')).toContainText('Mill Utara')
    await expect(page.getByTestId('period-meta')).toContainText('Periode Maret 2026')

    // Kelengkapan pencatatan.
    await expect(page.getByTestId('coverage-percent')).toHaveText('6,3%')
    await expect(page.getByTestId('coverage-slots')).toContainText('9 dari 144 slot waktu terisi')

    // Kesembilan metrik dengan terendah/rata-rata/tertinggi beserta
    // reading_count masing-masing.
    await expect(page.locator('[data-testid="metric-cards"] .metric-card')).toHaveCount(9)
    await expect(page.getByTestId('metric-steam-pressure-avg')).toHaveText('21,7')
    await expect(page.getByTestId('metric-steam-pressure-min')).toHaveText('18,2')
    await expect(page.getByTestId('metric-steam-pressure-max')).toHaveText('26,9')
    await expect(page.getByTestId('metric-steam-pressure-count')).toContainText('300')
    await expect(page.getByTestId('metric-water-ph-count')).toContainText('3')

    // Perawatan: dilakukan + tidak tercatat, terpisah.
    await expect(page.getByTestId('maintenance-blowdown-executed')).toHaveText('5')
    await expect(page.getByTestId('maintenance-blowdown-not-recorded')).toHaveText('41')

    // Tren harian, rekap per unit, rekap harian.
    await expect(page.getByTestId('daily-trend-steam-pressure')).toBeVisible()
    await expect(page.getByTestId('daily-trend-steam-temp')).toBeVisible()
    await expect(page.getByTestId('by-unit-card')).toBeVisible()
    await expect(page.getByTestId('daily-recap')).toBeVisible()
    await expect(page.getByTestId('raw-extremes-note')).toBeVisible()

    // Tata letak ponsel.
    await expectSingleColumn(page)
    await expectNoHorizontalPageScroll(page)
    await expectTouchTargets(page, ['period-select', 'export-button', 'back-button'])

    // Tidak ada satu pun kontrol tulis di sepanjang layar.
    await expect(page.locator('main.laporan-br-view input')).toHaveCount(0)
    await expect(page.locator('main.laporan-br-view textarea')).toHaveCount(0)
    await expect(page.getByRole('button', { name: /simpan|hapus/i })).toHaveCount(0)

    // Layar baca: tidak ada permintaan ke luar prefiks laporan.
    expect(api.urls.every((url) => url.includes('boiler-room-reports'))).toBe(true)
  })

  // Scenario 2: "berhasil dilihat Admin setelah memilih mill"
  test('Admin — memilih mill memuat ulang periode beserta seluruh angkanya, tetap satu kolom', async ({ page }) => {
    await login(page, USERS.operator)
    await becomeAdminSession(page)
    const api = await stubApi(page)
    await openReport(page)

    await expect(page.getByTestId('mill-select')).toBeVisible()
    await expect(page.getByTestId('mill-select').locator('option')).toHaveCount(3) // placeholder + 2 mill
    expect(api.hits.periods).toBe(0)

    await page.getByTestId('mill-select').selectOption('bu-1')
    await expect(page.getByTestId('period-select').locator('option')).toHaveCount(2)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('coverage-percent')).toHaveText('6,3%')
    await expect(page.getByTestId('metric-steam-pressure-avg')).toHaveText('21,7')
    await expect(page.getByTestId('by-unit-card')).toBeVisible()
    await expectSingleColumn(page)
    await expectNoHorizontalPageScroll(page)

    // business_unit_id memang dikirim untuk Admin — dan HANYA untuk Admin.
    expect(api.urls.some((url) => url.includes('/summary') && url.includes('business_unit_id=bu-1'))).toBe(true)

    // Mengganti mill memuat ulang daftar periode dan membuang angka lama.
    const periodsBefore = api.hits.periods
    await page.getByTestId('mill-select').selectOption('bu-2')
    await expect(page.getByTestId('metric-cards')).toHaveCount(0)
    expect(api.hits.periods).toBe(periodsBefore + 1)
    expect(api.urls.some((url) => url.includes('/periods') && url.includes('business_unit_id=bu-2'))).toBe(true)
  })

  // Scenario 3: "Operator membuka laporan"
  test('Operator — laporan terbuka penuh, tanpa kontrol ganti mill, nama mill adalah mill akunnya', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Tidak ada bagian yang dibatasi untuk peran ini.
    await expect(page.getByTestId('coverage-card')).toBeVisible()
    await expect(page.locator('[data-testid="metric-cards"] .metric-card')).toHaveCount(9)
    await expect(page.getByTestId('maintenance-card')).toBeVisible()
    await expect(page.getByTestId('daily-trend-steam-pressure')).toBeVisible()
    await expect(page.getByTestId('by-unit-card')).toBeVisible()
    await expect(page.getByTestId('daily-recap')).toBeVisible()

    // Tidak ada kontrol untuk mengganti mill, dan daftar seluruh mill tidak
    // pernah diminta.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.locator('main.laporan-br-view select')).toHaveCount(1)
    await expect(page.getByTestId('mill-current')).toContainText('Mill Utara')
    expect(api.hits.units).toBe(0)
  })

  // Scenario 4: "Admin memilih mill lebih dulu"
  test('Admin belum memilih mill — pemilih Mill tampil, arahan terbaca, tanpa satu pun angka', async ({ page }) => {
    await login(page, USERS.operator)
    await becomeAdminSession(page)
    const api = await stubApi(page)
    await openReport(page)

    await expect(page.getByTestId('mill-required-hint')).toBeVisible()
    await expect(page.getByTestId('mill-required-hint')).toContainText('Pilih mill')
    await expect(page.getByTestId('mill-select')).toBeVisible()
    // Belum terpilih: opsi aktif masih placeholder. Dibaca lewat
    // option:checked, bukan toHaveValue('') — placeholder memakai
    // :value="null", sehingga nilai DOM-nya adalah teks opsi itu sendiri.
    await expect(page.getByTestId('mill-select').locator('option:checked')).toHaveText('Pilih Mill')

    await expect(page.getByTestId('coverage-card')).toHaveCount(0)
    await expect(page.getByTestId('metric-cards')).toHaveCount(0)
    await expect(page.getByTestId('period-meta')).toHaveCount(0)

    expect(api.hits.periods).toBe(0)
    expect(api.hits.summary).toBe(0)
  })

  // Scenario 5: "mill belum punya periode"
  test('mill belum punya periode — pemilih Periode kosong dan arahan menghubungi Admin terbaca', async ({ page }) => {
    await login(page, USERS.supervisor)
    const api = await stubApi(page, { periods: [] })
    await openReport(page)

    await expect(page.getByTestId('no-periods')).toBeVisible()
    await expect(page.getByTestId('no-periods')).toContainText('hubungi Admin')
    // Hanya placeholder.
    await expect(page.getByTestId('period-select').locator('option')).toHaveCount(1)

    // Layar tidak kosong tanpa penjelasan, dan ini bukan galat teknis.
    await expect(page.getByTestId('metric-cards')).toHaveCount(0)
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('error-message')).toHaveCount(0)
    expect(api.hits.summary).toBe(0)
  })

  // Scenario 6: "periode tanpa data"
  test('periode tanpa data — seluruh angka tampil "-" bukan nol, dan tanpa grafik kosong yang menyesatkan', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page, { summary: EMPTY_SUMMARY })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('empty-period')).toBeVisible()
    await expect(page.getByTestId('empty-period')).toContainText('Belum ada data')

    // "Belum diukur" dan "nilainya nol" adalah dua fakta berbeda.
    for (const testid of METRIC_TESTIDS) {
      await expect(page.getByTestId(`metric-${testid}-avg`)).toHaveText('-')
      await expect(page.getByTestId(`metric-${testid}-min`)).toHaveText('-')
      await expect(page.getByTestId(`metric-${testid}-max`)).toHaveText('-')
      await expect(page.getByTestId(`metric-${testid}-count`)).toContainText('0 pembacaan')
    }

    // Tidak ada grafik kosong yang akan terbaca sebagai garis datar hasil
    // pengukuran.
    await expect(page.getByTestId('daily-trend-steam-pressure')).toHaveCount(0)
    await expect(page.getByTestId('daily-trend-steam-temp')).toHaveCount(0)
    await expect(page.getByTestId('daily-recap')).toHaveCount(0)
    await expect(page.getByTestId('by-unit-card')).toHaveCount(0)

    await expectNoHorizontalPageScroll(page)
  })

  // Scenario 7: "sebuah metrik tidak pernah diisi"
  test('satu metrik kosong — kartu pH air terbaca tidak tersedia dengan 0 pembacaan, kartu lain normal', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page, {
      summary: makeSummary({ metrics: makeMetrics({ water_ph: { ...EMPTY_METRIC } }) }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metric-water-ph-avg')).toHaveText('-')
    await expect(page.getByTestId('metric-water-ph-min')).toHaveText('-')
    await expect(page.getByTestId('metric-water-ph-max')).toHaveText('-')
    await expect(page.getByTestId('metric-water-ph-count')).toContainText('0 pembacaan')
    await expect(page.getByTestId('metric-water-ph')).not.toContainText('0,0')

    // Kartu metrik lain tetap menampilkan angkanya masing-masing.
    await expect(page.getByTestId('metric-steam-pressure-avg')).toHaveText('21,7')
    await expect(page.getByTestId('metric-steam-pressure-count')).toContainText('300')
    await expect(page.getByTestId('metric-steam-temp-avg')).toHaveText('268,4')
  })

  // Scenario 8: "perawatan tidak tercatat"
  test('perawatan — hanya yang bertanda dilakukan yang terbaca sebagai dilakukan, tidak tercatat berdiri sendiri', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('maintenance-blowdown-executed')).toHaveText('5')
    await expect(page.getByTestId('maintenance-blowdown-not-executed')).toHaveText('2')
    await expect(page.getByTestId('maintenance-blowdown-not-recorded')).toHaveText('41')
    // 2 + 41 = 43 tidak pernah muncul: "tidak tercatat" tidak pernah
    // dibacakan sebagai "tidak dilakukan".
    await expect(page.getByTestId('maintenance-card')).not.toContainText('43')

    await expect(page.getByTestId('maintenance-sootblowing-executed')).toHaveText('0')
    await expect(page.getByTestId('maintenance-sootblowing-not-recorded')).toHaveText('48')
    await expect(page.getByTestId('maintenance-sootblowing-all-unrecorded')).toBeVisible()
    await expect(page.getByTestId('maintenance-sootblowing-all-unrecorded')).toContainText('TIDAK TERCATAT')
    // blowdown all_unrecorded false — keterangannya tidak dirender.
    await expect(page.getByTestId('maintenance-blowdown-all-unrecorded')).toHaveCount(0)
  })

  // Scenario 9: "pencatatan sangat tidak lengkap"
  test('pencatatan sangat tidak lengkap — kelengkapan terbaca lebih dulu daripada angka metrik mana pun', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page, {
      summary: makeSummary({
        coverage: {
          filled_slots: 9,
          expected_slots: 720,
          coverage_percent: 1.3,
          boiler_unit_count: 1,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
        },
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Terbaca LEBIH DULU tanpa perlu menggulir mencarinya: kartu
    // kelengkapan berada di atas kartu metrik pertama pada layar 390px.
    const coverageBox = await page.getByTestId('coverage-card').boundingBox()
    const firstMetricBox = await page.getByTestId('metric-steam-pressure').boundingBox()
    expect(coverageBox).not.toBeNull()
    expect(firstMetricBox).not.toBeNull()
    expect(coverageBox!.y).toBeLessThan(firstMetricBox!.y)

    // Angkanya tetap tampil, dan coverage_percent tidak dihitung ulang
    // (100 × 9 / 720 = 1,25).
    await expect(page.getByTestId('coverage-percent')).toHaveText('1,3%')
    await expect(page.getByTestId('coverage-slots')).toContainText('9 dari 720 slot waktu terisi')
    await expect(page.getByTestId('metric-steam-pressure-avg')).toHaveText('21,7')
  })

  // Scenario 10: "mill punya beberapa unit boiler"
  test('beberapa unit boiler — seluruh unit muncul termasuk yang nol, dan tabelnya bergeser di dalam kartunya', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page, {
      summary: makeSummary({
        daily: THIRTY_DAYS,
        by_unit: [
          ...BY_UNIT_ROWS,
          {
            boiler_room_id: 'BLR-3',
            reading_count: 88,
            steam_pressure_avg: 24.5,
            steam_temp_avg: 280.0,
            water_tds_avg: 2100,
            water_ph_avg: 10.9,
            blowdown_executed: 1,
            sootblowing_executed: 0,
          },
        ],
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Angka periode menggabungkan seluruh unit — dari blok metrics apa
    // adanya, bukan dihitung ulang dari by_unit.
    await expect(page.getByTestId('metric-steam-pressure-avg')).toHaveText('21,7')

    const rows = page.getByTestId('by-unit-row')
    await expect(rows).toHaveCount(3)
    await expect(rows.nth(0)).toContainText('BLR-1')
    await expect(rows.nth(1)).toContainText('BLR-2')
    await expect(rows.nth(2)).toContainText('BLR-3')

    // Tabel bergeser DI DALAM kartunya sendiri tanpa menggeser halaman.
    await expectCardScrollsInsideItself(page, '[data-testid="by-unit-card"] .detail-table-wrap')
    await expectNoHorizontalPageScroll(page)
  })

  // Scenario 11: "akun belum terhubung ke mill"
  test('akun tanpa mill — pesan hubungi Admin, tanpa daftar mill, dan NOL permintaan laporan', async ({ page }) => {
    await login(page, USERS.supervisor)
    await becomeMilllessSession(page)
    const api = await stubApi(page)
    await openReport(page)

    await expect(page.getByTestId('no-mill-for-account')).toBeVisible()
    await expect(page.getByTestId('no-mill-for-account')).toContainText('hubungi Admin')
    // Daftar seluruh mill TIDAK ditawarkan kepada yang tidak berhak.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.getByTestId('metric-cards')).toHaveCount(0)

    expect(api.hits.total).toBe(0)
    expect(api.urls).toEqual([])
    expect(api.requested).toEqual([])
  })

  // Scenario 12: "mencoba melihat mill lain"
  test('mencoba melihat mill lain — business_unit_id dari URL diabaikan, mill akun yang tetap tampil', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page)

    // business_unit_id mill lain dicantumkan pada permintaan halaman.
    await openReport(page, `${ROUTE}?business_unit_id=bu-99`)
    await pickPeriod(page, 'per-1')

    // Angka yang tampil milik mill akun (BU-A / Mill Utara).
    await expect(page.getByTestId('mill-current')).toContainText('Mill Utara')
    await expect(page.getByTestId('period-meta')).toContainText('Periode Maret 2026')
    await expect(page.getByTestId('metric-steam-pressure-avg')).toHaveText('21,7')
    // Tidak ada kontrol pada layar untuk berpindah mill.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)

    // Tidak satu pun permintaan membawa business_unit_id.
    expect(api.urls.length).toBeGreaterThan(0)
    for (const url of api.urls) {
      expect(url).not.toContain('business_unit_id')
    }
  })

  // Scenario 13: "jaringan gagal"
  test('jaringan gagal — pesan jelas + Coba Lagi, periode terpilih bertahan, dan coba lagi menampilkan angkanya', async ({
    page,
  }) => {
    await login(page)
    const api = await stubApi(page, { summaryAbort: true })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Bukan layar kosong dan bukan diam.
    await expect(page.getByTestId('network-error')).toBeVisible()
    await expect(page.getByTestId('network-error')).toContainText(/koneksi|terhubung/i)
    await expect(page.getByTestId('retry-button')).toBeVisible()
    await expectTouchTargets(page, ['retry-button'])
    // Periode yang sudah dipilih TIDAK hilang.
    await expect(page.getByTestId('period-select')).toHaveValue('per-1')

    // Jaringan dipulihkan.
    api.summaryAbort = false
    await page.getByTestId('retry-button').click()

    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('metric-steam-pressure-avg')).toHaveText('21,7')
    await expect(page.getByTestId('period-select')).toHaveValue('per-1')
  })

  // Scenario 14: "sesi berakhir"
  test('sesi berakhir — 401 memindahkan ke Login sebelum satu angka pun tampil, tanpa tombol coba lagi', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page, { summaryStatus: 401 })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await page.waitForURL('**/login**')
    await expect(page.getByTestId('laporan-boiler-room-mobile')).toHaveCount(0)
    // 401 bukan kegagalan jaringan, dan bukan kasus coba lagi.
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('retry-button')).toHaveCount(0)
  })

  // Scenario 15: "periode tertutup"
  test('periode tertutup — laporan penuh, status Ditutup terbaca, unduhan CSV tetap berjalan', async ({ page }) => {
    await login(page)
    await stubApi(page, {
      periods: [PERIOD_CLOSED],
      summary: makeSummary({
        period: {
          id: 'per-3',
          name: 'Periode Februari 2026',
          start_date: '2026-02-01',
          end_date: '2026-02-28',
          status: 'closed',
          business_unit_name: 'Mill Utara',
        },
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-3')

    await expect(page.getByTestId('period-status-badge')).toHaveText('Ditutup')
    await expect(page.getByTestId('coverage-percent')).toHaveText('6,3%')
    await expect(page.locator('[data-testid="metric-cards"] .metric-card')).toHaveCount(9)
    await expect(page.getByTestId('by-unit-card')).toBeVisible()
    await expect(page.getByTestId('export-button')).toBeEnabled()

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByTestId('export-button').click(),
    ])
    expect(download.suggestedFilename()).toMatch(/^laporan-boiler-room_.*\.csv$/)
  })

  // Scenario 16: "rekap harian dapat ditutup agar layar ponsel tetap terbaca"
  test('rekap harian — tertutup bawaan, dibuka utuh, tetap satu kolom, dan NOL permintaan jaringan baru', async ({
    page,
  }) => {
    await login(page)
    const api = await stubApi(page, { summary: makeSummary({ daily: THIRTY_DAYS }) })
    await openReport(page)

    // `pickPeriod` hanya menunggu event `change` di browser; GET /summary masih
    // dalam perjalanan ke handler `page.route()` di Node, dan `hits.summary` baru
    // bertambah DI DALAM handler itu (sebelum `route.fulfill()`). Pembacaan
    // counter di bawah bersifat sinkron dan TIDAK auto-retry, jadi respons harus
    // ditunggu lebih dulu agar angkanya sudah pasti.
    const summaryResponse = page.waitForResponse((response) => response.url().includes('/summary'))
    await pickPeriod(page, 'per-1')
    await summaryResponse

    const summaryHits = api.hits.summary
    const requestedBefore = api.requested.length
    expect(summaryHits).toBe(1)

    // TERTUTUP secara bawaan, dan memakai v-if: barisnya benar-benar tidak
    // ada di DOM.
    await expect(page.getByTestId('daily-recap')).toBeVisible()
    await expect(recapRows(page)).toHaveCount(0)
    await expect(recapToggle(page)).toHaveAttribute('aria-expanded', 'false')

    // Saat tertutup, angka utama dan grafik terbaca tanpa gulir panjang.
    await expect(page.getByTestId('coverage-percent')).toHaveText('6,3%')
    await expect(page.getByTestId('daily-trend-steam-pressure')).toBeVisible()
    await expectNoHorizontalPageScroll(page)

    // Dibuka: seluruh 30 baris tampil utuh.
    await recapToggle(page).click()
    await expect(recapToggle(page)).toHaveAttribute('aria-expanded', 'true')
    await expect(recapRows(page)).toHaveCount(30)
    await expect(recapRows(page).first()).toContainText('01 Mar 2026')
    await expect(recapRows(page).nth(29)).toContainText('30 Mar 2026')

    // Halaman tetap satu kolom tanpa gulir mendatar; yang menggulir adalah
    // TABEL di dalam kartunya sendiri.
    await expectNoHorizontalPageScroll(page)
    await expectCardScrollsInsideItself(page, '[data-testid="daily-recap"] .detail-table-wrap')

    // Ditutup lagi, lalu dibuka lagi.
    await recapToggle(page).click()
    await expect(recapRows(page)).toHaveCount(0)
    await recapToggle(page).click()
    await expect(recapRows(page)).toHaveCount(30)

    // ASERSI INTINYA: tidak ada satu pun permintaan jaringan baru — dibaca
    // dari DUA rekaman yang berdiri sendiri (handler route dan
    // page.on('request')).
    expect(api.hits.summary).toBe(summaryHits)
    expect(api.requested.length).toBe(requestedBefore)
    expect(api.requested.filter((url) => url.includes('/summary'))).toHaveLength(1)
    expect(api.hits.total).toBe(api.hits.units + api.hits.periods + api.hits.summary + api.hits.export)

    // Sasaran sentuh tombol buka/tutup memenuhi ukuran jari.
    const toggleBox = await recapToggle(page).boundingBox()
    expect(toggleBox).not.toBeNull()
    expect(toggleBox!.height).toBeGreaterThanOrEqual(44)
  })

  // Scenario 17: "layar hanya membaca, tanpa aksi tulis"
  test('layar hanya membaca — dari angka utama sampai rekap harian tidak ada kontrol pengubah data', async ({
    page,
  }) => {
    await login(page)
    const api = await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')
    await recapToggle(page).click()
    await page.getByTestId('daily-recap').scrollIntoViewIfNeeded()

    await expect(page.locator('main.laporan-br-view input')).toHaveCount(0)
    await expect(page.locator('main.laporan-br-view textarea')).toHaveCount(0)
    await expect(page.locator('main.laporan-br-view form')).toHaveCount(0)
    await expect(page.getByRole('button', { name: /simpan|hapus|ubah data|tambah/i })).toHaveCount(0)

    // Satu-satunya pemilih adalah Periode (peran terikat mill).
    await expect(page.locator('main.laporan-br-view select')).toHaveCount(1)

    // Seluruh permintaan yang dikirim halaman adalah GET laporan — tidak
    // ada satu pun verba tulis pada prefiks ini.
    expect(api.urls.length).toBeGreaterThan(0)
    expect(api.urls.every((url) => url.includes('boiler-room-reports'))).toBe(true)
  })

  // Scenario 18: "angka ponsel sama persis dengan laporan versi web"
  test('angka sama dengan laporan web — nilai apa adanya dari respons, lewat path /api/boiler-room-reports yang sama', async ({
    page,
  }) => {
    await login(page)
    const api = await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // coverage_percent apa adanya (6,3), BUKAN 100 × 9 / 144 = 6,25.
    await expect(page.getByTestId('coverage-percent')).toHaveText('6,3%')
    // Rata-rata apa adanya, bukan (min + max) / 2 = 22,55.
    await expect(page.getByTestId('metric-steam-pressure-avg')).toHaveText('21,7')
    // Ekstrem dari PEMBACAAN MENTAH — 26,9 walau rata-rata harian tertinggi
    // 42,1 — dan layar mengatakan keduanya tidak dapat direkonsiliasi.
    await expect(page.getByTestId('metric-steam-pressure-max')).toHaveText('26,9')
    await expect(page.getByTestId('raw-extremes-note')).toContainText('pembacaan mentah')
    await expect(page.getByTestId('raw-extremes-note')).toContainText('tidak dapat dicocokkan')
    // Perawatan apa adanya — bukan jumlah kolom daily (3).
    await expect(page.getByTestId('maintenance-blowdown-executed')).toHaveText('5')

    await recapToggle(page).click()
    // Blok total apa adanya (412 pembacaan / 11 hari), meski bertentangan
    // dengan jumlah barisnya sendiri (3).
    await expect(page.getByTestId('daily-recap-total')).toContainText('412')
    await expect(page.getByTestId('daily-recap-total')).toContainText('11')
    await expect(recapRows(page)).toHaveCount(3)

    // Tidak ada endpoint mobile tersendiri: path-nya sama persis dengan
    // laporan versi web (screen-131).
    expect(api.urls.length).toBeGreaterThan(0)
    expect(api.urls.every((url) => url.includes('/api/boiler-room-reports/'))).toBe(true)
  })

  // Scenario 19: "periode yang tidak mencakup Boiler Room tidak ditawarkan"
  test('pemilih Periode — hanya Boiler Room dan periode semua jenis stasiun yang terbaca', async ({ page }) => {
    await login(page)
    // Server memang tidak mengembalikan periode jenis lain (itu diuji di
    // backend); yang dibuktikan di sini adalah layar tidak menambahkan dan
    // tidak membuang apa pun dari daftar yang diterimanya.
    await stubApi(page, { periods: [PERIOD_BR, PERIOD_ALL_TYPES] })
    await openReport(page)

    const options = page.getByTestId('period-select').locator('option')
    await expect(options).toHaveCount(3)
    await expect(options.nth(1)).toHaveText('Boiler Room — Periode Maret 2026')
    await expect(options.nth(2)).toHaveText('Semua Jenis Stasiun — Periode Semua Stasiun Maret 2026')
    await expect(page.getByTestId('period-select')).not.toContainText('Sterilizer')
    await expect(page.getByTestId('period-select')).not.toContainText('Cages & Tracks')
  })

  // Scenario 20: "rentang periode inklusif di kedua ujung"
  test('rentang inklusif — tanggal mulai dan tanggal akhir terbaca pada rekap dan ikut membentuk angka utama', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Tren harian memuat kedua ujung rentang.
    await expect(page.getByTestId('daily-trend-steam-pressure')).toContainText('01')
    await expect(page.getByTestId('daily-trend-steam-pressure')).toContainText('06')

    await recapToggle(page).click()

    const rows = recapRows(page)
    await expect(rows).toHaveCount(3)
    await expect(rows.nth(0)).toContainText('01 Mar 2026')
    await expect(rows.nth(2)).toContainText('06 Mar 2026')

    // Ujung rentang tidak dipangkas dan tidak ada tanggal di luar rentang.
    await expect(page.getByTestId('daily-recap')).not.toContainText('28 Feb 2026')
    await expect(page.getByTestId('daily-recap')).not.toContainText('07 Mar 2026')
    // Dan keduanya ikut membentuk angka utama (nilai server apa adanya).
    await expect(page.getByTestId('metric-steam-pressure-count')).toContainText('300')
  })

  // Scenario 21: "setiap metrik punya penyebutnya sendiri"
  test('penyebut per metrik — rata-rata yang jarang diisi tidak mengempis, jumlah pembacaan terbaca di sebelahnya', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metric-steam-pressure-avg')).toHaveText('21,7')
    await expect(page.getByTestId('metric-steam-pressure-count')).toContainText('300')
    // Metrik yang jauh lebih jarang diisi tetap menampilkan rata-ratanya
    // sendiri — tidak mengempis oleh penyebut metrik lain.
    await expect(page.getByTestId('metric-water-ph-avg')).toHaveText('10,4')
    await expect(page.getByTestId('metric-water-ph-count')).toContainText('3')
    await expect(page.getByTestId('metric-water-ph-count')).not.toContainText('300')

    // Tidak ada satu penyebut bersama: total.reading_rows (412) tidak
    // dipakai sebagai penyebut metrik mana pun.
    for (const testid of METRIC_TESTIDS) {
      await expect(page.getByTestId(`metric-${testid}-count`)).not.toContainText('412')
    }
  })

  // Scenario 22: "jumlah pembacaan ditampilkan berdampingan dengan angkanya"
  test('kesembilan kartu metrik — jumlah pembacaan terbaca berdampingan dengan setiap angka', async ({ page }) => {
    await login(page)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    const expectedCount: Record<string, string> = {
      'steam-pressure': '300',
      'steam-temp': '287',
      'water-tds': '265',
      'water-ph': '3',
      'exhaust-gas-temp': '240',
      'feed-water-temp': '198',
      'feed-water-tank-level': '176',
      'boiler-water-level': '154',
      'dust-collector-dp': '121',
    }

    await expect(page.locator('[data-testid="metric-cards"] .metric-card')).toHaveCount(9)

    for (const testid of METRIC_TESTIDS) {
      await expect(page.getByTestId(`metric-${testid}`)).toBeVisible()
      await expect(page.getByTestId(`metric-${testid}-avg`)).toBeVisible()
      await expect(page.getByTestId(`metric-${testid}-min`)).toBeVisible()
      await expect(page.getByTestId(`metric-${testid}-max`)).toBeVisible()
      // Tidak ada kartu yang merender angka TANPA penyebutnya.
      await expect(page.getByTestId(`metric-${testid}-count`)).toContainText(expectedCount[testid])
    }
  })

  // Scenario 23: "laju bahan bakar dan beban fan tidak pernah dirata-rata"
  test('laju bahan bakar dan beban fan — tidak jadi rata-rata maupun grafik di layar, tetap teks apa adanya di CSV', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Tidak ada kartu metrik maupun seri grafik untuk ketiganya.
    await expect(page.locator('[data-testid="metric-cards"] .metric-card')).toHaveCount(9)
    await expect(page.locator('main.laporan-br-view')).not.toContainText(/bahan bakar/i)
    await expect(page.locator('main.laporan-br-view')).not.toContainText(/id fan/i)
    await expect(page.locator('main.laporan-br-view')).not.toContainText(/sa fan/i)
    await expect(page.locator('[data-testid^="daily-trend-"]')).toHaveCount(2)

    // Pada berkas CSV ketiganya muncul apa adanya sebagai TEKS — isinya
    // dibentuk server, bukan disusun ulang dari angka di layar.
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByTestId('export-button').click(),
    ])

    const stream = await download.createReadStream()
    const chunks: Buffer[] = []
    for await (const chunk of stream) {
      chunks.push(Buffer.from(chunk))
    }
    const csv = Buffer.concat(chunks).toString('utf-8')

    expect(csv).toContain('Laju Bahan Bakar')
    expect(csv).toContain('Beban ID Fan')
    expect(csv).toContain('Beban SA Fan')
    expect(csv).toContain('45 Hz')
    expect(csv).toContain('80 %')
    expect(csv).toContain('12 A')
  })

  // Scenario 24: "tidak ada penandaan nilai di luar batas"
  test('nilai menyimpang jauh — tampil apa adanya tanpa penandaan di luar batas di mana pun', async ({ page }) => {
    await login(page)
    await stubApi(page, {
      summary: makeSummary({
        metrics: makeMetrics({
          steam_pressure_bar: { min: 0.4, avg: 21.7, max: 98.6, reading_count: 300 },
          water_ph: { min: 2.1, avg: 10.4, max: 14.0, reading_count: 3 },
        }),
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Terendah, rata-rata, tertinggi tampil apa adanya.
    await expect(page.getByTestId('metric-steam-pressure-min')).toHaveText('0,4')
    await expect(page.getByTestId('metric-steam-pressure-avg')).toHaveText('21,7')
    await expect(page.getByTestId('metric-steam-pressure-max')).toHaveText('98,6')
    await expect(page.getByTestId('metric-water-ph-max')).toHaveText('14,0')

    // Tidak ada kelas penanda, ikon peringatan, atau pewarnaan bersyarat —
    // Boiler Room tidak punya master target operasional.
    const markup = await page.locator('[data-testid="metric-cards"]').innerHTML()
    expect(markup).not.toMatch(/danger|warning|alert|critical|out-of-range|over-limit|threshold/i)
    await expect(page.locator('[data-testid="metric-cards"] svg')).toHaveCount(0)
    await expect(page.locator('[data-testid="by-unit-card"] svg')).toHaveCount(0)

    // Warna teks nilai seragam di seluruh kartu: tidak ada satu kartu pun
    // yang diwarnai berbeda karena angkanya menyimpang.
    const colors = await page
      .locator('[data-testid="metric-cards"] .metric-value')
      .evaluateAll((elements) => elements.map((element) => getComputedStyle(element).color))
    expect(new Set(colors).size).toBe(1)
  })

  // Scenario 25: "tata letak satu kolom pada layar ponsel"
  test('tata letak ponsel — grafik dan tabel menggulir di dalam kartunya, halaman tidak pernah menggulir mendatar', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page, { summary: makeSummary({ daily: THIRTY_DAYS }) })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // 30 kolom tanggal jauh lebih lebar daripada 390px — dan tetap
    // menggulir DI DALAM kartunya.
    await expectCardScrollsInsideItself(page, '[data-testid="daily-trend-steam-pressure"] .chart-scroll')
    await expectCardScrollsInsideItself(page, '[data-testid="daily-trend-steam-temp"] .chart-scroll')
    await expectCardScrollsInsideItself(page, '[data-testid="by-unit-card"] .detail-table-wrap')

    await recapToggle(page).click()
    await expectCardScrollsInsideItself(page, '[data-testid="daily-recap"] .detail-table-wrap')

    // Meski keempatnya lebih lebar dari layar, HALAMAN tidak pernah
    // menggulir mendatar — di puncak maupun setelah digulir ke dasar.
    await expectNoHorizontalPageScroll(page)
    await expectSingleColumn(page)
    await page.getByTestId('back-button').scrollIntoViewIfNeeded()
    await expectNoHorizontalPageScroll(page)

    // Sasaran sentuh pada kendali utama.
    await expectTouchTargets(page, [
      'period-select',
      'export-button',
      'back-button',
      'hamburger-button',
      'daily-recap-toggle',
    ])
  })

  // Scenario 26: "mengunduh rincian pembacaan per slot waktu sebagai CSV"
  test('ekspor CSV — berkas terunduh dari endpoint yang sama dan tidak ada data stasiun yang berubah', async ({
    page,
  }) => {
    await login(page)
    const api = await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByTestId('export-button').click(),
    ])

    expect(download.suggestedFilename()).toMatch(/^laporan-boiler-room_.*\.csv$/)
    expect(api.hits.export).toBe(1)
    // Isi CSV dibentuk SERVER: satu baris per slot waktu, kolom konteks
    // record diulang, label kolom mengikuti layar Detail.
    expect(api.urls.some((url) => url.includes('/export') && url.includes('format=csv'))).toBe(true)

    // Ekspor tidak menulis apa pun: seluruh permintaan adalah GET laporan.
    expect(api.urls.every((url) => url.includes('boiler-room-reports'))).toBe(true)
  })
})
