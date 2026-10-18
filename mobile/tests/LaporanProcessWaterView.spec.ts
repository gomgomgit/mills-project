/**
 * LaporanProcessWaterView.spec.ts — screen-161--laporan-process-water-mobile /
 * usecase-167--laporan-process-water-mobile.
 *
 * Satu test (atau lebih) per `test_scenarios[].component_test` pada tech spec
 * screen-161 — 25 skenario — plus seluruh `unit_test_cases` yang menyangkut
 * KEPUTUSAN LAYAR (8-58). Bentuk permintaan HTTP-nya milik
 * processWaterReportRepo.spec.ts dan tidak diulang di sini.
 *
 * Kembaran KEEMPAT BELAS dari pola yang sama, dari LaporanSterilizerView.spec.ts
 * (screen-135) sampai LaporanEngineRoomView.spec.ts (screen-159).
 *
 * YANG DI-MOCK: keempat fungsi jaringan repo laporan + saveCsvFile, repo
 * Production Line, apiClient, localDb, router, dan ketiga store.
 *
 * `fetchLines` IKUT DI-MOCK, DAN ITU WAJIB. Layar ini memanggil
 * processWaterReportRepo.fetchLines(), yang MENDELEGASIKAN ke
 * productionLineRepo.fetchProductionLinesForReport(null). Mock repo laporan
 * yang MELUPAKAN `fetchLines` adalah satu-satunya kombinasi yang mematahkan
 * berkas ini: fungsi aslinya ikut terpanggil, delegasinya menembus ke mock
 * productionLineRepo, dan hitungan "berapa permintaan yang dikirim" terbelah
 * ke dua tempat. Keduanya karena itu di-mock, dan mock productionLineRepo
 * diasersi NOL pemanggilan — sebuah regresi yang memanggilnya langsung dari
 * view akan tertangkap, bukan lolos.
 *
 * ────────────────────────────────────────────────────────────────────────
 * LIMA HAL YANG MEMBEDAKAN LAYAR INI DARI KETIGA BELAS LAPORAN SEBELUMNYA
 * ────────────────────────────────────────────────────────────────────────
 * 1. SEMBILAN KARTU, bukan 23 (Engine Room) atau 14 (Effluent Plant) — gulir
 *    TERPENDEK di antara laporan bergrid. Keempat bloknya tetap DIBANGUN DARI
 *    FIELD `group` PADA RESPONS, jadi yang diasersi bukan "ada sembilan
 *    kartu" saja, melainkan keanggotaan tiap kartu DI DALAM elemen bloknya.
 *    Kesembilan penyebut fixture BERBEDA-BEDA; dengan penyebut seragam,
 *    penyebut bersama lolos tanpa terlihat.
 *
 * 2. BLOK DOSIS KIMIA ADALAH TITIK PALING RAWAN, dan satuannya diasersi
 *    DALAM DUA ASERSI TERPISAH — satu per kartu, menyasar
 *    `pw-metric-unit-{kolom}` kartu itu sendiri. Satu asersi atas elemen blok
 *    'dosis' TIDAK dianggap memenuhi kasus 22 dan 23: alum kg/jam melawan
 *    polimer g/jam, skala berbeda SERIBU KALI, dan di ponsel kedua kartu
 *    tidak pernah terlihat bersamaan.
 *
 * 3. NOL SEBARAN KATEGORIS, dan ketiadaannya diasersi atas TESTID dan JUDUL —
 *    testid-nya DISALIN dari LaporanEngineRoomView.vue, laporan yang MEMANG
 *    punya blok itu, supaya asersinya benar-benar menangkap salinan yang
 *    keliru. PEMINDAIAN PROSA TIDAK DIPAKAI DAN TIDAK BOLEH DIPAKAI: layar
 *    ini WAJIB merender prosa yang menyebut kata 'sebaran' untuk menerangkan
 *    ketiadaannya sendiri (pw-no-distribution-note), dan payload-nya pun
 *    memuat kata itu di `_note_absences`. Pemindaian prosa memerah pada layar
 *    yang justru BENAR.
 *
 * 4. KEDUA KARTU pH TIDAK PUNYA ELEMEN SATUAN SAMA SEKALI, dan asersinya
 *    MENYASAR ELEMEN ITU — bukan teks kartunya. Label kedua kartu itu
 *    memang berbunyi 'pH Masuk Softener' dan 'pH Air Umpan Boiler', jadi
 *    asersi `not.toContain('pH')` tingkat kartu MEMERAH pada layar yang
 *    benar. Yang diasersi: `pw-metric-unit-softener_inlet_ph` dan
 *    `pw-metric-unit-boiler_feed_water_ph` TIDAK DIRENDER, dan kartunya tidak
 *    memuat '-' / '—' / 'ppm' / 'unit' sebagai satuan.
 *
 * 5. REKAP PER INSPECTOR mencetak KODE MENTAH, dan angkanya dibaca DARI SEL
 *    DI DALAM BARISNYA SENDIRI (`pw-inspector-code-{i}` /
 *    `pw-inspector-slots-{i}`). Kode pendek seperti 'A1' atau '7' cocok di
 *    dalam angka lain pada halaman yang sama, jadi pemindaian teks kontainer
 *    hijau tanpa membuktikan apa pun — pelajaran yang sudah dibayar tiga kali
 *    di sisi web.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DIVERGENSI YANG DICATAT DI SINI
 * ────────────────────────────────────────────────────────────────────────
 * DIVERGENSI 1 — SATUAN TIDAK ADA DI PAYLOAD. unit_test_cases 22-26 berbunyi
 * "satuan dicetak dari field `unit` respons". Payload Process Water TIDAK
 * menerbitkan `unit` maupun `label` sama sekali
 * (ProcessWaterReportService::metricsOf). Satuannya datang dari tabel
 * METRIC_META di LaporanProcessWaterView.vue, disalin persis dari $metrikMeta
 * pada blade screen-160, dan nilai server MENANG bila kelak diterbitkan.
 * Kedua jalur karena itu diuji di bawah: fixture BENTUK SERVER (tanpa `unit`)
 * dan fixture KOSAKATA TECH SPEC (dengan `unit` eksplisit, termasuk null).
 * Keduanya WAJIB menghasilkan satuan yang sama persis — kalau tidak, ponsel
 * dan web akan mencetak satuan berbeda untuk payload yang sama.
 *
 * DIVERGENSI 2 — NAMA KUNCI METRIK. Tech spec menamai kolomnya `alum_dose`,
 * `polymer_dose`, `boiler_feed_ph`; server menamainya `alum_dosing_kgh`,
 * `polymer_dosing_gh`, `boiler_feed_water_ph`. METRIC_META memetakan KEDUA
 * ejaan ke objek meta yang SAMA, jadi testid satuannya pun ada dalam kedua
 * ejaan. Keduanya diasersi.
 *
 * DIVERGENSI 3 — skenario yang berbunyi "Router menolak Mill Management dan
 * Admin" tidak dapat dipenuhi seperti tertulis: router mobile hanya punya
 * penjaga SESI (`meta.public`), tanpa daftar peran per rute. Lihat bagian
 * "router" di bawah, yang mengasersi kontrak yang SEBENARNYA alih-alih
 * memalsukan penolakan yang tidak ada.
 *
 * DIVERGENSI 4 — PORSI REKAP INSPECTOR DIHITUNG DI KLIEN, dan itu BUKAN
 * pelanggaran aturan nol-aritmetika. Payload tidak menerbitkan porsi siap
 * cetak, jadi porsinya dibentuk formatShare() dengan rumus, pembulatan, dan
 * pemisah desimal yang DISALIN PERSIS dari helper $porsi pada blade
 * screen-160. Yang menjadi syaratnya adalah PENYEBUTNYA: `recorded_slots`
 * DARI RESPONS, tidak pernah expected_slots. Diasersi digit demi digit di
 * bawah.
 *
 * PERINGATAN YANG DIHARAPKAN: "Failed to resolve directive: stacked-table".
 * `v-stacked-table` didaftarkan global di src/main.ts, bukan di setup test —
 * identik dengan ketiga belas saudaranya, dan tidak fatal. Jangan mencoba
 * mengasersinya hilang.
 *
 * KETIGA REKAP MEMAKAI CollapsibleSection, yang memakai `v-show` (BUKAN
 * `v-if`): saat tertutup isinya TETAP ADA di DOM. Asersi isi karena itu tidak
 * perlu menoggle apa pun di berkas ini; yang menoggle adalah spec browser,
 * tempat visibilitasnya memang berarti.
 *
 * TANGGALNYA RELATIF, bukan kalender tetap. Skenario 10 menyebut tanggal
 * 2026-10-05 dan slot 08:00 sebagai contoh; yang mengikat adalah bahwa SATU
 * slot yang sama mengisi kedua kolom, jadi slot itu diwakili DAY_A + '08:00'
 * dan diasersi lewat atribut data-date/data-time-slot barisnya — bukan lewat
 * tanggal kalender yang akan basi tanpa satu test berubah warna.
 *
 * BENTUK GALAT — DATAR, TANPA `response`. Ketiadaan `status` itulah yang
 * membedakan "jaringan putus" dari "server menjawab 401/403/404/422", dan
 * urutan cabang handleError bergantung padanya.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { DOMWrapper, VueWrapper } from '@vue/test-utils'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import LaporanProcessWaterView from '@/views/LaporanProcessWaterView.vue'

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
 * apiClient di-mock supaya modul ASLI processWaterReportRepo (diimpor lewat
 * importActual di bawah, demi konstanta jalur yang sungguhan) tidak membangun
 * instance axios dan tidak pernah menyentuh jaringan. Spy `get`-nya DIPAKAI
 * sebagai asersi: layar ini hanya boleh berbicara lewat repo, jadi setiap
 * pemanggilan langsung — termasuk pencarian nama inspector — akan terhitung
 * di sini.
 */
const { apiClientGetMock } = vi.hoisted(() => ({ apiClientGetMock: vi.fn() }))

vi.mock('@/services/apiClient', () => ({
  default: { get: apiClientGetMock },
}))

/** LIMA fungsi, dan `fetchLines` TERMASUK — lihat docblock berkas. */
const { repoMocks } = vi.hoisted(() => ({
  repoMocks: {
    fetchLines: vi.fn(),
    fetchPeriods: vi.fn(),
    fetchSummary: vi.fn(),
    exportCsv: vi.fn(),
    saveCsvFile: vi.fn(),
  },
}))

vi.mock('@/services/processWaterReportRepo', async () => {
  const actual =
    await vi.importActual<typeof import('@/services/processWaterReportRepo')>(
      '@/services/processWaterReportRepo',
    )

  return {
    ...actual,
    default: { ...actual.default, ...repoMocks },
    ...repoMocks,
  }
})

const { productionLineMocks } = vi.hoisted(() => ({
  productionLineMocks: {
    fetchProductionLinesForReport: vi.fn(),
    fetchCurrentProductionLines: vi.fn(),
    fetchAndCacheStationsForProductionLine: vi.fn(),
  },
}))

vi.mock('@/services/productionLineRepo', () => ({
  default: productionLineMocks,
  productionLineRepo: productionLineMocks,
  ...productionLineMocks,
}))

/**
 * localDb di-mock PENUH dengan spy tulis, lalu diasersi NOL pemanggilan.
 * Modul aslinya hanya punya open/query/run; put/add/bulkPut/transaction
 * ditambahkan karena unit_test_case 57 menamai keempatnya.
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

/* ------------------------------------------------------------------ */
/* Bentuk galat NYATA                                                  */
/* ------------------------------------------------------------------ */

const NETWORK_ERROR = { message: 'Tidak dapat terhubung ke server.' }
const UNAUTHENTICATED_ERROR = { message: 'Unauthenticated.', status: 401 }
const FORBIDDEN_ERROR = { message: 'Anda tidak memiliki akses untuk aksi ini.', status: 403 }
const NOT_FOUND_ERROR = { message: 'Periode tidak ditemukan.', status: 404 }
const NO_MILL_ERROR = {
  message: 'Akun Anda belum terhubung ke mill. Hubungi Admin.',
  status: 422,
  errors: { business_unit_id: ['Akun Anda belum terhubung ke mill. Hubungi Admin.'] },
}
const EXPORT_FAILED_ERROR = {
  message: 'Ekspor melebihi 50.000 baris slot. Persempit rentang periode, lalu coba lagi.',
  status: 422,
}

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

/** Slot yang mengisi KEDUA kolom teks — skenario 10. */
const SHARED_SLOT = '08:00'

/* ------------------------------------------------------------------ */
/* Fixture                                                             */
/* ------------------------------------------------------------------ */

const PERIOD = {
  id: 'p-1',
  name: 'Periode Uji Process Water',
  start_date: PERIOD_START,
  end_date: PERIOD_END,
  status: 'open',
  // DENGAN TANDA HUBUNG.
  station_type: 'process-water',
  station_type_label: 'Process Water',
}

const PERIOD_CLOSED = {
  id: 'p-2',
  name: 'Periode Sebelumnya',
  start_date: PREV_START,
  end_date: PREV_END,
  status: 'closed',
  station_type: 'process-water',
  station_type_label: 'Process Water',
}

const LINE_1 = { id: 'l-1', name: 'Line Satu', code: 'L1' }
const LINE_2 = { id: 'l-2', name: 'Line Dua', code: 'L2' }
const LINE_QUERY = { id: 'l-query', name: 'Line Dari Rute', code: 'LQ' }

/** KUNCI YANG SAMA PERSIS yang ditulis StationListView.vue. */
const REMEMBERED_LINE_KEY = 'msl_production_line_u-1'

/** Kesembilan kolom ukur, urutan ProcessWaterReportService::NUMERIC_METRICS. */
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
 * KESEMBILAN penyebut, SELURUHNYA BERBEDA — himpunan yang diminta
 * unit_test_case 21 ({12, 7, 24, 3, 19, 0, 8, 15, 21}). Penyebut seragam
 * membuat asersi "tiap kartu membawa penyebutnya sendiri" selalu hijau, dan
 * di ponsel cacat itu LEBIH sulit terlihat karena kartunya tidak pernah
 * berdampingan.
 */
const READING_COUNTS: Record<string, number> = {
  raw_water_flow_m3h: 12,
  // KOLOM YANG TIDAK PERNAH TERISI sepanjang periode — kartunya TETAP
  // dirender dengan 'tidak tersedia' dan penyebut nol.
  clarified_water_flow_m3h: 0,
  softener_inlet_ph: 24,
  softener_outlet_hardness_ppm: 3,
  alum_dosing_kgh: 19,
  polymer_dosing_gh: 21,
  boiler_feed_tank_temp_c: 8,
  boiler_feed_water_ph: 15,
  boiler_feed_tds_ppm: 7,
}

/** KEEMPAT blok berjudul, dengan jumlah kartu di dalamnya: 2 + 2 + 2 + 3. */
const METRIC_BLOCKS = [
  {
    testid: 'pw-metric-block-aliran-air',
    group: 'water_flow',
    title: 'Aliran Air',
    columns: ['raw_water_flow_m3h', 'clarified_water_flow_m3h'],
  },
  {
    testid: 'pw-metric-block-softener',
    group: 'softener',
    title: 'Softener',
    columns: ['softener_inlet_ph', 'softener_outlet_hardness_ppm'],
  },
  {
    testid: 'pw-metric-block-dosis-kimia',
    group: 'chemical_dosing',
    title: 'Dosis Kimia',
    columns: ['alum_dosing_kgh', 'polymer_dosing_gh'],
  },
  {
    testid: 'pw-metric-block-air-umpan-boiler',
    group: 'boiler_feed',
    title: 'Air Umpan Boiler',
    columns: ['boiler_feed_tank_temp_c', 'boiler_feed_water_ph', 'boiler_feed_tds_ppm'],
  },
]

/** Satuan yang WAJIB tercetak per kolom — null = tidak mencetak apa pun. */
const EXPECTED_UNITS: Record<string, string | null> = {
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

/**
 * Satu entri metrics[kolom], BENTUK SERVER — TANPA `unit` dan TANPA `label`,
 * karena payload-nya memang tidak menerbitkan keduanya (DIVERGENSI 1).
 */
function metric(
  column: string,
  min: number | null,
  avg: number | null,
  max: number | null,
  overrides: Record<string, unknown> = {},
): Record<string, unknown> {
  return {
    min,
    avg,
    max,
    reading_count: READING_COUNTS[column] ?? 0,
    group: METRIC_GROUPS[column] ?? 'water_flow',
    ...overrides,
  }
}

/**
 * Kesembilan entri.
 *
 * alum_dosing_kgh sengaja membawa avg 9.9 di LUAR rentang min 1.8 .. max 3.1:
 * mustahil dari perhitungan apa pun, dan itulah yang membuat "nol aritmetika
 * di klien" dapat dibuktikan DI LAYAR.
 *
 * clarified_water_flow_m3h TIDAK PERNAH TERISI: penyebut 0, seluruh angkanya
 * null. Kartunya tetap harus dirender, dan tetap di dalam blok Aliran Air.
 *
 * pH masuk softener 3,2 dan TDS air umpan boiler 9800 ada di sini justru
 * karena keduanya terlihat "mencurigakan": NOL elemen penanda di luar batas
 * tetap yang benar (skenario 15).
 */
function metrics(): Record<string, Record<string, unknown>> {
  return {
    raw_water_flow_m3h: metric('raw_water_flow_m3h', 96.4, 118.42, 141.8),
    clarified_water_flow_m3h: metric('clarified_water_flow_m3h', null, null, null),
    softener_inlet_ph: metric('softener_inlet_ph', 3.2, 6.85, 7.4),
    softener_outlet_hardness_ppm: metric('softener_outlet_hardness_ppm', 12, 28.5, 44),
    alum_dosing_kgh: metric('alum_dosing_kgh', 1.8, 9.9, 3.1),
    polymer_dosing_gh: metric('polymer_dosing_gh', 1.9, 2.4, 3),
    boiler_feed_tank_temp_c: metric('boiler_feed_tank_temp_c', 68.2, 81.5, 92.9),
    boiler_feed_water_ph: metric('boiler_feed_water_ph', 8.4, 9.95, 11.2),
    boiler_feed_tds_ppm: metric('boiler_feed_tds_ppm', 1200, 4100, 9800),
  }
}

/** Kesembilan entri dengan nilai null dan penyebut 0 — periode tanpa pembacaan. */
function emptyMetrics(): Record<string, Record<string, unknown>> {
  return Object.fromEntries(
    METRIC_COLUMNS.map((column) => [
      column,
      { min: null, avg: null, max: null, reading_count: 0, group: METRIC_GROUPS[column] },
    ]),
  )
}

/**
 * TIGA KEJADIAN BERTEKS IDENTIK pada tiga slot berbeda — layar yang
 * mengelompokkan menurut teks menjawab SATU baris berjumlah tiga, dan itulah
 * cacat yang dicari unit_test_case 36.
 */
function actionTakenEntries(): Array<Record<string, unknown>> {
  return [
    { date: DAY_A, time_slot: SHARED_SLOT, text: 'Sudah ditangani' },
    { date: DAY_A, time_slot: '14:00', text: 'Sudah ditangani' },
    { date: DAY_B, time_slot: '21:00', text: 'Sudah ditangani' },
  ]
}

/**
 * DAFTAR TERPISAH, dan entri PERTAMA-nya memakai date + time_slot yang SAMA
 * dengan entri pertama action_taken_status: satu slot yang mengisi kedua
 * kolom muncul pada KEDUA rekap, dan itu benar — bukan penghitungan ganda.
 */
function findingEntries(): Array<Record<string, unknown>> {
  return [
    { date: DAY_A, time_slot: SHARED_SLOT, text: 'Kesadahan keluaran softener naik tajam' },
    { date: DAY_B, time_slot: '03:00', text: 'Dosis polimer terasa tidak stabil' },
  ]
}

function unitMetrics(avg: number | null): Record<string, unknown> {
  return Object.fromEntries(METRIC_COLUMNS.map((column) => [column, metric(column, avg, avg, avg)]))
}

/**
 * Payload /summary BENTUK SERVER, sengaja bertentangan dengan dirinya
 * sendiri:
 *   - coverage_percent 77.7 padahal 96/480 = 20,0
 *   - expected_slots 480 padahal 2 unit x 7 hari x 24 slot = 336
 *   - alum avg 9.9 di LUAR min 1.8 .. max 3.1
 *   - kesembilan reading_count SELURUHNYA lebih kecil daripada filled_slots 96
 *   - daily_total.reading_rows 777 padahal baris hariannya 12 + 9
 * Layar yang menghitung sendiri PASTI gagal di sini.
 */
function summaryFixture(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Sungai Rambutan' },
    production_line: { id: 'l-1', name: 'Line Satu' },
    period: {
      id: 'p-1',
      name: 'Periode Uji Process Water',
      start_date: PERIOD_START,
      end_date: PERIOD_END,
      status: 'open',
    },
    has_data: true,
    coverage: {
      filled_slots: 96,
      expected_slots: 480,
      coverage_percent: 77.7,
      process_water_unit_count: 2,
      slots_per_unit_per_day: 24,
      days_in_period: 10,
      days_counted: 7,
      period_running: true,
      _note_filled_rule: NOTE_FILLED_RULE,
    },
    metrics: metrics(),
    text_entries: {
      action_taken_status: actionTakenEntries(),
      findings: findingEntries(),
    },
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
        // null — BUKAN 0: batangnya KOSONG, bukan batang nol.
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
    daily_total: { reading_rows: 777 },
    total: { days_with_records: 2, reading_rows: 96 },
    _note_absences: NOTE_ABSENCES,
    ...overrides,
  }
}

