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

// entity-catalog v5 (2026-08-19): arrival_datetime/dispatch_datetime merged
// into a single record_datetime column (arrival time for
// weighbridge_type='receive', dispatch time for weighbridge_type='dispatch');
// weighbridge_type and destination (dispatch-only, required in the app
// layer) added. See screen-010--form-weighbridge's tech spec v6.
const CREATE_WEIGHBRIDGE_RECORD = `
  CREATE TABLE IF NOT EXISTS weighbridge_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    wb_card_number TEXT,
    weighbridge_type TEXT,
    record_datetime TEXT,
    vehicle_number TEXT,
    driver_name TEXT,
    estate_supplier TEXT,
    destination TEXT,
    division TEXT,
    block TEXT,
    gross_weight REAL,
    tare_weight REAL,
    net_weight REAL,
    quantity REAL,
    checked_by TEXT,
    acknowledged_by TEXT,
    checked_by_name TEXT,
    acknowledged_by_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    server_id TEXT,
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
    checked_by_name TEXT,
    acknowledged_by_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    server_id TEXT,
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
    checked_by_name TEXT,
    acknowledged_by_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    server_id TEXT,
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
//
// entity-catalog v7 (2026-08-19, Mills Setting feature) added `icon`
// (optional Lucide icon-name override for the station-tile; falls back to
// the existing default icon per station type when null/unset) — see
// migrateStationTableToV7() below for the "table pre-dates this column"
// gap this repeats from every prior schema-change migration in this file.
// `icon` is populated by fetchAndCacheStationIconOverrides() (below),
// matched by (business_unit_id, type) rather than by id — see that
// function's doc comment for why (local rows use synthetic ids, not real
// server station UUIDs) and its known limitation (breaks if a mill ever
// has >1 active station of the same type).
const CREATE_STATION = `
  CREATE TABLE IF NOT EXISTS station (
    id TEXT PRIMARY KEY,
    business_unit_id TEXT NOT NULL,
    production_line_id TEXT,
    name TEXT NOT NULL,
    type TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 0,
    icon TEXT,
    machinery_count INTEGER,
    created_at TEXT,
    updated_at TEXT
  )
`

// New read-only reference cache table (entity-catalog v6/v7, Mills Setting
// feature) — server is the source of truth (edited via the web Mills
// Setting screen, screen-034); mobile only reads it. One row per business
// unit (business_unit_id UNIQUE, same 1:1 shape as the server `mill-setting`
// entity). Populated by fetchAndCacheMillSetting() (below), called from
// stores/auth.ts's login() alongside seedDefaultStationsIfNeeded() — unlike
// `station`'s local-only synthetic seed, this data is genuinely
// Admin/Mill-Management-authored and must come from the server, so it uses
// a real GET /api/mill-settings/current fetch (see fetchAndCacheMillSetting()
// doc comment for why a real fetch is used here and not another local seed).
const CREATE_MILL_SETTING = `
  CREATE TABLE IF NOT EXISTS mill_setting (
    id TEXT PRIMARY KEY,
    business_unit_id TEXT NOT NULL UNIQUE,
    app_name TEXT,
    logo TEXT,
    home_page_image TEXT,
    jumlah_cages INTEGER,
    immediate_sync_enabled INTEGER NOT NULL DEFAULT 0,
    created_at TEXT,
    updated_at TEXT
  )
`

// screen-037--monitor-threshing / screen-041--form-threshing (entity-catalog
// v11, 2026-08-23/24) — new station table. Unlike cages_track_record's
// per-station dynamic column count, threshing_record's child table
// (threshing_detail) is a FIXED set of 24 rows (one per hourly time-slot,
// 07:00 through 06:00 the next day) created all at once when the draft is
// first created (createDraft() in threshingRecordRepo.ts) — no
// tambah-baris/hapus-baris UI exists for this grid at all, unlike
// cages_tipped_time. `date` auto-fills once at draft creation (mirrors
// cages_track_record.tippler_start_time's pattern), not manually editable.
const CREATE_THRESHING_RECORD = `
  CREATE TABLE IF NOT EXISTS threshing_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    thresher_id TEXT,
    date TEXT,
    note TEXT,
    checked_by TEXT,
    acknowledged_by TEXT,
    checked_by_name TEXT,
    acknowledged_by_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    server_id TEXT,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-041--form-threshing (entity-catalog v11) — one row per hourly
// time-slot (24 rows/day, 07:00-06:00 next day) belonging to a
// threshing_record. `time_slot` stored as plain TEXT 'HH:00' (not a SQLite
// native TIME type — this app's SQLite layer has no such type; consistent
// with every other local table in this file using TEXT for time-like
// values). All 6 reading columns nullable — every column is optional per
// row, only the row's EXISTENCE is fixed (all 24 always exist from
// createDraft() onward), never its column values.
const CREATE_THRESHING_DETAIL = `
  CREATE TABLE IF NOT EXISTS threshing_detail (
    id TEXT PRIMARY KEY,
    threshing_record_id TEXT NOT NULL,
    time_slot TEXT NOT NULL,
    ffb_throughput_mt_hour REAL,
    thresher_drum_speed_rpm REAL,
    motor_current_amps REAL,
    unstripped_bunch_count_percent REAL,
    empty_bunch_oil_loss_percent REAL,
    downtime_reason TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-038--monitor-pressing / screen-042--form-pressing (entity-catalog
// v12, 2026-08-24 REVISED) — new station table, mirrors threshing_record/
// threshing_detail's shape exactly: pressing_detail is a DYNAMIC
// add-row/remove-row grid (one row per hourly time-slot, 07:00 through
// 06:00 the next day) — the user explicitly rejected the original FIXED
// 24-row design (all 24 rows pre-created at once by createDraft() in
// pressingRecordRepo.ts) as wasting screen space. Rows are now added one at
// a time via "Tambah baris" (FormPressingView.vue), same
// tambah-baris/hapus-baris pattern as cages_tipped_time. `date` auto-fills
// once at draft creation, not manually editable.
const CREATE_PRESSING_RECORD = `
  CREATE TABLE IF NOT EXISTS pressing_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    presser_id TEXT,
    date TEXT,
    note TEXT,
    checked_by TEXT,
    acknowledged_by TEXT,
    checked_by_name TEXT,
    acknowledged_by_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    server_id TEXT,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-042--form-pressing (entity-catalog v12, 2026-08-24 REVISED) — one
// row per hourly time-slot belonging to a pressing_record, added dynamically
// via "Tambah baris" (however many the user adds — no longer always 24).
// `time_slot` stored as plain TEXT 'HH:00', consistent with
// threshing_detail. All 6 reading columns nullable.
const CREATE_PRESSING_DETAIL = `
  CREATE TABLE IF NOT EXISTS pressing_detail (
    id TEXT PRIMARY KEY,
    pressing_record_id TEXT NOT NULL,
    time_slot TEXT NOT NULL,
    digester_temp_c REAL,
    digester_level_percent REAL,
    press_motor_current_amps REAL,
    cone_hydraulic_pressure_bar REAL,
    dilution_water_temp_c REAL,
    downtime_reason TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-039--monitor-depricarping / screen-043--form-depricarping
// (entity-catalog v11, 2026-08-24) — new station table, mirrors
// threshing_record/pressing_record's shape exactly: depricarping_detail is
// a FIXED set of 24 rows (one per hourly time-slot, 07:00 through 06:00 the
// next day) created all at once when the draft is first created
// (createDraft() in depricarpingRecordRepo.ts) — no tambah-baris/hapus-baris
// UI exists for this grid at all. `date` auto-fills once at draft creation,
// not manually editable. The header ID field is literally `presser_id`
// (labeled "Presser ID" on the form) per entity-catalog — NOT a typo, both
// Pressing's and Depricarping's header ID field share this name/label in
// the source log sheet.
const CREATE_DEPRICARPING_RECORD = `
  CREATE TABLE IF NOT EXISTS depricarping_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    presser_id TEXT,
    date TEXT,
    note TEXT,
    checked_by TEXT,
    acknowledged_by TEXT,
    checked_by_name TEXT,
    acknowledged_by_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    server_id TEXT,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-043--form-depricarping (entity-catalog v11) — one row per hourly
