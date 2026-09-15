import { query, run } from '@/services/localDb'

/**
 * pressingRecordRepo — screen-038--monitor-pressing /
 * usecase-038--monitor-pressing, screen-042--form-pressing /
 * usecase-042--form-pressing, screen-046--data-preview-pressing /
 * usecase-046--data-preview-pressing. Local (offline) read/write access to
 * the `pressing_record` / `pressing_detail` tables (schema defined in
 * localSchema.ts) — mirrors threshingRecordRepo.ts's repo style (plain
 * async functions over localDb.ts's `query`/`run` primitives) exactly,
 * adapted for Pressing's own header field (`presser_id`) and 6 reading
 * columns.
 *
 * REVISED (2026-08-24, entity-catalog v12): pressing_detail is now a
 * DYNAMIC add-row/remove-row grid — exactly the same pattern as
 * cagesTrackRecordRepo.ts's `cages_tipped_time` (`tippedTimeRows`/
 * `pendingDeletionIds` in FormCagesTrackView.vue) and threshingRecordRepo.ts
 * (Pressing's structural sibling, fixed moments earlier). This REPLACES the
 * original design (a FIXED set of exactly 24 pre-created rows, one per
 * hourly time-slot, inserted all at once by `createDraft()`), which the
 * user explicitly rejected as wasting screen space. `createDraft()` now
 * inserts ONLY the header row — no detail rows at all. Rows are added one
 * at a time via `applyDetailRowChanges()` (called from `saveDraft()`/
 * `pauseDraftWithFormData()`, mirrors threshingRecordRepo.ts's
 * `applyDetailRowChanges()`): rows with an existing `id` are UPDATEd,
 * rows without one are INSERTed with a freshly generated id via
 * `generateDetailId()`, and every id in the caller-supplied
 * `idsToDelete` list is DELETEd after the upserts. `canonicalTimeSlots()`
 * is KEPT — no longer used to pre-generate rows, but still the source of
 * truth for the dropdown's ordered option list (FormPressingView.vue's
 * `availableTimeSlotOptions()`) and for `getDraftWithDetails()`'s sort
 * order.
 *
 * "Filled" row, for the required-row validation only (not a DB constraint):
 * a pressing_detail row counts as filled when at least one of its 6 reading
 * columns (digester_temp_c, digester_level_percent,
 * press_motor_current_amps, cone_hydraulic_pressure_bar,
 * dilution_water_temp_c, downtime_reason) is non-null/non-empty. The
 * server-side PressingRecord::booted() `saving` guard ("minimal satu
 * pressing-detail sebelum status=saved") checks row EXISTENCE, satisfied
 * once at least one row has been added — the REAL "minimal satu baris
 * terisi" business rule (a selected `time_slot` AND at least one filled
 * reading column) is enforced at this repo level (`saveDraft()`) and again
 * in FormPressingView.vue's client-side validation, mirroring
 * threshingRecordRepo.ts's saveDraft() "at least one valid row" gate
 * exactly.
 */

export type PressingDraftStatus = 'draft_ongoing' | 'draft_paused'
export type PressingRecordStatus = PressingDraftStatus | 'saved' | 'synced'

/**
 * Full local `pressing_record` row shape (mirrors localSchema.ts's
 * CREATE_PRESSING_RECORD column-for-column).
 */
export interface PressingRecord {
  id: string
  station_id: string | null
  presser_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  checked_by_name?: string | null
  acknowledged_by_name?: string | null
  status: PressingRecordStatus
  created_by: string
  created_at: string
  updated_at: string
}

/**
 * Full local `pressing_detail` row shape (mirrors localSchema.ts's
 * CREATE_PRESSING_DETAIL column-for-column).
 */
export interface PressingDetailRow {
  id: string
  pressing_record_id: string
  time_slot: string
  digester_temp_c: number | null
  digester_level_percent: number | null
  press_motor_current_amps: number | null
  cone_hydraulic_pressure_bar: number | null
  dilution_water_temp_c: number | null
  downtime_reason: string | null
  created_at: string
  updated_at: string
}

export interface PressingDraftWithDetails {
  record: PressingRecord
  details: PressingDetailRow[]
}

/**
 * Subset of `PressingRecord` columns the Form Pressing screen collects
 * from the user (excludes id/station_id/created_by/created_at/updated_at/
 * status, which are managed by createDraft()/saveDraft() itself).
 */
export interface PressingHeaderFormData {
  presser_id: string
  date: string
  note: string
  checked_by: string
  acknowledged_by: string
}

