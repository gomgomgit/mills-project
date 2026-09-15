import { query, run } from '@/services/localDb'

/**
 * kernelPlantRecordRepo — screen-040--monitor-kernel-plant /
 * usecase-040--monitor-kernel-plant, screen-044--form-kernel-plant /
 * usecase-044--form-kernel-plant, screen-048--data-preview-kernel-plant /
 * usecase-048--data-preview-kernel-plant. Local (offline) read/write access
 * to the `kernel_plant_record` / `kernel_plant_detail` tables (schema
 * defined in localSchema.ts) — mirrors depricarpingRecordRepo.ts's repo
 * style (plain async functions over localDb.ts's `query`/`run`
 * primitives) exactly, adapted for Kernel Plant's own header field
 * (`kernel_plant_id` — labeled "Kernel Plant ID") and 9 reading columns (7
 * numeric + 1 integer downtime + 1 free-text).
 *
 * REVISED (2026-08-24, entity-catalog v12): kernel_plant_detail is now a
 * DYNAMIC add-row/remove-row grid — exactly the same pattern as
 * cagesTrackRecordRepo.ts's `cages_tipped_time` (`tippedTimeRows`/
 * `pendingDeletionIds` in FormCagesTrackView.vue) and
 * threshingRecordRepo.ts/pressingRecordRepo.ts/depricarpingRecordRepo.ts
 * (Kernel Plant's structural siblings, fixed moments earlier). This
 * REPLACES the original design (a FIXED set of exactly 24 pre-created rows,
 * one per hourly time-slot, inserted all at once by `createDraft()`), which
 * the user explicitly rejected as wasting screen space. `createDraft()` now
 * inserts ONLY the header row — no detail rows at all. Rows are added one at
 * a time via `applyDetailRowChanges()` (called from `saveDraft()`/
 * `pauseDraftWithFormData()`, mirrors depricarpingRecordRepo.ts's
 * `applyDetailRowChanges()`): rows with an existing `id` are UPDATEd, rows
 * without one are INSERTed with a freshly generated id via
 * `generateDetailId()`, and every id in the caller-supplied `idsToDelete`
 * list is DELETEd after the upserts. `canonicalTimeSlots()` is KEPT — no
 * longer used to pre-generate rows, but still the source of truth for the
 * dropdown's ordered option list (FormKernelPlantView.vue's
 * `availableTimeSlotOptions()`) and for `getDraftWithDetails()`'s sort
 * order.
 *
 * STRUCTURAL SHAPE MATCHES DEPRICARPING (not Threshing/Pressing): Kernel
 * Plant's source log sheet has TWO separate columns — `downtime_minutes`
 * (integer duration in minutes) and `findings` (free-text notes) — kept as
 * two distinct fields end-to-end (this repo, the Vue views, the backend
 * service/migration), never merged into one. This split predates the
 * dynamic-row fix and is preserved unchanged by it.
 *
 * "Filled" row, for the required-row validation only (not a DB constraint):
 * a kernel_plant_detail row counts as filled when at least one of its 9
 * reading columns (ripple_mill_1_amps, ripple_mill_2_amps,
 * claybath_hydro_sg, kernel_silo_1_temp_c, kernel_silo_2_temp_c,
 * kernel_moisture_percent, shell_loss_percent, downtime_minutes) OR
 * `findings` is non-null/non-empty — i.e. EITHER downtime_minutes OR
 * findings (or any other reading column) satisfies isRowFilled(), matching
 * the pre-fix behavior's semantics. The server-side KernelPlantRecord::booted()
 * `saving` guard ("minimal satu kernel-plant-detail sebelum status=saved")
 * checks row EXISTENCE, satisfied once at least one row has been added —
 * the REAL "minimal satu baris terisi" business rule (a selected
 * `time_slot` AND at least one filled reading column) is enforced at this
 * repo level (`saveDraft()`) and again in FormKernelPlantView.vue's
 * client-side validation, mirroring depricarpingRecordRepo.ts's saveDraft()
 * "at least one valid row" gate exactly.
 */

export type KernelPlantDraftStatus = 'draft_ongoing' | 'draft_paused'
export type KernelPlantRecordStatus = KernelPlantDraftStatus | 'saved' | 'synced'

/**
 * Full local `kernel_plant_record` row shape (mirrors localSchema.ts's
 * CREATE_KERNEL_PLANT_RECORD column-for-column).
 */
