/**
 * LaporanCpoDispatchView.spec.ts — screen-167--laporan-cpo-dispatch-mobile /
 * usecase-173--laporan-cpo-dispatch-mobile "Lihat Laporan Periode CPO Dispatch
 * (Mobile)".
 *
 * Tech spec screen ini tidak memuat `unit_test_cases`; kasusnya diturunkan dari
 * `implementation_notes`, `edge_case_handling` dan `business_rules` spec itu,
 * dan kerangka harness-nya (mock modul, penghitung permintaan per jalur,
 * pemalsu `navigator.onLine`, pembersih wrapper) DISALIN dari
 * LaporanKernelDispatchView.spec.ts — bukan dikarang ulang.
 *
 * YANG MEMBUAT ANGKA PONSEL SAMA DENGAN ANGKA WEB: layar ini TIDAK MENGHITUNG
 * APA PUN, dan terutama TIDAK menghitung waktu putar. Karena itu fixture bawaan
 * SENGAJA BERTENTANGAN DENGAN DIRINYA SENDIRI:
 *   - dispatches.net_weight_avg 777 padahal 1000 / 4 = 250
 *   - turnaround.avg_minutes 45,5 di luar rentang min 60 .. max 90
 *     (mustahil lahir dari penghitungan apa pun)
 *   - crossed_midnight_count 3 padahal tidak satu jam pun di fixture
 *     menunjukkan penyeberangan
 *   - hourly.busiest_hour menunjuk jam 17 padahal kelompok terbesar di daftar
 *     adalah jam 9
 * Klien yang menghitung ulang PASTI gagal di sini. JANGAN "merapikan" angka-angka
 * ini.
 *
 * Asersi ketiadaan memakai testid / nama kunci, tidak pernah sapuan teks:
 * payload ini membawa prosa 'rekap berlabel', 'tidak wajar' dan 'jam'.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import LaporanCpoDispatchView from '@/views/LaporanCpoDispatchView.vue'

/* ------------------------------------------------------------------ */
/* Mock modul                                                          */
/* ------------------------------------------------------------------ */

const { pushMock, routeQuery } = vi.hoisted(() => ({
  pushMock: vi.fn(),
  routeQuery: {} as Record<string, string>,
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: () => ({ params: {}, query: routeQuery }),
}))

const { useAuthStoreMock, logoutMock } = vi.hoisted(() => ({
  useAuthStoreMock: vi.fn(),
  logoutMock: vi.fn().mockResolvedValue(undefined),
}))

vi.mock('@/stores/auth', () => ({ useAuthStore: useAuthStoreMock }))

vi.mock('@/stores/floatingClock', () => ({
  useFloatingClockStore: () => ({ enabled: false, toggle: vi.fn() }),
}))

vi.mock('@/stores/aiAssistant', () => ({
  useAiAssistantStore: () => ({
    isOpen: false,
    bubbleEnabled: true,
    open: vi.fn(),
    close: vi.fn(),
    toggleBubble: vi.fn(),
  }),
}))

/** apiClient di-mock dengan KELIMA metode supaya "nol permintaan tulis" dapat DIHITUNG. */
const { apiClientMocks } = vi.hoisted(() => ({
  apiClientMocks: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}))

vi.mock('@/services/apiClient', () => ({ default: apiClientMocks }))

const { repoMocks } = vi.hoisted(() => ({
  repoMocks: {
    fetchBusinessUnits: vi.fn(),
    fetchLines: vi.fn(),
    fetchPeriods: vi.fn(),
    fetchSummary: vi.fn(),
    exportCsv: vi.fn(),
    saveCsvFile: vi.fn(),
  },
}))

vi.mock('@/services/cpoDispatchReportRepo', async () => {
  const actual = await vi.importActual<typeof import('@/services/cpoDispatchReportRepo')>(
    '@/services/cpoDispatchReportRepo',
  )

  return {
    ...actual,
    default: { ...actual.default, ...repoMocks },
    ...repoMocks,
  }
})

const { productionLineMocks } = vi.hoisted(() => ({
  productionLineMocks: {
    fetchProductionLinesForReport: vi.fn(),
    fetchCurrentProductionLines: vi.fn(),
    fetchAndCacheStationsForProductionLine: vi.fn(),
  },
}))

vi.mock('@/services/productionLineRepo', () => ({
  default: productionLineMocks,
  productionLineRepo: productionLineMocks,
  ...productionLineMocks,
}))

const { localDbMocks } = vi.hoisted(() => ({
  localDbMocks: {
    open: vi.fn(),
    query: vi.fn(),
    run: vi.fn(),
    put: vi.fn(),
    add: vi.fn(),
    bulkPut: vi.fn(),
    transaction: vi.fn(),
  },
}))

vi.mock('@/services/localDb', () => ({
  default: localDbMocks,
  localDb: localDbMocks,
  ...localDbMocks,
}))

import {
  BUSINESS_UNITS_PATH,
  EXPORT_PATH,
  LINES_PATH,
  PERIODS_PATH,
  SUMMARY_PATH,
  type CpoDispatchReportScope,
  type CpoDispatchReportSummary,
} from '@/services/cpoDispatchReportRepo'

/* ------------------------------------------------------------------ */
/* Galat NYATA                                                         */
/* ------------------------------------------------------------------ */

const NETWORK_ERROR = { message: 'Tidak dapat terhubung ke server.' }
const UNAUTHENTICATED_ERROR = { message: 'Unauthenticated.', status: 401 }
const FORBIDDEN_ERROR = { message: 'Anda tidak memiliki akses untuk aksi ini.', status: 403 }
const SERVER_ERROR = { message: 'Terjadi kesalahan pada server.', status: 500 }
const NO_MILL_ERROR = {
  message: 'Akun Anda belum terhubung ke mill. Hubungi Admin.',
  status: 422,
  errors: { business_unit_id: ['Akun Anda belum terhubung ke mill. Hubungi Admin.'] },
}

/* ------------------------------------------------------------------ */
/* Prosa SERVER — DIBACA DARI BERKAS SERVICE-nya, bukan dikarang       */
/* ------------------------------------------------------------------ */

function readSource(relative: string): string {
  return readFileSync(resolve(process.cwd(), relative), 'utf-8')
}

/**
 * Sebuah konstanta prosa PHP dirakit dari potongan ber-kutip tunggalnya dengan
 * regex LENGKET: pembaca yang berhenti pada `;` pertama memulangkan prosa
 * TERPOTONG, dan kesetaraan penuh atas prosa terpotong tetap HIJAU karena
 * fixture dan asersinya sama-sama terpotong.
 */
function phpStringConst(source: string, name: string): string {
  const marker = `const ${name} = `
  const start = source.indexOf(marker)

  expect(start, `konstanta ${name} tidak ada di CpoDispatchReportService`).toBeGreaterThan(-1)

  const segments = /\s*\.?\s*'((?:[^'\\]|\\.)*)'/y
  const parts: string[] = []

  segments.lastIndex = start + marker.length

  let match = segments.exec(source)

  while (match !== null) {
    parts.push(match[1])
    match = segments.exec(source)
  }

  const prose = parts.join('').replace(/\\'/g, "'").replace(/\\\\/g, '\\')

  // PENJAGA PARSER: prosa yang gagal dirakit memulangkan string kosong, dan
  // kesetaraan penuh atas string kosong hijau tanpa menguji apa pun.
  expect(prose.length, `prosa ${name} terlalu pendek — parser gagal`).toBeGreaterThan(60)

  return prose
}

