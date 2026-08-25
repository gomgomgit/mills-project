import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-047--data-preview-depricarping / usecase-047--data-preview-depricarping
//
// REVISED 2026-08-24 (entity-catalog v12): depricarping_detail rows are no
// longer pre-created 24-at-a-time — this seeds only 3 rows (exactly as if
// the user had added 3 via "Tambah baris"), matching what a real saved
// record now typically looks like — mirrors data-preview-pressing.spec.ts
// exactly.
async function seedDepricarpingRecord(
  page: Page,
  userId: string,
  overrides: { id: string; status?: 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'; presserId?: string | null; date?: string },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, status, presserId, date }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO depricarping_record (id, status, presser_id, date, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)`,
        [id, status ?? 'saved', presserId ?? null, date ?? now, userId, now, now],
      )

      const hours = [7, 9, 14]
      for (const [i, hour] of hours.entries()) {
        const timeSlot = `${String(hour).padStart(2, '0')}:00`
        await db.run(
          `INSERT OR REPLACE INTO depricarping_detail (id, depricarping_record_id, time_slot, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?)`,
          [`${id}-detail-${i}`, id, timeSlot, now, now],
        )
      }
    },
    { userId, id: overrides.id, status: overrides.status, presserId: overrides.presserId, date: overrides.date },
  )
}

function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

test.describe('Data Preview Depricarping (screen-047)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('Lihat Data Depricarping Tersimpan — success', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    await seedDepricarpingRecord(page, userId, {
      id: 'e2e-depricarping-preview-1',
      status: 'saved',
      presserId: 'DP-PREVIEW-1',
      date: `${today}T07:00:00`,
    })

    await page.goto('/stations/depricarping/preview')
    await expect(page.getByTestId('record-item-e2e-depricarping-preview-1')).toBeVisible()

    await page.getByTestId('record-item-e2e-depricarping-preview-1').click()
    await page.waitForURL('**/stations/depricarping/preview/e2e-depricarping-preview-1')
    await expect(page.getByTestId('depricarping-detail-rows-list')).toBeVisible()
    await expect(page.getByTestId(/^depricarping-detail-row-/)).toHaveCount(3)
    await expect(page.getByTestId('operational-target-table')).toBeVisible()
  })

  test('Lihat Data Depricarping Tersimpan — Record Tidak Ditemukan', async ({ page }) => {
    await page.goto('/stations/depricarping/preview/non-existent-id')

    await expect(page.getByTestId('record-not-found')).toBeVisible()
  })

  test('Lihat Data Depricarping Tersimpan — List Kosong', async ({ page }) => {
    await page.goto('/stations/depricarping/preview')

    await expect(page.getByTestId('record-list-empty')).toBeVisible()
  })

  test('Lihat Data Depricarping Tersimpan — Back dari Mode Detail', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    await seedDepricarpingRecord(page, userId, {
      id: 'e2e-depricarping-preview-back',
      status: 'saved',
      presserId: 'DP-BACK',
      date: `${today}T07:00:00`,
    })

    await page.goto(`/stations/depricarping/preview/e2e-depricarping-preview-back`)
    await page.getByTestId('back-button').click()

    await expect(page).toHaveURL('**/stations/depricarping/preview')
  })
})
