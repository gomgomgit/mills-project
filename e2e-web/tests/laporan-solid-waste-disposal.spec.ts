import { expect, test, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { removeOpenPeriodForForms, seedOpenPeriodForForms } from './support/period-fixture'
import { statefulHeaders } from './support/periods'

/**
 * Laporan Periode Solid Waste Disposal (Browser/Playwright) —
 * screen-162--laporan-solid-waste-disposal-web /
 * usecase-168--laporan-solid-waste-disposal-web.
 *
 * ────────────────────────────────────────────────────────────────────────
 * ASERSI INVARIAN, BUKAN ANGKA YANG DIPAKU
 * ────────────────────────────────────────────────────────────────────────
 * Spec ini TIDAK menanam data laporannya sendiri dan TIDAK mengasersi satu
 * jumlah muatan yang tetap pun — pola yang sama dengan
 * tests/laporan-process-water.spec.ts, dengan dua alasan yang sama dan
 * keduanya menentukan:
 *
 * 1. Angka laporan ini sudah dibuktikan dua kali di backend terhadap data yang
 *    dikendalikan penuh — atas service-nya (59 kasus di
 *    backend/tests/Unit/Services/SolidWasteDisposalReportServiceTest.php) dan
 *    atas markup ter-render komponennya (29 skenario di
 *    backend/tests/Feature/Livewire/LaporanSolidWasteDisposalTest.php).
 *    Mengulangnya lewat browser hanya memperlambat suite.
 *
 * 2. `form-solid-waste-disposal.spec.ts` MENULIS record baru ke database e2e
 *    yang SAMA, pada mill dan line fixture yang sama, dan tanggalnya
 *    (2026-08-31) ikut dimuat periode prasyarat di bawah. Jumlah muatan karena
 *    itu bergantung pada spec mana yang sudah berjalan lebih dulu. Asersi
 *    "tepat N muatan" akan hijau sendiri dan merah ketika urutannya berubah —
 *    test yang gagal bukan karena produknya salah adalah test yang akan
 *    diabaikan orang, lalu dihapus. Yang dipakai di sini ≥ 1 dan sifat
 *    strukturalnya; angka persisnya milik uji unit.
 *
 * ────────────────────────────────────────────────────────────────────────
 * BENTUK FIXTURE e2e MEMBATASI APA YANG DAPAT DIASERSIKAN DI SINI
 * ────────────────────────────────────────────────────────────────────────
 * Diverifikasi langsung di `mill_smart_log_e2e` pada 2026-10-18: 2 record, 2
 * baris detail (event_date = record.date = 2025-03-10), status induk
 * `draft_ongoing`, net_weight_mt = 5 sementara gross DAN tare KEDUANYA null,
 * dan KEEMPAT kolom kategoris — beserta gate_pass_no dan security_seal_no —
 * NULL.
 *
 * KONSEKUENSI YANG MEMBATALKAN SATU ASERSI DI test_scenarios: karena keempat
 * kolom kategoris null, setiap rekap kategoris hanya punya SATU baris (baris
 * null itu sendiri) selama belum ada spec lain yang menulis nilai. Asersi apa
 * pun tentang POSISI baris null — 'di atas', 'bukan baris terakhir' — karena
 * itu VAKUM di browser: satu baris selalu baris pertama DAN baris terakhir
 * sekaligus. Pengurutan rekap diuji HANYA di uji unit, tempat fixture-nya
 * dapat disusun. Spec ini tidak mengasersinya, dan tidak membiarkannya tampak
 * teruji.
 *
 * SEBALIKNYA, fixture itu justru IDEAL untuk tiga hal yang semuanya jalur
 * paling mudah ditulis salah dan paling jarang punya data nyata:
 *   (1) baris 'tidak tercatat' BENAR-BENAR dirender pada KEEMPAT rekap;
 *   (2) compliance MENGHITUNG muatan tanpa gate pass dan tanpa segel;
 *   (3) record `draft_ongoing` IKUT terhitung, dan draft_load_count
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
 *   (a) TILE Solid Waste Disposal pada Laporan Stasiun (screen-140) menyala
 *       dan mendarat di layar ini. Tanpa test ini, satu baris yang hilang
 *       pada StationReportService::REPORT_ROUTES membuat layar ini ada,
 *       seluruh test backend lulus, dan tile-nya tetap kelabu — pola
 *       kegagalan yang sudah pernah terjadi pada laporan Cages & Tracks versi
 *       web. Kuncinya 'solid-waste-disposal' BERTANDA HUBUNG, dan entrinya
 *       DI-APPEND di akhir map, sesudah storage-tank (sort_order 140 -> 150).
 *   (b) HALAMANNYA HIDUP: Livewire terpasang dan wire:model bekerja, sehingga
 *       pemilihnya memicu putaran yang mengubah halaman. Component test tidak
 *       dapat menangkap jebakan wire:id-menempel-pada-<style> yang pernah
 *       mematikan SELURUH wire:model di screen-140.
 *   (c) PENYEBUT RATA-RATA TERCETAK DI DALAM KARTU RATA-RATANYA SENDIRI,
 *       disasarkan ke testid kartunya — bukan ke halaman, karena angka
 *       sependek '6' cocok di mana saja.
 *   (d) Baris 'tidak tercatat' dirender pada KEEMPAT rekap (lihat fixture di
 *       atas).
 *   (e) compliance adalah kartu yang TERPISAH dari kartu muatan, memuat dua
 *       penghitung dan satu paragraf, dan NOL grafik maupun tabel bernomor
 *       segel di dalamnya.
 *   (f) NOL ELEMEN berkelas penanda di luar batas, disisir atas NAMA KELAS
 *       lewat locator — bukan atas frasa dan bukan atas sumber halaman.
 *       Kalimat yang menyatakan ketiadaannya sendiri memuat frasa "di luar
 *       batas", dan `.md-threshold` DIDEFINISIKAN pada partial stylesheet
 *       bersama report-styles.blade.php sehingga namanya muncul pada HTML
 *       setiap laporan tanpa pernah dipakai. Locator menghitung ELEMEN, dan
 *       itulah sebabnya bentuk locator yang benar.
 *   (g) TIDAK ADA BLOK PER JAM — diasersi atas TESTID, DAN paragraf
 *       _note_no_hourly hadir. DUA MEKANISME BERBEDA dengan sengaja: prosa
 *       itu MEMUAT kata 'jam' dan 'Sebaran', jadi pencarian teks akan cocok
 *       dengan kalimat yang justru menyatakan ketiadaannya dan lolos tanpa
 *       membuktikan apa pun (persis yang terjadi saat memeriksa mock
 *       screen-161).
 *   (h) Kolom `shift` TIDAK ADA di layar tetapi ADA di berkas ekspor.
 *   (i) Rekap harian menyerap gulir mendatar DI DALAM kartunya sendiri
 *       sementara HALAMANNYA tidak pernah menggulir mendatar — DIUKUR secara
 *       geometris, bukan disimpulkan dari nama kelas, karena toBeVisible
 *       TIDAK membuktikan cacat tampilan ini.
 *   (j) Operator DITOLAK pada rute WEB — sementara KETIGA rute API DATA-nya
 *       justru menerimanya 200 SEJAK COMMIT PERTAMA (kembaran mobile
 *       screen-163 dibangun dalam seri yang sama), dan
 *       /business-units/options tetap 403. Ketiga jawaban berbeda itulah
 *       invariannya, DAN business_unit_id kiriman klien tetap dibuang.
 *   - EKSPOR benar-benar mengunduh berkas, dan yang Excel benar-benar .xlsx.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG SENGAJA TIDAK DIASERSI DI SINI
 * ────────────────────────────────────────────────────────────────────────
 *   - POSISI baris 'tidak tercatat' di dalam rekap — vakum pada fixture ini,
 *     lihat di atas.
 *   - Dua belas keadaan yang menuntut database tersusun per test: akun
 *     terikat mill dengan business_unit_id null, mill tanpa periode SWD,
 *     periode tanpa data, periode yang seluruh muatannya belum ditimbang,
 *     periode campuran 6-bertimbang/4-belum, satu kolom kategoris kosong pada
 *     seluruh muatan, nilai kategoris yang hanya berbeda besar-kecil huruf,
 *     kedua kolom teks kosong sepanjang periode, line dengan dua unit SWD,
 *     periode yang BELUM MULAI, periode berstatus closed, dan id
 *     mill/line/periode mill lain lewat properti komponen. Kedua belasnya
 *     dibuktikan di backend/tests/Feature/Livewire/LaporanSolidWasteDisposalTest.php,
 *     yang memang punya database sendiri per test.
 *   - Keadaan 'periode BELUM MULAI'. Periode prasyarat spec ini membentang
 *     2020-01-01 sampai hari-ini+7, jadi di browser ia selalu BERJALAN;
 *     periode masa depan tambahan akan bertumpang-tindih dan ditolak 422
 *     PERIOD_OVERLAP. Yang dapat diuji di sini — dan diuji — adalah keterangan
 *     'periode masih berjalan'.
 */

const REPORT_PATH = '/reports/solid-waste-disposal'
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
 * Di sini bahayanya BUKAN di masa depan: SolidWasteDisposalReportService::
 * guardAccess() MENERIMA Operator HARI INI, sejak commit pertama, karena
 * kembaran mobile screen-163 dibangun dalam seri yang sama dan merupakan
 * pemanggil yang sah. Kalau canAccess() mendelegasikan ke service, layar web
 * ini terbuka untuk Operator SEKARANG — tanpa satu galat pun.
 */
const OPERATOR = 'operator01'

/** KEEMPAT rekap kategoris, dengan testid tabelnya. */
const BREAKDOWNS = [
  'by-purpose-end-use',
  'by-waste-type',
  'by-source-station',
  'by-disposal-site',
]

/**
 * TESTID BLOK PER JAM / SHIFT / DOWNTIME yang WAJIB NOL di layar ini.
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
  'downtime',
  'downtime-table',
  'no-downtime-note',
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
async function openReportWithData(page: Page): Promise<void> {
  await page.goto(REPORT_PATH)
  await expect(page.locator('[data-testid="laporan-solid-waste-disposal"]')).toBeVisible()

  await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })

  await expect(page.locator('[data-testid="report-hero"]')).toBeVisible()
  // Kartu muatan dirender untuk SETIAP periode yang sah, berdata atau tidak.
  // Menunggunya membuktikan putaran Livewire mendarat, dan mencegah count()
  // yang menjawab 0 palsu karena wilayahnya belum ada.
  await expect(page.locator('[data-testid="loads-card"]')).toBeVisible()
}

/** Jumlah muatan periode, dibaca dari kartunya sendiri. */
async function loadCountOf(page: Page): Promise<number> {
  const raw = await page.locator('[data-testid="kpi-load-count"] .md-kpi__value').innerText()
  const value = parseIdNumber(raw)

  expect(value, `tidak bisa membaca jumlah muatan dari "${raw}"`).not.toBeNull()

  return value as number
}

/**
 * PRASYARAT PERIODE PELAPORAN.
 *
 * Spec ini sengaja tidak menanam data laporannya sendiri, tetapi PERIODE-nya
 * tetap harus ada: tanpa satu pun periode yang mencakup Solid Waste Disposal
 * di "BU Browser Test", pemilih periodenya kosong, `[data-testid="loads-card"]`
 * tidak pernah muncul, dan hampir seluruh skenario gagal sebagai "element(s)
 * not found" — seolah produknya yang rusak.
 *
 * DAN TANPA HOOK INI SAMA SEKALI, beforeAll MENGGANTUNG alih-alih gagal, jadi
 * kegagalannya tidak akan menyebut periode sama sekali.
 *
 * seedOpenPeriodForForms() idempoten dan MEMAKAI ULANG periode yang sudah ada
 * (findPeriodByPrefix), jadi memanggilnya tidak menambah periode kedua ketika
 * spec `form-solid-waste-disposal` memang jalan lebih dulu — ia hanya
 * menghapus ketergantungan pada urutan alfabet, dan membuat spec ini dapat
 * dijalankan SENDIRIAN setelah scripts/prepare-db.sh.
 *
 * RENTANGNYA MEMANG MEMUAT DATANYA: FIXTURE_START '2020-01-01' sampai
 * hari-ini+7, sementara kedua record fixture bertanggal 2025-03-10.
 *
 * KUNCI STASIUNNYA 'solid-waste-disposal' BERTANDA HUBUNG. Fixture
 * mencocokkannya dengan `station.station_type` dari API, yang berisi nilai
 * enum-nya; 'solid_waste_disposal' gagal di period-fixture.ts dengan "periode
 * prasyarat tidak punya baris stasiun".
 */
let periodFixturePage: Page | undefined

test.beforeAll(async ({ browser }) => {
  // 30 detik bawaan Playwright untuk sebuah hook TIDAK CUKUP, dan itu terukur:
  // `php artisan serve` melayani satu permintaan sekaligus, jadi login lewat
  // UI ditambah beberapa panggilan API periode melewatinya pada mesin sibuk.
  test.setTimeout(180_000)
  periodFixturePage = await seedOpenPeriodForForms(browser, 'solid-waste-disposal')
})

test.afterAll(async () => {
  // Dijaga: kalau beforeAll gagal sebelum mengembalikan page-nya, afterAll
  // tidak boleh menimpa kegagalan itu dengan TypeError miliknya sendiri.
  test.setTimeout(120_000)

  if (periodFixturePage !== undefined) {
    await removeOpenPeriodForForms(periodFixturePage)
  }
})

test.describe('Laporan Periode Solid Waste Disposal (screen-162)', () => {
  // Skenario 6: "Production Line belum dipilih"
  test('Supervisor: tanpa pemilih mill, dan NOL angka sebelum line dipilih', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(REPORT_PATH)

    await expect(page.locator('[data-testid="laporan-solid-waste-disposal"]')).toBeVisible()

    // Peran terikat mill tidak melihat pemilih mill — menawarkan pemilih yang
    // tidak bisa ia pakai adalah kebohongan kecil yang mahal.
    await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="mill-name"]')).toContainText(BUSINESS_UNIT)

    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()

    // Dan BENAR-BENAR tidak ada angka: bukan sekadar bagian yang
    // disembunyikan, dan BUKAN angka seluruh mill sebagai penggantinya.
    for (const testid of ['loads-card', 'compliance-card', 'daily-table', 'completeness-card', 'draft-card']) {
      await expect(page.locator(`[data-testid="${testid}"]`), testid).toHaveCount(0)
    }

    // KEDUA TOMBOL EKSPOR TIDAK ADA DI DOM — count() setelah arahan tampil,
    // bukan toBeHidden: yang diklaim adalah 'belum dirender', bukan
    // 'tersembunyi'.
    await expect(page.locator('[data-testid="export-button"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="export-excel-button"]')).toHaveCount(0)

    await expect(page.locator('[data-testid="select-production-line-hint"]'))
      .toContainText('tidak menampilkan angka seluruh mill')
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
    await expect(page.locator('[data-testid="loads-card"]')).toBeVisible()
    await expect(page.locator('[data-testid="hero-production-line"]')).toContainText(PRODUCTION_LINE)
    await expect(page.locator('[data-testid="production-line-current"]')).toBeVisible()
    await expect(page.locator('[data-testid="period-status-badge"]')).toBeVisible()
  })

  // Skenario 1 + invarian (c).
  test('skenario 1 — angka utama tampil, penyebut rata-rata ada DI DALAM kartunya, nol permintaan tulis', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)

    const headers = await statefulHeaders(page)

    // JUMLAH MUATAN MENURUT SERVER, dibaca lewat API — bukan dibaca dari DOM
    // yang sedang diuji. Dipakai di akhir untuk membuktikan membaca laporan
    // tidak menulis apa pun: kalau layar ini menyimpan sesuatu, angka yang
    // DISERVER yang berubah.
    const periods = await page.request.get('/api/solid-waste-disposal-reports/periods', { headers })

    expect(periods.status()).toBe(200)

    const periodId = ((await periods.json()).data ?? [])[0]?.id

    const lines = await page.request.get('/api/production-lines/options-for-report', { headers })

    expect(lines.status()).toBe(200)

    const lineId = ((await lines.json()).data ?? []).find(
      (line: { name: string }) => line.name === PRODUCTION_LINE,
    )?.id

    expect(periodId, 'mill fixture tidak punya satu pun periode Solid Waste Disposal').toBeTruthy()
    expect(lineId, `mill fixture tidak punya production line "${PRODUCTION_LINE}"`).toBeTruthy()

    const serverLoadCount = async (): Promise<number> => {
      const response = await page.request.get(
        `/api/solid-waste-disposal-reports/summary?period_id=${periodId}&production_line_id=${lineId}`,
        { headers },
      )

      expect(response.status()).toBe(200)

      return (await response.json()).loads.load_count as number
    }

    const before = await serverLoadCount()

    await openReportWithData(page)

    // Angka utama tampil. ≥ 1, bukan angka pasti: form-solid-waste-disposal
    // menulis ke database yang sama (lihat docblock berkas).
    const loadCount = await loadCountOf(page)

    expect(loadCount, 'periode fixture tidak memuat satu muatan pun').toBeGreaterThanOrEqual(1)

    await expect(page.locator('[data-testid="kartu-total-netto"]')).toBeVisible()
    await expect(page.locator('[data-testid="kartu-penimbangan-belum-selesai"]')).toBeVisible()

    // KOTAK `undated-note` SENGAJA TIDAK DIHARAPKAN DI SINI, dan ketiadaannya
    // adalah perilaku yang BENAR: blade merendernya hanya ketika
    // undated_load_count > 0, sementara `event_date` adalah kolom NOT NULL
    // (migrasi 2026_08_31_000003) sehingga angkanya selalu 0 di basis data
    // mana pun. Kedua cabangnya diuji di
    // backend/tests/Feature/Livewire/LaporanSolidWasteDisposalTest.php, yang
    // dapat memasang koleksi record di memori; di browser hanya cabang nol
    // yang dapat dicapai, jadi hanya itu yang diasersi.
    await expect(page.locator('[data-testid="undated-note"]')).toHaveCount(0)

    // INVARIAN (c) — PENYEBUT RATA-RATA DISASARKAN KE TESTID KARTUNYA, bukan
    // ke halaman: rata-rata dari 6 muatan tidak boleh terlihat sama
    // meyakinkan dengan rata-rata dari 600, dan angka sependek '6' cocok di
    // mana saja pada halaman ini.
    const kartuRata = page.locator('[data-testid="kartu-rata-netto"]')

    await expect(kartuRata).toBeVisible()
    await expect(kartuRata.locator('[data-testid="net-weight-load-count"]')).toBeVisible()

    const denominator = parseIdNumber(
      await kartuRata.locator('[data-testid="net-weight-load-count"]').innerText(),
    )

    expect(denominator, 'penyebut rata-rata tidak terbaca di dalam kartunya').not.toBeNull()
    // Penyebutnya tidak pernah melebihi jumlah muatan — identitas yang
    // berlaku apa pun isi databasenya.
    expect(denominator as number).toBeLessThanOrEqual(loadCount)

    // KEEMPAT rekap punya baris.
    for (const testid of BREAKDOWNS) {
      const table = page.locator(`[data-testid="${testid}-table"]`)

      await expect(table, testid).toBeVisible()
      expect(
        await table.locator('tbody tr').count(),
        `${testid} tidak punya satu baris pun`,
      ).toBeGreaterThanOrEqual(1)
      await expect(page.locator(`[data-testid="${testid}-empty"]`), testid).toHaveCount(0)
    }

    // DAN ANGKA DI LAYAR SEPAKAT DENGAN ANGKA DI API — halaman dan API tidak
    // pernah dapat menyebut angka yang berbeda, karena keduanya memakai
    // service yang sama.
    expect(loadCount, 'angka di layar berbeda dari angka /summary').toBe(before)

    // NOL PERMINTAAN TULIS: membuka laporan tidak menambah satu muatan pun.
    expect(await serverLoadCount(), 'membuka laporan mengubah data').toBe(before)
  })

  // Invarian (d) — skenario 11 / 26, dalam bentuk yang TIDAK vakum.
  test("invarian (d) — baris 'tidak tercatat' dirender pada KEEMPAT rekap", async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    for (const testid of BREAKDOWNS) {
      const nullRow = page.locator(`[data-testid="${testid}-row-null"]`)

      // Kedua baris detail fixture mengosongkan KEEMPAT kolom kategoris, jadi
      // baris 'tidak tercatat' WAJIB ada pada keempat rekap — dan rekapnya
      // TIDAK boleh menghilang: rekap yang hilang membuat pembaca mengira
      // bagian laporan itu tidak ada.
      expect(await nullRow.count(), `${testid} tidak merender baris 'tidak tercatat'`).toBe(1)
      await expect(nullRow).toContainText('tidak tercatat')

      // Dan kolom Muatan baris itu bernilai ≥ 1 — barisnya bukan sekadar
      // tempat kosong.
      const muatan = parseIdNumber(await nullRow.locator('td').nth(1).innerText())

      expect(muatan, `${testid}: kolom Muatan baris null tidak terbaca`).not.toBeNull()
      expect(muatan as number).toBeGreaterThanOrEqual(1)
    }

    // POSISI baris null SENGAJA TIDAK DIASERSI DI SINI. Pada fixture e2e
    // keempat rekap hanya punya satu baris, sehingga 'di atas' dan 'bukan
    // baris terakhir' keduanya terpenuhi tanpa menguji apa pun. Pengurutannya
    // dibuktikan di uji unit (case 23 dan 27), tempat fixture-nya dapat
    // disusun.
  })

  // Invarian (e) — skenario 13 / 25.
  test('invarian (e) — compliance adalah kartu terpisah: dua penghitung, satu paragraf, nol grafik', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const compliance = page.locator('[data-testid="compliance-card"]')

    await expect(compliance).toBeVisible()
    await expect(compliance.locator('[data-testid="kpi-missing-gate-pass"]')).toBeVisible()
    await expect(compliance.locator('[data-testid="kpi-missing-seal"]')).toBeVisible()
    await expect(compliance.locator('[data-testid="compliance-note"]')).toBeVisible()

    // KEDUA baris detail fixture mengosongkan gate_pass_no DAN
    // security_seal_no, jadi kedua penghitungnya ≥ 1 — dan tidak pernah
    // melebihi jumlah muatan.
    const loadCount = await loadCountOf(page)

    for (const testid of ['kpi-missing-gate-pass', 'kpi-missing-seal']) {
      const value = parseIdNumber(
        await compliance.locator(`[data-testid="${testid}"] .md-kpi__value`).innerText(),
      )

      expect(value, `${testid} tidak terbaca`).not.toBeNull()
      expect(value as number, `${testid} seharusnya menghitung muatan fixture yang kosong`).toBeGreaterThanOrEqual(1)
      expect(value as number).toBeLessThanOrEqual(loadCount)
    }

    // NOL GRAFIK dan NOL TABEL bernomor segel DI DALAM kartunya: sebuah nomor
    // segel tidak punya besaran, jadi ia tidak pernah dirata-rata, diurutkan,
    // maupun digrafikkan.
    await expect(compliance.locator('svg.md-lc__svg')).toHaveCount(0)
    await expect(compliance.locator('table')).toHaveCount(0)

    // KARTU YANG TERPISAH dari kartu muatan — diasersi atas hubungan DOM,
    // bukan atas 'keduanya ada di halaman'.
    await expect(page.locator('[data-testid="loads-card"] [data-testid="compliance-card"]')).toHaveCount(0)
    await expect(compliance.locator('[data-testid="kpi-load-count"]')).toHaveCount(0)
  })

  // Skenario 22.
  test('skenario 22 — rekap tujuan penggunaan akhir berada DI DALAM kotak angka total', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const kotak = page.locator('[data-testid="loads-card"]')

    // HUBUNGAN DOM, bukan 'keduanya ada di halaman': tidak ada satu angka
    // 'limbah keluar' pun yang boleh dirender dalam container yang tidak
    // memuat rinciannya.
    await expect(kotak.locator('[data-testid="kartu-total-netto"]')).toHaveCount(1)
    await expect(kotak.locator('[data-testid="by-purpose-end-use-table"]')).toHaveCount(1)

    // Dan rekap LAIN justru di luar kotak itu — tanpa baris ini, asersi di
    // atas juga hijau pada layar yang menaruh segalanya di satu kotak.
    await expect(kotak.locator('[data-testid="by-waste-type-table"]')).toHaveCount(0)
    await expect(kotak.locator('[data-testid="compliance-card"]')).toHaveCount(0)

    // DIBUKTIKAN GEOMETRIS: keduanya berada dalam satu layar yang sama,
    // bukan sekadar 'terlihat' setelah menggulir jauh.
    const totalBox = await kotak.locator('[data-testid="kartu-total-netto"]').boundingBox()
    const recapBox = await kotak.locator('[data-testid="by-purpose-end-use-table"]').boundingBox()
    const viewport = page.viewportSize()

    expect(totalBox).not.toBeNull()
    expect(recapBox).not.toBeNull()
    expect(viewport).not.toBeNull()

    expect(
      recapBox!.y - totalBox!.y,
      'rekap tujuan penggunaan akhir terlalu jauh dari kartu total untuk dibaca bersamaan',
    ).toBeLessThan((viewport!.height) * 2)
  })

  // Skenario 18 / 28 — fixture e2e berstatus draft_ongoing.
  test('skenario 18 — record draft_ongoing IKUT terhitung, dan kartu draft menyatakannya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const draftCard = page.locator('[data-testid="draft-card"]')

    await expect(draftCard).toBeVisible()

    const kpi = draftCard.locator('[data-testid="kpi-draft-load-count"]')
    const draftCount = parseIdNumber(await kpi.locator('.md-kpi__value').innerText())
    const loadCount = await loadCountOf(page)

    expect(draftCount, 'jumlah muatan draft tidak terbaca').not.toBeNull()
    // Kedua record fixture e2e berstatus draft_ongoing, jadi ≥ 1 — dan itu
    // membuktikan laporan ini TIDAK menyaring draft keluar.
    expect(draftCount as number, 'muatan draft tidak ikut terhitung').toBeGreaterThanOrEqual(1)
    expect(draftCount as number).toBeLessThanOrEqual(loadCount)

    // Label 'Draft' DISASARKAN KE KARTUNYA: kata itu juga muncul di pemilih
    // periode, jadi mencarinya di seluruh halaman tidak membuktikan apa pun.
    await expect(kpi).toContainText('Muatan Draft')
    await expect(draftCard.locator('[data-testid="draft-note"]')).toBeVisible()
  })

  // Invarian (g) — skenario 24. DUA MEKANISME BERBEDA, dengan sengaja.
  test('invarian (g) — nol blok per jam di DOM, dan paragraf sebabnya hadir', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    // (1) ATAS TESTID — bukan atas kata 'jam'.
    for (const testid of FORBIDDEN_TESTIDS) {
      await expect(page.locator(`[data-testid="${testid}"]`), testid).toHaveCount(0)
    }

    // Nol tabel/grafik ber-24 kolom jam.
    await expect(page.locator('[data-hour]')).toHaveCount(0)

    // (2) DAN paragraf _note_no_hourly hadir di dalam container catatannya.
    //     Mekanisme kedua yang sengaja berbeda dari yang pertama, supaya yang
    //     satu tidak memuaskan yang lain.
    const note = page.locator('[data-testid="no-hourly-note"]')

    await expect(note).toBeVisible()
    await expect(note).toContainText('Sebaran muatan per jam TIDAK DAPAT dibuat untuk stasiun ini')
    await expect(note).toContainText('Ini BUKAN fitur yang ditunda')

    await expect(page.locator('[data-testid="shift-not-published-note"]')).toBeVisible()
    await expect(page.locator('[data-testid="absences-note"]')).toBeVisible()
  })

  // Invarian (f).
  test('invarian (f) — nol ELEMEN berkelas penanda di luar batas pada halaman ter-render', async ({ page }) => {
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

    // Dan kotak yang MENJELASKAN ketiadaan itu memang ada, berkelas
    // .md-explain — memakai kelas bernama 'threshold' untuk menjelaskan bahwa
    // tidak ada ambang akan memerahkan baris di atas justru karena namanya.
    await expect(page.locator('.md-explain[data-testid="absences-note"]')).toHaveCount(1)
  })

  // Invarian (i) — skenario 19.
  test('invarian (i) — rekap harian dapat ditutup/dibuka, dan gulir mendatar ada DI DALAM kartunya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const details = page.locator('[data-testid="daily-recap-details"]')
    const toggle = page.locator('[data-testid="daily-recap-toggle"]')
    const table = page.locator('[data-testid="daily-table"]')

    await expect(details).toBeVisible()
    await expect(toggle).toBeVisible()
    // <details open> pada paint pertama: tabelnya terlihat sejak awal.
    await expect(table).toBeVisible()

    const loadCountBefore = await loadCountOf(page)
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
    expect(await loadCountOf(page)).toBe(loadCountBefore)

    const afterToggle = await pageOverflowOf(page)

    expect(afterToggle.scrollWidth).toBeLessThanOrEqual(afterToggle.clientWidth + 1)
  })

  // Periode prasyarat spec ini SELALU masih berjalan (2020-01-01 .. hari+7).
  test('kelengkapan — periode yang masih berjalan menyatakan penyebutnya berhenti di hari ini', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    const card = page.locator('[data-testid="completeness-card"]')

    await expect(card).toBeVisible()
    await expect(card.locator('[data-testid="days-with-load"]')).toBeVisible()
    await expect(card.locator('[data-testid="days-counted"]')).toBeVisible()

    // Persentase diasersi HANYA pada selnya, tidak pernah atas halaman:
    // keterangan kelengkapan memuat angka lain.
    const percent = await card.locator('[data-testid="days-with-load-percent"]').innerText()

    expect(percent.includes('%') || percent.includes('—'), `sel kelengkapan tak terbaca: ${percent}`).toBe(true)

    // Periode fixture membentang sampai hari-ini+7, jadi ia MASIH BERJALAN dan
    // layar menyatakan penyebutnya berhenti di hari ini — bukan di end_date
    // yang belum tiba.
    await expect(card.locator('[data-testid="period-running-note"]')).toBeVisible()
    await expect(card.locator('[data-testid="period-running-note"]')).toContainText('periode masih berjalan')
    await expect(card.locator('[data-testid="period-not-started-note"]')).toHaveCount(0)
  })

  // Invarian (h) — skenario 27. DUA MEKANISME BERBEDA, dengan sengaja.
  test('invarian (h) — kolom shift tidak ada di layar tetapi ADA di berkas ekspor', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    // (1) DI LAYAR: nol kolom/kartu/penyaring shift, dicari atas TESTID —
    //     BUKAN atas kata 'shift', yang justru dieja utuh oleh
    //     _note_shift_not_published.
    for (const testid of ['shift-select', 'shift-column', 'by-shift-table', 'kpi-shift']) {
      await expect(page.locator(`[data-testid="${testid}"]`), testid).toHaveCount(0)
    }

    for (const testid of BREAKDOWNS.concat(['daily'])) {
      const headers = await page.locator(`[data-testid="${testid}-table"] thead th`).allInnerTexts()

      expect(
        headers.some((header) => header.toLowerCase().includes('shift')),
        `${testid} memuat kolom shift`,
      ).toBe(false)
    }

    await expect(page.locator('[data-testid="shift-not-published-note"]'))
      .toContainText('Kolom shift pada baris detail bertipe TEKS BEBAS')

    // (2) DI BERKAS: header memuat kolom Shift, dan ekspor adalah rincian
    //     MENTAH. Mekanisme kedua — berkas, bukan DOM.
    const headers = await statefulHeaders(page)

    const periods = await page.request.get('/api/solid-waste-disposal-reports/periods', { headers })

    expect(periods.status()).toBe(200)

    const periodId = ((await periods.json()).data ?? [])[0]?.id

    const lines = await page.request.get('/api/production-lines/options-for-report', { headers })

    expect(lines.status()).toBe(200)

    const lineId = ((await lines.json()).data ?? []).find(
      (line: { name: string }) => line.name === PRODUCTION_LINE,
    )?.id

    expect(periodId, 'mill fixture tidak punya satu pun periode Solid Waste Disposal').toBeTruthy()
    expect(lineId, `mill fixture tidak punya production line "${PRODUCTION_LINE}"`).toBeTruthy()

    const exported = await page.request.get(
      `/api/solid-waste-disposal-reports/export?period_id=${periodId}&production_line_id=${lineId}&format=csv`,
      { headers },
    )

    expect(exported.status()).toBe(200)
    expect(exported.headers()['content-type']).toContain('text/csv')

    const body = await exported.text()
    const header = body.split('\n')[0]

    // KETIGA kolom yang tidak muncul di satu angka layar pun TETAP ada di
    // berkas — justru karena itu ketiganya yang paling mudah tertinggal.
    for (const column of [
      'Shift', 'Gate Pass No', 'Security Seal No', 'Operator ID',
      'Weighbridge Ticket No', 'Vehicle No', 'Driver Name',
    ]) {
      expect(header, `header ekspor kehilangan kolom ${column}`).toContain(column)
    }

    // Dan berkasnya memang punya baris data, bukan hanya header.
    expect(body.trim().split('\n').length).toBeGreaterThan(1)
  })

  // Skenario 17: ekspor.
  test('Ekspor CSV dan Ekspor Excel benar-benar mengunduh berkas', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    await expect(page.locator('[data-testid="export-button"]')).toBeVisible()
    await expect(page.locator('[data-testid="export-excel-button"]')).toBeVisible()

    const csvPromise = page.waitForEvent('download')
    await page.locator('[data-testid="export-button"]').click()
    const csv = await csvPromise

    expect(csv.suggestedFilename()).toContain('laporan-solid-waste-disposal')
    expect(csv.suggestedFilename()).toMatch(/\.csv$/)

    const excelPromise = page.waitForEvent('download')
    await page.locator('[data-testid="export-excel-button"]').click()
    const excel = await excelPromise

    expect(excel.suggestedFilename()).toContain('laporan-solid-waste-disposal')
    // .xlsx SUNGGUHAN lewat SheetWriter, bukan CSV bernama .xlsx. Komponen
    // menerjemahkan 'excel' dari toolbar bersama menjadi 'xlsx' sebelum
    // sampai ke service, yang daftar formatnya tertutup {csv, xlsx}.
    expect(excel.suggestedFilename()).toMatch(/\.xlsx$/)
  })

  test('layar hanya membaca: tidak ada kontrol tulis apa pun', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await openReportWithData(page)

    await expect(page.getByRole('button', { name: /^Simpan/ })).toHaveCount(0)
    await expect(page.getByRole('button', { name: /^Hapus/ })).toHaveCount(0)
    await expect(page.getByRole('button', { name: /^Ubah/ })).toHaveCount(0)
    await expect(page.getByRole('button', { name: /^Verifikasi/ })).toHaveCount(0)

    // DIBATASI PADA AKAR LAPORANNYA, bukan atas halaman: layout membawa satu
    // <form> logout, dan `page.locator('form')` akan selalu menghitungnya.
    const root = page.locator('[data-testid="laporan-solid-waste-disposal"]')

    await expect(root.locator('form')).toHaveCount(0)
    await expect(root.locator('input')).toHaveCount(0)
    await expect(root.locator('textarea')).toHaveCount(0)
    await expect(root.locator('button[type="submit"]')).toHaveCount(0)
  })

  // Skenario 3: Admin.
  test('skenario 3 — Admin: pemilih mill dirender dan angka muncul setelah mill + line dipilih', async ({ page }) => {
    await login(page, ADMIN, PASSWORD)
    await page.goto(REPORT_PATH)

    await expect(page.locator('[data-testid="mill-select"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-select-hint"]')).toBeVisible()
    // SEBELUM mill dipilih: nol kartu angka di DOM.
    await expect(page.locator('[data-testid="loads-card"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="compliance-card"]')).toHaveCount(0)

    await page.locator('[data-testid="mill-select"]').selectOption({ label: BUSINESS_UNIT })
    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })

    await expect(page.locator('[data-testid="loads-card"]')).toBeVisible()
    // Nama mill terpilih tampil di HEADER laporan — disasarkan ke elemennya.
    await expect(page.locator('[data-testid="hero-subtitle"]')).toContainText(BUSINESS_UNIT)
    await expect(page.locator('[data-testid="hero-production-line"]')).toContainText(PRODUCTION_LINE)
    await expect(page.locator('[data-testid="export-button"]')).toBeVisible()
  })

  // Skenario 4.
  test('skenario 4 — Admin mengganti mill: kartu angka HILANG dari DOM dan kedua pemilih kosong', async ({ page }) => {
    await login(page, ADMIN, PASSWORD)
    await page.goto(REPORT_PATH)

    await page.locator('[data-testid="mill-select"]').selectOption({ label: BUSINESS_UNIT })
    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })
    await expect(page.locator('[data-testid="loads-card"]')).toBeVisible()

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
    await expect(page.locator('[data-testid="select-production-line-hint"]')).toBeVisible()

    // KARTU ANGKA HILANG DARI DOM — count() setelah menunggu arahannya
    // tampil, bukan sekadar 'tersembunyi'.
    await expect(page.locator('[data-testid="loads-card"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="compliance-card"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="draft-card"]')).toHaveCount(0)
    // Dan TIDAK ADA pesan galat tentang periode yang tidak cocok.
    await expect(page.locator('[data-testid="forbidden-notice"]')).toHaveCount(0)
  })

  // Invarian (j) — skenario 2, dan TIGA jawaban berbeda.
  test('invarian (j) — Operator DITOLAK pada rute web, 200 pada ketiga rute API data, 403 pada pemilih mill', async ({ page }) => {
    await login(page, OPERATOR, PASSWORD)

    const response = await page.goto(REPORT_PATH)

    // LAYAR WEB TERTUTUP. Penjagaannya ada pada middleware rute DAN pada
    // canAccess() komponen, yang memegang daftar perannya SENDIRI justru
    // supaya penerimaan Operator di service — yang sudah berlaku HARI INI demi
    // kembaran mobile screen-163 — TIDAK dapat membuka layar web ini.
    expect(response?.status()).toBe(403)

    // URL-nya diperiksa dengan REGEX, bukan glob: `toHaveURL('**/x')` dipadu
    // baseURL TIDAK PERNAH cocok, dan kegagalannya terbaca seperti navigasi
    // yang tidak terjadi.
    await expect(page.locator('[data-testid="laporan-solid-waste-disposal"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="loads-card"]')).toHaveCount(0)
    await expect(page.locator('table')).toHaveCount(0)

    // Header stateful WAJIB ada. Tanpa X-Requested-With + Referer,
    // EnsureFrontendRequestsAreStateful tidak mengaktifkan session guard dan
    // Sanctum menjawab 401 SEBELUM guard peran dievaluasi — 401 itu akan lulus
    // sebagai "ditolak" padahal tidak membuktikan apa pun tentang peran.
    const headers = await statefulHeaders(page)

    // PEMILIH MILL TETAP 403 — diangkat DARI DALAM
    // SolidWasteDisposalReportService::businessUnitOptions(), bukan oleh
    // daftar peran middleware yang memang menerima empat peran demi ketiga
    // rute data.
    const options = await page.request.get(
      '/api/solid-waste-disposal-reports/business-units/options',
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
    const periods = await page.request.get('/api/solid-waste-disposal-reports/periods', { headers })

    expect(periods.status()).toBe(200)

    const periodId = ((await periods.json()).data ?? [])[0]?.id

    const lines = await page.request.get('/api/production-lines/options-for-report', { headers })

    expect(lines.status()).toBe(200)

    const lineId = ((await lines.json()).data ?? [])[0]?.id

    // Diasersi, bukan dilewati diam-diam: tanpa keduanya kedua asersi di bawah
    // menjadi 422 tentang parameter yang hilang, dan "tidak 403" yang lulus
    // karena alasan yang salah adalah asersi yang berhenti dibaca orang.
    expect(periodId, 'mill operator01 tidak punya satu pun periode Solid Waste Disposal').toBeTruthy()
    expect(lineId, 'mill operator01 tidak punya satu pun Production Line').toBeTruthy()

    const summary = await page.request.get(
      `/api/solid-waste-disposal-reports/summary?period_id=${periodId}&production_line_id=${lineId}`,
      { headers },
    )

    expect(summary.status()).toBe(200)

    const summaryBody = await summary.json()

    // NOL KUNCI PER JAM, NOL KUNCI SHIFT, NOL KUNCI DOWNTIME/AMBANG, dan NOL
    // EMBER 'dibuang/dimanfaatkan' — diasersi atas NAMA KUNCI. Payload ini
    // memuat tiga kalimat manusia (`_note_no_hourly`,
    // `_note_shift_not_published`, `_note_absences`) yang justru MEMBAHAS
    // ketiadaan itu, jadi memindai teks respons akan menjawab pertanyaan yang
    // salah.
    const keys = allKeysOf(summaryBody)

    for (const forbidden of [
      'hourly', 'busiest_hour', 'empty_hour_count', 'by_hour',
      'shift', 'shifts', 'by_shift', 'shift_distribution',
      'downtime', 'downtime_minutes', 'stoppages',
      'out_of_range', 'threshold', 'thresholds', 'target', 'targets',
      'disposed', 'utilized', 'dibuang', 'dimanfaatkan',
      'gate_pass_no', 'security_seal_no', 'avg_gate_pass', 'avg_seal',
    ]) {
      expect(keys, `payload tidak boleh punya kunci ${forbidden}`).not.toContain(forbidden)
    }

    // Dan KUNCI YANG MEMANG HARUS ADA — tanpa baris ini, daftar larangan di
    // atas akan hijau pada payload kosong.
    for (const required of [
      'loads', 'by_waste_type', 'by_source_station', 'by_disposal_site',
      'by_purpose_end_use', 'compliance', 'draft_load_count',
      'undated_load_count', 'daily', 'daily_total', 'completeness',
      '_note_no_hourly', '_note_shift_not_published', '_note_absences',
    ]) {
      expect(keys, `payload kehilangan kunci ${required}`).toContain(required)
    }

    // Penyebutnya diterbitkan BERSAMA rata-ratanya, di setiap tingkat.
    expect(Object.keys(summaryBody.loads ?? {})).toEqual(
      expect.arrayContaining([
        'load_count', 'net_weight_total', 'net_weight_avg',
        'net_weight_load_count', 'missing_net_weight_load_count',
      ]),
    )

    const exported = await page.request.get(
      `/api/solid-waste-disposal-reports/export?period_id=${periodId}&production_line_id=${lineId}&format=csv`,
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
      `/api/solid-waste-disposal-reports/summary?period_id=${periodId}&production_line_id=${lineId}`
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
   * 'solid-waste-disposal' pada StationReportService::REPORT_ROUTES,
   * DI-APPEND di akhir map sesudah storage-tank (sort_order 140 -> 150).
   * Tanpa test ini, layar laporannya bisa lengkap, seluruh test backend hijau,
   * dan tile-nya tetap kelabu — pola kegagalan yang sudah pernah terjadi pada
   * laporan Cages & Tracks versi web.
   *
   * RIAK YANG SUDAH IKUT DIPERBAIKI: konstanta UNAVAILABLE_STATION pada
   * tests/laporan-stasiun.spec.ts kini 'process-quality-control' (sort_order
   * 180) dan TIDAK boleh menunjuk solid-waste-disposal lagi — kalau tidak,
   * spec itu akan mengasersikan tile yang baru saja diaktifkan sebagai
   * nonaktif. Riak ini ditemukan dengan grep atas KODE STASIUNNYA di seluruh
   * tests/ dan e2e-web/, bukan dengan memeriksa urutan daftar.
   */
  test('invarian (a) — tile Solid Waste Disposal aktif dan menavigasi ke layar laporannya', async ({ page }) => {
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto(STATION_REPORT_PATH)

    // Grid tile screen-140 sendiri digate oleh Production Line — tile baru
    // digambar setelah satu line dipilih.
    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE })
    await expect(page.locator('[data-testid="station-grid"]')).toBeVisible()

    const tile = page.locator('[data-testid="station-tile-solid-waste-disposal"]')

    await expect(tile).toBeVisible()
    // AKTIF, bukan kelabu: kelas .active dan href yang benar-benar ada.
    await expect(tile).toHaveClass(/active/)
    await expect(tile).toHaveAttribute('href', /\/reports\/solid-waste-disposal/)

    await tile.click()

    // DIBANDINGKAN SEBAGAI PATHNAME, bukan lewat toHaveURL('**/x'): pola glob
    // itu dipadu dengan baseURL TIDAK PERNAH cocok, dan kegagalannya terbaca
    // seperti navigasi yang tidak terjadi. Tautannya membawa business_unit_id
    // dan production_line_id, jadi query-nya diabaikan di sini.
    await page.waitForURL((url) => url.pathname === REPORT_PATH)
    expect(new URL(page.url()).pathname).toMatch(/\/reports\/solid-waste-disposal$/)

    await expect(page.locator('[data-testid="laporan-solid-waste-disposal"]')).toBeVisible()

    // Mill dan line ikut terbawa, jadi layar tujuan TIDAK meminta memilih lagi
    // — itu sebabnya kedua properti layar ini membawa
    // #[Url(as: 'business_unit_id')] dan #[Url(as: 'production_line_id')]
    // dengan `as:` yang eksplisit: tanpa `as:`, kuncinya menjadi
    // 'businessUnitId'/'productionLineId' dan tautan dari screen-140 tidak
    // pernah terbaca, tanpa satu tanda pun bahwa ada yang salah.
    await expect(page.locator('[data-testid="select-production-line-hint"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="loads-card"]')).toBeVisible()
    await expect(page.locator('[data-testid="hero-production-line"]')).toContainText(PRODUCTION_LINE)
  })
})
