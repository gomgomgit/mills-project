/**
 * LaporanDepricarpingView.spec.ts — screen-151--laporan-depricarping-mobile /
 * usecase-152--laporan-depricarping-mobile.
 *
 * Satu test per test_scenarios[].component_test pada tech spec screen-151.
 * Kembaran kedelapan dari pola yang sama, dari LaporanSterilizerView.spec.ts
 * (screen-135) sampai LaporanGradingView.spec.ts (screen-147).
 *
 * YANG DI-MOCK: repo laporan, repo Production Line, router, dan ketiga store.
 * Yang diuji adalah KEPUTUSAN LAYAR atas jawaban repo — bentuk permintaan
 * HTTP-nya milik depricarpingReportRepo.spec.ts dan tidak diulang di sini.
 *
 * ── EMPAT HAL YANG PALING MUDAH RUSAK DI LAYAR INI ──────────────────────
 *
 * 1. PENYEBUT TIAP RATA-RATA WAJIB TERCETAK. Pada layar sempit kolom itu yang
 *    paling mudah dikorbankan demi ruang, dan tanpanya rata-rata sebuah
 *    parameter terbaca sebagai angka seluruh periode. Fixture memberi
 *    penyebut 2 dan 10 atas 10 slot terisi supaya kedua teksnya dapat dicari
 *    apa adanya.
 *
 * 2. CAKUPAN WAJIB BERADA DI ATAS KARTU PARAMETER, dan itu diasersi atas
 *    URUTAN DOM — bukan atas kehadiran keduanya. Periode yang terisi
 *    seperlima pun menghasilkan rata-rata yang terlihat rapi.
 *
 * 3. KARTU PARAMETER TANPA PEMBACAAN TETAP DIRENDER. Kartu yang hilang
 *    terbaca sebagai "tidak ada parameter ini", padahal yang benar adalah
 *    "tidak ada yang mengukurnya" — dan hanya yang kedua benar.
 *
 * 4. KETIADAAN PENANDAAN DI LUAR BATAS DIASERSI ATAS NAMA KELAS, bukan atas
 *    frasa: kalimat yang menyatakan ketiadaannya sendiri memuat frasa itu.
 *
 * BENTUK GALAT — DATAR, TANPA `response`. Ketiadaan `status` itulah yang
 * membedakan "jaringan putus" dari "server menjawab 401/403/422", dan urutan
 * cabang handleError bergantung padanya.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import LaporanDepricarpingView from '@/views/LaporanDepricarpingView.vue'

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

const { repoMocks } = vi.hoisted(() => ({
  repoMocks: {
    fetchBusinessUnits: vi.fn(),
    fetchPeriods: vi.fn(),
    fetchSummary: vi.fn(),
    exportCsv: vi.fn(),
    saveCsvFile: vi.fn(),
  },
}))

vi.mock('@/services/depricarpingReportRepo', () => ({
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
  station_type: 'depricarping',
  station_type_label: 'Depricarping',
}

const PERIOD_CLOSED = {
  id: 'per-2',
  name: 'Periode Agustus 2026',
  start_date: '2026-08-01',
  end_date: '2026-08-31',
  status: 'closed',
  station_type: 'depricarping',
  station_type_label: 'Depricarping',
}

const BUSINESS_UNITS = [
  { id: 'bu-1', name: 'Mill Utara' },
  { id: 'bu-2', name: 'Mill Selatan' },
]

const LINES = [
  { id: 'pl-1', name: 'Line Satu' },
  { id: 'pl-2', name: 'Line Dua' },
]

function metric(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    column: 'fan_static_pressure_mmh2o',
    label: 'Tekanan Statis Fan',
    unit: 'mmH2O',
    min: 25.5,
    avg: 30.25,
    max: 42,
    filled_slot_count: 2,
    target: {
      parameter_metric: 'Fan Static Pressure',
      target_range: '40 - 50 mmH2O',
      critical_limit: '< 35 or > 55 mmH2O',
      // KOLOM KEEMPAT, tidak ada pada master Threshing maupun Pressing.
      operational_consequence_justification:
        'Low pressure drops fibre early (heavy losses).',
      shares_standard_with: [],
      unmapped_reason: null,
    },
    ...overrides,
  }
}

/**
 * Fixture penuh. Penyebut 2 dan 10 atas 10 slot terisi dipilih supaya kedua
 * teks penyebut dapat dicari apa adanya.
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
    metrics: [
      metric(),
      metric({
        column: 'polishing_drum_speed_rpm',
        label: 'Putaran Polishing Drum',
        unit: 'RPM',
        min: 21,
        avg: 27.4,
        max: 30,
        filled_slot_count: 10,
        target: {
          parameter_metric: 'Polishing Drum Speed',
          target_range: '20 - 24 RPM',
          critical_limit: '< 18 or > 26 RPM',
          operational_consequence_justification:
            'Higher speeds cause premature mechanical wear.',
          shares_standard_with: [],
          unmapped_reason: null,
        },
      }),
      metric({
        column: 'air_velocity_ms',
        label: 'Kecepatan Udara Aspirator',
        unit: 'm/s',
        min: null,
        avg: null,
        max: null,
        filled_slot_count: 0,
        target: {
          parameter_metric: 'Air Velocity (Aspirator)',
          target_range: '12 - 14 m/s',
          critical_limit: '< 10 or > 16 m/s',
          operational_consequence_justification:
            'Controls the pneumatic separation gap.',
          shares_standard_with: [],
          unmapped_reason: null,
        },
      }),
      metric({
        column: 'fibre_moisture_percent',
        label: 'Kadar Air Fibre',
        unit: '%',
        min: 33,
        avg: 35,
        max: 37,
        filled_slot_count: 7,
        target: {
          parameter_metric: 'Fibre Moisture Content',
          target_range: '33% - 37%',
          critical_limit: '> 40%',
          operational_consequence_justification:
            'High moisture reduces downstream boiler combustion efficiency.',
          shares_standard_with: [],
          unmapped_reason: null,
        },
      }),
      // SENGAJA TANPA STANDAR: master menyebutnya "Kernel Loss in Fibre"
      // (sebuah KEHILANGAN) sementara kolom ini dan keempat layar input
      // Depricarping melabelinya PEROLEHAN.
      metric({
        column: 'kernel_recovery_in_fibre_percent',
        label: 'Kernel Recovery in Fibre',
        unit: '%',
        min: 0.3,
        avg: 0.45,
        max: 0.7,
        filled_slot_count: 1,
        target: {
          parameter_metric: null,
          target_range: null,
          critical_limit: null,
          operational_consequence_justification: null,
          shares_standard_with: [],
          unmapped_reason: 'direction_unresolved',
        },
      }),
      // SATU STANDAR, DUA KOLOM. Angkanya SENGAJA berbeda jauh: merata-ratakan
      // keduanya akan menyembunyikan silo yang menyimpang.
      metric({
        column: 'nut_silo_1_temp_c',
        label: 'Suhu Nut Silo 1',
        unit: 'C',
        min: 60,
        avg: 65,
        max: 70,
        filled_slot_count: 5,
        target: {
          parameter_metric: 'Nut Silo Temperature',
          target_range: '60C - 70C',
          critical_limit: '< 55C or > 75C',
          operational_consequence_justification: 'Crucial for nut conditioning.',
          shares_standard_with: ['nut_silo_2_temp_c'],
          unmapped_reason: null,
        },
      }),
      metric({
        column: 'nut_silo_2_temp_c',
        label: 'Suhu Nut Silo 2',
        unit: 'C',
        min: 80,
        avg: 85,
        max: 90,
        filled_slot_count: 3,
        target: {
          parameter_metric: 'Nut Silo Temperature',
          target_range: '60C - 70C',
          critical_limit: '< 55C or > 75C',
          operational_consequence_justification: 'Crucial for nut conditioning.',
          shares_standard_with: ['nut_silo_1_temp_c'],
          unmapped_reason: null,
        },
      }),
    ],
    // SATU entri, bukan dua seperti pada Pressing — dan ALASANNYA yang
    // membedakan jenisnya: di sini ada kolom yang namanya mirip tetapi arahnya
    // berlawanan, bukan "tidak ada kolomnya".
    targets_without_metric: [
      {
        parameter_metric: 'Kernel Loss in Fibre',
        target_range: '< 0.50%',
        critical_limit: '> 1.00%',
        operational_consequence_justification: 'Direct operational revenue loss.',
        reason: 'direction_unresolved',
      },
    ],
    all_targets_measured: false,
    targets_master_empty: false,
    by_presser: [
      {
        presser_id: 'PR-1',
        day_count: 2,
        filled_slot_count: 7,
        downtime_minutes: 30,
        averages: { fan_static_pressure_mmh2o: 30, polishing_drum_speed_rpm: null, air_velocity_ms: null },
      },
      {
        presser_id: 'PR-2',
        day_count: 1,
        filled_slot_count: 3,
        // null, BUKAN 0: presser yang tidak dicatat bukan presser yang tidak
        // pernah berhenti.
        downtime_minutes: null,
        averages: { fan_static_pressure_mmh2o: 28.5, polishing_drum_speed_rpm: 22, air_velocity_ms: null },
      },
    ],
    daily: [
      {
        date: '2026-09-04',
        filled_slot_count: 6,
        downtime_minutes: 30,
        averages: { fan_static_pressure_mmh2o: 30, polishing_drum_speed_rpm: null, air_velocity_ms: null },
      },
      {
        date: '2026-09-05',
        filled_slot_count: 4,
        downtime_minutes: null,
        averages: { fan_static_pressure_mmh2o: 28, polishing_drum_speed_rpm: 22, air_velocity_ms: null },
      },
    ],
    daily_total: {
      filled_slot_count: 10,
      downtime_minutes: 30,
      averages: { fan_static_pressure_mmh2o: 29.2, polishing_drum_speed_rpm: 22, air_velocity_ms: null },
    },
    // OBJEK berisi ANGKA, bukan daftar alasan teks seperti pada payload
    // Threshing/Pressing. Penyebutnya 3 slot pencatat dari 10 slot terisi —
    // sengaja BERBEDA supaya penyebut yang salah terlihat.
    downtime: {
      total_minutes: 30,
      recorded_slot_count: 3,
      avg_minutes_per_recorded_slot: 10,
      has_standard: false,
    },
    findings: [
      { finding: 'Belt kendur', slot_count: 3 },
      { finding: 'belt kendur', slot_count: 2 },
    ],
    total: {
      record_count: 12,
      days_with_records: 2,
      draft_record_count: 4,
      records_not_checked: 5,
      records_not_acknowledged: 6,
    },
    ...overrides,
  }
}

/** Periode kosong: nol slot terisi, seluruh angka null. */
function emptySummaryFixture(): Record<string, unknown> {
  return summaryFixture({
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
    metrics: [
      metric({ min: null, avg: null, max: null, filled_slot_count: 0 }),
    ],
    by_presser: [],
    daily: [],
    daily_total: { filled_slot_count: 0, downtime_minutes: null, averages: {} },
    downtime: {
      total_minutes: null,
      recorded_slot_count: 0,
      avg_minutes_per_recorded_slot: null,
      has_standard: false,
    },
    findings: [],
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
  const wrapper = mount(LaporanDepricarpingView)
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

    for (const testid of [
      'coverage', 'coverage-slots', 'coverage-denominator', 'coverage-days',
      'metrics', 'no-flagging-note', 'targets-without-metric',
      'by-presser', 'downtime', 'completeness', 'daily-recap',
    ]) {
      expect(exists(wrapper, testid), testid).toBe(true)
    }

    // Tidak ada satu pun kontrol tulis.
    const buttonLabels = wrapper.findAll('button').map((button) => button.text().toLowerCase())

    expect(buttonLabels.some((label) => /simpan|ubah|hapus|verifik/.test(label))).toBe(false)
  })

  it.each(['supervisor', 'mill_management'] as const)(
    '%s: perilakunya identik dengan Operator — tanpa pemilih mill, tanpa memanggil daftar mill',
    async (role) => {
      asRole(role, 'bu-1')

      const wrapper = await mountView()
      await selectPeriod(wrapper, 'per-1')

      expect(exists(wrapper, 'mill-select')).toBe(false)
      expect(text(wrapper, 'mill-current')).toContain('Mill Utara')
      expect(exists(wrapper, 'metrics')).toBe(true)
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

    expect(exists(wrapper, 'metrics')).toBe(true)
  })

  it('nama mill dibaca dari blok business_unit, BUKAN dari period.business_unit_name', async () => {
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
/* Cakupan — dibaca lebih dulu                                         */
/* ================================================================== */

describe('cakupan pencatatan', () => {
  it('blok cakupan berada DI ATAS kartu parameter pada urutan DOM', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const html = wrapper.html()

    // Diasersi atas URUTAN, bukan atas kehadiran: periode yang terisi
    // seperlima pun menghasilkan rata-rata yang terlihat rapi, dan pembaca
    // harus melihat cakupannya lebih dulu.
    expect(html.indexOf('data-testid="coverage"'))
      .toBeLessThan(html.indexOf('data-testid="metrics"'))
  })

  it('mencetak ketiga angka pembentuk penyebutnya, bukan hanya persennya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const denominator = text(wrapper, 'coverage-denominator')

    expect(denominator).toContain('2 presser')
    expect(denominator).toContain('10 hari')
    expect(denominator).toContain('24 slot')
    expect(text(wrapper, 'coverage-slots')).toContain('10 dari 480 slot')
    expect(text(wrapper, 'coverage-percent')).toContain('2,1%')
  })

  it('periode berjalan: keterangan penyebut berhenti di hari ini', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'coverage-running-note')).toContain('sampai hari ini')
    expect(exists(wrapper, 'coverage-not-started-note')).toBe(false)
  })

  it('periode belum mulai: persen "—" dan keterangan belum mulai MESKI period_running true', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          coverage_percent: null,
          presser_count: 0,
          slots_per_presser_per_day: 24,
          days_in_period: 30,
          days_counted: 0,
          // true di server juga untuk periode yang BELUM MULAI — inilah
          // sebabnya days_counted harus diperiksa lebih dulu.
          period_running: true,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'coverage-percent')).toBe('—')
    expect(exists(wrapper, 'coverage-not-started-note')).toBe(true)
    expect(exists(wrapper, 'coverage-running-note')).toBe(false)
  })

  it('cakupan TETAP dirender untuk periode kosong — justru itu yang menjelaskannya', async () => {
    repoMocks.fetchSummary.mockResolvedValue(emptySummaryFixture())

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'empty-period')).toBe(true)
    expect(exists(wrapper, 'coverage')).toBe(true)
    expect(text(wrapper, 'coverage-slots')).toContain('0 dari 480 slot')
  })
})

