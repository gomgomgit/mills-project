/**
 * LaporanStorageTankView.spec.ts — screen-139--laporan-storage-tank-mobile /
 * usecase-139--laporan-storage-tank-mobile "Lihat Laporan Periode Storage
 * Tank (Mobile)".
 *
 * Satu test per test_scenarios yang punya component_test.
 *
 * STRATEGI MOCK — yang di-mock adalah REPO, bukan apiClient.
 *
 * Pembagiannya disengaja dan berpasangan dengan
 * tests/storageTankReportRepo.spec.ts (konvensi yang sama dengan
 * LaporanClarificationView.spec.ts): seluruh klaim tentang QUERY YANG DIKIRIM
 * dan PEMETAAN RESPONS — scopeParams() yang mengosongkan business_unit_id
 * untuk peran terikat mill, unwrap() yang menerima kedua bentuk pembungkus,
 * null yang bertahan sebagai null — sudah dikunci baris demi baris di sana
 * terhadap apiClient yang sungguhan. Yang tersisa untuk berkas ini, dan hanya
 * dapat dibuktikan di sini, adalah apa yang dilakukan VIEW terhadap
 * nilai-nilai itu: null menjadi "-" (bukan "0"), pergerakan yang tidak dapat
 * dihitung menjadi kata alih-alih angka nol, 401 menjadi perpindahan ke Login
 * TANPA tombol Coba Lagi, jaringan putus menjadi pesan + Coba Lagi yang TIDAK
 * membuang periode terpilih, dan buka/tutup rekap yang tidak memicu satu pun
 * permintaan baru.
 *
 * ANGKA FIXTURE SENGAJA TIDAK KONSISTEN SECARA ARITMETIKA — sama persis
 * dengan fixture pada storageTankReportRepo.spec.ts, dan dengan alasan yang
 * sama: layar yang diam-diam menghitung ulang HARUS gagal di sini.
 *   - stock.movement_mt (280,5) ≠ closing_mt − opening_mt (300,0) dan ≠
 *     jumlah by_tank[].movement_mt (260,5) — godaan terbesar di layar ini;
 *   - by_tank[0].movement_mt (100,5) ≠ 960,0 − 800,0 (160,0), jadi tidak ada
 *     satu baris pun yang selisihnya kebetulan cocok;
 *   - stock.tanks_without_movement (1) ≠ jumlah baris ber-movement_computable
 *     false (2);
 *   - metrics.ffa_percent.max (4,4) LEBIH KECIL daripada daily[0].ffa_avg
 *     (5,8) — ekstrem berasal dari PEMBACAAN MENTAH per slot waktu, bukan
 *     dari rata-rata harian;
 *   - metrics.average_temperature_c.avg (52,0) ≠ rata-rata ketiga suhu posisi
 *     (55,4);
 *   - coverage.expected_slots (240) ≠ 4 × 30 × 4 (480);
 *   - coverage.filled_slots (3) ≠ jumlah daily.filled_slots (18);
 *   - total.days_with_records (11) ≠ panjang daily (4).
 * JANGAN "merapikan" angka-angka ini.
 *
 * JUMLAH KARTU DISAMAKAN DENGAN LAYAR WEB screen-133, dan itu adalah
 * keputusan yang diasersi di bawah: kelengkapan, stok awal, stok akhir,
 * pergerakan bersih, keempat kartu mutu (FFA / kadar air / kotoran / DOBI),
 * dan satu kartu suhu rata-rata. calculated_volume_m3, calculated_weight_mt,
 * dan ketiga suhu posisi TIDAK mendapat kartu — sama seperti laporan web,
 * dan karena calculated_weight_mt sudah menjadi angka stok di atas.
 * (Bandingkan screen-137, yang tercatat sebagai known_issue justru karena
 * ponselnya menampilkan 9 kartu sementara webnya 5.)
 *
 * REKAP HARIAN MEMAKAI v-if, BUKAN v-show — sama seperti screen-137/138.
 * Di layar ini barisnya benar-benar HILANG dari DOM saat tertutup, jadi
 * keadaan tertutup diasersi sebagai KETIADAAN (toHaveLength(0)), bukan
 * sebagai keterlihatan — jsdom pun menyimpan cache getComputedStyle per
 * elemen, sehingga asersi berbasis keterlihatan di sana tidak dapat
 * dipercaya.
 *
 * SESI BERAKHIR MEMAKAI router.replace, BUKAN push: layar laporan yang
 * sesinya sudah mati tidak boleh dapat dicapai kembali dengan tombol Back
 * peramban. Tech spec screen-139 menuliskan "router.push dipanggil menuju
 * rute Login"; yang berlaku adalah replace, mengikuti screen-135/136/137/138,
 * dan router palsu di bawah memaparkan keduanya sehingga keduanya dapat
 * diperiksa — termasuk bahwa push TIDAK dipakai.
 *
 * TUNTUTAN PIKSEL ADA DI BROWSER TEST. jsdom tidak menghitung tata letak,
 * jadi "44x44 piksel" dan "tanpa gulir mendatar halaman" dibuktikan di
 * tests/e2e/laporan-storage-tank.spec.ts pada viewport 390x844. Yang
 * dibuktikan di sini adalah STRUKTURnya: tumpukan satu kolom, kelas
 * kontainer yang menggulir sendiri, dan kendali yang memakai kelas
 * ber-min-height 44px.
 */
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import LaporanStorageTankView from '@/views/LaporanStorageTankView.vue'

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

vi.mock('@/services/storageTankReportRepo', () => ({
  default: repoMocks,
  ...repoMocks,
}))

/**
 * productionLineRepo — daftar Production Line untuk mill yang berlaku
 * (GET /api/production-lines/options-for-report). Di-stub di tingkat repo, sama
 * seperti repo laporan berkas ini, karena yang diuji di sini adalah
 * KEPUTUSAN LAYAR atas daftar itu, bukan bentuk permintaan HTTP-nya (itu
 * milik storageTankReportRepo.spec.ts).
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
const FORBIDDEN_ERROR = { message: 'Anda tidak memiliki akses untuk aksi ini.', status: 403 }

/* ------------------------------------------------------------------ */
/* Fixture                                                             */
/* ------------------------------------------------------------------ */

const PERIOD_STG = {
  id: 'per-1',
  name: 'Periode September 2026',
  start_date: '2026-09-01',
  end_date: '2026-09-30',
  status: 'open',
  station_type: 'storage-tank',
  station_type_label: 'Storage Tank',
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
  name: 'Periode Lintas Stasiun September 2026',
  start_date: '2026-09-01',
  end_date: '2026-09-30',
  status: 'open',
  station_type: 'storage-tank',
  station_type_label: 'Storage Tank',
}

const PERIOD_CLOSED = {
  id: 'per-3',
  name: 'Periode Agustus 2026',
  start_date: '2026-08-01',
  end_date: '2026-08-31',
  status: 'closed',
  station_type: 'storage-tank',
  station_type_label: 'Storage Tank',
}

