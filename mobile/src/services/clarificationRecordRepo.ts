import { query, run } from '@/services/localDb'

/**
 * clarificationRecordRepo — screen-069--monitor-clarification /
 * usecase-109--monitor-clarification, screen-079--form-clarification /
 * usecase-110--form-clarification, screen-089--data-preview-clarification /
 * usecase-111--data-preview-clarification. Local (offline) read/write access
 * to the `clarification_record` / `clarification_detail` tables (schema
 * defined in localSchema.ts) — mirrors boilerRoomRecordRepo.ts's repo style
 * (plain async functions over localDb.ts's `query`/`run` primitives)
 * exactly, since Clarification follows the same hourly-grid (dynamic
 * add-row/remove-row, 1..24 rows) pattern as Boiler Room/Engine Room.
 *
 * `createDraft()` inserts ONLY the header row — no detail rows at all. Rows
 * are added one at a time via `applyDetailRowChanges()` (called from
 * `saveDraft()`/`pauseDraftWithFormData()`, mirrors
 * boilerRoomRecordRepo.ts's `applyDetailRowChanges()`): rows with an
 * existing `id` are UPDATEd, rows without one are INSERTed with a freshly
 * generated id via `generateDetailId()`, and every id in the
 * caller-supplied `idsToDelete` list is DELETEd after the upserts.
 * `canonicalTimeSlots()` is the source of truth for the dropdown's ordered
 * option list (FormClarificationView.vue's `availableTimeSlotOptions()`)
 * and for `getDraftWithDetails()`'s sort order.
 *
 * This is the SIMPLEST station in the whole project: NO operational-target
 * reference table, NO enum columns, NO free-text-unit columns — the 7
 * non-time_slot columns are 6 plain numeric readings plus `findings` (plain
 * text). No enum-coercion logic is needed anywhere in this file.
 *
 * "Filled" row, for the required-row validation only (not a DB constraint):
 * a clarification_detail row counts as filled when at least one of its 7
 * non-time_slot columns is non-null/non-empty — like Boiler Room/Engine
 * Room, this station has no identifying/context columns (no shift/
 * inspector_id equivalent), so all 7 columns participate.
 */

export type ClarificationDraftStatus = 'draft_ongoing' | 'draft_paused'
export type ClarificationRecordStatus = ClarificationDraftStatus | 'saved' | 'synced'

/**
 * Full local `clarification_record` row shape (mirrors localSchema.ts's
 * CREATE_CLARIFICATION_RECORD column-for-column).
 */
export interface ClarificationRecord {
  id: string
  station_id: string | null
  clarification_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  checked_by_name?: string | null
  acknowledged_by_name?: string | null
  status: ClarificationRecordStatus
  created_by: string
  created_at: string
  updated_at: string
}

/**
 * Full local `clarification_detail` row shape (mirrors localSchema.ts's
 * CREATE_CLARIFICATION_DETAIL column-for-column).
 */
export interface ClarificationDetailRow {
  id: string
  clarification_record_id: string
  time_slot: string
  clarification_tank_temp_c: number | null
  oil_tank_temperature_c: number | null
  sludge_tank_temp_c: number | null
  buffer_tank_level_percent: number | null
  pure_oil_production_rate_ton_hour: number | null
  downtime_mins: number | null
  findings: string | null
  created_at: string
  updated_at: string
}

export interface ClarificationDraftWithDetails {
  record: ClarificationRecord
  details: ClarificationDetailRow[]
}

/**
 * Subset of `ClarificationRecord` columns the Form Clarification screen
 * collects from the user (excludes id/station_id/created_by/created_at/
 * updated_at/status, which are managed by createDraft()/saveDraft() itself).
 */
export interface ClarificationHeaderFormData {
  clarification_id: string
  date: string
  note: string
  checked_by: string
  acknowledged_by: string
}

/**
 * One editable `clarification_detail` row as held in
 * FormClarificationView.vue's form state. `id` is present for rows loaded
 * from an existing draft (UPDATE target) and absent for rows added in this
 * editing session (INSERT target) — `saveDraft()`/
 * `pauseDraftWithFormData()` upsert on exactly this distinction, mirroring
 * BoilerRoomDetailFormRow exactly. `time_slot` is `null` until the user
 * picks one from the dropdown for a newly-added row.
 */
