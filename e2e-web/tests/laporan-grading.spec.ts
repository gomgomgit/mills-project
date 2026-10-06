import { expect, test, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { removeOpenPeriodForForms, seedOpenPeriodForForms } from './support/period-fixture'

/**
 * Laporan Grading (Browser/Playwright) — screen-146--laporan-grading-web /
 * usecase-149--laporan-grading-web.
 *
 * ────────────────────────────────────────────────────────────────────────
 * ASERSI INVARIAN, BUKAN ANGKA YANG DIPAKU
 * ────────────────────────────────────────────────────────────────────────
 * Spec ini TIDAK menanam datanya sendiri dan TIDAK mengasersi satu angka
 * tetap pun. Dua alasan, keduanya menentukan:
 *
 * 1. Angka laporan ini sudah dibuktikan TIGA KALI di backend, terhadap data
 *    yang dikendalikan penuh — atas service-nya (30 kasus unit), atas kontrak
 *    HTTP-nya (26 skenario Api), dan atas markup ter-render komponennya (23
 *    skenario Livewire). Mengulangnya lewat browser hanya memperlambat suite.
 *
 * 2. Database e2e ini BERBAGI data Grading dengan spec lain (form-grading
 *    menanam muatan, dan periode prasyaratnya berumur panjang). Angka yang
 *    dipaku di sini akan berubah setiap kali spec lain berubah — test yang
 *    gagal bukan karena produknya salah adalah test yang akan diabaikan
 *    orang, lalu dihapus.
 *
 * Yang diuji di sini karena HANYA browser dapat membuktikannya:
 *   - TILE Grading pada Laporan Stasiun (screen-140) menyala dan mendarat di
 *     layar ini. Tanpa test ini, satu baris yang hilang pada
 *     StationReportService::REPORT_ROUTES membuat layar ini ada, seluruh test
 *     backend lulus, dan tile-nya tetap kelabu — pola kegagalan yang sudah
 *     pernah terjadi pada laporan Cages & Tracks versi web.
 *   - HALAMANNYA HIDUP: Livewire terpasang dan wire:model bekerja, sehingga
 *     pemilih-pemilihnya memicu putaran yang mengubah halaman. Component test
 *     tidak dapat menangkap jebakan wire:id-menempel-pada-<style> yang pernah
 *     mematikan SELURUH wire:model di screen-140.
 *   - EMPAT INVARIAN yang dihitung DARI DOM, jadi benar untuk data apa pun:
 *       (a) janjang dan kilogram tidak pernah dijumlahkan — jumlah kedua
 *           total dibaca dari halaman, lalu dicari di seluruh teksnya;
 *       (b) jumlah muatan seluruh baris rekap per asal = angka utama;
 *       (c) baris total rekap harian = jumlah baris hariannya;
 *       (d) tiap baris parameter mencetak penyebut rata-ratanya.
 *   - EKSPOR benar-benar mengunduh berkas.
 *   - Penjagaan peran pada RUTE-nya, bukan hanya pada komponennya.
 */

const REPORT_PATH = '/reports/grading'
const STATION_REPORT_PATH = '/reports'

const BUSINESS_UNIT = 'BU Browser Test'
const PRODUCTION_LINE = 'PL Mill A'

const SUPERVISOR = 'stest-supervisor01'
const ADMIN = 'brtest-admin01'
const OPERATOR = 'operator01'

/** "1.234,56" -> 1234.56; "tidak tersedia" / "—" -> null. */
function parseIdNumber(raw: string): number | null {
  const match = raw.replace(/ /g, ' ').match(/-?\d[\d.]*(?:,\d+)?/)

  if (match === null) {
    return null
  }

  return Number(match[0].replace(/\./g, '').replace(',', '.'))
}

/** Format Indonesia dua desimal, seperti yang dicetak halaman. */
function formatId(value: number): string {
  return value.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
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
  await expect(page.locator('[data-testid="laporan-grading"]')).toBeVisible()

  await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })

  await expect(page.locator('[data-testid="report-hero"]')).toBeVisible()
  await expect(page.locator('[data-testid="headline-kpis"]')).toBeVisible()
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
  periodFixturePage = await seedOpenPeriodForForms(browser, 'grading')
})

