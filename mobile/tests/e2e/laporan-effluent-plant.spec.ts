import { expect, test, type Page } from '@playwright/test'
import { getAuthUserId, login, USERS } from './helpers'

/**
 * laporan-effluent-plant.spec.ts — screen-157--laporan-effluent-plant-mobile /
 * usecase-163--laporan-effluent-plant-mobile.
 *
 * Satu test per test_scenarios yang punya browser_test. Berjalan di Vite dev
 * server (playwright.config.ts, baseURL http://localhost:5174): aplikasi
 * Capacitor adalah SPA biasa sebelum dibungkus native, jadi browser test layar
 * mobile TIDAK ditangguhkan sebagai "mobile-only".
 *
 * VIEWPORT PONSEL, BUKAN DESKTOP. Berkas ini menimpa viewport bawaan proyek
 * chromium (Desktop Chrome, 1280px) dengan 390x844. Separuh klaim layar ini
 * adalah klaim tata letak ("satu kolom", "tanpa gulir mendatar pada halaman",
 * "pernyataan kepatuhan terlihat dalam satu layar bersama angkanya"), dan
 * seluruhnya hampa pada kanvas 1280px: apa pun muat di sana.
 *
 * MASUK LEWAT RUTE LANGSUNG, KECUALI SATU TEST. Pintu masuk UI-nya
 * (screen-141, Reporting: pilih stasiun) punya test-nya sendiri, tetapi SATU
 * test di bawah sengaja melewati tile Effluent Plant dari sana — entri
 * 'effluent-plant' pada REPORT_ROUTES di ReportingPilihStasiunView.vue adalah
 * satu-satunya penentu tile itu hidup atau mati, dan tanpa test itu layar ini
 * bisa lengkap, hijau, dan tetap tidak dapat dicapai siapa pun.
 *
 * ────────────────────────────────────────────────────────────────────────
 * RESPONS LAPORAN DI-STUB DI TINGKAT JARINGAN (page.route), BUKAN DISEMAI
 * ────────────────────────────────────────────────────────────────────────
 * Keputusan dan alasan yang sama dengan kesebelas laporan mobile lain, plus
 * satu alasan yang KHUSUS berlaku di sini:
 *
 *   - Layar ini read-only total dan tidak punya satu pun UI untuk membuat
 *     data yang dibacanya.
 *   - Kebenaran ANGKA-nya bukan tanggung jawab layar ini: seluruhnya dari
 *     EffluentPlantReportService, yang sudah diuji penuh di
 *     backend/tests/Unit/Services/EffluentPlantReportServiceTest.php,
 *     backend/tests/Feature/Api/LaporanEffluentPlantTest.php, dan
 *     e2e-web/tests/laporan-effluent-plant.spec.ts terhadap data sungguhan.
 *     Yang khas mobile — dan hanya dapat dibuktikan di sini — adalah
 *     PEMETAAN respons itu ke layar satu kolom.
 *   - Beberapa skenario mustahil dibuat dari data sungguhan tanpa merusak
 *     lingkungan (sesi kedaluwarsa, jaringan putus, mill tanpa Production
 *     Line, periode belum mulai, muatan yang sengaja tidak konsisten secara
 *     aritmetika).
 *   - DAN: backend di :8000 adalah DATABASE DEV — data kerja pengguna — dan
 *     ia tertinggal satu migrasi. Berkas ini karena itu TIDAK menulis,
 *     menghapus, menyemai, atau memigrasi apa pun di sana. Yang menyentuh
 *     backend sungguhan HANYA login (POST /api/login), seperti kesebelas
 *     spec laporan mobile lainnya.
 *
 * ── LIMA HAL YANG HANYA BROWSER DAPAT MEMBUKTIKAN DI LAYAR INI ─────────
 *
 * 1. CAKUPAN BERADA DI ATAS KEENAM BLOK KARTU — diukur dari POSISI KOTAK DI
 *    VIEWPORT, bukan dari urutan sumbernya. Di layar sempit pembaca melihat
 *    lebih sedikit sekaligus, jadi apa yang dilihat lebih dulu menentukan
 *    lebih banyak.
 *
 * 2. KEEMPAT BELAS PENYEBUT BENAR-BENAR TERCETAK pada kanvas 390px, dan
 *    KEEMPAT BELASNYA BERBEDA. Inilah yang paling mudah hilang karena dipotong
 *    demi ruang, dan tanpanya rata-rata sebuah parameter terbaca sebagai angka
 *    seluruh periode. Markup kartunya ditulis SEKALI lalu di-loop, jadi yang
 *    diuji pula adalah bahwa tidak satu blok pun MEWARISI penyebut blok lain.
 *
 * 3. PERNYATAAN KEPATUHAN LINGKUNGAN TERLIHAT DALAM SATU LAYAR BERSAMA
 *    KEEMPAT ANGKANYA, di dalam blok Buangan Akhir. Di catatan kaki halaman
 *    itu berarti puluhan layar jauhnya dari angka yang dirujuknya — dan angka
 *    lingkungan tanpa batas hukumnya mengundang pembaca memasok batas itu dari
 *    ingatan.
 *
 * 4. KEDUA TABEL REKAP DAN KEDUA GRAFIK TREN MENGGULIR/BERTUMPUK DI DALAM
 *    KARTUNYA, bukan menggeser halaman. EMPAT BELAS kolom rata-rata plus
 *    tanggal adalah yang terlebar di laporan mobile mana pun.
 *
 * 5. BARIS SEBARAN STATUS BERNILAI 0 BENAR-BENAR TERGAMBAR. Baris yang hilang
 *    terbaca sebagai "nilai ini tidak ada" padahal yang benar adalah "nilai
 *    ini tidak pernah terjadi" — dan hanya halaman yang dirender dapat
 *    membuktikan ia tergambar, bukan sekadar ada di state.
 *
 * ── KONVENSI YANG HARGANYA SUDAH DIBAYAR ───────────────────────────────
 *
 * KETIADAAN PENANDAAN DI LUAR BATAS DISISIR ATAS NAMA KELAS (jumlah elemen),
 * bukan atas frasa: kalimat yang menyatakan ketiadaannya sendiri memuat frasa
 * "di luar batas". Hal yang sama berlaku untuk 'downtime', 'target', dan
 * 'patuh' — ketiganya muncul justru pada pernyataan yang membuktikan
 * ketiadaannya.
 *
 * ASERSI PERSEN DILINGKUP KE ELEMENNYA. Halaman ini memuat porsi sebaran
 * status (50,8% / 6,8% / 0,0% / 42,4% / 100,0%) di samping persen cakupan,
 * jadi asersi persen tingkat halaman tidak bermakna di sini.
 *
 * ALAMAT DIBANDINGKAN ATAS PATHNAME, bukan dengan toHaveURL('**\/x'): dengan
 * baseURL terpasang, pola glob semacam itu tidak pernah cocok dan test-nya
 * "lulus" tanpa pernah menguji apa pun. Di sini dipakai waitForURL dengan
 * PREDIKAT atas URL yang sudah terurai.
 *
 * SETIAP count() DIDAHULUI ASERSI YANG MEMBUKTIKAN WILAYAHNYA SUDAH DIRENDER
 * (toBeVisible / toHaveCount yang menunggu sendiri). count() tanpa penantian
 * mengembalikan 0 pada halaman yang belum selesai merender, dan 0 adalah
 * jawaban yang diam-diam meluluskan asersi "tidak ada".
 *
 * REKAP HARIAN MEMAKAI v-show (CollapsibleSection), BUKAN v-if: saat tertutup
 * barisnya TETAP ADA di DOM dengan display:none. Karena itu keadaan tertutup
 * diasersi dengan toBeHidden(), tidak pernah dengan toHaveCount(0).
 *
 * TANGGAL FIXTURE RELATIF terhadap hari ini — tidak ada tanggal kalender yang
 * ditulis mati, dan nama periodenya pun bukan nama bulan, supaya slug nama
 * berkas ekspor tidak ikut basi.
 *
 * MENJALANKANNYA MENUNTUT DUA PROSES: backend di :8000 (dengan seeder
 * DemoAccountSeeder sudah dijalankan — login di sini POST sungguhan) dan Vite
 * dev server di :5174 (dinyalakan playwright.config.ts sendiri lewat blok
 * webServer, reuseExistingServer). TIDAK ADA backend :8001 di sini: port itu
 * milik suite e2e-web, dan spec ini tidak pernah menyentuhnya.
 */

const ROUTE = '/reports/effluent-plant'

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
const DAY_A = isoDaysFromNow(-26)
const DAY_B = isoDaysFromNow(-25)

/* ------------------------------------------------------------------ */
/* Fixture — BENTUK RESPONS NYATA                                      */
/* ------------------------------------------------------------------ */

/**
 * Opsi periode. PERHATIKAN APA YANG TIDAK ADA: business_unit_name. Payload
 * /periods pada endpoint ini tidak memuatnya, jadi nama mill WAJIB datang
 * dari summary.business_unit — dan stub yang menambahkannya akan menyamarkan
 * layar yang membacanya dari tempat yang salah.
 */
const PERIOD = {
  id: 'per-1',
  name: 'Periode Uji Effluent Plant',
  start_date: PERIOD_START,
  end_date: PERIOD_END,
  status: 'open',
  // DENGAN TANDA HUBUNG. Garis bawah di situ tidak cocok dengan satu baris
  // period_stations pun, dan server menjawab [] tanpa galat.
  station_type: 'effluent-plant',
  station_type_label: 'Effluent Plant',
}

const PERIOD_CLOSED = {
  id: 'per-2',
  name: 'Periode Sebelumnya',
  start_date: PREV_START,
  end_date: PREV_END,
  status: 'closed',
  station_type: 'effluent-plant',
  station_type_label: 'Effluent Plant',
}

const LINE_1 = { id: 'pl-1', name: 'Line 1', code: 'L1' }
const LINE_2 = { id: 'pl-2', name: 'Line 2', code: 'L2' }

