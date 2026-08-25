import { query, run } from '@/services/localDb'

/**
 * threshingRecordRepo — screen-037--monitor-threshing /
 * usecase-037--monitor-threshing, screen-041--form-threshing /
 * usecase-041--form-threshing, screen-045--data-preview-threshing /
 * usecase-045--data-preview-threshing. Local (offline) read/write access to
 * the `threshing_record` / `threshing_detail` tables (schema defined in
 * localSchema.ts) — mirrors cagesTrackRecordRepo.ts's repo style (plain
 * async functions over localDb.ts's `query`/`run` primitives).
 *
 * REVISED (2026-08-24, entity-catalog v12): threshing_detail is now a
 * DYNAMIC add-row/remove-row grid — exactly the same pattern as
 * cagesTrackRecordRepo.ts's `cages_tipped_time` (`tippedTimeRows`/
 * `pendingDeletionIds` in FormCagesTrackView.vue). This REPLACES the
 * original design (a FIXED set of exactly 24 pre-created rows, one per
 * hourly time-slot, inserted all at once by `createDraft()`), which the
 * user explicitly rejected as wasting screen space. `createDraft()` now
 * inserts ONLY the header row — no detail rows at all. Rows are added one
 * at a time via `applyDetailRowChanges()` (called from `saveDraft()`/
 * `pauseDraftWithFormData()`, mirrors cagesTrackRecordRepo.ts's
 * `applyTippedTimeRowChanges()`): rows with an existing `id` are UPDATEd,
 * rows without one are INSERTed with a freshly generated id via
 * `generateDetailId()`, and every id in the caller-supplied
 * `idsToDelete` list is DELETEd after the upserts. `canonicalTimeSlots()`
 * is KEPT — no longer used to pre-generate rows, but still the source of
 * truth for the dropdown's ordered option list (FormThreshingView.vue's
 * `availableTimeSlotOptions()`) and for `getDraftWithDetails()`'s sort
 * order.
 *
 * "Filled" row, for the required-row validation only (not a DB constraint):
 * a threshing_detail row counts as filled when at least one of its 6
 * reading columns (ffb_throughput_mt_hour, thresher_drum_speed_rpm,
 * motor_current_amps, unstripped_bunch_count_percent,
 * empty_bunch_oil_loss_percent, downtime_reason) is non-null/non-empty. The
 * server-side ThreshingRecord::booted() `saving` guard ("minimal satu
 * threshing-detail sebelum status=saved") checks row EXISTENCE, satisfied
 * once at least one row has been added — the REAL "minimal satu baris
 * terisi" business rule (a selected `time_slot` AND at least one filled
 * reading column) is enforced at this repo level (`saveDraft()`) and again
 * in FormThreshingView.vue's client-side validation, mirroring
 * cagesTrackRecordRepo.ts's `CagesTippedTimeRequiredError` gate exactly.
 */

export type ThreshingDraftStatus = 'draft_ongoing' | 'draft_paused'
export type ThreshingRecordStatus = ThreshingDraftStatus | 'saved' | 'synced'

/**
 * Full local `threshing_record` row shape (mirrors localSchema.ts's
 * CREATE_THRESHING_RECORD column-for-column).
 */
export interface ThreshingRecord {
  id: string
  station_id: string | null
  thresher_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  status: ThreshingRecordStatus
  created_by: string
  created_at: string
  updated_at: string
}

/**
 * Full local `threshing_detail` row shape (mirrors localSchema.ts's
 * CREATE_THRESHING_DETAIL column-for-column).
 */
export interface ThreshingDetailRow {
  id: string
  threshing_record_id: string
  time_slot: string
  ffb_throughput_mt_hour: number | null
  thresher_drum_speed_rpm: number | null
  motor_current_amps: number | null
  unstripped_bunch_count_percent: number | null
  empty_bunch_oil_loss_percent: number | null
  downtime_reason: string | null
  created_at: string
  updated_at: string
}

export interface ThreshingDraftWithDetails {
  record: ThreshingRecord
  details: ThreshingDetailRow[]
}

/**
 * Subset of `ThreshingRecord` columns the Form Threshing screen collects
 * from the user (excludes id/station_id/created_by/created_at/updated_at/
 * status, which are managed by createDraft()/saveDraft() itself).
 */
export interface ThreshingHeaderFormData {
  thresher_id: string
  date: string
  note: string
  checked_by: string
  acknowledged_by: string
}

/**
 * One editable `threshing_detail` row as held in FormThreshingView.vue's
 * form state. `id` is present for rows loaded from an existing draft
 * (UPDATE target) and absent for rows added in this editing session (INSERT
 * target) — `saveDraft()`/`pauseDraftWithFormData()` upsert on exactly this
 * distinction, mirroring CagesTippedTimeFormRow exactly. `time_slot` is
 * `null` until the user picks one from the dropdown for a newly-added row.
 */
