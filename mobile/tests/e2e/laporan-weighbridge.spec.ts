import { expect, test, type Page } from '@playwright/test'
import { getAuthUserId, login, USERS } from './helpers'

/**
 * laporan-weighbridge.spec.ts — screen-144--laporan-weighbridge-mobile /
 * usecase-147--laporan-weighbridge-mobile "Lihat Laporan Periode Weighbridge
 * (Mobile)".
 *
 * Satu test per test_scenarios yang punya browser_test. Berjalan di Vite dev
 * server (playwright.config.ts, baseURL http://localhost:5174): aplikasi
 * Capacitor adalah SPA biasa sebelum dibungkus native, jadi browser test layar
 * mobile TIDAK ditangguhkan sebagai "mobile-only".
 *
 * VIEWPORT PONSEL, BUKAN DESKTOP. Berkas ini menimpa viewport bawaan proyek
 * chromium (Desktop Chrome, 1280px) dengan 390x844 — ukuran ponsel yang
 * sesungguhnya. Separuh klaim layar ini adalah klaim tata letak ("satu
 * kolom", "tanpa gulir mendatar pada halaman", "sasaran sentuh minimal 44px"),
 * dan seluruhnya menjadi hampa bila diuji pada kanvas selebar 1280px: apa pun
 * muat di sana.
 *
 * MASUK LEWAT RUTE LANGSUNG, KECUALI SATU TEST. Pintu masuk UI-nya
 * (screen-141, Reporting: pilih stasiun) punya test-nya sendiri, tetapi SATU
 * test di bawah sengaja melewati tile Weighbridge dari sana — karena entri
 * 'weighbridge' pada REPORT_ROUTES adalah satu-satunya penentu tile itu hidup
 * atau mati, dan tanpa test itu layar ini bisa lengkap, hijau, dan tetap tidak
 * dapat dicapai siapa pun.
 *
 * RESPONS API DI-STUB DI TINGKAT JARINGAN (page.route), BUKAN DISEMAI KE
 * DATABASE — keputusan yang sama dengan kelima laporan mobile lain, dengan
 * alasan yang sama:
 *   - Layar ini read-only total dan tidak punya satu pun UI untuk membuat data
 *     yang dibacanya; menyemai fixture berarti menjalankan dua layar WEB lain
 *     (periode + input Weighbridge) dari dalam suite mobile.
 *   - Kebenaran ANGKA-nya bukan tanggung jawab layar ini: seluruhnya berasal
 *     dari WeighbridgeReportService, yang sudah diuji penuh di backend (55 unit
 *     + 36 feature) dan di e2e-web terhadap data sungguhan. Yang khas mobile —
 *     dan hanya dapat dibuktikan di sini — adalah PEMETAAN respons itu ke
 *     layar satu kolom.
 *   - Beberapa skenario mustahil dibuat dari data sungguhan tanpa merusak
 *     lingkungan (sesi kedaluwarsa, jaringan putus, akun tanpa mill).
 *
 * ANGKA FIXTURE SENGAJA MEMUNGKINKAN JUMLAH KEDUA ARUS TERBENTUK: 10 trip
 * masuk + 5 trip keluar = 15, dan 12.000,50 + 8.000,25 = 20.000,75 kg. Asersi
 * "tidak ada angka gabungan" hanya bermakna bila jumlahnya memang mungkin
 * muncul. Jangan "merapikan" angka-angka ini.
 *
 * REKAP HARIAN MEMAKAI v-show (CollapsibleSection), BUKAN v-if: saat tertutup
 * barisnya TETAP ADA di DOM dengan display:none. Karena itu keadaan tertutup
 * diasersi dengan toBeHidden(), tidak pernah dengan toHaveCount(0).
 */

const ROUTE = '/reports/weighbridge'

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
  station_type: 'weighbridge',
  station_type_label: 'Weighbridge',
}

const PERIOD_CLOSED = {
  id: 'per-2',
  name: 'Periode Agustus 2026',
  start_date: '2026-08-01',
  end_date: '2026-08-31',
  status: 'closed',
  station_type: 'weighbridge',
  station_type_label: 'Weighbridge',
}

const LINE_1 = { id: 'pl-1', name: 'Line 1', code: 'L1' }
const LINE_2 = { id: 'pl-2', name: 'Line 2', code: 'L2' }

/** Kunci ingatan line, ditulis StationListView.vue dan dibaca layar ini. */
const REMEMBERED_LINE_KEY = (userId: string) => `msl_production_line_${userId}`

