/**
 * sqlJsLocalDb — a REAL in-memory SQLite (sql.js, already a dependency for
 * jeep-sqlite) behind the same `query()`/`run()` shape as
 * src/services/localDb.ts.
 *
 * Why this exists (audit 2026-10-04): most repo specs mock `query()` with a
 * function that returns a hand-written full row no matter what the SQL
 * text says. That hid a real production bug — millSettingRepo's SELECT
 * omitted `immediate_sync_enabled`, so write-through never activated, yet
 * every unit test was green because the mock returned the column anyway.
 * Specs that use this harness run the real localSchema DDL and the real
 * SQL, so a column missing from a SELECT/INSERT fails the test.
 *
 * Usage in a spec:
 *
 *   vi.mock('@/services/localDb', async () => (await import('./support/sqlJsLocalDb')).localDbMock)
 *   ...
 *   beforeEach(async () => { await resetSqlJsDb(); await initLocalSchema() })
 */
import initSqlJs, { type Database } from 'sql.js'
import { createRequire } from 'node:module'

const require = createRequire(import.meta.url)

let SQL: Awaited<ReturnType<typeof initSqlJs>> | null = null
let db: Database | null = null

async function getDb(): Promise<Database> {
  if (!SQL) {
    SQL = await initSqlJs({ locateFile: () => require.resolve('sql.js/dist/sql-wasm.wasm') })
  }
  if (!db) {
    db = new SQL.Database()
  }
  return db
}

/** Drops the whole database — call in beforeEach for isolation. */
export async function resetSqlJsDb(): Promise<void> {
  if (db) {
    db.close()
    db = null
  }
  await getDb()
}

function normalizeParams(params: unknown[]): (string | number | null | Uint8Array)[] {
  return params.map((p) => {
    if (p === undefined) return null
    if (typeof p === 'boolean') return p ? 1 : 0
    return p as string | number | null | Uint8Array
  })
}

export async function query<T = Record<string, unknown>>(sql: string, params: unknown[] = []): Promise<T[]> {
  const database = await getDb()
  const statement = database.prepare(sql)
  try {
    statement.bind(normalizeParams(params))
    const rows: T[] = []
    while (statement.step()) {
      rows.push(statement.getAsObject() as T)
    }
    return rows
  } finally {
    statement.free()
  }
}

export async function run(sql: string, params: unknown[] = []): Promise<{ changes: number }> {
  const database = await getDb()
  database.run(sql, normalizeParams(params))
  return { changes: database.getRowsModified() }
}

export const localDbMock = {
  query,
  run,
  open: async () => undefined,
  localDb: { query, run, open: async () => undefined },
  default: { query, run, open: async () => undefined },
}
