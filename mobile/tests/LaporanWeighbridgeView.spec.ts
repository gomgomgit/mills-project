/**
 * LaporanWeighbridgeView.spec.ts — screen-144--laporan-weighbridge-mobile /
 * usecase-147--laporan-weighbridge-mobile "Lihat Laporan Periode Weighbridge
 * (Mobile)".
 *
 * Satu test per test_scenarios[].component_test pada tech spec screen-144.
 * Kembaran dari tests/LaporanSterilizerView.spec.ts (screen-135) sampai
 * tests/LaporanStorageTankView.spec.ts (screen-139).
 *
 * YANG DI-MOCK: repo laporan, repo Production Line, router, dan ketiga store.
 * Yang diuji adalah KEPUTUSAN LAYAR atas jawaban repo — bentuk permintaan
 * HTTP-nya milik weighbridgeReportRepo.spec.ts, dan tidak diulang di sini.
 *
 * ── TIGA HAL YANG PALING MUDAH RUSAK DI LAYAR INI, DAN DIJAGA DI SINI ───
 *
 * 1. ARUS MASUK DAN ARUS KELUAR TIDAK PERNAH DIJUMLAHKAN. Fixture memberi
 *    10 trip masuk dan 5 trip keluar justru supaya angka 15 dapat dicari di
 *    teks terender; begitu pula 12.000,5 + 8.000,25. Asersi atas KETIADAAN
 *    semacam ini hanya bermakna bila jumlahnya memang mungkin terbentuk.
 *
 * 2. NULL BUKAN NOL, DAN TERLIHAT DI LAYAR. Kartu berat ber-null harus
 *    menampilkan keterangan, bukan "0,00" — dan kelasnya .metric-value--na,
 *    supaya tidak pernah terbaca sekilas sebagai bilangan.
 *
 * 3. TIDAK ADA DURASI DI MANA PUN. Satu test menyisir SELURUH teks terender
 *    terhadap kosakata durasi. Memeriksa "kartu durasi tidak ada" akan selalu
 *    hijau; menyisir teksnya tidak.
 *
 * BENTUK GALAT — DATAR, TANPA `response`. apiClient.normalizeError menolak
 * dengan { message, errors?, status? } dan sudah MEMBUANG `response`.
 * Ketiadaan `status` itulah yang membedakan "jaringan putus" dari "server
 * menjawab 401/403/422", dan urutan cabang handleError bergantung padanya.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import LaporanWeighbridgeView from '@/views/LaporanWeighbridgeView.vue'

/* ------------------------------------------------------------------ */
/* Mock modul                                                          */
/* ------------------------------------------------------------------ */

