/**
 * engineRoomReportRepo.spec.ts — screen-159--laporan-engine-room-mobile /
 * usecase-165--laporan-engine-room-mobile.
 *
 * Bagian SISI KLIEN dari 73 `unit_test_cases` pada tech spec screen-159 yang
 * menyangkut BENTUK PERMINTAAN dan KESETIAAN TERUSAN (kasus 1-17, 36-37, dan
 * separuh repo dari 28-35, 38). Keputusan LAYAR atas jawaban repo ada di
 * LaporanEngineRoomView.spec.ts; kebenaran ANGKA-nya sudah dibuktikan
 * terhadap EngineRoomReportService di backend ketika screen-158 dibangun —
 * layar ini tidak menyentuh satu berkas backend pun.
 *
 * ────────────────────────────────────────────────────────────────────────
 * LIMA KLAIM YANG HANYA BERKAS INI DAPAT MENCATATNYA
 * ────────────────────────────────────────────────────────────────────────
 * 1. `business_unit_id` TIDAK PERNAH ADA DI KAWAT, apa pun yang diserahkan
 *    pemanggil. Perlindungannya STRUKTURAL — tidak ada satu pun jalur kode
 *    yang dapat mengirimkannya, bukan sebuah penjagaan yang menolaknya —
 *    jadi test inilah satu-satunya yang mencatat maksudnya. Dan rute
 *    /api/engine-room-reports/business-units/options tetap Admin-saja:
 *    menyerahkan daftar seluruh mill ke perangkat peran terikat-mill adalah
 *    justru kebocoran yang dihindari.
 *
 * 2. DUA PULUH DUA ENTRI `metrics`, MASING-MASING MEMBAWA `group`, dan
 *    `daily_energy_export_kwh` TIDAK ADA DI DALAMNYA. Kolom ukur ke-23 itu
 *    diterbitkan pada kunci TERPISAH `daily_energy_export` sebagai seri
 *    nilai TERAKHIR per tanggal beserta `time_slot` asalnya, tanpa rata-rata
 *    periode di mana pun pada payload.
 *
 * 3. KLIEN TIDAK MENENTUKAN SLOT MANA YANG 'TERAKHIR'. Server menentukannya
 *    menurut POSISI pada kisi 24 slot kanonis (07:00 .. 06:00 keesokan hari).
 *    Fixture di bawah SENGAJA memberi seri yang urutan slotnya BUKAN urutan
 *    leksikal: titik pertamanya ber-slot '06:00' (posisi TERAKHIR pada kisi,
 *    tetapi paling AWAL secara leksikal). Repo yang mengurutkan atau
 *    membandingkan jam akan membalik urutannya dan test ini gagal — dan
 *    audit statis di bawah memaku bahwa tidak ada sort/localeCompare atas
 *    slot waktu di berkas repo maupun berkas view.
 *
 * 4. `status_distribution` memuat DUA kolom bernilai TIGA (run/standby/off),
 *    DITERUSKAN UTUH, dan ia TIDAK membawa `share`. 'STANDBY' BUKAN 'OFF',
 *    dan `not_recorded` adalah kunci TERPISAH yang tidak pernah dilipat ke
 *    salah satu dari ketiganya.
 *
 * 5. TIDAK ADA `target`, TIDAK ADA `is_lab`, TIDAK ADA `shares_standard_with`,
 *    dan TIDAK ADA blok `downtime` — pada SETIAP metrik dan pada SELURUH
 *    ringkasan. Diasersi atas KETIADAAN MEDANNYA, bukan atas nilainya null:
 *    medan yang muncul tanpa disadari akan lolos dari asersi "nilainya null",
 *    dan layar akan menumbuhkan sel yang tidak mungkin diisi siapa pun.
 *    Menyalin tipe effluentPlantReportRepo.ts mentah-mentah membuat `is_lab`
 *    undefined di mana-mana tanpa satu galat TypeScript.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DIVERGENSI SPEC-LAWAN-PAYLOAD YANG DICATAT DI SINI
 * ────────────────────────────────────────────────────────────────────────
 * unit_test_case 7 berbunyi "tidak ada porsi yang dihitung di repo", dan
 * unit_test_case 51 menuntut porsi 15/20 DIRENDER. Keduanya benar sekaligus:
 * payload Engine Room TIDAK menerbitkan `share` sama sekali
 * (EngineRoomReportService::statusDistributionOf() menerbitkan `known_values`
 * + `counts` + `not_recorded` + `recorded_slots`), jadi porsinya dibentuk di
 * VIEW oleh formatShare() dengan rumus yang disalin persis dari helper $porsi
 * pada blade screen-158, dan PENYEBUTNYA `recorded_slots`. Yang diasersi di
 * sini adalah separuh repo-nya: keempat medan diteruskan apa adanya dan repo
 * TIDAK menumbuhkan `share` sendiri. Asersi angka porsinya ada di
 * LaporanEngineRoomView.spec.ts.
 *
 * Divergensi kedua: spec kasus 18-23 menamai `resolveProductionLine` sebagai
 * bagian "repo". Fungsi itu hidup di LaporanEngineRoomView.vue, bukan di
 * sini, dan diuji di spec komponen. Yang diasersi di berkas ini adalah
 * akibatnya pada kawat: nilai line yang kosong TIDAK PERNAH dikirim sebagai
 * parameter kosong.
 *
 * Divergensi ketiga: spec menamai `fetchProductionLines` sebagai bagian layar
 * ini. Layar memakai ULANG productionLineRepo.fetchProductionLinesForReport(null)
 * VERBATIM dari screen-135 — endpoint itu tidak diduplikasi di bawah prefix
 * engine-room-reports, dan repo ini SENGAJA tidak membungkusnya kedua kali.
 *
 * ────────────────────────────────────────────────────────────────────────
 * FIXTURE-NYA SENGAJA BERTENTANGAN DENGAN DIRINYA SENDIRI
 * ────────────────────────────────────────────────────────────────────────
 * Klaim pokok layar ini adalah "NOL perhitungan di klien", dan klaim itu
 * hanya dapat dibuktikan oleh payload yang MUSTAHIL dihasilkan perhitungan:
 *
 *   - coverage_percent 88.8 padahal 260/1440 = 18,1
 *   - diesel_gen_2_load_kw avg 40 di LUAR rentang min 10 .. max 20
 *   - kedua puluh dua reading_count BERBEDA-BEDA, dan seluruhnya lebih kecil
 *     daripada coverage.filled_slots 260
 *   - sum(counts) + not_recorded pada diesel_gen_1_status TIDAK sama dengan
 *     recorded_slots — invariannya milik server, dan repo tidak boleh
 *     "memperbaikinya"
 *   - daily_total.reading_rows 99 padahal baris hariannya 3 + 4
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

import engineRoomReportRepo, {
  exportCsv,
  fetchPeriods,
  fetchSummary,
  saveCsvFile,
  statusValueLabel,
  STATUS_VALUE_LABELS,
  type EngineRoomReportScope,
} from '@/services/engineRoomReportRepo'

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

/**
 * KEDUA PULUH DUA kolom ukur, dalam urutan
 * EngineRoomReportService::NUMERIC_METRICS.
 *
 * `daily_energy_export_kwh` TIDAK ADA DI DAFTAR INI, dan ketiadaannya adalah
 * bentuk payload-nya — bukan kelalaian fixture.
 */
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

/** Blok tata letak tiap kolom — dari EngineRoomReportService::METRIC_GROUPS. */
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

/** KEDUA PULUH DUA penyebut, SELURUHNYA BERBEDA — lihat docblock berkas. */
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
  // KOLOM YANG TIDAK PERNAH TERISI — penyebut 0, seluruh angkanya null.
  electrical_sync_power_factor: 0,
  electrical_sync_busbar_voltage_v: 238,
  air_compressor_1_pressure_bar: 120,
  compressor_2_pressure_bar: 119,
  battery_charger_ups_voltage_v: 60,
  fuel_tank_level: 59,
}

/**
 * Cakupan yang membawa medan yang TIDAK ADA pada tipe EngineRoomReportScope.
 * Dipakai untuk membuktikan bahwa medan itu tidak punya jalur apa pun ke
 * kawat — bukan bahwa tipe menolaknya (tipe memang menolaknya, dan itu bukan
 * yang diuji di sini: tipe tidak berlaku pada JavaScript yang sudah
 * terkompilasi).
 */
function scopeWithForbiddenFields(productionLineId: string | null): EngineRoomReportScope {
  return {
    productionLineId,
    businessUnitId: 'bu-9',
    business_unit_id: 'bu-9',
    isAdmin: true,
  } as unknown as EngineRoomReportScope
}

