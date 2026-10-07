import { expect, test, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { removeOpenPeriodForForms, seedOpenPeriodForForms } from './support/period-fixture'

/**
 * Laporan Periode Kernel Plant (Browser/Playwright) — screen-154--laporan-kernel-plant-web /
 * usecase-160--laporan-kernel-plant-web.
 *
 * ────────────────────────────────────────────────────────────────────────
 * ASERSI INVARIAN, BUKAN ANGKA YANG DIPAKU
 * ────────────────────────────────────────────────────────────────────────
 * Spec ini TIDAK menanam data laporannya sendiri dan TIDAK mengasersi satu
 * angka tetap pun — pola yang sama dengan tests/laporan-depricarping.spec.ts
 * dan tests/laporan-grading.spec.ts, dengan dua alasan yang sama dan keduanya
 * menentukan:
 *
 * 1. Angka laporan ini sudah dibuktikan TIGA KALI di backend, terhadap data
 *    yang dikendalikan penuh — atas service-nya (67 kasus unit di
 *    backend/tests/Unit/Services/KernelPlantReportServiceTest.php), atas
 *    kontrak HTTP-nya (33 skenario di
 *    backend/tests/Feature/Api/LaporanKernelPlantTest.php), dan atas markup
 *    ter-render komponennya (37 skenario di
 *    backend/tests/Feature/Livewire/LaporanKernelPlantTest.php). Mengulangnya
 *    lewat browser hanya memperlambat suite.
 *
 * 2. Database e2e ini BERBAGI data Kernel Plant dengan spec lain: record
 *    KP-BROWSER-EDIT ditanam BrowserTestFixtureSeeder, form-kernel-plant
 *    menanam record lain, detail-kernel-plant menanam deret KP-nya sendiri,
 *    dan periode prasyaratnya berumur panjang. Angka yang dipaku di sini akan
 *    berubah setiap kali salah satu dari itu berubah — test yang gagal bukan
 *    karena produknya salah adalah test yang akan diabaikan orang, lalu
 *    dihapus.
 *
 * Yang diuji di sini karena HANYA browser dapat membuktikannya:
 *   - TILE Kernel Plant pada Laporan Stasiun (screen-140) menyala dan mendarat
 *     di layar ini. Tanpa test ini, satu baris yang hilang pada
 *     StationReportService::REPORT_ROUTES membuat layar ini ada, seluruh test
 *     backend lulus, dan tile-nya tetap kelabu — pola kegagalan yang sudah
 *     pernah terjadi pada laporan Cages & Tracks versi web. Kuncinya
 *     'kernel-plant' BERTANDA HUBUNG, yang memang dipegang
 *     StationTypeEnum::KernelPlant->value; kunci snake_case akan ter-compile,
 *     tampak benar, dan tidak pernah cocok dengan satu baris master pun.
 *   - HALAMANNYA HIDUP: Livewire terpasang dan wire:model bekerja, sehingga
 *     pemilih-pemilihnya memicu putaran yang mengubah halaman. Component test
 *     tidak dapat menangkap jebakan wire:id-menempel-pada-<style> yang pernah
 *     mematikan SELURUH wire:model di screen-140, dan laporan ini menaruh
 *     partial ber-<style>-nya di slot `styles` layout justru karena itu.
 *   - LIMA INVARIAN yang dihitung DARI DOM, jadi benar untuk data apa pun:
 *       (a) blok cakupan berada DI ATAS tabel parameter — diukur dari posisi
 *           kotaknya di viewport, bukan dari urutan sumbernya;
 *       (b) tiap baris parameter mencetak penyebutnya sendiri; N-nya tidak
 *           pernah melampaui jumlah slot terisi periode, dan M-nya SAMA pada
 *           ketujuh baris dan sama dengan jumlah slot terisi itu — skema ini
 *           tidak punya cara mengetahui M per kolom (ketujuh kolom ukur ada
 *           pada SETIAP baris detail), jadi M yang berbeda per baris hanya
 *           bisa dikarang. Mock HTML layar ini sempat mencetak dua M berbeda;
 *           asersi ini yang menolaknya;
 *       (c) ketujuh baris parameter selalu ada, termasuk yang tak terukur;
 *       (d) baris total rekap harian = jumlah slot seluruh baris hariannya;
 *       (e) jumlah slot per unit kernel plant menjumlah ke slot terisi periode.
 *   - KETIADAAN PENANDAAN DI LUAR BATAS, disisir atas NAMA KELAS lewat
 *     locator — bukan atas frasa dan bukan atas sumber halaman. Kalimat yang
 *     menyatakan ketiadaannya sendiri memuat frasa "di luar batas", dan
 *     `.md-threshold` DIDEFINISIKAN pada partial stylesheet bersama
 *     resources/views/dashboard/partials/report-styles.blade.php, jadi namanya
 *     muncul pada HTML setiap laporan tanpa pernah dipakai. Locator menghitung
 *     ELEMEN, dan itulah sebabnya bentuk locator yang benar.
 *   - KEDUA kolom target pada baris yang sama dengan angkanya — target/
 *     benchmark DAN rencana tindakan koreksi, masing-masing dengan testid
 *     sendiri sehingga satu kolom yang hilang terlihat sebagai kegagalan.
 *     Tabelnya TUJUH kolom, bukan delapan: master Kernel Plant berbentuk tiga
 *     kolom seperti Threshing/Pressing, tanpa critical_limit dan tanpa
 *     operational_consequence_justification milik master Depricarping.
 *   - DUA PASANGAN BERBAGI STANDAR, bukan satu. Depricarping hanya punya SATU
 *     (Nut Silo), jadi markup atau logika yang mengistimewakan satu pasangan
 *     LOLOS di sana dan SALAH di sini. Keduanya punya testnya sendiri.
 *   - EKSPOR benar-benar mengunduh berkas.
 *   - Penjagaan peran pada RUTE-nya, bukan hanya pada komponennya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TUJUH KEADAAN YANG SENGAJA TIDAK ADA DI SINI
 * ────────────────────────────────────────────────────────────────────────
 * Mill dengan satu Production Line, mill tanpa periode Kernel Plant, periode
 * berisi record tetapi nol slot terisi, periode yang belum mulai, baris master
 * yang disunting tangan, akun terikat mill dengan business_unit_id null, dan
 * id mill/line mill lain lewat properti komponen — ketujuhnya menuntut
 * database yang disusun per test, dan database e2e ini DIPAKAI BERSAMA ke-60
 * spec lain. Ketujuhnya dibuktikan di
 * backend/tests/Feature/Livewire/LaporanKernelPlantTest.php, yang memang punya
 * database sendiri per test.
 */

const REPORT_PATH = '/reports/kernel-plant'
const STATION_REPORT_PATH = '/reports'

const BUSINESS_UNIT = 'BU Browser Test'
const PRODUCTION_LINE = 'PL Mill A'

const SUPERVISOR = 'stest-supervisor01'
const ADMIN = 'brtest-admin01'
/** Operator adalah aktor mobile pada laporan ini (screen-155) — rute web tidak
 *  dibuka untuknya, meski KEEMPAT rute API-nya menerimanya sejak hari pertama,
 *  dan canAccess() memegang daftar perannya sendiri justru karena itu. */
const OPERATOR = 'operator01'

/** "1.234,56" -> 1234.56; "tidak tersedia" / "—" -> null. */
function parseIdNumber(raw: string): number | null {
  const match = raw.replace(/ /g, ' ').match(/-?\d[\d.]*(?:,\d+)?/)

  if (match === null) {
    return null
  }

  return Number(match[0].replace(/\./g, '').replace(',', '.'))
}

/** Seluruh bilangan pada sebuah potongan teks, berurutan. */
function allIdNumbers(raw: string): number[] {
  const matches = raw.replace(/ /g, ' ').match(/-?\d[\d.]*(?:,\d+)?/g)

  if (matches === null) {
    return []
  }

  return matches.map((value) => Number(value.replace(/\./g, '').replace(',', '.')))
}

/**
 * Penyebut sebuah baris parameter, berformat "N dari M slot".
 *
 * KEDUA angkanya dibaca, bukan hanya yang pertama: N milik baris itu sendiri,
 * M sama untuk ketujuh baris — dan M itulah yang cacat mock layar ini
 * (dua M berbeda) akan melanggar.
 */
function parseDenominator(raw: string): { n: number, m: number } | null {
  const numbers = allIdNumbers(raw)

  return numbers.length >= 2 ? { n: numbers[0], m: numbers[1] } : null
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
  await expect(page.locator('[data-testid="laporan-kernel-plant"]')).toBeVisible()

  await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })

  await expect(page.locator('[data-testid="report-hero"]')).toBeVisible()
  // Cakupan dirender untuk SETIAP periode yang sah, berdata atau tidak —
  // justru itu gunanya. Menunggunya membuktikan putaran Livewire mendarat,
  // dan mencegah count() yang menjawab 0 palsu karena wilayahnya belum ada.
  await expect(page.locator('[data-testid="coverage"]')).toBeVisible()
}