export interface ThreshingDetailFormRow {
  id?: string
  time_slot: string | null
  ffb_throughput_mt_hour: number | null
  thresher_drum_speed_rpm: number | null
  motor_current_amps: number | null
  unstripped_bunch_count_percent: number | null
  empty_bunch_oil_loss_percent: number | null
  downtime_reason: string | null
}

/**
 * User roles a saveDraft()/pauseDraftWithFormData() caller may pass as
 * currentUserRole — plain string union, matching
 * cagesTrackRecordRepo.ts's CagesTrackActorRole, decoupling this repo from
 * the auth store.
 */
export type ThreshingActorRole = 'operator' | 'supervisor' | 'mill_management' | 'admin'

/**
 * screen-041--form-threshing business_logic step 9 — thrown by saveDraft()
 * when zero rows in the given `details` are "valid" (a selected `time_slot`
 * AND at least one filled reading column), so callers
 * (FormThreshingView.vue) can distinguish this from other save failures —
 * mirrors CagesTippedTimeRequiredError exactly.
 */
export class ThreshingDetailRequiredError extends Error {
  constructor() {
    super('Minimal 1 baris Threshing Detail (Time-Slot terpilih + minimal 1 kolom bacaan terisi) harus diisi sebelum menyimpan.')
    this.name = 'ThreshingDetailRequiredError'
  }
}

export interface ThreshingDraftListItem {
  id: string
  status: ThreshingDraftStatus
  thresher_id: string | null
  updated_at: string
}

interface DraftListRow {
  id: string
  status: ThreshingDraftStatus
  thresher_id: string | null
  updated_at: string
}

export interface ThreshingTodaySummary {
  countThreshingRecord: number
  detailRowCount: number
}

interface TodayRecordIdRow {
  id: string
}

interface DetailRowCountRow {
  detail_row_count: number
}

const DRAFT_STATUSES: ThreshingDraftStatus[] = ['draft_ongoing', 'draft_paused']

function nowIso(): string {
  return new Date().toISOString()
}

function generateId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `thr-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function generateDetailId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `thd-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

/**
 * The 24 canonical hourly time-slot labels, in order: 07:00, 08:00, ...,
 * 23:00, 00:00, ..., 06:00 (i.e. starting at hour 7, wrapping past midnight).
 * Shared by createDraft() (row generation) and any caller needing the
 * canonical order (e.g. tests). `for i in 0..23 -> hour = (7 + i) % 24`.
 */
export function canonicalTimeSlots(): string[] {
  return Array.from({ length: 24 }, (_, i) => {
    const hour = (7 + i) % 24
    return `${String(hour).padStart(2, '0')}:00`
  })
}

/**
 * Returns true when a threshing_detail row has at least one of its 6
 * reading columns non-null/non-empty — see this file's header comment for
 * the "filled row" definition.
 */
function isRowFilled(row: {
  ffb_throughput_mt_hour: number | null
  thresher_drum_speed_rpm: number | null
  motor_current_amps: number | null
  unstripped_bunch_count_percent: number | null
  empty_bunch_oil_loss_percent: number | null
  downtime_reason: string | null
}): boolean {
  return (
    row.ffb_throughput_mt_hour !== null ||
    row.thresher_drum_speed_rpm !== null ||
    row.motor_current_amps !== null ||
    row.unstripped_bunch_count_percent !== null ||
    row.empty_bunch_oil_loss_percent !== null ||
    (row.downtime_reason !== null && row.downtime_reason !== '')
  )
}

/**
 * screen-037--monitor-threshing business_logic step 5 — 'New Data'. INSERTs
 * a new threshing_record row ONLY (status=draft_ongoing, created_by=current
 * user, date=now — auto-filled once at creation, mirrors
 * cages_track_record.tippler_start_time) — no threshing_detail rows are
 * pre-created (2026-08-24 revision, entity-catalog v12: rows are added one
 * at a time by the user via "Tambah baris" in FormThreshingView.vue, same
 * pattern as cagesTrackRecordRepo.ts's `cages_tipped_time`). Returns the new
 * record's id so the caller can navigate to Form Threshing with it.
 */
export async function createDraft(userId: string): Promise<string> {
  const id = generateId()
  const timestamp = nowIso()

  await run(
    `INSERT INTO threshing_record (id, status, created_by, date, created_at, updated_at)
     VALUES (?, 'draft_ongoing', ?, ?, ?, ?)`,
    [id, userId, timestamp, timestamp, timestamp],
  )

  return id
}

