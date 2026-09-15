import { query, run } from '@/services/localDb'

/**
 * effluentPlantRecordRepo — screen-065--monitor-effluent-plant /
 * usecase-085--monitor-effluent-plant, screen-075--form-effluent-plant /
 * usecase-086--form-effluent-plant, screen-085--data-preview-effluent-plant /
 * usecase-087--data-preview-effluent-plant. Local (offline) read/write
 * access to the `effluent_plant_record` / `effluent_plant_detail` tables
 * (schema defined in localSchema.ts) — mirrors threshingRecordRepo.ts's
 * repo style (plain async functions over localDb.ts's `query`/`run`
 * primitives) exactly, since Effluent Plant follows the same hourly-grid
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
 * option list (FormEffluentPlantView.vue's `availableTimeSlotOptions()`)
 * and for `getDraftWithDetails()`'s sort order.
 *
 * UNLIKE threshingRecordRepo.ts: this station has NO operational-target
 * reference table — no static-constant data file, no Target Operasional
 * section on any of its 3 mobile screens.
 *
 * "Filled" row, for the required-row validation only (not a DB
 * constraint): an effluent_plant_detail row counts as filled when at least
 * one of its 19 non-time_slot columns is non-null/non-empty — unlike
 * Process Water, this station has no identifying/context columns (no
 * shift/inspector_id equivalent), so all 19 columns participate.
 *
 * The 3 enum columns (biogas_flare_status, dosing_pump_1_status,
 * sludge_dewatering_status) are stored as plain TEXT in local SQLite (no
 * CHECK constraint here — that only applies on the backend), so no
 * empty-string coercion is required in this repo; `|| null` already
 * normalizes '' the same way it does for every other optional column.
 */

export type EffluentPlantDraftStatus = 'draft_ongoing' | 'draft_paused'
export type EffluentPlantRecordStatus = EffluentPlantDraftStatus | 'saved' | 'synced'

/**
 * Full local `effluent_plant_record` row shape (mirrors localSchema.ts's
 * CREATE_EFFLUENT_PLANT_RECORD column-for-column).
 */
export interface EffluentPlantRecord {
  id: string
  station_id: string | null
  effluent_plant_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  checked_by_name?: string | null
  acknowledged_by_name?: string | null
  status: EffluentPlantRecordStatus
  created_by: string
  created_at: string
  updated_at: string
}

/**
 * Full local `effluent_plant_detail` row shape (mirrors localSchema.ts's
 * CREATE_EFFLUENT_PLANT_DETAIL column-for-column).
 */
export interface EffluentPlantDetailRow {
  id: string
  effluent_plant_record_id: string
  time_slot: string
  anaerobic_pond_1_ph: number | null
  anaerobic_pond_1_temp_c: number | null
  anaerobic_pond_2_ph: number | null
  anaerobic_pond_2_temp_c: number | null
  cooling_pond_ph: number | null
  cooling_pond_temp_c: number | null
  biogas_flare_status: string | null
  biogas_flow_rate_m3h: number | null
  raw_pome_feed_rate_m3h: number | null
  effluent_discharge_flow_rate_m3h: number | null
  final_discharge_ph: number | null
  final_discharge_bod_mgl_lab: number | null
  final_discharge_cod_mgl_lab: number | null
  final_discharge_tss_mgl_lab: number | null
  dosing_pump_1_status: string | null
  chemical_consumed_kgl: number | null
  sludge_dewatering_status: string | null
  remarks_maintenance_actions: string | null
  findings: string | null
  created_at: string
  updated_at: string
}

export interface EffluentPlantDraftWithDetails {
  record: EffluentPlantRecord
  details: EffluentPlantDetailRow[]
}

/**
 * Subset of `EffluentPlantRecord` columns the Form Effluent Plant screen
 * collects from the user (excludes id/station_id/created_by/created_at/
 * updated_at/status, which are managed by createDraft()/saveDraft()
 * itself).
 */
export interface EffluentPlantHeaderFormData {
  effluent_plant_id: string
  date: string
  note: string
  checked_by: string
  acknowledged_by: string
}

/**
 * One editable `effluent_plant_detail` row as held in
 * FormEffluentPlantView.vue's form state. `id` is present for rows loaded
 * from an existing draft (UPDATE target) and absent for rows added in this
 * editing session (INSERT target) — `saveDraft()`/
 * `pauseDraftWithFormData()` upsert on exactly this distinction, mirroring
 * ThreshingDetailFormRow exactly. `time_slot` is `null` until the user
 * picks one from the dropdown for a newly-added row.
 */