/** Seluruh angka pada satu kolom tabel, sebagai bilangan (null dibuang). */
async function columnNumbers(page: Page, selector: string): Promise<number[]> {
  const cells = await page.locator(selector).allInnerTexts()

  return cells
    .map((cell) => parseIdNumber(cell))
    .filter((value): value is number => value !== null)
}

/** Jumlah slot terisi periode, dibaca dari kartu cakupan. */
async function filledSlotsOf(page: Page): Promise<number> {
  const raw = await page.locator('[data-testid="coverage-slots"]').innerText()
  const filled = parseIdNumber(raw)

  expect(filled, `tidak bisa membaca slot terisi dari "${raw}"`).not.toBeNull()

  return filled as number
}

/**
 * PRASYARAT PERIODE PELAPORAN.
 *
 * Spec ini sengaja tidak menanam data laporannya sendiri (lihat catatan
 * panjang di atas), tetapi PERIODE-nya tetap harus ada: tanpa satu pun periode
 * yang mencakup Kernel Plant di "BU Browser Test", pemilih periodenya kosong,
 * `[data-testid="coverage"]` tidak pernah muncul, dan hampir seluruh
 * skenarionya gagal sebagai "element(s) not found" — seolah produknya yang
 * rusak. Itu benar-benar terjadi pada 2026-10-06 untuk empat spec laporan yang
 * menumpang periode `form-*` tanpa menyebutkannya, dan butuh setengah jam
 * untuk dibuktikan BUKAN regresi.
 *
 * Karena itu periodenya ditanam DI SINI, bukan diandalkan dari urutan
 * alfabet spec `form-*`. seedOpenPeriodForForms() idempoten dan MEMAKAI ULANG
 * periode yang sudah ada (findPeriodByPrefix), jadi memanggilnya tidak
 * menambah periode kedua ketika spec `form-*` memang jalan lebih dulu — ia
 * hanya menghapus ketergantungan pada urutan, dan membuat spec ini dapat
 * dijalankan SENDIRIAN setelah scripts/prepare-db.sh.
 *
 * KUNCI STASIUNNYA 'kernel-plant' BERTANDA HUBUNG. Fixture mencocokkannya
 * dengan `station.station_type` dari API, yang berisi nilai enum-nya;
 * 'kernel_plant' gagal di period-fixture.ts dengan "periode prasyarat tidak
 * punya baris stasiun". Dan tanpa hook ini sama sekali, beforeAll MENGGANTUNG
 * alih-alih gagal.
 */