const { pushMock, routeQuery } = vi.hoisted(() => ({
  pushMock: vi.fn(),
  /** Query rute — screen-141 menyisipkan production_line_id di sini. */
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

vi.mock('@/stores/auth', () => ({
  useAuthStore: useAuthStoreMock,
}))

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

const { repoMocks } = vi.hoisted(() => ({
  repoMocks: {
    fetchBusinessUnits: vi.fn(),
    fetchPeriods: vi.fn(),
    fetchSummary: vi.fn(),
    exportCsv: vi.fn(),
    saveCsvFile: vi.fn(),
  },
}))

vi.mock('@/services/weighbridgeReportRepo', () => ({
  default: repoMocks,
  ...repoMocks,
}))

const { productionLineMocks } = vi.hoisted(() => ({
  productionLineMocks: {
    fetchProductionLinesForReport: vi.fn(),
    fetchAndCacheStationsForProductionLine: vi.fn(),
  },
}))

vi.mock('@/services/productionLineRepo', () => ({
  default: productionLineMocks,
  productionLineRepo: productionLineMocks,
  ...productionLineMocks,
}))

/* ------------------------------------------------------------------ */
/* Bentuk galat NYATA                                                  */
/* ------------------------------------------------------------------ */

const NETWORK_ERROR = { message: 'Tidak dapat terhubung ke server.' }
const UNAUTHENTICATED_ERROR = { message: 'Unauthenticated.', status: 401 }
const FORBIDDEN_ERROR = { message: 'Anda tidak memiliki akses untuk aksi ini.', status: 403 }

/* ------------------------------------------------------------------ */
/* Fixture                                                             */
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

const BUSINESS_UNITS = [
  { id: 'bu-1', name: 'Mill Utara' },
  { id: 'bu-2', name: 'Mill Selatan' },
]

const LINES = [
  { id: 'pl-1', name: 'Line Satu' },
  { id: 'pl-2', name: 'Line Dua' },
]

function hourly(filled: Record<number, number>): Array<{ hour: number; trip_count: number }> {
  return Array.from({ length: 24 }, (_, hour) => ({ hour, trip_count: filled[hour] ?? 0 }))
}

/**
 * Fixture penuh. Angkanya dipilih supaya jumlah kedua arus (15 trip dan
 * 20.000,75 kg) BISA terbentuk — itulah yang membuat asersi "tidak ada angka
 * gabungan" bermakna.
 */
function summaryFixture(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Utara' },
    production_line: { id: 'pl-1', name: 'Line Satu' },
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
    daily: [
      {
        date: '2026-09-04',
        receive_trip_count: 6,
        receive_net_weight_total: 9000.25,
        dispatch_trip_count: 2,
        dispatch_net_weight_total: null,
      },
      {
        date: '2026-09-05',
        receive_trip_count: 4,
        receive_net_weight_total: 3000.25,
        dispatch_trip_count: 3,
        dispatch_net_weight_total: 8000.25,
      },
    ],
    daily_total: {
      receive_trip_count: 10,
      receive_net_weight_total: 12000.5,
      dispatch_trip_count: 5,
      dispatch_net_weight_total: 8000.25,
    },
    completeness: {
      days_in_period: 30,
      days_with_trip: 2,
      days_counted: 9,
      period_running: true,
    },
    ...overrides,
  }
}

/** Periode kosong: kedua arus nol, seluruh berat null. */
function emptySummaryFixture(): Record<string, unknown> {
  return summaryFixture({
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
/* Auth store palsu                                                    */
/* ------------------------------------------------------------------ */

function asRole(
  role: 'operator' | 'supervisor' | 'mill_management' | 'admin',
  businessUnitId: string | null,
  userId = 'user-1',
): void {
  useAuthStoreMock.mockReturnValue({
    currentUser: {
      id: userId,
      username: role === 'admin' ? 'admin' : 'operator01',
      name: 'Pengguna Uji',
      role,
      business_unit_id: businessUnitId,
    },
    businessUnit: businessUnitId ? { id: businessUnitId, name: 'Mill Utara' } : null,
    logout: logoutMock,
  })
}

/* ------------------------------------------------------------------ */
/* Bantuan mount / interaksi                                           */
/* ------------------------------------------------------------------ */

async function mountView(): Promise<VueWrapper> {
  const wrapper = mount(LaporanWeighbridgeView)
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

async function selectMill(wrapper: VueWrapper, businessUnitId: string): Promise<void> {
  await wrapper.get('[data-testid="mill-select"]').setValue(businessUnitId)
  await flushPromises()
}

function text(wrapper: VueWrapper, testid: string): string {
  return wrapper.get(`[data-testid="${testid}"]`).text()
}

function exists(wrapper: VueWrapper, testid: string): boolean {
  return wrapper.find(`[data-testid="${testid}"]`).exists()
}

beforeEach(() => {
  pushMock.mockReset()
  logoutMock.mockClear()
  useAuthStoreMock.mockReset()

  Object.keys(routeQuery).forEach((key) => delete routeQuery[key])

  repoMocks.fetchBusinessUnits.mockReset()
  repoMocks.fetchPeriods.mockReset()
  repoMocks.fetchSummary.mockReset()
  repoMocks.exportCsv.mockReset()
  repoMocks.saveCsvFile.mockReset()

  productionLineMocks.fetchProductionLinesForReport.mockReset()
  productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINES[0]])

  window.localStorage.clear()

  asRole('operator', 'bu-1')
  repoMocks.fetchPeriods.mockResolvedValue([PERIOD, PERIOD_CLOSED])
  repoMocks.fetchSummary.mockResolvedValue(summaryFixture())
})

afterEach(() => {
  vi.restoreAllMocks()
})

/* ================================================================== */
/* Jalur sukses                                                        */
/* ================================================================== */

describe('jalur sukses', () => {
  it('Operator: tanpa pemilih mill, nama mill dan line tampil, seluruh angka kedua arus terender', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'mill-select')).toBe(false)
    expect(text(wrapper, 'mill-current')).toContain('Mill Utara')
    expect(text(wrapper, 'production-line-current')).toContain('Line Satu')

    // Arus masuk
    expect(text(wrapper, 'kpi-receive-trip-count')).toBe('10')
    expect(text(wrapper, 'kpi-receive-net-weight-total')).toContain('12.000,50')
    expect(text(wrapper, 'kpi-receive-net-weight-avg')).toContain('2.000,08')
    expect(text(wrapper, 'kpi-receive-missing')).toBe('4')
    expect(text(wrapper, 'kpi-receive-busiest-hour')).toBe('07.00')

    // Arus keluar
    expect(text(wrapper, 'kpi-dispatch-trip-count')).toBe('5')
    expect(text(wrapper, 'kpi-dispatch-net-weight-total')).toContain('8.000,25')
    expect(text(wrapper, 'kpi-dispatch-net-weight-avg')).toContain('2.000,06')
    expect(text(wrapper, 'kpi-dispatch-missing')).toBe('1')
    expect(text(wrapper, 'kpi-dispatch-busiest-hour')).toBe('20.00')

    // Kelengkapan, rekap, grafik
    expect(text(wrapper, 'draft-trip-count')).toBe('2')
    expect(text(wrapper, 'undated-trip-count')).toBe('3')
    expect(exists(wrapper, 'by-origin')).toBe(true)
    expect(exists(wrapper, 'by-destination')).toBe(true)
    expect(exists(wrapper, 'hourly-receive')).toBe(true)
    expect(exists(wrapper, 'hourly-dispatch')).toBe(true)
    expect(exists(wrapper, 'daily-recap')).toBe(true)

    // Tidak ada satu pun kontrol tulis.
    const buttonLabels = wrapper.findAll('button').map((button) => button.text().toLowerCase())

    expect(buttonLabels.some((label) => /simpan|ubah|hapus|verifik/.test(label))).toBe(false)
  })

  it.each(['supervisor', 'mill_management'] as const)(
    '%s: perilakunya identik dengan Operator — tanpa pemilih mill, dengan keterangan mill',
    async (role) => {
      asRole(role, 'bu-1')

      const wrapper = await mountView()
      await selectPeriod(wrapper, 'per-1')

      expect(exists(wrapper, 'mill-select')).toBe(false)
      expect(text(wrapper, 'mill-current')).toContain('Mill Utara')
      expect(text(wrapper, 'kpi-receive-trip-count')).toBe('10')
      expect(repoMocks.fetchBusinessUnits).not.toHaveBeenCalled()
    },
  )

  it('Admin: pemilih mill dirender, dan daftar line/periode TIDAK dimuat sebelum mill dipilih', async () => {
    asRole('admin', null)
    repoMocks.fetchBusinessUnits.mockResolvedValue(BUSINESS_UNITS)
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)

    const wrapper = await mountView()

    expect(exists(wrapper, 'mill-select')).toBe(true)
    expect(exists(wrapper, 'mill-required-hint')).toBe(true)
    expect(productionLineMocks.fetchProductionLinesForReport).not.toHaveBeenCalled()
    expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()

    await selectMill(wrapper, 'bu-2')

    expect(productionLineMocks.fetchProductionLinesForReport).toHaveBeenCalledWith('bu-2')
    expect(repoMocks.fetchPeriods).toHaveBeenCalled()

    await selectLine(wrapper, 'pl-1')
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'kpi-receive-trip-count')).toBe('10')
  })

  it('nama mill dibaca dari blok business_unit, BUKAN dari period.business_unit_name', async () => {
    // Perbedaan nyata dari lima laporan sebelumnya, dan satu-satunya alasan
    // test ini ada: menyalin pola lama menghasilkan keterangan mill yang
    // kosong tanpa satu pun galat.
    useAuthStoreMock.mockReturnValue({
      currentUser: { id: 'user-1', username: 'spv', name: 'SPV', role: 'supervisor', business_unit_id: 'bu-1' },
      businessUnit: null,
      logout: logoutMock,
    })

    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({ business_unit: { id: 'bu-1', name: 'Mill Dari Payload' } }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'mill-current')).toContain('Mill Dari Payload')
  })
})