export interface KernelPlantRecord {
  id: string
  station_id: string | null
  kernel_plant_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  checked_by_name?: string | null
  acknowledged_by_name?: string | null
  status: KernelPlantRecordStatus
  created_by: string
  created_at: string
  updated_at: string
}

/**
 * Full local `kernel_plant_detail` row shape (mirrors localSchema.ts's
 * CREATE_KERNEL_PLANT_DETAIL column-for-column).
 */
export interface KernelPlantDetailRow {
  id: string
  kernel_plant_record_id: string
  time_slot: string
  ripple_mill_1_amps: number | null
  ripple_mill_2_amps: number | null
  claybath_hydro_sg: number | null
  kernel_silo_1_temp_c: number | null
  kernel_silo_2_temp_c: number | null
  kernel_moisture_percent: number | null
  shell_loss_percent: number | null
  downtime_minutes: number | null
  findings: string | null
  created_at: string
  updated_at: string
}

export interface KernelPlantDraftWithDetails {
  record: KernelPlantRecord
  details: KernelPlantDetailRow[]
}

/**
 * Subset of `KernelPlantRecord` columns the Form Kernel Plant screen
 * collects from the user (excludes id/station_id/created_by/created_at/
 * updated_at/status, which are managed by createDraft()/saveDraft()
 * itself).
 */
export interface KernelPlantHeaderFormData {
  kernel_plant_id: string
  date: string
  note: string
  checked_by: string
  acknowledged_by: string
}

/**
 * One editable `kernel_plant_detail` row as held in
 * FormKernelPlantView.vue's form state. `id` is present for rows loaded
 * from an existing draft (UPDATE target) and absent for rows added in this
 * editing session (INSERT target) — `saveDraft()`/`pauseDraftWithFormData()`
 * upsert on exactly this distinction, mirroring DepricarpingDetailFormRow
 * exactly. `time_slot` is `null` until the user picks one from the dropdown
 * for a newly-added row.
 */
export interface KernelPlantDetailFormRow {
  id?: string
  time_slot: string | null
  ripple_mill_1_amps: number | null
  ripple_mill_2_amps: number | null
  claybath_hydro_sg: number | null
  kernel_silo_1_temp_c: number | null
  kernel_silo_2_temp_c: number | null
  kernel_moisture_percent: number | null
  shell_loss_percent: number | null
  downtime_minutes: number | null
  findings: string | null
}

/**
 * User roles a saveDraft()/pauseDraftWithFormData() caller may pass as
 * currentUserRole — plain string union, matching depricarpingRecordRepo.ts's
 * DepricarpingActorRole, decoupling this repo from the auth store.
 */
export type KernelPlantActorRole = 'operator' | 'supervisor' | 'mill_management' | 'admin'

/**
 * screen-044--form-kernel-plant business_logic step 9 — thrown by
 * saveDraft() when zero rows in the given `details` are "valid" (a selected
 * `time_slot` AND at least one filled reading column), so callers
 * (FormKernelPlantView.vue) can distinguish this from other save failures —
 * mirrors DepricarpingDetailRequiredError exactly.
 */
export class KernelPlantDetailRequiredError extends Error {
  constructor() {
    super('Minimal 1 baris Kernel Plant Detail (Time-Slot terpilih + minimal 1 kolom bacaan terisi) harus diisi sebelum menyimpan.')
    this.name = 'KernelPlantDetailRequiredError'
  }
}

export interface KernelPlantDraftListItem {
  id: string
  status: KernelPlantDraftStatus
  kernel_plant_id: string | null
  updated_at: string
}

interface DraftListRow {
  id: string
  status: KernelPlantDraftStatus
  kernel_plant_id: string | null
  updated_at: string
}

export interface KernelPlantTodaySummary {
  countKernelPlantRecord: number
  detailRowCount: number
}

interface TodayRecordIdRow {
  id: string
}

interface DetailRowCountRow {
  detail_row_count: number
}

const DRAFT_STATUSES: KernelPlantDraftStatus[] = ['draft_ongoing', 'draft_paused']

function nowIso(): string {
  return new Date().toISOString()
}

function generateId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `kpc-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function generateDetailId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `kpd-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

