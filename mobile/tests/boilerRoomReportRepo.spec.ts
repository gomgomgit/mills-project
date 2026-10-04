/**
 * boilerRoomReportRepo.spec.ts — screen-137--laporan-boiler-room-mobile /
 * usecase-137--laporan-boiler-room-mobile "Lihat Laporan Periode Boiler
 * Room (Mobile)".
 *
 * Satu test per unit_test_cases pada tech spec screen-137 — seluruh 23.
 * Kembaran dari tests/sterilizerReportRepo.spec.ts (screen-135) dan
 * tests/cagesTrackReportRepo.spec.ts (screen-136), untuk
 * src/services/boilerRoomReportRepo.ts.
 *
 * YANG DI-MOCK HANYA apiClient. Repo-nya sendiri berjalan sungguhan —
 * itulah satu-satunya cara asersi seperti "params yang dikirim tidak memuat
 * business_unit_id" dan "tidak ada perhitungan kedua di klien" membuktikan
 * sesuatu: yang menyusun query adalah scopeParams() di dalam repo, dan yang
 * membaca pembungkus respons adalah unwrap() di dalam repo. Men-stub repo
 * akan memindahkan seluruh asersi ke mock buatan test sendiri.
 *
 * ────────────────────────────────────────────────────────────────────────
 * ANGKA FIXTURE SENGAJA TIDAK KONSISTEN SECARA ARITMETIKA — INI POKOKNYA
 * ────────────────────────────────────────────────────────────────────────
 * Dengan payload yang konsisten, "repo tidak menghitung ulang" TIDAK
 * MEMBUKTIKAN APA PUN: repo yang diam-diam menurunkan angkanya sendiri akan
 * menghasilkan nilai yang sama dan tetap lolos. Karena itu setiap besaran
 * turunan di bawah dibuat BERTENTANGAN dengan sumbernya, sehingga repo yang
 * berhitung PASTI gagal:
 *
 *   - metrics.steam_pressure_bar.reading_count (300) ≠ jumlah reading_count
 *     by_unit (301) dan ≠ total.reading_rows (412);
 *   - metrics.water_ph.reading_count (3) jauh berbeda dari metrik lain —
 *     tidak ada satu penyebut bersama yang dapat menghasilkan keduanya;
 *   - metrics.steam_pressure_bar.avg (21,7) ≠ (min + max) / 2 (22,55) dan ≠
 *     rata-rata kolom daily.steam_pressure_avg;
 *   - metrics.steam_pressure_bar.max (26,9) LEBIH KECIL daripada
 *     daily[0].steam_pressure_avg (42,1) — mustahil bila ekstremnya
 *     diturunkan dari kolom harian, dan memang tidak: ekstrem berasal dari
 *     PEMBACAAN MENTAH per slot waktu (BoilerRoomReportService::metricsOf);
 *   - coverage.coverage_percent (6,3) ≠ 100 × 9 / 144 (6,25), dan
 *     coverage.expected_slots (144) ≠ boiler_unit_count × days_in_period ×
 *     slots_per_unit_per_day (2 × 6 × 24 = 288);
 *   - coverage.filled_slots (9) ≠ jumlah daily.filled_slots (12);
 *   - maintenance.blowdown.executed (5) ≠ jumlah daily.blowdown_executed (3)
 *     dan ≠ jumlah by_unit.blowdown_executed (4);
 *   - maintenance.blowdown.avg_per_day (0,83) ≠ 5 / 6 (0,8333…);
 *   - total.days_with_records (11) ≠ panjang daily (3).
 *
 * JANGAN "merapikan" angka-angka ini. Merapikannya akan melucuti separuh
 * berkas ini menjadi test yang selalu hijau.
 *
 * BENTUK GALAT — DATAR, TANPA `response`. Interceptor pada
 * src/services/apiClient.ts (normalizeError) selalu menolak dengan objek
 * DATAR { message, errors?, status? } dan sudah MEMBUANG `response`. Seluruh
 * mock penolakan di bawah memakai bentuk nyata itu; memalsukan
 * `{ response: { status } }` akan menguji bentuk yang tidak pernah terjadi
 * di produksi. Penerjemahan 401/403 menjadi perilaku layar adalah tugas
 * view (diuji di LaporanBoilerRoomView.spec.ts) — yang wajib dibuktikan di
 * sini hanyalah prasyaratnya: galat naik APA ADANYA, objeknya identik.
 *
 * PEMBUNGKUS `data` BERBEDA ANTAR ENDPOINT. /periods dan
 * /business-units/options dibaca lewat `response.data?.data`, sedangkan
 * /summary dikirim controller TANPA pembungkus. fetchSummary menerima KEDUA
 * bentuk (unwrap), dan keduanya diuji.
 */
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'

/* ------------------------------------------------------------------ */
/* Mock modul — satu-satunya lapisan yang distub                       */
/* ------------------------------------------------------------------ */

const { apiGetMock } = vi.hoisted(() => ({ apiGetMock: vi.fn() }))

vi.mock('@/services/apiClient', () => ({
  default: { get: apiGetMock },
}))

import boilerRoomReportRepo, {
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  exportCsv,
  saveCsvFile,
} from '@/services/boilerRoomReportRepo'

/* ------------------------------------------------------------------ */
/* Fixture — BENTUK RESPONS NYATA                                      */
/* ------------------------------------------------------------------ */

