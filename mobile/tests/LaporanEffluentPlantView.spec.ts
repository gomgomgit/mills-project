/**
 * LaporanEffluentPlantView.spec.ts — screen-157--laporan-effluent-plant-mobile /
 * usecase-163--laporan-effluent-plant-mobile.
 *
 * Satu test per `test_scenarios[].component_test` pada tech spec screen-157
 * (37 skenario). Kembaran kedua belas dari pola yang sama, dari
 * LaporanSterilizerView.spec.ts (screen-135) sampai
 * LaporanKernelPlantView.spec.ts (screen-155).
 *
 * YANG DI-MOCK: repo laporan, repo Production Line, router, dan ketiga store.
 * Yang diuji adalah KEPUTUSAN LAYAR atas jawaban repo — bentuk permintaan
 * HTTP-nya milik effluentPlantReportRepo.spec.ts dan tidak diulang di sini.
 *
 * DAFTAR LINE DI-MOCK PADA '@/services/productionLineRepo', BUKAN PADA REPO
 * LAPORAN INI. Layar ini memakai ULANG
 * productionLineRepo.fetchProductionLinesForReport(null) verbatim dari
 * screen-135 — endpoint itu tidak diduplikasi di bawah prefix
 * effluent-plant-reports, dan effluentPlantReportRepo SENGAJA tidak
 * membungkusnya kedua kali. Spec menamai fungsi itu `fetchProductionLines`;
 * nama itu tidak ada di kode mana pun, dan mock di bawah mengikuti kode.
 *
 * ────────────────────────────────────────────────────────────────────────
 * ENAM HAL YANG MEMBEDAKAN LAYAR INI DARI KESEBELAS LAPORAN SEBELUMNYA
 * ────────────────────────────────────────────────────────────────────────
 * 1. EMPAT BELAS KARTU DALAM ENAM BLOK BERJUDUL, dan markup kartunya ditulis
 *    SEKALI lalu di-loop. Yang diasersi karena itu bukan "ada empat belas
 *    kartu" saja, melainkan bahwa TIAP BLOK membawa penyebutnya sendiri dan
 *    TIDAK ADA BLOK YANG MEWARISI PENYEBUT BLOK LAIN — pelajaran yang sudah
 *    terbukti pada blade screen-156. Keempat belas penyebut fixture karena
 *    itu BERBEDA-BEDA; dengan penyebut seragam satu penyebut bersama lolos
 *    tanpa terlihat.
 *
 * 2. TIDAK ADA OBJEK `target` DAN TIDAK ADA BLOK `downtime` pada payload, dan
 *    KETIADAAN KEDUANYA DINYATAKAN DI LAYAR. Diasersi atas TESTID pernyataan
 *    dan atas KETIADAAN elemen — bukan atas substring 'downtime'/'target',
 *    karena pernyataan yang membuktikan ketiadaannya sendiri memuat kedua
 *    kata itu.
 *
 * 3. SEBARAN STATUS MENGITERASI `known_values`, BUKAN kunci `counts`. Fixture
 *    memberi `fault: 0` dan satu sebaran yang KEDUA nilai dikenalnya 0: baris
 *    bernilai 0 WAJIB tetap tercetak, karena baris yang hilang terbaca
 *    sebagai "nilai ini tidak ada" padahal yang benar adalah "nilai ini tidak
 *    pernah terjadi". `not_recorded` adalah baris TERPISAH dan tidak pernah
 *    dilipat ke Off/Stop.
 *
 * 4. DUA REKAP TEKS BEBAS, DUA BAGIAN TERPISAH, dan keduanya daftar KEJADIAN
 *    {date, time_slot, text}. Fixture memberi SATU SLOT yang mengisi KEDUA
 *    kolom: tanggal + slot waktu itu muncul pada KEDUA daftar, dan itu benar.
 *
 * 5. PENANDA 'lab' DITURUNKAN DARI metrics[kolom].is_lab PADA RESPONS, bukan
 *    dari daftar nama kolom yang dipaku. Satu test memberi is_lab true pada
 *    kolom yang HARI INI bukan laboratorium, untuk membuktikan penurunannya.
 *
 * 6. SATU-SATUNYA PEMBAGIAN ATAS ANGKA LAPORAN DI SELURUH LAYAR adalah
 *    formatShare(), dan ia ada KARENA payload tidak menerbitkan `share`.
 *    Lihat blok DIVERGENSI di bawah.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DIVERGENSI SPEC-LAWAN-PAYLOAD YANG DICATAT DI SINI
 * ────────────────────────────────────────────────────────────────────────
 * unit_test_case 25 berbunyi "porsi sebaran status tidak dihitung ulang di
 * klien / state memakai share dari server". MEDAN `share` ITU TIDAK ADA pada
 * payload: EffluentPlantReportService::statusDistributionOf() menerbitkan
 * known_values + counts + not_recorded + recorded_slots dan tidak satu pun
 * porsi. Kasus itu TIDAK DAPAT DIPENUHI SEPERTI TERTULIS dan tidak dibuang:
 * yang diuji di sini adalah kontrak yang SEBENARNYA — porsi dihitung
 * formatShare() dengan rumus, pembulatan, dan pemisah desimal yang DISALIN
 * PERSIS dari helper $porsi pada blade screen-156
 * (number_format(100 * count / recordedSlots, 1)), dan penyebutnya
 * recorded_slots BUKAN expected_slots. Dua test di bawah memakunya:
 * angka porsinya diasersi digit demi digit, dan penyebut nol menghasilkan
 * '—' bukan 0,0%.
 *
 * Divergensi kedua: `metrics[kolom]` tidak membawa `label` maupun `unit`.
 * View memegang METRIC_META yang disalin dari $metrikMeta pada blade
 * screen-156 (sehingga suhu menampilkan '°C' secara bawaan), TETAPI NILAI
 * SERVER MENANG dan tidak pernah ditulis ulang — respons yang membawa
 * unit 'C' WAJIB mencetak 'C'. Keduanya diasersi.
 *
 * ── PENYEBUT TIAP KARTU ────────────────────────────────────────────────
 * Tiap kartu mencetak penyebutnya SENDIRI (`metrics[kolom].reading_count`),
 * dan TIDAK ADA penyebut bersama di layar ini — berbeda dari Kernel Plant,
 * yang mencetak "N dari M slot" dengan M seragam. coverage.filled_slots
 * dapat MELEBIHI setiap penyebut kartu, karena slot dihitung terisi atas
 * SEMBILAN BELAS kolom dan lima di antaranya bukan kolom ukur.
 *
 * KETIADAAN PENANDAAN DI LUAR BATAS DIASERSI ATAS JUMLAH ELEMEN BERKELAS,
 * bukan atas frasa maupun atas substring sumber: kalimat yang menyatakan
 * ketiadaannya sendiri memuat frasa "di luar batas".
 *
 * TANGGALNYA RELATIF, bukan kalender tetap. Nama periode pun sengaja bukan
 * nama bulan, supaya slug nama berkas ekspor tidak ikut basi.
 *
 * BENTUK GALAT — DATAR, TANPA `response`. Ketiadaan `status` itulah yang
 * membedakan "jaringan putus" dari "server menjawab 401/403/422", dan urutan
 * cabang handleError bergantung padanya.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import LaporanEffluentPlantView from '@/views/LaporanEffluentPlantView.vue'

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

/**
 * EMPAT fungsi, bukan lima: tidak ada fetchBusinessUnits, karena
 * /api/effluent-plant-reports/business-units/options Admin saja — 403 bahkan
 * SETELAH pelebaran akses Operator yang mendarat bersama layar ini, karena
 * penolakannya diangkat DI DALAM service dan bukan oleh daftar peran
 * middleware. Mock yang menambahkannya akan membuat test "tidak dipanggil"
 * selalu hijau tanpa arti.
 */
const { repoMocks } = vi.hoisted(() => ({
  repoMocks: {
    fetchPeriods: vi.fn(),
    fetchSummary: vi.fn(),
    exportCsv: vi.fn(),
    saveCsvFile: vi.fn(),
  },
}))

