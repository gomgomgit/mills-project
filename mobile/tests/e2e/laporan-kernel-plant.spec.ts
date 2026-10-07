import { expect, test, type Page } from '@playwright/test'
import { getAuthUserId, login, USERS } from './helpers'

/**
 * laporan-kernel-plant.spec.ts — screen-155--laporan-kernel-plant-mobile /
 * usecase-161--laporan-kernel-plant-mobile.
 *
 * Satu test per test_scenarios yang punya browser_test. Berjalan di Vite dev
 * server (playwright.config.ts, baseURL http://localhost:5174): aplikasi
 * Capacitor adalah SPA biasa sebelum dibungkus native, jadi browser test layar
 * mobile TIDAK ditangguhkan sebagai "mobile-only".
 *
 * VIEWPORT PONSEL, BUKAN DESKTOP. Berkas ini menimpa viewport bawaan proyek
 * chromium (Desktop Chrome, 1280px) dengan 390x844. Separuh klaim layar ini
 * adalah klaim tata letak ("satu kolom", "tanpa gulir mendatar pada halaman",
 * "sasaran sentuh minimal 44px"), dan seluruhnya hampa pada kanvas 1280px:
 * apa pun muat di sana.
 *
 * MASUK LEWAT RUTE LANGSUNG, KECUALI SATU TEST. Pintu masuk UI-nya
 * (screen-141, Reporting: pilih stasiun) punya test-nya sendiri, tetapi SATU
 * test di bawah sengaja melewati tile Kernel Plant dari sana — entri
 * 'kernel-plant' pada REPORT_ROUTES di ReportingPilihStasiunView.vue adalah
 * satu-satunya penentu tile itu hidup atau mati, dan tanpa test itu layar ini
 * bisa lengkap, hijau, dan tetap tidak dapat dicapai siapa pun.
 *
 * RESPONS API DI-STUB DI TINGKAT JARINGAN (page.route), BUKAN DISEMAI KE
 * DATABASE — keputusan dan alasan yang sama dengan kesepuluh laporan mobile
 * lain:
 *   - Layar ini read-only total dan tidak punya satu pun UI untuk membuat data
 *     yang dibacanya.
 *   - Kebenaran ANGKA-nya bukan tanggung jawab layar ini: seluruhnya dari
 *     KernelPlantReportService, yang sudah diuji penuh di backend dan di
 *     e2e-web terhadap data sungguhan. Yang khas mobile — dan hanya dapat
 *     dibuktikan di sini — adalah PEMETAAN respons itu ke layar satu kolom.
 *   - Beberapa skenario mustahil dibuat dari data sungguhan tanpa merusak
 *     lingkungan (sesi kedaluwarsa, jaringan putus, mill tanpa Production
 *     Line).
 *
 * ── EMPAT HAL YANG HANYA BROWSER DAPAT MEMBUKTIKAN DI LAYAR INI ─────────
 *
 * 1. CAKUPAN BERADA DI ATAS KARTU PARAMETER — diukur dari POSISI KOTAK DI
 *    VIEWPORT, bukan dari urutan sumbernya. Di layar sempit pembaca melihat
 *    lebih sedikit sekaligus, jadi apa yang dilihat lebih dulu menentukan
 *    lebih banyak.
 *
 * 2. PENYEBUT TIAP KARTU BENAR-BENAR TERCETAK pada kanvas 390px, dan N-nya
 *    berbeda-beda sementara M seragam. Inilah yang paling mudah hilang karena
 *    dipotong demi ruang, dan tanpanya rata-rata sebuah parameter terbaca
 *    sebagai angka seluruh periode.
 *
 * 3. KEEMPAT KARTU PASANGAN MEMBAWA KETERANGAN STANDAR-BERSAMA. Di layar
 *    sempit keempatnya bertumpuk berurutan, sehingga target yang identik
 *    muncul dua kali beruntun lalu dua kali lagi — terbaca sebagai data
 *    terduplikasi lebih kuat lagi daripada di tabel web. DUA pasangan, bukan
 *    satu: markup yang mengistimewakan satu pasangan lolos di laporan
 *    Depricarping mobile dan salah di sini.
 *
 * 4. TABEL REKAP MENGGULIR DI DALAM KARTUNYA, bukan menggeser halaman. Tujuh
 *    kolom rata-rata plus tanggal jelas lebih lebar daripada 390px.
 *
 * ── KONVENSI YANG HARGANYA SUDAH DIBAYAR ───────────────────────────────
 *
 * KETIADAAN PENANDAAN DI LUAR BATAS DISISIR ATAS NAMA KELAS (jumlah elemen),
 * bukan atas frasa: kalimat yang menyatakan ketiadaannya sendiri memuat frasa
 * "di luar batas".
 *
 * ASERSI PERSEN DILINGKUP KE ELEMENNYA. Standar master Kernel Plant memuat
 * '≤ 7.0%', '≤ 6.0%' dan '≤ 1.5%' dan dirender VERBATIM, jadi asersi persen
 * tingkat halaman tidak bermakna di sini.
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
 * webServer, reuseExistingServer).
 */

const ROUTE = '/reports/kernel-plant'

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
  name: 'Periode Uji Kernel Plant',
  start_date: PERIOD_START,
  end_date: PERIOD_END,
  status: 'open',
  // DENGAN TANDA HUBUNG. Garis bawah di situ tidak cocok dengan satu baris
  // period_stations pun, dan server menjawab [] tanpa galat.
  station_type: 'kernel-plant',
  station_type_label: 'Kernel Plant',
}

const PERIOD_CLOSED = {
  id: 'per-2',
  name: 'Periode Sebelumnya',
  start_date: PREV_START,
  end_date: PREV_END,
  status: 'closed',
  station_type: 'kernel-plant',
  station_type_label: 'Kernel Plant',
}

const LINE_1 = { id: 'pl-1', name: 'Line 1', code: 'L1' }
const LINE_2 = { id: 'pl-2', name: 'Line 2', code: 'L2' }

/** Kunci ingatan line, ditulis StationListView.vue dan dibaca layar ini. */
const REMEMBERED_LINE_KEY = (userId: string) => `msl_production_line_${userId}`

const METRIC_COLUMNS = [
  'ripple_mill_1_amps',
  'ripple_mill_2_amps',
  'claybath_hydro_sg',
  'kernel_silo_1_temp_c',
  'kernel_silo_2_temp_c',
  'kernel_moisture_percent',
  'shell_loss_percent',
]

function averages(values: Record<string, number | null> = {}): Record<string, number | null> {
  return Object.fromEntries(METRIC_COLUMNS.map((column) => [column, values[column] ?? null]))
}

