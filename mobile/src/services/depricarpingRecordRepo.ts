import { query, run } from '@/services/localDb'

/**
 * depricarpingRecordRepo — screen-039--monitor-depricarping /
 * usecase-039--monitor-depricarping, screen-043--form-depricarping /
 * usecase-043--form-depricarping, screen-047--data-preview-depricarping /
 * usecase-047--data-preview-depricarping. Local (offline) read/write access
 * to the `depricarping_record` / `depricarping_detail` tables (schema
 * defined in localSchema.ts) — mirrors pressingRecordRepo.ts's/
 * threshingRecordRepo.ts's repo style (plain async functions over
 * localDb.ts's `query`/`run` primitives) exactly, adapted for
 * Depricarping's own header field (`presser_id` — labeled "Presser ID",
 * same literal field name as Pressing per entity-catalog, not a typo) and
 * 8 reading columns (7 numeric + 1 free-text) plus a separate integer
 * `downtime_minutes` column.
 *
 * REVISED (2026-08-24, entity-catalog v12): depricarping_detail is now a
 * DYNAMIC add-row/remove-row grid — exactly the same pattern as
 * cagesTrackRecordRepo.ts's `cages_tipped_time` (`tippedTimeRows`/
 * `pendingDeletionIds` in FormCagesTrackView.vue) and
 * threshingRecordRepo.ts/pressingRecordRepo.ts (Depricarping's structural
 * siblings, fixed moments earlier). This REPLACES the original design (a
 * FIXED set of exactly 24 pre-created rows, one per hourly time-slot,
 * inserted all at once by `createDraft()`), which the user explicitly
 * rejected as wasting screen space. `createDraft()` now inserts ONLY the
 * header row — no detail rows at all. Rows are added one at a time via
 * `applyDetailRowChanges()` (called from `saveDraft()`/
 * `pauseDraftWithFormData()`, mirrors pressingRecordRepo.ts's
 * `applyDetailRowChanges()`): rows with an existing `id` are UPDATEd, rows
 * without one are INSERTed with a freshly generated id via
 * `generateDetailId()`, and every id in the caller-supplied `idsToDelete`
 * list is DELETEd after the upserts. `canonicalTimeSlots()` is KEPT — no
 * longer used to pre-generate rows, but still the source of truth for the
 * dropdown's ordered option list (FormDepricarpingView.vue's
 * `availableTimeSlotOptions()`) and for `getDraftWithDetails()`'s sort
 * order.
 *
 * STRUCTURAL DIFFERENCE FROM THRESHING/PRESSING: those two stations have a
 * single free-text "Downtime Reason" column. Depricarping's source log
 * sheet instead has TWO separate columns — `downtime_minutes` (integer
 * duration in minutes) and `findings` (free-text notes) — kept as two
 * distinct fields end-to-end (this repo, the Vue views, the backend
 * service/migration), never merged into one. This split predates the
 * dynamic-row fix and is preserved unchanged by it.
 *
 * "Filled" row, for the required-row validation only (not a DB constraint):
 * a depricarping_detail row counts as filled when at least one of its 8
 * reading columns (fan_static_pressure_mmh2o, polishing_drum_speed_rpm,
 * air_velocity_ms, fibre_moisture_percent, kernel_recovery_in_fibre_percent,
 * nut_silo_1_temp_c, nut_silo_2_temp_c, downtime_minutes) OR `findings` is
 * non-null/non-empty — i.e. EITHER downtime_minutes OR findings (or any
 * other reading column) satisfies isRowFilled(), matching the pre-fix
 * behavior's semantics. The server-side DepricarpingRecord::booted()
 * `saving` guard ("minimal satu depricarping-detail sebelum status=saved")
 * checks row EXISTENCE, satisfied once at least one row has been added —
 * the REAL "minimal satu baris terisi" business rule (a selected
 * `time_slot` AND at least one filled reading column) is enforced at this
 * repo level (`saveDraft()`) and again in FormDepricarpingView.vue's
 * client-side validation, mirroring pressingRecordRepo.ts's saveDraft()
 * "at least one valid row" gate exactly.
 */

export type DepricarpingDraftStatus = 'draft_ongoing' | 'draft_paused'
export type DepricarpingRecordStatus = DepricarpingDraftStatus | 'saved' | 'synced'

