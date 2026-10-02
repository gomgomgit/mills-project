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

import { test, expect, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { removeOpenPeriodForForms, seedOpenPeriodForForms } from './support/period-fixture'

const CREATE_PATH = '/data/weighbridge/create';
/**
 * 'BU Browser Test', BUKAN 'Mill A', sejak 2026-10-02.
 *
 * Tidak ada Business Unit bernama 'Mill A' di instance ini — dibaca langsung
 * dari tabel `business_units`, isinya: BU Browser Test, Business Unit A..D,
 * Mill Kode Duplikat. Jadi setiap selectOption({ label: 'Mill A' }) di bawah
 * menunggu opsi yang tidak akan pernah ada, lalu gagal sebagai timeout 30
 * detik tanpa menyebut sebabnya.
 *
 * Dan seandainya 'Mill A' ADA, ia tetap tidak akan muncul: pemiliknya
 * `stest-supervisor01` adalah Supervisor yang terikat ke satu mill, sehingga
 * dropdown-nya hanya memuat mill-nya sendiri (clampMillIdForActor). Nama ini
 * berasal dari masa sebelum pengikatan mill — nama fixture lama yang tidak
 * pernah dibuat siapa pun, bukan regresi.
 */
const BUSINESS_UNIT_NAME = 'BU Browser Test';


test.describe('Form Weighbridge (Web)', () => {
  /**
   * PRASYARAT PERIODE PELAPORAN, sejak 2026-10-02.
   *
   * Kunci periode (usecase-141) menolak 422 PERIOD_CLOSED setiap penulisan
   * record stasiun yang tidak dimuat sebuah periode TERBUKA. Spec ini menulis
   * record lewat form, jadi tanpa blok ini setiap skenario "Simpan berhasil"
   * gagal sebagai timeout — bukan sebagai pesan yang menyebut periode.
   *
   * Alasan lengkap, termasuk mengapa satu periode lebar dan mengapa ia dihapus
   * di afterAll alih-alih ditinggalkan: tests/support/period-fixture.ts.
   */
  let periodFixturePage: Page

  test.beforeAll(async ({ browser }) => {
    // 30 detik bawaan Playwright untuk sebuah hook TIDAK CUKUP, dan itu terukur:
    // `php artisan serve` melayani satu permintaan sekaligus, jadi login lewat UI
    // ditambah beberapa panggilan API periode melewatinya pada mesin yang sibuk —
    // 4 dari 9 spek pertama gagal di hook-nya sendiri sebelum batas ini dinaikkan.
    test.setTimeout(180_000)
    periodFixturePage = await seedOpenPeriodForForms(browser, 'weighbridge')
  })

  test.afterAll(async () => {
    // Alasan yang sama dengan beforeAll di atas — pembersihan juga memanggil API
    // periode, dan 30 detik bawaan untuk sebuah hook pernah terlampaui di sini.
    test.setTimeout(120_000)
    await removeOpenPeriodForForms(periodFixturePage)
  })

  // Scenario: "Buat Record Weighbridge Baru - berhasil"
  test('klik Tambah Data, isi form, klik Simpan, halaman Detail menampilkan record baru', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto('/data/weighbridge');
    await page.locator('[data-testid="add-data-button"]').click();
    await page.waitForURL(CREATE_PATH);

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
    await page.goto('/data/weighbridge');
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
    await page.goto(CREATE_PATH);

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
    await page.goto(CREATE_PATH);

    await expect(page.locator('[data-testid="destination-input"]')).toHaveCount(0);
    await page.locator('[data-testid="type-tab-dispatch"]').click();
    await expect(page.locator('[data-testid="destination-input"]')).toBeVisible();

    await page.locator('[data-testid="type-tab-receive"]').click();
    await expect(page.locator('[data-testid="destination-input"]')).toHaveCount(0);
  });

  // Scenario: "Field Wajib Belum Lengkap"
  test('kosongkan field wajib, klik Simpan, error inline muncul', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    await page.locator('[data-testid="business-unit-select"]').selectOption({ label: BUSINESS_UNIT_NAME });
    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('.fw-field__error')).toContainText('WB Card Number');
  });

  // Scenario: "Business Unit Tanpa Station Weighbridge Aktif"
  test('pilih Business Unit tanpa station weighbridge, klik Simpan, error ditampilkan', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

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
    await page.goto('/data/weighbridge/00000000-0000-0000-0000-000000000000/edit');

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });
});
