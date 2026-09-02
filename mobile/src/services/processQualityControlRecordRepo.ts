import { query, run } from '@/services/localDb'

/**
 * processQualityControlRecordRepo — screen-070--monitor-process-quality-control /
 * usecase-115--monitor-process-quality-control, screen-080--form-process-quality-control /
 * usecase-116--form-process-quality-control, screen-090--data-preview-process-quality-control /
 * usecase-117--data-preview-process-quality-control. Local (offline) read/write access
 * to the `process_quality_control_record` / `process_quality_control_detail` tables (schema
 * defined in localSchema.ts) — mirrors clarificationRecordRepo.ts's repo style
 * (plain async functions over localDb.ts's `query`/`run` primitives)
 * exactly, since Process Quality Control follows the same hourly-grid (dynamic
 * add-row/remove-row, 1..24 rows) pattern as Clarification/Boiler Room/Engine Room.
 *
 * `createDraft()` inserts ONLY the header row — no detail rows at all. Rows
 * are added one at a time via `applyDetailRowChanges()` (called from
 * `saveDraft()`/`pauseDraftWithFormData()`, mirrors
 * clarificationRecordRepo.ts's `applyDetailRowChanges()`): rows with an
 * existing `id` are UPDATEd, rows without one are INSERTed with a freshly
 * generated id via `generateDetailId()`, and every id in the
 * caller-supplied `idsToDelete` list is DELETEd after the upserts.
 * `canonicalTimeSlots()` is the source of truth for the dropdown's ordered
 * option list (FormProcessQualityControlView.vue's `availableTimeSlotOptions()`)
 * and for `getDraftWithDetails()`'s sort order.
 *
 * THIS STATION HAS THE MOST NON-TIME_SLOT COLUMNS IN THE PROJECT (16): NO
 * operational-target reference table, NO enum columns — but `shift` and
 * `qc_inspector_id` ARE identifying/context columns (like Process Water's
 * shift/inspector_id), excluded from the "filled" check below.
 *
 * "Filled" row, for the required-row validation only (not a DB constraint):
 * a process_quality_control_detail row counts as filled when at least one
 * of its 14 reading/keterangan columns (12 numeric readings across Fruit
 * Press/Purifier & Clarification Balance/Vacuum Drying
 * Station/Decanter-Centrifuge/Final Storage, plus
 * qc_engineering_corrective_actions and findings) is non-null/non-empty —
 * `shift` and `qc_inspector_id` do NOT participate.
 */

export type ProcessQualityControlDraftStatus = 'draft_ongoing' | 'draft_paused'
export type ProcessQualityControlRecordStatus = ProcessQualityControlDraftStatus | 'saved' | 'synced'

/**
 * Full local `process_quality_control_record` row shape (mirrors
 * localSchema.ts's CREATE_PROCESS_QUALITY_CONTROL_RECORD column-for-column).
 */
export interface ProcessQualityControlRecord {
  id: string
  station_id: string | null
  process_qc_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  status: ProcessQualityControlRecordStatus
  created_by: string
  created_at: string
  updated_at: string
}

/**
 * Full local `process_quality_control_detail` row shape (mirrors
 * localSchema.ts's CREATE_PROCESS_QUALITY_CONTROL_DETAIL column-for-column).
 */
export interface ProcessQualityControlDetailRow {
  id: string
  process_quality_control_record_id: string
  time_slot: string
  shift: string | null
  fruit_press_oil_loss_in_sludge_percent: number | null
  fruit_press_oil_loss_in_fibre_percent: number | null
  purifier_clarification_balance_inlet_temp_c: number | null
  purifier_clarification_balance_backpressure_bar: number | null
  vacuum_drying_station_drier_temp_c: number | null
  vacuum_drying_station_vacuum_pressure_bar: number | null
  decanter_centrifuge_feed_rate_mth: number | null
  decanter_centrifuge_oil_loss_in_cake_percent: number | null
  final_storage_ffa_percent: number | null
  final_storage_moisture_content_percent: number | null
  final_storage_impurities_dirt_percent: number | null
  final_storage_dobi_index: number | null
  qc_inspector_id: string | null
  qc_engineering_corrective_actions: string | null
  findings: string | null
  created_at: string
  updated_at: string
}

export interface ProcessQualityControlDraftWithDetails {
  record: ProcessQualityControlRecord
  details: ProcessQualityControlDetailRow[]
}

/**
 * Subset of `ProcessQualityControlRecord` columns the Form Process Quality
 * Control screen collects from the user (excludes id/station_id/created_by/
 * created_at/updated_at/status, which are managed by createDraft()/
 * saveDraft() itself).
 */
