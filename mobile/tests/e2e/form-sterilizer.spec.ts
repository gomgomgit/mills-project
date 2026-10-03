import { test, expect, type Page } from '@playwright/test'
import { login, USERS } from './helpers'

// screen-122--form-sterilizer / usecase-122--form-sterilizer
//
// Header + EVENT-LOG detail station (like form-cages-track.spec.ts's header
// + child rows, but the rows are a free table): "Log Siklus Sterilisasi" is
// one row per sterilization cycle, added via "+ Tambah Baris", unbounded.
// Duration (Minutes) = Open Door Time − Close Door Time is computed in the
// form and shown as a disabled, non-editable value (the server recomputes it
// on save — not observable from this offline browser suite).
//
// Unlike the hourly-grid stations, this form still RENDERS Checked By /
// Acknowledged By for every role and disables them for roles that cannot
// write them (tech-spec business_logic steps 8-9).
//
// Write-through saving: Simpan may also POST to /api/sterilizer-records when
// Mills Setting immediate_sync_enabled is on. Every test aborts that request
// so the suite neither depends on the flag nor writes into the dev backend
// (a network failure is silent; the record stays saved locally).

const RECORDS_API = '**/api/sterilizer-records**'

async function openNewDraft(page: Page): Promise<string> {
  await page.goto('/stations/sterilizer/monitor')
  await page.getByTestId('new-data-button').click()
  await page.waitForURL(/\/stations\/sterilizer\/form\/[^/]+$/)
  await expect(page.locator('#sterilizer_id')).toBeVisible()
  return page.url().split('/').pop() as string
}

function row(page: Page, index: number) {
  return page.getByTestId(`detail-row-${index}`)
}

/** Fills one complete cycle row (every column but Duration, which is derived). */
async function fillCompleteRow(page: Page, index: number, values: { no: string; close: string; open: string }): Promise<void> {
  const r = row(page, index)
  const textInputs = r.locator('input[type="text"]:not([disabled])')
  const timeInputs = r.locator('input[type="time"]')

  await textInputs.nth(0).fill(values.no) // Sterilizer No
  await timeInputs.nth(0).fill(values.close) // Close Door Time
  await timeInputs.nth(1).fill('07:20') // Peak 1
  await timeInputs.nth(2).fill('07:25') // Exhaust 1
  await timeInputs.nth(3).fill('07:35') // Peak 2
  await timeInputs.nth(4).fill('07:40') // Exhaust 2
  await timeInputs.nth(5).fill('07:50') // Peak 3
  await timeInputs.nth(6).fill('07:55') // Exhaust 3
  await timeInputs.nth(7).fill(values.open) // Open Door Time
  await r.locator('input[type="number"]').fill('10') // Number of Cages
  await textInputs.nth(1).fill('Lengkap') // Cages Status
  await page.getByTestId(`detail-checked-by-spv-${index}`).check()
  await textInputs.nth(2).fill('Normal') // Remarks
}

