/**
 * FormKernelPlantTest (Browser/Playwright) —
 * screen-060--form-kernel-plant-web /
 * usecase-060--form-kernel-plant-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/FormDepricarpingTest.php's conventions — REVISED
 * 2026-08-24 (entity-catalog v12): the Kernel Plant Detail grid is now a
 * dynamic add-row/remove-row grid (the user explicitly rejected the
 * original fixed 24-row design), so this now exercises "Tambah Baris" +
 * the Time-Slot <select> exactly like FormDepricarpingTest.php's
 * Depricarping Detail grid, adapted for Kernel Plant's own header field
 * (kernel_plant_id) and 9 reading columns (7 numeric + downtime_minutes +
 * findings) and 3-column Target Operasional table.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * tests/Browser/DataBrowserKernelPlantTest.php's docblock for the full
 * rationale.
 *
 * Test data assumption: authenticated Supervisor session
 * (kernelplanttest-supervisor01 / Passw0rd!), a Production Line named
 * "PL Mill A" with an active Kernel Plant station, a second Production
 * Line "PL Tanpa Kernel Plant" with no active Kernel Plant station, and a
 * pre-seeded Kernel Plant record with Kernel Plant ID "KP-BROWSER-EDIT"
 * under "PL Mill A".
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const CREATE_PATH = '/data/kernel-plant/create';
const PRODUCTION_LINE_NAME = 'PL Mill A';


test.describe('Form Kernel Plant (Web)', () => {
  // Scenario: "berhasil"
  test('klik Tambah Data, isi form lengkap termasuk 1 kolom bacaan, klik Simpan, halaman Detail menampilkan record baru', async ({ page }) => {
    await login(page, 'kernelplanttest-supervisor01', PASSWORD);
    await page.goto('/data/kernel-plant');
    await page.locator('[data-testid="add-data-button"]').click();
    await page.waitForURL(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    const uniqueSuffix = Date.now();
    await page.locator('[data-testid="kernel-plant-id-input"]').fill(`KP-BROWSER-${uniqueSuffix}`);
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('07:00');
    await page.locator('[data-testid="ripple-mill-1-0"]').fill('22');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/kernel-plant/') && !url.pathname.endsWith('/create'));
    await expect(page.locator('[data-testid="detail-kernel-plant-id"]')).toContainText(`KP-BROWSER-${uniqueSuffix}`);
  });

  // Scenario: "Field Wajib Belum Lengkap"
  test('kosongkan field wajib, klik Simpan, error inline muncul', async ({ page }) => {
    await login(page, 'kernelplanttest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('.kf-field__error')).toContainText('Kernel Plant ID');
  });

  // Scenario: "Belum Ada Baris Terisi"
  test('isi header lengkap tanpa mengisi kolom bacaan apapun, klik Simpan, pesan error khusus muncul', async ({ page }) => {
    await login(page, 'kernelplanttest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="kernel-plant-id-input"]').fill('KP-NO-DETAIL');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="detail-error"]')).toBeVisible();
  });

  // Scenario: "Baris Terisi Hanya via Downtime/Findings"
  test('isi hanya kolom Findings pada satu baris, klik Simpan, berhasil tersimpan', async ({ page }) => {
    await login(page, 'kernelplanttest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="kernel-plant-id-input"]').fill('KP-FINDINGS-ONLY');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('07:00');
    await page.locator('[data-testid="findings-0"]').fill('Ripple mill vibration');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/kernel-plant/') && !url.pathname.endsWith('/create'));
  });

  // Scenario: "Production Line Tanpa Station Kernel Plant Aktif"
  test('pilih Production Line tanpa station kernel-plant, klik Simpan, error ditampilkan', async ({ page }) => {
    await login(page, 'kernelplanttest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: 'PL Tanpa Kernel Plant' });
    await page.locator('[data-testid="kernel-plant-id-input"]').fill('KP-NO-STATION');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('07:00');
    await page.locator('[data-testid="ripple-mill-1-0"]').fill('10');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="general-error"]')).toBeVisible();
  });

  // Scenario: "Tambah/Hapus Baris Dinamis" — REVISED 2026-08-24
  // (entity-catalog v12): the grid starts empty and grows one row at a
  // time via "Tambah Baris", mirroring Depricarping Detail exactly.
  test('grid Kernel Plant Detail dimulai kosong, bertambah satu baris tiap klik Tambah Baris, dan bisa dihapus', async ({ page }) => {
    await login(page, 'kernelplanttest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await expect(page.locator('[data-testid="kernel-plant-detail-grid"] tbody tr')).toHaveCount(0);

    await page.locator('[data-testid="add-row-button"]').click();
    await expect(page.locator('[data-testid="kernel-plant-detail-grid"] tbody tr')).toHaveCount(1);

    await page.locator('[data-testid="add-row-button"]').click();
    await expect(page.locator('[data-testid="kernel-plant-detail-grid"] tbody tr')).toHaveCount(2);

    await page.locator('[data-testid="remove-row-button-0"]').click();
    await expect(page.locator('[data-testid="kernel-plant-detail-grid"] tbody tr')).toHaveCount(1);
  });

  // Scenario: "Target Operasional 3 Kolom"
  test('tabel Target Operasional menampilkan 6 baris dan 3 kolom', async ({ page }) => {
    await login(page, 'kernelplanttest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    const table = page.locator('[data-testid="operational-target-table"]');
    await expect(table.locator('tbody tr')).toHaveCount(6);
    await expect(table.locator('thead th')).toHaveCount(3);
    await expect(table).toContainText('Corrective Action Plan');
  });

  // Scenario: "Edit Record Kernel Plant — berhasil"
  test('klik Edit dari Detail, ubah field, klik Simpan, Detail menampilkan nilai baru', async ({ page }) => {
    await login(page, 'kernelplanttest-supervisor01', PASSWORD);
    await page.goto('/data/kernel-plant');
    await page.locator('.kp-table__row', { hasText: 'KP-BROWSER-EDIT' }).click();
    await page.locator('[data-testid="edit-button"]').click();

    await page.locator('[data-testid="kernel-plant-id-input"]').fill('KP-BROWSER-EDIT-DONE');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => !url.pathname.endsWith('/edit'));
    await expect(page.locator('body')).toContainText('KP-BROWSER-EDIT-DONE');
  });

  // Scenario: "Record Tidak Ditemukan (mode edit)"
  test('navigasi ke id yang tidak valid, halaman menampilkan error', async ({ page }) => {
    await login(page, 'kernelplanttest-supervisor01', PASSWORD);
    await page.goto('/data/kernel-plant/00000000-0000-0000-0000-000000000000/edit');

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });
});
