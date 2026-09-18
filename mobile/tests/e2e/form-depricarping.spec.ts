import { test, expect } from '@playwright/test'
import { login } from './helpers'

// screen-043--form-depricarping / usecase-043--form-depricarping
//
// REVISED 2026-08-24 (entity-catalog v12): the Depricarping Detail grid is
// now a dynamic add-row/remove-row grid — exactly like
// form-pressing.spec.ts's/form-threshing.spec.ts's Detail grid. A fresh
// draft (created via Monitor Depricarping's 'New Data') starts with ZERO
// rows; "Tambah baris" adds one row at a time, then a Time-Slot is picked
// from its dropdown before the reading fields can be usefully filled.

// Time-Slot is a SearchableSelect.vue instance (typeable/searchable
// combobox) — mirrors form-pressing.spec.ts's
// selectSearchableOption()/searchableSelectOptionValues() helpers exactly.
async function selectSearchableOption(page: import('@playwright/test').Page, testId: string, optionLabel: string): Promise<void> {
  const root = page.getByTestId(testId)
  await root.locator('input').click()

  const option = root.getByRole('option', { name: optionLabel, exact: true })
  await option.waitFor({ state: 'visible', timeout: 15_000 })
  await option.click()
}

test.describe('Form Depricarping (screen-043)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
    await page.goto('/stations/depricarping/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/depricarping\/form\/[^/]+$/)
  })

  test('Input Data Depricarping — success as Station Operator', async ({ page }) => {
    await page.locator('#field-presser-id').fill('DP-E2E-001')
    await page.getByTestId('add-detail-row-button').click()
    await selectSearchableOption(page, 'time-slot-select-0', '07:00')
    await page.locator('#fan-static-pressure-0').fill('45')
    await page.getByTestId('save-button').click()

    await page.waitForURL('**/stations/depricarping/monitor')
  })

  test('Input Data Depricarping — Presser ID Belum Lengkap', async ({ page }) => {
    await page.getByTestId('save-button').click()

    await expect(page.getByText('Presser ID wajib diisi.')).toBeVisible()
    await expect(page).toHaveURL(/\/stations\/depricarping\/form\//)
  })

  test('Input Data Depricarping — Belum Ada Baris Terisi', async ({ page }) => {
    await page.locator('#field-presser-id').fill('DP-E2E-002')
    await page.getByTestId('save-button').click()

    await expect(page.getByTestId('detail-rows-error')).toBeVisible()
    await expect(page).toHaveURL(/\/stations\/depricarping\/form\//)
  })

  test('Input Data Depricarping — baris terisi hanya via Downtime (Mins) atau Findings dianggap valid', async ({
    page,
  }) => {
    await page.locator('#field-presser-id').fill('DP-E2E-003')
    await page.getByTestId('add-detail-row-button').click()
    await selectSearchableOption(page, 'time-slot-select-0', '07:00')
    await page.locator('#findings-0').fill('Fan belt loose')
    await page.getByTestId('save-button').click()

    await page.waitForURL('**/stations/depricarping/monitor')
  })

  test('Input Data Depricarping — Tambah/Hapus Baris dan Urutan Time-Slot', async ({ page }) => {
    await page.getByTestId('add-detail-row-button').click()
    await selectSearchableOption(page, 'time-slot-select-0', '07:00')
    await page.getByTestId('add-detail-row-button').click()

    const row1Root = page.getByTestId('time-slot-select-1')
    await row1Root.locator('input').click()
    await expect(row1Root.getByRole('option', { name: '07:00', exact: true })).toHaveCount(0)
    await expect(row1Root.getByRole('option', { name: '08:00', exact: true })).toBeVisible()

    await page.getByTestId('remove-detail-row-button').first().click()
    await expect(page.getByTestId('depricarping-detail-row')).toHaveCount(1)
  })

  test('Input Data Depricarping — Checked By Khusus Supervisor (Operator tidak dapat mengisi)', async ({ page }) => {
    // 2026-09-14: both toggles are now HIDDEN from roles that cannot write
    // them, not rendered-and-disabled. These specs had never run, so the
    // change landed without this contract being visibly broken.
    await expect(page.getByTestId('checked-by-toggle')).toHaveCount(0)
    await expect(page.getByTestId('acknowledged-by-toggle')).toHaveCount(0)
  })

  test('Input Data Depricarping — Pause Progress', async ({ page }) => {
    await page.getByTestId('pause-button').click()

    await page.waitForURL('**/stations/depricarping/monitor')
  })

  test('Input Data Depricarping — Clear Draft', async ({ page }) => {
    await page.getByTestId('clear-button').click()
    await page.getByText('Ya, Hapus').click()

    await page.waitForURL('**/stations/depricarping/monitor')
  })

  test('Input Data Depricarping — Back Dengan Perubahan Belum Tersimpan', async ({ page }) => {
    await page.locator('#field-presser-id').fill('DP-E2E-DIRTY')
    await page.getByTestId('back-button').click()

    await expect(page.getByText('Ada perubahan yang belum disimpan.')).toBeVisible()
  })

  test('Input Data Depricarping — Tabel Target Operasional Read-Only (4 kolom, 6 baris)', async ({ page }) => {
    const table = page.getByTestId('operational-target-table')
    await expect(table).toBeVisible()
    await expect(table.locator('tbody tr')).toHaveCount(6)
    await expect(table.locator('thead th')).toHaveCount(4)
    await expect(table).toContainText('Fan Static Pressure')
  })
})
