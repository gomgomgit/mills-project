import { query, run } from '@/services/localDb'

/**
 * localSchema — authoritative local SQLite DDL (schema definition +
 * init), first introduced by screen-007--monitor-weighbridge /
 * usecase-007--monitor-weighbridge — the first mobile screen that WRITES
 * local records (screens 005/006 were read-only; see localDb.ts's and
 * draftRecordsRepo.ts's header comments, which explicitly deferred schema
 * ownership to "whichever screen first needs to WRITE").
 *
 * Design choice — plain TypeScript (CREATE TABLE strings run via
 * localDb.ts's `run()`) rather than `.sql` migration files under
 * `src/db/migrations/`:
 *   - `@capacitor-community/sqlite` does not ship a migration-runner that
 *     reads `.sql` files off disk in a Capacitor webview context without
 *     extra plugin config (importFromAssets / jsonschema) not present in
 *     this project yet (see capacitor.config.ts) — adding that machinery
 *     is out of scope for this screen.
 *   - Vite has no built-in "load .sql as raw string" transform configured
 *     in vite.config.ts, so `.sql` files would need an extra plugin
 *     (vite-plugin-raw / `?raw` imports work, but the SQLite plugin's own
 *     `importFromAssets` path is the idiomatic way to ship real `.sql`
 *     migrations, and that's a bigger infra decision than one screen
 *     should make unilaterally).
 *   - Keeping DDL as TS strings run through the exact same `run()`
 *     primitive already established by localDb.ts (see localDb.ts's
 *     header comment update) keeps this screen's addition consistent with
 *     the existing query()/run() mockability contract — tests can
 *     `vi.mock('@/services/localDb')` exactly as screens 005/006 already
 *     do, without introducing a second, differently-tested code path for
 *     schema loading.
 *   - `CREATE TABLE IF NOT EXISTS` makes `initLocalSchema()` naturally
 *     idempotent, so it can safely be called on every app start without a
 *     separate "have we migrated" version check — acceptable for this
 *     stage (DB_VERSION in localDb.ts remains the placeholder single-shot
 *     version; a real migration/versioning strategy is deferred, same as
 *     localDb.ts's existing DB_VERSION placeholder note).
 *
 * Tables:
 *   - `weighbridge_record` — full shape per screen-007's tech spec, the
 *     only table that screen's business logic reads/writes
 *     (weighbridgeRecordRepo.ts).
 *   - `grading_record` / `grading_detail` — full authoritative shape per
 *     screen-008--monitor-grading's tech spec (entity-catalog), replacing
 *     this file's earlier placeholder columns for these two tables.
 *     `grading_record` is read/written by gradingRecordRepo.ts (Monitor
 *     Grading's business_logic steps 1-5: progress summary, create/resume/
 *     pause/delete draft). `grading_detail` (one-to-many, FK
 *     `grading_record_id`) is NOT touched by this screen — Monitor Grading
 *     only needs the parent record's existence/status; row-level detail
 *     CRUD belongs to screen-011--form-grading (not built yet). No FK
 *     enforcement is declared here (SQLite `PRAGMA foreign_keys` is not
 *     turned on anywhere in this app yet — consistent with the rest of
 *     this schema file), so gradingRecordRepo.ts's `deleteDraft()`
 *     explicitly deletes `grading_detail` rows itself before deleting the
 *     parent `grading_record` row (application-level cascade) rather than
 *     relying on a DB-level `ON DELETE CASCADE`.
 *   - `cages_track_record` / `cages_tipped_time` — authoritative shape per
 *     screen-009--monitor-cages-track's tech spec (entity-catalog),
 *     replacing this file's earlier placeholder columns for these two
 *     tables. `cages_track_record` is read/written by
 *     cagesTrackRecordRepo.ts (Monitor Cages Track's business_logic steps
 *     1-5: progress summary, create/resume/pause/delete draft) and also
 *     satisfies `draftRecordsRepo.ts`'s pre-existing assumed column name
 *     (`cages_track_number`, see its header comment). `cages_tipped_time`
 *     (one-to-many, FK `cages_track_record_id`) holds per-cage tip
 *     timestamps — screen-009 only reads a COUNT of these rows for its
 *     progress summary ("jumlah cage/lori tercatat pada sesi berjalan");
 *     row-level CRUD (inserting individual tipped-time rows) belongs to
 *     screen-012--form-cages-track (not built yet). No FK enforcement is
 *     declared here (same as `grading_detail`, see above), so
 *     cagesTrackRecordRepo.ts's `deleteDraft()` explicitly deletes
 *     `cages_tipped_time` rows itself before deleting the parent
 *     `cages_track_record` row (application-level cascade).
 *   - `station` — read-only reference cache table (see stationRepo.ts's
 *     header comment, which already assumed this table exists but never
 *     defined it).
 *
 * Update (2026-08-18, screen-006--station-list): every mobile screen's own
 * comments assumed `station` would be "populated by a separate sync flow"
 * — but no such flow exists anywhere in this project's 32-screen scope
 * (the PRD's only mobile "sync" goal is push-sync of created
 * weighbridge/grading/cages-track records up to the server, not pulling
 * station master data down). Real devices/browsers were left with a
 * permanently empty Station List as a result. Per explicit product
 * decision, the fix is `seedDefaultStationsIfNeeded()` below: the 15
 * MVP stations (3 functional + 12 future-scheme placeholders) are fixed,
 * known domain data — not something that actually needs a live
 * server round-trip for this MVP — so they are seeded locally,
 * idempotently, once busines_unit_id is known (called from
 * `stores/auth.ts`'s `login()`, right after login succeeds). `station`
 * remains conceptually "read-only" from every screen's point of view
 * (stationRepo.ts itself still never writes to it) — this is bootstrap
 * seed data, not a live sync.
 */

