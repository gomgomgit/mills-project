/**
 * processWaterReportRepo.spec.ts — screen-161--laporan-process-water-mobile /
 * usecase-167--laporan-process-water-mobile.
 *
 * Bagian SISI KLIEN dari 58 `unit_test_cases` tech spec screen-161 yang
 * menyangkut BENTUK PERMINTAAN dan KESETIAAN TERUSAN: kasus 1-7, separuh repo
 * dari 8-10 (galat diteruskan APA ADANYA; penafsirannya milik view), dan
 * separuh repo dari 57. Keputusan LAYAR atas jawaban repo ada di
 * LaporanProcessWaterView.spec.ts; kebenaran ANGKA-nya sudah dibuktikan
 * terhadap ProcessWaterReportService di backend ketika screen-160 dibangun —
 * layar ini tidak menyentuh satu berkas backend pun.
 *
 * Kembaran KEEMPAT BELAS dari pola yang sama, dari sterilizerReportRepo.spec.ts
 * (screen-135) sampai engineRoomReportRepo.spec.ts (screen-159).
 *
 * ────────────────────────────────────────────────────────────────────────
 * ENAM KLAIM YANG HANYA BERKAS INI DAPAT MENCATATNYA
 * ────────────────────────────────────────────────────────────────────────
 * 1. TEPAT EMPAT ENDPOINT, DAN TIDAK ADA YANG KELIMA. Ketiganya di bawah
 *    prefix /api/process-water-reports/ plus /api/production-lines/
 *    options-for-report yang dipakai ULANG verbatim dari screen-135. Nol
 *    endpoint baru, nol entri api-index baru, nol berkas backend disentuh.
 *
 * 2. `business_unit_id` TIDAK PERNAH ADA DI KAWAT, apa pun yang diserahkan
 *    pemanggil. Perlindungannya STRUKTURAL — tidak ada satu pun jalur kode
 *    yang dapat mengirimkannya, bukan sebuah penjagaan yang menolaknya —
 *    jadi test inilah satu-satunya yang mencatat maksudnya. Diasersi atas
 *    NAMA KUNCI lewat walker rekursif, bukan atas teks URL: kunci yang
 *    muncul bernilai undefined lolos dari asersi substring.
 *
 * 3. `format=csv` DIKIRIM EKSPLISIT, dan tidak ada jalur kode yang dapat
 *    menyusun format lain. Endpoint-nya MEMANG mendukung 'excel'; WebView
 *    ponsel tidak membuka .xlsx, jadi nilai itu tidak muncul di repo maupun
 *    di view — diasersi juga lewat audit statis atas sumber keduanya.
 *
 * 4. BODY /summary DITERUSKAN UTUH. Nol kunci dibuang, nol bawaan
 *    disuntikkan, nol aritmetika. Diasersi dua arah: deep-equal dengan
 *    fixture DAN walker rekursif atas setiap KUNCI fixture — kunci yang
 *    hilang tidak boleh tak terlihat sampai seseorang membacanya di layar.
 *
 * 5. fetchLines() MENDELEGASIKAN ke productionLineRepo.
 *    fetchProductionLinesForReport(null). Berkas ini karena itu me-mock
 *    apiClient SAJA dan memakai productionLineRepo YANG SUNGGUHAN, sehingga
 *    URL yang terkumpul adalah URL yang benar-benar dikirim — mock atas repo
 *    line akan membuat kasus 1 dan 2 hijau tanpa menyentuh satu URL pun.
 *
 * 6. NOL TULISAN KE localDb. Ketiadaan mode luring DISENGAJA: hasil laporan
 *    tidak pernah disimpan, jadi keadaan luring dinyatakan apa adanya
 *    alih-alih ditutupi angka basi.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DIVERGENSI SPEC-LAWAN-PAYLOAD YANG DICATAT DI SINI
 * ────────────────────────────────────────────────────────────────────────
 * DIVERGENSI 1 — SATUAN TIDAK ADA DI PAYLOAD. unit_test_cases 6, 22-26
 * berbunyi "satuan dicetak dari field `unit` respons". Payload Process Water
 * TIDAK MENERBITKAN `unit` maupun `label` sama sekali
 * (ProcessWaterReportService::metricsOf menerbitkan min/avg/max/
 * reading_count/group dan tidak ada yang keenam). Satuannya datang dari tabel
 * METRIC_META di LaporanProcessWaterView.vue, yang disalin persis dari
 * $metrikMeta pada blade screen-160; nilai server MENANG bila kelak
 * diterbitkan. Yang diasersi DI SINI karena itu dua hal sekaligus: (a) repo
 * meneruskan `unit` APA ADANYA bila payload membawanya — termasuk null, '°C'
 * dan 'm³/jam' tanpa normalisasi menjadi 'C'/'m3/jam'; dan (b) repo TIDAK
 * MENUMBUHKAN `unit` sendiri ketika payload tidak membawanya. Asersi atas
 * satuan yang TERCETAK ada di LaporanProcessWaterView.spec.ts.
 *
 * DIVERGENSI 2 — spec kasus 11-18 menamai penjagaan Production Line dan
 * resolusi line sebagai bagian "repo". Keduanya hidup di
 * LaporanProcessWaterView.vue dan diuji di spec komponen. Yang diasersi di
 * berkas ini adalah akibatnya pada kawat: nilai line yang kosong TIDAK PERNAH
 * dikirim sebagai parameter kosong.
 *
 * DIVERGENSI 3 — spec menamai `fetchPeriods`/`fetchSummary` sebagai pembawa
 * `inspector_recap` berbentuk DAFTAR. Server menerbitkannya sebagai OBJEK
 * {entries, not_recorded, recorded_slots}. Repo mengetik KEDUANYA dan
 * meneruskan apa adanya; view yang menormalkannya. Kedua bentuk diuji di
 * sini.
 *
 * ────────────────────────────────────────────────────────────────────────
 * FIXTURE-NYA SENGAJA BERTENTANGAN DENGAN DIRINYA SENDIRI
 * ────────────────────────────────────────────────────────────────────────
 * Klaim pokok layar ini adalah "PONSEL TIDAK MENGHITUNG APA PUN", dan klaim
 * itu hanya dapat dibuktikan oleh payload yang MUSTAHIL dihasilkan
 * perhitungan:
 *
 *   - coverage_percent 77.7 padahal 96/480 = 20,0
 *   - expected_slots 480 padahal unit_count 2 × days_counted 7 × 24 = 336
 *   - alum_dosing_kgh avg 9.9 di LUAR rentang min 1.8 .. max 3.1
 *   - kesembilan reading_count BERBEDA-BEDA, dan SELURUHNYA lebih kecil
 *     daripada coverage.filled_slots 96 — konsekuensi aturan SEBELAS kolom,
 *     bukan selisih yang perlu dikoreksi
 *   - daily_total.reading_rows 777 padahal baris hariannya 12 + 9
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
 * ditambahkan di sini karena unit_test_case 7 menamai keempatnya — dan sebuah
 * repo yang kelak menumbuhkan cache lewat salah satu nama itu akan tertangkap
 * tanpa berkas ini perlu disunting. Mock-nya berlaku meski modulnya tidak
 * pernah diimpor; itulah sebabnya audit statis di bawah MENDAMPINGINYA
 * alih-alih diganti olehnya.
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

import processWaterReportRepo, {
  buildExportUrl,
  EXPORT_PATH,
  exportCsv,
  exportParams,
  fetchLines,
  fetchPeriods,
  fetchSummary,
  LINES_PATH,
  PERIODS_PATH,
  saveCsvFile,
  SUMMARY_PATH,
  type ProcessWaterReportScope,
} from '@/services/processWaterReportRepo'

/* ------------------------------------------------------------------ */
/* Tanggal relatif                                                     */
/* ------------------------------------------------------------------ */

