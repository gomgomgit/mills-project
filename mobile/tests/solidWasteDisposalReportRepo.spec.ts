/**
 * solidWasteDisposalReportRepo.spec.ts — screen-163--laporan-solid-waste-disposal-mobile /
 * usecase-169--laporan-solid-waste-disposal-mobile.
 *
 * Bagian SISI KLIEN-BAWAH dari 48 `unit_test_cases` tech spec screen-163 yang
 * menyangkut BENTUK PERMINTAAN dan KESETIAAN TERUSAN: kasus 1, 2, 3, separuh
 * repo dari 5 (baca saja, nol tulisan), separuh repo dari 17-23 (nol
 * aritmetika), separuh repo dari 31-32 (kunci yang tidak pernah diketik),
 * separuh repo dari 41-44 (galat diteruskan APA ADANYA — penafsirannya milik
 * view), dan kasus 45 (URL ekspor). Keputusan LAYAR atas jawaban repo ada di
 * LaporanSolidWasteDisposalView.spec.ts; kebenaran ANGKA-nya sudah dibuktikan
 * terhadap SolidWasteDisposalReportService ketika screen-162 dibangun — layar
 * ini tidak menyentuh satu berkas backend pun.
 *
 * Kembaran KELIMA BELAS dari pola yang sama, dari sterilizerReportRepo.spec.ts
 * (screen-135) sampai processWaterReportRepo.spec.ts (screen-161).
 *
 * ────────────────────────────────────────────────────────────────────────
 * TUJUH KLAIM YANG HANYA BERKAS INI DAPAT MENCATATNYA
 * ────────────────────────────────────────────────────────────────────────
 * 1. TEPAT LIMA ENDPOINT, DAN TIDAK ADA YANG KEENAM. Empat di bawah prefix
 *    /api/solid-waste-disposal-reports/ (business-units/options, periods,
 *    summary, export) plus /api/production-lines/options-for-report yang
 *    dipakai ULANG VERBATIM dari screen-135. NOL endpoint baru, NOL entri
 *    api-index baru, NOL berkas backend disentuh untuk layar ini.
 *
 * 2. ADA CABANG ADMIN DI SINI — BERBEDA DARI processWaterReportRepo.ts.
 *    Layar ini punya EMPAT aktor dan Admin salah satunya, jadi
 *    `business_unit_id` memang punya pekerjaan. Yang diasersi: parameter itu
 *    terkirim HANYA ketika pemanggil MENYATAKAN dirinya Admin, dan untuk
 *    ketiga peran terikat mill kuncinya ABSEN — bukan null, bukan string
 *    kosong. Diasersi atas NAMA KUNCI lewat walker rekursif, bukan atas teks
 *    URL: kunci yang muncul bernilai undefined lolos dari asersi substring.
 *
 * 3. `format=csv` DIKIRIM EKSPLISIT, dan tidak ada jalur kode yang dapat
 *    menyusun format lain. Endpoint-nya MEMANG mendukung 'xlsx'
 *    (SUPPORTED_FORMATS = ['csv', 'xlsx']); perangkat lapangan tidak punya
 *    pembaca .xlsx yang dapat diandalkan, jadi nilai itu tidak muncul sebagai
 *    NILAI di repo maupun di kode view — diasersi juga lewat audit statis atas
 *    sumber keduanya, dan audit itu DILINGKUP KE BLOK SKRIP view karena
 *    PROSANYA memang menyebut '.xlsx' untuk menerangkan ketiadaannya sendiri.
 *
 * 4. BODY /summary DITERUSKAN UTUH. Nol kunci dibuang, nol bawaan
 *    disuntikkan, nol aritmetika. Diasersi dua arah: deep-equal dengan
 *    fixture DAN walker rekursif atas setiap JALUR KUNCI fixture — kunci yang
 *    hilang tidak boleh tak terlihat sampai seseorang membacanya di layar.
 *
 * 5. fetchLines() MENDELEGASIKAN ke productionLineRepo.
 *    fetchProductionLinesForReport(). Berkas ini karena itu me-mock apiClient
 *    SAJA dan memakai productionLineRepo YANG SUNGGUHAN, sehingga URL yang
 *    terkumpul adalah URL yang benar-benar dikirim — mock atas repo line akan
 *    membuat kasus 1 hijau tanpa menyentuh satu URL pun.
 *
 * 6. NOL TULISAN KE localDb, dan SELURUH permintaan bermetode GET. Ketiadaan
 *    mode luring DISENGAJA: hasil laporan tidak pernah disimpan, jadi keadaan
 *    luring dinyatakan apa adanya alih-alih ditutupi angka basi.
 *
 * 7. KUNCI YANG TIDAK DIKETIK TIDAK DAPAT SALAH DIBACA. Repo ini tidak
 *    menyebut `hourly`, `busiest_hour`, `empty_hour_count`, maupun `shift`
 *    sekali pun di luar komentar — dan payload yang MEMBAWA ketiga kunci
 *    pertama tetap diteruskan utuh, karena membuang kunci dan tidak membaca
 *    kunci adalah dua hal yang berbeda.
 *
 * ────────────────────────────────────────────────────────────────────────
 * FIXTURE-NYA SENGAJA BERTENTANGAN DENGAN DIRINYA SENDIRI
 * ────────────────────────────────────────────────────────────────────────
 * Klaim pokok layar ini adalah "PONSEL TIDAK MENGHITUNG APA PUN", dan klaim
 * itu hanya dapat dibuktikan oleh payload yang MUSTAHIL dihasilkan
 * perhitungan:
 *
 *   - loads.net_weight_avg 9.9 padahal 37.5 / 5 = 7,5
 *   - loads.load_count 12 padahal baris by_waste_type berjumlah 11
 *   - daily[] berjumlah 9 muatan sementara daily_total.net_weight_total 55.0
 *     BUKAN jumlah ketiga net_weight_total barisnya
 *   - completeness.percent 41.3 padahal 4 / 11 = 36,36…
 *
 * Repo yang menghitung sendiri PASTI gagal di sini. JANGAN "merapikan"
 * angka-angka ini.
 *
 * TANGGALNYA RELATIF, bukan kalender tetap — fixture bertanggal mati menjadi
 * basi tanpa satu pun test berubah warna.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }))

vi.mock('@/services/apiClient', () => ({
  default: { get: getMock },
}))

/**
 * localDb di-mock PENUH dengan spy tulis, lalu diasersi NOL pemanggilan.
 *
 * Modul aslinya hanya punya open/query/run; put/add/bulkPut/transaction
 * ditambahkan di sini karena unit_test_case 5 menamai "nol tulisan ke
 * localDb" — dan sebuah repo yang kelak menumbuhkan cache lewat salah satu
 * nama itu akan tertangkap tanpa berkas ini perlu disunting. Mock-nya
 * berlaku meski modulnya tidak pernah diimpor; itulah sebabnya audit statis
 * di bawah MENDAMPINGINYA alih-alih diganti olehnya.
 */
