<?php

/**
 * DetailClarificationTest (Browser/Playwright) —
 * screen-109--detail-clarification-web / usecase-113--detail-clarification-web.
 *
 * Playwright spec, mirrors tests/Browser/DetailBoilerRoomTest.php's
 * conventions — MINUS any operational-target table assertion (this station
 * has none).
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserClarificationTest.php's docblock for the full rationale.
 *
 * Test data assumption: authenticated Supervisor session
 * (cltest-supervisor01 / Passw0rd!), a pre-seeded Clarification record
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

test.describe('Detail Clarification (Web)', () => {
  // Scenario: "berhasil"
  test('klik baris di Data Browser Clarification, halaman detail menampilkan seluruh field record', async ({ page }) => {
    await login(page, 'cltest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/clarification`);
    await page.locator('.cl-table__row').first().click();

    await page.waitForURL((url) => url.pathname.startsWith('/data/clarification/'));
    await expect(page.locator('[data-testid="detail-clarification-id"]')).toBeVisible();
    await expect(page.locator('[data-testid="clarification-detail-grid"]')).toBeVisible();
  });

  // Scenario: "Record Tidak Ditemukan"
  test('navigasi langsung ke id yang tidak valid, halaman menampilkan error', async ({ page }) => {
    await login(page, 'cltest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/clarification/00000000-0000-0000-0000-000000000000`);

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });

  // Scenario: "Edit"
  test('klik tombol Edit pada halaman detail, browser menampilkan Form Clarification mode ubah', async ({ page }) => {
    await login(page, 'cltest-supervisor01', PASSWORD);
    await page.goto(`${BASE_URL}/data/clarification`);
    await page.locator('.cl-table__row').first().click();
    await page.waitForURL((url) => url.pathname.startsWith('/data/clarification/'));

    await page.locator('[data-testid="edit-button"]').click();
    await page.waitForURL((url) => url.pathname.endsWith('/edit'));
  });
});
