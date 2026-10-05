/**
 * weighbridgeReportRepo.spec.ts — screen-144--laporan-weighbridge-mobile /
 * usecase-147--laporan-weighbridge-mobile "Lihat Laporan Periode Weighbridge
 * (Mobile)".
 *
 * Satu test per unit_test_cases pada tech spec screen-144. Kembaran dari
 * tests/sterilizerReportRepo.spec.ts (screen-135) sampai
 * tests/storageTankReportRepo.spec.ts (screen-139), untuk
 * src/services/weighbridgeReportRepo.ts.
 *
 * YANG DI-MOCK HANYA apiClient. Repo-nya sendiri berjalan sungguhan — itulah
 * satu-satunya cara asersi seperti "params yang dikirim tidak memuat
 * business_unit_id" dan "tidak ada perhitungan kedua di klien" membuktikan
 * sesuatu: yang menyusun query adalah scopeParams()/productionLineParams() di
 * dalam repo, dan yang membaca pembungkus respons adalah unwrap() di dalam
 * repo. Men-stub repo akan memindahkan seluruh asersi ke mock buatan test
 * sendiri.
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
 *   - receive.net_weight_avg (777,5) ≠ net_weight_total / net_weight_trip_count
 *     (12.000,5 / 6 = 2.000,08) — godaan terbesar di layar ini;
 *   - receive.missing_net_weight_trip_count (3) ≠ trip_count −
 *     net_weight_trip_count (10 − 6 = 4) — cacahnya milik server, bukan hasil
 *     pengurangan di klien;
 *   - receive.busiest_hour_trip_count (9) ≠ nilai tertinggi receive.hourly
 *     (6), dan receive.busiest_hour (7) BUKAN jam bernilai tertinggi itu —
 *     jadi tidak ada satu pun cara menurunkannya dari daftar jam;
 *   - receive.empty_hour_count (21) ≠ jumlah entri hourly bernilai 0 (22);
 *   - jumlah by_origin[].trip_count (4 + 2 = 6) ≠ receive.trip_count (10);
 *   - dispatch.net_weight_avg (111,25) ≠ 8.000,25 / 4 = 2.000,06;
 *   - dispatch.missing_net_weight_trip_count (2) ≠ 5 − 4 (1);
 *   - jumlah by_destination[].trip_count (3 + 1 = 4) ≠ dispatch.trip_count (5);
 *   - daily_total.receive_trip_count (99) ≠ jumlah daily[].receive_trip_count
 *     (4 + 2 = 6), dan daily_total.dispatch_trip_count (88) ≠ (1 + 3 = 4);
 *   - daily_total.receive_net_weight_total (1,5) ≠ jumlah kolom hariannya
 *     (900,25 + 300,75 = 1.201,0);
 *   - completeness.days_with_trip (11) ≠ panjang daily (2);
 *   - completeness.days_counted (17) ≠ days_in_period (30);
 *   - draft_trip_count (13) dan undated_trip_count (7) tidak berhubungan
 *     dengan jumlah trip mana pun.
 *
 * DAN SATU LAGI YANG KHUSUS LAYAR INI: tidak ada satu pun angka pada fixture
 * yang menjumlahkan kedua arus (10 + 5 = 15, 12.000,5 + 8.000,25 = 20.000,75),
 * dan test terakhir memeriksa KETIADAAN kunci semacam itu pada hasil. Arus
 * masuk dan arus keluar tidak pernah dijumlahkan — bukan di server, bukan di
 * repo, bukan di layar.
 *
 * BENTUK GALAT — DATAR, TANPA `response`. Interceptor pada
 * src/services/apiClient.ts (normalizeError) selalu menolak dengan objek
 * DATAR { message, errors?, status? } dan sudah MEMBUANG `response`. Seluruh
 * mock penolakan di bawah memakai bentuk nyata itu; memalsukan
 * `{ response: { status } }` akan menguji bentuk yang tidak pernah terjadi di
 * produksi. Penerjemahan 401/403 menjadi perilaku layar adalah tugas view
 * (diuji di LaporanWeighbridgeView.spec.ts) — yang wajib dibuktikan di sini
 * hanyalah prasyaratnya: galat naik APA ADANYA, objeknya identik.
 *
 * PEMBUNGKUS `data` BERBEDA ANTAR ENDPOINT. /periods dan
 * /business-units/options dibaca lewat `response.data?.data`, sedangkan
 * /summary dikirim controller TANPA pembungkus. fetchSummary menerima KEDUA
 * bentuk (unwrap), dan keduanya diuji.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/* ------------------------------------------------------------------ */