/** Prosa SERVER — dirender VERBATIM, dan diasersi dengan KESAMAAN PENUH. */
const NOTE_FILLED_RULE =
  'Slot terisi dihitung atas SEBELAS kolom ProcessWaterRecordService::READING_FIELDS, BUKAN '
  + 'ketiga belas kolom baris detail: kedua kolom identitas (giliran kerja dan kode inspector) '
  + 'DIKECUALIKAN dari isRowFilled(). Karena kesebelas kolom itu mencakup kedua kolom teks '
  + 'bebas, filled_slots dapat MELEBIHI reading_count setiap metrik ukur — dan selisih itu '
  + 'benar, bukan ketidaksesuaian yang perlu dikoreksi.'

const NOTE_ABSENCES =
  'TIDAK ADA satu pun blok sebaran kategoris di payload ini, dan ketiadaan itu disengaja: '
  + 'stasiun ini tidak punya satu pun kolom berenum. TIDAK ADA blok lama berhenti — baris '
  + 'detail stasiun ini tidak punya satu pun kolomnya. TIDAK ADA acuan batas maupun penandaan '
  + 'nilai di luar batas dalam bentuk apa pun. Dan kolom giliran kerja tidak diterbitkan di '
  + 'layar ini, hanya di berkas ekspor, karena payload laporan tidak memuatnya sama sekali.'

const LINES_BY_ID: Record<string, { id: string; name: string }> = {
  'l-1': LINE_1,
  'l-2': LINE_2,
  'l-query': LINE_QUERY,
}

/**
 * Ringkasan yang blok `production_line`-nya DIBENTUK DARI LINE YANG DIMINTA,
 * bukan dipaku ke Line Satu: layar mendahulukan nama line dari SERVER atas
 * nama dari daftar lokalnya, jadi fixture yang mengabaikan argumennya akan
 * membuat setiap asersi "line mana yang berlaku" membaca jawaban yang salah
 * — dan selalu hijau.
 */
function summaryForScope(
  scope?: { productionLineId?: string | null },
  overrides: Record<string, unknown> = {},
): Record<string, unknown> {
  const line = scope?.productionLineId ? LINES_BY_ID[scope.productionLineId] : null

  return summaryFixture({ production_line: line ?? null, ...overrides })
}

/* ------------------------------------------------------------------ */
/* Auth store palsu                                                    */
/* ------------------------------------------------------------------ */

function asRole(
  role: 'operator' | 'supervisor' | 'mill_management' | 'admin',
  businessUnitId: string | null,
  userId = 'u-1',
): void {
  useAuthStoreMock.mockReturnValue({
    currentUser: {
      id: userId,
      username: role === 'operator' ? 'operator01' : `${role}01`,
      name: 'Pengguna Uji',
      role,
      business_unit_id: businessUnitId,
    },
    businessUnit: businessUnitId ? { id: businessUnitId, name: 'Mill Sungai Rambutan' } : null,
    logout: logoutMock,
  })
}

/* ------------------------------------------------------------------ */
/* Walker rekursif atas NAMA KUNCI                                     */
/* ------------------------------------------------------------------ */

/**
 * Seluruh NAMA KUNCI di dalam sebuah struktur, serapat apa pun ia tersarang.
 *
 * KETIADAAN DIASERSI ATAS KUNCI, BUKAN ATAS TEKS, dan di layar ini pembedaan
 * itu menentukan benar/salahnya asersi: payload-nya memuat PROSA yang menyebut
 * 'sebaran' dan 'batas' untuk menerangkan ketiadaannya sendiri, jadi
 * pemindaian `JSON.stringify` memerah pada payload yang justru BENAR.
 * `JSON.stringify` juga MEMBUANG kunci bernilai `undefined` — justru kunci
 * yang paling berbahaya, karena ia ADA pada objeknya dan akan ikut terkirim
 * begitu nilainya terisi.
 *
 * Bentuknya SAMA PERSIS dengan walker di processWaterReportRepo.spec.ts.
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
 * Sumber TANPA komentar. Berkas view ini MENJELASKAN panjang-panjang apa yang
 * tidak boleh dikerjakan di dalamnya, jadi audit statis atas sumber MENTAH
 * selalu memerah pada kalimat yang justru melarangnya.
 */
function stripComments(source: string): string {
  return source
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/<!--[\s\S]*?-->/g, '')
    .replace(/^\s*\/\/.*$/gm, '')
}

/* ------------------------------------------------------------------ */
/* Bantuan mount / interaksi                                           */
/* ------------------------------------------------------------------ */

/**
 * Setiap wrapper yang di-mount DILEPAS di afterEach.
 *
 * KENAPA INI WAJIB DI BERKAS INI, berbeda dari ketiga belas saudaranya:
 * layar ini memasang pendengar `online`/`offline` pada window, dan komponen
 * yang tidak dilepas TETAP mendengarkan. Sebuah test yang men-dispatch
 * `online` karena itu membangunkan pula komponen test SEBELUMNYA yang belum
 * pernah bootstrap (test luring), sehingga `fetchPeriods` terhitung DUA kali
 * — kegagalan yang tidak ada hubungannya dengan apa yang diuji, dan yang
 * urutannya tergantung urutan test. Dilepas di sini supaya setiap test
 * berdiri sendiri.
 */
const mountedWrappers: VueWrapper[] = []

async function mountView(): Promise<VueWrapper> {
  const wrapper = mount(LaporanProcessWaterView)

  mountedWrappers.push(wrapper)
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

/**
 * textContent MENTAH sebuah elemen, hanya di-trim.
 *
 * Dipakai untuk asersi VERBATIM (kasus 31 dan 46): kedua elemen prosa server
 * adalah SATU mustache tanpa satu kata tambahan di dalamnya, jadi kesamaan
 * penuh memang berlaku — dan `.text()` VTU pun hanya `textContent.trim()`,
 * tetapi ditulis eksplisit di sini supaya maksudnya tidak dapat salah dibaca
 * bila kelak VTU menormalkan spasi.
 */
function rawText(wrapper: VueWrapper, testid: string): string {
  return wrapper.get(`[data-testid="${testid}"]`).element.textContent?.trim() ?? ''
}

function exists(wrapper: VueWrapper, testid: string): boolean {
  return wrapper.find(`[data-testid="${testid}"]`).exists()
}

function cards(wrapper: VueWrapper): DOMWrapper<Element>[] {
  return wrapper.findAll('[data-testid^="pw-metric-card-"]')
}

/**
 * Kartu satu kolom, dicari lewat data-metric — bukan lewat posisinya.
 *
 * Tipe kembaliannya DIBIARKAN TERSIMPULKAN: `wrapper.get()` memulangkan
 * `Omit<DOMWrapper<Element>, 'exists'>` (ia memang tidak boleh ditanyai
 * `.exists()` — ia sudah melempar kalau tidak ada), dan menuliskan
 * `DOMWrapper<Element>` di sini membuat tsc menolaknya.
 */
function card(wrapper: VueWrapper, column: string) {
  return wrapper.get(`[data-metric="${column}"]`)
}

function block(wrapper: VueWrapper, testid: string) {
  return wrapper.get(`[data-testid="${testid}"]`)
}

/**
 * ELEMEN SATUAN sebuah kartu — atau null bila ia TIDAK DIRENDER.
 *
 * Kartu ber-satuan null tidak merender elemen ini sama sekali, dan itulah
 * yang diasersi: bukan "teksnya kosong", bukan "teksnya bukan '-'", melainkan
 * ELEMENNYA TIDAK ADA.
 */
function unitElement(wrapper: VueWrapper, column: string): DOMWrapper<Element> | null {
  const found = wrapper.find(`[data-testid="pw-metric-unit-${column}"]`)

  return found.exists() ? found : null
}

/** Urutan DOM seluruh testid — dipakai asersi POSISI, bukan keberadaan. */
function testidOrder(wrapper: VueWrapper): string[] {
  return wrapper.findAll('[data-testid]').map((el) => el.attributes('data-testid') ?? '')
}

/** Seluruh teks judul di halaman — dipakai asersi "nol judul 'Sebaran'". */
function headingTexts(wrapper: VueWrapper): string[] {
  return wrapper
    .findAll('h1, h2, h3, h4, h5, h6, [role="heading"]')
    .map((heading) => heading.text())
}

/** Seluruh teks judul KOLOM tabel — dipakai asersi "nol kolom shift". */
function columnHeaderTexts(wrapper: VueWrapper): string[] {
  return wrapper.findAll('th, [role="columnheader"]').map((header) => header.text())
}

function collapsibleToggles(wrapper: VueWrapper): DOMWrapper<Element>[] {
  return wrapper.findAll('[data-testid="collapsible-section-toggle"]')
}

function inspectorRows(wrapper: VueWrapper): DOMWrapper<Element>[] {
  // tfoot TIDAK ikut tercocok: barisnya ber-testid 'pw-inspector-total'.
  return wrapper.findAll('[data-testid^="pw-inspector-row-"]')
}

/**
 * LABEL OPSI YANG SEDANG TERPILIH pada sebuah <select>.
 *
 * TIDAK membaca `element.value`: Vue mengikat `:value="null"` pada opsi
 * placeholder lewat properti internalnya dan TIDAK menuliskan atribut
 * `value`, sehingga `select.value` di jsdom jatuh ke TEKS opsi itu — asersi
 * `value === ''` karena itu memerah pada keadaan yang justru benar.
 */
function selectedOptionText(wrapper: VueWrapper, testid: string): string {
  const select = wrapper.get(`[data-testid="${testid}"]`).element as HTMLSelectElement

  return select.selectedOptions[0]?.textContent?.trim() ?? ''
}

const NO_LINE_SELECTED = 'Pilih Production Line'
const NO_PERIOD_SELECTED = 'Pilih Periode'

/**
 * TESTID SEBARAN YANG DISALIN DARI LAPORAN YANG MEMANG PUNYA BLOK ITU.
 *
 * Keempat yang pertama adalah testid NYATA pada LaporanEngineRoomView.vue
 * (screen-159, layar yang persis sebelum ini) — bentuk yang paling mudah
 * tersalin ke sini, dan salinan itu akan salah. Sisanya adalah nama yang
 * disebut tech spec layar ini. Mengasersi ketiadaan atas nama yang TIDAK
 * pernah dipakai siapa pun adalah asersi yang tidak dapat gagal; inilah
 * sebabnya daftarnya disalin, bukan dikarang.
 */
const ENGINE_ROOM_DISTRIBUTION_TESTIDS = [
  'status-distribution-diesel-gen-1',
  'status-distribution-diesel-gen-2',
  'status-distribution-diesel-gen-1-table',
  'status-distribution-diesel-gen-2-table',
  'status-row',
  'status-value-label',
  'status-not-recorded',
  'status-recorded-total',
  'status-distribution-note',
  // Nama yang disebut tech spec screen-161.
  'pw-sebaran-action-taken-status',
  'pw-sebaran-table',
  'sebaran-table',
  'sebaran-row',
  'pw-status-distribution',
  'pw-distribution',
]

beforeEach(() => {
  pushMock.mockReset()
  logoutMock.mockClear()
  useAuthStoreMock.mockReset()
  apiClientGetMock.mockReset()

  Object.keys(routeQuery).forEach((key) => delete routeQuery[key])

  repoMocks.fetchLines.mockReset()
  repoMocks.fetchPeriods.mockReset()
  repoMocks.fetchSummary.mockReset()
  repoMocks.exportCsv.mockReset()
  repoMocks.saveCsvFile.mockReset()

  for (const spy of Object.values(productionLineMocks)) {
    spy.mockReset()
  }

  for (const spy of Object.values(localDbMocks)) {
    spy.mockReset()
  }

  window.localStorage.clear()
  /**
   * LAYAR INI TIDAK MEMILIHKAN LINE — bahkan ketika mill hanya punya satu.
   * Jadi fixture jalur-sukses WAJIB memberi ingatan perangkat lebih dulu;
   * tanpa ini tidak ada satu angka pun yang dirender, dan itu memang
   * perilaku yang benar.
   */
  window.localStorage.setItem(REMEMBERED_LINE_KEY, 'l-1')

  asRole('operator', 'bu-1')
  repoMocks.fetchLines.mockResolvedValue([LINE_1])
  repoMocks.fetchPeriods.mockResolvedValue([PERIOD, PERIOD_CLOSED])
  repoMocks.fetchSummary.mockImplementation(
    (_periodId: string, scope?: { productionLineId?: string | null }) =>
      Promise.resolve(summaryForScope(scope)),
  )
  repoMocks.exportCsv.mockResolvedValue(new Blob(['a,b']))
})

afterEach(() => {
  // DILEPAS LEBIH DULU, sebelum mock dipulihkan: onUnmounted melepas
  // pendengar online/offline, dan itu harus terjadi selagi spy window masih
  // yang dipasang test ini.
  while (mountedWrappers.length > 0) {
    mountedWrappers.pop()?.unmount()
  }

  vi.restoreAllMocks()
})

/* ================================================================== */
/* Skenario 1 + 2 + 5 — jalur sukses, kedua aktor, tanpa pemilih mill  */
/* ================================================================== */

describe('jalur sukses', () => {
  // unit_test_case 58 + skenario 1 + skenario 2 (aktor)
  it.each(['operator', 'supervisor'] as const)(
    'skenario 1: %s — seluruh bagian laporan terender, TEPAT satu permintaan per endpoint',
    async (role) => {
      asRole(role, 'bu-1')

      const wrapper = await mountView()
      await selectPeriod(wrapper, 'p-1')

      // TEPAT satu pemanggilan per endpoint data — bukan nol dan bukan dua.
      expect(repoMocks.fetchLines).toHaveBeenCalledTimes(1)
      expect(repoMocks.fetchPeriods).toHaveBeenCalledTimes(1)
      expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)
      expect(repoMocks.fetchSummary).toHaveBeenCalledWith('p-1', { productionLineId: 'l-1' })

      for (const testid of [
        'laporan-process-water-mobile',
        'period-meta',
        'period-status-badge',
        'pw-coverage',
        'pw-coverage-slots',
        'pw-coverage-percent',
        'pw-coverage-denominator',
        'pw-coverage-days',
        'pw-coverage-running-note',
        'pw-draft-note',
        'pw-note-filled-rule',
        'pw-filled-rule-consequence',
        'pw-empty-period-note',
        'pw-metric-block-aliran-air',
        'pw-metric-block-softener',
        'pw-metric-block-dosis-kimia',
        'pw-metric-block-air-umpan-boiler',
        'pw-softener-note',
        'pw-dosis-unit-note',
        'pw-no-threshold-note',
        'pw-metric-blocks-note',
        'pw-own-denominator-note',
        'pw-trend',
        'pw-recap-action-taken',
        'pw-recap-findings',
        'pw-inspector-recap',
        'pw-inspector-table',
        'pw-inspector-total',
        'pw-unit-recap',
        'pw-unit-table',
        'pw-daily-recap',
        'pw-daily-table',
        'pw-daily-total',
        'pw-absences',
        'pw-note-absences',
        'pw-no-downtime-note',
        'pw-no-shift-note',
        'pw-no-distribution-note',
        'pw-no-mill-picker-note',
        'pw-export-csv',
        'back-button',
      ]) {
        expect(exists(wrapper, testid), testid).toBe(true)
      }

      // SEMBILAN kartu dalam EMPAT blok, dengan satuannya masing-masing.
      expect(cards(wrapper)).toHaveLength(9)

      // Tidak ada pemilih mill di layar ini, dan itu bukan pemangkasan:
      // kedua aktornya terikat satu mill, diselesaikan server dari akun.
      expect(exists(wrapper, 'pw-mill-select')).toBe(false)
      expect(exists(wrapper, 'mill-select')).toBe(false)
      expect(text(wrapper, 'mill-current')).toContain('Mill Sungai Rambutan')

      // Nol galat.
      for (const testid of [
        'network-error',
        'error-message',
        'mill-not-linked',
        'production-line-rejected',
        'offline-notice',
        'no-periods',
        'empty-period',
      ]) {
        expect(exists(wrapper, testid), testid).toBe(false)
      }

      // Layar BACA SAJA: tidak ada satu pun medan input di luar kedua
      // pemilih <select>.
      expect(wrapper.findAll('input')).toHaveLength(0)
      expect(wrapper.findAll('textarea')).toHaveLength(0)
      expect(wrapper.findAll('form')).toHaveLength(0)
    },
  )

  // skenario 2 (bagian "kedua peran identik")
  it('skenario 1/2: kedua peran menghasilkan susunan dan teks yang SAMA PERSIS', async () => {
    asRole('operator', 'bu-1')
    const asOperator = await mountView()
    await selectPeriod(asOperator, 'p-1')

    asRole('supervisor', 'bu-1')
    const asSupervisor = await mountView()
    await selectPeriod(asSupervisor, 'p-1')

    // Ketiga rute data sudah menerima Operator sejak commit pertama
    // screen-160, jadi layar ini menambah PEMANGGIL — bukan varian layar.
    expect(asOperator.text()).toBe(asSupervisor.text())
    expect(cards(asOperator)).toHaveLength(cards(asSupervisor).length)
  })

  // skenario 5 — TANPA PEMILIH MILL, diasersi juga atas NAMA KUNCI params
  it('skenario 5: nol kontrol pemilih mill, dan business_unit_id tidak ada pada satu pun argumen', async () => {
    asRole('supervisor', 'bu-1')

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // Nama mill tampil sebagai KETERANGAN, bukan sebagai pilihan.
    expect(text(wrapper, 'mill-current')).toContain('Mill Sungai Rambutan')
    expect(exists(wrapper, 'pw-mill-select')).toBe(false)

    const comboboxes = wrapper
      .findAll('select, [role="combobox"]')
      .map((el) => el.attributes('data-testid') ?? '')

    // TEPAT dua pemilih: Production Line dan Periode. Tidak ada yang ketiga.
    expect(comboboxes.sort()).toEqual(['period-select', 'production-line-select'])
    expect(wrapper.text()).not.toMatch(/pilih mill|pilih business unit/i)

    // WALKER REKURSIF atas KUNCI setiap argumen yang diserahkan ke repo.
    const allArgs = [
      ...repoMocks.fetchLines.mock.calls,
      ...repoMocks.fetchPeriods.mock.calls,
      ...repoMocks.fetchSummary.mock.calls,
    ]

    for (const args of allArgs) {
      const keys = keyNamesOf(args)

      expect(keys).not.toContain('business_unit_id')
      expect(keys).not.toContain('businessUnitId')
      expect(keys).not.toContain('isAdmin')
    }

    // Dan keterangannya DINYATAKAN di layar, bukan hanya benar secara diam.
    expect(text(wrapper, 'pw-no-mill-picker-note')).toContain('Tidak ada pemilih Mill')
  })

  it('layar hanya berbicara lewat repo — nol pemanggilan apiClient dan repo line langsung', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(cards(wrapper)).toHaveLength(9)
    // fetchLines() repo laporan MENDELEGASIKAN ke productionLineRepo, dan
    // delegasinya ada DI REPO — bukan di view. View yang memanggil
    // productionLineRepo langsung akan tertangkap di sini.
    expect(productionLineMocks.fetchProductionLinesForReport).not.toHaveBeenCalled()
    expect(productionLineMocks.fetchCurrentProductionLines).not.toHaveBeenCalled()
    expect(apiClientGetMock).not.toHaveBeenCalled()
  })

  it('Back kembali ke Dashboard & Reporting', async () => {
    const wrapper = await mountView()
    await wrapper.get('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'dashboard-reporting' })
  })
})