const RIPPLE_TARGET = {
  equipment_parameter: 'Ripple Mill (Cracker)',
  // DUA angka, DUA satuan, arah pembanding BERLAWANAN, dalam satu sel.
  target_benchmark: '20 - 25 Amps (Nut Breakage >95%)',
  corrective_action_plan: 'Adjust rotor-vane clearance if uncracked nut rate >5%.',
}

const SILO_TARGET = {
  equipment_parameter: 'Kernel Silo 1 & 2',
  // Menyebut ZONA yang tidak punya kolom sama sekali.
  target_benchmark: '70°C - 80°C (Top/Middle zones)',
  corrective_action_plan: 'Check heater elements/steam valves if temperature drops below 65°C.',
}

/**
 * KETUJUH kartu parameter — sama banyak dengan laporan Depricarping mobile,
 * dua lebih banyak daripada Threshing dan Pressing.
 *
 * Penyebutnya SENGAJA berbeda-beda (2, 9, 0, 5, 3, 7, 1) atas M = 12, supaya
 * asersi "tiap kartu mencetak penyebutnya sendiri" bermakna alih-alih selalu
 * hijau — dengan penyebut yang seragam, satu penyebut bersama akan lolos tanpa
 * terlihat. M sengaja LEBIH BESAR daripada setiap N: slot yang hanya memuat
 * temuan tetap slot terisi.
 *
 * DUA PASANGAN berbagi satu baris master, dan angka tiap pasangan sengaja
 * berjauhan (12,5 vs 33; 74 vs 58) supaya asersi "tidak dirata-ratakan" punya
 * angka yang jelas untuk ditolak (22,75 dan 66).
 *
 * claybath_hydro_sg adalah kolom yang TIDAK PERNAH TERISI sekaligus yang
 * masternya TERLEPAS — kartunya tetap harus dirender, dengan pernyataan alih-
 * alih sel kosong.
 */
function metrics(): Array<Record<string, unknown>> {
  return [
    {
      column: 'ripple_mill_1_amps',
      label: 'Arus Ripple Mill 1',
      unit: 'Amps',
      min: 10,
      // Di luar rentang min..max — mustahil dari perhitungan apa pun, dan
      // karena itu bukti bahwa angkanya diteruskan apa adanya.
      avg: 12.5,
      max: 11,
      filled_slot_count: 2,
      target: { ...RIPPLE_TARGET, shares_standard_with: ['ripple_mill_2_amps'] },
    },
    {
      column: 'ripple_mill_2_amps',
      label: 'Arus Ripple Mill 2',
      unit: 'Amps',
      min: 30,
      // 33 Amps jelas di luar '20 - 25 Amps', dan TETAP tidak ditandai.
      avg: 33,
      max: 36,
      filled_slot_count: 9,
      target: { ...RIPPLE_TARGET, shares_standard_with: ['ripple_mill_1_amps'] },
    },
    {
      column: 'claybath_hydro_sg',
      label: 'Claybath / Hydrocyclone',
      unit: 'SG',
      min: null,
      avg: null,
      max: null,
      filled_slot_count: 0,
      target: {
        equipment_parameter: null,
        target_benchmark: null,
        corrective_action_plan: null,
        shares_standard_with: [],
      },
    },
    {
      column: 'kernel_silo_1_temp_c',
      label: 'Suhu Kernel Silo 1',
      // 'C' APA ADANYA dari server — tidak dipercantik menjadi '°C' di klien,
      // karena laporan web mencetak medan yang sama apa adanya.
      unit: 'C',
      min: 70,
      avg: 74,
      max: 80,
      filled_slot_count: 5,
      target: { ...SILO_TARGET, shares_standard_with: ['kernel_silo_2_temp_c'] },
    },
    {
      column: 'kernel_silo_2_temp_c',
      label: 'Suhu Kernel Silo 2',
      unit: 'C',
      min: 55,
      avg: 58,
      max: 62,
      filled_slot_count: 3,
      target: { ...SILO_TARGET, shares_standard_with: ['kernel_silo_1_temp_c'] },
    },
    {
      column: 'kernel_moisture_percent',
      label: 'Kadar Air Kernel',
      unit: '%',
      min: 6.2,
      avg: 6.8,
      max: 7.4,
      filled_slot_count: 7,
      target: {
        equipment_parameter: 'Final Kernel Moisture',
        // Mencampur BATAS dengan ALASAN batas itu ada — dan memuat '%' di
        // dalam standarnya, sebab mengapa asersi persen harus dilingkup.
        target_benchmark: '≤ 7.0% (Prevents mold growth)',
        corrective_action_plan: 'Increase retention time or adjust silo air flow rates.',
        shares_standard_with: [],
      },
    },
    {
      column: 'shell_loss_percent',
      label: 'Shell Bin Kernel Loss',
      unit: '%',
      min: 1.1,
      avg: 1.3,
      max: 1.9,
      filled_slot_count: 1,
      target: {
        equipment_parameter: 'Shell Bin Kernel Loss',
        target_benchmark: '≤ 1.5% (Maximized separation recovery)',
        corrective_action_plan: 'Reduce air velocity or inspect separator screen meshes.',
        shares_standard_with: [],
      },
    },
  ]
}

