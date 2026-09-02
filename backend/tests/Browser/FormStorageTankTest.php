<?php

/**
 * FormStorageTankTest (Browser/Playwright) —
 * screen-116--form-storage-tank-web / usecase-096--form-storage-tank-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/FormEffluentPlantTest.php's conventions — MINUS any
 * operational-target table assertion (this station has none).
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserStorageTankTest.php's docblock for the full rationale.
 *
 * Test data assumption: authenticated Supervisor session
 * (sttest-supervisor01 / Passw0rd!), a Production Line named
 * "PL Mill A" with an active Storage Tank station, a second Production
 * Line "PL Tanpa Storage Tank" with no active Storage Tank station, and
 * a pre-seeded Storage Tank record with Storage Tank ID
 * "ST-BROWSER-EDIT" under "PL Mill A".
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const CREATE_PATH = '/data/storage-tank/create';
const PRODUCTION_LINE_NAME = 'PL Mill A';
const PASSWORD = 'Passw0rd!';

async function login(page, username, password) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
}

test.describe('Form Storage Tank (Web)', () => {
  // Scenario: "Buat Record Storage Tank Baru — berhasil"
  test('klik Tambah Data, isi form lengkap termasuk 1 kolom bacaan, klik Simpan, halaman Detail menampilkan record baru', async ({ page }) => {
    await login(page, 'sttest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/storage-tank`);
    await page.locator('[data-testid="add-data-button"]').click();
    await page.waitForURL(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    const uniqueSuffix = Date.now();
    await page.locator('[data-testid="storage-tank-id-input"]').fill(`ST-BROWSER-${uniqueSuffix}`);
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('07:00');
    await page.locator('[data-testid="cpo-sounding-depth-0"]').fill('1200.5');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/storage-tank/') && !url.pathname.endsWith('/create'));
    await expect(page.locator('[data-testid="detail-storage-tank-id"]')).toContainText(`ST-BROWSER-${uniqueSuffix}`);
  });

  // Scenario: "Field Wajib Belum Lengkap"
  test('kosongkan field wajib, klik Simpan, error inline muncul', async ({ page }) => {
    await login(page, 'sttest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('.pf-field__error')).toContainText('Storage Tank ID');
  });

  // Scenario: "Belum Ada Baris Valid"
  test('isi header lengkap tanpa menambah baris apapun, klik Simpan, pesan error khusus muncul', async ({ page }) => {
    await login(page, 'sttest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="storage-tank-id-input"]').fill('ST-NO-DETAIL');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="detail-error"]')).toBeVisible();
  });

  // Scenario: "Production Line Tanpa Station Storage Tank Aktif"
  test('pilih Production Line tanpa station storage-tank, klik Simpan, error ditampilkan', async ({ page }) => {
    await login(page, 'sttest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: 'PL Tanpa Storage Tank' });
    await page.locator('[data-testid="storage-tank-id-input"]').fill('ST-NO-STATION');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('07:00');
    await page.locator('[data-testid="cpo-sounding-depth-0"]').fill('10');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="general-error"]')).toBeVisible();
  });

  // Scenario: "Tambah/Hapus Baris Dinamis dan Urutan Time-Slot"
  test('grid Storage Tank Detail dimulai kosong, bertambah satu baris tiap klik Tambah Baris, dan bisa dihapus', async ({ page }) => {
    await login(page, 'sttest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await expect(page.locator('[data-testid="storage-tank-detail-grid"] tbody tr')).toHaveCount(0);

    await page.locator('[data-testid="add-row-button"]').click();
    await expect(page.locator('[data-testid="storage-tank-detail-grid"] tbody tr')).toHaveCount(1);

    await page.locator('[data-testid="add-row-button"]').click();
    await expect(page.locator('[data-testid="storage-tank-detail-grid"] tbody tr')).toHaveCount(2);

    await page.locator('[data-testid="remove-row-button-0"]').click();
    await expect(page.locator('[data-testid="storage-tank-detail-grid"] tbody tr')).toHaveCount(1);
  });

  // Scenario: "Edit Record Storage Tank — berhasil"
  test('klik Edit dari Detail, ubah field, klik Simpan, Detail menampilkan nilai baru', async ({ page }) => {
    await login(page, 'sttest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/storage-tank`);
    await page.locator('.st-table__row', { hasText: 'ST-BROWSER-EDIT' }).click();
    await page.locator('[data-testid="edit-button"]').click();

    await page.locator('[data-testid="storage-tank-id-input"]').fill('ST-BROWSER-EDIT-DONE');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => !url.pathname.endsWith('/edit'));
    await expect(page.locator('body')).toContainText('ST-BROWSER-EDIT-DONE');
  });

  // Scenario: "Record Tidak Ditemukan (mode edit)"
  test('navigasi ke id yang tidak valid, halaman menampilkan error', async ({ page }) => {
    await login(page, 'sttest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/storage-tank/00000000-0000-0000-0000-000000000000/edit`);

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });
});
