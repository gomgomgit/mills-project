/**
 * cagesTrackReportRepo.spec.ts — screen-136--laporan-cages-track-mobile /
 * usecase-136--laporan-cages-track-mobile "Lihat Laporan Periode Cages &
 * Tracks (Mobile)".
 *
 * Mencakup SELURUH 21 unit_test_cases pada api_contracts[0] tech spec
 * screen-136, dalam urutan spec, terhadap
 * src/services/cagesTrackReportRepo.ts.
 *
 * YANG DI-MOCK HANYA apiClient. Repo-nya sendiri berjalan sungguhan —
 * itulah satu-satunya cara asersi seperti "params yang dikirim tidak memuat
 * business_unit_id" dan "tidak ada perhitungan kedua di klien" membuktikan
 * sesuatu: yang menyusun query adalah scopeParams() di dalam repo, dan yang
 * memetakan respons adalah unwrap() di dalam repo. Men-stub repo akan
 * memindahkan seluruh asersi ke mock buatan test sendiri.
 *
 * ANGKA FIXTURE SENGAJA TIDAK KONSISTEN SECARA ARITMETIKA. kpi
 * .total_cages_tipped (1284) tidak sama dengan jumlah hourly maupun jumlah
 * daily, avg_cages_per_day (91.7) bukan 1284/14, dan total.cages_tipped
 * (777) bertentangan dengan keduanya. Repo yang benar meneruskan ketiganya
 * apa adanya; repo yang diam-diam menghitung ulang gagal di sini. JANGAN
 * "merapikan" angka-angka ini.
 *
 * ------------------------------------------------------------------
 * DEVIASI TERCATAT — unit_test_cases 18 & 19
 * ------------------------------------------------------------------
 * Kedua butir spec itu menggambarkan galat lewat `response.status`
 * ("apiClient.get menolak dengan galat ber-response.status 403"). Bentuk
 * itu TIDAK PERNAH ADA di produksi: interceptor pada
 * src/services/apiClient.ts (normalizeError, ~baris 52-75) selalu menolak
 * dengan objek DATAR `{ message, errors?, status? }` — properti `response`
 * sudah dibuang di sana. Test di bawah karena itu ditulis terhadap bentuk
 * NYATA (datar), karena test yang memakai bentuk khayalan akan lulus tanpa
 * membuktikan apa pun tentang jalur galat yang benar-benar dilalui
 * aplikasi. Maksud kedua butir itu — "galat diteruskan apa adanya, tidak
 * ditelan menjadi nilai bawaan" — tetap diuji utuh, bahkan lebih keras:
 * objek galat yang diterima pemanggil diasersi IDENTIK (toBe) dengan objek
 * yang ditolak apiClient.
 */
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'

/* ------------------------------------------------------------------ */
/* Mock modul — satu-satunya lapisan yang distub                       */
/* ------------------------------------------------------------------ */

const { apiGetMock } = vi.hoisted(() => ({ apiGetMock: vi.fn() }))

vi.mock('@/services/apiClient', () => ({
  default: { get: apiGetMock },
}))

import cagesTrackReportRepo, {
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  exportCsv,
  saveCsvFile,
} from '@/services/cagesTrackReportRepo'

/* ------------------------------------------------------------------ */
/* Fixture — BENTUK RESPONS NYATA                                      */
/* ------------------------------------------------------------------ */

const PERIOD_CT = {
  id: 'per-1',
  name: 'Periode Maret 2026',
  start_date: '2026-03-01',
  end_date: '2026-03-14',
  status: 'open',
  station_type: 'cages-track',
  station_type_label: 'Cages Track',
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
  station_type: 'cages-track',
  station_type_label: 'Cages Track',
}

const BUSINESS_UNITS = [
  { id: 'BU-A', name: 'PKS Sungai Bahar' },
  { id: 'BU-B', name: 'PKS Muara Bulian' },
]

/** 24 entri; jam 00-05 dan 19-23 di luar jendela operasi tippler 06-18. */
function makeHourly(): Array<{ hour: number; cages: number; within_operating_window: boolean }> {
  return Array.from({ length: 24 }, (_, hour) => ({
    hour,
    cages: hour >= 6 && hour <= 18 ? (hour === 9 ? 168 : 10) : 0,
    within_operating_window: hour >= 6 && hour <= 18,
  }))
}