function makeSummary(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Utara' },
    production_line: { id: 'pl-1', name: 'Line 1' },
    period: {
      id: 'per-1',
      name: 'Periode Uji Kernel Plant',
      start_date: PERIOD_START,
      end_date: PERIOD_END,
      status: 'open',
      // Layar SENGAJA tidak membaca medan ini — satu sumber nama mill saja.
      business_unit_name: 'Mill Dari Kepala Periode',
    },
    has_data: true,
    coverage: {
      filled_slots: 12,
      expected_slots: 288,
      // 12/288 = 4,2 — layar yang menghitung sendiri menjawab 4,2 dan gagal.
      coverage_percent: 41.7,
      kernel_plant_count: 2,
      slots_per_kernel_plant_per_day: 24,
      days_in_period: 30,
      days_counted: 6,
      period_running: true,
    },
    metrics: metrics(),
    /**
     * NORMALNYA TEPAT SATU BARIS, dan itulah keadaan TENANG layar ini —
     * berlawanan dengan laporan Depricarping, yang daftarnya normalnya kosong.
     * 'Final Kernel Dirt' tidak punya kolom kadar kotoran DI MANA PUN pada
     * skema ini.
     */
    targets_without_metric: [
      {
        equipment_parameter: 'Final Kernel Dirt',
        target_benchmark: '≤ 6.0% (Standard quality premium)',
        corrective_action_plan: 'Clean winnowing ducts or re-calibrate hydrocyclone settings.',
        reason: 'no_column',
      },
    ],
    targets_master_empty: false,
    all_targets_measured: false,
    by_kernel_plant: [
      {
        // KEDUANYA string biasa dan BERNILAI SAMA — tidak ada tabel master
        // kernel_plants; id itu teks yang diketik di layar input.
        kernel_plant_id: 'KP-1',
        kernel_plant_name: 'KP-1',
        day_count: 2,
        filled_slot_count: 7,
        downtime_minutes: 30,
        averages: averages({ ripple_mill_1_amps: 30 }),
      },
      {
        kernel_plant_id: 'KP-2',
        kernel_plant_name: 'KP-2',
        day_count: 1,
        filled_slot_count: 5,
        // null, BUKAN 0 — unit yang tidak dicatat bukan unit yang tidak
        // pernah berhenti.
        downtime_minutes: null,
        averages: averages({ ripple_mill_1_amps: 28.5, kernel_silo_1_temp_c: 72 }),
      },
    ],
    // Dua belas hari: cukup panjang untuk membuktikan rekap harian memang
    // menggulir di dalam kartunya sendiri.
    daily: Array.from({ length: 12 }, (_, index) => ({
      date: isoDaysFromNow(-29 + index),
      filled_slot_count: index,
      downtime_minutes: index === 0 ? null : index,
      averages: averages(index === 0 ? {} : { ripple_mill_1_amps: 20 + index }),
    })),
    daily_total: {
      // Bukan jumlah baris hariannya, dan bukan rata-rata dari rata-ratanya.
      filled_slot_count: 99,
      downtime_minutes: 999,
      averages: averages({ ripple_mill_1_amps: 77.7 }),
    },
    // OBJEK berisi ANGKA, bukan daftar alasan teks seperti pada payload
    // Threshing/Pressing. Penyebutnya 3 slot pencatat dari 12 slot terisi —
    // sengaja BERBEDA supaya penyebut yang salah terlihat.
    downtime: {
      total_minutes: 60,
      recorded_slot_count: 3,
      // 60/3 = 20, dan server mengirim 99.
      avg_minutes_per_recorded_slot: 99,
      has_standard: false,
    },
    findings: [
      { finding: 'Ripple mill bergetar', slot_count: 3 },
      { finding: 'ripple mill bergetar', slot_count: 2 },
    ],
    total: {
      record_count: 12,
      days_with_records: 12,
      draft_record_count: 4,
      records_not_checked: 5,
      records_not_acknowledged: 6,
    },
    ...overrides,
  }
}

/**
 * Periode sah tanpa satu slot terisi. coverage_percent 0 — BUKAN null:
 * penyebutnya terbentuk, dan yang terukur memang nol slot.
 */
function emptySummary(): Record<string, unknown> {
  return makeSummary({
    has_data: false,
    coverage: {
      filled_slots: 0,
      expected_slots: 288,
      coverage_percent: 0,
      kernel_plant_count: 2,
      slots_per_kernel_plant_per_day: 24,
      days_in_period: 30,
      days_counted: 6,
      period_running: true,
    },
    metrics: metrics().map((metric) => ({
      ...metric,
      min: null,
      avg: null,
      max: null,
      filled_slot_count: 0,
    })),
    by_kernel_plant: [],
    daily: [],
    daily_total: { filled_slot_count: 0, downtime_minutes: null, averages: averages() },
    downtime: {
      total_minutes: null,
      recorded_slot_count: 0,
      avg_minutes_per_recorded_slot: null,
      has_standard: false,
    },
    findings: [],
  })
}

/* ------------------------------------------------------------------ */
/* Stub jaringan                                                       */
/* ------------------------------------------------------------------ */

interface ApiState {
  periods: unknown[]
  summary: Record<string, unknown>
  /** 200 kecuali test menyetel lain (401 / 422). */
  summaryStatus: number
  /** true = permintaan ringkasan diputus di tingkat jaringan. */
  summaryAbort: boolean
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
    productionLines: [LINE_1],
    productionLinesAbort: false,
    hits: { periods: 0, summary: 0, export: 0, units: 0, lines: 0 },
    urls: [],
    lineUrls: [],
    ...overrides,
  }

  // Daftar Production Line — endpoint DI LUAR prefiks laporan, jadi rutenya
  // sendiri. Tanpa stub ini permintaan menembus ke backend sungguhan di :8000
  // dan hasil test bergantung pada isi database.
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

  await page.route('**/api/kernel-plant-reports/**', async (route) => {
    const request = route.request()
    const url = request.url()

    if (request.method() === 'OPTIONS') {
      await route.fulfill({ status: 204, headers: CORS_HEADERS, body: '' })

      return
    }

    state.urls.push(url)

    if (url.includes('/business-units/options')) {
      state.hits.units += 1
      // ADMIN SAJA — kedua peran layar ini memang menerima 403 dari dalam
      // KernelPlantReportService, dan layar ini tidak punya satu pun jalur ke
      // sini. Stub ini ada JUSTRU supaya pemanggilan yang tak diinginkan
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
          'Periode,Mill,Production Line,Tanggal,Unit Kernel Plant,Status,Catatan,Slot Waktu\n' +
          `Periode Uji Kernel Plant,Mill Utara,Line 1,${PERIOD_START},KP-1,draft,,07:00\n`,
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
      const requestedLineId = new URL(url).searchParams.get('production_line_id')
      const requestedLine = (state.productionLines as Array<{ id: string; name: string }>).find(
        (line) => line.id === requestedLineId,
      )

      await route.fulfill({
        status: 200,
        headers: CORS_HEADERS,
        json: requestedLine
          ? { ...state.summary, production_line: { id: requestedLine.id, name: requestedLine.name } }
          : state.summary,
      })

      return
    }

    await route.fulfill({ status: 404, headers: CORS_HEADERS, json: { message: 'not stubbed' } })
  })

  return state
}

