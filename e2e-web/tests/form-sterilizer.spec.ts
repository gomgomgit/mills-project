/**
 * FormSterilizerTest (Browser/Playwright) —
 * screen-126--form-sterilizer-web /
 * usecase-126--form-sterilizer-web.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserSterilizerTest.php's file-level docblock. This is the FINAL
 * station of this project.
 */

import { test, expect, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { removeOpenPeriodForForms, seedOpenPeriodForForms } from './support/period-fixture'

const FORM_CREATE_PATH = '/data/sterilizer/create';
const USERNAME = 'stertest-form01';


test.describe('Form Sterilizer', () => {
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
    periodFixturePage = await seedOpenPeriodForForms(browser, 'sterilizer')
  })

  test.afterAll(async () => {
    // Alasan yang sama dengan beforeAll di atas — pembersihan juga memanggil API
    // periode, dan 30 detik bawaan untuk sebuah hook pernah terlampaui di sini.
    test.setTimeout(120_000)
    await removeOpenPeriodForForms(periodFixturePage)
  })

  test('membuat record baru berhasil', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(FORM_CREATE_PATH);

    // Berdasarkan LABEL, bukan index 1: opsinya diurutkan menurut nama, dan
    // opsi pertama "BU Browser Test" adalah "Mill Machinery Group PL Baru" —
    // line milik fixture Kelola Machinery yang tidak memikul stasiun jenis ini,
    // sehingga Simpan ditolak "Production Line yang dipilih belum memiliki
    // station ... yang aktif." dan URL tidak pernah berpindah (timeout).
    // "PL Mill A" adalah line yang memikul stasiun aktif setiap jenis.
    await page.locator('[data-testid="production-line-select"]').selectOption({ label: 'PL Mill A' });
    await page.locator('[data-testid="sterilizer-id-input"]').fill('STR-BROWSER-001');
    await page.locator('[data-testid="date-input"]').fill('2026-08-31');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="detail-close-door-time-0"]').fill('07:00');
    await page.locator('[data-testid="detail-open-door-time-0"]').fill('08:10');
    await expect(page.locator('[data-testid="detail-duration-minutes-0"]')).toHaveText('70');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL(/\/data\/sterilizer\/[0-9a-f-]+$/);
  });

  test('menampilkan error inline saat field wajib kosong', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(FORM_CREATE_PATH);

    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="detail-error"]').or(page.locator('.sf-field__error'))).toBeVisible();
  });
});