const BUSINESS_UNITS = [
  { id: 'bu-1', name: 'Mill Utara' },
  { id: 'bu-2', name: 'Mill Selatan' },
]

const EMPTY_METRIC = { min: null, avg: null, max: null, reading_count: 0 }

function makeMetrics(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    ffa_percent: { min: 3.1, avg: 4.05, max: 4.4, reading_count: 240 },
    moisture_content_percent: { min: 0.11, avg: 0.195, max: 0.24, reading_count: 118 },
    impurities_dirt_percent: { min: 0.01, avg: 0.027, max: 0.05, reading_count: 42 },
    dobi_index: { min: 2.2, avg: 2.85, max: 3.4, reading_count: 19 },
    average_temperature_c: { min: 48.0, avg: 52.0, max: 57.0, reading_count: 12 },
    calculated_weight_mt: { min: 380.0, avg: 660.0, max: 980.0, reading_count: 231 },
    calculated_volume_m3: { min: 420.0, avg: 730.0, max: 1090.0, reading_count: 229 },
    oil_temperature_top_c: { min: 46.0, avg: 50.0, max: 54.0, reading_count: 205 },
    oil_temperature_middle_c: { min: 51.0, avg: 55.0, max: 59.0, reading_count: 204 },
    oil_temperature_bottom_c: { min: 57.0, avg: 61.2, max: 66.0, reading_count: 203 },
    ...overrides,
  }
}

const EMPTY_METRICS = {
  ffa_percent: { ...EMPTY_METRIC },
  moisture_content_percent: { ...EMPTY_METRIC },
  impurities_dirt_percent: { ...EMPTY_METRIC },
  dobi_index: { ...EMPTY_METRIC },
  average_temperature_c: { ...EMPTY_METRIC },
  calculated_weight_mt: { ...EMPTY_METRIC },
  calculated_volume_m3: { ...EMPTY_METRIC },
  oil_temperature_top_c: { ...EMPTY_METRIC },
  oil_temperature_middle_c: { ...EMPTY_METRIC },
  oil_temperature_bottom_c: { ...EMPTY_METRIC },
}

const DAILY_ROWS = [
  {
    date: '2026-09-01',
    filled_slots: 4,
    stock_total_mt: 1240.0,
    ffa_avg: 5.8,
    moisture_avg: 0.21,
    impurities_avg: 0.02,
    dobi_avg: 3.1,
    temperature_avg: 53.0,
  },
  {
    date: '2026-09-03',
    filled_slots: 3,
    stock_total_mt: null,
    ffa_avg: 3.4,
    moisture_avg: null,
    impurities_avg: null,
    dobi_avg: null,
    temperature_avg: null,
  },
  {
    date: '2026-09-20',
    filled_slots: 5,
    stock_total_mt: 1380.0,
    ffa_avg: 4.0,
    moisture_avg: 0.19,
    impurities_avg: 0.03,
    dobi_avg: 2.8,
    temperature_avg: 51.5,
  },
  {
    date: '2026-09-30',
    filled_slots: 6,
    stock_total_mt: 1470.0,
    ffa_avg: 4.2,
    moisture_avg: 0.18,
    impurities_avg: 0.03,
    dobi_avg: 2.6,
    temperature_avg: 50.9,
  },
]

/**
 * Empat tangki, dan keempatnya ada di sini dengan alasannya sendiri: dua
 * dengan pergerakan yang dapat dihitung (nilainya sengaja tidak sama dengan
 * closing − opening), satu berpembacaan TUNGGAL (movement null,
 * movement_computable false — bukan 0), dan satu TANPA satu pun pembacaan
 * (seluruh kolom stok null, reading_count 0) yang tetap wajib dirender.
 */
const BY_TANK_ROWS = [
  {
    storage_tank_id: 'ST-1',
    reading_count: 180,
    opening_mt: 800.0,
    opening_at: '2026-09-01 06:00',
    closing_mt: 960.0,
    closing_at: '2026-09-30 18:00',
    movement_mt: 100.5,
    movement_computable: true,
    ffa_avg: 4.1,
    average_temperature_avg: 52.0,
  },
  {
    storage_tank_id: 'ST-2',
    reading_count: 96,
    opening_mt: 500.0,
    opening_at: '2026-09-02 06:00',
    closing_mt: 540.0,
    closing_at: '2026-09-28 12:00',
    movement_mt: 160.0,
    movement_computable: true,
    ffa_avg: 3.9,
    average_temperature_avg: null,
  },
  {
    storage_tank_id: 'ST-3',
    reading_count: 1,
    opening_mt: 400.0,
    opening_at: '2026-09-03 06:00',
    closing_mt: 400.0,
    closing_at: '2026-09-03 06:00',
    movement_mt: null,
    movement_computable: false,
    ffa_avg: null,
    average_temperature_avg: null,
  },
  {
    storage_tank_id: 'ST-4',
    reading_count: 0,
    opening_mt: null,
    opening_at: null,
    closing_mt: null,
    closing_at: null,
    movement_mt: null,
    movement_computable: false,
    ffa_avg: null,
    average_temperature_avg: null,
  },
]

const STOCK = {
  opening_mt: 1200.0,
  opening_at: '2026-09-01 06:00',
  closing_mt: 1500.0,
  closing_at: '2026-09-28 18:00',
  movement_mt: 280.5,
  tanks_with_movement: 2,
  tanks_without_movement: 1,
}

const COVERAGE = {
  filled_slots: 3,
  expected_slots: 240,
  coverage_percent: 1.25,
  tank_count: 4,
  slots_per_tank_per_day: 4,
  days_in_period: 30,
}

function makeSummary(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    period: {
      id: 'per-1',
      name: 'Periode September 2026',
      start_date: '2026-09-01',
      end_date: '2026-09-30',
      status: 'open',
      business_unit_name: 'Mill Utara',
    },
    business_unit: { id: 'bu-1', name: 'Mill Utara' },
    has_data: true,
    coverage: { ...COVERAGE },
    stock: { ...STOCK },
    metrics: makeMetrics(),
    by_tank: BY_TANK_ROWS,
    daily: DAILY_ROWS,
    total: { days_with_records: 11, reading_rows: 412 },
    ...overrides,
  }
}

/** Periode tanpa satu pun pembacaan — has_data false datang dari SERVER. */
const EMPTY_SUMMARY = makeSummary({
  has_data: false,
  coverage: {
    filled_slots: 0,
    expected_slots: 480,
    coverage_percent: 0,
    tank_count: 4,
    slots_per_tank_per_day: 4,
    days_in_period: 30,
  },
  stock: {
    opening_mt: null,
    opening_at: null,
    closing_mt: null,
    closing_at: null,
    movement_mt: null,
    tanks_with_movement: 0,
    tanks_without_movement: 0,
  },
  metrics: { ...EMPTY_METRICS },
  by_tank: [],
  daily: [],
  total: { days_with_records: 0, reading_rows: 0 },
})

