import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { getAuthUserId, login, USERS } from './helpers'

/**
 * laporan-cpo-dispatch.spec.ts — screen-167--laporan-cpo-dispatch-mobile /
 * usecase-173--laporan-cpo-dispatch-mobile.
 *
 * Tech spec screen ini tidak punya `test_scenarios`; skenarionya diturunkan
 * dari `implementation_notes` dan `edge_case_handling`. Kerangka stub
 * (page.route + header CORS + penghitung permintaan) dan seluruh bantuan
 * pengukuran DISALIN dari laporan-kernel-dispatch.spec.ts, bukan dikarang ulang.
 *
 * Berjalan di Vite :5174 (playwright.config.ts) dengan VIEWPORT PONSEL 390x844.
 * Respons laporan di-STUB di tingkat jaringan: yang menyentuh backend :8000
 * sungguhan HANYA login (POST /api/login). Tidak ada migrate, seed, maupun
 * db:prepare di jalur ini, dan database dev tidak ditulis.
 *
 * PROFIL FIXTURE: dua baris cpo-dispatch di DB e2e punya `time_in` dan
 * `time_out` KEDUANYA NULL, sehingga yang masuk akal di browser adalah kasus
 * "tidak ada satu jam pun tercatat". Jebakan tengah malam dan waktu putar nol
 * TIDAK dapat diuji di sini; keduanya milik unit test
 * (tests/LaporanCpoDispatchView.spec.ts).
 *
 * LURING DIPALSUKAN DI `navigator.onLine` LEWAT addInitScript dengan stub API
 * tetap terpasang — BUKAN context.setOffline() + reload, yang memutus
 * pengambilan aplikasi dari Vite sehingga yang teruji Chromium, bukan layar ini.
 *
 * Ketiadaan diasersi atas testid dan jumlah elemen, tidak pernah atas prosa
 * (payload ini membawa kalimat 'rekap berlabel', 'tidak wajar' dan 'jam').
 * Alamat dibandingkan atas PATHNAME, bukan toHaveURL('**\/x'). Setiap count()
 * didahului asersi yang menunggu sendiri.
 */

const ROUTE = '/reports/cpo-dispatch'
const PHONE = { width: 390, height: 844 }

const CORS_HEADERS = {
  'access-control-allow-origin': '*',
  'access-control-allow-headers': '*',
  'access-control-allow-methods': 'GET,POST,OPTIONS',
}

function isoDaysFromNow(offsetDays: number): string {
  return new Date(Date.now() + offsetDays * 86_400_000).toISOString().slice(0, 10)
}

const PERIOD = {
  id: 'per-1',
  name: 'Periode Uji CPO Dispatch',
  start_date: isoDaysFromNow(-29),
  end_date: isoDaysFromNow(0),
  status: 'open',
  station_type: 'cpo-dispatch',
  station_type_label: 'CPO Dispatch',
}

const LINE_1 = { id: 'pl-1', name: 'Line Satu', code: 'L1' }
const LINE_2 = { id: 'pl-2', name: 'Line Dua', code: 'L2' }

const rememberedLineKey = (userId: string) => `msl_production_line_${userId}`

/* ------------------------------------------------------------------ */
/* Prosa SERVER — dibaca dari berkas service-nya, bukan dikarang        */
/* ------------------------------------------------------------------ */

const SERVICE_SOURCE = readFileSync(
  resolve(process.cwd(), '../backend/app/Services/CpoDispatchReportService.php'),
  'utf-8',
)

function phpStringConst(name: string): string {
  const marker = `const ${name} = `
  const start = SERVICE_SOURCE.indexOf(marker)

  expect(start, `konstanta ${name} tidak ada di CpoDispatchReportService`).toBeGreaterThan(-1)

  const segments = /\s*\.?\s*'((?:[^'\\]|\\.)*)'/y
  const parts: string[] = []

  segments.lastIndex = start + marker.length

  let match = segments.exec(SERVICE_SOURCE)

  while (match !== null) {
    parts.push(match[1])
    match = segments.exec(SERVICE_SOURCE)
  }

  const prose = parts.join('').replace(/\\'/g, "'").replace(/\\\\/g, '\\')

  expect(prose.length, `prosa ${name} terlalu pendek — parser gagal`).toBeGreaterThan(60)

  return prose
}

