import { query, run } from '@/services/localDb'

/**
 * engineRoomRecordRepo — screen-067--monitor-engine-room /
 * usecase-097--monitor-engine-room, screen-077--form-engine-room /
 * usecase-098--form-engine-room, screen-087--data-preview-engine-room /
 * usecase-099--data-preview-engine-room. Local (offline) read/write access
 * to the `engine_room_record` / `engine_room_detail` tables (schema
 * defined in localSchema.ts) — mirrors storageTankRecordRepo.ts's repo
 * style (plain async functions over localDb.ts's `query`/`run` primitives)
 * exactly, since Engine Room follows the same hourly-grid (dynamic
 * add-row/remove-row, 1..24 rows) pattern as Storage Tank/Effluent Plant.
 *
 * `createDraft()` inserts ONLY the header row — no detail rows at all. Rows
 * are added one at a time via `applyDetailRowChanges()` (called from
 * `saveDraft()`/`pauseDraftWithFormData()`, mirrors
 * storageTankRecordRepo.ts's `applyDetailRowChanges()`): rows with an
 * existing `id` are UPDATEd, rows without one are INSERTed with a freshly
 * generated id via `generateDetailId()`, and every id in the
 * caller-supplied `idsToDelete` list is DELETEd after the upserts.
 * `canonicalTimeSlots()` is the source of truth for the dropdown's ordered
 * option list (FormEngineRoomView.vue's `availableTimeSlotOptions()`) and
 * for `getDraftWithDetails()`'s sort order.
 *
 * UNLIKE threshingRecordRepo.ts: this station has NO operational-target
 * reference table — no static-constant data file, no Target Operasional
 * section on any of its 3 mobile screens.
 *
 * "Filled" row, for the required-row validation only (not a DB constraint):
 * an engine_room_detail row counts as filled when at least one of its 27
 * non-time_slot columns is non-null/non-empty — like Storage Tank, this
 * station has no identifying/context columns (no shift/inspector_id
 * equivalent), so all 27 columns participate. This is the LARGEST field
 * count of any station in this project (task brief originally said 26;
 * the backend migration — ground truth — has 27; corrected here as a
 * derived assumption).
 *
 * The 2 enum columns (diesel_gen_1_status, diesel_gen_2_status) are stored
 * as plain TEXT in local SQLite (no CHECK constraint here — that only
 * applies on the backend), so no empty-string coercion is required in this
 * repo; `|| null` already normalizes '' the same way it does for every
 * other optional column.
 */

export type EngineRoomDraftStatus = 'draft_ongoing' | 'draft_paused'
export type EngineRoomRecordStatus = EngineRoomDraftStatus | 'saved' | 'synced'

/**
 * Full local `engine_room_record` row shape (mirrors localSchema.ts's
 * CREATE_ENGINE_ROOM_RECORD column-for-column).
 */
export interface EngineRoomRecord {
  id: string
  station_id: string | null
  engine_room_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  status: EngineRoomRecordStatus
  created_by: string
  created_at: string
  updated_at: string
}

/**
 * Full local `engine_room_detail` row shape (mirrors localSchema.ts's
 * CREATE_ENGINE_ROOM_DETAIL column-for-column).
 */
export interface EngineRoomDetailRow {
  id: string
  engine_room_record_id: string
  time_slot: string
  steam_turbine_inlet_pressure_bar: number | null
  steam_turbine_inlet_temp_c: number | null
  steam_turbine_exhaust_pressure_bar: number | null
  steam_turbine_rpm: number | null
  steam_turbine_alternator_bearing_temp_1_c: number | null
  steam_turbine_alternator_bearing_temp_2_c: number | null
  diesel_gen_1_status: string | null
  diesel_gen_1_load_kw: number | null
  diesel_gen_1_amperage_a: number | null
  diesel_gen_1_jacket_water_temp_c: number | null
  diesel_gen_1_lube_oil_pressure_bar: number | null
  diesel_gen_2_status: string | null
  diesel_gen_2_load_kw: number | null
  diesel_gen_2_amperage_a: number | null
  diesel_gen_2_jacket_water_temp_c: number | null
  diesel_gen_2_lube_oil_pressure_bar: number | null
  electrical_sync_total_factory_load_kw: number | null
  electrical_sync_system_frequency_hz: number | null
  electrical_sync_power_factor: number | null
  electrical_sync_busbar_voltage_v: number | null
  air_compressor_1_pressure_bar: number | null
  compressor_2_pressure_bar: number | null
  battery_charger_ups_voltage_v: number | null
  fuel_tank_level: number | null
  daily_energy_export_kwh: number | null
  action_taken_maintenance_remark: string | null
  findings: string | null
  created_at: string
  updated_at: string
}

