/**
 * LaporanEngineRoomView.spec.ts — screen-159--laporan-engine-room-mobile /
 * usecase-165--laporan-engine-room-mobile.
 *
 * Satu test (atau lebih) per `test_scenarios[].component_test` pada tech spec
 * screen-159 — 39 skenario. Kembaran KETIGA BELAS dari pola yang sama, dari
 * LaporanSterilizerView.spec.ts (screen-135) sampai
 * LaporanEffluentPlantView.spec.ts (screen-157).
 *
 * YANG DI-MOCK: ketiga fungsi jaringan + saveCsvFile pada repo laporan, repo
 * Production Line, router, dan ketiga store. `statusValueLabel` dan
 * `STATUS_VALUE_LABELS` SENGAJA TIDAK DI-MOCK — keduanya diambil dari modul
 * ASLI lewat vi.importActual, karena label Run / Standby / Off yang dirender
 * layar ini WAJIB berasal dari satu salinan peta itu. Mock yang
 * menduplikasinya akan membuat asersi label selalu hijau terhadap peta
 * tiruan, dan peta tiruan yang menyimpang dari Display::OPTION_LABELS tidak
 * akan terlihat oleh satu test pun.
 *
 * Bentuk permintaan HTTP-nya milik engineRoomReportRepo.spec.ts dan tidak
 * diulang di sini; yang diuji di berkas ini adalah KEPUTUSAN LAYAR atas
 * jawaban repo.
 *
 * DAFTAR LINE DI-MOCK PADA '@/services/productionLineRepo', BUKAN PADA REPO
 * LAPORAN INI. Layar ini memakai ULANG
 * productionLineRepo.fetchProductionLinesForReport(null) verbatim dari
 * screen-135 — endpoint itu tidak diduplikasi di bawah prefix
 * engine-room-reports.
 *
 * ────────────────────────────────────────────────────────────────────────
 * ENAM HAL YANG MEMBEDAKAN LAYAR INI DARI KEDUA BELAS LAPORAN SEBELUMNYA
 * ────────────────────────────────────────────────────────────────────────
 * 1. DUA PULUH TIGA KARTU — gulir terpanjang di seluruh laporan mobile, dan
 *    KEENAM BLOKNYA DIBANGUN DARI FIELD `group` PADA RESPONS. Yang diasersi
 *    karena itu bukan "ada dua puluh dua kartu" saja, melainkan bahwa sebuah
 *    kolom yang DIPINDAHKAN group-nya oleh server berpindah blok tanpa satu
 *    berkas klien disentuh, dan bahwa kolom BARU dari migrasi muncul di blok
 *    yang benar. Kedua puluh dua penyebut fixture BERBEDA-BEDA; dengan
 *    penyebut seragam, satu penyebut bersama lolos tanpa terlihat.
 *
 * 2. KETERANGAN GENSET DIRENDER TEPAT DUA KALI — sekali DI DALAM tiap blok
 *    genset. Asersinya MENGHITUNG kemunculannya, bukan memastikan ia ada:
 *    "ada" akan lolos pada implementasi yang menaruhnya sekali di atas
 *    halaman, dan itu persis cacat yang dihindari — di ponsel keterangan di
 *    atas halaman sudah tergulir jauh ketika blok yang diterangkannya
 *    akhirnya terlihat.
 *
 * 3. PENJAGA PRODUCTION LINE ADA DI loadSummary(), SEBELUM PERMINTAAN APA PUN.
 *    Tiga test terpisah, satu per jalur (muat awal, ganti periode, coba
 *    lagi), masing-masing mengasersi NOL pemanggilan fetchSummary. Penjaga
 *    yang hanya ada di template dilewati ketiganya.
 *
 * 4. URUTAN PENENTUAN LINE TIGA LANGKAH, BUKAN EMPAT: rute -> ingatan
 *    perangkat -> minta pengguna. TIDAK ADA "pilih line pertama" DAN —
 *    berbeda dari LaporanEffluentPlantView.vue — TIDAK ADA "pilih
 *    satu-satunya line". AKIBATNYA: pemilih line DIRENDER walau daftarnya
 *    hanya berisi SATU entri, dan tidak ada angka sampai pengguna memilih.
 *    Asersi effluent "tidak ada pemilih bila hanya satu line" SALAH di sini.
 *
 * 5. SEBARAN STATUS BERNILAI TIGA, dan 'standby' TIDAK PERNAH dilipat ke
 *    'off'. Angka tiap baris dibaca DARI SEL DI DALAM BARISNYA SENDIRI
 *    (data-status-value + data-testid="status-value-label"), bukan dari
 *    pemindaian teks kontainer: 'Run' / 'Off' / 'Standby' panjangnya 2-7
 *    karakter dan cocok di dalam kata lain, sehingga asersi substring
 *    tingkat kontainer hijau tanpa membuktikan apa pun. Pelajaran itu sudah
 *    dibayar tiga kali di sisi web.
 *
 * 6. SATU KARTU YANG BUKAN RATA-RATA. Seri ekspor energi memuat nilai
 *    TERAKHIR per tanggal beserta slot asalnya, dan PONSEL TIDAK MENENTUKAN
 *    SLOT MANA YANG TERAKHIR. Fixture seri di bawah sengaja berurutan
 *    '06:00' -> '23:00' -> '13:00': pada kisi kanonis 07:00..06:00 slot
 *    '06:00' adalah posisi TERAKHIR, sementara urutan leksikal memilihnya
 *    sebagai yang paling AWAL. Layar yang mengurutkan slot membalik urutan
 *    barisnya dan test ini gagal.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DIVERGENSI YANG DICATAT DI SINI
 * ────────────────────────────────────────────────────────────────────────
 * PORSI SEBARAN DIHITUNG DI KLIEN, dan itu BUKAN pelanggaran aturan
 * nol-aritmetika. Payload Engine Room tidak menerbitkan `share` sama sekali
 * (EngineRoomReportService::statusDistributionOf() menerbitkan known_values +
 * counts + not_recorded + recorded_slots), jadi porsinya dibentuk
 * formatShare() dengan rumus, pembulatan, dan pemisah desimal yang DISALIN
 * PERSIS dari helper $porsi pada blade screen-158. Yang menjadi syaratnya
 * adalah PENYEBUTNYA: `recorded_slots` DARI RESPONS, tidak pernah
 * expected_slots. Dua test di bawah memakunya digit demi digit, termasuk
 * kasus tech-spec note #18 (15/20 -> 75,0%).
 *
 * TINGGI BATANG TREN juga skala TAMPILAN (28% + 72%×(v−min)/(max−min)) atas
 * `daily_trend[].avg` — sama dengan kedua belas saudaranya, dan ia tidak
 * pernah menyentuh seri energi.
 *
 * SKENARIO 29 (Mill Management / Admin ditolak ROUTER) TIDAK DAPAT DIPENUHI
 * SEPERTI TERTULIS: router mobile hanya punya penjaga SESI (`meta.public`),
 * tanpa daftar peran per rute — lihat test di bagian "router" di bawah, yang
 * mengasersi kontrak yang SEBENARNYA alih-alih memalsukan penolakan yang
 * tidak ada.
 *
 * PERINGATAN YANG DIHARAPKAN: "Failed to resolve directive: stacked-table".
 * `v-stacked-table` didaftarkan global di src/main.ts, bukan di setup test —
 * identik dengan kedua belas saudaranya, dan tidak fatal. Jangan mencoba
 * mengasersinya hilang.
 *
 * TANGGALNYA RELATIF, bukan kalender tetap. Nama periodenya pun sengaja
 * bukan nama bulan, supaya slug nama berkas ekspor tidak ikut basi.
 *
 * BENTUK GALAT — DATAR, TANPA `response`. Ketiadaan `status` itulah yang
 * membedakan "jaringan putus" dari "server menjawab 401/403/404/422", dan
 * urutan cabang handleError bergantung padanya.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import LaporanEngineRoomView from '@/views/LaporanEngineRoomView.vue'

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
 * apiClient di-mock supaya modul ASLI engineRoomReportRepo (yang diimpor
 * lewat importActual di bawah, demi statusValueLabel yang sungguhan) tidak
 * membangun instance axios dan tidak pernah menyentuh jaringan.
 */
vi.mock('@/services/apiClient', () => ({
  default: { get: vi.fn() },
}))

/**
 * EMPAT fungsi yang di-mock — dan TIDAK ADA fetchBusinessUnits, karena
 * /api/engine-room-reports/business-units/options Admin saja. Mock yang
 * menambahkannya akan membuat test "tidak dipanggil" selalu hijau tanpa arti.
 */
const { repoMocks } = vi.hoisted(() => ({
  repoMocks: {
    fetchPeriods: vi.fn(),
    fetchSummary: vi.fn(),
    exportCsv: vi.fn(),
    saveCsvFile: vi.fn(),
  },
}))

vi.mock('@/services/engineRoomReportRepo', async () => {
  const actual =
    await vi.importActual<typeof import('@/services/engineRoomReportRepo')>(
      '@/services/engineRoomReportRepo',
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
    fetchAndCacheStationsForProductionLine: vi.fn(),
  },
}))

