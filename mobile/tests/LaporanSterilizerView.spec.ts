/**
 * LaporanSterilizerView.spec.ts — screen-135--laporan-sterilizer-mobile /
 * usecase-135--laporan-sterilizer-mobile "Lihat Laporan Periode Sterilizer
 * (Mobile)".
 *
 * Mencakup SELURUH 21 unit_test_cases dan SELURUH 18 component_test dari
 * tech spec screen-135. Beberapa test sengaja memenuhi lebih dari satu
 * butir sekaligus (dicatat pada komentarnya), konvensi yang sama dengan
 * DashboardReportingView.spec.ts / StationListView.spec.ts.
 *
 * STRATEGI MOCK — yang di-mock adalah apiClient, BUKAN repo.
 *
 * Berbeda dengan DataPreviewSterilizerView.spec.ts (yang men-stub seluruh
 * repo-nya), berkas ini hanya men-stub '@/services/apiClient' dan
 * membiarkan src/services/sterilizerReportRepo.ts berjalan SUNGGUHAN.
 * Tiga alasan, semuanya berasal dari sifat layar ini:
 *
 *   1. Sebagian besar unit_test_cases menyebut apiClient secara harfiah
 *      ("apiClient.get tidak pernah dipanggil", "query yang dikirim tidak
 *      memuat business_unit_id"). Asersi itu hanya bermakna bila lapisan
 *      yang menyusun query — scopeParams() di repo — ikut dieksekusi.
 *      Men-stub repo akan memindahkan asersi ke mock buatan test sendiri
 *      dan membuktikan nol hal tentang permintaan yang benar-benar dikirim.
 *
 *   2. Klaim utama layar ini adalah NOL PERHITUNGAN ULANG DI KLIEN. Yang
 *      menjaganya adalah rantai respons -> repo -> view model -> DOM secara
 *      utuh; memotong rantai itu di tengah berarti membiarkan
 *      normalizeKpi()/unwrap() tidak teruji justru pada berkas yang
 *      mengaku menjaganya.
 *
 *   3. Bentuk respons NYATA berbeda dari bentuk naif: blok `kpi` memakai
 *      avg_duration_minutes/min_duration_minutes/max_duration_minutes
 *      sedangkan `daily`/`by_unit`/`total` memakai nama tanpa sufiks; dan
 *      `periods`/`business-units/options` dibungkus { data: [...] }
 *      sementara `summary` TIDAK dibungkus sama sekali. Fixture di bawah
 *      memakai bentuk nyata itu — kalau repo di-stub, perbedaan ini
 *      (sumber bug yang paling mudah terjadi di layar ini) tidak akan
 *      pernah tersentuh test.
 *
 * ANGKA FIXTURE SENGAJA TIDAK KONSISTEN SECARA ARITMETIKA. kpi.total_cycles
 * (1234), jumlah baris daily (12 + 18 = 30), dan total.cycles (999) dibuat
 * bertentangan satu sama lain. Layar yang benar menampilkan ketiganya apa
 * adanya; layar yang diam-diam menghitung ulang akan gagal di sini. JANGAN
 * "merapikan" angka-angka ini.
 */
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import LaporanSterilizerView from '@/views/LaporanSterilizerView.vue'
import sterilizerReportRepo from '@/services/sterilizerReportRepo'

/* ------------------------------------------------------------------ */
/* Mock modul                                                          */
/* ------------------------------------------------------------------ */

const { pushMock } = vi.hoisted(() => ({ pushMock: vi.fn() }))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: () => ({ params: {}, query: {} }),
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

/**
 * Satu-satunya lapisan yang di-stub. sterilizerReportRepo.ts di atasnya
 * berjalan sungguhan — lihat catatan "STRATEGI MOCK" pada docblock berkas.
 */
const { apiGetMock } = vi.hoisted(() => ({ apiGetMock: vi.fn() }))

vi.mock('@/services/apiClient', () => ({
  default: { get: apiGetMock },
}))

/* ------------------------------------------------------------------ */
/* Stub respons per endpoint                                           */
/* ------------------------------------------------------------------ */

type Stub = { kind: 'ok'; payload: unknown } | { kind: 'fail'; error: unknown }

const ok = (payload: unknown): Stub => ({ kind: 'ok', payload })
const fail = (error: unknown): Stub => ({ kind: 'fail', error })

/**
 * Bentuk galat yang dipakai di seluruh berkas ini adalah bentuk TERNORMALISASI
 * milik apiClient (`{ message, errors?, status? }`, lihat normalizeError di
 * src/services/apiClient.ts) — bukan AxiosError mentah. Itulah yang sungguh
 * diterima repo dan view di produksi: kegagalan jaringan tiba TANPA field
 * `status` sama sekali, dan justru ketiadaan status itulah yang membedakan
 * "jaringan putus" dari "server menjawab 401/403/422".
 */
const NETWORK_ERROR = { message: 'Tidak dapat terhubung ke server. Data akan disimpan secara lokal.' }
const UNAUTHENTICATED_ERROR = { message: 'Unauthenticated.', status: 401 }
const FORBIDDEN_ERROR = { message: 'This action is unauthorized.', status: 403 }
const VALIDATION_ERROR = { message: 'Mill wajib dipilih.', status: 422 }

interface ApiStubs {
  units?: Stub
  periods?: Stub
  summary?: Stub
  export?: Stub
}

const currentStubs: ApiStubs = {}