/**
 * One editable `pressing_detail` row as held in FormPressingView.vue's
 * form state. `id` is present for rows loaded from an existing draft
 * (UPDATE target) and absent for rows added in this editing session (INSERT
 * target) — `saveDraft()`/`pauseDraftWithFormData()` upsert on exactly this
 * distinction, mirroring ThreshingDetailFormRow exactly. `time_slot` is
 * `null` until the user picks one from the dropdown for a newly-added row.
 */
export interface PressingDetailFormRow {
  id?: string
  time_slot: string | null
  digester_temp_c: number | null
  digester_level_percent: number | null
  press_motor_current_amps: number | null
  cone_hydraulic_pressure_bar: number | null
  dilution_water_temp_c: number | null
  downtime_reason: string | null
}

/**
 * User roles a saveDraft()/pauseDraftWithFormData() caller may pass as
 * currentUserRole — plain string union, matching
 * threshingRecordRepo.ts's ThreshingActorRole, decoupling this repo from
 * the auth store.
 */
export type PressingActorRole = 'operator' | 'supervisor' | 'mill_management' | 'admin'

/**
 * screen-042--form-pressing business_logic step 9 — thrown by saveDraft()
 * when zero rows in the given `details` are "valid" (a selected `time_slot`
 * AND at least one filled reading column), so callers
 * (FormPressingView.vue) can distinguish this from other save failures —
 * mirrors ThreshingDetailRequiredError exactly.
 */
export class PressingDetailRequiredError extends Error {
  constructor() {
    super('Minimal 1 baris Pressing Detail (Time-Slot terpilih + minimal 1 kolom bacaan terisi) harus diisi sebelum menyimpan.')
    this.name = 'PressingDetailRequiredError'
  }
}

export interface PressingDraftListItem {
  id: string
  status: PressingDraftStatus
  presser_id: string | null
  updated_at: string
}

interface DraftListRow {
  id: string
  status: PressingDraftStatus
  presser_id: string | null
  updated_at: string
}

export interface PressingTodaySummary {
  countPressingRecord: number
  detailRowCount: number
}

interface TodayRecordIdRow {
  id: string
}

interface DetailRowCountRow {
  detail_row_count: number
}

const DRAFT_STATUSES: PressingDraftStatus[] = ['draft_ongoing', 'draft_paused']

function nowIso(): string {
  return new Date().toISOString()
}

function generateId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `prs-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function generateDetailId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `prd-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

/**
 * The 24 canonical hourly time-slot labels, in order: 07:00, 08:00, ...,
 * 23:00, 00:00, ..., 06:00 (i.e. starting at hour 7, wrapping past midnight).
 * Shared by any caller needing the canonical order (e.g. the dropdown's
 * ordered option list, tests). `for i in 0..23 -> hour = (7 + i) % 24`.
 * Identical to threshingRecordRepo.ts's canonicalTimeSlots().
 */
export function canonicalTimeSlots(): string[] {
  return Array.from({ length: 24 }, (_, i) => {
    const hour = (7 + i) % 24
    return `${String(hour).padStart(2, '0')}:00`
  })
}

/**
 * Returns true when a pressing_detail row has at least one of its 6
 * reading columns non-null/non-empty — see this file's header comment for
 * the "filled row" definition.
 */
function isRowFilled(row: {
  digester_temp_c: number | null
  digester_level_percent: number | null
  press_motor_current_amps: number | null
  cone_hydraulic_pressure_bar: number | null
  dilution_water_temp_c: number | null
  downtime_reason: string | null
}): boolean {
  return (
    row.digester_temp_c !== null ||
    row.digester_level_percent !== null ||
    row.press_motor_current_amps !== null ||
    row.cone_hydraulic_pressure_bar !== null ||
    row.dilution_water_temp_c !== null ||
    (row.downtime_reason !== null && row.downtime_reason !== '')
  )
}

/**
 * screen-038--monitor-pressing business_logic step 5 — 'New Data'. INSERTs
 * a new pressing_record row ONLY (status=draft_ongoing, created_by=current
 * user, date=now — auto-filled once at creation, mirrors
 * cages_track_record.tippler_start_time) — no pressing_detail rows are
 * pre-created (2026-08-24 revision, entity-catalog v12: rows are added one
 * at a time by the user via "Tambah baris" in FormPressingView.vue, same
 * pattern as threshingRecordRepo.ts's `threshing_detail`). Returns the new
 * record's id so the caller can navigate to Form Pressing with it.
 */
export async function createDraft(userId: string): Promise<string> {
  const id = generateId()
  const timestamp = nowIso()

  await run(
    `INSERT INTO pressing_record (id, status, created_by, date, created_at, updated_at)
     VALUES (?, 'draft_ongoing', ?, ?, ?, ?)`,
    [id, userId, timestamp, timestamp, timestamp],
  )

  return id
}

