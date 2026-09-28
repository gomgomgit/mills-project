import { test, expect, type Page } from '@playwright/test'
import { getAuthUserId, login, USERS } from './helpers'

/**
 * laporan-sterilizer.spec.ts — screen-135--laporan-sterilizer-mobile /
 * usecase-135--laporan-sterilizer-mobile "Lihat Laporan Periode Sterilizer
 * (Mobile)".
 *
 * Satu test per test_scenarios yang punya browser_test — seluruh 18.
 * Berjalan di Vite dev server (playwright.config.ts, baseURL
 * http://localhost:5174): aplikasi Capacitor adalah SPA biasa sebelum
 * dibungkus native, jadi browser test layar mobile TIDAK ditangguhkan
 * sebagai "mobile-only" (lihat header playwright.config.ts).
 *
 * MASUK LANGSUNG KE RUTE, BUKAN LEWAT KARTU. Layar ini belum punya pintu
 * masuk UI — screen-141 (Reporting: pilih stasiun) belum ada — sehingga
 * setiap test menavigasi langsung ke '/reports/sterilizer'. Begitu
 * screen-141 dibuat, test yang menekan kartu adalah milik screen-141,
 * bukan berkas ini.
 *
 * RESPONS API DI-STUB DI TINGKAT JARINGAN (page.route), BUKAN DISEMAI KE
 * DATABASE. Ini keputusan sadar, bukan jalan pintas:
 *
 *   - Layar ini read-only total. Ia tidak punya satu pun UI untuk membuat
 *     data yang dibacanya, jadi menyemai fixture berarti menjalankan dua
 *     layar WEB lain (screen-128 periode + screen-126 input siklus) dari
 *     dalam suite mobile — persis yang dilakukan
 *     e2e-web/tests/laporan-sterilizer.spec.ts, dan sudah dilakukan di
 *     sana.
 *   - Kebenaran ANGKA-nya memang bukan tanggung jawab layar ini: seluruh
 *     angka berasal dari SterilizerReportService, yang sudah diuji penuh
 *     di backend (Feature + Unit) dan di e2e-web terhadap data sungguhan.
 *     Yang khas mobile — dan hanya dapat dibuktikan di sini — adalah
 *     PEMETAAN respons itu ke layar satu kolom: null jadi "tidak
 *     tersedia", 401 jadi Login, jaringan putus tidak membuang periode
 *     terpilih, dan akun tanpa mill tidak memicu satu permintaan pun.
 *   - Beberapa skenario mustahil dibuat dari data sungguhan tanpa merusak
 *     lingkungan (sesi kedaluwarsa, jaringan putus, akun tanpa mill).
 *
 * ANGKA FIXTURE SENGAJA TIDAK KONSISTEN SECARA ARITMETIKA — kpi.total_cycles
 * (1234), jumlah baris daily (12 + 18 = 30), dan total.cycles (999) saling
 * bertentangan. Layar yang benar menampilkan ketiganya apa adanya; layar
 * yang diam-diam menghitung ulang akan gagal di sini. Jangan "merapikan".
 */

const ROUTE = '/reports/sterilizer'
const API_GLOB = '**/api/sterilizer-reports/**'

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
 * kpi memakai sufiks _minutes; daily/by_unit/total tidak. periods dan
 * business-units/options dibungkus { data: [...] }, sedangkan summary
 * dikirim TANPA pembungkus. Itu bentuk yang benar-benar dikirim
 * App\Http\Controllers\Api\SterilizerReportController.
 */
const PERIOD_STER = {
  id: 'per-1',
  name: 'Periode Agustus 2026',
  start_date: '2026-08-01',
  end_date: '2026-08-31',
  status: 'open',
  station_type: 'sterilizer',
  station_type_label: 'Sterilizer',
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
  name: 'Periode Lintas Stasiun Agustus 2026',
  start_date: '2026-08-01',
  end_date: '2026-08-31',
  status: 'open',
  station_type: 'sterilizer',
  station_type_label: 'Sterilizer',
}