test.afterAll(async () => {
  // Dijaga: kalau beforeAll gagal sebelum mengembalikan page-nya, afterAll
  // tidak boleh menimpa kegagalan itu dengan TypeError miliknya sendiri.
  if (periodFixturePage !== undefined) {
    await removeOpenPeriodForForms(periodFixturePage)
  }
})

test.describe('Laporan Grading (screen-146)', () => {
  // Scenario: "Production Line belum dipilih"
  test('sebelum line dipilih: arahan memilih line, dan NOL angka di halaman', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(REPORT_PATH)

    await expect(page.locator('[data-testid="laporan-grading"]')).toBeVisible()

    // Peran terikat mill: keterangan mill, bukan pemilih.
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)

    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()

    // Tidak satu angka pun — dan TIDAK ADA angka seluruh mill sebagai
    // penggantinya.
    await expect(page.locator('[data-testid="headline-kpis"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="parameter-bunch-table"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="export-button"]')).toHaveCount(0)
  })

  /**
   * TEST YANG MEMBUKTIKAN HALAMANNYA HIDUP. Memilih Production Line adalah
   * putaran Livewire penuh; kalau wire:model halaman ini mati — jebakan
   * wire:id-pada-<style> yang pernah terjadi di screen-140 — arahan memilih
   * line tidak akan pernah berganti, dan hanya browser yang dapat
   * menangkapnya.
   */
  test('memilih Production Line memicu putaran Livewire yang menampilkan laporan', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(REPORT_PATH)

    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })

    // Arahan memilih line HILANG — bukti putarannya mendarat dan state
    // komponen benar-benar berubah.
    await expect(page.locator('[data-testid="select-production-line-hint"]')).toHaveCount(0)

    // Line yang berlaku ikut dinamai di sebelah angkanya: tidak boleh ada
    // angka di layar ini yang tidak dapat ditelusuri ke satu line tertentu.
    await expect(page.locator('[data-testid="hero-production-line"]')).toContainText(PRODUCTION_LINE)
    await expect(page.locator('[data-testid="period-status-badge"]')).toBeVisible()
  })

  test('seluruh bagian laporan terender, dan tidak ada kontrol tulis', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    for (const testid of [
      'no-sum-note',
      'kpi-load-count',
      'kpi-netto-total',
      'kpi-bunch-total',
      'parameter-bunch',
      'parameter-kg',
      'parameter-share-note',
      'by-estate-supplier-table',
      'completeness',
      'completeness-note',
      'daily-recap-card',
    ]) {
      await expect(page.locator(`[data-testid="${testid}"]`), testid).toBeVisible()
    }

    // Read-only: tidak ada satu pun kontrol tulis di halaman ini.
    await expect(page.getByRole('button', { name: /Simpan|Hapus|Verifikasi/ })).toHaveCount(0)
  })

  /**
   * INVARIAN (a): janjang dan kilogram TIDAK PERNAH DIJUMLAHKAN.
   *
   * Dihitung dari DOM, bukan dipaku: kedua total dibaca dari halaman, lalu
   * jumlahnya dicari di SELURUH teks laporan. Benar untuk data apa pun, dan
   * gagal begitu ada yang menambahkan kolom/baris gabungan.
   */
  test('invarian: jumlah total janjang dan total kilogram tidak muncul di mana pun', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const bunchTotal = parseIdNumber(await page.locator('[data-testid="parameter-bunch-total"]').innerText())
    const kgTotal = parseIdNumber(await page.locator('[data-testid="parameter-kg-total"]').innerText())

    // Kalau salah satu kelompok kosong, invariannya tidak dapat diuji — dan
    // itu dinyatakan, bukan dilewati diam-diam.
    expect(
      bunchTotal !== null && kgTotal !== null && bunchTotal > 0 && kgTotal > 0,
      'periode ini tidak memuat kedua satuan sekaligus, sehingga invarian "tidak pernah dijumlahkan" '
        + 'tidak dapat diuji dari data yang ada',
    ).toBe(true)

    const combined = formatId(bunchTotal! + kgTotal!)
    const report = await page.locator('[data-testid="laporan-grading"]').innerText()

    expect(report, `angka gabungan ${combined} muncul di halaman`).not.toContain(combined)
    await expect(page.getByText(/Total Keseluruhan|Total Gabungan/)).toHaveCount(0)
  })

  /**
   * INVARIAN (b): kelompok asal yang belum diisi TIDAK DIBUANG, sehingga
   * kolom muatan rekap per asal tetap menjumlah ke angka utama.
   */
  test('invarian: jumlah muatan seluruh baris rekap per asal = jumlah muatan utama', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const headline = parseIdNumber(await page.locator('[data-testid="kpi-load-count"]').innerText())

    const rowLoads = await page
      .locator('[data-testid="by-estate-supplier-row"] td:nth-child(2)')
      .allInnerTexts()

    const sum = rowLoads.reduce((total, raw) => total + (parseIdNumber(raw) ?? 0), 0)

    expect(sum).toBe(headline)

    // Dan baris kaki tabelnya menyebut angka yang sama.
    const footer = parseIdNumber(
      await page.locator('[data-testid="by-estate-supplier-row-total"] td:nth-child(2)').innerText(),
    )

    expect(footer).toBe(headline)
  })

  /** INVARIAN (c): baris total rekap harian = jumlah baris hariannya. */
  test('invarian: baris total rekap harian menjumlah seluruh baris hariannya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    await expect(page.locator('[data-testid="daily-table"]')).toBeVisible()

    const dailyLoads = await page
      .locator('[data-testid="daily-table"] tbody tr td:nth-child(2)')
      .allInnerTexts()

    const sum = dailyLoads.reduce((total, raw) => total + (parseIdNumber(raw) ?? 0), 0)

    const footer = parseIdNumber(
      await page.locator('[data-testid="daily-row-total"] td:nth-child(2)').innerText(),
    )

    expect(footer).toBe(sum)
  })

  /**
   * INVARIAN (d): tiap baris parameter mencetak PENYEBUT rata-ratanya —
   * jumlah muatan yang benar-benar mencatat parameter itu. Tanpa penyebut
   * yang tercetak, rata-rata itu terbaca sebagai angka periode.
   */
  test('invarian: tiap baris parameter mencetak penyebut rata-ratanya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const rows = page.locator('[data-testid="parameter-bunch-row"]')

    await expect(rows.first()).toBeVisible()

    const denominators = await rows.locator('td:nth-child(5)').allInnerTexts()

    expect(denominators.length).toBeGreaterThan(0)

    for (const text of denominators) {
      expect(text).toMatch(/\d+ dari \d+ muatan/)
    }
  })

  // Scenario: "rekap harian sangat panjang"
  test('rekap harian dapat ditutup dan dibuka, tanpa mengubah angka utama', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const loadCountBefore = await page.locator('[data-testid="kpi-load-count"]').innerText()

    await expect(page.locator('[data-testid="daily-table"]')).toBeVisible()

    await page.locator('[data-testid="daily-toggle"]').click()

    // Tombol, bukan <details>: tertutup berarti benar-benar hilang dari DOM.
    await expect(page.locator('[data-testid="daily-table"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="parameter-bunch-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="kpi-load-count"]')).toHaveText(loadCountBefore)

    await page.locator('[data-testid="daily-toggle"]').click()
    await expect(page.locator('[data-testid="daily-table"]')).toBeVisible()
  })

  // Scenario: "periode berstatus tertutup" (bagian yang browser-reachable)
  test('status periode adalah keterangan: kedua tombol ekspor tetap aktif', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    await expect(page.locator('[data-testid="period-status-badge"]')).toBeVisible()
    await expect(page.locator('[data-testid="export-button"]')).toBeEnabled()
    await expect(page.locator('[data-testid="export-excel-button"]')).toBeEnabled()
  })

  // Scenario: "unduh rincian seluruh baris parameter"
  test('ekspor CSV benar-benar mengunduh berkas bernama laporan-grading', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const download = page.waitForEvent('download')

    await page.locator('[data-testid="export-button"]').click()

    const file = await download

    expect(file.suggestedFilename()).toContain('laporan-grading')
    expect(file.suggestedFilename()).toContain('.csv')
  })

  // Scenario: "Admin memilih mill lebih dulu" + "sukses sebagai Admin"
  test('Admin: pemilih mill dirender, dan memilihnya memuat daftar line', async ({ page }) => {
    await login(page, ADMIN, PASSWORD)
    await page.goto(REPORT_PATH)

    await expect(page.locator('[data-testid="mill-select"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-select-hint"]')).toBeVisible()

    // Tanpa mill: tidak ada pemilih line, dan nol angka.
    await expect(page.locator('[data-testid="production-line-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="headline-kpis"]')).toHaveCount(0)

    await page.locator('[data-testid="mill-select"]').selectOption({ label: BUSINESS_UNIT })

    // Putaran Livewire kedua: daftar line milik mill TERPILIH kini ada.
    await expect(page.locator('[data-testid="production-line-select"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-select-hint"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })

    await expect(page.locator('[data-testid="headline-kpis"]')).toBeVisible()
  })

  // Scenario: "Operator mencoba membuka layar web ini"
  test('Operator: rute menolak 403 dan tidak ada data Grading yang terlihat', async ({ page }) => {
    await login(page, OPERATOR, PASSWORD)

    const response = await page.goto(REPORT_PATH)

    // Ditolak di lapis RUTE — sebelum komponen mount. Catatan: ketiga rute API
    // data JUSTRU menerima Operator (dipakai laporan mobile screen-147); yang
    // ditolak adalah layar web ini, lewat daftar peran komponennya sendiri.
    expect(response?.status()).toBe(403)
    await expect(page.locator('[data-testid="laporan-grading"]')).toHaveCount(0)
  })
})