/**
 * Satu entri metrics[kolom].
 *
 * PERHATIKAN APA YANG TIDAK ADA: tidak ada `target`, tidak ada `is_lab`,
 * tidak ada `label`, tidak ada `unit`, tidak ada severity /
 * is_out_of_range / flag. Payload Engine Room memang tidak menerbitkan satu
 * pun di antaranya, dan stub yang menambahkannya akan menyamarkan repo yang
 * membacanya dari tempat yang tidak ada. `group` ADA, karena view membangun
 * keenam bloknya dari medan itu.
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
    group: METRIC_GROUPS[column] ?? 'auxiliary',
  }
}

function metrics(): Record<string, Record<string, unknown>> {
  return {
    steam_turbine_inlet_pressure_bar: metric('steam_turbine_inlet_pressure_bar', 18.2, 21.4, 24.9),
    steam_turbine_inlet_temp_c: metric('steam_turbine_inlet_temp_c', 280, 305, 330),
    steam_turbine_exhaust_pressure_bar: metric('steam_turbine_exhaust_pressure_bar', 0.12, 0.31, 0.58),
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
    // GENSET 1 BERAT, GENSET 2 HAMPIR MENGANGGUR — ketidakseimbangan beban
    // yang merupakan TEMUAN, bukan galat, dan yang tidak boleh dirata-ratakan
    // maupun diselisihkan di klien.
    diesel_gen_1_load_kw: metric('diesel_gen_1_load_kw', 700, 800, 900),
    diesel_gen_1_amperage_a: metric('diesel_gen_1_amperage_a', 1100, 1250, 1400),
    diesel_gen_1_jacket_water_temp_c: metric('diesel_gen_1_jacket_water_temp_c', 72.1, 81.6, 92.4),
    diesel_gen_1_lube_oil_pressure_bar: metric('diesel_gen_1_lube_oil_pressure_bar', 3.1, 4.05, 4.9),
    // avg DI LUAR rentang min..max — mustahil dari perhitungan apa pun, dan
    // itulah yang membuat "nol aritmetika di klien" dapat dibuktikan.
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
    // Frekuensi 43 Hz, power factor, dan tegangan busbar 310 V punya batas
    // yang dikenal sangat luas DI LUAR aplikasi ini — dan payload-nya tetap
    // TIDAK menerbitkan satu pun penandaan.
    electrical_sync_system_frequency_hz: metric('electrical_sync_system_frequency_hz', 43, 49.82, 51.1),
    electrical_sync_power_factor: metric('electrical_sync_power_factor', null, null, null),
    electrical_sync_busbar_voltage_v: metric('electrical_sync_busbar_voltage_v', 310, 388, 415),
    air_compressor_1_pressure_bar: metric('air_compressor_1_pressure_bar', 5.2, 6.4, 7.1),
    compressor_2_pressure_bar: metric('compressor_2_pressure_bar', 4.9, 6.1, 6.8),
    battery_charger_ups_voltage_v: metric('battery_charger_ups_voltage_v', 22.4, 24.1, 26.8),
    fuel_tank_level: metric('fuel_tank_level', 18, 54, 92),
  }
}

/**
 * SERI EKSPOR ENERGI YANG URUTANNYA BUKAN URUTAN LEKSIKAL SLOT.
 *
 * Kisi kanonis layar input berjalan 07:00 .. 06:00 KEESOKAN HARI, jadi
 * '06:00' adalah posisi TERAKHIR — sementara urutan leksikal 'HH:MM'
 * memilihnya sebagai yang paling AWAL. Titik pertama di bawah memakai slot
 * itu dengan sengaja:
 *
 *   server : ['06:00', '23:00', '13:00']
 *   leksikal: ['06:00', '13:00', '23:00']   <- berbeda
 *   nilai   : [4100, 3900, 4250]            <- juga bukan urutan nilai
 *
 * Repo (atau view) yang mengurutkan, memaksimumkan, atau membandingkan jam
 * akan menghasilkan urutan yang berbeda, dan test urutan di bawah gagal.
 * Nilai-nilainya pun sengaja TIDAK monoton, supaya "urut menurut nilai" juga
 * tidak dapat lolos.
 */
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
 * TIDAK ADA `share` DI SINI, dan itu bukan kelalaian stub: payload memang
 * tidak menerbitkannya (lihat blok DIVERGENSI pada docblock berkas).
 *
 * Genset 1: `standby` ABSEN dari `counts` sementara tetap ada di
 * `known_values` — view yang mengiterasi kunci counts akan menghilangkan
 * barisnya. Dan sum(counts) + not_recorded SENGAJA tidak sama dengan
 * recorded_slots: invariannya milik server, dan repo tidak boleh
 * "memperbaikinya".
 *
 * Genset 2: ketiga nilai HADIR, `standby` 6 dan `off` 4 sebagai dua angka
 * yang BERBEDA — melipat keduanya menghasilkan 10, dan 10 tidak muncul di
 * mana pun pada fixture ini.
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

function unitMetrics(avg: number | null): Record<string, unknown> {
  return Object.fromEntries(
    METRIC_COLUMNS.map((column) => [column, metric(column, avg, avg, avg)]),
  )
}

/** Payload ringkasan yang sengaja tidak konsisten — lihat docblock berkas. */
function summaryPayload(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Sungai Rambutan' },
    production_line: { id: 'pl-1', name: 'Line Satu' },
    period: {
      id: 'per-1',
      name: 'Periode Uji Engine Room',
      start_date: PERIOD_START,
      end_date: PERIOD_END,
      status: 'open',
      // Payload /summary MEMANG memuat medan ini (lihat
      // EngineRoomReportService::buildSummary). Ia sengaja tidak ditulis pada
      // tipe EngineRoomReportPeriodHeader: nama mill dibaca view dari blok
      // `business_unit`, satu sumber saja. Yang diasersi di bawah adalah
      // bahwa repo TIDAK menyentuhnya — bukan bahwa repo membuangnya.
      business_unit_name: 'Mill Sungai Rambutan',
    },
    has_data: true,
    coverage: {
      filled_slots: 260,
      expected_slots: 1440,
      // 260/1440 = 18,1 — dan server mengirim 88,8.
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
    /**
     * TIGA KEJADIAN BERTEKS IDENTIK pada tiga slot berbeda. Repo yang
     * mengelompokkan menurut teks menjawab SATU entri dan test ini gagal.
     */
    maintenance_remarks: [
      { date: DAY_A, time_slot: '07:00', text: 'Ganti filter lube oil genset 1' },
      { date: DAY_A, time_slot: '13:00', text: 'Ganti filter lube oil genset 1' },
      { date: DAY_B, time_slot: '21:00', text: 'Ganti filter lube oil genset 1' },
    ],
    /**
     * DAFTAR TERPISAH, dan entri PERTAMA-nya memakai date + time_slot yang
     * SAMA dengan entri pertama maintenance_remarks: satu slot yang mengisi
     * kedua kolom muncul pada KEDUA daftar, dan itu benar.
     */
    findings: [
      { date: DAY_A, time_slot: '07:00', text: 'Getaran tidak wajar pada bearing alternator 2' },
      { date: DAY_B, time_slot: '03:00', text: 'Tegangan busbar turun saat genset 2 start' },
    ],
    daily_trend: {
      electrical_sync_total_factory_load_kw: [
        { date: DAY_A, avg: 1180, reading_count: 24 },
        // null — BUKAN 0 — pada tanggal yang tidak sekali pun mencatatnya.
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
        metrics: unitMetrics(7.5),
        energy_export: { last_value: 4100, date: DAY_A, time_slot: '06:00', reading_count: 24 },
      },
      {
        // Unit yang punya record tetapi TIDAK SATU PUN pembacaan — tetap
        // muncul, karena membuangnya justru menyembunyikan unit yang tidak
        // pernah dituliskan.
        engine_room_id: 'ER-2',
        reading_rows: 0,
        reading_count: 0,
        days_with_rows: 0,
        metrics: unitMetrics(null),
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
        // DI SAMPING metrics_avg, bukan di dalamnya: sel energi baris ini
        // memuat nilai TERAKHIR tanggal itu sementara setiap sel lain pada
        // baris yang sama memuat rata-rata.
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
    // 3 + 4 = 7, dan server mengirim 99.
    daily_total: { reading_rows: 99 },
    total: { days_with_records: 2, reading_rows: 260 },
    /**
     * Kunci PENJELASAN yang benar-benar dikirim server
     * (EngineRoomReportService::buildSummary menutup payload dengan medan
     * ini). fetchSummary membangun objeknya dari daftar kunci yang DITULIS,
     * jadi medan ini WAJIB tidak sampai ke pemanggil — dan asersi di bawah
     * memakunya, karena medan tak terduga yang lolos akan ikut terbaca pada
     * setiap asersi JSON.stringify di berkas ini.
     */
    _note_absences: 'TIDAK ADA kunci downtime, target, maupun penanda di luar batas.',
    ...overrides,
  }
}

beforeEach(() => {
  getMock.mockReset()
})

/* ================================================================== */
/* business_unit_id — TIDAK PERNAH ADA DI KAWAT                        */
/* ================================================================== */

describe('business_unit_id tidak pernah dikirim', () => {
  // unit_test_case 2
  it('fetchPeriods memanggil endpoint TANPA argumen kedua, apa pun yang diserahkan pemanggil', async () => {
    getMock.mockResolvedValue({ data: { data: [] } })

    // Pemanggil yang menyerahkan cakupan ala kesepuluh repo pertama. Di
    // JavaScript yang sudah terkompilasi tipe tidak berlaku, jadi inilah
    // bentuk pemanggilan yang benar-benar mungkin terjadi.
    const fetchPeriodsLoose = fetchPeriods as unknown as (scope?: unknown) => Promise<unknown>

    for (const scope of [
      undefined,
      {},
      { isAdmin: true, businessUnitId: 'bu-9' },
      { business_unit_id: 'bu-9' },
      { productionLineId: 'pl-1', businessUnitId: 'bu-9' },
    ]) {
      getMock.mockClear()
      await fetchPeriodsLoose(scope)

      expect(getMock.mock.calls[0][0]).toBe('/api/engine-room-reports/periods')
      // TIDAK ADA objek konfigurasi sama sekali — bukan `{ params: {} }`.
      // Periode adalah milik MILL, dan mill diselesaikan server dari akun.
      expect(getMock.mock.calls[0][1]).toBeUndefined()
    }
  })

  // unit_test_case 5
  it('fetchSummary mengirim TEPAT period_id + production_line_id, tanpa medan mill', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    await fetchSummary('per-1', scopeWithForbiddenFields('pl-1'))

    // toEqual, bukan toMatchObject: kunci yang BERTAMBAH harus terlihat
    // sebagai kegagalan. Perlindungannya struktural (tidak ada jalurnya),
    // bukan penjagaan — jadi test inilah yang mencatat maksudnya.
    expect(getMock.mock.calls[0][1].params).toEqual({
      period_id: 'per-1',
      production_line_id: 'pl-1',
    })
    expect(Object.keys(getMock.mock.calls[0][1].params)).not.toContain('business_unit_id')
  })

  it('exportCsv mengirim TEPAT period_id + format + production_line_id, tanpa medan mill', async () => {
    getMock.mockResolvedValue({ data: new Blob(['a,b']) })

    await exportCsv('per-1', scopeWithForbiddenFields('pl-1'))

    expect(getMock.mock.calls[0][1].params).toEqual({
      period_id: 'per-1',
      format: 'csv',
      production_line_id: 'pl-1',
    })
  })

  it('tidak ada satu pun pemanggilan ke /business-units/options dari modul ini', async () => {
    getMock.mockResolvedValue({ data: { data: [] } })

    await fetchPeriods()
    getMock.mockResolvedValue({ data: summaryPayload() })
    await fetchSummary('per-1', { productionLineId: 'pl-1' })
    getMock.mockResolvedValue({ data: new Blob(['a']) })
    await exportCsv('per-1', { productionLineId: 'pl-1' })

    const urls = getMock.mock.calls.map((call) => String(call[0]))

    // Rute itu Admin saja — 403 untuk Operator, Supervisor, DAN Mill
    // Management, diangkat dari DALAM service dan karena itu tidak terbuka
    // oleh daftar peran middleware. Menyerahkan daftar seluruh mill kepada
    // peran terikat-mill adalah justru kebocoran yang dihindari.
    expect(urls.some((url) => url.includes('business-unit'))).toBe(false)
    expect(urls).toEqual([
      '/api/engine-room-reports/periods',
      '/api/engine-room-reports/summary',
      '/api/engine-room-reports/export',
    ])
  })

  it('modul tidak mengekspor fungsi pengambil daftar mill sama sekali', async () => {
    const module = await import('@/services/engineRoomReportRepo')

    // Ketiadaan FUNGSINYA, bukan ketiadaan pemanggilannya: fungsi yang ada
    // akan dipakai seseorang.
    expect(Object.keys(module)).not.toContain('fetchBusinessUnits')
    expect(Object.keys(engineRoomReportRepo)).not.toContain('fetchBusinessUnits')
  })
})

/* ================================================================== */
/* production_line_id                                                  */
/* ================================================================== */

describe('productionLineParams', () => {
  // unit_test_case 3
  it('dikirim ke summary dan export, TIDAK ke periods', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })
    await fetchSummary('per-1', { productionLineId: 'pl-1' })
    expect(getMock.mock.calls[0][1].params).toMatchObject({ production_line_id: 'pl-1' })

    getMock.mockReset()
    getMock.mockResolvedValue({ data: new Blob(['a']) })
    await exportCsv('per-1', { productionLineId: 'pl-1' })
    expect(getMock.mock.calls[0][1].params).toMatchObject({ production_line_id: 'pl-1' })

    getMock.mockReset()
    getMock.mockResolvedValue({ data: { data: [] } })
    await fetchPeriods()
    // Periode adalah milik MILL, bukan milik Production Line — menyaringnya
    // per line akan mengarang penyempitan yang tidak ada di data, dan backend
    // pun tidak menerima parameter ini di sana.
    expect(getMock.mock.calls[0][1]).toBeUndefined()
  })

  it('tidak mengirim parameter kosong ketika line belum berlaku', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    await fetchSummary('per-1', { productionLineId: null })

    // Server akan menjawab 422 tentang parameter yang HILANG, dan itu pesan
    // yang benar — bukan 422 tentang nilai kosong. Jalur pertahanan PERTAMA
    // tetap penjaga di loadSummary(), yang diuji di spec komponen.
    expect(getMock.mock.calls[0][1].params).toEqual({ period_id: 'per-1' })
  })

  it('cakupan yang tidak diberikan sama sekali tetap menghasilkan permintaan yang sah', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    await fetchSummary('per-1')

    expect(getMock.mock.calls[0][1].params).toEqual({ period_id: 'per-1' })
  })
})

