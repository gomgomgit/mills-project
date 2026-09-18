/**
 * FormEngineRoomTest (Browser/Playwright) —
 * screen-117--form-engine-room-web / usecase-102--form-engine-room-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/FormStorageTankTest.php's conventions — MINUS any
 * operational-target table assertion (this station has none).
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserEngineRoomTest.php's docblock for the full rationale.
 *
 * Test data assumption: authenticated Supervisor session
 * (ertest-supervisor01 / Passw0rd!), a Production Line named
 * "PL Mill A" with an active Engine Room station, a second Production
 * Line "PL Tanpa Engine Room" with no active Engine Room station, and
 * a pre-seeded Engine Room record with Engine Room ID
 * "ER-BROWSER-EDIT" under "PL Mill A".
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const CREATE_PATH = '/data/engine-room/create';
const PRODUCTION_LINE_NAME = 'PL Mill A';


test.describe('Form Engine Room (Web)', () => {
  // Scenario: "Buat Record Engine Room Baru — berhasil"
  test('klik Tambah Data, isi form lengkap termasuk 1 kolom bacaan, klik Simpan, halaman Detail menampilkan record baru', async ({ page }) => {
    await login(page, 'ertest-supervisor01', PASSWORD);
    await page.goto('/data/engine-room');
    await page.locator('[data-testid="add-data-button"]').click();
    await page.waitForURL(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    const uniqueSuffix = Date.now();
    await page.locator('[data-testid="engine-room-id-input"]').fill(`ER-BROWSER-${uniqueSuffix}`);
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('07:00');
    await page.locator('[data-testid="steam-inlet-pressure-0"]').fill('12.5');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/engine-room/') && !url.pathname.endsWith('/create'));
    await expect(page.locator('[data-testid="detail-engine-room-id"]')).toContainText(`ER-BROWSER-${uniqueSuffix}`);
  });

  // Scenario: "Field Wajib Belum Lengkap"
  test('kosongkan field wajib, klik Simpan, error inline muncul', async ({ page }) => {
    await login(page, 'ertest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('.pf-field__error')).toContainText('Engine Room ID');
  });

  // Scenario: "Belum Ada Baris Valid"
  test('isi header lengkap tanpa menambah baris apapun, klik Simpan, pesan error khusus muncul', async ({ page }) => {
    await login(page, 'ertest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="engine-room-id-input"]').fill('ER-NO-DETAIL');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="detail-error"]')).toBeVisible();
  });

  // Scenario: "Production Line Tanpa Station Engine Room Aktif"
  test('pilih Production Line tanpa station engine-room, klik Simpan, error ditampilkan', async ({ page }) => {
    await login(page, 'ertest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: 'PL Tanpa Engine Room' });
    await page.locator('[data-testid="engine-room-id-input"]').fill('ER-NO-STATION');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('07:00');
    await page.locator('[data-testid="steam-inlet-pressure-0"]').fill('10');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="general-error"]')).toBeVisible();
  });

  // Scenario: "Tambah/Hapus Baris Dinamis dan Urutan Time-Slot"
  test('grid Engine Room Detail dimulai kosong, bertambah satu baris tiap klik Tambah Baris, dan bisa dihapus', async ({ page }) => {
    await login(page, 'ertest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await expect(page.locator('[data-testid="engine-room-detail-grid"] tbody tr')).toHaveCount(0);

    await page.locator('[data-testid="add-row-button"]').click();
    await expect(page.locator('[data-testid="engine-room-detail-grid"] tbody tr')).toHaveCount(1);

    await page.locator('[data-testid="add-row-button"]').click();
    await expect(page.locator('[data-testid="engine-room-detail-grid"] tbody tr')).toHaveCount(2);

    await page.locator('[data-testid="remove-row-button-0"]').click();
    await expect(page.locator('[data-testid="engine-room-detail-grid"] tbody tr')).toHaveCount(1);
  });

  // Scenario: "Edit Record Engine Room — berhasil"
  test('klik Edit dari Detail, ubah field, klik Simpan, Detail menampilkan nilai baru', async ({ page }) => {
    await login(page, 'ertest-supervisor01', PASSWORD);
    await page.goto('/data/engine-room');
    await page.locator('.er-table__row', { hasText: 'ER-BROWSER-EDIT' }).click();
    await page.locator('[data-testid="edit-button"]').click();

    await page.locator('[data-testid="engine-room-id-input"]').fill('ER-BROWSER-EDIT-DONE');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => !url.pathname.endsWith('/edit'));
    await expect(page.locator('body')).toContainText('ER-BROWSER-EDIT-DONE');
  });

  // Scenario: "Record Tidak Ditemukan (mode edit)"
  test('navigasi ke id yang tidak valid, halaman menampilkan error', async ({ page }) => {
    await login(page, 'ertest-supervisor01', PASSWORD);
    await page.goto('/data/engine-room/00000000-0000-0000-0000-000000000000/edit');

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });
});
