import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-048--data-preview-kernel-plant / usecase-048--data-preview-kernel-plant
//
// REVISED 2026-08-24 (entity-catalog v12): kernel_plant_detail rows are no
// longer pre-created 24-at-a-time — this seeds only 3 rows (exactly as if
// the user had added 3 via "Tambah baris"), matching what a real saved
// record now typically looks like — mirrors data-preview-depricarping.spec.ts
// exactly.
async function seedKernelPlantRecord(
  page: Page,
  userId: string,
  overrides: { id: string; status?: 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'; kernelPlantId?: string | null; date?: string },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, status, kernelPlantId, date }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO kernel_plant_record (id, status, kernel_plant_id, date, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'saved', kernelPlantId ?? null, date ?? now, userId, now, now],
      )

      const hours = [7, 9, 14]
      for (const [i, hour] of hours.entries()) {
        const timeSlot = `${String(hour).padStart(2, '0')}:00`
        await db.run(
          `INSERT OR REPLACE INTO kernel_plant_detail (id, kernel_plant_record_id, time_slot, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?)`,
          [`${id}-detail-${i}`, id, timeSlot, now, now],
        )
      }
    },
    { userId, id: overrides.id, status: overrides.status, kernelPlantId: overrides.kernelPlantId, date: overrides.date },
  )
}

function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

test.describe('Data Preview Kernel Plant (screen-048)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('Lihat Data Kernel Plant Tersimpan — success', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    await seedKernelPlantRecord(page, userId, {
      id: 'e2e-kernel-plant-preview-1',
      status: 'saved',
      kernelPlantId: 'KP-PREVIEW-1',
      date: `${today}T07:00:00`,
    })

    await page.goto('/stations/kernel-plant/preview')
    await expect(page.getByTestId('record-item-e2e-kernel-plant-preview-1')).toBeVisible()

    await page.getByTestId('record-item-e2e-kernel-plant-preview-1').click()
    await page.waitForURL('**/stations/kernel-plant/preview/e2e-kernel-plant-preview-1')
    await expect(page.getByTestId('kernel-plant-detail-rows-list')).toBeVisible()
    await expect(page.getByTestId(/^kernel-plant-detail-row-/)).toHaveCount(3)
    await expect(page.getByTestId('operational-target-table')).toBeVisible()
  })

  test('Lihat Data Kernel Plant Tersimpan — Record Tidak Ditemukan', async ({ page }) => {
    await page.goto('/stations/kernel-plant/preview/non-existent-id')

    await expect(page.getByTestId('record-not-found')).toBeVisible()
  })

  test('Lihat Data Kernel Plant Tersimpan — List Kosong', async ({ page }) => {
    await page.goto('/stations/kernel-plant/preview')

    await expect(page.getByTestId('record-list-empty')).toBeVisible()
  })

  test('Lihat Data Kernel Plant Tersimpan — Back dari Mode Detail', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    await seedKernelPlantRecord(page, userId, {
      id: 'e2e-kernel-plant-preview-back',
      status: 'saved',
      kernelPlantId: 'KP-BACK',
      date: `${today}T07:00:00`,
    })

    await page.goto(`/stations/kernel-plant/preview/e2e-kernel-plant-preview-back`)
    await page.getByTestId('back-button').click()

    await expect(page).toHaveURL('**/stations/kernel-plant/preview')
  })
})