/**
 * screen-038--monitor-pressing business_logic step 2 — every local
 * pressing_record the current user has ongoing or paused, most-recently-
 * updated first. Mirrors threshingRecordRepo.ts's getDrafts().
 */
export async function getDrafts(userId: string): Promise<PressingDraftListItem[]> {
  const rows = await query<DraftListRow>(
    `SELECT id, status, presser_id, updated_at
     FROM pressing_record
     WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
     ORDER BY updated_at DESC`,
    [userId],
  )

  return rows.map((row) => ({
    id: row.id,
    status: row.status,
    presser_id: row.presser_id,
    updated_at: row.updated_at,
  }))
}

/**
 * screen-038--monitor-pressing business_logic step 1 — "Hari Ini" counters.
 * "Jumlah Pressing Record" counts the current user's pressing_record rows
 * (any status) dated today (device-local day boundary). "Jumlah Baris
 * Time-Slot Tercatat" is a plain COUNT of pressing_detail rows belonging to
 * those same records — NOT filtered by isRowFilled() (2026-08-24 revision:
 * since rows are no longer pre-created, every existing row was explicitly
 * added by the user via "Tambah baris", so row EXISTENCE itself is the
 * meaningful signal now — mirrors threshingRecordRepo.ts's
 * getTodaySummary() `detailRowCount` "count of child rows added" shape,
 * rather than "filled rows out of a fixed 24"). Two sequential queries
 * (today's record ids, then a scoped COUNT), skipping the second query
 * entirely when there are no matching ids (an empty `IN ()` list is invalid
 * SQL) — same shape as threshingRecordRepo.ts's getTodaySummary().
 */
export async function getTodaySummary(userId: string): Promise<PressingTodaySummary> {
  const idRows = await query<TodayRecordIdRow>(
    `SELECT id
     FROM pressing_record
     WHERE created_by = ? AND date(date) = date('now', 'localtime')`,
    [userId],
  )

  const countPressingRecord = idRows.length

  if (countPressingRecord === 0) {
    return { countPressingRecord: 0, detailRowCount: 0 }
  }

  const placeholders = idRows.map(() => '?').join(', ')

  const countRows = await query<DetailRowCountRow>(
    `SELECT COUNT(*) AS detail_row_count FROM pressing_detail WHERE pressing_record_id IN (${placeholders})`,
    idRows.map((row) => row.id),
  )

  return {
    countPressingRecord,
    detailRowCount: countRows[0]?.detail_row_count ?? 0,
  }
}

/**
 * screen-046--data-preview-pressing business_logic step 1 — every local
 * pressing_record row for the current user, ANY status, most-recently-
 * updated first. Mirrors threshingRecordRepo.ts's getAllRecords().
 */
export async function getAllRecords(userId: string): Promise<PressingRecord[]> {
  return query<PressingRecord>(`SELECT * FROM pressing_record WHERE created_by = ? ORDER BY updated_at DESC`, [
    userId,
  ])
}

/**
 * screen-042--form-pressing business_logic step 1 — Form Pressing loads
 * an existing draft by route param id, plus all of its pressing_detail
 * rows (however many the user has added so far — no longer always 24),
 * ordered by time_slot ascending starting at 07:00 (canonical order, not
 * alphabetical — '00:00' would otherwise sort before '07:00'). Returns null
 * when no header row matches (defensive).
 */
export async function getDraftWithDetails(recordId: string): Promise<PressingDraftWithDetails | null> {
  const recordRows = await query<PressingRecord>(`SELECT * FROM pressing_record WHERE id = ?`, [recordId])
  const record = recordRows[0]

  if (!record) {
    return null
  }

  const rawDetails = await query<PressingDetailRow>(`SELECT * FROM pressing_detail WHERE pressing_record_id = ?`, [
    recordId,
  ])

  const order = canonicalTimeSlots()
  const details = [...rawDetails].sort((a, b) => order.indexOf(a.time_slot) - order.indexOf(b.time_slot))

  return { record, details }
}

/**
 * screen-042--form-pressing — upserts every row in `rows` against
 * `pressing_detail` (rows with an existing `id` UPDATEd in place, rows
 * without one INSERTed with a freshly generated id via
 * `generateDetailId()`), then DELETEs every id in `idsToDelete` (rows the
 * user removed via "Hapus baris" on an already-loaded draft). Shared by
 * `saveDraft()` and `pauseDraftWithFormData()` so both stay in lock-step on
 * this upsert/delete contract — mirrors threshingRecordRepo.ts's
 * `applyDetailRowChanges()` exactly.
 */