function hourly(filled: Record<number, number>): Array<{ hour: number; trip_count: number }> {
  return Array.from({ length: 24 }, (_, hour) => ({ hour, trip_count: filled[hour] ?? 0 }))
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
    receive: {
      trip_count: 10,
      net_weight_total: 12000.5,
      net_weight_avg: 2000.08,
      net_weight_trip_count: 6,
      missing_net_weight_trip_count: 4,
      hourly: hourly({ 7: 9, 13: 1 }),
      busiest_hour: 7,
      busiest_hour_trip_count: 9,
      empty_hour_count: 22,
      by_origin: [
        { estate_supplier: 'Estate Utara', trip_count: 7, net_weight_total: 9000.25, net_weight_trip_count: 5 },
        { estate_supplier: '', trip_count: 3, net_weight_total: null, net_weight_trip_count: 0 },
      ],
    },
    dispatch: {
      trip_count: 5,
      net_weight_total: 8000.25,
      net_weight_avg: 2000.06,
      net_weight_trip_count: 4,
      missing_net_weight_trip_count: 1,
      hourly: hourly({ 20: 5 }),
      busiest_hour: 20,
      busiest_hour_trip_count: 5,
      empty_hour_count: 23,
      by_destination: [
        { destination: 'Refinery X', trip_count: 3, net_weight_total: 7000.0, net_weight_trip_count: 3 },
        { destination: null, trip_count: 2, net_weight_total: null, net_weight_trip_count: 1 },
      ],
    },
    draft_trip_count: 2,
    undated_trip_count: 3,
    daily: Array.from({ length: 12 }, (_, index) => ({
      date: `2026-09-${String(index + 1).padStart(2, '0')}`,
      receive_trip_count: index,
      receive_net_weight_total: index === 0 ? null : index * 100.25,
      dispatch_trip_count: index % 3,
      dispatch_net_weight_total: index % 3 === 0 ? null : index * 50.5,
    })),
    daily_total: {
      receive_trip_count: 10,
      receive_net_weight_total: 12000.5,
      dispatch_trip_count: 5,
      dispatch_net_weight_total: 8000.25,
    },
    completeness: {
      days_in_period: 30,
      days_with_trip: 12,
      days_counted: 18,
      period_running: true,
    },
    ...overrides,
  }
}