/**
 * Respons /summary yang dikirim CagesTrackReportService::summary() —
 * TANPA pembungkus `data` (lihat controller).
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
    kpi: {
      total_cages_tipped: 1284,
      total_cages_out: 1310,
      avg_cages_per_day: 91.7,
      peak_hour: 9,
      peak_hour_cages: 168,
      idle_operating_hours: 9,
      longest_gap_hours: 4,
      longest_gap_date: '2026-03-08',
      avg_tippler_duration_hours: 11.5,
      days_with_records: 14,
      days_without_valid_window: 0,
    },
    hourly: makeHourly(),
    daily: [
      {
        date: '2026-03-01',
        cages_tipped: 96,
        cages_out: 99,
        operating_hours: 12,
        idle_operating_hours: 1,
        longest_gap_hours: 2,
        min_remaining: 4,
      },
      {
        date: '2026-03-14',
        cages_tipped: 0,
        cages_out: 0,
        operating_hours: null,
        idle_operating_hours: null,
        longest_gap_hours: null,
        min_remaining: null,
      },
    ],
    queue: { min_remaining: 4, avg_remaining: 11.3 },
    // Sengaja bertentangan dengan kpi dan dengan jumlah baris daily.
    total: { cages_tipped: 777, cages_out: 888, days: 14 },
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
      return Promise.resolve({ data: { data: [PERIOD_CT] } })
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
/* unit_test_cases 1-21 (tech spec screen-136)                         */
/* ================================================================== */

