import { test, expect, type Page } from '@playwright/test'
import { login, USERS } from './helpers'

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

const PERIOD_ALL_TYPES = {
  id: 'per-2',
  name: 'Periode Semua Stasiun Agustus 2026',
  start_date: '2026-08-01',
  end_date: '2026-08-31',
  status: 'open',
  station_type: null,
  station_type_label: 'Semua Jenis Stasiun',
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
    ...overrides,
  }

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
  test('success as Admin — mill-select berisi lebih dari satu mill, ringkasan muncul setelah mill + periode dipilih', async ({
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

    await page.getByTestId('mill-select').selectOption('bu-1')
    await expect(page.getByTestId('period-select').locator('option')).toHaveCount(2)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('kpi-total-cycles')).toHaveText('1.234')
    await expect(page.getByTestId('kpi-total-cages')).toHaveText('15.678')
    await expect(page.getByTestId('daily-trend')).toBeVisible()
    await expect(page.getByTestId('by-unit')).toBeVisible()

    // business_unit_id memang dikirim untuk Admin — dan HANYA untuk Admin.
    expect(api.urls.some((url) => url.includes('/summary') && url.includes('business_unit_id=bu-1'))).toBe(true)

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByTestId('export-button').click(),
    ])
    expect(download.suggestedFilename()).toMatch(/^laporan-sterilizer_.*\.csv$/)
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
  test('daftar periode — hanya Sterilizer dan "semua jenis stasiun" yang dapat dipilih', async ({ page }) => {
    await login(page)
    // Server memang tidak mengembalikan periode jenis stasiun lain (itu
    // diuji di backend); yang dibuktikan di sini adalah layar tidak
    // menambahkan apa pun ke daftar yang diterimanya.
    await stubApi(page, { periods: [PERIOD_STER, PERIOD_ALL_TYPES] })
    await openReport(page)

    const options = page.getByTestId('period-select').locator('option')
    await expect(options).toHaveCount(3)
    await expect(options.nth(1)).toHaveText('Sterilizer — Periode Agustus 2026')
    await expect(options.nth(2)).toHaveText('Semua Jenis Stasiun — Periode Semua Stasiun Agustus 2026')
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