const { localDbMocks } = vi.hoisted(() => ({
  localDbMocks: {
    open: vi.fn(),
    query: vi.fn(),
    run: vi.fn(),
    put: vi.fn(),
    add: vi.fn(),
    bulkPut: vi.fn(),
    transaction: vi.fn(),
  },
}))

vi.mock('@/services/localDb', () => ({
  default: localDbMocks,
  localDb: localDbMocks,
  ...localDbMocks,
}))

import solidWasteDisposalReportRepo, {
  BUSINESS_UNITS_PATH,
  buildExportUrl,
  EXPORT_PATH,
  exportCsv,
  exportParams,
  fetchBusinessUnits,
  fetchLines,
  fetchPeriods,
  fetchSummary,
  LINES_PATH,
  PERIODS_PATH,
  saveCsvFile,
  SUMMARY_PATH,
  type SolidWasteDisposalReportScope,
} from '@/services/solidWasteDisposalReportRepo'

/* ------------------------------------------------------------------ */
/* Tanggal relatif                                                     */
/* ------------------------------------------------------------------ */

function isoDaysFromNow(offsetDays: number): string {
  return new Date(Date.now() + offsetDays * 86_400_000).toISOString().slice(0, 10)
}

const PERIOD_START = isoDaysFromNow(-10)
const PERIOD_END = isoDaysFromNow(-1)
const PREV_START = isoDaysFromNow(-40)
const PREV_END = isoDaysFromNow(-11)
const DAY_A = isoDaysFromNow(-9)
const DAY_B = isoDaysFromNow(-8)
const DAY_C = isoDaysFromNow(-7)

/* ------------------------------------------------------------------ */
/* Walker rekursif atas NAMA KUNCI                                     */
/* ------------------------------------------------------------------ */

/**
 * Seluruh NAMA KUNCI di dalam sebuah struktur, serapat apa pun ia tersarang.
 *
 * KETIADAAN DIASERSI ATAS KUNCI, BUKAN ATAS TEKS. `JSON.stringify(payload)`
 * membuang kunci yang bernilai `undefined`, sementara kunci itulah yang justru
 * paling berbahaya: ia ADA pada objeknya, ikut terbaca `Object.keys`, dan
 * sebuah pemanggil yang menuliskannya akan mengirimkannya begitu nilainya
 * terisi. Pemindaian teks juga memerah pada PROSA yang justru menerangkan
 * ketiadaannya — dan payload layar ini penuh prosa semacam itu.
 */
function keyNamesOf(value: unknown): string[] {
  const names: string[] = []
  const stack: unknown[] = [value]

  while (stack.length > 0) {
    const node = stack.pop()

    if (Array.isArray(node)) {
      stack.push(...node)

      continue
    }

    if (node !== null && typeof node === 'object') {
      for (const [key, child] of Object.entries(node as Record<string, unknown>)) {
        names.push(key)
        stack.push(child)
      }
    }
  }

  return names
}

/**
 * Setiap JALUR kunci (dot-path) di dalam sebuah struktur. Deep-equal
 * membuktikan nilainya sama; daftar jalur membuktikan tidak satu kunci pun
 * HILANG — dua pertanyaan yang berbeda, dan yang kedua tetap berguna bila
 * kelak sebuah kunci kebetulan bernilai undefined di kedua sisi.
 */
function keyPathsOf(value: unknown, prefix = ''): string[] {
  if (Array.isArray(value)) {
    return value.flatMap((item, index) => keyPathsOf(item, `${prefix}[${index}]`))
  }

  if (value !== null && typeof value === 'object') {
    return Object.entries(value as Record<string, unknown>).flatMap(([key, child]) => {
      const path = prefix === '' ? key : `${prefix}.${key}`

      return [path, ...keyPathsOf(child, path)]
    })
  }

  return []
}

/**
 * Sumber TANPA komentar. Kedua berkas layar ini MENJELASKAN panjang-panjang
 * apa yang tidak boleh dikerjakan di dalamnya, jadi setiap audit statis atas
 * sumber MENTAH akan memerah pada kalimat yang justru melarangnya.
 */
function stripComments(source: string): string {
  return source
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/<!--[\s\S]*?-->/g, '')
    .replace(/^\s*\/\/.*$/gm, '')
}

/** Blok `<script setup>` sebuah SFC — tanpa template dan tanpa <style>. */
function scriptBlockOf(sfcSource: string): string {
  const start = sfcSource.indexOf('<script setup')
  const end = sfcSource.indexOf('</script>')

  expect(start, 'blok <script setup> tidak ditemukan').toBeGreaterThan(-1)
  expect(end, 'penutup </script> tidak ditemukan').toBeGreaterThan(start)

  return sfcSource.slice(start, end)
}

/** Params objek yang benar-benar diserahkan ke apiClient.get pada URL tertentu. */
function paramsFor(url: string): Record<string, unknown> {
  const call = getMock.mock.calls.find((item) => String(item[0]) === url)

  expect(call, `tidak ada permintaan ke ${url}`).toBeTruthy()

  return ((call?.[1] as { params?: Record<string, unknown> })?.params ?? {}) as Record<
    string,
    unknown
  >
}

/** Query string permintaan, dibaca lewat URLSearchParams — bukan lewat substring. */
function searchParamsFor(url: string): URLSearchParams {
  const params = paramsFor(url)
  const search = new URLSearchParams()

  for (const [key, value] of Object.entries(params)) {
    search.set(key, String(value))
  }

  return search
}

/* ------------------------------------------------------------------ */
/* Fixture — BENTUK RESPONS NYATA                                      */
/* ------------------------------------------------------------------ */

/**
 * Satu baris rekap kategoris. `value` null adalah KELOMPOKNYA SENDIRI dan
 * tidak pernah dibuang.
 */
function row(
  value: string | null,
  loadCount: number,
  netWeightTotal: number | null,
  netWeightLoadCount: number,
  netWeightAvg: number | null,
): Record<string, unknown> {
  return {
    value,
    load_count: loadCount,
    net_weight_total: netWeightTotal,
    net_weight_load_count: netWeightLoadCount,
    net_weight_avg: netWeightAvg,
  }
}

/**
 * URUTAN BARIS MILIK SERVER, dan urutan di sini SENGAJA bukan alfabetis dan
 * bukan urut jumlah: baris 'tidak tercatat' (value null) berada di POSISI
 * KEDUA. Repo yang mengurutkan ulang apa pun gagal di sini.
 *
 * Jumlah load_count barisnya 1 + 9 + 4 + 2 = 16, SENGAJA berbeda dari
 * loads.load_count 12 — konsistensi penjumlahan diuji di spec komponen atas
 * fixture yang memang konsisten; yang dibuktikan DI SINI adalah arah
 * pembacaannya: repo tidak menjumlah dan tidak mengoreksi.
 */
