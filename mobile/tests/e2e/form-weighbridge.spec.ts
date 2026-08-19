import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

/**
 * form-weighbridge.spec.ts — screen-010--form-weighbridge /
 * usecase-010--form-weighbridge.
 *
 * SCHEMA REVISION (2026-08-19, entity-catalog v5 / tech spec v6) — rewritten
 * to match FormWeighbridgeView.vue's schema-revision update:
 * `arrival_datetime`/`dispatch_datetime` (and the old live-ticking
 * `setInterval`-driven Dispatch clock, frozen only at Simpan-click time) no
 * longer exist. Both are replaced by a single `record_datetime` column, and
 * a new two-tab `weighbridge_type` selector ("Receive"/"Dispatch",
 * `data-testid="weighbridge-type-receive"`/`"weighbridge-type-dispatch"`,
 * `role="tab"`) decides what it means (Arrival vs Dispatch labels) —
 * `record_datetime` is auto-set-once IDENTICALLY for both types (set once
 * on load/type-switch when empty; a stored value is otherwise preserved;
 * there is NO live ticking for either type). Switching the type tab
 * discards `record_datetime`/`destination` and immediately re-applies the
 * auto-set-once rule. A new `destination` field ("Tujuan Muatan")
 * renders/is required only for `weighbridge_type === 'dispatch'`.
 * Quantity's label is now "Kuantitas (tandan)".
 *
 * This project's mobile screens ARE browser-testable via the Vite dev
 * server (a Capacitor app is a regular SPA before native build) — browser
 * tests are not deferred as "mobile-only".
 *
 * Seeds `weighbridge_record` rows directly via the dev-only
 * `window.__mslTestDb` bridge (same pattern as monitor-weighbridge.spec.ts's
 * `seedWeighbridgeDraft()` / helpers.ts's `seedPausedDrafts()`) for the
 * "Lanjutkan Draft Paused" scenario, which needs a pre-existing
 * `draft_paused` row with a stored `record_datetime`/`destination` to prove
 * both are preserved (not reset) on resume.
 */
async function fillBaseRequiredFields(page: Page): Promise<void> {
  await page.getByLabel('WB Card Number/ID').fill('WB-001')
  await page.getByLabel('No. Kendaraan').fill('B 1234 CD')
  await page.getByLabel('Nama Supir').fill('Budi Santoso')
  await page.getByLabel('Estate/Supplier Asal').fill('Estate A')
  await page.getByLabel('Berat Masuk (Gross)').fill('15000')
}

async function seedWeighbridgeDraft(
  page: Page,
  userId: string,
  overrides: {
    id: string
    status?: 'draft_ongoing' | 'draft_paused'
    wbCardNumber?: string | null
    weighbridgeType?: 'receive' | 'dispatch' | null
    recordDatetime?: string | null
    destination?: string | null
  },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, status, wbCardNumber, weighbridgeType, recordDatetime, destination }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO weighbridge_record
           (id, status, wb_card_number, weighbridge_type, record_datetime, destination, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          id,
          status ?? 'draft_ongoing',
          wbCardNumber ?? null,
          weighbridgeType ?? null,
          recordDatetime ?? null,
          destination ?? null,
          userId,
          now,
          now,
        ],
      )
    },
    {
      userId,
      id: overrides.id,
      status: overrides.status,
      wbCardNumber: overrides.wbCardNumber,
      weighbridgeType: overrides.weighbridgeType,
      recordDatetime: overrides.recordDatetime,
      destination: overrides.destination,
    },
  )
}