/**
 * The 24 canonical hourly time-slot labels, in order: 07:00, 08:00, ...,
 * 23:00, 00:00, ..., 06:00 (i.e. starting at hour 7, wrapping past midnight).
 * Shared by any caller needing the canonical order (e.g. the dropdown's
 * ordered option list, tests). `for i in 0..23 -> hour = (7 + i) % 24`.
 * Identical to depricarpingRecordRepo.ts's/threshingRecordRepo.ts's/
 * pressingRecordRepo.ts's canonicalTimeSlots().
 */
export function canonicalTimeSlots(): string[] {
  return Array.from({ length: 24 }, (_, i) => {
    const hour = (7 + i) % 24
    return `${String(hour).padStart(2, '0')}:00`
  })
}

/**
 * Returns true when a kernel_plant_detail row has at least one of its 8
 * numeric reading columns (ripple_mill_1_amps, ripple_mill_2_amps,
 * claybath_hydro_sg, kernel_silo_1_temp_c, kernel_silo_2_temp_c,
 * kernel_moisture_percent, shell_loss_percent, downtime_minutes)
 * non-null/non-empty OR `findings` is non-empty — EITHER downtime_minutes OR
 * findings satisfies this, not both required — see this file's header
 * comment for the "filled row" definition.
 */
function isRowFilled(row: {
  ripple_mill_1_amps: number | null
  ripple_mill_2_amps: number | null
  claybath_hydro_sg: number | null
  kernel_silo_1_temp_c: number | null
  kernel_silo_2_temp_c: number | null
  kernel_moisture_percent: number | null
  shell_loss_percent: number | null
  downtime_minutes: number | null
  findings: string | null
}): boolean {
  return (
    row.ripple_mill_1_amps !== null ||
    row.ripple_mill_2_amps !== null ||
    row.claybath_hydro_sg !== null ||
    row.kernel_silo_1_temp_c !== null ||
    row.kernel_silo_2_temp_c !== null ||
    row.kernel_moisture_percent !== null ||
    row.shell_loss_percent !== null ||
    row.downtime_minutes !== null ||
    (row.findings !== null && row.findings !== '')
  )
}

/**
 * screen-040--monitor-kernel-plant business_logic step 5 — 'New Data'.
 * INSERTs a new kernel_plant_record row ONLY (status=draft_ongoing,
 * created_by=current user, date=now — auto-filled once at creation) — no
 * kernel_plant_detail rows are pre-created (2026-08-24 revision,
 * entity-catalog v12: rows are added one at a time by the user via "Tambah
 * baris" in FormKernelPlantView.vue, same pattern as
 * depricarpingRecordRepo.ts's `depricarping_detail`). Returns the new
 * record's id so the caller can navigate to Form Kernel Plant with it.
 */
export async function createDraft(userId: string): Promise<string> {
  const id = generateId()
  const timestamp = nowIso()

  await run(
    `INSERT INTO kernel_plant_record (id, status, created_by, date, created_at, updated_at)
     VALUES (?, 'draft_ongoing', ?, ?, ?, ?)`,
    [id, userId, timestamp, timestamp, timestamp],
  )

  return id
}

/**
 * screen-040--monitor-kernel-plant business_logic step 2 — every local
 * kernel_plant_record the current user has ongoing or paused, most-
 * recently-updated first. Mirrors depricarpingRecordRepo.ts's getDrafts().
 */
export async function getDrafts(userId: string): Promise<KernelPlantDraftListItem[]> {
  const rows = await query<DraftListRow>(
    `SELECT id, status, kernel_plant_id, updated_at
     FROM kernel_plant_record
     WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
     ORDER BY updated_at DESC`,
    [userId],
  )

  return rows.map((row) => ({
    id: row.id,
    status: row.status,
    kernel_plant_id: row.kernel_plant_id,
    updated_at: row.updated_at,
  }))
}

/**
 * screen-040--monitor-kernel-plant business_logic step 1 — "Hari Ini"
 * counters. "Jumlah Kernel Plant Record" counts the current user's
 * kernel_plant_record rows (any status) dated today (device-local day
 * boundary). "Jumlah Baris Time-Slot Tercatat" is a plain COUNT of
 * kernel_plant_detail rows belonging to those same records — NOT filtered
 * by isRowFilled() (2026-08-24 revision: since rows are no longer
 * pre-created, every existing row was explicitly added by the user via
 * "Tambah baris", so row EXISTENCE itself is the meaningful signal now —
 * mirrors depricarpingRecordRepo.ts's getTodaySummary() `detailRowCount`
 * "count of child rows added" shape, rather than "filled rows out of a
 * fixed 24"). Two sequential queries (today's record ids, then a scoped
 * COUNT), skipping the second query entirely when there are no matching ids
 * (an empty `IN ()` list is invalid SQL) — same shape as
 * depricarpingRecordRepo.ts's getTodaySummary().
 */
