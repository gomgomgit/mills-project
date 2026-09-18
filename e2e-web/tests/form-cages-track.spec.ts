/**
 * FormCagesTrackTest (Browser/Playwright) — screen-024--form-cages-track-web /
 * usecase-024--form-cages-track-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/FormGradingTest.php (screen-023)'s conventions, with the
 * Cages Tipped Time grid (Time dropdown + per-cage checkboxes) layered on
 * top.
 *
 * REWRITTEN: unlike FormWeighbridgeTest.php/FormGradingTest.php (whose
 * create forms still pick a Business Unit first, then a cascaded
 * Production Line), this screen's create form dropped the Business Unit
 * step entirely — resources/views/livewire/data/form-cages-track.blade.php
 * only renders `[data-testid="production-line-select"]` in create mode
 * (`@if (! $isEdit)`); Business Unit is derived server-side from the
 * chosen Production Line and only shown read-only in edit mode
 * (`[data-testid="business-unit-readonly"]`). Every
 * `[data-testid="business-unit-select"]` interaction below (which no
 * longer exists on this page at all) is replaced with
 * `[data-testid="production-line-select"]`, and the fixture names in the
 * doc comment below are now Production Line names rather than Business
 * Unit names.
 *
 * WRITTEN BUT NOT RUN IN THIS SESSION — same environment constraint as
 * every other Browser/* spec in this codebase (no dev server/browser
 * available in this sandbox).
 *
 * Test data assumption: authenticated Supervisor session via /login, then
 * navigate to /data/cages-track/create (or /data/cages-track/{id}/edit).
 * Scenarios assume a Production Line named "PL Mill A" exists (under any
 * Business Unit) with an active Cages Track station and a Mills Setting
 * row on its Business Unit (Jumlah Cages = 10), and (for the "tanpa
 * station aktif" scenario) a second Production Line "PL Tanpa Cages Track"
 * exists with no active Cages Track station. The "Jumlah Kolom Grid"
 * scenario assumes a third Production Line "PL Mill Kecil" exists whose
 * Business Unit has Jumlah Cages = 8. Edit scenarios assume a pre-seeded
 * Cages Track record with Cages Track Number "CT-BROWSER-EDIT" exists
 * under "PL Mill A".
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const CREATE_PATH = '/data/cages-track/create';
const PRODUCTION_LINE_NAME = 'PL Mill A';


test.describe('Form Cages Track (Web)', () => {
  // Scenario: "Buat Record Cages Track Baru — berhasil"
  test('klik Tambah Data, isi form lengkap termasuk 1 baris Cages Tipped Time, klik Simpan, halaman Detail menampilkan record baru beserta grid detail', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto('/data/cages-track');
    await page.locator('[data-testid="add-data-button"]').click();
    await page.waitForURL(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    const uniqueSuffix = Date.now();
    await page.locator('[data-testid="cages-track-number-input"]').fill(`CT-BROWSER-${uniqueSuffix}`);
    await page.locator('[data-testid="cages-out-input"]').fill('12');
    await page.locator('[data-testid="cages-tipped-input"]').fill('10');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="detail-hour-select-0"]').selectOption({ index: 1 });
    await page.locator('[data-testid="detail-cage-0-1"]').check();
    await page.locator('[data-testid="detail-cage-0-2"]').check();
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/cages-track/') && !url.pathname.endsWith('/create'));
    await expect(page.locator('[data-testid="detail-cages-track-number"]')).toContainText(`CT-BROWSER-${uniqueSuffix}`);
  });

  // Scenario: "Edit Record Cages Track — berhasil"
  test('klik Edit dari Detail, ubah field, klik Simpan, Detail menampilkan nilai baru', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto('/data/cages-track');
    await page.locator('.ct-table__row', { hasText: 'CT-BROWSER-EDIT' }).click();
    await page.locator('[data-testid="edit-button"]').click();

    await page.locator('[data-testid="cages-track-number-input"]').fill('CT-BROWSER-EDIT-DONE');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => !url.pathname.endsWith('/edit'));
    await expect(page.locator('body')).toContainText('CT-BROWSER-EDIT-DONE');
  });

  // Scenario: "Tanggal & Tippler Time Dapat Diedit Manual"
  test('ubah Tanggal & Tippler Time manual, isi field lain, klik Simpan, record tersimpan sesuai input user', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="date-input"]').fill('2020-01-01');
    const uniqueSuffix = Date.now();
    await page.locator('[data-testid="cages-track-number-input"]').fill(`CT-BROWSER-DT-${uniqueSuffix}`);
    await page.locator('[data-testid="cages-out-input"]').fill('12');
    await page.locator('[data-testid="cages-tipped-input"]').fill('10');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="detail-hour-select-0"]').selectOption({ index: 1 });
    await page.locator('[data-testid="detail-cage-0-1"]').check();
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/cages-track/') && !url.pathname.endsWith('/create'));
    await expect(page.locator('body')).toContainText('01 Jan 2020');
  });

  // Scenario: "Jumlah Kolom Grid Mengikuti Mills Setting, Bukan Cages Tipped Header"
  test('pilih Production Line dengan Jumlah Cages=8, isi Cages Tipped header dengan 15, tambah 1 baris, grid menampilkan 8 kolom checklist', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: 'PL Mill Kecil' });
    await page.locator('[data-testid="cages-tipped-input"]').fill('15');
    await page.locator('[data-testid="add-row-button"]').click();

    await expect(page.locator('[data-testid="detail-cage-checklist-0"] input[type="checkbox"]')).toHaveCount(8);
  });

  // Scenario: "Time Tidak Bisa Duplikat Atau Mundur"
  test('pilih jam 7 pada baris pertama, tambah baris kedua, buka dropdown Time baris kedua, hanya jam 8 ke atas yang tersedia', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="detail-hour-select-0"]').selectOption('7');
    await page.locator('[data-testid="add-row-button"]').click();

    const row2Options = await page.locator('[data-testid="detail-hour-select-1"] option').allInnerTexts();
    expect(row2Options).not.toContain('07:00');
    expect(row2Options).toContain('08:00');
  });

  // Scenario: "Field Wajib Belum Lengkap"
  test('kosongkan field wajib, klik Simpan, error inline muncul', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('.fc-field__error')).toContainText('Cages Track Number');
  });

  // Scenario: "Belum Ada Baris Cages Tipped Time Valid"
  test('isi header lengkap tanpa baris detail, klik Simpan, pesan error khusus muncul', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="cages-track-number-input"]').fill('CT-NO-DETAIL');
    await page.locator('[data-testid="cages-out-input"]').fill('12');
    await page.locator('[data-testid="cages-tipped-input"]').fill('10');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="detail-error"]')).toBeVisible();
  });

  // Scenario: "Business Unit Tanpa Station Cages Track Aktif"
  test('pilih Production Line tanpa station cages-track, klik Simpan, error ditampilkan', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: 'PL Tanpa Cages Track' });
    await page.locator('[data-testid="cages-track-number-input"]').fill('CT-NO-STATION');
    await page.locator('[data-testid="cages-out-input"]').fill('12');
    await page.locator('[data-testid="cages-tipped-input"]').fill('10');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="detail-hour-select-0"]').selectOption({ index: 1 });
    await page.locator('[data-testid="detail-cage-0-1"]').check();
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="general-error"]')).toBeVisible();
  });

  // Scenario: "Record Tidak Ditemukan (mode edit)"
  test('navigasi ke id yang tidak valid, halaman menampilkan error', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto('/data/cages-track/00000000-0000-0000-0000-000000000000/edit');

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });
});
