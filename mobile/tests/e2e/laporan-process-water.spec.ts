import { expect, test, type Page } from '@playwright/test'
import { getAuthUserId, login, USERS } from './helpers'

/**
 * laporan-process-water.spec.ts — screen-161--laporan-process-water-mobile /
 * usecase-167--laporan-process-water-mobile.
 *
 * Satu test per test_scenarios yang punya browser_test. Berjalan di Vite dev
 * server (playwright.config.ts, baseURL http://localhost:5174): aplikasi
 * Capacitor adalah SPA biasa sebelum dibungkus native, jadi browser test layar
 * mobile TIDAK ditangguhkan sebagai "mobile-only".
 *
 * VIEWPORT PONSEL, BUKAN DESKTOP. Berkas ini menimpa viewport bawaan proyek
 * chromium (Desktop Chrome, 1280px) dengan 390x844. Separuh klaim layar ini
 * adalah klaim tata letak ("kelengkapan terlihat tanpa menggulir", "halaman
 * tidak menggulir mendatar walau rekapnya 12 kolom", "pernyataan ketiadaan
 * penandaan terbaca UTUH"), dan seluruhnya hampa pada kanvas 1280px: apa pun
 * muat di sana.
 *
 * ────────────────────────────────────────────────────────────────────────
 * RESPONS LAPORAN DI-STUB DI TINGKAT JARINGAN (page.route), BUKAN DISEMAI —
 * DAN ITULAH YANG MENJAGA BASIS DATA DEV
 * ────────────────────────────────────────────────────────────────────────
 * Keputusan dan alasan yang sama dengan ketiga belas laporan mobile lain:
 *
 *   - Layar ini read-only total dan tidak punya satu pun UI untuk membuat
 *     data yang dibacanya.
 *   - Kebenaran ANGKA-nya bukan tanggung jawab layar ini: seluruhnya dari
 *     ProcessWaterReportService, yang sudah diuji penuh ketika screen-160
 *     dibangun. Yang khas mobile — dan hanya dapat dibuktikan di sini —
 *     adalah PEMETAAN respons itu ke layar satu kolom dengan sembilan kartu
 *     dalam empat blok, dan ketiga rekapnya yang bertumpuk menjadi kartu.
 *   - Beberapa skenario mustahil dibuat dari data sungguhan tanpa merusak
 *     lingkungan (sesi kedaluwarsa, jaringan putus, 403 line milik mill lain,
 *     periode belum mulai, muatan yang sengaja tidak konsisten secara
 *     aritmetika, penyimpanan perangkat yang melempar).
 *   - DAN YANG PALING MENGIKAT: backend di :8000 adalah DATABASE DEV — data
 *     kerja pengguna. Berkas ini karena itu TIDAK menulis, menghapus,
 *     menyemai, atau memigrasi apa pun di sana. Yang menyentuh backend
 *     sungguhan HANYA login (POST /api/login), seperti ketiga belas spec
 *     laporan mobile lainnya. TIDAK ADA `migrate`, `migrate:fresh`, `--seed`,
 *     maupun `db:prepare` di jalur spec ini.
 *
 * TIDAK ADA BACKEND :8001 DI SINI. `test_strategy.browser_test.start_command`
 * menyebut port :8001 dan `db:prepare` — itu menggambarkan suite e2e-web/ di
 * akar proyek, BUKAN jalur layar ini, dan spec ini tidak pernah
 * menyentuhnya. Dev server :8000 pun sudah berjalan; berkas ini tidak
 * menyalakan proses kedua di port itu.
 *
 * ── LIMA HAL YANG HANYA BROWSER DAPAT MEMBUKTIKAN DI LAYAR INI ─────────
 *
 * 1. PERNYATAAN KETIADAAN PENANDAAN DI LUAR BATAS TERBACA UTUH pada 390px —
 *    DIUKUR pada hasil render: scrollHeight lawan clientHeight, plus
 *    `textOverflow` dan `webkitLineClamp` dari CSS HASIL COMPILE. Keterangan
 *    yang terpotong di tengah berhenti menerangkan apa pun, dan di layar
 *    sempit itulah godaan terbesarnya.
 *
 * 2. HALAMAN TIDAK PERNAH MENGGULIR MENDATAR, DIUKUR — bukan disimpulkan dari
 *    markup. Ketiga rekapnya membawa sampai DUA BELAS kolom angka, dan
 *    ketiganya BERTUMPUK menjadi kartu lewat v-stacked-table (temuan QA
 *    LP-M-02/LP-M-03). Satu-satunya elemen yang MENGGULIR MENDATAR DI DALAM
 *    KARTUNYA adalah grafik tren aliran air baku (.chart-scroll) — dan
 *    fixture di bawah memberinya DUA BELAS tanggal justru supaya ia
 *    benar-benar melampaui 390px.
 *
 * 3. SATUAN KEDUA KARTU DOSIS BENAR-BENAR TERCETAK pada kanvas 390px, dibaca
 *    SATU KARTU PADA SATU WAKTU — locator di-scope ke kartunya, bukan ke
 *    halaman. Alum kg/jam melawan polimer g/jam: skala berbeda SERIBU KALI,
 *    dan di ponsel kedua kartu tidak pernah terlihat bersamaan.
 *
 * 4. KELENGKAPAN BERADA DI ATAS KARTU PERTAMA — diukur dari POSISI KOTAK DI
 *    VIEWPORT, bukan dari urutan sumbernya.
 *
 * 5. SETIAP KELAS CSS YANG DIPAKAI MARKUP PUNYA DEFINISI DI CSS HASIL
 *    COMPILE. Kelas tanpa definisi tidak menggagalkan satu asersi sumber pun
 *    — ia hanya membuat bagian layar kehilangan gayanya tanpa satu tanda.
 *    Lima kelas tanpa definisi pernah lolos 25 test Livewire dan 19 spec
 *    browser di kelola-machinery.blade.php; pemeriksaannya karena itu membaca
 *    document.styleSheets, bukan berkas sumber.
 *
 * ── KONVENSI YANG HARGANYA SUDAH DIBAYAR ───────────────────────────────
 *
 * LAYAR INI TIDAK MEMILIHKAN LINE, bahkan ketika mill hanya punya satu.
 * Karena itu setiap test di bawah MEMILIH line lewat pemilihnya (atau
 * menyemai ingatan perangkat lebih dulu); test yang langsung memilih periode
 * akan menunggu angka yang memang tidak akan pernah datang.
 *
 * KETIADAAN DIASERSI ATAS TESTID DAN JUDUL, BUKAN ATAS PROSA. Layar ini WAJIB
 * merender kalimat yang menyebut 'sebaran', 'downtime', 'di luar batas',
 * 'Ekspor Excel', dan '.xlsx' — seluruhnya untuk menerangkan ketiadaannya
 * SENDIRI. Pemindaian prosa (termasuk `body.innerText`) karena itu MEMERAH
 * pada layar yang BENAR, dan tidak dipakai satu kali pun di berkas ini. Ini
 * penyimpangan SADAR dari laporan-engine-room.spec.ts, yang memang boleh
 * menyisir `body.innerText` untuk 'excel' karena layarnya tidak menyebutnya.
 *
 * ALAMAT DIBANDINGKAN ATAS PATHNAME, bukan dengan toHaveURL('**\/x'): dengan
 * baseURL terpasang, pola glob semacam itu TIDAK PERNAH cocok dan test-nya
 * "lulus" tanpa pernah menguji apa pun. Di sini dipakai waitForURL dengan
 * PREDIKAT atas URL yang sudah terurai.
 *
 * SETIAP count() DIDAHULUI ASERSI YANG MENUNGGU SENDIRI (toBeVisible /
 * toHaveCount). count() tanpa penantian mengembalikan 0 pada halaman yang
 * belum selesai merender, dan 0 adalah jawaban yang diam-diam meluluskan
 * asersi "tidak ada".
 *
 * KETIGA REKAP MEMAKAI v-show (CollapsibleSection), BUKAN v-if: saat tertutup
 * barisnya TETAP ADA di DOM dengan display:none. Karena itu isinya DIBUKA
 * lewat ketiga kontrol `collapsible-section-toggle` sebelum diasersi, dan
 * keadaan tertutup diasersi dengan toBeHidden() — tidak pernah dengan
 * toHaveCount(0).
 *
 * LURING DISETEL SETELAH LAYAR TERBUKA, bukan sebelumnya: rutenya
 * lazy-import, jadi menyetel luring sebelum chunk-nya terunduh membuat
 * navigasi MENGGANTUNG alih-alih gagal.
 *
 * PRASYARAT APA PUN TETAP DI DALAM STUB. Sebuah prasyarat tulis lintas layar
 * membuat beforeAll MENGGANTUNG alih-alih gagal; berkas ini tidak punya satu
 * pun, dan login adalah satu-satunya permintaan sungguhannya.
 *
 * TANGGAL FIXTURE RELATIF terhadap hari ini — tidak ada tanggal kalender yang
 * ditulis mati, dan nama periodenya pun bukan nama bulan, supaya slug nama
 * berkas ekspor tidak ikut basi. Tidak ada satu pun asersi "tepat N record
 * hari ini": seluruh angka datang dari stub bertanggal tetap-relatif, bukan
 * dari factory bertanggal acak.
 *
 * MENJALANKANNYA MENUNTUT DUA PROSES: backend di :8000 (dengan seeder
 * DemoAccountSeeder sudah dijalankan — login di sini POST sungguhan) dan Vite
 * dev server di :5174 (dinyalakan playwright.config.ts sendiri lewat blok
 * webServer, reuseExistingServer).
 */

const ROUTE = '/reports/process-water'

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

const PERIOD_START = isoDaysFromNow(-14)
const PERIOD_END = isoDaysFromNow(-1)
const PREV_START = isoDaysFromNow(-40)
const PREV_END = isoDaysFromNow(-15)

/** DUA BELAS tanggal — lihat catatan #2 pada docblock berkas. */
const SERIES_DATES = Array.from({ length: 12 }, (_unused, index) => isoDaysFromNow(-13 + index))
const DAY_A = SERIES_DATES[0]
const DAY_B = SERIES_DATES[1]

/* ------------------------------------------------------------------ */
/* Fixture — BENTUK RESPONS NYATA                                      */
/* ------------------------------------------------------------------ */

