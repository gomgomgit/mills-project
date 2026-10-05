/**
 * gradingReportRepo.spec.ts — screen-147--laporan-grading-mobile /
 * usecase-150--laporan-grading-mobile "Lihat Laporan Periode Grading
 * (Mobile)".
 *
 * Satu test per unit_test_cases pada tech spec screen-147. Kembaran dari
 * tests/sterilizerReportRepo.spec.ts (screen-135) sampai
 * tests/weighbridgeReportRepo.spec.ts (screen-144).
 *
 * YANG DI-MOCK HANYA apiClient. Repo-nya sendiri berjalan sungguhan — itulah
 * satu-satunya cara asersi seperti "params yang dikirim tidak memuat
 * business_unit_id" dan "tidak ada perhitungan kedua di klien" membuktikan
 * sesuatu.
 *
 * ────────────────────────────────────────────────────────────────────────
 * ANGKA FIXTURE SENGAJA TIDAK KONSISTEN SECARA ARITMETIKA — INI POKOKNYA
 * ────────────────────────────────────────────────────────────────────────
 * Dengan payload yang konsisten, "repo tidak menghitung ulang" TIDAK
 * MEMBUKTIKAN APA PUN: repo yang diam-diam menurunkan angkanya sendiri akan
 * menghasilkan nilai yang sama dan tetap lolos. Karena itu:
 *
 *   - share_percent baris Mentah (55) BUKAN quantity_total-nya dibagi
 *     bunch.quantity_total (30 / 100 = 30%) — godaan terbesar di layar ini;
 *   - avg_percentage baris Mentah (77,5) tidak berhubungan dengan
 *     kuantitas mana pun, dan penyebutnya (load_count 2) BERBEDA dari
 *     load_count periode (10);
 *   - bunch.quantity_total (100) ≠ jumlah quantity_total barisnya (30 + 60 =
 *     90), jadi total blok tidak dapat diturunkan dari daftar barisnya;
 *   - bunch.parameter_count (5) ≠ panjang rows (2);
 *   - kg.quantity_total (40) dipilih supaya 100 + 40 = 140 adalah angka yang
 *     BISA terbentuk — dan test memeriksa bahwa ia TIDAK pernah terbentuk;
 *   - netto_avg (123,45) ≠ netto_total / load_count (8.000 / 10 = 800);
 *   - bunch_avg (7,5) ≠ bunch_total / load_count (900 / 10 = 90);
 *   - daily_total.load_count (99) ≠ jumlah daily[].load_count (3 + 4 = 7);
 *   - completeness.days_with_load (11) ≠ panjang daily (2), dan days_counted
 *     (17) ≠ days_in_period (30);
 *   - loads_without_detail (3), draft_load_count (13), loads_not_checked (5),
 *     dan loads_not_acknowledged (6) tidak berhubungan dengan angka lain.
 *
 * JANGAN "merapikan" angka-angka ini. Merapikannya akan melucuti separuh
 * berkas ini menjadi test yang selalu hijau.
 *
 * BENTUK GALAT — DATAR, TANPA `response`. apiClient.normalizeError selalu
 * menolak dengan { message, errors?, status? } dan sudah MEMBUANG `response`.
 * Memalsukan `{ response: { status } }` akan menguji bentuk yang tidak pernah
 * terjadi di produksi.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/* ------------------------------------------------------------------ */
/* Mock modul — satu-satunya lapisan yang distub                       */
/* ------------------------------------------------------------------ */

const { apiGetMock } = vi.hoisted(() => ({ apiGetMock: vi.fn() }))

vi.mock('@/services/apiClient', () => ({
  default: { get: apiGetMock },
}))

import gradingReportRepo, {
  exportCsv,
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  saveCsvFile,
} from '@/services/gradingReportRepo'

/* ------------------------------------------------------------------ */
/* Fixture — BENTUK RESPONS NYATA                                      */
/* ------------------------------------------------------------------ */

const PERIOD_OPTION = {
  id: 'per-1',
  name: 'Periode September 2026',
  start_date: '2026-09-01',
  end_date: '2026-09-30',
  status: 'open',
  station_type: 'grading',
  station_type_label: 'Grading',
}

