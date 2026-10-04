import { test, expect, type Page } from '@playwright/test'
import { getAuthUserId, login, USERS } from './helpers'

/**
 * laporan-cages-track.spec.ts — screen-136--laporan-cages-track-mobile /
 * usecase-136--laporan-cages-track-mobile "Lihat Laporan Periode Cages &
 * Tracks (Mobile)".
 *
 * Satu test per test_scenarios yang punya browser_test — seluruh 24.
 * Berjalan di Vite dev server (playwright.config.ts, baseURL
 * http://localhost:5174): aplikasi Capacitor adalah SPA biasa sebelum
 * dibungkus native, jadi browser test layar mobile TIDAK ditangguhkan
 * sebagai "mobile-only".
 *
 * VIEWPORT PONSEL, BUKAN DESKTOP. Berkas ini menimpa viewport bawaan
 * proyek chromium (Desktop Chrome, 1280px) dengan 390x844 — ukuran ponsel
 * yang sesungguhnya. Separuh klaim layar ini adalah klaim tata letak
 * ("satu kolom", "tanpa gulir mendatar pada halaman", "sasaran sentuh
 * minimal 44px"), dan seluruhnya menjadi hampa bila diuji pada kanvas
 * selebar 1280px: apa pun muat di sana.
 *
 * MASUK LEWAT RUTE LANGSUNG. Pintu masuk UI-nya (screen-141, Reporting:
 * pilih stasiun) punya test-nya sendiri di
 * tests/e2e/reporting-pilih-stasiun.spec.ts; berkas ini menavigasi
 * langsung ke '/reports/cages-track' supaya kegagalan di sini selalu
 * berarti layar laporannya, bukan layar pemilih stasiun.
 *
 * RESPONS API DI-STUB DI TINGKAT JARINGAN (page.route), BUKAN DISEMAI KE
 * DATABASE — keputusan yang sama dengan laporan-sterilizer.spec.ts, dengan
 * alasan yang sama:
 *   - Layar ini read-only total dan tidak punya satu pun UI untuk membuat
 *     data yang dibacanya; menyemai fixture berarti menjalankan dua layar
 *     WEB lain (periode + input Cages & Tracks) dari dalam suite mobile.
 *   - Kebenaran ANGKA-nya bukan tanggung jawab layar ini: seluruhnya
 *     berasal dari CagesTrackReportService, yang sudah diuji penuh di
 *     backend (Feature + Unit) dan di e2e-web terhadap data sungguhan.
 *     Yang khas mobile — dan hanya dapat dibuktikan di sini — adalah
 *     PEMETAAN respons itu ke layar satu kolom.
 *   - Beberapa skenario mustahil dibuat dari data sungguhan tanpa merusak
 *     lingkungan (sesi kedaluwarsa, jaringan putus, akun tanpa mill).
 *
 * ANGKA FIXTURE SENGAJA TIDAK KONSISTEN SECARA ARITMETIKA —
 * kpi.total_cages_tipped (1284), jumlah baris daily (96 + 0), dan
 * total.cages_tipped (777) saling bertentangan. Layar yang benar
 * menampilkan ketiganya apa adanya. Jangan "merapikan".
 *
 * REKAP HARIAN MEMAKAI v-show (CollapsibleSection), BUKAN v-if: saat
 * tertutup barisnya TETAP ADA di DOM dengan display:none. Karena itu
 * keadaan tertutup diasersi dengan not.toBeVisible() / toBeHidden(), tidak
 * pernah dengan toHaveCount(0).
 */

const ROUTE = '/reports/cages-track'
const API_GLOB = '**/api/cages-track-reports/**'

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
 * benar-benar dikirim App\Http\Controllers\Api\CagesTrackReportController.
 */
const PERIOD_CT = {
  id: 'per-1',
  name: 'Periode Maret 2026',
  start_date: '2026-03-01',
  end_date: '2026-03-14',
  status: 'open',
  station_type: 'cages-track',
  station_type_label: 'Cages Track',
}

/**
 * Periode yang cakupannya mencakup BANYAK jenis stasiun sekaligus.
 *
 * Sampai 2026-09-25 bentuknya `station_type: null` + label 'Semua Jenis
 * Stasiun'. Sejak `periods` dipecah menjadi `periods` + `period_stations`,
 * cakupan itu diwujudkan sebagai satu baris period_stations per jenis, dan
 * periodOption() hanya memulangkan baris jenis stasiun LAYAR INI — jadi
 * station_type TIDAK PERNAH null lagi dan bentuknya tak berbeda dari
 * periode berjenis tunggal. Yang tetap diuji fixture ini: periode semacam
 * itu TETAP terpungut layar ini.
 */
const PERIOD_LINTAS_STASIUN = {
  id: 'per-2',
  name: 'Periode Lintas Stasiun Maret 2026',
  start_date: '2026-03-01',
  end_date: '2026-03-31',
  status: 'open',
  station_type: 'cages-track',
  station_type_label: 'Cages Track',
}

const PERIOD_CLOSED = {
  id: 'per-3',
  name: 'Periode Februari 2026',
  start_date: '2026-02-01',
  end_date: '2026-02-28',
  status: 'closed',
  station_type: 'cages-track',
  station_type_label: 'Cages Track',
}

const BUSINESS_UNITS = [
  { id: 'bu-1', name: 'Mill Utara' },
  { id: 'bu-2', name: 'Mill Selatan' },
]

function makeHourly(): Array<{ hour: number; cages: number; within_operating_window: boolean }> {
  return Array.from({ length: 24 }, (_, hour) => ({
    hour,
    cages: hour >= 6 && hour <= 18 ? (hour === 9 ? 168 : 10) : 0,
    within_operating_window: hour >= 6 && hour <= 18,
  }))
}

