import { expect, test, type Page } from '@playwright/test'
import { getAuthUserId, login, USERS } from './helpers'

/**
 * laporan-grading.spec.ts — screen-147--laporan-grading-mobile /
 * usecase-150--laporan-grading-mobile "Lihat Laporan Periode Grading
 * (Mobile)".
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
 * test di bawah sengaja melewati tile Grading dari sana — entri 'grading' pada
 * REPORT_ROUTES adalah satu-satunya penentu tile itu hidup atau mati, dan
 * tanpa test itu layar ini bisa lengkap, hijau, dan tetap tidak dapat dicapai
 * siapa pun.
 *
 * RESPONS API DI-STUB DI TINGKAT JARINGAN (page.route), BUKAN DISEMAI KE
 * DATABASE — keputusan dan alasan yang sama dengan keenam laporan mobile lain:
 *   - Layar ini read-only total dan tidak punya satu pun UI untuk membuat data
 *     yang dibacanya; menyemai fixture berarti menjalankan dua layar WEB lain
 *     (periode + input Grading) dari dalam suite mobile.
 *   - Kebenaran ANGKA-nya bukan tanggung jawab layar ini: seluruhnya dari
 *     GradingReportService, yang sudah diuji penuh di backend (30 unit + 26
 *     feature + 23 Livewire). Yang khas mobile — dan hanya dapat dibuktikan di
 *     sini — adalah PEMETAAN respons itu ke layar satu kolom.
 *   - Beberapa skenario mustahil dibuat dari data sungguhan tanpa merusak
 *     lingkungan (sesi kedaluwarsa, jaringan putus, akun tanpa mill).
 *
 * ANGKA FIXTURE SENGAJA MEMUNGKINKAN JUMLAH KEDUA SATUAN TERBENTUK: 100
 * janjang + 40 kg = 140. Asersi "tidak ada angka gabungan" hanya bermakna bila
 * jumlahnya memang mungkin muncul. Jangan "merapikan" angka-angka ini.
 *
 * DAN SATU LAGI YANG KHAS GRADING: PENYEBUT RATA-RATA. Parameter 'Mentah' ada
 * pada 2 dari 10 muatan, sementara rata-ratanya 12,50% — di layar sempit kolom
 * penyebut itulah yang paling mudah dikorbankan demi ruang, dan tanpanya
 * 12,50% terbaca sebagai rata-rata seluruh periode. Satu test di bawah mencari
 * teks "2 dari 10 muatan" apa adanya.
 *
 * REKAP HARIAN MEMAKAI v-show (CollapsibleSection), BUKAN v-if: saat tertutup
 * barisnya TETAP ADA di DOM dengan display:none. Karena itu keadaan tertutup
 * diasersi dengan toBeHidden(), tidak pernah dengan toHaveCount(0).
 */

const ROUTE = '/reports/grading'

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
  station_type: 'grading',
  station_type_label: 'Grading',
}

const PERIOD_CLOSED = {
  id: 'per-2',
  name: 'Periode Agustus 2026',
  start_date: '2026-08-01',
  end_date: '2026-08-31',
  status: 'closed',
  station_type: 'grading',
  station_type_label: 'Grading',
}

const LINE_1 = { id: 'pl-1', name: 'Line 1', code: 'L1' }
const LINE_2 = { id: 'pl-2', name: 'Line 2', code: 'L2' }

/** Kunci ingatan line, ditulis StationListView.vue dan dibaca layar ini. */
const REMEMBERED_LINE_KEY = (userId: string) => `msl_production_line_${userId}`

