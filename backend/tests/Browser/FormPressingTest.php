<?php

/**
 * FormPressingTest (Browser/Playwright) — screen-058--form-pressing-web /
 * usecase-058--form-pressing-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/FormThreshingTest.php's conventions — REVISED 2026-08-24
 * (entity-catalog v12): the Pressing Detail grid is now a dynamic
 * add-row/remove-row grid (the user explicitly rejected the original fixed
 * 24-row design), so this now exercises "Tambah Baris" + the Time-Slot
 * <select> exactly like FormThreshingTest.php's Threshing Detail grid.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * tests/Browser/DataBrowserPressingTest.php's docblock for the full
 * rationale.
 *
 * Test data assumption: authenticated Supervisor session
 * (presstest-supervisor01 / Passw0rd!), a Production Line named
 * "PL Mill A" with an active Pressing station, a second Production Line
 * "PL Tanpa Pressing" with no active Pressing station, and a pre-seeded
 * Pressing record with Presser ID "PR-BROWSER-EDIT" under "PL Mill A".
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const CREATE_PATH = '/data/pressing/create';
const PRODUCTION_LINE_NAME = 'PL Mill A';
const PASSWORD = 'Passw0rd!';

async function login(page, username, password) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
}

test.describe('Form Pressing (Web)', () => {
  // Scenario: "berhasil"
  test('klik Tambah Data, isi form lengkap termasuk 1 kolom bacaan, klik Simpan, halaman Detail menampilkan record baru', async ({ page }) => {
    await login(page, 'presstest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/pressing`);
    await page.locator('[data-testid="add-data-button"]').click();
    await page.waitForURL(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    const uniqueSuffix = Date.now();
    await page.locator('[data-testid="presser-id-input"]').fill(`PR-BROWSER-${uniqueSuffix}`);
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('07:00');
    await page.locator('[data-testid="digester-temp-0"]').fill('92');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/pressing/') && !url.pathname.endsWith('/create'));
    await expect(page.locator('[data-testid="detail-presser-id"]')).toContainText(`PR-BROWSER-${uniqueSuffix}`);
  });

  // Scenario: "Field Wajib Belum Lengkap"
  test('kosongkan field wajib, klik Simpan, error inline muncul', async ({ page }) => {
    await login(page, 'presstest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('.pf-field__error')).toContainText('Presser ID');
  });

  // Scenario: "Belum Ada Baris Terisi"
  test('isi header lengkap tanpa mengisi kolom bacaan apapun, klik Simpan, pesan error khusus muncul', async ({ page }) => {
    await login(page, 'presstest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="presser-id-input"]').fill('PR-NO-DETAIL');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="detail-error"]')).toBeVisible();
  });

  // Scenario: "Production Line Tanpa Station Pressing Aktif"
  test('pilih Production Line tanpa station pressing, klik Simpan, error ditampilkan', async ({ page }) => {
    await login(page, 'presstest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await page.locator('[data-testid="production-line-select"]').selectOption({ label: 'PL Tanpa Pressing' });
    await page.locator('[data-testid="presser-id-input"]').fill('PR-NO-STATION');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="time-slot-select-0"]').selectOption('07:00');
    await page.locator('[data-testid="digester-temp-0"]').fill('10');
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="general-error"]')).toBeVisible();
  });

  // Scenario: "Tambah/Hapus Baris Dinamis" — REVISED 2026-08-24
  // (entity-catalog v12): the grid starts empty and grows one row at a
  // time via "Tambah Baris", mirroring Threshing Detail exactly.
  test('grid Pressing Detail dimulai kosong, bertambah satu baris tiap klik Tambah Baris, dan bisa dihapus', async ({ page }) => {
    await login(page, 'presstest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}${CREATE_PATH}`);

    await expect(page.locator('[data-testid="pressing-detail-grid"] tbody tr')).toHaveCount(0);

    await page.locator('[data-testid="add-row-button"]').click();
    await expect(page.locator('[data-testid="pressing-detail-grid"] tbody tr')).toHaveCount(1);

    await page.locator('[data-testid="add-row-button"]').click();
    await expect(page.locator('[data-testid="pressing-detail-grid"] tbody tr')).toHaveCount(2);

    await page.locator('[data-testid="remove-row-button-0"]').click();
    await expect(page.locator('[data-testid="pressing-detail-grid"] tbody tr')).toHaveCount(1);
  });

  // Scenario: "Edit Record Pressing — berhasil"
  test('klik Edit dari Detail, ubah field, klik Simpan, Detail menampilkan nilai baru', async ({ page }) => {
    await login(page, 'presstest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/pressing`);
    await page.locator('.pr-table__row', { hasText: 'PR-BROWSER-EDIT' }).click();
    await page.locator('[data-testid="edit-button"]').click();

    await page.locator('[data-testid="presser-id-input"]').fill('PR-BROWSER-EDIT-DONE');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => !url.pathname.endsWith('/edit'));
    await expect(page.locator('body')).toContainText('PR-BROWSER-EDIT-DONE');
  });

  // Scenario: "Record Tidak Ditemukan (mode edit)"
  test('navigasi ke id yang tidak valid, halaman menampilkan error', async ({ page }) => {
    await login(page, 'presstest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/pressing/00000000-0000-0000-0000-000000000000/edit`);

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });
});
