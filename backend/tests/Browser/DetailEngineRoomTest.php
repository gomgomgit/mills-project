<?php

/**
 * DetailEngineRoomTest (Browser/Playwright) —
 * screen-107--detail-engine-room-web / usecase-101--detail-engine-room-web.
 *
 * Playwright spec, mirrors tests/Browser/DetailStorageTankTest.php's
 * conventions — MINUS any operational-target table assertion (this station
 * has none).
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserEngineRoomTest.php's docblock for the full rationale.
 *
 * Test data assumption: authenticated Supervisor session
 * (ertest-supervisor01 / Passw0rd!), a pre-seeded Engine Room record
 * reachable from the Data Browser's first row.
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8000';
const LOGIN_PATH = '/login';
const PASSWORD = 'Passw0rd!';

async function login(page, username, password) {
  await page.goto(`${BASE_URL}${LOGIN_PATH}`);
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.startsWith(LOGIN_PATH));
}

test.describe('Detail Engine Room (Web)', () => {
  // Scenario: "berhasil"
  test('klik baris di Data Browser Engine Room, halaman detail menampilkan seluruh field record', async ({ page }) => {
    await login(page, 'ertest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/engine-room`);
    await page.locator('.er-table__row').first().click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/engine-room/'));
    await expect(page.locator('[data-testid="detail-engine-room-id"]')).toBeVisible();
    await expect(page.locator('[data-testid="engine-room-detail-grid"]')).toBeVisible();
  });

  // Scenario: "Record Tidak Ditemukan"
  test('navigasi langsung ke id yang tidak valid, halaman menampilkan error', async ({ page }) => {
    await login(page, 'ertest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/engine-room/00000000-0000-0000-0000-000000000000`);

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });

  // Scenario: "Edit"
  test('klik tombol Edit pada halaman detail, browser menampilkan Form Engine Room mode ubah', async ({ page }) => {
    await login(page, 'ertest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/engine-room`);
    await page.locator('.er-table__row').first().click();
    await page.waitForURL((url) => url.pathname.startsWith('/data/engine-room/'));

    await page.locator('[data-testid="edit-button"]').click();
    await page.waitForURL((url) => url.pathname.endsWith('/edit'));
  });
});