/* ================================================================== */
/* unit_test_case 19 + 20 + skenario 6 — EMPAT BLOK DARI FIELD `group` */
/* ================================================================== */

describe('empat blok berjudul, diturunkan dari field group', () => {
  // unit_test_case 19 + skenario 6
  it('unit_test_case 19: judul blok berurutan tepat, dan sembilan kartu tersebar 2+2+2+3', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    const order = testidOrder(wrapper)

    expect(
      METRIC_BLOCKS.map((metricBlock) => order.indexOf(metricBlock.testid)),
    ).toEqual([...METRIC_BLOCKS.map((metricBlock) => order.indexOf(metricBlock.testid))].sort((a, b) => a - b))

    for (const metricBlock of METRIC_BLOCKS) {
      expect(block(wrapper, metricBlock.testid).text()).toContain(metricBlock.title)
    }

    // JUDUL BLOK sebagai JUDUL, dalam urutan itu — bukan sebagai teks di mana
    // pun pada halaman.
    const blockHeadings = headingTexts(wrapper).filter((heading) =>
      METRIC_BLOCKS.some((metricBlock) => metricBlock.title === heading),
    )

    expect(blockHeadings).toEqual(['Aliran Air', 'Softener', 'Dosis Kimia', 'Air Umpan Boiler'])

    expect(cards(wrapper)).toHaveLength(9)

    for (const metricBlock of METRIC_BLOCKS) {
      expect(
        block(wrapper, metricBlock.testid).findAll('[data-testid^="pw-metric-card-"]').length,
        metricBlock.testid,
      ).toBe(metricBlock.columns.length)
    }
  })

  // unit_test_case 20 + skenario 6
  it('unit_test_case 20: URUTAN KUNCI metrics DIACAK — keempat blok tetap kanonis dan keanggotaannya benar', async () => {
    const shuffled = [
      'boiler_feed_tds_ppm',
      'alum_dosing_kgh',
      'softener_outlet_hardness_ppm',
      'raw_water_flow_m3h',
      'boiler_feed_water_ph',
      'polymer_dosing_gh',
      'clarified_water_flow_m3h',
      'boiler_feed_tank_temp_c',
      'softener_inlet_ph',
    ]
    const source = metrics()

    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        metrics: Object.fromEntries(shuffled.map((column) => [column, source[column]])),
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    const blockHeadings = headingTexts(wrapper).filter((heading) =>
      METRIC_BLOCKS.some((metricBlock) => metricBlock.title === heading),
    )

    // URUTAN BLOK TIDAK BERGESER: ia ditetapkan di klien sebagai rantai
    // pengolahan air, sementara ISINYA datang dari field `group`.
    expect(blockHeadings).toEqual(['Aliran Air', 'Softener', 'Dosis Kimia', 'Air Umpan Boiler'])
    expect(cards(wrapper)).toHaveLength(9)

    // KEANGGOTAAN DIPERIKSA PER ELEMEN BLOK, bukan atas teks halaman: sebuah
    // kartu yang mendarat di blok yang salah tetap "ada di halaman".
    for (const metricBlock of METRIC_BLOCKS) {
      const inBlock = block(wrapper, metricBlock.testid)
        .findAll('[data-metric]')
        .map((el) => el.attributes('data-metric') ?? '')

      expect(inBlock.sort(), metricBlock.testid).toEqual([...metricBlock.columns].sort())
    }
  })

  it('kolom yang DIPINDAHKAN group-nya oleh server berpindah blok tanpa satu berkas klien disentuh', async () => {
    const moved = metrics()

    // Server memindahkan dosis alum ke blok Air Umpan Boiler. Layar yang
    // memaku daftar nama kolom per blok akan tetap menggambarnya di Dosis
    // Kimia — dan test ini gagal.
    moved.alum_dosing_kgh = { ...moved.alum_dosing_kgh, group: 'boiler_feed' }
    repoMocks.fetchSummary.mockResolvedValue(summaryFixture({ metrics: moved }))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(
      block(wrapper, 'pw-metric-block-air-umpan-boiler')
        .findAll('[data-metric]')
        .map((el) => el.attributes('data-metric')),
    ).toContain('alum_dosing_kgh')
    expect(
      block(wrapper, 'pw-metric-block-dosis-kimia')
        .findAll('[data-metric]')
        .map((el) => el.attributes('data-metric')),
    ).not.toContain('alum_dosing_kgh')
    // Dan satuannya tetap ikut kartunya, bukan ikut bloknya.
    expect(unitElement(wrapper, 'alum_dosing_kgh')?.text()).toBe('kg/jam')
  })

  it('kolom BARU dari migrasi muncul di blok yang sesuai group-nya, memakai nama kolomnya', async () => {
    const grown = {
      ...metrics(),
      turbidity_ntu: { min: 1, avg: 2, max: 3, reading_count: 5, group: 'water_flow' },
    }

    repoMocks.fetchSummary.mockResolvedValue(summaryFixture({ metrics: grown }))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(cards(wrapper)).toHaveLength(10)
    expect(
      block(wrapper, 'pw-metric-block-aliran-air')
        .findAll('[data-metric]')
        .map((el) => el.attributes('data-metric')),
    ).toContain('turbidity_ntu')
    // Label manusiawinya belum ada, jadi nama kolomnya yang dipakai —
    // kartunya TIDAK hilang dari layar.
    expect(text(wrapper, 'pw-metric-label-turbidity_ntu')).toBe('turbidity_ntu')
    // Dan satuannya TIDAK DIKARANG: mengarangnya membuat angkanya salah
    // dibaca.
    expect(unitElement(wrapper, 'turbidity_ntu')).toBeNull()
  })

  it('keempat blok dirender TERBUKA, tanpa satu kontrol lipat pun', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    for (const metricBlock of METRIC_BLOCKS) {
      // Melipatnya memperpendek gulir tetapi menyembunyikan penyebut tiap
      // kartu, dan penyebut itulah yang paling perlu dilihat di layar ini.
      expect(
        block(wrapper, metricBlock.testid)
          .find('[data-testid="collapsible-section-toggle"]')
          .exists(),
        metricBlock.testid,
      ).toBe(false)
    }

    // Yang DAPAT ditutup hanyalah KETIGA REKAP di bawah — bukan blok kartu.
    expect(collapsibleToggles(wrapper)).toHaveLength(3)
  })
})

/* ================================================================== */
/* unit_test_case 21 — SETIAP KARTU MEMBAWA PENYEBUTNYA SENDIRI         */
/* ================================================================== */

describe('penyebut per kartu', () => {
  // unit_test_case 21 + skenario 1
  it('unit_test_case 21: kesembilan pw-metric-count-{kolom} menampilkan count-nya SENDIRI', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    for (const column of METRIC_COLUMNS) {
      // ASERSI PER ELEMEN KARTU, bukan atas kontainer blok: elemen penyebut
      // memuat ANGKANYA SAJA, justru supaya ia dapat diasersi sendiri.
      expect(text(wrapper, `pw-metric-count-${column}`), column).toBe(
        new Intl.NumberFormat('id-ID').format(READING_COUNTS[column]),
      )
    }

    // Himpunan penyebut yang DIRENDER memuat sembilan nilai BERBEDA: penyebut
    // identik pada kesembilan kartu adalah cacat yang dicari, dan tidak satu
    // blok pun mewarisi penyebut blok lain.
    const rendered = METRIC_COLUMNS.map((column) => text(wrapper, `pw-metric-count-${column}`))

    expect(new Set(rendered).size).toBe(9)
  })

  it('angka min/avg/max dirender APA ADANYA, termasuk avg di LUAR rentang min..max', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // alum: min 1,8 — avg 9,9 — max 3,1. Mustahil dari perhitungan apa pun.
    expect(text(wrapper, 'pw-metric-min-alum_dosing_kgh')).toBe('1,8')
    expect(text(wrapper, 'pw-metric-avg-alum_dosing_kgh')).toBe('9,9')
    expect(text(wrapper, 'pw-metric-max-alum_dosing_kgh')).toBe('3,1')
  })

  it('nilai jauh di luar batas yang dikenal umum dirender POLOS, tanpa satu kelas penanda', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // pH masuk softener 3,2 dan TDS air umpan boiler 9800 — keduanya punya
    // batas yang dikenal SANGAT luas DI LUAR aplikasi ini.
    expect(text(wrapper, 'pw-metric-min-softener_inlet_ph')).toBe('3,20')
    expect(text(wrapper, 'pw-metric-max-boiler_feed_tds_ppm')).toBe('9.800')

    // DISISIR ATAS NAMA KELAS pada kartu, bukan atas frasa: kalimat yang
    // menyatakan ketiadaan penandaannya sendiri memuat frasa 'di luar batas'.
    const flagged = cards(wrapper)
      .flatMap((cardEl) => [
        cardEl.attributes('class') ?? '',
        ...cardEl.findAll('[class]').map((child) => child.attributes('class') ?? ''),
      ])
      .flatMap((className) => className.split(/\s+/))
      .filter((name) =>
        /warning|danger|error|alert|violation|out-of-range|breach|critical|over-limit/.test(name),
      )

    expect(flagged).toEqual([])
    // Dan tidak satu elemen target pun tumbuh di kartunya.
    expect(wrapper.findAll('[data-testid^="pw-metric-target"]')).toHaveLength(0)
  })
})

/* ================================================================== */
/* unit_test_case 22-26 + skenario 7 + 8 — SATUAN PER KARTU            */
/* ================================================================== */

describe('satuan dicetak pada kartunya masing-masing', () => {
  /**
   * unit_test_case 22 — KASUS TERSENDIRI, TERPISAH DARI POLIMER.
   *
   * Satu asersi atas elemen blok 'dosis' TIDAK memenuhi kasus ini: alum
   * kg/jam dan polimer g/jam berada di blok yang sama, skalanya berbeda
   * SERIBU KALI, dan di ponsel pembaca melihat SATU KARTU pada satu waktu.
   */
  it('unit_test_case 22: kartu DOSIS ALUM mencetak kg/jam pada KARTUNYA SENDIRI', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    const unitAlum = unitElement(wrapper, 'alum_dosing_kgh')

    expect(unitAlum, 'elemen satuan kartu alum wajib dirender').not.toBeNull()
    // KESAMAAN PENUH, bukan substring: 'kg/jam' yang terbungkus kalimat lain
    // di dalam elemen satuan bukan pil satuan.
    expect(unitAlum!.text()).toBe('kg/jam')
    // Dan elemennya BERADA DI DALAM kartu alum, bukan di judul bloknya.
    expect(card(wrapper, 'alum_dosing_kgh').find('[data-testid="pw-metric-unit-alum_dosing_kgh"]').exists()).toBe(true)
  })

  /**
   * unit_test_case 23 — KASUS TERSENDIRI, TERPISAH DARI ALUM, dan ia memeriksa
   * satu hal lagi: kartu polimer TIDAK BOLEH memuat 'kg/jam'. Menuliskan
   * polimer sebagai 0,42 kg/jam demi kerapian menyembunyikan ketelitian yang
   * dicatat di lapangan.
   */
  it('unit_test_case 23: kartu DOSIS POLIMER mencetak g/jam, dan TIDAK memuat kg/jam', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    const unitPolimer = unitElement(wrapper, 'polymer_dosing_gh')

    expect(unitPolimer, 'elemen satuan kartu polimer wajib dirender').not.toBeNull()
    expect(unitPolimer!.text()).toBe('g/jam')
    expect(unitPolimer!.text()).not.toContain('kg/jam')
    // Dilingkup KE KARTUNYA: 'kg/jam' memang ada di halaman (kartu alum dan
    // keterangan blok), jadi asersi tingkat halaman tidak bermakna di sini.
    expect(card(wrapper, 'polymer_dosing_gh').find('[data-testid="pw-metric-unit-polymer_dosing_gh"]').exists()).toBe(true)
  })

  it('kedua kartu dosis berada di blok yang SAMA dan satuannya BERBEDA — dua elemen, dua nilai', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    const dosis = block(wrapper, 'pw-metric-block-dosis-kimia')

    expect(dosis.findAll('[data-testid^="pw-metric-card-"]')).toHaveLength(2)

    const unitsInBlock = dosis
      .findAll('[data-testid^="pw-metric-unit-"]')
      .map((el) => el.text())

    // DUA elemen satuan di dalam satu blok, bukan satu keterangan bersama.
    expect(unitsInBlock).toEqual(['kg/jam', 'g/jam'])
    // Dan keterangannya DINYATAKAN di dalam blok itu, di tempat yang
    // diterangkannya — bukan sekali di atas halaman.
    expect(dosis.find('[data-testid="pw-dosis-unit-note"]').exists()).toBe(true)
    expect(text(wrapper, 'pw-dosis-unit-note')).toContain('seribu kali')
  })

  /**
   * unit_test_case 24 — dan TRAP-nya dicatat di sini.
   *
   * Asersi `not.toContain('pH')` tingkat KARTU MEMERAH pada layar yang BENAR:
   * label kartunya memang berbunyi 'pH Masuk Softener'. Yang diasersi karena
   * itu adalah ELEMEN satuannya (yang tidak boleh ada) dan ketiadaan
   * '-'/'—'/satuan karangan DI POSISI SATUAN — bukan ketiadaan kata 'pH' di
   * dalam kartu.
   */
  it('unit_test_case 24: kartu pH MASUK SOFTENER tidak merender elemen satuan sama sekali', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(unitElement(wrapper, 'softener_inlet_ph')).toBeNull()
    expect(wrapper.find('[data-testid="pw-metric-unit-softener_inlet_ph"]').exists()).toBe(false)

    const labelRow = card(wrapper, 'softener_inlet_ph').get('.metric-label-row')

    // Di dalam baris label hanya ada LABELNYA — tidak ada pil satuan, tidak
    // ada '-', tidak ada '—', dan tidak ada satuan karangan.
    expect(labelRow.text()).toBe('pH Masuk Softener')
    expect(labelRow.findAll('[data-testid^="pw-metric-unit-"]')).toHaveLength(0)
    for (const invented of ['-', '—', 'ppm', 'unit', 'm³/jam']) {
      expect(labelRow.text(), invented).not.toContain(invented)
    }
  })

  /** unit_test_case 25 — KASUS TERPISAH dari pH softener, kartu yang berbeda. */
  it('unit_test_case 25: kartu pH AIR UMPAN BOILER tidak merender elemen satuan sama sekali', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(unitElement(wrapper, 'boiler_feed_water_ph')).toBeNull()
    expect(wrapper.find('[data-testid="pw-metric-unit-boiler_feed_water_ph"]').exists()).toBe(false)

    const labelRow = card(wrapper, 'boiler_feed_water_ph').get('.metric-label-row')

    expect(labelRow.text()).toBe('pH Air Umpan Boiler')
    expect(labelRow.findAll('[data-testid^="pw-metric-unit-"]')).toHaveLength(0)
    for (const invented of ['-', '—', 'ppm', 'unit', '°C']) {
      expect(labelRow.text(), invented).not.toContain(invented)
    }

    // Dan keterangannya dinyatakan di dalam bloknya sendiri.
    expect(text(wrapper, 'pw-softener-note')).toContain('tidak punya pil satuan')
  })

  // skenario 8 — kedua kartu pH, DUA asersi, dalam satu test skenario.
  it('skenario 8: TEPAT tujuh elemen satuan di seluruh layar — kesembilan kartu, dua tanpa satuan', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    const withUnit = METRIC_COLUMNS.filter((column) => EXPECTED_UNITS[column] !== null)
    const withoutUnit = METRIC_COLUMNS.filter((column) => EXPECTED_UNITS[column] === null)

    expect(withUnit).toHaveLength(7)
    expect(withoutUnit).toEqual(['softener_inlet_ph', 'boiler_feed_water_ph'])

    expect(wrapper.findAll('[data-testid^="pw-metric-unit-"]')).toHaveLength(7)

    for (const column of withUnit) {
      expect(unitElement(wrapper, column)?.text(), column).toBe(EXPECTED_UNITS[column])
    }

    for (const column of withoutUnit) {
      expect(unitElement(wrapper, column), column).toBeNull()
    }
  })

  /**
   * unit_test_case 26 — SATUAN DICETAK APA ADANYA, termasuk '³' dan '°'.
   *
   * Dua jalur diuji dalam satu test karena keduanya WAJIB sepakat: payload
   * SERVER tidak menerbitkan `unit` sama sekali (satuannya dari METRIC_META,
   * salinan $metrikMeta blade screen-160), sementara kosakata TECH SPEC
   * menerbitkannya. Kalau keduanya tidak menghasilkan string yang sama
   * persis, ponsel dan web mencetak satuan berbeda untuk payload yang sama.
   */
  it('unit_test_case 26: m³/jam dan °C dicetak IDENTIK, tanpa normalisasi — pada KEDUA kosakata payload', async () => {
    const fromMeta = await mountView()
    await selectPeriod(fromMeta, 'p-1')

    expect(unitElement(fromMeta, 'raw_water_flow_m3h')?.text()).toBe('m³/jam')
    expect(unitElement(fromMeta, 'boiler_feed_tank_temp_c')?.text()).toBe('°C')
    // TIDAK dinormalkan.
    expect(unitElement(fromMeta, 'raw_water_flow_m3h')?.text()).not.toBe('m3/jam')
    expect(unitElement(fromMeta, 'boiler_feed_tank_temp_c')?.text()).not.toBe('C')

    // KOSAKATA TECH SPEC: `unit` diterbitkan eksplisit, dan NILAI SERVER
    // MENANG atas tabel view.
    const specMetrics = Object.fromEntries(
      METRIC_COLUMNS.map((column) => [
        column,
        { ...metrics()[column], unit: EXPECTED_UNITS[column], count: READING_COUNTS[column] },
      ]),
    )

    repoMocks.fetchSummary.mockResolvedValue(summaryFixture({ metrics: specMetrics }))

    const fromPayload = await mountView()
    await selectPeriod(fromPayload, 'p-1')

    for (const column of METRIC_COLUMNS) {
      const expected = EXPECTED_UNITS[column]

      if (expected === null) {
        // `unit` yang DIKIRIM null tetap null — bukan jatuh ke tabel view.
        expect(unitElement(fromPayload, column), column).toBeNull()
      } else {
        expect(unitElement(fromPayload, column)?.text(), column).toBe(expected)
      }
    }
  })

  /**
   * DIVERGENSI 2 — NAMA KUNCI. Tech spec menamai kolomnya `alum_dose`,
   * `polymer_dose`, `boiler_feed_ph`. Testid satuannya karena itu ada dalam
   * KEDUA ejaan, dan keduanya wajib mencetak satuan yang sama.
   */
  it('kosakata TECH SPEC (alum_dose / polymer_dose / boiler_feed_ph) mencetak satuan yang sama', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        metrics: {
          raw_water_flow: { min: 96.4, avg: 118.42, max: 141.8, count: 12, group: 'aliran' },
          clarified_water_flow: { min: null, avg: null, max: null, count: 0, group: 'aliran' },
          softener_inlet_ph: { min: 3.2, avg: 6.85, max: 7.4, count: 24, group: 'softener' },
          softener_outlet_hardness: { min: 12, avg: 28.5, max: 44, count: 3, group: 'softener' },
          alum_dose: { min: 1.8, avg: 2.5, max: 3.1, count: 20, unit: 'kg/jam', group: 'dosis' },
          polymer_dose: { min: 1.9, avg: 2.4, max: 3, count: 20, unit: 'g/jam', group: 'dosis' },
          boiler_feed_tank_temp: { min: 68.2, avg: 81.5, max: 92.9, count: 8, group: 'air_umpan_boiler' },
          boiler_feed_ph: { min: 8.4, avg: 9.95, max: 11.2, count: 15, unit: null, group: 'air_umpan_boiler' },
          boiler_feed_tds: { min: 1200, avg: 4100, max: 9800, count: 7, group: 'air_umpan_boiler' },
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // Kesembilan kartu, keempat blok — kosakata yang berbeda, layar yang sama.
    expect(cards(wrapper)).toHaveLength(9)
    expect(
      headingTexts(wrapper).filter((heading) =>
        METRIC_BLOCKS.some((metricBlock) => metricBlock.title === heading),
      ),
    ).toEqual(['Aliran Air', 'Softener', 'Dosis Kimia', 'Air Umpan Boiler'])

    // DUA ASERSI TERPISAH, satu per kartu dosis — tuntutan yang sama dengan
    // kasus 22 dan 23, pada ejaan kunci yang lain.
    expect(unitElement(wrapper, 'alum_dose')?.text()).toBe('kg/jam')
    expect(unitElement(wrapper, 'polymer_dose')?.text()).toBe('g/jam')
    expect(unitElement(wrapper, 'polymer_dose')?.text()).not.toContain('kg/jam')

    // Dan kedua kartu pH tetap TANPA elemen satuan.
    expect(unitElement(wrapper, 'softener_inlet_ph')).toBeNull()
    expect(unitElement(wrapper, 'boiler_feed_ph')).toBeNull()

    // Penyebut dibaca dari `count` (nama tech spec), bukan dari
    // `reading_count` yang tidak ada di sini.
    expect(text(wrapper, 'pw-metric-count-alum_dose')).toBe('20')
    expect(text(wrapper, 'pw-metric-count-boiler_feed_tds')).toBe('7')
  })
})