/**
 * screen-037--monitor-threshing business_logic step 2 — every local
 * threshing_record the current user has ongoing or paused, most-recently-
 * updated first. Mirrors cagesTrackRecordRepo.ts's getDrafts().
 */
export async function getDrafts(userId: string): Promise<ThreshingDraftListItem[]> {
  const rows = await query<DraftListRow>(
    `SELECT id, status, thresher_id, updated_at
     FROM threshing_record
     WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
     ORDER BY updated_at DESC`,
    [userId],
  )

  return rows.map((row) => ({
    id: row.id,
    status: row.status,
    thresher_id: row.thresher_id,
    updated_at: row.updated_at,
  }))
}

/**
 * screen-037--monitor-threshing business_logic step 1 — "Hari Ini" counters.
 * "Jumlah Threshing Record" counts the current user's threshing_record rows
 * (any status) dated today (device-local day boundary). "Jumlah Baris
 * Time-Slot Tercatat" is a plain COUNT of threshing_detail rows belonging to
 * those same records — NOT filtered by isRowFilled() (2026-08-24 revision:
 * since rows are no longer pre-created, every existing row was explicitly
 * added by the user via "Tambah baris", so row EXISTENCE itself is the
 * meaningful signal now — mirrors cagesTrackRecordRepo.ts's
 * getProgressSummary() `tippedCount` "count of child rows added" shape,
 * rather than "filled rows out of a fixed 24"). Two sequential queries
 * (today's record ids, then a scoped COUNT), skipping the second query
 * entirely when there are no matching ids (an empty `IN ()` list is invalid
 * SQL) — same shape as cagesTrackRecordRepo.ts's getTodaySummary().
 */
export async function getTodaySummary(userId: string): Promise<ThreshingTodaySummary> {
  const idRows = await query<TodayRecordIdRow>(
    `SELECT id
     FROM threshing_record
     WHERE created_by = ? AND date(date) = date('now', 'localtime')`,
    [userId],
  )

  const countThreshingRecord = idRows.length

  if (countThreshingRecord === 0) {
    return { countThreshingRecord: 0, detailRowCount: 0 }
  }

  const placeholders = idRows.map(() => '?').join(', ')

  const countRows = await query<DetailRowCountRow>(
    `SELECT COUNT(*) AS detail_row_count FROM threshing_detail WHERE threshing_record_id IN (${placeholders})`,
    idRows.map((row) => row.id),
  )

  return {
    countThreshingRecord,
    detailRowCount: countRows[0]?.detail_row_count ?? 0,
  }
}

/**
 * screen-045--data-preview-threshing business_logic step 1 — every local
 * threshing_record row for the current user, ANY status, most-recently-
 * updated first. Mirrors cagesTrackRecordRepo.ts's getAllRecords().
 */
export async function getAllRecords(userId: string): Promise<ThreshingRecord[]> {
  return query<ThreshingRecord>(`SELECT * FROM threshing_record WHERE created_by = ? ORDER BY updated_at DESC`, [
    userId,
  ])
}

/**
 * screen-041--form-threshing business_logic step 1 — Form Threshing loads
 * an existing draft by route param id, plus all of its threshing_detail
 * rows (however many the user has added so far — no longer always 24),
 * ordered by time_slot ascending starting at 07:00 (canonical order, not
 * alphabetical — '00:00' would otherwise sort before '07:00'). Returns null
 * when no header row matches (defensive).
 */
export async function getDraftWithDetails(recordId: string): Promise<ThreshingDraftWithDetails | null> {
  const recordRows = await query<ThreshingRecord>(`SELECT * FROM threshing_record WHERE id = ?`, [recordId])
  const record = recordRows[0]

  if (!record) {
    return null
  }

  const rawDetails = await query<ThreshingDetailRow>(`SELECT * FROM threshing_detail WHERE threshing_record_id = ?`, [
    recordId,
  ])

  const order = canonicalTimeSlots()
  const details = [...rawDetails].sort((a, b) => order.indexOf(a.time_slot) - order.indexOf(b.time_slot))

  return { record, details }
}

/**
 * screen-041--form-threshing — upserts every row in `rows` against
 * `threshing_detail` (rows with an existing `id` UPDATEd in place, rows
 * without one INSERTed with a freshly generated id via
 * `generateDetailId()`), then DELETEs every id in `idsToDelete` (rows the
 * user removed via "Hapus baris" on an already-loaded draft). Shared by
 * `saveDraft()` and `pauseDraftWithFormData()` so both stay in lock-step on
 * this upsert/delete contract — mirrors cagesTrackRecordRepo.ts's
 * `applyTippedTimeRowChanges()` exactly.
 */