vi.mock('@/services/effluentPlantReportRepo', () => ({
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
const NOT_FOUND_ERROR = { message: 'Periode tidak ditemukan.', status: 404 }

/* ------------------------------------------------------------------ */
/* Tanggal relatif                                                     */
/* ------------------------------------------------------------------ */

function isoDaysFromNow(offsetDays: number): string {
  return new Date(Date.now() + offsetDays * 86_400_000).toISOString().slice(0, 10)
}

const PERIOD_START = isoDaysFromNow(-30)
const PERIOD_END = isoDaysFromNow(-1)
const PREV_START = isoDaysFromNow(-60)
const PREV_END = isoDaysFromNow(-31)
const DAY_A = isoDaysFromNow(-26)
const DAY_B = isoDaysFromNow(-25)

/* ------------------------------------------------------------------ */
/* Fixture                                                             */
/* ------------------------------------------------------------------ */

/**
 * Opsi periode — PERHATIKAN APA YANG TIDAK ADA: business_unit_name. Payload
 * /periods layar ini tidak memuatnya, jadi nama mill WAJIB datang dari
 * summary.business_unit.
 */
const PERIOD = {
  id: 'per-1',
  name: 'Periode Uji Effluent Plant',
  start_date: PERIOD_START,
  end_date: PERIOD_END,
  status: 'open',
  // DENGAN TANDA HUBUNG.
  station_type: 'effluent-plant',
  station_type_label: 'Effluent Plant',
}

const PERIOD_CLOSED = {
  id: 'per-2',
  name: 'Periode Sebelumnya',
  start_date: PREV_START,
  end_date: PREV_END,
  status: 'closed',
  station_type: 'effluent-plant',
  station_type_label: 'Effluent Plant',
}

const LINE_1 = { id: 'pl-1', name: 'Line Satu' }
const LINE_2 = { id: 'pl-2', name: 'Line Dua' }
const LINE_3 = { id: 'pl-3', name: 'Line Tiga' }
const LINES = [LINE_1, LINE_2]

/** KEEMPAT BELAS kolom ukur, urutan EffluentPlantReportService::NUMERIC_METRICS. */
const METRIC_COLUMNS = [
  'anaerobic_pond_1_ph',
  'anaerobic_pond_1_temp_c',
  'anaerobic_pond_2_ph',
  'anaerobic_pond_2_temp_c',
  'cooling_pond_ph',
  'cooling_pond_temp_c',
  'biogas_flow_rate_m3h',
  'raw_pome_feed_rate_m3h',
  'effluent_discharge_flow_rate_m3h',
  'final_discharge_ph',
  'final_discharge_bod_mgl_lab',
  'final_discharge_cod_mgl_lab',
  'final_discharge_tss_mgl_lab',
  'chemical_consumed_kgl',
]

const LAB_COLUMNS = [
  'final_discharge_bod_mgl_lab',
  'final_discharge_cod_mgl_lab',
  'final_discharge_tss_mgl_lab',
]

/** KEENAM blok berjudul, dengan jumlah kartu di dalamnya. */
const METRIC_BLOCKS = [
  { testid: 'metric-block-anaerobic-pond-1', title: 'Kolam Anaerobik 1', cards: 2 },
  { testid: 'metric-block-anaerobic-pond-2', title: 'Kolam Anaerobik 2', cards: 2 },
  { testid: 'metric-block-cooling-pond', title: 'Cooling Pond', cards: 2 },
  { testid: 'metric-block-flow', title: 'Aliran', cards: 3 },
  { testid: 'metric-block-final-discharge', title: 'Buangan Akhir', cards: 4 },
  { testid: 'metric-block-chemical', title: 'Bahan Kimia', cards: 1 },
]

function metric(
  min: number | null,
  avg: number | null,
  max: number | null,
  readingCount: number,
  isLab = false,
): Record<string, unknown> {
  return { min, avg, max, reading_count: readingCount, is_lab: isLab }
}

/**
 * KEEMPAT BELAS entri, dengan PENYEBUT YANG BERBEDA-BEDA (118..0).
 *
 * Penyebut seragam akan membuat asersi "tiap kartu mencetak penyebutnya
 * sendiri" dan "tidak ada blok yang mewarisi penyebut blok lain" selalu hijau
 * — justru dua cacat yang paling mungkin muncul pada markup yang di-loop.
 *
 * cooling_pond_temp_c sengaja membawa avg 12,5 di LUAR rentang min 10 ..
 * max 11: mustahil dari perhitungan apa pun, dan itulah yang membuat "nol
 * aritmetika di klien" dapat dibuktikan.
 *
 * chemical_consumed_kgl adalah kolom yang TIDAK PERNAH TERISI: penyebut 0,
 * seluruh angkanya null. Kartunya tetap harus dirender.
 */
function metrics(): Record<string, Record<string, unknown>> {
  return {
    anaerobic_pond_1_ph: metric(6.1, 7.4, 8.9, 118),
    anaerobic_pond_1_temp_c: metric(38, 41.2, 45, 117),
    anaerobic_pond_2_ph: metric(6.4, 7.1, 7.9, 116),
    anaerobic_pond_2_temp_c: metric(37, 40.1, 44, 115),
    cooling_pond_ph: metric(6.8, 7.2, 7.6, 114),
    cooling_pond_temp_c: metric(10, 12.5, 11, 113),
    biogas_flow_rate_m3h: metric(120, 240, 310, 90),
    raw_pome_feed_rate_m3h: metric(18.5, 24.1, 30.2, 110),
    effluent_discharge_flow_rate_m3h: metric(15.1, 21.8, 28.4, 109),
    final_discharge_ph: metric(6.2, 6.9, 7.5, 108),
    final_discharge_bod_mgl_lab: metric(42, 78, 120, 4, true),
    final_discharge_cod_mgl_lab: metric(180, 260, 340, 5, true),
    final_discharge_tss_mgl_lab: metric(60, 95, 140, 3, true),
    chemical_consumed_kgl: metric(null, null, null, 0),
  }
}

/**
 * KETIGA sebaran status — dan yang ini SENGAJA KONSISTEN secara penjumlahan
 * (60 + 8 + 0 + 50 = 118), karena porsinya diasersi digit demi digit.
 * Payload yang tidak konsisten dipakai pada test "nol aritmetika" tersendiri.
 *
 * TIDAK ADA `share` di sini, dan itu bukan kelalaian fixture — lihat blok
 * DIVERGENSI pada docblock berkas.
 */
function statusDistribution(): Record<string, Record<string, unknown>> {
  return {
    biogas_flare_status: {
      known_values: ['on', 'off', 'fault'],
      // 'fault' BERNILAI 0: nilai dikenal yang tidak pernah muncul. Barisnya
      // WAJIB tetap tercetak.
      counts: { on: 60, off: 8, fault: 0 },
      not_recorded: 50,
      recorded_slots: 118,
    },
    dosing_pump_1_status: {
      known_values: ['run', 'stop'],
      counts: { run: 60, stop: 8 },
      not_recorded: 50,
      recorded_slots: 118,
    },
    sludge_dewatering_status: {
      known_values: ['run', 'stop'],
      // KEDUA nilai dikenal 0 — tidak satu slot pun mencatatnya.
      counts: { run: 0, stop: 0 },
      not_recorded: 118,
      recorded_slots: 118,
    },
  }
}

function unitMetrics(avg: number | null, readingCount: number): Record<string, unknown> {
  return Object.fromEntries(
    METRIC_COLUMNS.map((column) => [
      column,
      metric(avg, avg, avg, readingCount, LAB_COLUMNS.includes(column)),
    ]),
  )
}

function summaryFixture(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Sungai Rambutan' },
    production_line: { id: 'pl-1', name: 'Line Satu' },
    period: {
      id: 'per-1',
      name: 'Periode Uji Effluent Plant',
      start_date: PERIOD_START,
      end_date: PERIOD_END,
      status: 'open',
    },
    has_data: true,
    coverage: {
      filled_slots: 118,
      expected_slots: 1440,
      coverage_percent: 8.2,
      effluent_plant_unit_count: 2,
      slots_per_unit_per_day: 24,
      days_in_period: 30,
      days_counted: 30,
      period_running: false,
    },
    metrics: metrics(),
    status_distribution: statusDistribution(),
    /**
     * TIGA KEJADIAN BERTEKS IDENTIK pada tiga slot berbeda — layar yang
     * mengelompokkan menurut teks mencetak satu baris dan test ini gagal.
     * Entri PERTAMA memakai tanggal + slot waktu yang SAMA dengan entri
     * pertama findings: satu slot yang mengisi keduanya muncul pada KEDUA
     * daftar, dan itu benar.
     */
    maintenance_remarks: [
      { date: DAY_A, time_slot: '07:00', text: 'Ganti membran pompa dosing' },
      { date: DAY_A, time_slot: '13:00', text: 'Ganti membran pompa dosing' },
      { date: DAY_B, time_slot: '21:00', text: 'Ganti membran pompa dosing' },
    ],
    findings: [
      { date: DAY_A, time_slot: '07:00', text: 'Busa tebal di kolam anaerobik 2' },
      { date: DAY_B, time_slot: '03:00', text: 'Bau menyengat di dekat cooling pond' },
    ],
    daily_trend: {
      raw_pome_feed_rate_m3h: [
        { date: DAY_A, avg: 22.5, reading_count: 3 },
        // null — BUKAN 0: batangnya kosong, bukan batang nol.
        { date: DAY_B, avg: null, reading_count: 0 },
      ],
      final_discharge_ph: [
        { date: DAY_A, avg: 6.8, reading_count: 3 },
        { date: DAY_B, avg: 7.1, reading_count: 4 },
      ],
    },
    by_unit: [
      { effluent_plant_id: 'EP-1', reading_rows: 70, reading_count: 70, metrics: unitMetrics(7.0, 70) },
      // Unit yang punya record tetapi TIDAK SATU PUN pembacaan.
      { effluent_plant_id: 'EP-2', reading_rows: 0, reading_count: 0, metrics: unitMetrics(null, 0) },
    ],
    daily: [
      {
        date: DAY_A,
        reading_rows: 3,
        metrics_avg: {
          anaerobic_pond_1_ph: { avg: 7.0, reading_count: 3 },
          chemical_consumed_kgl: { avg: null, reading_count: 0 },
        },
      },
      {
        date: DAY_B,
        reading_rows: 4,
        metrics_avg: {
          anaerobic_pond_1_ph: { avg: 8.0, reading_count: 4 },
          chemical_consumed_kgl: { avg: null, reading_count: 0 },
        },
      },
    ],
    // 3 + 4 = 7, dan server mengirim 99 — layar memakai angka server.
    daily_total: { reading_rows: 99 },
    total: { days_with_records: 2, reading_rows: 118 },
    ...overrides,
  }
}

const LINES_BY_ID: Record<string, { id: string; name: string }> = {
  'pl-1': LINE_1,
  'pl-2': LINE_2,
  'pl-3': LINE_3,
}

/**
 * Ringkasan yang blok `production_line`-nya DIBENTUK DARI LINE YANG DIMINTA,
 * bukan dipaku ke Line Satu.
 *
 * Layar ini mendahulukan nama line dari SERVER atas nama dari daftar lokalnya
 * (itulah line yang benar-benar dipakai saat menghitung), jadi fixture yang
 * mengabaikan argumennya akan membuat setiap asersi "line mana yang berlaku"
 * membaca jawaban yang salah — dan selalu hijau, karena jawabannya selalu
 * Line Satu.
 */
function summaryForScope(
  scope?: { productionLineId?: string | null },
  overrides: Record<string, unknown> = {},
): Record<string, unknown> {
  const line = scope?.productionLineId ? LINES_BY_ID[scope.productionLineId] : null

  return summaryFixture({ production_line: line ?? null, ...overrides })
}

/** Periode yang punya record tetapi tanpa satu slot terisi: 0,0%, BUKAN '—'. */
function emptySummaryFixture(): Record<string, unknown> {
  return summaryFixture({
    has_data: false,
    coverage: {
      filled_slots: 0,
      expected_slots: 1440,
      // Penyebutnya TERBENTUK, dan yang terukur memang nol slot.
      coverage_percent: 0,
      effluent_plant_unit_count: 2,
      slots_per_unit_per_day: 24,
      days_in_period: 30,
      days_counted: 30,
      period_running: false,
    },
    metrics: Object.fromEntries(
      METRIC_COLUMNS.map((column) => [
        column,
        metric(null, null, null, 0, LAB_COLUMNS.includes(column)),
      ]),
    ),
    status_distribution: Object.fromEntries(
      Object.entries(statusDistribution()).map(([column, distribution]) => [
        column,
        { ...distribution, counts: {}, not_recorded: 0, recorded_slots: 0 },
      ]),
    ),
    maintenance_remarks: [],
    findings: [],
    daily_trend: { raw_pome_feed_rate_m3h: [], final_discharge_ph: [] },
    by_unit: [],
    daily: [],
    daily_total: { reading_rows: 0 },
    total: { days_with_records: 0, reading_rows: 0 },
  })
}

/* ------------------------------------------------------------------ */
/* Auth store palsu — HANYA dua peran yang punya layar ini             */
/* ------------------------------------------------------------------ */

function asRole(
  role: 'operator' | 'supervisor',
  businessUnitId: string | null,
  userId = 'user-1',
): void {
  useAuthStoreMock.mockReturnValue({
    currentUser: {
      id: userId,
      username: role === 'operator' ? 'operator01' : 'supervisor01',
      name: 'Pengguna Uji',
      role,
      business_unit_id: businessUnitId,
    },
    businessUnit: businessUnitId ? { id: businessUnitId, name: 'Mill Sungai Rambutan' } : null,
    logout: logoutMock,
  })
}

/* ------------------------------------------------------------------ */
/* Bantuan mount / interaksi                                           */
/* ------------------------------------------------------------------ */

async function mountView(): Promise<VueWrapper> {
  const wrapper = mount(LaporanEffluentPlantView)
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

function text(wrapper: VueWrapper, testid: string): string {
  return wrapper.get(`[data-testid="${testid}"]`).text()
}

function exists(wrapper: VueWrapper, testid: string): boolean {
  return wrapper.find(`[data-testid="${testid}"]`).exists()
}

function cards(wrapper: VueWrapper) {
  return wrapper.findAll('[data-testid="metric-card"]')
}

/** Kartu satu kolom, dicari lewat data-metric — bukan lewat posisinya. */
function card(wrapper: VueWrapper, column: string) {
  return wrapper.get(`[data-metric="${column}"]`)
}

function block(wrapper: VueWrapper, testid: string) {
  return wrapper.get(`[data-testid="${testid}"]`)
}

beforeEach(() => {
  pushMock.mockReset()
  logoutMock.mockClear()
  useAuthStoreMock.mockReset()

  Object.keys(routeQuery).forEach((key) => delete routeQuery[key])

  repoMocks.fetchPeriods.mockReset()
  repoMocks.fetchSummary.mockReset()
  repoMocks.exportCsv.mockReset()
  repoMocks.saveCsvFile.mockReset()

  productionLineMocks.fetchProductionLinesForReport.mockReset()
  productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1])

  window.localStorage.clear()

  asRole('operator', 'bu-1')
  repoMocks.fetchPeriods.mockResolvedValue([PERIOD, PERIOD_CLOSED])
  repoMocks.fetchSummary.mockImplementation(
    (_periodId: string, scope?: { productionLineId?: string | null }) =>
      Promise.resolve(summaryForScope(scope)),
  )
})

afterEach(() => {
  vi.restoreAllMocks()
})

/* ================================================================== */
/* Skenario 1 + 2 + 30 — jalur sukses, kedua aktor, tanpa pemilih mill */
/* ================================================================== */

describe('jalur sukses', () => {
  it.each(['operator', 'supervisor'] as const)(
    'skenario 1/2: %s — seluruh bagian laporan terender, TANPA pemilih mill',
    async (role) => {
      asRole(role, 'bu-1')

      const wrapper = await mountView()
      await selectPeriod(wrapper, 'per-1')

      // Tidak ada pemilih mill di layar ini, dan itu bukan pemangkasan:
      // kedua aktornya terikat satu mill, diselesaikan server dari akun.
      expect(exists(wrapper, 'mill-select')).toBe(false)
      expect(text(wrapper, 'mill-current')).toContain('Mill Sungai Rambutan')

      for (const testid of [
        'coverage',
        'coverage-slots',
        'coverage-percent',
        'coverage-denominator',
        'coverage-days',
        'coverage-nineteen-columns-note',
        'metric-blocks-note',
        'no-target-note',
        'no-downtime-note',
        'no-flagging-note',
        'status-distribution-biogas-flare',
        'status-distribution-dosing-pump-1',
        'status-distribution-sludge-dewatering',
        'status-distribution-note',
        'maintenance-remarks',
        'findings',
        'daily-trend-raw-pome-feed',
        'daily-trend-final-discharge-ph',
        'by-unit',
        'daily-recap',
        'completeness',
        'export-button',
        'back-button',
      ]) {
        expect(exists(wrapper, testid), testid).toBe(true)
      }

      // KEENAM judul blok, dan keempat belas kartu di dalamnya.
      for (const metricBlock of METRIC_BLOCKS) {
        expect(text(wrapper, metricBlock.testid)).toContain(metricBlock.title)
      }
      expect(cards(wrapper)).toHaveLength(14)

      // Layar BACA SAJA: tidak ada satu pun medan input di luar kedua
      // pemilih <select>.
      expect(wrapper.findAll('input')).toHaveLength(0)
      expect(wrapper.findAll('textarea')).toHaveLength(0)
      expect(wrapper.findAll('form')).toHaveLength(0)
    },
  )

  it('skenario 2: kedua peran menghasilkan susunan dan teks yang sama PERSIS', async () => {
    asRole('operator', 'bu-1')
    const asOperator = await mountView()
    await selectPeriod(asOperator, 'per-1')

    asRole('supervisor', 'bu-1')
    const asSupervisor = await mountView()
    await selectPeriod(asSupervisor, 'per-1')

    // Tidak satu pun medan, kolom, atau aksi yang hanya muncul pada salah
    // satu peran: pelebaran akses Operator menambah PEMANGGIL, bukan varian
    // layar.
    expect(asOperator.text()).toBe(asSupervisor.text())
    expect(cards(asOperator)).toHaveLength(cards(asSupervisor).length)
    expect(exists(asOperator, 'mill-select')).toBe(false)
    expect(exists(asSupervisor, 'mill-select')).toBe(false)
  })

  it('skenario 30: daftar periode diminta TANPA satu argumen pun, dan cakupan hanya satu medan', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Mill diselesaikan server dari akun — tidak ada medan mill untuk dapat
    // salah terkirim.
    expect(repoMocks.fetchPeriods).toHaveBeenCalledWith()
    expect(repoMocks.fetchSummary.mock.calls[0][1]).toEqual({ productionLineId: 'pl-1' })
    expect(productionLineMocks.fetchProductionLinesForReport).toHaveBeenCalledWith(null)
    expect(JSON.stringify(repoMocks.fetchSummary.mock.calls)).not.toContain('business_unit')
    expect(JSON.stringify(repoMocks.fetchSummary.mock.calls)).not.toContain('businessUnitId')
  })

  it('skenario 30: nama mill dibaca dari blok business_unit, BUKAN dari respons /periods', async () => {
    // Entri periode TIDAK membawa business_unit_name sama sekali, dan
    // ringkasan membawa nama yang BERBEDA dari mill akun — layar yang
    // membacanya dari tempat yang salah mencetak nama akun atau undefined.
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({ business_unit: { id: 'bu-1', name: 'Mill Dari Server' } }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'mill-current')).toContain('Mill Dari Server')
    expect(wrapper.text()).not.toContain('undefined')
    for (const period of [PERIOD, PERIOD_CLOSED]) {
      expect(period).not.toHaveProperty('business_unit_name')
    }
  })

  it('opsi periode berlabel jenis stasiun, tanpa bergantung pada business_unit_name', async () => {
    const wrapper = await mountView()

    const options = wrapper.findAll('[data-testid="period-select"] option').map((o) => o.text())

    expect(options[1]).toContain('Effluent Plant — Periode Uji Effluent Plant')
    expect(options[2]).toContain('Effluent Plant — Periode Sebelumnya')
  })
})