/* ================================================================== */
/* unit_test_case 27 + 28 + skenario 19 — KARTU TETAP ADA              */
/* ================================================================== */

describe('kartu yang tidak terisi tetap dirender', () => {
  // unit_test_case 27
  it('unit_test_case 27: kolom yang tidak pernah terisi TETAP di bloknya, "tidak tersedia", count 0', async () => {
    const withEmptyPolymer = metrics()

    withEmptyPolymer.polymer_dosing_gh = {
      min: null,
      avg: null,
      max: null,
      reading_count: 0,
      group: 'chemical_dosing',
    }
    repoMocks.fetchSummary.mockResolvedValue(summaryFixture({ metrics: withEmptyPolymer }))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // Kartunya TETAP ADA, dan tetap DI DALAM blok Dosis Kimia.
    expect(
      block(wrapper, 'pw-metric-block-dosis-kimia')
        .findAll('[data-metric]')
        .map((el) => el.attributes('data-metric')),
    ).toContain('polymer_dosing_gh')

    // 'tidak tersedia', BUKAN '0' sebagai nilai: nol berarti "terukur dan
    // hasilnya nol", tidak tersedia berarti "tidak ada yang diukur".
    expect(text(wrapper, 'pw-metric-avg-polymer_dosing_gh')).toBe('tidak tersedia')
    expect(text(wrapper, 'pw-metric-min-polymer_dosing_gh')).toBe('tidak tersedia')
    expect(text(wrapper, 'pw-metric-max-polymer_dosing_gh')).toBe('tidak tersedia')
    expect(text(wrapper, 'pw-metric-count-polymer_dosing_gh')).toBe('0')
    expect(exists(wrapper, 'pw-metric-unavailable-polymer_dosing_gh')).toBe(true)

    // Dan satuannya TETAP tercetak: kartu tanpa pembacaan tetap kartu yang
    // satuannya diketahui.
    expect(unitElement(wrapper, 'polymer_dosing_gh')?.text()).toBe('g/jam')

    // Kedelapan kartu lain TIDAK terpengaruh.
    expect(cards(wrapper)).toHaveLength(9)
    expect(exists(wrapper, 'pw-metric-unavailable-alum_dosing_kgh')).toBe(false)
  })

  // unit_test_case 28 + skenario 19
  it('unit_test_case 28/skenario 19: periode tanpa satu pun pembacaan — sembilan kartu tetap, kelengkapan tetap', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        has_data: false,
        coverage: {
          filled_slots: 0,
          expected_slots: 480,
          // Penyebutnya TERBENTUK, dan yang terukur memang nol slot — jadi
          // 0,0% dan BUKAN '—'.
          coverage_percent: 0,
          process_water_unit_count: 2,
          slots_per_unit_per_day: 24,
          days_in_period: 10,
          days_counted: 10,
          period_running: false,
          _note_filled_rule: NOTE_FILLED_RULE,
        },
        metrics: emptyMetrics(),
        text_entries: { action_taken_status: [], findings: [] },
        inspector_recap: { entries: [], not_recorded: 0, recorded_slots: 0 },
        daily_trend: { raw_water_flow_m3h: [] },
        by_unit: [],
        daily: [],
        daily_total: { reading_rows: 0 },
        total: { days_with_records: 0, reading_rows: 0 },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // KESEMBILAN kartu TETAP dirender — justru itu yang memperlihatkan bahwa
    // parameternya ADA dan tidak terukur.
    expect(cards(wrapper)).toHaveLength(9)

    for (const column of METRIC_COLUMNS) {
      expect(text(wrapper, `pw-metric-avg-${column}`), column).toBe('tidak tersedia')
      expect(text(wrapper, `pw-metric-count-${column}`), column).toBe('0')
      expect(exists(wrapper, `pw-metric-unavailable-${column}`), column).toBe(true)
    }

    // BLOK KELENGKAPAN TETAP TAMPIL.
    expect(exists(wrapper, 'pw-coverage')).toBe(true)
    expect(text(wrapper, 'pw-coverage-percent')).toBe('0,0%')
    expect(text(wrapper, 'pw-coverage-slots')).toContain('0 dari 480 slot')

    // KEDUA REKAP TEKS MENYATAKAN tidak ada catatan — bukan daftar kosong
    // tanpa penjelasan, dan bukan section yang hilang.
    expect(exists(wrapper, 'pw-recap-action-taken')).toBe(true)
    expect(exists(wrapper, 'pw-recap-findings')).toBe(true)
    expect(text(wrapper, 'pw-recap-action-taken-empty')).toContain('Tidak ada satu pun catatan')
    expect(text(wrapper, 'pw-recap-findings-empty')).toContain('Tidak ada satu pun temuan')

    // Dan keadaannya dinyatakan sebab-akibatnya.
    expect(text(wrapper, 'empty-period')).toContain('penyebut nol')
    // Tren tidak menggambar garis nol.
    expect(exists(wrapper, 'pw-trend-empty')).toBe(true)
    expect(wrapper.findAll('[data-testid^="pw-trend-col-"]')).toHaveLength(0)
  })
})

/* ================================================================== */
/* unit_test_case 29-33 + skenario 11 + 12 + 16 + 17 + 18 — KELENGKAPAN */
/* ================================================================== */

describe('kelengkapan pencatatan', () => {
  // unit_test_case 32 + skenario 16
  it('unit_test_case 32: blok kelengkapan berada PALING ATAS, mendahului kartu pertama dalam urutan DOM', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    const order = testidOrder(wrapper)
    const coverageIndex = order.indexOf('pw-coverage')
    const firstCardIndex = order.findIndex((testid) => testid.startsWith('pw-metric-card-'))
    const firstBlockIndex = order.indexOf('pw-metric-block-aliran-air')

    expect(coverageIndex).toBeGreaterThan(-1)
    expect(firstCardIndex).toBeGreaterThan(-1)

    // ASERSI POSISI, bukan sekadar keberadaan. Di layar sempit ia
    // satu-satunya bagian yang pasti terlihat tanpa menggulir, dan ia
    // menentukan apakah sembilan angka di bawahnya layak dipercaya.
    expect(coverageIndex).toBeLessThan(firstCardIndex)
    expect(coverageIndex).toBeLessThan(firstBlockIndex)
    // Dan ia juga mendahului KETIGA rekap serta blok tren.
    for (const testid of ['pw-trend', 'pw-recap-action-taken', 'pw-inspector-recap', 'pw-absences']) {
      expect(coverageIndex, testid).toBeLessThan(order.indexOf(testid))
    }
  })

  it('mencetak KETIGA angka pembentuk penyebutnya, bukan hanya persennya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(text(wrapper, 'pw-coverage-slots')).toContain('96 dari 480 slot')
    expect(text(wrapper, 'pw-coverage-denominator')).toContain('2 unit')
    expect(text(wrapper, 'pw-coverage-denominator')).toContain('7 hari')
    expect(text(wrapper, 'pw-coverage-denominator')).toContain('24 slot')
    // Record draft IKUT terhitung, dan hal itu DINYATAKAN.
    expect(text(wrapper, 'pw-draft-note')).toContain('draft')
  })

  it('persen cakupan DIRENDER APA ADANYA dari server, bukan diturunkan di klien', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // 96/480 = 20,0 dan server mengirim 77,7. Dilingkup KE ELEMENNYA, yang
    // memuat PERSENNYA SAJA.
    expect(text(wrapper, 'pw-coverage-percent')).toBe('77,7%')
    expect(text(wrapper, 'pw-coverage-percent')).not.toContain('20,0')
  })

  it('penyebut yang DITERBITKAN server tidak diturunkan ulang dari unit x hari x slot', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // 2 x 7 x 24 = 336, dan server mengirim 480. Yang dirender 480.
    expect(text(wrapper, 'pw-coverage-slots')).toContain('480')
    expect(text(wrapper, 'pw-coverage-slots')).not.toContain('336')
  })

  // unit_test_case 30 + skenario 11
  it('unit_test_case 30/skenario 11: filled_slots MELEBIHI penyebut SETIAP kartu, dirender apa adanya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    const counts = METRIC_COLUMNS.map((column) => READING_COUNTS[column])

    expect(Math.max(...counts)).toBe(24)

    // 96 DIRENDER APA ADANYA: tidak di-clamp ke 24, tidak disembunyikan.
    expect(text(wrapper, 'pw-coverage-slots')).toContain('96')
    expect(text(wrapper, 'pw-coverage-slots')).not.toContain('24 dari')

    // Dan yang menerangkannya adalah kalimat SERVER.
    expect(exists(wrapper, 'pw-note-filled-rule')).toBe(true)
    expect(text(wrapper, 'pw-note-filled-rule')).toContain('SEBELAS kolom')

    // TANPA peringatan 'data tidak konsisten' dalam bentuk apa pun.
    expect(wrapper.text()).not.toMatch(/tidak konsisten|inkonsisten|anomali|data janggal/i)
    expect(wrapper.findAll('[data-testid^="pw-inconsistent"]')).toHaveLength(0)
  })

  /**
   * unit_test_case 31 — KESAMAAN PENUH, bukan substring.
   *
   * Elemen ini memuat KALIMAT SERVER SAJA, tanpa satu kata tambahan di
   * dalamnya (satu mustache), justru supaya asersi verbatim memang berlaku.
   * Kalimat yang disusun dua kali di dua platform adalah kalimat yang akan
   * berselisih.
   */
  it('unit_test_case 31: pw-note-filled-rule dirender VERBATIM — kesamaan string penuh', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(rawText(wrapper, 'pw-note-filled-rule')).toBe(NOTE_FILLED_RULE)
  })

  it('kalimat server yang BERBEDA ikut berubah di layar — buktinya ia dibaca, bukan dipaku', async () => {
    const lain =
      'Kalimat aturan yang berbeda dari server, dirender apa adanya tanpa disusun ulang klien.'

    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        coverage: { ...(summaryFixture().coverage as object), _note_filled_rule: lain },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(rawText(wrapper, 'pw-note-filled-rule')).toBe(lain)
  })

  // skenario 12 — konsekuensi BERPASANGAN DAN BERLAWANAN
  it('skenario 12: konsekuensi kolom identitas DINYATAKAN, berpasangan dan berlawanan', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    const consequence = text(wrapper, 'pw-filled-rule-consequence')

    // Slot yang HANYA mengisi kolom teks bebas TETAP terhitung terisi…
    expect(consequence).toContain('TETAP terhitung terisi')
    // …sementara slot yang HANYA mengisi kolom identitas TIDAK.
    expect(consequence).toContain('TIDAK terhitung terisi')
    expect(consequence).toContain('giliran kerja')
    expect(consequence).toContain('kode inspector')
    // Dan selisihnya dinyatakan BUKAN galat.
    expect(consequence).toContain('bukan galat dan tidak dikoreksi')
  })

  // unit_test_case 29 + skenario 18
  it('unit_test_case 29/skenario 18: percent null → "—" plus "belum ada slot yang diharapkan", TANPA 0,0%', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          unit_count: 0,
          days_counted: 0,
          slots_per_unit_per_day: 24,
          // Kosakata TECH SPEC: null ketika expected_slots nol.
          percent: null,
          _note_filled_rule: NOTE_FILLED_RULE,
        },
        metrics: emptyMetrics(),
        has_data: false,
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(text(wrapper, 'pw-coverage-percent')).toBe('—')
    expect(exists(wrapper, 'pw-coverage-no-expected-note')).toBe(true)
    expect(text(wrapper, 'pw-coverage-no-expected-note')).toMatch(
      /belum ada slot yang diharapkan/i,
    )

    // DAN elemen kelengkapan SELURUHNYA tidak memuat '0,0%' maupun '0%' —
    // nol persen akan terbaca seolah pencatatan gagal, padahal yang belum ada
    // adalah penyebutnya.
    const coverageText = text(wrapper, 'pw-coverage')

    expect(coverageText).not.toContain('0,0%')
    expect(coverageText).not.toContain('0%')
    // Bilah kemajuan pun tidak digambar tanpa penyebut.
    expect(wrapper.findAll('.completeness-track')).toHaveLength(0)
  })

  it('percent null TIDAK ikut membuat persen "—" ketika penyebutnya ADA — 0,0% itu benar di sana', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        coverage: {
          filled_slots: 0,
          expected_slots: 480,
          coverage_percent: 0,
          process_water_unit_count: 2,
          slots_per_unit_per_day: 24,
          days_in_period: 10,
          days_counted: 10,
          period_running: false,
          _note_filled_rule: NOTE_FILLED_RULE,
        },
        metrics: emptyMetrics(),
        has_data: false,
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // Periode yang PUNYA penyebut tetapi tanpa satu slot terisi menghasilkan
    // persen NOL — dan itu benar, karena penyebutnya ada. Pembedaan ini
    // persis yang membuat pemetaan '—' bukan "null berarti nol".
    expect(text(wrapper, 'pw-coverage-percent')).toBe('0,0%')
    expect(exists(wrapper, 'pw-coverage-no-expected-note')).toBe(false)
  })

  // unit_test_case 33 + skenario 17
  it('unit_test_case 33/skenario 17: periode berjalan — days_counted apa adanya + keterangan berhenti di hari ini', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // days_counted 7 dari 10 hari periode, DIRENDER APA ADANYA: klien tidak
    // menghitung hari, dan jam perangkat tidak pernah ikut memutuskan.
    expect(text(wrapper, 'pw-coverage-days')).toContain('7 hari')
    expect(text(wrapper, 'pw-coverage-days')).toContain('dari 10 hari periode')

    expect(exists(wrapper, 'pw-coverage-running-note')).toBe(true)
    expect(text(wrapper, 'pw-coverage-running-note')).toContain(
      'Dihitung sampai HARI INI, karena periode masih berjalan',
    )
    // Keterangan "belum mulai" TIDAK ikut muncul.
    expect(exists(wrapper, 'pw-coverage-not-started-note')).toBe(false)
  })

  it('days_counted 0 DIPERIKSA LEBIH DULU: periode belum mulai, bukan periode berjalan', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          coverage_percent: 0,
          process_water_unit_count: 0,
          slots_per_unit_per_day: 24,
          days_in_period: 10,
          days_counted: 0,
          // Server menandai periode yang BELUM MULAI sebagai masih berjalan
          // JUGA — memeriksa yang kedua lebih dulu membuat keterangan "belum
          // mulai" tidak pernah terender. Cacat nyata, diperbaiki screen-148.
          period_running: true,
          _note_filled_rule: NOTE_FILLED_RULE,
        },
        metrics: emptyMetrics(),
        has_data: false,
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(exists(wrapper, 'pw-coverage-not-started-note')).toBe(true)
    expect(text(wrapper, 'pw-coverage-not-started-note')).toContain('Periode belum mulai')
    expect(exists(wrapper, 'pw-coverage-running-note')).toBe(false)
    expect(text(wrapper, 'pw-coverage-percent')).toBe('—')
  })
})

/* ================================================================== */
/* unit_test_case 34 + 35 + 36 + skenario 9 — NOL SEBARAN KATEGORIS    */
/* ================================================================== */

describe('nol sebaran kategoris', () => {
  /**
   * unit_test_case 34 — DIASERSI ATAS TESTID, dan testid-nya DISALIN dari
   * laporan yang MEMANG punya blok itu (Engine Room mobile). Mengasersi
   * ketiadaan atas nama yang tidak pernah dipakai siapa pun adalah asersi
   * yang tidak dapat gagal.
   */
  it('unit_test_case 34: nol testid sebaran — termasuk testid NYATA yang disalin dari Engine Room', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    for (const testid of ENGINE_ROOM_DISTRIBUTION_TESTIDS) {
      expect(wrapper.findAll(`[data-testid="${testid}"]`), testid).toHaveLength(0)
    }

    // Dan SATU-SATUNYA testid di seluruh layar yang menyebut sebaran adalah
    // yang MENYATAKAN ketiadaannya — bukan sebuah blok sebaran. Diasersi
    // dengan kesamaan daftar, bukan dengan daftar kosong: daftar kosong akan
    // menuntut kalimat penjelasnya dihapus, dan kalimat itu justru diminta
    // spesifikasi.
    expect(
      testidOrder(wrapper).filter((testid) => /sebaran|distribution/i.test(testid)),
    ).toEqual(['pw-no-distribution-note'])
  })

  /**
   * unit_test_case 35 — NOL JUDUL 'Sebaran'.
   *
   * PEMINDAIAN PROSA TIDAK DIPAKAI, dan tidak boleh: layar ini WAJIB merender
   * kalimat yang menyebut kata 'sebaran' untuk menerangkan ketiadaannya
   * sendiri, dan payload-nya pun memuat kata itu di `_note_absences`. Asersi
   * di bawah karena itu menyasar JUDUL — dan test berikutnya membuktikan
   * prosanya memang ADA, supaya tidak seseorang "memperbaiki" layar dengan
   * menghapus kalimatnya.
   */
  it('unit_test_case 35: nol JUDUL yang dimulai "Sebaran" di seluruh layar', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    const headings = headingTexts(wrapper)

    expect(headings.length).toBeGreaterThan(5)
    expect(headings.filter((heading) => /^Sebaran/.test(heading))).toEqual([])
    // Judul Engine Room yang akan tersalin ke sini, tepat seperti bunyinya.
    expect(headings).not.toContain('Sebaran Status Genset 1')
    expect(headings).not.toContain('Sebaran Status Genset 2')
  })

  it('PROSA yang menerangkan ketiadaan sebaran memang ADA — ia bukan yang harus dihapus', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // Inilah sebabnya pemindaian prosa dilarang di kedua test di atas: kata
    // 'sebaran' WAJIB muncul di sini, dan di payload _note_absences pula.
    expect(text(wrapper, 'pw-no-distribution-note')).toContain('sebaran kategoris')
    expect(text(wrapper, 'pw-no-distribution-note')).toContain('tidak punya satu pun kolom')
    expect(rawText(wrapper, 'pw-note-absences')).toContain('sebaran')
  })

  // unit_test_case 36 + skenario 9
  it('unit_test_case 36/skenario 9: tiga entri BERTEKS IDENTIK dirender TIGA baris, bukan satu berjumlah 3', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    const rows = wrapper.findAll('[data-testid^="pw-action-taken-row-"]')

    expect(rows).toHaveLength(3)

    // Masing-masing membawa tanggal dan slot waktunya SENDIRI — dibaca dari
    // atribut barisnya, bukan dari teks kontainer.
    expect(
      rows.map((row) => `${row.attributes('data-date')} ${row.attributes('data-time-slot')}`),
    ).toEqual([`${DAY_A} ${SHARED_SLOT}`, `${DAY_A} 14:00`, `${DAY_B} 21:00`])

    // Dan ketiganya berteks sama persis — tetap tiga baris, bukan satu baris
    // bernilai jumlah tiga: yang sama persis hari ini belum tentu sama persis
    // besok.
    expect(rows.map((row) => row.get('.note-text').text())).toEqual([
      'Sudah ditangani',
      'Sudah ditangani',
      'Sudah ditangani',
    ])

    // TIDAK ADA satu sel pun yang menjadi "nilai + jumlah slot + porsi".
    expect(block(wrapper, 'pw-recap-action-taken').findAll('table')).toHaveLength(0)
    expect(text(wrapper, 'pw-action-taken-note')).toContain('bukan sebagai sebaran')
  })
})