let periodFixturePage: Page | undefined

test.beforeAll(async ({ browser }) => {
  periodFixturePage = await seedOpenPeriodForForms(browser, 'kernel-plant')
})

test.afterAll(async () => {
  // Dijaga: kalau beforeAll gagal sebelum mengembalikan page-nya, afterAll
  // tidak boleh menimpa kegagalan itu dengan TypeError miliknya sendiri.
  if (periodFixturePage !== undefined) {
    await removeOpenPeriodForForms(periodFixturePage)
  }
})

test.describe('Laporan Periode Kernel Plant (screen-154)', () => {
  // Scenario: "Production Line belum dipilih"
  test('Supervisor: tanpa pemilih mill, dan NOL angka sebelum line dipilih', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(REPORT_PATH)

    await expect(page.locator('[data-testid="laporan-kernel-plant"]')).toBeVisible()

    // Peran terikat mill tidak melihat pemilih mill — menawarkan pemilih yang
    // tidak bisa ia pakai adalah kebohongan kecil yang mahal.
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)

    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()

    // Dan BENAR-BENAR tidak ada angka: bukan sekadar tabel yang disembunyikan,
    // dan BUKAN angka seluruh mill sebagai penggantinya. Pilihan itu tidak
    // pernah dibuat untuk pembaca, bahkan pada mill yang hanya punya satu line.
    await expect(page.locator('[data-testid="coverage"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="metrics-table"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="export-button"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="select-production-line-hint"]'))
      .toContainText('tidak menampilkan angka seluruh mill')
  })

  // Scenario: halaman hidup — wire:model memicu putaran yang mengubah halaman
  test('memilih Production Line memicu putaran Livewire yang mengubah halaman', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(REPORT_PATH)

    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })

    // INILAH yang hanya browser dapat buktikan: bila atribut wire:id menempel
    // pada sebuah <style> alih-alih pada root komponen, SELURUH wire:model
    // mati tanpa satu pun galat, dan baris ini yang menangkapnya. Layar ini
    // menaruh partial ber-<style>-nya di slot `styles` layout justru karena
    // harga jebakan itu sudah dibayar sekali di screen-140.
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

    // Ketiga angka pembentuk penyebut ikut tercetak, bukan hanya persennya:
    // unit kernel plant yang benar-benar beroperasi, hari yang dihitung, dan
    // 24 slot kanonis per hari.
    await expect(page.locator('[data-testid="coverage-denominator"]')).toContainText('unit')
    await expect(page.locator('[data-testid="coverage-denominator"]')).toContainText('hari')
    await expect(page.locator('[data-testid="coverage-denominator"]')).toContainText('24 slot')
    await expect(page.locator('[data-testid="coverage-days"]')).toBeVisible()

    // Persentase diasersi HANYA pada sel cakupannya, tidak pernah atas
    // halaman: kedua kolom target master memuat '≤ 7.0%' dan '≤ 6.0%', jadi
    // asersi persen yang tidak dibatasi akan menyentuh teks master.
    const percent = await page.locator('[data-testid="coverage-percent"]').innerText()

    expect(percent.includes('%') || percent.includes('—'), `sel cakupan tak terbaca: ${percent}`).toBe(true)
  })

  // Scenario: setiap rata-rata membawa penyebutnya sendiri
  test('invarian (b+c) — KETUJUH baris parameter ada, N miliknya sendiri, M sama pada semuanya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)
    await expect(page.locator('[data-testid="metrics-table"]')).toBeVisible()

    // KETUJUH kolom ukur SELALU dirender, termasuk yang tidak pernah diukur:
    // baris yang hilang terbaca sebagai "tidak ada parameter ini", padahal
    // yang benar adalah "tidak ada yang mengukurnya". Tujuh juga ketika salah
    // satu ripple mill atau salah satu silo tidak pernah dicatat sekali pun.
    await expect(page.locator('[data-testid="metric-row"]')).toHaveCount(7)
    await expect(page.locator('[data-testid="metric-denominator"]')).toHaveCount(7)

    const filledSlots = await filledSlotsOf(page)

    const cells = await page.locator('[data-testid="metric-denominator"]').allInnerTexts()

    expect(cells).toHaveLength(7)

    for (const cell of cells) {
      const denominator = parseDenominator(cell)

      expect(denominator, `penyebut tak terbaca: ${cell}`).not.toBeNull()

      // N sebuah kolom tidak pernah melampaui jumlah slot terisi periode —
      // kolom itu hanya bisa terisi pada slot yang memang terisi. Invarian ini
      // benar untuk data apa pun.
      expect(denominator!.n, cell).toBeLessThanOrEqual(filledSlots)

      // Dan M-nya SAMA pada ketujuh baris, yaitu jumlah slot terisi cakupan.
      // Skema ini tidak punya cara mengetahui M per kolom — ketujuh kolom ukur
      // ada pada SETIAP baris detail — jadi M yang berbeda per baris hanya
      // bisa dikarang. Mock HTML layar ini sempat mencetak 672 untuk baris
      // berpasangan dan 336 untuk sisanya; asersi ini yang menolaknya.
      expect(denominator!.m, cell).toBe(filledSlots)
    }
  })

  // Scenario: standar operasional berada pada baris yang sama dengan angkanya
  test('KEDUA kolom target berada pada baris parameternya, dan tabelnya TUJUH kolom', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)
    await expect(page.locator('[data-testid="metrics-table"]')).toBeVisible()

    const firstRow = page.locator('[data-testid="metric-row"]').first()

    // TUJUH kolom: parameter, min, rata-rata, maks, penyebut, target/
    // benchmark, rencana tindakan koreksi. SATU LEBIH SEDIKIT daripada tabel
    // Depricarping yang delapan kolom — master Kernel Plant berbentuk TIGA
    // kolom seperti Threshing/Pressing, tanpa critical_limit dan tanpa
    // operational_consequence_justification. Sel untuk kolom yang masternya
    // tidak punya sengaja tidak dibuat sama sekali, dan asersi ini yang
    // menolak penyalinan bentuk delapan kolom dari Depricarping.
    await expect(firstRow.locator('td')).toHaveCount(7)
    await expect(page.locator('[data-testid="metric-critical-limit"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="metric-consequence"]')).toHaveCount(0)

    // Keduanya ada pada baris yang SAMA, masing-masing dengan testid-nya
    // sendiri supaya satu kolom yang hilang terlihat sebagai kegagalan.
    await expect(firstRow.locator('[data-testid="metric-target-benchmark"]')).toHaveCount(1)
    await expect(firstRow.locator('[data-testid="metric-corrective-action"]')).toHaveCount(1)
    // Nama parameter masternya ikut tercetak — ia yang menjelaskan mengapa dua
    // baris berbeda dapat membawa target yang sama persis.
    await expect(firstRow.locator('[data-testid="metric-master-parameter"]')).toHaveCount(1)

    // Entah terisi, entah dinyatakan belum terisi — yang tidak boleh adalah
    // sel kosong tanpa keterangan.
    await expect(firstRow.locator('[data-testid="metric-target-benchmark"]')).not.toHaveText('')
    await expect(firstRow.locator('[data-testid="metric-corrective-action"]')).not.toHaveText('')
  })

  // Scenario: PASANGAN PERTAMA — satu standar mengatur dua kolom ripple mill
  test('kedua baris ripple mill menyatakan standarnya SATU, dan angkanya tidak dirata-ratakan', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)
    await expect(page.locator('[data-testid="metrics-table"]')).toBeVisible()

    const shared = page.locator('[data-testid="metric-shared-standard"]')

    // EMPAT keterangan pada halaman ini, bukan dua: DUA pasangan berbagi
    // standar — ripple mill DAN kernel silo. Depricarping hanya punya SATU
    // pasangan (Nut Silo), jadi markup atau logika yang mengistimewakan satu
    // pasangan lolos di sana dan SALAH di sini. Penandanya diturunkan dari
    // target.shares_standard_with, tidak dipaku, jadi ripple mill atau silo
    // ketiga pun akan ditandai benar tanpa menyentuh layar ini.
    await expect(shared).toHaveCount(4)

    const texts = await shared.allInnerTexts()

    // Pasangan ripple mill: masing-masing menyebut SAUDARA kolomnya dengan
    // label manusia, bukan nama kolom mentah.
    expect(texts.some((t) => t.includes('Arus Ripple Mill 1'))).toBe(true)
    expect(texts.some((t) => t.includes('Arus Ripple Mill 2'))).toBe(true)

    const ripple = texts.filter((t) => t.includes('Ripple Mill (Cracker)'))

    expect(ripple).toHaveLength(2)

    // Dan keduanya menyatakan bahwa angkanya TIDAK digabungkan: empat mesin
    // fisik, dan rata-rata pasangan akan menyembunyikan ketidakseimbangan
    // beban yang justru menjadi alasan parameter ini diukur. Tanpa keterangan
    // itu standar yang identik tercetak dua kali berturut-turut terbaca
    // seperti data terduplikasi, dan seseorang akan "membersihkannya".
    for (const text of ripple) {
      expect(text).toContain('TIDAK dirata-ratakan')
    }
  })

  // Scenario: PASANGAN KEDUA — dan inilah yang tidak ada padanannya di
  // Depricarping. Satu standar 'Kernel Silo 1 & 2' mengatur dua kolom suhu.
  test('kedua baris kernel silo menyatakan standarnya SATU — pasangan kedua, bukan hanya yang pertama', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)
    await expect(page.locator('[data-testid="metrics-table"]')).toBeVisible()

    const shared = page.locator('[data-testid="metric-shared-standard"]')

    await expect(shared).toHaveCount(4)

    const texts = await shared.allInnerTexts()

    expect(texts.some((t) => t.includes('Suhu Kernel Silo 1'))).toBe(true)
    expect(texts.some((t) => t.includes('Suhu Kernel Silo 2'))).toBe(true)

    // Master memuat SATU baris yang namanya sendiri menyebut kedua silo, dan
    // keterangannya mengutipnya apa adanya.
    const silo = texts.filter((t) => t.includes('Kernel Silo 1 & 2'))

    expect(silo).toHaveLength(2)

    for (const text of silo) {
      expect(text).toContain('TIDAK dirata-ratakan')
    }

    // Dan TIGA baris sisanya memang tidak bertanda: tujuh baris, empat
    // bertanda. Tanda pada baris yang standarnya tidak dibagi akan membuat
    // pembaca menyangka ada kolom lain yang mengatur angka yang sama.
    await expect(page.locator('[data-testid="metric-row"]')).toHaveCount(7)
    await expect(page.locator('[data-testid="metric-shared-standard-row"]')).toHaveCount(4)
  })

  // Scenario: tidak ada penandaan otomatis di luar batas
  test('tidak ada satu pun ELEMEN berkelas penanda di luar batas pada halaman ter-render', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)
    await expect(page.locator('[data-testid="metrics-table"]')).toBeVisible()

    // DISISIR ATAS NAMA KELAS LEWAT LOCATOR, dan tidak dengan dua cara lain
    // yang tampak setara:
    //   - bukan atas FRASA, karena kalimat yang menyatakan ketiadaan
    //     penandaan itu sendiri memuat frasa "di luar batas", jadi penyisiran
    //     teks justru gagal pada kalimat yang membuktikan klaimnya;
    //   - bukan atas SUMBER halaman, karena `.md-threshold` DIDEFINISIKAN di
    //     partial stylesheet bersama report-styles.blade.php — namanya muncul
    //     pada HTML setiap laporan tanpa pernah dipakai pada satu elemen pun.
    // Locator menghitung ELEMEN, dan hanya itu yang menjawab pertanyaannya.
    for (const className of ['md-threshold', 'is-danger', 'is-warning', 'is-critical', 'md-chip--danger']) {
      await expect(page.locator(`.${className}`), className).toHaveCount(0)
    }

    // Dan ketiadaannya DINYATAKAN — ketiadaan penandaan yang tidak dijelaskan
    // terbaca sebagai fitur yang belum selesai, dan seseorang akan
    // "melengkapinya".
    await expect(page.locator('[data-testid="no-flagging-note"]')).toBeVisible()
    await expect(page.locator('[data-testid="no-flagging-note"]')).toContainText('teks bebas')
    await expect(page.locator('[data-testid="no-flagging-note"]'))
      .toContainText('berlaku umum untuk seluruh mill')
  })

  // Scenario: standar tanpa pengukuran tetap ditampilkan
  test('bagian standar-tanpa-pengukuran terender, dengan kontainernya ADA walau isinya kosong', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)
    await expect(page.locator('[data-testid="metrics-table"]')).toBeVisible()

    const section = page.locator('[data-testid="targets-without-metric"]')
    const masterEmpty = page.locator('[data-testid="targets-master-empty"]')

    // Salah satu dari keduanya SELALU ada: entah master kosong (dan itu
    // dinyatakan), entah bagian standar-tanpa-pengukuran. Bagian itu DIGAMBAR
    // WALAU KOSONG, jadi pada master yang terisi ia selalu muncul — bagian
    // yang hilang ketika kosong tidak dapat dibedakan dari bagian yang belum
    // pernah dibuat, dan di layar inilah nama parameter master yang disunting
    // tangan menjadi terlihat.
    await expect(section.or(masterEmpty).first()).toBeVisible()

    if (await section.isVisible()) {
      const rows = page.locator('[data-testid="targets-without-metric-row"]')
      const allMeasured = page.locator('[data-testid="targets-all-measured"]')

      const rowCount = await rows.count()

      if (rowCount === 0) {
        await expect(allMeasured).toBeVisible()
      } else {
        // SETIAP baris mencetak ALASANNYA, bukan hanya nama parameternya.
        await expect(page.locator('[data-testid="targets-without-metric-reason"]'))
          .toHaveCount(rowCount)

        const reasons = await page
          .locator('[data-testid="targets-without-metric-reason"]')
          .allInnerTexts()

        // SATU alasan, dan pada stasiun ini satu alasan adalah semua yang bisa
        // terjadi: sebuah parameter master punya kolom terpeta, atau tidak
        // punya sama sekali. Kunci tanpa cabang yang dapat dicapai lebih buruk
        // daripada tidak ada kunci.
        for (const reason of reasons) {
          expect(reason, `alasan tak dikenal: ${reason}`).toContain('Tidak ada kolom pengukurannya')
        }

        // Keterangannya menjelaskan mengapa daftar ini dibandingkan terhadap
        // HIMPUNAN nama parameter dan bukan terhadap jumlah entri peta: dua
        // pasangan kolom berbagi satu standar masing-masing, jadi tujuh entri
        // peta hanya menyebut lima nama parameter.
        await expect(page.locator('[data-testid="targets-without-metric-note"]'))
          .toContainText('himpunan nama parameter')
      }
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

    // Dan baris total itu DIHITUNG ULANG atas seluruh slot, bukan dengan
    // merata-ratakan rata-rata harian — yang dinyatakan di halamannya, karena
    // rata-rata dari rata-rata memberi bobot yang sama pada hari berisi dua
    // slot dan hari yang terisi penuh.
    await expect(page.locator('[data-testid="daily-total-note"]')).toContainText('dihitung ulang')
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
    // disembunyikan — keadaan terlihat dan DOM tidak boleh berselisih. Itu
    // sebabnya tombol, bukan <details>.
    await expect(page.locator('[data-testid="daily-table"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="daily-recap-card"]')).toBeVisible()
    await expect(page.locator('[data-testid="coverage-slots"]')).toHaveText(slotsBefore)

    await page.locator('[data-testid="daily-toggle"]').click()
    await expect(page.locator('[data-testid="daily-table"]')).toBeVisible()
  })

  // Scenario: rekap per unit kernel plant
  test('invarian (e) — slot per unit kernel plant menjumlah ke slot terisi periode', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const byUnit = page.locator('[data-testid="by-kernel-plant-table"]')

    if (await byUnit.count() === 0) {
      test.skip(true, 'periode terpilih tidak punya data pada line ini')
    }

    await expect(byUnit).toBeVisible()

    const perUnit = await columnNumbers(
      page,
      '[data-testid="by-kernel-plant-row"] td:nth-child(3)',
    )

    const filledSlots = await filledSlotsOf(page)

    expect(perUnit.length).toBeGreaterThan(0)
    // Pengelompokan per unit tidak boleh kehilangan maupun menghitung ganda
    // satu slot pun — dan kernel_plant_id yang sama pada dua tanggal adalah
    // SATU baris dengan dua hari pencatatan, bukan dua baris, karena ia LABEL
    // yang diketik di layar input dan bukan kunci baris.
    expect(perUnit.reduce((sum, value) => sum + value, 0)).toBe(filledSlots)
  })

  // Scenario: downtime sebagai ANGKA — kernel_plant_details.downtime_minutes
  // adalah kolom INTEGER, jadi "berapa lama stasiun berhenti" bisa dijawab.
  test('blok downtime menerbitkan angka dengan penyebut slot pencatatnya, atau menyatakan ketiadaannya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const downtime = page.locator('[data-testid="downtime"]')

    if (await downtime.count() === 0) {
      test.skip(true, 'periode terpilih tidak punya data pada line ini')
    }

    await expect(downtime).toBeVisible()

    const total = page.locator('[data-testid="downtime-total"]')
    const empty = page.locator('[data-testid="downtime-empty"]')

    // Salah satu dari keduanya, selalu. Yang tidak boleh adalah total 0 menit
    // untuk periode yang tidak satu slotnya mencatat downtime: itu terbaca
    // seperti "stasiun tidak pernah berhenti", padahal yang benar adalah
    // "tidak ada yang mencatatnya".
    await expect(total.or(empty).first()).toBeVisible()

    // Keterangan ini ada di KEDUA keadaan — ia menjelaskan cara hitungnya,
    // bukan hasilnya.
    await expect(page.locator('[data-testid="downtime-note"]'))
      .toContainText('tidak dihitung sebagai nol')
    await expect(page.locator('[data-testid="downtime-note"]'))
      .toContainText('tidak punya baris pada master target')

    if (await total.isVisible()) {
      // INVARIAN, bukan angka keras: jumlah slot yang MENCATAT downtime tidak
      // pernah melampaui jumlah slot terisi periode — sebuah slot hanya dapat
      // mencatat downtime bila slot itu memang terisi. Inilah asersi yang
      // jatuh bila penyebut rata-ratanya diambil dari angka yang lebih besar,
      // yang akan membuat angkanya MENGECIL justru seiring bertambahnya slot
      // yang tidak dicatat.
      const recorded = parseIdNumber(
        await page.locator('[data-testid="downtime-recorded-slots"]').innerText(),
      )

      const filledSlots = await filledSlotsOf(page)

      expect(recorded).not.toBeNull()
      expect(recorded as number).toBeLessThanOrEqual(filledSlots)
      await expect(page.locator('[data-testid="downtime-average"]')).toBeVisible()
    }
  })

  // Scenario: rekap temuan — paruh teks bebas, pada bagian TERPISAH
  test('bagian temuan terpisah dari downtime dan menyatakan pengelompokannya harfiah', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const findings = page.locator('[data-testid="findings"]')

    if (await findings.count() === 0) {
      test.skip(true, 'periode terpilih tidak punya data pada line ini')
    }

    // DUA kontainer yang BERBEDA, dan keduanya ada: satu menjawab "berapa
    // lama", satu menjawab "apa yang terlihat". Satu slot dapat memuat
    // keduanya, dan menggabungkannya akan membuat slot itu terhitung dua kali
    // pada satu pengertian.
    await expect(page.locator('[data-testid="downtime"]')).toBeVisible()
    await expect(findings).toBeVisible()

    const downtimeBox = await page.locator('[data-testid="downtime"]').boundingBox()
    const findingsBox = await findings.boundingBox()

    expect(downtimeBox).not.toBeNull()
    expect(findingsBox).not.toBeNull()
    expect(downtimeBox!.y).toBeLessThan(findingsBox!.y)

    const table = page.locator('[data-testid="findings-table"]')
    const empty = page.locator('[data-testid="findings-empty"]')

    // Salah satu dari keduanya, selalu — tabel kosong tidak membedakan "tidak
    // ada temuan" dari "tidak ada yang mencatatnya".
    await expect(table.or(empty).first()).toBeVisible()

    if (await table.isVisible()) {
      // Pengelompokan HARFIAH dinyatakan, supaya dua ejaan untuk satu hal
      // tidak dibaca sebagai cacat laporan alih-alih sebagai bentuk datanya.
      await expect(page.locator('[data-testid="findings-note"]')).toContainText('harfiah')
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

    expect(download.suggestedFilename()).toContain('laporan-kernel-plant')
    expect(download.suggestedFilename()).toMatch(/\.csv$/)
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
    const root = page.locator('[data-testid="laporan-kernel-plant"]')

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
  })

  // Scenario: penjagaan peran pada RUTE
  test('Operator ditolak pada rute web, meski keempat rute API menerimanya', async ({ page }) => {
    await login(page, OPERATOR, PASSWORD)

    const response = await page.goto(REPORT_PATH)

    // Penjagaannya ada pada middleware rute DAN pada canAccess() komponen,
    // yang memegang daftar perannya SENDIRI justru supaya
    // KernelPlantReportService::guardAccess() dapat menerima Operator (untuk
    // screen-155, laporan mobile) tanpa ikut membuka layar web ini. Bila
    // canAccess() suatu hari mendelegasikan ke service, layar ini terbuka
    // diam-diam — dan pintu rute inilah lapisan terakhirnya.
    expect(response?.status()).toBe(403)
    await expect(page.locator('[data-testid="laporan-kernel-plant"]')).toHaveCount(0)
  })
})

