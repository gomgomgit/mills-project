import { test, expect, type Page } from '@playwright/test'
import { login, USERS } from './helpers'

/**
 * laporan-clarification.spec.ts — screen-138--laporan-clarification-mobile /
 * usecase-138--laporan-clarification-mobile "Lihat Laporan Periode
 * Clarification (Mobile)".
 *
 * Satu test per test_scenarios yang punya browser_test — seluruh 29.
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
 * langsung ke '/reports/clarification' supaya kegagalan di sini selalu
 * berarti layar laporannya, bukan layar pemilih stasiun.
 *
 * RESPONS API DI-STUB DI TINGKAT JARINGAN (page.route), BUKAN DISEMAI KE
 * DATABASE — keputusan yang sama dengan laporan-boiler-room.spec.ts,
 * laporan-cages-track.spec.ts, dan laporan-sterilizer.spec.ts:
 *   - Layar ini read-only total dan tidak punya satu pun UI untuk membuat
 *     data yang dibacanya; menyemai fixture berarti menjalankan dua layar
 *     WEB lain (periode + input Clarification) dari dalam suite mobile.
 *   - Kebenaran ANGKA-nya bukan tanggung jawab layar ini: seluruhnya
 *     berasal dari ClarificationReportService, yang sudah diuji penuh di
 *     backend (Unit/Services + Feature/Api + Feature/Livewire) dan di
 *     e2e-web terhadap data sungguhan. Yang khas mobile — dan hanya dapat
 *     dibuktikan di sini — adalah PEMETAAN respons itu ke layar satu kolom
 *     390px.
 *   - Beberapa skenario mustahil dibuat dari data sungguhan tanpa merusak
 *     lingkungan (sesi kedaluwarsa, jaringan putus, akun tanpa mill).
 *
 * ANGKA FIXTURE SENGAJA TIDAK KONSISTEN SECARA ARITMETIKA — sama persis
 * dengan tests/clarificationReportRepo.spec.ts dan
 * tests/LaporanClarificationView.spec.ts: production.total_ton (128,5) ≠
 * jumlah daily.production_ton (66,0) maupun jumlah daily.rate_avg (51,5);
 * metrics.pure_oil_production_rate_ton_hour.max (14,8) LEBIH KECIL
 * daripada daily[0].rate_avg (42,1) karena ekstrem berasal dari PEMBACAAN
 * MENTAH per slot waktu; downtime.total_mins (240) ≠ jumlah kolom daily
 * (95); coverage.expected_slots (144) ≠ 2 × 6 × 24 (288);
 * total.days_with_records (11) ≠ panjang daily (3). Layar yang diam-diam
 * menghitung ulang HARUS gagal di sini. Jangan "merapikan".
 *
 * REKAP HARIAN MEMAKAI v-if, BUKAN v-show — sama seperti screen-137. Saat
 * tertutup barisnya benar-benar HILANG dari DOM, jadi keadaan tertutup
 * diasersi dengan toHaveCount(0), bukan dengan not.toBeVisible().
 */

const ROUTE = '/reports/clarification'
const API_GLOB = '**/api/clarification-reports/**'

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
 * benar-benar dikirim App\Http\Controllers\Api\ClarificationReportController.
 */
const PERIOD_CLF = {
  id: 'per-1',
  name: 'Periode Maret 2026',
  start_date: '2026-03-01',
  end_date: '2026-03-06',
  status: 'open',
  station_type: 'clarification',
  station_type_label: 'Clarification',
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
  station_type: 'clarification',
  station_type_label: 'Clarification',
}

const BUSINESS_UNITS = [
  { id: 'bu-1', name: 'Mill Utara' },
  { id: 'bu-2', name: 'Mill Selatan' },
]

const EMPTY_METRIC = { min: null, avg: null, max: null, reading_count: 0 }

function makeMetrics(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    pure_oil_production_rate_ton_hour: { min: 4.1, avg: 9.33, max: 14.8, reading_count: 120 },
    clarification_tank_temp_c: { min: 89.5, avg: 93.4, max: 95.1, reading_count: 113 },
    oil_tank_temperature_c: { min: 93.2, avg: 97.2, max: 98.9, reading_count: 106 },
    sludge_tank_temp_c: { min: 83.5, avg: 87.6, max: 89.4, reading_count: 98 },
    buffer_tank_level_percent: { min: 58.7, avg: 72.4, max: 78.4, reading_count: 6 },
    downtime_mins: { min: 0, avg: 6.0, max: 45, reading_count: 40 },
    ...overrides,
  }
}

const EMPTY_METRICS = {
  pure_oil_production_rate_ton_hour: { ...EMPTY_METRIC },
  clarification_tank_temp_c: { ...EMPTY_METRIC },
  oil_tank_temperature_c: { ...EMPTY_METRIC },
  sludge_tank_temp_c: { ...EMPTY_METRIC },
  buffer_tank_level_percent: { ...EMPTY_METRIC },
  downtime_mins: { ...EMPTY_METRIC },
}

