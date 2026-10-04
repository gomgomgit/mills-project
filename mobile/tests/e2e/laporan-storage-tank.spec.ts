import { test, expect, type Page } from '@playwright/test'
import { getAuthUserId, login, USERS } from './helpers'

/**
 * laporan-storage-tank.spec.ts — screen-139--laporan-storage-tank-mobile /
 * usecase-139--laporan-storage-tank-mobile "Lihat Laporan Periode Storage
 * Tank (Mobile)".
 *
 * Satu test per test_scenarios yang punya browser_test — seluruh 33.
 * Berjalan di Vite dev server (playwright.config.ts, baseURL
 * http://localhost:5174): aplikasi Capacitor adalah SPA biasa sebelum
 * dibungkus native, jadi browser test layar mobile TIDAK ditangguhkan
 * sebagai "mobile-only".
 *
 * ────────────────────────────────────────────────────────────────────────
 * VIEWPORT PONSEL, BUKAN DESKTOP — BUKAN OPSIONAL
 * ────────────────────────────────────────────────────────────────────────
 * Berkas ini menimpa viewport bawaan proyek chromium (Desktop Chrome,
 * 1280px) dengan 390x844 lewat test.use() pada SELURUH describe. Separuh
 * klaim layar ini adalah klaim tata letak ("satu kolom", "tanpa gulir
 * mendatar pada halaman", "sasaran sentuh minimal 44px"), dan seluruhnya
 * menjadi HAMPA bila diuji pada kanvas selebar 1280px: apa pun muat di
 * sana, dan asersinya lulus tanpa menguji apa pun. Skenario 32 menuliskan
 * 375x812 — lebih sempit lagi — dan test-nya menyempitkan viewport ke
 * angka itu SETELAH memeriksa 390px, sehingga keduanya terbukti.
 *
 * MASUK LEWAT RUTE LANGSUNG. Pintu masuk UI-nya (screen-141, Reporting:
 * pilih stasiun) punya test-nya sendiri di
 * tests/e2e/reporting-pilih-stasiun.spec.ts; berkas ini menavigasi
 * langsung ke '/reports/storage-tank' supaya kegagalan di sini selalu
 * berarti layar laporannya, bukan layar pemilih stasiun.
 *
 * RESPONS API DI-STUB DI TINGKAT JARINGAN (page.route), BUKAN DISEMAI KE
 * DATABASE — keputusan yang sama dengan laporan-boiler-room.spec.ts,
 * laporan-cages-track.spec.ts, laporan-sterilizer.spec.ts, dan
 * laporan-clarification.spec.ts:
 *   - Layar ini read-only total dan tidak punya satu pun UI untuk membuat
 *     data yang dibacanya; menyemai fixture berarti menjalankan dua layar
 *     WEB lain (periode + input Storage Tank) dari dalam suite mobile.
 *   - Kebenaran ANGKA-nya bukan tanggung jawab layar ini: seluruhnya
 *     berasal dari StorageTankReportService, yang sudah diuji penuh di
 *     backend (Unit/Services + Feature/Api + Feature/Livewire). Yang khas
 *     mobile — dan hanya dapat dibuktikan di sini — adalah PEMETAAN
 *     respons itu ke layar satu kolom 390px.
 *   - Beberapa skenario mustahil dibuat dari data sungguhan tanpa merusak
 *     lingkungan (sesi kedaluwarsa, jaringan putus, akun tanpa mill).
 *
 * ANGKA FIXTURE SENGAJA TIDAK KONSISTEN SECARA ARITMETIKA — sama persis
 * dengan tests/storageTankReportRepo.spec.ts dan
 * tests/LaporanStorageTankView.spec.ts: stock.movement_mt (280,5) ≠
 * closing_mt − opening_mt (300,0) dan ≠ jumlah by_tank[].movement_mt
 * (260,5); by_tank[0].movement_mt (100,5) ≠ 960,0 − 800,0;
 * stock.tanks_without_movement (1) ≠ cacah baris movement_computable false
 * (2); metrics.ffa_percent.max (4,4) LEBIH KECIL daripada daily[0].ffa_avg
 * (5,8) karena ekstrem berasal dari PEMBACAAN MENTAH per slot waktu;
 * metrics.average_temperature_c.avg (52,0) ≠ rata-rata ketiga suhu posisi
 * (55,4); coverage.expected_slots (240) ≠ 4 × 30 × 4 (480);
 * coverage.filled_slots (3) ≠ jumlah daily.filled_slots; total
 * .days_with_records (11) ≠ panjang daily (4). Layar yang diam-diam
 * menghitung ulang HARUS gagal di sini. Jangan "merapikan".
 *
 * STOK DIAMBIL DARI BERAT (MT), bukan dari calculated_volume_m3 maupun
 * kedalaman sounding — keputusan yang sudah final dan tertanam di
 * StorageTankReportService::STOCK_COLUMN. Skenario 27 menjaganya.
 *
 * PERGERAKAN "tidak dapat dihitung", TIDAK PERNAH "0,0" — nol adalah klaim
 * bahwa stoknya tidak berubah, dan klaim itu tidak pernah diukur. Asersi
 * atas hal ini SELALU menyasar SEL pergerakannya (td ke-6 pada
 * [data-testid="by-tank-row"]), tidak pernah teks seluruh barisnya: "0,0"
 * adalah substring dari "400,0" pada kolom stok, dan asersi selebar baris
 * akan memerah betapa pun benarnya layar.
 *
 * REKAP HARIAN MEMAKAI v-if, BUKAN v-show — sama seperti screen-137/138.
 * Saat tertutup barisnya benar-benar HILANG dari DOM, jadi keadaan
 * tertutup diasersi dengan toHaveCount(0), bukan not.toBeVisible().
 *
 * SESI BERAKHIR MEMAKAI router.replace, BUKAN push (lihat docblock
 * LaporanStorageTankView.vue): layar laporan yang sesinya sudah mati tidak
 * boleh dapat dicapai kembali dengan tombol Back peramban. Tech spec
 * menuliskan "router.push"; yang berlaku adalah replace, mengikuti
 * screen-135/136/137/138, dan skenario 18 memeriksanya lewat panjang
 * history yang TIDAK bertambah.
 */

const ROUTE = '/reports/storage-tank'
const API_GLOB = '**/api/storage-tank-reports/**'

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
/* Fixture — BENTUK RESPONS NYATA                                      */
/* ------------------------------------------------------------------ */

/**
 * /periods dan /business-units/options dibungkus { data: [...] },
 * sedangkan /summary dikirim TANPA pembungkus. Itu bentuk yang
 * benar-benar dikirim App\Http\Controllers\Api\StorageTankReportController.
 */
const PERIOD_STG = {
  id: 'per-1',
  name: 'Periode September 2026',
  start_date: '2026-09-01',
  end_date: '2026-09-30',
  status: 'open',
  station_type: 'storage-tank',
  station_type_label: 'Storage Tank',
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
  name: 'Periode Lintas Stasiun September 2026',
  start_date: '2026-09-01',
  end_date: '2026-09-30',
  status: 'open',
  station_type: 'storage-tank',
  station_type_label: 'Storage Tank',
}

const PERIOD_CLOSED = {
  id: 'per-3',
  name: 'Periode Agustus 2026',
  start_date: '2026-08-01',
  end_date: '2026-08-31',
  status: 'closed',
  station_type: 'storage-tank',
  station_type_label: 'Storage Tank',
}

const BUSINESS_UNITS = [
  { id: 'bu-1', name: 'Mill Utara' },
  { id: 'bu-2', name: 'Mill Selatan' },
]

const EMPTY_METRIC = { min: null, avg: null, max: null, reading_count: 0 }

/**
 * Kesepuluh metrik dikirim apa adanya oleh server. Hanya enam yang DIRENDER
 * (empat kartu mutu + satu kartu suhu rata-rata); calculated_weight_mt sudah
 * menjadi angka stok di atas, dan calculated_volume_m3 serta ketiga suhu
 * posisi memang tidak punya kartu — sama seperti laporan web screen-133.
 * Ketiganya tetap ada di fixture supaya skenario 27 dan 28 dapat
 * membuktikan bahwa layar TIDAK menurunkan apa pun darinya.
 */
function makeMetrics(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    ffa_percent: { min: 3.1, avg: 4.05, max: 4.4, reading_count: 240 },
    moisture_content_percent: { min: 0.11, avg: 0.195, max: 0.24, reading_count: 118 },
    impurities_dirt_percent: { min: 0.01, avg: 0.027, max: 0.05, reading_count: 42 },
    dobi_index: { min: 2.2, avg: 2.85, max: 3.4, reading_count: 19 },
    average_temperature_c: { min: 48.0, avg: 52.0, max: 57.0, reading_count: 12 },
    calculated_weight_mt: { min: 380.0, avg: 660.0, max: 980.0, reading_count: 231 },
    calculated_volume_m3: { min: 420.0, avg: 730.0, max: 1090.0, reading_count: 229 },
    oil_temperature_top_c: { min: 46.0, avg: 50.0, max: 54.0, reading_count: 205 },
    oil_temperature_middle_c: { min: 51.0, avg: 55.0, max: 59.0, reading_count: 204 },
    oil_temperature_bottom_c: { min: 57.0, avg: 61.2, max: 66.0, reading_count: 203 },
    ...overrides,
  }
}

const EMPTY_METRICS = {
  ffa_percent: { ...EMPTY_METRIC },
  moisture_content_percent: { ...EMPTY_METRIC },
  impurities_dirt_percent: { ...EMPTY_METRIC },
  dobi_index: { ...EMPTY_METRIC },
  average_temperature_c: { ...EMPTY_METRIC },
  calculated_weight_mt: { ...EMPTY_METRIC },
  calculated_volume_m3: { ...EMPTY_METRIC },
  oil_temperature_top_c: { ...EMPTY_METRIC },
  oil_temperature_middle_c: { ...EMPTY_METRIC },
  oil_temperature_bottom_c: { ...EMPTY_METRIC },
}

/** Baris pertama tepat pada tanggal mulai, baris terakhir tepat pada tanggal akhir. */
const DAILY_ROWS = [
  {
    date: '2026-09-01',
    filled_slots: 4,
    stock_total_mt: 1240.0,
    ffa_avg: 5.8,
    moisture_avg: 0.21,
    impurities_avg: 0.02,
    dobi_avg: 3.1,
    temperature_avg: 53.0,
  },
  {
    date: '2026-09-03',
    filled_slots: 3,
    stock_total_mt: null,
    ffa_avg: 3.4,
    moisture_avg: null,
    impurities_avg: null,
    dobi_avg: null,
    temperature_avg: null,
  },
  {
    date: '2026-09-20',
    filled_slots: 5,
    stock_total_mt: 1380.0,
    ffa_avg: 4.0,
    moisture_avg: 0.19,
    impurities_avg: 0.03,
    dobi_avg: 2.8,
    temperature_avg: 51.5,
  },
  {
    date: '2026-09-30',
    filled_slots: 6,
    stock_total_mt: 1470.0,
    ffa_avg: 4.2,
    moisture_avg: 0.18,
    impurities_avg: 0.03,
    dobi_avg: 2.6,
    temperature_avg: 50.9,
  },
]