export interface EngineRoomDraftWithDetails {
  record: EngineRoomRecord
  details: EngineRoomDetailRow[]
}

/**
 * Subset of `EngineRoomRecord` columns the Form Engine Room screen
 * collects from the user (excludes id/station_id/created_by/created_at/
 * updated_at/status, which are managed by createDraft()/saveDraft() itself).
 */
export interface EngineRoomHeaderFormData {
  engine_room_id: string
  date: string
  note: string
  checked_by: string
  acknowledged_by: string
}

/**
 * One editable `engine_room_detail` row as held in
 * FormEngineRoomView.vue's form state. `id` is present for rows loaded
 * from an existing draft (UPDATE target) and absent for rows added in this
 * editing session (INSERT target) — `saveDraft()`/
 * `pauseDraftWithFormData()` upsert on exactly this distinction, mirroring
 * StorageTankDetailFormRow exactly. `time_slot` is `null` until the user
 * picks one from the dropdown for a newly-added row.
 */
export interface EngineRoomDetailFormRow {
  id?: string
  time_slot: string | null
  steam_turbine_inlet_pressure_bar: number | null
  steam_turbine_inlet_temp_c: number | null
  steam_turbine_exhaust_pressure_bar: number | null
  steam_turbine_rpm: number | null
  steam_turbine_alternator_bearing_temp_1_c: number | null
  steam_turbine_alternator_bearing_temp_2_c: number | null
  diesel_gen_1_status: string | null
  diesel_gen_1_load_kw: number | null
  diesel_gen_1_amperage_a: number | null
  diesel_gen_1_jacket_water_temp_c: number | null
  diesel_gen_1_lube_oil_pressure_bar: number | null
  diesel_gen_2_status: string | null
  diesel_gen_2_load_kw: number | null
  diesel_gen_2_amperage_a: number | null
  diesel_gen_2_jacket_water_temp_c: number | null
  diesel_gen_2_lube_oil_pressure_bar: number | null
  electrical_sync_total_factory_load_kw: number | null
  electrical_sync_system_frequency_hz: number | null
  electrical_sync_power_factor: number | null
  electrical_sync_busbar_voltage_v: number | null
  air_compressor_1_pressure_bar: number | null
  compressor_2_pressure_bar: number | null
  battery_charger_ups_voltage_v: number | null
  fuel_tank_level: number | null
  daily_energy_export_kwh: number | null
  action_taken_maintenance_remark: string | null
  findings: string | null
}

/**
 * User roles a saveDraft()/pauseDraftWithFormData() caller may pass as
 * currentUserRole — plain string union, matching
 * storageTankRecordRepo.ts's StorageTankActorRole, decoupling this repo
 * from the auth store.
 */
export type EngineRoomActorRole = 'operator' | 'supervisor' | 'mill_management' | 'admin'

/**
 * screen-077--form-engine-room business_logic step 12 — thrown by
 * saveDraft() when zero rows in the given `details` are "valid" (a selected
 * `time_slot` AND at least one filled reading column), so callers
 * (FormEngineRoomView.vue) can distinguish this from other save failures —
 * mirrors StorageTankDetailRequiredError exactly.
 */
export class EngineRoomDetailRequiredError extends Error {
  constructor() {
    super(
      'Minimal 1 baris Engine Room Detail (Time-Slot terpilih + minimal 1 kolom bacaan terisi) harus diisi sebelum menyimpan.',
    )
    this.name = 'EngineRoomDetailRequiredError'
  }
}