const FULL_KPI = {
  total_cages_tipped: 1284,
  total_cages_out: 1310,
  avg_cages_per_day: 91.7,
  peak_hour: 9,
  peak_hour_cages: 168,
  idle_operating_hours: 9,
  longest_gap_hours: 4,
  longest_gap_date: '2026-03-08',
  avg_tippler_duration_hours: 11.5,
  days_with_records: 14,
  days_without_valid_window: 0,
}

function makeSummary(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    period: {
      id: 'per-1',
      name: 'Periode Maret 2026',
      start_date: '2026-03-01',
      end_date: '2026-03-14',
      status: 'open',
      business_unit_name: 'Mill Utara',
    },
    kpi: { ...FULL_KPI },
    hourly: makeHourly(),
    daily: [
      {
        date: '2026-03-01',
        cages_tipped: 96,
        cages_out: 99,
        operating_hours: 12,
        idle_operating_hours: 1,
        longest_gap_hours: 2,
        min_remaining: 4,
      },
      {
        date: '2026-03-14',
        cages_tipped: 0,
        cages_out: 0,
        operating_hours: null,
        idle_operating_hours: null,
        longest_gap_hours: null,
        min_remaining: null,
      },
    ],
    queue: { min_remaining: 4, avg_remaining: 11.3 },
    // Sengaja bertentangan dengan kpi dan dengan jumlah baris daily.
    total: { cages_tipped: 777, cages_out: 888, days: 14 },
    ...overrides,
  }
}

const EMPTY_SUMMARY = makeSummary({
  kpi: {
    total_cages_tipped: 0,
    total_cages_out: 0,
    avg_cages_per_day: 0,
    peak_hour: null,
    peak_hour_cages: 0,
    idle_operating_hours: 0,
    longest_gap_hours: null,
    longest_gap_date: null,
    avg_tippler_duration_hours: null,
    days_with_records: 0,
    days_without_valid_window: 0,
  },
  hourly: Array.from({ length: 24 }, (_, hour) => ({ hour, cages: 0, within_operating_window: false })),
  daily: [],
  queue: { min_remaining: null, avg_remaining: null },
  total: { cages_tipped: 0, cages_out: 0, days: 0 },
})

/** Rekap sebulan penuh — 30 baris, kasus yang membuat rekap perlu ditutup. */
const THIRTY_DAYS = Array.from({ length: 30 }, (_, index) => ({
  date: `2026-03-${String(index + 1).padStart(2, '0')}`,
  cages_tipped: 10 + index,
  cages_out: 9 + index,
  operating_hours: 12,
  idle_operating_hours: 1,
  longest_gap_hours: 2,
  min_remaining: 5,
}))

/* ------------------------------------------------------------------ */
/* Stub jaringan                                                       */
/* ------------------------------------------------------------------ */

/* ------------------------------------------------------------------ */
/* Production Line — konteks yang DIPILIH, bukan ikatan akun            */
/* ------------------------------------------------------------------ */

/**
 * Bawaan berkas ini SATU line: mill dengan satu line tidak punya keputusan
 * untuk diminta, line itu berlaku otomatis, dan seluruh skenario lama tetap
 * menguji apa yang memang mereka uji. Skenario yang menguji PEMILIHnya
 * men-stub DUA line secara eksplisit.
 */
const LINE_1 = { id: 'pl-1', name: 'Line 1', code: 'L1' }
const LINE_2 = { id: 'pl-2', name: 'Line 2', code: 'L2' }
const TWO_LINES = [LINE_1, LINE_2]

/** Kunci ingatan line, ditulis StationListView.vue dan dibaca layar ini. */
const REMEMBERED_LINE_KEY = (userId: string) => `msl_production_line_${userId}`

interface ApiState {
  units: unknown[]
  periods: unknown[]
  summary: Record<string, unknown>
  /** 200 kecuali test menyetel lain (401 / 422 / 403). */
  summaryStatus: number
  /** true = permintaan ringkasan diputus di tingkat jaringan. */
  summaryAbort: boolean
  hits: { units: number; periods: number; summary: number; export: number; total: number }
  /** GET /api/production-lines/options-for-report — daftar line mill yang berlaku. */
  productionLines: unknown[]
  /** true = daftar line gagal diambil (jaringan putus). */
  productionLinesAbort: boolean
  /** Ringkasan per production_line_id — untuk skenario "angka satu line". */
  summaryByLine?: Record<string, Record<string, unknown>>
  /** Hitungan permintaan daftar line. */
  productionLineHits: number
  /** URL lengkap setiap permintaan daftar line — dipakai membuktikan
   *  business_unit_id mill TERPILIH yang berangkat, bukan swa-cakup. */
  productionLineUrls: string[]
  /** Seluruh URL yang benar-benar dikirim halaman ke endpoint laporan. */
  urls: string[]
}

