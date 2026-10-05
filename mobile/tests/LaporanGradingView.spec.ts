/**
 * LaporanGradingView.spec.ts — screen-147--laporan-grading-mobile /
 * usecase-150--laporan-grading-mobile "Lihat Laporan Periode Grading
 * (Mobile)".
 *
 * Satu test per test_scenarios[].component_test pada tech spec screen-147.
 * Kembaran dari tests/LaporanSterilizerView.spec.ts (screen-135) sampai
 * tests/LaporanWeighbridgeView.spec.ts (screen-144).
 *
 * YANG DI-MOCK: repo laporan, repo Production Line, router, dan ketiga store.
 * Yang diuji adalah KEPUTUSAN LAYAR atas jawaban repo — bentuk permintaan
 * HTTP-nya milik gradingReportRepo.spec.ts dan tidak diulang di sini.
 *
 * ── EMPAT HAL YANG PALING MUDAH RUSAK DI LAYAR INI ──────────────────────
 *
 * 1. SATUAN JANJANG DAN KILOGRAM TIDAK PERNAH DIJUMLAHKAN. Fixture memberi
 *    total 100 janjang dan 40 kg justru supaya angka 140 dapat dicari di teks
 *    terender. Asersi atas KETIADAAN hanya bermakna bila jumlahnya memang
 *    mungkin terbentuk.
 *
 * 2. PENYEBUT RATA-RATA WAJIB TERCETAK. Pada layar sempit kolom itu yang
 *    paling mudah dikorbankan demi ruang, dan tanpanya rata-rata terbaca
 *    sebagai angka periode. Fixture memberi load_count parameter 2 dari 10
 *    muatan supaya "2 dari 10 muatan" dapat dicari apa adanya.
 *
 * 3. BAGIAN SATUAN YANG KOSONG TETAP DIRENDER. Bagian yang hilang terbaca
 *    sebagai "tidak ada bagian ini", padahal yang benar adalah "tidak ada
 *    isinya" — dan hanya yang kedua benar.
 *
 * 4. NULL BUKAN NOL, DAN TERLIHAT DI LAYAR, lewat kelas .metric-value--na
 *    supaya tidak pernah terbaca sekilas sebagai bilangan.
 *
 * BENTUK GALAT — DATAR, TANPA `response`. Ketiadaan `status` itulah yang
 * membedakan "jaringan putus" dari "server menjawab 401/403/422", dan urutan
 * cabang handleError bergantung padanya.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import LaporanGradingView from '@/views/LaporanGradingView.vue'

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

vi.mock('@/services/gradingReportRepo', () => ({
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

const BUSINESS_UNITS = [
  { id: 'bu-1', name: 'Mill Utara' },
  { id: 'bu-2', name: 'Mill Selatan' },
]

const LINES = [
  { id: 'pl-1', name: 'Line Satu' },
  { id: 'pl-2', name: 'Line Dua' },
]

/**
 * Fixture penuh. Total 100 janjang dan 40 kg dipilih supaya jumlahnya (140)
 * BISA terbentuk, dan load_count parameter 2 dari 10 muatan supaya penyebutnya
 * dapat dicari apa adanya.
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
    load_count: 10,
    netto_total: 8000.5,
    netto_avg: 800.05,
    bunch_total: 900,
    bunch_avg: 90,
    bunch: {
      quantity_total: 100,
      parameter_count: 2,
      rows: [
        {
          grading_parameter_id: 'gp-1',
          name: 'Mentah',
          quantity_total: 30,
          share_percent: 30,
          avg_percentage: 12.5,
          load_count: 2,
        },
        {
          grading_parameter_id: 'gp-2',
          name: 'Masak',
          quantity_total: 70,
          share_percent: 70,
          avg_percentage: 80,
          load_count: 10,
        },
      ],
    },
    kg: {
      quantity_total: 40,
      parameter_count: 1,
      rows: [
        {
          grading_parameter_id: 'gp-3',
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
    daily: [
      { date: '2026-09-04', load_count: 6, netto_total: 5000.25, bunch_total: 500 },
      { date: '2026-09-05', load_count: 4, netto_total: null, bunch_total: 400 },
    ],
    daily_total: {
      load_count: 10,
      netto_total: 8000.5,
      bunch_total: 900,
    },
    completeness: {
      days_in_period: 30,
      days_with_load: 2,
      days_counted: 9,
      period_running: true,
    },
    ...overrides,
  }
}

/** Periode kosong: nol muatan, seluruh total null, kedua blok kosong. */
function emptySummaryFixture(): Record<string, unknown> {
  return summaryFixture({
    load_count: 0,
    netto_total: null,
    netto_avg: null,
    bunch_total: null,
    bunch_avg: null,
    bunch: { quantity_total: null, parameter_count: 0, rows: [] },
    kg: { quantity_total: null, parameter_count: 0, rows: [] },
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
  const wrapper = mount(LaporanGradingView)
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
  it('Operator: tanpa pemilih mill, seluruh bagian laporan terender', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'mill-select')).toBe(false)
    expect(text(wrapper, 'mill-current')).toContain('Mill Utara')
    expect(text(wrapper, 'production-line-current')).toContain('Line Satu')

    expect(text(wrapper, 'kpi-load-count')).toBe('10')
    expect(text(wrapper, 'kpi-netto-total')).toContain('8.000,50')
    expect(text(wrapper, 'kpi-netto-avg')).toContain('800,05')
    expect(text(wrapper, 'kpi-bunch-total')).toContain('900')

    // Kedua bagian parameter, masing-masing dengan tabelnya sendiri.
    expect(exists(wrapper, 'parameter-bunch-table')).toBe(true)
    expect(exists(wrapper, 'parameter-kg-table')).toBe(true)
    expect(exists(wrapper, 'parameter-share-note')).toBe(true)
    expect(exists(wrapper, 'by-estate-supplier-table')).toBe(true)
    expect(exists(wrapper, 'completeness')).toBe(true)
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
      expect(text(wrapper, 'kpi-load-count')).toBe('10')
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

    expect(text(wrapper, 'kpi-load-count')).toBe('10')
  })

  it('nama mill dibaca dari blok business_unit, BUKAN dari period.business_unit_name', async () => {
    // Perbedaan nyata dari lima laporan pertama, dan satu-satunya alasan test
    // ini ada: menyalin pola lama menghasilkan keterangan mill yang kosong
    // tanpa satu pun galat.
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
/* Dua satuan, tidak pernah dijumlahkan                                */
/* ================================================================== */

describe('satuan janjang dan kilogram', () => {
  it('tidak ada angka gabungan kedua satuan di mana pun pada layar', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const rendered = wrapper.text()

    // 100 + 40 = 140 — angka yang BISA terbentuk dari fixture ini, dan itulah
    // yang membuat asersi ini bermakna alih-alih selalu hijau.
    expect(rendered).not.toContain('140,00')
    expect(rendered).not.toMatch(/Total Keseluruhan|Total Gabungan|Total Semua Satuan/i)

    // Dan total tiap bagian memang terender terpisah.
    expect(text(wrapper, 'parameter-bunch-total')).toContain('100,00')
    expect(text(wrapper, 'parameter-kg-total')).toContain('40,00')
  })

  it('kedua bagian dirender dengan judul yang menyebut satuannya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'parameter-bunch')).toContain('Janjang')
    expect(text(wrapper, 'parameter-kg')).toContain('Kilogram')
  })

  it('bagian satuan tanpa baris TETAP dirender, bukan disembunyikan', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({ kg: { quantity_total: null, parameter_count: 0, rows: [] } }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Bagian yang hilang akan terbaca sebagai "tidak ada bagian ini", padahal
    // yang benar adalah "tidak ada isinya".
    expect(exists(wrapper, 'parameter-kg')).toBe(true)
    expect(exists(wrapper, 'parameter-kg-empty')).toBe(true)
    expect(exists(wrapper, 'parameter-kg-table')).toBe(false)
    expect(text(wrapper, 'parameter-kg-total')).toContain('tidak tersedia')
    // Bagian janjang tetap menampilkan angkanya sendiri.
    expect(exists(wrapper, 'parameter-bunch-table')).toBe(true)
  })
})

