import { expect, test, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { removeOpenPeriodForForms, seedOpenPeriodForForms } from './support/period-fixture'
import { statefulHeaders } from './support/periods'

/**
 * Laporan Periode Engine Room (Browser/Playwright) — screen-158--laporan-engine-room-web /
 * usecase-164--laporan-engine-room-web.
 *
 * ────────────────────────────────────────────────────────────────────────
 * ASERSI INVARIAN, BUKAN ANGKA YANG DIPAKU
 * ────────────────────────────────────────────────────────────────────────
 * Spec ini TIDAK menanam data laporannya sendiri dan TIDAK mengasersi satu
 * angka tetap pun — pola yang sama dengan tests/laporan-effluent-plant.spec.ts
 * dan tests/laporan-kernel-plant.spec.ts, dengan dua alasan yang sama dan
 * keduanya menentukan:
 *
 * 1. Angka laporan ini sudah dibuktikan TIGA KALI di backend, terhadap data
 *    yang dikendalikan penuh — atas service-nya (73 kasus unit di
 *    backend/tests/Unit/Services/EngineRoomReportServiceTest.php), atas
 *    kontrak HTTP-nya (37 skenario di
 *    backend/tests/Feature/Api/LaporanEngineRoomTest.php), dan atas markup
 *    ter-render komponennya (39 skenario di
 *    backend/tests/Feature/Livewire/LaporanEngineRoomTest.php).
 *    Mengulangnya lewat browser hanya memperlambat suite.
 *
 * 2. Database e2e ini BERBAGI data Engine Room dengan spec lain:
 *    BrowserTestFixtureSeeder menanam ER-BROWSER-EDIT dan deret
 *    ER-BROWSER-PL-A/B, form-engine-room menanam record lain lewat UI,
 *    detail-engine-room dan data-browser-engine-room menanam deret ER-nya
 *    sendiri, dan periode prasyaratnya berumur panjang. Angka yang dipaku di
 *    sini akan berubah setiap kali salah satu dari itu berubah — test yang
 *    gagal bukan karena produknya salah adalah test yang akan diabaikan
 *    orang, lalu dihapus.
 *
 * PERIOD_LANES (tests/support/period-lanes.ts) SENGAJA TIDAK DIPAKAI: itu
 * perangkat enam spec laporan yang lebih tua, yang menanam periodenya sendiri
 * di mill terpisah. Spec laporan terbaru memakai period-fixture.ts, dan
 * mencampur keduanya akan menghasilkan periode bertumpang-tindih yang ditolak
 * 422 PERIOD_OVERLAP.
 *
 * Yang diuji di sini karena HANYA browser dapat membuktikannya:
 *   (a) TILE Engine Room pada Laporan Stasiun (screen-140) menyala dan
 *       mendarat di layar ini. Tanpa test ini, satu baris yang hilang pada
 *       StationReportService::REPORT_ROUTES membuat layar ini ada, seluruh
 *       test backend lulus, dan tile-nya tetap kelabu — pola kegagalan yang
 *       sudah pernah terjadi pada laporan Cages & Tracks versi web. Kuncinya
 *       'engine-room' BERTANDA HUBUNG, yang memang dipegang
 *       StationTypeEnum::EngineRoom->value.
 *   (b) HALAMANNYA HIDUP: Livewire terpasang dan wire:model bekerja, sehingga
 *       pemilih-pemilihnya memicu putaran yang mengubah halaman. Component
 *       test tidak dapat menangkap jebakan wire:id-menempel-pada-<style> yang
 *       pernah mematikan SELURUH wire:model di screen-140, dan laporan ini
 *       menaruh partial ber-<style>-nya di slot `styles` layout justru karena
 *       itu.
 *   (c) Blok KELENGKAPAN berada DI ATAS seluruh kartu metrik — diukur dari
 *       posisi kotaknya di viewport, bukan dari urutan sumbernya.
 *   (d) KEDUA PULUH DUA kartu metrik mencetak PENYEBUTNYA SENDIRI, plus kartu
 *       ke-23 (ekspor energi harian) yang TIDAK menerbitkan rata-rata periode.
 *       PENYEBUT YANG SAMA PADA SEMUANYA ADALAH JUSTRU CACAT YANG DICARI, dan
 *       bentuk asersinya yang menangkapnya tanpa memaku angka: sebuah kartu
 *       yang nilainya "tidak tersedia" WAJIB mencetak penyebut 0 — penyebut
 *       bersama akan mencetak jumlah slot terisi periode di sana.
 *   (e) TIDAK ADA satu pun ELEMEN berkelas penanda di luar batas, disisir atas
 *       NAMA KELAS lewat locator — bukan atas frasa dan bukan atas sumber
 *       halaman. Kalimat yang menyatakan ketiadaannya sendiri memuat frasa
 *       "di luar batas", dan `.md-threshold` DIDEFINISIKAN pada partial
 *       stylesheet bersama report-styles.blade.php, jadi namanya muncul pada
 *       HTML setiap laporan tanpa pernah dipakai. Locator menghitung ELEMEN,
 *       dan itulah sebabnya bentuk locator yang benar. Layar ini sengaja
 *       memakai `.md-explain` untuk kotak keterangannya justru supaya asersi
 *       ini dapat ditulis menurut nama kelas.
 *   (f) TIDAK ADA BAGIAN DOWNTIME — dan asersinya atas TESTID dan JUDUL, BUKAN
 *       atas substring 'downtime'. Pernyataan ketiadaannya memakai
 *       data-testid="no-downtime-note".
 *   (g) KEDUA sebaran status menjumlah ke slot tercatat, dan KETIGA baris
 *       nilainya tetap terender lengkap — termasuk baris bernilai 0. 'Standby'
 *       adalah barisnya sendiri dan tidak pernah dilipat ke 'Off'.
 *   (h) Jumlah slot per unit menjumlah ke slot terisi periode.
 *   (i) Rekap harian dapat dibuka/ditutup tanpa mengubah satu angka pun, DAN
 *       — diukur, bukan disimpulkan — kedua tabel rekap terlebar menggulir
 *       mendatar DI DALAM kartunya sendiri sementara HALAMANNYA tidak pernah
 *       menggulir mendatar. 26 kolom pada rekap per unit adalah rekap
 *       TERLEBAR di antara seluruh laporan web, jadi di sinilah pemotongan
 *       akan muncul lebih dulu daripada di mana pun.
 *   (j) KARTU EKSPOR ENERGI adalah NILAI TERAKHIR, bukan rata-rata, dan
 *       keterangan sebabnya terender di dekat serinya.
 *   (k) Operator DITOLAK pada rute WEB — sementara KETIGA rute API DATA-nya
 *       justru menerimanya 200 SEJAK COMMIT PERTAMA (kembaran mobile
 *       screen-159 dibangun dalam seri yang sama), dan
 *       /business-units/options tetap 403. Ketiga jawaban yang berbeda itulah
 *       invariannya.
 *   - EKSPOR benar-benar mengunduh berkas, dan yang Excel benar-benar .xlsx.
 *
 * ────────────────────────────────────────────────────────────────────────
 * SEMBILAN KEADAAN YANG SENGAJA TIDAK ADA DI SINI
 * ────────────────────────────────────────────────────────────────────────
 * Mill tanpa periode Engine Room, periode berisi record tetapi nol slot
 * terisi, periode yang belum mulai (slot harapan nol), periode yang masih
 * berjalan, sebuah metrik yang tidak pernah diisi sepanjang periode, satu
 * genset yang tidak pernah menyala, kolom ekspor energi yang tidak pernah
 * terisi, akun terikat mill dengan business_unit_id null, dan id
 * mill/line/periode mill lain lewat properti komponen — kesembilannya
 * menuntut database yang disusun per test, dan database e2e ini DIPAKAI
 * BERSAMA puluhan spec lain. Kesembilannya dibuktikan di
 * backend/tests/Feature/Livewire/LaporanEngineRoomTest.php, yang memang punya
 * database sendiri per test.
 */

const REPORT_PATH = '/reports/engine-room'
const STATION_REPORT_PATH = '/reports'

const BUSINESS_UNIT = 'BU Browser Test'
const PRODUCTION_LINE = 'PL Mill A'

const SUPERVISOR = 'stest-supervisor01'
const ADMIN = 'brtest-admin01'
/**
 * Operator tidak punya akses ke layar WEB ini, dan tidak pernah akan punya:
 * penjagaannya ada pada middleware rute DAN pada canAccess() komponen, yang
 * memegang daftar perannya SENDIRI.
 *
 * BERBEDA DARI SAUDARA-SAUDARANYA, di sini bahayanya BUKAN di masa depan:
 * EngineRoomReportService::guardAccess() MENERIMA Operator HARI INI, sejak
 * commit pertama, karena kembaran mobile screen-159 dibangun dalam seri yang
 * sama dan merupakan pemanggil yang sah. Kalau canAccess() mendelegasikan ke
 * service, layar web ini terbuka untuk Operator SEKARANG — tanpa satu galat
 * pun. Test di bawah yang membuktikan pelebaran itu tidak merembes ke sini.
 */
const OPERATOR = 'operator01'

/**
 * KEDUA PULUH DUA kolom ukur yang MENERBITKAN rata-rata periode, dalam urutan
 * blok tata letaknya di layar. `daily_energy_export_kwh` SENGAJA TIDAK ADA di
 * sini — ia kolom ke-23 dan punya kartunya sendiri (lihat ENERGY_COLUMN).
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

/**
 * KOLOM KE-23, dan satu-satunya yang TIDAK menerbitkan rata-rata periode:
 * namanya menyebut 'daily' sementara ia disimpan per slot waktu, jadi yang
 * diterbitkan adalah NILAI TERAKHIR per tanggal.
 */
const ENERGY_COLUMN = 'daily_energy_export_kwh'

/** KEENAM blok berjudul, diturunkan dari field `group` pada respons. */
const METRIC_BLOCKS = [
  { testid: 'metric-block-steam-turbine', group: 'steam_turbine', cards: 6 },
  { testid: 'metric-block-diesel-gen-1', group: 'diesel_gen_1', cards: 4 },
  { testid: 'metric-block-diesel-gen-2', group: 'diesel_gen_2', cards: 4 },
  { testid: 'metric-block-electrical-sync', group: 'electrical_sync', cards: 4 },
  { testid: 'metric-block-compressor', group: 'compressor', cards: 2 },
  // Blok Penunjang membawa 2 kartu metrik PLUS kartu ekspor energi.
  { testid: 'metric-block-auxiliary', group: 'auxiliary', cards: 2 },
]

/**
 * KEDUA sebaran status, dan jumlah baris nilai yang WAJIB terender masing-
 * masing — TIGA, termasuk baris bernilai 0: himpunan nilainya tertutup di
 * basis data ($table->enum(['run','standby','off']) -> CHECK constraint).
 */
const STATUS_BLOCKS = [
  { testid: 'status-distribution-diesel-gen-1', rows: 3 },
  { testid: 'status-distribution-diesel-gen-2', rows: 3 },
]

/** "1.234,56" -> 1234.56; "tidak tersedia" / "—" -> null. */
function parseIdNumber(raw: string): number | null {
  const match = raw.replace(/ /g, ' ').match(/-?\d[\d.]*(?:,\d+)?/)

  if (match === null) {
    return null
  }

  return Number(match[0].replace(/\./g, '').replace(',', '.'))
}

/**
 * Membuka laporan untuk line fixture dan memastikan sebuah periode terpilih.
 *
 * Periode TIDAK dipilih dengan nama: periode mana yang terbaru di database
 * bersama ini bukan sesuatu yang spec ini kendalikan. Yang dibutuhkannya hanya
 * "sebuah periode yang sah", dan pemilih periode memang memilih sendiri
 * periode terbaru pada muat pertama.
 */
async function openReportWithData(page: Page): Promise<void> {
  await page.goto(REPORT_PATH)
  await expect(page.locator('[data-testid="laporan-engine-room"]')).toBeVisible()

  await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })

  await expect(page.locator('[data-testid="report-hero"]')).toBeVisible()
  // Cakupan dirender untuk SETIAP periode yang sah, berdata atau tidak —
  // justru itu gunanya. Menunggunya membuktikan putaran Livewire mendarat, dan
  // mencegah count() yang menjawab 0 palsu karena wilayahnya belum ada.
  await expect(page.locator('[data-testid="coverage"]')).toBeVisible()
}