/** Rekap sebulan penuh — kasus yang membuat rekap perlu ditutup. */
const THIRTY_DAYS = Array.from({ length: 30 }, (_, index) => ({
  date: `2026-09-${String(index + 1).padStart(2, '0')}`,
  filled_slots: 4,
  stock_total_mt: 1200 + index * 5,
  ffa_avg: 4 + index * 0.01,
  moisture_avg: 0.19 + index * 0.001,
  impurities_avg: 0.02 + index * 0.001,
  dobi_avg: 2.8 + index * 0.01,
  temperature_avg: 52 + index * 0.1,
}))

/** Keempat kartu mutu, urutan mengikuti laporan web screen-133. */
const METRIC_TESTIDS = ['ffa', 'moisture', 'impurities', 'dobi']

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
  const wrapper = mount(LaporanStorageTankView)
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

function tankRows(wrapper: VueWrapper) {
  return wrapper.findAll('[data-testid="by-tank-row"]')
}

/** Posisi sebuah blok dalam urutan DOM, dibaca dari HTML terender. */
function domPosition(wrapper: VueWrapper, testid: string): number {
  return wrapper.html().indexOf(`data-testid="${testid}"`)
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
  repoMocks.fetchPeriods.mockResolvedValue([PERIOD_STG])
  repoMocks.fetchSummary.mockResolvedValue(makeSummary())
  repoMocks.exportCsv.mockResolvedValue(new Blob(['csv'], { type: 'text/csv' }))
  repoMocks.saveCsvFile.mockImplementation(() => {})
})

afterEach(() => {
  vi.restoreAllMocks()
})

/* ================================================================== */
/* test_scenarios — component_test (tech spec screen-139)              */
/* ================================================================== */

