import { test, expect, type Page } from '@playwright/test'
import { login, USERS } from './helpers'

/**
 * reporting-pilih-stasiun.spec.ts — screen-141--reporting-pilih-stasiun-mobile
 * / usecase-143--reporting-pilih-stasiun-mobile "Pilih Stasiun untuk Laporan
 * (Mobile)".
 *
 * Satu test per test_scenarios yang punya browser_test — seluruh 11 (dua di
 * antaranya dipecah menjadi dua test karena skenarionya memang menyebut dua
 * peran / dua segmen breadcrumb yang berbeda).
 *
 * Berjalan di Vite dev server (playwright.config.ts, baseURL
 * http://localhost:5174): aplikasi Capacitor adalah SPA biasa sebelum
 * dibungkus native, jadi browser test layar mobile TIDAK ditangguhkan
 * sebagai "mobile-only" (lihat header playwright.config.ts).
 *
 * ────────────────────────────────────────────────────────────────────────
 * DUA BATAS LINGKUNGAN YANG MENENTUKAN BENTUK BERKAS INI
 * ────────────────────────────────────────────────────────────────────────
 *
 * 1. DAFTAR STASIUN LOKAL BARU ADA SETELAH LAYAR DAFTAR STASIUN DIBUKA.
 *    Penyemaian tabel `station` lokal terjadi di StationListView.vue
 *    (loadProductionLinesAndStations → seedDefaultStationsIfNeeded /
 *    productionLineRepo.fetchAndCacheStationsForProductionLine), BUKAN saat
 *    login. Itu bukan kekurangan test — itu justru aturan bisnis layar ini
 *    ("bila pengguna belum pernah membuka Daftar Stasiun, layar ini tidak
 *    punya stasiun untuk ditampilkan"), dan skenario "Belum ada stasiun
 *    tersimpan" di bawah memanfaatkannya apa adanya tanpa satu pun
 *    manipulasi database: cukup JANGAN buka Daftar Stasiun.
 *    Karena itu setiap test yang butuh tile memanggil `seedLocalStations()`
 *    lebih dulu.
 *
 * 2. NAVIGASI DILAKUKAN DENGAN KLIK, BUKAN page.goto(). Tabel SQLite lokal
 *    di web dipegang <jeep-sqlite> dan tidak pernah di-saveToStore(), jadi
 *    isinya hilang pada setiap muat ulang halaman. page.goto() = muat ulang
 *    = daftar stasiun kosong lagi. Navigasi sisi klien (klik kartu /
 *    breadcrumb) mempertahankannya — dan sekaligus menguji jalur masuk yang
 *    sebenarnya dipakai pengguna. page.goto() hanya dipakai pada skenario
 *    "Belum masuk", yang memang harus mengunjungi alamatnya langsung.
 *
 * Selector mengikuti markup ReportingPilihStasiunView.vue apa adanya:
 * data-testid="station-grid" / "station-tile-<type>" / "info-message" /
 * "no-stations" / "breadcrumb-home" / "breadcrumb-dashboard-reporting".
 * Perhatikan bahwa tile di layar INI ber-testid per JENIS stasiun,
 * sementara tile di layar Daftar Stasiun (StationGrid.vue) ber-testid per
 * ID stasiun — keduanya berbagi awalan `station-tile-`.
 */

const BACKEND_ORIGIN = 'http://localhost:8000'

/**
 * Mengisi tabel `station` lokal dengan membuka layar Daftar Stasiun —
 * satu-satunya jalur yang memang mengisinya di aplikasi ini (lihat batas
 * lingkungan 1 di header). Bila business unit punya lebih dari satu
 * Production Line, pemilihnya muncul lebih dulu; opsi pertama dipilih,
 * dan pilihan itu diingat di localStorage dengan kunci yang sama persis
 * yang dibaca ReportingPilihStasiunView.vue, sehingga kedua layar membaca
 * daftar yang sama. Berakhir kembali di /home.
 */
