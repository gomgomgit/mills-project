import { query, run } from '@/services/localDb'

/**
 * storageTankRecordRepo — screen-066--monitor-storage-tank /
 * usecase-091--monitor-storage-tank, screen-076--form-storage-tank /
 * usecase-092--form-storage-tank, screen-086--data-preview-storage-tank /
 * usecase-093--data-preview-storage-tank. Local (offline) read/write access
 * to the `storage_tank_record` / `storage_tank_detail` tables (schema
 * defined in localSchema.ts) — mirrors effluentPlantRecordRepo.ts's repo
 * style (plain async functions over localDb.ts's `query`/`run` primitives)
 * exactly, since Storage Tank follows the same hourly-grid (dynamic
 * add-row/remove-row, 1..24 rows) pattern as Effluent Plant.
 *
 * `createDraft()` inserts ONLY the header row — no detail rows at all. Rows
 * are added one at a time via `applyDetailRowChanges()` (called from
 * `saveDraft()`/`pauseDraftWithFormData()`, mirrors
 * effluentPlantRecordRepo.ts's `applyDetailRowChanges()`): rows with an
 * existing `id` are UPDATEd, rows without one are INSERTed with a freshly
 * generated id via `generateDetailId()`, and every id in the
 * caller-supplied `idsToDelete` list is DELETEd after the upserts.
 * `canonicalTimeSlots()` is the source of truth for the dropdown's ordered
 * option list (FormStorageTankView.vue's `availableTimeSlotOptions()`) and
 * for `getDraftWithDetails()`'s sort order.
 *
 * UNLIKE threshingRecordRepo.ts: this station has NO operational-target
 * reference table — no static-constant data file, no Target Operasional
 * section on any of its 3 mobile screens.
 *
 * "Filled" row, for the required-row validation only (not a DB constraint):
 * a storage_tank_detail row counts as filled when at least one of its 17
 * non-time_slot columns is non-null/non-empty — like Effluent Plant, this
 * station has no identifying/context columns (no shift/inspector_id
 * equivalent), so all 17 columns participate.
 *
 * The 1 enum column (steam_heating_valve_status) is stored as plain TEXT in
 * local SQLite (no CHECK constraint here — that only applies on the
 * backend), so no empty-string coercion is required in this repo; `|| null`
 * already normalizes '' the same way it does for every other optional
 * column.
 */

export type StorageTankDraftStatus = 'draft_ongoing' | 'draft_paused'
export type StorageTankRecordStatus = StorageTankDraftStatus | 'saved' | 'synced'

/**
 * Full local `storage_tank_record` row shape (mirrors localSchema.ts's
 * CREATE_STORAGE_TANK_RECORD column-for-column).
 */
export interface StorageTankRecord {
  id: string
  station_id: string | null
  storage_tank_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  status: StorageTankRecordStatus
  created_by: string
  created_at: string
  updated_at: string
}

/**
 * Full local `storage_tank_detail` row shape (mirrors localSchema.ts's
 * CREATE_STORAGE_TANK_DETAIL column-for-column).
 */
export interface StorageTankDetailRow {
  id: string
  storage_tank_record_id: string
  time_slot: string
  cpo_sounding_depth_mm: number | null
  water_dip_bottom_depth_mm: number | null
  net_oil_depth_mm: number | null
  oil_temperature_top_c: number | null
  oil_temperature_middle_c: number | null
  oil_temperature_bottom_c: number | null
  average_temperature_c: number | null
  calculated_volume_m3: number | null
  calculated_weight_mt: number | null
  ffa_percent: number | null
  moisture_content_percent: number | null
  impurities_dirt_percent: number | null
  dobi_index: number | null
  steam_heating_valve_status: string | null
  tank_structural_condition: string | null
  inspector_name: string | null
  findings: string | null
  created_at: string
  updated_at: string
}

export interface StorageTankDraftWithDetails {
  record: StorageTankRecord
  details: StorageTankDetailRow[]
}

/**
 * Subset of `StorageTankRecord` columns the Form Storage Tank screen
 * collects from the user (excludes id/station_id/created_by/created_at/
 * updated_at/status, which are managed by createDraft()/saveDraft() itself).
 */