// time-slot (24 rows/day, 07:00-06:00 next day) belonging to a
// depricarping_record. `time_slot` stored as plain TEXT 'HH:00', consistent
// with threshing_detail/pressing_detail. Unlike Threshing/Pressing (a
// single free-text "Downtime Reason" column), Depricarping has TWO separate
// downtime-related columns per its own source log sheet: `downtime_minutes`
// (INTEGER, numeric duration) and `findings` (TEXT, free-text notes) — kept
// as two distinct columns end-to-end (repo/UI/backend), not merged. All 8
// reading columns nullable — only the row's EXISTENCE is fixed (all 24
// always exist from createDraft() onward), never its column values.
const CREATE_DEPRICARPING_DETAIL = `
  CREATE TABLE IF NOT EXISTS depricarping_detail (
    id TEXT PRIMARY KEY,
    depricarping_record_id TEXT NOT NULL,
    time_slot TEXT NOT NULL,
    fan_static_pressure_mmh2o REAL,
    polishing_drum_speed_rpm REAL,
    air_velocity_ms REAL,
    fibre_moisture_percent REAL,
    kernel_recovery_in_fibre_percent REAL,
    nut_silo_1_temp_c REAL,
    nut_silo_2_temp_c REAL,
    downtime_minutes INTEGER,
    findings TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-040--monitor-kernel-plant / screen-044--form-kernel-plant
// (entity-catalog v12, 2026-08-24 REVISED) — new station table, mirrors
// pressing_record/pressing_detail's shape exactly: kernel_plant_detail is a
// DYNAMIC add-row/remove-row grid (one row per hourly time-slot, 07:00
// through 06:00 the next day) — the user explicitly rejected the original
// FIXED 24-row design (all 24 rows pre-created at once by createDraft() in
// kernelPlantRecordRepo.ts) as wasting screen space. Rows are now added one
// at a time via "Tambah baris" (FormKernelPlantView.vue), same
// tambah-baris/hapus-baris pattern as cages_tipped_time/pressing_detail.
// `date` auto-fills once at draft creation, not manually editable. The
// header ID field is `kernel_plant_id` (labeled "Kernel Plant ID" on the
// form) per entity-catalog.
const CREATE_KERNEL_PLANT_RECORD = `
  CREATE TABLE IF NOT EXISTS kernel_plant_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    kernel_plant_id TEXT,
    date TEXT,
    note TEXT,
    checked_by TEXT,
    acknowledged_by TEXT,
    checked_by_name TEXT,
    acknowledged_by_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    server_id TEXT,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-044--form-kernel-plant (entity-catalog v12, 2026-08-24 REVISED) —
// one row per hourly time-slot belonging to a kernel_plant_record, added
// dynamically via "Tambah baris" (however many the user adds — no longer
// always 24). `time_slot` stored as plain TEXT 'HH:00', consistent with
// threshing_detail/pressing_detail/depricarping_detail. Like Depricarping
// (not Threshing/Pressing), Kernel Plant has TWO separate downtime-related
// columns per its own source log sheet: `downtime_minutes` (INTEGER,
// numeric duration) and `findings` (TEXT, free-text notes) — kept as two
// distinct columns end-to-end (repo/UI/backend), not merged. All 9 reading
// columns nullable.
const CREATE_KERNEL_PLANT_DETAIL = `
  CREATE TABLE IF NOT EXISTS kernel_plant_detail (
    id TEXT PRIMARY KEY,
    kernel_plant_record_id TEXT NOT NULL,
    time_slot TEXT NOT NULL,
    ripple_mill_1_amps REAL,
    ripple_mill_2_amps REAL,
    claybath_hydro_sg REAL,
    kernel_silo_1_temp_c REAL,
    kernel_silo_2_temp_c REAL,
    kernel_moisture_percent REAL,
    shell_loss_percent REAL,
    downtime_minutes INTEGER,
    findings TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-061--monitor-solid-waste-disposal / screen-071--form-solid-waste-disposal
// (entity-catalog v13, 2026-08-31) — new station table, mirrors
// cages_track_record's header shape but WITHOUT the tippler/grid-column
// concept — Solid Waste Disposal is a pure EVENT-LOG station (one row per
// disposal/shipment event, added manually via "Tambah baris", unbounded per
// day — like cages_tipped_time, unlike the fixed/dynamic hourly time-slot
// grids of Threshing/Pressing/Depricarping/Kernel Plant). `date` auto-fills
// once at draft creation, not manually editable. The header ID field is
// `solid_waste_disposal_id` (labeled "Solid Waste Disp. ID" on the form).
const CREATE_SOLID_WASTE_DISPOSAL_RECORD = `
  CREATE TABLE IF NOT EXISTS solid_waste_disposal_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    solid_waste_disposal_id TEXT,
    date TEXT,
    note TEXT,
    checked_by TEXT,
    acknowledged_by TEXT,
    checked_by_name TEXT,
    acknowledged_by_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    server_id TEXT,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-071--form-solid-waste-disposal (entity-catalog v13) — one row per
// disposal/shipment event belonging to a solid_waste_disposal_record, added
// manually via "Tambah baris" (unbounded — NOT a fixed/dynamic 24-slot
// grid). `net_weight_mt` is computed client-side (= gross_weight_mt -
// tare_weight_mt) and persisted as-is, same "computed then persisted"
// pattern as cages_tipped_time.total_cages.
const CREATE_SOLID_WASTE_DISPOSAL_DETAIL = `
  CREATE TABLE IF NOT EXISTS solid_waste_disposal_detail (
    id TEXT PRIMARY KEY,
    solid_waste_disposal_record_id TEXT NOT NULL,
    event_date TEXT,
    shift TEXT,
    weighbridge_ticket_no TEXT,
    vehicle_no TEXT,
    driver_name TEXT,
    solid_waste_type TEXT,
    source_station TEXT,
    gross_weight_mt REAL,
    tare_weight_mt REAL,
    net_weight_mt REAL,
    disposal_utilization_site TEXT,
    purpose_end_use TEXT,
    gate_pass_no TEXT,
    security_seal_no TEXT,
    operator_id TEXT,
    remarks TEXT,
    findings TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-062--monitor-process-water / screen-072--form-process-water
// (entity-catalog v13, 2026-08-31) — new station table, mirrors
// threshing_record/threshing_detail's shape exactly: process_water_detail
// is a DYNAMIC add-row/remove-row grid (one row per hourly time-slot,
// 07:00 through 06:00 the next day), following the same hourly-grid
// pattern as Threshing/Pressing/Kernel Plant. `date` auto-fills once at
// draft creation, not manually editable. UNLIKE Threshing/Pressing/
// Depricarping/Kernel Plant, Process Water has NO operational-target
// reference table.
const CREATE_PROCESS_WATER_RECORD = `
  CREATE TABLE IF NOT EXISTS process_water_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    process_water_id TEXT,
    date TEXT,
    note TEXT,
    checked_by TEXT,
    acknowledged_by TEXT,
    checked_by_name TEXT,
    acknowledged_by_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    server_id TEXT,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-072--form-process-water (entity-catalog v13) — one row per hourly