describe('LaporanStorageTankView — test_scenarios / component_test (tech spec screen-139)', () => {
  // Scenario 1: "berhasil dilihat Operator, Supervisor, dan Mill Management"
  it('berhasil dilihat peran terikat mill — tanpa pemilih Mill, seluruh blok terender apa adanya, tanpa kontrol tulis', async () => {
    for (const role of ['operator', 'supervisor', 'mill_management'] as const) {
      vi.clearAllMocks()
      asRole(role, 'bu-1')
      repoMocks.fetchPeriods.mockResolvedValue([PERIOD_STG])
      repoMocks.fetchSummary.mockResolvedValue(makeSummary())

      const wrapper = await mountView()
      await selectPeriod(wrapper, 'per-1')

      // Pemilih Mill tidak dirender sama sekali untuk peran terikat mill.
      expect(exists(wrapper, 'mill-select')).toBe(false)
      expect(text(wrapper, 'mill-current')).toContain('Mill Utara')

      // Kelengkapan, stok awal/akhir beserta tanggal pembacaannya,
      // pergerakan bersih, rekap per tangki, keempat kartu mutu, suhu
      // rata-rata, tren harian, dan rekap harian.
      expect(text(wrapper, 'coverage-percent')).toBe('1,25%')
      expect(text(wrapper, 'stock-opening-mt')).toBe('1.200,0')
      expect(text(wrapper, 'opening-at')).toContain('01 Sep 2026')
      expect(text(wrapper, 'stock-closing-mt')).toBe('1.500,0')
      expect(text(wrapper, 'closing-at')).toContain('28 Sep 2026')
      expect(text(wrapper, 'stock-movement-mt')).toBe('+280,5')
      expect(text(wrapper, 'metric-ffa-avg')).toBe('4,05')
      expect(text(wrapper, 'metric-temperature-avg')).toBe('52,0')
      expect(text(wrapper, 'metric-ffa-count')).toContain('240')
      expect(exists(wrapper, 'stock-trend-chart')).toBe(true)
      expect(exists(wrapper, 'quality-trend-chart')).toBe(true)
      expect(exists(wrapper, 'by-tank-table')).toBe(true)
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
  it('Admin — pemilih Mill dirender, memilih mill memuat daftar line + periode, lalu seluruh angka tampil untuk mill itu', async () => {
    asRole('admin', null)

    const wrapper = await mountView()

    expect(repoMocks.fetchBusinessUnits).toHaveBeenCalledTimes(1)
    expect(exists(wrapper, 'mill-select')).toBe(true)
    // Periode dan daftar line belum dimuat sebelum mill dipilih.
    expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()
    expect(productionLineMocks.fetchProductionLinesForReport).not.toHaveBeenCalled()

    await selectMill(wrapper, 'bu-2')

    // Daftar line diminta UNTUK MILL TERPILIH.
    expect(productionLineMocks.fetchProductionLinesForReport).toHaveBeenCalledTimes(1)
    expect(productionLineMocks.fetchProductionLinesForReport).toHaveBeenCalledWith('bu-2')

    expect(repoMocks.fetchPeriods).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchPeriods).toHaveBeenCalledWith({ isAdmin: true, businessUnitId: 'bu-2', productionLineId: 'pl-1' })

    await selectPeriod(wrapper, 'per-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('per-1', {
      isAdmin: true,
      businessUnitId: 'bu-2',
      productionLineId: 'pl-1',
    })
    expect(text(wrapper, 'coverage-percent')).toBe('1,25%')
    expect(text(wrapper, 'stock-opening-mt')).toBe('1.200,0')
    expect(text(wrapper, 'stock-closing-mt')).toBe('1.500,0')
    expect(text(wrapper, 'stock-movement-mt')).toBe('+280,5')
    expect(text(wrapper, 'metric-temperature-avg')).toBe('52,0')
    expect(exists(wrapper, 'by-tank-table')).toBe(true)
    expect(exists(wrapper, 'stock-trend-chart')).toBe(true)
    expect(exists(wrapper, 'daily-recap')).toBe(true)
    // Dan tidak ada lagi pesan buntu "pakai versi web".
    expect(exists(wrapper, 'production-line-unavailable')).toBe(false)

    // Mengganti mill memuat ulang daftar periode DAN daftar line mill baru,
    // lalu membuang angka lama.
    await selectMill(wrapper, 'bu-1')

    expect(productionLineMocks.fetchProductionLinesForReport).toHaveBeenCalledTimes(2)
    expect(productionLineMocks.fetchProductionLinesForReport).toHaveBeenLastCalledWith('bu-1')
    expect(repoMocks.fetchPeriods).toHaveBeenCalledTimes(2)
    expect(repoMocks.fetchPeriods).toHaveBeenLastCalledWith({ isAdmin: true, businessUnitId: 'bu-1', productionLineId: 'pl-1' })
    expect(exists(wrapper, 'coverage-percent')).toBe(false)
  })

  // Scenario 3: "Operator membuka laporan"
  it('Operator — laporan penuh, tanpa pemilih Mill, fetchBusinessUnits TIDAK pernah dipanggil, scope tanpa business_unit_id', async () => {
    asRole('operator', 'bu-1')

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Persis seperti peran lain — tidak ada bagian yang dibatasi.
    expect(text(wrapper, 'stock-opening-mt')).toBe('1.200,0')
    expect(text(wrapper, 'metric-ffa-avg')).toBe('4,05')
    expect(exists(wrapper, 'by-tank-table')).toBe(true)

    // Pemilih Mill tidak dirender: mill tidak dapat diganti dari layar.
    expect(exists(wrapper, 'mill-select')).toBe(false)

    // Endpoint options TIDAK pernah dipanggil — memanggilnya akan membentuk
    // daftar SELURUH mill di perangkat orang yang tidak berhak melihatnya,
    // dan server pun menjawab 403 di sana (pelebaran screen-139 sengaja
    // tidak menyentuh endpoint itu).
    expect(repoMocks.fetchBusinessUnits).not.toHaveBeenCalled()

    // scopeParams menghasilkan objek kosong: businessUnitId null diteruskan
    // ke repo, yang membuangnya karena isAdmin false.
    expect(repoMocks.fetchPeriods).toHaveBeenCalledWith({ isAdmin: false, businessUnitId: null, productionLineId: 'pl-1' })
    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('per-1', {
      isAdmin: false,
      businessUnitId: null,
      productionLineId: 'pl-1',
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
    expect(exists(wrapper, 'stock-opening-card')).toBe(false)
    expect(exists(wrapper, 'stock-movement-card')).toBe(false)
    expect(exists(wrapper, 'metric-cards')).toBe(false)
    expect(exists(wrapper, 'by-tank-card')).toBe(false)
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
    expect(exists(wrapper, 'stock-opening-card')).toBe(false)
  })

  // Scenario 6: "periode tanpa data"
  it('periode tanpa data — keterangan belum ada data, seluruh angka "-" bukan nol, dan TIDAK ada grafik sama sekali', async () => {
    repoMocks.fetchSummary.mockResolvedValue(EMPTY_SUMMARY)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'empty-period')).toContain('Belum ada data')

    // stock.* null dan metrics[*] null dirender sebagai "-", TIDAK PERNAH 0.
    expect(text(wrapper, 'stock-opening-mt')).toBe('-')
    expect(text(wrapper, 'stock-opening-mt')).not.toBe('0,0')
    expect(text(wrapper, 'stock-closing-mt')).toBe('-')
    expect(text(wrapper, 'stock-movement-mt')).toBe('-')
    expect(text(wrapper, 'stock-movement-mt')).not.toBe('0,0')
    expect(text(wrapper, 'opening-at')).toContain('-')
    expect(text(wrapper, 'closing-at')).toContain('-')

    for (const testid of METRIC_TESTIDS) {
      expect(text(wrapper, `metric-${testid}-avg`)).toBe('-')
      expect(text(wrapper, `metric-${testid}-count`)).toContain('0')
    }
    expect(text(wrapper, 'metric-temperature-avg')).toBe('-')

    // Tidak ada grafik yang dirender — garis datar dari data kosong akan
    // terbaca sebagai hasil pengukuran.
    expect(exists(wrapper, 'stock-trend-chart')).toBe(false)
    expect(exists(wrapper, 'quality-trend-chart')).toBe(false)
  })

  // Scenario 7: "tangki hanya punya satu pembacaan stok"
  it('tangki berpembacaan tunggal — stok awal dan akhir menunjuk pembacaan yang SAMA, pergerakannya "tidak dapat dihitung" BUKAN 0', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const singleReadingRow = tankRows(wrapper)[2]

    expect(singleReadingRow.text()).toContain('ST-3')
    // Nilai DAN tanggalnya identik: kedua ujung menunjuk pembacaan yang sama.
    expect(singleReadingRow.text()).toContain('400,0')
    expect(singleReadingRow.text()).toContain('03 Sep 2026 06:00')

    // Keputusan tampilan memakai movement_computable, BUKAN nilai
    // movement_mt — di sini closing − opening kebetulan 0, jadi layar yang
    // menghitungnya sendiri akan merender "0,0" dan gagal di sini.
    //
    // Asersinya menyasar SEL PERGERAKAN saja (td ke-6), bukan teks seluruh
    // baris: "0,0" adalah substring dari "400,0" milik kolom stok awal dan
    // akhir, sehingga asersi selebar satu baris akan selalu memerah betapa
    // pun benarnya layar ini. Yang diklaim di sini adalah sel pergerakan
    // tidak pernah membaca nol — dan itu hanya terbaca di selnya sendiri.
    const movementCell = singleReadingRow.findAll('td')[5]

    expect(movementCell.text()).toBe('tidak dapat dihitung')
    expect(movementCell.text()).not.toContain('0,0')
  })

  // Scenario 8: "pembacaan paling awal tidak mencatat stok"
  it('stok awal dirender berdampingan dengan TANGGAL PEMBACAANNYA, bukan dengan tanggal mulai periode', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        stock: { ...STOCK, opening_at: '2026-09-04 12:00' },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Tanggal pembacaan yang SEBENARNYA — 4 September — bukan start_date
    // periode (1 September). Selisih tiga hari itulah yang menyatakan
    // hari-hari sebelumnya tidak tercatat.
    expect(text(wrapper, 'opening-at')).toContain('04 Sep 2026')
    expect(text(wrapper, 'opening-at')).not.toContain('01 Sep 2026')
    // Dan ia dirender, bukan disembunyikan.
    expect(exists(wrapper, 'opening-at')).toBe(true)
  })

  // Scenario 9: "tangki tanpa satu pun pembacaan stok"
  it('tangki tanpa pembacaan TETAP dirender pada rekap per tangki, dengan stok dan pergerakan "-" bukan nol', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Keempat tangki hadir — tangki yang tidak pernah diukur adalah temuan,
    // bukan baris yang layak disaring keluar.
    expect(tankRows(wrapper)).toHaveLength(4)

    const emptyTank = tankRows(wrapper)[3]

    expect(emptyTank.text()).toContain('ST-4')
    expect(emptyTank.text()).not.toContain('0,0')
    // Ketiga kolom stoknya "-", dan bukan "tidak dapat dihitung": tangki ini
    // tidak punya pembacaan sama sekali.
    const cells = emptyTank.findAll('td').map((cell) => cell.text())
    expect(cells[1]).toBe('-')
    expect(cells[3]).toBe('-')
    expect(cells[5]).toBe('-')
    expect(cells[8]).toBe('0')
  })

  // Scenario 10: "jumlah tangki berbeda antara awal dan akhir periode"
  it('pergerakan bersih dirender dari payload apa adanya — BUKAN selisih stok gabungan', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // 280,5 dari payload, bukan 300,0 dari 1500,0 − 1200,0.
    expect(text(wrapper, 'stock-movement-mt')).toBe('+280,5')
    expect(text(wrapper, 'stock-movement-mt')).not.toContain('300')

    // Cacah tangki dirender dari payload, bukan dari penyaringan by_tank
    // (yang menghasilkan 2, bukan 1).
    expect(text(wrapper, 'tanks-with-movement')).toBe('2')
    expect(text(wrapper, 'tanks-without-movement')).toBe('1')

    // Tangki yang hanya muncul di satu ujung dinyatakan tidak dapat dihitung.
    expect(tankRows(wrapper)[2].text()).toContain('tidak dapat dihitung')

    // Dan alasannya dirender DI LAYAR, supaya tidak "diperbaiki" kelak.
    expect(text(wrapper, 'movement-not-difference-note')).toContain('BUKAN selisih')
  })

  // Scenario 11: "pergerakan bernilai negatif"
  it('pergerakan negatif dirender apa adanya dengan tanda minus — tanpa Math.abs, tanpa kelas peringatan', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        stock: { ...STOCK, movement_mt: -312.75 },
        by_tank: [{ ...BY_TANK_ROWS[0], movement_mt: -180.25 }, ...BY_TANK_ROWS.slice(1)],
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'stock-movement-mt')).toBe('-312,8')
    expect(text(wrapper, 'stock-movement-mt')).not.toBe('312,8')
    expect(tankRows(wrapper)[0].text()).toContain('-180,3')

    // Tidak ada kelas galat atau ikon peringatan yang dipasang karenanya.
    const html = wrapper.html().toLowerCase()
    for (const marker of ['is-danger', 'is-warning', 'negative', 'error-value', 'out-of-range']) {
      expect(html).not.toContain(marker)
    }
  })

  // Scenario 12: "sebuah metrik mutu tidak pernah diisi"
  it('metrik yang tidak pernah diisi — kartunya "-" dengan 0 pembacaan, sementara metrik lain tidak terpengaruh', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({ metrics: makeMetrics({ dobi_index: { ...EMPTY_METRIC } }) }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'metric-dobi-avg')).toBe('-')
    expect(text(wrapper, 'metric-dobi-avg')).not.toBe('0,00')
    expect(text(wrapper, 'metric-dobi-count')).toContain('0')
    expect(text(wrapper, 'metric-dobi-min')).toBe('-')
    expect(text(wrapper, 'metric-dobi-max')).toBe('-')

    // FFA tetap merender angkanya beserta reading_count-nya sendiri.
    expect(text(wrapper, 'metric-ffa-avg')).toBe('4,05')
    expect(text(wrapper, 'metric-ffa-count')).toContain('240')
  })

  // Scenario 13: "kolom suhu rata-rata kosong"
  it('suhu rata-rata kosong — kartunya "-" dan komponen TIDAK merata-ratakan ketiga suhu posisi', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({ metrics: makeMetrics({ average_temperature_c: { ...EMPTY_METRIC } }) }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'metric-temperature-avg')).toBe('-')
    expect(text(wrapper, 'metric-temperature-count')).toContain('0')

    // Rata-rata ketiga suhu posisi adalah 55,4 — angka itu TIDAK BOLEH muncul
    // di mana pun, karena layar tidak menurunkannya.
    expect(wrapper.html()).not.toContain('55,4')

    // by_tank[].average_temperature_avg yang null juga "-".
    expect(tankRows(wrapper)[1].findAll('td')[7].text()).toBe('-')

    // Dan alasannya dirender di layar.
    expect(text(wrapper, 'temperature-source-note')).toContain('DICATAT OPERATOR')
  })

  // Scenario 14: "pencatatan sangat tidak lengkap"
  it('kelengkapan sangat rendah — kartunya dirender DI ATAS seluruh blok angka, coverage_percent dua desimal apa adanya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Dua desimal apa adanya: 1,25 dan 1,3 menceritakan hal yang berbeda.
    expect(text(wrapper, 'coverage-percent')).toBe('1,25%')
    expect(text(wrapper, 'coverage-percent')).not.toBe('1,3%')
    expect(text(wrapper, 'coverage-slots')).toContain('3')
    expect(text(wrapper, 'coverage-slots')).toContain('240')

    // DI ATAS seluruh blok angka lain, dalam urutan DOM.
    const coveragePos = domPosition(wrapper, 'coverage-card')
    expect(coveragePos).toBeGreaterThan(-1)
    for (const testid of [
      'stock-opening-card',
      'stock-closing-card',
      'stock-movement-card',
      'metric-cards',
      'metric-card-temperature',
      'by-tank-card',
      'daily-recap',
    ]) {
      expect(coveragePos).toBeLessThan(domPosition(wrapper, testid))
    }

    // Stok awal/akhir tetap berdampingan dengan tanggal pembacaannya,
    // sehingga jarak waktu antar keduanya terbaca.
    expect(text(wrapper, 'opening-at')).toContain('01 Sep 2026')
    expect(text(wrapper, 'closing-at')).toContain('28 Sep 2026')
  })

  // Scenario 15: "akun belum terhubung ke mill"
  it('akun tanpa mill — pesan menghubungi Admin, TANPA pemilih Mill pengganti dan TANPA satu pun permintaan', async () => {
    for (const role of ['operator', 'supervisor', 'mill_management'] as const) {
      vi.clearAllMocks()
      asRole(role, null)

      const wrapper = await mountView()

      expect(text(wrapper, 'no-mill-for-account')).toContain('hubungi Admin')

      // Pemilih Mill TIDAK dirender sebagai gantinya — menawarkan daftar
      // seluruh mill kepada akun yang tidak berhak melihat satu pun adalah
      // persis kebocoran yang dihindari.
      expect(exists(wrapper, 'mill-select')).toBe(false)
      expect(exists(wrapper, 'period-select')).toBe(false)

      // NOL permintaan HTTP.
      expect(repoMocks.fetchBusinessUnits).not.toHaveBeenCalled()
      expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()
      expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

      // Tidak ada blok angka.
      expect(exists(wrapper, 'coverage-card')).toBe(false)
      expect(exists(wrapper, 'stock-opening-card')).toBe(false)

      wrapper.unmount()
    }
  })

  // Scenario 16: "mencoba melihat mill lain"
  it('Operator tidak punya jalan apa pun menyebut mill lain — setiap pemanggilan repo memakai scope tanpa business_unit_id', async () => {
    asRole('operator', 'bu-1')

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Setiap pemanggilan, tanpa kecuali.
    for (const call of [
      ...repoMocks.fetchPeriods.mock.calls,
      ...repoMocks.fetchSummary.mock.calls,
    ]) {
      const scope = call[call.length - 1] as { isAdmin?: boolean; businessUnitId?: string | null }
      expect(scope.isAdmin).toBe(false)
      expect(scope.businessUnitId).toBeNull()
    }

    // Dan layar tidak menyediakan jalan apa pun untuk memasukkan mill lain.
    expect(exists(wrapper, 'mill-select')).toBe(false)
    expect(wrapper.findAll('input')).toHaveLength(0)
    expect(wrapper.findAll('select')).toHaveLength(1)
  })

  // Scenario 17: "jaringan gagal"
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
    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('per-1', {
      isAdmin: false,
      businessUnitId: null,
      productionLineId: 'pl-1',
    })
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(text(wrapper, 'stock-opening-mt')).toBe('1.200,0')
    expect(selectValue(wrapper, 'period-select')).toBe('per-1')
  })

  // Scenario 18: "sesi berakhir"
  it('sesi berakhir — 401 mengarahkan ke Login lewat replace, tanpa angka dan TANPA tombol coba lagi', async () => {
    const wrapper = await mountView()
    repoMocks.fetchSummary.mockRejectedValueOnce(UNAUTHENTICATED_ERROR)

    await selectPeriod(wrapper, 'per-1')

    // replace, bukan push: layar yang sesinya mati tidak boleh dicapai
    // kembali dengan tombol Back peramban. Tech spec menulis "push"; yang
    // berlaku adalah preseden screen-135/136/137/138.
    expect(replaceMock).toHaveBeenCalledWith({ name: 'login' })
    expect(pushMock).not.toHaveBeenCalled()

    // Sesi berakhir BUKAN kasus coba lagi — pesan galat dan tombolnya
    // justru tidak boleh muncul.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'retry-button')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)

    // Tidak ada blok angka yang sempat dirender.
    expect(exists(wrapper, 'coverage-card')).toBe(false)
    expect(exists(wrapper, 'stock-opening-card')).toBe(false)
    expect(exists(wrapper, 'metric-cards')).toBe(false)
    expect(exists(wrapper, 'period-meta')).toBe(false)
  })

  // Scenario 19: "periode tertutup"
  it('periode tertutup — laporan tetap penuh, penanda Ditutup terender, tombol Ekspor tetap aktif', async () => {
    repoMocks.fetchPeriods.mockResolvedValue([PERIOD_CLOSED])
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        period: {
          id: 'per-3',
          name: 'Periode Agustus 2026',
          start_date: '2026-08-01',
          end_date: '2026-08-31',
          status: 'closed',
          business_unit_name: 'Mill Utara',
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-3')

    expect(text(wrapper, 'period-status-badge')).toContain('Ditutup')

    // Seluruh bagian tetap penuh.
    expect(text(wrapper, 'stock-opening-mt')).toBe('1.200,0')
    expect(exists(wrapper, 'coverage-card')).toBe(true)
    expect(exists(wrapper, 'by-tank-table')).toBe(true)
    expect(exists(wrapper, 'daily-recap')).toBe(true)

    // Ekspor TIDAK dinonaktifkan oleh status periode: kunci periode
    // mengatur penulisan data, bukan pembacaan laporan.
    const exportButton = wrapper.get('[data-testid="export-button"]')
    expect(exportButton.attributes('disabled')).toBeUndefined()
  })

  // Scenario 20: "rekap harian dapat ditutup"
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

    // Tutup — baris hilang, sementara kartu kelengkapan, ketiga kartu stok,
    // rekap per tangki, dan kedua grafik TETAP dirender.
    await wrapper.get('[data-testid="daily-recap-toggle"]').trigger('click')
    expect(recapRows(wrapper)).toHaveLength(0)
    expect(exists(wrapper, 'coverage-card')).toBe(true)
    expect(exists(wrapper, 'stock-opening-card')).toBe(true)
    expect(exists(wrapper, 'stock-movement-card')).toBe(true)
    expect(exists(wrapper, 'by-tank-table')).toBe(true)
    expect(exists(wrapper, 'stock-trend-chart')).toBe(true)
    expect(exists(wrapper, 'quality-trend-chart')).toBe(true)

    // Buka lagi — seluruh 30 baris kembali utuh.
    await wrapper.get('[data-testid="daily-recap-toggle"]').trigger('click')
    expect(recapRows(wrapper)).toHaveLength(30)

    // NOL permintaan jaringan baru — dihitung dari mock, bukan dari DOM.
    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchPeriods).toHaveBeenCalledTimes(1)
  })

  // Scenario 21: "layar hanya membaca"
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
    const repo = await import('@/services/storageTankReportRepo')
    expect(Object.keys(repo.default).sort()).toEqual([
      'exportCsv',
      'fetchBusinessUnits',
      'fetchPeriods',
      'fetchSummary',
      'saveCsvFile',
    ])
  })

  // Scenario 22: "angka ponsel sama persis dengan laporan versi web"
  it('nol perhitungan ulang di layar — setiap angka yang dirender berasal dari payload apa adanya', async () => {
    const payload = makeSummary()
    repoMocks.fetchSummary.mockResolvedValue(payload)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Angka dari payload, dengan format Indonesia — bukan hasil hitungan.
    expect(text(wrapper, 'stock-opening-mt')).toBe('1.200,0')
    expect(text(wrapper, 'stock-closing-mt')).toBe('1.500,0')
    expect(text(wrapper, 'stock-movement-mt')).toBe('+280,5')
    expect(text(wrapper, 'coverage-percent')).toBe('1,25%')
    expect(text(wrapper, 'metric-ffa-avg')).toBe('4,05')
    expect(text(wrapper, 'metric-temperature-avg')).toBe('52,0')

    // Angka-angka yang akan MUNCUL kalau layar berhitung sendiri — dan tidak
    // satu pun boleh ada di HTML.
    const html = wrapper.html()
    expect(html).not.toContain('300,0') // closing − opening
    expect(html).not.toContain('260,5') // jumlah by_tank[].movement_mt
    expect(html).not.toContain('55,4') // rata-rata ketiga suhu posisi
    expect(html).not.toContain('0,625') // 100 × 3 / 480
    expect(html).not.toContain('1,3%') // coverage_percent dibulatkan ulang
  })

  // Scenario 23: "periode yang tidak mencakup Storage Tank tidak ditawarkan"
  it('pemilih periode merender persis entri yang diterima — komponen tidak menyaring station_type sendiri', async () => {
    repoMocks.fetchPeriods.mockResolvedValue([PERIOD_STG, PERIOD_LINTAS_STASIUN])

    const wrapper = await mountView()

    const options = wrapper.get('[data-testid="period-select"]').findAll('option')

    // Opsi pembuka + kedua entri, dalam urutan server.
    expect(options).toHaveLength(3)
    expect(options[1].text()).toContain('Periode September 2026')
    expect(options[2].text()).toContain('Periode Lintas Stasiun September 2026')
    // Periode lintas stasiun TIDAK dibuang di klien — penyaringan cakupan
    // adalah pekerjaan server (periode itu sampai ke sini justru karena
    // punya baris period_stations berjenis 'storage-tank'), dan
    // menyaringnya dua kali menciptakan dua definisi.
    expect(options[2].attributes('value')).toBe('per-2')
  })

  // Scenario 24: "rentang periode inklusif di kedua ujung"
  it('kedua tanggal ujung dirender pada tren harian dan pada rekap harian', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Sumbu tren memuat kedua ujung.
    const chartText = wrapper.get('[data-testid="stock-trend-chart"]').text()
    expect(chartText).toContain('01')
    expect(chartText).toContain('30')

    await wrapper.get('[data-testid="daily-recap-toggle"]').trigger('click')

    const rows = recapRows(wrapper)
    expect(rows).toHaveLength(4)
    expect(rows[0].text()).toContain('01 Sep 2026')
    expect(rows[3].text()).toContain('30 Sep 2026')
  })

  // Scenario 25: "stok awal adalah pembacaan pertama, stok akhir yang terakhir"
  it('stok awal/akhir dirender dari blok stock beserta tanggalnya — bukan nilai terendah/tertinggi kolom harian', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'stock-opening-mt')).toBe('1.200,0')
    expect(text(wrapper, 'stock-closing-mt')).toBe('1.500,0')

    // Nilai stok harian terendah (1.240,0) dan tertinggi (1.470,0) SENGAJA
    // berbeda: layar yang mengambil min/max kolom harian akan merender
    // angka-angka itu pada kartu stok dan gagal.
    expect(text(wrapper, 'stock-opening-mt')).not.toBe('1.240,0')
    expect(text(wrapper, 'stock-closing-mt')).not.toBe('1.470,0')

    // Tanggal kedua pembacaan dirender bersama angkanya.
    expect(text(wrapper, 'opening-at')).toContain('01 Sep 2026 06:00')
    expect(text(wrapper, 'closing-at')).toContain('28 Sep 2026 18:00')
  })

  // Scenario 26: "pergerakan dihitung per tangki lalu dijumlahkan"
  it('rekap per tangki merender kolom pergerakan tiap tangki apa adanya, dan tidak satu pun sama dengan selisih kolom stoknya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const rows = tankRows(wrapper)

    // 100,5 dari payload — BUKAN 960,0 − 800,0 (160,0).
    expect(rows[0].text()).toContain('+100,5')
    expect(rows[0].text()).not.toContain('+160,0')
    // 160,0 dari payload — BUKAN 540,0 − 500,0 (40,0).
    expect(rows[1].text()).toContain('+160,0')
    expect(rows[1].text()).not.toContain('+40,0')
  })

  // Scenario 27: "stok diambil dari berat, bukan volume maupun kedalaman sounding"
  it('kartu stok memakai berat (MT) dari blok stock — tidak satu pun nilai volume muncul di sana', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Satuannya MT pada ketiga kartu stok.
    expect(text(wrapper, 'stock-opening-card')).toContain('MT')
    expect(text(wrapper, 'stock-closing-card')).toContain('MT')
    expect(text(wrapper, 'stock-movement-card')).toContain('MT')

    // Nilai volume (420,0 / 730,0 / 1.090,0) tidak boleh muncul pada kartu
    // stok mana pun.
    for (const card of ['stock-opening-card', 'stock-closing-card', 'stock-movement-card']) {
      const cardText = text(wrapper, card)
      for (const volume of ['420,0', '730,0', '1.090,0']) {
        expect(cardText).not.toContain(volume)
      }
    }
  })

  // Scenario 28: "setiap metrik punya penyebutnya sendiri"
  it('tiap kartu mutu dirender dengan reading_count-nya sendiri — tidak ada satu penyebut bersama di layar', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'metric-ffa-count')).toContain('240')
    expect(text(wrapper, 'metric-moisture-count')).toContain('118')
    expect(text(wrapper, 'metric-impurities-count')).toContain('42')
    expect(text(wrapper, 'metric-dobi-count')).toContain('19')
    expect(text(wrapper, 'metric-temperature-count')).toContain('12')

    // Penyebutnya berbeda-beda, dan tidak satu pun sama dengan
    // total.reading_rows (412) maupun coverage.filled_slots (3).
    const counts = METRIC_TESTIDS.map((testid) => text(wrapper, `metric-${testid}-count`))
    expect(new Set(counts).size).toBe(METRIC_TESTIDS.length)
    for (const count of counts) {
      expect(count).not.toContain('412')
    }
  })

  // Scenario 29: "FFA, kadar air, dan DOBI pada satu grafik dengan skala yang dinyatakan"
  it('ketiga metrik mutu adalah tiga seri pada SATU grafik, penskalaannya DINYATAKAN, dan nilai mentahnya tetap ada di rekap', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // SATU elemen grafik, tiga seri.
    expect(wrapper.findAll('[data-testid="quality-trend-chart"]')).toHaveLength(1)
    for (const series of ['ffa', 'moisture', 'dobi']) {
      expect(exists(wrapper, `quality-legend-${series}`)).toBe(true)
      expect(
        wrapper.get('[data-testid="quality-trend-chart"]').findAll(`[data-testid="quality-bar-${series}"]`)
          .length,
      ).toBe(DAILY_ROWS.length)
    }

    // Cara penskalaannya DINYATAKAN pada legenda — asersi ini mengikat.
    const legend = text(wrapper, 'quality-trend-legend')
    expect(legend).toContain('dinormalkan')
    expect(legend).toContain('indeks 100')
    expect(legend).toContain('4,05') // rata-rata periode FFA
    expect(text(wrapper, 'quality-trend-note')).toContain('BUKAN NILAI ASLI')

    // Nilai mentahnya tetap dirender pada tabel di bawah grafik.
    await wrapper.get('[data-testid="daily-recap-toggle"]').trigger('click')
    expect(recapRows(wrapper)[0].text()).toContain('5,80')
    expect(recapRows(wrapper)[0].text()).toContain('0,210')

    // Grafik dibungkus kontainer yang menggulir sendiri.
    expect(wrapper.get('[data-testid="quality-trend-card"]').find('.chart-scroll').exists()).toBe(true)
  })

  // Scenario 30: "tidak ada penandaan nilai di luar batas"
  it('tidak ada satu pun penanda ambang, ikon peringatan, atau pewarnaan bersyarat — bahkan untuk nilai yang jauh menyimpang', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        metrics: makeMetrics({
          ffa_percent: { min: 0.1, avg: 4.05, max: 480.0, reading_count: 240 },
          average_temperature_c: { min: -40.0, avg: 52.0, max: 999.9, reading_count: 12 },
        }),
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Nilai menyimpang dirender apa adanya.
    expect(text(wrapper, 'metric-ffa-max')).toBe('480,00')
    expect(text(wrapper, 'metric-temperature-min')).toBe('-40,0')

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

    // Seluruh kartu mutu memakai kelas yang SAMA PERSIS, termasuk yang
    // memuat nilai ekstrem.
    const cardClasses = METRIC_TESTIDS.map(
      (testid) => wrapper.get(`[data-testid="metric-${testid}"]`).attributes('class'),
    )
    expect(new Set(cardClasses).size).toBe(1)
  })

  // Scenario 31: "tata letak satu kolom pada layar ponsel"
  it('struktur satu kolom — kendali memakai kelas ber-min-height 44px, grafik dan tabel menggulir di dalam kartunya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Akar layar adalah satu kolom flex dengan overflow-x tersembunyi
    // (nilai pikselnya dibuktikan di browser test).
    expect(wrapper.get('[data-testid="laporan-storage-tank-mobile"]').classes()).toContain(
      'laporan-stg-view',
    )

    // Kendali sentuh memakai kelas yang membawa min-height/min-width 44px.
    expect(wrapper.get('[data-testid="period-select"]').element.closest('.filter-field')).not.toBeNull()
    expect(wrapper.get('[data-testid="daily-recap-toggle"]').classes()).toContain('recap-toggle')
    expect(wrapper.get('[data-testid="export-button"]').classes()).toContain('action-button')
    expect(wrapper.get('[data-testid="hamburger-button"]').classes()).toContain('hamburger-button')

    // Kedua grafik dan tabel rekap per tangki dibungkus kontainer yang
    // menggulir sendiri — bukan halaman yang bergeser.
    expect(wrapper.get('[data-testid="stock-trend-card"]').find('.chart-scroll').exists()).toBe(true)
    expect(wrapper.get('[data-testid="quality-trend-card"]').find('.chart-scroll').exists()).toBe(true)
    expect(wrapper.get('[data-testid="by-tank-card"]').find('.detail-table-wrap').exists()).toBe(true)

    await wrapper.get('[data-testid="daily-recap-toggle"]').trigger('click')
    expect(wrapper.get('[data-testid="daily-recap"]').find('.detail-table-wrap').exists()).toBe(true)
  })

  // Scenario 32: "mengunduh rincian pembacaan per slot waktu sebagai CSV"
  it('Ekspor memanggil repo.exportCsv dengan period_id yang sedang dibuka dan menyerahkan blob-nya ke saveCsvFile', async () => {
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

    expect(repoMocks.saveCsvFile).toHaveBeenCalledTimes(1)
    const [blob, filename] = repoMocks.saveCsvFile.mock.calls[0]
    expect(blob).toBeInstanceOf(Blob)
    expect(String(filename)).toMatch(/^laporan-storage-tank_.*\.csv$/)

    // Komponen TIDAK menyusun isi CSV sendiri dari data yang sedang tampil:
    // isinya dibentuk server, satu baris per slot waktu.
    expect(String(blob)).not.toContain('1.200,0')
  })
})

