import { expect, test, type Page } from '@playwright/test'
import { getAuthUserId, login, USERS } from './helpers'

/**
 * laporan-pressing.spec.ts — screen-151--laporan-pressing-mobile /
 * usecase-152--laporan-pressing-mobile.
 *
 * Satu test per test_scenarios yang punya browser_test. Berjalan di Vite dev
 * server (playwright.config.ts, baseURL http://localhost:5174): aplikasi
 * Capacitor adalah SPA biasa sebelum dibungkus native, jadi browser test layar
 * mobile TIDAK ditangguhkan sebagai "mobile-only".
 *
 * VIEWPORT PONSEL, BUKAN DESKTOP. Berkas ini menimpa viewport bawaan proyek
 * chromium (Desktop Chrome, 1280px) dengan 390x844. Separuh klaim layar ini
 * adalah klaim tata letak ("satu kolom", "tanpa gulir mendatar pada halaman",
 * "sasaran sentuh minimal 44px"), dan seluruhnya hampa pada kanvas 1280px:
 * apa pun muat di sana.
 *
 * MASUK LEWAT RUTE LANGSUNG, KECUALI SATU TEST. Pintu masuk UI-nya
 * (screen-141, Reporting: pilih stasiun) punya test-nya sendiri, tetapi SATU
 * test di bawah sengaja melewati tile Pressing dari sana — entri 'pressing'
 * pada REPORT_ROUTES adalah satu-satunya penentu tile itu hidup atau mati, dan
 * tanpa test itu layar ini bisa lengkap, hijau, dan tetap tidak dapat dicapai
 * siapa pun.
 *
 * RESPONS API DI-STUB DI TINGKAT JARINGAN (page.route), BUKAN DISEMAI KE
 * DATABASE — keputusan dan alasan yang sama dengan ketujuh laporan mobile lain:
 *   - Layar ini read-only total dan tidak punya satu pun UI untuk membuat data
 *     yang dibacanya.
 *   - Kebenaran ANGKA-nya bukan tanggung jawab layar ini: seluruhnya dari
 *     PressingReportService, yang sudah diuji penuh di backend (46 unit + 25
 *     API + 29 Livewire) dan di e2e-web terhadap data sungguhan. Yang khas
 *     mobile — dan hanya dapat dibuktikan di sini — adalah PEMETAAN respons
 *     itu ke layar satu kolom.
 *   - Beberapa skenario mustahil dibuat dari data sungguhan tanpa merusak
 *     lingkungan (sesi kedaluwarsa, jaringan putus, akun tanpa mill).
 *
 * ── TIGA HAL YANG HANYA BROWSER DAPAT MEMBUKTIKAN DI LAYAR INI ─────────
 *
 * 1. CAKUPAN BERADA DI ATAS KARTU PARAMETER — diukur dari POSISI KOTAK DI
 *    VIEWPORT, bukan dari urutan sumbernya. Di layar sempit pembaca melihat
 *    lebih sedikit sekaligus, jadi apa yang dilihat lebih dulu menentukan
 *    lebih banyak.
 *
 * 2. PENYEBUT TIAP KARTU BENAR-BENAR TERCETAK pada kanvas 390px. Inilah yang
 *    paling mudah hilang karena dipotong demi ruang, dan tanpanya rata-rata
 *    sebuah parameter terbaca sebagai angka seluruh periode.
 *
 * 3. TABEL REKAP MENGGULIR DI DALAM KARTUNYA, bukan menggeser halaman. Lima
 *    kolom rata-rata plus tanggal jelas lebih lebar daripada 390px.
 *
 * KETIADAAN PENANDAAN DI LUAR BATAS DISISIR ATAS NAMA KELAS, bukan atas frasa:
 * kalimat yang menyatakan ketiadaannya sendiri memuat frasa itu. Pelajaran yang
 * harganya sudah dibayar tiga kali (screen-146, screen-147, screen-150).
 *
 * REKAP HARIAN MEMAKAI v-show (CollapsibleSection), BUKAN v-if: saat tertutup
 * barisnya TETAP ADA di DOM dengan display:none. Karena itu keadaan tertutup
 * diasersi dengan toBeHidden(), tidak pernah dengan toHaveCount(0).
 */

const ROUTE = '/reports/pressing'

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

const PERIOD = {
  id: 'per-1',
  name: 'Periode September 2026',
  start_date: '2026-09-01',
  end_date: '2026-09-30',
  status: 'open',
  station_type: 'pressing',
  station_type_label: 'Pressing',
}

const PERIOD_CLOSED = {
  id: 'per-2',
  name: 'Periode Agustus 2026',
  start_date: '2026-08-01',
  end_date: '2026-08-31',
  status: 'closed',
  station_type: 'pressing',
  station_type_label: 'Pressing',
}