vi.mock('@/services/productionLineRepo', () => ({
  default: productionLineMocks,
  productionLineRepo: productionLineMocks,
  ...productionLineMocks,
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

const PERIOD_START = isoDaysFromNow(-30)
const PERIOD_END = isoDaysFromNow(-1)
const PREV_START = isoDaysFromNow(-60)
const PREV_END = isoDaysFromNow(-31)
const DAY_A = isoDaysFromNow(-26)
const DAY_B = isoDaysFromNow(-25)
const DAY_C = isoDaysFromNow(-24)

/* ------------------------------------------------------------------ */
/* Fixture                                                             */
/* ------------------------------------------------------------------ */

const PERIOD = {
  id: 'per-1',
  name: 'Periode Uji Engine Room',
  start_date: PERIOD_START,
  end_date: PERIOD_END,
  status: 'open',
  // DENGAN TANDA HUBUNG.
  station_type: 'engine-room',
  station_type_label: 'Engine Room',
}

const PERIOD_CLOSED = {
  id: 'per-2',
  name: 'Periode Sebelumnya',
  start_date: PREV_START,
  end_date: PREV_END,
  status: 'closed',
  station_type: 'engine-room',
  station_type_label: 'Engine Room',
}

const LINE_1 = { id: 'pl-1', name: 'Line Satu' }
const LINE_2 = { id: 'pl-2', name: 'Line Dua' }
const LINE_3 = { id: 'pl-3', name: 'Line Tiga' }

const REMEMBERED_LINE_KEY = 'msl_production_line_user-1'

/** KEDUA PULUH DUA kolom ukur, urutan EngineRoomReportService::NUMERIC_METRICS. */
const METRIC_COLUMNS = [
  'steam_turbine_inlet_pressure_bar',
  'steam_turbine_inlet_temp_c',
  'steam_turbine_exhaust_pressure_bar',
  'steam_turbine_rpm',
  'steam_turbine_alternator_bearing_temp_1_c',
  'steam_turbine_alternator_bearing_temp_2_c',
  'diesel_gen_1_load_kw',
  'diesel_gen_1_amperage_a',
  'diesel_gen_1_jacket_water_temp_c',
  'diesel_gen_1_lube_oil_pressure_bar',
  'diesel_gen_2_load_kw',
  'diesel_gen_2_amperage_a',
  'diesel_gen_2_jacket_water_temp_c',
  'diesel_gen_2_lube_oil_pressure_bar',
  'electrical_sync_total_factory_load_kw',
  'electrical_sync_system_frequency_hz',
  'electrical_sync_power_factor',
  'electrical_sync_busbar_voltage_v',
  'air_compressor_1_pressure_bar',
  'compressor_2_pressure_bar',
  'battery_charger_ups_voltage_v',
  'fuel_tank_level',
]

const METRIC_GROUPS: Record<string, string> = {
  steam_turbine_inlet_pressure_bar: 'steam_turbine',
  steam_turbine_inlet_temp_c: 'steam_turbine',
  steam_turbine_exhaust_pressure_bar: 'steam_turbine',
  steam_turbine_rpm: 'steam_turbine',
  steam_turbine_alternator_bearing_temp_1_c: 'steam_turbine',
  steam_turbine_alternator_bearing_temp_2_c: 'steam_turbine',
  diesel_gen_1_load_kw: 'diesel_gen_1',
  diesel_gen_1_amperage_a: 'diesel_gen_1',
  diesel_gen_1_jacket_water_temp_c: 'diesel_gen_1',
  diesel_gen_1_lube_oil_pressure_bar: 'diesel_gen_1',
  diesel_gen_2_load_kw: 'diesel_gen_2',
  diesel_gen_2_amperage_a: 'diesel_gen_2',
  diesel_gen_2_jacket_water_temp_c: 'diesel_gen_2',
  diesel_gen_2_lube_oil_pressure_bar: 'diesel_gen_2',
  electrical_sync_total_factory_load_kw: 'electrical_sync',
  electrical_sync_system_frequency_hz: 'electrical_sync',
  electrical_sync_power_factor: 'electrical_sync',
  electrical_sync_busbar_voltage_v: 'electrical_sync',
  air_compressor_1_pressure_bar: 'compressor',
  compressor_2_pressure_bar: 'compressor',
  battery_charger_ups_voltage_v: 'auxiliary',
  fuel_tank_level: 'auxiliary',
}

/**
 * KEDUA PULUH DUA penyebut, SELURUHNYA BERBEDA.
 *
 * Penyebut seragam akan membuat asersi "tiap kartu mencetak penyebutnya
 * sendiri" dan "tidak ada blok yang mewarisi penyebut blok lain" selalu hijau
 * — justru dua cacat yang paling mungkin muncul pada markup yang di-loop,
 * dan di ponsel keduanya LEBIH sulit terlihat karena kartunya tidak pernah
 * berdampingan.
 */
const READING_COUNTS: Record<string, number> = {
  steam_turbine_inlet_pressure_bar: 250,
  steam_turbine_inlet_temp_c: 249,
  steam_turbine_exhaust_pressure_bar: 248,
  steam_turbine_rpm: 247,
  steam_turbine_alternator_bearing_temp_1_c: 246,
  steam_turbine_alternator_bearing_temp_2_c: 245,
  diesel_gen_1_load_kw: 200,
  diesel_gen_1_amperage_a: 199,
  diesel_gen_1_jacket_water_temp_c: 198,
  diesel_gen_1_lube_oil_pressure_bar: 197,
  diesel_gen_2_load_kw: 11,
  diesel_gen_2_amperage_a: 10,
  diesel_gen_2_jacket_water_temp_c: 9,
  diesel_gen_2_lube_oil_pressure_bar: 8,
  electrical_sync_total_factory_load_kw: 240,
  electrical_sync_system_frequency_hz: 239,
  // KOLOM YANG TIDAK PERNAH TERISI sepanjang periode.
  electrical_sync_power_factor: 0,
  electrical_sync_busbar_voltage_v: 238,
  air_compressor_1_pressure_bar: 120,
  compressor_2_pressure_bar: 119,
  battery_charger_ups_voltage_v: 60,
  fuel_tank_level: 59,
}

/** KEENAM blok berjudul, dengan jumlah kartu metrik di dalamnya. */
const METRIC_BLOCKS = [
  { testid: 'metric-block-steam-turbine', group: 'steam_turbine', title: 'Turbin Uap', cards: 6 },
  { testid: 'metric-block-diesel-gen-1', group: 'diesel_gen_1', title: 'Genset 1', cards: 4 },
  { testid: 'metric-block-diesel-gen-2', group: 'diesel_gen_2', title: 'Genset 2', cards: 4 },
  {
    testid: 'metric-block-electrical-sync',
    group: 'electrical_sync',
    title: 'Kelistrikan Tersinkron',
    cards: 4,
  },
  { testid: 'metric-block-compressor', group: 'compressor', title: 'Kompresor', cards: 2 },
  { testid: 'metric-block-auxiliary', group: 'auxiliary', title: 'Penunjang', cards: 2 },
]

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
    group: METRIC_GROUPS[column] ?? 'auxiliary',
    ...overrides,
  }
}

/**
 * KEDUA PULUH DUA entri.
 *
 * diesel_gen_2_load_kw sengaja membawa avg 40 di LUAR rentang min 10 ..
 * max 20: mustahil dari perhitungan apa pun, dan itulah yang membuat "nol
 * aritmetika di klien" dapat dibuktikan di layar.
 *
 * electrical_sync_power_factor TIDAK PERNAH TERISI: penyebut 0, seluruh
 * angkanya null. Kartunya tetap harus dirender, dan tetap di dalam blok
 * Kelistrikan Tersinkron.
 *
 * Frekuensi 43 Hz dan tegangan busbar 310 V ada di sini justru karena
 * keduanya terlihat "mencurigakan": nol elemen penanda di luar batas tetap
 * yang benar.
 */
function metrics(): Record<string, Record<string, unknown>> {
  return {
    steam_turbine_inlet_pressure_bar: metric('steam_turbine_inlet_pressure_bar', 18.2, 21.4, 24.9),
    steam_turbine_inlet_temp_c: metric('steam_turbine_inlet_temp_c', 280, 305, 330),
    steam_turbine_exhaust_pressure_bar: metric(
      'steam_turbine_exhaust_pressure_bar',
      0.12,
      0.31,
      0.58,
    ),
    steam_turbine_rpm: metric('steam_turbine_rpm', 4800, 5100, 5400),
    steam_turbine_alternator_bearing_temp_1_c: metric(
      'steam_turbine_alternator_bearing_temp_1_c',
      42.5,
      55.1,
      68.9,
    ),
    steam_turbine_alternator_bearing_temp_2_c: metric(
      'steam_turbine_alternator_bearing_temp_2_c',
      41.8,
      54.2,
      67.3,
    ),
    diesel_gen_1_load_kw: metric('diesel_gen_1_load_kw', 700, 800, 900),
    diesel_gen_1_amperage_a: metric('diesel_gen_1_amperage_a', 1100, 1250, 1400),
    diesel_gen_1_jacket_water_temp_c: metric('diesel_gen_1_jacket_water_temp_c', 72.1, 81.6, 92.4),
    diesel_gen_1_lube_oil_pressure_bar: metric('diesel_gen_1_lube_oil_pressure_bar', 3.1, 4.05, 4.9),
    diesel_gen_2_load_kw: metric('diesel_gen_2_load_kw', 10, 40, 20),
    diesel_gen_2_amperage_a: metric('diesel_gen_2_amperage_a', 18, 62, 95),
    diesel_gen_2_jacket_water_temp_c: metric('diesel_gen_2_jacket_water_temp_c', 38.4, 44.9, 51.2),
    diesel_gen_2_lube_oil_pressure_bar: metric('diesel_gen_2_lube_oil_pressure_bar', 2.8, 3.42, 4.1),
    electrical_sync_total_factory_load_kw: metric(
      'electrical_sync_total_factory_load_kw',
      920,
      1180,
      1460,
    ),
    electrical_sync_system_frequency_hz: metric(
      'electrical_sync_system_frequency_hz',
      43,
      49.82,
      51.1,
    ),
    electrical_sync_power_factor: metric('electrical_sync_power_factor', null, null, null),
    electrical_sync_busbar_voltage_v: metric('electrical_sync_busbar_voltage_v', 310, 388, 415),
    air_compressor_1_pressure_bar: metric('air_compressor_1_pressure_bar', 5.2, 6.4, 7.1),
    compressor_2_pressure_bar: metric('compressor_2_pressure_bar', 4.9, 6.1, 6.8),
    battery_charger_ups_voltage_v: metric('battery_charger_ups_voltage_v', 22.4, 24.1, 26.8),
    fuel_tank_level: metric('fuel_tank_level', 18, 54, 92),
  }
}

/** Lihat catatan #6 pada docblock berkas — urutan slot BUKAN urutan leksikal. */
function energySeries(): Array<Record<string, unknown>> {
  return [
    { date: DAY_A, last_value: 4100, time_slot: '06:00', reading_count: 24 },
    { date: DAY_B, last_value: 3900, time_slot: '23:00', reading_count: 17 },
    { date: DAY_C, last_value: 4250, time_slot: '13:00', reading_count: 9 },
  ]
}

/**
 * KEDUA sebaran status, TIGA nilai masing-masing.
 *
 * Genset 1: 'standby' ABSEN dari `counts` sementara tetap ada di
 * `known_values` — baris Standby WAJIB tetap tercetak bernilai 0, karena
 * iterasinya atas known_values. Angka-angkanya sengaja KONSISTEN secara
 * penjumlahan (10 + 2 + 7 = 19) karena porsinya diasersi digit demi digit.
 *
 * Genset 2: ketiga nilai HADIR, standby 6 dan off 4 sebagai dua angka BERBEDA
 * — melipat keduanya menghasilkan 10, dan tidak ada satu sel pun bernilai 10
 * pada sebaran ini.
 */
function statusDistribution(): Record<string, Record<string, unknown>> {
  return {
    diesel_gen_1_status: {
      known_values: ['run', 'standby', 'off'],
      counts: { run: 10, off: 2 },
      not_recorded: 7,
      recorded_slots: 19,
    },
    diesel_gen_2_status: {
      known_values: ['run', 'standby', 'off'],
      counts: { run: 0, standby: 6, off: 4 },
      not_recorded: 10,
      recorded_slots: 20,
    },
  }
}

function unitMetrics(avg: number | null, readingCount: number): Record<string, unknown> {
  return Object.fromEntries(
    METRIC_COLUMNS.map((column) => [
      column,
      { min: avg, avg, max: avg, reading_count: readingCount, group: METRIC_GROUPS[column] },
    ]),
  )
}

function summaryFixture(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Sungai Rambutan' },
    production_line: { id: 'pl-1', name: 'Line Satu' },
    period: {
      id: 'per-1',
      name: 'Periode Uji Engine Room',
      start_date: PERIOD_START,
      end_date: PERIOD_END,
      status: 'open',
    },
    has_data: true,
    coverage: {
      filled_slots: 260,
      expected_slots: 1440,
      // 260/1440 = 18,1 — dan server mengirim 88,8. Layar yang menghitung
      // sendiri menggambar 18,1%.
      coverage_percent: 88.8,
      engine_room_unit_count: 2,
      slots_per_unit_per_day: 24,
      days_in_period: 30,
      days_counted: 30,
      period_running: false,
    },
    metrics: metrics(),
    daily_energy_export: { series: energySeries(), has_data: true },
    status_distribution: statusDistribution(),
    maintenance_remarks: [
      { date: DAY_A, time_slot: '07:00', text: 'Ganti filter lube oil genset 1' },
      { date: DAY_A, time_slot: '13:00', text: 'Ganti filter lube oil genset 1' },
      { date: DAY_B, time_slot: '21:00', text: 'Ganti filter lube oil genset 1' },
    ],
    findings: [
      { date: DAY_A, time_slot: '07:00', text: 'Getaran tidak wajar pada bearing alternator 2' },
      { date: DAY_B, time_slot: '03:00', text: 'Tegangan busbar turun saat genset 2 start' },
    ],
    daily_trend: {
      electrical_sync_total_factory_load_kw: [
        { date: DAY_A, avg: 1180, reading_count: 24 },
        // null — BUKAN 0: batangnya KOSONG, bukan batang nol.
        { date: DAY_B, avg: null, reading_count: 0 },
        { date: DAY_C, avg: 1320, reading_count: 9 },
      ],
    },
    by_unit: [
      {
        engine_room_id: 'ER-1',
        reading_rows: 180,
        reading_count: 180,
        days_with_rows: 12,
        metrics: unitMetrics(7.5, 180),
        energy_export: { last_value: 4100, date: DAY_A, time_slot: '06:00', reading_count: 24 },
      },
      {
        // Unit yang punya record tetapi TIDAK SATU PUN pembacaan.
        engine_room_id: 'ER-2',
        reading_rows: 0,
        reading_count: 0,
        days_with_rows: 0,
        metrics: unitMetrics(null, 0),
        energy_export: { last_value: null, date: null, time_slot: null, reading_count: 0 },
      },
    ],
    daily: [
      {
        date: DAY_A,
        reading_rows: 3,
        metrics_avg: {
          diesel_gen_1_load_kw: { avg: 790, reading_count: 3 },
          electrical_sync_power_factor: { avg: null, reading_count: 0 },
        },
        energy_export: { last_value: 4100, date: DAY_A, time_slot: '06:00', reading_count: 24 },
      },
      {
        date: DAY_B,
        reading_rows: 4,
        metrics_avg: {
          diesel_gen_1_load_kw: { avg: 810, reading_count: 4 },
          electrical_sync_power_factor: { avg: null, reading_count: 0 },
        },
        energy_export: { last_value: 3900, date: DAY_B, time_slot: '23:00', reading_count: 17 },
      },
    ],
    // 3 + 4 = 7, dan server mengirim 99 — layar memakai angka server.
    daily_total: { reading_rows: 99 },
    total: { days_with_records: 2, reading_rows: 260 },
    ...overrides,
  }
}

const LINES_BY_ID: Record<string, { id: string; name: string }> = {
  'pl-1': LINE_1,
  'pl-2': LINE_2,
  'pl-3': LINE_3,
}

/**
 * Ringkasan yang blok `production_line`-nya DIBENTUK DARI LINE YANG DIMINTA,
 * bukan dipaku ke Line Satu: layar mendahulukan nama line dari SERVER atas
 * nama dari daftar lokalnya (itulah line yang benar-benar dipakai saat
 * menghitung), jadi fixture yang mengabaikan argumennya akan membuat setiap
 * asersi "line mana yang berlaku" membaca jawaban yang salah — dan selalu
 * hijau.
 */
function summaryForScope(
  scope?: { productionLineId?: string | null },
  overrides: Record<string, unknown> = {},
): Record<string, unknown> {
  const line = scope?.productionLineId ? LINES_BY_ID[scope.productionLineId] : null

  return summaryFixture({ production_line: line ?? null, ...overrides })
}

/** Periode yang PUNYA record tetapi tanpa satu slot terisi: 0,0%, BUKAN '—'. */
function emptySummaryFixture(): Record<string, unknown> {
  return summaryFixture({
    has_data: false,
    coverage: {
      filled_slots: 0,
      expected_slots: 1440,
      // Penyebutnya TERBENTUK, dan yang terukur memang nol slot.
      coverage_percent: 0,
      engine_room_unit_count: 2,
      slots_per_unit_per_day: 24,
      days_in_period: 30,
      days_counted: 30,
      period_running: false,
    },
    metrics: Object.fromEntries(
      METRIC_COLUMNS.map((column) => [
        column,
        { min: null, avg: null, max: null, reading_count: 0, group: METRIC_GROUPS[column] },
      ]),
    ),
    daily_energy_export: { series: [], has_data: false },
    status_distribution: Object.fromEntries(
      Object.entries(statusDistribution()).map(([column, distribution]) => [
        column,
        { ...distribution, counts: {}, not_recorded: 0, recorded_slots: 0 },
      ]),
    ),
    maintenance_remarks: [],
    findings: [],
    daily_trend: { electrical_sync_total_factory_load_kw: [] },
    by_unit: [],
    daily: [],
    daily_total: { reading_rows: 0 },
    total: { days_with_records: 0, reading_rows: 0 },
  })
}

/* ------------------------------------------------------------------ */
/* Auth store palsu                                                    */
/* ------------------------------------------------------------------ */

function asRole(
  role: 'operator' | 'supervisor' | 'mill_management' | 'admin',
  businessUnitId: string | null,
  userId = 'user-1',
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
/* Bantuan mount / interaksi                                           */
/* ------------------------------------------------------------------ */

async function mountView(): Promise<VueWrapper> {
  const wrapper = mount(LaporanEngineRoomView)
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

function exists(wrapper: VueWrapper, testid: string): boolean {
  return wrapper.find(`[data-testid="${testid}"]`).exists()
}

function cards(wrapper: VueWrapper) {
  return wrapper.findAll('[data-testid="metric-card"]')
}

/** Kartu satu kolom, dicari lewat data-metric — bukan lewat posisinya. */
function card(wrapper: VueWrapper, column: string) {
  return wrapper.get(`[data-metric="${column}"]`)
}

function block(wrapper: VueWrapper, testid: string) {
  return wrapper.get(`[data-testid="${testid}"]`)
}

/**
 * LABEL OPSI YANG SEDANG TERPILIH pada sebuah <select>.
 *
 * TIDAK membaca `element.value`: Vue mengikat `:value="null"` pada opsi
 * placeholder lewat properti internalnya dan TIDAK menuliskan atribut
 * `value`, sehingga `select.value` di jsdom jatuh ke TEKS opsi itu — asersi
 * `value === ''` karena itu memerah pada keadaan yang justru benar. Yang
 * dibaca di sini adalah opsi terpilih itu sendiri.
 */
function selectedOptionText(wrapper: VueWrapper, testid: string): string {
  const select = wrapper.get(`[data-testid="${testid}"]`).element as HTMLSelectElement

  return select.selectedOptions[0]?.textContent?.trim() ?? ''
}

const NO_LINE_SELECTED = 'Pilih Production Line'
const NO_PERIOD_SELECTED = 'Pilih Periode'

/**
 * SATU BARIS SEBARAN, DICARI LEWAT data-status-value — bukan lewat posisinya
 * dan bukan lewat pemindaian teks kontainer.
 */
function statusRow(wrapper: VueWrapper, distributionTestid: string, value: string) {
  return block(wrapper, distributionTestid).get(`[data-status-value="${value}"]`)
}

/**
 * LABEL nilai sebuah baris, DIBACA DARI SELNYA SENDIRI.
 *
 * 'Run' / 'Off' / 'Standby' panjangnya 2-7 karakter dan cocok di dalam kata
 * lain ('Off' ada di dalam 'Offline', 'Run' di dalam 'Running'), jadi asersi
 * substring tingkat kontainer hijau tanpa membuktikan apa pun. Pelajaran itu
 * sudah dibayar tiga kali di sisi web.
 */
function statusLabel(wrapper: VueWrapper, distributionTestid: string, value: string): string {
  return statusRow(wrapper, distributionTestid, value)
    .get('[data-testid="status-value-label"]')
    .text()
}

/** Sel ANGKA dan sel PORSI satu baris, dibaca dari dalam barisnya sendiri. */
function statusCells(
  wrapper: VueWrapper,
  distributionTestid: string,
  value: string,
): { count: string; share: string } {
  const cells = statusRow(wrapper, distributionTestid, value).findAll('td.num')

  return { count: cells[0].text(), share: cells[1].text() }
}

function notRecordedCells(
  wrapper: VueWrapper,
  distributionTestid: string,
): { count: string; share: string } {
  const cells = block(wrapper, distributionTestid)
    .get('[data-testid="status-not-recorded"]')
    .findAll('td.num')

  return { count: cells[0].text(), share: cells[1].text() }
}

beforeEach(() => {
  pushMock.mockReset()
  logoutMock.mockClear()
  useAuthStoreMock.mockReset()

  Object.keys(routeQuery).forEach((key) => delete routeQuery[key])

  repoMocks.fetchPeriods.mockReset()
  repoMocks.fetchSummary.mockReset()
  repoMocks.exportCsv.mockReset()
  repoMocks.saveCsvFile.mockReset()

  productionLineMocks.fetchProductionLinesForReport.mockReset()
  productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1])

  window.localStorage.clear()
  /**
   * LAYAR INI TIDAK MEMILIHKAN LINE — bahkan ketika mill hanya punya satu
   * (lihat catatan #4 pada docblock berkas). Jadi fixture jalur-sukses WAJIB
   * memberi ingatan perangkat lebih dulu; tanpa ini tidak ada satu angka pun
   * yang dirender, dan itu memang perilaku yang benar.
   */
  window.localStorage.setItem(REMEMBERED_LINE_KEY, 'pl-1')

  asRole('operator', 'bu-1')
  repoMocks.fetchPeriods.mockResolvedValue([PERIOD, PERIOD_CLOSED])
  repoMocks.fetchSummary.mockImplementation(
    (_periodId: string, scope?: { productionLineId?: string | null }) =>
      Promise.resolve(summaryForScope(scope)),
  )
})

afterEach(() => {
  vi.restoreAllMocks()
})

/* ================================================================== */
/* Skenario 1 + 2 — jalur sukses, kedua aktor, tanpa pemilih mill      */
/* ================================================================== */