/* Mock modul — satu-satunya lapisan yang distub                       */
/* ------------------------------------------------------------------ */

const { apiGetMock } = vi.hoisted(() => ({ apiGetMock: vi.fn() }))

vi.mock('@/services/apiClient', () => ({
  default: { get: apiGetMock },
}))

import weighbridgeReportRepo, {
  exportCsv,
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  saveCsvFile,
} from '@/services/weighbridgeReportRepo'

/* ------------------------------------------------------------------ */
/* Fixture — BENTUK RESPONS NYATA                                      */
/* ------------------------------------------------------------------ */

const PERIOD_OPTION = {
  id: 'per-1',
  name: 'Periode September 2026',
  start_date: '2026-09-01',
  end_date: '2026-09-30',
  status: 'open',
  // Sejak 2026-09-25 station_type SELALU terisi — cakupan dinyatakan per
  // jenis stasiun, jadi tidak ada lagi station_type null.
  station_type: 'weighbridge',
  station_type_label: 'Weighbridge',
}

const PERIOD_OPTION_CLOSED = {
  id: 'per-2',
  name: 'Periode Agustus 2026',
  start_date: '2026-08-01',
  end_date: '2026-08-31',
  status: 'closed',
  station_type: 'weighbridge',
  station_type_label: 'Weighbridge',
}

/** 24 ember jam: hanya dua yang berisi, sisanya 0 — dan tetap dikirim. */
function hourlyFixture(filled: Record<number, number>): Array<{ hour: number; trip_count: number }> {
  return Array.from({ length: 24 }, (_, hour) => ({ hour, trip_count: filled[hour] ?? 0 }))
}

const SUMMARY = {
  business_unit: { id: 'bu-1', name: 'Mill Alpha' },
  production_line: { id: 'pl-1', name: 'Line Satu' },
  period: {
    id: 'per-1',
    name: 'Periode September 2026',
    start_date: '2026-09-01',
    end_date: '2026-09-30',
    status: 'open',
  },
  receive: {
    trip_count: 10,
    net_weight_total: 12000.5,
    // SENGAJA ≠ 12000.5 / 6.
    net_weight_avg: 777.5,
    net_weight_trip_count: 6,
    // SENGAJA ≠ 10 − 6.
    missing_net_weight_trip_count: 3,
    hourly: hourlyFixture({ 5: 6, 13: 4 }),
    // SENGAJA bukan jam bernilai tertinggi, dan cacahnya pun tidak cocok.
    busiest_hour: 7,
    busiest_hour_trip_count: 9,
    // SENGAJA ≠ 22 (jumlah ember bernilai 0).
    empty_hour_count: 21,
    by_origin: [
      { estate_supplier: 'Estate Utara', trip_count: 4, net_weight_total: 9000.25, net_weight_trip_count: 3 },
      // Asal yang belum diisi datang sebagai STRING KOSONG (keepNull=false).
      { estate_supplier: '', trip_count: 2, net_weight_total: null, net_weight_trip_count: 0 },
    ],
  },
  dispatch: {
    trip_count: 5,
    net_weight_total: 8000.25,
    // SENGAJA ≠ 8000.25 / 4.
    net_weight_avg: 111.25,
    net_weight_trip_count: 4,
    // SENGAJA ≠ 5 − 4.
    missing_net_weight_trip_count: 2,
    hourly: hourlyFixture({ 20: 3 }),
    busiest_hour: 20,
    busiest_hour_trip_count: 3,
    empty_hour_count: 22,
    by_destination: [
      { destination: 'Refinery X', trip_count: 3, net_weight_total: 7000.0, net_weight_trip_count: 3 },
      // Tujuan yang belum diisi tetap NULL (keepNull=true) — bentuk yang
      // BERBEDA dari by_origin di atas, dan perbedaan itu nyata.
      { destination: null, trip_count: 1, net_weight_total: null, net_weight_trip_count: 0 },
    ],
  },
  draft_trip_count: 13,
  undated_trip_count: 7,
  daily: [
    {
      date: '2026-09-04',
      receive_trip_count: 4,
      receive_net_weight_total: 900.25,
      dispatch_trip_count: 1,
      dispatch_net_weight_total: null,
    },
    {
      date: '2026-09-05',
      receive_trip_count: 2,
      receive_net_weight_total: 300.75,
      dispatch_trip_count: 3,
      dispatch_net_weight_total: 500.5,
    },
  ],
  daily_total: {
    // SENGAJA tidak menjumlah kolom hariannya.
    receive_trip_count: 99,
    receive_net_weight_total: 1.5,
    dispatch_trip_count: 88,
    dispatch_net_weight_total: 2.5,
  },
  completeness: {
    days_in_period: 30,
    // SENGAJA ≠ panjang daily.
    days_with_trip: 11,
    // SENGAJA ≠ days_in_period — periode berjalan.
    days_counted: 17,
    period_running: true,
  },
}