export interface EngineRoomDraftListItem {
  id: string
  status: EngineRoomDraftStatus
  engine_room_id: string | null
  updated_at: string
}

interface DraftListRow {
  id: string
  status: EngineRoomDraftStatus
  engine_room_id: string | null
  updated_at: string
}

export interface EngineRoomTodaySummary {
  countEngineRoomRecord: number
  detailRowCount: number
}

interface TodayRecordIdRow {
  id: string
}

interface DetailRowCountRow {
  detail_row_count: number
}

const DRAFT_STATUSES: EngineRoomDraftStatus[] = ['draft_ongoing', 'draft_paused']

function nowIso(): string {
  return new Date().toISOString()
}

function generateId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `err-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function generateDetailId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `erd-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

/**
 * The 24 canonical hourly time-slot labels, in order: 07:00, 08:00, ...,
 * 23:00, 00:00, ..., 06:00 (i.e. starting at hour 7, wrapping past
 * midnight). Shared by createDraft() callers and any caller needing the
 * canonical order (e.g. tests). `for i in 0..23 -> hour = (7 + i) % 24`.
 */
export function canonicalTimeSlots(): string[] {
  return Array.from({ length: 24 }, (_, i) => {
    const hour = (7 + i) % 24
    return `${String(hour).padStart(2, '0')}:00`
  })
}

/**
 * Returns true when an engine_room_detail row has at least one of its 27
 * non-time_slot columns non-null/non-empty — see this file's header comment
 * for the "filled row" definition (no identifying columns to exclude on
 * this station).
 */
function isRowFilled(row: {
  steam_turbine_inlet_pressure_bar: number | null
  steam_turbine_inlet_temp_c: number | null
  steam_turbine_exhaust_pressure_bar: number | null
  steam_turbine_rpm: number | null
  steam_turbine_alternator_bearing_temp_1_c: number | null
  steam_turbine_alternator_bearing_temp_2_c: number | null
  diesel_gen_1_status: string | null
  diesel_gen_1_load_kw: number | null
  diesel_gen_1_amperage_a: number | null
  diesel_gen_1_jacket_water_temp_c: number | null
  diesel_gen_1_lube_oil_pressure_bar: number | null
  diesel_gen_2_status: string | null
  diesel_gen_2_load_kw: number | null
  diesel_gen_2_amperage_a: number | null
  diesel_gen_2_jacket_water_temp_c: number | null
  diesel_gen_2_lube_oil_pressure_bar: number | null
  electrical_sync_total_factory_load_kw: number | null
  electrical_sync_system_frequency_hz: number | null
  electrical_sync_power_factor: number | null
  electrical_sync_busbar_voltage_v: number | null
  air_compressor_1_pressure_bar: number | null
  compressor_2_pressure_bar: number | null
  battery_charger_ups_voltage_v: number | null
  fuel_tank_level: number | null
  daily_energy_export_kwh: number | null
  action_taken_maintenance_remark: string | null
  findings: string | null
}): boolean {
  return (
    row.steam_turbine_inlet_pressure_bar !== null ||
    row.steam_turbine_inlet_temp_c !== null ||
    row.steam_turbine_exhaust_pressure_bar !== null ||
    row.steam_turbine_rpm !== null ||
    row.steam_turbine_alternator_bearing_temp_1_c !== null ||
    row.steam_turbine_alternator_bearing_temp_2_c !== null ||
    (row.diesel_gen_1_status !== null && row.diesel_gen_1_status !== '') ||
    row.diesel_gen_1_load_kw !== null ||
    row.diesel_gen_1_amperage_a !== null ||
    row.diesel_gen_1_jacket_water_temp_c !== null ||
    row.diesel_gen_1_lube_oil_pressure_bar !== null ||
    (row.diesel_gen_2_status !== null && row.diesel_gen_2_status !== '') ||
    row.diesel_gen_2_load_kw !== null ||
    row.diesel_gen_2_amperage_a !== null ||
    row.diesel_gen_2_jacket_water_temp_c !== null ||
    row.diesel_gen_2_lube_oil_pressure_bar !== null ||
    row.electrical_sync_total_factory_load_kw !== null ||
    row.electrical_sync_system_frequency_hz !== null ||
    row.electrical_sync_power_factor !== null ||
    row.electrical_sync_busbar_voltage_v !== null ||
    row.air_compressor_1_pressure_bar !== null ||
    row.compressor_2_pressure_bar !== null ||
    row.battery_charger_ups_voltage_v !== null ||
    row.fuel_tank_level !== null ||
    row.daily_energy_export_kwh !== null ||
    (row.action_taken_maintenance_remark !== null && row.action_taken_maintenance_remark !== '') ||
    (row.findings !== null && row.findings !== '')
  )
}