const SERVICE_SOURCE = readSource('../backend/app/Services/CpoDispatchReportService.php')

/** KESEMBILAN prosa yang layar ini render UTUH, dari servernya sendiri. */
const NOTE_QUALITY_DENOMINATORS = phpStringConst(SERVICE_SOURCE, 'NOTE_QUALITY_DENOMINATORS')
const NOTE_DOBI_UNITLESS = phpStringConst(SERVICE_SOURCE, 'NOTE_DOBI_UNITLESS')
const NOTE_FOUR_DENOMINATORS = phpStringConst(SERVICE_SOURCE, 'NOTE_FOUR_DENOMINATORS')
const NOTE_NO_LABELED_RECAP = phpStringConst(SERVICE_SOURCE, 'NOTE_NO_LABELED_RECAP')
const NOTE_NO_QUALITY_THRESHOLDS = phpStringConst(SERVICE_SOURCE, 'NOTE_NO_QUALITY_THRESHOLDS')
const NOTE_SHIFT_NOT_PUBLISHED = phpStringConst(SERVICE_SOURCE, 'NOTE_SHIFT_NOT_PUBLISHED')
const NOTE_COMPLIANCE = phpStringConst(SERVICE_SOURCE, 'NOTE_COMPLIANCE')
const NOTE_HOURLY = phpStringConst(SERVICE_SOURCE, 'NOTE_HOURLY')
const NOTE_TURNAROUND = phpStringConst(SERVICE_SOURCE, 'NOTE_TURNAROUND')

/* ------------------------------------------------------------------ */
/* Fixture — BENTUK RESPONS NYATA                                      */
/* ------------------------------------------------------------------ */

function isoDaysFromNow(offsetDays: number): string {
  return new Date(Date.now() + offsetDays * 86_400_000).toISOString().slice(0, 10)
}

const PERIOD = {
  id: 'p-1',
  name: 'Periode Uji CPO Dispatch',
  start_date: isoDaysFromNow(-29),
  end_date: isoDaysFromNow(0),
  status: 'open',
  // DENGAN TANDA HUBUNG — garis bawah tidak cocok dengan satu baris pun.
  station_type: 'cpo-dispatch',
  station_type_label: 'CPO Dispatch',
}

const LINE_3 = { id: 'l-3', name: 'Line Tiga', code: 'L3' }
const LINE_4 = { id: 'l-4', name: 'Line Empat', code: null }

const BUSINESS_UNITS = [
  { id: 'bu-1', name: 'Mill Sungai Rambutan' },
  { id: 'bu-9', name: 'Mill Sungai Lilin' },
]

const REMEMBERED_LINE_KEY = 'msl_production_line_u-7'

function row(
  value: string | null,
  dispatchCount: number,
  netWeightTotal: number | null,
  netWeightDispatchCount: number,
  netWeightAvg: number | null,
): Record<string, unknown> {
  return {
    value,
    dispatch_count: dispatchCount,
    net_weight_total: netWeightTotal,
    net_weight_dispatch_count: netWeightDispatchCount,
    net_weight_avg: netWeightAvg,
  }
}

function measure(
  min: number | null,
  avg: number | null,
  max: number | null,
  measuredCount: number,
): Record<string, unknown> {
  return { min, avg, max, measured_count: measuredCount }
}

/** Ukuran TANPA satu hasil uji pun — null/null/null/0, BUKAN nol. */
function unmeasured(): Record<string, unknown> {
  return measure(null, null, null, 0)
}

/** Dua puluh empat kelompok jam; `counts` memetakan jam → jumlah, sisanya NOL. */
function buckets(counts: Record<number, number> = {}): Array<Record<string, unknown>> {
  return Array.from({ length: 24 }, (_, hour) => ({
    hour,
    dispatch_count: counts[hour] ?? 0,
    net_weight_total: counts[hour] ? counts[hour] * 10 : null,
  }))
}

function makeHourly(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    // Kelompok terbesar jam 9 (8), tetapi `busiest_hour` SENGAJA menunjuk 17.
    buckets: buckets({ 6: 2, 9: 8, 14: 4, 17: 5, 23: 1 }),
    not_recorded_count: 3,
    recorded_count: 20,
    busiest_hour: 17,
    empty_hour_count: 19,
    _note: NOTE_HOURLY,
    ...overrides,
  }
}

function makeTurnaround(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    min_minutes: 60,
    avg_minutes: 45.5,
    max_minutes: 90,
    measured_count: 17,
    crossed_midnight_count: 3,
    uncomputable_count: 13,
    _note: NOTE_TURNAROUND,
    ...overrides,
  }
}

function makeQuality(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    // Keempat measured_count BERBEDA satu dengan lainnya dan dari dispatch_count.
    ffa_percent: measure(3.1, 4.25, 5.9, 21),
    moisture_percent: measure(0.1, 0.15, 0.3, 22),
    impurities_percent: measure(0.02, 0.04, 0.07, 19),
    dobi: { ...measure(2.1, 2.6, 3.1, 14), _note: NOTE_DOBI_UNITLESS },
    _note: NOTE_QUALITY_DENOMINATORS,
    ...overrides,
  }
}

function makeSummary(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Sungai Rambutan' },
    production_line: { id: LINE_3.id, name: LINE_3.name, code: LINE_3.code },
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
    quality: makeQuality(),
    turnaround: makeTurnaround(),
    hourly: makeHourly(),
    _note_four_denominators: NOTE_FOUR_DENOMINATORS,
    // Urutan SENGAJA bukan terbesar-dulu, 'tidak tercatat' SENGAJA di tengah —
    // satu-satunya cara membuktikan klien tidak menyusun ulang.
    by_transport_company: [
      row('PT Kecil Sentosa', 2, 20, 2, 10),
      row(null, 20, 500, 1, 500),
      row('PT Besar Makmur', 8, 480, 1, 480),
    ],
    by_storage_tank_source: [row('Tangki 2', 5, 100, 2, 50), row('Tangki 1', 25, 900, 2, 450)],
    by_destination_buyer: [row('Pembeli A', 30, 1000, 4, 250)],
    compliance: {
      missing_waybill_count: 4,
      missing_seal_top_count: 6,
      missing_seal_bottom_count: 2,
      _note: NOTE_COMPLIANCE,
    },
    draft_dispatch_count: 11,
    daily: [
      { date: isoDaysFromNow(-3), dispatch_count: 5, net_weight_total: 10, net_weight_dispatch_count: 1, net_weight_avg: 10 },
      { date: isoDaysFromNow(-6), dispatch_count: 7, net_weight_total: 20, net_weight_dispatch_count: 2, net_weight_avg: 10 },
    ],
    daily_total: {
      dispatch_count: 88,
      net_weight_total: 999,
      net_weight_dispatch_count: 3,
      net_weight_avg: 33,
    },
    completeness: { days_in_period: 30, days_with_dispatch: 2, days_counted: 10, percent: 88.8 },
    text_entries: {
      findings: [{ date: isoDaysFromNow(-6), value: 'Segel atas tanker BK 1234 XY terlepas di pos.' }],
    },
    _note_no_labeled_recap: NOTE_NO_LABELED_RECAP,
    _note_no_quality_thresholds: NOTE_NO_QUALITY_THRESHOLDS,
    _note_shift_not_published: NOTE_SHIFT_NOT_PUBLISHED,
    ...overrides,
  }
}