async function seedLocalStations(page: Page): Promise<void> {
  await page.getByTestId('menu-card-production-process-activity').click()
  await page.waitForURL('**/stations')

  const picker = page.getByTestId('production-line-picker')
  const firstTile = page.locator('[data-testid^="station-tile-"]').first()

  await expect(picker.or(firstTile).first()).toBeVisible({ timeout: 15_000 })

  if (await picker.isVisible()) {
    await page.locator('[data-testid^="production-line-option-"]').first().click()
  }

  await expect(firstTile).toBeVisible({ timeout: 15_000 })

  await page.getByTestId('breadcrumb-home').click()
  await page.waitForURL('**/home')
}

/** Home → Dashboard & Reporting → Reporting, seluruhnya lewat klik. */
async function goToReports(page: Page): Promise<void> {
  await page.getByTestId('menu-card-dashboard-reporting').click()
  await page.waitForURL('**/dashboard-reporting')

  await page.getByTestId('menu-card-reporting').click()
  await page.waitForURL('**/reports')
  await expect(page.getByTestId('station-grid')).toBeVisible()
}

test.describe('Reporting — Pilih Stasiun Mobile (screen-141)', () => {
  // Scenario: "Pilih Stasiun untuk Laporan (Mobile) — success"
  test('Pilih Stasiun untuk Laporan — success', async ({ page }) => {
    await login(page)
    await seedLocalStations(page)

    // NOL permintaan ke backend sepanjang perpindahan ke /reports dan
    // perenderan gridnya — inti klaim layar ini. Perekaman sengaja
    // dihentikan sebelum menekan tile Sterilizer: layar laporan TUJUAN
    // memang memanggil API, dan itu tanggung jawab screen-135.
    const backendRequests: string[] = []
    const recordRequest = (request: { url: () => string }) => {
      if (request.url().startsWith(BACKEND_ORIGIN)) {
        backendRequests.push(request.url())
      }
    }
    page.on('request', recordRequest)

    await page.getByTestId('menu-card-dashboard-reporting').click()
    await page.waitForURL('**/dashboard-reporting')

    // Kartu 'Dashboard' di layar itu TETAP nonaktif — yang dihidupkan
    // screen-141 hanyalah kartu 'Reporting'.
    await expect(page.getByTestId('menu-card-dashboard')).toHaveAttribute('aria-disabled', 'true')

    await page.getByTestId('menu-card-reporting').click()
    await page.waitForURL('**/reports')

    // Tidak ada pesan "belum tersedia" yang sempat muncul di layar
    // Dashboard & Reporting — kartunya bernavigasi, bukan menolak.
    await expect(page.getByTestId('info-message')).toHaveCount(0)

    const grid = page.getByTestId('station-grid')
    await expect(grid).toBeVisible()
    await expect(page.locator('[data-testid^="station-tile-"]').first()).toBeVisible()

    const breadcrumb = page.locator('.breadcrumb')
    await expect(breadcrumb).toContainText('Home')
    await expect(breadcrumb).toContainText('Dashboard & Reporting')
    await expect(breadcrumb).toContainText('Reporting')
    await expect(page.getByTestId('breadcrumb-home')).toBeVisible()
    await expect(page.getByTestId('breadcrumb-dashboard-reporting')).toBeVisible()

    page.off('request', recordRequest)
    expect(backendRequests).toEqual([])

    await page.getByTestId('station-tile-sterilizer').click()
    await page.waitForURL('**/reports/sterilizer**') // sufiks ** — alamatnya kini dapat membawa ?production_line_id=
  })

  // Scenario: "Pilih Stasiun untuk Laporan (Mobile) — Menekan stasiun yang
  // belum tersedia"
  test('Menekan stasiun yang belum tersedia — menampilkan pesan, tidak berpindah rute', async ({ page }) => {
    await login(page)
    await seedLocalStations(page)
    await goToReports(page)

    const unavailable = page.locator('[data-testid^="station-tile-"][aria-disabled="true"]').first()
    await expect(unavailable).toBeVisible()
    await expect(unavailable).toContainText('Belum tersedia')
    const stationName = (await unavailable.locator('.station-tile-name').innerText()).trim()

    // force: true diperlukan karena tile memakai aria-disabled="true", bukan
    // atribut disabled native — Playwright memperlakukan aria-disabled
    // sebagai "not enabled" dan menolak mengklik. Di perangkat sungguhan
    // tile ini tetap dapat ditap, dan justru itulah syarat spec: lihat
    // StationGrid.vue, disabled native menelan ketukan sehingga pesan tak
    // pernah muncul.
    await unavailable.click({ force: true })

    await expect(page.getByTestId('info-message')).toContainText(`Laporan ${stationName} belum tersedia.`)
    await expect(page).toHaveURL(/\/reports$/)
    await expect(unavailable).toBeVisible()
    await expect(unavailable).toContainText('Belum tersedia')
  })

  // Scenario: "Pilih Stasiun untuk Laporan (Mobile) — Menekan berkali-kali"
  test('Menekan tile yang sama berkali-kali — pesan tidak menumpuk', async ({ page }) => {
    await login(page)
    await seedLocalStations(page)
    await goToReports(page)

    const unavailable = page.locator('[data-testid^="station-tile-"][aria-disabled="true"]').first()
    const stationName = (await unavailable.locator('.station-tile-name').innerText()).trim()

    await unavailable.click({ force: true })
    await unavailable.click({ force: true })
    await unavailable.click({ force: true })

    await expect(page.getByTestId('info-message')).toHaveCount(1)
    await expect(page.getByTestId('info-message')).toContainText(`Laporan ${stationName} belum tersedia.`)
    await expect(page).toHaveURL(/\/reports$/)
  })

  // Scenario: "Pilih Stasiun untuk Laporan (Mobile) — Belum ada stasiun
  // tersimpan". Tanpa satu pun manipulasi database: layar Daftar Stasiun
  // sengaja TIDAK dibuka, sehingga tabel `station` lokal memang masih
  // kosong — persis keadaan pengguna yang baru masuk.
  test('Belum ada stasiun tersimpan — menampilkan arahan membuka Daftar Stasiun', async ({ page }) => {
    await login(page)

    await page.getByTestId('menu-card-dashboard-reporting').click()
    await page.waitForURL('**/dashboard-reporting')
    await page.getByTestId('menu-card-reporting').click()
    await page.waitForURL('**/reports')

    await expect(page.getByTestId('no-stations')).toBeVisible()
    await expect(page.getByTestId('no-stations')).toContainText('Daftar Stasiun')
    await expect(page.locator('[data-testid^="station-tile-"]')).toHaveCount(0)
    await expect(page.getByText(/gagal|error|kesalahan|sqlite/i)).toHaveCount(0)
  })

  // Scenario: "Pilih Stasiun untuk Laporan (Mobile) — Perangkat offline"
  test('Perangkat offline — grid tetap tampil penuh tanpa pesan kesalahan jaringan', async ({ page, context }) => {
    await login(page)
    await seedLocalStations(page)

    // BATAS LINGKUNGAN — sama persis dengan dashboard-reporting.spec.ts.
    // Skenario aslinya adalah "pengguna MEMBUKA layar ini dalam keadaan
    // offline". Itu tidak dapat direproduksi di bawah Vite dev server:
    // seluruh rute memakai lazy import dan dev server menyajikan modul
    // tanpa cache, sehingga membuka rute selalu menuntut pengambilan chunk
    // lewat jaringan. Memutus jaringan lebih dulu hanya menguji pengiriman
    // chunk oleh Vite, bukan perilaku layar ini. Di produk nyata kebutuhan
    // itu tidak ada: Capacitor memuat seluruh bundle dari perangkat.
    // Jangan "memperbaiki" ini dengan membuat rutenya eager-import.
    await goToReports(page)

    await context.setOffline(true)

    await expect(page.getByTestId('station-grid')).toBeVisible()
    await expect(page.locator('[data-testid^="station-tile-"]').first()).toBeVisible()
    await expect(page.getByText(/gagal|error|kesalahan jaringan/i)).toHaveCount(0)

    // Interaksinya pun tidak butuh jaringan.
    const unavailable = page.locator('[data-testid^="station-tile-"][aria-disabled="true"]').first()
    await unavailable.click({ force: true })
    await expect(page.getByTestId('info-message')).toContainText('belum tersedia.')
    await expect(page).toHaveURL(/\/reports$/)

    await context.setOffline(false)
  })

  // Scenario: "Pilih Stasiun untuk Laporan (Mobile) — Stasiun aktif di mill
  // namun laporannya belum ada". Seluruh stasiun yang disemai Daftar
  // Stasiun aktif di mill; hanya Sterilizer yang layar laporannya sudah
  // dibangun — jadi setiap tile nonaktif di sini ADALAH kasus itu.
  test('Stasiun aktif di mill namun laporannya belum dibangun — tile tetap tampil dalam keadaan nonaktif', async ({ page }) => {
    await login(page)
    await seedLocalStations(page)
    await goToReports(page)

    const unavailable = page.locator('[data-testid^="station-tile-"][aria-disabled="true"]').first()
    await expect(unavailable).toBeVisible()
    await expect(unavailable).toHaveAttribute('aria-disabled', 'true')
    await expect(unavailable).toContainText('Belum tersedia')

    await unavailable.click({ force: true })

    await expect(page.getByTestId('info-message')).toBeVisible()
    await expect(page).toHaveURL(/\/reports$/)
  })

  // Scenario: "Pilih Stasiun untuk Laporan (Mobile) — Belum masuk"
  test('Belum masuk — mengunjungi langsung /reports mengalihkan ke Login', async ({ page }) => {
    // Konteks segar (default per test, tanpa login()) — tidak ada sesi di
    // local storage, persis jalur pengguna tanpa sesi pada penjagaan rute.
    await page.goto('/reports')

    // Glob diberi sufiks ** agar cocok dengan /login?redirect=/reports —
    // penjagaan rute memang menyimpan tujuan asal.
    await page.waitForURL('**/login**')
    await expect(page.getByTestId('station-grid')).toHaveCount(0)
    await expect(page.locator('[data-testid^="station-tile-"]')).toHaveCount(0)
  })

  // Scenario: "Pilih Stasiun untuk Laporan (Mobile) — Kembali" (segmen
  // 'Dashboard & Reporting')
  test('Breadcrumb — menekan "Dashboard & Reporting" kembali ke layar Dashboard & Reporting', async ({ page }) => {
    await login(page)
    await seedLocalStations(page)
    await goToReports(page)

    await page.getByTestId('breadcrumb-dashboard-reporting').click()

    await page.waitForURL('**/dashboard-reporting')
    await expect(page.getByTestId('menu-card-reporting')).toBeVisible()
  })

  // Scenario: "Pilih Stasiun untuk Laporan (Mobile) — Kembali" (segmen
  // 'Home')
  test('Breadcrumb — menekan "Home" kembali ke layar Home', async ({ page }) => {
    await login(page)
    await seedLocalStations(page)
    await goToReports(page)

    await page.getByTestId('breadcrumb-home').click()

    await page.waitForURL('**/home')
    await expect(page.getByTestId('menu-card-dashboard-reporting')).toBeVisible()
  })

  // Scenario: "Pilih Stasiun untuk Laporan (Mobile) — layar tetap terbuka
  // untuk semua peran mobile" (Operator Stasiun)
  test('Layar terbuka untuk Station Operator', async ({ page }) => {
    await login(page, USERS.operator)
    await seedLocalStations(page)
    await goToReports(page)

    await expect(page).toHaveURL(/\/reports$/)
    await expect(page.getByTestId('station-tile-sterilizer')).toBeVisible()
    await expect(page.locator('[data-testid^="station-tile-"][aria-disabled="true"]').first()).toContainText(
      'Belum tersedia',
    )
    await expect(page.getByText(/akses ditolak|tidak diizinkan/i)).toHaveCount(0)
  })

  // Scenario: "Pilih Stasiun untuk Laporan (Mobile) — layar tetap terbuka
  // untuk semua peran mobile" (Supervisor)
  test('Layar terbuka untuk Supervisor', async ({ page }) => {
    await login(page, USERS.supervisor)
    await seedLocalStations(page)
    await goToReports(page)

    await expect(page).toHaveURL(/\/reports$/)
    await expect(page.getByTestId('station-tile-sterilizer')).toBeVisible()
    await expect(page.locator('[data-testid^="station-tile-"][aria-disabled="true"]').first()).toContainText(
      'Belum tersedia',
    )
    await expect(page.getByText(/akses ditolak|tidak diizinkan/i)).toHaveCount(0)
  })

  // Scenario: "Pilih Stasiun untuk Laporan (Mobile) — stasiun yang belum
  // tersedia tidak boleh disembunyikan". Jumlah tile di /reports
  // dibandingkan langsung dengan jumlah tile di layar Daftar Stasiun —
  // sumber daftarnya memang satu dan sama.
  test('Jumlah tile sama dengan layar Daftar Stasiun — yang belum tersedia tidak disembunyikan', async ({ page }) => {
    await login(page)

    await page.getByTestId('menu-card-production-process-activity').click()
    await page.waitForURL('**/stations')

    const picker = page.getByTestId('production-line-picker')
    const firstStationTile = page.locator('[data-testid^="station-tile-"]').first()
    await expect(picker.or(firstStationTile).first()).toBeVisible({ timeout: 15_000 })
    if (await picker.isVisible()) {
      await page.locator('[data-testid^="production-line-option-"]').first().click()
    }
    await expect(firstStationTile).toBeVisible({ timeout: 15_000 })

    const stationListCount = await page.locator('[data-testid^="station-tile-"]').count()
    expect(stationListCount).toBeGreaterThan(1)

    await page.getByTestId('breadcrumb-home').click()
    await page.waitForURL('**/home')
    await goToReports(page)

    await expect(page.locator('[data-testid^="station-tile-"]')).toHaveCount(stationListCount)

    // DIASERSI SEBAGAI INVARIAN, BUKAN SEBAGAI ANGKA. Empat laporan stasiun
    // lagi direncanakan pada fase ini; sebuah hitungan keras
    // (stationListCount - n) akan memerah empat kali lagi tanpa
    // mengajarkan apa pun — yang berubah cuma berapa banyak tile yang sudah
    // punya laporan, bukan aturannya. Yang benar-benar harus dijaga adalah:
    // setiap tile persis salah satu dari dua keadaan, grid tidak mati
    // seluruhnya, dan cabang "belum tersedia" tetap teruji. Bentuk ini
    // mengikuti e2e-web/tests/laporan-stasiun.spec.ts ("stasiun tanpa
    // laporan tampil nonaktif; yang punya laporan dapat diklik").
    const enabledCount = await page.locator('[data-testid^="station-tile-"][aria-disabled="false"]').count()
    const disabledCount = await page.locator('[data-testid^="station-tile-"][aria-disabled="true"]').count()

    // Setiap tile persis salah satu dari dua keadaan — tidak ada yang
    // keduanya, tidak ada yang bukan keduanya.
    expect(enabledCount + disabledCount).toBe(stationListCount)
    // Grid tidak mati seluruhnya: setidaknya satu laporan dapat dibuka.
    expect(enabledCount).toBeGreaterThan(0)
    // Dan tidak semuanya aktif, kalau tidak cabang "belum tersedia" di atas
    // tidak teruji di sini.
    expect(disabledCount).toBeGreaterThan(0)

    // Dua tile yang dipaku BY NAME, supaya invarian di atas tidak pernah
    // terpenuhi secara hampa oleh grid yang kosong atau salah label.
    await expect(page.getByTestId('station-tile-sterilizer')).toHaveAttribute('aria-disabled', 'false')
    await expect(page.getByTestId('station-tile-cages-track')).toHaveAttribute('aria-disabled', 'false')
  })

  // Scenario: "Pilih Stasiun untuk Laporan (Mobile) — layar tidak meminta
  // pengguna memilih mill"
  test('Layar tidak meminta memilih mill — grid langsung tampil dan tile langsung dapat ditekan', async ({ page }) => {
    await login(page)
    await seedLocalStations(page)
    await goToReports(page)

    await expect(page.getByTestId('station-grid')).toBeVisible()
    await expect(page.locator('dialog')).toHaveCount(0)
    await expect(page.locator('[role="dialog"]')).toHaveCount(0)
    await expect(page.locator('main.reporting-stations-view select')).toHaveCount(0)
    await expect(page.getByText(/pilih mill|pilih business unit/i)).toHaveCount(0)

    await page.getByTestId('station-tile-sterilizer').click()
    await page.waitForURL('**/reports/sterilizer**') // sufiks ** — alamatnya kini dapat membawa ?production_line_id=
  })
})