/* ================================================================== */
/* Pembungkus respons                                                  */
/* ================================================================== */

describe('unwrap', () => {
  // unit_test_case 9
  it('menerima respons dengan maupun tanpa pembungkus data', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })
    const flat = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    getMock.mockReset()
    getMock.mockResolvedValue({ data: { data: summaryPayload() } })
    const wrapped = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(wrapped).toEqual(flat)
  })

  // unit_test_case 10
  it('payload yang tidak dikenali (string/angka) menghasilkan blok bawaan, bukan lemparan', async () => {
    for (const payload of ['bukan objek', 42, true]) {
      getMock.mockReset()
      getMock.mockResolvedValue({ data: payload })

      const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

      expect(summary.has_data).toBe(false)
      expect(summary.metrics).toEqual({})
    }
  })

  // unit_test_case 11
  it('respons kosong tidak melempar dan menghasilkan blok bawaan nol-aman', async () => {
    getMock.mockResolvedValue({ data: null })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.has_data).toBe(false)
    expect(summary.metrics).toEqual({})
    expect(summary.status_distribution).toEqual({})
    expect(summary.maintenance_remarks).toEqual([])
    expect(summary.findings).toEqual([])
    expect(summary.by_unit).toEqual([])
    expect(summary.daily).toEqual([])
    // null, BUKAN 0 — bentuk bawaan yang berbohong lebih buruk daripada
    // bentuk bawaan yang kosong.
    expect(summary.coverage.coverage_percent).toBeNull()
    expect(summary.coverage.filled_slots).toBe(0)
    expect(summary.coverage.expected_slots).toBe(0)
    expect(summary.coverage.engine_room_unit_count).toBe(0)
    expect(summary.coverage.slots_per_unit_per_day).toBe(0)
    expect(summary.coverage.period_running).toBe(false)
    expect(summary.daily_energy_export).toEqual({ series: [], has_data: false })
    expect(summary.daily_trend).toEqual({ electrical_sync_total_factory_load_kw: [] })
    expect(summary.daily_total).toEqual({ reading_rows: 0 })
    expect(summary.total).toEqual({ days_with_records: 0, reading_rows: 0 })
  })
})

/* ================================================================== */
/* Kesetiaan terusan — NOL perhitungan di klien                        */
/* ================================================================== */