/* ------------------------------------------------------------------ */
/* Penghitung permintaan PER JALUR                                     */
/* ------------------------------------------------------------------ */

const requests: string[] = []

function hits(path: string): number {
  return requests.filter((item) => item === path).length
}

const summaryCalls: Array<{ periodId: string; scope: CpoDispatchReportScope }> = []

const EVERY_PATH = [BUSINESS_UNITS_PATH, PERIODS_PATH, LINES_PATH, SUMMARY_PATH, EXPORT_PATH]

/* ------------------------------------------------------------------ */
/* Bantuan mount / interaksi                                           */
/* ------------------------------------------------------------------ */

const mountedWrappers: VueWrapper[] = []
const cleanups: Array<() => void> = []

async function mountView(): Promise<VueWrapper> {
  const wrapper = mount(LaporanCpoDispatchView)

  mountedWrappers.push(wrapper)
  await flushPromises()

  return wrapper
}

async function selectPeriod(wrapper: VueWrapper, periodId: string): Promise<void> {
  await wrapper.get('[data-testid="period-select"]').setValue(periodId)
  await flushPromises()
}

async function selectLine(wrapper: VueWrapper, lineId: string): Promise<void> {
  await wrapper.get('[data-testid="production-line-select"]').setValue(lineId)
  await flushPromises()
}

/** Ingatan perangkat menyediakan line; periode TIDAK PERNAH dipilihkan layar. */
async function mountFullReport(periodId = 'p-1'): Promise<VueWrapper> {
  window.localStorage.setItem(REMEMBERED_LINE_KEY, LINE_3.id)

  const wrapper = await mountView()

  await selectPeriod(wrapper, periodId)

  return wrapper
}

function summaryReturns(payload: Record<string, unknown>): void {
  repoMocks.fetchSummary.mockImplementation(
    (periodId: string, scope?: CpoDispatchReportScope) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: scope ?? {} })

      return Promise.resolve(payload as unknown as CpoDispatchReportSummary)
    },
  )
}

async function mountWith(payload: Record<string, unknown>): Promise<VueWrapper> {
  summaryReturns(payload)

  return mountFullReport()
}

function exists(wrapper: VueWrapper, testid: string): boolean {
  return wrapper.find(`[data-testid="${testid}"]`).exists()
}

function count(wrapper: VueWrapper, testid: string): number {
  return wrapper.findAll(`[data-testid="${testid}"]`).length
}

/** textContent MENTAH, spasinya dinormalkan — untuk kesetaraan penuh. */
function rawText(wrapper: VueWrapper, testid: string): string {
  const content = wrapper.get(`[data-testid="${testid}"]`).element.textContent ?? ''

  return content.replace(/\s+/g, ' ').trim()
}

function text(wrapper: VueWrapper, testid: string): string {
  return wrapper.get(`[data-testid="${testid}"]`).text()
}

function testidsOf(wrapper: VueWrapper, prefix: string): string[] {
  return wrapper
    .findAll(`[data-testid^="${prefix}"]`)
    .map((el) => el.attributes('data-testid') ?? '')
}

function selectedOptionText(wrapper: VueWrapper, testid: string): string {
  const select = wrapper.get(`[data-testid="${testid}"]`).element as HTMLSelectElement

  return select.selectedOptions[0]?.textContent?.trim() ?? ''
}

const NO_LINE_SELECTED = 'Pilih Production Line'
const DASH = '—'

type Role = 'operator' | 'supervisor' | 'mill_management' | 'admin'

function asRole(role: Role, businessUnitId: string | null, userId = 'u-7'): void {
  useAuthStoreMock.mockReturnValue({
    currentUser: {
      id: userId,
      username: `${role}01`,
      name: 'Pengguna Uji',
      role,
      business_unit_id: businessUnitId,
    },
    businessUnit: businessUnitId ? { id: businessUnitId, name: 'Mill Sungai Rambutan' } : null,
    logout: logoutMock,
  })
}

/** `navigator.onLine` dipalsukan lewat defineProperty — sinyal yang DIBACA layar ini. */
function fakeOnLine(value: boolean): void {
  const original = Object.getOwnPropertyDescriptor(window.navigator, 'onLine')

  Object.defineProperty(window.navigator, 'onLine', { configurable: true, get: () => value })

  cleanups.push(() => {
    if (original) {
      Object.defineProperty(window.navigator, 'onLine', original)
    } else {
      delete (window.navigator as unknown as Record<string, unknown>).onLine
    }
  })
}

function stripComments(source: string): string {
  return source
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/<!--[\s\S]*?-->/g, '')
    .replace(/^\s*\/\/.*$/gm, '')
}

const VIEW_PATH = 'src/views/LaporanCpoDispatchView.vue'

beforeEach(() => {
  pushMock.mockReset()
  logoutMock.mockClear()
  useAuthStoreMock.mockReset()

  Object.keys(routeQuery).forEach((key) => delete routeQuery[key])

  requests.length = 0
  summaryCalls.length = 0

  for (const group of [apiClientMocks, repoMocks, productionLineMocks, localDbMocks]) {
    for (const spy of Object.values(group)) {
      spy.mockReset()
    }
  }

  window.localStorage.clear()

  asRole('operator', 'bu-1')

  repoMocks.fetchBusinessUnits.mockImplementation(() => {
    requests.push(BUSINESS_UNITS_PATH)

    return Promise.resolve(BUSINESS_UNITS)
  })

  repoMocks.fetchPeriods.mockImplementation(() => {
    requests.push(PERIODS_PATH)

    return Promise.resolve([PERIOD])
  })

  repoMocks.fetchLines.mockImplementation(() => {
    requests.push(LINES_PATH)

    return Promise.resolve([LINE_3, LINE_4])
  })

  summaryReturns(makeSummary())

  repoMocks.exportCsv.mockImplementation(() => {
    requests.push(EXPORT_PATH)

    return Promise.resolve(new Blob(['a,b']))
  })

  repoMocks.saveCsvFile.mockImplementation(() => undefined)
})

afterEach(() => {
  // Dilepas DULU: onUnmounted melepas pendengar online/offline selagi spy
  // window masih yang dipasang test ini.
  while (mountedWrappers.length > 0) {
    mountedWrappers.pop()?.unmount()
  }

  while (cleanups.length > 0) {
    cleanups.pop()?.()
  }

  vi.restoreAllMocks()
})

/* ================================================================== */
/* 1 — audit sumber: klien tidak menghitung apa pun                    */
/* ================================================================== */

