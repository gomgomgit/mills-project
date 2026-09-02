import { query, run } from '@/services/localDb'

/**
 * sterilizerRecordRepo — screen-121--monitor-sterilizer /
 * screen-122--form-sterilizer / screen-123--data-preview-sterilizer.
 *
 * Local (offline) read/write access to the `sterilizer_record` /
 * `sterilizer_detail` tables (schema in localSchema.ts). Mirrors
 * cpoDispatchRecordRepo.ts's repo style closely — Sterilizer is also a
 * pure EVENT-LOG station: detail rows (one per sterilization cycle) are
 * added manually per occurrence (unbounded per day, no grid/N-column
 * concept, no per-row time-slot uniqueness/ascending constraint).
 *
 * Monitor screen semantics (business_logic step 1) mirror CPO Dispatch:
 * this station shows (a) how many cycles have been logged TODAY
 * (`getTodaySummary()`), and (b) a summary of the single most-recently-
 * logged cycle across all of the user's records (`getLastCycleSummary()`)
 * — there is no "progress toward a target" metric to show for a free
 * event log.
 *
 * `duration_minutes` is computed client-side here too (= open_door_time -
 * close_door_time, in minutes) for immediate UX feedback on the Form
 * screen, but the SERVER remains the source of truth on save
 * (SterilizerRecordService::computeDurationMinutes() always recomputes it
 * — a client-supplied value is never trusted).
 *
 * This is the FINAL station of this project (Sterilizer promoted
 * 2026-09-01, the 18th and last of the 18 canonical stations).
 */

export type SterilizerDraftStatus = 'draft_ongoing' | 'draft_paused'
export type SterilizerRecordStatus = SterilizerDraftStatus | 'saved' | 'synced'

export interface SterilizerRecord {
  id: string
  station_id: string | null
  sterilizer_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  status: SterilizerRecordStatus
  created_by: string
  created_at: string
  updated_at: string
}

export interface SterilizerDetailRow {
  id: string
  sterilizer_record_id: string
  sterilizer_no: string | null
  close_door_time: string | null
  peak_1_time: string | null
  exhaust_1_time: string | null
  peak_2_time: string | null
  exhaust_2_time: string | null
  peak_3_time: string | null
  exhaust_3_time: string | null
  open_door_time: string | null
  duration_minutes: number | null
  number_of_cages: number | null
  cages_status: string | null
  checked_by_spv: boolean
  remarks: string | null
  created_at: string
  updated_at: string
}

export interface SterilizerDraftWithDetails {
  record: SterilizerRecord
  details: SterilizerDetailRow[]
}

/**
 * Header fields actually collected on Form Sterilizer (excludes
 * id/station_id/created_by/created_at/updated_at/status, managed by
 * createDraft()/saveDraft() itself). `date` is always rendered disabled
 * (auto-set-once-if-new), same convention as Form CPO Dispatch's date
 * field.
 */
export interface SterilizerHeaderFormData {
  sterilizer_id: string
  date: string
  note: string
  checked_by: string
  acknowledged_by: string
}

/**
 * One editable detail row as held in form state before saving. `id`
 * present for rows loaded from an existing draft (UPDATE target), absent
 * for rows added this session (INSERT target). `duration_minutes` is
 * (re)computed by the caller from close/open door time immediately
 * before save, same "computed fresh right before save" pattern as
 * CpoDispatchDetailFormRow's net_weight_mt — but is ALWAYS recomputed
 * server-side on save regardless of what is sent here.
 */
export interface SterilizerDetailFormRow {
  id?: string
  sterilizer_no: string | null
  close_door_time: string | null
  peak_1_time: string | null
  exhaust_1_time: string | null
  peak_2_time: string | null
  exhaust_2_time: string | null
  peak_3_time: string | null
  exhaust_3_time: string | null
  open_door_time: string | null
  duration_minutes: number | null
  number_of_cages: number | null
  cages_status: string | null
  checked_by_spv: boolean
  remarks: string | null
}

export type SterilizerActorRole = 'operator' | 'supervisor' | 'mill_management' | 'admin'

/**
 * Thrown by saveDraft() when zero rows in `detailRows` are "valid" (a
 * close_door_time filled in) — mirrors CpoDispatchDetailRequiredError.
 */
export class SterilizerDetailRequiredError extends Error {
  constructor() {
    super('Minimal 1 baris log Sterilizer harus diisi sebelum menyimpan.')
    this.name = 'SterilizerDetailRequiredError'
  }
}