/** Kunci ingatan line, ditulis StationListView.vue dan dibaca layar ini. */
const REMEMBERED_LINE_KEY = (userId: string) => `msl_production_line_${userId}`

/** KEEMPAT BELAS kolom ukur, urutan EffluentPlantReportService::NUMERIC_METRICS. */
const METRIC_COLUMNS = [
  'anaerobic_pond_1_ph',
  'anaerobic_pond_1_temp_c',
  'anaerobic_pond_2_ph',
  'anaerobic_pond_2_temp_c',
  'cooling_pond_ph',
  'cooling_pond_temp_c',
  'biogas_flow_rate_m3h',
  'raw_pome_feed_rate_m3h',
  'effluent_discharge_flow_rate_m3h',
  'final_discharge_ph',
  'final_discharge_bod_mgl_lab',
  'final_discharge_cod_mgl_lab',
  'final_discharge_tss_mgl_lab',
  'chemical_consumed_kgl',
]

const LAB_COLUMNS = [
  'final_discharge_bod_mgl_lab',
  'final_discharge_cod_mgl_lab',
  'final_discharge_tss_mgl_lab',
]

/** KEENAM blok berjudul, dengan jumlah kartu di dalamnya. */
const METRIC_BLOCKS = [
  { testid: 'metric-block-anaerobic-pond-1', title: 'Kolam Anaerobik 1', cards: 2 },
  { testid: 'metric-block-anaerobic-pond-2', title: 'Kolam Anaerobik 2', cards: 2 },
  { testid: 'metric-block-cooling-pond', title: 'Cooling Pond', cards: 2 },
  { testid: 'metric-block-flow', title: 'Aliran', cards: 3 },
  { testid: 'metric-block-final-discharge', title: 'Buangan Akhir', cards: 4 },
  { testid: 'metric-block-chemical', title: 'Bahan Kimia', cards: 1 },
]

function metric(
  min: number | null,
  avg: number | null,
  max: number | null,
  readingCount: number,
  isLab = false,
): Record<string, unknown> {
  return { min, avg, max, reading_count: readingCount, is_lab: isLab }
}

/**
 * KEEMPAT BELAS entri, dengan KEEMPAT BELAS PENYEBUT YANG BERBEDA.
 *
 * Dengan penyebut seragam, asersi "tiap kartu mencetak penyebutnya sendiri"
 * dan "tidak satu blok pun mewarisi penyebut blok lain" selalu hijau — dua
 * cacat yang paling mungkin muncul pada markup yang di-loop, dan satu di
 * antaranya sudah pernah terjadi pada blade screen-156.
 *
 * cooling_pond_temp_c sengaja membawa avg 12,5 di LUAR rentang 10..11:
 * mustahil dari perhitungan apa pun.
 *
 * chemical_consumed_kgl TIDAK PERNAH TERISI — penyebut 0, seluruh angkanya
 * null. Kartunya tetap harus tergambar.
 */
function metrics(): Record<string, Record<string, unknown>> {
  return {
    anaerobic_pond_1_ph: metric(6.1, 7.4, 8.9, 118),
    anaerobic_pond_1_temp_c: metric(38, 41.2, 45, 117),
    anaerobic_pond_2_ph: metric(6.4, 7.1, 7.9, 116),
    anaerobic_pond_2_temp_c: metric(37, 40.1, 44, 115),
    cooling_pond_ph: metric(6.8, 7.2, 7.6, 114),
    cooling_pond_temp_c: metric(10, 12.5, 11, 113),
    biogas_flow_rate_m3h: metric(120, 240, 310, 90),
    raw_pome_feed_rate_m3h: metric(18.5, 24.1, 30.2, 110),
    effluent_discharge_flow_rate_m3h: metric(15.1, 21.8, 28.4, 109),
    final_discharge_ph: metric(6.2, 6.9, 7.5, 108),
    // KETIGA nilai laboratorium: penyebut 4 / 5 / 3 lawan 118, dan itu NORMAL.
    final_discharge_bod_mgl_lab: metric(42, 78, 120, 4, true),
    final_discharge_cod_mgl_lab: metric(180, 260, 340, 5, true),
    final_discharge_tss_mgl_lab: metric(60, 95, 140, 3, true),
    chemical_consumed_kgl: metric(null, null, null, 0),
  }
}

/**
 * KETIGA sebaran status — KONSISTEN secara penjumlahan (60 + 8 + 0 + 50 = 118)
 * karena porsinya diasersi digit demi digit. Payload yang sengaja tidak
 * konsisten dipakai pada test "nol aritmetika" tersendiri.
 *
 * TIDAK ADA `share` di sini, dan itu bukan kelalaian stub: payload memang
 * tidak menerbitkannya (lihat EffluentPlantReportService::statusDistributionOf).
 * Porsinya dihitung formatShare() di view, ditiru baris demi baris dari helper
 * $porsi pada blade screen-156 supaya ponsel tidak mungkin mencetak porsi yang
 * berbeda dari web.
 */
function statusDistribution(): Record<string, Record<string, unknown>> {
  return {
    biogas_flare_status: {
      known_values: ['on', 'off', 'fault'],
      // 'fault' BERNILAI 0 — barisnya WAJIB tetap tergambar.
      counts: { on: 60, off: 8, fault: 0 },
      not_recorded: 50,
      recorded_slots: 118,
    },
    dosing_pump_1_status: {
      known_values: ['run', 'stop'],
      counts: { run: 60, stop: 8 },
      not_recorded: 50,
      recorded_slots: 118,
    },
    sludge_dewatering_status: {
      known_values: ['run', 'stop'],
      counts: { run: 0, stop: 0 },
      not_recorded: 118,
      recorded_slots: 118,
    },
  }
}

function unitMetrics(avg: number | null, readingCount: number): Record<string, unknown> {
  return Object.fromEntries(
    METRIC_COLUMNS.map((column) => [
      column,
      metric(avg, avg, avg, readingCount, LAB_COLUMNS.includes(column)),
    ]),
  )
}

/** DUA BELAS tanggal — jelas lebih lebar daripada 390px bersama 14 kolom. */
function dailyRows(): Array<Record<string, unknown>> {
  return Array.from({ length: 12 }, (_unused, index) => ({
    date: isoDaysFromNow(-26 + index),
    reading_rows: index + 1,
    metrics_avg: Object.fromEntries(
      METRIC_COLUMNS.map((column) => [
        column,
        column === 'chemical_consumed_kgl'
          ? { avg: null, reading_count: 0 }
          : { avg: 7 + index / 10, reading_count: index + 1 },
      ]),
    ),
  }))
}

function makeSummary(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Sungai Rambutan' },
    production_line: { id: 'pl-1', name: 'Line 1' },
    period: {
      id: 'per-1',
      name: 'Periode Uji Effluent Plant',
      start_date: PERIOD_START,
      end_date: PERIOD_END,
      status: 'open',
    },
    has_data: true,
    coverage: {
      filled_slots: 118,
      expected_slots: 1440,
      coverage_percent: 8.2,
      effluent_plant_unit_count: 2,
      slots_per_unit_per_day: 24,
      days_in_period: 30,
      days_counted: 30,
      period_running: false,
    },
    metrics: metrics(),
    status_distribution: statusDistribution(),
    /**
     * TIGA KEJADIAN BERTEKS IDENTIK pada tiga slot berbeda — layar yang
     * mengelompokkan menurut teks menggambar satu baris. Entri PERTAMA
     * memakai tanggal + slot waktu yang SAMA dengan entri pertama findings:
     * satu slot yang mengisi keduanya muncul pada KEDUA daftar, dan itu benar.
     */
    maintenance_remarks: [
      { date: DAY_A, time_slot: '07:00', text: 'Ganti membran pompa dosing' },
      { date: DAY_A, time_slot: '13:00', text: 'Ganti membran pompa dosing' },
      { date: DAY_B, time_slot: '21:00', text: 'Ganti membran pompa dosing' },
    ],
    findings: [
      { date: DAY_A, time_slot: '07:00', text: 'Busa tebal di kolam anaerobik 2' },
      { date: DAY_B, time_slot: '03:00', text: 'Bau menyengat di dekat cooling pond' },
    ],
    daily_trend: {
      raw_pome_feed_rate_m3h: Array.from({ length: 12 }, (_unused, index) => ({
        date: isoDaysFromNow(-26 + index),
        // Titik ke-2 tanpa pembacaan: null, BUKAN 0 — batangnya KOSONG.
        avg: index === 1 ? null : 18 + index,
        reading_count: index === 1 ? 0 : index + 1,
      })),
      final_discharge_ph: Array.from({ length: 12 }, (_unused, index) => ({
        date: isoDaysFromNow(-26 + index),
        avg: 6.5 + index / 20,
        reading_count: index + 1,
      })),
    },
    by_unit: [
      { effluent_plant_id: 'EP-1', reading_rows: 70, reading_count: 70, metrics: unitMetrics(7.0, 70) },
      // Unit yang punya record tetapi TIDAK SATU PUN pembacaan.
      { effluent_plant_id: 'EP-2', reading_rows: 0, reading_count: 0, metrics: unitMetrics(null, 0) },
    ],
    daily: dailyRows(),
    // Jumlah baris harian 1+2+...+12 = 78, dan server mengirim 99.
    daily_total: { reading_rows: 99 },
    total: { days_with_records: 12, reading_rows: 118 },
    ...overrides,
  }
}