/** Jumlah slot terisi periode, dibaca dari kartu cakupan. */
async function filledSlotsOf(page: Page): Promise<number> {
  const raw = await page.locator('[data-testid="coverage-slots"]').innerText()
  const filled = parseIdNumber(raw)

  expect(filled, `tidak bisa membaca slot terisi dari "${raw}"`).not.toBeNull()

  return filled as number
}

/** Seluruh angka pada satu kolom tabel, sebagai bilangan (null dibuang). */
async function columnNumbers(page: Page, selector: string): Promise<number[]> {
  const cells = await page.locator(selector).allInnerTexts()

  return cells
    .map((cell) => parseIdNumber(cell))
    .filter((value): value is number => value !== null)
}

/**
 * PRASYARAT PERIODE PELAPORAN.
 *
 * Spec ini sengaja tidak menanam data laporannya sendiri (lihat catatan
 * panjang di atas), tetapi PERIODE-nya tetap harus ada: tanpa satu pun periode
 * yang mencakup Engine Room di "BU Browser Test", pemilih periodenya kosong,
 * `[data-testid="coverage"]` tidak pernah muncul, dan hampir seluruh
 * skenarionya gagal sebagai "element(s) not found" — seolah produknya yang
 * rusak. Itu benar-benar terjadi pada 2026-10-06 untuk empat spec laporan yang
 * menumpang periode `form-*` tanpa menyebutkannya.
 *
 * seedOpenPeriodForForms() idempoten dan MEMAKAI ULANG periode yang sudah ada
 * (findPeriodByPrefix), jadi memanggilnya tidak menambah periode kedua ketika
 * spec `form-*` memang jalan lebih dulu — ia hanya menghapus ketergantungan
 * pada urutan alfabet, dan membuat spec ini dapat dijalankan SENDIRIAN setelah
 * scripts/prepare-db.sh.
 *
 * KUNCI STASIUNNYA 'engine-room' BERTANDA HUBUNG. Fixture mencocokkannya
 * dengan `station.station_type` dari API, yang berisi nilai enum-nya;
 * 'engine_room' gagal di period-fixture.ts dengan "periode prasyarat tidak
 * punya baris stasiun". Dan tanpa hook ini sama sekali, beforeAll MENGGANTUNG
 * alih-alih gagal.
 */
let periodFixturePage: Page | undefined