export interface ClarificationDetailFormRow {
  id?: string
  time_slot: string | null
  clarification_tank_temp_c: number | null
  oil_tank_temperature_c: number | null
  sludge_tank_temp_c: number | null
  buffer_tank_level_percent: number | null
  pure_oil_production_rate_ton_hour: number | null
  downtime_mins: number | null
  findings: string | null
}

/**
 * User roles a saveDraft()/pauseDraftWithFormData() caller may pass as
 * currentUserRole — plain string union, matching
 * boilerRoomRecordRepo.ts's BoilerRoomActorRole, decoupling this repo
 * from the auth store.
 */
export type ClarificationActorRole = 'operator' | 'supervisor' | 'mill_management' | 'admin'

/**
 * screen-079--form-clarification business_logic step 12 — thrown by
 * saveDraft() when zero rows in the given `details` are "valid" (a selected
 * `time_slot` AND at least one filled reading column), so callers
 * (FormClarificationView.vue) can distinguish this from other save failures
 * — mirrors BoilerRoomDetailRequiredError exactly.
 */
export class ClarificationDetailRequiredError extends Error {
  constructor() {
    super(
      'Minimal 1 baris Clarification Detail (Time-Slot terpilih + minimal 1 kolom bacaan terisi) harus diisi sebelum menyimpan.',
    )
    this.name = 'ClarificationDetailRequiredError'
  }
}

export interface ClarificationDraftListItem {
  id: string
  status: ClarificationDraftStatus
  clarification_id: string | null
  updated_at: string
}

interface DraftListRow {
  id: string
  status: ClarificationDraftStatus
  clarification_id: string | null
  updated_at: string
}

export interface ClarificationTodaySummary {
  countClarificationRecord: number
  detailRowCount: number
}

interface TodayRecordIdRow {
  id: string
}

interface DetailRowCountRow {
  detail_row_count: number
}

const DRAFT_STATUSES: ClarificationDraftStatus[] = ['draft_ongoing', 'draft_paused']

function nowIso(): string {
  return new Date().toISOString()
}