export interface ProcessQualityControlHeaderFormData {
  process_qc_id: string
  date: string
  note: string
  checked_by: string
  acknowledged_by: string
}

/**
 * One editable `process_quality_control_detail` row as held in
 * FormProcessQualityControlView.vue's form state. `id` is present for rows
 * loaded from an existing draft (UPDATE target) and absent for rows added
 * in this editing session (INSERT target) — `saveDraft()`/
 * `pauseDraftWithFormData()` upsert on exactly this distinction, mirroring
 * ClarificationDetailFormRow exactly. `time_slot` is `null` until the user
 * picks one from the dropdown for a newly-added row.
 */
export interface ProcessQualityControlDetailFormRow {
  id?: string
  time_slot: string | null
  shift: string | null
  fruit_press_oil_loss_in_sludge_percent: number | null
  fruit_press_oil_loss_in_fibre_percent: number | null
  purifier_clarification_balance_inlet_temp_c: number | null
  purifier_clarification_balance_backpressure_bar: number | null
  vacuum_drying_station_drier_temp_c: number | null
  vacuum_drying_station_vacuum_pressure_bar: number | null
  decanter_centrifuge_feed_rate_mth: number | null
  decanter_centrifuge_oil_loss_in_cake_percent: number | null
  final_storage_ffa_percent: number | null
  final_storage_moisture_content_percent: number | null
  final_storage_impurities_dirt_percent: number | null
  final_storage_dobi_index: number | null
  qc_inspector_id: string | null
  qc_engineering_corrective_actions: string | null
  findings: string | null
}

/**
 * User roles a saveDraft()/pauseDraftWithFormData() caller may pass as
 * currentUserRole — plain string union, matching
 * clarificationRecordRepo.ts's ClarificationActorRole, decoupling this repo
 * from the auth store.
 */
export type ProcessQualityControlActorRole = 'operator' | 'supervisor' | 'mill_management' | 'admin'

/**
 * screen-080--form-process-quality-control business_logic step 6 — thrown by
 * saveDraft() when zero rows in the given `details` are "valid" (a selected
 * `time_slot` AND at least one filled reading/keterangan column, excluding
 * shift/qc_inspector_id), so callers (FormProcessQualityControlView.vue)
 * can distinguish this from other save failures — mirrors
 * ClarificationDetailRequiredError exactly.
 */
export class ProcessQualityControlDetailRequiredError extends Error {
  constructor() {
    super(
      'Minimal 1 baris Process Quality Control Detail (Time-Slot terpilih + minimal 1 kolom bacaan/keterangan terisi) harus diisi sebelum menyimpan.',
    )
    this.name = 'ProcessQualityControlDetailRequiredError'
  }
}

export interface ProcessQualityControlDraftListItem {
  id: string
  status: ProcessQualityControlDraftStatus
  process_qc_id: string | null
  updated_at: string
}

interface DraftListRow {
  id: string
  status: ProcessQualityControlDraftStatus
  process_qc_id: string | null
  updated_at: string
}

export interface ProcessQualityControlTodaySummary {
  countProcessQualityControlRecord: number
  detailRowCount: number
}

interface TodayRecordIdRow {
  id: string
}

interface DetailRowCountRow {
  detail_row_count: number
}

const DRAFT_STATUSES: ProcessQualityControlDraftStatus[] = ['draft_ongoing', 'draft_paused']

function nowIso(): string {
  return new Date().toISOString()
}

