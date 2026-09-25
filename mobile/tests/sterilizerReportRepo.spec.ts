/**
 * sterilizerReportRepo.spec.ts — screen-135--laporan-sterilizer-mobile /
 * usecase-135--laporan-sterilizer-mobile "Lihat Laporan Periode Sterilizer
 * (Mobile)".
 *
 * Kembaran dari tests/cagesTrackReportRepo.spec.ts, untuk
 * src/services/sterilizerReportRepo.ts. Sebelum berkas ini ada, lapisan repo
 * Sterilizer sama sekali tidak terjaga test: LaporanSterilizerView.spec.ts
 * memang menjalankan repo-nya sungguhan, tetapi asersinya berbicara tentang
 * view model — bukan tentang query yang dikirim, bukan tentang toleransi
 * penamaan field durasi, dan bukan tentang ketiadaan aritmetika di klien.
 *
 * YANG DI-MOCK HANYA apiClient. Repo-nya sendiri berjalan sungguhan —
 * itulah satu-satunya cara asersi seperti "params yang dikirim tidak memuat
 * business_unit_id" dan "tidak ada perhitungan kedua di klien" membuktikan
 * sesuatu: yang menyusun query adalah scopeParams() di dalam repo, dan yang
 * memetakan respons adalah unwrap() + normalizeKpi() di dalam repo.
 * Men-stub repo akan memindahkan seluruh asersi ke mock buatan test sendiri.
 *
 * ANGKA FIXTURE SENGAJA TIDAK KONSISTEN SECARA ARITMETIKA.
 * kpi.total_cycles (312) tidak sama dengan jumlah daily (24) maupun jumlah
 * by_unit (299); total.cycles (299) bertentangan dengan kpi; dan
 * total.avg_duration (90.1) berbeda dari kpi.avg_duration (96.4), yang
 * sendirinya bukan rata-rata min/max. Repo yang benar meneruskan semuanya
 * apa adanya; repo yang diam-diam menghitung ulang gagal di sini. JANGAN
 * "merapikan" angka-angka ini.
 *
 * ------------------------------------------------------------------
 * DEVIASI TERCATAT — galat lewat `response.status`
 * ------------------------------------------------------------------
 * unit_test_cases 17-19 pada tech spec screen-135 menggambarkan galat lewat
 * `response.status` ("apiClient.get menolak dengan response.status=401").
 * Bentuk itu TIDAK PERNAH ADA di produksi: interceptor pada
 * src/services/apiClient.ts (normalizeError, ~baris 52-75) selalu menolak
 * dengan objek DATAR `{ message, errors?, status? }` — properti `response`
 * sudah dibuang di sana. Test di bawah karena itu ditulis terhadap bentuk
 * NYATA (datar). Selain itu, ketiga butir tersebut berbicara tentang
 * keputusan VIEW (mengarahkan ke rute 'login', menyusun pesan validasi,
 * menyembunyikan pemilih Mill) — bukan tanggung jawab repo, dan memang
 * sudah diuji di LaporanSterilizerView.spec.ts. Yang menjadi urusan berkas
 * ini hanyalah prasyaratnya: galat diteruskan NAIK APA ADANYA, objeknya
 * identik (toBe), tidak dibungkus ulang menjadi kelas galat sendiri, dan
 * tidak ditelan menjadi nilai bawaan yang menyamar sebagai keberhasilan.
 */
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'

/* ------------------------------------------------------------------ */
/* Mock modul — satu-satunya lapisan yang distub                       */
/* ------------------------------------------------------------------ */

const { apiGetMock } = vi.hoisted(() => ({ apiGetMock: vi.fn() }))

vi.mock('@/services/apiClient', () => ({
  default: { get: apiGetMock },
}))

import sterilizerReportRepo, {
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  exportCsv,
  saveCsvFile,
} from '@/services/sterilizerReportRepo'

/* ------------------------------------------------------------------ */
/* Fixture — BENTUK RESPONS NYATA                                      */
/* ------------------------------------------------------------------ */

const PERIOD_STERILIZER = {
  id: 'per-1',
  name: 'Periode Maret 2026',
  start_date: '2026-03-01',
  end_date: '2026-03-14',
  status: 'open',
  station_type: 'sterilizer',
  station_type_label: 'Sterilizer',
}