describe('nol perhitungan di klien', () => {
  // unit_test_case 7
  it('meneruskan coverage apa adanya, tanpa menurunkan satu angka pun', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // 260/1440 = 18,1 dan server mengirim 88,8. Repo yang menghitung sendiri
    // menjawab 18,1 dan test ini gagal.
    expect(summary.coverage.coverage_percent).toBe(88.8)
    expect(summary.coverage.filled_slots).toBe(260)
    // TIDAK diturunkan dari 2 × 30 × 24 meski ketiga angkanya tersedia.
    expect(summary.coverage.expected_slots).toBe(1440)
    expect(summary.coverage.engine_room_unit_count).toBe(2)
    expect(summary.coverage.slots_per_unit_per_day).toBe(24)
    expect(summary.coverage.days_counted).toBe(30)
    expect(summary.coverage.days_in_period).toBe(30)
    expect(summary.coverage.period_running).toBe(false)
  })

  it('meneruskan min/avg/max apa adanya walau avg di luar rentang min..max', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.metrics.diesel_gen_1_load_kw.min).toBe(700)
    expect(summary.metrics.diesel_gen_1_load_kw.avg).toBe(800)
    expect(summary.metrics.diesel_gen_1_load_kw.max).toBe(900)

    // Tidak ada (min+max)/2 maupun turunan lain: 40 berada di LUAR rentang
    // 10..20, jadi repo yang menghitung sendiri menjawab 15.
    expect(summary.metrics.diesel_gen_2_load_kw.min).toBe(10)
    expect(summary.metrics.diesel_gen_2_load_kw.avg).toBe(40)
    expect(summary.metrics.diesel_gen_2_load_kw.max).toBe(20)
  })

  // unit_test_case 6
  it('KEDUA PULUH DUA entri metrik selalu ada, dalam urutan payload, termasuk yang kosong', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(Object.keys(summary.metrics)).toEqual(METRIC_COLUMNS)
    expect(Object.keys(summary.metrics)).toHaveLength(22)

    // Kolom yang tidak pernah terisi TIDAK disaring keluar: null tetap null,
    // dan penyebutnya 0 — bukan dihapus dan bukan dikoersi menjadi 0/0/0.
    expect(summary.metrics.electrical_sync_power_factor.min).toBeNull()
    expect(summary.metrics.electrical_sync_power_factor.avg).toBeNull()
    expect(summary.metrics.electrical_sync_power_factor.max).toBeNull()
    expect(summary.metrics.electrical_sync_power_factor.reading_count).toBe(0)
  })

  // unit_test_case 6 (separuh kedua): kolom ke-23 BUKAN bagian `metrics`
  it('daily_energy_export_kwh TIDAK ADA di dalam metrics — ia kunci TERPISAH', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Nama kolomnya menyebut 'daily' sementara ia disimpan per slot waktu,
    // dan semantiknya belum terjelaskan: karena itu ia tidak punya rata-rata
    // periode di mana pun pada payload, dan menaruhnya di `metrics` akan
    // menumbuhkan sel rata-rata yang tidak pernah bisa benar.
    expect(summary.metrics).not.toHaveProperty('daily_energy_export_kwh')
    expect(summary.daily_energy_export.has_data).toBe(true)
    expect(summary.daily_energy_export.series).toHaveLength(3)
  })

  // unit_test_case 43 (separuh repo)
  it('KEDUA PULUH DUA penyebut diteruskan apa adanya, dan seluruhnya BERBEDA', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(METRIC_COLUMNS.map((column) => summary.metrics[column].reading_count)).toEqual(
      METRIC_COLUMNS.map((column) => READING_COUNTS[column]),
    )

    // Tiap kolom punya penyebutnya SENDIRI: dua puluh dua kolom nullable yang
    // terisi saling bebas, jadi satu penyebut bersama akan salah untuk
    // setidaknya dua puluh satu di antaranya — dan di ponsel cacat itu LEBIH
    // sulit terlihat karena kartunya tidak pernah berdampingan.
    expect(
      new Set(METRIC_COLUMNS.map((column) => summary.metrics[column].reading_count)).size,
    ).toBe(22)
  })

  it('coverage.filled_slots BOLEH melampaui setiap penyebut kartu, dan itu benar', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Slot dihitung terisi bila salah satu dari DUA PULUH TUJUH kolom bacaan
    // terisi, dan empat di antaranya (dua kolom status, dua kolom teks) bukan
    // kolom ukur. Yang dilampaui adalah penyebut KARTU, bukan penyebut
    // CAKUPAN — bukan ketidaksesuaian yang perlu "diperbaiki".
    for (const column of METRIC_COLUMNS) {
      expect(summary.metrics[column].reading_count, column).toBeLessThanOrEqual(
        summary.coverage.filled_slots,
      )
    }
    expect(summary.coverage.filled_slots).toBe(260)
  })

  // unit_test_case 8
  it("satuan 'C' TIDAK dipetakan menjadi '°C' — dan repo tidak punya peta satuan sama sekali", async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        metrics: {
          ...metrics(),
          // Server MENERBITKAN unit di sini (biasanya tidak), dan nilainya
          // 'C'. Repo yang mempercantiknya menjadi '°C' membuat ponsel
          // berselisih dengan laporan web yang mencetak medan yang sama apa
          // adanya.
          steam_turbine_inlet_temp_c: {
            ...metric('steam_turbine_inlet_temp_c', 280, 305, 330),
            unit: 'C',
          },
        },
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.metrics.steam_turbine_inlet_temp_c.unit).toBe('C')
    expect(JSON.stringify(summary)).not.toContain('°C')
  })

  it('label dari server MENANG dan diteruskan apa adanya', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        metrics: {
          ...metrics(),
          fuel_tank_level: {
            ...metric('fuel_tank_level', 18, 54, 92),
            label: 'Level Tangki dari Server',
            unit: '%',
          },
        },
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Keduanya medan OPSIONAL pada tipe justru supaya nilai server, bila
    // kelak diterbitkan, menang atas tabel label view.
    expect(summary.metrics.fuel_tank_level.label).toBe('Level Tangki dari Server')
    expect(summary.metrics.fuel_tank_level.unit).toBe('%')
  })

  it('meneruskan daily_total dan total apa adanya, tanpa menurunkannya dari daily', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // 3 + 4 = 7, dan server mengirim 99.
    expect(summary.daily_total.reading_rows).toBe(99)
    expect(summary.daily[0].reading_rows).toBe(3)
    expect(summary.daily[1].reading_rows).toBe(4)
    expect(summary.total).toEqual({ days_with_records: 2, reading_rows: 260 })

    // `metrics_avg` dengan bentuk {avg, reading_count}, dan `energy_export` DI
    // SAMPINGNYA — bukan di dalamnya.
    expect(summary.daily[0].metrics_avg.diesel_gen_1_load_kw).toEqual({
      avg: 790,
      reading_count: 3,
    })
    expect(summary.daily[0].metrics_avg.electrical_sync_power_factor.avg).toBeNull()
    expect(summary.daily[0].energy_export).toEqual({
      last_value: 4100,
      date: DAY_A,
      time_slot: '06:00',
      reading_count: 24,
    })
    expect(summary.daily[0].metrics_avg).not.toHaveProperty('energy_export')
    expect(summary.daily[0].metrics_avg).not.toHaveProperty('daily_energy_export_kwh')
  })

  it('null tetap null, tidak pernah menjadi 0', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.metrics.electrical_sync_power_factor.avg).toBeNull()
    expect(summary.by_unit[1].metrics.diesel_gen_1_load_kw.avg).toBeNull()
    expect(summary.by_unit[1].energy_export.last_value).toBeNull()
    expect(summary.by_unit[1].energy_export.time_slot).toBeNull()
    expect(summary.daily[0].metrics_avg.electrical_sync_power_factor.avg).toBeNull()
    // Titik tren tanpa pembacaan: null, BUKAN 0 — batang nol terbaca sebagai
    // hasil pengukuran yang memang nol.
    expect(summary.daily_trend.electrical_sync_total_factory_load_kw[1].avg).toBeNull()
    expect(summary.daily_trend.electrical_sync_total_factory_load_kw[1].reading_count).toBe(0)
  })

  it('coverage_percent 0 diteruskan 0, bukan diganti null', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
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
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.coverage.coverage_percent).toBe(0)
    expect(summary.coverage.expected_slots).toBe(1440)
  })

  it('coverage_percent 0.0 pada penyebut NOL pun diteruskan 0, bukan diubah menjadi null', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          // INI YANG DIKIRIM server Engine Room pada keadaan penyebut-nol
          // (lihat EngineRoomReportService::buildSummary) — 0.0, BUKAN null.
          // Repo meneruskannya apa adanya, dan VIEW yang memutuskan tanda
          // pisah dari expected_slots === 0. Mengubahnya menjadi null di sini
          // akan memindahkan keputusan itu ke tempat yang salah.
          coverage_percent: 0,
          engine_room_unit_count: 0,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 0,
          period_running: true,
        },
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.coverage.coverage_percent).toBe(0)
    expect(summary.coverage.expected_slots).toBe(0)
    // Server menandai periode yang BELUM MULAI sebagai masih berjalan juga —
    // keduanya hadir sekaligus, dan view WAJIB memeriksa days_counted lebih
    // dulu.
    expect(summary.coverage.days_counted).toBe(0)
    expect(summary.coverage.period_running).toBe(true)
  })

  it('coverage_percent null tetap null — tipe menerima keduanya', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          coverage_percent: null,
          engine_room_unit_count: 0,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 0,
          period_running: true,
        },
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.coverage.coverage_percent).toBeNull()
  })
})

/* ================================================================== */
/* `group` — BLOK TATA LETAK DARI RESPONS                              */
/* ================================================================== */

describe('field group', () => {
  // unit_test_case 6 + 41 (separuh repo)
  it('setiap metrik membawa group-nya, dan keenam nilainya adalah keenam blok layar', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    for (const column of METRIC_COLUMNS) {
      expect(summary.metrics[column].group, column).toBe(METRIC_GROUPS[column])
    }

    // ENAM nilai, dalam urutan kemunculan pertama pada respons — urutan itulah
    // yang menjadi urutan blok di layar.
    expect([...new Set(METRIC_COLUMNS.map((column) => summary.metrics[column].group))]).toEqual([
      'steam_turbine',
      'diesel_gen_1',
      'diesel_gen_2',
      'electrical_sync',
      'compressor',
      'auxiliary',
    ])
  })

  it('group diteruskan apa adanya, termasuk nilai yang belum dikenal layar', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        metrics: {
          ...metrics(),
          // Kolom BARU dari migrasi kelak, dengan group yang belum dikenal.
          // Repo tidak boleh memaksanya ke salah satu dari keenam nilai —
          // memaksanya berarti menaruh kolom di blok yang salah, dan blok yang
          // salah lebih buruk daripada kolom yang belum punya blok.
          future_sensor_reading: {
            min: 1,
            avg: 2,
            max: 3,
            reading_count: 4,
            group: 'future_block',
          },
        },
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.metrics.future_sensor_reading.group).toBe('future_block')
    expect(Object.keys(summary.metrics)).toHaveLength(23)
  })

  it('group per unit berbentuk SAMA dengan group tingkat periode', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    for (const row of summary.by_unit) {
      for (const column of METRIC_COLUMNS) {
        expect(row.metrics[column].group, `${row.engine_room_id}.${column}`).toBe(
          METRIC_GROUPS[column],
        )
      }
    }
  })
})

/* ================================================================== */
/* SERI EKSPOR ENERGI — klien TIDAK memilih slot 'terakhir'             */
/* ================================================================== */

