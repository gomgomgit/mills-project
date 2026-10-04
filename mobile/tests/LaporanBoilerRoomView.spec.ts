/**
 * LaporanBoilerRoomView.spec.ts — screen-137--laporan-boiler-room-mobile /
 * usecase-137--laporan-boiler-room-mobile "Lihat Laporan Periode Boiler
 * Room (Mobile)".
 *
 * Satu test per test_scenarios yang punya component_test — seluruh 26.
 *
 * STRATEGI MOCK — yang di-mock adalah REPO, bukan apiClient.
 *
 * Pembagiannya disengaja dan berpasangan dengan
 * tests/boilerRoomReportRepo.spec.ts (konvensi yang sama dengan
 * LaporanCagesTrackView.spec.ts): seluruh klaim tentang QUERY YANG DIKIRIM
 * dan PEMETAAN RESPONS — scopeParams() yang mengosongkan business_unit_id
 * untuk peran terikat mill, unwrap() yang menerima kedua bentuk pembungkus,
 * null yang bertahan sebagai null — sudah dikunci baris demi baris di sana
 * terhadap apiClient yang sungguhan. Yang tersisa untuk berkas ini, dan
 * hanya dapat dibuktikan di sini, adalah apa yang dilakukan VIEW terhadap
 * nilai-nilai itu: null menjadi "-" (bukan "0"), 401 menjadi perpindahan ke
 * Login TANPA tombol Coba Lagi, jaringan putus menjadi pesan + Coba Lagi
 * yang TIDAK membuang periode terpilih, dan buka/tutup rekap yang tidak
 * memicu satu pun permintaan baru.
 *
 * ANGKA FIXTURE SENGAJA TIDAK KONSISTEN SECARA ARITMETIKA — sama persis
 * dengan fixture pada boilerRoomReportRepo.spec.ts, dan dengan alasan yang
 * sama: layar yang diam-diam menghitung ulang HARUS gagal di sini.
 *   - metrics.steam_pressure_bar.avg (21,7) ≠ (min + max) / 2 dan ≠
 *     rata-rata kolom daily; max-nya (26,9) bahkan LEBIH KECIL daripada
 *     daily[0].steam_pressure_avg (42,1) — ekstrem berasal dari PEMBACAAN
 *     MENTAH per slot waktu, bukan dari rata-rata harian;
 *   - coverage_percent (6,3) ≠ 100 × 9 / 144 (6,25);
 *   - maintenance.blowdown.executed (5) ≠ jumlah daily (3) ≠ jumlah by_unit (4);
 *   - total.days_with_records (11) ≠ panjang daily (3).
 * JANGAN "merapikan" angka-angka ini.
 *
 * REKAP HARIAN MEMAKAI v-if, BUKAN v-show — berbeda dari screen-135/136
 * yang membungkusnya dengan CollapsibleSection. Di layar ini barisnya
 * benar-benar HILANG dari DOM saat tertutup, jadi keadaan tertutup diasersi
 * sebagai KETIADAAN (toHaveLength(0)), bukan sebagai keterlihatan.
 *
 * SESI BERAKHIR MEMAKAI router.replace, BUKAN push: layar laporan yang
 * sesinya sudah mati tidak boleh dapat dicapai kembali dengan tombol Back
 * peramban. Karena itu router palsu di bawah memaparkan keduanya, dan
 * keduanya diperiksa.
 *
 * TUNTUTAN PIKSEL ADA DI BROWSER TEST. jsdom tidak menghitung tata letak,
 * jadi "44x44 piksel" dan "tanpa gulir mendatar halaman" dibuktikan di
 * tests/e2e/laporan-boiler-room.spec.ts pada viewport 390x844. Yang
 * dibuktikan di sini adalah STRUKTURnya: tumpukan satu kolom, kelas
 * kontainer yang menggulir sendiri, dan kendali yang memakai kelas
 * ber-min-height 44px.
 */
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import LaporanBoilerRoomView from '@/views/LaporanBoilerRoomView.vue'

/* ------------------------------------------------------------------ */
/* Mock modul                                                          */
/* ------------------------------------------------------------------ */

const { pushMock, replaceMock } = vi.hoisted(() => ({
  pushMock: vi.fn(),
  replaceMock: vi.fn(),
}))

/** Query rute — screen-141 menyisipkan production_line_id di sini. */
const { routeQuery } = vi.hoisted(() => ({ routeQuery: {} as Record<string, string> }))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock, replace: replaceMock }),
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

vi.mock('@/services/boilerRoomReportRepo', () => ({
  default: repoMocks,
  ...repoMocks,
}))

/**
 * productionLineRepo — daftar Production Line untuk mill yang berlaku
 * (GET /api/production-lines/options-for-report). Di-stub di tingkat repo, sama
 * seperti repo laporan berkas ini, karena yang diuji di sini adalah
 * KEPUTUSAN LAYAR atas daftar itu, bukan bentuk permintaan HTTP-nya (itu
 * milik boilerRoomReportRepo.spec.ts).
 */
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
/* Bentuk galat NYATA (apiClient.normalizeError) — DATAR, tanpa         */
/* properti `response`. Ketiadaan `status` itulah yang membedakan       */
/* "jaringan putus" dari "server menjawab 401/403/422".                 */
/* ------------------------------------------------------------------ */

const NETWORK_ERROR = { message: 'Tidak dapat terhubung ke server. Data akan disimpan secara lokal.' }
const UNAUTHENTICATED_ERROR = { message: 'Unauthenticated.', status: 401 }

/* ------------------------------------------------------------------ */
/* Fixture                                                             */
/* ------------------------------------------------------------------ */

const PERIOD_BR = {
  id: 'per-1',
  name: 'Periode Maret 2026',
  start_date: '2026-03-01',
  end_date: '2026-03-06',
  status: 'open',
  station_type: 'boiler-room',
  station_type_label: 'Boiler Room',
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
  station_type: 'boiler-room',
  station_type_label: 'Boiler Room',
}

const PERIOD_CLOSED = {
  id: 'per-3',
  name: 'Periode Februari 2026',
  start_date: '2026-02-01',
  end_date: '2026-02-28',
  status: 'closed',
  station_type: 'boiler-room',
  station_type_label: 'Boiler Room',
}

const BUSINESS_UNITS = [
  { id: 'bu-1', name: 'Mill Utara' },
  { id: 'bu-2', name: 'Mill Selatan' },
]

const EMPTY_METRIC = { min: null, avg: null, max: null, reading_count: 0 }

function makeMetrics(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    steam_pressure_bar: { min: 18.2, avg: 21.7, max: 26.9, reading_count: 300 },
    steam_temp_c: { min: 240.0, avg: 268.4, max: 301.5, reading_count: 287 },
    water_tds_ppm: { min: 1400, avg: 1980, max: 2600, reading_count: 265 },
    water_ph: { min: 9.8, avg: 10.4, max: 11.2, reading_count: 3 },
    exhaust_gas_temp_c: { min: 180, avg: 214, max: 260, reading_count: 240 },
    feed_water_temp_c: { min: 78.5, avg: 84.2, max: 91.0, reading_count: 198 },
    feed_water_tank_level_percent: { min: 42.0, avg: 61.3, max: 88.0, reading_count: 176 },
    boiler_water_level_percent: { min: 35.5, avg: 52.8, max: 70.1, reading_count: 154 },
    dust_collector_differential_pressure_mmh2o: { min: 12.0, avg: 18.9, max: 27.4, reading_count: 121 },
    ...overrides,
  }
}

const EMPTY_METRICS = {
  steam_pressure_bar: { ...EMPTY_METRIC },
  steam_temp_c: { ...EMPTY_METRIC },
  water_tds_ppm: { ...EMPTY_METRIC },
  water_ph: { ...EMPTY_METRIC },
  exhaust_gas_temp_c: { ...EMPTY_METRIC },
  feed_water_temp_c: { ...EMPTY_METRIC },
  feed_water_tank_level_percent: { ...EMPTY_METRIC },
  boiler_water_level_percent: { ...EMPTY_METRIC },
  dust_collector_differential_pressure_mmh2o: { ...EMPTY_METRIC },
}

const DAILY_ROWS = [
  {
    date: '2026-03-01',
    filled_slots: 4,
    steam_pressure_avg: 42.1,
    steam_temp_avg: 268.0,
    water_tds_avg: 1900,
    water_ph_avg: null,
    exhaust_gas_temp_avg: null,
    blowdown_executed: 1,
    sootblowing_executed: 0,
  },
  {
    date: '2026-03-02',
    filled_slots: 3,
    steam_pressure_avg: null,
    steam_temp_avg: null,
    water_tds_avg: null,
    water_ph_avg: null,
    exhaust_gas_temp_avg: null,
    blowdown_executed: 0,
    sootblowing_executed: 0,
  },
  {
    date: '2026-03-06',
    filled_slots: 5,
    steam_pressure_avg: 19.4,
    steam_temp_avg: 251.0,
    water_tds_avg: 1750,
    water_ph_avg: 10.4,
    exhaust_gas_temp_avg: 205.0,
    blowdown_executed: 2,
    sootblowing_executed: 1,
  },
]