function isoDaysFromNow(offsetDays: number): string {
  return new Date(Date.now() + offsetDays * 86_400_000).toISOString().slice(0, 10)
}

const PERIOD_START = isoDaysFromNow(-10)
const PERIOD_END = isoDaysFromNow(-1)
const PREV_START = isoDaysFromNow(-30)
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
 * ketiadaannya — dan layar ini penuh prosa semacam itu.
 *
 * Tidak ada walker semacam ini di keempat belas spec saudaranya (keduanya
 * memakai JSON.stringify), jadi ia ditulis di sini dan di spec komponen layar
 * ini dengan bentuk yang sama persis.
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
 * Setiap JALUR kunci (dot-path) di dalam sebuah struktur. Dipakai kasus 5:
 * deep-equal membuktikan nilainya sama, dan daftar jalur membuktikan tidak
 * satu kunci pun HILANG — dua pertanyaan yang berbeda, dan yang kedua tetap
 * berguna bila kelak sebuah kunci kebetulan bernilai undefined di kedua sisi.
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
 * assertStringContainsString — pembungkus yang MENERIMA PESAN.
 *
 * `expect(x).toContain(needle, 'pesan')` di Vitest bersifat VARIADIK atas
 * needle: argumen kedua diperlakukan sebagai NEEDLE KEDUA, bukan sebagai
 * pesan, sehingga asersinya diam-diam berubah arti. unit_test_case 4 menuntut
 * pesan, jadi pesannya ditaruh di deskripsi `expect()` — satu-satunya tempat
 * yang benar.
 */