export interface EffluentPlantDetailFormRow {
  id?: string
  time_slot: string | null
  anaerobic_pond_1_ph: number | null
  anaerobic_pond_1_temp_c: number | null
  anaerobic_pond_2_ph: number | null
  anaerobic_pond_2_temp_c: number | null
  cooling_pond_ph: number | null
  cooling_pond_temp_c: number | null
  biogas_flare_status: string | null
  biogas_flow_rate_m3h: number | null
  raw_pome_feed_rate_m3h: number | null
  effluent_discharge_flow_rate_m3h: number | null
  final_discharge_ph: number | null
  final_discharge_bod_mgl_lab: number | null
  final_discharge_cod_mgl_lab: number | null
  final_discharge_tss_mgl_lab: number | null
  dosing_pump_1_status: string | null
  chemical_consumed_kgl: number | null
  sludge_dewatering_status: string | null
  remarks_maintenance_actions: string | null
  findings: string | null
}

/**
 * User roles a saveDraft()/pauseDraftWithFormData() caller may pass as
 * currentUserRole — plain string union, matching
 * threshingRecordRepo.ts's ThreshingActorRole, decoupling this repo from
 * the auth store.
 */
export type EffluentPlantActorRole = 'operator' | 'supervisor' | 'mill_management' | 'admin'

/**
 * screen-075--form-effluent-plant business_logic step 12 — thrown by
 * saveDraft() when zero rows in the given `details` are "valid" (a
 * selected `time_slot` AND at least one filled reading column), so callers
 * (FormEffluentPlantView.vue) can distinguish this from other save
 * failures — mirrors ThreshingDetailRequiredError exactly.
 */
export class EffluentPlantDetailRequiredError extends Error {
  constructor() {
    super(
      'Minimal 1 baris Effluent Plant Detail (Time-Slot terpilih + minimal 1 kolom bacaan terisi) harus diisi sebelum menyimpan.',
    )
    this.name = 'EffluentPlantDetailRequiredError'
  }
}

export interface EffluentPlantDraftListItem {
  id: string
  status: EffluentPlantDraftStatus
  effluent_plant_id: string | null
  updated_at: string
}

interface DraftListRow {
  id: string
  status: EffluentPlantDraftStatus
  effluent_plant_id: string | null
  updated_at: string
}

export interface EffluentPlantTodaySummary {
  countEffluentPlantRecord: number
  detailRowCount: number
}

interface TodayRecordIdRow {
  id: string
}

interface DetailRowCountRow {
  detail_row_count: number
}

const DRAFT_STATUSES: EffluentPlantDraftStatus[] = ['draft_ongoing', 'draft_paused']

function nowIso(): string {
  return new Date().toISOString()
}