async function stubApi(page: Page, overrides: Partial<ApiState> = {}): Promise<ApiState> {
  const state: ApiState = {
    units: BUSINESS_UNITS,
    periods: [PERIOD_CT],
    summary: makeSummary(),
    summaryStatus: 200,
    summaryAbort: false,
    hits: { units: 0, periods: 0, summary: 0, export: 0, total: 0 },
    urls: [],
    productionLines: [LINE_1],
    productionLinesAbort: false,
    productionLineHits: 0,
    productionLineUrls: [],
    ...overrides,
  }


  // Daftar Production Line — endpoint DI LUAR prefiks laporan, jadi
  // rutenya sendiri. Tanpa stub ini permintaan akan menembus ke backend
  // sungguhan di :8000 dan hasil test bergantung pada isi database.
  await page.route('**/api/production-lines/options-for-report*', async (route) => {
    const request = route.request()

    if (request.method() === 'OPTIONS') {
      await route.fulfill({ status: 204, headers: CORS_HEADERS, body: '' })

      return
    }

    state.productionLineHits += 1
    state.productionLineUrls.push(request.url())

    if (state.productionLinesAbort) {
      await route.abort('failed')

      return
    }

    await route.fulfill({ status: 200, headers: CORS_HEADERS, json: { data: state.productionLines } })
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

      const lineId = new URL(url).searchParams.get('production_line_id') ?? ''
      const perLine = state.summaryByLine?.[lineId]

      await route.fulfill({
        status: 200,
        headers: CORS_HEADERS,
        json: perLine ?? state.summary,
      })

      return
    }

    if (url.includes('/export')) {
      state.hits.export += 1
      await route.fulfill({
        status: 200,
        headers: { ...CORS_HEADERS, 'content-type': 'text/csv' },
        body: 'Tanggal,Jam,Lori Ditumpahkan\n2026-03-01,09,168\n',
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
  await expect(page.getByTestId('laporan-cages-track-mobile')).toBeVisible()
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
 * Halaman TIDAK PERNAH menggulir mendatar. Yang lebar adalah isi kartu
 * (grafik 24 jam, grafik tanggal, tabel rekap) — dan masing-masing
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

/** Satu kolom: tidak ada dua blok utama yang berdampingan mendatar. */
async function expectSingleColumn(page: Page): Promise<void> {
  const cards = page.locator('.laporan-ct-view > .metric-stack > .metric-card')
  // Tunggu kartu pertama dirender sebelum menghitung — count() dibaca
  // seketika (lihat catatan yang sama di laporan-clarification.spec.ts).
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
  const result = await page.locator(selector).first().evaluate((element) => ({
    scrollWidth: element.scrollWidth,
    clientWidth: element.clientWidth,
    overflowX: getComputedStyle(element).overflowX,
  }))

  expect(['auto', 'scroll']).toContain(result.overflowX)
  expect(result.scrollWidth).toBeGreaterThan(result.clientWidth)
}

/* ================================================================== */

test.describe('Laporan Cages & Tracks Mobile (screen-136)', () => {
  // Layar ponsel sungguhan — lihat catatan VIEWPORT pada docblock berkas.
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

    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveText('1.284')
    await expect(page.getByTestId('kpi-total-cages-out')).toHaveText('1.310')
    await expect(page.getByTestId('kpi-avg-cages-per-day')).toHaveText('91,7')
    await expect(page.getByTestId('kpi-peak-hour')).toHaveText('09.00')
    await expect(page.getByTestId('kpi-idle-hours')).toHaveText('9')
    await expect(page.getByTestId('kpi-longest-gap')).toHaveText('4')
    await expect(page.getByTestId('kpi-avg-tippler-duration')).toHaveText('11,5')

    await expect(page.getByTestId('hourly-distribution')).toBeVisible()
    await expect(page.getByTestId('daily-trend')).toBeVisible()
    await expect(page.getByTestId('queue')).toBeVisible()
    await expect(page.getByTestId('daily-recap')).toBeVisible()

    // Tata letak ponsel.
    await expectSingleColumn(page)
    await expectNoHorizontalPageScroll(page)
    await expectTouchTargets(page, ['period-select', 'export-button', 'back-button'])

    // Tidak ada satu pun kontrol tulis di sepanjang layar.
    await expect(page.locator('main.laporan-ct-view input')).toHaveCount(0)
    await expect(page.locator('main.laporan-ct-view textarea')).toHaveCount(0)
    await expect(page.getByRole('button', { name: /simpan|hapus/i })).toHaveCount(0)

    // Layar baca: tidak ada permintaan ke luar prefiks laporan.
    expect(api.urls.every((url) => url.includes('cages-track-reports'))).toBe(true)
  })

  // Scenario 2: "berhasil dilihat Admin setelah memilih mill"
  /*
   * DIPULIHKAN 2026-09-28 (tahap 4b) ke bentuk aslinya: Admin memilih mill,
   * line berlaku, dan ANGKANYA TAMPIL.
   *
   * Bentuk sementara sebelumnya ("nol angka, karena tidak ada jalan") lahir
   * dari satu keterbatasan backend, bukan dari keputusan produk: satu-satunya
   * endpoint mobile yang mendaftar Production Line saat itu
   * (GET /api/production-lines/current) bersifat SWA-CAKUP — ia memulangkan
   * line milik mill AKUN PEMANGGIL, dan Admin tidak terikat mill. Endpoint
   * baru GET /api/production-lines/options-for-report?business_unit_id=
   * menutup lubang itu.
   *
   * Asersi di bawah lebih kuat daripada bentuk aslinya MAUPUN daripada
   * bentuk sementara itu: daftar line diminta DENGAN business_unit_id mill
   * terpilih, /summary membawa mill DAN line, /periods TIDAK PERNAH membawa
   * production_line_id, dan mengganti mill memuat ulang keduanya.
   */
  test('Admin — memilih mill memuat ulang daftar line + periode beserta seluruh angkanya, tetap satu kolom', async ({ page }) => {
    await login(page, USERS.operator)
    await becomeAdminSession(page)
    const api = await stubApi(page)
    await openReport(page)

    await expect(page.getByTestId('mill-select')).toBeVisible()
    await expect(page.getByTestId('mill-select').locator('option')).toHaveCount(3) // placeholder + 2 mill
    expect(api.hits.periods).toBe(0)
    // Tanpa mill terpilih, daftar line pun belum diminta.
    expect(api.productionLineHits).toBe(0)

    await page.getByTestId('mill-select').selectOption('bu-1')
    await expect(page.getByTestId('period-select').locator('option')).toHaveCount(2)

    // Daftar line diminta UNTUK MILL TERPILIH — bukan lewat endpoint
    // swa-cakup yang akan memulangkan line milik mill akun Admin.
    await expect.poll(() => api.productionLineHits).toBe(1)
    expect(api.productionLineUrls.some((url) => url.includes('business_unit_id=bu-1'))).toBe(true)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveText('1.284')
    await expect(page.getByTestId('daily-trend')).toBeVisible()

    // Tidak ada lagi pesan buntu "pakai versi web".
    await expect(page.getByTestId('production-line-unavailable')).toHaveCount(0)
    await expectSingleColumn(page)
    await expectNoHorizontalPageScroll(page)

    // business_unit_id memang dikirim untuk Admin — dan HANYA untuk Admin.
    expect(api.urls.some((url) => url.includes('/summary') && url.includes('business_unit_id=bu-1'))).toBe(true)
    // Dan angkanya menyertai SATU line.
    expect(api.urls.some((url) => url.includes('/summary') && url.includes('production_line_id=pl-1'))).toBe(true)
    // Daftar periode TIDAK PERNAH tersaring line — periode milik MILL.
    expect(
      api.urls.filter((url) => url.includes('/periods')).every((url) => !url.includes('production_line_id')),
    ).toBe(true)

    // Mengganti mill memuat ulang daftar periode DAN daftar line mill baru,
    // lalu membuang angka lama.
    const periodsBefore = api.hits.periods
    await page.getByTestId('mill-select').selectOption('bu-2')
    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveCount(0)

    // expect.poll: permintaan berangkat secara asinkron sesudah
    // selectOption, jadi asersi sinkron akan membaca hitungan lama.
    await expect.poll(() => api.hits.periods).toBe(periodsBefore + 1)
    expect(api.urls.some((url) => url.includes('/periods') && url.includes('business_unit_id=bu-2'))).toBe(true)
    await expect.poll(() => api.productionLineUrls.some((url) => url.includes('business_unit_id=bu-2'))).toBe(true)
  })

  // Scenario 3: "Operator membuka laporan"
  test('Operator — laporan terbuka penuh, tanpa kontrol ganti mill, nama mill adalah mill akunnya', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Tidak ada bagian yang dibatasi untuk peran ini.
    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveText('1.284')
    await expect(page.getByTestId('hourly-distribution')).toBeVisible()
    await expect(page.getByTestId('daily-trend')).toBeVisible()
    await expect(page.getByTestId('queue')).toBeVisible()
    await expect(page.getByTestId('daily-recap')).toBeVisible()

    // Tidak ada kontrol untuk mengganti mill.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.locator('main.laporan-ct-view select')).toHaveCount(1)
    await expect(page.getByTestId('mill-current')).toContainText('Mill Utara')
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

    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveCount(0)
    await expect(page.getByTestId('hourly-distribution')).toHaveCount(0)
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
    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveCount(0)
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('error-message')).toHaveCount(0)
    expect(api.hits.summary).toBe(0)
  })

  // Scenario 6: "periode tanpa data"
  test('periode tanpa data — angka utama nol, keterangan tampil, tanpa kanvas grafik kosong', async ({ page }) => {
    await login(page)
    await stubApi(page, { summary: EMPTY_SUMMARY })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('empty-period')).toBeVisible()
    await expect(page.getByTestId('empty-period')).toContainText('Belum ada data')

    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveText('0')
    await expect(page.getByTestId('kpi-total-cages-out')).toHaveText('0')
    // null tetap keterangan, bukan angka nol yang menyesatkan.
    await expect(page.getByTestId('kpi-peak-hour')).toHaveText('tidak tersedia')
    await expect(page.getByTestId('kpi-longest-gap')).toHaveText('tidak dapat dihitung')

    // Grafik TIDAK dirender sebagai kotak kosong.
    await expect(page.getByTestId('hourly-distribution')).toHaveCount(0)
    await expect(page.getByTestId('daily-trend')).toHaveCount(0)
    await expect(page.getByTestId('daily-recap')).toHaveCount(0)
  })

  // Scenario 7: "hari dengan record tetapi tanpa rincian per jam"
  test('hari ber-record tanpa rincian per jam — barisnya terbaca bernilai 0 dan ikut sebagai pembagi', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')
    await recapToggle(page).click()

    const rows = page.getByTestId('daily-recap').locator('tbody tr')
    await expect(rows).toHaveCount(2)
    await expect(rows.nth(1)).toContainText('14 Mar 2026')
    await expect(rows.nth(1)).toContainText('0')
    // Nilai yang memang tidak tercatat tidak dipalsukan menjadi 0.
    await expect(rows.nth(1)).toContainText('tidak tercatat')

    // Rata-rata per hari mencerminkan tanggal itu sebagai pembagi — nilai
    // server apa adanya, bukan 1284/14.
    await expect(page.getByTestId('kpi-avg-cages-per-day')).toHaveText('91,7')
    await expect(page.getByTestId('days-with-records')).toContainText('14')
  })

  // Scenario 8: "seluruh hari jendela operasinya tidak dapat dihitung"
  test('tanpa jendela operasi terhitung — durasi rata-rata terbaca tidak tersedia, bukan 0 jam', async ({ page }) => {
    await login(page)
    await stubApi(page, {
      summary: makeSummary({
        kpi: { ...FULL_KPI, avg_tippler_duration_hours: null, days_without_valid_window: 14 },
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('kpi-avg-tippler-duration')).toHaveText('tidak tersedia')
    await expect(page.getByTestId('kpi-avg-tippler-duration')).not.toHaveText('0')

    const card = page
      .getByTestId('kpi-avg-tippler-duration')
      .locator('xpath=ancestor::div[@class="metric-card"]')
    await expect(card).not.toContainText('0 jam')
    // Jumlah hari yang dikeluarkan dari perhitungan terbaca di layar.
    await expect(page.getByTestId('days-without-valid-window')).toContainText('14')
  })

  // Scenario 9: "penumpahan hanya pada satu jam"
  test('penumpahan satu jam saja — jeda terpanjang terbaca tidak dapat dihitung, tanpa angka 0 jam', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page, {
      summary: makeSummary({ kpi: { ...FULL_KPI, longest_gap_hours: null, longest_gap_date: null } }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('kpi-longest-gap')).toHaveText('tidak dapat dihitung')
    await expect(page.getByTestId('kpi-longest-gap-date')).toHaveCount(0)

    const card = page.getByTestId('kpi-longest-gap').locator('xpath=ancestor::div[@class="metric-card"]')
    await expect(card).not.toContainText('0 jam')
  })

  // Scenario 10: "operasi melewati tengah malam"
  test('operasi melewati tengah malam — jam operasi positif dan jeda tidak melonjak jadi nilai semu', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page, {
      summary: makeSummary({
        kpi: { ...FULL_KPI, longest_gap_hours: 3, longest_gap_date: '2026-03-02' },
        daily: [
          {
            // Jendela 22:00 -> 04:00 hari berikutnya = 6 jam, bukan -18.
            date: '2026-03-02',
            cages_tipped: 120,
            cages_out: 118,
            operating_hours: 6,
            idle_operating_hours: 2,
            longest_gap_hours: 3,
            min_remaining: 5,
          },
        ],
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')
    await recapToggle(page).click()

    const row = page.getByTestId('daily-recap').locator('tbody tr').first()
    await expect(row).toContainText('6 jam')
    await expect(row).not.toContainText('-18')

    await expect(page.getByTestId('kpi-longest-gap')).toHaveText('3')
    await expect(page.getByTestId('kpi-longest-gap-date')).toContainText('02 Mar 2026')
  })

  // Scenario 11: "mencoba melihat mill lain"
  test('mencoba melihat mill lain — business_unit_id dari URL diabaikan, mill akun yang tetap tampil', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page)

    // business_unit_id mill lain dicantumkan pada permintaan halaman.
    await openReport(page, `${ROUTE}?business_unit_id=bu-99`)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveText('1.284')
    await expect(page.getByTestId('mill-current')).toContainText('Mill Utara')
    // Tidak ada kontrol pada layar untuk berpindah mill.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)

    // Tidak satu pun permintaan membawa business_unit_id.
    expect(api.urls.length).toBeGreaterThan(0)
    for (const url of api.urls) {
      expect(url).not.toContain('business_unit_id')
    }
  })

  // Scenario 12: "akun belum terhubung ke mill"
  test('akun tanpa mill — pesan hubungi Admin, tanpa daftar mill, dan NOL permintaan laporan', async ({ page }) => {
    await login(page, USERS.supervisor)
    await becomeMilllessSession(page)
    const api = await stubApi(page)
    await openReport(page)

    await expect(page.getByTestId('no-mill-for-account')).toBeVisible()
    await expect(page.getByTestId('no-mill-for-account')).toContainText('hubungi Admin')
    // Daftar seluruh mill TIDAK ditawarkan kepada yang tidak berhak.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveCount(0)

    expect(api.hits.total).toBe(0)
    expect(api.urls).toEqual([])
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
    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveText('1.284')
    await expect(page.getByTestId('period-select')).toHaveValue('per-1')
  })

  // Scenario 14: "sesi berakhir"
  test('sesi berakhir — 401 memindahkan ke Login sebelum satu angka pun tampil', async ({ page }) => {
    await login(page)
    await stubApi(page, { summaryStatus: 401 })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await page.waitForURL('**/login**')
    await expect(page.getByTestId('laporan-cages-track-mobile')).toHaveCount(0)
    // 401 bukan kegagalan jaringan: tidak ada pesan "periksa koneksi".
    await expect(page.getByTestId('network-error')).toHaveCount(0)
  })

  // Scenario 15: "periode tertutup"
  test('periode tertutup — laporan penuh, status Tertutup terbaca, unduhan CSV tetap berjalan', async ({ page }) => {
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

    await expect(page.getByTestId('period-status-badge')).toHaveText('Tertutup')
    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveText('1.284')
    await expect(page.getByTestId('hourly-distribution')).toBeVisible()
    await expect(page.getByTestId('daily-trend')).toBeVisible()
    await expect(page.getByTestId('queue')).toBeVisible()
    await expect(page.getByTestId('export-button')).toBeEnabled()

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByTestId('export-button').click(),
    ])
    expect(download.suggestedFilename()).toMatch(/^laporan-cages-track_.*\.csv$/)
  })

  // Scenario 16: "layar hanya membaca, tanpa aksi tulis"
  test('layar hanya membaca — dari angka utama sampai rekap harian tidak ada kontrol pengubah data', async ({
    page,
  }) => {
    await login(page)
    const api = await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')
    await recapToggle(page).click()
    await page.getByTestId('daily-recap').scrollIntoViewIfNeeded()

    await expect(page.locator('main.laporan-ct-view input')).toHaveCount(0)
    await expect(page.locator('main.laporan-ct-view textarea')).toHaveCount(0)
    await expect(page.locator('main.laporan-ct-view form')).toHaveCount(0)
    await expect(page.getByRole('button', { name: /simpan|hapus|ubah data|tambah/i })).toHaveCount(0)

    // Satu-satunya pemilih adalah Periode (peran terikat mill).
    await expect(page.locator('main.laporan-ct-view select')).toHaveCount(1)

    // Seluruh permintaan yang dikirim halaman adalah GET laporan.
    expect(api.urls.length).toBeGreaterThan(0)
    expect(api.urls.every((url) => url.includes('cages-track-reports'))).toBe(true)
  })

  // Scenario 17: "angka ponsel sama persis dengan laporan versi web"
  test('angka sama dengan laporan web — seluruh nilai apa adanya dari respons, tanpa perhitungan ulang', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // KPI apa adanya — BUKAN jumlah baris harian (96 + 0).
    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveText('1.284')
    await expect(page.getByTestId('kpi-total-cages-out')).toHaveText('1.310')
    await expect(page.getByTestId('kpi-avg-cages-per-day')).toHaveText('91,7')
    await expect(page.getByTestId('kpi-peak-hour')).toHaveText('09.00')
    await expect(page.getByTestId('kpi-idle-hours')).toHaveText('9')
    await expect(page.getByTestId('kpi-longest-gap')).toHaveText('4')
    await expect(page.getByTestId('queue-min-remaining')).toHaveText('4')
    await expect(page.getByTestId('queue-avg-remaining')).toHaveText('11,3')

    // Blok `total` apa adanya (777/888/14), meski bertentangan dengan kpi.
    await recapToggle(page).click()
    await expect(page.getByTestId('daily-recap-total')).toContainText('777')
    await expect(page.getByTestId('daily-recap-total')).toContainText('888')
    await expect(page.getByTestId('daily-recap-total')).toContainText('14 hari')
  })

  // Scenario 18: "periode yang tidak mencakup Cages & Tracks tidak ditawarkan"
  test('pemilih Periode — hanya periode yang punya baris Cages Track yang terbaca, termasuk periode lintas stasiun', async ({
    page,
  }) => {
    await login(page)
    // Server memang tidak mengembalikan periode jenis lain (itu diuji di
    // backend); yang dibuktikan di sini adalah layar tidak menambahkan apa
    // pun ke daftar yang diterimanya.
    await stubApi(page, { periods: [PERIOD_CT, PERIOD_LINTAS_STASIUN] })
    await openReport(page)

    const options = page.getByTestId('period-select').locator('option')
    await expect(options).toHaveCount(3)
    await expect(options.nth(1)).toHaveText('Cages Track — Periode Maret 2026')
    await expect(options.nth(2)).toHaveText('Cages Track — Periode Lintas Stasiun Maret 2026')
    await expect(page.getByTestId('period-select')).not.toContainText('Sterilizer')
  })

  // Scenario 19: "rentang periode inklusif di kedua ujung"
  test('rentang inklusif — tanggal mulai dan tanggal akhir terbaca pada rekap dan tren harian', async ({ page }) => {
    await login(page)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Tren harian memuat kedua ujung rentang.
    await expect(page.getByTestId('daily-trend')).toContainText('01')
    await expect(page.getByTestId('daily-trend')).toContainText('14')

    await recapToggle(page).click()

    const rows = page.getByTestId('daily-recap').locator('tbody tr')
    await expect(rows).toHaveCount(2)
    await expect(rows.nth(0)).toContainText('01 Mar 2026')
    await expect(rows.nth(1)).toContainText('14 Mar 2026')

    // Ujung rentang tidak dipangkas dan tidak ada tanggal di luar rentang.
    await expect(page.getByTestId('daily-recap')).not.toContainText('28 Feb 2026')
    await expect(page.getByTestId('daily-recap')).not.toContainText('15 Mar 2026')
    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveText('1.284')
  })

  // Scenario 20: "jam menganggur hanya di dalam jendela operasi tippler"
  test('jam menganggur — angka server apa adanya, dan jam di luar jendela terbaca sebagai di luar jendela', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('kpi-idle-hours')).toHaveText('9')
    const idleCard = page.getByTestId('kpi-idle-hours').locator('xpath=ancestor::div[@class="metric-card"]')
    await expect(idleCard).toContainText('Hanya dihitung di dalam jam operasi tippler')

    // Jam 05 di luar jendela, jam 06 di dalam — penandanya berbeda.
    await expect(page.getByTestId('hourly-bar-5')).toHaveAttribute('data-within-window', 'false')
    await expect(page.getByTestId('hourly-bar-6')).toHaveAttribute('data-within-window', 'true')
    await expect(page.getByTestId('hourly-distribution')).toContainText('Di luar jendela operasi')
  })

  // Scenario 21: "jeda terpanjang tidak melintasi pergantian tanggal"
  test('jeda terpanjang — dibaca sebagai jeda di dalam satu tanggal beserta tanggalnya', async ({ page }) => {
    await login(page)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('kpi-longest-gap')).toHaveText('4')
    // Satu tanggal tunggal, bukan rentang lintas tanggal.
    await expect(page.getByTestId('kpi-longest-gap-date')).toContainText('08 Mar 2026')
    await expect(page.getByTestId('kpi-longest-gap-date')).not.toContainText('09 Mar')

    const gapCard = page.getByTestId('kpi-longest-gap').locator('xpath=ancestor::div[@class="metric-card"]')
    await expect(gapCard).toContainText('tidak melintasi pergantian hari')
  })

  // Scenario 22: "antrean lori tersisa tidak pernah dijumlahkan"
  test('antrean lori — terendah dan rata-rata dengan keterangan potret per jam, tanpa total lintas jam', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('queue-min-remaining')).toHaveText('4')
    await expect(page.getByTestId('queue-avg-remaining')).toHaveText('11,3')
    await expect(page.getByTestId('queue-snapshot-note')).toContainText('potret per jam')
    await expect(page.getByTestId('queue-snapshot-note')).toContainText('tidak pernah dijumlahkan')

    // Tidak ada angka total antrean di layar.
    await expect(page.getByTestId('queue')).not.toContainText(/total antrean|jumlah antrean/i)
    await expect(page.getByTestId('queue')).not.toContainText('15,3') // 4 + 11,3
  })

  // Scenario 23: "rekap harian dapat ditutup agar layar ponsel tetap terbaca"
  test('rekap harian — tertutup bawaan, dibuka utuh, tetap satu kolom, dan tanpa permintaan jaringan baru', async ({
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
    expect(summaryHits).toBe(1)

    // TERTUTUP secara bawaan. CollapsibleSection memakai v-show, jadi
    // barisnya tetap ada di DOM — yang diasersi adalah KETERLIHATANNYA.
    await expect(recapBody(page)).not.toBeVisible()
    await expect(page.getByTestId('daily-recap').locator('tbody tr')).toHaveCount(30)
    await expect(page.getByTestId('daily-recap').locator('tbody tr').first()).not.toBeVisible()

    // Angka utama dan grafik tetap terbaca tanpa gulir panjang.
    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveText('1.284')
    await expect(page.getByTestId('hourly-distribution')).toBeVisible()
    await expectNoHorizontalPageScroll(page)

    // Dibuka: seluruh 30 baris tampil utuh.
    await recapToggle(page).click()
    await expect(recapBody(page)).toBeVisible()
    const rows = page.getByTestId('daily-recap').locator('tbody tr')
    await expect(rows).toHaveCount(30)
    await expect(rows.first()).toBeVisible()
    await expect(rows.nth(29)).toContainText('30 Mar 2026')

    // Halaman tetap satu kolom tanpa gulir mendatar; yang menggulir adalah
    // TABEL di dalam kartunya sendiri.
    await expectNoHorizontalPageScroll(page)
    await expectCardScrollsInsideItself(page, '[data-testid="daily-recap"] .detail-table-wrap')

    // Ditutup lagi, lalu dibuka lagi.
    await recapToggle(page).click()
    await expect(recapBody(page)).not.toBeVisible()
    await recapToggle(page).click()
    await expect(recapBody(page)).toBeVisible()
    await expect(page.getByTestId('daily-recap').locator('tbody tr')).toHaveCount(30)

    // ASERSI INTINYA: tidak ada permintaan jaringan baru sama sekali.
    expect(api.hits.summary).toBe(summaryHits)
    expect(api.hits.total).toBe(api.hits.units + api.hits.periods + api.hits.summary + api.hits.export)

    // Sasaran sentuh tombol buka/tutup memenuhi ukuran jari.
    const toggleBox = await recapToggle(page).boundingBox()
    expect(toggleBox).not.toBeNull()
    expect(toggleBox!.height).toBeGreaterThanOrEqual(44)
  })

  // Scenario 24: "mengunduh rincian penumpahan per jam sebagai CSV"
  test('ekspor CSV — berkas terunduh dan tidak ada data stasiun yang berubah', async ({ page }) => {
    await login(page)
    const api = await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByTestId('export-button').click(),
    ])

    expect(download.suggestedFilename()).toMatch(/^laporan-cages-track_.*\.csv$/)
    expect(api.hits.export).toBe(1)
    // Isi CSV dibentuk SERVER (satu baris per rincian penumpahan per jam).
    expect(api.urls.some((url) => url.includes('/export') && url.includes('format=csv'))).toBe(true)

    // Ekspor tidak menulis apa pun: seluruh permintaan adalah GET laporan.
    expect(api.urls.every((url) => url.includes('cages-track-reports'))).toBe(true)
  })

  /* ---------------------------------------------------------------- */
  /* Tata letak ponsel — klaim browser_test yang berulang pada banyak  */
  /* skenario, dikumpulkan sekali di sini supaya kegagalannya menunjuk */
  /* tepat ke penyebabnya alih-alih menular ke 24 test sekaligus.      */
  /* ---------------------------------------------------------------- */

  test('tata letak ponsel — grafik 24 jam dan tren harian menggulir di dalam kartunya, halaman tidak', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page, { summary: makeSummary({ daily: THIRTY_DAYS }) })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // 24 batang jam jauh lebih lebar daripada 390px — dan tetap menggulir
    // DI DALAM kartunya.
    await expectCardScrollsInsideItself(page, '[data-testid="hourly-distribution"] .chart-scroll')
    // Tren 30 tanggal, sama halnya.
    await expectCardScrollsInsideItself(page, '[data-testid="daily-trend"] .chart-scroll')

    await recapToggle(page).click()
    await expectCardScrollsInsideItself(page, '[data-testid="daily-recap"] .detail-table-wrap')

    // Meski ketiganya lebih lebar dari layar, HALAMAN tidak pernah
    // menggulir mendatar.
    await expectNoHorizontalPageScroll(page)
    await expectSingleColumn(page)
    await expectTouchTargets(page, ['period-select', 'export-button', 'back-button', 'hamburger-button'])
  })
})

