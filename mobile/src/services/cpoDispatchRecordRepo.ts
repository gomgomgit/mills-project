import { query, run } from '@/services/localDb'

/**
 * cpoDispatchRecordRepo — screen-064--monitor-cpo-dispatch /
 * screen-074--form-cpo-dispatch / screen-084--data-preview-cpo-dispatch.
 *
 * Local (offline) read/write access to the `cpo_dispatch_record` /
 * `cpo_dispatch_detail` tables (schema in localSchema.ts). Mirrors
 * kernelDispatchRecordRepo.ts's repo style closely — CPO Dispatch is
 * also a pure EVENT-LOG station: detail rows are added manually per
 * occurrence (unbounded per day, no grid/N-column concept, no per-row
 * time-slot uniqueness/ascending constraint).
 *
 * Monitor screen semantics (business_logic step 1) mirror Kernel
 * Dispatch: this station shows (a) how many events have been logged TODAY
 * (`getTodaySummary()`), and (b) a summary of the single most-recently-logged
 * event across all of the user's records (`getLastEventSummary()`) — there
 * is no "progress toward a target" metric to show for a free event log.
 *
 * Unlike Kernel Dispatch, CPO Dispatch has no gate-status/enum detail
 * column and no weighbridge_ticket_no field — it instead has Time In/Time
 * Out (two separate time columns) and DOBI.
 */

export type CpoDispatchDraftStatus = 'draft_ongoing' | 'draft_paused'
export type CpoDispatchRecordStatus = CpoDispatchDraftStatus | 'saved' | 'synced'

export interface CpoDispatchRecord {
  id: string
  station_id: string | null
  cpo_dispatch_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  checked_by_name?: string | null
  acknowledged_by_name?: string | null
  status: CpoDispatchRecordStatus
  created_by: string
  created_at: string
  updated_at: string
}

export interface CpoDispatchDetailRow {
  id: string
  cpo_dispatch_record_id: string
  event_date: string | null
  shift: string | null
  time_in: string | null
  time_out: string | null
  waybill_number: string | null
  tanker_plate_no: string | null
  transport_company: string | null
  driver_name: string | null
  storage_tank_source: string | null
  seal_no_top: string | null
  seal_no_bottom: string | null
  gross_weight_mt: number | null
  tare_weight_mt: number | null
  net_weight_mt: number | null
  ffa_percent: number | null
  moisture_percent: number | null
  impurities_percent: number | null
  dobi: number | null
  destination_buyer: string | null
  weighbridge_operator: string | null
  findings: string | null
  created_at: string
  updated_at: string
}

export interface CpoDispatchDraftWithDetails {
  record: CpoDispatchRecord
  details: CpoDispatchDetailRow[]
}

/**
 * Header fields actually collected on Form CPO Dispatch (excludes
 * id/station_id/created_by/created_at/updated_at/status, managed by
 * createDraft()/saveDraft() itself). `date` is always rendered disabled
 * (auto-set-once-if-new), same convention as Form Kernel Dispatch's
 * date field.
 */
export interface CpoDispatchHeaderFormData {
  cpo_dispatch_id: string
  date: string
  note: string
  checked_by: string
  acknowledged_by: string
}

/**
 * One editable detail row as held in form state before saving. `id` present
 * for rows loaded from an existing draft (UPDATE target), absent for rows
 * added this session (INSERT target). `net_weight_mt` is (re)computed by
 * the caller from gross/tare immediately before save, same "computed fresh
 * right before save" pattern as KernelDispatchDetailFormRow.
 */
export interface CpoDispatchDetailFormRow {
  id?: string
  event_date: string | null
  shift: string | null
  time_in: string | null
  time_out: string | null
  waybill_number: string | null
  tanker_plate_no: string | null
  transport_company: string | null
  driver_name: string | null
  storage_tank_source: string | null
  seal_no_top: string | null
  seal_no_bottom: string | null
  gross_weight_mt: number | null
  tare_weight_mt: number | null
  net_weight_mt: number | null
  ffa_percent: number | null
  moisture_percent: number | null
  impurities_percent: number | null
  dobi: number | null
  destination_buyer: string | null
  weighbridge_operator: string | null
  findings: string | null
}

export type CpoDispatchActorRole = 'operator' | 'supervisor' | 'mill_management' | 'admin'

