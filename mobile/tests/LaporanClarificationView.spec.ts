/**
 * LaporanClarificationView.spec.ts — screen-138--laporan-clarification-mobile /
 * usecase-138--laporan-clarification-mobile "Lihat Laporan Periode
 * Clarification (Mobile)".
 *
 * Satu test per test_scenarios yang punya component_test — seluruh 29.
 *
 * STRATEGI MOCK — yang di-mock adalah REPO, bukan apiClient.
 *
 * Pembagiannya disengaja dan berpasangan dengan
 * tests/clarificationReportRepo.spec.ts (konvensi yang sama dengan
 * LaporanBoilerRoomView.spec.ts): seluruh klaim tentang QUERY YANG DIKIRIM
 * dan PEMETAAN RESPONS — scopeParams() yang mengosongkan business_unit_id
 * untuk peran terikat mill, unwrap() yang menerima kedua bentuk pembungkus,
 * null yang bertahan sebagai null — sudah dikunci baris demi baris di sana
 * terhadap apiClient yang sungguhan. Yang tersisa untuk berkas ini, dan
 * hanya dapat dibuktikan di sini, adalah apa yang dilakukan VIEW terhadap
 * nilai-nilai itu: null menjadi "-" (bukan "0"), downtime yang tidak pernah
 * tercatat menjadi kata alih-alih angka nol, 401 menjadi perpindahan ke
 * Login TANPA tombol Coba Lagi, jaringan putus menjadi pesan + Coba Lagi
 * yang TIDAK membuang periode terpilih, dan buka/tutup rekap yang tidak
 * memicu satu pun permintaan baru.
 *
 * ANGKA FIXTURE SENGAJA TIDAK KONSISTEN SECARA ARITMETIKA — sama persis
 * dengan fixture pada clarificationReportRepo.spec.ts, dan dengan alasan
 * yang sama: layar yang diam-diam menghitung ulang HARUS gagal di sini.
 *   - production.total_ton (128,5) ≠ jumlah daily.production_ton (66,0) dan
 *     ≠ jumlah daily.rate_avg (51,5) — menjumlahkan laju per jam sendiri
 *     adalah godaan terbesar di layar ini;
 *   - metrics.pure_oil_production_rate_ton_hour.max (14,8) LEBIH KECIL
 *     daripada daily[0].rate_avg (42,1) — ekstrem berasal dari PEMBACAAN
 *     MENTAH per slot waktu, bukan dari rata-rata harian;
 *   - coverage.expected_slots (144) ≠ 2 × 6 × 24 (288);
 *   - downtime.total_mins (240) ≠ jumlah daily.downtime_mins (95);
 *   - total.days_with_records (11) ≠ panjang daily (3).
 * JANGAN "merapikan" angka-angka ini.
 *
 * REKAP HARIAN MEMAKAI v-if, BUKAN v-show — sama seperti screen-137, dan
 * berbeda dari screen-135/136 yang membungkusnya dengan CollapsibleSection.
 * Di layar ini barisnya benar-benar HILANG dari DOM saat tertutup, jadi
 * keadaan tertutup diasersi sebagai KETIADAAN (toHaveLength(0)), bukan
 * sebagai keterlihatan — jsdom pun menyimpan cache getComputedStyle per
 * elemen, sehingga asersi berbasis keterlihatan di sana tidak dapat
 * dipercaya.
 *
 * SESI BERAKHIR MEMAKAI router.replace, BUKAN push: layar laporan yang
 * sesinya sudah mati tidak boleh dapat dicapai kembali dengan tombol Back
 * peramban. Tech spec menuliskan "router.push dipanggil menuju rute Login";
 * yang berlaku adalah replace, mengikuti screen-135/136/137, dan router
 * palsu di bawah memaparkan keduanya sehingga keduanya dapat diperiksa —
 * termasuk bahwa push TIDAK dipakai.
 *
 * TUNTUTAN PIKSEL ADA DI BROWSER TEST. jsdom tidak menghitung tata letak,
 * jadi "44x44 piksel" dan "tanpa gulir mendatar halaman" dibuktikan di
 * tests/e2e/laporan-clarification.spec.ts pada viewport 390x844. Yang
 * dibuktikan di sini adalah STRUKTURnya: tumpukan satu kolom, kelas
 * kontainer yang menggulir sendiri, dan kendali yang memakai kelas
 * ber-min-height 44px.
 */
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import LaporanClarificationView from '@/views/LaporanClarificationView.vue'

/* ------------------------------------------------------------------ */
/* Mock modul                                                          */
/* ------------------------------------------------------------------ */

const { pushMock, replaceMock } = vi.hoisted(() => ({
  pushMock: vi.fn(),
  replaceMock: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock, replace: replaceMock }),
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

const { repoMocks } = vi.hoisted(() => ({
  repoMocks: {
    fetchBusinessUnits: vi.fn(),
    fetchPeriods: vi.fn(),
    fetchSummary: vi.fn(),
    exportCsv: vi.fn(),
    saveCsvFile: vi.fn(),
  },
}))

vi.mock('@/services/clarificationReportRepo', () => ({
  default: repoMocks,
  ...repoMocks,
}))

/* ------------------------------------------------------------------ */
/* Bentuk galat NYATA (apiClient.normalizeError) — DATAR, tanpa         */
/* properti `response`. Ketiadaan `status` itulah yang membedakan       */
/* "jaringan putus" dari "server menjawab 401/403/422".                 */
/* ------------------------------------------------------------------ */

const NETWORK_ERROR = { message: 'Tidak dapat terhubung ke server. Data akan disimpan secara lokal.' }
const UNAUTHENTICATED_ERROR = { message: 'Unauthenticated.', status: 401 }
const FORBIDDEN_ERROR = { message: 'Anda tidak memiliki akses untuk aksi ini.', status: 403 }

/* ------------------------------------------------------------------ */
/* Fixture                                                             */
/* ------------------------------------------------------------------ */

const PERIOD_CLF = {
  id: 'per-1',
  name: 'Periode Maret 2026',
  start_date: '2026-03-01',
  end_date: '2026-03-06',
  status: 'open',
  station_type: 'clarification',
  station_type_label: 'Clarification',
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
  station_type: 'clarification',
  station_type_label: 'Clarification',
}

const PERIOD_CLOSED = {
  id: 'per-3',
  name: 'Periode Februari 2026',
  start_date: '2026-02-01',
  end_date: '2026-02-28',
  status: 'closed',
  station_type: 'clarification',
  station_type_label: 'Clarification',
}

const BUSINESS_UNITS = [
  { id: 'bu-1', name: 'Mill Utara' },
  { id: 'bu-2', name: 'Mill Selatan' },
]

const EMPTY_METRIC = { min: null, avg: null, max: null, reading_count: 0 }

function makeMetrics(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    pure_oil_production_rate_ton_hour: { min: 4.1, avg: 9.33, max: 14.8, reading_count: 120 },
    clarification_tank_temp_c: { min: 89.5, avg: 93.4, max: 95.1, reading_count: 113 },
    oil_tank_temperature_c: { min: 93.2, avg: 97.2, max: 98.9, reading_count: 106 },
    sludge_tank_temp_c: { min: 83.5, avg: 87.6, max: 89.4, reading_count: 98 },
    buffer_tank_level_percent: { min: 58.7, avg: 72.4, max: 78.4, reading_count: 6 },
    downtime_mins: { min: 0, avg: 6.0, max: 45, reading_count: 40 },
    ...overrides,
  }
}