function assertStringContainsString(needle: string, haystack: string, message: string): void {
  expect(haystack, message).toContain(needle)
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

/* ------------------------------------------------------------------ */
/* Kesembilan kolom ukur — urutan NUMERIC_METRICS                      */
/* ------------------------------------------------------------------ */

const METRIC_COLUMNS = [
  'raw_water_flow_m3h',
  'clarified_water_flow_m3h',
  'softener_inlet_ph',
  'softener_outlet_hardness_ppm',
  'alum_dosing_kgh',
  'polymer_dosing_gh',
  'boiler_feed_tank_temp_c',
  'boiler_feed_water_ph',
  'boiler_feed_tds_ppm',
] as const

/** Blok tata letak tiap kolom — dari ProcessWaterReportService::METRIC_GROUPS. */
const METRIC_GROUPS: Record<string, string> = {
  raw_water_flow_m3h: 'water_flow',
  clarified_water_flow_m3h: 'water_flow',
  softener_inlet_ph: 'softener',
  softener_outlet_hardness_ppm: 'softener',
  alum_dosing_kgh: 'chemical_dosing',
  polymer_dosing_gh: 'chemical_dosing',
  boiler_feed_tank_temp_c: 'boiler_feed',
  boiler_feed_water_ph: 'boiler_feed',
  boiler_feed_tds_ppm: 'boiler_feed',
}

/**
 * KESEMBILAN penyebut, SELURUHNYA BERBEDA, dan seluruhnya lebih kecil daripada
 * coverage.filled_slots 96 — persis himpunan yang diminta unit_test_case 21.
 * Penyebut seragam membuat asersi "tiap kartu membawa penyebutnya sendiri"
 * selalu hijau.
 */
const READING_COUNTS: Record<string, number> = {
  raw_water_flow_m3h: 12,
  // KOLOM YANG TIDAK PERNAH TERISI sepanjang periode — penyebut 0, seluruh
  // angkanya null. Kartunya tetap wajib dirender (diasersi di spec komponen).
  clarified_water_flow_m3h: 0,
  softener_inlet_ph: 24,
  softener_outlet_hardness_ppm: 3,
  alum_dosing_kgh: 19,
  polymer_dosing_gh: 21,
  boiler_feed_tank_temp_c: 8,
  boiler_feed_water_ph: 15,
  boiler_feed_tds_ppm: 7,
}

/**
 * Cakupan yang membawa medan yang TIDAK ADA pada tipe
 * ProcessWaterReportScope. Dipakai untuk membuktikan bahwa medan itu tidak
 * punya jalur apa pun ke kawat — bukan bahwa tipe menolaknya (tipe memang
 * menolaknya, dan itu bukan yang diuji di sini: tipe tidak berlaku pada
 * JavaScript yang sudah terkompilasi).
 */
function scopeWithForbiddenFields(productionLineId: string | null): ProcessWaterReportScope {
  return {
    productionLineId,
    businessUnitId: 'bu-9',
    business_unit_id: 'bu-9',
    isAdmin: true,
  } as unknown as ProcessWaterReportScope
}

/**
 * Satu entri metrics[kolom], BENTUK SERVER.
 *
 * PERHATIKAN APA YANG TIDAK ADA: tidak ada `unit`, tidak ada `label`, tidak
 * ada `target`, tidak ada severity / is_out_of_range / flag apa pun. Payload
 * Process Water memang tidak menerbitkan satu pun di antaranya (DIVERGENSI 1
 * pada docblock berkas), dan stub yang menambahkannya akan menyamarkan repo
 * yang membacanya dari tempat yang tidak ada. `group` ADA, karena view
 * membangun keempat bloknya dari medan itu.
 */
function metric(
  column: string,
  min: number | null,
  avg: number | null,
  max: number | null,
): Record<string, unknown> {
  return {
    min,
    avg,
    max,
    reading_count: READING_COUNTS[column] ?? 0,
    group: METRIC_GROUPS[column] ?? 'water_flow',
  }
}

function metrics(): Record<string, Record<string, unknown>> {
  return {
    raw_water_flow_m3h: metric('raw_water_flow_m3h', 96.4, 118.42, 141.8),
    clarified_water_flow_m3h: metric('clarified_water_flow_m3h', null, null, null),
    // pH 3,2 punya batas yang dikenal SANGAT luas di luar aplikasi ini — dan
    // payload-nya tetap TIDAK menerbitkan satu pun penandaan.
    softener_inlet_ph: metric('softener_inlet_ph', 3.2, 6.85, 7.4),
    softener_outlet_hardness_ppm: metric('softener_outlet_hardness_ppm', 12, 28.5, 44),
    // avg DI LUAR rentang min..max — mustahil dari perhitungan apa pun, dan
    // itulah yang membuat "nol aritmetika di klien" dapat dibuktikan.
    alum_dosing_kgh: metric('alum_dosing_kgh', 1.8, 9.9, 3.1),
    polymer_dosing_gh: metric('polymer_dosing_gh', 1.9, 2.4, 3),
    boiler_feed_tank_temp_c: metric('boiler_feed_tank_temp_c', 68.2, 81.5, 92.9),
    boiler_feed_water_ph: metric('boiler_feed_water_ph', 8.4, 9.95, 11.2),
    // TDS 9800 ppm, juga jauh di luar batas yang dikenal umum — tetap tanpa
    // penandaan apa pun.
    boiler_feed_tds_ppm: metric('boiler_feed_tds_ppm', 1200, 4100, 9800),
  }
}

/**
 * KOSAKATA TECH SPEC untuk metrics: `count` alih-alih `reading_count`, DAN
 * `unit` yang diterbitkan eksplisit — termasuk null untuk kedua kolom pH, dan
 * termasuk '°C' dan 'm³/jam' yang TIDAK BOLEH dinormalkan menjadi 'C' dan
 * 'm3/jam'. Dipakai kasus 6 dan 26.
 */
const SPEC_UNITS: Record<string, string | null> = {
  raw_water_flow_m3h: 'm³/jam',
  clarified_water_flow_m3h: 'm³/jam',
  softener_inlet_ph: null,
  softener_outlet_hardness_ppm: 'ppm',
  alum_dosing_kgh: 'kg/jam',
  polymer_dosing_gh: 'g/jam',
  boiler_feed_tank_temp_c: '°C',
  boiler_feed_water_ph: null,
  boiler_feed_tds_ppm: 'ppm',
}

function specMetrics(): Record<string, Record<string, unknown>> {
  return Object.fromEntries(
    METRIC_COLUMNS.map((column) => {
      const server = metrics()[column]

      return [
        column,
        {
          min: server.min,
          avg: server.avg,
          max: server.max,
          count: READING_COUNTS[column],
          unit: SPEC_UNITS[column],
          group: METRIC_GROUPS[column],
        },
      ]
    }),
  )
}

/**
 * TIGA KEJADIAN BERTEKS IDENTIK pada tiga slot berbeda. Repo yang
 * mengelompokkan menurut teks menjawab SATU entri dan test ini gagal.
 */
function actionTakenEntries(): Array<Record<string, unknown>> {
  return [
    { date: DAY_A, time_slot: '08:00', text: 'Sudah ditangani' },
    { date: DAY_A, time_slot: '14:00', text: 'Sudah ditangani' },
    { date: DAY_B, time_slot: '21:00', text: 'Sudah ditangani' },
  ]
}

/**
 * DAFTAR TERPISAH, dan entri PERTAMA-nya memakai date + time_slot yang SAMA
 * dengan entri pertama action_taken_status: satu slot yang mengisi kedua kolom
 * muncul pada KEDUA daftar, dan itu benar.
 */
function findingEntries(): Array<Record<string, unknown>> {
  return [
    { date: DAY_A, time_slot: '08:00', text: 'Kesadahan keluaran softener naik tajam' },
    { date: DAY_B, time_slot: '03:00', text: 'Dosis polimer terasa tidak stabil' },
  ]
}

function unitMetrics(avg: number | null): Record<string, unknown> {
  return Object.fromEntries(METRIC_COLUMNS.map((column) => [column, metric(column, avg, avg, avg)]))
}

/** Payload ringkasan yang sengaja tidak konsisten — lihat docblock berkas. */
function summaryPayload(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Sungai Rambutan' },
    production_line: { id: 'l-1', name: 'Line Satu', code: 'L1' },
    period: {
      id: 'p-1',
      name: 'Periode Uji Process Water',
      start_date: PERIOD_START,
      end_date: PERIOD_END,
      status: 'open',
      // Payload /summary MEMANG memuat medan ini. Nama mill dibaca view dari
      // blok `business_unit`, satu sumber saja. Yang diasersi di bawah adalah
      // bahwa repo TIDAK menyentuhnya — bukan bahwa repo membuangnya.
      business_unit_name: 'Mill Sungai Rambutan',
    },
    has_data: true,
    coverage: {
      // 96 MELEBIHI penyebut setiap kartu (tertinggi 24) — konsekuensi aturan
      // SEBELAS kolom, karena kedua kolom teks ikut terhitung.
      filled_slots: 96,
      expected_slots: 480,
      // 96/480 = 20,0 — dan server mengirim 77,7.
      coverage_percent: 77.7,
      process_water_unit_count: 2,
      slots_per_unit_per_day: 24,
      days_in_period: 10,
      // 2 x 7 x 24 = 336, dan expected_slots di atas 480.
      days_counted: 7,
      period_running: true,
      _note_filled_rule:
        'Slot terisi dihitung atas SEBELAS kolom pembacaan, BUKAN ketiga belas kolom baris '
        + 'detail: kedua kolom identitas DIKECUALIKAN. Karena kesebelas kolom itu mencakup '
        + 'kedua kolom teks bebas, filled_slots dapat MELEBIHI reading_count setiap metrik '
        + 'ukur — dan selisih itu benar, bukan ketidaksesuaian yang perlu dikoreksi.',
    },
    metrics: metrics(),
    // TEPAT DUA KUNCI, dan keduanya DAFTAR TEKS. BUKAN sebaran.
    text_entries: {
      action_taken_status: actionTakenEntries(),
      findings: findingEntries(),
    },
    // BENTUK SERVER: `not_recorded` kunci TERPISAH, tidak pernah dilipat ke
    // salah satu entri. 9 + 8 + 7 = 24 = recorded_slots.
    inspector_recap: {
      entries: [
        { inspector_id: 'A1', filled_slots: 9 },
        { inspector_id: 'INSP 07 PAGI', filled_slots: 8 },
      ],
      not_recorded: 7,
      recorded_slots: 24,
    },
    daily_trend: {
      raw_water_flow_m3h: [
        { date: DAY_A, avg: 118.4, reading_count: 12 },
        // null — BUKAN 0 — pada tanggal yang tidak sekali pun mencatatnya.
        { date: DAY_B, avg: null, reading_count: 0 },
        { date: DAY_C, avg: 132.1, reading_count: 9 },
      ],
    },
    by_unit: [
      {
        process_water_id: 'PW-1',
        reading_rows: 72,
        reading_count: 72,
        days_with_rows: 6,
        metrics: unitMetrics(7.5),
      },
      {
        // Unit yang punya record tetapi TIDAK SATU PUN pembacaan — tetap
        // muncul, karena membuangnya justru menyembunyikan unit yang tidak
        // pernah dituliskan.
        process_water_id: 'PW-2',
        reading_rows: 0,
        reading_count: 0,
        days_with_rows: 0,
        metrics: unitMetrics(null),
      },
    ],
    daily: [
      {
        date: DAY_A,
        reading_rows: 12,
        metrics_avg: {
          raw_water_flow_m3h: { avg: 118.4, reading_count: 12 },
          clarified_water_flow_m3h: { avg: null, reading_count: 0 },
        },
      },
      {
        date: DAY_B,
        reading_rows: 9,
        metrics_avg: {
          raw_water_flow_m3h: { avg: null, reading_count: 0 },
          clarified_water_flow_m3h: { avg: null, reading_count: 0 },
        },
      },
    ],
    // 12 + 9 = 21, dan server mengirim 777.
    daily_total: { reading_rows: 777 },
    total: { days_with_records: 2, reading_rows: 96 },
    _note_absences:
      'TIDAK ADA satu pun blok sebaran kategoris di payload ini, dan ketiadaan itu disengaja. '
      + 'TIDAK ADA blok lama berhenti. TIDAK ADA acuan batas maupun penandaan nilai di luar '
      + 'batas dalam bentuk apa pun. Dan kolom giliran kerja tidak diterbitkan di layar, hanya '
      + 'di berkas ekspor.',
    ...overrides,
  }
}

