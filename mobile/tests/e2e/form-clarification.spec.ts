import { test, expect, type Page } from '@playwright/test'
import { login, USERS } from './helpers'

// screen-079--form-clarification / usecase-110--form-clarification
//
// Mirrors form-effluent-plant.spec.ts's structure — Clarification follows the
// same hourly-grid, dynamic add-row/remove-row pattern. A fresh draft
// (created via Monitor Clarification's 'New Data') starts with ZERO rows;
// "Tambah baris" adds one row at a time, then a Time-Slot is picked from its
// SearchableSelect before the reading fields are filled. This station has NO
// operational-target reference table, so no such test appears here.
//
// Write-through saving: when Mills Setting immediate_sync_enabled is on,
// Simpan also POSTs the record to /api/clarification-records. This suite
// must not depend on that flag nor write into the dev backend, so every test
// aborts that request — writeThroughSync treats a network failure as
// "offline, stay silent" and the form still navigates to Monitor.

const RECORDS_API = '**/api/clarification-records**'

async function selectSearchableOption(page: Page, testId: string, optionLabel: string): Promise<void> {
  const root = page.getByTestId(testId)
  await root.locator('input').click()

  const option = root.getByRole('option', { name: optionLabel, exact: true })
  await option.waitFor({ state: 'visible', timeout: 15_000 })
  await option.click()
}

async function openNewDraft(page: Page): Promise<string> {
  await page.goto('/stations/clarification/monitor')
  await page.getByTestId('new-data-button').click()
  await page.waitForURL(/\/stations\/clarification\/form\/[^/]+$/)
  await expect(page.locator('#field-clarification-id')).toBeVisible()
  return page.url().split('/').pop() as string
}

