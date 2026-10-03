import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-090--data-preview-process-quality-control / usecase-117--data-preview-process-quality-control
//
// Mirrors data-preview-clarification.spec.ts's structure — records and their
// process_quality_control_detail rows are seeded directly via the dev-only
// `window.__mslTestDb` bridge (no in-app flow produces several saved records
// at once). This station has NO operational-target reference table.
//
// Not covered here: the RecordVerificationActions server write (PATCH
// /api/records/process-quality-control/{server_id}/verification) — it is not one of
// this screen's browser_test scenarios and needs a record that really exists
// on the server.

type Status = 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'

interface DetailSeed {
  id: string
  timeSlot: string
  fruitPressOilLossInSludge?: number | null
  findings?: string | null
}

async function seedProcessQualityControlRecord(
  page: Page,
  userId: string,
  overrides: {
    id: string
    status?: Status
    processQcId?: string | null
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
        `INSERT OR REPLACE INTO process_quality_control_record
           (id, status, process_qc_id, date, note, checked_by, checked_by_name, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          o.id,
          o.status ?? 'saved',
          o.processQcId ?? null,
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
          `INSERT OR REPLACE INTO process_quality_control_detail
             (id, process_quality_control_record_id, time_slot, fruit_press_oil_loss_in_sludge_percent, findings, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?, ?, ?)`,
          [d.id, o.id, d.timeSlot, d.fruitPressOilLossInSludge ?? null, d.findings ?? null, now, now],
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

test.describe('Data Preview Process Quality Control (screen-090)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('Lihat Data Process Quality Control Tersimpan — success', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    await seedProcessQualityControlRecord(page, userId, {
      id: 'e2e-pqc-preview-1',
      status: 'saved',
      processQcId: 'PQC-PREVIEW-1',
      date: `${today}T07:00:00`,
      note: 'Catatan e2e',
      checkedBy: 'e2e-supervisor-id',
      checkedByName: 'Supervisor Satu',
      details: [
        { id: 'e2e-pqc-d-1', timeSlot: '07:00', fruitPressOilLossInSludge: 91.5 },
        { id: 'e2e-pqc-d-2', timeSlot: '09:00', findings: 'Perlu ditinjau' },
        { id: 'e2e-pqc-d-3', timeSlot: '14:00', fruitPressOilLossInSludge: 93 },
      ],
    })

    // From Monitor Process Quality Control -> Load Data.
    await page.goto('/stations/process-quality-control/monitor')
    await page.getByTestId('load-data-button').click()
    await page.waitForURL(/\/stations\/process-quality-control\/preview$/)

    const item = page.getByTestId('record-item-e2e-pqc-preview-1')
    await expect(item).toContainText('PQC-PREVIEW-1')
    await expect(item).toContainText('Tersimpan')
    await item.click()
    await page.waitForURL('**/stations/process-quality-control/preview/e2e-pqc-preview-1')

    // Read-only header fields matching the stored record.
    await expect(page.getByLabel('Process QC ID')).toHaveValue('PQC-PREVIEW-1')
    await expect(page.getByLabel('Process QC ID')).toBeDisabled()
    await expect(page.getByLabel('Catatan')).toHaveValue('Catatan e2e')
    await expect(page.getByTestId('verify-status-checked-by')).toContainText('Supervisor Satu')
    await expect(page.getByTestId('verify-status-acknowledged-by')).toContainText('Belum dikonfirmasi Mill Management')

    // Process Quality Control Detail grid.
    await expect(page.getByTestId('process-quality-control-detail-rows-list')).toBeVisible()
    await expect(page.getByTestId(/^process-quality-control-detail-row-/)).toHaveCount(3)
    await expect(page.getByTestId('process-quality-control-detail-row-e2e-pqc-d-1')).toContainText('07:00')
    await expect(page.getByTestId('process-quality-control-detail-row-e2e-pqc-d-1')).toContainText('Fruit Press Oil Loss in Sludge (%): 91.5')
    await expect(page.getByTestId('process-quality-control-detail-row-e2e-pqc-d-2')).toContainText('Findings: Perlu ditinjau')
  })

  test('Lihat Data Process Quality Control Tersimpan — Filter Tanggal dan Pencarian', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    const yesterday = toLocalDateString(new Date(Date.now() - 24 * 60 * 60 * 1000))
    await seedProcessQualityControlRecord(page, userId, { id: 'e2e-pqc-today-a', processQcId: 'PQC-ALPHA', date: `${today}T07:00:00` })
    await seedProcessQualityControlRecord(page, userId, { id: 'e2e-pqc-today-b', processQcId: 'PQC-BETA', date: `${today}T08:00:00` })
    await seedProcessQualityControlRecord(page, userId, { id: 'e2e-pqc-yesterday', processQcId: 'PQC-OLD', date: `${yesterday}T07:00:00` })

    await page.goto('/stations/process-quality-control/preview')

    // Date filter defaults to today.
    await expect(page.getByTestId('date-filter-input')).toHaveValue(today)
    await expect(page.getByTestId('record-item-e2e-pqc-today-a')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-pqc-today-b')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-pqc-yesterday')).toHaveCount(0)

    // Case-insensitive search on Process QC ID.
    await page.getByTestId('search-filter-input').fill('alpha')
    await expect(page.getByTestId('record-item-e2e-pqc-today-a')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-pqc-today-b')).toHaveCount(0)

    // No match -> message + Reset Filter, which brings every record back.
    await page.getByTestId('search-filter-input').fill('tidak-ada')
    await expect(page.getByTestId('record-list-empty')).toContainText('Tidak ada data yang cocok dengan filter.')
    await page.getByTestId('reset-filter-button').click()
    await expect(page.getByTestId('record-item-e2e-pqc-yesterday')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-pqc-today-a')).toBeVisible()
  })

  test('Lihat Data Process Quality Control Tersimpan — Tap Item Draft/Pause membuka Form', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    await seedProcessQualityControlRecord(page, userId, {
      id: 'e2e-pqc-draft',
      status: 'draft_paused',
      processQcId: 'PQC-DRAFT',
      date: `${today}T07:00:00`,
    })

    await page.goto('/stations/process-quality-control/preview')
    await expect(page.getByTestId('record-item-e2e-pqc-draft')).toContainText('Pause')
    await page.getByTestId('record-item-e2e-pqc-draft').click()

    await page.waitForURL('**/stations/process-quality-control/form/e2e-pqc-draft')
    await expect(page.locator('#field-process-qc-id')).toHaveValue('PQC-DRAFT')
  })

  test('Lihat Data Process Quality Control Tersimpan — Record Tidak Ditemukan', async ({ page }) => {
    await page.goto('/stations/process-quality-control/preview/00000000-0000-0000-0000-000000000000')

    await expect(page.getByTestId('record-not-found')).toBeVisible()

    // Back works: returns to list mode.
    await page.getByTestId('back-button').click()
    await expect(page).toHaveURL(/\/stations\/process-quality-control\/preview$/)
    await expect(page.getByTestId('date-filter-input')).toBeVisible()
  })

  test('Lihat Data Process Quality Control Tersimpan — List Kosong', async ({ page }) => {
    await page.goto('/stations/process-quality-control/preview')

    await expect(page.getByTestId('record-list-empty')).toBeVisible()
  })

  test('Lihat Data Process Quality Control Tersimpan — Back dari Mode Detail lalu ke Monitor', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    await seedProcessQualityControlRecord(page, userId, {
      id: 'e2e-pqc-back',
      processQcId: 'PQC-BACK',
      date: `${today}T07:00:00`,
    })

    await page.goto('/stations/process-quality-control/preview/e2e-pqc-back')
    // Zero detail rows -> empty state, not an error.
    await expect(page.getByTestId('process-quality-control-detail-rows-empty')).toBeVisible()

    await page.getByTestId('back-button').click()
    await expect(page).toHaveURL(/\/stations\/process-quality-control\/preview$/)
    await expect(page.getByTestId('record-item-e2e-pqc-back')).toBeVisible()

    await page.getByTestId('back-button').click()
    await expect(page).toHaveURL(/\/stations\/process-quality-control\/monitor$/)
  })
})
