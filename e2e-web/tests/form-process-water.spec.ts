/**
 * FormProcessWaterTest (Browser/Playwright) —
 * screen-112--form-process-water-web / usecase-072--form-process-water-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/FormThreshingTest.php's conventions — MINUS any
 * operational-target table assertion (this station has none).
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserProcessWaterTest.php's docblock for the full rationale.
 *
 * Test data assumption: authenticated Supervisor session
 * (pwtest-supervisor01 / Passw0rd!), a Production Line named
 * "PL Mill A" with an active Process Water station, a second Production
 * Line "PL Tanpa Process Water" with no active Process Water station, and
 * a pre-seeded Process Water record with Process Water ID
 * "PW-BROWSER-EDIT" under "PL Mill A".
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const CREATE_PATH = '/data/process-water/create';
const PRODUCTION_LINE_NAME = 'PL Mill A';


test.describe('Form Process Water (Web)', () => {
  // Scenario: "Buat Record Process Water Baru — berhasil"
  test('klik Tambah Data, isi form lengkap termasuk 1 kolom bacaan, klik Simpan, halaman Detail menampilkan record baru', async ({ page }) => {
    await login(page, 'pwtest-supervisor01', PASSWORD);
    await page.goto('/data/process-water');
    await page.locator('[data-testid="add-data-button"]').click();
    await page.waitForURL(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    const uniqueSuffix = Date.now();
    await page.locator('[data-testid="process-water-id-input"]').fill(`PW-BROWSER-${uniqueSuffix}`);
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('07:00');
    await page.locator('[data-testid="raw-water-flow-0"]').fill('45.5');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/process-water/') && !url.pathname.endsWith('/create'));
    await expect(page.locator('[data-testid="detail-process-water-id"]')).toContainText(`PW-BROWSER-${uniqueSuffix}`);
  });

  // Scenario: "Field Wajib Belum Lengkap"
  test('kosongkan field wajib, klik Simpan, error inline muncul', async ({ page }) => {
    await login(page, 'pwtest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('.pf-field__error')).toContainText('Process Water ID');
  });

  // Scenario: "Belum Ada Baris Valid"
  test('isi header lengkap tanpa menambah baris apapun, klik Simpan, pesan error khusus muncul', async ({ page }) => {
    await login(page, 'pwtest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="process-water-id-input"]').fill('PW-NO-DETAIL');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="detail-error"]')).toBeVisible();
  });

  // Scenario: "Production Line Tanpa Station Process Water Aktif"
  test('pilih Production Line tanpa station process-water, klik Simpan, error ditampilkan', async ({ page }) => {
    await login(page, 'pwtest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: 'PL Tanpa Process Water' });
    await page.locator('[data-testid="process-water-id-input"]').fill('PW-NO-STATION');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('07:00');
    await page.locator('[data-testid="raw-water-flow-0"]').fill('10');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="general-error"]')).toBeVisible();
  });

  // Scenario: "Tambah/Hapus Baris Dinamis dan Urutan Time-Slot"
  test('grid Process Water Detail dimulai kosong, bertambah satu baris tiap klik Tambah Baris, dan bisa dihapus', async ({ page }) => {
    await login(page, 'pwtest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await expect(page.locator('[data-testid="process-water-detail-grid"] tbody tr')).toHaveCount(0);

    await page.locator('[data-testid="add-row-button"]').click();
    await expect(page.locator('[data-testid="process-water-detail-grid"] tbody tr')).toHaveCount(1);

    await page.locator('[data-testid="add-row-button"]').click();
    await expect(page.locator('[data-testid="process-water-detail-grid"] tbody tr')).toHaveCount(2);

    await page.locator('[data-testid="remove-row-button-0"]').click();
    await expect(page.locator('[data-testid="process-water-detail-grid"] tbody tr')).toHaveCount(1);
  });

  // Scenario: "Edit Record Process Water — berhasil"
  test('klik Edit dari Detail, ubah field, klik Simpan, Detail menampilkan nilai baru', async ({ page }) => {
    await login(page, 'pwtest-supervisor01', PASSWORD);
    await page.goto('/data/process-water');
    await page.locator('.pw-table__row', { hasText: 'PW-BROWSER-EDIT' }).click();
    await page.locator('[data-testid="edit-button"]').click();

    await page.locator('[data-testid="process-water-id-input"]').fill('PW-BROWSER-EDIT-DONE');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => !url.pathname.endsWith('/edit'));
    await expect(page.locator('body')).toContainText('PW-BROWSER-EDIT-DONE');
  });

  // Scenario: "Record Tidak Ditemukan (mode edit)"
  test('navigasi ke id yang tidak valid, halaman menampilkan error', async ({ page }) => {
    await login(page, 'pwtest-supervisor01', PASSWORD);
    await page.goto('/data/process-water/00000000-0000-0000-0000-000000000000/edit');

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });
});