/* ================================================================== */
/* Skenario 10 + 11 + 21 + 22 — cakupan pencatatan                     */
/* ================================================================== */

describe('cakupan pencatatan', () => {
  it('blok cakupan berada DI ATAS keenam blok kartu pada urutan DOM', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const sections = wrapper.findAll('[data-testid]').map((el) => el.attributes('data-testid'))

    // Di layar sempit pembaca melihat lebih sedikit sekaligus, jadi apa yang
    // dilihat lebih dulu menentukan lebih banyak.
    expect(sections.indexOf('coverage')).toBeLessThan(
      sections.indexOf('metric-block-anaerobic-pond-1'),
    )
    expect(sections.indexOf('coverage')).toBeLessThan(sections.indexOf('metric-card'))
  })

  it('mencetak KETIGA angka pembentuk penyebutnya, bukan hanya persennya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'coverage-slots')).toContain('118 dari 1.440 slot')
    expect(text(wrapper, 'coverage-denominator')).toContain('2 unit')
    expect(text(wrapper, 'coverage-denominator')).toContain('30 hari')
    expect(text(wrapper, 'coverage-denominator')).toContain('24 slot')
    expect(text(wrapper, 'coverage-formula')).toContain('1.440')
    expect(text(wrapper, 'coverage-days')).toContain('30 dari 30 hari')
    // Record draft IKUT terhitung, dan hal itu dinyatakan.
    expect(text(wrapper, 'draft-note')).toContain('draft')
  })

  it('keterangan 19 KOLOM dirender: slot terisi dapat melebihi penyebut setiap kartu', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const note = text(wrapper, 'coverage-nineteen-columns-note')

    expect(note).toContain('19 kolom')
    expect(note).toContain('kedua kolom teks')
    // Yang dilampaui adalah penyebut KARTU, bukan penyebut cakupan.
    expect(note).toContain('MELEBIHI penyebut setiap kartu metrik')
  })

  // skenario 21
  it('periode berjalan: keterangan penyebut berhenti di hari ini, dan hari tidak dihitung ulang', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        coverage: {
          filled_slots: 118,
          expected_slots: 576,
          coverage_percent: 20.5,
          effluent_plant_unit_count: 2,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 12,
          period_running: true,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'coverage-days')).toContain('12 dari 30 hari')
    expect(text(wrapper, 'coverage-running-note')).toContain('masih berjalan')
    // 2 × 12 × 24 = 576 dan server mengirim 576 — tetapi yang dicetak adalah
    // angka SERVER, dan persennya pun tidak diturunkan di klien.
    expect(text(wrapper, 'coverage-percent')).toBe('20,5%')
    expect(exists(wrapper, 'coverage-not-started-note')).toBe(false)
  })

  // skenario 22 — URUTAN CABANGNYA yang diuji
  it('days_counted 0 DIPERIKSA SEBELUM period_running: keterangan belum mulai yang terender', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          coverage_percent: null,
          effluent_plant_unit_count: 0,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 0,
          // KEDUANYA sekaligus: server menandai periode yang BELUM MULAI
          // sebagai masih berjalan juga, jadi memeriksa period_running lebih
          // dulu membuat keterangan "belum mulai" tidak pernah terender —
          // cacat yang nyata terjadi pada tiga layar dan diperbaiki pada
          // commit screen-148.
          period_running: true,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'coverage-not-started-note')).toBe(true)
    expect(text(wrapper, 'coverage-not-started-note')).toContain('belum mulai')
    expect(exists(wrapper, 'coverage-running-note')).toBe(false)
    expect(text(wrapper, 'coverage-percent')).toBe('—')
  })

  // skenario 11
  it('expected_slots 0: persen tanda pisah, dengan keterangan sebabnya', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          coverage_percent: null,
          effluent_plant_unit_count: 0,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 30,
          period_running: false,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'coverage-percent')).toBe('—')
    expect(exists(wrapper, 'coverage-no-expected-note')).toBe(true)
    expect(text(wrapper, 'coverage-no-expected-note')).toContain('penyebutnya belum terbentuk')
  })

  /**
   * CABANGNYA DIPUTUSKAN DARI expected_slots === 0, BUKAN DARI
   * coverage_percent === null — dan payload Effluent Plant mengirim 0.0 pada
   * keadaan penyebut-nol (lihat EffluentPlantReportService::buildSummary).
   * Memutuskannya dari null akan menampilkan '0,0%' justru di tempat '—'
   * seharusnya.
   */
  it('coverage_percent 0.0 DENGAN expected_slots 0: tetap tanda pisah, bukan 0,0%', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          // 0.0, BUKAN null — ini yang benar-benar dikirim server.
          coverage_percent: 0,
          effluent_plant_unit_count: 0,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 30,
          period_running: false,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'coverage-percent')).toBe('—')
    expect(text(wrapper, 'coverage-percent')).not.toContain('0,0%')
    expect(exists(wrapper, 'coverage-no-expected-note')).toBe(true)
  })

  // skenario 10
  it('skenario 10: periode punya record tetapi tanpa slot terisi — 0,0%, BUKAN tanda pisah', async () => {
    repoMocks.fetchSummary.mockResolvedValue(emptySummaryFixture())

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Penyebutnya TERBENTUK (1.440), dan yang terukur memang nol slot.
    expect(text(wrapper, 'coverage-percent')).toBe('0,0%')
    expect(text(wrapper, 'coverage-percent')).not.toContain('—')
    expect(exists(wrapper, 'coverage-no-expected-note')).toBe(false)

    // Keterangan periode kosong dirender, dan KEEMPAT BELAS kartu tetap ada
    // dengan 'tidak tersedia' — bukan 0.
    expect(exists(wrapper, 'empty-period')).toBe(true)
    expect(cards(wrapper)).toHaveLength(14)
    expect(card(wrapper, 'anaerobic_pond_1_ph').text()).toContain('tidak tersedia')
    expect(card(wrapper, 'anaerobic_pond_1_ph').text()).not.toContain('0,00')

    // Blok CAKUPAN TETAP dirender — justru itu yang menjelaskan kekosongannya.
    expect(exists(wrapper, 'coverage')).toBe(true)
    expect(exists(wrapper, 'status-distribution-biogas-flare')).toBe(true)
    // Rekap dan grafik TIDAK digambar: tabel kosong terbaca sebagai hasil
    // pengukuran bernilai nol.
    expect(exists(wrapper, 'by-unit')).toBe(false)
    expect(exists(wrapper, 'daily-recap')).toBe(false)
    expect(exists(wrapper, 'daily-trend-raw-pome-feed')).toBe(false)
  })
})

/* ================================================================== */
/* Skenario 12 + 13 + 14 + 34 — empat belas kartu dalam enam blok      */
/* ================================================================== */

describe('empat belas kartu dalam enam blok berjudul', () => {
  it('TEPAT empat belas kartu, dalam urutan kanonis, satu data-metric per kolom', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(cards(wrapper)).toHaveLength(14)
    expect(cards(wrapper).map((element) => element.attributes('data-metric'))).toEqual(
      METRIC_COLUMNS,
    )
  })

  it('TEPAT empat belas kartu juga ketika has_data false dan tiap kolom berpenyebut nol', async () => {
    repoMocks.fetchSummary.mockResolvedValue(emptySummaryFixture())

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(cards(wrapper)).toHaveLength(14)
    expect(
      wrapper
        .findAll('[data-testid="metric-denominator"]')
        .every((element) => element.text().includes('dari 0 pembacaan')),
    ).toBe(true)
  })

  /**
   * CACAT YANG PALING MUNGKIN PADA MARKUP YANG DI-LOOP: sebuah blok yang
   * MEWARISI penyebut blok lain. Karena itu yang diasersi di sini bukan
   * jumlah kartunya saja melainkan jumlah kartu PER BLOK dan penyebut PER
   * KARTU di dalamnya — pelajaran yang sudah terbukti pada blade screen-156.
   */
  it('tiap blok membawa kartunya sendiri, dan tidak satu blok pun mewarisi penyebut blok lain', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const expectedCounts = metrics()

    for (const metricBlock of METRIC_BLOCKS) {
      const blockCards = block(wrapper, metricBlock.testid).findAll(
        '[data-testid="metric-card"]',
      )

      expect(blockCards, metricBlock.testid).toHaveLength(metricBlock.cards)

      for (const blockCard of blockCards) {
        const column = blockCard.attributes('data-metric') as string
        const denominator = blockCard.get('[data-testid="metric-denominator"]').text()

        expect(denominator, `${metricBlock.testid}/${column}`).toContain(
          `dari ${expectedCounts[column].reading_count as number} pembacaan`,
        )
      }
    }

    // KEEMPAT BELAS penyebut BERBEDA-BEDA (dua di antaranya kebetulan sama
    // tidak dipakai): dengan penyebut seragam, penyebut bersama lolos tanpa
    // terlihat.
    const denominators = wrapper
      .findAll('[data-testid="metric-denominator"]')
      .map((element) => element.text().trim())

    expect(denominators).toHaveLength(14)
    expect(new Set(denominators).size).toBe(14)
  })

  it('TIDAK ADA total per blok: judulnya penanda tempat, bukan agregat', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    for (const metricBlock of METRIC_BLOCKS) {
      const blockText = text(wrapper, metricBlock.testid)

      expect(blockText, metricBlock.testid).toContain(`dari ${METRIC_BLOCKS.length}`)
      expect(blockText.toLowerCase(), metricBlock.testid).not.toContain('total blok')
      expect(blockText.toLowerCase(), metricBlock.testid).not.toContain('jumlah blok')
    }

    // Dan keterangan mengapa pengelompokannya murni penanda tempat ada di
    // layar, UTUH.
    expect(text(wrapper, 'metric-blocks-note')).toContain('murni penanda tempat')
    expect(text(wrapper, 'metric-blocks-note')).toContain('tidak ada total per blok')
  })

  it('min, rata-rata, dan maks ketiganya dirender pada kartu yang sama, apa adanya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const ph = card(wrapper, 'anaerobic_pond_1_ph').text()

    expect(ph).toContain('terendah')
    expect(ph).toContain('6,10')
    expect(ph).toContain('rata-rata')
    expect(ph).toContain('7,40')
    expect(ph).toContain('tertinggi')
    expect(ph).toContain('8,90')
  })

  // skenario 12
  it('skenario 12: satu kolom kosong sepanjang periode — kartunya TETAP ada, penyebut 0', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Tiga belas kolom lain terisi; chemical_consumed_kgl tidak.
    expect(cards(wrapper)).toHaveLength(14)

    const chemical = card(wrapper, 'chemical_consumed_kgl')

    expect(chemical.text()).toContain('tidak tersedia')
    expect(chemical.get('[data-testid="metric-denominator"]').text()).toContain(
      'dari 0 pembacaan',
    )
    // Dan kartu itu memang berada di dalam blok Bahan Kimia, bukan hilang.
    expect(text(wrapper, 'metric-block-chemical')).toContain('Konsumsi Bahan Kimia')
  })

  // skenario 34
  it('skenario 34: tidak ada kolom target maupun rencana koreksi pada satu pun kartu', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Diasersi atas KETIADAAN ELEMEN — Kernel Plant dan Depricarping mobile
    // membawa .metric-standard / testid target di tiap kartunya.
    expect(wrapper.findAll('.metric-standard')).toHaveLength(0)
    expect(wrapper.findAll('[data-testid="metric-target"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-testid="metric-corrective-action"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-testid="targets-without-metric"]')).toHaveLength(0)

    for (const blockCard of cards(wrapper)) {
      expect(blockCard.text(), blockCard.attributes('data-metric')).not.toContain('Rencana Tindakan')
    }

    // Dan sebabnya DINYATAKAN, bukan dibiarkan terbaca sebagai bagian yang
    // gagal dimuat.
    expect(text(wrapper, 'no-target-note')).toContain('tidak punya tabel master target')
  })

  // skenario 33
  it('skenario 33: tidak ada bagian, kartu, maupun angka downtime di mana pun', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Diasersi atas TESTID dan JUDUL, BUKAN atas substring 'downtime':
    // pernyataan ketiadaannya sendiri memakai data-testid="no-downtime-note"
    // dan memuat kata itu, jadi penyisiran substring akan gagal pada elemen
    // yang justru membuktikan klaimnya.
    expect(exists(wrapper, 'downtime')).toBe(false)
    expect(exists(wrapper, 'downtime-total')).toBe(false)
    expect(wrapper.findAll('h2').map((h) => h.text())).not.toContain('Downtime')
    expect(wrapper.text()).not.toContain('menit')

    expect(text(wrapper, 'no-downtime-note')).toContain('tidak mencatat downtime')
  })
})

