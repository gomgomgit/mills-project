import { query, run } from '@/services/localDb'

/**
 * processWaterRecordRepo — screen-062--monitor-process-water /
 * usecase-067--monitor-process-water, screen-072--form-process-water /
 * usecase-068--form-process-water, screen-082--data-preview-process-water /
 * usecase-069--data-preview-process-water. Local (offline) read/write
 * access to the `process_water_record` / `process_water_detail` tables
 * (schema defined in localSchema.ts) — mirrors threshingRecordRepo.ts's
 * repo style (plain async functions over localDb.ts's `query`/`run`
 * primitives) exactly, since Process Water follows the same hourly-grid
 * (dynamic add-row/remove-row, 1..24 rows) pattern as Threshing.
 *
 * `createDraft()` inserts ONLY the header row — no detail rows at all.
 * Rows are added one at a time via `applyDetailRowChanges()` (called from
 * `saveDraft()`/`pauseDraftWithFormData()`, mirrors
 * threshingRecordRepo.ts's `applyDetailRowChanges()`): rows with an
 * existing `id` are UPDATEd, rows without one are INSERTed with a freshly
 * generated id via `generateDetailId()`, and every id in the
 * caller-supplied `idsToDelete` list is DELETEd after the upserts.
 * `canonicalTimeSlots()` is the source of truth for the dropdown's ordered
 * option list (FormProcessWaterView.vue's `availableTimeSlotOptions()`)
 * and for `getDraftWithDetails()`'s sort order.
 *
 * UNLIKE threshingRecordRepo.ts: this station has NO operational-target
 * reference table — no static-constant data file, no Target Operasional
 * section on any of its 3 mobile screens.
 *
 * "Filled" row, for the required-row validation only (not a DB
 * constraint): a process_water_detail row counts as filled when at least
 * one of its reading columns (raw_water_flow_m3h, clarified_water_flow_m3h,
 * softener_inlet_ph, softener_outlet_hardness_ppm, alum_dosing_kgh,
 * polymer_dosing_gh, boiler_feed_tank_temp_c, boiler_feed_water_ph,
 * boiler_feed_tds_ppm, action_taken_status, findings) is non-null/non-empty
 * — `shift` and `inspector_id` are identifying/context columns, not
 * reading columns, so they are excluded from this check (mirrors
 * Threshing's exclusion of `time_slot` itself from its own isRowFilled()).
 */

export type ProcessWaterDraftStatus = 'draft_ongoing' | 'draft_paused'
export type ProcessWaterRecordStatus = ProcessWaterDraftStatus | 'saved' | 'synced'

/**
 * Full local `process_water_record` row shape (mirrors localSchema.ts's
 * CREATE_PROCESS_WATER_RECORD column-for-column).
 */
export interface ProcessWaterRecord {
  id: string
  station_id: string | null
  process_water_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  checked_by_name?: string | null
  acknowledged_by_name?: string | null
  status: ProcessWaterRecordStatus
  created_by: string
  created_at: string
  updated_at: string
}

/**
 * Full local `process_water_detail` row shape (mirrors localSchema.ts's
 * CREATE_PROCESS_WATER_DETAIL column-for-column).
 */
export interface ProcessWaterDetailRow {
  id: string
  process_water_record_id: string
  time_slot: string
  shift: string | null
  inspector_id: string | null
  raw_water_flow_m3h: number | null
  clarified_water_flow_m3h: number | null
  softener_inlet_ph: number | null
  softener_outlet_hardness_ppm: number | null
  alum_dosing_kgh: number | null
  polymer_dosing_gh: number | null
  boiler_feed_tank_temp_c: number | null
  boiler_feed_water_ph: number | null
  boiler_feed_tds_ppm: number | null
  action_taken_status: string | null
  findings: string | null
  created_at: string
  updated_at: string
}

export interface ProcessWaterDraftWithDetails {
  record: ProcessWaterRecord
  details: ProcessWaterDetailRow[]
}

