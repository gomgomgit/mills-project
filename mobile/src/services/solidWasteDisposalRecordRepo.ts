import { query, run } from '@/services/localDb'

/**
 * solidWasteDisposalRecordRepo — screen-061--monitor-solid-waste-disposal /
 * screen-071--form-solid-waste-disposal / screen-081--data-preview-solid-waste-disposal.
 *
 * Local (offline) read/write access to the `solid_waste_disposal_record` /
 * `solid_waste_disposal_detail` tables (schema in localSchema.ts). Mirrors
 * cagesTrackRecordRepo.ts's repo style closely, but Solid Waste Disposal is
 * a pure EVENT-LOG station: detail rows are added manually per occurrence
 * (unbounded per day, no grid/N-column concept, no per-row time-slot
 * uniqueness/ascending constraint like cages_tipped_time.tipped_hour).
 *
 * Monitor screen semantics (business_logic step 1) deliberately differ from
 * Cages Track's "tipping progress" counter: this station shows (a) how many
 * events have been logged TODAY (`getTodaySummary()`), and (b) a summary of
 * the single most-recently-logged event across all of the user's records
 * (`getLastEventSummary()`) — there is no "progress toward a target" metric
 * to show for a free event log.
 */

export type SolidWasteDisposalDraftStatus = 'draft_ongoing' | 'draft_paused'
export type SolidWasteDisposalRecordStatus = SolidWasteDisposalDraftStatus | 'saved' | 'synced'

export interface SolidWasteDisposalRecord {
  id: string
  station_id: string | null
  solid_waste_disposal_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  checked_by_name?: string | null
  acknowledged_by_name?: string | null
  status: SolidWasteDisposalRecordStatus
  created_by: string
  created_at: string
  updated_at: string
}

export interface SolidWasteDisposalDetailRow {
  id: string
  solid_waste_disposal_record_id: string
  event_date: string | null
  shift: string | null
  weighbridge_ticket_no: string | null
  vehicle_no: string | null
  driver_name: string | null
  solid_waste_type: string | null
  source_station: string | null
  gross_weight_mt: number | null
  tare_weight_mt: number | null
  net_weight_mt: number | null
  disposal_utilization_site: string | null
  purpose_end_use: string | null
  gate_pass_no: string | null
  security_seal_no: string | null
  operator_id: string | null
  remarks: string | null
  findings: string | null
  created_at: string
  updated_at: string
}

export interface SolidWasteDisposalDraftWithDetails {
  record: SolidWasteDisposalRecord
  details: SolidWasteDisposalDetailRow[]
}

/**
 * Header fields actually collected on Form Solid Waste Disposal (excludes
 * id/station_id/created_by/created_at/updated_at/status, managed by
 * createDraft()/saveDraft() itself). `date` is always rendered disabled
 * (auto-set-once-if-new), same convention as Form Cages Track's
 * date/tippler_start_time.
 */