function stubApi(stubs: ApiStubs): void {
  Object.assign(currentStubs, stubs)
}

function settle(stub: Stub | undefined, fallbackPayload: unknown): Promise<{ data: unknown }> {
  if (stub && stub.kind === 'fail') {
    return Promise.reject(stub.error)
  }

  return Promise.resolve({ data: stub ? stub.payload : fallbackPayload })
}

/** Seluruh panggilan apiClient.get untuk satu path (untuk hitung pemanggilan). */
function callsTo(path: string): unknown[][] {
  return apiGetMock.mock.calls.filter((call) => String(call[0]).includes(path))
}

/* ------------------------------------------------------------------ */
/* Fixture — BENTUK RESPONS NYATA (lihat docblock)                     */
/* ------------------------------------------------------------------ */

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

/**
 * Blok `kpi` memakai sufiks _minutes; `daily`/`by_unit`/`total` tidak.
 * Itu bentuk yang benar-benar dikirim SterilizerReportService.
 */
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

/* ------------------------------------------------------------------ */
/* Auth store palsu                                                    */
/* ------------------------------------------------------------------ */

function asRole(
  role: 'operator' | 'supervisor' | 'mill_management' | 'admin',
  businessUnitId: string | null,
): void {
  useAuthStoreMock.mockReturnValue({
    currentUser: {
      id: 'user-1',
      username: role === 'admin' ? 'admin' : 'operator01',
      name: 'Pengguna Uji',
      role,
      business_unit_id: businessUnitId,
    },
    businessUnit: businessUnitId ? { id: businessUnitId, name: 'Business Unit A' } : null,
    logout: logoutMock,
  })
}

/* ------------------------------------------------------------------ */
/* Bantuan mount / interaksi                                           */
/* ------------------------------------------------------------------ */

async function mountView(): Promise<VueWrapper> {
  const wrapper = mount(LaporanSterilizerView)
  await flushPromises()

  return wrapper
}