function emptySummary(): Record<string, unknown> {
  return makeSummary({
    receive: {
      trip_count: 0,
      net_weight_total: null,
      net_weight_avg: null,
      net_weight_trip_count: 0,
      missing_net_weight_trip_count: 0,
      hourly: hourly({}),
      busiest_hour: null,
      busiest_hour_trip_count: 0,
      empty_hour_count: 24,
      by_origin: [],
    },
    dispatch: {
      trip_count: 0,
      net_weight_total: null,
      net_weight_avg: null,
      net_weight_trip_count: 0,
      missing_net_weight_trip_count: 0,
      hourly: hourly({}),
      busiest_hour: null,
      busiest_hour_trip_count: 0,
      empty_hour_count: 24,
      by_destination: [],
    },
    draft_trip_count: 0,
    undated_trip_count: 0,
    daily: [],
    daily_total: {
      receive_trip_count: 0,
      receive_net_weight_total: null,
      dispatch_trip_count: 0,
      dispatch_net_weight_total: null,
    },
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

  await page.route('**/api/weighbridge-reports/**', async (route) => {
    const request = route.request()
    const url = request.url()

    if (request.method() === 'OPTIONS') {
      await route.fulfill({ status: 204, headers: CORS_HEADERS, body: '' })

      return
    }

    state.urls.push(url)

    if (url.includes('/business-units/options')) {
      state.hits.units += 1
      // ADMIN SAJA — perluasan screen-144 sengaja tidak menyentuh endpoint
      // ini, jadi peran terikat mill memang menerima 403 di sini.
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
        body: 'Periode,Mill,Production Line,Jenis Arus\nPeriode September 2026,Mill Utara,Line 1,Arus Masuk\n',
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
  await expect(page.getByTestId('laporan-weighbridge-mobile')).toBeVisible()
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
 * Halaman TIDAK PERNAH menggulir mendatar. Yang lebar adalah isi kartu (dua
 * grafik 24 jam, tabel rekap) — dan masing-masing menggulir di dalam kartunya
 * sendiri, itulah yang dibuktikan expectCardScrollsInsideItself().
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

/** Satu kolom: tidak ada dua kartu angka yang berdampingan mendatar. */
async function expectSingleColumn(page: Page): Promise<void> {
  const cards = page.locator('[data-testid="flow-receive"] .metric-card')

  await expect(cards.first()).toBeVisible()

  const count = await cards.count()

  expect(count).toBeGreaterThan(1)

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

test.describe('Laporan Weighbridge Mobile (screen-144)', () => {
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

    // Kedua kelompok arus hadir, dan keduanya bertumpuk.
    await expect(page.getByTestId('flow-receive')).toBeVisible()
    await expect(page.getByTestId('flow-dispatch')).toBeVisible()

    await expect(page.getByTestId('kpi-receive-trip-count')).toHaveText('10')
    await expect(page.getByTestId('kpi-dispatch-trip-count')).toHaveText('5')
    await expect(page.getByTestId('kpi-receive-net-weight-total')).toContainText('12.000,50')
    await expect(page.getByTestId('kpi-dispatch-net-weight-total')).toContainText('8.000,25')

    // Pemilih mill memang tidak untuk Operator, dan layar ini tidak pernah
    // memintanya — endpoint options akan 403, jadi memanggilnya hanya akan
    // membentuk daftar seluruh mill di perangkat yang tidak berhak.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.getByTestId('mill-current')).toContainText('Mill Utara')

    await expectSingleColumn(page)
    await expectNoHorizontalPageScroll(page)
    await expectTouchTargets(page, ['period-select', 'export-button', 'back-button'])
  })

  test('kedua grafik 24 jam menggulir DI DALAM kartunya, halaman tetap tidak bergeser', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('hourly-receive')).toBeVisible()
    await expect(page.getByTestId('hourly-dispatch')).toBeVisible()

    await expectCardScrollsInsideItself(page, '[data-testid="hourly-receive"] .chart-scroll')
    await expectCardScrollsInsideItself(page, '[data-testid="hourly-dispatch"] .chart-scroll')

    // 24 batang per arus, termasuk jam bernilai 0.
    await expect(page.locator('[data-testid^="hourly-receive-bar-"]')).toHaveCount(24)
    await expect(page.locator('[data-testid^="hourly-dispatch-bar-"]')).toHaveCount(24)

    await expectNoHorizontalPageScroll(page)
  })

  test('tidak ada satu pun angka yang menjumlahkan kedua arus', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('period-meta')).toBeVisible()

    // 12.000,50 + 8.000,25 = 20.000,75 — angka yang BISA terbentuk dari
    // fixture ini, dan karena itu asersi ketiadaannya bermakna.
    await expect(page.getByTestId('laporan-weighbridge-mobile')).not.toContainText('20.000,75')
    await expect(page.getByText(/Total Keseluruhan|Total Gabungan/i)).toHaveCount(0)
  })

  test('rekap harian tertutup secara bawaan, menggulir di dalam kartunya setelah dibuka', async ({ page }) => {
    const state = await stubApi(page)

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('daily-recap')).toBeVisible()
    // v-show, bukan v-if — jadi toBeHidden(), bukan toHaveCount(0).
    await expect(recapBody(page)).toBeHidden()

    const summaryHitsBefore = state.hits.summary

    await recapToggle(page).click()
    await expect(recapBody(page)).toBeVisible()

    // Kolom terpisah per arus, dan baris total periode.
    await expect(recapBody(page)).toContainText('Trip Masuk')
    await expect(recapBody(page)).toContainText('Trip Keluar')
    await expect(page.getByTestId('daily-recap-total')).toBeVisible()

    await expectCardScrollsInsideItself(page, '[data-testid="daily-recap"] .detail-table-wrap')
    await expectNoHorizontalPageScroll(page)

    await recapToggle(page).click()
    await expect(recapBody(page)).toBeHidden()

    // Membuka/menutup murni penyingkapan: datanya sudah ada di memori.
    expect(state.hits.summary).toBe(summaryHitsBefore)
  })

  test('periode tanpa data: keterangan tampil dan grafik TIDAK dirender', async ({ page }) => {
    await stubApi(page, { summary: emptySummary() })
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('empty-period')).toBeVisible()
    // Grafik kosong akan terbaca sebagai hasil pengukuran bernilai nol.
    await expect(page.getByTestId('hourly-receive')).toHaveCount(0)
    await expect(page.getByTestId('hourly-dispatch')).toHaveCount(0)
    await expect(page.getByTestId('kpi-receive-net-weight-total')).toContainText('tidak tersedia')
    await expectNoHorizontalPageScroll(page)
  })

  test('arus keluar tanpa trip: kelompoknya TETAP dirender, tidak disembunyikan', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        dispatch: {
          trip_count: 0,
          net_weight_total: null,
          net_weight_avg: null,
          net_weight_trip_count: 0,
          missing_net_weight_trip_count: 0,
          hourly: hourly({}),
          busiest_hour: null,
          busiest_hour_trip_count: 0,
          empty_hour_count: 24,
          by_destination: [],
        },
      }),
    })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('flow-dispatch')).toBeVisible()
    await expect(page.getByTestId('kpi-dispatch-trip-count')).toHaveText('0')
    await expect(page.getByTestId('kpi-dispatch-net-weight-total')).toContainText('tidak tersedia')
    await expect(page.getByTestId('kpi-receive-trip-count')).toHaveText('10')
  })

  test('asal kosong dan tujuan null keduanya berlabel Belum diisi', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('by-origin')).toContainText('Belum diisi')
    await expect(page.getByTestId('by-destination')).toContainText('Belum diisi')
    await expect(page.getByTestId('by-origin')).toContainText('Estate Utara')
    await expect(page.getByTestId('by-destination')).toContainText('Refinery X')
  })

  test('tidak ada kosakata durasi di mana pun pada layar', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('period-meta')).toBeVisible()

    const rendered = (await page.getByTestId('laporan-weighbridge-mobile').innerText()).toLowerCase()

    // Asersi atas KETIADAAN, dan ia harus berupa PENYISIRAN: memeriksa "kartu
    // durasi tidak ada" akan selalu hijau.
    for (const forbidden of ['durasi', 'turnaround', 'lama di pabrik', 'dwell', 'lama kendaraan']) {
      expect(rendered, `kosakata durasi "${forbidden}" muncul di layar`).not.toContain(forbidden)
    }
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
    await expect(page.getByTestId('kpi-receive-trip-count')).toHaveText('10')
    // Kunci periode mengatur penulisan data, bukan pembacaan laporan.
    await expect(page.getByTestId('export-button')).toBeEnabled()
  })

  test('ekspor CSV: unduhan terpicu dan tombol sempat berlabel Mengekspor', async ({ page }) => {
    const state = await stubApi(page)

    await openReport(page)
    await pickPeriod(page, 'per-1')

    const download = page.waitForEvent('download')

    await page.getByTestId('export-button').click()
    await (await download).delete()

    expect(state.hits.export).toBe(1)

    // Permintaan ekspor membawa periode DAN line — berkas yang mencampur line
    // adalah bentuk kesalahan yang paling sulit dibantah setelah terkirim.
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
    await expect(page.getByTestId('kpi-receive-trip-count')).toHaveText('10')
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

    await expect(page.getByTestId('kpi-receive-trip-count')).toHaveText('10')
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
/* Pintu masuk — tile Weighbridge pada layar pemilih stasiun            */
/* ================================================================== */

/**
 * SEMUA NAVIGASI DI SINI LEWAT KLIK, BUKAN page.goto(). Tabel `station`
 * lokal hidup di memori (sql.js) dan isinya hilang pada setiap muat ulang
 * halaman, jadi page.goto() akan menghapus hasil penyemaian dan grid tile
 * kembali kosong. Alasan yang sama dicatat pada docblock
 * tests/e2e/reporting-pilih-stasiun.spec.ts, dan helper di bawah menirunya.
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
   * SATU BARIS yang menentukan layar ini dapat dicapai: entri 'weighbridge'
   * pada REPORT_ROUTES di ReportingPilihStasiunView.vue. Tanpa test ini,
   * layar laporan bisa lengkap, seluruh test lainnya hijau, dan tile-nya
   * tetap kelabu — pola kegagalan yang sudah terjadi sekali pada laporan
   * Cages & Tracks versi web.
   */
  test('tile Weighbridge aktif dan menavigasi ke layar laporannya', async ({ page }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await seedLocalStations(page)
    await goToReports(page)

    const tile = page.getByTestId('station-tile-weighbridge')

    await expect(tile).toBeVisible()
    await tile.click()

    // Sufiks ** — alamatnya membawa ?production_line_id= dari layar ini.
    await page.waitForURL('**/reports/weighbridge**')
    await expect(page.getByTestId('laporan-weighbridge-mobile')).toBeVisible()

    // Dan tidak ada pesan "belum tersedia" yang muncul sebagai gantinya.
    await expect(page.getByTestId('info-message')).toHaveCount(0)
  })
})