function generateId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `clr-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function generateDetailId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `cld-${Date.now()}-${Math.random().toString(16).slice(2)}`
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
 * Returns true when a clarification_detail row has at least one of its 7
 * non-time_slot columns non-null/non-empty — see this file's header comment
 * for the "filled row" definition (no identifying columns to exclude on
 * this station).
 */
function isRowFilled(row: {
  clarification_tank_temp_c: number | null
  oil_tank_temperature_c: number | null
  sludge_tank_temp_c: number | null
  buffer_tank_level_percent: number | null
  pure_oil_production_rate_ton_hour: number | null
  downtime_mins: number | null
  findings: string | null
}): boolean {
  return (
    row.clarification_tank_temp_c !== null ||
    row.oil_tank_temperature_c !== null ||
    row.sludge_tank_temp_c !== null ||
    row.buffer_tank_level_percent !== null ||
    row.pure_oil_production_rate_ton_hour !== null ||
    row.downtime_mins !== null ||
    (row.findings !== null && row.findings !== '')
  )
}

/**
 * screen-069--monitor-clarification business_logic step 5 — 'New Data'.
 * INSERTs a new clarification_record row ONLY (status=draft_ongoing,
 * created_by=current user, date=now — auto-filled once at creation, mirrors
 * boilerRoomRecordRepo.ts's createDraft()) — no clarification_detail rows
 * are pre-created; rows are added one at a time by the user via "Tambah
 * baris" in FormClarificationView.vue. Returns the new record's id so the
 * caller can navigate to Form Clarification with it.
 */
export async function createDraft(userId: string): Promise<string> {
  const id = generateId()
  const timestamp = nowIso()

  await run(
    `INSERT INTO clarification_record (id, status, created_by, date, created_at, updated_at)
     VALUES (?, 'draft_ongoing', ?, ?, ?, ?)`,
    [id, userId, timestamp, timestamp, timestamp],
  )

  return id
}

/**
 * screen-069--monitor-clarification business_logic step 2 — every local
 * clarification_record the current user has ongoing or paused,
 * most-recently-updated first. Mirrors boilerRoomRecordRepo.ts's
 * getDrafts().
 */
export async function getDrafts(userId: string): Promise<ClarificationDraftListItem[]> {
  const rows = await query<DraftListRow>(
    `SELECT id, status, clarification_id, updated_at
     FROM clarification_record
     WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
     ORDER BY updated_at DESC`,
    [userId],
  )

  return rows.map((row) => ({
    id: row.id,
    status: row.status,
    clarification_id: row.clarification_id,
    updated_at: row.updated_at,
  }))
}

/**
 * screen-069--monitor-clarification business_logic step 1 — "Hari Ini"
 * counters. "Jumlah Clarification Record" counts the current user's
 * clarification_record rows (any status) dated today (device-local day
 * boundary). "Jumlah Baris Time-Slot Tercatat" is a plain COUNT of
 * clarification_detail rows belonging to those same records — mirrors
 * boilerRoomRecordRepo.ts's getTodaySummary() exactly.
 */
export async function getTodaySummary(userId: string): Promise<ClarificationTodaySummary> {
  const idRows = await query<TodayRecordIdRow>(
    `SELECT id
     FROM clarification_record
     WHERE created_by = ? AND date(date) = date('now', 'localtime')`,
    [userId],
  )

  const countClarificationRecord = idRows.length

  if (countClarificationRecord === 0) {
    return { countClarificationRecord: 0, detailRowCount: 0 }
  }

  const placeholders = idRows.map(() => '?').join(', ')

  const countRows = await query<DetailRowCountRow>(
    `SELECT COUNT(*) AS detail_row_count FROM clarification_detail WHERE clarification_record_id IN (${placeholders})`,
    idRows.map((row) => row.id),
  )

  return {
    countClarificationRecord,
    detailRowCount: countRows[0]?.detail_row_count ?? 0,
  }
}

/**
 * screen-089--data-preview-clarification business_logic step 1 — every
 * local clarification_record row for the current user, ANY status,
 * most-recently-updated first. Mirrors boilerRoomRecordRepo.ts's
 * getAllRecords().
 */
export async function getAllRecords(userId: string): Promise<ClarificationRecord[]> {
  return query<ClarificationRecord>(
    `SELECT * FROM clarification_record WHERE created_by = ? ORDER BY updated_at DESC`,
    [userId],
  )
}

/**
 * screen-079--form-clarification business_logic step 1 — Form Clarification
 * loads an existing draft by route param id, plus all of its
 * clarification_detail rows (however many the user has added so far),
 * ordered by time_slot ascending starting at 07:00 (canonical order, not
 * alphabetical — '00:00' would otherwise sort before '07:00'). Returns null
 * when no header row matches (defensive).
 */
export async function getDraftWithDetails(recordId: string): Promise<ClarificationDraftWithDetails | null> {
  const recordRows = await query<ClarificationRecord>(`SELECT * FROM clarification_record WHERE id = ?`, [recordId])
  const record = recordRows[0]

  if (!record) {
    return null
  }

  const rawDetails = await query<ClarificationDetailRow>(
    `SELECT * FROM clarification_detail WHERE clarification_record_id = ?`,
    [recordId],
  )

  const order = canonicalTimeSlots()
  const details = [...rawDetails].sort((a, b) => order.indexOf(a.time_slot) - order.indexOf(b.time_slot))

  return { record, details }
}

/**
 * screen-079--form-clarification — upserts every row in `rows` against
 * `clarification_detail` (rows with an existing `id` UPDATEd in place, rows
 * without one INSERTed with a freshly generated id via
 * `generateDetailId()`), then DELETEs every id in `idsToDelete` (rows the
 * user removed via "Hapus baris" on an already-loaded draft). Shared by
 * `saveDraft()` and `pauseDraftWithFormData()` so both stay in lock-step on
 * this upsert/delete contract — mirrors boilerRoomRecordRepo.ts's
 * `applyDetailRowChanges()` exactly.
 */
async function applyDetailRowChanges(
  recordId: string,
  rows: ClarificationDetailFormRow[],
  idsToDelete: string[],
  timestamp: string,
): Promise<void> {
  for (const row of rows) {
    if (row.id) {
      await run(
        `UPDATE clarification_detail
         SET time_slot = ?,
             clarification_tank_temp_c = ?,
             oil_tank_temperature_c = ?,
             sludge_tank_temp_c = ?,
             buffer_tank_level_percent = ?,
             pure_oil_production_rate_ton_hour = ?,
             downtime_mins = ?,
             findings = ?,
             updated_at = ?
         WHERE id = ?`,
        [
          row.time_slot,
          row.clarification_tank_temp_c,
          row.oil_tank_temperature_c,
          row.sludge_tank_temp_c,
          row.buffer_tank_level_percent,
          row.pure_oil_production_rate_ton_hour,
          row.downtime_mins,
          row.findings || null,
          timestamp,
          row.id,
        ],
      )
      continue
    }

    const detailId = generateDetailId()

    await run(
      `INSERT INTO clarification_detail
         (id, clarification_record_id, time_slot, clarification_tank_temp_c, oil_tank_temperature_c, sludge_tank_temp_c, buffer_tank_level_percent, pure_oil_production_rate_ton_hour, downtime_mins, findings, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        detailId,
        recordId,
        row.time_slot,
        row.clarification_tank_temp_c,
        row.oil_tank_temperature_c,
        row.sludge_tank_temp_c,
        row.buffer_tank_level_percent,
        row.pure_oil_production_rate_ton_hour,
        row.downtime_mins,
        row.findings || null,
        timestamp,
        timestamp,
      ],
    )
  }

  for (const id of idsToDelete) {
    await run(`DELETE FROM clarification_detail WHERE id = ?`, [id])
  }
}