describe('cagesTrackReportRepo — unit_test_cases (tech spec screen-136)', () => {
  // unit_test_case 1
  it('fetchPeriods memanggil tepat GET /api/cages-track-reports/periods dan tidak menyaring station_type di klien', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [PERIOD_CT, PERIOD_LINTAS_STASIUN] } })

    const periods = await fetchPeriods()

    expect(callsTo('/api/cages-track-reports/periods')).toHaveLength(1)
    expect(apiGetMock).toHaveBeenCalledTimes(1)
    expect(apiGetMock).toHaveBeenCalledWith('/api/cages-track-reports/periods', { params: {} })

    // Seluruh entri diteruskan apa adanya, dalam urutan server. Penyaringan
    // cakupan Cages & Tracks sepenuhnya milik server — termasuk periode yang
    // mencakup banyak jenis stasiun sekaligus, yang TIDAK boleh dibuang di
    // sini.
    expect(periods).toEqual([PERIOD_CT, PERIOD_LINTAS_STASIUN])
    expect(periods).toHaveLength(2)
    expect(periods[1].station_type).toBe('cages-track')
  })

  // unit_test_case 2
  it('scopeParams tidak mengirim business_unit_id untuk peran yang terikat mill', async () => {
    await fetchPeriods({ isAdmin: false, businessUnitId: 'BU-A' })

    const params = paramsOf('/api/cages-track-reports/periods')

    // Bukan null, bukan string kosong — kuncinya ABSEN.
    expect(params).toEqual({})
    expect(params).not.toHaveProperty('business_unit_id')
    expect(Object.keys(params)).toEqual([])
    expect(JSON.stringify(params)).not.toContain('BU-A')
  })

  // unit_test_case 3
  it('scopeParams mengirim business_unit_id hanya ketika Admin sudah memilih mill', async () => {
    await fetchPeriods({ isAdmin: true, businessUnitId: 'BU-B' })

    expect(apiGetMock).toHaveBeenCalledWith('/api/cages-track-reports/periods', {
      params: { business_unit_id: 'BU-B' },
    })
  })

  // unit_test_case 4
  it('scopeParams tidak mengirim business_unit_id ketika Admin belum memilih mill', async () => {
    await fetchPeriods({ isAdmin: true, businessUnitId: null })

    const params = paramsOf('/api/cages-track-reports/periods')

    // Repo tidak mengarang nilai mill dan tidak melempar galatnya sendiri:
    // server yang menjawab 422 VALIDATION_ERROR, satu sumber kebenaran.
    expect(params).toEqual({})
    expect(params).not.toHaveProperty('business_unit_id')

    // Admin tanpa businessUnitId sama sekali (undefined) pun sama.
    apiGetMock.mockClear()
    await fetchPeriods({ isAdmin: true })
    expect(apiGetMock).toHaveBeenCalledWith('/api/cages-track-reports/periods', { params: {} })
  })

  // unit_test_case 5
  it('fetchSummary memanggil GET /api/cages-track-reports/summary dengan period_id', async () => {
    await fetchSummary('per-1')

    expect(callsTo('/api/cages-track-reports/summary')).toHaveLength(1)
    expect(apiGetMock).toHaveBeenCalledWith('/api/cages-track-reports/summary', {
      params: { period_id: 'per-1' },
    })
  })

  // unit_test_case 6
  it('unwrap menerima respons summary TANPA pembungkus data maupun DENGAN pembungkus', async () => {
    // (a) tanpa pembungkus — bentuk yang benar-benar dikirim controller.
    apiGetMock.mockResolvedValueOnce({ data: makeSummaryBody() })
    const unwrapped = await fetchSummary('per-1')

    // (b) dengan pembungkus — bila kelak pembungkusnya diseragamkan server.
    apiGetMock.mockResolvedValueOnce({ data: { data: makeSummaryBody() } })
    const wrapped = await fetchSummary('per-1')

    expect(wrapped).toEqual(unwrapped)
    expect(wrapped.kpi.total_cages_tipped).toBe(1284)
    expect(wrapped.hourly).toHaveLength(24)
  })

  // unit_test_case 7 — NOL PERHITUNGAN KEDUA.
  it('fetchSummary meneruskan seluruh KPI apa adanya tanpa perhitungan kedua', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    expect(summary.kpi.total_cages_tipped).toBe(1284)
    expect(summary.kpi.total_cages_out).toBe(1310)
    // 91.7 BUKAN 1284/14 (= 91.714...) dan tidak dibulatkan ulang.
    expect(summary.kpi.avg_cages_per_day).toBe(91.7)
    expect(summary.kpi.avg_cages_per_day).not.toBe(1284 / 14)
    expect(summary.kpi.peak_hour).toBe(9)
    expect(summary.kpi.peak_hour_cages).toBe(168)
    expect(summary.kpi.idle_operating_hours).toBe(9)

    // Asersi paling keras yang tersedia: blok kpi yang dikembalikan adalah
    // OBJEK YANG SAMA dengan blok pada respons. Satu pun penyalinan medan,
    // pembulatan, atau penurunan nilai akan memutus identitas ini.
    expect(summary.kpi).toBe(body.kpi)
  })

  // unit_test_case 8 — NULL BUKAN NOL.
  it('fetchSummary mempertahankan null pada avg_tippler_duration_hours, bukan mengoersinya menjadi 0', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({
        kpi: {
          ...(makeSummaryBody().kpi as Record<string, unknown>),
          avg_tippler_duration_hours: null,
          days_without_valid_window: 14,
        },
      }),
    })

    const summary = await fetchSummary('per-1')

    // null = "tidak dapat dihitung"; 0 = "tippler tidak pernah jalan".
    expect(summary.kpi.avg_tippler_duration_hours).toBeNull()
    expect(summary.kpi.avg_tippler_duration_hours).not.toBe(0)
    expect(summary.kpi.days_without_valid_window).toBe(14)
  })

  // unit_test_case 9
  it('fetchSummary mempertahankan null pada longest_gap_hours dan longest_gap_date', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({
        kpi: {
          ...(makeSummaryBody().kpi as Record<string, unknown>),
          longest_gap_hours: null,
          longest_gap_date: null,
        },
      }),
    })

    const summary = await fetchSummary('per-1')

    expect(summary.kpi.longest_gap_hours).toBeNull()
    expect(summary.kpi.longest_gap_date).toBeNull()
    expect(summary.kpi.longest_gap_hours).not.toBe(0)
    expect(summary.kpi.longest_gap_date).not.toBe('')
  })

  // unit_test_case 10
  it('fetchSummary mempertahankan peak_hour null pada periode tanpa penumpahan', async () => {
    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({
        kpi: {
          ...(makeSummaryBody().kpi as Record<string, unknown>),
          peak_hour: null,
          peak_hour_cages: 0,
        },
      }),
    })

    const summary = await fetchSummary('per-1')

    // 0 akan terbaca "puncaknya pukul 00.00" — fakta yang berbeda.
    expect(summary.kpi.peak_hour).toBeNull()
    expect(summary.kpi.peak_hour).not.toBe(0)
    expect(summary.kpi.peak_hour_cages).toBe(0)
  })

  // unit_test_case 11
  it('fetchSummary meneruskan 24 entri hourly beserta penanda within_operating_window', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    expect(summary.hourly).toHaveLength(24)
    // Urutan respons dipertahankan.
    expect(summary.hourly.map((row) => row.hour)).toEqual(Array.from({ length: 24 }, (_, i) => i))
    // Penanda diteruskan apa adanya — repo tidak menghitung sendiri jam
    // mana yang berada di dalam jendela operasi.
    expect(summary.hourly[0].within_operating_window).toBe(false)
    expect(summary.hourly[9].within_operating_window).toBe(true)
    expect(summary.hourly[9].cages).toBe(168)
    expect(summary.hourly[23].within_operating_window).toBe(false)
    expect(summary.hourly).toBe(body.hourly)
  })

  // unit_test_case 12
  it('fetchSummary tidak mengurutkan ulang maupun menyaring daily, termasuk baris bernilai nol', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    expect(summary.daily).toHaveLength(2)
    // Urutan server, termasuk baris ber-cages_tipped 0 yang justru harus
    // terbaca (hari itu tetap menjadi pembagi rata-rata harian).
    expect(summary.daily.map((row) => row.date)).toEqual(['2026-03-01', '2026-03-14'])
    expect(summary.daily[1].cages_tipped).toBe(0)
    expect(summary.daily[1].operating_hours).toBeNull()
    expect(summary.daily).toBe(body.daily)
    // Tidak ada tanggal karangan di antara keduanya.
    expect(summary.daily.map((row) => row.date)).not.toContain('2026-03-02')
  })

  // unit_test_case 13
  it('fetchSummary meneruskan queue sebagai min dan rata-rata tanpa penjumlahan', async () => {
    const summary = await fetchSummary('per-1')

    expect(summary.queue.min_remaining).toBe(4)
    expect(summary.queue.avg_remaining).toBe(11.3)
    // Antrean adalah POTRET PER JAM: medan total/jumlah tidak boleh ada,
    // karena menyediakan tempatnya saja sudah mengundang penjumlahan.
    expect(summary.queue).not.toHaveProperty('total')
    expect(summary.queue).not.toHaveProperty('total_remaining')
    expect(summary.queue).not.toHaveProperty('sum_remaining')
    expect(Object.keys(summary.queue).sort()).toEqual(['avg_remaining', 'min_remaining'])
  })

  // unit_test_case 14
  it('fetchSummary meneruskan payload periode kosong apa adanya', async () => {
    const emptyKpi = {
      total_cages_tipped: 0,
      total_cages_out: 0,
      avg_cages_per_day: 0,
      peak_hour: null,
      peak_hour_cages: 0,
      idle_operating_hours: 0,
      longest_gap_hours: null,
      longest_gap_date: null,
      avg_tippler_duration_hours: null,
      days_with_records: 0,
      days_without_valid_window: 0,
    }
    const zeroHourly = Array.from({ length: 24 }, (_, hour) => ({
      hour,
      cages: 0,
      within_operating_window: false,
    }))

    apiGetMock.mockResolvedValueOnce({
      data: makeSummaryBody({
        kpi: emptyKpi,
        hourly: zeroHourly,
        daily: [],
        queue: { min_remaining: null, avg_remaining: null },
        total: { cages_tipped: 0, cages_out: 0, days: 0 },
      }),
    })

    const summary = await fetchSummary('per-1')

    expect(summary.kpi).toEqual(emptyKpi)
    expect(summary.hourly).toHaveLength(24)
    expect(summary.daily).toEqual([])
    expect(summary.queue).toEqual({ min_remaining: null, avg_remaining: null })
    expect(summary.total).toEqual({ cages_tipped: 0, cages_out: 0, days: 0 })
    // Blok yang DIKIRIM nol tidak diganti nilai bentukan; layar yang
    // memutuskan menampilkan keterangan belum ada data.
    expect(summary.kpi.avg_cages_per_day).toBe(0)
  })

  // unit_test_case 15
  it('fetchBusinessUnits memanggil endpoint opsi mill dan membaca pembungkus data', async () => {
    apiGetMock.mockResolvedValueOnce({ data: { data: [{ id: 'BU-A', name: 'PKS Sungai Bahar' }] } })

    const units = await fetchBusinessUnits()

    expect(apiGetMock).toHaveBeenCalledWith('/api/cages-track-reports/business-units/options')
    expect(units).toEqual([{ id: 'BU-A', name: 'PKS Sungai Bahar' }])

    // Respons tanpa isi tetap menghasilkan daftar kosong, bukan lemparan.
    apiGetMock.mockResolvedValueOnce({ data: {} })
    await expect(fetchBusinessUnits()).resolves.toEqual([])
  })

  // unit_test_case 16
  it('exportCsv meminta responseType blob dan format csv', async () => {
    const blob = new Blob(['a,b\n1,2\n'], { type: 'text/csv' })
    apiGetMock.mockResolvedValueOnce({ data: blob })

    const result = await exportCsv('per-1')

    expect(apiGetMock).toHaveBeenCalledWith('/api/cages-track-reports/export', {
      params: { period_id: 'per-1', format: 'csv' },
      responseType: 'blob',
    })
    // Isi CSV dibentuk SERVER; repo hanya meneruskan blob-nya.
    expect(result).toBe(blob)
  })

  // unit_test_case 17
  it('saveCsvFile memicu unduhan lewat anchor + object URL dan membersihkannya', () => {
    const clickSpy = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
    const appendSpy = vi.spyOn(document.body, 'appendChild')
    const removeSpy = vi.spyOn(document.body, 'removeChild')

    const blob = new Blob(['csv'], { type: 'text/csv' })
    saveCsvFile(blob, 'cages-track.csv')

    expect(createObjectURLMock).toHaveBeenCalledTimes(1)
    expect(createObjectURLMock).toHaveBeenCalledWith(blob)

    const anchor = appendSpy.mock.calls[0][0] as HTMLAnchorElement
    expect(anchor.tagName).toBe('A')
    expect(anchor.download).toBe('cages-track.csv')
    expect(anchor.href).toContain('blob:mock-url')

    expect(clickSpy).toHaveBeenCalledTimes(1)
    expect(removeSpy).toHaveBeenCalledWith(anchor)
    // Object URL dilepas kembali — tidak ada kebocoran.
    expect(revokeObjectURLMock).toHaveBeenCalledWith('blob:mock-url')
    // Anchor tidak tertinggal di dokumen.
    expect(document.body.contains(anchor)).toBe(false)
  })

  /*
   * unit_test_case 18 — DITULIS TERHADAP PERILAKU NYATA, bukan teks spec.
   *
   * Spec menulis "galat ber-response.status 403". Bentuk itu tidak pernah
   * sampai ke repo: apiClient.interceptors.response menolak dengan hasil
   * normalizeError(), yaitu objek DATAR { message, errors?, status } tanpa
   * properti `response` sama sekali. Menguji `.response.status` di sini
   * hanya akan menguji objek karangan test. Yang diuji: galat diteruskan
   * NAIK APA ADANYA — objeknya identik, bukan salinan, bukan kelas galat
   * baru, dan bukan nilai bawaan yang menyamar sebagai keberhasilan.
   */
  it('galat dari apiClient diteruskan naik, tidak ditelan menjadi hasil kosong', async () => {
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

    // Dan galat itu memang hanya muncul karena apiClient menolak: dengan
    // mock yang kembali normal, pemanggilan berikutnya berhasil seperti
    // biasa — jadi asersi di atas benar-benar menguji jalur galat, bukan
    // repo yang kebetulan selalu melempar.
    await expect(fetchSummary('per-1')).resolves.toHaveProperty('kpi')
  })

  /*
   * unit_test_case 19 — deviasi yang sama, lihat catatan pada case 18.
   * Kegagalan transport tiba TANPA `status` sama sekali (cabang
   * error.request di normalizeError), dan justru ketiadaan status itulah
   * yang membedakan "jaringan putus" dari "server menjawab 401/403/422" —
   * pembeda yang dipakai view untuk memilih antara Coba Lagi dan Login.
   */
  it('galat transport tanpa objek response juga diteruskan naik', async () => {
    const transport = { message: 'Tidak dapat terhubung ke server. Data akan disimpan secara lokal.' }
    apiGetMock.mockRejectedValueOnce(transport)

    const caught = await fetchSummary('per-1').then(
      () => null,
      (error: unknown) => error,
    )

    expect(caught).toBe(transport)
    expect(caught).not.toHaveProperty('status')
    expect(caught).not.toHaveProperty('response')

    // Berlaku untuk seluruh fungsi repo, bukan hanya fetchSummary.
    apiGetMock.mockRejectedValueOnce(transport)
    await expect(fetchPeriods()).rejects.toBe(transport)
    apiGetMock.mockRejectedValueOnce(transport)
    await expect(fetchBusinessUnits()).rejects.toBe(transport)
    apiGetMock.mockRejectedValueOnce(transport)
    await expect(exportCsv('per-1')).rejects.toBe(transport)
  })

  // unit_test_case 20 — ATURAN BISNIS: tidak ada perhitungan kedua di ponsel.
  it('repo tidak memuat satu pun perhitungan KPI di sisi klien', async () => {
    const body = makeSummaryBody()
    apiGetMock.mockResolvedValueOnce({ data: body })

    const summary = await fetchSummary('per-1')

    // Seluruh blok adalah objek/array yang SAMA dengan respons: tidak ada
    // agregasi (jumlah, rata-rata, min, maks), tidak ada pengurutan, dan
    // tidak ada penyaringan yang dapat terjadi tanpa memutus identitas ini.
    expect(summary.kpi).toBe(body.kpi)
    expect(summary.hourly).toBe(body.hourly)
    expect(summary.daily).toBe(body.daily)
    expect(summary.queue).toBe(body.queue)
    expect(summary.total).toBe(body.total)
    expect(summary.period).toBe(body.period)

    // Dan angka-angka yang saling bertentangan tetap bertentangan: repo
    // tidak "memperbaiki" satu pun dari mereka.
    const hourlySum = (body.hourly as Array<{ cages: number }>).reduce((sum, row) => sum + row.cages, 0)
    const dailySum = (body.daily as Array<{ cages_tipped: number }>).reduce((sum, row) => sum + row.cages_tipped, 0)
    expect(summary.kpi.total_cages_tipped).toBe(1284)
    expect(summary.kpi.total_cages_tipped).not.toBe(hourlySum)
    expect(summary.kpi.total_cages_tipped).not.toBe(dailySum)
    expect(summary.total.cages_tipped).toBe(777)
    expect(summary.total.days).toBe(14)

    // Tidak ada medan turunan yang ditambahkan repo.
    expect(Object.keys(summary).sort()).toEqual(['daily', 'hourly', 'kpi', 'period', 'queue', 'total'])
  })

  // unit_test_case 21
  it('mengembalikan hasil lengkap ketika seluruh kondisi terpenuhi', async () => {
    const units = await cagesTrackReportRepo.fetchBusinessUnits()
    const periods = await cagesTrackReportRepo.fetchPeriods({ isAdmin: true, businessUnitId: 'BU-A' })
    const summary = await cagesTrackReportRepo.fetchSummary('per-1', { isAdmin: true, businessUnitId: 'BU-A' })
    const blob = await cagesTrackReportRepo.exportCsv('per-1')

    expect(units).toEqual(BUSINESS_UNITS)
    expect(periods).toEqual([PERIOD_CT])

    expect(summary.period?.id).toBe('per-1')
    expect(summary.kpi.total_cages_tipped).toBe(1284)
    expect(summary.hourly).toHaveLength(24)
    expect(summary.daily).toHaveLength(2)
    expect(summary.queue).toEqual({ min_remaining: 4, avg_remaining: 11.3 })
    expect(summary.total).toEqual({ cages_tipped: 777, cages_out: 888, days: 14 })
    expect(blob).toBeInstanceOf(Blob)

    // Admin: business_unit_id memang ikut — dan HANYA untuk Admin.
    expect(apiGetMock).toHaveBeenCalledWith('/api/cages-track-reports/periods', {
      params: { business_unit_id: 'BU-A' },
    })
    expect(apiGetMock).toHaveBeenCalledWith('/api/cages-track-reports/summary', {
      params: { period_id: 'per-1', business_unit_id: 'BU-A' },
    })
  })
})