const PERIOD_ALL_TYPES = {
  id: 'per-2',
  name: 'Periode Semua Stasiun Maret 2026',
  start_date: '2026-03-01',
  end_date: '2026-03-31',
  status: 'open',
  station_type: null,
  station_type_label: 'Semua Jenis Stasiun',
}

const BUSINESS_UNITS = [
  { id: 'BU-A', name: 'PKS Sungai Bahar' },
  { id: 'BU-B', name: 'PKS Muara Bulian' },
]

/**
 * Blok kpi dalam penamaan yang BENAR-BENAR dikirim
 * SterilizerReportService::kpiOf() — durasi memakai akhiran `_minutes`,
 * berbeda dari blok daily/by_unit/total pada respons yang sama.
 */
function makeKpiMinutes(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    total_cycles: 312,
    total_cages: 1870,
    avg_duration_minutes: 96.4,
    min_duration_minutes: 61,
    max_duration_minutes: 154,
    cycles_without_duration: 7,
    triple_peak_compliance_percent: 82.5,
    ...overrides,
  }
}

const DAILY_ROWS = [
  {
    date: '2026-03-01',
    cycles: 24,
    cages: 140,
    avg_duration: 98.2,
    min_duration: 70,
    max_duration: 141,
    triple_peak_complete: 20,
    cycles_without_duration: 1,
  },
  {
    // Hari tanpa siklus: seluruh durasi null, BUKAN 0.
    date: '2026-03-14',
    cycles: 0,
    cages: 0,
    avg_duration: null,
    min_duration: null,
    max_duration: null,
    triple_peak_complete: 0,
    cycles_without_duration: 0,
  },
]

const BY_UNIT_ROWS = [
  { sterilizer_no: 'ST-01', cycles: 150, cages: 900, avg_duration: 94.1, triple_peak_complete: 121 },
  // Unit yang seluruh siklusnya tanpa jam pintu: rata-rata tidak dapat dihitung.
  { sterilizer_no: 'ST-02', cycles: 149, cages: 899, avg_duration: null, triple_peak_complete: 119 },
]

const OUTLIER_ITEMS = [
  {
    date: '2026-03-03',
    sterilizer_no: 'ST-01',
    duration_minutes: 201,
    number_of_cages: 10,
    cages_status: 'full',
    close_door_time: '06:10',
    open_door_time: '09:31',
  },
  {
    date: '2026-03-09',
    sterilizer_no: 'ST-02',
    duration_minutes: 18,
    number_of_cages: null,
    cages_status: null,
    close_door_time: null,
    open_door_time: null,
  },
]

/**
 * Respons /summary yang dikirim SterilizerReportController::summary() —
 * TANPA pembungkus `data` (`response()->json($this->service->summary(...))`).
 */
