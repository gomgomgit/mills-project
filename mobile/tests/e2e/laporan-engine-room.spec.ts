import { expect, test, type Page } from '@playwright/test'
import { getAuthUserId, login, USERS } from './helpers'

/**
 * laporan-engine-room.spec.ts — screen-159--laporan-engine-room-mobile /
 * usecase-165--laporan-engine-room-mobile.
 *
 * Satu test per test_scenarios yang punya browser_test. Berjalan di Vite dev
 * server (playwright.config.ts, baseURL http://localhost:5174): aplikasi
 * Capacitor adalah SPA biasa sebelum dibungkus native, jadi browser test layar
 * mobile TIDAK ditangguhkan sebagai "mobile-only".
 *
 * VIEWPORT PONSEL, BUKAN DESKTOP. Berkas ini menimpa viewport bawaan proyek
 * chromium (Desktop Chrome, 1280px) dengan 390x844. Separuh klaim layar ini
 * adalah klaim tata letak ("satu kolom", "halaman tidak menggulir mendatar
 * walau rekapnya 23 kolom", "keterangan genset terlihat di dalam KEDUA blok"),
 * dan seluruhnya hampa pada kanvas 1280px: apa pun muat di sana.
 *
 * ────────────────────────────────────────────────────────────────────────
 * RESPONS LAPORAN DI-STUB DI TINGKAT JARINGAN (page.route), BUKAN DISEMAI
 * ────────────────────────────────────────────────────────────────────────
 * Keputusan dan alasan yang sama dengan kedua belas laporan mobile lain:
 *
 *   - Layar ini read-only total dan tidak punya satu pun UI untuk membuat
 *     data yang dibacanya.
 *   - Kebenaran ANGKA-nya bukan tanggung jawab layar ini: seluruhnya dari
 *     EngineRoomReportService, yang sudah diuji penuh ketika screen-158
 *     dibangun. Yang khas mobile — dan hanya dapat dibuktikan di sini —
 *     adalah PEMETAAN respons itu ke layar satu kolom dengan dua puluh tiga
 *     kartu, gulir terpanjang di seluruh laporan mobile.
 *   - Beberapa skenario mustahil dibuat dari data sungguhan tanpa merusak
 *     lingkungan (sesi kedaluwarsa, jaringan putus, 403 line milik mill lain,
 *     periode belum mulai, muatan yang sengaja tidak konsisten secara
 *     aritmetika, nilai status di luar himpunan enum).
 *   - DAN: backend di :8000 adalah DATABASE DEV — data kerja pengguna — dan
 *     ia tertinggal satu migrasi. Berkas ini karena itu TIDAK menulis,
 *     menghapus, menyemai, atau memigrasi apa pun di sana. Yang menyentuh
 *     backend sungguhan HANYA login (POST /api/login), seperti kedua belas
 *     spec laporan mobile lainnya. TIDAK ADA backend :8001 di sini: port itu
 *     milik suite e2e-web, dan spec ini tidak pernah menyentuhnya.
 *
 * ── ENAM HAL YANG HANYA BROWSER DAPAT MEMBUKTIKAN DI LAYAR INI ─────────
 *
 * 1. KETERANGAN GENSET TERLIHAT DI DALAM KEDUA BLOK, dan jumlahnya TEPAT
 *    DUA. Di web kedelapan kartu genset berdampingan sehingga
 *    ketidakseimbangan beban terbaca dari kedekatannya; di ponsel kedekatan
 *    itu hilang, dan keterangan SEKALI di atas halaman sudah tergulir jauh
 *    ketika blok yang diterangkannya akhirnya terlihat. Hanya halaman yang
 *    dirender dapat membuktikan ia benar-benar tergambar di kedua tempat.
 *
 * 2. HALAMAN TIDAK PERNAH MENGGULIR MENDATAR, DIUKUR — bukan disimpulkan
 *    dari markup. Kedua rekapnya membawa DUA PULUH TIGA kolom angka,
 *    terlebar di laporan mobile mana pun, dan keduanya BERTUMPUK menjadi
 *    kartu lewat v-stacked-table (temuan QA LP-M-02/LP-M-03). Satu-satunya
 *    elemen yang MENGGULIR MENDATAR DI DALAM KARTUNYA adalah grafik tren
 *    beban total pabrik (.chart-scroll) — dan fixture di bawah memberinya
 *    DUA BELAS tanggal justru supaya ia benar-benar melampaui 390px.
 *
 * 3. KEDUA PULUH DUA PENYEBUT BENAR-BENAR TERCETAK pada kanvas 390px, dan
 *    KEDUA PULUH DUANYA BERBEDA. Inilah yang paling mudah hilang karena
 *    dipotong demi ruang, dan tanpanya rata-rata sebuah parameter terbaca
 *    sebagai angka seluruh periode.
 *
 * 4. CAKUPAN BERADA DI ATAS KEENAM BLOK KARTU — diukur dari POSISI KOTAK DI
 *    VIEWPORT, bukan dari urutan sumbernya.
 *
 * 5. BARIS SEBARAN STATUS BERNILAI 0 BENAR-BENAR TERGAMBAR, dan 'Standby'
 *    berdiri terpisah dari 'Off'. Baris yang hilang terbaca sebagai "nilai
 *    ini tidak ada" padahal yang benar adalah "nilai ini tidak pernah
 *    terjadi".
 *
 * 6. PERNYATAAN KETIADAAN PENANDAAN DI LUAR BATAS TERLIHAT UTUH, tanpa
 *    terpotong — diukur dari scrollHeight lawan clientHeight elemennya,
 *    bukan dari sumbernya.
 *
 * ── KONVENSI YANG HARGANYA SUDAH DIBAYAR ───────────────────────────────
 *
 * LAYAR INI TIDAK MEMILIHKAN LINE, bahkan ketika mill hanya punya satu —
 * berbeda dari LaporanEffluentPlantView. Karena itu setiap test di bawah
 * MEMILIH line lewat pemilihnya (atau menyemai ingatan perangkat lebih
 * dulu); test yang menyalin alur effluent dan langsung memilih periode akan
 * menunggu angka yang memang tidak akan pernah datang.
 *
 * ASERSI LABEL PENDEK DILINGKUP KE SELNYA. 'Run' / 'Off' / 'Standby'
 * panjangnya 2-7 karakter dan cocok di dalam kata lain, jadi asersi
 * substring tingkat halaman hijau tanpa membuktikan apa pun. Di sini dipakai
 * baris ber-data-status-value + sel data-testid="status-value-label".
 *
 * KETIADAAN PENANDAAN DI LUAR BATAS DISISIR ATAS NAMA KELAS (jumlah elemen),
 * bukan atas frasa: kalimat yang menyatakan ketiadaannya sendiri memuat
 * frasa "di luar batas". Hal yang sama berlaku untuk 'downtime' dan
 * 'target'.
 *
 * ASERSI PERSEN DILINGKUP KE ELEMENNYA. Halaman ini memuat porsi sebaran
 * status (52,6% / 0,0% / 10,5% / 36,8% / 100,0%) di samping persen cakupan,
 * jadi asersi persen tingkat halaman tidak bermakna di sini.
 *
 * ALAMAT DIBANDINGKAN ATAS PATHNAME, bukan dengan toHaveURL('**\/x'): dengan
 * baseURL terpasang, pola glob semacam itu tidak pernah cocok dan test-nya
 * "lulus" tanpa pernah menguji apa pun. Di sini dipakai waitForURL dengan
 * PREDIKAT atas URL yang sudah terurai.
 *
 * SETIAP count() DIDAHULUI ASERSI YANG MEMBUKTIKAN WILAYAHNYA SUDAH
 * DIRENDER (toBeVisible / toHaveCount yang menunggu sendiri). count() tanpa
 * penantian mengembalikan 0 pada halaman yang belum selesai merender, dan 0
 * adalah jawaban yang diam-diam meluluskan asersi "tidak ada".
 *
 * REKAP HARIAN MEMAKAI v-show (CollapsibleSection), BUKAN v-if: saat
 * tertutup barisnya TETAP ADA di DOM dengan display:none. Karena itu keadaan
 * tertutup diasersi dengan toBeHidden(), tidak pernah dengan toHaveCount(0).
 *
 * LURING DISETEL SETELAH LAYAR TERBUKA, bukan sebelumnya: rutenya
 * lazy-import, jadi menyetel luring sebelum chunk-nya terunduh membuat
 * navigasi MENGGANTUNG alih-alih gagal — alasan yang sama dicatat pada
 * tests/e2e/dashboard-reporting.spec.ts.
 *
 * TANGGAL FIXTURE RELATIF terhadap hari ini — tidak ada tanggal kalender
 * yang ditulis mati, dan nama periodenya pun bukan nama bulan, supaya slug
 * nama berkas ekspor tidak ikut basi.
 *
 * MENJALANKANNYA MENUNTUT DUA PROSES: backend di :8000 (dengan seeder
 * DemoAccountSeeder sudah dijalankan — login di sini POST sungguhan) dan
 * Vite dev server di :5174 (dinyalakan playwright.config.ts sendiri lewat
 * blok webServer, reuseExistingServer).
 */

const ROUTE = '/reports/engine-room'

/**
 * Halaman disajikan dari :5174 sementara API berada di :8000, jadi setiap
 * respons stub harus membawa header CORS-nya sendiri (termasuk jawaban
 * preflight) — tanpa ini axios menerimanya sebagai kegagalan jaringan dan
 * setiap test di bawah akan "lulus" ke cabang yang salah.
 */