export async function getTodaySummary(userId: string): Promise<KernelPlantTodaySummary> {
  const idRows = await query<TodayRecordIdRow>(
    `SELECT id
     FROM kernel_plant_record
     WHERE created_by = ? AND date(date) = date('now', 'localtime')`,
    [userId],
  )

  const countKernelPlantRecord = idRows.length

  if (countKernelPlantRecord === 0) {
    return { countKernelPlantRecord: 0, detailRowCount: 0 }
  }

  const placeholders = idRows.map(() => '?').join(', ')

  const countRows = await query<DetailRowCountRow>(
    `SELECT COUNT(*) AS detail_row_count FROM kernel_plant_detail WHERE kernel_plant_record_id IN (${placeholders})`,
    idRows.map((row) => row.id),
  )

  return {
    countKernelPlantRecord,
    detailRowCount: countRows[0]?.detail_row_count ?? 0,
  }
}

/**
 * screen-048--data-preview-kernel-plant business_logic step 1 — every local
 * kernel_plant_record row for the current user, ANY status, most-recently-
 * updated first. Mirrors depricarpingRecordRepo.ts's getAllRecords().
 */
export async function getAllRecords(userId: string): Promise<KernelPlantRecord[]> {
  return query<KernelPlantRecord>(
    `SELECT * FROM kernel_plant_record WHERE created_by = ? ORDER BY updated_at DESC`,
    [userId],
  )
}

/**
 * screen-044--form-kernel-plant business_logic step 1 — Form Kernel Plant
 * loads an existing draft by route param id, plus all of its
 * kernel_plant_detail rows (however many the user has added so far — no
 * longer always 24), ordered by time_slot ascending starting at 07:00
 * (canonical order, not alphabetical — '00:00' would otherwise sort before
 * '07:00'). Returns null when no header row matches (defensive).
 */
export async function getDraftWithDetails(recordId: string): Promise<KernelPlantDraftWithDetails | null> {
  const recordRows = await query<KernelPlantRecord>(`SELECT * FROM kernel_plant_record WHERE id = ?`, [recordId])
  const record = recordRows[0]

  if (!record) {
    return null
  }

  const rawDetails = await query<KernelPlantDetailRow>(
    `SELECT * FROM kernel_plant_detail WHERE kernel_plant_record_id = ?`,
    [recordId],
  )

  const order = canonicalTimeSlots()
  const details = [...rawDetails].sort((a, b) => order.indexOf(a.time_slot) - order.indexOf(b.time_slot))

  return { record, details }
}

/**
 * screen-044--form-kernel-plant — upserts every row in `rows` against
 * `kernel_plant_detail` (rows with an existing `id` UPDATEd in place, rows
 * without one INSERTed with a freshly generated id via
 * `generateDetailId()`), then DELETEs every id in `idsToDelete` (rows the
 * user removed via "Hapus baris" on an already-loaded draft). Shared by
 * `saveDraft()` and `pauseDraftWithFormData()` so both stay in lock-step on
 * this upsert/delete contract — mirrors depricarpingRecordRepo.ts's
 * `applyDetailRowChanges()` exactly, preserving the downtime_minutes/
 * findings split as two distinct columns.
 */