const PERIOD = {
  id: 'per-1',
  name: 'Periode Uji Process Water',
  start_date: PERIOD_START,
  end_date: PERIOD_END,
  status: 'open',
  // DENGAN TANDA HUBUNG. Garis bawah di situ tidak cocok dengan satu baris
  // period_stations pun, dan server menjawab [] tanpa satu galat.
  station_type: 'process-water',
  station_type_label: 'Process Water',
}

const PERIOD_CLOSED = {
  id: 'per-2',
  name: 'Periode Sebelumnya',
  start_date: PREV_START,
  end_date: PREV_END,
  status: 'closed',
  station_type: 'process-water',
  station_type_label: 'Process Water',
}

const LINE_1 = { id: 'pl-1', name: 'Line 1', code: 'L1' }
const LINE_2 = { id: 'pl-2', name: 'Line 2', code: 'L2' }

/** Kunci ingatan line, ditulis StationListView.vue dan dibaca layar ini. */
const REMEMBERED_LINE_KEY = (userId: string) => `msl_production_line_${userId}`

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
]

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

/** KESEMBILAN penyebut, SELURUHNYA BERBEDA, dan seluruhnya < filled_slots 96. */
const READING_COUNTS: Record<string, number> = {
  raw_water_flow_m3h: 12,
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
  { testId: 'pw-metric-block-aliran-air', title: 'Aliran Air', cards: 2 },
  { testId: 'pw-metric-block-softener', title: 'Softener', cards: 2 },
  { testId: 'pw-metric-block-dosis-kimia', title: 'Dosis Kimia', cards: 2 },
  { testId: 'pw-metric-block-air-umpan-boiler', title: 'Air Umpan Boiler', cards: 3 },
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

/** Prosa SERVER — dirender VERBATIM, diasersi dengan KESAMAAN PENUH. */
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

/**
 * Kesembilan entri.
 *
 * alum_dosing_kgh membawa avg 9.9 di LUAR rentang min 1.8 .. max 3.1:
 * mustahil dari perhitungan apa pun, dan itulah yang membuat "nol aritmetika
 * di klien" dapat dibuktikan DI LAYAR.
 *
 * pH masuk softener 3,2 dan TDS air umpan boiler 9800 ada di sini justru
 * karena keduanya terlihat "mencurigakan": NOL elemen penanda tetap benar.
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

function emptyMetrics(): Record<string, Record<string, unknown>> {
  return Object.fromEntries(
    METRIC_COLUMNS.map((column) => [
      column,
      { min: null, avg: null, max: null, reading_count: 0, group: METRIC_GROUPS[column] },
    ]),
  )
}

function unitMetrics(avg: number | null): Record<string, unknown> {
  return Object.fromEntries(METRIC_COLUMNS.map((column) => [column, metric(column, avg, avg, avg)]))
}

/** TIGA kejadian BERTEKS IDENTIK pada tiga slot berbeda. */
function actionTakenEntries(): Array<Record<string, unknown>> {
  return [
    { date: DAY_A, time_slot: '08:00', text: 'Sudah ditangani' },
    { date: DAY_A, time_slot: '14:00', text: 'Sudah ditangani' },
    { date: DAY_B, time_slot: '21:00', text: 'Sudah ditangani' },
  ]
}

/** Entri PERTAMA-nya memakai date + time_slot yang SAMA dengan di atas. */
function findingEntries(): Array<Record<string, unknown>> {
  return [
    { date: DAY_A, time_slot: '08:00', text: 'Kesadahan keluaran softener naik tajam' },
    { date: DAY_B, time_slot: '03:00', text: 'Dosis polimer terasa tidak stabil' },
  ]
}

function dailyRows(): Array<Record<string, unknown>> {
  return SERIES_DATES.map((date, index) => ({
    date,
    reading_rows: 4 + index,
    metrics_avg: {
      raw_water_flow_m3h:
        index === 1
          ? { avg: null, reading_count: 0 }
          : { avg: 100 + index * 3.5, reading_count: 4 + index },
      clarified_water_flow_m3h: { avg: null, reading_count: 0 },
    },
  }))
}

/**
 * Payload /summary, sengaja bertentangan dengan dirinya sendiri — lihat
 * docblock berkas. JANGAN "merapikan" angka-angka ini.
 */
function makeSummary(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Sungai Rambutan' },
    production_line: { id: 'pl-1', name: 'Line 1' },
    period: {
      id: 'per-1',
      name: 'Periode Uji Process Water',
      start_date: PERIOD_START,
      end_date: PERIOD_END,
      status: 'open',
    },
    has_data: true,
    coverage: {
      // 96 MELEBIHI penyebut setiap kartu (tertinggi 24).
      filled_slots: 96,
      expected_slots: 480,
      // 96/480 = 20,0 — dan server mengirim 77,7.
      coverage_percent: 77.7,
      process_water_unit_count: 2,
      slots_per_unit_per_day: 24,
      days_in_period: 14,
      days_counted: 10,
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
      raw_water_flow_m3h: SERIES_DATES.map((date, index) => ({
        date,
        // null — BUKAN 0: batangnya KOSONG, bukan batang nol.
        avg: index === 1 ? null : 100 + index * 3.5,
        reading_count: index === 1 ? 0 : 4 + index,
      })),
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
        // Unit yang punya record tetapi TIDAK SATU PUN pembacaan.
        process_water_id: 'PW-2',
        reading_rows: 0,
        reading_count: 0,
        days_with_rows: 0,
        metrics: unitMetrics(null),
      },
    ],
    daily: dailyRows(),
    // Jumlah baris hariannya bukan 777 — layar memakai angka SERVER.
    daily_total: { reading_rows: 777 },
    total: { days_with_records: 12, reading_rows: 96 },
    _note_absences: NOTE_ABSENCES,
    ...overrides,
  }
}