/* ================================================================== */
/* Kartu parameter & penyebutnya                                       */
/* ================================================================== */

describe('kartu parameter', () => {
  it('mencetak penyebut tiap kartu apa adanya, dan penyebutnya memang berbeda', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const cards = wrapper.findAll('[data-testid="metric-denominator"]')

    // TUJUH kartu, bukan lima seperti pada Threshing dan Pressing mobile.
    expect(cards).toHaveLength(7)

    const texts = cards.map((cell) => cell.text())

    // Penyebut yang BERBEDA-BEDA pada layar yang sama — itulah yang membuat
    // asersi ini bermakna alih-alih selalu hijau. Dengan penyebut seragam,
    // satu penyebut bersama akan lolos tanpa terlihat.
    expect(texts[0]).toContain('2 dari 10 slot')
    expect(texts[1]).toContain('10 dari 10 slot')
    expect(texts[2]).toContain('0 dari 10 slot')
    expect(texts[3]).toContain('7 dari 10 slot')
    expect(texts[4]).toContain('1 dari 10 slot')
    expect(texts[5]).toContain('5 dari 10 slot')
    expect(texts[6]).toContain('3 dari 10 slot')

    // Dan ketujuhnya memang tidak seragam.
    expect(new Set(texts).size).toBe(7)
  })

  it('kartu tanpa satu pun pembacaan TETAP dirender, dengan standarnya tetap tampil', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const cards = wrapper.findAll('[data-testid="metric-card"]')

    expect(cards).toHaveLength(7)

    const aspiratorCard = cards[2].text()

    // Kartu yang hilang terbaca sebagai "tidak ada parameter ini", padahal
    // yang benar adalah "tidak ada yang mengukurnya".
    expect(aspiratorCard).toContain('Kecepatan Udara Aspirator')
    expect(aspiratorCard).toContain('tidak tersedia')
    expect(aspiratorCard).toContain('12 - 14 m/s')
    // Dan KETIGA kolom target tetap tampil, termasuk yang keempat pada master.
    expect(aspiratorCard).toContain('< 10 or > 16 m/s')
    expect(aspiratorCard).toContain('Controls the pneumatic separation gap.')
  })

  it('min, rata-rata, dan maks ketiganya dirender pada kartu yang sama', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const firstCard = wrapper.findAll('[data-testid="metric-card"]')[0].text()

    expect(firstCard).toContain('25,50')
    expect(firstCard).toContain('30,25')
    expect(firstCard).toContain('42,00')
  })

  it('standar dan rencana tindakan berada di dalam kartu angkanya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const drumCard = wrapper.findAll('[data-testid="metric-card"]')[1]

    // Menaruh standarnya di bagian terpisah akan membuang satu-satunya
    // keunggulan yang diberikan master target. KETIGA kolom target pada kartu
    // yang SAMA — dan yang ketiga itulah yang paling berisiko dibuang demi
    // ruang, karena teksnya paling panjang.
    expect(drumCard.text()).toContain('27,40')
    expect(drumCard.text()).toContain('20 - 24 RPM')
    expect(drumCard.text()).toContain('< 18 or > 26 RPM')
    expect(drumCard.text()).toContain('Higher speeds cause premature mechanical wear.')
    expect(drumCard.find('[data-testid="metric-consequence"]').exists()).toBe(true)
  })

  it('kedua kartu nut silo menyatakan standarnya SATU, dan angkanya tidak dirata-ratakan', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const shared = wrapper.findAll('[data-testid="metric-shared-standard"]')

    // DUA keterangan, satu per kartu silo. Di layar sempit kedua kartu itu
    // bertumpuk LANGSUNG berurutan, sehingga standar '60C - 70C' yang identik
    // muncul dua kali tepat beruntun — tanpa keterangan ini ia terbaca sebagai
    // data terduplikasi lebih kuat lagi daripada di tabel web.
    expect(shared).toHaveLength(2)
    expect(shared[0].text()).toContain('Suhu Nut Silo 2')
    expect(shared[1].text()).toContain('Suhu Nut Silo 1')

    for (const note of shared) {
      expect(note.text()).toContain('Nut Silo Temperature')
      expect(note.text()).toContain('Angkanya tetap dipisah karena keduanya silo yang berbeda')
    }

    // Dan angkanya TIDAK dirata-ratakan: 75,00 tidak boleh muncul sama sekali.
    const cards = wrapper.findAll('[data-testid="metric-card"]')

    expect(cards[5].text()).toContain('65,00')
    expect(cards[6].text()).toContain('85,00')
    expect(wrapper.text()).not.toContain('75,00')
  })

  it('kartu kernel recovery menyatakan standarnya TIDAK dipasangkan, beserta sebabnya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const kernelCard = wrapper.findAll('[data-testid="metric-card"]')[4]

    // ANGKANYA TETAP ADA, di bawah label kolomnya sendiri.
    expect(kernelCard.text()).toContain('Kernel Recovery in Fibre')
    expect(kernelCard.text()).toContain('0,45')

    // Dan keterangan bahwa standarnya tidak dipasangkan — BUKAN sel target
    // kosong, yang terbaca seperti master yang belum terisi.
    expect(kernelCard.find('[data-testid="metric-unmapped"]').exists()).toBe(true)
    expect(kernelCard.text()).toContain('tidak dipasangkan')
    expect(kernelCard.text()).toContain('kolom ini')

    // Ketiga kolom target TIDAK dirender pada kartu ini.
    expect(kernelCard.find('[data-testid="metric-target-range"]').exists()).toBe(false)
    expect(kernelCard.find('[data-testid="metric-critical-limit"]').exists()).toBe(false)
  })

  it('standar yang null dirender sebagai keterangan, bukan sel kosong', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        metrics: [
          metric({
            target: {
              parameter_metric: null,
              target_range: null,
              critical_limit: null,
              operational_consequence_justification: null,
              shares_standard_with: [],
              unmapped_reason: null,
            },
          }),
        ],
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'metric-target-range')).toContain('rentang target belum terisi pada master')
    expect(text(wrapper, 'metric-critical-limit')).toContain('belum terisi')
    expect(text(wrapper, 'metric-consequence')).toContain('belum terisi')
    // Dan keterangan "tidak dipasangkan" TIDAK muncul: master yang belum
    // terisi adalah hal yang BERBEDA dari standar yang sengaja tidak
    // dipasangkan.
    expect(exists(wrapper, 'metric-unmapped')).toBe(false)
  })
})

