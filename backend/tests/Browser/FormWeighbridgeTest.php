<?php

/**
 * FormWeighbridgeTest (Browser/Playwright) — screen-022--form-weighbridge-web /
 * usecase-022--form-weighbridge-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/KelolaStationTest.php's conventions (Playwright TS body in
 * a .php path, since test_strategy.browser_test.tool is Playwright, not
 * Laravel Dusk).
 *
 * WRITTEN BUT NOT RUN IN THIS SESSION — same environment constraint as
 * every other Browser/* spec in this codebase (no dev server/browser
 * available in this sandbox).
 *
 * Test data assumption: authenticated Supervisor session via /login, then
 * navigate to /data/weighbridge/create (or /data/weighbridge/{id}/edit).
 * Scenarios assume a Business Unit named "Mill A" exists with an active
 * Weighbridge station, and (for the "tanpa station aktif" scenario) a
 * second Business Unit "Mill Tanpa Weighbridge" exists with no active
 * Weighbridge station. Edit scenarios assume a pre-seeded Weighbridge
 * record with WB Card Number "WB-BROWSER-EDIT" exists under "Mill A".
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const CREATE_PATH = '/data/weighbridge/create';
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

test.describe('Form Weighbridge (Web)', () => {
  // Scenario: "Buat Record Weighbridge Baru - berhasil"
  test('klik Tambah Data, isi form, klik Simpan, halaman Detail menampilkan record baru', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/weighbridge`);
    await page.locator('[data-testid="add-data-button"]').click();
    await page.waitForURL(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="business-unit-select"]').selectOption({ label: BUSINESS_UNIT_NAME });
    const uniqueSuffix = Date.now();
    await page.locator('[data-testid="wb-card-number-input"]').fill(`WB-BROWSER-${uniqueSuffix}`);
    await page.locator('[data-testid="vehicle-number-input"]').fill('B 1234 XY');
    await page.locator('[data-testid="driver-name-input"]').fill('Budi');
    await page.locator('[data-testid="estate-supplier-input"]').fill('Estate A');
    await page.locator('[data-testid="gross-weight-input"]').fill('15000');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/weighbridge/') && !url.pathname.endsWith('/create'));
    await expect(page.locator('[data-testid="detail-weighbridge-type"]')).toContainText('Receive');
  });

  // Scenario: "Edit Record Weighbridge - berhasil"
  test('klik Edit dari Detail, ubah field, klik Simpan, Detail menampilkan nilai baru', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/weighbridge`);
    await page.locator('.wb-table__row', { hasText: 'WB-BROWSER-EDIT' }).click();
    await page.locator('[data-testid="edit-button"]').click();

    await page.locator('[data-testid="wb-card-number-input"]').fill('WB-BROWSER-EDIT-DONE');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => !url.pathname.endsWith('/edit'));
    await expect(page.locator('body')).toContainText('WB-BROWSER-EDIT-DONE');
  });

  // Scenario: "Tanggal & Waktu Dapat Diedit Manual"
  test('ubah field tanggal & waktu secara manual, klik Simpan, record tersimpan sesuai input user', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="business-unit-select"]').selectOption({ label: BUSINESS_UNIT_NAME });
    await page.locator('[data-testid="record-datetime-input"]').fill('2020-01-01T08:00');
    const uniqueSuffix = Date.now();
    await page.locator('[data-testid="wb-card-number-input"]').fill(`WB-BROWSER-DT-${uniqueSuffix}`);
    await page.locator('[data-testid="vehicle-number-input"]').fill('B 1234 XY');
    await page.locator('[data-testid="driver-name-input"]').fill('Budi');
    await page.locator('[data-testid="estate-supplier-input"]').fill('Estate A');
    await page.locator('[data-testid="gross-weight-input"]').fill('15000');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/weighbridge/') && !url.pathname.endsWith('/create'));
    await expect(page.locator('[data-testid="detail-record-datetime"]')).toContainText('01 Jan 2020');
  });

  // Scenario: "Ganti Tipe Setelah Field Terisi"
  test('isi field di Receive lalu tap Dispatch, field Tujuan Muatan muncul', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await expect(page.locator('[data-testid="destination-input"]')).toHaveCount(0);
    await page.locator('[data-testid="type-tab-dispatch"]').click();
    await expect(page.locator('[data-testid="destination-input"]')).toBeVisible();

    await page.locator('[data-testid="type-tab-receive"]').click();
    await expect(page.locator('[data-testid="destination-input"]')).toHaveCount(0);
  });

  // Scenario: "Field Wajib Belum Lengkap"
  test('kosongkan field wajib, klik Simpan, error inline muncul', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="business-unit-select"]').selectOption({ label: BUSINESS_UNIT_NAME });
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('.fw-field__error')).toContainText('WB Card Number');
  });

  // Scenario: "Business Unit Tanpa Station Weighbridge Aktif"
  test('pilih Business Unit tanpa station weighbridge, klik Simpan, error ditampilkan', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="business-unit-select"]').selectOption({ label: 'Mill Tanpa Weighbridge' });
    await page.locator('[data-testid="wb-card-number-input"]').fill('WB-NO-STATION');
    await page.locator('[data-testid="vehicle-number-input"]').fill('B 1234 XY');
    await page.locator('[data-testid="driver-name-input"]').fill('Budi');
    await page.locator('[data-testid="estate-supplier-input"]').fill('Estate A');
    await page.locator('[data-testid="gross-weight-input"]').fill('15000');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="general-error"]')).toBeVisible();
  });

  // Scenario: "Record Tidak Ditemukan (mode edit)"
  test('navigasi ke id yang tidak valid, halaman menampilkan error', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/weighbridge/00000000-0000-0000-0000-000000000000/edit`);

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });
});