const CREATE_WEIGHBRIDGE_RECORD = `
  CREATE TABLE IF NOT EXISTS weighbridge_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    wb_card_number TEXT,
    arrival_datetime TEXT,
    dispatch_datetime TEXT,
    vehicle_number TEXT,
    driver_name TEXT,
    estate_supplier TEXT,
    division TEXT,
    block TEXT,
    gross_weight REAL,
    tare_weight REAL,
    net_weight REAL,
    quantity REAL,
    checked_by TEXT,
    acknowledged_by TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// Authoritative shape per entity-catalog `grading-record` (ver 2,
// 2026-08-18 revision): vehicle_number/driver_name/block REMOVED;
// weighbridge_record_id (FK, selected via WB Card No dropdown on
// screen-011--form-grading), license_plate_no (auto-filled from the
// selected weighbridge_record, editable), vehicle_code, netto, quantity,
// note ADDED. `date` stores a full timestamp (not date-only), consistent
// with weighbridge_record.arrival_datetime's "auto-filled once" pattern.
const CREATE_GRADING_RECORD = `
  CREATE TABLE IF NOT EXISTS grading_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    grading_number TEXT,
    date TEXT,
    weighbridge_record_id TEXT,
    license_plate_no TEXT,
    vehicle_code TEXT,
    estate_supplier TEXT,
    division TEXT,
    netto REAL,
    quantity REAL,
    note TEXT,
    checked_by TEXT,
    acknowledged_by TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// Authoritative shape per entity-catalog `grading-detail` (ver 2,