/**
 * Subset of `ProcessWaterRecord` columns the Form Process Water screen
 * collects from the user (excludes id/station_id/created_by/created_at/
 * updated_at/status, which are managed by createDraft()/saveDraft()
 * itself).
 */
export interface ProcessWaterHeaderFormData {
  process_water_id: string
  date: string
  note: string
  checked_by: string
  acknowledged_by: string
}

/**
 * One editable `process_water_detail` row as held in
 * FormProcessWaterView.vue's form state. `id` is present for rows loaded
 * from an existing draft (UPDATE target) and absent for rows added in this
 * editing session (INSERT target) — `saveDraft()`/
 * `pauseDraftWithFormData()` upsert on exactly this distinction, mirroring
 * ThreshingDetailFormRow exactly. `time_slot` is `null` until the user
 * picks one from the dropdown for a newly-added row.
 */
export interface ProcessWaterDetailFormRow {
  id?: string
  time_slot: string | null
  shift: string | null
  inspector_id: string | null
  raw_water_flow_m3h: number | null
  clarified_water_flow_m3h: number | null
  softener_inlet_ph: number | null
  softener_outlet_hardness_ppm: number | null
  alum_dosing_kgh: number | null
  polymer_dosing_gh: number | null
  boiler_feed_tank_temp_c: number | null
  boiler_feed_water_ph: number | null
  boiler_feed_tds_ppm: number | null
  action_taken_status: string | null
  findings: string | null
}

/**
 * User roles a saveDraft()/pauseDraftWithFormData() caller may pass as
 * currentUserRole — plain string union, matching
 * threshingRecordRepo.ts's ThreshingActorRole, decoupling this repo from
 * the auth store.
 */
export type ProcessWaterActorRole = 'operator' | 'supervisor' | 'mill_management' | 'admin'

/**
 * screen-072--form-process-water business_logic step 12 — thrown by
 * saveDraft() when zero rows in the given `details` are "valid" (a
 * selected `time_slot` AND at least one filled reading column), so callers
 * (FormProcessWaterView.vue) can distinguish this from other save
 * failures — mirrors ThreshingDetailRequiredError exactly.
 */
export class ProcessWaterDetailRequiredError extends Error {
  constructor() {
    super(
      'Minimal 1 baris Process Water Detail (Time-Slot terpilih + minimal 1 kolom bacaan terisi) harus diisi sebelum menyimpan.',
    )
    this.name = 'ProcessWaterDetailRequiredError'
  }
}

export interface ProcessWaterDraftListItem {
  id: string
  status: ProcessWaterDraftStatus
  process_water_id: string | null
  updated_at: string
}

interface DraftListRow {
  id: string
  status: ProcessWaterDraftStatus
  process_water_id: string | null
  updated_at: string
}

export interface ProcessWaterTodaySummary {
  countProcessWaterRecord: number
  detailRowCount: number
}

interface TodayRecordIdRow {
  id: string
}

interface DetailRowCountRow {
  detail_row_count: number
}

const DRAFT_STATUSES: ProcessWaterDraftStatus[] = ['draft_ongoing', 'draft_paused']

function nowIso(): string {
  return new Date().toISOString()
}