const PERIOD_CLOSED = {
  id: 'per-3',
  name: 'Periode Juli 2026',
  start_date: '2026-07-01',
  end_date: '2026-07-31',
  status: 'closed',
  station_type: 'sterilizer',
  station_type_label: 'Sterilizer',
}

const BUSINESS_UNITS = [
  { id: 'bu-1', name: 'Mill Utara' },
  { id: 'bu-2', name: 'Mill Selatan' },
]

function makeSummary(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    period: {
      id: 'per-1',
      name: 'Periode Agustus 2026',
      start_date: '2026-08-01',
      end_date: '2026-08-31',
      status: 'open',
      business_unit_name: 'Business Unit A',
    },
    kpi: {
      total_cycles: 1234,
      total_cages: 15678,
      avg_duration_minutes: 95.5,
      min_duration_minutes: 70,
      max_duration_minutes: 130,
      cycles_without_duration: 4,
      triple_peak_compliance_percent: 87.5,
    },
    daily: [
      {
        date: '2026-08-01',
        cycles: 12,
        cages: 144,
        avg_duration: 94,
        min_duration: 80,
        max_duration: 110,
        triple_peak_complete: 10,
        cycles_without_duration: 1,
      },
      {
        date: '2026-08-31',
        cycles: 18,
        cages: 216,
        avg_duration: null,
        min_duration: null,
        max_duration: null,
        triple_peak_complete: 0,
        cycles_without_duration: 18,
      },
    ],
    by_unit: [
      { sterilizer_no: '1', cycles: 700, cages: 8400, avg_duration: 96, triple_peak_complete: 600 },
      { sterilizer_no: '2', cycles: 534, cages: 7278, avg_duration: null, triple_peak_complete: 480 },
    ],
    outliers: {
      method: 'iqr',
      q1: 80,
      q3: 120,
      iqr: 40,
      lower_bound: 20,
      upper_bound: 180,
      sample_size: 30,
      min_sample_size: 8,
      insufficient_data: false,
      items: [
        {
          date: '2026-08-01',
          sterilizer_no: '1',
          duration_minutes: 200,
          number_of_cages: 12,
          cages_status: null,
          close_door_time: '07:00',
          open_door_time: '10:20',
        },
        {
          date: '2026-08-31',
          sterilizer_no: '2',
          duration_minutes: 15,
          number_of_cages: 8,
          cages_status: null,
          close_door_time: '08:00',
          open_door_time: '08:15',
        },
      ],
    },
    total: {
      cycles: 999,
      cages: 8888,
      avg_duration: 95.5,
      min_duration: 70,
      max_duration: 130,
      triple_peak_complete: 777,
      cycles_without_duration: 4,
    },
    ...overrides,
  }
}

const EMPTY_SUMMARY = makeSummary({
  kpi: {
    total_cycles: 0,
    total_cages: 0,
    avg_duration_minutes: null,
    min_duration_minutes: null,
    max_duration_minutes: null,
    cycles_without_duration: 0,
    triple_peak_compliance_percent: 0,
  },
  daily: [],
  by_unit: [],
  total: {
    cycles: 0,
    cages: 0,
    avg_duration: null,
    min_duration: null,
    max_duration: null,
    triple_peak_complete: 0,
    cycles_without_duration: 0,
  },
})

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
    periods: [PERIOD_STER],
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
        body: 'Tanggal,Siklus\n2026-08-01,12\n',
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

/**
 * Menjadikan sesi yang tersimpan sebagai sesi Admin (peran admin, tanpa
 * mill). Tidak ada akun Admin pada helpers.USERS, dan yang diuji di sini
 * memang bukan proses login Admin (itu milik screen-002) melainkan
 * perilaku layar terhadap peran yang tidak terikat mill. Seluruh endpoint
 * laporan sudah di-stub, jadi token milik siapa pun tidak dipakai server.
 */
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
  await expect(page.getByTestId('laporan-sterilizer-mobile')).toBeVisible()
}