// time-slot belonging to a process_water_record, added dynamically via
// "Tambah baris" (however many the user adds — up to 24). `time_slot`
// stored as plain TEXT 'HH:00', consistent with threshing_detail. Unlike
// Threshing's single set of reading columns, Process Water also carries
// `shift`/`inspector_id` (identifying/context columns, not reading
// columns) per row.
const CREATE_PROCESS_WATER_DETAIL = `
  CREATE TABLE IF NOT EXISTS process_water_detail (
    id TEXT PRIMARY KEY,
    process_water_record_id TEXT NOT NULL,
    time_slot TEXT NOT NULL,
    shift TEXT,
    inspector_id TEXT,
    raw_water_flow_m3h REAL,
    clarified_water_flow_m3h REAL,
    softener_inlet_ph REAL,
    softener_outlet_hardness_ppm REAL,
    alum_dosing_kgh REAL,
    polymer_dosing_gh REAL,
    boiler_feed_tank_temp_c REAL,
    boiler_feed_water_ph REAL,
    boiler_feed_tds_ppm REAL,
    action_taken_status TEXT,
    findings TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-063--monitor-kernel-dispatch / screen-073--form-kernel-dispatch
// (entity-catalog v13) — event-log station mirroring
// solid_waste_disposal_record/solid_waste_disposal_detail's shape exactly
// (one row per dispatch event, added manually via "Tambah baris",
// unbounded per day — like cages_tipped_time/solid_waste_disposal_detail,
// unlike the fixed/dynamic hourly time-slot grids of Threshing/Pressing/
// Depricarping/Kernel Plant). `date` auto-fills once at draft creation,
// not manually editable. The header ID field is `kernel_dispatch_id`
// (labeled "Kernel Dispatch ID" on the form).
const CREATE_KERNEL_DISPATCH_RECORD = `
  CREATE TABLE IF NOT EXISTS kernel_dispatch_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    kernel_dispatch_id TEXT,
    date TEXT,
    note TEXT,
    checked_by TEXT,
    acknowledged_by TEXT,
    checked_by_name TEXT,
    acknowledged_by_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    server_id TEXT,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-073--form-kernel-dispatch (entity-catalog v13) — one row per
// dispatch event belonging to a kernel_dispatch_record, added manually via
// "Tambah baris" (unbounded — NOT a fixed/dynamic 24-slot grid).
// `net_weight_mt` is computed client-side (= gross_weight_mt -
// tare_weight_mt) and persisted as-is, same "computed then persisted"
// pattern as solid_waste_disposal_detail.net_weight_mt.
const CREATE_KERNEL_DISPATCH_DETAIL = `
  CREATE TABLE IF NOT EXISTS kernel_dispatch_detail (
    id TEXT PRIMARY KEY,
    kernel_dispatch_record_id TEXT NOT NULL,
    event_date TEXT,
    shift TEXT,
    weighbridge_ticket_no TEXT,
    waybill_number TEXT,
    transporter_contractor TEXT,
    vehicle_plate_no TEXT,
    driver_name TEXT,
    silo_source_id TEXT,
    destination_buyer TEXT,
    gross_weight_mt REAL,
    tare_weight_mt REAL,
    net_weight_mt REAL,
    kernel_moisture_percent REAL,
    dirt_impurities_percent REAL,
    ffa_percent REAL,
    broken_kernel_percent REAL,
    security_seal_no_top TEXT,
    security_seal_no_bottom TEXT,
    weighbridge_operator_id TEXT,
    remarks_gate_status TEXT,
    findings TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-064--monitor-cpo-dispatch / screen-074--form-cpo-dispatch
// (entity-catalog v13) — event-log station mirroring
// kernel_dispatch_record/kernel_dispatch_detail's shape exactly (one row
// per dispatch event, added manually via "Tambah baris", unbounded per
// day — like cages_tipped_time/kernel_dispatch_detail, unlike the
// fixed/dynamic hourly time-slot grids of Threshing/Pressing/
// Depricarping/Kernel Plant). `date` auto-fills once at draft creation,
// not manually editable. The header ID field is `cpo_dispatch_id`
// (labeled "CPO Dispatch ID" on the form). Unlike Kernel Dispatch, there
// is no remarks_gate_status/weighbridge_ticket_no column — CPO Dispatch
// instead has time_in/time_out (two separate time columns) and dobi.
const CREATE_CPO_DISPATCH_RECORD = `
  CREATE TABLE IF NOT EXISTS cpo_dispatch_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    cpo_dispatch_id TEXT,
    date TEXT,
    note TEXT,
    checked_by TEXT,
    acknowledged_by TEXT,
    checked_by_name TEXT,
    acknowledged_by_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    server_id TEXT,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-074--form-cpo-dispatch (entity-catalog v13) — one row per
// dispatch event belonging to a cpo_dispatch_record, added manually via
// "Tambah baris" (unbounded — NOT a fixed/dynamic 24-slot grid).
// `net_weight_mt` is computed client-side (= gross_weight_mt -
// tare_weight_mt) and persisted as-is, same "computed then persisted"
// pattern as kernel_dispatch_detail.net_weight_mt.
const CREATE_CPO_DISPATCH_DETAIL = `
  CREATE TABLE IF NOT EXISTS cpo_dispatch_detail (
    id TEXT PRIMARY KEY,
    cpo_dispatch_record_id TEXT NOT NULL,
    event_date TEXT,
    shift TEXT,
    time_in TEXT,
    time_out TEXT,
    waybill_number TEXT,
    tanker_plate_no TEXT,
    transport_company TEXT,
    driver_name TEXT,
    storage_tank_source TEXT,
    seal_no_top TEXT,
    seal_no_bottom TEXT,
    gross_weight_mt REAL,
    tare_weight_mt REAL,
    net_weight_mt REAL,
    ffa_percent REAL,
    moisture_percent REAL,
    impurities_percent REAL,
    dobi REAL,
    destination_buyer TEXT,
    weighbridge_operator TEXT,
    findings TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-065--monitor-effluent-plant / screen-075--form-effluent-plant
// (entity-catalog v13) — new station table, mirrors
// process_water_record/process_water_detail's shape exactly:
// effluent_plant_detail is a DYNAMIC add-row/remove-row grid (one row per
// hourly time-slot, 07:00 through 06:00 the next day), following the same
// hourly-grid pattern as Threshing/Pressing/Kernel Plant/Process Water.
// `date` auto-fills once at draft creation, not manually editable. UNLIKE
// Threshing/Pressing/Depricarping/Kernel Plant, Effluent Plant has NO
// operational-target reference table.
const CREATE_EFFLUENT_PLANT_RECORD = `
  CREATE TABLE IF NOT EXISTS effluent_plant_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    effluent_plant_id TEXT,
    date TEXT,
    note TEXT,
    checked_by TEXT,
    acknowledged_by TEXT,
    checked_by_name TEXT,
    acknowledged_by_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    server_id TEXT,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-075--form-effluent-plant (entity-catalog v13) — one row per hourly