test.beforeAll(async ({ browser }) => {
  periodFixturePage = await seedOpenPeriodForForms(browser, 'engine-room')
})

test.afterAll(async () => {
  // Dijaga: kalau beforeAll gagal sebelum mengembalikan page-nya, afterAll
  // tidak boleh menimpa kegagalan itu dengan TypeError miliknya sendiri.
  if (periodFixturePage !== undefined) {
    await removeOpenPeriodForForms(periodFixturePage)
  }
})

test.describe('Laporan Periode Engine Room (screen-158)', () => {
  // Scenario 4: "Production Line belum dipilih"
  test('Supervisor: tanpa pemilih mill, dan NOL angka sebelum line dipilih', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(REPORT_PATH)

    await expect(page.locator('[data-testid="laporan-engine-room"]')).toBeVisible()

    // Peran terikat mill tidak melihat pemilih mill — menawarkan pemilih yang
    // tidak bisa ia pakai adalah kebohongan kecil yang mahal.
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)

    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()

    // Dan BENAR-BENAR tidak ada angka: bukan sekadar bagian yang
    // disembunyikan, dan BUKAN angka seluruh mill sebagai penggantinya.
    // Pilihan itu tidak pernah dibuat untuk pembaca, bahkan pada mill yang
    // hanya punya satu line.
    await expect(page.locator('[data-testid="coverage"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="metric-card"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="energy-export-card"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="status-row"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="export-button"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="export-excel-button"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="select-production-line-hint"]'))
      .toContainText('tidak menampilkan angka seluruh mill')
  })

  // Invarian (b): halaman hidup — wire:model memicu putaran yang mengubah
  // halaman.
  test('invarian (b) — memilih Production Line memicu putaran Livewire yang mengubah halaman', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(REPORT_PATH)

    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })

    // INILAH yang hanya browser dapat buktikan: bila atribut wire:id menempel
    // pada sebuah <style> alih-alih pada root komponen, SELURUH wire:model
    // mati tanpa satu pun galat, dan baris ini yang menangkapnya. Laporan ini
    // menaruh partial ber-<style>-nya di slot `styles` layout justru karena
    // harga jebakan itu sudah dibayar sekali di screen-140.
    await expect(page.locator('[data-testid="select-production-line-hint"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="coverage"]')).toBeVisible()
    await expect(page.locator('[data-testid="hero-production-line"]')).toContainText(PRODUCTION_LINE)
    await expect(page.locator('[data-testid="production-line-current"]')).toBeVisible()
    await expect(page.locator('[data-testid="period-status-badge"]')).toBeVisible()
    await expect(page.locator('[data-testid="hero-unit-count"]')).toContainText('unit Engine Room')
  })

  // Invarian (c): cakupan dibaca lebih dulu.
  test('invarian (c) — blok kelengkapan berada DI ATAS seluruh kartu metrik di viewport', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const firstCard = page.locator('[data-testid="metric-card"]').first()

    await expect(firstCard).toBeVisible()

    const coverage = await page.locator('[data-testid="coverage"]').boundingBox()
    const metrics = await firstCard.boundingBox()
    const block = await page.locator('[data-testid="metric-block-steam-turbine"]').boundingBox()

    expect(coverage).not.toBeNull()
    expect(metrics).not.toBeNull()
    expect(block).not.toBeNull()

    // Diukur dari POSISI di viewport, bukan dari urutan sumbernya: periode
    // yang terisi seperlima pun menghasilkan rata-rata yang terlihat rapi, dan
    // pembaca harus melihat kelengkapannya lebih dulu. Kelengkapan adalah ISI
    // laporan, bukan metadata.
    expect(coverage!.y).toBeLessThan(metrics!.y)
    expect(coverage!.y).toBeLessThan(block!.y)

    // KETIGA angka pembentuk penyebut ikut tercetak, bukan hanya persennya:
    // unit Engine Room yang benar-benar punya record, hari yang dihitung, dan
    // 24 slot kanonis per hari — dan 24 itu diambil dari kisi slot layar input
    // sendiri, bukan dikarang di blade.
    await expect(page.locator('[data-testid="coverage-formula"]')).toContainText('unit')
    await expect(page.locator('[data-testid="coverage-formula"]')).toContainText('hari')
    await expect(page.locator('[data-testid="coverage-formula"]')).toContainText('24 slot')
    await expect(page.locator('[data-testid="coverage-formula"]')).toContainText('07:00')

    // Persentase diasersi HANYA pada sel cakupannya, tidak pernah atas
    // halaman: keterangan cakupan dan kedua sebaran status memuat banyak
    // persentase lain.
    const percent = await page.locator('[data-testid="coverage-percent"]').innerText()

    expect(percent.includes('%') || percent.includes('—'), `sel cakupan tak terbaca: ${percent}`).toBe(true)

    // Dan keterangan mengapa slot terisi dapat MELEBIHI penyebut metrik mana
    // pun dirender di blok kelengkapan, bukan sebagai catatan kaki
    // tersembunyi.
    await expect(page.locator('[data-testid="coverage-note"]')).toBeVisible()
    await expect(page.locator('[data-testid="coverage-note"]')).toContainText('dua puluh')
    await expect(page.locator('[data-testid="coverage-note"]')).toContainText('kedua kolom teks bebas')
  })

  // Invarian (d): INTI LAPORAN INI.
  test('invarian (d) — 22 kartu mencetak penyebutnya SENDIRI, plus kartu ke-23 tanpa rata-rata', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    // KEDUA PULUH DUA kartu SELALU dirender, termasuk yang tidak pernah
    // diukur: kartu yang hilang terbaca sebagai "parameter itu tidak ada",
    // padahal yang benar "tidak ada yang mengukurnya".
    await expect(page.locator('[data-testid="metric-card"]')).toHaveCount(22)
    await expect(page.locator('[data-testid="metric-denominator"]')).toHaveCount(22)
    await expect(page.locator('[data-testid="metric-avg"]')).toHaveCount(22)
    await expect(page.locator('[data-testid="metric-range"]')).toHaveCount(22)

    // KARTU KE-23 punya markupnya SENDIRI karena ia memang bukan rata-rata
    // periode: tepat satu, dengan badge 'nilai terakhir'.
    await expect(page.locator('[data-testid="energy-export-card"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="energy-export-badge"]')).toHaveCount(1)
    await expect(page.locator(`[data-metric="${ENERGY_COLUMN}"]`)).toHaveCount(1)
    // 22 kartu metrik + 1 kartu energi = 23 kolom ukur, seluruhnya dirender.
    await expect(page.locator('[data-metric]')).toHaveCount(23)

    // KEENAM blok, dan jumlah kartu di tiap blok diturunkan dari field `group`
    // pada respons — bukan dari daftar kolom yang dipaku di template.
    for (const block of METRIC_BLOCKS) {
      const card = page.locator(`[data-testid="${block.testid}"]`)

      await expect(card, block.testid).toHaveCount(1)
      await expect(card, block.testid).toHaveAttribute('data-metric-group', block.group)
      await expect(card.locator('[data-testid="metric-card"]'), block.testid).toHaveCount(block.cards)
    }

    // Kartu ke-23 duduk DI DALAM blok Penunjang, bukan di bagiannya sendiri.
    await expect(page.locator('[data-testid="metric-block-auxiliary"] [data-testid="energy-export-card"]'))
      .toHaveCount(1)

    const filledSlots = await filledSlotsOf(page)
    const denominators: number[] = []

    for (const column of METRIC_COLUMNS) {
      const card = page.locator(`[data-metric="${column}"]`)

      await expect(card, column).toHaveCount(1)

      const denominatorRaw = await card.locator('[data-testid="metric-denominator"]').innerText()
      const avgRaw = await card.locator('[data-testid="metric-avg"]').innerText()
      const denominator = parseIdNumber(denominatorRaw)

      expect(denominator, `penyebut ${column} tak terbaca: ${denominatorRaw}`).not.toBeNull()

      // Penyebut sebuah kolom tidak pernah melampaui jumlah slot terisi
      // periode — kolom itu hanya bisa terisi pada slot yang memang terisi.
      // Invarian ini benar untuk data apa pun, dan ia juga separuh dari
      // invarian "27 kolom": arah ketidaksamaannya yang sah hanya satu.
      expect(denominator as number, `${column}: ${denominatorRaw}`).toBeLessThanOrEqual(filledSlots)

      // DAN INILAH BENTUK YANG MENANGKAP PENYEBUT BERSAMA tanpa memaku satu
      // angka pun: sebuah kartu yang nilainya "tidak tersedia" WAJIB
      // mencetak 0. Penyebut bersama akan mencetak jumlah slot terisi
      // periode di sana — angka yang tetap terlihat sah, dan justru itu
      // sebabnya cacat ini sulit terlihat tanpa asersi ini.
      if (avgRaw.includes('tidak tersedia')) {
        expect(denominator, `${column} tidak tersedia tetapi penyebutnya ${denominatorRaw}`).toBe(0)
      } else {
        expect(denominator as number, `${column} berangka tetapi penyebutnya 0`).toBeGreaterThan(0)
      }

      denominators.push(denominator as number)
    }

    expect(denominators).toHaveLength(22)
  })

  // Invarian (j): kartu ke-23 dan kedua agregasi yang berbeda.
  test('invarian (j) — kartu ekspor energi adalah NILAI TERAKHIR, dan sebabnya dinyatakan', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const card = page.locator('[data-testid="energy-export-card"]')

    await expect(card).toBeVisible()
    // Badge-nya menyebut sifat angkanya, DI DALAM kartunya — bukan di catatan
    // kaki halaman: sebuah angka yang terbaca sebagai rata-rata padahal nilai
    // terakhir adalah kesimpulan yang salah tentang pabrik.
    await expect(card.locator('[data-testid="energy-export-badge"]')).toContainText('nilai terakhir')
    await expect(card.locator('[data-testid="energy-export-denominator"]')).toContainText('Tanpa rata-rata periode')

    // Seri ekspor energi harian ADA, dan ia entah menggambar titik entah
    // menyatakan tidak ada data — tabel/grafik kosong tanpa keterangan tidak
    // membedakan "tidak diekspor" dari "tidak ada yang mencatatnya".
    const series = page.locator('[data-testid="daily-energy-export"]')

    if (await series.count() > 0) {
      await expect(series).toBeVisible()
      await expect(
        series.locator('[data-testid="daily-energy-export-empty"]')
          .or(series.locator('[data-testid^="daily-energy-export-col-"]'))
          .first(),
      ).toBeVisible()

      // Keterangan mengapa kolom ini tidak dirata-ratakan terender UTUH di
      // dekat serinya, dan menyebut kisi kanonis 07:00..06:00 — bukan
      // perbandingan angka jam.
      const note = page.locator('[data-testid="daily-energy-export-note"]')

      await expect(note).toBeVisible()
      await expect(note).toContainText('nilai TERAKHIR hari itu')
      await expect(note).toContainText('per slot waktu')
      await expect(note).toContainText('06:00')

      // Setiap titik seri membawa SLOT ASALNYA, supaya pembaca dapat
      // memeriksa nilai mana yang dipakai.
      const points = page.locator('[data-testid^="daily-energy-export-col-"]')
      const pointCount = await points.count()

      for (let index = 0; index < pointCount; index += 1) {
        await expect(points.nth(index)).toHaveAttribute('data-time-slot', /^\d{2}:\d{2}$/)
      }
    }

    // Dan tren beban total pabrik di sebelahnya adalah RATA-RATA — kedua
    // agregasi dinyatakan BERBEDA justru supaya keduanya tidak terbaca
    // sejenis.
    const trend = page.locator('[data-testid="daily-trend-total-factory-load"]')

    if (await trend.count() > 0) {
      await expect(trend).toBeVisible()
      await expect(page.locator('[data-testid="aggregation-difference-note"]'))
        .toContainText('Agregasi kedua seri ini BERBEDA')
    }

    // Sel total ekspor energi pada rekap harian sengaja TANDA PISAH: sel total
    // yang berisi angka akan terbaca sebagai rata-rata periode yang memang
    // tidak ada.
    const total = page.locator('[data-testid="daily-total-energy-export"]')

    if (await total.count() > 0) {
      await expect(total).toContainText('—')
      await expect(page.locator('[data-testid="daily-total-energy-note"]'))
        .toContainText('tidak')
    }
  })

  // Invarian (e): tidak ada penandaan otomatis di luar batas.
  test('invarian (e) — nol ELEMEN berkelas penanda di luar batas pada halaman ter-render', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)
    await expect(page.locator('[data-testid="metric-card"]').first()).toBeVisible()

    // DISISIR ATAS NAMA KELAS LEWAT LOCATOR, dan tidak dengan dua cara lain
    // yang tampak setara:
    //   - bukan atas FRASA, karena kalimat yang menyatakan ketiadaan
    //     penandaan itu sendiri memuat frasa "di luar batas";
    //   - bukan atas SUMBER halaman, karena `.md-threshold` DIDEFINISIKAN di
    //     partial stylesheet bersama report-styles.blade.php — namanya muncul
    //     pada HTML setiap laporan tanpa pernah dipakai pada satu elemen pun.
    // Locator menghitung ELEMEN, dan hanya itu yang menjawab pertanyaannya.
    for (const className of [
      'md-threshold', 'is-danger', 'is-warning', 'is-critical', 'is-breach',
      'md-chip--danger', 'md-badge--danger', 'md-kpi--danger',
      'md-trendchart__bar--high', 'md-trendchart__bar--low',
    ]) {
      await expect(page.locator(`.${className}`), className).toHaveCount(0)
    }

    // Kotak keterangan layar ini memakai `.md-explain`, yang tampil identik
    // dan hanya berbeda namanya — pilihan itulah yang membuat asersi di atas
    // dapat ditulis menurut nama kelas sama sekali.
    expect(await page.locator('.md-explain').count()).toBeGreaterThan(0)

    // Engine Room tidak punya tabel master target operasional sama sekali,
    // dan ketiadaan itu DINYATAKAN UTUH — ketiadaan penandaan yang tidak
    // dijelaskan terbaca sebagai fitur yang belum selesai, dan seseorang akan
    // "melengkapinya". Di layar inilah godaan itu paling besar: frekuensi
    // sistem, power factor dan tegangan busbar punya batas yang dikenal
    // sangat luas DI LUAR aplikasi.
    const note = page.locator('[data-testid="no-threshold-note"]')

    await expect(note).toBeVisible()
    await expect(note).toContainText('tidak punya tabel master target')
    await expect(note).toContainText('keselamatan')

    // DAN ia terender DI DALAM blok Kelistrikan Tersinkron, bukan sebagai
    // catatan kaki halaman — diasersi sebagai KETURUNAN elemennya, bentuk
    // paling langsung dari klaim "di dekat angkanya".
    await expect(page.locator('[data-testid="metric-block-electrical-sync"] [data-testid="no-threshold-note"]'))
      .toHaveCount(1)
  })

  // Invarian: kedelapan kolom genset tetap delapan kartu terpisah.
  test('kedelapan kartu genset terpisah, tanpa kartu gabungan dan tanpa badge selisih', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const gensetOne = page.locator('[data-testid="metric-block-diesel-gen-1"]')
    const gensetTwo = page.locator('[data-testid="metric-block-diesel-gen-2"]')

    await expect(gensetOne).toBeVisible()
    await expect(gensetTwo).toBeVisible()
    await expect(gensetOne.locator('[data-testid="metric-card"]')).toHaveCount(4)
    await expect(gensetTwo.locator('[data-testid="metric-card"]')).toHaveCount(4)

    // KEEMPAT PASANGAN kolom sejajar ada sebagai delapan kartu TERPISAH.
    for (const suffix of ['load_kw', 'amperage_a', 'jacket_water_temp_c', 'lube_oil_pressure_bar']) {
      await expect(gensetOne.locator(`[data-metric="diesel_gen_1_${suffix}"]`), suffix).toHaveCount(1)
      await expect(gensetTwo.locator(`[data-metric="diesel_gen_2_${suffix}"]`), suffix).toHaveCount(1)
    }

    // Tidak ada kartu gabungan, badge selisih, maupun penanda penyimpangan —
    // disisir atas PENANDA markup, bukan atas kata di dalam kalimat yang
    // justru menyatakan ketiadaannya.
    for (const testid of ['genset-delta', 'genset-imbalance', 'genset-total', 'genset-combined']) {
      await expect(page.locator(`[data-testid="${testid}"]`), testid).toHaveCount(0)
    }

    await expect(page.locator('[data-metric="diesel_gen_combined_load_kw"]')).toHaveCount(0)

    // Keterangan kesejajaran dirender di KEDUA blok — pembaca yang hanya
    // melihat satu blok tetap harus membacanya — dan ia menyebut kesejajaran
    // FISIK kedua mesin, BUKAN standar bersama: stasiun ini tidak punya
    // master target sama sekali.
    await expect(page.locator('[data-testid="genset-parallel-note"]')).toHaveCount(2)
    await expect(gensetOne.locator('[data-testid="genset-parallel-note"]')).toHaveCount(1)
    await expect(gensetTwo.locator('[data-testid="genset-parallel-note"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="genset-parallel-note"]').first())
      .toContainText('dua mesin fisik yang berbeda')
    await expect(page.locator('[data-testid="genset-parallel-note"]').first())
      .toContainText('bukan standar')
  })

  // Invarian (f): tidak ada bagian downtime.
  test('invarian (f) — tidak ada bagian downtime, diasersi atas TESTID dan JUDUL', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)
    await expect(page.locator('[data-testid="metric-card"]').first()).toBeVisible()

    // DIASERSI ATAS TESTID DAN JUDUL, BUKAN atas substring 'downtime': laporan
    // ini MENYATAKAN ketiadaan bagian itu, dan pernyataannya sendiri memakai
    // data-testid="no-downtime-note". Penyisiran substring akan gagal pada
    // elemen yang justru membuktikan klaimnya.
    for (const testid of ['downtime', 'downtime-total', 'downtime-empty', 'downtime-note', 'downtime-table']) {
      await expect(page.locator(`[data-testid="${testid}"]`), testid).toHaveCount(0)
    }

    await expect(page.getByRole('heading', { name: /^Downtime/ })).toHaveCount(0)
    await expect(page.getByRole('heading', { name: /Lama Berhenti/ })).toHaveCount(0)
    await expect(page.getByRole('heading', { name: /Rekap Downtime/ })).toHaveCount(0)

    // Ketiadaannya DINYATAKAN — bagian yang hilang tanpa keterangan tidak
    // dapat dibedakan dari bagian yang gagal dimuat, dan laporan stasiun lain
    // memilikinya sehingga pembaca yang datang dari sana akan mencarinya.
    await expect(page.locator('[data-testid="no-downtime-note"]'))
      .toContainText('tidak mencatat lama berhenti sama sekali')
  })

  // Invarian (g): dua sebaran status kategoris dengan TIGA nilai.
  test('invarian (g) — kedua sebaran menjumlah ke slot tercatat, baris 0 tetap terender', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const filledSlots = await filledSlotsOf(page)

    for (const block of STATUS_BLOCKS) {
      const card = page.locator(`[data-testid="${block.testid}"]`)

      await expect(card, block.testid).toBeVisible()

      // NILAI DIKENAL YANG TIDAK PERNAH MUNCUL TETAP DIRENDER DENGAN NOL:
      // baris yang hilang tidak dapat dibedakan dari nilai yang tidak pernah
      // ada di basis data. Jumlah barisnya karena itu TETAP TIGA, apa pun
      // datanya.
      await expect(card.locator('[data-testid="status-row"]'), block.testid)
        .toHaveCount(block.rows)

      for (const value of ['run', 'standby', 'off']) {
        await expect(card.locator(`[data-status-value="${value}"]`), `${block.testid}/${value}`)
          .toHaveCount(1)
      }

      const counts = await columnNumbers(
        page,
        `[data-testid="${block.testid}"] [data-testid="status-row"] td:nth-child(2)`,
      )

      expect(counts, block.testid).toHaveLength(block.rows)

      const notRecorded = parseIdNumber(
        await card.locator('[data-testid="status-not-recorded"] td:nth-child(2)').innerText(),
      )
      const recorded = parseIdNumber(
        await card.locator('[data-testid="status-recorded-total"] td:nth-child(2)').innerText(),
      )

      expect(notRecorded, block.testid).not.toBeNull()
      expect(recorded, block.testid).not.toBeNull()

      // sum(counts) + not_recorded === recorded_slots === slot terisi periode.
      // "Tidak tercatat" adalah KEADAAN TERSENDIRI dan tidak pernah dilipat
      // ke 'Off': "tidak kami tulis" dan "memang tidak jalan" adalah dua
      // kenyataan yang berbeda akibatnya.
      expect(
        counts.reduce((sum, value) => sum + value, 0) + (notRecorded as number),
        block.testid,
      ).toBe(recorded as number)
      expect(recorded as number, block.testid).toBe(filledSlots)
    }

    // Label SAMA dengan layar Detail, formulir, dan kedua berkas ekspor
    // (Display::OPTION_LABELS): Run / Standby / Off, dan layar laporan tidak
    // menerjemahkannya ke Bahasa Indonesia.
    //
    // Diasersi PADA BARISNYA MASING-MASING lewat data-status-value, bukan
    // dengan memindai teks seluruh kartu: 'Run' dan 'Off' hanya dua atau tiga
    // huruf dan akan cocok di dalam kata lain mana pun yang kebetulan ada di
    // kartu itu, sehingga asersi substring bisa hijau tanpa membuktikan apa
    // pun. Harga kesalahan itu sudah dibayar tiga kali.
    const gensetOne = page.locator('[data-testid="status-distribution-diesel-gen-1"]')

    await expect(gensetOne.locator('[data-status-value="run"] td:nth-child(1)')).toHaveText('Run')
    await expect(gensetOne.locator('[data-status-value="standby"] td:nth-child(1)')).toHaveText('Standby')
    await expect(gensetOne.locator('[data-status-value="off"] td:nth-child(1)')).toHaveText('Off')
    await expect(gensetOne.locator('[data-testid="status-not-recorded"] td:nth-child(1)'))
      .toHaveText('Tidak tercatat')

    // 'STANDBY' BUKAN 'OFF', dan pernyataan itu dirender sebagai pernyataannya
    // sendiri: melipat keduanya melaporkan pembangkit mati yang tidak pernah
    // terjadi.
    await expect(page.locator('[data-testid="standby-not-off-note"]')).toBeVisible()
    await expect(page.locator('[data-testid="status-distribution-note"]'))
      .toContainText('jumlah slot tercatat sebagai penyebut')

    // Dan kedua kolom status TIDAK PERNAH muncul sebagai kartu metrik maupun
    // seri tren: terendah, rata-rata dan tertinggi tidak punya arti untuk
    // kolom kategoris.
    for (const column of ['diesel_gen_1_status', 'diesel_gen_2_status']) {
      await expect(page.locator(`[data-metric="${column}"]`), column).toHaveCount(0)
      await expect(page.locator(`[data-testid="daily-trend-${column}"]`), column).toHaveCount(0)
    }
  })

  // Scenario 16/17: dua rekap teks bebas yang TERPISAH
  test('Rekap Catatan Tindakan Perawatan dan Rekap Temuan adalah DUA bagian terpisah', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const remarks = page.locator('[data-testid="maintenance-remarks"]')
    const findings = page.locator('[data-testid="findings"]')

    // DUA kontainer yang BERBEDA, dan keduanya ada: satu menjawab "apa yang
    // dikerjakan", satu menjawab "apa yang ditemukan". Satu slot dapat memuat
    // keduanya, dan menggabungkannya akan menghitung slot itu dua kali di
    // dalam satu pengertian yang sama.
    await expect(remarks).toBeVisible()
    await expect(findings).toBeVisible()
    await expect(remarks).toHaveCount(1)
    await expect(findings).toHaveCount(1)

    const remarksBox = await remarks.boundingBox()
    const findingsBox = await findings.boundingBox()

    expect(remarksBox).not.toBeNull()
    expect(findingsBox).not.toBeNull()
    expect(remarksBox!.y).toBeLessThan(findingsBox!.y)

    // Masing-masing: entah daftar entri, entah keterangan "tidak ada catatan"
    // — tabel kosong tidak membedakan "tidak ada perawatan" dari "tidak ada
    // yang mencatatnya".
    await expect(
      remarks.locator('[data-testid="maintenance-remark-row"]')
        .or(remarks.locator('[data-testid="maintenance-remarks-empty"]'))
        .first(),
    ).toBeVisible()
    await expect(
      findings.locator('[data-testid="finding-row"]')
        .or(findings.locator('[data-testid="findings-empty"]'))
        .first(),
    ).toBeVisible()

    // Keduanya tidak pernah dirata-rata maupun digrafikkan — dan itu
    // dinyatakan di tempatnya.
    await expect(page.locator('[data-testid="maintenance-remarks-note"]'))
      .toContainText('tidak pernah dirata-rata')
    await expect(page.locator('[data-testid="findings-note"]'))
      .toContainText('dua bagian')
  })

  // Invarian (h): rekap per unit.
  test('invarian (h) — jumlah slot per unit menjumlah ke slot terisi periode', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const byUnit = page.locator('[data-testid="by-unit-table"]')

    if (await byUnit.count() === 0) {
      test.skip(true, 'periode terpilih tidak punya data pada line ini')
    }

    await expect(byUnit).toBeVisible()

    const perUnit = await columnNumbers(page, '[data-testid="by-unit-reading-rows"]')
    const filledSlots = await filledSlotsOf(page)

    expect(perUnit.length).toBeGreaterThan(0)
    // Pengelompokan per unit tidak boleh kehilangan maupun menghitung ganda
    // satu slot pun — dan engine_room_id yang sama pada dua tanggal adalah
    // SATU baris dengan dua hari pencatatan, bukan dua baris, karena ia NAMA
    // UNIT yang diketik di layar input dan bukan kunci baris.
    expect(perUnit.reduce((sum, value) => sum + value, 0)).toBe(filledSlots)

    // Dan baris total menyatakan gabungannya, dihitung ulang atas seluruh slot
    // periode — bukan rata-rata dari rata-rata per unit.
    await expect(page.locator('[data-testid="by-unit-row-total"]')).toContainText('Gabungan')
    await expect(page.locator('[data-testid="total-reading-rows"]')).toBeVisible()
    await expect(page.locator('[data-testid="days-with-records"]')).toBeVisible()

    // REKAP TERLEBAR DI ANTARA SELURUH LAPORAN WEB: 26 kolom (unit + hari +
    // slot + 22 metrik + ekspor energi). Dihitung atas <th>-nya, bukan atas
    // lebar pikselnya.
    await expect(byUnit.locator('thead th')).toHaveCount(26)
    await expect(page.locator('[data-testid="by-unit-scroll-hint"]')).toBeVisible()
  })

  // Invarian (i): rekap harian dapat ditutup, DAN gulir mendatarnya DIUKUR.
  test('invarian (i) — rekap harian dapat dibuka/ditutup, dan gulir mendatar ada DI DALAM kartunya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const details = page.locator('[data-testid="daily-recap-details"]')

    if (await details.count() === 0) {
      test.skip(true, 'periode terpilih tidak punya data pada line ini')
    }

    const toggle = page.locator('[data-testid="daily-recap-toggle"]')
    const table = page.locator('[data-testid="daily-table"]')

    await expect(toggle).toBeVisible()

    const slotsBefore = await page.locator('[data-testid="coverage-slots"]').innerText()

    // Elemen <details> NATIVE: tertutup pada paint pertama, jadi tabelnya ada
    // di DOM tetapi TIDAK TERLIHAT. Membukanya dan menutupnya kembali terjadi
    // sepenuhnya di peramban, tanpa satu putaran Livewire pun — dan itulah
    // yang membuat klaim "tidak memicu permintaan data baru" dapat
    // dibuktikan: seluruh angka halaman ini dihitung di service.
    await expect(table).toBeHidden()

    await toggle.click()
    await expect(table).toBeVisible()

    // 25 kolom: tanggal + slot + 22 metrik + ekspor energi.
    await expect(table.locator('thead th')).toHaveCount(25)

    // ──────────────────────────────────────────────────────────────────
    // GULIR MENDATAR, DIUKUR — bukan disimpulkan dari nama kelas.
    // ──────────────────────────────────────────────────────────────────
    // Kedua rekap terlebar menggulir mendatar DI DALAM kartunya sendiri lewat
    // .md-recap { overflow-x: auto }, sementara HALAMANNYA tidak pernah
    // menggulir mendatar. Hanya browser dapat mengukur ini: kelas yang dipakai
    // markup tetapi tidak terdefinisi di CSS hasil compile akan tampak benar
    // di sumbernya dan memotong tabelnya di layar.
    const pageOverflow = await page.evaluate(() => {
      const root = document.scrollingElement ?? document.documentElement

      return { scrollWidth: root.scrollWidth, clientWidth: root.clientWidth }
    })

    expect(
      pageOverflow.scrollWidth,
      `halaman menggulir mendatar: ${pageOverflow.scrollWidth} > ${pageOverflow.clientWidth}`,
    ).toBeLessThanOrEqual(pageOverflow.clientWidth + 1)

    // Dan wrapper masing-masing rekap BENAR-BENAR menggulir: 26 dan 25 kolom
    // tidak mungkin muat di viewport default, jadi scrollWidth wrapper-nya
    // WAJIB melampaui clientWidth-nya. Nol gulir di sini berarti tabelnya
    // terpotong, bukan rapi.
    for (const testid of ['by-unit', 'daily-recap-details']) {
      const wrapper = page.locator(`[data-testid="${testid}"] .md-recap`).first()

      await expect(wrapper, testid).toBeVisible()

      const metrics = await wrapper.evaluate((element) => ({
        scrollWidth: element.scrollWidth,
        clientWidth: element.clientWidth,
        overflowX: window.getComputedStyle(element).overflowX,
      }))

      expect(metrics.overflowX, testid).toBe('auto')
      expect(
        metrics.scrollWidth,
        `${testid}: wrapper tidak menggulir (${metrics.scrollWidth} <= ${metrics.clientWidth})`,
      ).toBeGreaterThan(metrics.clientWidth)
    }

    // Digulir sampai kolom terakhir, lalu ditutup kembali.
    await page.locator('[data-testid="daily-recap-details"] .md-recap').first()
      .evaluate((element) => { element.scrollLeft = element.scrollWidth })

    await toggle.click()
    await expect(table).toBeHidden()

    // Angka utama dan kedua seri harian tetap terbaca pada KEDUA keadaan, dan
    // halaman tetap tanpa gulir mendatar sesudahnya.
    await expect(page.locator('[data-testid="coverage-slots"]')).toHaveText(slotsBefore)
    await expect(page.locator('[data-testid="metric-card"]')).toHaveCount(22)
    await expect(page.locator('[data-testid="by-unit-table"]')).toBeVisible()

    const afterClose = await page.evaluate(() => {
      const root = document.scrollingElement ?? document.documentElement

      return { scrollWidth: root.scrollWidth, clientWidth: root.clientWidth }
    })

    expect(afterClose.scrollWidth).toBeLessThanOrEqual(afterClose.clientWidth + 1)
  })

  // Scenario 35: ekspor
  test('Ekspor CSV dan Ekspor Excel benar-benar mengunduh berkas', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    await expect(page.locator('[data-testid="export-button"]')).toBeVisible()
    await expect(page.locator('[data-testid="export-excel-button"]')).toBeVisible()

    const csvPromise = page.waitForEvent('download')
    await page.locator('[data-testid="export-button"]').click()
    const csv = await csvPromise

    expect(csv.suggestedFilename()).toContain('laporan-engine-room')
    expect(csv.suggestedFilename()).toMatch(/\.csv$/)

    const excelPromise = page.waitForEvent('download')
    await page.locator('[data-testid="export-excel-button"]').click()
    const excel = await excelPromise

    expect(excel.suggestedFilename()).toContain('laporan-engine-room')
    // .xlsx SUNGGUHAN lewat SheetWriter, bukan CSV bernama .xlsx.
    expect(excel.suggestedFilename()).toMatch(/\.xlsx$/)
  })

  // Scenario 28: layar hanya membaca
  test('layar hanya membaca: tidak ada kontrol tulis apa pun', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    await expect(page.getByRole('button', { name: /^Simpan/ })).toHaveCount(0)
    await expect(page.getByRole('button', { name: /^Hapus/ })).toHaveCount(0)
    await expect(page.getByRole('button', { name: /^Ubah/ })).toHaveCount(0)
    await expect(page.getByRole('button', { name: /^Verifikasi/ })).toHaveCount(0)

    // DIBATASI PADA AKAR LAPORANNYA, bukan atas halaman: layout membawa satu
    // <form> logout, dan `page.locator('form')` akan selalu menghitungnya —
    // kegagalan yang tidak ada hubungannya dengan layar ini. Di dalam akar
    // laporan tidak ada satu pun form, input, maupun textarea: ketiga
    // pemilihnya <select> ber-wire:model.
    const root = page.locator('[data-testid="laporan-engine-room"]')

    await expect(root.locator('form')).toHaveCount(0)
    await expect(root.locator('input')).toHaveCount(0)
    await expect(root.locator('textarea')).toHaveCount(0)
    await expect(root.locator('button[type="submit"]')).toHaveCount(0)
  })

  // Scenario 2: Admin
  test('Admin: pemilih mill dirender dan angka muncul setelah mill + line dipilih', async ({ page }) => {
    await login(page, ADMIN, PASSWORD)
    await page.goto(REPORT_PATH)

    await expect(page.locator('[data-testid="mill-select"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-select-hint"]')).toBeVisible()
    await expect(page.locator('[data-testid="coverage"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="metric-card"]')).toHaveCount(0)

    await page.locator('[data-testid="mill-select"]').selectOption({ label: BUSINESS_UNIT })
    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })

    await expect(page.locator('[data-testid="coverage"]')).toBeVisible()
    // Isinya identik dengan peran terikat mill.
    await expect(page.locator('[data-testid="metric-card"]')).toHaveCount(22)
    await expect(page.locator('[data-testid="energy-export-card"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="status-row"]')).toHaveCount(6)
    // Label pemilih Production Line memberi badge 'Aktif', dan nama mill +
    // nama line tampil di hero.
    await expect(page.locator('[data-testid="production-line-current"]')).toBeVisible()
    await expect(page.locator('[data-testid="hero-production-line"]')).toContainText(PRODUCTION_LINE)
    await expect(page.locator('[data-testid="report-hero"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="export-button"]')).toBeVisible()
  })

  // Scenario 5: Admin mengganti mill mengosongkan periode dan line
  test('Admin mengganti mill: pemilih periode dan line kembali ke keadaan belum terpilih', async ({ page }) => {
    await login(page, ADMIN, PASSWORD)
    await page.goto(REPORT_PATH)

    await page.locator('[data-testid="mill-select"]').selectOption({ label: BUSINESS_UNIT })
    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })
    await expect(page.locator('[data-testid="coverage"]')).toBeVisible()

    // Mill LAIN mana pun; yang penting ia bukan mill yang sedang dibaca.
    const options = await page.locator('[data-testid="mill-select"] option').all()
    let otherMill: string | null = null

    for (const option of options) {
      const label = (await option.innerText()).trim()
      const value = await option.getAttribute('value')

      if (value !== null && value !== '' && label !== BUSINESS_UNIT) {
        otherMill = label
        break
      }
    }

    if (otherMill === null) {
      test.skip(true, 'master mill e2e hanya punya satu mill — tidak ada mill lain untuk berganti')
    }

    await page.locator('[data-testid="mill-select"]').selectOption({ label: otherMill as string })

    // KEDUANYA kembali ke keadaan belum terpilih: sebuah periode MAUPUN
    // sebuah line milik tepat satu mill, jadi membawa salah satunya melintas
    // akan merender penolakan untuk sesuatu yang tidak dilakukan pengguna.
    await expect(page.locator('[data-testid="production-line-select"]')).toHaveValue('')
    await expect(page.locator('[data-testid="coverage"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="metric-card"]')).toHaveCount(0)
    // Dan TIDAK ADA pesan galat tentang periode yang tidak cocok — yang
    // dirender adalah ajakan memilih line.
    await expect(page.locator('[data-testid="forbidden-notice"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()
  })

  // Invarian (k): penjagaan peran pada RUTE-nya, dan TIGA jawaban berbeda.
  test('invarian (k) — Operator DITOLAK pada rute web, 200 pada ketiga rute API data, 403 pada pemilih mill', async ({ page }) => {
    await login(page, OPERATOR, PASSWORD)

    const response = await page.goto(REPORT_PATH)

    // LAYAR WEB TERTUTUP. Penjagaannya ada pada middleware rute DAN pada
    // canAccess() komponen, yang memegang daftar perannya SENDIRI justru
    // supaya penerimaan Operator di service — yang sudah berlaku HARI INI
    // demi kembaran mobile screen-159 — TIDAK dapat membuka layar web ini.
    expect(response?.status()).toBe(403)
    await expect(page.locator('[data-testid="laporan-engine-room"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="coverage"]')).toHaveCount(0)

    // Header stateful WAJIB ada. Tanpa X-Requested-With + Referer,
    // EnsureFrontendRequestsAreStateful tidak mengaktifkan session guard dan
    // Sanctum menjawab 401 SEBELUM guard peran dievaluasi — 401 itu akan lulus
    // sebagai "ditolak" padahal tidak membuktikan apa pun tentang peran.
    const headers = await statefulHeaders(page)

    // PEMILIH MILL TETAP 403 — 403 yang diangkat DARI DALAM
    // EngineRoomReportService::businessUnitOptions(), bukan oleh daftar peran
    // middleware yang memang menerima empat peran demi ketiga rute data.
    const options = await page.request.get(
      '/api/engine-room-reports/business-units/options',
      { headers },
    )

    expect(options.status()).toBe(403)

    // ────────────────────────────────────────────────────────────────────
    // KETIGA RUTE DATA: 200 SEJAK COMMIT PERTAMA
    // ────────────────────────────────────────────────────────────────────
    // Parameter period_id dan production_line_id DITURUNKAN dari jawaban
    // server untuk Operator ini sendiri — bukan dari fixture mill
    // 'BU Browser Test', yang milik Supervisor/Admin dan BUKAN mill
    // operator01. Keduanya memang diselesaikan server dari AKUN, jadi
    // menurunkannya dari respons adalah satu-satunya cara memberi parameter
    // yang sah tanpa menanam data di database bersama ini.
    const periods = await page.request.get('/api/engine-room-reports/periods', { headers })

    expect(periods.status()).toBe(200)

    const periodId = ((await periods.json()).data ?? [])[0]?.id

    const lines = await page.request.get('/api/production-lines/options-for-report', { headers })

    expect(lines.status()).toBe(200)

    const lineId = ((await lines.json()).data ?? [])[0]?.id

    // Diasersi, bukan dilewati diam-diam: tanpa keduanya kedua asersi di bawah
    // menjadi 422 tentang parameter yang hilang, dan "tidak 403" yang lulus
    // karena alasan yang salah adalah asersi yang berhenti dibaca orang.
    expect(periodId, 'mill operator01 tidak punya satu pun periode Engine Room').toBeTruthy()
    expect(lineId, 'mill operator01 tidak punya satu pun Production Line').toBeTruthy()

    const summary = await page.request.get(
      `/api/engine-room-reports/summary?period_id=${periodId}&production_line_id=${lineId}`,
      { headers },
    )

    expect(summary.status()).toBe(200)

    const summaryBody = await summary.json()

    // DUA PULUH DUA kunci metrik, dan kolom ke-23 pada kuncinya SENDIRI.
    expect(Object.keys(summaryBody.metrics ?? {})).toHaveLength(22)
    expect(summaryBody.metrics?.[ENERGY_COLUMN]).toBeUndefined()
    expect(summaryBody.daily_energy_export).toBeDefined()
    // Dan KEDUA sebaran status dengan TIGA nilai dikenal masing-masing.
    expect(Object.keys(summaryBody.status_distribution ?? {})).toHaveLength(2)
    expect(summaryBody.status_distribution?.diesel_gen_1_status?.known_values)
      .toEqual(['run', 'standby', 'off'])

    const exported = await page.request.get(
      `/api/engine-room-reports/export?period_id=${periodId}&production_line_id=${lineId}&format=csv`,
      { headers },
    )

    expect(exported.status()).toBe(200)
    expect(exported.headers()['content-type']).toContain('text/csv')

    // Dan mill yang terbaca adalah mill AKUN, bukan mill fixture spec ini:
    // business_unit_id kiriman klien memang tidak pernah dihormati bagi peran
    // terikat mill — tempat KETIGA dari tiga tempat penerimaan Operator, dan
    // tanpanya penerimaan itu adalah kebocoran lintas mill.
    const leak = await page.request.get(
      `/api/engine-room-reports/summary?period_id=${periodId}&production_line_id=${lineId}`
        + '&business_unit_id=00000000-0000-0000-0000-000000000000',
      { headers },
    )

    expect(leak.status()).toBe(200)
    expect((await leak.json()).business_unit?.id).toBe(summaryBody.business_unit?.id)
  })
})

