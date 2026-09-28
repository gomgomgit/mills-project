/**
 * storageTankReportRepo.spec.ts — screen-139--laporan-storage-tank-mobile /
 * usecase-139--laporan-storage-tank-mobile "Lihat Laporan Periode Storage
 * Tank (Mobile)".
 *
 * Satu test per unit_test_cases pada tech spec screen-139. Kembaran dari
 * tests/sterilizerReportRepo.spec.ts (screen-135),
 * tests/cagesTrackReportRepo.spec.ts (screen-136),
 * tests/boilerRoomReportRepo.spec.ts (screen-137), dan
 * tests/clarificationReportRepo.spec.ts (screen-138), untuk
 * src/services/storageTankReportRepo.ts.
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
 *   - stock.movement_mt (280,5) ≠ closing_mt − opening_mt (1500,0 − 1200,0 =
 *     300,0) — godaan terbesar di layar ini, dan fixture inilah yang
 *     menangkapnya;
 *   - stock.movement_mt (280,5) juga ≠ jumlah by_tank[].movement_mt (100,5 +
 *     160,0 = 260,5), jadi ia tidak dapat diturunkan dari tabel per tangki
 *     sekalipun;
 *   - by_tank[0].movement_mt (100,5) ≠ closing_mt − opening_mt tangki itu
 *     (960,0 − 800,0 = 160,0), dan by_tank[1].movement_mt (160,0) ≠
 *     540,0 − 500,0 (40,0) — jadi tidak ada satu tangki pun yang selisihnya
 *     kebetulan cocok;
 *   - stock.tanks_without_movement (1) ≠ jumlah baris by_tank yang
 *     movement_computable-nya false (2) — cacahnya milik server, bukan hasil
 *     penyaringan di klien;
 *   - metrics.ffa_percent.max (4,4) LEBIH KECIL daripada daily[0].ffa_avg
 *     (5,8) — mustahil bila ekstremnya diturunkan dari kolom harian, dan
 *     memang tidak: ekstrem berasal dari PEMBACAAN MENTAH per slot waktu
 *     (StorageTankReportService::metricsOf);
 *   - metrics.average_temperature_c.avg (52,0) ≠ rata-rata ketiga suhu posisi
 *     (50,0 + 55,0 + 61,2) / 3 = 55,4 — suhu rata-rata DIBACA dari kolom
 *     Operator, tidak pernah diturunkan;
 *   - metrics.calculated_weight_mt.avg (660,0) dan
 *     metrics.calculated_volume_m3.avg (730,0) keduanya tidak berhubungan
 *     dengan angka stock mana pun — stok berasal dari blok `stock`, bukan
 *     dari metrik;
 *   - metrics.ffa_percent.reading_count (240) ≠ metrics.dobi_index
 *     .reading_count (0) ≠ metrics.average_temperature_c.reading_count (12)
 *     ≠ total.reading_rows (412) ≠ jumlah by_tank[].reading_count (277);
 *   - coverage.expected_slots (240) ≠ tank_count × days_in_period ×
 *     slots_per_tank_per_day (4 × 30 × 4 = 480), sehingga coverage_percent
 *     1,25 mustahil diturunkan ulang dari medan-medan di sebelahnya
 *     (100 × 3 / 480 = 0,625);
 *   - coverage.filled_slots (3) ≠ jumlah daily.filled_slots (18);
 *   - stock.opening_mt (1200,0) ≠ nilai daily.stock_total_mt terendah
 *     (1240,0) dan stock.closing_mt (1500,0) ≠ yang tertinggi (1470,0) —
 *     stok periode diambil dari pembacaan PER TANGKI, bukan dari kolom stok
 *     harian, dan tidak pula dari nilai ekstremnya;
 *   - total.days_with_records (11) ≠ panjang daily (4).
 *
 * JANGAN "merapikan" angka-angka ini. Merapikannya akan melucuti separuh
 * berkas ini menjadi test yang selalu hijau.
 *
 * BENTUK GALAT — DATAR, TANPA `response`. Interceptor pada
 * src/services/apiClient.ts (normalizeError) selalu menolak dengan objek
 * DATAR { message, errors?, status? } dan sudah MEMBUANG `response`. Seluruh
 * mock penolakan di bawah memakai bentuk nyata itu; memalsukan
 * `{ response: { status } }` akan menguji bentuk yang tidak pernah terjadi
 * di produksi. Penerjemahan 401/403 menjadi perilaku layar adalah tugas view
 * (diuji di LaporanStorageTankView.spec.ts) — yang wajib dibuktikan di sini
 * hanyalah prasyaratnya: galat naik APA ADANYA, objeknya identik.
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

import storageTankReportRepo, {
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  exportCsv,
  saveCsvFile,
} from '@/services/storageTankReportRepo'

/* ------------------------------------------------------------------ */
/* Fixture — BENTUK RESPONS NYATA                                      */
/* ------------------------------------------------------------------ */