/**
 * screen-067--monitor-engine-room business_logic step 5 — 'New Data'.
 * INSERTs a new engine_room_record row ONLY (status=draft_ongoing,
 * created_by=current user, date=now — auto-filled once at creation, mirrors
 * storageTankRecordRepo.ts's createDraft()) — no engine_room_detail rows
 * are pre-created; rows are added one at a time by the user via "Tambah
 * baris" in FormEngineRoomView.vue. Returns the new record's id so the
 * caller can navigate to Form Engine Room with it.
 */
export async function createDraft(userId: string): Promise<string> {
  const id = generateId()
  const timestamp = nowIso()

  await run(
    `INSERT INTO engine_room_record (id, status, created_by, date, created_at, updated_at)
     VALUES (?, 'draft_ongoing', ?, ?, ?, ?)`,
    [id, userId, timestamp, timestamp, timestamp],
  )

  return id
}

/**
 * screen-067--monitor-engine-room business_logic step 2 — every local
 * engine_room_record the current user has ongoing or paused,
 * most-recently-updated first. Mirrors storageTankRecordRepo.ts's
 * getDrafts().
 */
export async function getDrafts(userId: string): Promise<EngineRoomDraftListItem[]> {
  const rows = await query<DraftListRow>(
    `SELECT id, status, engine_room_id, updated_at
     FROM engine_room_record
     WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
     ORDER BY updated_at DESC`,
    [userId],
  )

  return rows.map((row) => ({
    id: row.id,
    status: row.status,
    engine_room_id: row.engine_room_id,
    updated_at: row.updated_at,
  }))
}

/**
 * screen-067--monitor-engine-room business_logic step 1 — "Hari Ini"
 * counters. "Jumlah Engine Room Record" counts the current user's
 * engine_room_record rows (any status) dated today (device-local day
 * boundary). "Jumlah Baris Time-Slot Tercatat" is a plain COUNT of
 * engine_room_detail rows belonging to those same records — mirrors
 * storageTankRecordRepo.ts's getTodaySummary() exactly.
 */
export async function getTodaySummary(userId: string): Promise<EngineRoomTodaySummary> {
  const idRows = await query<TodayRecordIdRow>(
    `SELECT id
     FROM engine_room_record
     WHERE created_by = ? AND date(date) = date('now', 'localtime')`,
    [userId],
  )

  const countEngineRoomRecord = idRows.length

  if (countEngineRoomRecord === 0) {
    return { countEngineRoomRecord: 0, detailRowCount: 0 }
  }

  const placeholders = idRows.map(() => '?').join(', ')

  const countRows = await query<DetailRowCountRow>(
    `SELECT COUNT(*) AS detail_row_count FROM engine_room_detail WHERE engine_room_record_id IN (${placeholders})`,
    idRows.map((row) => row.id),
  )

  return {
    countEngineRoomRecord,
    detailRowCount: countRows[0]?.detail_row_count ?? 0,
  }
}

/**
 * screen-087--data-preview-engine-room business_logic step 1 — every local
 * engine_room_record row for the current user, ANY status,
 * most-recently-updated first. Mirrors storageTankRecordRepo.ts's
 * getAllRecords().
 */