/**
 * Thrown by saveDraft() when zero rows in `detailRows` are "valid" (an
 * event_date filled in) — mirrors KernelDispatchDetailRequiredError.
 */
export class CpoDispatchDetailRequiredError extends Error {
  constructor() {
    super('Minimal 1 baris log CPO Dispatch harus diisi sebelum menyimpan.')
    this.name = 'CpoDispatchDetailRequiredError'
  }
}

export interface CurrentDraft {
  id: string
  status: CpoDispatchDraftStatus
}

export interface CpoDispatchTodaySummary {
  countRecords: number
  countEvents: number
}

export interface CpoDispatchLastEvent {
  recordId: string
  eventDate: string | null
  destinationBuyer: string | null
  tankerPlateNo: string | null
}

export interface CpoDispatchDraftListItem {
  id: string
  status: CpoDispatchDraftStatus
  cpo_dispatch_id: string | null
  updated_at: string
}

interface DraftListRow {
  id: string
  status: CpoDispatchDraftStatus
  cpo_dispatch_id: string | null
  updated_at: string
}

interface TodayRecordIdRow {
  id: string
}

interface CountRow {
  count: number
}

interface LastEventRow {
  cpo_dispatch_record_id: string
  event_date: string | null
  destination_buyer: string | null
  tanker_plate_no: string | null
}

const DRAFT_STATUSES: CpoDispatchDraftStatus[] = ['draft_ongoing', 'draft_paused']

function nowIso(): string {
  return new Date().toISOString()
}

function todayDateString(): string {
  return new Date().toISOString().slice(0, 10)
}

function generateId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `cd-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function generateDetailId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `cdd-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

/**
 * business_logic step 5 — 'New Data' (Monitor CPO Dispatch). INSERTs a
 * new draft record with status=draft_ongoing, created_by=current user,
 * `date` auto-filled once to today (entity-catalog: "date diisi otomatis
 * sekali saat draft baru dibuat, tidak dapat diedit manual").
 */
export async function createDraft(userId: string): Promise<string> {
  const id = generateId()
  const timestamp = nowIso()

  await run(
    `INSERT INTO cpo_dispatch_record (id, status, created_by, date, created_at, updated_at)
     VALUES (?, 'draft_ongoing', ?, ?, ?, ?)`,
    [id, userId, todayDateString(), timestamp, timestamp],
  )

  return id
}

/**
 * business_logic step 1 — "Hari Ini" counters: count of records dated
 * today (any status), and count of detail rows (events) belonging to those
 * records — a pure activity count, not a "progress toward N" metric.
 */
export async function getTodaySummary(userId: string): Promise<CpoDispatchTodaySummary> {
  const idRows = await query<TodayRecordIdRow>(
    `SELECT id
     FROM cpo_dispatch_record
     WHERE created_by = ? AND date(date) = date('now', 'localtime')`,
    [userId],
  )

  const countRecords = idRows.length

  if (countRecords === 0) {
    return { countRecords: 0, countEvents: 0 }
  }

  const placeholders = idRows.map(() => '?').join(', ')

  const countRows = await query<CountRow>(
    `SELECT COUNT(*) AS count
     FROM cpo_dispatch_detail
     WHERE cpo_dispatch_record_id IN (${placeholders})`,
    idRows.map((row) => row.id),
  )

  return {
    countRecords,
    countEvents: countRows[0]?.count ?? 0,
  }
}

/**
 * business_logic step 1 (extended) — the single most-recently-logged event
 * across all of the current user's records (any status, any date), so
 * Monitor can show a "last event" summary card rather than a tipping/
 * progress metric. Returns null when the user has no records or no detail
 * rows at all yet.
 */
export async function getLastEventSummary(userId: string): Promise<CpoDispatchLastEvent | null> {
  const rows = await query<LastEventRow>(
    `SELECT d.cpo_dispatch_record_id, d.event_date, d.destination_buyer, d.tanker_plate_no
     FROM cpo_dispatch_detail d
     INNER JOIN cpo_dispatch_record r ON r.id = d.cpo_dispatch_record_id
     WHERE r.created_by = ?
     ORDER BY d.created_at DESC
     LIMIT 1`,
    [userId],
  )

  const row = rows[0]

  if (!row) {
    return null
  }

  return {
    recordId: row.cpo_dispatch_record_id,
    eventDate: row.event_date,
    destinationBuyer: row.destination_buyer,
    tankerPlateNo: row.tanker_plate_no,
  }
}