describe('jalur sukses', () => {
  it.each(['operator', 'supervisor'] as const)(
    'skenario 1/2: %s — seluruh bagian laporan terender, TANPA pemilih mill',
    async (role) => {
      asRole(role, 'bu-1')

      const wrapper = await mountView()
      await selectPeriod(wrapper, 'per-1')

      // Tidak ada pemilih mill di layar ini, dan itu bukan pemangkasan:
      // kedua aktornya terikat satu mill, diselesaikan server dari akun.
      expect(exists(wrapper, 'mill-select')).toBe(false)
      expect(text(wrapper, 'mill-current')).toContain('Mill Sungai Rambutan')

      for (const testid of [
        'laporan-engine-room-mobile',
        'coverage',
        'coverage-slots',
        'coverage-percent',
        'coverage-denominator',
        'coverage-days',
        'coverage-twenty-seven-columns-note',
        'metric-blocks-note',
        'no-target-note',
        'no-downtime-note',
        'no-threshold-note',
        'status-distribution-diesel-gen-1',
        'status-distribution-diesel-gen-2',
        'standby-not-off-note',
        'status-distribution-note',
        'energy-export-card',
        'energy-export-badge',
        'daily-energy-export',
        'daily-energy-export-table',
        'daily-energy-export-note',
        'daily-trend-total-factory-load',
        'aggregation-difference-note',
        'maintenance-remarks',
        'findings',
        'by-unit',
        'daily-recap',
        'completeness',
        'export-button',
        'back-button',
      ]) {
        expect(exists(wrapper, testid), testid).toBe(true)
      }

      // KEENAM judul blok, dan kedua puluh dua kartu metrik di dalamnya.
      for (const metricBlock of METRIC_BLOCKS) {
        expect(text(wrapper, metricBlock.testid)).toContain(metricBlock.title)
      }
      expect(cards(wrapper)).toHaveLength(22)

      // DUA PULUH TIGA kartu seluruhnya: 22 dari loop `metrics` + 1 kartu
      // energi yang ditulis terpisah karena bentuknya memang berbeda.
      expect(exists(wrapper, 'energy-export-card')).toBe(true)
      expect(cards(wrapper).length + 1).toBe(23)

      // Layar BACA SAJA: tidak ada satu pun medan input di luar kedua
      // pemilih <select>.
      expect(wrapper.findAll('input')).toHaveLength(0)
      expect(wrapper.findAll('textarea')).toHaveLength(0)
      expect(wrapper.findAll('form')).toHaveLength(0)
    },
  )

  it('skenario 2: kedua peran menghasilkan susunan dan teks yang sama PERSIS', async () => {
    asRole('operator', 'bu-1')
    const asOperator = await mountView()
    await selectPeriod(asOperator, 'per-1')

    asRole('supervisor', 'bu-1')
    const asSupervisor = await mountView()
    await selectPeriod(asSupervisor, 'per-1')

    // Tidak satu pun medan, kolom, atau aksi yang hanya muncul pada salah
    // satu peran: ketiga rute data sudah menerima Operator sejak commit
    // pertama screen-158, jadi layar ini menambah PEMANGGIL — bukan varian
    // layar.
    expect(asOperator.text()).toBe(asSupervisor.text())
    expect(cards(asOperator)).toHaveLength(cards(asSupervisor).length)
    expect(exists(asOperator, 'mill-select')).toBe(false)
    expect(exists(asSupervisor, 'mill-select')).toBe(false)
  })

  it('daftar periode diminta TANPA satu argumen pun, dan cakupan hanya satu medan', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Mill diselesaikan server dari akun — tidak ada medan mill untuk dapat
    // salah terkirim.
    expect(repoMocks.fetchPeriods).toHaveBeenCalledWith()
    expect(repoMocks.fetchSummary.mock.calls[0][1]).toEqual({ productionLineId: 'pl-1' })
    expect(productionLineMocks.fetchProductionLinesForReport).toHaveBeenCalledWith(null)
    expect(JSON.stringify(repoMocks.fetchSummary.mock.calls)).not.toContain('business_unit')
    expect(JSON.stringify(repoMocks.fetchSummary.mock.calls)).not.toContain('businessUnitId')
  })

  it('nama mill dibaca dari blok business_unit, BUKAN dari respons /periods', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({ business_unit: { id: 'bu-1', name: 'Mill Dari Server' } }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'mill-current')).toContain('Mill Dari Server')
    expect(wrapper.text()).not.toContain('undefined')
    for (const period of [PERIOD, PERIOD_CLOSED]) {
      expect(period).not.toHaveProperty('business_unit_name')
    }
  })

  // skenario 38
  it('skenario 38: pemilih periode memuat TEPAT opsi dari server, berlabel jenis stasiun', async () => {
    const wrapper = await mountView()

    const options = wrapper.findAll('[data-testid="period-select"] option').map((o) => o.text())

    // Klien TIDAK menyaring ulang — server yang menyaringnya lewat baris
    // period_stations berjenis 'engine-room'. Dua opsi + satu placeholder.
    expect(options).toHaveLength(3)
    expect(options[0]).toContain('Pilih Periode')
    expect(options[1]).toContain('Engine Room — Periode Uji Engine Room')
    expect(options[2]).toContain('Engine Room — Periode Sebelumnya')
    // Nama periode stasiun lain tidak pernah masuk ke DOM: layar tidak
    // pernah menerimanya.
    expect(wrapper.text()).not.toContain('Sterilizer')
    expect(wrapper.text()).not.toContain('Boiler Room')
  })
})

/* ================================================================== */
/* Skenario 12 + 22 + 23 + 24 + 56-58 — kelengkapan pencatatan          */
/* ================================================================== */

describe('kelengkapan pencatatan', () => {
  // skenario 56 + 22
  it('skenario 56: blok kelengkapan berada DI ATAS keenam blok kartu pada urutan DOM', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const sections = wrapper.findAll('[data-testid]').map((el) => el.attributes('data-testid'))

    // Di layar sempit pembaca melihat lebih sedikit sekaligus, jadi apa yang
    // dilihat lebih dulu menentukan lebih banyak — dan kelengkapan inilah
    // yang menentukan apakah dua puluh tiga angka di bawahnya layak
    // dipercaya.
    expect(sections.indexOf('coverage')).toBeLessThan(
      sections.indexOf('metric-block-steam-turbine'),
    )
    expect(sections.indexOf('coverage')).toBeLessThan(sections.indexOf('metric-card'))
    expect(sections.indexOf('coverage')).toBeLessThan(sections.indexOf('energy-export-card'))
  })

  it('mencetak KETIGA angka pembentuk penyebutnya, bukan hanya persennya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'coverage-slots')).toContain('260 dari 1.440 slot')
    expect(text(wrapper, 'coverage-denominator')).toContain('2 unit')
    expect(text(wrapper, 'coverage-denominator')).toContain('30 hari')
    expect(text(wrapper, 'coverage-denominator')).toContain('24 slot')
    expect(text(wrapper, 'coverage-formula')).toContain('1.440')
    expect(text(wrapper, 'coverage-days')).toContain('30 dari 30 hari')
    // Record draft IKUT terhitung, dan hal itu dinyatakan.
    expect(text(wrapper, 'draft-note')).toContain('draft')
  })

  // skenario 31 (separuh cakupan)
  it('persen cakupan DIRENDER APA ADANYA dari server, bukan diturunkan di klien', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // 260/1440 = 18,1 dan server mengirim 88,8. Dilingkup KE ELEMENNYA:
    // halaman ini memuat porsi sebaran status di samping persen cakupan,
    // jadi asersi persen tingkat halaman tidak bermakna di sini.
    expect(text(wrapper, 'coverage-percent')).toBe('88,8%')
    expect(text(wrapper, 'coverage-percent')).not.toContain('18,1')
  })

  // skenario 12 + 57
  it('skenario 12/57: slot terisi MELAMPAUI penyebut setiap kartu, dengan keterangan 27 kolom', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        coverage: {
          // Tujuh slot terisi sementara penyebut terbesar di antara seluruh
          // metrik hanya DUA: sebuah slot yang hanya mengisi temuan tetap
          // terhitung pada kelengkapan tanpa menambah penyebut kartu mana
          // pun.
          filled_slots: 7,
          expected_slots: 240,
          coverage_percent: 2.9,
          engine_room_unit_count: 1,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 10,
          period_running: false,
        },
        metrics: Object.fromEntries(
          METRIC_COLUMNS.map((column, index) => [
            column,
            {
              min: 1,
              avg: 2,
              max: 3,
              reading_count: index === 0 ? 2 : 1,
              group: METRIC_GROUPS[column],
            },
          ]),
        ),
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'coverage-slots')).toContain('7 dari 240 slot')

    const note = text(wrapper, 'coverage-twenty-seven-columns-note')

    expect(note).toContain('27 kolom')
    expect(note).toContain('kedua kolom teks bebas')
    // Yang dilampaui adalah penyebut KARTU, bukan penyebut cakupan —
    // dinyatakan supaya pembaca berikutnya tidak "memperbaiki" selisih yang
    // memang benar.
    expect(note).toContain('MELEBIHI penyebut setiap kartu')

    // Dan penyebut tiap kartu BENAR-BENAR lebih kecil daripada 7.
    const denominators = wrapper
      .findAll('[data-testid="metric-denominator"]')
      .map((element) => element.text())

    expect(denominators).toHaveLength(22)
    for (const denominator of denominators) {
      expect(denominator).toMatch(/dari [12] pembacaan/)
    }
  })

  // skenario 23
  it('skenario 23: periode berjalan — keterangan berhenti di hari ini, hari tidak dihitung ulang', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        coverage: {
          filled_slots: 230,
          expected_slots: 288,
          coverage_percent: 79.9,
          engine_room_unit_count: 1,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 12,
          period_running: true,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'coverage-days')).toContain('12 dari 30 hari')
    expect(text(wrapper, 'coverage-running-note')).toContain('masih berjalan')
    expect(exists(wrapper, 'coverage-not-started-note')).toBe(false)
    // Hari yang belum terjadi tidak ikut menjadi penyebut, jadi persennya
    // tidak diturunkan olehnya — dan klien tidak menghitung ulang
    // days_counted.
    expect(text(wrapper, 'coverage-percent')).toBe('79,9%')
  })

  // skenario 24
  it('skenario 24: expected_slots 0 dirender tanda hubung, dan 0,0% TIDAK muncul', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          // Server mengirim 0.0 — BUKAN null — pada keadaan ini, jadi
          // keputusannya diambil dari expected_slots === 0. Memutuskannya
          // dari null-nya coverage_percent akan menampilkan '0,0%' justru di
          // tempat '—' seharusnya.
          coverage_percent: 0,
          engine_room_unit_count: 0,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 0,
          period_running: true,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'coverage-percent')).toBe('—')
    expect(text(wrapper, 'coverage-percent')).not.toContain('0,0%')
    expect(text(wrapper, 'coverage-no-expected-note')).toContain('Belum ada slot yang diharapkan')
    // URUTAN CABANG: days_counted === 0 DIPERIKSA SEBELUM period_running —
    // server menandai periode yang BELUM MULAI sebagai masih berjalan juga,
    // jadi memeriksa period_running lebih dulu membuat keterangan "belum
    // mulai" tidak pernah terender.
    expect(text(wrapper, 'coverage-not-started-note')).toContain('belum mulai')
    expect(exists(wrapper, 'coverage-running-note')).toBe(false)
  })

  it('periode ber-record tanpa slot terisi menghasilkan 0,0% — BUKAN tanda hubung', async () => {
    repoMocks.fetchSummary.mockResolvedValue(emptySummaryFixture())

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Penyebutnya TERBENTUK (1.440), dan yang terukur memang nol slot.
    expect(text(wrapper, 'coverage-percent')).toBe('0,0%')
    expect(exists(wrapper, 'coverage-no-expected-note')).toBe(false)
  })

  // skenario 22
  it('skenario 22: cakupan sangat rendah tetap dirender, dan kartu tetap membawa penyebut kecilnya', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        coverage: {
          filled_slots: 12,
          expected_slots: 1440,
          coverage_percent: 0.8,
          engine_room_unit_count: 2,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 30,
          period_running: false,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'coverage-percent')).toBe('0,8%')
    // Angka metriknya TETAP dirender, dengan penyebutnya masing-masing: yang
    // dinyatakan adalah kelengkapannya, bukan angka yang disembunyikan.
    expect(cards(wrapper)).toHaveLength(22)
    expect(card(wrapper, 'diesel_gen_1_load_kw').text()).toContain('200 pembacaan')
  })
})

/* ================================================================== */
/* Skenario 31 — PONSEL TIDAK MENGHITUNG APA PUN                       */
/* ================================================================== */

describe('nol aritmetika atas angka laporan', () => {
  it('skenario 31: setiap angka yang dirender sama persis dengan payload', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // avg DI LUAR rentang min..max — mustahil dari perhitungan apa pun. Layar
    // yang menurunkan avg dari (min+max)/2 menggambar 15.
    const gen2 = card(wrapper, 'diesel_gen_2_load_kw')
    const gen2Values = gen2.findAll('.metric-row-item b').map((element) => element.text())

    expect(gen2Values).toEqual(['10', '40', '20'])
    expect(gen2.get('[data-testid="metric-avg"]').text()).toBe('40')

    // Genset 1 berat, Genset 2 hampir menganggur — dan keduanya tetap dua
    // angka.
    expect(card(wrapper, 'diesel_gen_1_load_kw').get('[data-testid="metric-avg"]').text()).toBe(
      '800',
    )

    // Pembulatan desimal mengikuti tabel meta yang disalin dari blade
    // screen-158 — bukan diturunkan dari nilainya.
    expect(
      card(wrapper, 'electrical_sync_system_frequency_hz')
        .get('[data-testid="metric-avg"]')
        .text(),
    ).toBe('49,82')
    expect(card(wrapper, 'steam_turbine_rpm').get('[data-testid="metric-avg"]').text()).toBe(
      '5.100',
    )

    // Total harian DARI SERVER, bukan dijumlahkan dari baris harian: 3 + 4 =
    // 7, dan server mengirim 99.
    expect(text(wrapper, 'daily-row-total')).toContain('99')
    expect(text(wrapper, 'total-reading-rows')).toBe('260')
    expect(text(wrapper, 'days-with-records')).toBe('2')
  })

  it("skenario 31: satuan 'C' dari server DIRENDER 'C', tidak pernah '°C'", async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        metrics: {
          ...metrics(),
          // Server MENERBITKAN satuannya (biasanya tidak), dan nilainya 'C'.
          // Nilai server MENANG atas tabel META view — dan tidak ada peta
          // satuan di antara keduanya.
          steam_turbine_inlet_temp_c: metric('steam_turbine_inlet_temp_c', 280, 305, 330, {
            unit: 'C',
          }),
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const label = card(wrapper, 'steam_turbine_inlet_temp_c').get('.metric-label').text()

    // DILINGKUP KE ELEMENNYA: '°C' yang sah tetap muncul di judul kolom kedua
    // tabel rekap (label STATIS yang ditulis tangan di METRIC_META, disalin
    // dari $metrikMeta pada blade screen-158), jadi asersi tingkat halaman
    // tidak bermakna di sini.
    expect(label).toContain('· C')
    expect(label).not.toContain('°C')
  })

  it('label dari server MENANG atas tabel META view', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        metrics: {
          ...metrics(),
          fuel_tank_level: metric('fuel_tank_level', 18, 54, 92, {
            label: 'Level Tangki dari Server',
            unit: '%',
          }),
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const label = card(wrapper, 'fuel_tank_level').get('.metric-label').text()

    expect(label).toContain('Level Tangki dari Server')
    expect(label).toContain('%')
    expect(label).not.toContain('Level Tangki Bahan Bakar')
  })
})

/* ================================================================== */
/* Skenario 32 + 40 + 41 + 42 + 43 + 44 — kartu dan keenam blok         */
/* ================================================================== */

