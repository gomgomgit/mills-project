import { test, expect, type Page } from '@playwright/test'
import { login, USERS, getAuthUserId, getBusinessUnitId } from './helpers'

// screen-012--form-cages-track / usecase-012--form-cages-track
//
// FULL REWRITE (2026-08-19, entity-catalog v3), replacing every scenario in
// the previous version of this file (built against the pre-v3
// "No. Cages Track" + free-text cage/time-per-row model). Mirrors
// form-grading.spec.ts's structure/conventions.
//
// UPDATED 2026-08-20 (tech-spec v4, entity-catalog v9 — mill-setting.
// jumlah_cages was removed from the backend entirely): the grid's N
// (shared "Cage 1".."Cage N" checkbox column count) and the "Tambah baris"
// enable/disable condition now come from the local `station` cache's
// `machinery_count` for the active Cages Track station, not the Cages
// Tipped header field and no longer from mill_setting — see
// `seedCagesTrackStationMachineryCount()` below for how this suite seeds
// that column directly (GET /api/production-lines/current/stations, the
// real sync source, is not exercised by this offline-only test
// environment).

const DEFAULT_MACHINERY_COUNT = 10

async function fillRequiredHeaderFields(page: Page): Promise<void> {
  await page.locator('#field-cages-track-number').fill('CT-E2E-001')
  await page.locator('#field-cages-out').fill('12')
  await page.locator('#field-cages-tipped').fill('5')
}

// Matches FormCagesTrackView.vue's own hourLabel() exactly.
function hourLabel(hour: number): string {
  return `${String(hour).padStart(2, '0')}:00`
}

// Time is now a SearchableSelect.vue instance (typeable/searchable
// combobox) rather than a plain <select> — selection is the real
// open-then-click sequence a user drives, replacing the old single
// `.selectOption(value)` DOM call. See form-grading.spec.ts's own
// `selectSearchableOption()`/`searchableSelectOptionValues()` (mirrored
// here) for the fuller rationale, including why `data-value` (not the
// visible label) is what these helpers read/assert on.
async function selectSearchableOption(page: Page, testId: string, optionLabel: string): Promise<void> {
  const root = page.getByTestId(testId)
  await root.locator('input').click()

  const option = root.getByRole('option', { name: optionLabel, exact: true })
  await option.waitFor({ state: 'visible', timeout: 15_000 })
  await option.click()
}

async function searchableSelectOptionValues(page: Page, testId: string): Promise<string[]> {
  const root = page.getByTestId(testId)
  await root.locator('input').click()

  return root.getByRole('option').evaluateAll((options) => options.map((option) => option.getAttribute('data-value') ?? ''))
}

/**
 * Seeds/overrides the local (offline) `station` table's active Cages
 * Track station row directly via the dev-only `window.__mslTestDb` bridge
 * — same technique/rationale as helpers.ts's `seedStations()` (there is
 * no in-app sync flow that reliably populates this table in this test
 * environment — see this file's header comment above). Uses the same
 * deterministic id (`e2e-station-cages-track`) as `seedStations()` so a
 * test that also seeds the full station grid doesn't end up with two
 * competing Cages Track rows. `jumlahCages: null` deletes the row instead,
 * to simulate "station not synced locally yet for this business unit"
 * (distinct from a synced-but-zero value).
 */
async function seedCagesTrackStationMachineryCount(
  page: Page,
  businessUnitId: string,
  jumlahCages: number | null,
): Promise<void> {
  await page.evaluate(
    async ({ buId, jumlahCages }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb

      if (jumlahCages === null) {
        await db.run('DELETE FROM station WHERE id = ?', ['e2e-station-cages-track'])
        return
      }

      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO station (id, business_unit_id, name, type, is_active, machinery_count, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        ['e2e-station-cages-track', buId, 'Cages Track 01', 'cages-track', 1, jumlahCages, now, now],
      )
    },
    { buId: businessUnitId, jumlahCages },
  )
}

