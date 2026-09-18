/**
 * syncServiceSchemaConformance.spec.ts — guards the column names
 * syncService.ts SELECTs against the local SQLite DDL in localSchema.ts.
 *
 * Why this exists (2026-09-17): the config-driven refactor of syncService on
 * 2026-09-14 listed `ripple_mill_1_efficiency_percent` /
 * `ripple_mill_2_efficiency_percent` for kernel_plant_detail. Those columns
 * do not exist — the real ones are `ripple_mill_1_amps` /
 * `ripple_mill_2_amps` — so on a device the SELECT fails with "no such
 * column" and Kernel Plant, which synced correctly BEFORE the refactor,
 * stopped syncing entirely.
 *
 * syncService.spec.ts could not catch it: it mocks `query()`, so any column
 * name at all "succeeds". This test needs no database — it reads both source
 * files as text and checks every selected column is actually declared.
 */
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const root = resolve(__dirname, '..')
const schema = readFileSync(resolve(root, 'src/services/localSchema.ts'), 'utf8')
const sync = readFileSync(resolve(root, 'src/services/syncService.ts'), 'utf8')

function declaredColumns(table: string): Set<string> {
  const match = schema.match(new RegExp(`CREATE TABLE IF NOT EXISTS ${table} \\(([\\s\\S]*?)\\n\\s*\\)`))
  if (!match) return new Set()
  return new Set(
    match[1]
      .split('\n')
      .map((line) => line.trim().split(/\s+/)[0])
      .filter(Boolean),
  )
}

const configs = [...sync.matchAll(/table: '(\w+)',[\s\S]*?detailTable: '(\w+)',[\s\S]*?detailColumns: \[([\s\S]*?)\]/g)].map(
  ([, table, detailTable, columns]) => ({
    table,
    detailTable,
    columns: [...columns.matchAll(/'([a-z_0-9]+)'/g)].map((m) => m[1]),
  }),
)

describe('syncService — every selected column exists in the local schema', () => {
  it('finds the station push configs to check', () => {
    // If this drops, the regex stopped matching and the checks below would
    // pass vacuously — fail loudly instead.
    expect(configs.length).toBeGreaterThanOrEqual(15)
  })

  it.each(configs)('$detailTable — all detail columns are declared', ({ detailTable, columns }) => {
    const declared = declaredColumns(detailTable)

    expect(declared.size, `${detailTable} not found in localSchema.ts`).toBeGreaterThan(0)
    expect(columns.filter((column) => !declared.has(column))).toEqual([])
  })

  it.each(configs)('$table — the record table exists', ({ table }) => {
    expect(declaredColumns(table).size, `${table} not found in localSchema.ts`).toBeGreaterThan(0)
  })
})