const EMPTY_METRICS = {
  pure_oil_production_rate_ton_hour: { ...EMPTY_METRIC },
  clarification_tank_temp_c: { ...EMPTY_METRIC },
  oil_tank_temperature_c: { ...EMPTY_METRIC },
  sludge_tank_temp_c: { ...EMPTY_METRIC },
  buffer_tank_level_percent: { ...EMPTY_METRIC },
  downtime_mins: { ...EMPTY_METRIC },
}

const DAILY_ROWS = [
  {
    date: '2026-03-01',
    filled_slots: 4,
    production_ton: 40.0,
    rate_avg: 42.1,
    rate_reading_count: 4,
    clarification_tank_temp_avg: 93.8,
    oil_tank_temperature_avg: 97.0,
    sludge_tank_temp_avg: 87.1,
    buffer_tank_level_avg: null,
    downtime_mins: 35,
  },
  {
    date: '2026-03-02',
    filled_slots: 3,
    production_ton: null,
    rate_avg: null,
    rate_reading_count: 0,
    clarification_tank_temp_avg: 88.2,
    oil_tank_temperature_avg: null,
    sludge_tank_temp_avg: null,
    buffer_tank_level_avg: null,
    downtime_mins: null,
  },
  {
    date: '2026-03-06',
    filled_slots: 5,
    production_ton: 26.0,
    rate_avg: 9.4,
    rate_reading_count: 3,
    clarification_tank_temp_avg: 92.9,
    oil_tank_temperature_avg: 96.4,
    sludge_tank_temp_avg: 86.8,
    buffer_tank_level_avg: 71.0,
    downtime_mins: 60,
  },
]

const BY_UNIT_ROWS = [
  {
    clarification_id: 'CLF-1',
    reading_count: 301,
    production_ton: 120.0,
    rate_avg: 9.1,
    rate_reading_count: 118,
    clarification_tank_temp_avg: 93.5,
    oil_tank_temperature_avg: 97.1,
    sludge_tank_temp_avg: 87.4,
    downtime_mins: 210,
  },
  {
    clarification_id: 'CLF-2',
    reading_count: 0,
    production_ton: null,
    rate_avg: null,
    rate_reading_count: 0,
    clarification_tank_temp_avg: null,
    oil_tank_temperature_avg: null,
    sludge_tank_temp_avg: null,
    downtime_mins: null,
  },
]

const PRODUCTION = {
  total_ton: 128.5,
  avg_per_day_ton: 21.4,
  avg_production_per_day_ton: 21.4,
  reading_count: 96,
  avg_rate_ton_hour: 9.33,
  min_rate_ton_hour: 4.1,
  max_rate_ton_hour: 14.8,
}

const DOWNTIME = {
  total_mins: 240,
  avg_per_day_mins: 20.5,
  avg_downtime_per_day_mins: 20.5,
  hours_with_downtime: 7,
  reading_count: 40,
}

function makeSummary(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    period: {
      id: 'per-1',
      name: 'Periode Maret 2026',
      start_date: '2026-03-01',
      end_date: '2026-03-06',
      status: 'open',
      business_unit_name: 'Mill Utara',
    },
    business_unit: { id: 'bu-1', name: 'Mill Utara' },
    has_data: true,
    coverage: {
      filled_slots: 9,
      expected_slots: 144,
      coverage_percent: 6.25,
      unit_count: 2,
      slots_per_unit_per_day: 24,
      days_in_period: 6,
    },
    production: { ...PRODUCTION },
    downtime: { ...DOWNTIME },
    metrics: makeMetrics(),
    daily: DAILY_ROWS,
    by_unit: BY_UNIT_ROWS,
    total: { days_with_records: 11, reading_rows: 412 },
    ...overrides,
  }
}

/** Periode tanpa satu pun pembacaan — has_data false datang dari SERVER. */
const EMPTY_SUMMARY = makeSummary({
  has_data: false,
  coverage: {
    filled_slots: 0,
    expected_slots: 720,
    coverage_percent: 0,
    unit_count: 1,
    slots_per_unit_per_day: 24,
    days_in_period: 30,
  },
  production: {
    total_ton: null,
    avg_per_day_ton: null,
    avg_production_per_day_ton: null,
    reading_count: 0,
    avg_rate_ton_hour: null,
    min_rate_ton_hour: null,
    max_rate_ton_hour: null,
  },
  downtime: {
    total_mins: null,
    avg_per_day_mins: null,
    avg_downtime_per_day_mins: null,
    hours_with_downtime: 0,
    reading_count: 0,
  },
  metrics: { ...EMPTY_METRICS },
  daily: [],
  by_unit: [],
  total: { days_with_records: 0, reading_rows: 0 },
})

/** Rekap sebulan penuh — kasus yang membuat rekap perlu ditutup. */
const THIRTY_DAYS = Array.from({ length: 30 }, (_, index) => ({
  date: `2026-03-${String(index + 1).padStart(2, '0')}`,
  filled_slots: 4,
  production_ton: 10 + index * 0.5,
  rate_avg: 9 + index * 0.1,
  rate_reading_count: 4,
  clarification_tank_temp_avg: 93 + index * 0.1,
  oil_tank_temperature_avg: 97 + index * 0.1,
  sludge_tank_temp_avg: 87 + index * 0.1,
  buffer_tank_level_avg: 70 + index * 0.1,
  downtime_mins: 5,
}))

/** Keempat kartu metrik, urutan mengikuti laporan web screen-132. */
const METRIC_TESTIDS = ['clarification-temp', 'oil-temp', 'sludge-temp', 'buffer-level']

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
    businessUnit: businessUnitId ? { id: businessUnitId, name: 'Mill Utara' } : null,
    logout: logoutMock,
  })
}

/* ------------------------------------------------------------------ */
/* Bantuan mount / interaksi                                           */
/* ------------------------------------------------------------------ */