export interface CurrentDraft {
  id: string
  status: SterilizerDraftStatus
}

export interface SterilizerTodaySummary {
  countRecords: number
  countCycles: number
}

export interface SterilizerLastCycle {
  recordId: string
  sterilizerNo: string | null
  closeDoorTime: string | null
  durationMinutes: number | null
}

export interface SterilizerDraftListItem {
  id: string
  status: SterilizerDraftStatus
  sterilizer_id: string | null
  updated_at: string
}

interface DraftListRow {
  id: string
  status: SterilizerDraftStatus
  sterilizer_id: string | null
  updated_at: string
}

interface TodayRecordIdRow {
  id: string
}

interface CountRow {
  count: number
}

interface LastCycleRow {
  sterilizer_record_id: string
  sterilizer_no: string | null
  close_door_time: string | null
  duration_minutes: number | null
}

const DRAFT_STATUSES: SterilizerDraftStatus[] = ['draft_ongoing', 'draft_paused']

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

  return `ster-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function generateDetailId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `sterd-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

/**
 * computeDurationMinutes() — client-side preview mirror of
 * SterilizerRecordService::computeDurationMinutes() (= open_door_time -
 * close_door_time, in minutes, wrapping across midnight). Purely for
 * immediate UX feedback — the server always recomputes this on save.
 */
export function computeDurationMinutes(closeDoorTime: string | null, openDoorTime: string | null): number | null {
  if (!closeDoorTime || !openDoorTime) {
    return null
  }

  const [closeHour, closeMinute] = closeDoorTime.slice(0, 5).split(':').map(Number)
  const [openHour, openMinute] = openDoorTime.slice(0, 5).split(':').map(Number)

  if ([closeHour, closeMinute, openHour, openMinute].some((value) => Number.isNaN(value))) {
    return null
  }

  const closeTotal = closeHour * 60 + closeMinute
  const openTotal = openHour * 60 + openMinute

  let diff = openTotal - closeTotal

  if (diff < 0) {
    diff += 24 * 60
  }

  return diff
}

/**
 * business_logic step 5 — 'New Data' (Monitor Sterilizer). INSERTs a
 * new draft record with status=draft_ongoing, created_by=current user,
 * `date` auto-filled once to today (entity-catalog: "date diisi otomatis
 * sekali saat draft baru dibuat, tidak dapat diedit manual").
 */
export async function createDraft(userId: string): Promise<string> {
  const id = generateId()
  const timestamp = nowIso()

  await run(
    `INSERT INTO sterilizer_record (id, status, created_by, date, created_at, updated_at)
     VALUES (?, 'draft_ongoing', ?, ?, ?, ?)`,
    [id, userId, todayDateString(), timestamp, timestamp],
  )

  return id
}

/**
 * business_logic step 1 — "Hari Ini" counters: count of records dated
 * today (any status), and count of detail rows (sterilization cycles)
 * belonging to those records — a pure activity count, not a "progress
 * toward N" metric.
 */
export async function getTodaySummary(userId: string): Promise<SterilizerTodaySummary> {
  const idRows = await query<TodayRecordIdRow>(
    `SELECT id
     FROM sterilizer_record
     WHERE created_by = ? AND date(date) = date('now', 'localtime')`,
    [userId],
  )

  const countRecords = idRows.length

  if (countRecords === 0) {
    return { countRecords: 0, countCycles: 0 }
  }

  const placeholders = idRows.map(() => '?').join(', ')

  const countRows = await query<CountRow>(
    `SELECT COUNT(*) AS count
     FROM sterilizer_detail
     WHERE sterilizer_record_id IN (${placeholders})`,
    idRows.map((row) => row.id),
  )

  return {
    countRecords,
    countCycles: countRows[0]?.count ?? 0,
  }
}

/**
 * business_logic step 1 (extended) — the single most-recently-logged
 * sterilization cycle across all of the current user's records (any
 * status, any date), so Monitor can show a "last cycle" summary card
 * rather than a tipping/progress metric. Returns null when the user has
 * no records or no detail rows at all yet.
 */