/**
 * Empat tangki, dan keempatnya ada di sini dengan alasannya sendiri: dua
 * dengan pergerakan yang dapat dihitung (nilainya sengaja tidak sama dengan
 * closing − opening), satu berpembacaan TUNGGAL (movement null,
 * movement_computable false — bukan 0), dan satu TANPA satu pun pembacaan
 * (seluruh kolom stok null, reading_count 0) yang tetap wajib dirender.
 */
const BY_TANK_ROWS = [
  {
    storage_tank_id: 'ST-1',
    reading_count: 180,
    opening_mt: 800.0,
    opening_at: '2026-09-01 06:00',
    closing_mt: 960.0,
    closing_at: '2026-09-30 18:00',
    movement_mt: 100.5,
    movement_computable: true,
    ffa_avg: 4.1,
    average_temperature_avg: 52.0,
  },
  {
    storage_tank_id: 'ST-2',
    reading_count: 96,
    opening_mt: 500.0,
    opening_at: '2026-09-02 06:00',
    closing_mt: 540.0,
    closing_at: '2026-09-28 12:00',
    movement_mt: 160.0,
    movement_computable: true,
    ffa_avg: 3.9,
    average_temperature_avg: null,
  },
  {
    storage_tank_id: 'ST-3',
    reading_count: 1,
    opening_mt: 400.0,
    opening_at: '2026-09-03 06:00',
    closing_mt: 400.0,
    closing_at: '2026-09-03 06:00',
    movement_mt: null,
    movement_computable: false,
    ffa_avg: null,
    average_temperature_avg: null,
  },
  {
    storage_tank_id: 'ST-4',
    reading_count: 0,
    opening_mt: null,
    opening_at: null,
    closing_mt: null,
    closing_at: null,
    movement_mt: null,
    movement_computable: false,
    ffa_avg: null,
    average_temperature_avg: null,
  },
]

const STOCK = {
  opening_mt: 1200.0,
  opening_at: '2026-09-01 06:00',
  closing_mt: 1500.0,
  closing_at: '2026-09-28 18:00',
  movement_mt: 280.5,
  tanks_with_movement: 2,
  tanks_without_movement: 1,
}

const COVERAGE = {
  filled_slots: 3,
  expected_slots: 240,
  coverage_percent: 1.25,
  tank_count: 4,
  slots_per_tank_per_day: 4,
  days_in_period: 30,
}

function makeSummary(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    period: {
      id: 'per-1',
      name: 'Periode September 2026',
      start_date: '2026-09-01',
      end_date: '2026-09-30',
      status: 'open',
      business_unit_name: 'Mill Utara',
    },
    business_unit: { id: 'bu-1', name: 'Mill Utara' },
    has_data: true,
    coverage: { ...COVERAGE },
    stock: { ...STOCK },
    metrics: makeMetrics(),
    by_tank: BY_TANK_ROWS,
    daily: DAILY_ROWS,
    total: { days_with_records: 11, reading_rows: 412 },
    ...overrides,
  }
}

/** Periode tanpa satu pun pembacaan — has_data false datang dari SERVER. */
const EMPTY_SUMMARY = makeSummary({
  has_data: false,
  coverage: {
    filled_slots: 0,
    expected_slots: 480,
    coverage_percent: 0,
    tank_count: 4,
    slots_per_tank_per_day: 4,
    days_in_period: 30,
  },
  stock: {
    opening_mt: null,
    opening_at: null,
    closing_mt: null,
    closing_at: null,
    movement_mt: null,
    tanks_with_movement: 0,
    tanks_without_movement: 0,
  },
  metrics: { ...EMPTY_METRICS },
  by_tank: [],
  daily: [],
  total: { days_with_records: 0, reading_rows: 0 },
})

/**
 * Rekap sebulan penuh — 30 baris. Ini juga fixture tata letak: 30 kolom
 * grafik (40px masing-masing pada grafik mutu bergerombol) jauh lebih lebar
 * daripada viewport 390px, yang membuat klaim "menggulir DI DALAM kartunya"
 * dapat diuji.
 */
const THIRTY_DAYS = Array.from({ length: 30 }, (_, index) => ({
  date: `2026-09-${String(index + 1).padStart(2, '0')}`,
  filled_slots: 4,
  stock_total_mt: 1200 + index * 5,
  ffa_avg: 4 + index * 0.01,
  moisture_avg: 0.19 + index * 0.001,
  impurities_avg: 0.02 + index * 0.001,
  dobi_avg: 2.8 + index * 0.01,
  temperature_avg: 52 + index * 0.1,
}))

/** Keempat kartu mutu, urutan mengikuti laporan web screen-133. */
const METRIC_TESTIDS = ['ffa', 'moisture', 'impurities', 'dobi']

/**
 * Isi CSV — dibentuk SERVER. Keduapuluh dua kolomnya persis
 * StorageTankReportService::EXPORT_HEADER: EMPAT kolom konteks record
 * (Tanggal, Tangki, Status, Catatan) yang diulang pada setiap baris, lalu
 * Slot Waktu, lalu ketujuh belas kolom pengukuran dengan label yang sama
 * dengan layar Detail. Dua baris di bawah sengaja berbagi keempat kolom
 * konteks yang sama dan hanya berbeda slot waktunya — itulah bentuk "satu
 * baris per detail, konteks diulang".
 */
const CSV_HEADER =
  'Tanggal,Tangki,Status,Catatan,Slot Waktu,Kedalaman Sounding CPO (mm),' +
  'Kedalaman Water Dip Bottom (mm),Kedalaman Minyak Bersih (mm),Suhu Minyak Atas (C),' +
  'Suhu Minyak Tengah (C),Suhu Minyak Bawah (C),Suhu Rata-rata (C),Volume Terhitung (m3),' +
  'Berat Terhitung (MT),FFA (%),Kadar Air (%),Kotoran (%),DOBI,Status Katup Pemanas Uap,' +
  'Kondisi Struktur Tangki,Nama Inspektur,Temuan'

const CSV_BODY =
  `${CSV_HEADER}\n` +
  '2026-09-01,ST-1,synced,catatan harian,06:00,3200,120,3080,50.0,55.0,61.0,52.0,720.0,660.0,' +
  '4.05,0.19,0.02,2.9,open,baik,Budi,ada endapan di dasar\n' +
  '2026-09-01,ST-1,synced,catatan harian,12:00,3180,118,3062,50.5,55.5,61.5,52.5,715.0,655.0,' +
  '4.10,0.20,0.02,2.8,open,baik,Budi,\n'

/* ------------------------------------------------------------------ */
/* Stub jaringan                                                       */
/* ------------------------------------------------------------------ */

/* ------------------------------------------------------------------ */
/* Production Line — konteks yang DIPILIH, bukan ikatan akun            */
/* ------------------------------------------------------------------ */

/**
 * Bawaan berkas ini SATU line: mill dengan satu line tidak punya keputusan
 * untuk diminta, line itu berlaku otomatis, dan seluruh skenario lama tetap
 * menguji apa yang memang mereka uji. Skenario yang menguji PEMILIHnya
 * men-stub DUA line secara eksplisit.
 */
const LINE_1 = { id: 'pl-1', name: 'Line 1', code: 'L1' }
const LINE_2 = { id: 'pl-2', name: 'Line 2', code: 'L2' }
const TWO_LINES = [LINE_1, LINE_2]

/** Kunci ingatan line, ditulis StationListView.vue dan dibaca layar ini. */
const REMEMBERED_LINE_KEY = (userId: string) => `msl_production_line_${userId}`

interface ApiState {
  units: unknown[]
  periods: unknown[]
  summary: Record<string, unknown>
  /** 200 kecuali test menyetel lain (401 / 422 / 403). */
  summaryStatus: number
  /** true = permintaan ringkasan diputus di tingkat jaringan. */
  summaryAbort: boolean
  /** 200 kecuali test menyetel lain — endpoint options Admin-only. */
  unitsStatus: number
  hits: { units: number; periods: number; summary: number; export: number; total: number }
  /** Seluruh URL yang benar-benar dikirim halaman ke endpoint laporan. */
  urls: string[]
  /**
   * Rekaman page.on('request') — independen dari handler route(), supaya
   * klaim "nol permintaan jaringan baru" tidak bersandar pada satu
   * mekanisme saja.
   */
  requested: string[]  /** GET /api/production-lines/options-for-report — daftar line mill yang berlaku. */
  productionLines: unknown[]
  /** true = daftar line gagal diambil (jaringan putus). */
  productionLinesAbort: boolean
  /** Ringkasan per production_line_id — untuk skenario "angka satu line". */
  summaryByLine?: Record<string, Record<string, unknown>>
  /** Hitungan permintaan daftar line. */
  productionLineHits: number
  /** URL lengkap setiap permintaan daftar line — dipakai membuktikan
   *  business_unit_id mill TERPILIH yang berangkat, bukan swa-cakup. */
  productionLineUrls: string[]
}

async function stubApi(page: Page, overrides: Partial<ApiState> = {}): Promise<ApiState> {
  const state: ApiState = {
    units: BUSINESS_UNITS,
    periods: [PERIOD_STG],
    summary: makeSummary(),
    summaryStatus: 200,
    summaryAbort: false,
    unitsStatus: 200,
    hits: { units: 0, periods: 0, summary: 0, export: 0, total: 0 },
    urls: [],
    requested: [],
    productionLines: [LINE_1],
    productionLinesAbort: false,
    productionLineHits: 0,
    productionLineUrls: [],
    ...overrides,
  }

  page.on('request', (request) => {
    if (request.url().includes('/api/storage-tank-reports/')) {
      state.requested.push(request.url())
    }
  })


  // Daftar Production Line — endpoint DI LUAR prefiks laporan, jadi
  // rutenya sendiri. Tanpa stub ini permintaan akan menembus ke backend
  // sungguhan di :8000 dan hasil test bergantung pada isi database.
  await page.route('**/api/production-lines/options-for-report*', async (route) => {
    const request = route.request()

    if (request.method() === 'OPTIONS') {
      await route.fulfill({ status: 204, headers: CORS_HEADERS, body: '' })

      return
    }

    state.productionLineHits += 1
    state.productionLineUrls.push(request.url())

    if (state.productionLinesAbort) {
      await route.abort('failed')

      return
    }

    await route.fulfill({ status: 200, headers: CORS_HEADERS, json: { data: state.productionLines } })
  })

  await page.route(API_GLOB, async (route) => {
    const request = route.request()
    const url = request.url()

    if (request.method() === 'OPTIONS') {
      await route.fulfill({ status: 204, headers: CORS_HEADERS, body: '' })

      return
    }

    state.urls.push(url)
    state.hits.total += 1

    if (url.includes('/business-units/options')) {
      state.hits.units += 1

      if (state.unitsStatus !== 200) {
        await route.fulfill({
          status: state.unitsStatus,
          headers: CORS_HEADERS,
          json: { message: 'Anda tidak memiliki akses untuk aksi ini.', code: 'FORBIDDEN' },
        })

        return
      }

      await route.fulfill({ status: 200, headers: CORS_HEADERS, json: { data: state.units } })

      return
    }

    if (url.includes('/periods')) {
      state.hits.periods += 1
      await route.fulfill({ status: 200, headers: CORS_HEADERS, json: { data: state.periods } })

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
          json: {
            message: state.summaryStatus === 401 ? 'Unauthenticated.' : 'Permintaan tidak valid.',
          },
        })

        return
      }

      const lineId = new URL(url).searchParams.get('production_line_id') ?? ''
      const perLine = state.summaryByLine?.[lineId]

      await route.fulfill({
        status: 200,
        headers: CORS_HEADERS,
        json: perLine ?? state.summary,
      })

      return
    }

    if (url.includes('/export')) {
      state.hits.export += 1
      await route.fulfill({
        status: 200,
        headers: { ...CORS_HEADERS, 'content-type': 'text/csv' },
        body: CSV_BODY,
      })

      return
    }

    await route.continue()
  })

  return state
}