/** Kosakata TECH SPEC untuk seluruh payload — nama medan yang berbeda, isi yang sama. */
function specSummaryPayload(): Record<string, unknown> {
  return {
    period: {
      id: 'p-1',
      name: 'Periode Uji Process Water',
      start_date: PERIOD_START,
      end_date: PERIOD_END,
      status: 'open',
    },
    coverage: {
      filled_slots: 96,
      expected_slots: 480,
      unit_count: 2,
      days_counted: 7,
      slots_per_unit_per_day: 24,
      percent: null,
      _note_filled_rule: 'Aturan SEBELAS kolom, disusun server.',
    },
    metrics: specMetrics(),
    text_entries: {
      action_taken_status: actionTakenEntries().map((entry) => ({
        date: entry.date,
        time_slot: entry.time_slot,
        value: entry.text,
      })),
      findings: findingEntries().map((entry) => ({
        date: entry.date,
        time_slot: entry.time_slot,
        value: entry.text,
      })),
    },
    // BENTUK TECH SPEC: DAFTAR, dengan baris ber-inspector_id null sebagai
    // baris 'tidak tercatat'-nya SENDIRI.
    inspector_recap: [
      { inspector_id: 'A1', slots: 9, share: 0.375 },
      { inspector_id: 'INSP 07 PAGI', slots: 8, share: 0.333 },
      { inspector_id: null, slots: 7, share: 0.292 },
    ],
    unit_recap: [
      { process_water_id: 'PW-1', filled_slots: 72 },
      { process_water_id: 'PW-2', filled_slots: 0 },
    ],
    daily: [
      { date: DAY_A, raw_water_flow_avg: 118.4, filled_slots: 12 },
      { date: DAY_B, raw_water_flow_avg: null, filled_slots: 9 },
    ],
    total: { days_with_records: 2, reading_rows: 96 },
    _note_absences: 'Tanpa downtime, tanpa penandaan di luar batas, tanpa kolom giliran kerja.',
  }
}

const PERIOD_OPTIONS = [
  {
    id: 'p-1',
    name: 'Periode Uji Process Water',
    start_date: PERIOD_START,
    end_date: PERIOD_END,
    status: 'open',
    // DENGAN TANDA HUBUNG. Garis bawah di situ tidak cocok dengan satu baris
    // period_stations pun, dan server menjawab [] tanpa satu galat.
    station_type: 'process-water',
    station_type_label: 'Process Water',
  },
  {
    id: 'p-2',
    name: 'Periode Sebelumnya',
    start_date: PREV_START,
    end_date: PREV_END,
    status: 'closed',
    station_type: 'process-water',
    station_type_label: 'Process Water',
  },
]

const LINE_OPTIONS = [
  { id: 'l-1', name: 'Line Satu', code: 'L1' },
  { id: 'l-2', name: 'Line Dua', code: null },
]

beforeEach(() => {
  getMock.mockReset()

  for (const spy of Object.values(localDbMocks)) {
    spy.mockReset()
  }
})

/* ================================================================== */
/* unit_test_case 1 — TEPAT EMPAT ENDPOINT                             */
/* ================================================================== */

describe('empat endpoint, dan tidak ada yang kelima', () => {
  // unit_test_case 1
  it('keempat fungsi meminta TEPAT keempat URL yang sudah ada — nol endpoint baru', async () => {
    getMock.mockImplementation((url: string) => {
      if (url === SUMMARY_PATH) {
        return Promise.resolve({ data: summaryPayload() })
      }

      if (url === EXPORT_PATH) {
        return Promise.resolve({ data: new Blob(['a,b']) })
      }

      return Promise.resolve({ data: { data: [] } })
    })

    await fetchLines()
    await fetchPeriods()
    await fetchSummary({ periodId: 'p-1', productionLineId: 'l-1' })
    const exportUrl = buildExportUrl({ periodId: 'p-1', productionLineId: 'l-1' })

    // buildExportUrl TIDAK menyentuh jaringan — jalurnya diperiksa dari URL
    // yang dibentuknya, dan exportCsv di bawah membuktikan jalur yang sama
    // benar-benar diminta.
    await exportCsv({ periodId: 'p-1', productionLineId: 'l-1' })

    const urls = getMock.mock.calls.map((call) => String(call[0]))

    expect(urls).toEqual([
      '/api/production-lines/options-for-report',
      '/api/process-water-reports/periods',
      '/api/process-water-reports/summary',
      '/api/process-water-reports/export',
    ])
    expect(exportUrl.split('?')[0]).toBe('/api/process-water-reports/export')

    // Dan tidak ada URL KELIMA: empat pemanggilan jaringan untuk empat
    // endpoint, bukan lima.
    expect(getMock).toHaveBeenCalledTimes(4)
    expect(new Set(urls).size).toBe(4)
  })

  it('keempat konstanta jalur adalah SATU definisi masing-masing, dan tidak ada yang kelima', () => {
    expect(LINES_PATH).toBe('/api/production-lines/options-for-report')
    expect(PERIODS_PATH).toBe('/api/process-water-reports/periods')
    expect(SUMMARY_PATH).toBe('/api/process-water-reports/summary')
    expect(EXPORT_PATH).toBe('/api/process-water-reports/export')

    // LINES_PATH TIDAK diduplikasi di bawah prefix process-water-reports: ia
    // endpoint screen-135 yang dipakai ulang verbatim.
    expect(LINES_PATH).not.toContain('process-water-reports')
  })

  // unit_test_case 1 (bagian "nol endpoint baru"): rute pemilih mill ADMIN
  // SAJA, dan repo ini tidak punya satu jalur pun ke sana.
  it('tidak pernah menyentuh endpoint pemilih mill, dan tidak mengekspor pengambilnya', async () => {
    getMock.mockResolvedValue({ data: { data: [] } })

    await fetchLines()
    await fetchPeriods()
    getMock.mockResolvedValue({ data: summaryPayload() })
    await fetchSummary('p-1', { productionLineId: 'l-1' })

    for (const call of getMock.mock.calls) {
      expect(String(call[0])).not.toContain('business-units')
    }

    const module = await import('@/services/processWaterReportRepo')

    expect(Object.keys(module)).not.toContain('fetchBusinessUnits')
    expect(Object.keys(processWaterReportRepo)).not.toContain('fetchBusinessUnits')
  })
})

/* ================================================================== */
/* unit_test_case 2 — business_unit_id TIDAK PERNAH ADA DI KAWAT        */
/* ================================================================== */

describe('business_unit_id tidak pernah dikirim', () => {
  // unit_test_case 2
  it('walker rekursif atas KUNCI params keempat pemanggilan tidak menemukan business_unit_id', async () => {
    getMock.mockImplementation((url: string) =>
      Promise.resolve({ data: url === SUMMARY_PATH ? summaryPayload() : { data: [] } }),
    )

    // Pemanggil yang menyerahkan cakupan ala kesepuluh repo pertama. Di
    // JavaScript yang sudah terkompilasi tipe tidak berlaku, jadi inilah
    // bentuk pemanggilan yang benar-benar mungkin terjadi.
    await fetchLines()
    await fetchPeriods()
    await fetchSummary('p-1', scopeWithForbiddenFields('l-1'))
    await exportCsv('p-1', scopeWithForbiddenFields('l-1'))

    expect(getMock).toHaveBeenCalledTimes(4)

    for (const [url, config] of getMock.mock.calls) {
      const keys = keyNamesOf((config as { params?: unknown } | undefined)?.params ?? {})

      // ASERSI ATAS NAMA KUNCI, bukan atas teks URL maupun payload: kunci
      // yang muncul bernilai undefined lolos dari asersi substring, dan
      // justru kunci itulah yang akan terkirim begitu nilainya terisi.
      expect(keys, String(url)).not.toContain('business_unit_id')
      expect(keys, String(url)).not.toContain('businessUnitId')
      expect(keys, String(url)).not.toContain('isAdmin')
      expect(keys, String(url)).not.toContain('mill_id')
    }
  })

  it('cakupan berisi business_unit_id tidak mengubah satu pun parameter yang terkirim', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    await fetchSummary('p-1', { productionLineId: 'l-1' })
    const bersih = getMock.mock.calls[0][1].params

    getMock.mockClear()
    await fetchSummary('p-1', scopeWithForbiddenFields('l-1'))

    // Parameter yang terkirim SAMA PERSIS: medan terlarang tidak punya jalur
    // apa pun, bukan ditolak oleh sebuah penjagaan.
    expect(getMock.mock.calls[0][1].params).toEqual(bersih)
  })

  it('fetchLines mendelegasikan ke endpoint screen-135 TANPA business_unit_id', async () => {
    getMock.mockResolvedValue({ data: { data: LINE_OPTIONS } })

    const lines = await fetchLines()

    expect(getMock).toHaveBeenCalledTimes(1)
    expect(getMock.mock.calls[0][0]).toBe(LINES_PATH)
    // `null` dikirim EKSPLISIT ke productionLineRepo, yang karenanya
    // mengirimkan params KOSONG — bukan {business_unit_id: undefined}.
    expect(keyNamesOf(getMock.mock.calls[0][1].params)).toEqual([])
    // Barisnya diteruskan APA ADANYA, dalam urutan yang sama, tanpa pemeta
    // baris kedua di repo ini.
    expect(lines).toEqual(LINE_OPTIONS)
  })
})