test.describe('Form Clarification (screen-079)', () => {
  test.beforeEach(async ({ page }) => {
    await page.route(RECORDS_API, (route) => route.abort())
    await login(page)
  })

  test('Input Data Clarification — success', async ({ page }) => {
    const recordId = await openNewDraft(page)

    // Grid starts empty.
    await expect(page.getByTestId('clarification-detail-row')).toHaveCount(0)

    await page.locator('#field-clarification-id').fill('CLR-E2E-001')
    await page.getByTestId('add-detail-row-button').click()
    await selectSearchableOption(page, 'time-slot-select-0', '07:00')
    await page.locator('#clarification-tank-temp-0').fill('92.5')
    await page.getByTestId('save-button').click()

    await page.waitForURL('**/stations/clarification/monitor')

    // The saved record now shows status Tersimpan in Data Preview.
    await page.goto('/stations/clarification/preview')
    await expect(page.getByTestId(`record-item-${recordId}`)).toContainText('CLR-E2E-001')
    await expect(page.getByTestId(`record-item-${recordId}`)).toContainText('Tersimpan')
  })

  test('Input Data Clarification — Clarification ID Belum Lengkap', async ({ page }) => {
    await openNewDraft(page)

    await page.getByTestId('save-button').click()

    await expect(page.getByText('Clarification ID wajib diisi.')).toBeVisible()
    await expect(page).toHaveURL(/\/stations\/clarification\/form\//)
  })

  test('Input Data Clarification — Belum Ada Baris Valid', async ({ page }) => {
    const recordId = await openNewDraft(page)

    await page.locator('#field-clarification-id').fill('CLR-E2E-002')
    await page.getByTestId('save-button').click()

    await expect(page.getByTestId('detail-rows-error')).toContainText('Minimal 1 baris Clarification Detail')
    await expect(page).toHaveURL(/\/stations\/clarification\/form\//)

    // Not saved: the record is still a draft (Pause) in Data Preview.
    await page.goto('/stations/clarification/preview')
    await expect(page.getByTestId(`record-item-${recordId}`)).toContainText('Pause')
  })

  test('Input Data Clarification — Tambah/Hapus Baris dan Urutan Time-Slot', async ({ page }) => {
    await openNewDraft(page)

    await page.getByTestId('add-detail-row-button').click()
    await expect(page.getByTestId('clarification-detail-row')).toHaveCount(1)
    await selectSearchableOption(page, 'time-slot-select-0', '07:00')

    await page.getByTestId('add-detail-row-button').click()
    await expect(page.getByTestId('clarification-detail-row')).toHaveCount(2)

    // Row 2 only offers slots after 07:00.
    const row1Root = page.getByTestId('time-slot-select-1')
    await row1Root.locator('input').click()
    await expect(row1Root.getByRole('option', { name: '08:00', exact: true })).toBeVisible()
    await expect(row1Root.getByRole('option', { name: '07:00', exact: true })).toHaveCount(0)
    await row1Root.getByRole('option', { name: '08:00', exact: true }).click()

    // Remove the first row — 07:00 is free again for the remaining row.
    await page.getByTestId('remove-detail-row-button').first().click()
    await expect(page.getByTestId('clarification-detail-row')).toHaveCount(1)

    const remainingRoot = page.getByTestId('time-slot-select-0')
    await remainingRoot.locator('input').click()
    await expect(remainingRoot.getByRole('option', { name: '07:00', exact: true })).toBeVisible()
  })

  test('Input Data Clarification — Baris Valid Hanya Dari Findings', async ({ page }) => {
    const recordId = await openNewDraft(page)

    await page.locator('#field-clarification-id').fill('CLR-E2E-FINDINGS')
    await page.getByTestId('add-detail-row-button').click()
    await selectSearchableOption(page, 'time-slot-select-0', '08:00')
    await page.locator('#findings-0').fill('Perlu ditinjau')
    await page.getByTestId('save-button').click()

    await page.waitForURL('**/stations/clarification/monitor')

    await page.goto('/stations/clarification/preview')
    await expect(page.getByTestId(`record-item-${recordId}`)).toContainText('Tersimpan')
  })

  test('Input Data Clarification — Checked By Khusus Supervisor (Operator tidak dapat mengisi)', async ({ page }) => {
    await openNewDraft(page)

    // Hidden from roles that cannot write them (2026-09-14), not disabled.
    await expect(page.getByTestId('inputted-by-display')).toBeVisible()
    await expect(page.getByTestId('checked-by-toggle')).toHaveCount(0)
    await expect(page.getByTestId('acknowledged-by-toggle')).toHaveCount(0)
  })

  test('Input Data Clarification — Supervisor dapat mengisi Checked By', async ({ page }) => {
    await login(page, USERS.supervisor)
    await openNewDraft(page)

    await expect(page.getByTestId('checked-by-toggle')).toBeEnabled()
    await expect(page.getByTestId('acknowledged-by-toggle')).toHaveCount(0)
  })

  test('Input Data Clarification — Pause Progress', async ({ page }) => {
    const recordId = await openNewDraft(page)

    // Clarification ID left empty — Pause must not validate.
    await page.getByTestId('pause-button').click()

    await page.waitForURL('**/stations/clarification/monitor')
    await expect(page.getByTestId(`draft-item-${recordId}`)).toContainText('Pause')
  })

  test('Input Data Clarification — Clear Draft', async ({ page }) => {
    const recordId = await openNewDraft(page)
    await page.locator('#field-clarification-id').fill('CLR-E2E-CLEAR')
    await page.getByTestId('pause-button').click()
    await page.waitForURL('**/stations/clarification/monitor')

    // Present first, in both Monitor and Data Preview.
    await expect(page.getByTestId(`draft-item-${recordId}`)).toBeVisible()
    await page.goto('/stations/clarification/preview')
    await expect(page.getByTestId(`record-item-${recordId}`)).toBeVisible()

    await page.goto(`/stations/clarification/form/${recordId}`)
    await expect(page.locator('#field-clarification-id')).toHaveValue('CLR-E2E-CLEAR')
    await page.getByTestId('clear-button').click()
    await page.getByRole('button', { name: 'Ya, Hapus' }).click()

    await page.waitForURL('**/stations/clarification/monitor')
    await expect(page.getByTestId('draft-list-empty')).toBeVisible()
    await expect(page.getByTestId(`draft-item-${recordId}`)).toHaveCount(0)

    await page.goto('/stations/clarification/preview')
    await expect(page.getByTestId('record-list-empty')).toBeVisible()
    await expect(page.getByTestId(`record-item-${recordId}`)).toHaveCount(0)
  })

  test('Input Data Clarification — Clear Draft dibatalkan', async ({ page }) => {
    await openNewDraft(page)

    await page.getByTestId('clear-button').click()
    await page.getByRole('button', { name: 'Batal' }).click()

    await expect(page.getByRole('alertdialog')).toHaveCount(0)
    await expect(page).toHaveURL(/\/stations\/clarification\/form\//)
  })

  test('Input Data Clarification — Back Dengan Perubahan Belum Tersimpan', async ({ page }) => {
    await openNewDraft(page)

    await page.locator('#field-clarification-id').fill('CLR-E2E-DIRTY')
    await page.getByTestId('back-button').click()

    await expect(page.getByText('Ada perubahan yang belum disimpan.')).toBeVisible()
    await expect(page).toHaveURL(/\/stations\/clarification\/form\//)
  })
})