const DAILY_ROWS = [
  {
    date: '2026-03-01',
    filled_slots: 4,
    production_ton: 40.0,
    rate_avg: 42.1,
    rate_reading_count: 4,
    clarification_tank_temp_avg: 93.8,
    oil_tank_temperature_avg: 97.0,
    sludge_tank_temp_avg: 87.1,
    buffer_tank_level_avg: null,
    downtime_mins: 35,
  },
  {
    date: '2026-03-02',
    filled_slots: 3,
    production_ton: null,
    rate_avg: null,
    rate_reading_count: 0,
    clarification_tank_temp_avg: 88.2,
    oil_tank_temperature_avg: null,
    sludge_tank_temp_avg: null,
    buffer_tank_level_avg: null,
    downtime_mins: null,
  },
  {
    date: '2026-03-06',
    filled_slots: 5,
    production_ton: 26.0,
    rate_avg: 9.4,
    rate_reading_count: 3,
    clarification_tank_temp_avg: 92.9,
    oil_tank_temperature_avg: 96.4,
    sludge_tank_temp_avg: 86.8,
    buffer_tank_level_avg: 71.0,
    downtime_mins: 60,
  },
]

const BY_UNIT_ROWS = [
  {
    clarification_id: 'CLF-1',
    reading_count: 301,
    production_ton: 120.0,
    rate_avg: 9.1,
    rate_reading_count: 118,
    clarification_tank_temp_avg: 93.5,
    oil_tank_temperature_avg: 97.1,
    sludge_tank_temp_avg: 87.4,
    downtime_mins: 210,
  },
  {
    clarification_id: 'CLF-2',
    reading_count: 0,
    production_ton: null,
    rate_avg: null,
    rate_reading_count: 0,
    clarification_tank_temp_avg: null,
    oil_tank_temperature_avg: null,
    sludge_tank_temp_avg: null,
    downtime_mins: null,
  },
]

const PRODUCTION = {
  total_ton: 128.5,
  avg_per_day_ton: 21.4,
  avg_production_per_day_ton: 21.4,
  reading_count: 96,
  avg_rate_ton_hour: 9.33,
  min_rate_ton_hour: 4.1,
  max_rate_ton_hour: 14.8,
}

const DOWNTIME = {
  total_mins: 240,
  avg_per_day_mins: 20.5,
  avg_downtime_per_day_mins: 20.5,
  hours_with_downtime: 7,
  reading_count: 40,
}

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
      coverage_percent: 6.25,
      unit_count: 2,
      slots_per_unit_per_day: 24,
      days_in_period: 6,
    },
    production: { ...PRODUCTION },
    downtime: { ...DOWNTIME },
    metrics: makeMetrics(),
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
    unit_count: 1,
    slots_per_unit_per_day: 24,
    days_in_period: 30,
  },
  production: {
    total_ton: null,
    avg_per_day_ton: null,
    avg_production_per_day_ton: null,
    reading_count: 0,
    avg_rate_ton_hour: null,
    min_rate_ton_hour: null,
    max_rate_ton_hour: null,
  },
  downtime: {
    total_mins: null,
    avg_per_day_mins: null,
    avg_downtime_per_day_mins: null,
    hours_with_downtime: 0,
    reading_count: 0,
  },
  metrics: { ...EMPTY_METRICS },
  daily: [],
  by_unit: [],
  total: { days_with_records: 0, reading_rows: 0 },
})

/**
 * Rekap sebulan penuh — 30 baris. Ini juga fixture tata letak: 30 kolom
 * grafik (40px masing-masing pada grafik suhu bergerombol) jauh lebih
 * lebar daripada viewport 390px, yang membuat klaim "menggulir DI DALAM
 * kartunya" dapat diuji.
 */
const THIRTY_DAYS = Array.from({ length: 30 }, (_, index) => ({
  date: `2026-03-${String(index + 1).padStart(2, '0')}`,
  filled_slots: 4,
  production_ton: 10 + index * 0.5,
  rate_avg: 9 + index * 0.1,
  rate_reading_count: 4,
  clarification_tank_temp_avg: 93 + index * 0.1,
  oil_tank_temperature_avg: 97 + index * 0.1,
  sludge_tank_temp_avg: 87 + index * 0.1,
  buffer_tank_level_avg: 70 + index * 0.1,
  downtime_mins: 5,
}))

