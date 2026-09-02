import { test, expect } from '@playwright/test'
import { login } from './helpers'

// screen-075--form-effluent-plant / usecase-086--form-effluent-plant
//
// Mirrors form-threshing.spec.ts's structure exactly — Effluent Plant
// follows the same hourly-grid, dynamic add-row/remove-row pattern. A
// fresh draft (created via Monitor Effluent Plant's 'New Data') starts with
// ZERO rows; "Tambah baris" adds one row at a time, then a Time-Slot is
// picked from its dropdown before the reading fields can be usefully
// filled. UNLIKE form-threshing.spec.ts: this station has NO
// operational-target reference table, so no such test appears here.

// Time-Slot is a SearchableSelect.vue instance (typeable/searchable
// combobox) — mirrors form-threshing.spec.ts's selectSearchableOption()
// helper exactly.
async function selectSearchableOption(page: import('@playwright/test').Page, testId: string, optionLabel: string): Promise<void> {
  const root = page.getByTestId(testId)
  await root.locator('input').click()

  const option = root.getByRole('option', { name: optionLabel, exact: true })
  await option.waitFor({ state: 'visible', timeout: 15_000 })
  await option.click()
}

test.describe('Form Effluent Plant (screen-075)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
    await page.goto('/stations/effluent-plant/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/effluent-plant\/form\/[^/]+$/)
  })

  test('Input Data Effluent Plant — success as Station Operator', async ({ page }) => {
    await page.locator('#field-effluent-plant-id').fill('EP-E2E-001')
    await page.getByTestId('add-detail-row-button').click()
    await selectSearchableOption(page, 'time-slot-select-0', '07:00')
    await page.locator('#anaerobic-pond-1-ph-0').fill('45.5')
    await page.getByTestId('save-button').click()

    await page.waitForURL('**/stations/effluent-plant/monitor')
  })

  test('Input Data Effluent Plant — Effluent Plant ID Belum Lengkap', async ({ page }) => {
    await page.getByTestId('save-button').click()

    await expect(page.getByText('Effluent Plant ID wajib diisi.')).toBeVisible()
    await expect(page).toHaveURL(/\/stations\/effluent-plant\/form\//)
  })

  test('Input Data Effluent Plant — Belum Ada Baris Terisi', async ({ page }) => {
    await page.locator('#field-effluent-plant-id').fill('EP-E2E-002')
    await page.getByTestId('save-button').click()

    await expect(page.getByTestId('detail-rows-error')).toBeVisible()
    await expect(page).toHaveURL(/\/stations\/effluent-plant\/form\//)
  })

  test('Input Data Effluent Plant — Tambah/Hapus Baris dan Urutan Time-Slot', async ({ page }) => {
    await page.getByTestId('add-detail-row-button').click()
    await selectSearchableOption(page, 'time-slot-select-0', '07:00')
    await page.getByTestId('add-detail-row-button').click()

    const row1Root = page.getByTestId('time-slot-select-1')
    await row1Root.locator('input').click()
    await expect(row1Root.getByRole('option', { name: '07:00', exact: true })).toHaveCount(0)
    await expect(row1Root.getByRole('option', { name: '08:00', exact: true })).toBeVisible()

    await page.getByTestId('remove-detail-row-button').first().click()
    await expect(page.getByTestId('effluent-plant-detail-row')).toHaveCount(1)
  })

  test('Input Data Effluent Plant — Checked By Khusus Supervisor (Operator tidak dapat mengisi)', async ({ page }) => {
    await expect(page.getByTestId('checked-by-toggle')).toBeDisabled()
    await expect(page.getByTestId('acknowledged-by-toggle')).toBeDisabled()
  })

  test('Input Data Effluent Plant — Pause Progress', async ({ page }) => {
    await page.getByTestId('pause-button').click()

    await page.waitForURL('**/stations/effluent-plant/monitor')
  })

  test('Input Data Effluent Plant — Clear Draft', async ({ page }) => {
    await page.getByTestId('clear-button').click()
    await page.getByText('Ya, Hapus').click()

    await page.waitForURL('**/stations/effluent-plant/monitor')
  })

  test('Input Data Effluent Plant — Back Dengan Perubahan Belum Tersimpan', async ({ page }) => {
    await page.locator('#field-effluent-plant-id').fill('EP-E2E-DIRTY')
    await page.getByTestId('back-button').click()

    await expect(page.getByText('Ada perubahan yang belum disimpan.')).toBeVisible()
  })
})