describe('dua puluh tiga kartu dalam enam blok', () => {
  // skenario 40
  it('skenario 40: 22 kartu dari loop metrics PLUS satu kartu energi — 23 seluruhnya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(cards(wrapper)).toHaveLength(22)
    expect(exists(wrapper, 'energy-export-card')).toBe(true)

    // Kartu energi ditulis TERPISAH karena bentuknya memang berbeda (nilai
    // terakhir + slot asalnya, bukan terendah/rata-rata/tertinggi), dan ia
    // dirender DI DALAM blok Penunjang.
    expect(
      wrapper
        .get('[data-testid="energy-export-card"]')
        .element.closest('[data-testid="metric-block-auxiliary"]'),
    ).not.toBeNull()
    // Ia BUKAN salah satu dari 22 kartu metrik: testid-nya sendiri.
    expect(
      wrapper.get('[data-testid="energy-export-card"]').attributes('data-testid'),
    ).toBe('energy-export-card')
  })

  // skenario 41 — INI yang membuktikan blok dibangun dari RESPONS
  it('skenario 41: keenam blok memuat kolom menurut field group DARI RESPONS', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    for (const metricBlock of METRIC_BLOCKS) {
      const blockCards = block(wrapper, metricBlock.testid).findAll('[data-testid="metric-card"]')

      expect(blockCards, metricBlock.testid).toHaveLength(metricBlock.cards)
      expect(
        blockCards.map((element) => element.attributes('data-metric')),
        metricBlock.testid,
      ).toEqual(METRIC_COLUMNS.filter((column) => METRIC_GROUPS[column] === metricBlock.group))
    }
  })

  it('skenario 41: kolom yang DIPINDAHKAN group-nya oleh server berpindah blok, tanpa menyunting view', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        metrics: {
          ...metrics(),
          // Server memindahkan fuel_tank_level dari 'auxiliary' ke
          // 'compressor'. View yang memegang daftar nama kolom yang dipaku
          // akan tetap menggambarnya di Penunjang, dan test ini gagal.
          fuel_tank_level: metric('fuel_tank_level', 18, 54, 92, { group: 'compressor' }),
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(
      block(wrapper, 'metric-block-compressor')
        .findAll('[data-testid="metric-card"]')
        .map((element) => element.attributes('data-metric')),
    ).toEqual(['air_compressor_1_pressure_bar', 'compressor_2_pressure_bar', 'fuel_tank_level'])
    expect(
      block(wrapper, 'metric-block-auxiliary')
        .findAll('[data-testid="metric-card"]')
        .map((element) => element.attributes('data-metric')),
    ).toEqual(['battery_charger_ups_voltage_v'])

    // Jumlah kartu seluruhnya tidak berubah — hanya tempatnya.
    expect(cards(wrapper)).toHaveLength(22)
  })

  it('skenario 41: kolom BARU dari migrasi masuk ke blok yang benar, dengan nama kolomnya sebagai label', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        metrics: {
          ...metrics(),
          // Kolom yang belum punya entri di METRIC_META: ia tetap dirender
          // memakai NAMA KOLOMNYA, bukan hilang dari layar — kartu yang
          // hilang terbaca sebagai "tidak ada metrik ini" padahal yang benar
          // adalah "tidak ada yang mengukurnya".
          steam_turbine_gland_seal_pressure_bar: {
            min: 0.8,
            avg: 1.2,
            max: 1.6,
            reading_count: 77,
            group: 'steam_turbine',
          },
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(cards(wrapper)).toHaveLength(23)
    const newCard = card(wrapper, 'steam_turbine_gland_seal_pressure_bar')

    expect(
      newCard.element.closest('[data-testid="metric-block-steam-turbine"]'),
    ).not.toBeNull()
    expect(newCard.get('.metric-label').text()).toContain('steam_turbine_gland_seal_pressure_bar')
    expect(newCard.get('[data-testid="metric-denominator"]').text()).toContain('77 pembacaan')
  })

  it('kolom ber-group DI LUAR keenam blok tidak dirender, dan jumlah kartu tidak bergeser', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        metrics: {
          ...metrics(),
          future_sensor_reading: {
            min: 1,
            avg: 2,
            max: 3,
            reading_count: 4,
            group: 'future_block',
          },
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // KONSEKUENSI YANG DISENGAJA: judul, urutan, dan keterangan keenam blok
    // ditetapkan view; KOLOM tiap blok datang dari respons. Sebuah group yang
    // belum dikenal karena itu tidak punya blok, dan kolomnya tidak dirender
    // — menaruhnya di blok yang salah lebih buruk daripada kolom yang belum
    // punya blok, dan dicatat di sini supaya ketiadaannya tidak dibaca
    // sebagai cacat.
    expect(cards(wrapper)).toHaveLength(22)
    expect(wrapper.find('[data-metric="future_sensor_reading"]').exists()).toBe(false)
  })

  // skenario 33 + 42
  it('skenario 33/42: keenam blok terbuka TANPA satu kontrol lipat pun', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    for (const metricBlock of METRIC_BLOCKS) {
      const section = block(wrapper, metricBlock.testid)

      // Melipatnya memperpendek gulir tetapi menyembunyikan penyebut tiap
      // kartu, dan penyebut itulah yang paling perlu dilihat di layar ini.
      expect(
        section.find('[data-testid="collapsible-section-toggle"]').exists(),
        metricBlock.testid,
      ).toBe(false)
      // Dan kartunya sudah terender tanpa satu interaksi pun.
      expect(section.findAll('[data-testid="metric-card"]').length, metricBlock.testid).toBe(
        metricBlock.cards,
      )
    }

    // Yang DAPAT ditutup hanyalah REKAP HARIAN — bukan keenam blok kartu.
    expect(
      block(wrapper, 'daily-recap').find('[data-testid="collapsible-section-toggle"]').exists(),
    ).toBe(true)
    expect(
      wrapper.findAll('[data-testid="collapsible-section-toggle"]'),
    ).toHaveLength(1)
  })

  // skenario 32 + 43
  it('skenario 32/43: setiap kartu mencetak PENYEBUTNYA SENDIRI, dan kedua puluh dua berbeda', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    for (const column of METRIC_COLUMNS) {
      expect(
        card(wrapper, column).get('[data-testid="metric-denominator"]').text(),
        column,
      ).toContain(`dari ${new Intl.NumberFormat('id-ID').format(READING_COUNTS[column])} pembacaan`)
    }

    // Himpunan penyebut yang DIRENDER memuat dua puluh dua nilai berbeda:
    // penyebut identik pada dua puluh dua kartu adalah cacat yang dicari, dan
    // tidak satu blok pun mewarisi penyebut blok lain.
    const rendered = new Set(
      METRIC_COLUMNS.map((column) =>
        card(wrapper, column).get('[data-testid="metric-denominator"]').text(),
      ),
    )

    expect(rendered.size).toBe(22)
  })

  // skenario 44 + 9
  it('skenario 9/44: kartu tanpa nilai menyatakan tidak tersedia dengan penyebut 0, bukan angka 0', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const powerFactor = card(wrapper, 'electrical_sync_power_factor')
    const values = powerFactor.findAll('.metric-row-item b').map((element) => element.text())

    // Nol berarti "terukur dan hasilnya nol"; tidak tersedia berarti "tidak
    // ada yang diukur" — dua fakta yang berbeda, dan yang satu menyesatkan.
    expect(values).toEqual(['tidak tersedia', 'tidak tersedia', 'tidak tersedia'])
    expect(values).not.toContain('0,000')
    expect(powerFactor.get('[data-testid="metric-denominator"]').text()).toContain(
      'dari 0 pembacaan',
    )

    // Dan kartunya TETAP berada di dalam blok Kelistrikan Tersinkron.
    expect(
      powerFactor.element.closest('[data-testid="metric-block-electrical-sync"]'),
    ).not.toBeNull()

    // Kedua puluh satu kartu lain menampilkan angka dengan penyebutnya
    // masing-masing.
    expect(
      METRIC_COLUMNS.filter(
        (column) =>
          card(wrapper, column).get('[data-testid="metric-avg"]').text() !== 'tidak tersedia',
      ),
    ).toHaveLength(21)
  })

  // skenario 8
  it('skenario 8: periode tanpa data — 23 kartu tetap dirender, blok kelengkapan tetap ada', async () => {
    repoMocks.fetchSummary.mockResolvedValue(emptySummaryFixture())

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'empty-period')).toBe(true)
    // Blok CAKUPAN tetap digambar — justru itu yang menjelaskan
    // kekosongannya.
    expect(exists(wrapper, 'coverage')).toBe(true)
    expect(cards(wrapper)).toHaveLength(22)
    expect(exists(wrapper, 'energy-export-card')).toBe(true)
    expect(exists(wrapper, 'energy-export-empty')).toBe(true)

    for (const column of METRIC_COLUMNS) {
      expect(card(wrapper, column).get('[data-testid="metric-avg"]').text(), column).toBe(
        'tidak tersedia',
      )
      expect(
        card(wrapper, column).get('[data-testid="metric-denominator"]').text(),
        column,
      ).toContain('dari 0 pembacaan')
    }

    // Tabel kosong akan terbaca sebagai hasil pengukuran bernilai nol dan
    // grafik kosong sebagai garis datar, jadi keduanya TIDAK digambar.
    expect(exists(wrapper, 'by-unit')).toBe(false)
    expect(exists(wrapper, 'daily-recap')).toBe(false)
    expect(exists(wrapper, 'daily-trend-total-factory-load')).toBe(false)
    // Sementara KEDUA SEBARAN tetap digambar: parameternya ADA dan tidak
    // terukur.
    expect(exists(wrapper, 'status-distribution-diesel-gen-1')).toBe(true)
    expect(exists(wrapper, 'status-distribution-diesel-gen-2')).toBe(true)
  })

  // skenario 10
  it('skenario 10: satu genset tidak pernah menyala — empat kartu penyebut nol, tanpa penanda galat', async () => {
    const gen2Columns = METRIC_COLUMNS.filter((column) => METRIC_GROUPS[column] === 'diesel_gen_2')

    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        metrics: {
          ...metrics(),
          ...Object.fromEntries(
            gen2Columns.map((column) => [
              column,
              { min: null, avg: null, max: null, reading_count: 0, group: 'diesel_gen_2' },
            ]),
          ),
        },
        status_distribution: {
          diesel_gen_1_status: statusDistribution().diesel_gen_1_status,
          diesel_gen_2_status: {
            known_values: ['run', 'standby', 'off'],
            // Seluruh slot menempatkan Genset 2 pada Off; Run TETAP tercetak
            // bernilai 0.
            counts: { run: 0, standby: 0, off: 20 },
            not_recorded: 0,
            recorded_slots: 20,
          },
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    for (const column of gen2Columns) {
      expect(card(wrapper, column).get('[data-testid="metric-avg"]').text(), column).toBe(
        'tidak tersedia',
      )
      expect(
        card(wrapper, column).get('[data-testid="metric-denominator"]').text(),
        column,
      ).toContain('dari 0 pembacaan')
    }

    // Genset yang tidak pernah menyala adalah keadaan operasional yang SAH —
    // bukan galat. Nol elemen bertanda galat/peringatan di blok itu.
    const gen2Block = block(wrapper, 'metric-block-diesel-gen-2')
    const classNames = gen2Block
      .findAll('[class]')
      .flatMap((element) => (element.attributes('class') ?? '').split(/\s+/))

    expect(
      classNames.filter((name) => /error|danger|warning|alert|invalid|critical/.test(name)),
    ).toEqual([])

    // Dan sebarannya: Off 20, Run 0, Standby 0 — dibaca dari sel di dalam
    // barisnya masing-masing.
    expect(statusCells(wrapper, 'status-distribution-diesel-gen-2', 'off').count).toBe('20')
    expect(statusCells(wrapper, 'status-distribution-diesel-gen-2', 'run').count).toBe('0')
    expect(statusCells(wrapper, 'status-distribution-diesel-gen-2', 'standby').count).toBe('0')
  })
})

/* ================================================================== */
/* Skenario 11 + 45 + 46 + 47 — KETERANGAN GENSET, DUA KALI             */
/* ================================================================== */

describe('keterangan kesejajaran genset', () => {
  // skenario 11 + 45 — ASERSI MENGHITUNG KEMUNCULANNYA
  it('skenario 11/45: keterangan genset dirender TEPAT DUA KALI, sekali di DALAM tiap blok', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const notes = wrapper.findAll('[data-testid="genset-parallel-note"]')

    // DUA, TEPAT. Mengasersi "ada" saja akan lolos pada implementasi yang
    // menaruhnya SEKALI di atas halaman — dan itu persis cacat yang
    // dihindari, karena di ponsel keterangan di atas halaman sudah tergulir
    // jauh ketika blok yang diterangkannya akhirnya terlihat.
    expect(notes).toHaveLength(2)

    // Dan masing-masing berada DI DALAM blok genset-nya sendiri — bukan
    // keduanya di dalam satu blok, dan bukan di luar keduanya.
    expect(notes[0].element.closest('[data-testid="metric-block-diesel-gen-1"]')).not.toBeNull()
    expect(notes[0].element.closest('[data-testid="metric-block-diesel-gen-2"]')).toBeNull()
    expect(notes[1].element.closest('[data-testid="metric-block-diesel-gen-2"]')).not.toBeNull()
    expect(notes[1].element.closest('[data-testid="metric-block-diesel-gen-1"]')).toBeNull()

    // Keempat blok lain TIDAK membawanya.
    for (const testid of [
      'metric-block-steam-turbine',
      'metric-block-electrical-sync',
      'metric-block-compressor',
      'metric-block-auxiliary',
    ]) {
      expect(
        block(wrapper, testid).findAll('[data-testid="genset-parallel-note"]').length,
        testid,
      ).toBe(0)
    }
  })

  // skenario 46
  it('skenario 46: keterangannya menyebut kesejajaran FISIK dan MENYANGKAL standar bersama', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const note = wrapper.findAll('[data-testid="genset-parallel-note"]')[0].text()

    expect(note).toContain('dua mesin fisik yang berbeda')
    expect(note).toContain('secara fisik')
    // Stasiun ini TIDAK punya tabel master target sama sekali — berbeda dari
    // pasangan ripple mill / kernel silo di Kernel Plant, yang memang berbagi
    // satu baris master dan punya `shares_standard_with` pada responsnya.
    expect(note).toContain('bukan standar bersama')
    expect(note).toContain('tidak punya tabel master target')
    expect(note).not.toContain('shares_standard_with')
    // Dan tidak ada satu elemen pun yang MENGKLAIM standar bersama.
    expect(wrapper.find('[data-testid="shares-standard-note"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="shared-target-note"]').exists()).toBe(false)
  })

  // skenario 11 + 47
  it('skenario 11/47: kedelapan kartu genset berdiri sendiri — nol gabungan/selisih/badge', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // 800 lawan 40, dan keduanya tetap dua angka: ketidakseimbangan beban
    // adalah TEMUAN, bukan galat, dan rata-rata gabungan justru
    // menyembunyikannya sebagai "normal".
    expect(card(wrapper, 'diesel_gen_1_load_kw').get('[data-testid="metric-avg"]').text()).toBe(
      '800',
    )
    expect(card(wrapper, 'diesel_gen_2_load_kw').get('[data-testid="metric-avg"]').text()).toBe(
      '40',
    )

    // Kedelapan kartu, empat di tiap blok.
    expect(block(wrapper, 'metric-block-diesel-gen-1').findAll('[data-testid="metric-card"]'))
      .toHaveLength(4)
    expect(block(wrapper, 'metric-block-diesel-gen-2').findAll('[data-testid="metric-card"]'))
      .toHaveLength(4)

    // NOL elemen bertanda gabungan/selisih/imbalance/deviation — diasersi
    // atas TESTID dan atas KELAS, bukan atas frasa: keterangan genset yang
    // membuktikan ketiadaannya sendiri memuat kata "selisih".
    for (const testid of [
      'genset-combined-card',
      'genset-difference-card',
      'genset-imbalance-badge',
      'genset-deviation-badge',
    ]) {
      expect(exists(wrapper, testid), testid).toBe(false)
    }

    const classNames = wrapper
      .findAll('[class]')
      .flatMap((element) => (element.attributes('class') ?? '').split(/\s+/))

    expect(
      classNames.filter((name) => /combined|imbalance|deviation|difference/.test(name)),
    ).toEqual([])

    // Dan tidak ada kartu ke-23 bernama gabungan di antara kedua puluh dua.
    expect(
      cards(wrapper)
        .map((element) => element.attributes('data-metric'))
        .filter((column) => /combined|difference|imbalance/.test(column ?? '')),
    ).toEqual([])
  })
})

/* ================================================================== */
/* Skenario 13 + 14 + 15 + 34 + 48-52 — DUA SEBARAN STATUS              */
/* ================================================================== */