/* ------------------------------------------------------------------ */
/* Fixture                                                             */
/* ------------------------------------------------------------------ */

function measure(min: number | null, avg: number | null, max: number | null, count: number) {
  return { min, avg, max, measured_count: count }
}

function buckets(counts: Record<number, number> = {}) {
  return Array.from({ length: 24 }, (_, hour) => ({
    hour,
    dispatch_count: counts[hour] ?? 0,
    net_weight_total: counts[hour] ? counts[hour] * 10 : null,
  }))
}

function makeSummary(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Sungai Rambutan' },
    production_line: { id: LINE_1.id, name: LINE_1.name },
    period: {
      id: PERIOD.id,
      name: PERIOD.name,
      start_date: PERIOD.start_date,
      end_date: PERIOD.end_date,
      status: PERIOD.status,
    },
    dispatches: {
      dispatch_count: 30,
      net_weight_total: 1000,
      net_weight_avg: 777,
      net_weight_dispatch_count: 4,
      missing_net_weight_dispatch_count: 26,
    },
    quality: {
      ffa_percent: measure(3.1, 4.25, 5.9, 21),
      moisture_percent: measure(0.1, 0.15, 0.3, 22),
      impurities_percent: measure(0.02, 0.04, 0.07, 19),
      dobi: { ...measure(2.1, 2.6, 3.1, 14), _note: phpStringConst('NOTE_DOBI_UNITLESS') },
      _note: phpStringConst('NOTE_QUALITY_DENOMINATORS'),
    },
    turnaround: {
      min_minutes: 60,
      avg_minutes: 45.5,
      max_minutes: 90,
      measured_count: 17,
      crossed_midnight_count: 3,
      uncomputable_count: 13,
      _note: phpStringConst('NOTE_TURNAROUND'),
    },
    hourly: {
      buckets: buckets({ 6: 2, 9: 8, 14: 4, 17: 5, 23: 1 }),
      not_recorded_count: 3,
      recorded_count: 20,
      busiest_hour: 17,
      empty_hour_count: 19,
      _note: phpStringConst('NOTE_HOURLY'),
    },
    _note_four_denominators: phpStringConst('NOTE_FOUR_DENOMINATORS'),
    by_transport_company: [
      { value: 'PT Kecil Sentosa', dispatch_count: 2, net_weight_total: 20, net_weight_dispatch_count: 2, net_weight_avg: 10 },
      { value: null, dispatch_count: 28, net_weight_total: 980, net_weight_dispatch_count: 2, net_weight_avg: 490 },
    ],
    by_storage_tank_source: [
      { value: 'Tangki 1', dispatch_count: 30, net_weight_total: 1000, net_weight_dispatch_count: 4, net_weight_avg: 250 },
    ],
    by_destination_buyer: [
      { value: 'Pembeli A', dispatch_count: 30, net_weight_total: 1000, net_weight_dispatch_count: 4, net_weight_avg: 250 },
    ],
    compliance: {
      missing_waybill_count: 4,
      missing_seal_top_count: 6,
      missing_seal_bottom_count: 2,
      _note: phpStringConst('NOTE_COMPLIANCE'),
    },
    draft_dispatch_count: 11,
    daily: [
      { date: isoDaysFromNow(-3), dispatch_count: 5, net_weight_total: 10, net_weight_dispatch_count: 1, net_weight_avg: 10 },
    ],
    daily_total: { dispatch_count: 5, net_weight_total: 10, net_weight_dispatch_count: 1, net_weight_avg: 10 },
    completeness: { days_in_period: 30, days_with_dispatch: 1, days_counted: 10, percent: 10 },
    text_entries: { findings: [] },
    _note_no_labeled_recap: phpStringConst('NOTE_NO_LABELED_RECAP'),
    _note_no_quality_thresholds: phpStringConst('NOTE_NO_QUALITY_THRESHOLDS'),
    _note_shift_not_published: phpStringConst('NOTE_SHIFT_NOT_PUBLISHED'),
    ...overrides,
  }
}