/**
 * Full local `depricarping_record` row shape (mirrors localSchema.ts's
 * CREATE_DEPRICARPING_RECORD column-for-column).
 */
export interface DepricarpingRecord {
  id: string
  station_id: string | null
  presser_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  checked_by_name?: string | null
  acknowledged_by_name?: string | null
  status: DepricarpingRecordStatus
  created_by: string
  created_at: string
  updated_at: string
}

/**
 * Full local `depricarping_detail` row shape (mirrors localSchema.ts's
 * CREATE_DEPRICARPING_DETAIL column-for-column).
 */
export interface DepricarpingDetailRow {
  id: string
  depricarping_record_id: string
  time_slot: string
  fan_static_pressure_mmh2o: number | null
  polishing_drum_speed_rpm: number | null
  air_velocity_ms: number | null
  fibre_moisture_percent: number | null
  kernel_recovery_in_fibre_percent: number | null
  nut_silo_1_temp_c: number | null
  nut_silo_2_temp_c: number | null
  downtime_minutes: number | null
  findings: string | null
  created_at: string
  updated_at: string
}

export interface DepricarpingDraftWithDetails {
  record: DepricarpingRecord
  details: DepricarpingDetailRow[]
}

/**
 * Subset of `DepricarpingRecord` columns the Form Depricarping screen
 * collects from the user (excludes id/station_id/created_by/created_at/
 * updated_at/status, which are managed by createDraft()/saveDraft()
 * itself).
 */
export interface DepricarpingHeaderFormData {
  presser_id: string
  date: string
  note: string
  checked_by: string
  acknowledged_by: string
}

/**
 * One editable `depricarping_detail` row as held in
 * FormDepricarpingView.vue's form state. `id` is present for rows loaded
 * from an existing draft (UPDATE target) and absent for rows added in this
 * editing session (INSERT target) — `saveDraft()`/`pauseDraftWithFormData()`
 * upsert on exactly this distinction, mirroring PressingDetailFormRow
 * exactly. `time_slot` is `null` until the user picks one from the dropdown
 * for a newly-added row.
 */
export interface DepricarpingDetailFormRow {
  id?: string
  time_slot: string | null
  fan_static_pressure_mmh2o: number | null
  polishing_drum_speed_rpm: number | null
  air_velocity_ms: number | null
  fibre_moisture_percent: number | null
  kernel_recovery_in_fibre_percent: number | null
  nut_silo_1_temp_c: number | null
  nut_silo_2_temp_c: number | null
  downtime_minutes: number | null
  findings: string | null
}

/**
 * User roles a saveDraft()/pauseDraftWithFormData() caller may pass as
 * currentUserRole — plain string union, matching pressingRecordRepo.ts's
 * PressingActorRole, decoupling this repo from the auth store.
 */
export type DepricarpingActorRole = 'operator' | 'supervisor' | 'mill_management' | 'admin'

/**
 * screen-043--form-depricarping business_logic step 9 — thrown by
 * saveDraft() when zero rows in the given `details` are "valid" (a selected
 * `time_slot` AND at least one filled reading column), so callers
 * (FormDepricarpingView.vue) can distinguish this from other save failures —
 * mirrors PressingDetailRequiredError exactly.
 */
export class DepricarpingDetailRequiredError extends Error {
  constructor() {
    super('Minimal 1 baris Depricarping Detail (Time-Slot terpilih + minimal 1 kolom bacaan terisi) harus diisi sebelum menyimpan.')
    this.name = 'DepricarpingDetailRequiredError'
  }
}

export interface DepricarpingDraftListItem {
  id: string
  status: DepricarpingDraftStatus
  presser_id: string | null
  updated_at: string
}

interface DraftListRow {
  id: string
  status: DepricarpingDraftStatus
  presser_id: string | null
  updated_at: string
}

export interface DepricarpingTodaySummary {
  countDepricarpingRecord: number
  detailRowCount: number
}

interface TodayRecordIdRow {
  id: string
}

interface DetailRowCountRow {
  detail_row_count: number
}

const DRAFT_STATUSES: DepricarpingDraftStatus[] = ['draft_ongoing', 'draft_paused']

function nowIso(): string {
  return new Date().toISOString()
}

function generateId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `dpc-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function generateDetailId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `dpd-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