export interface StorageTankHeaderFormData {
  storage_tank_id: string
  date: string
  note: string
  checked_by: string
  acknowledged_by: string
}

/**
 * One editable `storage_tank_detail` row as held in
 * FormStorageTankView.vue's form state. `id` is present for rows loaded
 * from an existing draft (UPDATE target) and absent for rows added in this
 * editing session (INSERT target) — `saveDraft()`/
 * `pauseDraftWithFormData()` upsert on exactly this distinction, mirroring
 * EffluentPlantDetailFormRow exactly. `time_slot` is `null` until the user
 * picks one from the dropdown for a newly-added row.
 */
export interface StorageTankDetailFormRow {
  id?: string
  time_slot: string | null
  cpo_sounding_depth_mm: number | null
  water_dip_bottom_depth_mm: number | null
  net_oil_depth_mm: number | null
  oil_temperature_top_c: number | null
  oil_temperature_middle_c: number | null
  oil_temperature_bottom_c: number | null
  average_temperature_c: number | null
  calculated_volume_m3: number | null
  calculated_weight_mt: number | null
  ffa_percent: number | null
  moisture_content_percent: number | null
  impurities_dirt_percent: number | null
  dobi_index: number | null
  steam_heating_valve_status: string | null
  tank_structural_condition: string | null
  inspector_name: string | null
  findings: string | null
}

/**
 * User roles a saveDraft()/pauseDraftWithFormData() caller may pass as
 * currentUserRole — plain string union, matching
 * effluentPlantRecordRepo.ts's EffluentPlantActorRole, decoupling this repo
 * from the auth store.
 */
export type StorageTankActorRole = 'operator' | 'supervisor' | 'mill_management' | 'admin'

/**
 * screen-076--form-storage-tank business_logic step 12 — thrown by
 * saveDraft() when zero rows in the given `details` are "valid" (a selected
 * `time_slot` AND at least one filled reading column), so callers
 * (FormStorageTankView.vue) can distinguish this from other save failures —
 * mirrors EffluentPlantDetailRequiredError exactly.
 */
export class StorageTankDetailRequiredError extends Error {
  constructor() {
    super(
      'Minimal 1 baris Storage Tank Detail (Time-Slot terpilih + minimal 1 kolom bacaan terisi) harus diisi sebelum menyimpan.',
    )
    this.name = 'StorageTankDetailRequiredError'
  }
}

export interface StorageTankDraftListItem {
  id: string
  status: StorageTankDraftStatus
  storage_tank_id: string | null
  updated_at: string
}

interface DraftListRow {
  id: string
  status: StorageTankDraftStatus
  storage_tank_id: string | null
  updated_at: string
}

export interface StorageTankTodaySummary {
  countStorageTankRecord: number
  detailRowCount: number
}

interface TodayRecordIdRow {
  id: string
}

interface DetailRowCountRow {
  detail_row_count: number
}

const DRAFT_STATUSES: StorageTankDraftStatus[] = ['draft_ongoing', 'draft_paused']

function nowIso(): string {
  return new Date().toISOString()
}