describe('klien tidak menghitung satu agregat pun, dan tidak menghitung waktu putar', () => {
  const code = stripComments(readSource(VIEW_PATH))

  it('unit_test_case 1: tidak ada aritmetika jam, tidak ada Date/Intl untuk durasi, tidak ada reduce/sort/Math.min/Math.max', () => {
    expect(code).not.toMatch(/\bnew Date\b/)
    expect(code).not.toMatch(/\bDate\.(now|parse|UTC)\b/)
    expect(code).not.toMatch(/Intl\.(DateTimeFormat|RelativeTimeFormat|DurationFormat)/)
    expect(code).not.toMatch(/\.(getTime|getHours|getMinutes|valueOf)\s*\(/)
    expect(code).not.toMatch(/\.reduce\s*\(/)
    expect(code).not.toMatch(/\.sort\s*\(/)
    expect(code).not.toMatch(/\.toSorted\s*\(/)
    expect(code).not.toMatch(/Math\.(min|max)\s*\(/)
    // Nol medan jam mentah: layar hanya MEMBACA turnaround dan hourly dari payload.
    expect(code).not.toMatch(/\btime_in\b|\btime_out\b|\btimeIn\b|\btimeOut\b/)
  })

  it('unit_test_case 1b: audit itu sendiri bukan asersi hampa — Intl.NumberFormat HADIR di sumber', () => {
    expect(code).toMatch(/Intl\.NumberFormat/)
    expect(code.length).toBeGreaterThan(5000)
  })

  it('unit_test_case 2: optionLabel tidak diimpor dan tidak disebut — stasiun ini nol kolom berpilihan tertutup', () => {
    expect(code).not.toMatch(/optionLabel/)
  })

  it('unit_test_case 3: angka datang apa adanya dari payload yang SENGAJA tidak konsisten (klien tidak menghitung)', async () => {
    const wrapper = await mountWith(makeSummary())

    // 1000 / 4 = 250, tetapi server menerbitkan 777.
    expect(rawText(wrapper, 'cd-kpi-net-weight-avg')).toBe('777,0')
    // avg waktu putar 45,5 berada DI LUAR min 60 .. max 90.
    expect(rawText(wrapper, 'cd-turnaround-avg')).toBe('45,5')
    expect(rawText(wrapper, 'cd-turnaround-min')).toBe('60')
    expect(rawText(wrapper, 'cd-turnaround-max')).toBe('90')
    // 3 penyeberangan dibaca dari payload, bukan dihitung ulang.
    expect(rawText(wrapper, 'cd-turnaround-crossed-midnight')).toBe('3')
  })
})

/* ================================================================== */
/* 2 — dua jebakan falsiness                                           */
/* ================================================================== */

describe('null eksplisit: jam 0 dan waktu putar 0 adalah nilai SAH yang falsy', () => {
  it('unit_test_case 4: busiest_hour null dirender "—", BUKAN 0', async () => {
    const wrapper = await mountWith(
      makeSummary({ hourly: makeHourly({ busiest_hour: null, recorded_count: 0, empty_hour_count: 24 }) }),
    )

    expect(rawText(wrapper, 'cd-hourly-busiest')).toBe(DASH)
  })

  it('unit_test_case 5: busiest_hour 0 dirender "00" — jam 0 SAH, BUKAN "—"', async () => {
    const wrapper = await mountWith(
      makeSummary({
        hourly: makeHourly({
          buckets: buckets({ 0: 4 }),
          busiest_hour: 0,
          recorded_count: 4,
          not_recorded_count: 0,
          empty_hour_count: 23,
        }),
      }),
    )

    expect(rawText(wrapper, 'cd-hourly-busiest')).toBe('00')
    expect(rawText(wrapper, 'cd-hourly-busiest')).not.toBe(DASH)
  })

  it('unit_test_case 6: waktu putar 0 menit (min, rata-rata, maks) dirender 0, BUKAN "—"', async () => {
    const wrapper = await mountWith(
      makeSummary({
        turnaround: makeTurnaround({ min_minutes: 0, avg_minutes: 0, max_minutes: 0, measured_count: 2 }),
      }),
    )

    expect(rawText(wrapper, 'cd-turnaround-min')).toBe('0')
    expect(rawText(wrapper, 'cd-turnaround-max')).toBe('0')
    // Rata-rata memakai satu desimal minimal — 0,0 adalah ANGKA, bukan ketiadaan.
    expect(rawText(wrapper, 'cd-turnaround-avg')).toBe('0,0')
    expect(rawText(wrapper, 'cd-turnaround-count')).toBe('2')
  })

  it('unit_test_case 7: waktu putar null dirender "—" dengan penyebut 0', async () => {
    const wrapper = await mountWith(
      makeSummary({
        turnaround: makeTurnaround({
          min_minutes: null,
          avg_minutes: null,
          max_minutes: null,
          measured_count: 0,
          crossed_midnight_count: 0,
          uncomputable_count: 30,
        }),
      }),
    )

    expect(rawText(wrapper, 'cd-turnaround-min')).toBe(DASH)
    expect(rawText(wrapper, 'cd-turnaround-avg')).toBe(DASH)
    expect(rawText(wrapper, 'cd-turnaround-max')).toBe(DASH)
    expect(rawText(wrapper, 'cd-turnaround-count')).toBe('0')
    expect(rawText(wrapper, 'cd-turnaround-uncomputable')).toBe('30')
    // Kartu TETAP dirender walau seluruhnya kosong.
    expect(exists(wrapper, 'cd-card-turnaround')).toBe(true)
  })
})

/* ================================================================== */
/* 3 — kartu mutu                                                      */
/* ================================================================== */

describe('kartu mutu: penjaga "—" di tingkat UKURAN dan DOBI tanpa satuan', () => {
  const SLUGS = ['ffa', 'moisture', 'impurities', 'dobi']

  it('unit_test_case 8: satu ukuran kosong mengosongkan ukuran ITU saja — ketiga lainnya tetap berangka', async () => {
    const wrapper = await mountWith(makeSummary({ quality: makeQuality({ moisture_percent: unmeasured() }) }))

    for (const part of ['min', 'avg', 'max']) {
      expect(rawText(wrapper, `cd-quality-moisture-${part}`)).toBe(DASH)
    }

    expect(rawText(wrapper, 'cd-quality-moisture-count')).toBe('0')
    expect(exists(wrapper, 'cd-quality-unmeasured-moisture')).toBe(true)
    expect(exists(wrapper, 'cd-quality-unmeasured-all')).toBe(false)

    expect(rawText(wrapper, 'cd-quality-ffa-avg')).toBe('4,25')
    expect(rawText(wrapper, 'cd-quality-impurities-avg')).toBe('0,04')
    expect(rawText(wrapper, 'cd-quality-dobi-avg')).toBe('2,60')

    for (const slug of ['ffa', 'impurities', 'dobi']) {
      expect(exists(wrapper, `cd-quality-unmeasured-${slug}`)).toBe(false)
    }
  })

  it('unit_test_case 9: keempat ukuran kosong — kartu mutu menyatakannya, kartu tonase dan waktu putar TIDAK terpengaruh', async () => {
    const wrapper = await mountWith(
      makeSummary({
        quality: makeQuality({
          ffa_percent: unmeasured(),
          moisture_percent: unmeasured(),
          impurities_percent: unmeasured(),
          dobi: { ...unmeasured(), _note: NOTE_DOBI_UNITLESS },
        }),
      }),
    )

    expect(exists(wrapper, 'cd-quality-unmeasured-all')).toBe(true)

    for (const slug of SLUGS) {
      expect(rawText(wrapper, `cd-quality-${slug}-avg`)).toBe(DASH)
      expect(rawText(wrapper, `cd-quality-${slug}-count`)).toBe('0')
    }

    expect(rawText(wrapper, 'cd-kpi-dispatch-count')).toBe('30')
    expect(rawText(wrapper, 'cd-turnaround-avg')).toBe('45,5')
  })

  it('unit_test_case 10: keempat penyebut mutu berbeda satu dengan lainnya dan tercetak masing-masing', async () => {
    const wrapper = await mountWith(makeSummary())

    expect(rawText(wrapper, 'cd-quality-ffa-count')).toBe('21')
    expect(rawText(wrapper, 'cd-quality-moisture-count')).toBe('22')
    expect(rawText(wrapper, 'cd-quality-impurities-count')).toBe('19')
    expect(rawText(wrapper, 'cd-quality-dobi-count')).toBe('14')
  })

  it('unit_test_case 11: DOBI tanpa pil satuan sementara ketiga ukuran lain ber-"%", dan dobi._note UTUH di dalam kartu DOBI', async () => {
    const wrapper = await mountWith(makeSummary())

    for (const slug of ['ffa', 'moisture', 'impurities']) {
      const pill = wrapper.get(`[data-testid="cd-quality-card-${slug}"]`).find('.metric-unit-pill')

      expect(pill.exists()).toBe(true)
      expect(pill.text()).toBe('%')
    }

    // DOBI: tidak ada pil satuan sama sekali, dalam bentuk apa pun.
    const dobiCard = wrapper.get('[data-testid="cd-quality-card-dobi"]')

    expect(dobiCard.find('.metric-unit-pill').exists()).toBe(false)

    // Prosa SERVER sendiri menyebut "tanda %" (justru untuk melarangnya), jadi
    // yang diperiksa adalah kartu TANPA elemen prosanya: tidak ada '%' di
    // label, angka, rentang, maupun penyebutnya.
    const dobiClone = dobiCard.element.cloneNode(true) as HTMLElement

    dobiClone.querySelector('[data-testid="cd-quality-dobi-note"]')?.remove()

    expect(dobiClone.textContent ?? '').not.toContain('%')
    expect(dobiClone.textContent ?? '').toContain('2,60')

    // Prosa DOBI duduk DI DALAM kartu DOBI, UTUH, dan bukan di kartu lain.
    expect(dobiCard.find('[data-testid="cd-quality-dobi-note"]').exists()).toBe(true)
    expect(rawText(wrapper, 'cd-quality-dobi-note')).toBe(NOTE_DOBI_UNITLESS.replace(/\s+/g, ' ').trim())
    expect(count(wrapper, 'cd-quality-dobi-note')).toBe(1)

    for (const slug of ['ffa', 'moisture', 'impurities']) {
      expect(
        wrapper.get(`[data-testid="cd-quality-card-${slug}"]`).find('[data-testid="cd-quality-dobi-note"]').exists(),
      ).toBe(false)
    }
  })
})

/* ================================================================== */
/* 4 — sebaran 24 jam                                                  */
/* ================================================================== */

describe('sebaran pengiriman menurut jam masuk: 25 baris bertumpuk, baris nol tetap terlihat', () => {
  it('unit_test_case 12: 24 baris jam + baris "jam tidak tercatat", urutan 00..23 dari server, bilah memakai <progress>', async () => {
    const wrapper = await mountWith(makeSummary())

    const hourRows = testidsOf(wrapper, 'cd-hourly-row-').filter((id) => /^cd-hourly-row-\d+$/.test(id))

    expect(hourRows).toEqual(Array.from({ length: 24 }, (_, hour) => `cd-hourly-row-${hour}`))
    expect(exists(wrapper, 'cd-hourly-row-not-recorded')).toBe(true)
    expect(wrapper.findAll('[data-testid="cd-hourly-list"] > li')).toHaveLength(25)
    expect(wrapper.findAll('[data-testid="cd-hourly-list"] progress')).toHaveLength(24)

    // Jumlah per baris dibaca apa adanya; kelompok terbesar adalah jam 9 (8)
    // sementara busiest_hour menunjuk 17 — klien tidak memilih sendiri.
    expect(rawText(wrapper, 'cd-hourly-count-9')).toBe('8')
    expect(rawText(wrapper, 'cd-hourly-count-17')).toBe('5')
    expect(rawText(wrapper, 'cd-hourly-busiest')).toBe('17')
    expect(rawText(wrapper, 'cd-hourly-empty-count')).toBe('19')
    expect(rawText(wrapper, 'cd-hourly-not-recorded')).toBe('3')
  })

  it('unit_test_case 13: seluruh pengiriman pada SATU jam — 23 baris NOL tetap terlihat sebagai "0"', async () => {
    const wrapper = await mountWith(
      makeSummary({
        hourly: makeHourly({
          buckets: buckets({ 14: 30 }),
          busiest_hour: 14,
          recorded_count: 30,
          not_recorded_count: 0,
          empty_hour_count: 23,
        }),
      }),
    )

    const zeroRows = Array.from({ length: 24 }, (_, hour) => hour)
      .filter((hour) => hour !== 14)
      .filter((hour) => rawText(wrapper, `cd-hourly-count-${hour}`) === '0')

    expect(zeroRows).toHaveLength(23)
    expect(rawText(wrapper, 'cd-hourly-count-14')).toBe('30')
    expect(rawText(wrapper, 'cd-hourly-empty-count')).toBe('23')
    expect(rawText(wrapper, 'cd-hourly-not-recorded')).toBe('0')
  })

  it('unit_test_case 14: tidak ada jam tercatat sama sekali — kartu tetap ada, tersibuk "—", 24 baris nol, baris tidak-tercatat memuat semuanya', async () => {
    const wrapper = await mountWith(
      makeSummary({
        hourly: makeHourly({
          buckets: buckets(),
          busiest_hour: null,
          recorded_count: 0,
          not_recorded_count: 30,
          empty_hour_count: 24,
        }),
      }),
    )

    expect(exists(wrapper, 'cd-hourly-card')).toBe(true)
    expect(rawText(wrapper, 'cd-hourly-busiest')).toBe(DASH)
    expect(rawText(wrapper, 'cd-hourly-empty-count')).toBe('24')
    expect(rawText(wrapper, 'cd-hourly-not-recorded')).toBe('30')
    expect(wrapper.findAll('[data-testid="cd-hourly-list"] > li')).toHaveLength(25)
  })

  it('unit_test_case 15: kepala kartu (tersibuk, jam kosong) ada DI LUAR bagian yang dilipat', async () => {
    const wrapper = await mountWith(makeSummary())

    await wrapper.get('[data-testid="cd-hourly-card-toggle"]').trigger('click')
    await flushPromises()

    expect(exists(wrapper, 'cd-hourly-list')).toBe(false)
    expect(rawText(wrapper, 'cd-hourly-busiest')).toBe('17')
    expect(rawText(wrapper, 'cd-hourly-empty-count')).toBe('19')
  })
})

/* ================================================================== */
/* 5 — penyeberangan tengah malam dan empat penyebut                   */
/* ================================================================== */

describe('crossed_midnight_count hanya ditampilkan; empat penyebut dinyatakan terpisah', () => {
  it('unit_test_case 16: crossed_midnight_count 0 tetap dirender "0", dan 3 dirender 3 tanpa dihitung ulang', async () => {
    const zero = await mountWith(
      makeSummary({ turnaround: makeTurnaround({ crossed_midnight_count: 0 }) }),
    )

    expect(exists(zero, 'cd-turnaround-crossed-midnight')).toBe(true)
    expect(rawText(zero, 'cd-turnaround-crossed-midnight')).toBe('0')

    const three = await mountWith(makeSummary())

    expect(rawText(three, 'cd-turnaround-crossed-midnight')).toBe('3')
  })

  it('unit_test_case 17: keempat penyebut tercetak TERPISAH dan prosa penjelasnya UTUH', async () => {
    const wrapper = await mountWith(makeSummary())

    expect(rawText(wrapper, 'cd-denominator-all')).toBe('30')
    expect(rawText(wrapper, 'cd-denominator-weight')).toBe('4')
    expect(rawText(wrapper, 'cd-denominator-time-in')).toBe('20')
    expect(rawText(wrapper, 'cd-denominator-turnaround')).toBe('17')
    expect(rawText(wrapper, 'cd-note-four-denominators')).toBe(NOTE_FOUR_DENOMINATORS.replace(/\s+/g, ' ').trim())
  })

  it('unit_test_case 18: keempat penyebut kebetulan SAMA — keempatnya tetap dicetak, tidak digabung', async () => {
    const wrapper = await mountWith(
      makeSummary({
        dispatches: {
          dispatch_count: 10,
          net_weight_total: 100,
          net_weight_avg: 10,
          net_weight_dispatch_count: 10,
          missing_net_weight_dispatch_count: 0,
        },
        hourly: makeHourly({ recorded_count: 10, not_recorded_count: 0 }),
        turnaround: makeTurnaround({ measured_count: 10, uncomputable_count: 0 }),
      }),
    )

    for (const id of ['all', 'weight', 'time-in', 'turnaround']) {
      expect(count(wrapper, `cd-denominator-${id}`)).toBe(1)
      expect(rawText(wrapper, `cd-denominator-${id}`)).toBe('10')
    }
  })
})

/* ================================================================== */
/* 6 — tiga kartu utama tidak dapat dilipat                            */
/* ================================================================== */

describe('tiga kartu angka utama tidak dapat dilipat; sebaran dan rekap dapat', () => {
  it('unit_test_case 19: tonase, mutu, waktu putar berurutan dan TANPA kontrol lipat', async () => {
    const wrapper = await mountWith(makeSummary())

    const cards = testidsOf(wrapper, 'cd-card-')

    expect(cards.slice(0, 3)).toEqual(['cd-card-tonnage', 'cd-card-quality', 'cd-card-turnaround'])

    for (const card of ['cd-card-tonnage', 'cd-card-quality', 'cd-card-turnaround']) {
      expect(exists(wrapper, `${card}-toggle`)).toBe(false)
      expect(wrapper.get(`[data-testid="${card}"]`).find('button').exists()).toBe(false)
      expect(wrapper.get(`[data-testid="${card}"]`).find('[aria-expanded]').exists()).toBe(false)
    }
  })

  it('unit_test_case 20: melipat sebaran jam dan rekap tidak memicu permintaan, tidak mengubah angka, dan ketiga kartu utama tetap terbuka', async () => {
    const wrapper = await mountWith(makeSummary())

    const before = {
      dispatchCount: rawText(wrapper, 'cd-kpi-dispatch-count'),
      avgMinutes: rawText(wrapper, 'cd-turnaround-avg'),
      ffa: rawText(wrapper, 'cd-quality-ffa-avg'),
    }
    const requestsBefore = requests.length

    for (const toggle of [
      'cd-hourly-card-toggle',
      'cd-recap-transport-company-toggle',
      'cd-recap-storage-tank-source-toggle',
      'cd-recap-destination-buyer-toggle',
      'cd-daily-recap-toggle',
    ]) {
      await wrapper.get(`[data-testid="${toggle}"]`).trigger('click')
      await flushPromises()
    }

    expect(exists(wrapper, 'cd-hourly-list')).toBe(false)
    expect(exists(wrapper, 'cd-recap-transport-company-table')).toBe(false)
    expect(exists(wrapper, 'cd-daily-table')).toBe(false)
    expect(requests.length).toBe(requestsBefore)

    for (const card of ['cd-card-tonnage', 'cd-card-quality', 'cd-card-turnaround']) {
      expect(exists(wrapper, card)).toBe(true)
    }

    expect(rawText(wrapper, 'cd-kpi-dispatch-count')).toBe(before.dispatchCount)
    expect(rawText(wrapper, 'cd-turnaround-avg')).toBe(before.avgMinutes)
    expect(rawText(wrapper, 'cd-quality-ffa-avg')).toBe(before.ffa)
  })
})

/* ================================================================== */
/* 7 — Production Line wajib                                           */
/* ================================================================== */

describe('penjagaan Production Line ada di SATU fungsi pemuat', () => {
  it('unit_test_case 21: tanpa line, /summary dipanggil TEPAT 0 kali — juga sesudah memilih periode', async () => {
    const wrapper = await mountView()

    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(hits(SUMMARY_PATH)).toBe(0)

    await selectPeriod(wrapper, 'p-1')

    expect(hits(SUMMARY_PATH)).toBe(0)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
    expect(testidsOf(wrapper, 'cd-kpi-')).toHaveLength(0)
    expect(exists(wrapper, 'cd-export-csv')).toBe(false)
  })

  it('unit_test_case 22: jalur Coba Lagi daftar line — line tetap kosong, /summary tetap 0', async () => {
    repoMocks.fetchLines
      .mockImplementationOnce(() => {
        requests.push(LINES_PATH)

        return Promise.reject(NETWORK_ERROR)
      })
      .mockImplementation(() => {
        requests.push(LINES_PATH)

        return Promise.resolve([LINE_3, LINE_4])
      })

    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-retry')).toBe(true)

    await wrapper.get('[data-testid="production-line-retry"]').trigger('click')
    await flushPromises()

    expect(hits(LINES_PATH)).toBe(2)
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    expect(hits(SUMMARY_PATH)).toBe(0)
  })

  it('unit_test_case 23: mill hanya punya SATU line — tetap TIDAK dipilihkan', async () => {
    repoMocks.fetchLines.mockImplementation(() => {
      requests.push(LINES_PATH)

      return Promise.resolve([LINE_3])
    })

    const wrapper = await mountView()

    await selectPeriod(wrapper, 'p-1')

    expect(wrapper.findAll('[data-testid="production-line-select"] option')).toHaveLength(2)
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    expect(hits(SUMMARY_PATH)).toBe(0)
  })

  it('unit_test_case 24: line tersimpan yang tidak lagi sah DIBUANG, bukan dipakai diam-diam', async () => {
    window.localStorage.setItem(REMEMBERED_LINE_KEY, 'l-sudah-dihapus')

    const wrapper = await mountView()

    await selectPeriod(wrapper, 'p-1')

    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    expect(hits(SUMMARY_PATH)).toBe(0)
  })

  it('unit_test_case 25: line tersimpan yang sah dipakai tanpa diminta memilih, dan TETAP dapat diganti', async () => {
    const wrapper = await mountFullReport()

    expect(selectedOptionText(wrapper, 'production-line-select')).toBe('Line Tiga')
    expect(hits(SUMMARY_PATH)).toBe(1)

    await selectLine(wrapper, LINE_4.id)

    expect(selectedOptionText(wrapper, 'production-line-select')).toBe('Line Empat')
  })
})

/* ================================================================== */
/* 8 — luring                                                          */
/* ================================================================== */

describe('luring DINYATAKAN, bukan ditutupi angka basi', () => {
  it('unit_test_case 26: luring sejak awal — NOL permintaan di kelima jalur, nol angka di DOM, pernyataan tampil', async () => {
    fakeOnLine(false)

    const wrapper = await mountView()

    for (const path of EVERY_PATH) {
      expect(hits(path)).toBe(0)
    }

    expect(requests).toHaveLength(0)
    expect(exists(wrapper, 'offline-notice')).toBe(true)
    expect(testidsOf(wrapper, 'cd-kpi-')).toHaveLength(0)
    expect(testidsOf(wrapper, 'cd-card-')).toHaveLength(0)
    expect(exists(wrapper, 'cd-hourly-card')).toBe(false)
  })

  it('unit_test_case 27: menjadi luring SESUDAH angka termuat — event offline MENUTUP angkanya', async () => {
    const wrapper = await mountFullReport()

    expect(testidsOf(wrapper, 'cd-kpi-').length).toBeGreaterThan(0)
    expect(exists(wrapper, 'cd-card-turnaround')).toBe(true)

    fakeOnLine(false)
    window.dispatchEvent(new Event('offline'))
    await flushPromises()

    expect(exists(wrapper, 'offline-notice')).toBe(true)
    expect(testidsOf(wrapper, 'cd-kpi-')).toHaveLength(0)
    expect(testidsOf(wrapper, 'cd-card-')).toHaveLength(0)
    expect(exists(wrapper, 'cd-hourly-card')).toBe(false)
    expect(testidsOf(wrapper, 'cd-recap-')).toHaveLength(0)
  })

  it('unit_test_case 28: pendengar online/offline DILEPAS saat unmount', async () => {
    const addSpy = vi.spyOn(window, 'addEventListener')
    const removeSpy = vi.spyOn(window, 'removeEventListener')

    const wrapper = await mountView()

    const added = addSpy.mock.calls.map((call) => call[0])

    expect(added).toContain('online')
    expect(added).toContain('offline')

    mountedWrappers.pop()
    wrapper.unmount()

    const removed = removeSpy.mock.calls.map((call) => call[0])

    expect(removed).toContain('online')
    expect(removed).toContain('offline')
  })
})

/* ================================================================== */
/* 9 — penyimpanan perangkat                                           */
/* ================================================================== */

describe('penyimpanan perangkat yang melempar', () => {
  it('unit_test_case 29: getItem melempar (mode privat) — layar tetap render, minta pilih line, nol elemen galat', async () => {
    // Spy pada Storage.prototype, BUKAN instance: di jsdom instance dibungkus
    // Proxy sehingga spy instance menerima NOL panggilan dan test-nya hijau
    // tanpa menguji apa pun.
    const getSpy = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('SecurityError: storage diblokir')
    })

    const wrapper = await mountView()

    // Buktikan dulu spy-nya benar-benar menerima panggilan.
    expect(getSpy.mock.calls.length).toBeGreaterThanOrEqual(1)

    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)
    expect(exists(wrapper, 'production-line-unavailable')).toBe(false)
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    expect(hits(SUMMARY_PATH)).toBe(0)
  })
})

/* ================================================================== */
/* 10 — galat                                                          */
/* ================================================================== */

describe('galat: empat kode, empat keadaan yang berbeda', () => {
  it('unit_test_case 30: 401 → Login TANPA pesan jaringan dan tanpa angka', async () => {
    repoMocks.fetchSummary.mockRejectedValue(UNAUTHENTICATED_ERROR)

    const wrapper = await mountFullReport()

    expect(pushMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)
    expect(exists(wrapper, 'retry-button')).toBe(false)
    expect(testidsOf(wrapper, 'cd-kpi-')).toHaveLength(0)
  })

  it('unit_test_case 31: 403 → minta pilih line lagi, line dikosongkan, angka DIBERSIHKAN, bukan galat jaringan', async () => {
    const wrapper = await mountFullReport()

    expect(testidsOf(wrapper, 'cd-kpi-').length).toBeGreaterThan(0)

    repoMocks.fetchSummary.mockRejectedValue(FORBIDDEN_ERROR)

    await selectPeriod(wrapper, 'p-1')

    expect(exists(wrapper, 'production-line-rejected')).toBe(true)
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(testidsOf(wrapper, 'cd-kpi-')).toHaveLength(0)
    expect(testidsOf(wrapper, 'cd-card-')).toHaveLength(0)
    expect(pushMock).not.toHaveBeenCalled()
  })

  it('unit_test_case 32: 422 akun tanpa mill → arahan menghubungi Admin, BUKAN galat jaringan', async () => {
    repoMocks.fetchSummary.mockRejectedValue(NO_MILL_ERROR)

    const wrapper = await mountFullReport()

    expect(exists(wrapper, 'no-mill-for-account')).toBe(true)
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'retry-button')).toBe(false)
    expect(testidsOf(wrapper, 'cd-kpi-')).toHaveLength(0)
  })

  it('unit_test_case 33: galat lain (500) → keadaan galat + Coba Lagi, dan Coba Lagi memuat ulang tepat sekali dengan penjagaan line tetap berlaku', async () => {
    // Gagal SEKALI, lalu jawaban bawaan beforeEach (payload penuh) berlaku.
    repoMocks.fetchSummary.mockRejectedValueOnce(SERVER_ERROR)

    const wrapper = await mountFullReport()

    expect(exists(wrapper, 'network-error')).toBe(true)
    expect(exists(wrapper, 'retry-button')).toBe(true)
    expect(testidsOf(wrapper, 'cd-kpi-')).toHaveLength(0)

    const callsBefore = repoMocks.fetchSummary.mock.calls.length

    await wrapper.get('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    expect(repoMocks.fetchSummary.mock.calls.length).toBe(callsBefore + 1)
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(rawText(wrapper, 'cd-kpi-dispatch-count')).toBe('30')
  })

  it('unit_test_case 34: jaringan terputus (tanpa status) → keadaan galat dengan Coba Lagi, bukan arahan Login', async () => {
    repoMocks.fetchSummary.mockRejectedValue(NETWORK_ERROR)

    const wrapper = await mountFullReport()

    expect(exists(wrapper, 'network-error')).toBe(true)
    expect(pushMock).not.toHaveBeenCalled()
    expect(testidsOf(wrapper, 'cd-kpi-')).toHaveLength(0)
  })
})

/* ================================================================== */
/* 11 — ekspor                                                         */
/* ================================================================== */

describe('ekspor CSV saja', () => {
  it('unit_test_case 35: ketukan ganda cepat → SATU permintaan dan SATU unduhan', async () => {
    let release: (blob: Blob) => void = () => undefined

    repoMocks.exportCsv.mockImplementation(() => {
      requests.push(EXPORT_PATH)

      return new Promise<Blob>((resolveBlob) => {
        release = resolveBlob
      })
    })

    const wrapper = await mountFullReport()
    const button = wrapper.get('[data-testid="cd-export-csv"]')

    await button.trigger('click')
    // Ketukan kedua dikirim langsung ke elemen, tanpa melewati penjagaan alat uji.
    ;(button.element as HTMLButtonElement).click()
    await flushPromises()

    expect(hits(EXPORT_PATH)).toBe(1)

    release(new Blob(['a,b']))
    await flushPromises()

    expect(repoMocks.saveCsvFile).toHaveBeenCalledTimes(1)
  })

  it('unit_test_case 36: hanya CSV — tidak ada tombol atau testid Excel/xlsx', async () => {
    const wrapper = await mountFullReport()

    const everyTestid = wrapper
      .findAll('[data-testid]')
      .map((el) => el.attributes('data-testid') ?? '')

    expect(everyTestid.filter((id) => /xlsx|excel/i.test(id))).toEqual([])
    expect(testidsOf(wrapper, 'cd-export-').filter((id) => id !== 'cd-export-scope-note')).toEqual([
      'cd-export-csv',
    ])
    expect(wrapper.findAll('button[data-testid^="cd-export-"]')).toHaveLength(1)
  })

  it('unit_test_case 37: ekspor tidak menambah permintaan tulis dan tidak menyentuh basis data lokal', async () => {
    const wrapper = await mountFullReport()

    await wrapper.get('[data-testid="cd-export-csv"]').trigger('click')
    await flushPromises()

    for (const method of ['post', 'put', 'patch', 'delete'] as const) {
      expect(apiClientMocks[method]).not.toHaveBeenCalled()
    }

    for (const spy of Object.values(localDbMocks)) {
      expect(spy).not.toHaveBeenCalled()
    }
  })
})

/* ================================================================== */
/* 12 — kepatuhan, urutan server, prosa utuh                           */
/* ================================================================== */

describe('kepatuhan, urutan server dan prosa server dirender UTUH', () => {
  it('unit_test_case 38: segel atas dan segel bawah DUA angka terpisah, tidak pernah dijumlahkan', async () => {
    const wrapper = await mountWith(makeSummary())

    expect(rawText(wrapper, 'cd-compliance-missing-waybill')).toBe('4')
    expect(rawText(wrapper, 'cd-compliance-missing-seal-top')).toBe('6')
    expect(rawText(wrapper, 'cd-compliance-missing-seal-bottom')).toBe('2')

    // Nol penghitung gabungan: 6 + 2 = 8 tidak boleh muncul sebagai angka sendiri.
    expect(testidsOf(wrapper, 'cd-compliance-').filter((id) => id !== 'cd-compliance-note')).toEqual([
      'cd-compliance-missing-waybill',
      'cd-compliance-missing-seal-top',
      'cd-compliance-missing-seal-bottom',
    ])
  })

  it('unit_test_case 39: nilai 0 pada penghitung kepatuhan tetap dirender "0"', async () => {
    const wrapper = await mountWith(
      makeSummary({
        compliance: {
          missing_waybill_count: 0,
          missing_seal_top_count: 0,
          missing_seal_bottom_count: 0,
          _note: NOTE_COMPLIANCE,
        },
      }),
    )

    for (const id of ['waybill', 'seal-top', 'seal-bottom']) {
      expect(rawText(wrapper, `cd-compliance-missing-${id}`)).toBe('0')
    }
  })

  it('unit_test_case 40: urutan baris tiga rekap MILIK SERVER — "tidak tercatat" di tengah tetap di tengah', async () => {
    const wrapper = await mountWith(makeSummary())

    expect(
      wrapper
        .findAll('[data-testid^="cd-recap-transport-company-value-"]')
        .map((cell) => cell.text().replace(/\s+/g, ' ').trim()),
    ).toEqual(['PT Kecil Sentosa', 'tidak tercatat', 'PT Besar Makmur'])

    expect(
      wrapper
        .findAll('[data-testid^="cd-recap-storage-tank-source-value-"]')
        .map((cell) => cell.text().replace(/\s+/g, ' ').trim()),
    ).toEqual(['Tangki 2', 'Tangki 1'])
  })

  it('unit_test_case 41: kesembilan prosa server dirender UTUH di tempatnya', async () => {
    const wrapper = await mountWith(makeSummary())
    const normalized = (value: string): string => value.replace(/\s+/g, ' ').trim()

    const expected: Array<[string, string]> = [
      ['cd-note-four-denominators', NOTE_FOUR_DENOMINATORS],
      ['cd-hourly-note', NOTE_HOURLY],
      ['cd-turnaround-note', NOTE_TURNAROUND],
      ['cd-quality-note', NOTE_QUALITY_DENOMINATORS],
      ['cd-quality-dobi-note', NOTE_DOBI_UNITLESS],
      ['cd-compliance-note', NOTE_COMPLIANCE],
      ['cd-note-no-labeled-recap', NOTE_NO_LABELED_RECAP],
      ['cd-note-no-quality-thresholds', NOTE_NO_QUALITY_THRESHOLDS],
      ['cd-note-shift', NOTE_SHIFT_NOT_PUBLISHED],
    ]

    expect(expected).toHaveLength(9)

    for (const [testid, prose] of expected) {
      expect(exists(wrapper, testid), testid).toBe(true)
      expect(rawText(wrapper, testid), testid).toBe(normalized(prose))
    }
  })

  it('unit_test_case 42: layar baca saja — nol permintaan bermetode tulis sepanjang bootstrap', async () => {
    await mountFullReport()

    for (const method of ['post', 'put', 'patch', 'delete'] as const) {
      expect(apiClientMocks[method]).not.toHaveBeenCalled()
    }

    for (const spy of Object.values(localDbMocks)) {
      expect(spy).not.toHaveBeenCalled()
    }

    expect(hits(EXPORT_PATH)).toBe(0)
  })
})

/* ================================================================== */
/* 13 — peran dan mill                                                 */
/* ================================================================== */

describe('peran dan pemilih Mill', () => {
  it.each(['operator', 'supervisor', 'mill_management'] as const)(
    'unit_test_case 43: %s tidak melihat pemilih Mill dan daftar mill tidak pernah dipanggil',
    async (role) => {
      asRole(role, 'bu-1')

      const wrapper = await mountView()

      expect(exists(wrapper, 'mill-select')).toBe(false)
      expect(hits(BUSINESS_UNITS_PATH)).toBe(0)
      expect(exists(wrapper, 'mill-current')).toBe(true)
    },
  )

  it('unit_test_case 44: Admin melihat pemilih Mill, dan sebelum memilih TIDAK satu permintaan pun dikirim selain daftar mill', async () => {
    asRole('admin', null)

    const wrapper = await mountView()

    expect(exists(wrapper, 'mill-select')).toBe(true)
    expect(exists(wrapper, 'mill-required-hint')).toBe(true)
    expect(hits(BUSINESS_UNITS_PATH)).toBe(1)
    expect(hits(PERIODS_PATH)).toBe(0)
    expect(hits(LINES_PATH)).toBe(0)
    expect(hits(SUMMARY_PATH)).toBe(0)
  })

  it('unit_test_case 45: akun terikat mill tanpa mill → arahan menghubungi Admin dan NOL permintaan', async () => {
    asRole('operator', null)

    const wrapper = await mountView()

    expect(exists(wrapper, 'no-mill-for-account')).toBe(true)
    expect(requests).toHaveLength(0)
    expect(exists(wrapper, 'mill-select')).toBe(false)
  })
})