// time-slot belonging to an effluent_plant_record, added dynamically via
// "Tambah baris" (however many the user adds — up to 24). `time_slot`
// stored as plain TEXT 'HH:00', consistent with process_water_detail.
// UNLIKE Process Water, this station has NO identifying/context columns
// (no shift/inspector_id equivalent) — all 19 non-time_slot columns are
// reading/status columns. 3 of them (biogas_flare_status,
// dosing_pump_1_status, sludge_dewatering_status) are enum-shaped but
// stored as plain TEXT here (no CHECK constraint in local SQLite — that
// only applies on the Laravel backend).
const CREATE_EFFLUENT_PLANT_DETAIL = `
  CREATE TABLE IF NOT EXISTS effluent_plant_detail (
    id TEXT PRIMARY KEY,
    effluent_plant_record_id TEXT NOT NULL,
    time_slot TEXT NOT NULL,
    anaerobic_pond_1_ph REAL,
    anaerobic_pond_1_temp_c REAL,
    anaerobic_pond_2_ph REAL,
    anaerobic_pond_2_temp_c REAL,
    cooling_pond_ph REAL,
    cooling_pond_temp_c REAL,
    biogas_flare_status TEXT,
    biogas_flow_rate_m3h REAL,
    raw_pome_feed_rate_m3h REAL,
    effluent_discharge_flow_rate_m3h REAL,
    final_discharge_ph REAL,
    final_discharge_bod_mgl_lab REAL,
    final_discharge_cod_mgl_lab REAL,
    final_discharge_tss_mgl_lab REAL,
    dosing_pump_1_status TEXT,
    chemical_consumed_kgl REAL,
    sludge_dewatering_status TEXT,
    remarks_maintenance_actions TEXT,
    findings TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-066--monitor-storage-tank / screen-076--form-storage-tank
// (entity-catalog v13) — new station table, mirrors
// effluent_plant_record/effluent_plant_detail's shape exactly:
// storage_tank_detail is a DYNAMIC add-row/remove-row grid (one row per
// hourly time-slot, 07:00 through 06:00 the next day), following the same
// hourly-grid pattern as Effluent Plant/Process Water/Threshing/Pressing/
// Kernel Plant. `date` auto-fills once at draft creation, not manually
// editable. UNLIKE Threshing/Pressing/Depricarping/Kernel Plant, Storage
// Tank has NO operational-target reference table.
const CREATE_STORAGE_TANK_RECORD = `
  CREATE TABLE IF NOT EXISTS storage_tank_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    storage_tank_id TEXT,
    date TEXT,
    note TEXT,
    checked_by TEXT,
    acknowledged_by TEXT,
    checked_by_name TEXT,
    acknowledged_by_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    server_id TEXT,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-076--form-storage-tank (entity-catalog v13) — one row per hourly
// time-slot belonging to a storage_tank_record, added dynamically via
// "Tambah baris" (however many the user adds — up to 24). `time_slot`
// stored as plain TEXT 'HH:00', consistent with effluent_plant_detail.
// UNLIKE Process Water, this station has NO identifying/context columns
// (no shift/inspector_id equivalent) — all 17 non-time_slot columns are
// reading/status/text columns. 1 of them (steam_heating_valve_status) is
// enum-shaped but stored as plain TEXT here (no CHECK constraint in local
// SQLite — that only applies on the Laravel backend).
const CREATE_STORAGE_TANK_DETAIL = `
  CREATE TABLE IF NOT EXISTS storage_tank_detail (
    id TEXT PRIMARY KEY,
    storage_tank_record_id TEXT NOT NULL,
    time_slot TEXT NOT NULL,
    cpo_sounding_depth_mm REAL,
    water_dip_bottom_depth_mm REAL,
    net_oil_depth_mm REAL,
    oil_temperature_top_c REAL,
    oil_temperature_middle_c REAL,
    oil_temperature_bottom_c REAL,
    average_temperature_c REAL,
    calculated_volume_m3 REAL,
    calculated_weight_mt REAL,
    ffa_percent REAL,
    moisture_content_percent REAL,
    impurities_dirt_percent REAL,
    dobi_index REAL,
    steam_heating_valve_status TEXT,
    tank_structural_condition TEXT,
    inspector_name TEXT,
    findings TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-077--form-engine-room (entity-catalog v13) — header row for a
// single Engine Room log-sheet, mirrors storage_tank_record's shape
// column-for-column (storage_tank_id -> engine_room_id).
const CREATE_ENGINE_ROOM_RECORD = `
  CREATE TABLE IF NOT EXISTS engine_room_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    engine_room_id TEXT,
    date TEXT,
    note TEXT,
    checked_by TEXT,
    acknowledged_by TEXT,
    checked_by_name TEXT,
    acknowledged_by_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    server_id TEXT,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-077--form-engine-room (entity-catalog v13) — one row per hourly
// time-slot belonging to an engine_room_record, added dynamically via
// "Tambah baris" (however many the user adds — up to 24). `time_slot`
// stored as plain TEXT 'HH:00', consistent with storage_tank_detail.
// UNLIKE Threshing/Pressing/Depricarping/Kernel Plant, this station has NO
// identifying/context columns (no shift/inspector_id equivalent) — all 27
// non-time_slot columns are reading/status/text columns. This is the
// LARGEST field count of any station in this project (task brief
// originally said 26; the migration on the backend — ground truth — has
// 27; corrected here as a derived assumption). 2 of them
// (diesel_gen_1_status, diesel_gen_2_status) are enum-shaped but stored as
// plain TEXT here (no CHECK constraint in local SQLite — that only applies
// on the Laravel backend).
const CREATE_ENGINE_ROOM_DETAIL = `
  CREATE TABLE IF NOT EXISTS engine_room_detail (
    id TEXT PRIMARY KEY,
    engine_room_record_id TEXT NOT NULL,
    time_slot TEXT NOT NULL,
    steam_turbine_inlet_pressure_bar REAL,
    steam_turbine_inlet_temp_c REAL,
    steam_turbine_exhaust_pressure_bar REAL,
    steam_turbine_rpm REAL,
    steam_turbine_alternator_bearing_temp_1_c REAL,
    steam_turbine_alternator_bearing_temp_2_c REAL,
    diesel_gen_1_status TEXT,
    diesel_gen_1_load_kw REAL,
    diesel_gen_1_amperage_a REAL,
    diesel_gen_1_jacket_water_temp_c REAL,
    diesel_gen_1_lube_oil_pressure_bar REAL,
    diesel_gen_2_status TEXT,
    diesel_gen_2_load_kw REAL,
    diesel_gen_2_amperage_a REAL,
    diesel_gen_2_jacket_water_temp_c REAL,
    diesel_gen_2_lube_oil_pressure_bar REAL,
    electrical_sync_total_factory_load_kw REAL,
    electrical_sync_system_frequency_hz REAL,
    electrical_sync_power_factor REAL,
    electrical_sync_busbar_voltage_v REAL,
    air_compressor_1_pressure_bar REAL,
    compressor_2_pressure_bar REAL,
    battery_charger_ups_voltage_v REAL,
    fuel_tank_level REAL,
    daily_energy_export_kwh REAL,
    action_taken_maintenance_remark TEXT,
    findings TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-078--form-boiler-room (entity-catalog v13) — header row for a
// single Boiler Room log-sheet, mirrors engine_room_record's shape
// column-for-column (engine_room_id -> boiler_room_id).
const CREATE_BOILER_ROOM_RECORD = `
  CREATE TABLE IF NOT EXISTS boiler_room_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    boiler_room_id TEXT,
    date TEXT,
    note TEXT,
    checked_by TEXT,
    acknowledged_by TEXT,
    checked_by_name TEXT,
    acknowledged_by_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    server_id TEXT,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-078--form-boiler-room (entity-catalog v13) — one row per hourly