/** Periode yang PUNYA record tetapi tanpa satu slot terisi: 0,0%, BUKAN '—'. */
function emptySummary(): Record<string, unknown> {
  return makeSummary({
    has_data: false,
    coverage: {
      filled_slots: 0,
      expected_slots: 480,
      coverage_percent: 0,
      process_water_unit_count: 2,
      slots_per_unit_per_day: 24,
      days_in_period: 14,
      days_counted: 14,
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
  })
}

/** Periode BELUM MULAI: penyebutnya belum terbentuk, percent null. */
function notStartedSummary(): Record<string, unknown> {
  return makeSummary({
    has_data: false,
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
    text_entries: { action_taken_status: [], findings: [] },
    inspector_recap: { entries: [], not_recorded: 0, recorded_slots: 0 },
    daily_trend: { raw_water_flow_m3h: [] },
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
  summaryStatus: number
  summaryMessage: string
  /** true = permintaan ringkasan gagal di tingkat JARINGAN (tanpa status). */
  summaryAbort: boolean
  /** Sekali gagal, lalu sukses — dipakai test Coba Lagi. */
  summaryAbortOnce: boolean
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
    summaryAbortOnce: false,
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

  await page.route('**/api/process-water-reports/**', async (route) => {
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
      // diam-diam menembus ke backend dev.
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

      // 21 KOLOM: 7 konteks + Slot Waktu + 13 kolom detail, termasuk kolom
      // giliran kerja dan kode inspector — pengecualian keduanya dari
      // pemeriksaan slot terisi BUKAN alasan membuangnya dari ekspor, karena
      // ekspor adalah rincian MENTAH.
      //
      // HEADER INI DISALIN VERBATIM dari ProcessWaterReportService::EXPORT_HEADER
      // — nama, EJAAN dan URUTANNYA — dan bukan dikarang di sini. Versi pertama
      // stub ini mengarang tujuh kolom konteks sendiri ('Tanggal Mulai',
      // 'Tanggal Selesai', 'Catatan Periode') dan menerjemahkan kesembilan label
      // ukur ke bahasa Indonesia, lalu meletakkan Shift dan Inspector di UJUNG
      // alih-alih tepat sesudah Slot Waktu. Hasilnya 22 kolom, dan test-nya
      // memerah dengan benar.
      //
      // Yang membuatnya layak dicatat: stub yang menyimpang TIDAK memerah dengan
      // sendirinya. Seandainya angka 21 di asersi ikut disesuaikan menjadi 22,
      // seluruh berkas ini akan hijau sambil membuktikan nol hal tentang berkas
      // yang benar-benar diunduh pengguna — layar ini mengunduh dari endpoint
      // ASLI, bukan dari stub. Sebuah stub hanya sekuat kesetiaannya pada yang
      // ia tiru, dan kesetiaan itu tidak dijaga oleh apa pun selain disalin.
      await route.fulfill({
        status: 200,
        headers: { ...CORS_HEADERS, 'content-type': 'text/csv' },
        body:
          'Periode,Mill,Production Line,Tanggal,Unit Process Water,Status,Catatan,'
          + 'Slot Waktu,Shift,Inspector ID,'
          + 'Raw Water Flow (m³/h),Clarified Water Flow (m³/h),Softener Inlet pH,'
          + 'Softener Outlet Hardness (ppm),Alum Dosing (kg/h),Polymer Dosing (g/h),'
          + 'Boiler Feed Tank Temp (°C),Boiler Feed Water pH,Boiler Feed TDS (ppm),'
          + 'Action Taken/Status,Findings\n'
          + `Periode Uji Process Water,Mill Sungai Rambutan,Line 1,${PERIOD_START},PW-1,`
          + `open,,07:00,Pagi,A1,118.4,,6.85,28.5,2.5,2,81.5,9.95,4100,Sudah ditangani,\n`
          + `Periode Uji Process Water,Mill Sungai Rambutan,Line 1,${PERIOD_START},PW-1,`
          + `open,,08:00,Siang,,,,,,,,,,,,\n`,
      })

      return
    }

    if (url.includes('/summary')) {
      state.hits.summary += 1

      if (state.summaryAbort || (state.summaryAbortOnce && state.hits.summary === 1)) {
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
      // daftarnya sendiri, jadi stub yang mengabaikan parameternya akan
      // membuat setiap asersi "line mana yang berlaku" membaca jawaban yang
      // salah — dan selalu hijau.
      const query = new URL(url).searchParams
      const requestedLineId = query.get('production_line_id')
      const requestedLine = (state.productionLines as Array<{ id: string; name: string }>).find(
        (line) => line.id === requestedLineId,
      )

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
  await expect(page.getByTestId('laporan-process-water-mobile')).toBeVisible()
}

/**
 * MEMILIH LINE ITU LANGKAH TERSENDIRI DI LAYAR INI — layar ini TIDAK
 * memilihkan line walau mill hanya punya satu.
 */
async function pickLine(page: Page, lineId = 'pl-1'): Promise<void> {
  await expect(page.getByTestId('production-line-select')).toBeVisible()
  await page.getByTestId('production-line-select').selectOption(lineId)
}

async function pickPeriod(page: Page, periodId: string): Promise<void> {
  await page.getByTestId('period-select').selectOption(periodId)
}

/** Alur baku: buka, pilih line, pilih periode, tunggu kesembilan kartu. */
async function openFullReport(page: Page, periodId = 'per-1'): Promise<void> {
  await openReport(page)
  await pickLine(page)
  await pickPeriod(page, periodId)
  await expect(page.getByTestId('period-meta')).toBeVisible()
  await expect(page.locator('[data-testid^="pw-metric-card-"]')).toHaveCount(9)
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

/** KETIGA rekap memakai CollapsibleSection — TERTUTUP secara bawaan. */
function recapToggle(page: Page, recapTestId: string) {
  return page.getByTestId(recapTestId).getByTestId('collapsible-section-toggle')
}

function recapBody(page: Page, recapTestId: string) {
  return page.getByTestId(recapTestId).getByTestId('collapsible-section-body')
}

const RECAP_TEST_IDS = ['pw-inspector-recap', 'pw-unit-recap', 'pw-daily-recap']

/** Ketiga rekap dibuka — isinya baru benar-benar TERLIHAT sesudah ini. */
async function openAllRecaps(page: Page): Promise<void> {
  // TEPAT TIGA kontrol lipat di seluruh halaman: keempat blok kartu TIDAK
  // punya satu pun.
  await expect(page.getByTestId('collapsible-section-toggle')).toHaveCount(3)

  for (const testId of RECAP_TEST_IDS) {
    await recapToggle(page, testId).click()
    await expect(recapBody(page, testId)).toBeVisible()
  }
}

/**
 * "BELUM ADA YANG DIPILIH" DIBACA DARI OPSI TERPILIH, BUKAN DARI .value.
 *
 * Vue mengikat `:value="null"` pada opsi placeholder lewat properti
 * internalnya dan TIDAK menuliskan atribut `value`, sehingga `select.value`
 * di browser jatuh ke TEKS opsi itu — `toHaveValue('')` karena itu memerah
 * pada keadaan yang justru benar.
 */
async function expectNothingSelected(
  page: Page,
  testId: string,
  placeholder: string,
): Promise<void> {
  await expect(page.getByTestId(testId).locator('option:checked')).toHaveText(placeholder)
}

/* ------------------------------------------------------------------ */
/* Bantuan asersi khas ponsel — DIUKUR, bukan disimpulkan              */
/* ------------------------------------------------------------------ */

/**
 * Halaman TIDAK PERNAH menggulir mendatar, DIUKUR pada hasil render. Yang
 * lebar adalah isi kartu — dan masing-masing menggulir atau bertumpuk di
 * dalam kartunya sendiri.
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
  const cards = page.locator('[data-testid^="pw-metric-card-"]')

  // SEMBILAN, TEPAT — bukan "setidaknya satu". toHaveCount menunggu sendiri,
  // jadi evaluateAll di bawah tidak pernah membaca halaman setengah render.
  await expect(cards).toHaveCount(9)
  await expect(cards.first()).toBeVisible()

  const boxes = await cards.evaluateAll((elements) =>
    elements.map((element) => {
      const rect = element.getBoundingClientRect()

      return { top: rect.top, bottom: rect.bottom }
    }),
  )

  for (let index = 1; index < boxes.length; index += 1) {
    expect(boxes[index].top).toBeGreaterThanOrEqual(boxes[index - 1].bottom - 1)
  }
}

/**
 * Tabel rekap BERTUMPUK di dalam kartunya, bukan menggulir mendatar.
 *
 * DIUKUR DARI BENTUK YANG TERLIHAT: tiap sel menempati barisnya sendiri
 * (kotaknya turun, bukan berderet), tiap sel membawa data-label, dan tidak
 * ada isi yang disembunyikan di balik gulir mendatar.
 */
async function expectRecapStacksIntoCards(page: Page, tableTestId: string): Promise<void> {
  await expect(page.getByTestId(tableTestId)).toBeVisible()

  const result = await page.getByTestId(tableTestId).evaluate((element) => {
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

  expect(result.cells, `${tableTestId} tidak punya satu baris isi pun untuk dinilai`).toBeGreaterThan(0)
  // Tidak ada isi yang tersembunyi di balik gulir mendatar.
  expect(result.scrollWidth).toBeLessThanOrEqual(result.clientWidth + 1)
  // Setiap sel menempati barisnya sendiri, dan membawa label kolomnya.
  expect(result.barisTegak).toBe(result.cells)
  expect(result.berlabel).toBe(result.cells)
}

/**
 * Pembungkus rekap melaporkan overflowX 'auto' pada CSS HASIL COMPILE.
 *
 * CATATAN KEKHUSUSAN SELEKTOR, karena ini mudah salah dibaca:
 * src/assets/stacked-table.css memaksa `.detail-table-wrap { overflow-x:
 * visible }` pada lebar <=640px, tetapi blok <style scoped> view
 * mengompilasi `.detail-table-wrap[data-v-hash] { overflow-x: auto }`, yang
 * kekhususannya LEBIH TINGGI dan karena itu MENANG di 390px. Nilai yang
 * dibaca di sini karena itu 'auto' — dan ia TIDAK BERBAHAYA justru karena
 * tabelnya bertumpuk, yang dibuktikan dengan mengukur scrollWidth-nya
 * sendiri. Kelas tanpa definisi akan menjawab 'visible'.
 */
async function expectRecapWrapperOverflowAuto(page: Page, tableTestId: string): Promise<void> {
  await expect(page.getByTestId(tableTestId)).toBeVisible()

  const shape = await page.getByTestId(tableTestId).evaluate((element) => {
    const wrapper = element.parentElement as HTMLElement

    return {
      className: wrapper.className,
      overflowX: getComputedStyle(wrapper).overflowX,
      scrollWidth: wrapper.scrollWidth,
      clientWidth: wrapper.clientWidth,
    }
  })

  expect(shape.className).toContain('detail-table-wrap')
  expect(shape.overflowX).toBe('auto')
  // Dan tidak ada yang benar-benar perlu digulir di dalamnya.
  expect(shape.scrollWidth).toBeLessThanOrEqual(shape.clientWidth + 1)
}

/**
 * Setiap kelas CSS yang dipakai MARKUP LAYAR INI punya definisi di CSS HASIL
 * COMPILE — dibaca dari document.styleSheets, bukan dari berkas sumber.
 *
 * DILINGKUP KE SUBPOHON akar layar ini: kerangka aplikasi (App.vue, jam
 * mengambang, bubble AI) membawa kelasnya sendiri yang bukan tanggung jawab
 * layar ini, dan memasukkannya akan membuat test ini memerah karena berkas
 * lain.
 */
async function expectEveryClassDefined(page: Page): Promise<void> {
  await expect(page.getByTestId('laporan-process-water-mobile')).toBeVisible()

  const undefinedClasses = await page.evaluate(() => {
    const defined = new Set<string>()

    const walk = (rules: CSSRuleList): void => {
      for (const rule of Array.from(rules)) {
        const styleRule = rule as CSSStyleRule & { cssRules?: CSSRuleList }

        if (typeof styleRule.selectorText === 'string') {
          for (const match of styleRule.selectorText.matchAll(
            /\.(-?[_a-zA-Z]+[_a-zA-Z0-9-]*)/g,
          )) {
            defined.add(match[1])
          }
        }

        if (styleRule.cssRules) {
          walk(styleRule.cssRules)
        }
      }
    }

    for (const sheet of Array.from(document.styleSheets)) {
      try {
        walk(sheet.cssRules)
      } catch {
        // Stylesheet lintas asal tidak dapat dibaca — tidak ada di dev server
        // ini, tetapi dilewati alih-alih melempar.
      }
    }

    const root = document.querySelector('[data-testid="laporan-process-water-mobile"]')
    const used = new Set<string>()

    if (root) {
      for (const element of [root, ...Array.from(root.querySelectorAll('[class]'))]) {
        // getAttribute, BUKAN .className: pada elemen SVG nilainya
        // SVGAnimatedString, dan .toString()-nya '[object SVGAnimatedString]'.
        for (const name of (element.getAttribute('class') ?? '').split(/\s+/)) {
          if (name !== '') {
            used.add(name)
          }
        }
      }
    }

    return { undefinedClasses: [...used].filter((name) => !defined.has(name)), usedCount: used.size }
  })

  expect(undefinedClasses.usedCount).toBeGreaterThan(10)
  expect(
    undefinedClasses.undefinedClasses,
    `kelas tanpa definisi di CSS hasil compile: ${undefinedClasses.undefinedClasses.join(', ')}`,
  ).toEqual([])
}

/** Satu kartu metrik, dicari lewat data-metric — bukan lewat posisinya. */
function metricCard(page: Page, column: string) {
  return page.locator(`[data-metric="${column}"]`)
}

/* ================================================================== */

test.describe('Laporan Process Water Mobile (screen-161)', () => {
  // Layar ponsel sungguhan — lihat catatan VIEWPORT pada docblock berkas.
  test.use({ viewport: { width: 390, height: 844 } })

  test.beforeEach(async ({ page }) => {
    await login(page, USERS.operator)
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 1 + 2 + 5 — jalur sukses, kedua aktor, tanpa pemilih mill */
  /* ---------------------------------------------------------------- */

  test('skenario 1: Operator — kelengkapan terlihat tanpa menggulir, sembilan kartu dalam empat blok, nol galat console', async ({
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

    await stubApi(page)
    await openFullReport(page)

    // BLOK KELENGKAPAN TERLIHAT DI VIEWPORT AWAL 390x844 TANPA MENGGULIR —
    // diukur dari kotaknya, bukan dari urutan sumbernya.
    const coverage = page.getByTestId('pw-coverage')

    await expect(coverage).toBeVisible()

    const coverageBox = await coverage.boundingBox()

    expect(coverageBox, 'blok kelengkapan tidak terender').not.toBeNull()
    expect(coverageBox!.y).toBeLessThan(844)
    expect(await page.evaluate(() => window.scrollY)).toBe(0)

    // KEEMPAT BLOK BERJUDUL, dalam urutan rantai pengolahan air.
    for (const block of METRIC_BLOCKS) {
      await expect(page.getByTestId(block.testId)).toBeVisible()
      await expect(
        page.getByTestId(block.testId).locator('[data-testid^="pw-metric-card-"]'),
      ).toHaveCount(block.cards)
      await expect(
        page.getByTestId(block.testId).getByRole('heading', { name: block.title, exact: true }),
      ).toBeVisible()
    }

    // SATUAN TERBACA pada ketujuh kartu ber-satuan, masing-masing DI DALAM
    // elemen kartunya sendiri.
    for (const column of METRIC_COLUMNS) {
      const expected = EXPECTED_UNITS[column]

      if (expected === null) {
        await expect(metricCard(page, column).getByTestId(`pw-metric-unit-${column}`)).toHaveCount(0)
      } else {
        await expect(metricCard(page, column).getByTestId(`pw-metric-unit-${column}`)).toHaveText(
          expected,
        )
      }
    }

    await expectSingleColumn(page)
    await expectNoHorizontalPageScroll(page)

    // NOL GALAT CONSOLE.
    expect(pageErrors, pageErrors.join(' | ')).toHaveLength(0)
    expect(consoleErrors, consoleErrors.join(' | ')).toHaveLength(0)
  })

  test('skenario 2: Supervisor melihat bagian dan angka yang sama persis dengan Operator', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    const asOperator = await page.getByTestId('laporan-process-water-mobile').innerText()

    await login(page, USERS.supervisor)
    await openFullReport(page)

    const asSupervisor = await page.getByTestId('laporan-process-water-mobile').innerText()

    // Ketiga rute data sudah menerima Operator sejak commit pertama
    // screen-160, jadi layar ini menambah PEMANGGIL — bukan varian layar.
    expect(asSupervisor).toBe(asOperator)
    await expect(page.getByTestId('pw-mill-select')).toHaveCount(0)
  })

  test('skenario 5: tanpa pemilih Mill, dan business_unit_id tidak ada pada satu pun alamat', async ({
    page,
  }) => {
    const state = await stubApi(page)
    const allRequests: string[] = []

    page.on('request', (request) => {
      if (request.url().includes('/api/')) {
        allRequests.push(request.url())
      }
    })

    await login(page, USERS.supervisor)
    await openFullReport(page)

    // Nama mill tampil sebagai KETERANGAN, bukan sebagai pilihan.
    await expect(page.getByTestId('mill-current')).toContainText('Mill Sungai Rambutan')
    await expect(page.getByTestId('pw-mill-select')).toHaveCount(0)
    await expect(page.locator('select')).toHaveCount(2)

    // TIDAK SATU PUN URL memuat parameter business_unit_id.
    for (const url of [...allRequests, ...state.urls, ...state.lineUrls]) {
      expect(new URL(url).searchParams.has('business_unit_id'), url).toBe(false)
    }

    // Dan rute pemilih mill TIDAK PERNAH disentuh.
    expect(state.hits.units).toBe(0)
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 6 + 7 + 8 — blok, satuan dosis, pH tanpa satuan          */
  /* ---------------------------------------------------------------- */

  test('skenario 6: keempat judul blok terbaca dalam urutan yang sama dengan screen-160, semuanya terbuka', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    // URUTAN DOM keempat blok, dibaca dari posisi kotaknya di halaman.
    const tops: number[] = []

    for (const block of METRIC_BLOCKS) {
      const locator = page.getByTestId(block.testId)

      await locator.scrollIntoViewIfNeeded()
      await expect(locator).toBeVisible()

      const box = await locator.boundingBox()

      expect(box, block.testId).not.toBeNull()
      tops.push(box!.y + (await page.evaluate(() => window.scrollY)))
    }

    expect(tops).toEqual([...tops].sort((left, right) => left - right))

    // KEEMPAT BLOK TERBUKA (tidak terlipat): kartunya sudah tergambar tanpa
    // satu interaksi pun, dan tidak ada kontrol lipat di dalam bloknya.
    for (const block of METRIC_BLOCKS) {
      await expect(
        page.getByTestId(block.testId).getByTestId('collapsible-section-toggle'),
      ).toHaveCount(0)
      await expect(
        page.getByTestId(block.testId).locator('[data-testid^="pw-metric-card-"]').first(),
      ).toBeVisible()
    }
  })

  test('skenario 7: kartu alum menampilkan kg/jam dan kartu polimer g/jam, dibaca SATU KARTU pada satu waktu', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    await page.getByTestId('pw-metric-block-dosis-kimia').scrollIntoViewIfNeeded()

    // ASERSI PERTAMA — KARTU ALUM, locator di-scope ke kartunya sendiri.
    const alum = metricCard(page, 'alum_dosing_kgh')

    await expect(alum).toBeVisible()
    await expect(alum.getByTestId('pw-metric-unit-alum_dosing_kgh')).toHaveText('kg/jam')

    // ASERSI KEDUA — KARTU POLIMER, TERPISAH. Skalanya berbeda SERIBU KALI
    // dan di ponsel kedua kartu tidak pernah terlihat bersamaan, jadi satu
    // asersi atas blok 'dosis' tidak memenuhi skenario ini.
    const polimer = metricCard(page, 'polymer_dosing_gh')

    await expect(polimer).toBeVisible()
    await expect(polimer.getByTestId('pw-metric-unit-polymer_dosing_gh')).toHaveText('g/jam')
    // Dilingkup KE KARTUNYA: 'kg/jam' memang ada di halaman (kartu alum dan
    // keterangan blok), jadi asersi tingkat halaman tidak bermakna di sini.
    await expect(polimer).not.toContainText('kg/jam')

    // DUA elemen satuan di dalam satu blok, bukan satu keterangan bersama.
    await expect(
      page.getByTestId('pw-metric-block-dosis-kimia').locator('[data-testid^="pw-metric-unit-"]'),
    ).toHaveCount(2)
    await expect(page.getByTestId('pw-dosis-unit-note')).toContainText('seribu kali')
  })

  test('skenario 8: kedua kartu pH — locator satuan berjumlah 0, dan tidak ada tanda hubung di posisi satuan', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    // ASERSI PERTAMA — pH MASUK SOFTENER.
    const softenerPh = metricCard(page, 'softener_inlet_ph')

    await softenerPh.scrollIntoViewIfNeeded()
    await expect(softenerPh).toBeVisible()
    await expect(softenerPh.getByTestId('pw-metric-unit-softener_inlet_ph')).toHaveCount(0)
    await expect(softenerPh.locator('[data-testid^="pw-metric-unit-"]')).toHaveCount(0)
    await expect(softenerPh.locator('.metric-unit-pill')).toHaveCount(0)

    // TRAP: label kartunya memang berbunyi 'pH Masuk Softener', jadi asersi
    // `not.toContainText('pH')` MEMERAH pada layar yang BENAR. Yang diasersi
    // adalah BARIS LABELNYA: isinya labelnya saja, tanpa '-', '—', maupun
    // satuan karangan.
    await expect(softenerPh.locator('.metric-label-row')).toHaveText('pH Masuk Softener')

    // ASERSI KEDUA — pH AIR UMPAN BOILER, kartu yang berbeda.
    const boilerPh = metricCard(page, 'boiler_feed_water_ph')

    await boilerPh.scrollIntoViewIfNeeded()
    await expect(boilerPh).toBeVisible()
    await expect(boilerPh.getByTestId('pw-metric-unit-boiler_feed_water_ph')).toHaveCount(0)
    await expect(boilerPh.locator('[data-testid^="pw-metric-unit-"]')).toHaveCount(0)
    await expect(boilerPh.locator('.metric-label-row')).toHaveText('pH Air Umpan Boiler')

    // TUJUH elemen satuan di seluruh layar: sembilan kartu, dua tanpa satuan.
    await expect(page.locator('[data-testid^="pw-metric-unit-"]')).toHaveCount(7)
    await expect(page.getByTestId('pw-softener-note')).toContainText('tidak punya pil satuan')
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 9 — NOL SEBARAN KATEGORIS                                */
  /* ---------------------------------------------------------------- */

  test('skenario 9: nol judul "Sebaran" dan nol testid sebaran — atas TESTID dan JUDUL, bukan prosa', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)
    await openAllRecaps(page)

    // JUDUL: nol heading yang dimulai 'Sebaran'.
    await expect(page.getByRole('heading', { name: /^Sebaran/ })).toHaveCount(0)
    // Termasuk judul NYATA Engine Room yang akan tersalin ke sini.
    await expect(page.getByRole('heading', { name: 'Sebaran Status Genset 1' })).toHaveCount(0)

    // TESTID: disalin dari laporan yang MEMANG punya blok itu (Engine Room
    // mobile), plus nama yang disebut tech spec layar ini. Mengasersi
    // ketiadaan atas nama yang tidak pernah dipakai siapa pun adalah asersi
    // yang tidak dapat gagal.
    for (const testId of [
      'status-distribution-diesel-gen-1',
      'status-distribution-diesel-gen-2',
      'status-distribution-diesel-gen-1-table',
      'status-row',
      'status-value-label',
      'status-not-recorded',
      'status-recorded-total',
      'status-distribution-note',
      'pw-sebaran-action-taken-status',
      'pw-sebaran-table',
      'sebaran-table',
      'sebaran-row',
    ]) {
      await expect(page.getByTestId(testId), testId).toHaveCount(0)
    }

    // TIGA entri BERTEKS IDENTIK tetap TIGA baris, masing-masing dengan
    // tanggal dan slot waktunya sendiri — bukan satu baris berjumlah 3.
    await page.getByTestId('pw-recap-action-taken').scrollIntoViewIfNeeded()
    await expect(page.locator('[data-testid^="pw-action-taken-row-"]')).toHaveCount(3)
    await expect(
      page.getByTestId('pw-recap-action-taken').locator('table'),
    ).toHaveCount(0)

    // PROSA yang menerangkan ketiadaannya memang ADA — inilah sebabnya
    // pemindaian prosa dilarang di atas.
    await expect(page.getByTestId('pw-no-distribution-note')).toContainText('sebaran kategoris')
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 10 — DUA REKAP TEKS, DUA BAGIAN TERPISAH                 */
  /* ---------------------------------------------------------------- */

  test('skenario 10: dua bagian berjudul terpisah, dan slot yang sama tampil pada keduanya', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    const actionSection = page.getByTestId('pw-recap-action-taken')
    const findingSection = page.getByTestId('pw-recap-findings')

    await actionSection.scrollIntoViewIfNeeded()
    await expect(actionSection).toBeVisible()
    await expect(
      actionSection.getByRole('heading', { name: /^Rekap Catatan Tindakan \(3 slot\)$/ }),
    ).toBeVisible()

    await findingSection.scrollIntoViewIfNeeded()
    await expect(findingSection).toBeVisible()
    await expect(
      findingSection.getByRole('heading', { name: /^Rekap Temuan \(2 slot\)$/ }),
    ).toBeVisible()

    // PENCARIAN DI-SCOPE KE MASING-MASING SECTION, bukan ke halaman: slot
    // yang sama hadir di keduanya, dan itulah yang diuji.
    const slotSelector = `[data-date="${DAY_A}"][data-time-slot="08:00"]`

    await expect(actionSection.locator(slotSelector)).toHaveCount(1)
    await expect(findingSection.locator(slotSelector)).toHaveCount(1)
    await expect(actionSection.locator(slotSelector)).toContainText('Sudah ditangani')
    await expect(findingSection.locator(slotSelector)).toContainText('Kesadahan keluaran softener')

    // Dan jumlah pada kedua judul TIDAK BOLEH dijumlahkan.
    await expect(page.getByTestId('pw-findings-note')).toContainText('tidak boleh dijumlahkan')
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 11 + 12 — KELENGKAPAN dan ATURAN SEBELAS KOLOM           */
  /* ---------------------------------------------------------------- */

  test('skenario 11: slot terisi MELEBIHI setiap penyebut, beserta kalimat aturan sebelas kolom UTUH', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    // 96 dari 480, dan penyebut kartu tertinggi 24 — dirender APA ADANYA.
    await expect(page.getByTestId('pw-coverage-slots')).toContainText('96 dari 480 slot')

    const counts = await page
      .locator('[data-testid^="pw-metric-count-"]')
      .evaluateAll((elements) => elements.map((element) => (element.textContent ?? '').trim()))

    expect(counts).toHaveLength(9)
    // KESEMBILAN penyebut BERBEDA — penyebut bersama akan lolos tanpa
    // terlihat kalau fixture-nya seragam.
    expect(new Set(counts).size).toBe(9)
    expect(counts).not.toContain('96')

    // KALIMAT SERVER DIRENDER VERBATIM, diukur pada hasil render.
    const note = page.getByTestId('pw-note-filled-rule')

    await note.scrollIntoViewIfNeeded()
    await expect(note).toBeVisible()
    expect((await note.innerText()).replace(/\s+/g, ' ').trim()).toBe(
      NOTE_FILLED_RULE.replace(/\s+/g, ' ').trim(),
    )

    // Dan TANPA peringatan 'data tidak konsisten' dalam bentuk apa pun.
    await expect(page.locator('[data-testid^="pw-inconsistent"]')).toHaveCount(0)
  })

  test('skenario 12: keterangan kolom identitas tidak membuat slot terhitung terisi, terbaca UTUH', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    const consequence = page.getByTestId('pw-filled-rule-consequence')

    await consequence.scrollIntoViewIfNeeded()
    await expect(consequence).toBeVisible()
    await expect(consequence).toContainText('TETAP terhitung terisi')
    await expect(consequence).toContainText('TIDAK terhitung terisi')
    await expect(consequence).toContainText('giliran kerja')
    await expect(consequence).toContainText('kode inspector')

    // TERBACA UTUH pada 390px — diukur, bukan disimpulkan.
    const shape = await consequence.evaluate((element) => ({
      scrollHeight: element.scrollHeight,
      clientHeight: element.clientHeight,
      textOverflow: getComputedStyle(element).textOverflow,
      lineClamp: getComputedStyle(element).webkitLineClamp,
    }))

    expect(shape.scrollHeight).toBeLessThanOrEqual(shape.clientHeight + 1)
    expect(shape.textOverflow).toBe('clip')
    expect(['none', '', 'auto']).toContain(shape.lineClamp)
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 13 — REKAP PER INSPECTOR                                 */
  /* ---------------------------------------------------------------- */

  test('skenario 13: setiap baris menampilkan kodenya apa adanya, termasuk kode berspasi; baris "tidak tercatat" berdiri sendiri', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    // CollapsibleSection memakai v-show: barisnya ada di DOM saat tertutup,
    // jadi keadaan tertutup diasersi dengan toBeHidden(), tidak pernah
    // dengan toHaveCount(0).
    await page.getByTestId('pw-inspector-recap').scrollIntoViewIfNeeded()
    await expect(recapBody(page, 'pw-inspector-recap')).toBeHidden()

    await recapToggle(page, 'pw-inspector-recap').click()
    await expect(recapBody(page, 'pw-inspector-recap')).toBeVisible()

    // TIGA baris: dua berkode + satu 'tidak tercatat' dari kunci
    // not_recorded yang TERPISAH.
    await expect(page.locator('[data-testid^="pw-inspector-row-"]')).toHaveCount(3)

    // DIBACA DARI SEL KODENYA, bukan dari teks kontainer: kode pendek seperti
    // 'A1' atau '7' cocok di dalam angka lain pada halaman yang sama.
    await expect(page.getByTestId('pw-inspector-code-0')).toHaveText('A1')
    await expect(page.getByTestId('pw-inspector-code-1')).toHaveText('INSP 07 PAGI')
    await expect(page.getByTestId('pw-inspector-code-2')).toHaveText('tidak tercatat')
    await expect(page.getByTestId('pw-inspector-slots-0')).toHaveText('9')
    await expect(page.getByTestId('pw-inspector-slots-1')).toHaveText('8')
    await expect(page.getByTestId('pw-inspector-slots-2')).toHaveText('7')

    // Kode BERSPASI tampil UTUH — tidak dipotong oleh lebar 390px.
    const codeShape = await page.getByTestId('pw-inspector-code-1').evaluate((element) => ({
      text: (element.textContent ?? '').trim(),
      scrollWidth: element.scrollWidth,
      clientWidth: element.clientWidth,
      textOverflow: getComputedStyle(element).textOverflow,
    }))

    expect(codeShape.text).toBe('INSP 07 PAGI')
    expect(codeShape.scrollWidth).toBeLessThanOrEqual(codeShape.clientWidth + 1)
    expect(codeShape.textOverflow).toBe('clip')

    // Porsinya memakai recorded_slots DARI RESPONS (24), bukan expected_slots.
    await expect(page.getByTestId('pw-inspector-share-0')).toHaveText('37,5%')
    await expect(page.getByTestId('pw-inspector-total')).toContainText('100,0%')

    // Keterangan bahwa rekap ini untuk menelusuri slot belum terisi — bukan
    // menilai orang — TERLIHAT.
    await page.getByTestId('pw-inspector-purpose-note').scrollIntoViewIfNeeded()
    await expect(page.getByTestId('pw-inspector-purpose-note')).toBeVisible()
    await expect(page.getByTestId('pw-inspector-purpose-note')).toContainText(
      'bukan untuk menilai orang',
    )
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 14 + 15 — KETIADAAN shift, downtime, penandaan            */
  /* ---------------------------------------------------------------- */

  test('skenario 14: nol locator header/kolom/label shift di SELURUH halaman, termasuk rekap terbuka', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)
    await openAllRecaps(page)

    // DIASERSI ATAS TESTID dan ATAS PERAN KOLOM, bukan atas teks halaman:
    // layar ini WAJIB merender frasa 'giliran kerja', dan testid
    // `pw-no-shift-note` sendiri memuat rangkaian huruf 'shift'.
    for (const testId of ['pw-shift-column', 'pw-shift', 'pw-shift-recap', 'shift-column']) {
      await expect(page.getByTestId(testId), testId).toHaveCount(0)
    }

    await expect(page.getByRole('columnheader', { name: /shift/i })).toHaveCount(0)
    await expect(page.getByRole('columnheader', { name: /giliran/i })).toHaveCount(0)

    // Ketiga tabel rekap memang TERBUKA saat diperiksa — tanpa ini asersi
    // "nol kolom shift" lolos karena tabelnya belum tergambar.
    for (const tableTestId of ['pw-inspector-table', 'pw-unit-table', 'pw-daily-table']) {
      await expect(page.getByTestId(tableTestId)).toBeVisible()
    }
    await expect(page.getByRole('columnheader')).not.toHaveCount(0)

    // KETERANGAN ALASANNYA TERBACA.
    await page.getByTestId('pw-no-shift-note').scrollIntoViewIfNeeded()
    await expect(page.getByTestId('pw-no-shift-note')).toBeVisible()
    await expect(page.getByTestId('pw-no-shift-note')).toContainText(
      'konsekuensi, bukan penyaringan di klien',
    )
  })

  test('skenario 15: pernyataan ketiadaan penandaan di luar batas terbaca UTUH pada 390px, DIUKUR', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    // Nilai jauh di luar batas yang dikenal umum tergambar APA ADANYA.
    await metricCard(page, 'softener_inlet_ph').scrollIntoViewIfNeeded()
    await expect(page.getByTestId('pw-metric-min-softener_inlet_ph')).toHaveText('3,20')
    await expect(page.getByTestId('pw-metric-max-boiler_feed_tds_ppm')).toHaveText('9.800')

    // DISISIR ATAS NAMA KELAS pada kartu, bukan atas frasa: kalimat yang
    // menyatakan ketiadaan penandaannya sendiri memuat frasa 'di luar batas'.
    const flagged = await page.evaluate(() =>
      Array.from(document.querySelectorAll('[data-testid^="pw-metric-card-"]'))
        .flatMap((card) => [card, ...Array.from(card.querySelectorAll('[class]'))])
        .flatMap((element) => (element.getAttribute('class') ?? '').split(/\s+/))
        .filter((name) =>
          /warning|danger|error|alert|violation|out-of-range|breach|critical|over-limit/.test(name),
        ),
    )

    expect(flagged).toEqual([])

    // DAN PERNYATAANNYA TERBACA UTUH — DIUKUR pada hasil render, dengan CSS
    // HASIL COMPILE.
    const note = page.getByTestId('pw-no-threshold-note')

    await note.scrollIntoViewIfNeeded()
    await expect(note).toBeVisible()

    const shape = await note.evaluate((element) => ({
      scrollHeight: element.scrollHeight,
      clientHeight: element.clientHeight,
      scrollWidth: element.scrollWidth,
      clientWidth: element.clientWidth,
      textOverflow: getComputedStyle(element).textOverflow,
      lineClamp: getComputedStyle(element).webkitLineClamp,
      whiteSpace: getComputedStyle(element).whiteSpace,
      length: (element.textContent ?? '').length,
    }))

    expect(shape.length).toBeGreaterThan(600)
    // TANPA PEMOTONGAN: seluruh isinya benar-benar terender.
    expect(shape.scrollHeight).toBeLessThanOrEqual(shape.clientHeight + 1)
    expect(shape.scrollWidth).toBeLessThanOrEqual(shape.clientWidth + 1)
    expect(shape.textOverflow).toBe('clip')
    expect(['none', '', 'auto']).toContain(shape.lineClamp)
    expect(['normal', 'pre-line', 'pre-wrap']).toContain(shape.whiteSpace)
    // Dan tidak ada kontrol 'baca selengkapnya'.
    await expect(page.getByRole('button', { name: /selengkapnya|lihat semua/i })).toHaveCount(0)

    // NOL BLOK DOWNTIME — atas TESTID dan JUDUL.
    for (const testId of ['pw-downtime', 'pw-downtime-summary', 'downtime']) {
      await expect(page.getByTestId(testId), testId).toHaveCount(0)
    }
    await expect(page.getByRole('heading', { name: /downtime|lama berhenti/i })).toHaveCount(0)

    // Dan prosa SERVER dirender UTUH, kesamaan penuh.
    const absences = page.getByTestId('pw-note-absences')

    await absences.scrollIntoViewIfNeeded()
    expect((await absences.innerText()).replace(/\s+/g, ' ').trim()).toBe(
      NOTE_ABSENCES.replace(/\s+/g, ' ').trim(),
    )
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 16 + 17 + 18 + 19 — KELENGKAPAN pada berbagai keadaan     */
  /* ---------------------------------------------------------------- */

  test('skenario 16: kelengkapan berada DI ATAS kartu pertama, diukur dari posisi kotaknya', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    const coverageBox = await page.getByTestId('pw-coverage').boundingBox()
    const firstCardBox = await page
      .locator('[data-testid^="pw-metric-card-"]')
      .first()
      .boundingBox()

    expect(coverageBox, 'blok kelengkapan tidak terender').not.toBeNull()
    expect(firstCardBox, 'kartu pertama tidak terender').not.toBeNull()

    // ASERSI POSISI, bukan sekadar keberadaan.
    expect(coverageBox!.y).toBeLessThan(firstCardBox!.y)
    // Dan ia sepenuhnya berada di dalam viewport awal 390x844.
    expect(coverageBox!.y).toBeGreaterThanOrEqual(0)
    expect(coverageBox!.y).toBeLessThan(844)
  })

  test('skenario 17: periode berjalan — hari terhitung dan keterangan "berhenti di hari ini" terbaca utuh', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    await expect(page.getByTestId('pw-coverage-days')).toContainText('10 hari')
    await expect(page.getByTestId('pw-coverage-days')).toContainText('dari 14 hari periode')

    const note = page.getByTestId('pw-coverage-running-note')

    await note.scrollIntoViewIfNeeded()
    await expect(note).toBeVisible()
    await expect(note).toContainText('Dihitung sampai HARI INI, karena periode masih berjalan')
    await expect(page.getByTestId('pw-coverage-not-started-note')).toHaveCount(0)

    const shape = await note.evaluate((element) => ({
      scrollHeight: element.scrollHeight,
      clientHeight: element.clientHeight,
    }))

    expect(shape.scrollHeight).toBeLessThanOrEqual(shape.clientHeight + 1)
  })

  test('skenario 18: percent null — "—" beserta "belum ada slot yang diharapkan", dan nol "0,0%" di blok kelengkapan', async ({
    page,
  }) => {
    await stubApi(page, { summary: notStartedSummary() })
    await openReport(page)
    await pickLine(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('pw-coverage')).toBeVisible()
    await expect(page.getByTestId('pw-coverage-percent')).toHaveText('—')
    await expect(page.getByTestId('pw-coverage-no-expected-note')).toContainText(
      'Belum ada slot yang diharapkan',
    )

    // ELEMEN KELENGKAPAN SELURUHNYA tidak memuat '0,0%' maupun '0%'.
    const coverageText = await page.getByTestId('pw-coverage').innerText()

    expect(coverageText).not.toContain('0,0%')
    expect(coverageText).not.toContain('0%')
    // Bilah kemajuan pun tidak digambar tanpa penyebut.
    await expect(page.getByTestId('pw-coverage').locator('.completeness-track')).toHaveCount(0)
    // Dan keterangan "belum mulai" yang DIPERIKSA LEBIH DULU memang muncul.
    await expect(page.getByTestId('pw-coverage-not-started-note')).toBeVisible()
    await expect(page.getByTestId('pw-coverage-running-note')).toHaveCount(0)
  })

  test('skenario 19: periode tanpa data — kelengkapan terlihat, sembilan kartu "tidak tersedia", kedua rekap berketerangan', async ({
    page,
  }) => {
    await stubApi(page, { summary: emptySummary() })
    await openReport(page)
    await pickLine(page)
    await pickPeriod(page, 'per-1')

    await expect(page.locator('[data-testid^="pw-metric-card-"]')).toHaveCount(9)
    await expect(page.getByTestId('pw-coverage')).toBeVisible()
    // Penyebutnya TERBENTUK, dan yang terukur memang nol slot — 0,0%, bukan '—'.
    await expect(page.getByTestId('pw-coverage-percent')).toHaveText('0,0%')

    await expect(page.locator('[data-testid^="pw-metric-unavailable-"]')).toHaveCount(9)

    for (const column of METRIC_COLUMNS) {
      await expect(page.getByTestId(`pw-metric-avg-${column}`), column).toHaveText('tidak tersedia')
      await expect(page.getByTestId(`pw-metric-count-${column}`), column).toHaveText('0')
    }

    // KEDUA REKAP TEKS BERKETERANGAN, bukan daftar kosong.
    await page.getByTestId('pw-recap-action-taken').scrollIntoViewIfNeeded()
    await expect(page.getByTestId('pw-recap-action-taken-empty')).toBeVisible()
    await page.getByTestId('pw-recap-findings').scrollIntoViewIfNeeded()
    await expect(page.getByTestId('pw-recap-findings-empty')).toBeVisible()

    await expectNoHorizontalPageScroll(page)
  })
})

/* ================================================================== */

test.describe('Laporan Process Water Mobile — Production Line, ekspor, dan keadaan', () => {
  test.use({ viewport: { width: 390, height: 844 } })

  test.beforeEach(async ({ page }) => {
    await login(page, USERS.operator)
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 3 — PENJAGAAN DI FUNGSI PEMUATAN, DIHITUNG               */
  /* ---------------------------------------------------------------- */

  test('skenario 3: tanpa line — jumlah request /summary TEPAT 0, juga sesudah ganti periode', async ({
    page,
  }) => {
    const state = await stubApi(page, { productionLines: [LINE_1, LINE_2] })
    const summaryRequests: string[] = []

    page.on('request', (request) => {
      if (request.url().includes('/api/process-water-reports/summary')) {
        summaryRequests.push(request.url())
      }
    })

    await openReport(page)

    // Daftar line DAN daftar periode tetap diminta — keduanya tidak
    // bergantung pada line. Yang DITAHAN adalah /summary.
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    await expectNothingSelected(page, 'production-line-select', 'Pilih Production Line')

    // GANTI PERIODE tanpa line terpilih.
    await pickPeriod(page, 'per-1')
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    await pickPeriod(page, 'per-2')
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()

    // DIUKUR PADA PENGHITUNG, bukan disimpulkan dari angka yang tidak tampil.
    await expect.poll(() => state.hits.summary).toBe(0)
    expect(summaryRequests).toHaveLength(0)

    // Permintaan memilih line TERLIHAT, dan tombol Ekspor CSV TIDAK ADA di DOM.
    await expect(page.getByTestId('production-line-required-hint')).toContainText(
      'tidak satu permintaan pun dikirim ke server',
    )
    await expect(page.getByTestId('pw-export-csv')).toHaveCount(0)
    await expect(page.locator('[data-testid^="pw-metric-card-"]')).toHaveCount(0)
  })

  test('skenario 3: jalur COBA LAGI pun tidak meminta ringkasan selama line kosong', async ({
    page,
  }) => {
    // /periods gagal di tingkat jaringan: satu-satunya keadaan yang
    // menampilkan tombol Coba Lagi dengan line masih kosong.
    const state = await stubApi(page, { productionLines: [LINE_1, LINE_2] })

    await page.route('**/api/process-water-reports/periods*', (route) => route.abort('failed'))
    await openReport(page)

    await expect(page.getByTestId('network-error')).toBeVisible()
    await expect(page.getByTestId('retry-button')).toBeVisible()

    await page.unroute('**/api/process-water-reports/periods*')
    await page.getByTestId('retry-button').click()

    await expect(page.getByTestId('period-select')).toBeVisible()
    await pickPeriod(page, 'per-1')
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()

    await expect.poll(() => state.hits.summary).toBe(0)
    await expect(page.getByTestId('pw-export-csv')).toHaveCount(0)
  })

  test('line yang diingat perangkat dipakai tanpa diminta memilih lagi, dan terbawa ke query /summary', async ({
    page,
  }) => {
    const state = await stubApi(page)
    const userId = await getAuthUserId(page)

    expect(userId, 'userId tidak terbaca dari penyimpanan sesi').not.toBe('')

    const summaryRequests: string[] = []

    page.on('request', (request) => {
      if (request.url().includes('/api/process-water-reports/summary')) {
        summaryRequests.push(request.url())
      }
    })

    // KUNCINYA SAMA PERSIS dengan yang ditulis StationListView.vue.
    await page.addInitScript(
      ([key, lineId]) => window.localStorage.setItem(key, lineId),
      [REMEMBERED_LINE_KEY(userId), 'pl-1'] as const,
    )

    await openReport(page)
    await expect(page.getByTestId('production-line-select').locator('option:checked')).toHaveText(
      'Line 1',
    )
    // Tidak ada langkah memilih line yang diminta ke pengguna.
    await expect(page.getByTestId('production-line-required-hint')).toHaveCount(0)

    await pickPeriod(page, 'per-1')
    await expect(page.locator('[data-testid^="pw-metric-card-"]')).toHaveCount(9)

    await expect.poll(() => state.hits.summary).toBe(1)
    expect(summaryRequests).toHaveLength(1)
    expect(new URL(summaryRequests[0]).searchParams.get('production_line_id')).toBe('pl-1')
  })

  test('TIDAK memilihkan line, bahkan ketika mill hanya punya SATU', async ({ page }) => {
    const state = await stubApi(page, { productionLines: [LINE_1] })

    await openReport(page)

    // Pemilihnya DIRENDER walau daftarnya hanya berisi satu entri.
    await expect(page.getByTestId('production-line-select')).toBeVisible()
    await expectNothingSelected(page, 'production-line-select', 'Pilih Production Line')
    await pickPeriod(page, 'per-1')

    await expect.poll(() => state.hits.summary).toBe(0)
    await expect(page.getByTestId('production-line-required-hint')).toContainText(
      'hanya punya satu line',
    )
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 4 — PENYIMPANAN PERANGKAT MELEMPAR                       */
  /* ---------------------------------------------------------------- */

  test('skenario 4: Storage.prototype.getItem melempar — layar tetap merender, minta pilih line, nol request /summary', async ({
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

    const state = await stubApi(page, { productionLines: [LINE_1, LINE_2] })

    // DIPASANG SETELAH LOGIN, dan hanya untuk kunci ingatan line: yang dibuat
    // gagal adalah PEMBACAAN ingatan, bukan sesi itu sendiri — sesi yang ikut
    // gagal akan membuang test ini ke halaman Login dan menguji hal lain.
    await page.addInitScript(() => {
      const original = Storage.prototype.getItem

      Storage.prototype.getItem = function patched(key: string) {
        if (key.startsWith('msl_production_line_')) {
          throw new DOMException('akses penyimpanan ditolak', 'SecurityError')
        }

        return original.call(this, key)
      }
    })

    await openReport(page)

    // LAYAR TETAP MERENDER — bukan halaman rusak / blank.
    await expect(page.getByTestId('laporan-process-water-mobile')).toBeVisible()
    await expect(page.getByTestId('production-line-select')).toBeVisible()
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()

    // Keadaannya diperlakukan sebagai "tidak ada ingatan", bukan sebagai
    // galat — tidak ada satu pesan galat pun di halaman.
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('error-message')).toHaveCount(0)
    await expect(page.getByTestId('mill-not-linked')).toHaveCount(0)

    // NOL request /summary.
    await expect.poll(() => state.hits.summary).toBe(0)

    // NOL galat yang tidak tertangani.
    expect(pageErrors, pageErrors.join(' | ')).toHaveLength(0)
    expect(
      consoleErrors.filter((message) => message.includes('SecurityError')),
      consoleErrors.join(' | '),
    ).toHaveLength(0)

    // Dan pemilihnya tetap operasional.
    await pickLine(page, 'pl-2')
    await pickPeriod(page, 'per-1')
    await expect(page.locator('[data-testid^="pw-metric-card-"]')).toHaveCount(9)
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 20 + 21 + 25 — EKSPOR CSV SAJA                           */
  /* ---------------------------------------------------------------- */

  test('skenario 20: TEPAT satu tombol ekspor (CSV); locator testid xlsx/Excel berjumlah 0', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    await expect(page.getByTestId('pw-export-csv')).toBeVisible()
    await expect(page.getByTestId('pw-export-csv')).toContainText('Ekspor CSV')
    await expect(page.locator('[data-testid^="pw-export-"]')).toHaveCount(1)

    // KETIADAAN DIASERSI ATAS TESTID, BUKAN ATAS TEKS HALAMAN: layar ini
    // WAJIB merender kalimat 'tidak ada tombol Ekspor Excel' dan menyebut
    // '.xlsx' pada pw-no-mill-picker-note, jadi penyisiran body.innerText
    // untuk 'excel' MEMERAH pada layar yang BENAR — penyimpangan sadar dari
    // laporan-engine-room.spec.ts, yang layarnya tidak menyebutnya.
    await expect(page.getByTestId('pw-export-xlsx')).toHaveCount(0)
    await expect(page.getByTestId('pw-export-excel')).toHaveCount(0)
    await expect(page.getByRole('button', { name: /excel|xlsx/i })).toHaveCount(0)

    // Dan kalimat yang menerangkan ketiadaannya memang ADA.
    await page.getByTestId('pw-no-mill-picker-note').scrollIntoViewIfNeeded()
    await expect(page.getByTestId('pw-no-mill-picker-note')).toContainText(
      'tidak ada tombol Ekspor Excel',
    )
  })

  test('skenario 21: unduhan CSV terpicu, header memuat kolom giliran kerja dan inspector, dan klik ganda mengirim SATU permintaan', async ({
    page,
  }) => {
    // TUNDAAN WAJIB — lihat catatan pada ApiState.exportDelayMs.
    const state = await stubApi(page, { exportDelayMs: 1500 })

    await openFullReport(page)
    await expect(page.getByTestId('pw-export-csv')).toBeVisible()

    const download = page.waitForEvent('download')

    await page.getByTestId('pw-export-csv').click()
    await expect(page.getByTestId('pw-export-csv')).toBeDisabled()
    // force:true justru supaya Playwright tidak menolak ketukan itu lebih
    // dulu — yang diuji adalah penjagaan APLIKASI, bukan penjagaan alat uji.
    await page.getByTestId('pw-export-csv').click({ force: true })

    const file = await download

    expect(file.suggestedFilename()).toMatch(/\.csv$/)
    expect(file.suggestedFilename()).not.toMatch(/xlsx/i)
    expect(file.suggestedFilename()).toContain('laporan-process-water')

    // ISI BERKASNYA DIPERIKSA: ekspor adalah rincian MENTAH, jadi kedua kolom
    // identitas IKUT — pengecualian keduanya dari pemeriksaan slot terisi
    // bukan alasan membuangnya dari ekspor.
    const stream = await file.createReadStream()
    const chunks: Buffer[] = []

    for await (const chunk of stream) {
      chunks.push(Buffer.from(chunk))
    }

    const header = Buffer.concat(chunks).toString('utf-8').split('\n')[0]

    // assertStringContainsString-equivalent: pesannya ditaruh di DESKRIPSI
    // expect(), bukan sebagai argumen kedua toContain — pada Vitest argumen
    // itu diperlakukan sebagai NEEDLE KEDUA, bukan sebagai pesan.
    expect(header, 'baris header CSV wajib memuat kolom Shift').toContain('Shift')
    expect(header, 'baris header CSV wajib memuat kolom Inspector').toContain('Inspector')
    expect(header.split(',')).toHaveLength(21)
    await file.delete()

    // SATU permintaan, bukan dua.
    await expect.poll(() => state.hits.export).toBe(1)

    const exportUrl = state.urls.find((url) => url.includes('/export')) ?? ''

    expect(exportUrl).toContain('period_id=per-1')
    expect(exportUrl).toContain('production_line_id=pl-1')
    expect(exportUrl).toContain('format=csv')
    expect(exportUrl).not.toContain('xlsx')
    expect(exportUrl).not.toContain('excel')
  })

  test('skenario 25: periode TERTUTUP — laporan penuh, badge Tertutup, unduhan CSV tetap berjalan', async ({
    page,
  }) => {
    const state = await stubApi(page)

    await openFullReport(page, 'per-2')

    await expect(page.getByTestId('period-status-badge')).toHaveText('Tertutup')
    await expect(page.locator('[data-testid^="pw-metric-card-"]')).toHaveCount(9)
    await expect(page.getByTestId('pw-export-csv')).toBeEnabled()

    const download = page.waitForEvent('download')

    await page.getByTestId('pw-export-csv').click()

    const file = await download

    expect(file.suggestedFilename()).toMatch(/\.csv$/)
    await file.delete()

    await expect.poll(() => state.hits.export).toBe(1)
    expect(state.urls.find((url) => url.includes('/export'))).toContain('period_id=per-2')
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 22 — REKAP BERTUMPUK, GULIR DIUKUR, CSS HASIL COMPILE    */
  /* ---------------------------------------------------------------- */

  test('skenario 22: ketiga rekap BERTUMPUK jadi kartu, halaman tidak menggulir mendatar (DIUKUR), setiap kelas terdefinisi', async ({
    page,
  }) => {
    const state = await stubApi(page)

    await openFullReport(page)
    await openAllRecaps(page)

    // (a) HALAMAN TIDAK MENGGULIR MENDATAR — DIUKUR.
    await expectNoHorizontalPageScroll(page)

    // (b) SEMENTARA pembungkus rekap melaporkan overflowX 'auto' dari CSS
    // HASIL COMPILE — kelas tanpa definisi akan menjawab 'visible'.
    for (const tableTestId of ['pw-inspector-table', 'pw-unit-table', 'pw-daily-table']) {
      await expectRecapWrapperOverflowAuto(page, tableTestId)
    }

    // (c) Dan ketiganya BERTUMPUK: satu kartu per baris, tiap sel BERLABEL.
    for (const tableTestId of ['pw-inspector-table', 'pw-unit-table', 'pw-daily-table']) {
      await expectRecapStacksIntoCards(page, tableTestId)
    }

    // (d) DUA BELAS baris harian benar-benar tergambar.
    await expect(page.locator('[data-testid^="pw-daily-row-"]')).toHaveCount(12)

    // (e) SETIAP KELAS CSS yang dipakai markup punya definisi di HASIL
    // COMPILE.
    await expectEveryClassDefined(page)

    // (f) Menutupnya kembali tidak memicu satu permintaan pun.
    const summaryHits = state.hits.summary

    for (const testId of RECAP_TEST_IDS) {
      await recapToggle(page, testId).click()
      await expect(recapBody(page, testId)).toBeHidden()
    }

    await expect.poll(() => state.hits.summary).toBe(summaryHits)
  })

  test('grafik tren menggulir mendatar DI DALAM kartunya, dan halaman tetap tidak bergeser', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    const chart = page.locator('.chart-scroll').first()

    await chart.scrollIntoViewIfNeeded()
    await expect(chart).toBeVisible()

    const shape = await chart.evaluate((element) => ({
      scrollWidth: element.scrollWidth,
      clientWidth: element.clientWidth,
      overflowX: getComputedStyle(element).overflowX,
    }))

    // SATU-SATUNYA elemen yang menggulir mendatar di layar ini — dan DUA
    // BELAS tanggal pada fixture memang membuatnya melampaui 390px.
    expect(['auto', 'scroll']).toContain(shape.overflowX)
    expect(shape.scrollWidth).toBeGreaterThan(shape.clientWidth)

    await expect(page.locator('[data-testid^="pw-trend-col-"]')).toHaveCount(12)
    await expectNoHorizontalPageScroll(page)
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 23 + 24 — GALAT DAN LURING                               */
  /* ---------------------------------------------------------------- */

  test('skenario 23: /summary gagal sekali — state galat + Coba Lagi, lalu angka termuat; jumlah request terhitung', async ({
    page,
  }) => {
    const state = await stubApi(page, { summaryAbortOnce: true })

    await openReport(page)
    await pickLine(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('network-error')).toBeVisible()
    await expect(page.getByTestId('retry-button')).toBeVisible()
    await expect(page.locator('[data-testid^="pw-metric-card-"]')).toHaveCount(0)
    // Pilihan periode dan line SENGAJA tidak hilang.
    await expect(page.getByTestId('period-select').locator('option:checked')).toContainText(
      'Periode Uji Process Water',
    )
    await expect.poll(() => state.hits.summary).toBe(1)

    await page.getByTestId('retry-button').click()

    await expect(page.locator('[data-testid^="pw-metric-card-"]')).toHaveCount(9)
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect.poll(() => state.hits.summary).toBe(2)
  })

  test('403 — pilih line lagi, BUKAN galat jaringan, dan nol angka mill lain', async ({ page }) => {
    const state = await stubApi(page, {
      productionLines: [LINE_1, LINE_2],
      summaryStatus: 403,
      summaryMessage: 'Anda tidak memiliki akses untuk aksi ini.',
    })

    await openReport(page)
    await pickLine(page, 'pl-2')
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('production-line-rejected')).toBeVisible()
    await expect(page.getByTestId('production-line-rejected')).toContainText(
      'pilih Production Line kembali',
    )
    await expectNothingSelected(page, 'production-line-select', 'Pilih Production Line')
    await expect(page.locator('[data-testid^="pw-metric-card-"]')).toHaveCount(0)
    await expect(page.getByTestId('pw-coverage')).toHaveCount(0)
    // BUKAN galat jaringan.
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('error-message')).toHaveCount(0)
    await expect.poll(() => state.hits.summary).toBe(1)
  })

  test('422 akun tanpa mill — arahan menghubungi Admin, bukan galat jaringan', async ({ page }) => {
    await stubApi(page)
    await page.route('**/api/process-water-reports/periods*', async (route) => {
      if (route.request().method() === 'OPTIONS') {
        await route.fulfill({ status: 204, headers: CORS_HEADERS, body: '' })

        return
      }

      await route.fulfill({
        status: 422,
        headers: CORS_HEADERS,
        json: { message: 'Akun Anda belum terhubung ke mill. Hubungi Admin.' },
      })
    })

    await openReport(page)

    await expect(page.getByTestId('mill-not-linked')).toBeVisible()
    await expect(page.getByTestId('mill-not-linked')).toContainText('hubungi Admin')
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('retry-button')).toHaveCount(0)
    await expect(page.getByTestId('pw-mill-select')).toHaveCount(0)
  })

  test('sesi berakhir (401) — diarahkan ke Login tanpa pesan jaringan', async ({ page }) => {
    await stubApi(page, { summaryStatus: 401, summaryMessage: 'Unauthenticated.' })

    await openReport(page)
    await pickLine(page)
    await pickPeriod(page, 'per-1')

    // PATHNAME, bukan toHaveURL('**/login') — dengan baseURL terpasang pola
    // glob semacam itu tidak pernah cocok.
    await expectPathname(page, '/login')
  })

  test('skenario 24: luring DINYATAKAN — di-set SESUDAH bootstrap sesi, bukan sebelumnya', async ({
    page,
  }) => {
    const state = await stubApi(page)

    // LAYARNYA DIBUKA LEBIH DULU: rutenya lazy-import, jadi menyetel luring
    // sebelum chunk-nya terunduh membuat navigasi MENGGANTUNG alih-alih
    // gagal.
    await openFullReport(page)

    const summaryHits = state.hits.summary

    // LURINGNYA DIPALSUKAN DI `navigator.onLine`, BUKAN LEWAT
    // context.setOffline() — dan perbedaannya menentukan, bukan selera.
    //
    // `context.setOffline(true)` memutus jaringan pada tingkat BROWSER, jadi
    // `page.reload()` sesudahnya gagal mengambil dokumen dan bundle-nya
    // sendiri dari Vite dev server: navigasinya mati dengan
    // ERR_INTERNET_DISCONNECTED, dan aplikasinya TIDAK PERNAH DIJALANKAN.
    // Yang teruji dengan cara itu bukan penjagaan layar ini melainkan
    // kemampuan Chromium memuat halaman tanpa jaringan — dan jawabannya
    // memang tidak.
    //
    // Sinyal yang DIBACA layar ini adalah `navigator.onLine === false`
    // (LaporanProcessWaterView.vue:499) beserta event `online`/`offline`.
    // Itulah jalur produksinya, dan itulah yang dipalsukan di sini:
    // dokumennya tetap terambil, aplikasinya benar-benar boot, lalu ia
    // melihat dirinya luring dan menolak meminta apa pun. Stub API tetap
    // terpasang justru supaya penolakan itu DAPAT dibantah — kalau layarnya
    // ternyata meminta, state.hits.summary akan naik dan asersi terakhir
    // memerah.
    await page.addInitScript(() => {
      Object.defineProperty(window.navigator, 'onLine', {
        configurable: true,
        get: () => false,
      })
    })

    await page.reload()

    await expect(page.getByTestId('offline-notice')).toBeVisible()
    await expect(page.getByTestId('offline-notice')).toContainText('memerlukan jaringan')
    await expect(page.getByTestId('offline-notice')).toContainText(
      'tidak menampilkan satu angka pun dari pemuatan sebelumnya',
    )

    // NOL ANGKA dirender bersamaan dengan pernyataan itu — bukan angka basi
    // tanpa penanda.
    await expect(page.locator('[data-testid^="pw-metric-card-"]')).toHaveCount(0)
    await expect(page.getByTestId('pw-coverage')).toHaveCount(0)
    // Dan layarnya tidak diam: kerangkanya tetap terender.
    await expect(page.getByTestId('laporan-process-water-mobile')).toBeVisible()

    // LURING MENGHENTIKANNYA SEBELUM PERMINTAAN PERTAMA.
    await expect.poll(() => state.hits.summary).toBe(summaryHits)
  })

  /* ---------------------------------------------------------------- */
  /* Layar BACA SAJA dan jaringan yang tersentuh                       */
  /* ---------------------------------------------------------------- */

  test('layar hanya MEMBACA: nol kontrol tulis, dan nol permintaan POST/PUT/PATCH/DELETE ke API', async ({
    page,
  }) => {
    const writes: string[] = []

    page.on('request', (request) => {
      if (
        request.url().includes('/api/')
        && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method())
      ) {
        writes.push(`${request.method()} ${request.url()}`)
      }
    })

    await stubApi(page)
    await openFullReport(page)
    await openAllRecaps(page)

    // Nol medan input di luar kedua pemilih.
    await expect(page.getByTestId('laporan-process-water-mobile').locator('input')).toHaveCount(0)
    await expect(page.getByTestId('laporan-process-water-mobile').locator('textarea')).toHaveCount(0)
    await expect(page.getByTestId('laporan-process-water-mobile').locator('form')).toHaveCount(0)

    // Dan NOL permintaan tulis — inilah yang menjaga DATABASE DEV di :8000
    // tidak tersentuh oleh spec ini. Login terjadi SEBELUM pendengar ini
    // dipasang, jadi ia tidak terhitung di sini.
    expect(writes, writes.join(' | ')).toHaveLength(0)
  })

  test('tidak ada endpoint lain yang muncul pada catatan jaringan', async ({ page }) => {
    const state = await stubApi(page)

    await openFullReport(page)
    await openAllRecaps(page)

    // KEEMPAT endpoint, dan tidak ada yang kelima.
    for (const url of state.urls) {
      expect(
        /\/api\/process-water-reports\/(periods|summary|export)/.test(url),
        url,
      ).toBe(true)
    }

    expect(state.hits.units).toBe(0)
    await expect.poll(() => state.hits.lines).toBe(1)
    await expect.poll(() => state.hits.periods).toBe(1)
    await expect.poll(() => state.hits.summary).toBe(1)
  })

  test('Back kembali ke Dashboard & Reporting', async ({ page }) => {
    await stubApi(page)
    await openReport(page)

    await page.getByTestId('back-button').click()
    await expectPathname(page, '/dashboard-reporting')
  })
})

/* ================================================================== */
/* PINTU MASUK DARI REPORTING (screen-141)                             */
/* ================================================================== */

/**
 * Tabel `station` lokal kosong pada perangkat yang baru login, dan layar
 * Reporting membaca stasiunnya dari sana. Dibuka lewat layar Daftar Stasiun
 * supaya alur sinkronisasi aplikasi sendiri yang mengisinya — bukan lewat
 * penyemaian langsung. TIDAK ADA TULISAN KE BASIS DATA DEV di sini: yang
 * terisi adalah SQLite DI DALAM browser.
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
   * SATU BARIS yang menentukan layar ini dapat dicapai: entri 'process-water'
   * pada REPORT_ROUTES di ReportingPilihStasiunView.vue — DENGAN TANDA HUBUNG
   * dan TERKUTIP. Kunci bergaris-bawah ('process_water') akan terkompilasi,
   * terlihat benar, dan tidak pernah cocok dengan satu station.type pun.
   * Tanpa test ini, layar laporan bisa lengkap, seluruh test lainnya hijau,
   * dan tile-nya tetap kelabu — pola kegagalan yang sudah terjadi sekali pada
   * laporan Cages & Tracks versi web.
   */
  test('tile Process Water aktif dan menavigasi ke layar laporannya', async ({ page }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await seedLocalStations(page)
    await goToReports(page)

    const tile = page.getByTestId('station-tile-process-water')

    await tile.scrollIntoViewIfNeeded()
    await expect(tile).toBeVisible()
    await expect(tile).toHaveAttribute('aria-disabled', 'false')
    await tile.click()

    // Alamatnya membawa ?production_line_id= dari layar itu, jadi yang
    // dibandingkan adalah PATHNAME-nya saja — bukan toHaveURL('**/x'), yang
    // dengan baseURL terpasang tidak pernah cocok.
    await expectPathname(page, ROUTE)
    await expect(page.getByTestId('laporan-process-water-mobile')).toBeVisible()

    // Dan tidak ada pesan "belum tersedia" yang muncul sebagai gantinya.
    await expect(page.getByTestId('info-message')).toHaveCount(0)
  })
})
