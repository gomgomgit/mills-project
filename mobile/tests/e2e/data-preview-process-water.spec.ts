import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-082--data-preview-process-water / usecase-069--data-preview-process-water
//
// Mirrors data-preview-threshing.spec.ts's structure — seeds only 3 rows
// (exactly as if the user had added 3 via "Tambah baris"), matching what a
// real saved record now typically looks like. UNLIKE
// data-preview-threshing.spec.ts: this station has NO operational-target
// reference table, so no such assertion appears here.
async function seedProcessWaterRecord(
  page: Page,
  userId: string,
  overrides: { id: string; status?: 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'; processWaterId?: string | null; date?: string },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, status, processWaterId, date }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO process_water_record (id, status, process_water_id, date, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'saved', processWaterId ?? null, date ?? now, userId, now, now],
      )

      const hours = [7, 9, 14]
      for (const [i, hour] of hours.entries()) {
        const timeSlot = `${String(hour).padStart(2, '0')}:00`
        await db.run(
          `INSERT OR REPLACE INTO process_water_detail (id, process_water_record_id, time_slot, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?)`,
          [`${id}-detail-${i}`, id, timeSlot, now, now],
        )
      }
    },
    { userId, id: overrides.id, status: overrides.status, processWaterId: overrides.processWaterId, date: overrides.date },
  )
}

function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

test.describe('Data Preview Process Water (screen-082)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('Lihat Data Process Water Tersimpan — success', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    await seedProcessWaterRecord(page, userId, {
      id: 'e2e-process-water-preview-1',
      status: 'saved',
      processWaterId: 'PW-PREVIEW-1',
      date: `${today}T07:00:00`,
    })

    await page.goto('/stations/process-water/preview')
    await expect(page.getByTestId('record-item-e2e-process-water-preview-1')).toBeVisible()

    await page.getByTestId('record-item-e2e-process-water-preview-1').click()
    await page.waitForURL('**/stations/process-water/preview/e2e-process-water-preview-1')
    await expect(page.getByTestId('process-water-detail-rows-list')).toBeVisible()
    await expect(page.getByTestId(/^process-water-detail-row-/)).toHaveCount(3)
  })

  test('Lihat Data Process Water Tersimpan — Record Tidak Ditemukan', async ({ page }) => {
    await page.goto('/stations/process-water/preview/non-existent-id')

    await expect(page.getByTestId('record-not-found')).toBeVisible()
  })

  test('Lihat Data Process Water Tersimpan — List Kosong', async ({ page }) => {
    await page.goto('/stations/process-water/preview')

    await expect(page.getByTestId('record-list-empty')).toBeVisible()
  })

  test('Lihat Data Process Water Tersimpan — Back dari Mode Detail', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    await seedProcessWaterRecord(page, userId, {
      id: 'e2e-process-water-preview-back',
      status: 'saved',
      processWaterId: 'PW-BACK',
      date: `${today}T07:00:00`,
    })

    await page.goto(`/stations/process-water/preview/e2e-process-water-preview-back`)
    await page.getByTestId('back-button').click()

    await expect(page).toHaveURL('**/stations/process-water/preview')
  })
})