function generateId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `epr-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function generateDetailId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `epd-${Date.now()}-${Math.random().toString(16).slice(2)}`
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
 * Returns true when an effluent_plant_detail row has at least one of its
 * 19 non-time_slot columns non-null/non-empty — see this file's header
 * comment for the "filled row" definition (no identifying columns to
 * exclude on this station).
 */
function isRowFilled(row: {
  anaerobic_pond_1_ph: number | null
  anaerobic_pond_1_temp_c: number | null
  anaerobic_pond_2_ph: number | null
  anaerobic_pond_2_temp_c: number | null
  cooling_pond_ph: number | null
  cooling_pond_temp_c: number | null
  biogas_flare_status: string | null
  biogas_flow_rate_m3h: number | null
  raw_pome_feed_rate_m3h: number | null
  effluent_discharge_flow_rate_m3h: number | null
  final_discharge_ph: number | null
  final_discharge_bod_mgl_lab: number | null
  final_discharge_cod_mgl_lab: number | null
  final_discharge_tss_mgl_lab: number | null
  dosing_pump_1_status: string | null
  chemical_consumed_kgl: number | null
  sludge_dewatering_status: string | null
  remarks_maintenance_actions: string | null
  findings: string | null
}): boolean {
  return (
    row.anaerobic_pond_1_ph !== null ||
    row.anaerobic_pond_1_temp_c !== null ||
    row.anaerobic_pond_2_ph !== null ||
    row.anaerobic_pond_2_temp_c !== null ||
    row.cooling_pond_ph !== null ||
    row.cooling_pond_temp_c !== null ||
    (row.biogas_flare_status !== null && row.biogas_flare_status !== '') ||
    row.biogas_flow_rate_m3h !== null ||
    row.raw_pome_feed_rate_m3h !== null ||
    row.effluent_discharge_flow_rate_m3h !== null ||
    row.final_discharge_ph !== null ||
    row.final_discharge_bod_mgl_lab !== null ||
    row.final_discharge_cod_mgl_lab !== null ||
    row.final_discharge_tss_mgl_lab !== null ||
    (row.dosing_pump_1_status !== null && row.dosing_pump_1_status !== '') ||
    row.chemical_consumed_kgl !== null ||
    (row.sludge_dewatering_status !== null && row.sludge_dewatering_status !== '') ||
    (row.remarks_maintenance_actions !== null && row.remarks_maintenance_actions !== '') ||
    (row.findings !== null && row.findings !== '')
  )
}

/**
 * screen-065--monitor-effluent-plant business_logic step 5 — 'New Data'.
 * INSERTs a new effluent_plant_record row ONLY (status=draft_ongoing,
 * created_by=current user, date=now — auto-filled once at creation,
 * mirrors threshingRecordRepo.ts's createDraft()) — no
 * effluent_plant_detail rows are pre-created; rows are added one at a time
 * by the user via "Tambah baris" in FormEffluentPlantView.vue. Returns the
 * new record's id so the caller can navigate to Form Effluent Plant with
 * it.
 */
export async function createDraft(userId: string): Promise<string> {
  const id = generateId()
  const timestamp = nowIso()

  await run(
    `INSERT INTO effluent_plant_record (id, status, created_by, date, created_at, updated_at)
     VALUES (?, 'draft_ongoing', ?, ?, ?, ?)`,
    [id, userId, timestamp, timestamp, timestamp],
  )

  return id
}

/**
 * screen-065--monitor-effluent-plant business_logic step 2 — every local
 * effluent_plant_record the current user has ongoing or paused,
 * most-recently-updated first. Mirrors threshingRecordRepo.ts's
 * getDrafts().
 */
export async function getDrafts(userId: string): Promise<EffluentPlantDraftListItem[]> {
  const rows = await query<DraftListRow>(
    `SELECT id, status, effluent_plant_id, updated_at
     FROM effluent_plant_record
     WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
     ORDER BY updated_at DESC`,
    [userId],
  )

  return rows.map((row) => ({
    id: row.id,
    status: row.status,
    effluent_plant_id: row.effluent_plant_id,
    updated_at: row.updated_at,
  }))
}

/**
 * screen-065--monitor-effluent-plant business_logic step 1 — "Hari Ini"
 * counters. "Jumlah Effluent Plant Record" counts the current user's
 * effluent_plant_record rows (any status) dated today (device-local day
 * boundary). "Jumlah Baris Time-Slot Tercatat" is a plain COUNT of
 * effluent_plant_detail rows belonging to those same records — mirrors
 * threshingRecordRepo.ts's getTodaySummary() exactly.
 */
export async function getTodaySummary(userId: string): Promise<EffluentPlantTodaySummary> {
  const idRows = await query<TodayRecordIdRow>(
    `SELECT id
     FROM effluent_plant_record
     WHERE created_by = ? AND date(date) = date('now', 'localtime')`,
    [userId],
  )

  const countEffluentPlantRecord = idRows.length

  if (countEffluentPlantRecord === 0) {
    return { countEffluentPlantRecord: 0, detailRowCount: 0 }
  }

  const placeholders = idRows.map(() => '?').join(', ')

  const countRows = await query<DetailRowCountRow>(
    `SELECT COUNT(*) AS detail_row_count FROM effluent_plant_detail WHERE effluent_plant_record_id IN (${placeholders})`,
    idRows.map((row) => row.id),
  )

  return {
    countEffluentPlantRecord,
    detailRowCount: countRows[0]?.detail_row_count ?? 0,
  }
}

/**
 * screen-085--data-preview-effluent-plant business_logic step 1 — every
 * local effluent_plant_record row for the current user, ANY status,
 * most-recently-updated first. Mirrors threshingRecordRepo.ts's
 * getAllRecords().
 */
export async function getAllRecords(userId: string): Promise<EffluentPlantRecord[]> {
  return query<EffluentPlantRecord>(
    `SELECT * FROM effluent_plant_record WHERE created_by = ? ORDER BY updated_at DESC`,
    [userId],
  )
}

/**
 * screen-075--form-effluent-plant business_logic step 1 — Form Process
 * Water loads an existing draft by route param id, plus all of its
 * effluent_plant_detail rows (however many the user has added so far),
 * ordered by time_slot ascending starting at 07:00 (canonical order, not
 * alphabetical — '00:00' would otherwise sort before '07:00'). Returns
 * null when no header row matches (defensive).
 */
export async function getDraftWithDetails(recordId: string): Promise<EffluentPlantDraftWithDetails | null> {
  const recordRows = await query<EffluentPlantRecord>(`SELECT * FROM effluent_plant_record WHERE id = ?`, [recordId])
  const record = recordRows[0]

  if (!record) {
    return null
  }

  const rawDetails = await query<EffluentPlantDetailRow>(
    `SELECT * FROM effluent_plant_detail WHERE effluent_plant_record_id = ?`,
    [recordId],
  )

  const order = canonicalTimeSlots()
  const details = [...rawDetails].sort((a, b) => order.indexOf(a.time_slot) - order.indexOf(b.time_slot))

  return { record, details }
}

/**
 * screen-075--form-effluent-plant — upserts every row in `rows` against
 * `effluent_plant_detail` (rows with an existing `id` UPDATEd in place,
 * rows without one INSERTed with a freshly generated id via
 * `generateDetailId()`), then DELETEs every id in `idsToDelete` (rows the
 * user removed via "Hapus baris" on an already-loaded draft). Shared by
 * `saveDraft()` and `pauseDraftWithFormData()` so both stay in lock-step
 * on this upsert/delete contract — mirrors threshingRecordRepo.ts's
 * `applyDetailRowChanges()` exactly.
 */
async function applyDetailRowChanges(
  recordId: string,
  rows: EffluentPlantDetailFormRow[],
  idsToDelete: string[],
  timestamp: string,
): Promise<void> {
  for (const row of rows) {
    if (row.id) {
      await run(
        `UPDATE effluent_plant_detail
         SET time_slot = ?,
             anaerobic_pond_1_ph = ?,
             anaerobic_pond_1_temp_c = ?,
             anaerobic_pond_2_ph = ?,
             anaerobic_pond_2_temp_c = ?,
             cooling_pond_ph = ?,
             cooling_pond_temp_c = ?,
             biogas_flare_status = ?,
             biogas_flow_rate_m3h = ?,
             raw_pome_feed_rate_m3h = ?,
             effluent_discharge_flow_rate_m3h = ?,
             final_discharge_ph = ?,
             final_discharge_bod_mgl_lab = ?,
             final_discharge_cod_mgl_lab = ?,
             final_discharge_tss_mgl_lab = ?,
             dosing_pump_1_status = ?,
             chemical_consumed_kgl = ?,
             sludge_dewatering_status = ?,
             remarks_maintenance_actions = ?,
             findings = ?,
             updated_at = ?
         WHERE id = ?`,
        [
          row.time_slot,
          row.anaerobic_pond_1_ph,
          row.anaerobic_pond_1_temp_c,
          row.anaerobic_pond_2_ph,
          row.anaerobic_pond_2_temp_c,
          row.cooling_pond_ph,
          row.cooling_pond_temp_c,
          row.biogas_flare_status || null,
          row.biogas_flow_rate_m3h,
          row.raw_pome_feed_rate_m3h,
          row.effluent_discharge_flow_rate_m3h,
          row.final_discharge_ph,
          row.final_discharge_bod_mgl_lab,
          row.final_discharge_cod_mgl_lab,
          row.final_discharge_tss_mgl_lab,
          row.dosing_pump_1_status || null,
          row.chemical_consumed_kgl,
          row.sludge_dewatering_status || null,
          row.remarks_maintenance_actions || null,
          row.findings || null,
          timestamp,
          row.id,
        ],
      )
      continue
    }

    const detailId = generateDetailId()

    await run(
      `INSERT INTO effluent_plant_detail
         (id, effluent_plant_record_id, time_slot, anaerobic_pond_1_ph, anaerobic_pond_1_temp_c, anaerobic_pond_2_ph, anaerobic_pond_2_temp_c, cooling_pond_ph, cooling_pond_temp_c, biogas_flare_status, biogas_flow_rate_m3h, raw_pome_feed_rate_m3h, effluent_discharge_flow_rate_m3h, final_discharge_ph, final_discharge_bod_mgl_lab, final_discharge_cod_mgl_lab, final_discharge_tss_mgl_lab, dosing_pump_1_status, chemical_consumed_kgl, sludge_dewatering_status, remarks_maintenance_actions, findings, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        detailId,
        recordId,
        row.time_slot,
        row.anaerobic_pond_1_ph,
        row.anaerobic_pond_1_temp_c,
        row.anaerobic_pond_2_ph,
        row.anaerobic_pond_2_temp_c,
        row.cooling_pond_ph,
        row.cooling_pond_temp_c,
        row.biogas_flare_status || null,
        row.biogas_flow_rate_m3h,
        row.raw_pome_feed_rate_m3h,
        row.effluent_discharge_flow_rate_m3h,
        row.final_discharge_ph,
        row.final_discharge_bod_mgl_lab,
        row.final_discharge_cod_mgl_lab,
        row.final_discharge_tss_mgl_lab,
        row.dosing_pump_1_status || null,
        row.chemical_consumed_kgl,
        row.sludge_dewatering_status || null,
        row.remarks_maintenance_actions || null,
        row.findings || null,
        timestamp,
        timestamp,
      ],
    )
  }

  for (const id of idsToDelete) {
    await run(`DELETE FROM effluent_plant_detail WHERE id = ?`, [id])
  }
}