const CORS_HEADERS = {
  'access-control-allow-origin': '*',
  'access-control-allow-headers': '*',
  'access-control-allow-methods': 'GET,POST,OPTIONS',
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

/** DUA BELAS tanggal — lihat catatan #2 pada docblock berkas. */
const SERIES_DATES = Array.from({ length: 12 }, (_unused, index) => isoDaysFromNow(-26 + index))
const DAY_A = SERIES_DATES[0]
const DAY_B = SERIES_DATES[1]

/* ------------------------------------------------------------------ */
/* Fixture — BENTUK RESPONS NYATA                                      */
/* ------------------------------------------------------------------ */

const PERIOD = {
  id: 'per-1',
  name: 'Periode Uji Engine Room',
  start_date: PERIOD_START,
  end_date: PERIOD_END,
  status: 'open',
  // DENGAN TANDA HUBUNG. Garis bawah di situ tidak cocok dengan satu baris
  // period_stations pun, dan server menjawab [] tanpa galat.
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

const LINE_1 = { id: 'pl-1', name: 'Line 1', code: 'L1' }
const LINE_2 = { id: 'pl-2', name: 'Line 2', code: 'L2' }

/** Kunci ingatan line, ditulis StationListView.vue dan dibaca layar ini. */
const REMEMBERED_LINE_KEY = (userId: string) => `msl_production_line_${userId}`

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
 * KEDUA PULUH DUA PENYEBUT YANG BERBEDA.
 *
 * Dengan penyebut seragam, asersi "tiap kartu mencetak penyebutnya sendiri"
 * dan "tidak satu blok pun mewarisi penyebut blok lain" selalu hijau — dua
 * cacat yang paling mungkin muncul pada markup yang di-loop, dan di ponsel
 * keduanya LEBIH sulit terlihat karena kartunya tidak pernah berdampingan.
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
  { testid: 'metric-block-steam-turbine', title: 'Turbin Uap', cards: 6 },
  { testid: 'metric-block-diesel-gen-1', title: 'Genset 1', cards: 4 },
  { testid: 'metric-block-diesel-gen-2', title: 'Genset 2', cards: 4 },
  { testid: 'metric-block-electrical-sync', title: 'Kelistrikan Tersinkron', cards: 4 },
  { testid: 'metric-block-compressor', title: 'Kompresor', cards: 2 },
  { testid: 'metric-block-auxiliary', title: 'Penunjang', cards: 2 },
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
 * diesel_gen_2_load_kw sengaja membawa avg 40 di LUAR rentang 10..20:
 * mustahil dari perhitungan apa pun. Frekuensi 43 Hz dan tegangan busbar
 * 310 V ada di sini justru karena keduanya terlihat "mencurigakan" — nol
 * elemen penanda di luar batas tetap yang benar.
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
    diesel_gen_1_lube_oil_pressure_bar: metric(
      'diesel_gen_1_lube_oil_pressure_bar',
      3.1,
      4.05,
      4.9,
    ),
    diesel_gen_2_load_kw: metric('diesel_gen_2_load_kw', 10, 40, 20),
    diesel_gen_2_amperage_a: metric('diesel_gen_2_amperage_a', 18, 62, 95),
    diesel_gen_2_jacket_water_temp_c: metric('diesel_gen_2_jacket_water_temp_c', 38.4, 44.9, 51.2),
    diesel_gen_2_lube_oil_pressure_bar: metric(
      'diesel_gen_2_lube_oil_pressure_bar',
      2.8,
      3.42,
      4.1,
    ),
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

/**
 * SLOT WAKTU SERI ENERGI — URUTANNYA BUKAN URUTAN LEKSIKAL.
 *
 * Kisi kanonis layar input berjalan 07:00 .. 06:00 KEESOKAN HARI, jadi
 * '06:00' adalah posisi TERAKHIR — sementara urutan leksikal 'HH:MM'
 * memilihnya sebagai yang paling AWAL, dan hasilnya terlihat sepenuhnya
 * masuk akal. Titik PERTAMA memakai slot itu dengan sengaja, dan daftar di
 * bawah tidak terurut secara leksikal maupun menurut nilainya: layar yang
 * mengurutkan slot atau membandingkan jam akan menggambar urutan yang
 * berbeda, dan test urutan di bawah gagal.
 */
const ENERGY_SLOTS = [
  '06:00',
  '23:00',
  '13:00',
  '07:00',
  '21:00',
  '09:00',
  '18:00',
  '11:00',
  '05:00',
  '19:00',
  '08:00',
  '22:00',
]

const ENERGY_VALUES = [
  4100, 3900, 4250, 4010, 4330, 3880, 4190, 4420, 3950, 4270, 4060, 4380,
]

function energySeries(): Array<Record<string, unknown>> {
  return SERIES_DATES.map((date, index) => ({
    date,
    last_value: ENERGY_VALUES[index],
    time_slot: ENERGY_SLOTS[index],
    reading_count: 24 - index,
  }))
}

/**
 * KEDUA sebaran status, TIGA nilai masing-masing.
 *
 * Genset 1: 'standby' ABSEN dari `counts` sementara tetap ada di
 * `known_values` — barisnya WAJIB tetap tergambar bernilai 0. Angka-angkanya
 * KONSISTEN secara penjumlahan (10 + 2 + 7 = 19) karena porsinya diasersi
 * digit demi digit.
 *
 * Genset 2: standby 6 dan off 4 sebagai dua angka BERBEDA — melipat keduanya
 * menghasilkan 10, dan tidak ada satu sel pun bernilai 10 pada sebaran ini.
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

/** DUA BELAS tanggal × 23 kolom — rekap terlebar di laporan mobile mana pun. */
function dailyRows(): Array<Record<string, unknown>> {
  return SERIES_DATES.map((date, index) => ({
    date,
    reading_rows: index + 1,
    metrics_avg: Object.fromEntries(
      METRIC_COLUMNS.map((column) => [
        column,
        column === 'electrical_sync_power_factor'
          ? { avg: null, reading_count: 0 }
          : { avg: 700 + index, reading_count: index + 1 },
      ]),
    ),
    energy_export: {
      last_value: ENERGY_VALUES[index],
      date,
      time_slot: ENERGY_SLOTS[index],
      reading_count: 24 - index,
    },
  }))
}

function makeSummary(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Sungai Rambutan' },
    production_line: { id: 'pl-1', name: 'Line 1' },
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
      // Entri PERTAMA memakai tanggal + slot yang SAMA dengan entri pertama
      // maintenance_remarks: satu slot yang mengisi keduanya muncul pada
      // KEDUA daftar, dan itu benar.
      { date: DAY_A, time_slot: '07:00', text: 'Getaran tidak wajar pada bearing alternator 2' },
      { date: DAY_B, time_slot: '03:00', text: 'Tegangan busbar turun saat genset 2 start' },
    ],
    daily_trend: {
      electrical_sync_total_factory_load_kw: SERIES_DATES.map((date, index) => ({
        date,
        // Titik ke-2 tanpa pembacaan: null, BUKAN 0 — batangnya KOSONG.
        avg: index === 1 ? null : 1000 + index * 40,
        reading_count: index === 1 ? 0 : index + 1,
      })),
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
    daily: dailyRows(),
    // Jumlah baris harian 1+2+...+12 = 78, dan server mengirim 99.
    daily_total: { reading_rows: 99 },
    total: { days_with_records: 12, reading_rows: 260 },
    ...overrides,
  }
}

/** Periode yang PUNYA record tetapi tanpa satu slot terisi: 0,0%, BUKAN '—'. */
function emptySummary(): Record<string, unknown> {
  return makeSummary({
    has_data: false,
    coverage: {
      filled_slots: 0,
      expected_slots: 1440,
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
/* Stub jaringan                                                       */
/* ------------------------------------------------------------------ */

interface ApiState {
  periods: unknown[]
  summary: Record<string, unknown>
  /** 200 kecuali test menyetel lain (401 / 403 / 404 / 422). */
  summaryStatus: number
  /**
   * Pesan yang menyertai respons /summary non-200. Dapat disetel per test
   * supaya asersi "pesan SERVER ditampilkan apa adanya" benar-benar menguji
   * terusannya alih-alih mencocokkan kalimat yang dipaku di stub.
   */
  summaryMessage: string
  /** true = permintaan ringkasan diputus di tingkat jaringan. */
  summaryAbort: boolean
  /** Status respons /export — 200 kecuali test menyetel lain. */
  exportStatus: number
  /**
   * Tundaan ms sebelum respons /export diselesaikan. WAJIB bagi test klik
   * ganda: tanpa tundaan stub menjawab seketika, `exporting` sudah kembali
   * false sebelum ketukan kedua mendarat, dan dua permintaan terkirim —
   * bukan karena penjagaannya gagal, melainkan karena tidak sekali pun
   * diuji.
   */
  exportDelayMs: number
  productionLines: unknown[]
  /** true = daftar line gagal diambil (jaringan putus). */
  productionLinesAbort: boolean
  hits: { periods: number; summary: number; export: number; units: number; lines: number }
  /** Seluruh URL yang benar-benar dikirim halaman ke endpoint laporan. */
  urls: string[]
  /** Dan ke endpoint daftar Production Line, terpisah. */
  lineUrls: string[]
}

async function stubApi(page: Page, overrides: Partial<ApiState> = {}): Promise<ApiState> {
  const state: ApiState = {
    periods: [PERIOD, PERIOD_CLOSED],
    summary: makeSummary(),
    summaryStatus: 200,
    summaryMessage: 'Permintaan ditolak.',
    summaryAbort: false,
    exportStatus: 200,
    exportDelayMs: 0,
    productionLines: [LINE_1],
    productionLinesAbort: false,
    hits: { periods: 0, summary: 0, export: 0, units: 0, lines: 0 },
    urls: [],
    lineUrls: [],
    ...overrides,
  }

  // Daftar Production Line — endpoint DI LUAR prefiks laporan (dipakai ULANG
  // verbatim dari screen-135), jadi rutenya sendiri. Tanpa stub ini
  // permintaan menembus ke backend sungguhan di :8000 dan hasil test
  // bergantung pada isi DATABASE DEV.
  await page.route('**/api/production-lines/options-for-report*', async (route) => {
    if (route.request().method() === 'OPTIONS') {
      await route.fulfill({ status: 204, headers: CORS_HEADERS, body: '' })

      return
    }

    state.hits.lines += 1
    state.lineUrls.push(route.request().url())

    if (state.productionLinesAbort) {
      await route.abort('failed')

      return
    }

    await route.fulfill({
      status: 200,
      headers: CORS_HEADERS,
      json: { data: state.productionLines },
    })
  })

  await page.route('**/api/engine-room-reports/**', async (route) => {
    const request = route.request()
    const url = request.url()

    if (request.method() === 'OPTIONS') {
      await route.fulfill({ status: 204, headers: CORS_HEADERS, body: '' })

      return
    }

    state.urls.push(url)

    if (url.includes('/business-units/options')) {
      state.hits.units += 1
      // ADMIN SAJA. Layar ini tidak punya satu pun jalur ke sini; stub ini
      // ada JUSTRU supaya pemanggilan yang tak diinginkan TERHITUNG alih-alih
      // diam-diam menembus ke backend.
      await route.fulfill({
        status: 403,
        headers: CORS_HEADERS,
        json: { message: 'Anda tidak memiliki akses untuk aksi ini.' },
      })

      return
    }

    if (url.includes('/periods')) {
      state.hits.periods += 1
      await route.fulfill({ status: 200, headers: CORS_HEADERS, json: { data: state.periods } })

      return
    }

    if (url.includes('/export')) {
      state.hits.export += 1

      if (state.exportDelayMs > 0) {
        await new Promise((resolveDelay) => setTimeout(resolveDelay, state.exportDelayMs))
      }

      if (state.exportStatus !== 200) {
        await route.fulfill({
          status: state.exportStatus,
          headers: CORS_HEADERS,
          json: {
            message:
              'Ekspor melebihi 50.000 baris slot. Persempit rentang periode, lalu coba lagi.',
          },
        })

        return
      }

      await route.fulfill({
        status: 200,
        headers: { ...CORS_HEADERS, 'content-type': 'text/csv' },
        body:
          'Periode,Mill,Production Line,Tanggal,Slot Waktu,Unit Engine Room,Status Genset 1,Status Genset 2\n'
          + `Periode Uji Engine Room,Mill Sungai Rambutan,Line 1,${PERIOD_START},07:00,ER-1,Run,Standby\n`
          + `Periode Uji Engine Room,Mill Sungai Rambutan,Line 1,${PERIOD_END},19:00,ER-1,Run,Off\n`,
      })

      return
    }

    if (url.includes('/summary')) {
      state.hits.summary += 1

      if (state.summaryAbort) {
        await route.abort('failed')

        return
      }

      if (state.summaryStatus !== 200) {
        await route.fulfill({
          status: state.summaryStatus,
          headers: CORS_HEADERS,
          json: { message: state.summaryMessage },
        })

        return
      }

      // Blok production_line DIBENTUK DARI PARAMETER yang diminta, bukan
      // dipaku ke Line 1: layar mendahulukan nama dari SERVER atas nama dari
      // daftarnya sendiri (itulah line yang benar-benar dipakai saat
      // menghitung), jadi stub yang mengabaikan parameternya akan membuat
      // setiap asersi "line mana yang berlaku" membaca jawaban yang salah.
      const query = new URL(url).searchParams
      const requestedLineId = query.get('production_line_id')
      const requestedLine = (state.productionLines as Array<{ id: string; name: string }>).find(
        (line) => line.id === requestedLineId,
      )

      // KEPALA PERIODE pun dibentuk dari period_id yang diminta, dengan
      // alasan yang sama.
      const requestedPeriodId = query.get('period_id')
      const requestedPeriod = (
        state.periods as Array<{
          id: string
          name: string
          start_date: string
          end_date: string
          status: string
        }>
      ).find((period) => period.id === requestedPeriodId)

      const body: Record<string, unknown> = { ...state.summary }

      if (requestedLine) {
        body.production_line = { id: requestedLine.id, name: requestedLine.name }
      }

      if (requestedPeriod) {
        body.period = {
          id: requestedPeriod.id,
          name: requestedPeriod.name,
          start_date: requestedPeriod.start_date,
          end_date: requestedPeriod.end_date,
          status: requestedPeriod.status,
        }
      }

      await route.fulfill({ status: 200, headers: CORS_HEADERS, json: body })

      return
    }

    await route.fulfill({ status: 404, headers: CORS_HEADERS, json: { message: 'not stubbed' } })
  })

  return state
}

/* ------------------------------------------------------------------ */
/* Bantuan navigasi                                                    */
/* ------------------------------------------------------------------ */

async function openReport(page: Page, path: string = ROUTE): Promise<void> {
  await page.goto(path)
  await expect(page.getByTestId('laporan-engine-room-mobile')).toBeVisible()
}

/**
 * MEMILIH LINE ITU LANGKAH TERSENDIRI DI LAYAR INI.
 *
 * Berbeda dari LaporanEffluentPlantView, layar ini TIDAK memilihkan line
 * walau mill hanya punya satu — memilihkannya secara diam-diam menerbitkan
 * angka yang pengguna tidak pernah minta. Test yang menyalin alur effluent
 * dan langsung memilih periode akan menunggu angka yang memang tidak akan
 * pernah datang.
 */
async function pickLine(page: Page, lineId = 'pl-1'): Promise<void> {
  await expect(page.getByTestId('production-line-select')).toBeVisible()
  await page.getByTestId('production-line-select').selectOption(lineId)
}

async function pickPeriod(page: Page, periodId: string): Promise<void> {
  await page.getByTestId('period-select').selectOption(periodId)
}

/** Alur baku: buka, pilih line, pilih periode, tunggu laporan tergambar. */
async function openFullReport(page: Page, periodId = 'per-1'): Promise<void> {
  await openReport(page)
  await pickLine(page)
  await pickPeriod(page, periodId)
  await expect(page.getByTestId('period-meta')).toBeVisible()
  await expect(page.getByTestId('metric-card')).toHaveCount(22)
}

/**
 * Alamat dibandingkan atas PATHNAME yang sudah terurai, bukan atas pola glob:
 * dengan baseURL terpasang, toHaveURL('**\/login') tidak pernah cocok dan
 * test-nya "lulus" tanpa menguji apa pun.
 */
async function expectPathname(page: Page, expected: string): Promise<void> {
  await page.waitForURL((url) => url.pathname === expected)
  expect(new URL(page.url()).pathname).toBe(expected)
}

/** Rekap Harian — CollapsibleSection, TERTUTUP secara bawaan. */
function recapToggle(page: Page) {
  return page.getByTestId('daily-recap').getByTestId('collapsible-section-toggle')
}

function recapBody(page: Page) {
  return page.getByTestId('daily-recap').getByTestId('collapsible-section-body')
}

/** Satu baris sebaran, dicari lewat data-status-value — bukan lewat posisinya. */
function statusRow(page: Page, distributionTestId: string, value: string) {
  return page.getByTestId(distributionTestId).locator(`[data-status-value="${value}"]`)
}

/**
 * "BELUM ADA YANG DIPILIH" DIBACA DARI OPSI TERPILIH, BUKAN DARI .value.
 *
 * Vue mengikat `:value="null"` pada opsi placeholder lewat properti
 * internalnya dan TIDAK menuliskan atribut `value`, sehingga `select.value`
 * di browser jatuh ke TEKS opsi itu — `toHaveValue('')` karena itu memerah
 * pada keadaan yang justru benar. Diperiksa langsung sebelum ditulis.
 */
async function expectNothingSelected(
  page: Page,
  testId: string,
  placeholder: string,
): Promise<void> {
  await expect(page.getByTestId(testId).locator('option:checked')).toHaveText(placeholder)
}

/* ------------------------------------------------------------------ */
/* Bantuan asersi khas ponsel                                          */
/* ------------------------------------------------------------------ */

/**
 * Halaman TIDAK PERNAH menggulir mendatar. Yang lebar adalah isi kartu —
 * dan masing-masing menggulir atau bertumpuk di dalam kartunya sendiri.
 */
async function expectNoHorizontalPageScroll(page: Page): Promise<void> {
  const overflow = await page.evaluate(() => {
    const doc = document.scrollingElement ?? document.documentElement

    return {
      scrollWidth: doc.scrollWidth,
      clientWidth: doc.clientWidth,
      bodyScrollWidth: document.body.scrollWidth,
    }
  })

  // Toleransi 1px untuk pembulatan subpiksel.
  expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1)
  expect(overflow.bodyScrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1)
}

/** Satu kolom: tidak ada dua kartu metrik yang berdampingan mendatar. */
async function expectSingleColumn(page: Page): Promise<void> {
  const cards = page.getByTestId('metric-card')

  // DUA PULUH DUA, TEPAT — bukan "setidaknya satu". toHaveCount menunggu
  // sendiri, jadi evaluateAll di bawah tidak pernah membaca halaman setengah
  // render.
  await expect(cards).toHaveCount(22)
  await expect(cards.first()).toBeVisible()

  const boxes = await cards.evaluateAll((elements) =>
    elements.map((element) => {
      const rect = element.getBoundingClientRect()

      return { top: rect.top, bottom: rect.bottom }
    }),
  )

  // Setiap kartu berikutnya berada DI BAWAH kartu sebelumnya, bukan di
  // sampingnya — itulah arti "satu kolom" pada layar 390px.
  for (let index = 1; index < boxes.length; index += 1) {
    expect(boxes[index].top).toBeGreaterThanOrEqual(boxes[index - 1].bottom - 1)
  }
}

/** Sasaran sentuh minimal 44px — ukuran jari, bukan ukuran kursor. */
async function expectTouchTargets(page: Page, testIds: string[]): Promise<void> {
  for (const testId of testIds) {
    await expect(page.getByTestId(testId)).toBeVisible()

    const box = await page.getByTestId(testId).boundingBox()

    expect(box, `sasaran sentuh ${testId} tidak terender`).not.toBeNull()
    expect(box!.height, `sasaran sentuh ${testId} terlalu pendek`).toBeGreaterThanOrEqual(44)
  }
}

/**
 * Kartu yang isinya lebih lebar daripada layar menggulir DI DALAM dirinya
 * sendiri: scrollWidth > clientWidth DAN overflow-x-nya auto/scroll.
 *
 * DI LAYAR INI SATU-SATUNYA ELEMEN SEMACAM ITU ADALAH .chart-scroll (grafik
 * tren beban total pabrik) — bukan pembungkus rekap: kedua rekap BERTUMPUK
 * menjadi kartu lewat v-stacked-table (temuan QA LP-M-02/LP-M-03, ditegakkan
 * global oleh src/assets/stacked-table.css yang memaksa
 * .detail-table-wrap { overflow-x: visible } pada ≤640px).
 */
async function expectCardScrollsInsideItself(page: Page, selector: string): Promise<void> {
  await expect(page.locator(selector).first()).toBeVisible()

  const result = await page
    .locator(selector)
    .first()
    .evaluate((element) => ({
      scrollWidth: element.scrollWidth,
      clientWidth: element.clientWidth,
      overflowX: getComputedStyle(element).overflowX,
    }))

  expect(['auto', 'scroll']).toContain(result.overflowX)
  expect(result.scrollWidth).toBeGreaterThan(result.clientWidth)
}

/**
 * Tabel rekap BERTUMPUK di dalam kartunya, bukan menggulir mendatar.
 *
 * Diukur dari BENTUK YANG TERLIHAT: tiap sel menempati barisnya sendiri
 * (kotaknya turun, bukan berderet), tiap sel membawa data-label, dan tidak
 * ada isi yang disembunyikan di balik gulir mendatar.
 */
async function expectCardStacksInsideItself(page: Page, selector: string): Promise<void> {
  const result = await page
    .locator(selector)
    .first()
    .evaluate((element) => {
      const row = element.querySelector('tbody tr')
      const cells = row ? Array.from(row.children) : []
      const tops = new Set(cells.map((cell) => Math.round(cell.getBoundingClientRect().top)))

      return {
        scrollWidth: element.scrollWidth,
        clientWidth: element.clientWidth,
        cells: cells.length,
        barisTegak: tops.size,
        berlabel: cells.filter((cell) => (cell.getAttribute('data-label') ?? '') !== '').length,
      }
    })

  expect(result.cells, 'tabel rekap tidak punya satu baris isi pun untuk dinilai').toBeGreaterThan(
    0,
  )
  // Tidak ada isi yang tersembunyi di balik gulir mendatar.
  expect(result.scrollWidth).toBeLessThanOrEqual(result.clientWidth + 1)
  // Setiap sel menempati barisnya sendiri, dan membawa label kolomnya.
  expect(result.barisTegak).toBe(result.cells)
  expect(result.berlabel).toBe(result.cells)
}

/* ================================================================== */

test.describe('Laporan Engine Room Mobile (screen-159)', () => {
  // Layar ponsel sungguhan — lihat catatan VIEWPORT pada docblock berkas.
  test.use({ viewport: { width: 390, height: 844 } })

  test.beforeEach(async ({ page }) => {
    await login(page, USERS.operator)
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 1 + 2 — jalur sukses, kedua aktor                        */
  /* ---------------------------------------------------------------- */

  test('skenario 1: Operator — seluruh laporan tampil satu kolom tanpa gulir mendatar halaman', async ({
    page,
  }) => {
    const state = await stubApi(page)

    await openFullReport(page)

    for (const testId of [
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
      'energy-export-card',
      'energy-export-badge',
      'status-distribution-diesel-gen-1',
      'status-distribution-diesel-gen-2',
      'standby-not-off-note',
      'status-distribution-note',
      'daily-energy-export',
      'daily-energy-export-table',
      'daily-trend-total-factory-load',
      'aggregation-difference-note',
      'maintenance-remarks',
      'findings',
      'by-unit',
      'daily-recap',
      'completeness',
    ]) {
      await expect(page.getByTestId(testId), testId).toBeVisible()
    }

    // KEENAM judul blok, dan kartunya masing-masing.
    for (const block of METRIC_BLOCKS) {
      await expect(page.getByTestId(block.testid), block.testid).toContainText(block.title)
      await expect(
        page.getByTestId(block.testid).getByTestId('metric-card'),
        block.testid,
      ).toHaveCount(block.cards)
    }

    // DUA PULUH TIGA kartu: 22 dari loop `metrics` + 1 kartu energi.
    await expect(page.getByTestId('metric-card')).toHaveCount(22)
    await expect(page.getByTestId('energy-export-card')).toHaveCount(1)

    // TIDAK ADA pemilih mill, dan itu bukan pemangkasan: kedua aktor layar
    // ini terikat satu mill, diselesaikan server dari akun.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.getByTestId('mill-current')).toContainText('Mill Sungai Rambutan')

    // TIDAK ADA kontrol tulis.
    await expect(page.locator('input')).toHaveCount(0)
    await expect(page.locator('textarea')).toHaveCount(0)
    await expect(page.locator('form')).toHaveCount(0)

    await expectSingleColumn(page)
    await expectNoHorizontalPageScroll(page)
    await expectTouchTargets(page, [
      'production-line-select',
      'period-select',
      'export-button',
      'back-button',
    ])

    // Dan endpoint daftar mill tidak pernah disentuh.
    expect(state.hits.units).toBe(0)
  })

  test('skenario 2: Supervisor melihat bagian dan angka yang sama persis dengan Operator', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    const asOperator = await page.getByTestId('laporan-engine-room-mobile').innerText()

    await login(page, USERS.supervisor)
    await openFullReport(page)

    const asSupervisor = await page.getByTestId('laporan-engine-room-mobile').innerText()

    // Tidak satu pun medan, kolom, atau aksi yang hanya muncul pada salah
    // satu peran: ketiga rute data sudah menerima Operator sejak commit
    // pertama screen-158, jadi layar ini menambah PEMANGGIL — bukan varian
    // layar.
    expect(asSupervisor).toBe(asOperator)
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
  })

  test('business_unit_id tidak pernah ada pada satu pun alamat yang dikirim layar ini', async ({
    page,
  }) => {
    const state = await stubApi(page)

    await openFullReport(page)

    const download = page.waitForEvent('download')

    await page.getByTestId('export-button').click()
    await (await download).delete()

    await expect.poll(() => state.hits.export).toBe(1)

    // Perlindungannya STRUKTURAL — tidak ada jalur kode yang dapat
    // mengirimkannya — jadi asersi inilah yang mencatat maksudnya.
    for (const url of [...state.urls, ...state.lineUrls]) {
      expect(url, url).not.toContain('business_unit_id')
    }

    expect(state.urls.some((url) => url.includes('/business-units/options'))).toBe(false)
    // Nama mill terlihat sebagai KETERANGAN saja — yang tidak ada hanyalah
    // kemampuan menggantinya.
    await expect(page.getByTestId('mill-current')).toContainText('Mill Sungai Rambutan')
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 12 + 22 + 23 + 24 — kelengkapan pencatatan               */
  /* ---------------------------------------------------------------- */

  test('skenario 22: cakupan berada DI ATAS keenam blok kartu, diukur dari posisi di viewport', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    await expect(page.getByTestId('coverage')).toBeVisible()
    await expect(page.getByTestId('metric-block-steam-turbine')).toBeVisible()

    const coverage = await page.getByTestId('coverage').boundingBox()
    const firstBlock = await page.getByTestId('metric-block-steam-turbine').boundingBox()

    expect(coverage).not.toBeNull()
    expect(firstBlock).not.toBeNull()

    // Di layar sempit pembaca melihat lebih sedikit sekaligus, jadi apa yang
    // dilihat lebih dulu menentukan lebih banyak — dan kelengkapan inilah
    // yang menentukan apakah dua puluh tiga angka di bawahnya layak
    // dipercaya.
    expect(coverage!.y).toBeLessThan(firstBlock!.y)
    // Dan ia benar-benar berada di viewport awal, sebelum satu kartu pun.
    expect(coverage!.y).toBeLessThan(844)

    // Ketiga angka pembentuk penyebut ikut tercetak, bukan hanya persennya.
    await expect(page.getByTestId('coverage-denominator')).toContainText('2 unit')
    await expect(page.getByTestId('coverage-denominator')).toContainText('30 hari')
    await expect(page.getByTestId('coverage-denominator')).toContainText('24 slot')
    await expect(page.getByTestId('coverage-slots')).toContainText('260 dari 1.440 slot')
    // Dilingkup KE ELEMENNYA — halaman ini memuat porsi sebaran status di
    // samping persen cakupan.
    await expect(page.getByTestId('coverage-percent')).toHaveText('88,8%')
  })

  test('skenario 23: periode masih berjalan — hari berhenti di hari ini, keterangannya tampil', async ({
    page,
  }) => {
    await stubApi(page, {
      summary: makeSummary({
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
    })

    await openFullReport(page)

    await expect(page.getByTestId('coverage-days')).toContainText('12 dari 30 hari')
    await expect(page.getByTestId('coverage-running-note')).toContainText('masih berjalan')
    await expect(page.getByTestId('coverage-not-started-note')).toHaveCount(0)
    // Hari yang belum terjadi tidak ikut menjadi penyebut, jadi persennya
    // tidak diturunkan olehnya.
    await expect(page.getByTestId('coverage-percent')).toHaveText('79,9%')
  })

  test('skenario 24: expected_slots 0 — tanda hubung, dan 0,0% TIDAK terlihat', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          // Server mengirim 0.0 — BUKAN null — pada keadaan ini.
          coverage_percent: 0,
          engine_room_unit_count: 0,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          // KEDUANYA sekaligus: server menandai periode yang BELUM MULAI
          // sebagai masih berjalan juga, jadi days_counted WAJIB diperiksa
          // lebih dulu.
          days_counted: 0,
          period_running: true,
        },
      }),
    })

    await openFullReport(page)

    await expect(page.getByTestId('coverage-percent')).toHaveText('—')
    await expect(page.getByTestId('coverage-no-expected-note')).toContainText(
      'Belum ada slot yang diharapkan',
    )
    await expect(page.getByTestId('coverage-not-started-note')).toContainText('belum mulai')
    await expect(page.getByTestId('coverage-running-note')).toHaveCount(0)
  })

  test('skenario 8: periode ber-record tanpa slot terisi — 0,0% dan kartu tidak tersedia', async ({
    page,
  }) => {
    await stubApi(page, { summary: emptySummary() })

    await openReport(page)
    await pickLine(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('empty-period')).toBeVisible()
    // Penyebutnya TERBENTUK (1.440), dan yang terukur memang nol slot.
    await expect(page.getByTestId('coverage-percent')).toHaveText('0,0%')
    await expect(page.getByTestId('coverage-no-expected-note')).toHaveCount(0)

    // Kedua puluh dua kartu TETAP tergambar, dan kartu energi menyatakan
    // tidak ada data.
    await expect(page.getByTestId('metric-card')).toHaveCount(22)
    await expect(page.getByTestId('metric-card').first()).toContainText('tidak tersedia')
    await expect(page.getByTestId('energy-export-empty')).toBeVisible()
    await expect(page.getByTestId('daily-energy-export-empty')).toBeVisible()
    // Tabel dan grafik TIDAK digambar: tabel kosong terbaca sebagai nol
    // terukur, dan grafik kosong sebagai garis datar hasil ukur.
    await expect(page.getByTestId('daily-energy-export-table')).toHaveCount(0)
    await expect(page.getByTestId('daily-trend-total-factory-load')).toHaveCount(0)
    await expect(page.getByTestId('by-unit')).toHaveCount(0)
    await expect(page.getByTestId('daily-recap')).toHaveCount(0)
    // Sementara KEDUA SEBARAN tetap digambar.
    await expect(page.getByTestId('status-distribution-diesel-gen-1')).toBeVisible()
    await expect(page.getByTestId('status-distribution-diesel-gen-2')).toBeVisible()
  })

  test('skenario 12: slot terisi MELAMPAUI penyebut setiap kartu, dengan keterangan sebabnya', async ({
    page,
  }) => {
    await stubApi(page, {
      summary: makeSummary({
        coverage: {
          // Tujuh slot terisi sementara penyebut terbesar hanya DUA: sebuah
          // slot yang hanya mengisi temuan tetap terhitung pada kelengkapan
          // tanpa menambah penyebut kartu mana pun.
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
    })

    await openFullReport(page)

    await expect(page.getByTestId('coverage-slots')).toContainText('7 dari 240 slot')

    const note = page.getByTestId('coverage-twenty-seven-columns-note')

    await expect(note).toContainText('27 kolom')
    await expect(note).toContainText('kedua kolom teks bebas')
    await expect(note).toContainText('MELEBIHI penyebut setiap kartu')

    // Dan penyebut tiap kartu BENAR-BENAR lebih kecil daripada 7, dibaca dari
    // halaman yang sudah dirender.
    const denominators = page.getByTestId('metric-denominator')

    await expect(denominators).toHaveCount(22)

    const texts = await denominators.evaluateAll((elements) =>
      elements.map((element) => element.textContent ?? ''),
    )

    for (const denominator of texts) {
      expect(denominator).toMatch(/dari [12] pembacaan/)
    }
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 31 + 32 + 33 — kartu, penyebut, dan blok yang terbuka     */
  /* ---------------------------------------------------------------- */

  test('skenario 32: kedua puluh dua penyebut tercetak dan SELURUHNYA berbeda', async ({ page }) => {
    await stubApi(page)
    await openFullReport(page)

    const denominators = page.getByTestId('metric-denominator')

    await expect(denominators).toHaveCount(22)

    const texts = await denominators.evaluateAll((elements) =>
      elements.map((element) => (element.textContent ?? '').trim()),
    )

    // Penyebut identik pada dua puluh dua kartu adalah cacat yang dicari, dan
    // tidak satu blok pun mewarisi penyebut blok lain.
    expect(new Set(texts).size).toBe(22)

    // Kartu berpenyebut kecil tidak tampil sama meyakinkan dengan kartu
    // berpenyebut besar: keduanya mencetak penyebutnya sendiri.
    await expect(page.locator('[data-metric="diesel_gen_1_load_kw"]')).toContainText(
      'dari 200 pembacaan',
    )
    await expect(page.locator('[data-metric="diesel_gen_2_load_kw"]')).toContainText(
      'dari 11 pembacaan',
    )
  })

  test('skenario 33: keenam blok tergambar TERBUKA tanpa satu kontrol lipat pun', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    for (const block of METRIC_BLOCKS) {
      // Kartunya sudah terender tanpa satu ketukan pun.
      await expect(
        page.getByTestId(block.testid).getByTestId('metric-card'),
        block.testid,
      ).toHaveCount(block.cards)
      await expect(
        page.getByTestId(block.testid).getByTestId('collapsible-section-toggle'),
        block.testid,
      ).toHaveCount(0)
    }

    // Yang DAPAT ditutup hanyalah rekap harian.
    await expect(page.getByTestId('collapsible-section-toggle')).toHaveCount(1)
    await expect(recapToggle(page)).toBeVisible()

    // Dan penyebut setiap kartu BENAR-BENAR terlihat saat digulir.
    for (const column of ['steam_turbine_rpm', 'diesel_gen_2_load_kw', 'fuel_tank_level']) {
      const cardLocator = page.locator(`[data-metric="${column}"]`)

      await cardLocator.scrollIntoViewIfNeeded()
      await expect(cardLocator.getByTestId('metric-denominator'), column).toBeVisible()
    }
  })

  test('skenario 31: angka di layar cocok dengan payload — nol angka turunan', async ({ page }) => {
    await stubApi(page)
    await openFullReport(page)

    // avg DI LUAR rentang min..max: layar yang menurunkan (min+max)/2
    // menggambar 15.
    const gen2 = page.locator('[data-metric="diesel_gen_2_load_kw"]')

    await expect(gen2).toBeVisible()
    await expect(gen2.getByTestId('metric-avg')).toHaveText('40')
    await expect(gen2.locator('.metric-row-item b').first()).toHaveText('10')
    await expect(gen2.locator('.metric-row-item b').nth(2)).toHaveText('20')

    // 800 lawan 40 — ketidakseimbangan beban yang merupakan TEMUAN, bukan
    // galat, dan yang tidak pernah dirata-ratakan di klien.
    await expect(
      page.locator('[data-metric="diesel_gen_1_load_kw"]').getByTestId('metric-avg'),
    ).toHaveText('800')

    // Pembulatan mengikuti tabel meta yang disalin dari blade screen-158.
    await expect(
      page.locator('[data-metric="electrical_sync_system_frequency_hz"]').getByTestId('metric-avg'),
    ).toHaveText('49,82')
    await expect(
      page.locator('[data-metric="steam_turbine_rpm"]').getByTestId('metric-avg'),
    ).toHaveText('5.100')

    // Total harian DARI SERVER: 1+2+...+12 = 78, dan server mengirim 99.
    await recapToggle(page).click()
    await expect(recapBody(page)).toBeVisible()
    await expect(page.getByTestId('daily-row-total')).toContainText('99')
    await expect(page.getByTestId('total-reading-rows')).toHaveText('260')
  })

  test("satuan 'C' dari server tergambar 'C', tidak pernah '°C'", async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        metrics: {
          ...metrics(),
          steam_turbine_inlet_temp_c: metric('steam_turbine_inlet_temp_c', 280, 305, 330, {
            unit: 'C',
          }),
        },
      }),
    })

    await openFullReport(page)

    const label = page.locator('[data-metric="steam_turbine_inlet_temp_c"] .metric-label')

    await expect(label).toBeVisible()
    // DILINGKUP KE ELEMENNYA: '°C' yang sah tetap muncul di judul kolom kedua
    // tabel rekap (label STATIS di METRIC_META), jadi asersi tingkat halaman
    // tidak bermakna di sini.
    await expect(label).toContainText('· C')
    expect(await label.innerText()).not.toContain('°C')
  })

  test('skenario 9: kartu yang tidak pernah terisi tetap terlihat di dalam bloknya', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    const powerFactor = page.locator('[data-metric="electrical_sync_power_factor"]')

    await powerFactor.scrollIntoViewIfNeeded()
    await expect(powerFactor).toBeVisible()
    await expect(powerFactor.getByTestId('metric-avg')).toHaveText('tidak tersedia')
    await expect(powerFactor.getByTestId('metric-denominator')).toContainText('dari 0 pembacaan')

    // Ia TETAP berada di dalam blok Kelistrikan Tersinkron — tidak hilang
    // dari bloknya.
    await expect(
      page.getByTestId('metric-block-electrical-sync').locator('[data-metric="electrical_sync_power_factor"]'),
    ).toHaveCount(1)
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 11 — KETERANGAN GENSET DI DALAM KEDUA BLOK                */
  /* ---------------------------------------------------------------- */

  test('skenario 11: keterangan genset terlihat DI DALAM KEDUA blok, dan jumlahnya TEPAT DUA', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    const notes = page.getByTestId('genset-parallel-note')

    // DUA, TEPAT — toHaveCount menunggu sendiri, jadi ia tidak pernah membaca
    // halaman setengah render. Mengasersi "ada" saja akan lolos pada
    // implementasi yang menaruhnya SEKALI di atas halaman.
    await expect(notes).toHaveCount(2)

    // Dan masing-masing BENAR-BENAR terlihat di dalam blok genset-nya sendiri
    // ketika blok itu berada di viewport.
    for (const [index, blockTestId] of [
      'metric-block-diesel-gen-1',
      'metric-block-diesel-gen-2',
    ].entries()) {
      const note = page.getByTestId(blockTestId).getByTestId('genset-parallel-note')

      await expect(note, blockTestId).toHaveCount(1)
      await note.scrollIntoViewIfNeeded()
      await expect(note, blockTestId).toBeVisible()
      await expect(notes.nth(index), blockTestId).toBeVisible()
    }

    // Keempat blok lain TIDAK membawanya.
    for (const blockTestId of [
      'metric-block-steam-turbine',
      'metric-block-electrical-sync',
      'metric-block-compressor',
      'metric-block-auxiliary',
    ]) {
      await expect(
        page.getByTestId(blockTestId).getByTestId('genset-parallel-note'),
        blockTestId,
      ).toHaveCount(0)
    }

    // Menyebut kesejajaran FISIK, dan MENYANGKAL standar bersama.
    await expect(notes.first()).toContainText('dua mesin fisik yang berbeda')
    await expect(notes.first()).toContainText('bukan standar bersama')
    await expect(notes.first()).toContainText('tidak punya tabel master target')

    // NOL kartu gabungan maupun badge selisih — disisir atas NAMA KELAS dan
    // TESTID, bukan atas frasa: keterangannya sendiri memuat kata "selisih".
    const flaggedClasses = await page.evaluate(() =>
      Array.from(document.querySelectorAll('[class]'))
        .flatMap((element) => element.className.toString().split(/\s+/))
        .filter((name) => /combined|imbalance|deviation|difference/.test(name)),
    )

    expect(flaggedClasses).toEqual([])
    for (const testId of ['genset-combined-card', 'genset-difference-card']) {
      await expect(page.getByTestId(testId), testId).toHaveCount(0)
    }
  })

  test('skenario 10: Genset 2 tanpa pembacaan — empat kartu penyebut nol, tanpa ikon galat', async ({
    page,
  }) => {
    const gen2Columns = METRIC_COLUMNS.filter((column) => METRIC_GROUPS[column] === 'diesel_gen_2')

    await stubApi(page, {
      summary: makeSummary({
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
            counts: { run: 0, standby: 0, off: 20 },
            not_recorded: 0,
            recorded_slots: 20,
          },
        },
      }),
    })

    await openFullReport(page)

    const gen2Block = page.getByTestId('metric-block-diesel-gen-2')

    await gen2Block.scrollIntoViewIfNeeded()
    await expect(gen2Block.getByTestId('metric-card')).toHaveCount(4)

    for (const column of gen2Columns) {
      await expect(
        gen2Block.locator(`[data-metric="${column}"]`).getByTestId('metric-avg'),
        column,
      ).toHaveText('tidak tersedia')
      await expect(
        gen2Block.locator(`[data-metric="${column}"]`).getByTestId('metric-denominator'),
        column,
      ).toContainText('dari 0 pembacaan')
    }

    // Genset yang tidak pernah menyala adalah keadaan operasional yang SAH.
    const flagged = await gen2Block.evaluate((element) =>
      Array.from(element.querySelectorAll('[class]'))
        .flatMap((child) => child.className.toString().split(/\s+/))
        .filter((name) => /error|danger|warning|alert|invalid|critical/.test(name)),
    )

    expect(flagged).toEqual([])

    // Dan sebarannya: Off 20, Run 0, Standby 0 — dibaca dari sel di dalam
    // barisnya masing-masing.
    await expect(statusRow(page, 'status-distribution-diesel-gen-2', 'off')).toContainText('20')
    await expect(
      statusRow(page, 'status-distribution-diesel-gen-2', 'run').locator('td.num').first(),
    ).toHaveText('0')
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 13 + 14 + 15 + 34 — SEBARAN STATUS                        */
  /* ---------------------------------------------------------------- */

  test('skenario 14: baris Standby yang ABSEN dari counts BENAR-BENAR tergambar bernilai 0', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    const distribution = page.getByTestId('status-distribution-diesel-gen-1')

    await distribution.scrollIntoViewIfNeeded()
    await expect(distribution.getByTestId('status-row')).toHaveCount(3)

    const standby = statusRow(page, 'status-distribution-diesel-gen-1', 'standby')

    await expect(standby).toBeVisible()
    // LABEL dibaca dari SELNYA sendiri, dengan toHaveText — 'Run'/'Off'
    // panjangnya tiga karakter dan cocok di dalam kata lain.
    await expect(standby.getByTestId('status-value-label')).toHaveText('Standby')
    await expect(standby.locator('td.num').first()).toHaveText('0')
    // Dan ia TERPISAH dari baris tidak tercatat, yang berjumlah berbeda.
    await expect(
      distribution.getByTestId('status-not-recorded').locator('td.num').first(),
    ).toHaveText('7')
  })

  test('skenario 13: seluruh slot tidak mencatat status — ketiga baris nilai tetap 0', async ({
    page,
  }) => {
    await stubApi(page, {
      summary: makeSummary({
        status_distribution: {
          diesel_gen_1_status: {
            known_values: ['run', 'standby', 'off'],
            counts: {},
            not_recorded: 18,
            recorded_slots: 18,
          },
          diesel_gen_2_status: statusDistribution().diesel_gen_2_status,
        },
      }),
    })

    await openFullReport(page)

    const distribution = page.getByTestId('status-distribution-diesel-gen-1')

    await distribution.scrollIntoViewIfNeeded()
    await expect(distribution.getByTestId('status-row')).toHaveCount(3)

    for (const value of ['run', 'standby', 'off']) {
      const row = statusRow(page, 'status-distribution-diesel-gen-1', value)

      await expect(row, value).toBeVisible()
      await expect(row.locator('td.num').first(), value).toHaveText('0')
    }

    await expect(
      distribution.getByTestId('status-not-recorded').locator('td.num').first(),
    ).toHaveText('18')
    // Keterangan bahwa Standby bukan Off tetap terlihat.
    await page.getByTestId('standby-not-off-note').scrollIntoViewIfNeeded()
    await expect(page.getByTestId('standby-not-off-note')).toContainText('siap menanggung beban')
  })

  test('skenario 34: Standby dan Off terlihat TERPISAH, dan porsinya memakai recorded_slots', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    const distribution = page.getByTestId('status-distribution-diesel-gen-2')

    await distribution.scrollIntoViewIfNeeded()

    const standby = statusRow(page, 'status-distribution-diesel-gen-2', 'standby')
    const off = statusRow(page, 'status-distribution-diesel-gen-2', 'off')

    await expect(standby.getByTestId('status-value-label')).toHaveText('Standby')
    await expect(off.getByTestId('status-value-label')).toHaveText('Off')
    await expect(standby.locator('td.num').first()).toHaveText('6')
    await expect(off.locator('td.num').first()).toHaveText('4')

    // NOL baris gabungan bernilai 10.
    const counts = await distribution
      .getByTestId('status-row')
      .locator('td.num')
      .first()
      .evaluateAll((elements) => elements.map((element) => element.textContent?.trim() ?? ''))

    expect(counts).not.toContain('10')

    // Porsinya memakai recorded_slots 20 — BUKAN expected_slots 1.440:
    // 6/20 = 30,0%, 4/20 = 20,0%, tidak tercatat 10/20 = 50,0%.
    await expect(standby.locator('td.num').nth(1)).toHaveText('30,0%')
    await expect(off.locator('td.num').nth(1)).toHaveText('20,0%')
    await expect(
      distribution.getByTestId('status-not-recorded').locator('td.num').nth(1),
    ).toHaveText('50,0%')
    await expect(distribution).toContainText('20 slot tercatat')
  })

  test('skenario 15: nilai status di luar himpunan dikenal terlihat MENTAH', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        status_distribution: {
          diesel_gen_1_status: {
            known_values: ['run', 'standby', 'off', 'turbo'],
            counts: { run: 10, standby: 0, off: 2, turbo: 1 },
            not_recorded: 6,
            recorded_slots: 19,
          },
          diesel_gen_2_status: statusDistribution().diesel_gen_2_status,
        },
      }),
    })

    await openFullReport(page)

    const distribution = page.getByTestId('status-distribution-diesel-gen-1')

    await distribution.scrollIntoViewIfNeeded()
    await expect(distribution.getByTestId('status-row')).toHaveCount(4)

    const turbo = statusRow(page, 'status-distribution-diesel-gen-1', 'turbo')

    // Nilai mentah yang tercetak apa adanya terlihat janggal — dan terlihat
    // janggal itulah tujuannya; baris yang hilang tidak terlihat sama sekali.
    await expect(turbo).toBeVisible()
    await expect(turbo.getByTestId('status-value-label')).toHaveText('turbo')
    await expect(turbo.locator('td.num').first()).toHaveText('1')
    // Dan layarnya tetap utuh.
    await expect(page.getByTestId('metric-card')).toHaveCount(22)
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 16 + 17 — SERI EKSPOR ENERGI                              */
  /* ---------------------------------------------------------------- */

  test('skenario 17: urutan baris energi mengikuti kisi kanonis server, bukan urutan jam', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    const table = page.getByTestId('daily-energy-export-table')

    await table.scrollIntoViewIfNeeded()
    await expect(page.getByTestId('energy-export-row')).toHaveCount(12)

    const slots = await page
      .getByTestId('energy-export-time-slot')
      .evaluateAll((elements) => elements.map((element) => element.textContent?.trim() ?? ''))

    // '06:00' lebih dulu karena pada kisi 07:00..06:00 ia posisi TERAKHIR,
    // sementara urutan LEKSIKAL memilihnya sebagai yang paling awal — dan
    // hasilnya terlihat sepenuhnya masuk akal.
    expect(slots).toEqual(ENERGY_SLOTS)
    expect(slots).not.toEqual([...slots].sort())

    const values = await page
      .getByTestId('energy-export-last')
      .evaluateAll((elements) => elements.map((element) => element.textContent?.trim() ?? ''))

    expect(values[0]).toBe('4.100')
    expect(values[1]).toBe('3.900')
    // Bukan urut menurut nilai pula.
    expect(values).not.toEqual([...values].sort())

    // DAN TIDAK ADA nilai periode untuk kolom energi: layar yang MENURUNKAN
    // sendiri "nilai terakhir periode" harus menaruh angka itu di suatu
    // tempat, dan satu-satunya tempatnya adalah kedua sel total ini.
    await expect(page.getByTestId('by-unit-total-energy-export')).toHaveText('—')
    await recapToggle(page).click()
    await expect(recapBody(page)).toBeVisible()
    await expect(page.getByTestId('daily-total-energy-export')).toHaveText('—')

    // Keterangannya menyebut siapa yang menentukan "terakhir".
    await page.getByTestId('daily-energy-export-note').scrollIntoViewIfNeeded()
    await expect(page.getByTestId('daily-energy-export-note')).toContainText('ditentukan SERVER')
    await expect(page.getByTestId('energy-export-badge')).toHaveText('nilai terakhir')
  })

  test('skenario 16: ekspor energi tidak pernah terisi — keterangan tidak ada data, nol titik', async ({
    page,
  }) => {
    await stubApi(page, {
      summary: makeSummary({ daily_energy_export: { series: [], has_data: false } }),
    })

    await openFullReport(page)

    await page.getByTestId('energy-export-card').scrollIntoViewIfNeeded()
    await expect(page.getByTestId('energy-export-empty')).toBeVisible()
    await expect(page.getByTestId('energy-export-empty')).toContainText('bukan angka nol')
    await expect(page.getByTestId('daily-energy-export-empty')).toBeVisible()
    await expect(page.getByTestId('daily-energy-export-table')).toHaveCount(0)
    await expect(page.getByTestId('energy-export-row')).toHaveCount(0)
  })

  test('skenario 17: keterangan perbedaan agregasi terlihat DI TEMPAT yang diterangkannya', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    // "bukan rata-rata" di KARTU ENERGI.
    await expect(
      page.getByTestId('energy-export-card').getByTestId('energy-export-no-average-note'),
    ).toHaveCount(1)
    await page.getByTestId('energy-export-no-average-note').scrollIntoViewIfNeeded()
    await expect(page.getByTestId('energy-export-no-average-note')).toBeVisible()

    // "agregasi tren berbeda" di DEKAT TREN BEBAN.
    await expect(
      page
        .getByTestId('daily-trend-total-factory-load')
        .getByTestId('aggregation-difference-note'),
    ).toHaveCount(1)
    await page.getByTestId('aggregation-difference-note').scrollIntoViewIfNeeded()
    await expect(page.getByTestId('aggregation-difference-note')).toBeVisible()
    await expect(page.getByTestId('aggregation-difference-note')).toContainText('nilai terakhir')
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 18 + 19 + 20 + 21 + 30 — rekap teks dan rekap tabel       */
  /* ---------------------------------------------------------------- */

  test('skenario 18: satu slot terlihat di KEDUA rekap teks, di bawah judul terpisah', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    await page.getByTestId('maintenance-remarks').scrollIntoViewIfNeeded()
    await expect(page.getByTestId('maintenance-remarks')).toContainText(
      'Rekap Catatan Tindakan Perawatan (3 slot)',
    )
    await expect(page.getByTestId('maintenance-remark-row')).toHaveCount(3)

    await page.getByTestId('findings').scrollIntoViewIfNeeded()
    await expect(page.getByTestId('findings')).toContainText('Rekap Temuan (2 slot)')
    await expect(page.getByTestId('finding-row')).toHaveCount(2)

    const firstRemarkMeta = await page
      .getByTestId('maintenance-remark-row')
      .first()
      .locator('.note-meta')
      .innerText()
    const firstFindingMeta = await page
      .getByTestId('finding-row')
      .first()
      .locator('.note-meta')
      .innerText()

    // TANGGAL + SLOT yang SAMA pada kedua rekap, dengan teks masing-masing.
    expect(firstRemarkMeta).toBe(firstFindingMeta)
    await expect(page.getByTestId('findings-note')).toContainText('bukan penghitungan ganda')

    // Dan tidak ada satu tabel gabungan.
    await expect(page.getByTestId('free-text-combined')).toHaveCount(0)
  })

  test('skenario 19: kedua rekap teks kosong — keduanya tetap berjudul dengan keterangannya', async ({
    page,
  }) => {
    await stubApi(page, {
      summary: makeSummary({ maintenance_remarks: [], findings: [] }),
    })

    await openFullReport(page)

    await page.getByTestId('maintenance-remarks').scrollIntoViewIfNeeded()
    await expect(page.getByTestId('maintenance-remarks-empty')).toBeVisible()
    await page.getByTestId('findings').scrollIntoViewIfNeeded()
    await expect(page.getByTestId('findings-empty')).toBeVisible()
    await expect(page.getByTestId('maintenance-remark-row')).toHaveCount(0)
    await expect(page.getByTestId('finding-row')).toHaveCount(0)
  })

  test('skenario 20: teks panjang terbungkus UTUH dan halaman tidak menggulir mendatar', async ({
    page,
  }) => {
    const longText =
      'Ditemukan rembesan oli pada sambungan pipa lube oil genset 2 yang membasahi lantai '
      + 'sekitar panel sinkronisasi, sudah dibersihkan dan diberi penampung sementara, '
      + 'tetapi sambungannya perlu diganti pada kesempatan berhenti berikutnya karena '
      + 'rembesannya kembali muncul setelah beberapa jam beroperasi.'

    await stubApi(page, {
      summary: makeSummary({
        findings: [{ date: DAY_A, time_slot: '09:00', text: longText }],
      }),
    })

    await openFullReport(page)

    const noteText = page.getByTestId('finding-row').locator('.note-text')

    await noteText.scrollIntoViewIfNeeded()
    await expect(noteText).toBeVisible()
    // UTUH — tidak dipotong.
    expect((await noteText.innerText()).replace(/\s+/g, ' ')).toBe(longText)

    // Dan ia TERBUNGKUS, bukan terpotong: scrollHeight tidak melampaui
    // clientHeight, dan scrollWidth tidak melampaui clientWidth.
    const shape = await noteText.evaluate((element) => ({
      scrollWidth: element.scrollWidth,
      clientWidth: element.clientWidth,
      scrollHeight: element.scrollHeight,
      clientHeight: element.clientHeight,
      overflow: getComputedStyle(element).textOverflow,
      lineClamp: getComputedStyle(element).webkitLineClamp,
    }))

    expect(shape.scrollWidth).toBeLessThanOrEqual(shape.clientWidth + 1)
    expect(shape.scrollHeight).toBeLessThanOrEqual(shape.clientHeight + 1)
    expect(shape.overflow).toBe('clip')
    expect(['none', '', 'auto']).toContain(shape.lineClamp)

    await expectNoHorizontalPageScroll(page)
  })

  test('skenario 21: dua baris unit terlihat, termasuk unit tanpa pembacaan', async ({ page }) => {
    await stubApi(page)
    await openFullReport(page)

    await page.getByTestId('by-unit').scrollIntoViewIfNeeded()
    await expect(page.getByTestId('by-unit-row')).toHaveCount(2)
    await expect(page.getByTestId('by-unit')).toContainText('ER-1')
    await expect(page.getByTestId('by-unit')).toContainText('ER-2')

    const readingRows = await page
      .getByTestId('by-unit-reading-rows')
      .evaluateAll((elements) => elements.map((element) => element.textContent?.trim() ?? ''))

    expect(readingRows).toEqual(['180', '0'])
    await expect(page.getByTestId('by-unit-row-total')).toContainText('Gabungan 2 unit')
  })

  test('skenario 30: rekap harian tertutup secara bawaan, dan buka/tutup tidak memicu permintaan', async ({
    page,
  }) => {
    const state = await stubApi(page)

    await openFullReport(page)

    const summaryHitsBefore = state.hits.summary

    // CollapsibleSection memakai v-show: saat tertutup barisnya TETAP ADA di
    // DOM dengan display:none, jadi diasersi dengan toBeHidden() — tidak
    // pernah dengan toHaveCount(0).
    await expect(recapBody(page)).toBeHidden()

    await recapToggle(page).click()
    await expect(recapBody(page)).toBeVisible()
    await expect(page.getByTestId('daily-table').locator('tbody tr')).toHaveCount(12)

    await recapToggle(page).click()
    await expect(recapBody(page)).toBeHidden()

    // Datanya sudah ada di memori.
    expect(state.hits.summary).toBe(summaryHitsBefore)
    // Dan sesudah ditutup, angka utama tetap terbaca.
    await page.getByTestId('coverage').scrollIntoViewIfNeeded()
    await expect(page.getByTestId('coverage-percent')).toHaveText('88,8%')
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 20 + 30 — TATA LETAK, DIUKUR                              */
  /* ---------------------------------------------------------------- */

  test('skenario 30: GRAFIK TREN menggulir di dalam kartunya, kedua rekap BERTUMPUK, halaman tidak', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    // SATU-SATUNYA elemen ber-overflow-x di layar ini — dan fixture memberinya
    // DUA BELAS tanggal justru supaya ia benar-benar melampaui 390px.
    await page.getByTestId('daily-trend-total-factory-load').scrollIntoViewIfNeeded()
    await expect(page.locator('.chart-col')).toHaveCount(12)
    await expectCardScrollsInsideItself(page, '.chart-scroll')
    await expect(page.locator('.chart-scroll')).toHaveCount(1)
    await expect(page.getByTestId('daily-trend-scroll-hint')).toContainText('di dalam kartu ini')

    // KEDUA REKAP BERTUMPUK menjadi kartu, bukan menggulir mendatar — temuan
    // QA LP-M-02/LP-M-03, ditegakkan global oleh v-stacked-table +
    // src/assets/stacked-table.css pada ≤640px.
    await page.getByTestId('by-unit').scrollIntoViewIfNeeded()
    await expectCardStacksInsideItself(page, '[data-testid="by-unit-table"]')

    await recapToggle(page).click()
    await expect(recapBody(page)).toBeVisible()
    await expectCardStacksInsideItself(page, '[data-testid="daily-table"]')

    // Dan halaman SENDIRI tidak pernah menggulir mendatar — DIUKUR, bukan
    // disimpulkan dari markup.
    await expectNoHorizontalPageScroll(page)
  })

  test('grafik tren dapat digulir sampai kolom terakhirnya, halaman tetap tidak bergeser', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    await page.getByTestId('daily-trend-total-factory-load').scrollIntoViewIfNeeded()
    await expect(page.locator('.chart-col')).toHaveCount(12)

    const scrolled = await page.locator('.chart-scroll').evaluate((element) => {
      element.scrollLeft = element.scrollWidth

      return element.scrollLeft
    })

    expect(scrolled).toBeGreaterThan(0)
    await expect(page.locator('.chart-col').last()).toBeVisible()
    await expectNoHorizontalPageScroll(page)
  })

  test('titik tren tanpa pembacaan menghasilkan batang KOSONG, bukan batang nol', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    await page.getByTestId('daily-trend-total-factory-load').scrollIntoViewIfNeeded()

    const second = page.locator('.chart-col').nth(1)

    await expect(second.locator('.chart-value')).toHaveText('tidak tersedia')

    const height = await second.locator('.chart-fill').evaluate(
      (element) => element.getBoundingClientRect().height,
    )

    // Batang KOSONG (0%), bukan batang nol yang terbaca sebagai hasil ukur.
    expect(height).toBeLessThanOrEqual(1)
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 36 + 66 — ketiadaan penandaan, target, dan downtime        */
  /* ---------------------------------------------------------------- */

  test('skenario 36: nol penanda di luar batas terlihat, dan keterangannya UTUH', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    // Frekuensi 43 Hz dan busbar 310 V tergambar APA ADANYA.
    await page.getByTestId('metric-block-electrical-sync').scrollIntoViewIfNeeded()
    await expect(
      page.locator('[data-metric="electrical_sync_busbar_voltage_v"] .metric-row-item b').first(),
    ).toHaveText('310')

    // DISISIR ATAS NAMA KELAS pada kartu, bukan atas frasa: kalimat yang
    // menyatakan ketiadaannya sendiri memuat frasa "di luar batas".
    const flagged = await page.evaluate(() =>
      Array.from(document.querySelectorAll('[data-testid="metric-card"]'))
        .flatMap((card) => [card, ...Array.from(card.querySelectorAll('[class]'))])
        .flatMap((element) => element.className.toString().split(/\s+/))
        .filter((name) =>
          /warning|danger|error|alert|violation|out-of-range|breach|critical|over-limit/.test(name),
        ),
    )

    expect(flagged).toEqual([])

    // Dan keterangannya terlihat PENUH — diukur dari hasil render, bukan dari
    // sumbernya.
    const note = page.getByTestId('no-threshold-note')

    await note.scrollIntoViewIfNeeded()
    await expect(note).toBeVisible()
    await expect(note).toContainText('TIDAK ADA penandaan nilai di luar batas')
    await expect(note).toContainText('batas keselamatan kelistrikan')

    const shape = await note.evaluate((element) => ({
      scrollHeight: element.scrollHeight,
      clientHeight: element.clientHeight,
      textOverflow: getComputedStyle(element).textOverflow,
      lineClamp: getComputedStyle(element).webkitLineClamp,
      length: (element.textContent ?? '').length,
    }))

    expect(shape.length).toBeGreaterThan(600)
    // Tanpa pemotongan: seluruh isinya benar-benar terender, bukan terpotong.
    expect(shape.scrollHeight).toBeLessThanOrEqual(shape.clientHeight + 1)
    expect(shape.textOverflow).toBe('clip')
    expect(['none', '', 'auto']).toContain(shape.lineClamp)
    // Dan tidak ada kontrol "baca selengkapnya".
    await expect(page.getByRole('button', { name: /selengkapnya/i })).toHaveCount(0)
  })

  test('skenario 66: nol bagian downtime dan nol kolom target, keduanya DINYATAKAN', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    // Diasersi atas TESTID bagian, bukan atas substring 'downtime': pernyataan
    // yang membuktikan ketiadaannya sendiri memuat kata itu.
    for (const testId of ['downtime', 'downtime-summary', 'downtime-total', 'metric-target']) {
      await expect(page.getByTestId(testId), testId).toHaveCount(0)
    }

    await page.getByTestId('metric-blocks-note').scrollIntoViewIfNeeded()
    await expect(page.getByTestId('no-downtime-note')).toContainText(
      'tidak mencatat lama berhenti sama sekali',
    )
    await expect(page.getByTestId('no-target-note')).toContainText(
      'tidak punya tabel master target',
    )
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 3 + 4 + 5 + 6 + 7 — Production Line dan periode           */
  /* ---------------------------------------------------------------- */

  test('skenario 3: tanpa line — /summary TIDAK PERNAH diminta, juga sesudah ganti periode dan coba lagi', async ({
    page,
  }) => {
    const state = await stubApi(page, { productionLines: [LINE_1, LINE_2] })

    await openReport(page)

    // JALUR 1 — MUAT AWAL.
    await expect(page.getByTestId('production-line-select')).toBeVisible()
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    await expect(page.getByTestId('metric-card')).toHaveCount(0)
    await expect(page.getByTestId('coverage')).toHaveCount(0)
    // Tombol ekspor tidak dirender: berkas yang mencampur line adalah bentuk
    // kesalahan yang paling sulit dibantah setelah terkirim.
    await expect(page.getByTestId('export-button')).toHaveCount(0)
    expect(state.hits.summary).toBe(0)

    // JALUR 2 — GANTI PERIODE.
    await pickPeriod(page, 'per-1')
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    await pickPeriod(page, 'per-2')
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    expect(state.hits.summary).toBe(0)
    await expect(page.getByTestId('metric-card')).toHaveCount(0)

    // Dan TIDAK ada angka gabungan seluruh mill sebagai penggantinya.
    await expect(page.getByTestId('by-unit')).toHaveCount(0)
    await expect(page.getByTestId('completeness')).toHaveCount(0)
    expect(state.hits.summary).toBe(0)
  })

  test('skenario 3: jalur COBA LAGI pun tidak meminta ringkasan selama line kosong', async ({
    page,
  }) => {
    // Keadaan yang dapat DICAPAI dengan line kosong DAN tombol Coba Lagi
    // terender: daftar line gagal diambil. Inilah jalur ketiga yang akan
    // lolos bila penjagaannya ditaruh di template.
    const state = await stubApi(page, { productionLinesAbort: true })

    await openReport(page)

    await expect(page.getByTestId('production-line-unavailable')).toBeVisible()
    await expect(page.getByTestId('production-line-retry')).toBeVisible()
    expect(state.hits.summary).toBe(0)

    await page.getByTestId('production-line-retry').click()
    await expect(page.getByTestId('production-line-unavailable')).toBeVisible()
    expect(state.hits.summary).toBe(0)

    state.productionLinesAbort = false
    await page.getByTestId('production-line-retry').click()

    await expect(page.getByTestId('production-line-select')).toBeVisible()
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    // Daftarnya kini ada, line-nya BELUM dipilih — dan tetap nol permintaan
    // ringkasan, termasuk sesudah memilih periode.
    await pickPeriod(page, 'per-1')
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    expect(state.hits.summary).toBe(0)
  })

  test('skenario 4: line yang diingat perangkat dipakai tanpa diminta memilih lagi', async ({
    page,
  }) => {
    await stubApi(page, { productionLines: [LINE_1, LINE_2] })

    const userId = await getAuthUserId(page)

    // Kuncinya SAMA PERSIS dengan yang ditulis StationListView.vue.
    await page.addInitScript(
      ({ key, value }) => window.localStorage.setItem(key, value),
      { key: REMEMBERED_LINE_KEY(userId), value: 'pl-2' },
    )

    await openReport(page)

    await expect(page.getByTestId('production-line-select')).toHaveValue('pl-2')
    await pickPeriod(page, 'per-1')
    await expect(page.getByTestId('production-line-current')).toContainText('Line 2')
    await expect(page.getByTestId('metric-card')).toHaveCount(22)
  })

  test('line dari query rute dipakai dan DIUTAMAKAN atas ingatan perangkat', async ({ page }) => {
    await stubApi(page, { productionLines: [LINE_1, LINE_2] })

    const userId = await getAuthUserId(page)

    await page.addInitScript(
      ({ key, value }) => window.localStorage.setItem(key, value),
      { key: REMEMBERED_LINE_KEY(userId), value: 'pl-1' },
    )

    await openReport(page, `${ROUTE}?production_line_id=pl-2`)

    await expect(page.getByTestId('production-line-select')).toHaveValue('pl-2')
    // Dan nilai dari rute DITULIS KEMBALI sebagai ingatan.
    expect(
      await page.evaluate((key) => window.localStorage.getItem(key), REMEMBERED_LINE_KEY(userId)),
    ).toBe('pl-2')
  })

  test('TIDAK memilihkan line, bahkan ketika mill hanya punya SATU', async ({ page }) => {
    const state = await stubApi(page, { productionLines: [LINE_1] })

    await openReport(page)

    // DIVERGENSI YANG DISENGAJA dari LaporanEffluentPlantView, yang memilih
    // line secara otomatis bila mill hanya punya satu. Memilihkannya secara
    // diam-diam menerbitkan angka yang pengguna tidak pernah minta — dan pada
    // laporan per-line itu berarti pembaca menyangka sedang melihat seluruh
    // mill.
    await expect(page.getByTestId('production-line-select')).toBeVisible()
    await expectNothingSelected(page, 'production-line-select', 'Pilih Production Line')
    await expect(page.getByTestId('production-line-required-hint')).toContainText(
      'bahkan ketika mill ini hanya punya satu line',
    )
    await pickPeriod(page, 'per-1')
    expect(state.hits.summary).toBe(0)
  })

  test('skenario 5: penyimpanan perangkat tidak dapat dibaca — pemilih normal, tanpa galat', async ({
    page,
  }) => {
    const consoleErrors: string[] = []
    const pageErrors: string[] = []

    page.on('console', (message) => {
      if (message.type() === 'error') {
        consoleErrors.push(message.text())
      }
    })
    page.on('pageerror', (error) => pageErrors.push(error.message))

    await stubApi(page, { productionLines: [LINE_1, LINE_2] })

    // Dipasang SETELAH login, supaya sesi sudah tersimpan — yang dibuat gagal
    // adalah PEMBACAAN ingatan line, bukan sesi itu sendiri.
    await page.addInitScript(() => {
      const original = window.localStorage.getItem.bind(window.localStorage)

      window.localStorage.getItem = (key: string) => {
        if (key.startsWith('msl_production_line_')) {
          throw new Error('SecurityError: akses penyimpanan ditolak')
        }

        return original(key)
      }
    })

    await openReport(page)

    await expect(page.getByTestId('production-line-select')).toBeVisible()
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    // Keadaannya diperlakukan sebagai "tidak ada ingatan", bukan sebagai
    // galat — tidak ada pesan galat di halaman.
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('error-message')).toHaveCount(0)

    expect(pageErrors, pageErrors.join(' | ')).toHaveLength(0)
    expect(
      consoleErrors.filter((message) => message.includes('SecurityError')),
      consoleErrors.join(' | '),
    ).toHaveLength(0)
  })

  test('skenario 6: 403 — pilih line lagi, BUKAN galat jaringan dan nol angka mill lain', async ({
    page,
  }) => {
    await stubApi(page, { productionLines: [LINE_1, LINE_2], summaryStatus: 403 })

    await openReport(page)
    await pickLine(page, 'pl-2')
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('production-line-rejected')).toBeVisible()
    await expect(page.getByTestId('production-line-rejected')).toContainText(
      'pilih Production Line kembali',
    )
    // BUKAN galat jaringan, dan BUKAN pengalihan ke Login (403 bukan sesi
    // yang berakhir).
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('error-message')).toHaveCount(0)
    await expect(page.getByTestId('retry-button')).toHaveCount(0)
    expect(new URL(page.url()).pathname).toBe(ROUTE)
    // Pilihan line dikosongkan, dan nol angka tergambar.
    await expectNothingSelected(page, 'production-line-select', 'Pilih Production Line')
    await expect(page.getByTestId('metric-card')).toHaveCount(0)
    await expect(page.getByTestId('coverage')).toHaveCount(0)
  })

  test('404 pada /summary — pesan SERVER apa adanya, periode dikosongkan, tanpa coba lagi', async ({
    page,
  }) => {
    await stubApi(page, {
      summaryStatus: 404,
      // Pesan SERVER, bukan kalimat cadangan klien: yang diuji adalah bahwa
      // cabang 404 meneruskan pesan yang datang alih-alih mencetak teks yang
      // dipaku di view.
      summaryMessage: 'Periode Pelaporan yang dipilih tidak ditemukan pada mill Anda.',
    })

    await openReport(page)
    await pickLine(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('error-message')).toHaveText(
      'Periode Pelaporan yang dipilih tidak ditemukan pada mill Anda.',
    )
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('retry-button')).toHaveCount(0)
    await expectNothingSelected(page, 'period-select', 'Pilih Periode')
    await expect(page.getByTestId('metric-card')).toHaveCount(0)
  })

  test('skenario 25: 422 akun tanpa mill — arahan menghubungi Admin, bukan galat jaringan', async ({
    page,
  }) => {
    await stubApi(page, { summaryStatus: 422 })

    await openReport(page)
    await pickLine(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('mill-not-linked')).toBeVisible()
    await expect(page.getByTestId('mill-not-linked')).toContainText('hubungi Admin')
    await expect(page.getByTestId('mill-not-linked')).toContainText('bukan masalah jaringan')
    // TIDAK ada pesan jaringan, dan TIDAK ada pemilih mill sebagai gantinya.
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.getByTestId('metric-card')).toHaveCount(0)
  })

  test('skenario 7: mill belum punya periode — arahan Admin, pemilih kosong', async ({ page }) => {
    await stubApi(page, { periods: [] })

    await openReport(page)
    // LINE DIPILIH LEBIH DULU, dan itu bukan kerapian: keterangan "mill belum
    // punya periode" dan arahan "pilih Production Line" berada pada SATU
    // rantai v-if/v-else-if, dan arahan line berada LEBIH DULU di rantai itu.
    // Selama belum ada line yang berlaku, keterangan periode-kosong karena itu
    // tidak terender — dan itu urutan yang benar: memilih line adalah langkah
    // yang harus dikerjakan pengguna lebih dulu. Diperiksa langsung: tanpa
    // baris ini yang terlihat adalah production-line-required-hint.
    await pickLine(page)

    await expect(page.getByTestId('no-periods')).toContainText('hubungi Admin')
    await expect(page.getByTestId('period-select').locator('option')).toHaveCount(1)
    await expect(page.getByTestId('metric-card')).toHaveCount(0)
    // Keadaannya BUKAN keadaan galat.
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('error-message')).toHaveCount(0)
  })

  test('skenario 38: pemilih periode memuat TEPAT periode Engine Room dari server', async ({
    page,
  }) => {
    await stubApi(page)

    await openReport(page)

    const options = page.getByTestId('period-select').locator('option')

    await expect(options).toHaveCount(3)
    await expect(options.nth(1)).toHaveText('Engine Room — Periode Uji Engine Room')
    await expect(options.nth(2)).toHaveText('Engine Room — Periode Sebelumnya')
    // Nama periode stasiun lain tidak pernah muncul: klien tidak menyaring
    // ulang, server yang menyaringnya.
    await expect(page.getByTestId('period-select')).not.toContainText('Sterilizer')
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 26 + 27 + 28 + 29 — galat, luring, ekspor                 */
  /* ---------------------------------------------------------------- */

  test('skenario 27: kegagalan jaringan — pesan + Coba Lagi, pilihan tidak hilang', async ({
    page,
  }) => {
    const state = await stubApi(page, { summaryAbort: true })

    await openReport(page)
    await pickLine(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('network-error')).toBeVisible()
    await expect(page.getByTestId('retry-button')).toBeVisible()
    await expect(page.getByTestId('metric-card')).toHaveCount(0)
    // Pengguna di area tanpa sinyal tidak boleh dipaksa memilih dari awal.
    await expect(page.getByTestId('period-select')).toHaveValue('per-1')
    await expect(page.getByTestId('production-line-select')).toHaveValue('pl-1')

    state.summaryAbort = false

    await page.getByTestId('retry-button').click()

    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('metric-card')).toHaveCount(22)
  })

  test('sesi berakhir (401) — diarahkan ke Login tanpa pesan jaringan', async ({ page }) => {
    await stubApi(page, { summaryStatus: 401 })

    await openReport(page)
    await pickLine(page)
    await pickPeriod(page, 'per-1')

    // PATHNAME, bukan pola glob dengan baseURL.
    await expectPathname(page, '/login')

    // "Periksa koneksi Anda" pada sesi yang berakhir mengirim pembaca
    // memeriksa sinyalnya alih-alih masuk kembali.
    await expect(page.getByTestId('network-error')).toHaveCount(0)
  })

  test('skenario 28: luring DINYATAKAN sesudah aplikasi bootstrap, bukan layar yang diam', async ({
    page,
    context,
  }) => {
    await stubApi(page)

    // LURING DISETEL SETELAH LAYAR TERBUKA. Rutenya lazy-import, jadi
    // menyetel luring sebelum chunk-nya terunduh membuat navigasi
    // MENGGANTUNG alih-alih gagal — alasan yang sama dicatat pada
    // tests/e2e/dashboard-reporting.spec.ts.
    await openFullReport(page)

    await context.setOffline(true)

    // Pendengar 'offline' dipasang di onMounted, jadi keadaannya DINYATAKAN
    // tanpa memuat ulang halaman.
    await expect(page.getByTestId('offline-notice')).toBeVisible()
    await expect(page.getByTestId('offline-notice')).toContainText('memerlukan jaringan')
    await expect(page.getByTestId('offline-notice')).toContainText(
      'tidak disimpan di perangkat ini',
    )
    // Layar tidak diam tanpa penjelasan.
    await expect(page.getByTestId('laporan-engine-room-mobile')).toBeVisible()

    await context.setOffline(false)

    await expect(page.getByTestId('offline-notice')).toHaveCount(0)
  })

  test('skenario 26: periode tertutup — laporan penuh, badge Tertutup, ekspor tetap aktif', async ({
    page,
  }) => {
    await stubApi(page)

    await openReport(page)
    await pickLine(page)
    await pickPeriod(page, 'per-2')

    await expect(page.getByTestId('period-status-badge')).toContainText('Tertutup')
    await expect(page.getByTestId('metric-card')).toHaveCount(22)
    await expect(page.getByTestId('by-unit')).toBeVisible()
    // Kunci periode mengatur PENULISAN data, bukan pembacaan laporan.
    await expect(page.getByTestId('export-button')).toBeVisible()
    await expect(page.getByTestId('export-button')).not.toBeDisabled()

    const download = page.waitForEvent('download')

    await page.getByTestId('export-button').click()

    const file = await download

    expect(file.suggestedFilename()).toMatch(/\.csv$/)
    await file.delete()
  })

  test('skenario 35: ekspor CSV saja — unduhan terpicu, dan klik ganda cepat mengirim SATU permintaan', async ({
    page,
  }) => {
    // TUNDAAN WAJIB — lihat catatan pada ApiState.exportDelayMs. Tanpa ini
    // ketukan kedua mendarat setelah `exporting` kembali false, dua
    // permintaan terkirim, dan penjagaannya tidak sekali pun teruji.
    const state = await stubApi(page, { exportDelayMs: 1500 })

    await openFullReport(page)

    await expect(page.getByTestId('export-button')).toBeVisible()
    // Hanya SATU tombol ekspor di seluruh layar, dan labelnya CSV.
    await expect(page.locator('[data-testid^="export-"]')).toHaveCount(1)
    await expect(page.getByTestId('export-button')).toContainText('Ekspor CSV')

    const download = page.waitForEvent('download')

    // DUA ketukan cepat: `exporting` mengunci tombol sesaat selama berkas
    // disiapkan, jadi ketukan kedua mendarat pada tombol yang DISABLED dan
    // browser tidak meneruskannya. force:true dipakai justru supaya
    // Playwright tidak menolak ketukan itu lebih dulu — yang diuji adalah
    // penjagaan APLIKASI, bukan penjagaan alat uji.
    await page.getByTestId('export-button').click()
    await expect(page.getByTestId('export-button')).toBeDisabled()
    await page.getByTestId('export-button').click({ force: true })

    const file = await download

    expect(file.suggestedFilename()).toMatch(/\.csv$/)
    expect(file.suggestedFilename()).not.toMatch(/xlsx/i)
    expect(file.suggestedFilename()).toContain('laporan-engine-room')
    await file.delete()

    await expect.poll(() => state.hits.export).toBe(1)

    // Berkas yang mencampur line adalah bentuk kesalahan yang paling sulit
    // dibantah setelah terkirim.
    const exportUrl = state.urls.find((url) => url.includes('/export')) ?? ''

    expect(exportUrl).toContain('period_id=per-1')
    expect(exportUrl).toContain('production_line_id=pl-1')
    expect(exportUrl).toContain('format=csv')
    expect(exportUrl).not.toContain('excel')
    expect(exportUrl).not.toContain('xlsx')

    // Dan tidak ada satu pun tawaran .xlsx di layar.
    const body = (await page.locator('body').innerText()).toLowerCase()

    expect(body).not.toContain('xlsx')
    expect(body).not.toContain('excel')
  })

  /**
   * TEMUAN — 422 EXPORT_FAILED TIDAK SAMPAI KE LAYAR SEBAGAI PESAN SERVER,
   * DAN SEBABNYA ADA DI apiClient, BUKAN DI LAYAR INI.
   *
   * unit_test_case 38 dan implementation_note layar ini menuntut pesan 422
   * EXPORT_FAILED ditampilkan APA ADANYA. Di browser sungguhan ia TIDAK:
   * permintaan /export memakai `responseType: 'blob'`, jadi
   * `error.response.data` adalah Blob — dan apiClient.normalizeError membaca
   * `data?.message`, yang pada Blob undefined. Hasilnya pesan cadangan
   * generik 'Terjadi kesalahan. Silakan coba lagi.' dengan `status` 422 tetap
   * utuh.
   *
   * Yang diasersi di sini karena itu adalah perilaku yang SEBENARNYA, bukan
   * pesan yang tidak pernah muncul: 422 pada /export masuk ke cabang
   * errorMessage (BUKAN ke arahan "hubungi Admin" milik 422 /periods dan
   * /summary), tanpa tombol Coba Lagi, dan dengan SATU permintaan saja.
   * Asersi "verbatim" ada di LaporanEngineRoomView.spec.ts, tempat repo
   * memang menyerahkan galat datar ber-`message` — itulah kontrak VIEW-nya,
   * dan view memenuhinya.
   *
   * Perbaikannya menyentuh apiClient (membaca Blob 422 sebagai teks sebelum
   * normalisasi) dan karena itu berlaku bagi KEDUA BELAS laporan mobile
   * saudaranya sekaligus — bukan milik putaran layar ini. Dilaporkan terpisah.
   */
  test('422 EXPORT_FAILED masuk ke cabang pesan galat, bukan arahan mill, dengan SATU permintaan', async ({
    page,
  }) => {
    const state = await stubApi(page, { exportStatus: 422 })

    await openFullReport(page)
    await page.getByTestId('export-button').click()

    await expect(page.getByTestId('error-message')).toBeVisible()
    // BUKAN arahan "hubungi Admin": satu kode HTTP yang sama membawa dua
    // arti, dan pemanggillah yang memutuskan — pemetaan itu BENAR dan
    // terbukti di sini.
    await expect(page.getByTestId('mill-not-linked')).toHaveCount(0)
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('retry-button')).toHaveCount(0)
    // Dan tidak ada pengalihan ke Login: 422 bukan sesi yang berakhir.
    expect(new URL(page.url()).pathname).toBe(ROUTE)
    // SATU permintaan: tidak ada upaya memotong, memecah, atau mengulang
    // dengan rentang lebih kecil.
    await expect.poll(() => state.hits.export).toBe(1)
    // Angka laporan yang sedang tampil TIDAK hilang karena ekspor gagal...
    // kecuali memang begitu: handleError mengosongkan summary pada cabang
    // ini. Dicatat apa adanya alih-alih diasersi ke arah yang diinginkan.
    await expect(page.getByTestId('metric-card')).toHaveCount(0)
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 39 — LAYAR BACA SAJA                                      */
  /* ---------------------------------------------------------------- */

  test('skenario 39: layar hanya membaca — nol kontrol tulis, nol permintaan POST/PUT/PATCH/DELETE', async ({
    page,
  }) => {
    const writes: string[] = []

    page.on('request', (request) => {
      if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method())) {
        writes.push(`${request.method()} ${request.url()}`)
      }
    })

    await stubApi(page)
    await openFullReport(page)

    // Seluruh isi layar ditelusuri dari atas sampai bawah.
    await recapToggle(page).click()
    await expect(recapBody(page)).toBeVisible()
    await page.getByTestId('completeness').scrollIntoViewIfNeeded()

    const footerButtons = page.locator('.action-footer button')

    await expect(footerButtons).toHaveCount(2)
    await expect(footerButtons.nth(0)).toContainText('Ekspor')
    await expect(footerButtons.nth(1)).toContainText('Back')

    await expect(
      page.getByRole('button', { name: /Simpan|Hapus|Ubah|Verifikasi|Tambah/i }),
    ).toHaveCount(0)
    await expect(page.locator('input')).toHaveCount(0)
    await expect(page.locator('textarea')).toHaveCount(0)
    await expect(page.locator('form')).toHaveCount(0)

    // Dan tidak satu pun permintaan tulis pada catatan jaringan sepanjang
    // perjalanan ini (login sudah selesai sebelum pendengar dipasang).
    expect(writes, writes.join(' | ')).toHaveLength(0)
  })

  test('tidak ada endpoint lain yang muncul pada catatan jaringan', async ({ page }) => {
    const state = await stubApi(page)

    await openFullReport(page)

    // KETIGA rute data saja, plus daftar line yang dipakai ULANG dari
    // screen-135. Tidak ada endpoint keempat yang dikarang di layar ini.
    for (const url of state.urls) {
      expect(url, url).toMatch(/\/api\/engine-room-reports\/(periods|summary|export)/)
    }
    expect(state.hits.periods).toBeGreaterThan(0)
    expect(state.hits.summary).toBeGreaterThan(0)
    expect(state.hits.lines).toBeGreaterThan(0)
    expect(state.hits.units).toBe(0)
  })

  test('Back kembali ke Dashboard & Reporting', async ({ page }) => {
    await stubApi(page)
    await openReport(page)

    await page.getByTestId('back-button').click()
    await expectPathname(page, '/dashboard-reporting')
  })
})