function breakdown(): Array<Record<string, unknown>> {
  return [
    row('Zpalm shell', 1, 4.5, 1, 4.5),
    row(null, 9, 21.25, 3, 7.08),
    row('Abu boiler', 4, null, 0, null),
    row('Mesocarp fibre', 2, 11.75, 1, 11.75),
  ]
}

/**
 * Payload /summary LENGKAP — bentuk SolidWasteDisposalReportService::
 * buildSummary(), termasuk `daily_total._note` yang ADA di payload nyata
 * (NOTE_DAILY_TOTAL) meski TIDAK tercantum pada success_schema tech spec.
 *
 * TIGA KUNCI TAMBAHAN yang TIDAK PERNAH diterbitkan server — `hourly`,
 * `busiest_hour`, `empty_hour_count` — ikut di sini SENGAJA (unit_test_case
 * 31): repo tidak boleh membuangnya (ia meneruskan body UTUH) dan tidak boleh
 * mengetiknya (yang tidak dapat ditulis tidak dapat salah dibaca).
 */
function summaryPayload(): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Sungai Rambutan' },
    production_line: { id: 'l-3', name: 'Line Tiga', code: 'L3' },
    period: {
      id: 'p-1',
      name: 'Periode Uji Solid Waste Disposal',
      start_date: PERIOD_START,
      end_date: PERIOD_END,
      status: 'open',
    },
    loads: {
      load_count: 12,
      net_weight_total: 37.5,
      // 37.5 / 5 = 7,5 — angka ini SENGAJA 9.9.
      net_weight_avg: 9.9,
      net_weight_load_count: 5,
      missing_net_weight_load_count: 7,
    },
    by_waste_type: breakdown(),
    by_source_station: breakdown(),
    by_disposal_site: breakdown(),
    by_purpose_end_use: breakdown(),
    compliance: {
      missing_gate_pass_count: 0,
      missing_seal_count: 3,
      _note: 'Prosa kepatuhan milik server.',
    },
    draft_load_count: 4,
    undated_load_count: 0,
    daily: [
      { date: DAY_B, load_count: 3, net_weight_total: 9.5, net_weight_load_count: 2, net_weight_avg: 4.75 },
      { date: DAY_A, load_count: 4, net_weight_total: null, net_weight_load_count: 0, net_weight_avg: null },
      { date: DAY_C, load_count: 2, net_weight_total: 6.25, net_weight_load_count: 1, net_weight_avg: 6.25 },
    ],
    daily_total: {
      load_count: 9,
      // BUKAN 9.5 + 6.25 = 15,75 — nilai payload apa adanya.
      net_weight_total: 55.0,
      net_weight_load_count: 3,
      net_weight_avg: 7.25,
      _note: 'Prosa total harian milik server.',
    },
    completeness: {
      days_in_period: 30,
      days_with_load: 4,
      days_counted: 11,
      // 4 / 11 = 36,36… — angka ini SENGAJA 41.3.
      percent: 41.3,
    },
    text_entries: {
      findings: [{ date: DAY_A, value: 'Ceceran di jalur keluar gerbang timbang.' }],
      remarks: [{ date: null, value: 'Tanpa tanggal kejadian, tetap sebuah catatan.' }],
    },
    _note_no_hourly: 'Prosa ketiadaan sebaran per jam milik server.',
    _note_shift_not_published: 'Prosa giliran kerja milik server.',
    _note_absences: 'Prosa ketiadaan downtime dan acuan batas milik server.',
    // KETIGA KUNCI YANG TIDAK PERNAH DITERBITKAN SERVER — lihat docblock.
    hourly: [{ hour: 7, load_count: 2 }],
    busiest_hour: 7,
    empty_hour_count: 22,
  }
}

const PERIOD_OPTIONS = [
  {
    id: 'p-1',
    name: 'Periode Uji Solid Waste Disposal',
    start_date: PERIOD_START,
    end_date: PERIOD_END,
    status: 'open',
    // DENGAN TANDA HUBUNG. Garis bawah di situ tidak cocok dengan satu baris
    // period_stations pun, dan server menjawab [] tanpa satu galat.
    station_type: 'solid-waste-disposal',
    station_type_label: 'Solid Waste Disposal',
  },
  {
    id: 'p-2',
    name: 'Periode Sebelumnya',
    start_date: PREV_START,
    end_date: PREV_END,
    // Periode TERTUTUP tetap terdaftar — status mengatur penulisan data,
    // bukan pembacaan laporan.
    status: 'closed',
    station_type: 'solid-waste-disposal',
    station_type_label: 'Solid Waste Disposal',
  },
]

const BUSINESS_UNIT_OPTIONS = [
  { id: 'bu-1', name: 'Mill Sungai Rambutan' },
  { id: 'bu-9', name: 'Mill Sungai Lilin' },
]

const LINE_OPTIONS = [
  { id: 'l-3', name: 'Line Tiga', code: 'L3' },
  { id: 'l-4', name: 'Line Empat', code: null },
]

const ADMIN_SCOPE: SolidWasteDisposalReportScope = {
  isAdmin: true,
  businessUnitId: 'bu-9',
  productionLineId: 'l-3',
}

/**
 * Cakupan tiga peran terikat mill yang SENGAJA menyerahkan businessUnitId
 * (dan bahkan nama kunci kawatnya) tanpa menyatakan isAdmin. Di JavaScript
 * yang sudah terkompilasi tipe tidak berlaku, jadi inilah satu-satunya tempat
 * maksud "gagal tertutup" itu dapat dicatat.
 */
function millBoundScope(): SolidWasteDisposalReportScope {
  return {
    productionLineId: 'l-3',
    businessUnitId: 'bu-9',
    business_unit_id: 'bu-9',
  } as unknown as SolidWasteDisposalReportScope
}

beforeEach(() => {
  getMock.mockReset()

  for (const spy of Object.values(localDbMocks)) {
    spy.mockReset()
  }
})

/* ================================================================== */
/* unit_test_case 1 — TEPAT LIMA ENDPOINT                              */
/* ================================================================== */

