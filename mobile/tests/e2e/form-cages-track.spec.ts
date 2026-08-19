import { test, expect, type Page } from '@playwright/test'
import { login, USERS, getAuthUserId } from './helpers'

// screen-012--form-cages-track / usecase-012--form-cages-track
//
// FULL REWRITE (2026-08-19, entity-catalog v3), replacing every scenario in
// the previous version of this file (built against the pre-v3
// "No. Cages Track" + free-text cage/time-per-row model). Mirrors
// form-grading.spec.ts's structure/conventions.

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

test.describe('Form Cages Track (screen-012)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
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
    await page.getByTestId('row-cages-remain-0').textContent().then((t) => expect(t).toContain('3'))

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

  test('Form Cages Track — Cages Tipped Belum Diisi Menonaktifkan Tambah Baris', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    await expect(page.getByTestId('add-tipped-time-row-button')).toBeDisabled()

    await page.locator('#field-cages-tipped').fill('5')

    await expect(page.getByTestId('add-tipped-time-row-button')).toBeEnabled()
  })

  test('Form Cages Track — Cages Tipped Terkunci Setelah Baris Pertama Ditambahkan', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    await page.locator('#field-cages-tipped').fill('5')
    await expect(page.locator('#field-cages-tipped')).toBeEnabled()

    await page.getByTestId('add-tipped-time-row-button').click()

    await expect(page.locator('#field-cages-tipped')).toBeDisabled()
  })

  test('Form Cages Track — Time Tidak Bisa Duplikat Atau Mundur', async ({ page }) => {
    await page.goto('/stations/cages-track/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)

    await page.locator('#field-cages-tipped').fill('5')
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

    await page.locator('#field-cages-tipped').fill('5')
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