/* ================================================================== */
/* Production Line wajib (2026-09-28)                                  */
/* ================================================================== */

/**
 * Laporan Cages & Tracks memulangkan ANGKA GABUNGAN satu periode. Menggabungkan
 * beberapa Production Line ke dalam satu angka menghasilkan bilangan yang
 * tidak dapat ditindaklanjuti siapa pun — karena itu memilih line di sini
 * WAJIB dan tidak ada opsi "semua line". (Data Browser versi web memang
 * punya opsi "Semua Line"; di sana barisnya tetap terpisah per record.)
 *
 * FIXTURE DUA LINE SENGAJA BERBEDA NILAINYA, DAN JUMLAH KEDUANYA SENGAJA
 * TIDAK MUNCUL DI MANA PUN — layar yang diam-diam menggabungkan akan
 * menampilkan jumlahnya dan gagal di sini. Jangan "merapikan".
 */
const SUMMARY_LINE_1 = makeSummary({
  production_line: { id: 'pl-1', name: 'Line 1' },
  kpi: { ...FULL_KPI, total_cages_tipped: 6421 },
})

const SUMMARY_LINE_2 = makeSummary({
  production_line: { id: 'pl-2', name: 'Line 2' },
  kpi: { ...FULL_KPI, total_cages_tipped: 233 },
})