/**
 * The 24 canonical hourly time-slot labels, in order: 07:00, 08:00, ...,
 * 23:00, 00:00, ..., 06:00 (i.e. starting at hour 7, wrapping past midnight).
 * Shared by any caller needing the canonical order (e.g. the dropdown's
 * ordered option list, tests). `for i in 0..23 -> hour = (7 + i) % 24`.
 * Identical to threshingRecordRepo.ts's/pressingRecordRepo.ts's
 * canonicalTimeSlots().
 */
export function canonicalTimeSlots(): string[] {
  return Array.from({ length: 24 }, (_, i) => {
    const hour = (7 + i) % 24
    return `${String(hour).padStart(2, '0')}:00`
  })
}

/**
 * Returns true when a depricarping_detail row has at least one of its 8
 * reading columns non-null/non-empty (fan_static_pressure_mmh2o,
 * polishing_drum_speed_rpm, air_velocity_ms, fibre_moisture_percent,
 * kernel_recovery_in_fibre_percent, nut_silo_1_temp_c, nut_silo_2_temp_c,
 * downtime_minutes) OR `findings` is non-empty — EITHER downtime_minutes OR
 * findings satisfies this, not both required — see this file's header
 * comment for the "filled row" definition.
 */
function isRowFilled(row: {
  fan_static_pressure_mmh2o: number | null
  polishing_drum_speed_rpm: number | null
  air_velocity_ms: number | null
  fibre_moisture_percent: number | null
  kernel_recovery_in_fibre_percent: number | null
  nut_silo_1_temp_c: number | null
  nut_silo_2_temp_c: number | null
  downtime_minutes: number | null
  findings: string | null
}): boolean {
  return (
    row.fan_static_pressure_mmh2o !== null ||
    row.polishing_drum_speed_rpm !== null ||
    row.air_velocity_ms !== null ||
    row.fibre_moisture_percent !== null ||
    row.kernel_recovery_in_fibre_percent !== null ||
    row.nut_silo_1_temp_c !== null ||
    row.nut_silo_2_temp_c !== null ||
    row.downtime_minutes !== null ||
    (row.findings !== null && row.findings !== '')
  )
}

/**
 * screen-039--monitor-depricarping business_logic step 5 — 'New Data'.
 * INSERTs a new depricarping_record row ONLY (status=draft_ongoing,
 * created_by=current user, date=now — auto-filled once at creation,
 * mirrors cages_track_record.tippler_start_time) — no depricarping_detail
 * rows are pre-created (2026-08-24 revision, entity-catalog v12: rows are
 * added one at a time by the user via "Tambah baris" in
 * FormDepricarpingView.vue, same pattern as pressingRecordRepo.ts's
 * `pressing_detail`). Returns the new record's id so the caller can
 * navigate to Form Depricarping with it.
 */
export async function createDraft(userId: string): Promise<string> {
  const id = generateId()
  const timestamp = nowIso()

  await run(
    `INSERT INTO depricarping_record (id, status, created_by, date, created_at, updated_at)
     VALUES (?, 'draft_ongoing', ?, ?, ?, ?)`,
    [id, userId, timestamp, timestamp, timestamp],
  )

  return id
}

/**
 * screen-039--monitor-depricarping business_logic step 2 — every local
 * depricarping_record the current user has ongoing or paused, most-
 * recently-updated first. Mirrors pressingRecordRepo.ts's getDrafts().
 */
