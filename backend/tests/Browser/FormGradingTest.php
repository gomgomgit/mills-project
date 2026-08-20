<?php

/**
 * FormGradingTest (Browser/Playwright) — screen-023--form-grading-web /
 * usecase-023--form-grading-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/FormWeighbridgeTest.php (screen-022)'s conventions.
 *
 * WRITTEN BUT NOT RUN IN THIS SESSION — same environment constraint as
 * every other Browser/* spec in this codebase (no dev server/browser
 * available in this sandbox).
 *
 * Test data assumption: authenticated Supervisor session via /login, then
 * navigate to /data/grading/create (or /data/grading/{id}/edit). Scenarios
 * assume a Business Unit named "Mill A" exists with an active Grading
 * station and at least one Weighbridge record to reference via WB Card No,
 * and (for the "tanpa station aktif" scenario) a second Business Unit
 * "Mill Tanpa Grading" exists with no active Grading station. Edit
 * scenarios assume a pre-seeded Grading record with Grading Number
 * "GR-BROWSER-EDIT" exists under "Mill A".
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const CREATE_PATH = '/data/grading/create';
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

test.describe('Form Grading (Web)', () => {
  // Scenario: "Buat Record Grading Baru — berhasil"
  test('klik Tambah Data, isi form lengkap termasuk 1 baris Grading Detail, klik Simpan, halaman Detail menampilkan record baru beserta grid detail', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/grading`);
    await page.locator('[data-testid="add-data-button"]').click();
    await page.waitForURL(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="business-unit-select"]').selectOption({ label: BUSINESS_UNIT_NAME });
    await page.locator('[data-testid="wb-card-no-select"]').selectOption({ index: 1 });
    const uniqueSuffix = Date.now();
    await page.locator('[data-testid="grading-number-input"]').fill(`GR-BROWSER-${uniqueSuffix}`);
    await page.locator('[data-testid="netto-input"]').fill('1000');
    await page.locator('[data-testid="quantity-input"]').fill('120');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="detail-parameter-select-0"]').selectOption({ index: 1 });
    await page.locator('[data-testid="detail-quantity-input-0"]').fill('30');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/grading/') && !url.pathname.endsWith('/create'));
    await expect(page.locator('[data-testid="detail-grading-number"]')).toContainText(`GR-BROWSER-${uniqueSuffix}`);
  });

  // Scenario: "Edit Record Grading — berhasil"
  test('klik Edit dari Detail, ubah field, klik Simpan, Detail menampilkan nilai baru', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/grading`);
    await page.locator('.gr-table__row', { hasText: 'GR-BROWSER-EDIT' }).click();
    await page.locator('[data-testid="edit-button"]').click();

    await page.locator('[data-testid="grading-number-input"]').fill('GR-BROWSER-EDIT-DONE');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => !url.pathname.endsWith('/edit'));
    await expect(page.locator('body')).toContainText('GR-BROWSER-EDIT-DONE');
  });

  // Scenario: "Tanggal Dapat Diedit Manual"
  test('ubah Tanggal manual, isi field lain, klik Simpan, record tersimpan sesuai input user', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="business-unit-select"]').selectOption({ label: BUSINESS_UNIT_NAME });
    await page.locator('[data-testid="date-input"]').fill('2020-01-01');
    await page.locator('[data-testid="wb-card-no-select"]').selectOption({ index: 1 });
    const uniqueSuffix = Date.now();
    await page.locator('[data-testid="grading-number-input"]').fill(`GR-BROWSER-DT-${uniqueSuffix}`);
    await page.locator('[data-testid="netto-input"]').fill('1000');
    await page.locator('[data-testid="quantity-input"]').fill('120');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="detail-parameter-select-0"]').selectOption({ index: 1 });
    await page.locator('[data-testid="detail-quantity-input-0"]').fill('30');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/grading/') && !url.pathname.endsWith('/create'));
    await expect(page.locator('body')).toContainText('01 Jan 2020');
  });

  // Scenario: "Quality Parameter Tidak Bisa Duplikat Antar Baris"
  test('tambah 2 baris, pilih Quality Parameter di baris pertama, parameter tsb tidak muncul di dropdown baris kedua', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="detail-parameter-select-0"]').selectOption({ index: 1 });
    const selectedValue = await page.locator('[data-testid="detail-parameter-select-0"]').inputValue();

    const row2Options = await page.locator('[data-testid="detail-parameter-select-1"] option').allInnerTexts();
    const row1SelectedLabel = await page.locator(`[data-testid="detail-parameter-select-0"] option[value="${selectedValue}"]`).innerText();
    expect(row2Options).not.toContain(row1SelectedLabel);
  });

  // Scenario: "Field Wajib Belum Lengkap"
  test('kosongkan field wajib, klik Simpan, error inline muncul', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="business-unit-select"]').selectOption({ label: BUSINESS_UNIT_NAME });
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('.fg-field__error')).toContainText('WB Card No');
  });

  // Scenario: "Belum Ada Baris Grading Detail Valid"
  test('isi header lengkap tanpa baris detail, klik Simpan, pesan error khusus grading detail muncul', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="business-unit-select"]').selectOption({ label: BUSINESS_UNIT_NAME });
    await page.locator('[data-testid="wb-card-no-select"]').selectOption({ index: 1 });
    await page.locator('[data-testid="grading-number-input"]').fill('GR-NO-DETAIL');
    await page.locator('[data-testid="netto-input"]').fill('1000');
    await page.locator('[data-testid="quantity-input"]').fill('120');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="detail-error"]')).toBeVisible();
  });

  // Scenario: "Business Unit Tanpa Station Grading Aktif"
  test('pilih Business Unit tanpa station grading, klik Simpan, error ditampilkan', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="business-unit-select"]').selectOption({ label: 'Mill Tanpa Grading' });
    await page.locator('[data-testid="grading-number-input"]').fill('GR-NO-STATION');
    await page.locator('[data-testid="license-plate-no-input"]').fill('B 1234 XY');
    await page.locator('[data-testid="estate-supplier-input"]').fill('Estate A');
    await page.locator('[data-testid="netto-input"]').fill('1000');
    await page.locator('[data-testid="quantity-input"]').fill('120');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="detail-parameter-select-0"]').selectOption({ index: 1 });
    await page.locator('[data-testid="detail-quantity-input-0"]').fill('30');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="general-error"]')).toBeVisible();
  });

  // Scenario: "Record Tidak Ditemukan (mode edit)"
  test('navigasi ke id yang tidak valid, halaman menampilkan error', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/grading/00000000-0000-0000-0000-000000000000/edit`);

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });
});