const PERIOD_OPTION_CLOSED = {
  id: 'per-2',
  name: 'Periode Agustus 2026',
  start_date: '2026-08-01',
  end_date: '2026-08-31',
  status: 'closed',
  station_type: 'grading',
  station_type_label: 'Grading',
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
  load_count: 10,
  netto_total: 8000.0,
  // SENGAJA ≠ 8000 / 10.
  netto_avg: 123.45,
  bunch_total: 900.0,
  // SENGAJA ≠ 900 / 10.
  bunch_avg: 7.5,
  bunch: {
    // SENGAJA ≠ 30 + 60.
    quantity_total: 100.0,
    // SENGAJA ≠ panjang rows.
    parameter_count: 5,
    rows: [
      {
        grading_parameter_id: 'gp-1',
        name: 'Mentah',
        quantity_total: 30.0,
        // SENGAJA ≠ 30 / 100.
        share_percent: 55.0,
        // SENGAJA tidak berhubungan dengan kuantitas mana pun …
        avg_percentage: 77.5,
        // … dan penyebutnya BERBEDA dari load_count periode (10).
        load_count: 2,
      },
      {
        grading_parameter_id: 'gp-2',
        name: 'Masak',
        quantity_total: 60.0,
        share_percent: 11.0,
        avg_percentage: 22.0,
        load_count: 9,
      },
    ],
  },
  kg: {
    quantity_total: 40.0,
    parameter_count: 1,
    rows: [
      {
        grading_parameter_id: 'gp-3',
        name: 'Brondolan Segar',
        quantity_total: 12.0,
        share_percent: 88.0,
        avg_percentage: 1.5,
        load_count: 4,
      },
    ],
  },
  by_estate_supplier: [
    { estate_supplier: 'Estate Utara', load_count: 6, netto_total: 7000.25, bunch_total: 700.0 },
    // Asal yang belum diisi datang sebagai STRING KOSONG — sebuah KELOMPOK.
    { estate_supplier: '', load_count: 4, netto_total: null, bunch_total: null },
  ],
  loads_without_detail: 3,
  draft_load_count: 13,
  loads_without_division: 2,
  loads_not_checked: 5,
  loads_not_acknowledged: 6,
  daily: [
    { date: '2026-09-04', load_count: 3, netto_total: 900.25, bunch_total: 90.0 },
    { date: '2026-09-05', load_count: 4, netto_total: null, bunch_total: 120.0 },
  ],
  daily_total: {
    // SENGAJA tidak menjumlah baris hariannya.
    load_count: 99,
    netto_total: 1.5,
    bunch_total: 2.5,
  },
  completeness: {
    days_in_period: 30,
    // SENGAJA ≠ panjang daily.
    days_with_load: 11,
    // SENGAJA ≠ days_in_period.
    days_counted: 17,
    period_running: true,
  },
}

