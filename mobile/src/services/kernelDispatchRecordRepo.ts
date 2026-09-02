import { query, run } from '@/services/localDb'

/**
 * kernelDispatchRecordRepo — screen-063--monitor-kernel-dispatch /
 * screen-073--form-kernel-dispatch / screen-083--data-preview-kernel-dispatch.
 *
 * Local (offline) read/write access to the `kernel_dispatch_record` /
 * `kernel_dispatch_detail` tables (schema in localSchema.ts). Mirrors
 * solidWasteDisposalRecordRepo.ts's repo style closely — Kernel Dispatch is
 * also a pure EVENT-LOG station: detail rows are added manually per
 * occurrence (unbounded per day, no grid/N-column concept, no per-row
 * time-slot uniqueness/ascending constraint).
 *
 * Monitor screen semantics (business_logic step 1) mirror Solid Waste
 * Disposal: this station shows (a) how many events have been logged TODAY
 * (`getTodaySummary()`), and (b) a summary of the single most-recently-logged
 * event across all of the user's records (`getLastEventSummary()`) — there
 * is no "progress toward a target" metric to show for a free event log.
 */

export type KernelDispatchDraftStatus = 'draft_ongoing' | 'draft_paused'
export type KernelDispatchRecordStatus = KernelDispatchDraftStatus | 'saved' | 'synced'

export interface KernelDispatchRecord {
  id: string
  station_id: string | null
  kernel_dispatch_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  status: KernelDispatchRecordStatus
  created_by: string
  created_at: string
  updated_at: string
}

export interface KernelDispatchDetailRow {
  id: string
  kernel_dispatch_record_id: string
  event_date: string | null
  shift: string | null
  weighbridge_ticket_no: string | null
  waybill_number: string | null
  transporter_contractor: string | null
  vehicle_plate_no: string | null
  driver_name: string | null
  silo_source_id: string | null
  destination_buyer: string | null
  gross_weight_mt: number | null
  tare_weight_mt: number | null
  net_weight_mt: number | null
  kernel_moisture_percent: number | null
  dirt_impurities_percent: number | null
  ffa_percent: number | null
  broken_kernel_percent: number | null
  security_seal_no_top: string | null
  security_seal_no_bottom: string | null
  weighbridge_operator_id: string | null
  remarks_gate_status: string | null
  findings: string | null
  created_at: string
  updated_at: string
}

export interface KernelDispatchDraftWithDetails {
  record: KernelDispatchRecord
  details: KernelDispatchDetailRow[]
}

/**
 * Header fields actually collected on Form Kernel Dispatch (excludes
 * id/station_id/created_by/created_at/updated_at/status, managed by
 * createDraft()/saveDraft() itself). `date` is always rendered disabled
 * (auto-set-once-if-new), same convention as Form Solid Waste Disposal's
 * date field.
 */
