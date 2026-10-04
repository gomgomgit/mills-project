import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// screen-123--data-preview-sterilizer / usecase-123--data-preview-sterilizer
//
// Dual-mode screen (list at /stations/sterilizer/preview, read-only detail
// at /stations/sterilizer/preview/:id), mirroring
// data-preview-cages-track.spec.ts for a header + child-row station: the
// detail shows the header, a condensed read-only Log Siklus table (Sterilizer
// No, Close/Open Door Time, Duration, Number of Cages, Cages Status, Checked
// by SPV) and the verification status blocks.
//
// Records/cycles are seeded via the dev-only `window.__mslTestDb` bridge.
// sterilizer_record.date is a plain 'YYYY-MM-DD'.
//
// Not covered: the RecordVerificationActions server write (PATCH
// /api/records/sterilizer/{server_id}/verification) — not one of this
// screen's browser_test scenarios, and it needs a record that exists on the
// server.

type Status = 'draft_ongoing' | 'draft_paused' | 'saved' | 'synced'

interface CycleSeed {
  id: string
  sterilizerNo?: string | null
  closeDoorTime?: string | null
  openDoorTime?: string | null
  durationMinutes?: number | null
  numberOfCages?: number | null
  cagesStatus?: string | null
  checkedBySpv?: boolean
  createdAt?: string
}