describe('seri ekspor energi harian', () => {
  // unit_test_case 12
  it('setiap titik membawa last_value, time_slot asalnya, dan penyebutnya', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // time_slot ikut diterbitkan JUSTRU supaya pembaca dapat MEMERIKSA nilai
    // mana yang dipakai — repo yang membuangnya menghilangkan satu-satunya
    // cara memeriksanya.
    expect(summary.daily_energy_export.series).toEqual([
      { date: DAY_A, last_value: 4100, time_slot: '06:00', reading_count: 24 },
      { date: DAY_B, last_value: 3900, time_slot: '23:00', reading_count: 17 },
      { date: DAY_C, last_value: 4250, time_slot: '13:00', reading_count: 9 },
    ])
  })

  // unit_test_case 13 — INI fixture yang sebenarnya menjatuhkan cacatnya
  it('TIDAK mengurutkan ulang seri: urutan kisi kanonis server dipertahankan', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    const slots = summary.daily_energy_export.series.map((point) => point.time_slot)

    // URUTAN SERVER: 06:00 lebih dulu, karena pada kisi 07:00..06:00 ia
    // posisi TERAKHIR hari sebelumnya — sementara urutan LEKSIKAL 'HH:MM'
    // memilihnya sebagai yang paling awal dan hasilnya terlihat sepenuhnya
    // masuk akal. Repo yang mengurutkan slot akan menjawab urutan leksikal.
    expect(slots).toEqual(['06:00', '23:00', '13:00'])
    expect(slots).not.toEqual([...slots].sort())

    // Dan bukan urut menurut NILAI pula — "urut menurut besar" tidak boleh
    // dapat lolos sebagai "kebetulan sudah urut".
    const values = summary.daily_energy_export.series.map((point) => point.last_value as number)

    expect(values).toEqual([4100, 3900, 4250])
    expect(values).not.toEqual([...values].sort((left, right) => left - right))
  })

  it('TIDAK ada rata-rata periode untuk kolom energi di mana pun pada payload', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Diasersi atas KETIADAAN MEDANNYA: medan yang muncul tanpa disadari akan
    // lolos dari asersi "nilainya null", dan layar akan menumbuhkan sel
    // rata-rata yang tidak pernah bisa benar.
    const energy = summary.daily_energy_export as unknown as Record<string, unknown>

    expect(Object.keys(energy).sort()).toEqual(['has_data', 'series'])
    for (const forbidden of ['avg', 'min', 'max', 'reading_count', 'last_value', 'time_slot']) {
      expect(energy, forbidden).not.toHaveProperty(forbidden)
    }
    expect(summary.metrics).not.toHaveProperty('daily_energy_export_kwh')
  })

  it('nilai energi per bucket berbentuk {last_value, date, time_slot, reading_count}', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    for (const bucket of [summary.by_unit[0].energy_export, summary.daily[0].energy_export]) {
      expect(Object.keys(bucket as unknown as Record<string, unknown>).sort()).toEqual([
        'date',
        'last_value',
        'reading_count',
        'time_slot',
      ])
      // Kolom ini tidak pernah dirata-rata, di bucket mana pun.
      expect(bucket as unknown as Record<string, unknown>).not.toHaveProperty('avg')
    }
  })

  it('has_data false dengan series kosong diteruskan apa adanya, bukan diisi nol', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({ daily_energy_export: { series: [], has_data: false } }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.daily_energy_export).toEqual({ series: [], has_data: false })
  })

  it('nilai yang TURUN dibanding slot sebelumnya diteruskan apa adanya, tanpa dikoreksi', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        daily_energy_export: {
          series: [
            { date: DAY_A, last_value: 4100, time_slot: '06:00', reading_count: 24 },
            // TURUN — dan diteruskan apa adanya: tidak ada penanda anomali,
            // tidak ada koreksi, tidak ada pengurutan ulang.
            { date: DAY_B, last_value: 120, time_slot: '22:00', reading_count: 20 },
          ],
          has_data: true,
        },
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.daily_energy_export.series[1].last_value).toBe(120)
    expect(summary.daily_energy_export.series[1].time_slot).toBe('22:00')
    const flat = JSON.stringify(summary)

    for (const forbidden of ['anomaly', 'anomali', 'corrected', 'koreksi', 'suspicious']) {
      expect(flat, forbidden).not.toContain(forbidden)
    }
  })

  /**
   * AUDIT STATIS — satu-satunya cara memaku "tidak ada sort, max, maupun
   * perbandingan jam" di kedua berkas layar ini.
   *
   * Payload hanya memuat slot yang SUDAH DIPILIH server, jadi tidak ada
   * fixture yang dapat memperlihatkan klien memilih slot yang salah: yang
   * dapat diperlihatkan hanyalah bahwa kodenya tidak punya operasi untuk
   * memilihnya. Math.min/Math.max pada view SAH — keduanya skala tampilan
   * batang tren atas `daily_trend[].avg`, dan tidak satu pun menyentuh seri
   * energi; asersi di bawah karena itu dilingkup ke BARIS yang menyebut slot
   * waktu atau seri energi.
   */
  it('AUDIT STATIS: tidak ada sort/max/perbandingan jam atas slot waktu di repo maupun view', () => {
    const files = [
      'src/services/engineRoomReportRepo.ts',
      'src/views/LaporanEngineRoomView.vue',
    ]

    for (const relative of files) {
      const source = readFileSync(resolve(process.cwd(), relative), 'utf-8')
      // Komentar dibuang lebih dulu: kedua berkas MENJELASKAN panjang-panjang
      // mengapa pengurutan slot dilarang, jadi penyisiran atas sumber mentah
      // akan selalu memerah pada kalimat yang justru melarangnya.
      const code = source
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/^\s*\/\/.*$/gm, '')

      const offending = code
        .split('\n')
        .filter(
          (line) =>
            /time_slot|energySeries|energy_export|daily_energy_export/.test(line) &&
            /\.sort\(|localeCompare|Math\.max|Math\.min|\.reverse\(/.test(line),
        )

      expect(offending, `${relative}: ${offending.join(' | ')}`).toEqual([])
    }
  })
})

/* ================================================================== */
/* KETIADAAN target / is_lab / downtime — atas KETIADAAN MEDANNYA      */
/* ================================================================== */

describe('tidak ada target, tidak ada is_lab, tidak ada downtime', () => {
  it('SETIAP metrik berisi TEPAT lima kunci, dan tidak satu pun nama milik master saudaranya', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    for (const column of METRIC_COLUMNS) {
      const metricEntry = summary.metrics[column] as unknown as Record<string, unknown>

      // `group` ADA; `is_lab` TIDAK. Menyalin tipe effluentPlantReportRepo.ts
      // mentah-mentah membuat is_lab undefined di mana-mana tanpa satu galat
      // TypeScript, dan layar akan menumbuhkan penanda 'lab' yang tidak
      // pernah benar — stasiun ini tidak punya satu pun kolom laboratorium.
      expect(Object.keys(metricEntry).sort(), column).toEqual([
        'avg',
        'group',
        'max',
        'min',
        'reading_count',
      ])

      for (const forbidden of [
        'is_lab',
        'target',
        'equipment_parameter',
        'target_benchmark',
        'corrective_action_plan',
        'shares_standard_with',
        'critical_limit',
        'operational_consequence_justification',
        'parameter_metric',
        'target_range',
        'standard_operational_target',
      ]) {
        expect(metricEntry, `${column}.${forbidden}`).not.toHaveProperty(forbidden)
      }
    }
  })

  it('ringkasan tidak punya blok downtime, dan daftar kuncinya DIKUNCI pada enam belas', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // engine_room_details TIDAK PUNYA satu pun kolom downtime: menyalin blok
    // downtime Kernel Plant ke sini menghasilkan undefined di mana-mana, dan
    // layar yang menggambarnya memamerkan blok yang tidak pernah bisa terisi.
    for (const forbidden of [
      'downtime',
      'targets_without_metric',
      'all_targets_measured',
      'targets_master_empty',
      'by_kernel_plant',
      'by_effluent_plant',
    ]) {
      expect(summary, forbidden).not.toHaveProperty(forbidden)
    }

    // Kunci yang hilang atau bertambah terlihat sebagai kegagalan, bukan
    // sebagai bagian layar yang diam-diam kosong. `_note_absences` yang
    // BENAR-BENAR dikirim server TIDAK ada di sini: fetchSummary membangun
    // objeknya dari daftar kunci yang ditulis, bukan menyebar payload.
    expect(Object.keys(summary).sort()).toEqual([
      'business_unit',
      'by_unit',
      'coverage',
      'daily',
      'daily_energy_export',
      'daily_total',
      'daily_trend',
      'findings',
      'has_data',
      'maintenance_remarks',
      'metrics',
      'period',
      'production_line',
      'status_distribution',
      'total',
    ])
    expect(summary).not.toHaveProperty('_note_absences')
  })

  it('TIDAK PERNAH membentuk satu pun kunci penilaian terhadap batas atau standar', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })
    const flat = JSON.stringify(summary)

    // Tiga metrik stasiun ini (frekuensi sistem, power factor, tegangan
    // busbar) punya batas yang dikenal SANGAT LUAS di luar aplikasi ini —
    // 43 Hz dan 310 V ada di fixture justru karena keduanya terlihat
    // "mencurigakan". Membentuk kunci penilaian di sini berarti mengarang
    // batas keselamatan kelistrikan yang tidak pernah ditetapkan siapa pun.
    for (const forbidden of [
      'severity',
      'is_out_of_range',
      'out_of_range',
      '"flag"',
      'threshold',
      'breach',
      'compliant',
      'compliance',
      'imbalance',
      'deviation',
    ]) {
      expect(flat, forbidden).not.toContain(forbidden)
    }
  })

  it('TIDAK ada kunci gabungan, selisih, maupun rasio antar kedua genset', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Kedelapan kolom genset berdiri sendiri. Rata-rata atau selisih gabungan
    // menyembunyikan persis apa yang dicari: ketidakseimbangan beban adalah
    // TEMUAN, bukan galat. 800 dan 40 tetap dua angka.
    for (const forbidden of [
      'diesel_gen_combined_load_kw',
      'diesel_gen_load_difference_kw',
      'diesel_gen_load_ratio',
      'genset_combined',
      'genset_difference',
    ]) {
      expect(summary.metrics, forbidden).not.toHaveProperty(forbidden)
      expect(summary, forbidden).not.toHaveProperty(forbidden)
    }

    expect(summary.metrics.diesel_gen_1_load_kw.avg).toBe(800)
    expect(summary.metrics.diesel_gen_2_load_kw.avg).toBe(40)
  })
})

/* ================================================================== */
/* Sebaran status — DUA kolom, TIGA nilai, TANPA `share`               */
/* ================================================================== */

