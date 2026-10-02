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
import { fillAndKeep, selectLive } from './support/livewire'

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

/** Satu-satunya line mill ini yang memikul stasiun setiap jenis. */
const PRODUCTION_LINE_NAME = 'PL Mill A';


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

    // Tunggu putaran Livewire-nya: wire:model.live, dan re-render yang mendarat
    // belakangan menghapus apa yang sudah diketik di bawahnya.
    await selectLive(page, 'business-unit-select', { label: BUSINESS_UNIT_NAME });
    // Production Line WAJIB, dan spec ini lahir sebelum ia ada. Sejak 2026-08-20
    // stasiun di-resolve dari production_line_id, bukan dari business_unit_id
    // (entity-catalog v9: Production Line masuk ke hierarki antara Business Unit
    // dan Station). Tanpa memilihnya, resolveActiveStationForActor(null, ...)
    // mengembalikan null dan penyimpanan ditolak dengan "Business Unit yang
    // dipilih belum memiliki station ... yang aktif" — pesan yang masih berkata
    // "Business Unit" padahal yang kurang adalah Production Line, dan itulah yang
    // membuat sebabnya sulit terbaca. Opsinya baru terisi setelah putaran BU di
    // atas selesai, karena itu urutannya tidak boleh dibalik.
    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    const uniqueSuffix = Date.now();
    await fillAndKeep(page, 'wb-card-number-input', `WB-BROWSER-${uniqueSuffix}`);
    await fillAndKeep(page, 'vehicle-number-input', 'B 1234 XY');
    await fillAndKeep(page, 'driver-name-input', 'Budi');
    await fillAndKeep(page, 'estate-supplier-input', 'Estate A');
    await fillAndKeep(page, 'gross-weight-input', '15000');
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

    await fillAndKeep(page, 'wb-card-number-input', 'WB-BROWSER-EDIT-DONE');
    await page.locator('[data-testid="save-button"]').click();

    await page.waitForURL((url) => !url.pathname.endsWith('/edit'));
    await expect(page.locator('body')).toContainText('WB-BROWSER-EDIT-DONE');
  });

  // Scenario: "Tanggal & Waktu Dapat Diedit Manual"
  test('ubah field tanggal & waktu secara manual, klik Simpan, record tersimpan sesuai input user', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    // Tunggu putaran Livewire-nya: wire:model.live, dan re-render yang mendarat
    // belakangan menghapus apa yang sudah diketik di bawahnya.
    await selectLive(page, 'business-unit-select', { label: BUSINESS_UNIT_NAME });
    // Production Line WAJIB, dan spec ini lahir sebelum ia ada. Sejak 2026-08-20
    // stasiun di-resolve dari production_line_id, bukan dari business_unit_id
    // (entity-catalog v9: Production Line masuk ke hierarki antara Business Unit
    // dan Station). Tanpa memilihnya, resolveActiveStationForActor(null, ...)
    // mengembalikan null dan penyimpanan ditolak dengan "Business Unit yang
    // dipilih belum memiliki station ... yang aktif" — pesan yang masih berkata
    // "Business Unit" padahal yang kurang adalah Production Line, dan itulah yang
    // membuat sebabnya sulit terbaca. Opsinya baru terisi setelah putaran BU di
    // atas selesai, karena itu urutannya tidak boleh dibalik.
    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="record-datetime-input"]').fill('2020-01-01T08:00');
    const uniqueSuffix = Date.now();
    await fillAndKeep(page, 'wb-card-number-input', `WB-BROWSER-DT-${uniqueSuffix}`);
    await fillAndKeep(page, 'vehicle-number-input', 'B 1234 XY');
    await fillAndKeep(page, 'driver-name-input', 'Budi');
    await fillAndKeep(page, 'estate-supplier-input', 'Estate A');
    await fillAndKeep(page, 'gross-weight-input', '15000');
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

    // Tunggu putaran Livewire-nya: wire:model.live, dan re-render yang mendarat
    // belakangan menghapus apa yang sudah diketik di bawahnya.
    await selectLive(page, 'business-unit-select', { label: BUSINESS_UNIT_NAME });
    // Production Line WAJIB, dan spec ini lahir sebelum ia ada. Sejak 2026-08-20
    // stasiun di-resolve dari production_line_id, bukan dari business_unit_id
    // (entity-catalog v9: Production Line masuk ke hierarki antara Business Unit
    // dan Station). Tanpa memilihnya, resolveActiveStationForActor(null, ...)
    // mengembalikan null dan penyimpanan ditolak dengan "Business Unit yang
    // dipilih belum memiliki station ... yang aktif" — pesan yang masih berkata
    // "Business Unit" padahal yang kurang adalah Production Line, dan itulah yang
    // membuat sebabnya sulit terbaca. Opsinya baru terisi setelah putaran BU di
    // atas selesai, karena itu urutannya tidak boleh dibalik.
    await page.locator('[data-testid="production-line-select"]').selectOption({ label: PRODUCTION_LINE_NAME });
    await page.locator('[data-testid="save-button"]').click();

    // DISARING KE SATU SPAN, sejak 2026-10-02. Mengosongkan form memunculkan
    // SEBERAPA PUN galat inline sekaligus (beberapa di layar ini), dan
    // `expect(locator)` Playwright berjalan dalam strict mode: locator yang
    // cocok ke lebih dari satu elemen DITOLAK, bukan dicocokkan ke salah
    // satunya. Jadi asersi lama gagal sebagai "strict mode violation" —
    // bukan karena pesannya tidak ada, melainkan karena ada yang lain di
    // sebelahnya. Menyaring ke span yang memuat teksnya tetap menuntut
    // pesan itu benar-benar muncul.
    await expect(page.locator('.fw-field__error').filter({ hasText: 'WB Card Number' })).toBeVisible();
  });

  // Scenario: "Business Unit Tanpa Station Weighbridge Aktif"
  // DITULIS ULANG 2026-10-02 UNTUK MENGASERSI PERILAKU YANG NYATA. Versi lama
  // memilih Business Unit 'Mill Tanpa Weighbridge' lalu mengharap `general-error`
  // muncul setelah Simpan. Dua hal membuatnya tidak pernah bisa terjadi:
  //
  //   1. Tidak ada Business Unit bernama 'Mill Tanpa Weighbridge' di instance ini
  //      (dibaca langsung dari tabel `business_units`).
  //   2. Dan seandainya ada, ia TETAP tidak akan muncul: `stest-supervisor01`
  //      adalah Supervisor yang terikat satu mill, dan
  //      ScopesToActorMill::businessUnitsForActor() membatasi daftarnya ke
  //      `whereKey($user->business_unit_id)` untuk setiap peran selain Admin.
  //
  // Jadi produk menutup jalannya LEBIH RAPAT daripada yang diasersi test lama:
  // mill lain tidak pernah bisa dipilih, bukan ditolak setelah dicoba. Itulah
  // yang diasersi sekarang — satu-satunya mill yang ditawarkan adalah mill si
  // aktor sendiri, dan tidak ada yang lain.
  test('Supervisor terikat mill hanya ditawari mill-nya sendiri, bukan mill tanpa station weighbridge', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto(CREATE_PATH);

    const options = page.locator('[data-testid="business-unit-select"] option');

    // Placeholder "Pilih Business Unit" + tepat satu mill, tidak lebih.
    await expect(options).toHaveCount(2);
    await expect(options.filter({ hasText: BUSINESS_UNIT_NAME })).toHaveCount(1);
    await expect(options.filter({ hasText: 'Mill Tanpa Weighbridge' })).toHaveCount(0);

    // Mill mana pun selain miliknya tidak dapat dipilih sama sekali — bukan
    // dipilih lalu ditolak.
    await expect(
      options.filter({ hasText: 'Mill Kode Duplikat' }),
      'mill lain tidak boleh ditawarkan kepada Supervisor yang terikat mill',
    ).toHaveCount(0);
  });

  // Scenario: "Record Tidak Ditemukan (mode edit)"
  test('navigasi ke id yang tidak valid, halaman menampilkan error', async ({ page }) => {
    await login(page, 'stest-supervisor01', PASSWORD);
    await page.goto('/data/weighbridge/00000000-0000-0000-0000-000000000000/edit');

    await expect(page.locator('[data-testid="record-not-found"]')).toBeVisible();
  });
});