const LINE_1 = { id: 'pl-1', name: 'Line 1', code: 'L1' }
const LINE_2 = { id: 'pl-2', name: 'Line 2', code: 'L2' }

/** Kunci ingatan line, ditulis StationListView.vue dan dibaca layar ini. */
const REMEMBERED_LINE_KEY = (userId: string) => `msl_production_line_${userId}`

const METRIC_COLUMNS = [
  'digester_temp_c',
  'digester_level_percent',
  'press_motor_current_amps',
  'cone_hydraulic_pressure_bar',
  'dilution_water_temp_c',
]

function averages(values: Record<string, number | null> = {}): Record<string, number | null> {
  return Object.fromEntries(METRIC_COLUMNS.map((column) => [column, values[column] ?? null]))
}

/**
 * Kelima kartu parameter. Penyebutnya SENGAJA berbeda-beda (2, 10, 0, 7, 1)
 * supaya asersi "tiap kartu mencetak penyebutnya sendiri" bermakna alih-alih
 * selalu hijau — dengan penyebut yang seragam, satu penyebut bersama akan
 * lolos tanpa terlihat.
 */
function metrics(): Array<Record<string, unknown>> {
  return [
    {
      column: 'digester_temp_c',
      label: 'Suhu Digester',
      unit: 'C',
      min: 25.5,
      avg: 30.25,
      max: 42,
      filled_slot_count: 2,
      target: {
        parameter_metric: 'Digester Temperature',
        target_operating_range: '90C - 95C',
        critical_trigger_action_limit: '< 85C (Leads to poor oil liberation)',
      },
    },
    {
      column: 'digester_level_percent',
      label: 'Level Isi Digester',
      unit: '%',
      min: 21,
      // 27,4 jelas di luar '21 - 23 RPM', dan TETAP tidak ditandai.
      avg: 27.4,
      max: 30,
      filled_slot_count: 10,
      target: {
        parameter_metric: 'Digester Fill Level',
        target_operating_range: '75% - 80% (Minimum 3/4 full)',
        critical_trigger_action_limit: '< 50% (Reduces retention time & friction)',
      },
    },
    {
      column: 'press_motor_current_amps',
      label: 'Arus Motor Screw Press',
      unit: 'A',
      min: null,
      avg: null,
      max: null,
      filled_slot_count: 0,
      target: {
        parameter_metric: 'Screw Press Motor Current',
        target_operating_range: '35 - 45 Amperes',
        critical_trigger_action_limit: '> 50 Amps (Indicates choke or heavy load)',
      },
    },
    {
      column: 'cone_hydraulic_pressure_bar',
      label: 'Tekanan Hidrolik Cone',
      unit: '%',
      min: 0,
      avg: 1.25,
      max: 3,
      filled_slot_count: 7,
      target: {
        parameter_metric: 'Cone Hydraulic Pressure',
        target_operating_range: '45 - 55 Bar',
        critical_trigger_action_limit: '> 60 Bar (Increases nut breakage severely)',
      },
    },
    {
      column: 'dilution_water_temp_c',
      label: 'Suhu Air Pengencer',
      unit: '%',
      min: 0.42,
      avg: 0.42,
      max: 0.42,
      filled_slot_count: 1,
      target: {
        parameter_metric: 'Dilution Water Temperature',
        target_operating_range: '85C - 90C',
        critical_trigger_action_limit: '< 80C (Causes poor oil-water separation)',
      },
    },
  ]
}