// time-slot belonging to a boiler_room_record, added dynamically via
// "Tambah baris" (however many the user adds — up to 24). `time_slot`
// stored as plain TEXT 'HH:00', consistent with engine_room_detail.
// UNLIKE Threshing/Pressing/Depricarping/Kernel Plant, this station has NO
// identifying/context columns (no shift/inspector_id equivalent) — all 15
// non-time_slot columns are reading/status/text columns. 2 of them
// (blowdown_executed, sootblowing_executed) are enum-shaped (Y/N) but
// stored as plain TEXT here (no CHECK constraint in local SQLite — that
// only applies on the Laravel backend). fuel_feed_rate/id_fan_load/
// sa_fan_load are free-text TEXT columns (not numeric) because the
// paper-form units are mixed/ambiguous (Hz/%/tons, A/%).
const CREATE_BOILER_ROOM_DETAIL = `
  CREATE TABLE IF NOT EXISTS boiler_room_detail (
    id TEXT PRIMARY KEY,
    boiler_room_record_id TEXT NOT NULL,
    time_slot TEXT NOT NULL,
    steam_pressure_bar REAL,
    steam_temp_c REAL,
    feed_water_temp_c REAL,
    feed_water_tank_level_percent REAL,
    boiler_water_level_percent REAL,
    water_tds_ppm REAL,
    water_ph REAL,
    fuel_feed_rate TEXT,
    id_fan_load TEXT,
    sa_fan_load TEXT,
    exhaust_gas_temp_c REAL,
    dust_collector_differential_pressure_mmh2o REAL,
    blowdown_executed TEXT,
    sootblowing_executed TEXT,
    findings TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-079--form-clarification (entity-catalog v13) — header row for a
// single Clarification log-sheet, mirrors boiler_room_record's shape
// column-for-column (boiler_room_id -> clarification_id).
const CREATE_CLARIFICATION_RECORD = `
  CREATE TABLE IF NOT EXISTS clarification_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    clarification_id TEXT,
    date TEXT,
    note TEXT,
    checked_by TEXT,
    acknowledged_by TEXT,
    checked_by_name TEXT,
    acknowledged_by_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    server_id TEXT,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-079--form-clarification (entity-catalog v13) — one row per hourly
// time-slot belonging to a clarification_record, added dynamically via
// "Tambah baris" (however many the user adds — up to 24). `time_slot`
// stored as plain TEXT 'HH:00', consistent with boiler_room_detail. This
// is the SIMPLEST station in the whole project — NO identifying/context
// columns (no shift/inspector_id equivalent), NO enum columns, NO
// free-text-unit columns — all 6 non-findings columns are plain numeric
// readings; `findings` is plain text.
const CREATE_CLARIFICATION_DETAIL = `
  CREATE TABLE IF NOT EXISTS clarification_detail (
    id TEXT PRIMARY KEY,
    clarification_record_id TEXT NOT NULL,
    time_slot TEXT NOT NULL,
    clarification_tank_temp_c REAL,
    oil_tank_temperature_c REAL,
    sludge_tank_temp_c REAL,
    buffer_tank_level_percent REAL,
    pure_oil_production_rate_ton_hour REAL,
    downtime_mins REAL,
    findings TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-080--form-process-quality-control (entity-catalog v13) — header row
// for a single Process Quality Control log-sheet, mirrors
// clarification_record's shape column-for-column (clarification_id ->
// process_qc_id).
const CREATE_PROCESS_QUALITY_CONTROL_RECORD = `
  CREATE TABLE IF NOT EXISTS process_quality_control_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    process_qc_id TEXT,
    date TEXT,
    note TEXT,
    checked_by TEXT,
    acknowledged_by TEXT,
    checked_by_name TEXT,
    acknowledged_by_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    server_id TEXT,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-080--form-process-quality-control (entity-catalog v13) — one row
// per hourly time-slot belonging to a process_quality_control_record, added
// dynamically via "Tambah baris" (however many the user adds — up to 24).
// `time_slot` stored as plain TEXT 'HH:00', consistent with
// clarification_detail. THIS STATION HAS THE MOST NON-TIME_SLOT COLUMNS IN
// THE PROJECT (16): `shift` and `qc_inspector_id` are identifying/context
// columns (like Process Water's shift/inspector_id), NOT part of the
// "filled" check; the remaining 14 columns (12 numeric readings across
// Fruit Press/Purifier & Clarification Balance/Vacuum Drying
// Station/Decanter-Centrifuge/Final Storage, plus
// qc_engineering_corrective_actions and findings) participate. NO enum
// columns at all.
const CREATE_PROCESS_QUALITY_CONTROL_DETAIL = `
  CREATE TABLE IF NOT EXISTS process_quality_control_detail (
    id TEXT PRIMARY KEY,
    process_quality_control_record_id TEXT NOT NULL,
    time_slot TEXT NOT NULL,
    shift TEXT,
    fruit_press_oil_loss_in_sludge_percent REAL,
    fruit_press_oil_loss_in_fibre_percent REAL,
    purifier_clarification_balance_inlet_temp_c REAL,
    purifier_clarification_balance_backpressure_bar REAL,
    vacuum_drying_station_drier_temp_c REAL,
    vacuum_drying_station_vacuum_pressure_bar REAL,
    decanter_centrifuge_feed_rate_mth REAL,
    decanter_centrifuge_oil_loss_in_cake_percent REAL,
    final_storage_ffa_percent REAL,
    final_storage_moisture_content_percent REAL,
    final_storage_impurities_dirt_percent REAL,
    final_storage_dobi_index REAL,
    qc_inspector_id TEXT,
    qc_engineering_corrective_actions TEXT,
    findings TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-121--monitor-sterilizer / screen-122--form-sterilizer
// (entity-catalog v15) — event-log station mirroring
// cpo_dispatch_record/cpo_dispatch_detail's shape exactly (one row per
// sterilization cycle, added manually via "Tambah baris", unbounded per
// day — like cages_tipped_time/cpo_dispatch_detail, unlike the
// fixed/dynamic hourly time-slot grids of Threshing/Pressing/
// Depricarping/Kernel Plant). `date` auto-fills once at draft creation,
// not manually editable. The header ID field is `sterilizer_id` (labeled
// "Sterilizer ID" on the form). This is the FINAL station of this project
// — the 18th and last of the 18 canonical stations, promoted 2026-09-01.
const CREATE_STERILIZER_RECORD = `
  CREATE TABLE IF NOT EXISTS sterilizer_record (
    id TEXT PRIMARY KEY,
    station_id TEXT,
    sterilizer_id TEXT,
    date TEXT,
    note TEXT,
    checked_by TEXT,
    acknowledged_by TEXT,
    checked_by_name TEXT,
    acknowledged_by_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft_ongoing',
    server_id TEXT,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )
`

// screen-122--form-sterilizer (entity-catalog v15) — one row per
// sterilization cycle belonging to a sterilizer_record, added manually via
// "Tambah baris" (unbounded — NOT a fixed/dynamic 24-slot grid).
// `duration_minutes` is computed client-side (= open_door_time -
// close_door_time) and persisted as-is for immediate UX feedback, same
// "computed then persisted" pattern as cpo_dispatch_detail.net_weight_mt
// — but the SERVER (SterilizerRecordService) always recomputes this value
// on save, never trusting the client-supplied one. `checked_by_spv` is
// stored as INTEGER (0/1) — SQLite has no native boolean type.
const CREATE_STERILIZER_DETAIL = `
  CREATE TABLE IF NOT EXISTS sterilizer_detail (
    id TEXT PRIMARY KEY,
    sterilizer_record_id TEXT NOT NULL,
    sterilizer_no TEXT,
    close_door_time TEXT,
    peak_1_time TEXT,
    exhaust_1_time TEXT,
    peak_2_time TEXT,
    exhaust_2_time TEXT,
    peak_3_time TEXT,
    exhaust_3_time TEXT,
    open_door_time TEXT,
    duration_minutes INTEGER,
    number_of_cages INTEGER,
    cages_status TEXT,
    checked_by_spv INTEGER NOT NULL DEFAULT 0,
    remarks TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
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
  CREATE_MILL_SETTING,
  CREATE_THRESHING_RECORD,
  CREATE_THRESHING_DETAIL,
  CREATE_PRESSING_RECORD,
  CREATE_PRESSING_DETAIL,
  CREATE_DEPRICARPING_RECORD,
  CREATE_DEPRICARPING_DETAIL,
  CREATE_KERNEL_PLANT_RECORD,
  CREATE_KERNEL_PLANT_DETAIL,
  CREATE_SOLID_WASTE_DISPOSAL_RECORD,
  CREATE_SOLID_WASTE_DISPOSAL_DETAIL,
  CREATE_PROCESS_WATER_RECORD,
  CREATE_PROCESS_WATER_DETAIL,
  CREATE_KERNEL_DISPATCH_RECORD,
  CREATE_KERNEL_DISPATCH_DETAIL,
  CREATE_CPO_DISPATCH_RECORD,
  CREATE_CPO_DISPATCH_DETAIL,
  CREATE_EFFLUENT_PLANT_RECORD,
  CREATE_EFFLUENT_PLANT_DETAIL,
  CREATE_STORAGE_TANK_RECORD,
  CREATE_STORAGE_TANK_DETAIL,
  CREATE_ENGINE_ROOM_RECORD,
  CREATE_ENGINE_ROOM_DETAIL,
  CREATE_BOILER_ROOM_RECORD,
  CREATE_BOILER_ROOM_DETAIL,
  CREATE_CLARIFICATION_RECORD,
  CREATE_CLARIFICATION_DETAIL,
  CREATE_PROCESS_QUALITY_CONTROL_RECORD,
  CREATE_PROCESS_QUALITY_CONTROL_DETAIL,
  CREATE_STERILIZER_RECORD,
  CREATE_STERILIZER_DETAIL,
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

  await migrateWeighbridgeTableToV5()
  await migrateGradingTablesToV2()
  await migrateCagesTrackTablesToV3()
  await migrateStationTableToV7()
  await migrateStationTableToV9()
  await migrateStationTableForMachineryCount()
  await migrateRecordTablesForSync()
  await migrateRecordTablesForVerifierNames()
  await migrateMillSettingForImmediateSync()
  await dedupeStationRows()
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
 * entity-catalog v5 (2026-08-19) merged weighbridge_record's
 * arrival_datetime/dispatch_datetime into a single record_datetime column,
 * and added weighbridge_type/destination (see CREATE_WEIGHBRIDGE_RECORD's
 * own comment for the full rationale). Same "CREATE TABLE IF NOT EXISTS is
 * a no-op on an existing table" gap as migrateGradingTablesToV2()/
 * migrateCagesTrackTablesToV3() below — any device/browser whose
 * weighbridge_record table pre-dates this change is left without
 * weighbridge_type/record_datetime/destination, so every v5 read/write
 * (including gradingRecordRepo.ts's WB Card No dropdown, which selects
 * record_datetime) fails with "no such column: record_datetime" — the
 * production bug this migration fixes. The old pre-v5 columns
 * (arrival_datetime, dispatch_datetime) are deliberately left in place
 * rather than dropped — same reasoning as the other migrations here.
 */
async function migrateWeighbridgeTableToV5(): Promise<void> {
  await addMissingColumns('weighbridge_record', [
    { name: 'weighbridge_type', type: 'TEXT' },
    { name: 'record_datetime', type: 'TEXT' },
    { name: 'destination', type: 'TEXT' },
  ])
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
 * entity-catalog v7 (2026-08-19, Mills Setting feature) added `icon` to
 * `station` (see CREATE_STATION's own comment). Same "CREATE TABLE IF NOT
 * EXISTS is a no-op on an existing table" gap as every other migration in
 * this file — any device/browser whose `station` table was seeded before
 * this change is left without the `icon` column, so any v7 read against it
 * fails with "no such column: icon".
 */
async function migrateStationTableToV7(): Promise<void> {
  await addMissingColumns('station', [{ name: 'icon', type: 'TEXT' }])
}

/**
 * entity-catalog v9 (2026-08-20, Production Line feature) — a new
 * hierarchy level (Business Unit → Production Line → Station) was inserted
 * above Station; `station` gained `production_line_id`. Same "CREATE TABLE
 * IF NOT EXISTS is a no-op on an existing table" gap as every other
 * migration in this file. NULL for rows seeded by
 * `seedDefaultStationsIfNeeded()` (that seed predates Production Line and
 * has no real production line to attach to — see that function's own
 * comment); populated for rows written by
 * `productionLineRepo.fetchAndCacheStationsForProductionLine()`, which uses
 * real backend station ids/production_line_ids (see that function's own
 * comment for why this finally fixes the long-standing "local rows use
 * synthetic ids, not real server Station UUIDs" limitation documented on
 * `fetchAndCacheStationIconOverrides()` below).
 */
async function migrateStationTableToV9(): Promise<void> {
  await addMissingColumns('station', [{ name: 'production_line_id', type: 'TEXT' }])
}

/**
 * Cages Track grid fix (2026-08-20) — `station` gained `machinery_count`.
 * The backend already returns this per-station (Cages Track type) via
 * GET /api/production-lines/current/stations, and `productionLineRepo.ts`
 * already typed it in `CurrentStationRow` — but never persisted it locally,
 * so `FormCagesTrackView.vue` was left reading the now-removed
 * `mill_setting.jumlah_cages` (dropped from the backend when Mills Setting's
 * jumlah_cages field was removed in favor of this exact machinery-count
 * approach), which always resolves to null and disables "Tambah baris"
 * entirely. Same "CREATE TABLE IF NOT EXISTS is a no-op on an existing
 * table" gap as every other migration in this file.
 */
async function migrateStationTableForMachineryCount(): Promise<void> {
  await addMissingColumns('station', [{ name: 'machinery_count', type: 'INTEGER' }])
}

/**
 * Temporary sync feature (2026-08-20) — adds `server_id` to
 * weighbridge_record/grading_record/cages_track_record. Populated by
 * syncService.ts after a record is successfully POSTed to the backend
 * (backend always assigns its own UUID via HasUuids, never the local id —
 * see syncService.ts's own doc comment), so grading_record's sync can look
 * up its parent weighbridge_record's server-assigned id (required by
 * POST /api/grading-records' weighbridge_record_id, which validates
 * `exists:weighbridge_records,id`). NULL until synced. Same
 * "CREATE TABLE IF NOT EXISTS is a no-op on an existing table" gap as
 * every prior migration in this file — required for any device/browser
 * whose these 3 tables pre-date this change.
 */
/**
 * Verification display names (2026-09-14) — Data Preview used to render the
 * raw `checked_by` / `acknowledged_by` UUID straight into a "Checked By"
 * field, which is meaningless to a user. There is no local `user` table to
 * resolve an id against offline, so the NAME is stored alongside the id
 * whenever this device learns it (the verification API returns it; a user
 * verifying themselves is resolved from the auth store instead).
 *
 * A record verified by someone else on the web still arrives here with no
 * name — nothing pulls server-side changes down — so the UI falls back to
 * "sudah diverifikasi" without a name rather than inventing one.
 *
 * Same "CREATE TABLE IF NOT EXISTS is a no-op on an existing table" gap
 * this repeats from every prior migration in this file.
 */
const VERIFIER_NAME_COLUMNS = [
  { name: 'checked_by_name', type: 'TEXT' },
  { name: 'acknowledged_by_name', type: 'TEXT' },
]

/**
 * Write-through saving flag (2026-09-14), cached from
 * GET /api/mill-settings/current so a save can decide whether to push
 * immediately without a network round-trip first. 0 = wait for the manual
 * Sinkronisasi button, which is the pre-existing behaviour and the default
 * for every mill that has not opted in.
 */
async function migrateMillSettingForImmediateSync(): Promise<void> {
  await addMissingColumns('mill_setting', [
    { name: 'immediate_sync_enabled', type: 'INTEGER NOT NULL DEFAULT 0' },
  ])
}

async function migrateRecordTablesForVerifierNames(): Promise<void> {
  await addMissingColumns('weighbridge_record', VERIFIER_NAME_COLUMNS)
  await addMissingColumns('grading_record', VERIFIER_NAME_COLUMNS)
  await addMissingColumns('cages_track_record', VERIFIER_NAME_COLUMNS)
  await addMissingColumns('threshing_record', VERIFIER_NAME_COLUMNS)
  await addMissingColumns('pressing_record', VERIFIER_NAME_COLUMNS)
  await addMissingColumns('depricarping_record', VERIFIER_NAME_COLUMNS)
  await addMissingColumns('kernel_plant_record', VERIFIER_NAME_COLUMNS)
  await addMissingColumns('solid_waste_disposal_record', VERIFIER_NAME_COLUMNS)
  await addMissingColumns('process_water_record', VERIFIER_NAME_COLUMNS)
  await addMissingColumns('kernel_dispatch_record', VERIFIER_NAME_COLUMNS)
  await addMissingColumns('cpo_dispatch_record', VERIFIER_NAME_COLUMNS)
  await addMissingColumns('effluent_plant_record', VERIFIER_NAME_COLUMNS)
  await addMissingColumns('storage_tank_record', VERIFIER_NAME_COLUMNS)
  await addMissingColumns('engine_room_record', VERIFIER_NAME_COLUMNS)
  await addMissingColumns('boiler_room_record', VERIFIER_NAME_COLUMNS)
  await addMissingColumns('clarification_record', VERIFIER_NAME_COLUMNS)
  await addMissingColumns('process_quality_control_record', VERIFIER_NAME_COLUMNS)
  await addMissingColumns('sterilizer_record', VERIFIER_NAME_COLUMNS)
}

async function migrateRecordTablesForSync(): Promise<void> {
  await addMissingColumns('weighbridge_record', [{ name: 'server_id', type: 'TEXT' }])
  await addMissingColumns('grading_record', [{ name: 'server_id', type: 'TEXT' }])
  await addMissingColumns('cages_track_record', [{ name: 'server_id', type: 'TEXT' }])
}

/**
 * The 18 canonical stations — ALL 18 are now MVP-functional and active
 * (weighbridge/grading/cages-track/sterilizer/threshing/pressing/
 * depricarping/kernel-plant/clarification/boiler-room/effluent-plant/
 * engine-room/process-water/storage-tank/solid-waste-disposal/
 * kernel-dispatch/cpo-dispatch/process-quality-control, `isActive: true`).
 * 0 placeholders remain as of 2026-09-01 (Sterilizer was the last one
 * promoted — see this array's own 2026-09-01 doc comment below) — fixed,
 * known domain data per entity-catalog's `station` entity, identical
 * (active/placeholder types) to the backend's
 * `ProductionLineService::DEFAULT_STATIONS`
 * (backend/app/Services/ProductionLineService.php), which this array must
 * be kept in sync with. Names and icon mapping (StationGrid.vue's
 * `ACTIVE_ICONS`) match the Phase 2 reference mock
 * (.asdlc/generated/2-business-spec/screens/html/screen-006--station-list.html)
 * exactly. `idSuffix` combines with `business_unit_id` to build a stable,
 * deterministic local id — safe to re-run every login via `INSERT OR
 * IGNORE` without duplicating rows or needing a "have we seeded"
 * version flag. stationRepo.ts's query orders placeholders by `id ASC`
 * (tie-break after the active types) — the 1 remaining placeholder
 * `idSuffix` is zero-padded (`01-`) for consistency with its prior
 * numbering.
 *
 * 2026-08-23 — 4 of the former 12 `other` placeholders promoted to active
 * MVP stations: 'Thresher' → 'Threshing' (type threshing), 'Press' →
 * 'Pressing' (type pressing), 'Digester' → 'Depricarping' (type
 * depricarping), 'Kernel Plant' stays same name (type kernel-plant) —
 * leaving 8 `other` placeholders (`01-`..`12-`, with gaps where the 4
 * promoted suffixes used to sit).
 *
 * 2026-08-31 — 6 of the remaining 8 `other` placeholders promoted to
 * active stations: 'Clarification' stays same name (type clarification),
 * 'Boiler' → 'Boiler Room' (type boiler-room), 'Effluent Treatment' →
 * 'Effluent Plant' (type effluent-plant), 'Engine Room' stays same name
 * (type engine-room), 'Water Treatment' → 'Process Water' (type
 * process-water), 'Bulking Storage' → 'Storage Tank' (type storage-tank)
 * — leaving only 'Sterilizer' and 'Loading Ramp' as `other` placeholders,
 * renumbered `01-`/`02-` since there are only 2 left. 4 brand-new active
 * stations also appended: 'Solid Waste Disposal' (type
 * solid-waste-disposal), 'Kernel Dispatch' (type kernel-dispatch), 'CPO
 * Dispatch' (type cpo-dispatch), 'Process Quality Control' (type
 * process-quality-control) — mirrors
 * ProductionLineService::DEFAULT_STATIONS verbatim (see that file's own
 * 2026-08-31 doc comment).
 *
 * 2026-09-01 — 'Loading Ramp' removed entirely: it turned out to be a
 * duplicate name for the already-active Cages Track station, not a
 * distinct station. Only 'Sterilizer' remained as an `other` placeholder
 * at that point.
 *
 * 2026-09-01 (final promotion) — 'Sterilizer' promoted from `other`
 * placeholder to a fully active station (type `sterilizer`, idSuffix
 * simplified from '01-sterilizer' to 'sterilizer' now that it sits
 * alongside the other 17 active entries, not in a separate numbered
 * placeholder list). This was the LAST remaining placeholder — 0
 * placeholders remain anywhere in this project after this.
 */
const DEFAULT_STATIONS: Array<{ idSuffix: string; name: string; type: string; isActive: boolean }> = [
  { idSuffix: 'weighbridge', name: 'Weighbridge', type: 'weighbridge', isActive: true },
  { idSuffix: 'grading', name: 'Grading', type: 'grading', isActive: true },
  { idSuffix: 'cages-track', name: 'Cages Track', type: 'cages-track', isActive: true },
  { idSuffix: 'sterilizer', name: 'Sterilizer', type: 'sterilizer', isActive: true },
  { idSuffix: 'threshing', name: 'Threshing', type: 'threshing', isActive: true },
  { idSuffix: 'pressing', name: 'Pressing', type: 'pressing', isActive: true },
  { idSuffix: 'depricarping', name: 'Depricarping', type: 'depricarping', isActive: true },
  { idSuffix: 'kernel-plant', name: 'Kernel Plant', type: 'kernel-plant', isActive: true },
  { idSuffix: 'clarification', name: 'Clarification', type: 'clarification', isActive: true },
  { idSuffix: 'boiler-room', name: 'Boiler Room', type: 'boiler-room', isActive: true },
  { idSuffix: 'effluent-plant', name: 'Effluent Plant', type: 'effluent-plant', isActive: true },
  { idSuffix: 'engine-room', name: 'Engine Room', type: 'engine-room', isActive: true },
  { idSuffix: 'process-water', name: 'Process Water', type: 'process-water', isActive: true },
  { idSuffix: 'storage-tank', name: 'Storage Tank', type: 'storage-tank', isActive: true },
  { idSuffix: 'solid-waste-disposal', name: 'Solid Waste Disposal', type: 'solid-waste-disposal', isActive: true },
  { idSuffix: 'kernel-dispatch', name: 'Kernel Dispatch', type: 'kernel-dispatch', isActive: true },
  { idSuffix: 'cpo-dispatch', name: 'CPO Dispatch', type: 'cpo-dispatch', isActive: true },
  { idSuffix: 'process-quality-control', name: 'Process Quality Control', type: 'process-quality-control', isActive: true },
]

/**
 * One-time cleanup migration (2026-08-20) for devices that already
 * accumulated duplicate `station` rows before the login-time seed call was
 * removed and the two seeding paths (seedDefaultStationsIfNeeded() /
 * fetchAndCacheStationsForProductionLine()) were made delete-first — this
 * is the concrete "bersihkan semua data" fix for existing installs, since
 * a device already affected needs its bad local data cleared, not just a
 * guarantee that no *new* duplicates will form.
 *
 * Deliberately narrow: only deletes legacy SYNTHETIC rows
 * (`production_line_id IS NULL`, from seedDefaultStationsIfNeeded()) that
 * coexist with at least one REAL row (`production_line_id IS NOT NULL`,
 * from a Production Line sync) for the same (business_unit_id, type) — the
 * exact combination that renders as doubled tiles. Does NOT collapse
 * multiple REAL rows sharing a (business_unit_id, type) — a business unit
 * with 2+ Production Lines legitimately has multiple real stations of the
 * same type (one per line), and each is already correctly scoped by
 * `production_line_id` wherever it's queried (see
 * getActiveAndPlaceholderStationsForProductionLine()) — those must not be
 * treated as duplicates or deleted.
 */
async function dedupeStationRows(): Promise<void> {
  await run(`
    DELETE FROM station
    WHERE production_line_id IS NULL
      AND EXISTS (
        SELECT 1 FROM station s2
        WHERE s2.business_unit_id = station.business_unit_id
          AND s2.type = station.type
          AND s2.production_line_id IS NOT NULL
      )
  `)
}

/**
 * Seeds the 18 default MVP stations for `businessUnitId` — replace, not
 * merge: deletes every existing `station` row for this business unit
 * FIRST, then inserts the fixed synthetic set fresh. This is now a
 * fallback-only path (Production Line fetch unreachable — see
 * StationListView.vue's `loadProductionLinesAndStations()`, the only
 * caller), so it must never leave stale rows behind from a previous
 * real/production-line-scoped sync mixed in with the synthetic set —
 * that combination was what caused stations to render doubled
 * (2026-08-20, found via user report). Previously `INSERT OR IGNORE`
 * (merge-safe but not double-safe); the delete-first replace makes this
 * function's output deterministic regardless of what was cached before.
 */
export async function seedDefaultStationsIfNeeded(businessUnitId: string): Promise<void> {
  const now = new Date().toISOString()

  await run('DELETE FROM station WHERE business_unit_id = ?', [businessUnitId])

  for (const station of DEFAULT_STATIONS) {
    await run(
      `INSERT INTO station (id, business_unit_id, name, type, is_active, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [`default-${businessUnitId}-${station.idSuffix}`, businessUnitId, station.name, station.type, station.isActive ? 1 : 0, now, now],
    )
  }
}

/**
 * Fetches the current mill-setting (app_name/logo/home_page_image/
 * jumlah_cages) from `GET /api/mill-settings/current` and upserts it into
 * the local `mill_setting` table, keyed by `business_unit_id`.
 *
 * Unlike `seedDefaultStationsIfNeeded()`'s fixed local seed, this data is
 * genuinely Admin/Mill-Management-authored (edited via the web Mills
 * Setting screen, screen-034) — it cannot be hardcoded client-side, so this
 * is a real network fetch, not another local seed. Called from
 * `stores/auth.ts`'s `login()` right after `seedDefaultStationsIfNeeded()`,
 * with the same best-effort semantics: a failed/offline fetch must not
 * block a successful login (the caller wraps this in try/catch, same as
 * the station seed) — screens needing mill_setting data (Home, Station
 * List, Form Cages Track) each have their own local-cache-first / fallback
 * handling for the case where no row exists yet (see their tech specs'
 * edge_case_handling).
 *
 * Deliberately NOT registering the `apiClient` import at module load time
 * beyond what's already imported here — this file otherwise only touches
 * localDb, so the import is scoped to this one function's use.
 */
export async function fetchAndCacheMillSetting(businessUnitId: string): Promise<void> {
  const { default: apiClient } = await import('@/services/apiClient')

  const response = await apiClient.get('/api/mill-settings/current')
  const data = response.data as {
    business_unit_id?: string
    app_name?: string | null
    logo?: string | null
    home_page_image?: string | null
    jumlah_cages?: number | null
    immediate_sync_enabled?: boolean
  }

  const now = new Date().toISOString()

  await run(
    `INSERT INTO mill_setting (id, business_unit_id, app_name, logo, home_page_image, jumlah_cages, immediate_sync_enabled, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
     ON CONFLICT(business_unit_id) DO UPDATE SET
       app_name = excluded.app_name,
       logo = excluded.logo,
       home_page_image = excluded.home_page_image,
       jumlah_cages = excluded.jumlah_cages,
       immediate_sync_enabled = excluded.immediate_sync_enabled,
       updated_at = excluded.updated_at`,
    [
      `mill-setting-${businessUnitId}`,
      data.business_unit_id ?? businessUnitId,
      data.app_name ?? null,
      data.logo ?? null,
      data.home_page_image ?? null,
      data.jumlah_cages ?? null,
      data.immediate_sync_enabled ? 1 : 0,
      now,
      now,
    ],
  )

  await fetchAndCacheStationIconOverrides(businessUnitId)
}

/**
 * Known limitation (2026-08-19, Mills Setting station-icon sync — explicit
 * product decision): local `station` rows use synthetic deterministic ids
 * (see `seedDefaultStationsIfNeeded()`'s `DEFAULT_STATIONS`, e.g.
 * `default-${businessUnitId}-weighbridge`) that are NOT the real server
 * `station` UUIDs Admin/Mill Management manage via Kelola Station / Mills
 * Setting — there is no reliable id to join a server station row to its
 * local counterpart (station.icon is server-authored, per-station data;
 * see CREATE_STATION's own comment). Pragmatic fix: match by
 * `(business_unit_id, type)` instead of by id, since every mill currently
 * has AT MOST ONE active station per type (weighbridge/grading/cages-track)
 * in this MVP — `DEFAULT_STATIONS` seeds exactly one row per type per
 * business unit, so this is unambiguous today.
 *
 * WILL BREAK if a mill is ever given more than one active station of the
 * same type: this update statement applies the server's icon to EVERY
 * local row sharing that `(business_unit_id, type)` pair, which would
 * silently apply the same icon to multiple distinct stations instead of
 * the one it was actually set for. A real id-based sync (requiring local
 * `station` rows to carry the real server id, not a synthetic one) is the
 * correct long-term fix — deferred here since it needs a broader decision
 * about how `station` sync works on mobile (see CREATE_STATION's and
 * `seedDefaultStationsIfNeeded()`'s own comments on that pre-existing gap).
 * screen-006--station-list's own implementation should carry the same
 * caveat in its implementation_notes.
 */
async function fetchAndCacheStationIconOverrides(businessUnitId: string): Promise<void> {
  const { default: apiClient } = await import('@/services/apiClient')

  const response = await apiClient.get('/api/mill-settings/current/stations')
  const stations = (response.data?.data ?? []) as Array<{ id: string; name: string; type: string; icon: string | null }>

  for (const station of stations) {
    await run('UPDATE station SET icon = ? WHERE business_unit_id = ? AND type = ?', [
      station.icon,
      businessUnitId,
      station.type,
    ])
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
  fetchAndCacheMillSetting,
  seedGradingParametersIfNeeded,
}

export default localSchema