/* ================================================================== */
/* Skenario 13 — penanda lab DI DALAM kartunya                         */
/* ================================================================== */

describe('penanda laboratorium', () => {
  it('penanda lab dirender DI DALAM ketiga kartu lab, bukan sebagai satu keterangan halaman', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(wrapper.findAll('[data-testid="metric-lab-badge"]')).toHaveLength(3)

    for (const column of LAB_COLUMNS) {
      const labCard = card(wrapper, column)

      // DI DALAM kartunya: di ponsel pembaca melihat satu kartu pada satu
      // waktu, jadi keterangan di atas halaman tidak hadir saat angkanya
      // dibaca.
      expect(labCard.find('[data-testid="metric-lab-badge"]').exists(), column).toBe(true)
      expect(labCard.find('[data-testid="metric-lab-note"]').exists(), column).toBe(true)
      expect(labCard.get('[data-testid="metric-lab-note"]').text()).toContain('laboratorium')
      expect(labCard.get('[data-testid="metric-lab-note"]').text()).toContain('keadaan normal')
    }

    // Dan TIDAK pada sebelas kartu lainnya.
    for (const column of METRIC_COLUMNS.filter((c) => !LAB_COLUMNS.includes(c))) {
      expect(card(wrapper, column).find('[data-testid="metric-lab-badge"]').exists(), column)
        .toBe(false)
    }
  })

  /**
   * DITURUNKAN DARI metrics[kolom].is_lab PADA RESPONS, bukan dari daftar
   * nama kolom yang dipaku di template — supaya kolom laboratorium KEEMPAT
   * kelak ditandai benar tanpa menyunting satu berkas klien pun. Pelajaran
   * yang sama dengan shares_standard_with di Kernel Plant.
   */
  it('kolom laboratorium KEEMPAT dari respons ikut bertanda, tanpa menyentuh berkas view', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        metrics: {
          ...metrics(),
          // HARI INI bukan laboratorium; server menandainya lab.
          final_discharge_ph: metric(6.2, 6.9, 7.5, 12, true),
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(wrapper.findAll('[data-testid="metric-lab-badge"]')).toHaveLength(4)
    expect(card(wrapper, 'final_discharge_ph').find('[data-testid="metric-lab-badge"]').exists())
      .toBe(true)
  })

  it('is_lab false pada kolom yang biasanya lab: penandanya HILANG untuknya', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        metrics: {
          ...metrics(),
          final_discharge_bod_mgl_lab: metric(42, 78, 120, 4, false),
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // DUA, bukan tiga: penandanya memang diturunkan dari respons.
    expect(wrapper.findAll('[data-testid="metric-lab-badge"]')).toHaveLength(2)
    expect(
      card(wrapper, 'final_discharge_bod_mgl_lab')
        .find('[data-testid="metric-lab-badge"]')
        .exists(),
    ).toBe(false)
  })

  // skenario 13
  it('skenario 13: ketiga nilai lab kosong sepanjang periode — kartunya tetap ada, tanpa nada kegagalan', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        metrics: {
          ...metrics(),
          final_discharge_bod_mgl_lab: metric(null, null, null, 0, true),
          final_discharge_cod_mgl_lab: metric(null, null, null, 0, true),
          final_discharge_tss_mgl_lab: metric(null, null, null, 0, true),
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    for (const column of LAB_COLUMNS) {
      const labCard = card(wrapper, column)

      expect(labCard.text(), column).toContain('tidak tersedia')
      expect(labCard.get('[data-testid="metric-denominator"]').text()).toContain(
        'dari 0 pembacaan',
      )
      expect(labCard.find('[data-testid="metric-lab-badge"]').exists(), column).toBe(true)
    }

    // Sebelas kartu lain tetap menampilkan angkanya normal.
    expect(card(wrapper, 'anaerobic_pond_1_ph').text()).toContain('7,40')

    // Dan tidak ada teks bernada kegagalan pencatatan.
    expect(wrapper.text().toLowerCase()).not.toContain('gagal mencatat')
    expect(wrapper.text().toLowerCase()).not.toContain('data hilang')
  })

  it('penyebut lab yang satu orde lebih kecil TIDAK diselaraskan dengan sebelas kartu lain', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(card(wrapper, 'final_discharge_bod_mgl_lab')
      .get('[data-testid="metric-denominator"]').text()).toContain('dari 4 pembacaan')
    expect(card(wrapper, 'final_discharge_tss_mgl_lab')
      .get('[data-testid="metric-denominator"]').text()).toContain('dari 3 pembacaan')
    expect(card(wrapper, 'anaerobic_pond_1_ph')
      .get('[data-testid="metric-denominator"]').text()).toContain('dari 118 pembacaan')
  })
})

/* ================================================================== */
/* Skenario 14 — slot yang hanya mengisi teks bebas                    */
/* ================================================================== */

describe('slot yang hanya mengisi teks bebas', () => {
  it('skenario 14: filled_slots melampaui penyebut setiap kartu, dan tidak dikoreksi di klien', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        coverage: {
          // 130 > 118, penyebut tertinggi di antara keempat belas kartu.
          filled_slots: 130,
          expected_slots: 1440,
          coverage_percent: 9.0,
          effluent_plant_unit_count: 2,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 30,
          period_running: false,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'coverage-slots')).toContain('130 dari 1.440 slot')

    const denominators = wrapper
      .findAll('[data-testid="metric-denominator"]')
      .map((element) => Number(element.text().match(/dari ([\d.]+) pembacaan/)?.[1]?.replace(/\./g, '')))

    expect(denominators).toHaveLength(14)
    // Yang dilampaui adalah penyebut KARTU, bukan penyebut CAKUPAN — itu
    // benar, bukan selisih yang perlu dicocokkan.
    expect(denominators.every((value) => value < 130)).toBe(true)

    expect(text(wrapper, 'coverage-nineteen-columns-note')).toContain('kedua kolom teks')
  })
})

/* ================================================================== */
/* Skenario 15 + 16 — sebaran status                                   */
/* ================================================================== */