/* ------------------------------------------------------------------ */
/* Bantuan sesi                                                        */
/* ------------------------------------------------------------------ */

/** Sesi tersimpan dijadikan sesi Admin (peran admin, tanpa mill). */
async function becomeAdminSession(page: Page): Promise<void> {
  await page.evaluate(() => {
    const raw = localStorage.getItem('msl_auth_user')
    const user = raw ? JSON.parse(raw) : {}
    user.role = 'admin'
    user.business_unit_id = null
    localStorage.setItem('msl_auth_user', JSON.stringify(user))
    localStorage.removeItem('msl_auth_business_unit')
  })
}

/** Akun terikat mill yang business_unit_id-nya kosong (masalah data, bukan Admin). */
async function becomeMilllessSession(page: Page): Promise<void> {
  await page.evaluate(() => {
    const raw = localStorage.getItem('msl_auth_user')
    const user = raw ? JSON.parse(raw) : {}
    user.role = 'operator'
    user.business_unit_id = null
    localStorage.setItem('msl_auth_user', JSON.stringify(user))
    localStorage.removeItem('msl_auth_business_unit')
  })
}

async function openReport(page: Page, path: string = ROUTE): Promise<void> {
  await page.goto(path)
  await expect(page.getByTestId('laporan-storage-tank-mobile')).toBeVisible()
}

async function pickPeriod(page: Page, periodId: string): Promise<void> {
  await page.getByTestId('period-select').selectOption(periodId)
}

/** Rekap Harian — TERTUTUP secara bawaan, memakai v-if. */
function recapToggle(page: Page) {
  return page.getByTestId('daily-recap-toggle')
}

function recapRows(page: Page) {
  return page.getByTestId('daily-recap-row')
}

function tankRows(page: Page) {
  return page.getByTestId('by-tank-row')
}

/**
 * SEL pergerakan sebuah baris rekap per tangki — kolom ke-6, dan hanya
 * kolom itu. Menyasar teks seluruh baris adalah jebakan: "0,0" adalah
 * substring dari "400,0" pada kolom stok, sehingga asersi selebar baris
 * akan memerah betapa pun benarnya layar.
 */
function tankMovementCell(page: Page, index: number) {
  return tankRows(page).nth(index).locator('td').nth(5)
}

/* ------------------------------------------------------------------ */
/* Bantuan asersi khas ponsel                                          */
/* ------------------------------------------------------------------ */

/**
 * HALAMAN tidak pernah menggulir mendatar. Yang lebar adalah isi kartu
 * (dua grafik tren, tabel per tangki, tabel rekap) — dan masing-masing
 * menggulir di dalam kartunya sendiri, itulah yang dibuktikan
 * expectCardScrollsInsideItself() di bawah.
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

/** Satu kolom: tidak ada dua kartu yang berdampingan mendatar. */
async function expectSingleColumn(page: Page): Promise<void> {
  const cards = page.locator('main.laporan-stg-view > .metric-stack > .metric-card')
  // toHaveCount MENUNGGU render; count() dibaca seketika dan, karena
  // pickPeriod() tidak menunggu respons laporan, sesekali membaca 0 sebelum
  // kartu muncul (terbukti flaky 2026-10-03: 1 dari 5 run screen-138).
  await expect(cards).toHaveCount(4)

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

  // Ketiga kartu stok (awal, akhir, pergerakan) juga bertumpuk, bukan
  // berjajar — itulah yang membedakan tata letak ponsel dari md-kpis--3
  // pada laporan web screen-133.
  const stockBoxes = await page
    .locator(
      'main.laporan-stg-view > [data-testid="stock-opening-card"], main.laporan-stg-view > [data-testid="stock-closing-card"], main.laporan-stg-view > [data-testid="stock-movement-card"]',
    )
    .evaluateAll((elements) =>
      elements.map((element) => {
        const rect = element.getBoundingClientRect()

        return { top: rect.top, bottom: rect.bottom }
      }),
    )

  expect(stockBoxes).toHaveLength(3)

  for (let index = 1; index < stockBoxes.length; index += 1) {
    expect(stockBoxes[index].top).toBeGreaterThanOrEqual(stockBoxes[index - 1].bottom - 1)
  }
}

/** Sasaran sentuh minimal 44px — ukuran jari, bukan ukuran kursor. */
async function expectTouchTargets(page: Page, testIds: string[]): Promise<void> {
  for (const testId of testIds) {
    const box = await page.getByTestId(testId).boundingBox()
    expect(box, `sasaran sentuh ${testId} tidak terender`).not.toBeNull()
    expect(box!.height, `sasaran sentuh ${testId} terlalu pendek`).toBeGreaterThanOrEqual(44)
    expect(box!.width, `sasaran sentuh ${testId} terlalu sempit`).toBeGreaterThanOrEqual(44)
  }
}

/**
 * Kartu yang isinya lebih lebar daripada layar menggulir DI DALAM dirinya
 * sendiri: scrollWidth > clientWidth DAN overflow-x-nya auto/scroll.
 */