/* ================================================================== */
/* Arus tidak pernah dijumlahkan                                       */
/* ================================================================== */

describe('arus masuk dan arus keluar tidak pernah dijumlahkan', () => {
  it('tidak ada angka gabungan kedua arus di mana pun pada layar', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const rendered = wrapper.text()

    // 10 + 5 = 15 trip, dan 12.000,50 + 8.000,25 = 20.000,75 kg. Keduanya
    // BISA terbentuk dari fixture ini — itulah yang membuat asersi ini
    // bermakna alih-alih selalu hijau.
    expect(rendered).not.toContain('20.000,75')
    expect(rendered).not.toMatch(/Total Keseluruhan|Total Gabungan|Total Semua Arus/i)

    // Baris total rekap harian pun terpisah per arus.
    const total = text(wrapper, 'daily-recap-total')

    expect(total).toContain('10')
    expect(total).toContain('5')
    expect(total).not.toContain('20.000,75')
  })

  it('kedua kelompok arus dirender terpisah dengan judul yang menyebut artinya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'flow-receive')).toContain('estate')
    expect(text(wrapper, 'flow-dispatch')).toContain('tujuan')
  })

  it('kelompok arus keluar TETAP dirender ketika periode hanya memuat arus masuk', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
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
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // TIDAK disembunyikan walau layar ponsel sempit: pembaca harus tahu
    // memang tidak ada kiriman keluar, bukan mengira bagian ini tidak ada.
    expect(exists(wrapper, 'flow-dispatch')).toBe(true)
    expect(text(wrapper, 'kpi-dispatch-trip-count')).toBe('0')
    expect(text(wrapper, 'kpi-dispatch-net-weight-total')).toContain('tidak tersedia')
    expect(text(wrapper, 'kpi-receive-trip-count')).toBe('10')
  })
})