/* ================================================================== */
/* Membawa Production Line ke laporan tujuan (2026-09-28)              */
/* ================================================================== */

/**
 * RANTAI PENUH, BUKAN SEKADAR MEMERIKSA URL.
 *
 * Kelima layar laporan mobile kini menolak menampilkan angka sebelum ada
 * satu Production Line yang berlaku. Layar ini adalah pintu masuknya, jadi
 * yang harus dibuktikan bukan "query-nya ada", melainkan: menekan tile di
 * sini MENDARATKAN laporan yang SUDAH TERISI line-nya — tanpa pertanyaan
 * kedua kepada pengguna.
 *
 * Endpoint laporan di-stub (screen-135 sendiri sudah diuji terhadap data
 * sungguhan di e2e-web), tetapi GET /api/production-lines/current SENGAJA
 * TIDAK di-stub: line yang dibawa layar ini berasal dari pilihan nyata
 * pengguna di layar Daftar Stasiun, dan laporan tujuan memvalidasinya
 * terhadap daftar nyata dari backend. Kalau keduanya sampai memakai kunci
 * atau sumber yang berbeda, test ini yang menangkapnya.
 */
const REPORT_API_GLOB = '**/api/sterilizer-reports/**'

const REPORT_CORS_HEADERS = {
  'access-control-allow-origin': '*',
  'access-control-allow-headers': '*',
  'access-control-allow-methods': 'GET,POST,OPTIONS',
}