function makeSummary(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Utara' },
    production_line: { id: 'pl-1', name: 'Line 1' },
    period: {
      id: 'per-1',
      name: 'Periode September 2026',
      start_date: '2026-09-01',
      end_date: '2026-09-30',
      status: 'open',
    },
    has_data: true,
    coverage: {
      filled_slots: 10,
      expected_slots: 480,
      coverage_percent: 2.1,
      presser_count: 2,
      slots_per_presser_per_day: 24,
      days_in_period: 30,
      days_counted: 10,
      period_running: true,
    },
    metrics: metrics(),
    // DUA entri, bukan satu seperti pada Threshing.
    targets_without_metric: [
      {
        parameter_metric: 'Nut Breakage Rate',
        target_operating_range: '< 10% to 12%',
        critical_trigger_action_limit: '> 15% (Adjust screw press cones backward)',
      },
      {
        parameter_metric: 'Press Cake Moisture',
        target_operating_range: '34% - 38%',
        critical_trigger_action_limit: '> 40% (Indicates insufficient pressing pressure)',
      },
    ],
    targets_master_empty: false,
    by_presser: [
      {
        presser_id: 'PR-1',
        day_count: 2,
        filled_slot_count: 7,
        averages: averages({ digester_temp_c: 30 }),
      },
      {
        presser_id: 'PR-2',
        day_count: 1,
        filled_slot_count: 3,
        averages: averages({ digester_temp_c: 28.5, digester_level_percent: 22 }),
      },
    ],
    daily: Array.from({ length: 12 }, (_, index) => ({
      date: `2026-09-${String(index + 1).padStart(2, '0')}`,
      filled_slot_count: index,
      averages: averages(index === 0 ? {} : { digester_temp_c: 20 + index }),
    })),
    daily_total: {
      filled_slot_count: 10,
      averages: averages({ digester_temp_c: 29.2, digester_level_percent: 22 }),
    },
    downtime_reasons: [
      { reason: 'Belt kendur', slot_count: 3 },
      { reason: 'belt kendur', slot_count: 2 },
    ],
    total: {
      record_count: 12,
      days_with_records: 12,
      draft_record_count: 4,
      records_not_checked: 5,
      records_not_acknowledged: 6,
    },
    ...overrides,
  }
}

function emptySummary(): Record<string, unknown> {
  return makeSummary({
    has_data: false,
    coverage: {
      filled_slots: 0,
      expected_slots: 480,
      coverage_percent: 0,
      presser_count: 2,
      slots_per_presser_per_day: 24,
      days_in_period: 30,
      days_counted: 10,
      period_running: true,
    },
    metrics: metrics().map((metric) => ({
      ...metric,
      min: null,
      avg: null,
      max: null,
      filled_slot_count: 0,
    })),
    by_presser: [],
    daily: [],
    daily_total: { filled_slot_count: 0, averages: averages() },
    downtime_reasons: [],
  })
}

/* ------------------------------------------------------------------ */
/* Stub jaringan                                                       */
/* ------------------------------------------------------------------ */

interface ApiState {
  periods: unknown[]
  summary: Record<string, unknown>
  /** 200 kecuali test menyetel lain (401 / 422 / 403). */
  summaryStatus: number
  /** true = permintaan ringkasan diputus di tingkat jaringan. */
  summaryAbort: boolean
  productionLines: unknown[]
  /** true = daftar line gagal diambil (jaringan putus). */
  productionLinesAbort: boolean
  hits: { periods: number; summary: number; export: number; units: number }
  /** Seluruh URL yang benar-benar dikirim halaman ke endpoint laporan. */
  urls: string[]
}

async function stubApi(page: Page, overrides: Partial<ApiState> = {}): Promise<ApiState> {
  const state: ApiState = {
    periods: [PERIOD, PERIOD_CLOSED],
    summary: makeSummary(),
    summaryStatus: 200,
    summaryAbort: false,
    productionLines: [LINE_1],
    productionLinesAbort: false,
    hits: { periods: 0, summary: 0, export: 0, units: 0 },
    urls: [],
    ...overrides,
  }

  // Daftar Production Line — endpoint DI LUAR prefiks laporan, jadi rutenya
  // sendiri. Tanpa stub ini permintaan menembus ke backend sungguhan di :8000
  // dan hasil test bergantung pada isi database.
  await page.route('**/api/production-lines/options-for-report*', async (route) => {
    if (route.request().method() === 'OPTIONS') {
      await route.fulfill({ status: 204, headers: CORS_HEADERS, body: '' })

      return
    }

    if (state.productionLinesAbort) {
      await route.abort('failed')

      return
    }

    await route.fulfill({ status: 200, headers: CORS_HEADERS, json: { data: state.productionLines } })
  })

  await page.route('**/api/pressing-reports/**', async (route) => {
    const request = route.request()
    const url = request.url()

    if (request.method() === 'OPTIONS') {
      await route.fulfill({ status: 204, headers: CORS_HEADERS, body: '' })

      return
    }

    state.urls.push(url)

    if (url.includes('/business-units/options')) {
      state.hits.units += 1
      // ADMIN SAJA — peran terikat mill memang menerima 403 di sini, dan layar
      // ini tidak pernah memanggilnya untuk mereka.
      await route.fulfill({
        status: 403,
        headers: CORS_HEADERS,
        json: { message: 'Anda tidak memiliki akses untuk aksi ini.' },
      })

      return
    }

    if (url.includes('/periods')) {
      state.hits.periods += 1
      await route.fulfill({ status: 200, headers: CORS_HEADERS, json: { data: state.periods } })

      return
    }

    if (url.includes('/export')) {
      state.hits.export += 1
      await route.fulfill({
        status: 200,
        headers: { ...CORS_HEADERS, 'content-type': 'text/csv' },
        body: 'Periode,Mill,Production Line,Tanggal,Presser,Slot Waktu\nPeriode September 2026,Mill Utara,Line 1,2026-09-04,PR-1,07:00\n',
      })

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
          json: { message: 'Permintaan ditolak.' },
        })

        return
      }

      // Blok production_line DIBENTUK DARI PARAMETER yang diminta, bukan
      // dipaku ke Line 1. Layar ini mendahulukan nama dari SERVER atas nama
      // dari daftarnya sendiri (itulah line yang benar-benar dipakai saat
      // menghitung), jadi stub yang mengabaikan parameternya akan membuat
      // setiap asersi "line mana yang berlaku" membaca jawaban yang salah.
      const requestedLineId = new URL(url).searchParams.get('production_line_id')
      const requestedLine = (state.productionLines as Array<{ id: string; name: string }>).find(
        (line) => line.id === requestedLineId,
      )

      await route.fulfill({
        status: 200,
        headers: CORS_HEADERS,
        json: requestedLine
          ? { ...state.summary, production_line: { id: requestedLine.id, name: requestedLine.name } }
          : state.summary,
      })

      return
    }

    await route.fulfill({ status: 404, headers: CORS_HEADERS, json: { message: 'not stubbed' } })
  })

  return state
}