/* ================================================================== */
/* Ketiadaan penandaan di luar batas                                   */
/* ================================================================== */

describe('tidak ada penandaan di luar batas', () => {
  it('tidak ada satu pun kelas penanda pada hasil render', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const html = wrapper.html()

    // Rata-rata drum 27,4 jelas di luar '21 - 23 RPM', dan tetap tidak
    // ditandai. DIASERSI ATAS NAMA KELAS, bukan atas frasa: kalimat yang
    // menyatakan ketiadaan penandaan sendiri memuat frasa "di luar batas".
    for (const className of ['md-threshold', 'is-danger', 'is-warning', 'md-chip--danger']) {
      expect(html, className).not.toContain(className)
    }
  })

  it('ketiadaannya DINYATAKAN, berikut sebabnya dan cakupan standarnya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const note = text(wrapper, 'no-flagging-note')

    expect(note).toContain('tidak menilai satu angka pun')
    expect(note).toContain('teks bebas')
    expect(note).toContain('berhenti memperingatkan')
    expect(note).toContain('berlaku umum untuk seluruh mill')
    expect(note).toContain('satu penyebut bersama akan salah')
  })
})

/* ================================================================== */
/* Target tanpa pengukuran                                             */
/* ================================================================== */

describe('standar yang belum diukur sistem', () => {
  it('dirender pada bagiannya sendiri beserta keterangannya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // SATU entri, bukan dua seperti pada Pressing — dan alasannya BUKAN
    // "tidak ada kolomnya".
    expect(text(wrapper, 'targets-without-metric')).toContain('Kernel Loss in Fibre')
    expect(wrapper.findAll('[data-testid="targets-without-metric-row"]')).toHaveLength(1)
    expect(text(wrapper, 'targets-without-metric')).toContain('< 0.50%')
    expect(text(wrapper, 'targets-without-metric')).toContain('> 1.00%')
    // KEEMPAT kolom master ikut, termasuk justifikasinya.
    expect(text(wrapper, 'targets-without-metric')).toContain('Direct operational revenue loss.')
    // ALASANNYA tercetak, bukan hanya nama parameternya.
    expect(exists(wrapper, 'targets-without-metric-reason')).toBe(true)
    expect(text(wrapper, 'targets-without-metric-reason')).toContain('arahnya belum pasti')
    expect(text(wrapper, 'targets-without-metric-note')).toContain('berlawanan arah')
    expect(exists(wrapper, 'targets-master-empty')).toBe(false)
  })

  it('bagian ini TETAP digambar walau kosong, dengan keterangannya sendiri', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({ targets_without_metric: [], all_targets_measured: true }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Bagian yang hilang ketika kosong tidak dapat dibedakan dari bagian yang
    // belum pernah dibuat — dan di layar ini bagian itulah yang memuat temuan
    // paling perlu dibaca manusia.
    expect(exists(wrapper, 'targets-without-metric')).toBe(true)
    expect(exists(wrapper, 'targets-all-measured')).toBe(true)
    expect(exists(wrapper, 'targets-without-metric-row')).toBe(false)
  })

  it('master kosong: keterangan terender dan angka ukur TETAP tampil', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({ targets_master_empty: true, targets_without_metric: [] }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'targets-master-empty')).toBe(true)
    expect(exists(wrapper, 'targets-without-metric')).toBe(false)
    // Master yang belum diisi tidak menghapus pengukuran yang sudah terjadi.
    expect(exists(wrapper, 'metrics')).toBe(true)
    expect(text(wrapper, 'metrics')).toContain('30,25')
  })

  it('master kosong: bagian standar-tanpa-pengukuran sengaja TIDAK digambar', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        targets_without_metric: [],
        all_targets_measured: true,
        targets_master_empty: true,
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // PERILAKU YANG SENGAJA BERBEDA dari laporan Pressing mobile, dan ini
    // satu-satunya keadaan di mana bagian itu hilang. Daftar kosong karena
    // master belum terisi adalah hal yang BERLAWANAN dari daftar kosong karena
    // seluruh standar sudah terukur — mencampurnya akan membuat master yang
    // kosong terbaca sebagai "tidak ada yang tertinggal". Jadi cabang
    // "master kosong" menang, dan bagian yang satu lagi tidak digambar.
    expect(exists(wrapper, 'targets-master-empty')).toBe(true)
    expect(exists(wrapper, 'targets-without-metric')).toBe(false)
    expect(exists(wrapper, 'targets-all-measured')).toBe(false)
    // Angka ukur TETAP tampil — master yang belum diisi tidak menghapus
    // pengukuran yang sudah terjadi.
    expect(exists(wrapper, 'metrics')).toBe(true)
  })
})