/* ================================================================== */
/* Pintu masuk — tile Engine Room pada layar pemilih stasiun            */
/* ================================================================== */

/**
 * SEMUA NAVIGASI DI SINI LEWAT KLIK, BUKAN page.goto(). Tabel `station` lokal
 * hidup di memori (sql.js) dan isinya hilang pada setiap muat ulang halaman,
 * jadi page.goto() akan menghapus hasil penyemaian dan grid tile kembali
 * kosong. Alasan yang sama dicatat pada docblock
 * tests/e2e/reporting-pilih-stasiun.spec.ts.
 */
async function seedLocalStations(page: Page): Promise<void> {
  await page.getByTestId('menu-card-production-process-activity').click()
  await page.waitForURL((url) => url.pathname === '/stations')

  const picker = page.getByTestId('production-line-picker')
  const firstTile = page.locator('[data-testid^="station-tile-"]').first()

  await expect(picker.or(firstTile).first()).toBeVisible({ timeout: 15_000 })

  if (await picker.isVisible()) {
    await page.locator('[data-testid^="production-line-option-"]').first().click()
  }

  await expect(firstTile).toBeVisible({ timeout: 15_000 })

  await page.getByTestId('breadcrumb-home').click()
  await page.waitForURL((url) => url.pathname === '/home')
}

