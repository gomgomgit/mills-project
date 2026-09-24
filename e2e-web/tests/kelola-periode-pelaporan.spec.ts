/**
 * Kelola Periode Pelaporan (Browser/Playwright) —
 * screen-128--kelola-periode-pelaporan / usecase-128 (CRUD) +
 * usecase-140 (tutup & buka kembali) + usecase-144 (buka periode draft).
 *
 * One test per test_scenarios entry whose `browser_test` is non-empty
 * (scenarios 1–13, 16–18 and 21–28, plus the cancelOpen path). Scenarios 14, 15, 19 and 20 carry an empty
 * browser_test — they are mobile-sync / record-locking scenarios and live,
 * skipped, in backend/tests/Feature/Api/KelolaPeriodePelaporanTest.php.
 *
 * FIXTURES — this spec deliberately reuses accounts BrowserTestFixtureSeeder
 * already creates, so no seeder change is needed to run it:
 *   - butest-admin01     (Admin,      name "Butest admin01")
 *   - pltest-admin01     (Admin,      name "Pltest admin01") — the second
 *                        Admin of the concurrent-closure scenario
 *   - butest-nonadmin01  (Supervisor) — the "akses ditolak" scenarios
 *   - Business Unit "BU Browser Test" — the only mill in the fixture set
 * Password: PASSWORD from ./support/auth.
 *
 * SELF-SUFFICIENT DATA: every scenario creates the periods it needs through
 * the UI. Because a period's range may not overlap another period on the
 * same (mill, station type), each test gets its OWN five-day window far in
 * the future, offset by a per-run seed (see uniqueRange) — so re-running
 * the suite never collides with the rows a previous run left behind.
 * Scenario 13 is the one exception: it must cover a date that already
 * carries an unverified station record (2026-08-05, seeded by
 * BrowserTestFixtureSeeder), so it cleans up after itself.
 *
 * SAFE TO RUN AGAINST A SHARED FIXTURE DB: closing a period currently has
 * no effect on any other screen — the record-level lock
 * (usecase-141--kunci-input-periode-tertutup) is not implemented — so the
 * periods these tests leave behind cannot influence any other spec.
 *
 * #business_unit_id / #station_type / #filterBusinessUnitId / #filterStatus
 * are x-searchable-select comboboxes (resources/views/components/
 * searchable-select.blade.php), not native <select> — driven through the
 * selectSearchable() helper, same pattern as kelola-production-line.spec.ts.
 */