/** Galat transport: penolakan TANPA status, bentuk nyata normalizeError. */
const NETWORK_ERROR = { message: 'Network Error' }

/** Galat berstatus, bentuk nyata normalizeError (datar, tanpa `response`). */
function httpError(status: number, message: string): { status: number; message: string } {
  return { status, message }
}

beforeEach(() => {
  apiGetMock.mockReset()
})

afterEach(() => {
  vi.restoreAllMocks()
})

/* ================================================================== */
/* fetchPeriods                                                        */
/* ================================================================== */

describe('fetchPeriods()', () => {
  it('memanggil tepat GET /api/weighbridge-reports/periods dan tidak menyaring jenis stasiun di sisi klien', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [PERIOD_OPTION, PERIOD_OPTION_CLOSED] } })

    const periods = await fetchPeriods()

    expect(apiGetMock).toHaveBeenCalledTimes(1)
    expect(apiGetMock.mock.calls[0][0]).toBe('/api/weighbridge-reports/periods')

    // Seluruh entri diteruskan apa adanya, dalam urutan yang sama. TIDAK ada
    // penyaringan station_type maupun status di repo — cakupan Weighbridge
    // sepenuhnya milik server, dan periode tertutup TETAP terdaftar karena
    // status mengatur penulisan data, bukan pembacaan laporan.
    expect(periods).toEqual([PERIOD_OPTION, PERIOD_OPTION_CLOSED])
  })

  it('TIDAK PERNAH mengirim production_line_id ke /periods', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [] } })

    await fetchPeriods({ isAdmin: false, productionLineId: 'pl-1' })

    // Periode adalah milik MILL, bukan milik Production Line
    // (periods.business_unit_id, tanpa production_line_id), dan server pun
    // tidak menerima parameter itu di sini. Menyaring daftar periode per line
    // akan mengarang penyempitan yang tidak ada di data.
    expect(apiGetMock.mock.calls[0][1]).toEqual({ params: {} })
  })

  it('daftar kosong adalah jawaban yang sah, bukan galat', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [] } })

    await expect(fetchPeriods()).resolves.toEqual([])
  })

  it('respons tanpa pembungkus data menghasilkan daftar kosong, bukan undefined', async () => {
    apiGetMock.mockResolvedValueOnce({ data: {} })

    await expect(fetchPeriods()).resolves.toEqual([])
  })
})

/* ================================================================== */
/* scopeParams — gagal tertutup pada business_unit_id                  */
/* ================================================================== */