const PERIOD_BOILER_ROOM = {
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

const BUSINESS_UNITS = [
  { id: 'BU-A', name: 'PKS Sungai Bahar' },
  { id: 'BU-B', name: 'PKS Muara Bulian' },
]

/**
 * Kesembilan metrik numerik, masing-masing dengan PENYEBUTNYA SENDIRI.
 * water_ph sengaja hanya 3 pembacaan sementara steam_pressure_bar 300 —
 * tidak ada satu penyebut bersama yang dapat menghasilkan keduanya, dan
 * itulah yang membuat asersi "tiap metrik punya penyebutnya sendiri"
 * bermakna.
 */
function makeMetrics(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    // avg 21,7 BUKAN (18,2 + 26,9) / 2 = 22,55; max 26,9 LEBIH KECIL
    // daripada daily[0].steam_pressure_avg 42,1 (ekstrem mentah vs
    // rata-rata harian — sengaja tak dapat direkonsiliasi).
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

const EMPTY_METRIC = { min: null, avg: null, max: null, reading_count: 0 }

/**
 * Tiga tanggal ber-record. Baris kedua kosong pada SELURUH kolom rata-rata
 * — tanggal itu tetap tanggal ber-record dan tidak boleh dibuang. Jumlah
 * filled_slots (4 + 3 + 5 = 12) sengaja BERBEDA dari coverage.filled_slots
 * (9).
 */
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

/** Unit kedua tanpa satu pun pembacaan — temuan, bukan baris yang dibuang. */
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

const MAINTENANCE = {
  // executed 5 ≠ jumlah daily (3) ≠ jumlah by_unit (4);
  // avg_per_day 0,83 ≠ 5 / 6 (0,8333…).
  blowdown: { executed: 5, not_executed: 2, not_recorded: 41, avg_per_day: 0.83, all_unrecorded: false },
  // Nol yang berarti "tidak pernah DICATAT", bukan "tidak pernah dilakukan".
  sootblowing: { executed: 0, not_executed: 0, not_recorded: 48, avg_per_day: 0, all_unrecorded: true },
}

const COVERAGE = {
  filled_slots: 9,
  // 144 ≠ 2 × 6 × 24 (288), dan 6,3 ≠ 100 × 9 / 144 (6,25).
  expected_slots: 144,
  coverage_percent: 6.3,
  boiler_unit_count: 2,
  slots_per_unit_per_day: 24,
  days_in_period: 6,
}

/** days_with_records 11 ≠ panjang daily (3); reading_rows 412 milik server. */
const TOTAL = { days_with_records: 11, reading_rows: 412 }

/**
 * Respons /summary yang dikirim BoilerRoomReportController::summary() —
 * TANPA pembungkus `data` (`response()->json($this->service->summary(...))`).
 */
function makeSummaryBody(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    period: {
      id: 'per-1',
      name: 'Periode Maret 2026',
      start_date: '2026-03-01',
      end_date: '2026-03-06',
      status: 'open',
      business_unit_name: 'PKS Sungai Bahar',
    },
    business_unit: { id: 'BU-A', name: 'PKS Sungai Bahar' },
    has_data: true,
    coverage: { ...COVERAGE },
    metrics: makeMetrics(),
    maintenance: { blowdown: { ...MAINTENANCE.blowdown }, sootblowing: { ...MAINTENANCE.sootblowing } },
    daily: DAILY_ROWS,
    by_unit: BY_UNIT_ROWS,
    total: { ...TOTAL },
    ...overrides,
  }
}

/** Seluruh panggilan apiClient.get untuk satu path. */
function callsTo(path: string): unknown[][] {
  return apiGetMock.mock.calls.filter((call) => String(call[0]).includes(path))
}

/** Params yang benar-benar dikirim pada panggilan pertama ke satu path. */
function paramsOf(path: string): Record<string, unknown> {
  const [, config] = (callsTo(path)[0] ?? []) as [string, { params?: Record<string, unknown> }]

  return config?.params ?? {}
}

const { createObjectURLMock, revokeObjectURLMock } = vi.hoisted(() => ({
  createObjectURLMock: vi.fn(),
  revokeObjectURLMock: vi.fn(),
}))

beforeEach(() => {
  vi.clearAllMocks()
  // Aturan repo: setiap test yang dapat menyentuh localStorage wajib
  // membersihkannya — jsdom membagi satu objek localStorage per berkas.
  window.localStorage.clear()

  apiGetMock.mockImplementation((url: string) => {
    if (String(url).includes('/business-units/options')) {
      return Promise.resolve({ data: { data: BUSINESS_UNITS } })
    }

    if (String(url).includes('/periods')) {
      return Promise.resolve({ data: { data: [PERIOD_BOILER_ROOM] } })
    }

    if (String(url).includes('/summary')) {
      return Promise.resolve({ data: makeSummaryBody() })
    }

    if (String(url).includes('/export')) {
      return Promise.resolve({ data: new Blob(['csv'], { type: 'text/csv' }) })
    }

    return Promise.reject(new Error(`Endpoint tak terduga: ${url}`))
  })

  createObjectURLMock.mockReturnValue('blob:mock-url')
  ;(URL as unknown as { createObjectURL: (blob: Blob) => string }).createObjectURL = createObjectURLMock
  ;(URL as unknown as { revokeObjectURL: (url: string) => void }).revokeObjectURL = revokeObjectURLMock
})

afterEach(() => {
  vi.restoreAllMocks()
})

/* ================================================================== */
/* Lapisan repo screen-137 — 23 kasus (unit_test_cases tech spec)      */
/* ================================================================== */

describe('boilerRoomReportRepo — lapisan repo (screen-137)', () => {
  // 1
  it('fetchPeriods memanggil tepat GET /api/boiler-room-reports/periods dan tidak menyaring jenis stasiun di klien', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [PERIOD_BOILER_ROOM, PERIOD_LINTAS_STASIUN] } })

    const periods = await fetchPeriods()

    expect(callsTo('/api/boiler-room-reports/periods')).toHaveLength(1)
    expect(apiGetMock).toHaveBeenCalledTimes(1)
    expect(apiGetMock).toHaveBeenCalledWith('/api/boiler-room-reports/periods', { params: {} })

    // Seluruh entri diteruskan apa adanya, dalam urutan server. Penyaringan
    // cakupan Boiler Room sepenuhnya milik server — termasuk periode yang
    // mencakup banyak jenis stasiun sekaligus, yang TIDAK boleh dibuang di
    // sini.
    expect(periods).toEqual([PERIOD_BOILER_ROOM, PERIOD_LINTAS_STASIUN])
    expect(periods).toHaveLength(2)
    expect(periods[0].station_type).toBe('boiler-room')
    expect(periods[1].station_type).toBe('boiler-room')
  })

  // 2 — GAGAL TERTUTUP: peran terikat mill (termasuk Operator) tidak pernah
  //     mengirim business_unit_id.
  it('scopeParams tidak mengirim business_unit_id untuk peran yang terikat mill, termasuk Operator', async () => {
    await fetchPeriods({ isAdmin: false, businessUnitId: 'BU-A' })

    const params = paramsOf('/api/boiler-room-reports/periods')

    // Bukan null, bukan string kosong — kuncinya ABSEN.
    expect(params).toEqual({})
    expect(params).not.toHaveProperty('business_unit_id')
    expect(Object.keys(params)).toEqual([])
    expect(JSON.stringify(params)).not.toContain('BU-A')

    // Berlaku juga pada /summary, yang menggabung scopeParams dengan
    // period_id: yang tersisa HANYA period_id. Operator ada di cabang
    // TERIKAT MILL ini, bukan di cabang Admin.
    apiGetMock.mockClear()
    await fetchSummary('per-1', { isAdmin: false, businessUnitId: 'BU-B' })
    expect(apiGetMock).toHaveBeenCalledWith('/api/boiler-room-reports/summary', {
      params: { period_id: 'per-1' },
    })

    // Dan ketika pemanggil tidak menyatakan peran sama sekali (scope
    // undefined, atau isAdmin tak disebut) hasilnya sama — bawaannya
    // tertutup, bukan terbuka.
    apiGetMock.mockClear()
    await fetchPeriods()
    await fetchPeriods({ businessUnitId: 'BU-A' })
    expect(paramsOf('/api/boiler-room-reports/periods')).toEqual({})
    expect(callsTo('/api/boiler-room-reports/periods')).toHaveLength(2)
    expect(JSON.stringify(apiGetMock.mock.calls)).not.toContain('business_unit_id')
  })

  // 3
  it('scopeParams mengirim business_unit_id hanya ketika Admin sudah memilih mill', async () => {
    await fetchPeriods({ isAdmin: true, businessUnitId: 'BU-B' })

    expect(apiGetMock).toHaveBeenCalledWith('/api/boiler-room-reports/periods', {
      params: { business_unit_id: 'BU-B' },
    })

    apiGetMock.mockClear()
    await fetchSummary('per-1', { isAdmin: true, businessUnitId: 'BU-B' })

    expect(apiGetMock).toHaveBeenCalledWith('/api/boiler-room-reports/summary', {
      params: { period_id: 'per-1', business_unit_id: 'BU-B' },
    })
  })

  // 4
  it('scopeParams tidak mengirim business_unit_id ketika Admin belum memilih mill', async () => {
    await fetchPeriods({ isAdmin: true, businessUnitId: null })

    const params = paramsOf('/api/boiler-room-reports/periods')

    // Repo tidak mengarang nilai mill dan tidak melempar galatnya sendiri:
    // server yang menjawab 422 VALIDATION_ERROR, satu sumber kebenaran.
    expect(params).toEqual({})
    expect(params).not.toHaveProperty('business_unit_id')

    // Admin tanpa businessUnitId sama sekali (undefined), dan Admin dengan
    // string kosong, diperlakukan sama.
    apiGetMock.mockClear()
    await fetchPeriods({ isAdmin: true })
    await fetchPeriods({ isAdmin: true, businessUnitId: '' })
    expect(callsTo('/api/boiler-room-reports/periods')).toHaveLength(2)
    expect(
      apiGetMock.mock.calls.every(([, config]) => {
        return Object.keys((config as { params?: Record<string, unknown> })?.params ?? {}).length === 0
      }),
    ).toBe(true)
  })

  // 5
  it('fetchSummary memanggil GET /api/boiler-room-reports/summary dengan period_id', async () => {
    await fetchSummary('per-1')

    expect(callsTo('/api/boiler-room-reports/summary')).toHaveLength(1)
    expect(apiGetMock).toHaveBeenCalledTimes(1)
    expect(apiGetMock).toHaveBeenCalledWith('/api/boiler-room-reports/summary', {
      params: { period_id: 'per-1' },
    })
    // Tidak ada parameter lain yang ditambahkan repo.
    expect(Object.keys(paramsOf('/api/boiler-room-reports/summary'))).toEqual(['period_id'])
  })

  // 6 — unwrap menerima kedua bentuk pembungkus.
  it('unwrap menerima respons summary TANPA pembungkus data maupun DENGAN pembungkus', async () => {
    // (a) tanpa pembungkus — bentuk yang benar-benar dikirim controller.
    apiGetMock.mockResolvedValueOnce({ data: makeSummaryBody() })
    const unwrapped = await fetchSummary('per-1')

    // (b) dengan pembungkus — bila kelak pembungkusnya diseragamkan server,
    //     sebagaimana /periods dan /business-units/options sudah memakainya.
    apiGetMock.mockResolvedValueOnce({ data: { data: makeSummaryBody() } })
    const wrapped = await fetchSummary('per-1')

    // Identik medan per medan.
    expect(wrapped).toEqual(unwrapped)
    expect(wrapped.metrics.steam_pressure_bar.avg).toBe(21.7)
    expect(wrapped.coverage.coverage_percent).toBe(6.3)
    expect(wrapped.daily).toHaveLength(3)
    expect(wrapped.by_unit).toHaveLength(2)
    expect(wrapped.total.reading_rows).toBe(412)

    // /periods dan /business-units/options tetap dibaca LEWAT pembungkus:
    // bentuk tanpa pembungkus memang bukan bentuk yang dikirim controller.
    apiGetMock.mockResolvedValueOnce({ data: [PERIOD_BOILER_ROOM] })
    await expect(fetchPeriods()).resolves.toEqual([])
    apiGetMock.mockResolvedValueOnce({ data: BUSINESS_UNITS })
    await expect(fetchBusinessUnits()).resolves.toEqual([])
  })

  // 7
  it('fetchSummary meneruskan seluruh blok apa adanya tanpa perhitungan kedua', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    // Kesembilan blok hadir — dan blok yang tidak disentuh sama sekali
    // adalah OBJEK/ARRAY YANG SAMA dengan respons.
    expect(summary.period).toBe(body.period)
    expect(summary.business_unit).toBe(body.business_unit)
    expect(summary.has_data).toBe(true)
    expect(summary.coverage).toBe(body.coverage)
    expect(summary.metrics).toBe(body.metrics)
    expect(summary.maintenance).toBe(body.maintenance)
    expect(summary.daily).toBe(body.daily)
    expect(summary.by_unit).toBe(body.by_unit)
    expect(summary.total).toBe(body.total)

    // Kesembilan metrik hadir dengan nilai identik.
    expect(Object.keys(summary.metrics).sort()).toEqual([
      'boiler_water_level_percent',
      'dust_collector_differential_pressure_mmh2o',
      'exhaust_gas_temp_c',
      'feed_water_tank_level_percent',
      'feed_water_temp_c',
      'steam_pressure_bar',
      'steam_temp_c',
      'water_ph',
      'water_tds_ppm',
    ])
    expect(summary.metrics.steam_pressure_bar).toEqual({
      min: 18.2,
      avg: 21.7,
      max: 26.9,
      reading_count: 300,
    })

    // Tidak ada medan turunan yang ditambahkan repo.
    expect(Object.keys(summary).sort()).toEqual([
      'business_unit',
      'by_unit',
      'coverage',
      'daily',
      'has_data',
      'maintenance',
      'metrics',
      'period',
      'production_line',
      'total',
    ])
  })

  // 8 — NULL BUKAN NOL.
  it('fetchSummary mempertahankan metrics[metrik].min/avg/max sebagai null dengan reading_count 0', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({ metrics: makeMetrics({ water_ph: { ...EMPTY_METRIC } }) }),
    })

    const summary = await fetchSummary('per-1')

    // null = "tidak pernah diukur"; 0 = "nilainya nol" — dua fakta berbeda,
    // dan yang satu menyesatkan.
    expect(summary.metrics.water_ph.min).toBeNull()
    expect(summary.metrics.water_ph.avg).toBeNull()
    expect(summary.metrics.water_ph.max).toBeNull()
    expect(summary.metrics.water_ph.min).not.toBe(0)
    expect(summary.metrics.water_ph.avg).not.toBe(0)
    expect(summary.metrics.water_ph.max).not.toBe(0)
    expect(summary.metrics.water_ph.reading_count).toBe(0)

    // Metrik lain sama sekali tidak terpengaruh.
    expect(summary.metrics.steam_pressure_bar).toEqual({
      min: 18.2,
      avg: 21.7,
      max: 26.9,
      reading_count: 300,
    })
  })

  // 9
  it('fetchSummary meneruskan reading_count tiap metrik sendiri-sendiri, tanpa penyebut bersama', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    expect(summary.metrics.steam_pressure_bar.reading_count).toBe(300)
    expect(summary.metrics.water_ph.reading_count).toBe(3)

    // Tidak diturunkan dari total.reading_rows dan tidak dinormalkan:
    // sebuah baris pengukuran dapat mengisi tekanan dan mengosongkan pH.
    expect(summary.metrics.steam_pressure_bar.reading_count).not.toBe(summary.total.reading_rows)
    expect(summary.metrics.water_ph.reading_count).not.toBe(summary.total.reading_rows)
    expect(summary.metrics.water_ph.reading_count).not.toBe(
      summary.metrics.steam_pressure_bar.reading_count,
    )

    // Dan bukan pula jumlah reading_count per unit (301).
    const unitReadings = BY_UNIT_ROWS.reduce((sum, row) => sum + row.reading_count, 0)
    expect(summary.metrics.steam_pressure_bar.reading_count).not.toBe(unitReadings)

    // Kesembilan penyebut diteruskan apa adanya, sembilan angka berbeda.
    expect(
      Object.values(summary.metrics).map((metric) => metric.reading_count),
    ).toEqual([300, 287, 265, 3, 240, 198, 176, 154, 121])
  })

  // 10 — PERAWATAN PUNYA TIGA KEADAAN.
  it('fetchSummary mempertahankan maintenance.not_recorded dan all_unrecorded apa adanya', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({
        maintenance: {
          blowdown: { executed: 0, not_executed: 0, not_recorded: 48, avg_per_day: 0, all_unrecorded: true },
          sootblowing: { ...MAINTENANCE.sootblowing },
        },
      }),
    })

    const summary = await fetchSummary('per-1')

    expect(summary.maintenance.blowdown).toEqual({
      executed: 0,
      not_executed: 0,
      not_recorded: 48,
      avg_per_day: 0,
      all_unrecorded: true,
    })

    // not_recorded TIDAK pernah ditambahkan ke not_executed — kolom kosong
    // bukan berarti perawatan tidak dijalankan.
    expect(summary.maintenance.blowdown.not_executed).toBe(0)
    expect(summary.maintenance.blowdown.not_executed).not.toBe(48)
    expect(summary.maintenance.blowdown.not_recorded).toBe(48)
    // all_unrecorded datang dari server, tidak diturunkan di sini.
    expect(summary.maintenance.blowdown.all_unrecorded).toBe(true)

    // Dan pada fixture penuh, ketiga keadaan tetap tiga angka terpisah yang
    // tidak dilebur satu sama lain.
    apiGetMock.mockResolvedValueOnce({ data: makeSummaryBody() })
    const full = await fetchSummary('per-1')
    expect(full.maintenance.blowdown).toEqual({
      executed: 5,
      not_executed: 2,
      not_recorded: 41,
      avg_per_day: 0.83,
      all_unrecorded: false,
    })
    // avg_per_day bukan hasil hitungan ulang 5 / 6.
    expect(full.maintenance.blowdown.avg_per_day).not.toBe(5 / 6)
    expect(full.maintenance.sootblowing.all_unrecorded).toBe(true)
    expect(full.maintenance.sootblowing.executed).toBe(0)
  })

  // 11
  it('fetchSummary mempertahankan null pada daily[].*_avg untuk tanggal yang metriknya kosong', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    const first = summary.daily[0]
    expect(first.date).toBe('2026-03-01')
    expect(first.filled_slots).toBe(4)
    expect(first.steam_pressure_avg).toBe(42.1)
    expect(first.water_ph_avg).toBeNull()
    expect(first.exhaust_gas_temp_avg).toBeNull()
    expect(first.water_ph_avg).not.toBe(0)
    expect(first.exhaust_gas_temp_avg).not.toBe(0)
    expect(first.blowdown_executed).toBe(1)

    // Barisnya TIDAK dibuang dan TIDAK ditambal nol — hari itu tetap hari
    // yang ber-record walau satu metriknya kosong.
    expect(summary.daily).toHaveLength(3)
    expect(summary.daily).toBe(body.daily)
  })

  // 12
  it('fetchSummary meneruskan by_unit termasuk unit boiler dengan reading_count 0', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    expect(summary.by_unit).toBe(body.by_unit)
    expect(summary.by_unit).toHaveLength(2)
    // Urutan server dipertahankan.
    expect(summary.by_unit.map((row) => row.boiler_room_id)).toEqual(['BLR-1', 'BLR-2'])

    const blank = summary.by_unit[1]
    expect(blank.reading_count).toBe(0)
    expect(blank.steam_pressure_avg).toBeNull()
    expect(blank.water_ph_avg).toBeNull()
    expect(blank.steam_pressure_avg).not.toBe(0)
    expect(blank.water_ph_avg).not.toBe(0)

    // Unit tanpa pembacaan TIDAK disaring keluar, dan tidak ada baris
    // "Total" yang ditambahkan repo.
    expect(summary.by_unit).toHaveLength(2)
  })

  // 13
  it('fetchSummary meneruskan coverage apa adanya tanpa menghitung ulang coverage_percent', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    expect(summary.coverage).toBe(body.coverage)
    expect(summary.coverage).toEqual({
      filled_slots: 9,
      expected_slots: 144,
      coverage_percent: 6.3,
      boiler_unit_count: 2,
      slots_per_unit_per_day: 24,
      days_in_period: 6,
    })

    // Pembulatan SERVER adalah satu-satunya sumber: 100 × 9 / 144 = 6,25.
    expect(summary.coverage.coverage_percent).toBe(6.3)
    expect(summary.coverage.coverage_percent).not.toBe((100 * 9) / 144)
    expect(summary.coverage.coverage_percent).not.toBe(Math.round((100 * 9) / 144))

    // expected_slots pun bukan hasil perkalian ulang unit × hari × slot.
    expect(summary.coverage.expected_slots).not.toBe(2 * 6 * 24)
    // Dan filled_slots bukan jumlah kolom daily (4 + 3 + 5 = 12).
    const dailyFilled = DAILY_ROWS.reduce((sum, row) => sum + row.filled_slots, 0)
    expect(summary.coverage.filled_slots).toBe(9)
    expect(summary.coverage.filled_slots).not.toBe(dailyFilled)
  })

  // 14
  it('fetchSummary meneruskan has_data apa adanya dan tidak menurunkannya sendiri', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({ has_data: false, total: { days_with_records: 0, reading_rows: 0 } }),
    })

    const summary = await fetchSummary('per-1')

    // Penanda milik SERVER — bukan turunan dari panjang daily (yang di sini
    // tetap 3) maupun dari total.
    expect(summary.has_data).toBe(false)
    expect(summary.daily).toHaveLength(3)
    expect(summary.total.reading_rows).toBe(0)

    // Dan sebaliknya: has_data true tetap true walau blok lain kosong.
    apiGetMock.mockResolvedValueOnce({ data: makeSummaryBody({ has_data: true, daily: [] }) })
    const other = await fetchSummary('per-1')
    expect(other.has_data).toBe(true)
    expect(other.daily).toEqual([])
  })

  // 15
  it('fetchSummary tidak mengurutkan ulang maupun menyaring daily, termasuk baris bernilai nol', async () => {
    const fiveDays = [
      { ...DAILY_ROWS[2], date: '2026-03-06' },
      { ...DAILY_ROWS[0], date: '2026-03-01' },
      {
        // Baris bernilai nol/null pada SELURUH kolom rata-rata.
        date: '2026-03-04',
        filled_slots: 0,
        steam_pressure_avg: null,
        steam_temp_avg: null,
        water_tds_avg: null,
        water_ph_avg: null,
        exhaust_gas_temp_avg: null,
        blowdown_executed: 0,
        sootblowing_executed: 0,
      },
      { ...DAILY_ROWS[1], date: '2026-03-02' },
      { ...DAILY_ROWS[2], date: '2026-03-03' },
    ]
    const body = makeSummaryBody({ daily: fiveDays })
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    // Panjang, urutan, dan isi sama persis — termasuk urutan tanggal yang
    // sengaja tidak menaik.
    expect(summary.daily).toBe(body.daily)
    expect(summary.daily).toHaveLength(5)
    expect(summary.daily.map((row) => row.date)).toEqual([
      '2026-03-06',
      '2026-03-01',
      '2026-03-04',
      '2026-03-02',
      '2026-03-03',
    ])

    // Baris bernilai nol TIDAK dibuang — justru harus terbaca sebagai hari
    // yang tercatat.
    const zeroRow = summary.daily[2]
    expect(zeroRow.date).toBe('2026-03-04')
    expect(zeroRow.filled_slots).toBe(0)
    expect(zeroRow.steam_pressure_avg).toBeNull()
    expect(zeroRow.steam_pressure_avg).not.toBe(0)

    // Tidak ada tanggal karangan yang disisipkan repo.
    expect(summary.daily.map((row) => row.date)).not.toContain('2026-03-05')
  })

  // 16
  it('fetchSummary meneruskan payload periode kosong apa adanya', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: {
        period: {
          id: 'per-9',
          name: 'Periode Kosong',
          start_date: '2026-04-01',
          end_date: '2026-04-30',
          status: 'open',
          business_unit_name: 'PKS Sungai Bahar',
        },
        business_unit: { id: 'BU-A', name: 'PKS Sungai Bahar' },
        has_data: false,
        coverage: {
          filled_slots: 0,
          expected_slots: 720,
          coverage_percent: 0,
          boiler_unit_count: 1,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
        },
        metrics: {
          steam_pressure_bar: { ...EMPTY_METRIC },
          steam_temp_c: { ...EMPTY_METRIC },
          water_tds_ppm: { ...EMPTY_METRIC },
          water_ph: { ...EMPTY_METRIC },
          exhaust_gas_temp_c: { ...EMPTY_METRIC },
          feed_water_temp_c: { ...EMPTY_METRIC },
          feed_water_tank_level_percent: { ...EMPTY_METRIC },
          boiler_water_level_percent: { ...EMPTY_METRIC },
          dust_collector_differential_pressure_mmh2o: { ...EMPTY_METRIC },
        },
        maintenance: {
          blowdown: { executed: 0, not_executed: 0, not_recorded: 0, avg_per_day: 0, all_unrecorded: false },
          sootblowing: { executed: 0, not_executed: 0, not_recorded: 0, avg_per_day: 0, all_unrecorded: false },
        },
        daily: [],
        by_unit: [],
        total: { days_with_records: 0, reading_rows: 0 },
      },
    })

    const summary = await fetchSummary('per-9')

    // "belum ada data" dan "angkanya nol" adalah dua jawaban berbeda: yang
    // null tetap null, yang nol tetap nol.
    expect(summary.has_data).toBe(false)
    expect(summary.metrics.steam_pressure_bar.avg).toBeNull()
    expect(summary.metrics.steam_pressure_bar.avg).not.toBe(0)
    expect(summary.metrics.steam_pressure_bar.reading_count).toBe(0)
    expect(summary.coverage.coverage_percent).toBe(0)
    expect(summary.daily).toEqual([])
    expect(summary.by_unit).toEqual([])
    expect(summary.total.reading_rows).toBe(0)
    expect(summary.period?.name).toBe('Periode Kosong')

    // Respons yang blok-bloknya tidak ada SAMA SEKALI pun tidak melempar —
    // nilai bawaan hanya berlaku bila BLOK-nya absen, bukan bila medannya
    // dikirim null. Layar yang memutuskan menampilkan keterangannya.
    apiGetMock.mockResolvedValueOnce({ data: {} })
    const bare = await fetchSummary('per-9')
    expect(bare.period).toBeNull()
    expect(bare.business_unit).toBeNull()
    expect(bare.has_data).toBe(false)
    expect(bare.daily).toEqual([])
    expect(bare.by_unit).toEqual([])
    expect(bare.coverage.expected_slots).toBe(0)
    expect(bare.metrics.water_ph.avg).toBeNull()
    expect(bare.metrics.water_ph.reading_count).toBe(0)
    expect(bare.maintenance.blowdown.all_unrecorded).toBe(false)
    expect(bare.total.days_with_records).toBe(0)
  })

  // 17
  it('fetchBusinessUnits memanggil endpoint opsi mill dan membaca pembungkus data', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [{ id: 'BU-A', name: 'PKS Sungai Bahar' }] } })

    const units = await fetchBusinessUnits()

    // Tanpa argumen config sama sekali — endpoint ini tidak berparameter.
    expect(apiGetMock).toHaveBeenCalledWith('/api/boiler-room-reports/business-units/options')
    expect(apiGetMock.mock.calls[0]).toHaveLength(1)
    expect(units).toEqual([{ id: 'BU-A', name: 'PKS Sungai Bahar' }])

    // Respons tanpa isi tetap menghasilkan daftar kosong, bukan lemparan.
    apiGetMock.mockResolvedValueOnce({ data: {} })
    await expect(fetchBusinessUnits()).resolves.toEqual([])

    apiGetMock.mockResolvedValueOnce({ data: undefined })
    await expect(fetchBusinessUnits()).resolves.toEqual([])

    // Daftar periode kosong pun jawaban yang SAH (mill belum punya periode),
    // bukan galat.
    apiGetMock.mockResolvedValueOnce({ data: { data: [] } })
    await expect(fetchPeriods()).resolves.toEqual([])
    apiGetMock.mockResolvedValueOnce({ data: {} })
    await expect(fetchPeriods()).resolves.toEqual([])
  })

  // 18
  it('exportCsv meminta responseType blob dan format csv', async () => {
    const blob = new Blob(['a,b\n1,2\n'], { type: 'text/csv' })
    apiGetMock.mockResolvedValueOnce({ data: blob })

    const result = await exportCsv('per-1')

    expect(apiGetMock).toHaveBeenCalledWith('/api/boiler-room-reports/export', {
      params: { period_id: 'per-1', format: 'csv' },
      responseType: 'blob',
    })
    // Isi CSV dibentuk SERVER; repo hanya meneruskan blob-nya apa adanya —
    // bukan CSV yang disusun ulang dari angka di layar.
    expect(result).toBe(blob)
  })

  // 19
  it('saveCsvFile memicu unduhan lewat anchor + object URL dan membersihkannya', () => {
    const clickSpy = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
    const appendSpy = vi.spyOn(document.body, 'appendChild')
    const removeSpy = vi.spyOn(document.body, 'removeChild')

    const blob = new Blob(['a,b'], { type: 'text/csv' })
    saveCsvFile(blob, 'laporan.csv')

    expect(createObjectURLMock).toHaveBeenCalledTimes(1)
    expect(createObjectURLMock).toHaveBeenCalledWith(blob)

    const anchor = appendSpy.mock.calls[0][0] as HTMLAnchorElement
    expect(anchor.tagName).toBe('A')
    expect(anchor.download).toBe('laporan.csv')
    expect(anchor.href).toContain('blob:mock-url')

    expect(clickSpy).toHaveBeenCalledTimes(1)
    expect(removeSpy).toHaveBeenCalledWith(anchor)
    // Object URL dilepas kembali — tidak ada yang tertinggal.
    expect(revokeObjectURLMock).toHaveBeenCalledTimes(1)
    expect(revokeObjectURLMock).toHaveBeenCalledWith('blob:mock-url')
    expect(document.body.contains(anchor)).toBe(false)
  })

  // 20 — BENTUK GALAT NYATA: objek DATAR, tanpa `response`.
  it('galat dari apiClient diteruskan naik APA ADANYA sebagai objek DATAR, tidak ditelan menjadi hasil kosong', async () => {
    const forbidden = { message: 'Tidak berwenang.', status: 403 }
    apiGetMock.mockRejectedValueOnce(forbidden)

    const caught = await fetchSummary('per-1').then(
      () => null,
      (error: unknown) => error,
    )

    // Objek yang SAMA PERSIS — bukan dibungkus ulang menjadi kelas galat
    // sendiri, bukan ditambah medan, bukan diganti nilai kosong.
    expect(caught).toBe(forbidden)
    expect(caught).toEqual({ message: 'Tidak berwenang.', status: 403 })
    expect(caught).not.toBeInstanceOf(Error)
    expect((caught as { status?: number }).status).toBe(403)
    // `.response` tidak pernah ada di produksi: interceptor apiClient sudah
    // membuangnya lewat normalizeError.
    expect(caught).not.toHaveProperty('response')
    expect(Object.keys(caught as object).sort()).toEqual(['message', 'status'])

    // Penerjemahan 401/403 menjadi perilaku layar adalah tugas view.
    const unauthenticated = { message: 'Unauthenticated.', status: 401 }
    apiGetMock.mockRejectedValueOnce(unauthenticated)
    await expect(fetchSummary('per-1')).rejects.toBe(unauthenticated)
  })

  // 21
  it('galat transport tanpa status juga diteruskan naik apa adanya', async () => {
    const transport = { message: 'Tidak dapat terhubung ke server. Data akan disimpan secara lokal.' }

    // Ketiadaan `status` itulah yang membedakan "jaringan putus" dari
    // "server menjawab 401/403/422" — pembeda yang dipakai view untuk
    // memilih antara Coba Lagi dan Login. Repo tidak mengarang status.
    apiGetMock.mockRejectedValueOnce(transport)
    await expect(fetchPeriods()).rejects.toBe(transport)

    apiGetMock.mockRejectedValueOnce(transport)
    const caught = await fetchSummary('per-1').then(
      () => null,
      (error: unknown) => error,
    )
    expect(caught).toBe(transport)
    expect(caught).not.toHaveProperty('status')
    expect(caught).not.toHaveProperty('response')

    apiGetMock.mockRejectedValueOnce(transport)
    await expect(fetchBusinessUnits()).rejects.toBe(transport)
    apiGetMock.mockRejectedValueOnce(transport)
    await expect(exportCsv('per-1')).rejects.toBe(transport)

    // Mock sudah kembali normal di sini — jadi asersi di atas benar-benar
    // menguji jalur galat, bukan repo yang kebetulan selalu melempar.
    await expect(fetchPeriods()).resolves.toEqual([PERIOD_BOILER_ROOM])
    await expect(fetchSummary('per-1')).resolves.toHaveProperty('metrics')
  })

  // 22 — ATURAN BISNIS: tidak ada perhitungan kedua di ponsel.
  it('repo tidak memuat satu pun perhitungan KPI di sisi klien', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    // (a) Identitas objek: tidak ada agregasi, pengurutan, maupun
    //     penyaringan yang dapat terjadi tanpa memutus identitas ini.
    expect(summary.coverage).toBe(body.coverage)
    expect(summary.metrics).toBe(body.metrics)
    expect(summary.maintenance).toBe(body.maintenance)
    expect(summary.daily).toBe(body.daily)
    expect(summary.by_unit).toBe(body.by_unit)
    expect(summary.total).toBe(body.total)

    // (b) Angka-angka yang saling bertentangan TETAP bertentangan — repo
    //     tidak "memperbaiki" satu pun dari mereka. Inilah yang tidak dapat
    //     dibuktikan dengan payload yang konsisten.
    const dailyPressure = DAILY_ROWS.map((row) => row.steam_pressure_avg).filter(
      (value): value is number => value !== null,
    )
    const dailyPressureMean = dailyPressure.reduce((sum, value) => sum + value, 0) / dailyPressure.length
    const dailyBlowdown = DAILY_ROWS.reduce((sum, row) => sum + row.blowdown_executed, 0)
    const dailySootblowing = DAILY_ROWS.reduce((sum, row) => sum + row.sootblowing_executed, 0)
    const unitBlowdown = BY_UNIT_ROWS.reduce((sum, row) => sum + row.blowdown_executed, 0)

    // Tidak ada perataan tekanan maupun suhu.
    expect(summary.metrics.steam_pressure_bar.avg).toBe(21.7)
    expect(summary.metrics.steam_pressure_bar.avg).not.toBe(dailyPressureMean)
    expect(summary.metrics.steam_pressure_bar.avg).not.toBe((18.2 + 26.9) / 2)
    expect(summary.metrics.steam_pressure_bar.avg).not.toBe(Math.round(21.7))
    // Ekstrem berasal dari PEMBACAAN MENTAH: max (26,9) bahkan lebih kecil
    // daripada rata-rata harian tertinggi (42,1) — mustahil bila diturunkan
    // dari kolom harian.
    expect(summary.metrics.steam_pressure_bar.max).toBe(26.9)
    expect(summary.metrics.steam_pressure_bar.max).not.toBe(Math.max(...dailyPressure))
    expect(summary.metrics.steam_pressure_bar.min).not.toBe(Math.min(...dailyPressure))

    // Tidak ada penjumlahan blowdown/sootblowing.
    expect(summary.maintenance.blowdown.executed).toBe(5)
    expect(summary.maintenance.blowdown.executed).not.toBe(dailyBlowdown)
    expect(summary.maintenance.blowdown.executed).not.toBe(unitBlowdown)
    expect(summary.maintenance.sootblowing.executed).toBe(0)
    expect(summary.maintenance.sootblowing.executed).not.toBe(dailySootblowing)

    // Tidak ada pembulatan ulang coverage_percent maupun avg_per_day.
    expect(summary.coverage.coverage_percent).toBe(6.3)
    expect(summary.coverage.coverage_percent).not.toBe((100 * 9) / 144)
    expect(summary.maintenance.blowdown.avg_per_day).toBe(0.83)
    expect(summary.maintenance.blowdown.avg_per_day).not.toBe(5 / 6)

    // Tidak ada penurunan has_data dan tidak ada penjumlahan total.
    expect(summary.has_data).toBe(true)
    expect(summary.total.days_with_records).toBe(11)
    expect(summary.total.days_with_records).not.toBe(summary.daily.length)
    expect(summary.total.reading_rows).toBe(412)

    // (c) Setiap medan hasil === medan masukan, medan demi medan.
    expect(summary.metrics).toEqual(body.metrics)
    expect(summary.maintenance).toEqual(body.maintenance)
    expect(summary.coverage).toEqual(body.coverage)
    expect(summary.daily).toEqual(body.daily)
    expect(summary.by_unit).toEqual(body.by_unit)
    expect(summary.total).toEqual(body.total)
  })

  // 23 — Pemeriksaan penutup lewat objek default modul.
  it('mengembalikan hasil lengkap ketika seluruh kondisi terpenuhi', async () => {
    apiGetMock.mockClear()

    const summary = await boilerRoomReportRepo.fetchSummary('per-1', {
      isAdmin: false,
      businessUnitId: null,
    })

    // Seluruh blok payload hadir dengan nilai identik.
    expect(summary.period?.id).toBe('per-1')
    expect(summary.business_unit?.name).toBe('PKS Sungai Bahar')
    expect(summary.has_data).toBe(true)
    expect(summary.coverage.coverage_percent).toBe(6.3)
    expect(Object.keys(summary.metrics)).toHaveLength(9)
    expect(summary.metrics.steam_pressure_bar.avg).toBe(21.7)
    expect(summary.maintenance.blowdown.not_recorded).toBe(41)
    expect(summary.daily).toHaveLength(3)
    expect(summary.by_unit).toHaveLength(2)
    expect(summary.total.reading_rows).toBe(412)

    // apiClient.get dipanggil TEPAT SATU KALI — tidak ada permintaan
    // tambahan yang dikirim repo.
    expect(apiGetMock).toHaveBeenCalledTimes(1)
    expect(apiGetMock).toHaveBeenCalledWith('/api/boiler-room-reports/summary', {
      params: { period_id: 'per-1' },
    })

    // Objek default mengekspos kelima fungsi yang dipakai view.
    expect(Object.keys(boilerRoomReportRepo).sort()).toEqual([
      'exportCsv',
      'fetchBusinessUnits',
      'fetchPeriods',
      'fetchSummary',
      'saveCsvFile',
    ])

    const units = await boilerRoomReportRepo.fetchBusinessUnits()
    const periods = await boilerRoomReportRepo.fetchPeriods({ isAdmin: true, businessUnitId: 'BU-A' })
    const blob = await boilerRoomReportRepo.exportCsv('per-1')

    expect(units).toEqual(BUSINESS_UNITS)
    expect(periods).toEqual([PERIOD_BOILER_ROOM])
    expect(blob).toBeInstanceOf(Blob)

    // Admin: business_unit_id memang ikut — dan HANYA untuk Admin.
    expect(apiGetMock).toHaveBeenCalledWith('/api/boiler-room-reports/periods', {
      params: { business_unit_id: 'BU-A' },
    })
    // Ekspor tidak pernah menyertakan business_unit_id — period_id sudah
    // menentukan mill-nya di sisi server.
    expect(paramsOf('/api/boiler-room-reports/export')).toEqual({ period_id: 'per-1', format: 'csv' })
  })
})

