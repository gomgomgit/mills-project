/**
 * FormProcessQualityControlTest (Browser/Playwright) —
 * screen-120--form-process-quality-control-web / usecase-120--form-process-quality-control-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/FormClarificationTest.php's conventions — MINUS any
 * operational-target table assertion (this station has none).
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserProcessQualityControlTest.php's docblock for the full
 * rationale.
 *
 * Test data assumption: authenticated Supervisor session
 * (pqctest-supervisor01 / Passw0rd!), a Production Line named
 * "PL Mill A" with an active Process Quality Control station, a second
 * Production Line "PL Tanpa Process Quality Control" with no active Process
 * Quality Control station, and a pre-seeded Process Quality Control record
 * with Process QC ID "PQC-BROWSER-EDIT" under "PL Mill A".
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const CREATE_PATH = '/data/process-quality-control/create';
const PRODUCTION_LINE_NAME = 'PL Mill A';


test.describe('Form Process Quality Control (Web)', () => {
  // Scenario: "Buat Record Process Quality Control Baru — berhasil"
  test('klik Tambah Data, isi form lengkap termasuk 1 kolom, klik Simpan, halaman Detail menampilkan record baru', async ({ page }) => {
    await login(page, 'pqctest-supervisor01', PASSWORD);
    await page.goto('/data/process-quality-control');
    await page.locator('[data-testid="add-data-button"]').click();
    await page.waitForURL(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    const uniqueSuffix = Date.now();
    await page.locator('[data-testid="process-qc-id-input"]').fill(`PQC-BROWSER-${uniqueSuffix}`);
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('07:00');
    await page.locator('[data-testid="fruit-press-oil-loss-in-sludge-0"]').fill('0.85');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/process-quality-control/') && !url.pathname.endsWith('/create'));
    await expect(page.locator('[data-testid="detail-process-qc-id"]')).toContainText(`PQC-BROWSER-${uniqueSuffix}`);
  });

  // Scenario: "Field Wajib Belum Lengkap"
  test('kosongkan field wajib, klik Simpan, error inline muncul', async ({ page }) => {
    await login(page, 'pqctest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('.pf-field__error')).toContainText('Process QC ID');
  });

  // Scenario: "Belum Ada Baris Valid"
  test('isi header lengkap tanpa menambah baris apapun, klik Simpan, pesan error khusus muncul', async ({ page }) => {
    await login(page, 'pqctest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="process-qc-id-input"]').fill('PQC-NO-DETAIL');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="detail-error"]')).toBeVisible();
  });

  // Scenario: "Production Line Tanpa Station Process Quality Control Aktif"
  test('pilih Production Line tanpa station process quality control aktif, klik Simpan, error ditampilkan', async ({ page }) => {
    await login(page, 'pqctest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: 'PL Tanpa Process Quality Control' });
    await page.locator('[data-testid="process-qc-id-input"]').fill('PQC-NO-STATION');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('07:00');
    await page.locator('[data-testid="fruit-press-oil-loss-in-sludge-0"]').fill('0.6');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="general-error"]')).toBeVisible();
  });

  // Scenario: "Tambah/Hapus Baris Dinamis dan Urutan Time-Slot"
  test('grid Process Quality Control Detail dimulai kosong, bertambah satu baris tiap klik Tambah Baris, dan bisa dihapus', async ({ page }) => {
    await login(page, 'pqctest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await expect(page.locator('[data-testid="process-quality-control-detail-grid"] tbody tr')).toHaveCount(0);

    await page.locator('[data-testid="add-row-button"]').click();
    await expect(page.locator('[data-testid="process-quality-control-detail-grid"] tbody tr')).toHaveCount(1);

    await page.locator('[data-testid="add-row-button"]').click();
    await expect(page.locator('[data-testid="process-quality-control-detail-grid"] tbody tr')).toHaveCount(2);

    await page.locator('[data-testid="remove-row-button-0"]').click();
    await expect(page.locator('[data-testid="process-quality-control-detail-grid"] tbody tr')).toHaveCount(1);
  });

  // Scenario: "Edit Record Process Quality Control — berhasil"
  test('klik Edit dari Detail, ubah field, klik Simpan, Detail menampilkan nilai baru', async ({ page }) => {
    await login(page, 'pqctest-supervisor01', PASSWORD);
    await page.goto('/data/process-quality-control');
    await page.locator('.pqc-table__row', { hasText: 'PQC-BROWSER-EDIT' }).click();
    await page.locator('[data-testid="edit-button"]').click();

    await page.locator('[data-testid="process-qc-id-input"]').fill('PQC-BROWSER-EDIT-DONE');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => !url.pathname.endsWith('/edit'));
    await expect(page.locator('body')).toContainText('PQC-BROWSER-EDIT-DONE');
  });

  // Scenario: "Record Tidak Ditemukan (mode edit)"
  test('navigasi ke id yang tidak valid, halaman menampilkan error', async ({ page }) => {
    await login(page, 'pqctest-supervisor01', PASSWORD);
    await page.goto('/data/process-quality-control/00000000-0000-0000-0000-000000000000/edit');

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });

  // Scenario: "Baris Valid Hanya Dari Findings"
  test('tambah baris dengan hanya Findings terisi, klik Simpan, record tersimpan', async ({ page }) => {
    await login(page, 'pqctest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="process-qc-id-input"]').fill('PQC-FINDINGS-ONLY');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('08:00');
    await page.locator('[data-testid="findings-0"]').fill('Perlu ditinjau');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/process-quality-control/') && !url.pathname.endsWith('/create'));
  });
});