const EMPTY_BLOCK = { quantity_total: null, parameter_count: 0, rows: [] }

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
    load_count: 10,
    netto_total: 8000.5,
    netto_avg: 800.05,
    bunch_total: 900,
    bunch_avg: 90,
    // 13 parameter berbasis janjang pada master; tiga di sini cukup untuk
    // membuktikan pemetaannya, dan total 100 dipilih supaya 100 + 40 = 140
    // BISA terbentuk.
    bunch: {
      quantity_total: 100,
      parameter_count: 3,
      rows: [
        {
          grading_parameter_id: 'gp-1',
          name: 'Mentah',
          quantity_total: 20,
          share_percent: 20,
          avg_percentage: 12.5,
          load_count: 2,
        },
        {
          grading_parameter_id: 'gp-2',
          name: 'Masak',
          quantity_total: 70,
          share_percent: 70,
          avg_percentage: 68.75,
          load_count: 10,
        },
        {
          grading_parameter_id: 'gp-3',
          name: 'Tangkai Panjang',
          quantity_total: 10,
          share_percent: null,
          avg_percentage: null,
          load_count: 1,
        },
      ],
    },
    kg: {
      quantity_total: 40,
      parameter_count: 1,
      rows: [
        {
          grading_parameter_id: 'gp-9',
          name: 'Brondolan Segar',
          quantity_total: 40,
          share_percent: 100,
          avg_percentage: 2.25,
          load_count: 6,
        },
      ],
    },
    by_estate_supplier: [
      { estate_supplier: 'Estate Utara', load_count: 7, netto_total: 7000.25, bunch_total: 700 },
      { estate_supplier: '', load_count: 3, netto_total: null, bunch_total: null },
    ],
    loads_without_detail: 3,
    draft_load_count: 2,
    loads_without_division: 1,
    loads_not_checked: 5,
    loads_not_acknowledged: 6,
    daily: Array.from({ length: 12 }, (_, index) => ({
      date: `2026-09-${String(index + 1).padStart(2, '0')}`,
      load_count: index,
      netto_total: index === 0 ? null : index * 100.25,
      bunch_total: index === 0 ? null : index * 10,
    })),
    daily_total: {
      load_count: 10,
      netto_total: 8000.5,
      bunch_total: 900,
    },
    completeness: {
      days_in_period: 30,
      days_with_load: 12,
      days_counted: 18,
      period_running: true,
    },
    ...overrides,
  }
}