const PERIOD_STORAGE_TANK = {
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

const BUSINESS_UNITS = [
  { id: 'BU-A', name: 'PKS Sungai Bahar' },
  { id: 'BU-B', name: 'PKS Muara Bulian' },
]

/**
 * Kesepuluh metrik numerik, masing-masing dengan PENYEBUTNYA SENDIRI.
 * dobi_index sengaja tidak pernah diisi (reading_count 0) sementara
 * ffa_percent 240 — tidak ada satu penyebut bersama yang dapat menghasilkan
 * keduanya, dan itulah yang membuat asersi "tiap metrik punya penyebutnya
 * sendiri" bermakna.
 *
 * average_temperature_c.avg (52,0) sengaja BERBEDA dari rata-rata ketiga
 * suhu posisi (55,4): repo yang merata-ratakannya akan menghasilkan 55,4
 * dan gagal.
 */
function makeMetrics(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    // max 4,4 LEBIH KECIL daripada daily[0].ffa_avg 5,8 (ekstrem mentah vs
    // rata-rata harian — sengaja tak dapat direkonsiliasi).
    ffa_percent: { min: 3.1, avg: 4.05, max: 4.4, reading_count: 240 },
    moisture_content_percent: { min: 0.11, avg: 0.195, max: 0.24, reading_count: 118 },
    impurities_dirt_percent: { min: 0.01, avg: 0.027, max: 0.05, reading_count: 42 },
    dobi_index: { min: null, avg: null, max: null, reading_count: 0 },
    average_temperature_c: { min: 48.0, avg: 52.0, max: 57.0, reading_count: 12 },
    calculated_weight_mt: { min: 380.0, avg: 660.0, max: 980.0, reading_count: 231 },
    calculated_volume_m3: { min: 420.0, avg: 730.0, max: 1090.0, reading_count: 229 },
    oil_temperature_top_c: { min: 46.0, avg: 50.0, max: 54.0, reading_count: 205 },
    oil_temperature_middle_c: { min: 51.0, avg: 55.0, max: 59.0, reading_count: 204 },
    oil_temperature_bottom_c: { min: 57.0, avg: 61.2, max: 66.0, reading_count: 203 },
    ...overrides,
  }
}

const EMPTY_METRIC = { min: null, avg: null, max: null, reading_count: 0 }

/**
 * Empat tanggal ber-record. Baris kedua kosong pada hampir SELURUH kolom
 * rata-rata — tanggal itu tetap tanggal ber-record dan tidak boleh dibuang.
 * Jumlah filled_slots (4 + 3 + 5 + 6 = 18) sengaja BERBEDA dari
 * coverage.filled_slots (3), dan ffa_avg tertinggi (5,8) sengaja LEBIH BESAR
 * daripada metrics.ffa_percent.max (4,4).
 */
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
 * Empat tangki, dan keempatnya ada di sini dengan alasannya sendiri:
 *   - ST-1 dan ST-2 punya pergerakan yang DAPAT dihitung, tetapi nilainya
 *     sengaja tidak sama dengan closing − opening tangki itu;
 *   - ST-3 hanya punya SATU pembacaan stok: movement_mt null dengan
 *     movement_computable false — bukan 0, karena 0 akan mengaku stoknya
 *     tidak berubah;
 *   - ST-4 tidak punya satu pun pembacaan: seluruh kolom stok null dan
 *     reading_count 0, dan ia TETAP hadir — tangki yang tidak pernah diukur
 *     adalah temuan, bukan baris yang layak dibuang.
 *
 * Jumlah reading_count (180 + 96 + 1 + 0 = 277) sengaja berbeda dari
 * total.reading_rows (412).
 */