describe('sebaran status', () => {
  it('KETIGA sebaran dirender, masing-masing dengan judul dan tabelnya sendiri', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'status-distribution-biogas-flare')).toContain('Sebaran Biogas Flare')
    expect(text(wrapper, 'status-distribution-dosing-pump-1')).toContain('Sebaran Pompa Dosing 1')
    expect(text(wrapper, 'status-distribution-sludge-dewatering'))
      .toContain('Sebaran Sludge Dewatering')

    // KETIGANYA tidak pernah muncul sebagai kartu metrik: terendah,
    // rata-rata, dan tertinggi tidak punya arti untuk kolom kategoris.
    for (const column of [
      'biogas_flare_status',
      'dosing_pump_1_status',
      'sludge_dewatering_status',
    ]) {
      expect(wrapper.find(`[data-metric="${column}"]`).exists(), column).toBe(false)
    }
    expect(text(wrapper, 'status-distribution-note')).toContain('kategoris')
  })

  // skenario 16
  it('skenario 16: nilai dikenal bernilai 0 TETAP tercetak — diiterasi dari known_values', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const flare = block(wrapper, 'status-distribution-biogas-flare')
    const rows = flare.findAll('[data-testid="status-row"]')

    // TIGA baris: On, Off, DAN Fault — meski Fault bernilai 0.
    // Mengiterasi kunci `counts` yang ada tetap memberi tiga di sini, jadi
    // test berikutnya memberi `counts` yang BENAR-BENAR kehilangan kuncinya.
    expect(rows).toHaveLength(3)
    expect(rows.map((row) => row.attributes('data-status-value'))).toEqual(['on', 'off', 'fault'])
    expect(flare.get('[data-status-value="fault"]').text()).toContain('Fault')
    expect(flare.get('[data-status-value="fault"]').text()).toContain('0')

    // Dan jumlah slot bergangguan tetap DITONJOLKAN walau 0.
    expect(text(wrapper, 'biogas-flare-fault-count')).toContain('0 slot bergangguan')
  })

  /**
   * INI yang membedakan "mengiterasi known_values" dari "mengiterasi kunci
   * counts": `counts` di sini KEHILANGAN kunci 'fault' sama sekali, bukan
   * membawanya bernilai 0. Layar yang mengiterasi counts mencetak DUA baris
   * dan test ini gagal.
   */
  it('counts yang kehilangan sebuah kunci dikenal TETAP menghasilkan barisnya, bernilai 0', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        status_distribution: {
          ...statusDistribution(),
          biogas_flare_status: {
            known_values: ['on', 'off', 'fault'],
            // 'fault' TIDAK ADA sebagai kunci.
            counts: { on: 60, off: 8 },
            not_recorded: 50,
            recorded_slots: 118,
          },
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const flare = block(wrapper, 'status-distribution-biogas-flare')

    expect(flare.findAll('[data-testid="status-row"]')).toHaveLength(3)
    expect(flare.get('[data-status-value="fault"]').text()).toContain('Fault')
    // Baris yang hilang terbaca sebagai "nilai ini tidak ada" padahal yang
    // benar adalah "nilai ini tidak pernah terjadi".
    expect(flare.get('[data-status-value="fault"]').text()).toContain('0')
  })

  it('nilai di luar peta label dicetak APA ADANYA, bukan disembunyikan', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        status_distribution: {
          ...statusDistribution(),
          biogas_flare_status: {
            // Nilai BARU dari migrasi kelak — harus terlihat, bukan hilang.
            known_values: ['on', 'off', 'fault', 'maintenance'],
            counts: { on: 60, off: 8, fault: 0, maintenance: 4 },
            not_recorded: 46,
            recorded_slots: 118,
          },
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const flare = block(wrapper, 'status-distribution-biogas-flare')

    expect(flare.findAll('[data-testid="status-row"]')).toHaveLength(4)
    expect(flare.get('[data-status-value="maintenance"]').text()).toContain('maintenance')
  })

  // skenario 15
  it('skenario 15: seluruh slot tidak mencatat flare — ketiga nilai 0, not_recorded TERPISAH', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        status_distribution: {
          ...statusDistribution(),
          biogas_flare_status: {
            known_values: ['on', 'off', 'fault'],
            counts: { on: 0, off: 0, fault: 0 },
            // SELURUH slot terisi.
            not_recorded: 118,
            recorded_slots: 118,
          },
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const flare = block(wrapper, 'status-distribution-biogas-flare')

    for (const value of ['on', 'off', 'fault']) {
      expect(flare.get(`[data-status-value="${value}"]`).text(), value).toContain('0')
    }

    // Baris 'tidak tercatat' TERPISAH, membawa seluruh slot terisi — dan
    // TIDAK dilipat ke baris Mati: kolom yang kosong berarti tidak ada yang
    // mencatatnya, bukan flare yang mati.
    const notRecorded = flare.get('[data-testid="status-not-recorded"]').text()

    expect(notRecorded).toContain('Tidak tercatat')
    expect(notRecorded).toContain('118')
    expect(flare.get('[data-status-value="off"]').text()).not.toContain('118')
    expect(text(wrapper, 'status-distribution-note')).toContain('tidak pernah')
  })

  /**
   * DIVERGENSI SPEC-LAWAN-PAYLOAD — lihat docblock berkas.
   *
   * Payload tidak menerbitkan `share`, jadi porsinya dihitung formatShare()
   * di view, dengan rumus dan pembulatan yang DISALIN PERSIS dari helper
   * $porsi pada blade screen-156. Diasersi digit demi digit justru supaya
   * ponsel tidak mungkin mencetak porsi yang berbeda dari web.
   */
  it('porsi dihitung dari counts/recorded_slots persis seperti blade web, satu desimal', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const flare = block(wrapper, 'status-distribution-biogas-flare')

    // 100 × 60 / 118 = 50,847 -> 50,8%
    expect(flare.get('[data-status-value="on"]').text()).toContain('50,8%')
    // 100 × 8 / 118 = 6,779 -> 6,8%
    expect(flare.get('[data-status-value="off"]').text()).toContain('6,8%')
    // 0 tetap 0,0% — penyebutnya ada.
    expect(flare.get('[data-status-value="fault"]').text()).toContain('0,0%')
    // 100 × 50 / 118 = 42,37 -> 42,4%
    expect(flare.get('[data-testid="status-not-recorded"]').text()).toContain('42,4%')
  })

  it('PENYEBUT porsinya recorded_slots, BUKAN expected_slots', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const flare = block(wrapper, 'status-distribution-biogas-flare')

    // Dengan expected_slots 1.440 sebagai penyebut, 60 menjadi 4,2% — bukan
    // 50,8%. Penyebut kedua itu akan mencampur slot tak tersentuh ke dalam
    // porsi sebuah nilai yang justru tercatat.
    expect(flare.get('[data-status-value="on"]').text()).not.toContain('4,2%')
    expect(text(wrapper, 'status-distribution-biogas-flare')).toContain('118 slot tercatat')
    expect(text(wrapper, 'status-distribution-note')).toContain('slot TERCATAT sebagai penyebut')
  })

  it('penyebut nol menghasilkan tanda pisah, bukan 0,0%', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        status_distribution: {
          ...statusDistribution(),
          biogas_flare_status: {
            known_values: ['on', 'off', 'fault'],
            counts: { on: 0, off: 0, fault: 0 },
            not_recorded: 0,
            recorded_slots: 0,
          },
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const flare = block(wrapper, 'status-distribution-biogas-flare')

    expect(flare.get('[data-status-value="on"]').text()).toContain('—')
    expect(flare.get('[data-status-value="on"]').text()).not.toContain('0,0%')
    expect(flare.get('[data-testid="status-recorded-total"]').text()).toContain('—')
  })

  it('baris total kaki tabel menyatakan 100,0% hanya ketika ada slot tercatat', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(
      block(wrapper, 'status-distribution-biogas-flare')
        .get('[data-testid="status-recorded-total"]')
        .text(),
    ).toContain('100,0%')
  })

  it('KEDUA nilai dikenal bernilai 0 tetap dua baris, dengan not_recorded penuh', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const sludge = block(wrapper, 'status-distribution-sludge-dewatering')

    expect(sludge.findAll('[data-testid="status-row"]')).toHaveLength(2)
    expect(sludge.get('[data-status-value="run"]').text()).toContain('Run')
    expect(sludge.get('[data-status-value="stop"]').text()).toContain('Stop')
    expect(sludge.get('[data-testid="status-not-recorded"]').text()).toContain('118')
  })
})

/* ================================================================== */
/* Skenario 17 + 18 + 19 — dua rekap teks bebas                        */
/* ================================================================== */

describe('rekap teks bebas', () => {
  it('skenario 17: dua bagian berjudul TERPISAH, dan satu slot muncul pada KEDUA daftar', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const maintenance = block(wrapper, 'maintenance-remarks')
    const findings = block(wrapper, 'findings')

    expect(maintenance.text()).toContain('Rekap Catatan Perawatan')
    expect(findings.text()).toContain('Rekap Temuan')

    // TIGA kejadian berteks identik tetap TIGA baris — bukan satu baris
    // dengan "3 slot": pengelompokan menurut teks menghilangkan tanggal dan
    // slot waktu yang justru satu-satunya cara menemukan kembali kejadiannya.
    expect(maintenance.findAll('[data-testid="maintenance-remark-row"]')).toHaveLength(3)
    expect(findings.findAll('[data-testid="finding-row"]')).toHaveLength(2)

    // SLOT YANG SAMA pada KEDUA daftar, dengan teksnya masing-masing.
    const firstMaintenance = maintenance
      .findAll('[data-testid="maintenance-remark-row"]')[0]
      .text()
    const firstFinding = findings.findAll('[data-testid="finding-row"]')[0].text()

    expect(firstMaintenance).toContain('07:00')
    expect(firstFinding).toContain('07:00')
    expect(firstMaintenance).toContain('Ganti membran pompa dosing')
    expect(firstFinding).toContain('Busa tebal di kolam anaerobik 2')

    // Tidak ada satu daftar gabungan yang memuat slot itu dua kali.
    expect(exists(wrapper, 'free-text-combined')).toBe(false)
    expect(text(wrapper, 'findings-note')).toContain('bukan penghitungan ganda')
  })

  it('judul masing-masing bagian menyebut jumlah SLOT-nya sendiri, dan keduanya tidak dijumlahkan', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'maintenance-remarks')).toContain('Rekap Catatan Perawatan (3 slot)')
    expect(text(wrapper, 'findings')).toContain('Rekap Temuan (2 slot)')
    expect(text(wrapper, 'findings-note')).toContain('tidak boleh dijumlahkan')
  })

  it('skenario 18: kedua daftar kosong — keduanya tetap dirender dengan keterangannya', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({ maintenance_remarks: [], findings: [] }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Kedua bagian tetap ada DENGAN judulnya yang terpisah.
    expect(exists(wrapper, 'maintenance-remarks')).toBe(true)
    expect(exists(wrapper, 'findings')).toBe(true)
    expect(exists(wrapper, 'maintenance-remarks-empty')).toBe(true)
    expect(exists(wrapper, 'findings-empty')).toBe(true)
    expect(text(wrapper, 'maintenance-remarks-empty')).toContain('Tidak ada satu pun catatan')
    expect(text(wrapper, 'findings-empty')).toContain('Tidak ada satu pun temuan')

    // Dan tidak ada tabel kosong tanpa penjelasan.
    expect(wrapper.findAll('[data-testid="maintenance-remark-row"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-testid="finding-row"]')).toHaveLength(0)
  })

  it('skenario 19: teks satu paragraf penuh hadir UTUH, tanpa pemotongan maupun elipsis', async () => {
    const paragraph =
      'Ditemukan busa tebal berwarna cokelat gelap menutupi hampir seluruh permukaan kolam '
      + 'anaerobik kedua sejak giliran pagi, disertai bau menyengat yang tercium sampai area '
      + 'cooling pond; operator sudah menurunkan laju umpan POME dan menambah dosis antifoam, '
      + 'namun ketebalan busanya belum berkurang sampai akhir giliran malam, sehingga perlu '
      + 'diperiksa kembali pada hari berikutnya bersama bagian pemeliharaan.'

    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        findings: [{ date: DAY_A, time_slot: '07:00', text: paragraph }],
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const row = wrapper.get('[data-testid="finding-row"]')

    // SELURUH isi teks hadir utuh — tidak ada pemotongan maupun elipsis yang
    // menghilangkan isi.
    expect(row.text()).toContain(paragraph)
    expect(row.text()).not.toContain('…')
    expect(row.text()).not.toContain('baca selengkapnya')
    // Dan gaya pembungkusannya diterapkan: kelas .note-text, yang
    // terdefinisi di blok <style> dan memakai overflow-wrap.
    expect(row.find('.note-text').exists()).toBe(true)
    expect(text(wrapper, 'maintenance-remarks-note')).toContain('dibungkus utuh tanpa pemotongan')
  })
})

/* ================================================================== */
/* Skenario 20 — rekap per unit                                        */
/* ================================================================== */

describe('rekap per unit', () => {
  it('skenario 20: satu baris per unit termasuk unit tanpa pembacaan, dan total dari metrics periode', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        by_unit: [
          { effluent_plant_id: 'EP-1', reading_rows: 70, reading_count: 70, metrics: unitMetrics(7.0, 70) },
          { effluent_plant_id: 'EP-2', reading_rows: 48, reading_count: 48, metrics: unitMetrics(8.0, 48) },
          // Unit yang punya record tetapi TIDAK SATU PUN pembacaan.
          { effluent_plant_id: 'EP-3', reading_rows: 0, reading_count: 0, metrics: unitMetrics(null, 0) },
        ],
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const rows = wrapper.findAll('[data-testid="by-unit-row"]')

    expect(rows).toHaveLength(3)
    expect(rows.map((row) => row.text())).toEqual([
      expect.stringContaining('EP-1'),
      expect.stringContaining('EP-2'),
      expect.stringContaining('EP-3'),
    ])
    // Membuangnya justru akan menyembunyikan unit yang tidak pernah
    // dituliskan.
    expect(rows[2].get('[data-testid="by-unit-reading-rows"]').text()).toBe('0')
    expect(rows[2].text()).toContain('—')
    expect(text(wrapper, 'by-unit')).toContain('Rekap per Unit Effluent Plant (3 unit)')

    // Baris gabungan DIAMBIL dari `metrics` tingkat periode, yang dihitung
    // ulang server atas seluruh slot — bukan dirata-ratakan dari ketiga baris
    // di atasnya (yang akan memberi 7,5).
    const total = wrapper.get('[data-testid="by-unit-row-total"]').text()

    expect(total).toContain('Gabungan seluruh unit')
    expect(total).toContain('7,40')
    expect(total).not.toContain('7,50')
  })

  it('nama unit kosong tetap berlabel, bukan sel kosong', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        by_unit: [
          { effluent_plant_id: '   ', reading_rows: 5, reading_count: 5, metrics: unitMetrics(7.0, 5) },
        ],
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // effluent_plant_id adalah kolom TEKS yang diketik di layar input dan
    // bukan kunci asing, jadi nilai kosong mungkin saja sampai ke sini —
    // barisnya tetap diberi label, karena sel tanpa label membuat seluruh
    // baris terbaca sebagai cacat tampilan.
    expect(wrapper.get('[data-testid="by-unit-row"]').text()).toContain('Belum diisi')
  })

  it('kedua tabel rekap membawa KEEMPAT BELAS kolom rata-rata, bukan subhimpunan', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Unit + Slot terisi + 14 kolom = 16 judul.
    expect(wrapper.get('[data-testid="by-unit-table"]').findAll('thead th')).toHaveLength(16)
    expect(wrapper.get('[data-testid="daily-table"]').findAll('thead th')).toHaveLength(16)

    // Dan penanda lab ikut pada judul kolom KEDUA tabel, diturunkan dari
    // respons.
    const headers = wrapper.get('[data-testid="by-unit-table"]').findAll('thead th')
      .map((th) => th.text())

    expect(headers.filter((header) => header.includes('lab'))).toHaveLength(3)
    expect(headers).toContain('BOD (mg/L) · lab')
  })
})

/* ================================================================== */
/* Skenario 23 — periode tertutup                                      */
/* ================================================================== */