test.describe('Form Weighbridge (screen-010)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  // Scenario 1: "success" (Receive — default type, no Destination)
  test('Form Weighbridge — success (Receive)', async ({ page }) => {
    await page.goto('/stations/weighbridge/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/weighbridge\/form\/(.+)/)
    const recordId = page.url().split('/').pop()

    // Receive is the default tab for a brand-new draft.
    await expect(page.getByTestId('weighbridge-type-receive')).toHaveAttribute('aria-selected', 'true')

    await fillBaseRequiredFields(page)
    await page.getByLabel('Berat Keluar (Tare)').fill('3000')
    await page.getByLabel('Kuantitas (tandan)').fill('4')

    await page.getByTestId('save-button').click()
    await page.waitForURL('**/stations/weighbridge/monitor')

    // Verify the saved record directly (bypassing Monitor's 'Load Data',
    // which is a documented pre-existing known_issue — see
    // DataPreviewWeighbridgeView.vue's header comment — navigating with no
    // id param at all).
    await page.goto(`/stations/weighbridge/preview/${recordId}`)
    await expect(page.getByLabel('Tipe Weighbridge')).toHaveValue('Receive')
    await expect(page.getByLabel('No. WB Card')).toHaveValue('WB-001')
    await expect(page.getByLabel('Berat Bersih (Net Weight)')).toHaveValue('12000')
    await expect(page.getByLabel('Kuantitas (tandan)')).toHaveValue('4')
    // Destination is not rendered on the detail view for a receive record.
    await expect(page.getByTestId('detail-destination')).toHaveCount(0)
  })

  // Scenario 2: "success" (Dispatch — Destination required and saved)
  test('Form Weighbridge — success (Dispatch dengan Destination)', async ({ page }) => {
    await page.goto('/stations/weighbridge/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/weighbridge\/form\/(.+)/)
    const recordId = page.url().split('/').pop()

    await page.getByTestId('weighbridge-type-dispatch').click()
    await expect(page.getByTestId('weighbridge-type-dispatch')).toHaveAttribute('aria-selected', 'true')

    await fillBaseRequiredFields(page)
    await page.getByLabel('Tujuan Muatan').fill('PKS Tujuan B')
    await page.getByLabel('Berat Keluar (Tare)').fill('3000')

    await page.getByTestId('save-button').click()
    await page.waitForURL('**/stations/weighbridge/monitor')

    await page.goto(`/stations/weighbridge/preview/${recordId}`)
    await expect(page.getByLabel('Tipe Weighbridge')).toHaveValue('Dispatch')
    await expect(page.getByLabel('Tujuan Muatan')).toHaveValue('PKS Tujuan B')
    await expect(page.getByLabel('Berat Bersih (Net Weight)')).toHaveValue('12000')
  })

  // Scenario 3: single record_datetime display, no live update
  test('Form Weighbridge — Tanggal/Waktu Tidak Live Update', async ({ page }) => {
    await page.goto('/stations/weighbridge/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/weighbridge\/form\/(.+)/)

    // Both the date and time portions of the single record_datetime field
    // render for the active (default Receive) type, and are disabled.
    const dateField = page.getByLabel('Tanggal Arrival')
    const timeField = page.getByLabel('Waktu Arrival')
    await expect(dateField).toBeVisible()
    await expect(timeField).toBeVisible()
    await expect(dateField).toBeDisabled()
    await expect(timeField).toBeDisabled()

    const timeValueBefore = await timeField.inputValue()
    expect(timeValueBefore).not.toBe('')

    // No live ticking — the same value must still be shown after a real
    // wait (unlike the old dispatch clock, which used to tick every
    // second).
    await page.waitForTimeout(2500)
    const timeValueAfter = await timeField.inputValue()
    expect(timeValueAfter).toBe(timeValueBefore)
  })

  // Scenario 4: "Field Wajib Belum Lengkap" (Receive)
  test('Form Weighbridge — Field Wajib Belum Lengkap', async ({ page }) => {
    await page.goto('/stations/weighbridge/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/weighbridge\/form\/(.+)/)

    // Leave every required field empty.
    await page.getByTestId('save-button').click()

    await expect(page.getByText('wajib diisi.').first()).toBeVisible()
    await expect(page).toHaveURL(/\/stations\/weighbridge\/form\//)
  })

  // Scenario 5: conditional destination field with validation (Dispatch)
  test('Form Weighbridge — Tujuan Muatan Wajib Diisi (Dispatch)', async ({ page }) => {
    await page.goto('/stations/weighbridge/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/weighbridge\/form\/(.+)/)

    await page.getByTestId('weighbridge-type-dispatch').click()
    // Destination field renders only for Dispatch.
    await expect(page.getByLabel('Tujuan Muatan')).toBeVisible()

    await fillBaseRequiredFields(page)
    // Destination intentionally left empty.

    await page.getByTestId('save-button').click()

    await expect(page.getByText('Tujuan Muatan wajib diisi.')).toBeVisible()
    await expect(page).toHaveURL(/\/stations\/weighbridge\/form\//)
  })

  // Scenario 6: type-switch discard/reset behavior
  test('Form Weighbridge — Ganti Tipe Membuang Tanggal/Waktu dan Tujuan Muatan', async ({ page }) => {
    await page.goto('/stations/weighbridge/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/weighbridge\/form\/(.+)/)

    await page.getByTestId('weighbridge-type-dispatch').click()
    await page.getByLabel('Tujuan Muatan').fill('PKS Sementara')
    await expect(page.getByLabel('Tanggal Dispatch')).toBeVisible()

    // Switch back to Receive — Destination must disappear, Arrival labels
    // must take over.
    await page.getByTestId('weighbridge-type-receive').click()
    await expect(page.getByLabel('Tujuan Muatan')).toHaveCount(0)
    await expect(page.getByLabel('Tanggal Arrival')).toBeVisible()
    await expect(page.getByLabel('Tanggal Dispatch')).toHaveCount(0)

    // Switch back to Dispatch — Destination is empty again (discarded, not
    // merely hidden).
    await page.getByTestId('weighbridge-type-dispatch').click()
    await expect(page.getByLabel('Tujuan Muatan')).toHaveValue('')
  })

  // Scenario 7: "Lanjutkan Draft Paused" — stored record_datetime and
  // destination are both preserved on resume (not reset).
  test('Form Weighbridge — Lanjutkan Draft Paused', async ({ page }) => {
    const userId = await getAuthUserId(page)
    const storedRecordDatetime = '2020-01-01T00:00:00.000Z'
    await seedWeighbridgeDraft(page, userId, {
      id: 'e2e-wb-paused-resume',
      status: 'draft_paused',
      wbCardNumber: 'WB-PAUSED-01',
      weighbridgeType: 'dispatch',
      recordDatetime: storedRecordDatetime,
      destination: 'PKS Lama',
    })

    await page.goto('/stations/weighbridge/monitor')
    await page.getByTestId('draft-item-e2e-wb-paused-resume').click()
    await page.waitForURL('**/stations/weighbridge/form/e2e-wb-paused-resume')

    // Fields populated from the draft.
    await expect(page.getByLabel('WB Card Number/ID')).toHaveValue('WB-PAUSED-01')
    await expect(page.getByTestId('weighbridge-type-dispatch')).toHaveAttribute('aria-selected', 'true')
    await expect(page.getByLabel('Tujuan Muatan')).toHaveValue('PKS Lama')

    // record_datetime is the STORED (2020) value, not "now" — proving no
    // reset/re-tick happens for a resumed draft whose value was already
    // set.
    const expectedDate = new Intl.DateTimeFormat('id-ID', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    }).format(new Date(storedRecordDatetime))
    await expect(page.getByLabel('Tanggal Dispatch')).toHaveValue(expectedDate)
    await expect(page.getByLabel('Tanggal Dispatch')).toBeDisabled()
    await expect(page.getByLabel('Waktu Dispatch')).toBeDisabled()
  })

  // Scenario 8: "Back Dengan Perubahan Belum Tersimpan" (unchanged)
  test('Form Weighbridge — Back Dengan Perubahan Belum Tersimpan', async ({ page }) => {
    await page.goto('/stations/weighbridge/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/weighbridge\/form\/(.+)/)

    await page.getByLabel('WB Card Number/ID').fill('WB-DIRTY')
    await page.getByTestId('back-button').click()

    await expect(page.getByRole('alertdialog')).toBeVisible()
    await expect(page).toHaveURL(/\/stations\/weighbridge\/form\//)
  })

  // Scenario 9: "Pause Progress" (unchanged)
  test('Form Weighbridge — Pause Progress', async ({ page }) => {
    await page.goto('/stations/weighbridge/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/weighbridge\/form\/(.+)/)

    // Fill partially — leave a required field (Nama Supir) empty.
    await page.getByLabel('WB Card Number/ID').fill('WB-PAUSE-01')
    await page.getByLabel('No. Kendaraan').fill('B 9999 ZZ')

    // Pause has NO required-field validation — if it were blocked, the
    // navigation below would never happen and this waitForURL would time
    // out, so a successful wait is itself proof Pause succeeded despite the
    // incomplete required fields.
    await page.getByTestId('pause-button').click()
    await page.waitForURL('**/stations/weighbridge/monitor')
  })

  // Scenario 10: "Clear Draft" (confirm) (unchanged)
  test('Form Weighbridge — Clear Draft (confirm)', async ({ page }) => {
    await page.goto('/stations/weighbridge/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/weighbridge\/form\/(.+)/)
    const recordId = page.url().split('/').pop()

    await page.getByTestId('clear-button').click()
    await expect(page.getByRole('alertdialog')).toBeVisible()
    await page.getByRole('button', { name: 'Ya, Hapus' }).click()
    await page.waitForURL('**/stations/weighbridge/monitor')

    await expect(page.getByTestId(`draft-item-${recordId}`)).toHaveCount(0)
  })

  // Scenario 11: "Clear Draft" (cancel) (unchanged)
  test('Form Weighbridge — Clear Draft (cancel)', async ({ page }) => {
    await page.goto('/stations/weighbridge/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/weighbridge\/form\/(.+)/)

    await page.getByLabel('WB Card Number/ID').fill('WB-KEEP')
    await page.getByTestId('clear-button').click()
    await expect(page.getByRole('alertdialog')).toBeVisible()
    await page.getByRole('button', { name: 'Batal' }).click()

    await expect(page.getByRole('alertdialog')).toBeHidden()
    await expect(page).toHaveURL(/\/stations\/weighbridge\/form\//)
    await expect(page.getByLabel('WB Card Number/ID')).toHaveValue('WB-KEEP')
  })

  // Scenario 12: "Tap Breadcrumb" (unchanged)
  test('Form Weighbridge — Tap Breadcrumb', async ({ page }) => {
    await page.goto('/stations/weighbridge/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/weighbridge\/form\/(.+)/)

    await page.getByTestId('breadcrumb-weighbridge').click()
    await page.waitForURL('**/stations/weighbridge/monitor')
  })

  // Scenario 13: "Buka Menu Hamburger" (unchanged)
  test('Form Weighbridge — Buka Menu Hamburger', async ({ page }) => {
    await page.goto('/stations/weighbridge/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/weighbridge\/form\/(.+)/)

    await expect(page.getByTestId('nav-menu')).toBeHidden()

    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu-change-password')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })
})
