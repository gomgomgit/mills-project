/**
 * FormKernelDispatchTest (Browser/Playwright) —
 * screen-113--form-kernel-dispatch-web /
 * usecase-078--form-kernel-dispatch-web.
 *
 * GENERATED BUT NOT EXECUTED IN THIS ENVIRONMENT — see
 * DataBrowserKernelDispatchTest.php's file-level docblock.
 */

import { test, expect, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { removeOpenPeriodForForms, seedOpenPeriodForForms } from './support/period-fixture'

const FORM_CREATE_PATH = '/data/kernel-dispatch/create';
const USERNAME = 'kdtest-form01';


test.describe('Form Kernel Dispatch', () => {
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
    periodFixturePage = await seedOpenPeriodForForms(browser, 'kernel-dispatch')
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

    await page.locator('[data-testid="production-line-select"]').selectOption({ index: 1 });
    await page.locator('[data-testid="kernel-dispatch-id-input"]').fill('KD-BROWSER-001');
    await page.locator('[data-testid="date-input"]').fill('2026-08-31');
    await page.locator('[data-testid="add-row-button"]').click();
    await page.locator('[data-testid="detail-event-date-0"]').fill('2026-08-31');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL(/\/data\/kernel-dispatch\/[0-9a-f-]+$/);
  });

  test('menampilkan error inline saat field wajib kosong', async ({ page }) => {
    await login(page, USERNAME, PASSWORD);
    await page.goto(FORM_CREATE_PATH);

    await page.locator('[data-testid="save-button"]').click();

    await expect(page.locator('[data-testid="detail-error"]').or(page.locator('.kf-field__error'))).toBeVisible();
  });
});
