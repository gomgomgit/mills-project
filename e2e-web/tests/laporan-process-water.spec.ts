import { expect, test, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { removeOpenPeriodForForms, seedOpenPeriodForForms } from './support/period-fixture'
import { statefulHeaders } from './support/periods'

/**
 * Laporan Periode Process Water (Browser/Playwright) —
 * screen-160--laporan-process-water-web / usecase-166--laporan-process-water-web.
 *
 * ────────────────────────────────────────────────────────────────────────
 * ASERSI INVARIAN, BUKAN ANGKA YANG DIPAKU
 * ────────────────────────────────────────────────────────────────────────
 * Spec ini TIDAK menanam data laporannya sendiri dan TIDAK mengasersi satu
 * angka tetap pun — pola yang sama dengan tests/laporan-engine-room.spec.ts
 * dan tests/laporan-effluent-plant.spec.ts, dengan dua alasan yang sama dan
 * keduanya menentukan:
 *
 * 1. Angka laporan ini sudah dibuktikan TIGA KALI di backend, terhadap data
 *    yang dikendalikan penuh — atas service-nya (52 kasus unit di
 *    backend/tests/Unit/Services/ProcessWaterReportServiceTest.php), atas
 *    kontrak HTTP-nya (34 skenario di
 *    backend/tests/Feature/Api/LaporanProcessWaterTest.php), dan atas markup
 *    ter-render komponennya (37 skenario di
 *    backend/tests/Feature/Livewire/LaporanProcessWaterTest.php).
 *    Mengulangnya lewat browser hanya memperlambat suite.
 *
 * 2. Database e2e ini BERBAGI data Process Water dengan spec lain:
 *    form-process-water menanam record lewat UI, detail-process-water dan
 *    data-browser-process-water menanam deret PW-nya sendiri, dan periode
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
 * DATABASE-NYA mill_smart_log_e2e, BUKAN mill_smart_log. Dijaga dua kali:
 * backend/.env.e2e menunjuk database itu, e2e-web/scripts/prepare-db.sh
 * MENOLAK berjalan kecuali nama database berakhiran '_e2e', dan
 * tests/support/global-setup.ts memeriksa identitas server sebelum satu test
 * pun jalan. Suite ini menulis ke database yang dilayani :8001, jadi
 * mengarahkannya ke :8000 (server dev) akan menulis ke database dev.
 *
 * Yang diuji di sini karena HANYA browser dapat membuktikannya:
 *   (a) TILE Process Water pada Laporan Stasiun (screen-140) menyala dan
 *       mendarat di layar ini. Tanpa test ini, satu baris yang hilang pada
 *       StationReportService::REPORT_ROUTES membuat layar ini ada, seluruh
 *       test backend lulus, dan tile-nya tetap kelabu — pola kegagalan yang
 *       sudah pernah terjadi pada laporan Cages & Tracks versi web. Kuncinya
 *       'process-water' BERTANDA HUBUNG, yang memang dipegang
 *       StationTypeEnum::ProcessWater->value, dan entrinya DISISIPKAN antara
 *       engine-room (120) dan storage-tank (140).
 *   (b) HALAMANNYA HIDUP: Livewire terpasang dan wire:model bekerja, sehingga
 *       pemilih-pemilihnya memicu putaran yang mengubah halaman. Component
 *       test tidak dapat menangkap jebakan wire:id-menempel-pada-<style> yang
 *       pernah mematikan SELURUH wire:model di screen-140, dan laporan ini
 *       menaruh partial ber-<style>-nya di slot `styles` layout justru karena
 *       itu.
 *   (c) Blok KELENGKAPAN berada DI ATAS seluruh kartu metrik — diukur dari
 *       posisi kotaknya di viewport, bukan dari urutan sumbernya.
 *   (d) KESEMBILAN kartu metrik mencetak PENYEBUTNYA SENDIRI. PENYEBUT YANG
 *       SAMA PADA SEMBILANNYA ADALAH JUSTRU CACAT YANG DICARI, dan bentuk
 *       asersinya yang menangkapnya tanpa memaku angka: sebuah kartu yang
 *       nilainya "tidak tersedia" WAJIB mencetak penyebut 0 — penyebut
 *       bersama akan mencetak jumlah slot terisi periode di sana.
 *   (e) NOL ELEMEN BLOK SEBARAN KATEGORIS. Stasiun ini tidak punya satu pun
 *       kolom berenum, dan `action_taken_status` yang namanya menyesatkan
 *       adalah TEKS BEBAS. Engine Room dan Effluent Plant punya blok sebaran;
 *       layar ini TIDAK, dan ketiadaannya diasersi atas TESTID / ATRIBUT /
 *       JUDUL — bukan atas prosa, karena keterangan yang MENYATAKAN ketiadaan
 *       itu sendiri memuat kata 'sebaran'.
 *   (f) NOL ELEMEN berkelas penanda di luar batas, disisir atas NAMA KELAS
 *       lewat locator — bukan atas frasa dan bukan atas sumber halaman.
 *       Kalimat yang menyatakan ketiadaannya sendiri memuat frasa "di luar
 *       batas", dan `.md-threshold` DIDEFINISIKAN pada partial stylesheet
 *       bersama report-styles.blade.php, jadi namanya muncul pada HTML setiap
 *       laporan tanpa pernah dipakai. Locator menghitung ELEMEN, dan itulah
 *       sebabnya bentuk locator yang benar.
 *   (g) TIDAK ADA BAGIAN DOWNTIME — asersinya atas TESTID dan JUDUL, BUKAN
 *       atas substring 'downtime'. Pernyataan ketiadaannya memakai
 *       data-testid="no-downtime-note".
 *   (h) REKAP PER INSPECTOR ADA DI LAYAR (bukan ekspor-saja — penyimpangan
 *       sadar dari preseden Storage Tank, diminta pemilik produk 2026-10-16),
 *       kodenya MENTAH, 'tidak tercatat' barisnya SENDIRI, dan
 *       sum(baris inspector) + tidak tercatat === slot tercatat === slot
 *       terisi periode.
 *   (i) Jumlah slot per unit menjumlah ke slot terisi periode.
 *   (j) Rekap harian dapat dibuka/ditutup tanpa mengubah satu angka pun, DAN
 *       — diukur, bukan disimpulkan — wrapper rekap menyerap gulir mendatar
 *       DI DALAM kartunya sendiri sementara HALAMANNYA tidak pernah menggulir
 *       mendatar. Rekap harian membawa SEMBILAN kolom rata-rata; lebih sempit
 *       daripada Engine Room yang dua puluh tiga, dan justru itu sebabnya
 *       pengukurannya bercabang alih-alih memaku satu arah ketidaksamaan.
 *   (k) Kolom `shift` TIDAK ADA di layar tetapi ADA di berkas ekspor, dan
 *       keterangan sebabnya terlihat utuh.
 *   (l) Operator DITOLAK pada rute WEB — sementara KETIGA rute API DATA-nya
 *       justru menerimanya 200 SEJAK COMMIT PERTAMA (kembaran mobile
 *       screen-161 dibangun dalam seri yang sama), dan
 *       /business-units/options tetap 403. Ketiga jawaban yang berbeda itulah
 *       invariannya, DAN business_unit_id kiriman klien tetap dibuang.
 *   - EKSPOR benar-benar mengunduh berkas, dan yang Excel benar-benar .xlsx.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG SENGAJA TIDAK DIASERSI DI SINI
 * ────────────────────────────────────────────────────────────────────────
 *   - UKURAN FONT MINIMUM. Mock yang disetujui memakai lantai 14px, tetapi
 *     partial produksi bersama dashboard/partials/report-styles.blade.php
 *     masih merender 10/11/12px, dan keputusan putaran ini SENGAJA tidak
 *     menaikkan partial itu — ia menyentuh tiga belas layar laporan.
 *     Verifikasi 14px pada mock karena itu TIDAK berpindah ke layar ini, dan
 *     mengasersinya di sini akan memerahkan spec ini atas keputusan yang
 *     sudah diambil, bukan atas cacat.
 *   - Sembilan keadaan yang menuntut database tersusun per test: mill tanpa
 *     periode Process Water, periode berisi record tetapi nol slot terisi,
 *     periode yang belum mulai (slot harapan nol), periode yang masih
 *     berjalan, sebuah metrik yang tidak pernah diisi sepanjang periode, slot
 *     yang hanya mengisi kolom identitas, kedua kolom teks yang kosong
 *     sepanjang periode, akun terikat mill dengan business_unit_id null, dan
 *     id mill/line/periode mill lain lewat properti komponen. Kesembilannya
 *     dibuktikan di backend/tests/Feature/Livewire/LaporanProcessWaterTest.php,
 *     yang memang punya database sendiri per test.
 */

const REPORT_PATH = '/reports/process-water'
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
 * Di sini bahayanya BUKAN di masa depan: ProcessWaterReportService::
 * guardAccess() MENERIMA Operator HARI INI, sejak commit pertama, karena
 * kembaran mobile screen-161 dibangun dalam seri yang sama dan merupakan
 * pemanggil yang sah. Kalau canAccess() mendelegasikan ke service, layar web
 * ini terbuka untuk Operator SEKARANG — tanpa satu galat pun. Test di bawah
 * yang membuktikan pelebaran itu tidak merembes ke sini.
 */
const OPERATOR = 'operator01'

/**
 * KESEMBILAN kolom ukur, dalam urutan blok tata letaknya di layar — yaitu
 * urutan RANTAI PENGOLAHAN AIR, bukan urutan kolom di basis data.
 *
 * `action_taken_status` dan `findings` SENGAJA TIDAK DI SINI: keduanya kolom
 * TEKS dan diterbitkan sebagai dua daftar, bukan sebagai metrik — walau
 * keduanya TERMASUK kesebelas kolom yang menentukan sebuah slot terisi.
 */
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

/** KEEMPAT blok berjudul, diturunkan dari field `group` pada respons. */
const METRIC_BLOCKS = [
  { testid: 'metric-block-water-flow', group: 'water_flow', cards: 2 },
  { testid: 'metric-block-softener', group: 'softener', cards: 2 },
  { testid: 'metric-block-chemical-dosing', group: 'chemical_dosing', cards: 2 },
  { testid: 'metric-block-boiler-feed', group: 'boiler_feed', cards: 3 },
]

/**
 * TESTID BLOK SEBARAN KATEGORIS yang WAJIB NOL di layar ini.
 *
 * Diambil dari laporan yang MEMANG punya blok itu (Engine Room, Effluent
 * Plant), supaya daftarnya bukan karangan: kalau seseorang membangun blok
 * sebaran di sini, hampir pasti ia menyalin markup dari salah satu dari
 * keduanya, beserta testid-nya.
 */
const DISTRIBUTION_TESTIDS = [
  'status-distribution',
  'status-distribution-action-taken',
  'status-distribution-table',
  'status-distribution-note',
  'status-row',
  'status-not-recorded',
  'status-recorded-total',
  'standby-not-off-note',
  'action-taken-distribution',
]

/** "1.234,56" -> 1234.56; "tidak tersedia" / "—" -> null. */
function parseIdNumber(raw: string): number | null {
  const match = raw.replace(/ /g, ' ').match(/-?\d[\d.]*(?:,\d+)?/)

  if (match === null) {
    return null
  }

  return Number(match[0].replace(/\./g, '').replace(',', '.'))
}

/** Setiap nama KUNCI, serapa pun dalamnya, di dalam sebuah payload JSON. */
function allKeysOf(value: unknown): string[] {
  if (value === null || typeof value !== 'object') {
    return []
  }

  const keys: string[] = []

  for (const [key, child] of Object.entries(value as Record<string, unknown>)) {
    keys.push(key)
    keys.push(...allKeysOf(child))
  }

  return Array.from(new Set(keys))
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
  await expect(page.locator('[data-testid="laporan-process-water"]')).toBeVisible()

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

/** Lebar gulir halaman — dipakai untuk membuktikan HALAMAN tidak menggulir. */
async function pageOverflowOf(page: Page): Promise<{ scrollWidth: number; clientWidth: number }> {
  return page.evaluate(() => {
    const root = document.scrollingElement ?? document.documentElement

    return { scrollWidth: root.scrollWidth, clientWidth: root.clientWidth }
  })
}

/**
 * PRASYARAT PERIODE PELAPORAN.
 *
 * Spec ini sengaja tidak menanam data laporannya sendiri (lihat catatan
 * panjang di atas), tetapi PERIODE-nya tetap harus ada: tanpa satu pun periode
 * yang mencakup Process Water di "BU Browser Test", pemilih periodenya kosong,
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
 * KUNCI STASIUNNYA 'process-water' BERTANDA HUBUNG. Fixture mencocokkannya
 * dengan `station.station_type` dari API, yang berisi nilai enum-nya;
 * 'process_water' gagal di period-fixture.ts dengan "periode prasyarat tidak
 * punya baris stasiun". Dan tanpa hook ini sama sekali, beforeAll MENGGANTUNG
 * alih-alih gagal — jadi kegagalannya tidak akan menyebut periode sama sekali.
 */
let periodFixturePage: Page | undefined

test.beforeAll(async ({ browser }) => {
  periodFixturePage = await seedOpenPeriodForForms(browser, 'process-water')
})

test.afterAll(async () => {
  // Dijaga: kalau beforeAll gagal sebelum mengembalikan page-nya, afterAll
  // tidak boleh menimpa kegagalan itu dengan TypeError miliknya sendiri.
  if (periodFixturePage !== undefined) {
    await removeOpenPeriodForForms(periodFixturePage)
  }
})

test.describe('Laporan Periode Process Water (screen-160)', () => {
  // Scenario 3: "Production Line belum dipilih"
  test('Supervisor: tanpa pemilih mill, dan NOL angka sebelum line dipilih', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(REPORT_PATH)

    await expect(page.locator('[data-testid="laporan-process-water"]')).toBeVisible()

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
    await expect(page.locator('[data-testid="inspector-recap-table"]')).toHaveCount(0)
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
    await expect(page.locator('[data-testid="hero-unit-count"]')).toContainText('unit Process Water')
  })

  // Invarian (c): cakupan dibaca lebih dulu.
  test('invarian (c) — blok kelengkapan berada DI ATAS seluruh kartu metrik di viewport', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const firstCard = page.locator('[data-testid="metric-card"]').first()

    await expect(firstCard).toBeVisible()

    const coverage = await page.locator('[data-testid="coverage"]').boundingBox()
    const metrics = await firstCard.boundingBox()
    const block = await page.locator('[data-testid="metric-block-water-flow"]').boundingBox()

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
    // unit Process Water yang benar-benar punya record, hari yang dihitung,
    // dan 24 slot kanonis per hari — dan 24 itu diambil dari kisi slot layar
    // input sendiri, bukan dikarang di blade.
    const formula = page.locator('[data-testid="coverage-formula"]')

    await expect(formula).toContainText('unit')
    await expect(formula).toContainText('hari')
    await expect(formula).toContainText('24 slot')
    await expect(formula).toContainText('07:00')

    // Persentase diasersi HANYA pada sel cakupannya, tidak pernah atas
    // halaman: keterangan cakupan dan rekap per inspector memuat banyak
    // persentase lain.
    const percent = await page.locator('[data-testid="coverage-percent"]').innerText()

    expect(percent.includes('%') || percent.includes('—'), `sel cakupan tak terbaca: ${percent}`).toBe(true)

    // ATURAN SEBELAS-BUKAN-TIGA-BELAS DIRENDER DI BLOK KELENGKAPAN, bukan
    // sebagai catatan kaki tersembunyi, dan ia MENYATAKAN bahwa jumlah slot
    // terisi dapat MELEBIHI penyebut setiap kartu.
    const elevenNote = page.locator('[data-testid="eleven-columns-note"]')

    await expect(elevenNote).toBeVisible()
    await expect(elevenNote).toContainText('SEBELAS kolom pembacaan')
    await expect(elevenNote).toContainText('bukan tiga belas')
    await expect(elevenNote).toContainText('MELEBIHI penyebut setiap kartu metrik')
    // KEDUA kolom identitas disebut dengan namanya, di dalam elemen itu.
    await expect(elevenNote).toContainText('inspector_id')
    await expect(elevenNote).toContainText('dikecualikan')

    await expect(page.locator('[data-testid="coverage-note"]')).toBeVisible()
  })

  // Invarian (d): INTI LAPORAN INI.
  test('invarian (d) — 9 kartu mencetak penyebutnya SENDIRI, dikelompokkan empat blok', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    // KESEMBILAN kartu SELALU dirender, termasuk yang tidak pernah diukur:
    // kartu yang hilang terbaca sebagai "parameter itu tidak ada", padahal
    // yang benar "tidak ada yang mengukurnya".
    await expect(page.locator('[data-testid="metric-card"]')).toHaveCount(9)
    await expect(page.locator('[data-testid="metric-denominator"]')).toHaveCount(9)
    await expect(page.locator('[data-testid="metric-avg"]')).toHaveCount(9)
    await expect(page.locator('[data-testid="metric-range"]')).toHaveCount(9)
    await expect(page.locator('[data-metric]')).toHaveCount(9)

    // KEEMPAT blok, dan jumlah kartu di tiap blok diturunkan dari field
    // `group` pada respons — bukan dari daftar kolom yang dipaku di template.
    for (const block of METRIC_BLOCKS) {
      const card = page.locator(`[data-testid="${block.testid}"]`)

      await expect(card, block.testid).toHaveCount(1)
      await expect(card, block.testid).toHaveAttribute('data-metric-group', block.group)
      await expect(card.locator('[data-testid="metric-card"]'), block.testid).toHaveCount(block.cards)
    }

    // URUTAN BLOKNYA MENGIKUTI RANTAI PENGOLAHAN AIR, diukur dari posisi
    // kotaknya: pembaca yang menemukan angka air umpan boiler yang tidak ia
    // harapkan harus dapat bergerak MUNDUR ke tahap sebelumnya.
    const tops: number[] = []

    for (const block of METRIC_BLOCKS) {
      const box = await page.locator(`[data-testid="${block.testid}"]`).boundingBox()

      expect(box, block.testid).not.toBeNull()
      tops.push(box!.y)
    }

    expect(tops[0]).toBeLessThan(tops[3])
    expect(tops[1]).toBeLessThan(tops[3])
    expect(tops[2]).toBeLessThan(tops[3])

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
      // aturan sebelas kolom: arah ketidaksamaannya yang sah hanya satu.
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

    expect(denominators).toHaveLength(9)

    // Keterangan rantai pengolahan air dan keterangan dua satuan dosis
    // dirender di bloknya masing-masing, bukan sebagai catatan kaki halaman.
    await expect(page.locator('[data-testid="metric-block-water-flow"] [data-testid="treatment-chain-note"]'))
      .toHaveCount(1)
    await expect(page.locator('[data-testid="metric-block-softener"] [data-testid="softener-note"]'))
      .toHaveCount(1)
    await expect(page.locator('[data-testid="metric-block-chemical-dosing"] [data-testid="dosing-unit-note"]'))
      .toHaveCount(1)
    await expect(page.locator('[data-testid="dosing-unit-note"]')).toContainText('kg/jam')
    await expect(page.locator('[data-testid="dosing-unit-note"]')).toContainText('g/jam')
  })

  // Invarian (e): NOL BLOK SEBARAN KATEGORIS — pembeda terpenting layar ini.
  test('invarian (e) — nol elemen blok sebaran kategoris, dan sebabnya dinyatakan', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)
    await expect(page.locator('[data-testid="metric-card"]').first()).toBeVisible()

    // DIASERSI ATAS TESTID, ATRIBUT, DAN JUDUL — bukan atas prosa.
    // Keterangan yang MENYATAKAN mengapa kolom ini bukan sebaran memuat kata
    // 'sebaran' berkali-kali, jadi penyisiran teks akan gagal pada elemen
    // yang justru membuktikan klaimnya.
    for (const testid of DISTRIBUTION_TESTIDS) {
      await expect(page.locator(`[data-testid="${testid}"]`), testid).toHaveCount(0)
    }

    await expect(page.locator('[data-status-value]')).toHaveCount(0)
    await expect(page.getByRole('heading', { name: /^Sebaran/ })).toHaveCount(0)
    // Kepala tabel sebaran di laporan stasiun lain — nol di sini.
    await expect(page.locator('th', { hasText: /^Nilai$/ })).toHaveCount(0)
    await expect(page.locator('th', { hasText: /^Jumlah slot$/ })).toHaveCount(0)

    // Yang ADA: rekap TEKS, satu entri per slot.
    const section = page.locator('[data-testid="action-taken-entries"]')

    await expect(section).toBeVisible()
    await expect(
      section.locator('[data-testid="action-taken-row"]')
        .or(section.locator('[data-testid="action-taken-empty"]'))
        .first(),
    ).toBeVisible()

    // Dan keterangan MENGAPA kolom bernama 'status' ini diperlakukan sebagai
    // teks dirender UTUH — tiga verifikasinya disebutkan, termasuk label
    // formulir mobile yang memakai GARIS MIRING.
    const note = page.locator('[data-testid="action-taken-is-text-note"]')

    await expect(note).toBeVisible()
    await expect(note).toContainText('teks bebas tanpa himpunan')
    await expect(note).toContainText('Action Taken/Status')
    await expect(note).toContainText('dengan garis miring')
  })

  // Invarian (f): nol elemen berkelas penanda di luar batas.
  test('invarian (f) — nol ELEMEN berkelas penanda di luar batas pada halaman ter-render', async ({ page }) => {
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

    // Process Water tidak punya tabel master target operasional sama sekali,
    // dan ketiadaan itu DINYATAKAN UTUH — ketiadaan penandaan yang tidak
    // dijelaskan terbaca sebagai fitur yang belum selesai, dan seseorang akan
    // "melengkapinya". Di layar inilah godaan itu paling besar di antara
    // seluruh laporan stasiun: pH dan TDS air umpan boiler punya batas yang
    // dikenal SANGAT luas DI LUAR aplikasi ini.
    const note = page.locator('[data-testid="no-threshold-note"]')

    await expect(note).toBeVisible()
    await expect(note).toContainText('tidak punya tabel master target')
    await expect(note).toContainText('keselamatan')
    await expect(note).toContainText('turunan')

    // DAN ia terender DI DALAM blok Air Umpan Boiler, bukan sebagai catatan
    // kaki halaman — diasersi sebagai KETURUNAN elemennya, bentuk paling
    // langsung dari klaim "di dekat angkanya".
    await expect(page.locator('[data-testid="metric-block-boiler-feed"] [data-testid="no-threshold-note"]'))
      .toHaveCount(1)
  })

  // Invarian (g): tidak ada bagian downtime.
  test('invarian (g) — tidak ada bagian downtime, diasersi atas TESTID dan JUDUL', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)
    await expect(page.locator('[data-testid="metric-card"]').first()).toBeVisible()

    // DIASERSI ATAS TESTID DAN JUDUL, BUKAN atas substring 'downtime':
    // laporan ini MENYATAKAN ketiadaan bagian itu, dan pernyataannya sendiri
    // memakai data-testid="no-downtime-note". Penyisiran substring akan gagal
    // pada elemen yang justru membuktikan klaimnya.
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

  // Invarian (k): kolom shift — ABSEN di layar, ADA di ekspor.
  test('invarian (k) — kolom shift tidak diterbitkan di layar, dan sebabnya terlihat', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)
    await expect(page.locator('[data-testid="metric-card"]').first()).toBeVisible()

    // DIASERSI ATAS TESTID, ATRIBUT, DAN KEPALA TABEL — bukan atas substring
    // 'Shift'. Label itu sependek lima huruf dan DIEJA di dalam keterangan
    // yang justru menjelaskan ketiadaannya, jadi asersi substring atas
    // halaman akan hijau tanpa membuktikan apa pun.
    for (const testid of ['shift-distribution', 'shift-row', 'by-shift', 'shift-recap', 'shift-table']) {
      await expect(page.locator(`[data-testid="${testid}"]`), testid).toHaveCount(0)
    }

    await expect(page.locator('[data-metric="shift"]')).toHaveCount(0)
    await expect(page.locator('[data-shift]')).toHaveCount(0)
    // KEPALA TABEL, bukan halaman: kalau sebuah tabel memperoleh kolom Shift,
    // inilah yang menangkapnya.
    await expect(page.locator('th', { hasText: /^Shift$/ })).toHaveCount(0)

    // KETERANGAN SEBABNYA TERLIHAT UTUH: kosong di seluruh baris, dan
    // dijadwalkan diganti relasi ke master Shift. Tanpa ini, ketiadaannya
    // terbaca sebagai kelalaian.
    const note = page.locator('[data-testid="no-shift-note"]')

    await expect(note).toBeVisible()
    await expect(note).toContainText('kosong di seluruh')
    await expect(note).toContainText('master Shift')
    // Dan ia menyatakan bahwa kolomnya TETAP ADA di berkas ekspor.
    await expect(note).toContainText('ikut di berkas ekspor')
  })

  // Invarian (h): rekap per inspector — DI LAYAR, kode MENTAH.
  test('invarian (h) — rekap per inspector di layar menjumlah ke slot tercatat, kode mentah', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const table = page.locator('[data-testid="inspector-recap-table"]')

    if (await table.count() === 0) {
      test.skip(true, 'periode terpilih tidak punya slot terisi pada line ini')
    }

    // DI LAYAR, bukan ekspor-saja — penyimpangan SADAR dari preseden Storage
    // Tank, diminta pemilik produk 2026-10-16.
    await expect(table).toBeVisible()

    const perInspector = await columnNumbers(page, '[data-testid="inspector-row"] td:nth-child(2)')
    const notRecorded = parseIdNumber(
      await page.locator('[data-testid="inspector-not-recorded"] td:nth-child(2)').innerText(),
    )
    const recorded = parseIdNumber(
      await page.locator('[data-testid="inspector-recorded-total"] td:nth-child(2)').innerText(),
    )
    const filledSlots = await filledSlotsOf(page)

    expect(notRecorded, 'baris tidak tercatat tak terbaca').not.toBeNull()
    expect(recorded, 'baris total tak terbaca').not.toBeNull()

    // sum(baris inspector) + tidak tercatat === slot tercatat === slot terisi
    // periode. 'Tidak tercatat' adalah BARISNYA SENDIRI dan tidak pernah
    // dilipat ke salah satu inspector: "tidak kami tulis siapa" dan "ditulis
    // oleh seseorang" adalah dua kenyataan berbeda.
    expect(
      perInspector.reduce((sum, value) => sum + value, 0) + (notRecorded as number),
    ).toBe(recorded as number)
    expect(recorded as number).toBe(filledSlots)

    // Baris 'tidak tercatat' SELALU dirender, apa pun datanya — baris yang
    // hilang tidak dapat dibedakan dari nol slot tanpa inspector.
    await expect(page.locator('[data-testid="inspector-not-recorded"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="inspector-not-recorded"] td:nth-child(1)'))
      .toHaveText('tidak tercatat')

    // KODE MENTAH: tiap baris inspector membawa kodenya pada atributnya, dan
    // sel pertamanya berisi kode yang SAMA — tidak ada nama yang dipulangkan.
    const rows = page.locator('[data-testid="inspector-row"]')
    const rowCount = await rows.count()

    for (let index = 0; index < rowCount; index += 1) {
      const row = rows.nth(index)
      const code = await row.getAttribute('data-inspector-id')

      expect(code, `baris ${index} tanpa data-inspector-id`).not.toBeNull()
      await expect(row.locator('td:nth-child(1)')).toHaveText(code as string)
    }

    // Dan keterangan MAKSUDNYA terlihat: rekap ini ada untuk menelusuri slot
    // yang BELUM terisi, BUKAN untuk menilai orang. Tanpa keterangan itu,
    // tabel ini akan dibaca sebagai papan peringkat.
    const purpose = page.locator('[data-testid="inspector-recap-purpose-note"]')

    await expect(purpose).toBeVisible()
    await expect(purpose).toContainText('menelusuri slot yang BELUM terisi')
    await expect(purpose).toContainText('menilai orang')
    // Penyebut porsinya SLOT TERISI, bukan slot yang diharapkan.
    await expect(purpose).toContainText('jumlah slot terisi')
    await expect(page.locator('[data-testid="inspector-recap-raw-code-note"]'))
      .toContainText('bukan relasi ke tabel')
  })

  // Scenario 13/14: dua rekap teks bebas yang TERPISAH.
  test('Rekap Catatan Tindakan dan Rekap Temuan adalah DUA bagian terpisah', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const actionTaken = page.locator('[data-testid="action-taken-entries"]')
    const findings = page.locator('[data-testid="findings"]')

    // DUA kontainer yang BERBEDA, dan keduanya ada: satu menjawab "apa yang
    // dikerjakan", satu menjawab "apa yang ditemukan". Satu slot dapat memuat
    // keduanya, dan menggabungkannya akan menghitung slot itu dua kali di
    // dalam satu pengertian yang sama.
    await expect(actionTaken).toBeVisible()
    await expect(findings).toBeVisible()
    await expect(actionTaken).toHaveCount(1)
    await expect(findings).toHaveCount(1)

    const actionBox = await actionTaken.boundingBox()
    const findingsBox = await findings.boundingBox()

    expect(actionBox).not.toBeNull()
    expect(findingsBox).not.toBeNull()
    expect(actionBox!.y).toBeLessThan(findingsBox!.y)

    // Masing-masing: entah daftar entri, entah keterangan "tidak ada catatan"
    // — tabel kosong tidak membedakan "tidak ada tindakan" dari "tidak ada
    // yang mencatatnya".
    await expect(
      actionTaken.locator('[data-testid="action-taken-row"]')
        .or(actionTaken.locator('[data-testid="action-taken-empty"]'))
        .first(),
    ).toBeVisible()
    await expect(
      findings.locator('[data-testid="finding-row"]')
        .or(findings.locator('[data-testid="findings-empty"]'))
        .first(),
    ).toBeVisible()

    // Dan satu slot yang mengisi keduanya muncul di keduanya — itu BUKAN
    // penghitungan ganda, dan keterangannya menyatakannya.
    await expect(page.locator('[data-testid="findings-note"]'))
      .toContainText('bukan penghitungan ganda')

    // NOL daftar gabungan.
    for (const testid of ['text-entries-combined', 'notes', 'all-texts', 'remarks']) {
      await expect(page.locator(`[data-testid="${testid}"]`), testid).toHaveCount(0)
    }
  })

  // Invarian (i): rekap per unit.
  test('invarian (i) — jumlah slot per unit menjumlah ke slot terisi periode', async ({ page }) => {
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
    // satu slot pun — dan process_water_id yang sama pada dua tanggal adalah
    // SATU baris dengan dua hari pencatatan, bukan dua baris, karena ia NAMA
    // UNIT yang diketik di layar input dan bukan kunci baris.
    expect(perUnit.reduce((sum, value) => sum + value, 0)).toBe(filledSlots)

    await expect(page.locator('[data-testid="by-unit-row-total"]')).toBeVisible()

    // 12 kolom: unit + hari + slot + 9 rata-rata. Dihitung atas <th>-nya,
    // bukan atas lebar pikselnya.
    await expect(byUnit.locator('thead th')).toHaveCount(12)
    await expect(page.locator('[data-testid="by-unit-scroll-hint"]')).toBeVisible()
  })

  // Invarian (j): rekap harian dapat ditutup, DAN gulir mendatarnya DIUKUR.
  test('invarian (j) — rekap harian dapat dibuka/ditutup, dan gulir mendatar ada DI DALAM kartunya', async ({ page }) => {
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

    // ──────────────────────────────────────────────────────────────────
    // HALAMAN TIDAK PERNAH MENGGULIR MENDATAR — diukur pada KEDUA keadaan.
    // ──────────────────────────────────────────────────────────────────
    const beforeOpen = await pageOverflowOf(page)

    expect(
      beforeOpen.scrollWidth,
      `halaman menggulir mendatar sebelum rekap dibuka: ${beforeOpen.scrollWidth} > ${beforeOpen.clientWidth}`,
    ).toBeLessThanOrEqual(beforeOpen.clientWidth + 1)

    // Elemen <details> NATIVE: tertutup pada paint pertama, jadi tabelnya ada
    // di DOM tetapi TIDAK TERLIHAT. Membukanya dan menutupnya kembali terjadi
    // sepenuhnya di peramban, tanpa satu putaran Livewire pun — dan itulah
    // yang membuat klaim "tidak memicu permintaan data baru" dapat
    // dibuktikan: seluruh angka halaman ini dihitung di service.
    await expect(table).toBeHidden()

    await toggle.click()
    await expect(table).toBeVisible()

    // 11 kolom: tanggal + slot + 9 rata-rata.
    await expect(table.locator('thead th')).toHaveCount(11)

    // ──────────────────────────────────────────────────────────────────
    // GULIR MENDATAR DISERAP OLEH KARTUNYA — DIUKUR, bukan disimpulkan dari
    // nama kelas, dan BERCABANG, bukan dipaku pada satu arah ketidaksamaan.
    // ──────────────────────────────────────────────────────────────────
    // Rekap harian layar ini SEMBILAN kolom rata-rata — lebih sempit daripada
    // Engine Room yang dua puluh tiga — jadi pada viewport lebar ia bisa
    // MUAT. Memaku 'scrollWidth > clientWidth' di sini akan memerahkan spec
    // ini pada tabel yang justru rapi. Yang BENAR-BENAR invarian ada dua, dan
    // keduanya diukur:
    //   1. wrapper-nya adalah elemen yang MENYERAP kelebihan lebar
    //      (overflow-x: auto pada CSS HASIL COMPILE — kelas `.md-recap` yang
    //      dipakai markup tanpa definisi akan menjawab 'visible' di sini dan
    //      memotong tabelnya di layar);
    //   2. HALAMANNYA tidak menggulir mendatar, bahkan setelah wrapper itu
    //      digulir sampai kolom terakhir.
    for (const testid of ['by-unit-details', 'daily-recap-details']) {
      const wrapper = page.locator(`[data-testid="${testid}"] .md-recap`).first()

      await expect(wrapper, testid).toBeVisible()

      const metrics = await wrapper.evaluate((element) => ({
        scrollWidth: element.scrollWidth,
        clientWidth: element.clientWidth,
        overflowX: window.getComputedStyle(element).overflowX,
        tableScrollWidth: (element.querySelector('table') as HTMLElement | null)?.scrollWidth ?? 0,
      }))

      // (1) MEKANISMENYA ADA, dan diperiksa pada CSS hasil compile.
      expect(metrics.overflowX, testid).toBe('auto')

      // (2) Dan ia BENAR-BENAR menyerapnya ketika tabelnya memang lebih lebar
      //     daripada kartunya. Percabangan ini bukan kelonggaran: ia yang
      //     membedakan "tabel muat" dari "tabel terpotong", dua keadaan yang
      //     keduanya menghasilkan scrollWidth === clientWidth pada wrapper
      //     yang TIDAK punya overflow-x.
      if (metrics.tableScrollWidth > metrics.clientWidth) {
        expect(
          metrics.scrollWidth,
          `${testid}: tabel lebih lebar dari kartunya tetapi wrapper tidak menggulir`
            + ` (${metrics.scrollWidth} <= ${metrics.clientWidth})`,
        ).toBeGreaterThan(metrics.clientWidth)

        // Digulir sampai kolom terakhir, lalu HALAMAN diukur lagi: kelebihan
        // lebar harus tetap tinggal DI DALAM kartunya.
        await wrapper.evaluate((element) => { element.scrollLeft = element.scrollWidth })

        const afterScroll = await pageOverflowOf(page)

        expect(
          afterScroll.scrollWidth,
          `${testid}: halaman menggulir mendatar setelah kartunya digulir`,
        ).toBeLessThanOrEqual(afterScroll.clientWidth + 1)
      }
    }

    await toggle.click()
    await expect(table).toBeHidden()

    // Angka utama tetap terbaca pada KEDUA keadaan — membuka/menutup
    // <details> tidak memicu permintaan data baru, jadi tidak satu angka pun
    // boleh berubah.
    await expect(page.locator('[data-testid="coverage-slots"]')).toHaveText(slotsBefore)
    await expect(page.locator('[data-testid="metric-card"]')).toHaveCount(9)
    await expect(page.locator('[data-testid="by-unit-table"]')).toBeVisible()

    const afterClose = await pageOverflowOf(page)

    expect(afterClose.scrollWidth).toBeLessThanOrEqual(afterClose.clientWidth + 1)
  })

  // Scenario 30: ekspor
  test('Ekspor CSV dan Ekspor Excel benar-benar mengunduh berkas', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    await expect(page.locator('[data-testid="export-button"]')).toBeVisible()
    await expect(page.locator('[data-testid="export-excel-button"]')).toBeVisible()

    const csvPromise = page.waitForEvent('download')
    await page.locator('[data-testid="export-button"]').click()
    const csv = await csvPromise

    expect(csv.suggestedFilename()).toContain('laporan-process-water')
    expect(csv.suggestedFilename()).toMatch(/\.csv$/)

    const excelPromise = page.waitForEvent('download')
    await page.locator('[data-testid="export-excel-button"]').click()
    const excel = await excelPromise

    expect(excel.suggestedFilename()).toContain('laporan-process-water')
    // .xlsx SUNGGUHAN lewat SheetWriter, bukan CSV bernama .xlsx.
    expect(excel.suggestedFilename()).toMatch(/\.xlsx$/)
  })

  // Scenario 33: layar hanya membaca
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
    const root = page.locator('[data-testid="laporan-process-water"]')

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
    await expect(page.locator('[data-testid="metric-card"]')).toHaveCount(9)
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

  // Invarian (l): penjagaan peran pada RUTE-nya, dan TIGA jawaban berbeda.
  test('invarian (l) — Operator DITOLAK pada rute web, 200 pada ketiga rute API data, 403 pada pemilih mill', async ({ page }) => {
    await login(page, OPERATOR, PASSWORD)

    const response = await page.goto(REPORT_PATH)

    // LAYAR WEB TERTUTUP. Penjagaannya ada pada middleware rute DAN pada
    // canAccess() komponen, yang memegang daftar perannya SENDIRI justru
    // supaya penerimaan Operator di service — yang sudah berlaku HARI INI
    // demi kembaran mobile screen-161 — TIDAK dapat membuka layar web ini.
    expect(response?.status()).toBe(403)
    await expect(page.locator('[data-testid="laporan-process-water"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="coverage"]')).toHaveCount(0)

    // Header stateful WAJIB ada. Tanpa X-Requested-With + Referer,
    // EnsureFrontendRequestsAreStateful tidak mengaktifkan session guard dan
    // Sanctum menjawab 401 SEBELUM guard peran dievaluasi — 401 itu akan lulus
    // sebagai "ditolak" padahal tidak membuktikan apa pun tentang peran.
    const headers = await statefulHeaders(page)

    // PEMILIH MILL TETAP 403 — 403 yang diangkat DARI DALAM
    // ProcessWaterReportService::businessUnitOptions(), bukan oleh daftar
    // peran middleware yang memang menerima empat peran demi ketiga rute
    // data.
    const options = await page.request.get(
      '/api/process-water-reports/business-units/options',
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
    const periods = await page.request.get('/api/process-water-reports/periods', { headers })

    expect(periods.status()).toBe(200)

    const periodId = ((await periods.json()).data ?? [])[0]?.id

    const lines = await page.request.get('/api/production-lines/options-for-report', { headers })

    expect(lines.status()).toBe(200)

    const lineId = ((await lines.json()).data ?? [])[0]?.id

    // Diasersi, bukan dilewati diam-diam: tanpa keduanya kedua asersi di bawah
    // menjadi 422 tentang parameter yang hilang, dan "tidak 403" yang lulus
    // karena alasan yang salah adalah asersi yang berhenti dibaca orang.
    expect(periodId, 'mill operator01 tidak punya satu pun periode Process Water').toBeTruthy()
    expect(lineId, 'mill operator01 tidak punya satu pun Production Line').toBeTruthy()

    const summary = await page.request.get(
      `/api/process-water-reports/summary?period_id=${periodId}&production_line_id=${lineId}`,
      { headers },
    )

    expect(summary.status()).toBe(200)

    const summaryBody = await summary.json()

    // SEMBILAN kunci metrik, dan kedua kolom teks BUKAN di antaranya.
    expect(Object.keys(summaryBody.metrics ?? {})).toHaveLength(9)
    expect(summaryBody.metrics?.action_taken_status).toBeUndefined()
    expect(summaryBody.metrics?.findings).toBeUndefined()
    // DUA daftar teks terpisah, dan rekap per inspector memang diterbitkan.
    expect(Object.keys(summaryBody.text_entries ?? {})).toEqual(
      expect.arrayContaining(['action_taken_status', 'findings']),
    )
    expect(summaryBody.inspector_recap).toBeDefined()

    // NOL KUNCI SEBARAN KATEGORIS, NOL KUNCI DOWNTIME, NOL KUNCI AMBANG, dan
    // NOL KUNCI GILIRAN KERJA — diasersi atas NAMA KUNCI. Payload ini memuat
    // dua kalimat manusia (`coverage._note_filled_rule` dan
    // `_note_absences`) yang justru MEMBAHAS ketiadaan itu, jadi memindai
    // teks respons akan menjawab pertanyaan yang salah.
    const keys = allKeysOf(summaryBody)

    for (const forbidden of [
      'status_distribution', 'known_values', 'counts', 'distribution',
      'value_counts', 'status_counts', 'by_status',
      'downtime', 'downtime_minutes', 'stoppages',
      'out_of_range', 'threshold', 'thresholds', 'target', 'targets',
      'violation', 'severity', 'flag', 'flags',
      'shift', 'shifts', 'by_shift', 'shift_distribution',
      'inspector_name', 'inspector_names',
    ]) {
      expect(keys, `payload tidak boleh punya kunci ${forbidden}`).not.toContain(forbidden)
    }

    const exported = await page.request.get(
      `/api/process-water-reports/export?period_id=${periodId}&production_line_id=${lineId}&format=csv`,
      { headers },
    )

    expect(exported.status()).toBe(200)
    expect(exported.headers()['content-type']).toContain('text/csv')

    // DAN `shift` ADA DI BERKAS EKSPOR walau tidak ada di payload: ekspor
    // adalah rincian MENTAH, dan mengecualikan sebuah kolom dari pemeriksaan
    // slot terisi bukan alasan membuangnya dari berkas.
    const header = (await exported.text()).split('\n')[0]

    expect(header).toContain('Shift')
    expect(header).toContain('Inspector ID')

    // Dan mill yang terbaca adalah mill AKUN, bukan mill fixture spec ini:
    // business_unit_id kiriman klien memang tidak pernah dihormati bagi peran
    // terikat mill — tempat KETIGA dari tiga tempat penerimaan Operator, dan
    // tanpanya penerimaan itu adalah kebocoran lintas mill.
    const leak = await page.request.get(
      `/api/process-water-reports/summary?period_id=${periodId}&production_line_id=${lineId}`
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
   * 'process-water' pada StationReportService::REPORT_ROUTES, DISISIPKAN
   * antara engine-room (sort_order 120) dan storage-tank (140). Tanpa test
   * ini, layar laporannya bisa lengkap, seluruh test backend hijau, dan
   * tile-nya tetap kelabu — pola kegagalan yang sudah pernah terjadi pada
   * laporan Cages & Tracks versi web.
   */
  test('invarian (a) — tile Process Water aktif dan menavigasi ke layar laporannya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(STATION_REPORT_PATH)

    // Grid tile screen-140 sendiri digate oleh Production Line — tile baru
    // digambar setelah satu line dipilih.
    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })
    await expect(page.locator('[data-testid="station-grid"]')).toBeVisible()

    const tile = page.locator('[data-testid="station-tile-process-water"]')

    await expect(tile).toBeVisible()
    // AKTIF, bukan kelabu: kelas .active dan href yang benar-benar ada.
    await expect(tile).toHaveClass(/active/)
    await expect(tile).toHaveAttribute('href', /\/reports\/process-water/)

    await tile.click()

    // DIBANDINGKAN SEBAGAI PATHNAME, bukan lewat toHaveURL('**/x'): pola glob
    // itu dipadu dengan baseURL TIDAK PERNAH cocok, dan kegagalannya terbaca
    // seperti navigasi yang tidak terjadi. Tautannya membawa business_unit_id
    // dan production_line_id, jadi query-nya diabaikan di sini.
    await page.waitForURL((url) => url.pathname === REPORT_PATH)
    expect(new URL(page.url()).pathname).toBe(REPORT_PATH)

    await expect(page.locator('[data-testid="laporan-process-water"]')).toBeVisible()

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