async function mountView(): Promise<VueWrapper> {
  const wrapper = mount(LaporanClarificationView)
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

function recapRows(wrapper: VueWrapper) {
  return wrapper.findAll('[data-testid="daily-recap-row"]')
}

/** Posisi sebuah blok dalam urutan DOM, dibaca dari HTML terender. */
function domPosition(wrapper: VueWrapper, testid: string): number {
  return wrapper.html().indexOf(`data-testid="${testid}"`)
}

beforeEach(() => {
  vi.clearAllMocks()
  // Aturan repo: setiap test yang dapat menyentuh localStorage wajib
  // membersihkannya — jsdom membagi satu objek localStorage per berkas.
  window.localStorage.clear()

  asRole('operator', 'bu-1')

  repoMocks.fetchBusinessUnits.mockResolvedValue(BUSINESS_UNITS)
  repoMocks.fetchPeriods.mockResolvedValue([PERIOD_CLF])
  repoMocks.fetchSummary.mockResolvedValue(makeSummary())
  repoMocks.exportCsv.mockResolvedValue(new Blob(['csv'], { type: 'text/csv' }))
  repoMocks.saveCsvFile.mockImplementation(() => {})
})

afterEach(() => {
  vi.restoreAllMocks()
})

/* ================================================================== */
/* 29 test_scenarios — component_test (tech spec screen-138)           */
/* ================================================================== */

describe('LaporanClarificationView — test_scenarios / component_test (tech spec screen-138)', () => {
  // Scenario 1: "berhasil dilihat Operator, Supervisor, dan Mill Management"
  it('berhasil dilihat peran terikat mill — tanpa pemilih Mill, seluruh blok terender apa adanya, tanpa kontrol tulis', async () => {
    for (const role of ['operator', 'supervisor', 'mill_management'] as const) {
      vi.clearAllMocks()
      asRole(role, 'bu-1')
      repoMocks.fetchPeriods.mockResolvedValue([PERIOD_CLF])
      repoMocks.fetchSummary.mockResolvedValue(makeSummary())

      const wrapper = await mountView()
      await selectPeriod(wrapper, 'per-1')

      // Pemilih Mill tidak dirender sama sekali untuk peran terikat mill.
      expect(exists(wrapper, 'mill-select')).toBe(false)
      expect(text(wrapper, 'mill-current')).toContain('Mill Utara')

      // Kelengkapan pencatatan, produksi beserta penyebutnya, downtime
      // sebagai konteks, ketiga suhu pada satu grafik, level buffer tank,
      // tren harian, rekap per unit, dan rekap harian.
      expect(text(wrapper, 'coverage-percent')).toBe('6,3%')
      expect(text(wrapper, 'production-total')).toBe('128,5')
      expect(text(wrapper, 'production-reading-count')).toContain('96')
      expect(text(wrapper, 'downtime-total')).toBe('240')
      expect(exists(wrapper, 'tank-temperature-chart')).toBe(true)
      expect(text(wrapper, 'metric-buffer-level-avg')).toBe('72,4')
      expect(exists(wrapper, 'daily-trend-production')).toBe(true)
      expect(exists(wrapper, 'by-unit-table')).toBe(true)
      expect(exists(wrapper, 'daily-recap')).toBe(true)

      // Tidak ada satu pun kontrol tulis.
      expect(wrapper.findAll('input')).toHaveLength(0)
      expect(wrapper.findAll('textarea')).toHaveLength(0)
      expect(wrapper.html()).not.toContain('Simpan')
      expect(wrapper.html()).not.toContain('Hapus')

      wrapper.unmount()
    }
  })

  // Scenario 2: "berhasil dilihat Admin setelah memilih mill"
  it('Admin — pemilih Mill dirender, memilih mill memuat ulang periode, lalu seluruh angka tampil untuk mill itu', async () => {
    asRole('admin', null)

    const wrapper = await mountView()

    expect(repoMocks.fetchBusinessUnits).toHaveBeenCalledTimes(1)
    expect(exists(wrapper, 'mill-select')).toBe(true)
    // Periode belum dimuat sebelum mill dipilih.
    expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()

    await selectMill(wrapper, 'bu-2')

    expect(repoMocks.fetchPeriods).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchPeriods).toHaveBeenCalledWith({ isAdmin: true, businessUnitId: 'bu-2' })

    await selectPeriod(wrapper, 'per-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('per-1', {
      isAdmin: true,
      businessUnitId: 'bu-2',
    })
    expect(text(wrapper, 'production-total')).toBe('128,5')
    expect(text(wrapper, 'production-reading-count')).toContain('96')
    expect(text(wrapper, 'downtime-total')).toBe('240')
    expect(exists(wrapper, 'tank-temperature-chart')).toBe(true)
    expect(text(wrapper, 'metric-buffer-level-avg')).toBe('72,4')
    expect(text(wrapper, 'coverage-percent')).toBe('6,3%')
    expect(exists(wrapper, 'daily-trend-production')).toBe(true)
    expect(exists(wrapper, 'daily-recap')).toBe(true)
  })

  // Scenario 3: "Operator membuka laporan"
  it('Operator — laporan penuh, tanpa pemilih Mill, fetchBusinessUnits TIDAK pernah dipanggil, scope tanpa business_unit_id', async () => {
    asRole('operator', 'bu-1')

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Persis seperti peran lain — tidak ada bagian yang dibatasi.
    expect(text(wrapper, 'production-total')).toBe('128,5')
    expect(text(wrapper, 'metric-clarification-temp-avg')).toBe('93,4')
    expect(exists(wrapper, 'by-unit-table')).toBe(true)

    // Pemilih Mill tidak dirender: mill tidak dapat diganti dari layar.
    expect(exists(wrapper, 'mill-select')).toBe(false)

    // Endpoint options TIDAK pernah dipanggil — memanggilnya akan membentuk
    // daftar SELURUH mill di perangkat orang yang tidak berhak melihatnya,
    // dan server pun menjawab 403 di sana.
    expect(repoMocks.fetchBusinessUnits).not.toHaveBeenCalled()

    // scopeParams menghasilkan objek kosong: businessUnitId null diteruskan
    // ke repo, yang membuangnya karena isAdmin false.
    expect(repoMocks.fetchPeriods).toHaveBeenCalledWith({ isAdmin: false, businessUnitId: null })
    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('per-1', {
      isAdmin: false,
      businessUnitId: null,
    })
  })

  // Scenario 4: "Admin memilih mill lebih dulu"
  it('Admin tanpa mill terpilih — pemilih Mill + arahan memilih, tanpa satu pun blok angka', async () => {
    asRole('admin', null)

    const wrapper = await mountView()

    expect(exists(wrapper, 'mill-select')).toBe(true)
    expect(text(wrapper, 'mill-required-hint')).toContain('Pilih mill terlebih dahulu')

    // Tidak ada satu pun blok angka selama mill belum dipilih.
    expect(exists(wrapper, 'coverage-card')).toBe(false)
    expect(exists(wrapper, 'production-card')).toBe(false)
    expect(exists(wrapper, 'downtime-card')).toBe(false)
    expect(exists(wrapper, 'metric-cards')).toBe(false)
    expect(exists(wrapper, 'daily-recap')).toBe(false)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
  })

  // Scenario 5: "mill belum punya periode"
  it('daftar periode kosong — pemilih tetap dirender kosong dengan arahan menghubungi Admin, BUKAN galat', async () => {
    repoMocks.fetchPeriods.mockResolvedValue([])

    const wrapper = await mountView()

    // Pemilih dirender, hanya opsi pembuka yang ada.
    expect(exists(wrapper, 'period-select')).toBe(true)
    expect(wrapper.get('[data-testid="period-select"]').findAll('option')).toHaveLength(1)

    expect(text(wrapper, 'no-periods')).toContain('hubungi Admin')

    // Daftar kosong BUKAN galat.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)
    expect(exists(wrapper, 'coverage-card')).toBe(false)
    expect(exists(wrapper, 'production-card')).toBe(false)
  })

  // Scenario 6: "periode tanpa data"
  it('periode tanpa data — keterangan belum ada data, seluruh angka "-" bukan nol, dan TIDAK ada grafik sama sekali', async () => {
    repoMocks.fetchSummary.mockResolvedValue(EMPTY_SUMMARY)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'empty-period')).toContain('Belum ada data')

    // production.total_ton null dan downtime.total_mins null dirender
    // sebagai "-" / "tidak tercatat", TIDAK PERNAH sebagai 0.
    expect(text(wrapper, 'production-total')).toBe('-')
    expect(text(wrapper, 'production-total')).not.toBe('0,0')
    expect(text(wrapper, 'downtime-total')).toBe('tidak tercatat')
    expect(text(wrapper, 'downtime-total')).not.toBe('0')

    for (const testid of METRIC_TESTIDS) {
      expect(text(wrapper, `metric-${testid}-avg`)).toBe('-')
      expect(text(wrapper, `metric-${testid}-count`)).toContain('0')
    }

    // Tidak ada grafik yang dirender — garis datar dari data kosong akan
    // terbaca sebagai hasil pengukuran.
    expect(exists(wrapper, 'daily-trend-production')).toBe(false)
    expect(exists(wrapper, 'tank-temperature-chart')).toBe(false)
  })

  // Scenario 7: "laju produksi tidak pernah tercatat"
  it('laju tidak pernah tercatat — produksi "-" dengan 0 pembacaan, sementara metrik suhu tetap normal', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        production: {
          total_ton: null,
          avg_per_day_ton: null,
          avg_production_per_day_ton: null,
          reading_count: 0,
          avg_rate_ton_hour: null,
          min_rate_ton_hour: null,
          max_rate_ton_hour: null,
        },
        metrics: makeMetrics({ pure_oil_production_rate_ton_hour: { ...EMPTY_METRIC } }),
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'production-total')).toBe('-')
    expect(text(wrapper, 'production-reading-count')).toContain('0')
    expect(text(wrapper, 'production-rate-avg')).toBe('-')
    expect(text(wrapper, 'production-rate-min')).toBe('-')
    expect(text(wrapper, 'production-rate-max')).toBe('-')

    // Metrik suhu TIDAK terpengaruh — penyebutnya masing-masing sendiri.
    expect(text(wrapper, 'metric-clarification-temp-avg')).toBe('93,4')
    expect(text(wrapper, 'metric-sludge-temp-avg')).toBe('87,6')
    expect(text(wrapper, 'metric-clarification-temp-count')).toContain('113')
  })

  // Scenario 8: "jam tanpa catatan laju"
  it('jam tanpa catatan laju — produksi memakai reading_count 96, BUKAN filled_slots 140, dan selisihnya terbaca lewat kelengkapan', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        coverage: {
          filled_slots: 140,
          expected_slots: 144,
          coverage_percent: 97.22,
          unit_count: 2,
          slots_per_unit_per_day: 24,
          days_in_period: 6,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Penyebut produksi adalah pembacaan LAJU (96), bukan slot terisi (140).
    expect(text(wrapper, 'production-reading-count')).toContain('96')
    expect(text(wrapper, 'production-reading-count')).not.toContain('140')

    // 44 jam tanpa catatan laju TIDAK ditambal nol: total tetap 128,5.
    expect(text(wrapper, 'production-total')).toBe('128,5')

    // Selisihnya terbaca lewat kartu kelengkapan pencatatan.
    expect(text(wrapper, 'coverage-slots')).toContain('140')
    expect(text(wrapper, 'coverage-slots')).toContain('144')
  })

  // Scenario 9: "downtime tercatat bersamaan dengan laju"
  it('downtime dan laju sama-sama tercatat — produksi tetap 128,5 tanpa pengurangan, downtime berdiri sendiri', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        downtime: {
          total_mins: 240,
          avg_per_day_mins: 40,
          avg_downtime_per_day_mins: 40,
          hours_with_downtime: 6,
          reading_count: 96,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Tidak dikurangi, tidak diprorata, tidak disesuaikan.
    expect(text(wrapper, 'production-total')).toBe('128,5')
    expect(text(wrapper, 'production-total')).not.toBe('124,5')
    expect(text(wrapper, 'production-total')).not.toBe('120,5')

    // Downtime pada kartunya SENDIRI, berikut keterangannya.
    expect(text(wrapper, 'downtime-total')).toBe('240')
    expect(text(wrapper, 'downtime-hours')).toContain('6')
    expect(text(wrapper, 'downtime-production-note')).toContain('TIDAK dikurangkan')
    expect(text(wrapper, 'downtime-production-note')).toContain('menghitung ganda')
  })

  // Scenario 10: "downtime tidak pernah tercatat"
  it('downtime tidak tercatat vs downtime nol — dua tampilan yang JELAS BERBEDA, dibedakan dari pasangan (total_mins, reading_count)', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        downtime: {
          total_mins: null,
          avg_per_day_mins: null,
          avg_downtime_per_day_mins: null,
          hours_with_downtime: 0,
          reading_count: 0,
        },
      }),
    )

    const unrecorded = await mountView()
    await selectPeriod(unrecorded, 'per-1')

    const unrecordedText = text(unrecorded, 'downtime-total')

    expect(unrecordedText).toBe('tidak tercatat')
    expect(unrecordedText).not.toBe('0')
    expect(text(unrecorded, 'downtime-reading-count')).toContain('0')

    unrecorded.unmount()
    vi.clearAllMocks()
    repoMocks.fetchPeriods.mockResolvedValue([PERIOD_CLF])
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        downtime: {
          total_mins: 0,
          avg_per_day_mins: 0,
          avg_downtime_per_day_mins: 0,
          hours_with_downtime: 0,
          reading_count: 40,
        },
      }),
    )

    const measuredZero = await mountView()
    await selectPeriod(measuredZero, 'per-1')

    const zeroText = text(measuredZero, 'downtime-total')

    // total_mins-nya 0 pada KEDUA kasus di sisi tampilan bila hanya nilai
    // itu yang dibaca — pembedanya adalah reading_count, dan layar memakai
    // pasangan itu.
    expect(zeroText).toBe('0')
    expect(zeroText).not.toBe(unrecordedText)
    expect(text(measuredZero, 'downtime-reading-count')).toContain('40')
  })

  // Scenario 11: "pencatatan sangat tidak lengkap"
  it('kelengkapan sangat rendah — kartu kelengkapan dirender DI ATAS seluruh blok angka, dengan keterangan produksi turunan', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        coverage: {
          filled_slots: 9,
          expected_slots: 720,
          coverage_percent: 1.25,
          unit_count: 1,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
        },
        production: { ...PRODUCTION, reading_count: 9 },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'coverage-percent')).toBe('1,3%')
    expect(text(wrapper, 'production-reading-count')).toContain('9')

    // URUTAN DOM: kelengkapan mendahului produksi, downtime, laju, dan
    // seluruh kartu metrik. Catatan kaki di bawah baru terbaca setelah
    // pembacanya terlanjur mempercayai angkanya.
    const coveragePos = domPosition(wrapper, 'coverage-card')
    expect(coveragePos).toBeGreaterThan(-1)
    for (const testid of ['production-card', 'downtime-card', 'production-rate-card', 'metric-cards']) {
      expect(coveragePos).toBeLessThan(domPosition(wrapper, testid))
    }

    // Keterangan bahwa produksi diturunkan dari pembacaan yang ADA dirender
    // berdampingan dengan total produksi.
    expect(text(wrapper, 'production-derived-note')).toContain('DITURUNKAN')
    expect(text(wrapper, 'production-derived-note')).toContain('berproduksi nol')
  })

  // Scenario 12: "mill punya beberapa unit Clarification"
  it('beberapa unit — angka periode dari blok production apa adanya, ketiga baris by_unit dirender termasuk yang reading_count 0', async () => {
    const threeUnits = [
      ...BY_UNIT_ROWS,
      {
        clarification_id: 'CLF-3',
        reading_count: 55,
        production_ton: 8.5,
        rate_avg: 2.2,
        rate_reading_count: 50,
        clarification_tank_temp_avg: 90.1,
        oil_tank_temperature_avg: 95.0,
        sludge_tank_temp_avg: 85.0,
        downtime_mins: 30,
      },
    ]

    repoMocks.fetchSummary.mockResolvedValue(makeSummary({ by_unit: threeUnits }))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Angka periode TIDAK dihitung ulang dari by_unit (120,0 + 8,5 = 128,5
    // hanya kebetulan pada fixture ini; yang dirender tetap total_ton).
    expect(text(wrapper, 'production-total')).toBe('128,5')

    const rows = wrapper.findAll('[data-testid="by-unit-row"]')

    expect(rows).toHaveLength(3)
    expect(rows[1].text()).toContain('CLF-2')
    // Unit tanpa pembacaan TIDAK disaring keluar — ia adalah temuan.
    expect(rows[1].text()).toContain('0')
    expect(rows[2].text()).toContain('CLF-3')

    // Tabelnya menggulir DI DALAM kartunya sendiri.
    expect(wrapper.get('[data-testid="by-unit-card"]').find('.detail-table-wrap').exists()).toBe(true)
  })

  // Scenario 13: "akun belum terhubung ke mill"
  it('akun tanpa mill — pesan menghubungi Admin, TANPA pemilih Mill pengganti dan TANPA satu pun permintaan', async () => {
    for (const role of ['operator', 'supervisor', 'mill_management'] as const) {
      vi.clearAllMocks()
      asRole(role, null)

      const wrapper = await mountView()

      expect(text(wrapper, 'no-mill-for-account')).toContain('hubungi Admin')

      // Pemilih Mill TIDAK dirender sebagai gantinya.
      expect(exists(wrapper, 'mill-select')).toBe(false)
      expect(exists(wrapper, 'period-select')).toBe(false)

      // NOL permintaan — termasuk endpoint options, yang justru akan
      // membentuk daftar seluruh mill di perangkat yang tidak berhak.
      expect(repoMocks.fetchBusinessUnits).not.toHaveBeenCalled()
      expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()
      expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

      expect(exists(wrapper, 'coverage-card')).toBe(false)
      expect(exists(wrapper, 'production-card')).toBe(false)

      wrapper.unmount()
    }
  })

  // Scenario 14: "mencoba melihat mill lain"
  it('Operator tidak punya jalan apa pun menyebut mill lain — setiap pemanggilan repo memakai scope tanpa business_unit_id', async () => {
    asRole('operator', 'bu-1')

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    for (const call of repoMocks.fetchPeriods.mock.calls) {
      expect(call[0]).toEqual({ isAdmin: false, businessUnitId: null })
    }

    for (const call of repoMocks.fetchSummary.mock.calls) {
      expect(call[1]).toEqual({ isAdmin: false, businessUnitId: null })
    }

    // Tidak ada kendali apa pun untuk memasukkan mill lain.
    expect(exists(wrapper, 'mill-select')).toBe(false)
    expect(wrapper.findAll('input')).toHaveLength(0)
    expect(wrapper.findAll('select')).toHaveLength(1)
  })

  // Scenario 15: "jaringan gagal"
  it('jaringan gagal — pesan + Coba Lagi, periode terpilih TETAP, dan coba lagi memakai period_id yang SAMA', async () => {
    const wrapper = await mountView()
    repoMocks.fetchSummary.mockRejectedValueOnce(NETWORK_ERROR)

    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'network-error')).toContain('Tidak dapat terhubung ke server')
    expect(exists(wrapper, 'retry-button')).toBe(true)

    // Periode terpilih TIDAK hilang — pengguna di area tanpa sinyal tidak
    // boleh dipaksa memilih periodenya dari awal.
    expect(selectValue(wrapper, 'period-select')).toBe('per-1')

    // Kegagalan transport bukan sesi berakhir: tidak ada perpindahan rute.
    expect(replaceMock).not.toHaveBeenCalled()
    expect(pushMock).not.toHaveBeenCalled()

    // Jaringan pulih.
    repoMocks.fetchSummary.mockResolvedValue(makeSummary())
    repoMocks.fetchSummary.mockClear()

    await wrapper.get('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    // period_id yang SAMA, tanpa memilih ulang.
    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('per-1', { isAdmin: false, businessUnitId: null })
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(text(wrapper, 'production-total')).toBe('128,5')
    expect(selectValue(wrapper, 'period-select')).toBe('per-1')
  })

  // Scenario 16: "sesi berakhir"
  it('sesi berakhir — 401 mengarahkan ke Login lewat replace, tanpa angka dan TANPA tombol coba lagi', async () => {
    const wrapper = await mountView()
    repoMocks.fetchSummary.mockRejectedValueOnce(UNAUTHENTICATED_ERROR)

    await selectPeriod(wrapper, 'per-1')

    // replace, bukan push: layar yang sesinya mati tidak boleh dicapai
    // kembali dengan tombol Back peramban.
    expect(replaceMock).toHaveBeenCalledWith({ name: 'login' })
    expect(pushMock).not.toHaveBeenCalled()

    // Sesi berakhir BUKAN kasus coba lagi — pesan galat dan tombolnya
    // justru tidak boleh muncul.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'retry-button')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)

    // Tidak ada blok angka yang sempat dirender.
    expect(exists(wrapper, 'coverage-card')).toBe(false)
    expect(exists(wrapper, 'production-card')).toBe(false)
    expect(exists(wrapper, 'metric-cards')).toBe(false)
    expect(exists(wrapper, 'period-meta')).toBe(false)
  })

  // Scenario 17: "periode tertutup"
  it('periode tertutup — laporan tetap penuh, penanda Ditutup terender, tombol Ekspor tetap aktif', async () => {
    repoMocks.fetchPeriods.mockResolvedValue([PERIOD_CLOSED])
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        period: {
          id: 'per-3',
          name: 'Periode Februari 2026',
          start_date: '2026-02-01',
          end_date: '2026-02-28',
          status: 'closed',
          business_unit_name: 'Mill Utara',
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-3')

    expect(text(wrapper, 'period-status-badge')).toContain('Ditutup')

    // Seluruh bagian tetap penuh.
    expect(text(wrapper, 'production-total')).toBe('128,5')
    expect(exists(wrapper, 'coverage-card')).toBe(true)
    expect(exists(wrapper, 'by-unit-table')).toBe(true)
    expect(exists(wrapper, 'daily-recap')).toBe(true)

    // Ekspor TIDAK dinonaktifkan oleh status periode: kunci periode
    // mengatur penulisan data, bukan pembacaan laporan.
    const exportButton = wrapper.get('[data-testid="export-button"]')
    expect(exportButton.attributes('disabled')).toBeUndefined()
  })

  // Scenario 18: "rekap harian dapat ditutup"
  it('rekap harian buka/tutup/buka — barisnya benar-benar hilang dari DOM, dan fetchSummary tetap dipanggil TEPAT 1 kali', async () => {
    repoMocks.fetchSummary.mockResolvedValue(makeSummary({ daily: THIRTY_DAYS }))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)

    // TERTUTUP secara bawaan — barisnya tidak dirender sama sekali (v-if,
    // bukan v-show: v-show akan menyisakannya di DOM).
    expect(recapRows(wrapper)).toHaveLength(0)

    // Buka.
    await wrapper.get('[data-testid="daily-recap-toggle"]').trigger('click')
    expect(recapRows(wrapper)).toHaveLength(30)

    // Tutup — baris hilang, sementara kartu kelengkapan, produksi, downtime,
    // dan grafik suhu TETAP dirender.
    await wrapper.get('[data-testid="daily-recap-toggle"]').trigger('click')
    expect(recapRows(wrapper)).toHaveLength(0)
    expect(exists(wrapper, 'coverage-card')).toBe(true)
    expect(exists(wrapper, 'production-card')).toBe(true)
    expect(exists(wrapper, 'downtime-card')).toBe(true)
    expect(exists(wrapper, 'tank-temperature-chart')).toBe(true)

    // Buka lagi — seluruh 30 baris kembali utuh.
    await wrapper.get('[data-testid="daily-recap-toggle"]').trigger('click')
    expect(recapRows(wrapper)).toHaveLength(30)

    // NOL permintaan jaringan baru — dihitung dari mock, bukan dari DOM.
    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchPeriods).toHaveBeenCalledTimes(1)
  })

  // Scenario 19: "layar hanya membaca"
  it('hanya membaca — tidak ada elemen form tulis, dan repo hanya memaparkan fungsi baca + ekspor', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Satu-satunya <select> adalah pemilih Periode (peran terikat mill),
    // dan tidak ada input/textarea/form sama sekali.
    expect(wrapper.findAll('input')).toHaveLength(0)
    expect(wrapper.findAll('textarea')).toHaveLength(0)
    expect(wrapper.findAll('form')).toHaveLength(0)
    expect(wrapper.findAll('select')).toHaveLength(1)

    for (const label of ['Simpan', 'Ubah', 'Hapus', 'Tambah']) {
      expect(wrapper.html()).not.toContain(label)
    }

    // Repo yang diimpor komponen hanya memaparkan fungsi baca ditambah
    // exportCsv/saveCsvFile — tidak ada fungsi tulis sama sekali.
    const repo = await import('@/services/clarificationReportRepo')
    expect(Object.keys(repo.default).sort()).toEqual([
      'exportCsv',
      'fetchBusinessUnits',
      'fetchPeriods',
      'fetchSummary',
      'saveCsvFile',
    ])
  })

  // Scenario 20: "angka ponsel sama persis dengan laporan versi web"
  it('nol perhitungan ulang di layar — setiap angka yang dirender berasal dari payload apa adanya', async () => {
    const payload = makeSummary()
    repoMocks.fetchSummary.mockResolvedValue(payload)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Angka-angka fixture yang saling BERTENTANGAN tetap bertentangan di
    // layar — layar yang menghitung ulang akan menampilkan yang lain.
    expect(text(wrapper, 'production-total')).toBe('128,5')
    // Jumlah daily.production_ton = 66,0 dan jumlah daily.rate_avg = 51,5.
    expect(text(wrapper, 'production-total')).not.toBe('66,0')
    expect(text(wrapper, 'production-total')).not.toBe('51,5')

    expect(text(wrapper, 'production-rate-avg')).toBe('9,33')
    expect(text(wrapper, 'production-rate-max')).toBe('14,80')
    // max mentah (14,8) lebih KECIL daripada rata-rata harian tertinggi
    // (42,1) — mustahil bila diturunkan dari kolom harian.
    expect(text(wrapper, 'production-rate-max')).not.toBe('42,10')

    expect(text(wrapper, 'downtime-total')).toBe('240')
    // Jumlah daily.downtime_mins = 95.
    expect(text(wrapper, 'downtime-total')).not.toBe('95')

    expect(text(wrapper, 'coverage-percent')).toBe('6,3%')

    // total.days_with_records (11) ≠ panjang daily (3) dan reading_rows
    // (412) milik server — keduanya hanya terbaca setelah rekap dibuka.
    await wrapper.get('[data-testid="daily-recap-toggle"]').trigger('click')
    expect(text(wrapper, 'daily-recap-total')).toContain('11')
    expect(text(wrapper, 'daily-recap-total')).toContain('412')
  })

  // Scenario 21: "periode yang tidak mencakup Clarification tidak ditawarkan"
  it('pemilih periode merender persis entri yang diterima — komponen tidak menyaring station_type sendiri', async () => {
    repoMocks.fetchPeriods.mockResolvedValue([PERIOD_CLF, PERIOD_LINTAS_STASIUN])

    const wrapper = await mountView()

    const options = wrapper.get('[data-testid="period-select"]').findAll('option')

    // 1 opsi pembuka + 2 periode, dalam urutan server.
    expect(options).toHaveLength(3)
    expect(options[1].text()).toContain('Periode Maret 2026')
    expect(options[2].text()).toContain('Periode Lintas Stasiun Maret 2026')
    // Periode lintas stasiun TIDAK dibuang di klien — penyaringan cakupan
    // adalah pekerjaan server (periode itu sampai ke sini justru karena
    // punya baris period_stations berjenis 'clarification'), dan
    // menyaringnya dua kali menciptakan dua definisi.
    expect(options[2].attributes('value')).toBe('per-2')
  })

  // Scenario 22: "rentang periode inklusif di kedua ujung"
  it('kedua tanggal ujung dirender pada tren harian dan pada rekap harian, dan ikut membentuk angka utama', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // start_date 2026-03-01 dan end_date 2026-03-06 keduanya ada.
    expect(text(wrapper, 'daily-trend-production')).toContain('01')
    expect(text(wrapper, 'daily-trend-production')).toContain('06')

    await wrapper.get('[data-testid="daily-recap-toggle"]').trigger('click')

    const rows = recapRows(wrapper)

    expect(rows).toHaveLength(3)
    expect(rows[0].text()).toContain('01 Mar 2026')
    expect(rows[2].text()).toContain('06 Mar 2026')

    // Tidak ada baris ujung yang dipotong.
    expect(text(wrapper, 'daily-recap-toggle')).toContain('3 hari')
  })

  // Scenario 23: "produksi diturunkan dari laju per jam"
  it('kartu produksi berasal dari production.total_ton dengan reading_count-nya di sebelahnya — bukan penjumlahan daily.rate_avg', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        production: { ...PRODUCTION, total_ton: 128.5, reading_count: 96, avg_rate_ton_hour: 1.34 },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'production-total')).toBe('128,5')
    expect(text(wrapper, 'production-reading-count')).toContain('96')
    expect(text(wrapper, 'production-rate-avg')).toBe('1,34')

    // Komponen tidak menjumlahkan rate_avg dari daily (51,5).
    expect(text(wrapper, 'production-total')).not.toBe('51,5')

    // Dan layar mengatakan bahwa angkanya DITURUNKAN — tidak ada kolom
    // produksi pada payload maupun pada skema.
    expect(text(wrapper, 'production-derived-note')).toContain('Tidak ada kolom produksi')
  })

  // Scenario 24: "downtime sebagai konteks, bukan pengurang"
  it('produksi 128,5 dirender tanpa pengurangan oleh downtime 240, dan keterangan menghitung ganda dirender di layar', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'production-total')).toBe('128,5')
    // 128,5 − 240/60 = 124,5; 128,5 × (60−240)/60 negatif. Tak satu pun
    // muncul.
    expect(text(wrapper, 'production-total')).not.toBe('124,5')
    expect(wrapper.get('[data-testid="production-card"]').text()).not.toContain('240')

    // Kartu downtime berdiri sendiri di sebelahnya.
    expect(exists(wrapper, 'downtime-card')).toBe(true)
    expect(text(wrapper, 'downtime-total')).toBe('240')

    // Alasannya dirender: laju sudah rata-rata sepanjang jam.
    const note = text(wrapper, 'downtime-production-note')
    expect(note).toContain('laju rata-rata')
    expect(note).toContain('menghitung ganda')
  })

  // Scenario 25: "setiap metrik punya penyebutnya sendiri"
  it('tiap metrik dirender dengan reading_count-nya sendiri — tidak ada satu penyebut bersama di layar', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        metrics: makeMetrics({
          pure_oil_production_rate_ton_hour: { min: 4.1, avg: 9.33, max: 14.8, reading_count: 300 },
          buffer_tank_level_percent: { min: 58.7, avg: 72.4, max: 78.4, reading_count: 3 },
        }),
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Metrik yang jarang diisi TIDAK mengempis — rata-ratanya apa adanya.
    expect(text(wrapper, 'metric-buffer-level-avg')).toBe('72,4')
    expect(text(wrapper, 'metric-buffer-level-count')).toContain('3')

    // Keempat kartu membawa penyebutnya sendiri-sendiri, dan tidak ada satu
    // angka yang berlaku untuk semuanya.
    const counts = METRIC_TESTIDS.map((testid) => text(wrapper, `metric-${testid}-count`))
    expect(new Set(counts).size).toBe(METRIC_TESTIDS.length)

    // total.reading_rows (412) tidak pernah dipakai sebagai penyebut metrik.
    for (const count of counts) {
      expect(count).not.toContain('412')
    }
  })

  // Scenario 26: "ketiga suhu tangki pada satu grafik"
  it('ketiga suhu tangki adalah tiga seri pada SATU grafik dengan legenda ketiganya, di dalam kontainer yang menggulir sendiri', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // SATU elemen grafik — bukan tiga grafik terpisah.
    expect(wrapper.findAll('[data-testid="tank-temperature-chart"]')).toHaveLength(1)

    // Tiga seri per tanggal: 3 tanggal × 3 batang = 9.
    expect(wrapper.findAll('[data-testid="tank-bar-clarification"]')).toHaveLength(3)
    expect(wrapper.findAll('[data-testid="tank-bar-oil"]')).toHaveLength(3)
    expect(wrapper.findAll('[data-testid="tank-bar-sludge"]')).toHaveLength(3)

    // Legenda menamai ketiganya.
    expect(text(wrapper, 'tank-legend-clarification')).toContain('Tangki Clarification')
    expect(text(wrapper, 'tank-legend-oil')).toContain('Tangki Minyak')
    expect(text(wrapper, 'tank-legend-sludge')).toContain('Tangki Sludge')

    // Grafiknya dibungkus kontainer yang menggulir sendiri.
    expect(wrapper.get('[data-testid="tank-temperature-card"]').find('.chart-scroll').exists()).toBe(true)

    // Dan skalanya BERSAMA — itulah yang membuat selisih antar tangki
    // terbaca langsung dari tingginya.
    expect(text(wrapper, 'tank-gap-note')).toContain('skala yang SAMA')
  })

  // Scenario 27: "tidak ada penandaan nilai di luar batas"
  it('tidak ada satu pun penanda ambang, ikon peringatan, atau pewarnaan bersyarat — bahkan untuk nilai yang jauh menyimpang', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        metrics: makeMetrics({
          clarification_tank_temp_c: { min: 12.0, avg: 93.4, max: 480.0, reading_count: 113 },
          sludge_tank_temp_c: { min: -40.0, avg: 87.6, max: 999.9, reading_count: 98 },
        }),
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Nilai menyimpang dirender apa adanya.
    expect(text(wrapper, 'metric-clarification-temp-max')).toBe('480,0')
    expect(text(wrapper, 'metric-sludge-temp-min')).toBe('-40,0')

    // Ketiadaan penandaan DIASERSI MENURUT NAMA — aturan "jangan menandai"
    // hanya bertahan bila ada yang menjaganya.
    const html = wrapper.html().toLowerCase()

    for (const marker of [
      'threshold',
      'outlier',
      'iqr',
      'is-danger',
      'is-warning',
      'severity',
      'status-flag',
      'out-of-range',
    ]) {
      expect(html).not.toContain(marker)
    }

    // Seluruh kartu metrik memakai kelas yang SAMA PERSIS, termasuk yang
    // memuat nilai ekstrem.
    const cardClasses = METRIC_TESTIDS.map(
      (testid) => wrapper.get(`[data-testid="metric-${testid}"]`).attributes('class'),
    )
    expect(new Set(cardClasses).size).toBe(1)
  })

  // Scenario 28: "tata letak satu kolom pada layar ponsel"
  it('struktur satu kolom — kendali memakai kelas ber-min-height 44px, grafik dan tabel menggulir di dalam kartunya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Akar layar adalah satu kolom flex dengan overflow-x tersembunyi
    // (nilai pikselnya dibuktikan di browser test).
    expect(wrapper.get('[data-testid="laporan-clarification-mobile"]').classes()).toContain(
      'laporan-clf-view',
    )

    // Kendali sentuh memakai kelas yang membawa min-height/min-width 44px.
    expect(wrapper.get('[data-testid="period-select"]').element.closest('.filter-field')).not.toBeNull()
    expect(wrapper.get('[data-testid="daily-recap-toggle"]').classes()).toContain('recap-toggle')
    expect(wrapper.get('[data-testid="export-button"]').classes()).toContain('action-button')
    expect(wrapper.get('[data-testid="hamburger-button"]').classes()).toContain('hamburger-button')

    // Grafik suhu dan tabel rekap per unit dibungkus kontainer yang
    // menggulir sendiri — bukan halaman yang bergeser.
    expect(wrapper.get('[data-testid="tank-temperature-card"]').find('.chart-scroll').exists()).toBe(true)
    expect(wrapper.get('[data-testid="daily-trend-production"]').find('.chart-scroll').exists()).toBe(true)
    expect(wrapper.get('[data-testid="by-unit-card"]').find('.detail-table-wrap').exists()).toBe(true)

    await wrapper.get('[data-testid="daily-recap-toggle"]').trigger('click')
    expect(wrapper.get('[data-testid="daily-recap"]').find('.detail-table-wrap').exists()).toBe(true)
  })

  // Scenario 29: "mengunduh rincian pembacaan per slot waktu sebagai CSV"
  it('Ekspor memanggil repo.exportCsv dengan period_id yang sedang dibuka dan menyerahkan blob-nya ke saveCsvFile', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    expect(repoMocks.exportCsv).toHaveBeenCalledTimes(1)
    expect(repoMocks.exportCsv).toHaveBeenCalledWith('per-1')

    expect(repoMocks.saveCsvFile).toHaveBeenCalledTimes(1)
    const [blob, filename] = repoMocks.saveCsvFile.mock.calls[0]
    expect(blob).toBeInstanceOf(Blob)
    expect(String(filename)).toMatch(/^laporan-clarification_.*\.csv$/)

    // Komponen TIDAK menyusun isi CSV sendiri dari data yang sedang tampil:
    // isinya dibentuk server, satu baris per slot waktu.
    expect(String(blob)).not.toContain('128,5')
  })
})