async function openReport(page: Page, path: string = ROUTE): Promise<void> {
  await page.goto(path)
  await expect(page.getByTestId('laporan-pressing-mobile')).toBeVisible()
}

async function pickPeriod(page: Page, periodId: string): Promise<void> {
  await page.getByTestId('period-select').selectOption(periodId)
}

/** Rekap Harian — CollapsibleSection, TERTUTUP secara bawaan. */
function recapToggle(page: Page) {
  return page.getByTestId('daily-recap').getByTestId('collapsible-section-toggle')
}

function recapBody(page: Page) {
  return page.getByTestId('daily-recap').getByTestId('collapsible-section-body')
}

/* ------------------------------------------------------------------ */
/* Bantuan asersi khas ponsel                                          */
/* ------------------------------------------------------------------ */

/**
 * Halaman TIDAK PERNAH menggulir mendatar. Yang lebar adalah isi kartu (tabel
 * rekap per presser dan rekap harian) — dan masing-masing menggulir di dalam
 * kartunya sendiri, itulah yang dibuktikan expectCardScrollsInsideItself().
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

/** Satu kolom: tidak ada dua kartu parameter yang berdampingan mendatar. */
async function expectSingleColumn(page: Page): Promise<void> {
  const cards = page.getByTestId('metric-card')

  await expect(cards.first()).toBeVisible()

  const count = await cards.count()

  expect(count).toBe(5)

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
  }
}

/**
 * Kartu yang isinya lebih lebar daripada layar menggulir DI DALAM dirinya
 * sendiri: scrollWidth > clientWidth DAN overflow-x-nya auto/scroll.
 */
async function expectCardScrollsInsideItself(page: Page, selector: string): Promise<void> {
  const result = await page
    .locator(selector)
    .first()
    .evaluate((element) => ({
      scrollWidth: element.scrollWidth,
      clientWidth: element.clientWidth,
      overflowX: getComputedStyle(element).overflowX,
    }))

  expect(['auto', 'scroll']).toContain(result.overflowX)
  expect(result.scrollWidth).toBeGreaterThan(result.clientWidth)
}

/* ================================================================== */