export async function getLastCycleSummary(userId: string): Promise<SterilizerLastCycle | null> {
  const rows = await query<LastCycleRow>(
    `SELECT d.sterilizer_record_id, d.sterilizer_no, d.close_door_time, d.duration_minutes
     FROM sterilizer_detail d
     INNER JOIN sterilizer_record r ON r.id = d.sterilizer_record_id
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
    recordId: row.sterilizer_record_id,
    sterilizerNo: row.sterilizer_no,
    closeDoorTime: row.close_door_time,
    durationMinutes: row.duration_minutes,
  }
}

/**
 * business_logic step 2 — every local record the current user has ongoing
 * or paused, most-recently-updated first. Mirrors
 * cpoDispatchRecordRepo's getDrafts().
 */
export async function getDrafts(userId: string): Promise<SterilizerDraftListItem[]> {
  const rows = await query<DraftListRow>(
    `SELECT id, status, sterilizer_id, updated_at
     FROM sterilizer_record
     WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
     ORDER BY updated_at DESC`,
    [userId],
  )

  return rows.map((row) => ({
    id: row.id,
    status: row.status,
    sterilizer_id: row.sterilizer_id,
    updated_at: row.updated_at,
  }))
}

/**
 * Every local record for the current user, ANY status, most-recently
 * updated first — Data Preview Sterilizer's LIST mode.
 */
export async function getAllRecords(userId: string): Promise<SterilizerRecord[]> {
  return query<SterilizerRecord>(
    `SELECT * FROM sterilizer_record WHERE created_by = ? ORDER BY updated_at DESC`,
    [userId],
  )
}

/**
 * Loads a `sterilizer_record` header row plus all of its detail
 * (event-log) rows, ordered by creation order (insertion order = the order
 * cycles were added). Returns null when no header row matches.
 */
export async function getDraftWithDetails(recordId: string): Promise<SterilizerDraftWithDetails | null> {
  const recordRows = await query<SterilizerRecord>(
    `SELECT * FROM sterilizer_record WHERE id = ?`,
    [recordId],
  )
  const record = recordRows[0]

  if (!record) {
    return null
  }

  const rawDetails = await query<Omit<SterilizerDetailRow, 'checked_by_spv'> & { checked_by_spv: number | boolean }>(
    `SELECT * FROM sterilizer_detail WHERE sterilizer_record_id = ? ORDER BY created_at ASC`,
    [recordId],
  )

  const details: SterilizerDetailRow[] = rawDetails.map((row) => ({
    ...row,
    checked_by_spv: row.checked_by_spv === true || row.checked_by_spv === 1,
  }))

  return { record, details }
}

/**
 * Upserts every row in `rows` against `sterilizer_detail` (rows with
 * an existing `id` UPDATEd, rows without one INSERTed with a freshly
 * generated id), then DELETEs every id in `idsToDelete`. Shared by
 * saveDraft() and pauseDraftWithFormData() — mirrors
 * cpoDispatchRecordRepo.ts's applyDetailRowChanges().
 */
async function applyDetailRowChanges(
  recordId: string,
  rows: SterilizerDetailFormRow[],
  idsToDelete: string[],
  timestamp: string,
): Promise<void> {
  for (const row of rows) {
    const durationMinutes = computeDurationMinutes(row.close_door_time, row.open_door_time)

    const params = [
      row.sterilizer_no || null,
      row.close_door_time || null,
      row.peak_1_time || null,
      row.exhaust_1_time || null,
      row.peak_2_time || null,
      row.exhaust_2_time || null,
      row.peak_3_time || null,
      row.exhaust_3_time || null,
      row.open_door_time || null,
      durationMinutes,
      row.number_of_cages,
      row.cages_status || null,
      row.checked_by_spv ? 1 : 0,
      row.remarks || null,
    ]

    if (row.id) {
      await run(
        `UPDATE sterilizer_detail
         SET sterilizer_no = ?, close_door_time = ?, peak_1_time = ?, exhaust_1_time = ?, peak_2_time = ?,
             exhaust_2_time = ?, peak_3_time = ?, exhaust_3_time = ?, open_door_time = ?, duration_minutes = ?,
             number_of_cages = ?, cages_status = ?, checked_by_spv = ?, remarks = ?, updated_at = ?
         WHERE id = ?`,
        [...params, timestamp, row.id],
      )
      continue
    }

    const detailId = generateDetailId()

    await run(
      `INSERT INTO sterilizer_detail
         (id, sterilizer_record_id, sterilizer_no, close_door_time, peak_1_time, exhaust_1_time, peak_2_time,
          exhaust_2_time, peak_3_time, exhaust_3_time, open_door_time, duration_minutes, number_of_cages,
          cages_status, checked_by_spv, remarks, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [detailId, recordId, ...params, timestamp, timestamp],
    )
  }

  for (const id of idsToDelete) {
    await run(`DELETE FROM sterilizer_detail WHERE id = ?`, [id])
  }
}

