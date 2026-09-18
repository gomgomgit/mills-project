/**
 * FormDepricarpingTest (Browser/Playwright) —
 * screen-059--form-depricarping-web /
 * usecase-059--form-depricarping-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/FormPressingTest.php's conventions — REVISED 2026-08-24
 * (entity-catalog v12): the Depricarping Detail grid is now a dynamic
 * add-row/remove-row grid (the user explicitly rejected the original fixed
 * 24-row design), so this now exercises "Tambah Baris" + the Time-Slot
 * <select> exactly like FormPressingTest.php's Pressing Detail grid,
 * adapted for Depricarping's own header field (presser_id) and 8 reading
 * columns (7 numeric + downtime_minutes + findings) and 4-column Target
 * Operasional table.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * tests/Browser/DataBrowserDepricarpingTest.php's docblock for the full
 * rationale.
 *
 * Test data assumption: authenticated Supervisor session
 * (depricarpingtest-supervisor01 / Passw0rd!), a Production Line named
 * "PL Mill A" with an active Depricarping station, a second Production
 * Line "PL Tanpa Depricarping" with no active Depricarping station, and a
 * pre-seeded Depricarping record with Presser ID "DP-BROWSER-EDIT" under
 * "PL Mill A".
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const CREATE_PATH = '/data/depricarping/create';
const PRODUCTION_LINE_NAME = 'PL Mill A';


test.describe('Form Depricarping (Web)', () => {
  // Scenario: "berhasil"
  test('klik Tambah Data, isi form lengkap termasuk 1 kolom bacaan, klik Simpan, halaman Detail menampilkan record baru', async ({ page }) => {
    await login(page, 'depricarpingtest-supervisor01', PASSWORD);
    await page.goto('/data/depricarping');
    await page.locator('[data-testid="add-data-button"]').click();
    await page.waitForURL(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    const uniqueSuffix = Date.now();
    await page.locator('[data-testid="presser-id-input"]').fill(`DP-BROWSER-${uniqueSuffix}`);
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('07:00');
    await page.locator('[data-testid="fan-static-pressure-0"]').fill('45');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/depricarping/') && !url.pathname.endsWith('/create'));
    await expect(page.locator('[data-testid="detail-presser-id"]')).toContainText(`DP-BROWSER-${uniqueSuffix}`);
  });

  // Scenario: "Field Wajib Belum Lengkap"
  test('kosongkan field wajib, klik Simpan, error inline muncul', async ({ page }) => {
    await login(page, 'depricarpingtest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('.pf-field__error')).toContainText('Presser ID');
  });

  // Scenario: "Belum Ada Baris Terisi"
  test('isi header lengkap tanpa mengisi kolom bacaan apapun, klik Simpan, pesan error khusus muncul', async ({ page }) => {
    await login(page, 'depricarpingtest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="presser-id-input"]').fill('DP-NO-DETAIL');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="detail-error"]')).toBeVisible();
  });

  // Scenario: "Baris Terisi Hanya via Downtime/Findings"
  test('isi hanya kolom Findings pada satu baris, klik Simpan, berhasil tersimpan', async ({ page }) => {
    await login(page, 'depricarpingtest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="presser-id-input"]').fill('DP-FINDINGS-ONLY');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('07:00');
    await page.locator('[data-testid="findings-0"]').fill('Fan belt loose');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/depricarping/') && !url.pathname.endsWith('/create'));
  });

  // Scenario: "Production Line Tanpa Station Depricarping Aktif"
  test('pilih Production Line tanpa station depricarping, klik Simpan, error ditampilkan', async ({ page }) => {
    await login(page, 'depricarpingtest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: 'PL Tanpa Depricarping' });
    await page.locator('[data-testid="presser-id-input"]').fill('DP-NO-STATION');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('07:00');
    await page.locator('[data-testid="fan-static-pressure-0"]').fill('10');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="general-error"]')).toBeVisible();
  });

  // Scenario: "Tambah/Hapus Baris Dinamis" — REVISED 2026-08-24
  // (entity-catalog v12): the grid starts empty and grows one row at a
  // time via "Tambah Baris", mirroring Pressing Detail exactly.
  test('grid Depricarping Detail dimulai kosong, bertambah satu baris tiap klik Tambah Baris, dan bisa dihapus', async ({ page }) => {
    await login(page, 'depricarpingtest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await expect(page.locator('[data-testid="depricarping-detail-grid"] tbody tr')).toHaveCount(0);

    await page.locator('[data-testid="add-row-button"]').click();
    await expect(page.locator('[data-testid="depricarping-detail-grid"] tbody tr')).toHaveCount(1);

    await page.locator('[data-testid="add-row-button"]').click();
    await expect(page.locator('[data-testid="depricarping-detail-grid"] tbody tr')).toHaveCount(2);

    await page.locator('[data-testid="remove-row-button-0"]').click();
    await expect(page.locator('[data-testid="depricarping-detail-grid"] tbody tr')).toHaveCount(1);
  });

  // Scenario: "Target Operasional 4 Kolom"
  test('tabel Target Operasional menampilkan 6 baris dan 4 kolom (termasuk Operational Consequence / Justification)', async ({ page }) => {
    await login(page, 'depricarpingtest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    const table = page.locator('[data-testid="operational-target-table"]');
    await expect(table.locator('tbody tr')).toHaveCount(6);
    await expect(table.locator('thead th')).toHaveCount(4);
    await expect(table).toContainText('Operational Consequence / Justification');
  });

  // Scenario: "Edit Record Depricarping — berhasil"
  test('klik Edit dari Detail, ubah field, klik Simpan, Detail menampilkan nilai baru', async ({ page }) => {
    await login(page, 'depricarpingtest-supervisor01', PASSWORD);
    await page.goto('/data/depricarping');
    await page.locator('.dp-table__row', { hasText: 'DP-BROWSER-EDIT' }).click();
    await page.locator('[data-testid="edit-button"]').click();

    await page.locator('[data-testid="presser-id-input"]').fill('DP-BROWSER-EDIT-DONE');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => !url.pathname.endsWith('/edit'));
    await expect(page.locator('body')).toContainText('DP-BROWSER-EDIT-DONE');
  });

  // Scenario: "Record Tidak Ditemukan (mode edit)"
  test('navigasi ke id yang tidak valid, halaman menampilkan error', async ({ page }) => {
    await login(page, 'depricarpingtest-supervisor01', PASSWORD);
    await page.goto('/data/depricarping/00000000-0000-0000-0000-000000000000/edit');

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });
});
