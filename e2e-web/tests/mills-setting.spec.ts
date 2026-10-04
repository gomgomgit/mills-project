/**
 * MillsSettingTest (Browser/Playwright) — screen-034--mills-setting /
 * usecase-034--mills-setting.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * tests/Browser/KelolaStationTest.php's convention (.php path containing
 * a Playwright TS spec body, per test_strategy.browser_test.tool).
 *
 * WRITTEN BUT NOT RUN IN THIS SESSION — same environment constraint as
 * every sibling Browser spec in this codebase (no dev server/browser
 * available here). Run later via `playwright test` from a project root
 * with @playwright/test installed.
 *
 * Fixture assumptions:
 *   - login business area picker: "Mill A" — stest-admin01 / Passw0rd!
 *     (role: admin), stest-mm01 / Passw0rd! (role: mill_management,
 *     scoped to "Mill A")
 *   - a business unit named "Mill Station Icon" with at least one
 *     station (e.g. "Weighbridge Icon Test") exists, for the icon-picker
 *     scenario
 *   - a business unit named "Mill Kosong" with NO stations exists, for
 *     the empty-state scenario
 * Adjust the USERNAME/BUSINESS_UNIT_NAME constants below to match
 * whatever seeder provisions the target environment.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { pickCombobox, watchProblems } from './support/page-health'

const MILL_SETTINGS_PATH = '/mill-settings';

// Pemilih Mill kini x-searchable-select (audit 2026-10-04 #15), bukan <select>.
async function pickMill(page, label: string) {
  await pickCombobox(page, page.locator('#selectedBusinessUnitId'), label)
}

async function gotoMillSettings(page) {
  await page.goto(MILL_SETTINGS_PATH);
}

test.describe('Mills Setting', () => {
  let problems: string[] = []
  test.beforeEach(({ page }) => {
    problems = watchProblems(page)
  })
  test.afterEach(() => {
    expect(problems).toEqual([])
  })

  test('Nama aplikasi kosong ditolak dengan pesan Indonesia (audit #13)', async ({ page }) => {
    await login(page, 'stest-admin01');
    await gotoMillSettings(page);
    await pickMill(page, 'Mill Setting Uji');
    await page.locator('#app_name').fill('');
    await page.locator('button.ms-button--primary[type="submit"]').click();
    await expect(page.getByText('Nama aplikasi wajib diisi.')).toBeVisible();
    await expect(page.locator('.ms-alert--success')).toHaveCount(0);
  });

  test('Logo berupa file teks bernama .png ditolak saat dipilih (audit #6)', async ({ page }) => {
    await login(page, 'stest-admin01');
    await gotoMillSettings(page);
    await pickMill(page, 'Mill Setting Uji');
    await page.locator('input[type="file"][wire\\:model="logo"]').setInputFiles({
      name: 'logo.png',
      mimeType: 'image/png',
      buffer: Buffer.from('ini bukan gambar'),
    });
    await expect(page.getByText('Logo harus berupa file gambar JPG atau PNG yang valid.')).toBeVisible();
  });

  test('Icon station dikelompokkan dengan kolom Production Line (audit #10)', async ({ page }) => {
    await login(page, 'stest-admin01');
    await gotoMillSettings(page);
    await pickMill(page, 'Mill Station Icon');
    await expect(page.locator('.ms-table__head')).toContainText('Production Line');
    await expect(page.locator('.ms-table__row', { hasText: 'Weighbridge Icon Test' })).toContainText('PL Station Icon');
  });

  // DIPERBARUI 2026-10-03. Tiga hal yang tidak pernah benar sejak spec ini
  // ditulis: (1) mill "Mill A" tidak ada di database mana pun — fixture-nya
  // kini "Mill Setting Uji" dari BrowserTestFixtureSeeder; (2) field
  // #jumlah_cages DIHAPUS dari layar ini pada 2026-08-20 (jumlah cages kini
  // dihitung dari machinery station Cages Track), jadi asersinya dibuang;
  // (3) `button[type="submit"]` cocok juga ke tombol Logout dan Kirim chatbot,
  // sehingga tombol Simpan disebut lewat kelasnya.
  test('Admin: pilih mill, ubah nama aplikasi, klik Simpan', async ({ page }) => {
    await login(page, 'stest-admin01');
    await gotoMillSettings(page);

    const appName = `Mill Baru ${Date.now()}`;
    await pickMill(page, 'Mill Setting Uji');
    await page.locator('#app_name').fill(appName);
    await page.locator('button.ms-button--primary[type="submit"]').click();

    await expect(page.locator('.ms-alert--success')).toBeVisible();
    await page.reload();
    await pickMill(page, 'Mill Setting Uji');
    await expect(page.locator('#app_name')).toHaveValue(appName);
  });

  test('Mill Management: navigasi ke Mills Setting langsung menampilkan mill sendiri, tanpa pemilih', async ({ page }) => {
    await login(page, 'stest-mm01');
    await gotoMillSettings(page);

    await expect(page.locator('#selectedBusinessUnitId')).toHaveCount(0);
    await expect(page.locator('#app_name')).toBeVisible();
  });

  test('Mill yang belum pernah diatur: form menampilkan nilai default tanpa error', async ({ page }) => {
    await login(page, 'stest-admin01');
    await gotoMillSettings(page);

    await pickMill(page, 'Mill Kosong');

    // #jumlah_cages (dulu default '1') dihapus dari layar 2026-08-20 — yang
    // tersisa untuk diasersi adalah form yang tampil tanpa galat.
    await expect(page.locator('.ms-alert--error')).toHaveCount(0);
    await expect(page.locator('#app_name')).toBeVisible();
  });

  // DILEWATI SEJAK 2026-10-03: field #jumlah_cages beserta validasinya
  // dihapus dari Mills Setting pada 2026-08-20 — jumlah cages kini dihitung
  // dari machinery station Cages Track (CagesTrackRecordService::
  // machineryCountForStation). Tidak ada lagi yang bisa diuji di layar ini.
  test.skip('Validasi jumlah cages: isi 0, klik Simpan, error ditampilkan', async ({ page }) => {
    await login(page, 'stest-admin01');
    await gotoMillSettings(page);

    await pickMill(page, 'Mill A');
    await page.locator('#jumlah_cages').fill('0');
    await page.locator('button[type="submit"]').click();

    await expect(page.locator('.ms-form-field__error')).toContainText('lebih dari 0');
  });

  test('Mill Management: navigasi paksa ke business_unit_id lain menampilkan status akses ditolak', async ({ page }) => {
    await login(page, 'stest-mm01');
    await gotoMillSettings(page);

    // No picker is rendered for Mill Management — this scenario is
    // exercised at the API/Livewire-component level instead (see
    // tests/Feature/Api/MillSettingTest.php and
    // tests/Feature/Livewire/MillsSettingTest.php), since there is no
    // in-page control a Mill Management user could use to select another
    // mill in the first place.
    await expect(page.locator('#selectedBusinessUnitId')).toHaveCount(0);
  });

  test('Icon station: pilih icon baru untuk salah satu station, preview diperbarui', async ({ page }) => {
    await login(page, 'stest-admin01');
    await gotoMillSettings(page);

    await pickMill(page, 'Mill Station Icon');
    const row = page.locator('.ms-table__row', { hasText: 'Weighbridge Icon Test' });
    // Pemilih icon kini x-searchable-select per baris (audit 2026-10-04 #15).
    const picker = row.getByRole('combobox');
    await pickCombobox(page, picker, 'Truck');

    await expect(page.locator('.ms-alert--success')).toBeVisible();
    await expect(picker).toHaveValue('Truck');
  });

  test('Belum ada station: bagian icon station menampilkan pesan kosong, bukan error', async ({ page }) => {
    await login(page, 'stest-admin01');
    await gotoMillSettings(page);

    await pickMill(page, 'Mill Kosong');

    await expect(page.locator('.ms-empty__title')).toContainText('Belum ada station terdaftar');
  });
});