export async function getAllRecords(userId: string): Promise<EngineRoomRecord[]> {
  return query<EngineRoomRecord>(
    `SELECT * FROM engine_room_record WHERE created_by = ? ORDER BY updated_at DESC`,
    [userId],
  )
}

/**
 * screen-077--form-engine-room business_logic step 1 — Form Engine Room
 * loads an existing draft by route param id, plus all of its
 * engine_room_detail rows (however many the user has added so far),
 * ordered by time_slot ascending starting at 07:00 (canonical order, not
 * alphabetical — '00:00' would otherwise sort before '07:00'). Returns null
 * when no header row matches (defensive).
 */
export async function getDraftWithDetails(recordId: string): Promise<EngineRoomDraftWithDetails | null> {
  const recordRows = await query<EngineRoomRecord>(`SELECT * FROM engine_room_record WHERE id = ?`, [recordId])
  const record = recordRows[0]

  if (!record) {
    return null
  }

  const rawDetails = await query<EngineRoomDetailRow>(
    `SELECT * FROM engine_room_detail WHERE engine_room_record_id = ?`,
    [recordId],
  )

  const order = canonicalTimeSlots()
  const details = [...rawDetails].sort((a, b) => order.indexOf(a.time_slot) - order.indexOf(b.time_slot))

  return { record, details }
}

/**
 * screen-077--form-engine-room — upserts every row in `rows` against
 * `engine_room_detail` (rows with an existing `id` UPDATEd in place, rows
 * without one INSERTed with a freshly generated id via
 * `generateDetailId()`), then DELETEs every id in `idsToDelete` (rows the
 * user removed via "Hapus baris" on an already-loaded draft). Shared by
 * `saveDraft()` and `pauseDraftWithFormData()` so both stay in lock-step on
 * this upsert/delete contract — mirrors storageTankRecordRepo.ts's
 * `applyDetailRowChanges()` exactly.
 */