describe('lima endpoint, dan tidak ada yang keenam', () => {
  // unit_test_case 1
  it('kelima fungsi meminta TEPAT kelima URL yang sudah ada — nol endpoint baru', async () => {
    getMock.mockImplementation((url: string) => {
      if (url === SUMMARY_PATH) {
        return Promise.resolve({ data: summaryPayload() })
      }

      if (url === EXPORT_PATH) {
        return Promise.resolve({ data: new Blob(['a,b']) })
      }

      return Promise.resolve({ data: { data: [] } })
    })

    await fetchBusinessUnits()
    await fetchPeriods(ADMIN_SCOPE)
    await fetchLines(ADMIN_SCOPE)
    await fetchSummary({ periodId: 'p-1', productionLineId: 'l-3' })
    const exportUrl = buildExportUrl({ periodId: 'p-1', productionLineId: 'l-3' })

    // buildExportUrl TIDAK menyentuh jaringan — jalurnya diperiksa dari URL
    // yang dibentuknya, dan exportCsv di bawah membuktikan jalur yang sama
    // benar-benar diminta.
    await exportCsv({ periodId: 'p-1', productionLineId: 'l-3' })

    const urls = getMock.mock.calls.map((call) => String(call[0]))

    expect(urls).toEqual([
      '/api/solid-waste-disposal-reports/business-units/options',
      '/api/solid-waste-disposal-reports/periods',
      // DIPAKAI ULANG VERBATIM dari screen-135 — lihat docblock
      // SolidWasteDisposalReportController.
      '/api/production-lines/options-for-report',
      '/api/solid-waste-disposal-reports/summary',
      '/api/solid-waste-disposal-reports/export',
    ])
    expect(exportUrl.split('?')[0]).toBe('/api/solid-waste-disposal-reports/export')

    // Dan tidak ada URL KEENAM: lima pemanggilan jaringan untuk lima
    // endpoint, bukan enam.
    expect(getMock).toHaveBeenCalledTimes(5)
    expect(new Set(urls).size).toBe(5)

    // Nol path ber-prefix lain. Setiap URL ada di bawah salah satu dari dua
    // prefix yang memang dipakai layar ini.
    for (const url of urls) {
      expect(
        url.startsWith('/api/solid-waste-disposal-reports/') ||
          url === '/api/production-lines/options-for-report',
        `URL di luar kedua prefix layar ini: ${url}`,
      ).toBe(true)
    }
  })

  it('kelima konstanta jalur adalah SATU definisi masing-masing, dan tidak ada yang keenam', () => {
    expect(BUSINESS_UNITS_PATH).toBe('/api/solid-waste-disposal-reports/business-units/options')
    expect(PERIODS_PATH).toBe('/api/solid-waste-disposal-reports/periods')
    expect(SUMMARY_PATH).toBe('/api/solid-waste-disposal-reports/summary')
    expect(EXPORT_PATH).toBe('/api/solid-waste-disposal-reports/export')
    expect(LINES_PATH).toBe('/api/production-lines/options-for-report')

    // LINES_PATH TIDAK diduplikasi di bawah prefix solid-waste-disposal-reports:
    // ia endpoint screen-135 yang dipakai ulang verbatim.
    expect(LINES_PATH).not.toContain('solid-waste-disposal-reports')

    // Dan jalurnya memakai TANDA HUBUNG, bukan garis bawah: prefix
    // bergaris-bawah akan terkompilasi, terlihat benar, dan tidak pernah
    // cocok dengan satu rute pun.
    for (const path of [BUSINESS_UNITS_PATH, PERIODS_PATH, SUMMARY_PATH, EXPORT_PATH]) {
      expect(path).not.toContain('solid_waste_disposal')
    }
  })
})

/* ================================================================== */
/* unit_test_case 2 + 3 — business_unit_id HANYA untuk Admin            */
/* ================================================================== */

describe('business_unit_id hanya untuk Admin', () => {
  // unit_test_case 2
  it('walker rekursif atas KUNCI params tidak menemukan business_unit_id pada ketiga peran terikat mill', async () => {
    getMock.mockImplementation((url: string) =>
      Promise.resolve({ data: url === SUMMARY_PATH ? summaryPayload() : { data: [] } }),
    )

    // Ketiga peran terikat mill memakai cakupan yang SAMA — perannya hidup di
    // view, dan yang menentukan di repo adalah ketiadaan `isAdmin`. Ketiga
    // putaran tetap dijalankan terpisah supaya kegagalan menyebut perannya.
    for (const role of ['station-operator', 'supervisor', 'mill-management'] as const) {
      getMock.mockClear()

      await fetchPeriods(millBoundScope())
      await fetchSummary('p-1', millBoundScope())

      for (const call of getMock.mock.calls) {
        const params = (call[1] as { params?: unknown })?.params

        expect(keyNamesOf(params), role).not.toContain('business_unit_id')
        expect(keyNamesOf(params), role).not.toContain('businessUnitId')
      }

      expect(getMock, role).toHaveBeenCalledTimes(2)
    }
  })

  // unit_test_case 3
  it('business_unit_id dikirim untuk Admin pada /periods DAN /summary, dibaca dari URLSearchParams', async () => {
    getMock.mockImplementation((url: string) =>
      Promise.resolve({ data: url === SUMMARY_PATH ? summaryPayload() : { data: [] } }),
    )

    await fetchPeriods(ADMIN_SCOPE)
    await fetchSummary('p-1', ADMIN_SCOPE)

    expect(searchParamsFor(PERIODS_PATH).get('business_unit_id')).toBe('bu-9')
    expect(searchParamsFor(SUMMARY_PATH).get('business_unit_id')).toBe('bu-9')

    // Peran diganti ke station-operator dan kedua pemanggilan diulang: kunci
    // itu TIDAK LAGI ADA — bukan bernilai null, bukan string kosong.
    getMock.mockClear()

    await fetchPeriods({ productionLineId: 'l-3', businessUnitId: 'bu-9' })
    await fetchSummary('p-1', { productionLineId: 'l-3', businessUnitId: 'bu-9' })

    expect(searchParamsFor(PERIODS_PATH).has('business_unit_id')).toBe(false)
    expect(searchParamsFor(SUMMARY_PATH).has('business_unit_id')).toBe(false)
    expect(Object.keys(paramsFor(PERIODS_PATH))).toEqual([])
  })

  it('Admin yang BELUM memilih mill tidak mengirim kunci karangan dan tidak melempar sendiri', async () => {
    getMock.mockResolvedValue({ data: { data: [] } })

    await fetchPeriods({ isAdmin: true, businessUnitId: null })

    // Repo tidak mengarang nilai dan tidak melempar galatnya sendiri: server
    // menjawab 422 "Pilih mill terlebih dahulu", dan satu sumber kebenaran
    // itulah yang ditampilkan.
    expect(Object.keys(paramsFor(PERIODS_PATH))).toEqual([])
    expect(keyNamesOf(paramsFor(PERIODS_PATH))).not.toContain('business_unit_id')
  })

  it('fetchLines MENDELEGASIKAN ke endpoint screen-135, dengan business_unit_id hanya untuk Admin', async () => {
    getMock.mockResolvedValue({ data: { data: LINE_OPTIONS } })

    const asAdmin = await fetchLines(ADMIN_SCOPE)

    expect(asAdmin).toEqual(LINE_OPTIONS)
    expect(String(getMock.mock.calls[0][0])).toBe(LINES_PATH)
    expect(searchParamsFor(LINES_PATH).get('business_unit_id')).toBe('bu-9')

    getMock.mockClear()

    await fetchLines(millBoundScope())

    expect(Object.keys(paramsFor(LINES_PATH))).toEqual([])
    expect(keyNamesOf(paramsFor(LINES_PATH))).not.toContain('business_unit_id')
  })
})