/* ================================================================== */
/* unit_test_case 37 + 38 + 39 + skenario 10 — DUA REKAP TEKS           */
/* ================================================================== */

describe('dua rekap teks pada dua bagian terpisah', () => {
  // unit_test_case 37 + skenario 10
  it('unit_test_case 37/skenario 10: satu slot yang mengisi KEDUA kolom muncul di KEDUA rekap', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    const actionSection = block(wrapper, 'pw-recap-action-taken')
    const findingSection = block(wrapper, 'pw-recap-findings')

    // DUA ELEMEN YANG BERBEDA, bukan satu bagian yang sama dibaca dua kali.
    expect(actionSection.element).not.toBe(findingSection.element)

    // PENCARIAN DI-SCOPE KE MASING-MASING SECTION, bukan ke dokumen.
    const inAction = actionSection.find(
      `[data-date="${DAY_A}"][data-time-slot="${SHARED_SLOT}"]`,
    )
    const inFinding = findingSection.find(
      `[data-date="${DAY_A}"][data-time-slot="${SHARED_SLOT}"]`,
    )

    expect(inAction.exists()).toBe(true)
    expect(inFinding.exists()).toBe(true)
    // Dan ISINYA berbeda: dua pertanyaan yang berbeda, bukan satu catatan
    // yang digandakan.
    expect(inAction.get('.note-text').text()).toBe('Sudah ditangani')
    expect(inFinding.get('.note-text').text()).toBe('Kesadahan keluaran softener naik tajam')

    // TIDAK ADA PENANDAAN 'duplikat' MAUPUN PENGGABUNGAN — diasersi atas
    // TESTID dan atas KELAS, BUKAN atas prosa: layar ini WAJIB merender
    // kalimat yang memuat frasa 'bukan penghitungan ganda' dan 'tidak pernah
    // digabungkan', jadi pemindaian prosa memerah pada layar yang BENAR.
    for (const testid of ['pw-duplicate-note', 'pw-duplicate-badge', 'pw-merged-entries']) {
      expect(wrapper.findAll(`[data-testid="${testid}"]`), testid).toHaveLength(0)
    }
    expect(
      [inAction, inFinding]
        .flatMap((row) => (row.attributes('class') ?? '').split(/\s+/))
        .filter((name) => /duplicate|duplikat|merged|digabung/i.test(name)),
    ).toEqual([])
    // Dan kalimat yang menerangkannya memang ADA — ia bukan yang harus hilang.
    expect(text(wrapper, 'pw-findings-note')).toContain('bukan penghitungan ganda')

    // JUDUL KEDUANYA TERPISAH, masing-masing dengan jumlah slotnya sendiri.
    expect(actionSection.get('.section-title').text()).toBe('Rekap Catatan Tindakan (3 slot)')
    expect(findingSection.get('.section-title').text()).toBe('Rekap Temuan (2 slot)')
    // Jumlah pada kedua judul TIDAK BOLEH dijumlahkan, dan hal itu dinyatakan.
    expect(text(wrapper, 'pw-findings-note')).toContain('tidak boleh dijumlahkan')
  })

  // unit_test_case 38
  it('unit_test_case 38: kedua kolom kosong — kedua section MENYATAKAN tidak ada catatan, dan tetap ada', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({ text_entries: { action_taken_status: [], findings: [] } }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // Bukan section yang hilang.
    expect(exists(wrapper, 'pw-recap-action-taken')).toBe(true)
    expect(exists(wrapper, 'pw-recap-findings')).toBe(true)

    // Bukan daftar kosong tanpa penjelasan: daftar kosong tidak membedakan
    // 'tidak ada tindakan' dari 'tidak ada yang mencatatnya'.
    expect(text(wrapper, 'pw-recap-action-taken-empty')).toContain('bukan daftar kosong')
    expect(text(wrapper, 'pw-recap-findings-empty')).toContain('bukan daftar kosong')
    expect(wrapper.findAll('[data-testid^="pw-action-taken-row-"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-testid^="pw-finding-row-"]')).toHaveLength(0)
  })

  it('satu kolom kosong dan satu berisi: keterangan HANYA pada yang kosong', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        text_entries: { action_taken_status: actionTakenEntries(), findings: [] },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(wrapper.findAll('[data-testid^="pw-action-taken-row-"]')).toHaveLength(3)
    expect(exists(wrapper, 'pw-recap-action-taken-empty')).toBe(false)
    expect(exists(wrapper, 'pw-recap-findings-empty')).toBe(true)
    // Keterangan kosongnya menyatakan ia BUKAN digabung ke bagian sebelahnya.
    expect(text(wrapper, 'pw-recap-findings-empty')).toContain('bukan digabung')
  })

  // unit_test_case 39
  it('unit_test_case 39: teks 600 karakter hadir UTUH, tanpa kelas/gaya pemotong dan tanpa "baca selengkapnya"', async () => {
    const longText =
      'Ditemukan rembesan pada sambungan pipa keluaran softener yang membasahi lantai sekitar '
      + 'panel dosis kimia, sudah dibersihkan dan diberi penampung sementara, tetapi '
      + 'sambungannya perlu diganti pada kesempatan berhenti berikutnya karena rembesannya '
      + 'kembali muncul setelah beberapa jam beroperasi dan kesadahan keluaran ikut naik pada '
      + 'slot-slot sesudahnya, sehingga dosis alum sempat dinaikkan tanpa catatan tertulis pada '
      + 'dua slot berbeda; operator berikutnya menurunkannya kembali setelah membaca kolom '
      + 'temuan ini, dan itulah sebabnya kalimat sepanjang ini tidak boleh dipotong di tengah '
      + 'oleh layar yang sempit.'

    expect(longText.length).toBeGreaterThan(600)

    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        text_entries: {
          action_taken_status: [],
          findings: [{ date: DAY_A, time_slot: '09:00', text: longText }],
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    const noteText = wrapper.get('[data-testid="pw-finding-row-0"]').get('.note-text')

    // TEKS LENGKAPNYA HADIR di textContent — tidak dipotong, tidak diringkas.
    expect(noteText.element.textContent).toBe(longText)
    expect(noteText.text()).not.toContain('…')
    expect(noteText.text()).not.toContain('...')

    // Tidak ada kelas pemotong pada elemennya maupun pada induknya di dalam
    // barisnya. jsdom tidak menghitung tata letak, jadi yang dapat dibuktikan
    // DI SINI adalah ketiadaan kelas dan gaya inline pemotong; pengukuran
    // pada hasil render 390px ada di spec browser.
    const row = wrapper.get('[data-testid="pw-finding-row-0"]')
    const classNames = [row, ...row.findAll('[class]')]
      .flatMap((el) => (el.attributes('class') ?? '').split(/\s+/))
      .filter((name) => name !== '')

    for (const name of classNames) {
      expect(name).not.toMatch(/truncate|ellipsis|clamp|one-line|single-line|text-cut/)
    }

    expect(noteText.attributes('style') ?? '').not.toMatch(/line-clamp|text-overflow|ellipsis/)

    // Dan tidak ada kontrol 'baca selengkapnya' dalam bentuk apa pun.
    expect(
      wrapper.findAll('button').filter((btn) => /selengkapnya|lihat semua|expand/i.test(btn.text())),
    ).toHaveLength(0)

    // Definisi .note-text di blok <style> pun tidak memuat pemotong.
    const viewSource = readFileSync(
      resolve(process.cwd(), 'src/views/LaporanProcessWaterView.vue'),
      'utf-8',
    )
    const start = viewSource.indexOf('.note-text {')

    expect(start).toBeGreaterThan(-1)

    const rule = viewSource.slice(start, start + viewSource.slice(start).indexOf('}') + 1)

    expect(rule).not.toContain('line-clamp')
    expect(rule).not.toContain('text-overflow')
    expect(rule).not.toContain('nowrap')
  })
})

/* ================================================================== */
/* unit_test_case 40-43 + skenario 13 — REKAP PER INSPECTOR            */
/* ================================================================== */

describe('rekap per inspector memakai kode mentah', () => {
  /**
   * unit_test_case 40 + skenario 13 — BENTUK TECH SPEC (daftar).
   *
   * ASERSI MENYASAR ELEMEN SEL KODENYA, TIDAK MEMINDAI TEKS KONTAINER: kode
   * pendek seperti 'A1' atau '7' akan cocok di dalam angka lain pada halaman
   * yang sama — '7' ada di dalam '77,7%', '96', '7 hari', dan belasan tempat
   * lain — sehingga asersi substring tingkat kontainer hijau tanpa
   * membuktikan apa pun.
   */
  it('unit_test_case 40/skenario 13: kode mentah dibaca DARI SEL BARISNYA, bentuk DAFTAR', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        inspector_recap: [
          { inspector_id: 'A1', slots: 9, share: 0.375 },
          { inspector_id: 'INSP 07 PAGI', slots: 8, share: 0.333 },
          { inspector_id: null, slots: 7, share: 0.292 },
        ],
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // KESAMAAN PENUH pada SEL kodenya, bukan substring pada kontainernya.
    expect(text(wrapper, 'pw-inspector-code-0')).toBe('A1')
    expect(text(wrapper, 'pw-inspector-code-1')).toBe('INSP 07 PAGI')
    expect(text(wrapper, 'pw-inspector-code-2')).toBe('tidak tercatat')

    // Dan angkanya pun dibaca dari sel DI DALAM barisnya sendiri.
    expect(text(wrapper, 'pw-inspector-slots-0')).toBe('9')
    expect(text(wrapper, 'pw-inspector-slots-1')).toBe('8')
    expect(text(wrapper, 'pw-inspector-slots-2')).toBe('7')

    // Kode berSPASI ditampilkan UTUH, tidak dipotong dan tidak dirapikan.
    expect(rawText(wrapper, 'pw-inspector-code-1')).toBe('INSP 07 PAGI')

    // Urutannya URUTAN PAYLOAD. TIDAK diurutkan menurut jumlah slot: rekap
    // ini ada untuk menelusuri slot yang belum terisi, bukan untuk menilai
    // orang, jadi ia tidak boleh terbaca sebagai tabel peringkat.
    expect(
      inspectorRows(wrapper).map((row) =>
        row.get('[data-testid^="pw-inspector-code-"]').text(),
      ),
    ).toEqual(['A1', 'INSP 07 PAGI', 'tidak tercatat'])
    expect(text(wrapper, 'pw-inspector-purpose-note')).toContain('bukan untuk menilai orang')
  })

  /**
   * unit_test_case 42 + skenario 13 — BARIS null BERDIRI SENDIRI, dan
   * jumlah barisnya TEPAT sama dengan panjang payload: bentuk daftar sudah
   * memuat barisnya sendiri, jadi ia tidak bertambah satu.
   */
  it('unit_test_case 42: baris inspector_id null BERDIRI SENDIRI, jumlah baris TEPAT 3', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        inspector_recap: [
          { inspector_id: 'A1', slots: 9, share: 0.375 },
          { inspector_id: 'B2', slots: 8, share: 0.333 },
          { inspector_id: null, slots: 7, share: 0.292 },
        ],
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // Jumlah baris dihitung atas PREFIX TESTID BARIS — tfoot tidak ikut
    // tercocok, karena barisnya ber-testid 'pw-inspector-total'.
    expect(inspectorRows(wrapper)).toHaveLength(3)
    expect(exists(wrapper, 'pw-inspector-total')).toBe(true)

    // Slot baris null TIDAK dilipat ke baris berkode mana pun: 9 dan 8 tetap
    // 9 dan 8, bukan 16 dan 8 ataupun 9 dan 15.
    expect(text(wrapper, 'pw-inspector-slots-0')).toBe('9')
    expect(text(wrapper, 'pw-inspector-slots-1')).toBe('8')
    expect(text(wrapper, 'pw-inspector-slots-2')).toBe('7')
    expect(text(wrapper, 'pw-inspector-code-2')).toBe('tidak tercatat')
    // Dan keterangannya dinyatakan.
    expect(text(wrapper, 'pw-inspector-raw-code-note')).toContain('berdiri sendiri')
  })

  it('BENTUK SERVER (objek): entries berkode + SATU baris dari kunci not_recorded yang terpisah', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // 2 entri + 1 baris 'tidak tercatat' = 3 baris, dan judulnya menyebut 3.
    expect(inspectorRows(wrapper)).toHaveLength(3)
    expect(text(wrapper, 'pw-inspector-code-0')).toBe('A1')
    expect(text(wrapper, 'pw-inspector-code-1')).toBe('INSP 07 PAGI')
    expect(text(wrapper, 'pw-inspector-code-2')).toBe('tidak tercatat')
    expect(text(wrapper, 'pw-inspector-slots-2')).toBe('7')
    expect(block(wrapper, 'pw-inspector-recap').text()).toContain('Rekap per Inspector (3 baris)')
  })

  /**
   * DIVERGENSI 4 — PORSI DIHITUNG DI KLIEN, dan PENYEBUTNYA `recorded_slots`
   * DARI RESPONS. Diasersi digit demi digit: 9/24 = 37,5%, 8/24 = 33,3%,
   * 7/24 = 29,2%. Penyebut expected_slots (480) akan memberi 1,9% / 1,7% /
   * 1,5% — angka yang tidak muncul di mana pun pada fixture ini.
   */
  it('porsi memakai recorded_slots DARI RESPONS, tidak pernah expected_slots', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(text(wrapper, 'pw-inspector-share-0')).toBe('37,5%')
    expect(text(wrapper, 'pw-inspector-share-1')).toBe('33,3%')
    expect(text(wrapper, 'pw-inspector-share-2')).toBe('29,2%')

    // Penyebutnya 24 (slot TERCATAT), bukan 480 (slot DIHARAPKAN).
    expect(block(wrapper, 'pw-inspector-recap').text()).toContain('Dasar 24 slot terisi')
    expect(text(wrapper, 'pw-inspector-share-0')).not.toBe('1,9%')
    // Dan barisnya menjumlah 100,0% pada footer.
    expect(text(wrapper, 'pw-inspector-total')).toContain('100,0%')
  })

  // unit_test_case 41
  it('unit_test_case 41: NOL pemetaan nama — tidak ada satu pun pencarian master untuk inspector_id', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // Satu-satunya jalan keluar layar ini adalah repo laporan. Setiap
    // pemanggilan jaringan tambahan — termasuk pencarian nama — akan
    // terhitung pada spy apiClient dan spy repo line.
    expect(apiClientGetMock).not.toHaveBeenCalled()
    expect(productionLineMocks.fetchProductionLinesForReport).not.toHaveBeenCalled()
    expect(repoMocks.fetchLines).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchPeriods).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)

    // Teks yang dirender SAMA PERSIS dengan nilai inspector_id respons —
    // bukan nama pengguna uji yang ada di auth store.
    expect(text(wrapper, 'pw-inspector-code-0')).toBe('A1')
    expect(block(wrapper, 'pw-inspector-table').text()).not.toContain('Pengguna Uji')
    expect(block(wrapper, 'pw-inspector-table').text()).not.toContain('operator01')

    // AUDIT STATIS: tidak ada satu pun jalur pemetaan nama di berkas view.
    const code = stripComments(
      readFileSync(resolve(process.cwd(), 'src/views/LaporanProcessWaterView.vue'), 'utf-8'),
    )

    for (const forbidden of ['userRepo', 'resolveUserName', 'inspectorName', '/users', 'USER_NAMES']) {
      expect(code, forbidden).not.toContain(forbidden)
    }
    // Judul kolomnya pun TETAP nama kolomnya, bukan 'Nama Inspector'.
    expect(columnHeaderTexts(wrapper)).toContain('inspector_id')
    expect(columnHeaderTexts(wrapper)).not.toContain('Nama Inspector')
  })

  // unit_test_case 43
  it('unit_test_case 43: inspector_recap SELURUHNYA null → SATU baris "tidak tercatat" berisi seluruh slot', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({ inspector_recap: [{ inspector_id: null, slots: 24, share: 1 }] }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // TEPAT SATU baris — bukan rekap kosong, dan bukan section yang
    // disembunyikan.
    expect(inspectorRows(wrapper)).toHaveLength(1)
    expect(text(wrapper, 'pw-inspector-code-0')).toBe('tidak tercatat')
    expect(text(wrapper, 'pw-inspector-slots-0')).toBe('24')
    expect(exists(wrapper, 'pw-inspector-recap')).toBe(true)
    expect(exists(wrapper, 'pw-inspector-recap-empty')).toBe(false)
    expect(exists(wrapper, 'pw-inspector-table')).toBe(true)
  })

  it('kode inspector yang PANJANG ditampilkan utuh, tanpa dipotong dan tanpa dipetakan', async () => {
    const longCode = 'INSP-2026-10-SHIFT-PAGI-UNIT-PROCESS-WATER-LINE-SATU-OPERATOR-CADANGAN'

    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({ inspector_recap: [{ inspector_id: longCode, slots: 24, share: 1 }] }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(rawText(wrapper, 'pw-inspector-code-0')).toBe(longCode)
    expect(text(wrapper, 'pw-inspector-code-0')).not.toContain('…')
  })
})

/* ================================================================== */
/* unit_test_case 44 + 45 + 46 + skenario 14 + 15 — KETIADAAN           */
/* ================================================================== */