describe('periode tertutup', () => {
  it('skenario 23: badge Tertutup, laporan penuh, dan ekspor TIDAK dinonaktifkan', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        period: {
          id: 'per-2',
          name: 'Periode Sebelumnya',
          start_date: PREV_START,
          end_date: PREV_END,
          status: 'closed',
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-2')

    expect(text(wrapper, 'period-status-badge')).toContain('Tertutup')
    expect(text(wrapper, 'period-meta')).toContain('Periode Sebelumnya')
    expect(cards(wrapper)).toHaveLength(14)
    expect(exists(wrapper, 'by-unit')).toBe(true)
    expect(exists(wrapper, 'daily-recap')).toBe(true)

    // Kunci periode mengatur PENULISAN data, bukan pembacaan laporan.
    expect(wrapper.get('[data-testid="export-button"]').attributes('disabled')).toBeUndefined()
    expect(text(wrapper, 'period-meta')).toContain('tetap dapat dilihat dan diekspor')
  })
})

/* ================================================================== */
/* Skenario 3 + 4 + 5 + 6 + 7 + 8 — Production Line                    */
/* ================================================================== */

describe('Production Line', () => {
  it('skenario 6: memakai ingatan perangkat milik pengguna ITU, pada kunci milik Station List', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)
    window.localStorage.setItem('msl_production_line_user-1', 'pl-2')
    window.localStorage.setItem('msl_production_line_user-lain', 'pl-1')

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Kunci penyimpanannya SAMA PERSIS dengan yang ditulis StationListView —
    // line adalah konteks kerja satu orang pada satu shift, bukan pengaturan
    // per layar.
    expect(text(wrapper, 'production-line-current')).toContain('Line Dua')
    expect(exists(wrapper, 'production-line-required-hint')).toBe(false)
    expect(repoMocks.fetchSummary).toHaveBeenCalledWith(
      'per-1',
      expect.objectContaining({ productionLineId: 'pl-2' }),
    )
  })

  it('memakai line dari query rute, MENGALAHKAN ingatan perangkat', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1, LINE_2, LINE_3])
    window.localStorage.setItem('msl_production_line_user-1', 'pl-2')
    routeQuery.production_line_id = 'pl-3'

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'production-line-current')).toContain('Line Tiga')
    // Dan pilihan yang dibawa screen-141 ikut MENJADI ingatan.
    expect(window.localStorage.getItem('msl_production_line_user-1')).toBe('pl-3')
  })

  it('satu line: berlaku tanpa pemilih, tetapi namanya tetap tampil', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1])

    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-select')).toBe(false)
    expect(text(wrapper, 'production-line-current')).toContain('Line Satu')
  })

  it('skenario 7: line yang diingat sudah tidak ada — pemilih kembali, TIDAK ada line lain yang ditebak', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)
    window.localStorage.setItem('msl_production_line_user-1', 'pl-99')

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'production-line-select')).toBe(true)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(exists(wrapper, 'production-line-current')).toBe(false)
    // Line 7 maupun 8 TIDAK dipilih sebagai penggantinya.
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
  })

  it('skenario 8: localStorage yang melempar diperlakukan sebagai "tidak ada ingatan", bukan galat', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)

    const spy = vi.spyOn(window.localStorage, 'getItem').mockImplementation(() => {
      throw new Error('SecurityError')
    })

    const wrapper = await mountView()

    // Layar tetap utuh, dan yang terjadi hanyalah pemilih yang muncul.
    expect(exists(wrapper, 'laporan-effluent-plant-mobile')).toBe(true)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)

    spy.mockRestore()
  })

  it('kegagalan MENULIS penyimpanan tidak menjatuhkan alur: line tetap berlaku dan ringkasan dimuat', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)

    const spy = vi.spyOn(window.localStorage, 'setItem').mockImplementation(() => {
      throw new Error('QuotaExceededError')
    })

    const wrapper = await mountView()
    await selectLine(wrapper, 'pl-2')
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'production-line-current')).toContain('Line Dua')
    expect(exists(wrapper, 'period-meta')).toBe(true)
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)

    spy.mockRestore()
  })

  /**
   * PENJAGA LINE ADA DI FUNGSI loadSummary(), BUKAN HANYA DI TEMPLATE —
   * supaya jalur coba-lagi, ganti periode, dan pemulihan dari ingatan
   * perangkat tidak melewatinya. Penjaga yang hanya ada di template dilewati
   * oleh ketiganya, dan di layar ini melewatinya berarti 422 dari server,
   * bukan angka seluruh mill.
   */
  it('skenario 3: tanpa line berlaku, /summary TIDAK PERNAH diminta — juga sesudah Coba Lagi dan ganti periode', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1, LINE_2, LINE_3])
    productionLineMocks.fetchProductionLinesForReport.mockRejectedValueOnce(NETWORK_ERROR)

    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-retry')).toBe(true)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

    // JALUR COBA-LAGI.
    await wrapper.get('[data-testid="production-line-retry"]').trigger('click')
    await flushPromises()

    expect(exists(wrapper, 'production-line-select')).toBe(true)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

    // JALUR GANTI PERIODE, dua kali.
    await selectPeriod(wrapper, 'per-1')
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
    await selectPeriod(wrapper, 'per-2')
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

    // Dan tidak satu angka pun dirender, termasuk angka gabungan seluruh
    // mill sebagai pengganti.
    expect(cards(wrapper)).toHaveLength(0)
    expect(exists(wrapper, 'coverage')).toBe(false)

    // Begitu sebuah line berlaku, permintaannya berangkat — tanpa ini test di
    // atas juga akan hijau pada layar yang rusak total.
    await selectLine(wrapper, 'pl-2')

    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchSummary).toHaveBeenCalledWith(
      'per-2',
      expect.objectContaining({ productionLineId: 'pl-2' }),
    )
  })

  it('skenario 4: mill tanpa line — ARAHAN menghubungi Admin, TANPA tombol coba lagi', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([])

    const wrapper = await mountView()

    const notice = text(wrapper, 'production-line-unavailable')

    expect(notice).toContain('belum memiliki Production Line')
    expect(notice).toContain('hubungi Admin')
    // Mencoba lagi tidak akan mengubah sesuatu yang tidak ada.
    expect(exists(wrapper, 'production-line-retry')).toBe(false)
    expect(exists(wrapper, 'coverage')).toBe(false)
  })

  it('skenario 5: daftar line gagal dimuat — pesan BERBEDA, dengan tombol coba lagi sendiri', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockRejectedValueOnce(NETWORK_ERROR)

    const wrapper = await mountView()

    const notice = text(wrapper, 'production-line-unavailable')

    // DUA keadaan yang menuntut tindakan berbeda, jadi dua pesan berbeda.
    expect(notice).toContain('tidak dapat dimuat')
    expect(notice).toContain('Periksa koneksi jaringan Anda')
    expect(notice).not.toContain('belum memiliki Production Line')
    expect(exists(wrapper, 'production-line-retry')).toBe(true)
    // Kegagalan daftar line tidak boleh menyamar sebagai galat laporan.
    expect(exists(wrapper, 'network-error')).toBe(false)

    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1])
    await wrapper.get('[data-testid="production-line-retry"]').trigger('click')
    await flushPromises()

    expect(exists(wrapper, 'production-line-unavailable')).toBe(false)
    expect(text(wrapper, 'production-line-current')).toContain('Line Satu')
  })

  /**
   * DIVERGENSI YANG DISENGAJA DARI LaporanKernelPlantView: loadProductionLines()
   * MENERUSKAN 401 ke handleError(), yang mengarahkan ke Login TANPA pesan
   * jaringan. Pesan "daftar line gagal dimuat" pada sesi yang berakhir
   * mengirim pembaca memeriksa sinyal alih-alih masuk kembali.
   */
  it('401 dari daftar line: diarahkan ke Login, TANPA pesan kegagalan jaringan', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockRejectedValue(UNAUTHENTICATED_ERROR)

    const wrapper = await mountView()

    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    // TIDAK ADA tombol Coba Lagi dan TIDAK ADA pesan "daftar line tidak dapat
    // dimuat": 401 bukan masalah jaringan, dan mencoba lagi pada sesi yang
    // berakhir hanya menunda pembaca dari masuk kembali.
    expect(exists(wrapper, 'production-line-retry')).toBe(false)
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)
    expect(wrapper.text()).not.toContain('Daftar Production Line tidak dapat dimuat')
    // Yang TETAP tergambar adalah arahan "mill belum punya line", karena
    // daftar line dikosongkan sebelum pengalihan dan `productionLineBlocked`
    // membacanya begitu. Itu PERILAKU NYATA layar ini, bukan keadaan yang
    // dituju: router sudah diperintahkan berpindah ke Login, jadi tampilan
    // ini tidak pernah sempat dibaca pengguna. Dicatat di sini supaya
    // pembaca berikutnya tahu bahwa ia memang begitu dan bukan asersi yang
    // terlupa.
    expect(text(wrapper, 'production-line-unavailable')).toContain('belum memiliki Production Line')
  })

  it('ganti line mengosongkan ringkasan lama sebelum yang baru tiba, dan mengingat pilihannya', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)
    routeQuery.production_line_id = 'pl-1'

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'period-meta')).toBe(true)

    await selectLine(wrapper, 'pl-2')

    expect(repoMocks.fetchSummary).toHaveBeenLastCalledWith(
      'per-1',
      expect.objectContaining({ productionLineId: 'pl-2' }),
    )
    expect(window.localStorage.getItem('msl_production_line_user-1')).toBe('pl-2')
  })
})

/* ================================================================== */
/* Skenario 9 — keadaan tanpa angka                                    */
/* ================================================================== */

describe('keadaan tanpa angka', () => {
  it('akun tanpa business_unit_id: berhenti tanpa satu pun permintaan, tanpa pemilih mill', async () => {
    asRole('operator', null)

    const wrapper = await mountView()

    expect(exists(wrapper, 'no-mill-for-account')).toBe(true)
    expect(text(wrapper, 'no-mill-for-account')).toContain('hubungi Admin')
    // TIDAK ada pemilih mill sebagai gantinya.
    expect(exists(wrapper, 'mill-select')).toBe(false)
    expect(exists(wrapper, 'period-select')).toBe(false)
    expect(productionLineMocks.fetchProductionLinesForReport).not.toHaveBeenCalled()
    expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
  })

  it('skenario 9: daftar periode kosong — arahan tampil hanya SETELAH pemuatan selesai', async () => {
    let resolvePeriods: ((value: unknown) => void) | null = null

    repoMocks.fetchPeriods.mockImplementation(
      () =>
        new Promise((resolve) => {
          resolvePeriods = resolve as (value: unknown) => void
        }),
    )

    const wrapper = mount(LaporanEffluentPlantView)
    await flushPromises()

    // Daftar kosong SAAT MASIH MEMUAT terbaca sebagai "tidak ada periode" —
    // audit loading state 2026-10-05.
    expect(exists(wrapper, 'no-periods')).toBe(false)
    expect(exists(wrapper, 'periods-loading')).toBe(true)

    resolvePeriods?.([])
    await flushPromises()

    expect(exists(wrapper, 'no-periods')).toBe(true)
    expect(text(wrapper, 'no-periods')).toContain('hubungi Admin')
    // Pemilih periode TETAP dirender dalam keadaan kosong, bukan hilang.
    expect(exists(wrapper, 'period-select')).toBe(true)
    expect(wrapper.findAll('[data-testid="period-select"] option')).toHaveLength(1)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
  })
})

/* ================================================================== */
/* Skenario 24 + 26 + 27 — penanganan galat                            */
/* ================================================================== */