test.describe('Laporan Pressing Mobile (screen-151)', () => {
  // Layar ponsel sungguhan — lihat catatan VIEWPORT pada docblock berkas.
  test.use({ viewport: { width: 390, height: 844 } })

  test.beforeEach(async ({ page }) => {
    await login(page, USERS.operator)
  })

  test('Operator: seluruh laporan tampil satu kolom tanpa gulir mendatar halaman', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('period-meta')).toBeVisible()

    // Seluruh bagian hadir.
    for (const testId of [
      'coverage', 'coverage-slots', 'coverage-denominator', 'coverage-days',
      'metrics', 'no-flagging-note', 'targets-without-metric',
      'by-presser', 'downtime', 'completeness', 'daily-recap',
    ]) {
      await expect(page.getByTestId(testId), testId).toBeVisible()
    }

    // Pemilih mill memang tidak untuk Operator, dan layar ini tidak pernah
    // memintanya — endpoint options akan 403, jadi memanggilnya hanya akan
    // membentuk daftar seluruh mill di perangkat yang tidak berhak.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.getByTestId('mill-current')).toContainText('Mill Utara')

    await expectSingleColumn(page)
    await expectNoHorizontalPageScroll(page)
    await expectTouchTargets(page, ['period-select', 'export-button', 'back-button'])
  })

  test('cakupan berada DI ATAS kartu parameter, diukur dari posisi di viewport', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    const coverage = await page.getByTestId('coverage').boundingBox()
    const metricsBox = await page.getByTestId('metrics').boundingBox()

    expect(coverage).not.toBeNull()
    expect(metricsBox).not.toBeNull()

    // Di layar sempit pembaca melihat lebih sedikit sekaligus, jadi apa yang
    // dilihat lebih dulu menentukan lebih banyak.
    expect(coverage!.y).toBeLessThan(metricsBox!.y)

    // Ketiga angka pembentuk penyebut ikut tercetak, bukan hanya persennya.
    await expect(page.getByTestId('coverage-denominator')).toContainText('2 presser')
    await expect(page.getByTestId('coverage-denominator')).toContainText('24 slot')
    await expect(page.getByTestId('coverage-slots')).toContainText('10 dari 480 slot')
  })

  test('tiap kartu parameter mencetak penyebutnya sendiri, dan penyebutnya berbeda-beda', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    const denominators = page.getByTestId('metric-denominator')

    await expect(denominators).toHaveCount(5)

    const texts = await denominators.allInnerTexts()

    // Lima penyebut BERBEDA pada layar yang sama — itulah yang membuat asersi
    // ini bermakna alih-alih selalu hijau.
    expect(texts[0]).toContain('2 dari 10 slot')
    expect(texts[1]).toContain('10 dari 10 slot')
    expect(texts[2]).toContain('0 dari 10 slot')
    expect(texts[3]).toContain('7 dari 10 slot')
    expect(texts[4]).toContain('1 dari 10 slot')
  })

  test('kartu tanpa pembacaan TETAP dirender, dengan standarnya tetap tampil', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    const motorCard = page.getByTestId('metric-card').nth(2)

    // Kartu yang hilang terbaca sebagai "tidak ada parameter ini", padahal
    // yang benar adalah "tidak ada yang mengukurnya".
    await expect(motorCard).toContainText('Arus Motor Screw Press')
    await expect(motorCard).toContainText('tidak tersedia')
    await expect(motorCard).toContainText('35 - 45 Amperes')
  })

  test('standar dan rencana tindakan berada DI DALAM kartu angkanya', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    const levelCard = page.getByTestId('metric-card').nth(1)

    // Memindahkannya ke bagian terpisah akan membuang satu-satunya keunggulan
    // yang diberikan master target.
    await expect(levelCard).toContainText('27,40')
    await expect(levelCard).toContainText('75% - 80% (Minimum 3/4 full)')
    await expect(levelCard).toContainText('< 50% (Reduces retention time & friction)')
  })

  test('tidak ada satu pun kelas penanda di luar batas pada halaman', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metrics')).toBeVisible()

    // Rata-rata drum 27,4 jelas di luar '21 - 23 RPM', dan tetap tidak
    // ditandai. DISISIR ATAS NAMA KELAS, bukan atas frasa: kalimat yang
    // menyatakan ketiadaannya sendiri memuat frasa itu.
    for (const className of ['md-threshold', 'is-danger', 'is-warning', 'md-chip--danger']) {
      await expect(page.locator(`.${className}`), className).toHaveCount(0)
    }

    const note = page.getByTestId('no-flagging-note')

    await expect(note).toContainText('tidak menilai satu angka pun')
    await expect(note).toContainText('teks bebas')
    await expect(note).toContainText('berlaku umum untuk seluruh mill')
  })

  test('standar tanpa kolom pengukuran tampil pada bagiannya sendiri', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('targets-without-metric')).toContainText('Nut Breakage Rate')
    await expect(page.getByTestId('targets-without-metric')).toContainText('Press Cake Moisture')
    // DUA baris, bukan satu — asersi "bagiannya ada" saja akan tetap hijau
    // walau satu entri hilang.
    await expect(page.getByTestId('targets-without-metric-row')).toHaveCount(2)
    await expect(page.getByTestId('targets-without-metric-note'))
      .toContainText('tidak punya kolom pengukuran di mana pun pada sistem ini')
    await expect(page.getByTestId('targets-master-empty')).toHaveCount(0)
  })

  test('master target kosong: keterangan tampil, angka ukur tetap ada', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({ targets_master_empty: true, targets_without_metric: [] }),
    })
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('targets-master-empty')).toBeVisible()
    await expect(page.getByTestId('targets-without-metric')).toHaveCount(0)
    // Master yang belum diisi tidak menghapus pengukuran yang sudah terjadi.
    await expect(page.getByTestId('metrics')).toContainText('30,25')
  })

  test('periode belum mulai: persen cakupan tanda pisah, bukan 0%', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          coverage_percent: null,
          presser_count: 0,
          slots_per_presser_per_day: 24,
          days_in_period: 30,
          days_counted: 0,
          // true juga di server untuk periode yang BELUM MULAI — inilah
          // sebabnya days_counted harus diperiksa lebih dulu.
          period_running: true,
        },
      }),
    })
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('coverage-percent')).toHaveText('—')
    await expect(page.getByTestId('coverage-not-started-note')).toBeVisible()
    await expect(page.getByTestId('coverage-running-note')).toHaveCount(0)
  })

  test('rekap per presser dan rekap harian menggulir DI DALAM kartunya', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('by-presser-table')).toBeVisible()

    // Lima kolom rata-rata plus nama unit jelas lebih lebar daripada 390px.
    await expectCardScrollsInsideItself(page, '[data-testid="by-presser"] .detail-table-wrap')

    await recapToggle(page).click()
    await expect(recapBody(page)).toBeVisible()

    await expectCardScrollsInsideItself(page, '[data-testid="daily-recap"] .detail-table-wrap')
    await expectNoHorizontalPageScroll(page)
  })

  test('rekap harian tertutup secara bawaan dan membuka/menutupnya tidak memicu permintaan', async ({ page }) => {
    const state = await stubApi(page)

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('daily-recap')).toBeVisible()
    // v-show, bukan v-if — jadi toBeHidden(), bukan toHaveCount(0).
    await expect(recapBody(page)).toBeHidden()

    const summaryHitsBefore = state.hits.summary

    await recapToggle(page).click()
    await expect(recapBody(page)).toBeVisible()
    await expect(page.getByTestId('daily-recap-total')).toBeVisible()
    // Total periode DIHITUNG ULANG server atas seluruh slot.
    await expect(page.getByTestId('daily-recap-total')).toContainText('29,20')

    await recapToggle(page).click()
    await expect(recapBody(page)).toBeHidden()

    // Membuka/menutup murni penyingkapan: datanya sudah ada di memori.
    expect(state.hits.summary).toBe(summaryHitsBefore)
  })

  test('dua ejaan alasan downtime tampil sebagai dua baris, dengan keterangan harfiah', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('downtime-row')).toHaveCount(2)
    await expect(page.getByTestId('downtime-note')).toContainText('harfiah')
  })

  test('tanpa alasan downtime: keterangan tampil, bukan tabel kosong', async ({ page }) => {
    await stubApi(page, { summary: makeSummary({ downtime_reasons: [] }) })
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('downtime-empty')).toBeVisible()
    await expect(page.getByTestId('downtime-table')).toHaveCount(0)
  })

  test('periode tanpa data: keterangan tampil, cakupan tetap ada, rekap tidak digambar', async ({ page }) => {
    await stubApi(page, { summary: emptySummary() })
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('empty-period')).toBeVisible()
    // Cakupan TETAP digambar — justru itu yang menjelaskan kekosongannya.
    await expect(page.getByTestId('coverage')).toBeVisible()
    await expect(page.getByTestId('metrics')).toContainText('tidak tersedia')
    // Tabel kosong akan terbaca sebagai hasil pengukuran bernilai nol.
    await expect(page.getByTestId('by-presser')).toHaveCount(0)
    await expect(page.getByTestId('downtime')).toHaveCount(0)
    await expect(page.getByTestId('daily-recap')).toHaveCount(0)
    await expectNoHorizontalPageScroll(page)
  })

  test('kelengkapan record: kelima penghitung tampil, dengan keterangan draft ikut terhitung', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('record-count')).toHaveText('12')
    await expect(page.getByTestId('draft-record-count')).toHaveText('4')
    await expect(page.getByTestId('records-not-checked')).toHaveText('5')
    await expect(page.getByTestId('records-not-acknowledged')).toHaveText('6')
    await expect(page.getByTestId('draft-note')).toContainText('IKUT terhitung')
  })

  test('periode tertutup: laporan penuh, badge Tertutup, ekspor tetap aktif', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        period: {
          id: 'per-2',
          name: 'Periode Agustus 2026',
          start_date: '2026-08-01',
          end_date: '2026-08-31',
          status: 'closed',
        },
      }),
    })

    await openReport(page)
    await pickPeriod(page, 'per-2')

    await expect(page.getByTestId('period-status-badge')).toContainText('Tertutup')
    await expect(page.getByTestId('metrics')).toBeVisible()
    // Kunci periode mengatur penulisan data, bukan pembacaan laporan.
    await expect(page.getByTestId('export-button')).toBeEnabled()
  })

  test('ekspor CSV: unduhan terpicu dan permintaannya membawa periode + line', async ({ page }) => {
    const state = await stubApi(page)

    await openReport(page)
    await pickPeriod(page, 'per-1')

    const download = page.waitForEvent('download')

    await page.getByTestId('export-button').click()
    await (await download).delete()

    expect(state.hits.export).toBe(1)

    // Berkas yang mencampur line adalah bentuk kesalahan yang paling sulit
    // dibantah setelah terkirim.
    const exportUrl = state.urls.find((url) => url.includes('/export')) ?? ''

    expect(exportUrl).toContain('period_id=per-1')
    expect(exportUrl).toContain('production_line_id=pl-1')
    expect(exportUrl).toContain('format=csv')
  })

  test('kegagalan jaringan: pesan + Coba Lagi, periode terpilih tidak hilang', async ({ page }) => {
    const state = await stubApi(page, { summaryAbort: true })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('network-error')).toBeVisible()
    await expect(page.getByTestId('retry-button')).toBeVisible()
    await expect(page.getByTestId('period-select')).toHaveValue('per-1')

    state.summaryAbort = false

    await page.getByTestId('retry-button').click()

    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('metrics')).toBeVisible()
  })

  test('sesi berakhir (401): diarahkan ke Login tanpa pesan jaringan', async ({ page }) => {
    await stubApi(page, { summaryStatus: 401 })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await page.waitForURL('**/login')

    await expect(page.getByTestId('network-error')).toHaveCount(0)
  })

  test('mill tanpa Production Line: arahan menghubungi Admin, tanpa tombol coba lagi', async ({ page }) => {
    await stubApi(page, { productionLines: [] })

    await openReport(page)

    await expect(page.getByTestId('production-line-unavailable')).toContainText(
      'belum memiliki Production Line',
    )
    await expect(page.getByTestId('production-line-retry')).toHaveCount(0)
  })

  test('daftar Production Line gagal dimuat: pesan BERBEDA, dengan tombol coba lagi sendiri', async ({ page }) => {
    const state = await stubApi(page, { productionLinesAbort: true })

    await openReport(page)

    await expect(page.getByTestId('production-line-unavailable')).toContainText('tidak dapat dimuat')
    await expect(page.getByTestId('production-line-retry')).toBeVisible()
    // Kegagalan daftar line tidak boleh menyamar sebagai galat laporan.
    await expect(page.getByTestId('network-error')).toHaveCount(0)

    state.productionLinesAbort = false

    await page.getByTestId('production-line-retry').click()

    await expect(page.getByTestId('production-line-unavailable')).toHaveCount(0)
  })

  test('dua line tanpa pilihan: pemilih tampil dan TIDAK ada satu angka pun', async ({ page }) => {
    const state = await stubApi(page, { productionLines: [LINE_1, LINE_2] })

    await openReport(page)

    await expect(page.getByTestId('production-line-select')).toBeVisible()
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()

    await pickPeriod(page, 'per-1')

    // Penjagaan ada di loadSummary(), jadi tidak ada permintaan yang berangkat
    // — bukan sekadar angka yang tidak dirender.
    expect(state.hits.summary).toBe(0)
    await expect(page.getByTestId('period-meta')).toHaveCount(0)

    await page.getByTestId('production-line-select').selectOption('pl-2')

    await expect(page.getByTestId('metrics')).toBeVisible()
    expect(state.urls.some((url) => url.includes('production_line_id=pl-2'))).toBe(true)
  })

  test('line dari query rute dipakai dan diingat perangkat', async ({ page }) => {
    const state = await stubApi(page, { productionLines: [LINE_1, LINE_2] })

    await openReport(page, `${ROUTE}?production_line_id=pl-2`)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('production-line-current')).toContainText('Line 2')
    expect(state.urls.some((url) => url.includes('production_line_id=pl-2'))).toBe(true)

    const userId = await getAuthUserId(page)
    const remembered = await page.evaluate(
      (key) => window.localStorage.getItem(key),
      REMEMBERED_LINE_KEY(userId),
    )

    expect(remembered).toBe('pl-2')
  })

  test('line yang diingat perangkat dipakai tanpa diminta memilih lagi', async ({ page }) => {
    await stubApi(page, { productionLines: [LINE_1, LINE_2] })

    const userId = await getAuthUserId(page)

    await page.goto('/home')
    await page.evaluate(
      ([key, value]) => window.localStorage.setItem(key, value),
      [REMEMBERED_LINE_KEY(userId), 'pl-2'] as const,
    )

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('production-line-current')).toContainText('Line 2')
    await expect(page.getByTestId('production-line-required-hint')).toHaveCount(0)
  })

  test('line yang diingat sudah tidak ada: pemilih muncul lagi, tanpa menebak line lain', async ({ page }) => {
    const state = await stubApi(page, { productionLines: [LINE_1, LINE_2] })

    const userId = await getAuthUserId(page)

    await page.goto('/home')
    await page.evaluate(
      ([key, value]) => window.localStorage.setItem(key, value),
      [REMEMBERED_LINE_KEY(userId), 'pl-hilang'] as const,
    )

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    expect(state.hits.summary).toBe(0)
  })

  test('mill belum punya periode: arahan menghubungi Admin, pemilih tetap ada dalam keadaan kosong', async ({ page }) => {
    await stubApi(page, { periods: [] })

    await openReport(page)

    await expect(page.getByTestId('no-periods')).toBeVisible()
    // Pemilih yang LENYAP dan pemilih yang KOSONG menceritakan hal berbeda.
    await expect(page.getByTestId('period-select')).toBeVisible()
  })

  test('layar hanya membaca: footer hanya Ekspor dan Back', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    const footerButtons = page.locator('.action-footer button')

    await expect(footerButtons).toHaveCount(2)
    await expect(footerButtons.nth(0)).toContainText('Ekspor')
    await expect(footerButtons.nth(1)).toContainText('Back')

    // Tidak ada satu pun kontrol tulis di seluruh layar.
    await expect(page.getByRole('button', { name: /Simpan|Hapus|Verifikasi/i })).toHaveCount(0)
  })

  test('Back kembali ke Dashboard & Reporting', async ({ page }) => {
    await stubApi(page)
    await openReport(page)

    await page.getByTestId('back-button').click()
    await page.waitForURL('**/dashboard-reporting')
  })
})