describe('sebaran status', () => {
  // skenario 52
  it('skenario 52: dua sebaran sebagai dua bagian TERPISAH, masing-masing berjudul', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'status-distribution-diesel-gen-1')).toContain('Sebaran Status Genset 1')
    expect(text(wrapper, 'status-distribution-diesel-gen-2')).toContain('Sebaran Status Genset 2')

    // Tidak ada sebaran gabungan dua genset. Penyaringan '-table' perlu:
    // tabel di dalam tiap bagian membawa testid ber-awalan yang SAMA, jadi
    // pemilih ber-awalan saja menghitung empat elemen untuk dua bagian.
    const sections = wrapper
      .findAll('[data-testid^="status-distribution-diesel-gen-"]')
      .filter((element) => !(element.attributes('data-testid') ?? '').endsWith('-table'))

    expect(sections).toHaveLength(2)
    expect(exists(wrapper, 'status-distribution-diesel-gen-combined')).toBe(false)
  })

  // skenario 14 + 48
  it('skenario 14/48: iterasinya atas known_values — Standby yang ABSEN dari counts tetap tercetak 0', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const rows = block(wrapper, 'status-distribution-diesel-gen-1').findAll(
      '[data-testid="status-row"]',
    )

    // TIGA baris nilai — bukan dua. Fixture-nya SENGAJA tidak memuat
    // 'standby' pada `counts`: view yang mengiterasi kunci counts
    // menghilangkan barisnya, dan baris yang hilang terbaca sebagai "nilai
    // ini tidak ada" padahal yang benar adalah "nilai ini tidak pernah
    // terjadi".
    expect(rows).toHaveLength(3)
    expect(rows.map((row) => row.attributes('data-status-value'))).toEqual([
      'run',
      'standby',
      'off',
    ])

    // Dibaca DARI SEL DI DALAM BARISNYA SENDIRI.
    expect(statusCells(wrapper, 'status-distribution-diesel-gen-1', 'standby').count).toBe('0')
    expect(statusCells(wrapper, 'status-distribution-diesel-gen-1', 'run').count).toBe('10')
    expect(statusCells(wrapper, 'status-distribution-diesel-gen-1', 'off').count).toBe('2')
  })

  it('ketiga nilai dikenal tercetak walau SELURUHNYA bernilai 0', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        status_distribution: {
          diesel_gen_1_status: {
            known_values: ['run', 'standby', 'off'],
            // KOSONG: tidak satu slot pun mencatat kolom ini.
            counts: {},
            not_recorded: 18,
            recorded_slots: 18,
          },
          diesel_gen_2_status: statusDistribution().diesel_gen_2_status,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // skenario 13: ketiga baris nilai TETAP dirender, masing-masing 0, dan
    // baris tidak tercatat memuat 18.
    for (const value of ['run', 'standby', 'off']) {
      expect(statusCells(wrapper, 'status-distribution-diesel-gen-1', value).count, value).toBe('0')
    }
    expect(
      block(wrapper, 'status-distribution-diesel-gen-1').findAll('[data-testid="status-row"]'),
    ).toHaveLength(3)
    expect(notRecordedCells(wrapper, 'status-distribution-diesel-gen-1').count).toBe('18')
  })

  // skenario 49
  it('skenario 49: baris tidak tercatat TERPISAH, dan tujuh slot itu tidak dilipat ke mana pun', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(notRecordedCells(wrapper, 'status-distribution-diesel-gen-1').count).toBe('7')
    expect(
      block(wrapper, 'status-distribution-diesel-gen-1')
        .get('[data-testid="status-not-recorded"]')
        .text(),
    ).toContain('Tidak tercatat')

    // "Tidak kami tulis" dan "memang tidak jalan" adalah dua kenyataan yang
    // berbeda akibatnya: 2, BUKAN 9.
    expect(statusCells(wrapper, 'status-distribution-diesel-gen-1', 'off').count).toBe('2')
    expect(statusCells(wrapper, 'status-distribution-diesel-gen-1', 'run').count).toBe('10')
    // Dan baris tidak tercatat BUKAN salah satu dari ketiga baris nilai.
    expect(
      block(wrapper, 'status-distribution-diesel-gen-1')
        .get('[data-testid="status-not-recorded"]')
        .attributes('data-status-value'),
    ).toBeUndefined()
  })

  // skenario 34 + 50
  it("skenario 34/50: 'standby' 6 dan 'off' 4 sebagai dua baris terpisah — nol baris bernilai 10", async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const standby = statusCells(wrapper, 'status-distribution-diesel-gen-2', 'standby')
    const off = statusCells(wrapper, 'status-distribution-diesel-gen-2', 'off')

    // Genset siaga siap menanggung beban; genset mati tidak. Melipat Standby
    // ke Off akan melaporkan pembangkit mati yang tidak pernah terjadi.
    expect(standby.count).toBe('6')
    expect(off.count).toBe('4')

    // Tidak ada baris GABUNGAN bernilai 10 di antara ketiga baris nilai.
    const counts = block(wrapper, 'status-distribution-diesel-gen-2')
      .findAll('[data-testid="status-row"]')
      .map((row) => row.findAll('td.num')[0].text())

    expect(counts).toEqual(['0', '6', '4'])
    expect(counts).not.toContain('10')

    // Dan pernyataannya sendiri dirender sebagai bagiannya sendiri.
    expect(text(wrapper, 'standby-not-off-note')).toContain('Standby')
    expect(text(wrapper, 'standby-not-off-note')).toContain('Off')
    expect(text(wrapper, 'standby-not-off-note')).toContain('siap menanggung beban')
  })

  // skenario 17 + 48 (label) — DIBACA DARI SEL, bukan dari kontainer
  it('label Run / Standby / Off DIPAKAI APA ADANYA, tidak diterjemahkan', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // SEL-nya sendiri, dan dengan toBe — bukan toContain pada kontainer:
    // 'Run' dan 'Off' panjangnya tiga karakter dan cocok di dalam kata lain,
    // sehingga asersi substring tingkat kontainer hijau tanpa membuktikan apa
    // pun.
    for (const testid of ['status-distribution-diesel-gen-1', 'status-distribution-diesel-gen-2']) {
      expect(statusLabel(wrapper, testid, 'run'), testid).toBe('Run')
      expect(statusLabel(wrapper, testid, 'standby'), testid).toBe('Standby')
      expect(statusLabel(wrapper, testid, 'off'), testid).toBe('Off')
    }

    // Padanan Indonesia untuk kolom semacam ini diselaraskan kembali ke label
    // server pada 2026-10-14 atas keputusan pemilik produk: satu nilai hanya
    // boleh punya satu label di seluruh produk.
    expect(wrapper.text()).not.toContain('Siaga')
    expect(statusLabel(wrapper, 'status-distribution-diesel-gen-1', 'run')).not.toBe('Jalan')
    expect(statusLabel(wrapper, 'status-distribution-diesel-gen-1', 'off')).not.toBe('Mati')
  })

  // skenario 15
  it('skenario 15: nilai tak dikenal dirender MENTAH lewat jalur mundur map[v] ?? v', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        status_distribution: {
          diesel_gen_1_status: {
            // Server menerbitkan nilai di luar himpunan dikenal DI BELAKANG
            // daftar — nilai baru dari migrasi kelak harus TERLIHAT, bukan
            // hilang.
            known_values: ['run', 'standby', 'off', 'turbo'],
            counts: { run: 10, standby: 0, off: 2, turbo: 1 },
            not_recorded: 6,
            recorded_slots: 19,
          },
          diesel_gen_2_status: statusDistribution().diesel_gen_2_status,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(
      block(wrapper, 'status-distribution-diesel-gen-1').findAll('[data-testid="status-row"]'),
    ).toHaveLength(4)
    // Nilai mentah yang tercetak apa adanya terlihat janggal — dan terlihat
    // janggal itulah tujuannya; baris yang hilang tidak terlihat sama sekali.
    expect(statusLabel(wrapper, 'status-distribution-diesel-gen-1', 'turbo')).toBe('turbo')
    expect(statusLabel(wrapper, 'status-distribution-diesel-gen-1', 'turbo')).not.toBe('')
    expect(statusLabel(wrapper, 'status-distribution-diesel-gen-1', 'turbo')).not.toContain(
      'undefined',
    )
    expect(statusCells(wrapper, 'status-distribution-diesel-gen-1', 'turbo').count).toBe('1')
  })

  // skenario 34 + 51 — PENYEBUT PORSI
  it('skenario 51: porsi memakai recorded_slots dari respons, BUKAN expected_slots', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        coverage: {
          filled_slots: 20,
          // 240 — penyebut yang SALAH, dan ia ada di fixture justru supaya
          // layar yang memakainya dapat dijatuhkan.
          expected_slots: 240,
          coverage_percent: 8.3,
          engine_room_unit_count: 1,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 10,
          period_running: false,
        },
        status_distribution: {
          diesel_gen_1_status: {
            known_values: ['run', 'standby', 'off'],
            counts: { run: 15 },
            not_recorded: 5,
            recorded_slots: 20,
          },
          diesel_gen_2_status: statusDistribution().diesel_gen_2_status,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // 15/20 = 75,0%. Penyebut expected_slots akan menjawab 6,3% (15/240) —
    // dan ia mencampur slot tak tersentuh ke dalam porsi sebuah nilai yang
    // justru tercatat.
    const run = statusCells(wrapper, 'status-distribution-diesel-gen-1', 'run')

    expect(run.count).toBe('15')
    expect(run.share).toBe('75,0%')
    expect(run.share).not.toBe('6,3%')

    expect(statusCells(wrapper, 'status-distribution-diesel-gen-1', 'standby').share).toBe('0,0%')
    expect(notRecordedCells(wrapper, 'status-distribution-diesel-gen-1').share).toBe('25,0%')
    // Dasar penyebutnya DINYATAKAN pada bagiannya sendiri.
    expect(text(wrapper, 'status-distribution-diesel-gen-1')).toContain('20 slot tercatat')
  })

  it('porsi dirender digit demi digit sama dengan helper $porsi pada blade screen-158', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // number_format(100 * count / recordedSlots, 1) dengan pemisah desimal
    // Indonesia: 10/19 = 52,6%, 0/19 = 0,0%, 2/19 = 10,5%, 7/19 = 36,8%.
    expect(statusCells(wrapper, 'status-distribution-diesel-gen-1', 'run').share).toBe('52,6%')
    expect(statusCells(wrapper, 'status-distribution-diesel-gen-1', 'standby').share).toBe('0,0%')
    expect(statusCells(wrapper, 'status-distribution-diesel-gen-1', 'off').share).toBe('10,5%')
    expect(notRecordedCells(wrapper, 'status-distribution-diesel-gen-1').share).toBe('36,8%')

    // Genset 2 dengan penyebut 20: 0,0% / 30,0% / 20,0% / 50,0%.
    expect(statusCells(wrapper, 'status-distribution-diesel-gen-2', 'run').share).toBe('0,0%')
    expect(statusCells(wrapper, 'status-distribution-diesel-gen-2', 'standby').share).toBe('30,0%')
    expect(statusCells(wrapper, 'status-distribution-diesel-gen-2', 'off').share).toBe('20,0%')
    expect(notRecordedCells(wrapper, 'status-distribution-diesel-gen-2').share).toBe('50,0%')

    // Baris total slot tercatat: 100,0% bila penyebutnya ada.
    expect(text(wrapper, 'status-recorded-total')).toContain('100,0%')
  })

  it('penyebut NOL menghasilkan tanda hubung, bukan 0,0%', async () => {
    repoMocks.fetchSummary.mockResolvedValue(emptySummaryFixture())

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    for (const value of ['run', 'standby', 'off']) {
      expect(statusCells(wrapper, 'status-distribution-diesel-gen-1', value).share, value).toBe('—')
    }
    expect(notRecordedCells(wrapper, 'status-distribution-diesel-gen-1').share).toBe('—')
    expect(
      block(wrapper, 'status-distribution-diesel-gen-1')
        .get('[data-testid="status-recorded-total"]')
        .text(),
    ).toContain('—')
  })

  it('kedua kolom status TIDAK muncul sebagai kartu metrik maupun pada tren', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Terendah, rata-rata, dan tertinggi tidak punya arti untuk kolom
    // kategoris.
    for (const column of ['diesel_gen_1_status', 'diesel_gen_2_status']) {
      expect(wrapper.find(`[data-metric="${column}"]`).exists(), column).toBe(false)
    }
    expect(exists(wrapper, 'daily-trend-diesel-gen-1-status')).toBe(false)
    expect(exists(wrapper, 'daily-trend-diesel-gen-2-status')).toBe(false)
  })
})

/* ================================================================== */
/* Skenario 16 + 17 + 53 + 54 + 55 — SERI EKSPOR ENERGI                 */
/* ================================================================== */

describe('seri harian ekspor energi', () => {
  // skenario 53
  it('skenario 53: merender nilai TERAKHIR per tanggal beserta slot asalnya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const rows = wrapper.findAll('[data-testid="energy-export-row"]')

    expect(rows).toHaveLength(3)
    expect(rows.map((row) => row.get('[data-testid="energy-export-last"]').text())).toEqual([
      '4.100',
      '3.900',
      '4.250',
    ])
    // time_slot ikut dirender JUSTRU supaya pembaca dapat MEMERIKSA nilai
    // mana yang dipakai.
    expect(rows.map((row) => row.get('[data-testid="energy-export-time-slot"]').text())).toEqual([
      '06:00',
      '23:00',
      '13:00',
    ])

    // Kartu energi tidak pernah membawa label 'rata-rata'.
    const energyCard = wrapper.get('[data-testid="energy-export-card"]')

    expect(energyCard.get('[data-testid="energy-export-badge"]').text()).toBe('nilai terakhir')
    expect(energyCard.findAll('[data-testid="metric-avg"]')).toHaveLength(0)
    expect(energyCard.get('[data-testid="energy-export-no-average-note"]').text()).toContain(
      'tidak punya rata-rata periode sama sekali',
    )
  })

  // skenario 17 — PONSEL TIDAK MENENTUKAN SLOT MANA YANG TERAKHIR
  it('skenario 17: urutan baris mengikuti KISI KANONIS server, bukan urutan leksikal slot', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const slots = wrapper
      .findAll('[data-testid="energy-export-row"]')
      .map((row) => row.get('[data-testid="energy-export-time-slot"]').text())

    // Pada kisi 07:00..06:00 slot '06:00' adalah posisi TERAKHIR, sementara
    // urutan LEKSIKAL 'HH:MM' memilihnya sebagai yang paling AWAL — dan
    // hasilnya terlihat sepenuhnya masuk akal. Layar yang mengurutkan slot
    // menjawab urutan leksikal, dan test ini gagal.
    expect(slots).toEqual(['06:00', '23:00', '13:00'])
    expect(slots).not.toEqual([...slots].sort())

    // Dan bukan urut menurut NILAI pula.
    const values = wrapper
      .findAll('[data-testid="energy-export-row"]')
      .map((row) => row.get('[data-testid="energy-export-last"]').text())

    expect(values).toEqual(['4.100', '3.900', '4.250'])
  })

  it('skenario 17: TIDAK ada nilai periode untuk kolom energi — sel totalnya tanda hubung', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Layar yang MENURUNKAN sendiri "nilai terakhir periode" (misalnya dengan
    // mengambil slot leksikal terbesar dari seri) harus menaruh angka itu di
    // suatu tempat — dan satu-satunya tempatnya adalah kedua sel total ini.
    // Keduanya WAJIB em dash, dan tidak satu pun nilai seri boleh muncul di
    // sana.
    expect(text(wrapper, 'by-unit-total-energy-export')).toBe('—')
    expect(text(wrapper, 'daily-total-energy-export')).toBe('—')
    for (const value of ['4.100', '3.900', '4.250']) {
      expect(text(wrapper, 'by-unit-total-energy-export'), value).not.toContain(value)
      expect(text(wrapper, 'daily-total-energy-export'), value).not.toContain(value)
    }
  })

  it('nilai yang TURUN dibanding slot sebelumnya dirender apa adanya, tanpa penanda', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        daily_energy_export: {
          series: [
            { date: DAY_A, last_value: 4100, time_slot: '06:00', reading_count: 24 },
            // TURUN tajam — dan ditampilkan apa adanya tanpa dikoreksi.
            { date: DAY_B, last_value: 120, time_slot: '22:00', reading_count: 20 },
          ],
          has_data: true,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const rows = wrapper.findAll('[data-testid="energy-export-row"]')

    expect(rows[1].get('[data-testid="energy-export-last"]').text()).toBe('120')
    expect(rows[1].get('[data-testid="energy-export-time-slot"]').text()).toBe('22:00')

    const classNames = block(wrapper, 'daily-energy-export')
      .findAll('[class]')
      .flatMap((element) => (element.attributes('class') ?? '').split(/\s+/))

    expect(
      classNames.filter((name) => /error|danger|warning|anomal|invalid|alert/.test(name)),
    ).toEqual([])
  })

  // skenario 16 + 54
  it('skenario 16/54: has_data false — kartu dan seri menyatakan tidak ada data, nol titik bernilai 0', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({ daily_energy_export: { series: [], has_data: false } }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'energy-export-empty')).toContain('tidak ada data yang dapat ditampilkan')
    expect(text(wrapper, 'daily-energy-export-empty')).toContain('tidak menampilkan satu titik pun')
    // Tabelnya TIDAK digambar: baris nol akan terbaca sebagai hasil
    // pengukuran.
    expect(exists(wrapper, 'daily-energy-export-table')).toBe(false)
    expect(wrapper.findAll('[data-testid="energy-export-row"]')).toHaveLength(0)
    expect(exists(wrapper, 'energy-export-dates')).toBe(false)
  })

  // skenario 55
  it('skenario 55: keterangan perbedaan agregasi dirender DI TEMPAT yang diterangkannya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // "bukan rata-rata" ADA DI KARTU ENERGI.
    expect(
      wrapper
        .get('[data-testid="energy-export-no-average-note"]')
        .element.closest('[data-testid="energy-export-card"]'),
    ).not.toBeNull()

    // "agregasi tren berbeda" ADA DI DEKAT TREN BEBAN — bukan sekali di atas
    // halaman, tempat ia tidak menerangkan apa pun.
    expect(
      wrapper
        .get('[data-testid="aggregation-difference-note"]')
        .element.closest('[data-testid="daily-trend-total-factory-load"]'),
    ).not.toBeNull()
    expect(text(wrapper, 'aggregation-difference-note')).toContain('nilai terakhir')
    expect(text(wrapper, 'aggregation-difference-note')).toContain('rata-rata')

    // Dan keterangan seri menyebutkan bahwa SERVER yang menentukan
    // "terakhir" dari posisi slot pada kisi kanonis.
    expect(text(wrapper, 'daily-energy-export-note')).toContain('ditentukan SERVER')
    expect(text(wrapper, 'daily-energy-export-note')).toContain('07:00')
    expect(text(wrapper, 'daily-energy-export-note')).toContain('06:00')
  })
})