/**
 * business_logic step 2 — every local record the current user has ongoing
 * or paused, most-recently-updated first. Mirrors
 * kernelDispatchRecordRepo's getDrafts().
 */
export async function getDrafts(userId: string): Promise<CpoDispatchDraftListItem[]> {
  const rows = await query<DraftListRow>(
    `SELECT id, status, cpo_dispatch_id, updated_at
     FROM cpo_dispatch_record
     WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
     ORDER BY updated_at DESC`,
    [userId],
  )

  return rows.map((row) => ({
    id: row.id,
    status: row.status,
    cpo_dispatch_id: row.cpo_dispatch_id,
    updated_at: row.updated_at,
  }))
}

/**
 * Every local record for the current user, ANY status, most-recently
 * updated first — Data Preview CPO Dispatch's LIST mode.
 */
export async function getAllRecords(userId: string): Promise<CpoDispatchRecord[]> {
  return query<CpoDispatchRecord>(
    `SELECT * FROM cpo_dispatch_record WHERE created_by = ? ORDER BY updated_at DESC`,
    [userId],
  )
}

/**
 * Loads a `cpo_dispatch_record` header row plus all of its detail
 * (event-log) rows, ordered by creation order (insertion order = the order
 * events were added). Returns null when no header row matches.
 */
export async function getDraftWithDetails(recordId: string): Promise<CpoDispatchDraftWithDetails | null> {
  const recordRows = await query<CpoDispatchRecord>(
    `SELECT * FROM cpo_dispatch_record WHERE id = ?`,
    [recordId],
  )
  const record = recordRows[0]

  if (!record) {
    return null
  }

  const details = await query<CpoDispatchDetailRow>(
    `SELECT * FROM cpo_dispatch_detail WHERE cpo_dispatch_record_id = ? ORDER BY created_at ASC`,
    [recordId],
  )

  return { record, details }
}

/**
 * Upserts every row in `rows` against `cpo_dispatch_detail` (rows with
 * an existing `id` UPDATEd, rows without one INSERTed with a freshly
 * generated id), then DELETEs every id in `idsToDelete`. Shared by
 * saveDraft() and pauseDraftWithFormData() — mirrors
 * kernelDispatchRecordRepo.ts's applyDetailRowChanges().
 */