/* ================================================================== */
/* Dua angka per parameter, dan penyebutnya                            */
/* ================================================================== */

describe('pangsa, rata-rata, dan penyebutnya', () => {
  it('mencetak penyebut rata-rata sebagai jumlah muatan yang mencatat parameter itu', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const table = text(wrapper, 'parameter-bunch-table')

    // Mentah ada pada 2 dari 10 muatan; Masak pada 10 dari 10.
    expect(table).toContain('2 dari 10 muatan')
    expect(table).toContain('10 dari 10 muatan')
  })

  it('menampilkan pangsa dan rata-rata sebagai dua angka berbeda pada baris yang sama', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const firstRow = wrapper.findAll('[data-testid="parameter-bunch-row"]')[0].text()

    expect(firstRow).toContain('30,00%')
    expect(firstRow).toContain('12,50%')
  })

  it('merender keterangan pangsa vs rata-rata UTUH, bukan dipotong', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const note = text(wrapper, 'parameter-share-note')

    // Dua angka yang membantah tanpa sebab yang dinyatakan lebih buruk
    // daripada satu angka saja — jadi ketiga kalimatnya harus ada.
    expect(note).toContain('berbobot menurut besar muatan')
    expect(note).toContain('tiap muatan berbobot sama')
    expect(note).toContain('benar-benar mencatat')
    expect(note).toContain('tidak pernah dijumlahkan')
  })

  it('share_percent dan avg_percentage null dirender sebagai keterangan, bukan 0', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        bunch: {
          quantity_total: 0,
          parameter_count: 1,
          rows: [
            {
              grading_parameter_id: 'gp-1',
              name: 'Mentah',
              quantity_total: 0,
              share_percent: null,
              avg_percentage: null,
              load_count: 1,
            },
          ],
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const row = wrapper.findAll('[data-testid="parameter-bunch-row"]')[0].text()

    expect(row).toContain('tidak tersedia')
    expect(row).not.toContain('0,00%')
  })
})

