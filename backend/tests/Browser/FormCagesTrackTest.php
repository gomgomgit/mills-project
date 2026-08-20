<?php

/**
 * FormCagesTrackTest (Browser/Playwright) — screen-024--form-cages-track-web /
 * usecase-024--form-cages-track-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/FormGradingTest.php (screen-023)'s conventions, with the
 * Cages Tipped Time grid (Time dropdown + per-cage checkboxes) layered on
 * top.
 *
 * WRITTEN BUT NOT RUN IN THIS SESSION — same environment constraint as
 * every other Browser/* spec in this codebase (no dev server/browser
 * available in this sandbox).
 *
 * Test data assumption: authenticated Supervisor session via /login, then
 * navigate to /data/cages-track/create (or /data/cages-track/{id}/edit).
 * Scenarios assume a Business Unit named "Mill A" exists with an active
 * Cages Track station and a Mills Setting row (Jumlah Cages = 10), and (for
 * the "tanpa station aktif" scenario) a second Business Unit "Mill Tanpa
 * Cages Track" exists with no active Cages Track station. The "Jumlah Kolom
 * Grid" scenario assumes a third Business Unit "Mill Kecil" exists with
 * Jumlah Cages = 8. Edit scenarios assume a pre-seeded Cages Track record
 * with Cages Track Number "CT-BROWSER-EDIT" exists under "Mill A".
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const CREATE_PATH = '/data/cages-track/create';
const BUSINESS_UNIT_NAME = 'Mill A';
const PASSWORD = 'Passw0rd!';

async function login(page, username, password) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.locator('#business_unit_id').selectOption({ label: BUSINESS_UNIT_NAME });
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
}

test.describe('Form Cages Track (Web)', () => {
  // Scenario: "Buat Record Cages Track Baru — berhasil"
  test('klik Tambah Data, isi form lengkap termasuk 1 baris Cages Tipped Time, klik Simpan, halaman Detail menampilkan record baru beserta grid detail', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/cages-track`);
    await page.locator('[data-testid="add-data-button"]').click();
    await page.waitForURL(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="business-unit-select"]').selectOption({ label: BUSINESS_UNIT_NAME });
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
    await page.goto(`${BASE_URL}/data/cages-track`);
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
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="business-unit-select"]').selectOption({ label: BUSINESS_UNIT_NAME });
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
  test('pilih BU dengan Jumlah Cages=8, isi Cages Tipped header dengan 15, tambah 1 baris, grid menampilkan 8 kolom checklist', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="business-unit-select"]').selectOption({ label: 'Mill Kecil' });
    await page.locator('[data-testid="cages-tipped-input"]').fill('15');
    await page.locator('[data-testid="add-row-button"]').click();

    await expect(page.locator('[data-testid="detail-cage-checklist-0"] input[type="checkbox"]')).toHaveCount(8);
  });

  // Scenario: "Time Tidak Bisa Duplikat Atau Mundur"
  test('pilih jam 7 pada baris pertama, tambah baris kedua, buka dropdown Time baris kedua, hanya jam 8 ke atas yang tersedia', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="business-unit-select"]').selectOption({ label: BUSINESS_UNIT_NAME });
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
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="business-unit-select"]').selectOption({ label: BUSINESS_UNIT_NAME });
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('.fc-field__error')).toContainText('Cages Track Number');
  });

  // Scenario: "Belum Ada Baris Cages Tipped Time Valid"
  test('isi header lengkap tanpa baris detail, klik Simpan, pesan error khusus muncul', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="business-unit-select"]').selectOption({ label: BUSINESS_UNIT_NAME });
    await page.locator('[data-testid="cages-track-number-input"]').fill('CT-NO-DETAIL');
    await page.locator('[data-testid="cages-out-input"]').fill('12');
    await page.locator('[data-testid="cages-tipped-input"]').fill('10');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="detail-error"]')).toBeVisible();
  });

  // Scenario: "Business Unit Tanpa Station Cages Track Aktif"
  test('pilih BU tanpa station cages-track, klik Simpan, error ditampilkan', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="business-unit-select"]').selectOption({ label: 'Mill Tanpa Cages Track' });
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
    await page.goto(`${BASE_URL}/data/cages-track/00000000-0000-0000-0000-000000000000/edit`);

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });
});
