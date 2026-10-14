import { expect, test, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { removeOpenPeriodForForms, seedOpenPeriodForForms } from './support/period-fixture'
import { statefulHeaders } from './support/periods'

/**
 * Laporan Periode Effluent Plant (Browser/Playwright) — screen-156--laporan-effluent-plant-web /
 * usecase-162--laporan-effluent-plant-web.
 *
 * ────────────────────────────────────────────────────────────────────────
 * ASERSI INVARIAN, BUKAN ANGKA YANG DIPAKU
 * ────────────────────────────────────────────────────────────────────────
 * Spec ini TIDAK menanam data laporannya sendiri dan TIDAK mengasersi satu
 * angka tetap pun — pola yang sama dengan tests/laporan-kernel-plant.spec.ts,
 * dengan dua alasan yang sama dan keduanya menentukan:
 *
 * 1. Angka laporan ini sudah dibuktikan TIGA KALI di backend, terhadap data
 *    yang dikendalikan penuh — atas service-nya (63 kasus unit di
 *    backend/tests/Unit/Services/EffluentPlantReportServiceTest.php), atas
 *    kontrak HTTP-nya (36 skenario di
 *    backend/tests/Feature/Api/LaporanEffluentPlantTest.php), dan atas markup
 *    ter-render komponennya (38 skenario di
 *    backend/tests/Feature/Livewire/LaporanEffluentPlantTest.php).
 *    Mengulangnya lewat browser hanya memperlambat suite.
 *
 * 2. Database e2e ini BERBAGI data Effluent Plant dengan spec lain:
 *    BrowserTestFixtureSeeder menanam recordnya, form-effluent-plant menanam
 *    record lain lewat UI, detail-effluent-plant dan
 *    data-browser-effluent-plant menanam deret EP-nya sendiri, dan periode
 *    prasyaratnya berumur panjang. Angka yang dipaku di sini akan berubah
 *    setiap kali salah satu dari itu berubah — test yang gagal bukan karena
 *    produknya salah adalah test yang akan diabaikan orang, lalu dihapus.
 *
 * PERIOD_LANES (tests/support/period-lanes.ts) SENGAJA TIDAK DIPAKAI: itu
 * perangkat enam spec laporan yang lebih tua, yang menanam periodenya sendiri
 * di mill terpisah. Spec laporan terbaru memakai period-fixture.ts, dan
 * mencampur keduanya akan menghasilkan periode bertumpang-tindih yang ditolak
 * 422 PERIOD_OVERLAP.
 *
 * Yang diuji di sini karena HANYA browser dapat membuktikannya:
 *   (a) TILE Effluent Plant pada Laporan Stasiun (screen-140) menyala dan
 *       mendarat di layar ini. Tanpa test ini, satu baris yang hilang pada
 *       StationReportService::REPORT_ROUTES membuat layar ini ada, seluruh
 *       test backend lulus, dan tile-nya tetap kelabu — pola kegagalan yang
 *       sudah pernah terjadi pada laporan Cages & Tracks versi web. Kuncinya
 *       'effluent-plant' BERTANDA HUBUNG, yang memang dipegang
 *       StationTypeEnum::EffluentPlant->value; kunci snake_case akan
 *       ter-compile, tampak benar, dan tidak pernah cocok dengan satu baris
 *       master pun.
 *   (b) HALAMANNYA HIDUP: Livewire terpasang dan wire:model bekerja, sehingga
 *       pemilih-pemilihnya memicu putaran yang mengubah halaman. Component
 *       test tidak dapat menangkap jebakan wire:id-menempel-pada-<style> yang
 *       pernah mematikan SELURUH wire:model di screen-140, dan laporan ini
 *       menaruh partial ber-<style>-nya di slot `styles` layout justru karena
 *       itu.
 *   (c) Blok KELENGKAPAN berada DI ATAS seluruh kartu metrik — diukur dari
 *       posisi kotaknya di viewport, bukan dari urutan sumbernya.
 *   (d) KEEMPAT BELAS kartu metrik mencetak PENYEBUTNYA SENDIRI, dan ketiga
 *       kartu laboratorium boleh berpenyebut jauh lebih kecil. PENYEBUT YANG
 *       SAMA PADA KEEMPAT BELASNYA ADALAH JUSTRU CACAT YANG DICARI, dan
 *       bentuk asersinya yang menangkapnya tanpa memaku angka: sebuah kartu
 *       yang nilainya "tidak tersedia" WAJIB mencetak penyebut 0 — penyebut
 *       bersama akan mencetak jumlah slot terisi periode di sana.
 *   (e) TIDAK ADA satu pun ELEMEN berkelas penanda di luar batas, disisir atas
 *       NAMA KELAS lewat locator — bukan atas frasa dan bukan atas sumber
 *       halaman. Kalimat yang menyatakan ketiadaannya sendiri memuat frasa
 *       "di luar batas", dan `.md-threshold` DIDEFINISIKAN pada partial
 *       stylesheet bersama report-styles.blade.php, jadi namanya muncul pada
 *       HTML setiap laporan tanpa pernah dipakai. Locator menghitung ELEMEN,
 *       dan itulah sebabnya bentuk locator yang benar.
 *   (f) TIDAK ADA BAGIAN DOWNTIME — dan asersinya atas TESTID dan JUDUL, BUKAN
 *       atas substring 'downtime'. Pernyataan ketiadaannya memakai
 *       data-testid="no-downtime-note", jadi penyisiran substring akan gagal
 *       pada elemen yang justru membuktikan klaimnya.
 *   (g) KETIGA sebaran status menjumlah ke slot tercatat, dan barisnya tetap
 *       terender lengkap — termasuk baris bernilai 0.
 *   (h) Jumlah slot per unit menjumlah ke slot terisi periode.
 *   (i) Rekap harian dapat dibuka/ditutup tanpa mengubah satu angka pun.
 *   (j) PERNYATAAN KEPATUHAN LINGKUNGAN terender DI DALAM blok Buangan Akhir,
 *       bukan sebagai catatan kaki halaman.
 *   (k) Operator DITOLAK pada rute WEB — sementara KETIGA rute API DATA-nya
 *       justru menerimanya 200 sejak screen-157 (laporan Effluent Plant
 *       mobile) mendarat, dan /business-units/options tetap 403. Ketiga
 *       jawaban yang berbeda itulah invariannya: pelebaran API tidak
 *       melebarkan layar web ini, dan tidak melebarkan pemilih mill.
 *   - EKSPOR benar-benar mengunduh berkas.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TUJUH KEADAAN YANG SENGAJA TIDAK ADA DI SINI
 * ────────────────────────────────────────────────────────────────────────
 * Mill tanpa periode Effluent Plant, periode berisi record tetapi nol slot
 * terisi, periode yang belum mulai (slot harapan nol), periode yang masih
 * berjalan, nilai laboratorium yang tidak pernah diisi sepanjang periode,
 * akun terikat mill dengan business_unit_id null, dan id mill/line mill lain
 * lewat properti komponen — ketujuhnya menuntut database yang disusun per
 * test, dan database e2e ini DIPAKAI BERSAMA puluhan spec lain. Ketujuhnya
 * dibuktikan di backend/tests/Feature/Livewire/LaporanEffluentPlantTest.php,
 * yang memang punya database sendiri per test.
 */

const REPORT_PATH = '/reports/effluent-plant'
const STATION_REPORT_PATH = '/reports'

const BUSINESS_UNIT = 'BU Browser Test'
const PRODUCTION_LINE = 'PL Mill A'

const SUPERVISOR = 'stest-supervisor01'
const ADMIN = 'brtest-admin01'
/**
 * Operator tidak punya akses ke layar WEB ini, dan tidak pernah akan punya:
 * penjagaannya ada pada middleware rute DAN pada canAccess() komponen, yang
 * memegang daftar perannya SENDIRI justru supaya pelebaran service tidak
 * dapat membukanya secara tidak sengaja.
 *
 * Yang BERUBAH pada 2026-10-13 adalah sisi API-nya. Sampai hari itu keempat
 * rute /api/effluent-plant-reports/* menolak Operator 403 — berbeda dari
 * Grading/Kernel Plant, yang menerimanya pada ketiga rute datanya sejak
 * commit pertama karena kembaran mobile-nya dibangun dalam perubahan yang
 * sama. Kembaran mobile Effluent Plant (screen-157) memang ditunda, dan
 * penundaan itu dicatat sebagai langkah tersendiri yang dapat ditinjau.
 * screen-157 KINI DIBANGUN, jadi ketiga rute datanya menerima Operator 200
 * dan hanya /business-units/options yang tetap 403.
 */
const OPERATOR = 'operator01'

/** KEEMPAT BELAS kolom ukur, dalam urutan blok tata letaknya di layar. */
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

/** KETIGA metrik laboratorium — penyebut kecil pada ketiganya NORMAL. */
const LAB_COLUMNS = [
  'final_discharge_bod_mgl_lab',
  'final_discharge_cod_mgl_lab',
  'final_discharge_tss_mgl_lab',
]

/**
 * KETIGA sebaran status dan jumlah baris nilai yang WAJIB terender masing-
 * masing, termasuk baris bernilai 0: himpunan nilainya tertutup di basis data
 * ($table->enum -> CHECK constraint).
 */
const STATUS_BLOCKS = [
  { testid: 'status-distribution-biogas-flare', rows: 3 },
  { testid: 'status-distribution-dosing-pump-1', rows: 2 },
  { testid: 'status-distribution-sludge-dewatering', rows: 2 },
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
  await expect(page.locator('[data-testid="laporan-effluent-plant"]')).toBeVisible()

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
 * yang mencakup Effluent Plant di "BU Browser Test", pemilih periodenya
 * kosong, `[data-testid="coverage"]` tidak pernah muncul, dan hampir seluruh
 * skenarionya gagal sebagai "element(s) not found" — seolah produknya yang
 * rusak. Itu benar-benar terjadi pada 2026-10-06 untuk empat spec laporan yang
 * menumpang periode `form-*` tanpa menyebutkannya, dan butuh setengah jam
 * untuk dibuktikan BUKAN regresi.
 *
 * seedOpenPeriodForForms() idempoten dan MEMAKAI ULANG periode yang sudah ada
 * (findPeriodByPrefix), jadi memanggilnya tidak menambah periode kedua ketika
 * spec `form-*` memang jalan lebih dulu — ia hanya menghapus ketergantungan
 * pada urutan alfabet, dan membuat spec ini dapat dijalankan SENDIRIAN setelah
 * scripts/prepare-db.sh.
 *
 * KUNCI STASIUNNYA 'effluent-plant' BERTANDA HUBUNG. Fixture mencocokkannya
 * dengan `station.station_type` dari API, yang berisi nilai enum-nya;
 * 'effluent_plant' gagal di period-fixture.ts dengan "periode prasyarat tidak
 * punya baris stasiun". Dan tanpa hook ini sama sekali, beforeAll MENGGANTUNG
 * alih-alih gagal.
 */
let periodFixturePage: Page | undefined

test.beforeAll(async ({ browser }) => {
  periodFixturePage = await seedOpenPeriodForForms(browser, 'effluent-plant')
})

test.afterAll(async () => {
  // Dijaga: kalau beforeAll gagal sebelum mengembalikan page-nya, afterAll
  // tidak boleh menimpa kegagalan itu dengan TypeError miliknya sendiri.
  if (periodFixturePage !== undefined) {
    await removeOpenPeriodForForms(periodFixturePage)
  }
})

test.describe('Laporan Periode Effluent Plant (screen-156)', () => {
  // Scenario: "Production Line belum dipilih"
  test('Supervisor: tanpa pemilih mill, dan NOL angka sebelum line dipilih', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(REPORT_PATH)

    await expect(page.locator('[data-testid="laporan-effluent-plant"]')).toBeVisible()

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
  })

  // Invarian (c): cakupan dibaca lebih dulu.
  test('invarian (c) — blok kelengkapan berada DI ATAS seluruh kartu metrik di viewport', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const firstCard = page.locator('[data-testid="metric-card"]').first()

    await expect(firstCard).toBeVisible()

    const coverage = await page.locator('[data-testid="coverage"]').boundingBox()
    const metrics = await firstCard.boundingBox()
    const block = await page.locator('[data-testid="metric-block-anaerobic-pond-1"]').boundingBox()

    expect(coverage).not.toBeNull()
    expect(metrics).not.toBeNull()
    expect(block).not.toBeNull()

    // Diukur dari POSISI di viewport, bukan dari urutan sumbernya: periode
    // yang terisi seperlima pun menghasilkan rata-rata yang terlihat rapi, dan
    // pembaca harus melihat kelengkapannya lebih dulu. Kelengkapan adalah ISI
    // laporan, bukan metadata.
    expect(coverage!.y).toBeLessThan(metrics!.y)
    expect(coverage!.y).toBeLessThan(block!.y)

    // Ketiga angka pembentuk penyebut ikut tercetak, bukan hanya persennya:
    // unit Effluent Plant yang benar-benar punya record, hari yang dihitung,
    // dan 24 slot kanonis per hari.
    await expect(page.locator('[data-testid="coverage-formula"]')).toContainText('unit')
    await expect(page.locator('[data-testid="coverage-formula"]')).toContainText('hari')
    await expect(page.locator('[data-testid="coverage-formula"]')).toContainText('24 slot')

    // Persentase diasersi HANYA pada sel cakupannya, tidak pernah atas
    // halaman: keterangan cakupan dan ketiga sebaran status memuat banyak
    // persentase lain.
    const percent = await page.locator('[data-testid="coverage-percent"]').innerText()

    expect(percent.includes('%') || percent.includes('—'), `sel cakupan tak terbaca: ${percent}`).toBe(true)
  })

  // Invarian (d): INTI LAPORAN INI.
  test('invarian (d) — keempat belas kartu mencetak penyebutnya SENDIRI, dan tiga kartu lab bertanda', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    // KEEMPAT BELAS kartu SELALU dirender, termasuk yang tidak pernah
    // diukur: kartu yang hilang terbaca sebagai "parameter itu tidak ada",
    // padahal yang benar adalah "tidak ada yang mengukurnya".
    await expect(page.locator('[data-testid="metric-card"]')).toHaveCount(14)
    await expect(page.locator('[data-testid="metric-denominator"]')).toHaveCount(14)

    // TEPAT TIGA penanda laboratorium, diturunkan dari `is_lab` pada respons
    // dan tidak dipaku di markup.
    await expect(page.locator('[data-testid="metric-lab-badge"]')).toHaveCount(3)

    for (const column of LAB_COLUMNS) {
      await expect(page.locator(`[data-metric="${column}"] [data-testid="metric-lab-badge"]`))
        .toHaveCount(1)
    }

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
      // Invarian ini benar untuk data apa pun.
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

    // Dan tiap kartu mencetak terendah/tertinggi di kartu yang SAMA dengan
    // rata-rata dan penyebutnya — tidak dikumpulkan di satu catatan terpisah.
    await expect(page.locator('[data-testid="metric-range"]')).toHaveCount(14)
    await expect(page.locator('[data-testid="metric-avg"]')).toHaveCount(14)

    expect(denominators).toHaveLength(14)
  })

  // Invarian (e): tidak ada penandaan otomatis di luar batas.
  test('invarian (e) — nol ELEMEN berkelas penanda di luar batas pada halaman ter-render', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)
    await expect(page.locator('[data-testid="metric-card"]').first()).toBeVisible()

    // DISISIR ATAS NAMA KELAS LEWAT LOCATOR, dan tidak dengan dua cara lain
    // yang tampak setara:
    //   - bukan atas FRASA, karena kalimat yang menyatakan ketiadaan
    //     penandaan itu sendiri memuat frasa "di luar batas", jadi penyisiran
    //     teks justru gagal pada kalimat yang membuktikan klaimnya;
    //   - bukan atas SUMBER halaman, karena `.md-threshold` DIDEFINISIKAN di
    //     partial stylesheet bersama report-styles.blade.php — namanya muncul
    //     pada HTML setiap laporan tanpa pernah dipakai pada satu elemen pun.
    // Locator menghitung ELEMEN, dan hanya itu yang menjawab pertanyaannya.
    for (const className of [
      'md-threshold', 'is-danger', 'is-warning', 'is-critical', 'is-breach',
      'md-chip--danger', 'md-badge--danger',
    ]) {
      await expect(page.locator(`.${className}`), className).toHaveCount(0)
    }

    // Effluent Plant tidak punya tabel master target operasional sama sekali,
    // jadi tidak ada satu angka pun yang bisa dibandingkan terhadap standar
    // yang ditetapkan siapa pun — dan ketiadaan itu DINYATAKAN, karena
    // ketiadaan penandaan yang tidak dijelaskan terbaca sebagai fitur yang
    // belum selesai dan seseorang akan "melengkapinya".
    await expect(page.locator('[data-testid="no-downtime-note"]')).toBeVisible()
    await expect(page.locator('[data-testid="no-downtime-note"]'))
      .toContainText('Tidak ada pula satu nilai yang ditandai di luar batas')

    // Penonjolan jumlah slot bergangguan BUKAN penandaan di luar batas: ia
    // menghitung sebuah nilai yang memang tercatat, tidak menilai angka ukur
    // terhadap sebuah ambang — dan karena itu tidak memakai satu pun kelas
    // peringatan.
    const fault = page.locator('[data-testid="biogas-flare-fault-count"]')

    await expect(fault).toBeVisible()
    await expect(fault).toContainText('slot bergangguan')
  })

  // Invarian (f): tidak ada bagian downtime.
  test('invarian (f) — tidak ada bagian downtime, diasersi atas TESTID dan JUDUL', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)
    await expect(page.locator('[data-testid="metric-card"]').first()).toBeVisible()

    // DIASERSI ATAS TESTID DAN JUDUL, BUKAN atas substring 'downtime': laporan
    // ini MENYATAKAN ketiadaan bagian itu, dan pernyataannya sendiri memakai
    // data-testid="no-downtime-note". Penyisiran substring akan gagal pada
    // elemen yang justru membuktikan klaimnya — kesalahan yang persis sama
    // bentuknya dengan penyisiran frasa "di luar batas" di atas.
    await expect(page.locator('[data-testid="downtime"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="downtime-total"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="downtime-empty"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="downtime-note"]')).toHaveCount(0)
    await expect(page.getByRole('heading', { name: /^Downtime/ })).toHaveCount(0)
    await expect(page.getByRole('heading', { name: /Lama Berhenti/ })).toHaveCount(0)

    // Ketiadaannya DINYATAKAN — bagian yang hilang tanpa keterangan tidak
    // dapat dibedakan dari bagian yang gagal dimuat, dan laporan stasiun lain
    // memilikinya sehingga pembaca yang datang dari sana akan mencarinya.
    await expect(page.locator('[data-testid="no-downtime-note"]'))
      .toContainText('tidak mencatat lama berhenti sama sekali')
  })

  // Invarian (g): tiga sebaran status kategoris.
  test('invarian (g) — ketiga sebaran status menjumlah ke slot tercatat, baris 0 tetap terender', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const filledSlots = await filledSlotsOf(page)

    for (const block of STATUS_BLOCKS) {
      const card = page.locator(`[data-testid="${block.testid}"]`)

      await expect(card, block.testid).toBeVisible()

      // NILAI DIKENAL YANG TIDAK PERNAH MUNCUL TETAP DIRENDER DENGAN NOL:
      // baris yang hilang tidak dapat dibedakan dari nilai yang tidak pernah
      // ada di basis data. Jumlah barisnya karena itu TETAP, apa pun datanya.
      await expect(card.locator('[data-testid="status-row"]'), block.testid)
        .toHaveCount(block.rows)

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
      // "Tidak tercatat" adalah KEADAAN TERSENDIRI dan tidak pernah dilipat ke
      // 'Mati' maupun 'Berhenti': melakukannya akan melaporkan flare mati atau
      // pompa berhenti yang tidak pernah terjadi.
      expect(
        counts.reduce((sum, value) => sum + value, 0) + (notRecorded as number),
        block.testid,
      ).toBe(recorded as number)
      expect(recorded as number, block.testid).toBe(filledSlots)
    }

    // Label Bahasa Indonesia di LAYAR (berkas ekspor memakai label layar
    // Detail — dua pembaca yang berbeda, dua konvensi yang memang berbeda).
    const flare = page.locator('[data-testid="status-distribution-biogas-flare"]')

    await expect(flare).toContainText('Nyala')
    await expect(flare).toContainText('Mati')
    await expect(flare).toContainText('Gangguan')
    await expect(flare).toContainText('Tidak tercatat')

    // Dan ketiganya TIDAK PERNAH muncul sebagai kartu metrik maupun seri tren:
    // terendah, rata-rata dan tertinggi tidak punya arti untuk kolom
    // kategoris.
    for (const column of ['biogas_flare_status', 'dosing_pump_1_status', 'sludge_dewatering_status']) {
      await expect(page.locator(`[data-metric="${column}"]`), column).toHaveCount(0)
    }

    await expect(page.locator('[data-testid="status-distribution-note"]'))
      .toContainText('jumlah slot tercatat sebagai penyebut')
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
    // satu slot pun — dan effluent_plant_id yang sama pada dua tanggal adalah
    // SATU baris dengan dua hari pencatatan, bukan dua baris, karena ia NAMA
    // UNIT yang diketik di layar input dan bukan kunci baris.
    expect(perUnit.reduce((sum, value) => sum + value, 0)).toBe(filledSlots)

    // Dan baris total menyatakan gabungannya, dihitung ulang atas seluruh slot
    // periode — bukan rata-rata dari rata-rata per unit.
    await expect(page.locator('[data-testid="by-unit-row-total"]')).toContainText('Gabungan')
    await expect(page.locator('[data-testid="total-reading-rows"]')).toBeVisible()
  })

  // Invarian (i): rekap harian dapat ditutup.
  test('invarian (i) — rekap harian dapat dibuka/ditutup tanpa mengubah satu angka pun', async ({ page }) => {
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
    // yang membuat klaim "tidak mengubah satu angka pun" dapat dibuktikan:
    // seluruh angka halaman ini dihitung di service.
    await expect(table).toBeHidden()

    await toggle.click()
    await expect(table).toBeVisible()

    await toggle.click()
    await expect(table).toBeHidden()

    // Angka utama dan kedua grafik tren tetap terbaca pada KEDUA keadaan.
    await expect(page.locator('[data-testid="coverage-slots"]')).toHaveText(slotsBefore)
    await expect(page.locator('[data-testid="daily-trend-raw-pome-feed"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-trend-final-discharge-ph"]')).toBeVisible()
    await expect(page.locator('[data-testid="metric-card"]')).toHaveCount(14)
  })

  // Invarian (j): pernyataan kepatuhan lingkungan.
  test('invarian (j) — pernyataan kepatuhan lingkungan terender DI DALAM blok Buangan Akhir', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const block = page.locator('[data-testid="metric-block-final-discharge"]')

    await expect(block).toBeVisible()

    // DI DALAM blok itu, bukan di catatan kaki halaman — diasersi sebagai
    // KETURUNAN elemennya, bentuk paling langsung dari klaim "di dekat
    // angkanya". Angka lingkungan tanpa batas hukumnya mengundang pembaca
    // memasok batas itu dari ingatan, dan catatan kaki di ujung halaman dibaca
    // terakhir atau tidak dibaca sama sekali.
    const note = block.locator('[data-testid="environmental-compliance-note"]')

    await expect(note).toHaveCount(1)
    await expect(note).toBeVisible()
    await expect(note).toContainText('tidak menyatakan kepatuhan lingkungan')
    await expect(note).toContainText('regulasinya ditetapkan di luar aplikasi ini')

    // Keempat parameter buangan akhir ada di blok yang sama dengan
    // pernyataannya.
    for (const column of ['final_discharge_ph', ...LAB_COLUMNS]) {
      await expect(block.locator(`[data-metric="${column}"]`), column).toHaveCount(1)
    }

    // Dan BUKAN di footer: pernyataannya berada di atas blok kelengkapan
    // record.
    const noteBox = await note.boundingBox()
    const completeness = await page.locator('[data-testid="completeness"]').boundingBox()

    expect(noteBox).not.toBeNull()

    if (completeness !== null) {
      expect(noteBox!.y).toBeLessThan(completeness.y)
    }

    // TIDAK ADA label, badge, atau kolom kesimpulan 'patuh'/'tidak patuh' di
    // mana pun — diasersi atas PENANDA markup, bukan atas kata di dalam
    // kalimat yang justru menyatakan ketiadaannya.
    await expect(page.locator('[data-testid="compliance-badge"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="compliance-verdict"]')).toHaveCount(0)
    await expect(page.locator('.md-badge--compliant')).toHaveCount(0)
  })

  // Scenario: dua rekap teks bebas yang TERPISAH
  test('Rekap Catatan Perawatan dan Rekap Temuan adalah DUA bagian terpisah', async ({ page }) => {
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

  // Scenario: dua tren harian saja
  test('grafik tren memuat TEPAT dua seri: laju POME masuk dan pH buangan akhir', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    if (await page.locator('[data-testid="daily-trend-raw-pome-feed"]').count() === 0) {
      test.skip(true, 'periode terpilih tidak punya data pada line ini')
    }

    await expect(page.locator('[data-testid="daily-trend-raw-pome-feed"]')).toBeVisible()
    await expect(page.locator('[data-testid="daily-trend-final-discharge-ph"]')).toBeVisible()

    // Ketiga nilai laboratorium SENGAJA tidak digrafikkan harian: dengan
    // penyebut sekecil itu garisnya akan lebih banyak bolongnya daripada
    // datanya, dan garis yang menyambung dua titik berjauhan menyiratkan
    // pengukuran yang tidak pernah dilakukan.
    for (const column of LAB_COLUMNS) {
      await expect(page.locator(`[data-testid="daily-trend-${column}"]`), column).toHaveCount(0)
    }

    await expect(page.locator('[data-testid="daily-trend-bod"]')).toHaveCount(0)
  })

  // Scenario: ekspor
  test('Ekspor CSV dan Ekspor Excel benar-benar mengunduh berkas', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    await expect(page.locator('[data-testid="export-button"]')).toBeVisible()
    await expect(page.locator('[data-testid="export-excel-button"]')).toBeVisible()

    const csvPromise = page.waitForEvent('download')
    await page.locator('[data-testid="export-button"]').click()
    const csv = await csvPromise

    expect(csv.suggestedFilename()).toContain('laporan-effluent-plant')
    expect(csv.suggestedFilename()).toMatch(/\.csv$/)

    const excelPromise = page.waitForEvent('download')
    await page.locator('[data-testid="export-excel-button"]').click()
    const excel = await excelPromise

    expect(excel.suggestedFilename()).toContain('laporan-effluent-plant')
    // .xlsx SUNGGUHAN lewat SheetWriter, bukan CSV bernama .xlsx.
    expect(excel.suggestedFilename()).toMatch(/\.xlsx$/)
  })

  // Scenario: layar hanya membaca
  test('layar hanya membaca: tidak ada kontrol tulis apa pun', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    await expect(page.getByRole('button', { name: /^Simpan/ })).toHaveCount(0)
    await expect(page.getByRole('button', { name: /^Hapus/ })).toHaveCount(0)
    await expect(page.getByRole('button', { name: /^Verifikasi/ })).toHaveCount(0)

    // DIBATASI PADA AKAR LAPORANNYA, bukan atas halaman: layout membawa satu
    // <form> logout, dan `page.locator('form')` akan selalu menghitungnya —
    // kegagalan yang tidak ada hubungannya dengan layar ini. Di dalam akar
    // laporan tidak ada satu pun form, input, maupun textarea: ketiga
    // pemilihnya <select> ber-wire:model.
    const root = page.locator('[data-testid="laporan-effluent-plant"]')

    await expect(root.locator('form')).toHaveCount(0)
    await expect(root.locator('input')).toHaveCount(0)
    await expect(root.locator('textarea')).toHaveCount(0)
  })

  // Scenario: Admin
  test('Admin: pemilih mill dirender dan angka muncul setelah mill + line dipilih', async ({ page }) => {
    await login(page, ADMIN, PASSWORD)
    await page.goto(REPORT_PATH)

    await expect(page.locator('[data-testid="mill-select"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-select-hint"]')).toBeVisible()
    await expect(page.locator('[data-testid="coverage"]')).toHaveCount(0)

    await page.locator('[data-testid="mill-select"]').selectOption({ label: BUSINESS_UNIT })
    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })

    await expect(page.locator('[data-testid="coverage"]')).toBeVisible()
    // Label pemilih Production Line memberi badge 'Aktif'.
    await expect(page.locator('[data-testid="production-line-current"]')).toBeVisible()
    await expect(page.locator('[data-testid="export-button"]')).toBeVisible()
  })

  // Invarian (k): penjagaan peran pada RUTE-nya, dan TIGA jawaban berbeda.
  test('invarian (k) — Operator DITOLAK pada rute web, 200 pada ketiga rute API data, 403 pada pemilih mill', async ({ page }) => {
    await login(page, OPERATOR, PASSWORD)

    const response = await page.goto(REPORT_PATH)

    // LAYAR WEB TETAP TERTUTUP, dan itu bagian yang tidak berubah.
    // Penjagaannya ada pada middleware rute DAN pada canAccess() komponen,
    // yang memegang daftar perannya SENDIRI justru supaya pelebaran service
    // untuk screen-157 (laporan mobile Effluent Plant) TIDAK dapat membuka
    // layar web ini secara tidak sengaja. Pelebaran itu sudah mendarat, dan
    // asersi ini membuktikan ia tidak merembes ke sini.
    expect(response?.status()).toBe(403)
    await expect(page.locator('[data-testid="laporan-effluent-plant"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="coverage"]')).toHaveCount(0)

    // Header stateful WAJIB ada. Tanpa X-Requested-With + Referer,
    // EnsureFrontendRequestsAreStateful tidak mengaktifkan session guard dan
    // Sanctum menjawab 401 SEBELUM guard peran dievaluasi — 401 itu akan lulus
    // sebagai "ditolak" padahal tidak membuktikan apa pun tentang peran.
    // Keenam spec laporan lain memanggil API lewat statefulHeaders() untuk
    // alasan yang sama (support/periods.ts).
    const headers = await statefulHeaders(page)

    // PEMILIH MILL TETAP 403 — 403 yang diangkat DARI DALAM
    // EffluentPlantReportService::businessUnitOptions(), bukan oleh daftar
    // peran middleware yang screen-157 lebarkan. Operator terikat SATU mill,
    // jadi meminta daftar seluruh mill bukan "daftar berisi satu" melainkan
    // 403, dan layar mobile itu memang tidak punya jalur kode yang
    // memanggilnya.
    const options = await page.request.get(
      '/api/effluent-plant-reports/business-units/options',
      { headers },
    )

    expect(options.status()).toBe(403)

    // ────────────────────────────────────────────────────────────────────
    // KETIGA RUTE DATA: 200, BUKAN 403 LAGI
    // ────────────────────────────────────────────────────────────────────
    // Parameter period_id dan production_line_id DITURUNKAN dari jawaban
    // server untuk Operator ini sendiri — bukan dari fixture mill
    // 'BU Browser Test', yang milik Supervisor/Admin dan BUKAN mill
    // operator01. Keduanya memang diselesaikan server dari AKUN, jadi
    // menurunkannya dari respons adalah satu-satunya cara memberi parameter
    // yang sah tanpa menanam data di database bersama ini.
    const periods = await page.request.get('/api/effluent-plant-reports/periods', { headers })

    expect(periods.status()).toBe(200)

    const periodId = ((await periods.json()).data ?? [])[0]?.id

    const lines = await page.request.get('/api/production-lines/options-for-report', { headers })

    expect(lines.status()).toBe(200)

    const lineId = ((await lines.json()).data ?? [])[0]?.id

    // Diasersi, bukan dilewati diam-diam: tanpa keduanya kedua asersi di
    // bawah menjadi 422 tentang parameter yang hilang, dan "tidak 403" yang
    // lulus karena alasan yang salah adalah asersi yang berhenti dibaca
    // orang. Mill operator01 (Business Unit A pada DemoAccountSeeder) punya
    // periode Effluent Plant dan punya Production Line; bila suatu hari tidak
    // lagi punya, kegagalan ini menyebut sebabnya.
    expect(periodId, 'mill operator01 tidak punya satu pun periode Effluent Plant').toBeTruthy()
    expect(lineId, 'mill operator01 tidak punya satu pun Production Line').toBeTruthy()

    const summary = await page.request.get(
      `/api/effluent-plant-reports/summary?period_id=${periodId}&production_line_id=${lineId}`,
      { headers },
    )

    expect(summary.status()).toBe(200)

    const summaryBody = await summary.json()

    const exported = await page.request.get(
      `/api/effluent-plant-reports/export?period_id=${periodId}&production_line_id=${lineId}&format=csv`,
      { headers },
    )

    expect(exported.status()).toBe(200)
    expect(exported.headers()['content-type']).toContain('text/csv')

    // Dan mill yang terbaca adalah mill AKUN, bukan mill fixture spec ini:
    // business_unit_id kiriman klien memang tidak pernah dihormati bagi peran
    // terikat mill — itulah tempat ketiga dari tiga tempat pelebaran
    // screen-157, dan tanpanya pelebaran ini adalah kebocoran lintas mill.
    const leak = await page.request.get(
      `/api/effluent-plant-reports/summary?period_id=${periodId}&production_line_id=${lineId}`
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
   * 'effluent-plant' pada StationReportService::REPORT_ROUTES. Tanpa test ini,
   * layar laporannya bisa lengkap, seluruh test backend hijau, dan tile-nya
   * tetap kelabu — pola kegagalan yang sudah pernah terjadi pada laporan
   * Cages & Tracks versi web.
   */
  test('invarian (a) — tile Effluent Plant aktif dan menavigasi ke layar laporannya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(STATION_REPORT_PATH)

    // Grid tile screen-140 sendiri digate oleh Production Line — tile baru
    // digambar setelah satu line dipilih.
    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })
    await expect(page.locator('[data-testid="station-grid"]')).toBeVisible()

    const tile = page.locator('[data-testid="station-tile-effluent-plant"]')

    await expect(tile).toBeVisible()
    // AKTIF, bukan kelabu: kelas .active dan href yang benar-benar ada.
    await expect(tile).toHaveClass(/active/)
    await expect(tile).toHaveAttribute('href', /\/reports\/effluent-plant/)

    await tile.click()

    // DIBANDINGKAN SEBAGAI PATHNAME, bukan lewat toHaveURL('**/x'): pola glob
    // itu dipadu dengan baseURL TIDAK PERNAH cocok, dan kegagalannya terbaca
    // seperti navigasi yang tidak terjadi. Tautannya membawa business_unit_id
    // dan production_line_id, jadi query-nya diabaikan di sini.
    await page.waitForURL((url) => url.pathname === REPORT_PATH)
    expect(new URL(page.url()).pathname).toBe(REPORT_PATH)

    await expect(page.locator('[data-testid="laporan-effluent-plant"]')).toBeVisible()

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