import { test, expect, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { deletePeriodsByPrefix } from './support/periods'

const PERIODS_PATH = '/master-data/periods'

const ADMIN = 'butest-admin01'
const ADMIN_NAME = 'Butest admin01'
const SECOND_ADMIN = 'pltest-admin01'
const SECOND_ADMIN_NAME = 'Pltest admin01'
const NON_ADMIN = 'butest-nonadmin01'

const BUSINESS_UNIT = 'BU Browser Test'
const STATION_TYPE = 'Sterilizer'

/**
 * A per-run day offset, so two runs of this suite never try to create
 * overlapping ranges on the same (mill, station type).
 *
 * MONOTONIC ON PURPOSE (one day of period-date space per minute of wall
 * clock since 2026-01-01), where it used to be `Date.now() % 150000`: the
 * list is paginated at 20 rows ordered by `start_date DESC`, and the
 * fixture database keeps every period any previous run created —
 * including laporan-sterilizer.spec.ts's, which seeds its own windows in
 * the year-2600 era. A modulo offset lands a fresh run above or below
 * those older rows at random, and once more than 20 of them sort higher,
 * the row a test has just created is no longer on page 1 and every
 * assertion on it fails. An offset that only ever grows keeps the current
 * run's rows at the top of the first page whatever the database has
 * accumulated. Two runs started within the same minute still collide —
 * in practice that window is no worse than the old scheme's.
 */
const RUN_OFFSET = Math.floor((Date.now() - Date.UTC(2026, 0, 1)) / 60000)

function isoDate(dayOffset: number): string {
  return new Date(Date.UTC(2100, 0, 1) + dayOffset * 86400000).toISOString().slice(0, 10)
}

/** A five-day window unique to this run AND to this scenario's slot. */
function uniqueRange(slot: number): { start: string; end: string } {
  const startDay = RUN_OFFSET + slot * 10

  return { start: isoDate(startDay), end: isoDate(startDay + 4) }
}

function uniqueName(label: string): string {
  return `Periode ${label} ${RUN_OFFSET}`
}

async function gotoPeriods(page: Page): Promise<void> {
  await page.goto(PERIODS_PATH)
}

async function selectSearchable(page: Page, id: string, label: string): Promise<void> {
  await page.locator(`#${id}`).click()
  await page.locator(`#${id}`).fill(label)
  await page.locator(`#${id}-listbox`).getByRole('option', { name: label, exact: true }).click()
}

/**
 * Fills the "Tambah Periode" form. `stationType` null leaves the select
 * untouched — the empty value is the "Semua Stasiun" (all station types)
 * scope.
 */
async function fillPeriodForm(
  page: Page,
  options: {
    businessUnit?: string | null
    stationType?: string | null
    name: string
    start: string
    end: string
  },
): Promise<void> {
  if (options.businessUnit) {
    await selectSearchable(page, 'business_unit_id', options.businessUnit)
  }

  if (options.stationType) {
    await selectSearchable(page, 'station_type', options.stationType)
  }

  await page.locator('#name').fill(options.name)
  await page.locator('#start_date').fill(options.start)
  await page.locator('#end_date').fill(options.end)
}

/** Creates a period through the UI and waits for its row to appear. */
async function createPeriod(
  page: Page,
  options: { stationType?: string | null; name: string; start: string; end: string },
): Promise<void> {
  await page.locator('[data-testid="add-period-button"]').click()
  await fillPeriodForm(page, {
    businessUnit: BUSINESS_UNIT,
    stationType: options.stationType ?? STATION_TYPE,
    name: options.name,
    start: options.start,
    end: options.end,
  })
  await page.locator('[data-testid="save-button"]').click()

  await expect(page.locator('.kc-table__row', { hasText: options.name })).toBeVisible()
}

test.describe('Kelola Periode Pelaporan', () => {
  // Pembersihan — lihat tests/support/periods.ts untuk alasan lengkapnya.
  // Tanpa ini, periode yang dibuat spec ini menumpuk di database dev
  // (~15 baris per run) sampai memenuhi halaman 1 daftar yang dipaginasi
  // 20 baris, lalu baris yang baru dibuat test terdorong ke halaman 2 dan
  // seluruh suite gagal di createPeriod() sebelum satu pun asersi jalan.
  // Terbukti terjadi pada 2026-09-23: 117 periode, 116 di antaranya residu.
  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage()

    try {
      await login(page, ADMIN, PASSWORD)
      const deleted = await deletePeriodsByPrefix(page, ['Periode '])
      console.log(`[cleanup] kelola-periode-pelaporan: %d periode dihapus`, deleted)
    } catch (error) {
      // Kegagalan membersihkan bukan kegagalan produk — jangan pernah
      // memerahkan suite karenanya.
      console.warn('[cleanup] kelola-periode-pelaporan: pembersihan gagal:', error)
    } finally {
      await page.close()
    }
  })

  // Scenario 1: "Kelola Periode Pelaporan — success"
  test('menambah periode baru: baris muncul dengan badge Draft dan kolom Ditutup Oleh kosong', async ({ page }) => {
    const { start, end } = uniqueRange(0)
    const name = uniqueName('Sukses')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)

    await createPeriod(page, { name, start, end })

    const row = page.locator('.kc-table__row', { hasText: name })
    await expect(row).toContainText(BUSINESS_UNIT)
    await expect(row).toContainText(STATION_TYPE)
    await expect(row.locator('.kc-badge')).toHaveText('Draft')
    // "Ditutup Oleh" / "Waktu Ditutup" render an em dash while the period
    // is not closed.
    await expect(row.locator('td').nth(6)).toHaveText('—')
    await expect(row.locator('td').nth(7)).toHaveText('—')
    // The modal closed and a success flash is shown.
    await expect(page.locator('.kcm-modal')).toHaveCount(0)
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()
  })

  // Scenario 2: "Rentang tanggal tumpang tindih"
  test('rentang tumpang tindih: modal tetap terbuka, pesan menyebut periode lawan, tabel tidak bertambah', async ({ page }) => {
    const { start, end } = uniqueRange(1)
    const existing = uniqueName('Bentrok Awal')
    const attempted = uniqueName('Bentrok Tambahan')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)

    await createPeriod(page, { name: existing, start, end })

    const rowsBefore = await page.locator('.kc-table__row').count()

    await page.locator('[data-testid="add-period-button"]').click()
    await fillPeriodForm(page, {
      businessUnit: BUSINESS_UNIT,
      stationType: STATION_TYPE,
      name: attempted,
      // Starts inside the existing window -> overlaps.
      start: isoDate(RUN_OFFSET + 1 * 10 + 2),
      end: isoDate(RUN_OFFSET + 1 * 10 + 8),
    })
    await page.locator('[data-testid="save-button"]').click()

    // The modal stays open with the input intact, and the message names the
    // conflicting period.
    await expect(page.locator('.kcm-modal')).toBeVisible()
    await expect(page.locator('[data-testid="form-error"]')).toContainText(existing)
    await expect(page.locator('#name')).toHaveValue(attempted)
    await expect(page.locator('.kc-table__row', { hasText: attempted })).toHaveCount(0)
    expect(await page.locator('.kc-table__row').count()).toBe(rowsBefore)
  })

  // Scenario 3: "Tanggal selesai lebih awal dari tanggal mulai"
  test('tanggal selesai mendahului tanggal mulai: error inline di bawah Tanggal Selesai', async ({ page }) => {
    const { start, end } = uniqueRange(2)
    const name = uniqueName('Terbalik')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)

    await page.locator('[data-testid="add-period-button"]').click()
    await fillPeriodForm(page, {
      businessUnit: BUSINESS_UNIT,
      stationType: STATION_TYPE,
      name,
      // Deliberately reversed.
      start: end,
      end: start,
    })
    await page.locator('[data-testid="save-button"]').click()

    await expect(page.locator('.kc-form-field__error')).toContainText(/tidak boleh lebih awal/i)
    await expect(page.locator('.kcm-modal')).toBeVisible()
    await expect(page.locator('.kc-table__row', { hasText: name })).toHaveCount(0)
  })

  // Scenario 4: "Nama periode sudah dipakai"
  test('nama periode duplikat: error di bawah field Nama dan tabel tetap satu baris bernama sama', async ({ page }) => {
    const { start, end } = uniqueRange(3)
    const name = uniqueName('Duplikat')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)

    await createPeriod(page, { name, start, end })

    await page.locator('[data-testid="add-period-button"]').click()
    await fillPeriodForm(page, {
      businessUnit: BUSINESS_UNIT,
      stationType: STATION_TYPE,
      name,
      // Different, non-overlapping window — the only rejected thing is the name.
      start: isoDate(RUN_OFFSET + 3 * 10 + 20),
      end: isoDate(RUN_OFFSET + 3 * 10 + 24),
    })
    await page.locator('[data-testid="save-button"]').click()

    await expect(page.locator('.kc-form-field__error')).toContainText(/sudah digunakan/i)
    await expect(page.locator('.kcm-modal')).toBeVisible()
    await expect(page.locator('.kc-table__row', { hasText: name })).toHaveCount(1)
  })

  // Scenario 5: "Mengubah atau menghapus periode yang sudah tertutup"
  test('periode tertutup: Edit, Hapus dan Tutup Periode tidak tersedia, hanya Buka Kembali', async ({ page }) => {
    const { start, end } = uniqueRange(4)
    const name = uniqueName('Terkunci')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)

    await createPeriod(page, { name, start, end })

    const row = page.locator('.kc-table__row', { hasText: name })
    await row.locator('button', { hasText: 'Tutup Periode' }).click()
    await page.locator('[data-testid="confirm-close-button"]').click()

    const closedRow = page.locator('.kc-table__row', { hasText: name })
    await expect(closedRow.locator('.kc-badge')).toHaveText('Tertutup')

    // The screen refuses the dead end BEFORE it is reachable: a closed row
    // renders exactly one action. (The 409 PERIOD_CLOSED_IMMUTABLE message
    // that a forged/stale request would get is asserted at API level in
    // backend/tests/Feature/Api/KelolaPeriodePelaporanTest.php.)
    await expect(closedRow.locator('button', { hasText: 'Buka Kembali Periode' })).toBeVisible()
    await expect(closedRow.locator('button', { hasText: 'Edit' })).toHaveCount(0)
    await expect(closedRow.locator('button', { hasText: 'Hapus' })).toHaveCount(0)
    await expect(closedRow.locator('button', { hasText: 'Tutup Periode' })).toHaveCount(0)

    // Name and range are untouched.
    await expect(closedRow).toContainText(name)
  })

  // Scenario 6: "Belum ada Business Unit"
  //
  // SKIPPED, not deleted: the fixture environment ALWAYS has
  // "BU Browser Test" seeded (every other spec in this suite needs it), so
  // the empty-dropdown state cannot be produced in a browser without wiping
  // the database out from under the whole suite. The same scenario IS
  // asserted, for real, at component level
  // (backend/tests/Feature/Livewire/KelolaPeriodePelaporanTest.php) and at
  // API level (backend/tests/Feature/Api/KelolaPeriodePelaporanTest.php),
  // both of which can empty the table safely.
  test.skip('belum ada Business Unit: dropdown kosong dan pesan arahan membuat Business Unit dulu', async ({ page }) => {
    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)

    await page.locator('[data-testid="add-period-button"]').click()
    await expect(page.locator('#business_unit_id-listbox').getByRole('option')).toHaveCount(0)
    await expect(page.locator('.ss-combobox__empty-hint')).toContainText('Belum ada Business Unit')
  })

  // Scenario 7: "Periode sudah dihapus pengguna lain"
  test('periode dihapus pengguna lain saat form terbuka: pesan ramah, tabel ter-refresh, tanpa error 500', async ({ page, browser }) => {
    const { start, end } = uniqueRange(6)
    const name = uniqueName('Balapan Hapus')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    // Admin 1 opens the edit form...
    await page.locator('.kc-table__row', { hasText: name }).locator('button', { hasText: 'Edit' }).click()
    await expect(page.locator('.kcm-modal')).toBeVisible()

    // ...while Admin 2, in a separate browser context, deletes the row.
    const otherContext = await browser.newContext()
    const otherPage = await otherContext.newPage()
    await login(otherPage, SECOND_ADMIN, PASSWORD)
    await gotoPeriods(otherPage)
    const otherRow = otherPage.locator('.kc-table__row', { hasText: name })
    await otherRow.locator('button', { hasText: 'Hapus' }).click()
    await otherRow.locator('button', { hasText: 'Ya, Hapus' }).click()
    await expect(otherPage.locator('.kc-table__row', { hasText: name })).toHaveCount(0)
    await otherContext.close()

    // Admin 1 saves the now-orphaned form.
    await page.locator('#name').fill(`${name} Revisi`)
    await page.locator('[data-testid="save-button"]').click()

    await expect(page.locator('[data-testid="form-error"]')).toContainText(/tidak ditemukan/i)
    await expect(page.locator('body')).not.toContainText('500')
    await expect(page.locator('.kc-table__row', { hasText: name })).toHaveCount(0)
  })

  // Scenario 8: "pengguna non-Admin mencoba mengakses"
  test('akses ditolak: pengguna non-Admin tidak melihat data periode sama sekali', async ({ page }) => {
    await login(page, NON_ADMIN, PASSWORD)
    await page.goto(PERIODS_PATH)

    // EnsureRole::forbidden() -> abort(403), Laravel's default HTML error page.
    await expect(page.locator('body')).toContainText(/403/)
    await expect(page.locator('[data-testid="period-table"]')).toHaveCount(0)
  })

  // Scenario 9: "Business Unit tidak diisi"
  test('submit tanpa memilih Business Unit: error inline dan tabel tidak bertambah', async ({ page }) => {
    const { start, end } = uniqueRange(8)
    const name = uniqueName('Tanpa BU')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)

    await page.locator('[data-testid="add-period-button"]').click()
    await fillPeriodForm(page, {
      businessUnit: null,
      stationType: STATION_TYPE,
      name,
      start,
      end,
    })
    await page.locator('[data-testid="save-button"]').click()

    await expect(page.locator('.kc-form-field__error')).toContainText(/wajib dipilih/i)
    await expect(page.locator('.kcm-modal')).toBeVisible()
    // The rest of the form survived the rejection.
    await expect(page.locator('#name')).toHaveValue(name)
    await expect(page.locator('.kc-table__row', { hasText: name })).toHaveCount(0)
  })

  // Scenario 10: "Tutup & Buka Kembali Periode Pelaporan — success"
  test('tutup periode: dialog memuat angka belum terverifikasi, setelah konfirmasi badge jadi Tertutup', async ({ page }) => {
    const { start, end } = uniqueRange(9)
    const name = uniqueName('Tutup Sukses')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    await page.locator('.kc-table__row', { hasText: name }).locator('button', { hasText: 'Tutup Periode' }).click()

    const dialog = page.locator('.kcm-modal')
    await expect(dialog).toBeVisible()
    await expect(page.locator('[data-testid="unverified-count"]')).toContainText('data stasiun belum terverifikasi')

    await page.locator('[data-testid="confirm-close-button"]').click()

    const row = page.locator('.kc-table__row', { hasText: name })
    await expect(row.locator('.kc-badge')).toHaveText('Tertutup')
    await expect(row).toContainText(ADMIN_NAME)
    await expect(page.locator('.kcm-modal')).toHaveCount(0)
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()
  })

  // Scenario 11: "buka kembali periode yang sudah tertutup"
  test('buka kembali periode: badge jadi Terbuka, kolom penutupan kosong, tombol Tutup Periode kembali', async ({ page }) => {
    const { start, end } = uniqueRange(10)
    const name = uniqueName('Buka Kembali')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    await page.locator('.kc-table__row', { hasText: name }).locator('button', { hasText: 'Tutup Periode' }).click()
    await page.locator('[data-testid="confirm-close-button"]').click()
    await expect(page.locator('.kc-table__row', { hasText: name }).locator('.kc-badge')).toHaveText('Tertutup')

    const closedRow = page.locator('.kc-table__row', { hasText: name })
    await closedRow.locator('button', { hasText: 'Buka Kembali Periode' }).click()
    await closedRow.locator('button', { hasText: 'Ya, Buka Kembali' }).click()

    const reopened = page.locator('.kc-table__row', { hasText: name })
    await expect(reopened.locator('.kc-badge')).toHaveText('Terbuka')
    await expect(reopened.locator('td').nth(6)).toHaveText('—')
    await expect(reopened.locator('td').nth(7)).toHaveText('—')
    await expect(reopened.locator('button', { hasText: 'Tutup Periode' })).toBeVisible()
    await expect(reopened.locator('button', { hasText: 'Edit' })).toBeVisible()
    await expect(reopened.locator('button', { hasText: 'Hapus' })).toBeVisible()
  })

  // Scenario 12: "Admin membatalkan penutupan"
  test('membatalkan dialog Tutup Periode: badge tetap dan kolom Ditutup Oleh tetap kosong', async ({ page }) => {
    const { start, end } = uniqueRange(11)
    const name = uniqueName('Batal Tutup')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    await page.locator('.kc-table__row', { hasText: name }).locator('button', { hasText: 'Tutup Periode' }).click()
    await expect(page.locator('[data-testid="unverified-warning"]')).toBeVisible()

    await page.locator('[data-testid="cancel-close-button"]').click()

    await expect(page.locator('[data-testid="unverified-warning"]')).toHaveCount(0)
    const row = page.locator('.kc-table__row', { hasText: name })
    await expect(row.locator('.kc-badge')).toHaveText('Draft')
    await expect(row.locator('td').nth(6)).toHaveText('—')
  })

  // Scenario 13: "masih banyak data belum terverifikasi"
  test('dialog tutup menampilkan angka belum terverifikasi yang sebenarnya sebelum konfirmasi', async ({ page }) => {
    // This window deliberately covers 2026-08-05 — the date
    // BrowserTestFixtureSeeder stamps on its Effluent Plant fixture record,
    // which has neither checked_by nor acknowledged_by, so it counts as
    // unverified. Scoped to Effluent Plant so nothing else is pulled in.
    const name = uniqueName('Belum Terverifikasi')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)

    await createPeriod(page, {
      stationType: 'Effluent Plant',
      name,
      start: '2026-08-01',
      end: '2026-08-15',
    })

    await page.locator('.kc-table__row', { hasText: name }).locator('button', { hasText: 'Tutup Periode' }).click()

    // A real, non-zero figure, its per-station-type breakdown, and the
    // explanation that verification locks too.
    await expect(page.locator('[data-testid="unverified-count"]')).toContainText(/[1-9]\d* data stasiun belum terverifikasi/)
    await expect(page.locator('[data-testid="unverified-breakdown"]')).toContainText('Effluent Plant')
    await expect(page.locator('[data-testid="unverified-warning"]')).toContainText('verifikasi ikut terkunci')

    // The confirm button is never disabled by the figure — closing proceeds.
    await page.locator('[data-testid="confirm-close-button"]').click()
    await expect(page.locator('.kc-table__row', { hasText: name }).locator('.kc-badge')).toHaveText('Tertutup')

    // Clean up: this is the one scenario that cannot use a unique far-future
    // window, so it must not leave a row behind that would block a re-run.
    const row = page.locator('.kc-table__row', { hasText: name })
    await row.locator('button', { hasText: 'Buka Kembali Periode' }).click()
    await row.locator('button', { hasText: 'Ya, Buka Kembali' }).click()
    const reopened = page.locator('.kc-table__row', { hasText: name })
    await reopened.locator('button', { hasText: 'Hapus' }).click()
    await reopened.locator('button', { hasText: 'Ya, Hapus' }).click()
    await expect(page.locator('.kc-table__row', { hasText: name })).toHaveCount(0)
  })

  // Scenario 16: "menutup periode yang sudah tertutup"
  test('baris berstatus Tertutup: tombol Tutup Periode tidak terlihat, Buka Kembali dapat diklik', async ({ page }) => {
    const { start, end } = uniqueRange(13)
    const name = uniqueName('Sudah Tertutup')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    await page.locator('.kc-table__row', { hasText: name }).locator('button', { hasText: 'Tutup Periode' }).click()
    await page.locator('[data-testid="confirm-close-button"]').click()

    const row = page.locator('.kc-table__row', { hasText: name })
    await expect(row.locator('.kc-badge')).toHaveText('Tertutup')
    await expect(row.locator('button', { hasText: 'Tutup Periode' })).toHaveCount(0)
    await expect(row.locator('button', { hasText: 'Buka Kembali Periode' })).toBeEnabled()
  })

  // Scenario 17: "dua Admin menutup periode bersamaan"
  test('dua Admin menutup bersamaan: Admin kedua diberi tahu, penutup pertama tetap tercatat', async ({ page, browser }) => {
    const { start, end } = uniqueRange(14)
    const name = uniqueName('Balapan Tutup')

    // Admin B prepares the row and keeps the list open (not reloaded).
    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    // Admin A closes the same period in another browser context.
    const otherContext = await browser.newContext()
    const otherPage = await otherContext.newPage()
    await login(otherPage, SECOND_ADMIN, PASSWORD)
    await gotoPeriods(otherPage)
    await otherPage.locator('.kc-table__row', { hasText: name }).locator('button', { hasText: 'Tutup Periode' }).click()
    await otherPage.locator('[data-testid="confirm-close-button"]').click()
    await expect(otherPage.locator('.kc-table__row', { hasText: name })).toContainText(SECOND_ADMIN_NAME)

    // Admin B, without reloading, confirms the closure of the same period.
    await page.locator('.kc-table__row', { hasText: name }).locator('button', { hasText: 'Tutup Periode' }).click()
    await page.locator('[data-testid="confirm-close-button"]').click()

    // They are told it is already closed, by whom and when — and the list
    // now shows Admin A as the closer in BOTH browsers.
    await expect(page.locator('[data-testid="close-error"]')).toContainText(SECOND_ADMIN_NAME)
    await expect(page.locator('.kc-table__row', { hasText: name })).toContainText(SECOND_ADMIN_NAME)

    await otherPage.reload()
    await expect(otherPage.locator('.kc-table__row', { hasText: name })).toContainText(SECOND_ADMIN_NAME)
    await otherContext.close()
  })

  // Scenario 18: "pengguna selain Admin menutup periode"
  test('akses ditolak: non-Admin tidak mendapat tombol Tutup/Buka Kembali dan status tidak berubah', async ({ page, browser }) => {
    const { start, end } = uniqueRange(15)
    const name = uniqueName('Non Admin')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    // The non-Admin never reaches the screen, so neither action is offered.
    const otherContext = await browser.newContext()
    const otherPage = await otherContext.newPage()
    await login(otherPage, NON_ADMIN, PASSWORD)
    await otherPage.goto(PERIODS_PATH)
    await expect(otherPage.locator('body')).toContainText(/403/)
    await expect(otherPage.locator('button', { hasText: 'Tutup Periode' })).toHaveCount(0)
    await expect(otherPage.locator('button', { hasText: 'Buka Kembali Periode' })).toHaveCount(0)
    await otherContext.close()

    // Re-checked as Admin: the period is exactly as it was.
    await page.reload()
    const row = page.locator('.kc-table__row', { hasText: name })
    await expect(row.locator('.kc-badge')).toHaveText('Draft')
    await expect(row.locator('td').nth(6)).toHaveText('—')
  })

  // ── usecase-144 (Buka Periode) — scenarios 21–28 ──────────────────────
  //
  // SELECTOR WARNING: unlike edit-button-{id} / close-button-{id} /
  // reopen-button-{id}, the "Buka Periode" button carries a BARE
  // data-testid (open-period-button) with no id suffix — every draft row on
  // the page renders the same one. Always scope it to a row locator (or
  // .first()), never `page.locator('[data-testid="open-period-button"]')`
  // on its own, or Playwright's strict mode fails as soon as the fixture DB
  // holds more than one draft period.

  // Scenario 21: "Buka Periode Pelaporan — sukses"
  test('buka periode draft: dialog konfirmasi lalu badge jadi Terbuka dan tombol Buka Periode hilang', async ({ page }) => {
    const { start, end } = uniqueRange(16)
    const name = uniqueName('Buka Sukses')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const row = page.locator('.kc-table__row', { hasText: name })
    await expect(row.locator('.kc-badge')).toHaveText('Draft')

    await row.locator('[data-testid="open-period-button"]').click()

    const dialog = page.locator('[data-testid="open-period-dialog"]')
    await expect(dialog).toBeVisible()
    await expect(dialog).toContainText('tidak dapat dikembalikan ke Draft')

    await page.locator('[data-testid="confirm-open-period"]').click()

    // The dialog closes and the row is now Terbuka.
    await expect(page.locator('[data-testid="open-period-dialog"]')).toHaveCount(0)
    const opened = page.locator('.kc-table__row', { hasText: name })
    await expect(opened.locator('.kc-badge')).toHaveText('Terbuka')
    await expect(opened.locator('[data-testid="open-period-button"]')).toHaveCount(0)
    // Opening is not closing: the closure columns stay empty and the edit /
    // delete actions remain available.
    await expect(opened.locator('td').nth(6)).toHaveText('—')
    await expect(opened.locator('td').nth(7)).toHaveText('—')
    await expect(opened.locator('button', { hasText: 'Edit' })).toBeVisible()
    await expect(opened.locator('button', { hasText: 'Hapus' })).toBeVisible()
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()
  })

  // Alternative flow "Admin membatalkan pembukaan" — no bdd_scenario of its
  // own, but the cancel path must not go untested.
  test('membatalkan dialog Buka Periode: badge tetap Draft dan tombol Buka Periode masih ada', async ({ page }) => {
    const { start, end } = uniqueRange(17)
    const name = uniqueName('Batal Buka')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const row = page.locator('.kc-table__row', { hasText: name })
    await row.locator('[data-testid="open-period-button"]').click()
    await expect(page.locator('[data-testid="open-period-dialog"]')).toBeVisible()

    await page.locator('[data-testid="cancel-open-period"]').click()

    await expect(page.locator('[data-testid="open-period-dialog"]')).toHaveCount(0)
    const unchanged = page.locator('.kc-table__row', { hasText: name })
    await expect(unchanged.locator('.kc-badge')).toHaveText('Draft')
    await expect(unchanged.locator('[data-testid="open-period-button"]')).toBeVisible()
  })

  // Scenario 22: "Periode sudah terbuka"
  test('baris berstatus Terbuka: tombol Buka Periode tidak dirender sama sekali', async ({ page }) => {
    const { start, end } = uniqueRange(18)
    const name = uniqueName('Sudah Terbuka')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const row = page.locator('.kc-table__row', { hasText: name })
    await row.locator('[data-testid="open-period-button"]').click()
    await page.locator('[data-testid="confirm-open-period"]').click()
    await expect(page.locator('.kc-table__row', { hasText: name }).locator('.kc-badge')).toHaveText('Terbuka')

    // Still absent after a full reload — the button is driven by the row's
    // status, not by client-side state. (The forced-action message a stale
    // list produces is asserted in the concurrent-opening scenario below.)
    await page.reload()
    const reloaded = page.locator('.kc-table__row', { hasText: name })
    await expect(reloaded.locator('.kc-badge')).toHaveText('Terbuka')
    await expect(reloaded.locator('[data-testid="open-period-button"]')).toHaveCount(0)
    await expect(reloaded.locator('button', { hasText: 'Tutup Periode' })).toBeVisible()
  })

  // Scenario 23: "Periode sudah tertutup"
  test('baris berstatus Tertutup: hanya Buka Kembali Periode yang ditawarkan, bukan Buka Periode', async ({ page }) => {
    const { start, end } = uniqueRange(19)
    const name = uniqueName('Buka Tertutup')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    await page.locator('.kc-table__row', { hasText: name }).locator('button', { hasText: 'Tutup Periode' }).click()
    await page.locator('[data-testid="confirm-close-button"]').click()

    const row = page.locator('.kc-table__row', { hasText: name })
    await expect(row.locator('.kc-badge')).toHaveText('Tertutup')
    // "Buka Periode" and "Buka Kembali Periode" are two different actions;
    // a closed row must offer only the latter.
    await expect(row.locator('[data-testid="open-period-button"]')).toHaveCount(0)
    await expect(row.locator('button', { hasText: 'Buka Kembali Periode' })).toBeEnabled()
    // (The 409 message that names "Buka Kembali Periode" for a forced call
    // is asserted at API and component level — it cannot be produced from a
    // browser, since the button is never rendered on a closed row.)
  })

  // Scenario 24: "Periode tidak ditemukan"
  test('periode dihapus pengguna lain: konfirmasi Buka Periode memberi pesan tidak ditemukan tanpa error 500', async ({ page, browser }) => {
    const { start, end } = uniqueRange(20)
    const name = uniqueName('Buka Sudah Dihapus')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    // Admin 2, in another context, deletes the row while Admin 1's list
    // still shows it.
    const otherContext = await browser.newContext()
    const otherPage = await otherContext.newPage()
    await login(otherPage, SECOND_ADMIN, PASSWORD)
    await gotoPeriods(otherPage)
    const otherRow = otherPage.locator('.kc-table__row', { hasText: name })
    await otherRow.locator('button', { hasText: 'Hapus' }).click()
    await otherRow.locator('button', { hasText: 'Ya, Hapus' }).click()
    await expect(otherPage.locator('.kc-table__row', { hasText: name })).toHaveCount(0)
    await otherContext.close()

    // Admin 1 opens the now-orphaned row.
    await page.locator('.kc-table__row', { hasText: name }).locator('[data-testid="open-period-button"]').click()
    await page.locator('[data-testid="confirm-open-period"]').click()

    await expect(page.locator('[data-testid="close-error"]')).toContainText(/tidak ditemukan/i)
    await expect(page.locator('body')).not.toContainText('500')
    await expect(page.locator('.kc-table__row', { hasText: name })).toHaveCount(0)

    await page.reload()
    await expect(page.locator('.kc-table__row', { hasText: name })).toHaveCount(0)
  })

  // Scenario 25: "Dua Admin membuka bersamaan"
  test('dua Admin membuka bersamaan: hanya yang pertama mengubah status, yang kedua diberi tahu sudah terbuka', async ({ page, browser }) => {
    const { start, end } = uniqueRange(21)
    const name = uniqueName('Balapan Buka')

    // Admin B prepares the row and keeps the (now stale) list open.
    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    // Admin A opens the same period in another browser context, first.
    const otherContext = await browser.newContext()
    const otherPage = await otherContext.newPage()
    await login(otherPage, SECOND_ADMIN, PASSWORD)
    await gotoPeriods(otherPage)
    await otherPage.locator('.kc-table__row', { hasText: name }).locator('[data-testid="open-period-button"]').click()
    await otherPage.locator('[data-testid="confirm-open-period"]').click()
    await expect(otherPage.locator('.kc-table__row', { hasText: name }).locator('.kc-badge')).toHaveText('Terbuka')

    // Admin B, without reloading, confirms the same opening.
    await page.locator('.kc-table__row', { hasText: name }).locator('[data-testid="open-period-button"]').click()
    await page.locator('[data-testid="confirm-open-period"]').click()

    // Told it is already open — no second change, no silent overwrite.
    await expect(page.locator('[data-testid="close-error"]')).toContainText(/sudah terbuka/i)
    await expect(page.locator('[data-testid="success-message"]')).toHaveCount(0)
    await expect(page.locator('.kc-table__row', { hasText: name }).locator('.kc-badge')).toHaveText('Terbuka')

    await page.reload()
    const refreshed = page.locator('.kc-table__row', { hasText: name })
    await expect(refreshed.locator('.kc-badge')).toHaveText('Terbuka')
    await expect(refreshed.locator('[data-testid="open-period-button"]')).toHaveCount(0)

    await otherContext.close()
  })

  // Scenario 26: "Bukan Admin mencoba membuka periode"
  test('akses ditolak: non-Admin tidak pernah melihat tombol Buka Periode dan status tetap Draft', async ({ page, browser }) => {
    const { start, end } = uniqueRange(22)
    const name = uniqueName('Buka Non Admin')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const otherContext = await browser.newContext()
    const otherPage = await otherContext.newPage()
    await login(otherPage, NON_ADMIN, PASSWORD)
    await otherPage.goto(PERIODS_PATH)
    await expect(otherPage.locator('body')).toContainText(/403/)
    await expect(otherPage.locator('[data-testid="open-period-button"]')).toHaveCount(0)
    await otherContext.close()

    // Re-checked as Admin: the period is exactly as it was.
    await page.reload()
    const row = page.locator('.kc-table__row', { hasText: name })
    await expect(row.locator('.kc-badge')).toHaveText('Draft')
    await expect(row.locator('[data-testid="open-period-button"]')).toBeVisible()
  })

  // Scenario 27: "status Terbuka tidak dapat dikembalikan ke Draft"
  test('form edit periode Terbuka: tidak ada kontrol status dan badge tetap Terbuka setelah simpan', async ({ page }) => {
    const { start, end } = uniqueRange(23)
    const name = uniqueName('Tanpa Mundur')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    await page.locator('.kc-table__row', { hasText: name }).locator('[data-testid="open-period-button"]').click()
    await page.locator('[data-testid="confirm-open-period"]').click()
    await expect(page.locator('.kc-table__row', { hasText: name }).locator('.kc-badge')).toHaveText('Terbuka')

    await page.locator('.kc-table__row', { hasText: name }).locator('button', { hasText: 'Edit' }).click()

    const modal = page.locator('.kcm-modal')
    await expect(modal).toBeVisible()
    // The form has no status control of any kind — there is simply no way
    // back to Draft from the UI.
    await expect(modal.locator('#status')).toHaveCount(0)
    await expect(modal).not.toContainText('Draft')

    await page.locator('[data-testid="save-button"]').click()
    await expect(page.locator('.kcm-modal')).toHaveCount(0)

    await expect(page.locator('.kc-table__row', { hasText: name }).locator('.kc-badge')).toHaveText('Terbuka')
    await page.reload()
    await expect(page.locator('.kc-table__row', { hasText: name }).locator('.kc-badge')).toHaveText('Terbuka')
  })

  // Scenario 28: "periode Terbuka tetap dapat diubah dan dihapus"
  test('periode Terbuka tetap dapat diubah dan dihapus, tanpa pesan terkunci', async ({ page }) => {
    const { start, end } = uniqueRange(24)
    const name = uniqueName('Terbuka Bebas')
    const renamed = `${name} Revisi`

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    await page.locator('.kc-table__row', { hasText: name }).locator('[data-testid="open-period-button"]').click()
    await page.locator('[data-testid="confirm-open-period"]').click()
    await expect(page.locator('.kc-table__row', { hasText: name }).locator('.kc-badge')).toHaveText('Terbuka')

    // Edit is still available on an open row.
    await page.locator('.kc-table__row', { hasText: name }).locator('button', { hasText: 'Edit' }).click()
    await page.locator('#name').fill(renamed)
    await page.locator('[data-testid="save-button"]').click()

    const renamedRow = page.locator('.kc-table__row', { hasText: renamed })
    await expect(renamedRow).toBeVisible()
    await expect(renamedRow.locator('.kc-badge')).toHaveText('Terbuka')
    await expect(page.locator('[data-testid="delete-error"]')).toHaveCount(0)

    // And so is delete.
    await renamedRow.locator('button', { hasText: 'Hapus' }).click()
    await renamedRow.locator('button', { hasText: 'Ya, Hapus' }).click()

    await expect(page.locator('.kc-table__row', { hasText: renamed })).toHaveCount(0)
    await expect(page.locator('[data-testid="delete-error"]')).toHaveCount(0)
  })
})
