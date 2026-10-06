import { expect, test, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { removeOpenPeriodForForms, seedOpenPeriodForForms } from './support/period-fixture'

/**
 * Laporan Pressing (Browser/Playwright) — screen-150--laporan-pressing-web /
 * usecase-151--laporan-pressing-web.
 *
 * ────────────────────────────────────────────────────────────────────────
 * ASERSI INVARIAN, BUKAN ANGKA YANG DIPAKU
 * ────────────────────────────────────────────────────────────────────────
 * Spec ini TIDAK menanam datanya sendiri dan TIDAK mengasersi satu angka
 * tetap pun — pola yang sama dengan tests/laporan-grading.spec.ts, dengan dua
 * alasan yang sama dan keduanya menentukan:
 *
 * 1. Angka laporan ini sudah dibuktikan TIGA KALI di backend, terhadap data
 *    yang dikendalikan penuh — atas service-nya (46 kasus unit), atas kontrak
 *    HTTP-nya (25 skenario Api), dan atas markup ter-render komponennya (29
 *    skenario Livewire). Mengulangnya lewat browser hanya memperlambat suite.
 *
 * 2. Database e2e ini BERBAGI data Pressing dengan spec lain: record
 *    PR-BROWSER-EDIT ditanam BrowserTestFixtureSeeder, form-pressing menanam
 *    record lain, dan periode prasyaratnya berumur panjang. Angka yang dipaku
 *    di sini akan berubah setiap kali salah satu dari itu berubah — test yang
 *    gagal bukan karena produknya salah adalah test yang akan diabaikan orang,
 *    lalu dihapus.
 *
 * Yang diuji di sini karena HANYA browser dapat membuktikannya:
 *   - TILE Pressing pada Laporan Stasiun (screen-140) menyala dan mendarat di
 *     layar ini. Tanpa test ini, satu baris yang hilang pada
 *     StationReportService::REPORT_ROUTES membuat layar ini ada, seluruh test
 *     backend lulus, dan tile-nya tetap kelabu — pola kegagalan yang sudah
 *     pernah terjadi pada laporan Cages & Tracks versi web.
 *   - HALAMANNYA HIDUP: Livewire terpasang dan wire:model bekerja, sehingga
 *     pemilih-pemilihnya memicu putaran yang mengubah halaman. Component test
 *     tidak dapat menangkap jebakan wire:id-menempel-pada-<style> yang pernah
 *     mematikan SELURUH wire:model di screen-140, dan laporan ini menaruh
 *     partial ber-<style>-nya di slot `styles` layout justru karena itu.
 *   - LIMA INVARIAN yang dihitung DARI DOM, jadi benar untuk data apa pun:
 *       (a) blok cakupan berada DI ATAS tabel parameter — diukur dari posisi
 *           kotaknya di viewport, bukan dari urutan sumbernya;
 *       (b) tiap baris parameter mencetak penyebutnya sendiri, dan penyebut
 *           itu tidak pernah lebih besar daripada slot terisi periode;
 *       (c) kelima baris parameter selalu ada, termasuk yang tak terukur;
 *       (d) baris total rekap harian = jumlah slot seluruh baris hariannya;
 *       (e) jumlah slot per presser menjumlah ke slot terisi periode.
 *   - KETIADAAN PENANDAAN DI LUAR BATAS, disisir atas NAMA KELAS — bukan atas
 *     frasa, karena kalimat yang menyatakan ketiadaannya sendiri memuat frasa
 *     "di luar batas". Di sini godaannya lebih besar daripada pada Threshing:
 *     critical_trigger_action_limit MEMBAWA pembanding yang teratur
 *     ('< 85C', '> 50 Amps'), dan penolakannya tetap.
 *   - KEDUA KOLOM TARGET pada baris yang sama dengan angkanya — rentang kerja
 *     DAN batas tindakan, masing-masing dengan testid sendiri sehingga satu
 *     kolom yang hilang terlihat sebagai kegagalan.
 *   - EKSPOR benar-benar mengunduh berkas.
 *   - Penjagaan peran pada RUTE-nya, bukan hanya pada komponennya.
 */

const REPORT_PATH = '/reports/pressing'
const STATION_REPORT_PATH = '/reports'

const BUSINESS_UNIT = 'BU Browser Test'
const PRODUCTION_LINE = 'PL Mill A'

const SUPERVISOR = 'stest-supervisor01'
const ADMIN = 'brtest-admin01'
/** Operator adalah aktor mobile pada laporan ini — rute web tidak dibuka
 *  untuknya, meski ketiga rute API-nya menerimanya sejak hari pertama. */
const OPERATOR = 'operator01'

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
 * "sebuah periode berisi data", dan pemilih periode memang memilih sendiri
 * periode terbaru pada muat pertama.
 */
async function openReportWithData(page: Page): Promise<void> {
  await page.goto(REPORT_PATH)
  await expect(page.locator('[data-testid="laporan-pressing"]')).toBeVisible()

  await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })

  await expect(page.locator('[data-testid="report-hero"]')).toBeVisible()
  // Cakupan dirender untuk SETIAP periode yang sah, berdata atau tidak —
  // justru itu gunanya. Menunggunya membuktikan putaran Livewire mendarat.
  await expect(page.locator('[data-testid="coverage"]')).toBeVisible()
}