function generateId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `pqc-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function generateDetailId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `pqcd-${Date.now()}-${Math.random().toString(16).slice(2)}`
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
 * Returns true when a process_quality_control_detail row has at least one
 * of its 14 reading/keterangan columns non-null/non-empty — see this
 * file's header comment for the "filled row" definition (`shift` and
 * `qc_inspector_id` are identifying/context columns and do NOT
 * participate).
 */
function isRowFilled(row: {
  fruit_press_oil_loss_in_sludge_percent: number | null
  fruit_press_oil_loss_in_fibre_percent: number | null
  purifier_clarification_balance_inlet_temp_c: number | null
  purifier_clarification_balance_backpressure_bar: number | null
  vacuum_drying_station_drier_temp_c: number | null
  vacuum_drying_station_vacuum_pressure_bar: number | null
  decanter_centrifuge_feed_rate_mth: number | null
  decanter_centrifuge_oil_loss_in_cake_percent: number | null
  final_storage_ffa_percent: number | null
  final_storage_moisture_content_percent: number | null
  final_storage_impurities_dirt_percent: number | null
  final_storage_dobi_index: number | null
  qc_engineering_corrective_actions: string | null
  findings: string | null
}): boolean {
  return (
    row.fruit_press_oil_loss_in_sludge_percent !== null ||
    row.fruit_press_oil_loss_in_fibre_percent !== null ||
    row.purifier_clarification_balance_inlet_temp_c !== null ||
    row.purifier_clarification_balance_backpressure_bar !== null ||
    row.vacuum_drying_station_drier_temp_c !== null ||
    row.vacuum_drying_station_vacuum_pressure_bar !== null ||
    row.decanter_centrifuge_feed_rate_mth !== null ||
    row.decanter_centrifuge_oil_loss_in_cake_percent !== null ||
    row.final_storage_ffa_percent !== null ||
    row.final_storage_moisture_content_percent !== null ||
    row.final_storage_impurities_dirt_percent !== null ||
    row.final_storage_dobi_index !== null ||
    (row.qc_engineering_corrective_actions !== null && row.qc_engineering_corrective_actions !== '') ||
    (row.findings !== null && row.findings !== '')
  )
}

/**
 * screen-070--monitor-process-quality-control business_logic step 5 — 'New
 * Data'. INSERTs a new process_quality_control_record row ONLY
 * (status=draft_ongoing, created_by=current user, date=now — auto-filled
 * once at creation, mirrors clarificationRecordRepo.ts's createDraft()) —
 * no process_quality_control_detail rows are pre-created; rows are added
 * one at a time by the user via "Tambah baris" in
 * FormProcessQualityControlView.vue. Returns the new record's id so the
 * caller can navigate to Form Process Quality Control with it.
 */
export async function createDraft(userId: string): Promise<string> {
  const id = generateId()
  const timestamp = nowIso()

  await run(
    `INSERT INTO process_quality_control_record (id, status, created_by, date, created_at, updated_at)
     VALUES (?, 'draft_ongoing', ?, ?, ?, ?)`,
    [id, userId, timestamp, timestamp, timestamp],
  )

  return id
}

/**
 * screen-070--monitor-process-quality-control business_logic step 2 — every
 * local process_quality_control_record the current user has ongoing or
 * paused, most-recently-updated first. Mirrors
 * clarificationRecordRepo.ts's getDrafts().
 */
export async function getDrafts(userId: string): Promise<ProcessQualityControlDraftListItem[]> {
  const rows = await query<DraftListRow>(
    `SELECT id, status, process_qc_id, updated_at
     FROM process_quality_control_record
     WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
     ORDER BY updated_at DESC`,
    [userId],
  )

  return rows.map((row) => ({
    id: row.id,
    status: row.status,
    process_qc_id: row.process_qc_id,
    updated_at: row.updated_at,
  }))
}

/**
 * screen-070--monitor-process-quality-control business_logic step 1 —
 * "Hari Ini" counters. "Jumlah Process Quality Control Record" counts the
 * current user's process_quality_control_record rows (any status) dated
 * today (device-local day boundary). "Jumlah Baris Time-Slot Tercatat" is
 * a plain COUNT of process_quality_control_detail rows belonging to those
 * same records — mirrors clarificationRecordRepo.ts's getTodaySummary()
 * exactly.
 */
export async function getTodaySummary(userId: string): Promise<ProcessQualityControlTodaySummary> {
  const idRows = await query<TodayRecordIdRow>(
    `SELECT id
     FROM process_quality_control_record
     WHERE created_by = ? AND date(date) = date('now', 'localtime')`,
    [userId],
  )

  const countProcessQualityControlRecord = idRows.length

  if (countProcessQualityControlRecord === 0) {
    return { countProcessQualityControlRecord: 0, detailRowCount: 0 }
  }

  const placeholders = idRows.map(() => '?').join(', ')

  const countRows = await query<DetailRowCountRow>(
    `SELECT COUNT(*) AS detail_row_count FROM process_quality_control_detail WHERE process_quality_control_record_id IN (${placeholders})`,
    idRows.map((row) => row.id),
  )

  return {
    countProcessQualityControlRecord,
    detailRowCount: countRows[0]?.detail_row_count ?? 0,
  }
}

/**
 * screen-090--data-preview-process-quality-control business_logic step 1 —
 * every local process_quality_control_record row for the current user, ANY
 * status, most-recently-updated first. Mirrors clarificationRecordRepo.ts's
 * getAllRecords().
 */
export async function getAllRecords(userId: string): Promise<ProcessQualityControlRecord[]> {
  return query<ProcessQualityControlRecord>(
    `SELECT * FROM process_quality_control_record WHERE created_by = ? ORDER BY updated_at DESC`,
    [userId],
  )
}

/**
 * screen-080--form-process-quality-control business_logic step 1 — Form
 * Process Quality Control loads an existing draft by route param id, plus
 * all of its process_quality_control_detail rows (however many the user has
 * added so far), ordered by time_slot ascending starting at 07:00
 * (canonical order, not alphabetical — '00:00' would otherwise sort before
 * '07:00'). Returns null when no header row matches (defensive).
 */
export async function getDraftWithDetails(recordId: string): Promise<ProcessQualityControlDraftWithDetails | null> {
  const recordRows = await query<ProcessQualityControlRecord>(
    `SELECT * FROM process_quality_control_record WHERE id = ?`,
    [recordId],
  )
  const record = recordRows[0]

  if (!record) {
    return null
  }

  const rawDetails = await query<ProcessQualityControlDetailRow>(
    `SELECT * FROM process_quality_control_detail WHERE process_quality_control_record_id = ?`,
    [recordId],
  )

  const order = canonicalTimeSlots()
  const details = [...rawDetails].sort((a, b) => order.indexOf(a.time_slot) - order.indexOf(b.time_slot))

  return { record, details }
}

/**
 * screen-080--form-process-quality-control — upserts every row in `rows`
 * against `process_quality_control_detail` (rows with an existing `id`
 * UPDATEd in place, rows without one INSERTed with a freshly generated id
 * via `generateDetailId()`), then DELETEs every id in `idsToDelete` (rows
 * the user removed via "Hapus baris" on an already-loaded draft). Shared
 * by `saveDraft()` and `pauseDraftWithFormData()` so both stay in
 * lock-step on this upsert/delete contract — mirrors
 * clarificationRecordRepo.ts's `applyDetailRowChanges()` exactly.
 */
async function applyDetailRowChanges(
  recordId: string,
  rows: ProcessQualityControlDetailFormRow[],
  idsToDelete: string[],
  timestamp: string,
): Promise<void> {
  for (const row of rows) {
    if (row.id) {
      await run(
        `UPDATE process_quality_control_detail
         SET time_slot = ?,
             shift = ?,
             fruit_press_oil_loss_in_sludge_percent = ?,
             fruit_press_oil_loss_in_fibre_percent = ?,
             purifier_clarification_balance_inlet_temp_c = ?,
             purifier_clarification_balance_backpressure_bar = ?,
             vacuum_drying_station_drier_temp_c = ?,
             vacuum_drying_station_vacuum_pressure_bar = ?,
             decanter_centrifuge_feed_rate_mth = ?,
             decanter_centrifuge_oil_loss_in_cake_percent = ?,
             final_storage_ffa_percent = ?,
             final_storage_moisture_content_percent = ?,
             final_storage_impurities_dirt_percent = ?,
             final_storage_dobi_index = ?,
             qc_inspector_id = ?,
             qc_engineering_corrective_actions = ?,
             findings = ?,
             updated_at = ?
         WHERE id = ?`,
        [
          row.time_slot,
          row.shift || null,
          row.fruit_press_oil_loss_in_sludge_percent,
          row.fruit_press_oil_loss_in_fibre_percent,
          row.purifier_clarification_balance_inlet_temp_c,
          row.purifier_clarification_balance_backpressure_bar,
          row.vacuum_drying_station_drier_temp_c,
          row.vacuum_drying_station_vacuum_pressure_bar,
          row.decanter_centrifuge_feed_rate_mth,
          row.decanter_centrifuge_oil_loss_in_cake_percent,
          row.final_storage_ffa_percent,
          row.final_storage_moisture_content_percent,
          row.final_storage_impurities_dirt_percent,
          row.final_storage_dobi_index,
          row.qc_inspector_id || null,
          row.qc_engineering_corrective_actions || null,
          row.findings || null,
          timestamp,
          row.id,
        ],
      )
      continue
    }

    const detailId = generateDetailId()

    await run(
      `INSERT INTO process_quality_control_detail
         (id, process_quality_control_record_id, time_slot, shift, fruit_press_oil_loss_in_sludge_percent, fruit_press_oil_loss_in_fibre_percent, purifier_clarification_balance_inlet_temp_c, purifier_clarification_balance_backpressure_bar, vacuum_drying_station_drier_temp_c, vacuum_drying_station_vacuum_pressure_bar, decanter_centrifuge_feed_rate_mth, decanter_centrifuge_oil_loss_in_cake_percent, final_storage_ffa_percent, final_storage_moisture_content_percent, final_storage_impurities_dirt_percent, final_storage_dobi_index, qc_inspector_id, qc_engineering_corrective_actions, findings, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        detailId,
        recordId,
        row.time_slot,
        row.shift || null,
        row.fruit_press_oil_loss_in_sludge_percent,
        row.fruit_press_oil_loss_in_fibre_percent,
        row.purifier_clarification_balance_inlet_temp_c,
        row.purifier_clarification_balance_backpressure_bar,
        row.vacuum_drying_station_drier_temp_c,
        row.vacuum_drying_station_vacuum_pressure_bar,
        row.decanter_centrifuge_feed_rate_mth,
        row.decanter_centrifuge_oil_loss_in_cake_percent,
        row.final_storage_ffa_percent,
        row.final_storage_moisture_content_percent,
        row.final_storage_impurities_dirt_percent,
        row.final_storage_dobi_index,
        row.qc_inspector_id || null,
        row.qc_engineering_corrective_actions || null,
        row.findings || null,
        timestamp,
        timestamp,
      ],
    )
  }

  for (const id of idsToDelete) {
    await run(`DELETE FROM process_quality_control_detail WHERE id = ?`, [id])
  }
}