function generateId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `pwr-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function generateDetailId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `pwd-${Date.now()}-${Math.random().toString(16).slice(2)}`
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
 * Returns true when a process_water_detail row has at least one of its
 * reading columns non-null/non-empty — see this file's header comment for
 * the "filled row" definition. `shift`/`inspector_id` are deliberately
 * excluded.
 */
function isRowFilled(row: {
  raw_water_flow_m3h: number | null
  clarified_water_flow_m3h: number | null
  softener_inlet_ph: number | null
  softener_outlet_hardness_ppm: number | null
  alum_dosing_kgh: number | null
  polymer_dosing_gh: number | null
  boiler_feed_tank_temp_c: number | null
  boiler_feed_water_ph: number | null
  boiler_feed_tds_ppm: number | null
  action_taken_status: string | null
  findings: string | null
}): boolean {
  return (
    row.raw_water_flow_m3h !== null ||
    row.clarified_water_flow_m3h !== null ||
    row.softener_inlet_ph !== null ||
    row.softener_outlet_hardness_ppm !== null ||
    row.alum_dosing_kgh !== null ||
    row.polymer_dosing_gh !== null ||
    row.boiler_feed_tank_temp_c !== null ||
    row.boiler_feed_water_ph !== null ||
    row.boiler_feed_tds_ppm !== null ||
    (row.action_taken_status !== null && row.action_taken_status !== '') ||
    (row.findings !== null && row.findings !== '')
  )
}

/**
 * screen-062--monitor-process-water business_logic step 5 — 'New Data'.
 * INSERTs a new process_water_record row ONLY (status=draft_ongoing,
 * created_by=current user, date=now — auto-filled once at creation,
 * mirrors threshingRecordRepo.ts's createDraft()) — no
 * process_water_detail rows are pre-created; rows are added one at a time
 * by the user via "Tambah baris" in FormProcessWaterView.vue. Returns the
 * new record's id so the caller can navigate to Form Process Water with
 * it.
 */
export async function createDraft(userId: string): Promise<string> {
  const id = generateId()
  const timestamp = nowIso()

  await run(
    `INSERT INTO process_water_record (id, status, created_by, date, created_at, updated_at)
     VALUES (?, 'draft_ongoing', ?, ?, ?, ?)`,
    [id, userId, timestamp, timestamp, timestamp],
  )

  return id
}

/**
 * screen-062--monitor-process-water business_logic step 2 — every local
 * process_water_record the current user has ongoing or paused,
 * most-recently-updated first. Mirrors threshingRecordRepo.ts's
 * getDrafts().
 */
export async function getDrafts(userId: string): Promise<ProcessWaterDraftListItem[]> {
  const rows = await query<DraftListRow>(
    `SELECT id, status, process_water_id, updated_at
     FROM process_water_record
     WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
     ORDER BY updated_at DESC`,
    [userId],
  )

  return rows.map((row) => ({
    id: row.id,
    status: row.status,
    process_water_id: row.process_water_id,
    updated_at: row.updated_at,
  }))
}

/**
 * screen-062--monitor-process-water business_logic step 1 — "Hari Ini"
 * counters. "Jumlah Process Water Record" counts the current user's
 * process_water_record rows (any status) dated today (device-local day
 * boundary). "Jumlah Baris Time-Slot Tercatat" is a plain COUNT of
 * process_water_detail rows belonging to those same records — mirrors
 * threshingRecordRepo.ts's getTodaySummary() exactly.
 */
export async function getTodaySummary(userId: string): Promise<ProcessWaterTodaySummary> {
  const idRows = await query<TodayRecordIdRow>(
    `SELECT id
     FROM process_water_record
     WHERE created_by = ? AND date(date) = date('now', 'localtime')`,
    [userId],
  )

  const countProcessWaterRecord = idRows.length

  if (countProcessWaterRecord === 0) {
    return { countProcessWaterRecord: 0, detailRowCount: 0 }
  }

  const placeholders = idRows.map(() => '?').join(', ')

  const countRows = await query<DetailRowCountRow>(
    `SELECT COUNT(*) AS detail_row_count FROM process_water_detail WHERE process_water_record_id IN (${placeholders})`,
    idRows.map((row) => row.id),
  )

  return {
    countProcessWaterRecord,
    detailRowCount: countRows[0]?.detail_row_count ?? 0,
  }
}

/**
 * screen-082--data-preview-process-water business_logic step 1 — every
 * local process_water_record row for the current user, ANY status,
 * most-recently-updated first. Mirrors threshingRecordRepo.ts's
 * getAllRecords().
 */
export async function getAllRecords(userId: string): Promise<ProcessWaterRecord[]> {
  return query<ProcessWaterRecord>(
    `SELECT * FROM process_water_record WHERE created_by = ? ORDER BY updated_at DESC`,
    [userId],
  )
}

/**
 * screen-072--form-process-water business_logic step 1 — Form Process
 * Water loads an existing draft by route param id, plus all of its
 * process_water_detail rows (however many the user has added so far),
 * ordered by time_slot ascending starting at 07:00 (canonical order, not
 * alphabetical — '00:00' would otherwise sort before '07:00'). Returns
 * null when no header row matches (defensive).
 */
export async function getDraftWithDetails(recordId: string): Promise<ProcessWaterDraftWithDetails | null> {
  const recordRows = await query<ProcessWaterRecord>(`SELECT * FROM process_water_record WHERE id = ?`, [recordId])
  const record = recordRows[0]

  if (!record) {
    return null
  }

  const rawDetails = await query<ProcessWaterDetailRow>(
    `SELECT * FROM process_water_detail WHERE process_water_record_id = ?`,
    [recordId],
  )

  const order = canonicalTimeSlots()
  const details = [...rawDetails].sort((a, b) => order.indexOf(a.time_slot) - order.indexOf(b.time_slot))

  return { record, details }
}

/**
 * screen-072--form-process-water — upserts every row in `rows` against
 * `process_water_detail` (rows with an existing `id` UPDATEd in place,
 * rows without one INSERTed with a freshly generated id via
 * `generateDetailId()`), then DELETEs every id in `idsToDelete` (rows the
 * user removed via "Hapus baris" on an already-loaded draft). Shared by
 * `saveDraft()` and `pauseDraftWithFormData()` so both stay in lock-step
 * on this upsert/delete contract — mirrors threshingRecordRepo.ts's
 * `applyDetailRowChanges()` exactly.
 */
async function applyDetailRowChanges(
  recordId: string,
  rows: ProcessWaterDetailFormRow[],
  idsToDelete: string[],
  timestamp: string,
): Promise<void> {
  for (const row of rows) {
    if (row.id) {
      await run(
        `UPDATE process_water_detail
         SET time_slot = ?,
             shift = ?,
             inspector_id = ?,
             raw_water_flow_m3h = ?,
             clarified_water_flow_m3h = ?,
             softener_inlet_ph = ?,
             softener_outlet_hardness_ppm = ?,
             alum_dosing_kgh = ?,
             polymer_dosing_gh = ?,
             boiler_feed_tank_temp_c = ?,
             boiler_feed_water_ph = ?,
             boiler_feed_tds_ppm = ?,
             action_taken_status = ?,
             findings = ?,
             updated_at = ?
         WHERE id = ?`,
        [
          row.time_slot,
          row.shift || null,
          row.inspector_id || null,
          row.raw_water_flow_m3h,
          row.clarified_water_flow_m3h,
          row.softener_inlet_ph,
          row.softener_outlet_hardness_ppm,
          row.alum_dosing_kgh,
          row.polymer_dosing_gh,
          row.boiler_feed_tank_temp_c,
          row.boiler_feed_water_ph,
          row.boiler_feed_tds_ppm,
          row.action_taken_status || null,
          row.findings || null,
          timestamp,
          row.id,
        ],
      )
      continue
    }

    const detailId = generateDetailId()

    await run(
      `INSERT INTO process_water_detail
         (id, process_water_record_id, time_slot, shift, inspector_id, raw_water_flow_m3h, clarified_water_flow_m3h, softener_inlet_ph, softener_outlet_hardness_ppm, alum_dosing_kgh, polymer_dosing_gh, boiler_feed_tank_temp_c, boiler_feed_water_ph, boiler_feed_tds_ppm, action_taken_status, findings, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        detailId,
        recordId,
        row.time_slot,
        row.shift || null,
        row.inspector_id || null,
        row.raw_water_flow_m3h,
        row.clarified_water_flow_m3h,
        row.softener_inlet_ph,
        row.softener_outlet_hardness_ppm,
        row.alum_dosing_kgh,
        row.polymer_dosing_gh,
        row.boiler_feed_tank_temp_c,
        row.boiler_feed_water_ph,
        row.boiler_feed_tds_ppm,
        row.action_taken_status || null,
        row.findings || null,
        timestamp,
        timestamp,
      ],
    )
  }

  for (const id of idsToDelete) {
    await run(`DELETE FROM process_water_detail WHERE id = ?`, [id])
  }
}