test.describe('Form Cages Track (screen-012)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)

    // Default: the local `station` cache already has a positive
    // machinery_count for the active Cages Track station, matching
    // entity-catalog's test_fixture — keeps every scenario below that adds
    // a detail row working, now that "Tambah baris"/the grid's column count
    // are machinery_count-driven rather than Cages Tipped-header-driven
    // (2026-08-20, tech-spec v4). Scenarios exercising the
    // machinery_count-driven behavior itself re-seed with a different
    // value.
    const businessUnitId = await getBusinessUnitId(page)
    await seedCagesTrackStationMachineryCount(page, businessUnitId, DEFAULT_MACHINERY_COUNT)
  })

  test('Form Cages Track — success as Station Operator', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    await fillRequiredHeaderFields(page)

    await page.getByTestId('add-tipped-time-row-button').click()
    await selectSearchableOption(page, 'tipped-hour-select-0', hourLabel(7))
    await page.getByTestId('cage-checkbox-0-1').check()
    await page.getByTestId('cage-checkbox-0-2').check()

    expect(await page.getByTestId('checked-by-toggle').isDisabled()).toBe(true)
    expect(await page.getByTestId('acknowledged-by-toggle').isDisabled()).toBe(true)

    await page.getByTestId('row-total-cages-0').textContent().then((t) => expect(t).toContain('2'))
    // cages_remain = the Cages Track station's machinery_count
    // (DEFAULT_MACHINERY_COUNT = 10) minus this row's own total_cages (2)
    // — NOT derived from the Cages Tipped header field (filled to 5 above
    // by fillRequiredHeaderFields).
    await page.getByTestId('row-cages-remain-0').textContent().then((t) => expect(t).toContain('8'))

    await page.getByTestId('save-button').click()
    await page.waitForURL('**/stations/cages-track/monitor')
  })

  test('Form Cages Track — success as Supervisor (Checked By)', async ({ page }) => {
    await login(page, USERS.supervisor)
    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    await fillRequiredHeaderFields(page)
    expect(await page.getByTestId('checked-by-toggle').isDisabled()).toBe(false)
    await page.getByTestId('checked-by-toggle').check()
    expect(await page.getByTestId('acknowledged-by-toggle').isDisabled()).toBe(true)

    await page.getByTestId('add-tipped-time-row-button').click()
    await selectSearchableOption(page, 'tipped-hour-select-0', hourLabel(7))
    await page.getByTestId('cage-checkbox-0-1').check()

    await page.getByTestId('save-button').click()
    await page.waitForURL('**/stations/cages-track/monitor')
  })

  test('Form Cages Track — Field Wajib Belum Lengkap', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    await page.getByTestId('save-button').click()

    await expect(page.getByText('wajib diisi.').first()).toBeVisible()
    await expect(page).toHaveURL(/\/stations\/cages-track\/form\//)
  })

  test('Form Cages Track — Belum Ada Baris Cages Tipped Time Valid', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    await fillRequiredHeaderFields(page)
    await page.getByTestId('save-button').click()

    await expect(page.getByTestId('tipped-time-rows-error')).toContainText('Minimal 1 baris')
    await expect(page).toHaveURL(/\/stations\/cages-track\/form\//)
  })

  // Replaces the pre-2026-08-20 "Cages Tipped Belum Diisi Menonaktifkan
  // Tambah Baris" scenario: "Tambah baris" is no longer gated on the Cages
  // Tipped header field — it's gated on the local `station` cache's
  // machinery_count for the active Cages Track station (business_logic
  // step 5, tech-spec v4).
  test('Form Cages Track — Data Jumlah Cages Mills Setting Belum Tersedia Menonaktifkan Tambah Baris', async ({ page }) => {
    const businessUnitId = await getBusinessUnitId(page)
    await seedCagesTrackStationMachineryCount(page, businessUnitId, null)

    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    await expect(page.getByTestId('add-tipped-time-row-button')).toBeDisabled()
    await expect(page.getByTestId('add-row-jumlah-cages-hint')).toBeVisible()

    await seedCagesTrackStationMachineryCount(page, businessUnitId, DEFAULT_MACHINERY_COUNT)
    await page.reload()

    await expect(page.getByTestId('add-tipped-time-row-button')).toBeEnabled()
    await expect(page.getByTestId('add-row-jumlah-cages-hint')).toHaveCount(0)
  })

  // New scenario (2026-08-20, tech-spec v3): "Input Data Cages Track —
  // Jumlah Kolom Grid Mengikuti Mills Setting, Bukan Cages Tipped Header".
  test('Form Cages Track — Jumlah Kolom Grid Mengikuti Mills Setting, Bukan Cages Tipped Header', async ({ page }) => {
    const businessUnitId = await getBusinessUnitId(page)
    await seedCagesTrackStationMachineryCount(page, businessUnitId, 8)

    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    await page.locator('#field-cages-track-number').fill('CT-MS-001')
    await page.locator('#field-cages-out').fill('12')
    await page.locator('#field-cages-tipped').fill('15')

    await page.getByTestId('add-tipped-time-row-button').click()

    await expect(page.locator('[data-testid="cage-checkbox-grid-0"] input[type="checkbox"]')).toHaveCount(8)
    await expect(page.getByTestId('cage-checkbox-0-8')).toBeVisible()
    await expect(page.getByTestId('cage-checkbox-0-9')).toHaveCount(0)
  })

  test('Form Cages Track — Time Tidak Bisa Duplikat Atau Mundur', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    await page.getByTestId('add-tipped-time-row-button').click()
    await selectSearchableOption(page, 'tipped-hour-select-0', hourLabel(7))
    await page.getByTestId('add-tipped-time-row-button').click()

    // SearchableSelect.vue has no placeholder *option* at all (unlike the
    // old plain <select>'s `:value="null"` placeholder item) — every
    // rendered `role="option"` here is a real, selectable hour.
    const row1Values = await searchableSelectOptionValues(page, 'tipped-hour-select-1')

    for (const v of row1Values) {
      expect(Number(v)).toBeGreaterThan(7)
    }
  })

  test('Form Cages Track — Hapus Baris Membebaskan Jam', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    await page.getByTestId('add-tipped-time-row-button').click()
    await selectSearchableOption(page, 'tipped-hour-select-0', hourLabel(7))
    await page.getByTestId('add-tipped-time-row-button').click()

    await page.getByTestId('remove-tipped-time-row-button').first().click()

    const row0Values = await searchableSelectOptionValues(page, 'tipped-hour-select-0')
    expect(row0Values).toContain('7')
  })

  test('Form Cages Track — Checked By Khusus Supervisor', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    await expect(page.getByTestId('checked-by-toggle')).toBeDisabled()
  })

  test('Form Cages Track — Acknowledged By Khusus Mill Management', async ({ page }) => {
    await login(page, USERS.supervisor)
    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    await expect(page.getByTestId('acknowledged-by-toggle')).toBeDisabled()
    await expect(page.getByTestId('checked-by-toggle')).toBeEnabled()
  })

  test('Form Cages Track — Lanjutkan Draft Paused', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)
    const recordId = page.url().split('/').pop() as string

    await page.locator('#field-cages-track-number').fill('CT-PAUSE-001')
    await page.getByTestId('pause-button').click()
    await page.waitForURL('**/stations/cages-track/monitor')

    await page.getByTestId(`draft-item-${recordId}`).click()
    await page.waitForURL(new RegExp(`/stations/cages-track/form/${recordId}`))

    await expect(page.locator('#field-cages-track-number')).toHaveValue('CT-PAUSE-001')
    void userId
  })

  test('Form Cages Track — Pause Progress (tanpa validasi, tidak membekukan Tippler Stop Time)', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    // Leave required fields empty — Pause must succeed anyway.
    await page.getByTestId('pause-button').click()
    await page.waitForURL('**/stations/cages-track/monitor')
  })

  test('Form Cages Track — Clear Draft (confirm)', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    await page.getByTestId('clear-button').click()
    await page.locator('.confirm-dialog-button--confirm').click()
    await page.waitForURL('**/stations/cages-track/monitor')
  })

  test('Form Cages Track — Clear Draft (cancel)', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    await page.getByTestId('clear-button').click()
    await page.locator('.confirm-dialog-button--cancel').click()

    await expect(page).toHaveURL(/\/stations\/cages-track\/form\//)
  })

  test('Form Cages Track — Back Dengan Perubahan Belum Tersimpan', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    await page.locator('#field-cages-track-number').fill('CT-DIRTY')
    await page.getByTestId('back-button').click()

    await expect(page.getByRole('alertdialog')).toBeVisible()
    await expect(page).toHaveURL(/\/stations\/cages-track\/form\//)
  })

  test('Form Cages Track — Tap Breadcrumb', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    await page.getByTestId('breadcrumb-cages-track').click()
    await page.waitForURL('**/stations/cages-track/monitor')
  })

  test('Form Cages Track — Buka Menu Hamburger', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu-change-password')).toBeVisible()
    await expect(page.getByTestId('nav-menu-logout')).toBeVisible()
  })
})