const BY_UNIT_ROWS = [
  {
    boiler_room_id: 'BLR-1',
    reading_count: 301,
    steam_pressure_avg: 22.0,
    steam_temp_avg: 270.0,
    water_tds_avg: 2000,
    water_ph_avg: 10.5,
    blowdown_executed: 4,
    sootblowing_executed: 1,
  },
  {
    boiler_room_id: 'BLR-2',
    reading_count: 0,
    steam_pressure_avg: null,
    steam_temp_avg: null,
    water_tds_avg: null,
    water_ph_avg: null,
    blowdown_executed: 0,
    sootblowing_executed: 0,
  },
]

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
      coverage_percent: 6.3,
      boiler_unit_count: 2,
      slots_per_unit_per_day: 24,
      days_in_period: 6,
    },
    metrics: makeMetrics(),
    maintenance: {
      blowdown: { executed: 5, not_executed: 2, not_recorded: 41, avg_per_day: 0.83, all_unrecorded: false },
      sootblowing: { executed: 0, not_executed: 0, not_recorded: 48, avg_per_day: 0, all_unrecorded: true },
    },
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
    boiler_unit_count: 1,
    slots_per_unit_per_day: 24,
    days_in_period: 30,
  },
  metrics: { ...EMPTY_METRICS },
  maintenance: {
    blowdown: { executed: 0, not_executed: 0, not_recorded: 0, avg_per_day: 0, all_unrecorded: false },
    sootblowing: { executed: 0, not_executed: 0, not_recorded: 0, avg_per_day: 0, all_unrecorded: false },
  },
  daily: [],
  by_unit: [],
  total: { days_with_records: 0, reading_rows: 0 },
})

/** Rekap sebulan penuh — kasus yang membuat rekap perlu ditutup. */
const THIRTY_DAYS = Array.from({ length: 30 }, (_, index) => ({
  date: `2026-03-${String(index + 1).padStart(2, '0')}`,
  filled_slots: 4,
  steam_pressure_avg: 20 + index * 0.1,
  steam_temp_avg: 250 + index,
  water_tds_avg: 1800 + index,
  water_ph_avg: 10.2,
  exhaust_gas_temp_avg: 200 + index,
  blowdown_executed: 1,
  sootblowing_executed: 0,
}))

/** Kesembilan kartu metrik, dalam urutan NUMERIC_METRICS server. */
const METRIC_TESTIDS = [
  'steam-pressure',
  'steam-temp',
  'water-tds',
  'water-ph',
  'exhaust-gas-temp',
  'feed-water-temp',
  'feed-water-tank-level',
  'boiler-water-level',
  'dust-collector-dp',
]

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
  const wrapper = mount(LaporanBoilerRoomView)
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

/** Tombol buka/tutup Rekap Harian — TERTUTUP secara bawaan, memakai v-if. */
function recapToggle(wrapper: VueWrapper) {
  return wrapper.get('[data-testid="daily-recap-toggle"]')
}

function recapRows(wrapper: VueWrapper) {
  return wrapper.findAll('[data-testid="daily-recap-row"]')
}

/** Posisi sebuah blok dalam urutan DOM, dibaca dari HTML terender. */
function domPosition(wrapper: VueWrapper, testid: string): number {
  return wrapper.html().indexOf(`data-testid="${testid}"`)
}

/** Argumen `scope` pada panggilan ke-`index`. */
function scopeArgOf(mock: { mock: { calls: unknown[][] } }, index: number, position: number): unknown {
  return mock.mock.calls[index]?.[position]
}


/**
 * PRODUCTION LINE — konteks yang DIPILIH, bukan ikatan akun.
 *
 * Bawaan berkas ini SATU line: mill dengan satu line tidak punya keputusan
 * untuk diminta, line itu berlaku otomatis, dan seluruh test lama tetap
 * berbicara tentang apa yang memang mereka uji (pemetaan respons ke layar)
 * — bukan tentang pemilih line. Test yang memang menguji pemilihnya
 * men-stub DUA line secara eksplisit.
 *
 * Perhatikan: dengan satu line pun `productionLineId` TETAP ikut pada
 * cakupan yang diteruskan ke repo. "Satu line" bukan "tanpa line".
 */
const LINE_1 = { id: 'pl-1', name: 'Line 1', code: 'L1' }
const LINE_2 = { id: 'pl-2', name: 'Line 2', code: 'L2' }
const TWO_LINES = [LINE_1, LINE_2]

beforeEach(() => {
  vi.clearAllMocks()
  // Aturan repo: setiap test yang dapat menyentuh localStorage wajib
  // membersihkannya — jsdom membagi satu objek localStorage per berkas.
  window.localStorage.clear()

  for (const key of Object.keys(routeQuery)) {
    delete routeQuery[key]
  }

  asRole('operator', 'bu-1')

  productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1])

  repoMocks.fetchBusinessUnits.mockResolvedValue(BUSINESS_UNITS)
  repoMocks.fetchPeriods.mockResolvedValue([PERIOD_BR])
  repoMocks.fetchSummary.mockResolvedValue(makeSummary())
  repoMocks.exportCsv.mockResolvedValue(new Blob(['csv'], { type: 'text/csv' }))
  repoMocks.saveCsvFile.mockImplementation(() => {})
})

afterEach(() => {
  vi.restoreAllMocks()
})

/* ================================================================== */
/* 26 test_scenarios — component_test (tech spec screen-137)           */
/* ================================================================== */