const BY_TANK_ROWS = [
  {
    storage_tank_id: 'ST-1',
    reading_count: 180,
    opening_mt: 800.0,
    opening_at: '2026-09-01 06:00',
    closing_mt: 960.0,
    closing_at: '2026-09-30 18:00',
    // 100,5 ≠ 960,0 − 800,0 (160,0)
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
    // 160,0 ≠ 540,0 − 500,0 (40,0)
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

/**
 * movement_mt 280,5 ≠ 1500,0 − 1200,0 (300,0) DAN ≠ jumlah pergerakan
 * by_tank yang dapat dihitung (100,5 + 160,0 = 260,5). tanks_without_movement
 * 1 ≠ jumlah baris by_tank ber-movement_computable false (2).
 *
 * opening_at 2026-09-01 06:00 sementara periodenya berakhir 2026-09-30, dan
 * closing_at 2026-09-28 18:00 — dua hari SEBELUM ujung periode, selisih yang
 * harus tetap terbaca dan tidak boleh diganti tanggal batas periode.
 */
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
  // 240 ≠ 4 × 30 × 4 (480). coverage_percent 1,25 karena itu mustahil
  // diturunkan ulang dari medan di sebelahnya (100 × 3 / 480 = 0,625), dan
  // dua desimalnya itulah yang membedakan periode yang buruk pencatatannya
  // dari yang katastrofik — 1,3 bukan jawaban yang sama.
  expected_slots: 240,
  coverage_percent: 1.25,
  tank_count: 4,
  slots_per_tank_per_day: 4,
  days_in_period: 30,
}

/** days_with_records 11 ≠ panjang daily (4); reading_rows 412 milik server. */
const TOTAL = { days_with_records: 11, reading_rows: 412 }

/**
 * Respons /summary yang dikirim StorageTankReportController::summary() —
 * TANPA pembungkus `data` (`response()->json($this->service->summary(...))`).
 */
function makeSummaryBody(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    period: {
      id: 'per-1',
      name: 'Periode September 2026',
      start_date: '2026-09-01',
      end_date: '2026-09-30',
      status: 'open',
      business_unit_name: 'PKS Sungai Bahar',
    },
    business_unit: { id: 'BU-A', name: 'PKS Sungai Bahar' },
    has_data: true,
    coverage: { ...COVERAGE },
    stock: { ...STOCK },
    metrics: makeMetrics(),
    by_tank: BY_TANK_ROWS,
    daily: DAILY_ROWS,
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
      return Promise.resolve({ data: { data: [PERIOD_STORAGE_TANK] } })
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
/* Lapisan repo screen-139 (unit_test_cases tech spec)                 */
/* ================================================================== */

describe('storageTankReportRepo — lapisan repo (screen-139)', () => {
  // 1
  it('fetchPeriods memanggil tepat GET /api/storage-tank-reports/periods dan tidak menyaring jenis stasiun di klien', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [PERIOD_STORAGE_TANK, PERIOD_LINTAS_STASIUN] } })

    const periods = await fetchPeriods()

    expect(callsTo('/api/storage-tank-reports/periods')).toHaveLength(1)
    expect(apiGetMock).toHaveBeenCalledTimes(1)
    expect(apiGetMock).toHaveBeenCalledWith('/api/storage-tank-reports/periods', { params: {} })

    // Seluruh entri diteruskan apa adanya, dalam urutan server. Penyaringan
    // cakupan Storage Tank sepenuhnya milik server — termasuk periode yang
    // mencakup banyak jenis stasiun sekaligus, yang TIDAK boleh dibuang di
    // sini.
    expect(periods).toEqual([PERIOD_STORAGE_TANK, PERIOD_LINTAS_STASIUN])
    expect(periods).toHaveLength(2)
    expect(periods[0].station_type).toBe('storage-tank')
    expect(periods[1].station_type).toBe('storage-tank')
  })

  // 2 — GAGAL TERTUTUP: peran terikat mill (termasuk Operator) tidak pernah
  //     mengirim business_unit_id.
  it('scopeParams tidak mengirim business_unit_id untuk peran yang terikat mill, termasuk Operator', async () => {
    await fetchPeriods({ isAdmin: false, businessUnitId: 'BU-A' })

    const params = paramsOf('/api/storage-tank-reports/periods')

    // Medannya ABSEN — bukan null, bukan string kosong. Bahkan ketika
    // pemanggil menyertakan businessUnitId, nilai itu tidak ikut terkirim:
    // Operator ada di cabang TERIKAT MILL, sama seperti Supervisor dan Mill
    // Management, dan mill-nya ditentukan server dari akun
    // (StorageTankReportService::resolveBusinessUnit).
    expect(params).toEqual({})
    expect(Object.keys(params)).toHaveLength(0)
    expect(params).not.toHaveProperty('business_unit_id')

    // Berlaku juga untuk /summary.
    await fetchSummary('per-1', { isAdmin: false, businessUnitId: 'BU-B' })
    expect(paramsOf('/api/storage-tank-reports/summary')).toEqual({ period_id: 'per-1' })
  })

  // 3
  it('scopeParams mengirim business_unit_id hanya ketika Admin sudah memilih mill', async () => {
    await fetchPeriods({ isAdmin: true, businessUnitId: 'BU-B' })

    expect(paramsOf('/api/storage-tank-reports/periods')).toEqual({ business_unit_id: 'BU-B' })

    await fetchSummary('per-1', { isAdmin: true, businessUnitId: 'BU-B' })
    expect(paramsOf('/api/storage-tank-reports/summary')).toEqual({
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
      PERIOD_STORAGE_TANK,
    ])

    expect(paramsOf('/api/storage-tank-reports/periods')).toEqual({})
  })

  // 5
  it('fetchSummary memanggil GET /api/storage-tank-reports/summary dengan period_id', async () => {
    await fetchSummary('per-1')

    expect(apiGetMock).toHaveBeenCalledTimes(1)
    expect(apiGetMock).toHaveBeenCalledWith('/api/storage-tank-reports/summary', {
      params: { period_id: 'per-1' },
    })
    // Tidak ada parameter lain yang ditambahkan repo.
    expect(Object.keys(paramsOf('/api/storage-tank-reports/summary'))).toEqual(['period_id'])
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
    expect(wrapped.stock).toEqual(bare.stock)
    expect(wrapped.metrics).toEqual(bare.metrics)
    expect(wrapped.by_tank).toEqual(bare.by_tank)
    expect(wrapped.daily).toEqual(bare.daily)

    // /periods dan /business-units/options tetap dibaca LEWAT pembungkus.
    apiGetMock.mockResolvedValueOnce({ data: { data: [PERIOD_STORAGE_TANK] } })
    await expect(fetchPeriods()).resolves.toEqual([PERIOD_STORAGE_TANK])
    apiGetMock.mockResolvedValueOnce({ data: { data: BUSINESS_UNITS } })
    await expect(fetchBusinessUnits()).resolves.toEqual(BUSINESS_UNITS)
  })

  // 7
  it('fetchSummary meneruskan seluruh blok apa adanya tanpa perhitungan kedua', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    // Kesembilan blok hadir, nilainya identik medan per medan.
    expect(summary.period).toEqual(body.period)
    expect(summary.business_unit).toEqual(body.business_unit)
    expect(summary.has_data).toBe(true)
    expect(summary.coverage).toEqual(body.coverage)
    expect(summary.stock).toEqual(body.stock)
    expect(summary.metrics).toEqual(body.metrics)
    expect(summary.by_tank).toEqual(body.by_tank)
    expect(summary.daily).toEqual(body.daily)
    expect(summary.total).toEqual(body.total)
    expect(Object.keys(summary.metrics)).toHaveLength(10)
  })

  // 8 — TANGGAL PEMBACAAN: diteruskan apa adanya, tidak digeser ke ujung periode.
  it('fetchSummary mempertahankan stock.opening_at dan closing_at sebagai tanggal apa adanya', async () => {
    const summary = await fetchSummary('per-1')

    expect(summary.stock.opening_at).toBe('2026-09-01 06:00')
    // closing_at DUA HARI sebelum akhir periode (2026-09-30) — selisih itu
    // harus tetap terbaca, jadi repo tidak menggantinya dengan end_date.
    expect(summary.stock.closing_at).toBe('2026-09-28 18:00')
    expect(summary.stock.closing_at).not.toBe(summary.period?.end_date)
    expect(summary.stock.opening_at).not.toBe(summary.period?.start_date)
  })

  // 9 — GODAAN TERBESAR DI LAYAR INI.
  it('repo tidak menghitung movement_mt sendiri dari closing dikurangi opening', async () => {
    const summary = await fetchSummary('per-1')

    expect(summary.stock.movement_mt).toBe(280.5)
    // Selisih stok GABUNGAN adalah angka yang berbeda — 300,0.
    expect(summary.stock.movement_mt).not.toBe(300.0)
    expect(summary.stock.movement_mt).not.toBe(
      (summary.stock.closing_mt ?? 0) - (summary.stock.opening_mt ?? 0),
    )

    // Dan bukan pula jumlah kolom pergerakan by_tank (260,5): repo tidak
    // menurunkannya dari tabel per tangki sekalipun.
    const tankMovements = BY_TANK_ROWS.map((tank) => tank.movement_mt).filter(
      (value): value is number => value !== null,
    )
    expect(summary.stock.movement_mt).not.toBe(
      tankMovements.reduce((sum, value) => sum + value, 0),
    )
  })

  // 10 — SATU PEMBACAAN BUKAN PERGERAKAN NOL.
  it('fetchSummary mempertahankan by_tank[].movement_computable false beserta movement_mt null', async () => {
    const summary = await fetchSummary('per-1')

    const singleReading = summary.by_tank[2]

    expect(singleReading.storage_tank_id).toBe('ST-3')
    expect(singleReading.reading_count).toBe(1)
    expect(singleReading.opening_mt).toBe(400.0)
    expect(singleReading.closing_mt).toBe(400.0)
    expect(singleReading.opening_at).toBe('2026-09-03 06:00')
    expect(singleReading.closing_at).toBe('2026-09-03 06:00')

    // movement_computable false DAN movement_mt null — repo TIDAK menurunkan
    // 0 dari closing_mt − opening_mt (yang di sini kebetulan memang 0): nol
    // akan terbaca sebagai "tidak bergerak" padahal yang benar adalah "tidak
    // dapat dihitung".
    expect(singleReading.movement_computable).toBe(false)
    expect(singleReading.movement_mt).toBeNull()
    expect(singleReading.movement_mt).not.toBe(0)
  })

  // 11
  it('fetchSummary meneruskan tanks_with_movement dan tanks_without_movement apa adanya', async () => {
    const summary = await fetchSummary('per-1')

    expect(summary.stock.tanks_with_movement).toBe(2)
    expect(summary.stock.tanks_without_movement).toBe(1)

    // TIDAK diturunkan dari panjang by_tank (4) maupun dari penyaringan
    // movement_computable (yang di fixture ini menghasilkan 2, bukan 1).
    expect(summary.stock.tanks_without_movement).not.toBe(
      summary.by_tank.filter((tank) => !tank.movement_computable).length,
    )
    expect(
      summary.stock.tanks_with_movement + summary.stock.tanks_without_movement,
    ).not.toBe(summary.by_tank.length)
  })

  // 12 — PENGURANGAN STOK ADALAH HASIL YANG SAH.
  it('fetchSummary meneruskan movement_mt bernilai negatif apa adanya', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({
        stock: { ...STOCK, movement_mt: -312.75 },
        by_tank: [{ ...BY_TANK_ROWS[0], movement_mt: -180.25 }, ...BY_TANK_ROWS.slice(1)],
      }),
    })

    const summary = await fetchSummary('per-1')

    expect(summary.stock.movement_mt).toBe(-312.75)
    expect(summary.by_tank[0].movement_mt).toBe(-180.25)
    // Tanpa Math.abs, tanpa pembulatan ke nol, dan tanpa ditandai galat.
    expect(summary.stock.movement_mt).not.toBe(312.75)
    expect(summary.by_tank[0].movement_mt).not.toBe(180.25)
    expect(summary.by_tank[0].movement_computable).toBe(true)
  })

  // 13
  it('fetchSummary mempertahankan metrics[metrik].min/avg/max sebagai null dengan reading_count 0', async () => {
    const summary = await fetchSummary('per-1')

    expect(summary.metrics.dobi_index).toEqual(EMPTY_METRIC)
    expect(summary.metrics.dobi_index.min).toBeNull()
    expect(summary.metrics.dobi_index.avg).toBeNull()
    expect(summary.metrics.dobi_index.max).toBeNull()
    // TIDAK menjadi 0/0/0. DOBI memang tidak diukur setiap slot.
    expect(summary.metrics.dobi_index.avg).not.toBe(0)
    expect(summary.metrics.dobi_index.reading_count).toBe(0)

    // Metrik lain tidak terpengaruh sama sekali.
    expect(summary.metrics.ffa_percent.avg).toBe(4.05)
    expect(summary.metrics.ffa_percent.reading_count).toBe(240)
  })

  // 14 — SUHU RATA-RATA DIBACA, BUKAN DITURUNKAN.
  it('fetchSummary mempertahankan metrics.average_temperature_c dan tidak menurunkannya dari tiga suhu posisi', async () => {
    const summary = await fetchSummary('per-1')

    const positional =
      (summary.metrics.oil_temperature_top_c.avg! +
        summary.metrics.oil_temperature_middle_c.avg! +
        summary.metrics.oil_temperature_bottom_c.avg!) /
      3

    expect(summary.metrics.average_temperature_c.avg).toBe(52.0)
    // Rata-rata ketiga suhu posisi adalah 55,4 — angka yang BERBEDA, dan
    // repo yang merata-ratakannya akan menghasilkan itu.
    expect(positional).toBeCloseTo(55.4, 5)
    expect(summary.metrics.average_temperature_c.avg).not.toBe(positional)

    // Dan ketika kolomnya kosong, ia tetap kosong walau ketiga suhu posisi
    // terisi penuh.
    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({
        metrics: makeMetrics({ average_temperature_c: { ...EMPTY_METRIC } }),
      }),
    })

    const unrecorded = await fetchSummary('per-1')

    expect(unrecorded.metrics.average_temperature_c).toEqual(EMPTY_METRIC)
    expect(unrecorded.metrics.average_temperature_c.avg).not.toBe(0)
    expect(unrecorded.metrics.oil_temperature_top_c.avg).toBe(50.0)
    expect(unrecorded.metrics.oil_temperature_middle_c.avg).toBe(55.0)
    expect(unrecorded.metrics.oil_temperature_bottom_c.avg).toBe(61.2)
  })

  // 15
  it('fetchSummary meneruskan reading_count tiap metrik sendiri-sendiri, tanpa penyebut bersama', async () => {
    const summary = await fetchSummary('per-1')

    expect(summary.metrics.ffa_percent.reading_count).toBe(240)
    expect(summary.metrics.dobi_index.reading_count).toBe(0)
    expect(summary.metrics.average_temperature_c.reading_count).toBe(12)

    // Tidak ada satu penyebut bersama yang diturunkan dari
    // total.reading_rows (412) maupun dari coverage.filled_slots (3).
    const counts = Object.values(summary.metrics).map((metric) => metric.reading_count)
    expect(new Set(counts).size).toBeGreaterThan(1)
    expect(counts).not.toContain(summary.total.reading_rows)
    expect(counts).not.toContain(summary.coverage.filled_slots)
  })

  // 16 — STOK DARI BERAT, BUKAN VOLUME MAUPUN KEDALAMAN SOUNDING.
  it('repo tidak memakai calculated_volume_m3 maupun calculated_weight_mt untuk membentuk angka stok', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    // Seluruh medan stock berasal dari blok stock APA ADANYA — identitas
    // objeknya pun tidak putus.
    expect(summary.stock).toBe(body.stock)
    expect(summary.stock.opening_mt).toBe(1200.0)

    // Tidak satu pun nilai volume atau berat terhitung masuk ke stok.
    const volume = summary.metrics.calculated_volume_m3
    const weight = summary.metrics.calculated_weight_mt

    expect(volume.avg).toBe(730.0)
    expect(weight.avg).toBe(660.0)
    for (const value of [volume.min, volume.avg, volume.max, weight.min, weight.avg, weight.max]) {
      expect(summary.stock.opening_mt).not.toBe(value)
      expect(summary.stock.closing_mt).not.toBe(value)
      expect(summary.stock.movement_mt).not.toBe(value)
    }

    // Volume dan berat terhitung tetap hadir sebagai metrik tersendiri.
    expect(summary.metrics).toHaveProperty('calculated_volume_m3')
    expect(summary.metrics).toHaveProperty('calculated_weight_mt')
  })

  // 17
  it('fetchSummary mempertahankan null pada daily[].stock_total_mt dan rata-rata mutu', async () => {
    const summary = await fetchSummary('per-1')

    const blankRow = summary.daily[1]

    expect(blankRow.date).toBe('2026-09-03')
    expect(blankRow.filled_slots).toBe(3)
    expect(blankRow.stock_total_mt).toBeNull()
    expect(blankRow.moisture_avg).toBeNull()
    expect(blankRow.impurities_avg).toBeNull()
    expect(blankRow.dobi_avg).toBeNull()
    expect(blankRow.temperature_avg).toBeNull()
    // FFA-nya terisi walau yang lain kosong — satu baris dapat mengisi satu
    // metrik dan mengosongkan yang lain.
    expect(blankRow.ffa_avg).toBe(3.4)

    // Barisnya TIDAK dibuang dan TIDAK ditambal nol.
    expect(summary.daily).toHaveLength(4)
    expect(blankRow.stock_total_mt).not.toBe(0)
  })

  // 18
  it('fetchSummary meneruskan by_tank termasuk tangki tanpa satu pun pembacaan stok', async () => {
    const summary = await fetchSummary('per-1')

    expect(summary.by_tank).toHaveLength(4)
    expect(summary.by_tank.map((tank) => tank.storage_tank_id)).toEqual([
      'ST-1',
      'ST-2',
      'ST-3',
      'ST-4',
    ])

    const emptyTank = summary.by_tank[3]

    expect(emptyTank.reading_count).toBe(0)
    expect(emptyTank.opening_mt).toBeNull()
    expect(emptyTank.opening_at).toBeNull()
    expect(emptyTank.closing_mt).toBeNull()
    expect(emptyTank.closing_at).toBeNull()
    expect(emptyTank.movement_mt).toBeNull()
    expect(emptyTank.movement_computable).toBe(false)
    // Tangki yang tidak pernah tercatat adalah temuan, bukan baris yang
    // layak dibuang — dan bukan pula tangki bernilai nol.
    expect(emptyTank.opening_mt).not.toBe(0)
  })

  // 19
  it('fetchSummary meneruskan coverage apa adanya tanpa menghitung ulang coverage_percent', async () => {
    const summary = await fetchSummary('per-1')

    // Dua desimalnya apa adanya — 1,25 dan 1,3 menceritakan hal yang berbeda
    // tentang periode yang sama.
    expect(summary.coverage.coverage_percent).toBe(1.25)
    expect(summary.coverage.coverage_percent).not.toBe(1.3)
    // Dan mustahil diturunkan ulang dari medan di sebelahnya: expected_slots
    // (240) sengaja tidak sama dengan 4 × 30 × 4 = 480.
    expect(summary.coverage.expected_slots).not.toBe(
      summary.coverage.tank_count *
        summary.coverage.days_in_period *
        summary.coverage.slots_per_tank_per_day,
    )
    expect(summary.coverage.coverage_percent).not.toBe((100 * 3) / 480)
    // filled_slots milik server, bukan jumlah kolom harian (18).
    expect(summary.coverage.filled_slots).toBe(3)
    expect(summary.coverage.filled_slots).not.toBe(
      DAILY_ROWS.reduce((sum, row) => sum + row.filled_slots, 0),
    )
  })

  // 20
  it('fetchSummary meneruskan has_data apa adanya dan tidak menurunkannya sendiri', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({ has_data: false, total: { days_with_records: 0, reading_rows: 0 } }),
    })

    const summary = await fetchSummary('per-1')

    // daily dan by_tank-nya TIDAK kosong pada fixture ini, jadi has_data
    // false hanya dapat datang dari server.
    expect(summary.has_data).toBe(false)
    expect(summary.daily.length).toBeGreaterThan(0)
    expect(summary.by_tank.length).toBeGreaterThan(0)
    expect(summary.total.reading_rows).toBe(0)
  })

  // 21
  it('fetchSummary tidak mengurutkan ulang maupun menyaring daily, termasuk baris bernilai nol', async () => {
    const fiveDays = [
      { ...DAILY_ROWS[3], date: '2026-09-30' },
      { ...DAILY_ROWS[0], date: '2026-09-01' },
      {
        date: '2026-09-15',
        filled_slots: 0,
        stock_total_mt: 0,
        ffa_avg: 0,
        moisture_avg: 0,
        impurities_avg: 0,
        dobi_avg: 0,
        temperature_avg: 0,
      },
      { ...DAILY_ROWS[1], date: '2026-09-03' },
      { ...DAILY_ROWS[2], date: '2026-09-20' },
    ]

    apiGetMock.mockResolvedValueOnce({ data: makeSummaryBody({ daily: fiveDays }) })

    const summary = await fetchSummary('per-1')

    // Panjang, urutan, dan isinya sama persis dengan yang diterima — urutan
    // di sini sengaja BUKAN kronologis, supaya repo yang menyortir tertangkap.
    expect(summary.daily).toHaveLength(5)
    expect(summary.daily.map((row) => row.date)).toEqual([
      '2026-09-30',
      '2026-09-01',
      '2026-09-15',
      '2026-09-03',
      '2026-09-20',
    ])
    expect(summary.daily).toEqual(fiveDays)
    // Baris bernilai nol TIDAK dibuang — ia justru harus terbaca sebagai
    // hari yang tercatat.
    expect(summary.daily[2].stock_total_mt).toBe(0)
  })

  // 22
  it('fetchSummary meneruskan payload periode kosong apa adanya', async () => {
    const emptyBody = makeSummaryBody({
      has_data: false,
      coverage: { ...COVERAGE, filled_slots: 0 },
      stock: {
        opening_mt: null,
        opening_at: null,
        closing_mt: null,
        closing_at: null,
        movement_mt: null,
        tanks_with_movement: 0,
        tanks_without_movement: 0,
      },
      metrics: {
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
      },
      by_tank: [],
      daily: [],
      total: { days_with_records: 0, reading_rows: 0 },
    })

    apiGetMock.mockResolvedValueOnce({ data: emptyBody })

    const summary = await fetchSummary('per-1')

    expect(summary.has_data).toBe(false)
    expect(summary.daily).toEqual([])
    expect(summary.by_tank).toEqual([])
    // Blok kosong diteruskan apa adanya, TIDAK diganti nol — "belum ada
    // data" dan "angkanya nol" adalah dua jawaban berbeda.
    expect(summary.stock.opening_mt).toBeNull()
    expect(summary.stock.closing_mt).toBeNull()
    expect(summary.stock.movement_mt).toBeNull()
    expect(summary.metrics.ffa_percent.avg).toBeNull()
    expect(summary).toEqual({
      period: emptyBody.period,
      production_line: null,
      business_unit: emptyBody.business_unit,
      has_data: false,
      coverage: emptyBody.coverage,
      stock: emptyBody.stock,
      metrics: emptyBody.metrics,
      by_tank: [],
      daily: [],
      total: emptyBody.total,
    })
  })

  // 23
  it('fetchBusinessUnits memanggil endpoint opsi mill dan membaca pembungkus data', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [{ id: 'BU-A', name: 'Mill A' }] } })

    const units = await fetchBusinessUnits()

    expect(apiGetMock).toHaveBeenCalledWith('/api/storage-tank-reports/business-units/options')
    // Tanpa params sama sekali — tidak ada argumen kedua.
    expect(callsTo('/api/storage-tank-reports/business-units/options')[0]).toHaveLength(1)
    expect(units).toEqual([{ id: 'BU-A', name: 'Mill A' }])

    // Respons tanpa `data` menghasilkan array kosong, bukan galat.
    apiGetMock.mockResolvedValueOnce({ data: {} })
    await expect(fetchBusinessUnits()).resolves.toEqual([])
  })

  // 24
  it('exportCsv meminta responseType blob dan format csv', async () => {
    const blob = await exportCsv('per-1')

    expect(apiGetMock).toHaveBeenCalledWith('/api/storage-tank-reports/export', {
      params: { period_id: 'per-1', format: 'csv' },
      responseType: 'blob',
    })
    // Nilai kembaliannya adalah response.data apa adanya (Blob), bukan CSV
    // yang disusun ulang dari angka di layar.
    expect(blob).toBeInstanceOf(Blob)
  })

  // 25
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

  // 26 — BENTUK GALAT NYATA: objek DATAR, tanpa `response`.
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

  // 27
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
    await expect(fetchPeriods()).resolves.toEqual([PERIOD_STORAGE_TANK])
    await expect(fetchSummary('per-1')).resolves.toHaveProperty('metrics')
  })

  // 28 — ATURAN BISNIS: tidak ada perhitungan kedua di ponsel.
  it('repo tidak memuat satu pun perhitungan KPI di sisi klien', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    // (a) Identitas objek: tidak ada agregasi, pengurutan, maupun
    //     penyaringan yang dapat terjadi tanpa memutus identitas ini.
    expect(summary.coverage).toBe(body.coverage)
    expect(summary.stock).toBe(body.stock)
    expect(summary.metrics).toBe(body.metrics)
    expect(summary.by_tank).toBe(body.by_tank)
    expect(summary.daily).toBe(body.daily)
    expect(summary.total).toBe(body.total)

    // (b) Angka-angka yang saling bertentangan TETAP bertentangan — repo
    //     tidak "memperbaiki" satu pun dari mereka. Inilah yang tidak dapat
    //     dibuktikan dengan payload yang konsisten.
    const dailyFfa = DAILY_ROWS.map((row) => row.ffa_avg).filter(
      (value): value is number => value !== null,
    )
    const dailyStock = DAILY_ROWS.map((row) => row.stock_total_mt).filter(
      (value): value is number => value !== null,
    )
    const tankMovements = BY_TANK_ROWS.map((tank) => tank.movement_mt).filter(
      (value): value is number => value !== null,
    )

    // TIDAK ADA pengurangan closing − opening menjadi pergerakan.
    expect(summary.stock.movement_mt).toBe(280.5)
    expect(summary.stock.movement_mt).not.toBe(1500.0 - 1200.0)
    expect(summary.stock.movement_mt).not.toBe(
      tankMovements.reduce((sum, value) => sum + value, 0),
    )

    // TIDAK ADA penjumlahan stok harian menjadi stok periode.
    expect(summary.stock.closing_mt).toBe(1500.0)
    expect(summary.stock.closing_mt).not.toBe(dailyStock.reduce((sum, value) => sum + value, 0))
    expect(summary.stock.opening_mt).not.toBe(Math.min(...dailyStock))
    expect(summary.stock.closing_mt).not.toBe(Math.max(...dailyStock))

    // TIDAK ADA perataan ketiga suhu posisi menjadi suhu rata-rata.
    expect(summary.metrics.average_temperature_c.avg).toBe(52.0)
    expect(summary.metrics.average_temperature_c.avg).not.toBe((50.0 + 55.0 + 61.2) / 3)

    // Ekstrem berasal dari PEMBACAAN MENTAH: max FFA (4,4) bahkan lebih
    // kecil daripada rata-rata harian tertinggi (5,8) — mustahil bila
    // diturunkan dari kolom harian.
    expect(summary.metrics.ffa_percent.max).toBe(4.4)
    expect(summary.metrics.ffa_percent.max).not.toBe(Math.max(...dailyFfa))
    expect(summary.metrics.ffa_percent.avg).not.toBe(
      dailyFfa.reduce((sum, value) => sum + value, 0) / dailyFfa.length,
    )
    expect(summary.metrics.ffa_percent.avg).not.toBe(Math.round(4.05))

    // Tidak ada pembulatan ulang coverage_percent.
    expect(summary.coverage.coverage_percent).toBe(1.25)
    expect(summary.coverage.coverage_percent).not.toBe((100 * 3) / 480)

    // Tidak ada penurunan has_data dan tidak ada penjumlahan total.
    expect(summary.has_data).toBe(true)
    expect(summary.total.days_with_records).toBe(11)
    expect(summary.total.days_with_records).not.toBe(summary.daily.length)
    expect(summary.total.reading_rows).toBe(412)
    expect(summary.total.reading_rows).not.toBe(
      BY_TANK_ROWS.reduce((sum, tank) => sum + tank.reading_count, 0),
    )

    // (c) Setiap medan hasil === medan masukan, medan demi medan.
    expect(summary.stock).toEqual(body.stock)
    expect(summary.metrics).toEqual(body.metrics)
    expect(summary.coverage).toEqual(body.coverage)
    expect(summary.by_tank).toEqual(body.by_tank)
    expect(summary.daily).toEqual(body.daily)
    expect(summary.total).toEqual(body.total)
  })

  // 29 — Pemeriksaan penutup lewat objek default modul.
  it('mengembalikan hasil lengkap ketika seluruh kondisi terpenuhi', async () => {
    apiGetMock.mockClear()

    const summary = await storageTankReportRepo.fetchSummary('per-1', {
      isAdmin: false,
      businessUnitId: null,
    })

    // Seluruh blok payload hadir dengan nilai identik.
    expect(summary.period?.id).toBe('per-1')
    expect(summary.business_unit?.name).toBe('PKS Sungai Bahar')
    expect(summary.has_data).toBe(true)
    expect(summary.coverage.coverage_percent).toBe(1.25)
    expect(summary.stock.opening_mt).toBe(1200.0)
    expect(summary.stock.movement_mt).toBe(280.5)
    expect(Object.keys(summary.metrics)).toHaveLength(10)
    expect(summary.metrics.ffa_percent.avg).toBe(4.05)
    expect(summary.by_tank).toHaveLength(4)
    expect(summary.daily).toHaveLength(4)
    expect(summary.total.reading_rows).toBe(412)

    // apiClient.get dipanggil TEPAT SATU KALI — tidak ada permintaan
    // tambahan yang dikirim repo.
    expect(apiGetMock).toHaveBeenCalledTimes(1)
    expect(apiGetMock).toHaveBeenCalledWith('/api/storage-tank-reports/summary', {
      params: { period_id: 'per-1' },
    })

    // Objek default mengekspos kelima fungsi yang dipakai view.
    expect(Object.keys(storageTankReportRepo).sort()).toEqual([
      'exportCsv',
      'fetchBusinessUnits',
      'fetchPeriods',
      'fetchSummary',
      'saveCsvFile',
    ])

    const units = await storageTankReportRepo.fetchBusinessUnits()
    const periods = await storageTankReportRepo.fetchPeriods({ isAdmin: true, businessUnitId: 'BU-A' })
    const blob = await storageTankReportRepo.exportCsv('per-1')

    expect(units).toEqual(BUSINESS_UNITS)
    expect(periods).toEqual([PERIOD_STORAGE_TANK])
    expect(blob).toBeInstanceOf(Blob)

    // Admin: business_unit_id memang ikut — dan HANYA untuk Admin.
    expect(apiGetMock).toHaveBeenCalledWith('/api/storage-tank-reports/periods', {
      params: { business_unit_id: 'BU-A' },
    })
    // Ekspor tidak pernah menyertakan business_unit_id — period_id sudah
    // menentukan mill-nya di sisi server.
    expect(paramsOf('/api/storage-tank-reports/export')).toEqual({
      period_id: 'per-1',
      format: 'csv',
    })
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
describe('storageTankReportRepo — production_line_id (konteks yang dipilih, bukan ikatan akun)', () => {
  it('fetchSummary mengirim production_line_id ketika ada, dan MENGABSENKAN parameternya ketika tidak', async () => {
    await fetchSummary('per-1', { productionLineId: 'pl-2' })

    expect(paramsOf('/api/storage-tank-reports/summary')).toEqual({
      period_id: 'per-1',
      production_line_id: 'pl-2',
    })

    // Tanpa nilai: bukan null, bukan string kosong — kuncinya ABSEN, agar
    // jawaban server persis sama dengan sebelum fitur ini ada.
    apiGetMock.mockClear()
    await fetchSummary('per-1')
    await fetchSummary('per-1', { productionLineId: null })
    await fetchSummary('per-1', { productionLineId: '' })

    expect(callsTo('/api/storage-tank-reports/summary')).toHaveLength(3)
    expect(JSON.stringify(apiGetMock.mock.calls)).not.toContain('production_line_id')
  })

  it('production_line_id tidak bercabang menurut peran — ia konteks angka, bukan kewenangan', async () => {
    // Peran terikat mill: business_unit_id tetap ditahan (gagal tertutup),
    // tetapi production_line_id TETAP berangkat. Keduanya memang menjawab
    // pertanyaan yang berbeda.
    await fetchSummary('per-1', { isAdmin: false, businessUnitId: 'bu-9', productionLineId: 'pl-2' })

    expect(paramsOf('/api/storage-tank-reports/summary')).toEqual({
      period_id: 'per-1',
      production_line_id: 'pl-2',
    })

    apiGetMock.mockClear()
    await fetchSummary('per-1', { isAdmin: true, businessUnitId: 'bu-9', productionLineId: 'pl-2' })

    expect(paramsOf('/api/storage-tank-reports/summary')).toEqual({
      period_id: 'per-1',
      business_unit_id: 'bu-9',
      production_line_id: 'pl-2',
    })
  })

  it('fetchPeriods TIDAK PERNAH menyaring per Production Line — periode milik mill', async () => {
    await fetchPeriods({ productionLineId: 'pl-2' })
    await fetchPeriods({ isAdmin: true, businessUnitId: 'bu-9', productionLineId: 'pl-2' })

    expect(callsTo('/api/storage-tank-reports/periods')).toHaveLength(2)
    expect(paramsOf('/api/storage-tank-reports/periods')).toEqual({})

    const periodCalls = apiGetMock.mock.calls.filter((call) => String(call[0]).includes('/periods'))
    expect(JSON.stringify(periodCalls)).not.toContain('production_line_id')
    expect(JSON.stringify(periodCalls)).not.toContain('pl-2')
  })

  it('exportCsv membawa production_line_id — berkas mengikuti cakupan angka di layar', async () => {
    await exportCsv('per-1', { productionLineId: 'pl-2' })

    expect(apiGetMock).toHaveBeenCalledWith('/api/storage-tank-reports/export', {
      params: { period_id: 'per-1', format: 'csv', production_line_id: 'pl-2' },
      responseType: 'blob',
    })

    // Tanpa line, bentuk permintaannya persis seperti sebelum fitur ini ada
    // — nama berkas dan kolom CSV pun tidak berubah (dibentuk SERVER).
    apiGetMock.mockClear()
    await exportCsv('per-1')

    expect(apiGetMock).toHaveBeenCalledWith('/api/storage-tank-reports/export', {
      params: { period_id: 'per-1', format: 'csv' },
      responseType: 'blob',
    })
  })

  it('exportCsv membawa business_unit_id untuk Admin — tanpa itu server menjawab 422', async () => {
    // CACAT PRA-ADA yang ditutup 2026-09-28: exportCsv mengirim
    // productionLineParams tapi TIDAK scopeParams, padahal fetchSummary di
    // berkas yang sama mengirimnya. Untuk Admin (yang tidak terikat mill)
    // storage-tank-reports/export memanggil resolveBusinessUnit(null) dan
    // melempar 422 "Pilih mill terlebih dahulu" -- jadi tombol Ekspor
    // Admin di mobile menghasilkan galat, bukan berkas.
    //
    // Test lama tidak menangkapnya karena scope-nya hanya membawa line,
    // tidak pernah membawa mill; scopeParams() sendiri hanya mengirim mill
    // bagi Admin, sebab mill non-Admin memang dibuang server.
    await exportCsv('per-1', { isAdmin: true, businessUnitId: 'BU-B', productionLineId: 'pl-2' })

    expect(apiGetMock).toHaveBeenCalledWith('/api/storage-tank-reports/export', {
      params: { period_id: 'per-1', format: 'csv', business_unit_id: 'BU-B', production_line_id: 'pl-2' },
      responseType: 'blob',
    })

    // Non-Admin: mill TIDAK dikirim -- server menurunkannya dari akun, dan
    // mengirimnya hanya akan jadi nilai yang dibuang.
    apiGetMock.mockClear()
    await exportCsv('per-1', { isAdmin: false, businessUnitId: 'BU-A', productionLineId: 'pl-2' })

    expect(apiGetMock).toHaveBeenCalledWith('/api/storage-tank-reports/export', {
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
    await storageTankReportRepo.exportCsv('per-1', { productionLineId: 'pl-3' })

    expect(apiGetMock).toHaveBeenCalledWith('/api/storage-tank-reports/export', {
      params: { period_id: 'per-1', format: 'csv', production_line_id: 'pl-3' },
      responseType: 'blob',
    })
  })
})
