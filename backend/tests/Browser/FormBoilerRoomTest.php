<?php

/**
 * FormBoilerRoomTest (Browser/Playwright) —
 * screen-118--form-boiler-room-web / usecase-108--form-boiler-room-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/FormEngineRoomTest.php's conventions — MINUS any
 * operational-target table assertion (this station has none).
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserBoilerRoomTest.php's docblock for the full rationale.
 *
 * Test data assumption: authenticated Supervisor session
 * (brtest-supervisor01 / Passw0rd!), a Production Line named
 * "PL Mill A" with an active Boiler Room station, a second Production
 * Line "PL Tanpa Boiler Room" with no active Boiler Room station, and
 * a pre-seeded Boiler Room record with Boiler Room ID
 * "BR-BROWSER-EDIT" under "PL Mill A".
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const CREATE_PATH = '/data/boiler-room/create';
const PRODUCTION_LINE_NAME = 'PL Mill A';
const PASSWORD = 'Passw0rd!';

async function login(page, username, password) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
}

test.describe('Form Boiler Room (Web)', () => {
  // Scenario: "Buat Record Boiler Room Baru — berhasil"
  test('klik Tambah Data, isi form lengkap termasuk 1 kolom bacaan, klik Simpan, halaman Detail menampilkan record baru', async ({ page }) => {
    await login(page, 'brtest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/boiler-room`);
    await page.locator('[data-testid="add-data-button"]').click();
    await page.waitForURL(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    const uniqueSuffix = Date.now();
    await page.locator('[data-testid="boiler-room-id-input"]').fill(`BR-BROWSER-${uniqueSuffix}`);
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('07:00');
    await page.locator('[data-testid="steam-pressure-0"]').fill('12.5');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/boiler-room/') && !url.pathname.endsWith('/create'));
    await expect(page.locator('[data-testid="detail-boiler-room-id"]')).toContainText(`BR-BROWSER-${uniqueSuffix}`);
  });

  // Scenario: "Field Wajib Belum Lengkap"
  test('kosongkan field wajib, klik Simpan, error inline muncul', async ({ page }) => {
    await login(page, 'brtest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('.pf-field__error')).toContainText('Boiler Room ID');
  });

  // Scenario: "Belum Ada Baris Valid"
  test('isi header lengkap tanpa menambah baris apapun, klik Simpan, pesan error khusus muncul', async ({ page }) => {
    await login(page, 'brtest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="boiler-room-id-input"]').fill('BR-NO-DETAIL');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="detail-error"]')).toBeVisible();
  });

  // Scenario: "Production Line Tanpa Station Boiler Room Aktif"
  test('pilih Production Line tanpa station boiler-room aktif, klik Simpan, error ditampilkan', async ({ page }) => {
    await login(page, 'brtest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: 'PL Tanpa Boiler Room' });
    await page.locator('[data-testid="boiler-room-id-input"]').fill('BR-NO-STATION');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('07:00');
    await page.locator('[data-testid="steam-pressure-0"]').fill('10');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="general-error"]')).toBeVisible();
  });

  // Scenario: "Tambah/Hapus Baris Dinamis dan Urutan Time-Slot"
  test('grid Boiler Room Detail dimulai kosong, bertambah satu baris tiap klik Tambah Baris, dan bisa dihapus', async ({ page }) => {
    await login(page, 'brtest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await expect(page.locator('[data-testid="boiler-room-detail-grid"] tbody tr')).toHaveCount(0);

    await page.locator('[data-testid="add-row-button"]').click();
    await expect(page.locator('[data-testid="boiler-room-detail-grid"] tbody tr')).toHaveCount(1);

    await page.locator('[data-testid="add-row-button"]').click();
    await expect(page.locator('[data-testid="boiler-room-detail-grid"] tbody tr')).toHaveCount(2);

    await page.locator('[data-testid="remove-row-button-0"]').click();
    await expect(page.locator('[data-testid="boiler-room-detail-grid"] tbody tr')).toHaveCount(1);
  });

  // Scenario: "Edit Record Boiler Room — berhasil"
  test('klik Edit dari Detail, ubah field, klik Simpan, Detail menampilkan nilai baru', async ({ page }) => {
    await login(page, 'brtest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/boiler-room`);
    await page.locator('.br-table__row', { hasText: 'BR-BROWSER-EDIT' }).click();
    await page.locator('[data-testid="edit-button"]').click();

    await page.locator('[data-testid="boiler-room-id-input"]').fill('BR-BROWSER-EDIT-DONE');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => !url.pathname.endsWith('/edit'));
    await expect(page.locator('body')).toContainText('BR-BROWSER-EDIT-DONE');
  });

  // Scenario: "Record Tidak Ditemukan (mode edit)"
  test('navigasi ke id yang tidak valid, halaman menampilkan error', async ({ page }) => {
    await login(page, 'brtest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/boiler-room/00000000-0000-0000-0000-000000000000/edit`);

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });
});