const NETWORK_ERROR = { message: 'Network Error' }

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
  it('memanggil tepat GET /api/grading-reports/periods dan meneruskan daftar apa adanya', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [PERIOD_OPTION, PERIOD_OPTION_CLOSED] } })

    const periods = await fetchPeriods()

    expect(apiGetMock).toHaveBeenCalledTimes(1)
    expect(apiGetMock.mock.calls[0][0]).toBe('/api/grading-reports/periods')

    // Periode TERTUTUP tetap ikut: status mengatur penulisan data, bukan
    // pembacaan laporan. Dan tidak ada penyaringan station_type di repo.
    expect(periods).toEqual([PERIOD_OPTION, PERIOD_OPTION_CLOSED])
  })

  it('TIDAK PERNAH mengirim production_line_id ke /periods', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [] } })

    await fetchPeriods({ isAdmin: false, productionLineId: 'pl-1' })

    // Periode milik MILL, bukan milik line, dan server tidak menerima
    // parameter itu di sini.
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
  it('tidak mengirim business_unit_id untuk peran terikat mill, termasuk Operator', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [] } })

    await fetchPeriods({ isAdmin: false, businessUnitId: 'bu-lain' })

    const params = apiGetMock.mock.calls[0][1].params

    // Kuncinya ABSEN — bukan null, bukan string kosong. Bahkan ketika
    // pemanggil menyertakan nilainya.
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
    // server yang menjawab 422, dan satu sumber kebenaran itulah yang tampil.
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

    expect(apiGetMock.mock.calls[0][0]).toBe('/api/grading-reports/summary')
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
    // agar pemanggilan ini tidak terjadi adalah view.
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

  it('meneruskan kedua blok satuan APA ADANYA, tanpa satu operasi aritmetika', async () => {
    apiGetMock.mockResolvedValueOnce({ data: SUMMARY })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Nilai-nilai ini SENGAJA bertentangan dengan sumbernya pada fixture —
    // repo yang menghitung sendiri akan menghasilkan angka lain dan gagal.
    expect(summary.bunch.quantity_total).toBe(100.0)
    expect(summary.bunch.parameter_count).toBe(5)
    expect(summary.bunch.rows[0].share_percent).toBe(55.0)
    expect(summary.bunch.rows[0].avg_percentage).toBe(77.5)
    expect(summary.bunch.rows[0].load_count).toBe(2)

    expect(summary.kg.quantity_total).toBe(40.0)
    expect(summary.kg.rows[0].share_percent).toBe(88.0)

    expect(summary.netto_avg).toBe(123.45)
    expect(summary.bunch_avg).toBe(7.5)
    expect(summary.daily_total.load_count).toBe(99)
    expect(summary.completeness.days_with_load).toBe(11)
    expect(summary.loads_without_detail).toBe(3)
    expect(summary.draft_load_count).toBe(13)
  })

  it('TIDAK PERNAH membentuk satu pun angka yang menjumlahkan kedua satuan', async () => {
    apiGetMock.mockResolvedValueOnce({ data: SUMMARY })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Asersi atas KETIADAAN: tidak ada kunci baru, dan tidak ada nilai yang
    // sama dengan jumlah kedua satuan (100 + 40 = 140) — angka yang memang
    // BISA terbentuk dari fixture ini, dan itulah yang membuatnya bermakna.
    expect(Object.keys(summary).sort()).toEqual(
      [
        'bunch',
        'bunch_avg',
        'bunch_total',
        'business_unit',
        'by_estate_supplier',
        'completeness',
        'daily',
        'daily_total',
        'draft_load_count',
        'kg',
        'load_count',
        'loads_not_acknowledged',
        'loads_not_checked',
        'loads_without_detail',
        'loads_without_division',
        'netto_avg',
        'netto_total',
        'period',
        'production_line',
      ].sort(),
    )

    const flat = JSON.stringify(summary)

    expect(flat).not.toContain('140')
    expect(flat).not.toContain('"quantity_total_all"')
    expect(flat).not.toContain('"parameter_total"')
  })

  it('meneruskan null sebagai null, bukan 0', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: {
        ...SUMMARY,
        netto_total: null,
        netto_avg: null,
        bunch: { ...SUMMARY.bunch, quantity_total: null },
      },
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // "total 0 kg" berarti sesuatu ditimbang dan hasilnya nol; null berarti
    // tidak ada yang diukur. Dua fakta berbeda.
    expect(summary.netto_total).toBeNull()
    expect(summary.netto_avg).toBeNull()
    expect(summary.bunch.quantity_total).toBeNull()
    expect(summary.daily[1].netto_total).toBeNull()
    expect(summary.by_estate_supplier[1].netto_total).toBeNull()
  })

  it('meneruskan share_percent dan avg_percentage null tanpa mengoersinya', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: {
        ...SUMMARY,
        bunch: {
          ...SUMMARY.bunch,
          rows: [{ ...SUMMARY.bunch.rows[0], share_percent: null, avg_percentage: null }],
        },
      },
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.bunch.rows[0].share_percent).toBeNull()
    expect(summary.bunch.rows[0].avg_percentage).toBeNull()
  })

  it('meneruskan rows dalam urutan payload, tanpa mengurutkan ulang', async () => {
    apiGetMock.mockResolvedValueOnce({ data: SUMMARY })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.bunch.rows.map((row) => row.name)).toEqual(['Mentah', 'Masak'])
  })

  it('meneruskan baris by_estate_supplier ber-estate_supplier string kosong tanpa mengubahnya', async () => {
    apiGetMock.mockResolvedValueOnce({ data: SUMMARY })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Label "Belum diisi" adalah keputusan VIEW, bukan repo.
    expect(summary.by_estate_supplier[1].estate_supplier).toBe('')
    expect(summary.by_estate_supplier).toHaveLength(2)
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
    expect(summary.load_count).toBe(0)
    expect(summary.netto_total).toBeNull()
    expect(summary.bunch.quantity_total).toBeNull()
    expect(summary.bunch.rows).toEqual([])
    expect(summary.kg.quantity_total).toBeNull()
    expect(summary.kg.rows).toEqual([])
    expect(summary.by_estate_supplier).toEqual([])
    expect(summary.daily).toEqual([])
    expect(summary.daily_total.netto_total).toBeNull()
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
    expect(summary.load_count).toBe(10)
  })
})

/* ================================================================== */
/* fetchBusinessUnits                                                  */
/* ================================================================== */

describe('fetchBusinessUnits()', () => {
  it('memanggil endpoint options dan meneruskan daftarnya', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [{ id: 'bu-1', name: 'Mill Alpha' }] } })

    await expect(fetchBusinessUnits()).resolves.toEqual([{ id: 'bu-1', name: 'Mill Alpha' }])
    expect(apiGetMock.mock.calls[0][0]).toBe('/api/grading-reports/business-units/options')
  })

  it('mengembalikan [] untuk respons tanpa data, bukan undefined', async () => {
    apiGetMock.mockResolvedValueOnce({ data: {} })

    await expect(fetchBusinessUnits()).resolves.toEqual([])
  })

  it('meneruskan 403 apa adanya — endpoint ini Admin saja, termasuk terhadap Operator', async () => {
    // Satu-satunya rute pada prefix grading-reports yang tidak terbuka bagi
    // peran terikat mill, dan itu disengaja. View yang memutuskan artinya.
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

    expect(apiGetMock.mock.calls[0][0]).toBe('/api/grading-reports/export')
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

    saveCsvFile(new Blob(['a,b']), 'laporan-grading_periode-september-2026.csv')

    expect(createElement).toHaveBeenCalledWith('a')
    expect(anchor.download).toBe('laporan-grading_periode-september-2026.csv')
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
  it('membawa kelima fungsi dengan nama yang sama seperti keenam repo laporan lain', () => {
    expect(Object.keys(gradingReportRepo).sort()).toEqual([
      'exportCsv',
      'fetchBusinessUnits',
      'fetchPeriods',
      'fetchSummary',
      'saveCsvFile',
    ])
  })
})