describe('LaporanBoilerRoomView — test_scenarios / component_test (tech spec screen-137)', () => {
  // Scenario 1: "berhasil dilihat Operator, Supervisor, dan Mill Management"
  it('berhasil dilihat peran terikat mill — tanpa pemilih Mill, seluruh blok terender apa adanya, tanpa kontrol tulis', async () => {
    for (const role of ['operator', 'supervisor', 'mill_management'] as const) {
      vi.clearAllMocks()
      asRole(role, 'bu-1')
      repoMocks.fetchPeriods.mockResolvedValue([PERIOD_BR])
      repoMocks.fetchSummary.mockResolvedValue(makeSummary())

      const wrapper = await mountView()

      // Pemilih Mill tidak dirender SAMA SEKALI untuk peran terikat mill.
      expect(exists(wrapper, 'mill-select')).toBe(false)
      expect(exists(wrapper, 'mill-current')).toBe(true)
      expect(repoMocks.fetchBusinessUnits).not.toHaveBeenCalled()

      await selectPeriod(wrapper, 'per-1')

      // Nama mill sebagai KETERANGAN, bukan kontrol.
      expect(text(wrapper, 'mill-current')).toContain('Mill Utara')

      // Kelengkapan pencatatan, apa adanya dari server.
      expect(text(wrapper, 'coverage-percent')).toBe('6,3%')
      expect(text(wrapper, 'coverage-slots')).toContain('9 dari 144 slot waktu terisi')

      // Kesembilan metrik dengan terendah/rata-rata/tertinggi BESERTA
      // reading_count masing-masing.
      expect(wrapper.findAll('[data-testid^="metric-"][data-testid$="-avg"]')).toHaveLength(9)
      expect(text(wrapper, 'metric-steam-pressure-avg')).toBe('21,7')
      expect(text(wrapper, 'metric-steam-pressure-min')).toBe('18,2')
      expect(text(wrapper, 'metric-steam-pressure-max')).toBe('26,9')
      expect(text(wrapper, 'metric-steam-pressure-count')).toContain('300')
      expect(text(wrapper, 'metric-water-ph-avg')).toBe('10,4')
      expect(text(wrapper, 'metric-water-ph-count')).toContain('3')

      // Perawatan: dilakukan DAN jumlah yang tidak tercatat.
      expect(text(wrapper, 'maintenance-blowdown-executed')).toBe('5')
      expect(text(wrapper, 'maintenance-blowdown-not-recorded')).toBe('41')
      expect(text(wrapper, 'maintenance-sootblowing-not-recorded')).toBe('48')

      // Tren harian tekanan & suhu uap, rekap per unit, rekap harian.
      expect(exists(wrapper, 'daily-trend-steam-pressure')).toBe(true)
      expect(exists(wrapper, 'daily-trend-steam-temp')).toBe(true)
      expect(exists(wrapper, 'by-unit-card')).toBe(true)
      expect(exists(wrapper, 'daily-recap')).toBe(true)

      // Keterangan wajib: ekstrem mentah ≠ rata-rata harian.
      expect(exists(wrapper, 'raw-extremes-note')).toBe(true)

      // Tidak ada satu pun kontrol tulis.
      expect(wrapper.findAll('input')).toHaveLength(0)
      expect(wrapper.findAll('textarea')).toHaveLength(0)
      expect(wrapper.text()).not.toMatch(/simpan|hapus/i)

      wrapper.unmount()
    }
  })

  // Scenario 2: "berhasil dilihat Admin setelah memilih mill"
  /*
   * DIPULIHKAN 2026-09-28 (tahap 4b) ke bentuk aslinya: Admin memilih mill,
   * memilih line, dan MELIHAT ANGKA.
   *
   * Bentuk sementara sebelumnya ("nol angka, karena tidak ada jalan") lahir
   * dari satu keterbatasan backend, bukan dari keputusan produk: satu-satunya
   * endpoint mobile yang mendaftar Production Line saat itu
   * (GET /api/production-lines/current) bersifat SWA-CAKUP — ia memulangkan
   * line milik mill AKUN PEMANGGIL, dan Admin tidak terikat mill. Endpoint
   * baru GET /api/production-lines/options-for-report?business_unit_id=
   * menutup lubang itu, jadi Admin kembali mendapat pemilih line yang
   * sesungguhnya seperti peran lain.
   *
   * Asersi di bawah lebih kuat daripada bentuk aslinya MAUPUN daripada
   * bentuk sementara itu: pemilih Mill tetap utuh, daftar line diminta
   * DENGAN business_unit_id mill terpilih (bukan swa-cakup), mengganti mill
   * memuat ulang periode DAN daftar line mill baru, dan angkanya menyertai
   * satu line tertentu.
   */
  it('Admin — pemilih Mill dirender, memilih mill memuat daftar line + periode, lalu seluruh angka tampil', async () => {
    asRole('admin', null)

    const wrapper = await mountView()

    expect(exists(wrapper, 'mill-select')).toBe(true)
    expect(wrapper.findAll('[data-testid="mill-select"] option')).toHaveLength(3) // placeholder + 2
    expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()
    // Tanpa mill terpilih belum ada daftar line yang dapat diminta.
    expect(productionLineMocks.fetchProductionLinesForReport).not.toHaveBeenCalled()

    await selectMill(wrapper, 'bu-1')

    // Daftar line diminta UNTUK MILL TERPILIH.
    expect(productionLineMocks.fetchProductionLinesForReport).toHaveBeenCalledTimes(1)
    expect(productionLineMocks.fetchProductionLinesForReport).toHaveBeenCalledWith('bu-1')

    expect(repoMocks.fetchPeriods).toHaveBeenCalledTimes(1)
    expect(scopeArgOf(repoMocks.fetchPeriods, 0, 0)).toEqual({ isAdmin: true, businessUnitId: 'bu-1', productionLineId: 'pl-1' })
    expect(wrapper.findAll('[data-testid="period-select"] option')).toHaveLength(2)

    await selectPeriod(wrapper, 'per-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('per-1', { isAdmin: true, businessUnitId: 'bu-1', productionLineId: 'pl-1' })
    expect(text(wrapper, 'coverage-percent')).toBe('6,3%')
    expect(text(wrapper, 'metric-steam-pressure-avg')).toBe('21,7')
    expect(text(wrapper, 'metric-steam-pressure-count')).toContain('300')
    expect(text(wrapper, 'maintenance-blowdown-executed')).toBe('5')
    expect(exists(wrapper, 'daily-trend-steam-pressure')).toBe(true)
    expect(exists(wrapper, 'by-unit-card')).toBe(true)
    expect(exists(wrapper, 'daily-recap')).toBe(true)
    // Admin memilih mill sendiri — tidak ada keterangan mill akun.
    expect(exists(wrapper, 'mill-current')).toBe(false)
    // Dan tidak ada lagi pesan buntu "pakai versi web".
    expect(exists(wrapper, 'production-line-unavailable')).toBe(false)

    // Mengganti mill memuat ulang daftar periode DAN daftar line mill baru,
    // lalu membuang angka lama.
    repoMocks.fetchPeriods.mockResolvedValue([PERIOD_LINTAS_STASIUN])
    await selectMill(wrapper, 'bu-2')

    expect(productionLineMocks.fetchProductionLinesForReport).toHaveBeenCalledTimes(2)
    expect(productionLineMocks.fetchProductionLinesForReport).toHaveBeenLastCalledWith('bu-2')
    expect(repoMocks.fetchPeriods).toHaveBeenCalledTimes(2)
    expect(scopeArgOf(repoMocks.fetchPeriods, 1, 0)).toEqual({ isAdmin: true, businessUnitId: 'bu-2', productionLineId: 'pl-1' })
    expect(exists(wrapper, 'coverage-card')).toBe(false)
    expect(exists(wrapper, 'metric-cards')).toBe(false)
    // Kembali ke placeholder. Dibaca lewat option:checked, bukan
    // toHaveValue(''): opsi placeholder memakai :value="null", sehingga
    // nilai DOM-nya adalah teks opsi itu sendiri.
    expect(wrapper.get('[data-testid="period-select"] option:checked').text()).toBe('Pilih Periode')
  })

  // Scenario 3: "Operator membuka laporan"
  it('Operator — laporan penuh seperti peran lain, tanpa pemilih Mill, dan fetchBusinessUnits TIDAK pernah dipanggil', async () => {
    asRole('operator', 'bu-1')

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Sama penuhnya dengan peran lain — tidak ada bagian yang dipangkas.
    expect(text(wrapper, 'coverage-percent')).toBe('6,3%')
    expect(wrapper.findAll('[data-testid^="metric-"][data-testid$="-avg"]')).toHaveLength(9)
    expect(exists(wrapper, 'maintenance-card')).toBe(true)
    expect(exists(wrapper, 'daily-trend-steam-pressure')).toBe(true)
    expect(exists(wrapper, 'daily-trend-steam-temp')).toBe(true)
    expect(exists(wrapper, 'by-unit-card')).toBe(true)
    expect(exists(wrapper, 'daily-recap')).toBe(true)

    // Mill tidak dapat diganti dari layar, dan daftar seluruh mill TIDAK
    // pernah diminta — permintaan itu akan membentuk daftar mill yang
    // memang tidak berhak dilihat.
    expect(exists(wrapper, 'mill-select')).toBe(false)
    expect(repoMocks.fetchBusinessUnits).not.toHaveBeenCalled()
    expect(text(wrapper, 'mill-current')).toContain('Mill Utara')

    // Setiap pemanggilan repo menyatakan isAdmin: false — dan scopeParams
    // yang menerjemahkannya menjadi params KOSONG dikunci baris demi baris
    // pada tests/boilerRoomReportRepo.spec.ts (unit_test_case 2).
    expect(scopeArgOf(repoMocks.fetchPeriods, 0, 0)).toEqual({ isAdmin: false, businessUnitId: null, productionLineId: 'pl-1' })
    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('per-1', { isAdmin: false, businessUnitId: null, productionLineId: 'pl-1' })
  })

  // Scenario 4: "Admin memilih mill lebih dulu"
  it('Admin belum memilih mill — arahan memilih mill, tanpa satu pun blok angka, dan fetchSummary tidak dipanggil', async () => {
    asRole('admin', null)

    const wrapper = await mountView()

    expect(exists(wrapper, 'mill-select')).toBe(true)
    expect(exists(wrapper, 'mill-required-hint')).toBe(true)
    expect(text(wrapper, 'mill-required-hint')).toContain('Pilih mill')

    expect(exists(wrapper, 'coverage-card')).toBe(false)
    expect(exists(wrapper, 'metric-cards')).toBe(false)
    expect(exists(wrapper, 'maintenance-card')).toBe(false)
    expect(exists(wrapper, 'daily-trend-steam-pressure')).toBe(false)
    expect(exists(wrapper, 'by-unit-card')).toBe(false)
    expect(exists(wrapper, 'daily-recap')).toBe(false)
    expect(exists(wrapper, 'period-meta')).toBe(false)

    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
    expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()
    // Hanya opsi mill yang boleh terpanggil.
    expect(repoMocks.fetchBusinessUnits).toHaveBeenCalledTimes(1)
  })

  // Scenario 5: "mill belum punya periode"
  it('mill belum punya periode — pemilih Periode kosong, arahan hubungi Admin, dan itu BUKAN galat', async () => {
    asRole('supervisor', 'bu-1')
    repoMocks.fetchPeriods.mockResolvedValue([])

    const wrapper = await mountView()

    // Pemilih tetap dirender, hanya kosong — pemilih yang lenyap dan
    // pemilih yang kosong menceritakan hal berbeda kepada pengguna.
    expect(exists(wrapper, 'period-select')).toBe(true)
    expect(wrapper.findAll('[data-testid="period-select"] option')).toHaveLength(1) // hanya placeholder
    expect(exists(wrapper, 'no-periods')).toBe(true)
    expect(text(wrapper, 'no-periods')).toContain('hubungi Admin')

    expect(exists(wrapper, 'coverage-card')).toBe(false)
    expect(exists(wrapper, 'metric-cards')).toBe(false)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

    // Daftar kosong adalah jawaban yang SAH, bukan galat.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)
  })

  // Scenario 6: "periode tanpa data"
  it('periode tanpa data — keterangan belum ada data, seluruh metrik "-" BUKAN "0", dan tanpa grafik kosong', async () => {
    repoMocks.fetchSummary.mockResolvedValue(EMPTY_SUMMARY)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'empty-period')).toBe(true)
    expect(text(wrapper, 'empty-period')).toContain('Belum ada data')

    // NULL BUKAN NOL — dan itu terlihat di layar. Kesembilan kartu.
    for (const testid of METRIC_TESTIDS) {
      expect(text(wrapper, `metric-${testid}-avg`)).toBe('-')
      expect(text(wrapper, `metric-${testid}-avg`)).not.toBe('0')
      expect(text(wrapper, `metric-${testid}-avg`)).not.toBe('0,0')
      expect(text(wrapper, `metric-${testid}-min`)).toBe('-')
      expect(text(wrapper, `metric-${testid}-max`)).toBe('-')
      expect(text(wrapper, `metric-${testid}-count`)).toContain('0 pembacaan')
    }

    // Grafik TIDAK dirender sama sekali: garis datar dari data kosong akan
    // terbaca sebagai hasil pengukuran.
    expect(exists(wrapper, 'daily-trend-steam-pressure')).toBe(false)
    expect(exists(wrapper, 'daily-trend-steam-temp')).toBe(false)
    expect(exists(wrapper, 'daily-recap')).toBe(false)
    expect(exists(wrapper, 'by-unit-card')).toBe(false)

    // Kelengkapan pencatatan tetap dibacakan — 0 slot dari 720.
    expect(text(wrapper, 'coverage-slots')).toContain('0 dari 720 slot waktu terisi')
  })

  // Scenario 7: "sebuah metrik tidak pernah diisi"
  it('satu metrik tidak pernah diisi — pH air "-" dengan 0 pembacaan, metrik lain sama sekali tidak terpengaruh', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({ metrics: makeMetrics({ water_ph: { ...EMPTY_METRIC } }) }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // "Tidak pernah diukur", BUKAN "nilainya nol".
    expect(text(wrapper, 'metric-water-ph-avg')).toBe('-')
    expect(text(wrapper, 'metric-water-ph-avg')).not.toBe('0,0')
    expect(text(wrapper, 'metric-water-ph-min')).toBe('-')
    expect(text(wrapper, 'metric-water-ph-max')).toBe('-')
    expect(text(wrapper, 'metric-water-ph-count')).toContain('0 pembacaan')

    // Kartu tekanan uap tetap merender angkanya sendiri beserta
    // reading_count-nya sendiri.
    expect(text(wrapper, 'metric-steam-pressure-avg')).toBe('21,7')
    expect(text(wrapper, 'metric-steam-pressure-min')).toBe('18,2')
    expect(text(wrapper, 'metric-steam-pressure-max')).toBe('26,9')
    expect(text(wrapper, 'metric-steam-pressure-count')).toContain('300')
    expect(text(wrapper, 'metric-steam-temp-avg')).toBe('268,4')
  })

  // Scenario 8: "perawatan tidak tercatat"
  it('perawatan tidak tercatat — "tidak tercatat" berdiri sendiri, dan all_unrecorded dibedakan dari nol', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Yang dibacakan sebagai "dilakukan" hanyalah 5.
    expect(text(wrapper, 'maintenance-blowdown-executed')).toBe('5')
    expect(text(wrapper, 'maintenance-blowdown-not-executed')).toBe('2')
    // 41 dirender TERPISAH dan tidak pernah dijumlahkan ke not_executed
    // (2 + 41 = 43 tidak boleh muncul di mana pun pada kartu ini).
    expect(text(wrapper, 'maintenance-blowdown-not-recorded')).toBe('41')
    expect(text(wrapper, 'maintenance-blowdown-not-executed')).not.toBe('43')
    expect(text(wrapper, 'maintenance-card')).not.toContain('43')

    // sootblowing: nol yang berarti "tidak pernah DICATAT".
    expect(text(wrapper, 'maintenance-sootblowing-executed')).toBe('0')
    expect(text(wrapper, 'maintenance-sootblowing-not-recorded')).toBe('48')
    expect(exists(wrapper, 'maintenance-sootblowing-all-unrecorded')).toBe(true)
    expect(text(wrapper, 'maintenance-sootblowing-all-unrecorded')).toContain('TIDAK TERCATAT')
    // blowdown all_unrecorded false — keterangannya TIDAK dirender.
    expect(exists(wrapper, 'maintenance-blowdown-all-unrecorded')).toBe(false)

    // avg_per_day apa adanya dari server (0,83), bukan 5 / 6.
    expect(text(wrapper, 'maintenance-avg-per-day')).toContain('0,8')
  })

  // Scenario 9: "pencatatan sangat tidak lengkap"
  it('pencatatan sangat tidak lengkap — kartu kelengkapan mendahului kartu metrik dalam urutan DOM', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        coverage: {
          filled_slots: 9,
          expected_slots: 720,
          coverage_percent: 1.3,
          boiler_unit_count: 1,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // DI ATAS SEGALANYA — bukan catatan kaki yang baru terbaca setelah
    // pembacanya terlanjur mempercayai angkanya.
    const coveragePosition = domPosition(wrapper, 'coverage-card')
    expect(coveragePosition).toBeGreaterThan(-1)
    expect(coveragePosition).toBeLessThan(domPosition(wrapper, 'metric-cards'))
    expect(coveragePosition).toBeLessThan(domPosition(wrapper, 'maintenance-card'))
    expect(coveragePosition).toBeLessThan(domPosition(wrapper, 'daily-trend-steam-pressure'))
    expect(coveragePosition).toBeLessThan(domPosition(wrapper, 'by-unit-card'))

    // coverage_percent dirender dari payload, TIDAK dihitung ulang
    // (100 × 9 / 720 = 1,25).
    expect(text(wrapper, 'coverage-percent')).toBe('1,3%')
    expect(text(wrapper, 'coverage-percent')).not.toBe('1,25%')
    expect(text(wrapper, 'coverage-slots')).toContain('9 dari 720 slot waktu terisi')

    // Angka metrik tetap dirender apa adanya.
    expect(text(wrapper, 'metric-steam-pressure-avg')).toBe('21,7')
  })

  // Scenario 10: "mill punya beberapa unit boiler"
  it('beberapa unit boiler — angka periode dari blok metrics, seluruh unit terender, tabel menggulir di dalam kartunya', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        by_unit: [
          ...BY_UNIT_ROWS,
          {
            boiler_room_id: 'BLR-3',
            reading_count: 88,
            steam_pressure_avg: 24.5,
            steam_temp_avg: 280.0,
            water_tds_avg: 2100,
            water_ph_avg: 10.9,
            blowdown_executed: 1,
            sootblowing_executed: 0,
          },
        ],
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Angka periode dari blok metrics (gabungan seluruh unit), BUKAN
    // dihitung ulang dari by_unit: 22,0 / null / 24,5 tidak menghasilkan
    // 21,7.
    expect(text(wrapper, 'metric-steam-pressure-avg')).toBe('21,7')
    expect(text(wrapper, 'metric-steam-pressure-avg')).not.toBe('23,3')

    const rows = wrapper.findAll('[data-testid="by-unit-row"]')
    expect(rows).toHaveLength(3)
    expect(rows.map((row) => row.text())).toEqual([
      expect.stringContaining('BLR-1'),
      expect.stringContaining('BLR-2'),
      expect.stringContaining('BLR-3'),
    ])
    // Unit tanpa pembacaan TIDAK disaring keluar, dan rata-ratanya "-".
    expect(rows[1].text()).toContain('BLR-2')
    expect(rows[1].text()).toContain('-')
    expect(rows[1].text()).toContain('0')

    // Tabel per unit dibungkus kontainer yang menggulir sendiri (bukti
    // pikselnya ada di browser test).
    expect(
      wrapper.find('[data-testid="by-unit-card"] .detail-table-wrap').exists(),
    ).toBe(true)
  })

  // Scenario 11: "akun belum terhubung ke mill"
  it('akun belum terhubung ke mill — pesan hubungi Admin, tanpa pemilih Mill, dan NOL pemanggilan repo', async () => {
    for (const role of ['operator', 'supervisor', 'mill_management'] as const) {
      vi.clearAllMocks()
      asRole(role, null)

      const wrapper = await mountView()

      expect(exists(wrapper, 'no-mill-for-account')).toBe(true)
      expect(text(wrapper, 'no-mill-for-account')).toContain('hubungi Admin')
      // Daftar seluruh mill TIDAK ditawarkan sebagai penggantinya.
      expect(exists(wrapper, 'mill-select')).toBe(false)
      expect(exists(wrapper, 'coverage-card')).toBe(false)
      expect(exists(wrapper, 'metric-cards')).toBe(false)

      // Berhenti total: tidak ada satu pun permintaan HTTP.
      expect(repoMocks.fetchBusinessUnits).not.toHaveBeenCalled()
      expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()
      expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

      wrapper.unmount()
    }
  })

  // Scenario 12: "mencoba melihat mill lain"
  it('mencoba melihat mill lain — tidak satu pun pemanggilan repo menyertakan mill, dan layar tidak menyediakan jalannya', async () => {
    asRole('operator', 'bu-1')

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Setiap pemuatan menyatakan isAdmin: false — dan repo menjadikannya
    // params KOSONG (dikunci pada boilerRoomReportRepo.spec.ts).
    expect(repoMocks.fetchPeriods.mock.calls.length).toBeGreaterThan(0)
    for (const call of repoMocks.fetchPeriods.mock.calls) {
      expect(call[0]).toEqual({ isAdmin: false, businessUnitId: null, productionLineId: 'pl-1' })
    }
    expect(repoMocks.fetchSummary.mock.calls.length).toBeGreaterThan(0)
    for (const call of repoMocks.fetchSummary.mock.calls) {
      expect(call[1]).toEqual({ isAdmin: false, businessUnitId: null, productionLineId: 'pl-1' })
    }

    // Layar tidak menyediakan kontrol apa pun untuk berpindah mill.
    expect(exists(wrapper, 'mill-select')).toBe(false)
    expect(wrapper.findAll('select')).toHaveLength(1) // hanya pemilih Periode
    expect(text(wrapper, 'mill-current')).toContain('Mill Utara')
    expect(text(wrapper, 'coverage-percent')).toBe('6,3%')
  })

  // Scenario 13: "jaringan gagal"
  it('jaringan gagal — pesan + Coba Lagi, periode terpilih BERTAHAN, dan coba lagi memuat period_id yang SAMA', async () => {
    const wrapper = await mountView()
    repoMocks.fetchSummary.mockRejectedValueOnce(NETWORK_ERROR)

    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'network-error')).toBe(true)
    expect(text(wrapper, 'network-error')).toContain('Tidak dapat terhubung ke server')
    expect(exists(wrapper, 'retry-button')).toBe(true)
    // Pengguna di area tanpa sinyal TIDAK dipaksa memilih periode dari awal.
    expect(selectValue(wrapper, 'period-select')).toBe('per-1')
    expect(exists(wrapper, 'coverage-card')).toBe(false)
    expect(exists(wrapper, 'metric-cards')).toBe(false)
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
    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('per-1', { isAdmin: false, businessUnitId: null, productionLineId: 'pl-1' })
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(text(wrapper, 'metric-steam-pressure-avg')).toBe('21,7')
    expect(selectValue(wrapper, 'period-select')).toBe('per-1')
  })

  // Scenario 14: "sesi berakhir"
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
    expect(exists(wrapper, 'metric-cards')).toBe(false)
    expect(exists(wrapper, 'period-meta')).toBe(false)
  })

  // Scenario 15: "periode tertutup"
  it('periode tertutup — laporan tetap penuh, penanda Tertutup terender (label sama dengan web), tombol Ekspor tetap aktif', async () => {
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

    expect(text(wrapper, 'period-status-badge')).toBe('Tertutup')
    expect(text(wrapper, 'coverage-percent')).toBe('6,3%')
    expect(wrapper.findAll('[data-testid^="metric-"][data-testid$="-avg"]')).toHaveLength(9)
    expect(exists(wrapper, 'maintenance-card')).toBe(true)
    expect(exists(wrapper, 'daily-trend-steam-pressure')).toBe(true)
    expect(exists(wrapper, 'by-unit-card')).toBe(true)
    expect(exists(wrapper, 'daily-recap')).toBe(true)

    // Kunci periode mengatur PENULISAN data, bukan pembacaan laporan.
    const exportButton = wrapper.get('[data-testid="export-button"]')
    expect(exportButton.attributes('disabled')).toBeUndefined()
    expect(exportButton.attributes('aria-disabled')).toBeUndefined()

    await exportButton.trigger('click')
    await flushPromises()
    expect(repoMocks.exportCsv).toHaveBeenCalledWith('per-3', {
      isAdmin: false,
      businessUnitId: null,
      productionLineId: 'pl-1',
    })
  })

  // Scenario 16: "rekap harian dapat ditutup agar layar ponsel tetap terbaca"
  it('rekap harian dibuka-tutup — barisnya hilang dari DOM (v-if), sisanya tetap, dan fetchSummary TEPAT 1×', async () => {
    repoMocks.fetchSummary.mockResolvedValue(makeSummary({ daily: THIRTY_DAYS }))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)

    // TERTUTUP secara bawaan, dan memakai v-if: barisnya benar-benar tidak
    // ada di DOM — bukan sekadar disembunyikan.
    expect(exists(wrapper, 'daily-recap')).toBe(true)
    expect(recapRows(wrapper)).toHaveLength(0)
    expect(recapToggle(wrapper).attributes('aria-expanded')).toBe('false')
    expect(recapToggle(wrapper).text()).toContain('Rekap Harian (30 hari)')

    // Kartu kelengkapan, kartu metrik, dan grafik tren tetap dirender saat
    // rekap tertutup.
    expect(text(wrapper, 'coverage-percent')).toBe('6,3%')
    expect(exists(wrapper, 'metric-cards')).toBe(true)
    expect(exists(wrapper, 'daily-trend-steam-pressure')).toBe(true)
    expect(exists(wrapper, 'daily-trend-steam-temp')).toBe(true)

    // Dibuka: seluruh 30 baris kembali utuh.
    await recapToggle(wrapper).trigger('click')
    expect(recapToggle(wrapper).attributes('aria-expanded')).toBe('true')
    expect(recapRows(wrapper)).toHaveLength(30)
    expect(recapRows(wrapper)[0].text()).toContain('01 Mar 2026')
    expect(recapRows(wrapper)[29].text()).toContain('30 Mar 2026')
    expect(text(wrapper, 'daily-recap-total')).toContain('412')

    // Ditutup lagi, lalu dibuka lagi.
    await recapToggle(wrapper).trigger('click')
    expect(recapRows(wrapper)).toHaveLength(0)
    await recapToggle(wrapper).trigger('click')
    expect(recapRows(wrapper)).toHaveLength(30)

    // ASERSI INTINYA: membuka/menutup murni penyingkapan atas data yang
    // sudah ada di memori — NOL permintaan jaringan baru. Dihitung dari
    // pemanggilan mock, bukan dari DOM.
    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchPeriods).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchBusinessUnits).not.toHaveBeenCalled()
  })

  // Scenario 17: "layar hanya membaca, tanpa aksi tulis"
  it('layar hanya membaca — tanpa input/form/tombol tulis, dan repo hanya memaparkan fungsi baca + ekspor', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await recapToggle(wrapper).trigger('click')

    // Tidak ada elemen form tulis sama sekali.
    expect(wrapper.findAll('input')).toHaveLength(0)
    expect(wrapper.findAll('textarea')).toHaveLength(0)
    expect(wrapper.findAll('form')).toHaveLength(0)

    // Satu-satunya <select> adalah pemilih Periode (peran terikat mill).
    expect(wrapper.findAll('select')).toHaveLength(1)
    expect(exists(wrapper, 'period-select')).toBe(true)

    // Tidak ada tombol simpan/ubah/hapus di seluruh pohon komponen.
    const buttonLabels = wrapper.findAll('button').map((button) => button.text().toLowerCase())
    for (const label of buttonLabels) {
      expect(label).not.toMatch(/simpan|hapus|edit|ubah data|tambah/)
    }
    expect(wrapper.text()).not.toMatch(/simpan|hapus/i)

    // Repo yang diimpor komponen: empat fungsi baca + penyimpan berkas,
    // tanpa satu pun fungsi tulis.
    expect(Object.keys(repoMocks).sort()).toEqual([
      'exportCsv',
      'fetchBusinessUnits',
      'fetchPeriods',
      'fetchSummary',
      'saveCsvFile',
    ])

    // Kontrol yang memang ada.
    expect(exists(wrapper, 'export-button')).toBe(true)
    expect(exists(wrapper, 'back-button')).toBe(true)
    expect(exists(wrapper, 'daily-recap-toggle')).toBe(true)
  })

  // Scenario 18: "angka ponsel sama persis dengan laporan versi web"
  it('angka sama dengan laporan web — setiap nilai apa adanya dari payload, tanpa penjumlahan/perataan/pembulatan', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await recapToggle(wrapper).trigger('click')

    // coverage_percent apa adanya (6,3), BUKAN 100 × 9 / 144 = 6,25.
    expect(text(wrapper, 'coverage-percent')).toBe('6,3%')
    expect(text(wrapper, 'coverage-percent')).not.toBe('6,25%')
    expect(text(wrapper, 'coverage-percent')).not.toBe('6,3')

    // Rata-rata metrik apa adanya, bukan (min + max) / 2 = 22,55 dan bukan
    // rata-rata kolom daily.
    expect(text(wrapper, 'metric-steam-pressure-avg')).toBe('21,7')
    expect(text(wrapper, 'metric-steam-pressure-avg')).not.toBe('22,6')
    expect(text(wrapper, 'metric-steam-pressure-avg')).not.toBe('30,8')
    // Ekstrem dari PEMBACAAN MENTAH: 26,9 walau rata-rata harian tertinggi
    // 42,1 — dan layar mengatakan keduanya tidak dapat direkonsiliasi.
    expect(text(wrapper, 'metric-steam-pressure-max')).toBe('26,9')
    expect(text(wrapper, 'metric-steam-pressure-max')).not.toBe('42,1')
    expect(text(wrapper, 'raw-extremes-note')).toContain('pembacaan mentah')
    expect(text(wrapper, 'raw-extremes-note')).toContain('tidak dapat dicocokkan')

    // Perawatan apa adanya — bukan jumlah kolom daily (3) atau by_unit (4).
    expect(text(wrapper, 'maintenance-blowdown-executed')).toBe('5')

    // Blok total apa adanya (412 / 11 hari), meski bertentangan dengan
    // panjang daily (3 baris).
    expect(text(wrapper, 'daily-recap-total')).toContain('412')
    expect(text(wrapper, 'daily-recap-total')).toContain('11')
    expect(recapRows(wrapper)).toHaveLength(3)
  })

  // Scenario 19: "periode yang tidak mencakup Boiler Room tidak ditawarkan"
  it('daftar periode — tepat sebanyak dan seurut entri dari server, tanpa penyaringan station_type di klien', async () => {
    repoMocks.fetchPeriods.mockResolvedValue([PERIOD_BR, PERIOD_LINTAS_STASIUN])

    const wrapper = await mountView()

    const options = wrapper.findAll('[data-testid="period-select"] option')
    expect(options).toHaveLength(3) // placeholder + 2

    const labels = options.slice(1).map((option) => option.text())
    expect(labels).toEqual([
      'Boiler Room — Periode Maret 2026',
      'Boiler Room — Periode Lintas Stasiun Maret 2026',
    ])
    // Layar tidak menambahkan dan tidak membuang apa pun dari daftar yang
    // diterimanya — penyaringan cakupan adalah pekerjaan server.
    expect(labels.join(' ')).not.toContain('Sterilizer')
    expect(options[1].attributes('value')).toBe('per-1')
    expect(options[2].attributes('value')).toBe('per-2')
  })

  // Scenario 20: "rentang periode inklusif di kedua ujung"
  it('rentang inklusif — baris tanggal mulai dan tanggal akhir terender pada tren dan rekap harian', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Tren harian memuat kedua ujung rentang (sumbu memakai tanggalnya saja).
    const trend = text(wrapper, 'daily-trend-steam-pressure')
    expect(trend).toContain('01')
    expect(trend).toContain('06')

    await recapToggle(wrapper).trigger('click')

    const rows = recapRows(wrapper)
    expect(rows).toHaveLength(3)
    expect(rows[0].text()).toContain('01 Mar 2026')
    expect(rows[2].text()).toContain('06 Mar 2026')

    // Ujung rentang tidak dipangkas dan tidak ada tanggal di luar rentang.
    const recap = text(wrapper, 'daily-recap')
    expect(recap).not.toContain('28 Feb 2026')
    expect(recap).not.toContain('07 Mar 2026')

    // Baris ujung ikut membentuk angka utama — reading_count metrik
    // (300) tetap nilai server, tidak dipotong oleh komponen.
    expect(text(wrapper, 'metric-steam-pressure-count')).toContain('300')
  })

  // Scenario 21: "setiap metrik punya penyebutnya sendiri"
  it('setiap metrik punya penyebutnya sendiri — 300 dan 3 berdampingan dengan angkanya, tanpa penyebut bersama', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'metric-steam-pressure-avg')).toBe('21,7')
    expect(text(wrapper, 'metric-steam-pressure-count')).toContain('300')
    expect(text(wrapper, 'metric-water-ph-avg')).toBe('10,4')
    expect(text(wrapper, 'metric-water-ph-count')).toContain('3')

    // Penyebutnya berbeda-beda: tidak ada satu penyebut bersama yang
    // dipakai ulang di layar, dan bukan pula total.reading_rows (412).
    const counts = METRIC_TESTIDS.map((testid) => text(wrapper, `metric-${testid}-count`))
    expect(new Set(counts).size).toBe(9)
    for (const count of counts) {
      expect(count).not.toContain('412')
    }

    // Rata-rata metrik yang jarang diisi tidak mengempis oleh penyebut
    // metrik lain.
    expect(text(wrapper, 'metric-water-ph-avg')).not.toBe('0,0')
  })

  // Scenario 22: "jumlah pembacaan ditampilkan berdampingan dengan angkanya"
  it('kesembilan kartu metrik merender terendah/rata-rata/tertinggi BESERTA reading_count-nya sendiri', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(wrapper.findAll('[data-testid="metric-cards"] .metric-card')).toHaveLength(9)

    const expectedAvg: Record<string, string> = {
      'steam-pressure': '21,7',
      'steam-temp': '268,4',
      'water-tds': '1.980',
      'water-ph': '10,4',
      'exhaust-gas-temp': '214',
      'feed-water-temp': '84,2',
      'feed-water-tank-level': '61,3',
      'boiler-water-level': '52,8',
      'dust-collector-dp': '18,9',
    }
    const expectedCount: Record<string, string> = {
      'steam-pressure': '300',
      'steam-temp': '287',
      'water-tds': '265',
      'water-ph': '3',
      'exhaust-gas-temp': '240',
      'feed-water-temp': '198',
      'feed-water-tank-level': '176',
      'boiler-water-level': '154',
      'dust-collector-dp': '121',
    }

    // Tidak ada kartu yang merender angka TANPA penyebutnya.
    for (const testid of METRIC_TESTIDS) {
      expect(exists(wrapper, `metric-${testid}`)).toBe(true)
      expect(text(wrapper, `metric-${testid}-avg`)).toBe(expectedAvg[testid])
      expect(text(wrapper, `metric-${testid}-count`)).toContain(expectedCount[testid])
      expect(exists(wrapper, `metric-${testid}-min`)).toBe(true)
      expect(exists(wrapper, `metric-${testid}-max`)).toBe(true)
    }
  })

  // Scenario 23: "laju bahan bakar dan beban fan tidak pernah dirata-rata"
  it('laju bahan bakar dan beban ID/SA fan — tidak ada kartu metrik maupun seri grafik untuk ketiganya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Kartu metriknya tepat sembilan, dan tidak satu pun milik ketiga kolom
    // teks bebas itu (satuannya bercampur — merata-ratakannya mustahil,
    // bukan sekadar tidak diinginkan).
    expect(wrapper.findAll('[data-testid="metric-cards"] .metric-card')).toHaveLength(9)
    expect(exists(wrapper, 'metric-fuel-rate')).toBe(false)
    expect(exists(wrapper, 'metric-id-fan-load')).toBe(false)
    expect(exists(wrapper, 'metric-sa-fan-load')).toBe(false)

    const screen = wrapper.text()
    expect(screen).not.toMatch(/bahan bakar/i)
    expect(screen).not.toMatch(/id fan/i)
    expect(screen).not.toMatch(/sa fan/i)

    // Tidak ada seri grafik untuk ketiganya: hanya tekanan uap dan suhu uap.
    expect(exists(wrapper, 'daily-trend-steam-pressure')).toBe(true)
    expect(exists(wrapper, 'daily-trend-steam-temp')).toBe(true)
    expect(wrapper.findAll('[data-testid^="daily-trend-"]')).toHaveLength(2)
  })

  // Scenario 24: "tidak ada penandaan nilai di luar batas"
  it('nilai menyimpang jauh — tidak ada kelas penanda, ikon peringatan, atau pewarnaan bersyarat', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        metrics: makeMetrics({
          steam_pressure_bar: { min: 0.4, avg: 21.7, max: 98.6, reading_count: 300 },
          water_ph: { min: 2.1, avg: 10.4, max: 14.0, reading_count: 3 },
        }),
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Nilai menyimpang tetap dirender apa adanya.
    expect(text(wrapper, 'metric-steam-pressure-min')).toBe('0,4')
    expect(text(wrapper, 'metric-steam-pressure-max')).toBe('98,6')
    expect(text(wrapper, 'metric-water-ph-max')).toBe('14,0')

    // Tidak ada satu pun kelas penanda ambang pada kartu metrik maupun
    // pada tabel — Boiler Room tidak punya master target operasional.
    const markup = wrapper.find('[data-testid="metric-cards"]').html()
    expect(markup).not.toMatch(/danger|warning|alert|critical|out-of-range|over-limit|threshold|safe/i)
    expect(wrapper.findAll('[data-testid="metric-cards"] svg')).toHaveLength(0)
    expect(wrapper.findAll('[data-testid="by-unit-card"] svg')).toHaveLength(0)

    const byUnitMarkup = wrapper.find('[data-testid="by-unit-card"]').html()
    expect(byUnitMarkup).not.toMatch(/danger|warning|alert|critical|out-of-range|threshold/i)
  })

  // Scenario 25: "tata letak satu kolom pada layar ponsel"
  it('tata letak — satu kolom bertumpuk, kendali memakai kelas sasaran sentuh, grafik & tabel menggulir di dalam kartunya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await recapToggle(wrapper).trigger('click')

    // Tumpukan satu kolom: kesembilan kartu metrik adalah anak langsung
    // dari .metric-stack (flex-direction: column pada <style>), bukan grid
    // berkolom banyak.
    const stack = wrapper.get('[data-testid="metric-cards"]')
    expect(stack.classes()).toContain('metric-stack')
    expect(stack.element.children).toHaveLength(9)

    // Kendali sentuh memakai kelas ber-min-height 44px. Ukuran pikselnya
    // sendiri dibuktikan pada viewport 390x844 di
    // tests/e2e/laporan-boiler-room.spec.ts — jsdom tidak menghitung layout.
    expect(wrapper.get('[data-testid="period-select"]').element.closest('.filter-field')).not.toBeNull()
    expect(wrapper.get('[data-testid="export-button"]').classes()).toContain('action-button')
    expect(wrapper.get('[data-testid="back-button"]').classes()).toContain('action-button')
    expect(recapToggle(wrapper).classes()).toContain('recap-toggle')
    expect(wrapper.get('[data-testid="hamburger-button"]').classes()).toContain('hamburger-button')

    // Yang lebar bukan halamannya melainkan isi kartunya: keduanya
    // menggulir DI DALAM kartunya sendiri.
    expect(wrapper.find('[data-testid="daily-trend-steam-pressure"] .chart-scroll').exists()).toBe(true)
    expect(wrapper.find('[data-testid="daily-trend-steam-temp"] .chart-scroll').exists()).toBe(true)
    expect(wrapper.find('[data-testid="by-unit-card"] .detail-table-wrap').exists()).toBe(true)
    expect(wrapper.find('[data-testid="daily-recap"] .detail-table-wrap').exists()).toBe(true)
  })

  // Scenario 26: "mengunduh rincian pembacaan per slot waktu sebagai CSV"
  it('ekspor CSV — exportCsv dipanggil sekali dengan periode yang sedang dibuka lalu blob-nya disimpan apa adanya', async () => {
    const blob = new Blob(['a,b\n1,2\n'], { type: 'text/csv' })
    repoMocks.exportCsv.mockResolvedValue(blob)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    expect(repoMocks.exportCsv).toHaveBeenCalledTimes(1)
    expect(repoMocks.exportCsv).toHaveBeenCalledWith('per-1', {
      isAdmin: false,
      businessUnitId: null,
      productionLineId: 'pl-1',
    })

    // Blob hasil server disimpan apa adanya — komponen TIDAK menyusun isi
    // CSV sendiri dari angka yang sedang tampil di layar.
    expect(repoMocks.saveCsvFile).toHaveBeenCalledTimes(1)
    const [savedBlob, filename] = repoMocks.saveCsvFile.mock.calls[0] as [Blob, string]
    expect(savedBlob).toBe(blob)
    expect(filename).toMatch(/^laporan-boiler-room_.*\.csv$/)
  })
})