async function applyDetailRowChanges(
  recordId: string,
  rows: ThreshingDetailFormRow[],
  idsToDelete: string[],
  timestamp: string,
): Promise<void> {
  for (const row of rows) {
    if (row.id) {
      await run(
        `UPDATE threshing_detail
         SET time_slot = ?,
             ffb_throughput_mt_hour = ?,
             thresher_drum_speed_rpm = ?,
             motor_current_amps = ?,
             unstripped_bunch_count_percent = ?,
             empty_bunch_oil_loss_percent = ?,
             downtime_reason = ?,
             updated_at = ?
         WHERE id = ?`,
        [
          row.time_slot,
          row.ffb_throughput_mt_hour,
          row.thresher_drum_speed_rpm,
          row.motor_current_amps,
          row.unstripped_bunch_count_percent,
          row.empty_bunch_oil_loss_percent,
          row.downtime_reason || null,
          timestamp,
          row.id,
        ],
      )
      continue
    }

    const detailId = generateDetailId()

    await run(
      `INSERT INTO threshing_detail
         (id, threshing_record_id, time_slot, ffb_throughput_mt_hour, thresher_drum_speed_rpm, motor_current_amps, unstripped_bunch_count_percent, empty_bunch_oil_loss_percent, downtime_reason, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        detailId,
        recordId,
        row.time_slot,
        row.ffb_throughput_mt_hour,
        row.thresher_drum_speed_rpm,
        row.motor_current_amps,
        row.unstripped_bunch_count_percent,
        row.empty_bunch_oil_loss_percent,
        row.downtime_reason || null,
        timestamp,
        timestamp,
      ],
    )
  }

  for (const id of idsToDelete) {
    await run(`DELETE FROM threshing_detail WHERE id = ?`, [id])
  }
}

/**
 * screen-041--form-threshing business_logic step 9 — 'Simpan'.
 *
 * Throws ThreshingDetailRequiredError when zero rows in the given `details`
 * are "valid" (a selected `time_slot` AND at least one filled reading
 * column — see isRowFilled()) — enforced here (not only as a client-side
 * pre-check in FormThreshingView.vue) so the rule holds even if a caller
 * bypasses the UI. No DB write happens at all in that case. Mirrors
 * cagesTrackRecordRepo.ts's saveDraft() "at least one valid row" gate
 * exactly. Required-header-field validation (thresher_id) is the caller's
 * job — this function assumes the header has already been validated.
 *
 * checked_by/acknowledged_by role-stripping: mirrors
 * cagesTrackRecordRepo.ts's saveDraft() — whenever currentUserRole is not
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
  headerData: ThreshingHeaderFormData,
  details: ThreshingDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: ThreshingActorRole | null | undefined,
): Promise<ThreshingDraftWithDetails> {
  const validRowCount = details.filter(
    (row) => row.time_slot !== null && row.time_slot !== undefined && row.time_slot !== '' && isRowFilled(row),
  ).length

  if (validRowCount === 0) {
    throw new ThreshingDetailRequiredError()
  }

  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE threshing_record
     SET thresher_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'saved',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.thresher_id || null,
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
    throw new Error('Gagal memuat ulang data threshing setelah disimpan.')
  }

  return saved
}

/**
 * screen-041--form-threshing business_logic step 10 — 'Pause'. UPDATEs the
 * header as-is (no required-field validation, no "at least one valid row"
 * gate), status='draft_paused', then applies the same upsert/delete
 * contract as saveDraft() via applyDetailRowChanges(). Same role-stripping
 * as saveDraft(). Returns the freshly paused draft.
 */
export async function pauseDraftWithFormData(
  recordId: string,
  headerData: ThreshingHeaderFormData,
  details: ThreshingDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: ThreshingActorRole | null | undefined,
): Promise<ThreshingDraftWithDetails> {
  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE threshing_record
     SET thresher_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'draft_paused',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.thresher_id || null,
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
    throw new Error('Gagal menyimpan progres threshing setelah pause.')
  }

  return saved
}

/**
 * screen-041--form-threshing business_logic step 11 — 'Clear' (after UI
 * confirm). Permanently DELETEs the record, cascading the delete to all of
 * its threshing_detail rows first (application-level cascade, same pattern
 * as cagesTrackRecordRepo.ts's deleteDraft()).
 */
export async function deleteDraft(recordId: string): Promise<void> {
  await run(`DELETE FROM threshing_detail WHERE threshing_record_id = ?`, [recordId])
  await run(`DELETE FROM threshing_record WHERE id = ?`, [recordId])
}

export const threshingRecordRepo = {
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

export default threshingRecordRepo

export const THRESHING_DRAFT_STATUSES = DRAFT_STATUSES