/* ================================================================== */
/* Tren harian beban total pabrik                                      */
/* ================================================================== */

describe('tren harian beban total pabrik', () => {
  it('SATU tren, dan batang titik tanpa pembacaan KOSONG — bukan batang nol', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const columns = block(wrapper, 'daily-trend-total-factory-load').findAll('.chart-col')

    expect(columns).toHaveLength(3)
    expect(columns.map((column) => column.get('.chart-value').text())).toEqual([
      '1.180',
      'tidak tersedia',
      '1.320',
    ])
    // Titik tanpa pembacaan menghasilkan batang KOSONG (0%), bukan batang nol
    // yang terbaca sebagai hasil ukur.
    expect(columns[1].get('.chart-fill').attributes('style')).toContain('height: 0%')
    // Dan tinggi batang adalah SKALA TAMPILAN 28% + 72%×(v−min)/(max−min):
    // nilai terendah series mendapat 28%, tertinggi 100%.
    expect(columns[0].get('.chart-fill').attributes('style')).toContain('height: 28%')
    expect(columns[2].get('.chart-fill').attributes('style')).toContain('height: 100%')
  })

  it('terendah/tertinggi series DILABELI "dari rata-rata harian", dan tidak diwarnai', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const section = text(wrapper, 'daily-trend-total-factory-load')

    expect(section).toContain('dari rata-rata harian')
    // Rata-rata periode DIAMBIL dari metrics[kolom], dihitung ulang server
    // atas seluruh slot — bukan dirata-ratakan dari rata-rata harian.
    expect(section).toContain('1.180')
    expect(section).toContain('240 pembacaan')
    expect(section).toContain('Sumbu tegak tidak dimulai dari nol')

    const classNames = block(wrapper, 'daily-trend-total-factory-load')
      .findAll('[class]')
      .flatMap((element) => (element.attributes('class') ?? '').split(/\s+/))

    expect(classNames.filter((name) => /--low|--high|--danger|--warning|--safe/.test(name)))
      .toEqual([])
  })
})

/* ================================================================== */
/* Skenario 18 + 19 + 20 + 60-63 — DUA REKAP TEKS BEBAS                 */
/* ================================================================== */

describe('rekap teks bebas', () => {
  // skenario 60
  it('skenario 60: dua bagian berjudul TERPISAH, tiap entri membawa tanggal dan slot waktunya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'maintenance-remarks')).toContain(
      'Rekap Catatan Tindakan Perawatan (3 slot)',
    )
    expect(text(wrapper, 'findings')).toContain('Rekap Temuan (2 slot)')

    const remarks = wrapper.findAll('[data-testid="maintenance-remark-row"]')

    // TIGA entri berteks IDENTIK pada tiga slot berbeda: layar yang
    // mengelompokkan menurut teks mencetak SATU baris.
    expect(remarks).toHaveLength(3)
    for (const remark of remarks) {
      expect(remark.get('.note-text').text()).toBe('Ganti filter lube oil genset 1')
      expect(remark.get('.note-meta').text()).toMatch(/\d{2} \w{3} · \d{2}:\d{2}/)
    }
    expect(remarks.map((remark) => remark.get('.note-meta').text().split('·')[1].trim())).toEqual([
      '07:00',
      '13:00',
      '21:00',
    ])

    expect(wrapper.findAll('[data-testid="finding-row"]')).toHaveLength(2)

    // Tidak ada daftar GABUNGAN.
    expect(exists(wrapper, 'free-text-combined')).toBe(false)
    expect(exists(wrapper, 'text-entries')).toBe(false)
  })

  // skenario 18 + 61
  it('skenario 18/61: satu slot yang mengisi kedua kolom muncul di KEDUA rekap', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const firstRemark = wrapper
      .findAll('[data-testid="maintenance-remark-row"]')[0]
      .get('.note-meta')
      .text()
    const firstFinding = wrapper.findAll('[data-testid="finding-row"]')[0].get('.note-meta').text()

    // TANGGAL + SLOT yang SAMA pada kedua rekap, dengan teks masing-masing.
    expect(firstRemark).toBe(firstFinding)
    expect(
      wrapper.findAll('[data-testid="maintenance-remark-row"]')[0].get('.note-text').text(),
    ).not.toBe(wrapper.findAll('[data-testid="finding-row"]')[0].get('.note-text').text())

    // Dan hal itu DINYATAKAN bukan penghitungan ganda.
    expect(text(wrapper, 'findings-note')).toContain('muncul pada kedua rekap')
    expect(text(wrapper, 'findings-note')).toContain('bukan penghitungan ganda')

    // Jumlah slot terisi TIDAK bertambah dua karenanya: ia angka server.
    expect(text(wrapper, 'coverage-slots')).toContain('260 dari')
  })

  // skenario 19 + 62
  it('skenario 19/62: kedua rekap kosong — keterangan MASING-MASING, bukan satu gabungan', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({ maintenance_remarks: [], findings: [] }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Kedua bagian TETAP berjudul terpisah, masing-masing dengan
    // keterangannya sendiri — bukan satu keterangan gabungan dan bukan
    // bagian yang dihilangkan.
    expect(exists(wrapper, 'maintenance-remarks')).toBe(true)
    expect(exists(wrapper, 'findings')).toBe(true)
    expect(text(wrapper, 'maintenance-remarks-empty')).toContain(
      'Tidak ada satu pun catatan tindakan perawatan',
    )
    expect(text(wrapper, 'findings-empty')).toContain('Tidak ada satu pun temuan')
    expect(wrapper.findAll('[data-testid="maintenance-remark-row"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-testid="finding-row"]')).toHaveLength(0)
  })

  // skenario 20 + 63
  it('skenario 20/63: teks panjang membawa pembungkusan kata, TANPA ellipsis maupun line-clamp', async () => {
    const longText =
      'Ditemukan rembesan oli pada sambungan pipa lube oil genset 2 yang membasahi lantai '
      + 'sekitar panel sinkronisasi, sudah dibersihkan dan diberi penampung sementara, '
      + 'tetapi sambungannya perlu diganti pada kesempatan berhenti berikutnya karena '
      + 'rembesannya kembali muncul setelah beberapa jam beroperasi.'

    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        findings: [{ date: DAY_A, time_slot: '09:00', text: longText }],
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const noteText = wrapper.get('[data-testid="finding-row"]').get('.note-text')

    // Teksnya UTUH — tidak dipotong di klien.
    expect(noteText.text()).toBe(longText)

    // Dan definisi kelasnya membawa pembungkusan kata TANPA pemotongan.
    // Diperiksa dari DEFINISI CSS, bukan dari markup: jsdom tidak menghitung
    // tata letak, jadi lebar gulirnya diukur di spec browser.
    const viewSource = readFileSync(
      resolve(process.cwd(), 'src/views/LaporanEngineRoomView.vue'),
      'utf-8',
    )
    const noteTextRule = viewSource
      .slice(viewSource.indexOf('.note-text {'))
      .slice(0, viewSource.slice(viewSource.indexOf('.note-text {')).indexOf('}') + 1)

    expect(noteTextRule).toContain('overflow-wrap: anywhere')
    expect(noteTextRule).not.toContain('text-overflow')
    expect(noteTextRule).not.toContain('line-clamp')
    expect(noteTextRule).not.toContain('white-space: nowrap')
    // Tidak ada kontrol "baca selengkapnya" di mana pun pada layar ini.
    expect(wrapper.text().toLowerCase()).not.toContain('baca selengkapnya')
    expect(wrapper.text().toLowerCase()).not.toContain('selengkapnya')
  })
})

/* ================================================================== */
/* Skenario 21 + 30 + 64 + 65 — kedua rekap tabel                       */
/* ================================================================== */

describe('rekap per unit dan rekap harian', () => {
  // skenario 21 + 64
  it('skenario 21/64: dua baris unit, termasuk unit TANPA pembacaan', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const rows = wrapper.findAll('[data-testid="by-unit-row"]')

    expect(rows).toHaveLength(2)
    expect(rows[0].text()).toContain('ER-1')
    expect(rows[1].text()).toContain('ER-2')
    // Unit yang punya record tetapi tidak satu pun pembacaan TETAP muncul —
    // membuangnya justru akan menyembunyikan unit yang tidak pernah
    // dituliskan.
    expect(rows.map((row) => row.get('[data-testid="by-unit-reading-rows"]').text())).toEqual([
      '180',
      '0',
    ])

    // Baris total MENGAMBIL angka dari `metrics` tingkat periode — yang
    // dihitung ulang server atas seluruh slot — bukan merata-ratakan baris di
    // atasnya.
    expect(text(wrapper, 'by-unit-row-total')).toContain('Gabungan 2 unit')
    expect(text(wrapper, 'by-unit-row-total')).toContain('260')
  })

  // skenario 30 + 65
  it('skenario 30/65: rekap harian dapat ditutup, dan buka/tutup tidak memicu permintaan baru', async () => {
    const dailyRows = Array.from({ length: 31 }, (_unused, index) => ({
      date: isoDaysFromNow(-31 + index),
      reading_rows: index + 1,
      metrics_avg: Object.fromEntries(
        METRIC_COLUMNS.map((column) => [column, { avg: 7 + index / 10, reading_count: index + 1 }]),
      ),
      energy_export: {
        last_value: 4000 + index,
        date: isoDaysFromNow(-31 + index),
        time_slot: '06:00',
        reading_count: index + 1,
      },
    }))

    repoMocks.fetchSummary.mockResolvedValue(summaryFixture({ daily: dailyRows }))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)
    expect(wrapper.findAll('[data-testid^="daily-row-20"], [data-testid^="daily-row-19"]').length)
      .toBeGreaterThanOrEqual(0)
    expect(wrapper.get('[data-testid="daily-table"]').findAll('tbody tr')).toHaveLength(31)

    const toggle = block(wrapper, 'daily-recap').get('[data-testid="collapsible-section-toggle"]')

    await toggle.trigger('click')
    await flushPromises()
    await toggle.trigger('click')
    await flushPromises()

    // Datanya sudah ada di memori: membuka atau menutup rekap tidak memicu
    // satu permintaan pun, dan tidak mengubah satu angka pun.
    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)
    expect(wrapper.get('[data-testid="daily-table"]').findAll('tbody tr')).toHaveLength(31)
    expect(text(wrapper, 'daily-row-total')).toContain('99')
  })

  it('kedua rekap berada DI DALAM pembungkus yang menggulir sendiri, dan tren di .chart-scroll', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // jsdom tidak menghitung tata letak, jadi lebar GULIR-nya diuji di spec
    // browser (tests/e2e/laporan-engine-room.spec.ts). Yang dapat — dan
    // HARUS — dibuktikan di sini adalah STRUKTURNYA.
    for (const testid of [
      'by-unit-table',
      'daily-table',
      'daily-energy-export-table',
      'status-distribution-diesel-gen-1-table',
      'status-distribution-diesel-gen-2-table',
    ]) {
      expect(
        wrapper.get(`[data-testid="${testid}"]`).element.closest('.detail-table-wrap'),
        testid,
      ).not.toBeNull()
    }

    // Tidak satu tabel pun di luar pembungkusnya.
    expect(wrapper.findAll('table')).toHaveLength(wrapper.findAll('.detail-table-wrap table').length)

    // SATU-SATUNYA elemen ber-overflow-x di layar ini adalah grafik tren —
    // kedua rekap BERTUMPUK menjadi kartu lewat v-stacked-table (temuan QA
    // LP-M-02/LP-M-03), bukan menggulir mendatar.
    expect(
      block(wrapper, 'daily-trend-total-factory-load').get('.chart-bars').element.closest(
        '.chart-scroll',
      ),
    ).not.toBeNull()
    expect(wrapper.findAll('.chart-scroll')).toHaveLength(1)
    expect(text(wrapper, 'daily-trend-scroll-hint')).toContain('di dalam kartu ini')

    // Dan kedua tabel rekap MEMBAWA direktif penumpukan.
    const source = readFileSync(
      resolve(process.cwd(), 'src/views/LaporanEngineRoomView.vue'),
      'utf-8',
    )

    // Dihitung atas MARKUP tabelnya, bukan atas seluruh kemunculan string:
    // docblock berkas itu MENJELASKAN direktifnya dua kali, dan penyisiran
    // atas sumber mentah akan ikut menghitung penjelasannya.
    // EMPAT kemunculan markup untuk LIMA tabel di DOM: tabel sebaran status
    // adalah SATU markup yang di-loop atas STATUS_BLOCKS. Dihitung atas
    // markup tabelnya, bukan atas seluruh kemunculan string — docblock berkas
    // itu MENJELASKAN direktifnya dua kali.
    expect(source.match(/<table v-stacked-table/g) ?? []).toHaveLength(4)
  })
})

/* ================================================================== */
/* Skenario 36 + 59 + 66 — KETIADAAN penandaan, target, dan downtime   */
/* ================================================================== */

describe('ketiadaan penandaan di luar batas', () => {
  // skenario 36 + 59
  it('skenario 36/59: nol elemen berkelas penanda, dan keterangannya dirender UTUH', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Frekuensi 43 Hz, power factor tidak tersedia, dan busbar 310 V ada di
    // fixture justru karena ketiganya terlihat "mencurigakan".
    expect(
      card(wrapper, 'electrical_sync_system_frequency_hz')
        .get('[data-testid="metric-avg"]')
        .text(),
    ).toBe('49,82')
    expect(
      card(wrapper, 'electrical_sync_busbar_voltage_v').findAll('.metric-row-item b')[0].text(),
    ).toBe('310')

    // KETIADAAN PENANDAAN DIASERSI ATAS JUMLAH ELEMEN BERKELAS, bukan atas
    // frasa maupun atas substring sumber: kalimat yang menyatakan
    // ketiadaannya sendiri memuat frasa "di luar batas".
    const classNames = wrapper
      .findAll('[data-testid="metric-card"] [class], [data-testid="metric-card"]')
      .flatMap((element) => (element.attributes('class') ?? '').split(/\s+/))

    expect(
      classNames.filter((name) =>
        /warning|danger|error|alert|violation|out-of-range|breach|critical|over-limit/.test(name),
      ),
    ).toEqual([])

    // Dan keterangannya dirender PENUH — tanpa text-overflow, tanpa
    // line-clamp, tanpa "baca selengkapnya".
    const note = text(wrapper, 'no-threshold-note')

    expect(note.length).toBeGreaterThan(600)
    expect(note).toContain('TIDAK ADA penandaan nilai di luar batas')
    expect(note).toContain('tidak punya tabel master target')
    expect(note).toContain('frekuensi sistem 50 Hz')
    expect(note).toContain('batas keselamatan kelistrikan')
    expect(note).not.toContain('…')
    expect(note).not.toContain('...')

    // Ia MENEMPEL pada blok Kelistrikan Tersinkron — bukan catatan kaki
    // halaman, karena justru di blok inilah godaan menandai paling besar.
    expect(
      wrapper
        .get('[data-testid="no-threshold-note"]')
        .element.closest('[data-testid="metric-block-electrical-sync"]'),
    ).not.toBeNull()
  })

  it('definisi .section-note tidak membawa pemotongan apa pun', () => {
    const source = readFileSync(
      resolve(process.cwd(), 'src/views/LaporanEngineRoomView.vue'),
      'utf-8',
    )
    const start = source.indexOf('.section-note {')
    const rule = source.slice(start, start + source.slice(start).indexOf('}') + 1)

    // STANDAR KERAPIAN TAMPILAN: diperiksa dari DEFINISI, karena kalimat yang
    // terpotong di tengah berhenti menerangkan apa pun — dan di layar sempit
    // itulah godaan terbesarnya.
    expect(rule).toContain('overflow-wrap: anywhere')
    expect(rule).not.toContain('text-overflow')
    expect(rule).not.toContain('line-clamp')
    expect(rule).not.toContain('max-height')
    expect(rule).not.toContain('white-space: nowrap')
  })

  // skenario 66
  it('skenario 66: nol bagian downtime di DOM, dan ketiadaannya DINYATAKAN', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Diasersi atas TESTID bagian dan atas KETIADAAN elemen — bukan atas
    // substring 'downtime', karena pernyataan yang membuktikan ketiadaannya
    // sendiri memuat kata itu.
    for (const testid of [
      'downtime',
      'downtime-summary',
      'downtime-by-reason',
      'downtime-total',
      'targets-without-metric',
    ]) {
      expect(exists(wrapper, testid), testid).toBe(false)
    }

    expect(text(wrapper, 'no-downtime-note')).toContain('tidak mencatat lama berhenti sama sekali')
    expect(text(wrapper, 'no-target-note')).toContain('tidak punya tabel master target')
    // Nol kolom target pada kartu mana pun.
    expect(wrapper.findAll('[data-testid="metric-target"]')).toHaveLength(0)
  })
})

/* ================================================================== */
/* Skenario 3 + 4 + 5 + 6 + 18-27 — PRODUCTION LINE                     */
/* ================================================================== */