export async function getDrafts(userId: string): Promise<DepricarpingDraftListItem[]> {
  const rows = await query<DraftListRow>(
    `SELECT id, status, presser_id, updated_at
     FROM depricarping_record
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
 * screen-039--monitor-depricarping business_logic step 1 — "Hari Ini"
 * counters. "Jumlah Depricarping Record" counts the current user's
 * depricarping_record rows (any status) dated today (device-local day
 * boundary). "Jumlah Baris Time-Slot Tercatat" is a plain COUNT of
 * depricarping_detail rows belonging to those same records — NOT filtered
 * by isRowFilled() (2026-08-24 revision: since rows are no longer
 * pre-created, every existing row was explicitly added by the user via
 * "Tambah baris", so row EXISTENCE itself is the meaningful signal now —
 * mirrors pressingRecordRepo.ts's getTodaySummary() `detailRowCount` "count
 * of child rows added" shape, rather than "filled rows out of a fixed 24").
 * Two sequential queries (today's record ids, then a scoped COUNT), skipping
 * the second query entirely when there are no matching ids (an empty
 * `IN ()` list is invalid SQL) — same shape as pressingRecordRepo.ts's
 * getTodaySummary().
 */
export async function getTodaySummary(userId: string): Promise<DepricarpingTodaySummary> {
  const idRows = await query<TodayRecordIdRow>(
    `SELECT id
     FROM depricarping_record
     WHERE created_by = ? AND date(date) = date('now', 'localtime')`,
    [userId],
  )

  const countDepricarpingRecord = idRows.length

  if (countDepricarpingRecord === 0) {
    return { countDepricarpingRecord: 0, detailRowCount: 0 }
  }

  const placeholders = idRows.map(() => '?').join(', ')

  const countRows = await query<DetailRowCountRow>(
    `SELECT COUNT(*) AS detail_row_count FROM depricarping_detail WHERE depricarping_record_id IN (${placeholders})`,
    idRows.map((row) => row.id),
  )

  return {
    countDepricarpingRecord,
    detailRowCount: countRows[0]?.detail_row_count ?? 0,
  }
}

/**
 * screen-047--data-preview-depricarping business_logic step 1 — every local
 * depricarping_record row for the current user, ANY status, most-recently-
 * updated first. Mirrors pressingRecordRepo.ts's getAllRecords().
 */
export async function getAllRecords(userId: string): Promise<DepricarpingRecord[]> {
  return query<DepricarpingRecord>(
    `SELECT * FROM depricarping_record WHERE created_by = ? ORDER BY updated_at DESC`,
    [userId],
  )
}

/**
 * screen-043--form-depricarping business_logic step 1 — Form Depricarping
 * loads an existing draft by route param id, plus all of its
 * depricarping_detail rows (however many the user has added so far — no
 * longer always 24), ordered by time_slot ascending starting at 07:00
 * (canonical order, not alphabetical — '00:00' would otherwise sort before
 * '07:00'). Returns null when no header row matches (defensive).
 */
export async function getDraftWithDetails(recordId: string): Promise<DepricarpingDraftWithDetails | null> {
  const recordRows = await query<DepricarpingRecord>(`SELECT * FROM depricarping_record WHERE id = ?`, [recordId])
  const record = recordRows[0]

  if (!record) {
    return null
  }

  const rawDetails = await query<DepricarpingDetailRow>(
    `SELECT * FROM depricarping_detail WHERE depricarping_record_id = ?`,
    [recordId],
  )

  const order = canonicalTimeSlots()
  const details = [...rawDetails].sort((a, b) => order.indexOf(a.time_slot) - order.indexOf(b.time_slot))

  return { record, details }
}

/**
 * screen-043--form-depricarping — upserts every row in `rows` against
 * `depricarping_detail` (rows with an existing `id` UPDATEd in place, rows
 * without one INSERTed with a freshly generated id via
 * `generateDetailId()`), then DELETEs every id in `idsToDelete` (rows the
 * user removed via "Hapus baris" on an already-loaded draft). Shared by
 * `saveDraft()` and `pauseDraftWithFormData()` so both stay in lock-step on
 * this upsert/delete contract — mirrors pressingRecordRepo.ts's
 * `applyDetailRowChanges()` exactly, preserving the downtime_minutes/
 * findings split as two distinct columns.
 */
async function applyDetailRowChanges(
  recordId: string,
  rows: DepricarpingDetailFormRow[],
  idsToDelete: string[],
  timestamp: string,
): Promise<void> {
  for (const row of rows) {
    if (row.id) {
      await run(
        `UPDATE depricarping_detail
         SET time_slot = ?,
             fan_static_pressure_mmh2o = ?,
             polishing_drum_speed_rpm = ?,
             air_velocity_ms = ?,
             fibre_moisture_percent = ?,
             kernel_recovery_in_fibre_percent = ?,
             nut_silo_1_temp_c = ?,
             nut_silo_2_temp_c = ?,
             downtime_minutes = ?,
             findings = ?,
             updated_at = ?
         WHERE id = ?`,
        [
          row.time_slot,
          row.fan_static_pressure_mmh2o,
          row.polishing_drum_speed_rpm,
          row.air_velocity_ms,
          row.fibre_moisture_percent,
          row.kernel_recovery_in_fibre_percent,
          row.nut_silo_1_temp_c,
          row.nut_silo_2_temp_c,
          row.downtime_minutes,
          row.findings || null,
          timestamp,
          row.id,
        ],
      )
      continue
    }

    const detailId = generateDetailId()

    await run(
      `INSERT INTO depricarping_detail
         (id, depricarping_record_id, time_slot, fan_static_pressure_mmh2o, polishing_drum_speed_rpm, air_velocity_ms, fibre_moisture_percent, kernel_recovery_in_fibre_percent, nut_silo_1_temp_c, nut_silo_2_temp_c, downtime_minutes, findings, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        detailId,
        recordId,
        row.time_slot,
        row.fan_static_pressure_mmh2o,
        row.polishing_drum_speed_rpm,
        row.air_velocity_ms,
        row.fibre_moisture_percent,
        row.kernel_recovery_in_fibre_percent,
        row.nut_silo_1_temp_c,
        row.nut_silo_2_temp_c,
        row.downtime_minutes,
        row.findings || null,
        timestamp,
        timestamp,
      ],
    )
  }

  for (const id of idsToDelete) {
    await run(`DELETE FROM depricarping_detail WHERE id = ?`, [id])
  }
}