const STUB_PERIOD = {
  id: 'per-1',
  name: 'Periode Agustus 2026',
  start_date: '2026-08-01',
  end_date: '2026-08-31',
  status: 'open',
  station_type: 'sterilizer',
  station_type_label: 'Sterilizer',
}

async function stubSterilizerReport(page: Page): Promise<{ urls: string[] }> {
  const state = { urls: [] as string[] }

  await page.route(REPORT_API_GLOB, async (route) => {
    const request = route.request()

    if (request.method() === 'OPTIONS') {
      await route.fulfill({ status: 204, headers: REPORT_CORS_HEADERS, body: '' })

      return
    }

    const url = request.url()
    state.urls.push(url)

    if (url.includes('/periods')) {
      await route.fulfill({ status: 200, headers: REPORT_CORS_HEADERS, json: { data: [STUB_PERIOD] } })

      return
    }

    if (url.includes('/summary')) {
      const lineId = new URL(url).searchParams.get('production_line_id') ?? ''

      await route.fulfill({
        status: 200,
        headers: REPORT_CORS_HEADERS,
        json: {
          period: { ...STUB_PERIOD, business_unit_name: 'Business Unit A' },
          production_line: { id: lineId, name: 'Line terpilih' },
          kpi: {
            total_cycles: 6421,
            total_cages: 15678,
            avg_duration_minutes: 95.5,
            min_duration_minutes: 70,
            max_duration_minutes: 130,
            cycles_without_duration: 4,
            triple_peak_compliance_percent: 87.5,
          },
          daily: [],
          by_unit: [],
          outliers: {
            method: 'iqr',
            q1: null,
            q3: null,
            iqr: null,
            lower_bound: null,
            upper_bound: null,
            sample_size: 0,
            min_sample_size: 8,
            insufficient_data: true,
            items: [],
          },
          total: {
            cycles: 999,
            cages: 8888,
            avg_duration: 95.5,
            min_duration: 70,
            max_duration: 130,
            triple_peak_complete: 777,
            cycles_without_duration: 4,
          },
        },
      })

      return
    }

    await route.continue()
  })

  return state
}