function emptySummary(): Record<string, unknown> {
  return makeSummary({
    load_count: 0,
    netto_total: null,
    netto_avg: null,
    bunch_total: null,
    bunch_avg: null,
    bunch: { ...EMPTY_BLOCK },
    kg: { ...EMPTY_BLOCK },
    by_estate_supplier: [],
    loads_without_detail: 0,
    draft_load_count: 0,
    loads_without_division: 0,
    loads_not_checked: 0,
    loads_not_acknowledged: 0,
    daily: [],
    daily_total: { load_count: 0, netto_total: null, bunch_total: null },
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

  await page.route('**/api/grading-reports/**', async (route) => {
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
        body: 'Periode,Mill,Production Line,Parameter Mutu,Satuan\nPeriode September 2026,Mill Utara,Line 1,Mentah,Janjang\n',
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
  await expect(page.getByTestId('laporan-grading-mobile')).toBeVisible()
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
 * tabel parameter, rekap per asal, rekap harian) — dan masing-masing menggulir
 * di dalam kartunya sendiri, itulah yang dibuktikan
 * expectCardScrollsInsideItself().
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
  const cards = page.locator('[data-testid="headline-metrics"] .metric-card')

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

test.describe('Laporan Grading Mobile (screen-147)', () => {
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

    await expect(page.getByTestId('kpi-load-count')).toHaveText('10')
    await expect(page.getByTestId('kpi-netto-total')).toContainText('8.000,50')
    await expect(page.getByTestId('kpi-netto-avg')).toContainText('800,05')
    await expect(page.getByTestId('kpi-bunch-total')).toContainText('900')

    // Kedua kelompok satuan hadir, dan keduanya bertumpuk.
    await expect(page.getByTestId('parameter-bunch')).toBeVisible()
    await expect(page.getByTestId('parameter-kg')).toBeVisible()

    // Pemilih mill memang tidak untuk Operator, dan layar ini tidak pernah
    // memintanya — endpoint options akan 403, jadi memanggilnya hanya akan
    // membentuk daftar seluruh mill di perangkat yang tidak berhak.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.getByTestId('mill-current')).toContainText('Mill Utara')

    await expectSingleColumn(page)
    await expectNoHorizontalPageScroll(page)
    await expectTouchTargets(page, ['period-select', 'export-button', 'back-button'])
  })

  test('tidak ada satu pun angka yang menjumlahkan janjang dan kilogram', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('period-meta')).toBeVisible()

    // 100 + 40 = 140 — angka yang BISA terbentuk dari fixture ini, dan karena
    // itu asersi ketiadaannya bermakna.
    await expect(page.getByTestId('laporan-grading-mobile')).not.toContainText('140,00')
    await expect(page.getByText(/Total Keseluruhan|Total Gabungan|Total Semua Satuan/i)).toHaveCount(0)

    // Dan total tiap kelompok memang terender terpisah.
    await expect(page.getByTestId('parameter-bunch-total')).toContainText('100,00')
    await expect(page.getByTestId('parameter-kg-total')).toContainText('40,00')
  })

  test('penyebut rata-rata tercetak apa adanya, tidak dikorbankan demi ruang', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    const bunchTable = page.getByTestId('parameter-bunch-table')

    // 'Mentah' hanya ada pada 2 dari 10 muatan, dengan rata-rata 12,50% —
    // tanpa penyebutnya, 12,50% terbaca sebagai rata-rata seluruh periode.
    await expect(bunchTable).toContainText('2 dari 10 muatan')
    await expect(bunchTable).toContainText('10 dari 10 muatan')
    await expect(page.getByTestId('parameter-kg-table')).toContainText('6 dari 10 muatan')
  })

  test('keterangan pangsa vs rata-rata terender utuh, tidak dipotong', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    const note = page.getByTestId('parameter-share-note')

    // Dua angka yang membantah tanpa sebab yang dinyatakan lebih buruk
    // daripada satu angka saja.
    await expect(note).toContainText('berbobot menurut besar muatan')
    await expect(note).toContainText('tiap muatan berbobot sama')
    await expect(note).toContainText('tidak pernah dijumlahkan')
  })

  test('kedua tabel parameter menggulir DI DALAM kartunya, halaman tetap tidak bergeser', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('parameter-bunch-table')).toBeVisible()
    await expect(page.getByTestId('parameter-kg-table')).toBeVisible()

    await expectCardScrollsInsideItself(page, '[data-testid="parameter-bunch"] .detail-table-wrap')
    await expectCardScrollsInsideItself(page, '[data-testid="parameter-kg"] .detail-table-wrap')

    // Tiga baris janjang + satu baris kilogram, termasuk baris yang
    // persentasenya null.
    await expect(page.getByTestId('parameter-bunch-row')).toHaveCount(3)
    await expect(page.getByTestId('parameter-kg-row')).toHaveCount(1)

    await expectNoHorizontalPageScroll(page)
  })

  test('kelompok satuan tanpa baris TETAP dirender, tidak disembunyikan', async ({ page }) => {
    await stubApi(page, { summary: makeSummary({ kg: { ...EMPTY_BLOCK } }) })
    await openReport(page)
    await pickPeriod(page, 'per-1')

    // Bagian yang LENYAP terbaca sebagai "tidak ada bagian ini"; yang benar
    // adalah "tidak ada isinya".
    await expect(page.getByTestId('parameter-kg')).toBeVisible()
    await expect(page.getByTestId('parameter-kg-empty')).toBeVisible()
    await expect(page.getByTestId('parameter-kg-table')).toHaveCount(0)
    await expect(page.getByTestId('parameter-kg-total')).toContainText('tidak tersedia')
    // Kelompok janjang tetap menampilkan angkanya sendiri.
    await expect(page.getByTestId('parameter-bunch-table')).toBeVisible()
  })

  test('persentase null dirender sebagai keterangan, bukan 0,00%', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    // Baris ketiga fixture: share_percent dan avg_percentage keduanya null.
    const lastRow = page.getByTestId('parameter-bunch-row').nth(2)

    await expect(lastRow).toContainText('Tangkai Panjang')
    await expect(lastRow).toContainText('tidak tersedia')
    await expect(lastRow).not.toContainText('0,00%')
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

    await expect(recapBody(page)).toContainText('Muatan')
    await expect(recapBody(page)).toContainText('Netto')
    await expect(page.getByTestId('daily-recap-total')).toBeVisible()
    // Sel netto hari pertama null — dan null bukan nol.
    await expect(recapBody(page)).toContainText('tidak tercatat')

    await expectCardScrollsInsideItself(page, '[data-testid="daily-recap"] .detail-table-wrap')
    await expectNoHorizontalPageScroll(page)

    await recapToggle(page).click()
    await expect(recapBody(page)).toBeHidden()

    // Membuka/menutup murni penyingkapan: datanya sudah ada di memori.
    expect(state.hits.summary).toBe(summaryHitsBefore)
  })

  test('periode tanpa data: keterangan tampil, kedua kelompok tetap ada, rekap harian tidak', async ({ page }) => {
    await stubApi(page, { summary: emptySummary() })
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('empty-period')).toBeVisible()
    await expect(page.getByTestId('kpi-netto-total')).toContainText('tidak tersedia')
    await expect(page.getByTestId('parameter-bunch')).toBeVisible()
    await expect(page.getByTestId('parameter-kg')).toBeVisible()
    await expect(page.getByTestId('daily-recap')).toHaveCount(0)
    await expectNoHorizontalPageScroll(page)
  })

  test('asal kosong berlabel Belum diisi dan barisnya tidak dibuang', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('by-estate-supplier')).toContainText('Estate Utara')
    await expect(page.getByTestId('by-estate-supplier')).toContainText('Belum diisi')
    await expect(page.getByTestId('by-estate-supplier-row')).toHaveCount(2)
  })

  test('keenam penghitung kelengkapan tampil, dan tidak ada penghitung tanpa tanggal', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('loads-without-detail')).toHaveText('3')
    await expect(page.getByTestId('draft-load-count')).toHaveText('2')
    await expect(page.getByTestId('loads-without-division')).toHaveText('1')
    await expect(page.getByTestId('loads-not-checked')).toHaveText('5')
    await expect(page.getByTestId('loads-not-acknowledged')).toHaveText('6')
    // 12 dari 18 hari berjalan = 66,7% — penyebutnya days_counted, bukan
    // days_in_period (30).
    await expect(page.getByTestId('completeness-percent')).toContainText('66,7%')

    // grading_records.date NOT NULL, jadi penghitung "tanpa tanggal" memang
    // tidak ada — dan ketiadaannya DINYATAKAN, bukan dibiarkan terbaca sebagai
    // kelalaian.
    //
    // Asersi ini menyisir LABEL PENGHITUNG, bukan seluruh teks layar: kalimat
    // penjelasnya sendiri menyebut frasa "muatan tanpa tanggal" untuk
    // menyatakan bahwa penghitungnya tidak ada, jadi penyisiran atas seluruh
    // innerText justru akan gagal pada kalimat yang membuktikan klaimnya.
    const counterLabels = (
      await page.locator('[data-testid="completeness"] .queue-label').allInnerTexts()
    ).map((label) => label.toLowerCase())

    expect(counterLabels).toHaveLength(6)

    for (const forbidden of ['tanpa tanggal', 'belum bertanggal', 'tanggal kosong']) {
      expect(
        counterLabels.some((label) => label.includes(forbidden)),
        `penghitung "${forbidden}" muncul padahal kolomnya wajib`,
      ).toBe(false)
    }

    await expect(page.getByTestId('completeness-note')).toContainText('kolom wajib')
  })

  test('tidak ada nilai yang ditandai di luar batas', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('period-meta')).toBeVisible()

    // Tidak ada master target mutu untuk Grading, jadi layar ini tidak menilai
    // satu angka pun — dan penandaan visual apa pun akan menjadi penilaian
    // yang tidak punya dasar.
    for (const className of ['md-threshold', 'is-danger', 'is-warning', 'md-chip--danger']) {
      await expect(page.locator(`.${className}`), className).toHaveCount(0)
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
    await expect(page.getByTestId('kpi-load-count')).toHaveText('10')
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
    await expect(page.getByTestId('kpi-load-count')).toHaveText('10')
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

    await expect(page.getByTestId('kpi-load-count')).toHaveText('10')
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
/* Pintu masuk — tile Grading pada layar pemilih stasiun                */
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
   * SATU BARIS yang menentukan layar ini dapat dicapai: entri 'grading' pada
   * REPORT_ROUTES di ReportingPilihStasiunView.vue. Tanpa test ini, layar
   * laporan bisa lengkap, seluruh test lainnya hijau, dan tile-nya tetap
   * kelabu — pola kegagalan yang sudah terjadi sekali pada laporan Cages &
   * Tracks versi web.
   */
  test('tile Grading aktif dan menavigasi ke layar laporannya', async ({ page }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await seedLocalStations(page)
    await goToReports(page)

    const tile = page.getByTestId('station-tile-grading')

    await expect(tile).toBeVisible()
    await tile.click()

    // Sufiks ** — alamatnya membawa ?production_line_id= dari layar ini.
    await page.waitForURL('**/reports/grading**')
    await expect(page.getByTestId('laporan-grading-mobile')).toBeVisible()

    // Dan tidak ada pesan "belum tersedia" yang muncul sebagai gantinya.
    await expect(page.getByTestId('info-message')).toHaveCount(0)
  })
})