/* ==================================================================== */
/* Pintu masuk — tile Grading pada Laporan Stasiun (screen-140)          */
/* ==================================================================== */

test.describe('Pintu masuk dari Laporan Stasiun (screen-140)', () => {
  /**
   * SATU BARIS yang menentukan layar ini dapat dicapai: entri 'grading' pada
   * StationReportService::REPORT_ROUTES.
   *
   * Grid stasiun DITAHAN sampai mill DAN Production Line ditetapkan, karena
   * tiap tile membawa keduanya di report_path — jadi line dipilih lebih dulu
   * di sini, bukan karena laporan Grading membutuhkannya di layar itu.
   */
  test('tile Grading aktif dan menavigasi ke layar laporannya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(STATION_REPORT_PATH)

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })
    await expect(page.locator('[data-testid="station-grid"]')).toBeVisible()

    const tile = page.locator('[data-testid="station-tile-grading"]')

    await expect(tile).toBeVisible()
    // AKTIF, bukan kelabu: kelas .active dan href yang benar-benar ada.
    await expect(tile).toHaveClass(/active/)
    await expect(tile).toHaveAttribute('href', /\/reports\/grading/)

    await tile.click()

    // Sufiks ** — tautannya membawa business_unit_id dan production_line_id.
    await page.waitForURL('**/reports/grading**')
    await expect(page.locator('[data-testid="laporan-grading"]')).toBeVisible()

    // Mill dan line ikut terbawa, jadi layar tujuan TIDAK meminta memilih lagi.
    await expect(page.locator('[data-testid="select-production-line-hint"]')).toHaveCount(0)
  })
})