// 2026-08-18 revision): `category` (free text) REMOVED, replaced by
// `grading_parameter_id` (FK to grading_parameter, selected via dropdown).
// `uom` is a SNAPSHOT copied from the selected grading_parameter.uom at
// selection time (not a live join) so historical rows stay stable if the
// master parameter list changes later. `percentage` is computed on the
// mobile client (screen-011--form-grading business_logic): qty/header.netto
// *100 when uom='kg', qty/header.quantity*100 when uom='bunch'.
const CREATE_GRADING_DETAIL = `
  CREATE TABLE IF NOT EXISTS grading_detail (
    id TEXT PRIMARY KEY,
    grading_record_id TEXT NOT NULL,
    grading_parameter_id TEXT,
    quantity REAL,
    uom TEXT,
    percentage REAL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// New master-data table (entity-catalog `grading-parameter`, added
// 2026-08-18) — read-only reference list for screen-011--form-grading's
// Grading Detail "Quality Parameter" dropdown. Seeded with the 16 canonical
// rows via seedGradingParametersIfNeeded() (below), called from main.ts
// right after initLocalSchema() — NOT from inside initLocalSchema() itself,
// consistent with this file's existing convention (see initLocalSchema()'s
// own doc comment) of keeping schema creation free of seed calls so tests
// that only need `initLocalSchema()` aren't forced to also mock seeding.
// Unlike `station`, this has no business_unit_id scoping (parameters are
// global, not per-mill), so it's called unconditionally at app boot rather
// than waiting for login like seedDefaultStationsIfNeeded().
const CREATE_GRADING_PARAMETER = `
  CREATE TABLE IF NOT EXISTS grading_parameter (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    uom TEXT NOT NULL,
    sort_order INTEGER NOT NULL,
    created_at TEXT,
    updated_at TEXT
  )
`

// Authoritative shape per entity-catalog `cages-track-record` (ver 3,
// 2026-08-19 revision): tippler_start_time (auto-filled once when draft
// created, mirrors weighbridge_record.arrival_datetime's pattern),
// tippler_stop_time (auto-filled once at Simpan, NOT live-ticking — unlike
// weighbridge_record.dispatch_datetime, just frozen at save time),
// cages_out (informational, unrelated to the tipped-time grid), cages_tipped
// (entered once in the header, drives the Cages Tipped Time grid's
// checklist column count 1..cages_tipped), note ADDED. Read/written by
// cagesTrackRecordRepo.ts.
const CREATE_CAGES_TRACK_RECORD = `
  CREATE TABLE IF NOT EXISTS cages_track_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    cages_track_number TEXT,
    date TEXT,
    tippler_start_time TEXT,
    tippler_stop_time TEXT,
    cages_out INTEGER,
    cages_tipped INTEGER,
    note TEXT,
    checked_by TEXT,
    acknowledged_by TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// Authoritative shape per entity-catalog `cages-tipped-time` (ver 3,
// 2026-08-19 revision): FULL RESTRUCTURE from the pre-v3 "one row per
// cage" model (cage_number/tipped_time, both REMOVED) to "one row per hour
// slot with a checklist of multiple cages tipped in that hour" —
// tipped_hour (INTEGER 0-23, whole hours only; must be unique per
// cages_track_record_id AND strictly greater than the most-recently-added
// row's tipped_hour — both rules enforced at the mobile client, not by a
// SQLite constraint here, consistent with this file's existing convention
// of not declaring FK/uniqueness enforcement at the DB level),
// checked_cage_numbers (TEXT, comma-separated cage numbers 1..header's
// cages_tipped that were checked in this row, e.g. "1,3,5,7"), total_cages
// (INTEGER, computed client-side = COUNT of checked_cage_numbers, persisted
// — same "computed then persisted" pattern as grading_detail.percentage),
// cages_remain (INTEGER, computed client-side = header.cages_tipped minus
// this row's total_cages, PER ROW not cumulative across rows).
const CREATE_CAGES_TIPPED_TIME = `
  CREATE TABLE IF NOT EXISTS cages_tipped_time (
    id TEXT PRIMARY KEY,
    cages_track_record_id TEXT NOT NULL,
    tipped_hour INTEGER,
    checked_cage_numbers TEXT,
    total_cages INTEGER,
    cages_remain INTEGER,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// Read-only reference cache table — see stationRepo.ts, which already
// assumes this exact column set (id, business_unit_id, name, type,
// is_active); populated by a separate sync flow, never written here.
const CREATE_STATION = `
  CREATE TABLE IF NOT EXISTS station (
    id TEXT PRIMARY KEY,
    business_unit_id TEXT NOT NULL,
    name TEXT NOT NULL,
    type TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 0,
    created_at TEXT,
    updated_at TEXT
  )