export interface SolidWasteDisposalHeaderFormData {
  solid_waste_disposal_id: string
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
 * right before save" pattern as CagesTippedTimeFormRow.total_cages.
 */
export interface SolidWasteDisposalDetailFormRow {
  id?: string
  event_date: string | null
  shift: string | null
  weighbridge_ticket_no: string | null
  vehicle_no: string | null
  driver_name: string | null
  solid_waste_type: string | null
  source_station: string | null
  gross_weight_mt: number | null
  tare_weight_mt: number | null
  net_weight_mt: number | null
  disposal_utilization_site: string | null
  purpose_end_use: string | null
  gate_pass_no: string | null
  security_seal_no: string | null
  operator_id: string | null
  remarks: string | null
  findings: string | null
}

export type SolidWasteDisposalActorRole = 'operator' | 'supervisor' | 'mill_management' | 'admin'

/**
 * Thrown by saveDraft() when zero rows in `detailRows` are "valid" (an
 * event_date filled in) — mirrors CagesTippedTimeRequiredError.
 */
export class SolidWasteDisposalDetailRequiredError extends Error {
  constructor() {
    super('Minimal 1 baris log Solid Waste Disposal harus diisi sebelum menyimpan.')
    this.name = 'SolidWasteDisposalDetailRequiredError'
  }
}

export interface CurrentDraft {
  id: string
  status: SolidWasteDisposalDraftStatus
}

export interface SolidWasteDisposalTodaySummary {
  countRecords: number
  countEvents: number
}

export interface SolidWasteDisposalLastEvent {
  recordId: string
  eventDate: string | null
  solidWasteType: string | null
  vehicleNo: string | null
}

export interface SolidWasteDisposalDraftListItem {
  id: string
  status: SolidWasteDisposalDraftStatus
  solid_waste_disposal_id: string | null
  updated_at: string
}

interface DraftListRow {
  id: string
  status: SolidWasteDisposalDraftStatus
  solid_waste_disposal_id: string | null
  updated_at: string
}

interface TodayRecordIdRow {
  id: string
}

interface CountRow {
  count: number
}

interface LastEventRow {
  solid_waste_disposal_record_id: string
  event_date: string | null
  solid_waste_type: string | null
  vehicle_no: string | null
}

const DRAFT_STATUSES: SolidWasteDisposalDraftStatus[] = ['draft_ongoing', 'draft_paused']

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