/**
 * screen-072--form-process-water business_logic step 12 — 'Simpan'.
 *
 * Throws ProcessWaterDetailRequiredError when zero rows in the given
 * `details` are "valid" (a selected `time_slot` AND at least one filled
 * reading column — see isRowFilled()) — enforced here (not only as a
 * client-side pre-check in FormProcessWaterView.vue) so the rule holds
 * even if a caller bypasses the UI. No DB write happens at all in that
 * case. Mirrors threshingRecordRepo.ts's saveDraft() "at least one valid
 * row" gate exactly. Required-header-field validation (process_water_id)
 * is the caller's job — this function assumes the header has already been
 * validated.
 *
 * checked_by/acknowledged_by role-stripping: mirrors
 * threshingRecordRepo.ts's saveDraft() — whenever currentUserRole is not
 * 'supervisor', headerData.checked_by is ignored (null written instead);
 * whenever not 'mill_management', headerData.acknowledged_by is likewise
 * ignored.
 *
 * On success: UPDATEs the header row (status='saved'), then applies
 * `details`'s upserts and `detailIdsToDelete`'s deletes via
 * applyDetailRowChanges(). Sequential run() calls, not a single
 * transaction (see this file's header comment). Returns the freshly saved
 * draft (re-read via getDraftWithDetails()).
 */