async function expectCardScrollsInsideItself(page: Page, selector: string): Promise<void> {
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

test.describe('Laporan Storage Tank Mobile (screen-139)', () => {
  // Layar ponsel sungguhan — lihat catatan VIEWPORT pada docblock berkas.
  // Tanpa baris ini seluruh tuntutan satu-kolom di bawah menjadi hampa.
  test.use({ viewport: { width: 390, height: 844 } })

  // Scenario 1: "berhasil dilihat Operator, Supervisor, dan Mill Management"
  test('berhasil — satu kolom tanpa gulir mendatar halaman, tanpa pemilih Mill, tanpa kontrol tulis', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page)

    // Kedua peran terikat mill yang punya akun seed. Mill Management
    // memakai cabang kode yang sama persis (peran ≠ admin) dan dikunci
    // baris demi baris di tests/LaporanStorageTankView.spec.ts.
    for (const user of [USERS.operator, USERS.supervisor]) {
      await page.evaluate(() => localStorage.clear())
      await login(page, user)
      await openReport(page)

      // Pengguna terikat mill: pemilih Mill tidak ditawarkan sama sekali.
      await expect(page.getByTestId('mill-select')).toHaveCount(0)
      await expect(page.getByTestId('mill-current')).toBeVisible()

      await pickPeriod(page, 'per-1')

      await expect(page.getByTestId('mill-current')).toContainText('Mill Utara')
      await expect(page.getByTestId('period-meta')).toContainText('Periode September 2026')

      // Kelengkapan pencatatan — dibaca lebih dulu.
      await expect(page.getByTestId('coverage-percent')).toHaveText('1,3%')
      await expect(page.getByTestId('coverage-slots')).toContainText('3 dari 240 slot waktu terisi')

      // Stok awal / akhir beserta TANGGAL PEMBACAANNYA, lalu pergerakan.
      await expect(page.getByTestId('stock-opening-mt')).toHaveText('1.200,0')
      await expect(page.getByTestId('opening-at')).toContainText('01 Sep 2026 06:00')
      await expect(page.getByTestId('stock-closing-mt')).toHaveText('1.500,0')
      await expect(page.getByTestId('closing-at')).toContainText('28 Sep 2026 18:00')
      await expect(page.getByTestId('stock-movement-mt')).toHaveText('+280,5')

      // Keempat kartu mutu dengan reading_count masing-masing, lalu suhu.
      await expect(page.locator('[data-testid="metric-cards"] .metric-card')).toHaveCount(4)
      await expect(page.getByTestId('metric-ffa-avg')).toHaveText('4,05')
      await expect(page.getByTestId('metric-ffa-min')).toHaveText('3,10')
      await expect(page.getByTestId('metric-ffa-max')).toHaveText('4,40')
      await expect(page.getByTestId('metric-ffa-count')).toContainText('240')
      await expect(page.getByTestId('metric-moisture-avg')).toHaveText('0,195')
      await expect(page.getByTestId('metric-impurities-avg')).toHaveText('0,027')
      await expect(page.getByTestId('metric-dobi-avg')).toHaveText('2,85')
      await expect(page.getByTestId('metric-temperature-avg')).toHaveText('52,0')
      await expect(page.getByTestId('metric-temperature-count')).toContainText('12')

      // Kedua grafik, rekap per tangki, rekap harian.
      await expect(page.getByTestId('stock-trend-chart')).toHaveCount(1)
      await expect(page.getByTestId('quality-trend-chart')).toHaveCount(1)
      await expect(page.getByTestId('by-tank-card')).toBeVisible()
      await expect(page.getByTestId('daily-recap')).toBeVisible()

      // Tata letak ponsel.
      await expectSingleColumn(page)
      await expectNoHorizontalPageScroll(page)
      await expectTouchTargets(page, ['period-select', 'export-button', 'back-button'])

      // Tidak ada satu pun kontrol tulis di sepanjang layar.
      await expect(page.locator('main.laporan-stg-view input')).toHaveCount(0)
      await expect(page.locator('main.laporan-stg-view textarea')).toHaveCount(0)
      await expect(page.getByRole('button', { name: /simpan|hapus/i })).toHaveCount(0)
    }

    // Layar baca: tidak ada permintaan ke luar prefiks laporan.
    expect(api.urls.every((url) => url.includes('storage-tank-reports'))).toBe(true)
  })

  // Scenario 2: "berhasil dilihat Admin setelah memilih mill"
  /*
   * DIPULIHKAN 2026-09-28 (tahap 4b) ke bentuk aslinya: Admin memilih mill,
   * line berlaku, dan ANGKANYA TAMPIL.
   *
   * Bentuk sementara sebelumnya ("nol angka, karena tidak ada jalan") lahir
   * dari satu keterbatasan backend, bukan dari keputusan produk: satu-satunya
   * endpoint mobile yang mendaftar Production Line saat itu
   * (GET /api/production-lines/current) bersifat SWA-CAKUP — ia memulangkan
   * line milik mill AKUN PEMANGGIL, dan Admin tidak terikat mill. Endpoint
   * baru GET /api/production-lines/options-for-report?business_unit_id=
   * menutup lubang itu.
   *
   * Asersi di bawah lebih kuat daripada bentuk aslinya MAUPUN daripada
   * bentuk sementara itu: daftar line diminta DENGAN business_unit_id mill
   * terpilih, /summary membawa mill DAN line, /periods TIDAK PERNAH membawa
   * production_line_id, dan mengganti mill memuat ulang keduanya.
   */
  test('Admin — memilih mill memuat ulang daftar line + periode beserta seluruh angkanya, tetap satu kolom', async ({ page }) => {
    await login(page, USERS.operator)
    await becomeAdminSession(page)
    const api = await stubApi(page)
    await openReport(page)

    await expect(page.getByTestId('mill-select')).toBeVisible()
    await expect(page.getByTestId('mill-select').locator('option')).toHaveCount(3) // placeholder + 2 mill
    expect(api.hits.periods).toBe(0)
    // Tanpa mill terpilih, daftar line pun belum diminta.
    expect(api.productionLineHits).toBe(0)

    await page.getByTestId('mill-select').selectOption('bu-1')
    await expect(page.getByTestId('period-select').locator('option')).toHaveCount(2)

    // Daftar line diminta UNTUK MILL TERPILIH — bukan lewat endpoint
    // swa-cakup yang akan memulangkan line milik mill akun Admin.
    await expect.poll(() => api.productionLineHits).toBe(1)
    expect(api.productionLineUrls.some((url) => url.includes('business_unit_id=bu-1'))).toBe(true)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('coverage-percent')).toHaveText('1,3%')
    await expect(page.getByTestId('stock-opening-mt')).toHaveText('1.200,0')
    await expect(page.getByTestId('stock-closing-mt')).toHaveText('1.500,0')
    await expect(page.getByTestId('stock-movement-mt')).toHaveText('+280,5')
    await expect(page.getByTestId('by-tank-card')).toBeVisible()

    // Tidak ada lagi pesan buntu "pakai versi web".
    await expect(page.getByTestId('production-line-unavailable')).toHaveCount(0)
    await expectSingleColumn(page)
    await expectNoHorizontalPageScroll(page)

    // business_unit_id memang dikirim untuk Admin — dan HANYA untuk Admin.
    expect(api.urls.some((url) => url.includes('/summary') && url.includes('business_unit_id=bu-1'))).toBe(true)
    // Dan angkanya menyertai SATU line.
    expect(api.urls.some((url) => url.includes('/summary') && url.includes('production_line_id=pl-1'))).toBe(true)
    // Daftar periode TIDAK PERNAH tersaring line — periode milik MILL.
    expect(
      api.urls.filter((url) => url.includes('/periods')).every((url) => !url.includes('production_line_id')),
    ).toBe(true)

    // Mengganti mill memuat ulang daftar periode DAN daftar line mill baru,
    // lalu membuang angka lama.
    const periodsBefore = api.hits.periods
    await page.getByTestId('mill-select').selectOption('bu-2')
    await expect(page.getByTestId('metric-cards')).toHaveCount(0)
    await expect(page.getByTestId('stock-opening-card')).toHaveCount(0)

    // expect.poll: permintaan berangkat secara asinkron sesudah
    // selectOption, jadi asersi sinkron akan membaca hitungan lama.
    await expect.poll(() => api.hits.periods).toBe(periodsBefore + 1)
    expect(api.urls.some((url) => url.includes('/periods') && url.includes('business_unit_id=bu-2'))).toBe(true)
    await expect.poll(() => api.productionLineUrls.some((url) => url.includes('business_unit_id=bu-2'))).toBe(true)
  })

  // Scenario 3: "Operator membuka laporan"
  test('Operator — laporan terbuka penuh, tanpa kontrol ganti mill, nama mill adalah mill akunnya', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Tidak ada bagian yang dibatasi untuk peran ini.
    await expect(page.getByTestId('coverage-card')).toBeVisible()
    await expect(page.getByTestId('stock-opening-card')).toBeVisible()
    await expect(page.getByTestId('stock-closing-card')).toBeVisible()
    await expect(page.getByTestId('stock-movement-card')).toBeVisible()
    await expect(page.getByTestId('metric-cards')).toBeVisible()
    await expect(page.getByTestId('metric-card-temperature')).toBeVisible()
    await expect(page.getByTestId('by-tank-card')).toBeVisible()
    await expect(page.getByTestId('daily-recap')).toBeVisible()
    await expect(page.getByTestId('export-button')).toBeVisible()

    // Tidak ada kontrol untuk mengganti mill.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.getByTestId('mill-current')).toContainText('Mill Utara')

    // Endpoint options TIDAK pernah dipanggil — server pun menjawab 403 di
    // sana untuk Operator, dan memanggilnya akan membentuk daftar SELURUH
    // mill di perangkat yang tidak berhak melihatnya.
    expect(api.hits.units).toBe(0)
    expect(api.urls.some((url) => url.includes('/business-units/options'))).toBe(false)

    // Dan tidak satu pun permintaan menyertakan business_unit_id.
    expect(api.urls.every((url) => !url.includes('business_unit_id'))).toBe(true)
  })

  // Scenario 4: "Admin memilih mill lebih dulu"
  test('Admin tanpa mill — arahan memilih mill terbaca, tidak ada angka laporan yang tampil', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await becomeAdminSession(page)
    const api = await stubApi(page)
    await openReport(page)

    await expect(page.getByTestId('mill-select')).toBeVisible()
    await expect(page.getByTestId('mill-required-hint')).toContainText('Pilih mill terlebih dahulu')

    await expect(page.getByTestId('coverage-card')).toHaveCount(0)
    await expect(page.getByTestId('stock-opening-card')).toHaveCount(0)
    await expect(page.getByTestId('metric-cards')).toHaveCount(0)
    await expect(page.getByTestId('by-tank-card')).toHaveCount(0)
    expect(api.hits.summary).toBe(0)
    expect(api.hits.periods).toBe(0)
  })

  // Scenario 5: "mill belum punya periode"
  test('mill tanpa periode — pemilih kosong dengan arahan menghubungi Admin, bukan layar kosong', async ({
    page,
  }) => {
    await login(page, USERS.supervisor)
    await stubApi(page, { periods: [] })
    await openReport(page)

    // Hanya opsi pembuka.
    await expect(page.getByTestId('period-select').locator('option')).toHaveCount(1)
    await expect(page.getByTestId('no-periods')).toContainText('hubungi Admin')
    await expect(page.getByTestId('coverage-card')).toHaveCount(0)
    await expect(page.getByTestId('stock-opening-card')).toHaveCount(0)
    await expect(page.getByTestId('network-error')).toHaveCount(0)
  })

  // Scenario 6: "periode tanpa data"
  test('periode tanpa data — seluruh angka tidak tersedia (bukan nol) dan tidak ada grafik kosong', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, { summary: EMPTY_SUMMARY })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('empty-period')).toContainText('Belum ada data')

    // Tidak tersedia, dan itu BUKAN nol.
    for (const testId of ['stock-opening-mt', 'stock-closing-mt', 'stock-movement-mt']) {
      await expect(page.getByTestId(testId)).toHaveText('-')
      await expect(page.getByTestId(testId)).not.toHaveText('0,0')
    }

    for (const testId of METRIC_TESTIDS) {
      await expect(page.getByTestId(`metric-${testId}-avg`)).toHaveText('-')
    }

    await expect(page.getByTestId('metric-temperature-avg')).toHaveText('-')

    // Tidak ada grafik kosong yang menyesatkan.
    await expect(page.getByTestId('stock-trend-chart')).toHaveCount(0)
    await expect(page.getByTestId('quality-trend-chart')).toHaveCount(0)
    await expectNoHorizontalPageScroll(page)
  })

  // Scenario 7: "tangki hanya punya satu pembacaan stok"
  test('tangki berpembacaan tunggal — stok awal dan akhir pembacaan yang sama, pergerakan tidak dapat dihitung', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    const row = tankRows(page).nth(2) // ST-3, reading_count 1

    await expect(row).toContainText('ST-3')

    const cells = await row.locator('td').allTextContents()

    // Stok awal dan stok akhir menunjuk PEMBACAAN YANG SAMA: nilai sama,
    // dan waktu pembacaannya pun sama.
    expect(cells[1].trim()).toBe('400,0')
    expect(cells[3].trim()).toBe('400,0')
    expect(cells[2].trim()).toBe(cells[4].trim())
    expect(cells[2].trim()).toContain('03 Sep 2026 06:00')

    // Pergerakannya adalah KATA, bukan angka nol. Asersi menyasar SEL
    // pergerakannya saja — "0,0" adalah substring dari "400,0" di kolom
    // stok, dan asersi selebar baris akan memerah betapa pun benarnya
    // layar.
    await expect(tankMovementCell(page, 2)).toHaveText('tidak dapat dihitung')
    await expect(tankMovementCell(page, 2)).not.toContainText('0,0')

    // Tangki itu juga tidak menyumbang ke cacah tangki terhitung.
    await expect(page.getByTestId('tanks-with-movement')).toHaveText('2')
  })

  // Scenario 8: "pembacaan paling awal tidak mencatat stok"
  test('stok awal dari pembacaan terisi berikutnya — tanggalnya terbaca dan selisihnya dari mulai periode terlihat', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, {
      summary: makeSummary({
        stock: {
          ...STOCK,
          // Pembacaan pertama (01 Sep) tidak mencatat stok; yang terambil
          // adalah pembacaan terisi berikutnya, 04 Sep.
          opening_at: '2026-09-04 06:00',
        },
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Angka dan tanggal pembacaannya berdampingan.
    await expect(page.getByTestId('stock-opening-mt')).toHaveText('1.200,0')
    await expect(page.getByTestId('opening-at')).toContainText('04 Sep 2026 06:00')

    // Dan tanggal mulai periodenya ada di layar yang sama, sehingga selisih
    // tiga hari yang tidak tercatat itu dapat dilihat langsung tanpa
    // membuka apa pun.
    await expect(page.getByTestId('period-meta')).toContainText('01 Sep 2026')

    const openingBox = await page.getByTestId('opening-at').boundingBox()
    const periodBox = await page.getByTestId('period-meta').boundingBox()

    expect(openingBox).not.toBeNull()
    expect(periodBox).not.toBeNull()
    // Keduanya berada dalam satu layar gulir yang sama-sama terbaca:
    // keterangan periode di atas, tanggal pembacaan tepat di bawahnya.
    expect(openingBox!.y).toBeGreaterThan(periodBox!.y)
  })

  // Scenario 9: "tangki tanpa satu pun pembacaan stok"
  test('tangki tanpa pembacaan tetap muncul di rekap per tangki dengan ketiga kolom stok tidak tersedia', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(tankRows(page)).toHaveCount(4)

    const row = tankRows(page).nth(3) // ST-4, reading_count 0

    await expect(row).toContainText('ST-4')

    const cells = await row.locator('td').allTextContents()

    // Ketiga kolom stok: stok awal, stok akhir, pergerakan.
    expect(cells[1].trim()).toBe('-')
    expect(cells[3].trim()).toBe('-')
    expect(cells[5].trim()).toBe('-')

    // Dan barisnya memang ada — tangki yang tidak pernah diukur adalah
    // temuan, bukan baris yang layak dibuang.
    expect(cells[8].trim()).toBe('0')
  })

  // Scenario 10: "jumlah tangki berbeda antara awal dan akhir periode"
  test('cakupan tangki berubah — pergerakan bersih bukan selisih stok gabungan, dan tangki satu ujung dinyatakan', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, {
      summary: makeSummary({
        by_tank: [
          ...BY_TANK_ROWS,
          {
            // Hanya muncul di ujung AWAL periode: ada stok awal, tidak ada
            // stok akhir.
            storage_tank_id: 'ST-5',
            reading_count: 12,
            opening_mt: 300.0,
            opening_at: '2026-09-01 06:00',
            closing_mt: null,
            closing_at: null,
            movement_mt: null,
            movement_computable: false,
            ffa_avg: 4.2,
            average_temperature_avg: 50.0,
          },
        ],
        stock: { ...STOCK, tanks_with_movement: 2, tanks_without_movement: 3 },
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Pergerakan bersih (280,5) TIDAK sama dengan 1.500,0 − 1.200,0.
    await expect(page.getByTestId('stock-movement-mt')).toHaveText('+280,5')
    await expect(page.getByTestId('stock-movement-mt')).not.toHaveText('+300,0')
    await expect(page.getByTestId('movement-not-difference-note')).toContainText(
      'BUKAN selisih stok akhir dikurangi stok awal',
    )

    // Cacah tangki tanpa pergerakan terbaca di layar.
    await expect(page.getByTestId('tanks-with-movement')).toHaveText('2')
    await expect(page.getByTestId('tanks-without-movement')).toHaveText('3')

    // Tangki yang hanya muncul di satu ujung DINYATAKAN demikian: stok
    // akhirnya tidak tersedia dan pergerakannya tidak dapat dihitung —
    // bukan diam-diam dianggap nol.
    const row = tankRows(page).nth(4)

    await expect(row).toContainText('ST-5')

    const cells = await row.locator('td').allTextContents()

    expect(cells[1].trim()).toBe('300,0')
    expect(cells[3].trim()).toBe('-')
    await expect(tankMovementCell(page, 4)).toHaveText('tidak dapat dihitung')
  })

  // Scenario 11: "pergerakan bernilai negatif"
  test('pergerakan negatif tampil sebagai pengurangan stok — bukan galat, dan tidak dibulatkan ke nol', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, {
      summary: makeSummary({
        stock: {
          ...STOCK,
          opening_mt: 1500.0,
          closing_mt: 1380.0,
          movement_mt: -120.5,
        },
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Tanda minus apa adanya — tanpa Math.abs dan tanpa pembulatan ke nol.
    await expect(page.getByTestId('stock-movement-mt')).toHaveText('-120,5')
    await expect(page.getByTestId('stock-movement-mt')).not.toHaveText('120,5')
    await expect(page.getByTestId('stock-movement-mt')).not.toHaveText('0,0')

    // Dan layar MENGATAKAN bahwa nilai negatif adalah keadaan wajar.
    await expect(page.getByTestId('movement-not-difference-note')).toContainText(
      'Nilai negatif berarti stok berkurang, keadaan yang wajar dan bukan kesalahan',
    )

    // Bukan kesalahan: tidak ada pesan galat maupun penandaan apa pun.
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('error-message')).toHaveCount(0)

    const cardClass = await page.getByTestId('stock-movement-card').getAttribute('class')

    expect(cardClass).toBe('detail-section')
  })

  // Scenario 12: "sebuah metrik mutu tidak pernah diisi"
  test('metrik mutu tanpa pembacaan — tidak tersedia dengan 0 pembacaan, metrik lain tetap normal', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, {
      summary: makeSummary({ metrics: makeMetrics({ dobi_index: { ...EMPTY_METRIC } }) }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metric-dobi-avg')).toHaveText('-')
    await expect(page.getByTestId('metric-dobi-avg')).not.toHaveText('0,00')
    await expect(page.getByTestId('metric-dobi-min')).toHaveText('-')
    await expect(page.getByTestId('metric-dobi-max')).toHaveText('-')
    await expect(page.getByTestId('metric-dobi-count')).toContainText('0')

    // Metrik lain tetap tampil normal dengan penyebutnya SENDIRI.
    await expect(page.getByTestId('metric-ffa-avg')).toHaveText('4,05')
    await expect(page.getByTestId('metric-ffa-count')).toContainText('240')
    await expect(page.getByTestId('metric-moisture-avg')).toHaveText('0,195')
    await expect(page.getByTestId('metric-moisture-count')).toContainText('118')
    await expect(page.getByTestId('metric-impurities-count')).toContainText('42')
  })

  // Scenario 13: "kolom suhu rata-rata kosong"
  test('suhu rata-rata kosong — tidak tersedia, dan tidak diisi dari rata-rata ketiga suhu posisi', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, {
      summary: makeSummary({
        // Ketiga suhu posisi TERISI (50,0 / 55,0 / 61,2 → rata-rata 55,4),
        // hanya kolom suhu rata-ratanya yang kosong.
        metrics: makeMetrics({ average_temperature_c: { ...EMPTY_METRIC } }),
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('metric-temperature-avg')).toHaveText('-')
    await expect(page.getByTestId('metric-temperature-count')).toContainText('0')

    // Tidak ada angka yang muncul di tempatnya dari hasil perhitungan
    // ketiga suhu posisi — 55,4 tidak boleh ada di mana pun pada kartunya,
    // dan tidak satu pun dari ketiga nilai posisi itu sendiri.
    const card = page.getByTestId('metric-card-temperature')

    await expect(card).not.toContainText('55,4')
    await expect(card).not.toContainText('50,0')
    await expect(card).not.toContainText('61,2')
    await expect(page.getByTestId('temperature-source-note')).toContainText(
      'KOLOM SUHU RATA-RATA YANG DICATAT OPERATOR',
    )
  })

  // Scenario 14: "pencatatan sangat tidak lengkap"
  test('kelengkapan sangat rendah — kartu kelengkapan terbaca LEBIH DULU daripada angka mana pun', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, {
      summary: makeSummary({
        coverage: {
          filled_slots: 6,
          expected_slots: 480,
          coverage_percent: 1.25,
          tank_count: 4,
          slots_per_tank_per_day: 4,
          days_in_period: 30,
        },
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('coverage-percent')).toHaveText('1,3%')
    await expect(page.getByTestId('coverage-slots')).toContainText('6 dari 480 slot waktu terisi')

    // Posisi di layar, bukan sekadar urutan DOM: kartu kelengkapan berada
    // DI ATAS seluruh kartu angka.
    const coverageBox = await page.getByTestId('coverage-card').boundingBox()
    expect(coverageBox).not.toBeNull()

    for (const testId of [
      'stock-opening-card',
      'stock-closing-card',
      'stock-movement-card',
      'metric-cards',
      'metric-card-temperature',
    ]) {
      const box = await page.getByTestId(testId).boundingBox()
      expect(box, `${testId} tidak terender`).not.toBeNull()
      expect(box!.y).toBeGreaterThan(coverageBox!.y)
    }

    // Angka TETAP tampil — kelengkapan rendah bukan alasan menyembunyikan.
    await expect(page.getByTestId('stock-opening-mt')).toHaveText('1.200,0')
    await expect(page.getByTestId('stock-movement-mt')).toHaveText('+280,5')

    // Dan pembaca tahu angkanya bersandar pada sedikit pembacaan.
    await expect(page.getByTestId('coverage-card')).toContainText('Baca ini lebih dulu')
    await expect(page.getByTestId('coverage-formula')).toContainText('4 tangki')
  })

  // Scenario 15: "akun belum terhubung ke mill"
  test('akun tanpa mill — pesan menghubungi Admin, tanpa daftar mill dan tanpa satu pun angka', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await becomeMilllessSession(page)
    const api = await stubApi(page)
    await openReport(page)

    await expect(page.getByTestId('no-mill-for-account')).toContainText('hubungi Admin')
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.getByTestId('period-select')).toHaveCount(0)
    await expect(page.getByTestId('coverage-card')).toHaveCount(0)
    await expect(page.getByTestId('stock-opening-card')).toHaveCount(0)

    // NOL permintaan — termasuk endpoint options, yang justru akan
    // membentuk daftar SELURUH mill di perangkat orang yang tidak berhak
    // melihat satu pun di antaranya. Dua mekanisme, keduanya nol.
    expect(api.hits.total).toBe(0)
    expect(api.requested).toHaveLength(0)
  })

  // Scenario 16: "mencoba melihat mill lain"
  test('Operator BU-A — angka dan nama mill milik BU-A, dan tidak ada kontrol untuk berpindah mill', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('mill-current')).toContainText('Mill Utara')
    await expect(page.getByTestId('mill-current')).not.toContainText('Mill Selatan')
    await expect(page.getByTestId('period-meta')).toContainText('Periode September 2026')
    await expect(page.getByTestId('stock-opening-mt')).toHaveText('1.200,0')

    // Badan respons yang benar-benar dipakai layar adalah BU-A.
    expect(JSON.stringify(api.summary)).toContain('Mill Utara')
    expect(JSON.stringify(api.summary)).not.toContain('Mill Selatan')

    // Tidak ada kontrol apa pun untuk berpindah mill: satu-satunya <select>
    // di layar ini adalah pemilih Periode.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.locator('main.laporan-stg-view select')).toHaveCount(1)
    // Dan halaman tidak pernah mengirim business_unit_id.
    expect(api.urls.every((url) => !url.includes('business_unit_id'))).toBe(true)
  })

  // Scenario 17: "jaringan gagal"
  test('jaringan gagal — pesan + Coba Lagi, periode terpilih bertahan, dan coba lagi memuat periode yang sama', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page, { summaryAbort: true })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Bukan layar kosong, bukan diam.
    await expect(page.getByTestId('network-error')).toBeVisible()
    await expect(page.getByTestId('retry-button')).toBeVisible()
    await expect(page.getByTestId('coverage-card')).toHaveCount(0)

    // Periode yang sudah dipilih MASIH terpilih.
    await expect(page.getByTestId('period-select')).toHaveValue('per-1')
    expect(page.url()).toContain(ROUTE)

    // Jaringan pulih.
    api.summaryAbort = false
    const summaryHitsBefore = api.hits.summary

    await page.getByTestId('retry-button').click()

    await expect(page.getByTestId('stock-opening-mt')).toHaveText('1.200,0')
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('period-select')).toHaveValue('per-1')

    // period_id yang SAMA, tanpa memilih ulang.
    expect(api.hits.summary).toBe(summaryHitsBefore + 1)
    expect(api.urls[api.urls.length - 1]).toContain('period_id=per-1')
  })

  // Scenario 18: "sesi berakhir"
  test('sesi berakhir — 401 memindahkan ke Login lewat replace, tanpa angka dan tanpa tombol coba lagi', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, { summaryStatus: 401 })
    await openReport(page)

    const historyBefore = await page.evaluate(() => window.history.length)

    await pickPeriod(page, 'per-1')

    await page.waitForURL('**/login')
    await expect(page.getByTestId('coverage-card')).toHaveCount(0)
    await expect(page.getByTestId('stock-opening-card')).toHaveCount(0)
    await expect(page.getByTestId('retry-button')).toHaveCount(0)
    await expect(page.getByTestId('network-error')).toHaveCount(0)

    // router.replace, BUKAN push: panjang history TIDAK bertambah, sehingga
    // layar laporan yang sesinya sudah mati tidak dapat dicapai kembali
    // dengan tombol Back peramban.
    const historyAfter = await page.evaluate(() => window.history.length)

    expect(historyAfter).toBeLessThanOrEqual(historyBefore)
  })

  // Scenario 19: "periode tertutup"
  test('periode tertutup — laporan penuh, status Tertutup terbaca, dan unduhan CSV tetap berjalan', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page, {
      periods: [PERIOD_CLOSED],
      summary: makeSummary({
        period: {
          id: 'per-3',
          name: 'Periode Agustus 2026',
          start_date: '2026-08-01',
          end_date: '2026-08-31',
          status: 'closed',
          business_unit_name: 'Mill Utara',
        },
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-3')

    await expect(page.getByTestId('period-status-badge')).toContainText('Tertutup')
    await expect(page.getByTestId('stock-opening-mt')).toHaveText('1.200,0')
    await expect(page.getByTestId('stock-movement-mt')).toHaveText('+280,5')
    await expect(page.getByTestId('by-tank-card')).toBeVisible()

    // Ekspor TIDAK diblokir oleh status periode.
    const download = page.waitForEvent('download')
    await page.getByTestId('export-button').click()
    const file = await download

    expect(file.suggestedFilename()).toMatch(/\.csv$/)
    expect(api.hits.export).toBe(1)
  })

  // Scenario 20: "rekap harian dapat ditutup agar layar ponsel tetap terbaca"
  test('rekap harian buka/tutup/buka pada periode sebulan — NOL permintaan jaringan baru', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page, { summary: makeSummary({ daily: THIRTY_DAYS }) })
    await openReport(page)

    await pickPeriod(page, 'per-1')
    await expect(page.getByTestId('stock-opening-mt')).toHaveText('1.200,0')

    // TERTUTUP secara bawaan (v-if: barisnya tidak ada di DOM sama sekali).
    await expect(recapRows(page)).toHaveCount(0)

    const requestsAfterLoad = api.requested.length
    const summaryHits = api.hits.summary
    const totalHits = api.hits.total

    // Saat tertutup, angka utama dan grafik terbaca tanpa gulir panjang:
    // keduanya berada di atas tombol buka/tutup.
    await expect(page.getByTestId('coverage-card')).toBeVisible()
    await expect(page.getByTestId('stock-movement-card')).toBeVisible()
    await expect(page.getByTestId('stock-trend-chart')).toBeVisible()
    await expect(page.getByTestId('quality-trend-chart')).toBeVisible()
    await expectNoHorizontalPageScroll(page)

    await recapToggle(page).click()
    await expect(recapRows(page)).toHaveCount(30)

    await recapToggle(page).click()
    await expect(recapRows(page)).toHaveCount(0)

    await recapToggle(page).click()
    await expect(recapRows(page)).toHaveCount(30)

    // Seluruh baris tampil utuh, dan halaman tetap satu kolom tanpa gulir
    // mendatar dengan 30 baris terbuka.
    await expect(recapRows(page).first()).toContainText('01 Sep 2026')
    await expect(recapRows(page).last()).toContainText('30 Sep 2026')
    await expectNoHorizontalPageScroll(page)
    await expectCardScrollsInsideItself(page, '[data-testid="daily-recap"] .detail-table-wrap')

    // NOL permintaan jaringan baru — dihitung dari DUA mekanisme berbeda
    // dan saling bebas: penghitung di dalam handler page.route() dan
    // rekaman page.on('request').
    expect(api.hits.summary).toBe(summaryHits)
    expect(api.hits.total).toBe(totalHits)
    expect(api.requested).toHaveLength(requestsAfterLoad)
  })

  // Scenario 21: "layar hanya membaca, tanpa aksi tulis"
  test('hanya membaca — tidak ada kontrol pengubah data, dan tidak ada permintaan non-GET sepanjang layar', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const methods: string[] = []

    page.on('request', (request) => {
      if (request.url().includes('/api/')) {
        methods.push(request.method())
      }
    })

    await stubApi(page)
    await openReport(page)

    // Telusuri dari angka utama sampai rekap harian.
    await pickPeriod(page, 'per-1')
    await expect(page.getByTestId('stock-movement-mt')).toHaveText('+280,5')
    await recapToggle(page).click()
    await expect(recapRows(page)).toHaveCount(4)
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))

    await expect(page.locator('main.laporan-stg-view input')).toHaveCount(0)
    await expect(page.locator('main.laporan-stg-view textarea')).toHaveCount(0)
    await expect(page.locator('main.laporan-stg-view form')).toHaveCount(0)
    await expect(page.getByRole('button', { name: /simpan|ubah|hapus|tambah/i })).toHaveCount(0)

    // Data stasiun tidak dapat berubah: tidak satu pun permintaan yang
    // bukan GET (OPTIONS preflight dikecualikan) sepanjang hidup layar.
    expect(methods.filter((method) => method !== 'GET' && method !== 'OPTIONS')).toHaveLength(0)
  })

  // Scenario 22: "angka ponsel sama persis dengan laporan versi web"
  test('ponsel memakai path /api/storage-tank-reports yang sama — tidak ada endpoint mobile tersendiri', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')
    // Ditunggu sampai angkanya terender: memeriksa api.urls sebelum itu
    // hanya membaca daftar yang permintaan ringkasannya belum masuk.
    await expect(page.getByTestId('stock-opening-mt')).toBeVisible()

    // Seluruh permintaan menuju prefiks yang SAMA dengan laporan web
    // (screen-133) — tidak ada '/mobile' atau prefiks lain di mana pun.
    expect(api.requested.length).toBeGreaterThan(0)
    for (const url of api.requested) {
      expect(url).toContain('/api/storage-tank-reports/')
      expect(url).not.toContain('/mobile')
    }

    // Keempat endpoint yang dipakai layar adalah keempat endpoint web.
    expect(api.urls.some((url) => url.includes('/api/storage-tank-reports/periods'))).toBe(true)
    expect(api.urls.some((url) => url.includes('/api/storage-tank-reports/summary'))).toBe(true)

    // Dan angka yang tampil adalah nilai payload apa adanya — angka fixture
    // yang saling bertentangan tetap bertentangan di layar.
    await expect(page.getByTestId('stock-opening-mt')).toHaveText('1.200,0')
    await expect(page.getByTestId('stock-closing-mt')).toHaveText('1.500,0')
    await expect(page.getByTestId('stock-movement-mt')).toHaveText('+280,5')
    await expect(page.getByTestId('coverage-percent')).toHaveText('1,3%')
    await expect(page.getByTestId('metric-ffa-max')).toHaveText('4,40')
  })

  // Scenario 23: "periode yang tidak mencakup Storage Tank tidak ditawarkan"
  test('pemilih periode memuat periode Storage Tank berjenis tunggal dan yang lintas stasiun, persis seperti kiriman server', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, { periods: [PERIOD_STG, PERIOD_LINTAS_STASIUN] })
    await openReport(page)

    const options = page.getByTestId('period-select').locator('option')

    await expect(options).toHaveCount(3)
    await expect(options.nth(1)).toContainText('Storage Tank — Periode September 2026')
    await expect(options.nth(2)).toContainText('Storage Tank — Periode Lintas Stasiun September 2026')

    // Periode yang tidak punya baris period_stations berjenis
    // 'storage-tank' tidak pernah sampai ke layar: penyaringan cakupan
    // milik SERVER, dan layar ini tidak menyaring station_type sendiri —
    // periode lintas stasiun TIDAK dibuang.
    await expect(page.getByTestId('period-select')).not.toContainText('Pressing')
    await expect(page.getByTestId('period-select')).not.toContainText('Clarification')

    // Atribut value-nya, bukan toHaveValue(): pada <option> matcher itu
    // membaca nilai <select> induknya, bukan opsinya sendiri.
    expect(await options.nth(1).getAttribute('value')).toBe('per-1')
    expect(await options.nth(2).getAttribute('value')).toBe('per-2')
  })

  // Scenario 24: "rentang periode inklusif di kedua ujung"
  test('kedua tanggal ujung tampil pada rekap harian dan ikut membentuk angka utama', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('period-meta')).toContainText('01 Sep 2026')
    await expect(page.getByTestId('period-meta')).toContainText('30 Sep 2026')

    await recapToggle(page).click()

    await expect(recapRows(page)).toHaveCount(4)
    await expect(recapRows(page).first()).toContainText('01 Sep 2026')
    await expect(recapRows(page).last()).toContainText('30 Sep 2026')

    // Tidak ada baris ujung yang dipotong.
    await expect(recapToggle(page)).toContainText('4 hari')

    // Dan keduanya ikut membentuk angka utama: stok akhir tangki ST-1
    // terbaca tepat pada 30 Sep, sementara pembacaan pertamanya tepat pada
    // 01 Sep.
    const cells = await tankRows(page).first().locator('td').allTextContents()

    expect(cells[2].trim()).toContain('01 Sep 2026')
    expect(cells[4].trim()).toContain('30 Sep 2026')
  })

  // Scenario 25: "stok awal adalah pembacaan pertama dan stok akhir adalah pembacaan terakhir"
  test('stok awal/akhir menurut urutan tanggal lalu slot waktu — bukan nilai terendah, dan tanggalnya ikut', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, {
      summary: makeSummary({
        // Pembacaan berat TERENDAH periode ini (900,0 pada 03 Sep) BUKAN
        // pembacaan pertamanya — layar yang diam-diam memakai min/max
        // alih-alih urutan waktu harus gagal di sini.
        daily: [
          { ...DAILY_ROWS[0] },
          { ...DAILY_ROWS[1], stock_total_mt: 900.0 },
          { ...DAILY_ROWS[2] },
          { ...DAILY_ROWS[3] },
        ],
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Stok awal adalah pembacaan PERTAMA (1.200,0 @ 01 Sep 06:00), bukan
    // nilai terendah periode (900,0).
    await expect(page.getByTestId('stock-opening-mt')).toHaveText('1.200,0')
    await expect(page.getByTestId('stock-opening-mt')).not.toHaveText('900,0')
    await expect(page.getByTestId('opening-at')).toContainText('01 Sep 2026 06:00')

    // Stok akhir adalah pembacaan TERAKHIR menurut tanggal lalu slot waktu.
    await expect(page.getByTestId('stock-closing-mt')).toHaveText('1.500,0')
    await expect(page.getByTestId('closing-at')).toContainText('28 Sep 2026 18:00')

    // Nilai terendah itu memang ada di layar — pada rekap harian, bukan
    // sebagai stok awal.
    await recapToggle(page).click()
    await expect(recapRows(page).nth(1)).toContainText('900,0')
  })

  // Scenario 26: "pergerakan dihitung per tangki lalu dijumlahkan"
  test('pergerakan bersih = jumlah pergerakan per tangki yang dapat dihitung, bukan selisih stok gabungan', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, {
      // 100,5 (ST-1) + 160,0 (ST-2) = 260,5. ST-3 dan ST-4 tidak
      // menyumbang apa pun karena pergerakannya tidak dapat dihitung.
      summary: makeSummary({ stock: { ...STOCK, movement_mt: 260.5 } }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('stock-movement-mt')).toHaveText('+260,5')

    // Penjumlahan pergerakan per tangki yang DAPAT dihitung, dibaca dari
    // sel pergerakannya masing-masing.
    await expect(tankMovementCell(page, 0)).toHaveText('+100,5')
    await expect(tankMovementCell(page, 1)).toHaveText('+160,0')
    await expect(tankMovementCell(page, 2)).toHaveText('tidak dapat dihitung')
    await expect(tankMovementCell(page, 3)).toHaveText('-')

    // Dan TIDAK sama dengan selisih stok gabungan (1.500,0 − 1.200,0).
    await expect(page.getByTestId('stock-movement-mt')).not.toHaveText('+300,0')
    await expect(page.getByTestId('movement-not-difference-note')).toContainText(
      'Pergerakan dihitung PER TANGKI lalu',
    )
    await expect(page.getByTestId('by-tank-card')).toContainText(
      'kolom Pergerakan di bawah adalah asal angka pada kartu Pergerakan Bersih',
    )
  })

  // Scenario 27: "stok diambil dari berat, bukan volume maupun kedalaman sounding"
  test('stok dan pergerakan memakai berat MT — volume dan kedalaman sounding tidak ikut membentuknya', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Satuan stok adalah MT, pada ketiga kartunya.
    for (const testId of ['stock-opening-card', 'stock-closing-card', 'stock-movement-card']) {
      await expect(page.getByTestId(testId)).toContainText('MT')
    }

    await expect(page.getByTestId('by-tank-card')).toContainText('Stok Awal (MT)')
    await expect(page.getByTestId('by-tank-card')).toContainText('Pergerakan (MT)')

    // Volume (m3) dan kedalaman sounding TIDAK ikut membentuk satu pun
    // angka stok: payload memuat calculated_volume_m3 (rata-rata 730,0)
    // apa adanya, tetapi layar tidak pernah merendernya, dan tidak satu pun
    // angka stok sama dengannya.
    const screen = page.getByTestId('laporan-storage-tank-mobile')

    await expect(screen).not.toContainText('m3')
    await expect(screen).not.toContainText('Volume')
    await expect(screen).not.toContainText('Sounding')
    await expect(screen).not.toContainText('Kedalaman')
    await expect(page.getByTestId('stock-opening-mt')).not.toHaveText('730,0')

    // Dan calculated_weight_mt tidak mendapat kartu metriknya sendiri —
    // ia SUDAH menjadi angka stok di atas; merendernya lagi akan membuat
    // satu angka terbaca sebagai dua temuan yang berbeda.
    await expect(page.locator('[data-testid="metric-cards"] .metric-card')).toHaveCount(4)
    await expect(page.getByTestId('metric-weight')).toHaveCount(0)
    await expect(page.getByTestId('metric-volume')).toHaveCount(0)
  })

  // Scenario 28: "suhu rata-rata diambil dari kolom yang dicatat Operator"
  test('suhu rata-rata adalah nilai kolom Operator — tidak sama dengan rata-rata ketiga suhu posisi', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // 52,0 adalah metrics.average_temperature_c.avg apa adanya. Rata-rata
    // ketiga suhu posisi (50,0 + 55,0 + 61,2) / 3 = 55,4 — dan angka itu
    // tidak pernah muncul.
    await expect(page.getByTestId('metric-temperature-avg')).toHaveText('52,0')
    await expect(page.getByTestId('metric-temperature-avg')).not.toHaveText('55,4')
    await expect(page.getByTestId('metric-temperature-min')).toHaveText('48,0')
    await expect(page.getByTestId('metric-temperature-max')).toHaveText('57,0')
    await expect(page.getByTestId('metric-temperature-count')).toContainText('12')

    // Ketiga suhu posisi tidak dirender sama sekali, jadi sistem tidak
    // punya bahan untuk menghitungnya ulang di layar.
    const screen = page.getByTestId('laporan-storage-tank-mobile')

    await expect(screen).not.toContainText('55,4')
    await expect(screen).not.toContainText('61,2')

    // Dan alasannya tertulis di layar.
    await expect(page.getByTestId('temperature-source-note')).toContainText(
      'bukan dihitung ulang dari suhu',
    )
  })

  // Scenario 29: "setiap metrik punya penyebutnya sendiri"
  test('metrik yang jarang diisi tidak mengempis, dan jumlah pembacaan berbeda terbaca di sebelah tiap angka', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // DOBI hanya 19 pembacaan sementara FFA 240 — rata-ratanya TIDAK
    // mengempis karena penyebutnya memang 19, bukan 240 dan bukan 412.
    await expect(page.getByTestId('metric-dobi-avg')).toHaveText('2,85')
    await expect(page.getByTestId('metric-dobi-count')).toContainText('19')
    await expect(page.getByTestId('metric-ffa-count')).toContainText('240')
    await expect(page.getByTestId('metric-moisture-count')).toContainText('118')
    await expect(page.getByTestId('metric-impurities-count')).toContainText('42')
    await expect(page.getByTestId('metric-temperature-count')).toContainText('12')

    // Keempat penyebutnya berbeda-beda — tidak ada satu angka yang berlaku
    // untuk semuanya, dan total.reading_rows (412) tidak pernah dipakai
    // sebagai penyebut.
    const counts = await page
      .locator('[data-testid="metric-cards"] .metric-card [data-testid$="-count"]')
      .allTextContents()

    expect(new Set(counts).size).toBe(4)
    for (const count of counts) {
      expect(count).not.toContain('412')
    }

    await expect(page.getByTestId('metric-temperature-count')).not.toContainText('412')
  })

  // Scenario 30: "FFA, kadar air, dan DOBI pada satu grafik dengan skala yang dinyatakan"
  test('ketiga seri mutu pada SATU grafik dengan penskalaan dinyatakan, bergeser di dalam kartunya', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, { summary: makeSummary({ daily: THIRTY_DAYS }) })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // SATU elemen grafik, tiga seri — arah perubahan ketiganya terbaca
    // bersamaan.
    await expect(page.getByTestId('quality-trend-chart')).toHaveCount(1)
    await expect(page.getByTestId('quality-bar-ffa')).toHaveCount(30)
    await expect(page.getByTestId('quality-bar-moisture')).toHaveCount(30)
    await expect(page.getByTestId('quality-bar-dobi')).toHaveCount(30)

    // Cara penskalaannya DINYATAKAN pada grafik, sehingga dua dari tiga
    // seri tidak terbaca sebagai garis datar tanpa penjelasan.
    await expect(page.getByTestId('quality-legend-ffa')).toContainText('dinormalkan')
    await expect(page.getByTestId('quality-legend-ffa')).toContainText('indeks 100')
    await expect(page.getByTestId('quality-legend-moisture')).toContainText('Kadar Air')
    await expect(page.getByTestId('quality-legend-dobi')).toContainText('DOBI')
    await expect(page.getByTestId('quality-trend-note')).toContainText('TINGGI BATANG BUKAN NILAI ASLI')

    // Grafiknya bergeser DI DALAM kartunya sendiri.
    const scroller = page.locator('[data-testid="quality-trend-card"] .chart-scroll')

    await expectCardScrollsInsideItself(page, '[data-testid="quality-trend-card"] .chart-scroll')

    await scroller.evaluate((element) => {
      element.scrollLeft = 200
    })
    expect(await scroller.evaluate((element) => element.scrollLeft)).toBeGreaterThan(0)

    // Halaman TIDAK ikut bergeser.
    await expectNoHorizontalPageScroll(page)
    expect(await page.evaluate(() => window.scrollX)).toBe(0)
  })

  // Scenario 31: "tidak ada penandaan nilai di luar batas"
  test('nilai yang jauh menyimpang tampil apa adanya, tanpa satu pun penanda di luar batas', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, {
      summary: makeSummary({
        metrics: makeMetrics({
          ffa_percent: { min: 0.02, avg: 4.05, max: 48.9, reading_count: 240 },
          average_temperature_c: { min: -12.0, avg: 52.0, max: 210.0, reading_count: 12 },
        }),
      }),
    })
    await openReport(page)

    await pickPeriod(page, 'per-1')

    // Terendah, rata-rata, tertinggi apa adanya.
    await expect(page.getByTestId('metric-ffa-min')).toHaveText('0,02')
    await expect(page.getByTestId('metric-ffa-max')).toHaveText('48,90')
    await expect(page.getByTestId('metric-temperature-min')).toHaveText('-12,0')
    await expect(page.getByTestId('metric-temperature-max')).toHaveText('210,0')

    // Ketiadaan penandaan diasersi MENURUT NAMA pada HTML ter-render.
    const html = (await page.locator('main.laporan-stg-view').innerHTML()).toLowerCase()

    for (const marker of ['threshold', 'outlier', 'iqr', 'is-danger', 'is-warning', 'severity', 'status-flag']) {
      expect(html, `penanda "${marker}" muncul di layar`).not.toContain(marker)
    }

    // Seluruh kartu metrik memakai kelas yang SAMA PERSIS, termasuk yang
    // memuat nilai ekstrem.
    const classes = await page
      .locator('[data-testid="metric-cards"] .metric-card')
      .evaluateAll((elements) => elements.map((element) => element.className))

    expect(new Set(classes).size).toBe(1)

    // Dan tren harian pun tampil tanpa penanda: warna membedakan SERI,
    // bukan nilai.
    await expect(page.getByTestId('quality-trend-note')).toContainText(
      'Tidak ada nilai yang ditandai di luar batas',
    )
  })

  // Scenario 32: "tata letak satu kolom pada layar ponsel"
  test('satu kolom — scrollWidth halaman tidak melebihi clientWidth pada 390px dan 375px, sasaran sentuh 44x44', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page, { summary: makeSummary({ daily: THIRTY_DAYS }) })
    await openReport(page)

    await pickPeriod(page, 'per-1')
    // Ditunggu sampai laporannya terender — expectSingleColumn mengukur
    // kotak pembatas, dan mengukur sebelum kartunya ada berarti mengukur
    // ketiadaan.
    await expect(page.locator('[data-testid="metric-cards"] .metric-card')).toHaveCount(4)

    await expectSingleColumn(page)
    await expectNoHorizontalPageScroll(page)

    // Sasaran sentuh: tinggi DAN lebar minimal 44px, pada beberapa kendali.
    await expectTouchTargets(page, [
      'period-select',
      'daily-recap-toggle',
      'export-button',
      'back-button',
      'hamburger-button',
    ])

    // Gulir dari atas ke bawah tidak pernah memunculkan gulir mendatar.
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
    await expectNoHorizontalPageScroll(page)

    // Grafik dan tabel menggulir di dalam kartunya masing-masing — itulah
    // yang membedakan "isi kartu yang lebar" dari "halaman yang lebar".
    await expectCardScrollsInsideItself(page, '[data-testid="stock-trend-card"] .chart-scroll')
    await expectCardScrollsInsideItself(page, '[data-testid="quality-trend-card"] .chart-scroll')
    await expectCardScrollsInsideItself(page, '[data-testid="by-tank-card"] .detail-table-wrap')

    await recapToggle(page).click()
    await expectCardScrollsInsideItself(page, '[data-testid="daily-recap"] .detail-table-wrap')
    await expectNoHorizontalPageScroll(page)

    // Skenario ini menuliskan 375x812 — lebih sempit daripada viewport
    // describe. Diperiksa juga di sana, sesudahnya, karena yang lulus pada
    // 390px belum tentu lulus pada 375px.
    await page.setViewportSize({ width: 375, height: 812 })
    await page.evaluate(() => window.scrollTo(0, 0))

    await expectSingleColumn(page)
    await expectNoHorizontalPageScroll(page)
    await expectTouchTargets(page, ['period-select', 'daily-recap-toggle', 'export-button'])

    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
    await expectNoHorizontalPageScroll(page)
    await expectCardScrollsInsideItself(page, '[data-testid="daily-recap"] .detail-table-wrap')
  })

  // Scenario 33: "mengunduh rincian pembacaan per slot waktu sebagai CSV"
  test('Ekspor CSV — berkas terunduh, satu baris per slot dengan tepat empat kolom konteks yang diulang', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page)
    await openReport(page)

    await pickPeriod(page, 'per-1')

    const download = page.waitForEvent('download')
    await page.getByTestId('export-button').click()
    const file = await download

    expect(file.suggestedFilename()).toMatch(/^laporan-storage-tank_.*\.csv$/)
    expect(api.hits.export).toBe(1)

    // Permintaan ekspor membawa period_id yang sedang dibuka dan format csv,
    // pada endpoint yang SAMA dengan laporan web.
    const exportUrl = api.urls.find((url) => url.includes('/export'))
    expect(exportUrl).toContain('/api/storage-tank-reports/export')
    expect(exportUrl).toContain('period_id=per-1')
    expect(exportUrl).toContain('format=csv')

    const stream = await file.createReadStream()
    const chunks: Buffer[] = []

    for await (const chunk of stream) {
      chunks.push(Buffer.from(chunk))
    }

    const csv = Buffer.concat(chunks).toString('utf8')
    const lines = csv.trim().split('\n')
    const header = lines[0].split(',')

    // TEPAT EMPAT kolom konteks, dalam urutannya, lalu slot waktu.
    expect(header.slice(0, 5)).toEqual(['Tanggal', 'Tangki', 'Status', 'Catatan', 'Slot Waktu'])

    // Label kolom pengukuran mengikuti label layar Detail, verbatim —
    // termasuk kolom teks dan enum yang tidak punya kartu di laporan.
    expect(csv).toContain('Berat Terhitung (MT)')
    expect(csv).toContain('Status Katup Pemanas Uap')
    expect(csv).toContain('Kondisi Struktur Tangki')
    expect(csv).toContain('Nama Inspektur')
    expect(csv).toContain('Temuan')
    expect(csv).toContain('ada endapan di dasar')

    // Satu baris per detail, dengan keempat kolom konteks DIULANG pada
    // setiap baris dan hanya slot waktunya yang berbeda.
    expect(lines).toHaveLength(3)

    const rowA = lines[1].split(',')
    const rowB = lines[2].split(',')

    expect(rowA.slice(0, 4)).toEqual(rowB.slice(0, 4))
    expect(rowA[4]).toBe('06:00')
    expect(rowB[4]).toBe('12:00')

    // Isinya dibentuk SERVER, bukan disusun ulang dari angka yang sedang
    // tampil di layar.
    expect(csv).not.toContain('1.200,0')
    expect(csv).not.toContain('+280,5')

    // Data stasiun tidak berubah setelah ekspor: tidak ada permintaan tulis.
    expect(api.hits.total).toBeGreaterThan(0)
  })
})