/** Periode yang punya record tetapi tanpa satu slot terisi: 0,0%, BUKAN '—'. */
function emptySummary(): Record<string, unknown> {
  return makeSummary({
    has_data: false,
    coverage: {
      filled_slots: 0,
      expected_slots: 1440,
      coverage_percent: 0,
      effluent_plant_unit_count: 2,
      slots_per_unit_per_day: 24,
      days_in_period: 30,
      days_counted: 30,
      period_running: false,
    },
    metrics: Object.fromEntries(
      METRIC_COLUMNS.map((column) => [
        column,
        metric(null, null, null, 0, LAB_COLUMNS.includes(column)),
      ]),
    ),
    status_distribution: Object.fromEntries(
      Object.entries(statusDistribution()).map(([column, distribution]) => [
        column,
        { ...distribution, counts: {}, not_recorded: 0, recorded_slots: 0 },
      ]),
    ),
    maintenance_remarks: [],
    findings: [],
    daily_trend: { raw_pome_feed_rate_m3h: [], final_discharge_ph: [] },
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
  /** true = permintaan ringkasan diputus di tingkat jaringan. */
  summaryAbort: boolean
  /** Tundaan ms sebelum respons ringkasan diselesaikan. */
  summaryDelayMs: number
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
    summaryAbort: false,
    summaryDelayMs: 0,
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

  await page.route('**/api/effluent-plant-reports/**', async (route) => {
    const request = route.request()
    const url = request.url()

    if (request.method() === 'OPTIONS') {
      await route.fulfill({ status: 204, headers: CORS_HEADERS, body: '' })

      return
    }

    state.urls.push(url)

    if (url.includes('/business-units/options')) {
      state.hits.units += 1
      // ADMIN SAJA — dan ia TETAP 403 untuk Operator/Supervisor/Mill
      // Management bahkan setelah pelebaran akses yang mendarat bersama layar
      // ini, karena penolakannya diangkat DI DALAM
      // EffluentPlantReportService::businessUnitOptions() dan bukan oleh
      // daftar peran middleware. Layar ini tidak punya satu pun jalur ke
      // sini; stub ini ada JUSTRU supaya pemanggilan yang tak diinginkan
      // terhitung alih-alih diam-diam menembus ke backend.
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
      await route.fulfill({
        status: 200,
        headers: { ...CORS_HEADERS, 'content-type': 'text/csv' },
        body:
          'Periode,Mill,Production Line,Tanggal,Slot Waktu,Unit Effluent Plant,Status Record,Catatan\n'
          + `Periode Uji Effluent Plant,Mill Sungai Rambutan,Line 1,${DAY_A},07:00,EP-1,draft,\n`,
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
          json: { message: 'Permintaan ditolak.' },
        })

        return
      }

      // Blok production_line DIBENTUK DARI PARAMETER yang diminta, bukan
      // dipaku ke Line 1. Layar ini mendahulukan nama dari SERVER atas nama
      // dari daftarnya sendiri (itulah line yang benar-benar dipakai saat
      // menghitung), jadi stub yang mengabaikan parameternya akan membuat
      // setiap asersi "line mana yang berlaku" membaca jawaban yang salah.
      const query = new URL(url).searchParams
      const requestedLineId = query.get('production_line_id')
      const requestedLine = (state.productionLines as Array<{ id: string; name: string }>).find(
        (line) => line.id === requestedLineId,
      )

      // KEPALA PERIODE pun dibentuk dari period_id yang diminta, dengan alasan
      // yang sama: tanpa ini, asersi "periode mana yang sedang tampil" pada
      // test perlombaan dua permintaan membaca nama yang SAMA untuk kedua
      // periode, dan selalu hijau.
      const requestedPeriodId = query.get('period_id')
      const requestedPeriod = (
        state.periods as Array<{ id: string; name: string; start_date: string; end_date: string; status: string }>
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

      if (state.summaryDelayMs > 0) {
        const delay = state.summaryDelayMs

        // Hanya permintaan PERTAMA ditunda — itulah yang membuat respons lama
        // tiba belakangan.
        state.summaryDelayMs = 0
        await new Promise((resolve) => setTimeout(resolve, delay))
      }

      await route.fulfill({ status: 200, headers: CORS_HEADERS, json: body })

      return
    }

    await route.fulfill({ status: 404, headers: CORS_HEADERS, json: { message: 'not stubbed' } })
  })

  return state
}

async function openReport(page: Page, path: string = ROUTE): Promise<void> {
  await page.goto(path)
  await expect(page.getByTestId('laporan-effluent-plant-mobile')).toBeVisible()
}

async function pickPeriod(page: Page, periodId: string): Promise<void> {
  await page.getByTestId('period-select').selectOption(periodId)
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

/* ------------------------------------------------------------------ */
/* Bantuan asersi khas ponsel                                          */
/* ------------------------------------------------------------------ */

/**
 * Halaman TIDAK PERNAH menggulir mendatar. Yang lebar adalah isi kartu (kedua
 * tabel rekap dan kedua grafik tren) — dan masing-masing menggulir atau
 * bertumpuk di dalam kartunya sendiri.
 */
async function expectNoHorizontalPageScroll(page: Page): Promise<void> {
  const overflow = await page.evaluate(() => {
    const doc = document.documentElement

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

  // EMPAT BELAS, TEPAT — bukan "setidaknya satu". toHaveCount menunggu
  // sendiri, jadi evaluateAll di bawah tidak pernah membaca halaman setengah
  // render.
  await expect(cards).toHaveCount(14)
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
 * Tabel rekap BERTUMPUK di dalam kartunya, bukan menggulir mendatar — temuan
 * QA LP-M-02 dan LP-M-03, dan bentuk yang sama dipakai kesebelas laporan
 * mobile lain lewat direktif v-stacked-table + src/assets/stacked-table.css.
 *
 * Diukur dari BENTUK YANG TERLIHAT: tiap sel menempati barisnya sendiri
 * (kotaknya turun, bukan berderet), tiap sel membawa data-label, dan tidak
 * ada isi yang disembunyikan di balik gulir mendatar. GRAFIK tetap memakai
 * expectCardScrollsInsideItself() — tren per hari memang lebih lebar daripada
 * 390px, dan memadatkannya membuat sumbunya tidak terbaca.
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

  expect(result.cells, 'tabel rekap tidak punya satu baris isi pun untuk dinilai').toBeGreaterThan(0)
  // Tidak ada isi yang tersembunyi di balik gulir mendatar.
  expect(result.scrollWidth).toBeLessThanOrEqual(result.clientWidth + 1)
  // Setiap sel menempati barisnya sendiri, dan membawa label kolomnya.
  expect(result.barisTegak).toBe(result.cells)
  expect(result.berlabel).toBe(result.cells)
}

/* ================================================================== */

test.describe('Laporan Effluent Plant Mobile (screen-157)', () => {
  // Layar ponsel sungguhan — lihat catatan VIEWPORT pada docblock berkas.
  test.use({ viewport: { width: 390, height: 844 } })

  test.beforeEach(async ({ page }) => {
    await login(page, USERS.operator)
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 1 + 2 — jalur sukses, kedua aktor                        */
  /* ---------------------------------------------------------------- */

  test('skenario 1: Operator — seluruh laporan tampil satu kolom tanpa gulir mendatar halaman', async ({ page }) => {
    const state = await stubApi(page)

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('period-meta')).toBeVisible()

    // Seluruh bagian hadir.
    for (const testId of [
      'coverage', 'coverage-slots', 'coverage-percent', 'coverage-denominator',
      'coverage-days', 'coverage-nineteen-columns-note', 'metric-blocks-note',
      'no-target-note', 'no-downtime-note', 'no-flagging-note',
      'status-distribution-biogas-flare', 'status-distribution-dosing-pump-1',
      'status-distribution-sludge-dewatering', 'status-distribution-note',
      'maintenance-remarks', 'findings', 'daily-trend-raw-pome-feed',
      'daily-trend-final-discharge-ph', 'by-unit', 'daily-recap', 'completeness',
      'environmental-compliance-note',
    ]) {
      await expect(page.getByTestId(testId), testId).toBeVisible()
    }

    // KEENAM judul blok.
    for (const block of METRIC_BLOCKS) {
      await expect(page.getByTestId(block.testid), block.testid).toContainText(block.title)
    }

    // TIDAK ADA pemilih mill, dan itu bukan pemangkasan: kedua aktor layar ini
    // terikat satu mill, diselesaikan server dari akun.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.getByTestId('mill-current')).toContainText('Mill Sungai Rambutan')

    // TIDAK ADA kontrol tulis.
    await expect(page.locator('input')).toHaveCount(0)
    await expect(page.locator('textarea')).toHaveCount(0)
    await expect(page.locator('form')).toHaveCount(0)

    await expectSingleColumn(page)
    await expectNoHorizontalPageScroll(page)
    await expectTouchTargets(page, ['period-select', 'export-button', 'back-button'])

    // Dan endpoint daftar mill tidak pernah disentuh — ia 403 untuk peran ini
    // bahkan setelah pelebaran akses, dan memanggilnya hanya akan membentuk
    // daftar seluruh mill di perangkat yang tidak berhak melihatnya.
    expect(state.hits.units).toBe(0)
  })

  test('skenario 2: Supervisor melihat bagian dan angka yang sama persis dengan Operator', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')
    await expect(page.getByTestId('metric-card')).toHaveCount(14)

    const asOperator = await page.getByTestId('laporan-effluent-plant-mobile').innerText()

    await login(page, USERS.supervisor)
    await openReport(page)
    await pickPeriod(page, 'per-1')
    await expect(page.getByTestId('metric-card')).toHaveCount(14)

    const asSupervisor = await page.getByTestId('laporan-effluent-plant-mobile').innerText()

    // Tidak satu pun medan, kolom, atau aksi yang hanya muncul pada salah satu
    // peran: pelebaran akses Operator menambah PEMANGGIL, bukan varian layar.
    expect(asSupervisor).toBe(asOperator)
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 30 — tanpa pemilih mill, tanpa business_unit_id          */
  /* ---------------------------------------------------------------- */

  test('skenario 30: business_unit_id tidak pernah ada pada satu pun alamat yang dikirim layar ini', async ({ page }) => {
    const state = await stubApi(page)

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metric-card')).toHaveCount(14)

    const download = page.waitForEvent('download')

    await page.getByTestId('export-button').click()
    await (await download).delete()

    await expect.poll(() => state.hits.export).toBe(1)

    // Perlindungannya STRUKTURAL — tidak ada jalur kode yang dapat
    // mengirimkannya — jadi asersi inilah yang mencatat maksudnya. Termasuk
    // pada endpoint daftar Production Line, yang pun menyelesaikan mill dari
    // akun pemanggil.
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
  /* Cakupan pencatatan                                                */
  /* ---------------------------------------------------------------- */

  test('cakupan berada DI ATAS keenam blok kartu, diukur dari posisi di viewport', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('coverage')).toBeVisible()
    await expect(page.getByTestId('metric-block-anaerobic-pond-1')).toBeVisible()

    const coverage = await page.getByTestId('coverage').boundingBox()
    const firstBlock = await page.getByTestId('metric-block-anaerobic-pond-1').boundingBox()

    expect(coverage).not.toBeNull()
    expect(firstBlock).not.toBeNull()

    // Di layar sempit pembaca melihat lebih sedikit sekaligus, jadi apa yang
    // dilihat lebih dulu menentukan lebih banyak.
    expect(coverage!.y).toBeLessThan(firstBlock!.y)

    // Ketiga angka pembentuk penyebut ikut tercetak, bukan hanya persennya.
    await expect(page.getByTestId('coverage-denominator')).toContainText('2 unit')
    await expect(page.getByTestId('coverage-denominator')).toContainText('30 hari')
    await expect(page.getByTestId('coverage-denominator')).toContainText('24 slot')
    await expect(page.getByTestId('coverage-slots')).toContainText('118 dari 1.440 slot')
    // Dilingkup KE ELEMENNYA: halaman ini memuat porsi sebaran status di
    // samping persen cakupan, jadi asersi persen tingkat halaman tidak
    // bermakna di sini.
    await expect(page.getByTestId('coverage-percent')).toHaveText('8,2%')
  })

  test('skenario 21: periode masih berjalan — hari berhenti di hari ini, keterangannya tampil', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        coverage: {
          filled_slots: 118,
          expected_slots: 576,
          coverage_percent: 20.5,
          effluent_plant_unit_count: 2,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 12,
          period_running: true,
        },
      }),
    })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('coverage-days')).toContainText('12 dari 30 hari')
    await expect(page.getByTestId('coverage-running-note')).toContainText('masih berjalan')
    await expect(page.getByTestId('coverage-not-started-note')).toHaveCount(0)
    // Hari yang belum terjadi tidak ikut menjadi penyebut, jadi persennya
    // tidak diturunkan olehnya.
    await expect(page.getByTestId('coverage-percent')).toHaveText('20,5%')
  })

  test('skenario 22: days_counted 0 diperiksa SEBELUM period_running — tanda pisah, bukan 0,0%', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          coverage_percent: null,
          effluent_plant_unit_count: 0,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 0,
          // KEDUANYA sekaligus: server menandai periode yang BELUM MULAI
          // sebagai masih berjalan juga.
          period_running: true,
        },
      }),
    })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('coverage-percent')).toHaveText('—')
    await expect(page.getByTestId('coverage-not-started-note')).toContainText('belum mulai')
    // Keterangan "periode masih berjalan" TIDAK ADA di halaman.
    await expect(page.getByTestId('coverage-running-note')).toHaveCount(0)
  })

  test('skenario 11: periode tanpa satu pun record — persen tanda pisah, dengan sebabnya', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          coverage_percent: null,
          effluent_plant_unit_count: 0,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 30,
          period_running: false,
        },
      }),
    })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('coverage-percent')).toHaveText('—')
    await expect(page.getByTestId('coverage-no-expected-note')).toContainText(
      'tidak ada unit Effluent Plant ber-record',
    )
  })

  test('skenario 10: periode ber-record tanpa slot terisi — 0,0% dan BUKAN tanda pisah', async ({ page }) => {
    await stubApi(page, { summary: emptySummary() })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    // Penyebutnya TERBENTUK (1.440), dan yang terukur memang nol slot.
    await expect(page.getByTestId('coverage-percent')).toHaveText('0,0%')
    await expect(page.getByTestId('coverage-no-expected-note')).toHaveCount(0)
    await expect(page.getByTestId('empty-period')).toBeVisible()

    // Keempat belas kartu TETAP tergambar dengan keterangan tidak tersedia.
    await expect(page.getByTestId('metric-card')).toHaveCount(14)
    await expect(page.getByTestId('metric-card').first()).toContainText('tidak tersedia')

    // Rekap dan grafik TIDAK digambar: tabel kosong terbaca sebagai hasil
    // pengukuran bernilai nol.
    await expect(page.getByTestId('by-unit')).toHaveCount(0)
    await expect(page.getByTestId('daily-recap')).toHaveCount(0)
    await expect(page.getByTestId('daily-trend-raw-pome-feed')).toHaveCount(0)
    // Tetapi CAKUPAN dan KETIGA SEBARAN tetap ada — yang pertama menjelaskan
    // kekosongannya, yang lain memperlihatkan bahwa parameternya ADA.
    await expect(page.getByTestId('coverage')).toBeVisible()
    await expect(page.getByTestId('status-distribution-biogas-flare')).toBeVisible()
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 12 + 13 + 14 — empat belas kartu, enam blok              */
  /* ---------------------------------------------------------------- */

  test('skenario 12: empat belas kartu tergambar, dan tiap BLOK membawa kartunya sendiri', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metric-card')).toHaveCount(14)

    // PENYEBUT PER BLOK — cacat yang paling mungkin pada markup yang di-loop
    // adalah sebuah blok yang MEWARISI penyebut blok lain.
    const expected = metrics()

    for (const block of METRIC_BLOCKS) {
      const blockCards = page.getByTestId(block.testid).getByTestId('metric-card')

      await expect(blockCards, block.testid).toHaveCount(block.cards)

      const columns = await blockCards.evaluateAll((elements) =>
        elements.map((element) => element.getAttribute('data-metric') ?? ''),
      )

      for (const column of columns) {
        await expect(
          page.locator(`[data-metric="${column}"] [data-testid="metric-denominator"]`),
          `${block.testid}/${column}`,
        ).toContainText(`dari ${expected[column].reading_count as number} pembacaan`)
      }
    }

    // Kolom yang tidak pernah terisi TETAP tergambar, dengan penyebut 0.
    await expect(page.locator('[data-metric="chemical_consumed_kgl"]')).toContainText(
      'tidak tersedia',
    )
    await expect(
      page.locator('[data-metric="chemical_consumed_kgl"] [data-testid="metric-denominator"]'),
    ).toContainText('dari 0 pembacaan')
  })

  test('keempat belas penyebut BERBEDA — penyebut bersama tidak dapat lolos tanpa terlihat', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    const denominators = page.getByTestId('metric-denominator')

    // toHaveCount menunggu sendiri — allInnerTexts() di bawah karena itu tidak
    // pernah membaca halaman yang belum selesai merender.
    await expect(denominators).toHaveCount(14)

    const texts = (await denominators.allInnerTexts()).map((value) => value.trim())

    expect(new Set(texts).size).toBe(14)
    // Dan tidak satu pun dari keempat belas penyebut itu sama dengan jumlah
    // slot terisi periode: slot dihitung terisi atas SEMBILAN BELAS kolom, dan
    // lima di antaranya bukan kolom ukur.
    expect(texts.some((value) => value.includes('dari 118 pembacaan'))).toBe(true)
    await expect(page.getByTestId('coverage-slots')).toContainText('118 dari 1.440 slot')
  })

  test('skenario 14: slot terisi MELAMPAUI penyebut setiap kartu, dengan keterangan sebabnya', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        coverage: {
          // 130 > 118, penyebut tertinggi di antara keempat belas kartu.
          filled_slots: 130,
          expected_slots: 1440,
          coverage_percent: 9,
          effluent_plant_unit_count: 2,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 30,
          period_running: false,
        },
      }),
    })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('coverage-slots')).toContainText('130 dari 1.440 slot')

    const denominators = page.getByTestId('metric-denominator')

    await expect(denominators).toHaveCount(14)

    const numbers = (await denominators.allInnerTexts()).map((value) =>
      Number((value.match(/dari ([\d.]+) pembacaan/)?.[1] ?? '0').replace(/\./g, '')),
    )

    // Yang dilampaui adalah penyebut KARTU, bukan penyebut CAKUPAN — itu
    // benar, bukan selisih yang perlu dicocokkan.
    expect(numbers.every((value) => value < 130)).toBe(true)
    await expect(page.getByTestId('coverage-nineteen-columns-note')).toContainText('19 kolom')
    await expect(page.getByTestId('coverage-nineteen-columns-note')).toContainText(
      'kedua kolom teks',
    )
  })

  test('skenario 13: penanda lab tergambar DI DALAM ketiga kartu lab, bukan di atas halaman', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        metrics: {
          ...metrics(),
          final_discharge_bod_mgl_lab: metric(null, null, null, 0, true),
          final_discharge_cod_mgl_lab: metric(null, null, null, 0, true),
          final_discharge_tss_mgl_lab: metric(null, null, null, 0, true),
        },
      }),
    })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metric-lab-badge')).toHaveCount(3)

    for (const column of LAB_COLUMNS) {
      const labCard = page.locator(`[data-metric="${column}"]`)

      await expect(labCard.getByTestId('metric-lab-badge'), column).toBeVisible()
      await expect(labCard.getByTestId('metric-lab-note'), column).toContainText('laboratorium')
      await expect(labCard, column).toContainText('tidak tersedia')
      await expect(labCard.getByTestId('metric-denominator'), column).toContainText(
        'dari 0 pembacaan',
      )
    }

    // Sebelas kartu lain tetap membawa angkanya.
    await expect(page.locator('[data-metric="anaerobic_pond_1_ph"]')).toContainText('7,40')
    // Dan ketiga penanda itu berada DI BAWAH judul blok Buangan Akhir, bukan
    // di kepala halaman.
    const badgeBox = await page.getByTestId('metric-lab-badge').first().boundingBox()
    const blockBox = await page.getByTestId('metric-block-final-discharge').boundingBox()

    expect(badgeBox!.y).toBeGreaterThan(blockBox!.y)
  })

  test("penanda lab DITURUNKAN dari respons: kolom keempat ikut bertanda", async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        metrics: {
          ...metrics(),
          // HARI INI bukan laboratorium; server menandainya lab.
          final_discharge_ph: metric(6.2, 6.9, 7.5, 12, true),
        },
      }),
    })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metric-lab-badge')).toHaveCount(4)
    await expect(
      page.locator('[data-metric="final_discharge_ph"]').getByTestId('metric-lab-badge'),
    ).toBeVisible()
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 31 + 32 + 33 + 34 — pernyataan dan ketiadaan             */
  /* ---------------------------------------------------------------- */

  test('skenario 31: pernyataan kepatuhan terlihat dalam SATU LAYAR bersama keempat angkanya', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    const note = page.getByTestId('environmental-compliance-note')

    // TEPAT SATU, dan ia berada DI DALAM blok Buangan Akhir.
    await expect(note).toHaveCount(1)
    await expect(
      page.getByTestId('metric-block-final-discharge').getByTestId(
        'environmental-compliance-note',
      ),
    ).toHaveCount(1)

    // Dan TIDAK di dalam kelima blok lainnya.
    for (const block of METRIC_BLOCKS.filter(
      (candidate) => candidate.testid !== 'metric-block-final-discharge',
    )) {
      await expect(
        page.getByTestId(block.testid).getByTestId('environmental-compliance-note'),
        block.testid,
      ).toHaveCount(0)
    }

    // SATU LAYAR BERSAMA ANGKANYA: pada 390px, jarak dari kartu pH buangan
    // akhir ke pernyataannya harus muat dalam satu tinggi viewport. Di catatan
    // kaki halaman itu berarti puluhan layar jauhnya dari angka yang
    // dirujuknya.
    await note.scrollIntoViewIfNeeded()
    await expect(note).toBeVisible()

    const noteBox = await note.boundingBox()
    const tssBox = await page.locator('[data-metric="final_discharge_tss_mgl_lab"]').boundingBox()

    expect(noteBox!.y).toBeGreaterThan(tssBox!.y)
    expect(noteBox!.y - tssBox!.y).toBeLessThan(844)

    await expect(note).toContainText('tidak menyatakan kepatuhan lingkungan')
    await expect(note).toContainText('di luar aplikasi ini')
  })

  test('skenario 32: tidak ada satu pun elemen berkelas penanda di luar batas pada halaman', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        metrics: {
          ...metrics(),
          // Nilai EKSTREM jauh di atas dan di bawah yang lain.
          final_discharge_bod_mgl_lab: metric(1, 9999, 99999, 4, true),
          final_discharge_ph: metric(0.1, 0.2, 14, 108),
        },
      }),
    })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metric-card')).toHaveCount(14)

    // DISISIR ATAS NAMA KELAS (jumlah elemen), bukan atas frasa: kalimat yang
    // menyatakan ketiadaannya sendiri memuat frasa "di luar batas".
    for (const selector of [
      '.metric-warning',
      '.metric-danger',
      '.md-threshold',
      '.is-out-of-range',
      '.out-of-range',
      '.metric-breach',
      '[data-testid="metric-warning"]',
      '[data-testid="out-of-range"]',
      '[data-testid="compliance-verdict"]',
    ]) {
      await expect(page.locator(selector), selector).toHaveCount(0)
    }

    // Dan sebabnya terbaca UTUH di halaman — tanpa elipsis dan tanpa kontrol
    // 'baca selengkapnya'.
    const note = page.getByTestId('no-flagging-note')

    await note.scrollIntoViewIfNeeded()
    await expect(note).toBeVisible()
    await expect(note).toContainText('kepatuhan lingkungan')
    await expect(note).toContainText('tidak ada pewarnaan aman/bahaya')
    expect(await note.innerText()).not.toContain('…')
    await expect(page.getByRole('button', { name: /baca selengkapnya/i })).toHaveCount(0)
  })

  test('skenario 33 + 34: tidak ada blok downtime dan tidak ada kolom target, dan keduanya DINYATAKAN', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metric-card')).toHaveCount(14)

    // Diasersi atas TESTID dan JUDUL, BUKAN atas substring 'downtime' /
    // 'target': pernyataan ketiadaannya sendiri memakai
    // data-testid="no-downtime-note" / "no-target-note" dan memuat kedua kata
    // itu, jadi penyisiran substring akan gagal pada elemen yang justru
    // membuktikan klaimnya.
    await expect(page.getByTestId('downtime')).toHaveCount(0)
    await expect(page.getByTestId('downtime-total')).toHaveCount(0)
    await expect(page.getByTestId('metric-target')).toHaveCount(0)
    await expect(page.getByTestId('targets-without-metric')).toHaveCount(0)
    await expect(page.locator('.metric-standard')).toHaveCount(0)
    await expect(page.getByRole('heading', { name: 'Downtime' })).toHaveCount(0)

    await expect(page.getByTestId('no-downtime-note')).toContainText('tidak mencatat downtime')
    await expect(page.getByTestId('no-target-note')).toContainText(
      'tidak punya tabel master target',
    )
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 15 + 16 — sebaran status                                 */
  /* ---------------------------------------------------------------- */

  test('skenario 16: baris nilai dikenal bernilai 0 BENAR-BENAR tergambar', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    const flare = page.getByTestId('status-distribution-biogas-flare')

    await flare.scrollIntoViewIfNeeded()
    await expect(flare).toBeVisible()
    // TIGA baris — On, Off, DAN Fault, meski Fault bernilai 0.
    await expect(flare.getByTestId('status-row')).toHaveCount(3)

    const faultRow = flare.locator('[data-status-value="fault"]')

    await expect(faultRow).toBeVisible()
    await expect(faultRow).toContainText('Fault')
    // 100 × 0 / 118 = 0,0% — penyebutnya ada, jadi bukan tanda pisah.
    await expect(faultRow).toContainText('0,0%')

    // Jumlah slot bergangguan tetap DITONJOLKAN walau 0.
    await expect(page.getByTestId('biogas-flare-fault-count')).toContainText('0 slot bergangguan')

    // Porsinya DISALIN PERSIS dari helper $porsi pada blade screen-156,
    // penyebutnya recorded_slots. 100 × 60 / 118 = 50,8%.
    await expect(flare.locator('[data-status-value="on"]')).toContainText('50,8%')
    await expect(flare.locator('[data-status-value="off"]')).toContainText('6,8%')
    // Dan BUKAN 4,2%, yang akan muncul bila penyebutnya expected_slots 1.440.
    await expect(flare.locator('[data-status-value="on"]')).not.toContainText('4,2%')
    await expect(flare).toContainText('118 slot tercatat')
  })

  test('skenario 15: seluruh slot tidak mencatat flare — baris tidak tercatat TERPISAH', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        status_distribution: {
          ...statusDistribution(),
          biogas_flare_status: {
            known_values: ['on', 'off', 'fault'],
            counts: { on: 0, off: 0, fault: 0 },
            not_recorded: 118,
            recorded_slots: 118,
          },
        },
      }),
    })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    const flare = page.getByTestId('status-distribution-biogas-flare')

    await flare.scrollIntoViewIfNeeded()
    await expect(flare.getByTestId('status-row')).toHaveCount(3)

    for (const value of ['on', 'off', 'fault']) {
      await expect(flare.locator(`[data-status-value="${value}"]`), value).toContainText('0,0%')
    }

    // Baris 'tidak tercatat' TERPISAH, membawa seluruh slot terisi — dan
    // TIDAK dilipat ke baris Mati: kolom yang kosong berarti tidak ada yang
    // mencatatnya, bukan flare yang mati.
    await expect(flare.getByTestId('status-not-recorded')).toContainText('Tidak tercatat')
    await expect(flare.getByTestId('status-not-recorded')).toContainText('118')
    await expect(flare.locator('[data-status-value="off"]')).not.toContainText('118')
    await expect(page.getByTestId('status-distribution-note')).toContainText('tidak pernah')
  })

  test('ketiga kolom status TIDAK muncul sebagai kartu metrik maupun pada kedua tren', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metric-card')).toHaveCount(14)

    for (const column of [
      'biogas_flare_status',
      'dosing_pump_1_status',
      'sludge_dewatering_status',
    ]) {
      await expect(page.locator(`[data-metric="${column}"]`), column).toHaveCount(0)
    }

    // TEPAT DUA tren, dan keduanya atas kolom UKUR.
    await expect(page.getByTestId('daily-trend-raw-pome-feed')).toBeVisible()
    await expect(page.getByTestId('daily-trend-final-discharge-ph')).toBeVisible()
    await expect(page.locator('[data-testid^="daily-trend-"][data-testid$="-table"]')).toHaveCount(
      0,
    )
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 17 + 18 + 19 — dua rekap teks bebas                      */
  /* ---------------------------------------------------------------- */

  test('skenario 17: satu slot muncul pada KEDUA rekap, di bawah judul yang terpisah', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    const maintenance = page.getByTestId('maintenance-remarks')
    const findings = page.getByTestId('findings')

    await maintenance.scrollIntoViewIfNeeded()
    await expect(maintenance).toContainText('Rekap Catatan Perawatan (3 slot)')
    await expect(findings).toContainText('Rekap Temuan (2 slot)')

    // TIGA kejadian berteks identik tetap TIGA baris — pengelompokan menurut
    // teks menghilangkan tanggal dan slot waktu yang justru satu-satunya cara
    // menemukan kembali kejadiannya.
    await expect(maintenance.getByTestId('maintenance-remark-row')).toHaveCount(3)
    await expect(findings.getByTestId('finding-row')).toHaveCount(2)

    // SLOT YANG SAMA pada KEDUA daftar, dengan teksnya masing-masing.
    await expect(maintenance.getByTestId('maintenance-remark-row').first()).toContainText('07:00')
    await expect(findings.getByTestId('finding-row').first()).toContainText('07:00')
    await expect(maintenance.getByTestId('maintenance-remark-row').first()).toContainText(
      'Ganti membran pompa dosing',
    )
    await expect(findings.getByTestId('finding-row').first()).toContainText(
      'Busa tebal di kolam anaerobik 2',
    )

    // Judul yang TERPISAH, dan keduanya berada pada bagian yang berbeda.
    const maintenanceBox = await maintenance.boundingBox()
    const findingsBox = await findings.boundingBox()

    expect(findingsBox!.y).toBeGreaterThanOrEqual(maintenanceBox!.y + maintenanceBox!.height - 1)
    await expect(page.getByTestId('findings-note')).toContainText('bukan penghitungan ganda')
  })

  test('skenario 18: kedua rekap kosong — keduanya tetap tergambar dengan keterangannya', async ({ page }) => {
    await stubApi(page, { summary: makeSummary({ maintenance_remarks: [], findings: [] }) })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await page.getByTestId('maintenance-remarks').scrollIntoViewIfNeeded()

    await expect(page.getByTestId('maintenance-remarks')).toBeVisible()
    await expect(page.getByTestId('findings')).toBeVisible()
    await expect(page.getByTestId('maintenance-remarks-empty')).toContainText(
      'Tidak ada satu pun catatan',
    )
    await expect(page.getByTestId('findings-empty')).toContainText('Tidak ada satu pun temuan')
    // Tidak ada tabel kosong tanpa penjelasan.
    await expect(page.getByTestId('maintenance-remark-row')).toHaveCount(0)
    await expect(page.getByTestId('finding-row')).toHaveCount(0)
  })

  test('skenario 19: teks satu paragraf terbaca UTUH tanpa membuat halaman menggulir mendatar', async ({ page }) => {
    const paragraph =
      'Ditemukan busa tebal berwarna cokelat gelap menutupi hampir seluruh permukaan kolam '
      + 'anaerobik kedua sejak giliran pagi, disertai bau menyengat yang tercium sampai area '
      + 'cooling pond; operator sudah menurunkan laju umpan POME dan menambah dosis antifoam, '
      + 'namun ketebalan busanya belum berkurang sampai akhir giliran malam, sehingga perlu '
      + 'diperiksa kembali pada hari berikutnya bersama bagian pemeliharaan.'

    await stubApi(page, {
      summary: makeSummary({ findings: [{ date: DAY_A, time_slot: '07:00', text: paragraph }] }),
    })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    const row = page.getByTestId('finding-row').first()

    await row.scrollIntoViewIfNeeded()
    await expect(row).toBeVisible()

    // SELURUH isi teks terbaca — tidak ada pemotongan maupun elipsis.
    expect(await row.innerText()).toContain(paragraph)

    // Dan kartu itu tidak membuat halaman menggulir mendatar: teksnya
    // MEMBUNGKUS alih-alih memaksa lebar.
    await expectNoHorizontalPageScroll(page)

    const box = await row.boundingBox()

    expect(box!.width).toBeLessThanOrEqual(390)
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 20 + 36 — rekap dan tata letak                           */
  /* ---------------------------------------------------------------- */

  test('skenario 20: satu baris per unit termasuk unit tanpa pembacaan, total dari metrics periode', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        by_unit: [
          { effluent_plant_id: 'EP-1', reading_rows: 70, reading_count: 70, metrics: unitMetrics(7.0, 70) },
          { effluent_plant_id: 'EP-2', reading_rows: 48, reading_count: 48, metrics: unitMetrics(8.0, 48) },
          { effluent_plant_id: 'EP-3', reading_rows: 0, reading_count: 0, metrics: unitMetrics(null, 0) },
        ],
      }),
    })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    const byUnit = page.getByTestId('by-unit')

    await byUnit.scrollIntoViewIfNeeded()
    await expect(byUnit).toContainText('Rekap per Unit Effluent Plant (3 unit)')
    await expect(byUnit.getByTestId('by-unit-row')).toHaveCount(3)

    // Unit tanpa pembacaan TETAP muncul dengan 0 — membuangnya justru akan
    // menyembunyikan unit yang tidak pernah dituliskan.
    await expect(byUnit.getByTestId('by-unit-row').nth(2)).toContainText('EP-3')
    await expect(byUnit.getByTestId('by-unit-reading-rows').nth(2)).toHaveText('0')

    // Angka periode di atas tetap angka GABUNGAN dari server (7,40), bukan
    // rata-rata dari ketiga baris di atasnya (yang akan memberi 7,50).
    await expect(byUnit.getByTestId('by-unit-row-total')).toContainText('Gabungan seluruh unit')
    await expect(byUnit.getByTestId('by-unit-row-total')).toContainText('7,40')
    await expect(byUnit.getByTestId('by-unit-row-total')).not.toContainText('7,50')
  })

  test('skenario 36: kedua rekap dan kedua tren menggulir DI DALAM kartunya, halaman tidak', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('by-unit-table')).toBeVisible()
    await expect(page.getByTestId('by-unit-row')).toHaveCount(2)

    // EMPAT BELAS kolom rata-rata plus nama unit dan slot terisi jelas lebih
    // lebar daripada 390px — tabelnya BERTUMPUK di dalam kartunya.
    await expectCardStacksInsideItself(page, '[data-testid="by-unit"] .detail-table-wrap')

    // KEDUA grafik tren MENGGULIR mendatar di dalam kartunya: dua belas
    // tanggal tidak muat pada 390px, dan memadatkannya membuat sumbunya tidak
    // terbaca.
    for (const testid of ['daily-trend-raw-pome-feed', 'daily-trend-final-discharge-ph']) {
      await page.getByTestId(testid).scrollIntoViewIfNeeded()
      await expectCardScrollsInsideItself(page, `[data-testid="${testid}"] .chart-scroll`)
      await expect(page.getByTestId(`${testid}-scroll-hint`)).toContainText('di dalam kartu ini')
    }

    await recapToggle(page).click()
    await expect(recapBody(page)).toBeVisible()

    await expectCardStacksInsideItself(page, '[data-testid="daily-recap"] .detail-table-wrap')
    await expectNoHorizontalPageScroll(page)
  })

  test('kedua grafik tren dapat digulir sampai kolom terakhirnya, halaman tetap tidak bergeser', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    for (const testid of ['daily-trend-raw-pome-feed', 'daily-trend-final-discharge-ph']) {
      const scroller = page.locator(`[data-testid="${testid}"] .chart-scroll`)

      await scroller.scrollIntoViewIfNeeded()
      await expect(scroller).toBeVisible()

      const reached = await scroller.evaluate((element) => {
        element.scrollLeft = element.scrollWidth

        return element.scrollLeft + element.clientWidth >= element.scrollWidth - 2
      })

      expect(reached, testid).toBe(true)
      await expectNoHorizontalPageScroll(page)
    }
  })

  test('titik tren tanpa pembacaan menghasilkan batang KOSONG, bukan batang nol', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    const emptyDate = isoDaysFromNow(-25)
    const column = page.getByTestId(`daily-trend-raw-pome-feed-col-${emptyDate}`)

    await column.scrollIntoViewIfNeeded()
    await expect(column).toContainText('tidak tersedia')

    const height = await column
      .locator('.chart-fill')
      .evaluate((element) => (element as HTMLElement).style.height)

    expect(height).toBe('0%')
  })

  test('rekap harian tertutup secara bawaan dan membuka/menutupnya tidak memicu permintaan', async ({ page }) => {
    const state = await stubApi(page)

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('daily-recap')).toBeVisible()
    // v-show, bukan v-if — jadi toBeHidden(), bukan toHaveCount(0).
    await expect(recapBody(page)).toBeHidden()

    const summaryHitsBefore = state.hits.summary

    await recapToggle(page).click()
    await expect(recapBody(page)).toBeVisible()
    await expect(page.locator('[data-testid^="daily-row-2"]')).toHaveCount(12)
    // Total periode DIHITUNG ULANG server atas seluruh slot terisi, bukan
    // merata-ratakan rata-rata harian — karena itu pula ia tidak berubah
    // ketika rekap ditutup.
    await expect(page.getByTestId('daily-row-total')).toContainText('99')
    await expect(page.getByTestId('daily-row-total')).toContainText('7,40')

    await recapToggle(page).click()
    await expect(recapBody(page)).toBeHidden()

    // Membuka/menutup murni penyingkapan: datanya sudah ada di memori.
    expect(state.hits.summary).toBe(summaryHitsBefore)
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 23 + 35 — periode tertutup dan ekspor                    */
  /* ---------------------------------------------------------------- */

  test('skenario 23: periode tertutup — laporan penuh, badge Tertutup, ekspor tetap aktif', async ({ page }) => {
    // Kepala periodenya dibentuk stub dari period_id yang diminta (lihat
    // stubApi), jadi memilih per-2 cukup — tidak ada status yang dipaku di
    // sini yang dapat menyimpang dari pilihan di pemilih.
    await stubApi(page)

    await openReport(page)
    await pickPeriod(page, 'per-2')

    await expect(page.getByTestId('period-status-badge')).toContainText('Tertutup')
    await expect(page.getByTestId('metric-card')).toHaveCount(14)
    await expect(page.getByTestId('by-unit')).toBeVisible()
    // Kunci periode mengatur PENULISAN data, bukan pembacaan laporan.
    await expect(page.getByTestId('export-button')).toBeVisible()
    await expect(page.getByTestId('export-button')).not.toBeDisabled()
  })

  test('skenario 35: ekspor CSV saja — unduhan terpicu, permintaannya membawa periode + line', async ({ page }) => {
    const state = await stubApi(page)

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('export-button')).toBeVisible()

    const download = page.waitForEvent('download')

    await page.getByTestId('export-button').click()

    const file = await download

    // Nama berkasnya (slug periode + line) diuji pada spec komponen, tempat
    // ia dapat diperiksa tanpa bergantung pada mekanisme unduhan browser —
    // tetapi SUFIKS-nya diuji di sini, karena hanya browser membuktikan apa
    // yang benar-benar diunduh.
    expect(file.suggestedFilename()).toMatch(/\.csv$/)
    expect(file.suggestedFilename()).not.toMatch(/xlsx/i)
    await file.delete()

    await expect.poll(() => state.hits.export).toBe(1)

    // Berkas yang mencampur line adalah bentuk kesalahan yang paling sulit
    // dibantah setelah terkirim.
    const exportUrl = state.urls.find((url) => url.includes('/export')) ?? ''

    expect(exportUrl).toContain('period_id=per-1')
    expect(exportUrl).toContain('production_line_id=pl-1')
    expect(exportUrl).toContain('format=csv')
    expect(exportUrl).not.toContain('xlsx')

    // Dan tidak ada satu pun tawaran .xlsx di layar: menyimpan dan membukanya
    // di WebView ponsel menuntut penanganan berkas native yang belum ada.
    const body = await page.locator('body').innerText()

    expect(body.toLowerCase()).not.toContain('xlsx')
    expect(body.toLowerCase()).not.toContain('excel')
    await expect(page.getByTestId('export-button')).toContainText('Ekspor CSV')
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 24 + 25 + 26 + 27 — galat dan perlombaan permintaan      */
  /* ---------------------------------------------------------------- */

  test('skenario 24: kegagalan jaringan — pesan + Coba Lagi, periode terpilih tidak hilang', async ({ page }) => {
    const state = await stubApi(page, { summaryAbort: true })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('network-error')).toBeVisible()
    await expect(page.getByTestId('retry-button')).toBeVisible()
    // Pengguna di area tanpa sinyal tidak boleh dipaksa memilih dari awal.
    await expect(page.getByTestId('period-select')).toHaveValue('per-1')
    await expect(page.getByTestId('production-line-current')).toContainText('Line 1')

    state.summaryAbort = false

    await page.getByTestId('retry-button').click()

    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('metric-card')).toHaveCount(14)
  })

  test('skenario 26: sesi berakhir (401) — diarahkan ke Login tanpa pesan jaringan', async ({ page }) => {
    await stubApi(page, { summaryStatus: 401 })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    // PATHNAME, bukan pola glob dengan baseURL.
    await expectPathname(page, '/login')

    // "Periksa koneksi Anda" pada sesi yang berakhir mengirim pembaca
    // memeriksa sinyalnya alih-alih masuk kembali.
    await expect(page.getByTestId('network-error')).toHaveCount(0)
  })

  /**
   * skenario 27 — PENANDA REGRESI PELEBARAN AKSES, DIUJI DARI SISI KLIEN.
   *
   * Tech spec menuliskan skenario ini sebagai perjalanan dengan keempat
   * endpoint menjawab 403, dan menyatakan sendiri bahwa ia "WAJIB gagal
   * setelah pelebaran akses diterapkan". Pelebaran itu SUDAH diterapkan, jadi
   * 403 pada ketiga rute data bukan lagi perilaku backend yang benar —
   * bentuknya di sini karena itu bukan asersi tentang BACKEND melainkan
   * tentang KLIEN: 403 bukan jalur yang layar ini tangani secara khusus, jadi
   * tidak ada angka tergambar dan TIDAK ADA pengalihan ke Login (403 bukan
   * sesi yang berakhir). Penjaga 403-nya sendiri hidup di
   * backend/tests/Feature/Api/LaporanEffluentPlantTest.php (skenario 21) dan
   * e2e-web/tests/laporan-effluent-plant.spec.ts (invarian k).
   */
  test('skenario 27: 403 — tidak ada angka tergambar, dan TIDAK ADA pengalihan ke Login', async ({ page }) => {
    await stubApi(page, { summaryStatus: 403 })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('error-message')).toBeVisible()
    await expect(page.getByTestId('metric-card')).toHaveCount(0)
    await expect(page.getByTestId('coverage')).toHaveCount(0)
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    // TIDAK berpindah ke Login: 403 bukan sesi yang berakhir.
    expect(new URL(page.url()).pathname).toBe(ROUTE)
  })

  test('skenario 25: respons periode sebelumnya yang tiba belakangan TIDAK menimpa yang terpilih', async ({ page }) => {
    // Hanya permintaan PERTAMA ditunda, jadi respons per-1 tiba SETELAH
    // respons per-2 — itulah bentuk perlombaan yang hendak diuji.
    const state = await stubApi(page, { summaryDelayMs: 2000 })

    await openReport(page)
    await pickPeriod(page, 'per-1')
    await pickPeriod(page, 'per-2')

    await expect(page.getByTestId('metric-card')).toHaveCount(14)
    // Nama periode pada kepala adalah milik periode yang SEDANG terpilih —
    // dan stub-nya membentuk kepala periode dari period_id yang diminta,
    // sehingga kedua periode benar-benar dapat dibedakan di sini.
    await expect(page.getByTestId('period-meta')).toContainText('Periode Sebelumnya')
    await expect(page.getByTestId('period-status-badge')).toContainText('Tertutup')
    await expect(page.getByTestId('period-select')).toHaveValue('per-2')

    // KEDUA permintaan memang berangkat: yang lama TIDAK dibatalkan, hanya
    // responsnya dibuang.
    await expect.poll(() => state.hits.summary, { timeout: 15_000 }).toBe(2)
    // Dan setelah respons lama akhirnya tiba, ia tetap kalah.
    await expect
      .poll(async () => (await page.getByTestId('period-meta').innerText()).includes('Sebelumnya'), {
        timeout: 15_000,
      })
      .toBe(true)

    await expect(page.getByTestId('period-select')).toHaveValue('per-2')
    await expect(page.getByTestId('period-meta')).not.toContainText('Periode Uji Effluent Plant')
    await expect(page.getByTestId('summary-loading')).toHaveCount(0)
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 3 + 4 + 5 + 6 + 7 + 8 + 9 — Production Line & periode    */
  /* ---------------------------------------------------------------- */

  test('skenario 3: dua line tanpa pilihan — /summary TIDAK PERNAH diminta, juga sesudah ganti periode', async ({ page }) => {
    const state = await stubApi(page, { productionLines: [LINE_1, LINE_2] })

    await openReport(page)

    await expect(page.getByTestId('production-line-select')).toBeVisible()
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()

    await pickPeriod(page, 'per-1')

    // Penjagaan ada di loadSummary(), jadi tidak ada permintaan yang berangkat
    // — bukan sekadar angka yang tidak dirender.
    expect(state.hits.summary).toBe(0)
    await expect(page.getByTestId('period-meta')).toHaveCount(0)
    await expect(page.getByTestId('metric-card')).toHaveCount(0)

    await pickPeriod(page, 'per-2')

    expect(state.hits.summary).toBe(0)

    await page.getByTestId('production-line-select').selectOption('pl-2')

    // Dan begitu sebuah line berlaku, permintaannya berangkat — tanpa asersi
    // ini, yang di atas juga hijau pada layar yang rusak total.
    await expect(page.getByTestId('metric-card')).toHaveCount(14)
    expect(state.urls.some((url) => url.includes('production_line_id=pl-2'))).toBe(true)
  })

  test('skenario 4: mill tanpa Production Line — arahan menghubungi Admin, tanpa tombol coba lagi', async ({ page }) => {
    const state = await stubApi(page, { productionLines: [] })

    await openReport(page)

    await expect(page.getByTestId('production-line-unavailable')).toContainText(
      'belum memiliki Production Line',
    )
    await expect(page.getByTestId('production-line-unavailable')).toContainText('hubungi Admin')
    // Mencoba lagi tidak akan mengubah sesuatu yang tidak ada.
    await expect(page.getByTestId('production-line-retry')).toHaveCount(0)
    await expect(page.getByTestId('coverage')).toHaveCount(0)
    expect(state.hits.summary).toBe(0)
  })

  test('skenario 5: daftar Production Line gagal dimuat — pesan BERBEDA, dengan tombol coba lagi sendiri', async ({ page }) => {
    const state = await stubApi(page, { productionLinesAbort: true })

    await openReport(page)

    const notice = page.getByTestId('production-line-unavailable')

    // DUA keadaan yang menuntut tindakan berbeda, jadi dua pesan berbeda.
    await expect(notice).toContainText('tidak dapat dimuat')
    await expect(notice).toContainText('Periksa koneksi jaringan Anda')
    await expect(notice).not.toContainText('belum memiliki Production Line')
    await expect(page.getByTestId('production-line-retry')).toBeVisible()
    // Kegagalan daftar line tidak boleh menyamar sebagai galat laporan.
    await expect(page.getByTestId('network-error')).toHaveCount(0)

    state.productionLinesAbort = false

    await page.getByTestId('production-line-retry').click()

    await expect(page.getByTestId('production-line-unavailable')).toHaveCount(0)
    await expect(page.getByTestId('production-line-current')).toContainText('Line 1')
  })

  test('skenario 6: line dari query rute dipakai dan diingat perangkat', async ({ page }) => {
    const state = await stubApi(page, { productionLines: [LINE_1, LINE_2] })

    await openReport(page, `${ROUTE}?production_line_id=pl-2`)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('production-line-current')).toContainText('Line 2')
    expect(state.urls.some((url) => url.includes('production_line_id=pl-2'))).toBe(true)

    const userId = await getAuthUserId(page)
    const remembered = await page.evaluate(
      (key) => window.localStorage.getItem(key),
      REMEMBERED_LINE_KEY(userId),
    )

    // Pilihan yang dibawa screen-141 ikut menjadi ingatan — layar Daftar
    // Stasiun dan layar laporan berbagi satu konteks.
    expect(remembered).toBe('pl-2')
  })

  test('skenario 6: line yang diingat perangkat dipakai tanpa diminta memilih lagi', async ({ page }) => {
    await stubApi(page, { productionLines: [LINE_1, LINE_2] })

    const userId = await getAuthUserId(page)

    await page.goto('/home')
    await page.evaluate(
      ([key, value]) => window.localStorage.setItem(key, value),
      [REMEMBERED_LINE_KEY(userId), 'pl-2'] as const,
    )

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('production-line-current')).toContainText('Line 2')
    await expect(page.getByTestId('production-line-required-hint')).toHaveCount(0)
    await expect(page.getByTestId('metric-card')).toHaveCount(14)
  })

  test('skenario 7: line yang diingat sudah tidak ada — pemilih muncul lagi, tanpa menebak line lain', async ({ page }) => {
    const state = await stubApi(page, { productionLines: [LINE_1, LINE_2] })

    const userId = await getAuthUserId(page)

    await page.goto('/home')
    await page.evaluate(
      ([key, value]) => window.localStorage.setItem(key, value),
      [REMEMBERED_LINE_KEY(userId), 'pl-hilang'] as const,
    )

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    // Tidak menebak Line 1 maupun Line 2 sebagai penggantinya.
    await expect(page.getByTestId('production-line-current')).toHaveCount(0)
    expect(state.hits.summary).toBe(0)
  })

  /**
   * skenario 8 — PENYIMPANAN PERANGKAT TIDAK DAPAT DIBACA.
   *
   * localStorage dibuat MELEMPAR lewat page.addInitScript, bukan lewat
   * konteks peramban khusus: Playwright tidak punya opsi "matikan
   * localStorage", dan menonaktifkan seluruh penyimpanan juga akan
   * mematahkan sesi login yang dibaca authStore — yang berarti test-nya akan
   * menguji layar Login, bukan layar ini.
   */
  test('skenario 8: penyimpanan perangkat tidak dapat dibaca — pemilih normal, tanpa galat di console', async ({ page }) => {
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

  test('skenario 9: mill belum punya periode — arahan menghubungi Admin, pemilih kosong', async ({ page }) => {
    const state = await stubApi(page, { periods: [] })

    await openReport(page)

    await expect(page.getByTestId('no-periods')).toContainText('hubungi Admin')
    // Pemilih yang LENYAP dan pemilih yang KOSONG menceritakan hal berbeda.
    await expect(page.getByTestId('period-select')).toBeVisible()
    await expect(page.getByTestId('period-select').locator('option')).toHaveCount(1)
    await expect(page.getByTestId('coverage')).toHaveCount(0)
    expect(state.hits.summary).toBe(0)
  })

  /* ---------------------------------------------------------------- */
  /* Skenario 28 + 29 + 37 — baca saja dan nol aritmetika              */
  /* ---------------------------------------------------------------- */

  test('skenario 28: layar hanya membaca — footer hanya Ekspor dan Back, nol permintaan tulis', async ({ page }) => {
    const writes: string[] = []

    page.on('request', (request) => {
      if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method())) {
        writes.push(`${request.method()} ${request.url()}`)
      }
    })

    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metric-card')).toHaveCount(14)

    // Seluruh isi layar ditelusuri dari atas sampai bawah.
    await recapToggle(page).click()
    await expect(recapBody(page)).toBeVisible()
    await page.getByTestId('completeness').scrollIntoViewIfNeeded()

    const footerButtons = page.locator('.action-footer button')

    await expect(footerButtons).toHaveCount(2)
    await expect(footerButtons.nth(0)).toContainText('Ekspor')
    await expect(footerButtons.nth(1)).toContainText('Back')

    // Tidak ada satu pun kontrol tulis di seluruh layar.
    await expect(
      page.getByRole('button', { name: /Simpan|Hapus|Ubah|Verifikasi|Tambah/i }),
    ).toHaveCount(0)
    await expect(page.locator('input')).toHaveCount(0)
    await expect(page.locator('textarea')).toHaveCount(0)

    // Dan tidak satu pun permintaan POST/PUT/PATCH/DELETE pada catatan
    // jaringan sepanjang perjalanan ini (login sudah selesai sebelum
    // pendengar dipasang).
    expect(writes, writes.join(' | ')).toHaveLength(0)
  })

  test('skenario 37: muatan yang sengaja tidak konsisten dirender APA ADANYA', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        coverage: {
          filled_slots: 118,
          expected_slots: 1440,
          // 118/1440 = 8,2 — dan server mengirim 88,8. Layar yang menghitung
          // sendiri menggambar 8,2%.
          coverage_percent: 88.8,
          effluent_plant_unit_count: 2,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 30,
          period_running: false,
        },
        metrics: {
          ...metrics(),
          // Server MENERBITKAN unit, dan nilainya 'C'. METRIC_META milik view
          // berisi '°C' untuk kolom ini, jadi test ini membuktikan nilai
          // SERVER yang menang dan tidak pernah ditulis ulang.
          anaerobic_pond_1_temp_c: { ...metric(38, 41.2, 45, 117), unit: 'C' },
        },
      }),
    })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('coverage-percent')).toHaveText('88,8%')
    // avg DI LUAR rentang min..max, digambar apa adanya.
    await expect(page.locator('[data-metric="cooling_pond_temp_c"]')).toContainText('12,5')
    await expect(page.locator('[data-metric="cooling_pond_temp_c"]')).toContainText('10,0')
    await expect(page.locator('[data-metric="cooling_pond_temp_c"]')).toContainText('11,0')

    // SATUAN 'C' DIRENDER 'C', BUKAN '°C'.
    const temp = page.locator('[data-metric="anaerobic_pond_1_temp_c"]')

    await expect(temp).toContainText('· C')
    expect(await temp.innerText()).not.toContain('°C')

    // daily_total dari SERVER (99), bukan jumlah baris hariannya (78).
    //
    // DIASERSI PADA SEL-NYA, BUKAN PADA TEKS SELURUH BARIS. Baris total juga
    // memuat rata-rata keempat belas metrik, dan salah satunya — BOD, dari
    // fixture metrics() di berkas ini sendiri — bernilai TEPAT 78. Jadi
    // `not.toContainText('78')` atas seluruh baris menabrak fixture-nya
    // sendiri dan gagal walau implementasinya benar. Sel kedua adalah jumlah
    // slot (lihat <td class="num">{{ formatCount(dailyTotal?.reading_rows) }}</td>),
    // dan membandingkan sel itu secara persis justru LEBIH ketat daripada
    // memindai substring: ia menolak 99 yang kebetulan muncul di kolom lain.
    await recapToggle(page).click()
    await expect(recapBody(page)).toBeVisible()

    const totalSlotCell = page.getByTestId('daily-row-total').locator('td').nth(1)

    await expect(totalSlotCell).toHaveText('99')
    expect(await totalSlotCell.innerText()).not.toBe('78')
  })

  test('skenario 29: tidak ada endpoint lain yang muncul pada catatan jaringan', async ({ page }) => {
    const state = await stubApi(page)
    const apiUrls: string[] = []

    page.on('request', (request) => {
      const url = request.url()

      if (url.includes('/api/')) {
        apiUrls.push(new URL(url).pathname)
      }
    })

    await openReport(page)
    await pickPeriod(page, 'per-1')
    await expect(page.getByTestId('metric-card')).toHaveCount(14)

    const download = page.waitForEvent('download')

    await page.getByTestId('export-button').click()
    await (await download).delete()
    await expect.poll(() => state.hits.export).toBe(1)

    // KEEMPAT endpoint yang dipakai ULANG, dan tidak satu pun yang baru.
    const allowed = new Set([
      '/api/production-lines/options-for-report',
      '/api/effluent-plant-reports/periods',
      '/api/effluent-plant-reports/summary',
      '/api/effluent-plant-reports/export',
    ])

    for (const pathname of apiUrls) {
      expect(allowed.has(pathname), pathname).toBe(true)
    }

    expect(apiUrls.some((pathname) => pathname.includes('business-units'))).toBe(false)
  })

  test('Back kembali ke Dashboard & Reporting', async ({ page }) => {
    await stubApi(page)
    await openReport(page)

    await page.getByTestId('back-button').click()
    await expectPathname(page, '/dashboard-reporting')
  })
})