async function applyDetailRowChanges(
  recordId: string,
  rows: KernelPlantDetailFormRow[],
  idsToDelete: string[],
  timestamp: string,
): Promise<void> {
  for (const row of rows) {
    if (row.id) {
      await run(
        `UPDATE kernel_plant_detail
         SET time_slot = ?,
             ripple_mill_1_amps = ?,
             ripple_mill_2_amps = ?,
             claybath_hydro_sg = ?,
             kernel_silo_1_temp_c = ?,
             kernel_silo_2_temp_c = ?,
             kernel_moisture_percent = ?,
             shell_loss_percent = ?,
             downtime_minutes = ?,
             findings = ?,
             updated_at = ?
         WHERE id = ?`,
        [
          row.time_slot,
          row.ripple_mill_1_amps,
          row.ripple_mill_2_amps,
          row.claybath_hydro_sg,
          row.kernel_silo_1_temp_c,
          row.kernel_silo_2_temp_c,
          row.kernel_moisture_percent,
          row.shell_loss_percent,
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
      `INSERT INTO kernel_plant_detail
         (id, kernel_plant_record_id, time_slot, ripple_mill_1_amps, ripple_mill_2_amps, claybath_hydro_sg, kernel_silo_1_temp_c, kernel_silo_2_temp_c, kernel_moisture_percent, shell_loss_percent, downtime_minutes, findings, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        detailId,
        recordId,
        row.time_slot,
        row.ripple_mill_1_amps,
        row.ripple_mill_2_amps,
        row.claybath_hydro_sg,
        row.kernel_silo_1_temp_c,
        row.kernel_silo_2_temp_c,
        row.kernel_moisture_percent,
        row.shell_loss_percent,
        row.downtime_minutes,
        row.findings || null,
        timestamp,
        timestamp,
      ],
    )
  }

  for (const id of idsToDelete) {
    await run(`DELETE FROM kernel_plant_detail WHERE id = ?`, [id])
  }
}

/**
 * screen-044--form-kernel-plant business_logic step 9 — 'Simpan'.
 *
 * Throws KernelPlantDetailRequiredError when zero rows in the given
 * `details` are "valid" (a selected `time_slot` AND at least one filled
 * reading column — see isRowFilled()) — enforced here (not only as a
 * client-side pre-check in FormKernelPlantView.vue) so the rule holds even
 * if a caller bypasses the UI. No DB write happens at all in that case.
 * Mirrors depricarpingRecordRepo.ts's saveDraft() "at least one valid row"
 * gate exactly. Required-header-field validation (kernel_plant_id) is the
 * caller's job — this function assumes the header has already been
 * validated.
 *
 * checked_by/acknowledged_by role-stripping: mirrors
 * depricarpingRecordRepo.ts's saveDraft() — whenever currentUserRole is not
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
  headerData: KernelPlantHeaderFormData,
  details: KernelPlantDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: KernelPlantActorRole | null | undefined,
): Promise<KernelPlantDraftWithDetails> {
  const validRowCount = details.filter(
    (row) => row.time_slot !== null && row.time_slot !== undefined && row.time_slot !== '' && isRowFilled(row),
  ).length

  if (validRowCount === 0) {
    throw new KernelPlantDetailRequiredError()
  }

  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE kernel_plant_record
     SET kernel_plant_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'saved',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.kernel_plant_id || null,
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
    throw new Error('Gagal memuat ulang data kernel plant setelah disimpan.')
  }

  return saved
}

/**
 * screen-044--form-kernel-plant business_logic step 10 — 'Pause'. UPDATEs
 * the header as-is (no required-field validation, no "at least one valid
 * row" gate), status='draft_paused', then applies the same upsert/delete
 * contract as saveDraft() via applyDetailRowChanges(). Same role-stripping
 * as saveDraft(). Returns the freshly paused draft.
 */
export async function pauseDraftWithFormData(
  recordId: string,
  headerData: KernelPlantHeaderFormData,
  details: KernelPlantDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: KernelPlantActorRole | null | undefined,
): Promise<KernelPlantDraftWithDetails> {
  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE kernel_plant_record
     SET kernel_plant_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'draft_paused',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.kernel_plant_id || null,
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
    throw new Error('Gagal menyimpan progres kernel plant setelah pause.')
  }

  return saved
}

/**
 * screen-044--form-kernel-plant business_logic step 11 — 'Clear' (after UI
 * confirm). Permanently DELETEs the record, cascading the delete to all of
 * its kernel_plant_detail rows first (application-level cascade, same
 * pattern as depricarpingRecordRepo.ts's deleteDraft()).
 */
export async function deleteDraft(recordId: string): Promise<void> {
  await run(`DELETE FROM kernel_plant_detail WHERE kernel_plant_record_id = ?`, [recordId])
  await run(`DELETE FROM kernel_plant_record WHERE id = ?`, [recordId])
}

export const kernelPlantRecordRepo = {
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

export default kernelPlantRecordRepo

export const KERNEL_PLANT_DRAFT_STATUSES = DRAFT_STATUSES