describe('scopeParams() lewat fetchPeriods()', () => {
  it('tidak mengirim business_unit_id untuk peran yang terikat mill, termasuk Operator', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [] } })

    await fetchPeriods({ isAdmin: false, businessUnitId: 'bu-lain' })

    // Kuncinya ABSEN — bukan null, bukan string kosong. Bahkan ketika
    // pemanggil menyertakan businessUnitId, nilai itu tidak ikut dikirim:
    // Operator ada di cabang TERIKAT MILL ini, bukan di cabang Admin.
    const params = apiGetMock.mock.calls[0][1].params

    expect(params).toEqual({})
    expect(Object.keys(params)).not.toContain('business_unit_id')
  })

  it('mengirim business_unit_id hanya ketika Admin sudah memilih mill', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [] } })

    await fetchPeriods({ isAdmin: true, businessUnitId: 'bu-2' })

    expect(apiGetMock.mock.calls[0][1].params).toEqual({ business_unit_id: 'bu-2' })
  })

  it('tidak mengirim business_unit_id ketika Admin belum memilih mill', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [] } })

    await fetchPeriods({ isAdmin: true, businessUnitId: null })

    // Repo tidak mengarang nilai mill dan tidak melempar galat sendiri —
    // server yang menjawab 422 "Pilih mill terlebih dahulu", dan satu sumber
    // kebenaran itulah yang ditampilkan.
    expect(apiGetMock.mock.calls[0][1].params).toEqual({})
  })
})

/* ================================================================== */
/* fetchSummary                                                        */
/* ================================================================== */