/* ================================================================== */
/* Production Line — parameter permintaan baru (2026-09-28)            */
/* ================================================================== */

/**
 * Production Line adalah KONTEKS YANG DIPILIH, bukan ikatan akun: tidak ada
 * `users.production_line_id` dan tidak boleh ada. Yang dijaga berkas ini
 * hanyalah kontrak lapisan repo-nya:
 *
 *   - production_line_id hanya berangkat ke /summary dan /export;
 *   - ia TIDAK PERNAH berangkat ke /periods — periode milik MILL, dan
 *     menyaring daftarnya per line akan mengarang penyempitan yang tidak
 *     ada di data;
 *   - tanpa nilai, parameternya ABSEN (bukan kosong), sehingga jawaban
 *     server identik dengan sebelum fitur ini ada;
 *   - `production_line` pada respons dipetakan apa adanya, dan repo tidak
 *     pernah menggabungkan dua line menjadi satu angka.
 */
describe('boilerRoomReportRepo — production_line_id (konteks yang dipilih, bukan ikatan akun)', () => {
  it('fetchSummary mengirim production_line_id ketika ada, dan MENGABSENKAN parameternya ketika tidak', async () => {
    await fetchSummary('per-1', { productionLineId: 'pl-2' })

    expect(paramsOf('/api/boiler-room-reports/summary')).toEqual({
      period_id: 'per-1',
      production_line_id: 'pl-2',
    })

    // Tanpa nilai: bukan null, bukan string kosong — kuncinya ABSEN, agar
    // jawaban server persis sama dengan sebelum fitur ini ada.
    apiGetMock.mockClear()
    await fetchSummary('per-1')
    await fetchSummary('per-1', { productionLineId: null })
    await fetchSummary('per-1', { productionLineId: '' })

    expect(callsTo('/api/boiler-room-reports/summary')).toHaveLength(3)
    expect(JSON.stringify(apiGetMock.mock.calls)).not.toContain('production_line_id')
  })

  it('production_line_id tidak bercabang menurut peran — ia konteks angka, bukan kewenangan', async () => {
    // Peran terikat mill: business_unit_id tetap ditahan (gagal tertutup),
    // tetapi production_line_id TETAP berangkat. Keduanya memang menjawab
    // pertanyaan yang berbeda.
    await fetchSummary('per-1', { isAdmin: false, businessUnitId: 'bu-9', productionLineId: 'pl-2' })

    expect(paramsOf('/api/boiler-room-reports/summary')).toEqual({
      period_id: 'per-1',
      production_line_id: 'pl-2',
    })

    apiGetMock.mockClear()
    await fetchSummary('per-1', { isAdmin: true, businessUnitId: 'bu-9', productionLineId: 'pl-2' })

    expect(paramsOf('/api/boiler-room-reports/summary')).toEqual({
      period_id: 'per-1',
      business_unit_id: 'bu-9',
      production_line_id: 'pl-2',
    })
  })

  it('fetchPeriods TIDAK PERNAH menyaring per Production Line — periode milik mill', async () => {
    await fetchPeriods({ productionLineId: 'pl-2' })
    await fetchPeriods({ isAdmin: true, businessUnitId: 'bu-9', productionLineId: 'pl-2' })

    expect(callsTo('/api/boiler-room-reports/periods')).toHaveLength(2)
    expect(paramsOf('/api/boiler-room-reports/periods')).toEqual({})

    const periodCalls = apiGetMock.mock.calls.filter((call) => String(call[0]).includes('/periods'))
    expect(JSON.stringify(periodCalls)).not.toContain('production_line_id')
    expect(JSON.stringify(periodCalls)).not.toContain('pl-2')
  })

  it('exportCsv membawa production_line_id — berkas mengikuti cakupan angka di layar', async () => {
    await exportCsv('per-1', { productionLineId: 'pl-2' })

    expect(apiGetMock).toHaveBeenCalledWith('/api/boiler-room-reports/export', {
      params: { period_id: 'per-1', format: 'csv', production_line_id: 'pl-2' },
      responseType: 'blob',
    })

    // Tanpa line, bentuk permintaannya persis seperti sebelum fitur ini ada
    // — nama berkas dan kolom CSV pun tidak berubah (dibentuk SERVER).
    apiGetMock.mockClear()
    await exportCsv('per-1')

    expect(apiGetMock).toHaveBeenCalledWith('/api/boiler-room-reports/export', {
      params: { period_id: 'per-1', format: 'csv' },
      responseType: 'blob',
    })
  })

  it('exportCsv membawa business_unit_id untuk Admin — tanpa itu server menjawab 422', async () => {
    // CACAT PRA-ADA yang ditutup 2026-09-28: exportCsv mengirim
    // productionLineParams tapi TIDAK scopeParams, padahal fetchSummary di
    // berkas yang sama mengirimnya. Untuk Admin (yang tidak terikat mill)
    // boiler-room-reports/export memanggil resolveBusinessUnit(null) dan
    // melempar 422 "Pilih mill terlebih dahulu" -- jadi tombol Ekspor
    // Admin di mobile menghasilkan galat, bukan berkas.
    //
    // Test lama tidak menangkapnya karena scope-nya hanya membawa line,
    // tidak pernah membawa mill; scopeParams() sendiri hanya mengirim mill
    // bagi Admin, sebab mill non-Admin memang dibuang server.
    await exportCsv('per-1', { isAdmin: true, businessUnitId: 'BU-B', productionLineId: 'pl-2' })

    expect(apiGetMock).toHaveBeenCalledWith('/api/boiler-room-reports/export', {
      params: { period_id: 'per-1', format: 'csv', business_unit_id: 'BU-B', production_line_id: 'pl-2' },
      responseType: 'blob',
    })

    // Non-Admin: mill TIDAK dikirim -- server menurunkannya dari akun, dan
    // mengirimnya hanya akan jadi nilai yang dibuang.
    apiGetMock.mockClear()
    await exportCsv('per-1', { isAdmin: false, businessUnitId: 'BU-A', productionLineId: 'pl-2' })

    expect(apiGetMock).toHaveBeenCalledWith('/api/boiler-room-reports/export', {
      params: { period_id: 'per-1', format: 'csv', production_line_id: 'pl-2' },
      responseType: 'blob',
    })
  })

  it('production_line pada respons dipetakan apa adanya, dan null ketika server tidak mengirimnya', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { production_line: { id: 'pl-2', name: 'Line 2' } } })

    const withLine = await fetchSummary('per-1', { productionLineId: 'pl-2' })

    expect(withLine.production_line).toEqual({ id: 'pl-2', name: 'Line 2' })

    // Permintaan tanpa line, atau line milik mill lain: server menjawab
    // null — BUKAN 403, dan bukan pula nama karangan sisi klien.
    apiGetMock.mockResolvedValueOnce({ data: {} })

    const withoutLine = await fetchSummary('per-1')

    expect(withoutLine.production_line).toBeNull()
  })

  it('dua line dengan isi berbeda — repo memulangkan milik line yang diminta, tidak pernah menggabungkannya', async () => {
    // Server menjawab menurut production_line_id yang dikirim. Repo yang
    // benar meneruskan jawaban itu apa adanya; repo yang "membantu" dengan
    // menggabungkan dua line akan gagal di sini.
    apiGetMock.mockImplementation((url: string, config?: { params?: Record<string, string> }) => {
      if (!String(url).includes('/summary')) {
        return Promise.reject(new Error(`Endpoint tak terduga: ${url}`))
      }

      const lineId = String(config?.params?.production_line_id ?? '')

      return Promise.resolve({
        data: {
          production_line: { id: lineId, name: lineId === 'pl-1' ? 'Line 1' : 'Line 2' },
          period: { id: 'per-1', name: `Periode ${lineId}` },
        },
      })
    })

    const lineOne = await fetchSummary('per-1', { productionLineId: 'pl-1' })
    const lineTwo = await fetchSummary('per-1', { productionLineId: 'pl-2' })

    expect(lineOne.production_line).toEqual({ id: 'pl-1', name: 'Line 1' })
    expect(lineTwo.production_line).toEqual({ id: 'pl-2', name: 'Line 2' })
    expect(lineOne.period?.name).toBe('Periode pl-1')
    expect(lineTwo.period?.name).toBe('Periode pl-2')
    expect(lineOne).not.toEqual(lineTwo)
  })

  it('objek default mengekspos exportCsv yang menerima cakupan line', async () => {
    await boilerRoomReportRepo.exportCsv('per-1', { productionLineId: 'pl-3' })

    expect(apiGetMock).toHaveBeenCalledWith('/api/boiler-room-reports/export', {
      params: { period_id: 'per-1', format: 'csv', production_line_id: 'pl-3' },
      responseType: 'blob',
    })
  })
})