describe('sebaran status', () => {
  // unit_test_case 14
  it('known_values tetap TIGA elemen, dan counts TIDAK dilengkapi di repo', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })
    const gen1 = summary.status_distribution.diesel_gen_1_status

    // known_values itulah yang diiterasi VIEW — bukan kunci `counts`.
    // Melengkapi counts di REPO akan menyembunyikan apakah view mengiterasi
    // yang benar, jadi repo sengaja tidak melakukannya.
    expect(gen1.known_values).toEqual(['run', 'standby', 'off'])
    expect(gen1.counts).toEqual({ run: 10, off: 2 })
    expect(Object.keys(gen1.counts)).not.toContain('standby')
    expect(gen1.not_recorded).toBe(7)
    expect(gen1.recorded_slots).toBe(19)
  })

  it("'standby' tidak pernah dilipat ke 'off', dan 10 tidak muncul sebagai gabungan", async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })
    const gen2 = summary.status_distribution.diesel_gen_2_status

    // Genset siaga siap menanggung beban; genset mati tidak. Melipat Standby
    // ke Off akan melaporkan pembangkit mati yang tidak pernah terjadi.
    expect(gen2.counts.standby).toBe(6)
    expect(gen2.counts.off).toBe(4)
    expect(gen2.counts).toEqual({ run: 0, standby: 6, off: 4 })
    expect(Object.values(gen2.counts)).not.toContain(10)
  })

  it('not_recorded adalah kunci TERPISAH dan tidak pernah dilipat ke salah satu dari ketiganya', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })
    const gen1 = summary.status_distribution.diesel_gen_1_status

    expect(gen1.not_recorded).toBe(7)
    // 2, BUKAN 9: kolom yang kosong berarti tidak ada yang mencatatnya, bukan
    // genset yang mati — dan itu kesimpulan yang berbeda tentang kesiapan
    // pabrik.
    expect(gen1.counts.off).toBe(2)
  })

  it('payload TIDAK membawa `share`, dan repo pun tidak menumbuhkannya', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    for (const [column, distribution] of Object.entries(summary.status_distribution)) {
      expect(Object.keys(distribution).sort(), column).toEqual([
        'counts',
        'known_values',
        'not_recorded',
        'recorded_slots',
      ])
      expect(distribution, column).not.toHaveProperty('share')
      expect(distribution, column).not.toHaveProperty('shares')
      expect(distribution, column).not.toHaveProperty('percent')
    }
  })

  it('recorded_slots diteruskan sebagai penyebut porsi — BUKAN expected_slots', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // 19 dan 20, bukan 1440: penyebut kedua itu akan mencampur slot tak
    // tersentuh ke dalam porsi sebuah nilai yang justru tercatat.
    expect(summary.status_distribution.diesel_gen_1_status.recorded_slots).toBe(19)
    expect(summary.status_distribution.diesel_gen_2_status.recorded_slots).toBe(20)
    expect(summary.coverage.expected_slots).toBe(1440)
  })

  it('invarian penjumlahan milik SERVER tidak pernah "diperbaiki" repo', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })
    const gen1 = summary.status_distribution.diesel_gen_1_status
    const total = Object.values(gen1.counts).reduce((sum, value) => sum + value, 0)

    // 10 + 2 + 7 = 19 — kebetulan konsisten di sini; yang diuji adalah bahwa
    // repo tidak MENORMALKAN, jadi keadaan yang TIDAK konsisten diuji di
    // bawahnya.
    expect(total + gen1.not_recorded).toBe(19)

    getMock.mockReset()
    getMock.mockResolvedValue({
      data: summaryPayload({
        status_distribution: {
          diesel_gen_1_status: {
            known_values: ['run', 'standby', 'off'],
            counts: { run: 310, standby: 90, off: 0 },
            not_recorded: 50,
            // 310 + 90 + 0 + 50 = 450, dan recorded_slots 19. Payload-nya
            // SENGAJA melanggar invariannya sendiri: repo yang menormalkan
            // angka ini akan menjawab 19 dan test ini gagal.
            recorded_slots: 19,
          },
          diesel_gen_2_status: statusDistribution().diesel_gen_2_status,
        },
      }),
    })

    const inconsistent = await fetchSummary('per-1', { productionLineId: 'pl-1' })
    const broken = inconsistent.status_distribution.diesel_gen_1_status

    expect(
      Object.values(broken.counts).reduce((sum, value) => sum + value, 0) + broken.not_recorded,
    ).toBe(450)
    expect(broken.recorded_slots).toBe(19)
  })

  it('KEDUA sebaran diteruskan, dan tidak satu pun punya min/avg/max', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(Object.keys(summary.status_distribution)).toEqual([
      'diesel_gen_1_status',
      'diesel_gen_2_status',
    ])

    // Terendah, rata-rata, dan tertinggi tidak punya arti untuk kolom
    // kategoris — dan karena itu keduanya juga tidak pernah muncul di
    // `metrics` maupun pada tren harian.
    for (const column of Object.keys(summary.status_distribution)) {
      expect(summary.metrics, column).not.toHaveProperty(column)
      expect(summary.daily_trend, column).not.toHaveProperty(column)

      const distribution = summary.status_distribution[column] as unknown as Record<string, unknown>

      expect(distribution).not.toHaveProperty('min')
      expect(distribution).not.toHaveProperty('avg')
      expect(distribution).not.toHaveProperty('max')
    }

    // Dan TIDAK ada sebaran ketiga yang menggabungkan kedua genset.
    expect(Object.keys(summary.status_distribution)).toHaveLength(2)
  })

  it('nilai di luar himpunan dikenal diteruskan apa adanya, di belakang daftar', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        status_distribution: {
          diesel_gen_1_status: {
            // Server menambahkan nilai di luar himpunan dikenal DI BELAKANG
            // daftar, supaya nilai baru dari migrasi tidak hilang.
            known_values: ['run', 'standby', 'off', 'turbo'],
            counts: { run: 10, standby: 0, off: 2, turbo: 1 },
            not_recorded: 6,
            recorded_slots: 19,
          },
          diesel_gen_2_status: statusDistribution().diesel_gen_2_status,
        },
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.status_distribution.diesel_gen_1_status.known_values).toEqual([
      'run',
      'standby',
      'off',
      'turbo',
    ])
    expect(summary.status_distribution.diesel_gen_1_status.counts.turbo).toBe(1)
  })
})

/* ================================================================== */
/* STATUS_VALUE_LABELS                                                 */
/* ================================================================== */

describe('STATUS_VALUE_LABELS', () => {
  // unit_test_case 15
  it('memetakan ketiga nilai dikenal, identik dengan Display::OPTION_LABELS', () => {
    expect(STATUS_VALUE_LABELS).toEqual({ run: 'Run', standby: 'Standby', off: 'Off' })
    expect(statusValueLabel('run')).toBe('Run')
    expect(statusValueLabel('standby')).toBe('Standby')
    expect(statusValueLabel('off')).toBe('Off')
  })

  // unit_test_case 17
  it('TIDAK menerjemahkan ke Bahasa Indonesia', () => {
    // Satu nilai hanya boleh punya satu label di seluruh produk: layar
    // Detail, formulir, ekspor Data Browser, ekspor laporan web, dan ekspor
    // CSV layar ini semuanya bersandar pada peta server yang sama. Padanan
    // Indonesia untuk kolom semacam ini diselaraskan kembali ke label server
    // pada 2026-10-14 atas keputusan pemilik produk.
    expect(statusValueLabel('standby')).not.toBe('Siaga')
    expect(statusValueLabel('run')).not.toBe('Jalan')
    expect(statusValueLabel('off')).not.toBe('Mati')
    expect(Object.values(STATUS_VALUE_LABELS)).toEqual(['Run', 'Standby', 'Off'])
  })

  // unit_test_case 16
  it('memulangkan nilai MENTAH untuk kunci tak dikenal lewat jalur mundur map[v] ?? v', () => {
    // Tanpa jalur mundur ini, sebuah nilai enum baru dari migrasi kelak akan
    // HILANG dari sebaran tanpa satu test pun memerah — view mengiterasi
    // known_values dari respons, dan server memang menerbitkan nilai di luar
    // himpunan dikenal di belakang daftar itu. Nilai mentah yang tercetak apa
    // adanya terlihat janggal, dan terlihat janggal itulah tujuannya; baris
    // yang hilang tidak terlihat sama sekali.
    expect(statusValueLabel('turbo')).toBe('turbo')
    expect(statusValueLabel('')).toBe('')
    expect(statusValueLabel('UNKNOWN_FROM_MIGRATION')).toBe('UNKNOWN_FROM_MIGRATION')
    expect(statusValueLabel('turbo')).not.toBeUndefined()
  })

  it('tidak pernah melempar, dan tidak pernah memulangkan undefined', () => {
    // Nilai yang BENAR-BENAR dapat sampai ke fungsi ini: ketiga nilai enum
    // tertutup kedua kolom status (CHECK constraint di PostgreSQL maupun
    // SQLite) ditambah nilai yang kelak ditambahkan migrasi — seluruhnya
    // berbentuk identifier enum.
    //
    // CATATAN TEMUAN, SENGAJA TIDAK DIASERSI DI SINI: STATUS_VALUE_LABELS
    // adalah objek literal, jadi `statusValueLabel('constructor')` dan
    // `statusValueLabel('toString')` memulangkan ANGGOTA RANTAI PROTOTIPE —
    // sebuah function, bukan string. Nilai semacam itu tidak dapat sampai ke
    // sini (himpunan enum-nya tertutup dan view mengiterasi known_values dari
    // server), dan perbaikannya menyentuh berkas implementasi yang bukan
    // milik putaran test ini; temuannya dilaporkan terpisah alih-alih dipaku
    // di salah satu arah di berkas ini.
    for (const value of ['run', 'standby', 'off', 'turbo', '', 'UNKNOWN_FROM_MIGRATION']) {
      expect(typeof statusValueLabel(value), value).toBe('string')
    }
  })
})

/* ================================================================== */
/* Kedua daftar teks bebas — DAFTAR KEJADIAN, bukan dikelompokkan      */
/* ================================================================== */