/** Seluruh angka pada satu kolom tabel, sebagai bilangan (null dibuang). */
async function columnNumbers(page: Page, selector: string): Promise<number[]> {
  const cells = await page.locator(selector).allInnerTexts()

  return cells
    .map((cell) => parseIdNumber(cell))
    .filter((value): value is number => value !== null)
}

/**
 * PRASYARAT PERIODE PELAPORAN — ditanam di sini sejak 2026-10-06.
 *
 * Spec ini sengaja tidak menanam datanya sendiri (lihat catatan panjang di
 * atas), dan sampai hari ini ia juga tidak menanam PERIODE-nya: ia menumpang
 * periode "Prasyarat Form" yang ditanam ke-18 spec `form-*` lewat
 * tests/support/period-fixture.ts dan — sejak 2026-10-04 — tidak lagi dihapus
 * di afterAll. Ketergantungan itu tidak tertulis di mana pun dan hanya
 * terpenuhi oleh KEBETULAN URUTAN: `form-*` jalan sebelum `laporan-*` secara
 * alfabet, dan playwright.config.ts memaksa satu worker.
 *
 * Akibatnya spec ini TIDAK BISA dijalankan sendirian setelah
 * scripts/prepare-db.sh. Tanpa satu pun periode di "BU Browser Test" pemilih
 * periodenya kosong, `[data-testid="coverage"]` tidak pernah muncul, dan
 * hampir seluruh skenarionya gagal sebagai "element(s) not found" — seolah
 * produknya yang rusak. Itu benar-benar terjadi pada 2026-10-06 dan butuh
 * setengah jam untuk dibuktikan BUKAN regresi. Enam spec laporan yang lebih
 * tua tidak punya masalah ini karena mereka menanam periodenya sendiri lewat
 * tests/support/period-lanes.ts; keempat spec laporan terbaru (grading,
 * threshing, pressing, depricarping) tidak.
 *
 * seedOpenPeriodForForms() idempoten dan MEMAKAI ULANG periode yang sudah ada
 * (findPeriodByPrefix), jadi memanggilnya di sini tidak menambah periode kedua
 * ketika spec `form-*` memang jalan lebih dulu — ia hanya menghapus
 * ketergantungan pada urutan. Biayanya satu login + tiga permintaan API per
 * spec, jauh di dalam anggaran 30 detik `beforeAll`.
 */
let periodFixturePage: Page | undefined

test.beforeAll(async ({ browser }) => {
  periodFixturePage = await seedOpenPeriodForForms(browser, 'pressing')
})

test.afterAll(async () => {
  // Dijaga: kalau beforeAll gagal sebelum mengembalikan page-nya, afterAll
  // tidak boleh menimpa kegagalan itu dengan TypeError miliknya sendiri.
  if (periodFixturePage !== undefined) {
    await removeOpenPeriodForForms(periodFixturePage)
  }
})