describe('ketiadaan yang disengaja, dinyatakan', () => {
  /**
   * unit_test_case 44 + skenario 14 — KOLOM shift TIDAK MUNCUL DI MANA PUN.
   *
   * Diasersi atas NAMA KUNCI payload (walker rekursif), atas TESTID, dan atas
   * JUDUL KOLOM. Tidak atas `wrapper.html()`: testid `pw-no-shift-note`
   * sendiri memuat rangkaian huruf 'shift', jadi pemindaian HTML memerah pada
   * layar yang BENAR.
   */
  it('unit_test_case 44/skenario 14: nol kunci shift di payload, nol kolom shift di layar', async () => {
    const payload = summaryFixture()

    repoMocks.fetchSummary.mockResolvedValue(payload)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // (a) WALKER REKURSIF atas KUNCI payload yang dipasok ke view.
    const keys = keyNamesOf(payload)

    expect(keys).not.toContain('shift')
    expect(keys).not.toContain('shift_id')
    expect(keys).not.toContain('shift_name')

    // (b) TESTID.
    for (const testid of ['pw-shift-column', 'pw-shift', 'pw-shift-recap', 'shift-column']) {
      expect(wrapper.findAll(`[data-testid="${testid}"]`), testid).toHaveLength(0)
    }

    // (c) JUDUL KOLOM — ketiga tabel rekap ada di DOM (CollapsibleSection
    // memakai v-show), jadi judul kolomnya benar-benar terperiksa di sini.
    const headers = columnHeaderTexts(wrapper)

    expect(headers.length).toBeGreaterThan(10)
    expect(headers.filter((header) => /shift/i.test(header))).toEqual([])
    expect(headers.filter((header) => /giliran/i.test(header))).toEqual([])

    // (d) Dan ALASANNYA dirender dari prosa server plus keterangan layar.
    expect(exists(wrapper, 'pw-note-absences')).toBe(true)
    expect(rawText(wrapper, 'pw-note-absences')).toContain('giliran kerja')
    expect(text(wrapper, 'pw-no-shift-note')).toContain('konsekuensi, bukan penyaringan di klien')
  })

  it('frasa Indonesia "giliran kerja" DIHARAPKAN ada di prosa — ia bukan kolom yang dirender', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // Inilah sebabnya asersi di atas menyasar JUDUL KOLOM, bukan teks
    // halaman: frasa ini wajib terbaca pembaca, dan ia memang ada.
    expect(wrapper.text()).toContain('giliran kerja')
    // Sementara kata Inggrisnya tidak pernah dirender sebagai teks.
    expect(wrapper.text()).not.toMatch(/\bshift\b/i)
  })

  // unit_test_case 45 + skenario 15
  it('unit_test_case 45: nol blok downtime — diasersi atas TESTID dan JUDUL, bukan atas prosa', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    for (const testid of ['pw-downtime', 'pw-downtime-summary', 'pw-downtime-total', 'downtime']) {
      expect(wrapper.findAll(`[data-testid="${testid}"]`), testid).toHaveLength(0)
    }

    expect(headingTexts(wrapper).filter((heading) => /downtime|lama berhenti/i.test(heading))).toEqual([])

    // Dan keterangannya DINYATAKAN — kata 'downtime' WAJIB muncul di sana,
    // yang justru membuat pemindaian prosa tidak dapat dipakai.
    expect(text(wrapper, 'pw-no-downtime-note')).toContain(
      'tidak mencatat lama berhenti sama sekali',
    )
  })

  it('nol kolom target dan nol acuan batas di seluruh layar', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    for (const testid of ['pw-metric-target', 'pw-target', 'pw-threshold', 'metric-target']) {
      expect(wrapper.findAll(`[data-testid="${testid}"]`), testid).toHaveLength(0)
    }
    expect(columnHeaderTexts(wrapper).filter((header) => /target|ambang|batas/i.test(header))).toEqual([])
    expect(text(wrapper, 'pw-no-threshold-note')).toContain('tidak punya tabel master target')
  })

  /**
   * unit_test_case 46 + skenario 15 — VERBATIM, KESAMAAN PENUH.
   *
   * Elemen ini pun SATU mustache tanpa satu kata tambahan di dalamnya;
   * keterangan layar ada DI SEBELAHNYA (pw-no-downtime-note dan
   * seterusnya), bukan di dalamnya.
   */
  it('unit_test_case 46/skenario 15: pw-note-absences dirender UTUH — kesamaan string penuh', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(rawText(wrapper, 'pw-note-absences')).toBe(NOTE_ABSENCES)
    expect(rawText(wrapper, 'pw-note-absences')).not.toContain('…')
    expect(rawText(wrapper, 'pw-note-absences')).not.toContain('...')

    // Tanpa kelas pemotong, tanpa gaya pemotong, dan tanpa kontrol
    // 'baca selengkapnya'.
    const el = wrapper.get('[data-testid="pw-note-absences"]')

    expect((el.attributes('class') ?? '').split(/\s+/)).toEqual(['section-note'])
    expect(el.attributes('style') ?? '').not.toMatch(/line-clamp|text-overflow|ellipsis/)
    expect(
      wrapper.findAll('button').filter((btn) => /selengkapnya|lihat semua/i.test(btn.text())),
    ).toHaveLength(0)
  })

  it('pernyataan ketiadaan penandaan di luar batas dirender UTUH dan MENEMPEL pada blok Air Umpan Boiler', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // DI DALAM bloknya, bukan sebagai catatan kaki halaman: justru di blok
    // inilah godaan menandai paling besar.
    expect(
      block(wrapper, 'pw-metric-block-air-umpan-boiler')
        .find('[data-testid="pw-no-threshold-note"]')
        .exists(),
    ).toBe(true)
    // Dan ia dirender SEKALI, bukan di setiap blok.
    expect(wrapper.findAll('[data-testid="pw-no-threshold-note"]')).toHaveLength(1)

    const note = wrapper.get('[data-testid="pw-no-threshold-note"]')

    expect((note.element.textContent ?? '').length).toBeGreaterThan(600)
    expect((note.attributes('class') ?? '').split(/\s+/)).toEqual(['section-note'])
    expect(note.attributes('style') ?? '').not.toMatch(/line-clamp|text-overflow|ellipsis/)
    expect(note.text()).not.toContain('…')
  })

  it('kalimat server yang BERBEDA pada _note_absences ikut berubah di layar', async () => {
    const lain = 'Kalimat ketiadaan yang berbeda, dirender utuh dari server.'

    repoMocks.fetchSummary.mockResolvedValue(summaryFixture({ _note_absences: lain }))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(rawText(wrapper, 'pw-note-absences')).toBe(lain)
  })
})

/* ================================================================== */
/* unit_test_case 47-49 + skenario 20 + 21 + 25 — EKSPOR CSV SAJA      */
/* ================================================================== */

describe('ekspor CSV saja', () => {
  // unit_test_case 47 + skenario 20
  it('unit_test_case 47/skenario 20: pw-export-csv ADA; tombol Excel diasersi ABSEN atas TESTID', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(exists(wrapper, 'pw-export-csv')).toBe(true)
    expect(text(wrapper, 'pw-export-csv')).toContain('Ekspor CSV')

    // KETIADAAN DIASERSI ATAS TESTID, BUKAN ATAS TEKS HALAMAN: layar ini
    // WAJIB merender kalimat 'tidak ada tombol Ekspor Excel' dan menyebut
    // '.xlsx' (pw-no-mill-picker-note), jadi pemindaian teks halaman memerah
    // pada layar yang BENAR.
    expect(wrapper.find('[data-testid="pw-export-xlsx"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="pw-export-excel"]').exists()).toBe(false)

    // Dan TEPAT SATU tombol ekspor di seluruh layar.
    expect(wrapper.findAll('[data-testid^="pw-export-"]')).toHaveLength(1)

    // Kalimat yang menerangkan ketiadaannya memang ADA — ia bukan yang harus
    // dihapus.
    expect(text(wrapper, 'pw-no-mill-picker-note')).toContain('tidak ada tombol Ekspor Excel')
  })

  // unit_test_case 48
  it('unit_test_case 48: tombol ekspor TIDAK dirender selama line belum terpilih', async () => {
    window.localStorage.clear()
    repoMocks.fetchLines.mockResolvedValue([LINE_1, LINE_2])

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // Berkas yang mencampur line adalah bentuk kesalahan yang paling sulit
    // dibantah setelah terkirim.
    expect(wrapper.find('[data-testid="pw-export-csv"]').exists()).toBe(false)
  })

  it('tombol ekspor TIDAK dirender selama periode belum terpilih', async () => {
    const wrapper = await mountView()

    expect(selectedOptionText(wrapper, 'period-select')).toBe(NO_PERIOD_SELECTED)
    expect(wrapper.find('[data-testid="pw-export-csv"]').exists()).toBe(false)
  })

  // skenario 21
  it('skenario 21: klik Ekspor memanggil exportCsv dengan cakupan yang SAMA dengan angka di layar', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    await wrapper.get('[data-testid="pw-export-csv"]').trigger('click')
    await flushPromises()

    expect(repoMocks.exportCsv).toHaveBeenCalledTimes(1)
    expect(repoMocks.exportCsv).toHaveBeenCalledWith('p-1', { productionLineId: 'l-1' })

    // `format=csv` DIKIRIM EKSPLISIT — dibuktikan lewat exportParams ASLI
    // (importActual), bukan lewat mock yang bisa saja berbeda.
    const actual = await import('@/services/processWaterReportRepo')
    const params = actual.exportParams('p-1', { productionLineId: 'l-1' })

    expect(params).toEqual({ period_id: 'p-1', production_line_id: 'l-1', format: 'csv' })
    expect(actual.buildExportUrl('p-1', { productionLineId: 'l-1' })).toContain('format=csv')

    // Berkasnya disimpan lewat MEKANISME YANG SAMA dengan kembaran mobile
    // sebelumnya — tidak ada mekanisme unduhan baru yang dikarang di view.
    expect(repoMocks.saveCsvFile).toHaveBeenCalledTimes(1)

    const [, filename] = repoMocks.saveCsvFile.mock.calls[0]

    expect(String(filename)).toMatch(/^laporan-process-water_.*\.csv$/)
    expect(String(filename)).toContain('line-satu')
    expect(String(filename)).not.toMatch(/xlsx/i)

    // Dan NOL tulisan ke localDb sepanjang alur ekspor.
    for (const [name, spy] of Object.entries(localDbMocks)) {
      expect(spy, `localDb.${name}`).not.toHaveBeenCalled()
    }
  })

  it('klik ganda cepat mengirim SATU permintaan ekspor — tombolnya terkunci sesaat', async () => {
    let release!: (value: Blob) => void

    repoMocks.exportCsv.mockReturnValue(
      new Promise<Blob>((resolveBlob) => {
        release = resolveBlob
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    const button = wrapper.get('[data-testid="pw-export-csv"]')

    await button.trigger('click')
    await wrapper.vm.$nextTick()

    expect(button.attributes('disabled')).toBeDefined()
    expect(button.attributes('aria-busy')).toBe('true')

    await button.trigger('click')
    await wrapper.vm.$nextTick()

    expect(repoMocks.exportCsv).toHaveBeenCalledTimes(1)

    release(new Blob(['a,b']))
    await flushPromises()

    expect(button.attributes('disabled')).toBeUndefined()
  })

  it('422 batas baris masuk cabang PESAN GALAT, bukan arahan hubungi Admin, dan nol berkas tersimpan', async () => {
    repoMocks.exportCsv.mockRejectedValueOnce(EXPORT_FAILED_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    await wrapper.get('[data-testid="pw-export-csv"]').trigger('click')
    await flushPromises()

    // Satu kode HTTP yang sama membawa DUA arti di layar ini, dan pemanggil
    // yang memutuskan — pemetaan itu benar dan terbukti di sini.
    expect(text(wrapper, 'error-message')).toBe(EXPORT_FAILED_ERROR.message)
    expect(exists(wrapper, 'mill-not-linked')).toBe(false)
    expect(exists(wrapper, 'network-error')).toBe(false)
    // Kegagalan ekspor tidak dapat "diulang" secara bermakna oleh tombol yang
    // sama, jadi retry-nya null.
    expect(exists(wrapper, 'retry-button')).toBe(false)
    expect(repoMocks.saveCsvFile).not.toHaveBeenCalled()
    expect(pushMock).not.toHaveBeenCalled()
  })

  // unit_test_case 49 + skenario 25
  it('unit_test_case 49/skenario 25: periode TERTUTUP tetap terbaca penuh dan tetap dapat diekspor', async () => {
    repoMocks.fetchSummary.mockImplementation(
      (_periodId: string, scope?: { productionLineId?: string | null }) =>
        Promise.resolve(
          summaryForScope(scope, {
            period: {
              id: 'p-2',
              name: 'Periode Sebelumnya',
              start_date: PREV_START,
              end_date: PREV_END,
              status: 'closed',
            },
          }),
        ),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-2')

    // Status periode mengatur PENULISAN data, bukan PEMBACAAN laporan.
    expect(cards(wrapper)).toHaveLength(9)
    expect(text(wrapper, 'period-status-badge')).toBe('Tertutup')
    expect(exists(wrapper, 'pw-inspector-table')).toBe(true)
    expect(exists(wrapper, 'pw-unit-table')).toBe(true)
    expect(exists(wrapper, 'pw-daily-table')).toBe(true)

    const button = wrapper.get('[data-testid="pw-export-csv"]')

    expect(button.attributes('disabled')).toBeUndefined()

    await button.trigger('click')
    await flushPromises()

    expect(repoMocks.exportCsv).toHaveBeenCalledWith('p-2', { productionLineId: 'l-1' })
    expect(text(wrapper, 'period-meta')).toContain('tetap dapat dilihat dan diekspor')
  })
})

/* ================================================================== */
/* unit_test_case 50 + skenario 22 — KETIGA REKAP, TOGGLE TANPA REQUEST */
/* ================================================================== */

describe('ketiga rekap', () => {
  // unit_test_case 50 + skenario 22
  it('unit_test_case 50/skenario 22: membuka/menutup KETIGA rekap TIDAK memicu satu permintaan pun', async () => {
    const dailyRows = Array.from({ length: 45 }, (_unused, index) => ({
      date: isoDaysFromNow(-50 + index),
      reading_rows: index + 1,
      metrics_avg: { raw_water_flow_m3h: { avg: 100 + index, reading_count: index + 1 } },
    }))

    repoMocks.fetchSummary.mockResolvedValue(summaryFixture({ daily: dailyRows }))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)
    // CollapsibleSection memakai v-show, bukan v-if: barisnya SUDAH ADA di
    // DOM saat tertutup, dan itulah yang membuat asersi isi di berkas ini
    // tidak perlu menoggle apa pun.
    expect(block(wrapper, 'pw-daily-table').findAll('tbody tr')).toHaveLength(45)

    const toggles = collapsibleToggles(wrapper)

    // TEPAT TIGA: rekap inspector, rekap unit, rekap harian. Keempat blok
    // kartu TIDAK punya kontrol lipat.
    expect(toggles).toHaveLength(3)

    for (const toggle of toggles) {
      await toggle.trigger('click')
      await flushPromises()
      await toggle.trigger('click')
      await flushPromises()
    }

    // PENYINGKAPAN MURNI: datanya sudah ada di memori.
    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchPeriods).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchLines).toHaveBeenCalledTimes(1)
    expect(apiClientGetMock).not.toHaveBeenCalled()

    // Dan tidak satu angka pun berubah, termasuk baris total periode.
    expect(block(wrapper, 'pw-daily-table').findAll('tbody tr')).toHaveLength(45)
    expect(text(wrapper, 'pw-daily-total')).toContain('777')
  })

  it('ketiga tabel rekap memakai v-stacked-table, dan tiap tabel berada di pembungkusnya sendiri', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    const viewSource = readFileSync(
      resolve(process.cwd(), 'src/views/LaporanProcessWaterView.vue'),
      'utf-8',
    )

    // jsdom tidak menghitung tata letak, jadi "menumpuk menjadi kartu" hanya
    // dapat DIUKUR di spec browser. Yang dapat dibuktikan DI SINI adalah
    // bahwa ketiga tabel benar-benar memakai direktifnya dan berada di dalam
    // .detail-table-wrap — dua prasyarat yang, bila hilang, membuat
    // pengukuran browser gagal tanpa petunjuk sebabnya.
    // Dihitung atas MARKUP-nya, bukan atas sumber mentah: docblock berkas
    // itu menyebut nama direktifnya beberapa kali justru untuk menerangkan
    // mengapa ketiga tabel memakainya.
    expect(stripComments(viewSource).match(/v-stacked-table/g)).toHaveLength(3)

    for (const testid of ['pw-inspector-table', 'pw-unit-table', 'pw-daily-table']) {
      const table = block(wrapper, testid)

      expect(table.element.tagName, testid).toBe('TABLE')
      expect(table.classes(), testid).toContain('detail-table')
      expect(
        (table.element.parentElement as HTMLElement | null)?.className,
        testid,
      ).toContain('detail-table-wrap')
    }
  })

  it('rekap per unit menampilkan unit TANPA pembacaan dengan penyebut nol, tidak dibuang', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    const rows = wrapper.findAll('[data-testid^="pw-unit-row-"]')

    expect(rows).toHaveLength(2)
    expect(rows[0].text()).toContain('PW-1')
    // Unit yang punya record tetapi tidak satu pun pembacaan TETAP muncul.
    expect(rows[1].text()).toContain('PW-2')
    expect(text(wrapper, 'pw-unit-filled-slots-0')).toBe('72')
    expect(text(wrapper, 'pw-unit-filled-slots-1')).toBe('0')
  })

  it('rekap harian memberi SATU baris per tanggal berdata, dan sel kosong "—" bukan 0', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(wrapper.findAll('[data-testid^="pw-daily-row-"]')).toHaveLength(2)
    expect(exists(wrapper, `pw-daily-row-${DAY_A}`)).toBe(true)
    expect(exists(wrapper, `pw-daily-row-${DAY_B}`)).toBe(true)
    // DAY_C tidak punya baris harian (hanya titik tren) — baris nol akan
    // terbaca sebagai 'terukur nol', padahal tidak ada yang diukur.
    expect(exists(wrapper, `pw-daily-row-${DAY_C}`)).toBe(false)

    // Sel kosong '—', BUKAN 0: nol adalah pembacaan, kosong bukan.
    expect(block(wrapper, `pw-daily-row-${DAY_B}`).text()).toContain('—')

    // TOTAL PERIODE memakai angka server (777), bukan 12 + 9 = 21.
    //
    // DIBACA DARI SELNYA SENDIRI, bukan dari teks barisnya: baris itu memuat
    // penyebut tiap kolom dalam bentuk '(n=21)', jadi asersi
    // `not.toContain('21')` tingkat baris memerah pada layar yang BENAR —
    // angka 21 memang ada di sana, sebagai penyebut kartu polimer.
    const totalCells = block(wrapper, 'pw-daily-total').findAll('td')

    expect(totalCells[0].text()).toBe('Total periode')
    expect(totalCells[1].text()).toBe('777')
    expect(totalCells[1].text()).not.toBe('21')
  })

  it('tren harian memakai seri DARI RESPONS, dan titik tanpa pembacaan tidak digambar sebagai nol', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(wrapper.findAll('[data-testid^="pw-trend-col-"]')).toHaveLength(3)
    expect(block(wrapper, `pw-trend-col-${DAY_B}`).text()).toContain('tidak tersedia')
    expect(block(wrapper, `pw-trend-col-${DAY_B}`).text()).not.toContain('0,0')
    expect(block(wrapper, `pw-trend-col-${DAY_A}`).text()).toContain('118,4')
  })
})

/* ================================================================== */
/* unit_test_case 11-18 + skenario 2 + 3 + 4 — PRODUCTION LINE         */
/* ================================================================== */

/**
 * PENJAGAAN ADA DI loadSummary(), BUKAN DI TEMPLATE, dan ketiga jalurnya
 * memanggil fungsi yang sama — jadi satu penjagaan melindungi ketiganya.
 * Penjaga yang hanya ada di template dilewati ketiganya, dan melewatinya di
 * layar ini berarti 422 dari server. Tiga test terpisah, satu per jalur,
 * masing-masing mengasersi NOL pemanggilan fetchSummary.
 */