/* ================================================================== */
/* unit_test_case 3 + 4 — parameter /summary dan /export               */
/* ================================================================== */

describe('parameter permintaan', () => {
  // unit_test_case 3
  it('fetchSummary mengirim TEPAT period_id dan production_line_id — tidak ada kunci ketiga', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    await fetchSummary({ periodId: 'p-1', productionLineId: 'l-1' })

    expect(getMock.mock.calls[0][1].params).toEqual({
      period_id: 'p-1',
      production_line_id: 'l-1',
    })
    expect(Object.keys(getMock.mock.calls[0][1].params)).toHaveLength(2)
  })

  it('kedua bentuk pemanggilan menghasilkan parameter yang SAMA PERSIS', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    await fetchSummary('p-1', { productionLineId: 'l-1' })
    await fetchSummary({ periodId: 'p-1', productionLineId: 'l-1' })

    // Satu implementasi, dua cara memanggil; bukan dua implementasi.
    expect(getMock.mock.calls[0][1].params).toEqual(getMock.mock.calls[1][1].params)
  })

  it('line kosong TIDAK menjadi parameter kosong — server menjawab 422 tentang parameter yang HILANG', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    await fetchSummary({ periodId: 'p-1', productionLineId: null })
    await fetchSummary({ periodId: 'p-1', productionLineId: '' })
    await fetchSummary('p-1')

    for (const call of getMock.mock.calls) {
      expect(call[1].params).toEqual({ period_id: 'p-1' })
      expect(keyNamesOf(call[1].params)).not.toContain('production_line_id')
    }
  })

  // unit_test_case 4
  it('buildExportUrl SELALU mengirim format=csv secara eksplisit, tanpa diminta', () => {
    const url = buildExportUrl({ periodId: 'p-1', productionLineId: 'l-1' })

    assertStringContainsString(
      'format=csv',
      url,
      'format wajib eksplisit, bukan mengandalkan default server',
    )
    assertStringContainsString('period_id=p-1', url, 'period_id wajib ada pada URL ekspor')
    assertStringContainsString(
      'production_line_id=l-1',
      url,
      'production_line_id wajib ada — berkas yang mencampur line tidak dapat dibantah',
    )
  })

  // unit_test_case 4 (lanjutan) — exportCsv memakai parameter yang SAMA.
  it('exportCsv mengirim format=csv dan responseType blob, dari exportParams yang sama', async () => {
    getMock.mockResolvedValue({ data: new Blob(['a,b']) })

    await exportCsv('p-1', { productionLineId: 'l-1' })

    expect(getMock.mock.calls[0][1].params).toEqual({
      period_id: 'p-1',
      production_line_id: 'l-1',
      format: 'csv',
    })
    // Respons /export adalah ALIRAN text/csv, bukan JSON.
    expect(getMock.mock.calls[0][1].responseType).toBe('blob')
    // Dan tidak mungkin ada dua susunan parameter berbeda untuk satu unduhan
    // yang sama.
    expect(getMock.mock.calls[0][1].params).toEqual(
      exportParams('p-1', { productionLineId: 'l-1' }),
    )
  })

  it('TIDAK ADA jalur kode yang dapat menyusun format selain csv', async () => {
    getMock.mockResolvedValue({ data: new Blob(['a,b']) })

    // Pemanggil yang MENCOBA meminta xlsx — baik lewat cakupan maupun lewat
    // bentuk objek. Keduanya tetap menghasilkan csv.
    const exportLoose = exportCsv as unknown as (
      period: unknown,
      scope?: unknown,
    ) => Promise<Blob>

    for (const attempt of [
      { productionLineId: 'l-1', format: 'xlsx' },
      { productionLineId: 'l-1', format: 'excel' },
    ]) {
      getMock.mockClear()
      await exportLoose('p-1', attempt)

      expect(getMock.mock.calls[0][1].params.format).toBe('csv')
      expect(JSON.stringify(getMock.mock.calls[0][1])).not.toContain('xlsx')
      expect(JSON.stringify(getMock.mock.calls[0][1])).not.toContain('excel')
    }

    expect(buildExportUrl({ periodId: 'p-1', productionLineId: 'l-1' })).not.toContain('xlsx')
    expect(buildExportUrl({ periodId: 'p-1', productionLineId: 'l-1' })).not.toContain('excel')
  })

  /**
   * AUDIT STATIS — DAN DI LAYAR INI IA WAJIB DILINGKUP, berbeda dari
   * engineRoomReportRepo.spec.ts yang menyisir SELURUH berkas view.
   *
   * LaporanProcessWaterView.vue MERENDER PROSA yang menyebut 'Ekspor Excel'
   * dan '.xlsx' — itu teks yang DIMINTA spesifikasi (pw-no-mill-picker-note
   * menyatakan ketiadaan tombolnya), jadi penyisiran seluruh berkas memerah
   * pada layar yang justru BENAR. Yang disisir karena itu adalah KODE-nya
   * (blok <script>, komentar dibuang) plus ketiadaan nama itu pada setiap
   * data-testid di seluruh berkas: tombol yang tidak punya testid ber-xlsx
   * adalah tombol yang tidak ada. Berkas repo sendiri tidak punya prosa, jadi
   * ia tetap disisir utuh.
   */
  it('AUDIT STATIS: nol kemunculan excel/xlsx sebagai NILAI pada kode repo maupun kode view', () => {
    const repoSource = readFileSync(
      resolve(process.cwd(), 'src/services/processWaterReportRepo.ts'),
      'utf-8',
    )
    const repoCode = stripComments(repoSource)

    expect(repoCode.toLowerCase()).not.toContain('xlsx')
    expect(repoCode.toLowerCase()).not.toContain('excel')

    const viewSource = readFileSync(
      resolve(process.cwd(), 'src/views/LaporanProcessWaterView.vue'),
      'utf-8',
    )
    const viewScript = stripComments(
      viewSource.slice(viewSource.indexOf('<script'), viewSource.indexOf('</script>')),
    )

    expect(viewScript.toLowerCase()).not.toContain('xlsx')
    expect(viewScript.toLowerCase()).not.toContain('excel')

    // Dan tidak satu pun testid di SELURUH berkas view menamai keduanya —
    // ketiadaan tombolnya diasersi atas TESTID, bukan atas teks halaman.
    expect(viewSource).not.toMatch(/data-testid=["'`][^"'`]*(xlsx|excel)[^"'`]*["'`]/i)
  })

  it('fetchPeriods TIDAK mengirim satu parameter pun — periode milik MILL, bukan milik line', async () => {
    getMock.mockResolvedValue({ data: { data: PERIOD_OPTIONS } })

    const fetchPeriodsLoose = fetchPeriods as unknown as (scope?: unknown) => Promise<unknown>

    for (const scope of [
      undefined,
      {},
      { isAdmin: true, businessUnitId: 'bu-9' },
      { business_unit_id: 'bu-9' },
      { productionLineId: 'l-1', businessUnitId: 'bu-9' },
    ]) {
      getMock.mockClear()
      await fetchPeriodsLoose(scope)

      expect(getMock.mock.calls[0][0]).toBe(PERIODS_PATH)
      // TANPA argumen kedua sama sekali: tidak ada params untuk dapat salah
      // terisi. Yang disaring sebuah line adalah DATA-nya, bukan periodenya.
      expect(getMock.mock.calls[0]).toHaveLength(1)
    }
  })

  it('daftar periode diteruskan APA ADANYA, dalam urutan yang sama, termasuk periode tertutup', async () => {
    getMock.mockResolvedValue({ data: { data: PERIOD_OPTIONS } })

    const periods = await fetchPeriods()

    expect(periods).toEqual(PERIOD_OPTIONS)
    expect(periods.map((period) => period.status)).toEqual(['open', 'closed'])
    // DENGAN TANDA HUBUNG, keduanya.
    expect(periods.map((period) => period.station_type)).toEqual([
      'process-water',
      'process-water',
    ])
  })

  it('daftar periode kosong adalah jawaban yang SAH, bukan galat', async () => {
    getMock.mockResolvedValue({ data: { data: [] } })

    await expect(fetchPeriods()).resolves.toEqual([])

    // Dan payload tanpa pembungkus `data` pun tidak melempar.
    getMock.mockResolvedValue({ data: {} })
    await expect(fetchPeriods()).resolves.toEqual([])
  })
})