/* ================================================================== */
/* Rekap per presser, downtime, kelengkapan                           */
/* ================================================================== */

describe('rekap dan kelengkapan', () => {
  it('rekap per presser menampilkan jumlah hari dan slotnya sendiri', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const rows = wrapper.findAll('[data-testid="by-presser-row"]')

    expect(rows).toHaveLength(2)
    expect(rows[0].text()).toContain('PR-1')
    expect(rows[1].text()).toContain('PR-2')
  })

  it('dua ejaan alasan downtime terender sebagai dua baris, dengan keterangan harfiah', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const rows = wrapper.findAll('[data-testid="findings-row"]')

    expect(rows).toHaveLength(2)
    expect(rows[0].text()).toContain('Belt kendur')
    expect(rows[1].text()).toContain('belt kendur')
    // Tanpa keterangan ini, dua baris mirip terbaca sebagai cacat laporan.
    expect(text(wrapper, 'findings-note')).toContain('harfiah')
    // Dan bagian temuan TERPISAH dari blok downtime: satu menjawab "berapa
    // lama", satu "apa yang terlihat".
    expect(text(wrapper, 'findings-note')).toContain('tidak digabungkan')
    expect(exists(wrapper, 'downtime')).toBe(true)
    expect(exists(wrapper, 'findings')).toBe(true)
  })

  it('blok downtime menerbitkan angka dengan penyebut slot pencatatnya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'downtime-total')).toBe(true)
    expect(text(wrapper, 'downtime-total')).toContain('30')
    // PENYEBUTNYA 3 slot pencatat, BUKAN 10 slot terisi.
    expect(text(wrapper, 'downtime-recorded-slots')).toContain('3')
    expect(text(wrapper, 'downtime-recorded-slots')).toContain('dari 10 slot terisi')
    expect(text(wrapper, 'downtime-average')).toContain('10,0')
    expect(text(wrapper, 'downtime-note')).toContain('tidak dihitung sebagai nol')
    expect(text(wrapper, 'downtime-note')).toContain('tidak punya baris pada master target')
  })

  it('tanpa satu pun slot pencatat: blok downtime menyatakan ketiadaan, bukan 0 menit', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        downtime: {
          total_minutes: null,
          recorded_slot_count: 0,
          avg_minutes_per_recorded_slot: null,
          has_standard: false,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'downtime-empty')).toBe(true)
    expect(exists(wrapper, 'downtime-total')).toBe(false)
    // Total 0 menit terbaca seperti "stasiun tidak pernah berhenti", padahal
    // yang benar adalah "tidak ada yang mencatatnya".
    expect(text(wrapper, 'downtime-empty')).toContain('Belum ada satu slot pun')
    // Keterangan cara-hitung TETAP terender: ia menjelaskan cara hitungnya,
    // bukan hasilnya.
    expect(text(wrapper, 'downtime-note')).toContain('tidak dihitung sebagai nol')
  })

  it('tanpa alasan downtime: keterangan terender, bukan tabel kosong', async () => {
    repoMocks.fetchSummary.mockResolvedValue(summaryFixture({ findings: [] }))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'findings-empty')).toBe(true)
    expect(exists(wrapper, 'findings-table')).toBe(false)
  })

  it('kelima penghitung kelengkapan terender, dengan keterangan draft ikut terhitung', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'record-count')).toBe('12')
    expect(text(wrapper, 'days-with-records')).toBe('2')
    expect(text(wrapper, 'draft-record-count')).toBe('4')
    expect(text(wrapper, 'records-not-checked')).toBe('5')
    expect(text(wrapper, 'records-not-acknowledged')).toBe('6')
    expect(text(wrapper, 'draft-note')).toContain('IKUT terhitung')
    expect(text(wrapper, 'draft-note')).toContain('bukan penyaring')
  })
})