/* ================================================================== */
/* Production Line wajib (2026-09-28)                                  */
/* ================================================================== */

/**
 * Laporan ini memulangkan ANGKA GABUNGAN satu periode. Menjumlahkan
 * beberapa Production Line ke dalam satu angka menghasilkan bilangan yang
 * tidak dapat ditindaklanjuti siapa pun — karena itu memilih line di sini
 * WAJIB dan tidak ada opsi "semua line". (Data Browser versi web memang
 * punya opsi "Semua Line"; di sana barisnya tetap terpisah per record,
 * jadi perbedaan itu disengaja, bukan ketidakkonsistenan.)
 *
 * FIXTURE DUA LINE SENGAJA BERBEDA NILAINYA, DAN JUMLAH KEDUANYA SENGAJA
 * TIDAK MUNCUL DI MANA PUN. Layar yang benar menampilkan nilai salah satu
 * line; layar yang diam-diam menggabungkan akan menampilkan jumlahnya dan
 * gagal di sini. JANGAN "merapikan" angka-angka ini.
 */
const LINE_1_REF = { id: 'pl-1', name: 'Line 1' }
const LINE_2_REF = { id: 'pl-2', name: 'Line 2' }

const SUMMARY_LINE_1 = makeSummary({
  production_line: LINE_1_REF,
  metrics: makeMetrics({ steam_pressure_bar: { min: 18.2, avg: 33.3, max: 26.9, reading_count: 300 } }),
})