async function applyDetailRowChanges(
  recordId: string,
  rows: EngineRoomDetailFormRow[],
  idsToDelete: string[],
  timestamp: string,
): Promise<void> {
  for (const row of rows) {
    if (row.id) {
      await run(
        `UPDATE engine_room_detail
         SET time_slot = ?,
             steam_turbine_inlet_pressure_bar = ?,
             steam_turbine_inlet_temp_c = ?,
             steam_turbine_exhaust_pressure_bar = ?,
             steam_turbine_rpm = ?,
             steam_turbine_alternator_bearing_temp_1_c = ?,
             steam_turbine_alternator_bearing_temp_2_c = ?,
             diesel_gen_1_status = ?,
             diesel_gen_1_load_kw = ?,
             diesel_gen_1_amperage_a = ?,
             diesel_gen_1_jacket_water_temp_c = ?,
             diesel_gen_1_lube_oil_pressure_bar = ?,
             diesel_gen_2_status = ?,
             diesel_gen_2_load_kw = ?,
             diesel_gen_2_amperage_a = ?,
             diesel_gen_2_jacket_water_temp_c = ?,
             diesel_gen_2_lube_oil_pressure_bar = ?,
             electrical_sync_total_factory_load_kw = ?,
             electrical_sync_system_frequency_hz = ?,
             electrical_sync_power_factor = ?,
             electrical_sync_busbar_voltage_v = ?,
             air_compressor_1_pressure_bar = ?,
             compressor_2_pressure_bar = ?,
             battery_charger_ups_voltage_v = ?,
             fuel_tank_level = ?,
             daily_energy_export_kwh = ?,
             action_taken_maintenance_remark = ?,
             findings = ?,
             updated_at = ?
         WHERE id = ?`,
        [
          row.time_slot,
          row.steam_turbine_inlet_pressure_bar,
          row.steam_turbine_inlet_temp_c,
          row.steam_turbine_exhaust_pressure_bar,
          row.steam_turbine_rpm,
          row.steam_turbine_alternator_bearing_temp_1_c,
          row.steam_turbine_alternator_bearing_temp_2_c,
          row.diesel_gen_1_status || null,
          row.diesel_gen_1_load_kw,
          row.diesel_gen_1_amperage_a,
          row.diesel_gen_1_jacket_water_temp_c,
          row.diesel_gen_1_lube_oil_pressure_bar,
          row.diesel_gen_2_status || null,
          row.diesel_gen_2_load_kw,
          row.diesel_gen_2_amperage_a,
          row.diesel_gen_2_jacket_water_temp_c,
          row.diesel_gen_2_lube_oil_pressure_bar,
          row.electrical_sync_total_factory_load_kw,
          row.electrical_sync_system_frequency_hz,
          row.electrical_sync_power_factor,
          row.electrical_sync_busbar_voltage_v,
          row.air_compressor_1_pressure_bar,
          row.compressor_2_pressure_bar,
          row.battery_charger_ups_voltage_v,
          row.fuel_tank_level,
          row.daily_energy_export_kwh,
          row.action_taken_maintenance_remark,
          row.findings,
          timestamp,
          row.id,
        ],
      )
      continue
    }

    const detailId = generateDetailId()

    await run(
      `INSERT INTO engine_room_detail
         (id, engine_room_record_id, time_slot, steam_turbine_inlet_pressure_bar, steam_turbine_inlet_temp_c, steam_turbine_exhaust_pressure_bar, steam_turbine_rpm, steam_turbine_alternator_bearing_temp_1_c, steam_turbine_alternator_bearing_temp_2_c, diesel_gen_1_status, diesel_gen_1_load_kw, diesel_gen_1_amperage_a, diesel_gen_1_jacket_water_temp_c, diesel_gen_1_lube_oil_pressure_bar, diesel_gen_2_status, diesel_gen_2_load_kw, diesel_gen_2_amperage_a, diesel_gen_2_jacket_water_temp_c, diesel_gen_2_lube_oil_pressure_bar, electrical_sync_total_factory_load_kw, electrical_sync_system_frequency_hz, electrical_sync_power_factor, electrical_sync_busbar_voltage_v, air_compressor_1_pressure_bar, compressor_2_pressure_bar, battery_charger_ups_voltage_v, fuel_tank_level, daily_energy_export_kwh, action_taken_maintenance_remark, findings, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        detailId,
        recordId,
        row.time_slot,
        row.steam_turbine_inlet_pressure_bar,
        row.steam_turbine_inlet_temp_c,
        row.steam_turbine_exhaust_pressure_bar,
        row.steam_turbine_rpm,
        row.steam_turbine_alternator_bearing_temp_1_c,
        row.steam_turbine_alternator_bearing_temp_2_c,
        row.diesel_gen_1_status || null,
        row.diesel_gen_1_load_kw,
        row.diesel_gen_1_amperage_a,
        row.diesel_gen_1_jacket_water_temp_c,
        row.diesel_gen_1_lube_oil_pressure_bar,
        row.diesel_gen_2_status || null,
        row.diesel_gen_2_load_kw,
        row.diesel_gen_2_amperage_a,
        row.diesel_gen_2_jacket_water_temp_c,
        row.diesel_gen_2_lube_oil_pressure_bar,
        row.electrical_sync_total_factory_load_kw,
        row.electrical_sync_system_frequency_hz,
        row.electrical_sync_power_factor,
        row.electrical_sync_busbar_voltage_v,
        row.air_compressor_1_pressure_bar,
        row.compressor_2_pressure_bar,
        row.battery_charger_ups_voltage_v,
        row.fuel_tank_level,
        row.daily_energy_export_kwh,
        row.action_taken_maintenance_remark,
        row.findings,
        timestamp,
        timestamp,
      ],
    )
  }

  for (const id of idsToDelete) {
    await run(`DELETE FROM engine_room_detail WHERE id = ?`, [id])
  }
}

/**
 * screen-077--form-engine-room business_logic step 12 — 'Simpan'.
 *
 * Throws EngineRoomDetailRequiredError when zero rows in the given
 * `details` are "valid" (a selected `time_slot` AND at least one filled
 * reading column — see isRowFilled()) — enforced here (not only as a
 * client-side pre-check in FormEngineRoomView.vue) so the rule holds even
 * if a caller bypasses the UI. No DB write happens at all in that case.
 * Mirrors storageTankRecordRepo.ts's saveDraft() "at least one valid row"
 * gate exactly. Required-header-field validation (engine_room_id) is the
 * caller's job — this function assumes the header has already been
 * validated.
 *
 * checked_by/acknowledged_by role-stripping: mirrors
 * storageTankRecordRepo.ts's saveDraft() — whenever currentUserRole is
 * not 'supervisor', headerData.checked_by is ignored (null written
 * instead); whenever not 'mill_management', headerData.acknowledged_by is
 * likewise ignored.
 *
 * On success: UPDATEs the header row (status='saved'), then applies
 * `details`'s upserts and `detailIdsToDelete`'s deletes via
 * applyDetailRowChanges(). Sequential run() calls, not a single
 * transaction (see this file's header comment). Returns the freshly saved
 * draft (re-read via getDraftWithDetails()).
 */
export async function saveDraft(
  recordId: string,
  headerData: EngineRoomHeaderFormData,
  details: EngineRoomDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: EngineRoomActorRole | null | undefined,
): Promise<EngineRoomDraftWithDetails> {
  const validRowCount = details.filter(
    (row) => row.time_slot !== null && row.time_slot !== undefined && row.time_slot !== '' && isRowFilled(row),
  ).length

  if (validRowCount === 0) {
    throw new EngineRoomDetailRequiredError()
  }

  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE engine_room_record
     SET engine_room_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'saved',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.engine_room_id || null,
      headerData.date || null,
      headerData.note || null,
      checkedBy,
      acknowledgedBy,
      timestamp,
      recordId,
    ],
  )

  await applyDetailRowChanges(recordId, details, detailIdsToDelete, timestamp)

  const saved = await getDraftWithDetails(recordId)

  if (!saved) {
    throw new Error('Gagal memuat ulang data engine room setelah disimpan.')
  }

  return saved
}

/**
 * screen-077--form-engine-room business_logic step 13 — 'Pause'. UPDATEs
 * the header as-is (no required-field validation, no "at least one valid
 * row" gate), status='draft_paused', then applies the same upsert/delete
 * contract as saveDraft() via applyDetailRowChanges(). Same role-stripping
 * as saveDraft(). Returns the freshly paused draft.
 */
export async function pauseDraftWithFormData(
  recordId: string,
  headerData: EngineRoomHeaderFormData,
  details: EngineRoomDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: EngineRoomActorRole | null | undefined,
): Promise<EngineRoomDraftWithDetails> {
  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE engine_room_record
     SET engine_room_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'draft_paused',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.engine_room_id || null,
      headerData.date || null,
      headerData.note || null,
      checkedBy,
      acknowledgedBy,
      timestamp,
      recordId,
    ],
  )

  await applyDetailRowChanges(recordId, details, detailIdsToDelete, timestamp)

  const saved = await getDraftWithDetails(recordId)

  if (!saved) {
    throw new Error('Gagal menyimpan progres engine room setelah pause.')
  }

  return saved
}

/**
 * screen-077--form-engine-room business_logic step 14 — 'Clear' (after UI
 * confirm). Permanently DELETEs the record, cascading the delete to all of
 * its engine_room_detail rows first (application-level cascade, same
 * pattern as storageTankRecordRepo.ts's deleteDraft()).
 */
export async function deleteDraft(recordId: string): Promise<void> {
  await run(`DELETE FROM engine_room_detail WHERE engine_room_record_id = ?`, [recordId])
  await run(`DELETE FROM engine_room_record WHERE id = ?`, [recordId])
}

export const engineRoomRecordRepo = {
  canonicalTimeSlots,
  createDraft,
  getDrafts,
  getTodaySummary,
  getAllRecords,
  getDraftWithDetails,
  saveDraft,
  pauseDraftWithFormData,
  deleteDraft,
}

export default engineRoomRecordRepo

export const ENGINE_ROOM_DRAFT_STATUSES = DRAFT_STATUSES