async function applyDetailRowChanges(
  recordId: string,
  rows: PressingDetailFormRow[],
  idsToDelete: string[],
  timestamp: string,
): Promise<void> {
  for (const row of rows) {
    if (row.id) {
      await run(
        `UPDATE pressing_detail
         SET time_slot = ?,
             digester_temp_c = ?,
             digester_level_percent = ?,
             press_motor_current_amps = ?,
             cone_hydraulic_pressure_bar = ?,
             dilution_water_temp_c = ?,
             downtime_reason = ?,
             updated_at = ?
         WHERE id = ?`,
        [
          row.time_slot,
          row.digester_temp_c,
          row.digester_level_percent,
          row.press_motor_current_amps,
          row.cone_hydraulic_pressure_bar,
          row.dilution_water_temp_c,
          row.downtime_reason || null,
          timestamp,
          row.id,
        ],
      )
      continue
    }

    const detailId = generateDetailId()

    await run(
      `INSERT INTO pressing_detail
         (id, pressing_record_id, time_slot, digester_temp_c, digester_level_percent, press_motor_current_amps, cone_hydraulic_pressure_bar, dilution_water_temp_c, downtime_reason, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        detailId,
        recordId,
        row.time_slot,
        row.digester_temp_c,
        row.digester_level_percent,
        row.press_motor_current_amps,
        row.cone_hydraulic_pressure_bar,
        row.dilution_water_temp_c,
        row.downtime_reason || null,
        timestamp,
        timestamp,
      ],
    )
  }

  for (const id of idsToDelete) {
    await run(`DELETE FROM pressing_detail WHERE id = ?`, [id])
  }
}

/**
 * screen-042--form-pressing business_logic step 9 — 'Simpan'.
 *
 * Throws PressingDetailRequiredError when zero rows in the given `details`
 * are "valid" (a selected `time_slot` AND at least one filled reading
 * column — see isRowFilled()) — enforced here (not only as a client-side
 * pre-check in FormPressingView.vue) so the rule holds even if a caller
 * bypasses the UI. No DB write happens at all in that case. Mirrors
 * threshingRecordRepo.ts's saveDraft() "at least one valid row" gate
 * exactly. Required-header-field validation (presser_id) is the caller's
 * job — this function assumes the header has already been validated.
 *
 * checked_by/acknowledged_by role-stripping: mirrors
 * threshingRecordRepo.ts's saveDraft() — whenever currentUserRole is not
 * 'supervisor', headerData.checked_by is ignored (null written instead);
 * whenever not 'mill_management', headerData.acknowledged_by is likewise
 * ignored.
 *
 * On success: UPDATEs the header row (status='saved'), then applies
 * `details`'s upserts and `detailIdsToDelete`'s deletes via
 * applyDetailRowChanges(). Sequential run() calls, not a single transaction
 * (see this file's header comment). Returns the freshly saved draft
 * (re-read via getDraftWithDetails()).
 */
export async function saveDraft(
  recordId: string,
  headerData: PressingHeaderFormData,
  details: PressingDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: PressingActorRole | null | undefined,
): Promise<PressingDraftWithDetails> {
  const validRowCount = details.filter(
    (row) => row.time_slot !== null && row.time_slot !== undefined && row.time_slot !== '' && isRowFilled(row),
  ).length

  if (validRowCount === 0) {
    throw new PressingDetailRequiredError()
  }

  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE pressing_record
     SET presser_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'saved',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.presser_id || null,
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
    throw new Error('Gagal memuat ulang data pressing setelah disimpan.')
  }

  return saved
}

/**
 * screen-042--form-pressing business_logic step 10 — 'Pause'. UPDATEs the
 * header as-is (no required-field validation, no "at least one valid row"
 * gate), status='draft_paused', then applies the same upsert/delete
 * contract as saveDraft() via applyDetailRowChanges(). Same role-stripping
 * as saveDraft(). Returns the freshly paused draft.
 */
export async function pauseDraftWithFormData(
  recordId: string,
  headerData: PressingHeaderFormData,
  details: PressingDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: PressingActorRole | null | undefined,
): Promise<PressingDraftWithDetails> {
  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE pressing_record
     SET presser_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'draft_paused',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.presser_id || null,
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
    throw new Error('Gagal menyimpan progres pressing setelah pause.')
  }

  return saved
}

/**
 * screen-042--form-pressing business_logic step 11 — 'Clear' (after UI
 * confirm). Permanently DELETEs the record, cascading the delete to all of
 * its pressing_detail rows first (application-level cascade, same pattern
 * as threshingRecordRepo.ts's deleteDraft()).
 */
export async function deleteDraft(recordId: string): Promise<void> {
  await run(`DELETE FROM pressing_detail WHERE pressing_record_id = ?`, [recordId])
  await run(`DELETE FROM pressing_record WHERE id = ?`, [recordId])
}

export const pressingRecordRepo = {
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

export default pressingRecordRepo

export const PRESSING_DRAFT_STATUSES = DRAFT_STATUSES