const SUMMARY_LINE_2 = makeSummary({
  production_line: LINE_2_REF,
  metrics: makeMetrics({ steam_pressure_bar: { min: 18.2, avg: 11.1, max: 26.9, reading_count: 300 } }),
})

/** Server menjawab menurut line yang diminta — tidak pernah menggabungkan. */
function stubSummaryPerLine(): void {
  repoMocks.fetchSummary.mockImplementation(
    (_periodId: string, scope?: { productionLineId?: string | null }) =>
      Promise.resolve(scope?.productionLineId === 'pl-2' ? SUMMARY_LINE_2 : SUMMARY_LINE_1),
  )
}

async function selectProductionLine(wrapper: VueWrapper, lineId: string): Promise<void> {
  await wrapper.get('[data-testid="production-line-select"]').setValue(lineId)
  await flushPromises()
}

describe('LaporanBoilerRoomView — Production Line wajib (screen-137)', () => {
  it('dua line dan belum ada yang berlaku — pemilih tampil, NOL angka, fetchSummary tidak pernah dipanggil', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(TWO_LINES)

    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-select')).toBe(true)
    expect(
      wrapper.findAll('[data-testid="production-line-select"] option').map((option) => option.text()),
    ).toEqual(['Pilih Production Line', 'Line 1', 'Line 2'])
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(exists(wrapper, 'production-line-current')).toBe(false)
    // Tidak ada opsi gabungan yang diam-diam ditawarkan.
    expect(wrapper.text()).not.toMatch(/semua line/i)

    // Periode tetap boleh dipilih — /periods memang TIDAK tersaring line.
    await selectPeriod(wrapper, 'per-1')

    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
    expect(exists(wrapper, 'metric-steam-pressure-avg')).toBe(false)
    expect(exists(wrapper, 'export-button')).toBe(false)
  })

  it('angka yang tampil milik SATU line — bukan jumlah kedua line', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(TWO_LINES)
    stubSummaryPerLine()

    const wrapper = await mountView()

    await selectProductionLine(wrapper, 'pl-1')
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'metric-steam-pressure-avg')).toBe('33,3')
    for (const forbidden of ['44,4']) {
      expect(wrapper.text()).not.toContain(forbidden)
    }

    // Ganti line: permintaannya membawa id baru, angka lama tidak tertinggal.
    await selectProductionLine(wrapper, 'pl-2')

    expect(repoMocks.fetchSummary).toHaveBeenLastCalledWith('per-1', {
      isAdmin: false,
      businessUnitId: null,
      productionLineId: 'pl-2',
    })
    expect(text(wrapper, 'metric-steam-pressure-avg')).toBe('11,1')
    expect(wrapper.text()).not.toContain('33,3')
  })

  it('nama line yang berlaku terbaca di layar, diambil dari jawaban server', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(TWO_LINES)
    stubSummaryPerLine()

    const wrapper = await mountView()
    await selectProductionLine(wrapper, 'pl-2')
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'production-line-current')).toContain('Line 2')
    expect(text(wrapper, 'production-line-current')).not.toContain('Line 1')
  })

  it('ekspor membawa production_line_id — berkasnya mengikuti cakupan angka di layar', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(TWO_LINES)
    stubSummaryPerLine()

    const wrapper = await mountView()
    await selectProductionLine(wrapper, 'pl-2')
    await selectPeriod(wrapper, 'per-1')

    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    expect(repoMocks.exportCsv).toHaveBeenCalledWith('per-1', {
      isAdmin: false,
      businessUnitId: null,
      productionLineId: 'pl-2',
    })
  })

  it('ingatan line dari layar Daftar Stasiun dipakai ulang — pengguna TIDAK diminta memilih dua kali', async () => {
    // Kunci yang sama persis yang ditulis StationListView.vue.
    window.localStorage.setItem('msl_production_line_user-1', 'pl-2')
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(TWO_LINES)
    stubSummaryPerLine()

    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-required-hint')).toBe(false)
    expect(selectValue(wrapper, 'production-line-select')).toBe('pl-2')

    await selectPeriod(wrapper, 'per-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('per-1', {
      isAdmin: false,
      businessUnitId: null,
      productionLineId: 'pl-2',
    })
    expect(text(wrapper, 'metric-steam-pressure-avg')).toBe('11,1')
  })

  it('production_line_id dari rute (dibawa screen-141) berlaku, dan menang atas ingatan', async () => {
    window.localStorage.setItem('msl_production_line_user-1', 'pl-1')
    routeQuery.production_line_id = 'pl-2'
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(TWO_LINES)
    stubSummaryPerLine()

    const wrapper = await mountView()

    expect(selectValue(wrapper, 'production-line-select')).toBe('pl-2')

    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'metric-steam-pressure-avg')).toBe('11,1')
    // Pilihan itu ikut menjadi ingatan — kedua layar berbagi satu konteks.
    expect(window.localStorage.getItem('msl_production_line_user-1')).toBe('pl-2')
  })

  it('ingatan maupun query yang tidak ada di daftar line TIDAK dipercaya — pemilih muncul kembali', async () => {
    window.localStorage.setItem('msl_production_line_user-1', 'pl-sudah-dihapus')
    routeQuery.production_line_id = 'pl-mill-lain'
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(TWO_LINES)

    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(exists(wrapper, 'production-line-current')).toBe(false)

    await selectPeriod(wrapper, 'per-1')
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
  })

  it('ingatan milik pengguna LAIN tidak pernah terbawa setelah ganti akun di perangkat yang sama', async () => {
    window.localStorage.setItem('msl_production_line_user-99', 'pl-2')
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(TWO_LINES)

    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
  })

  it('satu line di mill — berlaku otomatis, tanpa pemilih, dan id-nya tetap terkirim', async () => {
    const wrapper = await mountView() // bawaan berkas ini: satu line

    expect(exists(wrapper, 'production-line-select')).toBe(false)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(false)
    expect(text(wrapper, 'production-line-current')).toContain('Line 1')

    await selectPeriod(wrapper, 'per-1')

    // "Satu line" bukan "tanpa line": id-nya TETAP ikut pada cakupan.
    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('per-1', {
      isAdmin: false,
      businessUnitId: null,
      productionLineId: 'pl-1',
    })
  })

  it('memilih line menuliskan ingatannya — konteksnya dibagi dengan layar Daftar Stasiun', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(TWO_LINES)
    stubSummaryPerLine()

    const wrapper = await mountView()
    expect(window.localStorage.getItem('msl_production_line_user-1')).toBeNull()

    await selectProductionLine(wrapper, 'pl-2')

    expect(window.localStorage.getItem('msl_production_line_user-1')).toBe('pl-2')
  })

  it('daftar periode TIDAK PERNAH tersaring per line — periode milik mill', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(TWO_LINES)
    stubSummaryPerLine()

    const wrapper = await mountView()
    await selectProductionLine(wrapper, 'pl-2')

    // Daftar periode diambil TEPAT sekali, pada pemuatan awal — mengganti
    // line tidak memuat ulangnya, karena periode memang milik mill.
    expect(repoMocks.fetchPeriods).toHaveBeenCalledTimes(1)
  })

  it('daftar line gagal dimuat — tanpa angka, dengan tombol coba lagi, dan BUKAN sebagai galat laporan', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockRejectedValue(NETWORK_ERROR)

    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-unavailable')).toBe(true)
    expect(text(wrapper, 'production-line-unavailable')).toContain('koneksi')
    expect(exists(wrapper, 'production-line-retry')).toBe(true)
    // Bukan jalur galat laporan: pemilih periode tetap terisi.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(wrapper.findAll('[data-testid="period-select"] option').length).toBeGreaterThan(1)

    await selectPeriod(wrapper, 'per-1')
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

    // Coba Lagi memuat ulang daftarnya; sekali berhasil, layar hidup lagi.
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_2])
    await wrapper.get('[data-testid="production-line-retry"]').trigger('click')
    await flushPromises()

    expect(exists(wrapper, 'production-line-unavailable')).toBe(false)
    expect(text(wrapper, 'production-line-current')).toContain('Line 2')
  })

  it('mill tanpa satu pun Production Line — arahan menghubungi Admin, tanpa tombol coba lagi', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([])

    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-unavailable')).toBe(true)
    expect(text(wrapper, 'production-line-unavailable')).toContain('hubungi Admin')
    expect(exists(wrapper, 'production-line-retry')).toBe(false)
  })

  it('akun tanpa mill tetap NOL permintaan — termasuk ke daftar Production Line', async () => {
    asRole('operator', null)

    const wrapper = await mountView()

    expect(productionLineMocks.fetchProductionLinesForReport).not.toHaveBeenCalled()
    expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()
    expect(exists(wrapper, 'no-mill-for-account')).toBe(true)
    expect(exists(wrapper, 'production-line-select')).toBe(false)
    expect(exists(wrapper, 'production-line-unavailable')).toBe(false)
  })
})