export async function saveDraft(
  recordId: string,
  headerData: ProcessWaterHeaderFormData,
  details: ProcessWaterDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: ProcessWaterActorRole | null | undefined,
): Promise<ProcessWaterDraftWithDetails> {
  const validRowCount = details.filter(
    (row) => row.time_slot !== null && row.time_slot !== undefined && row.time_slot !== '' && isRowFilled(row),
  ).length

  if (validRowCount === 0) {
    throw new ProcessWaterDetailRequiredError()
  }

  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE process_water_record
     SET process_water_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'saved',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.process_water_id || null,
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
    throw new Error('Gagal memuat ulang data process water setelah disimpan.')
  }

  return saved
}

/**
 * screen-072--form-process-water business_logic step 13 — 'Pause'.
 * UPDATEs the header as-is (no required-field validation, no "at least
 * one valid row" gate), status='draft_paused', then applies the same
 * upsert/delete contract as saveDraft() via applyDetailRowChanges(). Same
 * role-stripping as saveDraft(). Returns the freshly paused draft.
 */
export async function pauseDraftWithFormData(
  recordId: string,
  headerData: ProcessWaterHeaderFormData,
  details: ProcessWaterDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: ProcessWaterActorRole | null | undefined,
): Promise<ProcessWaterDraftWithDetails> {
  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE process_water_record
     SET process_water_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'draft_paused',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.process_water_id || null,
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
    throw new Error('Gagal menyimpan progres process water setelah pause.')
  }

  return saved
}

/**
 * screen-072--form-process-water business_logic step 14 — 'Clear' (after
 * UI confirm). Permanently DELETEs the record, cascading the delete to
 * all of its process_water_detail rows first (application-level cascade,
 * same pattern as threshingRecordRepo.ts's deleteDraft()).
 */
export async function deleteDraft(recordId: string): Promise<void> {
  await run(`DELETE FROM process_water_detail WHERE process_water_record_id = ?`, [recordId])
  await run(`DELETE FROM process_water_record WHERE id = ?`, [recordId])
}

export const processWaterRecordRepo = {
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

export default processWaterRecordRepo

export const PROCESS_WATER_DRAFT_STATUSES = DRAFT_STATUSES