describe('fetchSummary()', () => {
  it('mengirim period_id dan production_line_id ke /summary', async () => {
    apiGetMock.mockResolvedValueOnce({ data: SUMMARY })

    await fetchSummary('per-1', { isAdmin: false, businessUnitId: 'bu-lain', productionLineId: 'pl-9' })

    expect(apiGetMock.mock.calls[0][0]).toBe('/api/weighbridge-reports/summary')
    expect(apiGetMock.mock.calls[0][1].params).toEqual({
      period_id: 'per-1',
      production_line_id: 'pl-9',
    })
  })

  it('tidak mengirim production_line_id kosong sebagai parameter kosong', async () => {
    apiGetMock.mockResolvedValueOnce({ data: SUMMARY })

    await fetchSummary('per-1', { isAdmin: false, productionLineId: null })

    // Server menjawab 422 tentang parameter yang HILANG, dan itu pesan yang
    // benar; parameter kosong adalah pesan yang membingungkan. Yang menjaga
    // agar pemanggilan ini tidak terjadi sama sekali adalah view.
    expect(apiGetMock.mock.calls[0][1].params).toEqual({ period_id: 'per-1' })
  })

  it('menyertakan business_unit_id untuk Admin bersama production_line_id', async () => {
    apiGetMock.mockResolvedValueOnce({ data: SUMMARY })

    await fetchSummary('per-1', { isAdmin: true, businessUnitId: 'bu-2', productionLineId: 'pl-9' })

    expect(apiGetMock.mock.calls[0][1].params).toEqual({
      period_id: 'per-1',
      business_unit_id: 'bu-2',
      production_line_id: 'pl-9',
    })
  })

  it('meneruskan kedua kelompok arus APA ADANYA, tanpa satu operasi aritmetika', async () => {
    apiGetMock.mockResolvedValueOnce({ data: SUMMARY })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Nilai-nilai ini SENGAJA bertentangan dengan sumbernya pada fixture —
    // repo yang menghitung sendiri akan menghasilkan angka lain dan gagal di
    // sini. Lihat daftar lengkap pertentangannya pada docblock berkas.
    expect(summary.receive.net_weight_avg).toBe(777.5)
    expect(summary.receive.missing_net_weight_trip_count).toBe(3)
    expect(summary.receive.busiest_hour).toBe(7)
    expect(summary.receive.busiest_hour_trip_count).toBe(9)
    expect(summary.receive.empty_hour_count).toBe(21)

    expect(summary.dispatch.net_weight_avg).toBe(111.25)
    expect(summary.dispatch.missing_net_weight_trip_count).toBe(2)

    expect(summary.daily_total.receive_trip_count).toBe(99)
    expect(summary.daily_total.receive_net_weight_total).toBe(1.5)
    expect(summary.completeness.days_with_trip).toBe(11)
    expect(summary.completeness.days_counted).toBe(17)
    expect(summary.draft_trip_count).toBe(13)
    expect(summary.undated_trip_count).toBe(7)
  })

  it('TIDAK PERNAH membentuk satu pun angka yang menjumlahkan kedua arus', async () => {
    apiGetMock.mockResolvedValueOnce({ data: SUMMARY })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Asersi atas KETIADAAN: tidak ada kunci baru, dan tidak ada nilai yang
    // sama dengan jumlah kedua arus (15 trip atau 20.000,75 kg). 'berapa ton
    // yang ditimbang hari ini' bukan pertanyaan yang berguna bagi siapa pun.
    expect(Object.keys(summary).sort()).toEqual(
      [
        'business_unit',
        'completeness',
        'daily',
        'daily_total',
        'dispatch',
        'draft_trip_count',
        'period',
        'production_line',
        'receive',
        'undated_trip_count',
      ].sort(),
    )

    const flat = JSON.stringify(summary)

    expect(flat).not.toContain('20000.75')
    expect(flat).not.toContain('"total_trip_count"')
    expect(flat).not.toContain('"net_weight_grand_total"')
  })

  it('meneruskan null sebagai null, bukan 0', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: {
        ...SUMMARY,
        receive: { ...SUMMARY.receive, net_weight_total: null, net_weight_avg: null, busiest_hour: null },
      },
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // 'total 0 kg' berarti sesuatu ditimbang dan hasilnya nol; null berarti
    // tidak ada yang pernah ditimbang sampai selesai. Dua fakta berbeda.
    expect(summary.receive.net_weight_total).toBeNull()
    expect(summary.receive.net_weight_avg).toBeNull()
    expect(summary.receive.busiest_hour).toBeNull()
    expect(summary.daily[0].dispatch_net_weight_total).toBeNull()
  })

  it('meneruskan baris by_destination ber-destination null tanpa membuangnya', async () => {
    apiGetMock.mockResolvedValueOnce({ data: SUMMARY })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Tujuan yang belum diisi adalah KELOMPOK, bukan baris yang layak
    // dibuang: membuangnya membuat jumlah trip per tujuan tidak lagi
    // menjumlah ke total arus keluar.
    expect(summary.dispatch.by_destination).toHaveLength(2)
    expect(summary.dispatch.by_destination?.[1].destination).toBeNull()
  })

  it('meneruskan baris by_origin ber-estate_supplier string kosong tanpa mengubahnya', async () => {
    apiGetMock.mockResolvedValueOnce({ data: SUMMARY })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // DUA BENTUK 'belum diisi' dalam satu payload: '' untuk asal
    // (keepNull=false) dan null untuk tujuan (keepNull=true). Repo tidak
    // menyeragamkannya — penerjemahan ke label adalah urusan view.
    expect(summary.receive.by_origin?.[1].estate_supplier).toBe('')
    expect(summary.receive.by_origin).toHaveLength(2)
  })

  it('meneruskan 24 entri hourly apa adanya, termasuk yang bernilai 0', async () => {
    apiGetMock.mockResolvedValueOnce({ data: SUMMARY })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.receive.hourly).toHaveLength(24)
    expect(summary.receive.hourly.map((row) => row.hour)).toEqual(
      Array.from({ length: 24 }, (_, hour) => hour),
    )
    // Entri bernilai 0 tidak dibuang dan tidak dipadatkan: 23 jam kosong
    // adalah temuan operasional, bukan baris yang layak hilang.
    expect(summary.receive.hourly.filter((row) => row.trip_count === 0)).toHaveLength(22)
  })

  it('tidak mengurutkan ulang by_origin maupun by_destination', async () => {
    apiGetMock.mockResolvedValueOnce({ data: SUMMARY })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.receive.by_origin?.map((row) => row.estate_supplier)).toEqual(['Estate Utara', ''])
    expect(summary.dispatch.by_destination?.map((row) => row.destination)).toEqual(['Refinery X', null])
  })

  it('menerima respons dengan maupun tanpa pembungkus data', async () => {
    apiGetMock.mockResolvedValueOnce({ data: SUMMARY })
    const tanpaPembungkus = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    apiGetMock.mockResolvedValueOnce({ data: { data: SUMMARY } })
    const denganPembungkus = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(denganPembungkus).toEqual(tanpaPembungkus)
  })

  it('memakai nilai bawaan HANYA ketika bloknya tidak ada sama sekali', async () => {
    apiGetMock.mockResolvedValueOnce({ data: {} })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.business_unit).toBeNull()
    expect(summary.production_line).toBeNull()
    expect(summary.period).toBeNull()
    expect(summary.receive.trip_count).toBe(0)
    expect(summary.receive.net_weight_total).toBeNull()
    expect(summary.receive.by_origin).toEqual([])
    expect(summary.dispatch.by_destination).toEqual([])
    expect(summary.daily).toEqual([])
    expect(summary.daily_total.receive_net_weight_total).toBeNull()
    expect(summary.completeness.days_counted).toBe(0)
    expect(summary.completeness.period_running).toBe(false)
  })

  it('nilai bawaan TIDAK dipakai untuk menambal medan yang dikirim null', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: { ...SUMMARY, business_unit: null, production_line: null },
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Blok yang HILANG berarti bentuk respons berubah; blok yang dikirim null
    // adalah jawaban server yang sah dan harus lewat apa adanya.
    expect(summary.business_unit).toBeNull()
    expect(summary.production_line).toBeNull()
    expect(summary.receive.trip_count).toBe(10)
  })
})