/* ================================================================== */
/* Pemeriksaan tambahan — bukan dari test_scenarios, tetapi menutup    */
/* jalur galat dan keputusan tampilan yang hanya ada di view           */
/* ================================================================== */

describe('LaporanStorageTankView — penanganan galat khusus view', () => {
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

describe('LaporanStorageTankView — jumlah kartu disamakan dengan layar web screen-133', () => {
  /**
   * Kalau ponsel dan web menampilkan jumlah kartu yang berbeda, itu sendiri
   * sudah kebohongan tentang produknya — dan itulah yang tercatat sebagai
   * known_issue pada screen-137 (ponsel 9 kartu, web 5). Test ini menjaga
   * agar screen-139 tidak mengulanginya.
   *
   * Laporan web merender EMPAT kartu mutu (FFA, Kadar Air, Kotoran, DOBI)
   * ditambah SATU kartu suhu rata-rata. calculated_weight_mt,
   * calculated_volume_m3, dan ketiga suhu posisi TIDAK punya kartu di sana,
   * dan karena itu tidak punya kartu di sini.
   */
  it('tepat empat kartu mutu + satu kartu suhu, dan tidak satu pun metrik dirender dua kali', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const cards = wrapper.get('[data-testid="metric-cards"]').findAll('.metric-card')
    expect(cards).toHaveLength(4)
    expect(cards.map((card) => card.attributes('data-testid'))).toEqual([
      'metric-ffa',
      'metric-moisture',
      'metric-impurities',
      'metric-dobi',
    ])

    expect(exists(wrapper, 'metric-card-temperature')).toBe(true)

    // Metrik yang SUDAH punya tempatnya sendiri tidak dirender lagi sebagai
    // kartu: calculated_weight_mt adalah angka stok di atas, dan ketiga suhu
    // posisi sengaja tidak ditampilkan berdampingan dengan kartu Suhu
    // Rata-rata agar tidak terbaca sebagai pembandingnya (alasan yang sama
    // yang ditulis laporan web).
    for (const testid of [
      'metric-calculated-weight',
      'metric-calculated-volume',
      'metric-oil-temp-top',
      'metric-oil-temp-middle',
      'metric-oil-temp-bottom',
    ]) {
      expect(exists(wrapper, testid)).toBe(false)
    }

    // Dan nilai ketiga suhu posisi tidak bocor ke layar lewat jalan lain.
    const html = wrapper.html()
    expect(html).not.toContain('61,2')
    expect(html).not.toContain('1.090,0')
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
  stock: { ...STOCK, opening_mt: 6421.5 },
})

const SUMMARY_LINE_2 = makeSummary({
  production_line: LINE_2_REF,
  stock: { ...STOCK, opening_mt: 233.5 },
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

describe('LaporanStorageTankView — Production Line wajib (screen-139)', () => {
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
    expect(exists(wrapper, 'stock-opening-mt')).toBe(false)
    expect(exists(wrapper, 'export-button')).toBe(false)
  })

  it('angka yang tampil milik SATU line — bukan jumlah kedua line', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(TWO_LINES)
    stubSummaryPerLine()

    const wrapper = await mountView()

    await selectProductionLine(wrapper, 'pl-1')
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'stock-opening-mt')).toBe('6.421,5')
    for (const forbidden of ['6.655,0', '6655']) {
      expect(wrapper.text()).not.toContain(forbidden)
    }

    // Ganti line: permintaannya membawa id baru, angka lama tidak tertinggal.
    await selectProductionLine(wrapper, 'pl-2')

    expect(repoMocks.fetchSummary).toHaveBeenLastCalledWith('per-1', {
      isAdmin: false,
      businessUnitId: null,
      productionLineId: 'pl-2',
    })
    expect(text(wrapper, 'stock-opening-mt')).toBe('233,5')
    expect(wrapper.text()).not.toContain('6.421,5')
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
    expect(text(wrapper, 'stock-opening-mt')).toBe('233,5')
  })

  it('production_line_id dari rute (dibawa screen-141) berlaku, dan menang atas ingatan', async () => {
    window.localStorage.setItem('msl_production_line_user-1', 'pl-1')
    routeQuery.production_line_id = 'pl-2'
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(TWO_LINES)
    stubSummaryPerLine()

    const wrapper = await mountView()

    expect(selectValue(wrapper, 'production-line-select')).toBe('pl-2')

    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'stock-opening-mt')).toBe('233,5')
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