/* ================================================================== */
/* Null bukan nol                                                      */
/* ================================================================== */

describe('null bukan nol', () => {
  it('netto null dirender sebagai keterangan bergaya non-angka', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({ netto_total: null, netto_avg: null }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const total = wrapper.get('[data-testid="kpi-netto-total"]')

    expect(total.text()).toContain('tidak tersedia')
    expect(total.text()).not.toContain('0,00')
    // Kelas ini yang mencegahnya terbaca sekilas sebagai bilangan besar.
    expect(total.classes()).toContain('metric-value--na')

    // Jumlah muatan TETAP apa adanya — yang tidak tersedia adalah nettonya.
    expect(text(wrapper, 'kpi-load-count')).toBe('10')
  })

  it('sel netto harian yang null dirender "tidak tercatat", bukan 0,00', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // daily[1].netto_total = null pada fixture.
    expect(text(wrapper, 'daily-recap')).toContain('tidak tercatat')
  })
})

/* ================================================================== */
/* Rekap per asal & kelengkapan                                        */
/* ================================================================== */

describe('rekap per asal dan kelengkapan', () => {
  it('asal kosong dirender "Belum diisi" dan barisnya tidak dibuang', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'by-estate-supplier')).toContain('Belum diisi')
    expect(text(wrapper, 'by-estate-supplier')).toContain('Estate Utara')
    expect(wrapper.findAll('[data-testid="by-estate-supplier-row"]')).toHaveLength(2)
  })

  it('keenam penghitung kelengkapan terender pada satu bagian', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    for (const testid of [
      'completeness-days',
      'loads-without-detail',
      'draft-load-count',
      'loads-without-division',
      'loads-not-checked',
      'loads-not-acknowledged',
    ]) {
      expect(exists(wrapper, testid), testid).toBe(true)
    }

    expect(text(wrapper, 'loads-without-detail')).toBe('3')
    expect(text(wrapper, 'draft-load-count')).toBe('2')
    expect(text(wrapper, 'loads-not-checked')).toBe('5')
  })

  it('menyatakan bahwa muatan tanpa baris parameter tetap terhitung, dan ketiadaan penghitung tanpa tanggal', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'loads-without-detail-note')).toContain('tidak menyumbang apa pun')
    expect(text(wrapper, 'draft-note')).toContain('IKUT terhitung')
    // Ketiadaan penghitung "tanpa tanggal" DINYATAKAN, bukan dibiarkan
    // terbaca sebagai kelalaian.
    expect(text(wrapper, 'completeness-note')).toContain('kolom wajib')
  })

  it('periode berjalan: persen memakai days_counted dan keterangannya dinyatakan', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // 2 dari 9 hari = 22,2% — penyebutnya days_counted (9), BUKAN
    // days_in_period (30).
    expect(text(wrapper, 'completeness-percent')).toContain('22,2%')
    expect(text(wrapper, 'completeness-running-note')).toContain('sampai hari ini')
  })

  it('periode belum mulai: persen "—", bukan 0,0%', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        completeness: { days_in_period: 30, days_with_load: 0, days_counted: 0, period_running: false },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'completeness-percent')).toBe('—')
    expect(exists(wrapper, 'completeness-not-started-note')).toBe(true)
  })
})