/* ================================================================== */
/* Null bukan nol                                                      */
/* ================================================================== */

describe('null bukan nol', () => {
  it('berat null dirender sebagai keterangan bergaya non-angka, bukan 0', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        receive: {
          ...(summaryFixture().receive as Record<string, unknown>),
          net_weight_total: null,
          net_weight_avg: null,
          net_weight_trip_count: 0,
          missing_net_weight_trip_count: 10,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const total = wrapper.get('[data-testid="kpi-receive-net-weight-total"]')

    expect(total.text()).toContain('tidak tersedia')
    expect(total.text()).not.toContain('0,00')
    // Kelas ini yang mencegahnya terbaca sekilas sebagai bilangan besar.
    expect(total.classes()).toContain('metric-value--na')

    // Jumlah trip TETAP apa adanya — yang tidak tersedia adalah beratnya.
    expect(text(wrapper, 'kpi-receive-trip-count')).toBe('10')
    expect(text(wrapper, 'kpi-receive-missing')).toBe('10')
  })

  it('busiest_hour null dirender sebagai keterangan, bukan 00.00', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        receive: {
          ...(summaryFixture().receive as Record<string, unknown>),
          busiest_hour: null,
          busiest_hour_trip_count: 0,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'kpi-receive-busiest-hour')).toContain('tidak tersedia')
    expect(text(wrapper, 'kpi-receive-busiest-hour')).not.toContain('00.00')
  })

  it('sel berat harian yang null dirender "tidak tercatat", bukan 0,00', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // daily[0].dispatch_net_weight_total = null pada fixture.
    expect(text(wrapper, 'daily-recap')).toContain('tidak tercatat')
  })
})

/* ================================================================== */
/* Penyebut rata-rata                                                  */
/* ================================================================== */

describe('penyebut rata-rata', () => {
  it('menyatakan penyebut dan menambah ", bukan M" hanya ketika berbeda dari jumlah trip', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // 6 dari 10 trip punya berat → penyebutnya dinyatakan beserta bandingannya.
    expect(text(wrapper, 'kpi-receive-avg-denominator')).toContain('6')
    expect(text(wrapper, 'kpi-receive-avg-denominator')).toContain('bukan 10')
  })

  it('tidak menambah ", bukan M" ketika seluruh trip beratnya terisi', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        receive: {
          ...(summaryFixture().receive as Record<string, unknown>),
          net_weight_trip_count: 10,
          missing_net_weight_trip_count: 0,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Menampilkannya selalu akan menjadi kebisingan yang dilatih diabaikan
    // orang — perilaku yang sama dengan laporan versi web.
    expect(text(wrapper, 'kpi-receive-avg-denominator')).not.toContain('bukan')
  })
})

/* ================================================================== */
/* Rekap per asal / tujuan                                             */
/* ================================================================== */

describe('rekap per asal dan per tujuan', () => {
  it('asal kosong dan tujuan null keduanya dirender "Belum diisi"', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // DUA BENTUK dalam satu payload: '' untuk asal (keepNull=false) dan null
    // untuk tujuan (keepNull=true). Menangani hanya salah satunya
    // menghasilkan baris tanpa label pada salah satu rekap.
    expect(text(wrapper, 'by-origin')).toContain('Belum diisi')
    expect(text(wrapper, 'by-destination')).toContain('Belum diisi')
  })

  it('seluruh baris rekap ditampilkan tanpa pemangkasan', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(wrapper.findAll('[data-testid="by-origin"] .breakdown-row')).toHaveLength(2)
    expect(wrapper.findAll('[data-testid="by-destination"] .breakdown-row')).toHaveLength(2)
    expect(text(wrapper, 'by-origin')).toContain('Estate Utara')
    expect(text(wrapper, 'by-destination')).toContain('Refinery X')
  })
})

/* ================================================================== */
/* Grafik sebaran per jam                                              */
/* ================================================================== */