const SUMMARY_BY_LINE = { 'pl-1': SUMMARY_LINE_1, 'pl-2': SUMMARY_LINE_2 }

test.describe('Laporan Cages & Tracks Mobile (screen-136) — Production Line wajib', () => {
  // Viewport ponsel — sama seperti describe utama berkas ini. Tanpa ini
  // klaim satu kolom / sasaran sentuh 44px diuji pada lebar desktop.
  test.use({ viewport: { width: 390, height: 844 } })

  test('dua line — pemilih tampil, NOL angka sebelum memilih, lalu angka MILIK LINE ITU', async ({ page }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page, { productionLines: TWO_LINES, summaryByLine: SUMMARY_BY_LINE })
    await openReport(page)

    const lineSelect = page.getByTestId('production-line-select')
    await expect(lineSelect).toBeVisible()
    await expect(lineSelect.locator('option')).toHaveCount(3) // placeholder + 2 line
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    await expect(page.getByTestId('production-line-required-hint')).toContainText('Pilih Production Line')
    // Tidak ada opsi gabungan yang diam-diam ditawarkan.
    await expect(page.getByText(/semua line/i)).toHaveCount(0)

    // Periode boleh dipilih — /periods memang TIDAK tersaring line — tetapi
    // angkanya tetap ditahan.
    await pickPeriod(page, 'per-1')
    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveCount(0)
    await expect(page.getByTestId('export-button')).toHaveCount(0)
    expect(api.hits.summary).toBe(0)

    // Sasaran sentuh pemilih line: 44px pada kedua sisi.
    const box = await lineSelect.boundingBox()
    expect(box).not.toBeNull()
    expect(box!.height).toBeGreaterThanOrEqual(44)
    expect(box!.width).toBeGreaterThanOrEqual(44)

    await lineSelect.selectOption('pl-1')

    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveText('6.421')
    // Jumlah kedua line TIDAK PERNAH muncul.
    await expect(page.getByText('6.654', { exact: false })).toHaveCount(0)
    expect(api.urls.some((url) => url.includes('/summary') && url.includes('production_line_id=pl-1'))).toBe(true)

    // Ganti line: permintaannya membawa id baru, angka lama tidak tertinggal.
    await lineSelect.selectOption('pl-2')
    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveText('233')
    expect(api.urls.some((url) => url.includes('/summary') && url.includes('production_line_id=pl-2'))).toBe(true)

    // Tidak ada gulir mendatar halaman setelah pemilih line ikut terender.
    const overflow = await page.evaluate(() => ({
      scrollWidth: document.documentElement.scrollWidth,
      clientWidth: document.documentElement.clientWidth,
    }))
    expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1)
  })

  test('nama line yang berlaku terbaca, dan ekspor membawa production_line_id', async ({ page }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page, { productionLines: TWO_LINES, summaryByLine: SUMMARY_BY_LINE })
    await openReport(page)

    await page.getByTestId('production-line-select').selectOption('pl-2')
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('production-line-current')).toContainText('Line 2')

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByTestId('export-button').click(),
    ])
    // Nama berkas TIDAK berubah oleh fitur ini — kolom CSV pun tidak.
    expect(download.suggestedFilename()).toMatch(/^laporan-cages-track_.*\.csv$/)
    expect(api.urls.some((url) => url.includes('/export') && url.includes('production_line_id=pl-2'))).toBe(true)
  })

  test('ingatan line dari layar Daftar Stasiun dipakai ulang — pengguna TIDAK diminta memilih dua kali', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    // Kunci yang sama persis yang ditulis StationListView.vue setelah
    // pengguna memilih line di layar Daftar Stasiun.
    const userId = await getAuthUserId(page)
    await page.evaluate(
      ([key, value]) => window.localStorage.setItem(key, value),
      [REMEMBERED_LINE_KEY(userId), 'pl-2'] as const,
    )

    const api = await stubApi(page, { productionLines: TWO_LINES, summaryByLine: SUMMARY_BY_LINE })
    await openReport(page)

    // Tidak ada pertanyaan kedua.
    await expect(page.getByTestId('production-line-required-hint')).toHaveCount(0)
    await expect(page.getByTestId('production-line-select')).toHaveValue('pl-2')
    await expect(page.getByTestId('production-line-current')).toContainText('Line 2')

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveText('233')
    expect(api.urls.some((url) => url.includes('/summary') && url.includes('production_line_id=pl-2'))).toBe(true)
  })

  test('production_line_id pada URL (dibawa screen-141) langsung berlaku', async ({ page }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page, { productionLines: TWO_LINES, summaryByLine: SUMMARY_BY_LINE })
    await openReport(page, `${ROUTE}?production_line_id=pl-2`)

    await expect(page.getByTestId('production-line-required-hint')).toHaveCount(0)
    await expect(page.getByTestId('production-line-select')).toHaveValue('pl-2')

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveText('233')
    expect(api.urls.some((url) => url.includes('/summary') && url.includes('production_line_id=pl-2'))).toBe(true)
  })

  test('daftar periode TIDAK PERNAH membawa production_line_id — periode milik mill', async ({ page }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page, { productionLines: TWO_LINES, summaryByLine: SUMMARY_BY_LINE })
    await openReport(page)

    await page.getByTestId('production-line-select').selectOption('pl-2')
    await pickPeriod(page, 'per-1')

    const periodUrls = api.urls.filter((url) => url.includes('/periods'))
    expect(periodUrls.length).toBeGreaterThan(0)
    expect(periodUrls.every((url) => !url.includes('production_line_id'))).toBe(true)
  })

  test('daftar line gagal dimuat — tanpa angka, dengan arahan dan tombol coba lagi', async ({ page }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page, { productionLinesAbort: true })
    await openReport(page)

    await expect(page.getByTestId('production-line-unavailable')).toBeVisible()
    await expect(page.getByTestId('production-line-retry')).toBeVisible()
    await expect(page.getByTestId('network-error')).toHaveCount(0)

    await pickPeriod(page, 'per-1')
    await expect(page.getByTestId('kpi-total-cages-tipped')).toHaveCount(0)
    expect(api.hits.summary).toBe(0)
  })
})