test.describe('Laporan Pressing (screen-150)', () => {
  // Scenario: "Production Line belum dipilih"
  test('Supervisor: tanpa pemilih mill, dan NOL angka sebelum line dipilih', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(REPORT_PATH)

    await expect(page.locator('[data-testid="laporan-pressing"]')).toBeVisible()

    // Peran terikat mill tidak melihat pemilih mill — menawarkan pemilih yang
    // tidak bisa ia pakai adalah kebohongan kecil yang mahal.
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)

    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()

    // Dan BENAR-BENAR tidak ada angka: bukan sekadar tabel yang disembunyikan.
    await expect(page.locator('[data-testid="coverage"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="metrics-table"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="export-button"]')).toHaveCount(0)
  })

  // Scenario: halaman hidup — wire:model memicu putaran yang mengubah halaman
  test('memilih Production Line memicu putaran Livewire yang mengubah halaman', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(REPORT_PATH)

    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })

    // INILAH yang hanya browser dapat buktikan: bila atribut wire:id menempel
    // pada sebuah <style> alih-alih pada root komponen, SELURUH wire:model
    // mati tanpa satu pun galat, dan baris ini yang menangkapnya.
    await expect(page.locator('[data-testid="select-production-line-hint"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="coverage"]')).toBeVisible()
    await expect(page.locator('[data-testid="hero-production-line"]')).toContainText(PRODUCTION_LINE)
    await expect(page.locator('[data-testid="period-status-badge"]')).toBeVisible()
  })

  // Scenario: cakupan dibaca lebih dulu
  test('invarian (a) — blok cakupan berada DI ATAS tabel parameter di viewport', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)
    await expect(page.locator('[data-testid="metrics"]')).toBeVisible()

    const coverage = await page.locator('[data-testid="coverage"]').boundingBox()
    const metrics = await page.locator('[data-testid="metrics"]').boundingBox()

    expect(coverage).not.toBeNull()
    expect(metrics).not.toBeNull()

    // Diukur dari POSISI di viewport, bukan dari urutan sumbernya: periode
    // yang terisi seperlima pun menghasilkan rata-rata yang terlihat rapi,
    // dan pembaca harus melihat cakupannya lebih dulu.
    expect(coverage!.y).toBeLessThan(metrics!.y)

    // Ketiga angka pembentuk penyebut ikut tercetak, bukan hanya persennya.
    await expect(page.locator('[data-testid="coverage-denominator"]')).toContainText('presser')
    await expect(page.locator('[data-testid="coverage-denominator"]')).toContainText('24 slot')
  })

  // Scenario: setiap rata-rata membawa penyebutnya sendiri
  test('invarian (b+c) — kelima baris parameter ada, masing-masing dengan penyebutnya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)
    await expect(page.locator('[data-testid="metrics-table"]')).toBeVisible()

    // Kelima kolom ukur SELALU dirender, termasuk yang tidak pernah diukur:
    // baris yang hilang terbaca sebagai "tidak ada parameter ini", padahal
    // yang benar adalah "tidak ada yang mengukurnya".
    await expect(page.locator('[data-testid="metric-row"]')).toHaveCount(5)
    await expect(page.locator('[data-testid="metric-denominator"]')).toHaveCount(5)

    const filledSlots = parseIdNumber(
      await page.locator('[data-testid="coverage-slots"]').innerText(),
    )

    expect(filledSlots).not.toBeNull()

    const denominators = await columnNumbers(page, '[data-testid="metric-denominator"]')

    expect(denominators).toHaveLength(5)

    // Penyebut sebuah kolom tidak pernah melampaui jumlah slot terisi periode
    // — kolom itu hanya bisa terisi pada slot yang memang terisi. Invarian
    // ini benar untuk data apa pun, dan ia yang jatuh bila satu penyebut
    // bersama dipakai dari angka yang lebih besar.
    for (const denominator of denominators) {
      expect(denominator).toBeLessThanOrEqual(filledSlots as number)
    }
  })

  // Scenario: standar operasional berada pada baris yang sama dengan angkanya
  test('KEDUA kolom target berada pada baris parameternya, dan keduanya punya testid sendiri', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)
    await expect(page.locator('[data-testid="metrics-table"]')).toBeVisible()

    const firstRow = page.locator('[data-testid="metric-row"]').first()

    // TUJUH kolom: parameter, min, rata-rata, maks, penyebut, rentang kerja,
    // batas tindakan. Jumlahnya SAMA dengan tabel laporan Threshing — yang
    // berbeda adalah ARTI kedua kolom terakhirnya, bukan lebarnya: di sana
    // "standar + rencana tindakan", di sini "rentang kerja + batas tindakan".
    // Menampilkan salah satunya saja menghapus separuh informasi yang dipakai
    // pembaca untuk memutuskan.
    await expect(firstRow.locator('td')).toHaveCount(7)

    // Keduanya ada pada baris yang SAMA, dan keduanya punya testid-nya sendiri
    // supaya satu kolom yang hilang terlihat sebagai kegagalan.
    await expect(firstRow.locator('[data-testid="metric-target-range"]')).toHaveCount(1)
    await expect(firstRow.locator('[data-testid="metric-action-limit"]')).toHaveCount(1)

    // Entah terisi, entah dinyatakan belum terisi — yang tidak boleh adalah
    // sel kosong tanpa keterangan.
    await expect(firstRow.locator('[data-testid="metric-target-range"]')).not.toHaveText('')
    await expect(firstRow.locator('[data-testid="metric-action-limit"]')).not.toHaveText('')
  })

  // Scenario: tidak ada penandaan otomatis di luar batas
  test('tidak ada satu pun kelas penanda di luar batas pada halaman ter-render', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)
    await expect(page.locator('[data-testid="metrics-table"]')).toBeVisible()

    // DISISIR ATAS NAMA KELAS, bukan atas frasa: kalimat yang menyatakan
    // ketiadaan penandaan itu sendiri memuat frasa "di luar batas", jadi
    // penyisiran teks justru gagal pada kalimat yang membuktikan klaimnya.
    for (const className of ['md-threshold', 'is-danger', 'is-warning', 'md-chip--danger']) {
      await expect(page.locator(`.${className}`), className).toHaveCount(0)
    }

    // Dan ketiadaannya DINYATAKAN — ketiadaan penandaan yang tidak dijelaskan
    // terbaca sebagai fitur yang belum selesai.
    await expect(page.locator('[data-testid="no-flagging-note"]')).toBeVisible()
    await expect(page.locator('[data-testid="no-flagging-note"]')).toContainText('teks bebas')
  })

  // Scenario: target tanpa pengukuran tetap ditampilkan
  test('target tanpa kolom pengukuran terender pada bagiannya sendiri', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)
    await expect(page.locator('[data-testid="metrics-table"]')).toBeVisible()

    const section = page.locator('[data-testid="targets-without-metric"]')
    const masterEmpty = page.locator('[data-testid="targets-master-empty"]')

    // Salah satu dari keduanya SELALU ada: entah master kosong (dan itu
    // dinyatakan), entah ada parameter bertarget tanpa kolom ukur. Master
    // Pressing menyediakan enam parameter untuk lima kolom, jadi pada
    // lingkungan yang ter-seed yang kedualah yang muncul.
    await expect(section.or(masterEmpty).first()).toBeVisible()

    if (await section.isVisible()) {
      // DUA parameter, bukan satu seperti pada Threshing — dan keduanya tidak
      // punya kolom pengukuran di mana pun pada skema ini.
      await expect(section).toContainText('Nut Breakage Rate')
      await expect(section).toContainText('Press Cake Moisture')
      await expect(page.locator('[data-testid="targets-without-metric-row"]')).toHaveCount(2)
      // Standar yang tidak pernah diukur terbaca seperti terpenuhi padahal ia
      // sekadar tidak ada — dan halaman ini menyatakannya.
      await expect(page.locator('[data-testid="targets-without-metric-note"]'))
        .toContainText('tidak punya kolom pengukuran di mana pun pada sistem ini')
    }
  })

  // Scenario: rekap harian
  test('invarian (d) — baris total rekap harian menjumlah slot seluruh baris hariannya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const dailyTable = page.locator('[data-testid="daily-table"]')

    if (await dailyTable.count() === 0) {
      test.skip(true, 'periode terpilih tidak punya data pada line ini')
    }

    await expect(dailyTable).toBeVisible()

    const dailySlots = await columnNumbers(
      page,
      '[data-testid="daily-table"] tbody tr td:nth-child(2)',
    )

    const totalSlots = parseIdNumber(
      await page.locator('[data-testid="daily-row-total"] td:nth-child(2)').innerText(),
    )

    expect(dailySlots.length).toBeGreaterThan(0)
    expect(totalSlots).toBe(dailySlots.reduce((sum, value) => sum + value, 0))
  })

  test('rekap harian dapat ditutup, dan menutupnya tidak mengubah satu angka pun', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    if (await page.locator('[data-testid="daily-table"]').count() === 0) {
      test.skip(true, 'periode terpilih tidak punya data pada line ini')
    }

    const slotsBefore = await page.locator('[data-testid="coverage-slots"]').innerText()

    await page.locator('[data-testid="daily-toggle"]').click()

    // Ditutup berarti BENAR-BENAR hilang dari DOM, bukan sekadar
    // disembunyikan — keadaan terlihat dan DOM tidak boleh berselisih.
    await expect(page.locator('[data-testid="daily-table"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="coverage-slots"]')).toHaveText(slotsBefore)

    await page.locator('[data-testid="daily-toggle"]').click()
    await expect(page.locator('[data-testid="daily-table"]')).toBeVisible()
  })

  // Scenario: rekap per presser
  test('invarian (e) — slot per presser menjumlah ke slot terisi periode', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const byPresser = page.locator('[data-testid="by-presser-table"]')

    if (await byPresser.count() === 0) {
      test.skip(true, 'periode terpilih tidak punya data pada line ini')
    }

    const perPresser = await columnNumbers(
      page,
      '[data-testid="by-presser-row"] td:nth-child(3)',
    )

    const filledSlots = parseIdNumber(
      await page.locator('[data-testid="coverage-slots"]').innerText(),
    )

    expect(perPresser.length).toBeGreaterThan(0)
    // Pengelompokan per unit tidak boleh kehilangan maupun menghitung ganda
    // satu slot pun — dan presser_id yang sama pada dua tanggal adalah SATU
    // baris, bukan dua.
    expect(perPresser.reduce((sum, value) => sum + value, 0)).toBe(filledSlots)
  })

  // Scenario: alasan downtime
  test('bagian downtime selalu menyatakan keadaannya, tidak pernah tabel kosong tanpa penjelasan', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const downtime = page.locator('[data-testid="downtime"]')

    if (await downtime.count() === 0) {
      test.skip(true, 'periode terpilih tidak punya data pada line ini')
    }

    const table = page.locator('[data-testid="downtime-table"]')
    const empty = page.locator('[data-testid="downtime-empty"]')

    // Salah satu dari keduanya, selalu — tabel kosong tidak membedakan "tidak
    // ada downtime" dari "tidak ada yang mencatatnya".
    await expect(table.or(empty).first()).toBeVisible()

    if (await table.isVisible()) {
      // Pengelompokan harfiah DINYATAKAN, supaya dua baris mirip tidak dibaca
      // sebagai cacat laporan.
      await expect(page.locator('[data-testid="downtime-note"]')).toContainText('harfiah')
    }
  })

  // Scenario: ekspor
  test('Ekspor CSV benar-benar mengunduh berkas', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    await expect(page.locator('[data-testid="export-button"]')).toBeVisible()

    const downloadPromise = page.waitForEvent('download')
    await page.locator('[data-testid="export-button"]').click()
    const download = await downloadPromise

    expect(download.suggestedFilename()).toContain('laporan-pressing')
    expect(download.suggestedFilename()).toMatch(/\.csv$/)
  })

  // Scenario: layar hanya membaca
  test('layar hanya membaca: tidak ada kontrol tulis apa pun', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    await expect(page.getByRole('button', { name: /^Simpan/ })).toHaveCount(0)
    await expect(page.getByRole('button', { name: /^Hapus/ })).toHaveCount(0)
    await expect(page.getByRole('button', { name: /^Verifikasi/ })).toHaveCount(0)
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
  })

  // Scenario: penjagaan peran pada RUTE
  test('Operator ditolak pada rute web, meski ketiga rute API menerimanya', async ({ page }) => {
    await login(page, OPERATOR, PASSWORD)

    const response = await page.goto(REPORT_PATH)

    // Penjagaannya ada pada middleware rute DAN pada canAccess() komponen,
    // yang memegang daftar perannya SENDIRI justru supaya service dapat
    // menerima Operator (untuk screen-151, layar mobile) tanpa ikut membuka
    // layar web ini.
    expect(response?.status()).toBe(403)
    await expect(page.locator('[data-testid="laporan-pressing"]')).toHaveCount(0)
  })
})