/* ================================================================== */
/* Rekap harian                                                        */
/* ================================================================== */

describe('rekap harian', () => {
  it('menampilkan baris harian dan baris total periode', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const recap = text(wrapper, 'daily-recap')

    expect(recap).toContain('Muatan')
    expect(recap).toContain('Netto')
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

    expect(exists(wrapper, 'laporan-grading-mobile')).toBe(true)
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

    // Tiap mill punya line-nya SENDIRI, dengan id berbeda — itulah keadaan
    // nyata, dan satu-satunya cara test ini membuktikan sesuatu.
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

    expect(exists(wrapper, 'period-meta')).toBe(false)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
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

    const wrapper = mount(LaporanGradingView)
    await flushPromises()

    // SELAMA memuat: indikator muat, BUKAN "belum punya periode".
    expect(exists(wrapper, 'periods-loading')).toBe(true)
    expect(exists(wrapper, 'no-periods')).toBe(false)

    resolvePeriods?.([])
    await flushPromises()

    expect(exists(wrapper, 'no-periods')).toBe(true)
    expect(exists(wrapper, 'periods-loading')).toBe(false)
  })

  it('periode tanpa data: keterangan tampil, angka "tidak tersedia", kedua bagian tetap ada', async () => {
    repoMocks.fetchSummary.mockResolvedValue(emptySummaryFixture())

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'empty-period')).toBe(true)
    expect(text(wrapper, 'kpi-netto-total')).toContain('tidak tersedia')
    // Kedua bagian parameter TETAP dirender, dengan keterangan.
    expect(exists(wrapper, 'parameter-bunch')).toBe(true)
    expect(exists(wrapper, 'parameter-kg')).toBe(true)
    expect(exists(wrapper, 'parameter-bunch-empty')).toBe(true)
    // Rekap harian tidak digambar.
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

    expect(repoMocks.fetchSummary).toHaveBeenLastCalledWith('per-1', expect.anything())
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(text(wrapper, 'kpi-load-count')).toBe('10')
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
    const lama = summaryFixture({ load_count: 111 })
    const baru = summaryFixture({ load_count: 222 })

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

    expect(text(wrapper, 'kpi-load-count')).toBe('222')

    resolveLama?.(lama)
    await flushPromises()

    expect(text(wrapper, 'kpi-load-count')).toBe('222')
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
    expect(text(wrapper, 'kpi-load-count')).toBe('10')
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
    expect(text(wrapper, 'kpi-load-count')).toBe('10')
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
      'laporan-grading_periode-september-2026.csv',
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

    await selectPeriod(wrapper, 'per-2')

    resolveExport?.(new Blob(['a,b']))
    await flushPromises()

    // Isi berkas milik per-1, jadi namanya pun harus milik per-1.
    expect(repoMocks.saveCsvFile).toHaveBeenCalledWith(
      expect.any(Blob),
      'laporan-grading_periode-september-2026.csv',
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
  it('footer hanya Ekspor dan Back, dan seluruh pemanggilan repo adalah pembacaan', async () => {
    repoMocks.exportCsv.mockResolvedValue(new Blob(['a,b']))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    const footerButtons = wrapper.findAll('.action-footer button').map((b) => b.text())

    expect(footerButtons).toHaveLength(2)
    expect(footerButtons.join(' ')).toContain('Ekspor')
    expect(footerButtons.join(' ')).toContain('Back')

    expect(Object.keys(repoMocks).every((key) => /^fetch|^export|^save/.test(key))).toBe(true)
  })

  it('Back kembali ke Dashboard & Reporting', async () => {
    const wrapper = await mountView()
    await wrapper.get('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'dashboard-reporting' })
  })
})