async function selectPeriod(wrapper: VueWrapper, periodId: string): Promise<void> {
  await wrapper.get('[data-testid="period-select"]').setValue(periodId)
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

function selectValue(wrapper: VueWrapper, testid: string): string {
  return (wrapper.get(`[data-testid="${testid}"]`).element as HTMLSelectElement).value
}

/* ------------------------------------------------------------------ */

const { createObjectURLMock, revokeObjectURLMock } = vi.hoisted(() => ({
  createObjectURLMock: vi.fn(),
  revokeObjectURLMock: vi.fn(),
}))

beforeEach(() => {
  vi.clearAllMocks()
  // Aturan repo: setiap test yang dapat menyentuh localStorage wajib
  // membersihkannya. apiClient/tokenStorage memang di-mock di sini, tetapi
  // jsdom membagi satu objek localStorage untuk seluruh berkas — sisa dari
  // berkas/test lain tidak boleh bocor ke sini.
  window.localStorage.clear()

  asRole('operator', 'bu-1')

  // Stub bawaan: mill terisi, satu periode Sterilizer, ringkasan lengkap.
  currentStubs.units = ok({ data: BUSINESS_UNITS })
  currentStubs.periods = ok({ data: [PERIOD_STER] })
  currentStubs.summary = ok(makeSummary())
  currentStubs.export = ok(new Blob(['csv'], { type: 'text/csv' }))

  apiGetMock.mockImplementation((url: string) => {
    if (String(url).includes('/business-units/options')) {
      return settle(currentStubs.units, { data: [] })
    }

    if (String(url).includes('/periods')) {
      return settle(currentStubs.periods, { data: [] })
    }

    if (String(url).includes('/summary')) {
      return settle(currentStubs.summary, makeSummary())
    }

    if (String(url).includes('/export')) {
      return settle(currentStubs.export, new Blob([]))
    }

    return Promise.reject(new Error(`Endpoint tak terduga: ${url}`))
  })

  // saveCsvFile() memakai object URL + anchor. jsdom tidak punya
  // URL.createObjectURL, dan klik anchur sungguhan memicu "Not implemented:
  // navigation" — keduanya distub agar yang teruji adalah PEMANGGILANNYA.
  createObjectURLMock.mockReturnValue('blob:mock-url')
  ;(URL as unknown as { createObjectURL: (blob: Blob) => string }).createObjectURL = createObjectURLMock
  ;(URL as unknown as { revokeObjectURL: (url: string) => void }).revokeObjectURL = revokeObjectURLMock
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
})

afterEach(() => {
  vi.restoreAllMocks()
})

/* ================================================================== */
/* 21 unit_test_cases                                                  */
/* ================================================================== */

describe('LaporanSterilizerView — unit_test_cases (tech spec screen-135)', () => {
  // unit_test_case 1
  it('memanggil endpoint options dan menunda pemuatan periode ketika peran Admin', async () => {
    asRole('admin', null)
    stubApi({ units: ok({ data: [{ id: 'bu-1', name: 'Mill Utara' }] }) })

    const wrapper = await mountView()

    expect(callsTo('/business-units/options')).toHaveLength(1)
    expect(callsTo('/api/sterilizer-reports/periods')).toHaveLength(0)
    expect(wrapper.findAll('[data-testid="mill-select"] option')).toHaveLength(2)
  })

  // unit_test_case 2
  it('tidak memanggil periods maupun summary selama Admin belum memilih mill', async () => {
    asRole('admin', null)

    const wrapper = await mountView()

    expect(callsTo('/api/sterilizer-reports/periods')).toHaveLength(0)
    expect(callsTo('/api/sterilizer-reports/summary')).toHaveLength(0)
    // millRequired=true diwujudkan sebagai mill-required-hint.
    expect(exists(wrapper, 'mill-required-hint')).toBe(true)
    expect(exists(wrapper, 'kpi-total-cycles')).toBe(false)
  })

  // unit_test_case 3
  it('melewati endpoint options dan langsung memuat periode ketika peran bukan Admin', async () => {
    asRole('operator', 'bu-7')

    await mountView()

    expect(callsTo('/business-units/options')).toHaveLength(0)
    expect(callsTo('/api/sterilizer-reports/periods')).toHaveLength(1)
    // Tanpa business_unit_id — mill diambil server dari akun.
    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/periods', { params: {} })
  })

  // unit_test_case 4
  it('menampilkan pesan hubungi Admin tanpa memanggil endpoint apa pun ketika akun terikat mill namun business_unit_id kosong', async () => {
    asRole('supervisor', null)

    const wrapper = await mountView()

    expect(apiGetMock).not.toHaveBeenCalled()
    expect(apiGetMock).toHaveBeenCalledTimes(0)
    expect(exists(wrapper, 'no-mill-for-account')).toBe(true)
    expect(text(wrapper, 'no-mill-for-account')).toContain('hubungi Admin')
    // Daftar mill tetap kosong: sistem tidak menawarkan seluruh mill.
    expect(exists(wrapper, 'mill-select')).toBe(false)
  })

  // unit_test_case 5 — diuji pada repo secara langsung, karena di sinilah
  // business_unit_id yang dipaksakan pemanggil ditolak (scopeParams).
  it('mengabaikan business_unit_id yang dipaksakan klien ketika peran bukan Admin', async () => {
    const summary = await sterilizerReportRepo.fetchSummary('per-1', {
      isAdmin: false,
      businessUnitId: 'bu-99',
    })

    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/summary', {
      params: { period_id: 'per-1' },
    })

    const [, config] = callsTo('/api/sterilizer-reports/summary')[0] as [string, { params: Record<string, unknown> }]
    expect(config.params).not.toHaveProperty('business_unit_id')
    expect(JSON.stringify(config.params)).not.toContain('bu-99')

    // Respons 200 tetap dipetakan (bukan galat 403).
    expect(summary.kpi.total_cycles).toBe(1234)

    await sterilizerReportRepo.fetchPeriods({ isAdmin: false, businessUnitId: 'bu-99' })
    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/periods', { params: {} })
  })

  // unit_test_case 6
  it('memetakan daftar periode kosong menjadi state arahan menghubungi Admin', async () => {
    stubApi({ periods: ok({ data: [] }) })

    const wrapper = await mountView()

    expect(wrapper.findAll('[data-testid="period-select"] option')).toHaveLength(1) // hanya placeholder
    expect(exists(wrapper, 'no-periods')).toBe(true)
    expect(text(wrapper, 'no-periods')).toContain('hubungi Admin')
    // Bukan galat teknis.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)
  })

  // unit_test_case 7
  it('memetakan periode beserta label jenis stasiun dan status', async () => {
    stubApi({ periods: ok({ data: [PERIOD_STER, PERIOD_LINTAS_STASIUN] }) })

    const wrapper = await mountView()

    const options = wrapper.findAll('[data-testid="period-select"] option')
    expect(options).toHaveLength(3)
    expect(options[1].text()).toBe('Sterilizer — Periode Agustus 2026')
    expect(options[2].text()).toBe('Sterilizer — Periode Lintas Stasiun Agustus 2026')
    expect(options[1].attributes('value')).toBe('per-1')

    // status ikut dipetakan — terlihat setelah periode dipilih.
    await selectPeriod(wrapper, 'per-1')
    expect(text(wrapper, 'period-status-badge')).toBe('Terbuka')
  })

  // unit_test_case 8
  it('memanggil summary hanya setelah periode dipilih', async () => {
    const wrapper = await mountView()

    expect(callsTo('/api/sterilizer-reports/summary')).toHaveLength(0)

    await selectPeriod(wrapper, 'per-1')

    expect(callsTo('/api/sterilizer-reports/summary')).toHaveLength(1)
    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/summary', {
      params: { period_id: 'per-1' },
    })
  })

  // unit_test_case 9 — PALING MUDAH SALAH: null bukan 0.
  it('menampilkan durasi rata-rata sebagai tidak tersedia ketika nilainya null, bukan sebagai nol', async () => {
    stubApi({
      summary: ok(
        makeSummary({
          kpi: {
            total_cycles: 12,
            total_cages: 144,
            avg_duration_minutes: null,
            min_duration_minutes: null,
            max_duration_minutes: null,
            cycles_without_duration: 12,
            triple_peak_compliance_percent: 0,
          },
        }),
      ),
    })

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'kpi-avg-duration')).toBe('tidak tersedia')
    expect(text(wrapper, 'kpi-avg-duration')).not.toBe('0')
    expect(text(wrapper, 'kpi-avg-duration')).not.toContain('0')

    // Terpendek & terlama juga, pada catatan kartu yang sama.
    const avgCard = wrapper.get('[data-testid="kpi-avg-duration"]').element.closest('.metric-card')
    expect(avgCard?.textContent).toContain('Terpendek tidak tersedia')
    expect(avgCard?.textContent).toContain('Terlama tidak tersedia')

    // cycles_without_duration tetap diteruskan apa adanya.
    expect(text(wrapper, 'cycles-without-duration')).toContain('12')
  })

  // unit_test_case 10
  it('meneruskan jumlah siklus tanpa durasi bersama rata-rata yang tersedia', async () => {
    stubApi({
      summary: ok(
        makeSummary({
          kpi: {
            total_cycles: 100,
            total_cages: 1200,
            avg_duration_minutes: 95,
            min_duration_minutes: 70,
            max_duration_minutes: 130,
            cycles_without_duration: 4,
            triple_peak_compliance_percent: 90,
          },
        }),
      ),
    })

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'kpi-avg-duration')).toBe('95')
    // Field terpisah — TIDAK digabung ke dalam rata-rata.
    expect(text(wrapper, 'cycles-without-duration')).toContain('4')
    expect(text(wrapper, 'cycles-without-duration')).toContain('tidak ikut dihitung')
  })

  // unit_test_case 11
  it('menyatakan data belum cukup dan tidak menandai pencilan ketika insufficient_data bernilai true', async () => {
    stubApi({
      summary: ok(
        makeSummary({
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
      ),
    })

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'insufficient-data')).toBe(true)
    expect(text(wrapper, 'insufficient-data')).toContain('3')
    expect(text(wrapper, 'insufficient-data')).toContain('8')
    expect(exists(wrapper, 'outlier-list')).toBe(false)
    expect(exists(wrapper, 'outlier-threshold')).toBe(false)
  })

  // unit_test_case 12
  it('tetap menampilkan ambang batas ketika tidak ada pencilan namun data mencukupi', async () => {
    stubApi({
      summary: ok(
        makeSummary({
          outliers: {
            method: 'iqr',
            q1: 90,
            q3: 110,
            iqr: 20,
            lower_bound: 70,
            upper_bound: 130,
            sample_size: 40,
            min_sample_size: 8,
            insufficient_data: false,
            items: [],
          },
        }),
      ),
    })

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'insufficient-data')).toBe(false)
    expect(exists(wrapper, 'no-outliers')).toBe(true)
    expect(exists(wrapper, 'outlier-threshold')).toBe(true)
    expect(text(wrapper, 'outlier-threshold')).toContain('70')
    expect(text(wrapper, 'outlier-threshold')).toContain('130')
  })

  // unit_test_case 13
  it('menandai pencilan beserta ambang kuartil ketika terdapat siklus menyimpang', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(wrapper.findAll('[data-testid="outlier-list"] .outlier-item')).toHaveLength(2)
    expect(text(wrapper, 'outlier-threshold')).toContain('20')
    expect(text(wrapper, 'outlier-threshold')).toContain('180')
    // Metode yang ditampilkan adalah kuartil, bukan simpangan baku.
    expect(text(wrapper, 'outlier-threshold')).toContain('kuartil')
    expect(text(wrapper, 'outlier-threshold')).toContain('IQR')
    expect(text(wrapper, 'outlier-threshold')).not.toContain('simpangan baku,')
  })

  // unit_test_case 14
  it('menyusun state periode kosong ketika periode tidak memuat satu pun siklus', async () => {
    stubApi({
      summary: ok(
        makeSummary({
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
        }),
      ),
    })

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'empty-period')).toBe(true)
    // Grafik TIDAK dirender sebagai kotak kosong.
    expect(exists(wrapper, 'daily-trend')).toBe(false)
    expect(exists(wrapper, 'duration-distribution')).toBe(false)
  })

  // unit_test_case 15
  it('tetap mengaktifkan ekspor ketika periode berstatus tertutup', async () => {
    stubApi({
      periods: ok({ data: [PERIOD_CLOSED] }),
      summary: ok(
        makeSummary({
          period: {
            id: 'per-3',
            name: 'Periode Juli 2026',
            start_date: '2026-07-01',
            end_date: '2026-07-31',
            status: 'closed',
            business_unit_name: 'Business Unit A',
          },
        }),
      ),
    })

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-3')

    expect(text(wrapper, 'period-status-badge')).toBe('Ditutup')

    const exportButton = wrapper.get('[data-testid="export-button"]')
    expect(exportButton.attributes('disabled')).toBeUndefined()
    expect(exportButton.attributes('aria-disabled')).toBeUndefined()
  })

  // unit_test_case 16 — periode terpilih TIDAK boleh hilang.
  it('menyusun pesan kegagalan jaringan beserta opsi coba lagi tanpa membuang periode terpilih', async () => {
    const wrapper = await mountView()
    stubApi({ summary: fail(NETWORK_ERROR) })

    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'network-error')).toBe(true)
    expect(text(wrapper, 'network-error').length).toBeGreaterThan(0)
    expect(exists(wrapper, 'retry-button')).toBe(true)
    // selectedPeriodId tetap 'per-1'.
    expect(selectValue(wrapper, 'period-select')).toBe('per-1')
  })

  // unit_test_case 17 — 401 BUKAN kegagalan jaringan.
  it('meneruskan galat 401 ke penjagaan sesi alih-alih menampilkan pesan jaringan', async () => {
    const wrapper = await mountView()
    stubApi({ summary: fail(UNAUTHENTICATED_ERROR) })

    await selectPeriod(wrapper, 'per-1')

    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'retry-button')).toBe(false)
    expect(exists(wrapper, 'kpi-total-cycles')).toBe(false)
  })

  // unit_test_case 18
  it('menyusun pesan validasi ketika summary ditolak dengan 422', async () => {
    const wrapper = await mountView()
    stubApi({ summary: fail(VALIDATION_ERROR) })

    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'error-message')).toBe(true)
    expect(text(wrapper, 'error-message')).toContain('Mill wajib dipilih.')
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'kpi-total-cycles')).toBe(false)
  })

  // unit_test_case 19
  it('menyusun pesan larangan akses ketika endpoint options ditolak dengan 403', async () => {
    asRole('admin', null)
    stubApi({ units: fail(FORBIDDEN_ERROR) })

    const wrapper = await mountView()

    expect(exists(wrapper, 'error-message')).toBe(true)
    expect(text(wrapper, 'error-message')).toContain('Pemilih mill tidak tersedia')
    expect(exists(wrapper, 'mill-select')).toBe(false)
  })

  // unit_test_case 20
  it('membangun URL ekspor dengan period_id dan format csv lalu menyimpan berkas', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/export', {
      params: { period_id: 'per-1', format: 'csv' },
      responseType: 'blob',
    })
    // Mekanisme penyimpanan berkas dipanggil tepat satu kali.
    expect(createObjectURLMock).toHaveBeenCalledTimes(1)
    expect(revokeObjectURLMock).toHaveBeenCalledTimes(1)
  })

  // unit_test_case 21
  it('memetakan respons lengkap menjadi view model ketika seluruh syarat terpenuhi', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'period-meta')).toBe(true)
    expect(exists(wrapper, 'kpi-total-cycles')).toBe(true)
    expect(exists(wrapper, 'kpi-total-cages')).toBe(true)
    expect(exists(wrapper, 'kpi-avg-duration')).toBe(true)
    expect(exists(wrapper, 'kpi-triple-peak')).toBe(true)
    expect(exists(wrapper, 'daily-trend')).toBe(true)
    expect(exists(wrapper, 'duration-distribution')).toBe(true)
    expect(exists(wrapper, 'by-unit')).toBe(true)
    expect(exists(wrapper, 'outlier-threshold')).toBe(true)
    expect(exists(wrapper, 'outlier-list')).toBe(true)
    expect(exists(wrapper, 'daily-recap')).toBe(true)

    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)
    expect(exists(wrapper, 'empty-period')).toBe(false)
    expect(exists(wrapper, 'no-periods')).toBe(false)
    expect(exists(wrapper, 'no-mill-for-account')).toBe(false)
    expect(exists(wrapper, 'mill-required-hint')).toBe(false)
  })
})

