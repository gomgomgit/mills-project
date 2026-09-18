/**
 * DataBrowserThreshingTest (Browser/Playwright) —
 * screen-049--data-browser-threshing-web /
 * usecase-049--data-browser-threshing-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/DataBrowserCagesTrackTest.php's conventions exactly.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — same constraint as every
 * other Browser/* spec in this codebase (no dev server/browser available in
 * this sandbox). See that file's own docblock for the full rationale.
 *
 * Test data assumption: authenticated session with role supervisor,
 * mill_management, or admin (username threshtest-browse01 / Passw0rd!),
 * and at least one ThreshingRecord for that user's business unit, dated
 * within 2026-01-01..2026-12-31.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const DATA_BROWSER_PATH = '/data/threshing';
const USERNAME = 'threshtest-browse01';


test.describe('Data Browser Threshing', () => {
  // Scenario: "Telusuri & Ekspor Data Threshing — success"
  test('menerapkan filter, tabel diperbarui, dan ekspor CSV memicu unduhan', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(DATA_BROWSER_PATH);

    await page.locator('#date_from').fill('2026-01-01');
    await page.locator('#date_to').fill('2026-12-31');

    await expect(page.locator('.th-browser')).not.toHaveClass(/th-browser--busy/);
    await expect(page.locator('.th-table__row').first()).toBeVisible();
    await expect(page.locator('.th-empty')).toHaveCount(0);

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.locator('.th-browser__export a', { hasText: 'Ekspor CSV' }).click(),
    ]);

    expect(download.suggestedFilename()).toMatch(/^threshing-records_.*\.csv$/);
  });

  // Scenario: "Telusuri & Ekspor Data Threshing — Tidak Ada Data Sesuai Filter"
  test('filter tanpa data cocok menampilkan pesan tidak ada data', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(DATA_BROWSER_PATH);

    await page.locator('#date_from').fill('2000-01-01');
    await page.locator('#date_to').fill('2000-01-02');

    await expect(page.locator('.th-browser')).not.toHaveClass(/th-browser--busy/);
    await expect(page.locator('.th-empty__title')).toHaveText('Tidak ada data');
  });

  // Scenario: "Telusuri & Ekspor Data Threshing — Rentang Tanggal Tidak Valid"
  test('rentang tanggal tidak valid menampilkan pesan validasi', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(DATA_BROWSER_PATH);

    await page.locator('#date_from').fill('2026-08-20');
    await page.locator('#date_to').fill('2026-08-10');

    await expect(page.locator('.th-alert')).toContainText('Rentang tanggal tidak valid');
  });

  // Scenario: "Telusuri & Ekspor Data Threshing — Klik Baris Membuka Detail"
  test('klik baris membuka halaman Detail Threshing', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(DATA_BROWSER_PATH);

    await page.locator('.th-table__row').first().click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/threshing/') && url.pathname !== '/data/threshing/create');
  });

  // Scenario: "Telusuri & Ekspor Data Threshing — Ekspor Gagal"
  // (dataset size guard is server-side only, see
  // tests/Feature/Api/DataBrowserThreshingTest.php's "Ekspor Gagal" scenario
  // for full end-to-end coverage of the 422 EXPORT_FAILED response —
  // reproducing a 50,001-row dataset through the real browser is
  // impractical here.)
  test('tombol ekspor selalu mengikuti filter aktif (unduhan gagal diverifikasi lewat Feature/Api)', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(DATA_BROWSER_PATH);

    await page.locator('#date_from').fill('2026-01-01');
    await page.locator('#date_to').fill('2026-12-31');

    const exportHref = await page.locator('.th-browser__export a', { hasText: 'Ekspor CSV' }).getAttribute('href');
    expect(exportHref).toContain('date_from=2026-01-01');
    expect(exportHref).toContain('date_to=2026-12-31');
  });
});
