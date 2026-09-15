import { query, run } from '@/services/localDb'

/**
 * boilerRoomRecordRepo — screen-068--monitor-boiler-room /
 * usecase-103--monitor-boiler-room, screen-078--form-boiler-room /
 * usecase-104--form-boiler-room, screen-088--data-preview-boiler-room /
 * usecase-105--data-preview-boiler-room. Local (offline) read/write access
 * to the `boiler_room_record` / `boiler_room_detail` tables (schema
 * defined in localSchema.ts) — mirrors engineRoomRecordRepo.ts's repo
 * style (plain async functions over localDb.ts's `query`/`run` primitives)
 * exactly, since Boiler Room follows the same hourly-grid (dynamic
 * add-row/remove-row, 1..24 rows) pattern as Engine Room/Storage Tank.
 *
 * `createDraft()` inserts ONLY the header row — no detail rows at all. Rows
 * are added one at a time via `applyDetailRowChanges()` (called from
 * `saveDraft()`/`pauseDraftWithFormData()`, mirrors
 * engineRoomRecordRepo.ts's `applyDetailRowChanges()`): rows with an
 * existing `id` are UPDATEd, rows without one are INSERTed with a freshly
 * generated id via `generateDetailId()`, and every id in the
 * caller-supplied `idsToDelete` list is DELETEd after the upserts.
 * `canonicalTimeSlots()` is the source of truth for the dropdown's ordered
 * option list (FormBoilerRoomView.vue's `availableTimeSlotOptions()`) and
 * for `getDraftWithDetails()`'s sort order.
 *
 * UNLIKE Threshing/Pressing/Depricarping/Kernel Plant: this station has NO
 * operational-target reference table — no static-constant data file, no
 * Target Operasional section on any of its 3 mobile screens.
 *
 * "Filled" row, for the required-row validation only (not a DB constraint):
 * a boiler_room_detail row counts as filled when at least one of its 15
 * non-time_slot columns is non-null/non-empty — like Engine Room, this
 * station has no identifying/context columns (no shift/inspector_id
 * equivalent), so all 15 columns participate.
 *
 * fuel_feed_rate/id_fan_load/sa_fan_load are free-text string columns (not
 * numeric) because the paper-form units are mixed/ambiguous (Hz/%/tons,
 * A/%) per entity-catalog's actual field type.
 *
 * The 2 enum columns (blowdown_executed, sootblowing_executed) are stored
 * as plain TEXT in local SQLite (no CHECK constraint here — that only
 * applies on the backend), so no empty-string coercion is required in this
 * repo; `|| null` already normalizes '' the same way it does for every
 * other optional column.
 */

export type BoilerRoomDraftStatus = 'draft_ongoing' | 'draft_paused'
export type BoilerRoomRecordStatus = BoilerRoomDraftStatus | 'saved' | 'synced'

/**
 * Full local `boiler_room_record` row shape (mirrors localSchema.ts's
 * CREATE_BOILER_ROOM_RECORD column-for-column).
 */
export interface BoilerRoomRecord {
  id: string
  station_id: string | null
  boiler_room_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  checked_by_name?: string | null
  acknowledged_by_name?: string | null
  status: BoilerRoomRecordStatus
  created_by: string
  created_at: string
  updated_at: string
}

/**
 * Full local `boiler_room_detail` row shape (mirrors localSchema.ts's
 * CREATE_BOILER_ROOM_DETAIL column-for-column).
 */
export interface BoilerRoomDetailRow {
  id: string
  boiler_room_record_id: string
  time_slot: string
  steam_pressure_bar: number | null
  steam_temp_c: number | null
  feed_water_temp_c: number | null
  feed_water_tank_level_percent: number | null
  boiler_water_level_percent: number | null
  water_tds_ppm: number | null
  water_ph: number | null
  fuel_feed_rate: string | null
  id_fan_load: string | null
  sa_fan_load: string | null
  exhaust_gas_temp_c: number | null
  dust_collector_differential_pressure_mmh2o: number | null
  blowdown_executed: string | null
  sootblowing_executed: string | null
  findings: string | null
  created_at: string
  updated_at: string
}