/** Profil fixture DB e2e: time_in dan time_out KEDUANYA NULL pada setiap baris. */
function noHoursSummary(): Record<string, unknown> {
  return makeSummary({
    turnaround: {
      min_minutes: null,
      avg_minutes: null,
      max_minutes: null,
      measured_count: 0,
      crossed_midnight_count: 0,
      uncomputable_count: 30,
      _note: phpStringConst('NOTE_TURNAROUND'),
    },
    hourly: {
      buckets: buckets(),
      not_recorded_count: 30,
      recorded_count: 0,
      busiest_hour: null,
      empty_hour_count: 24,
      _note: phpStringConst('NOTE_HOURLY'),
    },
  })
}

/* ------------------------------------------------------------------ */
/* Stub jaringan                                                       */
/* ------------------------------------------------------------------ */

interface ApiState {
  summary: Record<string, unknown>
  exportDelayMs: number
  hits: { periods: number; summary: number; export: number; units: number; lines: number }
}

async function stubApi(page: Page, overrides: Partial<ApiState> = {}): Promise<ApiState> {
  const state: ApiState = {
    summary: makeSummary(),
    exportDelayMs: 0,
    hits: { periods: 0, summary: 0, export: 0, units: 0, lines: 0 },
    ...overrides,
  }

  await page.route('**/api/production-lines/options-for-report*', async (route) => {
    if (route.request().method() === 'OPTIONS') {
      await route.fulfill({ status: 204, headers: CORS_HEADERS, body: '' })

      return
    }

    state.hits.lines += 1
    await route.fulfill({ status: 200, headers: CORS_HEADERS, json: { data: [LINE_1, LINE_2] } })
  })

  await page.route('**/api/cpo-dispatch-reports/**', async (route) => {
    const request = route.request()
    const url = request.url()

    if (request.method() === 'OPTIONS') {
      await route.fulfill({ status: 204, headers: CORS_HEADERS, body: '' })

      return
    }

    if (url.includes('/business-units/options')) {
      state.hits.units += 1
      await route.fulfill({
        status: 403,
        headers: CORS_HEADERS,
        json: { message: 'Anda tidak memiliki akses untuk aksi ini.' },
      })

      return
    }

    if (url.includes('/periods')) {
      state.hits.periods += 1
      await route.fulfill({ status: 200, headers: CORS_HEADERS, json: { data: [PERIOD] } })

      return
    }

    if (url.includes('/export')) {
      state.hits.export += 1

      if (state.exportDelayMs > 0) {
        await new Promise((resolveDelay) => setTimeout(resolveDelay, state.exportDelayMs))
      }

      await route.fulfill({
        status: 200,
        headers: { ...CORS_HEADERS, 'content-type': 'text/csv' },
        body: 'a,b\n1,2\n',
      })

      return
    }

    if (url.includes('/summary')) {
      state.hits.summary += 1
      // /summary menjawab JSON TANPA pembungkus `data`, bentuk controller sungguhan.
      await route.fulfill({ status: 200, headers: CORS_HEADERS, json: state.summary })

      return
    }

    await route.fulfill({ status: 404, headers: CORS_HEADERS, json: { message: 'not stubbed' } })
  })

  return state
}

/* ------------------------------------------------------------------ */
/* Bantuan navigasi dan pengukuran                                     */
/* ------------------------------------------------------------------ */

async function openReport(page: Page): Promise<void> {
  await page.goto(ROUTE)
  await expect(page.getByTestId('laporan-cpo-dispatch-mobile')).toBeVisible()
}

async function pickLine(page: Page, lineId = LINE_1.id): Promise<void> {
  await expect(page.getByTestId('production-line-select')).toBeVisible()
  await page.getByTestId('production-line-select').selectOption(lineId)
}

async function pickPeriod(page: Page, periodId = PERIOD.id): Promise<void> {
  await expect(page.getByTestId('period-select')).toBeVisible()
  await page.getByTestId('period-select').selectOption(periodId)
}