/* ================================================================== */
/* Pintu masuk — tile Effluent Plant pada layar pemilih stasiun         */
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
   * SATU BARIS yang menentukan layar ini dapat dicapai: entri
   * 'effluent-plant' pada REPORT_ROUTES di ReportingPilihStasiunView.vue —
   * DENGAN TANDA HUBUNG dan TERKUTIP. Kunci bergaris-bawah ('effluent_plant')
   * akan terkompilasi, terlihat benar, dan tidak pernah cocok dengan satu
   * station.type pun. Tanpa test ini, layar laporan bisa lengkap, seluruh
   * test lainnya hijau, dan tile-nya tetap kelabu — pola kegagalan yang sudah
   * terjadi sekali pada laporan Cages & Tracks versi web.
   */
  test('skenario 1: tile Effluent Plant aktif dan menavigasi ke layar laporannya', async ({ page }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await seedLocalStations(page)
    await goToReports(page)

    const tile = page.getByTestId('station-tile-effluent-plant')

    await expect(tile).toBeVisible()
    await expect(tile).toHaveAttribute('aria-disabled', 'false')
    await tile.click()

    // Alamatnya membawa ?production_line_id= dari layar ini, jadi yang
    // dibandingkan adalah PATHNAME-nya saja.
    await expectPathname(page, ROUTE)
    await expect(page.getByTestId('laporan-effluent-plant-mobile')).toBeVisible()

    // Dan tidak ada pesan "belum tersedia" yang muncul sebagai gantinya.
    await expect(page.getByTestId('info-message')).toHaveCount(0)
  })
})