/**
 * screen-075--form-effluent-plant business_logic step 12 — 'Simpan'.
 *
 * Throws EffluentPlantDetailRequiredError when zero rows in the given
 * `details` are "valid" (a selected `time_slot` AND at least one filled
 * reading column — see isRowFilled()) — enforced here (not only as a
 * client-side pre-check in FormEffluentPlantView.vue) so the rule holds
 * even if a caller bypasses the UI. No DB write happens at all in that
 * case. Mirrors threshingRecordRepo.ts's saveDraft() "at least one valid
 * row" gate exactly. Required-header-field validation (effluent_plant_id)
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
  headerData: EffluentPlantHeaderFormData,
  details: EffluentPlantDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: EffluentPlantActorRole | null | undefined,
): Promise<EffluentPlantDraftWithDetails> {
  const validRowCount = details.filter(
    (row) => row.time_slot !== null && row.time_slot !== undefined && row.time_slot !== '' && isRowFilled(row),
  ).length

  if (validRowCount === 0) {
    throw new EffluentPlantDetailRequiredError()
  }

  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE effluent_plant_record
     SET effluent_plant_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'saved',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.effluent_plant_id || null,
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
    throw new Error('Gagal memuat ulang data effluent plant setelah disimpan.')
  }

  return saved
}

/**
 * screen-075--form-effluent-plant business_logic step 13 — 'Pause'.
 * UPDATEs the header as-is (no required-field validation, no "at least
 * one valid row" gate), status='draft_paused', then applies the same
 * upsert/delete contract as saveDraft() via applyDetailRowChanges(). Same
 * role-stripping as saveDraft(). Returns the freshly paused draft.
 */
export async function pauseDraftWithFormData(
  recordId: string,
  headerData: EffluentPlantHeaderFormData,
  details: EffluentPlantDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: EffluentPlantActorRole | null | undefined,
): Promise<EffluentPlantDraftWithDetails> {
  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE effluent_plant_record
     SET effluent_plant_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'draft_paused',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.effluent_plant_id || null,
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
    throw new Error('Gagal menyimpan progres effluent plant setelah pause.')
  }

  return saved
}

/**
 * screen-075--form-effluent-plant business_logic step 14 — 'Clear' (after
 * UI confirm). Permanently DELETEs the record, cascading the delete to
 * all of its effluent_plant_detail rows first (application-level cascade,
 * same pattern as threshingRecordRepo.ts's deleteDraft()).
 */
export async function deleteDraft(recordId: string): Promise<void> {
  await run(`DELETE FROM effluent_plant_detail WHERE effluent_plant_record_id = ?`, [recordId])
  await run(`DELETE FROM effluent_plant_record WHERE id = ?`, [recordId])
}

export const effluentPlantRecordRepo = {
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

export default effluentPlantRecordRepo

export const EFFLUENT_PLANT_DRAFT_STATUSES = DRAFT_STATUSES