/*
 * Temuan audit 2026-10-04 #3/#9/#10 — kelengkapan periode yang masih
 * berjalan. Server menghentikan penyebut di HARI INI (days_counted) dan
 * menandai period_running; layar harus memakai days_counted pada rumus
 * "× N hari", menampilkan catatan "sampai hari ini", dan menampilkan "-"
 * (bukan "0,0%") bila penyebutnya 0 — sama dengan laporan web.
 */
describe('LaporanBoilerRoomView — kelengkapan periode berjalan (temuan audit 2026-10-04)', () => {
  function coverageOf(overrides: Record<string, unknown>): Record<string, unknown> {
    return {
      filled_slots: 96,
      expected_slots: 192,
      coverage_percent: 50,
      boiler_unit_count: 2,
      slots_per_unit_per_day: 24,
      days_in_period: 9,
      days_counted: 4,
      period_running: true,
      ...overrides,
    }
  }

  it('periode masih berjalan — rumus memakai days_counted, catatan "sampai hari ini" tampil', async () => {
    repoMocks.fetchSummary.mockResolvedValue(makeSummary({ coverage: coverageOf({}) }))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'coverage-percent')).toBe('50,0%')
    expect(text(wrapper, 'coverage-slots')).toContain('96 dari 192 slot waktu terisi')
    const formula = text(wrapper, 'coverage-formula').replace(/\s+/g, ' ')
    expect(formula).toContain('× 4 hari (sampai hari ini) ×')
    expect(formula).not.toContain('9 hari')
    const note = text(wrapper, 'period-running-note').replace(/\s+/g, ' ')
    expect(note).toBe('Dihitung sampai hari ini, periode masih berjalan (4 dari 9 hari periode sudah lewat).')
  })

  it('periode sudah selesai — tanpa catatan dan tanpa "(sampai hari ini)"', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({ coverage: coverageOf({ expected_slots: 432, days_counted: 9, period_running: false }) }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'period-running-note')).toBe(false)
    const formula = text(wrapper, 'coverage-formula').replace(/\s+/g, ' ')
    expect(formula).toContain('× 9 hari ×')
    expect(formula).not.toContain('sampai hari ini')
  })

  it('penyebut 0 (periode belum mulai) — persen "-" BUKAN "0,0%", dan keterangan belum ada slot', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        has_data: false,
        coverage: coverageOf({ filled_slots: 0, expected_slots: 0, coverage_percent: 0, days_counted: 0 }),
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'coverage-percent')).toBe('-')
    expect(text(wrapper, 'coverage-percent')).not.toContain('0,0')
    expect(exists(wrapper, 'coverage-slots')).toBe(false)
    expect(text(wrapper, 'coverage-no-expected')).toBe('belum ada slot yang diharapkan')
    expect(text(wrapper, 'period-running-note').replace(/\s+/g, ' ')).toContain('(0 dari 9 hari periode sudah lewat)')
  })
})
