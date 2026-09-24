import { test, expect } from '@playwright/test'
import { login, USERS } from './helpers'

/**
 * dashboard-reporting.spec.ts — screen-134--dashboard-reporting-mobile /
 * usecase-134--dashboard-reporting-mobile "Buka Dashboard & Reporting
 * (Mobile)".
 *
 * Covers all 8 test_scenarios' browser_test entries from the tech spec.
 * Runs against the real Vite dev server (playwright.config.ts, baseURL
 * http://localhost:5174) — a Capacitor app is a regular SPA before native
 * build, fully reachable/testable via a browser exactly like a web
 * screen (see playwright.config.ts's header comment); browser tests for
 * this mobile screen are NOT deferred as "mobile-only".
 *
 * 2026-09-23 (screen-141) — kartu 'Reporting' kini HIDUP: menekannya
 * membawa ke /reports (ReportingPilihStasiunView.vue) alih-alih memunculkan
 * pesan "belum tersedia". Kartu 'Dashboard' TETAP nonaktif dan seluruh
 * asersinya di berkas ini dipertahankan utuh — yang disesuaikan hanya
 * bagian kartu 'Reporting'. Jumlah test tidak berubah (tetap 9).
 *
 * Selectors mirror DashboardReportingView.vue's actual markup —
 * data-testid="menu-card-dashboard" / "menu-card-reporting" /
 * "info-message" / "breadcrumb-home", same convention as
 * home.spec.ts / station-list.spec.ts.
 */