describe('penanganan galat', () => {
  it('skenario 24: kegagalan jaringan — pesan + Coba Lagi, dan line/periode terpilih TIDAK hilang', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)
    routeQuery.production_line_id = 'pl-2'
    repoMocks.fetchSummary.mockRejectedValueOnce(NETWORK_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'network-error')).toContain('Tidak dapat terhubung ke server.')
    expect(exists(wrapper, 'retry-button')).toBe(true)
    // Pengguna di area tanpa sinyal tidak boleh dipaksa memilih dari awal.
    expect((wrapper.get('[data-testid="period-select"]').element as HTMLSelectElement).value)
      .toBe('per-1')
    expect(text(wrapper, 'production-line-current')).toContain('Line Dua')

    await wrapper.get('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    // Coba Lagi memuat ulang periode dan line yang SAMA, tanpa pengguna
    // memilih ulang.
    expect(repoMocks.fetchSummary).toHaveBeenLastCalledWith(
      'per-1',
      expect.objectContaining({ productionLineId: 'pl-2' }),
    )
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(cards(wrapper)).toHaveLength(14)
  })

  it('skenario 26: 401 — diarahkan ke Login TANPA pesan jaringan dan tanpa tombol coba lagi', async () => {
    repoMocks.fetchSummary.mockRejectedValueOnce(UNAUTHENTICATED_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    // "Periksa koneksi Anda" pada sesi yang berakhir mengirim pembaca
    // memeriksa sinyalnya alih-alih masuk kembali.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'retry-button')).toBe(false)
    expect(wrapper.text()).not.toContain('Periksa koneksi')
    expect(exists(wrapper, 'coverage')).toBe(false)
  })

  it('401 dari /periods: diarahkan ke Login, tanpa pesan jaringan', async () => {
    repoMocks.fetchPeriods.mockRejectedValueOnce(UNAUTHENTICATED_ERROR)

    const wrapper = await mountView()

    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    expect(exists(wrapper, 'network-error')).toBe(false)
  })

  it('404 periode tidak ditemukan: pesan yang dapat dibaca, BUKAN pengalihan ke Login', async () => {
    repoMocks.fetchSummary.mockRejectedValueOnce(NOT_FOUND_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'error-message')).toContain('Periode tidak ditemukan.')
    expect(pushMock).not.toHaveBeenCalledWith({ name: 'login' })
    expect(exists(wrapper, 'network-error')).toBe(false)
  })

  /**
   * skenario 27 — PENANDA REGRESI PELEBARAN AKSES.
   *
   * Sebelum pelebaran yang mendarat bersama layar ini, keempat endpoint
   * menjawab 403 untuk Operator. Yang diuji di sini BUKAN bahwa 403 itu
   * benar — ia tidak benar lagi — melainkan bahwa 403 bukan jalur yang layar
   * ini tangani secara khusus: tidak ada angka dirender, dan TIDAK ADA
   * pengalihan ke Login (403 bukan sesi yang berakhir). Itulah sebabnya
   * pelebaran backend adalah BAGIAN dari layar ini, bukan pekerjaan yang
   * boleh menyusul.
   */
  it('skenario 27: 403 — tidak ada angka dirender, dan TIDAK ADA pengalihan ke Login', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockRejectedValue(FORBIDDEN_ERROR)
    repoMocks.fetchPeriods.mockRejectedValue(FORBIDDEN_ERROR)
    repoMocks.fetchSummary.mockRejectedValue(FORBIDDEN_ERROR)

    const wrapper = await mountView()

    expect(cards(wrapper)).toHaveLength(0)
    expect(exists(wrapper, 'coverage')).toBe(false)
    expect(pushMock).not.toHaveBeenCalledWith({ name: 'login' })
    // 403 adalah galat ber-status: pesan yang dapat dibaca, bukan pesan
    // jaringan.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(text(wrapper, 'error-message')).toContain('tidak memiliki akses')
  })
})

/* ================================================================== */
/* Skenario 25 — dua permintaan ringkasan saling mendahului            */
/* ================================================================== */

describe('hanya jawaban permintaan terbaru yang berlaku', () => {
  it('skenario 25: jawaban permintaan LAMA yang tiba belakangan tidak menimpa yang baru', async () => {
    const lama = summaryFixture({
      metrics: { ...metrics(), anaerobic_pond_1_ph: metric(1, 1.11, 2, 5) },
    })
    const baru = summaryFixture({
      metrics: { ...metrics(), anaerobic_pond_1_ph: metric(2, 2.22, 3, 6) },
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

    expect(card(wrapper, 'anaerobic_pond_1_ph').text()).toContain('2,22')

    // Permintaan PERTAMA selesai PALING AKHIR — dan tetap kalah. Tidak ada
    // pembatalan permintaan: penyelesaiannya memakai penanda permintaan
    // terakhir.
    resolveLama?.(lama)
    await flushPromises()

    expect(card(wrapper, 'anaerobic_pond_1_ph').text()).toContain('2,22')
    expect(card(wrapper, 'anaerobic_pond_1_ph').text()).not.toContain('1,11')
    // State memuat tidak tersangkut.
    expect(exists(wrapper, 'summary-loading')).toBe(false)
    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(2)
  })

  it('galat permintaan lama tidak memunculkan pesan basi, dan tidak mematikan indikator muat', async () => {
    let rejectLama: ((error: unknown) => void) | null = null

    repoMocks.fetchSummary
      .mockImplementationOnce(
        () =>
          new Promise((_resolve, reject) => {
            rejectLama = reject as (error: unknown) => void
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
    expect(cards(wrapper)).toHaveLength(14)
  })

  it('mengosongkan pilihan periode saat ringkasan dimuat: tidak ada angka yang muncul kemudian', async () => {
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
/* Skenario 29 + 32 + 37 — nol aritmetika di klien                     */
/* ================================================================== */

describe('nol aritmetika di klien', () => {
  it('skenario 37: setiap angka yang dirender sama dengan nilai respons, termasuk yang tidak konsisten', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        coverage: {
          filled_slots: 118,
          expected_slots: 1440,
          // 118/1440 = 8,19 — dan server mengirim 88,8. Layar yang menghitung
          // sendiri mencetak 8,2% dan test ini gagal.
          coverage_percent: 88.8,
          effluent_plant_unit_count: 2,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 30,
          period_running: false,
        },
        metrics: {
          ...metrics(),
          // Server MENERBITKAN unit, dan nilainya 'C'. METRIC_META milik view
          // berisi '°C' untuk kolom ini, jadi test ini membuktikan nilai
          // SERVER yang menang dan tidak pernah ditulis ulang.
          anaerobic_pond_1_temp_c: { ...metric(38, 41.2, 45, 117), unit: 'C' },
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'coverage-percent')).toBe('88,8%')
    // avg DI LUAR rentang min..max, dicetak apa adanya.
    expect(card(wrapper, 'cooling_pond_temp_c').text()).toContain('12,5')
    expect(card(wrapper, 'cooling_pond_temp_c').text()).toContain('10,0')
    expect(card(wrapper, 'cooling_pond_temp_c').text()).toContain('11,0')
    // 2 × 30 × 24 = 1.440 — angkanya kebetulan cocok, tetapi yang dicetak
    // tetap expected_slots dari server.
    expect(text(wrapper, 'coverage-slots')).toContain('1.440')

    // SATUAN 'C' DIRENDER 'C', BUKAN '°C'.
    const temp = card(wrapper, 'anaerobic_pond_1_temp_c').text()

    expect(temp).toContain('· C')
    expect(temp).not.toContain('°C')

    // daily_total dari SERVER (99), bukan 3 + 4.
    expect(wrapper.get('[data-testid="daily-row-total"]').text()).toContain('99')
  })

  it("tanpa unit dari server, METRIC_META view memasok '°C' — cadangan, bukan penimpa", async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Payload fixture TIDAK membawa unit, jadi tabel view yang dipakai —
    // isinya disalin PERSIS dari $metrikMeta pada blade screen-156 supaya
    // ponsel dan web menuliskan satuan yang sama untuk angka yang sama.
    expect(card(wrapper, 'anaerobic_pond_1_temp_c').text()).toContain('°C')
    expect(card(wrapper, 'biogas_flow_rate_m3h').text()).toContain('m³/jam')
    // pH tanpa satuan — dan karena itu tidak ada pemisah satuan yang kosong.
    expect(card(wrapper, 'anaerobic_pond_1_ph').text()).toContain('pH Kolam Anaerobik 1')
  })

  it('label dari server MENANG atas tabel label view', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        metrics: {
          ...metrics(),
          anaerobic_pond_1_ph: { ...metric(6.1, 7.4, 8.9, 118), label: 'pH AN-1 dari Server' },
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(card(wrapper, 'anaerobic_pond_1_ph').text()).toContain('pH AN-1 dari Server')
    expect(card(wrapper, 'anaerobic_pond_1_ph').text()).not.toContain('pH Kolam Anaerobik 1')
  })

  it('skenario 32: tidak ada satu pun elemen berkelas penanda di luar batas', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        metrics: {
          ...metrics(),
          // Nilai EKSTREM jauh di atas dan di bawah yang lain.
          final_discharge_bod_mgl_lab: metric(1, 9999, 99999, 4, true),
          final_discharge_ph: metric(0.1, 0.2, 14, 108),
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Diasersi atas JUMLAH ELEMEN BERKELAS, bukan atas frasa: kalimat yang
    // menyatakan ketiadaannya sendiri memuat frasa "di luar batas".
    for (const selector of [
      '.metric-warning',
      '.metric-danger',
      '.md-threshold',
      '.is-out-of-range',
      '.out-of-range',
      '.metric-breach',
      '[data-testid="metric-warning"]',
      '[data-testid="out-of-range"]',
    ]) {
      expect(wrapper.findAll(selector), selector).toHaveLength(0)
    }

    // Dan sebabnya dirender UTUH — tanpa elipsis dan tanpa kontrol 'baca
    // selengkapnya'.
    const note = text(wrapper, 'no-flagging-note')

    expect(note).toContain('kepatuhan lingkungan')
    expect(note).toContain('di luar aplikasi ini')
    expect(note).not.toContain('…')
    expect(note).not.toContain('baca selengkapnya')
  })

  it('skenario 29: tidak ada pemanggilan repo selain keempat fungsi pembacaan', async () => {
    repoMocks.exportCsv.mockResolvedValue(new Blob(['a,b']))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    expect(Object.keys(repoMocks).every((key) => /^fetch|^export|^save/.test(key))).toBe(true)
    // Endpoint daftar mill tidak pernah ada jalurnya — mock-nya pun tidak
    // ada, jadi pemanggilan apa pun ke sana akan melempar.
    expect(repoMocks).not.toHaveProperty('fetchBusinessUnits')
  })
})

/* ================================================================== */
/* Skenario 31 — pernyataan kepatuhan lingkungan                       */
/* ================================================================== */

describe('pernyataan kepatuhan lingkungan', () => {
  it('skenario 31: pernyataannya berada DI DALAM blok Buangan Akhir, bukan di catatan kaki halaman', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const compliance = wrapper.findAll('[data-testid="environmental-compliance-note"]')

    // TEPAT SATU, dan ia berada di dalam blok Buangan Akhir: di ponsel
    // catatan kaki halaman berarti puluhan layar jauhnya dari angka yang
    // dirujuknya.
    expect(compliance).toHaveLength(1)
    expect(
      block(wrapper, 'metric-block-final-discharge')
        .find('[data-testid="environmental-compliance-note"]')
        .exists(),
    ).toBe(true)

    // Dan TIDAK di dalam kelima blok lainnya.
    for (const metricBlock of METRIC_BLOCKS.filter(
      (candidate) => candidate.testid !== 'metric-block-final-discharge',
    )) {
      expect(
        block(wrapper, metricBlock.testid)
          .find('[data-testid="environmental-compliance-note"]')
          .exists(),
        metricBlock.testid,
      ).toBe(false)
    }

    // Keempat angkanya berada di blok yang sama dengan pernyataannya.
    for (const column of [
      'final_discharge_ph',
      'final_discharge_bod_mgl_lab',
      'final_discharge_cod_mgl_lab',
      'final_discharge_tss_mgl_lab',
    ]) {
      expect(
        block(wrapper, 'metric-block-final-discharge').find(`[data-metric="${column}"]`).exists(),
        column,
      ).toBe(true)
    }

    expect(text(wrapper, 'environmental-compliance-note'))
      .toContain('tidak menyatakan kepatuhan lingkungan')
  })

  it('tidak ada label maupun kesimpulan patuh/tidak patuh di mana pun pada hasil render', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Pernyataan ketiadaannya sendiri memuat kata 'patuh', jadi yang disisir
    // adalah ELEMEN BERKELAS/BERTESTID, bukan frasa.
    for (const selector of [
      '[data-testid="compliance-verdict"]',
      '[data-testid="compliance-badge"]',
      '.compliance-pass',
      '.compliance-fail',
    ]) {
      expect(wrapper.findAll(selector), selector).toHaveLength(0)
    }
  })
})

/* ================================================================== */
/* Skenario 36 — halaman tidak pernah menggulir mendatar               */
/* ================================================================== */

describe('tata letak satu kolom', () => {
  /**
   * jsdom tidak menghitung tata letak, jadi lebar GULIR-nya diuji di spec
   * browser (tests/e2e/laporan-effluent-plant.spec.ts). Yang dapat — dan
   * HARUS — dibuktikan di sini adalah STRUKTURNYA: setiap isi yang lebih
   * lebar daripada 390px berada DI DALAM pembungkus yang menggulir sendiri,
   * dan tidak satu pun di luarnya.
   */
  it('skenario 36: kedua tabel rekap dan kedua grafik tren masing-masing di dalam pembungkusnya sendiri', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        by_unit: Array.from({ length: 3 }, (_unused, index) => ({
          effluent_plant_id: `EP-${index + 1}`,
          reading_rows: 10 * (index + 1),
          reading_count: 10 * (index + 1),
          metrics: unitMetrics(7.0, 10 * (index + 1)),
        })),
        daily: Array.from({ length: 30 }, (_unused, index) => ({
          date: isoDaysFromNow(-30 + index),
          reading_rows: index + 1,
          metrics_avg: {
            anaerobic_pond_1_ph: { avg: 7.0, reading_count: index + 1 },
          },
        })),
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // KEDUA tabel rekap berada di dalam .detail-table-wrap (overflow-x pada
    // src/assets/stacked-table.css).
    for (const testid of ['by-unit-table', 'daily-table']) {
      const table = wrapper.get(`[data-testid="${testid}"]`)

      expect(table.element.closest('.detail-table-wrap'), testid).not.toBeNull()
    }

    // KEDUA grafik tren berada di dalam .chart-scroll.
    for (const testid of ['daily-trend-raw-pome-feed', 'daily-trend-final-discharge-ph']) {
      const chart = block(wrapper, testid).get('.chart-bars')

      expect(chart.element.closest('.chart-scroll'), testid).not.toBeNull()
      expect(text(wrapper, `${testid}-scroll-hint`)).toContain('di dalam kartu ini')
    }

    // Dan ketiga tabel sebaran status pun — mereka hanya tiga kolom, tetapi
    // pembungkusnya sama, jadi tidak ada satu tabel pun di luar pembungkus.
    expect(wrapper.findAll('table')).toHaveLength(
      wrapper.findAll('.detail-table-wrap table').length,
    )
  })

  /**
   * STANDAR KERAPIAN TAMPILAN: tiap kelas yang dipakai markup WAJIB punya
   * definisi. Kelas tanpa definisi tidak menggagalkan satu asersi pun —
   * ia hanya membuat bagian layar kehilangan gayanya tanpa satu tanda — dan
   * cacat persis itu ada di LaporanDepricarpingView.vue (.metric-values, yang
   * tidak terdefinisi di satu berkas pun di src/) dan SENGAJA tidak disalin
   * ke sini.
   */
  it('setiap kelas yang dipakai markup punya definisi di blok <style> atau stylesheet bersama', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Dibaca dari DISK, bukan dari import.meta.url: di Vitest nilai itu
    // sebuah URL http:// dan fileURLToPath menolaknya. process.cwd() adalah
    // akar proyek mobile (root Vitest), dan itulah yang dipakai.
    const sources = [
      'src/views/LaporanEffluentPlantView.vue',
      'src/assets/stacked-table.css',
      'src/components/CollapsibleSection.vue',
      'src/components/StatusBadge.vue',
      'src/components/filters/FilterPanel.vue',
      'src/components/filters/FilterSelectField.vue',
      'src/components/filters/FilterChip.vue',
      'src/components/loading/LoadingState.vue',
      'src/components/loading/BusyLabel.vue',
    ]
      .map((relative) => readFileSync(resolve(process.cwd(), relative), 'utf-8'))
      .join('\n')

    expect(sources.length).toBeGreaterThan(10_000)

    const used = new Set<string>()

    for (const element of wrapper.findAll('[class]')) {
      for (const className of (element.attributes('class') ?? '').split(/\s+/)) {
        if (className !== '') {
          used.add(className)
        }
      }
    }

    expect(used.size).toBeGreaterThan(10)

    const undefinedClasses = [...used].filter((className) => !sources.includes(`.${className}`))

    expect(undefinedClasses, `kelas tanpa definisi: ${undefinedClasses.join(', ')}`).toEqual([])
    // Dan defek lama yang SENGAJA tidak disalin tetap tidak ada.
    expect(used.has('metric-values')).toBe(false)
  })
})

/* ================================================================== */
/* Skenario 35 — ekspor CSV saja                                       */
/* ================================================================== */

describe('ekspor', () => {
  it('skenario 35: mengekspor periode + line terpilih dan menyimpan berkas bernama slug keduanya', async () => {
    repoMocks.exportCsv.mockResolvedValue(new Blob(['a,b']))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    expect(repoMocks.exportCsv).toHaveBeenCalledWith(
      'per-1',
      expect.objectContaining({ productionLineId: 'pl-1' }),
    )
    expect(repoMocks.saveCsvFile).toHaveBeenCalledWith(
      expect.any(Blob),
      'laporan-effluent-plant_periode-uji-effluent-plant_line-satu.csv',
    )
  })

  it('cakupan ekspor TIDAK membawa medan mill, karena medannya tidak ada', async () => {
    repoMocks.exportCsv.mockResolvedValue(new Blob(['a,b']))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    expect(repoMocks.exportCsv.mock.calls[0][1]).toEqual({ productionLineId: 'pl-1' })
    expect(repoMocks.fetchSummary.mock.calls[0][1]).toEqual({ productionLineId: 'pl-1' })
  })

  it('HANYA CSV: tidak ada satu pun tawaran .xlsx di layar ini', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const labels = wrapper.findAll('button').map((button) => button.text())

    expect(labels.some((label) => label.includes('Ekspor CSV'))).toBe(true)
    expect(labels.some((label) => /xlsx|excel/i.test(label))).toBe(false)
    // Diasersi atas TEKS YANG TERLIHAT, bukan atas wrapper.html(): komentar
    // template ikut terbawa ke HTML, dan komentar yang menjelaskan MENGAPA
    // .xlsx tidak ditawarkan memuat kata itu sendiri.
    expect(wrapper.text()).not.toMatch(/xlsx/i)
    expect(wrapper.text()).not.toMatch(/excel/i)
    expect(wrapper.findAll('option').map((option) => option.text()).join(' '))
      .not.toMatch(/xlsx|excel/i)
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
      'laporan-effluent-plant_periode-uji-effluent-plant_line-satu.csv',
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

  it('ekspor tidak memanggil repo bila line belum berlaku', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Tombolnya memang tidak dirender — dan itulah separuh penjagaannya;
    // separuh lagi ada di dalam onExport().
    expect(exists(wrapper, 'export-button')).toBe(false)
    expect(repoMocks.exportCsv).not.toHaveBeenCalled()
  })

  it('401 saat ekspor: diarahkan ke Login, dan tidak ada berkas yang disimpan', async () => {
    repoMocks.exportCsv.mockRejectedValueOnce(UNAUTHENTICATED_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    expect(repoMocks.saveCsvFile).not.toHaveBeenCalled()
    expect(exists(wrapper, 'network-error')).toBe(false)
  })
})

/* ================================================================== */
/* Skenario 28 — layar hanya membaca                                   */
/* ================================================================== */

describe('layar hanya membaca', () => {
  it('skenario 28: satu-satunya kontrol adalah pemilih, pembuka bagian, Ekspor, dan Back', async () => {
    repoMocks.exportCsv.mockResolvedValue(new Blob(['a,b']))
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)
    routeQuery.production_line_id = 'pl-1'

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Tidak ada elemen form input, tidak ada tombol simpan/ubah/hapus.
    expect(wrapper.findAll('input')).toHaveLength(0)
    expect(wrapper.findAll('textarea')).toHaveLength(0)
    expect(wrapper.findAll('form')).toHaveLength(0)

    const labels = wrapper.findAll('button').map((button) => button.text().toLowerCase())

    for (const forbidden of ['simpan', 'ubah', 'hapus', 'tambah', 'edit']) {
      expect(labels.some((label) => label.includes(forbidden)), forbidden).toBe(false)
    }

    // Kedua pemilih <select>, dan tidak lebih.
    expect(wrapper.findAll('select')).toHaveLength(2)

    const footerButtons = wrapper.findAll('.action-footer button').map((button) => button.text())

    expect(footerButtons).toHaveLength(2)
    expect(footerButtons.join(' ')).toContain('Ekspor')
    expect(footerButtons.join(' ')).toContain('Back')
  })

  it('membuka dan menutup rekap harian tidak memicu permintaan dan tidak mengubah satu angka pun', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const callsBefore = repoMocks.fetchSummary.mock.calls.length
    const totalBefore = wrapper.get('[data-testid="daily-row-total"]').text()

    const toggle = wrapper
      .get('[data-testid="daily-recap"]')
      .get('[data-testid="collapsible-section-toggle"]')

    await toggle.trigger('click')
    await flushPromises()
    await toggle.trigger('click')
    await flushPromises()

    // Datanya sudah ada di memori: tidak ada permintaan, dan baris total
    // periode TIDAK berubah — ia dihitung ulang SERVER atas seluruh slot,
    // bukan dirata-ratakan dari baris harian.
    expect(repoMocks.fetchSummary.mock.calls).toHaveLength(callsBefore)
    expect(wrapper.get('[data-testid="daily-row-total"]').text()).toBe(totalBefore)
  })

  it('rekap harian memakai v-show: barisnya ada di DOM walau bagian itu tertutup', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // CollapsibleSection memakai v-show, bukan v-if — jadi baris harian tetap
    // dapat diasersi apa pun keadaan terbuka/tertutupnya.
    expect(wrapper.findAll('[data-testid="daily-table"] tbody tr')).toHaveLength(2)
    expect(wrapper.get(`[data-testid="daily-row-${DAY_A}"]`).text()).toContain('7,00')
    expect(wrapper.get(`[data-testid="daily-row-${DAY_B}"]`).text()).toContain('8,00')
    // Sel kosong dirender '—', BUKAN 0: nol adalah pembacaan, kosong bukan.
    expect(wrapper.get(`[data-testid="daily-row-${DAY_A}"]`).text()).toContain('—')
  })

  it('kelengkapan record: kedua penghitung terender, dengan keterangan draft ikut terhitung', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'days-with-records')).toBe('2')
    expect(text(wrapper, 'total-reading-rows')).toBe('118')
    expect(text(wrapper, 'completeness')).toContain('draft')
    expect(text(wrapper, 'completeness')).toContain('IKUT terhitung')
  })

  it('Back kembali ke Dashboard & Reporting', async () => {
    const wrapper = await mountView()
    await wrapper.get('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'dashboard-reporting' })
  })
})