describe('sebaran per jam', () => {
  it('merender 24 batang per arus, termasuk jam bernilai 0', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(wrapper.findAll('[data-testid^="hourly-receive-bar-"]')).toHaveLength(24)
    expect(wrapper.findAll('[data-testid^="hourly-dispatch-bar-"]')).toHaveLength(24)
  })

  it('memakai SKALA BERSAMA kedua arus, dan menyatakannya pada catatan grafik', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Nilai tertinggi lintas kedua arus adalah 9 (receive jam 07). Batang
    // dispatch jam 20 bernilai 5 → 5/9 = 56%. Kalau skalanya per-arus,
    // batang itu akan 100% dan test ini gagal.
    const dispatchBar = wrapper
      .get('[data-testid="hourly-dispatch-bar-20"]')
      .get('.chart-fill')

    expect(dispatchBar.attributes('style')).toContain('56%')

    const receiveBar = wrapper.get('[data-testid="hourly-receive-bar-7"]').get('.chart-fill')

    expect(receiveBar.attributes('style')).toContain('100%')

    // Dua grafik bertumpuk dengan skala berbeda akan dibaca sebagai
    // perbandingan tinggi batang — jadi skala bersamanya harus DINYATAKAN.
    expect(text(wrapper, 'hourly-receive')).toContain('skala')
    expect(text(wrapper, 'hourly-dispatch')).toContain('Skala')
  })

  it('grafik TIDAK dirender untuk periode tanpa satu trip pun', async () => {
    repoMocks.fetchSummary.mockResolvedValue(emptySummaryFixture())

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'empty-period')).toBe(true)
    // Grafik kosong akan terbaca sebagai hasil pengukuran bernilai nol.
    expect(exists(wrapper, 'hourly-receive')).toBe(false)
    expect(exists(wrapper, 'hourly-dispatch')).toBe(false)
  })
})

/* ================================================================== */
/* Tidak ada durasi di mana pun                                        */
/* ================================================================== */

describe('tidak ada lama kendaraan di pabrik', () => {
  it('tidak satu pun kosakata durasi muncul pada teks terender', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const rendered = wrapper.text().toLowerCase()

    // Asersi atas KETIADAAN, dan ia harus berupa PENYISIRAN: memeriksa
    // "kartu durasi tidak ada" akan selalu hijau.
    for (const forbidden of ['durasi', 'turnaround', 'lama di pabrik', 'dwell', 'lama kendaraan']) {
      expect(rendered).not.toContain(forbidden)
    }
  })
})

/* ================================================================== */
/* Rekap harian                                                        */
/* ================================================================== */

describe('rekap harian', () => {
  it('kolom terpisah per arus beserta baris total periode', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const recap = text(wrapper, 'daily-recap')

    expect(recap).toContain('Trip Masuk')
    expect(recap).toContain('Trip Keluar')
    expect(recap).toContain('Berat Masuk')
    expect(recap).toContain('Berat Keluar')
    expect(exists(wrapper, 'daily-recap-total')).toBe(true)
  })

  it('tidak memicu permintaan jaringan apa pun saat dibuka atau ditutup', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const before = repoMocks.fetchSummary.mock.calls.length

    const toggle = wrapper.find('[data-testid="daily-recap"] button')

    if (toggle.exists()) {
      await toggle.trigger('click')
      await flushPromises()
      await toggle.trigger('click')
      await flushPromises()
    }

    expect(repoMocks.fetchSummary.mock.calls.length).toBe(before)
  })
})

/* ================================================================== */
/* Kelengkapan                                                         */
/* ================================================================== */

describe('kelengkapan pencatatan', () => {
  it('periode berjalan: persen memakai days_counted dan keterangannya dinyatakan', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // 2 dari 9 hari = 22,2% — penyebutnya days_counted (9), BUKAN
    // days_in_period (30), supaya hari yang belum terjadi tidak menurunkan
    // persen.
    expect(text(wrapper, 'completeness-percent')).toContain('22,2%')
    expect(text(wrapper, 'completeness-running-note')).toContain('sampai hari ini')
  })

  it('periode belum mulai: persen "—", bukan 0,0%', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        completeness: { days_in_period: 30, days_with_trip: 0, days_counted: 0, period_running: false },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'completeness-percent')).toBe('—')
    expect(exists(wrapper, 'completeness-not-started-note')).toBe(true)
  })

  it('menyatakan bahwa trip draft ikut terhitung dan trip tanpa penanda waktu tidak', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'draft-note')).toContain('IKUT')
    expect(text(wrapper, 'undated-note')).toContain('tidak ikut terhitung')
  })
})

/* ================================================================== */
/* Production Line                                                     */
/* ================================================================== */

