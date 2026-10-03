/**
 * DataBrowserKernelPlantTest (Browser/Playwright) —
 * screen-052--data-browser-kernel-plant-web /
 * usecase-052--data-browser-kernel-plant-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/DataBrowserDepricarpingTest.php's conventions exactly.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — same constraint as every
 * other Browser/* spec in this codebase (no dev server/browser available in
 * this sandbox). See that file's own docblock for the full rationale.
 *
 * Test data assumption: authenticated session with role supervisor,
 * mill_management, or admin (username kernelplanttest-browse01 /
 * Passw0rd!), and at least one KernelPlantRecord for that user's business
 * unit, dated within 2026-01-01..2026-12-31.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { assertProductionLineFilter } from './support/production-line-filter'

const DATA_BROWSER_PATH = '/data/kernel-plant';
const USERNAME = 'kernelplanttest-browse01';


test.describe('Data Browser Kernel Plant', () => {
  // Scenario: "Telusuri & Ekspor Data Kernel Plant — success"
  test('menerapkan filter, tabel diperbarui, dan ekspor CSV memicu unduhan', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(DATA_BROWSER_PATH);

    await page.locator('#date_from').fill('2026-01-01');
    await page.locator('#date_to').fill('2026-12-31');

    await expect(page.locator('.kp-browser')).not.toHaveClass(/kp-browser--busy/);
    await expect(page.locator('.kp-table__row').first()).toBeVisible();
    await expect(page.locator('.kp-empty')).toHaveCount(0);

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.locator('.kp-browser__export a', { hasText: 'Ekspor CSV' }).click(),
    ]);

    expect(download.suggestedFilename()).toMatch(/^kernel-plant-records_.*\.csv$/);
  });

  // Scenario: "Telusuri & Ekspor Data Kernel Plant — Tidak Ada Data Sesuai Filter"
  test('filter tanpa data cocok menampilkan pesan tidak ada data', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(DATA_BROWSER_PATH);

    await page.locator('#date_from').fill('2000-01-01');
    await page.locator('#date_to').fill('2000-01-02');

    await expect(page.locator('.kp-browser')).not.toHaveClass(/kp-browser--busy/);
    await expect(page.locator('.kp-empty__title')).toHaveText('Tidak ada data');
  });

  // Scenario: "Telusuri & Ekspor Data Kernel Plant — Rentang Tanggal Tidak Valid"
  test('rentang tanggal tidak valid menampilkan pesan validasi', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(DATA_BROWSER_PATH);

    await page.locator('#date_from').fill('2026-08-20');
    await page.locator('#date_to').fill('2026-08-10');

    await expect(page.locator('.kp-alert')).toContainText('Rentang tanggal tidak valid');
  });

  // Scenario: "Telusuri & Ekspor Data Kernel Plant — Klik Baris Membuka Detail"
  test('klik baris membuka halaman Detail Kernel Plant', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(DATA_BROWSER_PATH);

    await page.locator('.kp-table__row').first().click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/kernel-plant/') && url.pathname !== '/data/kernel-plant/create');
  });

  // Scenario: "Telusuri & Ekspor Data Kernel Plant — Ekspor Gagal"
  // (dataset size guard is server-side only, see
  // tests/Feature/Api/DataBrowserKernelPlantTest.php's "Ekspor Gagal"
  // scenario for full end-to-end coverage of the 422 EXPORT_FAILED
  // response — reproducing a 50,001-row dataset through the real browser
  // is impractical here.)
  test('tombol ekspor selalu mengikuti filter aktif (unduhan gagal diverifikasi lewat Feature/Api)', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(DATA_BROWSER_PATH);

    await page.locator('#date_from').fill('2026-01-01');
    await page.locator('#date_to').fill('2026-12-31');

    // Tunggu putaran Livewire: href baru ditulis ulang SETELAH server
    // menerima filter. Membaca getAttribute() langsung setelah fill() selalu
    // mendapat href lama (tanpa date_from) — race, bukan cacat aplikasi.
    const exportLink = page.locator('.kp-browser__export a', { hasText: 'Ekspor CSV' });
    await expect(exportLink).toHaveAttribute('href', /date_from=2026-01-01/);
    await expect(exportLink).toHaveAttribute('href', /date_to=2026-12-31/);
  });

  // Scenario: filter Production Line — record milik dua line di mill yang
  // sama (fixture KP-BROWSER-PL-A / -PL-B, BrowserTestFixtureSeeder::
  // productionLineFilterFixtures). "Semua Line" memuat keduanya dengan
  // Production Line sebagai kolom pertama; memilih satu line menyempitkan
  // tabel ke record line itu saja; tautan ekspor (dan CSV-nya) membawa
  // production_line_id. Langkahnya di tests/support/production-line-filter.ts.
  test('filter production line menyempitkan tabel dan terbawa ke ekspor', async ({ page }) => {
    await assertProductionLineFilter(page, {
      username: USERNAME,
      path: '/data/kernel-plant',
      cls: 'kp',
      idPrefix: 'KP',
    });
  });
});
