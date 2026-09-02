import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-085--data-preview-effluent-plant / usecase-087--data-preview-effluent-plant
//
// Mirrors data-preview-threshing.spec.ts's structure — seeds only 3 rows
// (exactly as if the user had added 3 via "Tambah baris"), matching what a
// real saved record now typically looks like. UNLIKE
// data-preview-threshing.spec.ts: this station has NO operational-target
// reference table, so no such assertion appears here.
async function seedEffluentPlantRecord(
  page: Page,
  userId: string,
  overrides: { id: string; status?: 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'; effluentPlantId?: string | null; date?: string },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, status, effluentPlantId, date }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO effluent_plant_record (id, status, effluent_plant_id, date, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'saved', effluentPlantId ?? null, date ?? now, userId, now, now],
      )

      const hours = [7, 9, 14]
      for (const [i, hour] of hours.entries()) {
        const timeSlot = `${String(hour).padStart(2, '0')}:00`
        await db.run(
          `INSERT OR REPLACE INTO effluent_plant_detail (id, effluent_plant_record_id, time_slot, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?)`,
          [`${id}-detail-${i}`, id, timeSlot, now, now],
        )
      }
    },
    { userId, id: overrides.id, status: overrides.status, effluentPlantId: overrides.effluentPlantId, date: overrides.date },
  )
}

function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

test.describe('Data Preview Effluent Plant (screen-085)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('Lihat Data Effluent Plant Tersimpan — success', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    await seedEffluentPlantRecord(page, userId, {
      id: 'e2e-effluent-plant-preview-1',
      status: 'saved',
      effluentPlantId: 'EP-PREVIEW-1',
      date: `${today}T07:00:00`,
    })

    await page.goto('/stations/effluent-plant/preview')
    await expect(page.getByTestId('record-item-e2e-effluent-plant-preview-1')).toBeVisible()

    await page.getByTestId('record-item-e2e-effluent-plant-preview-1').click()
    await page.waitForURL('**/stations/effluent-plant/preview/e2e-effluent-plant-preview-1')
    await expect(page.getByTestId('effluent-plant-detail-rows-list')).toBeVisible()
    await expect(page.getByTestId(/^effluent-plant-detail-row-/)).toHaveCount(3)
  })

  test('Lihat Data Effluent Plant Tersimpan — Record Tidak Ditemukan', async ({ page }) => {
    await page.goto('/stations/effluent-plant/preview/non-existent-id')

    await expect(page.getByTestId('record-not-found')).toBeVisible()
  })

  test('Lihat Data Effluent Plant Tersimpan — List Kosong', async ({ page }) => {
    await page.goto('/stations/effluent-plant/preview')

    await expect(page.getByTestId('record-list-empty')).toBeVisible()
  })

  test('Lihat Data Effluent Plant Tersimpan — Back dari Mode Detail', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    await seedEffluentPlantRecord(page, userId, {
      id: 'e2e-effluent-plant-preview-back',
      status: 'saved',
      effluentPlantId: 'EP-BACK',
      date: `${today}T07:00:00`,
    })

    await page.goto(`/stations/effluent-plant/preview/e2e-effluent-plant-preview-back`)
    await page.getByTestId('back-button').click()

    await expect(page).toHaveURL('**/stations/effluent-plant/preview')
  })
})