function makeSummaryBody(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    period: {
      id: 'per-1',
      name: 'Periode Maret 2026',
      start_date: '2026-03-01',
      end_date: '2026-03-14',
      status: 'open',
      business_unit_name: 'PKS Sungai Bahar',
    },
    kpi: makeKpiMinutes(),
    daily: DAILY_ROWS,
    by_unit: BY_UNIT_ROWS,
    outliers: {
      method: 'iqr',
      q1: 80,
      q3: 120,
      iqr: 40,
      // SENGAJA bukan q1 - 1.5*iqr (= 20) dan q3 + 1.5*iqr (= 180): server
      // memakai ambangnya sendiri, dan repo tidak boleh menurunkannya ulang.
      lower_bound: 27,
      upper_bound: 173,
      sample_size: 305,
      min_sample_size: 8,
      insufficient_data: false,
      items: OUTLIER_ITEMS,
    },
    // Sengaja bertentangan dengan kpi dan dengan jumlah baris daily/by_unit.
    total: {
      cycles: 299,
      cages: 1799,
      avg_duration: 90.1,
      min_duration: 61,
      max_duration: 154,
      triple_peak_complete: 240,
      cycles_without_duration: 7,
    },
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
      return Promise.resolve({ data: { data: [PERIOD_STERILIZER] } })
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
/* Lapisan repo screen-135 — 21 kasus                                  */
/* ================================================================== */

describe('sterilizerReportRepo — lapisan repo (screen-135)', () => {
  // 1
  it('fetchPeriods memanggil tepat GET /api/sterilizer-reports/periods dan tidak menyaring station_type di klien', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [PERIOD_STERILIZER, PERIOD_ALL_TYPES] } })

    const periods = await fetchPeriods()

    expect(callsTo('/api/sterilizer-reports/periods')).toHaveLength(1)
    expect(apiGetMock).toHaveBeenCalledTimes(1)
    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/periods', { params: {} })

    // Seluruh entri diteruskan apa adanya, dalam urutan server. Penyaringan
    // cakupan Sterilizer sepenuhnya milik server — termasuk periode
    // ber-station_type null, yang TIDAK boleh dibuang di sini.
    expect(periods).toEqual([PERIOD_STERILIZER, PERIOD_ALL_TYPES])
    expect(periods).toHaveLength(2)
    expect(periods[1].station_type).toBeNull()
  })

  // 2 — GAGAL TERTUTUP: perangkat non-Admin tidak pernah mengirim business_unit_id.
  it('scopeParams tidak mengirim business_unit_id untuk peran yang terikat mill, bahkan ketika pemanggil menyertakannya', async () => {
    await fetchPeriods({ isAdmin: false, businessUnitId: 'BU-A' })

    const params = paramsOf('/api/sterilizer-reports/periods')

    // Bukan null, bukan string kosong — kuncinya ABSEN.
    expect(params).toEqual({})
    expect(params).not.toHaveProperty('business_unit_id')
    expect(Object.keys(params)).toEqual([])
    expect(JSON.stringify(params)).not.toContain('BU-A')

    // Berlaku juga pada /summary, yang menggabung scopeParams dengan
    // period_id: yang tersisa HANYA period_id.
    apiGetMock.mockClear()
    await fetchSummary('per-1', { isAdmin: false, businessUnitId: 'BU-A' })
    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/summary', {
      params: { period_id: 'per-1' },
    })

    // Dan ketika pemanggil tidak menyatakan peran sama sekali (scope
    // undefined, atau isAdmin tak disebut) hasilnya sama — bawaannya
    // tertutup, bukan terbuka.
    apiGetMock.mockClear()
    await fetchPeriods()
    await fetchPeriods({ businessUnitId: 'BU-A' })
    expect(paramsOf('/api/sterilizer-reports/periods')).toEqual({})
    expect(callsTo('/api/sterilizer-reports/periods')).toHaveLength(2)
    expect(JSON.stringify(apiGetMock.mock.calls)).not.toContain('business_unit_id')
  })

  // 3
  it('scopeParams mengirim business_unit_id hanya ketika Admin sudah memilih mill', async () => {
    await fetchPeriods({ isAdmin: true, businessUnitId: 'BU-B' })

    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/periods', {
      params: { business_unit_id: 'BU-B' },
    })

    apiGetMock.mockClear()
    await fetchSummary('per-1', { isAdmin: true, businessUnitId: 'BU-B' })

    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/summary', {
      params: { period_id: 'per-1', business_unit_id: 'BU-B' },
    })
  })

  // 4
  it('scopeParams tidak mengirim business_unit_id ketika Admin belum memilih mill', async () => {
    await fetchPeriods({ isAdmin: true, businessUnitId: null })

    const params = paramsOf('/api/sterilizer-reports/periods')

    // Repo tidak mengarang nilai mill dan tidak melempar galatnya sendiri:
    // server yang menjawab 422 VALIDATION_ERROR, satu sumber kebenaran.
    expect(params).toEqual({})
    expect(params).not.toHaveProperty('business_unit_id')

    // Admin tanpa businessUnitId sama sekali (undefined), dan Admin dengan
    // string kosong, diperlakukan sama.
    apiGetMock.mockClear()
    await fetchPeriods({ isAdmin: true })
    await fetchPeriods({ isAdmin: true, businessUnitId: '' })
    expect(callsTo('/api/sterilizer-reports/periods')).toHaveLength(2)
    expect(apiGetMock.mock.calls.every(([, config]) => {
      return Object.keys((config as { params?: Record<string, unknown> })?.params ?? {}).length === 0
    })).toBe(true)
  })

  // 5
  it('fetchSummary memanggil GET /api/sterilizer-reports/summary dengan period_id', async () => {
    await fetchSummary('per-1')

    expect(callsTo('/api/sterilizer-reports/summary')).toHaveLength(1)
    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/summary', {
      params: { period_id: 'per-1' },
    })
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

    expect(wrapped).toEqual(unwrapped)
    expect(wrapped.kpi.total_cycles).toBe(312)
    expect(wrapped.by_unit).toHaveLength(2)
    expect(wrapped.outliers.items).toHaveLength(2)
  })

  // 7 — toleransi penamaan: bentuk `_minutes` (yang dikirim kpiOf()).
  it('normalizeKpi membaca penamaan avg/min/max_duration_minutes dari SterilizerReportService', async () => {
    apiGetMock.mockResolvedValueOnce({ data: makeSummaryBody({ kpi: makeKpiMinutes() }) })

    const summary = await fetchSummary('per-1')

    // Hanya NAMA field yang diseragamkan; nilainya diteruskan apa adanya.
    expect(summary.kpi.avg_duration).toBe(96.4)
    expect(summary.kpi.min_duration).toBe(61)
    expect(summary.kpi.max_duration).toBe(154)
    expect(summary.kpi.total_cycles).toBe(312)
    expect(summary.kpi.total_cages).toBe(1870)
    expect(summary.kpi.cycles_without_duration).toBe(7)
    expect(summary.kpi.triple_peak_compliance_percent).toBe(82.5)

    // Nama lama tidak bocor ke view model.
    expect(summary.kpi).not.toHaveProperty('avg_duration_minutes')
    expect(summary.kpi).not.toHaveProperty('min_duration_minutes')
    expect(summary.kpi).not.toHaveProperty('max_duration_minutes')
  })

  // 8 — toleransi penamaan: bentuk pendek (sebagaimana daily/by_unit/total).
  it('normalizeKpi juga membaca penamaan pendek avg/min/max_duration', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({
        kpi: {
          total_cycles: 312,
          total_cages: 1870,
          avg_duration: 96.4,
          min_duration: 61,
          max_duration: 154,
          cycles_without_duration: 7,
          triple_peak_compliance_percent: 82.5,
        },
      }),
    })

    const summary = await fetchSummary('per-1')

    // Kedua konvensi penamaan menghasilkan view model yang identik — itulah
    // seluruh maksud toleransi ini.
    expect(summary.kpi).toEqual({
      total_cycles: 312,
      total_cages: 1870,
      avg_duration: 96.4,
      min_duration: 61,
      max_duration: 154,
      cycles_without_duration: 7,
      triple_peak_compliance_percent: 82.5,
    })
  })

  // 9 — prioritas antar kedua penamaan, termasuk saat yang satu null.
  it('normalizeKpi mendahulukan bentuk _minutes dan jatuh ke bentuk pendek hanya ketika _minutes tidak ada', async () => {
    // Keduanya hadir: _minutes menang.
    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({
        kpi: makeKpiMinutes({ avg_duration: 11.1, min_duration: 22, max_duration: 33 }),
      }),
    })
    const both = await fetchSummary('per-1')
    expect(both.kpi.avg_duration).toBe(96.4)
    expect(both.kpi.min_duration).toBe(61)
    expect(both.kpi.max_duration).toBe(154)

    // _minutes bernilai null: bentuk pendek dipakai sebagai cadangan.
    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({
        kpi: makeKpiMinutes({
          avg_duration_minutes: null,
          min_duration_minutes: null,
          max_duration_minutes: null,
          avg_duration: 88.8,
          min_duration: 55,
          max_duration: 133,
        }),
      }),
    })
    const fallback = await fetchSummary('per-1')
    expect(fallback.kpi.avg_duration).toBe(88.8)
    expect(fallback.kpi.min_duration).toBe(55)
    expect(fallback.kpi.max_duration).toBe(133)
  })

  // 10 — NULL BUKAN NOL.
  it('fetchSummary mempertahankan avg/min/max_duration null pada KPI, bukan mengoersinya menjadi 0', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({
        kpi: makeKpiMinutes({
          avg_duration_minutes: null,
          min_duration_minutes: null,
          max_duration_minutes: null,
          cycles_without_duration: 12,
        }),
      }),
    })

    const summary = await fetchSummary('per-1')

    // null = "tidak dapat dihitung" (jam pintu belum terisi);
    // 0 = "siklusnya memang nol menit" — fakta yang berbeda, dan mustahil.
    expect(summary.kpi.avg_duration).toBeNull()
    expect(summary.kpi.min_duration).toBeNull()
    expect(summary.kpi.max_duration).toBeNull()
    expect(summary.kpi.avg_duration).not.toBe(0)
    expect(summary.kpi.min_duration).not.toBe(0)
    expect(summary.kpi.max_duration).not.toBe(0)

    // Jumlah siklus tanpa durasi tetap terbaca sebagai angka tersendiri —
    // bukan dilebur ke dalam rata-rata.
    expect(summary.kpi.cycles_without_duration).toBe(12)

    // Blok yang memang bernilai nol tetap nol; null dan 0 tidak saling
    // menular ke satu arah pun.
    expect(summary.kpi.total_cycles).toBe(312)
  })

  // 11 — null pada blok outliers, yang dipetakan field demi field.
  it('fetchSummary mempertahankan ambang pencilan null ketika data belum mencukupi', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({
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
    })

    const summary = await fetchSummary('per-1')

    // Ambang null berarti "belum dihitung", bukan "batas bawahnya 0" —
    // batas 0 akan membuat setiap siklus tampak berada di dalam rentang.
    expect(summary.outliers.q1).toBeNull()
    expect(summary.outliers.q3).toBeNull()
    expect(summary.outliers.iqr).toBeNull()
    expect(summary.outliers.lower_bound).toBeNull()
    expect(summary.outliers.upper_bound).toBeNull()
    expect(summary.outliers.lower_bound).not.toBe(0)
    expect(summary.outliers.upper_bound).not.toBe(0)
    expect(summary.outliers.insufficient_data).toBe(true)
    expect(summary.outliers.sample_size).toBe(3)
    expect(summary.outliers.min_sample_size).toBe(8)
    expect(summary.outliers.items).toEqual([])
  })

  // 12
  it('fetchSummary tidak mengurutkan ulang maupun menyaring daily, termasuk baris bernilai nol', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    expect(summary.daily).toHaveLength(2)
    // Urutan server, termasuk baris ber-cycles 0 yang justru harus terbaca
    // (hari itu tetap bagian dari periode).
    expect(summary.daily.map((row) => row.date)).toEqual(['2026-03-01', '2026-03-14'])
    expect(summary.daily[1].cycles).toBe(0)
    expect(summary.daily[1].avg_duration).toBeNull()
    expect(summary.daily[1].avg_duration).not.toBe(0)
    expect(summary.daily).toBe(body.daily)
    // Tidak ada tanggal karangan di antara keduanya.
    expect(summary.daily.map((row) => row.date)).not.toContain('2026-03-02')
  })

  // 13
  it('fetchSummary meneruskan by_unit apa adanya, termasuk unit tanpa rata-rata durasi', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    expect(summary.by_unit).toBe(body.by_unit)
    expect(summary.by_unit.map((row) => row.sterilizer_no)).toEqual(['ST-01', 'ST-02'])
    expect(summary.by_unit[1].avg_duration).toBeNull()
    expect(summary.by_unit[1].avg_duration).not.toBe(0)
    // Repo tidak menjumlah antar unit dan tidak menambahkan baris "Total".
    expect(summary.by_unit).toHaveLength(2)
  })

  // 14
  it('fetchSummary meneruskan blok total apa adanya meski bertentangan dengan KPI', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    expect(summary.total).toBe(body.total)
    expect(summary.total.cycles).toBe(299)
    expect(summary.total.avg_duration).toBe(90.1)
    // Bertentangan dengan kpi — dan tetap dibiarkan bertentangan. Repo
    // bukan tempat merekonsiliasi dua angka server.
    expect(summary.total.cycles).not.toBe(summary.kpi.total_cycles)
    expect(summary.total.avg_duration).not.toBe(summary.kpi.avg_duration)
  })

  // 15
  it('fetchSummary meneruskan payload periode kosong tanpa mengarang nilai bentukan', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: {
        period: null,
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
        outliers: {
          method: 'iqr',
          q1: null,
          q3: null,
          iqr: null,
          lower_bound: null,
          upper_bound: null,
          sample_size: 0,
          min_sample_size: 8,
          insufficient_data: true,
          items: [],
        },
        total: {
          cycles: 0,
          cages: 0,
          avg_duration: null,
          min_duration: null,
          max_duration: null,
          triple_peak_complete: 0,
          cycles_without_duration: 0,
        },
      },
    })

    const summary = await fetchSummary('per-1')

    expect(summary.period).toBeNull()
    expect(summary.kpi.total_cycles).toBe(0)
    expect(summary.kpi.avg_duration).toBeNull()
    expect(summary.daily).toEqual([])
    expect(summary.by_unit).toEqual([])
    expect(summary.outliers.insufficient_data).toBe(true)
    expect(summary.total.cycles).toBe(0)
    expect(summary.total.avg_duration).toBeNull()

    // Respons yang blok-bloknya tidak ada sama sekali pun tidak melempar —
    // layar yang memutuskan menampilkan keterangan belum ada data.
    apiGetMock.mockResolvedValueOnce({ data: {} })
    const bare = await fetchSummary('per-1')
    expect(bare.period).toBeNull()
    expect(bare.daily).toEqual([])
    expect(bare.by_unit).toEqual([])
    expect(bare.kpi.avg_duration).toBeNull()
    expect(bare.kpi.total_cycles).toBe(0)
    expect(bare.outliers.insufficient_data).toBe(true)
    expect(bare.outliers.items).toEqual([])
    expect(bare.total.cycles).toBe(0)
  })

  // 16 — ATURAN BISNIS: tidak ada perhitungan kedua di ponsel.
  it('repo tidak memuat satu pun perhitungan KPI di sisi klien', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    // Blok yang tidak disentuh sama sekali adalah OBJEK/ARRAY YANG SAMA
    // dengan respons: tidak ada agregasi (jumlah, rata-rata, min, maks),
    // tidak ada pengurutan, dan tidak ada penyaringan yang dapat terjadi
    // tanpa memutus identitas ini.
    expect(summary.period).toBe(body.period)
    expect(summary.daily).toBe(body.daily)
    expect(summary.by_unit).toBe(body.by_unit)
    expect(summary.total).toBe(body.total)
    // kpi dan outliers memang objek baru — keduanya dipetakan per field
    // (penyeragaman NAMA pada kpi, pembacaan nullable pada outliers).
    // items di dalamnya tetap array yang sama.
    expect(summary.outliers.items).toBe((body.outliers as { items: unknown }).items)

    // Angka-angka yang saling bertentangan tetap bertentangan: repo tidak
    // "memperbaiki" satu pun dari mereka.
    const dailyCycles = DAILY_ROWS.reduce((sum, row) => sum + row.cycles, 0)
    const unitCycles = BY_UNIT_ROWS.reduce((sum, row) => sum + row.cycles, 0)
    const dailyCages = DAILY_ROWS.reduce((sum, row) => sum + row.cages, 0)

    expect(summary.kpi.total_cycles).toBe(312)
    expect(summary.kpi.total_cycles).not.toBe(dailyCycles)
    expect(summary.kpi.total_cycles).not.toBe(unitCycles)
    expect(summary.kpi.total_cages).toBe(1870)
    expect(summary.kpi.total_cages).not.toBe(dailyCages)

    // Rata-rata durasi bukan turunan dari min/max, bukan pula rata-rata
    // baris daily atau by_unit — dan tidak dibulatkan ulang.
    expect(summary.kpi.avg_duration).toBe(96.4)
    expect(summary.kpi.avg_duration).not.toBe((61 + 154) / 2)
    expect(summary.kpi.avg_duration).not.toBe(Math.round(96.4))
    expect(summary.kpi.avg_duration).not.toBe(summary.total.avg_duration)

    // Persentase kepatuhan tiga puncak diterima apa adanya, tidak dihitung
    // ulang dari triple_peak_complete / total_cycles (= 76.9…).
    expect(summary.kpi.triple_peak_compliance_percent).toBe(82.5)
    expect(summary.kpi.triple_peak_compliance_percent).not.toBe((240 / 312) * 100)

    // Ambang IQR diterima apa adanya, tidak diturunkan dari q1/q3.
    expect(summary.outliers.lower_bound).toBe(27)
    expect(summary.outliers.upper_bound).toBe(173)
    expect(summary.outliers.lower_bound).not.toBe(80 - 1.5 * 40)
    expect(summary.outliers.upper_bound).not.toBe(120 + 1.5 * 40)
    // Jumlah pencilan pun tidak dihitung ulang dari items terhadap ambang.
    expect(summary.outliers.sample_size).toBe(305)

    // Tidak ada medan turunan yang ditambahkan repo.
    expect(Object.keys(summary).sort()).toEqual(['by_unit', 'daily', 'kpi', 'outliers', 'period', 'total'])
    expect(Object.keys(summary.kpi).sort()).toEqual([
      'avg_duration',
      'cycles_without_duration',
      'max_duration',
      'min_duration',
      'total_cages',
      'total_cycles',
      'triple_peak_compliance_percent',
    ])
  })

  // 17
  it('fetchBusinessUnits memanggil endpoint opsi mill dan membaca pembungkus data', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [{ id: 'BU-A', name: 'PKS Sungai Bahar' }] } })

    const units = await fetchBusinessUnits()

    // Tanpa argumen config sama sekali — endpoint ini tidak berparameter.
    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/business-units/options')
    expect(units).toEqual([{ id: 'BU-A', name: 'PKS Sungai Bahar' }])

    // Respons tanpa isi tetap menghasilkan daftar kosong, bukan lemparan.
    apiGetMock.mockResolvedValueOnce({ data: {} })
    await expect(fetchBusinessUnits()).resolves.toEqual([])

    apiGetMock.mockResolvedValueOnce({ data: undefined })
    await expect(fetchBusinessUnits()).resolves.toEqual([])
  })

  // 18
  it('fetchPeriods membaca pembungkus data dan memetakan daftar kosong menjadi array kosong', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [] } })
    await expect(fetchPeriods()).resolves.toEqual([])

    // Daftar kosong adalah jawaban yang SAH (mill belum punya periode),
    // bukan galat — repo tidak boleh melempar di sini.
    apiGetMock.mockResolvedValueOnce({ data: {} })
    await expect(fetchPeriods()).resolves.toEqual([])

    apiGetMock.mockResolvedValueOnce({ data: undefined })
    await expect(fetchPeriods()).resolves.toEqual([])

    // Berbeda dari /summary, kedua endpoint daftar ini membaca
    // `response.data?.data` LANGSUNG — pembungkusnya wajib ada, dan bentuk
    // tanpa pembungkus memang bukan bentuk yang dikirim controller.
    apiGetMock.mockResolvedValueOnce({ data: [PERIOD_STERILIZER] })
    await expect(fetchPeriods()).resolves.toEqual([])
  })

  // 19
  it('exportCsv meminta responseType blob dan format csv', async () => {
    const blob = new Blob(['a,b\n1,2\n'], { type: 'text/csv' })
    apiGetMock.mockResolvedValueOnce({ data: blob })

    const result = await exportCsv('per-1')

    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/export', {
      params: { period_id: 'per-1', format: 'csv' },
      responseType: 'blob',
    })
    // Isi CSV dibentuk SERVER; repo hanya meneruskan blob-nya.
    expect(result).toBe(blob)
  })

  // 20
  it('saveCsvFile memicu unduhan lewat anchor + object URL dan membersihkannya', () => {
    const clickSpy = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
    const appendSpy = vi.spyOn(document.body, 'appendChild')
    const removeSpy = vi.spyOn(document.body, 'removeChild')

    const blob = new Blob(['csv'], { type: 'text/csv' })
    saveCsvFile(blob, 'laporan-sterilizer.csv')

    expect(createObjectURLMock).toHaveBeenCalledTimes(1)
    expect(createObjectURLMock).toHaveBeenCalledWith(blob)

    const anchor = appendSpy.mock.calls[0][0] as HTMLAnchorElement
    expect(anchor.tagName).toBe('A')
    expect(anchor.download).toBe('laporan-sterilizer.csv')
    expect(anchor.href).toContain('blob:mock-url')

    expect(clickSpy).toHaveBeenCalledTimes(1)
    expect(removeSpy).toHaveBeenCalledWith(anchor)
    // Object URL dilepas kembali — tidak ada kebocoran.
    expect(revokeObjectURLMock).toHaveBeenCalledWith('blob:mock-url')
    // Anchor tidak tertinggal di dokumen.
    expect(document.body.contains(anchor)).toBe(false)
  })

  /*
   * 21 — DITULIS TERHADAP PERILAKU NYATA, bukan teks spec. Lihat catatan
   * "DEVIASI TERCATAT" pada docblock berkas: galat yang sampai ke repo
   * selalu berbentuk DATAR { message, errors?, status? }.
   */
  it('galat dari apiClient diteruskan naik apa adanya oleh seluruh fungsi repo', async () => {
    const forbidden = { message: 'Anda tidak memiliki akses untuk aksi ini.', status: 403 }
    apiGetMock.mockRejectedValueOnce(forbidden)

    const caught = await fetchSummary('per-1').then(
      () => null,
      (error: unknown) => error,
    )

    // Objek yang SAMA, bukan dibungkus ulang menjadi kelas galat sendiri.
    expect(caught).toBe(forbidden)
    expect(caught).not.toBeInstanceOf(Error)
    expect((caught as { status?: number }).status).toBe(403)
    // Tidak ada properti `response` di produksi — dicatat di sini supaya
    // deviasi ini terbaca sebagai fakta, bukan kelalaian.
    expect(caught).not.toHaveProperty('response')

    // Kegagalan transport tiba TANPA `status` sama sekali (cabang
    // error.request pada normalizeError), dan justru ketiadaan status itulah
    // yang membedakan "jaringan putus" dari "server menjawab 401/403/422" —
    // pembeda yang dipakai view untuk memilih antara Coba Lagi dan Login.
    // Keputusan itu milik view; repo hanya wajib meneruskannya utuh.
    const transport = { message: 'Tidak dapat terhubung ke server. Data akan disimpan secara lokal.' }

    apiGetMock.mockRejectedValueOnce(transport)
    await expect(fetchSummary('per-1')).rejects.toBe(transport)
    apiGetMock.mockRejectedValueOnce(transport)
    await expect(fetchPeriods()).rejects.toBe(transport)
    apiGetMock.mockRejectedValueOnce(transport)
    await expect(fetchBusinessUnits()).rejects.toBe(transport)
    apiGetMock.mockRejectedValueOnce(transport)
    await expect(exportCsv('per-1')).rejects.toBe(transport)

    // Mock sudah kembali normal di sini — jadi asersi di atas benar-benar
    // menguji jalur galat, bukan repo yang kebetulan selalu melempar.
    await expect(fetchPeriods()).resolves.toEqual([PERIOD_STERILIZER])

    // Dan hasil sukses tetap berjalan normal setelahnya.
    await expect(fetchSummary('per-1')).resolves.toHaveProperty('kpi')
  })

  // Pemeriksaan penutup: objek default modul mengekspos kelima fungsi yang
  // dipakai view, dan seluruhnya bekerja dari ujung ke ujung.
  it('mengembalikan hasil lengkap lewat objek default ketika seluruh kondisi terpenuhi', async () => {
    const units = await sterilizerReportRepo.fetchBusinessUnits()
    const periods = await sterilizerReportRepo.fetchPeriods({ isAdmin: true, businessUnitId: 'BU-A' })
    const summary = await sterilizerReportRepo.fetchSummary('per-1', { isAdmin: true, businessUnitId: 'BU-A' })
    const blob = await sterilizerReportRepo.exportCsv('per-1')

    expect(units).toEqual(BUSINESS_UNITS)
    expect(periods).toEqual([PERIOD_STERILIZER])

    expect(summary.period?.id).toBe('per-1')
    expect(summary.kpi.total_cycles).toBe(312)
    expect(summary.kpi.avg_duration).toBe(96.4)
    expect(summary.daily).toHaveLength(2)
    expect(summary.by_unit).toHaveLength(2)
    expect(summary.outliers.items).toHaveLength(2)
    expect(summary.total.cycles).toBe(299)
    expect(blob).toBeInstanceOf(Blob)
    expect(typeof sterilizerReportRepo.saveCsvFile).toBe('function')

    // Admin: business_unit_id memang ikut — dan HANYA untuk Admin.
    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/periods', {
      params: { business_unit_id: 'BU-A' },
    })
    expect(apiGetMock).toHaveBeenCalledWith('/api/sterilizer-reports/summary', {
      params: { period_id: 'per-1', business_unit_id: 'BU-A' },
    })
    // Ekspor tidak pernah menyertakan business_unit_id — period_id sudah
    // menentukan mill-nya di sisi server.
    expect(paramsOf('/api/sterilizer-reports/export')).toEqual({ period_id: 'per-1', format: 'csv' })
  })
})