/* ================================================================== */
/* unit_test_case 5 + 6 — BODY DITERUSKAN UTUH                         */
/* ================================================================== */

describe('body /summary diteruskan utuh', () => {
  // unit_test_case 5
  it('hasil parse DEEP-EQUAL dengan fixture lengkap — nol kunci dibuang, nol bawaan disuntikkan', async () => {
    const payload = summaryPayload()

    getMock.mockResolvedValue({ data: payload })

    const summary = await fetchSummary({ periodId: 'p-1', productionLineId: 'l-1' })

    // Satu asersi yang mencakup SELURUH bentuk respons sekaligus.
    expect(summary).toEqual(payload)
  })

  // unit_test_case 5 (bagian walker)
  it('walker rekursif memastikan SETIAP jalur kunci fixture ada pada hasil — tanpa kunci tambahan', async () => {
    const payload = summaryPayload()

    getMock.mockResolvedValue({ data: payload })

    const summary = await fetchSummary({ periodId: 'p-1', productionLineId: 'l-1' })

    const diminta = keyPathsOf(payload).sort()
    const diterima = keyPathsOf(summary).sort()

    // Kunci yang HILANG tidak boleh tak terlihat sampai seseorang membacanya
    // di layar; kunci TAMBAHAN yang lahir di repo sama berbahayanya, karena ia
    // akan terbaca sebagai angka server.
    expect(diterima).toEqual(diminta)

    // Dan ketiga kunci yang paling mudah terlewat memang ada.
    expect(diminta).toContain('coverage._note_filled_rule')
    expect(diminta).toContain('_note_absences')
    expect(diminta).toContain('inspector_recap.not_recorded')
  })

  it('TEPAT sembilan kunci metrics, masing-masing dengan group-nya, dalam urutan NUMERIC_METRICS', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('p-1', { productionLineId: 'l-1' })

    expect(Object.keys(summary.metrics ?? {})).toEqual([...METRIC_COLUMNS])
    expect(Object.keys(summary.metrics ?? {})).toHaveLength(9)

    for (const column of METRIC_COLUMNS) {
      expect(summary.metrics?.[column]?.group, column).toBe(METRIC_GROUPS[column])
    }

    // KEEMPAT blok, dan tidak ada yang kelima.
    expect([
      ...new Set(METRIC_COLUMNS.map((column) => summary.metrics?.[column]?.group)),
    ]).toEqual(['water_flow', 'softener', 'chemical_dosing', 'boiler_feed'])
  })

  it('TEPAT dua kunci text_entries, keduanya DAFTAR — bukan sebaran', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('p-1', { productionLineId: 'l-1' })

    expect(Object.keys(summary.text_entries ?? {}).sort()).toEqual([
      'action_taken_status',
      'findings',
    ])
    expect(Array.isArray(summary.text_entries?.action_taken_status)).toBe(true)
    expect(Array.isArray(summary.text_entries?.findings)).toBe(true)

    // TIGA kejadian berteks IDENTIK tetap TIGA entri: repo yang
    // mengelompokkan menurut teks menjawab satu.
    expect(summary.text_entries?.action_taken_status).toHaveLength(3)
    expect(
      summary.text_entries?.action_taken_status.map((entry) => entry.text),
    ).toEqual(['Sudah ditangani', 'Sudah ditangani', 'Sudah ditangani'])
    // Dan masing-masing membawa tanggal serta slot waktunya sendiri.
    expect(
      summary.text_entries?.action_taken_status.map((entry) => `${entry.date} ${entry.time_slot}`),
    ).toEqual([`${DAY_A} 08:00`, `${DAY_A} 14:00`, `${DAY_B} 21:00`])

    // Satu slot yang mengisi KEDUA kolom muncul di KEDUA daftar.
    expect(summary.text_entries?.findings[0]).toMatchObject({ date: DAY_A, time_slot: '08:00' })
  })

  it('walker rekursif: NOL kunci sebaran, NOL kunci downtime, NOL kunci target, NOL kunci shift', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('p-1', { productionLineId: 'l-1' })
    const keys = keyNamesOf(summary)

    // KETIADAAN DIASERSI ATAS NAMA KUNCI, dan itu wajib di sini: payload ini
    // memuat PROSA (_note_absences) yang menyebut kata 'sebaran' dan 'batas'
    // untuk menerangkan ketiadaannya sendiri, jadi pemindaian teks akan
    // memerah pada payload yang justru benar.
    for (const forbidden of [
      'status_distribution',
      'distribution',
      'sebaran',
      'downtime',
      'target',
      'shift',
      'shift_id',
      'is_out_of_range',
      'severity',
      'is_lab',
    ]) {
      expect(keys, forbidden).not.toContain(forbidden)
    }

    // Dan prosa yang menerangkan ketiadaannya memang ADA — yang tidak ada
    // adalah KUNCI-nya.
    expect(summary._note_absences).toContain('sebaran')
  })

  it('kosakata TECH SPEC (percent/count/unit/unit_recap/daily[].raw_water_flow_avg) juga diteruskan utuh', async () => {
    const payload = specSummaryPayload()

    getMock.mockResolvedValue({ data: payload })

    const summary = await fetchSummary({ periodId: 'p-1', productionLineId: 'l-1' })

    // KEDUA kosakata diketik dan KEDUANYA diteruskan: repo tidak memilih di
    // antara keduanya, view yang memilih.
    expect(summary).toEqual(payload)
    expect(summary.coverage?.percent).toBeNull()
    expect(summary.metrics?.alum_dosing_kgh?.count).toBe(19)
    expect(summary.unit_recap).toHaveLength(2)
    expect(summary.daily?.[0]?.raw_water_flow_avg).toBe(118.4)
  })

  it('respons BERPEMBUNGKUS `data` dan respons DATAR menghasilkan hasil yang sama', async () => {
    const payload = summaryPayload()

    getMock.mockResolvedValueOnce({ data: payload })
    const datar = await fetchSummary('p-1', { productionLineId: 'l-1' })

    getMock.mockResolvedValueOnce({ data: { data: payload } })
    const berpembungkus = await fetchSummary('p-1', { productionLineId: 'l-1' })

    expect(berpembungkus).toEqual(datar)
  })

  it('payload yang tidak dikenali menjadi objek kosong, bukan lemparan', async () => {
    for (const body of [null, undefined, 'bukan objek', 42]) {
      getMock.mockResolvedValueOnce({ data: body })

      await expect(fetchSummary('p-1', { productionLineId: 'l-1' })).resolves.toEqual({})
    }
  })
})