/**
 * screen-079--form-clarification business_logic step 12 — 'Simpan'.
 *
 * Throws ClarificationDetailRequiredError when zero rows in the given
 * `details` are "valid" (a selected `time_slot` AND at least one filled
 * reading column — see isRowFilled()) — enforced here (not only as a
 * client-side pre-check in FormClarificationView.vue) so the rule holds
 * even if a caller bypasses the UI. No DB write happens at all in that
 * case. Mirrors boilerRoomRecordRepo.ts's saveDraft() "at least one valid
 * row" gate exactly. Required-header-field validation (clarification_id)
 * is the caller's job — this function assumes the header has already been
 * validated.
 *
 * checked_by/acknowledged_by role-stripping: mirrors
 * boilerRoomRecordRepo.ts's saveDraft() — whenever currentUserRole is
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
  headerData: ClarificationHeaderFormData,
  details: ClarificationDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: ClarificationActorRole | null | undefined,
): Promise<ClarificationDraftWithDetails> {
  const validRowCount = details.filter(
    (row) => row.time_slot !== null && row.time_slot !== undefined && row.time_slot !== '' && isRowFilled(row),
  ).length

  if (validRowCount === 0) {
    throw new ClarificationDetailRequiredError()
  }

  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE clarification_record
     SET clarification_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'saved',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.clarification_id || null,
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
    throw new Error('Gagal memuat ulang data clarification setelah disimpan.')
  }

  return saved
}

/**
 * screen-079--form-clarification business_logic step 13 — 'Pause'. UPDATEs
 * the header as-is (no required-field validation, no "at least one valid
 * row" gate), status='draft_paused', then applies the same upsert/delete
 * contract as saveDraft() via applyDetailRowChanges(). Same role-stripping
 * as saveDraft(). Returns the freshly paused draft.
 */
export async function pauseDraftWithFormData(
  recordId: string,
  headerData: ClarificationHeaderFormData,
  details: ClarificationDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: ClarificationActorRole | null | undefined,
): Promise<ClarificationDraftWithDetails> {
  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE clarification_record
     SET clarification_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'draft_paused',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.clarification_id || null,
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
    throw new Error('Gagal menyimpan progres clarification setelah pause.')
  }

  return saved
}

/**
 * screen-079--form-clarification business_logic step 14 — 'Clear' (after UI
 * confirm). Permanently DELETEs the record, cascading the delete to all of
 * its clarification_detail rows first (application-level cascade, same
 * pattern as boilerRoomRecordRepo.ts's deleteDraft()).
 */
export async function deleteDraft(recordId: string): Promise<void> {
  await run(`DELETE FROM clarification_detail WHERE clarification_record_id = ?`, [recordId])
  await run(`DELETE FROM clarification_record WHERE id = ?`, [recordId])
}

export const clarificationRecordRepo = {
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

export default clarificationRecordRepo

export const CLARIFICATION_DRAFT_STATUSES = DRAFT_STATUSES