/* ================================================================== */
/* Parameter permintaan + unit_test_case 45 (URL ekspor)                */
/* ================================================================== */

describe('parameter permintaan', () => {
  it('fetchSummary mengirim TEPAT period_id dan production_line_id untuk peran terikat mill', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    await fetchSummary('p-1', { productionLineId: 'l-3' })

    expect(paramsFor(SUMMARY_PATH)).toEqual({ period_id: 'p-1', production_line_id: 'l-3' })
  })

  it('kedua bentuk pemanggilan menghasilkan parameter yang SAMA PERSIS', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    await fetchSummary('p-1', { productionLineId: 'l-3' })
    const positional = paramsFor(SUMMARY_PATH)

    getMock.mockClear()

    await fetchSummary({ periodId: 'p-1', productionLineId: 'l-3' })

    expect(paramsFor(SUMMARY_PATH)).toEqual(positional)
  })

  it('line kosong TIDAK menjadi parameter kosong — server menjawab 422 tentang parameter yang HILANG', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    await fetchSummary('p-1', { productionLineId: null })

    const params = paramsFor(SUMMARY_PATH)

    expect(Object.keys(params)).toEqual(['period_id'])
    expect(keyNamesOf(params)).not.toContain('production_line_id')

    getMock.mockClear()

    await fetchSummary('p-1', { productionLineId: '' })

    expect(keyNamesOf(paramsFor(SUMMARY_PATH))).not.toContain('production_line_id')
  })

  // unit_test_case 45
  it('unit_test_case 45: URL ekspor ber-pathname tepat, dengan format=csv dan kedua parameter lingkup', () => {
    const url = buildExportUrl({ periodId: 'p-1', productionLineId: 'l-3' })
    const [pathname, query] = url.split('?')
    const search = new URLSearchParams(query)

    expect(pathname).toBe('/api/solid-waste-disposal-reports/export')
    // KESETARAAN ATAS TIAP NILAI PARAM, bukan substring URL.
    expect(search.get('period_id')).toBe('p-1')
    expect(search.get('production_line_id')).toBe('l-3')
    expect(search.get('format')).toBe('csv')
    expect(search.get('format')).not.toBe('xlsx')
    expect([...search.keys()].sort()).toEqual(['format', 'period_id', 'production_line_id'])
  })

  it('exportParams dan exportCsv memakai SUSUNAN YANG SAMA, dan responseType-nya blob', async () => {
    getMock.mockResolvedValue({ data: new Blob(['a,b']) })

    const expected = exportParams({ periodId: 'p-1', productionLineId: 'l-3' })

    await exportCsv({ periodId: 'p-1', productionLineId: 'l-3' })

    const call = getMock.mock.calls[0]

    expect(String(call[0])).toBe(EXPORT_PATH)
    expect((call[1] as { params: unknown }).params).toEqual(expected)
    expect((call[1] as { responseType: string }).responseType).toBe('blob')
    expect(expected.format).toBe('csv')
  })

  it('TIDAK ADA jalur kode yang dapat menyusun format selain csv', () => {
    // Ketiga cakupan yang paling mungkin dipakai untuk menyelipkan format
    // lain — termasuk yang menuliskan kuncinya langsung.
    const attempts = [
      { periodId: 'p-1', productionLineId: 'l-3' },
      { periodId: 'p-1', productionLineId: 'l-3', format: 'xlsx' },
      { periodId: 'p-1', productionLineId: 'l-3', isAdmin: true, businessUnitId: 'bu-9', format: 'xlsx' },
    ]

    for (const attempt of attempts) {
      const params = exportParams(attempt as never)

      expect(params.format).toBe('csv')
    }
  })

  it('AUDIT STATIS: nol kemunculan xlsx/excel sebagai NILAI pada kode repo maupun kode view', () => {
    const repoCode = stripComments(
      readFileSync(resolve(process.cwd(), 'src/services/solidWasteDisposalReportRepo.ts'), 'utf-8'),
    )
    // DILINGKUP KE BLOK SKRIP: prosa template layar ini MEMANG menyebut
    // '.xlsx' untuk menerangkan bahwa tombolnya tidak ada — dan audit yang
    // menyisir seluruh SFC akan memerah pada kalimat yang justru
    // membuktikannya.
    const viewCode = stripComments(
      scriptBlockOf(
        readFileSync(resolve(process.cwd(), 'src/views/LaporanSolidWasteDisposalView.vue'), 'utf-8'),
      ),
    )

    for (const [label, code] of [
      ['repo', repoCode],
      ['view', viewCode],
    ] as const) {
      expect(code.toLowerCase(), label).not.toContain('xlsx')
      expect(code.toLowerCase(), label).not.toContain('excel')
    }

    // Dan 'csv' memang ada di repo sebagai NILAI yang dikirim.
    expect(repoCode).toContain("format: 'csv'")
  })

  it('fetchPeriods TIDAK mengirim production_line_id — periode milik MILL, bukan milik line', async () => {
    getMock.mockResolvedValue({ data: { data: PERIOD_OPTIONS } })

    await fetchPeriods({ productionLineId: 'l-3' })

    expect(Object.keys(paramsFor(PERIODS_PATH))).toEqual([])
    expect(keyNamesOf(paramsFor(PERIODS_PATH))).not.toContain('production_line_id')
  })

  it('daftar periode diteruskan APA ADANYA, dalam urutan yang sama, termasuk periode tertutup', async () => {
    getMock.mockResolvedValue({ data: { data: PERIOD_OPTIONS } })

    const periods = await fetchPeriods()

    expect(periods).toEqual(PERIOD_OPTIONS)
    expect(periods.map((period) => period.id)).toEqual(['p-1', 'p-2'])
    expect(periods.map((period) => period.status)).toEqual(['open', 'closed'])
  })

  it('daftar kosong adalah jawaban yang SAH pada ketiga daftar, bukan galat', async () => {
    getMock.mockResolvedValue({ data: { data: [] } })

    await expect(fetchPeriods()).resolves.toEqual([])
    await expect(fetchBusinessUnits()).resolves.toEqual([])
    await expect(fetchLines()).resolves.toEqual([])
  })

  it('pemilih mill diambil dari endpoint ADMIN SAJA, dan bentuk barisnya diteruskan apa adanya', async () => {
    getMock.mockResolvedValue({ data: { data: BUSINESS_UNIT_OPTIONS } })

    await expect(fetchBusinessUnits()).resolves.toEqual(BUSINESS_UNIT_OPTIONS)
    expect(String(getMock.mock.calls[0][0])).toBe(BUSINESS_UNITS_PATH)
  })
})