async function seedSterilizerRecord(
  page: Page,
  userId: string,
  overrides: {
    id: string
    status?: Status
    sterilizerId?: string | null
    date?: string | null
    note?: string | null
    checkedBy?: string | null
    checkedByName?: string | null
    cycles?: CycleSeed[]
  },
): Promise<void> {
  await page.evaluate(
    async ({ userId, o }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO sterilizer_record
           (id, status, sterilizer_id, date, note, checked_by, checked_by_name, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          o.id,
          o.status ?? 'saved',
          o.sterilizerId ?? null,
          o.date ?? null,
          o.note ?? null,
          o.checkedBy ?? null,
          o.checkedByName ?? null,
          userId,
          now,
          now,
        ],
      )

      for (const c of o.cycles ?? []) {
        const createdAt = c.createdAt ?? now
        await db.run(
          `INSERT OR REPLACE INTO sterilizer_detail
             (id, sterilizer_record_id, sterilizer_no, close_door_time, open_door_time, duration_minutes,
              number_of_cages, cages_status, checked_by_spv, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
          [
            c.id,
            o.id,
            c.sterilizerNo ?? null,
            c.closeDoorTime ?? null,
            c.openDoorTime ?? null,
            c.durationMinutes ?? null,
            c.numberOfCages ?? null,
            c.cagesStatus ?? null,
            c.checkedBySpv ? 1 : 0,
            createdAt,
            createdAt,
          ],
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

test.describe('Data Preview Sterilizer (screen-123)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('Lihat Data Sterilizer Tersimpan — Record Tidak Ditemukan', async ({ page }) => {
    await page.goto('/stations/sterilizer/preview/00000000-0000-0000-0000-000000000000')

    await expect(page.getByTestId('record-not-found')).toContainText('Record tidak ditemukan.')
    await expect(page.getByTestId('back-button')).toBeVisible()

    // Back returns to the LIST (not Monitor).
    await page.getByTestId('back-button').click()
    await expect(page).toHaveURL(/\/stations\/sterilizer\/preview$/)
    await expect(page.getByTestId('date-filter')).toBeVisible()
  })

  test('Lihat Data Sterilizer Tersimpan — success', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    await seedSterilizerRecord(page, userId, {
      id: 'e2e-ster-preview-1',
      status: 'saved',
      sterilizerId: 'STR-PREVIEW-1',
      date: today,
      note: 'Catatan e2e',
      checkedBy: 'e2e-supervisor-id',
      checkedByName: 'Supervisor Satu',
      cycles: [
        {
          id: 'e2e-ster-p-c1',
          sterilizerNo: '1',
          closeDoorTime: '07:00',
          openDoorTime: '08:10',
          durationMinutes: 70,
          numberOfCages: 10,
          cagesStatus: 'Lengkap',
          checkedBySpv: true,
          createdAt: '2026-01-01T07:00:00.000Z',
        },
        {
          id: 'e2e-ster-p-c2',
          sterilizerNo: '2',
          closeDoorTime: '23:30',
          openDoorTime: '00:15',
          durationMinutes: 45,
          createdAt: '2026-01-01T08:00:00.000Z',
        },
      ],
    })
    await seedSterilizerRecord(page, userId, {
      id: 'e2e-ster-preview-synced',
      status: 'synced',
      sterilizerId: 'STR-SYNCED',
      date: today,
    })

    // From Monitor -> Load Data.
    await page.goto('/stations/sterilizer/monitor')
    await page.getByTestId('load-data-button').click()
    await page.waitForURL(/\/stations\/sterilizer\/preview$/)

    await expect(page.getByTestId('record-item-e2e-ster-preview-synced')).toContainText('Tersinkron')
    const item = page.getByTestId('record-item-e2e-ster-preview-1')
    await expect(item).toContainText('STR-PREVIEW-1')
    await expect(item).toContainText('Tersimpan')
    await item.click()
    await page.waitForURL('**/stations/sterilizer/preview/e2e-ster-preview-1')

    await expect(page.getByTestId('detail-sterilizer-id')).toContainText('STR-PREVIEW-1')
    await expect(page.getByText(`Tanggal: ${today}`)).toBeVisible()
    await expect(page.getByText('Catatan: Catatan e2e')).toBeVisible()
    await expect(page.getByTestId('verify-status-checked-by')).toContainText('Oleh Supervisor Satu')
    await expect(page.getByTestId('verify-status-acknowledged-by')).toContainText('Belum dikonfirmasi Mill Management')

    // Log Siklus, read-only (no inputs at all).
    const log = page.getByTestId('sterilizer-detail-log')
    await expect(log.locator('tbody tr')).toHaveCount(2)
    await expect(log.locator('input')).toHaveCount(0)
    const first = log.locator('tbody tr').nth(0)
    for (const cell of ['1', '07:00', '08:10', '70', '10', 'Lengkap', 'Ya']) {
      await expect(first).toContainText(cell)
    }
    const second = log.locator('tbody tr').nth(1)
    await expect(second).toContainText('45')
    await expect(second).toContainText('Tidak')

    // Back -> list, Back again -> Monitor.
    await page.getByTestId('back-button').click()
    await expect(page).toHaveURL(/\/stations\/sterilizer\/preview$/)
    await expect(page.getByTestId('record-item-e2e-ster-preview-1')).toBeVisible()
    await page.getByTestId('back-button').click()
    await expect(page).toHaveURL(/\/stations\/sterilizer\/monitor$/)
  })

  test('Lihat Data Sterilizer Tersimpan — Tap Item Draft/Pause', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedSterilizerRecord(page, userId, {
      id: 'e2e-ster-preview-draft',
      status: 'draft_ongoing',
      sterilizerId: 'STR-DRAFT',
      date: toLocalDateString(new Date()),
    })

    await page.goto('/stations/sterilizer/preview')
    const item = page.getByTestId('record-item-e2e-ster-preview-draft')
    await expect(item).toContainText('Pause')
    await item.click()

    await page.waitForURL('**/stations/sterilizer/form/e2e-ster-preview-draft')
    await expect(page.locator('#sterilizer_id')).toHaveValue('STR-DRAFT')
  })

  test('Lihat Data Sterilizer Tersimpan — Filter Tanggal Default Hari Ini', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const today = toLocalDateString(new Date())
    const yesterday = toLocalDateString(new Date(Date.now() - 24 * 60 * 60 * 1000))
    await seedSterilizerRecord(page, userId, { id: 'e2e-ster-today', sterilizerId: 'STR-TODAY', date: today })
    await seedSterilizerRecord(page, userId, { id: 'e2e-ster-yesterday', sterilizerId: 'STR-YESTERDAY', date: yesterday })

    await page.goto('/stations/sterilizer/preview')

    await expect(page.getByTestId('date-filter')).toHaveValue(today)
    await expect(page.getByTestId('record-item-e2e-ster-today')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-ster-yesterday')).toHaveCount(0)

    // Changing the filter re-filters; clearing it shows every record.
    await page.getByTestId('date-filter').fill(yesterday)
    await expect(page.getByTestId('record-item-e2e-ster-yesterday')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-ster-today')).toHaveCount(0)
    await page.getByTestId('date-filter').fill('')
    await expect(page.getByTestId('record-item-e2e-ster-today')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-ster-yesterday')).toBeVisible()

    // Case-insensitive search on Sterilizer ID.
    await page.getByTestId('search-filter').fill('str-yest')
    await expect(page.getByTestId('record-item-e2e-ster-yesterday')).toBeVisible()
    await expect(page.getByTestId('record-item-e2e-ster-today')).toHaveCount(0)
  })

  test('Lihat Data Sterilizer Tersimpan — Filter Tidak Menghasilkan Apapun', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedSterilizerRecord(page, userId, {
      id: 'e2e-ster-nomatch',
      sterilizerId: 'STR-ADA',
      date: toLocalDateString(new Date()),
    })

    await page.goto('/stations/sterilizer/preview')
    await expect(page.getByTestId('record-item-e2e-ster-nomatch')).toBeVisible()

    await page.getByTestId('search-filter').fill('tidak-ada')
    await expect(page.getByTestId('list-not-found')).toContainText('Tidak ditemukan data yang sesuai filter.')
    await expect(page.getByTestId('reset-filter-button')).toBeVisible()

    await page.getByTestId('reset-filter-button').click()
    await expect(page.getByTestId('record-item-e2e-ster-nomatch')).toBeVisible()
    await expect(page.getByTestId('date-filter')).toHaveValue('')
  })

  test('Lihat Data Sterilizer Tersimpan — List Kosong', async ({ page }) => {
    await page.goto('/stations/sterilizer/preview')

    await expect(page.getByTestId('list-empty')).toContainText('Belum ada data sterilizer tersimpan.')
    await expect(page.getByTestId('record-list')).toHaveCount(0)
  })
})