test.describe('Pintu masuk dari Laporan Stasiun (screen-140)', () => {
  /**
   * SATU BARIS yang menentukan layar ini dapat dicapai: entri 'pressing' pada
   * StationReportService::REPORT_ROUTES. Tanpa test ini, layar laporan bisa
   * lengkap, seluruh test backend hijau, dan tile-nya tetap kelabu — pola
   * kegagalan yang sudah pernah terjadi pada laporan Cages & Tracks versi web.
   */
  test('tile Pressing aktif dan menavigasi ke layar laporannya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(STATION_REPORT_PATH)

    // Grid tile screen-140 sendiri digate oleh Production Line — tile baru
    // digambar setelah satu line dipilih.
    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })
    await expect(page.locator('[data-testid="station-grid"]')).toBeVisible()

    const tile = page.locator('[data-testid="station-tile-pressing"]')

    await expect(tile).toBeVisible()
    // AKTIF, bukan kelabu: kelas .active dan href yang benar-benar ada.
    await expect(tile).toHaveClass(/active/)
    await expect(tile).toHaveAttribute('href', /\/reports\/pressing/)

    await tile.click()

    // Sufiks ** — tautannya membawa business_unit_id dan production_line_id.
    await page.waitForURL('**/reports/pressing**')
    await expect(page.locator('[data-testid="laporan-pressing"]')).toBeVisible()

    // Mill dan line ikut terbawa, jadi layar tujuan TIDAK meminta memilih lagi.
    await expect(page.locator('[data-testid="select-production-line-hint"]')).toHaveCount(0)
  })
})
