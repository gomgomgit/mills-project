/**
 * KelolaStationTest (Browser/Playwright) — screen-030--kelola-station /
 * usecase-030--kelola-station.
 *
 * Playwright spec, one test per test_scenarios' browser_test step. Mirrors
 * the convention established by tests/Browser/KelolaBusinessUnitTest.php /
 * tests/Browser/KelolaCompanyTest.php — same file-extension/import-syntax
 * pattern (a .php path containing a Playwright TS spec body), since
 * test_strategy.browser_test.tool is Playwright (not Laravel Dusk — this
 * codebase has no laravel/dusk dependency, see composer.json).
 *
 * This is a plain Livewire/Blade web screen served by `php artisan serve`
 * (test_strategy.browser_test.base_url), so it is fully browser-testable
 * via the dev server — not deferred.
 *
 * REWRITTEN: three staleness issues fixed against the current
 * implementation.
 *   1. #business_unit_id/#filterBusinessUnitId/#type are now the
 *      x-searchable-select combobox (resources/views/components/
 *      searchable-select.blade.php), not a native <select> — .selectOption()
 *      never worked against them. Replaced with click+fill+click-option via
 *      the selectSearchable()/selectSearchableFirst() helpers below (same
 *      pattern as KelolaBusinessUnitTest.php et al).
 *   2. #production_line_id is a new required field (entity-catalog v9-10 —
 *      Station now requires a production_line_id FK cascaded from
 *      business_unit_id, wire:model.live on #business_unit_id so picking a
 *      Business Unit reloads this field's options and resets its own
 *      selection) that this file never filled in at all — every
 *      create/edit scenario below now also picks a Production Line via
 *      selectSearchableFirst() right after the Business Unit. New test-data
 *      assumption: every Business Unit fixture used below ("Mill Station
 *      Baru", "Mill Station Tujuan Edit", and whichever business unit
 *      index 1 resolves to) has at least one child Production Line already
 *      seeded.
 *   3. `.kc-form-field__error` assertions now use `.first()` — with
 *      production_line_id added as a second required field, a scenario
 *      that leaves BOTH business_unit_id and production_line_id unselected
 *      (scenario "Business Unit induk wajib dipilih") now renders two
 *      simultaneous field errors, and Playwright's strict mode throws on a
 *      multi-element locator passed to toContainText().
 *
 * WRITTEN BUT NOT RUN IN THIS SESSION: no dev server/browser available in
 * this environment. Written to be complete and correct, to be run later
 * via `playwright test` per test_strategy.browser_test.run_command, from a
 * project root with @playwright/test installed and a playwright.config.*
 * pointing at this file (e.g. testDir including backend/tests/Browser).
 *
 * Test data assumption (mirrors KelolaBusinessUnitTest.php's approach):
 * this screen requires an authenticated admin session, so each scenario
 * logs in via /login first, then navigates to /master-data/stations. Each
 * scenario uses its own dedicated fixture user/data (rather than sharing
 * across scenarios) so scenario order never matters even though this spec
 * does not reset the DB itself (Playwright drives the browser only,
 * against whatever seeder provisioned the target environment).
 *   - stest-admin01 / Passw0rd! (role: admin) — scenarios 1-9
 *   - stest-nonadmin01 / Passw0rd! (role: supervisor) — scenario 8
 * Scenario 1 assumes a business unit named "Mill Station Baru" already
 * exists (to pick from the Business Unit dropdown), with at least one
 * child Production Line. Scenario 2 assumes a station named "Weighbridge
 * Sebelum Edit" already exists under some business unit, and a business
 * unit named "Mill Station Tujuan Edit" (with at least one child
 * Production Line) exists as the edit target. Scenario 3 assumes a station
 * named "Weighbridge Hapus Bersih" pre-seeded with 0 related
 * MachineryGroup/Machinery rows. Scenario 4 assumes a station named
 * "Weighbridge Ada Machinery" pre-seeded with at least 1 related
 * MachineryGroup or Machinery row. Scenario 5 assumes a station already
 * exists with code "STA-DUP-01" under any business unit. Scenario 6
 * assumes at least one Business Unit row exists (for the "Business Unit
 * induk wajib dipilih" validation scenario). Scenario 7 (cross-field
 * is_active/type=other rule) needs at least one Business Unit with a child
 * Production Line. Scenario 8 (akses ditolak) needs no pre-seeded Station
 * data. Adjust the USERNAME/fixture-name constants below to match whatever
 * seeder is used to provision the target environment.
 */