/* ================================================================== */
/* Pintu masuk — tile Pressing pada layar pemilih stasiun              */
/* ================================================================== */

/**
 * SEMUA NAVIGASI DI SINI LEWAT KLIK, BUKAN page.goto(). Tabel `station` lokal
 * hidup di memori (sql.js) dan isinya hilang pada setiap muat ulang halaman,
 * jadi page.goto() akan menghapus hasil penyemaian dan grid tile kembali
 * kosong. Alasan yang sama dicatat pada docblock
 * tests/e2e/reporting-pilih-stasiun.spec.ts.
 */
async function seedLocalStations(page: Page): Promise<void> {
  await page.getByTestId('menu-card-production-process-activity').click()
  await page.waitForURL('**/stations')

  const picker = page.getByTestId('production-line-picker')
  const firstTile = page.locator('[data-testid^="station-tile-"]').first()

  await expect(picker.or(firstTile).first()).toBeVisible({ timeout: 15_000 })

  if (await picker.isVisible()) {
    await page.locator('[data-testid^="production-line-option-"]').first().click()
  }

  await expect(firstTile).toBeVisible({ timeout: 15_000 })

  await page.getByTestId('breadcrumb-home').click()
  await page.waitForURL('**/home')
}

/** Home → Dashboard & Reporting → Reporting, seluruhnya lewat klik. */
async function goToReports(page: Page): Promise<void> {
  await page.getByTestId('menu-card-dashboard-reporting').click()
  await page.waitForURL('**/dashboard-reporting')

  await page.getByTestId('menu-card-reporting').click()
  await page.waitForURL('**/reports')
  await expect(page.getByTestId('station-grid')).toBeVisible()
}

test.describe('Pintu masuk dari Reporting (screen-141)', () => {
  test.use({ viewport: { width: 390, height: 844 } })

  /**
   * SATU BARIS yang menentukan layar ini dapat dicapai: entri 'pressing' pada
   * REPORT_ROUTES di ReportingPilihStasiunView.vue. Tanpa test ini, layar
   * laporan bisa lengkap, seluruh test lainnya hijau, dan tile-nya tetap
   * kelabu — pola kegagalan yang sudah terjadi sekali pada laporan Cages &
   * Tracks versi web.
   */
  test('tile Pressing aktif dan menavigasi ke layar laporannya', async ({ page }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await seedLocalStations(page)
    await goToReports(page)

    const tile = page.getByTestId('station-tile-pressing')

    await expect(tile).toBeVisible()
    await tile.click()

    // Sufiks ** — alamatnya membawa ?production_line_id= dari layar ini.
    await page.waitForURL('**/reports/pressing**')
    await expect(page.getByTestId('laporan-pressing-mobile')).toBeVisible()

    // Dan tidak ada pesan "belum tersedia" yang muncul sebagai gantinya.
    await expect(page.getByTestId('info-message')).toHaveCount(0)
  })
})