`

const CREATE_TABLE_STATEMENTS: string[] = [
  CREATE_WEIGHBRIDGE_RECORD,
  CREATE_GRADING_RECORD,
  CREATE_GRADING_DETAIL,
  CREATE_GRADING_PARAMETER,
  CREATE_CAGES_TRACK_RECORD,
  CREATE_CAGES_TIPPED_TIME,
  CREATE_STATION,
]

/**
 * Runs every `CREATE TABLE IF NOT EXISTS` statement above, in order,
 * against the local SQLite database via localDb.ts's `run()`. Idempotent
 * (safe to call multiple times / on every app start).
 *
 * Intended call site: once, at app bootstrap, in `main.ts` — e.g.
 *   `import { initLocalSchema } from '@/services/localSchema'`
 *   `await initLocalSchema()` before (or right after) `app.mount('#app')`.
 *
 * NOT wired into main.ts by this screen. Reasoning: `run()`/`query()`
 * open a real native SQLite connection (see localDb.ts's `getConnection()`
 * — no device/simulator or jsdom SQLite bridge exists in this sandbox), so
 * eagerly calling this at bootstrap would make `main.ts` — and anything
 * that imports it — attempt a real native connection during screen
 * 005/006 test runs and any future `main.ts`-level test, which currently
 * have no reason to touch SQLite at all and are not set up to mock it.
 * Rather than risk breaking those, this function is exported and
 * documented so the app-shell owner (or a follow-up screen) can wire it
 * into `main.ts` deliberately, with the appropriate test mocking in place.
 * This is called out explicitly in this screen's implementation_notes.
 */
export async function initLocalSchema(): Promise<void> {
  for (const statement of CREATE_TABLE_STATEMENTS) {
    await run(statement)
  }

  await migrateGradingTablesToV2()
  await migrateCagesTrackTablesToV3()
}

/**
 * Column-level migration helper — `CREATE TABLE IF NOT EXISTS` is a no-op
 * once a table already exists, so it never picks up columns added to this
 * file's DDL after a device/browser already created that table under an
 * older shape. Adds any column from `expectedColumns` that
 * `PRAGMA table_info()` shows is missing. Cheap no-op for tables that are
 * already up to date, including a table CREATE_TABLE_STATEMENTS just
 * created moments earlier in the same `initLocalSchema()` call (it already
 * has every column, so nothing to add).
 */
async function addMissingColumns(
  table: string,
  expectedColumns: Array<{ name: string; type: string }>,
): Promise<void> {
  const existing = await query<{ name: string }>(`PRAGMA table_info(${table})`)
  const existingNames = new Set(existing.map((column) => column.name))

  for (const column of expectedColumns) {
    if (existingNames.has(column.name)) {
      continue
    }

    await run(`ALTER TABLE ${table} ADD COLUMN ${column.name} ${column.type}`)
  }
}

/**
 * entity-catalog v2 (2026-08-18) added weighbridge_record_id/
 * license_plate_no/vehicle_code/netto/quantity/note to grading_record, and
 * grading_parameter_id/uom/percentage to grading_detail (see
 * CREATE_GRADING_RECORD/CREATE_GRADING_DETAIL's own comments for the full
 * rationale). Any device/browser whose grading_record or grading_detail
 * table was already created under the pre-v2 shape before this change
 * shipped is left with a table CREATE TABLE IF NOT EXISTS silently skips —
 * every v2 read/write against the missing columns then fails with "no such
 * column: <name>" (a real bug a user hit in production/dev after this
 * schema change, since their browser's persisted local database predated
 * it). The old pre-v2 columns (vehicle_number/driver_name/block on
 * grading_record, category on grading_detail) are deliberately left in
 * place rather than dropped — SQLite's DROP COLUMN support is
 * version-dependent and no code reads them anymore, so leaving them as
 * harmless unused columns is simpler and safer than a conditional DROP.
 */
async function migrateGradingTablesToV2(): Promise<void> {
  await addMissingColumns('grading_record', [
    { name: 'weighbridge_record_id', type: 'TEXT' },
    { name: 'license_plate_no', type: 'TEXT' },
    { name: 'vehicle_code', type: 'TEXT' },
    { name: 'netto', type: 'REAL' },
    { name: 'quantity', type: 'REAL' },
    { name: 'note', type: 'TEXT' },
  ])

  await addMissingColumns('grading_detail', [
    { name: 'grading_parameter_id', type: 'TEXT' },
    { name: 'uom', type: 'TEXT' },
    { name: 'percentage', type: 'REAL' },
  ])
}

/**
 * entity-catalog v3 (2026-08-19) added tippler_start_time/tippler_stop_time/
 * cages_out/cages_tipped/note to cages_track_record, and tipped_hour/
 * checked_cage_numbers/total_cages/cages_remain to cages_tipped_time (see
 * CREATE_CAGES_TRACK_RECORD/CREATE_CAGES_TIPPED_TIME's own comments for the
 * full rationale). Same "CREATE TABLE IF NOT EXISTS is a no-op on an
 * existing table" gap as migrateGradingTablesToV2() above — any
 * device/browser whose cages_track_record/cages_tipped_time table
 * pre-dates this change needs these columns added explicitly. The old
 * pre-v3 cages_tipped_time columns (cage_number, tipped_time) are left in
 * place, unused — same reasoning as the grading migration above.
 */
async function migrateCagesTrackTablesToV3(): Promise<void> {
  await addMissingColumns('cages_track_record', [
    { name: 'tippler_start_time', type: 'TEXT' },
    { name: 'tippler_stop_time', type: 'TEXT' },
    { name: 'cages_out', type: 'INTEGER' },
    { name: 'cages_tipped', type: 'INTEGER' },
    { name: 'note', type: 'TEXT' },
  ])

  await addMissingColumns('cages_tipped_time', [
    { name: 'tipped_hour', type: 'INTEGER' },
    { name: 'checked_cage_numbers', type: 'TEXT' },
    { name: 'total_cages', type: 'INTEGER' },
    { name: 'cages_remain', type: 'INTEGER' },
  ])
}

/**
 * The 15 MVP stations (business_rules: "Hanya 3 stasiun MVP yang aktif
 * secara fungsional; 12 lainnya adalah placeholder skema data untuk fase
 * mendatang") — fixed, known domain data per entity-catalog's `station`
 * entity (type enum: weighbridge/grading/cages-track/other). Names and
 * icon mapping (StationGrid.vue) match the Phase 2 reference mock
 * (.asdlc/generated/2-business-spec/screens/html/screen-006--station-list.html)
 * exactly. `idSuffix` combines with `business_unit_id` to build a stable,
 * deterministic local id — safe to re-run every login via `INSERT OR
 * IGNORE` without duplicating rows or needing a "have we seeded"
 * version flag. stationRepo.ts's query orders placeholders by `id ASC`
 * (tie-break after the 3 active types) — the 12 placeholder `idSuffix`
 * values are zero-padded (`01-`..`12-`) in this exact array's order so
 * that sort lands in the same order as the mock, not alphabetical.
 */
const DEFAULT_STATIONS: Array<{ idSuffix: string; name: string; type: string; isActive: boolean }> = [
  { idSuffix: 'weighbridge', name: 'Weighbridge', type: 'weighbridge', isActive: true },
  { idSuffix: 'grading', name: 'Grading', type: 'grading', isActive: true },
  { idSuffix: 'cages-track', name: 'Cages Track', type: 'cages-track', isActive: true },
  { idSuffix: '01-sterilizer', name: 'Sterilizer', type: 'other', isActive: false },
  { idSuffix: '02-thresher', name: 'Thresher', type: 'other', isActive: false },
  { idSuffix: '03-press', name: 'Press', type: 'other', isActive: false },
  { idSuffix: '04-clarification', name: 'Clarification', type: 'other', isActive: false },
  { idSuffix: '05-kernel-plant', name: 'Kernel Plant', type: 'other', isActive: false },
  { idSuffix: '06-boiler', name: 'Boiler', type: 'other', isActive: false },
  { idSuffix: '07-effluent-treatment', name: 'Effluent Treatment', type: 'other', isActive: false },
  { idSuffix: '08-loading-ramp', name: 'Loading Ramp', type: 'other', isActive: false },
  { idSuffix: '09-digester', name: 'Digester', type: 'other', isActive: false },
  { idSuffix: '10-engine-room', name: 'Engine Room', type: 'other', isActive: false },
  { idSuffix: '11-water-treatment', name: 'Water Treatment', type: 'other', isActive: false },
  { idSuffix: '12-bulking-storage', name: 'Bulking Storage', type: 'other', isActive: false },
]

/**
 * Seeds the 15 default MVP stations for `businessUnitId` if they are not
 * already present — `INSERT OR IGNORE` on a deterministic id makes this
 * idempotent, so callers don't need their own "already seeded" check.
 * Call once per successful login (see `stores/auth.ts`'s `login()`).
 */
export async function seedDefaultStationsIfNeeded(businessUnitId: string): Promise<void> {
  const now = new Date().toISOString()

  for (const station of DEFAULT_STATIONS) {
    await run(
      `INSERT OR IGNORE INTO station (id, business_unit_id, name, type, is_active, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [`default-${businessUnitId}-${station.idSuffix}`, businessUnitId, station.name, station.type, station.isActive ? 1 : 0, now, now],
    )
  }
}