test.describe('Pintu masuk dari Laporan Stasiun (screen-140)', () => {
  /**
   * SATU BARIS yang menentukan layar ini dapat dicapai: entri 'kernel-plant'
   * pada StationReportService::REPORT_ROUTES. Tanpa test ini, layar
   * laporannya bisa lengkap, seluruh test backend hijau, dan tile-nya tetap
   * kelabu — pola kegagalan yang sudah pernah terjadi pada laporan Cages &
   * Tracks versi web.
   */
  test('tile Kernel Plant aktif dan menavigasi ke layar laporannya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(STATION_REPORT_PATH)

    // Grid tile screen-140 sendiri digate oleh Production Line — tile baru
    // digambar setelah satu line dipilih.
    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })
    await expect(page.locator('[data-testid="station-grid"]')).toBeVisible()

    const tile = page.locator('[data-testid="station-tile-kernel-plant"]')

    await expect(tile).toBeVisible()
    // AKTIF, bukan kelabu: kelas .active dan href yang benar-benar ada.
    await expect(tile).toHaveClass(/active/)
    await expect(tile).toHaveAttribute('href', /\/reports\/kernel-plant/)

    await tile.click()

    // DIBANDINGKAN SEBAGAI PATHNAME, bukan lewat toHaveURL('**/x'): pola glob
    // itu dipadu dengan baseURL TIDAK PERNAH cocok, dan kegagalannya terbaca
    // seperti navigasi yang tidak terjadi. Tautannya membawa business_unit_id
    // dan production_line_id, jadi query-nya diabaikan di sini.
    await page.waitForURL((url) => url.pathname === REPORT_PATH)
    expect(new URL(page.url()).pathname).toBe(REPORT_PATH)

    await expect(page.locator('[data-testid="laporan-kernel-plant"]')).toBeVisible()

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