  return `swd-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function generateDetailId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `swdd-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

/**
 * business_logic step 5 — 'New Data' (Monitor Solid Waste Disposal). INSERTs
 * a new draft record with status=draft_ongoing, created_by=current user,
 * `date` auto-filled once to today (entity-catalog: "date diisi otomatis
 * sekali saat draft baru dibuat, tidak dapat diedit manual").
 */
export async function createDraft(userId: string): Promise<string> {
  const id = generateId()
  const timestamp = nowIso()

  await run(
    `INSERT INTO solid_waste_disposal_record (id, status, created_by, date, created_at, updated_at)
     VALUES (?, 'draft_ongoing', ?, ?, ?, ?)`,
    [id, userId, todayDateString(), timestamp, timestamp],
  )

  return id
}

/**
 * business_logic step 1 — "Hari Ini" counters: count of records dated
 * today (any status), and count of detail rows (events) belonging to those
 * records — a pure activity count, not a "progress toward N" metric like
 * Cages Track's sumTotalCages.
 */
export async function getTodaySummary(userId: string): Promise<SolidWasteDisposalTodaySummary> {
  const idRows = await query<TodayRecordIdRow>(
    `SELECT id
     FROM solid_waste_disposal_record
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
     FROM solid_waste_disposal_detail
     WHERE solid_waste_disposal_record_id IN (${placeholders})`,
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
export async function getLastEventSummary(userId: string): Promise<SolidWasteDisposalLastEvent | null> {
  const rows = await query<LastEventRow>(
    `SELECT d.solid_waste_disposal_record_id, d.event_date, d.solid_waste_type, d.vehicle_no
     FROM solid_waste_disposal_detail d
     INNER JOIN solid_waste_disposal_record r ON r.id = d.solid_waste_disposal_record_id
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
    recordId: row.solid_waste_disposal_record_id,
    eventDate: row.event_date,
    solidWasteType: row.solid_waste_type,
    vehicleNo: row.vehicle_no,
  }
}

/**
 * business_logic step 2 — every local record the current user has ongoing
 * or paused, most-recently-updated first. Mirrors cagesTrackRecordRepo's
 * getDrafts().
 */
export async function getDrafts(userId: string): Promise<SolidWasteDisposalDraftListItem[]> {
  const rows = await query<DraftListRow>(
    `SELECT id, status, solid_waste_disposal_id, updated_at
     FROM solid_waste_disposal_record
     WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
     ORDER BY updated_at DESC`,
    [userId],
  )

  return rows.map((row) => ({
    id: row.id,
    status: row.status,
    solid_waste_disposal_id: row.solid_waste_disposal_id,
    updated_at: row.updated_at,
  }))
}

/**
 * Every local record for the current user, ANY status, most-recently
 * updated first — Data Preview Solid Waste Disposal's LIST mode.
 */
export async function getAllRecords(userId: string): Promise<SolidWasteDisposalRecord[]> {
  return query<SolidWasteDisposalRecord>(
    `SELECT * FROM solid_waste_disposal_record WHERE created_by = ? ORDER BY updated_at DESC`,
    [userId],
  )
}

/**
 * Loads a `solid_waste_disposal_record` header row plus all of its detail
 * (event-log) rows, ordered by creation order (insertion order = the order
 * events were added). Returns null when no header row matches.
 */
export async function getDraftWithDetails(recordId: string): Promise<SolidWasteDisposalDraftWithDetails | null> {
  const recordRows = await query<SolidWasteDisposalRecord>(
    `SELECT * FROM solid_waste_disposal_record WHERE id = ?`,
    [recordId],
  )
  const record = recordRows[0]

  if (!record) {
    return null
  }

  const details = await query<SolidWasteDisposalDetailRow>(
    `SELECT * FROM solid_waste_disposal_detail WHERE solid_waste_disposal_record_id = ? ORDER BY created_at ASC`,
    [recordId],
  )

  return { record, details }
}

/**
 * Upserts every row in `rows` against `solid_waste_disposal_detail` (rows
 * with an existing `id` UPDATEd, rows without one INSERTed with a freshly
 * generated id), then DELETEs every id in `idsToDelete`. Shared by
 * saveDraft() and pauseDraftWithFormData() — mirrors
 * cagesTrackRecordRepo.ts's applyTippedTimeRowChanges().
 */
async function applyDetailRowChanges(
  recordId: string,
  rows: SolidWasteDisposalDetailFormRow[],
  idsToDelete: string[],
  timestamp: string,
): Promise<void> {
  for (const row of rows) {
    const params = [
      row.event_date || null,
      row.shift || null,
      row.weighbridge_ticket_no || null,
      row.vehicle_no || null,
      row.driver_name || null,
      row.solid_waste_type || null,
      row.source_station || null,
      row.gross_weight_mt,
      row.tare_weight_mt,
      row.net_weight_mt,
      row.disposal_utilization_site || null,
      row.purpose_end_use || null,
      row.gate_pass_no || null,
      row.security_seal_no || null,
      row.operator_id || null,
      row.remarks || null,
      row.findings || null,
    ]

    if (row.id) {
      await run(
        `UPDATE solid_waste_disposal_detail
         SET event_date = ?, shift = ?, weighbridge_ticket_no = ?, vehicle_no = ?, driver_name = ?,
             solid_waste_type = ?, source_station = ?, gross_weight_mt = ?, tare_weight_mt = ?,
             net_weight_mt = ?, disposal_utilization_site = ?, purpose_end_use = ?, gate_pass_no = ?,
             security_seal_no = ?, operator_id = ?, remarks = ?, findings = ?, updated_at = ?
         WHERE id = ?`,
        [...params, timestamp, row.id],
      )
      continue
    }

    const detailId = generateDetailId()

    await run(
      `INSERT INTO solid_waste_disposal_detail
         (id, solid_waste_disposal_record_id, event_date, shift, weighbridge_ticket_no, vehicle_no,
          driver_name, solid_waste_type, source_station, gross_weight_mt, tare_weight_mt, net_weight_mt,
          disposal_utilization_site, purpose_end_use, gate_pass_no, security_seal_no, operator_id,
          remarks, findings, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [detailId, recordId, ...params, timestamp, timestamp],
    )
  }

  for (const id of idsToDelete) {
    await run(`DELETE FROM solid_waste_disposal_detail WHERE id = ?`, [id])
  }
}

function stripRoleGatedFields(
  headerData: SolidWasteDisposalHeaderFormData,
  currentUserRole: SolidWasteDisposalActorRole | null | undefined,
): { checkedBy: string | null; acknowledgedBy: string | null } {
  const isSupervisor = currentUserRole === 'supervisor'
  const isMillManagement = currentUserRole === 'mill_management'

  return {
    checkedBy: isSupervisor && headerData.checked_by ? headerData.checked_by : null,
    acknowledgedBy: isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null,
  }
}

/**
 * business_logic — 'Simpan'. Throws SolidWasteDisposalDetailRequiredError
 * when zero rows in `detailRows` have an event_date. On success: UPDATEs
 * the header row (status='saved'), then upserts `detailRows` via
 * applyDetailRowChanges().
 */
export async function saveDraft(
  recordId: string,
  headerData: SolidWasteDisposalHeaderFormData,
  detailRows: SolidWasteDisposalDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: SolidWasteDisposalActorRole | null | undefined,
): Promise<SolidWasteDisposalDraftWithDetails> {
  const validRowCount = detailRows.filter((row) => row.event_date !== null && row.event_date !== '').length

  if (validRowCount === 0) {
    throw new SolidWasteDisposalDetailRequiredError()
  }

  const timestamp = nowIso()
  const { checkedBy, acknowledgedBy } = stripRoleGatedFields(headerData, currentUserRole)

  await run(
    `UPDATE solid_waste_disposal_record
     SET solid_waste_disposal_id = ?, date = ?, note = ?, checked_by = ?, acknowledged_by = ?,
         status = 'saved', updated_at = ?
     WHERE id = ?`,
    [headerData.solid_waste_disposal_id || null, headerData.date || null, headerData.note || null, checkedBy, acknowledgedBy, timestamp, recordId],
  )

  await applyDetailRowChanges(recordId, detailRows, detailIdsToDelete, timestamp)

  const saved = await getDraftWithDetails(recordId)

  if (!saved) {
    throw new Error('Gagal memuat ulang data solid waste disposal setelah disimpan.')
  }

  return saved
}

/**
 * business_logic — 'Pause'. UPDATEs every header field as-is, then sets
 * status='draft_paused' — NO required-field validation.
 */
export async function pauseDraftWithFormData(
  recordId: string,
  headerData: SolidWasteDisposalHeaderFormData,
  detailRows: SolidWasteDisposalDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: SolidWasteDisposalActorRole | null | undefined,
): Promise<SolidWasteDisposalDraftWithDetails> {
  const timestamp = nowIso()
  const { checkedBy, acknowledgedBy } = stripRoleGatedFields(headerData, currentUserRole)

  await run(
    `UPDATE solid_waste_disposal_record
     SET solid_waste_disposal_id = ?, date = ?, note = ?, checked_by = ?, acknowledged_by = ?,
         status = 'draft_paused', updated_at = ?
     WHERE id = ?`,
    [headerData.solid_waste_disposal_id || null, headerData.date || null, headerData.note || null, checkedBy, acknowledgedBy, timestamp, recordId],
  )

  await applyDetailRowChanges(recordId, detailRows, detailIdsToDelete, timestamp)

  const saved = await getDraftWithDetails(recordId)

  if (!saved) {
    throw new Error('Gagal menyimpan progres solid waste disposal setelah pause.')
  }

  return saved
}

/**
 * 'Clear' (after UI-level confirm). Permanently DELETEs the record,
 * cascading the delete to its child detail rows first (application-level
 * cascade, same as cagesTrackRecordRepo.ts's deleteDraft()).
 */
export async function deleteDraft(recordId: string): Promise<void> {
  await run(`DELETE FROM solid_waste_disposal_detail WHERE solid_waste_disposal_record_id = ?`, [recordId])
  await run(`DELETE FROM solid_waste_disposal_record WHERE id = ?`, [recordId])
}

export const solidWasteDisposalRecordRepo = {
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

export default solidWasteDisposalRecordRepo

export const SOLID_WASTE_DISPOSAL_DRAFT_STATUSES = DRAFT_STATUSES
