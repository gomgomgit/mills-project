import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-089--data-preview-clarification / usecase-111--data-preview-clarification
//
// Mirrors data-preview-effluent-plant.spec.ts's structure — records and their
// clarification_detail rows are seeded directly via the dev-only
// `window.__mslTestDb` bridge (no in-app flow produces several saved records
// at once). This station has NO operational-target reference table.
//
// Not covered here: the RecordVerificationActions server write (PATCH
// /api/records/clarification/{server_id}/verification) — it is not one of
// this screen's browser_test scenarios and needs a record that really exists
// on the server.

type Status = 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'

interface DetailSeed {
  id: string
  timeSlot: string
  clarificationTankTemp?: number | null
  findings?: string | null
}

async function seedClarificationRecord(
  page: Page,
  userId: string,
  overrides: {
    id: string
    status?: Status
    clarificationId?: string | null
    date?: string
    note?: string | null
    checkedBy?: string | null
    checkedByName?: string | null
    details?: DetailSeed[]
  },
): Promise<void> {
  await page.evaluate(
    async ({ userId, o }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO clarification_record
           (id, status, clarification_id, date, note, checked_by, checked_by_name, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          o.id,
          o.status ?? 'saved',
          o.clarificationId ?? null,
          o.date ?? now,
          o.note ?? null,
          o.checkedBy ?? null,
          o.checkedByName ?? null,
          userId,
          now,
          now,
        ],
      )

      for (const d of o.details ?? []) {
        await db.run(
          `INSERT OR REPLACE INTO clarification_detail
             (id, clarification_record_id, time_slot, clarification_tank_temp_c, findings, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?, ?, ?)`,
          [d.id, o.id, d.timeSlot, d.clarificationTankTemp ?? null, d.findings ?? null, now, now],
        )
      }
    },
    { userId, o: overrides },
  )
}

function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