describe('penjagaan Production Line di fungsi pemuatan', () => {
  // unit_test_case 11 + skenario 3 (jalur 1/3)
  it('unit_test_case 11 — MUAT AWAL: nol permintaan /summary selama productionLineId kosong', async () => {
    window.localStorage.clear()
    repoMocks.fetchLines.mockResolvedValue([LINE_1, LINE_2])

    const wrapper = await mountView()

    // Daftar line dan daftar periode TETAP diminta — keduanya tidak
    // bergantung pada line. Yang DITAHAN adalah /summary.
    expect(repoMocks.fetchLines).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchPeriods).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(text(wrapper, 'production-line-required-hint')).toContain(
      'tidak satu permintaan pun dikirim ke server',
    )
    expect(cards(wrapper)).toHaveLength(0)
    expect(wrapper.find('[data-testid="pw-export-csv"]').exists()).toBe(false)
  })

  // unit_test_case 12 + skenario 3 (jalur 2/3)
  it('unit_test_case 12 — GANTI PERIODE: mengganti periode tanpa line terpilih mengirim nol permintaan', async () => {
    window.localStorage.clear()
    repoMocks.fetchLines.mockResolvedValue([LINE_1, LINE_2])

    const wrapper = await mountView()

    await selectPeriod(wrapper, 'p-1')
    await selectPeriod(wrapper, 'p-2')

    // Mengganti periode TIDAK melewati penjagaan: jalur ini memanggil fungsi
    // pemuatan yang sama.
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
    expect(cards(wrapper)).toHaveLength(0)
    expect(wrapper.find('[data-testid="pw-export-csv"]').exists()).toBe(false)
  })

  // unit_test_case 13 + skenario 3 (jalur 3/3) + skenario 23 (separuh)
  it('unit_test_case 13 — COBA LAGI: tetap nol permintaan /summary selama line kosong', async () => {
    // Keadaan yang DAPAT DICAPAI dengan line kosong DAN tombol Coba Lagi
    // terender: /periods gagal karena jaringan (retryAction = loadPeriods).
    // Inilah jalur ketiga yang akan lolos bila penjagaannya ditaruh di
    // template — sesudah coba lagi berhasil, pengguna memilih periode, dan
    // tanpa penjaga di fungsi pemuatan permintaan ringkasan terbentuk tanpa
    // line.
    window.localStorage.clear()
    repoMocks.fetchLines.mockResolvedValue([LINE_1, LINE_2])
    repoMocks.fetchPeriods.mockRejectedValueOnce(NETWORK_ERROR)

    const wrapper = await mountView()

    expect(exists(wrapper, 'network-error')).toBe(true)
    expect(exists(wrapper, 'retry-button')).toBe(true)

    repoMocks.fetchPeriods.mockResolvedValue([PERIOD, PERIOD_CLOSED])
    await wrapper.get('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

    await selectPeriod(wrapper, 'p-1')

    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
    expect(cards(wrapper)).toHaveLength(0)
  })

  // skenario 3 — KETIGA JALUR dalam satu skenario, dihitung pada spy
  it('skenario 3: ketiga jalur berturut-turut — spy repo menerima NOL pemanggilan /summary', async () => {
    window.localStorage.clear()
    repoMocks.fetchLines.mockResolvedValue([LINE_1, LINE_2])

    const wrapper = await mountView()

    // Jalur 1: muat awal.
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

    // Jalur 2: ganti periode.
    await selectPeriod(wrapper, 'p-1')
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

    // Jalur 3: coba lagi — dipicu dari galat /periods, satu-satunya keadaan
    // yang menampilkan tombolnya tanpa line terpilih.
    repoMocks.fetchPeriods.mockRejectedValueOnce(NETWORK_ERROR)
    await selectLine(wrapper, 'l-1')
    await selectLine(wrapper, '')

    // DIHITUNG PADA SPY, bukan disimpulkan dari angka yang tidak tampil.
    const summaryCalls = repoMocks.fetchSummary.mock.calls.filter(
      ([, scope]) => !(scope as { productionLineId?: string | null })?.productionLineId,
    )

    expect(summaryCalls).toHaveLength(0)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(wrapper.find('[data-testid="pw-export-csv"]').exists()).toBe(false)
  })

  // unit_test_case 14
  it('unit_test_case 14: dengan line terpilih, TEPAT satu permintaan /summary — bukan nol dan bukan dua', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('p-1', { productionLineId: 'l-1' })
    expect(cards(wrapper)).toHaveLength(9)
  })

  it('tombol Coba Lagi ringkasan MEMANGGIL ULANG loadSummary — jalur sama, penjaga sama', async () => {
    repoMocks.fetchSummary.mockRejectedValueOnce(NETWORK_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(exists(wrapper, 'network-error')).toBe(true)
    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)

    await wrapper.get('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    // Permintaan KEDUA terbentuk karena line-nya BERLAKU — itulah yang
    // membuktikan tombol Coba Lagi memang melewati loadSummary(), dan karena
    // itu melewati penjaganya pula.
    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(2)
    expect(cards(wrapper)).toHaveLength(9)
  })
})

describe('resolusi Production Line', () => {
  // unit_test_case 15
  it('unit_test_case 15: localStorage dibaca dengan kunci TEPAT "msl_production_line_{userId}"', async () => {
    /**
     * DI-SPY PADA Storage.prototype, BUKAN PADA window.localStorage.
     *
     * jsdom membungkus objek Storage dengan Proxy, sehingga
     * `vi.spyOn(window.localStorage, 'getItem')` memasang properti own yang
     * TIDAK pernah terbaca oleh `window.localStorage.getItem(...)` di dalam
     * komponen: spy-nya menerima nol pemanggilan dan asersi "kunci yang
     * dibaca" lolos tanpa membaca apa pun. Diperiksa langsung sebelum
     * ditulis.
     */
    const getItem = vi.spyOn(Storage.prototype, 'getItem')

    await mountView()

    const keys = getItem.mock.calls.map(([key]) => String(key))

    // KESAMAAN STRING PENUH, bukan substring: kunci yang berbeda akan
    // terkompilasi, terlihat benar, dan tidak pernah cocok.
    expect(keys).toContain('msl_production_line_u-1')
    expect(keys).toContain(REMEMBERED_LINE_KEY)

    // Dan tidak satu pun kunci ingatan line LAIN dibaca.
    const lineKeys = [...new Set(keys.filter((key) => key.includes('production_line')))]

    expect(lineKeys).toEqual(['msl_production_line_u-1'])
  })

  it('kunci ingatan dimuati id PENGGUNA — pilihan pengguna lain tidak terbawa', async () => {
    asRole('supervisor', 'bu-1', 'u-2')
    window.localStorage.setItem('msl_production_line_u-2', 'l-2')
    repoMocks.fetchLines.mockResolvedValue([LINE_1, LINE_2])

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // 'l-1' masih tersimpan pada kunci u-1 di beforeEach, dan ia TIDAK
    // dipakai: sebuah Production Line milik satu mill, jadi pilihan pengguna
    // lain tidak boleh terbawa setelah ganti akun di perangkat yang sama.
    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('p-1', { productionLineId: 'l-2' })
    expect(text(wrapper, 'production-line-current')).toContain('Line Dua')
  })

  // unit_test_case 16 + skenario 4
  it('unit_test_case 16/skenario 4: localStorage yang MELEMPAR tidak mematahkan layar', async () => {
    // Storage.prototype, bukan instance — lihat catatan pada unit_test_case 15.
    const getItem = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new DOMException('akses penyimpanan ditolak', 'SecurityError')
    })

    repoMocks.fetchLines.mockResolvedValue([LINE_1, LINE_2])

    // MOUNT TIDAK MELEMPAR.
    const wrapper = await mountView()

    expect(exists(wrapper, 'laporan-process-water-mobile')).toBe(true)
    // Jatuh ke keadaan 'belum ada line terpilih'.
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    // NOL permintaan /summary.
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

    // DAN TANPA SATU PESAN GALAT PUN: kegagalannya diperlakukan sebagai
    // "tidak ada ingatan", bukan sebagai galat.
    for (const testid of ['network-error', 'error-message', 'mill-not-linked', 'production-line-rejected']) {
      expect(exists(wrapper, testid), testid).toBe(false)
    }

    // Pemilih line TETAP DAPAT DIOPERASIKAN.
    expect(getItem).toHaveBeenCalled()
    await selectLine(wrapper, 'l-2')
    await selectPeriod(wrapper, 'p-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('p-1', { productionLineId: 'l-2' })
    expect(cards(wrapper)).toHaveLength(9)
  })

  // unit_test_case 17
  it('unit_test_case 17: query rute MENGALAHKAN ingatan perangkat (urutan a sebelum b)', async () => {
    routeQuery.production_line_id = 'l-query'
    window.localStorage.setItem(REMEMBERED_LINE_KEY, 'l-1')
    repoMocks.fetchLines.mockResolvedValue([LINE_1, LINE_QUERY])

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('p-1', { productionLineId: 'l-query' })
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe('Line Dari Rute')
    // Dan pilihan itu DITULIS KEMBALI sebagai ingatan — layar Daftar Stasiun
    // dan layar laporan berbagi satu konteks.
    expect(window.localStorage.getItem(REMEMBERED_LINE_KEY)).toBe('l-query')
  })

  it('nilai dari rute maupun ingatan TIDAK dipercaya bila tidak ada di daftar line server', async () => {
    routeQuery.production_line_id = 'l-tidak-ada'
    window.localStorage.setItem(REMEMBERED_LINE_KEY, 'l-juga-tidak-ada')
    repoMocks.fetchLines.mockResolvedValue([LINE_1, LINE_2])

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
  })

  // unit_test_case 18
  it('unit_test_case 18: tanpa rute dan tanpa ingatan, layar MEMINTA memilih — tidak memilih line pertama', async () => {
    window.localStorage.clear()
    repoMocks.fetchLines.mockResolvedValue([LINE_1, LINE_2])

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // TIDAK ADA langkah (d) 'pilih line pertama': memilih diam-diam
    // menghasilkan angka line yang tidak pernah diminta siapa pun.
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    // Daftarnya memang berisi DUA entri, dan tidak satu pun terpilih.
    expect(wrapper.findAll('[data-testid="production-line-select"] option')).toHaveLength(3)
  })

  it('pemilih line DIRENDER walau daftarnya hanya berisi SATU entri, dan tetap tidak dipilihkan', async () => {
    window.localStorage.clear()
    repoMocks.fetchLines.mockResolvedValue([LINE_1])

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // Berbeda dari LaporanEffluentPlantView: TIDAK ADA "pilih satu-satunya
    // line". Pilihan itu tidak pernah dibuat untuk pengguna, bahkan ketika
    // mill ini hanya punya satu line.
    expect(exists(wrapper, 'production-line-select')).toBe(true)
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
    expect(text(wrapper, 'production-line-required-hint')).toContain('hanya punya satu line')
  })

  // skenario 2
  it('skenario 2: line terpilih OTOMATIS dari ingatan perangkat, tanpa diminta memilih lagi', async () => {
    window.localStorage.setItem(REMEMBERED_LINE_KEY, 'l-1')

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(selectedOptionText(wrapper, 'production-line-select')).toBe('Line Satu')
    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('p-1', { productionLineId: 'l-1' })
    // Tidak ada langkah memilih line yang diminta ke pengguna.
    expect(exists(wrapper, 'production-line-required-hint')).toBe(false)
    expect(text(wrapper, 'production-line-current')).toContain('Line Satu')
  })

  it('mengganti line memuat ulang ringkasan dengan line BARU dan mengingatnya', async () => {
    repoMocks.fetchLines.mockResolvedValue([LINE_1, LINE_2])

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)

    await selectLine(wrapper, 'l-2')

    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(2)
    expect(repoMocks.fetchSummary).toHaveBeenLastCalledWith('p-1', { productionLineId: 'l-2' })
    expect(window.localStorage.getItem(REMEMBERED_LINE_KEY)).toBe('l-2')
    // Nama line dari SERVER yang menyertai angkanya.
    expect(text(wrapper, 'production-line-current')).toContain('Line Dua')
  })

  it('mill tanpa satu pun line: arahan hubungi Admin, nol permintaan ringkasan, tanpa Coba Lagi', async () => {
    window.localStorage.clear()
    repoMocks.fetchLines.mockResolvedValue([])

    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-unavailable')).toBe(true)
    expect(text(wrapper, 'production-line-unavailable')).toContain('hubungi Admin')
    // "Mill belum punya line" BUKAN "daftarnya gagal dimuat" — dan hanya yang
    // kedua punya tombol Coba Lagi.
    expect(exists(wrapper, 'production-line-retry')).toBe(false)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
  })

  it('daftar line GAGAL dimuat: pesan jaringan DENGAN tombol Coba Lagi, bukan arahan Admin', async () => {
    window.localStorage.clear()
    repoMocks.fetchLines.mockRejectedValueOnce(NETWORK_ERROR)

    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-unavailable')).toBe(true)
    expect(text(wrapper, 'production-line-unavailable')).toContain('Periksa koneksi jaringan')
    expect(exists(wrapper, 'production-line-retry')).toBe(true)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

    repoMocks.fetchLines.mockResolvedValue([LINE_1])
    await wrapper.get('[data-testid="production-line-retry"]').trigger('click')
    await flushPromises()

    expect(exists(wrapper, 'production-line-select')).toBe(true)
  })

  it('akun tanpa business_unit_id berhenti TOTAL — nol permintaan, dan tanpa pemilih mill sebagai ganti', async () => {
    asRole('operator', null)

    const wrapper = await mountView()

    expect(exists(wrapper, 'no-mill-for-account')).toBe(true)
    expect(text(wrapper, 'no-mill-for-account')).toContain('hubungi Admin')
    expect(repoMocks.fetchLines).not.toHaveBeenCalled()
    expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
    // Dan TIDAK ada pemilih mill sebagai gantinya.
    expect(wrapper.findAll('select')).toHaveLength(0)
  })
})

/* ================================================================== */
/* unit_test_case 8-10 + 51-53 + skenario 23 + 24 — GALAT DAN KEADAAN  */
/* ================================================================== */