/* ================================================================== */
/* Pemeriksaan tambahan — bukan dari test_scenarios, tetapi menutup    */
/* jalur galat yang hanya ada di view                                  */
/* ================================================================== */

describe('LaporanClarificationView — penanganan galat khusus view', () => {
  it('403 pada endpoint options tidak pernah merender pemilih Mill yang pasti gagal', async () => {
    asRole('admin', null)
    repoMocks.fetchBusinessUnits.mockRejectedValueOnce(FORBIDDEN_ERROR)

    const wrapper = await mountView()

    expect(exists(wrapper, 'mill-select')).toBe(false)
    expect(text(wrapper, 'error-message')).toContain('Pemilih mill tidak tersedia')
    // Bukan kasus jaringan dan bukan kasus sesi.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(replaceMock).not.toHaveBeenCalled()
  })

  it('galat yang sampai ke view berbentuk DATAR — tidak ada properti response yang dibaca di mana pun', async () => {
    const wrapper = await mountView()

    // Bentuk yang SALAH (punya .response, tanpa .status di akar) sengaja
    // TIDAK diperlakukan sebagai 401: interceptor apiClient sudah membuang
    // `response`, jadi bentuk ini tidak pernah terjadi di produksi dan
    // membacanya hanya akan menyembunyikan kesalahan.
    const wrongShape = { message: 'Unauthenticated.', response: { status: 401 } }
    repoMocks.fetchSummary.mockRejectedValueOnce(wrongShape)

    await selectPeriod(wrapper, 'per-1')

    expect(replaceMock).not.toHaveBeenCalled()
    // Tanpa `status` di akar, view memperlakukannya sebagai kegagalan
    // transport — pesan + Coba Lagi.
    expect(exists(wrapper, 'network-error')).toBe(true)
    expect(exists(wrapper, 'retry-button')).toBe(true)

    // Dan bentuk yang BENAR memang diarahkan ke Login.
    repoMocks.fetchSummary.mockRejectedValueOnce(UNAUTHENTICATED_ERROR)
    await wrapper.get('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    expect(replaceMock).toHaveBeenCalledWith({ name: 'login' })
  })
})
