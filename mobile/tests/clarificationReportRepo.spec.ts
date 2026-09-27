/**
 * clarificationReportRepo.spec.ts — screen-138--laporan-clarification-mobile /
 * usecase-138--laporan-clarification-mobile "Lihat Laporan Periode
 * Clarification (Mobile)".
 *
 * Satu test per unit_test_cases pada tech spec screen-138 — seluruh 26.
 * Kembaran dari tests/sterilizerReportRepo.spec.ts (screen-135),
 * tests/cagesTrackReportRepo.spec.ts (screen-136), dan
 * tests/boilerRoomReportRepo.spec.ts (screen-137), untuk
 * src/services/clarificationReportRepo.ts.
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
 *   - production.total_ton (128,5) ≠ jumlah daily.production_ton (66,0) dan
 *     ≠ jumlah daily.rate_avg (51,5) — menjumlahkan laju per jam sendiri
 *     adalah godaan terbesar di layar ini, dan fixture inilah yang
 *     menangkapnya;
 *   - production.total_ton (128,5) juga ≠ 128,5 − downtime 240/60 × laju,
 *     dan ≠ total_ton mana pun yang dikoreksi downtime;
 *   - production.avg_per_day_ton (21,4) ≠ 128,5 / 11 (11,68…) dan ≠
 *     128,5 / 3 (42,83…);
 *   - production.avg_rate_ton_hour (9,33) ≠ 128,5 / 96 (1,338…) — penyebut
 *     rata-rata laju BUKAN reading_count produksi yang tampil di sebelahnya;
 *   - metrics.pure_oil_production_rate_ton_hour.max (14,8) LEBIH KECIL
 *     daripada daily[0].rate_avg (42,1) — mustahil bila ekstremnya
 *     diturunkan dari kolom harian, dan memang tidak: ekstrem berasal dari
 *     PEMBACAAN MENTAH per slot waktu (ClarificationReportService::metricsOf);
 *   - metrics.pure_oil_production_rate_ton_hour.reading_count (120) ≠
 *     production.reading_count (96) ≠ jumlah reading_count by_unit (301) ≠
 *     total.reading_rows (412);
 *   - metrics.buffer_tank_level_percent.reading_count (6) jauh berbeda dari
 *     metrik lain — tidak ada satu penyebut bersama yang dapat menghasilkan
 *     keduanya;
 *   - coverage.expected_slots (144) ≠ unit_count × days_in_period ×
 *     slots_per_unit_per_day (2 × 6 × 24 = 288), sehingga coverage_percent
 *     6,25 mustahil diturunkan ulang dari medan-medan di sebelahnya
 *     (100 × 9 / 288 = 3,125);
 *   - coverage.filled_slots (9) ≠ jumlah daily.filled_slots (12);
 *   - downtime.total_mins (240) ≠ jumlah daily.downtime_mins (95), dan
 *     downtime.avg_per_day_mins (20,5) ≠ 240 / 11 maupun 240 / 3;
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
 * view (diuji di LaporanClarificationView.spec.ts) — yang wajib dibuktikan
 * di sini hanyalah prasyaratnya: galat naik APA ADANYA, objeknya identik.
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

import clarificationReportRepo, {
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  exportCsv,
  saveCsvFile,
} from '@/services/clarificationReportRepo'

/* ------------------------------------------------------------------ */
/* Fixture — BENTUK RESPONS NYATA                                      */
/* ------------------------------------------------------------------ */

const PERIOD_CLARIFICATION = {
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

const BUSINESS_UNITS = [
  { id: 'BU-A', name: 'PKS Sungai Bahar' },
  { id: 'BU-B', name: 'PKS Muara Bulian' },
]

/**
 * Keenam metrik numerik, masing-masing dengan PENYEBUTNYA SENDIRI.
 * buffer_tank_level_percent sengaja hanya 6 pembacaan sementara
 * pure_oil_production_rate_ton_hour 120 — tidak ada satu penyebut bersama
 * yang dapat menghasilkan keduanya, dan itulah yang membuat asersi "tiap
 * metrik punya penyebutnya sendiri" bermakna.
 */
function makeMetrics(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    // max 14,8 LEBIH KECIL daripada daily[0].rate_avg 42,1 (ekstrem mentah
    // vs rata-rata harian — sengaja tak dapat direkonsiliasi).
    pure_oil_production_rate_ton_hour: { min: 4.1, avg: 9.33, max: 14.8, reading_count: 120 },
    clarification_tank_temp_c: { min: 89.5, avg: 93.4, max: 95.1, reading_count: 113 },
    oil_tank_temperature_c: { min: 93.2, avg: 97.2, max: 98.9, reading_count: 106 },
    sludge_tank_temp_c: { min: 83.5, avg: 87.6, max: 89.4, reading_count: 98 },
    buffer_tank_level_percent: { min: 58.7, avg: 72.4, max: 78.4, reading_count: 6 },
    downtime_mins: { min: 0, avg: 6.0, max: 45, reading_count: 40 },
    ...overrides,
  }
}