/* ================================================================== */
/* unit_test_case 4 (bagian repo) + 17-23 + 31-32 — terusan utuh        */
/* ================================================================== */

describe('body /summary diteruskan utuh', () => {
  it('hasil parse DEEP-EQUAL dengan fixture lengkap — nol kunci dibuang, nol bawaan disuntikkan', async () => {
    const payload = summaryPayload()

    getMock.mockResolvedValue({ data: payload })

    await expect(fetchSummary('p-1', { productionLineId: 'l-3' })).resolves.toEqual(payload)
  })

  it('walker rekursif memastikan SETIAP jalur kunci fixture ada pada hasil — tanpa kunci tambahan', async () => {
    const payload = summaryPayload()

    getMock.mockResolvedValue({ data: payload })

    const result = await fetchSummary('p-1', { productionLineId: 'l-3' })

    expect(keyPathsOf(result).sort()).toEqual(keyPathsOf(payload).sort())
  })

  it('daily_total._note IKUT diteruskan meski TIDAK ada di success_schema tech spec', async () => {
    const payload = summaryPayload()

    getMock.mockResolvedValue({ data: payload })

    const result = await fetchSummary('p-1', { productionLineId: 'l-3' })

    // Ia ADA di payload nyata (SolidWasteDisposalReportService::
    // NOTE_DAILY_TOTAL) dan dirender VERBATIM oleh view. Repo yang memaku
    // daftar kuncinya pada success_schema akan membuangnya diam-diam.
    expect(result.daily_total?._note).toBe('Prosa total harian milik server.')
    expect(keyPathsOf(result)).toContain('daily_total._note')
  })

  it('KEEMPAT rekap kategoris diteruskan dengan urutan baris payload APA ADANYA', async () => {
    const payload = summaryPayload()

    getMock.mockResolvedValue({ data: payload })

    const result = await fetchSummary('p-1', { productionLineId: 'l-3' })

    for (const key of [
      'by_waste_type',
      'by_source_station',
      'by_disposal_site',
      'by_purpose_end_use',
    ] as const) {
      // ARRAY BERURUT, bukan himpunan: pengurutan ulang apa pun di repo gagal
      // di sini, termasuk yang memindahkan baris null ke akhir.
      expect(result[key]?.map((item) => item.value), key).toEqual([
        'Zpalm shell',
        null,
        'Abu boiler',
        'Mesocarp fibre',
      ])
    }
  })

  it('null tetap null — nol koersi menjadi 0, nol koersi menjadi string kosong', async () => {
    getMock.mockResolvedValue({
      data: {
        loads: {
          load_count: 8,
          net_weight_total: null,
          net_weight_avg: null,
          net_weight_load_count: 0,
          missing_net_weight_load_count: 8,
        },
        completeness: { days_in_period: 30, days_with_load: 0, days_counted: 0, percent: null },
        by_waste_type: [row(null, 8, null, 0, null)],
        daily_total: { load_count: 0, net_weight_total: null, net_weight_load_count: 0, net_weight_avg: null },
      },
    })

    const result = await fetchSummary('p-1', { productionLineId: 'l-3' })

    expect(result.loads?.net_weight_total).toBeNull()
    expect(result.loads?.net_weight_avg).toBeNull()
    expect(result.completeness?.percent).toBeNull()
    expect(result.by_waste_type?.[0].value).toBeNull()
    expect(result.by_waste_type?.[0].net_weight_total).toBeNull()
    expect(result.daily_total?.net_weight_avg).toBeNull()

    // Dan BUKAN 0: "tidak ada yang ditimbang" bukan "hasilnya nol".
    expect(result.loads?.net_weight_total).not.toBe(0)
    expect(result.completeness?.percent).not.toBe(0)
  })

  it('respons BERPEMBUNGKUS `data` dan respons DATAR menghasilkan hasil yang sama', async () => {
    const payload = summaryPayload()

    getMock.mockResolvedValue({ data: payload })
    const flat = await fetchSummary('p-1', { productionLineId: 'l-3' })

    getMock.mockResolvedValue({ data: { data: payload } })
    const wrapped = await fetchSummary('p-1', { productionLineId: 'l-3' })

    expect(wrapped).toEqual(flat)
  })

  it('payload yang tidak dikenali menjadi objek kosong, bukan lemparan', async () => {
    for (const payload of ['bukan objek', 42, null, undefined]) {
      getMock.mockResolvedValue({ data: payload })

      await expect(fetchSummary('p-1', { productionLineId: 'l-3' })).resolves.toEqual({})
    }
  })

  // unit_test_case 31 + 32 (bagian repo)
  it('kunci hourly yang DIBAWA payload tetap diteruskan, tetapi repo tidak pernah MENGETIKNYA', async () => {
    const payload = summaryPayload()

    getMock.mockResolvedValue({ data: payload })

    const result = await fetchSummary('p-1', { productionLineId: 'l-3' })

    // (a) Diteruskan: membuang kunci dan tidak membaca kunci adalah dua hal
    // yang berbeda, dan yang pertama menyembunyikan putaran server kelak.
    expect(keyNamesOf(result)).toContain('hourly')
    expect(keyNamesOf(result)).toContain('busiest_hour')
    expect(keyNamesOf(result)).toContain('empty_hour_count')

    // (b) Tidak diketik: yang tidak dapat ditulis tidak dapat salah dibaca.
    const repoCode = stripComments(
      readFileSync(resolve(process.cwd(), 'src/services/solidWasteDisposalReportRepo.ts'), 'utf-8'),
    )

    // Diasersi atas NAMA KUNCI yang DIDEKLARASIKAN, bukan atas substring:
    // `_note_no_hourly` dan `_note_shift_not_published` MEMANG diketik repo —
    // keduanya prosa server yang dirender utuh — dan keduanya memuat kata
    // 'hourly' dan 'shift' di dalam namanya sendiri.
    for (const key of ['hourly', 'busiest_hour', 'empty_hour_count', 'shift']) {
      const declaration = new RegExp(`(?<![A-Za-z0-9_])${key}\\s*\\??\\s*:`)

      expect(declaration.test(repoCode), `repo mengetik kunci ${key}`).toBe(false)
    }

    // 'downtime' dan 'target' tidak muncul sama sekali, bahkan sebagai
    // bagian dari nama lain.
    for (const key of ['downtime', 'target']) {
      expect(repoCode, `repo menyebut ${key}`).not.toContain(key)
    }
  })
})