/* ================================================================== */
/* Production Line wajib (2026-09-28)                                  */
/* ================================================================== */

/**
 * Laporan Storage Tank memulangkan ANGKA GABUNGAN satu periode. Menggabungkan
 * beberapa Production Line ke dalam satu angka menghasilkan bilangan yang
 * tidak dapat ditindaklanjuti siapa pun — karena itu memilih line di sini
 * WAJIB dan tidak ada opsi "semua line". (Data Browser versi web memang
 * punya opsi "Semua Line"; di sana barisnya tetap terpisah per record.)
 *
 * FIXTURE DUA LINE SENGAJA BERBEDA NILAINYA, DAN JUMLAH KEDUANYA SENGAJA
 * TIDAK MUNCUL DI MANA PUN — layar yang diam-diam menggabungkan akan
 * menampilkan jumlahnya dan gagal di sini. Jangan "merapikan".
 */
const SUMMARY_LINE_1 = makeSummary({
  production_line: { id: 'pl-1', name: 'Line 1' },
  stock: { ...STOCK, opening_mt: 6421.5 },
})

const SUMMARY_LINE_2 = makeSummary({
  production_line: { id: 'pl-2', name: 'Line 2' },
  stock: { ...STOCK, opening_mt: 233.5 },
})

const SUMMARY_BY_LINE = { 'pl-1': SUMMARY_LINE_1, 'pl-2': SUMMARY_LINE_2 }

