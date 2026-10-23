import { expect, test, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { removeOpenPeriodForForms, seedOpenPeriodForForms } from './support/period-fixture'
import { statefulHeaders } from './support/periods'

/**
 * Laporan Periode Kernel Dispatch (Browser/Playwright) —
 * screen-164--laporan-kernel-dispatch-web /
 * usecase-170--laporan-kernel-dispatch-web.
 *
 * ────────────────────────────────────────────────────────────────────────
 * ASERSI INVARIAN, BUKAN ANGKA YANG DIPAKU
 * ────────────────────────────────────────────────────────────────────────
 * Spec ini TIDAK menanam data laporannya sendiri dan TIDAK mengasersi satu
 * jumlah pengiriman yang tetap pun — pola yang sama dengan
 * tests/laporan-solid-waste-disposal.spec.ts, dengan dua alasan yang sama dan
 * keduanya menentukan:
 *
 * 1. Angka laporan ini sudah dibuktikan TIGA kali di backend terhadap data
 *    yang dikendalikan penuh — atas service-nya (67 kasus di
 *    backend/tests/Unit/Services/KernelDispatchReportServiceTest.php), atas
 *    markup ter-render komponennya (39 uji di
 *    backend/tests/Feature/Livewire/LaporanKernelDispatchTest.php), dan atas
 *    kontrak HTTP-nya (32 uji di
 *    backend/tests/Feature/Api/LaporanKernelDispatchTest.php). Mengulangnya
 *    lewat browser hanya memperlambat suite.
 *
 * 2. `form-kernel-dispatch.spec.ts` MENULIS record baru ke database e2e yang
 *    SAMA, pada mill dan line fixture yang sama. Jumlah pengiriman karena itu
 *    bergantung pada spec mana yang sudah berjalan lebih dulu. Asersi "tepat N
 *    pengiriman" akan hijau sendiri dan merah ketika urutannya berubah — test
 *    yang gagal bukan karena produknya salah adalah test yang akan diabaikan
 *    orang, lalu dihapus. Yang dipakai di sini >= 1 dan sifat strukturalnya;
 *    angka persisnya milik uji unit.
 *
 *    SATU ASERSI TIDAK DAPAT DIBUAT INVARIAN DENGAN CARA ITU, dan karena itu
 *    spec ini membaca lajur yang berbeda: `missing_waybill_count` = TEPAT NOL
 *    (invarian (d)). Record yang ditulis spec form tidak punya nomor surat
 *    jalan, jadi di "PL Mill A" nolnya bergantung urutan spec. Lihat
 *    PRODUCTION_LINE di bawah — spec ini membaca "PL Mill B", lajur yang tidak
 *    ditulis satu spec pun.
 *
 * ────────────────────────────────────────────────────────────────────────
 * BENTUK FIXTURE e2e MEMBATASI APA YANG DAPAT DIASERSIKAN DI SINI
 * ────────────────────────────────────────────────────────────────────────
 * Diverifikasi langsung di `mill_smart_log_e2e` pada 2026-10-18: 2 baris
 * detail (`event_date` = 2025-03-10), status induk `draft_ongoing`,
 * `net_weight_mt` = 20 sementara gross DAN tare KEDUANYA null,
 * `waybill_number` TERISI ('SJ-PL-A' / 'SJ-PL-B'), dan SEMUA SISANYA NULL:
 * ketiga kolom kategoris (`transporter_contractor`, `silo_source_id`,
 * `destination_buyer`), KEEMPAT kolom mutu, KEDUA nomor segel, dan
 * `remarks_gate_status`. Periode prasyarat membentang 2020-01-01 sampai
 * hari-ini+7, jadi ia MEMUAT kedua baris itu.
 *
 * KONSEKUENSI YANG MEMBATALKAN BEBERAPA ASERSI test_scenarios — dan ketiganya
 * dicatat alih-alih dibiarkan tampak teruji:
 *
 *   - PENGURUTAN REKAP. Karena ketiga kolom kategoris null, setiap rekap teks
 *     bebas hanya punya SATU baris (baris null itu sendiri). Asersi apa pun
 *     tentang POSISI baris null — 'di indeks 0', 'bukan baris terakhir' —
 *     karena itu VAKUM di browser: satu baris selalu baris pertama DAN baris
 *     terakhir sekaligus. Pengurutan diuji HANYA di backend, tempat
 *     fixture-nya dapat disusun (uji unit case 42/43).
 *   - NILAI MUTU. Keempat kolom mutu null, jadi tidak ada satu angka mutu pun
 *     yang dapat dibandingkan di sini. Yang justru IDEAL untuk diuji di sini
 *     adalah kebalikannya — lihat invarian (c) di bawah.
 *   - JUMLAH PENGIRIMAN PERSIS. Lihat alasan (2) di atas.
 *
 * SEBALIKNYA, fixture itu justru IDEAL untuk LIMA hal yang semuanya jalur
 * paling mudah ditulis salah dan paling jarang punya data nyata:
 *   (c) KEEMPAT ukuran mutu menampilkan '—' dengan penyebut 0 SEMENTARA
 *       tonasenya BERANGKA. Inilah jebakan utama layar ini dilihat dari arah
 *       yang paling berbahaya: blok mutu yang kosong TIDAK BOLEH mengosongkan
 *       blok tonase, dan sebaliknya.
 *   (d) KEDUA penghitung segel >= 1 SEMENTARA `missing_waybill_count` = NOL.
 *       Nol itu bukan hiasan: ia membuktikan penghitungnya benar-benar
 *       memeriksa kolomnya dan tidak sekadar memulangkan jumlah baris.
 *   (e) `by_gate_status` punya baris 'tidak tercatat' SEMENTARA
 *       `not_released_count` = 0 — dua keadaan yang berbeda, dan
 *       menggabungkannya MENUDUH pengiriman yang sekadar belum diisi
 *       statusnya.
 *   (f) KETIGA rekap teks bebas punya baris 'tidak tercatat'.
 *   (g) Record `draft_ongoing` IKUT terhitung, dan `draft_dispatch_count`
 *       menyatakannya.
 *
 * DATABASE-NYA mill_smart_log_e2e, BUKAN mill_smart_log. Dijaga tiga kali:
 * backend/.env.e2e menunjuk database itu, e2e-web/scripts/prepare-db.sh
 * MENOLAK berjalan kecuali nama databasenya berakhiran '_e2e', dan
 * tests/support/global-setup.ts memeriksa identitas server sebelum satu test
 * pun jalan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG DIUJI DI SINI KARENA HANYA BROWSER DAPAT MEMBUKTIKANNYA
 * ────────────────────────────────────────────────────────────────────────
 *   (a) TILE Kernel Dispatch pada Laporan Stasiun (screen-140) menyala dan
 *       mendarat di layar ini. Tanpa test ini, satu baris yang hilang pada
 *       StationReportService::REPORT_ROUTES membuat layar ini ada, seluruh
 *       test backend lulus, dan tile-nya tetap kelabu — pola kegagalan yang
 *       sudah pernah terjadi pada laporan Cages & Tracks versi web. Kuncinya
 *       'kernel-dispatch' BERTANDA HUBUNG, dan entrinya DI-APPEND di akhir map
 *       sesudah solid-waste-disposal (sort_order 150 -> 160).
 *   (b) HALAMANNYA HIDUP: Livewire terpasang dan wire:model bekerja, sehingga
 *       pemilihnya memicu putaran yang mengubah halaman. Component test tidak
 *       dapat menangkap jebakan wire:id-menempel-pada-<style> yang pernah
 *       mematikan SELURUH wire:model di screen-140.
 *   (c) sampai (g) — lihat daftar fixture di atas.
 *   (h) PENYEBUT RATA-RATA TERCETAK DI DALAM KARTU RATA-RATANYA SENDIRI, dan
 *       PENYEBUT TIAP UKURAN MUTU DI DALAM KARTU UKURAN ITU — disasarkan ke
 *       testid kartunya, bukan ke halaman, karena angka sependek '2' atau '0'
 *       cocok di mana saja.
 *   (i) NOL ELEMEN berkelas penanda di luar batas, disisir atas NAMA KELAS
 *       lewat locator — bukan atas frasa dan bukan atas sumber halaman.
 *       Kalimat yang menyatakan ketiadaannya sendiri memuat frasa "di luar
 *       batas", dan `.md-threshold` DIDEFINISIKAN pada partial stylesheet
 *       bersama report-styles.blade.php sehingga namanya muncul pada HTML
 *       setiap laporan tanpa pernah dipakai. Locator menghitung ELEMEN, dan
 *       itulah sebabnya bentuk locator yang benar.
 *   (j) TIDAK ADA BLOK PER JAM dan TIDAK ADA WAKTU PUTAR KENDARAAN —
 *       diasersi atas TESTID, DAN paragraf _note_no_hourly hadir. DUA
 *       MEKANISME BERBEDA dengan sengaja: prosa itu MEMUAT kata 'jam' DAN
 *       menyebut 'CPO Dispatch', jadi pencarian teks akan cocok dengan kalimat
 *       yang justru menyatakan ketiadaannya dan lolos tanpa membuktikan apa
 *       pun.
 *   (k) Kolom `shift` TIDAK ADA di layar tetapi ADA di berkas ekspor, bersama
 *       kelima kolom lain yang tidak muncul di satu angka layar pun.
 *   (l) Rekap harian menyerap gulir mendatar DI DALAM kartunya sendiri
 *       sementara HALAMANNYA tidak pernah menggulir mendatar — DIUKUR secara
 *       geometris, bukan disimpulkan dari nama kelas, karena toBeVisible TIDAK
 *       membuktikan cacat tampilan ini.
 *   (m) Operator DITOLAK pada rute WEB — sementara KETIGA rute API DATA-nya
 *       justru menerimanya 200 SEJAK COMMIT PERTAMA (kembaran mobile
 *       screen-165 dibangun dalam seri yang sama), dan
 *       /business-units/options tetap 403. Ketiga jawaban berbeda itulah
 *       invariannya, DAN business_unit_id kiriman klien tetap dibuang.
 *   - EKSPOR benar-benar mengunduh berkas, dan yang Excel benar-benar .xlsx.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG SENGAJA TIDAK DIASERSI DI SINI
 * ────────────────────────────────────────────────────────────────────────
 *   - PENGURUTAN rekap dan POSISI baris 'tidak tercatat' — vakum pada fixture
 *     ini, lihat di atas.
 *   - `completeness.percent`. Periode prasyarat membentang 2020-01-01 sampai
 *     hari-ini+7, yaitu ~2490 hari terhitung dengan 1 hari berpengiriman,
 *     sehingga persennya membulat menjadi 0,0. Angka itu BENAR, tetapi
 *     mengasersikannya mengunci sebuah kebetulan kalender dan bukan sebuah
 *     aturan. Yang diuji di sini hanyalah SELNYA TERBACA dan keterangan
 *     'periode masih berjalan' hadir.
 *   - Keadaan 'periode BELUM MULAI'. Periode prasyarat selalu BERJALAN di
 *     browser; periode masa depan tambahan akan bertumpang-tindih dan ditolak
 *     422 PERIOD_OVERLAP.
 *   - Belasan keadaan yang menuntut database tersusun per test: akun terikat
 *     mill dengan business_unit_id null, mill tanpa periode Kernel Dispatch,
 *     periode tanpa data, periode yang seluruh pengirimannya belum ditimbang,
 *     nilai mutu per ukuran dengan penyebut berbeda-beda, nilai kategoris yang
 *     hanya berbeda besar-kecil huruf, nilai `remarks_gate_status` di luar
 *     kedua pilihan yang dikenal (skema MENOLAK-nya lewat CHECK constraint),
 *     pengiriman tanpa `event_date` (kolomnya NOT NULL), line dengan dua unit
 *     Kernel Dispatch, periode berstatus closed, dan id mill/line/periode mill
 *     lain lewat properti komponen. Semuanya dibuktikan di backend, yang
 *     memang punya database sendiri per test.
 */

const REPORT_PATH = '/reports/kernel-dispatch'
const STATION_REPORT_PATH = '/reports'

const BUSINESS_UNIT = 'BU Browser Test'

/**
 * LAJUR BACA SPEC INI — "PL Mill B", BUKAN "PL Mill A".
 *
 * Ini keputusan yang menentukan, bukan selera, dan satu asersi di berkas ini
 * bergantung padanya.
 *
 * `form-kernel-dispatch.spec.ts` MENULIS satu record kernel dispatch baru ke
 * database e2e yang SAMA, dan ia menulisnya ke "PL Mill A" (diukur:
 * `selectOption({ label: 'PL Mill A' })`), bertanggal 2026-08-31 — tanggal
 * yang ikut dimuat periode prasyarat di bawah. Record itu mengisi HANYA id
 * bisnis, tanggal record, dan tanggal kejadian: nomor surat jalannya KOSONG.
 *
 * Di "PL Mill A", `missing_waybill_count` karena itu bernilai 0 bila spec form
 * belum berjalan dan 1 bila sudah — dan invarian (d) di bawah mengasersinya
 * TEPAT NOL, karena justru nol itulah yang membuktikan penghitungnya memeriksa
 * kolomnya alih-alih memulangkan jumlah baris. Di lajur itu asersi tersebut
 * akan hijau sendiri ketika spec ini dijalankan sendirian dan merah ketika
 * seluruh suite berjalan (`form-*` mendahului `laporan-*` secara alfabet),
 * yaitu kegagalan yang tidak disebabkan produknya — test semacam itu akan
 * diabaikan orang, lalu dihapus.
 *
 * "PL Mill B" adalah lajur yang TIDAK DITULIS SATU SPEC PUN. Barisnya ditanam
 * BrowserTestFixtureSeeder::productionLineFilterFixtures() (<AWALAN>-BROWSER-PL-B,
 * 2025-03-10), ke-18 spec `form-*` menulis ke "PL Mill A", dan ke-18 spec
 * `data-browser-*` hanya MEMBACA. Profilnya identik dengan baris PL Mill A —
 * netto 20, `waybill_number` 'SJ-PL-B', sisanya null — sehingga tidak satu pun
 * asersi lain di berkas ini berubah artinya, sementara nol pada surat jalan
 * menjadi nol pada setiap run.
 *
 * Line ini memikul stasiun `kernel-dispatch` yang AKTIF (diperiksa di tabel
 * `stations`), jadi tile screen-140 pun menyala untuknya.
 */
const PRODUCTION_LINE = 'PL Mill B'

/**
 * Line "BU Browser Test" yang TIDAK memikul satu record Kernel Dispatch pun —
 * diukur di `mill_smart_log_e2e`: nol baris pada `kernel_dispatch_records`.
 *
 * Dengannya keadaan 'periode tanpa satu pun pengiriman' dapat dibuktikan di
 * browser TANPA menanam apa pun dan tanpa menyentuh periode: periodenya tetap
 * periode prasyarat yang sama, yang berganti hanyalah line yang dibaca.
 *
 * TIDAK ADA PENJAGA 'line harus memikul stasiun ini' di layar maupun service —
 * opsinya seluruh line mill yang berlaku (productionLineOptions()), dan
 * penyaringannya atas `production_line_id` MILIK RECORD. Line tanpa stasiun
 * karena itu sah dibaca dan menjawab nol, bukan 422.
 */
const EMPTY_PRODUCTION_LINE = 'PL Mill Kecil'

const SUPERVISOR = 'stest-supervisor01'
const ADMIN = 'brtest-admin01'
/**
 * Operator tidak punya akses ke layar WEB ini, dan tidak pernah akan punya:
 * penjagaannya ada pada middleware rute DAN pada canAccess() komponen, yang
 * memegang daftar perannya SENDIRI.
 *
 * Di sini bahayanya BUKAN di masa depan: KernelDispatchReportService::
 * guardAccess() MENERIMA Operator HARI INI, sejak commit pertama, karena
 * kembaran mobile screen-165 dibangun dalam seri yang sama dan merupakan
 * pemanggil yang sah. Kalau canAccess() mendelegasikan ke service, layar web
 * ini terbuka untuk Operator SEKARANG — tanpa satu galat pun.
 */
const OPERATOR = 'operator01'

/** KETIGA rekap teks bebas, dengan testid tabelnya. */
const TEXT_BREAKDOWNS = ['by-transporter', 'by-silo-source', 'by-destination-buyer']

/** KEEMPAT ukuran mutu, dalam urutan KernelDispatchReportService::QUALITY_MEASURES. */
const QUALITY_MEASURES = [
  'kernel_moisture_percent',
  'dirt_impurities_percent',
  'ffa_percent',
  'broken_kernel_percent',
]

/**
 * TESTID BLOK PER JAM / SHIFT / WAKTU PUTAR yang WAJIB NOL di layar ini.
 *
 * Diambil dari laporan yang MEMANG punya blok itu, supaya daftarnya bukan
 * karangan: siapa pun yang membangun blok semacam itu di sini hampir pasti
 * menyalin markup dari salah satunya, beserta testid-nya.
 */
const FORBIDDEN_TESTIDS = [
  'hourly-block',
  'hourly-table',
  'hourly-chart',
  'hourly-distribution',
  'busiest-hour',
  'empty-hour-count',
  'shift-select',
  'shift-column',
  'by-shift-table',
  'kpi-shift',
  'turnaround-card',
  'kpi-turnaround',
  'turnaround-table',
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

/** Lebar gulir halaman — dipakai untuk membuktikan HALAMAN tidak menggulir. */
async function pageOverflowOf(page: Page): Promise<{ scrollWidth: number; clientWidth: number }> {
  return page.evaluate(() => {
    const root = document.scrollingElement ?? document.documentElement

    return { scrollWidth: root.scrollWidth, clientWidth: root.clientWidth }
  })
}

/**
 * Membuka laporan untuk line fixture dan memastikan sebuah periode terpilih.
 *
 * Periode TIDAK dipilih dengan nama: periode mana yang terbaru di database
 * bersama ini bukan sesuatu yang spec ini kendalikan. Yang dibutuhkannya hanya
 * "sebuah periode yang sah", dan pemilih periode memang memilih sendiri
 * periode terbaru pada muat pertama.
 */
async function openReportWithData(page: Page, line: string = PRODUCTION_LINE): Promise<void> {
  await page.goto(REPORT_PATH)
  await expect(page.locator('[data-testid="laporan-kernel-dispatch"]')).toBeVisible()

  await page.locator('[data-testid="production-line-select"]').selectOption({ label: line })

  await expect(page.locator('[data-testid="report-hero"]')).toBeVisible()
  // Kotak tonase+mutu dirender untuk SETIAP periode yang sah, berdata atau
  // tidak. Menunggunya membuktikan putaran Livewire mendarat, dan mencegah
  // count() yang menjawab 0 palsu karena wilayahnya belum ada.
  await expect(page.locator('[data-testid="dispatch-quality-card"]')).toBeVisible()
}

/** Jumlah pengiriman periode, dibaca dari kartunya sendiri. */
async function dispatchCountOf(page: Page): Promise<number> {
  const raw = await page.locator('[data-testid="dispatch-count"]').innerText()
  const value = parseIdNumber(raw)

  expect(value, `tidak bisa membaca jumlah pengiriman dari "${raw}"`).not.toBeNull()

  return value as number
}

/**
 * PRASYARAT PERIODE PELAPORAN.
 *
 * Spec ini sengaja tidak menanam data laporannya sendiri, tetapi PERIODE-nya
 * tetap harus ada: tanpa satu pun periode yang mencakup Kernel Dispatch di
 * "BU Browser Test", pemilih periodenya kosong,
 * `[data-testid="dispatch-quality-card"]` tidak pernah muncul, dan hampir
 * seluruh skenario gagal sebagai "element(s) not found" — seolah produknya
 * yang rusak.
 *
 * DAN TANPA HOOK INI SAMA SEKALI, beforeAll MENGGANTUNG alih-alih gagal, jadi
 * kegagalannya tidak akan menyebut periode sama sekali.
 *
 * seedOpenPeriodForForms() idempoten dan MEMAKAI ULANG periode yang sudah ada
 * (findPeriodByPrefix), jadi memanggilnya tidak menambah periode kedua ketika
 * spec `form-kernel-dispatch` memang jalan lebih dulu — ia hanya menghapus
 * ketergantungan pada urutan alfabet, dan membuat spec ini dapat dijalankan
 * SENDIRIAN setelah scripts/prepare-db.sh.
 *
 * RENTANGNYA MEMANG MEMUAT DATANYA: FIXTURE_START '2020-01-01' sampai
 * hari-ini+7, sementara kedua baris detail fixture bertanggal 2025-03-10.
 *
 * KUNCI STASIUNNYA 'kernel-dispatch' BERTANDA HUBUNG. Fixture
 * mencocokkannya dengan `station.station_type` dari API, yang berisi nilai
 * enum-nya; 'kernel_dispatch' gagal di period-fixture.ts dengan "periode
 * prasyarat tidak punya baris stasiun".
 */
let periodFixturePage: Page | undefined

test.beforeAll(async ({ browser }) => {
  // 30 detik bawaan Playwright untuk sebuah hook TIDAK CUKUP, dan itu terukur:
  // `php artisan serve` melayani satu permintaan sekaligus, jadi login lewat
  // UI ditambah beberapa panggilan API periode melewatinya pada mesin sibuk.
  test.setTimeout(180_000)
  periodFixturePage = await seedOpenPeriodForForms(browser, 'kernel-dispatch')
})

test.afterAll(async () => {
  // Dijaga: kalau beforeAll gagal sebelum mengembalikan page-nya, afterAll
  // tidak boleh menimpa kegagalan itu dengan TypeError miliknya sendiri.
  test.setTimeout(120_000)

  if (periodFixturePage !== undefined) {
    await removeOpenPeriodForForms(periodFixturePage)
  }
})

test.describe('Laporan Periode Kernel Dispatch (screen-164)', () => {
  // Skenario 7: "Production Line wajib dipilih, tanpa opsi 'semua line'"
  test("Supervisor: tanpa pemilih mill, NOL angka sebelum line dipilih, dan tanpa opsi 'semua line'", async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(REPORT_PATH)

    await expect(page.locator('[data-testid="laporan-kernel-dispatch"]')).toBeVisible()

    // Peran terikat mill tidak melihat pemilih mill — menawarkan pemilih yang
    // tidak bisa ia pakai adalah kebohongan kecil yang mahal.
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)

    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()

    // Dan BENAR-BENAR tidak ada angka: bukan sekadar bagian yang
    // disembunyikan, dan BUKAN angka seluruh mill sebagai penggantinya.
    for (const testid of [
      'dispatch-quality-card',
      'gate-status-card',
      'compliance-card',
      'daily-table',
      'completeness-card',
      'draft-card',
    ]) {
      await expect(page.locator(`[data-testid="${testid}"]`), testid).toHaveCount(0)
    }

    // KEDUA TOMBOL EKSPOR TIDAK ADA DI DOM — count() setelah arahan tampil,
    // bukan toBeHidden: yang diklaim adalah 'belum dirender', bukan
    // 'tersembunyi'.
    await expect(page.locator('[data-testid="export-button"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="export-excel-button"]')).toHaveCount(0)

    await expect(page.locator('[data-testid="select-production-line-hint"]'))
      .toContainText('tidak menampilkan satu angka pun')

    // PEMILIH LINE TIDAK PUNYA OPSI 'SEMUA LINE' — disisir atas NILAI
    // option-nya, bukan atas labelnya: sebuah opsi ber-value kosong adalah
    // placeholder, dan sebuah opsi bernilai 'all'/'semua' adalah mode
    // semua-line yang dilarang.
    const values = await page.locator('[data-testid="production-line-select"] option')
      .evaluateAll((nodes) => nodes.map((node) => (node as HTMLOptionElement).value))

    for (const value of values) {
      expect(['all', 'semua', '*'], `pemilih line punya opsi semua-line: ${value}`).not.toContain(value.toLowerCase())
    }
  })

  // Invarian (b): halaman hidup.
  test('invarian (b) — memilih Production Line memicu putaran Livewire yang mengubah halaman', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(REPORT_PATH)

    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })

    // INILAH yang hanya browser dapat buktikan: bila atribut wire:id menempel
    // pada sebuah <style> alih-alih pada root komponen, SELURUH wire:model
    // mati tanpa satu pun galat, dan baris ini yang menangkapnya.
    await expect(page.locator('[data-testid="select-production-line-hint"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="dispatch-quality-card"]')).toBeVisible()
    await expect(page.locator('[data-testid="hero-production-line"]')).toContainText(PRODUCTION_LINE)
    await expect(page.locator('[data-testid="production-line-current"]')).toBeVisible()
    await expect(page.locator('[data-testid="period-status-badge"]')).toBeVisible()
  })

  // Skenario 1 + 15 + invarian (h).
  test('skenario 1 / 15 — tonase dan mutu dirender DI DALAM SATU KOTAK, dan penyebut rata-rata ada di dalam kartunya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    // >= 1, bukan angka pasti: form-kernel-dispatch menulis ke database yang
    // sama (lihat docblock berkas).
    const dispatchCount = await dispatchCountOf(page)

    expect(dispatchCount, 'periode fixture tidak memuat satu pengiriman pun').toBeGreaterThanOrEqual(1)

    // SATU KOTAK — keempat kartu mutu berada DI DALAM section yang sama dengan
    // kartu tonase. Diukur sebagai STRUKTUR DOM, bukan sebagai kedekatan
    // visual: tonase tinggi dengan kadar air tinggi adalah kabar buruk yang
    // menyamar sebagai kabar baik, dan memisahkan keduanya membuat pembaca
    // menyimpulkan dari satu angka saja.
    const kotak = page.locator('[data-testid="dispatch-quality-card"]')

    await expect(kotak).toBeVisible()
    await expect(kotak.locator('[data-testid="kpi-dispatch-count"]')).toHaveCount(1)
    await expect(kotak.locator('[data-testid="kartu-total-netto"]')).toHaveCount(1)
    await expect(kotak.locator('[data-testid="kartu-rata-netto"]')).toHaveCount(1)
    await expect(kotak.locator('[data-testid="kartu-penimbangan-belum-selesai"]')).toHaveCount(1)
    await expect(kotak.locator('[data-testid="quality-card"]')).toHaveCount(4)

    for (const measure of QUALITY_MEASURES) {
      await expect(kotak.locator(`[data-measure="${measure}"]`), measure).toHaveCount(1)
    }

    // PENYEBUT RATA-RATA DI DALAM KARTU RATA-RATANYA SENDIRI — angka sependek
    // '2' cocok di mana saja pada halaman ini, jadi ia disasarkan ke kartunya.
    const kartuRata = page.locator('[data-testid="kartu-rata-netto"]')

    await expect(kartuRata.locator('[data-testid="net-weight-dispatch-count"]')).toHaveCount(1)

    const penyebut = parseIdNumber(
      await kartuRata.locator('[data-testid="net-weight-dispatch-count"]').innerText(),
    )

    expect(penyebut, 'penyebut rata-rata tidak terbaca').not.toBeNull()
    expect(penyebut as number).toBeGreaterThanOrEqual(1)

    // Dan penyebut itu TIDAK LEBIH BESAR daripada jumlah pengiriman.
    expect(penyebut as number).toBeLessThanOrEqual(dispatchCount)

    // Prosa pokok hadir.
    await expect(page.locator('[data-testid="hybrid-note"]')).toBeVisible()
    await expect(page.locator('[data-testid="quality-note"]')).toBeVisible()
  })

  // Invarian (c) — JEBAKAN UTAMA LAYAR INI, dilihat dari arah yang paling
  // berbahaya. Fixture e2e-nya memang begini: keempat kolom mutu null
  // sementara net_weight_mt = 20.
  test('invarian (c) — KEEMPAT ukuran mutu menampilkan tanda pisah dengan penyebut 0 SEMENTARA tonasenya berangka', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    // (1) TONASE BERANGKA.
    const total = parseIdNumber(await page.locator('[data-testid="net-weight-total"]').innerText())

    expect(total, 'total netto tidak terbaca sebagai angka').not.toBeNull()
    expect(total as number).toBeGreaterThan(0)

    const avg = parseIdNumber(await page.locator('[data-testid="net-weight-avg"]').innerText())

    expect(avg, 'rata-rata netto tidak terbaca sebagai angka').not.toBeNull()
    expect(avg as number).toBeGreaterThan(0)

    // (2) SEMENTARA KEEMPAT UKURAN MUTU '—' dengan penyebut 0 — DISASARKAN KE
    //     KARTU UKURAN ITU, satu per satu. Sebuah penjaga global 'ada data
    //     mutu atau tidak' akan mengosongkan keempatnya DAN tonasenya, dan
    //     asersi (1) di atas yang menangkapnya.
    for (const measure of QUALITY_MEASURES) {
      const kartu = page.locator(`[data-measure="${measure}"]`)

      await expect(kartu, measure).toHaveCount(1)

      // measured_count 0, dibaca dari kartunya sendiri.
      const penyebut = parseIdNumber(
        await kartu.locator('[data-testid="quality-measured-count"]').innerText(),
      )

      expect(penyebut, `penyebut ${measure} tidak terbaca`).toBe(0)

      // Ketiga angkanya tanda pisah, BUKAN 0: nol berarti diuji dan hasilnya
      // nol, '—' berarti tidak pernah diuji.
      for (const slot of ['quality-avg', 'quality-min', 'quality-max']) {
        const isi = (await kartu.locator(`[data-testid="${slot}"]`).innerText()).trim()

        expect(isi, `${measure}.${slot} seharusnya tanda pisah, bukan "${isi}"`).toBe('—')
      }

      // Dan kartunya MENYATAKAN sebabnya — bukan blok yang dihilangkan.
      await expect(kartu.locator('[data-testid="quality-unmeasured"]'), measure).toHaveCount(1)
    }
  })

  // Invarian (d) — DUA penghitung segel >= 1 SEMENTARA waybill = NOL.
  test('invarian (d) — kedua penghitung segel berangka sementara missing_waybill_count NOL, dan ketiganya kartu terpisah', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const kartu = page.locator('[data-testid="compliance-card"]')

    await expect(kartu).toBeVisible()

    // TIGA KARTU TERPISAH, bukan satu angka gabungan.
    await expect(kartu.locator('[data-testid="kpi-missing-waybill"]')).toHaveCount(1)
    await expect(kartu.locator('[data-testid="kpi-missing-seal-top"]')).toHaveCount(1)
    await expect(kartu.locator('[data-testid="kpi-missing-seal-bottom"]')).toHaveCount(1)

    const atas = parseIdNumber(await kartu.locator('[data-testid="missing-seal-top-count"]').innerText())
    const bawah = parseIdNumber(await kartu.locator('[data-testid="missing-seal-bottom-count"]').innerText())
    const waybill = parseIdNumber(await kartu.locator('[data-testid="missing-waybill-count"]').innerText())

    // Kedua nomor segel null pada kedua baris fixture.
    expect(atas, 'penghitung segel atas tidak terbaca').not.toBeNull()
    expect(atas as number).toBeGreaterThanOrEqual(1)
    expect(bawah, 'penghitung segel bawah tidak terbaca').not.toBeNull()
    expect(bawah as number).toBeGreaterThanOrEqual(1)

    // SEMENTARA nomor surat jalan TERISI pada keduanya, jadi penghitungnya
    // NOL. Nol itulah yang membuktikan penghitungnya memang memeriksa
    // KOLOMNYA dan tidak sekadar memulangkan jumlah baris — tiga angka yang
    // semuanya >= 1 akan lolos pada implementasi yang mengembalikan
    // dispatch_count tiga kali.
    expect(waybill, 'penghitung surat jalan tidak terbaca').not.toBeNull()
    expect(
      waybill as number,
      'penghitung surat jalan tampak memulangkan jumlah baris, bukan jumlah yang kosong',
    ).toBe(0)

    // DAN identitas yang berlaku apa pun isi databasenya: setidaknya satu
    // pengiriman MEMANG punya nomor surat jalan. Tanpa baris ini, asersi
    // 'tepat 0' di atas tidak menyatakan apa pun pada periode yang kosong —
    // nol di atas nol pengiriman lolos tanpa membuktikan apa pun.
    const totalPengiriman = await dispatchCountOf(page)

    expect(totalPengiriman, 'periode fixture tidak memuat satu pengiriman pun').toBeGreaterThanOrEqual(1)
    expect(waybill as number).toBeLessThan(totalPengiriman)
    expect(atas as number).toBeLessThanOrEqual(totalPengiriman)
    expect(bawah as number).toBeLessThanOrEqual(totalPengiriman)

    // Dan NOL GRAFIK maupun tabel bernomor segel di dalam kartunya: nomor
    // segel tidak punya besaran, jadi ia tidak pernah dirata-rata, diurutkan,
    // atau digrafikkan.
    await expect(kartu.locator('table')).toHaveCount(0)
    await expect(kartu.locator('canvas')).toHaveCount(0)
    await expect(kartu.locator('[data-testid="compliance-note"]')).toHaveCount(1)
  })

  // Invarian (e) — skenario 25.
  test("invarian (e) — by_gate_status punya baris 'tidak tercatat' SEMENTARA not_released_count NOL", async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const kartu = page.locator('[data-testid="gate-status-card"]')

    await expect(kartu).toBeVisible()

    // BARIS 'TIDAK TERCATAT' BENAR-BENAR DIRENDER — remarks_gate_status null
    // pada kedua baris fixture.
    const barisNull = page.locator('[data-testid="by-gate-status-row-null"]')

    await expect(barisNull).toHaveCount(1)
    await expect(barisNull).toContainText('tidak tercatat')

    // SEMENTARA not_released_count = 0. Pasangan dua asersi itulah yang
    // mencegah tuduhan: 'tidak tercatat' dan 'tidak dilepas' adalah dua
    // keadaan berbeda, dan menggabungkannya MENUDUH pengiriman yang sekadar
    // belum diisi statusnya — tuduhan yang akan sampai ke orang.
    const notReleased = parseIdNumber(
      await page.locator('[data-testid="not-released-count"]').innerText(),
    )

    expect(notReleased, 'penghitung tidak-dilepas tidak terbaca').toBe(0)

    // Dan kartu 'status tercatat' membaca baris null dari rekapnya sendiri.
    const tercatat = parseIdNumber(
      await page.locator('[data-testid="gate-status-recorded-count"]').innerText(),
    )

    expect(tercatat, 'penghitung status-tercatat tidak terbaca').toBe(0)

    // KODE MENTAHNYA tidak pernah dicetak kepada manusia.
    await expect(kartu).not.toContainText('not_released')
    await expect(kartu).toContainText('Not Released')
  })

  // Invarian (f) — skenario 20.
  test("invarian (f) — KETIGA rekap teks bebas punya baris 'tidak tercatat' dan kakinya menjumlah", async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const dispatchCount = await dispatchCountOf(page)

    for (const testid of TEXT_BREAKDOWNS) {
      const tabel = page.locator(`[data-testid="${testid}-table"]`)

      await expect(tabel, testid).toBeVisible()

      // Ketiga kolom kategoris null pada kedua baris fixture, jadi baris
      // 'tidak tercatat' WAJIB ada di ketiganya.
      const barisNull = page.locator(`[data-testid="${testid}-row-null"]`)

      await expect(barisNull, `${testid} kehilangan baris tidak tercatat`).toHaveCount(1)
      await expect(barisNull).toContainText('tidak tercatat')

      // KAKINYA MENJUMLAH ke jumlah pengiriman — baris null yang dibuang akan
      // langsung membuatnya tidak cocok.
      const kaki = page.locator(`[data-testid="${testid}-row-total"]`)

      await expect(kaki, `${testid} tidak punya baris total`).toHaveCount(1)
      await expect(kaki).toContainText(String(dispatchCount))

      // Dan barisnya memang satu-satunya — POSISINYA sengaja TIDAK diasersi:
      // satu baris selalu baris pertama DAN terakhir sekaligus, jadi asersi
      // posisi di sini vakum (lihat docblock berkas).
      await expect(page.locator(`[data-testid="${testid}-row"]`), testid).toHaveCount(0)
    }

    // by_gate_status juga menjumlah ke angka yang sama.
    await expect(page.locator('[data-testid="by-gate-status-row-total"]'))
      .toContainText(String(dispatchCount))
  })

  // Invarian (g) — skenario 33.
  test('invarian (g) — record draft_ongoing IKUT terhitung, dan kartu draft menyatakannya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const dispatchCount = await dispatchCountOf(page)
    const kartu = page.locator('[data-testid="draft-card"]')

    await expect(kartu).toBeVisible()

    const draft = parseIdNumber(await kartu.locator('[data-testid="draft-dispatch-count"]').innerText())

    expect(draft, 'penghitung draft tidak terbaca').not.toBeNull()
    // Kedua record fixture berstatus draft_ongoing.
    expect(draft as number).toBeGreaterThanOrEqual(1)

    // IKUT TERHITUNG: angka draft TIDAK PERNAH melebihi jumlah pengiriman, dan
    // jumlah pengiriman bukan nol meski seluruh record-nya draft. Sebuah
    // implementasi yang MENYARING draft akan memberi dispatch_count 0 di sini.
    expect(draft as number).toBeLessThanOrEqual(dispatchCount)
    expect(dispatchCount).toBeGreaterThanOrEqual(draft as number)
    expect(dispatchCount).toBeGreaterThanOrEqual(1)

    await expect(kartu.locator('[data-testid="draft-note"]')).toHaveCount(1)
  })

  // Invarian (j) — skenario 28.
  test('invarian (j) — nol blok per jam dan nol waktu putar kendaraan di DOM, dan paragraf sebabnya hadir', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    // (1) DIASERSI ATAS TESTID — bukan atas kata 'jam', yang dieja utuh oleh
    //     prosa yang justru menyatakan ketiadaannya.
    for (const testid of FORBIDDEN_TESTIDS) {
      await expect(page.locator(`[data-testid="${testid}"]`), testid).toHaveCount(0)
    }

    // (2) DAN PARAGRAFNYA HADIR, berkelas .md-explain.
    await expect(page.locator('.md-explain[data-testid="no-hourly-note"]')).toHaveCount(1)
    await expect(page.locator('.md-explain[data-testid="shift-not-published-note"]')).toHaveCount(1)

    // Prosanya MENYEBUT CPO Dispatch — perbedaannya terletak pada kolom yang
    // tersedia (time_in/time_out), bukan pada kelengkapan laporannya. Ini
    // SEKALIGUS membuktikan jebakan (1) nyata: pencarian teks atas 'jam' akan
    // cocok di sini.
    await expect(page.locator('[data-testid="no-hourly-note"]')).toContainText('CPO Dispatch')
    await expect(page.locator('[data-testid="no-hourly-note"]')).toContainText('jam')

    // Satu-satunya sumbu waktu yang tersedia adalah tanggal, yaitu rekap
    // harian — dan ia MEMANG ada.
    await expect(page.locator('[data-testid="daily-table"]')).toBeVisible()
  })

  // Invarian (i) — skenario 29.
  test('invarian (i) — nol ELEMEN berkelas penanda di luar batas pada halaman ter-render', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    // DISISIR ATAS NAMA KELAS LEWAT LOCATOR, bukan atas frasa dan bukan atas
    // sumber halaman: `.md-threshold` DIDEFINISIKAN pada stylesheet bersama,
    // jadi namanya memang muncul di HTML setiap laporan tanpa pernah dipakai —
    // sebuah pencarian teks akan selalu menemukannya. Locator menghitung
    // ELEMEN.
    await expect(page.locator('.md-threshold')).toHaveCount(0)
    await expect(page.locator('.is-out-of-range')).toHaveCount(0)
    await expect(page.locator('[data-out-of-range]')).toHaveCount(0)

    for (const testid of ['quality-flag', 'quality-badge', 'threshold-badge', 'out-of-range-badge']) {
      await expect(page.locator(`[data-testid="${testid}"]`), testid).toHaveCount(0)
    }

    // Dan kotak yang MENJELASKAN ketiadaan itu memang ada, berkelas
    // .md-explain — memakai kelas bernama 'threshold' untuk menjelaskan bahwa
    // tidak ada ambang akan memerahkan baris di atas justru karena namanya.
    await expect(page.locator('.md-explain[data-testid="no-quality-thresholds-note"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="no-quality-thresholds-note"]')).toContainText('batas')
  })

  // Skenario 30.
  test('skenario 30 — SATU bagian catatan kejadian (temuan), bukan dua', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    await expect(page.locator('[data-testid="findings-card"]')).toBeVisible()
    await expect(page.locator('[data-testid="findings-note"]')).toHaveCount(1)

    // ASERSI NEGATIF atas ketidakhadiran testid bagian KEDUA: stasiun ini
    // TIDAK punya kolom `remarks`, berbeda dari Solid Waste Disposal, jadi
    // struktur dua-bagian dari sana tidak boleh disalin.
    for (const testid of ['remarks-card', 'remarks-entry', 'remarks-empty', 'remarks-note']) {
      await expect(page.locator(`[data-testid="${testid}"]`), testid).toHaveCount(0)
    }

    // findings null pada kedua baris fixture, jadi layar mencetak PERNYATAAN
    // 'tidak ada' — bukan menghilangkan bagiannya.
    await expect(page.locator('[data-testid="findings-empty"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="findings-entry"]')).toHaveCount(0)
  })

  // Invarian (l) — skenario 34, bagian rekap harian.
  test('invarian (l) — rekap harian dapat ditutup/dibuka, dan gulir mendatar ada DI DALAM kartunya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const details = page.locator('[data-testid="daily-recap-details"]')
    const toggle = page.locator('[data-testid="daily-recap-toggle"]')
    const table = page.locator('[data-testid="daily-table"]')

    await expect(details).toBeVisible()
    await expect(toggle).toBeVisible()
    // <details open> pada paint pertama: tabelnya terlihat sejak awal.
    await expect(table).toBeVisible()

    const dispatchCountBefore = await dispatchCountOf(page)
    const beforeToggle = await pageOverflowOf(page)

    expect(
      beforeToggle.scrollWidth,
      `halaman menggulir mendatar sebelum rekap ditutup: ${beforeToggle.scrollWidth} > ${beforeToggle.clientWidth}`,
    ).toBeLessThanOrEqual(beforeToggle.clientWidth + 1)

    // ──────────────────────────────────────────────────────────────────
    // GULIR MENDATAR DISERAP KARTUNYA — DIUKUR, bukan disimpulkan dari nama
    // kelas, dan BERCABANG, bukan dipaku pada satu arah ketidaksamaan.
    // ──────────────────────────────────────────────────────────────────
    // Rekap harian layar ini hanya LIMA kolom, jadi pada viewport lebar ia
    // memang MUAT. Memaku 'scrollWidth > clientWidth' akan memerahkan spec ini
    // pada tabel yang justru rapi. Yang benar-benar invarian ada dua:
    //   1. wrapper-nya adalah elemen yang MENYERAP kelebihan lebar
    //      (overflow-x pada CSS HASIL COMPILE — kelas `.md-recap` yang dipakai
    //      markup tanpa definisi akan menjawab 'visible' di sini dan memotong
    //      tabelnya di layar);
    //   2. HALAMANNYA tidak menggulir mendatar, bahkan setelah wrapper itu
    //      digulir sampai kolom terakhir.
    const wrapper = details.locator('.md-recap').first()

    await expect(wrapper).toBeVisible()

    const metrics = await wrapper.evaluate((element) => ({
      scrollWidth: element.scrollWidth,
      clientWidth: element.clientWidth,
      overflowX: window.getComputedStyle(element).overflowX,
      tableScrollWidth: (element.querySelector('table') as HTMLElement | null)?.scrollWidth ?? 0,
    }))

    expect(metrics.overflowX, 'kartu rekap harian tidak menyerap gulir mendatar').toBe('auto')

    if (metrics.tableScrollWidth > metrics.clientWidth) {
      expect(
        metrics.scrollWidth,
        `tabel lebih lebar dari kartunya tetapi wrapper tidak menggulir (${metrics.scrollWidth} <= ${metrics.clientWidth})`,
      ).toBeGreaterThan(metrics.clientWidth)

      await wrapper.evaluate((element) => { element.scrollLeft = element.scrollWidth })

      const afterScroll = await pageOverflowOf(page)

      expect(
        afterScroll.scrollWidth,
        'halaman menggulir mendatar setelah kartu rekap digulir',
      ).toBeLessThanOrEqual(afterScroll.clientWidth + 1)
    }

    // DAN TABELNYA TIDAK MELEBIHI INDUKNYA: toBeVisible TIDAK membuktikan
    // cacat tampilan ini — markup dapat benar sementara tabelnya terpotong.
    const wrapperBox = await wrapper.boundingBox()
    const tableBox = await table.boundingBox()

    expect(wrapperBox).not.toBeNull()
    expect(tableBox).not.toBeNull()
    expect(
      tableBox!.x + tableBox!.width,
      'tabel harian meluber keluar dari kartunya',
    ).toBeLessThanOrEqual(wrapperBox!.x + wrapperBox!.width + 1)

    // ──────────────────────────────────────────────────────────────────
    // MENUTUP/MEMBUKA TIDAK MEMICU SATU PERMINTAAN PUN.
    // ──────────────────────────────────────────────────────────────────
    // <details> BAWAAN PERAMBAN: tidak ada atribut wire apa pun padanya, jadi
    // tidak ada putaran Livewire yang dapat terjadi. Dihitung, bukan
    // diasumsikan.
    let requests = 0

    page.on('request', () => { requests++ })

    await toggle.click()
    await expect(table).toBeHidden()
    await toggle.click()
    await expect(table).toBeVisible()

    expect(requests, 'menutup/membuka rekap memicu permintaan jaringan').toBe(0)

    // Dan angka utamanya tidak berubah.
    expect(await dispatchCountOf(page)).toBe(dispatchCountBefore)

    const afterToggle = await pageOverflowOf(page)

    expect(afterToggle.scrollWidth).toBeLessThanOrEqual(afterToggle.clientWidth + 1)
  })

  // Skenario 34, bagian kelengkapan. Periode prasyarat spec ini SELALU masih
  // berjalan (2020-01-01 .. hari+7).
  test('skenario 34 — kelengkapan: selnya terbaca dan keterangan periode-berjalan hadir', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const card = page.locator('[data-testid="completeness-card"]')

    await expect(card).toBeVisible()
    await expect(card.locator('[data-testid="days-with-dispatch"]')).toBeVisible()
    await expect(card.locator('[data-testid="days-counted"]')).toBeVisible()

    // Persentase diasersi HANYA pada selnya, dan HANYA bahwa ia TERBACA.
    // NILAINYA sengaja TIDAK diasersi: periode prasyarat membentang ~2490 hari
    // dengan 1 hari berpengiriman, sehingga persennya membulat menjadi 0,0 —
    // angka itu BENAR, tetapi mengasersikannya mengunci sebuah kebetulan
    // kalender dan bukan sebuah aturan (lihat docblock berkas).
    const percent = await card.locator('[data-testid="days-with-dispatch-percent"]').innerText()

    expect(percent.includes('%') || percent.includes('—'), `sel kelengkapan tak terbaca: ${percent}`).toBe(true)

    // Penyebutnya BERHENTI DI HARI INI, bukan di end_date yang belum tiba.
    await expect(card.locator('[data-testid="period-running-note"]')).toBeVisible()
    await expect(card.locator('[data-testid="period-running-note"]')).toContainText('periode masih berjalan')
    await expect(card.locator('[data-testid="period-not-started-note"]')).toHaveCount(0)
  })

  // Invarian (k) — skenario 31. DUA MEKANISME BERBEDA, dengan sengaja.
  test('invarian (k) — kolom shift tidak ada di layar tetapi ADA di berkas ekspor bersama lima kolom lain', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    // (1) DI LAYAR: nol kolom/kartu/penyaring shift, dicari atas TESTID —
    //     BUKAN atas kata 'shift', yang justru dieja utuh oleh
    //     _note_shift_not_published.
    for (const testid of ['shift-select', 'shift-column', 'by-shift-table', 'kpi-shift']) {
      await expect(page.locator(`[data-testid="${testid}"]`), testid).toHaveCount(0)
    }

    await expect(page.locator('[data-testid="shift-not-published-note"]')).toContainText('shift')

    // (2) DI BERKAS: header ekspor memuat keenam kolom. Dibaca lewat API
    //     dengan header stateful — bukan lewat unduhan DOM, karena yang
    //     diperiksa adalah ISI baris pertamanya.
    const headers = await statefulHeaders(page)

    const periods = await page.request.get('/api/kernel-dispatch-reports/periods', { headers })

    expect(periods.status()).toBe(200)

    const periodId = ((await periods.json()).data ?? [])[0]?.id

    const lines = await page.request.get('/api/production-lines/options-for-report', { headers })

    expect(lines.status()).toBe(200)

    const lineId = ((await lines.json()).data ?? []).find(
      (line: { name: string }) => line.name === PRODUCTION_LINE,
    )?.id

    expect(periodId, 'mill fixture tidak punya satu pun periode Kernel Dispatch').toBeTruthy()
    expect(lineId, `mill fixture tidak punya production line "${PRODUCTION_LINE}"`).toBeTruthy()

    const exported = await page.request.get(
      `/api/kernel-dispatch-reports/export?period_id=${periodId}&production_line_id=${lineId}&format=csv`,
      { headers },
    )

    expect(exported.status()).toBe(200)
    expect(exported.headers()['content-type']).toContain('text/csv')

    const header = (await exported.text()).split('\n')[0]

    // KEENAM KOLOM — kelima di antaranya tidak muncul di satu angka layar pun,
    // dan justru karena itu kelimanya yang paling mudah tertinggal.
    for (const label of [
      'Shift',
      'Weighbridge Ticket No',
      'Waybill Number',
      'Vehicle Plate No',
      'Driver Name',
      'Weighbridge Operator ID',
    ]) {
      expect(header, `header ekspor kehilangan kolom ${label}`).toContain(label)
    }

    // Dan 27 kolom: 6 konteks + 21 detail.
    expect(header.split(',').length).toBe(27)
  })

  test('Ekspor CSV dan Ekspor Excel benar-benar mengunduh berkas', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const csvDownload = page.waitForEvent('download')

    await page.locator('[data-testid="export-button"]').click()

    const csv = await csvDownload

    expect(csv.suggestedFilename()).toMatch(/\.csv$/)
    expect(csv.suggestedFilename()).toContain('laporan-kernel-dispatch')

    const excelDownload = page.waitForEvent('download')

    await page.locator('[data-testid="export-excel-button"]').click()

    const excel = await excelDownload

    // .xlsx SUNGGUHAN lewat SheetWriter, bukan CSV bernama .xlsx. Komponen
    // menerjemahkan 'excel' dari toolbar bersama menjadi 'xlsx' sebelum
    // sampai ke service, yang daftar formatnya tertutup {csv, xlsx}.
    expect(excel.suggestedFilename()).toMatch(/\.xlsx$/)
    expect(excel.suggestedFilename()).toContain('laporan-kernel-dispatch')
  })

  test('layar hanya membaca: tidak ada kontrol tulis apa pun', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const region = page.locator('[data-testid="laporan-kernel-dispatch"]')

    await expect(region.locator('form')).toHaveCount(0)
    await expect(region.locator('input[type="text"]')).toHaveCount(0)
    await expect(region.locator('textarea')).toHaveCount(0)
    await expect(region.locator('button[type="submit"]')).toHaveCount(0)
  })

  // Skenario 3 + 4.
  test('skenario 3 / 4 — Admin: pemilih mill dirender, angka muncul setelah mill + line dipilih', async ({ page }) => {
    await login(page, ADMIN, PASSWORD)
    await page.goto(REPORT_PATH)

    await expect(page.locator('[data-testid="laporan-kernel-dispatch"]')).toBeVisible()

    // ADMIN MELIHAT PEMILIH MILL — dan tidak ada mill default.
    await expect(page.locator('[data-testid="mill-select"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-select-hint"]')).toBeVisible()
    await expect(page.locator('[data-testid="dispatch-quality-card"]')).toHaveCount(0)

    await page.locator('[data-testid="mill-select"]').selectOption({ label: BUSINESS_UNIT })
    await expect(page.locator('[data-testid="mill-select-hint"]')).toHaveCount(0)

    // Mill sudah pasti, line belum.
    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })
    await expect(page.locator('[data-testid="dispatch-quality-card"]')).toBeVisible()

    expect(await dispatchCountOf(page)).toBeGreaterThanOrEqual(1)

    // MENGGANTI MILL mengosongkan KEDUANYA dan menghilangkan kartu angka dari
    // DOM.
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

    // KEDUANYA kembali ke keadaan belum terpilih: sebuah periode MAUPUN sebuah
    // line milik tepat satu mill, jadi membawa salah satunya melintas akan
    // merender penolakan untuk sesuatu yang tidak dilakukan pengguna.
    await expect(page.locator('[data-testid="production-line-select"]')).toHaveValue('')
    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()

    await expect(page.locator('[data-testid="dispatch-quality-card"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="compliance-card"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="gate-status-card"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="draft-card"]')).toHaveCount(0)
    // Dan TIDAK ADA pesan galat tentang periode yang tidak cocok.
    await expect(page.locator('[data-testid="forbidden-notice"]')).toHaveCount(0)
  })

  // Skenario 11 — "periode tanpa satu pun pengiriman". TERSEDIA di DB e2e
  // lewat line "PL Mill Kecil" (nol record), jadi ia TIDAK dilewati: inilah
  // satu-satunya tempat cabang 'tanda pisah, bukan nol' dibuktikan di aplikasi
  // nyata, dan cabang itu persis kebalikan dari seluruh asersi lain di berkas
  // ini.
  test("skenario 11 — periode tanpa pengiriman: tanda pisah BUKAN 0,00 MT, dan pernyataan kosong menyebut periode + line", async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page, EMPTY_PRODUCTION_LINE)

    // Line yang dibaca DINAMAI di hero — tanpa ini, nol di bawah tidak dapat
    // dibedakan dari nol milik line yang salah.
    await expect(page.locator('[data-testid="hero-production-line"]')).toContainText(EMPTY_PRODUCTION_LINE)

    expect(await dispatchCountOf(page), 'line ini seharusnya tidak punya pengiriman').toBe(0)

    // TANDA PISAH, TIDAK PERNAH '0,00 MT': nol berarti tidak ada inti
    // terangkut dan terukur demikian, tanda pisah berarti tidak diketahui.
    // '0,0 MT' di sini adalah cacat yang test ini cari.
    await expect(page.locator('[data-testid="net-weight-total"]')).toHaveText(/^—/)
    await expect(page.locator('[data-testid="net-weight-avg"]')).toHaveText(/^—/)

    // Keempat ukuran mutu juga tanda pisah dengan penyebut 0 — konsisten
    // dengan kartu tonase, dan satu-satunya keadaan di layar ini di mana
    // KEDUANYA memang kosong. Diasersi per kartu lewat data-measure-nya.
    for (const measure of QUALITY_MEASURES) {
      const kartu = page.locator(`[data-testid="quality-card"][data-measure="${measure}"]`)

      await expect(kartu, measure).toHaveCount(1)
      await expect(kartu.locator('[data-testid="quality-avg"]'), measure).toHaveText('—')
      await expect(kartu.locator('[data-testid="quality-unmeasured"]'), measure).toHaveCount(1)
    }

    // PERNYATAAN KOSONG MENYEBUT NAMA PERIODE DAN NAMA LINE, disasarkan ke
    // elemen pernyataannya dan bukan ke halaman: rekap yang menghilang membuat
    // pembaca mengira bagian laporan itu tidak ada, dan pernyataan yang tidak
    // menyebut keduanya membuatnya mengira ia melihat seluruh mill.
    const namaPeriode = (
      await page.locator('[data-testid="period-select"] option:checked').innerText()
    ).trim()

    expect(namaPeriode, 'nama periode terpilih tidak terbaca').not.toBe('')

    for (const testid of TEXT_BREAKDOWNS) {
      const kosong = page.locator(`[data-testid="${testid}-empty"]`)

      await expect(kosong, testid).toHaveCount(1)
      await expect(kosong, testid).toContainText(EMPTY_PRODUCTION_LINE)
      // Dan TIDAK ADA baris 'tidak tercatat': nol pengiriman berarti nol
      // baris, bukan satu baris null berisi nol — rekap yang mengarang baris
      // null di atas data kosong akan memerahkan baris ini.
      await expect(page.locator(`[data-testid="${testid}-row-null"]`), testid).toHaveCount(0)
    }

    const gerbangKosong = page.locator('[data-testid="by-gate-status-empty"]')

    await expect(gerbangKosong).toHaveCount(1)
    await expect(gerbangKosong).toContainText(EMPTY_PRODUCTION_LINE)
    await expect(page.locator('[data-testid="by-gate-status-row-null"]')).toHaveCount(0)

    const harianKosong = page.locator('[data-testid="daily-empty"]')

    await expect(harianKosong).toHaveCount(1)
    await expect(harianKosong).toContainText(EMPTY_PRODUCTION_LINE)

    await expect(page.locator('[data-testid="findings-empty"]')).toHaveCount(1)

    // Ketiga penghitung kepatuhan TETAP dirender dan bernilai nol — bagian
    // laporan yang menghilang ketika datanya kosong adalah bagian yang
    // pembacanya sangka tidak ada.
    for (const testid of ['missing-waybill-count', 'missing-seal-top-count', 'missing-seal-bottom-count']) {
      const nilai = parseIdNumber(await page.locator(`[data-testid="${testid}"]`).innerText())

      expect(nilai, `${testid} tidak terbaca`).toBe(0)
    }
  })

  // Invarian (m) — skenario 2, dan TIGA jawaban berbeda.
  test('invarian (m) — Operator DITOLAK pada rute web, 200 pada ketiga rute API data, 403 pada pemilih mill', async ({ page }) => {
    await login(page, OPERATOR, PASSWORD)

    const response = await page.goto(REPORT_PATH)

    // LAYAR WEB TERTUTUP. Penjagaannya ada pada middleware rute DAN pada
    // canAccess() komponen, yang memegang daftar perannya SENDIRI justru
    // supaya penerimaan Operator di service — yang sudah berlaku HARI INI demi
    // kembaran mobile screen-165 — TIDAK dapat membuka layar web ini.
    expect(response?.status()).toBe(403)

    await expect(page.locator('[data-testid="laporan-kernel-dispatch"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="dispatch-quality-card"]')).toHaveCount(0)
    await expect(page.locator('table')).toHaveCount(0)

    // Header stateful WAJIB ada. Tanpa X-Requested-With + Referer,
    // EnsureFrontendRequestsAreStateful tidak mengaktifkan session guard dan
    // Sanctum menjawab 401 SEBELUM guard peran dievaluasi — 401 itu akan lulus
    // sebagai "ditolak" padahal tidak membuktikan apa pun tentang peran.
    const headers = await statefulHeaders(page)

    // PEMILIH MILL TETAP 403 — diangkat DARI DALAM
    // KernelDispatchReportService::businessUnitOptions(), bukan oleh daftar
    // peran middleware yang memang menerima empat peran demi ketiga rute data.
    const options = await page.request.get(
      '/api/kernel-dispatch-reports/business-units/options',
      { headers },
    )

    expect(options.status()).toBe(403)

    // ────────────────────────────────────────────────────────────────────
    // KETIGA RUTE DATA: 200 SEJAK COMMIT PERTAMA
    // ────────────────────────────────────────────────────────────────────
    // period_id dan production_line_id DITURUNKAN dari jawaban server untuk
    // Operator ini sendiri — bukan dari fixture 'BU Browser Test', yang bukan
    // mill operator01. Keduanya memang diselesaikan server dari AKUN, jadi
    // menurunkannya dari respons adalah satu-satunya cara memberi parameter
    // yang sah tanpa menanam data di database bersama ini.
    const periods = await page.request.get('/api/kernel-dispatch-reports/periods', { headers })

    expect(periods.status()).toBe(200)

    const periodId = ((await periods.json()).data ?? [])[0]?.id

    const lines = await page.request.get('/api/production-lines/options-for-report', { headers })

    expect(lines.status()).toBe(200)

    const lineId = ((await lines.json()).data ?? [])[0]?.id

    // Diasersi, bukan dilewati diam-diam: tanpa keduanya kedua asersi di bawah
    // menjadi 422 tentang parameter yang hilang, dan "tidak 403" yang lulus
    // karena alasan yang salah adalah asersi yang berhenti dibaca orang.
    expect(periodId, 'mill operator01 tidak punya satu pun periode Kernel Dispatch').toBeTruthy()
    expect(lineId, 'mill operator01 tidak punya satu pun Production Line').toBeTruthy()

    const summary = await page.request.get(
      `/api/kernel-dispatch-reports/summary?period_id=${periodId}&production_line_id=${lineId}`,
      { headers },
    )

    expect(summary.status()).toBe(200)

    const summaryBody = await summary.json()

    // NOL KUNCI PER JAM, NOL WAKTU PUTAR, NOL KUNCI SHIFT, NOL PENANDAAN
    // AMBANG, dan NOL PENGHITUNG SEGEL GABUNGAN — diasersi atas NAMA KUNCI.
    // Payload ini memuat tiga kalimat manusia (`_note_no_hourly`,
    // `_note_no_quality_thresholds`, `_note_shift_not_published`) yang justru
    // MEMBAHAS ketiadaan itu, jadi memindai teks respons akan menjawab
    // pertanyaan yang salah.
    const keys = allKeysOf(summaryBody)

    for (const forbidden of [
      'hourly', 'busiest_hour', 'empty_hour_count', 'by_hour',
      'turnaround', 'turnaround_minutes', 'time_in', 'time_out',
      'shift', 'shifts', 'by_shift', 'shift_distribution',
      'out_of_range', 'threshold', 'thresholds', 'target', 'targets',
      'missing_seal_count', 'total_missing_seal',
      'quality_measured_count', 'measured_dispatch_count',
    ]) {
      expect(keys, `payload tidak boleh punya kunci ${forbidden}`).not.toContain(forbidden)
    }

    // Dan KUNCI YANG MEMANG HARUS ADA — tanpa baris ini, daftar larangan di
    // atas akan hijau pada payload kosong.
    for (const required of [
      'dispatches', 'quality', 'by_transporter', 'by_silo_source',
      'by_destination_buyer', 'by_gate_status', 'not_released_count',
      'compliance', 'draft_dispatch_count', 'undated_dispatch_count',
      'daily', 'daily_total', 'completeness', 'text_entries',
      '_note_no_hourly', '_note_no_quality_thresholds',
      '_note_shift_not_published',
    ]) {
      expect(keys, `payload kehilangan kunci ${required}`).toContain(required)
    }

    // Penyebutnya diterbitkan BERSAMA rata-ratanya, di setiap tingkat.
    expect(Object.keys(summaryBody.dispatches ?? {})).toEqual(
      expect.arrayContaining([
        'dispatch_count', 'net_weight_total', 'net_weight_avg',
        'net_weight_dispatch_count', 'missing_net_weight_dispatch_count',
      ]),
    )

    // EMPAT UKURAN MUTU, EMPAT PENYEBUT — masing-masing menerbitkan
    // measured_count-nya SENDIRI, dan tidak ada satu penyebut bersama.
    for (const measure of QUALITY_MEASURES) {
      expect(
        Object.keys(summaryBody.quality?.[measure] ?? {}),
        `ukuran ${measure} tidak menerbitkan min/avg/max/measured_count`,
      ).toEqual(expect.arrayContaining(['min', 'avg', 'max', 'measured_count']))
    }

    // TIGA penghitung kepatuhan TERPISAH, nol kunci gabungan.
    expect(Object.keys(summaryBody.compliance ?? {})).toEqual(
      expect.arrayContaining([
        'missing_waybill_count', 'missing_seal_top_count', 'missing_seal_bottom_count',
      ]),
    )

    // text_entries SATU DAFTAR — stasiun ini tidak punya kolom catatan kedua.
    expect(Object.keys(summaryBody.text_entries ?? {})).toEqual(['findings'])

    const exported = await page.request.get(
      `/api/kernel-dispatch-reports/export?period_id=${periodId}&production_line_id=${lineId}&format=csv`,
      { headers },
    )

    expect(exported.status()).toBe(200)
    expect(exported.headers()['content-type']).toContain('text/csv')
    expect((await exported.text()).split('\n')[0]).toContain('Shift')

    // Dan mill yang terbaca adalah mill AKUN, bukan mill yang diminta klien:
    // business_unit_id memang tidak pernah dihormati bagi peran terikat mill
    // — tempat KETIGA dari tiga tempat penerimaan Operator, dan tanpanya
    // penerimaan itu adalah kebocoran lintas mill.
    const leak = await page.request.get(
      `/api/kernel-dispatch-reports/summary?period_id=${periodId}&production_line_id=${lineId}`
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
   * 'kernel-dispatch' pada StationReportService::REPORT_ROUTES, DI-APPEND di
   * akhir map sesudah solid-waste-disposal (sort_order 150 -> 160). Tanpa test
   * ini, layar laporannya bisa lengkap, seluruh test backend hijau, dan
   * tile-nya tetap kelabu — pola kegagalan yang sudah pernah terjadi pada
   * laporan Cages & Tracks versi web.
   *
   * RIAK YANG SUDAH DIPERIKSA: konstanta UNAVAILABLE_STATION pada
   * tests/laporan-stasiun.spec.ts sudah 'process-quality-control' (sort_order
   * 180) sejak screen-162, jadi pekerjaan screen-164 TIDAK memerahkannya.
   * Riak ini dicari dengan grep atas KODE STASIUNNYA di seluruh backend/tests/
   * dan e2e-web/tests/, bukan dengan memeriksa urutan daftarnya.
   *
   * PEMBARUAN 2026-10-23 (screen-168): konstanta itu kini DIHAPUS dari
   * tests/laporan-stasiun.spec.ts. Laporan Process Quality Control dibangun,
   * dan dengan itu seluruh delapan belas jenis stasiun nyata berlaporan —
   * tidak ada lagi tile kelabu di grid, jadi spec itu mengasersikan KEADAAN
   * AKHIR (setiap tile aktif) alih-alih memakai satu contoh nonaktif.
   */
  test('invarian (a) — tile Kernel Dispatch aktif dan menavigasi ke layar laporannya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(STATION_REPORT_PATH)

    // Grid tile screen-140 sendiri digate oleh Production Line — tile baru
    // digambar setelah satu line dipilih.
    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })
    await expect(page.locator('[data-testid="station-grid"]')).toBeVisible()

    const tile = page.locator('[data-testid="station-tile-kernel-dispatch"]')

    await expect(tile).toBeVisible()
    // AKTIF, bukan kelabu: kelas .active dan href yang benar-benar ada.
    await expect(tile).toHaveClass(/active/)
    await expect(tile).not.toHaveClass(/disabled/)
    await expect(tile).toHaveAttribute('href', /\/reports\/kernel-dispatch/)

    await tile.click()

    // DIBANDINGKAN SEBAGAI PATHNAME, bukan lewat toHaveURL('**/x'): pola glob
    // itu dipadu dengan baseURL TIDAK PERNAH cocok, dan kegagalannya terbaca
    // seperti navigasi yang tidak terjadi. Tautannya membawa business_unit_id
    // dan production_line_id, jadi query-nya diabaikan di sini.
    await page.waitForURL((url) => url.pathname === REPORT_PATH)
    expect(new URL(page.url()).pathname).toMatch(/\/reports\/kernel-dispatch$/)

    await expect(page.locator('[data-testid="laporan-kernel-dispatch"]')).toBeVisible()

    // Mill dan line ikut terbawa, jadi layar tujuan TIDAK meminta memilih lagi
    // — itu sebabnya kedua properti layar ini membawa
    // #[Url(as: 'business_unit_id')] dan #[Url(as: 'production_line_id')]
    // dengan `as:` yang eksplisit: tanpa `as:`, kuncinya menjadi
    // 'businessUnitId'/'productionLineId' dan tautan dari screen-140 tidak
    // pernah terbaca, tanpa satu tanda pun bahwa ada yang salah.
    await expect(page.locator('[data-testid="select-production-line-hint"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="dispatch-quality-card"]')).toBeVisible()
    await expect(page.locator('[data-testid="hero-production-line"]')).toContainText(PRODUCTION_LINE)
  })
})