export interface BoilerRoomDraftWithDetails {
  record: BoilerRoomRecord
  details: BoilerRoomDetailRow[]
}

/**
 * Subset of `BoilerRoomRecord` columns the Form Boiler Room screen
 * collects from the user (excludes id/station_id/created_by/created_at/
 * updated_at/status, which are managed by createDraft()/saveDraft() itself).
 */
export interface BoilerRoomHeaderFormData {
  boiler_room_id: string
  date: string
  note: string
  checked_by: string
  acknowledged_by: string
}

/**
 * One editable `boiler_room_detail` row as held in
 * FormBoilerRoomView.vue's form state. `id` is present for rows loaded
 * from an existing draft (UPDATE target) and absent for rows added in this
 * editing session (INSERT target) — `saveDraft()`/
 * `pauseDraftWithFormData()` upsert on exactly this distinction, mirroring
 * EngineRoomDetailFormRow exactly. `time_slot` is `null` until the user
 * picks one from the dropdown for a newly-added row.
 */
export interface BoilerRoomDetailFormRow {
  id?: string
  time_slot: string | null
  steam_pressure_bar: number | null
  steam_temp_c: number | null
  feed_water_temp_c: number | null
  feed_water_tank_level_percent: number | null
  boiler_water_level_percent: number | null
  water_tds_ppm: number | null
  water_ph: number | null
  fuel_feed_rate: string | null
  id_fan_load: string | null
  sa_fan_load: string | null
  exhaust_gas_temp_c: number | null
  dust_collector_differential_pressure_mmh2o: number | null
  blowdown_executed: string | null
  sootblowing_executed: string | null
  findings: string | null
}

/**
 * User roles a saveDraft()/pauseDraftWithFormData() caller may pass as
 * currentUserRole — plain string union, matching
 * engineRoomRecordRepo.ts's EngineRoomActorRole, decoupling this repo
 * from the auth store.
 */
export type BoilerRoomActorRole = 'operator' | 'supervisor' | 'mill_management' | 'admin'

/**
 * screen-078--form-boiler-room business_logic step 12 — thrown by
 * saveDraft() when zero rows in the given `details` are "valid" (a selected
 * `time_slot` AND at least one filled reading column), so callers
 * (FormBoilerRoomView.vue) can distinguish this from other save failures —
 * mirrors EngineRoomDetailRequiredError exactly.
 */
export class BoilerRoomDetailRequiredError extends Error {
  constructor() {
    super(
      'Minimal 1 baris Boiler Room Detail (Time-Slot terpilih + minimal 1 kolom bacaan terisi) harus diisi sebelum menyimpan.',
    )
    this.name = 'BoilerRoomDetailRequiredError'
  }
}

export interface BoilerRoomDraftListItem {
  id: string
  status: BoilerRoomDraftStatus
  boiler_room_id: string | null
  updated_at: string
}

interface DraftListRow {
  id: string
  status: BoilerRoomDraftStatus
  boiler_room_id: string | null
  updated_at: string
}

export interface BoilerRoomTodaySummary {
  countBoilerRoomRecord: number
  detailRowCount: number
}

interface TodayRecordIdRow {
  id: string
}

interface DetailRowCountRow {
  detail_row_count: number
}

const DRAFT_STATUSES: BoilerRoomDraftStatus[] = ['draft_ongoing', 'draft_paused']

function nowIso(): string {
  return new Date().toISOString()
}