describe('rekap teks bebas', () => {
  it('tiga kejadian berteks IDENTIK tetap tiga entri {date, time_slot, text}', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Pengelompokan menurut teks menjawab SATU entri (dengan slot_count 3,
    // bentuk Kernel Plant mobile) dan menghilangkan tanggal serta slot waktu
    // yang justru satu-satunya cara menemukan kembali kejadiannya.
    expect(summary.maintenance_remarks).toHaveLength(3)
    expect(summary.maintenance_remarks).toEqual([
      { date: DAY_A, time_slot: '07:00', text: 'Ganti filter lube oil genset 1' },
      { date: DAY_A, time_slot: '13:00', text: 'Ganti filter lube oil genset 1' },
      { date: DAY_B, time_slot: '21:00', text: 'Ganti filter lube oil genset 1' },
    ])

    for (const entry of summary.maintenance_remarks) {
      expect(Object.keys(entry).sort()).toEqual(['date', 'text', 'time_slot'])
      expect(entry).not.toHaveProperty('slot_count')
      expect(entry).not.toHaveProperty('finding')
    }
  })

  it('kedua daftar TERPISAH, dan satu slot yang mengisi keduanya muncul pada keduanya', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.findings).toHaveLength(2)

    // SLOT YANG SAMA (tanggal + slot waktu) pada KEDUA daftar, dengan teks
    // masing-masing. Menyatukannya akan menghitung slot itu dua kali di dalam
    // satu daftar DAN mencampur dua pertanyaan yang berbeda (apa yang
    // dikerjakan vs apa yang ditemukan).
    expect(summary.maintenance_remarks[0].date).toBe(summary.findings[0].date)
    expect(summary.maintenance_remarks[0].time_slot).toBe(summary.findings[0].time_slot)
    expect(summary.maintenance_remarks[0].text).not.toBe(summary.findings[0].text)

    // Dan tidak ada daftar KETIGA yang menyatukan keduanya.
    expect(summary).not.toHaveProperty('free_text')
    expect(summary).not.toHaveProperty('notes')
    expect(summary).not.toHaveProperty('remarks')
    expect(summary).not.toHaveProperty('text_entries')
  })

  it('TIDAK mengurutkan ulang: urutan server dipertahankan apa adanya', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        findings: [
          { date: DAY_B, time_slot: '03:00', text: 'Zebra' },
          { date: DAY_A, time_slot: '07:00', text: 'Alfa' },
        ],
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Urutan payload SENGAJA tidak menurut tanggal maupun alfabet. Repo yang
    // mengurutkan sendiri akan membalikkannya dan test ini gagal.
    expect(summary.findings.map((entry) => entry.text)).toEqual(['Zebra', 'Alfa'])
  })

  it('kedua daftar kosong adalah jawaban yang sah, bukan medan yang hilang', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({ maintenance_remarks: [], findings: [] }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.maintenance_remarks).toEqual([])
    expect(summary.findings).toEqual([])
  })
})

/* ================================================================== */
/* Tren harian — SATU seri, dan bukan atas kolom energi maupun status   */
/* ================================================================== */

describe('tren harian', () => {
  it('TEPAT satu seri, dan ia BUKAN kolom energi maupun kolom status', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(Object.keys(summary.daily_trend)).toEqual(['electrical_sync_total_factory_load_kw'])

    for (const column of [
      'diesel_gen_1_status',
      'diesel_gen_2_status',
      'daily_energy_export_kwh',
    ]) {
      expect(summary.daily_trend, column).not.toHaveProperty(column)
    }
  })

  it('agregasinya RATA-RATA, berbeda dari seri energi yang nilai TERAKHIR', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })
    const points = summary.daily_trend.electrical_sync_total_factory_load_kw

    // Dua agregasi, dua bentuk kunci, sehingga tidak satu pun dapat terbaca
    // sebagai yang lain: tren memakai `avg`, seri energi memakai `last_value`
    // + `time_slot`.
    expect(Object.keys(points[0]).sort()).toEqual(['avg', 'date', 'reading_count'])
    expect(points[0]).not.toHaveProperty('last_value')
    expect(points[0]).not.toHaveProperty('time_slot')
    expect(summary.daily_energy_export.series[0]).not.toHaveProperty('avg')
  })

  it('tiap titik tren membawa penyebutnya sendiri, dan avg null bukan 0', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.daily_trend.electrical_sync_total_factory_load_kw).toEqual([
      { date: DAY_A, avg: 1180, reading_count: 24 },
      { date: DAY_B, avg: null, reading_count: 0 },
      { date: DAY_C, avg: 1320, reading_count: 9 },
    ])
  })
})

/* ================================================================== */
/* Rekap per unit                                                      */
/* ================================================================== */

describe('rekap per unit', () => {
  it('engine_room_id adalah string biasa — bukan uuid dan bukan kunci asing', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const rows = (await fetchSummary('per-1', { productionLineId: 'pl-1' })).by_unit

    // Tidak ada tabel master engine_rooms: nilainya kolom TEKS yang DIKETIK
    // di layar input (screen-077), bentuk yang sama dengan boiler_room_id dan
    // effluent_plant_id.
    for (const row of rows) {
      expect(typeof row.engine_room_id).toBe('string')
      expect(row.engine_room_id).not.toMatch(
        /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i,
      )
      expect(row).not.toHaveProperty('engine_room_name')
    }

    expect(rows.map((row) => row.engine_room_id)).toEqual(['ER-1', 'ER-2'])
  })

  it('reading_rows dan reading_count diterbitkan BERDAMPINGAN dan bernilai sama', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const rows = (await fetchSummary('per-1', { productionLineId: 'pl-1' })).by_unit

    // Kedua nama dipakai bergantian di dua belas laporan saudaranya; keduanya
    // diterbitkan supaya setiap pemanggil tetap membaca kunci yang sudah
    // diharapkannya.
    for (const row of rows) {
      expect(row.reading_count).toBe(row.reading_rows)
    }
    expect(rows.map((row) => row.reading_rows)).toEqual([180, 0])
    expect(rows.map((row) => row.days_with_rows)).toEqual([12, 0])
  })

  it('metrics per unit berbentuk SAMA dengan metrics tingkat periode', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const rows = (await fetchSummary('per-1', { productionLineId: 'pl-1' })).by_unit

    for (const row of rows) {
      expect(Object.keys(row.metrics)).toEqual(METRIC_COLUMNS)
      expect(Object.keys(row.metrics.diesel_gen_1_load_kw).sort()).toEqual([
        'avg',
        'group',
        'max',
        'min',
        'reading_count',
      ])
    }

    expect(rows[0].metrics.diesel_gen_1_load_kw.avg).toBe(7.5)
    // Unit tanpa satu pun pembacaan TETAP muncul, dengan null.
    expect(rows[1].metrics.diesel_gen_1_load_kw.avg).toBeNull()
  })
})

/* ================================================================== */
/* Daftar periode                                                      */
/* ================================================================== */

describe('daftar periode', () => {
  // unit_test_case 1
  it('meneruskan daftar apa adanya, termasuk periode tertutup, tanpa business_unit_name', async () => {
    const options = [
      {
        id: 'per-1',
        name: 'Periode Uji Engine Room',
        start_date: PERIOD_START,
        end_date: PERIOD_END,
        status: 'open',
        // DENGAN TANDA HUBUNG. Garis bawah di situ tidak akan cocok dengan
        // satu baris period_stations pun dan server menjawab [] untuk setiap
        // mill tanpa satu galat.
        station_type: 'engine-room',
        station_type_label: 'Engine Room',
      },
      {
        id: 'per-0',
        name: 'Periode Sebelumnya',
        start_date: PREV_START,
        end_date: PREV_END,
        status: 'closed',
        station_type: 'engine-room',
        station_type_label: 'Engine Room',
      },
    ]

    getMock.mockResolvedValue({ data: { data: options } })

    const periods = await fetchPeriods()

    // Status mengatur penulisan data, bukan pembacaan laporan.
    expect(periods.map((period) => period.status)).toEqual(['open', 'closed'])
    expect(periods.map((period) => period.station_type)).toEqual(['engine-room', 'engine-room'])
    expect(getMock.mock.calls[0][0]).toBe('/api/engine-room-reports/periods')

    // /periods TIDAK membawa nama mill — nama mill dibaca view dari
    // summary.business_unit.name, satu sumber saja supaya tidak ada dua
    // tempat yang dapat menyimpang. Koreksi itu ditemukan saat screen-155
    // diimplementasikan dan tidak diwarisi salah lagi ke sini.
    for (const period of periods) {
      expect(period).not.toHaveProperty('business_unit_name')
      expect(Object.keys(period).sort()).toEqual([
        'end_date',
        'id',
        'name',
        'start_date',
        'station_type',
        'station_type_label',
        'status',
      ])
    }
  })

  // unit_test_case 4
  it('daftar periode kosong adalah jawaban yang sah, bukan galat', async () => {
    getMock.mockResolvedValue({ data: { data: [] } })

    await expect(fetchPeriods()).resolves.toEqual([])
  })

  it('respons tanpa pembungkus data menghasilkan daftar kosong, bukan lemparan', async () => {
    getMock.mockResolvedValue({ data: {} })

    await expect(fetchPeriods()).resolves.toEqual([])
  })

  it('nama mill dibaca dari summary.business_unit, dan /summary memang membawa keduanya', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.business_unit).toEqual({ id: 'bu-1', name: 'Mill Sungai Rambutan' })
    // Kepala periode pada /summary MEMANG membawa business_unit_name dari
    // server, dan repo TIDAK menyentuhnya — yang disengaja adalah tipe yang
    // tidak menuliskannya, sehingga tidak ada pemanggil terketik yang
    // membacanya dari sana. Satu sumber untuk nama mill: blok business_unit.
    expect(summary.period).toHaveProperty('business_unit_name')
    expect(summary.business_unit?.name).toBe(
      (summary.period as unknown as Record<string, string>).business_unit_name,
    )
  })
})

/* ================================================================== */
/* Ekspor — CSV SAJA                                                   */
/* ================================================================== */