/** Home → Dashboard & Reporting → Reporting, seluruhnya lewat klik. */
async function goToReports(page: Page): Promise<void> {
  await page.getByTestId('menu-card-dashboard-reporting').click()
  await page.waitForURL((url) => url.pathname === '/dashboard-reporting')

  await page.getByTestId('menu-card-reporting').click()
  await page.waitForURL((url) => url.pathname === '/reports')
  await expect(page.getByTestId('station-grid')).toBeVisible()
}

test.describe('Pintu masuk dari Reporting (screen-141)', () => {
  test.use({ viewport: { width: 390, height: 844 } })

  /**
   * SATU BARIS yang menentukan layar ini dapat dicapai: entri 'engine-room'
   * pada REPORT_ROUTES di ReportingPilihStasiunView.vue — DENGAN TANDA HUBUNG
   * dan TERKUTIP. Kunci bergaris-bawah ('engine_room') akan terkompilasi,
   * terlihat benar, dan tidak pernah cocok dengan satu station.type pun.
   * Tanpa test ini, layar laporan bisa lengkap, seluruh test lainnya hijau,
   * dan tile-nya tetap kelabu — pola kegagalan yang sudah terjadi sekali pada
   * laporan Cages & Tracks versi web.
   */
  test('skenario 1: tile Engine Room aktif dan menavigasi ke layar laporannya', async ({ page }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await seedLocalStations(page)
    await goToReports(page)

    const tile = page.getByTestId('station-tile-engine-room')

    await tile.scrollIntoViewIfNeeded()
    await expect(tile).toBeVisible()
    await expect(tile).toHaveAttribute('aria-disabled', 'false')
    await tile.click()

    // Alamatnya membawa ?production_line_id= dari layar ini, jadi yang
    // dibandingkan adalah PATHNAME-nya saja.
    await expectPathname(page, ROUTE)
    await expect(page.getByTestId('laporan-engine-room-mobile')).toBeVisible()

    // Dan tidak ada pesan "belum tersedia" yang muncul sebagai gantinya.
    await expect(page.getByTestId('info-message')).toHaveCount(0)
  })
})