const EMPTY_METRIC = { min: null, avg: null, max: null, reading_count: 0 }

/**
 * Tiga tanggal ber-record. Baris kedua kosong pada SELURUH kolom rata-rata
 * — tanggal itu tetap tanggal ber-record dan tidak boleh dibuang. Jumlah
 * filled_slots (4 + 3 + 5 = 12) sengaja BERBEDA dari coverage.filled_slots
 * (9), dan jumlah production_ton (66,0) berbeda dari
 * production.total_ton (128,5).
 */
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

/** Unit kedua tanpa satu pun pembacaan — temuan, bukan baris yang dibuang. */
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

/**
 * total_ton 128,5 ≠ jumlah daily.production_ton (66,0) dan ≠ jumlah
 * daily.rate_avg (51,5); avg_per_day_ton 21,4 ≠ 128,5 / 11 maupun / 3;
 * avg_rate_ton_hour 9,33 ≠ 128,5 / 96.
 */
const PRODUCTION = {
  total_ton: 128.5,
  avg_per_day_ton: 21.4,
  avg_production_per_day_ton: 21.4,
  reading_count: 96,
  avg_rate_ton_hour: 9.33,
  min_rate_ton_hour: 4.1,
  max_rate_ton_hour: 14.8,
}

/** total_mins 240 ≠ jumlah daily.downtime_mins (95); avg 20,5 ≠ 240/11 ≠ 240/3. */
const DOWNTIME = {
  total_mins: 240,
  avg_per_day_mins: 20.5,
  avg_downtime_per_day_mins: 20.5,
  hours_with_downtime: 7,
  reading_count: 40,
}

const COVERAGE = {
  filled_slots: 9,
  // 144 ≠ 2 × 6 × 24 (288). coverage_percent 6,25 karena itu mustahil
  // diturunkan ulang dari medan di sebelahnya (100 × 9 / 288 = 3,125), dan
  // dua desimalnya itulah yang membedakan periode yang buruk pencatatannya
  // dari yang katastrofik — 6,3 bukan jawaban yang sama.
  expected_slots: 144,
  coverage_percent: 6.25,
  unit_count: 2,
  slots_per_unit_per_day: 24,
  days_in_period: 6,
}

/** days_with_records 11 ≠ panjang daily (3); reading_rows 412 milik server. */
const TOTAL = { days_with_records: 11, reading_rows: 412 }

/**
 * Respons /summary yang dikirim ClarificationReportController::summary() —
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
    production: { ...PRODUCTION },
    downtime: { ...DOWNTIME },
    metrics: makeMetrics(),
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
      return Promise.resolve({ data: { data: [PERIOD_CLARIFICATION] } })
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
/* Lapisan repo screen-138 — 26 kasus (unit_test_cases tech spec)      */
/* ================================================================== */