describe('penentuan Production Line', () => {
  // unit_test_case 18
  it('query rute DIUTAMAKAN atas ingatan perangkat', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1, LINE_2, LINE_3])
    window.localStorage.setItem(REMEMBERED_LINE_KEY, 'pl-2')
    routeQuery.production_line_id = 'pl-3'

    const wrapper = await mountView()

    // Tile Reporting membawa line terpilih, dan itu konteks yang paling baru.
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe('Line Tiga')
    // Dan nilai dari rute DITULIS KEMBALI sebagai ingatan — layar Daftar
    // Stasiun dan layar laporan berbagi satu konteks.
    expect(window.localStorage.getItem(REMEMBERED_LINE_KEY)).toBe('pl-3')
  })

  // unit_test_case 19 + 22 + skenario 4
  it('skenario 4: ingatan perangkat dipakai bila rute kosong, dengan kunci ber-id pengguna', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1, LINE_2])
    window.localStorage.clear()
    window.localStorage.setItem('msl_production_line_user-1', 'pl-2')

    const wrapper = await mountView()

    // Kuncinya SAMA PERSIS dengan yang ditulis StationListView.vue — kunci
    // yang berbeda akan terkompilasi, terlihat benar, dan tidak pernah cocok.
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe('Line Dua')

    await selectPeriod(wrapper, 'per-1')
    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('per-1', { productionLineId: 'pl-2' })
  })

  // unit_test_case 22
  it('ingatan milik pengguna LAIN tidak pernah terbawa', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1, LINE_2])
    window.localStorage.clear()
    window.localStorage.setItem('msl_production_line_user-99', 'pl-2')
    asRole('operator', 'bu-1', 'user-9')

    const wrapper = await mountView()

    // Dua akun di satu perangkat tidak saling menimpa: kunci yang dibaca
    // adalah 'msl_production_line_user-9'.
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
  })

  // unit_test_case 20 — LANGKAH KEEMPAT TIDAK ADA
  it('TIDAK memilih line pertama secara diam-diam, walau daftarnya tiga', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1, LINE_2, LINE_3])
    window.localStorage.clear()

    const wrapper = await mountView()

    // Memilihkan line secara diam-diam menerbitkan angka yang pengguna tidak
    // pernah minta — dan pada laporan per-line itu berarti pembaca menyangka
    // sedang melihat seluruh mill.
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
  })

  /**
   * DIVERGENSI YANG DISENGAJA DARI LaporanEffluentPlantView.vue — lihat
   * catatan #4 pada docblock berkas. Layar itu punya LANGKAH KEEMPAT
   * (lines.length === 1 -> lines[0]); layar ini tidak, karena
   * business_logic #5 menuliskan tiga langkah saja. Akibat teknisnya:
   * pemilih DIRENDER walau daftarnya hanya satu entri, dan asersi effluent
   * "tidak ada pemilih bila hanya satu line" SALAH di sini.
   */
  it('TIDAK memilih satu-satunya line pula — pemilihnya DIRENDER walau daftarnya satu', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1])
    window.localStorage.clear()

    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-select')).toBe(true)
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(text(wrapper, 'production-line-required-hint')).toContain(
      'bahkan ketika mill ini hanya punya satu line',
    )
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
  })

  // unit_test_case 21 + skenario 5
  it('skenario 5: localStorage yang MELEMPAR tidak mematahkan layar dan tidak memunculkan galat', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1, LINE_2])

    /**
     * DIGANTI DI PROTOTIPE, BUKAN LEWAT vi.spyOn PADA INSTANCE-NYA.
     * `window.localStorage` di jsdom adalah Proxy, dan spy yang dipasang pada
     * instance-nya TIDAK PERNAH terpanggil — getItem asli tetap menjawab, dan
     * test-nya "lulus" ke cabang yang justru sedang diuji tidak terjadi.
     * Diperiksa langsung sebelum ditulis: spy instance tidak melempar,
     * penggantian Storage.prototype melempar.
     */
    const originalGetItem = Storage.prototype.getItem

    Storage.prototype.getItem = () => {
      throw new Error('SecurityError: akses penyimpanan ditolak')
    }

    let wrapper: VueWrapper

    try {
      wrapper = await mountView()
    } finally {
      Storage.prototype.getItem = originalGetItem
    }

    // Pada mode privat penyimpanan dapat melempar, dan gagal mengingat
    // pilihan tidak boleh mematahkan layar — kegagalannya diperlakukan
    // sebagai "tidak ada ingatan", BUKAN sebagai galat.
    expect(exists(wrapper, 'production-line-select')).toBe(true)
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
  })

  // unit_test_case 23
  it('tanpa pengguna masuk, penyimpanan TIDAK dibaca sama sekali', async () => {
    useAuthStoreMock.mockReturnValue({
      currentUser: null,
      businessUnit: null,
      logout: logoutMock,
    })
    const getItem = vi.spyOn(window.localStorage, 'getItem')

    const wrapper = await mountView()

    // Tanpa pengguna, kunci ingatan tidak dapat dibentuk — dan layar berhenti
    // lebih awal pada cabang "akun tanpa mill", tanpa satu permintaan pun.
    expect(exists(wrapper, 'no-mill-for-account')).toBe(true)
    expect(
      getItem.mock.calls.filter(([key]) => String(key).startsWith('msl_production_line_')),
    ).toEqual([])
    expect(productionLineMocks.fetchProductionLinesForReport).not.toHaveBeenCalled()
    expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()
  })

  it('line yang DIINGAT tetapi sudah tidak ada di daftar TIDAK diganti line lain', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1, LINE_2])
    window.localStorage.clear()
    window.localStorage.setItem(REMEMBERED_LINE_KEY, 'pl-sudah-dihapus')

    const wrapper = await mountView()

    // Nilai dari rute dan dari ingatan TIDAK PERNAH dipercaya begitu saja:
    // keduanya hanya dipakai bila masih ada di daftar yang baru diambil.
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
  })

  it('mengganti line menyimpan pilihan dan memuat ulang ringkasan untuk line ITU', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1, LINE_2])

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await selectLine(wrapper, 'pl-2')

    expect(window.localStorage.getItem(REMEMBERED_LINE_KEY)).toBe('pl-2')
    expect(repoMocks.fetchSummary).toHaveBeenLastCalledWith('per-1', { productionLineId: 'pl-2' })
    // Nama line yang menyertai angka datang dari SERVER — itulah line yang
    // benar-benar dipakai saat menghitung.
    expect(text(wrapper, 'production-line-current')).toContain('Line Dua')
  })

  it('skenario 3: mill tanpa Production Line — arahan menghubungi Admin, tanpa tombol coba lagi', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([])

    const wrapper = await mountView()

    expect(text(wrapper, 'production-line-unavailable')).toContain('hubungi Admin')
    expect(exists(wrapper, 'production-line-retry')).toBe(false)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
  })

  it('daftar line GAGAL dimuat — pesan BERBEDA, dengan tombol coba lagi sendiri', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockRejectedValueOnce(NETWORK_ERROR)

    const wrapper = await mountView()

    // Dua keadaan yang menuntut tindakan berbeda, jadi dua pesan berbeda —
    // dan hanya yang kedua punya tombol Coba Lagi.
    expect(text(wrapper, 'production-line-unavailable')).toContain('tidak dapat dimuat')
    expect(exists(wrapper, 'production-line-retry')).toBe(true)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1])
    await wrapper.get('[data-testid="production-line-retry"]').trigger('click')
    await flushPromises()

    expect(exists(wrapper, 'production-line-unavailable')).toBe(false)
    expect(exists(wrapper, 'production-line-select')).toBe(true)
  })
})

/* ================================================================== */
/* Skenario 3 + 27 — PENJAGA ADA DI loadSummary(), TIGA JALUR           */
/* ================================================================== */

/**
 * TIGA TEST TERPISAH, SATU PER JALUR, dan masing-masing mengasersi NOL
 * pemanggilan fetchSummary. Ketiga jalur memanggil fungsi pemuatan yang SAMA,
 * jadi satu penjagaan melindungi ketiganya — tetapi penjaga yang hanya ada di
 * TEMPLATE dilewati oleh ketiganya, dan melewatinya di layar ini berarti 422
 * dari server, bukan angka seluruh mill.
 */
describe('penjaga Production Line di loadSummary()', () => {
  beforeEach(() => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1, LINE_2])
    window.localStorage.clear()
  })

  it('skenario 3 jalur 1/3 — MUAT AWAL: nol permintaan ringkasan, nol kartu, tanpa tombol ekspor', async () => {
    const wrapper = await mountView()

    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
    expect(cards(wrapper)).toHaveLength(0)
    expect(exists(wrapper, 'coverage')).toBe(false)
    expect(exists(wrapper, 'energy-export-card')).toBe(false)
    // Ekspor mengikuti cakupan yang sama dengan angka di layar: tanpa line,
    // tombolnya pun tidak dirender.
    expect(exists(wrapper, 'export-button')).toBe(false)
    expect(text(wrapper, 'production-line-required-hint')).toContain(
      'Pilih Production Line terlebih dahulu',
    )
    // Dan tidak ada angka gabungan seluruh mill sebagai penggantinya.
    expect(wrapper.text()).not.toContain('Gabungan')
  })

  it('skenario 3 jalur 2/3 — GANTI PERIODE: tetap nol permintaan ringkasan', async () => {
    const wrapper = await mountView()

    await selectPeriod(wrapper, 'per-1')
    await selectPeriod(wrapper, 'per-2')

    // Jalur ini memanggil fungsi pemuatan yang sama, jadi satu penjagaan
    // melindungi keduanya.
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
    expect(cards(wrapper)).toHaveLength(0)
    expect(exists(wrapper, 'export-button')).toBe(false)
  })

  it('skenario 3 jalur 3/3 — COBA LAGI: tetap nol permintaan ringkasan', async () => {
    // Keadaan yang dapat DICAPAI dengan line kosong DAN tombol Coba Lagi
    // terender: /periods gagal karena jaringan (retryAction = loadPeriods).
    // Inilah jalur ketiga yang akan lolos bila penjagaannya ditaruh di
    // template — sesudah coba lagi berhasil, pengguna memilih periode, dan
    // tanpa penjaga di fungsi pemuatan permintaan ringkasan terbentuk tanpa
    // line.
    repoMocks.fetchPeriods.mockRejectedValueOnce(NETWORK_ERROR)

    const wrapper = await mountView()

    expect(exists(wrapper, 'network-error')).toBe(true)
    expect(exists(wrapper, 'retry-button')).toBe(true)

    repoMocks.fetchPeriods.mockResolvedValue([PERIOD, PERIOD_CLOSED])
    await wrapper.get('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

    await selectPeriod(wrapper, 'per-1')

    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
    expect(cards(wrapper)).toHaveLength(0)
    expect(exists(wrapper, 'export-button')).toBe(false)
  })

  // unit_test_case 27
  it('memanggil jaringan TEPAT SEKALI ketika line dan periode keduanya berlaku', async () => {
    window.localStorage.setItem(REMEMBERED_LINE_KEY, 'pl-1')

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('per-1', { productionLineId: 'pl-1' })
  })

  it('tombol Coba Lagi ringkasan MEMANGGIL ULANG loadSummary — jalur yang sama, penjaga yang sama', async () => {
    window.localStorage.setItem(REMEMBERED_LINE_KEY, 'pl-1')
    repoMocks.fetchSummary.mockRejectedValueOnce(NETWORK_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'network-error')).toBe(true)
    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)

    await wrapper.get('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    // Permintaan KEDUA terbentuk karena line-nya berlaku — itulah yang
    // membuktikan bahwa tombol Coba Lagi memang melewati loadSummary(), dan
    // karena itu melewati penjaganya pula.
    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(2)
    expect(cards(wrapper)).toHaveLength(22)
  })
})

/* ================================================================== */
/* Skenario 6 + 7 + 25 + 27 + 28 — galat dan keadaan                   */
/* ================================================================== */

describe('galat dan keadaan', () => {
  // skenario 6 + unit_test_case 30 + 32
  it('skenario 6: 403 diperlakukan "pilih line lagi" — BUKAN galat jaringan', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1, LINE_2])
    window.localStorage.setItem(REMEMBERED_LINE_KEY, 'pl-2')
    repoMocks.fetchSummary.mockRejectedValueOnce(FORBIDDEN_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Klien TETAP mengirimkannya dan bersandar pada 403 server — tidak ada
    // pemeriksaan kepemilikan di klien, karena itu akan menduplikasi aturan
    // otorisasi di dua tempat yang dapat berselisih.
    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('per-1', { productionLineId: 'pl-2' })

    expect(text(wrapper, 'production-line-rejected')).toContain('pilih Production Line kembali')
    // BUKAN galat jaringan, dan BUKAN pesan galat umum.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)
    expect(exists(wrapper, 'retry-button')).toBe(false)
    // Pilihan line DIKOSONGKAN supaya pengguna memilih lagi.
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    // Dan nol angka mill lain di DOM.
    expect(cards(wrapper)).toHaveLength(0)
    expect(exists(wrapper, 'coverage')).toBe(false)
    expect(pushMock).not.toHaveBeenCalled()
  })

  // unit_test_case 31
  it('404 pada /summary menyatakan periode tidak ditemukan, TANPA tombol coba lagi', async () => {
    repoMocks.fetchSummary.mockRejectedValueOnce(NOT_FOUND_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'error-message')).toContain('Periode tidak ditemukan')
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'retry-button')).toBe(false)
    // Pilihan periode dikosongkan.
    expect(selectedOptionText(wrapper, 'period-select')).toBe(NO_PERIOD_SELECTED)
    expect(cards(wrapper)).toHaveLength(0)
  })

  // skenario 25 + unit_test_case 29
  it('skenario 25: 422 akun tanpa mill — arahan menghubungi Admin, tanpa daftar mill', async () => {
    repoMocks.fetchPeriods.mockRejectedValueOnce(NO_MILL_ERROR)

    const wrapper = await mountView()

    expect(text(wrapper, 'mill-not-linked')).toContain('hubungi Admin')
    expect(text(wrapper, 'mill-not-linked')).toContain('bukan masalah jaringan')
    // BUKAN galat jaringan, dan sistem TIDAK menawarkan daftar seluruh mill
    // sebagai gantinya.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'mill-select')).toBe(false)
    expect(exists(wrapper, 'retry-button')).toBe(false)
    expect(cards(wrapper)).toHaveLength(0)
  })

  it('422 pada /summary juga berarti akun tanpa mill, bukan pesan validasi mentah', async () => {
    repoMocks.fetchSummary.mockRejectedValueOnce(NO_MILL_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'mill-not-linked')).toBe(true)
    expect(exists(wrapper, 'network-error')).toBe(false)
  })

  it('akun ber-business_unit_id NULL berhenti total, TANPA satu permintaan pun', async () => {
    asRole('operator', null)

    const wrapper = await mountView()

    expect(text(wrapper, 'no-mill-for-account')).toContain('hubungi Admin')
    expect(productionLineMocks.fetchProductionLinesForReport).not.toHaveBeenCalled()
    expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
    // Dan TIDAK ada pemilih mill sebagai gantinya.
    expect(exists(wrapper, 'mill-select')).toBe(false)
    expect(exists(wrapper, 'production-line-select')).toBe(false)
  })

  // unit_test_case 28
  it('401 dari endpoint MANA PUN mengarahkan ke Login TANPA pesan jaringan', async () => {
    repoMocks.fetchPeriods.mockRejectedValueOnce(UNAUTHENTICATED_ERROR)

    const onPeriods = await mountView()

    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    // "Periksa koneksi Anda" pada sesi yang berakhir mengirim pembaca
    // memeriksa sinyal alih-alih masuk kembali.
    expect(exists(onPeriods, 'network-error')).toBe(false)
    expect(exists(onPeriods, 'error-message')).toBe(false)

    pushMock.mockClear()
    repoMocks.fetchPeriods.mockResolvedValue([PERIOD, PERIOD_CLOSED])
    repoMocks.fetchSummary.mockRejectedValueOnce(UNAUTHENTICATED_ERROR)

    const onSummary = await mountView()
    await selectPeriod(onSummary, 'per-1')

    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    expect(exists(onSummary, 'network-error')).toBe(false)
    expect(exists(onSummary, 'error-message')).toBe(false)
  })

  it('401 saat mengambil daftar line pun mengarahkan ke Login, bukan pesan daftar line gagal', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockRejectedValueOnce(UNAUTHENTICATED_ERROR)

    const wrapper = await mountView()

    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    // TIDAK ada pesan "daftar line gagal dimuat" dan TIDAK ada tombol Coba
    // Lagi: pesan jaringan pada sesi yang berakhir mengirim pembaca memeriksa
    // sinyal alih-alih masuk kembali.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'production-line-retry')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)
    // Keadaan "mill belum punya line" MEMANG ikut terender di sini, dan itu
    // tidak terlihat pengguna di produksi: router.push('login') melepas layar
    // ini. Di harness test router-nya di-mock, jadi layarnya tetap terpasang
    // — dicatat supaya pembaca berikutnya tidak mengejar "cacat" yang hanya
    // ada di harness.
    expect(exists(wrapper, 'production-line-select')).toBe(false)
  })

  // skenario 27 + unit_test_case 33
  it('skenario 27: galat jaringan menampilkan pesan + Coba Lagi, tanpa angka separuh', async () => {
    repoMocks.fetchSummary.mockRejectedValueOnce(NETWORK_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'network-error')).toContain('Tidak dapat terhubung ke server')
    expect(exists(wrapper, 'retry-button')).toBe(true)
    expect(cards(wrapper)).toHaveLength(0)
    expect(exists(wrapper, 'coverage')).toBe(false)
    // Periode dan line yang sudah terpilih SENGAJA tidak disentuh: pengguna
    // di area tanpa sinyal tidak boleh dipaksa memilih dari awal.
    expect(selectedOptionText(wrapper, 'period-select')).toContain('Periode Uji Engine Room')
    expect(text(wrapper, 'production-line-current')).toContain('Line Satu')
  })

  // skenario 7
  it('skenario 7: mill belum punya periode — arahan Admin, pemilih kosong, BUKAN keadaan galat', async () => {
    repoMocks.fetchPeriods.mockResolvedValue([])

    const wrapper = await mountView()

    expect(text(wrapper, 'no-periods')).toContain('hubungi Admin')
    expect(wrapper.findAll('[data-testid="period-select"] option')).toHaveLength(1)
    expect(cards(wrapper)).toHaveLength(0)
    // Keadaannya BUKAN keadaan galat.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)
    expect(exists(wrapper, 'mill-not-linked')).toBe(false)
  })

  // skenario 28 + unit_test_case 34
  it('skenario 28: luring DINYATAKAN, bukan ditutupi angka basi', async () => {
    const onLine = vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false)

    const wrapper = await mountView()

    expect(text(wrapper, 'offline-notice')).toContain('memerlukan jaringan')
    expect(text(wrapper, 'offline-notice')).toContain('tidak disimpan di perangkat ini')
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

  it('respons /summary yang tiba BELAKANGAN tidak menimpa pilihan yang baru', async () => {
    let releaseFirst!: (value: unknown) => void

    repoMocks.fetchSummary
      .mockImplementationOnce(
        () =>
          new Promise((resolvePromise) => {
            releaseFirst = resolvePromise
          }),
      )
      .mockImplementation((periodId: string) =>
        Promise.resolve(
          summaryFixture({
            period: {
              id: periodId,
              name: 'Periode Sebelumnya',
              start_date: PREV_START,
              end_date: PREV_END,
              status: 'closed',
            },
          }),
        ),
      )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await selectPeriod(wrapper, 'per-2')

    // Respons LAMA tiba belakangan — dan diabaikan lewat penanda permintaan
    // terakhir, bukan dengan membatalkan yang lama.
    releaseFirst(summaryFixture())
    await flushPromises()

    expect(text(wrapper, 'period-meta')).toContain('Periode Sebelumnya')
  })
})