describe('ekspor', () => {
  // unit_test_case 36
  it('meminta CSV sebagai blob untuk periode dan line yang diberikan', async () => {
    getMock.mockResolvedValue({ data: new Blob(['a,b']) })

    await exportCsv('per-1', { productionLineId: 'pl-1' })

    expect(getMock.mock.calls[0][0]).toBe('/api/engine-room-reports/export')
    expect(getMock.mock.calls[0][1]).toMatchObject({
      params: { period_id: 'per-1', format: 'csv', production_line_id: 'pl-1' },
      // text/csv streaming, BUKAN JSON.
      responseType: 'blob',
    })
  })

  // unit_test_case 37
  it('format yang diminta selalu csv — tidak ada jalur xlsx/excel di modul ini', async () => {
    getMock.mockResolvedValue({ data: new Blob(['a,b']) })

    await exportCsv('per-1', { productionLineId: 'pl-1' })

    // Endpoint-nya MEMANG mendukung 'excel', tetapi WebView ponsel tidak
    // membuka .xlsx — menyediakan tombol yang menghasilkan berkas yang tidak
    // dapat dibuka lebih buruk daripada tidak menyediakannya.
    expect(getMock.mock.calls[0][1].params.format).toBe('csv')
    expect(JSON.stringify(getMock.mock.calls[0][1])).not.toContain('xlsx')
    expect(JSON.stringify(getMock.mock.calls[0][1])).not.toContain('excel')
  })

  it('AUDIT STATIS: nol kemunculan excel/xlsx sebagai NILAI pada kode repo maupun view', () => {
    for (const relative of [
      'src/services/engineRoomReportRepo.ts',
      'src/views/LaporanEngineRoomView.vue',
    ]) {
      const source = readFileSync(resolve(process.cwd(), relative), 'utf-8')
      // Komentar dibuang: kedua berkas MENJELASKAN mengapa jalur .xlsx tidak
      // dirender, jadi penyisiran atas sumber mentah akan selalu memerah pada
      // kalimat yang justru melarangnya.
      const code = source
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/^\s*\/\/.*$/gm, '')

      expect(code.toLowerCase(), relative).not.toContain('xlsx')
      expect(code.toLowerCase(), relative).not.toContain('excel')
    }
  })

  it('saveCsvFile menyimpan blob dengan nama yang diberikan', () => {
    const createObjectURL = vi.fn(() => 'blob:x')
    const revokeObjectURL = vi.fn()

    vi.stubGlobal('URL', { createObjectURL, revokeObjectURL })

    const click = vi.fn()
    const anchor = document.createElement('a')

    anchor.click = click
    vi.spyOn(document, 'createElement').mockReturnValueOnce(anchor)

    saveCsvFile(new Blob(['a,b']), 'laporan-engine-room_periode_line.csv')

    expect(anchor.download).toBe('laporan-engine-room_periode_line.csv')
    expect(click).toHaveBeenCalledOnce()
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:x')

    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })
})

/* ================================================================== */
/* Galat                                                               */
/* ================================================================== */

describe('galat diteruskan apa adanya', () => {
  it('galat tanpa status dan galat ber-status keduanya diteruskan dengan bentuk aslinya', async () => {
    getMock.mockRejectedValueOnce({ message: 'Tidak dapat terhubung ke server.' })

    await expect(fetchSummary('per-1', { productionLineId: 'pl-1' })).rejects.toEqual({
      message: 'Tidak dapat terhubung ke server.',
    })

    getMock.mockRejectedValueOnce({ message: 'Unauthenticated.', status: 401 })

    // Ketiadaan `status` itulah yang membedakan "jaringan putus" dari "server
    // menjawab", dan urutan cabang handleError di view bergantung padanya.
    // normalizeError apiClient MEMBUANG `response`, jadi medan itu tidak
    // pernah ada pada galat yang sampai ke pemanggil.
    await expect(fetchSummary('per-1', { productionLineId: 'pl-1' })).rejects.toEqual({
      message: 'Unauthenticated.',
      status: 401,
    })
  })

  // unit_test_case 30
  it('403 diteruskan dengan status-nya — view yang menafsirkannya "pilih line lagi"', async () => {
    getMock.mockRejectedValueOnce({
      message: 'Anda tidak memiliki akses untuk aksi ini.',
      status: 403,
    })

    await expect(fetchSummary('per-1', { productionLineId: 'pl-basi' })).rejects.toEqual({
      message: 'Anda tidak memiliki akses untuk aksi ini.',
      status: 403,
    })
  })

  // unit_test_case 31
  it('404 periode tidak ditemukan diteruskan dengan status-nya, bukan diubah menjadi 401', async () => {
    getMock.mockRejectedValueOnce({ message: 'Periode tidak ditemukan.', status: 404 })

    await expect(fetchSummary('per-9', { productionLineId: 'pl-1' })).rejects.toEqual({
      message: 'Periode tidak ditemukan.',
      status: 404,
    })
  })

  // unit_test_case 29
  it('422 akun tanpa mill diteruskan beserta medan errors-nya', async () => {
    getMock.mockRejectedValueOnce({
      message: 'Akun Anda belum terhubung ke mill. Hubungi Admin.',
      status: 422,
      errors: { business_unit_id: ['Akun Anda belum terhubung ke mill. Hubungi Admin.'] },
    })

    // Repo tidak menafsirkan satu kode pun: yang membedakan 422 "akun tanpa
    // mill" dari 422 EXPORT_FAILED adalah PEMANGGIL-nya, dan pemetaan itu ada
    // di handleError() view (meaning422).
    await expect(fetchPeriods()).rejects.toMatchObject({
      status: 422,
      errors: { business_unit_id: ['Akun Anda belum terhubung ke mill. Hubungi Admin.'] },
    })
  })

  // unit_test_case 38
  it('422 EXPORT_FAILED diteruskan APA ADANYA, tanpa dipotong atau diulang', async () => {
    const message =
      'Ekspor melebihi 50.000 baris slot. Persempit rentang periode, lalu coba lagi.'

    getMock.mockRejectedValueOnce({ message, status: 422 })

    await expect(exportCsv('per-1', { productionLineId: 'pl-1' })).rejects.toEqual({
      message,
      status: 422,
    })
    // SATU permintaan: repo tidak mencoba memecah rentang menjadi beberapa
    // permintaan yang lebih kecil.
    expect(getMock).toHaveBeenCalledTimes(1)
  })

  it('galat /periods diteruskan, tidak diganti daftar kosong', async () => {
    getMock.mockRejectedValueOnce({ message: 'Unauthenticated.', status: 401 })

    await expect(fetchPeriods()).rejects.toEqual({ message: 'Unauthenticated.', status: 401 })
  })

  it('galat /export diteruskan, dan tidak ada berkas yang tersimpan', async () => {
    getMock.mockRejectedValueOnce({ message: 'Unauthenticated.', status: 401 })

    await expect(exportCsv('per-1', { productionLineId: 'pl-1' })).rejects.toEqual({
      message: 'Unauthenticated.',
      status: 401,
    })
  })
})

/* ================================================================== */
/* Bentuk modul                                                        */
/* ================================================================== */

describe('bentuk modul', () => {
  it('mengekspor tepat enam anggota: empat fungsi jaringan/berkas plus peta label dan pembacanya', () => {
    // ENAM, bukan empat: berbeda dari effluentPlantReportRepo, peta label
    // nilai status DAN pembacanya hidup di REPO (bukan di view), karena satu
    // salinan peta itu wajib selaras dengan Display::OPTION_LABELS di server.
    // Dan tetap TIDAK ADA fetchBusinessUnits — rute itu Admin saja.
    expect(Object.keys(engineRoomReportRepo).sort()).toEqual([
      'STATUS_VALUE_LABELS',
      'exportCsv',
      'fetchPeriods',
      'fetchSummary',
      'saveCsvFile',
      'statusValueLabel',
    ])
  })

  it('tidak ada satu pun anggota yang menulis ke server', () => {
    // Layar ini BACAAN SAJA: tidak ada post/put/patch/delete, dan tidak ada
    // nama yang menyiratkannya.
    for (const key of Object.keys(engineRoomReportRepo)) {
      expect(key, key).not.toMatch(/^(create|update|delete|save[A-Z](?!sv)|post|put|patch)/)
    }
    expect(Object.keys(engineRoomReportRepo).filter((key) => /^fetch/.test(key))).toHaveLength(2)
  })

  /**
   * DIVERGENSI SPEC — lihat docblock berkas. Spec menamai
   * `fetchProductionLines` sebagai bagian layar ini; yang benar adalah
   * productionLineRepo.fetchProductionLinesForReport(null), DIPAKAI ULANG
   * VERBATIM dari screen-135 seperti kedua belas laporan mobile saudaranya.
   */
  it('TIDAK mengekspor pengambil daftar Production Line, dan tidak menyentuh endpoint-nya', async () => {
    const module = await import('@/services/engineRoomReportRepo')

    expect(Object.keys(module)).not.toContain('fetchProductionLines')
    expect(Object.keys(module)).not.toContain('fetchProductionLinesForReport')
    expect(Object.keys(engineRoomReportRepo)).not.toContain('fetchProductionLines')

    getMock.mockResolvedValue({ data: { data: [] } })
    await fetchPeriods()
    getMock.mockResolvedValue({ data: summaryPayload() })
    await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Endpoint-nya TIDAK diduplikasi di bawah prefix engine-room-reports, dan
    // modul ini tidak pernah memanggilnya.
    for (const call of getMock.mock.calls) {
      expect(String(call[0])).not.toContain('production-lines')
    }
  })

  // unit_test_case 35 (separuh repo)
  it('tidak menyentuh localDb maupun localStorage sama sekali', () => {
    const source = readFileSync(
      resolve(process.cwd(), 'src/services/engineRoomReportRepo.ts'),
      'utf-8',
    )
    const code = source.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '')

    // Ketiadaan mode luring DISENGAJA: hasil laporan tidak pernah disimpan,
    // jadi keadaan luring dinyatakan apa adanya alih-alih ditutupi angka basi.
    expect(code).not.toContain('localDb')
    expect(code).not.toContain('localStorage')
    expect(code).not.toContain('sqlite')
  })
})