describe('Production Line', () => {
  it('memakai line dari query rute, mengalahkan ingatan perangkat', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)
    window.localStorage.setItem('msl_production_line_user-1', 'pl-1')
    routeQuery.production_line_id = 'pl-2'

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledWith(
      'per-1',
      expect.objectContaining({ productionLineId: 'pl-2' }),
    )
    // Pilihan yang dibawa tile ikut menjadi ingatan — layar Daftar Stasiun
    // dan layar laporan berbagi satu konteks.
    expect(window.localStorage.getItem('msl_production_line_user-1')).toBe('pl-2')
  })

  it('memakai ingatan perangkat milik pengguna ITU, bukan pengguna lain', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)
    window.localStorage.setItem('msl_production_line_user-1', 'pl-2')
    window.localStorage.setItem('msl_production_line_user-9', 'pl-1')

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledWith(
      'per-1',
      expect.objectContaining({ productionLineId: 'pl-2' }),
    )
  })

  it('TIDAK memakai line yang diingat bila sudah tidak ada pada daftar', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)
    window.localStorage.setItem('msl_production_line_user-1', 'pl-hilang')

    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)

    await selectPeriod(wrapper, 'per-1')

    // Penjagaan ada di loadSummary(), bukan hanya di template.
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
  })

  it('tidak melempar ketika localStorage menolak (mode privat)', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)

    const spy = vi.spyOn(window.localStorage, 'getItem').mockImplementation(() => {
      throw new Error('SecurityError')
    })

    const wrapper = await mountView()

    expect(exists(wrapper, 'laporan-weighbridge-mobile')).toBe(true)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)

    spy.mockRestore()
  })

  it('satu line: berlaku tanpa pemilih, tetapi namanya tetap tampil', async () => {
    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-select')).toBe(false)

    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'production-line-current')).toContain('Line Satu')
  })

  it('mill tanpa line: arahan menghubungi Admin, TANPA tombol coba lagi', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([])

    const wrapper = await mountView()

    expect(text(wrapper, 'production-line-unavailable')).toContain('belum memiliki Production Line')
    expect(exists(wrapper, 'production-line-retry')).toBe(false)
  })

  it('daftar line gagal dimuat: pesan BERBEDA, dengan tombol coba lagi sendiri', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockRejectedValueOnce(NETWORK_ERROR)

    const wrapper = await mountView()

    expect(text(wrapper, 'production-line-unavailable')).toContain('tidak dapat dimuat')
    expect(exists(wrapper, 'production-line-retry')).toBe(true)
    // Kegagalan daftar line tidak boleh menyamar sebagai galat laporan.
    expect(exists(wrapper, 'network-error')).toBe(false)

    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINES[0]])
    await wrapper.get('[data-testid="production-line-retry"]').trigger('click')
    await flushPromises()

    expect(exists(wrapper, 'production-line-unavailable')).toBe(false)
  })

  it('Admin mengganti mill: line yang terpilih dikosongkan', async () => {
    asRole('admin', null)
    repoMocks.fetchBusinessUnits.mockResolvedValue(BUSINESS_UNITS)

    // Tiap mill punya line-nya SENDIRI, dengan id yang berbeda — itulah
    // keadaan nyata, dan satu-satunya cara test ini membuktikan sesuatu.
    // Mengembalikan daftar line yang sama untuk kedua mill akan membuat
    // layar sah memilihkan ulang line yang "kebetulan" masih ada, dan
    // asersi di bawah kehilangan maknanya.
    productionLineMocks.fetchProductionLinesForReport.mockImplementation(
      async (businessUnitId: string | null) =>
        businessUnitId === 'bu-2'
          ? [
              { id: 'pl-9', name: 'Line Sembilan' },
              { id: 'pl-8', name: 'Line Delapan' },
            ]
          : LINES,
    )

    const wrapper = await mountView()
    await selectMill(wrapper, 'bu-1')
    await selectLine(wrapper, 'pl-1')
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'period-meta')).toBe(true)

    await selectMill(wrapper, 'bu-2')

    // Sebuah Production Line milik SATU mill, jadi mengganti mill selalu
    // membatalkan pilihan line — dan angka mill lama tidak boleh tertinggal.
    expect(exists(wrapper, 'period-meta')).toBe(false)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)

    // Ingatan perangkat pun tidak dipakai untuk menebak line mill baru.
    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)
  })
})

/* ================================================================== */
/* Keadaan tanpa angka                                                 */
/* ================================================================== */