/* ================================================================== */
/* 18 test_scenarios — component_test                                  */
/* ================================================================== */

describe('LaporanSterilizerView — test_scenarios / component_test (tech spec screen-135)', () => {
  // Scenario: "success"
  it('success — Operator terikat mill: seluruh bagian terender, rekap harian dapat dibuka-tutup, ekspor terpicu', async () => {
    const wrapper = await mountView()

    // mill-select tidak dirender; mill-current menampilkan mill akun.
    expect(exists(wrapper, 'mill-select')).toBe(false)
    expect(exists(wrapper, 'mill-current')).toBe(true)

    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'mill-current')).toContain('Business Unit A')
    expect(text(wrapper, 'period-meta')).toContain('Periode Agustus 2026')
    expect(text(wrapper, 'period-meta')).toContain('01 Agu 2026')
    expect(text(wrapper, 'period-meta')).toContain('31 Agu 2026')
    expect(text(wrapper, 'period-status-badge')).toBe('Terbuka')

    expect(text(wrapper, 'kpi-total-cycles')).toBe('1.234')
    expect(text(wrapper, 'kpi-total-cages')).toBe('15.678')
    expect(text(wrapper, 'kpi-avg-duration')).toBe('95,5')
    expect(text(wrapper, 'kpi-triple-peak')).toBe('87,5')
    expect(text(wrapper, 'cycles-without-duration')).toContain('4')

    expect(exists(wrapper, 'daily-trend')).toBe(true)
    expect(exists(wrapper, 'duration-distribution')).toBe(true)
    expect(exists(wrapper, 'by-unit')).toBe(true)
    expect(exists(wrapper, 'outlier-threshold')).toBe(true)
    expect(exists(wrapper, 'outlier-list')).toBe(true)
    expect(exists(wrapper, 'daily-recap')).toBe(true)

    // Rekap harian TERTUTUP secara bawaan (CollapsibleSection). Keadaan
    // buka/tutup dibaca lewat atribut style (v-show), konvensi yang sama
    // dengan CollapsibleSection.spec.ts dan keempat Form*View.spec.ts.
    const recapBody = () => wrapper.get('[data-testid="daily-recap"] [data-testid="collapsible-section-body"]')
    expect(recapBody().attributes('style')).toContain('display: none')

    const recapToggle = wrapper.get('[data-testid="daily-recap"] [data-testid="collapsible-section-toggle"]')
    await recapToggle.trigger('click')
    expect(recapBody().attributes('style')).not.toContain('display: none')
    expect(recapToggle.attributes('aria-expanded')).toBe('true')

    await recapToggle.trigger('click')
    expect(recapBody().attributes('style')).toContain('display: none')

    await recapToggle.trigger('click')
    expect(recapBody().attributes('style')).not.toContain('display: none')

    // Ekspor terpicu.
    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()
    expect(callsTo('/api/sterilizer-reports/export')).toHaveLength(1)
  })

  // Scenario: "success as Admin"
  it('success as Admin — periode baru terisi setelah mill dipilih, lalu seluruh angka terisi', async () => {
    asRole('admin', null)

    const wrapper = await mountView()

    // Pemilih mill berisi seluruh mill dari respons options.
    const millOptions = wrapper.findAll('[data-testid="mill-select"] option')
    expect(millOptions).toHaveLength(3) // placeholder + 2 mill
    expect(millOptions[1].text()).toBe('Mill Utara')
    expect(millOptions[2].text()).toBe('Mill Selatan')

    // period-select masih kosong sebelum mill dipilih.
    expect(wrapper.findAll('[data-testid="period-select"] option')).toHaveLength(1)
    expect(callsTo('/api/sterilizer-reports/periods')).toHaveLength(0)

    await selectMill(wrapper, 'bu-1')

    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/periods', {
      params: { business_unit_id: 'bu-1' },
    })
    expect(wrapper.findAll('[data-testid="period-select"] option')).toHaveLength(2)

    await selectPeriod(wrapper, 'per-1')

    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/summary', {
      params: { period_id: 'per-1', business_unit_id: 'bu-1' },
    })
    expect(text(wrapper, 'kpi-total-cycles')).toBe('1.234')
    expect(text(wrapper, 'kpi-total-cages')).toBe('15.678')
    expect(exists(wrapper, 'daily-trend')).toBe(true)
    expect(exists(wrapper, 'by-unit')).toBe(true)
    // Admin tidak mendapat keterangan mill akun — ia memilih sendiri.
    expect(exists(wrapper, 'mill-current')).toBe(false)

    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()
    expect(callsTo('/api/sterilizer-reports/export')).toHaveLength(1)
  })

  // Scenario: "Mill belum punya periode"
  it('Mill belum punya periode — no-periods, period-select kosong, tanpa kartu angka dan tanpa network-error', async () => {
    asRole('supervisor', 'bu-1')
    stubApi({ periods: ok({ data: [] }) })

    const wrapper = await mountView()

    expect(exists(wrapper, 'no-periods')).toBe(true)
    expect(text(wrapper, 'no-periods')).toContain('hubungi Admin')
    expect(wrapper.findAll('[data-testid="period-select"] option')).toHaveLength(1)
    expect(exists(wrapper, 'kpi-total-cycles')).toBe(false)
    expect(exists(wrapper, 'network-error')).toBe(false)
  })

  // Scenario: "Periode tanpa data"
  it('Periode tanpa data — empty-period, angka nol, grafik tidak dirender', async () => {
    stubApi({
      summary: ok(
        makeSummary({
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
        }),
      ),
    })

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'empty-period')).toBe(true)
    expect(text(wrapper, 'kpi-total-cycles')).toBe('0')
    expect(text(wrapper, 'kpi-total-cages')).toBe('0')
    expect(exists(wrapper, 'daily-trend')).toBe(false)
    expect(exists(wrapper, 'duration-distribution')).toBe(false)
    expect(exists(wrapper, 'daily-recap')).toBe(false)
  })

  // Scenario: "Seluruh siklus tanpa durasi"
  it('Seluruh siklus tanpa durasi — durasi tidak tersedia (bukan nol) dan cycles-without-duration tampil', async () => {
    stubApi({
      summary: ok(
        makeSummary({
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
      ),
    })

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'kpi-avg-duration')).toBe('tidak tersedia')
    const avgCard = wrapper.get('[data-testid="kpi-avg-duration"]').element.closest('.metric-card')
    expect(avgCard?.textContent).toContain('Terpendek tidak tersedia')
    expect(avgCard?.textContent).toContain('Terlama tidak tersedia')
    expect(avgCard?.textContent).not.toContain('Terpendek 0')
    expect(avgCard?.textContent).not.toContain('Terlama 0')
    expect(text(wrapper, 'cycles-without-duration')).toContain('20')
  })

  // Scenario: "Siklus terlalu sedikit untuk menentukan ambang"
  it('Siklus terlalu sedikit — insufficient-data tampil, ambang dan daftar pencilan tidak dirender', async () => {
    stubApi({
      summary: ok(
        makeSummary({
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
      ),
    })

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'insufficient-data')).toContain('belum cukup')
    expect(exists(wrapper, 'outlier-threshold')).toBe(false)
    expect(exists(wrapper, 'outlier-list')).toBe(false)
    expect(wrapper.findAll('.outlier-item')).toHaveLength(0)
  })

  // Scenario: "Durasi seragam"
  it('Durasi seragam — no-outliers tampil, insufficient-data tidak, ambang tetap tampil', async () => {
    stubApi({
      summary: ok(
        makeSummary({
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
      ),
    })

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'no-outliers')).toBe(true)
    expect(exists(wrapper, 'insufficient-data')).toBe(false)
    expect(exists(wrapper, 'outlier-threshold')).toBe(true)
    expect(text(wrapper, 'outlier-threshold')).toContain('Ambang bawah 95')
    expect(text(wrapper, 'outlier-threshold')).toContain('ambang atas')
  })

  // Scenario: "Admin belum memilih mill"
  it('Admin belum memilih mill — mill-required-hint, tanpa kartu angka, tanpa panggilan periods/summary', async () => {
    asRole('admin', null)

    const wrapper = await mountView()

    expect(exists(wrapper, 'mill-required-hint')).toBe(true)
    expect(text(wrapper, 'mill-required-hint')).toContain('Pilih mill')
    expect(exists(wrapper, 'kpi-total-cycles')).toBe(false)
    expect(callsTo('/api/sterilizer-reports/periods')).toHaveLength(0)
    expect(callsTo('/api/sterilizer-reports/summary')).toHaveLength(0)
    // Hanya endpoint options yang boleh terpanggil.
    expect(apiGetMock).toHaveBeenCalledTimes(1)
  })

  // Scenario: "Akun terikat mill tetapi mill-nya kosong" (api_test sengaja kosong)
  it('Akun terikat mill tetapi mill-nya kosong — pesan hubungi Admin dan NOL pemanggilan endpoint', async () => {
    asRole('operator', null)

    const wrapper = await mountView()

    expect(exists(wrapper, 'no-mill-for-account')).toBe(true)
    expect(text(wrapper, 'no-mill-for-account')).toContain('hubungi Admin')
    // Daftar seluruh mill TIDAK ditawarkan.
    expect(exists(wrapper, 'mill-select')).toBe(false)
    expect(exists(wrapper, 'kpi-total-cycles')).toBe(false)
    // Asersi intinya: nol pemanggilan, termasuk options.
    expect(apiGetMock).toHaveBeenCalledTimes(0)
    expect(callsTo('/business-units/options')).toHaveLength(0)
    expect(callsTo('/api/sterilizer-reports/periods')).toHaveLength(0)
  })

  // Scenario: "Mencoba melihat mill lain"
  it('Mencoba melihat mill lain — business_unit_id mill lain tidak pernah ikut terkirim', async () => {
    asRole('operator', 'bu-1')

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Query yang benar-benar dikirim view.
    for (const [, config] of callsTo('/api/sterilizer-reports/')) {
      const params = (config as { params?: Record<string, unknown> } | undefined)?.params ?? {}
      expect(params).not.toHaveProperty('business_unit_id')
    }

    // Bahkan ketika pemanggil memaksakan mill lain lewat repo.
    apiGetMock.mockClear()
    await sterilizerReportRepo.fetchSummary('per-1', { isAdmin: false, businessUnitId: 'bu-99' })
    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/summary', {
      params: { period_id: 'per-1' },
    })

    // Mill akun tetap yang ditampilkan, beserta angkanya.
    expect(text(wrapper, 'mill-current')).toContain('Business Unit A')
    expect(text(wrapper, 'kpi-total-cycles')).toBe('1.234')
  })

  // Scenario: "Jaringan gagal" (api_test sengaja kosong)
  it('Jaringan gagal — pesan + retry, periode terpilih bertahan, dan retry memuat ulang periode yang sama', async () => {
    const wrapper = await mountView()
    stubApi({ summary: fail(NETWORK_ERROR) })

    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'network-error')).toBe(true)
    expect(text(wrapper, 'network-error')).toContain('Tidak dapat terhubung ke server')
    expect(exists(wrapper, 'retry-button')).toBe(true)
    // Periode yang sudah dipilih TIDAK hilang.
    expect(selectValue(wrapper, 'period-select')).toBe('per-1')

    // Jaringan pulih.
    stubApi({ summary: ok(makeSummary()) })
    apiGetMock.mockClear()

    await wrapper.get('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/summary', {
      params: { period_id: 'per-1' },
    })
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(text(wrapper, 'kpi-total-cycles')).toBe('1.234')
  })

  // Scenario: "Sesi berakhir"
  it('Sesi berakhir — 401 mengarahkan ke rute login, bukan pesan jaringan', async () => {
    const wrapper = await mountView()
    stubApi({ summary: fail(UNAUTHENTICATED_ERROR) })

    await selectPeriod(wrapper, 'per-1')

    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'kpi-total-cycles')).toBe(false)
    expect(exists(wrapper, 'period-meta')).toBe(false)
  })

  // Scenario: "Periode tertutup"
  it('Periode tertutup — laporan tetap penuh, badge tertutup, ekspor tetap aktif dan terpicu', async () => {
    stubApi({
      periods: ok({ data: [PERIOD_CLOSED] }),
      summary: ok(
        makeSummary({
          period: {
            id: 'per-3',
            name: 'Periode Juli 2026',
            start_date: '2026-07-01',
            end_date: '2026-07-31',
            status: 'closed',
            business_unit_name: 'Business Unit A',
          },
        }),
      ),
    })

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-3')

    expect(text(wrapper, 'period-status-badge')).toBe('Ditutup')
    expect(exists(wrapper, 'kpi-total-cycles')).toBe(true)
    expect(exists(wrapper, 'daily-trend')).toBe(true)
    expect(exists(wrapper, 'duration-distribution')).toBe(true)
    expect(exists(wrapper, 'by-unit')).toBe(true)
    expect(exists(wrapper, 'daily-recap')).toBe(true)

    const exportButton = wrapper.get('[data-testid="export-button"]')
    expect(exportButton.attributes('disabled')).toBeUndefined()

    await exportButton.trigger('click')
    await flushPromises()

    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/export', {
      params: { period_id: 'per-3', format: 'csv' },
      responseType: 'blob',
    })
  })

  // Scenario: "daftar periode hanya memuat periode yang mencakup Sterilizer"
  it('daftar periode — hanya periode yang punya baris Sterilizer yang dapat dipilih, termasuk periode lintas stasiun', async () => {
    // Server memang tidak mengembalikan periode jenis stasiun lain; yang
    // diuji di sini adalah layar tidak menambahkan apa pun ke daftar itu.
    stubApi({ periods: ok({ data: [PERIOD_STER, PERIOD_LINTAS_STASIUN] }) })

    const wrapper = await mountView()

    const options = wrapper.findAll('[data-testid="period-select"] option')
    expect(options).toHaveLength(3)

    const labels = options.slice(1).map((option) => option.text())
    expect(labels).toEqual([
      'Sterilizer — Periode Agustus 2026',
      'Sterilizer — Periode Lintas Stasiun Agustus 2026',
    ])
    expect(labels.join(' ')).not.toContain('Threshing')
    expect(labels.join(' ')).not.toContain('Pressing')
  })

  // Scenario: "angka harus sama dengan laporan versi web" — NOL PERHITUNGAN
  // ULANG. Fixture sengaja tidak konsisten secara aritmetika (lihat docblock).
  it('angka sama dengan laporan web — seluruh nilai dipetakan apa adanya, tanpa perhitungan ulang di klien', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // KPI apa adanya dari respons, BUKAN jumlah baris daily (12 + 18 = 30).
    expect(text(wrapper, 'kpi-total-cycles')).toBe('1.234')
    expect(text(wrapper, 'kpi-total-cycles')).not.toBe('30')
    expect(text(wrapper, 'kpi-total-cages')).toBe('15.678')
    expect(text(wrapper, 'kpi-total-cages')).not.toBe('360') // 144 + 216
    expect(text(wrapper, 'kpi-avg-duration')).toBe('95,5')
    expect(text(wrapper, 'kpi-triple-peak')).toBe('87,5')

    // by_unit apa adanya — bukan diturunkan dari total mana pun.
    const byUnit = text(wrapper, 'by-unit')
    expect(byUnit).toContain('700')
    expect(byUnit).toContain('534')
    expect(byUnit).toContain('8.400')
    expect(byUnit).toContain('96')
    // Unit tanpa rata-rata durasi tidak diberi angka karangan.
    expect(byUnit).toContain('tidak tersedia')

    // Rekap harian memakai blok `total` apa adanya (999/8.888/777),
    // meskipun bertentangan dengan kpi maupun jumlah barisnya sendiri.
    await wrapper.get('[data-testid="daily-recap"] [data-testid="collapsible-section-toggle"]').trigger('click')
    const recap = text(wrapper, 'daily-recap')
    expect(recap).toContain('Total 999 siklus')
    expect(recap).toContain('8.888 lori')
    expect(recap).toContain('777 siklus patuh')
    // Baris harian pun apa adanya.
    expect(recap).toContain('12')
    expect(recap).toContain('18')
  })

  // Scenario: "rentang periode inklusif"
  it('rentang periode inklusif — baris tanggal mulai dan tanggal akhir tampil, tanpa tanggal di luar rentang', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    await wrapper.get('[data-testid="daily-recap"] [data-testid="collapsible-section-toggle"]').trigger('click')

    const rows = wrapper.findAll('[data-testid="daily-recap"] tbody tr')
    expect(rows).toHaveLength(2)
    expect(rows[0].text()).toContain('01 Agu 2026')
    expect(rows[1].text()).toContain('31 Agu 2026')

    const recap = text(wrapper, 'daily-recap')
    expect(recap).not.toContain('31 Jul 2026')
    expect(recap).not.toContain('01 Sep 2026')

    // Total mencakup kedua tanggal batas — dan tetap nilai server.
    expect(text(wrapper, 'kpi-total-cycles')).toBe('1.234')
  })

  // Scenario: "rata-rata durasi mengabaikan siklus tanpa durasi"
  it('rata-rata mengabaikan siklus tanpa durasi — nilai rata-rata tampil bersama jumlah siklus yang dikeluarkan', async () => {
    stubApi({
      summary: ok(
        makeSummary({
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
      ),
    })

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'kpi-avg-duration')).toBe('92')
    const avgCard = wrapper.get('[data-testid="kpi-avg-duration"]').element.closest('.metric-card')
    expect(avgCard?.textContent).toContain('Terpendek 70')
    expect(avgCard?.textContent).toContain('Terlama 120')
    // Konteksnya berada di kartu yang sama dengan rata-rata.
    expect(avgCard?.querySelector('[data-testid="cycles-without-duration"]')?.textContent).toContain('7')
    expect(text(wrapper, 'cycles-without-duration')).toContain('tidak ikut dihitung')
  })

  // Scenario: "siklus di luar kebiasaan ditentukan dengan metode kuartil"
  it('pencilan metode kuartil — daftar pencilan dan ambang tampil, insufficient-data tidak dirender', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const items = wrapper.findAll('[data-testid="outlier-list"] .outlier-item')
    expect(items).toHaveLength(2)
    expect(items[0].text()).toContain('200')
    expect(items[1].text()).toContain('15')

    const threshold = text(wrapper, 'outlier-threshold')
    expect(threshold).toContain('kuartil')
    expect(threshold).toContain('Ambang bawah 20')
    expect(threshold).toContain('180')
    expect(threshold).toContain('Q1')
    expect(threshold).toContain('Q3')

    expect(exists(wrapper, 'insufficient-data')).toBe(false)
  })
})