/*
 * Temuan audit 2026-10-04 #3 — /summary kini mengirim coverage.days_counted
 * dan coverage.period_running. Repo meneruskannya apa adanya, dan blok
 * coverage bawaan (blok absen) membawa keduanya dengan nilai yang aman:
 * 0 hari, periode tidak berjalan — bukan undefined yang membuat layar
 * menulis "undefined hari".
 */
describe('boilerRoomReportRepo — days_counted / period_running (temuan audit 2026-10-04)', () => {
  it('meneruskan days_counted dan period_running dari /api/boiler-room-reports/summary apa adanya', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: {
        coverage: {
          filled_slots: 96,
          expected_slots: 192,
          coverage_percent: 50,
          days_in_period: 9,
          days_counted: 4,
          period_running: true,
        },
      },
    })

    const summary = await fetchSummary('per-1')

    expect(summary.coverage.days_counted).toBe(4)
    expect(summary.coverage.days_in_period).toBe(9)
    expect(summary.coverage.period_running).toBe(true)
  })

  it('blok coverage absen — bawaan membawa days_counted 0 dan period_running false', async () => {
    apiGetMock.mockResolvedValueOnce({ data: {} })

    const bare = await fetchSummary('per-9')

    expect(bare.coverage.days_counted).toBe(0)
    expect(bare.coverage.period_running).toBe(false)
  })
})