/* ================================================================== */
/* unit_test_case 17-23 (bagian repo) — nol aritmetika                  */
/* ================================================================== */

describe('repo tidak menghitung apa pun', () => {
  it('net_weight_avg di luar hasil pembagian total/penyebut diteruskan APA ADANYA', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const result = await fetchSummary('p-1', { productionLineId: 'l-3' })

    // 37.5 / 5 = 7,5. Nilai payload 9.9 MENANG.
    expect(result.loads?.net_weight_avg).toBe(9.9)
    expect(result.loads?.net_weight_avg).not.toBe(7.5)
    expect(result.loads?.net_weight_total).toBe(37.5)
    expect(result.loads?.net_weight_load_count).toBe(5)
  })

  it('load_count dan missing_net_weight_load_count dibaca, bukan diturunkan satu dari lainnya', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const result = await fetchSummary('p-1', { productionLineId: 'l-3' })

    // 12 − 5 = 7 kebetulan cocok di fixture ini; yang dibuktikan adalah
    // bahwa penjumlahan BARIS REKAP (16) tidak mengoreksi load_count (12).
    expect(result.loads?.load_count).toBe(12)
    expect(result.loads?.missing_net_weight_load_count).toBe(7)

    const recapSum = (result.by_waste_type ?? []).reduce((total, item) => total + item.load_count, 0)

    expect(recapSum).toBe(16)
    expect(result.loads?.load_count).not.toBe(recapSum)
  })

  it('daily_total dibaca dari payload, bukan dijumlahkan dari daily[]', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const result = await fetchSummary('p-1', { productionLineId: 'l-3' })
    const columnSum = (result.daily ?? []).reduce(
      (total, item) => total + (item.net_weight_total ?? 0),
      0,
    )

    expect(result.daily_total?.net_weight_total).toBe(55)
    expect(columnSum).toBeCloseTo(15.75, 5)
    expect(result.daily_total?.net_weight_total).not.toBeCloseTo(columnSum, 5)
  })

  it('completeness.percent dibaca dari payload, bukan dibagi dari days_with_load/days_counted', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const result = await fetchSummary('p-1', { productionLineId: 'l-3' })

    expect(result.completeness?.percent).toBe(41.3)
    expect(result.completeness?.percent).not.toBeCloseTo((4 / 11) * 100, 2)
  })

  it('urutan daily[] tidak pernah diurutkan ulang, meski payload-nya tidak urut tanggal', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const result = await fetchSummary('p-1', { productionLineId: 'l-3' })

    expect(result.daily?.map((item) => item.date)).toEqual([DAY_B, DAY_A, DAY_C])
  })

  it('AUDIT STATIS: tidak ada aritmetika maupun pengurutan atas angka laporan di berkas repo', () => {
    const code = stripComments(
      readFileSync(resolve(process.cwd(), 'src/services/solidWasteDisposalReportRepo.ts'), 'utf-8'),
    )

    for (const forbidden of ['.reduce(', '.sort(', '.toSorted(', 'Math.round', 'Math.max', 'Math.min']) {
      expect(code, `repo memakai ${forbidden}`).not.toContain(forbidden)
    }
  })
})

/* ================================================================== */
/* unit_test_case 5 — BACA SAJA, NOL TULISAN                            */
/* ================================================================== */