/* ================================================================== */
/* unit_test_case 6 — NOL ARITMETIKA, NOL PEMETAAN SATUAN              */
/* ================================================================== */

describe('repo tidak menghitung apa pun dan tidak memetakan ulang satuan', () => {
  // unit_test_case 6
  it('setiap objek metrik diteruskan APA ADANYA, termasuk avg di luar rentang min..max', async () => {
    const payload = summaryPayload()

    getMock.mockResolvedValue({ data: payload })

    const summary = await fetchSummary('p-1', { productionLineId: 'l-1' })

    for (const column of METRIC_COLUMNS) {
      expect(summary.metrics?.[column], column).toEqual(
        (payload.metrics as Record<string, unknown>)[column],
      )
    }

    // avg 9.9 DI LUAR rentang 1.8 .. 3.1 — mustahil dari perhitungan apa pun.
    expect(summary.metrics?.alum_dosing_kgh?.min).toBe(1.8)
    expect(summary.metrics?.alum_dosing_kgh?.avg).toBe(9.9)
    expect(summary.metrics?.alum_dosing_kgh?.max).toBe(3.1)
  })

  // unit_test_case 6 (bagian satuan) — lihat DIVERGENSI 1 pada docblock.
  it('satuan payload diteruskan APA ADANYA: m³/jam, kg/jam, g/jam, ppm, °C, dan null', async () => {
    getMock.mockResolvedValue({ data: specSummaryPayload() })

    const summary = await fetchSummary('p-1', { productionLineId: 'l-1' })

    // KESAMAAN PENUH, termasuk '³' dan '°': tidak ada normalisasi ke
    // 'm3/jam' maupun ke 'C' di berkas ini.
    expect(summary.metrics?.raw_water_flow_m3h?.unit).toBe('m³/jam')
    expect(summary.metrics?.alum_dosing_kgh?.unit).toBe('kg/jam')
    expect(summary.metrics?.polymer_dosing_gh?.unit).toBe('g/jam')
    expect(summary.metrics?.softener_outlet_hardness_ppm?.unit).toBe('ppm')
    expect(summary.metrics?.boiler_feed_tank_temp_c?.unit).toBe('°C')
    // null BERARTI JAWABAN, bukan medan yang hilang: kedua kolom pH justru
    // yang null, dan pH memang besaran tanpa satuan.
    expect(summary.metrics?.softener_inlet_ph?.unit).toBeNull()
    expect(summary.metrics?.boiler_feed_water_ph?.unit).toBeNull()
  })

  it('repo TIDAK MENUMBUHKAN satuan sendiri ketika payload tidak membawanya', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('p-1', { productionLineId: 'l-1' })

    // Payload SERVER tidak menerbitkan `unit` sama sekali. Repo yang
    // menambahkannya di sini akan membuat tabel satuan punya DUA salinan —
    // satu di repo, satu di view — dan dua salinan adalah dua ejaan.
    for (const column of METRIC_COLUMNS) {
      expect(keyNamesOf(summary.metrics?.[column]).sort(), column).toEqual([
        'avg',
        'group',
        'max',
        'min',
        'reading_count',
      ])
    }
  })

  it('tidak ada persen, porsi, penyebut, maupun total yang diturunkan di repo', async () => {
    const payload = summaryPayload()

    getMock.mockResolvedValue({ data: payload })

    const summary = await fetchSummary('p-1', { productionLineId: 'l-1' })

    // 96/480 = 20,0 dan server mengirim 77,7 — yang dirender adalah 77,7.
    expect(summary.coverage?.coverage_percent).toBe(77.7)
    // 2 x 7 x 24 = 336 dan server mengirim 480 — repo tidak "memperbaikinya".
    expect(summary.coverage?.expected_slots).toBe(480)
    // 12 + 9 = 21 dan server mengirim 777.
    expect(summary.daily_total?.reading_rows).toBe(777)
    // Dan tidak ada medan `share` yang lahir di repo untuk rekap inspector.
    const recap = summary.inspector_recap as unknown as {
      entries: Array<Record<string, unknown>>
    }

    for (const entry of recap.entries) {
      expect(keyNamesOf(entry).sort()).toEqual(['filled_slots', 'inspector_id'])
    }
  })

  it('urutan metrics / by_unit / daily / kedua daftar teks tidak pernah diurutkan ulang', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('p-1', { productionLineId: 'l-1' })

    expect(Object.keys(summary.metrics ?? {})).toEqual([...METRIC_COLUMNS])
    expect(summary.by_unit?.map((row) => row.process_water_id)).toEqual(['PW-1', 'PW-2'])
    expect(summary.daily?.map((row) => row.date)).toEqual([DAY_A, DAY_B])
    expect(summary.text_entries?.findings.map((entry) => entry.time_slot)).toEqual([
      '08:00',
      '03:00',
    ])
    // Urutan slot BUKAN urutan leksikal, dan repo tidak mengurutkannya.
    expect(summary.text_entries?.findings.map((entry) => entry.time_slot)).not.toEqual(
      ['03:00', '08:00'],
    )
  })

  it('AUDIT STATIS: tidak ada aritmetika atas angka laporan di berkas repo', () => {
    const source = readFileSync(
      resolve(process.cwd(), 'src/services/processWaterReportRepo.ts'),
      'utf-8',
    )
    // Komentar dibuang lebih dulu: berkas ini MENJELASKAN panjang-panjang
    // mengapa tidak ada aritmetika, jadi penyisiran atas sumber mentah selalu
    // memerah pada kalimat yang justru melarangnya.
    const code = stripComments(source)

    const offending = code
      .split('\n')
      .filter((line) => /Math\.(min|max|round)|\.reduce\(|\.sort\(|localeCompare|\/ |\* 100/.test(line))

    expect(offending, offending.join(' | ')).toEqual([])
  })
})

/* ================================================================== */
/* unit_test_case 7 — NOL TULISAN KE localDb                           */
/* ================================================================== */

describe('nol tulisan ke localDb', () => {
  // unit_test_case 7
  it('seluruh metode tulis localDb menerima NOL pemanggilan sesudah seluruh alur repo', async () => {
    getMock.mockImplementation((url: string) =>
      Promise.resolve({
        data: url === SUMMARY_PATH ? summaryPayload() : { data: [] },
      }),
    )

    await fetchLines()
    await fetchPeriods()
    await fetchSummary('p-1', { productionLineId: 'l-1' })
    getMock.mockResolvedValue({ data: new Blob(['a,b']) })
    await exportCsv('p-1', { productionLineId: 'l-1' })

    for (const [name, spy] of Object.entries(localDbMocks)) {
      expect(spy, `localDb.${name} tidak boleh dipanggil`).not.toHaveBeenCalled()
    }
  })

  it('AUDIT STATIS: repo tidak menyentuh localDb, localStorage, maupun sqlite', () => {
    const source = readFileSync(
      resolve(process.cwd(), 'src/services/processWaterReportRepo.ts'),
      'utf-8',
    )
    const code = stripComments(source)

    // Ketiadaan mode luring DISENGAJA: hasil laporan tidak pernah disimpan,
    // jadi keadaan luring dinyatakan apa adanya alih-alih ditutupi angka basi.
    expect(code).not.toContain('localDb')
    expect(code).not.toContain('localStorage')
    expect(code).not.toContain('sqlite')
    expect(code).not.toContain('writeThroughSync')
  })
})