function generateId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `brr-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function generateDetailId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `brd-${Date.now()}-${Math.random().toString(16).slice(2)}`
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
 * Returns true when a boiler_room_detail row has at least one of its 15
 * non-time_slot columns non-null/non-empty — see this file's header comment
 * for the "filled row" definition (no identifying columns to exclude on
 * this station).
 */
function isRowFilled(row: {
  steam_pressure_bar: number | null
  steam_temp_c: number | null
  feed_water_temp_c: number | null
  feed_water_tank_level_percent: number | null
  boiler_water_level_percent: number | null
  water_tds_ppm: number | null
  water_ph: number | null
  fuel_feed_rate: string | null
  id_fan_load: string | null
  sa_fan_load: string | null
  exhaust_gas_temp_c: number | null
  dust_collector_differential_pressure_mmh2o: number | null
  blowdown_executed: string | null
  sootblowing_executed: string | null
  findings: string | null
}): boolean {
  return (
    row.steam_pressure_bar !== null ||
    row.steam_temp_c !== null ||
    row.feed_water_temp_c !== null ||
    row.feed_water_tank_level_percent !== null ||
    row.boiler_water_level_percent !== null ||
    row.water_tds_ppm !== null ||
    row.water_ph !== null ||
    (row.fuel_feed_rate !== null && row.fuel_feed_rate !== '') ||
    (row.id_fan_load !== null && row.id_fan_load !== '') ||
    (row.sa_fan_load !== null && row.sa_fan_load !== '') ||
    row.exhaust_gas_temp_c !== null ||
    row.dust_collector_differential_pressure_mmh2o !== null ||
    (row.blowdown_executed !== null && row.blowdown_executed !== '') ||
    (row.sootblowing_executed !== null && row.sootblowing_executed !== '') ||
    (row.findings !== null && row.findings !== '')
  )
}

/**
 * screen-068--monitor-boiler-room business_logic step 5 — 'New Data'.
 * INSERTs a new boiler_room_record row ONLY (status=draft_ongoing,
 * created_by=current user, date=now — auto-filled once at creation, mirrors
 * engineRoomRecordRepo.ts's createDraft()) — no boiler_room_detail rows
 * are pre-created; rows are added one at a time by the user via "Tambah
 * baris" in FormBoilerRoomView.vue. Returns the new record's id so the
 * caller can navigate to Form Boiler Room with it.
 */
export async function createDraft(userId: string): Promise<string> {
  const id = generateId()
  const timestamp = nowIso()

  await run(
    `INSERT INTO boiler_room_record (id, status, created_by, date, created_at, updated_at)
     VALUES (?, 'draft_ongoing', ?, ?, ?, ?)`,
    [id, userId, timestamp, timestamp, timestamp],
  )

  return id
}

/**
 * screen-068--monitor-boiler-room business_logic step 2 — every local
 * boiler_room_record the current user has ongoing or paused,
 * most-recently-updated first. Mirrors engineRoomRecordRepo.ts's
 * getDrafts().
 */
export async function getDrafts(userId: string): Promise<BoilerRoomDraftListItem[]> {
  const rows = await query<DraftListRow>(
    `SELECT id, status, boiler_room_id, updated_at
     FROM boiler_room_record
     WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
     ORDER BY updated_at DESC`,
    [userId],
  )

  return rows.map((row) => ({
    id: row.id,
    status: row.status,
    boiler_room_id: row.boiler_room_id,
    updated_at: row.updated_at,
  }))
}

/**
 * screen-068--monitor-boiler-room business_logic step 1 — "Hari Ini"
 * counters. "Jumlah Boiler Room Record" counts the current user's
 * boiler_room_record rows (any status) dated today (device-local day
 * boundary). "Jumlah Baris Time-Slot Tercatat" is a plain COUNT of
 * boiler_room_detail rows belonging to those same records — mirrors
 * engineRoomRecordRepo.ts's getTodaySummary() exactly.
 */
export async function getTodaySummary(userId: string): Promise<BoilerRoomTodaySummary> {
  const idRows = await query<TodayRecordIdRow>(
    `SELECT id
     FROM boiler_room_record
     WHERE created_by = ? AND date(date) = date('now', 'localtime')`,
    [userId],
  )

  const countBoilerRoomRecord = idRows.length

  if (countBoilerRoomRecord === 0) {
    return { countBoilerRoomRecord: 0, detailRowCount: 0 }
  }

  const placeholders = idRows.map(() => '?').join(', ')

  const countRows = await query<DetailRowCountRow>(
    `SELECT COUNT(*) AS detail_row_count FROM boiler_room_detail WHERE boiler_room_record_id IN (${placeholders})`,
    idRows.map((row) => row.id),
  )

  return {
    countBoilerRoomRecord,
    detailRowCount: countRows[0]?.detail_row_count ?? 0,
  }
}

/**
 * screen-088--data-preview-boiler-room business_logic step 1 — every local
 * boiler_room_record row for the current user, ANY status,
 * most-recently-updated first. Mirrors engineRoomRecordRepo.ts's
 * getAllRecords().
 */
export async function getAllRecords(userId: string): Promise<BoilerRoomRecord[]> {
  return query<BoilerRoomRecord>(
    `SELECT * FROM boiler_room_record WHERE created_by = ? ORDER BY updated_at DESC`,
    [userId],
  )
}

/**
 * screen-078--form-boiler-room business_logic step 1 — Form Boiler Room
 * loads an existing draft by route param id, plus all of its
 * boiler_room_detail rows (however many the user has added so far),
 * ordered by time_slot ascending starting at 07:00 (canonical order, not
 * alphabetical — '00:00' would otherwise sort before '07:00'). Returns null
 * when no header row matches (defensive).
 */
export async function getDraftWithDetails(recordId: string): Promise<BoilerRoomDraftWithDetails | null> {
  const recordRows = await query<BoilerRoomRecord>(`SELECT * FROM boiler_room_record WHERE id = ?`, [recordId])
  const record = recordRows[0]

  if (!record) {
    return null
  }

  const rawDetails = await query<BoilerRoomDetailRow>(
    `SELECT * FROM boiler_room_detail WHERE boiler_room_record_id = ?`,
    [recordId],
  )

  const order = canonicalTimeSlots()
  const details = [...rawDetails].sort((a, b) => order.indexOf(a.time_slot) - order.indexOf(b.time_slot))

  return { record, details }
}

/**
 * screen-078--form-boiler-room — upserts every row in `rows` against
 * `boiler_room_detail` (rows with an existing `id` UPDATEd in place, rows
 * without one INSERTed with a freshly generated id via
 * `generateDetailId()`), then DELETEs every id in `idsToDelete` (rows the
 * user removed via "Hapus baris" on an already-loaded draft). Shared by
 * `saveDraft()` and `pauseDraftWithFormData()` so both stay in lock-step on
 * this upsert/delete contract — mirrors engineRoomRecordRepo.ts's
 * `applyDetailRowChanges()` exactly.
 */
async function applyDetailRowChanges(
  recordId: string,
  rows: BoilerRoomDetailFormRow[],
  idsToDelete: string[],
  timestamp: string,
): Promise<void> {
  for (const row of rows) {
    if (row.id) {
      await run(
        `UPDATE boiler_room_detail
         SET time_slot = ?,
             steam_pressure_bar = ?,
             steam_temp_c = ?,
             feed_water_temp_c = ?,
             feed_water_tank_level_percent = ?,
             boiler_water_level_percent = ?,
             water_tds_ppm = ?,
             water_ph = ?,
             fuel_feed_rate = ?,
             id_fan_load = ?,
             sa_fan_load = ?,
             exhaust_gas_temp_c = ?,
             dust_collector_differential_pressure_mmh2o = ?,
             blowdown_executed = ?,
             sootblowing_executed = ?,
             findings = ?,
             updated_at = ?
         WHERE id = ?`,
        [
          row.time_slot,
          row.steam_pressure_bar,
          row.steam_temp_c,
          row.feed_water_temp_c,
          row.feed_water_tank_level_percent,
          row.boiler_water_level_percent,
          row.water_tds_ppm,
          row.water_ph,
          row.fuel_feed_rate || null,
          row.id_fan_load || null,
          row.sa_fan_load || null,
          row.exhaust_gas_temp_c,
          row.dust_collector_differential_pressure_mmh2o,
          row.blowdown_executed || null,
          row.sootblowing_executed || null,
          row.findings || null,
          timestamp,
          row.id,
        ],
      )
      continue
    }

    const detailId = generateDetailId()

    await run(
      `INSERT INTO boiler_room_detail
         (id, boiler_room_record_id, time_slot, steam_pressure_bar, steam_temp_c, feed_water_temp_c, feed_water_tank_level_percent, boiler_water_level_percent, water_tds_ppm, water_ph, fuel_feed_rate, id_fan_load, sa_fan_load, exhaust_gas_temp_c, dust_collector_differential_pressure_mmh2o, blowdown_executed, sootblowing_executed, findings, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        detailId,
        recordId,
        row.time_slot,
        row.steam_pressure_bar,
        row.steam_temp_c,
        row.feed_water_temp_c,
        row.feed_water_tank_level_percent,
        row.boiler_water_level_percent,
        row.water_tds_ppm,
        row.water_ph,
        row.fuel_feed_rate || null,
        row.id_fan_load || null,
        row.sa_fan_load || null,
        row.exhaust_gas_temp_c,
        row.dust_collector_differential_pressure_mmh2o,
        row.blowdown_executed || null,
        row.sootblowing_executed || null,
        row.findings || null,
        timestamp,
        timestamp,
      ],
    )
  }

  for (const id of idsToDelete) {
    await run(`DELETE FROM boiler_room_detail WHERE id = ?`, [id])
  }
}