async function openReport(page: Page, path: string = ROUTE): Promise<void> {
  await page.goto(path)
  await expect(page.getByTestId('laporan-kernel-plant-mobile')).toBeVisible()
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
 * Halaman TIDAK PERNAH menggulir mendatar. Yang lebar adalah isi kartu (tabel
 * rekap per unit dan rekap harian) — dan masing-masing menggulir di dalam
 * kartunya sendiri, itulah yang dibuktikan expectCardScrollsInsideItself().
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

/** Satu kolom: tidak ada dua kartu parameter yang berdampingan mendatar. */
async function expectSingleColumn(page: Page): Promise<void> {
  const cards = page.getByTestId('metric-card')

  // TUJUH, TEPAT — bukan "setidaknya satu". toHaveCount menunggu sendiri,
  // jadi evaluateAll di bawah tidak pernah membaca halaman setengah render.
  await expect(cards).toHaveCount(7)
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

/* ================================================================== */

test.describe('Laporan Kernel Plant Mobile (screen-155)', () => {
  // Layar ponsel sungguhan — lihat catatan VIEWPORT pada docblock berkas.
  test.use({ viewport: { width: 390, height: 844 } })

  test.beforeEach(async ({ page }) => {
    await login(page, USERS.operator)
  })

  test('Operator: seluruh laporan tampil satu kolom tanpa gulir mendatar halaman', async ({ page }) => {
    const state = await stubApi(page)

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('period-meta')).toBeVisible()

    // Seluruh bagian hadir.
    for (const testId of [
      'coverage', 'coverage-slots', 'coverage-denominator', 'coverage-days',
      'metrics', 'no-flagging-note', 'targets-without-metric', 'findings',
      'by-kernel-plant', 'downtime', 'completeness', 'daily-recap',
    ]) {
      await expect(page.getByTestId(testId), testId).toBeVisible()
    }

    // TIDAK ADA pemilih mill, dan itu bukan pemangkasan: kedua aktor layar ini
    // terikat satu mill, diselesaikan server dari akun.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.getByTestId('mill-current')).toContainText('Mill Utara')

    await expectSingleColumn(page)
    await expectNoHorizontalPageScroll(page)
    await expectTouchTargets(page, ['period-select', 'export-button', 'back-button'])

    // Dan endpoint daftar mill tidak pernah disentuh — ia 403 untuk peran ini,
    // dan memanggilnya hanya akan membentuk daftar seluruh mill di perangkat
    // yang tidak berhak melihatnya.
    expect(state.hits.units).toBe(0)
  })

  test('business_unit_id tidak pernah ada pada satu pun alamat yang dikirim layar ini', async ({ page }) => {
    const state = await stubApi(page)

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metrics')).toBeVisible()

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
  })

  test('cakupan berada DI ATAS kartu parameter, diukur dari posisi di viewport', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('coverage')).toBeVisible()
    await expect(page.getByTestId('metrics')).toBeVisible()

    const coverage = await page.getByTestId('coverage').boundingBox()
    const metricsBox = await page.getByTestId('metrics').boundingBox()

    expect(coverage).not.toBeNull()
    expect(metricsBox).not.toBeNull()

    // Di layar sempit pembaca melihat lebih sedikit sekaligus, jadi apa yang
    // dilihat lebih dulu menentukan lebih banyak.
    expect(coverage!.y).toBeLessThan(metricsBox!.y)

    // Ketiga angka pembentuk penyebut ikut tercetak, bukan hanya persennya.
    await expect(page.getByTestId('coverage-denominator')).toContainText('2 unit')
    await expect(page.getByTestId('coverage-denominator')).toContainText('6 hari')
    await expect(page.getByTestId('coverage-denominator')).toContainText('24 slot')
    await expect(page.getByTestId('coverage-slots')).toContainText('12 dari 288 slot')
    // Dilingkup KE ELEMENNYA: standar master memuat '≤ 7.0%' dan '≤ 6.0%' dan
    // dirender verbatim, jadi asersi persen tingkat halaman tidak bermakna.
    await expect(page.getByTestId('coverage-percent')).toHaveText('41,7%')
  })

  test('tiap kartu mencetak N miliknya sendiri, dan M-nya SAMA pada ketujuhnya', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    const denominators = page.getByTestId('metric-denominator')

    // toHaveCount menunggu sendiri — allInnerTexts() di bawah karena itu tidak
    // pernah membaca halaman yang belum selesai merender.
    await expect(denominators).toHaveCount(7)

    const texts = await denominators.allInnerTexts()

    expect(texts[0]).toContain('2 dari 12 slot')
    expect(texts[1]).toContain('9 dari 12 slot')
    expect(texts[2]).toContain('0 dari 12 slot')
    expect(texts[3]).toContain('5 dari 12 slot')
    expect(texts[4]).toContain('3 dari 12 slot')
    expect(texts[5]).toContain('7 dari 12 slot')
    expect(texts[6]).toContain('1 dari 12 slot')

    // KETUJUH N berbeda: dengan penyebut seragam, satu penyebut bersama akan
    // lolos tanpa terlihat.
    expect(new Set(texts.map((value) => value.trim())).size).toBe(7)

    // Dan M SERAGAM — skema ini tidak punya cara mengetahui M per kolom,
    // karena ketujuh kolom ukur ada pada SETIAP baris detail.
    expect(texts.every((value) => value.includes('dari 12 slot'))).toBe(true)
  })

  test('M yang melampaui setiap N dirender apa adanya, tanpa "dikoreksi" layar', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    const denominators = page.getByTestId('metric-denominator')

    await expect(denominators).toHaveCount(7)

    const texts = await denominators.allInnerTexts()
    const numbers = texts.map((value) => Number(value.trim().match(/^(\d+) dari (\d+) slot/)?.[1]))

    // Slot dihitung terisi bila salah satu dari SEMBILAN kolom bacaan terisi,
    // dan dua di antaranya (menit downtime, temuan) bukan kolom ukur — slot
    // yang hanya memuat "Ripple mill bergetar" jelas disentuh operator. Yang
    // dilampaui adalah N, BUKAN M, dan itu benar.
    expect(numbers.every((value) => value < 12)).toBe(true)
    await expect(page.getByTestId('coverage-slots')).toContainText('12 dari 288 slot')
    await expect(page.getByTestId('no-flagging-note')).toContainText('N, bukan M')
    await expect(page.getByTestId('no-flagging-note')).toContainText('sembilan')
  })

  test('kartu kolom yang tidak pernah terisi TETAP dirender, dengan pernyataan bukan sel kosong', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metric-card')).toHaveCount(7)

    const claybath = page.getByTestId('metric-card').nth(2)

    // Kartu yang hilang terbaca sebagai "tidak ada parameter ini", padahal
    // yang benar adalah "tidak ada yang mengukurnya".
    await expect(claybath).toContainText('Claybath / Hydrocyclone')
    await expect(claybath).toContainText('tidak tersedia')
    await expect(claybath.getByTestId('metric-denominator')).toContainText('0 dari 12 slot')
    await expect(claybath.getByTestId('metric-master-parameter'))
      .toContainText('tidak terpeta ke satu pun baris master')
    await expect(claybath.getByTestId('metric-target-benchmark'))
      .toContainText('target belum terisi pada master')
  })

  test('DUA kolom target berada DI DALAM kartu angkanya — dan hanya dua', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metric-card')).toHaveCount(7)

    const moistureCard = page.getByTestId('metric-card').nth(5)

    await expect(moistureCard).toContainText('6,80')
    await expect(moistureCard.getByTestId('metric-master-parameter'))
      .toContainText('Final Kernel Moisture')
    await expect(moistureCard.getByTestId('metric-target-benchmark'))
      .toContainText('≤ 7.0% (Prevents mold growth)')
    // Teks TERPANJANG dari ketiga medan master, dan karena itu yang paling
    // berisiko dibuang demi ruang pada kanvas 390px — tanpanya dua parameter
    // yang sama-sama melewati targetnya tampak menuntut tindakan yang sama.
    await expect(moistureCard.getByTestId('metric-corrective-action'))
      .toContainText('Increase retention time or adjust silo air flow rates.')

    // DUA sel standar per kartu, BUKAN empat: master Kernel Plant tidak punya
    // batas kritis maupun akibat operasional, jadi bentuk empat kolom milik
    // Depricarping tidak boleh dapat disalin masuk kemudian.
    await expect(moistureCard.locator('.metric-standard')).toHaveCount(2)
    await expect(page.getByTestId('metric-critical-limit')).toHaveCount(0)
    await expect(page.getByTestId('metric-consequence')).toHaveCount(0)
    await expect(page.getByTestId('metric-target-range')).toHaveCount(0)
    await expect(page.getByTestId('no-flagging-note'))
      .toContainText('tidak ada batas kritis terpisah')
  })

  test('KEEMPAT kartu pasangan menyatakan standarnya SATU, dan angkanya tidak dirata-ratakan', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    const shared = page.getByTestId('metric-shared-standard')

    // EMPAT, bukan dua: 'Ripple Mill (Cracker)' mengatur kedua ripple mill,
    // dan 'Kernel Silo 1 & 2' mengatur kedua silo. Markup yang
    // mengistimewakan SATU pasangan benar di laporan Depricarping mobile dan
    // SALAH di sini.
    await expect(shared).toHaveCount(4)

    const texts = await shared.allInnerTexts()

    // Label saudaranya diambil dari metrics[].label pada payload.
    expect(texts[0]).toContain('Arus Ripple Mill 2')
    expect(texts[1]).toContain('Arus Ripple Mill 1')
    expect(texts[2]).toContain('Suhu Kernel Silo 2')
    expect(texts[3]).toContain('Suhu Kernel Silo 1')

    expect(texts[0]).toContain('Ripple Mill (Cracker)')
    expect(texts[1]).toContain('Ripple Mill (Cracker)')
    expect(texts[2]).toContain('Kernel Silo 1 & 2')
    expect(texts[3]).toContain('Kernel Silo 1 & 2')

    for (const value of texts) {
      expect(value).toContain('Angkanya TIDAK dirata-ratakan menjadi satu')
    }

    // Dan ketiga metrik lain TIDAK membawanya — keterangan yang muncul di
    // mana-mana berhenti berarti apa pun.
    for (const index of [2, 5, 6]) {
      await expect(
        page.getByTestId('metric-card').nth(index).getByTestId('metric-shared-standard'),
      ).toHaveCount(0)
    }

    // Angkanya TIDAK dirata-ratakan: 22,75 (ripple) dan 66,00 (silo) tidak
    // boleh muncul sama sekali.
    await expect(page.getByTestId('metric-card').nth(0)).toContainText('12,50')
    await expect(page.getByTestId('metric-card').nth(1)).toContainText('33,00')
    await expect(page.getByTestId('metric-card').nth(3)).toContainText('74,00')
    await expect(page.getByTestId('metric-card').nth(4)).toContainText('58,00')

    const body = await page.locator('body').innerText()

    expect(body).not.toContain('22,75')
    expect(body).not.toContain('66,00')
  })

  test('keterangan pasangan DITURUNKAN dari respons: kosong pada satu anggota, hilang untuknya', async ({ page }) => {
    const trimmed = metrics()

    ;(trimmed[1].target as Record<string, unknown>).shares_standard_with = []

    await stubApi(page, { summary: makeSummary({ metrics: trimmed }) })
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metric-card')).toHaveCount(7)

    // TIGA keterangan, bukan empat — dan yang hilang adalah milik kartu yang
    // payload-nya mengosongkannya. Markup yang memaku keanggotaan pasangan
    // akan tetap mencetak empat dan gagal di sini.
    await expect(page.getByTestId('metric-shared-standard')).toHaveCount(3)
    await expect(
      page.getByTestId('metric-card').nth(1).getByTestId('metric-shared-standard'),
    ).toHaveCount(0)
    await expect(
      page.getByTestId('metric-card').nth(0).getByTestId('metric-shared-standard'),
    ).toHaveCount(1)
  })

  test('nama parameter master terlepas: PERNYATAANNYA berganti, bukan sekadar nilainya', async ({ page }) => {
    const detached = metrics()

    for (const index of [0, 1]) {
      ;(detached[index].target as Record<string, unknown>).equipment_parameter = null
      ;(detached[index].target as Record<string, unknown>).target_benchmark = null
      ;(detached[index].target as Record<string, unknown>).corrective_action_plan = null
    }

    await stubApi(page, { summary: makeSummary({ metrics: detached }) })
    await openReport(page)
    await pickPeriod(page, 'per-1')

    const shared = page.getByTestId('metric-shared-standard')

    // Keterangannya TETAP ADA — hubungan pasangannya tidak hilang hanya karena
    // masternya terlepas.
    await expect(shared).toHaveCount(4)

    const texts = await shared.allInnerTexts()

    for (const index of [0, 1]) {
      // Mencetaknya apa adanya menghasilkan 'master hanya memuat satu baris
      // “” untuk keduanya': tanda kutip kosong yang MENGKLAIM sebuah baris
      // master yang justru baru terlepas, tepat pada keadaan yang aturan
      // "suntingan master harus terlihat" dibangun untuk itu. Cacat persis ini
      // ditemukan dan ditutup pada layar web screen-154.
      expect(texts[index]).not.toContain('master hanya memuat satu baris')
      expect(texts[index]).not.toContain('“”')
      expect(texts[index]).not.toContain('""')
      expect(texts[index]).toContain('tidak terpeta ke satu pun baris master')
      expect(texts[index]).toContain('tidak lagi tercetak di kartu ini')
      // Dan ia TETAP menyatakan bahwa angkanya tidak dirata-ratakan — itulah
      // alasan keterangan ini ada sejak awal.
      expect(texts[index]).toContain('Angkanya TIDAK dirata-ratakan menjadi satu')
    }

    // Kartu pasangan silo, yang masternya masih terpeta, tetap memakai
    // kalimat yang satunya.
    expect(texts[2]).toContain('master hanya memuat satu baris')
    expect(texts[2]).toContain('Kernel Silo 1 & 2')
  })

  test('tidak ada satu pun elemen berkelas penanda di luar batas pada halaman', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metrics')).toBeVisible()
    await expect(page.getByTestId('metric-card')).toHaveCount(7)

    // Rata-rata ripple mill 2 (33 Amps) jelas di luar '20 - 25 Amps', dan
    // TETAP tidak ditandai. DISISIR ATAS JUMLAH ELEMEN BERKELAS, bukan atas
    // frasa: kalimat yang menyatakan ketiadaannya sendiri memuat frasa
    // "di luar batas".
    for (const className of ['md-threshold', 'is-danger', 'is-warning', 'md-chip--danger']) {
      await expect(page.locator(`.${className}`), className).toHaveCount(0)
    }

    const note = page.getByTestId('no-flagging-note')

    await expect(note).toContainText('tidak menilai satu angka pun')
    await expect(note).toContainText('teks bebas')
    // Sebab yang KHAS stasiun ini: satu sel memuat dua angka dengan satuan
    // berbeda dan arah pembanding berlawanan.
    await expect(note).toContainText('dua angka dengan satuan berbeda dan arah pembanding berlawanan')
    await expect(note).toContainText('berhenti memperingatkan tanpa satu pun galat')
    await expect(note).toContainText('Kedua kolom target berlaku umum untuk seluruh mill')
    await expect(note).toContainText('M sama untuk ketujuh kartu')
  })

  test('standar tanpa pengukuran: SATU baris adalah keadaan NORMAL layar ini', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('targets-without-metric')).toBeVisible()
    // Jumlahnya diasersi — "bagiannya ada" saja akan tetap hijau walau
    // entrinya hilang.
    await expect(page.getByTestId('targets-without-metric-row')).toHaveCount(1)
    await expect(page.getByTestId('targets-without-metric')).toContainText('Final Kernel Dirt')
    await expect(page.getByTestId('targets-without-metric'))
      .toContainText('≤ 6.0% (Standard quality premium)')
    await expect(page.getByTestId('targets-without-metric'))
      .toContainText('Clean winnowing ducts or re-calibrate hydrocyclone settings.')
    // ALASANNYA tercetak, bukan hanya nama parameternya.
    await expect(page.getByTestId('targets-without-metric-reason'))
      .toContainText('Tidak ada kolom pengukurannya')
    // Dan dinyatakan bahwa daftar yang BERISI di sini bukan tanda kerusakan —
    // berlawanan dengan laporan Depricarping.
    await expect(page.getByTestId('targets-without-metric-note'))
      .toContainText('adalah penghuni normal daftar ini')
    await expect(page.getByTestId('targets-all-measured')).toHaveCount(0)
    await expect(page.getByTestId('targets-master-empty')).toHaveCount(0)
  })

  test('bagian standar-tanpa-pengukuran TETAP digambar walau kosong, dengan NOL baris', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({ targets_without_metric: [], all_targets_measured: true }),
    })
    await openReport(page)
    await pickPeriod(page, 'per-1')

    // Bagian yang hilang ketika kosong tidak dapat dibedakan dari bagian yang
    // belum pernah dibuat.
    await expect(page.getByTestId('targets-without-metric')).toBeVisible()
    await expect(page.getByTestId('targets-all-measured')).toBeVisible()
    await expect(page.getByTestId('targets-without-metric-row')).toHaveCount(0)
  })

  test('master target kosong: bagian itu sengaja hilang, angka ukur tetap ada', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        targets_master_empty: true,
        targets_without_metric: [],
        all_targets_measured: true,
      }),
    })
    await openReport(page)
    await pickPeriod(page, 'per-1')

    // Daftar kosong karena master belum terisi BERLAWANAN artinya dengan
    // daftar kosong karena seluruh standar sudah terukur.
    await expect(page.getByTestId('targets-master-empty')).toBeVisible()
    await expect(page.getByTestId('targets-without-metric')).toHaveCount(0)
    // Master yang belum diisi tidak menghapus pengukuran yang sudah terjadi.
    await expect(page.getByTestId('metric-card')).toHaveCount(7)
    await expect(page.getByTestId('metrics')).toContainText('12,50')
  })

  test('days_counted 0 diperiksa SEBELUM period_running: persen tanda pisah, bukan 0,0%', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          coverage_percent: null,
          kernel_plant_count: 0,
          slots_per_kernel_plant_per_day: 24,
          days_in_period: 30,
          days_counted: 0,
          // true juga di server untuk periode yang BELUM MULAI — inilah
          // kombinasi yang benar-benar dikirimnya, dan inilah sebabnya
          // days_counted harus diperiksa lebih dulu.
          period_running: true,
        },
      }),
    })
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('coverage-percent')).toHaveText('—')
    await expect(page.getByTestId('coverage-not-started-note')).toBeVisible()
    await expect(page.getByTestId('coverage-running-note')).toHaveCount(0)
  })

  test('periode tanpa data: persen 0,0%, ketujuh kartu tetap ada, rekap tidak digambar', async ({ page }) => {
    await stubApi(page, { summary: emptySummary() })
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('empty-period')).toBeVisible()
    // Cakupan TETAP digambar — justru itu yang menjelaskan kekosongannya.
    await expect(page.getByTestId('coverage')).toBeVisible()
    // 0 BUKAN null: penyebutnya terbentuk, dan yang terukur memang nol slot.
    // Draf pertama spec layar web menyatakan ini terbalik dan sudah
    // dikoreksi. Dilingkup ke elemennya — bukan ke halaman.
    await expect(page.getByTestId('coverage-percent')).toHaveText('0,0%')
    // KETUJUH kartu tetap ada, dengan angka tidak tersedia.
    await expect(page.getByTestId('metric-card')).toHaveCount(7)
    await expect(page.getByTestId('metrics')).toContainText('tidak tersedia')
    // Tabel kosong akan terbaca sebagai hasil pengukuran bernilai nol.
    await expect(page.getByTestId('by-kernel-plant')).toHaveCount(0)
    await expect(page.getByTestId('downtime')).toHaveCount(0)
    await expect(page.getByTestId('findings')).toHaveCount(0)
    await expect(page.getByTestId('daily-recap')).toHaveCount(0)
    // Tetapi bagian standar tanpa pengukuran TETAP digambar.
    await expect(page.getByTestId('targets-without-metric')).toBeVisible()
    await expectNoHorizontalPageScroll(page)
  })

  test('rekap per unit dan rekap harian menggulir DI DALAM kartunya', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('by-kernel-plant-table')).toBeVisible()
    await expect(page.getByTestId('by-kernel-plant-row')).toHaveCount(2)

    // TUJUH kolom rata-rata plus nama unit, hari, slot dan downtime jelas
    // lebih lebar daripada 390px.
    await expectCardScrollsInsideItself(page, '[data-testid="by-kernel-plant"] .detail-table-wrap')

    await recapToggle(page).click()
    await expect(recapBody(page)).toBeVisible()

    await expectCardScrollsInsideItself(page, '[data-testid="daily-recap"] .detail-table-wrap')
    await expectNoHorizontalPageScroll(page)
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
    await expect(page.getByTestId('daily-row')).toHaveCount(12)
    await expect(page.getByTestId('daily-recap-total')).toBeVisible()
    // Total periode DIHITUNG ULANG server atas seluruh slot terisi, bukan
    // merata-ratakan rata-rata harian — karena itu pula ia tidak berubah
    // ketika rekap ditutup.
    await expect(page.getByTestId('daily-recap-total')).toContainText('77,70')
    await expect(page.getByTestId('daily-recap-total')).toContainText('999')

    await recapToggle(page).click()
    await expect(recapBody(page)).toBeHidden()

    // Membuka/menutup murni penyingkapan: datanya sudah ada di memori.
    expect(state.hits.summary).toBe(summaryHitsBefore)
  })

  test('blok downtime menerbitkan angka dengan penyebut slot pencatatnya', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    // Bagian yang tidak punya padanan pada laporan Threshing maupun Pressing
    // mobile: di sana downtime hanya berupa teks.
    await expect(page.getByTestId('downtime-total')).toBeVisible()
    await expect(page.getByTestId('downtime-total')).toContainText('60')
    // PENYEBUTNYA 3 slot pencatat, BUKAN 12 slot terisi.
    await expect(page.getByTestId('downtime-recorded-slots')).toContainText('3')
    await expect(page.getByTestId('downtime-recorded-slots')).toContainText('dari 12 slot terisi')
    await expect(page.getByTestId('downtime-average')).toContainText('99,0')
    await expect(page.getByTestId('downtime-note')).toContainText('tidak dihitung sebagai nol')
    await expect(page.getByTestId('downtime-note'))
      .toContainText('tidak punya baris pada master target')

    // DUA bagian yang berbeda, dan keduanya ada: satu menjawab "berapa lama",
    // satu "apa yang terlihat".
    await expect(page.getByTestId('findings')).toBeVisible()
    await expect(page.getByTestId('findings-note')).toContainText('tidak digabungkan')
  })

  test('tanpa satu pun slot pencatat: blok downtime menyatakan ketiadaan, bukan 0 menit', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        downtime: {
          total_minutes: null,
          recorded_slot_count: 0,
          avg_minutes_per_recorded_slot: null,
          has_standard: false,
        },
      }),
    })
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('downtime-empty')).toBeVisible()
    await expect(page.getByTestId('downtime-total')).toHaveCount(0)
    await expect(page.getByTestId('downtime-empty')).toContainText('Belum ada satu slot pun')
    // Keterangan cara-hitung TETAP terender: ia menjelaskan cara hitungnya,
    // bukan hasilnya.
    await expect(page.getByTestId('downtime-note')).toContainText('tidak dihitung sebagai nol')
    await expectNoHorizontalPageScroll(page)
  })

  test('dua ejaan temuan tampil sebagai dua baris, dengan keterangan harfiah', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('findings-table')).toBeVisible()
    await expect(page.getByTestId('findings-row')).toHaveCount(2)
    await expect(page.getByTestId('findings-note')).toContainText('harfiah')
  })

  test('tanpa temuan: keterangan tampil, bukan tabel kosong', async ({ page }) => {
    await stubApi(page, { summary: makeSummary({ findings: [] }) })
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('findings-empty')).toBeVisible()
    await expect(page.getByTestId('findings-table')).toHaveCount(0)
  })

  test('kelengkapan record: kelima penghitung tampil, dengan keterangan draft ikut terhitung', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('record-count')).toHaveText('12')
    await expect(page.getByTestId('days-with-records')).toHaveText('12')
    await expect(page.getByTestId('draft-record-count')).toHaveText('4')
    await expect(page.getByTestId('records-not-checked')).toHaveText('5')
    await expect(page.getByTestId('records-not-acknowledged')).toHaveText('6')
    await expect(page.getByTestId('draft-note')).toContainText('IKUT terhitung')
  })

  test('nama mill dibaca dari blok business_unit, bukan dari kepala periode', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        business_unit: { id: 'bu-1', name: 'Mill Dari Blok Business Unit' },
      }),
    })
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('mill-current')).toContainText('Mill Dari Blok Business Unit')
    // Dua tempat yang dapat menyimpang satu dari yang lain adalah satu tempat
    // terlalu banyak — dan /periods layar ini memang tidak membawa nama mill.
    await expect(page.getByTestId('mill-current')).not.toContainText('Mill Dari Kepala Periode')
  })

  test('periode tertutup: laporan penuh, badge Tertutup, ekspor tetap aktif', async ({ page }) => {
    await stubApi(page, {
      summary: makeSummary({
        period: {
          id: 'per-2',
          name: 'Periode Sebelumnya',
          start_date: PREV_START,
          end_date: PREV_END,
          status: 'closed',
        },
      }),
    })

    await openReport(page)
    await pickPeriod(page, 'per-2')

    await expect(page.getByTestId('period-status-badge')).toContainText('Tertutup')
    await expect(page.getByTestId('metrics')).toBeVisible()
    // Kunci periode mengatur penulisan data, bukan pembacaan laporan.
    await expect(page.getByTestId('export-button')).toBeEnabled()
    await expect(page.getByTestId('period-meta')).toContainText('mengatur penulisan data')
  })

  test('ekspor CSV saja: unduhan terpicu, permintaannya membawa periode + line, tanpa tawaran xlsx', async ({ page }) => {
    const state = await stubApi(page)

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('export-button')).toBeVisible()

    const download = page.waitForEvent('download')

    await page.getByTestId('export-button').click()

    // Berkasnya dibuang begitu diterima — yang diuji di sini adalah bahwa
    // unduhan BENAR-BENAR terpicu di browser dan bentuk permintaannya benar.
    // Nama berkasnya (slug nama periode) diuji pada spec komponen, tempat ia
    // dapat diperiksa tanpa bergantung pada mekanisme unduhan browser.
    await (await download).delete()

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

  test('kegagalan jaringan: pesan + Coba Lagi, periode terpilih tidak hilang', async ({ page }) => {
    const state = await stubApi(page, { summaryAbort: true })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('network-error')).toBeVisible()
    await expect(page.getByTestId('retry-button')).toBeVisible()
    // Pengguna di area tanpa sinyal tidak boleh dipaksa memilih dari awal.
    await expect(page.getByTestId('period-select')).toHaveValue('per-1')

    state.summaryAbort = false

    await page.getByTestId('retry-button').click()

    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('metric-card')).toHaveCount(7)
  })

  test('sesi berakhir (401): diarahkan ke Login tanpa pesan jaringan', async ({ page }) => {
    await stubApi(page, { summaryStatus: 401 })

    await openReport(page)
    await pickPeriod(page, 'per-1')

    // PATHNAME, bukan pola glob dengan baseURL.
    await expectPathname(page, '/login')

    // "Periksa koneksi Anda" pada sesi yang berakhir mengirim pembaca
    // memeriksa sinyalnya alih-alih masuk kembali.
    await expect(page.getByTestId('network-error')).toHaveCount(0)
  })

  test('mill tanpa Production Line: arahan menghubungi Admin, tanpa tombol coba lagi', async ({ page }) => {
    const state = await stubApi(page, { productionLines: [] })

    await openReport(page)

    await expect(page.getByTestId('production-line-unavailable')).toContainText(
      'belum memiliki Production Line',
    )
    await expect(page.getByTestId('production-line-unavailable')).toContainText('hubungi Admin')
    // Mencoba lagi tidak akan mengubah sesuatu yang tidak ada.
    await expect(page.getByTestId('production-line-retry')).toHaveCount(0)
    // Dan tidak ada satu angka pun yang diminta.
    expect(state.hits.summary).toBe(0)
  })

  test('daftar Production Line gagal dimuat: pesan BERBEDA, dengan tombol coba lagi sendiri', async ({ page }) => {
    const state = await stubApi(page, { productionLinesAbort: true })

    await openReport(page)

    await expect(page.getByTestId('production-line-unavailable')).toContainText('tidak dapat dimuat')
    await expect(page.getByTestId('production-line-unavailable'))
      .toContainText('Periksa koneksi jaringan Anda')
    // DUA keadaan yang menuntut tindakan berbeda, jadi dua pesan berbeda —
    // menyatukannya akan menyuruh pengguna menelepon Admin untuk masalah
    // jaringan, atau sebaliknya.
    await expect(page.getByTestId('production-line-unavailable'))
      .not.toContainText('belum memiliki Production Line')
    await expect(page.getByTestId('production-line-retry')).toBeVisible()
    // Kegagalan daftar line tidak boleh menyamar sebagai galat laporan.
    await expect(page.getByTestId('network-error')).toHaveCount(0)

    state.productionLinesAbort = false

    await page.getByTestId('production-line-retry').click()

    await expect(page.getByTestId('production-line-unavailable')).toHaveCount(0)
  })

  test('dua line tanpa pilihan: /summary TIDAK PERNAH diminta, juga sesudah ganti periode', async ({ page }) => {
    const state = await stubApi(page, { productionLines: [LINE_1, LINE_2] })

    await openReport(page)

    await expect(page.getByTestId('production-line-select')).toBeVisible()
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()

    await pickPeriod(page, 'per-1')

    // Penjagaan ada di loadSummary(), jadi tidak ada permintaan yang berangkat
    // — bukan sekadar angka yang tidak dirender.
    expect(state.hits.summary).toBe(0)
    await expect(page.getByTestId('period-meta')).toHaveCount(0)

    await pickPeriod(page, 'per-2')

    expect(state.hits.summary).toBe(0)

    await page.getByTestId('production-line-select').selectOption('pl-2')

    // Dan begitu sebuah line berlaku, permintaannya berangkat — tanpa asersi
    // ini, yang di atas juga hijau pada layar yang rusak total.
    await expect(page.getByTestId('metrics')).toBeVisible()
    expect(state.urls.some((url) => url.includes('production_line_id=pl-2'))).toBe(true)
  })

  test('line dari query rute dipakai dan diingat perangkat', async ({ page }) => {
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

  test('line yang diingat perangkat dipakai tanpa diminta memilih lagi', async ({ page }) => {
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
  })

  test('line yang diingat sudah tidak ada: pemilih muncul lagi, tanpa menebak line lain', async ({ page }) => {
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

  test('mill belum punya periode: arahan menghubungi Admin, pemilih tetap ada dalam keadaan kosong', async ({ page }) => {
    await stubApi(page, { periods: [] })

    await openReport(page)

    await expect(page.getByTestId('no-periods')).toBeVisible()
    // Pemilih yang LENYAP dan pemilih yang KOSONG menceritakan hal berbeda.
    await expect(page.getByTestId('period-select')).toBeVisible()
  })

  test('layar hanya membaca: footer hanya Ekspor dan Back', async ({ page }) => {
    await stubApi(page)
    await openReport(page)
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('export-button')).toBeVisible()

    const footerButtons = page.locator('.action-footer button')

    await expect(footerButtons).toHaveCount(2)
    await expect(footerButtons.nth(0)).toContainText('Ekspor')
    await expect(footerButtons.nth(1)).toContainText('Back')

    // Tidak ada satu pun kontrol tulis di seluruh layar.
    await expect(page.getByRole('button', { name: /Simpan|Hapus|Verifikasi/i })).toHaveCount(0)
  })

  test('Back kembali ke Dashboard & Reporting', async ({ page }) => {
    await stubApi(page)
    await openReport(page)

    await page.getByTestId('back-button').click()
    await expectPathname(page, '/dashboard-reporting')
  })
})

/* ================================================================== */
/* Pintu masuk — tile Kernel Plant pada layar pemilih stasiun           */
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
   * SATU BARIS yang menentukan layar ini dapat dicapai: entri 'kernel-plant'
   * pada REPORT_ROUTES di ReportingPilihStasiunView.vue. Tanpa test ini, layar
   * laporan bisa lengkap, seluruh test lainnya hijau, dan tile-nya tetap
   * kelabu — pola kegagalan yang sudah terjadi sekali pada laporan Cages &
   * Tracks versi web.
   */
  test('tile Kernel Plant aktif dan menavigasi ke layar laporannya', async ({ page }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await seedLocalStations(page)
    await goToReports(page)

    const tile = page.getByTestId('station-tile-kernel-plant')

    await expect(tile).toBeVisible()
    await tile.click()

    // Alamatnya membawa ?production_line_id= dari layar ini, jadi yang
    // dibandingkan adalah PATHNAME-nya saja.
    await expectPathname(page, ROUTE)
    await expect(page.getByTestId('laporan-kernel-plant-mobile')).toBeVisible()

    // Dan tidak ada pesan "belum tersedia" yang muncul sebagai gantinya.
    await expect(page.getByTestId('info-message')).toHaveCount(0)
  })
})