async function pickPeriod(page: Page, periodId: string): Promise<void> {
  await page.getByTestId('period-select').selectOption(periodId)
}

/** Membuka Rekap Harian — CollapsibleSection tertutup secara bawaan. */
function recapToggle(page: Page) {
  return page.getByTestId('daily-recap').getByTestId('collapsible-section-toggle')
}

function recapBody(page: Page) {
  return page.getByTestId('daily-recap').getByTestId('collapsible-section-body')
}

/* ================================================================== */

test.describe('Laporan Sterilizer Mobile (screen-135)', () => {
  // VIEWPORT PONSEL — ditambahkan 2026-09-27, dan berkas ini adalah satu-satunya
  // dari lima spec laporan mobile yang tidak memilikinya.
  //
  // playwright.config.ts memakai devices['Desktop Chrome'] (1280px) sebagai
  // satu-satunya project. Tanpa override ini, SELURUH klaim tata letak di berkas
  // ini diuji pada lebar desktop: satu kolom, sasaran sentuh 44x44, dan
  // "scrollWidth tidak melebihi clientWidth" semuanya lolos karena layarnya
  // memang lapang — bukan karena layar ponselnya benar. Test yang hijau di
  // viewport yang salah lebih buruk daripada tidak ada test, karena ia
  // menghabiskan anggaran kepercayaan tanpa membuktikan apa pun.
  //
  // Screen-135 adalah layar laporan mobile PERTAMA; empat berikutnya
  // (136-139) memasang override ini sejak awal. Ini menutup satu-satunya
  // yang tertinggal.
  test.use({ viewport: { width: 390, height: 844 } })

  // Scenario: "success"
  test('success — Operator: mill akun, seluruh bagian tampil, rekap dibuka-tutup, ekspor mengunduh CSV', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page)
    await openReport(page)

    // Pengguna terikat mill: pemilih Mill tidak ditawarkan sama sekali.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.getByTestId('mill-current')).toBeVisible()

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('mill-current')).toContainText('Business Unit A')
    await expect(page.getByTestId('period-meta')).toContainText('Periode Agustus 2026')
    await expect(page.getByTestId('period-status-badge')).toHaveText('Terbuka')

    await expect(page.getByTestId('kpi-total-cycles')).toHaveText('1.234')
    await expect(page.getByTestId('kpi-total-cages')).toHaveText('15.678')
    await expect(page.getByTestId('kpi-avg-duration')).toHaveText('95,5')
    await expect(page.getByTestId('kpi-triple-peak')).toHaveText('87,5')
    await expect(page.getByTestId('cycles-without-duration')).toContainText('4')

    await expect(page.getByTestId('daily-trend')).toBeVisible()
    await expect(page.getByTestId('duration-distribution')).toBeVisible()
    await expect(page.getByTestId('by-unit')).toBeVisible()
    await expect(page.getByTestId('outlier-threshold')).toBeVisible()
    await expect(page.getByTestId('outlier-list')).toBeVisible()
    await expect(page.getByTestId('daily-recap')).toBeVisible()

    // Rekap harian: tertutup bawaan, dapat dibuka, dapat ditutup lagi.
    await expect(recapBody(page)).toBeHidden()
    await recapToggle(page).click()
    await expect(recapBody(page)).toBeVisible()
    await recapToggle(page).click()
    await expect(recapBody(page)).toBeHidden()
    await recapToggle(page).click()
    await expect(recapBody(page)).toBeVisible()

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByTestId('export-button').click(),
    ])
    expect(download.suggestedFilename()).toMatch(/^laporan-sterilizer_.*\.csv$/)
    expect(api.hits.export).toBe(1)

    // Layar baca: tidak ada satu pun permintaan tulis data stasiun.
    const writes = api.urls.filter((url) => !url.includes('sterilizer-reports'))
    expect(writes).toEqual([])
  })

  // Scenario: "success as Admin"
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
  test('success as Admin — mill-select berisi lebih dari satu mill, daftar line + ringkasan muncul setelah mill + periode dipilih', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await becomeAdminSession(page)
    const api = await stubApi(page)
    await openReport(page)

    await expect(page.getByTestId('mill-select')).toBeVisible()
    await expect(page.getByTestId('mill-select').locator('option')).toHaveCount(3) // placeholder + 2 mill
    await expect(page.getByTestId('mill-required-hint')).toBeVisible()
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

    await expect(page.getByTestId('kpi-total-cycles')).toHaveText('1.234')
    await expect(page.getByTestId('kpi-total-cages')).toHaveText('15.678')
    await expect(page.getByTestId('daily-trend')).toBeVisible()
    await expect(page.getByTestId('by-unit')).toBeVisible()
    // Tidak ada lagi pesan buntu "pakai versi web".
    await expect(page.getByTestId('production-line-unavailable')).toHaveCount(0)

    // business_unit_id memang dikirim untuk Admin — dan HANYA untuk Admin.
    expect(api.urls.some((url) => url.includes('/summary') && url.includes('business_unit_id=bu-1'))).toBe(true)
    // Dan angkanya menyertai SATU line.
    expect(api.urls.some((url) => url.includes('/summary') && url.includes('production_line_id=pl-1'))).toBe(true)
    // Daftar periode TIDAK PERNAH tersaring line — periode milik MILL.
    expect(
      api.urls.filter((url) => url.includes('/periods')).every((url) => !url.includes('production_line_id')),
    ).toBe(true)

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByTestId('export-button').click(),
    ])
    expect(download.suggestedFilename()).toMatch(/^laporan-sterilizer_.*\.csv$/)

    // Mengganti mill memuat ulang daftar periode DAN daftar line mill baru.
    const periodsBefore = api.hits.periods
    await page.getByTestId('mill-select').selectOption('bu-2')
    // expect.poll: permintaan berangkat secara asinkron sesudah
    // selectOption, jadi asersi sinkron akan membaca hitungan lama.
    await expect.poll(() => api.hits.periods).toBe(periodsBefore + 1)
    expect(api.urls.some((url) => url.includes('/periods') && url.includes('business_unit_id=bu-2'))).toBe(true)
    await expect.poll(() => api.productionLineUrls.some((url) => url.includes('business_unit_id=bu-2'))).toBe(true)
  })

  // Scenario: "Mill belum punya periode"
  test('Mill belum punya periode — arahan menghubungi Admin, tanpa angka dan tanpa pesan teknis', async ({ page }) => {
    await login(page, USERS.supervisor)
    await stubApi(page, { periods: [] })
    await openReport(page)

    await expect(page.getByTestId('no-periods')).toBeVisible()
    await expect(page.getByTestId('no-periods')).toContainText('hubungi Admin')
    await expect(page.getByTestId('kpi-total-cycles')).toHaveCount(0)
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('error-message')).toHaveCount(0)
  })

  // Scenario: "Periode tanpa data"
  test('Periode tanpa data — empty-period, angka nol, tanpa grafik kosong', async ({ page }) => {
    await login(page)
    await stubApi(page, { summary: EMPTY_SUMMARY })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('empty-period')).toBeVisible()
    await expect(page.getByTestId('kpi-total-cycles')).toHaveText('0')
    await expect(page.getByTestId('kpi-total-cages')).toHaveText('0')
    await expect(page.getByTestId('daily-trend')).toHaveCount(0)
    await expect(page.getByTestId('duration-distribution')).toHaveCount(0)
  })

  // Scenario: "Seluruh siklus tanpa durasi"
  test('Seluruh siklus tanpa durasi — durasi "tidak tersedia", bukan nol', async ({ page }) => {
    await login(page)
    await stubApi(page, {
      summary: makeSummary({
        kpi: {
          total_cycles: 20,
          total_cages: 240,
          avg_duration_minutes: null,
          min_duration_minutes: null,
          max_duration_minutes: null,
          cycles_without_duration: 20,
          triple_peak_compliance_percent: 55,
        },
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('kpi-avg-duration')).toHaveText('tidak tersedia')
    await expect(page.getByTestId('kpi-avg-duration')).not.toHaveText('0')
    await expect(page.getByTestId('cycles-without-duration')).toBeVisible()
    await expect(page.getByTestId('cycles-without-duration')).toContainText('20')

    const avgCard = page.getByTestId('kpi-avg-duration').locator('xpath=ancestor::div[@class="metric-card"]')
    await expect(avgCard).toContainText('Terpendek tidak tersedia')
    await expect(avgCard).toContainText('Terlama tidak tersedia')
    await expect(avgCard).not.toContainText('Terpendek 0')
    await expect(avgCard).not.toContainText('Terlama 0')
  })

  // Scenario: "Siklus terlalu sedikit untuk menentukan ambang"
  test('Siklus terlalu sedikit — insufficient-data, tanpa ambang dan tanpa siklus ditandai', async ({ page }) => {
    await login(page)
    await stubApi(page, {
      summary: makeSummary({
        outliers: {
          method: 'iqr',
          q1: null,
          q3: null,
          iqr: null,
          lower_bound: null,
          upper_bound: null,
          sample_size: 3,
          min_sample_size: 8,
          insufficient_data: true,
          items: [],
        },
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('insufficient-data')).toBeVisible()
    await expect(page.getByTestId('insufficient-data')).toContainText('belum cukup')
    await expect(page.getByTestId('outlier-threshold')).toHaveCount(0)
    await expect(page.getByTestId('outlier-list')).toHaveCount(0)
  })

  // Scenario: "Durasi seragam"
  test('Durasi seragam — no-outliers tampil, ambang tetap terlihat', async ({ page }) => {
    await login(page)
    await stubApi(page, {
      summary: makeSummary({
        outliers: {
          method: 'iqr',
          q1: 95,
          q3: 95,
          iqr: 0,
          lower_bound: 95,
          upper_bound: 95,
          sample_size: 24,
          min_sample_size: 8,
          insufficient_data: false,
          items: [],
        },
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('no-outliers')).toBeVisible()
    await expect(page.getByTestId('insufficient-data')).toHaveCount(0)
    await expect(page.getByTestId('outlier-threshold')).toBeVisible()
    await expect(page.getByTestId('outlier-threshold')).toContainText('Ambang bawah 95')
    await expect(page.locator('.outlier-item')).toHaveCount(0)
  })

  // Scenario: "Admin belum memilih mill"
  test('Admin belum memilih mill — mill-required-hint, mill-select belum terpilih, tanpa angka', async ({ page }) => {
    await login(page, USERS.operator)
    await becomeAdminSession(page)
    const api = await stubApi(page)
    await openReport(page)

    await expect(page.getByTestId('mill-required-hint')).toBeVisible()
    await expect(page.getByTestId('mill-select')).toBeVisible()
    // Belum terpilih: opsi yang aktif masih placeholder. Dibaca lewat
    // option:checked, bukan toHaveValue('') — opsi placeholder memakai
    // :value="null", sehingga DOM-nya mengembalikan teks opsi itu sendiri.
    await expect(page.getByTestId('mill-select').locator('option:checked')).toHaveText('Pilih Mill')
    await expect(page.getByTestId('kpi-total-cycles')).toHaveCount(0)
    await expect(page.getByTestId('period-meta')).toHaveCount(0)

    expect(api.hits.periods).toBe(0)
    expect(api.hits.summary).toBe(0)
  })

  // Scenario: "Akun terikat mill tetapi mill-nya kosong" (api_test sengaja kosong)
  test('Akun tanpa mill — pesan hubungi Admin, tanpa mill-select, dan NOL permintaan ke endpoint laporan', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await becomeMilllessSession(page)
    const api = await stubApi(page)
    await openReport(page)

    await expect(page.getByTestId('no-mill-for-account')).toBeVisible()
    await expect(page.getByTestId('no-mill-for-account')).toContainText('hubungi Admin')
    // Daftar seluruh mill TIDAK ditawarkan kepada orang yang tidak berhak.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.getByTestId('kpi-total-cycles')).toHaveCount(0)

    // Asersi intinya: nol pemanggilan, termasuk endpoint options.
    expect(api.hits.total).toBe(0)
    expect(api.urls).toEqual([])
  })

  // Scenario: "Mencoba melihat mill lain"
  test('Mencoba melihat mill lain — business_unit_id dari URL diabaikan, mill akun yang tetap tampil', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page)

    // business_unit_id mill lain dicantumkan pada permintaan halaman.
    await openReport(page, `${ROUTE}?business_unit_id=bu-99`)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('kpi-total-cycles')).toHaveText('1.234')
    await expect(page.getByTestId('mill-current')).toContainText('Business Unit A')

    // Tidak satu pun permintaan membawa business_unit_id.
    expect(api.urls.length).toBeGreaterThan(0)
    for (const url of api.urls) {
      expect(url).not.toContain('business_unit_id')
    }
  })

  // Scenario: "Jaringan gagal" (api_test sengaja kosong — dibuktikan lewat route-abort)
  test('Jaringan gagal — network-error + retry, periode terpilih bertahan, retry memuat periode yang sama', async ({
    page,
  }) => {
    await login(page)
    const api = await stubApi(page, { summaryAbort: true })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('network-error')).toBeVisible()
    await expect(page.getByTestId('retry-button')).toBeVisible()
    // Periode yang sudah dipilih TIDAK hilang.
    await expect(page.getByTestId('period-select')).toHaveValue('per-1')

    // Jaringan dipulihkan.
    api.summaryAbort = false
    await page.getByTestId('retry-button').click()

    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('kpi-total-cycles')).toHaveText('1.234')
    await expect(page.getByTestId('period-select')).toHaveValue('per-1')
  })

  // Scenario: "Sesi berakhir"
  test('Sesi berakhir — 401 memindahkan ke Login, laporan tidak lagi tampil', async ({ page }) => {
    await login(page)
    await stubApi(page, { summaryStatus: 401 })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await page.waitForURL('**/login**')
    await expect(page.getByTestId('laporan-sterilizer-mobile')).toHaveCount(0)
    // 401 bukan kegagalan jaringan: tidak ada pesan "periksa koneksi".
    await expect(page.getByTestId('network-error')).toHaveCount(0)
  })

  // Scenario: "Periode tertutup"
  test('Periode tertutup — badge "Ditutup", laporan tetap penuh, ekspor tetap mengunduh CSV', async ({ page }) => {
    await login(page)
    await stubApi(page, {
      periods: [PERIOD_CLOSED],
      summary: makeSummary({
        period: {
          id: 'per-3',
          name: 'Periode Juli 2026',
          start_date: '2026-07-01',
          end_date: '2026-07-31',
          status: 'closed',
          business_unit_name: 'Business Unit A',
        },
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-3')

    await expect(page.getByTestId('period-status-badge')).toHaveText('Ditutup')
    await expect(page.getByTestId('kpi-total-cycles')).toHaveText('1.234')
    await expect(page.getByTestId('daily-trend')).toBeVisible()
    await expect(page.getByTestId('by-unit')).toBeVisible()
    await expect(page.getByTestId('daily-recap')).toBeVisible()
    await expect(page.getByTestId('export-button')).toBeEnabled()

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByTestId('export-button').click(),
    ])
    expect(download.suggestedFilename()).toMatch(/^laporan-sterilizer_.*\.csv$/)
  })

  // Scenario: "daftar periode hanya memuat periode yang mencakup Sterilizer"
  test('daftar periode — hanya periode yang punya baris Sterilizer yang dapat dipilih, termasuk periode lintas stasiun', async ({
    page,
  }) => {
    await login(page)
    // Server memang tidak mengembalikan periode jenis stasiun lain (itu
    // diuji di backend); yang dibuktikan di sini adalah layar tidak
    // menambahkan apa pun ke daftar yang diterimanya.
    await stubApi(page, { periods: [PERIOD_STER, PERIOD_LINTAS_STASIUN] })
    await openReport(page)

    const options = page.getByTestId('period-select').locator('option')
    await expect(options).toHaveCount(3)
    await expect(options.nth(1)).toHaveText('Sterilizer — Periode Agustus 2026')
    await expect(options.nth(2)).toHaveText('Sterilizer — Periode Lintas Stasiun Agustus 2026')
    await expect(page.getByTestId('period-select')).not.toContainText('Threshing')
    await expect(page.getByTestId('period-select')).not.toContainText('Pressing')
  })

  // Scenario: "angka harus sama dengan laporan versi web"
  test('angka sama dengan laporan web — seluruh nilai apa adanya dari respons, tanpa perhitungan ulang', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // KPI apa adanya — BUKAN jumlah baris harian (12 + 18 = 30).
    await expect(page.getByTestId('kpi-total-cycles')).toHaveText('1.234')
    await expect(page.getByTestId('kpi-total-cages')).toHaveText('15.678')
    await expect(page.getByTestId('kpi-avg-duration')).toHaveText('95,5')
    await expect(page.getByTestId('kpi-triple-peak')).toHaveText('87,5')

    await expect(page.getByTestId('by-unit')).toContainText('700')
    await expect(page.getByTestId('by-unit')).toContainText('534')
    await expect(page.getByTestId('by-unit')).toContainText('8.400')
    // Unit tanpa rata-rata durasi tidak diberi angka karangan.
    await expect(page.getByTestId('by-unit')).toContainText('tidak tersedia')

    await recapToggle(page).click()
    // Blok `total` apa adanya, meski bertentangan dengan kpi dan barisnya.
    await expect(page.getByTestId('daily-recap')).toContainText('Total 999 siklus')
    await expect(page.getByTestId('daily-recap')).toContainText('8.888 lori')
    await expect(page.getByTestId('daily-recap')).toContainText('777 siklus patuh')
  })

  // Scenario: "rentang periode inklusif"
  test('rentang periode inklusif — tanggal mulai dan akhir tampil, tanpa tanggal di luar rentang', async ({ page }) => {
    await login(page)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')
    await recapToggle(page).click()

    const rows = page.getByTestId('daily-recap').locator('tbody tr')
    await expect(rows).toHaveCount(2)
    await expect(rows.nth(0)).toContainText('01 Agu 2026')
    await expect(rows.nth(1)).toContainText('31 Agu 2026')

    // Sehari sebelum / sehari sesudah periode tidak ikut terhitung.
    await expect(page.getByTestId('daily-recap')).not.toContainText('31 Jul 2026')
    await expect(page.getByTestId('daily-recap')).not.toContainText('01 Sep 2026')
    await expect(page.getByTestId('kpi-total-cycles')).toHaveText('1.234')
  })

  // Scenario: "rata-rata durasi mengabaikan siklus tanpa durasi"
  test('rata-rata mengabaikan siklus tanpa durasi — nilai tampil bersama jumlah siklus yang dikeluarkan', async ({
    page,
  }) => {
    await login(page)
    await stubApi(page, {
      summary: makeSummary({
        kpi: {
          total_cycles: 50,
          total_cages: 600,
          avg_duration_minutes: 92,
          min_duration_minutes: 70,
          max_duration_minutes: 120,
          cycles_without_duration: 7,
          triple_peak_compliance_percent: 80,
        },
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('kpi-avg-duration')).toHaveText('92')

    const avgCard = page.getByTestId('kpi-avg-duration').locator('xpath=ancestor::div[@class="metric-card"]')
    await expect(avgCard).toContainText('Terpendek 70')
    await expect(avgCard).toContainText('Terlama 120')
    // Konteksnya berdampingan dengan rata-rata, bukan di bagian lain layar.
    await expect(avgCard.getByTestId('cycles-without-duration')).toContainText('7')
    await expect(page.getByTestId('cycles-without-duration')).toContainText('tidak ikut dihitung')
  })

  // Scenario: "siklus di luar kebiasaan ditentukan dengan metode kuartil"
  test('pencilan metode kuartil — daftar pencilan dan ambang bawah/atas terlihat', async ({ page }) => {
    await login(page)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await page.getByTestId('outlier-list').scrollIntoViewIfNeeded()

    const items = page.getByTestId('outlier-list').locator('.outlier-item')
    await expect(items).toHaveCount(2)
    await expect(items.nth(0)).toContainText('200')
    await expect(items.nth(1)).toContainText('15')

    await expect(page.getByTestId('outlier-threshold')).toContainText('kuartil')
    await expect(page.getByTestId('outlier-threshold')).toContainText('Ambang bawah 20')
    await expect(page.getByTestId('outlier-threshold')).toContainText('180')
    await expect(page.getByTestId('insufficient-data')).toHaveCount(0)
  })
})

/* ================================================================== */
/* Production Line wajib (2026-09-28)                                  */
/* ================================================================== */

/**
 * Laporan Sterilizer memulangkan ANGKA GABUNGAN satu periode. Menggabungkan
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
  kpi: {
    total_cycles: 6421,
    total_cages: 15678,
    avg_duration_minutes: 95.5,
    min_duration_minutes: 70,
    max_duration_minutes: 130,
    cycles_without_duration: 4,
    triple_peak_compliance_percent: 87.5,
  },
})

const SUMMARY_LINE_2 = makeSummary({
  production_line: { id: 'pl-2', name: 'Line 2' },
  kpi: {
    total_cycles: 233,
    total_cages: 15678,
    avg_duration_minutes: 95.5,
    min_duration_minutes: 70,
    max_duration_minutes: 130,
    cycles_without_duration: 4,
    triple_peak_compliance_percent: 87.5,
  },
})

const SUMMARY_BY_LINE = { 'pl-1': SUMMARY_LINE_1, 'pl-2': SUMMARY_LINE_2 }

test.describe('Laporan Sterilizer Mobile (screen-135) — Production Line wajib', () => {
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
    await expect(page.getByTestId('kpi-total-cycles')).toHaveCount(0)
    await expect(page.getByTestId('export-button')).toHaveCount(0)
    expect(api.hits.summary).toBe(0)

    // Sasaran sentuh pemilih line: 44px pada kedua sisi.
    const box = await lineSelect.boundingBox()
    expect(box).not.toBeNull()
    expect(box!.height).toBeGreaterThanOrEqual(44)
    expect(box!.width).toBeGreaterThanOrEqual(44)

    await lineSelect.selectOption('pl-1')

    await expect(page.getByTestId('kpi-total-cycles')).toHaveText('6.421')
    // Jumlah kedua line TIDAK PERNAH muncul.
    await expect(page.getByText('6.654', { exact: false })).toHaveCount(0)
    expect(api.urls.some((url) => url.includes('/summary') && url.includes('production_line_id=pl-1'))).toBe(true)

    // Ganti line: permintaannya membawa id baru, angka lama tidak tertinggal.
    await lineSelect.selectOption('pl-2')
    await expect(page.getByTestId('kpi-total-cycles')).toHaveText('233')
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
    expect(download.suggestedFilename()).toMatch(/^laporan-sterilizer_.*\.csv$/)
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

    await expect(page.getByTestId('kpi-total-cycles')).toHaveText('233')
    expect(api.urls.some((url) => url.includes('/summary') && url.includes('production_line_id=pl-2'))).toBe(true)
  })

  test('production_line_id pada URL (dibawa screen-141) langsung berlaku', async ({ page }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page, { productionLines: TWO_LINES, summaryByLine: SUMMARY_BY_LINE })
    await openReport(page, `${ROUTE}?production_line_id=pl-2`)

    await expect(page.getByTestId('production-line-required-hint')).toHaveCount(0)
    await expect(page.getByTestId('production-line-select')).toHaveValue('pl-2')

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('kpi-total-cycles')).toHaveText('233')
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
    await expect(page.getByTestId('kpi-total-cycles')).toHaveCount(0)
    expect(api.hits.summary).toBe(0)
  })
})