/* ================================================================== */
/* fetchBusinessUnits                                                  */
/* ================================================================== */

describe('fetchBusinessUnits()', () => {
  it('memanggil endpoint options dan meneruskan daftarnya', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [{ id: 'bu-1', name: 'Mill Alpha' }] } })

    await expect(fetchBusinessUnits()).resolves.toEqual([{ id: 'bu-1', name: 'Mill Alpha' }])
    expect(apiGetMock.mock.calls[0][0]).toBe('/api/weighbridge-reports/business-units/options')
  })

  it('mengembalikan [] untuk respons tanpa data, bukan undefined', async () => {
    apiGetMock.mockResolvedValueOnce({ data: {} })

    await expect(fetchBusinessUnits()).resolves.toEqual([])
  })

  it('meneruskan 403 apa adanya — endpoint ini memang Admin saja', async () => {
    // Perluasan akses screen-144 sengaja TIDAK menyentuh endpoint ini:
    // Operator, Supervisor, dan Mill Management terikat satu mill dan tidak
    // punya pemilih. View yang memutuskan apa artinya (millPickerForbidden).
    apiGetMock.mockRejectedValueOnce(httpError(403, 'Anda tidak memiliki akses untuk aksi ini.'))

    await expect(fetchBusinessUnits()).rejects.toEqual(
      httpError(403, 'Anda tidak memiliki akses untuk aksi ini.'),
    )
  })
})

/* ================================================================== */
/* Galat diteruskan apa adanya                                         */
/* ================================================================== */

describe('penerusan galat', () => {
  it('tidak menangkap galat transport — objeknya naik identik', async () => {
    apiGetMock.mockRejectedValueOnce(NETWORK_ERROR)

    await expect(fetchSummary('per-1', { productionLineId: 'pl-1' })).rejects.toBe(NETWORK_ERROR)
  })

  it('tidak membungkus 401 dan tidak mengembalikan nilai bawaan', async () => {
    const error = httpError(401, 'Unauthenticated.')

    apiGetMock.mockRejectedValueOnce(error)

    await expect(fetchPeriods()).rejects.toEqual(error)
  })

  it('meneruskan 422 beserta pesannya — termasuk penolakan production_line_id yang hilang', async () => {
    const error = httpError(422, 'Production Line wajib dipilih untuk menampilkan laporan.')

    apiGetMock.mockRejectedValueOnce(error)

    await expect(fetchSummary('per-1')).rejects.toEqual(error)
  })
})