describe('layar baca saja: nol tulisan', () => {
  it('seluruh metode tulis localDb menerima NOL pemanggilan sesudah seluruh alur repo', async () => {
    getMock.mockImplementation((url: string) => {
      if (url === SUMMARY_PATH) {
        return Promise.resolve({ data: summaryPayload() })
      }

      if (url === EXPORT_PATH) {
        return Promise.resolve({ data: new Blob(['a,b']) })
      }

      return Promise.resolve({ data: { data: [] } })
    })

    await fetchBusinessUnits()
    await fetchPeriods(ADMIN_SCOPE)
    await fetchLines(ADMIN_SCOPE)
    await fetchSummary('p-1', ADMIN_SCOPE)
    await exportCsv('p-1', ADMIN_SCOPE)

    for (const [name, spy] of Object.entries(localDbMocks)) {
      expect(spy, `localDb.${name} dipanggil`).not.toHaveBeenCalled()
    }
  })

  it('apiClient yang di-mock hanya mengekspos get — nol jalur POST/PUT/PATCH/DELETE', async () => {
    const apiClient = (await import('@/services/apiClient')).default

    expect(Object.keys(apiClient)).toEqual(['get'])
  })

  it('AUDIT STATIS: repo tidak menyentuh localDb, localStorage, sqlite, maupun metode tulis HTTP', () => {
    const code = stripComments(
      readFileSync(resolve(process.cwd(), 'src/services/solidWasteDisposalReportRepo.ts'), 'utf-8'),
    )

    for (const forbidden of [
      'localDb',
      'localStorage',
      'sqlite',
      'apiClient.post',
      'apiClient.put',
      'apiClient.patch',
      'apiClient.delete',
    ]) {
      expect(code, `repo menyentuh ${forbidden}`).not.toContain(forbidden)
    }

    // Dan satu-satunya metode HTTP yang dipakai adalah get.
    expect(code.match(/apiClient\.get\(/g)?.length).toBeGreaterThan(0)
  })
})

/* ================================================================== */
/* unit_test_case 41-44 (bagian repo) — galat APA ADANYA                */
/* ================================================================== */

describe('galat diteruskan apa adanya', () => {
  const NETWORK_ERROR = { message: 'Tidak dapat terhubung ke server.' }
  const UNAUTHENTICATED_ERROR = { message: 'Unauthenticated.', status: 401 }
  const FORBIDDEN_ERROR = { message: 'Anda tidak memiliki akses untuk aksi ini.', status: 403 }
  const NOT_FOUND_ERROR = { message: 'Periode Pelaporan tidak ditemukan.', status: 404 }
  // DISALIN dari SolidWasteDisposalReportService ± baris 445.
  const NO_MILL_ERROR = {
    message: 'Akun Anda belum terhubung ke mill. Hubungi Admin.',
    status: 422,
    errors: { business_unit_id: ['Akun Anda belum terhubung ke mill. Hubungi Admin.'] },
  }

  it('401 diteruskan dengan status-nya — view yang mengalihkan ke Login', async () => {
    getMock.mockRejectedValue(UNAUTHENTICATED_ERROR)

    await expect(fetchSummary('p-1', { productionLineId: 'l-3' })).rejects.toEqual(
      UNAUTHENTICATED_ERROR,
    )
  })

  it('403 line milik mill lain diteruskan dengan status-nya — view yang meminta memilih line', async () => {
    getMock.mockRejectedValue(FORBIDDEN_ERROR)

    await expect(fetchSummary('p-1', { productionLineId: 'l-99' })).rejects.toEqual(FORBIDDEN_ERROR)
  })

  it('403 pemilih mill untuk peran non-admin diteruskan apa adanya', async () => {
    getMock.mockRejectedValue(FORBIDDEN_ERROR)

    await expect(fetchBusinessUnits()).rejects.toEqual(FORBIDDEN_ERROR)
  })

  it('404 periode tidak ditemukan diteruskan dengan status-nya, bukan diubah menjadi 401', async () => {
    getMock.mockRejectedValue(NOT_FOUND_ERROR)

    await expect(fetchSummary('p-404', { productionLineId: 'l-3' })).rejects.toEqual(
      NOT_FOUND_ERROR,
    )
  })

  it('422 akun tanpa mill diteruskan beserta medan errors-nya, tanpa ditafsirkan', async () => {
    getMock.mockRejectedValue(NO_MILL_ERROR)

    await expect(fetchPeriods()).rejects.toEqual(NO_MILL_ERROR)
  })

  it('galat jaringan TANPA status diteruskan tanpa status — itu pembeda cabangnya', async () => {
    getMock.mockRejectedValue(NETWORK_ERROR)

    await expect(fetchSummary('p-1', { productionLineId: 'l-3' })).rejects.toEqual(NETWORK_ERROR)
    await expect(fetchSummary('p-1', { productionLineId: 'l-3' })).rejects.not.toHaveProperty(
      'status',
    )
  })

  it('galat /periods diteruskan, tidak diganti daftar kosong', async () => {
    getMock.mockRejectedValue(NETWORK_ERROR)

    await expect(fetchPeriods()).rejects.toEqual(NETWORK_ERROR)
  })

  it('galat /lines diteruskan dari productionLineRepo tanpa disamarkan', async () => {
    getMock.mockRejectedValue(NETWORK_ERROR)

    await expect(fetchLines()).rejects.toEqual(NETWORK_ERROR)
  })

  it('galat ekspor 422 diteruskan APA ADANYA, dengan SATU permintaan', async () => {
    const EXPORT_LIMIT_ERROR = {
      message: 'Ekspor melebihi 50.000 baris muatan. Persempit rentang periode, lalu coba lagi.',
      status: 422,
    }

    getMock.mockRejectedValue(EXPORT_LIMIT_ERROR)

    await expect(exportCsv('p-1', { productionLineId: 'l-3' })).rejects.toEqual(EXPORT_LIMIT_ERROR)
    expect(getMock).toHaveBeenCalledTimes(1)
  })

  it('tidak ada try/catch dan tidak ada kelas galat khusus di berkas repo', () => {
    const code = stripComments(
      readFileSync(resolve(process.cwd(), 'src/services/solidWasteDisposalReportRepo.ts'), 'utf-8'),
    )

    expect(code).not.toContain('catch')
    expect(code).not.toContain('class ')
  })
})

/* ================================================================== */
/* Penyimpanan berkas CSV                                               */
/* ================================================================== */

describe('penyimpanan berkas CSV', () => {
  it('saveCsvFile memakai anchor + object URL — POLA YANG SAMA dengan kembaran sebelumnya', () => {
    const createObjectURL = vi.fn(() => 'blob:swd')
    const revokeObjectURL = vi.fn()

    vi.stubGlobal('URL', { ...URL, createObjectURL, revokeObjectURL })

    const clickSpy = vi.fn()
    const anchor = document.createElement('a')

    anchor.click = clickSpy

    const createElementSpy = vi.spyOn(document, 'createElement').mockReturnValue(anchor)
    const appendSpy = vi.spyOn(document.body, 'appendChild')
    const removeSpy = vi.spyOn(document.body, 'removeChild')

    saveCsvFile(new Blob(['a,b']), 'laporan-solid-waste-disposal_periode_line.csv')

    expect(createObjectURL).toHaveBeenCalledTimes(1)
    expect(anchor.download).toBe('laporan-solid-waste-disposal_periode_line.csv')
    expect(anchor.href).toContain('blob:swd')
    expect(clickSpy).toHaveBeenCalledTimes(1)
    expect(appendSpy).toHaveBeenCalledTimes(1)
    expect(removeSpy).toHaveBeenCalledTimes(1)
    // Object URL DILEPAS: satu unduhan tidak boleh menahan blob di memori
    // WebView selama sesi berjalan.
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:swd')

    createElementSpy.mockRestore()
    appendSpy.mockRestore()
    removeSpy.mockRestore()
    vi.unstubAllGlobals()
  })
})

/* ================================================================== */
/* Bentuk modul                                                         */
/* ================================================================== */

describe('bentuk modul', () => {
  it('mengekspor TEPAT delapan anggota pada objek repo, dan fetchBusinessUnits ADA di antaranya', () => {
    expect(Object.keys(solidWasteDisposalReportRepo).sort()).toEqual([
      'buildExportUrl',
      'exportCsv',
      'exportParams',
      'fetchBusinessUnits',
      'fetchLines',
      'fetchPeriods',
      'fetchSummary',
      'saveCsvFile',
    ])
  })

  it('tidak ada satu pun anggota yang menulis ke server — layar ini BACAAN SAJA', () => {
    // saveCsvFile DIKECUALIKAN dengan sengaja dan bukan dilemahkan: ia
    // menyimpan berkas ke PERANGKAT lewat anchor + object URL, tanpa satu
    // permintaan HTTP — dibuktikan oleh test 'penyimpanan berkas CSV' di
    // atas, yang mengasersi createObjectURL/click/revokeObjectURL dan nol
    // pemanggilan apiClient.
    const serverFacing = Object.keys(solidWasteDisposalReportRepo).filter(
      (name) => name !== 'saveCsvFile',
    )

    expect(serverFacing).toHaveLength(7)

    for (const name of serverFacing) {
      expect(name).not.toMatch(/^(save|create|update|delete|store|submit|post|put|patch)/)
    }

    expect(Object.keys(solidWasteDisposalReportRepo)).toContain('saveCsvFile')
  })

  it('fetchLines MENDELEGASIKAN, dan tidak menduplikasi URL-nya di berkas ini', () => {
    const code = stripComments(
      readFileSync(resolve(process.cwd(), 'src/services/solidWasteDisposalReportRepo.ts'), 'utf-8'),
    )

    expect(code).toContain('productionLineRepo.fetchProductionLinesForReport')
    // SATU definisi URL-nya: konstanta LINES_PATH, bukan string kedua yang
    // ditulis ulang di dalam fetchLines.
    expect(code.match(/'\/api\/production-lines\/options-for-report'/g)?.length).toBe(1)
  })
})