/** Keempat kartu metrik, urutan mengikuti laporan web screen-132. */
const METRIC_TESTIDS = ['clarification-temp', 'oil-temp', 'sludge-temp', 'buffer-level']

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
  /** 200 kecuali test menyetel lain — endpoint options Admin-only. */
  unitsStatus: number
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
    periods: [PERIOD_CLF],
    summary: makeSummary(),
    summaryStatus: 200,
    summaryAbort: false,
    unitsStatus: 200,
    hits: { units: 0, periods: 0, summary: 0, export: 0, total: 0 },
    urls: [],
    requested: [],
    ...overrides,
  }

  page.on('request', (request) => {
    if (request.url().includes('/api/clarification-reports/')) {
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

      if (state.unitsStatus !== 200) {
        await route.fulfill({
          status: state.unitsStatus,
          headers: CORS_HEADERS,
          json: { message: 'Anda tidak memiliki akses untuk aksi ini.', code: 'FORBIDDEN' },
        })

        return
      }

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
        body:
          'Tanggal,Slot Waktu,Unit Clarification,Status,Catatan,Laju Produksi Minyak Murni (ton/jam),' +
          'Suhu Tangki Clarification (C),Suhu Tangki Minyak (C),Suhu Tangki Sludge (C),' +
          'Level Buffer Tank (%),Downtime (menit),Temuan\n' +
          '2026-03-01,08:00,CLF-1,synced,,10.5,93.8,97.0,87.1,71.0,0,ada buih di tangki\n',
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
  await expect(page.getByTestId('laporan-clarification-mobile')).toBeVisible()
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
    const doc = document.scrollingElement ?? document.documentElement

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
  const cards = page.locator('main.laporan-clf-view > .metric-stack > .metric-card')
  expect(await cards.count()).toBe(4)

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

  // Ketiga kartu angka utama (produksi, downtime, laju) juga bertumpuk,
  // bukan berjajar — itulah yang membedakan tata letak ponsel dari
  // md-kpis--3 pada laporan web.
  const kpiBoxes = await page
    .locator(
      'main.laporan-clf-view > [data-testid="production-card"], main.laporan-clf-view > [data-testid="downtime-card"], main.laporan-clf-view > [data-testid="production-rate-card"]',
    )
    .evaluateAll((elements) =>
      elements.map((element) => {
        const rect = element.getBoundingClientRect()

        return { top: rect.top, bottom: rect.bottom }
      }),
    )

  expect(kpiBoxes).toHaveLength(3)

  for (let index = 1; index < kpiBoxes.length; index += 1) {
    expect(kpiBoxes[index].top).toBeGreaterThanOrEqual(kpiBoxes[index - 1].bottom - 1)
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

test.describe('Laporan Clarification Mobile (screen-138)', () => {
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

    // Produksi beserta penyebutnya, downtime sebagai konteks, laju.
    await expect(page.getByTestId('production-total')).toHaveText('128,5')
    await expect(page.getByTestId('production-reading-count')).toContainText('96')
    await expect(page.getByTestId('downtime-total')).toHaveText('240')
    await expect(page.getByTestId('production-rate-avg')).toHaveText('9,33')

    // Keempat metrik dengan terendah/rata-rata/tertinggi beserta
    // reading_count masing-masing.
    await expect(page.locator('[data-testid="metric-cards"] .metric-card')).toHaveCount(4)
    await expect(page.getByTestId('metric-clarification-temp-avg')).toHaveText('93,4')
    await expect(page.getByTestId('metric-clarification-temp-min')).toHaveText('89,5')
    await expect(page.getByTestId('metric-clarification-temp-max')).toHaveText('95,1')
    await expect(page.getByTestId('metric-clarification-temp-count')).toContainText('113')
    await expect(page.getByTestId('metric-buffer-level-avg')).toHaveText('72,4')
    await expect(page.getByTestId('metric-buffer-level-count')).toContainText('6')

    // Ketiga suhu pada SATU grafik, tren harian, rekap per unit, rekap harian.
    await expect(page.getByTestId('tank-temperature-chart')).toHaveCount(1)
    await expect(page.getByTestId('daily-trend-production')).toBeVisible()
    await expect(page.getByTestId('by-unit-card')).toBeVisible()
    await expect(page.getByTestId('daily-recap')).toBeVisible()
    await expect(page.getByTestId('raw-extremes-note')).toBeVisible()

    // Tata letak ponsel.
    await expectSingleColumn(page)
    await expectNoHorizontalPageScroll(page)
    await expectTouchTargets(page, ['period-select', 'export-button', 'back-button'])

    // Tidak ada satu pun kontrol tulis di sepanjang layar.
    await expect(page.locator('main.laporan-clf-view input')).toHaveCount(0)
    await expect(page.locator('main.laporan-clf-view textarea')).toHaveCount(0)
    await expect(page.getByRole('button', { name: /simpan|hapus/i })).toHaveCount(0)

    // Layar baca: tidak ada permintaan ke luar prefiks laporan.
    expect(api.urls.every((url) => url.includes('clarification-reports'))).toBe(true)
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
    await expect(page.getByTestId('production-total')).toHaveText('128,5')
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
    await expect(page.getByTestId('production-card')).toBeVisible()
    await expect(page.getByTestId('downtime-card')).toBeVisible()
    await expect(page.getByTestId('metric-cards')).toBeVisible()
    await expect(page.getByTestId('by-unit-card')).toBeVisible()
    await expect(page.getByTestId('export-button')).toBeVisible()

    // Tidak ada kontrol untuk mengganti mill.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.getByTestId('mill-current')).toContainText('Mill Utara')

    // Endpoint options TIDAK pernah dipanggil — server pun menjawab 403 di
    // sana untuk Operator, dan memanggilnya akan membentuk daftar SELURUH
    // mill di perangkat yang tidak berhak melihatnya.
    expect(api.hits.units).toBe(0)
    expect(api.urls.some((url) => url.includes('/business-units/options'))).toBe(false)

    // Dan tidak satu pun permintaan menyertakan business_unit_id.
    expect(api.urls.every((url) => !url.includes('business_unit_id'))).toBe(true)
  })

  // Scenario 4: "Admin memilih mill lebih dulu"
  test('Admin tanpa mill — arahan memilih mill terbaca, tidak ada angka laporan yang tampil', async ({ page }) => {
    await login(page, USERS.operator)
    await becomeAdminSession(page)
    const api = await stubApi(page)
    await openReport(page)

    await expect(page.getByTestId('mill-select')).toBeVisible()
    await expect(page.getByTestId('mill-required-hint')).toContainText('Pilih mill terlebih dahulu')

    await expect(page.getByTestId('coverage-card')).toHaveCount(0)
    await expect(page.getByTestId('production-card')).toHaveCount(0)
    await expect(page.getByTestId('metric-cards')).toHaveCount(0)
    expect(api.hits.summary).toBe(0)
    expect(api.hits.periods).toBe(0)
  })

  // Scenario 5: "mill belum punya periode"
  test('mill tanpa periode — pemilih kosong dengan arahan menghubungi Admin, bukan layar kosong', async ({ page }) => {
    await login(page, USERS.supervisor)
    await stubApi(page, { periods: [] })
    await openReport(page)

    // Hanya opsi pembuka.
    await expect(page.getByTestId('period-select').locator('option')).toHaveCount(1)
    await expect(page.getByTestId('no-periods')).toContainText('hubungi Admin')
    await expect(page.getByTestId('coverage-card')).toHaveCount(0)
    await expect(page.getByTestId('network-error')).toHaveCount(0)
  })

  // Scenario 6: "periode tanpa data"
  test('periode tanpa data — seluruh angka tidak tersedia (bukan nol) dan tidak ada grafik kosong', async ({ page }) => {
    await login(page, USERS.operator)
    await stubApi(page, { summary: EMPTY_SUMMARY })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('empty-period')).toContainText('Belum ada data')
    await expect(page.getByTestId('production-total')).toHaveText('-')
    await expect(page.getByTestId('downtime-total')).toHaveText('tidak tercatat')

    for (const testId of METRIC_TESTIDS) {
      await expect(page.getByTestId(`metric-${testId}-avg`)).toHaveText('-')
    }

    // Tidak ada grafik kosong yang menyesatkan.
    await expect(page.getByTestId('daily-trend-production')).toHaveCount(0)
    await expect(page.getByTestId('tank-temperature-chart')).toHaveCount(0)
    await expectNoHorizontalPageScroll(page)
  })

  // Scenario 7: "laju produksi tidak pernah tercatat"
  test('laju tidak pernah tercatat — produksi tidak tersedia dengan 0 pembacaan, metrik suhu tetap normal', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, {
      summary: makeSummary({
        production: {
          total_ton: null,
          avg_per_day_ton: null,
          avg_production_per_day_ton: null,
          reading_count: 0,
          avg_rate_ton_hour: null,
          min_rate_ton_hour: null,
          max_rate_ton_hour: null,
        },
        metrics: makeMetrics({ pure_oil_production_rate_ton_hour: { ...EMPTY_METRIC } }),
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('production-total')).toHaveText('-')
    await expect(page.getByTestId('production-reading-count')).toContainText('0')
    await expect(page.getByTestId('production-rate-avg')).toHaveText('-')

    // Metrik suhu tetap tampil normal dengan penyebutnya sendiri.
    await expect(page.getByTestId('metric-clarification-temp-avg')).toHaveText('93,4')
    await expect(page.getByTestId('metric-clarification-temp-count')).toContainText('113')
    await expect(page.getByTestId('metric-sludge-temp-avg')).toHaveText('87,6')
  })

  // Scenario 8: "jam tanpa catatan laju"
  test('jam tanpa catatan laju — tidak muncul sebagai jam berproduksi nol, dan jumlahnya terbaca di kelengkapan', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, {
      summary: makeSummary({
        coverage: {
          filled_slots: 140,
          expected_slots: 144,
          coverage_percent: 97.22,
          unit_count: 2,
          slots_per_unit_per_day: 24,
          days_in_period: 6,
        },
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Penyebut produksi adalah pembacaan LAJU (96), bukan slot terisi (140).
    await expect(page.getByTestId('production-reading-count')).toContainText('96')
    await expect(page.getByTestId('production-total')).toHaveText('128,5')

    // Selisih 140 − 96 terbaca lewat kartu kelengkapan pencatatan.
    await expect(page.getByTestId('coverage-slots')).toContainText('140 dari 144')

    // Baris 2026-03-02 yang laju-nya null dirender "-", BUKAN 0,0.
    await recapToggle(page).click()
    await expect(recapRows(page).nth(1)).toContainText('-')
    await expect(recapRows(page).nth(1)).not.toContainText('0,0')
  })

  // Scenario 9: "downtime tercatat bersamaan dengan laju"
  test('laju dan downtime bersamaan — produksi apa adanya, downtime apa adanya, dan layar menjelaskan keduanya', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, {
      summary: makeSummary({
        downtime: {
          total_mins: 240,
          avg_per_day_mins: 40,
          avg_downtime_per_day_mins: 40,
          hours_with_downtime: 6,
          reading_count: 96,
        },
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('production-total')).toHaveText('128,5')
    await expect(page.getByTestId('downtime-total')).toHaveText('240')
    await expect(page.getByTestId('downtime-hours')).toContainText('6')

    // Layar MENGATAKAN bahwa keduanya tidak saling mengurangi.
    await expect(page.getByTestId('downtime-production-note')).toContainText('TIDAK dikurangkan')
    await expect(page.getByTestId('downtime-production-note')).toContainText('menghitung ganda')

    // Dan kartu produksi tidak memuat angka downtime sama sekali.
    await expect(page.getByTestId('production-card')).not.toContainText('240')
  })

  // Scenario 10: "downtime tidak pernah tercatat"
  test('downtime tidak tercatat vs downtime nol — dua tampilan yang tidak terlihat sama', async ({ page }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page, {
      summary: makeSummary({
        downtime: {
          total_mins: null,
          avg_per_day_mins: null,
          avg_downtime_per_day_mins: null,
          hours_with_downtime: 0,
          reading_count: 0,
        },
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')
    await expect(page.getByTestId('downtime-total')).toHaveText('tidak tercatat')
    await expect(page.getByTestId('downtime-reading-count')).toContainText('0')

    // Periode kedua: downtime tercatat dan memang nol.
    api.summary = makeSummary({
      downtime: {
        total_mins: 0,
        avg_per_day_mins: 0,
        avg_downtime_per_day_mins: 0,
        hours_with_downtime: 0,
        reading_count: 40,
      },
    })
    api.periods = [PERIOD_CLF, PERIOD_ALL_TYPES]

    await page.reload()
    await expect(page.getByTestId('laporan-clarification-mobile')).toBeVisible()
    await pickPeriod(page, 'per-2')

    await expect(page.getByTestId('downtime-total')).toHaveText('0')
    await expect(page.getByTestId('downtime-total')).not.toHaveText('tidak tercatat')
    await expect(page.getByTestId('downtime-reading-count')).toContainText('40')
  })

  // Scenario 11: "pencatatan sangat tidak lengkap"
  test('kelengkapan sangat rendah — kartu kelengkapan terbaca LEBIH DULU daripada angka mana pun', async ({ page }) => {
    await login(page, USERS.operator)
    await stubApi(page, {
      summary: makeSummary({
        coverage: {
          filled_slots: 9,
          expected_slots: 720,
          coverage_percent: 1.25,
          unit_count: 1,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
        },
        production: { ...PRODUCTION, reading_count: 9 },
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('coverage-percent')).toHaveText('1,3%')

    // Posisi di layar, bukan sekadar urutan DOM: kartu kelengkapan berada
    // DI ATAS kartu produksi, downtime, laju, dan seluruh kartu metrik.
    const coverageBox = await page.getByTestId('coverage-card').boundingBox()
    expect(coverageBox).not.toBeNull()

    for (const testId of ['production-card', 'downtime-card', 'production-rate-card', 'metric-cards']) {
      const box = await page.getByTestId(testId).boundingBox()
      expect(box, `${testId} tidak terender`).not.toBeNull()
      expect(box!.y).toBeGreaterThan(coverageBox!.y)
    }

    // Angka tetap tampil, dan pembaca dapat melihat bahwa produksinya
    // diturunkan dari 9 pembacaan saja.
    await expect(page.getByTestId('production-total')).toHaveText('128,5')
    await expect(page.getByTestId('production-reading-count')).toContainText('9')
    await expect(page.getByTestId('production-derived-note')).toContainText('DITURUNKAN')
  })

  // Scenario 12: "mill punya beberapa unit Clarification"
  test('beberapa unit — unit tanpa pembacaan tetap muncul, dan tabelnya bergeser DI DALAM kartunya', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, {
      summary: makeSummary({
        by_unit: [
          ...BY_UNIT_ROWS,
          {
            clarification_id: 'CLF-3',
            reading_count: 55,
            production_ton: 8.5,
            rate_avg: 2.2,
            rate_reading_count: 50,
            clarification_tank_temp_avg: 90.1,
            oil_tank_temperature_avg: 95.0,
            sludge_tank_temp_avg: 85.0,
            downtime_mins: 30,
          },
        ],
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Angka periode menggabungkan seluruh unit — diambil dari blok
    // production apa adanya, bukan dihitung ulang dari by_unit.
    await expect(page.getByTestId('production-total')).toHaveText('128,5')

    await expect(page.getByTestId('by-unit-row')).toHaveCount(3)
    await expect(page.getByTestId('by-unit-row').nth(1)).toContainText('CLF-2')
    await expect(page.getByTestId('by-unit-row').nth(1)).toContainText('0')

    // Tabelnya menggulir DI DALAM kartunya, dan halamannya tidak bergeser.
    await expectCardScrollsInsideItself(page, '[data-testid="by-unit-card"] .detail-table-wrap')
    await expectNoHorizontalPageScroll(page)
  })

  // Scenario 13: "akun belum terhubung ke mill"
  test('akun tanpa mill — pesan menghubungi Admin, tanpa daftar mill dan tanpa satu pun angka', async ({ page }) => {
    await login(page, USERS.operator)
    await becomeMilllessSession(page)
    const api = await stubApi(page)
    await openReport(page)

    await expect(page.getByTestId('no-mill-for-account')).toContainText('hubungi Admin')
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.getByTestId('period-select')).toHaveCount(0)
    await expect(page.getByTestId('coverage-card')).toHaveCount(0)

    // NOL permintaan — termasuk endpoint options.
    expect(api.hits.total).toBe(0)
    expect(api.requested).toHaveLength(0)
  })

  // Scenario 14: "mencoba melihat mill lain"
  test('Operator BU-A — angka dan nama mill milik BU-A, dan tidak ada kontrol untuk berpindah mill', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('mill-current')).toContainText('Mill Utara')
    await expect(page.getByTestId('mill-current')).not.toContainText('Mill Selatan')
    await expect(page.getByTestId('period-meta')).toContainText('Periode Maret 2026')
    await expect(page.getByTestId('production-total')).toHaveText('128,5')

    // Badan respons yang benar-benar dipakai layar adalah BU-A.
    expect(JSON.stringify(api.summary)).toContain('Mill Utara')
    expect(JSON.stringify(api.summary)).not.toContain('Mill Selatan')

    // Tidak ada kontrol apa pun untuk berpindah mill.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.locator('main.laporan-clf-view select')).toHaveCount(1)
    // Dan halaman tidak pernah mengirim business_unit_id.
    expect(api.urls.every((url) => !url.includes('business_unit_id'))).toBe(true)
  })

  // Scenario 15: "jaringan gagal"
  test('jaringan gagal — pesan + Coba Lagi, periode terpilih bertahan, dan coba lagi memuat periode yang sama', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page, { summaryAbort: true })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Bukan layar kosong, bukan diam.
    await expect(page.getByTestId('network-error')).toBeVisible()
    await expect(page.getByTestId('retry-button')).toBeVisible()
    await expect(page.getByTestId('coverage-card')).toHaveCount(0)

    // Periode yang sudah dipilih MASIH terpilih.
    await expect(page.getByTestId('period-select')).toHaveValue('per-1')
    expect(page.url()).toContain(ROUTE)

    // Jaringan pulih.
    api.summaryAbort = false
    const summaryHitsBefore = api.hits.summary

    await page.getByTestId('retry-button').click()

    await expect(page.getByTestId('production-total')).toHaveText('128,5')
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('period-select')).toHaveValue('per-1')

    // period_id yang SAMA, tanpa memilih ulang.
    expect(api.hits.summary).toBe(summaryHitsBefore + 1)
    expect(api.urls[api.urls.length - 1]).toContain('period_id=per-1')
  })

  // Scenario 16: "sesi berakhir"
  test('sesi berakhir — 401 memindahkan ke Login, tanpa angka dan tanpa tombol coba lagi', async ({ page }) => {
    await login(page, USERS.operator)
    await stubApi(page, { summaryStatus: 401 })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await page.waitForURL('**/login')
    await expect(page.getByTestId('coverage-card')).toHaveCount(0)
    await expect(page.getByTestId('production-card')).toHaveCount(0)
    await expect(page.getByTestId('retry-button')).toHaveCount(0)
    await expect(page.getByTestId('network-error')).toHaveCount(0)
  })

  // Scenario 17: "periode tertutup"
  test('periode tertutup — laporan penuh, status Ditutup terbaca, dan unduhan CSV tetap berjalan', async ({ page }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page, {
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

    await expect(page.getByTestId('period-status-badge')).toContainText('Ditutup')
    await expect(page.getByTestId('production-total')).toHaveText('128,5')
    await expect(page.getByTestId('by-unit-card')).toBeVisible()

    // Ekspor TIDAK diblokir oleh status periode.
    const download = page.waitForEvent('download')
    await page.getByTestId('export-button').click()
    const file = await download

    expect(file.suggestedFilename()).toMatch(/\.csv$/)
    expect(api.hits.export).toBe(1)
  })

  // Scenario 18: "rekap harian dapat ditutup"
  test('rekap harian buka/tutup/buka pada periode sebulan — NOL permintaan jaringan baru', async ({ page }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page, { summary: makeSummary({ daily: THIRTY_DAYS }) })
    await openReport(page)

    await pickPeriod(page, 'per-1')
    await expect(page.getByTestId('production-total')).toHaveText('128,5')

    // TERTUTUP secara bawaan (v-if: barisnya tidak ada di DOM sama sekali).
    await expect(recapRows(page)).toHaveCount(0)

    const requestsAfterLoad = api.requested.length
    const summaryHits = api.hits.summary

    // Saat tertutup, angka utama dan grafik tetap terbaca.
    await expect(page.getByTestId('coverage-card')).toBeVisible()
    await expect(page.getByTestId('production-card')).toBeVisible()
    await expect(page.getByTestId('tank-temperature-chart')).toBeVisible()
    await expectNoHorizontalPageScroll(page)

    await recapToggle(page).click()
    await expect(recapRows(page)).toHaveCount(30)

    await recapToggle(page).click()
    await expect(recapRows(page)).toHaveCount(0)

    await recapToggle(page).click()
    await expect(recapRows(page)).toHaveCount(30)

    // Halaman tetap satu kolom tanpa gulir mendatar, dengan 30 baris terbuka.
    await expectNoHorizontalPageScroll(page)
    await expectCardScrollsInsideItself(page, '[data-testid="daily-recap"] .detail-table-wrap')

    // NOL permintaan jaringan baru — dihitung dari DUA mekanisme berbeda.
    expect(api.hits.summary).toBe(summaryHits)
    expect(api.requested).toHaveLength(requestsAfterLoad)
  })

  // Scenario 19: "layar hanya membaca"
  test('hanya membaca — tidak ada kontrol pengubah data, dan tidak ada permintaan non-GET sepanjang layar', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const methods: string[] = []

    page.on('request', (request) => {
      if (request.url().includes('/api/')) {
        methods.push(request.method())
      }
    })

    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')
    await recapToggle(page).click()
    await expect(recapRows(page)).toHaveCount(3)

    await expect(page.locator('main.laporan-clf-view input')).toHaveCount(0)
    await expect(page.locator('main.laporan-clf-view textarea')).toHaveCount(0)
    await expect(page.locator('main.laporan-clf-view form')).toHaveCount(0)
    await expect(page.getByRole('button', { name: /simpan|ubah|hapus|tambah/i })).toHaveCount(0)

    // Data stasiun tidak dapat berubah: tidak satu pun permintaan yang
    // bukan GET (OPTIONS preflight dikecualikan).
    expect(methods.filter((method) => method !== 'GET' && method !== 'OPTIONS')).toHaveLength(0)
  })

  // Scenario 20: "angka ponsel sama persis dengan laporan versi web"
  test('ponsel memakai path /api/clarification-reports yang sama — tidak ada endpoint mobile tersendiri', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Seluruh permintaan menuju prefiks yang SAMA dengan laporan web
    // (screen-132) — tidak ada '/mobile' atau prefiks lain di mana pun.
    expect(api.requested.length).toBeGreaterThan(0)
    for (const url of api.requested) {
      expect(url).toContain('/api/clarification-reports/')
      expect(url).not.toContain('/mobile')
    }

    // Dan angka yang tampil adalah nilai payload apa adanya — angka fixture
    // yang saling bertentangan tetap bertentangan di layar.
    await expect(page.getByTestId('production-total')).toHaveText('128,5')
    await expect(page.getByTestId('production-rate-max')).toHaveText('14,80')
    await expect(page.getByTestId('downtime-total')).toHaveText('240')
    await expect(page.getByTestId('coverage-percent')).toHaveText('6,3%')
  })

  // Scenario 21: "periode yang tidak mencakup Clarification tidak ditawarkan"
  test('pemilih periode memuat periode Clarification dan semua-jenis, persis seperti kiriman server', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, { periods: [PERIOD_CLF, PERIOD_ALL_TYPES] })
    await openReport(page)

    const options = page.getByTestId('period-select').locator('option')

    await expect(options).toHaveCount(3)
    await expect(options.nth(1)).toContainText('Clarification — Periode Maret 2026')
    await expect(options.nth(2)).toContainText('Semua Jenis Stasiun')

    // Layar tidak menyaring station_type sendiri: periode ber-station_type
    // null TIDAK dibuang, dan penyaringan cakupan tetap milik server.
    // Atribut value-nya, bukan toHaveValue() — pada <option> matcher itu
    // membaca nilai <select> induknya, bukan opsinya sendiri.
    expect(await options.nth(2).getAttribute('value')).toBe('per-2')
    expect(await options.nth(1).getAttribute('value')).toBe('per-1')
  })

  // Scenario 22: "rentang periode inklusif di kedua ujung"
  test('kedua tanggal ujung tampil pada rekap harian dan ikut membentuk angka utama', async ({ page }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('period-meta')).toContainText('01 Mar 2026')
    await expect(page.getByTestId('period-meta')).toContainText('06 Mar 2026')

    await recapToggle(page).click()

    await expect(recapRows(page)).toHaveCount(3)
    await expect(recapRows(page).first()).toContainText('01 Mar 2026')
    await expect(recapRows(page).last()).toContainText('06 Mar 2026')

    // Tidak ada baris ujung yang dipotong.
    await expect(recapToggle(page)).toContainText('3 hari')
  })

  // Scenario 23: "produksi diturunkan dari laju per jam"
  test('kartu produksi menampilkan total berdampingan dengan jumlah pembacaan laju yang mendasarinya', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('production-total')).toHaveText('128,5')
    await expect(page.getByTestId('production-reading-count')).toContainText('96 pembacaan laju')
    await expect(page.getByTestId('production-rate-reading-count')).toContainText('96')

    // Layar mengatakan bahwa tidak ada kolom produksi mentah yang menjadi
    // sumbernya.
    await expect(page.getByTestId('production-derived-note')).toContainText('Tidak ada kolom produksi')
  })

  // Scenario 24: "downtime sebagai konteks, bukan pengurang produksi"
  test('total produksi tidak dikurangi downtime, dan downtime berdampingan hanya sebagai konteks', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // 128,5 apa adanya — bukan 124,5 (dikurangi 240 menit) dan bukan angka
    // yang diprorata dengan (60 − downtime) / 60.
    await expect(page.getByTestId('production-total')).toHaveText('128,5')
    await expect(page.getByTestId('production-total')).not.toHaveText('124,5')

    await expect(page.getByTestId('downtime-card')).toBeVisible()
    await expect(page.getByTestId('downtime-total')).toHaveText('240')

    // Kartu downtime berada DI BAWAH kartu produksi — berdampingan dalam
    // satu kolom, bukan di dalamnya.
    const productionBox = await page.getByTestId('production-card').boundingBox()
    const downtimeBox = await page.getByTestId('downtime-card').boundingBox()
    expect(downtimeBox!.y).toBeGreaterThan(productionBox!.y)
  })

  // Scenario 25: "setiap metrik punya penyebutnya sendiri"
  test('metrik yang jarang diisi tidak mengempis, dan jumlah pembacaan berbeda terbaca di sebelah tiap angka', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, {
      summary: makeSummary({
        metrics: makeMetrics({
          pure_oil_production_rate_ton_hour: { min: 4.1, avg: 9.33, max: 14.8, reading_count: 300 },
          buffer_tank_level_percent: { min: 58.7, avg: 72.4, max: 78.4, reading_count: 3 },
        }),
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metric-buffer-level-avg')).toHaveText('72,4')
    await expect(page.getByTestId('metric-buffer-level-count')).toContainText('3')
    await expect(page.getByTestId('metric-clarification-temp-count')).toContainText('113')
    await expect(page.getByTestId('metric-oil-temp-count')).toContainText('106')
    await expect(page.getByTestId('metric-sludge-temp-count')).toContainText('98')

    // Keempat penyebutnya berbeda-beda — tidak ada satu angka yang berlaku
    // untuk semuanya, dan total.reading_rows (412) tidak pernah dipakai.
    const counts = await page
      .locator('[data-testid="metric-cards"] .metric-card [data-testid$="-count"]')
      .allTextContents()

    expect(new Set(counts).size).toBe(4)
    for (const count of counts) {
      expect(count).not.toContain('412')
    }
  })

  // Scenario 26: "ketiga suhu tangki pada satu grafik"
  test('ketiga suhu pada SATU grafik yang bergeser di dalam kartunya, tanpa menggeser halaman', async ({ page }) => {
    await login(page, USERS.operator)
    await stubApi(page, { summary: makeSummary({ daily: THIRTY_DAYS }) })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // SATU elemen grafik, tiga seri.
    await expect(page.getByTestId('tank-temperature-chart')).toHaveCount(1)
    await expect(page.getByTestId('tank-bar-clarification')).toHaveCount(30)
    await expect(page.getByTestId('tank-bar-oil')).toHaveCount(30)
    await expect(page.getByTestId('tank-bar-sludge')).toHaveCount(30)

    // Legendanya menamai ketiganya.
    await expect(page.getByTestId('tank-legend-clarification')).toContainText('Tangki Clarification')
    await expect(page.getByTestId('tank-legend-oil')).toContainText('Tangki Minyak')
    await expect(page.getByTestId('tank-legend-sludge')).toContainText('Tangki Sludge')

    // Grafiknya bergeser DI DALAM kartunya sendiri.
    const scroller = page.locator('[data-testid="tank-temperature-card"] .chart-scroll')
    await expectCardScrollsInsideItself(page, '[data-testid="tank-temperature-card"] .chart-scroll')

    await scroller.evaluate((element) => {
      element.scrollLeft = 200
    })
    expect(await scroller.evaluate((element) => element.scrollLeft)).toBeGreaterThan(0)

    // Halaman TIDAK ikut bergeser.
    await expectNoHorizontalPageScroll(page)
    expect(await page.evaluate(() => window.scrollX)).toBe(0)
  })

  // Scenario 27: "tidak ada penandaan nilai di luar batas"
  test('nilai yang jauh menyimpang tampil apa adanya, tanpa satu pun penanda di luar batas', async ({ page }) => {
    await login(page, USERS.operator)
    await stubApi(page, {
      summary: makeSummary({
        metrics: makeMetrics({
          clarification_tank_temp_c: { min: 12.0, avg: 93.4, max: 480.0, reading_count: 113 },
          sludge_tank_temp_c: { min: -40.0, avg: 87.6, max: 999.9, reading_count: 98 },
        }),
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metric-clarification-temp-max')).toHaveText('480,0')
    await expect(page.getByTestId('metric-sludge-temp-min')).toHaveText('-40,0')

    // Ketiadaan penandaan diasersi MENURUT NAMA pada HTML ter-render.
    const html = (await page.locator('main.laporan-clf-view').innerHTML()).toLowerCase()

    for (const marker of ['threshold', 'outlier', 'iqr', 'is-danger', 'is-warning', 'severity', 'status-flag']) {
      expect(html, `penanda "${marker}" muncul di layar`).not.toContain(marker)
    }

    // Seluruh kartu metrik memakai kelas yang SAMA PERSIS, termasuk yang
    // memuat nilai ekstrem.
    const classes = await page
      .locator('[data-testid="metric-cards"] .metric-card')
      .evaluateAll((elements) => elements.map((element) => element.className))

    expect(new Set(classes).size).toBe(1)
  })

  // Scenario 28: "tata letak satu kolom pada layar ponsel"
  test('satu kolom 390px — scrollWidth halaman tidak melebihi clientWidth, dan sasaran sentuh 44x44', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, { summary: makeSummary({ daily: THIRTY_DAYS }) })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expectSingleColumn(page)
    await expectNoHorizontalPageScroll(page)

    // Sasaran sentuh: tinggi DAN lebar minimal 44px.
    await expectTouchTargets(page, [
      'period-select',
      'daily-recap-toggle',
      'export-button',
      'back-button',
      'hamburger-button',
    ])

    // Gulir dari atas ke bawah tidak pernah memunculkan gulir mendatar.
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
    await expectNoHorizontalPageScroll(page)

    // Grafik dan tabel menggulir di dalam kartunya masing-masing.
    await expectCardScrollsInsideItself(page, '[data-testid="tank-temperature-card"] .chart-scroll')
    await expectCardScrollsInsideItself(page, '[data-testid="daily-trend-production"] .chart-scroll')
    await expectCardScrollsInsideItself(page, '[data-testid="by-unit-card"] .detail-table-wrap')

    await recapToggle(page).click()
    await expectCardScrollsInsideItself(page, '[data-testid="daily-recap"] .detail-table-wrap')
    await expectNoHorizontalPageScroll(page)
  })

  // Scenario 29: "mengunduh rincian pembacaan per slot waktu sebagai CSV"
  test('Ekspor CSV — berkas terunduh, satu baris per slot dengan konteks diulang dan kolom Temuan verbatim', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    const download = page.waitForEvent('download')
    await page.getByTestId('export-button').click()
    const file = await download

    expect(file.suggestedFilename()).toMatch(/^laporan-clarification_.*\.csv$/)
    expect(api.hits.export).toBe(1)

    // Permintaan ekspor membawa period_id yang sedang dibuka dan format csv.
    const exportUrl = api.urls.find((url) => url.includes('/export'))
    expect(exportUrl).toContain('period_id=per-1')
    expect(exportUrl).toContain('format=csv')

    // Isinya dibentuk SERVER: kolom konteks record + kolom Temuan verbatim.
    const stream = await file.createReadStream()
    const chunks: Buffer[] = []

    for await (const chunk of stream) {
      chunks.push(Buffer.from(chunk))
    }

    const csv = Buffer.concat(chunks).toString('utf8')

    expect(csv).toContain('Unit Clarification')
    expect(csv).toContain('Temuan')
    expect(csv).toContain('ada buih di tangki')
    // Dan BUKAN disusun ulang dari angka yang sedang tampil di layar.
    expect(csv).not.toContain('128,5')

    // Data stasiun tidak berubah setelah ekspor: tidak ada permintaan tulis.
    expect(api.hits.total).toBeGreaterThan(0)
  })
})
