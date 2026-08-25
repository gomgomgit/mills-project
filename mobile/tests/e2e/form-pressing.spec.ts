import { test, expect } from '@playwright/test'
import { login } from './helpers'

// screen-042--form-pressing / usecase-042--form-pressing
//
// REVISED 2026-08-24 (entity-catalog v12): the Pressing Detail grid is now
// a dynamic add-row/remove-row grid — exactly like form-threshing.spec.ts's
// Threshing Detail grid. A fresh draft (created via Monitor Pressing's
// 'New Data') starts with ZERO rows; "Tambah baris" adds one row at a time,
// then a Time-Slot is picked from its dropdown before the reading fields
// can be usefully filled.

// Time-Slot is a SearchableSelect.vue instance (typeable/searchable
// combobox) — mirrors form-threshing.spec.ts's
// selectSearchableOption()/searchableSelectOptionValues() helpers exactly.
async function selectSearchableOption(page: import('@playwright/test').Page, testId: string, optionLabel: string): Promise<void> {
  const root = page.getByTestId(testId)
  await root.locator('input').click()

  const option = root.getByRole('option', { name: optionLabel, exact: true })
  await option.waitFor({ state: 'visible', timeout: 15_000 })
  await option.click()
}

test.describe('Form Pressing (screen-042)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
    await page.goto('/stations/pressing/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/pressing\/form\/[^/]+$/)
  })

  test('Input Data Pressing — success as Station Operator', async ({ page }) => {
    await page.locator('#field-presser-id').fill('PR-E2E-001')
    await page.getByTestId('add-detail-row-button').click()
    await selectSearchableOption(page, 'time-slot-select-0', '07:00')
    await page.locator('#digester-temp-0').fill('92')
    await page.getByTestId('save-button').click()

    await page.waitForURL('**/stations/pressing/monitor')
  })

  test('Input Data Pressing — Presser ID Belum Lengkap', async ({ page }) => {
    await page.getByTestId('save-button').click()

    await expect(page.getByText('Presser ID wajib diisi.')).toBeVisible()
    await expect(page).toHaveURL(/\/stations\/pressing\/form\//)
  })

  test('Input Data Pressing — Belum Ada Baris Terisi', async ({ page }) => {
    await page.locator('#field-presser-id').fill('PR-E2E-002')
    await page.getByTestId('save-button').click()

    await expect(page.getByTestId('detail-rows-error')).toBeVisible()
    await expect(page).toHaveURL(/\/stations\/pressing\/form\//)
  })

  test('Input Data Pressing — Tambah/Hapus Baris dan Urutan Time-Slot', async ({ page }) => {
    await page.getByTestId('add-detail-row-button').click()
    await selectSearchableOption(page, 'time-slot-select-0', '07:00')
    await page.getByTestId('add-detail-row-button').click()

    const row1Root = page.getByTestId('time-slot-select-1')
    await row1Root.locator('input').click()
    await expect(row1Root.getByRole('option', { name: '07:00', exact: true })).toHaveCount(0)
    await expect(row1Root.getByRole('option', { name: '08:00', exact: true })).toBeVisible()

    await page.getByTestId('remove-detail-row-button').first().click()
    await expect(page.getByTestId('pressing-detail-row')).toHaveCount(1)
  })

  test('Input Data Pressing — Checked By Khusus Supervisor (Operator tidak dapat mengisi)', async ({ page }) => {
    await expect(page.getByTestId('checked-by-toggle')).toBeDisabled()
    await expect(page.getByTestId('acknowledged-by-toggle')).toBeDisabled()
  })

  test('Input Data Pressing — Pause Progress', async ({ page }) => {
    await page.getByTestId('pause-button').click()

    await page.waitForURL('**/stations/pressing/monitor')
  })

  test('Input Data Pressing — Clear Draft', async ({ page }) => {
    await page.getByTestId('clear-button').click()
    await page.getByText('Ya, Hapus').click()

    await page.waitForURL('**/stations/pressing/monitor')
  })

  test('Input Data Pressing — Back Dengan Perubahan Belum Tersimpan', async ({ page }) => {
    await page.locator('#field-presser-id').fill('PR-E2E-DIRTY')
    await page.getByTestId('back-button').click()

    await expect(page.getByText('Ada perubahan yang belum disimpan.')).toBeVisible()
  })

  test('Input Data Pressing — Tabel Target Operasional Read-Only', async ({ page }) => {
    const table = page.getByTestId('operational-target-table')
    await expect(table).toBeVisible()
    await expect(table.locator('tbody tr')).toHaveCount(7)
    await expect(table).toContainText('Digester Temperature')
  })
})