test.describe('Dashboard & Reporting Mobile (screen-134)', () => {
  // Scenario: "Buka Dashboard & Reporting (Mobile) — success"
  test('Buka Dashboard & Reporting — success', async ({ page }) => {
    await login(page)

    await page.getByTestId('menu-card-dashboard-reporting').click()
    await page.waitForURL('**/dashboard-reporting')

    await expect(page.getByTestId('breadcrumb-home')).toBeVisible()
    await expect(page.getByTestId('menu-card-dashboard')).toBeVisible()
    await expect(page.getByTestId('menu-card-reporting')).toBeVisible()
  })

  // Scenario: "Buka Dashboard & Reporting (Mobile) — Menekan kartu yang
  // belum tersedia"
  test('Menekan kartu yang belum tersedia — menampilkan pesan, tidak berpindah rute', async ({ page }) => {
    await login(page)
    await page.goto('/dashboard-reporting')

    // force: true diperlukan karena kartu memakai aria-disabled="true",
    // bukan atribut disabled native — Playwright memperlakukan aria-disabled
    // sebagai "not enabled" dan menolak mengklik. Di perangkat sungguhan kartu
    // ini tetap dapat ditap, dan justru itulah syarat spec: lihat StationGrid.vue,
    // disabled native menelan ketukan sehingga pesan tak pernah muncul.
    // 2026-09-23 (screen-141): kartu yang masih belum dibangun kini
    // 'Dashboard' — 'Reporting' sudah punya layar tujuan (/reports).
    await page.getByTestId('menu-card-dashboard').click({ force: true })

    await expect(page.getByTestId('info-message')).toContainText('Layar Dashboard belum tersedia.')
    await expect(page).toHaveURL(/\/dashboard-reporting$/)
    await expect(page.getByTestId('menu-card-dashboard')).toHaveAttribute('aria-disabled', 'true')
    await expect(page.getByTestId('menu-card-dashboard')).toContainText('Belum tersedia')
  })

  // Scenario: "Buka Dashboard & Reporting (Mobile) — Menekan berkali-kali"
  test('Menekan kartu belum tersedia berkali-kali — pesan tidak menumpuk', async ({ page }) => {
    await login(page)
    await page.goto('/dashboard-reporting')

    const dashboardCard = page.getByTestId('menu-card-dashboard')
    // force: true diperlukan karena kartu memakai aria-disabled="true",
    // bukan atribut disabled native — Playwright memperlakukan aria-disabled
    // sebagai "not enabled" dan menolak mengklik. Di perangkat sungguhan kartu
    // ini tetap dapat ditap, dan justru itulah syarat spec: lihat StationGrid.vue,
    // disabled native menelan ketukan sehingga pesan tak pernah muncul.
    await dashboardCard.click({ force: true })
    await dashboardCard.click({ force: true })
    await dashboardCard.click({ force: true })

    await expect(page.getByTestId('info-message')).toHaveCount(1)
    await expect(page.getByTestId('info-message')).toContainText('Layar Dashboard belum tersedia.')
    await expect(page).toHaveURL(/\/dashboard-reporting$/)
  })

  // Scenario: "Buka Dashboard & Reporting (Mobile) — Kembali ke Home"
  test('Breadcrumb — tapping "Home" navigates back to Home', async ({ page }) => {
    await login(page)
    await page.goto('/dashboard-reporting')

    await page.getByTestId('breadcrumb-home').click()

    await page.waitForURL('**/home')
    await expect(page.getByTestId('menu-card-dashboard-reporting')).toBeVisible()
  })

  // Scenario: "Buka Dashboard & Reporting (Mobile) — Belum masuk"
  test('Belum masuk — mengunjungi langsung /dashboard-reporting mengalihkan ke Login', async ({ page }) => {
    // Fresh context (default per test, no login() call) — no session in
    // local storage, matches the router guard's unauthenticated path.
    await page.goto('/dashboard-reporting')

    // Glob diberi sufiks ** agar cocok dengan /login?redirect=/dashboard-reporting —
    // penjagaan rute memang menyimpan tujuan asal, dan itu perilaku yang diinginkan.
    await page.waitForURL('**/login**')
    await expect(page.getByTestId('menu-card-dashboard')).toHaveCount(0)
    await expect(page.getByTestId('menu-card-reporting')).toHaveCount(0)
  })

  // Scenario: "Buka Dashboard & Reporting (Mobile) — Perangkat offline"
  test('Perangkat offline — layar tetap terbuka penuh tanpa pesan kesalahan jaringan', async ({ page, context }) => {
    await login(page)

    // BATAS LINGKUNGAN — dibaca dulu sebelum mengubah test ini.
    //
    // Skenario aslinya adalah "pengguna MEMBUKA layar ini dalam keadaan
    // offline". Itu TIDAK DAPAT direproduksi di bawah Vite dev server:
    // seluruh rute di router/index.ts memakai lazy import, dan dev server
    // menyajikan modul tanpa cache, sehingga membuka rute — lewat goto
    // maupun navigasi di sisi klien — selalu menuntut pengambilan chunk
    // lewat jaringan. Memutus jaringan lebih dulu hanya menguji pengiriman
    // chunk oleh Vite, bukan perilaku layar ini.
    //
    // Di produk nyata kebutuhan itu TIDAK ADA: Capacitor memuat seluruh
    // bundle dari perangkat, jadi layar ini memang terbuka tanpa sinyal.
    // Jangan "memperbaiki" ini dengan membuat rutenya eager-import — itu
    // mengubah kode produksi demi keterbatasan alat uji.
    //
    // Yang diuji di sini adalah inti klaimnya, dan itu memang terbukti:
    // begitu layar terbuka, layar ini sama sekali tidak membutuhkan
    // jaringan — tidak ada permintaan data, tidak ada pesan kesalahan,
    // dan interaksinya tetap berfungsi penuh saat offline.
    await page.goto('/dashboard-reporting')
    await expect(page.getByTestId('menu-card-dashboard')).toBeVisible()

    await context.setOffline(true)

    // Layar tetap utuh tanpa jaringan.
    await expect(page.getByTestId('menu-card-dashboard')).toBeVisible()
    await expect(page.getByTestId('menu-card-reporting')).toBeVisible()
    await expect(page.getByText(/gagal|error|kesalahan jaringan/i)).toHaveCount(0)

    // Dan interaksinya pun tidak butuh jaringan: pesan info tetap muncul.
    // Kartu yang masih nonaktif kini 'Dashboard' (screen-141).
    await page.getByTestId('menu-card-dashboard').click({ force: true })
    await expect(page.getByTestId('info-message')).toContainText(
      'Layar Dashboard belum tersedia.',
    )
    await expect(page).toHaveURL(/\/dashboard-reporting$/)

    await context.setOffline(false)
  })

  // Scenario: "Buka Dashboard & Reporting (Mobile) — layar terbuka untuk
  // semua peran mobile"
  test('Layar terbuka untuk Station Operator', async ({ page }) => {
    await login(page, USERS.operator)
    await page.goto('/dashboard-reporting')

    await expect(page).toHaveURL(/\/dashboard-reporting$/)
    await expect(page.getByTestId('menu-card-dashboard')).toBeVisible()
    await expect(page.getByTestId('menu-card-reporting')).toBeVisible()
    await expect(page.getByText(/akses ditolak|tidak diizinkan/i)).toHaveCount(0)
  })

  test('Layar terbuka untuk Supervisor', async ({ page }) => {
    await login(page, USERS.supervisor)
    await page.goto('/dashboard-reporting')

    await expect(page).toHaveURL(/\/dashboard-reporting$/)
    await expect(page.getByTestId('menu-card-dashboard')).toBeVisible()
    await expect(page.getByTestId('menu-card-reporting')).toBeVisible()
    await expect(page.getByText(/akses ditolak|tidak diizinkan/i)).toHaveCount(0)
  })

  // Scenario: "Buka Dashboard & Reporting (Mobile) — pilihan yang belum
  // dibangun tetap ditampilkan nonaktif".
  //
  // 2026-09-23 (screen-141): kartu 'Dashboard' masih belum dibangun dan
  // asersinya TIDAK dilemahkan sedikit pun; kartu 'Reporting' kini hidup
  // dan membawa ke /reports.
  test('Kartu Dashboard tetap ditampilkan nonaktif, kartu Reporting kini bernavigasi', async ({ page }) => {
    await login(page)
    await page.goto('/dashboard-reporting')

    const dashboardCard = page.getByTestId('menu-card-dashboard')
    const reportingCard = page.getByTestId('menu-card-reporting')

    await expect(dashboardCard).toBeVisible()
    await expect(dashboardCard).toHaveAttribute('aria-disabled', 'true')
    await expect(dashboardCard).toContainText('Belum tersedia')

    await expect(reportingCard).toBeVisible()
    await expect(reportingCard).toHaveAttribute('aria-disabled', 'false')
    await expect(reportingCard).not.toContainText('Belum tersedia')

    await expect(page.locator('[data-testid^="menu-card-"]')).toHaveCount(2)

    await reportingCard.click()
    await page.waitForURL('**/reports')
    await expect(page.getByTestId('info-message')).toHaveCount(0)
  })
})
