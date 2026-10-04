import { test, expect, type Page } from '@playwright/test'
import { login, getAuthUserId } from './helpers'

// Audit 2026-10-05: detail Data Preview mobile menampilkan label pilihan yang
// sama dengan Detail web/ekspor (backend Display::OPTION_LABELS) — "Ya",
// "Run", "Fault" — bukan nilai mentah tersimpan "y", "run", "fault".
// Record diseed langsung ke SQLite lokal lewat bridge dev `window.__mslTestDb`.
// Setiap tes juga memastikan tidak ada pageerror / console.error / HTTP >= 400.

interface Station {
  slug: string
  table: string
  idColumn: string
  detailTable: string
  fk: string
  values: Record<string, string>
  expected: string[]
}

const STATIONS: Station[] = [
  {
    slug: 'boiler-room',
    table: 'boiler_room_record',
    idColumn: 'boiler_room_id',
    detailTable: 'boiler_room_detail',
    fk: 'boiler_room_record_id',
    values: { blowdown_executed: 'y', sootblowing_executed: 'n' },
    expected: ['Blowdown Executed: Ya', 'Sootblowing Executed: Tidak'],
  },
  {
    slug: 'effluent-plant',
    table: 'effluent_plant_record',
    idColumn: 'effluent_plant_id',
    detailTable: 'effluent_plant_detail',
    fk: 'effluent_plant_record_id',
    values: { biogas_flare_status: 'fault', dosing_pump_1_status: 'run', sludge_dewatering_status: 'stop' },
    expected: ['Biogas Flare Status: Fault', 'Dosing Pump 1 Status: Run', 'Sludge Dewatering Status: Stop'],
  },
  {
    slug: 'engine-room',
    table: 'engine_room_record',
    idColumn: 'engine_room_id',
    detailTable: 'engine_room_detail',
    fk: 'engine_room_record_id',
    values: { diesel_gen_1_status: 'standby', diesel_gen_2_status: 'off' },
    expected: ['Diesel Gen 1 Status: Standby', 'Diesel Gen 2 Status: Off'],
  },
]

function guard(page: Page) {
  const problems: string[] = []
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`))
  page.on('console', (m) => m.type() === 'error' && problems.push(`console.error: ${m.text()}`))
  page.on('response', (r) => r.status() >= 400 && problems.push(`HTTP ${r.status()} ${r.url()}`))
  return { assertClean: () => expect(problems, problems.join('\n')).toEqual([]) }
}

for (const station of STATIONS) {
  test(`${station.slug}: detail Data Preview menampilkan label pilihan, bukan nilai mentah`, async ({ page }) => {
    const g = guard(page)
    await page.setViewportSize({ width: 390, height: 844 })
    await login(page)
    const userId = await getAuthUserId(page)
    const id = `e2e-optlabel-${station.slug}-${Date.now()}`

    await page.evaluate(
      async ({ s, id, userId }) => {
        const db = (window as unknown as { __mslTestDb: { run: (sql: string, p?: unknown[]) => Promise<unknown> } }).__mslTestDb
        const now = new Date().toISOString()
        await db.run(
          `INSERT INTO ${s.table} (id, ${s.idColumn}, date, status, created_by, created_at, updated_at) VALUES (?, ?, ?, 'saved', ?, ?, ?)`,
          [id, 'OPT-LABEL', now.slice(0, 10), userId, now, now],
        )
        const cols = Object.keys(s.values)
        await db.run(
          `INSERT INTO ${s.detailTable} (id, ${s.fk}, time_slot, ${cols.join(', ')}, created_at, updated_at) VALUES (?, ?, '07:00', ${cols.map(() => '?').join(', ')}, ?, ?)`,
          [`${id}-d1`, id, ...cols.map((c) => s.values[c]), now, now],
        )
      },
      { s: station, id, userId },
    )

    await page.goto(`/stations/${station.slug}/preview/${id}`)
    const row = page.getByTestId(`${station.slug}-detail-row-${id}-d1`)
    await expect(row).toBeVisible()
    for (const text of station.expected) {
      await expect(row).toContainText(text)
    }
    for (const [, raw] of Object.entries(station.values)) {
      await expect(row).not.toContainText(new RegExp(`: ${raw}(?![a-z])`))
    }
    g.assertClean()
  })
}