function generateId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `str-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function generateDetailId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `std-${Date.now()}-${Math.random().toString(16).slice(2)}`
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
 * Returns true when a storage_tank_detail row has at least one of its 17
 * non-time_slot columns non-null/non-empty — see this file's header comment
 * for the "filled row" definition (no identifying columns to exclude on
 * this station).
 */
function isRowFilled(row: {
  cpo_sounding_depth_mm: number | null
  water_dip_bottom_depth_mm: number | null
  net_oil_depth_mm: number | null
  oil_temperature_top_c: number | null
  oil_temperature_middle_c: number | null
  oil_temperature_bottom_c: number | null
  average_temperature_c: number | null
  calculated_volume_m3: number | null
  calculated_weight_mt: number | null
  ffa_percent: number | null
  moisture_content_percent: number | null
  impurities_dirt_percent: number | null
  dobi_index: number | null
  steam_heating_valve_status: string | null
  tank_structural_condition: string | null
  inspector_name: string | null
  findings: string | null
}): boolean {
  return (
    row.cpo_sounding_depth_mm !== null ||
    row.water_dip_bottom_depth_mm !== null ||
    row.net_oil_depth_mm !== null ||
    row.oil_temperature_top_c !== null ||
    row.oil_temperature_middle_c !== null ||
    row.oil_temperature_bottom_c !== null ||
    row.average_temperature_c !== null ||
    row.calculated_volume_m3 !== null ||
    row.calculated_weight_mt !== null ||
    row.ffa_percent !== null ||
    row.moisture_content_percent !== null ||
    row.impurities_dirt_percent !== null ||
    row.dobi_index !== null ||
    (row.steam_heating_valve_status !== null && row.steam_heating_valve_status !== '') ||
    (row.tank_structural_condition !== null && row.tank_structural_condition !== '') ||
    (row.inspector_name !== null && row.inspector_name !== '') ||
    (row.findings !== null && row.findings !== '')
  )
}

/**
 * screen-066--monitor-storage-tank business_logic step 5 — 'New Data'.
 * INSERTs a new storage_tank_record row ONLY (status=draft_ongoing,
 * created_by=current user, date=now — auto-filled once at creation, mirrors
 * effluentPlantRecordRepo.ts's createDraft()) — no storage_tank_detail rows
 * are pre-created; rows are added one at a time by the user via "Tambah
 * baris" in FormStorageTankView.vue. Returns the new record's id so the
 * caller can navigate to Form Storage Tank with it.
 */
export async function createDraft(userId: string): Promise<string> {
  const id = generateId()
  const timestamp = nowIso()

  await run(
    `INSERT INTO storage_tank_record (id, status, created_by, date, created_at, updated_at)
     VALUES (?, 'draft_ongoing', ?, ?, ?, ?)`,
    [id, userId, timestamp, timestamp, timestamp],
  )

  return id
}

/**
 * screen-066--monitor-storage-tank business_logic step 2 — every local
 * storage_tank_record the current user has ongoing or paused,
 * most-recently-updated first. Mirrors effluentPlantRecordRepo.ts's
 * getDrafts().
 */
export async function getDrafts(userId: string): Promise<StorageTankDraftListItem[]> {
  const rows = await query<DraftListRow>(
    `SELECT id, status, storage_tank_id, updated_at
     FROM storage_tank_record
     WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
     ORDER BY updated_at DESC`,
    [userId],
  )

  return rows.map((row) => ({
    id: row.id,
    status: row.status,
    storage_tank_id: row.storage_tank_id,
    updated_at: row.updated_at,
  }))
}

/**
 * screen-066--monitor-storage-tank business_logic step 1 — "Hari Ini"
 * counters. "Jumlah Storage Tank Record" counts the current user's
 * storage_tank_record rows (any status) dated today (device-local day
 * boundary). "Jumlah Baris Time-Slot Tercatat" is a plain COUNT of
 * storage_tank_detail rows belonging to those same records — mirrors
 * effluentPlantRecordRepo.ts's getTodaySummary() exactly.
 */
export async function getTodaySummary(userId: string): Promise<StorageTankTodaySummary> {
  const idRows = await query<TodayRecordIdRow>(
    `SELECT id
     FROM storage_tank_record
     WHERE created_by = ? AND date(date) = date('now', 'localtime')`,
    [userId],
  )

  const countStorageTankRecord = idRows.length

  if (countStorageTankRecord === 0) {
    return { countStorageTankRecord: 0, detailRowCount: 0 }
  }

  const placeholders = idRows.map(() => '?').join(', ')

  const countRows = await query<DetailRowCountRow>(
    `SELECT COUNT(*) AS detail_row_count FROM storage_tank_detail WHERE storage_tank_record_id IN (${placeholders})`,
    idRows.map((row) => row.id),
  )

  return {
    countStorageTankRecord,
    detailRowCount: countRows[0]?.detail_row_count ?? 0,
  }
}

/**
 * screen-086--data-preview-storage-tank business_logic step 1 — every local
 * storage_tank_record row for the current user, ANY status,
 * most-recently-updated first. Mirrors effluentPlantRecordRepo.ts's
 * getAllRecords().
 */
export async function getAllRecords(userId: string): Promise<StorageTankRecord[]> {
  return query<StorageTankRecord>(
    `SELECT * FROM storage_tank_record WHERE created_by = ? ORDER BY updated_at DESC`,
    [userId],
  )
}

/**
 * screen-076--form-storage-tank business_logic step 1 — Form Storage Tank
 * loads an existing draft by route param id, plus all of its
 * storage_tank_detail rows (however many the user has added so far),
 * ordered by time_slot ascending starting at 07:00 (canonical order, not
 * alphabetical — '00:00' would otherwise sort before '07:00'). Returns null
 * when no header row matches (defensive).
 */
export async function getDraftWithDetails(recordId: string): Promise<StorageTankDraftWithDetails | null> {
  const recordRows = await query<StorageTankRecord>(`SELECT * FROM storage_tank_record WHERE id = ?`, [recordId])
  const record = recordRows[0]

  if (!record) {
    return null
  }

  const rawDetails = await query<StorageTankDetailRow>(
    `SELECT * FROM storage_tank_detail WHERE storage_tank_record_id = ?`,
    [recordId],
  )

  const order = canonicalTimeSlots()
  const details = [...rawDetails].sort((a, b) => order.indexOf(a.time_slot) - order.indexOf(b.time_slot))

  return { record, details }
}

/**
 * screen-076--form-storage-tank — upserts every row in `rows` against
 * `storage_tank_detail` (rows with an existing `id` UPDATEd in place, rows
 * without one INSERTed with a freshly generated id via
 * `generateDetailId()`), then DELETEs every id in `idsToDelete` (rows the
 * user removed via "Hapus baris" on an already-loaded draft). Shared by
 * `saveDraft()` and `pauseDraftWithFormData()` so both stay in lock-step on
 * this upsert/delete contract — mirrors effluentPlantRecordRepo.ts's
 * `applyDetailRowChanges()` exactly.
 */
async function applyDetailRowChanges(
  recordId: string,
  rows: StorageTankDetailFormRow[],
  idsToDelete: string[],
  timestamp: string,
): Promise<void> {
  for (const row of rows) {
    if (row.id) {
      await run(
        `UPDATE storage_tank_detail
         SET time_slot = ?,
             cpo_sounding_depth_mm = ?,
             water_dip_bottom_depth_mm = ?,
             net_oil_depth_mm = ?,
             oil_temperature_top_c = ?,
             oil_temperature_middle_c = ?,
             oil_temperature_bottom_c = ?,
             average_temperature_c = ?,
             calculated_volume_m3 = ?,
             calculated_weight_mt = ?,
             ffa_percent = ?,
             moisture_content_percent = ?,
             impurities_dirt_percent = ?,
             dobi_index = ?,
             steam_heating_valve_status = ?,
             tank_structural_condition = ?,
             inspector_name = ?,
             findings = ?,
             updated_at = ?
         WHERE id = ?`,
        [
          row.time_slot,
          row.cpo_sounding_depth_mm,
          row.water_dip_bottom_depth_mm,
          row.net_oil_depth_mm,
          row.oil_temperature_top_c,
          row.oil_temperature_middle_c,
          row.oil_temperature_bottom_c,
          row.average_temperature_c,
          row.calculated_volume_m3,
          row.calculated_weight_mt,
          row.ffa_percent,
          row.moisture_content_percent,
          row.impurities_dirt_percent,
          row.dobi_index,
          row.steam_heating_valve_status || null,
          row.tank_structural_condition || null,
          row.inspector_name || null,
          row.findings || null,
          timestamp,
          row.id,
        ],
      )
      continue
    }

    const detailId = generateDetailId()

    await run(
      `INSERT INTO storage_tank_detail
         (id, storage_tank_record_id, time_slot, cpo_sounding_depth_mm, water_dip_bottom_depth_mm, net_oil_depth_mm, oil_temperature_top_c, oil_temperature_middle_c, oil_temperature_bottom_c, average_temperature_c, calculated_volume_m3, calculated_weight_mt, ffa_percent, moisture_content_percent, impurities_dirt_percent, dobi_index, steam_heating_valve_status, tank_structural_condition, inspector_name, findings, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        detailId,
        recordId,
        row.time_slot,
        row.cpo_sounding_depth_mm,
        row.water_dip_bottom_depth_mm,
        row.net_oil_depth_mm,
        row.oil_temperature_top_c,
        row.oil_temperature_middle_c,
        row.oil_temperature_bottom_c,
        row.average_temperature_c,
        row.calculated_volume_m3,
        row.calculated_weight_mt,
        row.ffa_percent,
        row.moisture_content_percent,
        row.impurities_dirt_percent,
        row.dobi_index,
        row.steam_heating_valve_status || null,
        row.tank_structural_condition || null,
        row.inspector_name || null,
        row.findings || null,
        timestamp,
        timestamp,
      ],
    )
  }

  for (const id of idsToDelete) {
    await run(`DELETE FROM storage_tank_detail WHERE id = ?`, [id])
  }
}