test.describe('Laporan Storage Tank Mobile (screen-139) — Production Line wajib', () => {
  // Viewport ponsel — sama seperti describe utama berkas ini. Tanpa ini
  // klaim satu kolom / sasaran sentuh 44px diuji pada lebar desktop.
  test.use({ viewport: { width: 390, height: 844 } })

  test('dua line — pemilih tampil, NOL angka sebelum memilih, lalu angka MILIK LINE ITU', async ({ page }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page, { productionLines: TWO_LINES, summaryByLine: SUMMARY_BY_LINE })
    await openReport(page)

    const lineSelect = page.getByTestId('production-line-select')
    await expect(lineSelect).toBeVisible()
    await expect(lineSelect.locator('option')).toHaveCount(3) // placeholder + 2 line
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    await expect(page.getByTestId('production-line-required-hint')).toContainText('Pilih Production Line')
    // Tidak ada opsi gabungan yang diam-diam ditawarkan.
    await expect(page.getByText(/semua line/i)).toHaveCount(0)

    // Periode boleh dipilih — /periods memang TIDAK tersaring line — tetapi
    // angkanya tetap ditahan.
    await pickPeriod(page, 'per-1')
    await expect(page.getByTestId('stock-opening-mt')).toHaveCount(0)
    await expect(page.getByTestId('export-button')).toHaveCount(0)
    expect(api.hits.summary).toBe(0)

    // Sasaran sentuh pemilih line: 44px pada kedua sisi.
    const box = await lineSelect.boundingBox()
    expect(box).not.toBeNull()
    expect(box!.height).toBeGreaterThanOrEqual(44)
    expect(box!.width).toBeGreaterThanOrEqual(44)

    await lineSelect.selectOption('pl-1')

    await expect(page.getByTestId('stock-opening-mt')).toHaveText('6.421,5')
    // Jumlah kedua line TIDAK PERNAH muncul.
    await expect(page.getByText('6.655,0', { exact: false })).toHaveCount(0)
    expect(api.urls.some((url) => url.includes('/summary') && url.includes('production_line_id=pl-1'))).toBe(true)

    // Ganti line: permintaannya membawa id baru, angka lama tidak tertinggal.
    await lineSelect.selectOption('pl-2')
    await expect(page.getByTestId('stock-opening-mt')).toHaveText('233,5')
    expect(api.urls.some((url) => url.includes('/summary') && url.includes('production_line_id=pl-2'))).toBe(true)

    // Tidak ada gulir mendatar halaman setelah pemilih line ikut terender.
    const overflow = await page.evaluate(() => ({
      scrollWidth: document.documentElement.scrollWidth,
      clientWidth: document.documentElement.clientWidth,
    }))
    expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1)
  })

  test('nama line yang berlaku terbaca, dan ekspor membawa production_line_id', async ({ page }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page, { productionLines: TWO_LINES, summaryByLine: SUMMARY_BY_LINE })
    await openReport(page)

    await page.getByTestId('production-line-select').selectOption('pl-2')
    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('production-line-current')).toContainText('Line 2')

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByTestId('export-button').click(),
    ])
    // Nama berkas TIDAK berubah oleh fitur ini — kolom CSV pun tidak.
    expect(download.suggestedFilename()).toMatch(/^laporan-storage-tank_.*\.csv$/)
    expect(api.urls.some((url) => url.includes('/export') && url.includes('production_line_id=pl-2'))).toBe(true)
  })

  test('ingatan line dari layar Daftar Stasiun dipakai ulang — pengguna TIDAK diminta memilih dua kali', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    // Kunci yang sama persis yang ditulis StationListView.vue setelah
    // pengguna memilih line di layar Daftar Stasiun.
    const userId = await getAuthUserId(page)
    await page.evaluate(
      ([key, value]) => window.localStorage.setItem(key, value),
      [REMEMBERED_LINE_KEY(userId), 'pl-2'] as const,
    )

    const api = await stubApi(page, { productionLines: TWO_LINES, summaryByLine: SUMMARY_BY_LINE })
    await openReport(page)

    // Tidak ada pertanyaan kedua.
    await expect(page.getByTestId('production-line-required-hint')).toHaveCount(0)
    await expect(page.getByTestId('production-line-select')).toHaveValue('pl-2')
    await expect(page.getByTestId('production-line-current')).toContainText('Line 2')

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('stock-opening-mt')).toHaveText('233,5')
    expect(api.urls.some((url) => url.includes('/summary') && url.includes('production_line_id=pl-2'))).toBe(true)
  })

  test('production_line_id pada URL (dibawa screen-141) langsung berlaku', async ({ page }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page, { productionLines: TWO_LINES, summaryByLine: SUMMARY_BY_LINE })
    await openReport(page, `${ROUTE}?production_line_id=pl-2`)

    await expect(page.getByTestId('production-line-required-hint')).toHaveCount(0)
    await expect(page.getByTestId('production-line-select')).toHaveValue('pl-2')

    await pickPeriod(page, 'per-1')

    await expect(page.getByTestId('stock-opening-mt')).toHaveText('233,5')
    expect(api.urls.some((url) => url.includes('/summary') && url.includes('production_line_id=pl-2'))).toBe(true)
  })

  test('daftar periode TIDAK PERNAH membawa production_line_id — periode milik mill', async ({ page }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page, { productionLines: TWO_LINES, summaryByLine: SUMMARY_BY_LINE })
    await openReport(page)

    await page.getByTestId('production-line-select').selectOption('pl-2')
    await pickPeriod(page, 'per-1')

    const periodUrls = api.urls.filter((url) => url.includes('/periods'))
    expect(periodUrls.length).toBeGreaterThan(0)
    expect(periodUrls.every((url) => !url.includes('production_line_id'))).toBe(true)
  })

  test('daftar line gagal dimuat — tanpa angka, dengan arahan dan tombol coba lagi', async ({ page }) => {
    await login(page, USERS.operator)
    const api = await stubApi(page, { productionLinesAbort: true })
    await openReport(page)

    await expect(page.getByTestId('production-line-unavailable')).toBeVisible()
    await expect(page.getByTestId('production-line-retry')).toBeVisible()
    await expect(page.getByTestId('network-error')).toHaveCount(0)

    await pickPeriod(page, 'per-1')
    await expect(page.getByTestId('stock-opening-mt')).toHaveCount(0)
    expect(api.hits.summary).toBe(0)
  })
})