async function applyDetailRowChanges(
  recordId: string,
  rows: CpoDispatchDetailFormRow[],
  idsToDelete: string[],
  timestamp: string,
): Promise<void> {
  for (const row of rows) {
    const params = [
      row.event_date || null,
      row.shift || null,
      row.time_in || null,
      row.time_out || null,
      row.waybill_number || null,
      row.tanker_plate_no || null,
      row.transport_company || null,
      row.driver_name || null,
      row.storage_tank_source || null,
      row.seal_no_top || null,
      row.seal_no_bottom || null,
      row.gross_weight_mt,
      row.tare_weight_mt,
      row.net_weight_mt,
      row.ffa_percent,
      row.moisture_percent,
      row.impurities_percent,
      row.dobi,
      row.destination_buyer || null,
      row.weighbridge_operator || null,
      row.findings || null,
    ]

    if (row.id) {
      await run(
        `UPDATE cpo_dispatch_detail
         SET event_date = ?, shift = ?, time_in = ?, time_out = ?, waybill_number = ?, tanker_plate_no = ?,
             transport_company = ?, driver_name = ?, storage_tank_source = ?, seal_no_top = ?, seal_no_bottom = ?,
             gross_weight_mt = ?, tare_weight_mt = ?, net_weight_mt = ?, ffa_percent = ?,
             moisture_percent = ?, impurities_percent = ?, dobi = ?, destination_buyer = ?,
             weighbridge_operator = ?, findings = ?, updated_at = ?
         WHERE id = ?`,
        [...params, timestamp, row.id],
      )
      continue
    }

    const detailId = generateDetailId()

    await run(
      `INSERT INTO cpo_dispatch_detail
         (id, cpo_dispatch_record_id, event_date, shift, time_in, time_out, waybill_number,
          tanker_plate_no, transport_company, driver_name, storage_tank_source, seal_no_top,
          seal_no_bottom, gross_weight_mt, tare_weight_mt, net_weight_mt, ffa_percent,
          moisture_percent, impurities_percent, dobi, destination_buyer, weighbridge_operator,
          findings, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [detailId, recordId, ...params, timestamp, timestamp],
    )
  }

  for (const id of idsToDelete) {
    await run(`DELETE FROM cpo_dispatch_detail WHERE id = ?`, [id])
  }
}

function stripRoleGatedFields(
  headerData: CpoDispatchHeaderFormData,
  currentUserRole: CpoDispatchActorRole | null | undefined,
): { checkedBy: string | null; acknowledgedBy: string | null } {
  const isSupervisor = currentUserRole === 'supervisor'
  const isMillManagement = currentUserRole === 'mill_management'

  return {
    checkedBy: isSupervisor && headerData.checked_by ? headerData.checked_by : null,
    acknowledgedBy: isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null,
  }
}

/**
 * business_logic — 'Simpan'. Throws CpoDispatchDetailRequiredError when
 * zero rows in `detailRows` have an event_date. On success: UPDATEs the
 * header row (status='saved'), then upserts `detailRows` via
 * applyDetailRowChanges().
 */
export async function saveDraft(
  recordId: string,
  headerData: CpoDispatchHeaderFormData,
  detailRows: CpoDispatchDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: CpoDispatchActorRole | null | undefined,
): Promise<CpoDispatchDraftWithDetails> {
  const validRowCount = detailRows.filter((row) => row.event_date !== null && row.event_date !== '').length

  if (validRowCount === 0) {
    throw new CpoDispatchDetailRequiredError()
  }

  const timestamp = nowIso()
  const { checkedBy, acknowledgedBy } = stripRoleGatedFields(headerData, currentUserRole)

  await run(
    `UPDATE cpo_dispatch_record
     SET cpo_dispatch_id = ?, date = ?, note = ?, checked_by = ?, acknowledged_by = ?,
         status = 'saved', updated_at = ?
     WHERE id = ?`,
    [headerData.cpo_dispatch_id || null, headerData.date || null, headerData.note || null, checkedBy, acknowledgedBy, timestamp, recordId],
  )

  await applyDetailRowChanges(recordId, detailRows, detailIdsToDelete, timestamp)

  const saved = await getDraftWithDetails(recordId)

  if (!saved) {
    throw new Error('Gagal memuat ulang data cpo dispatch setelah disimpan.')
  }

  return saved
}

/**
 * business_logic — 'Pause'. UPDATEs every header field as-is, then sets
 * status='draft_paused' — NO required-field validation.
 */
export async function pauseDraftWithFormData(
  recordId: string,
  headerData: CpoDispatchHeaderFormData,
  detailRows: CpoDispatchDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: CpoDispatchActorRole | null | undefined,
): Promise<CpoDispatchDraftWithDetails> {
  const timestamp = nowIso()
  const { checkedBy, acknowledgedBy } = stripRoleGatedFields(headerData, currentUserRole)

  await run(
    `UPDATE cpo_dispatch_record
     SET cpo_dispatch_id = ?, date = ?, note = ?, checked_by = ?, acknowledged_by = ?,
         status = 'draft_paused', updated_at = ?
     WHERE id = ?`,
    [headerData.cpo_dispatch_id || null, headerData.date || null, headerData.note || null, checkedBy, acknowledgedBy, timestamp, recordId],
  )

  await applyDetailRowChanges(recordId, detailRows, detailIdsToDelete, timestamp)

  const saved = await getDraftWithDetails(recordId)

  if (!saved) {
    throw new Error('Gagal menyimpan progres cpo dispatch setelah pause.')
  }

  return saved
}

/**
 * 'Clear' (after UI-level confirm). Permanently DELETEs the record,
 * cascading the delete to its child detail rows first (application-level
 * cascade, same as kernelDispatchRecordRepo.ts's deleteDraft()).
 */
export async function deleteDraft(recordId: string): Promise<void> {
  await run(`DELETE FROM cpo_dispatch_detail WHERE cpo_dispatch_record_id = ?`, [recordId])
  await run(`DELETE FROM cpo_dispatch_record WHERE id = ?`, [recordId])
}

export const cpoDispatchRecordRepo = {
  createDraft,
  getTodaySummary,
  getLastEventSummary,
  getDrafts,
  getAllRecords,
  getDraftWithDetails,
  saveDraft,
  pauseDraftWithFormData,
  deleteDraft,
}

export default cpoDispatchRecordRepo

export const CPO_DISPATCH_DRAFT_STATUSES = DRAFT_STATUSES
