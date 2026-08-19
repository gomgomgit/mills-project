import { test, expect } from '@playwright/test'
import { login } from './helpers'

// screen-015--data-preview-cages-track / usecase-015--data-preview-cages-track
test.describe('Data Preview Cages Track (screen-015)', () => {
  test('Lihat Data Cages Track Tersimpan — success', async ({ page }) => {
    await login(page)
    await page.goto('/stations/cages-track/monitor')
    await page.getByRole('button', { name: 'Mulai Input Baru' }).click()
    await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)
    const recordId = page.url().split('/').pop()

    await page.getByLabel('No. Cages Track').fill('CT-PREVIEW-01')
    await page.getByLabel('Tanggal').fill('2026-08-18')
    await page.getByTestId('cages-tipped-time-add').click()
    await page.getByTestId('cages-tipped-time-row').last().locator('input[type="text"]').fill('C-01')
    await page.getByTestId('cages-tipped-time-row').last().locator('input[type="time"]').fill('08:00')
    await page.getByRole('button', { name: 'Simpan' }).click()
    await page.waitForURL('**/stations/cages-track/monitor')

    await page.goto(`/stations/cages-track/preview/${recordId}`)
    await expect(page.getByLabel('No. Cages Track')).toHaveValue('CT-PREVIEW-01')
    await expect(page.getByLabel('No. Cages Track')).toBeDisabled()
    await expect(page.getByTestId('tipped-time-rows-list')).toBeVisible()

    await page.getByRole('button', { name: 'Back' }).click()
    await page.waitForURL('**/stations/cages-track/monitor')
  })

  test('Lihat Data Cages Track Tersimpan — Record Tidak Ditemukan', async ({ page }) => {
    await login(page)
    await page.goto('/stations/cages-track/preview/does-not-exist')

    await expect(page.getByTestId('record-not-found')).toBeVisible()
    await page.getByRole('button', { name: 'Back' }).click()
    await page.waitForURL('**/stations/cages-track/monitor')
  })
})