describe('galat dan keadaan', () => {
  // unit_test_case 8
  it('unit_test_case 8: 401 mengalihkan ke Login TANPA state galat jaringan', async () => {
    repoMocks.fetchSummary.mockRejectedValueOnce(UNAUTHENTICATED_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    // "Periksa koneksi Anda" pada sesi yang berakhir mengirim pembaca
    // memeriksa sinyal alih-alih masuk kembali.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)
    expect(exists(wrapper, 'retry-button')).toBe(false)
    expect(cards(wrapper)).toHaveLength(0)
  })

  it('401 dari daftar LINE pun mengalihkan ke Login, bukan mengisi state galat daftar line', async () => {
    repoMocks.fetchLines.mockRejectedValueOnce(UNAUTHENTICATED_ERROR)

    const wrapper = await mountView()

    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    expect(exists(wrapper, 'production-line-retry')).toBe(false)
    expect(exists(wrapper, 'network-error')).toBe(false)
  })

  // unit_test_case 9
  it('unit_test_case 9: 422 akun tanpa mill → arahan hubungi Admin, BUKAN galat jaringan', async () => {
    repoMocks.fetchPeriods.mockRejectedValueOnce(NO_MILL_ERROR)

    const wrapper = await mountView()

    expect(exists(wrapper, 'mill-not-linked')).toBe(true)
    expect(text(wrapper, 'mill-not-linked')).toContain('hubungi Admin')
    expect(text(wrapper, 'mill-not-linked')).toContain('bukan masalah jaringan')
    // State galat jaringan dan tombol coba-lagi TIDAK dirender.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'retry-button')).toBe(false)
    expect(cards(wrapper)).toHaveLength(0)
    // Dan sistem TIDAK menawarkan daftar seluruh mill sebagai gantinya.
    expect(exists(wrapper, 'pw-mill-select')).toBe(false)
  })

  it('422 pada /summary pun diperlakukan sebagai akun tanpa mill, bukan pesan validasi mentah', async () => {
    repoMocks.fetchSummary.mockRejectedValueOnce(NO_MILL_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(exists(wrapper, 'mill-not-linked')).toBe(true)
    expect(exists(wrapper, 'error-message')).toBe(false)
  })

  // unit_test_case 10
  it('unit_test_case 10: 403 line ingatan milik mill lain → minta pilih line, NOL angka', async () => {
    window.localStorage.setItem(REMEMBERED_LINE_KEY, 'l-2')
    repoMocks.fetchLines.mockResolvedValue([LINE_1, LINE_2])
    repoMocks.fetchSummary.mockRejectedValueOnce(FORBIDDEN_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(exists(wrapper, 'production-line-rejected')).toBe(true)
    expect(text(wrapper, 'production-line-rejected')).toContain('pilih Production Line kembali')
    expect(text(wrapper, 'production-line-rejected')).toContain(
      'Tidak satu angka pun dari mill lain ditampilkan',
    )

    // Jatuh ke keadaan 'belum ada line terpilih'.
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    // NOL kartu metrik dirender, dan NOL angka kelengkapan.
    expect(cards(wrapper)).toHaveLength(0)
    expect(exists(wrapper, 'pw-coverage')).toBe(false)
    // BUKAN galat jaringan.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)
    expect(pushMock).not.toHaveBeenCalled()
    // Dan nama mill yang tampil tetap mill AKUN, bukan mill lain.
    expect(text(wrapper, 'mill-current')).toContain('Mill Sungai Rambutan')
  })

  it('404 periode tidak ditemukan: pesan SERVER apa adanya, pilihan periode dikosongkan, tanpa Coba Lagi', async () => {
    repoMocks.fetchSummary.mockRejectedValueOnce(NOT_FOUND_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(text(wrapper, 'error-message')).toBe(NOT_FOUND_ERROR.message)
    expect(selectedOptionText(wrapper, 'period-select')).toBe(NO_PERIOD_SELECTED)
    expect(exists(wrapper, 'retry-button')).toBe(false)
    expect(cards(wrapper)).toHaveLength(0)
  })

  // unit_test_case 51 + skenario 23
  it('unit_test_case 51/skenario 23: galat jaringan → pesan + Coba Lagi, nol angka, bukan Admin dan bukan Login', async () => {
    repoMocks.fetchSummary.mockRejectedValueOnce(NETWORK_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    expect(text(wrapper, 'network-error')).toContain(NETWORK_ERROR.message)
    expect(exists(wrapper, 'retry-button')).toBe(true)
    expect(cards(wrapper)).toHaveLength(0)
    expect(exists(wrapper, 'pw-coverage')).toBe(false)
    // Bukan arahan Admin dan bukan pengalihan Login.
    expect(exists(wrapper, 'mill-not-linked')).toBe(false)
    expect(pushMock).not.toHaveBeenCalled()

    // Pilihan periode dan line SENGAJA tidak disentuh — kegagalan jaringan
    // bukan alasan membuang konteks kerja pengguna.
    expect(selectedOptionText(wrapper, 'period-select')).toContain('Periode Uji Process Water')
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe('Line Satu')

    // Dan sesudah Coba Lagi angkanya termuat.
    await wrapper.get('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    expect(cards(wrapper)).toHaveLength(9)
    expect(exists(wrapper, 'network-error')).toBe(false)
  })

  // unit_test_case 52 + skenario 24
  it('unit_test_case 52/skenario 24: luring DINYATAKAN apa adanya — nol angka, bukan layar diam', async () => {
    const onLine = vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false)

    const wrapper = await mountView()

    expect(exists(wrapper, 'offline-notice')).toBe(true)
    expect(text(wrapper, 'offline-notice')).toContain('memerlukan jaringan')
    expect(text(wrapper, 'offline-notice')).toContain('tidak disimpan di perangkat ini')

    // NOL angka: bukan angka dari pemuatan sebelumnya tanpa penanda.
    expect(cards(wrapper)).toHaveLength(0)
    expect(exists(wrapper, 'pw-coverage')).toBe(false)
    expect(exists(wrapper, 'pw-coverage-percent')).toBe(false)

    // LURING MENGHENTIKANNYA SEBELUM PERMINTAAN PERTAMA.
    expect(repoMocks.fetchLines).not.toHaveBeenCalled()
    expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

    // Dan layarnya TIDAK diam: kerangkanya tetap terender.
    expect(exists(wrapper, 'laporan-process-water-mobile')).toBe(true)
    expect(wrapper.text().length).toBeGreaterThan(200)

    // NOL baca-tulis cache laporan di localDb.
    for (const [name, spy] of Object.entries(localDbMocks)) {
      expect(spy, `localDb.${name}`).not.toHaveBeenCalled()
    }

    onLine.mockRestore()
  })

  it('kembali ONLINE memuat ulang pemuatan awal yang sempat tertahan luring', async () => {
    const onLine = vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false)

    const wrapper = await mountView()

    expect(repoMocks.fetchLines).not.toHaveBeenCalled()

    onLine.mockReturnValue(true)
    window.dispatchEvent(new Event('online'))
    await flushPromises()

    expect(repoMocks.fetchLines).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchPeriods).toHaveBeenCalledTimes(1)
    expect(exists(wrapper, 'offline-notice')).toBe(false)

    onLine.mockRestore()
  })

  it('pendengar online/offline dipasang di onMounted dan dilepas di onUnmounted', async () => {
    const add = vi.spyOn(window, 'addEventListener')
    const remove = vi.spyOn(window, 'removeEventListener')

    const wrapper = await mountView()

    expect(add.mock.calls.filter(([event]) => event === 'online')).toHaveLength(1)
    expect(add.mock.calls.filter(([event]) => event === 'offline')).toHaveLength(1)

    wrapper.unmount()

    expect(remove.mock.calls.filter(([event]) => event === 'online')).toHaveLength(1)
    expect(remove.mock.calls.filter(([event]) => event === 'offline')).toHaveLength(1)
  })

  // unit_test_case 53
  it('unit_test_case 53: daftar periode kosong → arahan Admin, dan NOL permintaan ringkasan', async () => {
    repoMocks.fetchPeriods.mockResolvedValue([])

    const wrapper = await mountView()

    expect(exists(wrapper, 'no-periods')).toBe(true)
    expect(text(wrapper, 'no-periods')).toContain('hubungi Admin')
    // Pemilih periode tampil KOSONG, bukan hilang: hanya opsi placeholder.
    expect(wrapper.findAll('[data-testid="period-select"] option')).toHaveLength(1)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
    // Keadaannya BUKAN keadaan galat.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)
    expect(exists(wrapper, 'mill-not-linked')).toBe(false)
  })

  it('pemilih periode memuat TEPAT periode yang diterbitkan server, dengan label stasiunnya', async () => {
    const wrapper = await mountView()

    const options = wrapper
      .findAll('[data-testid="period-select"] option')
      .map((option) => option.text())

    expect(options).toHaveLength(3)
    expect(options[0]).toBe(NO_PERIOD_SELECTED)
    expect(options[1]).toContain('Process Water — Periode Uji Process Water')
    expect(options[2]).toContain('Process Water — Periode Sebelumnya')
    // Nama periode stasiun lain tidak pernah masuk ke DOM: layar tidak
    // pernah menerimanya.
    expect(wrapper.text()).not.toContain('Engine Room')
    expect(wrapper.text()).not.toContain('Sterilizer')
  })

  it('respons /summary yang tiba BELAKANGAN tidak menimpa pilihan yang baru', async () => {
    let releaseFirst!: (value: unknown) => void

    repoMocks.fetchSummary
      .mockImplementationOnce(
        () =>
          new Promise((resolveFirst) => {
            releaseFirst = resolveFirst
          }),
      )
      .mockImplementation(
        (_periodId: string, scope?: { productionLineId?: string | null }) =>
          Promise.resolve(summaryForScope(scope, { total: { days_with_records: 9, reading_rows: 9 } })),
      )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')
    await selectPeriod(wrapper, 'p-2')

    // Respons LAMA tiba belakangan dan DIABAIKAN: tanpa penjaga ini ia
    // menimpa ringkasan baru dan memunculkan angka periode yang salah.
    releaseFirst(summaryFixture({ total: { days_with_records: 1, reading_rows: 1 } }))
    await flushPromises()

    expect(block(wrapper, 'pw-unit-total').text()).toContain('9')
    expect(block(wrapper, 'pw-unit-total').text()).not.toContain('Gabungan 2 unit1')
  })
})

/* ================================================================== */
/* unit_test_case 57 — LAYAR TIDAK MENULIS KE localDb                  */
/* ================================================================== */

describe('nol tulisan ke localDb', () => {
  // unit_test_case 57
  it('unit_test_case 57: seluruh alur dijalankan, dan setiap metode localDb menerima NOL pemanggilan', async () => {
    repoMocks.fetchLines.mockResolvedValue([LINE_1, LINE_2])

    const wrapper = await mountView()

    // muat line -> muat periode -> muat ringkasan -> ganti line -> toggle
    // ketiga rekap -> ekspor.
    await selectPeriod(wrapper, 'p-1')
    await selectLine(wrapper, 'l-2')

    for (const toggle of collapsibleToggles(wrapper)) {
      await toggle.trigger('click')
      await flushPromises()
    }

    await wrapper.get('[data-testid="pw-export-csv"]').trigger('click')
    await flushPromises()

    expect(repoMocks.exportCsv).toHaveBeenCalledTimes(1)

    for (const [name, spy] of Object.entries(localDbMocks)) {
      expect(spy, `localDb.${name} tidak boleh dipanggil`).not.toHaveBeenCalled()
    }

    // AUDIT STATIS: layar ini TIDAK MENGIMPOR localDb sama sekali —
    // ketiadaan mode luring disengaja, dan dinyatakan di layar.
    const code = stripComments(
      readFileSync(resolve(process.cwd(), 'src/views/LaporanProcessWaterView.vue'), 'utf-8'),
    )

    expect(code).not.toContain('localDb')
    expect(code).not.toContain('sqlite')
    expect(code).not.toContain('writeThroughSync')
  })

  it('satu-satunya penulisan localStorage adalah ingatan Production Line — bukan hasil laporan', async () => {
    const setItem = vi.spyOn(Storage.prototype, 'setItem')

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    const keys = setItem.mock.calls.map(([key]) => String(key))

    // Production Line adalah KONTEKS KERJA, bukan hasil laporan.
    expect(keys.every((key) => key.startsWith('msl_production_line_'))).toBe(true)

    for (const [, value] of setItem.mock.calls) {
      expect(String(value)).not.toContain('coverage')
      expect(String(value)).not.toContain('metrics')
      expect(String(value)).not.toContain('inspector')
    }

    expect(wrapper.html()).not.toContain('localDb')
  })
})

/* ================================================================== */
/* unit_test_case 54 + 55 + 56 — RUTE, PINTU MASUK, DAN RIAKNYA        */
/* ================================================================== */

/**
 * DIVERGENSI 3 — tech spec menamai penahanan Mill Management dan Admin
 * sebagai urusan router. ROUTER MOBILE TIDAK PUNYA PENJAGA PERAN SAMA
 * SEKALI: mobile/src/router/index.ts hanya memegang satu penjaga global atas
 * `meta.public`, yakni penjaga SESI, dan tidak satu rute pun membawa daftar
 * peran. Penolakan kedua peran itu terjadi DI SERVER dan dari ketiadaan jalur
 * masuk mobile bagi keduanya; layar WEB screen-160 yang menjadi layar mereka.
 *
 * Yang diasersi di bawah karena itu adalah kontrak yang SEBENARNYA, bukan
 * penolakan yang tidak ada. Kalau penjaga peran kelak ditambahkan, test ini
 * akan memerah dan memaksa catatan ini diperbarui — itu lebih baik daripada
 * test hijau yang memalsukan penolakan.
 */
describe('rute dan pintu masuk', () => {
  // unit_test_case 54
  it('unit_test_case 54: rute report-process-water ADA di /reports/process-water, meta.public false, tanpa daftar peran', () => {
    const source = readFileSync(resolve(process.cwd(), 'src/router/index.ts'), 'utf-8')
    const start = source.indexOf("name: 'report-process-water'")

    expect(start).toBeGreaterThan(-1)

    const record = source.slice(Math.max(0, start - 400), start + 400)

    expect(record).toContain("path: '/reports/process-water'")
    expect(record).toContain('LaporanProcessWaterView.vue')
    // Penjaga SESI, bukan penjaga peran.
    expect(record).toContain('meta: { public: false }')
    expect(record).not.toContain('roles')
    expect(record).not.toContain('allowedRoles')

    // Dan tidak satu rute pun di berkas itu memegang daftar peran — kalau
    // kelak ada, catatan DIVERGENSI 3 di atas wajib diperbarui bersamanya.
    expect(source).not.toContain('allowedRoles')
  })

  it('komponen laporan termount bagi Operator maupun Supervisor tanpa cabang tampilan', async () => {
    for (const role of ['operator', 'supervisor'] as const) {
      asRole(role, 'bu-1')
      const wrapper = await mountView()
      await selectPeriod(wrapper, 'p-1')

      expect(exists(wrapper, 'laporan-process-water-mobile'), role).toBe(true)
      expect(cards(wrapper).length, role).toBe(9)
      expect(exists(wrapper, 'pw-mill-select'), role).toBe(false)
    }
  })

  /**
   * unit_test_case 55 — SATU ENTRI yang menentukan tile Process Water hidup
   * atau kelabu. Tanpa baris itu layar ini ada, rutenya ada, seluruh test-nya
   * lulus, dan tile-nya tetap kelabu — pola kegagalan yang sudah pernah
   * terjadi.
   *
   * REPORT_ROUTES adalah const LOKAL di dalam <script setup>
   * ReportingPilihStasiunView.vue dan tidak diekspor, jadi ia diasersi atas
   * SUMBERNYA. Diasersi atas KUNCI/NILAI peta, BUKAN atas urutan: peta itu
   * tidak urut sort_order dan tidak punya asersi urutan.
   */
  it('unit_test_case 55: REPORT_ROUTES memuat entri process-water → report-process-water', () => {
    const source = readFileSync(
      resolve(process.cwd(), 'src/views/ReportingPilihStasiunView.vue'),
      'utf-8',
    )
    const code = stripComments(source)
    const start = code.indexOf('const REPORT_ROUTES')

    expect(start).toBeGreaterThan(-1)

    const map = code.slice(start, start + code.slice(start).indexOf('}') + 1)

    // KUNCINYA WAJIB BERKUTIP karena bertanda hubung — kunci bergaris-bawah
    // ('process_water') akan terkompilasi, terlihat benar, dan tidak pernah
    // cocok dengan satu station.type pun.
    expect(map).toContain("'process-water': 'report-process-water'")
    expect(map).not.toContain('process_water')

    // Dan nama rutenya SAMA PERSIS dengan yang terdaftar di router.
    const routerSource = readFileSync(resolve(process.cwd(), 'src/router/index.ts'), 'utf-8')

    expect(routerSource).toContain("name: 'report-process-water'")
  })

  /**
   * unit_test_case 56 — RIAK TEST YANG MEMANG DIRANCANG, DAN INI YANG
   * KESEMBILAN.
   *
   * mobile/tests/ReportingPilihStasiunView.spec.ts memakai satu kode stasiun
   * sebagai contoh 'belum berlaporan'. Begitu kode itu masuk ke REPORT_ROUTES,
   * contoh itu berhenti menguji apa pun — jadi contohnya DIPINDAHKAN ke jenis
   * yang masih belum berlaporan, bukan asersinya dilemahkan. Pemindahan
   * KEDELAPAN ('process-water' → 'solid-waste-disposal') terjadi pada
   * screen-161.
   *
   * Test ini menjaga riak itu TETAP BENAR dari sisi berkas ini: ia memeriksa
   * KODE STASIUN yang dipakai spec tetangga, bukan urutan tile-nya.
   *
   * DIPERBARUI pada screen-163 (2026-10-18) — PEMINDAHAN KESEMBILAN: laporan
   * Solid Waste Disposal mobile dibangun, 'solid-waste-disposal' masuk ke
   * REPORT_ROUTES, dan berkas INI adalah tempat kelima — serta yang paling
   * mudah terlewat — yang memerah karenanya, sebab ia MEMBACA spec tetangga
   * dari disk alih-alih mengimpornya. Pada pemindahan-pemindahan sebelumnya
   * situs ini hanya ditemukan dengan menjalankan seluruh suite, bukan dengan
   * membaca daftar berkas yang disentuh.
   *
   * Asersinya kini melacak kode FIKTIF 'zz-stasiun-fiktif' — kode yang tidak
   * ada di master station_types dan karena itu tidak akan pernah masuk
   * REPORT_ROUTES, sehingga pemindahan KESEMBILAN adalah yang TERAKHIR —
   * DITAMBAH asersi cermin untuk contoh yang pensiun. Lebih kuat, bukan lebih
   * longgar: tidak satu asersi pun dihapus atau dilonggarkan.
   */
  it('unit_test_case 56: contoh "belum berlaporan" pada spec tetangga BUKAN stasiun yang sudah berlaporan', () => {
    const neighbourSpec = readFileSync(
      resolve(process.cwd(), 'tests/ReportingPilihStasiunView.spec.ts'),
      'utf-8',
    )
    const reportRoutesSource = stripComments(
      readFileSync(resolve(process.cwd(), 'src/views/ReportingPilihStasiunView.vue'), 'utf-8'),
    )
    const start = reportRoutesSource.indexOf('const REPORT_ROUTES')
    const map = reportRoutesSource.slice(
      start,
      start + reportRoutesSource.slice(start).indexOf('}') + 1,
    )

    // Setiap kode stasiun yang dipakai spec tetangga sebagai FIXTURE —
    // diambil dari kodenya, bukan dari urutannya.
    const usedTypes = [
      ...new Set(
        [...stripComments(neighbourSpec).matchAll(/type: '([a-z-]+)'/g)].map((match) => match[1]),
      ),
    ].filter((type) => type !== 'other')

    expect(usedTypes.length).toBeGreaterThan(0)

    // (a) 'process-water' TIDAK BOLEH lagi muncul sebagai fixture di sana
    // SAMA SEKALI: ia kini punya laporan mobile, jadi tile-nya AKTIF dan
    // setiap asersi yang memakainya sebagai contoh 'belum berlaporan'
    // berhenti menguji apa pun.
    expect(usedTypes).not.toContain('process-water')
    expect(neighbourSpec).not.toContain("type: 'process-water'")
    expect(neighbourSpec).not.toContain('station-tile-process-water')
    expect(neighbourSpec).not.toContain('Laporan Process Water belum tersedia')

    // (b) Spec tetangga memakai jenis stasiun untuk DUA maksud yang berlawanan
    // (contoh 'belum berlaporan' DAN contoh 'sudah berlaporan meski tidak
    // aktif'), jadi yang diasersi bukan "setiap jenis belum berlaporan",
    // melainkan bahwa SETIDAKNYA SATU jenis yang benar-benar belum berlaporan
    // masih dipakai di sana — tanpanya jebakan utama berkas itu hampa.
    const unbuilt = usedTypes.filter((type) => !map.includes(`'${type}': 'report-`))

    expect(
      unbuilt,
      `tidak ada satu pun contoh 'belum berlaporan' yang tersisa di spec tetangga`,
    ).not.toEqual([])

    // (c) Contoh penggantinya pada pemindahan KEDELAPAN adalah
    // 'solid-waste-disposal' (sort_order 150). Ia bertahan satu hari: pada
    // screen-163 (2026-10-18) laporan Solid Waste Disposal mobile dibangun,
    // tile-nya menjadi AKTIF, dan contoh itu PENSIUN — PEMINDAHAN KESEMBILAN.
    //
    // DAN PEMINDAHAN KESEMBILAN ITU TIDAK MEMAKAI STASIUN NYATA. Spec tetangga
    // TIDAK membaca master station_types: ia menyuntikkan daftarnya sendiri
    // lewat makeStation({...}) + getActiveAndPlaceholderStationsMock, jadi
    // kode FIKTIF tersedia di sana. Contohnya kini 'zz-stasiun-fiktif', dan
    // karena tidak ada layar masa depan yang dapat memasukkannya ke
    // REPORT_ROUTES, inilah pemindahan TERAKHIR — pengejaran yang sempat
    // dinyatakan "mustahil dilanjutkan" pada screen-161 justru berhenti di
    // sini. Pernyataan itu keliru; koreksinya ada di docblock spec tetangga.
    expect(unbuilt).toContain('zz-stasiun-fiktif')
    expect(map).not.toContain("'zz-stasiun-fiktif'")
    expect(neighbourSpec).toContain('Laporan Stasiun Fiktif belum tersedia')

    // Kode fiktifnya WAJIB lolos dari pungutan di atas: huruf kecil dan tanda
    // hubung saja, dan bukan 'other'. Diasersi di sini supaya penjaga ini
    // tidak diam-diam menjadi hampa bila kelak kodenya diubah bentuknya.
    expect('zz-stasiun-fiktif').toMatch(/^[a-z-]+$/)
    expect(usedTypes).toContain('zz-stasiun-fiktif')

    // (d) Dan contoh yang PENSIUN tidak lagi dipakai di sana sama sekali —
    // asersi cermin yang sama seperti (a), karena 'solid-waste-disposal' kini
    // berada di pihak yang SUDAH berlaporan.
    expect(usedTypes).not.toContain('solid-waste-disposal')
    expect(neighbourSpec).not.toContain("type: 'solid-waste-disposal'")
    expect(neighbourSpec).not.toContain('station-tile-solid-waste-disposal')
    expect(neighbourSpec).not.toContain('Laporan Solid Waste Disposal belum tersedia')
    expect(map).toContain("'solid-waste-disposal': 'report-solid-waste-disposal'")
  })
})

/* ================================================================== */
/* Menu dan navigasi (konvensi bersama layar mobile lain)              */
/* ================================================================== */

describe('menu dan navigasi', () => {
  it('menu hamburger terbuka dan Logout mengarahkan ke Login', async () => {
    const wrapper = await mountView()

    expect(exists(wrapper, 'nav-menu')).toBe(false)

    await wrapper.get('[data-testid="hamburger-button"]').trigger('click')

    expect(exists(wrapper, 'nav-menu')).toBe(true)

    await wrapper.get('[data-testid="nav-menu-logout"]').trigger('click')
    await flushPromises()

    expect(logoutMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
  })

  it('breadcrumb Home bernavigasi ke home', async () => {
    const wrapper = await mountView()

    await wrapper.get('[data-testid="breadcrumb-home"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'home' })
  })
})

/* ================================================================== */
/* STANDAR KERAPIAN TAMPILAN                                           */
/* ================================================================== */

describe('disiplin kelas CSS', () => {
  /**
   * Tiap kelas yang dipakai markup WAJIB punya definisi. Kelas tanpa definisi
   * tidak menggagalkan satu asersi pun — ia hanya membuat bagian layar
   * kehilangan gayanya tanpa satu tanda — dan cacat persis itu ada di
   * LaporanDepricarpingView.vue (.metric-values, yang tidak terdefinisi di
   * satu berkas pun di src/) dan SENGAJA tidak disalin ke sini.
   *
   * Yang diperiksa DI SINI adalah sumbernya; pemeriksaan atas HASIL COMPILE
   * ada di spec browser, tempat getComputedStyle memang berlaku.
   */
  it('setiap kelas yang dipakai markup punya definisi di blok <style> atau stylesheet bersama', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'p-1')

    // Dibaca dari DISK, bukan dari import.meta.url: di Vitest nilai itu
    // sebuah URL http:// dan fileURLToPath menolaknya. process.cwd() adalah
    // akar proyek mobile (root Vitest), dan itulah yang dipakai.
    const sources = [
      'src/views/LaporanProcessWaterView.vue',
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

  it('kelas yang dipakai pada KEADAAN LAIN pun punya definisi (galat, luring, kosong, tanpa line)', async () => {
    const sources = [
      'src/views/LaporanProcessWaterView.vue',
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

    const used = new Set<string>()

    async function collect(wrapper: VueWrapper): Promise<void> {
      for (const element of wrapper.findAll('[class]')) {
        for (const className of (element.attributes('class') ?? '').split(/\s+/)) {
          if (className !== '') {
            used.add(className)
          }
        }
      }
    }

    // (a) tanpa line terpilih.
    window.localStorage.clear()
    repoMocks.fetchLines.mockResolvedValue([LINE_1, LINE_2])
    await collect(await mountView())

    // (b) galat jaringan dengan tombol Coba Lagi.
    repoMocks.fetchPeriods.mockRejectedValueOnce(NETWORK_ERROR)
    await collect(await mountView())

    // (c) mill tanpa line sama sekali.
    repoMocks.fetchLines.mockResolvedValue([])
    await collect(await mountView())

    // (d) 422 akun tanpa mill.
    repoMocks.fetchLines.mockResolvedValue([LINE_1])
    repoMocks.fetchPeriods.mockRejectedValueOnce(NO_MILL_ERROR)
    await collect(await mountView())

    // (e) luring.
    const onLine = vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false)

    await collect(await mountView())
    onLine.mockRestore()

    // (f) akun tanpa mill sama sekali.
    asRole('operator', null)
    await collect(await mountView())

    expect(used.size).toBeGreaterThan(10)

    const undefinedClasses = [...used].filter((className) => !sources.includes(`.${className}`))

    expect(undefinedClasses, `kelas tanpa definisi: ${undefinedClasses.join(', ')}`).toEqual([])
  })
})