/* ================================================================== */
/* Skenario 26 + 35 + 39 + 73 — EKSPOR CSV SAJA                         */
/* ================================================================== */

describe('ekspor', () => {
  // skenario 35
  it('skenario 35: HANYA satu tombol ekspor, berlabel CSV, dan NOL tombol Excel/xlsx', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'export-button')).toContain('Ekspor CSV')
    // Satu-satunya tombol ekspor di seluruh layar.
    expect(wrapper.findAll('[data-testid^="export-"]')).toHaveLength(1)
    for (const testid of ['export-excel-button', 'export-xlsx-button', 'export-format-select']) {
      expect(exists(wrapper, testid), testid).toBe(false)
    }

    // Diasersi atas TEKS YANG DIRENDER: WebView ponsel tidak membuka .xlsx,
    // jadi tombol yang menghasilkan berkas yang tidak dapat dibuka lebih
    // buruk daripada tidak menyediakannya.
    const body = wrapper.text().toLowerCase()

    expect(body).not.toContain('xlsx')
    expect(body).not.toContain('excel')
  })

  it('skenario 35: mengekspor periode + line terpilih dan menyimpan berkas bernama slug keduanya', async () => {
    repoMocks.exportCsv.mockResolvedValue(new Blob(['a,b']))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    expect(repoMocks.exportCsv).toHaveBeenCalledWith(
      'per-1',
      expect.objectContaining({ productionLineId: 'pl-1' }),
    )
    // Cakupan ekspor TIDAK membawa medan mill, karena medannya tidak ada.
    expect(repoMocks.exportCsv.mock.calls[0][1]).toEqual({ productionLineId: 'pl-1' })
    expect(repoMocks.saveCsvFile).toHaveBeenCalledWith(
      expect.any(Blob),
      'laporan-engine-room_periode-uji-engine-room_line-satu.csv',
    )
    // Dan tidak satu pun nama berkas berakhiran .xlsx dapat lahir dari jalur
    // ini.
    expect(String(repoMocks.saveCsvFile.mock.calls[0][1])).toMatch(/\.csv$/)
  })

  // skenario 39
  it('skenario 39: klik ganda cepat hanya mengirim SATU permintaan ekspor', async () => {
    let releaseExport!: (value: unknown) => void

    repoMocks.exportCsv.mockImplementation(
      () =>
        new Promise((resolvePromise) => {
          releaseExport = resolvePromise
        }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const button = wrapper.get('[data-testid="export-button"]')

    await button.trigger('click')
    await button.trigger('click')
    await button.trigger('click')

    // `exporting` mengunci tombol sesaat selama berkas disiapkan.
    expect(repoMocks.exportCsv).toHaveBeenCalledTimes(1)
    expect(button.attributes('disabled')).toBeDefined()
    expect(button.attributes('aria-busy')).toBe('true')

    releaseExport(new Blob(['a,b']))
    await flushPromises()

    expect(repoMocks.saveCsvFile).toHaveBeenCalledTimes(1)
    expect(
      wrapper.get('[data-testid="export-button"]').attributes('disabled'),
    ).toBeUndefined()
  })

  // unit_test_case 38
  it('422 EXPORT_FAILED ditampilkan APA ADANYA, tanpa memotong atau mengulang', async () => {
    repoMocks.exportCsv.mockRejectedValueOnce(EXPORT_FAILED_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    // VERBATIM: 422 pada /export berarti EXPORT_FAILED, bukan akun tanpa mill
    // — satu kode HTTP yang sama membawa dua arti, dan pemanggillah yang
    // memutuskan. Menyeragamkannya akan menampilkan "hubungi Admin" untuk
    // ekspor yang terlalu besar.
    //
    // CATATAN LINTAS-LAPISAN: yang diuji di sini adalah kontrak VIEW-nya, dan
    // view memenuhinya — pesan yang DATANG dicetak apa adanya. Di browser
    // sungguhan pesan itu TIDAK sampai: /export memakai
    // `responseType: 'blob'`, jadi `error.response.data` adalah Blob dan
    // apiClient.normalizeError (yang membaca `data?.message`) jatuh ke pesan
    // cadangan generik dengan `status` 422 tetap utuh. Dibuktikan di
    // tests/e2e/laporan-engine-room.spec.ts, yang mengasersi perilaku
    // sebenarnya; perbaikannya milik apiClient dan berlaku bagi kedua belas
    // laporan mobile saudaranya sekaligus.
    expect(text(wrapper, 'error-message')).toBe(EXPORT_FAILED_ERROR.message)
    expect(exists(wrapper, 'mill-not-linked')).toBe(false)
    expect(exists(wrapper, 'retry-button')).toBe(false)
    // SATU permintaan: tidak ada upaya memecah rentang.
    expect(repoMocks.exportCsv).toHaveBeenCalledTimes(1)
    expect(repoMocks.saveCsvFile).not.toHaveBeenCalled()
  })

  it('401 pada ekspor mengarahkan ke Login, dan tidak ada berkas yang tersimpan', async () => {
    repoMocks.exportCsv.mockRejectedValueOnce(UNAUTHENTICATED_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(repoMocks.saveCsvFile).not.toHaveBeenCalled()
  })

  // skenario 26 + unit_test_case 73
  it('skenario 26: periode TERTUTUP — laporan penuh, badge Tertutup, ekspor tetap aktif', async () => {
    repoMocks.fetchSummary.mockImplementation((periodId: string) =>
      Promise.resolve(
        summaryFixture({
          period: {
            id: periodId,
            name: 'Periode Sebelumnya',
            start_date: PREV_START,
            end_date: PREV_END,
            status: 'closed',
          },
        }),
      ),
    )
    repoMocks.exportCsv.mockResolvedValue(new Blob(['a,b']))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-2')

    expect(text(wrapper, 'period-status-badge')).toContain('Tertutup')
    expect(cards(wrapper)).toHaveLength(22)
    expect(exists(wrapper, 'by-unit')).toBe(true)
    expect(exists(wrapper, 'daily-recap')).toBe(true)
    // Status periode mengatur PENULISAN data, bukan pembacaan laporan.
    expect(text(wrapper, 'period-meta')).toContain('mengatur penulisan data')
    expect(wrapper.get('[data-testid="export-button"]').attributes('disabled')).toBeUndefined()

    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    expect(repoMocks.exportCsv).toHaveBeenCalledTimes(1)
  })
})

/* ================================================================== */
/* Skenario 37 — rentang inklusif di kedua ujung                        */
/* ================================================================== */

describe('rentang periode inklusif', () => {
  it('skenario 37: kedua tanggal ujung hadir pada seri energi DAN rekap harian', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        daily_energy_export: {
          series: [
            { date: PERIOD_START, last_value: 3100, time_slot: '06:00', reading_count: 24 },
            { date: PERIOD_END, last_value: 3400, time_slot: '19:00', reading_count: 13 },
          ],
          has_data: true,
        },
        daily: [
          {
            date: PERIOD_START,
            reading_rows: 24,
            metrics_avg: { diesel_gen_1_load_kw: { avg: 700, reading_count: 24 } },
            energy_export: {
              last_value: 3100,
              date: PERIOD_START,
              time_slot: '06:00',
              reading_count: 24,
            },
          },
          {
            date: PERIOD_END,
            reading_rows: 13,
            metrics_avg: { diesel_gen_1_load_kw: { avg: 760, reading_count: 13 } },
            energy_export: {
              last_value: 3400,
              date: PERIOD_END,
              time_slot: '19:00',
              reading_count: 13,
            },
          },
        ],
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Penyaringannya di SERVER; klien tidak menyaring ulang, jadi kedua
    // record ujung ikut dirender.
    expect(
      wrapper
        .findAll('[data-testid="energy-export-row"]')
        .map((row) => row.attributes('data-date')),
    ).toEqual([PERIOD_START, PERIOD_END])
    expect(exists(wrapper, `daily-row-${PERIOD_START}`)).toBe(true)
    expect(exists(wrapper, `daily-row-${PERIOD_END}`)).toBe(true)
    // Tanggal DI LUAR rentang tidak pernah masuk ke DOM: layar tidak pernah
    // menerimanya.
    expect(exists(wrapper, `daily-row-${isoDaysFromNow(-31)}`)).toBe(false)
  })
})

/* ================================================================== */
/* Skenario 39 + 67 + 68 — LAYAR BACA SAJA                              */
/* ================================================================== */

describe('layar baca saja', () => {
  // skenario 39 + 68
  it('skenario 39/68: nol kontrol tulis, dan footer hanya Ekspor + Back', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(wrapper.findAll('form')).toHaveLength(0)
    expect(wrapper.findAll('input')).toHaveLength(0)
    expect(wrapper.findAll('textarea')).toHaveLength(0)

    const footerButtons = wrapper.findAll('.action-footer button')

    expect(footerButtons).toHaveLength(2)
    expect(footerButtons[0].text()).toContain('Ekspor')
    expect(footerButtons[1].text()).toContain('Back')

    // Tidak ada satu tombol tulis pun di seluruh layar.
    const labels = wrapper.findAll('button').map((button) => button.text())

    expect(labels.filter((label) => /Simpan|Hapus|Ubah|Verifikasi|Tambah/i.test(label))).toEqual([])
  })

  // skenario 35 + 39 + unit_test_case 35
  it('skenario 39: nol penulisan ke localStorage untuk HASIL laporan', async () => {
    const setItem = vi.spyOn(window.localStorage, 'setItem')

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Satu-satunya penulisan yang SAH adalah ingatan Production Line — yang
    // merupakan KONTEKS KERJA, bukan hasil laporan.
    const keys = setItem.mock.calls.map(([key]) => String(key))

    expect(keys.every((key) => key.startsWith('msl_production_line_'))).toBe(true)
    for (const [, value] of setItem.mock.calls) {
      expect(String(value)).not.toContain('coverage')
      expect(String(value)).not.toContain('metrics')
    }

    // Dan tidak satu pun angka laporan dapat sampai ke penyimpanan: layar ini
    // TIDAK mengimpor localDb sama sekali.
    const source = readFileSync(
      resolve(process.cwd(), 'src/views/LaporanEngineRoomView.vue'),
      'utf-8',
    )
    const code = source
      .replace(/\/\*[\s\S]*?\*\//g, '')
      .replace(/<!--[\s\S]*?-->/g, '')
      .replace(/^\s*\/\/.*$/gm, '')

    expect(code).not.toContain('localDb')
    expect(code).not.toContain('sqlite')
    expect(wrapper.html()).not.toContain('localDb')
  })
})

/* ================================================================== */
/* Skenario 29 — ROUTER                                                */
/* ================================================================== */

/**
 * DIVERGENSI SPEC — skenario 29 dan unit_test_cases 69-70 berbunyi "Router
 * menolak Mill Management dan Admin" dan "Router menerima Operator dan
 * Supervisor".
 *
 * ROUTER MOBILE TIDAK PUNYA PENJAGA PERAN SAMA SEKALI. mobile/src/router/
 * index.ts hanya memegang satu penjaga global atas `meta.public`, yakni
 * penjaga SESI — tidak satu rute pun membawa daftar peran, dan rute
 * `report-engine-room` tidak terkecuali (`meta: { public: false }`). Penolakan
 * Mill Management dan Admin terjadi DI SERVER dan dari ketiadaan jalur masuk
 * mobile bagi kedua peran itu; layar WEB screen-158 yang menjadi layar mereka.
 *
 * Yang diasersi di bawah karena itu adalah kontrak yang SEBENARNYA, bukan
 * penolakan yang tidak ada. Kalau penjaga peran kelak ditambahkan, test ini
 * akan memerah dan memaksa catatan ini diperbarui — itu lebih baik daripada
 * test hijau yang memalsukan penolakan.
 */
describe('router', () => {
  it('rute report-engine-room ADA, menuntut sesi, dan TIDAK membawa daftar peran', async () => {
    const source = readFileSync(resolve(process.cwd(), 'src/router/index.ts'), 'utf-8')
    const start = source.indexOf("name: 'report-engine-room'")

    expect(start).toBeGreaterThan(-1)

    const record = source.slice(Math.max(0, start - 400), start + 400)

    expect(record).toContain("path: '/reports/engine-room'")
    expect(record).toContain('LaporanEngineRoomView.vue')
    // Penjaga SESI, bukan penjaga peran.
    expect(record).toContain('meta: { public: false }')
    expect(record).not.toContain('roles')
    expect(record).not.toContain('allowedRoles')

    // Dan tidak satu rute pun di berkas itu memegang daftar peran — kalau
    // kelak ada, catatan DIVERGENSI di atas wajib diperbarui bersamanya.
    expect(source).not.toContain('allowedRoles')
  })

  it('komponen laporan termount bagi Operator maupun Supervisor tanpa cabang tampilan', async () => {
    for (const role of ['operator', 'supervisor'] as const) {
      asRole(role, 'bu-1')
      const wrapper = await mountView()
      await selectPeriod(wrapper, 'per-1')

      expect(exists(wrapper, 'laporan-engine-room-mobile'), role).toBe(true)
      expect(cards(wrapper).length, role).toBe(22)
      expect(exists(wrapper, 'mill-select'), role).toBe(false)
    }
  })

  it('Back kembali ke Dashboard & Reporting', async () => {
    const wrapper = await mountView()
    await wrapper.get('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'dashboard-reporting' })
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
   */
  it('setiap kelas yang dipakai markup punya definisi di blok <style> atau stylesheet bersama', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Dibaca dari DISK, bukan dari import.meta.url: di Vitest nilai itu
    // sebuah URL http:// dan fileURLToPath menolaknya. process.cwd() adalah
    // akar proyek mobile (root Vitest), dan itulah yang dipakai.
    const sources = [
      'src/views/LaporanEngineRoomView.vue',
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
})