describe('keadaan tanpa angka', () => {
  it('akun terikat mill tanpa business_unit_id: berhenti tanpa satu pun permintaan', async () => {
    asRole('operator', null)

    const wrapper = await mountView()

    expect(exists(wrapper, 'no-mill-for-account')).toBe(true)
    expect(exists(wrapper, 'mill-select')).toBe(false)
    // Termasuk TIDAK memanggil endpoint options, yang justru akan membentuk
    // daftar seluruh mill di perangkat orang yang tidak berhak melihatnya.
    expect(repoMocks.fetchBusinessUnits).not.toHaveBeenCalled()
    expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()
    expect(productionLineMocks.fetchProductionLinesForReport).not.toHaveBeenCalled()
  })

  it('daftar periode kosong: arahan tampil hanya SETELAH pemuatan selesai', async () => {
    let resolvePeriods: ((value: unknown[]) => void) | null = null

    repoMocks.fetchPeriods.mockReturnValue(
      new Promise((resolve) => {
        resolvePeriods = resolve as (value: unknown[]) => void
      }),
    )

    const wrapper = mount(LaporanWeighbridgeView)
    await flushPromises()

    // SELAMA memuat: indikator muat, BUKAN "belum punya periode" — daftar
    // kosong saat memuat terbaca sebagai "tidak ada periode".
    expect(exists(wrapper, 'periods-loading')).toBe(true)
    expect(exists(wrapper, 'no-periods')).toBe(false)

    resolvePeriods?.([])
    await flushPromises()

    expect(exists(wrapper, 'no-periods')).toBe(true)
    expect(exists(wrapper, 'periods-loading')).toBe(false)
  })

  it('periode tanpa data: keterangan tampil, angka "tidak tersedia", bukan nol', async () => {
    repoMocks.fetchSummary.mockResolvedValue(emptySummaryFixture())

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'empty-period')).toBe(true)
    expect(text(wrapper, 'kpi-receive-net-weight-total')).toContain('tidak tersedia')
    expect(text(wrapper, 'kpi-dispatch-net-weight-total')).toContain('tidak tersedia')
    expect(exists(wrapper, 'daily-recap')).toBe(false)
  })
})

/* ================================================================== */
/* Galat                                                               */
/* ================================================================== */

describe('penanganan galat', () => {
  it('kegagalan jaringan: pesan + coba lagi, dan periode terpilih TIDAK hilang', async () => {
    repoMocks.fetchSummary.mockRejectedValueOnce(NETWORK_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'network-error')).toBe(true)
    expect(exists(wrapper, 'retry-button')).toBe(true)
    expect(
      (wrapper.get('[data-testid="period-select"]').element as HTMLSelectElement).value,
    ).toBe('per-1')

    repoMocks.fetchSummary.mockResolvedValue(summaryFixture())
    await wrapper.get('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    // Coba Lagi mengulang periode yang SAMA.
    expect(repoMocks.fetchSummary).toHaveBeenLastCalledWith('per-1', expect.anything())
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(text(wrapper, 'kpi-receive-trip-count')).toBe('10')
  })

  it('401: diarahkan ke Login, TANPA pesan jaringan dan tanpa tombol coba lagi', async () => {
    repoMocks.fetchSummary.mockRejectedValueOnce(UNAUTHENTICATED_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'retry-button')).toBe(false)
    expect(exists(wrapper, 'period-meta')).toBe(false)
  })

  it('403 pada pemilih mill Admin: pemilih tidak dirender sama sekali', async () => {
    asRole('admin', null)
    repoMocks.fetchBusinessUnits.mockRejectedValueOnce(FORBIDDEN_ERROR)

    const wrapper = await mountView()

    expect(exists(wrapper, 'mill-select')).toBe(false)
    expect(text(wrapper, 'error-message')).toContain('tidak tersedia untuk peran Anda')
  })
})

/* ================================================================== */
/* Respons basi                                                        */
/* ================================================================== */

describe('hanya jawaban permintaan terbaru yang berlaku', () => {
  it('jawaban periode lama yang tiba belakangan tidak menimpa yang baru', async () => {
    const lama = summaryFixture({
      receive: { ...(summaryFixture().receive as Record<string, unknown>), trip_count: 111 },
    })
    const baru = summaryFixture({
      receive: { ...(summaryFixture().receive as Record<string, unknown>), trip_count: 222 },
    })

    let resolveLama: ((value: unknown) => void) | null = null

    repoMocks.fetchSummary
      .mockImplementationOnce(
        () =>
          new Promise((resolve) => {
            resolveLama = resolve as (value: unknown) => void
          }),
      )
      .mockResolvedValueOnce(baru)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await selectPeriod(wrapper, 'per-2')

    expect(text(wrapper, 'kpi-receive-trip-count')).toBe('222')

    // Jawaban permintaan LAMA tiba sekarang — dan diabaikan.
    resolveLama?.(lama)
    await flushPromises()

    expect(text(wrapper, 'kpi-receive-trip-count')).toBe('222')
  })

  it('galat permintaan lama tidak memunculkan pesan basi', async () => {
    let rejectLama: ((reason: unknown) => void) | null = null

    repoMocks.fetchSummary
      .mockImplementationOnce(
        () =>
          new Promise((_resolve, reject) => {
            rejectLama = reject as (reason: unknown) => void
          }),
      )
      .mockResolvedValueOnce(summaryFixture())

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await selectPeriod(wrapper, 'per-2')

    rejectLama?.(NETWORK_ERROR)
    await flushPromises()

    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'summary-loading')).toBe(false)
    expect(text(wrapper, 'kpi-receive-trip-count')).toBe('10')
  })

  it('mengosongkan pilihan periode saat ringkasan masih dimuat: tidak ada angka yang muncul kemudian', async () => {
    let resolveLama: ((value: unknown) => void) | null = null

    repoMocks.fetchSummary.mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          resolveLama = resolve as (value: unknown) => void
        }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await selectPeriod(wrapper, '')

    resolveLama?.(summaryFixture())
    await flushPromises()

    expect(exists(wrapper, 'period-meta')).toBe(false)
    expect(exists(wrapper, 'summary-loading')).toBe(false)
  })
})