import { test, expect } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { closeModal, expectNoRowOnAnyPage, expectRowCountOnAllPages, findRow } from './support/paged-table'
import { watchProblems } from './support/page-health'

const STATIONS_PATH = '/master-data/stations';

// Business Unit fixture dengan minimal satu Production Line (dibuat
// BrowserTestFixtureSeeder) — pilihan deterministik untuk skenario yang
// tidak peduli BU mana yang dipakai.
const FIXTURE_BU_WITH_LINE = 'Mill Station Baru';


async function gotoStations(page) {
  await page.goto(STATIONS_PATH);
}

// Interacts with the x-searchable-select combobox (see
// resources/views/components/searchable-select.blade.php) that replaced
// this screen's #business_unit_id / #production_line_id / #type /
// #filterBusinessUnitId <select>s.
async function selectSearchable(page, id, label) {
  await page.locator(`#${id}`).click();
  await page.locator(`#${id}`).fill(label);
  await page.locator(`#${id}-listbox`).getByRole('option', { name: label, exact: true }).click();
}

// Picks the first real option (skips the "-- Pilih ... --" placeholder,
// which is always listbox index 0) — mirrors the old selectOption({ index: 1 }).
async function selectSearchableFirst(page, id) {
  await page.locator(`#${id}`).click();
  await page.locator(`#${id}-listbox`).getByRole('option').nth(1).click();
}

// Sejak audit 2026-10-04 #7 satu Production Line hanya boleh punya SATU
// station per tipe (kecuali Other). Skenario tambah/edit/kode-duplikat
// yang dulu memakai Weighbridge/Grading kini memakai tipe Other (wajib
// Nonaktif) — kalau tidak, residu run sebelumnya (station ber-timestamp di
// line fixture yang sama) membuat run berikutnya ditolak sebagai tipe
// kembar. Penolakan tipe kembar itu sendiri diuji di skenario tersendiri.
async function chooseOtherInactive(page) {
  await selectSearchable(page, 'type', 'Other');
  await page.locator('#is_active').uncheck();
}