/** Line yang diingat layar Daftar Stasiun untuk pengguna yang sedang masuk. */
async function rememberedLineId(page: Page): Promise<string> {
  return page.evaluate(() => {
    const raw = localStorage.getItem('msl_auth_user')
    const userId = raw ? (JSON.parse(raw).id ?? '') : ''

    return localStorage.getItem(`msl_production_line_${userId}`) ?? ''
  })
}

test.describe('Reporting — Pilih Stasiun Mobile (screen-141): membawa Production Line', () => {
  test.use({ viewport: { width: 390, height: 844 } })

  test('menekan tile stasiun mendaratkan laporan yang SUDAH terisi Production Line-nya', async ({ page }) => {
    await login(page)
    // seedLocalStations() memilih line pertama bila mill punya lebih dari
    // satu — pilihan itulah yang diingat, dan itulah yang harus terbawa.
    await seedLocalStations(page)

    const lineId = await rememberedLineId(page)
    expect(lineId, 'layar Daftar Stasiun tidak mengingat satu line pun').not.toBe('')

    const report = await stubSterilizerReport(page)

    await goToReports(page)
    await page.getByTestId('station-tile-sterilizer').click()
    await page.waitForURL('**/reports/sterilizer**')

    // 1. Line ikut terbawa di alamatnya.
    expect(page.url()).toContain(`production_line_id=${lineId}`)

    // 2. Dan benar-benar BERLAKU di layar tujuan: tidak ada pertanyaan
    //    kedua, dan nama line yang berlaku terbaca.
    await expect(page.getByTestId('laporan-sterilizer-mobile')).toBeVisible()
    await expect(page.getByTestId('production-line-required-hint')).toHaveCount(0)
    await expect(page.getByTestId('production-line-unavailable')).toHaveCount(0)
    await expect(page.getByTestId('production-line-current')).toBeVisible()

    // 3. Memilih periode langsung memunculkan angka — tanpa satu langkah
    //    tambahan pun, dan permintaannya membawa line yang sama.
    await page.getByTestId('period-select').selectOption('per-1')
    await expect(page.getByTestId('kpi-total-cycles')).toHaveText('6.421')

    expect(
      report.urls.some((url) => url.includes('/summary') && url.includes(`production_line_id=${lineId}`)),
    ).toBe(true)
    // Daftar periode TIDAK tersaring line — periode milik mill.
    expect(report.urls.filter((url) => url.includes('/periods')).every((url) => !url.includes('production_line_id'))).toBe(true)
  })

  test('tanpa ingatan line, tile tetap berpindah — laporan tujuan yang bertanya', async ({ page }) => {
    await login(page)
    await seedLocalStations(page)

    // Ingatan dihapus: keadaan pengguna yang belum pernah memilih line.
    await page.evaluate(() => {
      const raw = localStorage.getItem('msl_auth_user')
      const userId = raw ? (JSON.parse(raw).id ?? '') : ''
      localStorage.removeItem(`msl_production_line_${userId}`)
    })

    await stubSterilizerReport(page)

    await goToReports(page)
    await page.getByTestId('station-tile-sterilizer').click()
    await page.waitForURL('**/reports/sterilizer**')

    expect(page.url()).not.toContain('production_line_id')
    await expect(page.getByTestId('laporan-sterilizer-mobile')).toBeVisible()
  })
})