describe('clarificationReportRepo — lapisan repo (screen-138)', () => {
  // 1
  it('fetchPeriods memanggil tepat GET /api/clarification-reports/periods dan tidak menyaring jenis stasiun di klien', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [PERIOD_CLARIFICATION, PERIOD_LINTAS_STASIUN] } })

    const periods = await fetchPeriods()

    expect(callsTo('/api/clarification-reports/periods')).toHaveLength(1)
    expect(apiGetMock).toHaveBeenCalledTimes(1)
    expect(apiGetMock).toHaveBeenCalledWith('/api/clarification-reports/periods', { params: {} })

    // Seluruh entri diteruskan apa adanya, dalam urutan server. Penyaringan
    // cakupan Clarification sepenuhnya milik server — termasuk periode yang
    // mencakup banyak jenis stasiun sekaligus, yang TIDAK boleh dibuang di
    // sini.
    expect(periods).toEqual([PERIOD_CLARIFICATION, PERIOD_LINTAS_STASIUN])
    expect(periods).toHaveLength(2)
    expect(periods[0].station_type).toBe('clarification')
    expect(periods[1].station_type).toBe('clarification')
  })

  // 2 — GAGAL TERTUTUP: peran terikat mill (termasuk Operator) tidak pernah
  //     mengirim business_unit_id.
  it('scopeParams tidak mengirim business_unit_id untuk peran yang terikat mill, termasuk Operator', async () => {
    await fetchPeriods({ isAdmin: false, businessUnitId: 'BU-A' })

    const params = paramsOf('/api/clarification-reports/periods')

    // Medannya ABSEN — bukan null, bukan string kosong. Bahkan ketika
    // pemanggil menyertakan businessUnitId, nilai itu tidak ikut terkirim:
    // Operator ada di cabang TERIKAT MILL, sama seperti Supervisor dan Mill
    // Management, dan mill-nya ditentukan server dari akun.
    expect(params).toEqual({})
    expect(Object.keys(params)).toHaveLength(0)
    expect(params).not.toHaveProperty('business_unit_id')

    // Berlaku juga untuk /summary.
    await fetchSummary('per-1', { isAdmin: false, businessUnitId: 'BU-B' })
    expect(paramsOf('/api/clarification-reports/summary')).toEqual({ period_id: 'per-1' })
  })

  // 3
  it('scopeParams mengirim business_unit_id hanya ketika Admin sudah memilih mill', async () => {
    await fetchPeriods({ isAdmin: true, businessUnitId: 'BU-B' })

    expect(paramsOf('/api/clarification-reports/periods')).toEqual({ business_unit_id: 'BU-B' })

    await fetchSummary('per-1', { isAdmin: true, businessUnitId: 'BU-B' })
    expect(paramsOf('/api/clarification-reports/summary')).toEqual({
      period_id: 'per-1',
      business_unit_id: 'BU-B',
    })
  })

  // 4
  it('scopeParams tidak mengirim business_unit_id ketika Admin belum memilih mill', async () => {
    // Repo tidak mengarang nilai mill dan tidak melempar galat sendiri —
    // server yang menjawab 422 VALIDATION_ERROR, dan satu sumber kebenaran
    // itulah yang ditampilkan.
    await expect(fetchPeriods({ isAdmin: true, businessUnitId: null })).resolves.toEqual([
      PERIOD_CLARIFICATION,
    ])

    expect(paramsOf('/api/clarification-reports/periods')).toEqual({})
  })

  // 5
  it('fetchSummary memanggil GET /api/clarification-reports/summary dengan period_id', async () => {
    await fetchSummary('per-1')

    expect(apiGetMock).toHaveBeenCalledTimes(1)
    expect(apiGetMock).toHaveBeenCalledWith('/api/clarification-reports/summary', {
      params: { period_id: 'per-1' },
    })
    // Tidak ada parameter lain yang ditambahkan repo.
    expect(Object.keys(paramsOf('/api/clarification-reports/summary'))).toEqual(['period_id'])
  })

  // 6
  it('unwrap menerima respons summary TANPA pembungkus data maupun DENGAN pembungkus', async () => {
    const body = makeSummaryBody()

    apiGetMock.mockResolvedValueOnce({ data: body })
    const bare = await fetchSummary('per-1')

    apiGetMock.mockResolvedValueOnce({ data: { data: body } })
    const wrapped = await fetchSummary('per-1')

    // Identik medan per medan — repo tidak pecah bila pembungkus `data`
    // kelak diseragamkan di sisi server.
    expect(wrapped).toEqual(bare)
    expect(wrapped.production).toEqual(bare.production)
    expect(wrapped.metrics).toEqual(bare.metrics)
    expect(wrapped.daily).toEqual(bare.daily)

    // /periods dan /business-units/options tetap dibaca LEWAT pembungkus.
    apiGetMock.mockResolvedValueOnce({ data: { data: [PERIOD_CLARIFICATION] } })
    await expect(fetchPeriods()).resolves.toEqual([PERIOD_CLARIFICATION])
    apiGetMock.mockResolvedValueOnce({ data: { data: BUSINESS_UNITS } })
    await expect(fetchBusinessUnits()).resolves.toEqual(BUSINESS_UNITS)
  })

  // 7
  it('fetchSummary meneruskan seluruh blok apa adanya tanpa perhitungan kedua', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    // Kesepuluh blok hadir, nilainya identik medan per medan.
    expect(summary.period).toEqual(body.period)
    expect(summary.business_unit).toEqual(body.business_unit)
    expect(summary.has_data).toBe(true)
    expect(summary.coverage).toEqual(body.coverage)
    expect(summary.production).toEqual(body.production)
    expect(summary.downtime).toEqual(body.downtime)
    expect(summary.metrics).toEqual(body.metrics)
    expect(summary.daily).toEqual(body.daily)
    expect(summary.by_unit).toEqual(body.by_unit)
    expect(summary.total).toEqual(body.total)
  })

  // 8 — NULL BUKAN NOL: produksi yang tidak pernah diukur.
  it('fetchSummary mempertahankan production.total_ton null ketika laju tidak pernah tercatat', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({
        production: {
          total_ton: null,
          avg_per_day_ton: null,
          avg_production_per_day_ton: null,
          reading_count: 0,
          avg_rate_ton_hour: null,
          min_rate_ton_hour: null,
          max_rate_ton_hour: null,
        },
      }),
    })

    const summary = await fetchSummary('per-1')

    expect(summary.production.total_ton).toBeNull()
    expect(summary.production.avg_per_day_ton).toBeNull()
    expect(summary.production.avg_production_per_day_ton).toBeNull()
    expect(summary.production.avg_rate_ton_hour).toBeNull()
    expect(summary.production.min_rate_ton_hour).toBeNull()
    expect(summary.production.max_rate_ton_hour).toBeNull()
    expect(summary.production.reading_count).toBe(0)

    // TIDAK dikoersi menjadi 0 — produksi yang tidak pernah diukur bukan
    // produksi nol, dan di ponsel perbedaan itu hilang tanpa jejak.
    expect(summary.production.total_ton).not.toBe(0)
    expect(summary.production.avg_per_day_ton).not.toBe(0)
  })

  // 9 — GODAAN TERBESAR DI LAYAR INI.
  it('repo tidak menurunkan produksi sendiri dari laju per jam', async () => {
    const summary = await fetchSummary('per-1')

    const dailyRates = DAILY_ROWS.map((row) => row.rate_avg).filter(
      (value): value is number => value !== null,
    )
    const sumOfDailyRates = dailyRates.reduce((sum, value) => sum + value, 0)
    const sumOfDailyProduction = DAILY_ROWS.map((row) => row.production_ton)
      .filter((value): value is number => value !== null)
      .reduce((sum, value) => sum + value, 0)

    expect(summary.production.total_ton).toBe(128.5)
    // Penurunan produksi dari laju adalah pekerjaan
    // ClarificationReportService; mengulangnya di ponsel pasti menyimpang.
    expect(summary.production.total_ton).not.toBe(sumOfDailyRates)
    expect(summary.production.total_ton).not.toBe(sumOfDailyProduction)
    expect(summary.production.avg_rate_ton_hour).toBe(9.33)
    expect(summary.production.avg_rate_ton_hour).not.toBe(128.5 / 96)
    expect(summary.production.avg_per_day_ton).toBe(21.4)
    expect(summary.production.avg_per_day_ton).not.toBe(128.5 / 11)
    expect(summary.production.avg_per_day_ton).not.toBe(128.5 / DAILY_ROWS.length)
  })

  // 10 — DUA KEADAAN NOL, dan keduanya wajib tetap dapat dibedakan.
  it('fetchSummary mempertahankan downtime.total_mins null, yang berbeda dari nol', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({
        downtime: {
          total_mins: null,
          avg_per_day_mins: null,
          avg_downtime_per_day_mins: null,
          hours_with_downtime: 0,
          reading_count: 0,
        },
      }),
    })

    const neverRecorded = await fetchSummary('per-1')

    expect(neverRecorded.downtime.total_mins).toBeNull()
    expect(neverRecorded.downtime.avg_per_day_mins).toBeNull()
    expect(neverRecorded.downtime.reading_count).toBe(0)
    expect(neverRecorded.downtime.total_mins).not.toBe(0)

    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({
        downtime: {
          total_mins: 0,
          avg_per_day_mins: 0,
          avg_downtime_per_day_mins: 0,
          hours_with_downtime: 0,
          reading_count: 40,
        },
      }),
    })

    const measuredZero = await fetchSummary('per-1')

    expect(measuredZero.downtime.total_mins).toBe(0)
    expect(measuredZero.downtime.reading_count).toBe(40)

    // Pasangan (total_mins, reading_count) adalah SATU-SATUNYA pembedanya,
    // dan repo tidak menyatukan kedua keadaan itu.
    expect(neverRecorded.downtime.total_mins).not.toBe(measuredZero.downtime.total_mins)
    expect(neverRecorded.downtime.reading_count).not.toBe(measuredZero.downtime.reading_count)
  })

  // 11 — DIPUTUSKAN 2026-09-25: downtime tidak mengurangi produksi.
  it('repo tidak mengurangkan downtime dari produksi', async () => {
    const summary = await fetchSummary('per-1')

    expect(summary.production.total_ton).toBe(128.5)
    expect(summary.downtime.total_mins).toBe(240)

    // Tidak ada pengurangan, pemotongan, atau penyesuaian apa pun. Laju yang
    // diinput Operator sudah laju RATA-RATA sepanjang jam, sehingga
    // mengurangkan downtime akan menghitung ganda.
    expect(summary.production.total_ton).not.toBe(128.5 - 240 / 60)
    expect(summary.production.total_ton).not.toBe(128.5 * ((60 - 240) / 60))
    expect(summary.production.total_ton).not.toBe(128.5 - 240)
    // Keduanya diteruskan berdampingan sebagai dua angka terpisah.
    expect(summary.downtime.total_mins).not.toBe(summary.production.total_ton)
  })

  // 12
  it('fetchSummary mempertahankan metrics[metrik].min/avg/max sebagai null dengan reading_count 0', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({
        metrics: makeMetrics({ sludge_tank_temp_c: { ...EMPTY_METRIC } }),
      }),
    })

    const summary = await fetchSummary('per-1')

    expect(summary.metrics.sludge_tank_temp_c).toEqual(EMPTY_METRIC)
    expect(summary.metrics.sludge_tank_temp_c.min).toBeNull()
    expect(summary.metrics.sludge_tank_temp_c.avg).toBeNull()
    expect(summary.metrics.sludge_tank_temp_c.max).toBeNull()
    // TIDAK menjadi 0/0/0.
    expect(summary.metrics.sludge_tank_temp_c.avg).not.toBe(0)

    // Metrik lain tidak terpengaruh sama sekali.
    expect(summary.metrics.clarification_tank_temp_c.avg).toBe(93.4)
    expect(summary.metrics.clarification_tank_temp_c.reading_count).toBe(113)
  })

  // 13
  it('fetchSummary meneruskan reading_count tiap metrik sendiri-sendiri, tanpa penyebut bersama', async () => {
    const summary = await fetchSummary('per-1')

    expect(summary.metrics.pure_oil_production_rate_ton_hour.reading_count).toBe(120)
    expect(summary.metrics.buffer_tank_level_percent.reading_count).toBe(6)

    // Tidak ada satu penyebut bersama yang diturunkan dari
    // total.reading_rows (412) maupun dari production.reading_count (96).
    const counts = Object.values(summary.metrics).map((metric) => metric.reading_count)
    expect(new Set(counts).size).toBeGreaterThan(1)
    expect(counts).not.toContain(summary.total.reading_rows)
    expect(summary.metrics.pure_oil_production_rate_ton_hour.reading_count).not.toBe(
      summary.production.reading_count,
    )
  })

  // 14
  it('fetchSummary mempertahankan null pada daily[].production_ton, rate_avg, dan ketiga suhu', async () => {
    const summary = await fetchSummary('per-1')

    const blankRow = summary.daily[1]

    expect(blankRow.date).toBe('2026-03-02')
    expect(blankRow.filled_slots).toBe(3)
    expect(blankRow.production_ton).toBeNull()
    expect(blankRow.rate_avg).toBeNull()
    expect(blankRow.rate_reading_count).toBe(0)
    expect(blankRow.oil_tank_temperature_avg).toBeNull()
    expect(blankRow.sludge_tank_temp_avg).toBeNull()
    expect(blankRow.buffer_tank_level_avg).toBeNull()
    expect(blankRow.downtime_mins).toBeNull()
    // Suhu clarification-nya terisi walau yang lain kosong — satu baris
    // dapat mengisi satu metrik dan mengosongkan yang lain.
    expect(blankRow.clarification_tank_temp_avg).toBe(88.2)

    // Barisnya TIDAK dibuang dan TIDAK ditambal nol.
    expect(summary.daily).toHaveLength(3)
    expect(blankRow.production_ton).not.toBe(0)
  })

  // 15
  it('fetchSummary meneruskan by_unit termasuk unit Clarification dengan reading_count 0', async () => {
    const summary = await fetchSummary('per-1')

    expect(summary.by_unit).toHaveLength(2)
    expect(summary.by_unit.map((unit) => unit.clarification_id)).toEqual(['CLF-1', 'CLF-2'])

    const emptyUnit = summary.by_unit[1]

    expect(emptyUnit.reading_count).toBe(0)
    expect(emptyUnit.production_ton).toBeNull()
    expect(emptyUnit.rate_avg).toBeNull()
    expect(emptyUnit.clarification_tank_temp_avg).toBeNull()
    expect(emptyUnit.downtime_mins).toBeNull()
  })

  // 16
  it('fetchSummary meneruskan coverage apa adanya tanpa menghitung ulang coverage_percent', async () => {
    const summary = await fetchSummary('per-1')

    // Dua desimalnya apa adanya — 6,25 dan 6,3 menceritakan hal yang
    // berbeda tentang periode yang sama.
    expect(summary.coverage.coverage_percent).toBe(6.25)
    expect(summary.coverage.coverage_percent).not.toBe(6.3)
    // Dan mustahil diturunkan ulang dari medan di sebelahnya: expected_slots
    // (144) sengaja tidak sama dengan 2 × 6 × 24 = 288.
    expect(summary.coverage.expected_slots).not.toBe(
      summary.coverage.unit_count *
        summary.coverage.days_in_period *
        summary.coverage.slots_per_unit_per_day,
    )
    expect(summary.coverage.coverage_percent).not.toBe((100 * 9) / 288)
    // filled_slots milik server, bukan jumlah kolom harian (12).
    expect(summary.coverage.filled_slots).toBe(9)
    expect(summary.coverage.filled_slots).not.toBe(
      DAILY_ROWS.reduce((sum, row) => sum + row.filled_slots, 0),
    )
  })

  // 17
  it('fetchSummary meneruskan has_data apa adanya dan tidak menurunkannya sendiri', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({ has_data: false, total: { days_with_records: 0, reading_rows: 0 } }),
    })

    const summary = await fetchSummary('per-1')

    // daily-nya TIDAK kosong pada fixture ini, jadi has_data false hanya
    // dapat datang dari server — bukan diturunkan dari panjang daily.
    expect(summary.has_data).toBe(false)
    expect(summary.daily.length).toBeGreaterThan(0)
    expect(summary.total.reading_rows).toBe(0)
  })

  // 18
  it('fetchSummary tidak mengurutkan ulang maupun menyaring daily, termasuk baris bernilai nol', async () => {
    const fiveDays = [
      { ...DAILY_ROWS[2], date: '2026-03-06' },
      { ...DAILY_ROWS[0], date: '2026-03-01' },
      {
        date: '2026-03-03',
        filled_slots: 0,
        production_ton: 0,
        rate_avg: 0,
        rate_reading_count: 0,
        clarification_tank_temp_avg: 0,
        oil_tank_temperature_avg: 0,
        sludge_tank_temp_avg: 0,
        buffer_tank_level_avg: 0,
        downtime_mins: 0,
      },
      { ...DAILY_ROWS[1], date: '2026-03-02' },
      { ...DAILY_ROWS[2], date: '2026-03-05' },
    ]

    apiGetMock.mockResolvedValueOnce({ data: makeSummaryBody({ daily: fiveDays }) })

    const summary = await fetchSummary('per-1')

    // Panjang, urutan, dan isinya sama persis dengan yang diterima — urutan
    // di sini sengaja BUKAN kronologis, supaya repo yang menyortir tertangkap.
    expect(summary.daily).toHaveLength(5)
    expect(summary.daily.map((row) => row.date)).toEqual([
      '2026-03-06',
      '2026-03-01',
      '2026-03-03',
      '2026-03-02',
      '2026-03-05',
    ])
    expect(summary.daily).toEqual(fiveDays)
    // Baris bernilai nol TIDAK dibuang — ia justru harus terbaca sebagai
    // hari yang tercatat.
    expect(summary.daily[2].production_ton).toBe(0)
  })

  // 19
  it('fetchSummary meneruskan payload periode kosong apa adanya', async () => {
    const emptyBody = makeSummaryBody({
      has_data: false,
      coverage: { ...COVERAGE, filled_slots: 0 },
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
      metrics: {
        pure_oil_production_rate_ton_hour: { ...EMPTY_METRIC },
        clarification_tank_temp_c: { ...EMPTY_METRIC },
        oil_tank_temperature_c: { ...EMPTY_METRIC },
        sludge_tank_temp_c: { ...EMPTY_METRIC },
        buffer_tank_level_percent: { ...EMPTY_METRIC },
        downtime_mins: { ...EMPTY_METRIC },
      },
      daily: [],
      by_unit: [],
      total: { days_with_records: 0, reading_rows: 0 },
    })

    apiGetMock.mockResolvedValueOnce({ data: emptyBody })

    const summary = await fetchSummary('per-1')

    expect(summary.has_data).toBe(false)
    expect(summary.daily).toEqual([])
    expect(summary.by_unit).toEqual([])
    // Blok kosong diteruskan apa adanya, TIDAK diganti nol — "belum ada
    // data" dan "angkanya nol" adalah dua jawaban berbeda.
    expect(summary.production.total_ton).toBeNull()
    expect(summary.downtime.total_mins).toBeNull()
    expect(summary.metrics.clarification_tank_temp_c.avg).toBeNull()
    expect(summary).toEqual({
      period: emptyBody.period,
      business_unit: emptyBody.business_unit,
      has_data: false,
      coverage: emptyBody.coverage,
      production: emptyBody.production,
      downtime: emptyBody.downtime,
      metrics: emptyBody.metrics,
      daily: [],
      by_unit: [],
      total: emptyBody.total,
    })
  })

  // 20
  it('fetchBusinessUnits memanggil endpoint opsi mill dan membaca pembungkus data', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [{ id: 'BU-A', name: 'Mill A' }] } })

    const units = await fetchBusinessUnits()

    expect(apiGetMock).toHaveBeenCalledWith('/api/clarification-reports/business-units/options')
    // Tanpa params sama sekali — tidak ada argumen kedua.
    expect(callsTo('/api/clarification-reports/business-units/options')[0]).toHaveLength(1)
    expect(units).toEqual([{ id: 'BU-A', name: 'Mill A' }])

    // Respons tanpa `data` menghasilkan array kosong, bukan galat.
    apiGetMock.mockResolvedValueOnce({ data: {} })
    await expect(fetchBusinessUnits()).resolves.toEqual([])
  })

  // 21
  it('exportCsv meminta responseType blob dan format csv', async () => {
    const blob = await exportCsv('per-1')

    expect(apiGetMock).toHaveBeenCalledWith('/api/clarification-reports/export', {
      params: { period_id: 'per-1', format: 'csv' },
      responseType: 'blob',
    })
    // Nilai kembaliannya adalah response.data apa adanya (Blob), bukan CSV
    // yang disusun ulang dari angka di layar.
    expect(blob).toBeInstanceOf(Blob)
  })

  // 22
  it('saveCsvFile memicu unduhan lewat anchor + object URL dan membersihkannya', () => {
    const blob = new Blob(['a,b'], { type: 'text/csv' })
    const clickSpy = vi.fn()
    const appendSpy = vi.spyOn(document.body, 'appendChild')
    const removeSpy = vi.spyOn(document.body, 'removeChild')
    const createElementSpy = vi.spyOn(document, 'createElement')

    const anchor = document.createElement('a')
    anchor.click = clickSpy
    createElementSpy.mockReturnValueOnce(anchor)

    saveCsvFile(blob, 'laporan.csv')

    expect(createObjectURLMock).toHaveBeenCalledWith(blob)
    expect(anchor.getAttribute('href')).toBe('blob:mock-url')
    expect(anchor.getAttribute('download')).toBe('laporan.csv')
    expect(clickSpy).toHaveBeenCalledTimes(1)
    expect(appendSpy).toHaveBeenCalledWith(anchor)
    expect(removeSpy).toHaveBeenCalledWith(anchor)
    // Tidak ada object URL yang tertinggal.
    expect(revokeObjectURLMock).toHaveBeenCalledWith('blob:mock-url')
  })

  // 23 — BENTUK GALAT NYATA: objek DATAR, tanpa `response`.
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
    // membuangnya lewat normalizeError. Antarmuka ErrorLike pada view pun
    // sengaja tidak memuatnya.
    expect(caught).not.toHaveProperty('response')
    expect(Object.keys(caught as object).sort()).toEqual(['message', 'status'])

    // Penerjemahan 401/403 menjadi perilaku layar adalah tugas view.
    const unauthenticated = { message: 'Unauthenticated.', status: 401 }
    apiGetMock.mockRejectedValueOnce(unauthenticated)
    await expect(fetchSummary('per-1')).rejects.toBe(unauthenticated)
  })

  // 24
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
    await expect(fetchPeriods()).resolves.toEqual([PERIOD_CLARIFICATION])
    await expect(fetchSummary('per-1')).resolves.toHaveProperty('metrics')
  })

  // 25 — ATURAN BISNIS: tidak ada perhitungan kedua di ponsel.
  it('repo tidak memuat satu pun perhitungan KPI di sisi klien', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    // (a) Identitas objek: tidak ada agregasi, pengurutan, maupun
    //     penyaringan yang dapat terjadi tanpa memutus identitas ini.
    expect(summary.coverage).toBe(body.coverage)
    expect(summary.production).toBe(body.production)
    expect(summary.downtime).toBe(body.downtime)
    expect(summary.metrics).toBe(body.metrics)
    expect(summary.daily).toBe(body.daily)
    expect(summary.by_unit).toBe(body.by_unit)
    expect(summary.total).toBe(body.total)

    // (b) Angka-angka yang saling bertentangan TETAP bertentangan — repo
    //     tidak "memperbaiki" satu pun dari mereka. Inilah yang tidak dapat
    //     dibuktikan dengan payload yang konsisten.
    const dailyRates = DAILY_ROWS.map((row) => row.rate_avg).filter(
      (value): value is number => value !== null,
    )
    const dailyRateMean = dailyRates.reduce((sum, value) => sum + value, 0) / dailyRates.length
    const dailyProduction = DAILY_ROWS.map((row) => row.production_ton).filter(
      (value): value is number => value !== null,
    )
    const dailyDowntime = DAILY_ROWS.map((row) => row.downtime_mins).filter(
      (value): value is number => value !== null,
    )
    const unitProduction = BY_UNIT_ROWS.map((row) => row.production_ton).filter(
      (value): value is number => value !== null,
    )

    // TIDAK ADA penjumlahan laju per jam menjadi produksi.
    expect(summary.production.total_ton).toBe(128.5)
    expect(summary.production.total_ton).not.toBe(dailyRates.reduce((sum, value) => sum + value, 0))
    expect(summary.production.total_ton).not.toBe(
      dailyProduction.reduce((sum, value) => sum + value, 0),
    )
    expect(summary.production.total_ton).not.toBe(
      unitProduction.reduce((sum, value) => sum + value, 0),
    )

    // TIDAK ADA pengurangan downtime dari produksi.
    expect(summary.production.total_ton).not.toBe(128.5 - 240 / 60)
    expect(summary.downtime.total_mins).toBe(240)
    expect(summary.downtime.total_mins).not.toBe(
      dailyDowntime.reduce((sum, value) => sum + value, 0),
    )

    // Tidak ada perataan laju maupun suhu.
    expect(summary.metrics.pure_oil_production_rate_ton_hour.avg).toBe(9.33)
    expect(summary.metrics.pure_oil_production_rate_ton_hour.avg).not.toBe(dailyRateMean)
    expect(summary.metrics.pure_oil_production_rate_ton_hour.avg).not.toBe((4.1 + 14.8) / 2)
    expect(summary.metrics.pure_oil_production_rate_ton_hour.avg).not.toBe(Math.round(9.33))
    // Ekstrem berasal dari PEMBACAAN MENTAH: max (14,8) bahkan lebih kecil
    // daripada rata-rata harian tertinggi (42,1) — mustahil bila diturunkan
    // dari kolom harian.
    expect(summary.metrics.pure_oil_production_rate_ton_hour.max).toBe(14.8)
    expect(summary.metrics.pure_oil_production_rate_ton_hour.max).not.toBe(Math.max(...dailyRates))

    // Tidak ada pembulatan ulang coverage_percent maupun avg_per_day.
    expect(summary.coverage.coverage_percent).toBe(6.25)
    expect(summary.coverage.coverage_percent).not.toBe((100 * 9) / 288)
    expect(summary.downtime.avg_per_day_mins).toBe(20.5)
    expect(summary.downtime.avg_per_day_mins).not.toBe(240 / 11)

    // Tidak ada penurunan has_data dan tidak ada penjumlahan total.
    expect(summary.has_data).toBe(true)
    expect(summary.total.days_with_records).toBe(11)
    expect(summary.total.days_with_records).not.toBe(summary.daily.length)
    expect(summary.total.reading_rows).toBe(412)

    // (c) Setiap medan hasil === medan masukan, medan demi medan.
    expect(summary.production).toEqual(body.production)
    expect(summary.downtime).toEqual(body.downtime)
    expect(summary.metrics).toEqual(body.metrics)
    expect(summary.coverage).toEqual(body.coverage)
    expect(summary.daily).toEqual(body.daily)
    expect(summary.by_unit).toEqual(body.by_unit)
    expect(summary.total).toEqual(body.total)
  })

  // 26 — Pemeriksaan penutup lewat objek default modul.
  it('mengembalikan hasil lengkap ketika seluruh kondisi terpenuhi', async () => {
    apiGetMock.mockClear()

    const summary = await clarificationReportRepo.fetchSummary('per-1', {
      isAdmin: false,
      businessUnitId: null,
    })

    // Seluruh blok payload hadir dengan nilai identik.
    expect(summary.period?.id).toBe('per-1')
    expect(summary.business_unit?.name).toBe('PKS Sungai Bahar')
    expect(summary.has_data).toBe(true)
    expect(summary.coverage.coverage_percent).toBe(6.25)
    expect(summary.production.total_ton).toBe(128.5)
    expect(summary.downtime.total_mins).toBe(240)
    expect(Object.keys(summary.metrics)).toHaveLength(6)
    expect(summary.metrics.clarification_tank_temp_c.avg).toBe(93.4)
    expect(summary.daily).toHaveLength(3)
    expect(summary.by_unit).toHaveLength(2)
    expect(summary.total.reading_rows).toBe(412)

    // apiClient.get dipanggil TEPAT SATU KALI — tidak ada permintaan
    // tambahan yang dikirim repo.
    expect(apiGetMock).toHaveBeenCalledTimes(1)
    expect(apiGetMock).toHaveBeenCalledWith('/api/clarification-reports/summary', {
      params: { period_id: 'per-1' },
    })

    // Objek default mengekspos kelima fungsi yang dipakai view.
    expect(Object.keys(clarificationReportRepo).sort()).toEqual([
      'exportCsv',
      'fetchBusinessUnits',
      'fetchPeriods',
      'fetchSummary',
      'saveCsvFile',
    ])

    const units = await clarificationReportRepo.fetchBusinessUnits()
    const periods = await clarificationReportRepo.fetchPeriods({ isAdmin: true, businessUnitId: 'BU-A' })
    const blob = await clarificationReportRepo.exportCsv('per-1')

    expect(units).toEqual(BUSINESS_UNITS)
    expect(periods).toEqual([PERIOD_CLARIFICATION])
    expect(blob).toBeInstanceOf(Blob)

    // Admin: business_unit_id memang ikut — dan HANYA untuk Admin.
    expect(apiGetMock).toHaveBeenCalledWith('/api/clarification-reports/periods', {
      params: { business_unit_id: 'BU-A' },
    })
    // Ekspor tidak pernah menyertakan business_unit_id — period_id sudah
    // menentukan mill-nya di sisi server.
    expect(paramsOf('/api/clarification-reports/export')).toEqual({ period_id: 'per-1', format: 'csv' })
  })
})