async function openFullReport(page: Page): Promise<void> {
  await openReport(page)
  await pickLine(page)
  await pickPeriod(page)
  await expect(page.getByTestId('period-meta')).toBeVisible()
  await expect(page.getByTestId('cd-card-turnaround')).toBeVisible()
  await expect(page.locator('[data-testid^="cd-quality-card-"]')).toHaveCount(4)
}

async function expectNoHorizontalPageScroll(page: Page): Promise<void> {
  const overflow = await page.evaluate(() => {
    const doc = document.scrollingElement ?? document.documentElement

    return {
      scrollWidth: doc.scrollWidth,
      clientWidth: doc.clientWidth,
      bodyScrollWidth: document.body.scrollWidth,
    }
  })

  expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1)
  expect(overflow.bodyScrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1)
}

async function expectEveryClassDefined(page: Page): Promise<void> {
  await expect(page.getByTestId('laporan-cpo-dispatch-mobile')).toBeVisible()

  const result = await page.evaluate(async () => {
    const defined = new Set<string>()
    const classPattern = /\.(-?[_a-zA-Z]+[_a-zA-Z0-9-]*)/g

    const walk = (rules: CSSRuleList): void => {
      for (const rule of Array.from(rules)) {
        const styleRule = rule as CSSStyleRule & { cssRules?: CSSRuleList }

        if (typeof styleRule.selectorText === 'string') {
          for (const match of styleRule.selectorText.matchAll(classPattern)) {
            defined.add(match[1])
          }
        }

        if (styleRule.cssRules) {
          walk(styleRule.cssRules)
        }
      }
    }

    let sheetsRead = 0
    let sheetsFetched = 0
    const unreadable: string[] = []

    for (const sheet of Array.from(document.styleSheets)) {
      try {
        walk(sheet.cssRules)
        sheetsRead += 1
      } catch {
        unreadable.push(sheet.href ?? '(inline)')
      }
    }

    const hrefs = (Array.from(
      document.querySelectorAll('link[rel="stylesheet"]'),
    ) as HTMLLinkElement[]).map((link) => link.href)

    for (const href of [...new Set([...hrefs, ...unreadable])]) {
      if (href === '(inline)') {
        continue
      }

      try {
        const text = await (await fetch(href)).text()

        for (const match of text.matchAll(classPattern)) {
          defined.add(match[1])
        }

        sheetsFetched += 1
      } catch {
        // Dicatat lewat selisih jumlah di bawah.
      }
    }

    const root = document.querySelector('[data-testid="laporan-cpo-dispatch-mobile"]')
    const used = new Set<string>()

    if (root) {
      for (const element of [root, ...Array.from(root.querySelectorAll('[class]'))]) {
        for (const name of (element.getAttribute('class') ?? '').split(/\s+/)) {
          if (name !== '') {
            used.add(name)
          }
        }
      }
    }

    return {
      used: used.size,
      definedCount: defined.size,
      sheetsRead,
      sheetsFetched,
      linkHrefs: hrefs.length,
      missing: [...used].filter((name) => !defined.has(name)),
    }
  })

  // Diasersi lebih dulu: audit yang tidak membaca satu lembar gaya pun akan
  // menuduh setiap kelas.
  expect(
    result.sheetsRead + result.sheetsFetched,
    `nol lembar gaya terbaca (link: ${result.linkHrefs})`,
  ).toBeGreaterThan(0)
  expect(result.definedCount).toBeGreaterThan(50)
  expect(result.used).toBeGreaterThan(20)
  expect(result.missing, `kelas tanpa definisi: ${result.missing.join(', ')}`).toEqual([])
}

/** Palsukan navigator.onLine SEBELUM aplikasi boot. */
async function bootOffline(page: Page): Promise<void> {
  await page.addInitScript(() => {
    Object.defineProperty(window.navigator, 'onLine', { configurable: true, get: () => false })
  })
}