/**
 * screen-080--form-process-quality-control business_logic step 6 —
 * 'Simpan'.
 *
 * Throws ProcessQualityControlDetailRequiredError when zero rows in the
 * given `details` are "valid" (a selected `time_slot` AND at least one
 * filled reading/keterangan column — see isRowFilled()) — enforced here
 * (not only as a client-side pre-check in
 * FormProcessQualityControlView.vue) so the rule holds even if a caller
 * bypasses the UI. No DB write happens at all in that case. Mirrors
 * clarificationRecordRepo.ts's saveDraft() "at least one valid row" gate
 * exactly. Required-header-field validation (process_qc_id) is the
 * caller's job — this function assumes the header has already been
 * validated.
 *
 * checked_by/acknowledged_by role-stripping: mirrors
 * clarificationRecordRepo.ts's saveDraft() — whenever currentUserRole is
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
  headerData: ProcessQualityControlHeaderFormData,
  details: ProcessQualityControlDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: ProcessQualityControlActorRole | null | undefined,
): Promise<ProcessQualityControlDraftWithDetails> {
  const validRowCount = details.filter(
    (row) => row.time_slot !== null && row.time_slot !== undefined && row.time_slot !== '' && isRowFilled(row),
  ).length

  if (validRowCount === 0) {
    throw new ProcessQualityControlDetailRequiredError()
  }

  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE process_quality_control_record
     SET process_qc_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'saved',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.process_qc_id || null,
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
    throw new Error('Gagal memuat ulang data process quality control setelah disimpan.')
  }

  return saved
}

/**
 * screen-080--form-process-quality-control business_logic step 9 —
 * 'Pause'. UPDATEs the header as-is (no required-field validation, no "at
 * least one valid row" gate), status='draft_paused', then applies the same
 * upsert/delete contract as saveDraft() via applyDetailRowChanges(). Same
 * role-stripping as saveDraft(). Returns the freshly paused draft.
 */
export async function pauseDraftWithFormData(
  recordId: string,
  headerData: ProcessQualityControlHeaderFormData,
  details: ProcessQualityControlDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: ProcessQualityControlActorRole | null | undefined,
): Promise<ProcessQualityControlDraftWithDetails> {
  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE process_quality_control_record
     SET process_qc_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'draft_paused',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.process_qc_id || null,
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
    throw new Error('Gagal menyimpan progres process quality control setelah pause.')
  }

  return saved
}

/**
 * screen-080--form-process-quality-control business_logic step 10 —
 * 'Clear' (after UI confirm). Permanently DELETEs the record, cascading
 * the delete to all of its process_quality_control_detail rows first
 * (application-level cascade, same pattern as
 * clarificationRecordRepo.ts's deleteDraft()).
 */
export async function deleteDraft(recordId: string): Promise<void> {
  await run(`DELETE FROM process_quality_control_detail WHERE process_quality_control_record_id = ?`, [recordId])
  await run(`DELETE FROM process_quality_control_record WHERE id = ?`, [recordId])
}

export const processQualityControlRecordRepo = {
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

export default processQualityControlRecordRepo

export const PROCESS_QUALITY_CONTROL_DRAFT_STATUSES = DRAFT_STATUSES
