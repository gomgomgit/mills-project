import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

/**
 * form-grading.spec.ts — screen-011--form-grading /
 * usecase-011--form-grading (entity-catalog v2 rewrite, 2026-08-19).
 *
 * FULL REWRITE, replacing every scenario in the previous version of this
 * file (built against grading_record/grading_detail's pre-v2 shape —
 * vehicle_number/driver_name/block header fields, free-text detail
 * `category`, Checked-By-visible-to-supervisor — none of which exist
 * anymore; see FormGradingView.vue's own "FULL REWRITE" header comment).
 * Runs against the real Vite dev server + real local SQLite (via
 * @capacitor-community/sqlite's web/wasm implementation) — this is a
 * mobile-only screen, but mobile screens ARE browser-testable through the
 * dev server (same as every other screens-005-through-015 e2e spec in this
 * directory), not "mobile-only, deferred".
 *
 * Seeds `weighbridge_record` rows directly via the dev-only
 * `window.__mslTestDb` bridge (same pattern as
 * form-weighbridge.spec.ts's/monitor-grading.spec.ts's own seed helpers)
 * — this screen's "WB Card No" dropdown needs a real local
 * weighbridge_record row to select from, and there is no in-app flow
 * (Form Weighbridge is a different station) worth driving end-to-end just
 * to produce one.
 *
 * ALSO seeds `grading_parameter` rows directly via the same bridge —
 * KNOWN ISSUE (see this suite's known_issues in the implementing agent's
 * report): `seedGradingParametersIfNeeded()` (localSchema.ts, the
 * function meant to seed the 16 canonical Quality Parameter rows) is
 * exported but never actually invoked anywhere in the app — not from
 * main.ts's `bootstrap()` (which only calls `initLocalSchema()`, and
 * `initLocalSchema()` itself only runs the `CREATE TABLE` statements, NOT
 * `seedGradingParametersIfNeeded()`, despite that function's own doc
 * comment claiming it is "called unconditionally from
 * initLocalSchema()"), and not from stores/auth.ts's login flow either
 * (which only calls `seedDefaultStationsIfNeeded()`). So in a real run of
 * this app today, `grading_parameter` is EMPTY and Form Grading's Quality
 * Parameter dropdown renders with zero real options. This suite seeds
 * two rows directly so it can still exercise that dropdown meaningfully;
 * it does not fix the underlying wiring gap (out of this test-writing
 * task's scope — no implementation file may be touched).
 *
 * Verifies persisted Simpan data by querying `grading_record`/
 * `grading_detail` directly via the `__mslTestDb` bridge, NOT via Data
 * Preview Grading (screen-014) — that screen is a documented
 * pre-existing known_issue, still bound to grading_record/grading_detail's
 * PRE-v2 columns (vehicle_number/driver_name/block/category — see
 * DataPreviewGradingView.vue's and GradingDetailGrid.vue's own header
 * comments), so it would throw/render incorrectly if used for
 * verification here. Mirrors form-weighbridge.spec.ts's own choice to
 * verify via a reliable, format-independent signal rather than a
 * screen with a known pre-existing mismatch.
 */