export interface KernelDispatchHeaderFormData {
  kernel_dispatch_id: string
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
 * right before save" pattern as SolidWasteDisposalDetailFormRow.
 */
export interface KernelDispatchDetailFormRow {
  id?: string
  event_date: string | null
  shift: string | null
  weighbridge_ticket_no: string | null
  waybill_number: string | null
  transporter_contractor: string | null
  vehicle_plate_no: string | null
  driver_name: string | null
  silo_source_id: string | null
  destination_buyer: string | null
  gross_weight_mt: number | null
  tare_weight_mt: number | null
  net_weight_mt: number | null
  kernel_moisture_percent: number | null
  dirt_impurities_percent: number | null
  ffa_percent: number | null
  broken_kernel_percent: number | null
  security_seal_no_top: string | null
  security_seal_no_bottom: string | null
  weighbridge_operator_id: string | null
  remarks_gate_status: string | null
  findings: string | null
}

export type KernelDispatchActorRole = 'operator' | 'supervisor' | 'mill_management' | 'admin'

/**
 * Thrown by saveDraft() when zero rows in `detailRows` are "valid" (an
 * event_date filled in) — mirrors SolidWasteDisposalDetailRequiredError.
 */
export class KernelDispatchDetailRequiredError extends Error {
  constructor() {
    super('Minimal 1 baris log Kernel Dispatch harus diisi sebelum menyimpan.')
    this.name = 'KernelDispatchDetailRequiredError'
  }
}

export interface CurrentDraft {
  id: string
  status: KernelDispatchDraftStatus
}

export interface KernelDispatchTodaySummary {
  countRecords: number
  countEvents: number
}

export interface KernelDispatchLastEvent {
  recordId: string
  eventDate: string | null
  destinationBuyer: string | null
  vehiclePlateNo: string | null
}

export interface KernelDispatchDraftListItem {
  id: string
  status: KernelDispatchDraftStatus
  kernel_dispatch_id: string | null
  updated_at: string
}

interface DraftListRow {
  id: string
  status: KernelDispatchDraftStatus
  kernel_dispatch_id: string | null
  updated_at: string
}

interface TodayRecordIdRow {
  id: string
}

interface CountRow {
  count: number
}

interface LastEventRow {
  kernel_dispatch_record_id: string
  event_date: string | null
  destination_buyer: string | null
  vehicle_plate_no: string | null
}

const DRAFT_STATUSES: KernelDispatchDraftStatus[] = ['draft_ongoing', 'draft_paused']

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