test.describe('Laporan CPO Dispatch Mobile (screen-167)', () => {
  test.use({ viewport: PHONE })

  test.beforeEach(async ({ page }) => {
    await login(page, USERS.operator)
  })

  test('skenario 1: tiga kartu utama berurutan, satu kolom, halaman tidak menggulir mendatar, nol galat console', async ({
    page,
  }) => {
    const consoleErrors: string[] = []
    const pageErrors: string[] = []

    page.on('console', (message) => {
      if (message.type() === 'error') {
        consoleErrors.push(message.text())
      }
    })
    page.on('pageerror', (error) => pageErrors.push(error.message))

    await stubApi(page)
    await openFullReport(page)

    const expected: Record<string, string> = {
      'cd-kpi-dispatch-count': '30',
      'cd-kpi-net-weight-avg': '777,0',
      'cd-turnaround-avg': '45,5',
      'cd-turnaround-min': '60',
      'cd-turnaround-max': '90',
      'cd-turnaround-crossed-midnight': '3',
      'cd-hourly-busiest': '17',
      'cd-denominator-all': '30',
      'cd-denominator-weight': '4',
      'cd-denominator-time-in': '20',
      'cd-denominator-turnaround': '17',
    }

    for (const [testId, value] of Object.entries(expected)) {
      await expect(page.getByTestId(testId), testId).toHaveText(value)
    }

    const cardIds = await page
      .locator('[data-testid^="cd-card-"]')
      .evaluateAll((elements) => elements.map((el) => el.getAttribute('data-testid')))

    expect(cardIds.slice(0, 3)).toEqual(['cd-card-tonnage', 'cd-card-quality', 'cd-card-turnaround'])

    const boxes = await page
      .locator('[data-testid^="cd-card-"]')
      .evaluateAll((elements) =>
        elements.map((element) => {
          const rect = element.getBoundingClientRect()

          return { top: rect.top, bottom: rect.bottom }
        }),
      )

    for (let index = 1; index < boxes.length; index += 1) {
      expect(boxes[index].top).toBeGreaterThanOrEqual(boxes[index - 1].bottom - 1)
    }

    await expectNoHorizontalPageScroll(page)
    expect(pageErrors).toEqual([])
    expect(consoleErrors).toEqual([])
  })

  test('skenario 2: sebaran jam — 25 baris bertumpuk di dalam kartunya, tidak terpotong, dan melipat mengeluarkannya dari DOM', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    await expect(page.locator('[data-testid="cd-hourly-list"] > li')).toHaveCount(25)
    await expect(page.locator('[data-testid="cd-hourly-list"] progress')).toHaveCount(24)

    const geometry = await page.evaluate(() => {
      const card = document.querySelector('[data-testid="cd-hourly-card"]') as HTMLElement
      const cardRect = card.getBoundingClientRect()
      const rows = Array.from(
        document.querySelectorAll('[data-testid="cd-hourly-list"] > li'),
      ) as HTMLElement[]

      return {
        card: { left: cardRect.left, right: cardRect.right },
        rows: rows.map((row) => {
          const rect = row.getBoundingClientRect()
          const count = row.querySelector('[class*="hourly-count"]') as HTMLElement | null
          const countRect = count?.getBoundingClientRect()

          return {
            top: rect.top,
            left: rect.left,
            right: rect.right,
            width: rect.width,
            countRight: countRect?.right ?? -1,
            countWidth: countRect?.width ?? 0,
          }
        }),
      }
    })

    expect(geometry.rows).toHaveLength(25)

    for (const row of geometry.rows) {
      expect(row.width).toBeGreaterThan(0)
      expect(row.left).toBeGreaterThanOrEqual(geometry.card.left - 1)
      expect(row.right).toBeLessThanOrEqual(geometry.card.right + 1)
      // Angka jumlahnya sendiri tidak terpotong oleh baris induknya.
      expect(row.countWidth).toBeGreaterThan(0)
      expect(row.countRight).toBeLessThanOrEqual(row.right + 1)
    }

    // BERTUMPUK: top menaik tegas — bukan 25 kolom berdampingan.
    for (let index = 1; index < geometry.rows.length; index += 1) {
      expect(geometry.rows[index].top).toBeGreaterThan(geometry.rows[index - 1].top)
    }

    await expectNoHorizontalPageScroll(page)

    await page.getByTestId('cd-hourly-card-toggle').click()
    await expect(page.getByTestId('cd-hourly-list')).toHaveCount(0)
    // Kepala kartu tetap terbaca dan ketiga kartu utama tidak ikut terlipat.
    await expect(page.getByTestId('cd-hourly-busiest')).toHaveText('17')
    await expect(page.getByTestId('cd-card-turnaround')).toBeVisible()
  })

  test('skenario 3: profil fixture — tidak ada satu jam pun tercatat, kartu sebaran dan waktu putar TETAP ada', async ({
    page,
  }) => {
    await stubApi(page, { summary: noHoursSummary() })
    await openFullReport(page)

    await expect(page.getByTestId('cd-hourly-busiest')).toHaveText('—')
    await expect(page.getByTestId('cd-hourly-empty-count')).toHaveText('24')
    await expect(page.getByTestId('cd-hourly-not-recorded')).toHaveText('30')
    await expect(page.locator('[data-testid="cd-hourly-list"] > li')).toHaveCount(25)

    for (const testId of ['cd-turnaround-min', 'cd-turnaround-avg', 'cd-turnaround-max']) {
      await expect(page.getByTestId(testId), testId).toHaveText('—')
    }

    await expect(page.getByTestId('cd-turnaround-count')).toHaveText('0')
    await expect(page.getByTestId('cd-turnaround-uncomputable')).toHaveText('30')
    await expect(page.getByTestId('cd-card-turnaround')).toBeVisible()
    await expect(page.getByTestId('cd-hourly-card')).toBeVisible()
  })

  test('skenario 4: kartu mutu bertumpuk tanpa memotong isinya, dan DOBI tanpa satuan', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    const geometry = await page.evaluate(() => {
      const card = document.querySelector('[data-testid="cd-card-quality"]') as HTMLElement
      const cardRect = card.getBoundingClientRect()
      const blocks = Array.from(
        document.querySelectorAll('[data-testid^="cd-quality-card-"]'),
      ) as HTMLElement[]

      const dobi = document.querySelector('[data-testid="cd-quality-card-dobi"]') as HTMLElement
      const clone = dobi.cloneNode(true) as HTMLElement

      clone.querySelector('[data-testid="cd-quality-dobi-note"]')?.remove()

      return {
        card: { left: cardRect.left, right: cardRect.right },
        blocks: blocks.map((block) => {
          const rect = block.getBoundingClientRect()

          return {
            top: rect.top,
            left: rect.left,
            right: rect.right,
            scrollWidth: block.scrollWidth,
            clientWidth: block.clientWidth,
            children: Array.from(block.querySelectorAll('*')).map((child) => {
              const childRect = child.getBoundingClientRect()

              return { left: childRect.left, right: childRect.right, width: childRect.width }
            }),
          }
        }),
        dobiHasPill: dobi.querySelector('.metric-unit-pill') !== null,
        dobiTextWithoutNote: clone.textContent ?? '',
        percentPills: ['ffa', 'moisture', 'impurities'].map(
          (slug) =>
            document
              .querySelector(`[data-testid="cd-quality-card-${slug}"] .metric-unit-pill`)
              ?.textContent?.trim() ?? null,
        ),
      }
    })

    expect(geometry.blocks).toHaveLength(4)

    for (let index = 0; index < geometry.blocks.length; index += 1) {
      const block = geometry.blocks[index]

      expect(block.left).toBeGreaterThanOrEqual(geometry.card.left - 1)
      expect(block.right).toBeLessThanOrEqual(geometry.card.right + 1)
      expect(block.scrollWidth).toBeLessThanOrEqual(block.clientWidth + 1)

      for (const child of block.children) {
        if (child.width > 0) {
          expect(child.left).toBeGreaterThanOrEqual(block.left - 1)
          expect(child.right).toBeLessThanOrEqual(block.right + 1)
        }
      }

      if (index > 0) {
        expect(block.top).toBeGreaterThan(geometry.blocks[index - 1].top)
      }
    }

    expect(geometry.percentPills).toEqual(['%', '%', '%'])
    expect(geometry.dobiHasPill).toBe(false)
    expect(geometry.dobiTextWithoutNote).not.toContain('%')
    await expectNoHorizontalPageScroll(page)
  })

  test('skenario 5: Production Line wajib — tanpa line, /summary dipanggil TEPAT 0 kali walau periode dipilih', async ({
    page,
  }) => {
    const state = await stubApi(page)

    await openReport(page)
    await pickPeriod(page)

    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    await expect(page.getByTestId('production-line-select').locator('option:checked')).toHaveText(
      'Pilih Production Line',
    )
    await expect(page.getByTestId('cd-export-csv')).toHaveCount(0)
    await expect(page.locator('[data-testid^="cd-kpi-"]')).toHaveCount(0)
    await expect.poll(() => state.hits.summary).toBe(0)
  })

  test('skenario 6: ingatan line yang sah dipakai tanpa diminta, periode tetap dipilih pengguna', async ({
    page,
  }) => {
    const userId = await getAuthUserId(page)

    expect(userId).not.toBe('')

    const state = await stubApi(page)

    await page.addInitScript(
      ([key, value]) => {
        window.localStorage.setItem(key, value)
      },
      [rememberedLineKey(userId), LINE_1.id] as const,
    )

    await openReport(page)

    await expect(page.getByTestId('production-line-select').locator('option:checked')).toHaveText(
      LINE_1.name,
    )
    await expect.poll(() => state.hits.summary).toBe(0)

    await pickPeriod(page)
    await expect(page.getByTestId('cd-card-turnaround')).toBeVisible()
    await expect.poll(() => state.hits.summary).toBe(1)
  })

  test('skenario 7: luring sejak awal — nol permintaan data, nol angka di DOM, pernyataan tampil', async ({
    page,
  }) => {
    const state = await stubApi(page)

    await bootOffline(page)
    await openReport(page)

    await expect(page.getByTestId('offline-notice')).toBeVisible()
    await expect(page.locator('[data-testid^="cd-kpi-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="cd-card-"]')).toHaveCount(0)

    expect(state.hits).toEqual({ periods: 0, summary: 0, export: 0, units: 0, lines: 0 })
  })

  test('skenario 8: menjadi luring SESUDAH angka termuat — angkanya ditutup', async ({ page }) => {
    await stubApi(page)
    await openFullReport(page)

    await expect(page.getByTestId('cd-kpi-dispatch-count')).toHaveText('30')

    await page.evaluate(() => {
      Object.defineProperty(window.navigator, 'onLine', { configurable: true, get: () => false })
      window.dispatchEvent(new Event('offline'))
    })

    await expect(page.getByTestId('offline-notice')).toBeVisible()
    await expect(page.locator('[data-testid^="cd-kpi-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="cd-card-"]')).toHaveCount(0)
    await expect(page.getByTestId('cd-hourly-card')).toHaveCount(0)
  })

  test('skenario 9: ekspor ditekan dua kali cepat — SATU permintaan dan SATU unduhan, hanya CSV', async ({
    page,
  }) => {
    const state = await stubApi(page, { exportDelayMs: 600 })

    await openFullReport(page)

    await expect(page.locator('[data-testid^="cd-export-"]:not([data-testid="cd-export-scope-note"])')).toHaveCount(1)

    const downloads: string[] = []

    page.on('download', (download) => downloads.push(download.suggestedFilename()))

    const button = page.getByTestId('cd-export-csv')

    await button.click()
    // Ketukan kedua dengan force supaya yang teruji penjagaan APLIKASI, bukan
    // penjagaan alat uji.
    await button.click({ force: true })

    await expect.poll(() => downloads.length, { timeout: 10_000 }).toBe(1)
    await page.waitForTimeout(800)

    expect(state.hits.export).toBe(1)
    expect(downloads).toHaveLength(1)
    expect(downloads[0]).toMatch(/\.csv$/i)
  })

  test('skenario 10: setiap kelas CSS yang dipakai markup punya definisi di CSS hasil compile', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)
    await expectEveryClassDefined(page)
  })
})