/**
 * screen-078--form-boiler-room business_logic step 12 — 'Simpan'.
 *
 * Throws BoilerRoomDetailRequiredError when zero rows in the given
 * `details` are "valid" (a selected `time_slot` AND at least one filled
 * reading column — see isRowFilled()) — enforced here (not only as a
 * client-side pre-check in FormBoilerRoomView.vue) so the rule holds even
 * if a caller bypasses the UI. No DB write happens at all in that case.
 * Mirrors engineRoomRecordRepo.ts's saveDraft() "at least one valid row"
 * gate exactly. Required-header-field validation (boiler_room_id) is the
 * caller's job — this function assumes the header has already been
 * validated.
 *
 * checked_by/acknowledged_by role-stripping: mirrors
 * engineRoomRecordRepo.ts's saveDraft() — whenever currentUserRole is
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
  headerData: BoilerRoomHeaderFormData,
  details: BoilerRoomDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: BoilerRoomActorRole | null | undefined,
): Promise<BoilerRoomDraftWithDetails> {
  const validRowCount = details.filter(
    (row) => row.time_slot !== null && row.time_slot !== undefined && row.time_slot !== '' && isRowFilled(row),
  ).length

  if (validRowCount === 0) {
    throw new BoilerRoomDetailRequiredError()
  }

  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE boiler_room_record
     SET boiler_room_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'saved',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.boiler_room_id || null,
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
    throw new Error('Gagal memuat ulang data boiler room setelah disimpan.')
  }

  return saved
}

/**
 * screen-078--form-boiler-room business_logic step 13 — 'Pause'. UPDATEs
 * the header as-is (no required-field validation, no "at least one valid
 * row" gate), status='draft_paused', then applies the same upsert/delete
 * contract as saveDraft() via applyDetailRowChanges(). Same role-stripping
 * as saveDraft(). Returns the freshly paused draft.
 */
export async function pauseDraftWithFormData(
  recordId: string,
  headerData: BoilerRoomHeaderFormData,
  details: BoilerRoomDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: BoilerRoomActorRole | null | undefined,
): Promise<BoilerRoomDraftWithDetails> {
  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE boiler_room_record
     SET boiler_room_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'draft_paused',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.boiler_room_id || null,
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
    throw new Error('Gagal menyimpan progres boiler room setelah pause.')
  }

  return saved
}

/**
 * screen-078--form-boiler-room business_logic step 14 — 'Clear' (after UI
 * confirm). Permanently DELETEs the record, cascading the delete to all of
 * its boiler_room_detail rows first (application-level cascade, same
 * pattern as engineRoomRecordRepo.ts's deleteDraft()).
 */
export async function deleteDraft(recordId: string): Promise<void> {
  await run(`DELETE FROM boiler_room_detail WHERE boiler_room_record_id = ?`, [recordId])
  await run(`DELETE FROM boiler_room_record WHERE id = ?`, [recordId])
}

export const boilerRoomRecordRepo = {
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

export default boilerRoomRecordRepo

export const BOILER_ROOM_DRAFT_STATUSES = DRAFT_STATUSES