  return `kd-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function generateDetailId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `kdd-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

/**
 * business_logic step 5 — 'New Data' (Monitor Kernel Dispatch). INSERTs a
 * new draft record with status=draft_ongoing, created_by=current user,
 * `date` auto-filled once to today (entity-catalog: "date diisi otomatis
 * sekali saat draft baru dibuat, tidak dapat diedit manual").
 */
export async function createDraft(userId: string): Promise<string> {
  const id = generateId()
  const timestamp = nowIso()

  await run(
    `INSERT INTO kernel_dispatch_record (id, status, created_by, date, created_at, updated_at)
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
export async function getTodaySummary(userId: string): Promise<KernelDispatchTodaySummary> {
  const idRows = await query<TodayRecordIdRow>(
    `SELECT id
     FROM kernel_dispatch_record
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
     FROM kernel_dispatch_detail
     WHERE kernel_dispatch_record_id IN (${placeholders})`,
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
export async function getLastEventSummary(userId: string): Promise<KernelDispatchLastEvent | null> {
  const rows = await query<LastEventRow>(
    `SELECT d.kernel_dispatch_record_id, d.event_date, d.destination_buyer, d.vehicle_plate_no
     FROM kernel_dispatch_detail d
     INNER JOIN kernel_dispatch_record r ON r.id = d.kernel_dispatch_record_id
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
    recordId: row.kernel_dispatch_record_id,
    eventDate: row.event_date,
    destinationBuyer: row.destination_buyer,
    vehiclePlateNo: row.vehicle_plate_no,
  }
}

/**
 * business_logic step 2 — every local record the current user has ongoing
 * or paused, most-recently-updated first. Mirrors
 * solidWasteDisposalRecordRepo's getDrafts().
 */
export async function getDrafts(userId: string): Promise<KernelDispatchDraftListItem[]> {
  const rows = await query<DraftListRow>(
    `SELECT id, status, kernel_dispatch_id, updated_at
     FROM kernel_dispatch_record
     WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
     ORDER BY updated_at DESC`,
    [userId],
  )

  return rows.map((row) => ({
    id: row.id,
    status: row.status,
    kernel_dispatch_id: row.kernel_dispatch_id,
    updated_at: row.updated_at,
  }))
}

/**
 * Every local record for the current user, ANY status, most-recently
 * updated first — Data Preview Kernel Dispatch's LIST mode.
 */
export async function getAllRecords(userId: string): Promise<KernelDispatchRecord[]> {
  return query<KernelDispatchRecord>(
    `SELECT * FROM kernel_dispatch_record WHERE created_by = ? ORDER BY updated_at DESC`,
    [userId],
  )
}

/**
 * Loads a `kernel_dispatch_record` header row plus all of its detail
 * (event-log) rows, ordered by creation order (insertion order = the order
 * events were added). Returns null when no header row matches.
 */
export async function getDraftWithDetails(recordId: string): Promise<KernelDispatchDraftWithDetails | null> {
  const recordRows = await query<KernelDispatchRecord>(
    `SELECT * FROM kernel_dispatch_record WHERE id = ?`,
    [recordId],
  )
  const record = recordRows[0]

  if (!record) {
    return null
  }

  const details = await query<KernelDispatchDetailRow>(
    `SELECT * FROM kernel_dispatch_detail WHERE kernel_dispatch_record_id = ? ORDER BY created_at ASC`,
    [recordId],
  )

  return { record, details }
}

/**
 * Upserts every row in `rows` against `kernel_dispatch_detail` (rows with
 * an existing `id` UPDATEd, rows without one INSERTed with a freshly
 * generated id), then DELETEs every id in `idsToDelete`. Shared by
 * saveDraft() and pauseDraftWithFormData() — mirrors
 * solidWasteDisposalRecordRepo.ts's applyDetailRowChanges().
 */
async function applyDetailRowChanges(
  recordId: string,
  rows: KernelDispatchDetailFormRow[],
  idsToDelete: string[],
  timestamp: string,
): Promise<void> {
  for (const row of rows) {
    const params = [
      row.event_date || null,
      row.shift || null,
      row.weighbridge_ticket_no || null,
      row.waybill_number || null,
      row.transporter_contractor || null,
      row.vehicle_plate_no || null,
      row.driver_name || null,
      row.silo_source_id || null,
      row.destination_buyer || null,
      row.gross_weight_mt,
      row.tare_weight_mt,
      row.net_weight_mt,
      row.kernel_moisture_percent,
      row.dirt_impurities_percent,
      row.ffa_percent,
      row.broken_kernel_percent,
      row.security_seal_no_top || null,
      row.security_seal_no_bottom || null,
      row.weighbridge_operator_id || null,
      row.remarks_gate_status || null,
      row.findings || null,
    ]

    if (row.id) {
      await run(
        `UPDATE kernel_dispatch_detail
         SET event_date = ?, shift = ?, weighbridge_ticket_no = ?, waybill_number = ?, transporter_contractor = ?,
             vehicle_plate_no = ?, driver_name = ?, silo_source_id = ?, destination_buyer = ?,
             gross_weight_mt = ?, tare_weight_mt = ?, net_weight_mt = ?, kernel_moisture_percent = ?,
             dirt_impurities_percent = ?, ffa_percent = ?, broken_kernel_percent = ?,
             security_seal_no_top = ?, security_seal_no_bottom = ?, weighbridge_operator_id = ?,
             remarks_gate_status = ?, findings = ?, updated_at = ?
         WHERE id = ?`,
        [...params, timestamp, row.id],
      )
      continue
    }

    const detailId = generateDetailId()

    await run(
      `INSERT INTO kernel_dispatch_detail
         (id, kernel_dispatch_record_id, event_date, shift, weighbridge_ticket_no, waybill_number,
          transporter_contractor, vehicle_plate_no, driver_name, silo_source_id, destination_buyer,
          gross_weight_mt, tare_weight_mt, net_weight_mt, kernel_moisture_percent, dirt_impurities_percent,
          ffa_percent, broken_kernel_percent, security_seal_no_top, security_seal_no_bottom,
          weighbridge_operator_id, remarks_gate_status, findings, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [detailId, recordId, ...params, timestamp, timestamp],
    )
  }

  for (const id of idsToDelete) {
    await run(`DELETE FROM kernel_dispatch_detail WHERE id = ?`, [id])
  }
}

function stripRoleGatedFields(
  headerData: KernelDispatchHeaderFormData,
  currentUserRole: KernelDispatchActorRole | null | undefined,
): { checkedBy: string | null; acknowledgedBy: string | null } {
  const isSupervisor = currentUserRole === 'supervisor'
  const isMillManagement = currentUserRole === 'mill_management'

  return {
    checkedBy: isSupervisor && headerData.checked_by ? headerData.checked_by : null,
    acknowledgedBy: isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null,
  }
}

/**
 * business_logic — 'Simpan'. Throws KernelDispatchDetailRequiredError when
 * zero rows in `detailRows` have an event_date. On success: UPDATEs the
 * header row (status='saved'), then upserts `detailRows` via
 * applyDetailRowChanges().
 */
export async function saveDraft(
  recordId: string,
  headerData: KernelDispatchHeaderFormData,
  detailRows: KernelDispatchDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: KernelDispatchActorRole | null | undefined,
): Promise<KernelDispatchDraftWithDetails> {
  const validRowCount = detailRows.filter((row) => row.event_date !== null && row.event_date !== '').length

  if (validRowCount === 0) {
    throw new KernelDispatchDetailRequiredError()
  }

  const timestamp = nowIso()
  const { checkedBy, acknowledgedBy } = stripRoleGatedFields(headerData, currentUserRole)

  await run(
    `UPDATE kernel_dispatch_record
     SET kernel_dispatch_id = ?, date = ?, note = ?, checked_by = ?, acknowledged_by = ?,
         status = 'saved', updated_at = ?
     WHERE id = ?`,
    [headerData.kernel_dispatch_id || null, headerData.date || null, headerData.note || null, checkedBy, acknowledgedBy, timestamp, recordId],
  )

  await applyDetailRowChanges(recordId, detailRows, detailIdsToDelete, timestamp)

  const saved = await getDraftWithDetails(recordId)

  if (!saved) {
    throw new Error('Gagal memuat ulang data kernel dispatch setelah disimpan.')
  }

  return saved
}

/**
 * business_logic — 'Pause'. UPDATEs every header field as-is, then sets
 * status='draft_paused' — NO required-field validation.
 */
export async function pauseDraftWithFormData(
  recordId: string,
  headerData: KernelDispatchHeaderFormData,
  detailRows: KernelDispatchDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: KernelDispatchActorRole | null | undefined,
): Promise<KernelDispatchDraftWithDetails> {
  const timestamp = nowIso()
  const { checkedBy, acknowledgedBy } = stripRoleGatedFields(headerData, currentUserRole)

  await run(
    `UPDATE kernel_dispatch_record
     SET kernel_dispatch_id = ?, date = ?, note = ?, checked_by = ?, acknowledged_by = ?,
         status = 'draft_paused', updated_at = ?
     WHERE id = ?`,
    [headerData.kernel_dispatch_id || null, headerData.date || null, headerData.note || null, checkedBy, acknowledgedBy, timestamp, recordId],
  )

  await applyDetailRowChanges(recordId, detailRows, detailIdsToDelete, timestamp)

  const saved = await getDraftWithDetails(recordId)

  if (!saved) {
    throw new Error('Gagal menyimpan progres kernel dispatch setelah pause.')
  }

  return saved
}

/**
 * 'Clear' (after UI-level confirm). Permanently DELETEs the record,
 * cascading the delete to its child detail rows first (application-level
 * cascade, same as solidWasteDisposalRecordRepo.ts's deleteDraft()).
 */
export async function deleteDraft(recordId: string): Promise<void> {
  await run(`DELETE FROM kernel_dispatch_detail WHERE kernel_dispatch_record_id = ?`, [recordId])
  await run(`DELETE FROM kernel_dispatch_record WHERE id = ?`, [recordId])
}

export const kernelDispatchRecordRepo = {
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

export default kernelDispatchRecordRepo

export const KERNEL_DISPATCH_DRAFT_STATUSES = DRAFT_STATUSES