test.describe('Form Sterilizer (screen-122)', () => {
  test.beforeEach(async ({ page }) => {
    await page.route(RECORDS_API, (route) => route.abort())
    await login(page)
  })

  test('Input Data Sterilizer — success sebagai Station Operator', async ({ page }) => {
    const recordId = await openNewDraft(page)

    await page.locator('#sterilizer_id').fill('STR-E2E-001')
    await page.getByTestId('add-row-button').click()
    await fillCompleteRow(page, 0, { no: '1', close: '07:00', open: '08:10' })

    // Operator cannot touch the header verification fields.
    await expect(page.getByTestId('checked-by-checkbox')).toBeDisabled()
    await expect(page.getByTestId('acknowledged-by-checkbox')).toBeDisabled()

    await page.getByTestId('save-button').click()
    await page.waitForURL('**/stations/sterilizer/monitor')

    await page.goto('/stations/sterilizer/preview')
    await expect(page.getByTestId(`record-item-${recordId}`)).toContainText('STR-E2E-001')
    await expect(page.getByTestId(`record-item-${recordId}`)).toContainText('Tersimpan')
  })

  test('Input Data Sterilizer — success sebagai Supervisor dengan Checked By', async ({ page }) => {
    await login(page, USERS.supervisor)
    const recordId = await openNewDraft(page)

    await page.locator('#sterilizer_id').fill('STR-E2E-SPV')
    await expect(page.getByTestId('checked-by-checkbox')).toBeEnabled()
    await expect(page.getByTestId('acknowledged-by-checkbox')).toBeDisabled()
    await page.getByTestId('checked-by-checkbox').check()

    await page.getByTestId('add-row-button').click()
    await fillCompleteRow(page, 0, { no: '2', close: '09:00', open: '10:15' })

    await page.getByTestId('save-button').click()
    await page.waitForURL('**/stations/sterilizer/monitor')

    // Record saved with Checked By filled.
    await page.goto(`/stations/sterilizer/preview/${recordId}`)
    await expect(page.getByTestId('detail-sterilizer-id')).toContainText('STR-E2E-SPV')
    await expect(page.getByTestId('verify-status-checked-by')).toContainText('Oleh')
    await expect(page.getByTestId('verify-status-checked-by')).not.toContainText('Belum diperiksa Supervisor')
  })

  test('Input Data Sterilizer — Field Wajib Belum Lengkap', async ({ page }) => {
    const recordId = await openNewDraft(page)

    await page.getByTestId('add-row-button').click()
    await page.getByTestId('detail-close-door-time-0').fill('07:00')
    await page.getByTestId('save-button').click()

    await expect(page.getByText('Sterilizer ID wajib diisi.')).toBeVisible()
    await expect(page).toHaveURL(/\/stations\/sterilizer\/form\//)

    // Not saved — still a draft (Pause) in Data Preview.
    await page.goto('/stations/sterilizer/preview')
    await expect(page.getByTestId(`record-item-${recordId}`)).toContainText('Pause')
  })

  test('Input Data Sterilizer — Belum Ada Baris Log Siklus Valid', async ({ page }) => {
    await openNewDraft(page)

    await page.locator('#sterilizer_id').fill('STR-E2E-NOROW')
    await page.getByTestId('save-button').click()

    await expect(page.getByTestId('detail-rows-error')).toContainText('Close Door Time wajib')
    await expect(page).toHaveURL(/\/stations\/sterilizer\/form\//)

    // A row without Close Door Time still does not count.
    await page.getByTestId('add-row-button').click()
    await page.getByTestId('detail-open-door-time-0').fill('08:00')
    await page.getByTestId('save-button').click()
    await expect(page.getByTestId('detail-rows-error')).toBeVisible()
    await expect(page).toHaveURL(/\/stations\/sterilizer\/form\//)
  })

  test('Input Data Sterilizer — Duration Dihitung Otomatis', async ({ page }) => {
    await openNewDraft(page)

    await page.getByTestId('add-row-button').click()
    const duration0 = page.getByTestId('detail-duration-minutes-0')
    await expect(duration0).toHaveValue('-')

    await page.getByTestId('detail-close-door-time-0').fill('07:00')
    await page.getByTestId('detail-open-door-time-0').fill('08:10')
    await expect(duration0).toHaveValue('70')
    // Not editable.
    await expect(duration0).toBeDisabled()

    // A cycle crossing midnight wraps 24h.
    await page.getByTestId('add-row-button').click()
    await page.getByTestId('detail-close-door-time-1').fill('23:30')
    await page.getByTestId('detail-open-door-time-1').fill('00:15')
    await expect(page.getByTestId('detail-duration-minutes-1')).toHaveValue('45')
  })

  test('Input Data Sterilizer — Checked By Khusus Supervisor', async ({ page }) => {
    await openNewDraft(page)

    await expect(page.getByTestId('checked-by-checkbox')).toBeVisible()
    await expect(page.getByTestId('checked-by-checkbox')).toBeDisabled()
    await expect(page.getByTestId('acknowledged-by-checkbox')).toBeDisabled()
  })

  test('Input Data Sterilizer — Hapus Baris Log Siklus', async ({ page }) => {
    const recordId = await openNewDraft(page)

    // Two rows persisted first (Pause), so both have ids.
    await page.locator('#sterilizer_id').fill('STR-E2E-HAPUS')
    await page.getByTestId('add-row-button').click()
    await page.getByTestId('detail-close-door-time-0').fill('07:00')
    await page.getByTestId('add-row-button').click()
    await page.getByTestId('detail-close-door-time-1').fill('09:00')
    await page.getByTestId('pause-button').click()
    await page.waitForURL('**/stations/sterilizer/monitor')

    await page.getByTestId(`draft-item-${recordId}`).click()
    await page.waitForURL(new RegExp(`/stations/sterilizer/form/${recordId}$`))
    await expect(page.locator('[data-testid^="detail-row-"]')).toHaveCount(2)

    await page.getByTestId('remove-row-button-0').click()
    await expect(page.locator('[data-testid^="detail-row-"]')).toHaveCount(1)
    await expect(page.getByTestId('detail-close-door-time-0')).toHaveValue('09:00')

    await page.getByTestId('save-button').click()
    await page.waitForURL('**/stations/sterilizer/monitor')

    // Permanently deleted after the next Simpan.
    await page.goto(`/stations/sterilizer/preview/${recordId}`)
    const log = page.getByTestId('sterilizer-detail-log')
    await expect(log.locator('tbody tr')).toHaveCount(1)
    await expect(log).toContainText('09:00')
    await expect(log).not.toContainText('07:00')
  })

  test('Input Data Sterilizer — Lanjutkan Draft Paused', async ({ page }) => {
    const recordId = await openNewDraft(page)

    await page.locator('#sterilizer_id').fill('STR-E2E-RESUME')
    await page.getByLabel('Note').fill('Catatan draft')
    await page.getByTestId('add-row-button').click()
    await fillCompleteRow(page, 0, { no: '3', close: '07:00', open: '08:10' })
    await page.getByTestId('pause-button').click()
    await page.waitForURL('**/stations/sterilizer/monitor')

    await page.getByTestId(`draft-item-${recordId}`).click()
    await page.waitForURL(new RegExp(`/stations/sterilizer/form/${recordId}$`))

    await expect(page.locator('#sterilizer_id')).toHaveValue('STR-E2E-RESUME')
    await expect(page.getByLabel('Note')).toHaveValue('Catatan draft')
    const r = row(page, 0)
    await expect(r.locator('input[type="text"]').nth(0)).toHaveValue('3')
    await expect(page.getByTestId('detail-close-door-time-0')).toHaveValue('07:00')
    await expect(page.getByTestId('detail-open-door-time-0')).toHaveValue('08:10')
    await expect(page.getByTestId('detail-duration-minutes-0')).toHaveValue('70')
    await expect(r.locator('input[type="number"]')).toHaveValue('10')
    await expect(page.getByTestId('detail-checked-by-spv-0')).toBeChecked()
  })

  test('Input Data Sterilizer — Back Dengan Perubahan Belum Tersimpan', async ({ page }) => {
    await openNewDraft(page)

    await page.locator('#sterilizer_id').fill('STR-E2E-DIRTY')
    await page.getByTestId('back-button').click()

    await expect(page.getByRole('alertdialog')).toBeVisible()
    await expect(page.getByText('Ada perubahan yang belum disimpan. Yakin ingin kembali?')).toBeVisible()
    await expect(page).toHaveURL(/\/stations\/sterilizer\/form\//)
  })

  test('Input Data Sterilizer — Back tanpa perubahan langsung ke Monitor', async ({ page }) => {
    await openNewDraft(page)

    await page.getByTestId('back-button').click()
    await page.waitForURL('**/stations/sterilizer/monitor')
  })

  test('Input Data Sterilizer — Pause Progress', async ({ page }) => {
    const recordId = await openNewDraft(page)

    // Partial: a row without the required header field.
    await page.getByTestId('add-row-button').click()
    await page.getByTestId('detail-close-door-time-0').fill('07:00')
    await page.getByTestId('pause-button').click()

    await page.waitForURL('**/stations/sterilizer/monitor')
    await expect(page.getByTestId(`draft-item-${recordId}`)).toContainText('Pause')
  })

  test('Input Data Sterilizer — Clear Draft', async ({ page }) => {
    const recordId = await openNewDraft(page)
    await page.locator('#sterilizer_id').fill('STR-E2E-CLEAR')
    await page.getByTestId('pause-button').click()
    await page.waitForURL('**/stations/sterilizer/monitor')
    await expect(page.getByTestId(`draft-item-${recordId}`)).toBeVisible()

    // Cancel: nothing changes.
    await page.getByTestId(`draft-item-${recordId}`).click()
    await expect(page.locator('#sterilizer_id')).toHaveValue('STR-E2E-CLEAR')
    await page.getByTestId('clear-button').click()
    await page.getByRole('button', { name: 'Batal' }).click()
    await expect(page.getByRole('alertdialog')).toHaveCount(0)
    await expect(page).toHaveURL(new RegExp(`/stations/sterilizer/form/${recordId}$`))
    await expect(page.locator('#sterilizer_id')).toHaveValue('STR-E2E-CLEAR')

    // Confirm: permanently deleted.
    await page.getByTestId('clear-button').click()
    await page.getByRole('button', { name: 'Ya, Hapus' }).click()
    await page.waitForURL('**/stations/sterilizer/monitor')
    await expect(page.getByTestId('draft-list-empty')).toBeVisible()
    await expect(page.getByTestId(`draft-item-${recordId}`)).toHaveCount(0)
  })

  test('Input Data Sterilizer — Tap Breadcrumb dan Menu Hamburger', async ({ page }) => {
    await openNewDraft(page)

    await page.getByTestId('hamburger-button').click()
    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toBeVisible()
    await page.getByTestId('hamburger-button').click()

    await page.getByRole('navigation', { name: 'Breadcrumb' }).getByRole('button', { name: 'Sterilizer', exact: true }).click()
    await page.waitForURL('**/stations/sterilizer/monitor')
  })
})