async function seedWeighbridgeRecord(
  page: Page,
  userId: string,
  overrides: {
    id: string
    wbCardNumber?: string | null
    vehicleNumber?: string | null
    estateSupplier?: string | null
    division?: string | null
    arrivalDatetime?: string | null
  },
): Promise<void> {
  await page.evaluate(
    async ({ userId, id, wbCardNumber, vehicleNumber, estateSupplier, division, arrivalDatetime }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb
      const now = new Date().toISOString()
      await db.run(
        `INSERT OR REPLACE INTO weighbridge_record
           (id, status, wb_card_number, arrival_datetime, vehicle_number, estate_supplier, division, created_by, created_at, updated_at)
         VALUES (?, 'saved', ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          id,
          wbCardNumber ?? null,
          arrivalDatetime ?? now,
          vehicleNumber ?? null,
          estateSupplier ?? null,
          division ?? null,
          userId,
          now,
          now,
        ],
      )
    },
    { userId, id: overrides.id, wbCardNumber: overrides.wbCardNumber, vehicleNumber: overrides.vehicleNumber, estateSupplier: overrides.estateSupplier, division: overrides.division, arrivalDatetime: overrides.arrivalDatetime },
  )
}

// See this file's header comment — grading_parameter is empty in a real
// run (seedGradingParametersIfNeeded() is never actually invoked by the
// app), so this suite seeds its own two rows directly.
async function seedGradingParameters(page: Page): Promise<void> {
  await page.evaluate(async () => {
    const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
      .__mslTestDb
    const now = new Date().toISOString()
    const rows: Array<[string, string, string, number]> = [
      ['e2e-grading-param-kg', 'Brondolan Segar', 'kg', 1],
      ['e2e-grading-param-bunch', 'Masak', 'bunch', 2],
    ]

    for (const [id, name, uom, sortOrder] of rows) {
      await db.run(
        `INSERT OR REPLACE INTO grading_parameter (id, name, uom, sort_order, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [id, name, uom, sortOrder, now, now],
      )
    }
  })
}

async function getGradingRecord(page: Page, id: string): Promise<Record<string, unknown> | null> {
  return page.evaluate(async (recordId) => {
    const db = (window as unknown as { __mslTestDb: { query: (sql: string, params?: unknown[]) => Promise<unknown[]> } })
      .__mslTestDb
    const rows = await db.query('SELECT * FROM grading_record WHERE id = ?', [recordId])
    return (rows[0] as Record<string, unknown>) ?? null
  }, id)
}

async function getGradingDetailRows(page: Page, recordId: string): Promise<Array<Record<string, unknown>>> {
  return page.evaluate(async (id) => {
    const db = (window as unknown as { __mslTestDb: { query: (sql: string, params?: unknown[]) => Promise<unknown[]> } })
      .__mslTestDb
    return db.query('SELECT * FROM grading_detail WHERE grading_record_id = ? ORDER BY created_at ASC', [id]) as Promise<
      Array<Record<string, unknown>>
    >
  }, recordId)
}