/**
 * screen-043--form-depricarping business_logic step 9 — 'Simpan'.
 *
 * Throws DepricarpingDetailRequiredError when zero rows in the given
 * `details` are "valid" (a selected `time_slot` AND at least one filled
 * reading column — see isRowFilled()) — enforced here (not only as a
 * client-side pre-check in FormDepricarpingView.vue) so the rule holds even
 * if a caller bypasses the UI. No DB write happens at all in that case.
 * Mirrors pressingRecordRepo.ts's saveDraft() "at least one valid row" gate
 * exactly. Required-header-field validation (presser_id) is the caller's
 * job — this function assumes the header has already been validated.
 *
 * checked_by/acknowledged_by role-stripping: mirrors
 * pressingRecordRepo.ts's saveDraft() — whenever currentUserRole is not
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
  headerData: DepricarpingHeaderFormData,
  details: DepricarpingDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: DepricarpingActorRole | null | undefined,
): Promise<DepricarpingDraftWithDetails> {
  const validRowCount = details.filter(
    (row) => row.time_slot !== null && row.time_slot !== undefined && row.time_slot !== '' && isRowFilled(row),
  ).length

  if (validRowCount === 0) {
    throw new DepricarpingDetailRequiredError()
  }

  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE depricarping_record
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
    throw new Error('Gagal memuat ulang data depricarping setelah disimpan.')
  }

  return saved
}

/**
 * screen-043--form-depricarping business_logic step 10 — 'Pause'. UPDATEs
 * the header as-is (no required-field validation, no "at least one valid
 * row" gate), status='draft_paused', then applies the same upsert/delete
 * contract as saveDraft() via applyDetailRowChanges(). Same role-stripping
 * as saveDraft(). Returns the freshly paused draft.
 */
export async function pauseDraftWithFormData(
  recordId: string,
  headerData: DepricarpingHeaderFormData,
  details: DepricarpingDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: DepricarpingActorRole | null | undefined,
): Promise<DepricarpingDraftWithDetails> {
  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE depricarping_record
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
    throw new Error('Gagal menyimpan progres depricarping setelah pause.')
  }

  return saved
}

/**
 * screen-043--form-depricarping business_logic step 11 — 'Clear' (after UI
 * confirm). Permanently DELETEs the record, cascading the delete to all of
 * its depricarping_detail rows first (application-level cascade, same
 * pattern as pressingRecordRepo.ts's deleteDraft()).
 */
export async function deleteDraft(recordId: string): Promise<void> {
  await run(`DELETE FROM depricarping_detail WHERE depricarping_record_id = ?`, [recordId])
  await run(`DELETE FROM depricarping_record WHERE id = ?`, [recordId])
}

export const depricarpingRecordRepo = {
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

export default depricarpingRecordRepo

export const DEPRICARPING_DRAFT_STATUSES = DRAFT_STATUSES