/**
 * screen-076--form-storage-tank business_logic step 12 — 'Simpan'.
 *
 * Throws StorageTankDetailRequiredError when zero rows in the given
 * `details` are "valid" (a selected `time_slot` AND at least one filled
 * reading column — see isRowFilled()) — enforced here (not only as a
 * client-side pre-check in FormStorageTankView.vue) so the rule holds even
 * if a caller bypasses the UI. No DB write happens at all in that case.
 * Mirrors effluentPlantRecordRepo.ts's saveDraft() "at least one valid row"
 * gate exactly. Required-header-field validation (storage_tank_id) is the
 * caller's job — this function assumes the header has already been
 * validated.
 *
 * checked_by/acknowledged_by role-stripping: mirrors
 * effluentPlantRecordRepo.ts's saveDraft() — whenever currentUserRole is
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
  headerData: StorageTankHeaderFormData,
  details: StorageTankDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: StorageTankActorRole | null | undefined,
): Promise<StorageTankDraftWithDetails> {
  const validRowCount = details.filter(
    (row) => row.time_slot !== null && row.time_slot !== undefined && row.time_slot !== '' && isRowFilled(row),
  ).length

  if (validRowCount === 0) {
    throw new StorageTankDetailRequiredError()
  }

  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE storage_tank_record
     SET storage_tank_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'saved',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.storage_tank_id || null,
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
    throw new Error('Gagal memuat ulang data storage tank setelah disimpan.')
  }

  return saved
}

/**
 * screen-076--form-storage-tank business_logic step 13 — 'Pause'. UPDATEs
 * the header as-is (no required-field validation, no "at least one valid
 * row" gate), status='draft_paused', then applies the same upsert/delete
 * contract as saveDraft() via applyDetailRowChanges(). Same role-stripping
 * as saveDraft(). Returns the freshly paused draft.
 */
export async function pauseDraftWithFormData(
  recordId: string,
  headerData: StorageTankHeaderFormData,
  details: StorageTankDetailFormRow[],
  detailIdsToDelete: string[],
  currentUserRole: StorageTankActorRole | null | undefined,
): Promise<StorageTankDraftWithDetails> {
  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE storage_tank_record
     SET storage_tank_id = ?,
         date = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'draft_paused',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.storage_tank_id || null,
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
    throw new Error('Gagal menyimpan progres storage tank setelah pause.')
  }

  return saved
}

/**
 * screen-076--form-storage-tank business_logic step 14 — 'Clear' (after UI
 * confirm). Permanently DELETEs the record, cascading the delete to all of
 * its storage_tank_detail rows first (application-level cascade, same
 * pattern as effluentPlantRecordRepo.ts's deleteDraft()).
 */
export async function deleteDraft(recordId: string): Promise<void> {
  await run(`DELETE FROM storage_tank_detail WHERE storage_tank_record_id = ?`, [recordId])
  await run(`DELETE FROM storage_tank_record WHERE id = ?`, [recordId])
}

export const storageTankRecordRepo = {
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

export default storageTankRecordRepo

export const STORAGE_TANK_DRAFT_STATUSES = DRAFT_STATUSES
