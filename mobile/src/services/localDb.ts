import { Capacitor } from '@capacitor/core'
import { CapacitorSQLite, SQLiteConnection, type SQLiteDBConnection } from '@capacitor-community/sqlite'

/**
 * localDb — thin wrapper around @capacitor-community/sqlite (localDb,
 * shared local data-access foundation).
 *
 * First screen to touch @capacitor-community/sqlite (screen-005--home /
 * usecase-005--home). All mobile station-ops screens (006-015) operate on
 * the same 3 local SQLite tables (weighbridge-record, grading-record,
 * cages-track-record — mirrored locally with the same shape as their MySQL
 * migrations, per shared_decisions' offline-first sync design) plus the
 * read-only `station` reference table, so the connection/query plumbing is
 * centralized here rather than duplicated per screen/repo.
 *
 * Deliberately minimal: a lazy connection singleton (`getConnection()`) and
 * a single generic `query<T>()` method. The native connection path
 * (CapacitorSQLite / SQLiteConnection APIs) cannot run in this sandbox (no
 * device/simulator, no jsdom SQLite bridge) — it is structurally reasonable
 * but untested here; screens 006-015 and this screen's own tests exercise
 * it exclusively through `vi.mock('@/services/localDb')` overriding
 * `query()`, which is why `query()`'s public shape (a single async
 * `(sql, params?) => Promise<T[]>` function) is kept trivially mockable.
 *
 * DB_NAME / DB_VERSION are placeholders — the authoritative local schema
 * (CREATE TABLE statements, migration/versioning strategy) is expected to
 * land with whichever screen first needs to WRITE local records (draft
 * create/update — likely screen-006 onward); this screen is read-only
 * (SELECT only, via draftRecordsRepo.ts) so it does not create tables.
 *
 * Update (screen-007--monitor-weighbridge): the authoritative local schema
 * has now landed — see services/localSchema.ts's `initLocalSchema()`, the
 * first screen to WRITE local records. `run()` below is added alongside
 * `query()` as the generic write primitive it (and this screen's
 * weighbridgeRecordRepo.ts) need — kept to the same trivially-mockable
 * shape as `query()` for the same reason (no device/simulator in this
 * sandbox; screens exercise the native path exclusively through
 * `vi.mock('@/services/localDb')`).
 */
const DB_NAME = 'mill_smart_log'
const DB_VERSION = 1

let sqliteConnection: SQLiteConnection | null = null
let dbConnection: SQLiteDBConnection | null = null
let connectPromise: Promise<SQLiteDBConnection> | null = null

function getSqliteConnection(): SQLiteConnection {
  if (!sqliteConnection) {
    sqliteConnection = new SQLiteConnection(CapacitorSQLite)
  }

  return sqliteConnection
}

let webStoreInitPromise: Promise<void> | null = null

/**
 * On the web platform (this app running in a plain browser — Vite dev
 * server, or any non-native shell), @capacitor-community/sqlite has no
 * backing store until `initWebStore()` is called against the <jeep-sqlite>
 * custom element (registered in main.ts, mounted in App.vue). Must run
 * once, and must complete before the first createConnection()/open() call
 * below. No-op on native platforms (Android/iOS talk to the native SQLite
 * plugin directly and don't need jeep-sqlite at all).
 */
async function initWebStoreIfNeeded(): Promise<void> {
  if (Capacitor.getPlatform() !== 'web') {
    return
  }

  if (!webStoreInitPromise) {
    webStoreInitPromise = (async () => {
      await customElements.whenDefined('jeep-sqlite')
      await getSqliteConnection().initWebStore()
    })()
  }

  return webStoreInitPromise
}

/**
 * Lazily opens (or reuses) the single local SQLite connection for the app.
 * Not called eagerly at module load — only the first `query()` call
 * triggers a connect, so importing this module (e.g. transitively, via
 * draftRecordsRepo.ts) has no side effect, which is what keeps it safe to
 * import in unit tests that mock `query()` directly instead.
 */
async function getConnection(): Promise<SQLiteDBConnection> {
  if (dbConnection) {
    return dbConnection
  }

  if (!connectPromise) {
    connectPromise = (async () => {
      await initWebStoreIfNeeded()

      const connection = getSqliteConnection()
      const isConsistent = await connection.checkConnectionsConsistency()
      const alreadyOpen = (await connection.isConnection(DB_NAME, false)).result

      const db =
        isConsistent.result && alreadyOpen
          ? await connection.retrieveConnection(DB_NAME, false)
          : await connection.createConnection(DB_NAME, false, 'no-encryption', DB_VERSION, false)

      await db.open()
      dbConnection = db

      return db
    })()
  }

  return connectPromise
}

/**
 * Opens the local database connection ahead of time (optional — `query()`
 * opens lazily on first use). Exposed so app bootstrap (main.ts) or a
 * future screen can warm the connection eagerly if desired.
 */
export async function open(): Promise<void> {
  await getConnection()
}

/**
 * Runs a read query against the local SQLite database and returns typed
 * rows. This is the only method screens/repos should call directly — kept
 * to this single generic shape so it stays trivially mockable via
 * `vi.mock('@/services/localDb')` in unit tests (no device/simulator
 * available in this sandbox to exercise the real native path).
 */
export async function query<T = Record<string, unknown>>(sql: string, params: unknown[] = []): Promise<T[]> {
  const db = await getConnection()
  const result = await db.query(sql, params)

  return (result.values ?? []) as T[]
}

/**
 * Runs a write statement (INSERT / UPDATE / DELETE / DDL such as CREATE
 * TABLE) against the local SQLite database. Added by
 * screen-007--monitor-weighbridge — the first screen that WRITES local
 * records (weighbridgeRecordRepo.ts) and the first to run schema DDL
 * (localSchema.ts's `initLocalSchema()`). Mirrors `query()`'s shape
 * (single generic `(sql, params?) => Promise<...>` function) for the same
 * mockability reason — tests override `run()` via
 * `vi.mock('@/services/localDb')` instead of exercising the real native
 * connection.
 */
export async function run(sql: string, params: unknown[] = []): Promise<{ changes: number }> {
  const db = await getConnection()
  const result = await db.run(sql, params)

  return { changes: result.changes?.changes ?? 0 }
}

export const localDb = {
  open,
  query,
  run,
}

export default localDb