test.describe('Form Grading (screen-011)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  // Scenario 1: "success" — full happy path: header fields, WB Card No
  // selection (verifying auto-fill), a detail row with a Quality
  // Parameter selection (verifying the UOM snapshot + Percentage
  // calculation), Simpan, navigation to Monitor Grading, and the
  // persisted data verified directly against local SQLite.
  test('Form Grading — success', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedGradingParameters(page)
    await seedWeighbridgeRecord(page, userId, {
      id: 'e2e-wb-for-grading-1',
      wbCardNumber: 'WB-GR-001',
      vehicleNumber: 'B 1234 GR',
      estateSupplier: 'Estate Grading A',
      division: 'Divisi 1',
    })

    await page.goto('/stations/grading/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/grading\/form\/(.+)/)
    const recordId = page.url().split('/').pop() as string

    await page.getByLabel('Grading No').fill('GR-E2E-001')

    await page.getByTestId('wb-card-no-select').selectOption('e2e-wb-for-grading-1')
    // WB Card No selection auto-fills these three fields.
    await expect(page.getByLabel('License Plate No')).toHaveValue('B 1234 GR')
    await expect(page.getByLabel('Estate')).toHaveValue('Estate Grading A')
    await expect(page.getByLabel('Divisi')).toHaveValue('Divisi 1')

    await page.getByLabel('Vehicle Code').fill('VC-E2E-001')
    await page.getByLabel('Netto (kg)').fill('1000')
    await page.getByLabel('Quantity (bunch)').fill('50')

    await page.getByTestId('add-detail-row-button').click()
    await page.getByTestId('detail-parameter-select-0').selectOption('e2e-grading-param-kg')
    await page.locator('#detail-qty-0').fill('250')

    // Quality Parameter selection snapshots the UOM, and Percentage is
    // computed live (250 / 1000 netto * 100 = 25.00%, uom='kg').
    await expect(page.locator('#detail-uom-0')).toHaveValue('kg')
    await expect(page.getByTestId('detail-percentage-0')).toHaveText('25.00%')

    await page.getByTestId('save-button').click()
    await page.waitForURL('**/stations/grading/monitor')

    const savedRecord = await getGradingRecord(page, recordId)
    expect(savedRecord?.status).toBe('saved')
    expect(savedRecord?.grading_number).toBe('GR-E2E-001')
    expect(savedRecord?.weighbridge_record_id).toBe('e2e-wb-for-grading-1')
    expect(savedRecord?.vehicle_code).toBe('VC-E2E-001')
    expect(Number(savedRecord?.netto)).toBe(1000)
    expect(Number(savedRecord?.quantity)).toBe(50)

    const savedDetails = await getGradingDetailRows(page, recordId)
    expect(savedDetails).toHaveLength(1)
    expect(savedDetails[0].grading_parameter_id).toBe('e2e-grading-param-kg')
    expect(savedDetails[0].uom).toBe('kg')
    expect(Number(savedDetails[0].quantity)).toBe(250)
    expect(Number(savedDetails[0].percentage)).toBeCloseTo(25, 1)
  })

  // Scenario 2: "Field Wajib Belum Lengkap"
  test('Form Grading — Field Wajib Belum Lengkap', async ({ page }) => {
    await page.goto('/stations/grading/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/grading\/form\/(.+)/)

    // Leave every required field empty.
    await page.getByTestId('save-button').click()

    await expect(page.getByText('wajib diisi.').first()).toBeVisible()
    await expect(page).toHaveURL(/\/stations\/grading\/form\//)
  })

  // Scenario 3: "Belum Ada Baris Grading Detail"
  test('Form Grading — Belum Ada Baris Grading Detail', async ({ page }) => {
    const userId = await getAuthUserId(page)
    await seedWeighbridgeRecord(page, userId, { id: 'e2e-wb-for-grading-2', wbCardNumber: 'WB-GR-002' })

    await page.goto('/stations/grading/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/grading\/form\/(.+)/)

    await page.getByLabel('Grading No').fill('GR-E2E-002')
    await page.getByTestId('wb-card-no-select').selectOption('e2e-wb-for-grading-2')
    await page.getByLabel('Vehicle Code').fill('VC-E2E-002')
    await page.getByLabel('Estate').fill('Estate C')
    await page.getByLabel('Netto (kg)').fill('500')
    await page.getByLabel('Quantity (bunch)').fill('20')
    // No detail row added at all.

    await page.getByTestId('save-button').click()

    await expect(page.getByTestId('detail-rows-error')).toContainText('Minimal 1 baris')
    await expect(page).toHaveURL(/\/stations\/grading\/form\//)
  })

  // Scenario 4: "Pause Progress"
  test('Form Grading — Pause Progress', async ({ page }) => {
    await page.goto('/stations/grading/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/grading\/form\/(.+)/)

    // Fill partially — leave WB Card No/Vehicle Code/Estate/Netto/Quantity
    // (all required) empty, and add no detail row at all.
    await page.getByLabel('Grading No').fill('GR-PAUSE-01')

    // Pause has NO required-field/detail-row validation — if it were
    // blocked, the navigation below would never happen and this
    // waitForURL would time out, so a successful wait is itself proof
    // Pause succeeded despite the incomplete form.
    await page.getByTestId('pause-button').click()
    await page.waitForURL('**/stations/grading/monitor')

    await expect(page.getByText('GR-PAUSE-01')).toBeVisible()
  })

  // Scenario 5: "Clear Draft" (confirm)
  test('Form Grading — Clear Draft (confirm)', async ({ page }) => {
    await page.goto('/stations/grading/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/grading\/form\/(.+)/)
    const recordId = page.url().split('/').pop() as string

    await page.getByTestId('clear-button').click()
    await expect(page.getByRole('alertdialog')).toBeVisible()
    await page.getByRole('button', { name: 'Ya, Hapus' }).click()
    await page.waitForURL('**/stations/grading/monitor')

    await expect(page.getByTestId(`draft-item-${recordId}`)).toHaveCount(0)
  })

  // Scenario 6: "Clear Draft" (cancel)
  test('Form Grading — Clear Draft (cancel)', async ({ page }) => {
    await page.goto('/stations/grading/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/grading\/form\/(.+)/)

    await page.getByLabel('Grading No').fill('GR-KEEP')
    await page.getByTestId('clear-button').click()
    await expect(page.getByRole('alertdialog')).toBeVisible()
    await page.getByRole('button', { name: 'Batal' }).click()

    await expect(page.getByRole('alertdialog')).toBeHidden()
    await expect(page).toHaveURL(/\/stations\/grading\/form\//)
    await expect(page.getByLabel('Grading No')).toHaveValue('GR-KEEP')
  })

  // Scenario 7: "Back Dengan Perubahan Belum Tersimpan"
  test('Form Grading — Back Dengan Perubahan Belum Tersimpan', async ({ page }) => {
    await page.goto('/stations/grading/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/grading\/form\/(.+)/)

    await page.getByLabel('Grading No').fill('GR-DIRTY')
    await page.getByTestId('back-button').click()

    await expect(page.getByRole('alertdialog')).toBeVisible()
    await expect(page).toHaveURL(/\/stations\/grading\/form\//)
  })

  // Quality Parameter Tidak Bisa Duplikat Antar Baris
  test('Form Grading — Quality Parameter Tidak Bisa Duplikat Antar Baris', async ({ page }) => {
    await seedGradingParameters(page)

    await page.goto('/stations/grading/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/grading\/form\/(.+)/)

    await page.getByTestId('add-detail-row-button').click()
    await page.getByTestId('add-detail-row-button').click()

    await page.getByTestId('detail-parameter-select-0').selectOption('e2e-grading-param-kg')

    // Assert by option `value` (the parameter id), not visible text — the
    // real app seeds its own 16 canonical parameters at boot
    // (seedGradingParametersIfNeeded(), wired into main.ts), some of which
    // share a NAME with this test's own fixture rows (e.g. "Brondolan
    // Segar") but have a different id. Only the id this test actually
    // selected should be excluded from the other row.
    const row1Values = await page.getByTestId('detail-parameter-select-1').locator('option').evaluateAll((options) =>
      options.map((option) => (option as HTMLOptionElement).value),
    )
    expect(row1Values).not.toContain('e2e-grading-param-kg')
    expect(row1Values).toContain('e2e-grading-param-bunch')

    // Removing the row that used it frees the parameter up again.
    await page.getByTestId('remove-detail-row-button').first().click()
    const row0ValuesAfterRemove = await page.getByTestId('detail-parameter-select-0').locator('option').evaluateAll((options) =>
      options.map((option) => (option as HTMLOptionElement).value),
    )
    expect(row0ValuesAfterRemove).toContain('e2e-grading-param-kg')
  })

  // Scenario 8: "Tap Breadcrumb"
  test('Form Grading — Tap Breadcrumb', async ({ page }) => {
    await page.goto('/stations/grading/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/grading\/form\/(.+)/)

    await page.getByTestId('breadcrumb-grading').click()
    await page.waitForURL('**/stations/grading/monitor')
  })

  // Scenario 9: "Buka Menu Hamburger"
  test('Form Grading — Buka Menu Hamburger', async ({ page }) => {
    await page.goto('/stations/grading/monitor')
    await page.getByTestId('new-data-button').click()
    await page.waitForURL(/\/stations\/grading\/form\/(.+)/)

    await expect(page.getByTestId('nav-menu')).toBeHidden()

    await page.getByTestId('hamburger-button').click()

    await expect(page.getByTestId('nav-menu')).toBeVisible()
    await expect(page.getByTestId('nav-menu-change-password')).toContainText('Ganti Password')
    await expect(page.getByTestId('nav-menu-logout')).toContainText('Logout')
  })
})
