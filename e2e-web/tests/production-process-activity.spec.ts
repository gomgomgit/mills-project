/**
 * ProductionProcessActivityTest (Browser/Playwright) —
 * screen-035--production-process-activity-web /
 * usecase-035--production-process-activity-web.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/DashboardHomeTest.php's convention (.php path containing a
 * Playwright TS spec body).
 *
 * WRITTEN BUT NOT RUN IN THIS SESSION — same environment constraint as
 * every sibling Browser spec in this codebase (no dev server/browser
 * available here). Run later via `playwright test` from a project root
 * with @playwright/test installed.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'

const PPA_PATH = '/production-process-activity';

test.describe('Production Process Activity (screen-035)', () => {
  test('Pilih Stasiun (Web) — success: clicking the Weighbridge tile navigates to its Data Browser', async ({ page }) => {
    await login(page, 'supervisor01');
    await page.goto(PPA_PATH);

    await page.getByRole('link', { name: /Weighbridge/i }).click();

    await page.waitForURL('/data/weighbridge');
    await expect(page).toHaveURL('/data/weighbridge');
  });

  // Repurposed 2026-09-01: Sterilizer (the last placeholder) was promoted
  // to a fully active tile — 0 placeholders remain anywhere on the grid,
  // so there is nothing left to click-disabled. This now asserts the
  // negative: clicking the (now active) Sterilizer tile navigates normally,
  // and no "Belum tersedia" placeholder text renders anywhere.
  test('Pilih Stasiun (Web) — Sterilizer is now active: clicking it navigates to its Data Browser', async ({ page }) => {
    await login(page, 'supervisor01');
    await page.goto(PPA_PATH);

    await expect(page.getByText('Belum tersedia')).toHaveCount(0);

    await page.getByRole('link', { name: /Sterilizer/i }).click();

    await page.waitForURL('/data/sterilizer');
    await expect(page).toHaveURL('/data/sterilizer');
  });

  // ───────────────────────────────────────────────────────────────────────
  // PAPAN STATUS (2026-10-07) — halaman ini berhenti menjadi peluncur statis
  //
  // ASERSI INVARIAN, BUKAN ANGKA YANG DIPAKU. Basis data e2e dipakai bersama
  // 87 spec dan BrowserTestFixtureSeeder dijalankan ulang di setiap globalSetup,
  // jadi berapa stasiun yang punya input "hari ini" berubah setiap kali
  // fixture atau tanggal berubah. Angka yang dipaku di sini akan gagal karena
  // sebab yang tidak ada hubungannya dengan produknya — dan test seperti itu
  // yang akhirnya diabaikan lalu dihapus. Yang diuji: hubungan antar angka
  // yang dibaca DARI DOM, dan karenanya benar untuk data apa pun.
  // ───────────────────────────────────────────────────────────────────────

  test('kepala halaman dan keadaan tile saling konsisten, dihitung dari DOM', async ({ page }) => {
    await login(page, 'supervisor01');
    await page.goto(PPA_PATH);

    const headline = page.getByTestId('ppa-headline');
    await expect(headline).toBeVisible();

    const text = ((await headline.textContent()) ?? '').trim();
    const match = text.match(/^(\d+) dari (\d+) stasiun sudah ada input hari ini$/);
    expect(match, `bentuk kepala halaman tak terduga: "${text}"`).not.toBeNull();

    const withInput = Number(match![1]);
    const activeTotal = Number(match![2]);
    expect(withInput).toBeLessThanOrEqual(activeTotal);

    // Tile ber-keadaan "sudah ada input" harus TEPAT sebanyak angka pertama
    // di kepala halaman. Inilah invariannya: dua tempat menghitung hal yang
    // sama, dan kalau salah satu salah keduanya tidak akan cocok.
    const grid = page.getByTestId('station-grid');
    await expect(grid).toBeVisible();
    await expect(grid.locator('.station-tile')).toHaveCount(18);

    expect(await grid.locator('.station-tile.is-done').count()).toBe(withInput);
    // Dan stasiun aktif = sudah-ada-input + belum-ada-input.
    expect(await grid.locator('.station-tile.is-pending').count()).toBe(activeTotal - withInput);
  });

  test('tile yang belum ada input hari ini MENYATAKANNYA dan tidak pernah mencetak angka 0', async ({ page }) => {
    await login(page, 'supervisor01');
    await page.goto(PPA_PATH);

    const grid = page.getByTestId('station-grid');
    await expect(grid).toBeVisible();

    const pending = grid.locator('.station-tile.is-pending');
    const pendingCount = await pending.count();

    // Fixture e2e tidak menjamin ADA tile pending, jadi cabangnya dibaca dari
    // DOM alih-alih diandaikan — tetapi bila ada, isinya wajib benar.
    if (pendingCount > 0) {
      const texts = await pending.allInnerTexts();

      for (const tileText of texts) {
        expect(tileText).toContain('Belum ada input hari ini');
        // Aturan yang berlaku di seluruh laporan proyek ini: 0 yang dicetak
        // sebagai angka terbaca sebagai nol YANG TERUKUR.
        expect(tileText).not.toMatch(/\b0 record hari ini\b/);
      }
    }

    // Tile yang sudah ada input mencetak jumlahnya, bukan pernyataan.
    const done = grid.locator('.station-tile.is-done');

    if ((await done.count()) > 0) {
      for (const tileText of await done.allInnerTexts()) {
        expect(tileText).toMatch(/\d+ record hari ini/);
        expect(tileText).not.toContain('Belum ada input hari ini');
      }
    }
  });

  test('keterangan DUA JENDELA WAKTU ada — tanpanya pasangan angkanya terbaca seperti bug', async ({ page }) => {
    await login(page, 'supervisor01');
    await page.goto(PPA_PATH);

    const legend = page.getByTestId('ppa-legend');
    await expect(legend).toBeVisible();

    // "0 hari ini" berdampingan dengan "input terakhir 3 menit lalu" hanya
    // masuk akal kalau pembacanya diberi tahu bahwa yang kedua tidak
    // dibatasi tanggal.
    await expect(legend).toContainText('tanpa dibatasi tanggal');

    // Dan cakupan mill dinyatakan: supervisor01 terikat satu mill.
    await expect(page.getByTestId('ppa-scope')).toHaveText('Mill akun Anda');
  });

  test('urutan tile mengikuti urutan mobile, bukan sort_order master', async ({ page }) => {
    await login(page, 'supervisor01');
    await page.goto(PPA_PATH);

    const grid = page.getByTestId('station-grid');
    await expect(grid.locator('.station-tile')).toHaveCount(18);

    const codes = await grid.locator('.station-tile').evaluateAll((nodes) =>
      nodes.map((node) => (node.getAttribute('data-testid') ?? '').replace('station-tile-', '')),
    );

    // Dua posisi pertama cukup untuk membedakan kedua urutan: urutan mobile
    // mulai weighbridge, pressing; station_types.sort_order mulai
    // weighbridge, grading. Memeriksa keduanya menangkap pengurutan ulang
    // yang tidak disengaja tanpa memaku ke-18 kodenya di dua tempat.
    expect(codes[0]).toBe('weighbridge');
    expect(codes[1]).toBe('pressing');
    expect(codes).toHaveLength(18);
    expect(new Set(codes).size).toBe(18);
  });
});