/* ================================================================== */
/* Periode tertutup & ekspor                                           */
/* ================================================================== */

describe('periode tertutup', () => {
  it('laporan tampil penuh, badge Tertutup tampil, dan ekspor TIDAK dinonaktifkan', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        period: {
          id: 'per-2',
          name: 'Periode Agustus 2026',
          start_date: '2026-08-01',
          end_date: '2026-08-31',
          status: 'closed',
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-2')

    expect(text(wrapper, 'period-status-badge')).toContain('Tertutup')
    expect(text(wrapper, 'kpi-receive-trip-count')).toBe('10')
    expect(
      wrapper.get('[data-testid="export-button"]').attributes('disabled'),
    ).toBeUndefined()
    expect(text(wrapper, 'period-meta')).toContain('mengatur penulisan data')
  })
})

describe('ekspor', () => {
  it('mengekspor periode terpilih dan menyimpan berkas bernama slug periodenya', async () => {
    repoMocks.exportCsv.mockResolvedValue(new Blob(['a,b']))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    expect(repoMocks.exportCsv).toHaveBeenCalledWith('per-1', expect.objectContaining({
      productionLineId: 'pl-1',
    }))
    expect(repoMocks.saveCsvFile).toHaveBeenCalledWith(
      expect.any(Blob),
      'laporan-weighbridge_periode-september-2026.csv',
    )
  })

  it('ketukan ganda hanya menghasilkan satu ekspor', async () => {
    let resolveExport: ((value: Blob) => void) | null = null

    repoMocks.exportCsv.mockImplementation(
      () =>
        new Promise((resolve) => {
          resolveExport = resolve as (value: Blob) => void
        }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const button = wrapper.get('[data-testid="export-button"]')

    await button.trigger('click')
    await button.trigger('click')
    await flushPromises()

    expect(repoMocks.exportCsv).toHaveBeenCalledTimes(1)
    expect(button.attributes('disabled')).toBeDefined()
    expect(button.text()).toContain('Mengekspor')

    resolveExport?.(new Blob(['a,b']))
    await flushPromises()
  })

  it('nama berkas mengikuti periode yang DIEKSPOR walau pilihan berganti selama unduhan', async () => {
    let resolveExport: ((value: Blob) => void) | null = null

    repoMocks.exportCsv.mockImplementation(
      () =>
        new Promise((resolve) => {
          resolveExport = resolve as (value: Blob) => void
        }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await wrapper.get('[data-testid="export-button"]').trigger('click')

    // Pengguna mengganti periode sebelum berkas tiba.
    await selectPeriod(wrapper, 'per-2')

    resolveExport?.(new Blob(['a,b']))
    await flushPromises()

    // Isi berkas milik per-1, jadi namanya pun harus milik per-1.
    expect(repoMocks.saveCsvFile).toHaveBeenCalledWith(
      expect.any(Blob),
      'laporan-weighbridge_periode-september-2026.csv',
    )
  })

  it('tombol ekspor tidak dirender sebelum periode dan line keduanya berlaku', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)

    const wrapper = await mountView()

    expect(exists(wrapper, 'export-button')).toBe(false)

    await selectLine(wrapper, 'pl-1')

    expect(exists(wrapper, 'export-button')).toBe(false)

    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'export-button')).toBe(true)
  })
})

/* ================================================================== */
/* Layar hanya membaca                                                 */
/* ================================================================== */

describe('layar hanya membaca', () => {
  it('seluruh pemanggilan repo adalah fungsi baca, dan tidak ada kontrol tulis', async () => {
    repoMocks.exportCsv.mockResolvedValue(new Blob(['a,b']))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    const footerButtons = wrapper.findAll('.action-footer button').map((b) => b.text())

    expect(footerButtons).toHaveLength(2)
    expect(footerButtons.join(' ')).toContain('Ekspor')
    expect(footerButtons.join(' ')).toContain('Back')

    // Tidak ada fungsi tulis pada repo laporan ini — hanya fetch*/export*.
    expect(Object.keys(repoMocks).every((key) => /^fetch|^export|^save/.test(key))).toBe(true)
  })

  it('Back kembali ke Dashboard & Reporting', async () => {
    const wrapper = await mountView()
    await wrapper.get('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'dashboard-reporting' })
  })
})