/* ================================================================== */
/* Ekspor                                                              */
/* ================================================================== */

describe('exportCsv()', () => {
  it('mengirim format csv, production_line_id, dan responseType blob', async () => {
    const blob = new Blob(['a,b'])

    apiGetMock.mockResolvedValueOnce({ data: blob })

    await expect(exportCsv('per-1', { isAdmin: false, productionLineId: 'pl-9' })).resolves.toBe(blob)

    expect(apiGetMock.mock.calls[0][0]).toBe('/api/weighbridge-reports/export')
    expect(apiGetMock.mock.calls[0][1]).toEqual({
      params: { period_id: 'per-1', format: 'csv', production_line_id: 'pl-9' },
      responseType: 'blob',
    })
  })

  it('tidak pernah meminta format excel — .xlsx hanya ada di layar web', async () => {
    apiGetMock.mockResolvedValueOnce({ data: new Blob(['a,b']) })

    await exportCsv('per-1', { productionLineId: 'pl-9' })

    expect(apiGetMock.mock.calls[0][1].params.format).toBe('csv')
  })

  it('menyertakan business_unit_id untuk Admin saja', async () => {
    apiGetMock.mockResolvedValueOnce({ data: new Blob(['a,b']) })
    await exportCsv('per-1', { isAdmin: false, businessUnitId: 'bu-lain', productionLineId: 'pl-9' })
    expect(apiGetMock.mock.calls[0][1].params.business_unit_id).toBeUndefined()

    apiGetMock.mockResolvedValueOnce({ data: new Blob(['a,b']) })
    await exportCsv('per-1', { isAdmin: true, businessUnitId: 'bu-2', productionLineId: 'pl-9' })
    expect(apiGetMock.mock.calls[1][1].params.business_unit_id).toBe('bu-2')
  })

  it('meneruskan 422 EXPORT_FAILED apa adanya', async () => {
    const error = httpError(422, 'Data terlalu banyak untuk diekspor.')

    apiGetMock.mockRejectedValueOnce(error)

    await expect(exportCsv('per-1', { productionLineId: 'pl-9' })).rejects.toEqual(error)
  })
})

describe('saveCsvFile()', () => {
  it('memakai anchor + object URL lalu membersihkannya', () => {
    const createObjectURL = vi.fn(() => 'blob:url-1')
    const revokeObjectURL = vi.fn()

    vi.stubGlobal('URL', { ...URL, createObjectURL, revokeObjectURL })

    const click = vi.fn()
    const anchor = document.createElement('a')

    anchor.click = click

    const createElement = vi.spyOn(document, 'createElement').mockReturnValueOnce(anchor)
    const appendChild = vi.spyOn(document.body, 'appendChild')
    const removeChild = vi.spyOn(document.body, 'removeChild')

    saveCsvFile(new Blob(['a,b']), 'laporan-weighbridge_periode-september-2026.csv')

    expect(createElement).toHaveBeenCalledWith('a')
    expect(anchor.download).toBe('laporan-weighbridge_periode-september-2026.csv')
    expect(anchor.href).toContain('blob:url-1')
    expect(appendChild).toHaveBeenCalledWith(anchor)
    expect(click).toHaveBeenCalledTimes(1)
    expect(removeChild).toHaveBeenCalledWith(anchor)
    // Object URL yang tidak dicabut adalah kebocoran memori yang bertahan
    // selama WebView hidup.
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:url-1')
  })
})

/* ================================================================== */
/* Objek gabungan                                                      */
/* ================================================================== */

describe('default export', () => {
  it('membawa kelima fungsi dengan nama yang sama seperti kelima repo laporan lain', () => {
    expect(Object.keys(weighbridgeReportRepo).sort()).toEqual([
      'exportCsv',
      'fetchBusinessUnits',
      'fetchPeriods',
      'fetchSummary',
      'saveCsvFile',
    ])
  })
})