/* ================================================================== */
/* unit_test_case 8 + 9 + 10 (separuh repo) — GALAT DITERUSKAN         */
/* ================================================================== */

describe('galat diteruskan apa adanya', () => {
  /**
   * BENTUK GALAT DATAR. apiClient.normalizeError menolak dengan objek DATAR
   * `{ message, errors?, status? }` dan sudah MEMBUANG `response`. KETIADAAN
   * `status` itulah yang membedakan "jaringan putus" dari "server menjawab",
   * dan urutan cabang handleError() di view bergantung padanya — jadi repo
   * TIDAK boleh menafsirkan satu kode pun.
   */
  // unit_test_case 8 (separuh repo)
  it('401 diteruskan dengan status-nya — view yang mengalihkan ke Login', async () => {
    getMock.mockRejectedValueOnce({ message: 'Unauthenticated.', status: 401 })

    await expect(fetchSummary('p-1', { productionLineId: 'l-1' })).rejects.toEqual({
      message: 'Unauthenticated.',
      status: 401,
    })
  })

  // unit_test_case 9 (separuh repo)
  it('422 akun tanpa mill diteruskan beserta medan errors-nya, tanpa ditafsirkan', async () => {
    getMock.mockRejectedValueOnce({
      message: 'Akun Anda belum terhubung ke mill. Hubungi Admin.',
      status: 422,
      errors: { business_unit_id: ['Akun Anda belum terhubung ke mill. Hubungi Admin.'] },
    })

    // Yang membedakan 422 "akun tanpa mill" dari 422 ekspor-terlalu-besar
    // adalah PEMANGGIL-nya, dan pemetaan itu ada di handleError() view
    // (meaning422).
    await expect(fetchPeriods()).rejects.toMatchObject({
      status: 422,
      errors: { business_unit_id: ['Akun Anda belum terhubung ke mill. Hubungi Admin.'] },
    })
  })

  // unit_test_case 10 (separuh repo)
  it('403 line milik mill lain diteruskan dengan status-nya — view yang meminta memilih line', async () => {
    getMock.mockRejectedValueOnce({
      message: 'Anda tidak memiliki akses untuk aksi ini.',
      status: 403,
    })

    await expect(fetchSummary('p-1', { productionLineId: 'l-mill-lain' })).rejects.toEqual({
      message: 'Anda tidak memiliki akses untuk aksi ini.',
      status: 403,
    })
  })

  it('galat jaringan TANPA status diteruskan tanpa status — itu pembeda cabangnya', async () => {
    getMock.mockRejectedValueOnce({ message: 'Tidak dapat terhubung ke server.' })

    await expect(fetchSummary('p-1', { productionLineId: 'l-1' })).rejects.toEqual({
      message: 'Tidak dapat terhubung ke server.',
    })
  })

  it('404 periode tidak ditemukan diteruskan dengan status-nya, bukan diubah menjadi 401', async () => {
    getMock.mockRejectedValueOnce({ message: 'Periode tidak ditemukan.', status: 404 })

    await expect(fetchSummary('p-9', { productionLineId: 'l-1' })).rejects.toEqual({
      message: 'Periode tidak ditemukan.',
      status: 404,
    })
  })

  it('422 batas 50.000 baris slot diteruskan APA ADANYA, dengan SATU permintaan', async () => {
    const message =
      'Ekspor melebihi 50.000 baris slot. Persempit rentang periode, lalu coba lagi.'

    getMock.mockRejectedValueOnce({ message, status: 422 })

    await expect(exportCsv('p-1', { productionLineId: 'l-1' })).rejects.toEqual({
      message,
      status: 422,
    })
    // Repo tidak mencoba memotong, memecah, atau mengulang permintaan dengan
    // rentang yang lebih kecil.
    expect(getMock).toHaveBeenCalledTimes(1)
  })

  it('galat /periods diteruskan, tidak diganti daftar kosong', async () => {
    getMock.mockRejectedValueOnce({ message: 'Unauthenticated.', status: 401 })

    await expect(fetchPeriods()).rejects.toEqual({ message: 'Unauthenticated.', status: 401 })
  })

  it('galat /lines diteruskan dari productionLineRepo tanpa disamarkan', async () => {
    getMock.mockRejectedValueOnce({ message: 'Tidak dapat terhubung ke server.' })

    await expect(fetchLines()).rejects.toEqual({ message: 'Tidak dapat terhubung ke server.' })
  })
})

/* ================================================================== */
/* Penyimpanan berkas                                                  */
/* ================================================================== */

describe('penyimpanan berkas CSV', () => {
  it('saveCsvFile memakai anchor + object URL — POLA YANG SAMA dengan kembaran sebelumnya', () => {
    const createObjectURL = vi.fn(() => 'blob:x')
    const revokeObjectURL = vi.fn()

    vi.stubGlobal('URL', { createObjectURL, revokeObjectURL })

    const click = vi.fn()
    const anchor = document.createElement('a')

    anchor.click = click
    vi.spyOn(document, 'createElement').mockReturnValueOnce(anchor)

    saveCsvFile(new Blob(['a,b']), 'laporan-process-water_periode_line.csv')

    expect(anchor.download).toBe('laporan-process-water_periode_line.csv')
    expect(click).toHaveBeenCalledOnce()
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:x')

    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })
})

/* ================================================================== */
/* Bentuk modul                                                        */
/* ================================================================== */

describe('bentuk modul', () => {
  it('mengekspor tepat tujuh anggota, dan fetchLines ADA di antaranya', () => {
    // TUJUH, dan `fetchLines` termasuk: berbeda dari ketiga belas kembaran
    // sebelumnya, layar ini memanggil SATU repo untuk keempat permintaannya,
    // sehingga penjagaan "nol permintaan sebelum line berlaku" dapat diamati
    // pada satu tempat. Mock view yang MELUPAKAN fetchLines adalah satu-satunya
    // kombinasi yang mematahkan spec komponen — dicatat di sini.
    expect(Object.keys(processWaterReportRepo).sort()).toEqual([
      'buildExportUrl',
      'exportCsv',
      'exportParams',
      'fetchLines',
      'fetchPeriods',
      'fetchSummary',
      'saveCsvFile',
    ])
  })

  it('tidak ada satu pun anggota yang menulis ke server — layar ini BACAAN SAJA', () => {
    for (const key of Object.keys(processWaterReportRepo)) {
      expect(key, key).not.toMatch(/^(create|update|delete|post|put|patch|sync)/)
    }
    expect(Object.keys(processWaterReportRepo).filter((key) => /^fetch/.test(key))).toHaveLength(3)
  })

  it('fetchLines MENDELEGASIKAN, dan tidak menduplikasi URL-nya di berkas ini', () => {
    const source = readFileSync(
      resolve(process.cwd(), 'src/services/processWaterReportRepo.ts'),
      'utf-8',
    )

    expect(source).toContain('productionLineRepo.fetchProductionLinesForReport(null)')
    // SATU definisi URL di berkas ini (konstanta LINES_PATH), bukan dua —
    // dihitung atas KODE-nya, karena docblock berkas itu menyebut jalurnya
    // beberapa kali justru untuk menerangkan bahwa ia TIDAK diduplikasi.
    expect(
      stripComments(source).match(/\/api\/production-lines\/options-for-report/g),
    ).toHaveLength(1)
  })
})