/* ================================================================== */
/* Tren harian                                                         */
/* ================================================================== */

describe('tren harian', () => {
  it('TEPAT dua tren, dan tidak satu pun atas kolom status maupun nilai laboratorium', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'daily-trend-raw-pome-feed')).toBe(true)
    expect(exists(wrapper, 'daily-trend-final-discharge-ph')).toBe(true)

    for (const testid of [
      'daily-trend-biogas-flare',
      'daily-trend-final-discharge-bod',
      'daily-trend-final-discharge-cod',
      'daily-trend-final-discharge-tss',
      'daily-trend-chemical',
    ]) {
      expect(exists(wrapper, testid), testid).toBe(false)
    }

    expect(text(wrapper, 'daily-trend-raw-pome-feed')).toContain('Tren Harian Laju POME Masuk')
    expect(text(wrapper, 'daily-trend-final-discharge-ph'))
      .toContain('Tren Harian pH Buangan Akhir')
  })

  it('titik tanpa pembacaan menghasilkan batang KOSONG dan nilai tidak tersedia, bukan batang nol', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const emptyColumn = wrapper.get(`[data-testid="daily-trend-raw-pome-feed-col-${DAY_B}"]`)

    expect(emptyColumn.text()).toContain('tidak tersedia')
    expect(emptyColumn.get('.chart-fill').attributes('style')).toContain('height: 0%')

    const filledColumn = wrapper.get(`[data-testid="daily-trend-raw-pome-feed-col-${DAY_A}"]`)

    expect(filledColumn.text()).toContain('22,5')
  })

  it('terendah/tertinggi tren DILABELI "dari rata-rata harian", bukan terendah/tertinggi periode', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const trend = text(wrapper, 'daily-trend-final-discharge-ph')

    expect(trend).toContain('dari rata-rata harian')
    // Terendah series 6,80 (dari rata-rata harian), sementara min PERIODE
    // atas pembacaan mentah 6,20 — dua angka yang berbeda, dan layar tidak
    // boleh menukarnya.
    expect(trend).toContain('6,80')
    expect(trend).toContain('7,10')
    expect(trend).toContain('Rata-rata periode')
    expect(trend).toContain('6,90')
    expect(trend).toContain('dihitung ulang server')
  })
})

/* ================================================================== */
/* Navigasi & menu                                                     */
/* ================================================================== */

describe('navigasi', () => {
  it('breadcrumb Home dan menu hamburger berperilaku seperti layar mobile lain', async () => {
    const wrapper = await mountView()

    await wrapper.get('[data-testid="breadcrumb-home"]').trigger('click')
    expect(pushMock).toHaveBeenCalledWith({ name: 'home' })

    expect(exists(wrapper, 'nav-menu')).toBe(false)
    await wrapper.get('[data-testid="hamburger-button"]').trigger('click')
    expect(exists(wrapper, 'nav-menu')).toBe(true)

    await wrapper.get('[data-testid="nav-menu-logout"]').trigger('click')
    await flushPromises()

    expect(logoutMock).toHaveBeenCalledOnce()
    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
  })
})