test.describe('Data Preview Clarification (screen-089)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('Lihat Data Clarification Tersimpan — success', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    await seedClarificationRecord(page, userId, {
      id: 'e2e-clarification-preview-1',
      status: 'saved',
      clarificationId: 'CLR-PREVIEW-1',
      date: `${today}T07:00:00`,
      note: 'Catatan e2e',
      checkedBy: 'e2e-supervisor-id',
      checkedByName: 'Supervisor Satu',
      details: [
        { id: 'e2e-clr-d-1', timeSlot: '07:00', clarificationTankTemp: 91.5 },
        { id: 'e2e-clr-d-2', timeSlot: '09:00', findings: 'Perlu ditinjau' },
        { id: 'e2e-clr-d-3', timeSlot: '14:00', clarificationTankTemp: 93 },
      ],
    })

    // From Monitor Clarification -> Load Data.
    await page.goto('/stations/clarification/monitor')
    await page.getByTestId('load-data-button').click()
    await page.waitForURL(/\/stations\/clarification\/preview$/)

    const item = page.getByTestId('record-item-e2e-clarification-preview-1')
    await expect(item).toContainText('CLR-PREVIEW-1')
    await expect(item).toContainText('Tersimpan')
    await item.click()
    await page.waitForURL('**/stations/clarification/preview/e2e-clarification-preview-1')

    // Read-only header fields matching the stored record.
    await expect(page.getByLabel('Clarification ID')).toHaveValue('CLR-PREVIEW-1')
    await expect(page.getByLabel('Clarification ID')).toBeDisabled()
    await expect(page.getByLabel('Catatan')).toHaveValue('Catatan e2e')
    await expect(page.getByTestId('verify-status-checked-by')).toContainText('Supervisor Satu')
    await expect(page.getByTestId('verify-status-acknowledged-by')).toContainText('Belum dikonfirmasi Mill Management')

    // Clarification Detail grid.
    await expect(page.getByTestId('clarification-detail-rows-list')).toBeVisible()
    await expect(page.getByTestId(/^clarification-detail-row-/)).toHaveCount(3)
    await expect(page.getByTestId('clarification-detail-row-e2e-clr-d-1')).toContainText('07:00')
    await expect(page.getByTestId('clarification-detail-row-e2e-clr-d-1')).toContainText('Clarification Tank Temp (°C): 91.5')
    await expect(page.getByTestId('clarification-detail-row-e2e-clr-d-2')).toContainText('Findings: Perlu ditinjau')
  })

  test('Lihat Data Clarification Tersimpan — Filter Tanggal dan Pencarian', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    const yesterday = toLocalDateString(new Date(Date.now() - 24 * 60 * 60 * 1000))
    await seedClarificationRecord(page, userId, { id: 'e2e-clr-today-a', clarificationId: 'CLR-ALPHA', date: `${today}T07:00:00` })
    await seedClarificationRecord(page, userId, { id: 'e2e-clr-today-b', clarificationId: 'CLR-BETA', date: `${today}T08:00:00` })
    await seedClarificationRecord(page, userId, { id: 'e2e-clr-yesterday', clarificationId: 'CLR-OLD', date: `${yesterday}T07:00:00` })

    await page.goto('/stations/clarification/preview')

    // Date filter defaults to today.
    await expect(page.getByTestId('date-filter-input')).toHaveValue(today)
    await expect(page.getByTestId('record-item-e2e-clr-today-a')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-clr-today-b')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-clr-yesterday')).toHaveCount(0)

    // Case-insensitive search on Clarification ID.
    await page.getByTestId('search-filter-input').fill('alpha')
    await expect(page.getByTestId('record-item-e2e-clr-today-a')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-clr-today-b')).toHaveCount(0)

    // No match -> message + Reset Filter, which brings every record back.
    await page.getByTestId('search-filter-input').fill('tidak-ada')
    await expect(page.getByTestId('record-list-empty')).toContainText('Tidak ada data yang cocok dengan filter.')
    await page.getByTestId('reset-filter-button').click()
    await expect(page.getByTestId('record-item-e2e-clr-yesterday')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-clr-today-a')).toBeVisible()
  })

  test('Lihat Data Clarification Tersimpan — Tap Item Draft/Pause membuka Form', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    await seedClarificationRecord(page, userId, {
      id: 'e2e-clr-draft',
      status: 'draft_paused',
      clarificationId: 'CLR-DRAFT',
      date: `${today}T07:00:00`,
    })

    await page.goto('/stations/clarification/preview')
    await expect(page.getByTestId('record-item-e2e-clr-draft')).toContainText('Pause')
    await page.getByTestId('record-item-e2e-clr-draft').click()

    await page.waitForURL('**/stations/clarification/form/e2e-clr-draft')
    await expect(page.locator('#field-clarification-id')).toHaveValue('CLR-DRAFT')
  })

  test('Lihat Data Clarification Tersimpan — Record Tidak Ditemukan', async ({ page }) => {
    await page.goto('/stations/clarification/preview/00000000-0000-0000-0000-000000000000')

    await expect(page.getByTestId('record-not-found')).toBeVisible()

    // Back works: returns to list mode.
    await page.getByTestId('back-button').click()
    await expect(page).toHaveURL(/\/stations\/clarification\/preview$/)
    await expect(page.getByTestId('date-filter-input')).toBeVisible()
  })

  test('Lihat Data Clarification Tersimpan — List Kosong', async ({ page }) => {
    await page.goto('/stations/clarification/preview')

    await expect(page.getByTestId('record-list-empty')).toBeVisible()
  })

  test('Lihat Data Clarification Tersimpan — Back dari Mode Detail lalu ke Monitor', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    await seedClarificationRecord(page, userId, {
      id: 'e2e-clr-back',
      clarificationId: 'CLR-BACK',
      date: `${today}T07:00:00`,
    })

    await page.goto('/stations/clarification/preview/e2e-clr-back')
    // Zero detail rows -> empty state, not an error.
    await expect(page.getByTestId('clarification-detail-rows-empty')).toBeVisible()

    await page.getByTestId('back-button').click()
    await expect(page).toHaveURL(/\/stations\/clarification\/preview$/)
    await expect(page.getByTestId('record-item-e2e-clr-back')).toBeVisible()

    await page.getByTestId('back-button').click()
    await expect(page).toHaveURL(/\/stations\/clarification\/monitor$/)
  })
})