test.describe('Pintu masuk dari Laporan Stasiun (screen-140)', () => {
  /**
   * Invarian (a) — SATU BARIS yang menentukan layar ini dapat dicapai: entri
   * 'engine-room' pada StationReportService::REPORT_ROUTES. Tanpa test ini,
   * layar laporannya bisa lengkap, seluruh test backend hijau, dan tile-nya
   * tetap kelabu — pola kegagalan yang sudah pernah terjadi pada laporan
   * Cages & Tracks versi web.
   */
  test('invarian (a) — tile Engine Room aktif dan menavigasi ke layar laporannya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(STATION_REPORT_PATH)

    // Grid tile screen-140 sendiri digate oleh Production Line — tile baru
    // digambar setelah satu line dipilih.
    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })
    await expect(page.locator('[data-testid="station-grid"]')).toBeVisible()

    const tile = page.locator('[data-testid="station-tile-engine-room"]')

    await expect(tile).toBeVisible()
    // AKTIF, bukan kelabu: kelas .active dan href yang benar-benar ada.
    await expect(tile).toHaveClass(/active/)
    await expect(tile).toHaveAttribute('href', /\/reports\/engine-room/)

    await tile.click()

    // DIBANDINGKAN SEBAGAI PATHNAME, bukan lewat toHaveURL('**/x'): pola glob
    // itu dipadu dengan baseURL TIDAK PERNAH cocok, dan kegagalannya terbaca
    // seperti navigasi yang tidak terjadi. Tautannya membawa business_unit_id
    // dan production_line_id, jadi query-nya diabaikan di sini.
    await page.waitForURL((url) => url.pathname === REPORT_PATH)
    expect(new URL(page.url()).pathname).toBe(REPORT_PATH)

    await expect(page.locator('[data-testid="laporan-engine-room"]')).toBeVisible()

    // Mill dan line ikut terbawa, jadi layar tujuan TIDAK meminta memilih
    // lagi — itu sebabnya kedua properti layar ini membawa
    // #[Url(as: 'business_unit_id')] dan #[Url(as: 'production_line_id')]
    // dengan `as:` yang eksplisit: tanpa `as:`, kuncinya menjadi
    // 'businessUnitId'/'productionLineId' dan tautan dari screen-140 tidak
    // pernah terbaca, tanpa satu tanda pun bahwa ada yang salah.
    await expect(page.locator('[data-testid="select-production-line-hint"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="coverage"]')).toBeVisible()
    await expect(page.locator('[data-testid="hero-production-line"]')).toContainText(PRODUCTION_LINE)
  })
})