function stripRoleGatedFields(
  headerData: SterilizerHeaderFormData,
  currentUserRole: SterilizerActorRole | null | undefined,
): { checkedBy: string | null; acknowledgedBy: string | null } {
  const isSupervisor = currentUserRole === 'supervisor'
  const isMillManagement = currentUserRole === 'mill_management'

  return {
    checkedBy: isSupervisor && headerData.checked_by ? headerData.checked_by : null,
    acknowledgedBy: isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null,
  }
}

/**
 * business_logic — 'Simpan'. Throws SterilizerDetailRequiredError when
 * zero rows in `detailRows` have a close_door_time. On success: UPDATEs
 * the header row (status='saved'), then upserts `detailRows` via
 * applyDetailRowChanges().
 */
export async function saveDraft(
  recordId: string,
  headerData: SterilizerHeaderFormData,
  detailRows: SterilizerDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: SterilizerActorRole | null | undefined,
): Promise<SterilizerDraftWithDetails> {
  const validRowCount = detailRows.filter((row) => row.close_door_time !== null && row.close_door_time !== '').length

  if (validRowCount === 0) {
    throw new SterilizerDetailRequiredError()
  }

  const timestamp = nowIso()
  const { checkedBy, acknowledgedBy } = stripRoleGatedFields(headerData, currentUserRole)

  await run(
    `UPDATE sterilizer_record
     SET sterilizer_id = ?, date = ?, note = ?, checked_by = ?, acknowledged_by = ?,
         status = 'saved', updated_at = ?
     WHERE id = ?`,
    [headerData.sterilizer_id || null, headerData.date || null, headerData.note || null, checkedBy, acknowledgedBy, timestamp, recordId],
  )

  await applyDetailRowChanges(recordId, detailRows, detailIdsToDelete, timestamp)

  const saved = await getDraftWithDetails(recordId)

  if (!saved) {
    throw new Error('Gagal memuat ulang data sterilizer setelah disimpan.')
  }

  return saved
}

/**
 * business_logic — 'Pause'. UPDATEs every header field as-is, then sets
 * status='draft_paused' — NO required-field validation.
 */
export async function pauseDraftWithFormData(
  recordId: string,
  headerData: SterilizerHeaderFormData,
  detailRows: SterilizerDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: SterilizerActorRole | null | undefined,
): Promise<SterilizerDraftWithDetails> {
  const timestamp = nowIso()
  const { checkedBy, acknowledgedBy } = stripRoleGatedFields(headerData, currentUserRole)

  await run(
    `UPDATE sterilizer_record
     SET sterilizer_id = ?, date = ?, note = ?, checked_by = ?, acknowledged_by = ?,
         status = 'draft_paused', updated_at = ?
     WHERE id = ?`,
    [headerData.sterilizer_id || null, headerData.date || null, headerData.note || null, checkedBy, acknowledgedBy, timestamp, recordId],
  )

  await applyDetailRowChanges(recordId, detailRows, detailIdsToDelete, timestamp)

  const saved = await getDraftWithDetails(recordId)

  if (!saved) {
    throw new Error('Gagal menyimpan progres sterilizer setelah pause.')
  }

  return saved
}

/**
 * 'Clear' (after UI-level confirm). Permanently DELETEs the record,
 * cascading the delete to its child detail rows first (application-level
 * cascade, same as cpoDispatchRecordRepo.ts's deleteDraft()).
 */
export async function deleteDraft(recordId: string): Promise<void> {
  await run(`DELETE FROM sterilizer_detail WHERE sterilizer_record_id = ?`, [recordId])
  await run(`DELETE FROM sterilizer_record WHERE id = ?`, [recordId])
}

export const sterilizerRecordRepo = {
  createDraft,
  getTodaySummary,
  getLastCycleSummary,
  getDrafts,
  getAllRecords,
  getDraftWithDetails,
  saveDraft,
  pauseDraftWithFormData,
  deleteDraft,
  computeDurationMinutes,
}

export default sterilizerRecordRepo

export const STERILIZER_DRAFT_STATUSES = DRAFT_STATUSES