/* ================================================================== */
/* Rekap harian                                                        */
/* ================================================================== */

describe('rekap harian', () => {
  it('menampilkan baris harian dan baris total periode', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(wrapper.findAll('[data-testid="daily-row"]')).toHaveLength(2)
    expect(exists(wrapper, 'daily-recap-total')).toBe(true)
    // Total periode DIHITUNG ULANG server — 29,2 bukan rata-rata dari 30
    // dan 28 dengan cara apa pun yang sederhana.
    expect(text(wrapper, 'daily-recap-total')).toContain('29,20')
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

    expect(exists(wrapper, 'laporan-depricarping-mobile')).toBe(true)
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

    const wrapper = mount(LaporanDepricarpingView)
    await flushPromises()

    // SELAMA memuat: indikator muat, BUKAN "belum punya periode".
    expect(exists(wrapper, 'periods-loading')).toBe(true)
    expect(exists(wrapper, 'no-periods')).toBe(false)

    resolvePeriods?.([])
    await flushPromises()

    expect(exists(wrapper, 'no-periods')).toBe(true)
    expect(exists(wrapper, 'periods-loading')).toBe(false)
  })

  it('periode tanpa data: keterangan tampil, rekap tidak digambar, cakupan tetap ada', async () => {
    repoMocks.fetchSummary.mockResolvedValue(emptySummaryFixture())

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'empty-period')).toBe(true)
    expect(exists(wrapper, 'coverage')).toBe(true)
    // Kartu parameter TETAP dirender dengan angka tidak tersedia.
    expect(exists(wrapper, 'metrics')).toBe(true)
    expect(text(wrapper, 'metrics')).toContain('tidak tersedia')
    // Rekap tidak digambar — tabel kosong terbaca sebagai hasil pengukuran
    // bernilai nol.
    expect(exists(wrapper, 'by-presser')).toBe(false)
    expect(exists(wrapper, 'downtime')).toBe(false)
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
    expect(exists(wrapper, 'metrics')).toBe(true)
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
    const lama = summaryFixture({ metrics: [metric({ avg: 111 })] })
    const baru = summaryFixture({ metrics: [metric({ avg: 222 })] })

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

    expect(text(wrapper, 'metrics')).toContain('222,00')

    resolveLama?.(lama)
    await flushPromises()

    expect(text(wrapper, 'metrics')).toContain('222,00')
    expect(text(wrapper, 'metrics')).not.toContain('111,00')
  })

  it('galat permintaan lama tidak memunculkan pesan basi', async () => {
    let rejectLama: ((finding: unknown) => void) | null = null

    repoMocks.fetchSummary
      .mockImplementationOnce(
        () =>
          new Promise((_resolve, reject) => {
            rejectLama = reject as (finding: unknown) => void
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
    expect(exists(wrapper, 'metrics')).toBe(true)
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
    expect(exists(wrapper, 'metrics')).toBe(true)
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
      'laporan-depricarping_periode-september-2026.csv',
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
      'laporan-depricarping_periode-september-2026.csv',
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