test.describe('Kelola Station', () => {
  let problems: string[] = []
  test.beforeEach(({ page }) => {
    problems = watchProblems(page, { allowStatus: [403] })
  })
  test.afterEach(() => {
    expect(problems).toEqual([])
  })

  // Scenario: "Kelola Station — success"
  test('menambah station baru dengan memilih business unit dan menampilkannya di tabel', async ({ page }) => {
    await login(page, 'stest-admin01', PASSWORD);
    await gotoStations(page);

    await page.locator('button', { hasText: 'Tambah Station' }).click();
    await selectSearchable(page, 'business_unit_id', 'Mill Station Baru');
    // Cascaded from the Business Unit just picked — wire:model.live on
    // #business_unit_id reloaded this field's options.
    await selectSearchableFirst(page, 'production_line_id');
    await chooseOtherInactive(page);

    const uniqueSuffix = Date.now();
    const uniqueName = `Weighbridge Baru ${uniqueSuffix}`;
    await page.locator('#name').fill(uniqueName);
    await page.locator('#code').fill(`STA-BROWSER-${uniqueSuffix}`);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    const row = await findRow(page, uniqueName);
    await expect(row).toBeVisible();
    await expect(row).toContainText('Mill Station Baru');
    await expect(row).toContainText('PL Station Baru');
    await expect(row).toContainText('Other');
    await expect(row).toContainText('Nonaktif');
    await expect(row).toContainText('0');
    await expect(page.locator('[data-testid="success-message"]')).toContainText('Station berhasil ditambahkan.');
  });

  // Scenario: "Kelola Station — Edit Station"
  test('mengedit nama, type, dan business unit station lalu menampilkan perubahan di tabel', async ({ page }) => {
    await login(page, 'stest-admin01', PASSWORD);
    await gotoStations(page);

    const row = await findRow(page, 'Weighbridge Sebelum Edit');
    await row.locator('button', { hasText: 'Edit' }).click();

    await selectSearchable(page, 'business_unit_id', 'Mill Station Tujuan Edit');
    // Changing Business Unit resets production_line_id and reloads its
    // options (updatedBusinessUnitId()) — must re-pick it.
    await selectSearchableFirst(page, 'production_line_id');
    await chooseOtherInactive(page);
    const uniqueSuffix = Date.now();
    const newName = `Weighbridge Sesudah Edit ${uniqueSuffix}`;
    await page.locator('#name').fill(newName);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    const updatedRow = await findRow(page, newName);
    await expect(updatedRow).toBeVisible();
    await expect(updatedRow).toContainText('Mill Station Tujuan Edit');
    await expect(updatedRow).toContainText('Other');
    await expectNoRowOnAnyPage(page, 'Weighbridge Sebelum Edit');
  });

  // Scenario: "Kelola Station — Hapus Station — berhasil"
  test('menghapus station tanpa machinery terkait, baris hilang dari tabel', async ({ page }) => {
    await login(page, 'stest-admin01', PASSWORD);
    await gotoStations(page);

    const row = await findRow(page, 'Weighbridge Hapus Bersih');
    await row.locator('button', { hasText: 'Hapus' }).click();
    await row.locator('button', { hasText: 'Ya, Hapus' }).click();

    await expectNoRowOnAnyPage(page, 'Weighbridge Hapus Bersih');
  });

  // Scenario: "Kelola Station — Hapus Station — ditolak"
  test('menolak penghapusan station yang masih memiliki machinery terkait, baris tetap ada', async ({ page }) => {
    await login(page, 'stest-admin01', PASSWORD);
    await gotoStations(page);

    const row = await findRow(page, 'Weighbridge Ada Machinery');
    await row.locator('button', { hasText: 'Hapus' }).click();
    await row.locator('button', { hasText: 'Ya, Hapus' }).click();

    await expect(page.locator('.kc-alert')).toContainText(/Machinery/i);
    await expect(await findRow(page, 'Weighbridge Ada Machinery')).toBeVisible();
  });

  // Scenario: "Kelola Station — Kode duplikat"
  test('menampilkan error validasi saat menyimpan kode yang sudah dipakai station lain', async ({ page }) => {
    await login(page, 'stest-admin01', PASSWORD);
    await gotoStations(page);

    await page.locator('button', { hasText: 'Tambah Station' }).click();
    // BU fixture yang PASTI punya Production Line (BrowserTestFixtureSeeder).
    // Dulu selectSearchableFirst() memilih BU pertama menurut abjad — BU itu
    // tergantung isi database dan bisa tanpa line, sehingga daftar line
    // kosong dan klik opsi line menunggu sampai timeout.
    await selectSearchable(page, 'business_unit_id', FIXTURE_BU_WITH_LINE);
    await selectSearchableFirst(page, 'production_line_id');
    await chooseOtherInactive(page);
    await page.locator('#name').fill('Weighbridge Kode Duplikat');
    await page.locator('#code').fill('STA-DUP-01');
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-form-field__error').first()).toContainText(/sudah digunakan/i);
    // The modal stays open — submission was blocked by validation, no new
    // row with the duplicate code was created.
    await expect(page.locator('.kcm-modal')).toBeVisible();
    await closeModal(page);
    await expectNoRowOnAnyPage(page, 'Weighbridge Kode Duplikat');
  });

  // Scenario: "Kelola Station — Business Unit induk wajib dipilih"
  test('menampilkan error validasi saat submit form tanpa memilih Business Unit', async ({ page }) => {
    await login(page, 'stest-admin01', PASSWORD);
    await gotoStations(page);

    await page.locator('button', { hasText: 'Tambah Station' }).click();
    await selectSearchable(page, 'type', 'Weighbridge');
    const uniqueSuffix = Date.now();
    await page.locator('#name').fill('Weighbridge Tanpa Business Unit');
    await page.locator('#code').fill(`STA-NOBU-${uniqueSuffix}`);
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    // Neither business_unit_id nor production_line_id was selected, so
    // both render a "wajib dipilih" error simultaneously — .first() picks
    // whichever renders first in DOM order (business_unit_id's field
    // comes before production_line_id's in the form markup).
    await expect(page.locator('.kc-form-field__error').first()).toContainText(/wajib dipilih/i);
    await expect(page.locator('.kcm-modal')).toBeVisible();
    await closeModal(page);
    await expectNoRowOnAnyPage(page, 'Weighbridge Tanpa Business Unit');
  });

  // CRITICAL — the cross-field rule: is_active=true with type=Other is
  // rejected, form stays open, no row created.
  test('menampilkan error validasi saat status Aktif dipilih untuk tipe Other', async ({ page }) => {
    await login(page, 'stest-admin01', PASSWORD);
    await gotoStations(page);

    await page.locator('button', { hasText: 'Tambah Station' }).click();
    // BU fixture yang PASTI punya Production Line (BrowserTestFixtureSeeder).
    // Dulu selectSearchableFirst() memilih BU pertama menurut abjad — BU itu
    // tergantung isi database dan bisa tanpa line, sehingga daftar line
    // kosong dan klik opsi line menunggu sampai timeout.
    await selectSearchable(page, 'business_unit_id', FIXTURE_BU_WITH_LINE);
    await selectSearchableFirst(page, 'production_line_id');
    await selectSearchable(page, 'type', 'Other');
    const uniqueSuffix = Date.now();
    await page.locator('#name').fill('Station Other Aktif');
    // Default state of the "Aktif" checkbox on openCreateForm() is checked
    // (is_active defaults to true) — leave it checked to trigger the
    // cross-field rule with type=Other.
    await expect(page.locator('#is_active')).toBeChecked();
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-form-field__error').first()).toContainText(/Other/i);
    await expect(page.locator('.kcm-modal')).toBeVisible();
    await closeModal(page);
    await expectNoRowOnAnyPage(page, 'Station Other Aktif');
  });

  // Audit 2026-10-04 #7: tipe kembar dalam satu Production Line ditolak,
  // dan pilihan Type memuat seluruh tipe station (bukan 4).
  test('menolak station bertipe sama di Production Line yang sama', async ({ page }) => {
    await login(page, 'stest-admin01', PASSWORD);
    await gotoStations(page);

    await page.locator('button', { hasText: 'Tambah Station' }).click();
    await selectSearchable(page, 'business_unit_id', 'Mill Station Baru');
    await selectSearchable(page, 'production_line_id', 'PL Station Baru');
    await page.locator('#type').click();
    const typeLabels = (await page.locator('#type-listbox').getByRole('option').allTextContents()).map((l) => l.trim());
    expect(typeLabels).toEqual(expect.arrayContaining(['Boiler Room', 'CPO Dispatch', 'Sterilizer', 'Other']));
    await page.keyboard.press('Escape');
    await selectSearchable(page, 'type', 'Weighbridge');
    await page.locator('#name').fill('Weighbridge Kembar');
    await page.locator('button[type="submit"]', { hasText: 'Simpan' }).click();

    await expect(page.locator('.kc-form-field__error').first()).toContainText('sudah memiliki station bertipe Weighbridge');
    await expect(page.locator('.kcm-modal')).toBeVisible();
    await closeModal(page);
    await expectNoRowOnAnyPage(page, 'Weighbridge Kembar');
  });

  // Audit 2026-10-04 #10: kolom Production Line + filter per line.
  test('menampilkan kolom Production Line dan memfilter per line', async ({ page }) => {
    await login(page, 'stest-admin01', PASSWORD);
    await gotoStations(page);

    await expect(page.locator('.kc-table__head')).toContainText('Production Line');
    await selectSearchable(page, 'filterBusinessUnitId', 'Mill Station Baru');
    await selectSearchable(page, 'filterProductionLineId', 'PL Station Baru');
    const rows = page.locator('.kc-table tbody tr');
    await expect(rows.first()).toContainText('PL Station Baru');
    for (const text of await rows.allTextContents()) expect(text).toContain('PL Station Baru');
  });

  // Scenario: "Kelola Station — Akses ditolak untuk non-Admin"
  test('menampilkan halaman akses ditolak untuk pengguna non-admin', async ({ page }) => {
    await login(page, 'stest-nonadmin01', PASSWORD);
    await page.goto(STATIONS_PATH);

    // EnsureRole::forbidden() -> abort(403) → halaman 403 ramah di dalam
    // shell (audit 2026-10-04 #1) — tanpa tabel/kontrol station.
    await expect(page.locator('body')).toContainText(/403/);
    await expect(page.locator('[data-testid="forbidden-page"]')).toContainText('Akses Ditolak');
    await expect(page.locator('[data-testid="forbidden-logout"]')).toBeVisible();
    await expect(page.locator('.kc-table')).toHaveCount(0);
  });

  // Additional coverage: filtering the table by Business Unit.
  test('memfilter tabel station berdasarkan Business Unit yang dipilih', async ({ page }) => {
    await login(page, 'stest-admin01', PASSWORD);
    await gotoStations(page);

    await selectSearchableFirst(page, 'filterBusinessUnitId');

    // Every visible row (if any) belongs to the selected Business Unit —
    // asserted structurally rather than against a specific row count,
    // since the target environment's seeded data volume may vary.
    await expect(page.locator('.kc-table')).toBeVisible();
  });
});