/**
 * The 16 canonical Grading Parameter rows (entity-catalog `grading-parameter`,
 * 2026-08-18) — provided directly by the user, not invented. `sort_order`
 * matches this exact list order (1-16), used by the Quality Parameter
 * dropdown on screen-011--form-grading. `uom` values: "JJG"/bunch in the
 * source list map to `'bunch'` here (JJG = "janjang", Indonesian for a palm
 * fruit bunch); "KG" maps to `'kg'`.
 */
const DEFAULT_GRADING_PARAMETERS: Array<{ name: string; uom: 'kg' | 'bunch' }> = [
  { name: 'Mentah', uom: 'bunch' },
  { name: 'Mengkal / Kurang Masak', uom: 'bunch' },
  { name: 'Masak', uom: 'bunch' },
  { name: 'Over ripe R-1', uom: 'bunch' },
  { name: 'Over ripe R-2', uom: 'bunch' },
  { name: 'Over ripe R->2', uom: 'bunch' },
  { name: 'Janjang Kosong', uom: 'bunch' },
  { name: 'Parthenocarpic / Abnormal', uom: 'bunch' },
  { name: 'Buah Sakit', uom: 'bunch' },
  { name: 'Hard bunch', uom: 'bunch' },
  { name: 'Buah Masak Tangkai Panjang', uom: 'bunch' },
  { name: 'Buah kecil / < 3.5 kg', uom: 'bunch' },
  { name: 'Dimakan Hama / Tikus / Lainnya', uom: 'bunch' },
  { name: 'Brondolan Segar', uom: 'kg' },
  { name: 'Brondolan Busuk', uom: 'kg' },
  { name: 'Brondolan Sampah', uom: 'kg' },
]

/**
 * Seeds the 16 canonical Grading Parameter rows if not already present.
 * `INSERT OR IGNORE` keyed on a deterministic id (derived from array index,
 * stable across app runs since DEFAULT_GRADING_PARAMETERS's order is fixed)
 * makes this idempotent. Unlike `seedDefaultStationsIfNeeded()`, this has no
 * `businessUnitId` parameter — parameters are global master data, not
 * per-mill — so it's called unconditionally from `initLocalSchema()` below
 * rather than waiting for login.
 */
export async function seedGradingParametersIfNeeded(): Promise<void> {
  const now = new Date().toISOString()

  for (const [index, parameter] of DEFAULT_GRADING_PARAMETERS.entries()) {
    const sortOrder = index + 1

    await run(
      `INSERT OR IGNORE INTO grading_parameter (id, name, uom, sort_order, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?)`,
      [`default-grading-parameter-${sortOrder}`, parameter.name, parameter.uom, sortOrder, now, now],
    )
  }
}

export const localSchema = {
  initLocalSchema,
  seedDefaultStationsIfNeeded,
  seedGradingParametersIfNeeded,
}

export default localSchema
